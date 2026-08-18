<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignSMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $contact, $campaign;

    public function __construct(Contact $contact, Campaign $campaign)
    {
        $this->contact = $contact;
        $this->campaign = $campaign;
    }

    public function handle()
    {
        $response = sendSMS($this->contact->phone, $this->campaign->message);
        DB::table('campaign_contact')->insert([
            'campaign_id' => $this->campaign->id,
            'contact_id' => $this->contact->id,
            'status' => 'sent',
            'response' => $response,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
