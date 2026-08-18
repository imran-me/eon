<?php

namespace App\Services;

use App\Models\AdvanceSalary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads the advance salary book — the figures the Advance tab is built out of.
 *
 * The same shape as LoanBookService next door, because it is the same kind of
 * book: money out, money back, and what is still between the two. One definition
 * feeds the screen, the spreadsheet and the printed report, so the three cannot
 * disagree.
 *
 * The one real difference from a loan is the extra state at the front. A loan
 * exists once it is disbursed; an advance is approved first and released later,
 * and between those two the money is owed TO the employee rather than by them.
 * That is why "Awaiting Release" is its own figure and never lands in
 * Outstanding.
 */
class AdvanceBookService
{
    public const SPARK_MONTHS = 12;

    /**
     * The headline figures.
     *
     * `recovered` is released − outstanding rather than a sum of recovery rows:
     * that is the only definition under which the tiles add up against each
     * other, and it is the same rule the loan book uses.
     */
    public function summary(Collection $advances): array
    {
        $released = round((float) $advances->sum(fn (AdvanceSalary $a) => $a->is_released ? (float) $a->amount : 0), 2);
        $outstanding = round((float) $advances->sum(fn (AdvanceSalary $a) => $a->outstanding), 2);
        $awaiting = $advances->filter(fn (AdvanceSalary $a) => ! $a->is_released);
        $open = $advances->filter(fn (AdvanceSalary $a) => $a->outstanding > 0);

        return [
            'released'        => $released,
            'outstanding'     => $outstanding,
            'recovered'       => round($released - $outstanding, 2),
            'recovered_pct'   => $released > 0 ? (int) round(($released - $outstanding) / $released * 100) : 0,
            'awaiting'        => round((float) $awaiting->sum('amount'), 2),
            'awaiting_count'  => $awaiting->count(),
            'advance_count'   => $advances->count(),
            'open_count'      => $open->count(),
            'holders'         => $open->pluck('user_id')->unique()->count(),
        ];
    }

    /**
     * One sparkline per tile, each a real month-end walk whose LAST point is the
     * figure printed above it — a line ending somewhere else would describe a
     * different number than the one being read.
     */
    public function series(Collection $advances, int $months = self::SPARK_MONTHS): array
    {
        $events = $this->events($advances);
        $window = $this->monthsUpTo($months);

        return [
            'outstanding' => $this->balanceSeries($events, $window),
            'released'    => $this->balanceSeries($this->outflows($events), $window),
            'recovered'   => $this->balanceSeries($this->inflows($events), $window),
            'holders'     => $this->headSeries($events, $window),
        ];
    }

    /**
     * The book folded down to one row per person — what they took, what has come
     * back, what is still owed, and what is still queued to go out to them.
     */
    public function byEmployee(Collection $advances): Collection
    {
        return $advances
            ->groupBy('user_id')
            ->map(function (Collection $theirs) {
                $first = $theirs->first();

                return [
                    'user'      => $first->user,
                    'user_id'   => $first->user_id,
                    'count'     => $theirs->count(),
                    'latest'    => $theirs->max('month'),
                    'taken'     => round((float) $theirs->sum(fn (AdvanceSalary $a) => $a->is_released ? (float) $a->amount : 0), 2),
                    'recovered' => round((float) $theirs->sum(fn (AdvanceSalary $a) => $a->recovered_amount), 2),
                    'due'       => round((float) $theirs->sum(fn (AdvanceSalary $a) => $a->outstanding), 2),
                    'awaiting'  => round((float) $theirs->sum(fn (AdvanceSalary $a) => $a->awaiting_release), 2),
                ];
            })
            // Someone with nothing outstanding and nothing queued has no live
            // position — their history stays in the register below.
            ->filter(fn (array $r) => $r['due'] > 0 || $r['awaiting'] > 0)
            ->sortByDesc('due')
            ->values();
    }

