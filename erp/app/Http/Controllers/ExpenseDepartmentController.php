<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ExpenseDepartment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The expense desk's own departments — the units it budgets and reports spending
 * against. Not HR's `departments`, which exists to place people; see the
 * ExpenseDepartment model for why the two are kept apart.
 *
 * Built to the same shape as ExpenseCategoryController so the three setup screens
 * under Expenses behave identically: same company lock, same filters, same modal
 * CRUD over AJAX.
 */
class ExpenseDepartmentController extends Controller
{
    /**
     * SUPERSEDED — unrouted. The list lives on the merged classification screen
     * (ExpenseClassificationController). Left in place because deleting it would
     * also orphan the view it renders; nothing calls either.
     */
    public function index(Request $request)
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');

        $query = ExpenseDepartment::select('expense_departments.*')
            ->leftJoin('users', 'users.id', '=', 'expense_departments.user_id')
            ->leftJoin('companies', 'companies.id', '=', 'expense_departments.company_id')
            ->withCount('expenses')
            ->orderBy('companies.name', 'asc')
            ->orderBy('expense_departments.name', 'asc');

        // Users without "view all expense" are locked to their own company,
        // same convention as Expense Category / Salary Manage / Loan.
        if (!$canViewAll && !empty($userCompanyId)) {
            $query->where('expense_departments.company_id', $userCompanyId);
        } elseif ($request->filled('company_id')) {
            $query->where('expense_departments.company_id', $request->company_id);
        }

        if ($request->filled('search')) {
            $query->where('expense_departments.name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('expense_departments.status', (int) $request->status);
        }

        $datas = $query->paginate(20)->withQueryString();
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $companies = (!$canViewAll && !empty($userCompanyId))
            ? Company::where('id', $userCompanyId)->get()
            : Company::orderBy('name')->get();

        return view('expense-departments.index', compact('datas', 'users', 'companies'));
    }

    public function store(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $validator = Validator::make(
            $request->all() + ['company_id' => $companyId],
            [
                'company_id' => 'required|exists:companies,id',
                'name' => [
                    'required', 'string', 'max:255',
                    // Scoped to the company: "Site" under Construction and "Site"
                    // under Woodart are different departments, but two "Site"
                    // rows under one company are a mistake.
                    Rule::unique('expense_departments', 'name')
                        ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
                ],
                'description' => 'nullable|string|max:255',
            ],
            ['name.unique' => 'That company already has a department with this name.']
        );

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            $data = ExpenseDepartment::create([
                'user_id' => auth()->id(),
                'company_id' => $companyId,
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status ?? 1,
            ]);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully.',
            'data' => $data,
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = ExpenseDepartment::findOrFail($request->item_id ?? $id);
        $this->assertVisible($data);

        $companyId = $this->resolveCompanyId($request) ?: $data->company_id;

        $validator = Validator::make(
            $request->all() + ['company_id' => $companyId],
            [
                'company_id' => 'required|exists:companies,id',
                'name' => [
                    'required', 'string', 'max:255',
                    Rule::unique('expense_departments', 'name')
                        ->ignore($data->id)
                        ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
                ],
                'description' => 'nullable|string|max:255',
            ],
            ['name.unique' => 'That company already has a department with this name.']
        );

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            $data->update([
                'company_id' => $companyId,
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status ?? 1,
            ]);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully.',
            'data' => $data,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $data = ExpenseDepartment::withCount('expenses')->find($request->item_id ?? $id);

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Department not found.']);
        }

        $this->assertVisible($data);

        // Soft-deleting a department that expenses point at would leave those
        // rows naming something the setup screen no longer lists. Retiring it
        // (status off) keeps the history readable and stops new use.
        if ($data->expenses_count > 0) {
            return response()->json([
                'success' => false,
                'message' => $data->expenses_count . ' expense(s) are filed against this department, so it cannot be '
                    . 'deleted. Set it inactive instead — it stays on the old expenses and stops being offered on new ones.',
            ]);
        }

        try {
            $data->delete();
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Department deleted successfully.']);
    }

    /**
     * Company-locked users always write to their own company whatever is posted;
     * only "view all expense" holders may target another.
     */
    private function resolveCompanyId(Request $request): ?int
    {
        $userCompanyId = auth()->user()->company_id;

        if (!auth()->user()->can('view all expense') && !empty($userCompanyId)) {
            return (int) $userCompanyId;
        }

        return $request->filled('company_id') ? (int) $request->company_id : null;
    }

    private function assertVisible(ExpenseDepartment $department): void
    {
        $userCompanyId = auth()->user()->company_id;

        if (!auth()->user()->can('view all expense') && !empty($userCompanyId)) {
            abort_if((int) $department->company_id !== (int) $userCompanyId, 403,
                'That department belongs to a different company.');
        }
    }
}
