<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadVisa extends Model
{
    use HasFactory;

    protected $table = 'lead_visas';

    protected $fillable = [
        'lead_id',
        'country_id',
        'visa_type',
        'travel_date',
        'document_status',
        'payment_status',
        'remarks',
    ];

    protected $casts = [
        'travel_date' => 'date',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function documents()
    {
        return $this->hasMany(LeadVisaDocument::class, 'lead_id', 'lead_id')->orderBy('sort_order');
    }

    public function missingMandatoryDocuments()
    {
        return $this->hasMany(LeadVisaDocument::class, 'lead_id', 'lead_id')
            ->where('is_mandatory', true)
            ->where('is_collected', false);
    }

    public function getVisaTypeLabelAttribute(): string
    {
        return match($this->visa_type) {
            'tourist'  => 'Tourist',
            'business' => 'Business',
            'student'  => 'Student',
            'work'     => 'Work',
            'medical'  => 'Medical',
            default    => 'Other',
        };
    }

    public function getDocumentStatusColorAttribute(): string
    {
        return match($this->document_status) {
            'pending'   => 'yellow',
            'collected' => 'blue',
            'complete'  => 'indigo',
            'submitted' => 'green',
            default     => 'gray',
        };
    }
}
