<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorCategory extends Model
{
    protected $fillable = ['vendor_type_id', 'slug', 'name', 'sort_order', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function type()
    {
        return $this->belongsTo(VendorType::class, 'vendor_type_id');
    }
}
