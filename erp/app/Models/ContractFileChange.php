<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractFileChange extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['client_refund'=>'float', 'vendor_refund'=>'float', 'penalty'=>'float', 'extra_cost'=>'float', 'client_delta'=>'float', 'snapshot'=>'array', 'occurred_on'=>'date'];
    public function file() { return $this->belongsTo(ContractFile::class, 'contract_file_id'); }
    public function serviceCost() { return $this->belongsTo(ContractFileServiceCost::class); }
    public function client() { return $this->belongsTo(User::class, 'client_id'); }
    public function vendor() { return $this->belongsTo(User::class, 'vendor_id'); }
}
