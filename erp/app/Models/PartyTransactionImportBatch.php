<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyTransactionImportBatch extends Model
{
    protected $fillable = [
        'company_id', 'original_filename', 'status', 'rows', 'notify_parties',
        'uploaded_by', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at',
        'rejection_note', 'posted_references',
    ];

    protected $casts = [
        'rows' => 'array', 'posted_references' => 'array', 'notify_parties' => 'boolean',
        'approved_at' => 'datetime', 'rejected_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
