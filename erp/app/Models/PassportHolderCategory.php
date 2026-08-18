<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassportHolderCategory extends Model
{
    protected $fillable = ['name', 'description'];

    /**
     * Get the passport holders associated with the category.
     */
    public function passportHolders()
    {
        return $this->hasMany(PassportHolder::class, 'category_id');
    }
}
