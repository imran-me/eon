<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendEmailCampaign implements ShouldQueue
{
     public $subscriber, $campaign;

    public function __construct($subscriber, $campaign)
    {
        $this->subscriber = $subscriber;
        $this->campaign = $campaign;
    }

    public function handle()
    {
        try {
            Mail::to($this->subscriber->email)->send(new \App\Mail\CampaignMail($this->campaign));
            DB::table('campaign_subscriber')->insert([
                'email_campaign_id' => $this->campaign->id,
                'subscriber_id' => $this->subscriber->id,
                'status' => 'sent',
                'response' => 'Sent successfully',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            DB::table('campaign_subscriber')->insert([
                'email_campaign_id' => $this->campaign->id,
                'subscriber_id' => $this->subscriber->id,
                'status' => 'failed',
                'response' => $e->getMessage(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
