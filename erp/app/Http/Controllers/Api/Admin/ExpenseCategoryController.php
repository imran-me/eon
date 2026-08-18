<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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
    public function index(Request $request)
    {
        $query = ExpenseCategory::select('expense_categories.*')
            ->leftJoin('users', 'users.id', '=', 'expense_categories.user_id')
            ->leftJoin('companies', 'companies.id', '=', 'expense_categories.company_id')
            ->orderBy('users.name', 'asc')
            ->orderBy('companies.name', 'asc')
            ->orderBy('expense_categories.name', 'asc');

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('expense_categories.user_id', $request->user_id);
        }

        // Narrowing to a company keeps the shared categories and adds that
        // company's own, matching the web list and the expense picker. A category
        // names a company only when it belongs to that one alone; matching on the
        // column by itself would return nothing, since most categories are shared.
        if ($request->has('company_id') && !empty($request->company_id)) {
            $query->where(fn ($q) => $q
                ->whereNull('expense_categories.company_id')
                ->orWhere('expense_categories.company_id', $request->company_id));
        }

        if ($request->filled('name')) {
            $query->where('expense_categories.name', $request->name);
        }

        if ($request->filled('status')) {
            $query->whereDate('expense_categories.status', $request->status);
        }

        $datas = $query->paginate(20);        
        // $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        // $companies = Company::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Expense categories retrieved successfully.',
            'data' => $datas
        ]);
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
            'name' => 'required'          
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }
  
        $data = ExpenseCategory::create([
            'user_id' => auth()->id(),
            // Blank means SHARED — offered to every company. `?: null` matters:
            // an empty string in a nullable bigint lands as 0, a company id that
            // matches nothing, which would hide the category everywhere.
            'company_id' => $request->company_id ?: null,
            'name' => $request->name,    
            'description' => $request->description,
            'status' => $request->status ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense Category created successfully.',
            'data' => $data
        ]);
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
        $data = ExpenseCategory::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Expense Category Info Not Found!'
            ]);
        }
        $validated = $request->validate([
            'name' => 'required'
        ]);

        $data->update([
            // 'user_id' => $request->user_id,
            // Blank means SHARED, not company 0 — see store().
            'company_id' => $request->company_id ?: null,
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense Category updated successfully.',
            'data' => $data
        ]);
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
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense Category Info Not Found!'
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
            'message' => 'Expense Category deleted successfully.'
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
            'message' => 'Expense Categories Found Successfully.'
        ]);
    }
}
