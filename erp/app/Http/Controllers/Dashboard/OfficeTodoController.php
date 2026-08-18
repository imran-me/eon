<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\OfficeTodo;
use App\Models\OfficeTodoAssignee;
use App\Models\OfficeTodoChecklist;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OfficeTodoController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = OfficeTodo::with([
            'creator:id,name,image',
            'department:id,name',
            'assignees:id,name,image',
        ])->withCount([
            // Leaf rows only — a parent is just a heading for the sub-items under
            // it, so counting both would inflate the totals.
            'checklists as checklists_total' => fn ($q) => $q->leafOnly(),
            'checklists as checklists_checked' => fn ($q) => $q->leafOnly()->where('is_checked', true),
        ]);

        if ($user->hasRole('employee')) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('assignees', fn ($sq) => $sq->where('users.id', $user->id))
                    ->orWhere(function ($sq) use ($user) {
                        $sq->where('created_by', $user->id)->where('is_self', true);
                    });
            });
        }

        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('is_self')) {
            $query->where('is_self', (bool) $request->is_self);
        }

        $datas = $query->orderBy('created_at', 'desc')->paginate(20);
        $departments = Department::orderBy('name')->get();
        $employees = User::where('status', 'active')
            ->where(fn ($q) => $q->where('is_super_admin', '!=', 1)->orWhereNull('is_super_admin'))
            ->orderBy('name')
            ->get(['id', 'name', 'image']);

        if ($user->hasRole('employee')) {
            $myAssignees = OfficeTodoAssignee::where('user_id', $user->id)
                ->whereIn('office_todo_id', $datas->pluck('id'))
                ->get()
                ->keyBy('office_todo_id');

            $datas->getCollection()->transform(function ($todo) use ($myAssignees) {
                $pivot = $myAssignees->get($todo->id);
                $todo->my_status = $pivot?->status ?? 'pending';
                $todo->my_note = $pivot?->note;
                return $todo;
            });
        }

        return view('office-todos.index', compact('datas', 'departments', 'employees'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'nullable|integer|exists:departments,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:pending,in_progress,completed',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xlsx|max:5120',
            'is_self' => 'nullable|boolean',
            'assigned_to' => 'nullable|array',
            'assigned_to.*' => 'integer|exists:users,id',
            'checklists' => 'nullable|array',
            'checklists.*.id' => 'nullable|integer',
            'checklists.*.key' => 'nullable|string|max:40',
            'checklists.*.parent_key' => 'nullable|string|max:40',
            'checklists.*.title' => 'nullable|string|max:255',
            'checklists.*.priority' => 'nullable|in:low,medium,high',
            'checklists.*.start_date' => 'nullable|date',
            'checklists.*.end_date' => 'nullable|date|after_or_equal:checklists.*.start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = uniqid() . '.' . strtolower($file->getClientOriginalExtension());
                $path = 'office-todos/attachments';
                if (!file_exists(public_path($path))) {
                    mkdir(public_path($path), 0777, true);
                }
                $file->move(public_path($path), $fileName);
                $attachmentPath = $path . '/' . $fileName;
            }

            $isSelf = $request->boolean('is_self', empty($request->assigned_to));
            $assignedUserIds = collect($request->assigned_to ?? [])
                ->filter()
                ->map(fn ($userId) => (int) $userId)
                ->unique()
                ->values()
                ->all();
            

            $todo = OfficeTodo::create([
                'company_id' => Auth::user()->company_id,
                'department_id' => $request->department_id,
                'created_by' => Auth::id(),
                'title' => $request->title,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'due_date' => $request->due_date,
                'priority' => $request->priority ?? 'medium',
                'status' => $request->status ?? 'pending',
                'attachment' => $attachmentPath,
                'is_self' => $isSelf,
            ]);

            if (!$isSelf && !empty($assignedUserIds)) {
                $rows = collect($assignedUserIds)->map(fn ($uid) => [
                    'office_todo_id' => $todo->id,
                    'user_id' => $uid,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                OfficeTodoAssignee::insert($rows->toArray());
            }

            $this->syncChecklists($todo->id, $request->input('checklists', []));

            DB::commit();

            if (!$isSelf && !empty($assignedUserIds)) {
                NotificationService::notifyOfficeTodoAssigned($todo, $assignedUserIds);
            }

            return response()->json([
                'success' => true,
                'message' => 'Todo created successfully.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function edit(string $role, string $id)
    {
        $todo = OfficeTodo::with([
            'creator:id,name',
            'department:id,name',
            'assignees',
            'checklists:id,office_todo_id,parent_id,title,priority,status,start_date,end_date,sort_order,is_checked,checked_by,checked_at',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $todo,
        ]);
    }

    public function update(Request $request, string $role, string $id)
    {
        $todo = OfficeTodo::findOrFail($id);
        $oldValues = $todo->only(['company_id', 'department_id', 'title', 'description', 'start_date', 'due_date', 'priority', 'status']);
        $oldAssigneeIds = $todo->assignees()->pluck('users.id')->map(fn ($value) => (int) $value)->all();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'nullable|integer|exists:departments,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:pending,in_progress,completed',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xlsx|max:5120',
            'is_self' => 'nullable|boolean',
            'assigned_to' => 'nullable|array',
            'assigned_to.*' => 'integer|exists:users,id',
            'checklists' => 'nullable|array',
            'checklists.*.id' => 'nullable|integer',
            'checklists.*.key' => 'nullable|string|max:40',
            'checklists.*.parent_key' => 'nullable|string|max:40',
            'checklists.*.title' => 'nullable|string|max:255',
            'checklists.*.priority' => 'nullable|in:low,medium,high',
            'checklists.*.start_date' => 'nullable|date',
            'checklists.*.end_date' => 'nullable|date|after_or_equal:checklists.*.start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $attachmentPath = $todo->attachment;
            if ($request->hasFile('attachment')) {
                if ($attachmentPath && file_exists(public_path($attachmentPath))) {
                    unlink(public_path($attachmentPath));
                }
                $file = $request->file('attachment');
                $fileName = uniqid() . '.' . strtolower($file->getClientOriginalExtension());
                $path = 'office-todos/attachments';
                if (!file_exists(public_path($path))) {
                    mkdir(public_path($path), 0777, true);
                }
                $file->move(public_path($path), $fileName);
                $attachmentPath = $path . '/' . $fileName;
            }

            $isSelf = $request->boolean('is_self', empty($request->assigned_to));
            $assignedUserIds = collect($request->assigned_to ?? [])
                ->filter()
                ->map(fn ($userId) => (int) $userId)
                ->unique()
                ->values();

            $todo->update([
                'company_id' => $request->company_id ?? $todo->company_id,
                'department_id' => $request->department_id,
                'title' => $request->title,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'due_date' => $request->due_date,
                'priority' => $request->priority ?? $todo->priority,
                'status' => $request->status ?? $todo->status,
                'attachment' => $attachmentPath,
                'is_self' => $isSelf,
            ]);

            if ($request->has('assigned_to')) {
                $existing = OfficeTodoAssignee::where('office_todo_id', $todo->id)->pluck('user_id');

                OfficeTodoAssignee::where('office_todo_id', $todo->id)
                    ->whereNotIn('user_id', $assignedUserIds)
                    ->delete();

                $toAdd = $assignedUserIds->diff($existing);
                if ($toAdd->isNotEmpty()) {
                    $rows = $toAdd->map(fn ($uid) => [
                        'office_todo_id' => $todo->id,
                        'user_id' => $uid,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    OfficeTodoAssignee::insert($rows->toArray());
                }
            }

            if ($request->has('checklists')) {
                $this->syncChecklists($todo->id, $request->input('checklists', []));
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
                NotificationService::notifyOfficeTodoUpdated($todo, array_values(array_diff($currentAssigneeIds, [Auth::id()])));
            }

            if ($request->has('assigned_to')) {
                $addedIds = array_values(array_diff($currentAssigneeIds, $oldAssigneeIds));
                if (!empty($addedIds)) {
                    NotificationService::notifyOfficeTodoAssigned($todo, $addedIds);
                }
            }

            return response()->json(['success' => true, 'message' => 'Todo updated successfully.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        $todo = OfficeTodo::find($request->item_id);
        if (!$todo) {
            return response()->json(['success' => false, 'message' => 'Todo not found.'], 404);
        }

        $todo->delete();

        return response()->json(['success' => true, 'message' => 'Todo deleted successfully.']);
    }

    public function quickStatus(Request $request, string $role, string $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,completed',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $user = Auth::user();
        $todo = OfficeTodo::with('assignees')->findOrFail($id);
        $oldStatus = $todo->status;

        if ($user->hasRole('employee')) {
            $assignee = OfficeTodoAssignee::where('office_todo_id', $id)->where('user_id', $user->id)->first();
            if (!$assignee) {
                return response()->json(['success' => false, 'message' => 'You are not assigned to this todo.'], 403);
            }

            $assignee->update([
                'status' => $request->status,
                'note' => $request->note,
                'completed_at' => $request->status === 'completed' ? now() : null,
            ]);

            $statuses = OfficeTodoAssignee::where('office_todo_id', $id)->pluck('status');
            if ($statuses->every(fn ($s) => $s === 'completed')) {
                $overall = 'completed';
            } elseif ($statuses->contains(fn ($s) => in_array($s, ['in_progress', 'completed']))) {
                $overall = 'in_progress';
            } else {
                $overall = 'pending';
            }

            $todo->update(['status' => $overall]);
            $this->notifyStatusChange($todo, $oldStatus, $overall);
        } else {
            $todo->update(['status' => $request->status]);
            $this->notifyStatusChange($todo, $oldStatus, $request->status);
        }

        return response()->json(['success' => true, 'message' => 'Status updated successfully.']);
    }

    public function getChecklists(Request $request, string $role, string $id)
    {
        $user = Auth::user();
        $todo = OfficeTodo::with('checklists')->findOrFail($id);

        if ($user->hasRole('employee')) {
            $allowed = OfficeTodoAssignee::where('office_todo_id', $id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$allowed && !($todo->is_self && $todo->created_by == $user->id)) {
                return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
            }
        }

        return response()->json([
            'success' => true,
            'checklists' => $todo->checklists,
            'todo_title' => $todo->title,
            'is_admin' => $user->hasRole('super admin') || $user->hasRole('admin'),
        ]);
    }

    public function toggleChecklist(Request $request, string $role, string $todo, string $checklist)
    {
        $user = Auth::user();
        $todoModel = OfficeTodo::findOrFail($todo);

        if ($user->hasRole('employee')) {
            $allowed = OfficeTodoAssignee::where('office_todo_id', $todo)
                ->where('user_id', $user->id)
                ->exists();

            if (!$allowed && !($todoModel->is_self && $todoModel->created_by == $user->id)) {
                return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
            }
        }

        $item = OfficeTodoChecklist::where('office_todo_id', $todo)->findOrFail($checklist);

        // An explicit status comes from the 3-state control; without one this
        // still behaves as the plain done/not-done toggle older callers expect.
        $status = $request->input('status');
        if (!in_array($status, ['pending', 'in_progress', 'completed'], true)) {
            $status = $item->is_checked ? 'pending' : 'completed';
        }

        $newChecked = $status === 'completed';

        $item->update([
            'status'     => $status,
            'is_checked' => $newChecked,
            'checked_by' => $newChecked ? $user->id : null,
            'checked_at' => $newChecked ? now() : null,
        ]);

        // A sub-item moving can change what its parent adds up to.
        $this->recalcParentStatuses((int) $todo);

        $parent = $item->parent_id
            ? OfficeTodoChecklist::find($item->parent_id)
            : null;

        return response()->json([
            'success'    => true,
            'is_checked' => $newChecked,
            'status'     => $status,
            'parent'     => $parent ? [
                'id'         => $parent->id,
                'status'     => $parent->status,
                'is_checked' => (bool) $parent->is_checked,
            ] : null,
        ]);
    }

    /**
     * Persist a drag-reorder from the checklist modal. The ids arrive in the
     * depth-first order they're shown in — parent then its own sub-items —
     * which is the same order syncChecklists() writes, so a later edit-and-save
     * round-trips the arrangement instead of undoing it.
     *
     * Only sort_order moves here; re-parenting is not offered by the UI, so any
     * id whose parent would change is rejected rather than silently applied.
     */
    public function reorderChecklists(Request $request, string $role, string $todo)
    {
        $user      = Auth::user();
        $todoModel = OfficeTodo::findOrFail($todo);

        if ($user->hasRole('employee')) {
            $allowed = OfficeTodoAssignee::where('office_todo_id', $todo)
                ->where('user_id', $user->id)
                ->exists();

            if (!$allowed && !($todoModel->is_self && $todoModel->created_by == $user->id)) {
                return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'order'   => 'required|array|min:1',
            'order.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $ids  = array_map('intval', $request->input('order'));
        $rows = OfficeTodoChecklist::where('office_todo_id', $todo)->get()->keyBy('id');

        // Every id must belong to this todo, and the payload must cover the
        // whole list — a partial order would leave gaps in sort_order.
        if (count($ids) !== $rows->count() || collect($ids)->contains(fn ($id) => !$rows->has($id))) {
            return response()->json([
                'success' => false,
                'message' => 'The submitted order does not match this todo\'s checklist.',
            ], 422);
        }

        DB::transaction(function () use ($ids, $rows) {
            foreach ($ids as $i => $id) {
                if ((int) $rows[$id]->sort_order !== $i) {
                    $rows[$id]->update(['sort_order' => $i]);
                }
            }
        });

        return response()->json(['success' => true, 'message' => 'Checklist reordered.']);
    }

    public function updateMyStatus(Request $request, $role, string $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,completed',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $userId = Auth::id();
        $todo = OfficeTodo::with('assignees')->findOrFail($id);
        $oldStatus = $todo->status;

        $assignee = OfficeTodoAssignee::where('office_todo_id', $id)->where('user_id', $userId)->first();

        if (!$assignee) {
            if ($todo->created_by != $userId) {
                return response()->json(['success' => false, 'message' => 'You are not assigned to this todo.'], 403);
            }

            $assignee = OfficeTodoAssignee::create([
                'office_todo_id' => (int) $id,
                'user_id' => $userId,
                'status' => 'pending',
            ]);
        }

        $assignee->update([
            'status' => $request->status,
            'note' => $request->note,
            'completed_at' => $request->status === 'completed' ? now() : null,
        ]);

        $statuses = OfficeTodoAssignee::where('office_todo_id', $id)->pluck('status');
        if ($statuses->every(fn ($s) => $s === 'completed')) {
            $overall = 'completed';
        } elseif ($statuses->contains(fn ($s) => in_array($s, ['in_progress', 'completed']))) {
            $overall = 'in_progress';
        } else {
            $overall = 'pending';
        }

        $todo->update(['status' => $overall]);
        $this->notifyStatusChange($todo, $oldStatus, $overall);

        return response()->json(['success' => true, 'message' => 'Status updated successfully.']);
    }

    /**
     * Reconcile a todo's checklist against the submitted rows.
     *
     * Rows carry their own id when they already exist, so an edit updates them
     * in place rather than deleting and re-inserting the whole list — that older
     * approach silently wiped every tick (is_checked/checked_by/checked_at) each
     * time the todo was saved. Only rows the user actually removed are deleted.
     * A plain string is still accepted so older callers keep working.
     */
    private function syncChecklists(int $todoId, array $items): void
    {
        $rows = collect($items)
            ->map(fn ($row) => is_array($row) ? $row : ['title' => $row])
            ->filter(fn ($row) => trim((string) ($row['title'] ?? '')) !== '')
            ->values();

        $keepIds = $rows->pluck('id')->filter()->map(fn ($v) => (int) $v)->all();

        OfficeTodoChecklist::where('office_todo_id', $todoId)
            ->when($keepIds, fn ($q) => $q->whereNotIn('id', $keepIds))
            ->delete();

        // Sub-items that were only just added have no id yet, so they point at
        // their parent through the client-side key the form assigned. Rows always
        // arrive parent-before-child, so the map is populated by the time it's read.
        $keyToId = [];

        foreach ($rows as $i => $row) {
            $parentKey = trim((string) ($row['parent_key'] ?? ''));
            $parentId  = $parentKey !== '' ? ($keyToId[$parentKey] ?? null) : null;

            $attrs = [
                'title'      => trim((string) $row['title']),
                'sort_order' => $i,
                'parent_id'  => $parentId,
                'priority'   => in_array($row['priority'] ?? null, ['low', 'medium', 'high'], true)
                    ? $row['priority']
                    : 'medium',
                'start_date' => !empty($row['start_date']) ? $row['start_date'] : null,
                'end_date'   => !empty($row['end_date']) ? $row['end_date'] : null,
            ];

            $id = (int) ($row['id'] ?? 0);

            if ($id) {
                // Touches only these columns, so the tick and status survive the save.
                OfficeTodoChecklist::where('office_todo_id', $todoId)
                    ->where('id', $id)
                    ->update($attrs);
            } else {
                $id = OfficeTodoChecklist::create($attrs + [
                    'office_todo_id' => $todoId,
                    'status'         => 'pending',
                    'is_checked'     => false,
                ])->id;
            }

            $key = trim((string) ($row['key'] ?? ''));
            if ($key !== '') {
                $keyToId[$key] = $id;
            }
        }

        $this->recalcParentStatuses($todoId);
    }

    /**
     * A parent item is just a heading for the sub-items beneath it, so its
     * status is derived rather than set by hand: done once every child is done,
     * in progress the moment any child moves off pending.
     */
    private function recalcParentStatuses(int $todoId): void
    {
        $all = OfficeTodoChecklist::where('office_todo_id', $todoId)->get();

        foreach ($all->whereNotNull('parent_id')->groupBy('parent_id') as $parentId => $children) {
            $parent = $all->firstWhere('id', (int) $parentId);
            if (!$parent) {
                continue;
            }

            $statuses = $children->map(fn ($c) => $c->status);

            if ($statuses->every(fn ($s) => $s === 'completed')) {
                $status = 'completed';
            } elseif ($statuses->contains(fn ($s) => $s !== 'pending')) {
                $status = 'in_progress';
            } else {
                $status = 'pending';
            }

            $isChecked = $status === 'completed';

            if ($parent->status !== $status || (bool) $parent->is_checked !== $isChecked) {
                $parent->update([
                    'status'     => $status,
                    'is_checked' => $isChecked,
                    'checked_at' => $isChecked ? ($parent->checked_at ?? now()) : null,
                    'checked_by' => $isChecked ? ($parent->checked_by ?? Auth::id()) : null,
                ]);
            }
        }
    }

    private function notifyStatusChange(OfficeTodo $todo, string $oldStatus, string $newStatus): void
    {
        $recipientIds = $todo->assignees->pluck('id')->map(fn ($value) => (int) $value)->all();

        if (!empty($todo->created_by)) {
            $recipientIds[] = (int) $todo->created_by;
        }

        NotificationService::notifyOfficeTodoStatusChanged($todo, $recipientIds, $oldStatus, $newStatus);
    }
}
