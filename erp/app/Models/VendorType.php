<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorType extends Model
{
    protected $fillable = ['slug', 'name', 'sort_order', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function categories()
    {
        return $this->hasMany(VendorCategory::class)->orderBy('sort_order')->orderBy('name');
    }
}
