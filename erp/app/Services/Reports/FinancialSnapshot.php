<?php

namespace App\Services\Reports;

use Carbon\Carbon;

/**
 * The Monthly Report — a one-page financial snapshot of one company for one month against the month
 * before: the KPIs, a six-area health score, the "key gaps" the figures show, and recommendations.
 *
 * READ-ONLY and rule-based: every status, gap and recommendation is produced by an explicit rule over
 * the same journals the statements use (thresholds in config/account_reports.php → 'health'), and each
 * one says which numbers triggered it. Nothing is guessed, nothing is stored.
 *
 * A month still running is compared month-to-date against the SAME number of days of the month before
 * (never a part month against a whole one).
 */
class FinancialSnapshot
{
    public function __construct(private StatementBuilder $sb, private AgingReport $aging)
    {
    }

    public function build(?int $companyId, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
        $today = now()->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        if ($end->greaterThan($today) && $start->lessThanOrEqualTo($today)) {
            $end = $today->copy();                                  // month in progress: month-to-date
        }
        $days = $start->diffInDays($end) + 1;
        $mtd = $end->lessThan($start->copy()->endOfMonth()->startOfDay());

        $pStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
        $pEnd = $pStart->copy()->addDays($days - 1);
        if ($pEnd->greaterThan($pStart->copy()->endOfMonth())) {
            $pEnd = $pStart->copy()->endOfMonth();
        }

        $cur = $this->sb->profitLoss($companyId, $start->toDateString(), $end->toDateString());
        $prv = $this->sb->profitLoss($companyId, $pStart->toDateString(), $pEnd->toDateString());
        $cfg = config('account_reports');

        $sales = fn (array $pl) => $this->salesOf($pl);
        $exp = fn (array $pl) => $pl['sections']['operating_expense']['total'] + $pl['sections']['nonop_expense']['total'] + $pl['sections']['tax_control']['total'] + $pl['sections']['other_expense']['total'];
        $income = fn (array $pl) => $pl['sections']['operating_income']['total'];

        $curBs = $this->sb->balanceSheet($companyId, $end->toDateString());
        $prvBs = $this->sb->balanceSheet($companyId, $pEnd->toDateString());
        $cash = fn (string $asOf, array $ranges) => $this->sb->balanceOfRanges($companyId, $asOf, $ranges);
        $cashRanges = $cfg['cash_ranges'];
        $bankRanges = $cfg['bank_ranges'];

        $cashNow = $cash($end->toDateString(), $cashRanges);
        $cashPrev = $cash($pEnd->toDateString(), $cashRanges);
        $bankNow = $cash($end->toDateString(), $bankRanges);
        $bankPrev = $cash($pEnd->toDateString(), $bankRanges);
        $handNow = $cashNow - $bankNow;
        $handPrev = $cashPrev - $bankPrev;

        // Receivable / Payable are the PARTY ledger totals — what the Party Statement and the aging reports
        // show — so every report agrees. (The control accounts in the journals can drift from the party ledger.)
        $arNow = $this->aging->build('receivable', $companyId, $end->toDateString())['totals']['total'];
        $arPrev = $this->aging->build('receivable', $companyId, $pEnd->toDateString())['totals']['total'];
        $apNow = $this->aging->build('payable', $companyId, $end->toDateString())['totals']['total'];
        $apPrev = $this->aging->build('payable', $companyId, $pEnd->toDateString())['totals']['total'];

        $kpis = [
            $this->kpi('Sales', $sales($cur), $sales($prv), 'up'),
            $this->kpi('Income', $income($cur), $income($prv), 'up'),
            $this->kpi('Expense', $exp($cur), $exp($prv), 'down'),
            $this->kpi('Net Profit', $cur['net_profit'], $prv['net_profit'], 'up'),
            $this->kpi('Cash Balance (in hand)', $handNow, $handPrev, 'up'),
            $this->kpi('Receivable', $arNow, $arPrev, 'down'),
            $this->kpi('Payable', $apNow, $apPrev, 'down'),
            $this->kpi('Bank Balance', $bankNow, $bankPrev, 'up'),
        ];

        $iNow = $income($cur);
        $iPrev = $income($prv);
        $eNow = $exp($cur);
        $ePrev = $exp($prv);
        $pct = fn (float $a, float $b) => $b != 0.0 ? ($a - $b) / abs($b) * 100 : null;
        $incomeChange = $pct($iNow, $iPrev);
        $expChange = $pct($eNow, $ePrev);

        $apAging = $this->aging->build('payable', $companyId, $end->toDateString());
        $arAging = $this->aging->build('receivable', $companyId, $end->toDateString());
        $apOver = $apAging['totals']['total'] > 0 ? $apAging['totals']['over'] / $apAging['totals']['total'] * 100 : 0.0;
        $arOver = $arAging['totals']['total'] > 0 ? $arAging['totals']['over'] / $arAging['totals']['total'] * 100 : 0.0;

        $cashChange = $this->sb->cashFlow($companyId, $start->toDateString(), $end->toDateString());
        $curAssets = $curBs['sections']['current_assets']['total'];
        $curLiab = $curBs['sections']['current_liabilities']['total'];
        $ratio = ($curLiab > 0 && $curAssets > 0) ? $curAssets / $curLiab : null;      // a debit-balance "liability" is not a ratio
        $netMargin = $iNow != 0.0 ? $cur['net_profit'] / $iNow * 100 : null;
        $dso = $iNow > 0 ? $arNow / ($iNow / $days) : null;

        $h = $cfg['health'];
        $health = [
            $this->rate('Cashflow', $cashChange['net_change'] >= 0 ? 'Good' : (abs($cashChange['net_change']) <= $h['cash_drop_tolerance'] * max(abs($cashChange['beginning']), 1) ? 'Moderate' : 'Worse'),
                'Cash and bank moved by ' . $this->n($cashChange['net_change']) . ' in the period (opening ' . $this->n($cashChange['beginning']) . ').'),
            $this->rate('Profitability', $netMargin === null ? 'No data' : ($netMargin >= $h['net_margin_good'] ? 'Good' : ($netMargin >= 0 ? 'Moderate' : 'Worse')),
                $netMargin === null ? 'No operating income in the period.' : 'Net margin ' . number_format($netMargin, 1) . '% (net profit ' . $this->n($cur['net_profit']) . ').'),
            $this->rate('Collection', $dso === null ? ($arNow > 0 ? 'Worse' : 'Good') : ($dso <= $h['collection_days_good'] ? 'Good' : ($dso <= $h['collection_days_ok'] ? 'Moderate' : 'Worse')),
                $dso === null ? ($arNow > 0 ? 'Receivables with no income in the period to collect against.' : 'No receivables outstanding.') : 'About ' . round($dso) . ' days of income is uncollected (receivable ' . $this->n($arNow) . '); ' . number_format($arOver, 1) . '% of party receivables is over 45 days.'),
            $this->rate('Supplier Payment', $apAging['totals']['total'] <= 0 ? 'Good' : ($apOver <= $h['ap_over_good'] ? 'Good' : ($apOver <= $h['ap_over_ok'] ? 'Moderate' : 'Worse')),
                $apAging['totals']['total'] <= 0 ? 'No vendor dues.' : number_format($apOver, 1) . '% of vendor dues (' . $this->n($apAging['totals']['over']) . ') is over 45 days past due.'),
            $this->rate('Working Capital', $ratio === null ? 'No data' : ($ratio >= $h['current_ratio_good'] ? 'Good' : ($ratio >= 1 ? 'Moderate' : 'Worse')),
                $ratio === null ? 'Current assets / liabilities on the balance sheet are not both positive (' . $this->n($curAssets) . ' / ' . $this->n($curLiab) . ') — check the ledger.' : 'Current ratio ' . number_format($ratio, 2) . ' (current assets ' . $this->n($curAssets) . ' ÷ current liabilities ' . $this->n($curLiab) . ').'),
            $this->rate('Expense Control', ($expChange === null || $incomeChange === null) ? 'No data' : ($expChange <= $incomeChange ? 'Good' : ($expChange <= $incomeChange + $h['expense_growth_tolerance'] ? 'Moderate' : 'Worse')),
                ($expChange === null || $incomeChange === null) ? 'Needs a previous month to compare.' : 'Expense ' . $this->sign($expChange) . ' against income ' . $this->sign($incomeChange) . '.'),
        ];

        [$gaps, $recs] = $this->gaps($cur, $prv, $iNow, $iPrev, $incomeChange, $expChange, $arOver, $apOver, $arAging, $apAging, $cashChange, $netMargin);

        return [
            'company_id' => $companyId, 'month' => $month, 'mtd' => $mtd,
            'from' => $start->toDateString(), 'to' => $end->toDateString(),
            'prev_from' => $pStart->toDateString(), 'prev_to' => $pEnd->toDateString(),
            'kpis' => $kpis, 'health' => $health, 'gaps' => $gaps, 'recommendations' => $recs,
            'verdict' => $this->verdict($cur['net_profit'], $incomeChange, $expChange),
        ];
    }