    /**
     * Every movement on the book, newest first: money released out, and money
     * recovered back through a payslip.
     *
     * An advance keeps no movement table of its own, so the trail is assembled
     * from the two places the money is actually recorded — the release on the
     * advance itself, and each recovery on the payslip that withheld it.
     */
    public function movements(Collection $advances): Collection
    {
        $rows = collect();

        foreach ($advances as $advance) {
            if ($advance->is_released) {
                $rows->push([
                    'id'        => 'rel-' . $advance->id,
                    'advance'   => $advance,
                    'user'      => $advance->user,
                    'type'      => 'release',
                    'date'      => $advance->paid_at ? Carbon::parse($advance->paid_at)->toDateString() : $advance->created_at?->toDateString(),
                    'note'      => 'Advance released' . ($advance->reason ? ' — ' . $advance->reason : ''),
                    'method'    => 'Cash / bank',
                    'amount'    => (float) $advance->amount,
                    // A release row shows where the advance stands NOW: there is
                    // no "after" for the movement that created the obligation.
                    'recovered' => $advance->recovered_amount,
                    'due'       => $advance->outstanding,
                ]);
            }

            $running = 0.0;

            foreach ($advance->recoveries as $salary) {
                $running = round($running + (float) $salary->advance_salary_deduction, 2);

                $rows->push([
                    'id'        => 'rec-' . $advance->id . '-' . $salary->id,
                    'advance'   => $advance,
                    'user'      => $advance->user,
                    'type'      => 'recovery',
                    'date'      => $salary->salary_generation_date
                        ? Carbon::parse($salary->salary_generation_date)->toDateString()
                        : sprintf('%04d-%02d-01', (int) $salary->year, (int) $salary->month),
                    'note'      => 'Recovered from ' . $this->monthLabel(sprintf('%04d-%02d', (int) $salary->year, (int) $salary->month)) . ' salary',
                    'method'    => 'Salary deduction',
                    'amount'    => (float) $salary->advance_salary_deduction,
                    'recovered' => $running,
                    'due'       => max(0, round((float) $advance->amount - $running, 2)),
                ]);
            }
        }

        return $rows->sortByDesc('date')->values();
    }

    /** "February 2026" for a Y-m. */
    public function monthLabel(?string $ym): string
    {
        if (! $ym || ! preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return $ym ?: '—';
        }

        return Carbon::createFromFormat('Y-m', $ym)->format('F Y');
    }

    /* ===================================================== the movement list */

    /** Money out is positive, money back is negative — one list for every tile. */
    private function events(Collection $advances): array
    {
        $out = [];

        foreach ($advances as $advance) {
            if (! $advance->is_released) {
                continue;
            }

            $releasedOn = $advance->paid_at ? Carbon::parse($advance->paid_at) : $advance->created_at;

            if ($releasedOn) {
                $out[] = ['ym' => $releasedOn->format('Y-m'), 'user_id' => $advance->user_id, 'delta' => (float) $advance->amount];
            }

            foreach ($advance->recoveries as $salary) {
                $on = $salary->salary_generation_date
                    ? Carbon::parse($salary->salary_generation_date)
                    : Carbon::createFromDate((int) $salary->year, (int) $salary->month, 1);

                $out[] = ['ym' => $on->format('Y-m'), 'user_id' => $advance->user_id, 'delta' => -(float) $salary->advance_salary_deduction];
            }
        }

        return $out;
    }

    private function outflows(array $events): array
    {
        return array_values(array_filter($events, fn (array $e) => $e['delta'] > 0));
    }

    /** Money coming back, sign-flipped so a "recovered to date" line climbs. */
    private function inflows(array $events): array
    {
        return array_values(array_map(
            fn (array $e) => ['ym' => $e['ym'], 'user_id' => $e['user_id'], 'delta' => -$e['delta']],
            array_filter($events, fn (array $e) => $e['delta'] < 0)
        ));
    }

    private function monthsUpTo(int $n): array
    {
        $end = Carbon::now()->startOfMonth();

        return collect(range($n - 1, 0))
            ->map(fn (int $back) => $end->copy()->subMonths($back)->format('Y-m'))
            ->all();
    }

    private function balanceSeries(array $events, array $months): array
    {
        $first = $months[0];
        $opening = 0.0;
        $byMonth = [];

        foreach ($events as $e) {
            if ($e['ym'] < $first) {
                $opening += $e['delta'];
                continue;
            }

            $byMonth[$e['ym']] = ($byMonth[$e['ym']] ?? 0) + $e['delta'];
        }

        $balance = $opening;

        return array_map(function (string $m) use (&$balance, $byMonth) {
            $balance += ($byMonth[$m] ?? 0);

            return round($balance, 2);
        }, $months);
    }

