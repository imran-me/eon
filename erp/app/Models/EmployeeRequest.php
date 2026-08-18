<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_requests';

    protected $fillable = [
        'employee_id',
        'category',
        'request_type',
        'amount',
        'reason',
        'image',
        'status',
        'deadline',
        'requested_at',
        'approved_by',
        'approved_at',
        'remarks',
        'metadata',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at'  => 'datetime',
        'deadline'     => 'date',
        'metadata'     => 'array',
    ];

    const CATEGORY_REQUEST = 'request';
    const CATEGORY_REQUIRE = 'require';

    const TYPE_EMERGENCY_FUND  = 'emergency_fund';
    const TYPE_NOC_CERTIFICATE = 'noc_certificate';
    const TYPE_BONUS_INCENTIVE = 'bonus_incentive';

    const STATUS_PENDING      = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED     = 'approved';
    const STATUS_REJECTED     = 'rejected';
    const STATUS_FULFILLED    = 'fulfilled';
    const STATUS_CLOSED       = 'closed';

    const REQUEST_TYPES = [
        'request' => [
            'advance_salary'    => 'Advance Salary',
            'loan'              => 'Loan',
            'salary_dispute'    => 'Salary Dispute / Correction',
            'project_payment'   => 'Project Payment',
            'travel_allowance'  => 'Travel & Allowance',
            // Phase 3
            'emergency_fund'    => 'Emergency Fund',
            'noc_certificate'   => 'NOC / Certificate',
            'bonus_incentive'   => 'Bonus / Incentive',
            'equipment_resource' => 'Equipment / Resource Request',
        ],
        'require' => [
            'document_submission'    => 'Document Submission',
            'policy_acknowledgment'  => 'Policy Acknowledgment',
            'training_completion'    => 'Training / Course Completion',
            // Phase 3
            'asset_return'       => 'Asset Return Checklist',
            'performance_review' => 'Performance Self-Review',
            'timesheet_log'      => 'Timesheet / Work Log',
            'office_requisition' => 'Office Requisition',
            'interior_project'   => 'Interior Project Requirement',
        ],
    ];

    public static function requestTypeLabel(string $type): string
    {
        foreach (self::REQUEST_TYPES as $types) {
            if (isset($types[$type])) {
                return $types[$type];
            }
        }
        return ucfirst(str_replace('_', ' ', $type));
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requireAssignment()
    {
        return $this->hasOne(RequireAssignment::class, 'request_id');
    }

    public function attachments()
    {
        return $this->hasMany(RequestAttachment::class, 'request_id');
    }

    public function disbursement()
    {
        return $this->hasOne(RequestDisbursement::class, 'request_id');
    }

    public function recoveries()
    {
        return $this->hasMany(RequestRecovery::class, 'request_id')->orderBy('installment_no');
    }

    public function totalRecovered(): float
    {
        return (float) $this->recoveries()->sum('deducted_amount');
    }

    public function remainingBalance(): float
    {
        return max(0, (float) $this->amount - $this->totalRecovered());
    }

    public function isRequest(): bool
    {
        return $this->category === self::CATEGORY_REQUEST;
    }

    public function isRequire(): bool
    {
        return $this->category === self::CATEGORY_REQUIRE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isFulfilled(): bool
    {
        return $this->status === self::STATUS_FULFILLED;
    }
}
