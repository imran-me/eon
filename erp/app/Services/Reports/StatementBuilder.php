<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The financial statements — Profit & Loss, Balance Sheet, Cash Flow, Expenses by Category — read
 * straight from the journals, one aggregate query per statement (never one query per account).
 *
 * READ-ONLY. It writes nothing and never changes how anything is posted. The sections come from
 * config/account_reports.php (code ranges over the existing chart), and an account of the right
 * type that no range claims still lands in a visible "Other" line — so a statement's total is
 * always the sum of every account, and the figures match the classic reports to the paisa.
 *
 * $companyId null = every company (the "whole group" view); an id = that company's journals only.
 */
class StatementBuilder
{
    /* ── building blocks ──────────────────────────────────────────────────── */

    /**
     * Every account that still exists, keyed by id. An account switched off (status 0) can take no NEW
     * postings but keeps its history, so it is included whenever it carries a balance — the classic reports
     * left such accounts out, which is why their balance sheets could fail to balance. They show as "(inactive)".
     */
    public function accounts(bool $activeOnly = false): Collection
    {
        return DB::table('accounts')->whereNull('deleted_at')
            ->when($activeOnly, fn ($q) => $q->where('status', 1))
            ->orderBy('code')->get(['id', 'code', 'name', 'type', 'parent_id', 'company_id', 'status'])
            ->keyBy('id');
    }

    /** account_id => {d, c}: the journal movement in [from, to] (either end optional) for one company or all. */
    public function movements(?int $companyId, ?string $from, ?string $to): Collection
    {
        return DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->whereNull('ji.deleted_at')->whereNull('je.deleted_at')
            ->when($companyId, fn ($q) => $q->where('je.company_id', $companyId))
            ->when($from, fn ($q) => $q->whereDate('je.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('je.date', '<=', $to))
            ->groupBy('ji.account_id')
            ->selectRaw('ji.account_id, COALESCE(SUM(ji.debit), 0) AS d, COALESCE(SUM(ji.credit), 0) AS c')
            ->get()->keyBy('account_id');
    }

    private function inRanges(int $code, array $ranges): bool
    {
        foreach ($ranges as [$lo, $hi]) {
            if ($code >= $lo && $code <= $hi) {
                return true;
            }
        }

        return false;
    }

    /** The 100-block header an account sits under (6101 → "Personnel Expenses"), unless it is a root or the account itself. */
    private function groupLabel(object $acc, Collection $accounts): ?string
    {
        $code = (int) $acc->code;
        $head = intdiv($code, 100) * 100;
        if ($head === $code || $head % 1000 === 0) {
            return null;
        }

        return optional($accounts->first(fn ($a) => (int) $a->code === $head))->name;
    }

    /** One display row for an account: its figure on the side that increases it. */
    private function row(object $acc, ?object $mv, string $normal): ?array
    {
        $d = (float) ($mv->d ?? 0);
        $c = (float) ($mv->c ?? 0);
        if ($d <= 0 && $c <= 0) {
            return null;
        }

        return [
            'id' => (int) $acc->id, 'code' => $acc->code, 'name' => $acc->name . (($acc->status ?? 1) != 1 ? ' (inactive)' : ''),
            'debit' => $d, 'credit' => $c,
            'amount' => $normal === 'debit' ? $d - $c : $c - $d,
        ];
    }

    /** Rows → [{label, rows[], total}] grouped under the 100-block header (null header = loose rows first). */
    private function grouped(Collection $rows, Collection $accounts): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $label = $r['group'] ?? '';
            $groups[$label]['label'] = $label;
            $groups[$label]['rows'][] = $r;
            $groups[$label]['total'] = ($groups[$label]['total'] ?? 0.0) + $r['amount'];
        }
        ksort($groups);   // '' (no header) sorts first

        return array_values($groups);
    }

    private function section(string $label, array $groups): array
    {
        return ['label' => $label, 'groups' => $groups, 'total' => (float) array_sum(array_column($groups, 'total'))];
    }


    /** Σ (debit − credit) of the ASSET accounts whose code is in $ranges, up to $asOf — cash, bank, … as a single figure. */
    public function balanceOfRanges(?int $companyId, string $asOf, array $ranges): float
    {
        $mv = $this->movements($companyId, null, $asOf);
        $sum = 0.0;
        foreach ($this->accounts() as $a) {
            if ($a->type === 'asset' && $this->inRanges((int) $a->code, $ranges) && ($m = $mv->get($a->id))) {
                $sum += (float) $m->d - (float) $m->c;
            }
        }

        return $sum;
    }

    /* ── Profit & Loss ────────────────────────────────────────────────────── */

