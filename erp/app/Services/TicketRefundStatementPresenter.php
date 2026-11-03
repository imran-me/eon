<?php

namespace App\Services;

use App\Models\TicketChange;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Present append-only refund / void postings as one reconciled statement event. */
class TicketRefundStatementPresenter
{
    /**
     * $foldSale: pull a one-ticket invoice's sale row into the refund line.
     * The Party Statement passes false (owner, 2026-10-28): every refund then
     * reads the same — the sale on its own row, the refund line = reversal −
     * penalty — whether the ticket was alone on its invoice or shared one.
     */
    public function group(Collection $rows, float $openingBalance, bool $foldSale = true): Collection
    {
        $candidateIds = $rows->whereIn('type', [TicketChange::SALE_REVERSAL_TYPE, TicketChange::CHARGE_TYPE])
            ->pluck('invoice_id')->filter()->unique()->values();
        if ($candidateIds->isEmpty()) {
            return $rows;
        }

        $changes = TicketChange::whereIn('id', $candidateIds)
            ->whereIn('kind', ['refund', 'void', 'refund_correction'])->get();
        $purchaseIds = $changes->pluck('ticket_purchase_id')->filter()->unique();
        if ($purchaseIds->isEmpty()) {
            return $rows;
        }

        $allChanges = TicketChange::with('ticketPurchase.passportHolder:id,name')
            ->whereIn('ticket_purchase_id', $purchaseIds)
            ->whereIn('kind', ['refund', 'void', 'refund_correction'])->get()
            ->groupBy('ticket_purchase_id');
        $replacementById = [];
        $hiddenIds = [];

        foreach ($allChanges as $purchaseId => $refundChanges) {
            $changeIds = $refundChanges->pluck('id');
            $members = $rows->filter(fn ($row) => $changeIds->contains((int) $row->invoice_id)
                && in_array($row->type, [TicketChange::SALE_REVERSAL_TYPE, TicketChange::CHARGE_TYPE], true));
            if ($members->isEmpty() || ! $this->containsCompletePostingSet($members, $changeIds)) {
                continue;
            }

            $original = $refundChanges->whereIn('kind', ['refund', 'void'])->sortByDesc('id')->first();
            $anchor = $members->first(fn ($row) => (int) $row->invoice_id === (int) $original?->id
                && $row->type === TicketChange::SALE_REVERSAL_TYPE) ?? $members->last();
            if (! $original || ! $anchor) {
                continue;
            }

            $saleItem = DB::table('ticket_sale_items')
                ->where('ticket_purchase_id', $purchaseId)->latest('id')->first();
            $agentPrice = (float) ($saleItem?->price ?? 0);
            $agentRefund = round((float) $original->sale_reversed, 2);
            if ($agentPrice <= 0 || $agentRefund < 0 || $agentRefund > $agentPrice) {
                continue;
            }

            // A one-ticket invoice can be presented as one complete lifecycle:
            // original sale + reversal + final void/refund charge. Multi-ticket
            // invoices stay separate because their one sale row belongs to all
            // passengers and cannot safely be attributed to this ticket alone.
            $saleRow = null;
            if ($foldSale && $saleItem && DB::table('ticket_sale_items')->where('ticket_sale_id', $saleItem->ticket_sale_id)->count() === 1) {
                $saleRows = Transaction::where('user_id', $anchor->user_id)
                    ->where('company_id', $anchor->company_id)
                    ->where('type', 'ticket_sale')
                    ->where('invoice_id', $saleItem->ticket_sale_id)
                    ->get();
                if ($saleRows->count() === 1) {
                    $saleRow = $rows->firstWhere('id', $saleRows->first()->id);
                }
            }

            $lifecycle = $saleRow ? $members->push($saleRow) : $members;
            $net = round((float) $lifecycle->sum('debit') - (float) $lifecycle->sum('credit'), 2);
            $presented = clone $anchor;
            $presented->debit = $net > 0 ? $net : 0;
            $presented->credit = $net < 0 ? abs($net) : 0;
            $presented->type = 'ticket_refund_summary';
            $presented->remarks = 'Ticket refund — '.($original->ticketPurchase?->ticket_no ?: '#'.$purchaseId)
                .' · '.($original->ticketPurchase?->passportHolder?->name ?: 'Passenger');
            $presented->refund_summary = [
                'original_agent_price' => round($agentPrice, 2),
                'refund_to_agent' => $agentRefund,
                'retained_amount' => round($agentPrice - $agentRefund, 2),
                'airline_penalty' => round((float) $original->penalty, 2),
                'service_charge' => round((float) $original->service_fee, 2),
                'final_charge' => round((float) $members->sum('debit'), 2),
                'kind' => $original->kind,
                'ticket_purchase_id' => (int) $purchaseId,
                'transaction_ids' => $lifecycle->pluck('id')->values()->all(),
            ];
            $replacementById[$anchor->id] = $presented;
            foreach ($lifecycle as $member) {
                if ((int) $member->id !== (int) $anchor->id) {
                    $hiddenIds[$member->id] = true;
                }
            }
        }

        $running = $openingBalance;

        return $rows->map(function ($row) use ($replacementById, $hiddenIds) {
            if (isset($hiddenIds[$row->id])) {
                return null;
            }

            return $replacementById[$row->id] ?? $row;
        })->filter()->values()->each(function ($row) use (&$running) {
            $row->old_balance = $running;
            $running += (float) $row->debit - (float) $row->credit;
            $row->balance = $running;
        });
    }

    private function containsCompletePostingSet(Collection $members, Collection $changeIds): bool
    {
        $sample = $members->first();
        $allIds = Transaction::where('user_id', $sample->user_id)
            ->where('company_id', $sample->company_id)
            ->whereIn('invoice_id', $changeIds)
            ->whereIn('type', [TicketChange::SALE_REVERSAL_TYPE, TicketChange::CHARGE_TYPE])
            ->pluck('id');

        return $allIds->diff($members->pluck('id'))->isEmpty();
    }
}
