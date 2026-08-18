<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\ExpenseDepartment;
use App\Models\ExpenseSubCategory;
use Illuminate\Http\Request;

/**
 * Department, Category and Sub-category on one screen.
 *
 * The three used to be separate pages behind three tabs, which made a single
 * chain — Company → Department → Category → Sub-category — look like three
 * unrelated setup screens, and forced two page loads to add a category and the
 * sub-category under it. This lists all three together.
 *
 * Read-only: every Add / Edit / Delete still posts to the existing
 * ExpenseDepartmentController, ExpenseCategoryController and
 * ExpenseSubCategoryController endpoints, which keep their own validation and
 * company rules. Nothing about who may change what moved here.
 *
 * Each panel pages independently (`dept_page`, `cat_page`, `sub_page`) so paging
 * one does not throw the other two back to page 1.
 */
class ExpenseClassificationController extends Controller
{
    /** Rows per panel. Lower than the old 20 because three tables share the page. */
    private const PER_PANEL = 10;

    public function index(Request $request)
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');
        $locked = ! $canViewAll && ! empty($userCompanyId);

        // One company filter drives all three panels — they describe one chain,
        // so filtering the middle of it and not the ends would be a lie.
        $companyFilter = $locked ? (int) $userCompanyId : ($request->filled('company_id') ? (int) $request->company_id : null);
        $search = trim((string) $request->input('search', ''));
        $status = $request->filled('status') ? (int) $request->status : null;

        return view('expense-classification.index', [
            'departments'      => $this->departments($companyFilter, $search, $status),
            'categories'       => $this->categories($companyFilter, $search, $status),
            'subCategories'    => $this->subCategories($companyFilter, $search, $status),
            'companies'        => $locked ? Company::where('id', $userCompanyId)->get() : Company::orderBy('name')->get(),
            'accounts'         => Account::where('status', 1)->where('type', 'expense')->orderBy('code')->get(['id', 'code', 'name']),
            'fallbackAccount'  => Account::where('code', config('accounts.general_expense'))->first(['id', 'code', 'name']),
            'parentCategories' => $this->parentCategoryOptions($locked, $userCompanyId ? (int) $userCompanyId : null),
            'counts'           => $this->counts($companyFilter),
            'canViewAll'       => $canViewAll,
        ]);
    }

    /**
     * The three old list URLs (/expense-departments, /expense-categories,
     * /expense-subcategories) land here. Kept so bookmarks and the non-AJAX
     * redirect()->route() fallbacks inside the category and sub-category
     * controllers still resolve to something real.
     */
    public function redirectLegacy(Request $request)
    {
        return redirect()->route('role.expense-classification.index', [
            'role' => $request->route('role'),
        ]);
    }

    /**
     * Departments are strictly company-owned — that is what lets the expense form
     * fill the company in from the department.
     */
    private function departments(?int $companyId, string $search, ?int $status)
    {
        $query = ExpenseDepartment::select('expense_departments.*')
            ->leftJoin('companies', 'companies.id', '=', 'expense_departments.company_id')
            ->withCount('expenses')
            ->orderBy('companies.name')
            ->orderBy('expense_departments.name');

        if ($companyId) {
            $query->where('expense_departments.company_id', $companyId);
        }

        if ($search !== '') {
            $query->where('expense_departments.name', 'like', '%' . $search . '%');
        }

        if ($status !== null) {
            $query->where('expense_departments.status', $status);
        }

        return $query->paginate(self::PER_PANEL, ['*'], 'dept_page')->withQueryString();
    }

    /**
     * A category with no company is SHARED — it belongs in every company's list,
     * so a company filter must let the NULL rows through. Same rule the standalone
     * page used; see ExpenseCategoryController::index() for why.
     */
    private function categories(?int $companyId, string $search, ?int $status)
    {
        $query = ExpenseCategory::select('expense_categories.*')
            ->with('account:id,code,name')
            ->withCount('subCategories')
            ->leftJoin('companies', 'companies.id', '=', 'expense_categories.company_id')
            ->orderBy('companies.name')
            ->orderBy('expense_categories.name');

        if ($companyId) {
            $query->where(fn ($q) => $q
                ->whereNull('expense_categories.company_id')
                ->orWhere('expense_categories.company_id', $companyId));
        }

        if ($search !== '') {
            $query->where('expense_categories.name', 'like', '%' . $search . '%');
        }

        if ($status !== null) {
            $query->where('expense_categories.status', $status);
        }

        return $query->paginate(self::PER_PANEL, ['*'], 'cat_page')->withQueryString();
    }

    private function subCategories(?int $companyId, string $search, ?int $status)
    {
        $query = ExpenseSubCategory::select('expense_sub_categories.*')
            ->with(['expense_category:id,name,account_id', 'expense_category.account:id,code,name', 'account:id,code,name'])
            ->leftJoin('companies', 'companies.id', '=', 'expense_sub_categories.company_id')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expense_sub_categories.expense_category_id')
            ->orderBy('expense_categories.name')
            ->orderBy('expense_sub_categories.name');

        if ($companyId) {
            $query->where('expense_sub_categories.company_id', $companyId);
        }

        if ($search !== '') {
            $query->where('expense_sub_categories.name', 'like', '%' . $search . '%');
        }

        if ($status !== null) {
            $query->where('expense_sub_categories.status', $status);
        }

        return $query->paginate(self::PER_PANEL, ['*'], 'sub_page')->withQueryString();
    }

    /**
     * Parent options for the sub-category form. Deliberately NOT the filtered,
     * paged category list: you must be able to attach a sub-category to a category
     * that is on page 2, or that the current filter hides.
     */
    private function parentCategoryOptions(bool $locked, ?int $userCompanyId)
    {
        $query = ExpenseCategory::where('status', 1)->orderBy('name');

        // The sub-category controller refuses a parent from another company for a
        // locked user, so offering one would only produce a 403.
        if ($locked) {
            $query->where('company_id', $userCompanyId);
        }

        return $query->get(['id', 'name', 'company_id']);
    }

    /**
     * Panel headline numbers, counted over the whole filtered set rather than the
     * visible page — the old category page derived active/inactive from the current
     * page while the total came from the paginator, so its three cards disagreed.
     */
    private function counts(?int $companyId): array
    {
        $dept = ExpenseDepartment::query();
        $cat  = ExpenseCategory::query();
        $sub  = ExpenseSubCategory::query();

        if ($companyId) {
            $dept->where('company_id', $companyId);
            $cat->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
            $sub->where('company_id', $companyId);
        }

        return [
            'departments'      => (clone $dept)->count(),
            'departments_on'   => (clone $dept)->where('status', 1)->count(),
            'categories'       => (clone $cat)->count(),
            'categories_on'    => (clone $cat)->where('status', 1)->count(),
            // An unmapped category posts silently to the general expense account,
            // so it is worth a number on the page rather than a later discovery
            // in the ledger.
            'categories_unmapped' => (clone $cat)->whereNull('account_id')->count(),
            'sub_categories'      => (clone $sub)->count(),
            'sub_categories_on'   => (clone $sub)->where('status', 1)->count(),
            // Same reasoning as categories_unmapped, one level down: an unmapped
            // sub-category quietly inherits its category's account, which is
            // right for "Newspaper & Magazine" and wrong for "Gas Bill".
            'sub_categories_unmapped' => (clone $sub)->whereNull('account_id')->count(),
        ];
    }
}
