<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketSale extends Model
{
    use SoftDeletes;

    protected $table = 'ticket_sales';

    protected $fillable = [
        'company_id',
        'bank_id',
        'paid_amount',
        'due_amount',
        'payment_status',
        'ticket_id',
        'invoice_no',
        'client_id',
        'sale_date',
        'due_date',
        'total_amount',
        'currency',
        'status',
        'created_by',
        'updated_by',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TicketSaleItem::class);
    }

    public function item()
    {
        return $this->hasOne(TicketSaleItem::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'invoice_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function schedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public static function nextInvoiceNumber(): string
    {
        $last = static::withTrashed()->latest('id')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'INV' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
