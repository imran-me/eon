<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCampaign extends Model
{
    protected $fillable = [
        'subject',
        'body',
        'schedule_at',
    ];

    /**
     * Get the contacts associated with the email campaign.
     */
    public function contacts()
    {
        return $this->hasMany(EmailCampaignContactLog::class);
    }
}
