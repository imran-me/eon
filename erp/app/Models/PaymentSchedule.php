<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentSchedule extends Model
{
    protected $fillable = [
        'company_id', 'project_category_id',
        'schedulable_type', 'schedulable_id',
        'type', 'party_type', 'party_id', 'party_name', 'source_label',
        'amount', 'paid_amount', 'scheduled_date', 'status',
        'paid_date', 'transaction_id', 'note', 'created_by',
        // reschedule
        'original_scheduled_date', 'reschedule_count', 'reschedule_reason',
        // approval
        'approved_by', 'approved_at', 'approval_note',
        // priority
        'priority',
    ];

    protected $casts = [
        'scheduled_date'          => 'date',
        'paid_date'               => 'date',
        'original_scheduled_date' => 'date',
        'approved_at'             => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function projectCategory()
    {
        return $this->belongsTo(\App\Models\ProjectCategory::class);
    }

    public function schedulable()
    {
        return $this->morphTo();
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function logs()
    {
        return $this->hasMany(PaymentScheduleLog::class, 'payment_schedule_id')->latest();
    }

    // ── Settled money — ONE rule, used everywhere ─────────────────────
    //
    // `paid_amount` is the money that actually moved; `status` only says
    // where the schedule sits in the payment queue. The two are
    // deliberately independent: a schedule can carry a part payment and
    // still be waiting on the rest.
    //
    // So NEVER filter on status = 'paid' before summing paid_amount — that
    // silently drops real payments. It is what made one ৳10,000 partial
    // payment read as ৳10,000 on the salary sheet and ৳0 on the same
    // employee's payslips tab (Imran Hossain, July 2026, 2026-08-18).
    //
    // Every paid/due figure for a payslip must go through settled(),
    // settledTotalsSubquery() or paidTotal() below, so that no two screens
    // can ever disagree about the same payment again.

    /**
     * Statuses whose paid_amount must NOT count as settled money.
     *
     * Only 'cancelled'. cancel() already refuses a paid schedule, so a
     * cancelled row never holds money — it is listed here so the rule is
     * explicit rather than accidental.
     */
    public const NON_SETTLING_STATUSES = ['cancelled'];

    /** Query form — restrict to schedules whose paid_amount is real money. */
    public function scopeSettled($q)
    {
        return $q->whereNotIn('status', self::NON_SETTLING_STATUSES);
    }

    /**
     * Sub-select form — "how much has been settled per schedulable", keyed
     * by schedulable_id, for the leftJoinSub() in the salary sheet and the
     * payroll report.
     */
    public static function settledTotalsSubquery(?string $schedulableType = null)
    {
        return DB::table('payment_schedules')
            ->when($schedulableType, fn ($q) => $q->where('schedulable_type', $schedulableType))
            ->whereNotIn('status', self::NON_SETTLING_STATUSES)
            ->groupBy('schedulable_id')
            ->selectRaw('schedulable_id, SUM(paid_amount) as paid_total');
    }

    /**
     * Collection form — the same rule against already-loaded schedules, so
     * a Blade row does not re-query (N+1) just to total what it has.
     */
    public static function paidTotal($schedules): float
    {
        return round((float) collect($schedules)
            ->whereNotIn('status', self::NON_SETTLING_STATUSES)
            ->sum(fn ($s) => (float) ($s->paid_amount ?? 0)), 2);
    }

    public function scopeOverdue($q) { return $q->where('status', 'overdue'); }
    public function scopeToday($q)   { return $q->where('scheduled_date', today())->where('status', 'pending'); }
    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeReceive($q) { return $q->where('type', 'receive'); }
    public function scopePay($q)     { return $q->where('type', 'pay'); }
}
