<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\TicketPurchase;
use App\Services\ReturnCancel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Cancel return (owner, 2026-10-14): the airline's window for a family ticket's
 * unused return — "the return can be cancelled until …". Whoever entered the
 * ticket is reminded in the ERP 3 days before, 1 day before, and on the day.
 *
 * Each reminder goes once (return_reminders); a new deadline starts them afresh
 * (ReturnCancel::setWindow). A return already cancelled, or a ticket no longer
 * live, gets nothing. Does nothing at all before the migration.
 */
class ProcessReturnCancelReminders extends Command
{
    protected $signature = 'tickets:return-cancel-reminders';
    protected $description = 'Remind whoever entered a family ticket that its return can still be cancelled';

    public function handle(): int
    {
        if (! ReturnCancel::ready()) {
            return self::SUCCESS;
        }
        $sent = 0;
        TicketPurchase::with(['passportHolder:id,name', 'creator'])
            ->where('status', 'confirm')->whereNull('return_cancelled_at')->whereNotNull('return_cancel_by')
            // a date alone runs to the end of its day, so yesterday's 00:00 may still be open
            ->where('return_cancel_by', '>=', now()->subDay()->startOfDay())
            ->where('return_cancel_by', '<=', now()->addDays(3)->endOfDay())
            ->orderBy('id')->chunkById(100, function ($tickets) use (&$sent) {
                foreach ($tickets as $t) {
                    try {
                        $sent += $this->remind($t) ? 1 : 0;
                    } catch (\Throwable $e) {
                        Log::warning('Return cancel reminder failed', ['ticket_purchase_id' => $t->id, 'error' => $e->getMessage()]);
                    }
                }
            });
        if ($sent) {
            $this->info($sent . ' return-cancel reminder(s) sent.');
        }
        return self::SUCCESS;
    }

    /** One reminder for this ticket if one is due and not yet sent. */
    private function remind(TicketPurchase $t): bool
    {
        $end = ReturnCancel::deadline($t);
        $user = $t->creator;
        if (! $end || $end->isPast() || ! $user) {
            return false;
        }
        $days = (int) now()->startOfDay()->diffInDays($end->copy()->startOfDay(), false);
        $stage = $days <= 0 ? '0d' : ($days === 1 ? '1d' : ($days <= 3 ? '3d' : null));
        $log = (array) ($t->return_reminders ?? []);
        if (! $stage || isset($log[$stage])) {
            return false;
        }

        $dateOnly = $end->format('H:i') === '23:59';
        $when = $dateOnly ? $end->format('d M Y') : $end->format('d M Y, h:i A');
        $left = $days <= 0 ? ($dateOnly ? 'today is the last day' : 'closes today') : $days . ' day' . ($days === 1 ? '' : 's') . ' left';
        $pnr = $t->ticket_no ?: ('#' . $t->id);
        $role = str($user->getRoleNames()->first() ?: 'super-admin')->slug();
        $url = Route::has('role.ticket-purchase.index') ? route('role.ticket-purchase.index', ['role' => $role, 'search' => $t->ticket_no]) : null;

        Notification::create(['user_id' => $user->id, 'type' => 'ticket_booking', 'data' => [
            'message' => 'Return of ' . $pnr . ' (' . ($t->passportHolder?->name ?: 'passenger') . ') can be cancelled until ' . $when . ' — ' . $left
                . '. Ticket Orders → Change → Cancel return.',
            'url' => $url,
            'ticket_purchase_id' => $t->id,
            'kind' => 'return_window',
        ]]);
        $log[$stage] = now()->toDateTimeString();
        $t->forceFill(['return_reminders' => $log])->saveQuietly();
        return true;
    }
}
