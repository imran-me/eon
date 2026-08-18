<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisaProcess extends Model
{
    use HasFactory, SoftDeletes;

    public static $stages = [
        'Document Collection',
        'Document Verification',
        'Embassy Submission',
        'Biometric / Interview',
        'Final Approval Wait',
        'Ready for Delivery',
    ];

    protected $fillable = [
        'application_id',
        'passport_holder_id',
        'vendor_id',
        'portal_id',
        'cost_bank_id',
        'country_id',
        'visa_category_id',
        'visa_type',
        'duration',
        'travel_date',
        'embassy_fee',
        'vfs_fee',
        'our_service_fee',
        'costing_price',
        'due_amount',
        'cost_paid_amount',
        'purchase_date',
        'sale_price',
        'advance_received',
        'payable_date',
        'receivable_date',
        'payment_status',
        'assigned_officer_id',
        'status',
        'stage',
        'remarks',
    ];

    protected $casts = [
        'travel_date' => 'date',
        'payable_date' => 'date',
        'receivable_date' => 'date',
        'purchase_date' => 'date',
        'cost_paid_amount' => 'float',
    ];

    public function getTotalFeeAttribute(): float
    {
        return (float) $this->sale_price;
    }

    /**
     * Client-facing balance still owed to us (sale_price - advance_received).
     * Named distinctly from the real `due_amount` column, which tracks the
     * separate vendor-payable balance (see PaymentSchedule/PostsPurchaseJournal).
     */
    public function getClientDueAttribute(): float
    {
        return max(0, $this->total_fee - (float) $this->advance_received);
    }

    public function assignedOfficer()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_officer_id');
    }

    public function passportHolder()
    {
        return $this->belongsTo(PassportHolder::class);
    }

    public function vendor()
    {
        return $this->belongsTo(\App\Models\User::class, 'vendor_id');
    }

    public function portal()
    {
        return $this->belongsTo(Portal::class);
    }

    public function costBank()
    {
        return $this->belongsTo(Bank::class, 'cost_bank_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function visaCategory()
    {
        return $this->belongsTo(VisaCategory::class);
    }

    public function visaAttachments()
    {
        return $this->hasMany(VisaAttachment::class);
    }

    public function processDocuments()
    {
        return $this->hasMany(VisaProcessDocument::class)->orderBy('sort_order');
    }

    public function paymentSchedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }
}
