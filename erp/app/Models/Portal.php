<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Portal extends Model
{
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'portals';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'name',
        'api_key',
        'api_secret',
        'base_url',
        'type',
        'vendor_id',
        'account_id',
        'status',
        'balance',
        'next_payment_date',
        'next_payment_amount',
        'created_by'
    ];

    /**
     * Get the vendor associated with the portal.
     */
    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function balanceLogs()
    {
        return $this->hasMany(PortalBalance::class, 'portal_id');
    }

    /**
     * Get the created by user.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalAddedAttribute(): float
    {
        return (float) $this->balanceLogs()->where('type', 'opening_balance')->sum('debit');
    }

    public function getTotalUsedAttribute(): float
    {
        $gross = (float) $this->balanceLogs()->whereIn('type', ['ticket_purchase', 'opening_usage'])->sum('credit');
        $repaid = (float) $this->balanceLogs()->where('type', 'add_balance')->sum('debit');
        return max(0.0, $gross - $repaid);
    }

}