    /** The same walk counting people who owed something at each month end. */
    private function headSeries(array $events, array $months): array
    {
        $first = $months[0];
        $balances = [];
        $byMonth = [];

        foreach ($events as $e) {
            if ($e['ym'] < $first) {
                $balances[$e['user_id']] = ($balances[$e['user_id']] ?? 0) + $e['delta'];
                continue;
            }

            $byMonth[$e['ym']][] = $e;
        }

        return array_map(function (string $m) use (&$balances, $byMonth) {
            foreach ($byMonth[$m] ?? [] as $e) {
                $balances[$e['user_id']] = ($balances[$e['user_id']] ?? 0) + $e['delta'];
            }

            return count(array_filter($balances, fn ($v) => $v > 0.5));
        }, $months);
    }

    /* ========================================================= what exports */

    /** The three tables an export can ask for. */
    public static function tables(): array
    {
        return ['employees', 'register', 'transactions'];
    }

    public function sheet(string $table, Collection $advances, bool $withCompany = true): array
    {
        $sheet = match ($table) {
            'employees'    => $this->employeeSheet($advances, $withCompany),
            'register'     => $this->registerSheet($advances, $withCompany),
            'transactions' => $this->transactionSheet($advances, $withCompany),
            default        => throw new \InvalidArgumentException('Unknown advance table: ' . $table),
        };

        // The unit stated once in the heading, as the party statement's export
        // heads its "Debit (৳)" column — a cell prefixed with a currency sign
        // stops being a number you can add up.
        foreach ($sheet['headings'] as $i => $heading) {
            if (! empty($heading['money'])) {
                $sheet['headings'][$i]['label'] .= ' (৳)';
            }
        }

        return $sheet;
    }

    private function employeeSheet(Collection $advances, bool $withCompany): array
    {
        $rows = $this->byEmployee($advances);

        return [
            'key'      => 'employees',
            'title'    => 'Employees with Advances',
            'subtitle' => 'released · recovered · still due · awaiting release, per person',
            'filename' => 'staff-advances',
            'headings' => array_values(array_filter([
                ['label' => 'Employee', 'money' => false],
                ['label' => 'Employee ID', 'money' => false],
                $withCompany ? ['label' => 'Company', 'money' => false] : null,
                ['label' => 'Advances', 'money' => false, 'align' => 'right'],
                ['label' => 'Latest', 'money' => false],
                ['label' => 'Released', 'money' => true],
                ['label' => 'Recovered', 'money' => true],
                ['label' => 'Still Due', 'money' => true],
                ['label' => 'Awaiting Release', 'money' => true],
            ])),
            'rows' => $rows->map(fn (array $r) => array_values(array_filter([
                $r['user']?->name ?? 'Employee #' . $r['user_id'],
                $r['user']?->employee_id_no ?: '—',
                $withCompany ? ($r['user']?->company?->short_name ?: $r['user']?->company?->name ?: '—') : null,
                $r['count'],
                $this->monthLabel($r['latest']),
                $r['taken'],
                $r['recovered'],
                $r['due'],
                $r['awaiting'],
            ], fn ($v) => $v !== null)))->all(),
            'totals' => array_values(array_filter([
                $rows->count() . ' ' . Str::plural('person', $rows->count()),
                null,
                $withCompany ? null : false,
                null,
                null,
                round((float) $rows->sum('taken'), 2),
                round((float) $rows->sum('recovered'), 2),
                round((float) $rows->sum('due'), 2),
                round((float) $rows->sum('awaiting'), 2),
            ], fn ($v) => $v !== false)),
        ];
    }

