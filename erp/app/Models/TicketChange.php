<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * One re-issue / reschedule / refund / void done on a ticket from Ticket
 * Orders (2026-10-06). See the migration for what each figure means.
 */
class TicketChange extends Model
{
    /** Party-ledger rows and journal sources this feature posts (invoice_id / source_id = change id). */
    public const FEE_TYPE = 'ticket_change_fee';            // seller: re-issue fee we owe — vendor row Dr / portal wallet Cr

    public const CHARGE_TYPE = 'ticket_change_charge';      // customer: one line, penalty + our fee

    public const SALE_REVERSAL_TYPE = 'ticket_sale_reversal'; // customer: agent price taken back (a credit, not a receipt)

    public const FARE_TYPE = 'ticket_change_fare';           // seller: the fare-difference part, its own row (2026-10-06); journal source FEE_TYPE

    public const PENALTY_SOURCE = 'ticket_change_penalty';   // journal: airline-kept penalty moved 5110 → 5171 (Company Ledger)

    public const KINDS = [
        'reissue' => 'Re-issue',
        'reschedule' => 'Reschedule',
        'refund' => 'Refund',
        'void' => 'Void',
        'refund_correction' => 'Refund correction',
        // After a refund / void the customer buys a new ticket (owner, 2026-10-07):
        // recorded on the OLD ticket; the new one is issued by Ticket Entry's own store().
        'newticket' => 'New ticket',
        // A family passenger's unused return (owner, 2026-10-14, App\Services\ReturnCancel):
        // a round trip's return half, or a separate return ticket.
        'return_cancel' => 'Cancel return',
    ];

    public const BEARERS = [
        'customer' => 'Customer / agent pays',
        'company' => 'We bear it',
    ];

    protected $fillable = [
        'company_id', 'ticket_purchase_id', 'kind', 'source', 'contract_flight_passenger_id',
        'contract_flight_passenger_change_id', 'old_travel_at', 'new_travel_at', 'penalty',
        'fare_difference', 'service_fee', 'refund_amount', 'bearer', 'client_id', 'cost_added',
        'charged', 'sale_reversed', 'token', 'note', 'created_by',
    ];

    protected $casts = [
        'old_travel_at' => 'datetime',
        'new_travel_at' => 'datetime',
    ];

    public function ticketPurchase(): BelongsTo
    {
        return $this->belongsTo(TicketPurchase::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Table there AND routes registered — a lagging server shows no button rather than an error. */
    public static function available(): bool
    {
        try {
            return Schema::hasTable('ticket_changes')
                && Route::has('role.ticket-changes.store');
        } catch (\Throwable) {
            return false;
        }
    }
}
