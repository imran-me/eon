<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonthlyProfitController extends Controller
{
    public function index()
    {
        $years = $this->availableYears();
        return view('reports.monthly-profit', compact('years'));
    }

    public function data(Request $request)
    {
        $year = (int) ($request->year ?? date('Y'));
        [$months, $annual] = $this->buildProfitData($year);
        return response()->json(compact('months', 'annual', 'year'));
    }

    public function printView(Request $request)
    {
        $year    = (int) ($request->year ?? date('Y'));
        $company = \App\Models\Company::first();

        $valid         = ['ticket', 'visa', 'flight', 'file'];
        $selectedTypes = array_values(array_intersect(
            $request->input('types', $valid),
            $valid
        ));
        if (empty($selectedTypes)) $selectedTypes = $valid;

        [$months, $annual] = $this->buildProfitData($year);

        return view('reports.print-profit', compact('months', 'annual', 'year', 'company', 'selectedTypes'));
    }

    private function buildProfitData(int $year): array
    {
        $ticketRows = DB::table('ticket_sales as ts')
            ->join('ticket_sale_items as tsi', 'tsi.ticket_sale_id', '=', 'ts.id')
            ->join('ticket_purchases as tp', 'tp.id', '=', 'tsi.ticket_purchase_id')
            ->whereYear('ts.sale_date', $year)
            ->selectRaw('MONTH(ts.sale_date) as m, SUM(tsi.price) as revenue, SUM(tp.amount) as cost')
            ->groupByRaw('MONTH(ts.sale_date)')
            ->get()->keyBy('m');

        $visaRows = DB::table('visa_sales as vs')
            ->join('visa_sale_items as vsi', 'vsi.visa_sale_id', '=', 'vs.id')
            ->join('visa_processes as vp', 'vp.id', '=', 'vsi.visa_process_id')
            ->whereYear('vs.voucher_date', $year)
            ->selectRaw('MONTH(vs.voucher_date) as m, SUM(vsi.sale_price) as revenue, SUM(vp.costing_price) as cost')
            ->groupByRaw('MONTH(vs.voucher_date)')
            ->get()->keyBy('m');

        $flightRows = DB::table('contract_flights')
            ->whereYear('departure_at', $year)
            ->selectRaw('MONTH(departure_at) as m, SUM(revenue) as revenue, SUM(cost_price) as cost')
            ->groupByRaw('MONTH(departure_at)')
            ->get()->keyBy('m');

        $fileRows = DB::table('contract_file_sales')
            ->whereYear('sale_date', $year)
            ->selectRaw('MONTH(sale_date) as m, SUM(total_amount) as revenue, SUM(vendor_cost) as cost')
            ->groupByRaw('MONTH(sale_date)')
            ->get()->keyBy('m');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $t  = $ticketRows->get($m);
            $v  = $visaRows->get($m);
            $fl = $flightRows->get($m);
            $fi = $fileRows->get($m);

            $tRev  = (float)($t?->revenue  ?? 0); $tCost  = (float)($t?->cost  ?? 0);
            $vRev  = (float)($v?->revenue  ?? 0); $vCost  = (float)($v?->cost  ?? 0);
            $fRev  = (float)($fl?->revenue ?? 0); $fCost  = (float)($fl?->cost ?? 0);
            $fiRev = (float)($fi?->revenue ?? 0); $fiCost = (float)($fi?->cost ?? 0);

            $totRev  = $tRev + $vRev + $fRev + $fiRev;
            $totCost = $tCost + $vCost + $fCost + $fiCost;

            $months[] = [
                'month'       => $m,
                'month_name'  => date('F', mktime(0, 0, 0, $m, 1)),
                'month_short' => date('M',  mktime(0, 0, 0, $m, 1)),
                'ticket'      => ['revenue' => $tRev,  'cost' => $tCost,  'profit' => $tRev  - $tCost],
                'visa'        => ['revenue' => $vRev,  'cost' => $vCost,  'profit' => $vRev  - $vCost],
                'flight'      => ['revenue' => $fRev,  'cost' => $fCost,  'profit' => $fRev  - $fCost],
                'file'        => ['revenue' => $fiRev, 'cost' => $fiCost, 'profit' => $fiRev - $fiCost],
                'total'       => [
                    'revenue' => $totRev,
                    'cost'    => $totCost,
                    'profit'  => $totRev - $totCost,
                    'margin'  => $totRev > 0 ? round((($totRev - $totCost) / $totRev) * 100, 1) : 0,
                ],
            ];
        }

        $annual = [];
        foreach (['ticket','visa','flight','file'] as $mod) {
            $annual[$mod] = [
                'revenue' => array_sum(array_column(array_column($months, $mod), 'revenue')),
                'cost'    => array_sum(array_column(array_column($months, $mod), 'cost')),
                'profit'  => array_sum(array_column(array_column($months, $mod), 'profit')),
            ];
        }
        $annual['total'] = [
            'revenue' => array_sum(array_column(array_column($months, 'total'), 'revenue')),
            'cost'    => array_sum(array_column(array_column($months, 'total'), 'cost')),
            'profit'  => array_sum(array_column(array_column($months, 'total'), 'profit')),
        ];
        $annual['total']['margin'] = $annual['total']['revenue'] > 0
            ? round(($annual['total']['profit'] / $annual['total']['revenue']) * 100, 1)
            : 0;

        return [$months, $annual];
    }

    private function availableYears(): array
    {
        $years = collect();
        try { $years->push((int) DB::table('ticket_sales')->whereNotNull('sale_date')->min(DB::raw('YEAR(sale_date)'))); } catch (\Throwable) {}
        try { $years->push((int) DB::table('visa_sales')->whereNotNull('voucher_date')->min(DB::raw('YEAR(voucher_date)'))); } catch (\Throwable) {}
        try { $years->push((int) DB::table('contract_flights')->whereNotNull('departure_at')->min(DB::raw('YEAR(departure_at)'))); } catch (\Throwable) {}
        try { $years->push((int) DB::table('contract_file_sales')->whereNotNull('sale_date')->min(DB::raw('YEAR(sale_date)'))); } catch (\Throwable) {}

        $min = $years->filter()->min() ?: date('Y');
        return range(date('Y'), $min);
    }
}
