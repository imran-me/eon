<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Notice;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NoticeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Notice::with('company','department');
        if ($request->has('title') && !empty($request->title)) {
            $query->where('title', 'like', '%'.$request->title.'%');
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('publish_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('publish_date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $datas = $query->orderBy('created_at', 'desc')->paginate(30);
        
        // $companies = Company::orderBy('name', 'asc')->get();
        // $departments = Department::orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Notices retrieved successfully.',
            'data' => $datas,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $existingNotice = Notice::where('title', $request->title)->first();

        $validator = Validator::make($request->all(), [
            'company_id'                => 'nullable|integer|exists:companies,id',
            'department_id'             => 'nullable|integer|exists:departments,id',
            'title'                     => 'required|string',
            'description'               => 'required|string',
            'status'                    => 'required|string|in:draft,published'
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
        $data = Notice::updateOrCreate([
            'title' => $request->title
        ],[
            'company_id' => $request->company_id,
            'department_id' => $request->department_id,
            'created_by' => auth()->user()->id,
            'description' => $request->description,
            'publish_date' => $request->publish_date,
            'expiry_date' => $request->expiry_date,
            'status' => $request->status,
        ]);

        if ($data->status === 'published') {
            $action = !$existingNotice || $existingNotice->status !== 'published' ? 'published' : 'updated';
            NotificationService::notifyNoticePublished($data, $action);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notices created successfully.',
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = Notice::findOrFail($request->id);
        $oldStatus = $data->status;

        $validator = Validator::make($request->all(), [
            'company_id'                => 'nullable|integer|exists:companies,id',
            'department_id'             => 'nullable|integer|exists:departments,id',
            'title'                     => 'required|string',
            'description'               => 'required|string',
            'status'                    => 'required|string|in:draft,published'
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            $data->update([
                'company_id' => $request->company_id,
                'department_id' => $request->department_id,
                'created_by' => auth()->user()->id,
                'title' => $request->title,
                'description' => $request->description,
                'publish_date' => $request->publish_date,
                'expiry_date' => $request->expiry_date,
                'status' => $request->status
            ]);

            if ($data->status === 'published') {
                $action = $oldStatus !== 'published' ? 'published' : 'updated';
                NotificationService::notifyNoticePublished($data, $action);
            }
            
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);    
        }

        return response()->json([
            'success' => true,
            'message' => 'Notices updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $data = Notice::find($request->item_id);
        if ($data) {
            $data->delete();
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Notice Info Not Found!'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notices deleted successfully.'
        ]);
    }
}
