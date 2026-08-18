<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Customer extends Model
{
    use HasFactory;
    Use Notifiable;

    protected $table = 'customers';

    protected $fillable = [            
        'user_id',
        'company_id',
        'name',
        'email',
        'phone',
        'address',
        'is_active'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
