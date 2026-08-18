<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'user_id',
        'name',    
        'sku',    
        'category_id',    
        'sub_category_id',    
        'brand_id',    
        'unit_id',    
        'supplier_id',    
        'purchase_price',    
        'selling_price',    
        'stock_qty',    
        'is_active'
    ];

    public function stocks(){
        return $this->hasMany(Stock::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function unit(){
        return $this->belongsTo(Unit::class);
    }
    public function brand(){
        return $this->belongsTo(Brand::class);
    }
    public function supplier(){
        return $this->belongsTo(Supplier::class);
    }
    public function category(){
        return $this->belongsTo(Category::class);
    }
    public function sub_category(){
        return $this->belongsTo(SubCategory::class);
    }
}