    /* ── rules ────────────────────────────────────────────────────────────── */

    private function gaps(array $cur, array $prv, float $iNow, float $iPrev, ?float $incomeChange, ?float $expChange, float $arOver, float $apOver, array $arAging, array $apAging, array $cf, ?float $netMargin): array
    {
        $gaps = [];
        $recs = [];
        $cfgFixed = config('account_reports.fixed_cost_ranges', []);

        // 1 · revenue
        if ($incomeChange !== null && $incomeChange <= -10) {
            $gaps[] = ['title' => 'Significant revenue drop', 'points' => [
                'Operating income fell from ' . $this->n($iPrev) . ' to ' . $this->n($iNow) . ' (' . $this->sign($incomeChange) . ').',
                'Sales: ' . $this->n($this->salesOf($prv)) . ' → ' . $this->n($this->salesOf($cur)) . '.',
            ]];
            $recs[] = 'Find out why income fell ' . number_format(abs($incomeChange), 0) . '% — fewer customers, fewer orders, or a pricing/discount change.';
        }

        // 2 · gross margin
        $gmNow = $cur['gross_margin'];
        $gmPrev = $prv['gross_margin'];
        if ($gmNow !== null && $gmPrev !== null && $gmNow <= $gmPrev - 3) {
            $gaps[] = ['title' => 'Gross margin deterioration', 'points' => [
                'Gross margin fell from ' . number_format($gmPrev, 1) . '% to ' . number_format($gmNow, 1) . '%.',
                'Cost of goods sold: ' . $this->n($prv['sections']['cogs']['total']) . ' → ' . $this->n($cur['sections']['cogs']['total']) . '.',
            ]];
            $recs[] = 'Review cost of goods sold and pricing: cost did not fall in step with income.';
        }

        // 3 · costs that did not follow revenue down
        if ($incomeChange !== null && $incomeChange < 0) {
            $stuck = [];
            $prevCosts = $this->expenseMap($prv);
            foreach ($this->expenseMap($cur) as $id => $a) {
                $p = $prevCosts[$id]['amount'] ?? 0.0;
                if ($iNow > 0 && $a['amount'] >= 0.03 * $iNow && $p > 0 && ($a['amount'] - $p) / $p > $incomeChange / 100) {
                    $stuck[$a['name']] = $a['amount'];
                }
            }
            arsort($stuck);
            if ($stuck) {
                $gaps[] = ['title' => 'Costs that did not fall with revenue', 'points' => array_map(
                    fn ($n, $v) => $n . ': ' . $this->n($v) . ' this month (' . number_format($v / max($iNow, 1) * 100, 1) . '% of income).', array_keys(array_slice($stuck, 0, 5, true)), array_values(array_slice($stuck, 0, 5, true))
                )];
                $recs[] = 'Check the largest costs above — they stayed high while income dropped.';
            }
        }

        // 4 · fixed-cost burden
        $fixed = 0.0;
        $fixedNames = [];
        foreach ($this->expenseMap($cur) as $a) {
            foreach ($cfgFixed as [$lo, $hi]) {
                if ((int) $a['code'] >= $lo && (int) $a['code'] <= $hi) {
                    $fixed += $a['amount'];
                    $fixedNames[] = $a['name'];
                    break;
                }
            }
        }
        if ($iNow > 0 && $fixed / $iNow >= 0.40) {
            $gaps[] = ['title' => 'High fixed operating cost', 'points' => [
                'People, rent, utilities and professional fees come to ' . $this->n($fixed) . ' — ' . number_format($fixed / $iNow * 100, 1) . '% of income.',
                'Profit depends heavily on sales volume.',
            ]];
            $recs[] = 'Reduce fixed overhead where possible (rent, subscriptions, administrative costs) or lift volume.';
        }

        // 5 · biggest increases
        $up = [];
        $prevMap = $this->expenseMap($prv);
        foreach ($this->expenseMap($cur) as $id => $a) {
            $delta = $a['amount'] - ($prevMap[$id]['amount'] ?? 0.0);
            if ($delta > 0) {
                $up[$a['name']] = $delta;
            }
        }
        arsort($up);
        $up = array_slice($up, 0, 3, true);
        if ($up && $expChange !== null && $expChange > 0) {
            $gaps[] = ['title' => 'Biggest cost increases', 'points' => array_map(fn ($n, $v) => $n . ' up ' . $this->n($v), array_keys($up), array_values($up))];
        }

        // 6 · receivables / payables
        if ($arOver >= 15 && $arAging['totals']['over'] > 0) {
            $gaps[] = ['title' => 'Old receivables', 'points' => [$this->n($arAging['totals']['over']) . ' (' . number_format($arOver, 1) . '%) of what customers and agents owe is over 45 days past due.']];
            $recs[] = 'Chase the receivables over 45 days (AR Aging) before they become bad debt.';
        }
        if ($apOver >= 15 && $apAging['totals']['over'] > 0) {
            $gaps[] = ['title' => 'Old vendor dues', 'points' => [$this->n($apAging['totals']['over']) . ' (' . number_format($apOver, 1) . '%) of vendor dues is over 45 days past due.']];
            $recs[] = 'Agree a payment plan for vendor dues over 45 days (AP Aging) to protect supply.';
        }

        // 7 · cash & loss
        if ($cf['net_change'] < 0) {
            $gaps[] = ['title' => 'Cash is falling', 'points' => ['Cash and bank fell by ' . $this->n(abs($cf['net_change'])) . ' in the period (operating ' . $this->n($cf['operating']) . ').']];
            $recs[] = 'Watch cash closely: operating cash flow is ' . ($cf['operating'] < 0 ? 'negative' : 'positive but outflows from investing/financing exceed it') . '.';
        }
        if ($cur['net_profit'] < 0) {
            $gaps[] = ['title' => 'Net loss', 'points' => ['Net result ' . $this->n($cur['net_profit']) . ($netMargin !== null ? ' (' . number_format($netMargin, 1) . '% of income)' : '') . '.']];
        }

        if (! $gaps) {
            $gaps[] = ['title' => 'No gaps flagged', 'points' => ['None of the monitored thresholds were crossed this period.']];
        }
        $recs[] = 'Monitor Gross Margin %, delivery / direct cost %, and marketing return every month.';

        return [$gaps, array_values(array_unique($recs))];
    }

