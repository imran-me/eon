<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractFileDocument extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['verified_at'=>'datetime'];
    public function file() { return $this->belongsTo(ContractFile::class, 'contract_file_id'); }
}
