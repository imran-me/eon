<?php

namespace App\Services;

use App\Models\Loan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads the staff loan book — the figures the Loans tab is built out of.
 *
 * Everything here is derived from the loans in scope and their transaction rows,
 * never from a stored total, so the tiles, the per-person table, the register and
 * the transaction trail are four views of one set of numbers and cannot disagree
 * with each other. LoanLedgerService writes the book; this reads it.
 *
 * The one figure with no row behind it is loans.opening_paid_amount — whatever an
 * older loan had already repaid before movements were recorded. It is folded in
 * at the loan's disbursement date, which is the only date it can honestly claim,
 * and which keeps each series ending exactly on the tile above it.
 */
class LoanBookService
{
    /** How many months of history the sparklines walk. */
    public const SPARK_MONTHS = 12;

    /**
     * The four headline figures, plus what each one is spread across.
     *
     * `repaid` is deliberately disbursed − outstanding rather than a sum of
     * repayment rows: that is the only definition under which the four tiles add
     * up, and it picks up opening_paid_amount, which has no rows to sum.
     */
    public function summary(Collection $loans): array
    {
        $disbursed = round((float) $loans->sum(fn (Loan $l) => (float) $l->amount), 2);
        $outstanding = round((float) $loans->sum(fn (Loan $l) => $l->outstanding), 2);
        $running = $loans->filter(fn (Loan $l) => ! $l->is_cleared);

        return [
            'disbursed'    => $disbursed,
            'outstanding'  => $outstanding,
            'repaid'       => round($disbursed - $outstanding, 2),
            'loan_count'   => $loans->count(),
            'active_loans' => $running->count(),
            'borrowers'    => $running->pluck('user_id')->unique()->count(),
            'emi_total'    => round((float) $running->sum(fn (Loan $l) => (float) $l->monthly_deduction), 2),
            'repaid_pct'   => $disbursed > 0 ? (int) round(($disbursed - $outstanding) / $disbursed * 100) : 0,
        ];
    }

    /**
     * One sparkline per tile, each a real month-end walk whose LAST point is the
     * figure printed above it — a line that ended somewhere else would be
     * describing a different number than the one being read.
     *
     * Anything older than the window is collapsed into the opening balance rather
     * than dropped, so a loan taken three years ago still weighs on the line.
     */
    public function series(Collection $loans, int $months = self::SPARK_MONTHS): array
    {
        $events = $this->events($loans);
        $window = $this->monthsUpTo($months);

        return [
            'outstanding' => $this->balanceSeries($events, $window),
            'disbursed'   => $this->balanceSeries($this->outflows($events), $window),
            'repaid'      => $this->balanceSeries($this->inflows($events), $window),
            // Heads, not taka: a total says nothing about how many people it is
            // spread across, which is the whole point of the Active Loans tile.
            'active'      => $this->headSeries($events, $window),
        ];
    }

    /**
     * The book folded down to one row per person — what they took, what has come
     * back, what is still owed, and how the money came back.
     *
     * Only people still carrying a balance appear: a cleared borrower belongs in
     * the register, which keeps every loan ever taken.
     */
    public function byEmployee(Collection $loans): Collection
    {
        return $loans
            ->groupBy('user_id')
            ->map(function (Collection $theirs) {
                $due = round((float) $theirs->sum(fn (Loan $l) => $l->outstanding), 2);
                $first = $theirs->first();

                $via = $theirs->reduce(function (array $carry, Loan $l) {
                    $split = $l->repaidByMethod();
                    $carry['salary'] += $split['salary'];
                    // Money repaid before the rows existed cannot be told apart
                    // by method; it is counted as cash so the split still adds up
                    // to what was actually paid back.
                    $carry['cash'] += $split['cash'] + (float) $l->opening_paid_amount;

                    return $carry;
                }, ['salary' => 0.0, 'cash' => 0.0]);

                return [
                    'user'       => $first->user,
                    'user_id'    => $first->user_id,
                    'loans'      => $theirs->count(),
                    'latest'     => $theirs->max('start_date'),
                    'taken'      => round((float) $theirs->sum(fn (Loan $l) => (float) $l->amount), 2),
                    'paid'       => round((float) $theirs->sum(fn (Loan $l) => $l->paid_amount), 2),
                    'due'        => $due,
                    'via_salary' => round($via['salary'], 2),
                    'via_cash'   => round($via['cash'], 2),
                    'emi'        => round((float) $theirs->filter(fn (Loan $l) => ! $l->is_cleared)
                        ->sum(fn (Loan $l) => (float) $l->monthly_deduction), 2),
                ];
            })
            ->filter(fn (array $row) => $row['due'] > 0)
            ->sortByDesc('due')
            ->values();
    }

