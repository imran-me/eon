<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DealManageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Deal::with('lead','dealAgent','dealWatcher','product');
        if ($request->filled('deal_name')) {
            $query->where('deal_name', 'like', '%' . $request->deal_name . '%');
        }
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }
        if ($request->filled('deal_agent')) {
            $query->where('deal_agent', $request->deal_agent);
        }
        if ($request->filled('stage')) {
            $query->where('stage', $request->stage);
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        
        $datas = $query->orderBy('created_at', 'desc')->paginate(30);

        // $leads = Lead::orderBy('name', 'asc')->get();
        // $products = Product::where('is_active', 1)->orderBy('name', 'asc')->get();
        // $deal_agents = User::role('agent')->get();
        // $deal_watchers = User::role('employee')->get();

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
            'deal_name'         => 'required|string|max:255|unique:deals,deal_name',
            'lead_id'           => 'required|integer|exists:leads,id',
            // 'deal_agent'        => 'required|integer|exists:users,id',
            'deal_watcher'      => 'required|integer|exists:users,id',
            'product_id'        => 'required|integer|exists:products,id',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        // Create
        $data = Deal::updateOrCreate([
            'deal_name' => $request->deal_name
        ],[
            'lead_id' => $request->lead_id,
            'deal_agent' => $request->deal_agent,
            'deal_watcher' => $request->deal_watcher,
            'product_id' => $request->product_id,
            'pipeline' => $request->pipeline,
            'stage' => $request->stage,
            'amount' => $request->amount,
            'closing_date' => $request->closing_date,
            'notes' => $request->notes
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
        $data = Deal::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'deal_name'         => 'required|string|max:255|unique:deals,deal_name,' . $data->id,
            'lead_id'           => 'required|integer|exists:leads,id',
            // 'deal_agent'        => 'required|integer|exists:users,id',
            'deal_watcher'      => 'required|integer|exists:users,id',
            'product_id'        => 'required|integer|exists:products,id',
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
                'lead_id' => $request->lead_id,
                'deal_agent' => $request->deal_agent,
                'deal_watcher' => $request->deal_watcher,
                'deal_name' => $request->deal_name,
                'product_id' => $request->product_id,
                'pipeline' => $request->pipeline,
                'stage' => $request->stage,
                'amount' => $request->amount,
                'closing_date' => $request->closing_date,
                'notes' => $request->notes,
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
    public function destroy(string $id)
    {
        $item = Deal::find($id);
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
