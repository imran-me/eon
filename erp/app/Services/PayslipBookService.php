<?php

namespace App\Services;

use App\Models\Payslip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads the payslip register — the figures the Payslip tab is built out of.
 *
 * Same shape as LoanBookService next door: one place that says what a payslip row
 * is, feeding the screen, the spreadsheet and the printed report so the three
 * cannot disagree. The controller scopes; this reads.
 *
 * A payslip's money lives on the EmployeeSalary it was issued against, not on the
 * payslip row itself, so every figure here is read through that relation.
 */
class PayslipBookService
{
    /**
     * Where a payslip stands.
     *
     * Derived from the payment schedules rather than from employee_salaries.status
     * alone, exactly as Salary Manage derives it: a slip can be part-paid, and a
     * status column with two values cannot say so.
     */
    public function status(Payslip $slip): string
    {
        $salary = $slip->employee_salary;

        if (! $salary) {
            return 'accrued';
        }

        if (strtolower((string) $salary->status) === 'paid') {
            return 'paid';
        }

        $paid = (float) ($salary->schedules?->sum(fn ($s) => (float) ($s->paid_amount ?? 0)) ?? 0);

        return $paid > 0 ? 'partial' : 'accrued';
    }

    /** What is still owed on a payslip. */
    public function due(Payslip $slip): float
    {
        $salary = $slip->employee_salary;

        if (! $salary) {
            return 0.0;
        }

        if (strtolower((string) $salary->status) === 'paid') {
            return 0.0;
        }

        $paid = (float) ($salary->schedules?->sum(fn ($s) => (float) ($s->paid_amount ?? 0)) ?? 0);

        return max(0, round((float) $salary->net_salary - $paid, 2));
    }

    /** The month a payslip covers, as a sortable Y-m. */
    public function period(Payslip $slip): ?string
    {
        $salary = $slip->employee_salary;

        if (! $salary || ! $salary->year || ! $salary->month) {
            return null;
        }

        return sprintf('%04d-%02d', (int) $salary->year, (int) $salary->month);
    }

    /** "July 2026" for a Y-m. */
    public function periodLabel(?string $ym): string
    {
        return $ym ? Carbon::createFromFormat('Y-m', $ym)->format('F Y') : '—';
    }

    /**
     * Everything paid on top of the template salary — the same three parts the
     * salary form adds up as "Total Additions".
     *
     * The register carries this as its own column because without it the row does
     * not add up: gross less deductions is NOT the net, and a reader who tries
     * that subtraction and comes up short will assume the table is wrong.
     * Gross + Additions − Deductions = Net.
     */
    public function additions(Payslip $slip): float
    {
        $salary = $slip->employee_salary;

        return round(
            (float) ($salary->overtime_salary ?? 0)
            + (float) ($salary->bonus_amount ?? 0)
            + (float) ($salary->salary_adjustment ?? 0),
            2
        );
    }

    /**
     * The headline figures.
     *
     * Gross, net and deductions sum over whatever is in scope — pick one month or
     * one status and the cards follow it. `due` is what is still owed across the
     * scope, which is the figure the "paid" card is the other half of.
     */
    public function summary(Collection $slips): array
    {
        $gross = round((float) $slips->sum(fn (Payslip $s) => (float) ($s->employee_salary->gross_salary ?? 0)), 2);
        $additions = round((float) $slips->sum(fn (Payslip $s) => $this->additions($s)), 2);
        $net = round((float) $slips->sum(fn (Payslip $s) => (float) ($s->employee_salary->net_salary ?? 0)), 2);
        $deductions = round((float) $slips->sum(fn (Payslip $s) => (float) ($s->employee_salary->total_deductions ?? 0)), 2);
        $due = round((float) $slips->sum(fn (Payslip $s) => $this->due($s)), 2);

        $byStatus = $slips->countBy(fn (Payslip $s) => $this->status($s));

        return [
            'count'       => $slips->count(),
            'employees'   => $slips->pluck('user_id')->unique()->count(),
            'months'      => $slips->map(fn (Payslip $s) => $this->period($s))->filter()->unique()->count(),
            'gross'       => $gross,
            'additions'   => $additions,
            'net'         => $net,
            'deductions'  => $deductions,
            'due'         => $due,
            'paid'        => round($net - $due, 2),
            'paid_pct'    => $net > 0 ? (int) round(($net - $due) / $net * 100) : 0,
            'by_status'   => [
                'paid'    => (int) ($byStatus['paid'] ?? 0),
                'partial' => (int) ($byStatus['partial'] ?? 0),
                'accrued' => (int) ($byStatus['accrued'] ?? 0),
            ],
        ];
    }

