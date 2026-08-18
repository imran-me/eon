<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use App\Models\LeadReminder;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class LeadReminderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = LeadReminder::with('lead','user')->orderBy('created_at', 'asc');

        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
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
            'data' => $datas,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id'       => 'required|integer|exists:leads,id',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:2000',
            'due_date'      => 'required|date',
            'assigned_to'   => 'required|integer|exists:users,id',
            'status'        => 'required|string|in:pending,completed',
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
        $data = LeadReminder::create([
            'lead_id' => $request->lead_id,
            'title' => $request->title,
            'description' => $request->description,
            'due_date' => $request->due_date,
            'assigned_to' => $request->assigned_to,
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
        $data = LeadReminder::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'lead_id'       => 'required|integer|exists:leads,id',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:2000',
            'due_date'      => 'required|date',
            'assigned_to'   => 'required|integer|exists:users,id',
            'status'        => 'required|string|in:pending,completed',
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
                'title' => $request->title,
                'description' => $request->description,
                'due_date' => $request->due_date,
                'assigned_to' => $request->assigned_to,
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
        $data = LeadReminder::find($id);
        if ($data) {
            $data->delete();
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
