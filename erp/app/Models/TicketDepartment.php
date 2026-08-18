<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketDepartment extends Model
{
    use HasFactory;

    protected $table = 'ticket_departments';

    protected $fillable = [
        'name',
        'is_active',
    ];
}
