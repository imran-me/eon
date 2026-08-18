<?php
declare(strict_types=1);

/* ============================================================
   Insight — the numbers the ERP does not print.

   Analytics covers what the ERP already reports. This class adds
   what a director asks for and the screens cannot show: aging,
   runway, a forecast, anomalies, per-company and per-department
   comparisons, party balances taken from the ledger rather than
   from the payment schedules.

   Every method returns plain arrays. No language, no formatting —
   Answer decides how it sounds.
   ============================================================ */
final class Insight
{
    private Analytics $A;
    private array $D;
    private string $today;

    public function __construct(Analytics $A)
    {
        $this->A = $A;
        $this->D = $A->dataset();
        $this->today = $A->today();
    }

    private function f($v): float { return (float) ($v ?? 0); }

    /* ---------------- money in and out ---------------- */

    /** aging, taken from the buckets Analytics already builds off the schedules */
    public function aging(string $type): array
    {
        $s = $this->A->schedules($type);
        $buckets = [];
        $worst = 'current';
        foreach (($s['buckets'] ?? []) as $b) {
            $name = (string) ($b['bucket'] ?? '');
            $buckets[$name] = ['amount' => $this->f($b['amount'] ?? 0), 'count' => (int) ($b['count'] ?? 0)];
            if ($name !== 'current' && $this->f($b['amount'] ?? 0) > 0) $worst = $name;
        }
        return ['type' => $type, 'buckets' => $buckets,
                'total' => $this->f($s['total'] ?? 0),
                'overdue' => $this->f($s['overdue_total'] ?? 0),
                'overdue_count' => (int) ($s['overdue_count'] ?? 0),
                'due_in_7_days' => $this->f($s['due_in_7_days'] ?? 0),
                'by_party' => $s['by_party'] ?? [],
                'oldest' => $s['overdue'][0] ?? null,
                // what the party accounts actually support, and where they contradict the invoices
                'ledger_receivable' => $this->f($s['ledger_receivable'] ?? 0),
                'advances_held' => $this->f($s['advances_held'] ?? 0),
                'reconciliation' => $s['reconciliation'] ?? [],
                'worst_bucket' => $worst];
    }

    /** who owes us / whom we owe, taken from the general ledger by party */
    public function partyBalances(string $side, int $limit = 10): array
    {
        $wantAsset = ($side === 'receive');
        $prefix = $wantAsset ? '13' : '21';

        // journal items are nested inside their entry, and each one carries its account code
        $by = [];
        $touched = 0;
        foreach ($this->A->rows('journal_entries') as $e) {
            foreach ((array) ($e['items'] ?? []) as $it) {
                $code = (string) ($it['account_code'] ?? '');
                if (strpos($code, $prefix) !== 0) continue;
                $touched++;
                $ptype = (string) ($it['party_type'] ?? '');
                $pid = $it['party_id'] ?? null;
                if ($ptype === '' && $pid === null) {
                    $key = 'unattributed';
                    $name = $wantAsset ? 'not tied to a customer' : 'not tied to a supplier';
                } else {
                    $key = $ptype . ':' . $pid;
                    $name = $this->partyName($ptype, $pid);
                }
                if (!isset($by[$key])) $by[$key] = ['party_type' => $ptype, 'party_id' => $pid, 'party_name' => $name, 'debit' => 0.0, 'credit' => 0.0];
                $by[$key]['debit'] += $this->f($it['debit'] ?? 0);
                $by[$key]['credit'] += $this->f($it['credit'] ?? 0);
            }
        }

        $out = [];
        foreach ($by as $row) {
            $bal = $wantAsset ? ($row['debit'] - $row['credit']) : ($row['credit'] - $row['debit']);
            if ($bal <= 0.5) continue;
            $row['balance'] = $bal;
            $out[] = $row;
        }
        usort($out, fn($a, $b) => $b['balance'] <=> $a['balance']);
        return ['parties' => array_slice($out, 0, $limit), 'total' => array_sum(array_column($out, 'balance')),
                'count' => count($out), 'postings' => $touched];
    }

