<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'file_number',
        'passport_holder_id',
        'country_id',
        'contract_file_category_id',
        'vendor_id',
        'portal_id',
        'cost_bank_id',
        'visa_rate',
        'due_amount',
        'cost_paid_amount',
        'purchase_date',
        'payable_date',
        'submit_date',
        'expected_out_date',
        'status',
        'required_documents',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'submit_date' => 'date',
        'expected_out_date' => 'date',
        'visa_rate' => 'float',
        'due_amount' => 'float',
        'cost_paid_amount' => 'float',
        'purchase_date' => 'date',
        'payable_date' => 'date',
        'required_documents' => 'array',
    ];

    protected $appends = [
        'status_label',
        'documents_total',
        'documents_received',
    ];

    public const STATUSES = [
        'doc_collection' => 'Doc Collection',
        'submitted_to_vendor' => 'Submitted to Vendor',
        'under_process' => 'Under Process',
        'approved' => 'Approved',
        'delivered' => 'Delivered',
        'rejected' => 'Rejected',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function passportHolder()
    {
        return $this->belongsTo(PassportHolder::class);
    }

    public function category()
    {
        return $this->belongsTo(ContractFileCategory::class, 'contract_file_category_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function portal()
    {
        return $this->belongsTo(Portal::class);
    }

    public function costBank()
    {
        return $this->belongsTo(Bank::class, 'cost_bank_id');
    }

    public function paymentSchedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function getApplicantNameAttribute($value): ?string
    {
        return $value ?: $this->passportHolder?->name;
    }

    public function getPhoneAttribute($value): ?string
    {
        return $value ?: $this->passportHolder?->phone;
    }

    public function getPassportNoAttribute($value): ?string
    {
        return $value ?: $this->passportHolder?->passport_no;
    }

    public function getDateOfBirthAttribute($value): ?Carbon
    {
        $date = $value ?: $this->passportHolder?->date_of_birth;

        return $date ? Carbon::parse($date) : null;
    }

    public function getDocumentsTotalAttribute(): int
    {
        return count($this->required_documents ?? []);
    }

    public function getDocumentsReceivedAttribute(): int
    {
        return collect($this->required_documents ?? [])->where('checked', true)->count();
    }

    public static function nextFileNumber(): string
    {
        $last = static::withTrashed()
            ->whereNotNull('file_number')
            ->orderByDesc('id')
            ->value('file_number');

        if ($last && preg_match('/CF-(\d+)/', $last, $match)) {
            $next = (int) $match[1] + 1;
        } else {
            $next = static::withTrashed()->count() + 1;
        }

        return 'CF-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
