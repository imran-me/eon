<?php
declare(strict_types=1);

/* ============================================================
   Reason — answering "why", which is a different job from "what".

   "How much profit" is a lookup. "Why is profit down" is an
   argument: you have to decompose the number, find which part
   actually moved, then look inside that part, and keep going
   until you reach something a person can act on.

   Each method returns a chain:
     ['claim' => …, 'evidence' => [ ['fact','detail','weight'], … ],
      'cause' => …, 'action' => …, 'confidence' => …]

   Language never appears here — Answer says it. Facts carry both
   the number and where it came from, so nothing is asserted that
   the ledger cannot back.
   ============================================================ */
final class Reason
{
    private Analytics $A;
    private Insight $I;
    private array $D;
    private string $today;

    public function __construct(Analytics $A)
    {
        $this->A = $A;
        $this->I = new Insight($A);
        $this->D = $A->dataset();
        $this->today = $A->today();
    }

    private function f($v): float { return (float) ($v ?? 0); }

    /** the trend, whichever shape Analytics is returning this week */
    private function series(int $months = 6): array
    {
        $t = $this->A->revenueTrend($months);
        $rows = isset($t['series']) && is_array($t['series']) ? $t['series'] : $t;
        $out = [];
        foreach ((array) $rows as $r) {
            if (!is_array($r) || !isset($r['month'])) continue;
            $income = $this->f($r['income'] ?? 0);
            $direct = $this->f($r['direct'] ?? 0);
            $opex = $this->f($r['opex'] ?? 0);
            $out[] = ['month' => (string) $r['month'], 'income' => $income, 'direct' => $direct,
                      'opex' => $opex, 'net' => array_key_exists('net', $r) ? $this->f($r['net']) : $income - $direct - $opex];
        }
        return $out;
    }

    /** which subject is the boss asking about? */
    public function topicOf(string $norm, ?string $intent): string
    {
        $map = [
            'profit_loss' => 'profit', 'revenue' => 'profit', 'expenses' => 'spend',
            'expense_by_category' => 'spend', 'budget' => 'spend',
            'cash' => 'cash', 'burn_runway' => 'cash', 'bank_accounts' => 'cash',
            'receivables' => 'receivable', 'payables' => 'payable',
            'attendance_today' => 'attendance', 'late_today' => 'attendance', 'chronic_late' => 'attendance',
            'payroll' => 'payroll', 'payroll_unpaid' => 'payroll', 'headcount' => 'payroll',
            'tasks' => 'delivery', 'projects' => 'delivery',
        ];
        foreach (['profit' => ['profit', 'loss', 'margin', 'লাভ', 'লোকসান', 'মুনাফা'],
                  'cash' => ['cash', 'money', 'ক্যাশ', 'নগদ', 'টাকা'],
                  'spend' => ['expense', 'spend', 'cost', 'খরচ', 'ব্যয়'],
                  'receivable' => ['receivable', 'owes us', 'পাওনা'],
                  'payable' => ['payable', 'we owe', 'দেনা'],
                  'attendance' => ['late', 'absent', 'attendance', 'দেরি', 'অনুপস্থিত', 'হাজিরা'],
                  'payroll' => ['payroll', 'salary', 'বেতন'],
                  'delivery' => ['project', 'task', 'প্রকল্প', 'কাজ']] as $topic => $cues) {
            foreach ($cues as $c) {
                if (mb_strpos($norm, $c) !== false) return $topic;
            }
        }
        return $map[$intent ?? ''] ?? 'profit';
    }

    public function explain(string $topic): array
    {
        switch ($topic) {
            case 'cash':       return $this->whyCash();
            case 'spend':      return $this->whySpend();
            case 'receivable': return $this->whyReceivable();
            case 'payable':    return $this->whyPayable();
            case 'attendance': return $this->whyAttendance();
            case 'payroll':    return $this->whyPayroll();
            case 'delivery':   return $this->whyDelivery();
            default:           return $this->whyProfit();
        }
    }

    /* ---------------- profit ---------------- */

