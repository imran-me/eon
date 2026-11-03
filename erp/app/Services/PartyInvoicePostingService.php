<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PartyInvoice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartyInvoicePostingService
{
    public function post(Company $company, User $party, array $document, User $actor): PartyInvoice
    {
        return DB::transaction(function () use ($company, $party, $document, $actor) {
            $items = collect($document['items']);
            $subtotal = round((float) $items->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']), 2);
            if ($subtotal <= 0) {
                throw ValidationException::withMessages(['invoice' => 'Invoice total must be greater than zero.']);
            }

            $receivable = Account::where('code', config('accounts.accounts_receivable'))->active()->firstOrFail();
            $revenueAccounts = Account::whereIn('code', $items->pluck('revenue_account_code')->unique())
                ->active()->get()->keyBy('code');
            foreach ($items as $item) {
                $account = $revenueAccounts->get($item['revenue_account_code']);
                if (! $account || $account->type !== 'income' || ($account->company_id && (int) $account->company_id !== (int) $company->id)) {
                    throw ValidationException::withMessages(['invoice' => "Revenue account {$item['revenue_account_code']} is not an active income account for {$company->name}."]);
                }
            }

            $invoice = PartyInvoice::create([
                'company_id' => $company->id,
                'user_id' => $party->id,
                'invoice_number' => 'TMP-'.str()->uuid(),
                'invoice_date' => $document['invoice_date'],
                'invoice_time' => $document['invoice_time'] ?? now()->format('H:i:s'),
                'service_description' => $items->pluck('description')->implode('; '),
                'country_id' => $document['country_id'] ?? null,
                'no_of_candidates' => $document['no_of_candidates'] ?? ($items->pluck('passenger_name')->filter()->unique()->count() ?: null),
                'amount' => $subtotal,
                'subtotal' => $subtotal,
                'paid_amount' => 0,
                'due_amount' => $subtotal,
                'payment_status' => 'due',
                'due_date' => $document['due_date'] ?: null,
                'notes' => $document['notes'] ?: null,
                'created_by' => $actor->id,
            ]);
            $invoiceNumber = 'CINV-'.date('Y', strtotime($document['invoice_date'])).'-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
            $invoice->update(['invoice_number' => $invoiceNumber]);
            $invoice->items()->createMany($items->map(fn ($item) => $item + [
                'amount' => round((float) $item['quantity'] * (float) $item['unit_price'], 2),
            ])->all());

            $journal = JournalEntry::create([
                'company_id' => $company->id,
                'created_by' => $actor->id,
                'date' => $document['invoice_date'],
                'entry_time' => $document['invoice_time'] ?? now()->format('H:i:s'),
                'reference' => $invoiceNumber,
                'source' => 'party_invoice',
                'source_id' => $invoice->id,
                'description' => 'Custom invoice to '.$party->name,
                'beneficiary_party_id' => $party->id,
            ]);
            JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $receivable->id, 'debit' => $subtotal, 'credit' => 0, 'note' => $invoiceNumber, 'party_type' => $this->partyType($party), 'party_id' => $party->id]);
            foreach ($items->groupBy('revenue_account_code') as $code => $group) {
                JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $revenueAccounts[$code]->id, 'debit' => 0, 'credit' => round($group->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']), 2), 'note' => $invoiceNumber]);
            }
            $invoice->update(['journal_entry_id' => $journal->id]);

            $last = Transaction::where('company_id', $company->id)->where('user_id', $party->id)->lockForUpdate()->latest('id')->first();
            $old = (float) ($last?->balance ?? 0);
            Transaction::create([
                'company_id' => $company->id, 'user_id' => $party->id, 'user_type' => 'customer',
                'type' => 'party_invoice', 'payment_date' => $document['invoice_date'], 'transaction_time' => $document['invoice_time'] ?? now()->format('H:i:s'), 'reference_no' => $invoiceNumber,
                'invoice_id' => $invoice->id, 'old_balance' => $old, 'debit' => $subtotal, 'credit' => 0,
                'balance' => $old + $subtotal, 'remarks' => $invoice->service_description,
            ]);

            return $invoice->fresh(['items']);
        });
    }

    private function partyType(User $party): string
    {
        return $party->hasRole('agent') ? 'agent' : 'customer';
    }
}
