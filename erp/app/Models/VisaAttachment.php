<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisaAttachment extends Model
{
    protected $fillable = [
        'visa_process_id',
        'file_path',
        'file_name',
    ];

    public function visaProcess()
    {
        return $this->belongsTo(VisaProcess::class);
    }
}
