<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtherVisaService extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'service_code',
        'passport_holder_id',
        'visa_process_id',
        'other_service_type_id',
        'country_id',
        'assigned_officer_id',
        'cost_price',
        'sale_price',
        'deadline',
        'status',
        'notes',
        'is_billable',
        'created_by',
    ];

    protected $casts = [
        'deadline'   => 'date',
        'cost_price' => 'float',
        'sale_price' => 'float',
        'is_billable' => 'boolean',
    ];

    public function passportHolder()
    {
        return $this->belongsTo(PassportHolder::class);
    }

    public function visaProcess()
    {
        return $this->belongsTo(VisaProcess::class);
    }

    public function serviceType()
    {
        return $this->belongsTo(OtherServiceType::class, 'other_service_type_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function assignedOfficer()
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function saleItem()
    {
        return $this->hasOne(VisaSaleItem::class, 'other_visa_service_id');
    }

    public static function nextServiceCode(): string
    {
        $last = static::withTrashed()->latest('id')->first();
        $next = $last ? ($last->id + 1) : 1;
        return 'OS-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
