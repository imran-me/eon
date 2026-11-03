<?php

namespace App\Services;

use App\Models\ContractFile;
use App\Models\ContractFileSale;
use App\Models\ContractFileServiceCost;
use App\Models\ContractFlight;
use App\Models\ContractFlightBooking;
use App\Models\PartyInvoice;
use App\Models\PaymentSchedule;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\Transaction;
use App\Models\VisaProcess;
use App\Models\VisaSale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PartyInvoiceAllocation
{
    private const TYPES = [
        'visa_sale' => [VisaSale::class, 'client_id', 'invoice_number', 'total_amount', 'paid_amount', 'due_amount', 'status', 'sale', 'Visa'],
        'ticket_sale' => [TicketSale::class, 'client_id', 'invoice_no', 'total_amount', 'paid_amount', 'due_amount', 'payment_status', 'sale', 'Ticket'],
        'flight_sale' => [ContractFlightBooking::class, 'client_id', 'booking_number', 'total_amount', 'paid_amount', 'due_amount', 'payment_status', 'sale', 'Contract Flight'],
        'file_sale' => [ContractFileSale::class, 'client_id', 'invoice_number', 'total_amount', 'paid_amount', 'due_amount', 'payment_status', 'sale', 'Contract File'],
        'contract_file_client' => [ContractFile::class, 'client_id', 'file_number', 'client_price', 'client_paid_amount', 'client_due_amount', null, 'sale', 'Contract File'],
        'party_invoice' => [PartyInvoice::class, 'user_id', 'invoice_number', 'amount', 'paid_amount', 'due_amount', 'payment_status', 'sale', 'Custom Invoice'],
        'visa_purchase' => [VisaProcess::class, 'vendor_id', 'application_id', 'costing_price', 'cost_paid_amount', 'due_amount', 'payment_status', 'payable', 'Visa Vendor Cost'],
        'ticket_purchase' => [TicketPurchase::class, 'vendor_id', 'ticket_no', 'amount', 'paid_amount', 'due_amount', 'payment_status', 'payable', 'Ticket Purchase'],
        'contract_flight' => [ContractFlight::class, 'vendor_id', 'flight_number', 'cost_price', 'cost_paid_amount', 'due_amount', null, 'payable', 'Contract Flight Cost'],
        'contract_file' => [ContractFile::class, 'vendor_id', 'file_number', 'visa_rate', 'cost_paid_amount', 'due_amount', null, 'payable', 'Contract File Cost'],
        'contract_file_service_cost' => [ContractFileServiceCost::class, 'vendor_id', 'title', 'amount', 'paid_amount', 'due_amount', 'payment_status', 'payable', 'Contract File Service'],
    ];

    public function unpaidForParty(int $partyId, ?string $side = null, ?int $companyId = null): Collection
    {
        return collect(self::TYPES)->filter(fn ($c) => ! $side || $c[7] === $side)
            ->flatMap(function (array $c, string $type) use ($partyId, $companyId) {
                [$model, $partyColumn, $number, $total, $paid, $due, , $documentSide, $service] = $c;
                if ($type === 'contract_file_service_cost') {
                    if (! Schema::hasTable('contract_file_service_costs')) {
                        return collect();
                    }

                    return $model::where($partyColumn, $partyId)->when($companyId && Schema::hasColumn((new $model)->getTable(), 'company_id'), fn ($q) => $q->where('company_id', $companyId))->whereNull('voided_at')->orderBy('id')->get()->filter(fn ($doc) => $doc->due_amount > 0)
                        ->map(fn ($doc) => ['value' => $type.':'.$doc->id, 'type' => $type, 'side' => $documentSide, 'service' => $service, 'number' => $doc->title, 'due' => round($doc->due_amount, 2)]);
                }

                return $model::query()->where($partyColumn, $partyId)->where($due, '>', 0)
                    ->when($companyId && Schema::hasColumn((new $model)->getTable(), 'company_id'), fn ($q) => $q->where('company_id', $companyId))
                    ->when(in_array($type, ['ticket_sale', 'ticket_purchase'], true), fn ($q) => $q->where('status', '!=', 'cancelled'))
                    ->orderBy('id')->get(['id', $number, $total, $paid, $due])
                    ->map(fn ($doc) => ['value' => $type.':'.$doc->id, 'type' => $type, 'side' => $documentSide,
                        'service' => $service, 'number' => $doc->{$number}, 'due' => round((float) $doc->{$due}, 2)]);
            })->values();
    }

    public function allocateMany(int $partyId, float $paymentAmount, string $mode, array $manual, string $side, ?int $bankId, ?string $method, ?string $date, ?string $reference): array
    {
        if ($mode === 'unallocated') {
            return [];
        }
        $items = [];
        if ($mode === 'auto') {
            $remaining = round($paymentAmount, 2);
            foreach ($this->unpaidForParty($partyId, $side) as $doc) {
                if ($remaining <= 0) {
                    break;
                }
                $take = min($remaining, (float) $doc['due']);
                $items[] = ['key' => $doc['value'], 'amount' => $take];
                $remaining = round($remaining - $take, 2);
            }
            if ($remaining > .001) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds total unpaid documents by ৳'.number_format($remaining, 2).'. Choose Unallocated for an advance.']);
            }
        } else {
            foreach ($manual as $row) {
                $amount = round((float) ($row['amount'] ?? 0), 2);
                if ($amount > 0) {
                    $items[] = ['key' => (string) ($row['key'] ?? ''), 'amount' => $amount];
                }
            }
            if (abs((float) collect($items)->sum('amount') - $paymentAmount) > .001) {
                throw ValidationException::withMessages(['invoice_allocations' => 'Allocated total must equal the payment amount.']);
            }
        }
        if (! $items) {
            throw ValidationException::withMessages(['invoice_allocations' => 'No unpaid invoice or bill was available.']);
        }

        return collect($items)->map(fn ($r) => $this->allocateOne($r['key'], $partyId, $r['amount'], $side, $bankId, $method, $date, $reference))->all();
    }

    private function allocateOne(string $key, int $partyId, float $amount, string $side, ?int $bankId, ?string $method, ?string $date, ?string $reference): array
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, null);
        if (! isset(self::TYPES[$type]) || ! ctype_digit((string) $id)) {
            throw ValidationException::withMessages(['invoice_allocations' => 'Invalid invoice/bill selection.']);
        }
        [$model, $partyColumn, $number, $total, $paidColumn, $dueColumn, $statusColumn, $documentSide, $service] = self::TYPES[$type];
        if ($documentSide !== $side) {
            throw ValidationException::withMessages(['invoice_allocations' => 'Selected document is on the wrong payment side.']);
        }
        /** @var Model $doc */
        $doc = $model::query()->whereKey((int) $id)->lockForUpdate()->first();
        if (! $doc || (int) $doc->{$partyColumn} !== $partyId) {
            throw ValidationException::withMessages(['invoice_allocations' => 'A selected document does not belong to this party.']);
        }
        $due = round((float) $doc->{$dueColumn}, 2);
        if ($due <= 0 || $amount > $due + .001) {
            throw ValidationException::withMessages(['amount' => $service.' '.$doc->{$number}.' has only ৳'.number_format($due, 2).' due.']);
        }
        $paid = round((float) $doc->{$paidColumn} + $amount, 2);
        $effectiveTotal = $type === 'contract_file_client' ? $doc->net_client_charge : ($type === 'contract_file' ? $doc->net_processing_cost : ($type === 'contract_file_service_cost' ? $doc->net_amount : (float) $doc->{$total}));
        if ($type === 'ticket_sale') {
            $effectiveTotal = max(0, $effectiveTotal - FlightPackageTicketBilling::covered((int) $doc->id, $partyId));
        }
        if (isset(PartyDiscountService::TYPES[$type])) {
            $effectiveTotal = max(0, $effectiveTotal - PartyDiscountService::total($type, (int) $doc->id));
        }
        $newDue = max(0, round($effectiveTotal - $paid, 2));
        $updates = [$paidColumn => $paid, $dueColumn => $newDue];
        if ($type === 'contract_file_service_cost') {
            unset($updates[$dueColumn]);
        }
        if ($statusColumn) {
            $updates[$statusColumn] = $newDue <= 0 ? 'paid' : 'partial';
        }
        if ($type === 'party_invoice') {
            // Generic invoices do not own a bank column; the payment journal
            // and its party-ledger row carry that settlement information.
        } elseif ($documentSide === 'sale' && $type !== 'contract_file_client') {
            $updates['bank_id'] = $bankId;
            if ($type !== 'ticket_sale') {
                $updates['payment_method'] = $method;
            }
        } elseif ($type === 'contract_file_service_cost') {
            $updates['bank_id'] = $bankId;
            $updates['payment_date'] = $date;
        } elseif ($type !== 'contract_file_client') {
            $updates[$type === 'ticket_purchase' ? 'bank_id' : 'cost_bank_id'] = $bankId;
        }
        $doc->forceFill($updates)->save();

        $hasDiscount = isset(PartyDiscountService::TYPES[$type]) && PartyDiscountService::total($type, (int) $doc->id) > 0;
        PaymentSchedule::where('schedulable_type', $doc->getMorphClass())->where('schedulable_id', $doc->id)
            ->where('type', $documentSide === 'sale' ? 'receive' : 'pay')
            ->update(['amount' => $hasDiscount ? $newDue : $effectiveTotal, 'paid_amount' => $hasDiscount ? 0 : $paid, 'status' => $newDue <= 0 ? 'paid' : 'pending', 'paid_date' => $newDue <= 0 ? $date : null]);

        if ($type === 'contract_file_client') {
            $this->createLedgerRow($partyId, 'customer', $type, (int) $doc->id, $amount, ['account_id' => $bankId, 'payment_date' => $date, 'reference_no' => $reference ?: $doc->{$number}, 'payment_method' => $method, 'remarks' => $service.' payment — '.$doc->{$number}]);
        } elseif ($documentSide === 'sale') {
            $this->reconcileSaleLedger($partyId, $type, (int) $doc->id, $paid, $bankId, $date, $reference, $method, $service, (string) $doc->{$number});
        } else {
            $this->createLedgerRow($partyId, 'supplier', $type, (int) $doc->id, $amount, ['account_id' => $bankId, 'payment_date' => $date, 'reference_no' => $reference ?: $doc->{$number}, 'payment_method' => $method, 'remarks' => $service.' payment — '.$doc->{$number}]);
        }

        return ['type' => $type, 'id' => $doc->id, 'number' => $doc->{$number}, 'service' => $service, 'amount' => $amount, 'due' => $newDue];
    }

    private function reconcileSaleLedger(int $partyId, string $type, int $id, float $paid, ?int $bankId, ?string $date, ?string $reference, ?string $method, string $service, string $number): void
    {
        $attrs = ['account_id' => $bankId, 'payment_date' => $date, 'reference_no' => $reference ?: $number, 'payment_method' => $method, 'remarks' => $service.' payment — '.$number];
        $row = Transaction::where('user_id', $partyId)->where('invoice_id', $id)->where('type', $type)->where('credit', '>', 0)->first();
        if (! $row) {
            $this->createLedgerRow($partyId, 'customer', $type, $id, $paid, $attrs);

            return;
        }
        $delta = round($paid - (float) $row->credit, 2);
        $row->update(array_merge(['credit' => $paid, 'balance' => (float) $row->balance - $delta], $attrs));
        if ($delta != 0.0) {
            Transaction::where('user_id', $partyId)->where('id', '>', $row->id)->increment('old_balance', -$delta);
            Transaction::where('user_id', $partyId)->where('id', '>', $row->id)->increment('balance', -$delta);
        }
    }

    private function createLedgerRow(int $partyId, string $userType, string $type, int $id, float $credit, array $attrs): void
    {
        $last = Transaction::where('user_id', $partyId)->orderByDesc('id')->lockForUpdate()->first();
        $old = (float) ($last?->balance ?? 0);
        Transaction::create(array_merge(['user_id' => $partyId, 'user_type' => $userType, 'type' => $type, 'invoice_id' => $id, 'old_balance' => $old, 'debit' => 0, 'credit' => $credit, 'balance' => $old - $credit], $attrs));
    }
}
