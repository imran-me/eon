<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorProfile extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = ['user_id', 'vendor_type_id'];

    public function type()
    {
        return $this->belongsTo(VendorType::class, 'vendor_type_id');
    }
}