    public function profitLoss(?int $companyId, ?string $from, ?string $to): array
    {
        $cfg = config('account_reports.pl');
        $accounts = $this->accounts();
        $mv = $this->movements($companyId, $from, $to);

        $claimed = [];
        $buckets = [];
        foreach ($cfg as $key => $def) {
            $rows = collect();
            foreach ($accounts as $acc) {
                if ($acc->type !== $def['type'] || ! $this->inRanges((int) $acc->code, $def['ranges'])) {
                    continue;
                }
                $r = $this->row($acc, $mv->get($acc->id), $def['type'] === 'income' ? 'credit' : 'debit');
                $claimed[$acc->id] = true;
                if ($r) {
                    $r['group'] = $this->groupLabel($acc, $accounts) ?? '';
                    $rows->push($r);
                }
            }
            $buckets[$key] = $this->section($def['label'], $this->grouped($rows, $accounts));
        }

        // anything of the right type that no range claimed — never dropped
        foreach (['income' => ['other_income', 'Other Income', 'credit'], 'expense' => ['other_expense', 'Other Expense', 'debit']] as $type => [$key, $label, $normal]) {
            $rows = collect();
            foreach ($accounts as $acc) {
                if ($acc->type !== $type || isset($claimed[$acc->id])) {
                    continue;
                }
                if ($r = $this->row($acc, $mv->get($acc->id), $normal)) {
                    $r['group'] = '';
                    $rows->push($r);
                }
            }
            $buckets[$key] = $this->section($label, $this->grouped($rows, $accounts));
        }

        $t = fn (string $k) => $buckets[$k]['total'];
        $totalIncome  = $t('operating_income') + $t('nonop_income') + $t('other_income');
        $totalExpense = $t('cogs') + $t('operating_expense') + $t('nonop_expense') + $t('tax_control') + $t('other_expense');
        $gross = $t('operating_income') - $t('cogs');
        $operating = $gross - $t('operating_expense');

        return [
            'sections'        => $buckets,
            'gross_profit'    => $gross,
            'operating_profit' => $operating,
            'total_income'    => $totalIncome,
            'total_expense'   => $totalExpense,
            'net_profit'      => $totalIncome - $totalExpense,
            'gross_margin'    => $t('operating_income') != 0.0 ? $gross / $t('operating_income') * 100 : null,
            'net_margin'      => $t('operating_income') != 0.0 ? ($totalIncome - $totalExpense) / $t('operating_income') * 100 : null,
        ];
    }

    /** Net profit only (cheap): Σ income − Σ expense in the window, over every account. */
    public function netProfit(?int $companyId, ?string $from, ?string $to): float
    {
        $accounts = $this->accounts();
        $mv = $this->movements($companyId, $from, $to);
        $net = 0.0;
        foreach ($accounts as $acc) {
            $m = $mv->get($acc->id);
            if (! $m) {
                continue;
            }
            if ($acc->type === 'income') {
                $net += (float) $m->c - (float) $m->d;
            } elseif ($acc->type === 'expense') {
                $net -= (float) $m->d - (float) $m->c;
            }
        }

        return $net;
    }

    /* ── Balance Sheet ────────────────────────────────────────────────────── */

