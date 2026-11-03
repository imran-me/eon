<?php

namespace App\Console\Commands;

use App\Jobs\SendTicketBookingNotification;
use App\Models\TicketBooking;
use Illuminate\Console\Command;

class ProcessTicketBookingReminders extends Command
{
    protected $signature = 'tickets:process-booking-reminders';
    protected $description = 'Send due ticket-booking reminders and expire elapsed bookings';

    public function handle(): int
    {
        TicketBooking::where('status', 'booked')->whereNotNull('expires_at')->orderBy('id')->chunkById(100, function ($bookings) {
            foreach ($bookings as $booking) {
                if ($booking->expires_at->isPast()) {
                    $booking->update(['status' => 'expired', 'expired_notified_at' => now()]);
                    SendTicketBookingNotification::dispatch($booking->id, 'expired');
                    continue;
                }
                $minutes = now()->diffInMinutes($booking->expires_at, false);
                if ($minutes <= 60 && !$booking->reminded_1h_at) {
                    $booking->update(['reminded_3h_at' => $booking->reminded_3h_at ?: now(), 'reminded_1h_at' => now()]);
                    SendTicketBookingNotification::dispatch($booking->id, 'reminder_1h');
                } elseif ($minutes <= 180 && !$booking->reminded_3h_at) {
                    $booking->update(['reminded_3h_at' => now()]);
                    SendTicketBookingNotification::dispatch($booking->id, 'reminder_3h');
                }
            }
        });
        return self::SUCCESS;
    }
}
