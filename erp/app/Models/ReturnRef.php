<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnRef extends Model
{
    use HasFactory;

    protected $table = 'return_refs';

    protected $fillable = [                         
        'return_no',
        'branch_id',
        'reference_id',
        'return_date',
        'total_amount',
        'return_type',
        'created_by',
        'note'
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }
    public function reference()
    {
        return $this->belongsTo(Sale::class, 'reference_id', 'id');
    }
    public function items()
    {
        return $this->hasMany(ReturnItem::class, 'return_id', 'id');
    }
}
