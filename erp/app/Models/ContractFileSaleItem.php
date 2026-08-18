<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractFileSaleItem extends Model
{
    protected $fillable = [
        'contract_file_sale_id',
        'contract_file_id',
        'sale_price',
        'vendor_cost',
    ];

    protected $casts = [
        'sale_price' => 'float',
        'vendor_cost' => 'float',
    ];

    public function sale()
    {
        return $this->belongsTo(ContractFileSale::class, 'contract_file_sale_id');
    }

    public function file()
    {
        return $this->belongsTo(ContractFile::class, 'contract_file_id');
    }
}