    /**
     * Where each loan stood at each of its own movements — keyed by transaction id.
     *
     * A repayment row answers "and what was left after that one?", which is the
     * question the trail exists to answer and which the amount column alone can
     * never reach. A disbursement carries the loan's figures as they stand today
     * instead: there is no "after" for the movement that created the debt.
     */
    public function runningBalances(Collection $loans): array
    {
        $out = [];

        foreach ($loans as $loan) {
            $principal = (float) $loan->amount;
            $paid = (float) $loan->opening_paid_amount;

            foreach ($loan->transactions->sortBy([['date', 'asc'], ['id', 'asc']]) as $txn) {
                if ($txn->type === 'repay') {
                    $paid = round($paid + (float) $txn->amount, 2);

                    $out[$txn->id] = [
                        'paid'  => $paid,
                        'due'   => max(0, round($principal - $paid, 2)),
                        'loan'  => $loan,
                    ];

                    continue;
                }

                $out[$txn->id] = [
                    'paid' => $loan->paid_amount,
                    'due'  => $loan->outstanding,
                    'loan' => $loan,
                ];
            }
        }

        return $out;
    }

    /* ========================================================= what exports */

    /**
     * One of the three tables, flattened to a sheet the spreadsheet and the PDF
     * both render from.
     *
     * Money cells come out as raw floats, not as "৳ 1,234". A spreadsheet whose
     * amount column is text cannot be summed, sorted or pivoted, which is most of
     * why anyone exports one; the PDF formats them on the way out instead. Each
     * heading carries its own alignment and whether it is money, so neither
     * renderer has to guess.
     *
     * Totals mirror the feet on screen: a float where the column genuinely sums,
     * a string where the honest answer is a note rather than a number, and null
     * where there is nothing to say.
     */
    public function sheet(string $table, Collection $loans, bool $withCompany = true): array
    {
        $sheet = match ($table) {
            'employees'    => $this->employeeSheet($loans, $withCompany),
            'register'     => $this->registerSheet($loans, $withCompany),
            'transactions' => $this->transactionSheet($loans, $withCompany),
            default        => throw new \InvalidArgumentException('Unknown loan table: ' . $table),
        };

        // The unit is stated once in the heading rather than glued to every cell,
        // the way the party statement's export heads its "Debit (৳)" column — a
        // cell prefixed with a currency sign stops being a number you can add up.
        foreach ($sheet['headings'] as $i => $heading) {
            if (! empty($heading['money'])) {
                $sheet['headings'][$i]['label'] .= ' (৳)';
            }
        }

        return $sheet;
    }

    /** The three sheets an export can ask for. */
    public static function tables(): array
    {
        return ['employees', 'register', 'transactions'];
    }

    private function employeeSheet(Collection $loans, bool $withCompany): array
    {
        $rows = $this->byEmployee($loans);

        return [
            'key'      => 'employees',
            'title'    => 'Employees with loans',
            'subtitle' => 'taken · paid · still due, per person',
            'filename' => 'staff-loans',
            'headings' => array_values(array_filter([
                ['label' => 'Employee', 'money' => false],
                ['label' => 'Employee ID', 'money' => false],
                $withCompany ? ['label' => 'Company', 'money' => false] : null,
                ['label' => 'Loans', 'money' => false, 'align' => 'right'],
                ['label' => 'Latest loan', 'money' => false],
                ['label' => 'Loan taken', 'money' => true],
                ['label' => 'Paid so far', 'money' => true],
                ['label' => 'Still due', 'money' => true],
                ['label' => 'Repaid via', 'money' => false],
                ['label' => 'Monthly EMI', 'money' => true],
            ])),
            'rows' => $rows->map(fn (array $r) => array_values(array_filter([
                $r['user']?->name ?? 'Employee #' . $r['user_id'],
                $r['user']?->employee_id_no ?: '—',
                $withCompany ? $this->companyName($r['user']?->company) : null,
                $r['loans'],
                $r['latest'] ? Carbon::parse($r['latest'])->format('d M Y') : '—',
                $r['taken'],
                $r['paid'],
                $r['due'],
                $this->viaText($r['via_salary'], $r['via_cash']),
                $r['emi'],
            ], fn ($v) => $v !== null)))->all(),
            'totals' => array_values(array_filter([
                $rows->count() . ' ' . Str::plural('person', $rows->count()),
                null,
                $withCompany ? null : false,
                null,
                null,
                round($rows->sum('taken'), 2),
                round($rows->sum('paid'), 2),
                round($rows->sum('due'), 2),
                'salary ' . number_format($rows->sum('via_salary')) . ' · cash ' . number_format($rows->sum('via_cash')),
                round($rows->sum('emi'), 2),
            ], fn ($v) => $v !== false)),
        ];
    }

