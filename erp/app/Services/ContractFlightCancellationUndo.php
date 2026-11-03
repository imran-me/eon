<?php

namespace App\Services;

use App\Http\Controllers\Dashboard\TicketPurchaseController;
use App\Models\ContractFlight;
use App\Models\ContractFlightPassenger as Pax;
use App\Models\ContractFlightPassengerChange as Change;
use App\Models\TicketPurchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Undo of a WRONG cancellation (owner, 2026-10-26: "this cancel was wrong, I want
 * to reverse it, as it was before, remove if any ledger entry done for
 * cancellations too"). The owner's exception to "nothing is deleted on a cancel",
 * super admin only: every line the cancellation posted is REMOVED (and copied
 * verbatim into contract_flight_undo_logs), his tickets come back live through
 * Ticketing's own code, and he is back on his flight — restored if it was
 * deleted after he left it.
 *
 * Safe while wrong: it refuses, touching nothing, when anything happened after
 * the cancellation, when it was corrected later, or when what it finds does not
 * add up (the package must land exactly where it stood, the agent's ledger must
 * move exactly as his receivable does).
 */
class ContractFlightCancellationUndo
{
    public static function available(): bool
    {
        try {
            return Schema::hasTable('contract_flight_undo_logs');
        } catch (\Throwable) {
            return false;
        }
    }

    /** The cancellation event: its main row and any "same move" split rows — or a refusal. */
    private function event(Pax $p): array
    {
        if ($p->contract_flight_id) {
            throw new ContractFlightUndoRefused('This passenger is not under Cancelled.');
        }
        $main = Change::where('contract_flight_passenger_id', $p->id)->where('kind', 'cancelled')->whereNotNull('reason')->orderByDesc('id')->first();
        if (! $main || ! $main->from_flight_id) {
            throw new ContractFlightUndoRefused('There is no record of the flight he left — use Re-assign.');
        }
        $after = Change::where('contract_flight_passenger_id', $p->id)->where('id', '>', $main->id)->get();
        $split = $after->filter(fn ($c) => $c->kind === 'cancelled' && str_contains((string) $c->note, 'same move as change #'.$main->id));
        if ($other = $after->first(fn ($c) => ! $split->contains('id', $c->id))) {
            throw new ContractFlightUndoRefused('Something happened after this cancellation (change #'.$other->id.', '.$other->kind.') — undo that first.');
        }
        $ids = collect([$main->id])->merge($split->pluck('id'))->all();
        if (DB::table('journal_entries')->where('source', Change::FIX_SOURCE)->whereIn('source_id', $ids)->exists()) {
            throw new ContractFlightUndoRefused('This cancellation was corrected afterwards (Flight changes → Correct) — it cannot be undone automatically.');
        }
        // the cancel posts inside one transaction, just before its change row
        $prev = Change::where('contract_flight_passenger_id', $p->id)->where('id', '<', $main->id)->orderByDesc('id')->value('created_at');
        $since = Carbon::parse($main->created_at)->subSeconds(60);
        if ($prev && Carbon::parse($prev)->gte($since)) {
            $since = Carbon::parse($prev)->addSecond();
        }
        $flight = ContractFlight::withTrashed()->find($main->from_flight_id);
        if (! $flight) {
            throw new ContractFlightUndoRefused('The flight he left no longer exists at all — use Re-assign.');
        }
        if (Pax::where('contract_flight_id', $flight->id)->where('passport_holder_id', $p->passport_holder_id)->where('id', '!=', $p->id)->exists()) {
            throw new ContractFlightUndoRefused('This passport is already on '.$flight->flight_number.'.');
        }
        $tickets = $after->merge([$main])->flatMap(fn ($c) => (array) ($c->previous_ticket_purchase_ids ?? []))->map(fn ($i) => (int) $i)->unique()->values();

        return compact('main', 'ids', 'since', 'flight', 'tickets');
    }

    /** What the undo would remove and restore — read-only, for the confirmation. */
    public function plan(Pax $p): array
    {
        $e = $this->event($p);
        $rows = $this->rows($p, $e);
        $lines = [];
        foreach ($rows['tx'] as $r) {
            $lines[] = ($r->credit > 0 ? 'Cr ' : 'Dr ').'৳'.number_format((float) max($r->debit, $r->credit), 2).' · '.(DB::table('users')->where('id', $r->user_id)->value('name') ?: 'party').' · '.$r->remarks;
        }
        foreach ($rows['company'] as $j) {
            $lines[] = 'Company Ledger: '.$j->description;
        }
        foreach (TicketPurchase::whereIn('id', $e['tickets'])->get() as $t) {
            $lines[] = 'Ticket '.$t->ticket_no.' back to live — its airline refund ৳'.number_format((float) $t->refund_amount, 2).' removed';
        }

        return ['flight' => $e['flight']->flight_number, 'restore_flight' => $e['flight']->trashed(), 'lines' => $lines];
    }

    /** Rows the event posted on the agent and the Company Ledger (tickets are Ticketing's). */
    private function rows(Pax $p, array $e): array
    {
        $byChange = DB::table('transactions')->whereIn('invoice_id', $e['ids'])
            ->whereIn('type', [Change::LEDGER_TYPE, Change::PENALTY_LEDGER_TYPE, Change::FARE_LEDGER_TYPE, Change::PRICE_REVISION_TYPE])->get();
        $reversal = $p->agent_id ? DB::table('transactions')->where('invoice_id', $p->id)->where('type', Pax::AGENT_REVERSAL_TYPE)
            ->where('user_id', $p->agent_id)->where('created_at', '>=', $e['since'])->get() : collect();
        $tx = $byChange->merge($reversal);
        $je = DB::table('journal_entries')->where(function ($q) use ($e, $tx) {
            $q->where(fn ($w) => $w->whereIn('source', [Change::LEDGER_TYPE, Change::PENALTY_COMPANY_SOURCE, Change::FARE_COMPANY_SOURCE])->whereIn('source_id', $e['ids']));
            foreach ($tx as $r) {
                $q->orWhere(fn ($w) => $w->where('source', $r->type)->where('source_id', $r->id));
            }
        })->get();

        return ['tx' => $tx, 'je' => $je, 'company' => $je->whereIn('source', [Change::PENALTY_COMPANY_SOURCE, Change::FARE_COMPANY_SOURCE])];
    }

    public function run(Pax $p, ?string $reason = null): string
    {
        return DB::transaction(function () use ($p, $reason) {
            $p = Pax::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $e = $this->event($p);
            $rows = $this->rows($p, $e);
            $ar = DB::table('accounts')->where('code', config('accounts.accounts_receivable'))->value('id');

            // the agent's ledger must move exactly as his receivable does
            $agentMove = round($rows['tx']->where('user_id', $p->agent_id)->sum(fn ($r) => $r->debit - $r->credit), 2);
            $arMove = round((float) DB::table('journal_items')->whereIn('journal_entry_id', $rows['je']->pluck('id'))->where('account_id', $ar)->selectRaw('COALESCE(SUM(debit)-SUM(credit),0) v')->value('v'), 2);
            if (abs($agentMove - $arMove) > 0.01) {
                throw new ContractFlightUndoRefused('The agent\'s lines (৳'.number_format($agentMove, 2).') and his receivable (৳'.number_format($arMove, 2).') do not match for this cancellation — nothing was removed.');
            }

            $removed = [
                'change_rows' => Change::whereIn('id', $e['ids'])->get()->map(fn ($c) => $c->getAttributes())->all(),
                'passenger' => $p->getAttributes(),
                'transactions' => $rows['tx']->map(fn ($r) => (array) $r)->all(),
                'journals' => $rows['je']->map(fn ($j) => ['entry' => (array) $j, 'items' => DB::table('journal_items')->where('journal_entry_id', $j->id)->get()->map(fn ($i) => (array) $i)->all()])->all(),
                'tickets' => [],
            ];
            $parties = [];
            foreach ($rows['tx'] as $r) {
                $parties[(int) $r->user_id] = min($parties[(int) $r->user_id] ?? PHP_INT_MAX, (int) $r->id);
            }
            $portals = [];

            DB::table('transactions')->whereIn('id', $rows['tx']->pluck('id'))->delete();
            DB::table('journal_items')->whereIn('journal_entry_id', $rows['je']->pluck('id'))->delete();
            DB::table('journal_entries')->whereIn('id', $rows['je']->pluck('id'))->delete();

            // back on his flight, as before — the flight too, if it was deleted after he left
            if ($e['flight']->trashed()) {
                $e['flight']->restore();
            }
            $p->update(['contract_flight_id' => $e['flight']->id, 'exception_flag' => $e['main']->previous_flag]);

            // his tickets: linked again, then un-cancelled by Ticketing's own code
            $ticketing = app(TicketPurchaseController::class);
            foreach (TicketPurchase::whereIn('id', $e['tickets'])->get() as $t) {
                if ($t->status !== 'cancelled') {
                    continue;
                }
                if (! DB::table('contract_flight_passenger_tickets')->where('contract_flight_passenger_id', $p->id)->where('ticket_purchase_id', $t->id)->exists()) {
                    $p->ticketPurchases()->attach($t->id, ['linked_by' => Auth::id()]);
                }
                try {
                    $u = $ticketing->undoCancelFromContractFlight($t, $e['since']);
                } catch (\RuntimeException $ex) {
                    throw new ContractFlightUndoRefused($ex->getMessage().' — nothing was removed.');
                }
                $removed['tickets'][] = $u['removed'];
                foreach ($u['parties'] as $k => $v) {
                    $parties[$k] = min($parties[$k] ?? PHP_INT_MAX, $v);
                }
                foreach ($u['portals'] as $k => $v) {
                    $portals[$k] = min($portals[$k] ?? PHP_INT_MAX, $v);
                }
            }

            Change::whereIn('id', $e['ids'])->delete();

            // the package must now stand exactly where it did before the cancellation
            if ($p->agent_id) {
                $chargeable = ! in_array($p->exception_flag, Pax::AGENT_CHARGE_VOIDING_FLAGS, true);
                $reversed = round((float) DB::table('transactions')->where('invoice_id', $p->id)->where('type', Pax::AGENT_REVERSAL_TYPE)
                    ->where('user_id', $p->agent_id)->selectRaw('COALESCE(SUM(credit)-SUM(debit),0) v')->value('v'), 2);
                $should = $chargeable ? 0.0 : round((float) $p->agent_price, 2);
                if (abs($reversed - $should) > 0.01) {
                    throw new ContractFlightUndoRefused('His package would not come back exactly (reversed ৳'.number_format($reversed, 2).', should be ৳'.number_format($should, 2).') — nothing was removed.');
                }
                ContractFlightAgentReceipts::sync((int) $p->agent_id);
            }

            foreach ($parties as $party => $from) {
                $this->rewalkParty($party, $from);
            }
            foreach ($portals as $portal => $from) {
                $running = (float) (DB::table('portal_balances')->where('portal_id', $portal)->where('id', '<', $from)->orderByDesc('id')->value('current_balance') ?? 0);
                foreach (DB::table('portal_balances')->where('portal_id', $portal)->where('id', '>=', $from)->orderBy('id')->get() as $r) {
                    $old = $running;
                    $running = round($running + (float) $r->debit - (float) $r->credit, 2);
                    DB::table('portal_balances')->where('id', $r->id)->update(['old_balance' => $old, 'current_balance' => $running]);
                }
            }

            $e['flight']->fresh()->syncMoneyFromPassengers();
            $summary = 'Wrong cancellation undone — back on '.$e['flight']->flight_number.($e['flight']->wasChanged('deleted_at') ? ' (flight restored)' : '')
                .'; '.count($removed['transactions']).' ledger line(s), '.count($removed['journals']).' journal(s) and '.count($removed['tickets']).' ticket refund(s) removed.';
            $p->events()->create(['type' => 'note', 'code' => 'cancel_undone', 'label' => 'Cancellation undone', 'note' => $summary.($reason ? ' Reason: '.$reason : ''),
                'occurred_at' => now(), 'created_by' => Auth::id()]);
            DB::table('contract_flight_undo_logs')->insert([
                'contract_flight_passenger_id' => $p->id, 'change_ids' => implode(',', $e['ids']), 'summary' => $summary,
                'reason' => $reason ? mb_substr($reason, 0, 1000) : null, 'removed' => json_encode($removed),
                'created_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return ($p->passportHolder?->name ?: 'Passenger').' is back on '.$e['flight']->flight_number.' as before — the cancellation and its lines are removed.';
        });
    }

    /** Twin of TicketTrail\Rewriter::rewalkParty — an opening balance row is an anchor, never rewritten. */
    private function rewalkParty(int $party, int $from): void
    {
        $running = (float) (DB::table('transactions')->where('user_id', $party)->where('id', '<', $from)->orderByDesc('id')->value('balance') ?? 0);
        foreach (DB::table('transactions')->where('user_id', $party)->where('id', '>=', $from)->orderBy('id')->get(['id', 'type', 'old_balance', 'debit', 'credit', 'balance']) as $r) {
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
}
