<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * One correction made from a ticket's Trail (2026-10-26). See the migration
 * for what each column holds.
 *
 * @property int $id
 * @property int $ticket_purchase_id
 * @property string|null $reason
 * @property string|null $summary
 * @property array|null $before
 * @property array|null $after
 * @property array|null $replaced
 * @property array|null $added
 * @property int|null $created_by
 */
class TicketTrailLog extends Model
{
    protected $fillable = [
        'company_id', 'ticket_purchase_id', 'action', 'reason', 'summary',
        'before', 'after', 'replaced', 'added', 'created_by',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'replaced' => 'array',
        'added' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Table there AND routes registered — a lagging server shows no Trail rather than an error. */
    public static function available(): bool
    {
        try {
            return Schema::hasTable('ticket_trail_logs')
                && Route::has('role.ticket-trail.show');
        } catch (\Throwable) {
            return false;
        }
    }
}