    private function partyName(string $type, $id): string
    {
        if ($id === null) return 'Unnamed';
        $map = ['customer' => 'customers', 'supplier' => 'suppliers', 'employee' => 'employees',
                'agent' => 'customers', 'vendor' => 'suppliers'];
        $table = $map[strtolower($type)] ?? null;
        if ($table) {
            foreach ($this->D[$table] ?? [] as $r) {
                if ((int) ($r['id'] ?? 0) === (int) $id) return (string) ($r['name'] ?? $r['party_name'] ?? 'Unnamed');
            }
        }
        return ucfirst($type) . ' #' . $id;
    }

    /** average monthly outgoing, and how many months the cash covers */
    public function runway(): array
    {
        $cash = $this->f($this->A->cash()['total'] ?? 0);
        $months = [];
        foreach ($this->A->rows('expenses') as $e) {
            if (strtolower((string) ($e['approval_status'] ?? 'approved')) === 'rejected') continue;
            $k = substr((string) ($e['expense_date'] ?? ''), 0, 7);
            if ($k === '') continue;
            $months[$k] = ($months[$k] ?? 0) + $this->f($e['amount'] ?? 0);
        }
        // payroll is the other half of the burn
        $pay = [];
        foreach ($this->A->rows('payroll') as $p) {
            $k = (string) ($p['month_key'] ?? '');
            if ($k === '') continue;
            $pay[$k] = ($pay[$k] ?? 0) + $this->f($p['net_salary'] ?? 0);
        }
        $keys = array_unique(array_merge(array_keys($months), array_keys($pay)));
        sort($keys);
        $keys = array_slice($keys, -3);                      // last three months
        $burnTotal = 0.0;
        foreach ($keys as $k) $burnTotal += ($months[$k] ?? 0) + ($pay[$k] ?? 0);
        $burn = $keys ? $burnTotal / count($keys) : 0.0;

        return ['cash' => $cash, 'monthly_burn' => $burn, 'months_covered' => $burn > 0 ? round($cash / $burn, 1) : null,
                'basis_months' => count($keys), 'payroll_share' => $burn > 0 ? round(array_sum(array_intersect_key($pay, array_flip($keys))) / max(1, count($keys)) / $burn * 100) : 0];
    }

    /* ---------------- looking forward ---------------- */

    /** straight-line projection off the revenue trend Analytics already builds */
    public function forecast(int $monthsAhead = 3): array
    {
        $trend = $this->A->revenueTrend(6);
        $series = [];
        foreach ($trend as $t) {
            $series[] = ['month' => (string) ($t['month'] ?? ''), 'income' => $this->f($t['income'] ?? 0),
                         'expense' => $this->f($t['expense'] ?? ($t['cost'] ?? 0)), 'profit' => $this->f($t['profit'] ?? 0)];
        }
        $n = count($series);
        if ($n < 2) return ['ok' => false, 'reason' => 'not enough months', 'months' => $n, 'series' => $series];

        $proj = [];
        foreach (['income', 'expense', 'profit'] as $field) {
            $proj[$field] = $this->line(array_column($series, $field), $monthsAhead);
        }
        $lastMonth = $series[$n - 1]['month'];
        $labels = [];
        $t = strtotime($lastMonth . '-01');
        for ($i = 1; $i <= $monthsAhead; $i++) $labels[] = date('Y-m', strtotime("+$i month", $t));

        $slope = $this->slope(array_column($series, 'income'));
        return ['ok' => true, 'months' => $n, 'series' => $series, 'labels' => $labels,
                'income' => $proj['income'], 'expense' => $proj['expense'], 'profit' => $proj['profit'],
                'income_total' => array_sum($proj['income']), 'profit_total' => array_sum($proj['profit']),
                'direction' => $slope > 0 ? 'up' : ($slope < 0 ? 'down' : 'flat'),
                'slope_per_month' => $slope,
                'confidence' => $n >= 5 ? 'reasonable' : 'thin'];
    }

