<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $table = 'purchases';

    protected $fillable = [
        'supplier_id',
        'company_id',
        'bank_id',
        'purchase_no',
        'purchase_date',
        'total_amount',
        'paid_amount',
        'due_amount',
        'commission_amount',
        'payment_status',
        'payment_method',
        'note',
        'created_by'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
    public function purchase_items()
    {
        return $this->hasMany(PurchaseItem::class);
    }
    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }
    public function payments()
    {
        return $this->hasMany(Transaction::class, 'invoice_id', 'id')->where('type', 'purchase');
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'invoice_id', 'id')->where('type', 'purchase');
    }

    public function schedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }
}