    private function registerSheet(Collection $loans, bool $withCompany): array
    {
        $open = $loans->filter(fn (Loan $l) => ! $l->is_cleared)->count();

        return [
            'key'      => 'register',
            'title'    => 'Loan register',
            'subtitle' => 'every loan ever taken — taken on · taken · paid till now · still due',
            'filename' => 'loan-register',
            'headings' => array_values(array_filter([
                ['label' => 'Employee', 'money' => false],
                ['label' => 'Employee ID', 'money' => false],
                $withCompany ? ['label' => 'Company', 'money' => false] : null,
                ['label' => 'Taken on', 'money' => false],
                ['label' => 'EMI plan', 'money' => false],
                ['label' => 'Loan taken', 'money' => true],
                ['label' => 'Paid till now', 'money' => true],
                ['label' => 'Paid %', 'money' => false, 'align' => 'right'],
                ['label' => 'Last payment', 'money' => false],
                ['label' => 'Still due', 'money' => true],
                ['label' => 'Repaid via', 'money' => false],
                ['label' => 'Status', 'money' => false],
            ])),
            'rows' => $loans->map(function (Loan $loan) use ($withCompany) {
                $via = $loan->repaidByMethod();

                return array_values(array_filter([
                    $loan->user?->name ?? 'Employee #' . $loan->user_id,
                    $loan->user?->employee_id_no ?: '—',
                    $withCompany ? $this->companyName($loan->user?->company) : null,
                    $loan->start_date ? Carbon::parse($loan->start_date)->format('d M Y') : '—',
                    $loan->emi_months ? $loan->emi_months . '-month EMI plan' : 'no EMI plan',
                    (float) $loan->amount,
                    $loan->paid_amount,
                    $loan->progress_pct . '%',
                    $loan->last_paid_on ? Carbon::parse($loan->last_paid_on)->format('d M Y') : 'none yet',
                    $loan->outstanding,
                    $this->viaText($via['salary'], $via['cash'] + (float) $loan->opening_paid_amount),
                    $loan->is_cleared
                        ? 'Cleared' . ($loan->cleared_on ? ' ' . Carbon::parse($loan->cleared_on)->format('d M Y') : '')
                        : 'Running',
                ], fn ($v) => $v !== null));
            })->all(),
            'totals' => array_values(array_filter([
                $loans->count() . ' ' . Str::plural('loan', $loans->count()),
                null,
                $withCompany ? null : false,
                null,
                null,
                round($loans->sum(fn (Loan $l) => (float) $l->amount), 2),
                round($loans->sum(fn (Loan $l) => $l->paid_amount), 2),
                null,
                null,
                round($loans->sum(fn (Loan $l) => $l->outstanding), 2),
                'salary ' . number_format($loans->sum(fn (Loan $l) => $l->repaidByMethod()['salary']))
                    . ' · cash ' . number_format($loans->sum(fn (Loan $l) => $l->repaidByMethod()['cash'] + (float) $l->opening_paid_amount)),
                // Running or cleared has no total; how many are still alive does.
                $open . ' running · ' . ($loans->count() - $open) . ' cleared',
            ], fn ($v) => $v !== false)),
        ];
    }

