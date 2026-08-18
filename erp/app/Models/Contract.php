<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'deal_id',
        'customer_id',
        'project_id',
        'contract_type_id',
        'contract_no',
        'contract_date',
        'valid_until',
        'contract_value',
        'status',
        'description',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'valid_until' => 'date',
        'contract_value' => 'decimal:2',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function contractType()
    {
        return $this->belongsTo(ContractType::class);
    }
}
