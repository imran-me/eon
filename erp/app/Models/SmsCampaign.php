<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsCampaign extends Model
{
    protected $fillable = [
        'title',
        'message',
        'schedule_at',
        'status', // Assuming status is an integer or string, adjust as necessary
    ];

    /**
     * Get the contacts associated with the SMS campaign.
     */
    public function contacts()
    {
        return $this->hasMany(SmsCampaignContactLog::class);
    }
}
