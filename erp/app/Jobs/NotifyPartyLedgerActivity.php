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
use Illuminate\Support\Facades\Cache;

/**
 * Tells a party (agent / customer / vendor) what was just posted on their
 * ledger — email with the statement attached, plus SMS (PartyNotifier).
 *
 * Queued by TransactionObserver for every new ledger row. The job does not
 * announce "the row it was queued for": it sweeps every entry on the party's
 * ledger newer than the last one already announced (the watermark in
 * party_ledger_notifications) and sends them together. So the charge and the
 * payment of one sale, posted seconds apart, arrive as one message; and the
 * second job that sale queued finds nothing left and exits.
 *
 * A party with no send history is only told about entries from the row that
 * queued the job onward — never their whole back-catalogue on day one.
 */
class NotifyPartyLedgerActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;   // a release() while another sweep holds the lock counts as an attempt
    public int $timeout = 120;

    public function __construct(
        public int $partyId,
        public int $transactionId,
    ) {}

    public function handle(PartyNotifier $notifier): void
    {
        if (!config('services.party_ledger.notify', true)) {
            return;
        }

        // One sweep per party at a time, so two jobs queued for the same sale
        // cannot both read "nothing announced yet" and send twice.
        $lock = Cache::lock('party-ledger-notify:' . $this->partyId, 90);
        if (!$lock->get()) {
            $this->release(15);
            return;
        }

        try {
            $this->sweep($notifier);
        } finally {
            $lock->release();
        }
    }

    private function sweep(PartyNotifier $notifier): void
    {
        $party = User::find($this->partyId);
        if (!$party) {
            return;
        }

        $type = $notifier->typeOf($party);
        if (!$type) {
            return;                      // no longer a party — nothing to tell
        }

        $watermark = PartyLedgerNotification::where('user_id', $party->id)->max('to_transaction_id');
        $floor     = $watermark !== null ? (int) $watermark : $this->transactionId - 1;

        $pending = Transaction::where('user_id', $party->id)
            ->where('id', '>', $floor)
            ->where('type', '!=', 'bank_transfer')
            ->orderBy('id')
            ->get();

        // Zero-amount rows (voided contract-flight charges) advance the
        // watermark but are not announced — nothing was charged or paid.
        $announce = $pending->filter(fn (Transaction $t) => (float) $t->debit != 0.0 || (float) $t->credit != 0.0)->values();
        if ($announce->isEmpty()) {
            return;
        }

        $notifier->sendActivity($party, $type, $announce, $pending->first()->id, $pending->last()->id);
    }
}
