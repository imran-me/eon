<?php

namespace App\Models;

use App\Services\FlightPackageTicketBilling;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    /** Presentation only: preserve original ledger remarks and source types. */
    public function getDisplayRemarksAttribute(): ?string
    {
        $remarks = $this->remarks;
        if (str_starts_with((string) $this->type, 'contract_file') && preg_match('/^File refund \((.*?)\): client ([\d,.]+), vendor ([\d,.]+), retained penalty ([\d,.]+) \((client|company) bears\)(.*)$/u', (string) $remarks, $parts)) {
            $text = $parts[1].' refund — Vendor refund ৳'.$parts[3].' · Penalty ৳'.$parts[4].($parts[5] === 'client' ? ' charged to Agent / Client' : ' borne by Company');
            if ((float) str_replace(',', '', $parts[2]) > 0) {
                $text .= ' · Client refund ৳'.$parts[2];
            }

            return $text.$parts[6];
        }
        if (str_starts_with((string) $this->type, 'contract_file')) {
            return str_ireplace(['Contract file charge', 'Contract file cost', 'Contract file'], ['File process charge', 'File process cost', 'File process'], $remarks ?? '');
        }

        return $remarks;
    }

    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'company_id',
        'expense_department_id',
        'user_id',
        'type',
        'user_type',
        'account_id',
        'payment_date',
        'transaction_time',
        'reference_no',
        'payment_method',
        'invoice_id',
        'old_balance',
        'debit',
        'credit',
        'balance',
        'remarks',
    ];

    protected static function booted(): void
    {
        $syncPackage = function (Transaction $row) {
            if ($row->type === 'ticket_sale') {
                app(FlightPackageTicketBilling::class)->sync((int) $row->invoice_id);
            }
        };
        static::saved($syncPackage);
        static::deleted($syncPackage);
        static::creating(function (Transaction $transaction) {
            $user = auth()->user();
            $selected = $user?->is_super_admin ? request()->integer('company') : null;
            $transaction->company_id ??= $selected ?: ($user?->company_id ?? 2);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Bank::class, 'account_id', 'id');
    }
}
