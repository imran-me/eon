<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FlightCategoryType extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'base_fare', 'status'];

    protected $casts = ['base_fare' => 'float'];

    public function flightCategories()
    {
        return $this->hasMany(FlightCategory::class);
    }
}
