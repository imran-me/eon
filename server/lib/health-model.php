<?php
/* ============================================================
   EON · company health — the server half of the score.

   The identical model runs in the browser
   (ai-companion/eon-brain/domains/erp/plugins/health.js). Same weights,
   same thresholds, same wording, so the panel, the morning brief and the
   model's tool never disagree about how a company is doing.

     score 0–100 = finance×0.40 + people×0.25 + sales×0.20 + operations×0.15
     grade        A ≥ 85 · B ≥ 70 · C ≥ 55 · D below

   FINANCE   margin      net margin last month → 50 + 2.5×margin%
             ar_overdue  overdue ÷ total receivable → 100 − 150×share
             ap_overdue  overdue ÷ total payable    → 100 − 150×share
             cash_cover  cash ÷ average monthly outflow (3 closed months) ÷ 6 × 100
             budget      100 − 25 per category over budget this month
   PEOPLE    attendance  (attendance% − 70) × 4        (40%)
             late        100 − 400 × chronic-late share (30%)
             payroll     100 − 100 × unpaid share, floored at 60 before the 5th (30%)
   SALES     conversion  2 × conversion%  (undecided → 60)
             stale       100 − 200 × cold-lead share
             pipeline    open pipeline ÷ last month's revenue ÷ 3 × 100
   OPS       tasks       100 − 250 × overdue share (50%)
             projects    100 − 200 × at-risk share  (50%)

   Every part is clamped to 0–100. The drivers are the three parts that cost
   the most points (weight × points lost), written as sentences a person reads.
   ============================================================ */
declare(strict_types=1);

