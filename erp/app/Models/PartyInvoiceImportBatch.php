<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyInvoiceImportBatch extends Model
{
    protected $fillable = ['company_id', 'original_filename', 'status', 'documents', 'uploaded_by', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_note', 'created_invoice_ids'];

    protected $casts = ['documents' => 'array', 'created_invoice_ids' => 'array', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];

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
