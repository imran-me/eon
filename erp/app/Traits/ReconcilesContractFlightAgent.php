<?php

namespace App\Traits;

use App\Models\ContractFlightPassenger;
use App\Models\Transaction;
use App\Services\ContractFlightAgentReceipts;

/**
 * Posting a contract-flight passenger's agent charge onto that agent's Party
 * Statement, in one place.
 *
 * It used to live as a private method on ContractFlightPassengerController,
 * which was fine while the passenger edit modal was the only screen that
 * could set agent_price. The Add/Edit Flight modal now sets it too, and two
 * copies of this would drift the moment one of them learned a new rule —
 * the exception-flag reversal below being exactly that kind of rule.
 *
 * The host class must also use PostsPartyLedger and PostsSaleJournal; this
 * trait deliberately does not pull them in itself, since the controllers and
 * the backfill command use those helpers directly as well.
 */
trait ReconcilesContractFlightAgent
{
    use PostsContractFlightAdjustments;

    /**
     * agent_price is a receivable — what we charge the agent for handling
     * their passenger, not a commission we pay them — so it posts as a debit,
     * the same direction a customer invoice would.
     *
     * A passenger carrying a terminal exception flag (cancelled, off
     * flight, deported) is not a charge that still stands. Since 2026-09-28
     * (owner: "no data should be deleted at all, upon cancellation, will be
     * reversed") the package row keeps its amount and a REVERSAL row is
     * added beside it (AGENT_REVERSAL_TYPE, credit, with its own journal).
     * Clearing the flag — a re-booking, a restore — adds a debit row that
     * charges it again. Nothing earlier is edited or removed; the statement
     * reads as the story: charged, reversed, charged on the new flight.
     */
    protected function reconcileAgentLedger(ContractFlightPassenger $passenger, ?int $previousAgentId = null): void
    {
        $postedAgentId = Transaction::where('invoice_id', $passenger->id)
            ->where('type', ContractFlightPassenger::AGENT_LEDGER_TYPE)
            ->where('debit', '>', 0)
            ->value('user_id');

        // Also self-heal rows written before callers started passing the old
        // agent explicitly: the ledger itself is the final evidence of which
        // party currently carries the debit.
        $previousAgentId ??= $postedAgentId ? (int) $postedAgentId : null;

        // If the passenger moved to another agent (or the agent was cleared),
        // zero the old agent's statement row before posting to the new party.
        // reconcilePartyLedgerRow() then re-chains every later balance on the
        // old statement. Without this, changing the dropdown left the former
        // agent billed forever.
        if ($previousAgentId && $previousAgentId !== (int) $passenger->agent_id) {
            $this->reconcilePartyLedgerRow(
                $previousAgentId,
                ContractFlightPassenger::AGENT_LEDGER_TYPE,
                $passenger->id,
                true,
                0.0,
                [
                    'account_id' => null,
                    'payment_date' => now(),
                    'remarks' => 'Charge moved to another agent or cleared',
                ]
            );
            // Any reversal already on the old agent is undone with an added
            // row, so their net for this passenger comes to zero, not below.
            $oldReversed = (float) Transaction::where('invoice_id', $passenger->id)
                ->where('type', ContractFlightPassenger::AGENT_REVERSAL_TYPE)
                ->where('user_id', $previousAgentId)
                ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) AS net')
                ->value('net');
            $this->postAgentAdjustment(
                $previousAgentId,
                ContractFlightPassenger::AGENT_REVERSAL_TYPE,
                $passenger->id,
                $oldReversed,
                'Reversal withdrawn — charge moved to another agent',
                'CF-' . $passenger->id,
                config('accounts.flight_sales_revenue'),
                auth()->user()?->company_id ?? 2
            );
        }

        if (! $passenger->agent_id) {
            $this->deleteSaleJournal(ContractFlightPassenger::AGENT_LEDGER_TYPE, $passenger->id);
            return;
        }

        $passenger->loadMissing(['contractFlight.creator', 'passportHolder', 'agent']);

        $chargeable = ! in_array($passenger->exception_flag, ContractFlightPassenger::AGENT_CHARGE_VOIDING_FLAGS, true);
        $price = (float) ($passenger->agent_price ?? 0);

        $name = $passenger->passportHolder?->name ?: 'passenger';
        $flightNo = $passenger->contractFlight?->flight_number ?: 'contract flight';
        $reference = ($passenger->contractFlight?->flight_number ?: 'CF') . '-' . $passenger->id;
        $companyId = $passenger->contractFlight?->creator?->company_id ?? auth()->user()?->company_id ?? 2;

        // Voided the old way (before 2026-09-28): the package row sits at zero
        // and no reversal exists. It already nets to nothing, so leave it —
        // re-posting a charge and a reversal dated today would put a past
        // cancellation on today's statement. It joins the new scheme the
        // moment he is charged again (re-booked or restored).
        if (! $chargeable) {
            $legacyVoid = Transaction::where('invoice_id', $passenger->id)
                    ->where('type', ContractFlightPassenger::AGENT_LEDGER_TYPE)->where('user_id', $passenger->agent_id)->exists()
                && ! Transaction::where('invoice_id', $passenger->id)
                    ->where('type', ContractFlightPassenger::AGENT_LEDGER_TYPE)->where('debit', '>', 0)->exists()
                && ! Transaction::where('invoice_id', $passenger->id)
                    ->where('type', ContractFlightPassenger::AGENT_REVERSAL_TYPE)->exists();
            if ($legacyVoid) {
                return;
            }
        }

