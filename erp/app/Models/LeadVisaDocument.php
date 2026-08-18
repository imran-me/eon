<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadVisaDocument extends Model
{
    protected $table = 'lead_visa_documents';

    protected $fillable = [
        'lead_id',
        'document_name',
        'is_mandatory',
        'is_collected',
        'collected_at',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'is_mandatory'  => 'boolean',
        'is_collected'  => 'boolean',
        'collected_at'  => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
