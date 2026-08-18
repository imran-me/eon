<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassportHolder extends Model
{

    protected $fillable = [
        'name',
        'passport_no',
        'nationality',
        'phone',
        'date_of_birth',
        'issue_date',
        'expiry_date',
        'category_id',
        'type',
        'user_id',
        'status',
        'created_by',
    ];

    public function category()
    {
        return $this->belongsTo(PassportHolderCategory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