    /** least-squares slope */
    private function slope(array $y): float
    {
        $n = count($y);
        if ($n < 2) return 0.0;
        $sx = $sy = $sxy = $sxx = 0.0;
        foreach ($y as $i => $v) { $sx += $i; $sy += $v; $sxy += $i * $v; $sxx += $i * $i; }
        $d = $n * $sxx - $sx * $sx;
        return $d == 0.0 ? 0.0 : ($n * $sxy - $sx * $sy) / $d;
    }

    /** project the line forward n steps */
    private function line(array $y, int $ahead): array
    {
        $n = count($y);
        $m = $this->slope($y);
        $mean = $n ? array_sum($y) / $n : 0.0;
        $meanX = ($n - 1) / 2;
        $c = $mean - $m * $meanX;
        $out = [];
        for ($i = 0; $i < $ahead; $i++) $out[] = round($c + $m * ($n + $i), 2);
        return $out;
    }

    /* ---------------- things that look wrong ---------------- */

    /** duplicates, spikes, stale approvals, negative balances, broken scope */
    public function anomalies(): array
    {
        $out = [];

        // 1. the same expense twice
        $seen = [];
        foreach ($this->A->rows('expenses') as $e) {
            $key = strtolower(trim((string) ($e['title'] ?? ''))) . '|' . round($this->f($e['amount'] ?? 0)) . '|' . substr((string) ($e['expense_date'] ?? ''), 0, 10);
            if (isset($seen[$key])) {
                $out[] = ['kind' => 'duplicate_expense', 'severity' => 'high',
                          'title' => (string) ($e['title'] ?? ''), 'amount' => $this->f($e['amount'] ?? 0),
                          'date' => (string) ($e['expense_date'] ?? ''), 'ids' => [$seen[$key], $e['id'] ?? null]];
            }
            $seen[$key] = $e['id'] ?? null;
        }

        // 2. a category that jumped against its own three-month average
        $byCatMonth = [];
        foreach ($this->A->rows('expenses') as $e) {
            $cat = (string) ($e['category'] ?? 'Uncategorised');
            $mk = substr((string) ($e['expense_date'] ?? ''), 0, 7);
            if ($mk === '') continue;
            $byCatMonth[$cat][$mk] = ($byCatMonth[$cat][$mk] ?? 0) + $this->f($e['amount'] ?? 0);
        }
        $thisMk = substr($this->today, 0, 7);
        foreach ($byCatMonth as $cat => $ms) {
            if (!isset($ms[$thisMk])) continue;
            $past = $ms; unset($past[$thisMk]);
            if (count($past) < 2) continue;
            $avg = array_sum($past) / count($past);
            if ($avg > 0 && $ms[$thisMk] > $avg * 2 && $ms[$thisMk] - $avg > 5000) {
                $out[] = ['kind' => 'expense_spike', 'severity' => 'medium', 'category' => $cat,
                          'amount' => $ms[$thisMk], 'average' => round($avg, 2),
                          'times' => round($ms[$thisMk] / $avg, 1)];
            }
        }

        // 3. approvals that have been sitting too long
        $ap = $this->A->approvals();
        foreach (($ap['items'] ?? []) as $it) {
            $d = strtotime((string) ($it['date'] ?? $it['created_at'] ?? ''));
            if (!$d) continue;
            $age = (int) floor((strtotime($this->today) - $d) / 86400);
            if ($age >= 14) {
                $out[] = ['kind' => 'stale_approval', 'severity' => $age >= 30 ? 'high' : 'medium',
                          'title' => (string) ($it['title'] ?? ''), 'days' => $age,
                          'amount' => $this->f($it['amount'] ?? 0)];
            }
        }

        // 4. a cash or bank account gone negative
        foreach (($this->A->cash()['accounts'] ?? []) as $a) {
            if ($this->f($a['balance'] ?? 0) < 0) {
                $out[] = ['kind' => 'negative_balance', 'severity' => 'high',
                          'title' => (string) ($a['name'] ?? ''), 'amount' => $this->f($a['balance'] ?? 0)];
            }
        }

        // 5. the trial balance not balancing — always means a scope bug
        $tb = $this->A->trialBalance();
        if (!($tb['balanced'] ?? true)) {
            $out[] = ['kind' => 'trial_balance_off', 'severity' => 'high',
                      'amount' => $this->f($tb['total_debit'] ?? 0) - $this->f($tb['total_credit'] ?? 0)];
        }

        usort($out, fn($a, $b) => ['high' => 0, 'medium' => 1, 'low' => 2][$a['severity']] <=> ['high' => 0, 'medium' => 1, 'low' => 2][$b['severity']]);
        return ['count' => count($out), 'items' => $out];
    }

