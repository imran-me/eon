<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\SalaryTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * SUPERSEDED — unrouted. The list lives on the merged classification screen
     * (ExpenseClassificationController). Left in place because deleting it would
     * also orphan the view it renders; nothing calls either.
     */
    public function index(Request $request)
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');

        $query = ExpenseCategory::select('expense_categories.*')
            ->with('account:id,code,name')
            ->leftJoin('users', 'users.id', '=', 'expense_categories.user_id')
            ->leftJoin('companies', 'companies.id', '=', 'expense_categories.company_id')
            ->orderBy('users.name', 'asc')
            ->orderBy('companies.name', 'asc')
            ->orderBy('expense_categories.name', 'asc');

        // Users without "view all expense" are locked to their own company,
        // same convention as Salary Manage / Loan / Advance Salary / Payslip —
        // except that a category with no company is SHARED by the whole group, so
        // it belongs in everyone's list. Filtering it out would hide the nine
        // categories nearly every expense is actually booked against, and leave a
        // company-locked user staring at a list that does not explain their own
        // ledger. They can see the shared rows; only "view all expense" holders
        // may change one, since a shared row is every company's.
        $ownedOrShared = fn ($id) => fn ($q) => $q
            ->whereNull('expense_categories.company_id')
            ->orWhere('expense_categories.company_id', $id);

        if (!$canViewAll && !empty($userCompanyId)) {
            $query->where($ownedOrShared($userCompanyId));
        } elseif ($request->has('company_id') && !empty($request->company_id)) {
            $query->where($ownedOrShared($request->company_id));
        }

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('expense_categories.user_id', $request->user_id);
        }

        if ($request->filled('name')) {
            $query->where('expense_categories.name', $request->name);
        }

        if ($request->filled('status')) {
            $query->whereDate('expense_categories.status', $request->status);
        }

        // Counted over the whole filtered set, before paging. The view used to
        // derive active/inactive from the current page while total came from the
        // paginator, so the three cards described different things and did not
        // add up. `unmapped` is here because an unmapped category silently posts
        // to the general expense account — worth a number on the page, not a
        // discovery made later in the ledger.
        $categoryStats = [
            'active'   => (clone $query)->where('expense_categories.status', 1)->count(),
            'inactive' => (clone $query)->where('expense_categories.status', 0)->count(),
            'unmapped' => (clone $query)->whereNull('expense_categories.account_id')->count(),
        ];

        $datas = $query->paginate(20);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();

        // Expense accounts to map a category onto. The chart of accounts is global
        // by design, so this is not company-scoped.
        $accounts = \App\Models\Account::where('status', 1)
            ->where('type', 'expense')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $fallbackAccount = \App\Models\Account::where('code', config('accounts.general_expense'))->first(['id', 'code', 'name']);
        $companies = (!$canViewAll && !empty($userCompanyId))
            ? Company::where('id', $userCompanyId)->get()
            : Company::orderBy('name')->get();

        return view('expense-categories.index', compact(
            'datas',
            'users',
            'companies',
            'accounts',
            'fallbackAccount',
            'categoryStats'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('expense-categories.create-modal');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }
  
        // Company-locked users can only create categories for their own
        // company, regardless of what's posted; only "view all expense"
        // holders can target a different company via the request.
        //
        // Left blank by such a holder it stays NULL, which means SHARED — offered
        // to every company. `?: null` is what makes that work: the select posts an
        // empty string, and an empty string in a nullable bigint lands as 0, a
        // company id that matches nothing and would hide the category everywhere.
        $userCompanyId = auth()->user()->company_id;
        $companyId = (!auth()->user()->can('view all expense') && !empty($userCompanyId))
            ? $userCompanyId
            : ($request->company_id ?: null);

        $data = ExpenseCategory::create([
            'user_id' => auth()->id(),
            'company_id' => $companyId,
            // Which chart-of-accounts expense account this category posts to.
            // Left blank, the posting falls back to the general expense account,
            // which is why an unmapped category shows a warning in the list.
            'account_id' => $request->account_id ?: null,
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status ? 1 : 0
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.expense-categories.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('expense-categories.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $id = $request->id;
        $data = ExpenseCategory::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }

        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');

        // A shared category is every company's, so editing one is a group-wide
        // act and stays with "view all expense". Said plainly, because "belongs to
        // a different company" would be a lie about a row that belongs to no
        // company and would send someone hunting for the owner.
        if (!$canViewAll && !empty($userCompanyId)) {
            abort_if(
                $data->company_id === null,
                403,
                'This category is shared by every company — only a group-wide expense role can change it.'
            );

            abort_if(
                (int) $data->company_id !== (int) $userCompanyId,
                403,
                'This category belongs to a different company.'
            );
        }

        $validated = $request->validate([
            'name' => 'required',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        // Blank means SHARED, not company 0 — see the note in store().
        $companyId = (!$canViewAll && !empty($userCompanyId))
            ? $userCompanyId
            : ($request->company_id ?: null);

        $data->update([
            // 'user_id' => $request->user_id,
            'company_id' => $companyId,
            // Which chart-of-accounts expense account this category posts to.
            // Left blank, the posting falls back to the general expense account,
            // which is why an unmapped category shows a warning in the list.
            'account_id' => $request->account_id ?: null,
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status ? 1 : 0
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $data
            ]);
        }

        return redirect('/super-admin/airport')->with('success', 'Data updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = ExpenseCategory::find($request->item_id);
            if ($item) {
                $userCompanyId = auth()->user()->company_id;
                if (!auth()->user()->can('view all expense') && !empty($userCompanyId)) {
                    // Same rule as update(): a shared category is group-wide, and
                    // deleting one would take it out of every company's picker.
                    if ($item->company_id === null) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This category is shared by every company — only a group-wide expense role can delete it.'
                        ]);
                    }

                    if ((int) $item->company_id !== (int) $userCompanyId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This category belongs to a different company.'
                        ]);
                    }
                }
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
    }

    public function getEmployeeSalary(Request $request)
    {
        try {
            $data = ExpenseCategory::where('user_id', $request->user_id)->orderBy('id', 'ASC')->get();
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => 'Data Found Successfully.'
        ]);
    }
}
