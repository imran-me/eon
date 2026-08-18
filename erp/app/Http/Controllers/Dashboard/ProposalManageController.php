<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProposalManageController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view proposal', only: ['index']),
            new Middleware('permission:create proposal', only: ['create', 'store']),
            new Middleware('permission:edit proposal', only: ['edit', 'update']),
            new Middleware('permission:delete proposal', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Proposal::with('deal');
        if ($request->filled('proposal_no')) {
            $query->where('proposal_no', 'like', '%' . $request->proposal_no . '%');
        }
        if ($request->filled('deal_id')) {
            $query->where('deal_id', $request->deal_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('proposal_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('proposal_date', '<=', $request->date_to);
        }

        $datas = $query->orderBy('created_at', 'desc')->paginate(30);
        $deals = Deal::orderBy('deal_name', 'asc')->get();
        //for proposal unique number generation
        $proposalCount = Proposal::count();
        $proposalNo = 'PROP-' . str_pad($proposalCount + 1, 5, '0', STR_PAD_LEFT);
        return view('proposals.index', compact(
            'datas',
            'deals',
            'proposalNo'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deal_id'           => 'required|integer|exists:deals,id',
            'proposal_no'       => 'required|string',
            'proposal_date'     => 'required|date|before_or_equal:today',
            'valid_until'       => 'required|date|after:proposal_date',
            'status'            => 'required|string|in:draft,sent,approved,rejected',
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
        $data = Proposal::updateOrCreate([
            'proposal_no' => $request->proposal_no
        ],[
            'deal_id' => $request->deal_id,
            'proposal_date' => $request->proposal_date,
            'valid_until' => $request->valid_until,
            'status' => $request->status,
            'terms' => $request->terms,
            'description' => $request->description
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('dashboard.proposals.index')->with('success', 'Data created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = Proposal::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'deal_id'           => 'required|integer|exists:deals,id',
            'proposal_no'       => 'required|string',
            'proposal_date'     => 'required|date|before_or_equal:today',
            'valid_until'       => 'required|date|after:proposal_date',
            'status'            => 'required|string|in:draft,sent,approved,rejected',
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
                'proposal_no'       => $request->proposal_no,
                'proposal_date'     => $request->proposal_date,
                'valid_until'       => $request->valid_until,
                'status'            => $request->status,
                'terms'             => $request->terms,
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
            'data' => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $data = Proposal::find($request->item_id);
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
