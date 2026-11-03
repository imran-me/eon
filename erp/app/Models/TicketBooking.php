<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;

class TicketBooking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'passport_holder_id', 'ticket_id', 'vendor_id', 'portal_id',
        'notification_recipient_id', 'confirmed_purchase_id', 'booking_reference', 'order_group', 'leg_sequence',
        'from_airport_id', 'to_airport_id', 'pnr', 'name_ticket_no',
        'trip_type', 'ticket_class', 'booking_at', 'expires_at', 'departure_at', 'return_at',
        'quoted_amount', 'currency', 'status', 'notes', 'attachment', 'notify_in_app',
        'notify_email', 'notify_sms', 'reminded_24h_at', 'reminded_3h_at', 'reminded_1h_at',
        'expired_notified_at', 'confirmed_at', 'cancelled_at', 'created_by', 'updated_by',
        // Standard ticket detail (2026-10-04); departure_at is above
        'base_fare', 'tax_amount', 'tax_breakdown', 'commission_pct', 'commission_amount',
        'validating_carrier_id', 'flight_no', 'arrival_at', 'rbd', 'baggage', 'return_flight_no',
        'free_baggage_kg', 'extra_baggage_kg', 'extra_baggage_cost',
        'extra_baggage_agent_charge', 'extra_baggage_bearer', 'baggage_conditions',
    ];

    protected function casts(): array
    {
        return [
            'booking_at' => 'datetime', 'expires_at' => 'datetime', 'departure_at' => 'datetime',
            'return_at' => 'datetime', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime',
            'reminded_24h_at' => 'datetime', 'reminded_3h_at' => 'datetime',
            'reminded_1h_at' => 'datetime', 'expired_notified_at' => 'datetime',
            'quoted_amount' => 'decimal:2', 'notify_in_app' => 'boolean',
            'notify_email' => 'boolean', 'notify_sms' => 'boolean',
        ];
    }

    public function passenger()
    {
        return $this->belongsTo(PassportHolder::class, 'passport_holder_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class)->withTrashed();
    }

    public function fromAirport()
    {
        return $this->belongsTo(Airport::class, 'from_airport_id');
    }

    public function toAirport()
    {
        return $this->belongsTo(Airport::class, 'to_airport_id');
    }

    /** Statuses a ticket can be entered with before it is purchased. */
    public const ENTRY_STATUSES = ['draft', 'pending', 'booked', 'cancelled'];

    /** Open = not yet purchased and not dropped: what a flight card still counts as pending. */
    public function scopeOpen($q)
    {
        return $q->whereIn('status', ['draft', 'pending', 'booked', 'expired'])->whereNull('confirmed_purchase_id');
    }

    /** The hold as the contract-flight passenger card and tracker show it. */
    public function flightHoldDetails(?string $role = null): array
    {
        $this->loadMissing(['ticket', 'vendor', 'portal']);
        $legs = $this->ticket?->legCount() ?? 1;

        return [
            'id' => $this->id,
            'reference' => $this->booking_reference,
            'status' => $this->status,
            'pnr' => $this->pnr,
            'ticket_id' => $this->ticket_id,
            'route' => $this->ticket?->title,
            'leg_sequence' => $this->leg_sequence,
            'leg_label' => $this->leg_sequence ? 'Ticket '.$this->leg_sequence.' of '.$legs : null,
            'sector' => $this->sector() ?: null,
            'amount' => (float) $this->quoted_amount,
            'currency' => $this->currency,
            'vendor' => $this->vendor?->name ?? $this->portal?->name,
            'expires_at' => $this->expires_at?->format('d M Y, h:i A'),
            'url' => ($role && Route::has('role.ticket-bookings.show'))
                ? route('role.ticket-bookings.show', ['role' => $role, 'ticket_booking' => $this->id]) : null,
        ];
    }

    /** "DAC → IST" for a leg booking, '' for a whole-route one. */
    public function sector(): string
    {
        return ($this->from_airport_id || $this->to_airport_id)
            ? ($this->fromAirport?->code ?? '?').' → '.($this->toAirport?->code ?? '?') : '';
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function portal()
    {
        return $this->belongsTo(Portal::class);
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'notification_recipient_id');
    }

    public function confirmedPurchase()
    {
        return $this->belongsTo(TicketPurchase::class, 'confirmed_purchase_id');
    }

    public function notificationLogs()
    {
        return $this->hasMany(TicketBookingNotification::class);
    }
}