    /* ---------------- comparisons ---------------- */

    /** every company side by side for the current month */
    public function byCompany(): array
    {
        $rows = [];
        foreach ($this->D['companies'] ?? [] as $co) {
            $id = (int) $co['id'];
            $A = new Analytics($this->D, $id);
            $mk = substr($this->today, 0, 7);
            $pl = $A->profitAndLoss($mk . '-01', $this->today);
            $rows[] = [
                'id' => $id, 'name' => (string) $co['name'],
                'income' => $this->f($pl['income'] ?? 0),
                'expense' => $this->f($pl['direct_cost'] ?? 0) + $this->f($pl['opex'] ?? 0),
                'profit' => $this->f($pl['net_profit'] ?? 0),
                'cash' => $this->f($A->cash()['total'] ?? 0),
                'headcount' => count(array_filter($A->rows('employees'), fn($e) => ($e['status'] ?? 'active') === 'active')),
            ];
        }
        usort($rows, fn($a, $b) => $b['profit'] <=> $a['profit']);
        return ['month' => substr($this->today, 0, 7), 'companies' => $rows,
                'best' => $rows[0] ?? null, 'worst' => $rows ? end($rows) : null];
    }

    /** spend per category this month, biggest first */
    public function expenseByCategory(?string $month = null, int $limit = 8): array
    {
        $mk = $month ?: substr($this->today, 0, 7);
        $cats = [];
        foreach ($this->A->rows('expenses') as $e) {
            if (substr((string) ($e['expense_date'] ?? ''), 0, 7) !== $mk) continue;
            if (strtolower((string) ($e['approval_status'] ?? 'approved')) === 'rejected') continue;
            $c = (string) ($e['category'] ?? 'Uncategorised');
            $cats[$c] = ($cats[$c] ?? 0) + $this->f($e['amount'] ?? 0);
        }
        arsort($cats);
        $total = array_sum($cats);
        $rows = [];
        foreach (array_slice($cats, 0, $limit, true) as $c => $v) {
            $rows[] = ['category' => $c, 'amount' => $v, 'pct' => $total > 0 ? round($v / $total * 100, 1) : 0.0];
        }
        return ['month' => $mk, 'total' => $total, 'categories' => $rows, 'category_count' => count($cats)];
    }

    /** staff split by department, with the salary each department carries */
    public function byDepartment(): array
    {
        $rows = [];
        foreach ($this->A->rows('employees') as $e) {
            if (($e['status'] ?? 'active') !== 'active') continue;
            $d = (string) ($e['department'] ?? 'Unassigned');
            if (!isset($rows[$d])) $rows[$d] = ['department' => $d, 'headcount' => 0, 'salary' => 0.0];
            $rows[$d]['headcount']++;
            $rows[$d]['salary'] += $this->f($e['salary'] ?? 0);
        }
        $rows = array_values($rows);
        usort($rows, fn($a, $b) => $b['headcount'] <=> $a['headcount']);
        return ['departments' => $rows, 'total_headcount' => array_sum(array_column($rows, 'headcount')),
                'total_salary' => array_sum(array_column($rows, 'salary'))];
    }

