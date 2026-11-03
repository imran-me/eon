<?php

namespace App\Traits;

use App\Models\Bank;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerServiceCost;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

/**
 * Reconciles passenger service-cost metadata with vendor statement, bank and
 * GL. Hosts also use PostsPartyLedger and PostsPurchaseJournal.
 */
trait ReconcilesContractFlightServiceCosts
{
    /**
     * Synchronise all non-ticket costs after the passenger amount columns have
     * been saved. Submitted metadata is optional so the compact passenger edit
     * modal can still change an amount while preserving its accounting setup.
     */
    protected function reconcilePassengerServiceCosts(
        ContractFlightPassenger $passenger,
        array $submittedCosts = []
    ): void {
        $passenger->loadMissing(['contractFlight.creator', 'passportHolder', 'serviceCosts']);
        $existing = $passenger->serviceCosts->keyBy('cost_type');

        foreach (ContractFlightPassengerServiceCost::TYPES as $type => $definition) {
            $amount = max(0, (float) ($passenger->{$definition['field']} ?? 0));
            /** @var ContractFlightPassengerServiceCost|null $cost */
            $cost = $existing->get($type);

            if ($amount <= 0) {
                if ($cost) {
                    $this->removeServiceCostPosting($cost);
                    $cost->delete();
                }
                continue;
            }

            $submitted = $submittedCosts[$type] ?? null;
            $oldVendorId = $cost?->vendor_id ? (int) $cost->vendor_id : null;

            if ($cost) {
                // Remove the old informal bank/party movements before the
                // record changes. The formal journal is updated in place below.
                $this->unwindServiceCostLedger($cost);
            } else {
                $cost = new ContractFlightPassengerServiceCost([
                    'contract_flight_passenger_id' => $passenger->id,
                    'cost_type' => $type,
                    'payment_status' => 'unassigned',
                    'created_by' => Auth::id(),
                ]);
            }

            $title = $type === 'other'
                ? ($passenger->other_title ?: 'Other service')
                : $definition['label'];

            if (is_array($submitted)) {
                $paidAmount = min($amount, max(0, (float) ($submitted['paid_amount'] ?? 0)));
                $status = $paidAmount <= 0 ? 'due' : ($paidAmount >= $amount ? 'paid' : 'partial');

                $cost->fill([
                    'title' => $title,
                    'amount' => $amount,
                    'vendor_id' => $this->nullableId($submitted['vendor_id'] ?? null),
                    'payment_status' => $status,
                    'paid_amount' => $paidAmount,
                    'bank_id' => $paidAmount > 0 ? $this->nullableId($submitted['bank_id'] ?? null) : null,
                    'incurred_on' => $submitted['incurred_on'] ?? null,
                    'payment_date' => $paidAmount > 0 ? ($submitted['payment_date'] ?? null) : null,
                    'due_date' => $paidAmount < $amount ? ($submitted['due_date'] ?? null) : null,
                    'reference_no' => $this->nullableText($submitted['reference_no'] ?? null),
                ]);
            } else {
                // No accounting controls were rendered by this caller. Keep
                // the setup, but normalise money/status if the compact modal
                // changed the underlying passenger amount.
                $cost->title = $title;
                $cost->amount = $amount;
                $cost->paid_amount = min($amount, max(0, (float) $cost->paid_amount));
                if ($cost->vendor_id && $cost->incurred_on) {
                    $cost->payment_status = $cost->paid_amount <= 0
                        ? 'due'
                        : ($cost->paid_amount >= $amount ? 'paid' : 'partial');
                } else {
                    $cost->payment_status = 'unassigned';
                }
                if ($cost->paid_amount <= 0) {
                    $cost->bank_id = null;
                    $cost->payment_date = null;
                }
                if ($cost->paid_amount >= $amount) {
                    $cost->due_date = null;
                }
            }

            $cost->save();

            if (! $this->serviceCostIsPostable($cost)) {
                $this->deletePurchaseJournal(ContractFlightPassengerServiceCost::JOURNAL_SOURCE, $cost->id);
                if ($oldVendorId) {
                    $this->rechainPartyLedger($oldVendorId);
                }
                continue;
            }

            $this->postServiceCost($passenger, $cost);

            if ($oldVendorId && $oldVendorId !== (int) $cost->vendor_id) {
                $this->rechainPartyLedger($oldVendorId);
            }
        }

        $passenger->unsetRelation('serviceCosts');
    }

    /** Remove all service-cost accounting before a passenger is deleted. */
    protected function removePassengerServiceCosts(ContractFlightPassenger $passenger): void
    {
        $passenger->serviceCosts()->get()->each(function (ContractFlightPassengerServiceCost $cost) {
            $this->removeServiceCostPosting($cost);
            $cost->delete();
        });
    }