    private function whyProfit(): array
    {
        $s = $this->series(6);
        $n = count($s);
        if ($n === 0) return ['claim' => 'no_data', 'evidence' => [], 'confidence' => 'none'];

        $cur = $s[$n - 1];
        $prev = $n >= 2 ? $s[$n - 2] : null;
        $ev = [];

        $ev[] = ['fact' => 'net', 'month' => $cur['month'], 'value' => $cur['net'],
                 'detail' => ['income' => $cur['income'], 'direct' => $cur['direct'], 'opex' => $cur['opex']]];

        // which of the three parts actually moved?
        $mover = null;
        if ($prev !== null) {
            $moves = [
                'income' => $cur['income'] - $prev['income'],
                'direct' => $cur['direct'] - $prev['direct'],
                'opex'   => $cur['opex'] - $prev['opex'],
            ];
            // an income fall and a cost rise both hurt; compare their effect on net
            $effect = ['income' => $moves['income'], 'direct' => -$moves['direct'], 'opex' => -$moves['opex']];
            asort($effect);
            $mover = array_key_first($effect);
            $ev[] = ['fact' => 'mover', 'part' => $mover, 'change' => $moves[$mover],
                     'from' => $prev['month'], 'to' => $cur['month'], 'effect' => $effect[$mover]];
        }

        // the structural point: overheads against income
        if ($cur['income'] > 0 && $cur['opex'] > $cur['income']) {
            $ev[] = ['fact' => 'opex_exceeds_income', 'opex' => $cur['opex'], 'income' => $cur['income'],
                     'ratio' => round($cur['opex'] / max(1.0, $cur['income']), 2)];
        }

        // and inside overheads, what is the biggest head?
        $cat = $this->I->expenseByCategory($cur['month'], 3);
        if (!empty($cat['categories'])) {
            $ev[] = ['fact' => 'top_category', 'category' => $cat['categories'][0]['category'],
                     'amount' => $this->f($cat['categories'][0]['amount']), 'pct' => $this->f($cat['categories'][0]['pct']),
                     'booked_total' => $this->f($cat['total'])];
        }

        // payroll is usually the real overhead — say so if it is
        $pay = $this->A->payroll(null);
        if ($this->f($pay['gross'] ?? 0) > 0 && $cur['opex'] > 0) {
            $share = $this->f($pay['gross']) / $cur['opex'] * 100;
            if ($share >= 25) {
                $ev[] = ['fact' => 'payroll_share', 'gross' => $this->f($pay['gross']),
                         'share' => round($share), 'month' => (string) ($pay['month'] ?? '')];
            }
        }

        // the honest caveat: expenses only post once approved
        $pend = $this->A->approvals();
        if (($pend['count'] ?? 0) > 0) {
            $ev[] = ['fact' => 'unapproved', 'count' => (int) $pend['count'], 'amount' => $this->f($pend['amount'] ?? 0)];
        }

        $cause = $mover ?? ($cur['opex'] > $cur['income'] ? 'opex' : 'income');
        return [
            'claim' => $cur['net'] >= 0 ? 'profit' : 'loss',
            'month' => $cur['month'],
            'net' => $cur['net'],
            'evidence' => $ev,
            'cause' => $cause,
            'confidence' => $n >= 3 ? 'reasonable' : 'thin',
        ];
    }

    /* ---------------- cash ---------------- */

    private function whyCash(): array
    {
        $rw = $this->I->runway();
        $ar = $this->I->aging('receive');
        $ap = $this->I->aging('pay');
        $ev = [];

        $ev[] = ['fact' => 'position', 'cash' => $this->f($rw['cash']), 'burn' => $this->f($rw['monthly_burn']),
                 'months' => $rw['months_covered'], 'payroll_share' => (int) $rw['payroll_share']];

        if ($this->f($ar['total']) > 0) {
            $ev[] = ['fact' => 'money_owed_in', 'total' => $this->f($ar['total']), 'overdue' => $this->f($ar['overdue']),
                     'top' => $ar['by_party'][0]['party_name'] ?? null,
                     'top_amount' => $this->f($ar['by_party'][0]['due'] ?? 0)];
        }
        if ($this->f($ap['overdue']) > 0) {
            $ev[] = ['fact' => 'money_owed_out', 'total' => $this->f($ap['total']), 'overdue' => $this->f($ap['overdue']),
                     'due_7' => $this->f($ap['due_in_7_days'])];
        }
        // the arithmetic that matters: would collecting fix it?
        $gap = $this->f($ap['overdue']) - $this->f($rw['cash']);
        if ($gap > 0) {
            $ev[] = ['fact' => 'shortfall', 'gap' => $gap,
                     'covered_by_receivables' => $this->f($ar['overdue']) >= $gap];
        }

        return [
            'claim' => ($rw['months_covered'] !== null && $rw['months_covered'] < 2) ? 'tight' : 'adequate',
            'evidence' => $ev,
            'cause' => $this->f($ap['overdue']) > $this->f($rw['cash']) ? 'payables' : ($this->f($ar['overdue']) > 0 ? 'collection' : 'burn'),
            'confidence' => 'reasonable',
        ];
    }

