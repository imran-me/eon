<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\Column;
use App\Models\TaskActivityLog;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminTaskController extends Controller
{
    /**
     * Display the task report metrics.
     */
    public function taskReport(Request $request)
    {
        $query = Task::with([
            'column', 
            'board', 
            'board.project',
            'labels'
        ]);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('column_id')) {
            $query->where('column_id', $request->column_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('assigned_user_id')) {
            $assignedUserId = (int) $request->assigned_user_id;
            $query->whereHas('users', function($q) use ($assignedUserId) {
                $q->where('users.id', $assignedUserId);
            });
        }

        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', Carbon::parse($request->start_date)->startOfDay());
        }

        if ($request->filled('end_date')) {
            $query->where('due_date', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        $tasks = $query->orderBy('created_at', 'desc')->get();
        $now = Carbon::now();

        $summary = [
            'total_tasks' => $tasks->count(),
            'by_priority' => [
                'high' => $tasks->where('priority', 'high')->count(),
                'medium' => $tasks->where('priority', 'medium')->count(),
                'low' => $tasks->where('priority', 'low')->count(),
            ],
            'by_status' => $tasks->groupBy(function($t) {
                return $t->column ? $t->column->name : 'Unassigned';
            })->map->count(),
            'overdue' => $tasks->filter(function($task) use ($now) {
                if (empty($task->due_date)) {
                    return false;
                }

                $statusName = strtolower(trim($task->column->name ?? ''));
                if (in_array($statusName, ['done', 'completed', 'complete', 'closed'], true)) {
                    return false;
                }

                return Carbon::parse($task->due_date)->endOfDay()->lt($now);
            })->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Task report retrieved successfully.',
            'data' => [
                'summary' => $summary,
            ]
        ]);
    }

    /**
     * Display a listing of personal tasks (List).
     */
    public function index(Request $request)
    {
        $query = Task::with(['column', 'board.project', 'labels', 'users']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('board_id')) {
            $query->where('board_id', $request->board_id);
        }
        if ($request->filled('column_id')) {
            $query->where('column_id', $request->column_id);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('assigned_user_id')) {
            $assignedUserId = (int) $request->assigned_user_id;
            $query->whereHas('users', function($q) use ($assignedUserId) {
                $q->where('users.id', $assignedUserId);
            });
        }

        $tasks = $query->orderBy('created_at', 'desc')->paginate(30);

        return response()->json([
            'success' => true,
            'message' => 'Tasks retrieved successfully.',
            'data' => $tasks
        ]);
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'project_id' => 'required|exists:projects,id',
            'board_id' => 'required|exists:boards,id',
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_users' => 'nullable|array',
            'assigned_users.*' => 'exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $position = Task::where('column_id', $request->column_id)->max('position') + 1;

        $assignedUsers = collect($request->input('assigned_users', []))
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values()
            ->all();

        $assignedTo = $request->filled('assigned_to') ? (int) $request->assigned_to : ($assignedUsers[0] ?? null);

        $task = Task::create([
            'company_id' => $request->company_id,
            'board_id' => $request->board_id,
            'column_id' => $request->column_id,
            'project_id' => $request->project_id,
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'start_date' => $request->filled('start_date') ? Carbon::parse($request->start_date)->format('Y-m-d H:i:s') : null,
            'due_date' => $request->filled('due_date') ? Carbon::parse($request->due_date)->format('Y-m-d H:i:s') : null,
            'assigned_to' => $assignedTo,
            'position' => $position,
            'created_by' => Auth::id(),
        ]);

        $this->logActivity($task->id, 'task_created', "created the task");

        if (!empty($assignedUsers)) {
            $task->users()->sync($assignedUsers);
            foreach ($assignedUsers as $userId) {
                $user = User::find($userId);
                if ($user && $user->id != Auth::id()) {
                    $this->logActivity($task->id, 'assignee_added', "added a new assignee {$user->name}");
                    // Notifications omitted for brevity
                }
            }
            if (class_exists(NotificationService::class)) {
                try {
                    NotificationService::notifyTaskAssigned($task, $assignedUsers);
                    NotificationService::notifyTaskCreated($task, $assignedUsers);
                } catch (\Throwable $th) {}
            }
        }

        if ($request->has('label_ids')) {
            $task->labels()->sync($request->label_ids);
        }

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully.',
            'data' => $task->load('users')
        ], 201);
    }

    /**
     * Display the specified task.
     */
    public function show($id)
    {
        $task = Task::with(['users', 'column', 'board.project', 'labels', 'attachments', 'comments.user'])->find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Task details retrieved successfully.',
            'data' => $task
        ]);
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, $id)
    {
        $task = Task::with('users')->find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'column_id' => 'sometimes|required|exists:columns,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_users' => 'nullable|array',
            'assigned_users.*' => 'exists:users,id',
            'assigned_to' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'label_ids' => 'nullable|array',
            'label_ids.*' => 'exists:labels,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $task->only(['priority', 'start_date', 'due_date', 'column_id', 'title', 'description']);

        $data = $request->only(['title', 'description', 'priority', 'start_date', 'due_date', 'column_id']);

        if (isset($data['start_date']) && $data['start_date']) {
            $data['start_date'] = Carbon::parse($data['start_date'])->format('Y-m-d H:i:s');
        } elseif ($request->has('start_date') && empty($data['start_date'])) {
            $data['start_date'] = null;
        }

        if (isset($data['due_date']) && $data['due_date']) {
            $data['due_date'] = Carbon::parse($data['due_date'])->format('Y-m-d H:i:s');
        } elseif ($request->has('due_date') && empty($data['due_date'])) {
            $data['due_date'] = null;
        }
        
        if ($request->has('priority') && empty($data['priority'])) {
            $data['priority'] = null;
        }

        $task->update($data);

        // Movement checking
        if (isset($data['column_id']) && $oldValues['column_id'] != $data['column_id']) {
            $oldCol = Column::find($oldValues['column_id']);
            $newCol = Column::find($data['column_id']);
            if ($newCol) {
                $this->logActivity($task->id, 'column_changed', "moved to {$newCol->name}");
            }
        }

        if ($request->has('assigned_users')) {
            $newAssignees = collect($request->input('assigned_users', []))
                ->filter()
                ->map(fn ($userId) => (int) $userId)
                ->unique()
                ->values()
                ->all();

            $task->users()->sync($newAssignees);

            if ($request->filled('assigned_to')) {
                $task->assigned_to = (int) $request->assigned_to;
            } else {
                $task->assigned_to = $newAssignees[0] ?? null;
            }

            $task->save();
        } elseif ($request->filled('assigned_to')) {
            $task->assigned_to = (int) $request->assigned_to;
            $task->save();
        }

        if ($request->has('label_ids')) {
            $task->labels()->sync($request->input('label_ids', []));
        }

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully.',
            'data' => $task->fresh()->load('users', 'column')
        ]);
    }

    /**
     * Remove the specified task.
     */
    public function destroy($id)
    {
        $task = Task::with(['users', 'labels', 'attachments'])->find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task Not Found!'
            ], 404);
        }

        try {
            $task->users()->detach();
            $task->labels()->detach();
            if(method_exists($task, 'links')) $task->links()->delete();
            if(method_exists($task, 'activityLogs')) $task->activityLogs()->delete();
            if(method_exists($task, 'comments')) $task->comments()->delete();
            
            if(method_exists($task, 'attachments')) {
                $task->attachments()->each(function ($attachment) {
                    $filePath = public_path($attachment->file_path);
                    if (file_exists($filePath)) { @unlink($filePath); }
                    $attachment->delete();
                });
            }

            $task->delete();

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    private function logActivity($taskId, $activityType, $description)
    {
        try {
            TaskActivityLog::create([
                'task_id' => $taskId,
                'user_id' => Auth::id(),
                'activity_type' => $activityType,
                'description' => $description
            ]);
        } catch (\Throwable $th) {}
    }
}
