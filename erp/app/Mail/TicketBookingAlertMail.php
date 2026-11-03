<?php

namespace App\Mail;

use App\Models\TicketBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketBookingAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TicketBooking $booking, public string $eventLabel, public string $bookingUrl) {}

    public function build(): self
    {
        return $this->subject("Ticket booking: {$this->booking->booking_reference} — {$this->eventLabel}")
            ->view('emails.ticket-booking-alert');
    }
}
