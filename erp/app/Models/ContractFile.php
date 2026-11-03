<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ContractFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'file_number',
        'contract_file_submission_id',
        'passport_holder_id',
        'client_id',
        'client_type',
        'client_price',
        'client_paid_amount',
        'client_due_amount',
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
        'exception_flag',
        'required_documents',
        'notes',
        'created_by',
        'contract_file_visa_type_id',
    ];

    protected $casts = [
        'submit_date' => 'date',
        'expected_out_date' => 'date',
        'visa_rate' => 'float',
        'client_price' => 'float',
        'client_paid_amount' => 'float',
        'client_due_amount' => 'float',
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
        'file_received' => 'File Received',
        'information_entered' => 'Information Entered',
        'documents_pending' => 'Documents Pending',
        'documents_verified' => 'Documents Verified',
        'application_prepared' => 'Application Prepared',
        'submitted_to_vendor' => 'Submitted to Vendor',
        'biometrics_appointment' => 'Biometrics / Appointment',
        'under_process' => 'Under Process',
        'additional_documents' => 'Additional Documents',
        'decision_received' => 'Decision Received',
        'approved' => 'Approved',
        'passport_ready' => 'Passport Ready',
        'delivered' => 'Delivered',
        'case_closed' => 'Case Closed',
    ];

    public const EXCEPTION_FLAGS = [
        'returned' => 'Returned for Correction',
        'resubmitted' => 'Resubmitted',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    public const CLIENT_LEDGER_TYPE = 'contract_file_client';

    public const CLIENT_ADJUSTMENT_TYPE = 'contract_file_client_adjustment';

    public const VENDOR_LEDGER_TYPE = 'contract_file';

    public const VENDOR_ADJUSTMENT_TYPE = 'contract_file_vendor_adjustment';

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function passportHolder()
    {
        return $this->belongsTo(PassportHolder::class);
    }

    /** Setup → Visa Types (2026-10-25). Null on files made before it, or before its migration ran. */
    public function visaType(): BelongsTo
    {
        return $this->belongsTo(ContractFileVisaType::class, 'contract_file_visa_type_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function serviceCosts()
    {
        return $this->hasMany(ContractFileServiceCost::class);
    }

    public function fileDocuments()
    {
        return $this->hasMany(ContractFileDocument::class);
    }

    public function moneyChanges()
    {
        return $this->hasMany(ContractFileChange::class);
    }

    public function getFileChangesAttribute()
    {
        return Schema::hasTable('contract_file_changes') ? $this->moneyChanges : collect();
    }

    public function getNetClientChargeAttribute(): float
    {
        return (float) $this->client_price + $this->file_changes->sum('client_delta');
    }

    public function getProcessingRefundAttribute(): float
    {
        return $this->file_changes->whereIn('type', ['refund', 'cancel'])->whereNull('service_cost_id')->sum('vendor_refund');
    }

    public function getNetProcessingCostAttribute(): float
    {
        return max(0, (float) $this->visa_rate - $this->processing_refund);
    }

    public function getClientCreditAttribute(): float
    {
        return max(0, (float) $this->client_paid_amount - $this->net_client_charge);
    }

    public function getVendorCreditAttribute(): float
    {
        return max(0, (float) $this->cost_paid_amount - $this->net_processing_cost) + (Schema::hasTable('contract_file_service_costs') ? $this->serviceCosts->sum('refund_credit') : 0);
    }

    public function getTotalFileCostAttribute(): float
    {
        return $this->net_processing_cost + (Schema::hasTable('contract_file_service_costs') ? $this->serviceCosts->whereNull('voided_at')->sum('net_amount') : 0);
    }

    public function getTotalVendorDueAttribute(): float
    {
        return (float) $this->due_amount + (Schema::hasTable('contract_file_service_costs') ? $this->serviceCosts->sum('due_amount') : 0);
    }

    public function events()
    {
        return $this->hasMany(ContractFileEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
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

    public static function stageLabel(?string $key): string
    {
        return self::STATUSES[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
    }

    public function getClientDueAttribute(): float
    {
        return max(0, (float) $this->client_due_amount);
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

        return 'CF-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
