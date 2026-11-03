<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketBookingNotification extends Model
{
    protected $fillable = ['ticket_booking_id', 'recipient_user_id', 'event', 'channel', 'status', 'attempts', 'error_message', 'sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
    public function booking() { return $this->belongsTo(TicketBooking::class, 'ticket_booking_id'); }
}
