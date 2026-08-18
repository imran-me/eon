<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Models\LeadReminder;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class LeadReminderController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view lead reminder', only: ['index']),
            new Middleware('permission:create lead reminder', only: ['store']),
            new Middleware('permission:edit lead reminder', only: ['update']),
            new Middleware('permission:delete lead reminder', only: ['destroy']),
        ];
    }
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

        $leads = Lead::orderBy('name', 'asc')->get();
        $employees = User::role('employee')->get();

        return view('lead-reminder.index', compact(
            'datas',
            'leads',
            'employees',
        ));
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
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
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

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.lead-reminder.index')->with('success', 'Data created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $data = LeadReminder::findOrFail($request->id);

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
    public function destroy(Request $request)
    {
        try {
            $data = LeadReminder::find($request->item_id);
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