    /* ---------------- spend ---------------- */

    private function whySpend(): array
    {
        $mk = substr($this->today, 0, 7);
        $cat = $this->I->expenseByCategory($mk, 5);
        $ev = [];
        $ev[] = ['fact' => 'total', 'month' => $mk, 'amount' => $this->f($cat['total']), 'categories' => (int) $cat['category_count']];
        foreach (array_slice($cat['categories'], 0, 3) as $c) {
            $ev[] = ['fact' => 'category', 'category' => $c['category'], 'amount' => $this->f($c['amount']), 'pct' => $this->f($c['pct'])];
        }
        // is anything abnormal against its own history?
        $an = $this->I->anomalies();
        foreach ($an['items'] as $x) {
            if (($x['kind'] ?? '') === 'expense_spike') {
                $ev[] = ['fact' => 'spike', 'category' => $x['category'], 'times' => $x['times'], 'amount' => $this->f($x['amount'])];
                break;
            }
        }
        // payroll dwarfs booked expenses in this business — say it plainly
        $pay = $this->A->payroll(null);
        if ($this->f($pay['gross'] ?? 0) > $this->f($cat['total'])) {
            $ev[] = ['fact' => 'payroll_dominates', 'gross' => $this->f($pay['gross']), 'expenses' => $this->f($cat['total'])];
        }
        return ['claim' => 'spend', 'evidence' => $ev, 'cause' => 'category', 'confidence' => 'reasonable'];
    }

    /* ---------------- receivable / payable ---------------- */

    private function whyReceivable(): array
    {
        $ar = $this->I->aging('receive');
        $ev = [['fact' => 'position', 'total' => $this->f($ar['total']), 'overdue' => $this->f($ar['overdue']),
                'count' => (int) $ar['overdue_count']]];
        foreach (array_slice($ar['by_party'], 0, 3) as $p) {
            $ev[] = ['fact' => 'party', 'name' => (string) $p['party_name'], 'due' => $this->f($p['due']),
                     'overdue' => $this->f($p['overdue'] ?? 0), 'oldest' => (int) ($p['oldest'] ?? 0)];
        }
        if ($this->f($ar['total']) <= 0) {
            $ev[] = ['fact' => 'no_schedules', 'note' => 'sales are booked without a payment schedule, so nothing shows as receivable'];
        }
        return ['claim' => 'receivable', 'evidence' => $ev,
                'cause' => $this->f($ar['total']) <= 0 ? 'not_recorded' : 'collection', 'confidence' => 'reasonable'];
    }

    private function whyPayable(): array
    {
        $ap = $this->I->aging('pay');
        $ev = [['fact' => 'position', 'total' => $this->f($ap['total']), 'overdue' => $this->f($ap['overdue']),
                'count' => (int) $ap['overdue_count'], 'due_7' => $this->f($ap['due_in_7_days'])]];
        if (!empty($ap['oldest'])) {
            $o = $ap['oldest'];
            $ev[] = ['fact' => 'oldest', 'party' => (string) ($o['party'] ?? ''), 'days' => (int) ($o['days_overdue'] ?? 0),
                     'ref' => (string) ($o['ref'] ?? ''), 'due' => $this->f($o['due'] ?? 0)];
        }
        // salaries inside payables is the fact a director must not miss
        $salary = 0.0;
        foreach (($ap['by_party'] ?? []) as $p) {
            if (($p['party_type'] ?? '') === 'employee') $salary += $this->f($p['due']);
        }
        if ($salary > 0) $ev[] = ['fact' => 'salary_inside', 'amount' => $salary];
        return ['claim' => 'payable', 'evidence' => $ev, 'cause' => $salary > 0 ? 'salary' : 'supplier', 'confidence' => 'reasonable'];
    }

    /* ---------------- people ---------------- */

