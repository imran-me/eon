<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ContractFlightPassenger;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PartyDiscount;
use App\Models\PartyInvoice;
use App\Models\PaymentSchedule;
use App\Models\TicketSale;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartyDiscountService
{
    /** document_type of the part of an overall discount no invoice could absorb (kept as party credit). */
    public const UNALLOCATED = 'party_account';

    public const TYPES = ['ticket_sale' => TicketSale::class, 'party_invoice' => PartyInvoice::class, 'contract_flight_passenger_agent' => ContractFlightPassenger::class];

    public static function total(string $type, int $id): float
    {
        if (! Schema::hasTable('party_discounts')) {
            return 0;
        }

        return (float) PartyDiscount::where('document_type', $type)->where('document_id', $id)->whereNull('reversed_at')->sum('amount');
    }

    public static function due(float $total, float $paid, float $discount): float
    {
        return max(0, round($total - $paid - $discount, 2));
    }

    // Owner decision (2026-10-18): no ceiling. A discount may exceed the invoice
    // due or the party's balance; only a positive, finite amount is required.
    public static function validateAmount(float $amount): void
    {
        if (! is_finite($amount) || $amount < .01) {
            throw ValidationException::withMessages(['amount' => 'Discount must be a positive amount.']);
        }
    }

    public function document(string $type, int $id, bool $lock = false): Model
    {
        abort_unless(isset(self::TYPES[$type]), 422, 'Unsupported discount document.');
        $class = self::TYPES[$type];

        return $class::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
    }

    public function outstanding(string $type, Model $doc): float
    {
        if ($type === 'contract_flight_passenger_agent') {
            return (float) $doc->agent_due;
        }
        $total = (float) ($type === 'ticket_sale' ? $doc->total_amount : $doc->amount);
        if ($type === 'ticket_sale') {
            $total -= FlightPackageTicketBilling::covered((int) $doc->id, (int) $doc->client_id);
        }

        return self::due($total, (float) $doc->paid_amount, self::total($type, (int) $doc->id));
    }

    public function post(int $partyId, string $type, int $id, float $amount, string $date, string $reason, string $key, User $actor, ?int $companyId, ?string $batch = null): PartyDiscount
    {
        return DB::transaction(function () use ($partyId, $type, $id, $amount, $date, $reason, $key, $actor, $companyId, $batch) {
            $doc = $this->document($type, $id, true);
            $charge = $this->charge($partyId, $type, $id, $companyId, $actor);
            $party = User::findOrFail($partyId);
            abort_unless($party->hasRole('agent') || $party->hasRole('customer'), 422, 'Discount is only available for agents/customers.');
            $owner = $type === 'party_invoice' ? $doc->user_id : ($type === 'ticket_sale' ? $doc->client_id : $doc->agent_id);
            abort_unless((int) $owner === $partyId, 422, 'Invoice belongs to another party.');
            abort_if($doc->company_id && (int) $doc->company_id !== (int) $charge->company_id, 422, 'Invoice and ledger company do not match.');
            abort_if($date < substr((string) $charge->payment_date, 0, 10), 422, 'Discount cannot be dated before the invoice charge.');
            abort_if($type === 'ticket_sale' && $doc->status === 'cancelled', 422, 'Cancelled invoices cannot be discounted.');
            abort_if($type === 'contract_flight_passenger_agent' && in_array($doc->exception_flag, ContractFlightPassenger::AGENT_CHARGE_VOIDING_FLAGS, true), 422, 'Cancelled packages cannot be discounted.');
            if ($existing = PartyDiscount::where('request_key', $key)->first()) {
                abort_unless((int) $existing->user_id === $partyId && $existing->document_type === $type && (int) $existing->document_id === $id && abs((float) $existing->amount - $amount) < .005, 422, 'Request key is already used.');

                return $existing;
            }
            self::validateAmount($amount);
            $discount = PartyDiscount::create(['company_id' => $charge->company_id, 'user_id' => $partyId, 'document_type' => $type, 'document_id' => $id, 'charge_transaction_id' => $charge->id, 'amount' => round($amount, 2), 'discount_date' => $date, 'reason' => $reason, 'request_key' => $key, 'created_by' => $actor->id] + ($batch ? ['batch_key' => $batch] : []));
            $entry = $this->posting($discount, $charge, $date, $reason, $actor, false);
            $discount->update(['journal_entry_id' => $entry->id]);
            $this->refreshDue($type, $doc);

            return $discount;
        });
    }

    /**
     * One discount for the party, spread over his unpaid invoices oldest first
     * (the way receipts are allocated): one PartyDiscount row per invoice, all
     * sharing batch_key = $key. Whatever the invoices cannot absorb stays as a
     * party-level credit (document_type = UNALLOCATED) — the owner allows any
     * amount, and it must not silently vanish. One transaction: all of it
     * posts or none of it does.
     *
     * @return array{rows: array, split: array, unallocated: float}
     */
    public function postOverall(int $partyId, float $amount, string $date, string $reason, string $key, User $actor, ?int $companyId): array
    {
        self::validateAmount($amount);
        $existing = PartyDiscount::where('batch_key', $key)->orderBy('id')->get();
        if ($existing->isNotEmpty()) {
            abort_unless((int) $existing->first()->user_id === $partyId && abs((float) $existing->sum('amount') - $amount) < .005, 422, 'Request key is already used.');

            return $this->batchSummary($existing->all());
        }

        return DB::transaction(function () use ($partyId, $amount, $date, $reason, $key, $actor, $companyId) {
            $party = User::findOrFail($partyId);
            abort_unless($party->hasRole('agent') || $party->hasRole('customer'), 422, 'Discount is only available for agents/customers.');

            $candidates = [];
            $charges = Transaction::where('user_id', $partyId)->whereIn('type', array_keys(self::TYPES))->where('debit', '>', 0)
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('payment_date')->orderBy('id')->get();
            foreach ($charges->unique(fn ($c) => $c->type.':'.$c->invoice_id) as $charge) {
                $doc = self::TYPES[$charge->type]::find($charge->invoice_id);
                if (! $doc || ($charge->type === 'ticket_sale' && $doc->status === 'cancelled')) {
                    continue;
                }
                if ($charge->type === 'contract_flight_passenger_agent' && in_array($doc->exception_flag, ContractFlightPassenger::AGENT_CHARGE_VOIDING_FLAGS, true)) {
                    continue;
                }
                $owner = $charge->type === 'party_invoice' ? $doc->user_id : ($charge->type === 'ticket_sale' ? $doc->client_id : $doc->agent_id);
                if ((int) $owner !== $partyId || substr((string) $charge->payment_date, 0, 10) > $date) {
                    continue;
                }
                $due = $this->outstanding($charge->type, $doc);
                if ($due >= .01) {
                    $candidates[] = ['charge' => $charge, 'due' => $due];
                }
            }

            $companies = collect($candidates)->map(fn ($c) => (int) $c['charge']->company_id)->unique();
            // An overall credit may intentionally sit on the party account
            // after every document has already been settled. In that case
            // there are no candidate invoices from which to infer ownership;
            // use the party ledger when it belongs to exactly one company.
            if (! $companyId && $companies->isEmpty()) {
                $companies = Transaction::where('user_id', $partyId)
                    ->whereNotNull('company_id')
                    ->distinct()
                    ->pluck('company_id')
                    ->map(fn ($id) => (int) $id);
            }
            $company = $companyId ?: ($companies->count() === 1 ? (int) $companies->first() : null);
            abort_if(! $company, 422, 'This party has dues in more than one company (or none) — open the statement for one company, then give the discount.');

            $remaining = round($amount, 2);
            $rows = [];
            foreach ($candidates as $c) {
                if ($remaining < .01) {
                    break;
                }
                if ((int) $c['charge']->company_id !== (int) $company) {
                    continue;
                }
                $take = round(min($remaining, $c['due']), 2);
                $rows[] = $this->post($partyId, $c['charge']->type, (int) $c['charge']->invoice_id, $take, $date, $reason, (string) Str::uuid(), $actor, (int) $company, $key);
                $remaining = round($remaining - $take, 2);
            }

            if ($remaining >= .01) {
                $discount = PartyDiscount::create(['company_id' => $company, 'user_id' => $partyId, 'document_type' => self::UNALLOCATED, 'document_id' => $partyId, 'charge_transaction_id' => 0, 'amount' => $remaining, 'discount_date' => $date, 'reason' => $reason, 'request_key' => (string) Str::uuid(), 'batch_key' => $key, 'created_by' => $actor->id]);
                $entry = $this->posting($discount, null, $date, $reason, $actor, false);
                $discount->update(['journal_entry_id' => $entry->id]);
                $rows[] = $discount;
            }

            return $this->batchSummary($rows);
        });
    }

    private function batchSummary(array $rows): array
    {
        $split = [];
        $unallocated = 0.0;
        foreach ($rows as $row) {
            if ($row->document_type === self::UNALLOCATED) {
                $unallocated += (float) $row->amount;

                continue;
            }
            $ref = Transaction::where('id', $row->charge_transaction_id)->value('reference_no');
            $split[] = ['label' => $ref ?: $row->document_type.' #'.$row->document_id, 'amount' => (float) $row->amount];
        }

        return ['rows' => $rows, 'split' => $split, 'unallocated' => round($unallocated, 2)];
    }

    public function reverse(int $id, string $reason, User $actor, ?int $companyId): void
    {
        // Lock documents before discounts, in the same order as posting.
        $snapshot = PartyDiscount::findOrFail($id);
        abort_if(! $actor->is_super_admin && (int) $snapshot->company_id !== (int) $actor->company_id, 403);
        abort_if($companyId && (int) $snapshot->company_id !== $companyId, 403);
        DB::transaction(function () use ($snapshot, $reason, $actor) {
            $doc = $snapshot->document_type === self::UNALLOCATED ? null : $this->document($snapshot->document_type, (int) $snapshot->document_id, true);
            $discount = PartyDiscount::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            if ($discount->reversed_at) {
                return;
            }
            $charge = $discount->charge_transaction_id ? Transaction::findOrFail($discount->charge_transaction_id) : null;
            $entry = $this->posting($discount, $charge, now()->toDateString(), $reason, $actor, true);
            $discount->update(['reversed_at' => now(), 'reversed_by' => $actor->id, 'reversal_reason' => $reason, 'reversal_journal_entry_id' => $entry->id]);
            if ($doc) {
                $this->refreshDue($discount->document_type, $doc);
            }
        });
    }

    /** Reverse every row of an overall discount together. */
    public function reverseBatch(string $batch, string $reason, User $actor, ?int $companyId): int
    {
        $ids = PartyDiscount::where('batch_key', $batch)->whereNull('reversed_at')->orderBy('id')->pluck('id');
        abort_if($ids->isEmpty(), 422, 'This overall discount is already reversed.');
        DB::transaction(function () use ($ids, $reason, $actor, $companyId) {
            foreach ($ids as $id) {
                $this->reverse((int) $id, $reason, $actor, $companyId);
            }
        });

        return $ids->count();
    }

    private function charge(int $partyId, string $type, int $id, ?int $companyId, User $actor): Transaction
    {
        $charge = Transaction::where('user_id', $partyId)->where('type', $type)->where('invoice_id', $id)->where('debit', '>', 0)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->lockForUpdate()->firstOrFail();
        abort_if(! $actor->is_super_admin && (! $actor->company_id || (int) $charge->company_id !== (int) $actor->company_id), 403);

        return $charge;
    }

    private function posting(PartyDiscount $discount, ?Transaction $charge, string $date, string $reason, User $actor, bool $reverse): JournalEntry
    {
        $account = Account::where('code', config('accounts.sales_discount'))->where('status', 1)->whereNull('company_id')->where('type', 'income')->firstOrFail();
        abort_if($account->children()->exists(), 422, 'Discount account must be a posting leaf.');
        $ar = Account::where('code', config('accounts.accounts_receivable'))->where('status', 1)->where('type', 'asset')->whereNull('company_id')->firstOrFail();
        abort_if($ar->children()->exists(), 422, 'Receivable account must be a posting leaf.');
        $reference = ($reverse ? 'REV-' : '').'DISC-'.$discount->id;
        $label = $discount->batch_key ? 'Overall discount' : 'Discount';
        $description = ($reverse ? $label.' reversed' : $label.' allowed').' — '.($charge?->reference_no ?? 'party account').' — '.$reason;
        $entry = JournalEntry::create(['company_id' => $discount->company_id, 'created_by' => $actor->id, 'date' => $date, 'entry_time' => now()->format('H:i:s'), 'source' => $reverse ? 'party_discount_reversal' : 'party_discount', 'source_id' => $discount->id, 'reference' => $reference, 'description' => $description, 'beneficiary_party_id' => $discount->user_id, 'reversed_journal_entry_id' => $reverse ? $discount->journal_entry_id : null]);
        foreach ([[$account->id, $reverse ? 0 : $discount->amount, $reverse ? $discount->amount : 0, null], [$ar->id, $reverse ? $discount->amount : 0, $reverse ? 0 : $discount->amount, $discount->user_id]] as [$accountId, $debit, $credit, $partyId]) {
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $accountId, 'debit' => $debit, 'credit' => $credit, 'party_id' => $partyId, 'party_type' => $partyId ? (User::findOrFail($partyId)->hasRole('agent') ? 'agent' : 'customer') : null, 'note' => $description]);
        }
        $balance = (float) Transaction::where('user_id', $discount->user_id)->where('company_id', $discount->company_id)->selectRaw('COALESCE(SUM(debit - credit), 0) amount')->value('amount');
        Transaction::create(['company_id' => $discount->company_id, 'expense_department_id' => $charge?->expense_department_id, 'user_id' => $discount->user_id, 'user_type' => 'customer', 'type' => $entry->source, 'invoice_id' => $entry->id, 'account_id' => null, 'payment_method' => null, 'reference_no' => $reference, 'payment_date' => $entry->date->format('Y-m-d'), 'transaction_time' => $entry->entry_time, 'debit' => $reverse ? $discount->amount : 0, 'credit' => $reverse ? 0 : $discount->amount, 'old_balance' => $balance, 'balance' => $balance + ($reverse ? (float) $discount->amount : -(float) $discount->amount), 'remarks' => $description]);

        return $entry;
    }

    public function refreshDue(string $type, Model $doc): void
    {
        $due = $this->outstanding($type, $doc);
        $paid = (float) ($type === 'contract_flight_passenger_agent' ? $doc->agent_paid_amount : $doc->paid_amount);
        if ($type !== 'contract_flight_passenger_agent') {
            $doc->forceFill(['due_amount' => $due, 'payment_status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due')])->saveQuietly();
        }
        $schedules = PaymentSchedule::where('schedulable_type', $doc->getMorphClass())->where('schedulable_id', $doc->id)->where('type', 'receive')->lockForUpdate()->get();
        $openSchedules = $schedules->whereIn('status', PaymentSchedule::OPEN_STATUSES)->values();
        if ($openSchedules->count() > 1) {
            throw ValidationException::withMessages(['document' => 'Multiple instalment schedules: consolidate the invoice schedule before discounting.']);
        }
        foreach ($openSchedules as $schedule) {
            // Mark Paid collects schedule.amount, not amount minus paid_amount.
            // Schedule only the remaining receivable; actual receipts stay on
            // the invoice/ledger, never masquerading as this credit note. Paid
            // schedules are immutable history; only the one open remainder is
            // reduced by a discount.
            $schedule->update([
                'amount' => $due,
                'paid_amount' => 0,
                'status' => $due <= 0 ? 'paid' : $schedule->status,
                'paid_date' => $due <= 0 ? ($schedule->paid_date ?: now()->toDateString()) : null,
            ]);
        }
    }
}
