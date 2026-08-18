<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceSetting extends Model
{
    protected $fillable = [
        'device_serial_no',
        'name',
        'device_location',
        'is_active',
    ];
    public function company()
    {
        return $this->belongsTo(Company::class,'device_location','id');
    }
}
