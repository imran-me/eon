<?php

namespace App\Services;

use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Keeps each contract-flight passenger's "received from agent" figure in
 * step with the agent's Party Statement.
 *
 * Money is received against the AGENT: Party Statement → Record Payment
 * posts a party_payment credit on the agent's ledger and a Bank/AR journal.
 * Nothing about a passenger is on that form, so the passenger rows'
 * agent_paid_amount used to be a hand-typed memo that the ledger never
 * touched — the same agent could read "paid" on their statement and "due"
 * on Flights & Passengers. This service is the one bridge: it spreads the
 * agent's ledger credits over their chargeable passengers oldest departure
 * first (FIFO aging) and writes the result onto the rows, so every screen
 * that reads agent_paid_amount agrees with the ledger automatically.
 *
 * Called after a party payment is recorded and after an agent charge is
 * (re)posted. It never writes when the agent has no ledger credit at all,
 * so an install that only ever typed the figure keeps what it typed.
 */
class ContractFlightAgentReceipts
{
    /**
     * FIFO allocation of ledger credits to passengers.
     *
     * @return array{credits: array<int,float>, allocated: array<int,float>}
     *                                                                       credits keyed by agent id, allocated keyed by passenger id
     */
    public static function allocate(Collection|array $agentIds): array
    {
        $agentIds = collect($agentIds)->filter()->unique()->values();
        if ($agentIds->isEmpty()) {
            return ['credits' => [], 'allocated' => []];
        }

        // Money received only — a package reversal (2026-09-28) or a charge
        // corrected downwards (2026-10-04) is a credit row but not a receipt.
        $credits = Transaction::whereIn('user_id', $agentIds)
            ->whereNotIn('type', ContractFlightPassengerChange::NOT_RECEIPT_TYPES)
            ->selectRaw('user_id, SUM(credit) as paid')->groupBy('user_id')->pluck('paid', 'user_id')
            ->map(fn ($v) => (float) $v)->all();

        $queue = ContractFlightPassenger::query()
            ->whereIn('contract_flight_passengers.agent_id', $agentIds)
            // Left join: a passenger under Cancelled has no flight but can
            // still owe a cancellation charge / penalty — his receipts must
            // reach it. He queues by when his card was made.
            ->leftJoin('contract_flights', 'contract_flights.id', '=', 'contract_flight_passengers.contract_flight_id')
            ->orderByRaw('COALESCE(contract_flights.departure_at, contract_flight_passengers.created_at)')->orderBy('contract_flight_passengers.id')
            ->get(['contract_flight_passengers.id', 'contract_flight_passengers.agent_id', 'contract_flight_passengers.agent_price', 'contract_flight_passengers.exception_flag'])
            ->groupBy('agent_id');

        // Penalties billed to the agent for a passenger's flight changes are
        // part of what his receipts pay off (2026-09-27). Guarded: none before
        // the changes migration.
        $penalties = [];
        try {
            if (ContractFlightPassengerChange::installed()) {
                $penalties = ContractFlightPassengerChange::where('bearer', 'agent')->whereNotNull('charge')
                    ->whereIn('contract_flight_passenger_id', $queue->flatten(1)->pluck('id'))
                    ->groupBy('contract_flight_passenger_id')->selectRaw('contract_flight_passenger_id, SUM(charge) as total')
                    ->pluck('total', 'contract_flight_passenger_id')->map(fn ($v) => (float) $v)->all();
                // Airline penalties and fare differences billed to the agent
                // (2026-09-28) are paid off by the same receipts.
                if (ContractFlightPassengerChange::hasFaultColumns()) {
                    ContractFlightPassengerChange::whereIn('contract_flight_passenger_id', $queue->flatten(1)->pluck('id'))
                        ->where(fn ($q) => $q->where('fault', 'agent')->orWhere('fare_bearer', 'agent'))
                        ->get()
                        ->each(function ($c) use (&$penalties) {
                            $add = ($c->fault === 'agent' ? (float) $c->airline_penalty : 0.0)
                                + $c->agentFareAmount();
                            $penalties[$c->contract_flight_passenger_id] = ($penalties[$c->contract_flight_passenger_id] ?? 0.0) + $add;
                        });
                }
            }
        } catch (\Throwable) {
            $penalties = [];
        }

        $allocated = [];
        foreach ($queue as $agentId => $list) {
            $left = $credits[$agentId] ?? 0.0;
            if ($left <= 0) {
                continue;
            }
            foreach ($list as $p) {
                // the package less a cancelled return's price revision (2026-10-14)
                $charge = in_array($p->exception_flag, ContractFlightPassenger::AGENT_CHARGE_VOIDING_FLAGS, true)
                    ? 0.0 : $p->chargedPackage();
                $charge += $penalties[$p->id] ?? 0.0;
                $charge += $p->baggageTotals()['charge'];
                $charge += $p->suspendWithdrawTotals()['charge'];
                $charge = max(0, $charge - PartyDiscountService::total(ContractFlightPassenger::AGENT_LEDGER_TYPE, (int) $p->id));
                $take = min($charge, $left);
                $allocated[$p->id] = $take;
                $left -= $take;
            }
        }

        return ['credits' => $credits, 'allocated' => $allocated];
    }

    /**
     * Write the allocation onto the passenger rows of one agent. Quiet
     * updates — no model events, so nothing re-posts the ledger from here.
     * Wrapped so a failure here can never fail the payment that triggered it.
     */
    public static function sync(?int $agentId): void
    {
        if (! $agentId) {
            return;
        }
        try {
            ['credits' => $credits, 'allocated' => $allocated] = self::allocate([$agentId]);
            if (($credits[$agentId] ?? 0) <= 0) {
                return; // no receipts on the ledger: the typed figure stands
            }
            ContractFlightPassenger::where('agent_id', $agentId)
                ->get(['id', 'agent_paid_amount', 'agent_paid_at'])
                ->each(function (ContractFlightPassenger $p) use ($allocated) {
                    $paid = round($allocated[$p->id] ?? 0.0, 2);
                    if (abs((float) ($p->agent_paid_amount ?? 0) - $paid) < 0.005) {
                        return;
                    }
                    $p->forceFill([
                        'agent_paid_amount' => $paid,
                        'agent_paid_at' => $paid > 0 ? ($p->agent_paid_at ?? now()) : null,
                    ])->saveQuietly();
                });
        } catch (\Throwable $e) {
            Log::warning('ContractFlightAgentReceipts::sync failed for agent '.$agentId.': '.$e->getMessage());
        }
    }
}
