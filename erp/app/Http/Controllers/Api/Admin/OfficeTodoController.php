<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfficeTodo;
use App\Models\OfficeTodoAssignee;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OfficeTodoController extends Controller
{
    public function index(Request $request)
    {
        $query = OfficeTodo::with([
            'company:id,name',
            'department:id,name',
            'creator:id,name,image',
            'assignees:id,name,image,email',
        ]);

        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('is_self')) {
            $query->where('is_self', (bool) $request->is_self);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('due_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('due_date', '<=', $request->date_to);
        }
        // show only my own todos
        if ($request->boolean('mine')) {
            $query->where('created_by', auth()->id());
        }

        $todos = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Office todos retrieved successfully.',
            'data'    => $todos,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'company_id'      => 'nullable|integer|exists:companies,id',
            'department_id'   => 'nullable|integer|exists:departments,id',
            'start_date'      => 'nullable|date',
            'due_date'        => 'nullable|date|after_or_equal:start_date',
            'priority'        => 'nullable|in:low,medium,high',
            'status'          => 'nullable|in:pending,in_progress,completed',
            'attachment'      => 'nullable|file|max:5120',
            'is_self'         => 'nullable|boolean',
            'assigned_to'     => 'nullable|array',
            'assigned_to.*'   => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('office-todos/attachments', 'public');
            }

            $isSelf = $request->boolean('is_self', empty($request->assigned_to));

            $todo = OfficeTodo::create([
                'company_id'    => $request->company_id,
                'department_id' => $request->department_id,
                'created_by'    => auth()->id(),
                'title'         => $request->title,
                'description'   => $request->description,
                'start_date'    => $request->start_date,
                'due_date'      => $request->due_date,
                'priority'      => $request->priority ?? 'medium',
                'status'        => $request->status ?? 'pending',
                'attachment'    => $attachmentPath,
                'is_self'       => $isSelf,
            ]);

            if (!$isSelf && !empty($request->assigned_to)) {
                $assignees = collect($request->assigned_to)->unique()->map(fn ($uid) => [
                    'office_todo_id' => $todo->id,
                    'user_id'        => $uid,
                    'status'         => 'pending',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                OfficeTodoAssignee::insert($assignees->toArray());
            }

            DB::commit();

            $todo->load(['creator:id,name,image', 'assignees:id,name,image,email']);

            if (!$isSelf && !empty($request->assigned_to)) {
                NotificationService::notifyOfficeTodoAssigned($todo, collect($request->assigned_to)->map(fn ($uid) => (int) $uid)->all());
            }

            return response()->json([
                'success' => true,
                'message' => 'Office todo created successfully.',
                'data'    => $todo,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id)
    {
        $todo = OfficeTodo::with([
            'company:id,name',
            'department:id,name',
            'creator:id,name,image',
            'assignees:id,name,image,email',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Office todo retrieved successfully.',
            'data'    => $todo,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $todo = OfficeTodo::findOrFail($id);
        $oldValues = $todo->only(['company_id', 'department_id', 'title', 'description', 'start_date', 'due_date', 'priority', 'status']);
        $oldAssigneeIds = $todo->assignees()->pluck('users.id')->map(fn ($value) => (int) $value)->all();

        $validator = Validator::make($request->all(), [
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'company_id'      => 'nullable|integer|exists:companies,id',
            'department_id'   => 'nullable|integer|exists:departments,id',
            'start_date'      => 'nullable|date',
            'due_date'        => 'nullable|date|after_or_equal:start_date',
            'priority'        => 'nullable|in:low,medium,high',
            'status'          => 'nullable|in:pending,in_progress,completed',
            'attachment'      => 'nullable|file|max:5120',
            'is_self'         => 'nullable|boolean',
            'assigned_to'     => 'nullable|array',
            'assigned_to.*'   => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $attachmentPath = $todo->attachment;
            if ($request->hasFile('attachment')) {
                if ($attachmentPath) {
                    Storage::disk('public')->delete($attachmentPath);
                }
                $attachmentPath = $request->file('attachment')->store('office-todos/attachments', 'public');
            }

            $isSelf = $request->boolean('is_self', empty($request->assigned_to));
            $assignedUserIds = collect($request->assigned_to ?? [])->filter()->map(fn ($uid) => (int) $uid)->unique()->values();

            $todo->update([
                'company_id'    => $request->company_id,
                'department_id' => $request->department_id,
                'title'         => $request->title,
                'description'   => $request->description,
                'start_date'    => $request->start_date,
                'due_date'      => $request->due_date,
                'priority'      => $request->priority ?? $todo->priority,
                'status'        => $request->status ?? $todo->status,
                'attachment'    => $attachmentPath,
                'is_self'       => $isSelf,
            ]);

            // Sync assignees if provided
            if ($request->has('assigned_to')) {
                $newIds = $assignedUserIds;
                $existing = OfficeTodoAssignee::where('office_todo_id', $todo->id)
                    ->pluck('user_id')
                    ->values();

                // remove de-assigned
                OfficeTodoAssignee::where('office_todo_id', $todo->id)
                    ->whereNotIn('user_id', $newIds)
                    ->delete();

                // add newly assigned (preserve existing status)
                $toAdd = $newIds->diff($existing);
                if ($toAdd->isNotEmpty()) {
                    $rows = $toAdd->map(fn ($uid) => [
                        'office_todo_id' => $todo->id,
                        'user_id'        => $uid,
                        'status'         => 'pending',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                    OfficeTodoAssignee::insert($rows->toArray());
                }
            }

            DB::commit();

            $todo->load(['creator:id,name,image', 'assignees:id,name,image,email']);

            $currentAssigneeIds = $todo->assignees->pluck('id')->map(fn ($value) => (int) $value)->all();
            $changedFields = array_filter([
                'company_id' => $oldValues['company_id'] != $todo->company_id,
                'department_id' => $oldValues['department_id'] != $todo->department_id,
                'title' => $oldValues['title'] != $todo->title,
                'description' => $oldValues['description'] != $todo->description,
                'start_date' => (string) $oldValues['start_date'] !== (string) $todo->start_date,
                'due_date' => (string) $oldValues['due_date'] !== (string) $todo->due_date,
                'priority' => $oldValues['priority'] != $todo->priority,
                'status' => $oldValues['status'] != $todo->status,
            ]);

            if (!empty($currentAssigneeIds) && !empty($changedFields)) {
                NotificationService::notifyOfficeTodoUpdated($todo, array_values(array_diff($currentAssigneeIds, [auth()->id()])));
            }

            if ($request->has('assigned_to')) {
                $addedIds = array_values(array_diff($currentAssigneeIds, $oldAssigneeIds));
                if (!empty($addedIds)) {
                    NotificationService::notifyOfficeTodoAssigned($todo, $addedIds);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Office todo updated successfully.',
                'data'    => $todo,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(String $id)
    {
        $todo = OfficeTodo::find($id);
        if (!$todo) {
            return response()->json(['success' => false, 'message' => 'Todo not found.'], 404);
        }

        $todo->delete();

        return response()->json(['success' => true, 'message' => 'Office todo deleted successfully.']);
    }

    // Admin can update overall todo status
    public function updateStatus(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $todo = OfficeTodo::with('assignees')->findOrFail($id);
        $oldStatus = $todo->status;
        $todo->update(['status' => $request->status]);

        $recipientIds = $todo->assignees->pluck('id')->map(fn ($value) => (int) $value)->all();
        if (!empty($todo->created_by)) {
            $recipientIds[] = (int) $todo->created_by;
        }
        NotificationService::notifyOfficeTodoStatusChanged($todo, $recipientIds, (string) $oldStatus, (string) $request->status);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully.',
            'data'    => $todo,
        ]);
    }

    // Get all employees for assignment dropdown
    public function employees()
    {
        $employees = User::where(function ($q) {
            $q->where('is_super_admin', '!=', 1)->orWhereNull('is_super_admin');
        })
            ->where('status', 'active')
            ->select('id', 'name', 'email', 'image')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $employees,
        ]);
    }
}
