<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Bank;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\ExpenseDepartment;
use App\Models\ExpenseSubCategory;
use App\Models\RecurringExpense;
use App\Services\ExpenseClassificationService;
use App\Services\RecurringExpenseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RecurringExpenseController implements HasMiddleware
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
        if (! Schema::hasTable('recurring_expenses')) {
            return redirect()->route('role.expenses.index', ['role' => $request->route('role')])
                ->with('error', 'Recurring Expenses is waiting for its database migration.');
        }

        $user = auth()->user();
        $canViewAll = $user->can('view all expense');
        $companyId = ! $canViewAll ? $user->company_id : null;
        $query = RecurringExpense::with(['company:id,name,short_name', 'expenseDepartment:id,name', 'bank:id,name', 'lines.account:id,code,name', 'generations'])
            ->when($companyId, fn ($builder) => $builder->where('company_id', $companyId))
            ->latest();
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $templates = $query->paginate(20)->withQueryString();
        $companies = $companyId ? Company::whereKey($companyId)->get() : Company::orderBy('name')->get();
        $departments = ExpenseDepartment::active()->when($companyId, fn ($builder) => $builder->where('company_id', $companyId))->orderBy('name')->get();
        $banks = Bank::where('status', 1)->when($companyId, fn ($builder) => $builder->where('company_id', $companyId))->orderBy('name')->get();
        $accounts = Account::whereIn('id', app(ExpenseClassificationService::class)->mainExpenseAccountIds())->orderBy('name')->get();
        $classification = app(ExpenseClassificationService::class)->payload($companyId ? (int) $companyId : null);
        $pageData = [
            'classification' => $classification,
            'departments' => $departments->map(fn ($department) => [
                'id' => $department->id,
                'name' => $department->name,
                'company_id' => $department->company_id,
            ])->values(),
            'accounts' => $accounts->map(fn ($account) => [
                'id' => $account->id,
                'label' => '['.$account->code.'] '.$account->name,
            ])->values(),
            'moneyAccounts' => $banks->map(fn ($bank) => [
                'id' => $bank->id,
                'name' => $bank->name,
                'company_id' => $bank->company_id,
                'type' => $bank->type,
            ])->values(),
            'role' => $request->route('role'),
            'storeUrl' => route('role.expenses.recurring.store', ['role' => $request->route('role')]),
            'updateBase' => url('/'.$request->route('role').'/expenses/recurring'),
        ];

        return view('recurring-expenses.index', compact('templates', 'companies', 'departments', 'banks', 'pageData'));
    }

    public function store(Request $request, RecurringExpenseService $generator)
    {
        $this->ensureInstalled();
        $data = $this->validated($request);
        [$template, $generated] = DB::transaction(function () use ($data, $generator) {
            $template = RecurringExpense::create($this->header($data) + ['created_by' => auth()->id()]);
            $this->replaceLines($template, $data['lines']);

            $generated = $template->is_active && $generator->generate($template, now()->startOfMonth());

            return [$template, (bool) $generated];
        });

        $message = "Recurring expense “{$template->name}” saved.";
        $message .= $generated
            ? ' This month’s pending expense draft was created now.'
            : ' Future pending drafts will be created automatically each month.';

        return back()->with('success', $message);
    }

    public function update(Request $request, string $role, int $recurringExpense, RecurringExpenseService $generator)
    {
        $this->ensureInstalled();
        $recurringExpense = RecurringExpense::findOrFail($recurringExpense);
        $this->authorise($recurringExpense);
        $data = $this->validated($request);
        $generated = DB::transaction(function () use ($recurringExpense, $data, $generator) {
            $recurringExpense->update($this->header($data));
            $this->replaceLines($recurringExpense, $data['lines']);

            return (bool) ($recurringExpense->is_active && $generator->generate($recurringExpense, now()->startOfMonth()));
        });

        return back()->with('success', $generated
            ? 'Recurring expense updated and this month’s pending draft was created. Previously generated expenses were not changed.'
            : 'Recurring expense updated. Previously generated expenses were not changed.');
    }

    public function destroy(string $role, int $recurringExpense)
    {
        $this->ensureInstalled();
        $recurringExpense = RecurringExpense::findOrFail($recurringExpense);
        $this->authorise($recurringExpense);
        $recurringExpense->delete();

        return back()->with('success', 'Recurring expense stopped. Existing generated expenses remain in history.');
    }

    private function validated(Request $request): array
    {
        $allowed = app(ExpenseClassificationService::class)->mainExpenseAccountIds()->all();
        $data = $request->validate([
            'description' => 'nullable|string|max:1000',
            'company_id' => 'required|exists:companies,id',
            'expense_department_id' => 'required|exists:expense_departments,id',
            'payment_mode' => 'required|in:cash,mobile_banking,bank_transfer,digital_wallet,other',
            'bank_id' => [Rule::requiredIf(in_array($request->input('payment_mode'), ['bank_transfer', 'mobile_banking', 'digital_wallet'], true)), 'nullable', 'exists:banks,id'],
            'is_active' => 'nullable|boolean',
            'lines' => 'required|array|min:1|max:30',
            'lines.*.expense_category_id' => 'required|exists:expense_categories,id',
            'lines.*.expense_sub_category_id' => 'nullable|exists:expense_sub_categories,id',
            'lines.*.account_id' => ['required', Rule::in($allowed)],
            'lines.*.description' => 'required|string|max:255',
            'lines.*.amount' => 'required|numeric|min:0.01',
        ]);

        $companyId = (int) $data['company_id'];
        $data['generation_day'] = 1;
        $data['start_month'] = now()->format('Y-m');
        $data['end_month'] = null;
        abort_if(! auth()->user()->can('view all expense') && $companyId !== (int) auth()->user()->company_id, 403);
        abort_unless(ExpenseDepartment::whereKey($data['expense_department_id'])->where('company_id', $companyId)->exists(), 422, 'Department must belong to the selected company.');
        if (! empty($data['bank_id'])) {
            $bank = Bank::whereKey($data['bank_id'])->where('company_id', $companyId)->first();
            abort_unless($bank, 422, 'Money account must belong to the selected company.');

            $requiredType = match ($data['payment_mode']) {
                'bank_transfer' => 'bank',
                'mobile_banking' => 'mobile_banking',
                'digital_wallet' => 'digital_wallet',
                default => null,
            };
            abort_if($requiredType && $bank->type !== $requiredType, 422, 'Choose a money account that matches the payment method.');
        }
        if ($data['payment_mode'] === 'cash') {
            $data['bank_id'] = null;
        }

        foreach ($data['lines'] as $line) {
            app(ExpenseClassificationService::class)->resolveLine($line, $companyId, null, null);
        }
        $data['name'] = $this->scheduleName($data['lines']);

        return $data;
    }

    private function header(array $data): array
    {
        return [
            'name' => $data['name'], 'description' => $data['description'] ?? null,
            'company_id' => $data['company_id'], 'expense_department_id' => $data['expense_department_id'],
            'generation_day' => $data['generation_day'],
            'start_month' => Carbon::createFromFormat('Y-m', $data['start_month'])->startOfMonth(),
            'end_month' => ! empty($data['end_month']) ? Carbon::createFromFormat('Y-m', $data['end_month'])->startOfMonth() : null,
            'payment_mode' => $data['payment_mode'], 'bank_id' => $data['bank_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function replaceLines(RecurringExpense $template, array $lines): void
    {
        $template->lines()->delete();
        foreach (array_values($lines) as $index => $line) {
            $resolved = app(ExpenseClassificationService::class)->resolveLine($line, (int) $template->company_id, null, null);
            $template->lines()->create($resolved + [
                'description' => $line['description'], 'amount' => $line['amount'], 'sort_order' => $index,
            ]);
        }
    }

    private function authorise(RecurringExpense $template): void
    {
        abort_if(! auth()->user()->can('view all expense') && (int) $template->company_id !== (int) auth()->user()->company_id, 403);
    }

    private function ensureInstalled(): void
    {
        abort_unless(Schema::hasTable('recurring_expenses'), 503, 'Recurring Expenses is waiting for its database migration.');
    }

    private function scheduleName(array $lines): string
    {
        $first = $lines[0];
        $name = ! empty($first['expense_sub_category_id'])
            ? ExpenseSubCategory::whereKey($first['expense_sub_category_id'])->value('name')
            : ExpenseCategory::whereKey($first['expense_category_id'])->value('name');
        $name = $name ?: 'Monthly Fixed Expense';
        $additional = count($lines) - 1;

        return $additional > 0 ? $name.' + '.$additional.' more' : $name;
    }
}
