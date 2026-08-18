<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestDisbursement extends Model
{
    protected $table = 'request_disbursements';

    protected $fillable = [
        'request_id',
        'payment_method',
        'disbursed_amount',
        'disbursed_by',
        'disbursed_at',
        'journal_entry_id',
        'attachment',
        'note',
    ];

    protected $casts = [
        'disbursed_at' => 'datetime',
    ];

    const PAYMENT_METHODS = [
        'cash'              => 'Cash',
        'bank_transfer'     => 'Bank Transfer',
        'cheque'            => 'Cheque',
        'payroll_deduction' => 'Payroll Deduction',
    ];

    public function request()
    {
        return $this->belongsTo(EmployeeRequest::class, 'request_id');
    }

    public function disbursedBy()
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