    public function balanceSheet(?int $companyId, string $asOf): array
    {
        $cfg = config('account_reports.bs');
        $accounts = $this->accounts();
        $mv = $this->movements($companyId, null, $asOf);

        $sectionDefs = [
            'current_assets'         => ['Current Assets', 'asset', 'debit'],
            'fixed_assets'           => ['Fixed Assets', 'asset', 'debit'],
            'other_assets'           => ['Other Assets', 'asset', 'debit'],
            'current_liabilities'    => ['Current Liabilities', 'liability', 'credit'],
            'noncurrent_liabilities' => ['Non Current Liabilities', 'liability', 'credit'],
            'equity'                 => ['Equity', 'equity', 'credit'],
        ];

        // pass 1 — the configured groups of every section (marks what they claim)
        $claimed = [];
        $lines = array_fill_keys(array_keys($sectionDefs), []);
        foreach ($sectionDefs as $sKey => [$sLabel, $type, $normal]) {
            foreach ($cfg as $g) {
                if ($g['section'] !== $sKey) {
                    continue;
                }
                $rows = collect();
                foreach ($accounts as $acc) {
                    if ($acc->type !== $type || ! $this->inRanges((int) $acc->code, $g['ranges'])) {
                        continue;
                    }
                    $claimed[$acc->id] = true;
                    if ($r = $this->row($acc, $mv->get($acc->id), $normal)) {
                        $r['group'] = $this->groupLabel($acc, $accounts) ?? '';
                        $rows->push($r);
                    }
                }
                if ($rows->isNotEmpty()) {
                    $lines[$sKey][] = ['label' => $g['label'], 'rows' => $rows->all(), 'total' => (float) $rows->sum('amount')];
                }
            }
        }

        // pass 2 — an account of a type that no group claimed is shown, never dropped
        foreach (['current_assets' => ['asset', 'debit'], 'current_liabilities' => ['liability', 'credit'], 'equity' => ['equity', 'credit']] as $sKey => [$type, $normal]) {
            $loose = collect();
            foreach ($accounts as $acc) {
                if ($acc->type !== $type || isset($claimed[$acc->id])) {
                    continue;
                }
                if ($r = $this->row($acc, $mv->get($acc->id), $normal)) {
                    $r['group'] = '';
                    $loose->push($r);
                    $claimed[$acc->id] = true;
                }
            }
            if ($loose->isNotEmpty()) {
                $lines[$sKey][] = ['label' => 'Other (not in a standard group)', 'rows' => $loose->all(), 'total' => (float) $loose->sum('amount')];
            }
        }

        $sections = [];
        foreach ($sectionDefs as $sKey => [$sLabel]) {
            $sections[$sKey] = ['label' => $sLabel, 'lines' => $lines[$sKey], 'total' => (float) array_sum(array_column($lines[$sKey], 'total'))];
        }
        // Cumulative profit to the date is part of equity, exactly as the classic sheet folded it in; it is
        // only SPLIT here into this financial year and before it.
        $fyStart = $this->fiscalYearStart($asOf);
        $cumulative = $this->netProfit($companyId, null, $asOf);
        $thisYear = $this->netProfit($companyId, $fyStart, $asOf);
        $prior = $cumulative - $thisYear;

        $equityAccounts = $sections['equity']['total'];
        $totalAssets = $sections['current_assets']['total'] + $sections['fixed_assets']['total'] + $sections['other_assets']['total'];
        $totalLiabilities = $sections['current_liabilities']['total'] + $sections['noncurrent_liabilities']['total'];
        $totalEquity = $equityAccounts + $cumulative;

        return [
            'sections'          => $sections,
            'current_year_earnings' => $thisYear,
            'prior_earnings'    => $prior,
            'fy_start'          => $fyStart,
            'total_assets'      => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity'      => $totalEquity,
            'total_liab_equity' => $totalLiabilities + $totalEquity,
            'balanced'          => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
            'difference'        => $totalAssets - ($totalLiabilities + $totalEquity),
        ];
    }

    public function fiscalYearStart(string $asOf): string
    {
        $m = max(1, min(12, (int) config('account_reports.fiscal_year_start_month', 1)));
        $d = Carbon::parse($asOf);
        $start = Carbon::create($d->year, $m, 1)->startOfDay();
        if ($start->greaterThan($d)) {
            $start->subYear();
        }

        return $start->toDateString();
    }

    /* ── Cash Flow (indirect) ─────────────────────────────────────────────── */

    public function cashFlow(?int $companyId, string $from, string $to): array
    {
        $cfg = config('account_reports');
        $accounts = $this->accounts(false);                       // every account, so the cash movement always reconciles
        $before = $this->movements($companyId, null, Carbon::parse($from)->subDay()->toDateString());
        $upToEnd = $this->movements($companyId, null, $to);
        $period = $this->movements($companyId, $from, $to);

        $cashIds = $accounts->filter(fn ($a) => $a->type === 'asset' && $this->inRanges((int) $a->code, $cfg['cash_ranges']))->keys();
        $bal = fn (Collection $mv) => (float) $cashIds->sum(fn ($id) => (float) ($mv[$id]->d ?? 0) - (float) ($mv[$id]->c ?? 0));
        $beginning = $bal($before);
        $actualEnding = $bal($upToEnd);

        // net income over every income / expense account
        $netIncome = 0.0;
        foreach ($accounts as $a) {
            $m = $period->get($a->id);
            if ($m && $a->type === 'income') {
                $netIncome += (float) $m->c - (float) $m->d;
            } elseif ($m && $a->type === 'expense') {
                $netIncome -= (float) $m->d - (float) $m->c;
            }
        }

        $claimed = [];
        $lines = ['operating' => [], 'investing' => [], 'financing' => []];
        foreach (['operating', 'investing', 'financing'] as $bucket) {
            foreach ($cfg['cash_flow'][$bucket] as $def) {
                $sum = 0.0;
                foreach ($accounts as $a) {
                    if ($a->type !== $def['type'] || ! $this->inRanges((int) $a->code, $def['ranges']) || $cashIds->contains($a->id)) {
                        continue;
                    }
                    $claimed[$a->id] = true;
                    $m = $period->get($a->id);
                    if (! $m) {
                        continue;
                    }
                    $sum += $def['type'] === 'asset' ? -((float) $m->d - (float) $m->c) : ((float) $m->c - (float) $m->d);
                }
                if (abs($sum) > 0.004) {
                    $lines[$bucket][] = ['label' => $def['label'], 'amount' => $sum];
                }
            }
        }

        // balance-sheet accounts no line claims → operating, visibly
        $other = 0.0;
        foreach ($accounts as $a) {
            if (! in_array($a->type, ['asset', 'liability', 'equity'], true) || isset($claimed[$a->id]) || $cashIds->contains($a->id)) {
                continue;
            }
            $m = $period->get($a->id);
            if ($m) {
                $other += $a->type === 'asset' ? -((float) $m->d - (float) $m->c) : ((float) $m->c - (float) $m->d);
            }
        }
        if (abs($other) > 0.004) {
            $lines['operating'][] = ['label' => 'Other (not in a standard group)', 'amount' => $other];
        }

        $op = $netIncome + array_sum(array_column($lines['operating'], 'amount'));
        $inv = array_sum(array_column($lines['investing'], 'amount'));
        $fin = array_sum(array_column($lines['financing'], 'amount'));
        $net = $op + $inv + $fin;
        $ending = $beginning + $net;

        return [
            'beginning'  => $beginning,
            'net_income' => $netIncome,
            'lines'      => $lines,
            'operating'  => $op, 'investing' => $inv, 'financing' => $fin,
            'net_change' => $net,
            'ending'     => $ending,
            'actual_ending' => $actualEnding,
            'reconciles' => abs($ending - $actualEnding) < 0.01,
            'difference' => $ending - $actualEnding,
        ];
    }

