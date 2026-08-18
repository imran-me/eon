<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendenceSetting extends Model
{
    use HasFactory;

    protected $table = 'attendence_settings';

    protected $fillable = [
        'time_before_checkin',
        'time_after_checkin',
        'time_before_checkout',
        'time_after_checkout'
    ];
}