    /* ---------------- odds and ends the screens hold ---------------- */

    public function headcount(): array
    {
        $emps = $this->A->rows('employees');
        $active = array_filter($emps, fn($e) => ($e['status'] ?? 'active') === 'active');
        $salary = 0.0;
        foreach ($active as $e) $salary += $this->f($e['salary'] ?? 0);
        $recent = [];
        foreach ($active as $e) {
            $j = (string) ($e['joining_date'] ?? '');
            if ($j !== '' && strtotime($j) > strtotime('-90 days', strtotime($this->today))) $recent[] = ['name' => $e['name'] ?? '', 'joined' => $j];
        }
        return ['active' => count($active), 'total' => count($emps), 'monthly_salary' => $salary,
                'recent_joiners' => $recent, 'by_department' => $this->byDepartment()['departments']];
    }

    public function upcomingHolidays(int $limit = 3): array
    {
        $out = [];
        foreach ($this->A->rows('holidays', true) as $h) {
            $d = (string) ($h['start_date'] ?? $h['date'] ?? '');
            $end = (string) ($h['end_date'] ?? $d);
            if ($d === '') continue;
            $past = strtotime($end ?: $d) < strtotime($this->today);
            $out[] = ['name' => (string) ($h['name'] ?? $h['title'] ?? 'Holiday'), 'date' => $d, 'end' => $end,
                      'past' => $past,
                      'days' => max(1, (int) round((strtotime($end ?: $d) - strtotime($d)) / 86400) + 1),
                      'days_away' => (int) floor((strtotime($d) - strtotime($this->today)) / 86400)];
        }
        usort($out, fn($a, $b) => strcmp($a['date'], $b['date']));
        $future = array_values(array_filter($out, fn($h) => !$h['past']));
        return ['next' => $future[0] ?? null, 'upcoming' => array_slice($future, 0, $limit),
                'count' => count($future), 'total_in_calendar' => count($out),
                'last_recorded' => $out ? end($out) : null];
    }

    public function loans(): array
    {
        $rows = [];
        $out = 0.0;
        foreach ($this->A->rows('loans') as $l) {
            $bal = $this->f($l['remaining_amount'] ?? ($this->f($l['amount'] ?? 0) - $this->f($l['paid_amount'] ?? 0)));
            if ($bal <= 0) continue;
            $out += $bal;
            $emi = $this->f($l['monthly_deduction'] ?? $l['installment_amount'] ?? 0);
            $rows[] = ['name' => $this->empName($l['user_id'] ?? null),
                       'amount' => $this->f($l['amount'] ?? 0), 'balance' => $bal, 'emi' => $emi,
                       'status' => (string) ($l['status'] ?? ''),
                       'months_left' => $emi > 0 ? (int) ceil($bal / $emi) : null];
        }
        usort($rows, fn($a, $b) => $b['balance'] <=> $a['balance']);
        return ['count' => count($rows), 'outstanding' => $out,
                'monthly_recovery' => array_sum(array_column($rows, 'emi')), 'rows' => $rows];
    }

    public function advances(): array
    {
        $rows = [];
        $out = 0.0;
        foreach ($this->A->rows('advance_salaries') as $a) {
            $status = strtolower((string) ($a['status'] ?? ''));
            $paid = strtolower((string) ($a['payment_status'] ?? ''));
            $amt = $this->f($a['amount'] ?? 0);
            // outstanding = handed over but not yet recovered through a payslip
            $open = ($status === 'approved' && $paid === 'paid');
            if ($open) $out += $amt;
            $rows[] = ['name' => $this->empName($a['user_id'] ?? null), 'amount' => $amt,
                       'month' => (string) ($a['month'] ?? ''), 'status' => $status,
                       'payment_status' => $paid, 'outstanding' => $open ? $amt : 0.0];
        }
        usort($rows, fn($a, $b) => $b['amount'] <=> $a['amount']);
        $pending = array_values(array_filter($rows, fn($r) => $r['status'] === 'pending'));
        return ['count' => count($rows), 'outstanding' => $out, 'pending' => count($pending),
                'pending_rows' => $pending, 'rows' => $rows];
    }