        // The package row carries the quoted price. A price edit is a data
        // correction and is reconciled in place, as before; a row zeroed by
        // the old void (before 2026-09-28) is left as it is and a new one is
        // posted beside it, so no historic line is rewritten.
        $this->reconcilePartyLedgerRow(
            $passenger->agent_id,
            ContractFlightPassenger::AGENT_LEDGER_TYPE,
            $passenger->id,
            true,
            $price,
            [
                'account_id' => null,
                'payment_date' => now(),
                'reference_no' => $reference,
                'remarks' => 'Charged to agent — ' . $name . ' (' . $flightNo . ')',
            ]
        );

        // Party Statement is the operational sub-ledger; the GL must recognise
        // the same gross receivable and revenue exactly once. Payments/advance
        // receipts remain on Party Statement's Record Payment flow, which
        // posts Bank / AR separately.
        if ($price > 0) {
            $this->updateSaleJournal(
                ContractFlightPassenger::AGENT_LEDGER_TYPE,
                $passenger->id,
                $companyId,
                $passenger->created_at ?? now(),
                $reference,
                'Contract flight agent charge — ' . $name . ' (' . $flightNo . ')',
                $price,
                0.0,
                $price,
                null,
                config('accounts.flight_sales_revenue'),
                false,
                'agent',
                $passenger->agent_id
            );
        } else {
            $this->deleteSaleJournal(ContractFlightPassenger::AGENT_LEDGER_TYPE, $passenger->id);
        }

        // Reversals: bring what is reversed for THIS agent to what should be —
        // the whole price while voided, nothing while chargeable — by adding
        // one row for the difference.
        $reversed = (float) Transaction::where('invoice_id', $passenger->id)
            ->where('type', ContractFlightPassenger::AGENT_REVERSAL_TYPE)
            ->where('user_id', $passenger->agent_id)
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) AS net')
            ->value('net');
        $shouldBe = $chargeable ? 0.0 : $price;
        $diff = round($shouldBe - $reversed, 2);
        if (abs($diff) >= 0.005) {
            $flagLabel = ContractFlightPassenger::EXCEPTION_FLAGS[$passenger->exception_flag] ?? ($passenger->exception_flag ?: 'exception');
            $this->postAgentAdjustment(
                (int) $passenger->agent_id,
                ContractFlightPassenger::AGENT_REVERSAL_TYPE,
                $passenger->id,
                -$diff,
                $diff > 0
                    ? 'Package reversed (' . $flagLabel . ') — ' . $name . ' (' . $flightNo . ')'
                    : 'Package charged again — ' . $name . ' (' . $flightNo . ')',
                $reference,
                config('accounts.flight_sales_revenue'),
                $companyId
            );
        }

        // The charge changed, so how the agent's receipts spread over their
        // passengers may have too — for the old agent as well, if it moved.
        ContractFlightAgentReceipts::sync((int) $passenger->agent_id);
        if ($previousAgentId && $previousAgentId !== (int) $passenger->agent_id) {
            ContractFlightAgentReceipts::sync($previousAgentId);
        }
    }

    /**
     * Zero an agent charge for a passenger about to be removed from a flight.
     *
     * Called before the delete, while the row still exists — the ledger row is
     * keyed on the passenger id, so once the passenger is gone there is
     * nothing left to reconcile against and the debit would stand forever.
     */
    protected function voidAgentLedger(ContractFlightPassenger $passenger): void
    {
        if (! $passenger->agent_id) {
            return;
        }

        $passenger->loadMissing(['contractFlight.creator', 'passportHolder']);

        // Removing a passenger reverses what is still charged for him — a row
        // added beside the charge, never the charge zeroed (2026-09-28).
        $net = (float) Transaction::where('invoice_id', $passenger->id)
            ->whereIn('type', [ContractFlightPassenger::AGENT_LEDGER_TYPE, ContractFlightPassenger::AGENT_REVERSAL_TYPE])
            ->where('user_id', $passenger->agent_id)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS net')
            ->value('net');

        $this->postAgentAdjustment(
            (int) $passenger->agent_id,
            ContractFlightPassenger::AGENT_REVERSAL_TYPE,
            $passenger->id,
            -$net,
            'Removed from flight — ' . ($passenger->passportHolder?->name ?: 'passenger')
                . ' (' . ($passenger->contractFlight?->flight_number ?: 'contract flight') . ')',
            ($passenger->contractFlight?->flight_number ?: 'CF') . '-' . $passenger->id,
            config('accounts.flight_sales_revenue'),
            $passenger->contractFlight?->creator?->company_id ?? auth()->user()?->company_id ?? 2
        );
    }
}
