<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractFileSale extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'company_id',
        'client_id',
        'country_id',
        'file_ids',
        'files_count',
        'sale_date',
        'total_amount',
        'paid_amount',
        'due_amount',
        'receivable_date',
        'vendor_cost',
        'payment_status',
        'payment_method',
        'bank_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'file_ids' => 'array',
        'sale_date' => 'date',
        'receivable_date' => 'date',
        'total_amount' => 'float',
        'paid_amount' => 'float',
        'due_amount' => 'float',
        'vendor_cost' => 'float',
    ];

    protected $appends = ['net_profit'];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function items()
    {
        return $this->hasMany(ContractFileSaleItem::class, 'contract_file_sale_id');
    }

    public function files()
    {
        return ContractFile::whereIn('id', $this->file_ids ?? [])->get();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public function paymentSchedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }

    public function getNetProfitAttribute(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->vendor_cost);
    }

    public static function nextInvoiceNumber(): string
    {
        $last = static::withTrashed()->latest('id')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'CFINV-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
