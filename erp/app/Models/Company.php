<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Bank;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'companies';

    protected $fillable = [
        'name',
        'short_name',
        'address',
        'email',
        'phone',
        'website',
        'logo',
        'icon',
        'status',
        'order',
        'attendance_device_serial_no',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'company_id');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function banks()
    {
        return $this->hasMany(Bank::class);
    }
}
