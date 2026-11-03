<?php

namespace App\Support;

use App\Http\Controllers\Dashboard\PartyStatementController;
use App\Models\Bank;
use App\Models\ContractFlightPassenger;
use App\Models\TicketSale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The facts of each Party Statement row, worked out once so every print
 * table design (party-statement/partials/statement-table/*) shows the same
 * thing and differs only in layout (owner, 2026-10-13: "just the table,
 * redesign, add / remove columns, more information"). Read-only.
 */
class StatementRows
{
    public const KIND = [
        'party_discount' => 'Discount allowed', 'party_discount_reversal' => 'Discount reversed',
        'contract_file_client' => 'File process', 'contract_file' => 'File process cost', 'contract_file_service_cost' => 'File service cost',
        'contract_file_client_adjustment' => 'File adjustment', 'contract_file_vendor_adjustment' => 'File cost adjustment',
        'party_payment' => 'Payment', 'party_payment_reversal' => 'Payment reversal',
        'customer_advance_refund' => 'Advance returned', 'customer_advance_refund_reversal' => 'Advance return reversal',
        'customer_service_refund' => 'Service refund paid', 'customer_service_refund_reversal' => 'Service refund reversal',
        // an advance is a payment to the party (owner, 2026-10-27: no "customer" on the ledger)
        'customer_advance_received' => 'Payment', 'customer_advance_received_reversal' => 'Payment reversal', 'supplier_advance_paid_reversal' => 'Advance reversal',
        'contract_flight_service_cost' => 'Flight service cost', 'contract_flight_passenger_agent' => 'Flight process',
        'contract_flight_passenger_reissue' => 'Reissue', 'contract_flight_passenger_agent_reversal' => 'Package reversal',
        'contract_flight_passenger_change' => 'Flight change charge', 'vendor_refund' => 'Refund received',
        'contract_flight_airline_penalty' => 'Airline penalty', 'contract_flight_fare_difference' => 'Fare difference',
        'contract_flight_price_revision' => 'Package revised',
        'party_invoice' => 'Invoice', 'bank_transfer' => 'Transfer', 'ticket_sale' => 'Ticket sale', 'ticket_purchase' => 'Ticket purchase',
        'ticket_purchase_refund' => 'Ticket refund', 'visa_sale' => 'Visa invoice', 'visa_process' => 'Visa cost', 'opening_balance' => 'Opening balance',
        'ticket_refund_summary' => 'Ticket refund',
    ];

    /**
     * What build() needs besides the rows: each ticket sale's tickets and each
     * contract-flight charge's passenger, keyed by invoice_id, in a fixed
     * number of queries. The printed statement and the emailed one both load
     * them here, so the two can never describe a row differently.
     *
     * @return array{0: Collection, 1: Collection} [flightChargePassengers, ticketSaleItems]
     */
    public static function related(Collection $transactions): array
    {
        // Ticket-sale ledger rows are one row per invoice (the total); pull each
        // invoice's passenger/route breakdown up front so the statement can list
        // a sub-row per passenger without querying inside the Blade loop.
        $ticketSaleIds = $transactions->where('type', 'ticket_sale')->pluck('invoice_id')->filter()->unique();
        $ticketSaleItems = $ticketSaleIds->isEmpty() ? collect() : TicketSale::with([
            'items.ticketPurchase.passportHolder',
            'items.ticketPurchase.ticket.from_airport',
            'items.ticketPurchase.ticket.to_airport',
            'items.ticketPurchase.legs',
        ])->whereIn('id', $ticketSaleIds)->get()->keyBy('id');

        // Every contract-flight passenger charge (the agent's package price
        // AND a reissue billed to the agent) carries the passenger on
        // invoice_id, keyed the same way — pulled up front so the statement
        // can show who the charge is for, their passport number, and where
        // they currently stand in the flight pipeline, for both row types,
        // without querying inside the Blade loop.
        $flightChargeTypes = [
            ContractFlightPassenger::AGENT_LEDGER_TYPE,
            ContractFlightPassenger::REISSUE_LEDGER_TYPE,
        ];
        $flightChargeIds = $transactions->whereIn('type', $flightChargeTypes)
            ->pluck('invoice_id')->filter()->unique();
        $flightChargePassengers = $flightChargeIds->isEmpty() ? collect() : ContractFlightPassenger::with([
            'passportHolder',
            'flightCategory',
            // .route (ContractFlight::getRouteAttribute) reads the linked
            // ticket's airports — eager-loaded so the statement can show
            // it without a query per row. Only used as a fallback when no
            // ticket is linked yet.
            'contractFlight.ticket.from_airport',
            'contractFlight.ticket.to_airport',
            // Per-leg breakdown: each linked ticket purchase is one leg
            // (DAC→IST, IST→ECN, ...), matched to its route leg by
            // leg_sequence for that leg's own airline (Ticket::legsArray()
            // — a via route is one airline per sector, not one for the
            // whole ticket), and its own TicketLeg for the travel date.
            'ticketPurchases.ticket.routeLegs.fromAirport',
            'ticketPurchases.ticket.routeLegs.toAirport',
            'ticketPurchases.ticket.routeLegs.airline',
            'ticketPurchases.ticket.from_airport',
            'ticketPurchases.ticket.to_airport',
            'ticketPurchases.ticket.airline',
            'ticketPurchases.legs',
        ])
            ->whereIn('id', $flightChargeIds)->get()->keyBy('id');

        return [$flightChargePassengers, $ticketSaleItems];
    }

    /**
     * who / what / how per transaction id — what the Party Statement screen's
     * Details column shows (PartyStatementController::reasonsFor), plus the
     * passport holder for the row types that column does not cover. Reads
     * only; any failure leaves the rows without it, never the page.
     */
    private static function facts(Collection $transactions): array
    {
        $facts = [];
        try {
            [, $detail, $facts] = app(PartyStatementController::class)->reasonsFor($transactions);
            // The full record behind each row (the screen's "Details" drop-down) rides
            // along for the sales invoice, which lists it; the statement prints ignore it.
            foreach ($detail as $tid => $full) {
                $facts[$tid] = ($facts[$tid] ?? ['who' => [], 'what' => [], 'how' => '']) + ['detail' => $full];
            }
        } catch (\Throwable $e) {
            \Log::warning('Statement rows: reasonsFor failed — '.$e->getMessage());
        }

        // Not covered on screen: whose ticket a refund / reissue fee / change
        // charge / flight service cost is. invoice_id points at the record
        // named here (checked against the posting code and the data).
        $routes = [
            'ticket_purchase_refund' => 'purchase', 'contract_flight_reissue_fee' => 'purchase',
            'ticket_change_charge' => 'change', 'ticket_sale_reversal' => 'change',
            'contract_flight_service_cost' => 'service',
            // a cancelled return's package revision (2026-10-14): invoice_id = the flight change
            'contract_flight_price_revision' => 'cfchange',
        ];
        $want = $transactions->filter(fn ($t) => $t->invoice_id && isset($routes[$t->type]) && empty($facts[$t->id]['who']));
        if ($want->isEmpty()) {
            return $facts;
        }
        try {
            $by = fn (string $kind) => $want->filter(fn ($t) => $routes[$t->type] === $kind)->pluck('invoice_id')->unique()->values();
            $names = [];
            if (($ids = $by('purchase'))->isNotEmpty()) {
                $names['purchase'] = DB::table('ticket_purchases as p')->join('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                    ->whereIn('p.id', $ids)->pluck('h.name', 'p.id');
            }
            if (($ids = $by('change'))->isNotEmpty() && Schema::hasTable('ticket_changes')) {
                $names['change'] = DB::table('ticket_changes as c')->join('ticket_purchases as p', 'p.id', '=', 'c.ticket_purchase_id')
                    ->join('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')->whereIn('c.id', $ids)->pluck('h.name', 'c.id');
            }
            if (($ids = $by('cfchange'))->isNotEmpty()) {
                $names['cfchange'] = DB::table('contract_flight_passenger_changes as c')
                    ->join('contract_flight_passengers as cp', 'cp.id', '=', 'c.contract_flight_passenger_id')
                    ->join('passport_holders as h', 'h.id', '=', 'cp.passport_holder_id')->whereIn('c.id', $ids)->pluck('h.name', 'c.id');
            }
            if (($ids = $by('service'))->isNotEmpty()) {
                $names['service'] = DB::table('contract_flight_passenger_service_costs as s')
                    ->join('contract_flight_passengers as cp', 'cp.id', '=', 's.contract_flight_passenger_id')
                    ->join('passport_holders as h', 'h.id', '=', 'cp.passport_holder_id')->whereIn('s.id', $ids)->pluck('h.name', 's.id');
            }
            foreach ($want as $t) {
                if ($name = $names[$routes[$t->type]][$t->invoice_id] ?? null) {
                    $facts[$t->id] = ['who' => [$name], 'what' => [], 'how' => ''];
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Statement rows: holder lookup failed — '.$e->getMessage());
        }

        return $facts;
    }

    /** One array per transaction, in statement order. */
    public static function build(Collection $transactions, $flightChargePassengers, $ticketSaleItems, bool $perPassenger = true): Collection
    {
        $today = now()->startOfDay();
        $cumDr = 0.0;
        $cumCr = 0.0;
        // Bank names in one query, not one per row.
        $banks = collect();
        try {
            $ids = $transactions->pluck('account_id')->filter()->unique();
            $banks = $ids->isEmpty() ? collect() : Bank::whereIn('id', $ids)->pluck('name', 'id');
        } catch (\Throwable) {
        }
        $facts = self::facts($transactions);

        return $transactions->values()->map(function ($t, $i) use ($flightChargePassengers, $ticketSaleItems, $today, $banks, $facts, &$cumDr, &$cumCr) {
            $pax = (in_array($t->type, [ContractFlightPassenger::AGENT_LEDGER_TYPE, ContractFlightPassenger::REISSUE_LEDGER_TYPE], true) && $t->invoice_id)
                ? ($flightChargePassengers[$t->invoice_id] ?? null) : null;

            $legs = [];
            if ($pax) {
                $flightNos = $pax->contractFlight?->airline_flight_no ?: [];
                $flightNos = is_array($flightNos) ? $flightNos : explode(',', $flightNos);
                foreach ($pax->ticketPurchases->sortBy('leg_sequence') as $tp) {
                    $legsArr = $tp->ticket?->legsArray() ?: [];
                    $leg = $legsArr[max(0, ($tp->leg_sequence ?: 1) - 1)] ?? null;
                    $air = $leg['airline'] ?? $tp->ticket?->airline?->name ?? null;
                    $code = ($air && preg_match('/\(([^)]+)\)\s*$/', $air, $m)) ? $m[1] : $air;
                    $legs[] = [
                        'route' => ($leg['from_code'] ?? $tp->ticket?->from_airport?->code ?? '—').'→'.($leg['to_code'] ?? $tp->ticket?->to_airport?->code ?? '—'),
                        'pnr' => $tp->ticket_no ?: '—', 'airline' => $code,
                        'flight' => trim($flightNos[max(0, ($tp->leg_sequence ?: 1) - 1)] ?? ($flightNos[0] ?? '')),
                        'date' => optional($tp->legs->first())->travel_date ?: ($tp->departure_at ?? null),
                        'tkt' => $tp->name_ticket_no ?? null,
                    ];
                }
            }

            $saleItems = ($t->type === 'ticket_sale' && $t->invoice_id && isset($ticketSaleItems[$t->invoice_id]))
                ? $ticketSaleItems[$t->invoice_id]->items->map(fn ($it) => [
                    'pnr' => $it->ticketPurchase?->ticket_no ?: '—',
                    'passenger' => $it->ticketPurchase?->passportHolder?->name,
                    // The route title contains the complete current route (for
                    // example ECN-DAC-ECN). Using only from/to silently lost
                    // the return sector after a sold purchase was edited.
                    'route' => $it->ticketPurchase?->ticket?->title
                        ?: (($it->ticketPurchase?->ticket?->from_airport?->code ?: '—').'→'.($it->ticketPurchase?->ticket?->to_airport?->code ?: '—')),
                    'price' => (float) $it->price,
                    'tkt' => $it->ticketPurchase?->name_ticket_no,
                    'date' => optional($it->ticketPurchase?->legs?->first())->travel_date ?: ($it->ticketPurchase?->departure_at ?? null),
                ])->values()->all() : [];

            $holder = $pax?->passportHolder;
            $firstLeg = $legs[0]['date'] ?? null;
            $date = Carbon::parse($firstLeg ?: $t->payment_date);
            $debit = (float) $t->debit;
            $credit = (float) $t->credit;
            $cumDr += $debit;
            $cumCr += $credit;
            $bal = (float) $t->balance;
            $kind = self::KIND[$t->type] ?? Str::headline((string) $t->type);
            $particulars = $pax
                ? trim(($pax->contractFlight?->flight_number ? $pax->contractFlight->flight_number.' · ' : '').collect($legs)->pluck('route')->implode(' + '))
                : trim((string) ($t->display_remarks ?? $t->remarks ?? ''));
            $bank = $t->account_id ? ($banks[$t->account_id] ?? null) : null;
            $isPayment = in_array($t->type, [
                'party_payment', 'vendor_refund', 'bank_transfer',
                'customer_advance_refund', 'customer_advance_refund_reversal',
                'customer_service_refund', 'customer_service_refund_reversal',
            ], true)
                || ($credit > 0 && ((bool) $t->account_id || (bool) $t->payment_method));
            // One type covers both halves of some flows: a visa invoice's payment
            // is posted as visa_sale, a package charged again as the reversal type.
            if ($isPayment && $credit > 0 && in_array($t->type, ['visa_sale', 'ticket_sale', 'party_invoice', 'contract_file_client'], true)) {
                $kind = 'Payment';
            } elseif ($t->type === ContractFlightPassenger::AGENT_REVERSAL_TYPE && $debit > 0) {
                $kind = 'Package re-charged';
            }

            return [
                'id' => $t->id ?? null,
                'sl' => $i + 1,
                'date' => $date, 'posted' => Carbon::parse($t->payment_date),
                'time' => $t->created_at ? Carbon::parse($t->created_at)->format('H:i') : null,
                'ref' => $t->reference_no ?: ($t->invoice_id ? '#'.$t->invoice_id : '—'),
                'kind' => $kind, 'type' => $t->type,
                'is_payment' => $isPayment,
                // A vendor / trainer row (user_type 'supplier'): debit = what they
                // billed us, credit = what we paid them. Never flipped.
                'payable' => ($t->user_type ?? null) === 'supplier',
                'passenger' => $holder?->name ?? ($saleItems[0]['passenger'] ?? null),
                'passport' => $holder?->passport_no,
                'category' => $pax?->flightCategory?->name,
                'stage' => $pax ? ContractFlightPassenger::stageLabel($pax->stage) : null,
                'flag' => $pax?->exception_flag ? (ContractFlightPassenger::EXCEPTION_FLAGS[$pax->exception_flag] ?? Str::headline($pax->exception_flag)) : null,
                'legs' => $legs,
                'route' => $legs ? self::chain(collect($legs)->pluck('route')->all()) : ($saleItems ? collect($saleItems)->pluck('route')->unique()->implode(' · ') : null),
                'pnr' => $legs ? collect($legs)->pluck('pnr')->implode(', ') : ($saleItems ? collect($saleItems)->pluck('pnr')->implode(', ') : null),
                'flight' => $pax?->contractFlight?->flight_number,
                'particulars' => $particulars !== '' ? $particulars : $kind,
                'remarks' => ($t->user_type ?? null) === 'supplier'
                    ? trim((string) ($t->display_remarks ?? $t->remarks ?? ''))
                    : self::plain(self::withoutSplit((string) ($t->display_remarks ?? $t->remarks ?? ''))),
                'method' => $t->payment_method ? Str::headline($t->payment_method) : null,
                'bank' => $bank,
                'sale_items' => $saleItems,
                // Whose row it is and what for — the screen's Details column (owner,
                // 2026-10-14: "show these"), for rows that are not a passenger charge.
                'who' => array_values(array_filter((array) ($facts[$t->id]['who'] ?? []))),
                'what' => trim(implode(' · ', array_filter((array) ($facts[$t->id]['what'] ?? [])))),
                'how' => trim((string) ($facts[$t->id]['how'] ?? '')),
                'detail' => $facts[$t->id]['detail'] ?? null,
                'tkt_no' => collect($legs ?: $saleItems)->pluck('tkt')->filter()->implode(', ') ?: null,
                'flight_date' => ($fd = collect($legs ?: $saleItems)->pluck('date')->filter()->first()) ? Carbon::parse($fd) : null,
                'debit' => $debit, 'credit' => $credit, 'balance' => $bal,
                'cum_dr' => $cumDr, 'cum_cr' => $cumCr,
                'age' => $debit > 0 ? (int) $date->copy()->startOfDay()->diffInDays($today, false) : null,
                'month' => $date->format('Y-m'),
            ];
        })->flatMap(fn ($r) => $perPassenger ? self::perPassenger($r) : [$r])->values()->map(fn ($r, $i) => ['sl' => $i + 1] + $r);
    }

    /**
     * A ticket sale of several tickets as one row per passenger (owner, 2026-10-27: "each row
     * with one passenger", not "RIAD MIA +1 more"), each with its own price and the balance
     * carried ticket by ticket — the same rows the screen draws. Only when the prices add up
     * to the row's debit; otherwise the row stays whole.
     */
    private static function perPassenger(array $r): array
    {
        $items = $r['sale_items'];
        if (count($items) < 2 || $r['credit'] > 0 || abs(array_sum(array_column($items, 'price')) - $r['debit']) > 0.01) {
            return [$r];
        }
        $bal = $r['balance'] - $r['debit'];
        $cum = $r['cum_dr'] - $r['debit'];
        $out = [];
        foreach ($items as $it) {
            $bal += $it['price'];
            $cum += $it['price'];
            $out[] = array_merge($r, [
                'sale_items' => [$it],
                'passenger' => $it['passenger'],
                'route' => $it['route'],
                'pnr' => $it['pnr'],
                'tkt_no' => $it['tkt'] ?: null,
                'flight_date' => $it['date'] ? Carbon::parse($it['date']) : null,
                'debit' => $it['price'],
                'balance' => $bal,
                'cum_dr' => $cum,
            ]);
        }

        return $out;
    }

    /**
     * The agent sees ONE figure per charge (owner, 2026-10-13): the breakdown a
     * remark carries — "(airline penalty ৳3,000.00 + our charge ৳1,000.00)",
     * "(service fee ৳19,000)", "· refund ৳…" — never reaches his statement.
     * Same rule as reasonsFor() applies to the change notes on screen.
     */
    public static function withoutSplit(string $remark): string
    {
        $s = preg_replace('/\s*\([^()]*৳[^()]*\)/u', '', $remark);
        $s = implode(' · ', array_filter(array_map('trim', explode(' · ', $s)), fn ($x) => $x !== '' && ! str_contains($x, '৳')));

        return trim(preg_replace('/\s{2,}/u', ' ', $s));
    }

    /**
     * The words in each cell of the confirmed statement table (design 19,
     * owner 2026-10-14): every cell filled, a payment and a passenger service
     * the same two-line shape. Shared by the print (statement-table/v19) and
     * the emailed PDF (statement-table/v19-email).
     *
     * kind2 = line under the entry name; p1 / p2 (+ pnr) = Particulars;
     * c1 / c2 = Flight / Bank; dr / cr = word under each amount; tag = balance word.
     */
    public static function cell(array $r): array
    {
        $pax = (bool) $r['passenger'] && ! $r['sale_items'];
        $sale = $r['sale_items'] ? count($r['sale_items']) : 0;
        $remark = self::tidy($r['remarks']);
        // A one-word remark ("Flight", "Ticket") says what the money was for, not what happened.
        $thin = mb_strlen($remark) <= 12;
        $bank = $r['bank'] ? self::clean($r['bank']) : null;
        // The bank has its own column: "Bank Deposit Received — Dutch Bangla" drops the tail.
        if ($bank && ! $thin && preg_match('/^(.*\S)\s+—\s+([^—]+)$/u', $remark, $m) && stripos($bank, trim($m[2])) !== false) {
            $remark = $m[1];
        }
        $pnr = collect(explode(', ', (string) $r['pnr']))->filter()->unique()->first();
        $ref = $r['ref'] !== '—' ? $r['ref'] : null;
        $paid = $r['payable'] ? 'Paid' : 'Received';
        $flies = $r['flight_date'] ? 'Flies '.$r['flight_date']->format('d M Y') : null;
        $words = [
            'dr' => $r['payable'] ? 'Billed' : 'Charged',
            'cr' => $r['is_payment'] ? $paid : 'Credited',
            'tag' => self::balanceWord($r['balance']),
        ];

        if ($pax || $sale) {
            return $words + [
                'kind2' => $sale ? $sale.' ticket'.($sale > 1 ? 's' : '') : ($r['category'] ?: 'Passenger service'),
                'p1' => $r['passenger'].($sale > 1 ? ' +'.($sale - 1).' more' : ''),
                'p2' => collect([$sale ? null : $r['passport'], $r['route']])->filter()->implode('  ·  '),
                'pnr' => $pnr,
                'c1' => $sale ? ($ref ?: 'Ticket sale') : ($r['flight'] ?: 'Contract flight'),
                'c2' => $flies ?: ($sale ? 'Ticket sale' : ($r['stage'] ?: 'Date pending')),
            ];
        }
        $kind2 = $r['is_payment'] ? ($r['method'] ?: ($r['payable'] ? 'Payment' : 'Receipt')) : ($r['credit'] > 0 ? 'Credit' : ($r['payable'] ? 'Bill' : 'Charge'));
        $base = ['pnr' => null, 'c1' => $bank ?: ($r['method'] ?: 'Ledger'), 'c2' => $bank ? 'Bank account' : ($r['is_payment'] ? $paid : ($r['credit'] > 0 ? 'Credited' : 'Posted'))];

        // A file / visa / ticket / flight-change row: whose it is, then what for —
        // the screen's Details lines (owner, 2026-10-14). The entry name is
        // already in the Entry column, so the remark is not repeated as a title.
        if (! empty($r['who']) && ! $r['is_payment']) {
            // The screen's lines are kept as written (PNR · DAC → ECN is not re-cased);
            // only the passenger's name is dropped where the note repeats it below
            // their name, with any separator left dangling ("Reissue penalty — ").
            $what = $r['what'] !== '' ? self::clean($r['payable'] ? $r['what'] : self::withoutSplit($r['what'])) : $remark;
            foreach ($r['who'] as $name) {
                $what = preg_replace('/\s*[—·-]\s*'.preg_quote($name, '/').'(?=\s|\(|$)/iu', '', $what);
            }
            $what = preg_replace('/[\s—·-]+$/u', '', $what);

            return $words + $base + [
                'kind2' => $kind2,
                'p1' => $r['who'][0].(count($r['who']) > 1 ? ' +'.(count($r['who']) - 1).' more' : ''),
                'p2' => implode('  ·  ', array_filter([$what, $r['how']])),
            ];
        }
        // The voucher only when the remark does not already carry it.
        $voucher = $ref && mb_stripos($remark, $ref) === false ? 'Voucher '.$ref : null;
        if (str_starts_with((string) $r['type'], 'customer_advance_received')) {
            return $words + $base + [
                'kind2' => $kind2,
                'p1' => $thin ? 'Payment '.strtolower($paid) : $remark,
                'p2' => $r['credit'] > 0 ? 'Advance payment' : 'Advance payment reversed',
            ];
        }

        return $words + $base + [
            'kind2' => $kind2,
            'p1' => $thin ? ($r['is_payment'] ? 'Payment '.strtolower($paid) : $r['kind']) : $remark,
            'p2' => $thin ? ($remark !== '' ? 'For '.strtolower($remark) : ($voucher ?: 'Ledger entry')).($bank ? '  ·  '.$bank : '')
                          : ($bank ? 'Via '.$bank : ($voucher ?: ($r['method'] ?: 'Ledger entry'))),
        ];
    }

    /**
     * The ledger never says "customer" (owner, 2026-10-27): "TICKET — Customer advance received"
     * reads "TICKET — Advance payment received". Display only; the stored remark is unchanged.
     */
    public static function plain(string $s): string
    {
        $s = preg_replace('/\bcustomer advance received\b/iu', 'Advance payment received', $s);
        $s = preg_replace('/\b(agent)\s*\/\s*customer\b/iu', '$1', $s);
        $s = preg_replace('/\bcustomers?\s+/iu', '', $s);

        return trim(preg_replace('/\s{2,}/u', ' ', $s));
    }

    /** Due / Advance / Settled — the same words as the statement's closing-balance box. */
    public static function balanceWord(float $v): string
    {
        return $v > 0 ? 'Due' : ($v < 0 ? 'Advance' : 'Settled');
    }

    /** "ISLAMI BANK (Travels)" → "ISLAMI BANK": the company suffix says nothing to the party. */
    public static function clean(?string $s): string
    {
        return trim(preg_replace('/\s*[(-]\s*Travels\)?\s*$/i', '', (string) $s));
    }

    /**
     * Only an all-caps remark ("BANK DEPOSIT RECEIVED") is re-cased, and only its
     * plain words — PNRs and codes (CF-013, VINV-021, S7UFDS) are kept as typed.
     */
    public static function tidy(?string $s): string
    {
        $s = self::clean($s);
        if ($s === '' || $s !== mb_strtoupper($s)) {
            return $s;
        }

        return preg_replace_callback('/(?<![\w-])[A-Z]{2,}(?![\w-])/u', fn ($m) => ucfirst(strtolower($m[0])), $s);
    }

    /** Connecting legs read as one journey: DAC→IST + IST→ECN = DAC→IST→ECN. */
    public static function chain(array $routes): string
    {
        $out = '';
        foreach ($routes as $r) {
            [$from, $to] = array_pad(explode('→', $r, 2), 2, '');
            $out = $out === '' ? $r : (str_ends_with($out, '→'.$from) ? $out.'→'.$to : $out.' · '.$r);
        }

        return $out;
    }

    /** "12,500.00 Dr" / "Cr" / "0.00". */
    public static function bal(float $v): string
    {
        return number_format(abs($v), 2).($v > 0 ? ' Dr' : ($v < 0 ? ' Cr' : ''));
    }
}