    private function registerSheet(Collection $advances, bool $withCompany): array
    {
        $byState = $advances->countBy(fn (AdvanceSalary $a) => $a->state);

        return [
            'key'      => 'register',
            'title'    => 'Advance Register',
            'subtitle' => 'every advance ever approved — month · amount · recovered · still due',
            'filename' => 'advance-register',
            'headings' => array_values(array_filter([
                ['label' => 'Employee', 'money' => false],
                ['label' => 'Employee ID', 'money' => false],
                $withCompany ? ['label' => 'Company', 'money' => false] : null,
                ['label' => 'For Month', 'money' => false],
                ['label' => 'Released On', 'money' => false],
                ['label' => 'Amount', 'money' => true],
                ['label' => 'Recovered', 'money' => true],
                ['label' => 'Recovered %', 'money' => false, 'align' => 'right'],
                ['label' => 'Still Due', 'money' => true],
                ['label' => 'Reason', 'money' => false],
                ['label' => 'Status', 'money' => false],
            ])),
            'rows' => $advances->map(fn (AdvanceSalary $a) => array_values(array_filter([
                $a->user?->name ?? 'Employee #' . $a->user_id,
                $a->user?->employee_id_no ?: '—',
                $withCompany ? ($a->user?->company?->short_name ?: $a->user?->company?->name ?: '—') : null,
                $this->monthLabel($a->month),
                $a->is_released && $a->paid_at ? Carbon::parse($a->paid_at)->format('d M Y') : 'not released',
                (float) $a->amount,
                $a->recovered_amount,
                $a->progress_pct . '%',
                $a->outstanding,
                $a->reason ?: '—',
                ucfirst($a->state),
            ], fn ($v) => $v !== null)))->all(),
            /* Amount, recovered and due all sum — each row is that advance's own
             * figure rather than a running balance. STATUS has no total; it
             * counts, which is the question the column actually raises. */
            'totals' => array_values(array_filter([
                $advances->count() . ' ' . Str::plural('advance', $advances->count()),
                null,
                $withCompany ? null : false,
                null,
                null,
                round((float) $advances->sum(fn (AdvanceSalary $a) => (float) $a->amount), 2),
                round((float) $advances->sum(fn (AdvanceSalary $a) => $a->recovered_amount), 2),
                null,
                round((float) $advances->sum(fn (AdvanceSalary $a) => $a->outstanding), 2),
                null,
                collect($byState)->map(fn ($n, $k) => $n . ' ' . $k)->implode(' · ') ?: 'none',
            ], fn ($v) => $v !== false)),
        ];
    }

    private function transactionSheet(Collection $advances, bool $withCompany): array
    {
        $rows = $this->movements($advances);

        $out = round((float) $rows->where('type', 'release')->sum('amount'), 2);
        $back = round((float) $rows->where('type', 'recovery')->sum('amount'), 2);

        return [
            'key'      => 'transactions',
            'title'    => 'Advance Transactions',
            'subtitle' => 'every movement on the book — money released and money recovered',
            'filename' => 'advance-transactions',
            'headings' => array_values(array_filter([
                ['label' => 'Date', 'money' => false],
                ['label' => 'Employee', 'money' => false],
                ['label' => 'Employee ID', 'money' => false],
                $withCompany ? ['label' => 'Company', 'money' => false] : null,
                ['label' => 'Type', 'money' => false],
                ['label' => 'Note', 'money' => false],
                ['label' => 'Method', 'money' => false],
                ['label' => 'The Advance', 'money' => true],
                ['label' => 'For Month', 'money' => false],
                ['label' => 'Recovered Till Then', 'money' => true],
                ['label' => 'Due After', 'money' => true],
                ['label' => 'Amount', 'money' => true],
            ])),
            'rows' => $rows->map(fn (array $r) => array_values(array_filter([
                Carbon::parse($r['date'])->format('d M Y'),
                $r['user']?->name ?? 'Employee',
                $r['user']?->employee_id_no ?: '—',
                $withCompany ? ($r['user']?->company?->short_name ?: $r['user']?->company?->name ?: '—') : null,
                $r['type'] === 'recovery' ? 'recovery' : 'release',
                $r['note'],
                $r['method'],
                (float) $r['advance']->amount,
                $this->monthLabel($r['advance']->month),
                $r['recovered'],
                $r['due'],
                $r['amount'],
            ], fn ($v) => $v !== null)))->all(),
            /* The one column where a single total would be a LIE: these rows run
             * in both directions, so summing Amount nets a release against a
             * recovery and calls the result "amount". It foots as both directions
             * plus the net. The two balance columns are positions at a moment in
             * time and are deliberately not summed. */
            'totals' => array_values(array_filter([
                $rows->count() . ' ' . Str::plural('movement', $rows->count()),
                null,
                null,
                $withCompany ? null : false,
                null,
                null,
                null,
                null,
                null,
                '',
                'balances, not sums',
                number_format($out - $back) . ' net (' . number_format($out) . ' out · ' . number_format($back) . ' back)',
            ], fn ($v) => $v !== false)),
        ];
    }
}
