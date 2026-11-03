<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\TicketBooking;
use App\Models\TicketBookingNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendTicketBookingNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookingId, public string $event) {}

    public function handle(): void
    {
        $booking = TicketBooking::with(['passenger', 'ticket', 'recipient'])->find($this->bookingId);
        if (!$booking || !$booking->recipient) return;

        $labels = [
            'created' => 'created', 'reminder_3h' => 'expires within 3 hours',
            'reminder_1h' => 'expires within 1 hour',
            'expired' => 'expired', 'confirmed' => 'confirmed', 'cancelled' => 'cancelled',
        ];
        $label = $labels[$this->event] ?? $this->event;
        $role = str($booking->recipient->getRoleNames()->first() ?: 'super-admin')->slug();
        $url = route('role.ticket-bookings.show', ['role' => $role, 'ticket_booking' => $booking->id]);
        $message = "Booking {$booking->booking_reference} ({$booking->pnr}) for {$booking->passenger?->name} {$label}. Expiry: " . ($booking->expires_at?->format('d M Y h:i A') ?? 'not set') . ".";

        $channels = [
            'in_app' => $booking->notify_in_app,
            'email' => $booking->notify_email && filled($booking->recipient->email),
            'sms' => $booking->notify_sms && filled($booking->recipient->phone),
        ];

        foreach ($channels as $channel => $enabled) {
            $log = TicketBookingNotification::firstOrCreate(
                ['ticket_booking_id' => $booking->id, 'event' => $this->event, 'channel' => $channel],
                ['recipient_user_id' => $booking->recipient->id, 'status' => $enabled ? 'pending' : 'skipped']
            );
            if (!$enabled || $log->status === 'sent') continue;
            try {
                $log->increment('attempts');
                if ($channel === 'in_app') {
                    Notification::create(['user_id' => $booking->recipient->id, 'type' => 'ticket_booking', 'data' => [
                        'message' => $message, 'url' => $url, 'booking_id' => $booking->id,
                    ]]);
                } elseif ($channel === 'email') {
                    $subject = "Ticket booking: {$booking->booking_reference} — {$label}";
                    $html = view('emails.ticket-booking-alert', [
                        'booking' => $booking,
                        'eventLabel' => $label,
                        'bookingUrl' => $url,
                    ])->render();
                    $response = sendBrevoMail(
                        $booking->recipient->email,
                        $booking->recipient->name,
                        $subject,
                        $html
                    );
                    if (!$response->successful()) {
                        throw new \RuntimeException(
                            'Brevo email API failed (HTTP '.$response->status().'): '.str($response->body())->limit(750)
                        );
                    }
                } else {
                    $result = sendSms($booking->recipient->phone, $message);
                    if ($result === false) throw new \RuntimeException('SMS gateway returned no response.');
                }
                $log->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
            } catch (\Throwable $e) {
                $log->update(['status' => 'failed', 'error_message' => str($e->getMessage())->limit(1000)]);
                Log::warning('Ticket booking notification failed', ['booking_id' => $booking->id, 'channel' => $channel, 'error' => $e->getMessage()]);
            }
        }
    }
}
