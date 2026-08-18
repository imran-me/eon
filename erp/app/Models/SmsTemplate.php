<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{

    protected $table = 'sms_templates';

    protected $fillable = [
        'name',
        'title',
        'template',
        'variables',
        'created_by',
        'status'
    ];

    // protected $casts = [
    //     'variables' => 'array'
    // ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