    /** How the status split reads in a footer or a caption. */
    public function statusSplit(array $byStatus): string
    {
        return collect($byStatus)
            ->filter(fn (int $n) => $n > 0)
            ->map(fn (int $n, string $k) => $n . ' ' . $k)
            ->implode(' · ') ?: 'none';
    }

    /* ========================================================= what exports */

    /**
     * The register flattened to a sheet the spreadsheet and the printed report
     * both render from.
     *
     * Money cells come out as raw floats, not as "৳ 1,234" — a spreadsheet whose
     * amount column is text cannot be summed, sorted or pivoted, which is most of
     * why anyone exports one. The report formats them on the way out. Each heading
     * carries whether it is money so neither renderer has to guess.
     */
    public function sheet(Collection $slips, bool $withCompany = true): array
    {
        $sheet = $this->buildSheet($slips, $withCompany);

        // The unit is stated once in the heading, the way the party statement's
        // export heads its "Debit (৳)" column — a cell prefixed with a currency
        // sign stops being a number you can add up.
        foreach ($sheet['headings'] as $i => $heading) {
            if (! empty($heading['money'])) {
                $sheet['headings'][$i]['label'] .= ' (৳)';
            }
        }

        return $sheet;
    }

    private function buildSheet(Collection $slips, bool $withCompany): array
    {
        $byStatus = [
            'paid'    => 0,
            'partial' => 0,
            'accrued' => 0,
        ];

        $rows = $slips->map(function (Payslip $slip) use ($withCompany, &$byStatus) {
            $salary = $slip->employee_salary;
            $status = $this->status($slip);
            $byStatus[$status]++;

            return array_values(array_filter([
                $slip->payslip_number ?: '—',
                $slip->user?->name ?? 'Employee #' . $slip->user_id,
                $slip->user?->employee_id_no ?: '—',
                $withCompany ? ($slip->user?->company?->short_name ?: $slip->user?->company?->name ?: '—') : null,
                $this->periodLabel($this->period($slip)),
                $slip->issue_date ? Carbon::parse($slip->issue_date)->format('d M Y') : '—',
                (float) ($salary->gross_salary ?? 0),
                $this->additions($slip),
                (float) ($salary->total_deductions ?? 0),
                (float) ($salary->net_salary ?? 0),
                $this->due($slip),
                ucfirst($status),
            ], fn ($v) => $v !== null));
        })->all();

        return [
            'key'      => 'payslips',
            'title'    => 'Payslip Register',
            'subtitle' => 'every payslip issued — month · gross · deductions · net · still due',
            'filename' => 'payslips',
            'headings' => array_values(array_filter([
                ['label' => 'Payslip #', 'money' => false],
                ['label' => 'Employee', 'money' => false],
                ['label' => 'Employee ID', 'money' => false],
                $withCompany ? ['label' => 'Company', 'money' => false] : null,
                ['label' => 'Month', 'money' => false],
                ['label' => 'Issued', 'money' => false],
                ['label' => 'Gross', 'money' => true],
                ['label' => 'Additions', 'money' => true],
                ['label' => 'Deductions', 'money' => true],
                ['label' => 'Net', 'money' => true],
                ['label' => 'Still Due', 'money' => true],
                ['label' => 'Status', 'money' => false],
            ])),
            'rows' => $rows,
            /* THE FOOT: gross, deductions, net and due all sum, because each row
             * is that month's own figure rather than a running balance. STATUS has
             * no total — it counts instead, which is the question the column
             * actually raises. */
            'totals' => array_values(array_filter([
                $slips->count() . ' ' . Str::plural('payslip', $slips->count()),
                null,
                null,
                $withCompany ? null : false,
                null,
                null,
                round((float) $slips->sum(fn (Payslip $s) => (float) ($s->employee_salary->gross_salary ?? 0)), 2),
                round((float) $slips->sum(fn (Payslip $s) => $this->additions($s)), 2),
                round((float) $slips->sum(fn (Payslip $s) => (float) ($s->employee_salary->total_deductions ?? 0)), 2),
                round((float) $slips->sum(fn (Payslip $s) => (float) ($s->employee_salary->net_salary ?? 0)), 2),
                round((float) $slips->sum(fn (Payslip $s) => $this->due($s)), 2),
                $this->statusSplit($byStatus),
            ], fn ($v) => $v !== false)),
        ];
    }
}
