<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisaProcessDocument extends Model
{
    protected $fillable = [
        'visa_process_id',
        'document_name',
        'is_mandatory',
        'status',
        'received_at',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'is_mandatory' => 'boolean',
    ];

    public static array $standardDocs = [
        'Passport',
        'Photo',
        'Bank Statement',
        'NID / Birth Cert',
        'Ticket + Hotel',
        'Trade License',
        'Other',
    ];

    public function visaProcess()
    {
        return $this->belongsTo(VisaProcess::class);
    }
}
