<?php

use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartyInvoicePostingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DB::beginTransaction();

try {
    $invoice = app(PartyInvoicePostingService::class)->post(
        Company::findOrFail(2),
        User::findOrFail(72),
        [
            'invoice_date' => '2026-10-26',
            'invoice_time' => '14:37',
            'due_date' => null,
            'notes' => 'Posting-time integration check',
            'items' => [[
                'service_type' => 'custom',
                'description' => 'Posting-time integration check',
                'passenger_name' => null,
                'quantity' => 1,
                'unit_price' => 1,
                'revenue_account_code' => config('accounts.service_charge_income'),
            ]],
        ],
        User::findOrFail(1)
    );

    $journal = JournalEntry::findOrFail($invoice->journal_entry_id);
    $ledger = Transaction::where('type', 'party_invoice')->where('invoice_id', $invoice->id)->firstOrFail();

    assert(substr((string) $invoice->invoice_time, 0, 5) === '14:37', 'Invoice time was not saved.');
    assert(substr((string) $journal->entry_time, 0, 5) === '14:37', 'Journal time does not match the invoice.');
    assert(substr((string) $ledger->transaction_time, 0, 5) === '14:37', 'Party-ledger time does not match the invoice.');

    echo "Custom invoice posting-time checks passed.\n";
} finally {
    DB::rollBack();
}