    private function whyAttendance(): array
    {
        $td = $this->A->attendanceToday();
        $lp = $this->A->latePatterns(30);
        $ev = [['fact' => 'today', 'present' => (int) ($td['present'] ?? 0), 'total' => (int) ($td['total'] ?? 0),
                'absent' => (int) ($td['absent'] ?? 0), 'late' => (int) ($td['late'] ?? 0),
                'weekend' => (bool) ($td['weekend'] ?? false)]];
        $ch = $lp['chronic_late'] ?? [];
        if ($ch) {
            $ev[] = ['fact' => 'chronic', 'count' => count($ch), 'worst' => (string) ($ch[0]['name'] ?? ''),
                     'days' => (int) ($ch[0]['late_days'] ?? 0), 'minutes' => (int) ($ch[0]['late_minutes'] ?? 0)];
        }
        // the rule that decides whether any of it costs money
        $ev[] = ['fact' => 'rule', 'grace_minutes' => 120];
        // coverage: attendance is only as true as the number of people on the device
        $tracked = 0;
        foreach ($this->A->rows('attendances') as $a) $tracked = max($tracked, 0) + 0;
        $ids = [];
        foreach ($this->A->rows('attendances') as $a) $ids[(int) ($a['user_id'] ?? 0)] = true;
        $active = count(array_filter($this->A->rows('employees'), fn($e) => ($e['status'] ?? 'active') === 'active'));
        if ($active > 0 && count($ids) < $active * 0.7) {
            $ev[] = ['fact' => 'coverage', 'tracked' => count($ids), 'active' => $active];
        }
        return ['claim' => 'attendance', 'evidence' => $ev, 'cause' => $ch ? 'habit' : 'none', 'confidence' => 'reasonable'];
    }

    private function whyPayroll(): array
    {
        $pay = $this->A->payroll(null);
        $hc = $this->I->headcount();
        $ev = [['fact' => 'run', 'month' => (string) ($pay['month'] ?? ''), 'heads' => (int) ($pay['heads'] ?? 0),
                'gross' => $this->f($pay['gross'] ?? 0), 'net' => $this->f($pay['net'] ?? 0),
                'deductions' => $this->f($pay['deductions'] ?? 0), 'pending' => (int) ($pay['pending_count'] ?? 0)]];
        $ev[] = ['fact' => 'headcount', 'active' => (int) $hc['active'], 'monthly_salary' => $this->f($hc['monthly_salary'])];
        // the run only covers who has a slip — that gap matters
        if ((int) ($pay['heads'] ?? 0) > 0 && (int) $hc['active'] > (int) $pay['heads'] * 1.5) {
            $ev[] = ['fact' => 'coverage', 'slips' => (int) $pay['heads'], 'active' => (int) $hc['active']];
        }
        $dep = $hc['by_department'] ?? [];
        if ($dep) $ev[] = ['fact' => 'biggest_department', 'department' => (string) $dep[0]['department'],
                           'headcount' => (int) $dep[0]['headcount'], 'salary' => $this->f($dep[0]['salary'])];
        return ['claim' => 'payroll', 'evidence' => $ev, 'cause' => 'headcount', 'confidence' => 'reasonable'];
    }

    private function whyDelivery(): array
    {
        $tk = $this->A->tasks();
        $pr = $this->A->projects();
        $ev = [['fact' => 'tasks', 'open' => (int) ($tk['open'] ?? 0), 'overdue' => (int) ($tk['overdue'] ?? 0),
                'closed_week' => (int) ($tk['closed_last_7_days'] ?? 0)]];
        $risk = $pr['at_risk'] ?? [];
        $ev[] = ['fact' => 'projects', 'active' => (int) ($pr['active'] ?? 0), 'at_risk' => count($risk)];
        if ($risk) {
            $w = $risk[0];
            $ev[] = ['fact' => 'worst', 'name' => (string) $w['name'], 'progress' => $this->f($w['progress']),
                     'elapsed' => $this->f($w['elapsed_pct'])];
        }
        if (!empty($tk['overloaded'][0])) {
            $ev[] = ['fact' => 'overloaded', 'name' => (string) $tk['overloaded'][0]['name'], 'open' => (int) $tk['overloaded'][0]['open']];
        }
        return ['claim' => 'delivery', 'evidence' => $ev, 'cause' => $risk ? 'schedule' : 'none', 'confidence' => 'reasonable'];
    }
}
