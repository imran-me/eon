<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    use HasFactory;

    protected $table = 'return_items';

    protected $fillable = [
        'return_id',    
        'product_id',    
        'quantity',    
        'unit_price',    
        'subtotal'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function return_ref()
    {
        return $this->belongsTo(ReturnRef::class, 'return_id', 'id');
    }
}
