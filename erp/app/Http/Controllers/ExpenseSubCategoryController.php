<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpenseSubCategoryController extends Controller
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

        $query = ExpenseSubCategory::select('expense_sub_categories.*')
            ->leftJoin('users', 'users.id', '=', 'expense_sub_categories.user_id')
            ->leftJoin('companies', 'companies.id', '=', 'expense_sub_categories.company_id')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expense_sub_categories.expense_category_id')
            ->orderBy('users.name', 'asc')
            ->orderBy('expense_categories.name', 'asc')
            ->orderBy('expense_sub_categories.name', 'asc');

        // Users without "view all expense" are locked to their own company,
        // same convention as Expense Category / Salary Manage / Loan / etc.
        if (!$canViewAll && !empty($userCompanyId)) {
            $query->where('expense_sub_categories.company_id', $userCompanyId);
        } elseif ($request->has('company_id') && !empty($request->company_id)) {
            $query->where('expense_sub_categories.company_id', $request->company_id);
        }

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('expense_sub_categories.user_id', $request->user_id);
        }

        if ($request->has('expense_category_id') && !empty($request->expense_category_id)) {
            $query->where('expense_sub_categories.expense_category_id', $request->expense_category_id);
        }

        if ($request->filled('name')) {
            $query->where('expense_sub_categories.name', $request->name);
        }

        if ($request->filled('status')) {
            $query->whereDate('expense_sub_categories.status', $request->status);
        }

        $datas = $query->paginate(20);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();

        $categoryQuery = ExpenseCategory::orderBy('name')->where('status', 1);
        if (!$canViewAll && !empty($userCompanyId)) {
            $companies = Company::where('id', $userCompanyId)->get();
            $categoryQuery->where('company_id', $userCompanyId);
        } else {
            $companies = Company::orderBy('name')->get();
        }
        $expense_categories = $categoryQuery->get();

        return view('expense-subcategories.index', compact(
            'datas',
            'users',
            'companies',
            'expense_categories'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('expense-subcategories.create-modal');
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
            'expense_category_id' => 'required',
            // Nullable on purpose: an unmapped sub-category falls back to its
            // category's account, which is a real answer rather than a gap. See
            // ExpenseClassificationService::accountFor().
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
  
        // Company-locked users can only create sub-categories for their own
        // company, regardless of what's posted; only "view all expense"
        // holders can target a different company via the request. Also
        // verify the chosen parent category actually belongs to that
        // company, so a company-locked user can't attach to another
        // company's category by guessing its id.
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');
        $companyId = (!$canViewAll && !empty($userCompanyId)) ? $userCompanyId : $request->company_id;

        if (!$canViewAll && !empty($userCompanyId)) {
            $parentCategory = ExpenseCategory::find($request->expense_category_id);
            abort_if(!$parentCategory || (int) $parentCategory->company_id !== (int) $userCompanyId, 403, 'Selected expense category belongs to a different company.');
        }

        $data = ExpenseSubCategory::create([
            'user_id' => auth()->id(),
            'company_id' => $companyId,
            'expense_category_id' => $request->expense_category_id,
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

        return redirect()->route('role.expense-subcategories.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('expense-subcategories.edit-modal', compact('id'));
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
        $data = ExpenseSubCategory::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }

        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');
        abort_if(!$canViewAll && !empty($userCompanyId) && (int) $data->company_id !== (int) $userCompanyId, 403, 'This sub-category belongs to a different company.');

        $validated = $request->validate([
            'name' => 'required',
            'expense_category_id' => 'required',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        $companyId = (!$canViewAll && !empty($userCompanyId)) ? $userCompanyId : $request->company_id;

        if (!$canViewAll && !empty($userCompanyId)) {
            $parentCategory = ExpenseCategory::find($request->expense_category_id);
            abort_if(!$parentCategory || (int) $parentCategory->company_id !== (int) $userCompanyId, 403, 'Selected expense category belongs to a different company.');
        }

        $data->update([
            'company_id' => $companyId,
            'expense_category_id' => $request->expense_category_id,
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
            $item = ExpenseSubCategory::find($request->item_id);
            if ($item) {
                $userCompanyId = auth()->user()->company_id;
                if (!auth()->user()->can('view all expense') && !empty($userCompanyId) && (int) $item->company_id !== (int) $userCompanyId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This sub-category belongs to a different company.'
                    ]);
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

    public function getExpenseSubCategory(Request $request)
    {
        try {
            $data = ExpenseSubCategory::where('expense_category_id', $request->expense_category_id)->orderBy('id', 'ASC')->get();
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
