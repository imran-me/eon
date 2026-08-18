<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeLedger extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_ledger';

    protected $fillable = [
        'user_id',
        'type',
        'source_type',
        'source_id',
        'entry_date',
        'reference',
        'old_balance',
        'debit',
        'credit',
        'balance',
        'note',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
