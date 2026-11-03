<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractFileEvent extends Model
{
    protected $fillable = ['type', 'code', 'label', 'note', 'occurred_at', 'created_by'];
    protected $casts = ['occurred_at' => 'datetime'];

    public function file() { return $this->belongsTo(ContractFile::class, 'contract_file_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
