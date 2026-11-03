<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Account;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PassportHolder;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\Ticket;
use App\Models\TicketPurchase;
use App\Models\User;
use App\Services\TicketImport\Lessons;
use App\Services\TicketImport\MasterData;
use App\Services\TicketImport\TicketImportMatcher;
use App\Services\TicketImport\TicketPdfImporter;
use App\Services\TicketImport\TicketSheetImporter;
use App\Support\TravelCompany;
use App\Traits\PostsPartyLedger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ticket Entry → "Import PDF": reads ticket PDFs and hands back what the
 * entry cards need. Writes NOTHING — the cards are filled in the browser
 * and saved by Ticket Entry's own store(), so the PNR, the vendor liability
 * and the journal are exactly what a typed entry makes.
 *
 * The PDF never comes here: pdf.js reads it in the browser and posts its text
 * lines (same approach as the bank statement compare). The file itself is
 * attached to each card, so it is stored with the ticket on Save as usual.
 */
class TicketImportController implements HasMiddleware
{
    use PostsPartyLedger;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:create ticket direct sale|create ticket purchase'),
        ];
    }

    public function read(Request $request)
    {
        $data = $request->validate([
            'files' => 'required|array|min:1|max:30',
            'files.*.name' => 'nullable|string|max:255',
            'files.*.lines' => 'present|array|max:5000',
            'files.*.lines.*' => 'nullable|string|max:2000',
        ]);

        // 1. read every file, 2. add any airline / airport they need that
        // Ticketing lacks, 3. match — after the adds, so they match at once.
        $importer = new TicketPdfImporter;
        $master = new MasterData;
        $results = [];
        foreach ($data['files'] as $file) {
            $name = (string) ($file['name'] ?? '');
            try {
                $read = $importer->read($file['lines'] ?? [], $name);
                $read['added'] = in_array($read['status'], ['read', 'partial'], true) ? $master->ensure($read) : [];
                $results[] = $read;
            } catch (\Throwable $e) {
                report($e);
                $results[] = ['status' => 'failed', 'message' => 'This file could not be read. Enter it by hand.'] + TicketPdfImporter::blank($name);
            }
        }
        $matcher = new TicketImportMatcher;
        foreach ($results as $k => $read) {
            try {
                $results[$k] = $matcher->match($read);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * Ticket Import → "Import CSV / Excel" (owner, 2026-10-19): one row per
     * passenger ticket, read into the same results read() gives a PDF —
     * missing airlines / airports added, then matched — so the page treats a
     * row exactly like a PDF ticket. The file is read and dropped, never stored.
     */
    public function sheet(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'extensions:csv,xlsx,xls'],
        ]);

        $read = (new TicketSheetImporter)->read($request->file('file'));
        if (! $read['ok']) {
            return response()->json(['success' => false, 'message' => $read['message']]);
        }
        $master = new MasterData;
        $matcher = new TicketImportMatcher;
        foreach ($read['results'] as $k => $res) {
            try {
                $res['added'] = $master->ensure($res);
                $read['results'][$k] = $matcher->match($res);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        unset($read['ok']);

        return response()->json(['success' => true] + $read);
    }

    /** The one-sheet Excel staff fill in every day and hand over for import. */
    public function template()
    {
        $book = TicketSheetImporter::template();

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, 'daily-tickets.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * After a Save: what staff typed on imported cards that the PDF did not
     * give is looked up in that PDF's lines and its label remembered
     * (Lessons::learn). Stores labels only. Never fails the user — a lesson
     * that cannot be learned is simply skipped.
     */
    public function learn(Request $request)
    {
        $data = $request->validate([
            'cards' => 'required|array|max:60',
            'cards.*.lines' => 'required|array|max:5000',
            'cards.*.lines.*' => 'nullable|string|max:2000',
            'cards.*.read' => 'nullable|array',
            'cards.*.final' => 'nullable|array',
            'cards.*.layout' => 'nullable|string|max:80',
        ]);
        $lessons = new Lessons;
        if (! $lessons::available()) {
            return response()->json(['success' => true, 'learned' => [], 'note' => 'not installed']);
        }
        $norm = fn ($v) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $v));
        $learned = [];
        foreach ($data['cards'] as $card) {
            try {
                $final = $card['final'] ?? [];
                $read = $card['read'] ?? [];
                // the passenger and route are chosen from lists — turn them into what the PDF prints
                if (! empty($final['holder_id']) && ($h = PassportHolder::find($final['holder_id']))) {
                    $final['name'] = $h->name;
                    $final['passport'] = $h->passport_no;
                }
                if (! empty($final['ticket_id']) && ($t = Ticket::with(['from_airport', 'to_airport'])->find($final['ticket_id']))) {
                    $final['from'] = $t->from_airport?->code;
                    $final['to'] = $t->to_airport?->code;
                }
                if (! empty($final['date'])) {
                    $final['date'] = substr((string) $final['date'], 0, 10);
                }
                // taught = saved but not read as such; wrong = read, then changed; confirmed = read and kept
                $taught = $wrong = $confirmed = [];
                foreach (Lessons::TEACHABLE as $f) {
                    $v = $final[$f] ?? null;
                    $was = $read[$f] ?? null;
                    $same = $f === 'name'
                        ? $was && ! array_diff(preg_split('/\W+/', strtoupper((string) $v), -1, PREG_SPLIT_NO_EMPTY), preg_split('/\W+/', strtoupper((string) $was), -1, PREG_SPLIT_NO_EMPTY))
                        : $norm($v) === $norm($was);
                    if ($v === null || $v === '') {
                        continue;
                    }
                    if (! $same) {
                        $taught[$f] = $v;
                        if ($was !== null && $was !== '') {
                            $wrong[$f] = $was;
                        }
                    } else {
                        $confirmed[] = $f;
                    }
                }
                if ($taught || $confirmed) {
                    $learned = array_merge($learned, $lessons->learn($card['lines'], $taught, $request->user()?->id,
                        $wrong, $confirmed, isset($card['layout']) ? (string) $card['layout'] : null));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['success' => true, 'learned' => array_values(array_unique($learned))]);
    }

    /**
     * The earlier ticket(s) an imported card collides with — same passenger,
     * route and leg — with what the "what happened to it?" step needs: its
     * money, seller, date, and the contract flight it is on, if any. Reads only.
     */
    public function conflict(Request $request)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        $ids = array_slice($ids, 0, 20);
        $out = [];
        try {
            foreach (TicketPurchase::with(['vendor:id,name', 'portal:id,name', 'legs', 'ticket:id,title'])->whereIn('id', $ids)->get() as $t) {
                $cf = DB::table('contract_flight_passenger_tickets as x')
                    ->join('contract_flight_passengers as p', 'p.id', '=', 'x.contract_flight_passenger_id')
                    ->leftJoin('contract_flights as f', 'f.id', '=', 'p.contract_flight_id')
                    ->leftJoin('users as a', 'a.id', '=', 'p.agent_id')
                    ->where('x.ticket_purchase_id', $t->id)
                    ->first(['p.id as passenger_id', 'p.contract_flight_id as flight_id', 'f.flight_number', 'p.agent_id', 'a.name as agent']);
                $leg = $t->legs->sortBy('id')->first();
                $out[] = [
                    'id' => $t->id,
                    'pnr' => $t->ticket_no,
                    'eticket' => $t->name_ticket_no ?? null,
                    'status' => $t->status,
                    'amount' => (float) $t->amount,
                    'seller' => $t->vendor?->name ?: $t->portal?->name,
                    'route' => $t->ticket?->title,
                    'leg' => $t->leg_sequence,
                    'flight_no' => $t->flight_no ?? null,
                    'departure' => $t->departure_at ? (string) $t->departure_at : ($leg?->travel_date ? (string) $leg->travel_date : null),
                    'purchase_date' => $t->purchase_date ? substr((string) $t->purchase_date, 0, 10) : null,
                    'contract_flight' => $cf ? [
                        'passenger_id' => (int) $cf->passenger_id,
                        'flight_id' => $cf->flight_id ? (int) $cf->flight_id : null,
                        'flight_no' => $cf->flight_number,
                        'agent_id' => $cf->agent_id ? (int) $cf->agent_id : null,
                        'agent' => $cf->agent,
                    ] : null,
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['success' => true, 'tickets' => $out]);
    }

    /**
     * "What happened to the earlier ticket?" — carried out through the code
     * that already owns each case, never new money logic here:
     *
     *  On a contract flight → ContractFlightRebookController::reassign() with
     *  stay_on_flight (reissue: old leg cancelled with the airline's refund,
     *  penalty placed by whose side, fare difference and fees by bearer;
     *  reschedule: same ticket, new date, fee by bearer), then the new
     *  ticket is linked to the passenger (linkTicket — which also settles a
     *  fare difference left blank and moves the stage).
     *
     *  Not on a contract flight → reissue: Ticketing's own cancel with the
     *  airline's refund (TicketPurchaseController::cancelFromContractFlight —
     *  rows kept, refund added beside them, the penalty stays in ticket cost);
     *  reschedule: the same ticket takes the new flight and date.
     *
     * Runs AFTER the new ticket is saved by Ticket Entry's own store(). Safe
     * to send twice: an old ticket already cancelled and a new one already
     * linked is reported as done, not posted again.
     */
    public function resolve(Request $request, string $role)
    {
        $v = $request->validate([
            'kind' => 'required|in:reissue,reschedule,join',
            'old_id' => 'required|integer|exists:ticket_purchases,id',
            'new_id' => 'nullable|required_if:kind,reissue|required_if:kind,join|integer|exists:ticket_purchases,id',
            'refund' => 'nullable|numeric|min:0',
            'fault' => 'nullable|in:agent,company',
            'fare_difference' => 'nullable|numeric|min:0',
            'fare_bearer' => 'nullable|in:agent,company',
            'fee' => 'nullable|numeric|min:0',
            'fee_bearer' => 'nullable|in:agent,company',
            'flight_no' => 'nullable|string|max:40',
            'departure_at' => 'nullable|date',
            'arrival_at' => 'nullable|date',
            'note' => 'nullable|string|max:500',
        ]);
        $old = TicketPurchase::with('legs')->find($v['old_id']);
        $new = ! empty($v['new_id']) ? TicketPurchase::find($v['new_id']) : null;
        if ($new && (int) $new->passport_holder_id !== (int) $old->passport_holder_id) {
            return $this->no('The new ticket is not the same passenger\'s.');
        }
        $pnrOld = $old->ticket_no ?: ('#'.$old->id);
        $note = trim('Imported ticket PDF'.($new ? ' — new PNR '.$new->ticket_no : '').(! empty($v['note']) ? ' · '.$v['note'] : ''));

        $cfp = ContractFlightPassenger::query()
            ->whereIn('id', DB::table('contract_flight_passenger_tickets')->where('ticket_purchase_id', $old->id)->pluck('contract_flight_passenger_id'))
            ->first();
        // Another leg of the same journey, entered later: when his earlier leg
        // is on a contract flight, this one goes onto the same passenger card
        // (linkTicket — which also completes "fully ticketed" and settles a
        // pending fare difference). Nothing else changes.
        if ($v['kind'] === 'join') {
            if (! $cfp) {
                return response()->json(['success' => true, 'message' => 'Joined the order of '.$pnrOld.'.']);
            }
            $already = DB::table('contract_flight_passenger_tickets')->where('contract_flight_passenger_id', $cfp->id)->where('ticket_purchase_id', $new->id)->exists();

            return $already
                ? response()->json(['success' => true, 'message' => $new->ticket_no.' is already on contract flight '.($cfp->contractFlight?->flight_number ?: '').'.'])
                : $this->linkNew($cfp, $new, $role, $new->ticket_no.' added to contract flight '.($cfp->contractFlight?->flight_number ?: '').' beside '.$pnrOld.'.');
        }

        // already done (a retry after a dropped connection): report, post nothing
        $newLinked = $new && $cfp && DB::table('contract_flight_passenger_tickets')
            ->where('contract_flight_passenger_id', $cfp->id)->where('ticket_purchase_id', $new->id)->exists();
        if ($v['kind'] === 'reissue' && $old->status === 'cancelled' && (! $cfp || $newLinked)) {
            return response()->json(['success' => true, 'message' => $pnrOld.' was already replaced.']);
        }
        if ($v['kind'] === 'reissue' && $old->status === 'cancelled' && $cfp) {
            // the reissue posted, only the link is missing
            return $this->linkNew($cfp, $new, $role, $pnrOld.' replaced by '.$new->ticket_no.'.');
        }
        $penalty = $v['kind'] === 'reissue' && ($v['refund'] ?? null) !== null ? max(0.0, (float) $old->amount - (float) $v['refund']) : 0.0;

        try {
            if ($cfp) {
                if (! auth()->user()?->can('edit contract flight')) {
                    return $this->no('This ticket is on contract flight — changing it needs the Contract Flight edit permission.');
                }
                if (! $cfp->contract_flight_id) {
                    return $this->no('The passenger is off his flight (under Cancelled). Re-assign him from Contract Flight first.');
                }
                // owner, 2026-09-28: nothing pre-selected for whose side — staff choose every time
                if ($penalty > 0 && empty($v['fault'])) {
                    return $this->no('Say whose side the airline penalty of ৳'.number_format($penalty, 2).' is on.');
                }
                if (! empty($v['fee']) && (float) $v['fee'] > 0 && empty($v['fee_bearer'])) {
                    return $this->no('Say who bears the ৳'.number_format((float) $v['fee'], 2).' fee.');
                }
                $charge = [
                    'ticket' => mb_substr($pnrOld, 0, 60),
                    'amount' => (float) ($v['fee'] ?? 0),
                    'bearer' => $v['fee_bearer'] ?? 'company',
                    'ticket_id' => $old->id,
                ];
                if ($v['kind'] === 'reschedule' && ! empty($v['departure_at'])) {
                    $charge['travel_date'] = $v['departure_at'];
                }
                $payload = [
                    'kind' => $v['kind'],
                    'contract_flight_id' => (string) $cfp->contract_flight_id,
                    'stay_on_flight' => 1,
                    'note' => $note,
                    'ticket_charges' => [$charge],
                ];
                if ($v['kind'] === 'reissue') {
                    $payload['only_tickets'] = (string) $old->id;
                    $payload['refunds'] = [$old->id => $v['refund'] ?? null];
                    if ($penalty > 0) {
                        $payload['faults'] = [$old->id => $v['fault']];
                        $payload['fault'] = $v['fault'];
                    }
                    if ($v['fare_bearer'] ?? null) {
                        $payload['fare_bearer'] = $v['fare_bearer'];
                        $payload['fare_difference'] = $v['fare_difference'] ?? null;
                    }
                }

                DB::beginTransaction();
                $res = app(ContractFlightRebookController::class)->reassign($this->inner($request, $payload), $role, $cfp);
                $data = $res->getData(true);
                if (empty($data['success'])) {
                    DB::rollBack();

                    return $this->no($data['message'] ?? 'Contract Flight refused the change.');
                }
                if ($v['kind'] === 'reschedule') {
                    $this->retime($old, $v);
                }
                DB::commit();

                return $v['kind'] === 'reissue'
                    ? $this->linkNew($cfp->fresh(), $new, $role, 'Contract flight '.($cfp->contractFlight?->flight_number ?: '').': '.$pnrOld.' replaced by '.$new->ticket_no.'.')
                    : response()->json(['success' => true, 'message' => 'Contract flight '.($cfp->contractFlight?->flight_number ?: '').': '.$pnrOld.' rescheduled.']);
            }

            // not on a contract flight: Ticketing's own cancel / the ticket's own fields
            $fee = round((float) ($v['fee'] ?? 0), 2);
            $posted = false;
            DB::transaction(function () use ($v, $old, $new, $note, $fee, &$posted) {
                if ($v['kind'] === 'reissue') {
                    $refund = ($v['refund'] ?? null) === null ? (float) $old->amount : min((float) $v['refund'], (float) $old->amount);
                    app(TicketPurchaseController::class)->cancelFromContractFlight($old, 'Reissued — replaced by PNR '.$new->ticket_no.' · '.$note, $refund);
                } else {
                    $this->retime($old, $v);
                }
                if ($fee > 0) {
                    $posted = $this->postChangeFee($old, $fee, $v['kind'], $v['kind'] === 'reissue' ? 'new PNR '.$new->ticket_no : ($v['departure_at'] ?? ''));
                }
            });
            $feeLine = $fee > 0 ? ($posted ? ' Fee ৳'.number_format($fee, 2).' posted to the seller.' : ' Fee ৳'.number_format($fee, 2).' was already posted.') : '';

            return response()->json(['success' => true, 'message' => ($v['kind'] === 'reissue'
                ? $pnrOld.' cancelled and replaced by '.$new->ticket_no.'.'
                : $pnrOld.' moved to its new date.').$feeLine]);
        } catch (ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return $this->no(collect($e->errors())->flatten()->first() ?: 'Not valid.');
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            report($e);

            return $this->no('Could not complete: '.$e->getMessage());
        }
    }

    /**
     * The airline / seller's change fee on a ticket that is NOT on a contract
     * flight (owner, 2026-10-05: "every penny … automatically and
     * errorlessly"). A ticket on a contract flight never comes here — its fee
     * goes through Contract Flight's own Re-assign, so one fee is posted in
     * exactly one place. Same shape as Contract Flight's reissue fee:
     *
     *   vendor seller   vendor row "Reissue fee" Dr fee      journal Dr 5110 / Cr AP
     *   portal seller   wallet debited                       journal Dr 5110 / Cr portal account
     *
     * Ticket cost (5110), because with no agent behind it the fee is simply
     * part of what this ticket cost us. Posted once per ticket + reason +
     * amount: a retry finds its own journal and posts nothing.
     */
    private function postChangeFee(TicketPurchase $t, float $fee, string $kind, string $why): bool
    {
        $pnr = (string) ($t->ticket_no ?: ('#'.$t->id));
        $ref = mb_substr($pnr.' · '.$kind.($why !== '' ? ' · '.$why : ''), 0, 190);
        $exists = JournalEntry::where('source', 'ticket_change_fee')->where('source_id', $t->id)->where('reference', $ref)
            ->whereHas('items', fn ($q) => $q->where('debit', $fee))->exists();
        if ($exists) {
            return false;
        }
        $costId = Account::where('code', config('accounts.ticket_purchase_cost'))->value('id');
        $crId = Account::where('code', config('accounts.accounts_payable'))->value('id');
        $vendorId = $t->vendor_id ? (int) $t->vendor_id : null;
        $portal = ! $vendorId && $t->portal_id ? Portal::find($t->portal_id) : null;
        if ($portal) {
            $crId = $portal->account_id;
        }
        if (! $costId || ! $crId || (! $vendorId && ! $portal)) {
            throw new \RuntimeException('The fee could not be posted: '.$pnr.' has no seller (vendor or portal) or the cost / payable account is missing.');
        }
        $label = ($kind === 'reissue' ? 'Reissue fee' : 'Change fee').' — '.$pnr.($why !== '' ? ' · '.$why : '').' · imported ticket PDF';

        if ($vendorId) {
            $this->postPartyLedgerRow($vendorId, [
                'type' => ContractFlightPassengerChange::REISSUE_FEE_TYPE,   // "Reissue fee" on every statement
                'invoice_id' => $t->id,
                'account_id' => null,
                'payment_date' => now(),
                'reference_no' => $pnr,
                'debit' => $fee,
                'credit' => 0,
                'remarks' => mb_substr($label, 0, 250),
            ]);
        } else {
            $opening = (float) $portal->balance;
            PortalBalance::create([
                'invoice_id' => $t->id, 'portal_id' => $portal->id, 'type' => 'ticket_change_fee',
                'old_balance' => $opening, 'debit' => 0, 'credit' => $fee, 'current_balance' => $opening - $fee,
                'remarks' => mb_substr($label, 0, 250), 'created_by' => auth()->id(),
            ]);
            $portal->update(['balance' => $opening - $fee]);
        }

        $entry = JournalEntry::create([
            'company_id' => TravelCompany::ID,
            'created_by' => auth()->id(),
            'date' => now()->toDateString(),
            'entry_time' => now()->format('H:i:s'),
            'reference' => $ref,
            'source' => 'ticket_change_fee',
            'source_id' => $t->id,
            'description' => mb_substr($label, 0, 250),
            'beneficiary' => $vendorId ? User::whereKey($vendorId)->value('name') : $portal?->name,
            'beneficiary_party_id' => $vendorId,
        ]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $costId, 'debit' => $fee, 'credit' => 0, 'note' => mb_substr($label, 0, 250)]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $crId, 'debit' => 0, 'credit' => $fee, 'note' => mb_substr($label, 0, 250),
            'party_type' => $vendorId ? 'vendor' : null, 'party_id' => $vendorId]);

        return true;
    }

    /** Same ticket, new flight / date (the PDF's): its own descriptive fields and first leg. No money. */
    private function retime(TicketPurchase $t, array $v): void
    {
        $set = [];
        foreach (['flight_no', 'departure_at', 'arrival_at'] as $col) {
            if (! empty($v[$col]) && Schema::hasColumn('ticket_purchases', $col)) {
                $set[$col] = $v[$col];
            }
        }
        if ($set) {
            $t->update($set);
        }
        if (! empty($v['departure_at'])) {
            $leg = $t->legs->sortBy('id')->first();
            $leg?->update(['travel_date' => $v['departure_at']]);
        }
    }

    private function linkNew(ContractFlightPassenger $cfp, TicketPurchase $new, string $role, string $done)
    {
        $res = app(ContractFlightPassengerController::class)->linkTicket($this->inner(request(), ['ticket_purchase_id' => $new->id]), $role, $cfp);
        $data = $res->getData(true);
        if (empty($data['success'])) {
            return $this->no($done.' But the new ticket could not be linked to the passenger: '.($data['message'] ?? '').' Link it from Contract Flight.');
        }

        return response()->json(['success' => true, 'message' => $done]);
    }

    /** A request for an internal call, carrying the signed-in user. */
    private function inner(Request $from, array $data): Request
    {
        $r = Request::create($from->url(), 'POST', $data);
        $r->setUserResolver(fn () => $from->user());
        $r->setLaravelSession($from->session());

        return $r;
    }

    private function no(string $message)
    {
        return response()->json(['success' => false, 'message' => $message], 422);
    }
}
