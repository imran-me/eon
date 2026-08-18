<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FlightPricePreset extends Model
{
    use SoftDeletes;
    protected $fillable = ['airline_id', 'flight_category_id', 'flight_category_type_id', 'ticket_class', 'handling_type', 'ticket_cost', 'manpower_cost', 'boarding_cost', 'immigration_cost', 'sale_price', 'status'];
    protected $casts = ['ticket_cost' => 'float', 'manpower_cost' => 'float', 'boarding_cost' => 'float', 'immigration_cost' => 'float', 'sale_price' => 'float'];
    public function airline()
    {
        return $this->belongsTo(Airline::class);
    }
    public function flightCategory()
    {
        return $this->belongsTo(FlightCategory::class);
    }
    public function categoryType()
    {
        return $this->belongsTo(FlightCategoryType::class, 'flight_category_type_id');
    }
}
