<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $table = 'leads';

    protected $fillable = [
        'lead_type',
        'service_category',
        'lead_source_id',
        'name',
        'email',
        'phone',
        'status',
        'assigned_to',
        'customer_id',
        'notes'
    ];

    public function leadSource()
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    
    public function assignedEmployee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusHistories()
    {
        return $this->hasMany(LeadStatusHistory::class, 'lead_id');
    }

    public function airTicket()
    {
        return $this->hasOne(\App\Models\LeadAirTicket::class, 'lead_id');
    }

    public function visa()
    {
        return $this->hasOne(\App\Models\LeadVisa::class, 'lead_id');
    }

    public function visaDocuments()
    {
        return $this->hasMany(\App\Models\LeadVisaDocument::class, 'lead_id')->orderBy('sort_order');
    }

    public function followups()
    {
        return $this->hasMany(LeadFollowup::class, 'lead_id');
    }

    public function reminders()
    {
        return $this->hasMany(LeadReminder::class, 'lead_id');
    }

    public function interior()
    {
        return $this->hasOne(\App\Models\LeadInterior::class, 'lead_id');
    }

    public function project()
    {
        return $this->hasOne(Project::class, 'lead_id');
    }

    public function isInPipeline(): bool
    {
        return in_array($this->status, [
            'new', 'contacted', 'qualified', 'proposal_sent', 'negotiation'
        ]);
    }

    public function isConverted(): bool
    {
        return $this->status === 'won' && $this->project()->exists();
    }

    public function getLeadTypeLabelAttribute(): string
    {
        return match($this->lead_type) {
            'air_ticket' => 'Air Ticket',
            'visa'       => 'Visa',
            'software'   => 'Software',
            'interior'   => 'Interior',
            default      => 'Other',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'new'           => 'blue',
            'contacted'     => 'indigo',
            'qualified'     => 'yellow',
            'proposal_sent' => 'purple',
            'negotiation'   => 'orange',
            'won'           => 'green',
            'lost'          => 'red',
            default         => 'gray',
        };
    }
}