    /* ── Expenses by Category (ledger) ────────────────────────────────────── */

    /** Every expense account in the window, with the previous window of the same length beside it. */
    public function expensesByCategory(?int $companyId, string $from, string $to): array
    {
        $accounts = $this->accounts()->filter(fn ($a) => $a->type === 'expense');
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $prevTo = Carbon::parse($from)->subDay()->toDateString();
        $prevFrom = Carbon::parse($prevTo)->subDays($days - 1)->toDateString();

        $now = $this->movements($companyId, $from, $to);
        $prev = $this->movements($companyId, $prevFrom, $prevTo);

        $sectionOf = function (object $a): string {
            $c = (int) $a->code;

            return match (true) {
                $c >= 5000 && $c <= 5999 => 'Cost of Goods Sold',
                $c >= 6000 && $c <= 7999 => 'Operating Expense',
                $c >= 8000 && $c <= 8999 => 'Non Operating Expense',
                $c >= 9000               => 'Tax & Control Accounts',
                default                  => 'Other Expense',
            };
        };

        $rows = [];
        foreach ($accounts as $a) {
            $m = $now->get($a->id);
            $p = $prev->get($a->id);
            $amount = $m ? (float) $m->d - (float) $m->c : 0.0;
            $before = $p ? (float) $p->d - (float) $p->c : 0.0;
            if (! $m && ! $p) {
                continue;
            }
            // header rows (6000 OPERATING EXPENSES, …) hold no postings of their own
            if (abs($amount) < 0.004 && abs($before) < 0.004) {
                continue;
            }
            $rows[$sectionOf($a)][] = [
                'id' => (int) $a->id, 'code' => $a->code, 'name' => $a->name,
                'amount' => $amount, 'previous' => $before,
                'change' => $before != 0.0 ? ($amount - $before) / abs($before) * 100 : null,
            ];
        }

        $order = ['Cost of Goods Sold', 'Operating Expense', 'Non Operating Expense', 'Tax & Control Accounts', 'Other Expense'];
        $sections = [];
        $grand = 0.0;
        $grandPrev = 0.0;
        foreach ($order as $label) {
            if (empty($rows[$label])) {
                continue;
            }
            usort($rows[$label], fn ($x, $y) => $y['amount'] <=> $x['amount']);
            $tot = array_sum(array_column($rows[$label], 'amount'));
            $totPrev = array_sum(array_column($rows[$label], 'previous'));
            $sections[] = ['label' => $label, 'rows' => $rows[$label], 'total' => $tot, 'previous' => $totPrev];
            $grand += $tot;
            $grandPrev += $totPrev;
        }
        foreach ($sections as &$s) {
            foreach ($s['rows'] as &$r) {
                $r['share'] = $grand != 0.0 ? $r['amount'] / $grand * 100 : 0.0;
            }
        }
        unset($s, $r);

        return [
            'sections' => $sections, 'total' => $grand, 'previous_total' => $grandPrev,
            'change' => $grandPrev != 0.0 ? ($grand - $grandPrev) / abs($grandPrev) * 100 : null,
            'prev_from' => $prevFrom, 'prev_to' => $prevTo,
        ];
    }
}
