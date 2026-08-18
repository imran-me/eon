<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nid',
        'appointment_letter',
        'passport_size_image',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}