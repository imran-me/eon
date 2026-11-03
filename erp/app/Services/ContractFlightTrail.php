<?php

namespace App\Services;

use App\Http\Controllers\Dashboard\TicketPurchaseController;
use App\Models\Account;
use App\Models\Airline;
use App\Models\Bank;
use App\Models\ContractFlight;
use App\Models\ContractFlightPassenger as Pax;
use App\Models\ContractFlightPassengerChange as Change;
use App\Models\ContractFlightPassengerServiceCost as ServiceCost;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PartyLedgerNotification;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\Ticket;
use App\Models\TicketPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TicketTrail\Diff;
use App\Services\TicketTrail\EditLog;
use App\Services\TicketTrail\Footprint;
use App\Services\TicketTrail\Rewriter;
use App\Services\TicketTrail\TrailRefused;
use App\Support\TravelCompany;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsPurchaseJournal;
use App\Traits\PostsSaleJournal;
use App\Traits\ReconcilesContractFlightAgent;
use App\Traits\ReconcilesContractFlightChangeCharge;
use App\Traits\ReconcilesContractFlightServiceCosts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Contract Flight Trail (owner, 2026-11-01): every entry of ONE passenger — his package and
 * each change (re-issue, refund, cancel, re-assign, flag) — with every ledger row it posted,
 * editable, and corrected by REPLACING: "affect, replace, delete and create new entry in the
 * ledgers". The Ticketing Trail's rule, for the flight. Super admin only; opened from the
 * Re-assign window's header ("All Trails and Edit").
 *
 * The ticket's own side (cost, seller, PNR, payments) stays Ticketing's and is edited on the
 * Ticketing Trail; what the FLIGHT posted is edited here.
 *
 * Editing an entry, in one transaction:
 *   1. what each ledger holds for it is read FROM THE POSTINGS (ContractFlightChangeCorrection::
 *      posted(), the same reader Correct used) and the edits are applied to those figures
 *   2. every row the entry posted — agent line, seller rows and wallet rows, Company Ledger
 *      reclasses, fare rows, Correct's added rows, Undo's reversing rows — is removed (a verbatim
 *      copy goes to contract_flight_undo_logs)
 *   3. the entry is posted again, ONCE, in its original shape (reconcileChangeCharge and the
 *      first posting's own row types and texts) at the entry's own date and time
 *   4. a changed airline refund goes through Ticketing: its refund rows removed, the ticket
 *      cancelled again with the new refund (cancelFromContractFlight)
 *   5. every party chain and wallet touched is re-walked; every journal must balance
 * Deleting an entry removes its money AND what it did (owner): a refunded ticket goes back live
 * and onto him, a re-issued date goes back, a New ticket comes off him; a cancellation is the
 * existing ContractFlightCancellationUndo. Refused when a later entry depends on it.
 *
 * A preview is the same run rolled back, so what it shows is what Confirm posts.
 */
class ContractFlightTrail
{
    use PostsPartyLedger, PostsPurchaseJournal, PostsSaleJournal, ReconcilesContractFlightAgent, ReconcilesContractFlightChangeCharge, ReconcilesContractFlightServiceCosts;

    /** Journal sources a change posts with source_id = the change (and its halves). */
    private const CHANGE_SOURCES = [Change::LEDGER_TYPE, Change::FIX_SOURCE, Change::REISSUE_FEE_TYPE];

    /** Party rows keyed on the change id. */
    private const CHANGE_ROWS = [Change::LEDGER_TYPE, Change::FARE_LEDGER_TYPE];

    /** Seller rows of a re-issue fee — keyed on the TICKET, paired with their journal. */
    private const SELLER_ROWS = [Change::REISSUE_FEE_TYPE, Change::REISSUE_FARE_TYPE];

    /** Kinds whose delete also undoes what they did. A cancellation goes through its own Undo. */
    private const DELETABLE = ['ticket_reissue', 'ticket_refund', 'cancelled'];

    /** Moves between flights — undone one by one, latest first (owner, 2026-11-02: "undo one by one, so I can start from refund again"). */
    private const MOVES = ['reschedule', 'reissue'];

    private array $parties = [];

    private array $portals = [];

    /** highest ids before the run: tx, je, pb */
    private array $marks = ['tx' => 0, 'je' => 0, 'pb' => 0];

    private array $replaced = ['transactions' => [], 'journals' => [], 'portal_balances' => [], 'changes' => [], 'ticket_changes' => [], 'tickets' => []];

    private ?ContractFlightChangeCorrection $correction = null;

    public static function available(): bool
    {
        try {
            return ContractFlightChangeCorrection::available()
                && Schema::hasTable('contract_flight_undo_logs')
                && Route::has('role.contract-flight-trail.apply');
        } catch (\Throwable) {
            return false;
        }
    }

    private function corr(): ContractFlightChangeCorrection
    {
        return $this->correction ??= new ContractFlightChangeCorrection;
    }

    // ── reading ─────────────────────────────────────────────────────────

    /** The passenger as the Trail shows him: his entries, each with what it posted and what can be done. */
    public function state(Pax $p): array
    {
        $p->loadMissing(['passportHolder', 'contractFlight']);
        $fp = $this->footprint($p);
        $byEvent = collect($fp['rows'])->groupBy('event');
        $jByEvent = collect($fp['journals'])->groupBy('event');
        $entries = [[
            'key' => 'package', 'kind' => 'package', 'title' => 'Package (per head)',
            'at' => null, 'by' => null, 'note' => null,
            'ts' => $p->created_at?->format('Y-m-d H:i:s'), 'via' => 'Flight builder — passenger card',
            'form' => ['agent_id' => $p->agent_id ? (int) $p->agent_id : null, 'agent_price' => round((float) $p->agent_price, 2)],
            'edit_problem' => null, 'delete_problem' => 'The package is his charge for the trip — set it to 0 instead.',
        ]];
        foreach ($this->primaries($p) as $c) {
            $problem = ContractFlightChangeCorrection::problem($c);
            $form = null;
            if (! $problem && $c->kind !== 'restored') {
                try {
                    $form = $this->corr()->form($c);
                } catch (\Throwable $e) {
                    $problem = 'This entry cannot be read for editing: '.$e->getMessage();
                }
            }
            // a refund is edited on the entry that made it, not on every entry naming the ticket
            if ($form && ! empty($form['penalty'])) {
                $form['penalty']['refunds'] = array_values(array_filter($form['penalty']['refunds'],
                    fn ($r) => $this->refundOwner($p, (int) $r['ticket_id']) === (int) $c->id));
                if (! $form['penalty']['refunds'] && (float) $form['penalty']['amount'] <= 0) {
                    $form['penalty'] = null;
                }
            }
            if (! $problem && $form && $form['agent_id'] && ! User::whereKey($form['agent_id'])->exists()) {
                $problem = 'The agent billed on this entry (user #'.$form['agent_id'].') no longer exists — nothing can be posted to him.';
            }
            if (! $problem && $this->isOffload($c)) {
                $problem = 'Offload / deported entries also post deport costs and service refunds — editing them here is not built yet. Delete is not either.';
            }
            $entries[] = [
                'key' => 'change:'.$c->id, 'id' => (int) $c->id, 'kind' => $c->kind,
                'title' => (Change::KINDS[$c->kind] ?? $c->kind).$this->moveLabel($c),
                'reason' => $c->reason,
                'at' => $c->created_at?->format('Y-m-d H:i'), 'by' => $c->creator?->name,
                'ts' => $c->created_at?->format('Y-m-d H:i:s'), 'by_id' => $c->created_by ? (int) $c->created_by : null, 'via' => $this->viaOf($c),
                'note' => $c->note,
                'form' => $form,
                'edit_problem' => $c->kind === 'restored' ? 'Putting him back on the flight posted nothing.' : $problem,
                'delete_problem' => $this->deleteProblem($p, $c),
            ];
        }
        $entries = array_merge([$entries[0]], $this->ticketEntries($p), $this->serviceEntries($p), array_slice($entries, 1));
        foreach ($entries as &$e) {
            $e['rows'] = array_values($byEvent->get($e['key'], collect())->all());
            $e['journals'] = array_values($jByEvent->get($e['key'], collect())->all());
        }
        unset($e);

        return [
            'passenger' => [
                'id' => $p->id, 'name' => $p->passportHolder?->name, 'passport' => $p->passportHolder?->passport_no,
                'flight' => $p->contractFlight?->flight_number, 'agent' => $p->agent_id ? User::whereKey($p->agent_id)->value('name') : null,
            ],
            'entries' => $entries,
            'timeline' => $this->timeline($p, $entries),
            'others' => array_values(collect($fp['rows'])->whereNotIn('event', collect($entries)->pluck('key'))->all()),
            'totals' => Footprint::totals($fp),
            'signature' => $this->signature($p),
            'options' => [
                'agents' => User::role('agent')->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->all(),
                // a ticket's boxes — the Ticketing Trail's own lists (vendor:N / portal:N sellers)
                'routes' => Ticket::where('status', 1)->orWhereIn('id', TicketPurchase::whereIn('id', $this->ticketIds($p))->pluck('ticket_id'))
                    ->orderBy('title')->get(['id', 'title'])->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->title])->all(),
                'sellers' => User::role('vendor')->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => 'vendor:'.$u->id, 'name' => $u->name])
                    ->concat(Portal::orderBy('name')->get(['id', 'name'])->map(fn ($x) => ['id' => 'portal:'.$x->id, 'name' => $x->name.' (portal)']))->values()->all(),
                'banks' => Bank::where('status', 1)->orderBy('name')->get(['id', 'name'])->map(fn ($b) => ['id' => (int) $b->id, 'name' => $b->name])->all(),
                'airlines' => Airline::orderBy('name')->get(['id', 'name'])->map(fn ($a) => ['id' => (int) $a->id, 'name' => $a->name])->all(),
            ],
        ];
    }

    /**
     * The trail as a timeline of SESSIONS (owner, 2026-11-01: "timeline wise, and session wise … in
     * which session, via which functionalities"). Everything that happened to him — each entry when
     * it was made, the passenger's own event log (what each screen recorded), every correction (this
     * Trail, Correct, the Ticketing Trail, a ticket edit) and Ticket Orders changes — in time order,
     * cut into sessions: one person's work with no gap over 5 minutes. Newest session first; inside
     * one, in the order it happened. Read-only; built from what is already recorded.
     */
    private function timeline(Pax $p, array $entries): array
    {
        $items = [];
        $names = [];
        $who = function ($id) use (&$names) {
            if (! $id) {
                return null;
            }

            return $names[$id] ??= User::whereKey($id)->value('name');
        };
        foreach ($entries as $e) {
            if (! empty($e['ts'])) {
                $items[] = ['type' => 'entry', 'ts' => $e['ts'], 'by_id' => $e['by_id'] ?? null, 'by' => $e['by'] ?? null, 'via' => $e['via'] ?? null, 'key' => $e['key']];
            }
        }
        // what each screen wrote on his record — an event at an entry's own moment is that entry's "what"
        $changeTs = collect($entries)->filter(fn ($e) => str_starts_with($e['key'], 'change:') && ! empty($e['ts']))
            ->mapWithKeys(fn ($e) => [$e['key'] => strtotime($e['ts'])]);
        $what = [];
        try {
            foreach (DB::table('contract_flight_passenger_events')->where('contract_flight_passenger_id', $p->id)->orderBy('occurred_at')->orderBy('id')->get() as $ev) {
                $t = strtotime((string) $ev->occurred_at);
                $key = in_array($ev->type, ['exception', 'note'], true)
                    ? $changeTs->filter(fn ($ct, $k) => abs($ct - $t) <= 5 && ! isset($what[$k]))->keys()->first() : null;
                if ($key) {
                    $what[$key] = $ev->label;

                    continue;
                }
                $via = match (true) {
                    $ev->type === 'stage' => 'Stage',
                    $ev->code === 'trail_corrected' => 'All Trails and Edit',
                    $ev->code === 'entry_corrected' => 'Flight changes → Correct',
                    $ev->code === 'undone' || $ev->code === 'cancel_undone' => 'Undo',
                    $ev->code === 'fare_difference' => 'New ticket linked',
                    default => ucfirst((string) $ev->type),
                };
                $items[] = ['type' => 'log', 'ts' => date('Y-m-d H:i:s', $t), 'by_id' => $ev->created_by ? (int) $ev->created_by : null, 'by' => $who($ev->created_by),
                    'via' => $via, 'text' => trim($ev->label.($ev->note ? ' — '.$ev->note : '')), 'stage' => $ev->type === 'stage', 'key' => null];
            }
        } catch (\Throwable) {
            // no event log on this server — the entries alone make the timeline
        }
        // his tickets' own history in Ticketing: Trail corrections, field edits, Ticket Orders changes
        $tickets = $this->ticketIds($p);
        try {
            if ($tickets && Schema::hasTable('ticket_trail_logs')) {
                foreach (DB::table('ticket_trail_logs')->whereIn('ticket_purchase_id', $tickets)->orderBy('id')->get() as $l) {
                    $items[] = ['type' => 'log', 'ts' => (string) $l->created_at, 'by_id' => $l->created_by ? (int) $l->created_by : null, 'by' => $who($l->created_by),
                        'via' => $l->action === 'correction' ? 'Ticket Orders → All Trails and Edit' : 'Ticket edited',
                        'text' => trim((string) $l->summary.($l->reason ? ' — '.$l->reason : '')), 'key' => 'ticket:'.$l->ticket_purchase_id];
                }
            }
            if ($tickets && Schema::hasTable('ticket_changes')) {
                foreach (DB::table('ticket_changes')->whereIn('ticket_purchase_id', $tickets)->where('source', 'ticketing')->orderBy('id')->get() as $c) {
                    $items[] = ['type' => 'log', 'ts' => (string) $c->created_at, 'by_id' => $c->created_by ? (int) $c->created_by : null, 'by' => $who($c->created_by),
                        'via' => 'Ticket Orders → Change', 'text' => ucfirst((string) $c->kind).($c->note ? ' — '.$c->note : ''), 'key' => 'ticket:'.$c->ticket_purchase_id];
                }
            }
        } catch (\Throwable) {
            // Ticketing's history unavailable — the rest still shows
        }
        foreach ($items as &$it) {
            if ($it['type'] === 'entry' && isset($what[$it['key']])) {
                $it['what'] = $what[$it['key']];
            }
        }
        unset($it);
        usort($items, fn ($a, $b) => strcmp($a['ts'], $b['ts']) ?: ($a['type'] === 'entry' ? -1 : 1));

        // sessions: one person, no gap over 5 minutes
        $sessions = [];
        foreach ($items as $it) {
            $t = strtotime($it['ts']);
            $last = $sessions ? count($sessions) - 1 : null;
            if ($last === null || $t - $sessions[$last]['end_t'] > 300
                || ($it['by_id'] && $sessions[$last]['by_id'] && $it['by_id'] !== $sessions[$last]['by_id'])) {
                $sessions[] = ['start_t' => $t, 'end_t' => $t, 'by_id' => $it['by_id'], 'by' => $it['by'], 'vias' => [], 'items' => []];
                $last = count($sessions) - 1;
            }
            $sessions[$last]['end_t'] = max($sessions[$last]['end_t'], $t);
            $sessions[$last]['by_id'] ??= $it['by_id'];
            $sessions[$last]['by'] ??= $it['by'];
            if ($it['via'] && $it['via'] !== 'Stage') {
                $sessions[$last]['vias'][$it['via']] = true;
            }
            $sessions[$last]['items'][] = $it + ['time' => date('h:i A', $t)];
        }

        return array_reverse(array_map(fn ($s) => [
            'day' => date('d M Y', $s['start_t']),
            'from' => date('h:i A', $s['start_t']),
            'to' => date('h:i A', $s['end_t']),
            'by' => $s['by'],
            'vias' => array_keys($s['vias']),
            'items' => $s['items'],
        ], $sessions));
    }

    /** Which screen made this change entry — from its kind, reason and what Ticketing recorded beside it. */
    private function viaOf(Change $c): string
    {
        $fromOrders = fn () => Schema::hasTable('ticket_changes')
            && DB::table('ticket_changes')->where('contract_flight_passenger_change_id', $c->id)->exists();

        return match (true) {
            $c->reason === 'lane_new_ticket' => 'His card → ticket lane → New ticket',
            $c->kind === 'ticket_reissue' => $fromOrders() ? 'Ticket Orders → Change → Re-issue' : 'His card → ticket lane → Re-Issue',
            $c->kind === 'ticket_refund' => $fromOrders() ? 'Ticket Orders → Change → Refund' : 'His card → ticket lane → Refund',
            $c->kind === 'return_cancel' => 'Cancel return',
            $c->kind === 'restored' => 'Undo — back on his flight',
            $c->kind === 'flagged' => isset(Change::OFFLOAD_EVENTS[$c->reason]) ? 'Re-assign → Offload / Deported' : 'Edit passenger → flag',
            $c->kind === 'cancelled' => $c->reason === 'new_ticket' ? 'Re-assign → Refund / New ticket' : 'Cancel from flight',
            in_array($c->kind, Change::MOVE_KINDS, true) => 'Re-assign → '.($c->kind === 'reissue' ? 'Reissue' : 'Reschedule'),
            default => 'Flight change',
        };
    }

    /** His change entries that stand on their own (a "we bear" half belongs to its entry). */
    private function primaries(Pax $p): Collection
    {
        $all = Change::with(['creator', 'fromFlight', 'toFlight'])->where('contract_flight_passenger_id', $p->id)->orderBy('id')->get();

        return $all->filter(fn ($c) => $this->corr()->primary($c)->id === $c->id && ! $this->isSplitHalf($c))->values();
    }

    /** A Re-assign "our-side legs" companion (splitLegPenalty) — no postings of its own. */
    private function isSplitHalf(Change $c): bool
    {
        return (bool) preg_match('/\(same move as change #\d+\)/', (string) $c->note) && ! str_starts_with((string) $c->note, 'Charge per ticket');
    }

    private function isOffload(Change $c): bool
    {
        return $c->kind === 'flagged' && isset(Change::OFFLOAD_EVENTS[$c->reason]);
    }

    /**
     * Everything the flight posted for him, in the Ticketing Trail's footprint shape (so its Diff
     * reads it), each row tagged with the entry that posted it: package, change:N, service:N,
     * ticket:N (a refund with no change behind it).
     */
    public function footprint(Pax $p): array
    {
        $rows = [];
        $journals = [];
        $claimedTx = [];
        $claimedPb = [];
        $claimedJe = [];
        $users = fn ($ids) => DB::table('users')->whereIn('id', array_filter(array_unique($ids)))->pluck('name', 'id');

        // the entries, each with exactly its own rows
        $owned = [];
        foreach ($this->primaries($p) as $c) {
            $own = $this->entryRows($c, $this->corr()->halves($c));
            $owned['change:'.$c->id] = $own;
        }
        // refunds of his tickets: the latest change that cancelled each, else the ticket
        $ticketIds = $this->ticketIds($p);
        foreach ($ticketIds as $tid) {
            $own = $this->refundRows($tid);
            if ($own['tx']->isEmpty() && $own['pb']->isEmpty() && $own['je']->isEmpty()) {
                continue;
            }
            $by = $this->refundOwner($p, (int) $tid);
            $key = $by ? 'change:'.$by : 'ticket:'.$tid;
            $owned[$key] = isset($owned[$key]) ? [
                'tx' => $owned[$key]['tx']->merge($own['tx']), 'pb' => $owned[$key]['pb']->merge($own['pb']), 'je' => $owned[$key]['je']->merge($own['je']),
            ] : $own;
        }
        // each ticket's own side: its purchase, payments to the seller, baggage (its refund sits on the change above)
        foreach ($ticketIds as $tid) {
            $own = [
                'tx' => Transaction::whereIn('type', ['ticket_purchase', TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $tid)->get(),
                'pb' => Schema::hasTable('portal_balances') ? PortalBalance::whereIn('type', ['ticket_purchase', TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $tid)->get() : collect(),
                'je' => JournalEntry::whereIn('source', ['ticket_purchase', 'ticket_commission', TicketBaggage::COST, TicketBaggage::CHARGE])->where('source_id', $tid)->get(),
            ];
            // the invoice it was sold on, and the "package includes this ticket" credit a passenger
            // save posts (FlightPackageTicketBilling) — otherwise a preview hides that credit
            $saleIds = DB::table('ticket_sale_items')->where('ticket_purchase_id', $tid)->pluck('ticket_sale_id')->unique()->values();
            if ($saleIds->isNotEmpty()) {
                $saleTx = Transaction::whereIn('type', ['ticket_sale', FlightPackageTicketBilling::TYPE, 'ticket_sale_reversal'])->whereIn('invoice_id', $saleIds)->get();
                $own['tx'] = $own['tx']->merge($saleTx);
                $own['je'] = $own['je']->merge(JournalEntry::where(fn ($q) => $q->whereIn('source', ['ticket_sale', FlightPackageTicketBilling::TYPE])->whereIn('source_id', $saleIds))
                    ->orWhere(fn ($q) => $q->where('source', FlightPackageTicketBilling::TYPE)->whereIn('source_id', $saleTx->pluck('id')))->get());
            }
            // a re-issue fee row on this ticket that no entry claims (its PNR / seller changed since) still shows
            $own['tx'] = $own['tx']->merge(Transaction::whereIn('type', self::SELLER_ROWS)->where('invoice_id', $tid)->get());
            if (Schema::hasTable('portal_balances')) {
                $own['pb'] = $own['pb']->merge(PortalBalance::whereIn('type', self::SELLER_ROWS)->where('invoice_id', $tid)->get());
            }
            $key = 'ticket:'.$tid;
            $owned[$key] = isset($owned[$key]) ? [
                'tx' => $owned[$key]['tx']->merge($own['tx']), 'pb' => $owned[$key]['pb']->merge($own['pb']), 'je' => $owned[$key]['je']->merge($own['je']),
            ] : $own;
        }
        // the package and its reversals / re-charges
        $pkgTx = Transaction::whereIn('type', [Pax::AGENT_LEDGER_TYPE, Pax::AGENT_REVERSAL_TYPE, Pax::REISSUE_LEDGER_TYPE])->where('invoice_id', $p->id)->get();
        $pkgJe = JournalEntry::where(fn ($q) => $q->whereIn('source', [Pax::AGENT_LEDGER_TYPE, Pax::REISSUE_LEDGER_TYPE])->where('source_id', $p->id))
            ->orWhere(fn ($q) => $q->where('source', Pax::AGENT_REVERSAL_TYPE)->whereIn('source_id', $pkgTx->where('type', Pax::AGENT_REVERSAL_TYPE)->pluck('id')))
            ->get();
        $owned['package'] = ['tx' => $pkgTx, 'pb' => collect(), 'je' => $pkgJe];
        // services (trainer / consultant), and anything else keyed on his changes (price revisions, deport costs …)
        if (Schema::hasTable('contract_flight_passenger_service_costs')) {
            foreach (DB::table('contract_flight_passenger_service_costs')->where('contract_flight_passenger_id', $p->id)->get(['id']) as $s) {
                $owned['service:'.$s->id] = [
                    'tx' => Transaction::whereIn('type', ['contract_flight_service_cost', 'contract_flight_service_refund'])->where('invoice_id', $s->id)->get(),
                    'pb' => collect(),
                    'je' => JournalEntry::whereIn('source', [ServiceCost::JOURNAL_SOURCE, 'contract_flight_service_refund'])->where('source_id', $s->id)->get(),
                ];
            }
        }
        $changeIds = Change::where('contract_flight_passenger_id', $p->id)->pluck('id');
        $extraTx = Transaction::whereIn('type', [Change::PRICE_REVISION_TYPE, 'contract_flight_deport_cost', Change::PENALTY_LEDGER_TYPE])->whereIn('invoice_id', $changeIds)->get();
        foreach ($extraTx as $r) {
            $c = Change::find($r->invoice_id);
            $key = $c ? 'change:'.$this->corr()->primary($c)->id : 'other';
            $owned[$key] ??= ['tx' => collect(), 'pb' => collect(), 'je' => collect()];
            $owned[$key]['tx'] = $owned[$key]['tx']->push($r);
            $owned[$key]['je'] = $owned[$key]['je']->merge(JournalEntry::where('source', $r->type)->where('source_id', $r->id)->get());
        }
        // what a change posted beyond its ticket penalty (service penalties, deport costs) on the Company Ledger
        foreach (JournalEntry::where('source', Change::PENALTY_COMPANY_SOURCE)->whereIn('source_id', $changeIds)->get() as $j) {
            $c = Change::find($j->source_id);
            $key = $c ? 'change:'.$this->corr()->primary($c)->id : 'other';
            $owned[$key] ??= ['tx' => collect(), 'pb' => collect(), 'je' => collect()];
            $owned[$key]['je'] = $owned[$key]['je']->push($j);
        }

        $allTx = collect($owned)->flatMap(fn ($o) => $o['tx']);
        $allJe = collect($owned)->flatMap(fn ($o) => $o['je']);
        $names = $users(array_merge($allTx->pluck('user_id')->all(), $allJe->pluck('beneficiary_party_id')->all()));
        $portals = DB::table('portals')->pluck('name', 'id');
        $lines = $allJe->isEmpty() ? collect() : DB::table('journal_items as ji')->leftJoin('accounts as a', 'a.id', '=', 'ji.account_id')
            ->whereIn('ji.journal_entry_id', $allJe->pluck('id')->unique())->whereNull('ji.deleted_at')
            ->orderBy('ji.id')->get(['ji.journal_entry_id', 'ji.account_id', 'ji.debit', 'ji.credit', 'ji.party_id', 'a.code', 'a.name'])->groupBy('journal_entry_id');

        foreach ($owned as $event => $o) {
            foreach ($o['tx'] as $r) {
                if (isset($claimedTx[$r->id])) {
                    continue;
                }
                $claimedTx[$r->id] = true;
                $rows[] = [
                    'src' => 'tx', 'id' => (int) $r->id, 'event' => $event, 'type' => $r->type, 'change_id' => null,
                    'party_id' => $r->user_id ? (int) $r->user_id : null,
                    'ledger' => 'party:'.$r->user_id, 'ledger_name' => $names[$r->user_id] ?? ('#'.$r->user_id),
                    'ledger_kind' => $r->user_type === 'supplier' ? 'seller' : 'agent',
                    'date' => substr((string) $r->payment_date, 0, 10), 'time' => $r->transaction_time ? substr((string) $r->transaction_time, 0, 5) : substr((string) $r->created_at, 11, 5),
                    'ref' => $r->reference_no, 'remarks' => $r->remarks, 'bank' => null, 'bank_id' => null, 'method' => $r->payment_method,
                    'debit' => round((float) $r->debit, 2), 'credit' => round((float) $r->credit, 2), 'shared' => false,
                ];
            }
            foreach ($o['pb'] as $r) {
                if (isset($claimedPb[$r->id])) {
                    continue;
                }
                $claimedPb[$r->id] = true;
                $rows[] = [
                    'src' => 'pb', 'id' => (int) $r->id, 'event' => $event, 'type' => $r->type, 'change_id' => null,
                    'party_id' => null, 'portal_id' => (int) $r->portal_id,
                    'ledger' => 'portal:'.$r->portal_id, 'ledger_name' => ($portals[$r->portal_id] ?? '#'.$r->portal_id).' (wallet)', 'ledger_kind' => 'portal',
                    'date' => substr((string) ($r->date ?: $r->created_at), 0, 10), 'time' => substr((string) $r->created_at, 11, 5),
                    'ref' => $r->reference, 'remarks' => $r->remarks, 'bank' => null, 'bank_id' => null, 'method' => $r->payment_method,
                    'debit' => round((float) $r->debit, 2), 'credit' => round((float) $r->credit, 2), 'shared' => false,
                ];
            }
            foreach ($o['je'] as $j) {
                if (isset($claimedJe[$j->id]) || $j->deleted_at) {
                    continue;
                }
                $claimedJe[$j->id] = true;
                $journals[] = [
                    'id' => (int) $j->id, 'event' => $event, 'source' => $j->source, 'change_id' => null,
                    'date' => substr((string) $j->date, 0, 10), 'time' => $j->entry_time ? substr((string) $j->entry_time, 0, 5) : substr((string) $j->created_at, 11, 5),
                    'ref' => $j->reference, 'description' => $j->description, 'shared' => false,
                    'lines' => $lines->get($j->id, collect())->map(fn ($l) => [
                        'account' => $l->code ? $l->code.' '.$l->name : '#'.$l->account_id, 'code' => $l->code,
                        'debit' => round((float) $l->debit, 2), 'credit' => round((float) $l->credit, 2),
                        'party' => $l->party_id ? ($names[$l->party_id] ?? '#'.$l->party_id) : null,
                    ])->values()->all(),
                ];
            }
        }
        $events = [['key' => 'package', 'label' => 'Package']];
        foreach ($this->primaries($p) as $c) {
            $events[] = ['key' => 'change:'.$c->id, 'label' => (Change::KINDS[$c->kind] ?? $c->kind).' #'.$c->id];
        }
        foreach (array_keys($owned) as $k) {
            if (str_starts_with($k, 'service:')) {
                $events[] = ['key' => $k, 'label' => 'Trainer / consultant'];
            } elseif (str_starts_with($k, 'ticket:')) {
                $events[] = ['key' => $k, 'label' => 'Ticket '.(TicketPurchase::whereKey((int) substr($k, 7))->value('ticket_no') ?: '')];
            }
        }

        return ['rows' => $rows, 'journals' => $journals, 'events' => $events];
    }

    /** @var array<string, int|null> */
    private array $refundOwners = [];

    /**
     * The entry that refunded a ticket (2026-11-01: a refund showed on every entry naming the
     * ticket). Its airline-refund rows were posted in the same request as that entry, so the entry
     * created nearest the ticket's first refund row (within two minutes) owns it; else the latest
     * entry of a refunding kind naming it. Null when no entry did (refunded from Ticketing).
     */
    private function refundOwner(Pax $p, int $tid): ?int
    {
        $k = $p->id.':'.$tid;
        if (array_key_exists($k, $this->refundOwners)) {
            return $this->refundOwners[$k];
        }
        $named = Change::where('contract_flight_passenger_id', $p->id)->whereJsonContains('previous_ticket_purchase_ids', $tid)
            ->orderBy('id')->get()->filter(fn ($c) => $this->corr()->primary($c)->id === $c->id)->values();
        $first = DB::table('transactions')->where('type', 'ticket_purchase_refund')->where('invoice_id', $tid)->min('created_at')
            ?? (Schema::hasTable('portal_balances') ? DB::table('portal_balances')->where('type', 'ticket_purchase_refund')->where('invoice_id', $tid)->min('created_at') : null)
            ?? DB::table('journal_entries')->where('source', 'ticket_purchase_cancel')->where('source_id', $tid)->min('created_at');
        $owner = null;
        if ($first) {
            $at = strtotime((string) $first);
            $near = $named->map(fn ($c) => [$c, abs(strtotime((string) $c->created_at) - $at)])->filter(fn ($x) => $x[1] <= 120)->sortBy(fn ($x) => $x[1])->first();
            $owner = $near ? (int) $near[0]->id : null;
        }
        $owner ??= $named->whereIn('kind', ['ticket_refund', 'cancelled', 'flagged', 'return_cancel', 'reissue'])->last()?->id;

        return $this->refundOwners[$k] = $owner ? (int) $owner : null;
    }

    /** Every ticket ever his: linked now, or named by one of his change rows. */
    private function ticketIds(Pax $p): array
    {
        $ids = DB::table('contract_flight_passenger_tickets')->where('contract_flight_passenger_id', $p->id)->pluck('ticket_purchase_id')->map(fn ($i) => (int) $i)->all();
        foreach (Change::where('contract_flight_passenger_id', $p->id)->pluck('previous_ticket_purchase_ids') as $prev) {
            foreach ((array) ($prev ?? []) as $i) {
                $ids[] = (int) $i;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Exactly the rows ONE entry (and its halves) posted: its agent line and its journals, its seller
     * rows (paired with their journal by party, ticket, amount and text — a seller row is keyed on the
     * ticket, not the change), its wallet rows, the Company Ledger reclasses of its ticket penalty and
     * fare difference (those whose other side is ticket cost 5110 — an offload's deport cost is not
     * one), and its fare rows. Correct's added rows and Undo's reversing rows are among them.
     *
     * @return array{tx: Collection, pb: Collection, je: Collection, bare: array<string, array<int, float>>}
     */
    private function entryRows(Change $p, Collection $halves): array
    {
        $ids = array_merge([(int) $p->id], $halves->pluck('id')->map(fn ($i) => (int) $i)->all());
        $cost = $this->acc('ticket_purchase_cost');
        $je = JournalEntry::with('items')->whereIn('source', self::CHANGE_SOURCES)->whereIn('source_id', $ids)->get();
        $je = $je->merge(JournalEntry::with('items')->whereIn('source', [Change::PENALTY_COMPANY_SOURCE, Change::FARE_COMPANY_SOURCE])->whereIn('source_id', $ids)->get()
            ->filter(fn ($e) => $cost && $e->items->contains(fn ($i) => (int) $i->account_id === $cost))->values());
        $tx = Transaction::whereIn('type', self::CHANGE_ROWS)->whereIn('invoice_id', $ids)->get();
        $je = $je->merge(JournalEntry::with('items')->where('source', Change::FARE_LEDGER_TYPE)->whereIn('source_id', $tx->where('type', Change::FARE_LEDGER_TYPE)->pluck('id'))->get());

        // seller rows: one per seller journal, matched on what both carry
        $tickets = TicketPurchase::whereIn('id', array_map('intval', (array) ($p->previous_ticket_purchase_ids ?? [])))->get(['id', 'ticket_no', 'vendor_id', 'portal_id']);
        $byPnr = $tickets->mapWithKeys(fn ($t) => [(string) $t->ticket_no => (int) $t->id])->all();
        $pb = PortalBalance::whereIn('journal_entry_id', $je->pluck('id'))->get();
        $taken = ['tx' => [], 'pb' => $pb->pluck('id')->flip()->all()];
        // a ticket's PNR or seller may have changed since the fee was posted (2026-11-01 cross-check:
        // the row was then missed and would have been posted twice) — so every ticket of the entry
        $allTickets = $tickets->pluck('id')->map(fn ($i) => (int) $i)->all() ?: [0];
        $fareHead = $this->acc('cf_fare_diff_company');
        // seller journals with no party row and no wallet row (older Re-assign postings: the journal
        // alone, crediting a payable or the portal's account) — replaced in that same shape
        $bare = [];
        foreach ($je->where('source', Change::REISSUE_FEE_TYPE) as $e) {
            if ($pb->contains('journal_entry_id', $e->id)) {
                continue;
            }
            $tid = $tickets->isEmpty() ? 0 : $this->corr()->sellerTicketOf($e, $tickets, $byPnr, (int) $tickets->first()->id);
            $amount = round((float) $e->items->sum('debit'), 2);
            $desc = (string) $e->description;
            $fits = fn ($remarks) => $remarks !== null && $remarks !== '' && ($remarks === $desc || str_starts_with($desc, $remarks));
            $row = null;
            if ($e->beneficiary_party_id) {
                $row = Transaction::whereIn('type', self::SELLER_ROWS)->whereIn('invoice_id', $allTickets)->where('user_id', $e->beneficiary_party_id)
                    ->whereNotIn('id', array_keys($taken['tx']))->orderBy('id')->get()
                    ->first(fn ($r) => abs(abs((float) $r->debit - (float) $r->credit) - $amount) < 0.005 && $fits((string) $r->remarks));
                if ($row) {
                    $taken['tx'][$row->id] = true;
                    $tx->push($row);
                }
            } else {
                $row = PortalBalance::whereIn('type', self::SELLER_ROWS)->whereIn('invoice_id', $allTickets)->whereNotIn('id', array_keys($taken['pb']))->orderBy('id')->get()
                    ->first(fn ($r) => abs(abs((float) $r->debit - (float) $r->credit) - $amount) < 0.005 && $fits((string) $r->remarks));
                if ($row) {
                    $taken['pb'][$row->id] = true;
                    $pb->push($row);
                }
            }
            if (! $row && ! $e->beneficiary_party_id) {
                $head = $e->items->contains(fn ($i) => (int) $i->account_id === (int) $fareHead && (float) $i->debit + (float) $i->credit > 0) ? 'fare' : 'fee';
                $headIds = [(int) $fareHead, (int) $this->acc('cf_change_company')];
                foreach ($e->items->reject(fn ($i) => in_array((int) $i->account_id, $headIds, true)) as $i) {
                    $k = $tid.':'.$head;
                    $bare[$k][(int) $i->account_id] = round(($bare[$k][(int) $i->account_id] ?? 0) + (float) $i->credit - (float) $i->debit, 2);
                }
            }
        }

        return ['tx' => $tx->unique('id')->values(), 'pb' => $pb->unique('id')->values(), 'je' => $je->unique('id')->values(), 'bare' => $bare];
    }

    /** A ticket's airline-refund rows (Ticketing's cancel, and any refund corrections). */
    private function refundRows(int $ticketId): array
    {
        return [
            'tx' => Transaction::where('type', 'ticket_purchase_refund')->where('invoice_id', $ticketId)->get(),
            'pb' => Schema::hasTable('portal_balances') ? PortalBalance::where('type', 'ticket_purchase_refund')->where('invoice_id', $ticketId)->get() : collect(),
            'je' => JournalEntry::where('source', 'ticket_purchase_cancel')->where('source_id', $ticketId)->get(),
        ];
    }

    /** A posting moment: the previewed state must still be the state when Confirm runs. */
    public function signature(Pax $p): string
    {
        $fp = $this->footprint($p);

        return md5(json_encode([
            array_map(fn ($r) => [$r['src'], $r['id'], $r['debit'], $r['credit'], $r['date']], $fp['rows']),
            array_map(fn ($j) => [$j['id'], $j['date']], $fp['journals']),
            Change::where('contract_flight_passenger_id', $p->id)->orderBy('id')->get()->map->only(['id', 'charge', 'bearer', 'agent_id', 'fault', 'airline_penalty', 'fare_difference', 'fare_bearer', 'agent_fare_amount', 'reason', 'note', 'created_at'])->toArray(),
            [$p->agent_id, (float) $p->agent_price, $p->contract_flight_id],
        ]));
    }

    // ── what cannot be done ─────────────────────────────────────────────

    private function deleteProblem(Pax $p, Change $c): ?string
    {
        if ($c->reason === 'undone') {
            return null;   // its effect is already reversed — only its rows go
        }
        if ($c->kind === 'cancelled') {
            if ($p->contract_flight_id) {
                return 'He is back on a flight since — delete the later entries first.';
            }
            $last = Change::where('contract_flight_passenger_id', $p->id)->where('kind', 'cancelled')->whereNotNull('reason')->orderByDesc('id')->value('id');

            return (int) $last === (int) $c->id ? null : 'Only his latest cancellation can be deleted.';
        }
        if (in_array($c->kind, self::MOVES, true)) {
            return $this->moveUndoProblem($p, $c);
        }
        $isNew = $c->reason === 'lane_new_ticket';
        if (! in_array($c->kind, self::DELETABLE, true) && ! $isNew) {
            return 'Deleting this kind of entry (it moved him between flights or flagged him) is not built yet — edit its amounts instead.';
        }
        $ids = array_map('intval', (array) ($c->previous_ticket_purchase_ids ?? []));
        $later = Change::where('contract_flight_passenger_id', $p->id)->where('id', '>', $c->id)
            ->where(fn ($q) => $q->whereNull('reason')->orWhere('reason', '!=', 'undone'))->get()
            ->filter(fn ($x) => $this->corr()->primary($x)->id !== (int) $c->id)
            ->first(fn ($x) => array_intersect($ids, array_map('intval', (array) ($x->previous_ticket_purchase_ids ?? []))));
        if ($later) {
            return 'A later entry (#'.$later->id.', '.(Change::KINDS[$later->kind] ?? $later->kind).') is on the same ticket — delete that one first.';
        }
        if ($c->kind === 'ticket_refund') {
            $live = $p->ticketPurchases()->where('ticket_purchases.status', '!=', 'cancelled')->count();
            if ($live + count($ids) > max(1, $p->ticketsRequired())) {
                return 'A newer ticket already covers this leg — remove it first, then the refunded ticket can come back.';
            }
        }
        if ($isNew && ! $this->newTicketOf($p, $c)) {
            return 'The new ticket is no longer linked to him.';
        }

        return null;
    }

    /** A move is undone only as his latest entry, while he is still on the flight it put him on. */
    private function moveUndoProblem(Pax $p, Change $c): ?string
    {
        $later = Change::where('contract_flight_passenger_id', $p->id)->where('id', '>', $c->id)
            ->where(fn ($q) => $q->whereNull('reason')->orWhere('reason', '!=', 'undone'))->get()
            ->first(fn ($x) => $this->corr()->primary($x)->id !== (int) $c->id);
        if ($later) {
            return 'A later entry (#'.$later->id.', '.(Change::KINDS[$later->kind] ?? $later->kind).') comes after it — undo that one first.';
        }
        if (! $c->to_flight_id || (int) $p->contract_flight_id !== (int) $c->to_flight_id) {
            return 'He is no longer on the flight this entry moved him to.';
        }
        $offFlight = $this->cameFromOffFlight($p, $c);
        if (! $offFlight && ! $c->from_flight_id) {
            return 'There is no flight to put him back on — this entry cannot be undone here.';
        }
        if ($offFlight) {
            $cancel = Change::where('contract_flight_passenger_id', $p->id)->where('id', '<', $c->id)->where('kind', 'cancelled')->orderByDesc('id')->first();
            $new = $p->ticketPurchases()->where('ticket_purchases.status', '!=', 'cancelled')
                ->where('ticket_purchases.created_at', '>=', $cancel?->created_at ?? $c->created_at)->value('ticket_purchases.ticket_no');
            if ($new) {
                return 'His new ticket '.$new.' is still on him — delete that ticket first, then undo this.';
            }
            if ($this->rechargeRows($p, $c)['tx']->count() !== 1) {
                return 'The "Package charged again" line of this re-booking cannot be found exactly — undo it by hand.';
            }
        }
        if ($c->kind === 'reissue' && ! $offFlight) {
            $cancelled = TicketPurchase::whereIn('id', array_map('intval', (array) ($c->previous_ticket_purchase_ids ?? [])))->where('status', 'cancelled')->exists();
            if ($cancelled) {
                return 'This reissue cancelled a live ticket — undoing that is not built yet.';
            }
        }

        return null;
    }

    /** The entry before this one took him off his flight (Cancel from flight) — the move put him back on one. */
    private function cameFromOffFlight(Pax $p, Change $c): bool
    {
        $prev = Change::where('contract_flight_passenger_id', $p->id)->where('id', '<', $c->id)
            ->where(fn ($q) => $q->whereNull('reason')->orWhere('reason', '!=', 'undone'))
            ->whereIn('kind', array_merge(self::MOVES, ['cancelled']))->orderByDesc('id')->first();

        return $prev && $prev->kind === 'cancelled' && ! $prev->to_flight_id;
    }

    /** The "Package charged again" row (and its journal) the re-booking posted at the move's moment. */
    private function rechargeRows(Pax $p, Change $c): array
    {
        $at = Carbon::parse($c->created_at);
        $tx = Transaction::where('type', Pax::AGENT_REVERSAL_TYPE)->where('invoice_id', $p->id)->where('debit', '>', 0)
            ->whereBetween('created_at', [$at->copy()->subSeconds(5), $at->copy()->addSeconds(5)])->orderBy('id')->get();

        return [
            'tx' => $tx,
            'pb' => collect(),
            'je' => $tx->isEmpty() ? collect() : JournalEntry::where('source', Pax::AGENT_REVERSAL_TYPE)->whereIn('source_id', $tx->pluck('id'))->get(),
        ];
    }

    /** Undo a move: its charges go, he goes back where he was, a moved ticket date goes back. */
    private function undoMove(Pax $pax, Change $c): array
    {
        $summary = [];
        $from = $c->from_flight_id ? ContractFlight::find($c->from_flight_id) : null;
        $to = ContractFlight::find($c->to_flight_id);
        if ($this->cameFromOffFlight($pax, $c)) {
            $this->drop($this->rechargeRows($pax, $c));
            $pax->forceFill(['contract_flight_id' => null, 'exception_flag' => Pax::OFF_FLIGHT_FLAG])->save();
            $summary[] = 'He is off flight again (Cancelled), the package charge he got back is removed';
        } else {
            $pax->forceFill(['contract_flight_id' => $c->from_flight_id, 'exception_flag' => $c->previous_flag])->save();
            $summary[] = 'He is back on '.($from?->flight_number ?: 'his previous flight');
        }
        foreach (array_map('intval', (array) ($c->previous_ticket_purchase_ids ?? [])) as $tid) {
            $t = TicketPurchase::find($tid);
            if ($t && $t->ticket_no && preg_match('/'.preg_quote($t->ticket_no, '/').'\s+(\d{4}-\d{2}-\d{2}[^→,·]*?)\s*→\s*(\d{4}-\d{2}-\d{2})/u', (string) $c->note, $m)
                && substr((string) $t->travelAt(), 0, 10) === $m[2]) {
                $t->moveTravelDate(trim($m[1]));
                $summary[] = $t->ticket_no.' back to '.trim($m[1]);
            } elseif ($t && $t->ticket_no && str_contains((string) $c->note, $t->ticket_no.' 20')) {
                $summary[] = $t->ticket_no.': travel date NOT moved back (it was changed since) — check it';
            }
        }
        $to?->syncMoneyFromPassengers();
        $from?->syncMoneyFromPassengers();

        return $summary;
    }

    private function newTicketOf(Pax $p, Change $c): ?TicketPurchase
    {
        if (! preg_match('/^New ticket (\S+(?: \([a-z]{1,2}\))?) after refund/u', (string) $c->note, $m)) {
            return null;
        }

        return $p->ticketPurchases()->where('ticket_purchases.ticket_no', $m[1])->first();
    }

    // ── the run ─────────────────────────────────────────────────────────

    /**
     * $edit: ['entry' => 'package' | change id, 'action' => 'edit' | 'delete', 'input' => the editor's
     * boxes (Correct's shape, or agent_id + agent_price), 'at' => 'Y-m-d H:i' the entry's moment].
     */
    public function run(Pax $p, array $edit, bool $commit, string $reason = '', ?string $signature = null): array
    {
        $this->parties = $this->portals = [];
        $this->replaced = ['transactions' => [], 'journals' => [], 'portal_balances' => [], 'changes' => [], 'ticket_changes' => [], 'tickets' => []];
        request()->merge(['suppress_party_notifications' => true]);
        EditLog::$paused = true;

        DB::beginTransaction();
        try {
            $p = Pax::whereKey($p->id)->lockForUpdate()->firstOrFail();
            if ($signature !== null && $signature !== $this->signature($p)) {
                throw new TrailRefused('Something on this passenger changed since the preview. Close the Trail, open it again and check before posting.');
            }
            $before = $this->footprint($p);
            $marks = $this->marks = [
                'tx' => (int) DB::table('transactions')->max('id'),
                'je' => (int) DB::table('journal_entries')->max('id'),
                'pb' => Schema::hasTable('portal_balances') ? (int) DB::table('portal_balances')->max('id') : 0,
            ];
            $entry = (string) ($edit['entry'] ?? '');
            $action = ($edit['action'] ?? 'edit') === 'delete' ? 'delete' : 'edit';
            $input = (array) ($edit['input'] ?? []);

            if (str_starts_with($entry, 'ticket:') || str_starts_with($entry, 'service:')) {
                $changeIds = [];
                $id = (int) substr($entry, strpos($entry, ':') + 1);
                if (str_starts_with($entry, 'ticket:')) {
                    $t = TicketPurchase::whereIn('id', $this->ticketIds($p))->find($id);
                    if (! $t) {
                        throw new TrailRefused('That ticket is not his.');
                    }
                    $summary = $action === 'delete' ? $this->deleteTicket($p, $t) : $this->editTicket($p, $t, $input, $reason);
                } else {
                    $cost = ServiceCost::where('contract_flight_passenger_id', $p->id)->find($id);
                    if (! $cost) {
                        throw new TrailRefused('That service is not his.');
                    }
                    $summary = $this->editService($p, $cost, $input, $action === 'delete');
                }
            } elseif ($entry === 'package') {
                if ($action === 'delete') {
                    throw new TrailRefused('The package cannot be deleted — set it to 0 instead.');
                }
                $summary = $this->editPackage($p, $input);
                $changeIds = [];
            } else {
                $c = Change::where('contract_flight_passenger_id', $p->id)->find((int) $entry);
                if (! $c || $this->corr()->primary($c)->id !== $c->id) {
                    throw new TrailRefused('That entry is not on this passenger.');
                }
                $changeIds = [(int) $c->id];
                $summary = $action === 'delete'
                    ? $this->deleteEntry($p, $c, $reason)
                    : $this->replaceEntry($p, $c, $input, $edit['at'] ?? null);
            }
            if (! $summary) {
                DB::rollBack();

                return ['changed' => false, 'summary' => [], 'diff' => Diff::of($before, $before)];
            }

            $p->refresh();
            $p->contractFlight?->syncMoneyFromPassengers();
            $this->rewalk($marks);
            $this->assertBalanced($marks['je']);
            $after = $this->footprint($p);
            $result = ['changed' => true, 'summary' => $summary, 'diff' => Diff::of($before, $after)];

            if (! $commit) {
                DB::rollBack();

                return $result;
            }
            if (trim($reason) === '') {
                throw new TrailRefused('Write why it is corrected — it is kept in the trail\'s log.');
            }
            DB::table('contract_flight_undo_logs')->insert([
                'contract_flight_passenger_id' => $p->id,
                'change_ids' => json_encode($changeIds),
                'summary' => mb_substr('Trail — '.implode(' · ', $summary), 0, 2000),
                'reason' => mb_substr($reason, 0, 2000),
                'removed' => json_encode(['trail' => true, 'replaced' => $this->replaced,
                    'added' => [
                        'transactions' => DB::table('transactions')->where('id', '>', $marks['tx'])->pluck('id')->all(),
                        'journals' => DB::table('journal_entries')->where('id', '>', $marks['je'])->pluck('id')->all(),
                    ]]),
                'created_by' => Auth::id(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $p->events()->create([
                'type' => 'finance', 'code' => 'trail_corrected', 'label' => 'Trail corrected',
                'note' => mb_substr(implode(' · ', $summary).' · '.$reason, 0, 1000),
                'occurred_at' => now(), 'created_by' => Auth::id(),
            ]);
            $this->quiet($marks['tx']);
            DB::commit();

            return $result;
        } catch (TrailRefused $e) {
            DB::rollBack();
            throw $e;
        } catch (\InvalidArgumentException|ContractFlightUndoRefused $e) {
            DB::rollBack();
            throw new TrailRefused($e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            throw new TrailRefused('Nothing was posted: '.$e->getMessage());
        } finally {
            EditLog::$paused = false;
        }
    }

    // ── the package ─────────────────────────────────────────────────────

    /** Agent price / agent: the package row is levelled in place, exactly as the passenger edit does. */
    private function editPackage(Pax $p, array $in): array
    {
        $price = round(max(0.0, (float) ($in['agent_price'] ?? $p->agent_price)), 2);
        $agentId = ! empty($in['agent_id']) ? (int) $in['agent_id'] : null;
        $gone = collect([$agentId, $p->agent_id])->filter()->first(fn ($id) => ! User::whereKey($id)->exists());
        if ($gone) {
            throw new TrailRefused('The agent on this package (user #'.$gone.') no longer exists — nothing can be posted to him. Choose another agent on the passenger first.');
        }
        if ($agentId && $agentId !== (int) $p->agent_id && ! User::role('agent')->whereKey($agentId)->exists()) {
            throw new TrailRefused('That agent was not found.');
        }
        if ($price > 0 && ! $agentId) {
            throw new TrailRefused('Choose the agent the package is billed to.');
        }
        $summary = [];
        if (abs($price - (float) $p->agent_price) >= 0.005) {
            $summary[] = 'Package: ৳'.number_format((float) $p->agent_price, 2).' → ৳'.number_format($price, 2);
        }
        if ((int) $agentId !== (int) $p->agent_id) {
            $summary[] = 'Agent: '.(($p->agent_id ? User::whereKey($p->agent_id)->value('name') : null) ?: 'nobody').' → '.($agentId ? User::whereKey($agentId)->value('name') : 'nobody');
        }
        if (! $summary) {
            return [];
        }
        $prev = $p->agent_id ? (int) $p->agent_id : null;
        $types = [Pax::AGENT_LEDGER_TYPE, Pax::AGENT_REVERSAL_TYPE];
        $rows = DB::table('transactions')->whereIn('type', $types)->where('invoice_id', $p->id)->get(['id', 'user_id', 'company_id', 'type', 'debit', 'payment_date', 'transaction_time', 'created_at']);
        $journal = DB::table('journal_entries')->where('source', Pax::AGENT_LEDGER_TYPE)->where('source_id', $p->id)->orderBy('id')->first(['date', 'entry_time', 'created_at']);
        $charged = $rows->where('type', Pax::AGENT_LEDGER_TYPE)->sortBy('id')->first();
        $p->forceFill(['agent_price' => $price, 'agent_id' => $agentId])->save();
        foreach ($rows as $r) {
            $this->touchParty((int) $r->user_id, (int) $r->company_id, (int) $r->id);
        }
        $this->reconcileAgentLedger($p->fresh(), $prev);

        // The package keeps its day: reconcileAgentLedger() re-dates the row it levels to today (the
        // passenger edit's behaviour); a correction is not a new charge.
        foreach ($rows as $r) {
            DB::table('transactions')->where('id', $r->id)->update(['payment_date' => $r->payment_date, 'transaction_time' => $r->transaction_time]);
        }
        if ($charged) {
            DB::table('transactions')->where('id', '>', $this->marks['tx'])->where('type', Pax::AGENT_LEDGER_TYPE)->where('invoice_id', $p->id)
                ->update(['payment_date' => $charged->payment_date, 'transaction_time' => $charged->transaction_time, 'created_at' => $charged->created_at]);
        }
        if ($journal) {
            DB::table('journal_entries')->where('source', Pax::AGENT_LEDGER_TYPE)->where('source_id', $p->id)
                ->update(['date' => $journal->date, 'entry_time' => $journal->entry_time]);
        }

        return $summary;
    }

    // ── one change entry, replaced ──────────────────────────────────────

    private function replaceEntry(Pax $pax, Change $p, array $in, ?string $at): array
    {
        if ($why = ContractFlightChangeCorrection::problem($p)) {
            throw new TrailRefused($why);
        }
        if ($p->kind === 'restored') {
            throw new TrailRefused('Putting him back on the flight posted nothing.');
        }
        if ($this->isOffload($p)) {
            throw new TrailRefused('Offload / deported entries cannot be edited here yet.');
        }
        $halves = $this->corr()->halves($p);
        $posted = $this->corr()->posted($p, $halves);
        $plan = $this->corr()->plan($p, $in);
        $moment = $this->moment($at, $p);
        $summary = $plan['changed'];
        $momentEdited = $moment->format('Y-m-d H:i') !== $p->created_at?->format('Y-m-d H:i');
        if ($momentEdited) {
            $summary[] = 'Posted on: '.($p->created_at?->format('d M Y, h:i A') ?: '—').' → '.$moment->format('d M Y, h:i A');
        }
        if (! $plan['ops'] && ! $plan['rows_change'] && count($summary) === count($plan['changed'])) {
            return [];
        }
        if (! $summary) {
            $summary[] = 'Entry posted again as one clean set (its added correction rows folded in)';
        }

        // the figures every ledger will hold: what it holds now + the edit
        $seller = $posted['seller'];
        $fareAgent = $posted['fare_agent'];
        $dPenalty = 0.0;
        $dFare = 0.0;
        $refunds = [];
        foreach ($plan['ops'] as $op) {
            match ($op['op']) {
                'seller' => $seller[$op['ticket_id'].':'.$op['head']][$op['seller']] = round(($seller[$op['ticket_id'].':'.$op['head']][$op['seller']] ?? 0) + $op['delta'], 2),
                'fare_agent' => $fareAgent[$op['agent_id']] = round(($fareAgent[$op['agent_id']] ?? 0) + $op['delta'], 2),
                'penalty' => $dPenalty = round($dPenalty + $op['delta'], 2),
                'fare_company' => $dFare = round($dFare + $op['delta'], 2),
                'refund' => $refunds[] = $this->refundOwner($pax, (int) $op['ticket_id']) === (int) $p->id ? $op
                    : throw new TrailRefused('The refund on '.$op['pnr'].' was posted by another entry — edit it there.'),
                default => null,
            };
        }
        $bearerOf = [];
        foreach ($plan['rows']['entry']['items'] ?? [] as $pnr => $amt) {
            $bearerOf[$pnr] = $plan['rows']['entry']['bearer'];
        }
        foreach ($plan['rows']['half']['items'] ?? [] as $pnr => $amt) {
            $bearerOf[$pnr] = 'company';
        }

        // 1. out: every row the entry posted
        $own = $this->entryRows($p, $halves);
        $penaltyHead = $this->acc('cf_penalty_company');
        $fareHead = $this->acc('cf_fare_diff_company');
        $net = fn (string $source, ?int $head) => round((float) $own['je']->where('source', $source)
            ->flatMap(fn ($e) => $e->items)->filter(fn ($i) => (int) $i->account_id === (int) $head)->sum(fn ($i) => (float) $i->debit - (float) $i->credit), 2);
        $penaltyCo = round($net(Change::PENALTY_COMPANY_SOURCE, $penaltyHead) + $dPenalty, 2);
        $fareCo = round($net(Change::FARE_COMPANY_SOURCE, $fareHead) + $dFare, 2);
        if ($penaltyCo < -0.005 || $fareCo < -0.005) {
            throw new TrailRefused('The Company Ledger would go below zero for this entry — check the refund and the fare difference.');
        }
        $this->drop($own);

        // 2. the entry's fields, then a changed refund through Ticketing
        foreach ($this->corr()->restate($p, $halves, $plan['rows'], null) as $gone) {
            $this->replaced['changes'][] = $gone;
        }
        if ($momentEdited) {
            $p->forceFill(['created_at' => $moment])->saveQuietly();
            foreach ($this->corr()->halves($p) as $h) {
                $h->forceFill(['created_at' => $moment])->saveQuietly();
            }
        }
        foreach ($refunds as $op) {
            $this->refundAgain(TicketPurchase::findOrFail($op['ticket_id']), (float) $op['new']);
        }

        // 3. in: posted once, in the first posting's own shape
        $p = $p->fresh(['passenger.passportHolder', 'passenger.contractFlight.creator', 'fromFlight', 'toFlight']);
        $flight = $p->toFlight ?: $p->fromFlight ?: $pax->contractFlight;
        $name = $pax->passportHolder?->name ?: 'passenger';
        $flightNo = $flight?->flight_number ?: 'contract flight';
        $ref = $flightNo.'-'.$pax->id.'-C'.$p->id;
        foreach ($seller as $key => $bySeller) {
            [$tid, $head] = explode(':', $key);
            $t = TicketPurchase::find((int) $tid);
            foreach ($bySeller as $who => $amount) {
                if ($amount < -0.005) {
                    throw new TrailRefused('A seller would be owed less than nothing on '.($t?->ticket_no ?: '#'.$tid).' — check the amounts.');
                }
                if ($amount >= 0.005 && $t) {
                    $bearer = $bearerOf[(string) $t->ticket_no] ?? $p->bearer;
                    $rest = round((float) $amount, 2);
                    // what was first posted as a bare journal stays bare while its seller is unchanged
                    if (isset($posted['seller'][$key][$who])) {
                        foreach ($own['bare'][$key] ?? [] as $cr => $held) {
                            $part = round(min($rest, max(0.0, (float) $held)), 2);
                            if ($part >= 0.005) {
                                $this->postSellerPart($p, $pax, $flight, $t, $head, (string) $who, $part, $bearer, (int) $cr);
                                $rest = round($rest - $part, 2);
                            }
                        }
                    }
                    if ($rest >= 0.005) {
                        $this->postSellerPart($p, $pax, $flight, $t, $head, (string) $who, $rest, $bearer);
                    }
                }
            }
        }
        $this->reconcileChangeCharge($p);
        if ($penaltyCo >= 0.005) {
            $this->postCompanyJournal(Change::PENALTY_COMPANY_SOURCE, $p->id, config('accounts.cf_penalty_company'), config('accounts.ticket_purchase_cost'),
                $penaltyCo, 'Airline cancellation penalty — '.$name.' ('.$flightNo.')', $ref, TravelCompany::ID, $p->agent_id ? (int) $p->agent_id : null);
        }
        if ($fareCo >= 0.005) {
            $this->postCompanyJournal(Change::FARE_COMPANY_SOURCE, $p->id, config('accounts.cf_fare_diff_company'), config('accounts.ticket_purchase_cost'),
                $fareCo, 'Fare difference on new ticket — '.$name.' ('.$flightNo.')', $ref, TravelCompany::ID, $p->agent_id ? (int) $p->agent_id : null);
        }
        foreach ($fareAgent as $uid => $amount) {
            if (abs($amount) >= 0.005) {
                $this->postAgentAdjustment((int) $uid, Change::FARE_LEDGER_TYPE, $p->id, (float) $amount,
                    ($amount > 0 ? 'Fare difference billed to agent — ' : 'Fare difference saving given to agent — ').$name.' ('.$flightNo.')',
                    $ref, config('accounts.cf_fare_diff_recovered'), TravelCompany::ID);
            }
        }

        // every row posted for the entry carries its moment (a refund keeps Ticketing's own date)
        $this->stamp($moment, $momentEdited);

        // the agent's line must be what the edit said — never a silent difference
        $expect = $this->expectedLine($posted, $plan, (int) $p->id);
        $got = round((float) Transaction::where('type', Change::LEDGER_TYPE)->where('invoice_id', $p->id)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) n')->value('n'), 2);
        if (abs($expect - $got) >= 0.01) {
            throw new TrailRefused('The agent\'s line would read ৳'.number_format($got, 2).' instead of ৳'.number_format($expect, 2).' — nothing was posted.');
        }

        return $summary;
    }

    /** The agent line the edit asks for on the entry itself: what it held + its agent op for this entry and agent. */
    private function expectedLine(array $posted, array $plan, int $entryId): float
    {
        $agentId = (int) ($plan['rows']['entry']['agent_id'] ?? 0);
        $held = (float) ($posted['agent'][$entryId][$agentId]['ledger'] ?? 0);
        foreach ($plan['ops'] as $op) {
            if ($op['op'] === 'agent' && (int) $op['change_id'] === $entryId && (int) $op['agent_id'] === $agentId) {
                $held += (float) $op['line'];
            }
        }

        return round($held, 2);
    }

    /** One part of a re-issue fee owed to its seller — postReissueFee()'s shape, for the seller chosen. */
    private function postSellerPart(Change $change, Pax $p, ?ContractFlight $flight, TicketPurchase $t, string $head, string $who, float $amount, ?string $bearer, ?int $bareCr = null): void
    {
        [$kind, $sid] = explode(':', $who.':');
        if ($bareCr) {
            $this->postBareSeller($change, $p, $flight, $t, $head, $bareCr, $amount, $bearer);

            return;
        }
        if (! in_array($kind, ['v', 'p'], true)) {
            throw new TrailRefused('The '.($head === 'fee' ? 're-issue charge' : 'fare difference').' on '.$t->ticket_no.' has no seller — choose one.');
        }
        $amount = round($amount, 2);
        $label = $head === 'fee' ? 'Re-issue charge' : 'Fare difference';
        $rowType = $head === 'fee' ? Change::REISSUE_FEE_TYPE : Change::REISSUE_FARE_TYPE;
        $dr = $this->acc($head === 'fee' ? 'cf_change_company' : 'cf_fare_diff_company') ?: $this->acc('cf_change_company');
        $ap = $this->acc('accounts_payable');
        $vendorId = $kind === 'v' ? (int) $sid : null;
        $portal = $kind === 'p' ? Portal::find((int) $sid) : null;
        if (! $dr || ($vendorId && ! $ap) || ($kind === 'p' && (! $portal || ! $portal->account_id))) {
            throw new TrailRefused('An account head (or the portal\'s account) is missing on this server — nothing was posted.');
        }
        $name = $p->passportHolder?->name ?: 'passenger';
        $pnr = (string) ($t->ticket_no ?: '#'.$t->id);
        $owedTo = $vendorId ? User::whereKey($vendorId)->value('name') : $portal->name;
        $agentName = $change->agent_id ? User::whereKey($change->agent_id)->value('name') : null;
        $fromNo = $change->fromFlight?->flight_number;
        $toNo = $flight?->flight_number;
        $where = $fromNo && $toNo && $fromNo !== $toNo ? $fromNo.' → '.$toNo : 'on '.($toNo ?: $fromNo ?: 'his flight');
        $agentPays = $bearer === 'agent' && $change->agent_id;
        $why = mb_substr($label.' — '.$pnr.' · '.$name.' · '.$where.' · owed to '.$owedTo
            .' · '.($agentPays ? 'agent pays ('.($agentName ?: 'agent').') — recovered' : 'we bear'), 0, 250);

        if ($vendorId) {
            $this->postPartyLedgerRow($vendorId, ['type' => $rowType, 'invoice_id' => $t->id, 'account_id' => null, 'payment_date' => now(),
                'reference_no' => $pnr, 'debit' => $amount, 'credit' => 0, 'remarks' => $why, 'company_id' => TravelCompany::ID]);
        }
        $entry = JournalEntry::create([
            'company_id' => TravelCompany::ID, 'created_by' => Auth::id(), 'date' => now()->toDateString(), 'entry_time' => now()->format('H:i:s'),
            'reference' => mb_substr($pnr.'-C'.$change->id, 0, 190), 'source' => Change::REISSUE_FEE_TYPE, 'source_id' => $change->id,
            'description' => $why, 'beneficiary' => $vendorId ? $owedTo : null, 'beneficiary_party_id' => $vendorId,
        ]);
        if ($portal) {
            $portal->refresh();
            $opening = (float) $portal->balance;
            PortalBalance::create(['invoice_id' => $t->id, 'portal_id' => $portal->id, 'type' => $rowType, 'journal_entry_id' => $entry->id,
                'old_balance' => $opening, 'debit' => 0, 'credit' => $amount, 'current_balance' => $opening - $amount,
                'remarks' => $why, 'created_by' => Auth::id()]);
            $portal->update(['balance' => $opening - $amount]);
        }
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $dr, 'debit' => $amount, 'credit' => 0, 'note' => $why,
            'party_type' => $agentPays ? 'agent' : null, 'party_id' => $agentPays ? (int) $change->agent_id : null]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $vendorId ? $ap : $portal->account_id, 'debit' => 0, 'credit' => $amount,
            'note' => $why, 'party_type' => $vendorId ? 'vendor' : null, 'party_id' => $vendorId]);
    }

    /** The older shape of a seller's part: the journal alone, crediting the account it first credited. */
    private function postBareSeller(Change $change, Pax $p, ?ContractFlight $flight, TicketPurchase $t, string $head, int $cr, float $amount, ?string $bearer): void
    {
        $dr = $this->acc($head === 'fee' ? 'cf_change_company' : 'cf_fare_diff_company') ?: $this->acc('cf_change_company');
        if (! $dr) {
            throw new TrailRefused('An account head is missing on this server — nothing was posted.');
        }
        $pnr = (string) ($t->ticket_no ?: '#'.$t->id);
        $agentPays = $bearer === 'agent' && $change->agent_id;
        $where = $change->fromFlight?->flight_number && $flight?->flight_number && $change->fromFlight->flight_number !== $flight->flight_number
            ? $change->fromFlight->flight_number.' → '.$flight->flight_number : 'on '.($flight?->flight_number ?: 'his flight');
        $why = mb_substr(($head === 'fee' ? 'Reissue fee' : 'Fare difference').' — '.$pnr.' · '.($p->passportHolder?->name ?: 'passenger').' · '.$where
            .' · '.($agentPays ? 'agent pays — recovered' : 'we bear'), 0, 250);
        $entry = JournalEntry::create([
            'company_id' => TravelCompany::ID, 'created_by' => Auth::id(), 'date' => now()->toDateString(), 'entry_time' => now()->format('H:i:s'),
            'reference' => mb_substr($pnr.'-C'.$change->id, 0, 190), 'source' => Change::REISSUE_FEE_TYPE, 'source_id' => $change->id, 'description' => $why,
        ]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $dr, 'debit' => round($amount, 2), 'credit' => 0, 'note' => $why,
            'party_type' => $agentPays ? 'agent' : null, 'party_id' => $agentPays ? (int) $change->agent_id : null]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $cr, 'debit' => 0, 'credit' => round($amount, 2), 'note' => $why]);
    }

    /** A ticket's airline refund replaced: its refund rows out, the ticket cancelled again with the new one. */
    private function refundAgain(TicketPurchase $t, float $refund): void
    {
        if ($t->status !== 'cancelled') {
            throw new TrailRefused('Ticket '.$t->ticket_no.' is not cancelled — there is no refund to correct.');
        }
        $note = (string) $t->cancel_note;
        $on = $t->cancelled_at ? Carbon::parse($t->cancelled_at) : now();
        $this->drop($this->refundRows((int) $t->id));
        $this->reopen($t);
        app(TicketPurchaseController::class)->cancelFromContractFlight($t->fresh(), $note ?: 'Cancelled', $refund, $on);
    }

    /** The ticket as it was before its cancel: live, no refund, its seat back. */
    private function reopen(TicketPurchase $t): void
    {
        $back = ['status' => 'confirm', 'cancel_note' => null];
        foreach (['cancelled_at', 'refund_amount'] as $col) {
            if (Schema::hasColumn('ticket_purchases', $col)) {
                $back[$col] = null;
            }
        }
        $t->forceFill($back)->save();
        if ($t->ticket_id) {
            DB::table('tickets')->where('id', $t->ticket_id)->increment('qty');
        }
    }

    // ── one change entry, deleted ───────────────────────────────────────

    private function deleteEntry(Pax $pax, Change $c, string $reason): array
    {
        if ($why = $this->deleteProblem($pax, $c)) {
            throw new TrailRefused($why);
        }
        $label = (Change::KINDS[$c->kind] ?? $c->kind).' #'.$c->id;
        if ($c->kind === 'cancelled' && $c->reason !== 'undone') {
            // the existing replace-style Undo: rows removed, tickets live, him back on his flight
            // its own re-walk goes per party across companies; ours follows postPartyLedgerRow's
            // chain (per party AND company), so every party it touches is re-walked again after it
            $ids = collect($this->footprint($pax)['rows'])->where('src', 'tx')->pluck('id');
            $hit = Transaction::whereIn('id', $ids)->get(['id', 'user_id'])->groupBy('user_id')->map(fn ($g) => (int) $g->min('id'));
            $undo = new ContractFlightCancellationUndo;
            $undo->run($pax, $reason !== '' ? $reason : 'deleted from the Trail');
            foreach ($hit as $uid => $from) {
                foreach (Transaction::where('user_id', $uid)->distinct()->pluck('company_id') as $co) {
                    $this->touchParty((int) $uid, (int) $co, $from);
                }
            }

            return ['Deleted: '.$label.' — he is back on his flight, his tickets live again'];
        }
        $halves = $this->corr()->halves($c);
        $summary = ['Deleted: '.$label];
        $ids = array_map('intval', (array) ($c->previous_ticket_purchase_ids ?? []));
        $this->drop($this->entryRows($c, $halves));

        if ($c->reason !== 'undone') {
            if (in_array($c->kind, self::MOVES, true)) {
                array_push($summary, ...$this->undoMove($pax, $c));
            } elseif ($c->kind === 'ticket_refund') {
                foreach ($ids as $tid) {
                    $t = TicketPurchase::find($tid);
                    if (! $t || $t->status !== 'cancelled') {
                        continue;
                    }
                    $this->drop($this->refundRows($tid));
                    $this->reopen($t);
                    $pax->ticketPurchases()->syncWithoutDetaching([$tid]);
                    $summary[] = $t->ticket_no.' live again and back on him';
                }
            } elseif ($c->kind === 'ticket_reissue') {
                $t = TicketPurchase::find($ids[0] ?? 0);
                if ($t && $t->ticket_no && preg_match('/'.preg_quote($t->ticket_no, '/').'\s+(\d{4}-\d{2}-\d{2}[^→,·]*?)\s*→\s*(\d{4}-\d{2}-\d{2})/u', (string) $c->note, $m)
                    && substr((string) $t->travelAt(), 0, 10) === $m[2]) {
                    $t->moveTravelDate(trim($m[1]));
                    $summary[] = $t->ticket_no.' back to '.trim($m[1]);
                }
            } elseif ($c->reason === 'lane_new_ticket' && ($new = $this->newTicketOf($pax, $c))) {
                $pax->ticketPurchases()->detach($new->id);
                $summary[] = $new->ticket_no.' taken off him (it stays in Ticket Orders)';
            }
        }
        if (Schema::hasTable('ticket_changes')) {
            $tc = DB::table('ticket_changes')->whereIn('contract_flight_passenger_change_id', array_merge([$c->id], $halves->pluck('id')->all()))->get();
            foreach ($tc as $r) {
                $this->replaced['ticket_changes'][] = (array) $r;
            }
            DB::table('ticket_changes')->whereIn('id', $tc->pluck('id'))->delete();
        }
        foreach ($halves->push($c) as $row) {
            $this->replaced['changes'][] = $row->getAttributes();
            $row->delete();
        }
        $pax->unsetRelation('ticketPurchases');
        $pax->syncDerivedStage();

        return $summary;
    }

    // ── his tickets (owner, 2026-11-01: "two ticket, airlines can be changed, amount, date, vendor,
    //    can be deleted one ticket, or both") ─────────────────────────────

    /** One entry per ticket ever his — edited through the Ticketing Trail's own Rewriter. */
    private function ticketEntries(Pax $p): array
    {
        $slotOf = [];
        foreach ($p->fresh()->ticketSlots() as $s) {
            if ($s['purchase_id']) {
                $slotOf[(int) $s['purchase_id']] = $s;
            }
        }
        $count = max(1, $p->ticketsRequired(), count($slotOf));
        $rw = new Rewriter;
        $out = [];
        foreach (TicketPurchase::with('legs')->whereIn('id', $this->ticketIds($p))->orderBy('id')->get() as $t) {
            $first = $t->legs->first();
            $last = $t->legs->last();
            $slot = $slotOf[(int) $t->id] ?? null;
            $label = $slot ? ($count > 1 ? 'Ticket '.$slot['index'].' of '.$count : 'Ticket')
                : ($t->status === 'cancelled' ? 'Refunded ticket' : 'Ticket, not on him now');
            try {
                $state = $rw->state($t);
                $blocked = $rw->blocked($t);
            } catch (\Throwable $e) {
                $state = null;
                $blocked = 'This ticket cannot be read: '.$e->getMessage();
            }
            $out[] = [
                'key' => 'ticket:'.$t->id, 'id' => (int) $t->id, 'kind' => 'ticket',
                'title' => $label.($first && $last ? ' · '.$first->from_location.' → '.$last->to_location : '').' · '.($t->ticket_no ?: '#'.$t->id),
                'at' => $t->created_at?->format('Y-m-d H:i'), 'by' => $t->created_by ? User::whereKey($t->created_by)->value('name') : null,
                'ts' => $t->created_at?->format('Y-m-d H:i:s'), 'by_id' => $t->created_by ? (int) $t->created_by : null, 'via' => 'Ticket Entry (Ticketing) — linked to him',
                'note' => $t->status === 'cancelled' ? 'Refunded'.($t->cancel_note ? ' — '.$t->cancel_note : '') : null,
                'form' => $state ? ['ticket' => $state['ticket'], 'payments' => $state['payments'], 'airline_id' => $this->airlineOf($t)] : null,
                'edit_problem' => $blocked,
                'delete_problem' => $this->ticketDeleteProblem($p, $t),
            ];
        }

        return $out;
    }

    /** The ticket's airline: the one that issues it, else its first leg's. */
    private function airlineOf(TicketPurchase $t): ?int
    {
        $id = $t->validating_carrier_id ?: DB::table('ticket_legs')->where('ticket_purchase_id', $t->id)->whereNotNull('airline_id')->orderBy('id')->value('airline_id');

        return $id ? (int) $id : null;
    }

    /**
     * Route, PNR, class, dates, cost, seller and payments to the seller go through the Ticketing
     * Trail's Rewriter — the same replace, the same rows as Ticket Orders' own Trail, so both screens
     * agree; it re-syncs the flight's cost. The airline has no money: set on the ticket and its legs.
     */
    private function editTicket(Pax $p, TicketPurchase $t, array $in, string $reason): array
    {
        $rw = new Rewriter;
        if ($why = $rw->blocked($t)) {
            throw new TrailRefused($why);
        }
        $state = $rw->state($t);
        $given = array_intersect_key((array) ($in['ticket'] ?? []), array_flip(['ticket_id', 'ticket_no', 'ticket_class', 'trip_type', 'purchase_date', 'travel_at', 'seller', 'amount']));
        $edit = [
            'ticket' => array_merge($state['ticket'], $given),
            'payments' => array_key_exists('payments', $in) ? array_values((array) $in['payments']) : $state['payments'],
            'sale' => $state['sale'],
            'events' => $state['events'],
        ];
        $summary = [];
        $r = $rw->run($t, $edit, true, $reason !== '' ? $reason : 'Contract Flight Trail');
        EditLog::$paused = true;
        if (! empty($r['changed'])) {
            $summary = array_map(fn ($s) => $t->ticket_no.': '.$s, $r['summary']);
        }
        if (array_key_exists('airline_id', $in)) {
            $now = ! empty($in['airline_id']) ? (int) $in['airline_id'] : null;
            $was = $this->airlineOf($t->fresh());
            if ($now !== $was) {
                if ($now && ! Airline::whereKey($now)->exists()) {
                    throw new TrailRefused('That airline was not found.');
                }
                DB::table('ticket_purchases')->where('id', $t->id)->update(['validating_carrier_id' => $now]);
                if ($now) {
                    DB::table('ticket_legs')->where('ticket_purchase_id', $t->id)->update(['airline_id' => $now]);
                }
                $name = fn ($id) => $id ? Airline::whereKey($id)->value('name') : '—';
                $summary[] = $t->ticket_no.': airline '.$name($was).' → '.$name($now);
            }
        }
        $p->unsetRelation('ticketPurchases');
        $p->syncDerivedStage();

        return $summary;
    }

    private function ticketDeleteProblem(Pax $p, TicketPurchase $t): ?string
    {
        $named = Change::where('contract_flight_passenger_id', $p->id)->orderBy('id')->get()
            ->filter(fn ($c) => in_array((int) $t->id, array_map('intval', (array) ($c->previous_ticket_purchase_ids ?? [])), true));
        if ($named->isNotEmpty()) {
            return 'Entries on this ticket ('.$named->map(fn ($c) => '#'.$c->id.' '.(Change::KINDS[$c->kind] ?? $c->kind))->implode(', ').') — delete them first, then the ticket.';
        }
        // two of his tickets on one PNR (an undone cancellation brings the old one back beside the new
        // one): only the newer may go — 2026-11-02 the original was deleted in its place
        $base = fn ($no) => preg_replace('/\s*\([a-z]{1,2}\)$/u', '', trim((string) $no));
        $twin = $t->ticket_no ? $p->ticketPurchases()->where('ticket_purchases.id', '>', $t->id)->get(['ticket_purchases.id', 'ticket_purchases.ticket_no'])
            ->first(fn ($x) => $base($x->ticket_no) === $base($t->ticket_no)) : null;
        if ($twin) {
            return 'He holds a newer ticket on the same PNR (#'.$twin->id.') — this older one is kept; delete the newer one if it is the wrong one.';
        }
        if (Schema::hasTable('ticket_changes') && DB::table('ticket_changes')->where('ticket_purchase_id', $t->id)->exists()) {
            return 'It has changes from Ticket Orders — delete those first.';
        }
        if (DB::table('ticket_sale_items')->where('ticket_purchase_id', $t->id)->exists()) {
            return 'It is on a Ticketing invoice — take it off there first.';
        }
        if (Transaction::where('invoice_id', $t->id)->where('type', 'ticket_purchase')->whereNotNull('account_id')->exists()) {
            return 'It has payments to the seller from a bank — remove them first (edit the ticket, remove each payment), then delete it.';
        }
        if (DB::table('contract_flight_passenger_tickets')->where('ticket_purchase_id', $t->id)->where('contract_flight_passenger_id', '!=', $p->id)->exists()) {
            return 'Another passenger holds this ticket too.';
        }

        return null;
    }

    /** A wrong ticket deleted: every row it posted removed (a portal's wallet given back), off him, gone. */
    private function deleteTicket(Pax $p, TicketPurchase $t): array
    {
        if ($why = $this->ticketDeleteProblem($p, $t)) {
            throw new TrailRefused($why);
        }
        $own = [
            'tx' => Transaction::whereIn('type', ['ticket_purchase', 'ticket_purchase_refund', TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $t->id)->get(),
            'pb' => Schema::hasTable('portal_balances') ? PortalBalance::whereIn('type', ['ticket_purchase', 'ticket_purchase_refund', TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $t->id)->get() : collect(),
            'je' => JournalEntry::whereIn('source', ['ticket_purchase', 'ticket_purchase_cancel', 'ticket_commission', TicketBaggage::COST, TicketBaggage::CHARGE])->where('source_id', $t->id)->get(),
        ];
        $this->drop($own);
        $this->replaced['tickets'][] = $t->getAttributes() + ['legs' => DB::table('ticket_legs')->where('ticket_purchase_id', $t->id)->get()->map(fn ($l) => (array) $l)->all()];
        // a confirmed purchase counted one seat into the route's stock (TicketPurgeController does the same)
        if ($t->status === 'confirm' && $t->ticket_id && ($acc = Ticket::find($t->ticket_id)) && $acc->qty > 0) {
            $acc->decrement('qty');
        }
        $t->schedules()->delete();
        DB::table('ticket_legs')->where('ticket_purchase_id', $t->id)->delete();
        if (Schema::hasTable('ticket_bookings') && Schema::hasColumn('ticket_bookings', 'confirmed_purchase_id')) {
            DB::table('ticket_bookings')->where('confirmed_purchase_id', $t->id)->update(['confirmed_purchase_id' => null]);
        }
        $p->ticketPurchases()->detach($t->id);
        $t->delete();
        $p->unsetRelation('ticketPurchases');
        $p->stepBackIfUnticketed();

        return ['Deleted ticket '.($t->ticket_no ?: '#'.$t->id).' — every row it posted removed and it is off him'];
    }

    // ── his services: trainer / consultant / others ─────────────────────

    private function serviceEntries(Pax $p): array
    {
        $out = [];
        if (! Schema::hasTable('contract_flight_passenger_service_costs')) {
            return $out;
        }
        foreach (ServiceCost::where('contract_flight_passenger_id', $p->id)->orderBy('id')->get() as $c) {
            $def = ServiceCost::TYPES[$c->cost_type] ?? null;
            $fixed = ! $def || ! empty($def['legacy']);
            $providers = $fixed ? collect() : ServiceCost::providerQuery($c->cost_type)->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name]);
            if ($c->vendor_id && ! $providers->contains('id', (int) $c->vendor_id)) {
                $providers->push(['id' => (int) $c->vendor_id, 'name' => (string) User::whereKey($c->vendor_id)->value('name')]);
            }
            $out[] = [
                'key' => 'service:'.$c->id, 'id' => (int) $c->id, 'kind' => 'service',
                'title' => (string) ($c->title ?: ($def['label'] ?? $c->cost_type)),
                'at' => $c->created_at?->format('Y-m-d H:i'), 'by' => $c->created_by ? User::whereKey($c->created_by)->value('name') : null,
                'ts' => $c->created_at?->format('Y-m-d H:i:s'), 'by_id' => $c->created_by ? (int) $c->created_by : null, 'via' => 'Passenger card — services',
                'note' => (float) $c->paid_amount > 0 ? 'Paid ৳'.number_format((float) $c->paid_amount, 2) : null,
                'form' => ['amount' => round((float) $c->amount, 2), 'vendor_id' => $c->vendor_id ? (int) $c->vendor_id : null, 'providers' => $providers->values()->all()],
                'edit_problem' => $fixed ? 'This kind of service cannot be edited here.' : null,
                'delete_problem' => $fixed ? 'This kind of service cannot be removed here.' : ((float) $c->paid_amount > 0 ? 'It has been paid — reverse the payment first.' : null),
            ];
        }

        return $out;
    }

    /** Who is paid and how much — the passenger edit's own reconcile, which rewrites the service's rows in place. */
    private function editService(Pax $p, ServiceCost $cost, array $in, bool $delete): array
    {
        $def = ServiceCost::TYPES[$cost->cost_type] ?? null;
        if (! $def || ! empty($def['legacy'])) {
            throw new TrailRefused('This kind of service cannot be changed here.');
        }
        $amount = $delete ? 0.0 : round(max(0.0, (float) ($in['amount'] ?? $cost->amount)), 2);
        $vendor = $delete ? ($cost->vendor_id ? (int) $cost->vendor_id : null) : (! empty($in['vendor_id']) ? (int) $in['vendor_id'] : null);
        if ($vendor && $vendor !== (int) $cost->vendor_id && ! ServiceCost::providerQuery($cost->cost_type)->whereKey($vendor)->exists()) {
            throw new TrailRefused('That person cannot be the responsible person for '.$def['label'].'.');
        }
        // no responsible person = unassigned, as the passenger edit allows: no payable is posted until one is chosen
        if ($amount < (float) $cost->paid_amount - 0.005) {
            throw new TrailRefused('৳'.number_format((float) $cost->paid_amount, 2).' was already paid for it — reverse the payment before going below that.');
        }
        $summary = [];
        $label = (string) ($cost->title ?: $def['label']);
        if ($delete) {
            $summary[] = $label.' removed (৳'.number_format((float) $cost->amount, 2).')';
        } else {
            if (abs($amount - (float) $cost->amount) >= 0.005) {
                $summary[] = $label.': ৳'.number_format((float) $cost->amount, 2).' → ৳'.number_format($amount, 2);
            }
            if ($vendor !== ($cost->vendor_id ? (int) $cost->vendor_id : null)) {
                $summary[] = $label.': paid to '.(User::whereKey($cost->vendor_id)->value('name') ?: 'nobody').' → '.(User::whereKey($vendor)->value('name') ?: 'nobody');
            }
        }
        if (! $summary) {
            return [];
        }
        foreach (Transaction::where('type', ServiceCost::JOURNAL_SOURCE)->where('invoice_id', $cost->id)->get() as $r) {
            $this->touchParty((int) $r->user_id, (int) $r->company_id, (int) $r->id);
        }
        $p->forceFill([$def['field'] => $amount])->save();
        $this->reconcilePassengerServiceCosts($p->fresh(), [$cost->cost_type => [
            'vendor_id' => $vendor, 'paid_amount' => (float) $cost->paid_amount, 'bank_id' => $cost->bank_id,
            'incurred_on' => $cost->incurred_on ? substr((string) $cost->incurred_on, 0, 10) : null,
            'payment_date' => $cost->payment_date ? substr((string) $cost->payment_date, 0, 10) : null,
            'due_date' => $cost->due_date ? substr((string) $cost->due_date, 0, 10) : null,
            'reference_no' => $cost->reference_no,
        ]]);

        return $summary;
    }

    // ── removing and re-walking ─────────────────────────────────────────

    /** @param array{tx: Collection, pb: Collection, je: Collection} $own */
    private function drop(array $own): void
    {
        foreach ($own['tx'] as $r) {
            $this->replaced['transactions'][] = $r->getAttributes();
            $this->touchParty((int) $r->user_id, (int) $r->company_id, (int) $r->id);
        }
        if ($own['tx']->isNotEmpty()) {
            DB::table('transactions')->whereIn('id', $own['tx']->pluck('id'))->delete();
        }
        foreach ($own['pb'] as $r) {
            $this->replaced['portal_balances'][] = $r->getAttributes();
            $this->portals[$r->portal_id] = min($this->portals[$r->portal_id] ?? PHP_INT_MAX, (int) $r->id);
            Portal::whereKey($r->portal_id)->decrement('balance', (float) $r->debit - (float) $r->credit);
        }
        if ($own['pb']->isNotEmpty()) {
            DB::table('portal_balances')->whereIn('id', $own['pb']->pluck('id'))->delete();
        }
        $ids = $own['je']->pluck('id')->unique();
        if ($ids->isNotEmpty()) {
            foreach (DB::table('journal_entries')->whereIn('id', $ids)->get() as $j) {
                $this->replaced['journals'][] = ['entry' => (array) $j, 'items' => DB::table('journal_items')->where('journal_entry_id', $j->id)->get()->map(fn ($i) => (array) $i)->all()];
            }
            DB::table('journal_items')->whereIn('journal_entry_id', $ids)->delete();
            DB::table('journal_entries')->whereIn('id', $ids)->delete();
        }
    }

    private function touchParty(int $party, int $company, int $from): void
    {
        if ($party) {
            $k = $party.':'.$company;
            $this->parties[$k] = min($this->parties[$k] ?? PHP_INT_MAX, $from);
        }
    }

    /** Running balances re-walked per party and company (postPartyLedgerRow's chain) and per wallet. Twin of TicketTrail\Rewriter::rewalk(). */
    private function rewalk(array $marks): void
    {
        foreach (DB::table('transactions')->where('id', '>', $marks['tx'])->whereNotNull('user_id')->groupBy('user_id', 'company_id')->selectRaw('user_id, company_id, MIN(id) m')->get() as $n) {
            $this->touchParty((int) $n->user_id, (int) $n->company_id, (int) $n->m);
        }
        foreach ($this->parties as $k => $from) {
            [$party, $company] = array_map('intval', explode(':', $k));
            $q = fn () => DB::table('transactions')->where('user_id', $party)->where('company_id', $company);
            $running = (float) ($q()->where('id', '<', $from)->orderByDesc('id')->value('balance') ?? 0);
            foreach ($q()->where('id', '>=', $from)->orderBy('id')->get(['id', 'type', 'old_balance', 'debit', 'credit', 'balance']) as $r) {
                if ($r->type === 'opening_balance') {
                    $running = (float) $r->balance;

                    continue;
                }
                $old = $running;
                $running = round($running + (float) $r->debit - (float) $r->credit, 2);
                if (abs((float) $r->old_balance - $old) > 0.005 || abs((float) $r->balance - $running) > 0.005) {
                    DB::table('transactions')->where('id', $r->id)->update(['old_balance' => $old, 'balance' => $running]);
                }
            }
        }
        if (! Schema::hasTable('portal_balances')) {
            return;
        }
        foreach (DB::table('portal_balances')->where('id', '>', $marks['pb'])->groupBy('portal_id')->selectRaw('portal_id, MIN(id) m')->get() as $n) {
            $this->portals[$n->portal_id] = min($this->portals[$n->portal_id] ?? PHP_INT_MAX, (int) $n->m);
        }
        foreach ($this->portals as $portal => $from) {
            $running = (float) (DB::table('portal_balances')->where('portal_id', $portal)->where('id', '<', $from)->orderByDesc('id')->value('current_balance') ?? 0);
            foreach (DB::table('portal_balances')->where('portal_id', $portal)->where('id', '>=', $from)->orderBy('id')->get() as $r) {
                $old = $running;
                $running = round($running + (float) $r->debit - (float) $r->credit, 2);
                if (abs((float) $r->old_balance - $old) > 0.005 || abs((float) $r->current_balance - $running) > 0.005) {
                    DB::table('portal_balances')->where('id', $r->id)->update(['old_balance' => $old, 'current_balance' => $running]);
                }
            }
        }
    }

    /**
     * The dates of the rows this run posted (Ticketing's refund rows keep their own). A new entry
     * moment: every row takes it. Otherwise each row takes the date and time of the row it replaced —
     * same type and party first, then same type — so a replace with no date edit moves no date
     * (the Ticketing Trail's restoreDates() rule); a row with nothing to replace takes the entry's.
     */
    private function stamp(Carbon $at, bool $momentEdited): void
    {
        $day = $at->toDateString();
        $when = $at->format('Y-m-d H:i:s');
        $take = function (array &$pool, array $keys) {
            foreach ($keys as $k) {
                if (! empty($pool[$k])) {
                    return array_shift($pool[$k]);
                }
            }

            return null;
        };
        $txPool = [];
        foreach ($momentEdited ? [] : $this->replaced['transactions'] as $r) {
            $v = [$r['payment_date'], $r['transaction_time'] ?? null, $r['created_at']];
            $txPool[$r['type'].':'.$r['user_id']][] = $v;
            $txPool[$r['type']][] = $v;
        }
        foreach (DB::table('transactions')->where('id', '>', $this->marks['tx'])->where('type', '!=', 'ticket_purchase_refund')->get(['id', 'type', 'user_id']) as $r) {
            $v = $take($txPool, [$r->type.':'.$r->user_id, $r->type]);
            DB::table('transactions')->where('id', $r->id)->update($v
                ? ['payment_date' => substr((string) $v[0], 0, 10), 'transaction_time' => $v[1], 'created_at' => $v[2]]
                : ['payment_date' => $day, 'created_at' => $when]);
        }
        $jePool = [];
        foreach ($momentEdited ? [] : $this->replaced['journals'] as $j) {
            $jePool[$j['entry']['source']][] = [$j['entry']['date'], $j['entry']['entry_time'], $j['entry']['created_at']];
        }
        foreach (DB::table('journal_entries')->where('id', '>', $this->marks['je'])->where('source', '!=', 'ticket_purchase_cancel')->get(['id', 'source']) as $j) {
            $v = $take($jePool, [$j->source]);
            DB::table('journal_entries')->where('id', $j->id)->update($v
                ? ['date' => $v[0], 'entry_time' => $v[1], 'created_at' => $v[2]]
                : ['date' => $day, 'entry_time' => $at->format('H:i:s'), 'created_at' => $when]);
        }
        // an airline refund re-posted through Ticketing keeps the date its replaced row had, always
        $refundPool = [];
        foreach ($this->replaced['transactions'] as $r) {
            if ($r['type'] === 'ticket_purchase_refund') {
                $refundPool[$r['invoice_id'].':'.$r['user_id']][] = [$r['payment_date'], $r['transaction_time'] ?? null, $r['created_at']];
            }
        }
        foreach (DB::table('transactions')->where('id', '>', $this->marks['tx'])->where('type', 'ticket_purchase_refund')->get(['id', 'invoice_id', 'user_id']) as $r) {
            if ($v = $take($refundPool, [$r->invoice_id.':'.$r->user_id])) {
                DB::table('transactions')->where('id', $r->id)->update(['payment_date' => substr((string) $v[0], 0, 10), 'transaction_time' => $v[1], 'created_at' => $v[2]]);
            }
        }
        if (! Schema::hasTable('portal_balances')) {
            return;
        }
        $refundPb = [];
        foreach ($this->replaced['portal_balances'] as $r) {
            if ($r['type'] === 'ticket_purchase_refund') {
                $refundPb[$r['invoice_id'].':'.$r['portal_id']][] = [$r['date'], $r['created_at']];
            }
        }
        foreach (DB::table('portal_balances')->where('id', '>', $this->marks['pb'])->where('type', 'ticket_purchase_refund')->get(['id', 'invoice_id', 'portal_id']) as $r) {
            if ($v = $take($refundPb, [$r->invoice_id.':'.$r->portal_id])) {
                DB::table('portal_balances')->where('id', $r->id)->update(['date' => $v[0], 'created_at' => $v[1]]);
            }
        }
        $pbPool = [];
        foreach ($momentEdited ? [] : $this->replaced['portal_balances'] as $r) {
            $pbPool[$r['type'].':'.$r['portal_id']][] = [$r['date'], $r['created_at']];
        }
        foreach (DB::table('portal_balances')->where('id', '>', $this->marks['pb'])->where('type', '!=', 'ticket_purchase_refund')->get(['id', 'type', 'portal_id']) as $r) {
            $v = $take($pbPool, [$r->type.':'.$r->portal_id]);
            DB::table('portal_balances')->where('id', $r->id)->update($v ? ['date' => $v[0], 'created_at' => $v[1]] : ['date' => $day, 'created_at' => $when]);
        }
    }

    /** Every journal posted in this run must balance — or nothing is posted. */
    private function assertBalanced(int $jeMark): void
    {
        foreach (DB::table('journal_entries')->where('id', '>', $jeMark)->pluck('id') as $id) {
            $s = DB::table('journal_items')->where('journal_entry_id', $id)->whereNull('deleted_at')->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();
            if (abs((float) $s->d - (float) $s->c) > 0.01) {
                throw new TrailRefused('Journal #'.$id.' would not balance (Dr ৳'.number_format((float) $s->d, 2).' / Cr ৳'.number_format((float) $s->c, 2).') — nothing was posted.');
            }
        }
    }

    /** Corrected rows are marked as already told, so the next party notice does not list them. Twin of Rewriter::quiet(). */
    private function quiet(int $txMark): void
    {
        if (! Schema::hasTable('party_ledger_notifications')) {
            return;
        }
        $rows = DB::table('transactions')->where('id', '>', $txMark)->whereNotNull('user_id')->groupBy('user_id')
            ->selectRaw('user_id, MIN(id) a, MAX(id) b, COUNT(*) n')->get();
        foreach ($rows as $r) {
            PartyLedgerNotification::create([
                'user_id' => $r->user_id, 'kind' => 'trail', 'sent_by' => Auth::id(),
                'from_transaction_id' => $r->a, 'to_transaction_id' => $r->b, 'entry_count' => $r->n,
                'email_status' => 'skipped', 'sms_status' => 'skipped',
            ]);
        }
    }

    // ── small helpers ───────────────────────────────────────────────────

    private function moment(?string $at, Change $c): Carbon
    {
        if (! $at) {
            return $c->created_at ? Carbon::parse($c->created_at) : now();
        }
        try {
            $d = Carbon::parse($at);
        } catch (\Throwable) {
            throw new TrailRefused('The date is not a date.');
        }
        // its own date, left as it was, is never "in the future" — the clock that stamped it may run
        // ahead of this one (live rows 2026-11-02 carried 16:53 at 12:45 Dhaka)
        if ($c->created_at && $d->format('Y-m-d H:i') === Carbon::parse($c->created_at)->format('Y-m-d H:i')) {
            return Carbon::parse($c->created_at);
        }
        if ($d->gt(now())) {
            throw new TrailRefused('An entry cannot be dated after now.');
        }

        return $d;
    }

    private function moveLabel(Change $c): string
    {
        $from = $c->fromFlight?->flight_number;
        $to = $c->toFlight?->flight_number;
        $move = $from && $to && $from !== $to ? $from.' → '.$to : ($from ?: $to);

        return $move ? ' · '.$move : '';
    }

    private array $accounts = [];

    private function acc(string $key): ?int
    {
        if (! array_key_exists($key, $this->accounts)) {
            $id = Account::where('code', config('accounts.'.$key))->value('id');
            $this->accounts[$key] = $id ? (int) $id : null;
        }

        return $this->accounts[$key];
    }
}
