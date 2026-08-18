<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisaCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'country_id',
        'visa_type',
        'base_fee',
        'embassy_fee',
        'vfs_fee',
        'our_fee',
        'costing_price',
        'sale_price',
        'avg_processing_days',
        'description',
        'is_active',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function visaProcesses()
    {
        return $this->hasMany(VisaProcess::class);
    }

    public function getTotalFeeAttribute(): float
    {
        return (float) $this->embassy_fee + (float) $this->vfs_fee + (float) $this->our_fee;
    }
}
