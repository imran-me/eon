<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadFollowupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = LeadFollowup::with('lead','user')->orderBy('created_at', 'asc');

        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('followup_date')) {
            $query->where('followup_date', '>=', $request->followup_date);
        }
        if ($request->filled('followup_date')) {
            $query->where('followup_date', '<=', $request->followup_date);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $datas = $query->paginate(30);

        // $leads = Lead::orderBy('name', 'asc')->get();
        // $employees = User::role('employee')->get();

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully.',
            'data' => $datas
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id'    => 'required|integer|exists:leads,id',
            'user_id'    => 'required|integer|exists:users,id',
            'type'       => 'required|string|in:call,meeting,email,whatsapp,note',
            'status'     => 'required|string|in:pending,completed,cancelled',
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
        $data = LeadFollowup::Create([
            'lead_id' => $request->lead_id,
            'user_id' => $request->user_id,
            'type' => $request->type,
            'description' => $request->description,
            'followup_date' => $request->followup_date,
            'status' => $request->status,
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
        $data = LeadFollowup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'lead_id'    => 'required|integer|exists:leads,id',
            'user_id'    => 'required|integer|exists:users,id',
            'type'       => 'required|string|in:call,meeting,email,whatsapp,note',
            'status'     => 'required|string|in:pending,completed,cancelled',
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
                'user_id' => $request->user_id,
                'description' => $request->description,
                'type' => $request->type,
                'followup_date' => $request->followup_date,
                'status' => $request->status,
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
    public function destroy(Request $request, string $id)
    {
        try {
            $data = LeadFollowup::find($id);
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
