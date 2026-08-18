<?php

namespace App\Services;

use App\Models\FinancingLoan;
use App\Models\FinancingSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds and rebuilds the instalment schedule for a financing arrangement.
 *
 * Three interest methods, because they produce genuinely different money:
 *
 *   none      — the principal split evenly. A goodwill loan, or a service paid
 *               for in instalments at no charge.
 *   flat      — interest charged on the ORIGINAL principal for the whole term.
 *               Simple, and what most local vehicle and appliance financing
 *               quotes actually mean.
 *   reducing  — interest charged on the balance still outstanding, so the
 *               interest share of each instalment falls as the loan is repaid.
 *               What a bank term loan means.
 *
 * The same principal, rate and tenure give a materially larger total under
 * flat than under reducing, which is why the method is stored per loan and
 * never assumed.
 *
 * Writes no journal entries — the loan book is kept outside the main accounts.
 */
class FinancingScheduleService
{
    /**
     * Replace a loan's schedule with a freshly computed one.
     *
     * Refuses once money has been applied to the existing schedule: rebuilding
     * underneath settled instalments would silently detach payments from the
     * rows they were made against.
     *
     * @throws \RuntimeException when the existing schedule has been paid against
     */
    public function generate(FinancingLoan $loan): int
    {
        $settled = FinancingSchedule::where('financing_loan_id', $loan->id)
            ->where('paid_amount', '>', 0)
            ->exists();

        if ($settled) {
            throw new \RuntimeException(
                'This schedule already has payments against it. Reverse those before regenerating.'
            );
        }

        $rows = $this->build(
            (float) $loan->principal,
            (float) $loan->interest_rate,
            (string) $loan->interest_method,
            (int) $loan->tenure_months,
            $loan->start_date
        );

        return DB::transaction(function () use ($loan, $rows) {
            FinancingSchedule::where('financing_loan_id', $loan->id)->delete();

            foreach ($rows as $row) {
                FinancingSchedule::create($row + ['financing_loan_id' => $loan->id]);
            }

            // Store the headline instalment so the register need not recompute
            // it per row. The schedule stays the source of truth for what is owed.
            $loan->forceFill([
                'instalment_amount' => $rows[0]['total_amount'] ?? null,
            ])->save();

            return count($rows);
        });
    }

    /**
     * Compute the rows without touching the database — also what the "preview"
     * on the create screen calls, so what is previewed is what gets saved.
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(float $principal, float $rate, string $method, int $months, $startDate): array
    {
        if ($principal <= 0 || $months <= 0) {
            return [];
        }

        $start = $startDate instanceof Carbon ? $startDate->copy() : Carbon::parse($startDate ?: now());

        // Each method splits an instalment differently, so both components are
        // computed together — pairing one method's interest with another's
        // principal would produce an instalment that is neither.
        if ($method === 'reducing' && $rate > 0) {
            // Level payment: as the balance falls the interest share falls and
            // the principal share rises, while the total stays the same.
            $perMonth = $this->reducingRows($principal, $rate, $months);
        } elseif ($method === 'flat' && $rate > 0) {
            // Interest on the original principal for the whole term, split
            // evenly, against an evenly split principal.
            $interest = $principal * ($rate / 100) * ($months / 12);
            $perMonth = array_fill(0, $months, [
                'principal' => $principal / $months,
                'interest'  => $interest / $months,
            ]);
        } else {
            $perMonth = array_fill(0, $months, [
                'principal' => $principal / $months,
                'interest'  => 0.0,
            ]);
        }

        $interestTotal = array_sum(array_column($perMonth, 'interest'));

        $rows = [];
        $principalRunning = 0.0;
        $interestRunning  = 0.0;

        for ($i = 1; $i <= $months; $i++) {
            $isLast = $i === $months;

            // The final instalment absorbs every rounding remainder, so the
            // schedule sums to the principal and the interest total exactly
            // rather than drifting by a few poisha over a long tenure.
            $principalPart = $isLast
                ? round($principal - $principalRunning, 2)
                : round($perMonth[$i - 1]['principal'], 2);

            $interestPart = $isLast
                ? round($interestTotal - $interestRunning, 2)
                : round($perMonth[$i - 1]['interest'], 2);

            $principalRunning += $principalPart;
            $interestRunning  += $interestPart;

            $rows[] = [
                'instalment_no'       => $i,
                // Clamps a 31st to the end of a short month, so a loan starting
                // on the 31st does not skip February.
                'due_date'            => $start->copy()->addMonthsNoOverflow($i)->toDateString(),
                'principal_component' => $principalPart,
                'interest_component'  => $interestPart,
                'total_amount'        => round($principalPart + $interestPart, 2),
                'paid_amount'         => 0,
                'status'              => 'due',
            ];
        }

        return $rows;
    }

    /**
     * Amortise a reducing-balance loan into per-instalment principal and
     * interest, from the standard level-payment formula:
     *
     *      EMI = P·r·(1+r)^n / ((1+r)^n − 1)          r = monthly rate
     *
     * Interest each month is charged on what is still outstanding, and the
     * principal is whatever the level payment leaves after it — so the total
     * stays flat while the split shifts from interest toward principal.
     *
     * @return array<int, array{principal: float, interest: float}>
     */
    private function reducingRows(float $principal, float $annualRate, int $months): array
    {
        $r = $annualRate / 12 / 100;

        if ($r <= 0) {
            return array_fill(0, $months, ['principal' => $principal / $months, 'interest' => 0.0]);
        }

        $pow = pow(1 + $r, $months);
        $emi = $principal * $r * $pow / ($pow - 1);

        $balance = $principal;
        $out = [];

        for ($i = 0; $i < $months; $i++) {
            $interest = $balance * $r;
            $principalPart = $emi - $interest;
            $balance -= $principalPart;

            $out[] = [
                // Floating point can leave a hair either side on the final row;
                // the caller's last-instalment absorption settles the remainder.
                'principal' => max(0, $principalPart),
                'interest'  => max(0, $interest),
            ];
        }

        return $out;
    }

    /** The level instalment for a set of terms, for previews and quotes. */
    public function instalmentFor(float $principal, float $rate, string $method, int $months): float
    {
        $rows = $this->build($principal, $rate, $method, $months, now());

        return (float) ($rows[0]['total_amount'] ?? 0);
    }
}