    private function postServiceCost(
        ContractFlightPassenger $passenger,
        ContractFlightPassengerServiceCost $cost
    ): void {
        $amount = (float) $cost->amount;
        $paid = min($amount, (float) $cost->paid_amount);
        $due = max(0, $amount - $paid);
        $bank = $paid > 0 ? Bank::findOrFail($cost->bank_id) : null;

        if ($bank && ! $bank->account_id) {
            throw new \RuntimeException("Bank '{$bank->name}' is not linked to a chart-of-accounts account.");
        }

        $reference = $cost->reference_no ?: $this->serviceCostReference($passenger, $cost);
        $date = $cost->incurred_on ?? $passenger->created_at ?? now();
        $name = $passenger->passportHolder?->name ?? ('Passenger #' . $passenger->id);

        $this->updatePurchaseJournal(
            ContractFlightPassengerServiceCost::JOURNAL_SOURCE,
            $cost->id,
            $passenger->contractFlight?->creator?->company_id ?? Auth::user()?->company_id ?? 2,
            $date,
            $reference,
            $cost->type_label . ' cost — ' . $name,
            $amount,
            $paid,
            $due,
            $bank?->account_id,
            config('accounts.flight_purchase_cost'),
            false,
            'vendor',
            $cost->vendor_id
        );

        // Vendor statement shows the full bill followed by whatever was paid
        // at entry time, matching Ticket Purchase's debit/credit convention.
        $this->postPartyLedgerRow((int) $cost->vendor_id, [
            'type' => ContractFlightPassengerServiceCost::JOURNAL_SOURCE,
            'account_id' => null,
            'payment_date' => $date,
            'reference_no' => $reference,
            'invoice_id' => $cost->id,
            'debit' => $amount,
            'credit' => 0,
            'remarks' => $cost->type_label . ' cost — ' . $name,
        ]);

        if ($paid > 0 && $bank) {
            $this->postPartyLedgerRow((int) $cost->vendor_id, [
                'type' => ContractFlightPassengerServiceCost::JOURNAL_SOURCE,
                'account_id' => $bank->id,
                'payment_date' => $cost->payment_date ?? $date,
                'reference_no' => $reference,
                'payment_method' => 'bank',
                'invoice_id' => $cost->id,
                'debit' => 0,
                'credit' => $paid,
                'remarks' => $cost->type_label . ' payment — ' . $name,
            ]);
            $bank->decrement('balance', $paid);
        }
    }

    private function removeServiceCostPosting(ContractFlightPassengerServiceCost $cost): void
    {
        $vendorId = $cost->vendor_id ? (int) $cost->vendor_id : null;
        $this->unwindServiceCostLedger($cost);
        $this->deletePurchaseJournal(ContractFlightPassengerServiceCost::JOURNAL_SOURCE, $cost->id);

        if ($vendorId) {
            $this->rechainPartyLedger($vendorId);
        }
    }

    /** Reverse informal bank movement and remove this cost's vendor rows. */
    private function unwindServiceCostLedger(ContractFlightPassengerServiceCost $cost): void
    {
        $rows = Transaction::where('type', ContractFlightPassengerServiceCost::JOURNAL_SOURCE)
            ->where('invoice_id', $cost->id)
            ->get();

        foreach ($rows->where('credit', '>', 0) as $payment) {
            if ($payment->account_id) {
                Bank::find($payment->account_id)?->increment('balance', (float) $payment->credit);
            }
        }

        $vendorIds = $rows->pluck('user_id')->filter()->unique();
        Transaction::whereIn('id', $rows->pluck('id'))->delete();
        $vendorIds->each(fn ($vendorId) => $this->rechainPartyLedger((int) $vendorId));
    }

    /** Stored balances are retained for older consumers even though statements recalculate them. */
    private function rechainPartyLedger(int $partyId): void
    {
        $running = 0.0;
        Transaction::where('user_id', $partyId)->orderBy('id')->get()
            ->each(function (Transaction $row) use (&$running) {
                $old = $running;
                $running = round($running + (float) $row->debit - (float) $row->credit, 2);
                $row->forceFill(['old_balance' => $old, 'balance' => $running])->saveQuietly();
            });
    }

    private function serviceCostIsPostable(ContractFlightPassengerServiceCost $cost): bool
    {
        if (! $cost->vendor_id || ! $cost->incurred_on || $cost->payment_status === 'unassigned') {
            return false;
        }

        return (float) $cost->paid_amount <= 0 || (bool) $cost->bank_id;
    }

    private function serviceCostReference(
        ContractFlightPassenger $passenger,
        ContractFlightPassengerServiceCost $cost
    ): string {
        $flight = $passenger->contractFlight?->flight_number ?: 'CF';
        return strtoupper($flight . '-P' . $passenger->id . '-' . str_replace('_', '-', $cost->cost_type));
    }

    private function nullableId($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableText($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
