<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtherServiceType extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'color',
        'bg_color',
        'default_fee',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'default_fee' => 'float',
    ];

    public function services()
    {
        return $this->hasMany(OtherVisaService::class, 'other_service_type_id');
    }
}
