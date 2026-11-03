<?php

use App\Http\Controllers\Dashboard\TicketPurchaseController;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\TicketPurchase;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$purchase = TicketPurchase::where('status', 'cancelled')
    ->whereNotNull('vendor_id')
    ->whereHas('transactions', fn ($query) => $query->where('type', 'ticket_purchase_refund'))
    ->firstOrFail();
$oldVendorId = (int) $purchase->vendor_id;
$targetVendor = User::role('vendor')->whereKeyNot($purchase->vendor_id)->firstOrFail();
$actor = User::where('is_super_admin', 1)->firstOrFail();
$controller = app(TicketPurchaseController::class);
$correctedAmount = (float) $purchase->amount + 123;
$recordedRefund = (float) $purchase->refund_amount;
$checks = 0;
$assert = function (bool $condition, string $message) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};

auth()->setUser($actor);
Gate::before(fn (User $user) => (int) $user->id === (int) $actor->id ? true : null);
DB::beginTransaction();

try {
    $request = Request::create('/', 'POST', [
        'passport_holder_id' => $purchase->passport_holder_id,
        'ticket_id' => $purchase->ticket_id,
        'vendor_id' => $targetVendor->id,
        'portal_id' => null,
        'bank_id' => $purchase->bank_id,
        'ticket_type' => $purchase->ticket_type,
        'trip_type' => $purchase->trip_type,
        'ticket_class' => $purchase->ticket_class,
        'purchase_date' => Carbon::parse($purchase->purchase_date)->format('Y-m-d'),
        'ticket_no' => $purchase->ticket_no,
        'name_ticket_no' => $purchase->name_ticket_no,
        'status' => 'cancelled',
        'amount' => $correctedAmount,
        'currency' => $purchase->currency,
    ]);
    $request->setUserResolver(fn () => $actor);
    $app->instance('request', $request);

    $result = $controller->update($request, 'super-admin', $purchase->id)->getData(true);
    $assert(($result['success'] ?? false) === true, $result['message'] ?? 'Cancelled ticket update failed.');

    $purchase->refresh();
    $assert((int) $purchase->vendor_id === (int) $targetVendor->id, 'Ticket vendor was not changed.');
    $assert(abs((float) $purchase->amount - $correctedAmount) < 0.005, 'Cancelled ticket amount was not corrected.');
    $assert(abs((float) $purchase->refund_amount - $recordedRefund) < 0.005, 'Recorded airline refund was changed.');

    $ledgerRows = Transaction::where('invoice_id', $purchase->id)
        ->whereIn('type', ['ticket_purchase', 'ticket_purchase_refund'])
        ->get();
    $assert($ledgerRows->isNotEmpty(), 'No purchase/refund ledger rows were found.');
    $assert($ledgerRows->every(fn ($row) => (int) $row->user_id === (int) $targetVendor->id), 'Not every purchase/refund row moved to the new vendor.');
    $assert(abs((float) $ledgerRows->firstWhere('type', 'ticket_purchase')->debit - $correctedAmount) < 0.005, 'Purchase ledger amount was not corrected.');

    $journalIds = JournalEntry::where('source_id', $purchase->id)
        ->whereIn('source', ['ticket_purchase', 'ticket_purchase_cancel'])
        ->pluck('id');
    $assert(JournalEntry::whereIn('id', $journalIds)->where('beneficiary_party_id', $oldVendorId)->doesntExist(), 'An old journal beneficiary remains.');
    $assert(JournalItem::whereIn('journal_entry_id', $journalIds)->where('party_type', 'vendor')->where('party_id', '!=', $targetVendor->id)->doesntExist(), 'An old journal party tag remains.');
    $purchaseJournalId = JournalEntry::where('source', 'ticket_purchase')->where('source_id', $purchase->id)->oldest('id')->value('id');
    $costAccountId = Account::where('code', config('accounts.ticket_purchase_cost'))->value('id');
    $assert(abs((float) JournalItem::where('journal_entry_id', $purchaseJournalId)->where('account_id', $costAccountId)->value('debit') - $correctedAmount) < 0.005, 'Purchase journal amount was not corrected.');

    echo "Cancelled ticket vendor correction checks passed: {$checks}\n";
} finally {
    DB::rollBack();
}
