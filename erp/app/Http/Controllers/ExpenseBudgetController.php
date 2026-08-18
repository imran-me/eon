<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ExpenseBudgetController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view expense', only: ['index']),
            new Middleware('permission:create expense', only: ['store']),
            new Middleware('permission:edit expense', only: ['update']),
            new Middleware('permission:delete expense', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');

        $query = ExpenseBudget::with(['company', 'expense_category', 'user'])
            ->orderBy('company_id')
            ->orderBy('expense_category_id')
            ->orderBy('period');

        // Users without "view all expense" are locked to their own company,
        // same convention as Expense / Expense Category / Expense Sub-Category.
        if (!$canViewAll && !empty($userCompanyId)) {
            $query->where('company_id', $userCompanyId);
        } elseif ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($inner) use ($search) {
                $inner->whereHas('company', function ($companyQuery) use ($search) {
                    $companyQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('short_name', 'like', '%' . $search . '%');
                })->orWhereHas('expense_category', function ($categoryQuery) use ($search) {
                    $categoryQuery->where('name', 'like', '%' . $search . '%');
                });
            });
        }

        $allBudgets = (clone $query)->get();

        // Loaded before the spend map on purpose: spend is measured inside each
        // budget's own period window, so which windows have to be queried is
        // known only once the budgets themselves are in hand.
        $spentMap = $this->buildSpentMap($allBudgets);

        $this->attachBudgetMetrics($allBudgets, $spentMap);

        $datas = (clone $query)->paginate(15)->withQueryString();
        $this->attachBudgetMetrics($datas->getCollection(), $spentMap);

        if (!$canViewAll && !empty($userCompanyId)) {
            $companies = Company::where('id', $userCompanyId)->get();
            // Shared categories included: a category names a company only when it
            // is that company's own, and most name none. Matching on the company
            // alone left this list empty and gave a company-locked user nothing to
            // budget against.
            $expenseCategories = ExpenseCategory::with('company')
                ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $userCompanyId))
                ->orderBy('name')
                ->get();
        } else {
            $companies = Company::orderBy('name')->get();
            $expenseCategories = ExpenseCategory::with('company')->orderBy('name')->get();
        }

        $summary = [
            'total_budgets' => $allBudgets->count(),
            'total_allocated' => $allBudgets->sum('amount'),
            'total_spent' => $allBudgets->sum('spent_amount'),
            'total_remaining' => $allBudgets->sum('remaining_amount'),
            'over_budget' => $allBudgets->where('budget_state', 'over')->count(),
            'at_risk' => $allBudgets->where('budget_state', 'warning')->count(),
        ];

        return view('expense-budgets.index', compact('datas', 'companies', 'expenseCategories', 'summary'));
    }

    /**
     * The categories a company can actually budget against.
     *
     * Its own, plus everything shared. This used to demand `company_id` match the
     * company, which quietly rejected every shared category — and since categories
     * are shared by default, that meant no budget could be saved at all. A budget
     * is company-scoped through its own `company_id`; the category it names does
     * not have to be.
     */
    private function spendableCategory($companyId): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('expense_categories', 'id')->where(
            fn ($query) => $query->where(
                fn ($scope) => $scope->whereNull('company_id')->orWhere('company_id', $companyId)
            )
        );
    }

    public function store(Request $request)
    {
        // Company-locked users can only create budgets for their own
        // company, regardless of what's posted; only "view all expense"
        // holders can target a different company via the request.
        $userCompanyId = auth()->user()->company_id;
        if (!auth()->user()->can('view all expense') && !empty($userCompanyId)) {
            $request->merge(['company_id' => $userCompanyId]);
        }

        $validator = Validator::make($request->all(), [
            'company_id' => ['required', 'exists:companies,id'],
            'expense_category_id' => [
                'required',
                $this->spendableCategory($request->company_id),
            ],
            'period' => ['required', Rule::in(['Weekly', 'Monthly', 'Quarterly', 'Yearly'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'threshold' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $budget = ExpenseBudget::create([
            'user_id' => auth()->id(),
            'company_id' => $request->company_id,
            'expense_category_id' => $request->expense_category_id,
            'period' => $request->period,
            'amount' => $request->amount,
            'threshold' => $request->threshold,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Budget created successfully.',
            'data' => $budget,
        ]);
    }

    public function update($role, Request $request, ExpenseBudget $budget)
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');
        abort_if(!$canViewAll && !empty($userCompanyId) && (int) $budget->company_id !== (int) $userCompanyId, 403, 'This budget belongs to a different company.');

        if (!$canViewAll && !empty($userCompanyId)) {
            $request->merge(['company_id' => $userCompanyId]);
        }

        $validator = Validator::make($request->all(), [
            'company_id' => ['required', 'exists:companies,id'],
            'expense_category_id' => [
                'required',
                $this->spendableCategory($request->company_id),
            ],
            'period' => ['required', Rule::in(['Weekly', 'Monthly', 'Quarterly', 'Yearly'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'threshold' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $budget->update([
            'company_id' => $request->company_id,
            'expense_category_id' => $request->expense_category_id,
            'period' => $request->period,
            'amount' => $request->amount,
            'threshold' => $request->threshold,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Budget updated successfully.',
            'data' => $budget,
        ]);
    }

    public function destroy($role, ExpenseBudget $budget)
    {
        $userCompanyId = auth()->user()->company_id;
        if (!auth()->user()->can('view all expense') && !empty($userCompanyId) && (int) $budget->company_id !== (int) $userCompanyId) {
            return response()->json([
                'success' => false,
                'message' => 'This budget belongs to a different company.'
            ]);
        }

        $budget->delete();

        return response()->json([
            'success' => true,
            'message' => 'Budget deleted successfully.',
        ]);
    }

    /**
     * The stretch of time a budget's figure covers, as [start, end].
     *
     * A "Monthly" budget is a monthly ALLOWANCE, so the only spend it can
     * sensibly be compared against is this month's. Until 2026-08-13 this class
     * summed every expense ever recorded and compared a month's allowance to a
     * lifetime's spend: `period` was stored, shown in the table, and used
     * nowhere in the arithmetic, so every budget drifted to "Over Budget" and
     * stayed there. The window is what makes the number mean something.
     *
     * Memoised because attachBudgetMetrics() runs over two collections and the
     * same four windows would otherwise be rebuilt once per row.
     */
    private array $periodWindows = [];

    private function periodWindow(string $period): array
    {
        return $this->periodWindows[$period] ??= (function () use ($period) {
            $now = now();

            return match ($period) {
                'Weekly'    => [$now->copy()->startOfWeek(),    $now->copy()->endOfWeek()],
                'Quarterly' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
                'Yearly'    => [$now->copy()->startOfYear(),    $now->copy()->endOfYear()],
                // Monthly, and anything the enum grows later — a month is the
                // period the table defaults to, so it is the safe fallback.
                default     => [$now->copy()->startOfMonth(),   $now->copy()->endOfMonth()],
            };
        })();
    }

    /**
     * Which dates the figure on screen covers, in words.
     *
     * Shown beside the period because "Monthly · ৳50,000 · ৳12,400 spent" is
     * ambiguous on its own — the reader cannot tell whether that spend is this
     * month's or all of it, which is the exact confusion the bug above created.
     */
    private function periodWindowLabel(string $period): string
    {
        [$start, $end] = $this->periodWindow($period);

        return match ($period) {
            'Weekly'    => $start->format('j M') . ' – ' . $end->format('j M Y'),
            'Quarterly' => 'Q' . $start->quarter . ' ' . $start->format('Y'),
            'Yearly'    => $start->format('Y'),
            default     => $start->format('M Y'),
        };
    }

    /**
     * Spend per budget window, keyed period|company|category.
     *
     * One query per DISTINCT period rather than one per budget — there are at
     * most four windows however many budgets are on screen, so this stays two or
     * three queries in practice.
     */
    private function buildSpentMap($budgets): array
    {
        $map = [];

        foreach ($budgets->pluck('period')->unique() as $period) {
            [$start, $end] = $this->periodWindow($period);

            $rows = Expense::query()
                ->selectRaw('company_id, expense_category_id, SUM(amount) as total_spent')
                ->where('status', 1)
                // expense_date is a plain `date` column, so a range comparison
                // is exact here and still uses the index.
                ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
                ->groupBy('company_id', 'expense_category_id')
                ->get();

            foreach ($rows as $row) {
                $map[$period . '|' . $row->company_id . '|' . $row->expense_category_id] = (float) $row->total_spent;
            }
        }

        return $map;
    }

    private function attachBudgetMetrics($budgets, $spentMap): void
    {
        $budgets->transform(function ($budget) use ($spentMap) {
            $spent = (float) ($spentMap[$budget->period . '|' . $budget->company_id . '|' . $budget->expense_category_id] ?? 0);
            $usage = $budget->amount > 0 ? (int) round(($spent / $budget->amount) * 100) : 0;

            if ($usage >= 100) {
                $state = 'over';
                $label = 'Over Budget';
                $badge = 'badge-rejected';
                $bar = 'danger';
            } elseif ($usage >= $budget->threshold) {
                $state = 'warning';
                $label = 'Near Limit';
                $badge = 'badge-pending';
                $bar = 'warning';
            } else {
                $state = 'safe';
                $label = 'Under Budget';
                $badge = 'badge-active';
                $bar = 'success';
            }

            $budget->spent_amount = $spent;
            $budget->remaining_amount = $budget->amount - $spent;
            $budget->usage_percent = $usage;
            // Which dates that spend was gathered from. Displayed under the
            // period so the figure cannot be misread as lifetime spend.
            $budget->period_window = $this->periodWindowLabel($budget->period);
            $budget->budget_state = $state;
            $budget->budget_label = $label;
            $budget->badge_class = $badge;
            $budget->bar_class = $bar;

            return $budget;
        });
    }
}