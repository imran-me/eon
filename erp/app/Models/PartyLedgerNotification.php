<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Send log + watermark for party ledger email/SMS notices. See the migration. */
class PartyLedgerNotification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'closing_balance' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
