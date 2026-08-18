<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightCategory extends Model
{
    protected $table = 'flight_categories';

    protected $fillable = [
        'name',
        'flight_category_type_id',
        'code',
        'icon',
        'typical_routes',
        'default_seats',
        'base_fare',
        'status',
    ];

    protected $casts = [
        'default_seats' => 'integer',
        'base_fare'     => 'float',
    ];

    public function airlines()
    {
        return $this->belongsToMany(Airline::class, 'flight_category_airline');
    }

    public function categoryType()
    {
        return $this->belongsTo(FlightCategoryType::class, 'flight_category_type_id');
    }
}
