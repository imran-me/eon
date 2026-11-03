<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Company;
use App\Models\ContractFlight;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use App\Models\ContractFlightPassengerServiceCost;
use App\Models\ContractFlightSuspendWithdrawal;
use App\Models\JournalEntry;
use App\Models\PartyStatementLink;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CompanyFunds;
use App\Services\ContractFlightAgentReceipts;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Flight Accounts — the money side of Contract Flight on one page: the
 * profit & loss picture, the agent due sheet, the vendor due sheet, the
 * party-ledger transactions and the journal history behind them.
 *
 * Nothing here writes. Every figure is derived from what the operational
 * screens already recorded — the passenger rows (agent price, costs, paid
 * amounts), the flight batch (vendor cost), the linked PNRs and service
 * costs, the Party Statement ledger (transactions) and the GL (journal
 * entries) — so this page can never disagree with those screens; if it
 * did, the bug would be theirs.
 *
 * P&L uses the passenger as the unit, as CLAUDE.md says the business does:
 * revenue is the chargeable agent price (a cancelled / deported passenger
 * charges nothing — same rule as ReconcilesContractFlightAgent), cost is
 * total_travel_cost (reissue kept aside, as everywhere else).
 */
class ContractFlightAccountsController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:view contract flight|view all contract flight')];
    }

    public function index(Request $request, string $role)
    {
        return view('contract-flights.accounts', $this->figures($request) + ['role' => $role]);
    }

    /** Everything the accounts page, its header and the due sheets show. */
    private function figures(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : null;
        $agentId = $request->filled('agent_id') ? (int) $request->agent_id : null;

        // ── The passengers in range (by their flight's departure) ──
        $passengers = ContractFlightPassenger::query()
            ->with([
                'contractFlight.vendor:id,name', 'contractFlight.ticket.airline', 'contractFlight.ticket.from_airport', 'contractFlight.ticket.to_airport',
                'agent:id,name', 'passportHolder:id,name,passport_no', 'ticketPurchases.vendor:id,name', 'serviceCosts.vendor:id,name',
                'flightChanges',
            ])
            ->where(function ($q) use ($from, $to) {
                $q->whereHas('contractFlight', function ($f) use ($from, $to) {
                    if ($from) {
                        $f->where('departure_at', '>=', $from);
                    }
                    if ($to) {
                        $f->where('departure_at', '<=', $to);
                    }
                })
                // Under Cancelled (no flight): dated by the cancellation, so
                // his charges and penalties are not lost from the page.
                    ->orWhere(fn ($o) => $o->whereNull('contract_flight_id')->whereHas('flightChanges', function ($c) use ($from, $to) {
                        $c->where('kind', 'cancelled');
                        if ($from) {
                            $c->where('created_at', '>=', $from);
                        }
                        if ($to) {
                            $c->where('created_at', '<=', $to);
                        }
                    }));
            })
            ->when($agentId, fn ($q) => $q->where('agent_id', $agentId))
            ->get();

        $rows = $passengers->map(fn (ContractFlightPassenger $p) => $this->passengerFigures($p));
        $rows = $this->applyLedgerReceipts($rows);
        $ledgerBalances = $this->ledgerBalances($rows->pluck('agent_id')->filter()->unique()->values());

        // ── Overview / P&L ──
        $sum = fn (string $key) => (float) $rows->sum($key);
        $revenue = $sum('revenue');
        $cost = $sum('cost');
        $overview = [
            'passengers' => $rows->count(),
            'flights' => $rows->pluck('flight_id')->unique()->count(),
            'revenue' => $revenue,
            'ticket_cost' => $sum('ticket_cost'),
            'service_cost' => $sum('service_cost'),
            'change_cost' => $sum('change_cost'),
            'reissue_cost' => $sum('reissue_cost'),
            'cost' => $cost,
            'profit' => $revenue - $cost,
            'margin' => $revenue > 0 ? (($revenue - $cost) / $revenue) * 100 : null,
            'agent_received' => $sum('agent_paid'),
            'agent_due' => $sum('agent_due'),
            'voided' => $rows->where('chargeable', false)->count(),
        ];

        // Month by month, on departure month, newest first.
        $monthly = $rows->groupBy('month')->map(function (Collection $g, string $month) {
            $revenue = (float) $g->sum('revenue');
            $cost = (float) $g->sum('cost');

            return [
                'month' => $month,
                'label' => $month === 'undated' ? 'Undated' : Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'flights' => $g->pluck('flight_id')->unique()->count(),
                'passengers' => $g->count(),
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $revenue - $cost,
                'margin' => $revenue > 0 ? (($revenue - $cost) / $revenue) * 100 : null,
                'agent_due' => (float) $g->sum('agent_due'),
            ];
        })->sortKeysDesc()->values();

        // Flight by flight. Passengers off a flight are one row per month
        // ('fk' = off:YYYY-MM), so a month's figures never borrow another's.
        $flights = $rows->groupBy('fk')->map(function (Collection $g) {
            $first = $g->first();
            $revenue = (float) $g->sum('revenue');
            $cost = (float) $g->sum('cost');

            return [
                'fk' => $first['fk'],
                'flight_id' => $first['flight_id'],
                'flight_number' => $first['flight_number'],
                'airline' => $first['airline'],
                'route' => $first['route'],
                'departure_at' => $first['departure_at'],
                'status' => $first['status'],
                'agents' => $g->pluck('agent_name')->filter()->unique()->implode(', '),
                'passengers' => $g->count(),
                'revenue' => $revenue,
                'package' => (float) $g->sum('package'),
                'billed' => (float) $g->sum('billed'),
                'received' => (float) $g->sum('agent_paid'),
                'date' => $first['departure_at'] ? substr($first['departure_at'], 0, 10) : $g->pluck('date')->filter()->min(),
                'month' => $first['month'],
                'earned' => (bool) $first['earned'],
                'cost_incomplete' => $g->where('cost_incomplete', true)->count(),
                'vendor_paid' => (float) $g->sum('vendor_paid'),
                'billed_changes' => (float) $g->sum('billed'),
                'ticket_cost' => (float) $g->sum('ticket_cost'),
                'service_cost' => (float) $g->sum('service_cost'),
                'change_cost' => (float) $g->sum('change_cost'),
                'cost' => $cost,
                'profit' => $revenue - $cost,
                'margin' => $revenue > 0 ? (($revenue - $cost) / $revenue) * 100 : null,
                'agent_due' => (float) $g->sum('agent_due'),
            ];
        })->sortBy(fn ($f) => ContractFlight::nearestFirstKey($f['departure_at']))->values();

        // The passengers behind each flight row, for its expandable detail.
        $flightPax = $rows->groupBy('fk')->map->values();

        // ── Agent due sheet ──
        $agentSheet = $rows->whereNotNull('agent_id')->groupBy('agent_id')->map(function (Collection $g) use ($ledgerBalances) {
            $oldestDue = $g->where('agent_due', '>', 0)->min('departure_at');

            return [
                'agent_id' => $g->first()['agent_id'],
                'agent_name' => $g->first()['agent_name'],
                'passengers' => $g->count(),
                'flights' => $g->pluck('flight_id')->unique()->count(),
                'charged' => (float) $g->sum('revenue'),
                'received' => (float) $g->sum('agent_paid'),
                'due' => (float) $g->sum('agent_due'),
                'due_passengers' => $g->where('agent_due', '>', 0)->count(),
                'oldest_due_at' => $oldestDue,
                'oldest_due_days' => $oldestDue ? max(0, (int) Carbon::parse($oldestDue)->diffInDays(now(), false)) : null,
                'ledger_balance' => $ledgerBalances[$g->first()['agent_id']] ?? null,
            ];
        })->sortByDesc('due')->values();

        $duePassengers = $rows->where('agent_due', '>', 0)->sortBy(fn ($r) => ContractFlight::nearestFirstKey($r['departure_at']))->values();
        // The due table is drawn with the shared passenger row partial, which
        // wants the models; hand it those plus the ledger figures per passenger.
        $passengersById = $passengers->keyBy('id');
        $duePassengerModels = $duePassengers->map(fn ($r) => $passengersById[$r['id']])->values();
        $dueLedger = $duePassengers->mapWithKeys(fn ($r) => [$r['id'] => ['received' => $r['agent_paid'], 'due' => $r['agent_due'], 'agent_id' => $r['agent_id']]])->all();

        // ── Vendor due sheet — three kinds of payable, one row per vendor ──
        $vendorItems = collect();
        ContractFlight::query()
            ->with('vendor:id,name')
            ->whereNotNull('vendor_id')
            ->when($from, fn ($q) => $q->where('departure_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('departure_at', '<=', $to))
            ->where('cost_price', '>', 0)
            // cost_price on a flight with passengers is their own tickets +
            // services rolled up (ContractFlight::syncMoneyFromPassengers), which
            // are listed below item by item — counting it too would double it.
            ->whereDoesntHave('passengers')
            ->get()
            ->each(fn (ContractFlight $f) => $vendorItems->push([
                'vendor_id' => $f->vendor_id, 'vendor_name' => $f->vendor?->name ?? 'Vendor',
                'kind' => 'Flight cost', 'ref' => $f->flight_number, 'date' => $f->purchase_date ?? $f->departure_at,
                'billed' => (float) $f->cost_price, 'paid' => (float) $f->cost_paid_amount, 'due_date' => $f->payable_date,
                'month' => $f->departure_at?->format('Y-m') ?? 'undated',
            ]));
        // Each payable carries its passenger's P&L month, so the month's
        // vendor due (KPI strip) is the vendors of that month's passengers.
        $paxMonth = $rows->pluck('month', 'id');
        $passengers->flatMap(fn ($p) => $p->ticketPurchases->map(fn ($t) => [$t, $paxMonth[$p->id] ?? 'undated']))
            ->unique(fn ($x) => $x[0]->id)->filter(fn ($x) => $x[0]->vendor_id && $x[0]->status !== 'cancelled')
            ->each(fn ($x) => $vendorItems->push([
                'vendor_id' => $x[0]->vendor_id, 'vendor_name' => $x[0]->vendor?->name ?? 'Vendor',
                'kind' => 'Ticket', 'ref' => $x[0]->ticket_no ?: 'PNR pending', 'date' => $x[0]->purchase_date,
                'billed' => (float) $x[0]->amount, 'paid' => (float) $x[0]->paid_amount, 'due_date' => null, 'month' => $x[1],
            ]));
        $passengers->flatMap(fn ($p) => $p->serviceCosts->map(fn ($c) => [$c, $paxMonth[$p->id] ?? 'undated']))
            ->filter(fn ($x) => $x[0]->vendor_id && (float) $x[0]->amount > 0)
            ->each(fn ($x) => $vendorItems->push([
                'vendor_id' => $x[0]->vendor_id, 'vendor_name' => $x[0]->vendor?->name ?? 'Vendor',
                'kind' => $x[0]->type_label ?? Str::headline($x[0]->cost_type), 'ref' => $x[0]->reference_no ?: '—', 'date' => $x[0]->incurred_on,
                'billed' => (float) $x[0]->amount, 'paid' => (float) $x[0]->paid_amount, 'due_date' => $x[0]->due_date, 'month' => $x[1],
            ]));
        $vendorItems = $vendorItems->map(function ($i) {
            $i['due'] = max(0, $i['billed'] - $i['paid']);

            return $i;
        });
        $vendorBalances = $this->ledgerBalances($vendorItems->pluck('vendor_id')->unique()->values());
        $vendorSheet = $vendorItems->groupBy('vendor_id')->map(fn (Collection $g) => [
            'vendor_id' => $g->first()['vendor_id'],
            'vendor_name' => $g->first()['vendor_name'],
            'items' => $g->count(),
            'kinds' => $g->pluck('kind')->unique()->implode(', '),
            'billed' => (float) $g->sum('billed'),
            'paid' => (float) $g->sum('paid'),
            'due' => (float) $g->sum('due'),
            'due_items' => $g->where('due', '>', 0)->count(),
            'ledger_balance' => $vendorBalances[$g->first()['vendor_id']] ?? null,
        ])->sortByDesc('due')->values();
        $vendorDueItems = $vendorItems->where('due', '>', 0)->sortBy('date')->values();

        $kpi = $this->monthKpi($rows, $vendorItems, now()->format('Y-m'));

        // ── Transactions: the Party Statement rows this feature and its
        //    counter-payments produced ──
        $partyIds = $rows->pluck('agent_id')->filter()
            ->merge($vendorItems->pluck('vendor_id'))
            ->unique()->values();
        $cfTypes = [ContractFlightPassenger::AGENT_LEDGER_TYPE, ContractFlightPassenger::AGENT_REVERSAL_TYPE,
            ContractFlightPassenger::REISSUE_LEDGER_TYPE, ContractFlightPassengerChange::LEDGER_TYPE,
            ContractFlightPassengerChange::PENALTY_COMPANY_SOURCE, ContractFlightPassengerChange::FARE_COMPANY_SOURCE,
            ContractFlightPassengerChange::REISSUE_FEE_TYPE, ContractFlightPassengerChange::FIX_SOURCE,
            ContractFlightPassengerChange::PENALTY_LEDGER_TYPE, ContractFlightPassengerChange::FARE_LEDGER_TYPE,
            'contract_flight', ContractFlightPassengerServiceCost::JOURNAL_SOURCE,
            // a cancelled return's package revision (2026-10-14): agent row + its own journal
            ContractFlightPassengerChange::PRICE_REVISION_TYPE,
            // suspend withdraw (2026-11-02): vendor bill + agent's ONE line
            ContractFlightSuspendWithdrawal::VENDOR_TYPE, ContractFlightSuspendWithdrawal::AGENT_TYPE];
        $ticketIds = $passengers->flatMap->ticketPurchases->pluck('id')
            ->merge($passengers->flatMap->flightChanges->flatMap(fn ($c) => (array) ($c->previous_ticket_purchase_ids ?? [])))
            ->map(fn ($v) => (int) $v)->unique()->values();
        $transactions = Transaction::query()
            ->with('user:id,name', 'account:id,name')
            ->where(function ($q) use ($cfTypes, $partyIds, $ticketIds) {
                $q->whereIn('type', $cfTypes)
                    ->orWhere(fn ($w) => $w->where('type', 'party_payment')->whereIn('user_id', $partyIds))
                    ->orWhere(fn ($w) => $w->whereIn('type', ['ticket_purchase', 'ticket_purchase_refund', 'contract_flight_reissue_fee', 'contract_flight_reissue_fare'])->whereIn('invoice_id', $ticketIds));
            })
            ->when($agentId, fn ($q) => $q->where('user_id', $agentId))
            ->when($from, fn ($q) => $q->where('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('payment_date', '<=', $to))
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->limit(400)
            ->get();

        // ── GL check: income − expense on every journal line behind this
        //    feature's money, so the P&L can be tied back to the books. ──
        $gl = null;
        try {
            $line = DB::table('journal_items')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_items.account_id')
                // Replaced lines are soft-deleted, not removed — skip them or a
                // passenger saved five times counts five times.
                ->whereNull('journal_items.deleted_at')
                ->whereNull('journal_entries.deleted_at')
                ->where(function ($q) use ($cfTypes, $ticketIds) {
                    $q->whereIn('journal_entries.source', $cfTypes)
                        ->orWhere(fn ($w) => $w->whereIn('journal_entries.source', ['ticket_purchase', 'ticket_purchase_cancel'])->whereIn('journal_entries.source_id', $ticketIds));
                })
                ->when($from, fn ($q) => $q->whereDate('journal_entries.date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('journal_entries.date', '<=', $to))
                ->selectRaw("COALESCE(SUM(CASE WHEN accounts.code LIKE '4%' THEN journal_items.credit - journal_items.debit ELSE 0 END),0) income,
                             COALESCE(SUM(CASE WHEN accounts.code LIKE '5%' THEN journal_items.debit - journal_items.credit ELSE 0 END),0) expense")
                ->first();
            $gl = ['income' => (float) $line->income, 'expense' => (float) $line->expense, 'profit' => (float) $line->income - (float) $line->expense];
        } catch (\Throwable $e) {
            report($e);
        }

        // ── Journal history: the GL entries behind the same money ──
        $journal = JournalEntry::query()
            ->with(['items.account:id,code,name'])
            ->where(function ($q) use ($cfTypes, $ticketIds, $partyIds) {
                $q->whereIn('source', $cfTypes)
                    ->orWhere(fn ($w) => $w->whereIn('source', ['ticket_purchase', 'ticket_purchase_cancel'])->whereIn('source_id', $ticketIds))
                    ->orWhere(fn ($w) => $w->where('source', 'party_payment')->whereIn('source_id', $partyIds));
            })
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')->orderByDesc('id')
            ->limit(200)
            ->get();

        $agents = User::role('agent')->orderBy('name')->get(['id', 'name']);

        return compact(
            'overview', 'monthly', 'flights', 'agentSheet', 'duePassengers', 'duePassengerModels', 'dueLedger', 'vendorSheet', 'vendorDueItems',
            'transactions', 'journal', 'agents', 'from', 'to', 'agentId', 'flightPax', 'rows', 'gl', 'kpi'
        );
    }

    /**
     * The KPI strip: one month's P&L (owner, 2026-10-15: "current months").
     * Same rows the P&L tab sums, so the strip and that month's statement
     * agree; earlier months are under Profit & Loss → Past months.
     */
    private function monthKpi(Collection $rows, Collection $vendorItems, string $month): array
    {
        $g = $rows->where('month', $month);
        $sum = fn (string $key) => (float) $g->sum($key);
        $revenue = $sum('revenue');
        $cost = $sum('cost');
        $due = $g->where('agent_due', '>', 0);
        $vendors = $vendorItems->where('month', $month)->where('due', '>', 0);

        return [
            'month' => $month,
            'label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
            'passengers' => $g->count(),
            'flights' => $g->pluck('fk')->unique()->count(),
            'voided' => $g->where('chargeable', false)->count(),
            'revenue' => $revenue,
            'ticket_cost' => $sum('ticket_cost'),
            'service_cost' => $sum('service_cost'),
            'change_cost' => $sum('change_cost'),
            'reissue_cost' => $sum('reissue_cost'),
            'cost' => $cost,
            'profit' => $revenue - $cost,
            'margin' => $revenue > 0 ? (($revenue - $cost) / $revenue) * 100 : null,
            'agent_received' => $sum('agent_paid'),
            'agent_due' => $sum('agent_due'),
            'due_passengers' => $due->count(),
            'due_agents' => $due->pluck('agent_id')->filter()->unique()->count(),
            'vendor_due' => (float) $vendors->sum('due'),
            'vendor_due_items' => $vendors->count(),
            'vendor_due_vendors' => $vendors->pluck('vendor_id')->unique()->count(),
        ];
    }

    /**
     * Agent / Vendor / Company Ledger tabs of Flight Accounts: Party
     * Statement's own screens, opened on Travels (the company contract
     * flights belong to), so every detail and action is the same code.
     * $cfLedger only swaps the Party Statement tab band for this section's.
     */
    public function ledger(Request $request, string $role, string $ledger)
    {
        if (! $request->has('company')) {
            $request->merge(['company' => CompanyFunds::COMPANY_ID]);
        }
        view()->share('cfLedger', $ledger);
        // Only Contract Flight's own agents / vendors (owner, 2026-10-17) — PartyStatement
        // filters by it; a party with cross business shows in each department he used.
        $request->attributes->set('party_scope_dept', 'contract_flight');
        // The accounts header, switcher and (agents / vendors) due sheet
        // shown with the ledger. If it fails, the ledger still renders alone.
        try {
            view()->share('cfaDue', $this->figures($request) + ['role' => $role]);
        } catch (\Throwable $e) {
            report($e);
        }
        $ps = app(PartyStatementController::class);

        return match ($ledger) {
            'agents' => $ps->agents($request),
            'vendors' => $ps->vendors($request),
            'company' => $ps->company($request),
        };
    }

    /**
     * What the WhatsApp button needs for one party: their contact details
     * and a no-login short link to their statement PDF (the same link Party
     * Statement's own WhatsApp button uses — wa.me can carry text, never a
     * file). Email and SMS reminders go through Party Statement's existing
     * endpoints; this is the one piece that page keeps client-side.
     */
    public function shareLink(Request $request, string $role)
    {
        $request->validate(['party_id' => 'required|exists:users,id', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $party = User::findOrFail($request->party_id);

        $url = null;
        if (Route::has('party-statement.short-pdf')) {
            try {
                $url = route('party-statement.short-pdf', ['token' => PartyStatementLink::issue($party->id, $request->from ?: null, $request->to ?: null, auth()->id())->token]);
            } catch (\Throwable) {
                $url = null;
            }
        }

        return response()->json([
            'success' => true,
            'name' => $party->name, 'phone' => $party->phone, 'email' => $party->email,
            'share_pdf_url' => $url,
            'company' => optional(Company::first())->name ?: config('app.name'),
        ]);
    }

    /**
     * What each agent has actually paid, read off the Party Statement ledger
     * and allocated to their passengers oldest departure first — the same
     * allocation ContractFlightAgentReceipts writes onto the passenger rows
     * after every payment, so this page and Flights & Passengers agree.
     * Computed live here (not read from the rows) so a ledger row posted by
     * a path that has no hook still shows correctly.
     */
    private function applyLedgerReceipts(Collection $rows): Collection
    {
        ['credits' => $credits, 'allocated' => $allocated] = ContractFlightAgentReceipts::allocate($rows->pluck('agent_id'));

        return $rows->map(function ($r) use ($allocated, $credits) {
            if ($r['agent_id'] && ($credits[$r['agent_id']] ?? 0) > 0) {
                $r['agent_paid'] = $allocated[$r['id']] ?? 0.0;
                $r['agent_due'] = max(0, $r['revenue'] - $r['agent_paid']);
                $r['paid_source'] = 'ledger';
            } else {
                $r['paid_source'] = 'memo';
            }

            return $r;
        });
    }

    /** Each party's running Party Statement balance (positive = outstanding), keyed by user id. */
    private function ledgerBalances(Collection $partyIds): array
    {
        if ($partyIds->isEmpty()) {
            return [];
        }
        $latest = Transaction::whereIn('user_id', $partyIds)
            ->selectRaw('user_id, MAX(id) as last_id')->groupBy('user_id')->pluck('last_id', 'user_id');

        return Transaction::whereIn('id', $latest->values())->pluck('balance', 'user_id')->map(fn ($v) => (float) $v)->all();
    }

    /** One passenger, reduced to the figures every section sums. */
    private function passengerFigures(ContractFlightPassenger $p): array
    {
        $f = $p->contractFlight;
        $chargeable = ! in_array($p->exception_flag, ContractFlightPassenger::AGENT_CHARGE_VOIDING_FLAGS, true);
        // Same reader as the passenger card and Profit & Funds
        // (penaltyTotals): change charges, penalties and fare differences
        // billed to the agent are revenue; what the airline kept, reissue
        // fees and charges we bear are cost.
        $pen = $p->penaltyTotals();
        // the package less a cancelled return's price revision (2026-10-14)
        $revenue = ($chargeable ? $p->chargedPackage() : 0.0) + (float) ($pen['billed'] ?? 0) + $p->baggageTotals()['charge']
            + $p->suspendWithdrawTotals()['charge'];
        $paid = (float) ($p->agent_paid_amount ?? 0);
        $ticketCost = (float) $p->ticketPurchases->where('status', '!=', 'cancelled')->sum(fn ($t) => (float) $t->amount - $t->returnTakenOff()) + $p->baggageTotals()['cost'];
        $serviceCost = (float) ($p->manpower_cost ?? 0) + (float) ($p->boarding_cost ?? 0)
            + (float) ($p->immigration_cost ?? 0) + (float) ($p->other_cost ?? 0) + (float) ($p->manpower_card_fee ?? 0);
        // a suspend withdraw's vendor cost is ours to pay whoever bears it (2026-11-02)
        $changeCost = (float) ($pen['borne'] ?? 0) + $p->suspendWithdrawTotals()['cost'];
        $cost = $ticketCost + $serviceCost + $changeCost;
        $when = $f?->departure_at ?? ($f ? null : $p->flightChanges->firstWhere('kind', 'cancelled')?->created_at);
        $liveTickets = $p->ticketPurchases->where('status', '!=', 'cancelled');
        // Revenue is in but the trip's cost is not: a charged passenger whose
        // tickets are not all bought. His margin would read as near 100%.
        $costIncomplete = $chargeable && (float) ($p->agent_price ?? 0) > 0 && ! $p->isFullyTicketed();

        $month = $when?->format('Y-m') ?? 'undated';

        return [
            'id' => $p->id,
            // the P&L row he sits in: his flight, or "off flight" of his month
            'fk' => $f ? (string) $f->id : 'off:'.$month,
            'name' => $p->passportHolder?->name ?? 'Passenger',
            'passport_no' => $p->passportHolder?->passport_no,
            'flight_id' => $f?->id,
            'flight_number' => $f?->flight_number ?? 'Cancelled',
            'airline' => $f?->airline?->name ?? 'Airline pending',
            'route' => ($f?->ticket?->from_airport?->code ?? '—').' → '.($f?->ticket?->to_airport?->code ?? '—'),
            'departure_at' => $f?->departure_at?->toDateTimeString(),
            'month' => $month,
            'status' => $f?->status,
            'agent_id' => $p->agent_id,
            'agent_name' => $p->agent?->name,
            'stage' => ContractFlightPassenger::stageLabel($p->stage),
            'exception' => $p->exception_flag ? (ContractFlightPassenger::EXCEPTION_FLAGS[$p->exception_flag] ?? $p->exception_flag) : null,
            'chargeable' => $chargeable,
            'revenue' => $revenue,
            'package' => $chargeable ? $p->chargedPackage() : 0.0,
            'billed' => (float) ($pen['billed'] ?? 0),
            'agent_paid' => $paid,
            'date' => $when?->toDateString(),
            // Earned = the service was delivered: the flight has departed.
            'earned' => (bool) ($f?->departure_at && $f->departure_at->lte(now())),
            'cost_incomplete' => $costIncomplete,
            'vendor_paid' => (float) $liveTickets->sum('paid_amount') + (float) $p->serviceCosts->sum('paid_amount'),
            'agent_due' => max(0, $revenue - $paid),
            'ticket_cost' => $ticketCost,
            'service_cost' => $serviceCost,
            'change_cost' => $changeCost,
            'reissue_cost' => (float) ($p->reissue_cost ?? 0),
            'cost' => $cost,
            'profit' => $revenue - $cost,
        ];
    }
}
