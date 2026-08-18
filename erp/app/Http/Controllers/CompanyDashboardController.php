<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Company;
use App\Models\ContractFileSale;
use App\Models\ContractFlightBooking;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Lead;
use App\Models\PaymentSchedule;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\User;
use App\Models\VisaSale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:view dashboard')->only(['show']);
    }

    public function show($role,Request $request, $company)
    {
        $company = Company::findOrFail($company);
        $period = $request->input('period', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }
        [$selectedYear, $selectedMonth] = array_map('intval', explode('-', $period));
        $selectedYear  = max(2000, min((int) now()->year + 1, $selectedYear));
        $selectedMonth = max(1, min(12, $selectedMonth));
        $period = sprintf('%04d-%02d', $selectedYear, $selectedMonth);

        $startDate = Carbon::createFromDate($selectedYear, $selectedMonth, 1)->startOfMonth();
        $endDate   = $startDate->copy()->endOfMonth();

        $isTravel = (int) $company->id === 2;

        // Financial stats for selected period.
        // Travel doesn't sell through the generic Sale/Purchase system — its real
        // revenue and cost live in the ticket/visa/contract-specific tables.
        if ($isTravel) {
            $totalSales = $this->travelRevenue($company->id, $startDate->toDateString(), $endDate->toDateString());
            $totalPurchase = TicketPurchase::where('company_id', $company->id)
                ->whereBetween('purchase_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->sum('amount');
        } else {
            $totalSales = Sale::where('company_id', $company->id)
                ->whereBetween('sale_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->sum('total_amount');

            $totalPurchase = Purchase::where('company_id', $company->id)
                ->whereBetween('purchase_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->sum('total_amount');
        }

        $totalExpense = Expense::where('company_id', $company->id)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('amount');

        // Rent broken out of the generic Expense total for a clearer P&L view.
        //
        // Matched against shared categories as well as this company's own: a
        // category names a company only when it is that company's, and the common
        // ones name none. Looking only at `company_id = $company->id` would have
        // found nothing and quietly reported zero rent. Ids plural because a
        // company may keep its own "Rent" beside the shared one, and both spend
        // against this line.
        $rentCategoryIds = ExpenseCategory::where('name', 'Rent')
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id))
            ->pluck('id');

        $totalRent = $rentCategoryIds->isNotEmpty()
            ? Expense::where('company_id', $company->id)
                ->whereIn('expense_category_id', $rentCategoryIds)
                ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->sum('amount')
            : 0;

        $otherExpenses = $totalExpense - $totalRent;

        $selectedMonthValues = array_unique([
            (string) $selectedMonth,
            str_pad((string) $selectedMonth, 2, '0', STR_PAD_LEFT),
        ]);

        $totalSalaryPaid = EmployeeSalary::query()
            ->join('users', 'users.id', '=', 'employee_salaries.user_id')
            ->where('users.company_id', $company->id)
            ->where('employee_salaries.year', $selectedYear)
            ->whereIn('employee_salaries.month', $selectedMonthValues)
            ->whereIn('employee_salaries.status', ['Paid', 'paid'])
            ->sum('employee_salaries.net_salary');

        // Bank balance (sum across company banks)
        $bankAccountIds = Bank::where('company_id', $company->id)
            ->where('status', 1)
            ->whereNotNull('account_id')
            ->pluck('account_id');

        $bankBalance = \App\Models\Account::whereIn('id', $bankAccountIds)
            ->with(['items' => function ($q) use ($endDate) {
                $q->whereHas('journalEntry', function ($q2) use ($endDate) {
                    $q2->whereDate('date', '<=', $endDate->toDateString())
                       ->whereNull('deleted_at');
                })->whereNull('journal_items.deleted_at');
            }])
            ->get()
            ->sum(function ($account) {
                $debits  = $account->items->sum('debit');
                $credits = $account->items->sum('credit');
                return $account->opening_balance + ($debits - $credits);
            });

        $bankCount = Bank::where('company_id', $company->id)->where('status', 1)->count();

        // HR stats
        $totalEmployees = User::where('company_id', $company->id)
            ->where('status', 'active')
            ->where('is_super_admin', 0)
            ->count();

        $netProfit = $totalSales - $totalPurchase - $totalRent - $otherExpenses - $totalSalaryPaid;

        // ── Yesterday snapshot — daily P&L is Sales/Purchase/Expense only;
        // Salary and Rent are monthly costs, shown separately above, not prorated.
        $yesterday = Carbon::yesterday()->toDateString();
        $yesterdaySales = $isTravel
            ? $this->travelRevenue($company->id, $yesterday, $yesterday)
            : Sale::where('company_id', $company->id)->whereDate('sale_date', $yesterday)->sum('total_amount');
        $yesterdayPurchase = $isTravel
            ? TicketPurchase::where('company_id', $company->id)->whereDate('purchase_date', $yesterday)->sum('amount')
            : Purchase::where('company_id', $company->id)->whereDate('purchase_date', $yesterday)->sum('total_amount');
        $yesterdayExpense = Expense::where('company_id', $company->id)->whereDate('expense_date', $yesterday)->sum('amount');
        $yesterdayPL = $yesterdaySales - $yesterdayPurchase - $yesterdayExpense;

        // ── Sales counts by type — Travel's 4 revenue lines, for the selected period
        $salesByType = $isTravel
            ? $this->travelSalesByType($company->id, $startDate->toDateString(), $endDate->toDateString())
            : [];

        // Monthly chart data (full year)
        $monthlyRevenueRaw = $isTravel
            ? $this->travelMonthlyRevenue($company->id, $selectedYear)
            : Sale::where('company_id', $company->id)
                ->whereYear('sale_date', $selectedYear)
                ->selectRaw('MONTH(sale_date) as month_no, SUM(total_amount) as total')
                ->groupBy('month_no')
                ->pluck('total', 'month_no');

        $monthlyPurchaseRaw = $isTravel
            ? TicketPurchase::where('company_id', $company->id)
                ->whereYear('purchase_date', $selectedYear)
                ->selectRaw('MONTH(purchase_date) as month_no, SUM(amount) as total')
                ->groupBy('month_no')
                ->pluck('total', 'month_no')
            : Purchase::where('company_id', $company->id)
                ->whereYear('purchase_date', $selectedYear)
                ->selectRaw('MONTH(purchase_date) as month_no, SUM(total_amount) as total')
                ->groupBy('month_no')
                ->pluck('total', 'month_no');

        $monthlyExpenseRaw = Expense::where('company_id', $company->id)
            ->whereYear('expense_date', $selectedYear)
            ->selectRaw('MONTH(expense_date) as month_no, SUM(amount) as total')
            ->groupBy('month_no')
            ->pluck('total', 'month_no');

        $monthlySalaryPaidRaw = EmployeeSalary::query()
            ->join('users', 'users.id', '=', 'employee_salaries.user_id')
            ->where('users.company_id', $company->id)
            ->where('employee_salaries.year', $selectedYear)
            ->whereIn('employee_salaries.status', ['Paid', 'paid'])
            ->selectRaw('CAST(employee_salaries.month AS UNSIGNED) as month_no, SUM(employee_salaries.net_salary) as total')
            ->groupBy('month_no')
            ->pluck('total', 'month_no');

        $chartLabels  = [];
        $chartRevenue = [];
        $chartPurchase = [];
        $chartExpense = [];
        $chartSalaryPaid = [];

        for ($m = 1; $m <= 12; $m++) {
            $chartLabels[]   = Carbon::create()->month($m)->format('M');
            $chartRevenue[]  = round((float) ($monthlyRevenueRaw[$m]  ?? 0), 2);
            $chartPurchase[] = round((float) ($monthlyPurchaseRaw[$m] ?? 0), 2);
            $chartExpense[]  = round((float) ($monthlyExpenseRaw[$m]  ?? 0), 2);
            $chartSalaryPaid[] = round((float) ($monthlySalaryPaidRaw[$m] ?? 0), 2);
        }

        // Recent transactions
        $recentSales = $isTravel
            ? $this->travelRecentSales($company->id)
            : Sale::where('company_id', $company->id)
                ->with('customer')
                ->latest('sale_date')
                ->limit(6)
                ->get()
                ->map(fn($s) => (object)[
                    'type'   => 'sale',
                    'label'  => 'Invoice #' . $s->invoice_no . ($s->customer ? ' — ' . $s->customer->name : ''),
                    'amount' => $s->total_amount,
                    'status' => $s->payment_status ?? 'paid',
                    'date'   => $s->sale_date,
                ]);

        $recentExpenses = Expense::where('company_id', $company->id)
            ->with('expense_category')
            ->latest('expense_date')
            ->limit(4)
            ->get()
            ->map(fn($e) => (object)[
                'type'   => 'expense',
                'label'  => ($e->expense_category->name ?? 'Expense') . ($e->title ? ' — ' . $e->title : ''),
                'amount' => $e->amount,
                'status' => 'expense',
                'date'   => $e->expense_date,
            ]);

        $recentTransactions = $recentSales->concat($recentExpenses)
            ->sortByDesc('date')
            ->take(8)
            ->values();

        $showVisaReports = (int) $company->id === 2;
        $showEcommerceReports = (int) $company->id === 5;
        $visaFunnelData = [];
        $visaPayment = [];
        $visaLeads = collect();
        $missingDocLeads = collect();
        $ecommerceSalesReport = [];
        $ecommercePurchaseReport = [];
        $ecommerceSalesByStatus = [];
        $ecommercePurchaseByStatus = [];
        $recentEcommerceSales = collect();
        $recentEcommercePurchases = collect();
        $topSellingProducts = collect();
        $topPurchasedProducts = collect();

        if ($showVisaReports) {
            $visaFunnel = DB::table('lead_visas')
                ->select('document_status', DB::raw('count(*) as total'))
                ->groupBy('document_status')
                ->pluck('total', 'document_status')
                ->toArray();

            $visaFunnelData = [
                'Applications'    => DB::table('lead_visas')->count(),
                'Docs Collected'  => ($visaFunnel['collected'] ?? 0) + ($visaFunnel['complete'] ?? 0) + ($visaFunnel['submitted'] ?? 0),
                'Complete'        => ($visaFunnel['complete'] ?? 0) + ($visaFunnel['submitted'] ?? 0),
                'Submitted'       => $visaFunnel['submitted'] ?? 0,
                'Approved (Won)'  => Lead::whereNull('deleted_at')->where('lead_type', 'visa')->where('status', 'won')->count(),
                'Rejected (Lost)' => Lead::whereNull('deleted_at')->where('lead_type', 'visa')->where('status', 'lost')->count(),
            ];

            $visaPayment = DB::table('lead_visas')
                ->select('payment_status', DB::raw('count(*) as total'))
                ->groupBy('payment_status')
                ->pluck('total', 'payment_status')
                ->toArray();

            $visaLeads = Lead::whereNull('deleted_at')
                ->where('lead_type', 'visa')
                ->whereNotIn('status', ['won', 'lost'])
                ->with(['visa.country', 'assignedEmployee:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit(8)
                ->get();

            $missingDocLeads = Lead::whereNull('deleted_at')
                ->where('lead_type', 'visa')
                ->whereNotIn('status', ['won', 'lost'])
                ->whereHas('visaDocuments', fn($q) => $q->where('is_mandatory', true)->where('is_collected', false))
                ->with([
                    'visa.country',
                    'visaDocuments' => fn($q) => $q->where('is_mandatory', true)->where('is_collected', false),
                ])
                ->limit(10)
                ->get();
        }

        if ($showEcommerceReports) {
            $salesQuery = Sale::where('company_id', $company->id)
                ->whereBetween('sale_date', [$startDate->toDateString(), $endDate->toDateString()]);

            $purchaseQuery = Purchase::where('company_id', $company->id)
                ->whereBetween('purchase_date', [$startDate->toDateString(), $endDate->toDateString()]);

            $ecommerceSalesReport = [
                'orders' => (clone $salesQuery)->count(),
                'total' => (clone $salesQuery)->sum('total_amount'),
                'paid' => (clone $salesQuery)->sum('paid_amount'),
                'due' => (clone $salesQuery)->sum('due_amount'),
                'average' => (clone $salesQuery)->avg('total_amount') ?? 0,
            ];

            $ecommercePurchaseReport = [
                'orders' => (clone $purchaseQuery)->count(),
                'total' => (clone $purchaseQuery)->sum('total_amount'),
                'paid' => (clone $purchaseQuery)->sum('paid_amount'),
                'due' => (clone $purchaseQuery)->sum('due_amount'),
                'average' => (clone $purchaseQuery)->avg('total_amount') ?? 0,
            ];

            $ecommerceSalesByStatus = (clone $salesQuery)
                ->selectRaw("COALESCE(payment_status, 'unknown') as status, COUNT(*) as total")
                ->groupBy(DB::raw("COALESCE(payment_status, 'unknown')"))
                ->pluck('total', 'status')
                ->toArray();

            $ecommercePurchaseByStatus = (clone $purchaseQuery)
                ->selectRaw("COALESCE(payment_status, 'unknown') as status, COUNT(*) as total")
                ->groupBy(DB::raw("COALESCE(payment_status, 'unknown')"))
                ->pluck('total', 'status')
                ->toArray();

            $recentEcommerceSales = (clone $salesQuery)
                ->with('customer')
                ->latest('sale_date')
                ->limit(8)
                ->get();

            $recentEcommercePurchases = (clone $purchaseQuery)
                ->with('supplier')
                ->latest('purchase_date')
                ->limit(8)
                ->get();

            $topSellingProducts = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
                ->where('sales.company_id', $company->id)
                ->whereBetween('sales.sale_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->selectRaw("sale_items.product_id, COALESCE(products.name, 'Unknown Product') as product_name, products.sku, SUM(sale_items.quantity) as quantity, SUM(sale_items.subtotal) as total")
                ->groupBy('sale_items.product_id', 'products.name', 'products.sku')
                ->orderByDesc('quantity')
                ->limit(5)
                ->get();

            $topPurchasedProducts = DB::table('purchase_items')
                ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
                ->leftJoin('products', 'products.id', '=', 'purchase_items.product_id')
                ->where('purchases.company_id', $company->id)
                ->whereBetween('purchases.purchase_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->selectRaw("purchase_items.product_id, COALESCE(products.name, 'Unknown Product') as product_name, products.sku, SUM(purchase_items.quantity) as quantity, SUM(purchase_items.subtotal) as total")
                ->groupBy('purchase_items.product_id', 'products.name', 'products.sku')
                ->orderByDesc('quantity')
                ->limit(5)
                ->get();
        }

        $companies = Company::where('status', 1)->get();

        // ── Payment Schedules (company-filtered) ─────────────────────────────
        $scheduleFilter = $request->input('schedule_filter', 'today');
        $companyId = $company->id;

        $buildCompanySchedule = function (string $type) use ($scheduleFilter, $companyId) {
            $q = PaymentSchedule::where('type', $type)
                ->where('company_id', $companyId);
            if ($scheduleFilter === '7days') {
                $rangeEnd = Carbon::today()->addDays(6)->toDateString();
                $q->where(function ($inner) use ($rangeEnd) {
                    $inner->where(function ($i2) use ($rangeEnd) {
                        $i2->whereBetween('scheduled_date', [Carbon::today()->toDateString(), $rangeEnd])
                            ->whereIn('status', ['pending', 'approved']);
                    })->orWhere('status', 'overdue');
                });
            } else {
                $q->where(function ($inner) {
                    $inner->where(function ($i2) {
                        $i2->whereDate('scheduled_date', Carbon::today())
                            ->whereIn('status', ['pending', 'approved']);
                    })->orWhere('status', 'overdue');
                });
            }
            return $q->orderBy('scheduled_date')->get();
        };

        $companyPayable    = $buildCompanySchedule('pay');
        $companyReceivable = $buildCompanySchedule('receive');

        // ── CRM Pipeline & Ongoing Projects (company-filtered) ────────────────
        $pipelineStatuses = ['new', 'contacted', 'qualified', 'proposal_sent', 'negotiation'];

        $pipelineCount = Lead::whereIn('status', $pipelineStatuses)
            ->whereHas('assignedEmployee', fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $pipelineByStage = Lead::whereIn('status', $pipelineStatuses)
            ->whereHas('assignedEmployee', fn ($q) => $q->where('company_id', $companyId))
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $pipelineLeads = Lead::whereIn('status', $pipelineStatuses)
            ->whereHas('assignedEmployee', fn ($q) => $q->where('company_id', $companyId))
            ->with('assignedEmployee:id,name')
            ->latest()
            ->take(5)
            ->get();

        $ongoingCount = Project::where('company_id', $companyId)
            ->whereNotNull('lead_id')
            ->where('status', 'in_progress')
            ->count();

        $ongoingProjects = Project::where('company_id', $companyId)
            ->whereNotNull('lead_id')
            ->where('status', 'in_progress')
            ->with(['lead:id,name', 'customer:id,name'])
            ->latest()
            ->take(5)
            ->get();

        return view('company.dashboard', compact(
            'company', 'companies', 'period', 'selectedYear', 'selectedMonth',
            'startDate', 'endDate', 'isTravel',
            'totalSales', 'totalPurchase', 'totalExpense', 'totalRent', 'otherExpenses', 'totalSalaryPaid',
            'bankBalance', 'bankCount', 'totalEmployees', 'netProfit',
            'yesterday', 'yesterdaySales', 'yesterdayPurchase', 'yesterdayExpense', 'yesterdayPL',
            'salesByType',
            'chartLabels', 'chartRevenue', 'chartPurchase', 'chartExpense', 'chartSalaryPaid',
            'recentTransactions',
            'showVisaReports', 'visaFunnelData', 'visaPayment', 'visaLeads', 'missingDocLeads',
            'showEcommerceReports', 'ecommerceSalesReport', 'ecommercePurchaseReport',
            'ecommerceSalesByStatus', 'ecommercePurchaseByStatus',
            'recentEcommerceSales', 'recentEcommercePurchases',
            'topSellingProducts', 'topPurchasedProducts',
            'scheduleFilter', 'companyPayable', 'companyReceivable',
            'pipelineCount', 'pipelineByStage', 'pipelineLeads',
            'ongoingCount', 'ongoingProjects'
        ));
    }

    /**
     * Travel's real revenue for a date range — sums the 4 sale-type tables
     * (Ticket, Visa, Contract Flight, Contract File) instead of the generic
     * Sale model, which Travel never uses.
     */
    private function travelRevenue(int $companyId, string $fromDate, string $toDate): float
    {
        return (float) TicketSale::where('company_id', $companyId)
                ->whereBetween('sale_date', [$fromDate, $toDate])->sum('total_amount')
            + (float) VisaSale::where('company_id', $companyId)
                ->whereBetween('voucher_date', [$fromDate, $toDate])->sum('total_amount')
            + (float) ContractFlightBooking::where('company_id', $companyId)
                ->whereBetween('created_at', ["{$fromDate} 00:00:00", "{$toDate} 23:59:59"])
                ->sum('total_amount')
            + (float) ContractFileSale::where('company_id', $companyId)
                ->whereBetween('sale_date', [$fromDate, $toDate])->sum('total_amount');
    }

    /**
     * Per-type breakdown (count + amount) of Travel's 4 revenue lines for a date range.
     */
    private function travelSalesByType(int $companyId, string $fromDate, string $toDate): array
    {
        $ticket = TicketSale::where('company_id', $companyId)->whereBetween('sale_date', [$fromDate, $toDate]);
        $visa = VisaSale::where('company_id', $companyId)->whereBetween('voucher_date', [$fromDate, $toDate]);
        $flight = ContractFlightBooking::where('company_id', $companyId)
            ->whereBetween('created_at', ["{$fromDate} 00:00:00", "{$toDate} 23:59:59"]);
        $file = ContractFileSale::where('company_id', $companyId)->whereBetween('sale_date', [$fromDate, $toDate]);

        return [
            'ticket' => [
                'label'  => 'Ticket Sales',
                'count'  => (clone $ticket)->count(),
                'amount' => (float) (clone $ticket)->sum('total_amount'),
            ],
            'visa' => [
                'label'  => 'Visa Sales',
                'count'  => (clone $visa)->count(),
                'amount' => (float) (clone $visa)->sum('total_amount'),
            ],
            'contract_flight' => [
                'label'  => 'Contract Flight Bookings',
                'count'  => (clone $flight)->count(),
                'amount' => (float) (clone $flight)->sum('total_amount'),
            ],
            'contract_file' => [
                'label'  => 'Contract File Sales',
                'count'  => (clone $file)->count(),
                'amount' => (float) (clone $file)->sum('total_amount'),
            ],
        ];
    }

    /**
     * Travel's revenue grouped by calendar month for the year-long trend chart,
     * merged across the 4 sale-type tables. Returns a plain 1-12 keyed array.
     */
    private function travelMonthlyRevenue(int $companyId, int $year): array
    {
        $totals = array_fill(1, 12, 0.0);

        $series = [
            TicketSale::where('company_id', $companyId)->whereYear('sale_date', $year)
                ->selectRaw('MONTH(sale_date) as month_no, SUM(total_amount) as total')
                ->groupBy('month_no')->pluck('total', 'month_no'),
            VisaSale::where('company_id', $companyId)->whereYear('voucher_date', $year)
                ->selectRaw('MONTH(voucher_date) as month_no, SUM(total_amount) as total')
                ->groupBy('month_no')->pluck('total', 'month_no'),
            ContractFlightBooking::where('company_id', $companyId)->whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as month_no, SUM(total_amount) as total')
                ->groupBy('month_no')->pluck('total', 'month_no'),
            ContractFileSale::where('company_id', $companyId)->whereYear('sale_date', $year)
                ->selectRaw('MONTH(sale_date) as month_no, SUM(total_amount) as total')
                ->groupBy('month_no')->pluck('total', 'month_no'),
        ];

        foreach ($series as $monthly) {
            foreach ($monthly as $month => $total) {
                $totals[(int) $month] += (float) $total;
            }
        }

        return $totals;
    }

    /**
     * Recent Travel transactions merged across the 4 sale-type tables,
     * since Travel's activity never appears in the generic Sale model.
     */
    private function travelRecentSales(int $companyId, int $limit = 6)
    {
        $ticket = TicketSale::where('company_id', $companyId)->latest('sale_date')->limit($limit)->get()
            ->map(fn ($t) => (object) [
                'type' => 'sale', 'label' => 'Ticket Sale #' . $t->invoice_no,
                'amount' => $t->total_amount, 'status' => $t->payment_status ?? 'paid', 'date' => $t->sale_date,
            ]);

        $visa = VisaSale::where('company_id', $companyId)->latest('voucher_date')->limit($limit)->get()
            ->map(fn ($v) => (object) [
                'type' => 'sale', 'label' => 'Visa Sale #' . $v->invoice_number,
                'amount' => $v->total_amount, 'status' => $v->payment_status ?? 'paid', 'date' => $v->voucher_date,
            ]);

        $flight = ContractFlightBooking::where('company_id', $companyId)->latest('created_at')->limit($limit)->get()
            ->map(fn ($b) => (object) [
                'type' => 'sale', 'label' => 'Contract Flight #' . $b->booking_number,
                'amount' => $b->total_amount, 'status' => $b->payment_status ?? 'paid', 'date' => $b->created_at,
            ]);

        $file = ContractFileSale::where('company_id', $companyId)->latest('sale_date')->limit($limit)->get()
            ->map(fn ($f) => (object) [
                'type' => 'sale', 'label' => 'Contract File #' . $f->invoice_number,
                'amount' => $f->total_amount, 'status' => $f->payment_status ?? 'paid', 'date' => $f->sale_date,
            ]);

        return $ticket->concat($visa)->concat($flight)->concat($file)
            ->sortByDesc('date')->take($limit)->values();
    }
}
