<?php

namespace App\Traits;

use App\Models\ContractFlightPassenger;
use App\Models\Transaction;

/**
 * Posting a contract-flight passenger's reissue cost onto the agent's Party
 * Statement, when the agent is the one who bears it.
 *
 * Twin of ReconcilesContractFlightAgent, deliberately kept as its own charge
 * (own Transaction type, own journal source) rather than folded into
 * agent_price: agent_price is the package the agent was originally quoted,
 * and mixing a reissue into it would make that quote unreadable from the
 * ledger afterwards. reissue_cost itself stays excluded from
 * getTotalTravelCostAttribute() either way (see that method's docblock) —
 * this trait only decides whether the agent is additionally billed for it.
 *
 * The host class must also use PostsPartyLedger and PostsSaleJournal, same
 * requirement as ReconcilesContractFlightAgent.
 */
trait ReconcilesContractFlightReissue
{
    /**
     * reissue_cost is billed to the agent only while reissue_bearer is
     * 'agent' — 'company' or null means we absorb it, so any existing
     * billed row is cleared. Clearing (rather than deleting) keeps the
     * reversal visible on the statement, same reasoning as
     * ReconcilesContractFlightAgent::reconcileAgentLedger().
     */
    protected function reconcileReissueLedger(ContractFlightPassenger $passenger, ?int $previousAgentId = null): void
    {
        $postedAgentId = Transaction::where('invoice_id', $passenger->id)
            ->where('type', ContractFlightPassenger::REISSUE_LEDGER_TYPE)
            ->where('debit', '>', 0)
            ->value('user_id');

        $previousAgentId ??= $postedAgentId ? (int) $postedAgentId : null;

        // Passenger moved to another agent (or lost their agent) while a
        // reissue charge was billed — zero the old agent's row first, then
        // fall through to post fresh against the current agent below.
        if ($previousAgentId && $previousAgentId !== (int) $passenger->agent_id) {
            $this->reconcilePartyLedgerRow(
                $previousAgentId,
                ContractFlightPassenger::REISSUE_LEDGER_TYPE,
                $passenger->id,
                true,
                0.0,
                [
                    'account_id' => null,
                    'payment_date' => now(),
                    'remarks' => 'Reissue charge moved to another agent or cleared',
                ]
            );
        }

        if (! $passenger->agent_id) {
            $this->deleteSaleJournal(ContractFlightPassenger::REISSUE_LEDGER_TYPE, $passenger->id);
            return;
        }

        $billable = $passenger->reissue_bearer === 'agent' && (float) ($passenger->reissue_cost ?? 0) > 0;
        $amount = $billable ? (float) $passenger->reissue_cost : 0.0;

        $passenger->loadMissing(['contractFlight', 'passportHolder']);
        $name = $passenger->passportHolder?->name ?: 'passenger';
        $flightNo = $passenger->contractFlight?->flight_number ?: 'contract flight';

        $this->reconcilePartyLedgerRow(
            $passenger->agent_id,
            ContractFlightPassenger::REISSUE_LEDGER_TYPE,
            $passenger->id,
            true,
            $amount,
            [
                'account_id' => null,
                'payment_date' => now(),
                'reference_no' => ($passenger->contractFlight?->flight_number ?: 'CF') . '-' . $passenger->id . '-RI',
                'remarks' => $billable
                    ? 'Reissue cost billed to agent — ' . $name . ' (' . $flightNo . ')'
                    : 'Reissue charge cleared — ' . $name . ' (' . $flightNo . ')',
            ]
        );

        if ($amount > 0) {
            $this->updateSaleJournal(
                ContractFlightPassenger::REISSUE_LEDGER_TYPE,
                $passenger->id,
                $passenger->contractFlight?->creator?->company_id ?? auth()->user()?->company_id ?? 2,
                now(),
                ($passenger->contractFlight?->flight_number ?: 'CF') . '-' . $passenger->id . '-RI',
                'Reissue cost billed to agent — ' . $name . ' (' . $flightNo . ')',
                $amount,
                0.0,
                $amount,
                null,
                config('accounts.flight_sales_revenue'),
                false,
                'agent',
                $passenger->agent_id
            );
        } else {
            $this->deleteSaleJournal(ContractFlightPassenger::REISSUE_LEDGER_TYPE, $passenger->id);
        }
    }

    /**
     * Zero a billed reissue charge for a passenger about to be removed from
     * a flight — twin of ReconcilesContractFlightAgent::voidAgentLedger().
     * Called before the delete, while the row still exists.
     */
    protected function voidReissueLedger(ContractFlightPassenger $passenger): void
    {
        if (! $passenger->agent_id) {
            return;
        }

        $passenger->loadMissing(['contractFlight', 'passportHolder']);

        $this->reconcilePartyLedgerRow(
            $passenger->agent_id,
            ContractFlightPassenger::REISSUE_LEDGER_TYPE,
            $passenger->id,
            true,
            0.0,
            [
                'account_id' => null,
                'payment_date' => now(),
                'reference_no' => ($passenger->contractFlight?->flight_number ?: 'CF') . '-' . $passenger->id . '-RI',
                'remarks' => 'Removed from flight — ' . ($passenger->passportHolder?->name ?: 'passenger')
                    . ' (' . ($passenger->contractFlight?->flight_number ?: 'contract flight') . ')',
            ]
        );

        $this->deleteSaleJournal(ContractFlightPassenger::REISSUE_LEDGER_TYPE, $passenger->id);
    }
}
