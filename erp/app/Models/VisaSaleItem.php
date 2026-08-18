<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisaSaleItem extends Model
{
    protected $table = 'visa_sale_items';

    protected $fillable = [
        'visa_sale_id',
        'visa_process_id',
        'other_visa_service_id',
        'sale_price',
    ];

    protected $casts = [
        'sale_price' => 'float',
    ];

    public function sale()
    {
        return $this->belongsTo(VisaSale::class, 'visa_sale_id');
    }

    public function visaProcess()
    {
        return $this->belongsTo(VisaProcess::class, 'visa_process_id');
    }

    public function otherVisaService()
    {
        return $this->belongsTo(OtherVisaService::class, 'other_visa_service_id');
    }

    public function getLineTypeAttribute(): string
    {
        return $this->other_visa_service_id ? 'other_service' : 'visa_process';
    }
}
