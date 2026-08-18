<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Column;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskLink;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($role, Board $board)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $canViewAll = $user->hasPermissionTo('view all tasks');
        if (!$canViewAll) {
            $board = Board::with([
                'board_columns.column.tasks' => function ($q) use ($board, $user) {
                    $q->where('board_id', $board->id)
                        ->where(function ($query) use ($user) {
                            $query->whereHas('users', function ($q2) use ($user) {
                                $q2->where('users.id', $user->id);
                            })
                            ->orWhere('created_by', $user->id);
                        });
                }
            ])->findOrFail($board->id);
        } else {
            $board = Board::with([
                'board_columns.column.tasks' => function ($q) use ($board) {
                    $q->where('board_id', $board->id);
                }
            ])->findOrFail($board->id);
        }

        $project = Project::find($board->project_id);
        $teamMemberIds = $project->team_members ?? [];

        $users = User::whereIn('id', $teamMemberIds)->get();
        // $board->load('columns.tasks.users', 'columns.tasks.labels', 'columns.tasks.creator');

        // Get labels for this project
        $labels = Label::where('project_id', $board->project_id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'board' => $board,
            'users' => $users,
            'labels' => $labels
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'workspace_id' => 'required|exists:companies,id',
            'project_id' => 'required|exists:projects,id',
            'board_id' => 'required|exists:boards,id',
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_to' => 'nullable|exists:users,id',
            'assigned_users' => 'nullable|array',
            'assigned_users.*' => 'exists:users,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);

        $position = Task::where('column_id', $request->column_id)->max('position') + 1;

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->format('Y-m-d H:i:s')
            : null;

        $dueDate = $request->filled('due_date')
            ? Carbon::parse($request->due_date)->format('Y-m-d H:i:s')
            : null;

        $assignedUsers = collect($request->input('assigned_users', []))
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values()
            ->all();

        $assignedTo = $request->filled('assigned_to')
            ? (int) $request->assigned_to
            : ($assignedUsers[0] ?? null);

        $task = Task::create([
            'company_id' => $request->workspace_id,
            'board_id' => $request->board_id,
            'column_id' => $request->column_id,
            'project_id' => $request->project_id,
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority ?? null,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'assigned_to' => $assignedTo,
            'position' => $position,
            'created_by' => auth()->id(),
        ]);

        // Log task creation
        $this->logActivity($task->id, 'task_created', "created the task");

        // Sync assigned users if provided
        if (!empty($assignedUsers)) {
            $task->users()->sync($assignedUsers);
            
            // Log each assigned user
            foreach ($assignedUsers as $userId) {
                $user = User::find($userId);
                if ($user && $user->id != auth()->id()) {
                    $this->logActivity($task->id, 'assignee_added', "added a new assignee {$user->name}");
                    //send mail notification to assigned user
                    sendBrevoMail(
                        $user->email,
                        $user->name,
                        'New Task Assigned: ' . $task->title,
                        "You have been assigned a new task: <strong>{$task->title}</strong><br><br>Click <a href='" . route('role.tasks.board', ['role' => auth()->user()->getRoleNames()->first(), 'board' => $task->board_id]) . "'>here</a> to view the task details."
                    );
                }
            }

            // Send notifications to assigned users
            NotificationService::notifyTaskAssigned($task, $assignedUsers);
            
            // Notify users about new task creation
            NotificationService::notifyTaskCreated($task, $assignedUsers);
        }

        // Sync labels if provided
        if ($request->has('label_ids')) {
            $task->labels()->sync($request->label_ids);
        }

        // Save uploaded attachments (optional)
        if ($request->hasFile('attachments')) {
            $uploadPath = 'image/tasks/attachments/';

            if (!file_exists(public_path($uploadPath))) {
                mkdir(public_path($uploadPath), 0777, true);
            }

            foreach ($request->file('attachments') as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }

                $originalName = $file->getClientOriginalName();
                $fileSize = $file->getSize();
                $mimeType = $file->getMimeType();
                $extension = strtolower($file->getClientOriginalExtension());

                $safeBaseName = pathinfo($originalName, PATHINFO_FILENAME);
                $safeBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $safeBaseName);
                $fileName = uniqid() . '_' . $safeBaseName . '.' . $extension;

                $file->move(public_path($uploadPath), $fileName);

                TaskAttachment::create([
                    'task_id'     => $task->id,
                    'file_name'   => $originalName,
                    'file_path'   => $uploadPath . $fileName,
                    'file_size'   => $fileSize,
                    'file_type'   => $mimeType,
                    'uploaded_by' => auth()->id(),
                ]);

                $this->logActivity($task->id, 'attachment_uploaded', "uploaded a new attachment");
                NotificationService::notifyAttachmentUploaded($task, $originalName);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'task_id' => $task->id
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($role, Task $task)
    {
        return response()->json([
            'success' => true,
            'task' => [
                'description' => $task->description,
                'updated_by' => [
                    'name' => auth()->user()->name
                ],
                'updated_at' => $task->updated_at ? $task->updated_at->toIso8601String() : null
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, Task $task)
    {
        // dd($request->all());

        // Store old values for activity logging
        $oldValues = $task->only(['priority', 'start_date', 'due_date', 'column_id', 'title', 'description']);
        $oldAssignees = $task->users->pluck('id')->toArray();
        $oldLabels = $task->labels->pluck('id')->toArray();

        $data = $request->only(
            'title',
            'description',
            'priority',
            'start_date',
            'due_date',
            'assigned_to',
            'column_id',
            'status'
        );

        if (isset($data['title']) && trim($data['title']) === '') {
            unset($data['title']);
        }

        if (isset($data['description']) && trim($data['description']) === '') {
            $data['description'] = null;
        }

        // Convert empty strings and 0 to null for nullable fields
        if (isset($data['assigned_to']) && ($data['assigned_to'] === '' || $data['assigned_to'] === 0 || $data['assigned_to'] === '0')) {
            $data['assigned_to'] = null;
        }

        // priority none -> null (if using NULL)
        if (isset($data['priority']) && ($data['priority'] === '')) {
            $data['priority'] = null;
        }

        // datetime handling
        if (isset($data['start_date']) && $data['start_date'] === '') {
            $data['start_date'] = null;
        } elseif (!empty($data['start_date'])) {
            $data['start_date'] = Carbon::parse($data['start_date'])->format('Y-m-d H:i:s');
        }

        if (isset($data['due_date']) && $data['due_date'] === '') {
            $data['due_date'] = null;
        } elseif (!empty($data['due_date'])) {
            $data['due_date'] = Carbon::parse($data['due_date'])->format('Y-m-d H:i:s');
        }

        $task->update($data);

        if (isset($data['description']) && $oldValues['description'] != $data['description']) {
            $this->logActivity($task->id, 'description_updated', "updated the description");
        }

        // Log priority changes
        if (isset($data['priority']) && $oldValues['priority'] != $data['priority']) {
            $priorityText = $data['priority'] ? ucfirst($data['priority']) : 'None';
            $this->logActivity($task->id, 'priority_changed', "set the priority to {$priorityText}");

            NotificationService::notifyPriorityChanged($task, $oldValues['priority'], $data['priority']);
        }

        // Log start date changes
        if (isset($data['start_date']) && $oldValues['start_date'] != $data['start_date']) {
            if ($data['start_date']) {
                $formattedDate = Carbon::parse($data['start_date'])->format('M d, Y, g:i A');
                $this->logActivity($task->id, 'start_date_changed', "set the start date to {$formattedDate}");
            } else {
                $this->logActivity($task->id, 'start_date_removed', "removed the start date");
            }
        }

        // Log due date changes
        if (isset($data['due_date']) && $oldValues['due_date'] != $data['due_date']) {
            if ($data['due_date']) {
                $formattedDate = Carbon::parse($data['due_date'])->format('M d, Y, g:i A');
                $this->logActivity($task->id, 'due_date_changed', "set the due date to {$formattedDate}");
            } else {
                $this->logActivity($task->id, 'due_date_removed', "removed the due date");
            }
            
            // Notify users about due date changes (only if becoming sooner or removed)
            NotificationService::notifyDueDateChanged($task, $oldValues['due_date'], $data['due_date']);
        }

        // Log column/state changes
        if (isset($data['column_id']) && $oldValues['column_id'] != $data['column_id']) {
            // Fetch both columns in a single query
            $columns = Column::whereIn('id', [$oldValues['column_id'], $data['column_id']])
                ->get()
                ->keyBy('id');
            
            $oldColumn = $columns->get($oldValues['column_id']);
            $newColumn = $columns->get($data['column_id']);
            
            if ($newColumn) {
                $this->logActivity($task->id, 'column_changed', "moved to {$newColumn->name}");
                
                // Notify users about state changes
                if ($oldColumn) {
                    NotificationService::notifyStateChanged($task, $oldColumn->name, $newColumn->name);
                }

                // Update task timestamps based on column change
                $isNewColumnInProgress = $this->isInProgressColumn($newColumn->name);
                $isOldColumnCompleted = $oldColumn && preg_match('/done|completed|complete|closed|finish|finished/i', $oldColumn->name);
                $isNewColumnCompleted = preg_match('/done|completed|complete|closed|finish|finished/i', $newColumn->name);

                if ($isNewColumnInProgress && !$task->start_date && !array_key_exists('start_date', $data)) {
                    $data['start_date'] = now();
                }

                if (!$isOldColumnCompleted && $isNewColumnCompleted) {
                    // Moving to completed column
                    $data['completed_at'] = now();

                    if (!$task->due_date && !array_key_exists('due_date', $data)) {
                        $data['due_date'] = now();
                    }
                } elseif ($isOldColumnCompleted && !$isNewColumnCompleted) {
                    // Moving away from completed column
                    $data['completed_at'] = null;
                }

                // Re-update the task with timestamps if they changed
                $timestampUpdates = array_intersect_key($data, array_flip(['start_date', 'due_date', 'completed_at']));
                if (!empty($timestampUpdates)) {
                    $task->update($timestampUpdates);
                }
            }
        }

        // Log assignee changes
        if ($request->has('assigned_users')) {
            $newAssignees = $request->assigned_users;
            $task->users()->sync($newAssignees);

            $added = array_diff($newAssignees, $oldAssignees);
            $removed = array_diff($oldAssignees, $newAssignees);

            foreach ($added as $userId) {
                $user = User::find($userId);
                if ($user && $user->id != auth()->id()) {
                    $this->logActivity($task->id, 'assignee_added', "added a new assignee {$user->name}");
                    //send mail notification to assigned user
                    sendBrevoMail(
                        $user->email,
                        $user->name,
                        'New Task Assigned: ' . $task->title,
                        "You have been assigned a new task: <strong>{$task->title}</strong><br><br>Click <a href='" . route('role.tasks.board', ['role' => auth()->user()->getRoleNames()->first(), 'board' => $task->board_id]) . "'>here</a> to view the task details."
                    );
                }
            }

            foreach ($removed as $userId) {
                $user = User::find($userId);
                if ($user && $user->id != auth()->id()) {
                    $this->logActivity($task->id, 'assignee_removed', "removed the assignee {$user->name}");
                    //send mail notification to removed user
                    sendBrevoMail(
                        $user->email,
                        $user->name,
                        'Task Unassigned: ' . $task->title,
                        "You have been unassigned from the task: <strong>{$task->title}</strong>"
                    );
                }
            }

            // Send notifications to assigned users
            NotificationService::notifyTaskAssigned($task, $request->assigned_users);
        }

        // Log label changes
        if ($request->has('label_ids')) {
            $newLabels = $request->label_ids;
            $task->labels()->sync($newLabels);

            $added = array_diff($newLabels, $oldLabels);
            $removed = array_diff($oldLabels, $newLabels);

            foreach ($added as $labelId) {
                $label = Label::find($labelId);
                if ($label) {
                    $this->logActivity($task->id, 'label_added', "added a new label \"{$label->name}\"");
                }
            }

            foreach ($removed as $labelId) {
                $label = Label::find($labelId);
                if ($label) {
                    $this->logActivity($task->id, 'label_removed', "removed the label \"{$label->name}\"");
                }
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'task' => $task->fresh()->load('labels', 'users'),
                'last_edited_by' => auth()->user()->name,
                'last_edited_at' => $task->updated_at->toIso8601String()
            ]);
        }

        return back();
    }

    /**
     * Helper method to log task activities
     */
    private function logActivity($taskId, $activityType, $description)
    {
        TaskActivityLog::create([
            'task_id' => $taskId,
            'user_id' => auth()->id(),
            'activity_type' => $activityType,
            'description' => $description
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($role, Task $task)
    {
        try {
            $taskId = $task->id;
            $boardId = $task->board_id;

            // Delete related records
            $task->users()->detach();
            $task->labels()->detach();
            $task->links()->delete();
            $task->attachments()->each(function ($attachment) {
                // Delete physical file
                $filePath = public_path($attachment->file_path);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $attachment->delete();
            });
            $task->activityLogs()->delete();
            $task->comments()->delete();

            // Delete the task
            $task->delete();

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully',
                'task_id' => $taskId,
                'board_id' => $boardId
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete task: ' . $e->getMessage()
            ], 500);
        }
    }
    // DRAG & DROP MOVE LOGIC
    public function move(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'column_id' => 'required|exists:columns,id',
            'position' => 'required|integer'
        ]);

        DB::transaction(function () use ($request) {
            $task = Task::findOrFail($request->task_id);
            $oldColumnId = $task->column_id;
            $oldPosition = $task->position;

            // If moving to a different column, adjust positions in the old column
            if ($oldColumnId != $request->column_id) {
                // Decrement positions of tasks that were after the moved task in the old column
                Task::where('column_id', $oldColumnId)
                    ->where('position', '>', $oldPosition)
                    ->decrement('position');
            } elseif ($request->position > $oldPosition) {
                // If moving within the same column to a higher position,
                // decrement positions between old and new position
                Task::where('column_id', $oldColumnId)
                    ->where('position', '>', $oldPosition)
                    ->where('position', '<=', $request->position)
                    ->decrement('position');
            } elseif ($request->position < $oldPosition) {
                // If moving within the same column to a lower position,
                // increment positions between new and old position
                Task::where('column_id', $oldColumnId)
                    ->where('position', '>=', $request->position)
                    ->where('position', '<', $oldPosition)
                    ->increment('position');
            }

            // Shift other tasks in target column (only if different column or position changed)
            if ($oldColumnId != $request->column_id || $request->position != $oldPosition) {
                Task::where('column_id', $request->column_id)
                    ->where('position', '>=', $request->position)
                    ->increment('position');
            }

            // Check if columns are completed columns
            $oldColumn = Column::find($oldColumnId);
            $newColumn = Column::find($request->column_id);
            
            $isOldColumnCompleted = $oldColumn && preg_match('/done|completed|complete|closed|finish|finished/i', $oldColumn->name);
            $isNewColumnCompleted = $newColumn && preg_match('/done|completed|complete|closed|finish|finished/i', $newColumn->name);
            $isNewColumnInProgress = $newColumn && $this->isInProgressColumn($newColumn->name);

            $updateData = [
                'column_id' => $request->column_id,
                'position' => $request->position
            ];

            if ($isNewColumnInProgress && !$task->start_date) {
                $updateData['start_date'] = now();
            }

            // Set completed_at based on column change
            if (!$isOldColumnCompleted && $isNewColumnCompleted) {
                // Moving to completed column
                $updateData['completed_at'] = now();

                if (!$task->due_date) {
                    $updateData['due_date'] = now();
                }
            } elseif ($isOldColumnCompleted && !$isNewColumnCompleted) {
                // Moving away from completed column
                $updateData['completed_at'] = null;
            }

            // Update moving task
            $task->update($updateData);

            // Log the move activity
            if ($oldColumnId != $request->column_id && $newColumn) {
                $this->logActivity($task->id, 'column_changed', "moved to {$newColumn->name}");
                
                // Notify users about state changes
                if ($oldColumn) {
                    NotificationService::notifyStateChanged($task, $oldColumn->name, $newColumn->name);
                }
            }
        });

        return response()->json([
            'success' => true,
            'task' => Task::with('labels', 'users')->find($request->task_id),
        ]);
    }

    private function isInProgressColumn(string $columnName): bool
    {
        return (bool) preg_match('/\bin[\s_-]*progress\b|\bprogress\b|inprogress|processing|working|ongoing/i', $columnName);
    }

    public function storeAttachment(Request $request)
    {
        try {
            $request->validate([
                'task_id' => 'required|exists:tasks,id',
                'file'    => 'required|file|max:10240',
            ]);

            $taskId = $request->task_id;
            $file   = $request->file('file');

            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();
            $extension = strtolower($file->getClientOriginalExtension());

            $uploadPath = 'image/tasks/attachments/';

            if (!file_exists(public_path($uploadPath))) {
                mkdir(public_path($uploadPath), 0777, true);
            }

            $safeBaseName = pathinfo($originalName, PATHINFO_FILENAME);
            $safeBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $safeBaseName);

            $fileName = uniqid() . '_' . $safeBaseName . '.' . $extension;

            $file->move(public_path($uploadPath), $fileName);

            $relativePath = $uploadPath . $fileName;

            $attachment = TaskAttachment::create([
                'task_id'     => $taskId,
                'file_name'   => $originalName,
                'file_path'   => $relativePath,
                'file_size'   => $fileSize,
                'file_type'   => $mimeType,
                'uploaded_by' => auth()->id(),
            ]);

            // Log activity
            $this->logActivity($taskId, 'attachment_uploaded', "uploaded a new attachment");
            
            // Notify task participants about attachment upload
            $task = Task::find($taskId);
            if ($task) {
                NotificationService::notifyAttachmentUploaded($task, $originalName);
            }

            $total = TaskAttachment::where('task_id', $taskId)->count();

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'attachment' => [
                    'id'        => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'file_size' => $attachment->file_size,
                    'file_url'  => asset($attachment->file_path),
                ],
                'total_attachments' => $total,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAttachments($role, $taskId)
    {
        try {
            $attachments = TaskAttachment::where('task_id', $taskId)
                ->with('uploader:id,name') // Assuming you have uploader relationship
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($att) {
                    return [
                        'id' => $att->id,
                        'file_name' => $att->file_name,
                        'file_size' => $att->file_size,
                        'file_url' => asset($att->file_path),
                        'uploader_name' => $att->uploader->name ?? 'Unknown',
                        'created_at' => $att->created_at->diffForHumans(),
                    ];
                });

            return response()->json([
                'success' => true,
                'attachments' => $attachments,
                'total' => $attachments->count()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load attachments: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyAttachment($role, $attachmentId)
    {
        try {
            $attachment = TaskAttachment::findOrFail($attachmentId);
            $taskId = $attachment->task_id;

            // Delete file from storage
            $filePath = public_path($attachment->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete database record
            $attachment->delete();

            // Log activity
            $this->logActivity($taskId, 'attachment_deleted', "deleted an attachment");

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully',
                'task_id' => $taskId
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete: ' . $e->getMessage()
            ], 500);
        }
    }

    //store link
    public function storeLink(Request $request)
    {
        try {
            $request->validate([
                'task_id' => 'required|exists:tasks,id',
                'url' => 'required|url|max:500',
                'display_title' => 'nullable|string|max:255',
            ]);

            $link = TaskLink::create([
                'task_id' => $request->task_id,
                'url' => $request->url,
                'display_title' => $request->display_title ?: $request->url,
                'created_by' => auth()->id(),
            ]);

            // Log activity
            $this->logActivity($request->task_id, 'link_added', "added a new link");

            $total = TaskLink::where('task_id', $request->task_id)->count();

            return response()->json([
                'success' => true,
                'message' => 'Link added successfully',
                'link' => [
                    'id' => $link->id,
                    'url' => $link->url,
                    'display_title' => $link->display_title,
                    'created_at' => $link->created_at->diffForHumans(),
                ],
                'total_links' => $total,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all links for a task
     */
    public function getLinks($role, $taskId)
    {
        try {
            $links = TaskLink::where('task_id', $taskId)
                ->with('creator:id,name')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($link) {
                    return [
                        'id' => $link->id,
                        'task_id' => $link->task_id,
                        'url' => $link->url,
                        'display_title' => $link->display_title,
                        'creator_name' => $link->creator->name ?? 'Unknown',
                        'created_at' => $link->created_at->diffForHumans(),
                    ];
                });

            return response()->json([
                'success' => true,
                'links' => $links,
                'total' => $links->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load links: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a link
     */
    /**
     * Update a link
     */
    public function updateLink(Request $request, $role, $linkId)
    {
        try {
            $request->validate([
                'task_id' => 'required|exists:tasks,id',
                'url' => 'required|url|max:500',
                'display_title' => 'nullable|string|max:255',
            ]);

            $link = TaskLink::findOrFail($linkId);

            $link->update([
                'url' => $request->url,
                'display_title' => $request->display_title ?: $request->url,
            ]);

            // Log activity
            $this->logActivity($request->task_id, 'link_updated', "updated a link");

            return response()->json([
                'success' => true,
                'message' => 'Link updated successfully',
                'task_id' => $request->task_id,
                'link' => [
                    'id' => $link->id,
                    'url' => $link->url,
                    'display_title' => $link->display_title,
                    'created_at' => $link->created_at->diffForHumans(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update link: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyLink($role, $linkId)
    {
        try {
            $link = TaskLink::findOrFail($linkId);
            $taskId = $link->task_id; // Get task_id before deleting
            $link->delete();

            // Log activity
            $this->logActivity($taskId, 'link_deleted', "deleted a link");

            // Return the remaining count for this task
            $remainingCount = TaskLink::where('task_id', $taskId)->count();

            return response()->json([
                'success' => true,
                'message' => 'Link deleted successfully',
                'task_id' => $taskId,
                'remaining_count' => $remainingCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all activities for a task (activity logs + comments)
     */
    public function getActivities($role, $taskId)
    {
        try {
            $task = Task::findOrFail($taskId);

            // Get activity logs
            $activityLogs = $task->activityLogs()->with('user:id,name')->get()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'type' => 'activity',
                    'activity_type' => $log->activity_type,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user->name ?? 'Unknown',
                    'description' => $log->description,
                    'created_at' => $log->created_at->format('M d, Y'),
                    'time_ago' => $log->created_at->diffForHumans(),
                    'timestamp' => $log->created_at->timestamp,
                ];
            });

            // Get comments
            $comments = $task->comments()->with('user:id,name')->get()->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'type' => 'comment',
                    'user_id' => $comment->user_id,
                    'user_name' => $comment->user->name ?? 'Unknown',
                    'comment' => $comment->comment,
                    'created_at' => $comment->created_at->format('M d, Y'),
                    'time_ago' => $comment->created_at->diffForHumans(),
                    'timestamp' => $comment->created_at->timestamp,
                ];
            });

            // Merge and sort by timestamp (newest first)
            $activities = $activityLogs->concat($comments)->sortByDesc('timestamp')->values();

            return response()->json([
                'success' => true,
                'activities' => $activities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load activities: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new comment
     */
    public function storeComment(Request $request)
    {
        try {
            $request->validate([
                'task_id' => 'required|exists:tasks,id',
                'comment' => 'required|string|max:5000',
            ]);

            $comment = TaskComment::create([
                'task_id' => $request->task_id,
                'user_id' => auth()->id(),
                'comment' => $request->comment,
            ]);

            // Log activity
            $this->logActivity($request->task_id, 'comment_added', "added a comment");
            
            // Notify task participants about the new comment
            $task = Task::find($request->task_id);
            if ($task) {
                NotificationService::notifyCommentAdded($task, $request->comment);
            }

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'comment' => [
                    'id' => $comment->id,
                    'user_id' => $comment->user_id,
                    'user_name' => auth()->user()->name,
                    'comment' => $comment->comment,
                    'created_at' => $comment->created_at->format('M d, Y'),
                    'time_ago' => $comment->created_at->diffForHumans(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment: ' . $e->getMessage()
            ], 500);
        }
    }
}
