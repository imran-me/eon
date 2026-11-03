<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\Ticket;
use App\Models\TicketChange;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\Transaction;
use App\Services\TicketBaggage;
use App\Support\UserRoleSetting\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Ticket edit page → "Delete wrong entry" (owner, 2026-10-12: "the 6 cancelled
 * are wrong entry … as they have hit many ledgers, those too will be deleted
 * automatically" — and, same day, "I should be able to delete individually").
 *
 * This is NOT a cancellation — cancelling still only ever adds rows (see
 * CLAUDE.md "Nothing is deleted on a cancel"). It removes ONE ticket that
 * should never have existed, together with everything it posted: its
 * Ticket Orders changes and their ledger rows / journals, its purchase and
 * refund rows / journals, legs and schedules. If it was sold, it comes off
 * its invoice: the invoice, the agent's invoice-charge row and the sale
 * journal are reduced by its price; the invoice itself goes only when this
 * was its last ticket. Each touched party's running balance is re-chained.
 *
 * Refused — nothing is touched — when:
 *   · real money moved (paid on the ticket or the invoice, a bank row, a
 *     portal debit): undoing a payment belongs to whoever recorded it;
 *   · the ticket is, or was, a contract flight passenger's;
 *   · the invoice's postings are not the plain shape this can reduce
 *     safely (one charge row, one Dr / Cr journal pair).
 * Super admin only.
 */
class TicketPurgeController extends Controller
{
    private function guard(): void
    {
        abort_unless(Access::allows('travels.ticketing', 'd'), 403, 'Deleting a wrong entry needs Delete on Ticketing.');
    }

    /** What would be removed, or why it cannot be. */
    public function preview(string $role, TicketPurchase $ticketPurchase)
    {
        $this->guard();
        $plan = $this->plan($ticketPurchase);

        return response()->json($plan['blocked']
            ? ['success' => false, 'message' => $plan['blocked']]
            : ['success' => true] + $this->summary($plan));
    }

    public function purge(string $role, TicketPurchase $ticketPurchase)
    {
        $this->guard();
        $plan = $this->plan($ticketPurchase);
        if ($plan['blocked']) {
            return response()->json(['success' => false, 'message' => $plan['blocked']]);
        }
        $summary = $this->summary($plan);
        $p = $ticketPurchase;

        DB::beginTransaction();
        try {
            // Each party's earliest touched row, to re-chain its balance from.
            $rebuild = [];
            $touch = function ($partyId, $rowId) use (&$rebuild) {
                if ($partyId) {
                    $rebuild[$partyId] = min($rebuild[$partyId] ?? PHP_INT_MAX, (int) $rowId);
                }
            };

            foreach ($plan['tx'] as $t) {
                $touch($t->user_id, $t->id);
            }
            Transaction::whereIn('id', $plan['tx']->pluck('id'))->delete();

            foreach ($plan['journals'] as $j) {
                $j->items()->forceDelete();
                $j->forceDelete();
            }
            if ($plan['changeIds']->isNotEmpty()) {
                DB::table('ticket_changes')->whereIn('id', $plan['changeIds'])->delete();
            }

            // Off its invoice.
            if ($sale = $plan['sale']) {
                DB::table('ticket_sale_items')->where('ticket_sale_id', $sale->id)->where('ticket_purchase_id', $p->id)->delete();
                if ($plan['lastOnSale']) {
                    foreach ($plan['saleTx'] as $t) {
                        $touch($t->user_id, $t->id);
                    }
                    Transaction::whereIn('id', $plan['saleTx']->pluck('id'))->delete();
                    foreach ($plan['saleJournals'] as $j) {
                        $j->items()->forceDelete();
                        $j->forceDelete();
                    }
                    if ($sale->status === 'confirm' && $sale->ticket_id && ($acc = Ticket::find($sale->ticket_id))) {
                        $acc->update(['qty' => $acc->qty + 1, 'total_sale_qty' => max(0, $acc->total_sale_qty - 1)]);
                    }
                    $sale->forceDelete();
                } else {
                    $price = $plan['price'];
                    $sale->update([
                        'total_amount' => round((float) $sale->total_amount - $price, 2),
                        'due_amount' => round((float) $sale->due_amount - $price, 2),
                    ]);
                    $row = $plan['saleTx']->first();
                    $row->update(['debit' => round((float) $row->debit - $price, 2)]);
                    $touch($row->user_id, $row->id);
                    foreach ($plan['saleJournals']->first()->items as $line) {
                        $line->update((float) $line->debit > 0
                            ? ['debit' => round((float) $line->debit - $price, 2)]
                            : ['credit' => round((float) $line->credit - $price, 2)]);
                    }
                }
            }

            if ($p->status === 'confirm' && $p->ticket_id && ($acc = Ticket::find($p->ticket_id)) && $acc->qty > 0) {
                $acc->decrement('qty');      // same as TicketPurchaseController::destroy()
            }
            $p->schedules()->delete();
            if (Schema::hasTable('ticket_legs')) {
                DB::table('ticket_legs')->where('ticket_purchase_id', $p->id)->delete();
            }
            if (Schema::hasTable('ticket_bookings') && Schema::hasColumn('ticket_bookings', 'confirmed_purchase_id')) {
                DB::table('ticket_bookings')->where('confirmed_purchase_id', $p->id)->update(['confirmed_purchase_id' => null]);
            }
            if ($p->attachment) {
                Storage::disk('public')->delete($p->attachment);
            }
            $p->delete();

            foreach ($rebuild as $partyId => $fromId) {
                $this->rebuildRunningBalance((int) $partyId, (int) $fromId);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['success' => false, 'message' => 'Nothing was deleted: '.$e->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Deleted '.$summary['ticket']
            .($summary['invoice'] ? ($summary['invoice_deleted'] ? ' and invoice '.$summary['invoice'] : ' — invoice '.$summary['invoice'].' reduced by ৳'.number_format($summary['price'], 0)) : '')
            .' · '.$summary['ledger_rows'].' ledger rows and '.$summary['journals'].' journal entries removed.']);
    }

    /** This one ticket and everything it posted. */
    private function plan(TicketPurchase $p): array
    {
        $plan = ['blocked' => null, 'sale' => null, 'lastOnSale' => false, 'price' => 0.0, 'saleTx' => collect(), 'saleJournals' => collect(),
            'tx' => collect(), 'journals' => collect(), 'changeIds' => collect(), 'ticket' => $p];

        // A contract flight's ticket: the flight's own code owns its postings.
        $cf = Schema::hasTable('contract_flight_passenger_tickets')
            && DB::table('contract_flight_passenger_tickets')->where('ticket_purchase_id', $p->id)->exists();
        if (! $cf && Schema::hasTable('contract_flight_passenger_changes')) {
            $cf = DB::table('contract_flight_passenger_changes')->whereNotNull('previous_ticket_purchase_ids')->pluck('previous_ticket_purchase_ids')
                ->contains(fn ($j) => in_array($p->id, array_map('intval', (array) json_decode((string) $j, true)), true));
        }
        $changeIds = Schema::hasTable('ticket_changes') ? DB::table('ticket_changes')->where('ticket_purchase_id', $p->id)->pluck('id') : collect();
        if (! $cf && $changeIds->isNotEmpty()) {
            $cf = DB::table('ticket_changes')->whereIn('id', $changeIds)->where('source', '!=', 'ticketing')->exists();
        }
        if ($cf) {
            $plan['blocked'] = 'This ticket belongs (or belonged) to a contract flight passenger. Its postings are owned by the flight — correct it from the passenger card.';

            return $plan;
        }
        $plan['changeIds'] = $changeIds;

        // Its own postings: purchase + refund rows, and its changes' rows.
        $plan['tx'] = Transaction::where(function ($q) use ($p, $changeIds) {
            $q->where(fn ($w) => $w->whereIn('type', ['ticket_purchase', 'ticket_purchase_refund', TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $p->id))
                ->orWhere(fn ($w) => $w->whereIn('type', [TicketChange::FEE_TYPE, TicketChange::FARE_TYPE, TicketChange::CHARGE_TYPE, TicketChange::SALE_REVERSAL_TYPE])->whereIn('invoice_id', $changeIds));
        })->get();
        $plan['journals'] = JournalEntry::where(function ($q) use ($p, $changeIds) {
            $q->where(fn ($w) => $w->whereIn('source', [TicketBaggage::COST, TicketBaggage::CHARGE, 'ticket_purchase', 'ticket_purchase_cancel'])->where('source_id', $p->id))
                ->orWhere(fn ($w) => $w->whereIn('source', [TicketChange::FEE_TYPE, TicketChange::CHARGE_TYPE, TicketChange::SALE_REVERSAL_TYPE, TicketChange::PENALTY_SOURCE])->whereIn('source_id', $changeIds));
        })->get();

        // Its invoice, if sold.
        $item = DB::table('ticket_sale_items')->where('ticket_purchase_id', $p->id)->first();
        if ($item && ($sale = TicketSale::find($item->ticket_sale_id))) {
            $plan['sale'] = $sale;
            $plan['price'] = (float) $item->price;
            $plan['lastOnSale'] = DB::table('ticket_sale_items')->where('ticket_sale_id', $sale->id)->count() <= 1;
            $plan['saleTx'] = Transaction::where('type', 'ticket_sale')->where('invoice_id', $sale->id)->get();
            $plan['saleJournals'] = JournalEntry::with('items')->where('source', 'ticket_sale')->where('source_id', $sale->id)->get();
        }

        // Money that really moved cannot be deleted from here.
        $paid = (float) $p->paid_amount + (float) ($plan['sale']?->paid_amount ?? 0);
        $portal = Schema::hasTable('portal_balances') && DB::table('portal_balances')->where('invoice_id', $p->id)->where('type', 'like', 'ticket_%')->exists();
        $bank = $plan['tx']->contains(fn ($t) => $t->account_id) || $plan['saleTx']->contains(fn ($t) => $t->account_id);
        if ($paid > 0 || $portal || $bank) {
            $plan['blocked'] = 'Money has moved on this ticket or its invoice (a payment to the seller, a portal debit, or a receipt on the invoice). Reverse that payment first — a wrong entry with real money behind it cannot simply be deleted.';

            return $plan;
        }

        // Reducing an invoice is only done on its plain shape: one charge
        // row, one journal with one debit and one credit line.
        if ($plan['sale'] && ! $plan['lastOnSale']) {
            $j = $plan['saleJournals'];
            $lines = $j->first()?->items ?? collect();
            $plain = $plan['saleTx']->count() === 1 && $j->count() === 1 && $lines->count() === 2
                && $lines->where('debit', '>', 0)->count() === 1 && $lines->where('credit', '>', 0)->count() === 1
                && (float) $plan['saleTx']->first()->debit >= $plan['price'] - 0.005;
            if (! $plain) {
                $plan['blocked'] = 'Invoice '.$plan['sale']->invoice_no.' has more than a plain charge on it, so this ticket cannot be taken off it automatically. Remove it from the invoice in Manage Sales first.';
            }
        }

        return $plan;
    }

    private function summary(array $plan): array
    {
        $p = $plan['ticket'];
        $parties = $plan['tx']->pluck('user_id')->merge($plan['saleTx']->pluck('user_id'))->filter()->unique();

        return [
            'ticket' => ($p->ticket_no ?: '#'.$p->id).' — '.($p->passportHolder?->name ?? 'passenger').' · ৳'.number_format((float) $p->amount, 0).' · '.$p->status,
            'invoice' => $plan['sale']?->invoice_no,
            'invoice_deleted' => $plan['sale'] ? $plan['lastOnSale'] : false,
            'price' => $plan['price'],
            'invoice_left' => $plan['sale'] && ! $plan['lastOnSale'] ? round((float) $plan['sale']->total_amount - $plan['price'], 2) : null,
            'changes' => $plan['changeIds']->count(),
            'ledger_rows' => $plan['tx']->count() + ($plan['lastOnSale'] ? $plan['saleTx']->count() : 0),
            'journals' => $plan['journals']->count() + ($plan['lastOnSale'] ? $plan['saleJournals']->count() : 0),
            'parties' => DB::table('users')->whereIn('id', $parties)->pluck('name')->values()->all(),
        ];
    }

    /** Same walk as TicketPurchaseController::rebuildPartyRunningBalancesFrom(). */
    private function rebuildRunningBalance(int $partyId, int $fromId): void
    {
        $running = (float) (Transaction::where('user_id', $partyId)->where('id', '<', $fromId)->orderByDesc('id')->value('balance') ?? 0);
        Transaction::where('user_id', $partyId)->where('id', '>=', $fromId)->orderBy('id')->get()
            ->each(function (Transaction $row) use (&$running) {
                $old = $running;
                $running = round($running + (float) $row->debit - (float) $row->credit, 2);
                if (abs((float) $row->old_balance - $old) > 0.005 || abs((float) $row->balance - $running) > 0.005) {
                    $row->update(['old_balance' => $old, 'balance' => $running]);
                }
            });
    }
}
