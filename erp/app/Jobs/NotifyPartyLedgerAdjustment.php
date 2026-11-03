<?php

namespace App\Jobs;

use App\Models\PartyLedgerNotification;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartyNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Announces a debit/credit delta when a payment flow updates an existing
 * party-ledger row (Payment Schedule reconciliation is the main example).
 */
class NotifyPartyLedgerAdjustment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $partyId,
        public int $transactionId,
        public float $debitDelta,
        public float $creditDelta,
    ) {}

    public function handle(PartyNotifier $notifier): void
    {
        if (!config('services.party_ledger.notify', true)) {
            return;
        }

        $party = User::find($this->partyId);
        $row = Transaction::find($this->transactionId);
        $type = $party ? $notifier->typeOf($party) : null;

        if (!$party || !$row || !$type) {
            return;
        }

        // When this row has not yet been announced, its delayed created-job
        // will read the final value. Sending the delta too would duplicate it.
        $watermark = PartyLedgerNotification::where('user_id', $party->id)
            ->max('to_transaction_id');
        if ($watermark === null || (int) $watermark < $row->id) {
            return;
        }

        $debit = $this->debitDelta;
        $credit = $this->creditDelta;
        if ($debit < 0) {
            $credit += abs($debit);
            $debit = 0;
        }
        if ($credit < 0) {
            $debit += abs($credit);
            $credit = 0;
        }
        if (abs($debit) < 0.005 && abs($credit) < 0.005) {
            return;
        }

        $entry = new Transaction($row->only([
            'user_id', 'user_type', 'type', 'account_id', 'payment_date',
            'reference_no', 'payment_method', 'invoice_id', 'remarks',
        ]));
        $entry->id = $row->id;
        $entry->debit = max(0, $debit);
        $entry->credit = max(0, $credit);
        $entry->balance = $row->balance;

        $notifier->sendActivity(
            $party,
            $type,
            new Collection([$entry]),
            $row->id,
            $row->id,
        );
    }
}
