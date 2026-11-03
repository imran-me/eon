<?php

namespace App\Services;

use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLog;

/**
 * Closes open payment_schedules rows after money for that party actually
 * moved through some OTHER door than Payment Schedule's own Mark Paid button
 * — Manage Bank's Cash In/Cash Out (a vendor paid via direct withdrawal, an
 * agent/customer received via direct deposit) and Party Statement's Record
 * Payment. Without this, the dashboard's Payable/Receivable totals read
 * PaymentSchedule.status alone (never the party ledger or a bank balance),
 * so a party settled through any of those screens kept showing as still due
 * forever — the same class of bug PaymentScheduleController's
 * settleSchedulesForSalary() fixes for employees, generalized here by party
 * instead of by schedulable record, since a direct bank/ledger payment only
 * ever knows WHICH PARTY was paid, never which specific invoice/PO it was
 * for.
 *
 * Deliberately silent about any amount it can't place: a party with no open
 * schedule at all (the common case — most payments never had one) is not an
 * error, just nothing to reconcile.
 */
class PaymentScheduleReconciler
{
    /**
     * @param  int  $partyId  users.id of the vendor/agent/customer being settled
     * @param  string  $scheduleType  'pay' (a vendor being paid) or 'receive'
     *                 (an agent/customer paying us) — must match
     *                 payment_schedules.type, never inferred from the party's
     *                 role, since the caller already knows which way the
     *                 money moved.
     * @param  float  $allocated  amount actually settled for this party
     * @return float  whatever part of $allocated no open schedule could absorb
     */
    public function settleForParty(
        int $partyId,
        string $scheduleType,
        float $allocated,
        string $paymentDate,
        ?string $note = null,
        ?int $userId = null
    ): float {
        if ($allocated <= 0.01) {
            return $allocated;
        }

        $remaining = $allocated;
        $userId ??= auth()->id();

        $schedules = PaymentSchedule::where('party_id', $partyId)
            ->where('type', $scheduleType)
            ->open()
            ->orderBy('scheduled_date')
            ->lockForUpdate()
            ->get();

        foreach ($schedules as $schedule) {
            if ($remaining <= 0.01) {
                break;
            }

            $portion = min($remaining, (float) $schedule->amount);
            $isPartial = $portion < ((float) $schedule->amount - 0.01);

            $schedule->update([
                'status' => 'paid',
                'paid_amount' => $portion,
                'paid_date' => $paymentDate,
                'note' => $note ?: $schedule->note,
            ]);

            PaymentScheduleLog::create([
                'payment_schedule_id' => $schedule->id,
                'action' => $isPartial ? 'partial_paid' : 'paid',
                'old_date' => $schedule->scheduled_date,
                'new_date' => $paymentDate,
                'reason' => 'Allocated through a direct ' . ($scheduleType === 'pay' ? 'bank payment' : 'party ledger receipt') . '.',
                'done_by' => $userId,
            ]);

            if ($isPartial) {
                PaymentSchedule::create([
                    'company_id' => $schedule->company_id,
                    'project_category_id' => $schedule->project_category_id,
                    'schedulable_type' => $schedule->schedulable_type,
                    'schedulable_id' => $schedule->schedulable_id,
                    'type' => $schedule->type,
                    'party_type' => $schedule->party_type,
                    'party_id' => $schedule->party_id,
                    'party_name' => $schedule->party_name,
                    'source_label' => $schedule->source_label,
                    'amount' => round($schedule->amount - $portion, 2),
                    'scheduled_date' => $schedule->scheduled_date,
                    'status' => 'pending',
                    'priority' => $schedule->priority,
                    'note' => 'Remainder from partial payment (SCH-' . $schedule->id . ')',
                    'created_by' => $userId,
                ]);
            }

            $remaining = round($remaining - $portion, 2);
        }

        return $remaining;
    }
}
