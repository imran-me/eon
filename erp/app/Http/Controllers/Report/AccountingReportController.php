<?php

namespace App\Http\Controllers\Report;

use App\Models\Company;
use App\Services\Reports\StatementBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The financial statements, one company at a time: Profit & Loss, Balance Sheet, Cash Flow,
 * Expenses by Category — each as a screen, a layout-free print page (company letterhead) and a CSV.
 *
 * Read-only: every figure comes from App\Services\Reports\StatementBuilder, which reads the journals.
 * Permissions are the existing report permissions (no new ones to seed).
 *
 * Company scope: a user without "view all account" is locked to their own company; one with it picks
 * a company (default: their own, else the whole group). "Whole group" is a deliberate, labelled choice
 * — inter-company balances are counted on both sides there.
 */
class AccountingReportController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view profit loss report', only: ['profitLoss', 'profitLossPrint', 'profitLossExport', 'expensesByCategory', 'expensesByCategoryPrint', 'expensesByCategoryExport', 'expensesByCategoryExcel']),
            new Middleware('permission:view account statement report', only: ['arAging', 'arAgingPrint', 'arAgingExport', 'apAging', 'apAgingPrint', 'apAgingExport']),
            new Middleware('permission:view balance sheet report', only: ['balanceSheet', 'balanceSheetPrint', 'balanceSheetExport', 'cashFlow', 'cashFlowPrint', 'cashFlowExport', 'monthlyReport', 'monthlyReportPrint', 'monthlyReportExport']),
        ];
    }

    /* ── scope & period ───────────────────────────────────────────────────── */

    /** @return array{0:?int,1:?Company,2:\Illuminate\Support\Collection,3:bool} [companyId, company, pickable companies, canPick] */
    private function scope(Request $request): array
    {
        $user = auth()->user();
        $canPick = $user->can('view all account');

        if (! $canPick) {
            $id = (int) $user->company_id ?: -1;                    // no company → matches nothing, never "everything"

            return [$id, Company::find($id), Company::where('id', $id)->get(), false];
        }

        $companies = Company::orderBy('name')->get();
        $raw = $request->input('company_id');

        if ($raw === 'all') {
            return [null, null, $companies, true];
        }
        if ($raw !== null && $raw !== '' && $companies->contains('id', (int) $raw)) {
            return [(int) $raw, $companies->firstWhere('id', (int) $raw), $companies, true];
        }
        $own = (int) $user->company_id;
        if ($own && $companies->contains('id', $own)) {
            return [$own, $companies->firstWhere('id', $own), $companies, true];
        }

        return [null, null, $companies, true];
    }

    private function date(Request $request, string $key, string $default): string
    {
        try {
            return $request->filled($key) ? Carbon::parse((string) $request->input($key))->toDateString() : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function period(Request $request): array
    {
        $from = $this->date($request, 'date_from', now()->startOfMonth()->toDateString());
        $to = $this->date($request, 'date_to', now()->toDateString());
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /** The data every report view needs beyond its own figures. */
    private function frame(Request $request, string $title, string $subtitle, ?int $companyId, ?Company $company, $companies, bool $canPick, array $more = []): array
    {
        $own = (int) auth()->user()->company_id;
        $ledgerScope = $companyId === null ? 'all' : ($companyId === $own ? 'company' : null);   // the ledger page only knows these two

        return $more + [
            'frTitle' => $title, 'frSub' => $subtitle, 'frCompany' => $company, 'frCompanyId' => $companyId,
            'frCompanies' => $companies, 'frCanPick' => $canPick, 'frLedgerScope' => $ledgerScope,
            'frRole' => request()->route('role') ?: Str::slug(auth()->user()->getRoleNames()->first() ?? ''),
            'frGeneratedAt' => now(),
        ];
    }

    private function csv(string $name, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function letterhead(?Company $company): ?Company
    {
        return $company ?: (! empty(auth()->user()->company_id) ? Company::find(auth()->user()->company_id) : null) ?: Company::first();
    }

    /* ── Profit & Loss ────────────────────────────────────────────────────── */

    private function plData(Request $request): array
    {
        [$cid, $company, $companies, $canPick] = $this->scope($request);
        [$from, $to] = $this->period($request);
        $data = app(StatementBuilder::class)->profitLoss($cid, $from, $to);

        return $this->frame($request, 'Profit and Loss', 'Basis: accrual — from the journals', $cid, $company, $companies, $canPick, [
            'pl' => $data, 'frFrom' => $from, 'frTo' => $to, 'frMode' => 'period', 'frRoute' => 'profit-loss',
        ]);
    }

    public function profitLoss(Request $request): View
    {
        return view('report.statements.profit-loss', $this->plData($request));
    }

    public function profitLossPrint(Request $request): View
    {
        $d = $this->plData($request);

        return view('report.statements.print', $d + ['kind' => 'pl', 'letterhead' => $this->letterhead($d['frCompany'])]);
    }

    public function profitLossExport(Request $request): StreamedResponse
    {
        $d = $this->plData($request);
        $rows = [[$d['frCompany']?->name ?? 'All companies'], ['Profit and Loss', $d['frFrom'] . ' to ' . $d['frTo']], ['Account', 'Code', 'Total']];
        foreach ($d['pl']['sections'] as $key => $s) {
            if (empty($s['groups']) && $s['total'] == 0.0) {
                continue;
            }
            $rows[] = [$s['label']];
            foreach ($s['groups'] as $g) {
                foreach ($g['rows'] as $r) {
                    $rows[] = [($g['label'] ? $g['label'] . ' · ' : '') . $r['name'], $r['code'], number_format($r['amount'], 2, '.', '')];
                }
            }
            $rows[] = ['Total for ' . $s['label'], '', number_format($s['total'], 2, '.', '')];
            if ($key === 'cogs') {
                $rows[] = ['Gross Profit', '', number_format($d['pl']['gross_profit'], 2, '.', '')];
            }
            if ($key === 'operating_expense') {
                $rows[] = ['Operating Profit', '', number_format($d['pl']['operating_profit'], 2, '.', '')];
            }
        }
        $rows[] = ['Net Profit/Loss', '', number_format($d['pl']['net_profit'], 2, '.', '')];

        return $this->csv('profit-and-loss-' . $d['frFrom'] . '-to-' . $d['frTo'] . '.csv', $rows);
    }

    /* ── Balance Sheet ────────────────────────────────────────────────────── */

    private function bsData(Request $request): array
    {
        [$cid, $company, $companies, $canPick] = $this->scope($request);
        $asOf = $this->date($request, 'as_of', now()->toDateString());
        $data = app(StatementBuilder::class)->balanceSheet($cid, $asOf);

        return $this->frame($request, 'Balance Sheet', 'Basis: accrual — as of ' . Carbon::parse($asOf)->format('d M Y'), $cid, $company, $companies, $canPick, [
            'bs' => $data, 'frAsOf' => $asOf, 'frMode' => 'asof', 'frRoute' => 'balance-sheet',
        ]);
    }

    public function balanceSheet(Request $request): View
    {
        return view('report.statements.balance-sheet', $this->bsData($request));
    }

    public function balanceSheetPrint(Request $request): View
    {
        $d = $this->bsData($request);

        return view('report.statements.print', $d + ['kind' => 'bs', 'letterhead' => $this->letterhead($d['frCompany'])]);
    }

    public function balanceSheetExport(Request $request): StreamedResponse
    {
        $d = $this->bsData($request);
        $bs = $d['bs'];
        $rows = [[$d['frCompany']?->name ?? 'All companies'], ['Balance Sheet', 'As of ' . $d['frAsOf']], ['Account', 'Total']];
        $emit = function (string $label, array $section) use (&$rows) {
            $rows[] = [$label];
            foreach ($section['lines'] as $l) {
                $rows[] = ['  ' . $l['label']];
                foreach ($l['rows'] as $r) {
                    $rows[] = ['    ' . $r['name'], number_format($r['amount'], 2, '.', '')];
                }
                $rows[] = ['  Total for ' . $l['label'], number_format($l['total'], 2, '.', '')];
            }
            $rows[] = ['Total for ' . $label, number_format($section['total'], 2, '.', '')];
        };
        $rows[] = ['Assets'];
        foreach (['current_assets', 'fixed_assets', 'other_assets'] as $k) {
            $emit($bs['sections'][$k]['label'], $bs['sections'][$k]);
        }
        $rows[] = ['Total for Assets', number_format($bs['total_assets'], 2, '.', '')];
        $rows[] = ['Liabilities & Equities'];
        foreach (['current_liabilities', 'noncurrent_liabilities'] as $k) {
            $emit($bs['sections'][$k]['label'], $bs['sections'][$k]);
        }
        $rows[] = ['Total for Liabilities', number_format($bs['total_liabilities'], 2, '.', '')];
        $emit('Equities', $bs['sections']['equity']);
        $rows[] = ['Current Year Earnings', number_format($bs['current_year_earnings'], 2, '.', '')];
        $rows[] = ['Retained Earnings (prior periods)', number_format($bs['prior_earnings'], 2, '.', '')];
        $rows[] = ['Total for Equities', number_format($bs['total_equity'], 2, '.', '')];
        $rows[] = ['Total for Liabilities & Equities', number_format($bs['total_liab_equity'], 2, '.', '')];

        return $this->csv('balance-sheet-' . $d['frAsOf'] . '.csv', $rows);
    }

    /* ── Cash Flow ────────────────────────────────────────────────────────── */

    private function cfData(Request $request): array
    {
        [$cid, $company, $companies, $canPick] = $this->scope($request);
        [$from, $to] = $this->period($request);
        $data = app(StatementBuilder::class)->cashFlow($cid, $from, $to);

        return $this->frame($request, 'Cash Flow Statement', 'Indirect method — from the journals', $cid, $company, $companies, $canPick, [
            'cf' => $data, 'frFrom' => $from, 'frTo' => $to, 'frMode' => 'period', 'frRoute' => 'cash-flow',
        ]);
    }

    public function cashFlow(Request $request): View
    {
        return view('report.statements.cash-flow', $this->cfData($request));
    }

    public function cashFlowPrint(Request $request): View
    {
        $d = $this->cfData($request);

        return view('report.statements.print', $d + ['kind' => 'cf', 'letterhead' => $this->letterhead($d['frCompany'])]);
    }

    public function cashFlowExport(Request $request): StreamedResponse
    {
        $d = $this->cfData($request);
        $cf = $d['cf'];
        $f = fn ($n) => number_format($n, 2, '.', '');
        $rows = [[$d['frCompany']?->name ?? 'All companies'], ['Cash Flow Statement', $d['frFrom'] . ' to ' . $d['frTo']], ['Account', 'Total'],
            ['Beginning Cash Balance', $f($cf['beginning'])], ['Cash Flow from Operating Activities'], ['Net Income', $f($cf['net_income'])]];
        foreach ($cf['lines']['operating'] as $l) {
            $rows[] = [$l['label'], $f($l['amount'])];
        }
        $rows[] = ['Net cash provided by Operating Activities', $f($cf['operating'])];
        $rows[] = ['Cash Flow from Investing Activities'];
        foreach ($cf['lines']['investing'] as $l) {
            $rows[] = [$l['label'], $f($l['amount'])];
        }
        $rows[] = ['Net cash provided by Investing Activities', $f($cf['investing'])];
        $rows[] = ['Cash Flow from Financing Activities'];
        foreach ($cf['lines']['financing'] as $l) {
            $rows[] = [$l['label'], $f($l['amount'])];
        }
        $rows[] = ['Net cash provided by Financing Activities', $f($cf['financing'])];
        $rows[] = ['Net Change in cash', $f($cf['net_change'])];
        $rows[] = ['Ending Cash Balance', $f($cf['ending'])];

        return $this->csv('cash-flow-' . $d['frFrom'] . '-to-' . $d['frTo'] . '.csv', $rows);
    }


    /* ── AR / AP Aging ────────────────────────────────────────────────────── */

    private function agingData(Request $request, string $side): array
    {
        [$cid, $company, $companies, $canPick] = $this->scope($request);
        $asOf = $this->date($request, 'as_of', now()->toDateString());
        $data = app(\App\Services\Reports\AgingReport::class)->build($side, $cid, $asOf);
        $isAr = $side === 'receivable';

        return $this->frame($request, $isAr ? 'AR Aging Summary' : 'AP Aging Summary',
            ($isAr ? 'What agents and customers owe us' : 'What we owe vendors') . ' — aged from the due date (entry date where none is set)',
            $cid, $company, $companies, $canPick, [
                'ag' => $data, 'frSide' => $side, 'frAsOf' => $asOf, 'frMode' => 'asof', 'frRoute' => $isAr ? 'ar-aging' : 'ap-aging',
            ]);
    }

    public function arAging(Request $request): View
    {
        return view('report.statements.aging', $this->agingData($request, 'receivable'));
    }

    public function arAgingPrint(Request $request): View
    {
        $d = $this->agingData($request, 'receivable');

        return view('report.statements.print', $d + ['kind' => 'aging', 'letterhead' => $this->letterhead($d['frCompany'])]);
    }

    public function arAgingExport(Request $request): StreamedResponse
    {
        return $this->agingCsv($this->agingData($request, 'receivable'), 'ar-aging');
    }

    public function apAging(Request $request): View
    {
        return view('report.statements.aging', $this->agingData($request, 'payable'));
    }

    public function apAgingPrint(Request $request): View
    {
        $d = $this->agingData($request, 'payable');

        return view('report.statements.print', $d + ['kind' => 'aging', 'letterhead' => $this->letterhead($d['frCompany'])]);
    }

    public function apAgingExport(Request $request): StreamedResponse
    {
        return $this->agingCsv($this->agingData($request, 'payable'), 'ap-aging');
    }

    private function agingCsv(array $d, string $name): StreamedResponse
    {
        $f = fn ($n) => number_format($n, 2, '.', '');
        $head = array_merge([$d['frSide'] === 'receivable' ? 'Customer / Agent' : 'Vendor'], array_values(\App\Services\Reports\AgingReport::BUCKETS), ['Total']);
        $rows = [[$d['frCompany']?->name ?? 'All companies'], [$d['frTitle'], 'As of ' . $d['frAsOf']], $head];
        foreach ($d['ag']['rows'] as $r) {
            $rows[] = array_merge([$r['name']], array_map($f, array_values($r['buckets'])), [$f($r['total'])]);
        }
        $rows[] = array_merge(['TOTAL'], array_map($f, array_values(array_diff_key($d['ag']['totals'], ['total' => 1]))), [$f($d['ag']['totals']['total'])]);
        if ($d['ag']['advances']) {
            $rows[] = [];
            $rows[] = [$d['frSide'] === 'receivable' ? 'Advances received (credit balances)' : 'Advances paid (credit balances)'];
            foreach ($d['ag']['advances'] as $a) {
                $rows[] = [$a['name'], $f($a['amount'])];
            }
        }

        return $this->csv($name . '-' . $d['frAsOf'] . '.csv', $rows);
    }


    /* ── Monthly Report (financial snapshot) ──────────────────────────────── */

    private function monthlyData(Request $request): array
    {
        [$cid, $company, $companies, $canPick] = $this->scope($request);
        try {
            $month = $request->filled('month') ? Carbon::createFromFormat('Y-m', (string) $request->input('month'))->format('Y-m') : now()->format('Y-m');
        } catch (\Throwable $e) {
            $month = now()->format('Y-m');
        }
        $data = app(\App\Services\Reports\FinancialSnapshot::class)->build($cid, $month);

        return $this->frame($request, 'Monthly Report', 'Financial snapshot, health score and key gaps — ' . Carbon::createFromFormat('Y-m', $month)->format('F Y'),
            $cid, $company, $companies, $canPick, ['mr' => $data, 'frMonth' => $month, 'frMode' => 'month', 'frRoute' => 'monthly-report']);
    }

    public function monthlyReport(Request $request): View
    {
        return view('report.statements.monthly-report', $this->monthlyData($request));
    }

    public function monthlyReportPrint(Request $request): View
    {
        $d = $this->monthlyData($request);

        return view('report.statements.print', $d + ['kind' => 'monthly', 'letterhead' => $this->letterhead($d['frCompany'])]);
    }

    public function monthlyReportExport(Request $request): StreamedResponse
    {
        $d = $this->monthlyData($request);
        $mr = $d['mr'];
        $f = fn ($n) => number_format($n, 2, '.', '');
        $rows = [[$d['frCompany']?->name ?? 'All companies'], ['Monthly Report', $d['frMonth']], ['Financial Snapshot'], ['KPI', 'This month', 'Last month', 'Change %']];
        foreach ($mr['kpis'] as $k) {
            $rows[] = [$k['label'], $f($k['this']), $f($k['last']), $k['change'] === null ? '' : round($k['change'], 1)];
        }
        $rows[] = [];
        $rows[] = ['Financial Health Score'];
        $rows[] = ['Area', 'Status', 'Comment'];
        foreach ($mr['health'] as $h) {
            $rows[] = [$h['area'], $h['status'], $h['comment']];
        }
        $rows[] = [];
        $rows[] = ['Key Gaps Identified'];
        foreach ($mr['gaps'] as $g) {
            $rows[] = [$g['title']];
            foreach ($g['points'] as $p) {
                $rows[] = ['', $p];
            }
        }
        $rows[] = [];
        $rows[] = ['Recommendations'];
        foreach ($mr['recommendations'] as $r) {
            $rows[] = ['', $r];
        }

        return $this->csv('monthly-report-' . $d['frMonth'] . '.csv', $rows);
    }

    /* ── Expenses by Category ─────────────────────────────────────────────── */

    private function expData(Request $request): array
    {
        [$cid, $company, $companies, $canPick] = $this->scope($request);
        [$from, $to] = $this->period($request);
        $data = app(StatementBuilder::class)->expensesByCategory($cid, $from, $to);

        return $this->frame($request, 'Expenses by Category', 'Every expense account in the ledger, with the previous period beside it', $cid, $company, $companies, $canPick, [
            'ex' => $data, 'frFrom' => $from, 'frTo' => $to, 'frMode' => 'period', 'frRoute' => 'expenses-by-category', 'frExcel' => true,
        ]);
    }

    public function expensesByCategory(Request $request): View
    {
        return view('report.statements.expenses-by-category', $this->expData($request));
    }

    public function expensesByCategoryPrint(Request $request): View
    {
        $d = $this->expData($request);

        return view('report.statements.print', $d + ['kind' => 'exp', 'letterhead' => $this->letterhead($d['frCompany'])]);
    }

    /** The same figures as the screen, as the accountant's spreadsheet (App\Exports\ExpensesByCategoryExport). */
    public function expensesByCategoryExcel(Request $request)
    {
        $d = $this->expData($request);

        $lines = [];
        foreach ($d['ex']['sections'] as $s) {
            foreach ($s['rows'] as $r) {
                $lines[] = ['code' => $r['code'], 'name' => $r['name'], 'amount' => $r['amount']];
            }
        }
        usort($lines, fn ($a, $b) => strcasecmp($a['name'], $b['name']));          // A→Z, like the sheet it replaces

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ExpensesByCategoryExport($lines, $d['frCompany']?->name ?? 'All companies', $d['frFrom'], $d['frTo']),
            'Expense Summary by Category ' . $d['frFrom'] . ' to ' . $d['frTo'] . '.xlsx'
        );
    }

    public function expensesByCategoryExport(Request $request): StreamedResponse
    {
        $d = $this->expData($request);
        $f = fn ($n) => number_format($n, 2, '.', '');
        $rows = [[$d['frCompany']?->name ?? 'All companies'], ['Expenses by Category', $d['frFrom'] . ' to ' . $d['frTo']], ['Category', 'Code', 'Amount', 'Previous period', 'Change %', 'Share %']];
        foreach ($d['ex']['sections'] as $s) {
            $rows[] = [$s['label']];
            foreach ($s['rows'] as $r) {
                $rows[] = [$r['name'], $r['code'], $f($r['amount']), $f($r['previous']), $r['change'] === null ? '' : round($r['change'], 1), round($r['share'], 1)];
            }
            $rows[] = ['Total for ' . $s['label'], '', $f($s['total']), $f($s['previous'])];
        }
        $rows[] = ['TOTAL', '', $f($d['ex']['total']), $f($d['ex']['previous_total'])];

        return $this->csv('expenses-by-category-' . $d['frFrom'] . '-to-' . $d['frTo'] . '.csv', $rows);
    }
}
