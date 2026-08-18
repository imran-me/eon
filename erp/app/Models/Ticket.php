<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ticket extends Model
{
    use HasFactory;

    protected $table = 'tickets';

    protected $fillable = [
        'title',
        'vendor_id',
        'portal_id',
        'airline_id',
        'from_airport_id',
        'to_airport_id',
        'price',
        'qty',
        'total_sale_qty',
        'total_sale_amount',
        'status'
    ];

    public function vendor(){
        return $this->belongsTo(User::class, 'vendor_id', 'id');
    }
    public function portal(){
        return $this->belongsTo(Portal::class, 'portal_id', 'id');
    }
    public function airline(){
        return $this->belongsTo(Airline::class, 'airline_id', 'id');
    }
    public function from_airport(){
        return $this->belongsTo(Airport::class, 'from_airport_id', 'id');
    }
    public function to_airport(){
        return $this->belongsTo(Airport::class, 'to_airport_id', 'id');
    }
    public function ticket_sales(){
        return $this->hasMany(TicketSale::class);
    }
    public function ticket_purchases(){
        return $this->hasMany(TicketPurchase::class);
    }
}