    private function verdict(float $net, ?float $incomeChange, ?float $expChange): string
    {
        if ($net < 0) {
            $why = ($incomeChange !== null && $incomeChange < 0 && ($expChange === null || $expChange > $incomeChange))
                ? 'income fell while costs did not fall as far' : 'costs exceeded income';

            return 'The company made a loss this period because ' . $why . '.';
        }

        return 'The company was profitable this period' . ($incomeChange !== null ? ' (income ' . $this->sign($incomeChange) . ' on the month before).' : '.');
    }

    /* ── helpers ──────────────────────────────────────────────────────────── */

    /** Sales = the revenue accounts proper (4000–4899); adjustments and other operating income are in "Income". */
    private function salesOf(array $pl): float
    {
        $t = 0.0;
        foreach ($pl['sections']['operating_income']['groups'] as $g) {
            foreach ($g['rows'] as $r) {
                if ((int) $r['code'] < 4900) {
                    $t += $r['amount'];
                }
            }
        }

        return $t;
    }

    /** id => {code,name,amount} for every cost account (COGS, operating, non-operating, tax) in a P&L. */
    private function expenseMap(array $pl): array
    {
        $out = [];
        foreach (['cogs', 'operating_expense', 'nonop_expense', 'tax_control', 'other_expense'] as $k) {
            foreach ($pl['sections'][$k]['groups'] as $g) {
                foreach ($g['rows'] as $r) {
                    $out[$r['id']] = ['code' => $r['code'], 'name' => $r['name'], 'amount' => $r['amount']];
                }
            }
        }

        return $out;
    }

    private function groupTotal(array $bs, string $section, string $label): float
    {
        foreach ($bs['sections'][$section]['lines'] as $l) {
            if ($l['label'] === $label) {
                return (float) $l['total'];
            }
        }

        return 0.0;
    }

    private function kpi(string $label, float $now, float $prev, string $good): array
    {
        $change = $prev != 0.0 ? ($now - $prev) / abs($prev) * 100 : null;
        $better = $change === null ? null : ($good === 'up' ? $change >= 0 : $change <= 0);

        return ['label' => $label, 'this' => $now, 'last' => $prev, 'change' => $change, 'better' => $better];
    }

    private function rate(string $area, string $status, string $comment): array
    {
        return ['area' => $area, 'status' => $status, 'comment' => $comment];
    }

    private function n(float $v): string
    {
        return number_format($v, 0);
    }

    private function sign(?float $c): string
    {
        return $c === null ? 'n/a' : ($c >= 0 ? '+' : '−') . number_format(abs($c), 1) . '%';
    }
}
