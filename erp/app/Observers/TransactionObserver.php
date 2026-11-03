<?php

namespace App\Observers;

use App\Http\Controllers\Dashboard\PartyStatementController;
use App\Jobs\NotifyPartyLedgerActivity;
use App\Jobs\NotifyPartyLedgerAdjustment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Every new party-ledger row queues a notice to the party it belongs to —
 * wherever it was posted from (ticket/visa sale, contract flight, purchase,
 * the Party Statement page's own payment/invoice/opening balance).
 *
 * Hooking the model rather than the ~20 controllers that write `transactions`
 * is the only way to be sure nothing is missed. The cost of that reach is
 * that this runs inside every one of those saves, so it must never throw and
 * never do real work: it decides whether the row belongs to a party and, if
 * so, drops one queued job. The queue worker does the rest.
 */
class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        try {
            if (request()->boolean('suppress_party_notifications')) {
                return;
            }
            if (! config('services.party_ledger.notify', true)) {
                return;
            }
            if (! $transaction->user_id) {
                return;
            }
            // Bank-to-bank transfers are booked against the STAFF user who made
            // them (BankTransferService) — never a party, never a notice.
            if ($transaction->type === 'bank_transfer') {
                return;
            }
            // Zero rows are real ledger entries (a contract-flight charge voided
            // on cancellation posts 0 rather than skipping) but there is
            // nothing to tell the party about.
            if ((float) $transaction->debit == 0.0 && (float) $transaction->credit == 0.0) {
                return;
            }

            $party = User::find($transaction->user_id);
            if (! $party || ! $this->isParty($party)) {
                return;
            }

            // Delayed so the rows of one sale (charge, then the amount paid)
            // land before the job runs and go out as a single message; the
            // job itself sweeps everything not yet announced. afterCommit so a
            // rolled-back sale never announces a row that no longer exists.
            NotifyPartyLedgerActivity::dispatch($party->id, $transaction->id)
                ->delay(now()->addSeconds(30))
                ->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('Party ledger notice could not be queued.', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Some payment flows (notably Payment Schedule settlements) reconcile an
     * existing invoice payment row instead of inserting another row. Notify
     * the party about the monetary delta once the original row has already
     * been announced. If it has not, the delayed created-job will announce
     * the row at its final value and this adjustment job safely does nothing.
     */
    public function updated(Transaction $transaction): void
    {
        try {
            if (request()->boolean('suppress_party_notifications')) {
                return;
            }
            if (! config('services.party_ledger.notify', true) || ! $transaction->user_id) {
                return;
            }

            $oldDebit = (float) $transaction->getOriginal('debit');
            $oldCredit = (float) $transaction->getOriginal('credit');
            $newDebit = (float) $transaction->debit;
            $newCredit = (float) $transaction->credit;

            if ($oldDebit === $newDebit && $oldCredit === $newCredit) {
                return;
            }

            $party = User::find($transaction->user_id);
            if (! $party || ! $this->isParty($party)) {
                return;
            }

            NotifyPartyLedgerAdjustment::dispatch(
                $party->id,
                $transaction->id,
                $newDebit - $oldDebit,
                $newCredit - $oldCredit,
            )->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('Party ledger adjustment notice could not be queued.', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isParty(User $user): bool
    {
        foreach (PartyStatementController::TYPES as $meta) {
            if ($user->hasRole($meta['role'])) {
                return true;
            }
        }

        return false;
    }
}