    private function transactionSheet(Collection $loans, bool $withCompany): array
    {
        $balances = $this->runningBalances($loans);

        $txns = $loans
            ->flatMap(fn (Loan $l) => $l->transactions)
            ->sortByDesc(fn ($t) => [Carbon::parse($t->date)->timestamp, $t->id])
            ->values();

        $lent = $txns->where('type', 'disburse')->sum(fn ($t) => (float) $t->amount);
        $back = $txns->where('type', 'repay')->sum(fn ($t) => (float) $t->amount);

        return [
            'key'      => 'transactions',
            'title'    => 'Loan transactions',
            'subtitle' => 'every movement on the book — money lent out and money coming back',
            'filename' => 'loan-transactions',
            'headings' => array_values(array_filter([
                ['label' => 'Date', 'money' => false],
                ['label' => 'Employee', 'money' => false],
                ['label' => 'Employee ID', 'money' => false],
                $withCompany ? ['label' => 'Company', 'money' => false] : null,
                ['label' => 'Type', 'money' => false],
                ['label' => 'Note', 'money' => false],
                ['label' => 'Method', 'money' => false],
                ['label' => 'The loan', 'money' => true],
                ['label' => 'Loan taken on', 'money' => false],
                ['label' => 'Paid till then', 'money' => true],
                ['label' => 'Due after', 'money' => true],
                ['label' => 'Amount', 'money' => true],
            ])),
            'rows' => $txns->map(function ($txn) use ($balances, $withCompany) {
                $at = $balances[$txn->id] ?? null;
                $loan = $at['loan'] ?? null;

                return array_values(array_filter([
                    Carbon::parse($txn->date)->format('d M Y'),
                    $txn->user?->name ?? 'Employee #' . $txn->user_id,
                    $txn->user?->employee_id_no ?: '—',
                    $withCompany ? $this->companyName($txn->user?->company) : null,
                    $txn->type === 'repay' ? 'loan-repay' : 'loan',
                    $txn->note ?: '—',
                    $txn->method === 'salary' ? 'Salary deduction' : ($txn->bank?->name ?: 'Cash / bank'),
                    $loan ? (float) $loan->amount : 0.0,
                    $loan && $loan->start_date ? Carbon::parse($loan->start_date)->format('d M Y') : '—',
                    $at['paid'] ?? 0.0,
                    $at['due'] ?? 0.0,
                    (float) $txn->amount,
                ], fn ($v) => $v !== null));
            })->all(),
            /* The one column where a single total would be a LIE: these rows run
             * in both directions, so summing Amount nets a disbursement against a
             * repayment and calls the result "amount". It foots as both directions
             * plus the net. "Paid till then" and "Due after" are balances at a
             * moment in time — adding them would produce a figure that never was. */
            'totals' => array_values(array_filter([
                $txns->count() . ' ' . Str::plural('transaction', $txns->count()),
                null,
                null,
                $withCompany ? null : false,
                null,
                null,
                null,
                null,
                null,
                // Under "Due after", the same column it foots on screen.
                '',
                'balances, not sums',
                number_format($lent - $back) . ' net (' . number_format($lent) . ' lent · ' . number_format($back) . ' repaid)',
            ], fn ($v) => $v !== false)),
        ];
    }

    private function companyName($company): string
    {
        return $company ? ($company->short_name ?: $company->name) : '—';
    }

    private function viaText(float $salary, float $cash): string
    {
        if ($salary <= 0 && $cash <= 0) {
            return 'nothing repaid yet';
        }

        return collect([
            $salary > 0 ? 'salary deduction ' . number_format($salary) : null,
            $cash > 0 ? 'cash / bank ' . number_format($cash) : null,
        ])->filter()->implode(' · ');
    }

    /* ===================================================== the movement list */

    /**
     * Every dated thing that moves the book: money out is positive, money back is
     * negative. One list, so a tile and the line under it are read off the same
     * events.
     */
    private function events(Collection $loans): array
    {
        $out = [];

        foreach ($loans as $loan) {
            foreach ($loan->transactions as $txn) {
                $ym = Carbon::parse($txn->date)->format('Y-m');
                $out[] = [
                    'ym'      => $ym,
                    'user_id' => $loan->user_id,
                    'delta'   => $txn->type === 'repay' ? -(float) $txn->amount : (float) $txn->amount,
                ];
            }

            $opening = (float) $loan->opening_paid_amount;

            if ($opening > 0 && $loan->start_date) {
                $out[] = [
                    'ym'      => Carbon::parse($loan->start_date)->format('Y-m'),
                    'user_id' => $loan->user_id,
                    'delta'   => -$opening,
                ];
            }
        }

        return $out;
    }

    private function outflows(array $events): array
    {
        return array_values(array_filter($events, fn (array $e) => $e['delta'] > 0));
    }

    /** Money coming back, sign-flipped so a "repaid to date" line climbs. */
    private function inflows(array $events): array
    {
        return array_values(array_map(
            fn (array $e) => ['ym' => $e['ym'], 'user_id' => $e['user_id'], 'delta' => -$e['delta']],
            array_filter($events, fn (array $e) => $e['delta'] < 0)
        ));
    }

    /** The last n months, oldest first, as Y-m. */
    private function monthsUpTo(int $n): array
    {
        $end = Carbon::now()->startOfMonth();

        return collect(range($n - 1, 0))
            ->map(fn (int $back) => $end->copy()->subMonths($back)->format('Y-m'))
            ->all();
    }

    /** A running balance walked to the end of each month in the window. */
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
}
