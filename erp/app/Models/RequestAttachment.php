<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestAttachment extends Model
{
    use HasFactory;

    protected $table = 'request_attachments';

    protected $fillable = [
        'request_id',
        'file_path',
        'file_type',
        'original_name',
        'uploaded_by',
    ];

    public function request()
    {
        return $this->belongsTo(EmployeeRequest::class, 'request_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
