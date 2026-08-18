<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EstimateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $req_subdatas = [];
        $query = Estimate::with('deal');

        if ($request->filled('estimate_no')) {
            $query->where('estimate_no', 'like', '%' . $request->estimate_no . '%');
        }
        if ($request->filled('deal_id')) {
            $query->where('deal_id', $request->deal_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('estimate_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('estimate_date', '<=', $request->date_to);
        }

        $datas = $query->orderBy('created_at', 'desc')->paginate(30);
        // $deals = Deal::orderBy('deal_name', 'asc')->get();
        //for estimate unique number generation
        // $estimateCount = Estimate::count();
        // $estimateNo = 'EST-' . str_pad($estimateCount + 1, 5, '0', STR_PAD_LEFT);

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully.',
            'data' => $datas,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deal_id'           => 'required|integer|exists:deals,id',
            'estimate_no'       => 'required|string',
            'estimate_date'     => 'required|date|before_or_equal:today',
            'valid_until'       => 'required|date|after:estimate_date',
            'status'            => 'required|string|in:draft,pending,sent,approved,rejected',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }
  
        $data = Estimate::create([
            'deal_id' => $request->deal_id,
            'estimate_no' => $request->estimate_no,
            'estimate_date' => $request->estimate_date,
            'valid_until' => $request->valid_until,
            'status' => $request->status,
            'total_amount' => $request->total_amount,
            'description' => $request->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.',
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = Estimate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'deal_id'           => 'required|integer|exists:deals,id',
            'estimate_no'       => 'required|string',
            'estimate_date'     => 'required|date',
            'valid_until'       => 'required|date|after:estimate_date',
            'status'            => 'required|string|in:draft,pending,sent,approved,rejected',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        try {
            $data->update([
                'deal_id'           => $request->deal_id,
                'estimate_no'       => $request->estimate_no,
                'estimate_date'     => $request->estimate_date,
                'valid_until'       => $request->valid_until,
                'status'            => $request->status,
                'total_amount'      => $request->total_amount,
                'description'       => $request->description
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
            'data' => $data
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $item = Estimate::findOrFail($id);
        if ($item) {
            $item->delete();
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
    }
}
