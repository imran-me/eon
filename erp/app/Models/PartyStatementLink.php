<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A short link to a party's statement PDF. See the migration. */
class PartyStatementLink extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date_from'      => 'date:Y-m-d',
        'date_to'        => 'date:Y-m-d',
        'last_opened_at' => 'datetime',
        'expires_at'     => 'datetime',
    ];

    /**
     * The active link for this party and range, or a new 30-day link when the
     * old one has expired. A 12-character alphanumeric token has about 71
     * bits of entropy; combined with expiry and throttling it remains
     * impractical to guess while keeping the customer-facing URL short.
     */
    public static function issue(int $partyId, ?string $from, ?string $to, ?int $by): self
    {
        $existing = static::where('user_id', $partyId)
            ->where('date_from', $from)
            ->where('date_to', $to)
            ->latest('id')
            ->get()
            ->first(fn (self $link) => strlen((string) $link->token) === 12 && $link->expires_at?->isFuture());
        if ($existing) {
            return $existing;
        }

        do {
            $token = Str::random(12);
        } while (static::where('token', $token)->exists());

        return static::create([
            'token'      => $token,
            'user_id'    => $partyId,
            'date_from'  => $from,
            'date_to'    => $to,
            'created_by' => $by,
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function party()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
