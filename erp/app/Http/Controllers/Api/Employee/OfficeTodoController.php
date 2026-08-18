<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\OfficeTodo;
use App\Models\OfficeTodoAssignee;
use App\Models\OfficeTodoChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;

class OfficeTodoController extends Controller
{
    // Employee sees only their assigned todos
    public function index(Request $request)
    {
        $userId = Auth::id();

        // Todos are maintained on the web; the app only views and ticks them.
        // So it receives the leaf items alone — a parent row is a heading, not
        // work, and shipping it here would render as an ordinary tickable box
        // whose tick the next web-side save would silently undo.
        $query = OfficeTodo::with(['checklists' => fn ($q) => $q->leafOnly()->orderBy('sort_order')])
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $userId))
            ->with([
                'creator:id,name,image',
                'department:id,name',
            ])->withCount([
                'checklists as checklists_total' => fn ($q) => $q->leafOnly(),
                'checklists as checklists_checked' => fn ($q) => $q->leafOnly()->where('is_checked', true),
            ]);

        if ($request->filled('status')) {
            // filter by employee's own pivot status
            $query->whereHas('assignees', function ($q) use ($userId, $request) {
                $q->where('users.id', $userId)->where('office_todo_assignees.status', $request->status);
            });
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }
        if ($request->filled('due_date')) {
            $query->whereDate('due_date', $request->due_date);
        }

        $todos = $query->orderBy('due_date', 'asc')->paginate(20);

        $this->prepareChecklistsForApp($todos->getCollection());

        // Attach employee's individual status to each todo
        $todos->getCollection()->transform(function ($todo) use ($userId) {
            $pivot = OfficeTodoAssignee::where('office_todo_id', $todo->id)
                ->where('user_id', $userId)
                ->first();

            $todo->my_status       = $pivot?->status ?? 'pending';
            $todo->my_completed_at = $pivot?->completed_at;
            $todo->my_note         = $pivot?->note;

            return $todo;
        });

        return response()->json([
            'success' => true,
            'message' => 'Assigned todos retrieved successfully.',
            'data'    => $todos,
        ]);
    }

    public function show(string $id)
    {
        $userId = Auth::id();

        $todo = OfficeTodo::whereHas('assignees', fn ($q) => $q->where('users.id', $userId))
            ->with(['creator:id,name,image', 'department:id,name'])
            ->findOrFail($id);

        $pivot = OfficeTodoAssignee::where('office_todo_id', $todo->id)
            ->where('user_id', $userId)
            ->first();

        $todo->my_status       = $pivot?->status ?? 'pending';
        $todo->my_completed_at = $pivot?->completed_at;
        $todo->my_note         = $pivot?->note;

        return response()->json([
            'success' => true,
            'message' => 'Todo detail retrieved successfully.',
            'data'    => $todo,
        ]);
    }

    // Employee updates their own completion status
    public function updateStatus(Request $request, string $id)
    {
        $userId = Auth::id();

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,completed',
            'note'   => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $assignee = OfficeTodoAssignee::where('office_todo_id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$assignee) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this todo.',
            ], 403);
        }

        $oldStatus = $assignee->status;

        $assignee->update([
            'status'       => $request->status,
            'note'         => $request->note,
            'completed_at' => $request->status === 'completed' ? now() : null,
        ]);

        // Auto-sync overall todo status if all assignees are done
        $this->syncOverallStatus($id);

        // Notify other assignees and creator about status change
        try {
            $todo = OfficeTodo::find($id);
            if ($todo) {
                $assigneeIds = OfficeTodoAssignee::where('office_todo_id', $id)->pluck('user_id')->toArray();
                // include creator
                if ($todo->created_by) {
                    $assigneeIds[] = $todo->created_by;
                }
                $recipientIds = array_values(array_unique($assigneeIds));
                NotificationService::notifyOfficeTodoStatusChanged($todo, $recipientIds, (string) $oldStatus, (string) $request->status);
            }
        } catch (\Throwable $e) {
            // swallow errors to avoid breaking user flow
        }

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully.',
            'data'    => $assignee,
        ]);
    }

    private function syncOverallStatus(string $todoId): void
    {
        $statuses = OfficeTodoAssignee::where('office_todo_id', $todoId)->pluck('status');

        if ($statuses->isEmpty()) {
            return;
        }

        if ($statuses->every(fn ($s) => $s === 'completed')) {
            $overallStatus = 'completed';
        } elseif ($statuses->contains(fn ($s) => in_array($s, ['in_progress', 'completed']))) {
            $overallStatus = 'in_progress';
        } else {
            $overallStatus = 'pending';
        }

        OfficeTodo::where('id', $todoId)->update(['status' => $overallStatus]);
    }

    public function toggleChecklist(string $todo, string $checklist)
    {
        $user = Auth::user();
        $todoModel = OfficeTodo::findOrFail($todo);

        $allowed = OfficeTodoAssignee::where('office_todo_id', $todo)
            ->where('user_id', $user->id)
            ->exists();

        if (!$allowed && !($todoModel->is_self && $todoModel->created_by == $user->id)) {
            return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
        }

        $item = OfficeTodoChecklist::where('office_todo_id', $todo)->findOrFail($checklist);
        $newChecked = !$item->is_checked;

        $item->update([
            'is_checked' => $newChecked,
            // Written alongside is_checked so a tick made here isn't undone the
            // next time the todo is saved on the web, where status is what the
            // parent roll-up is computed from.
            'status'     => $newChecked ? 'completed' : 'pending',
            'checked_by' => $newChecked ? $user->id : null,
            'checked_at' => $newChecked ? now() : null,
        ]);

        $this->rollUpParentStatus($item);

        // Fresh totals so the app doesn't have to recount locally — its list
        // holds leaf rows only, so a local count would drift from the server.
        $counts = OfficeTodo::withCount([
            'checklists as checklists_total' => fn ($q) => $q->leafOnly(),
            'checklists as checklists_checked' => fn ($q) => $q->leafOnly()->where('is_checked', true),
        ])->find($todo);

        return response()->json([
            'success' => true,
            'message' => 'Checklist item updated.',
            'is_checked' => $newChecked,
            'checklists_total' => (int) ($counts->checklists_total ?? 0),
            'checklists_checked' => (int) ($counts->checklists_checked ?? 0),
        ]);
    }

    /**
     * Bring a sub-item's parent in line with its children: done once they all
     * are, in progress as soon as any has moved off pending. The web derives
     * parents the same way, so both sides agree on what a heading means.
     */
    private function rollUpParentStatus(OfficeTodoChecklist $item): void
    {
        if (!$item->parent_id) {
            return;
        }

        $parent = OfficeTodoChecklist::find($item->parent_id);
        if (!$parent) {
            return;
        }

        $statuses = OfficeTodoChecklist::where('parent_id', $parent->id)->get()->map(fn ($c) => $c->status);

        if ($statuses->isEmpty()) {
            return;
        }

        if ($statuses->every(fn ($s) => $s === 'completed')) {
            $status = 'completed';
        } elseif ($statuses->contains(fn ($s) => $s !== 'pending')) {
            $status = 'in_progress';
        } else {
            $status = 'pending';
        }

        $isChecked = $status === 'completed';

        $parent->update([
            'status'     => $status,
            'is_checked' => $isChecked,
            'checked_at' => $isChecked ? ($parent->checked_at ?? now()) : null,
            'checked_by' => $isChecked ? ($parent->checked_by ?? Auth::id()) : null,
        ]);
    }

    /**
     * The app renders one flat list of checkboxes and is not being taught about
     * hierarchy, so a sub-item carries its parent's name inline for context.
     * The columns added for the web (parent_id, priority, status, dates) are
     * withheld, keeping this payload exactly the shape the app already parses.
     */
    private function prepareChecklistsForApp($todos): void
    {
        $parentIds = $todos->pluck('checklists')->flatten()
            ->pluck('parent_id')->filter()->unique();

        $parentTitles = $parentIds->isEmpty()
            ? collect()
            : OfficeTodoChecklist::whereIn('id', $parentIds)->pluck('title', 'id');

        foreach ($todos as $todo) {
            foreach ($todo->checklists as $item) {
                $parentTitle = $item->parent_id ? ($parentTitles[$item->parent_id] ?? null) : null;

                if ($parentTitle) {
                    $item->title = $parentTitle . ' › ' . $item->title;
                }

                $item->makeHidden(['parent_id', 'priority', 'status', 'start_date', 'end_date']);
            }
        }
    }
}
