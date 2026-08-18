<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisaSale extends Model
{
    use SoftDeletes;

    protected $table = 'visa_sales';

    protected $fillable = [
        'company_id',
        'invoice_number',
        'client_id',
        'send_via',
        'bundle_label',
        'voucher_date',
        'receivable_date',
        'issued_by',
        'total_amount',
        'paid_amount',
        'due_amount',
        'payment_method',
        'bank_id',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'voucher_date'     => 'date',
        'receivable_date'  => 'date',
        'total_amount'     => 'float',
        'paid_amount'      => 'float',
        'due_amount'       => 'float',
    ];

    public function items()
    {
        return $this->hasMany(VisaSaleItem::class, 'visa_sale_id');
    }

    public function paymentSchedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }

    /**
     * PaymentScheduleController::markPaid() writes $parent->payment_status
     * on any "linked document" schedulable — alias it onto the real `status`
     * column instead of adding a duplicate column.
     */
    protected function paymentStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status,
            set: fn ($value) => ['status' => $value],
        );
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function visaProcesses()
    {
        return $this->hasManyThrough(
            VisaProcess::class,
            VisaSaleItem::class,
            'visa_sale_id',
            'id',
            'id',
            'visa_process_id'
        );
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public static function nextInvoiceNumber(): string
    {
        $last = static::withTrashed()->latest('id')->first();
        $next = $last ? ($last->id + 1) : 1;
        return 'VINV-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
