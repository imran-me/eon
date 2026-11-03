<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ticketing as a department (owner, 2026-10-12). Read-only — it writes
 * nothing and no agent / vendor ledger reads it.
 *
 *   Sold directly    profit = agent price − cost price (Manage Sales invoice),
 *                    plus what Ticket Orders changes did to a sold ticket.
 *   To Contract      Contract Flight buys its tickets from Ticketing at the
 *   Flight           net price: revenue = cost, profit 0. The flight's own
 *                    profit is made per passport (CompanyFunds::perHead()).
 *   Own losses       what the airline kept on a cancelled ticket nobody bought
 *                    from us, and change fees on an unsold ticket.
 *   Stock            bought, not sold, not on a flight: an asset, not P&L.
 *
 * The direct-sale and change formulas are the ones TicketAccountsController
 * uses (sales(), changes()) — change one and change the other.
 */
class TicketingProfit
{
    public const COMPANY_ID = 2;

    public static function summary(): array
    {
        try {
            return ['ok' => true] + self::compute();
        } catch (\Throwable $e) {
            \Log::warning('TicketingProfit: '.$e->getMessage());

            return ['ok' => false];
        }
    }

    /** Everything the department figures need, ticket by ticket. */
    public static function compute(): array
    {
        $tickets = DB::table('ticket_purchases')->whereNull('deleted_at')->where('company_id', self::COMPANY_ID)
            ->get(['id', 'ticket_no', 'amount', 'status', 'purchase_date',
                Schema::hasColumn('ticket_purchases', 'refund_amount') ? 'refund_amount' : DB::raw('NULL as refund_amount'),
                ReturnCancel::ready() ? 'return_refund_amount' : DB::raw('NULL as return_refund_amount')]);
        $ids = $tickets->pluck('id');

        $soldIds = DB::table('ticket_sale_items as i')->join('ticket_sales as s', 's.id', '=', 'i.ticket_sale_id')
            ->whereNull('s.deleted_at')->where('s.status', '!=', 'cancelled')->whereIn('i.ticket_purchase_id', $ids)
            ->pluck('i.ticket_purchase_id')->flip();

        // A ticket is Contract Flight's if it is linked to a passenger now, or
        // was before a cancellation unlinked it (the change row keeps the ids).
        $cfIds = collect();
        if (Schema::hasTable('contract_flight_passenger_tickets')) {
            $cfIds = DB::table('contract_flight_passenger_tickets')->whereIn('ticket_purchase_id', $ids)->pluck('ticket_purchase_id');
        }
        if (Schema::hasTable('contract_flight_passenger_changes')) {
            DB::table('contract_flight_passenger_changes')->whereNotNull('previous_ticket_purchase_ids')->pluck('previous_ticket_purchase_ids')
                ->each(function ($json) use (&$cfIds) {
                    $cfIds = $cfIds->merge(array_map('intval', (array) json_decode((string) $json, true)));
                });
        }
        $cfIds = $cfIds->unique()->flip();

        $transfers = collect();
        $ownLosses = collect();
        $stock = collect();
        foreach ($tickets as $t) {
            $cancelled = $t->status === 'cancelled';
            // a live round trip whose return half was cancelled (2026-10-14): less that refund
            $net = $cancelled ? max(0.0, (float) $t->amount - (float) ($t->refund_amount ?? 0)) : (float) $t->amount - (float) ($t->return_refund_amount ?? 0);
            $row = ['id' => $t->id, 'pnr' => $t->ticket_no, 'month' => $t->purchase_date ? substr((string) $t->purchase_date, 0, 7) : null, 'date' => (string) $t->purchase_date, 'amount' => $net];
            if ($soldIds->has($t->id)) {
                continue;                                   // in a Manage Sales invoice
            } elseif ($cfIds->has($t->id)) {
                $transfers->push($row);                     // passed to Contract Flight at net
            } elseif ($cancelled) {
                if ($net > 0) {
                    $ownLosses->push($row + ['why' => 'Airline kept on cancellation']);
                }
            } else {
                $stock->push($row);                         // unsold — an asset
            }
        }

        // Direct sales: agent price − cost price of the tickets on the invoice.
        $sales = DB::table('ticket_sales as s')->whereNull('s.deleted_at')->where('s.status', '!=', 'cancelled')
            ->leftJoin(DB::raw('(SELECT i.ticket_sale_id, SUM(p.amount) cost FROM ticket_sale_items i JOIN ticket_purchases p ON p.id = i.ticket_purchase_id GROUP BY i.ticket_sale_id) c'), 'c.ticket_sale_id', '=', 's.id')
            ->get(['s.id', 's.sale_date', 's.total_amount', 'c.cost']);

        // Ticket Orders changes. On a sold ticket: + charge − agent price
        // reversed, cost = fee to the seller − the airline's refund. On an
        // unsold, non-flight ticket the fee is the department's own loss.
        $changeRevenue = 0.0;
        $changeCost = 0.0;
        if (Schema::hasTable('ticket_changes')) {
            foreach (DB::table('ticket_changes')->where('source', 'ticketing')->get() as $c) {
                $refundBack = in_array($c->kind, ['refund', 'void', 'return_cancel'], true) ? (float) $c->refund_amount : 0.0;
                if ($c->client_id) {
                    $changeRevenue += (float) $c->charged - (float) $c->sale_reversed;
                    $changeCost += (float) $c->cost_added - $refundBack;
                } elseif ((float) $c->cost_added > 0 && ! $cfIds->has($c->ticket_purchase_id)) {
                    $ownLosses->push(['id' => $c->ticket_purchase_id, 'pnr' => null, 'month' => substr((string) $c->created_at, 0, 7), 'date' => (string) $c->created_at, 'amount' => (float) $c->cost_added, 'why' => 'Change fee on an unsold ticket']);
                }
            }
        }

        $baggage = TicketBaggage::movements(self::COMPANY_ID);
        // Linked baggage is included in the flight's per-passenger profit already.
        if (Schema::hasTable('contract_flight_passenger_tickets')) {
            $linked = DB::table('contract_flight_passenger_tickets')->pluck('ticket_purchase_id')->all();
            $baggage = $baggage->whereNotIn('ticket_purchase_id', $linked);
        }
        $changeRevenue += (float) $baggage->sum('revenue');
        $changeCost += (float) $baggage->sum('cost_added');

        $directRevenue = (float) $sales->sum('total_amount');
        $directCost = (float) $sales->sum('cost');
        $transfer = (float) $transfers->sum('amount');
        $own = (float) $ownLosses->sum('amount');
        $revenue = $directRevenue + $changeRevenue + $transfer;
        $cost = $directCost + $changeCost + $transfer + $own;

        return [
            'transfers' => $transfers, 'own_losses' => $ownLosses, 'stock' => $stock,
            'totals' => [
                'direct_revenue' => $directRevenue, 'direct_cost' => $directCost, 'direct_profit' => $directRevenue - $directCost,
                'change_revenue' => $changeRevenue, 'change_cost' => $changeCost,
                'transfer' => $transfer, 'transfer_count' => $transfers->count(),
                'own_loss' => $own, 'own_loss_count' => $ownLosses->count(),
                'stock' => (float) $stock->sum('amount'), 'stock_count' => $stock->count(),
                'revenue' => $revenue, 'cost' => $cost, 'profit' => $revenue - $cost,
            ],
        ];
    }
}