    public function requests(): array
    {
        $rows = [];
        foreach ($this->A->rows('employee_requests') as $r) {
            $rows[] = ['name' => $this->empName($r['user_id'] ?? null),
                       'type' => str_replace('_', ' ', (string) ($r['request_type'] ?? $r['category'] ?? 'request')),
                       'status' => strtolower((string) ($r['status'] ?? 'pending')),
                       'amount' => $this->f($r['amount'] ?? 0),
                       'date' => (string) ($r['created_at'] ?? ''),
                       'deadline' => (string) ($r['deadline'] ?? '')];
        }
        usort($rows, fn($a, $b) => strcmp($b['date'], $a['date']));
        $pending = array_values(array_filter($rows, fn($r) => in_array($r['status'], ['pending', 'under_review', 'under review'], true)));
        return ['count' => count($rows), 'pending' => count($pending),
                'pending_amount' => array_sum(array_column($pending, 'amount')),
                'pending_rows' => $pending, 'rows' => $rows];
    }

    public function parties(string $which, int $limit = 8): array
    {
        $table = $which === 'supplier' ? 'suppliers' : 'customers';
        $rows = [];
        foreach ($this->A->rows($table, true) as $r) {
            $rows[] = ['id' => (int) ($r['id'] ?? 0), 'name' => (string) ($r['name'] ?? 'Unnamed'),
                       'phone' => (string) ($r['phone'] ?? ''), 'company_id' => $r['company_id'] ?? null];
        }
        $bal = $this->partyBalances($which === 'supplier' ? 'pay' : 'receive', 50);
        $byName = [];
        foreach ($bal['parties'] as $p) $byName[strtolower($p['party_name'])] = $p['balance'];
        foreach ($rows as &$r) $r['balance'] = $byName[strtolower($r['name'])] ?? 0.0;
        unset($r);
        usort($rows, fn($a, $b) => $b['balance'] <=> $a['balance']);
        return ['count' => count($rows), 'rows' => array_slice($rows, 0, $limit), 'with_balance' => count(array_filter($rows, fn($r) => $r['balance'] > 0))];
    }

    private function empName($id): string
    {
        foreach ($this->D['employees'] ?? [] as $e) if ((int) ($e['id'] ?? 0) === (int) $id) return (string) ($e['name'] ?? '');
        return 'Employee #' . $id;
    }

    /** one person's payslip history, newest first */
    public function payslips(int $userId, int $limit = 6): array
    {
        $rows = [];
        foreach ($this->D['payroll'] ?? [] as $p) {
            if ((int) ($p['user_id'] ?? 0) !== $userId) continue;
            $rows[] = [
                'month' => (string) ($p['month_key'] ?? ''),
                'gross' => $this->f($p['gross_salary'] ?? 0),
                'net' => $this->f($p['net_salary'] ?? 0),
                'absent' => $this->f($p['absent_deduction'] ?? 0),
                'late' => $this->f($p['late_deduction'] ?? 0),
                'early' => $this->f($p['early_leave_deduction'] ?? 0),
                'leave' => $this->f($p['leave_deduction'] ?? 0),
                'loan' => $this->f($p['loan_deduction'] ?? 0),
                'advance' => $this->f($p['advance_salary_deduction'] ?? 0),
                'overtime' => $this->f($p['overtime_salary'] ?? 0),
                'deductions' => $this->f($p['total_deductions'] ?? 0),
                'status' => (string) ($p['status'] ?? ''),
                'paid_on' => (string) ($p['payment_date'] ?? ''),
            ];
        }
        usort($rows, fn($a, $b) => strcmp($b['month'], $a['month']));
        return ['count' => count($rows), 'rows' => array_slice($rows, 0, $limit)];
    }
}
