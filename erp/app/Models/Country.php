<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'countries';

    protected $fillable = [
        'code',
        'name',
        'zone_id',
        'status',
    ];

    public function states()
    {
        return $this->hasMany(State::class);
    }

    public function visaCategories()
    {
        return $this->hasMany(VisaCategory::class);
    }

    public function contractFileCategories()
    {
        return $this->hasMany(ContractFileCategory::class);
    }
}
