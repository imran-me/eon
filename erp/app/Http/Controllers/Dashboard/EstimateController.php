<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EstimateController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view estimate', only: ['index', 'show']),
            new Middleware('permission:create estimate', only: ['store']),
            new Middleware('permission:edit estimate', only: ['update']),
            new Middleware('permission:delete estimate', only: ['destroy']),
        ];
    }
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
        $deals = Deal::orderBy('deal_name', 'asc')->get();
        //for estimate unique number generation
        $estimateCount = Estimate::count();
        $estimateNo = 'EST-' . str_pad($estimateCount + 1, 5, '0', STR_PAD_LEFT);
        return view('estimates.index', compact(
            'datas',
            'deals',
            'estimateNo'
        ));
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
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
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

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.estimates.index')->with('success', 'Data created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = Estimate::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'deal_id'           => 'required|integer|exists:deals,id',
            'estimate_no'       => 'required|string',
            'estimate_date'     => 'required|date',
            'valid_until'       => 'required|date|after:estimate_date',
            'status'            => 'required|string|in:draft,pending,sent,approved,rejected',
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

        return redirect()->route('role.estimates.index')->with('success', 'Data updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $item = Estimate::find($request->item_id);
            if ($item) {
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
}