if (!function_exists('eon_health_score')) {

    /** weight of each part inside the whole score — used to rank the drivers */
    function eon_health_weights(): array
    {
        return [
            'margin' => 0.40 / 5, 'ar_overdue' => 0.40 / 5, 'ap_overdue' => 0.40 / 5, 'cash_cover' => 0.40 / 5, 'budget' => 0.40 / 5,
            'attendance' => 0.25 * 0.4, 'late' => 0.25 * 0.3, 'payroll' => 0.25 * 0.3,
            'conversion' => 0.20 / 3, 'stale' => 0.20 / 3, 'pipeline' => 0.20 / 3,
            'tasks' => 0.15 * 0.5, 'projects' => 0.15 * 0.5,
        ];
    }
    function eon_health_layer(string $part): string
    {
        return match ($part) {
            'margin', 'ar_overdue', 'ap_overdue', 'cash_cover', 'budget' => 'finance',
            'attendance', 'late', 'payroll' => 'people',
            'conversion', 'stale', 'pipeline' => 'crm',
            default => 'ops',
        };
    }

    function eon_health_score(array $D, ?int $company, string $today): array
    {
        $clamp = fn($n) => max(0.0, min(100.0, (float) $n));
        $r1 = fn($n) => round((float) $n, 1);
        $A = new Analytics($D, $company);
        $day = (int) date('j', strtotime($today));
        $lm = date('Y-m', strtotime($today . ' 00:00:00 -' . $day . ' days'));   // the previous month

        // ---- finance ----
        $pl = $A->profitAndLoss($lm . '-01', $lm . '-31');
        $ar = $A->schedules('receive');
        $ap = $A->schedules('pay');
        $cash = $A->cash();
        $bud = $A->expensesVsBudget();
        $arRatio = ($ar['total'] ?? 0) > 0 ? ($ar['overdue_total'] / $ar['total']) : 0.0;
        $apRatio = ($ap['total'] ?? 0) > 0 ? ($ap['overdue_total'] / $ap['total']) : 0.0;

        $outflows = [];
        for ($i = 1; $i <= 3; $i++) {
            $mk = date('Y-m', strtotime($today . ' 00:00:00 -' . ($day + 1) . ' days -' . ($i - 1) . ' month'));
            $p = $A->profitAndLoss($mk . '-01', $mk . '-31');
            $o = (float) (($p['opex'] ?? 0) + ($p['direct_cost'] ?? 0));
            if ($o > 0) $outflows[] = $o;
        }
        $avgOut = $outflows ? array_sum($outflows) / count($outflows) : 0.0;
        $cover = $avgOut > 0 ? ((float) $cash['total']) / $avgOut : null;

        $parts = [];
        $parts['margin'] = $clamp(50 + 2.5 * (float) ($pl['margin_pct'] ?? 0));
        $parts['ar_overdue'] = ($ar['total'] ?? 0) > 0 ? $clamp(100 - 150 * $arRatio) : 100.0;
        $parts['ap_overdue'] = ($ap['total'] ?? 0) > 0 ? $clamp(100 - 150 * $apRatio) : 100.0;
        $parts['cash_cover'] = $cover === null ? 100.0 : $clamp($cover / 6 * 100);
        $parts['budget'] = $clamp(100 - 25 * count($bud['over'] ?? []));

        // ---- people ----
        $heads = 0;
        foreach (($D['employees'] ?? []) as $e) {
            if (($e['status'] ?? 'active') !== 'active') continue;
            if ($company !== null && (int) ($e['company_id'] ?? 0) !== $company) continue;
            $heads++;
        }
        $lp = $A->latePatterns(30);
        $pr = $A->payroll();
        $chronic = count($lp['chronic_late'] ?? []);
        $lateShare = $heads > 0 ? $chronic / $heads : 0.0;
        $payHeads = (int) ($pr['heads'] ?? 0);
        $payPending = (int) ($pr['pending_count'] ?? 0);
        $payShare = $payHeads > 0 ? $payPending / $payHeads : 0.0;
        $attPct = (float) ($lp['avg_attendance_pct'] ?? 0);
        $parts['attendance'] = $attPct > 0 ? $clamp(($attPct - 70) * 4) : 100.0;
        $parts['late'] = $clamp(100 - 400 * $lateShare);
        $parts['payroll'] = $payShare > 0 ? max($day < 5 ? 60.0 : 0.0, $clamp(100 - 100 * $payShare)) : 100.0;

        // ---- sales ----
        $pipe = $A->pipeline();
        $open = (int) ($pipe['open'] ?? 0);
        $staleShare = $open > 0 ? ((int) ($pipe['stale_count'] ?? 0)) / $open : 0.0;
        $income = (float) ($pl['income'] ?? 0);
        $pipeRatio = $income > 0 ? ((float) ($pipe['open_value'] ?? 0)) / $income : null;
        $parts['conversion'] = $pipe['conversion_pct'] === null ? 60.0 : $clamp(2 * (float) $pipe['conversion_pct']);
        $parts['stale'] = $open > 0 ? $clamp(100 - 200 * $staleShare) : 100.0;
        $parts['pipeline'] = $pipeRatio === null ? (($pipe['open_value'] ?? 0) > 0 ? 100.0 : 50.0) : $clamp($pipeRatio / 3 * 100);

        // ---- operations ----
        $tk = $A->tasks();
        $pj = $A->projects();
        $tkOpen = (int) ($tk['open'] ?? 0);
        $taskShare = $tkOpen > 0 ? ((int) ($tk['overdue'] ?? 0)) / $tkOpen : 0.0;
        $pjActive = (int) ($pj['active'] ?? 0);
        $riskShare = $pjActive > 0 ? ((int) ($pj['at_risk'] ?? 0)) / $pjActive : 0.0;
        $parts['tasks'] = $tkOpen > 0 ? $clamp(100 - 250 * $taskShare) : 100.0;
        $parts['projects'] = $pjActive > 0 ? $clamp(100 - 200 * $riskShare) : 100.0;

        $sub = [
            'finance' => $r1(($parts['margin'] + $parts['ar_overdue'] + $parts['ap_overdue'] + $parts['cash_cover'] + $parts['budget']) / 5),
            'people' => $r1($parts['attendance'] * 0.4 + $parts['late'] * 0.3 + $parts['payroll'] * 0.3),
            'sales' => $r1(($parts['conversion'] + $parts['stale'] + $parts['pipeline']) / 3),
            'ops' => $r1($parts['tasks'] * 0.5 + $parts['projects'] * 0.5),
        ];
        $score = (int) round($sub['finance'] * 0.40 + $sub['people'] * 0.25 + $sub['sales'] * 0.20 + $sub['ops'] * 0.15);
        $k = fn($n) => Analytics::bdtk((float) $n);

        $facts = [
            'margin' => sprintf('Net margin last month %s%% (%s %s on %s revenue)', $pl['margin_pct'] ?? 0, ($pl['net_profit'] ?? 0) >= 0 ? 'profit' : 'loss', $k(abs((float) ($pl['net_profit'] ?? 0))), $k($income)),
            'ar_overdue' => ($ar['total'] ?? 0) > 0 ? sprintf('%d%% of receivables overdue (%s of %s)', round($arRatio * 100), $k($ar['overdue_total']), $k($ar['total'])) : 'No open receivables',
            'ap_overdue' => ($ap['total'] ?? 0) > 0 ? sprintf('%d%% of payables past due (%s of %s)', round($apRatio * 100), $k($ap['overdue_total']), $k($ap['total'])) : 'No open payables',
            'cash_cover' => $cover === null ? sprintf('Cash %s, no outflow history', $k($cash['total'])) : sprintf('Cash %s covers %s months of outflow (%s/month)', $k($cash['total']), $r1($cover), $k($avgOut)),
            'budget' => count($bud['over'] ?? []) ? sprintf('%d categor%s over budget this month', count($bud['over']), count($bud['over']) > 1 ? 'ies' : 'y') : 'No category over budget this month',
            'attendance' => $attPct > 0 ? sprintf('Attendance %s%% over the last 30 days', round($attPct)) : 'No attendance rows in the last 30 days',
            'late' => $chronic ? sprintf('%d of %d employees chronically late (%d%%)', $chronic, $heads, round($lateShare * 100)) : 'Nobody chronically late',
            'payroll' => $payPending ? sprintf('%d of %d payslips for %s unpaid (%s)', $payPending, $payHeads, $pr['month'] ?? '', $k($pr['pending_net'] ?? 0)) : sprintf('Payroll %s fully paid', $pr['month'] ?? ''),
            'conversion' => $pipe['conversion_pct'] === null ? 'No decided leads yet' : sprintf('Lead conversion %d%% (%d won / %d lost)', $pipe['conversion_pct'], $pipe['won'] ?? 0, $pipe['lost'] ?? 0),
            'stale' => $open > 0 ? sprintf('%d of %d open leads gone cold (%d%%)', $pipe['stale_count'] ?? 0, $open, round($staleShare * 100)) : 'No open leads',
            'pipeline' => $pipeRatio === null ? sprintf('Pipeline %s, no revenue last month to compare', $k($pipe['open_value'] ?? 0)) : sprintf('Pipeline %s = %s× last month\'s revenue', $k($pipe['open_value'] ?? 0), $r1($pipeRatio)),
            'tasks' => $tkOpen > 0 ? sprintf('%d of %d open tasks overdue (%d%%)', $tk['overdue'] ?? 0, $tkOpen, round($taskShare * 100)) : 'No open tasks',
            'projects' => $pjActive > 0 ? sprintf('%d of %d active projects at risk', $pj['at_risk'] ?? 0, $pjActive) : 'No active projects',
        ];

        $W = eon_health_weights();
        $drivers = [];
        foreach ($parts as $key => $val) {
            $lost = round((100 - $val) * $W[$key], 1);
            if ($lost > 0) $drivers[] = ['part' => $key, 'layer' => eon_health_layer($key), 'score' => (int) round($val), 'lost' => $lost, 'text' => $facts[$key]];
        }
        usort($drivers, fn($a, $b) => $b['lost'] <=> $a['lost']);
        $drivers = array_slice($drivers, 0, 3);

        $name = 'Epal Group (all companies)';
        $short = 'GROUP';
        if ($company !== null) {
            foreach (($D['companies'] ?? []) as $c) {
                if ((int) ($c['id'] ?? 0) === $company) { $name = (string) ($c['name'] ?? $name); $short = (string) ($c['short_name'] ?? $short); break; }
            }
        }
        // a company with no people, no money owed either way, no leads and no work is not
        // "healthy" — there is simply nothing to judge. Saying 90/A there would be a lie.
        $signal = $heads + (float) ($ar['total'] ?? 0) + (float) ($ap['total'] ?? 0) + $open + $tkOpen + $pjActive + abs($income);
        $insufficient = $signal <= 0;

        return [
            'company_id' => $company, 'company' => $name, 'short_name' => $short,
            'insufficient_data' => $insufficient,
            'score' => $score, 'grade' => $score >= 85 ? 'A' : ($score >= 70 ? 'B' : ($score >= 55 ? 'C' : 'D')),
            'sub' => $sub, 'parts' => array_map(fn($v) => (int) round($v), $parts), 'facts' => $facts, 'drivers' => $drivers,
            'top_driver' => $insufficient ? 'No data for this company yet' : ($drivers[0]['text'] ?? 'Nothing is pulling the score down'),
            'formula' => 'finance×0.40 + people×0.25 + sales×0.20 + ops×0.15',
        ];
    }

    /** every company, worst first, with the group line separate */
    function eon_health_all(array $D, string $today): array
    {
        $rows = [];
        foreach (($D['companies'] ?? []) as $c) {
            $id = (int) ($c['id'] ?? 0);
            if (!$id) continue;
            $rows[] = eon_health_score($D, $id, $today);
        }
        usort($rows, fn($a, $b) => $a['score'] <=> $b['score']);
        return ['group' => eon_health_score($D, null, $today), 'companies' => $rows];
    }
}
