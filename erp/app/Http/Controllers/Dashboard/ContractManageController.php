<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ContractManageController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract', only: ['index']),
            new Middleware('permission:create contract', only: ['store']),
            new Middleware('permission:edit contract', only: ['update']),
            new Middleware('permission:delete contract', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Contract::with('deal','customer', 'project', 'contractType')->orderBy('contract_no', 'asc');
        if ($request->filled('contract_no')) {
            $query->where('contract_no', 'like', '%' . $request->contract_no . '%');
        }
        if ($request->filled('deal_id')) {
            $query->where('deal_id', $request->deal_id);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('contract_type_id')) {
            $query->where('contract_type_id', $request->contract_type_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $datas = $query->paginate(30);
        $deals = Deal::orderBy('deal_name', 'asc')->get();
        $customers = Customer::orderBy('name', 'asc')->get();
        $projects = Project::orderBy('project_name', 'asc')->get();
        $contractTypes = ContractType::orderBy('name', 'asc')->get();
        //contract unique number generation
        $contractCount = Contract::count();
        $contractNo = 'CONT-' . str_pad($contractCount + 1, 5, '0', STR_PAD_LEFT);

        return view('contracts.index', compact(
            'datas',
            'deals',
            'customers',
            'projects',
            'contractTypes',
            'contractNo'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deal_id'           => 'required|integer|exists:deals,id',
            'customer_id'       => 'required|integer|exists:customers,id',
            'project_id'        => 'required|integer|exists:projects,id',
            'contract_type_id'  => 'required|integer|exists:contract_types,id',
            'contract_no'       => 'required|string|unique:contracts,contract_no',
            'contract_value'    => 'required|numeric|min:0',
            'status'            => 'required|string|in:draft,signed,expired',
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

        // Create
        $data = Contract::updateOrCreate([
            'contract_no' => $request->contract_no
        ],[
            'deal_id' => $request->deal_id,
            'customer_id' => $request->customer_id,
            'project_id' => $request->project_id,
            'contract_type_id' => $request->contract_type_id,
            'contract_date' => $request->contract_date,
            'valid_until' => $request->valid_until,
            'contract_value' => $request->contract_value,
            'status' => $request->status,
            'description' => $request->description,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.contracts.index')->with('success', 'Data created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = Contract::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'deal_id'           => 'required|integer|exists:deals,id',
            'customer_id'       => 'required|integer|exists:customers,id',
            'project_id'        => 'required|integer|exists:projects,id',
            'contract_type_id'  => 'required|integer|exists:contract_types,id',
            'contract_no'       => 'required|string|unique:contracts,contract_no,' . $data->id,
            'contract_value'    => 'required|numeric|min:0',
            'status'            => 'required|string|in:draft,signed,expired',
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
        try {
    
            $data->update([
                'deal_id' => $request->deal_id,
                'customer_id' => $request->customer_id,
                'project_id' => $request->project_id,
                'contract_type_id' => $request->contract_type_id,
                'contract_no' => $request->contract_no,
                'contract_date' => $request->contract_date,
                'valid_until' => $request->valid_until,
                'contract_value' => $request->contract_value,
                'status' => $request->status,
                'description' => $request->description
            ]);
            
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);    
        }

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $data = Contract::find($request->item_id);
            if ($data) {
                $data->delete();
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
}
