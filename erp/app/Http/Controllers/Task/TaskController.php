<?php

namespace App\Http\Controllers\Task;

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
use App\Models\TaskSeen;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Exception;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;



class TaskController extends Controller
{
    /**
     * Which cards should wear the corner ribbon, and with which word.
     *
     * "New"      — an admin created this task and I have not opened it since.
     * "Comment"  — an admin commented on it after the last time I opened it.
     * "Sub-item" — an admin added a sub-work item to it since then. The item is
     *              not a card, so the news has to surface on its parent.
     *
     * One card, one word: whichever of the three happened LAST wins, so the
     * ribbon always names the freshest thing waiting to be read.
     *
     * Mine never shouts at me: an action by the person viewing the board is
     * skipped, so an admin does not get "New" on the task they just wrote.
     *
     * Three queries for the whole board, not three per card. A board here runs
     * to four figures of cards and this sits on the page's critical path, so
     * everything is fetched keyed by task id and matched up in memory.
     *
     * Returns [taskId => ['New'|'Comment', <css gradient>]].
     */
    public function adminAlerts(Board $board, ?User $viewer): array
    {
        // The board is the task module's front door for every company on this
        // install. A ribbon is decoration; it may never be the reason the page
        // does not load — and it CAN be, because task_seen arrives by migration
        // and deploys land before migrations run. Anything unexpected in here
        // costs the ribbons and nothing else.
        try {
            return $this->buildAdminAlerts($board, $viewer);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function buildAdminAlerts(Board $board, ?User $viewer): array
    {
        if (!$viewer) {
            return [];
        }

        $tasks = collect($board->board_columns ?? [])
            ->pluck('column.tasks')
            ->filter()
            ->flatten();

        $taskIds = $tasks->pluck('id')->all();

        if (empty($taskIds)) {
            return [];
        }

        // Role names, not a permission: this is "someone above me asked for
        // this", which is about who they are rather than what they may do.
        $adminIds = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['admin', 'super admin']);
        })->pluck('id')->all();

        if (empty($adminIds)) {
            return [];
        }

        $seen = TaskSeen::where('user_id', $viewer->id)
            ->whereIn('task_id', $taskIds)
            ->pluck('seen_at', 'task_id');

        $lastAdminComment = TaskComment::whereIn('task_id', $taskIds)
            ->whereIn('user_id', $adminIds)
            ->where('user_id', '!=', $viewer->id)
            ->groupBy('task_id')
            ->selectRaw('task_id, MAX(created_at) as last_at')
            ->pluck('last_at', 'task_id');

        // Sub-work items are Tasks with a parent, and never cards of their own —
        // so an admin adding one shows up on the parent's card or nowhere.
        $lastAdminSubtask = Task::whereIn('parent_id', $taskIds)
            ->whereIn('created_by', $adminIds)
            ->where('created_by', '!=', $viewer->id)
            ->groupBy('parent_id')
            ->selectRaw('parent_id, MAX(created_at) as last_at')
            ->pluck('last_at', 'parent_id');

        $alerts = [];

        $bands = [
            'Comment'  => 'linear-gradient(135deg,#a78bfa,#7c3aed)',
            'Sub-item' => 'linear-gradient(135deg,#fbbf24,#d97706)',
            'New'      => 'linear-gradient(135deg,#34d399,#059669)',
        ];

        foreach ($tasks as $task) {
            $seenAt = $seen[$task->id] ?? null;

            // Every candidate, with when it happened. Only an admin's own board
            // skips its own work — mine never shouts at me.
            $byAdmin = $task->created_by
                && in_array($task->created_by, $adminIds)
                && $task->created_by != $viewer->id;

            $candidates = array_filter([
                'Comment'  => $lastAdminComment[$task->id] ?? null,
                'Sub-item' => $lastAdminSubtask[$task->id] ?? null,
                'New'      => $byAdmin ? $task->created_at : null,
            ]);

            // Unread only: anything older than my last visit has been read.
            $unread = [];
            foreach ($candidates as $word => $at) {
                $at = Carbon::parse($at);
                if (!$seenAt || $at->gt($seenAt)) {
                    $unread[$word] = $at;
                }
            }

            if (!$unread) {
                continue;
            }

            // The freshest wins — one card, one word.
            arsort($unread);
            $word = array_key_first($unread);
            $alerts[$task->id] = [$word, $bands[$word]];
        }

        return $alerts;
    }

    /**
     * Stamp "I have looked at this", which is what takes the ribbon off my own
     * board and leaves it up for everyone who has not looked yet.
     *
     * Called when the task drawer opens. Deliberately forgiving: a failure here
     * must never break opening a task, so the caller ignores the response and
     * the worst case is a ribbon that stays one refresh longer.
     */
    public function markSeen($role, Task $task)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false], 401);
        }

        TaskSeen::updateOrCreate(
            ['task_id' => $task->id, 'user_id' => $user->id],
            ['seen_at' => now()],
        );

        return response()->json(['success' => true]);
    }

    /** Cards fetched per page in a "done" column (first paint and each scroll). */
    private const BOARD_PAGE = 20;

    /**
     * The scope every board card query shares: this board's top-level tasks plus
     * every relation and count the card markup touches (it used to be the
     * $topLevelOnly closure inside index(); boardColumnTasks() needs it too).
     */
    private function boardCardScope($q, int $boardId): void
    {
        $q->where('board_id', $boardId)
            ->whereNull('parent_id')
            // Every relation the card markup touches, loaded once for the
            // whole column rather than once per card. Without this the board
            // runs four lazy loads per card — users, labels, creator, column —
            // which an employee never notices because they see a handful of
            // cards, and which costs a super admin ~5,500 queries and twenty
            // seconds on a 1,400-card board.
            // `oversight` rides along so a restricted or flagged card can
            // say so on EVERY board, not only inside Employee Task
            // Oversight — an employee who cannot see the lock only meets it
            // later as a baffling refusal. Conditional because the table
            // arrives by migration and deploys land before migrations run:
            // no table, no relation, board renders exactly as before.
            ->with(EmployeeTaskOversightController::hasOversightTable()
                ? ['users', 'labels', 'creator', 'column', 'oversight']
                : ['users', 'labels', 'creator', 'column'])
            ->withCount([
                // reorder(): subtasks() carries an orderBy for the drawer's
                // list, which has no business inside a count sub-select.
                'subtasks' => fn($sq) => $sq->reorder(),
                'subtasks as done_subtasks_count' => fn($sq) => $sq->reorder()->whereNotNull('completed_at'),
                // Rides along for the footer's comment button, for the same
                // reason: one sub-select beats one query per card.
                'comments',
            ]);

        /* Time under this card, for the footer's clock chip.
         *
         * A card shows the sum of its sub-work items' time, or — when it has
         * none — its own. The banked part is a SUM; the part still ticking
         * cannot be, so the start of the one running child comes back too
         * and the browser adds the live remainder. Only one clock can run
         * under a parent (startTimer banks the siblings), so MAX is that
         * one.
         *
         * Two more sub-selects for the whole column, not two per card, and
         * both behind timersReady() because these columns arrive by
         * migration. AFTER withCount, which is what puts `tasks.*` into the
         * select list that these then extend. */
        if ($this->timersReady()) {
            $q->withSum(['subtasks as sub_accum_ms' => fn ($sq) => $sq->reorder()], 'timer_accum_ms')
                ->withMax(
                    ['subtasks as sub_running_since' => fn ($sq) => $sq->reorder()->whereNotNull('timer_started_at')],
                    'timer_started_at'
                );
        }
    }

    /** Employees without "view all task" only see what is theirs. */
    private function taskVisibility(User $user, bool $restricted): ?\Closure
    {
        if (!$restricted) {
            return null;
        }

        return function ($query) use ($user) {
            $query->whereHas('users', function ($q2) use ($user) {
                $q2->where('users.id', $user->id);
            })
            ->orWhere('created_by', $user->id)
            // Mine by inheritance: a parent I am not on myself still has
            // to appear when one of its sub-items is mine. Without this,
            // dropping sub-tasks from the columns would hide that work
            // from the person doing it.
            ->orWhereHas('subtasks', function ($q3) use ($user) {
                $q3->where(function ($q4) use ($user) {
                    $q4->whereHas('users', function ($q5) use ($user) {
                        $q5->where('users.id', $user->id);
                    })
                    ->orWhere('created_by', $user->id);
                });
            });
        };
    }

    /** Same name reading the card accent uses in show.blade.php ($kbcAccent). */
    private static function isDoneColumnName(?string $name): bool
    {
        $n = preg_replace('/[^a-z]/', '', strtolower((string) $name));

        return !Str::contains($n, ['cancel', 'reject', 'dropped'])
            && Str::contains($n, ['done', 'complete', 'finish', 'closed']);
    }

    private function doneColumnIds(Board $board): array
    {
        return $board->columns
            ->filter(fn ($c) => self::isDoneColumnName($c->name))
            ->pluck('id')->map(fn ($i) => (int) $i)->values()->all();
    }

    /**
     * The period a "done" column shows. Default is the last 7 days: on the live
     * board that is ~30 cards where the whole column is 600+.
     */
    private function doneWindow(Request $request): array
    {
        $key = (string) $request->query('done', 'week');
        $now = Carbon::now();
        $fmt = 'Y-m-d H:i:s';

        $from = $to = null;
        switch ($key) {
            case 'today':
                $from = $now->copy()->startOfDay();
                break;
            case 'yesterday':
                $from = $now->copy()->subDay()->startOfDay();
                $to = $now->copy()->subDay()->endOfDay();
                break;
            case 'month':
                $from = $now->copy()->startOfMonth();
                break;
            case 'days30':
                $from = $now->copy()->subDays(29)->startOfDay();
                break;
            case 'all':
                break;
            case 'custom':
                try {
                    $from = $request->filled('done_from') ? Carbon::parse($request->query('done_from'))->startOfDay() : null;
                    $to = $request->filled('done_to') ? Carbon::parse($request->query('done_to'))->endOfDay() : null;
                } catch (Throwable $e) {
                    $from = $to = null;
                }
                if (!$from && !$to) {
                    $key = 'week';
                    $from = $now->copy()->subDays(6)->startOfDay();
                }
                break;
            default:
                $key = 'week';
                $from = $now->copy()->subDays(6)->startOfDay();
        }

        return [
            'key' => $key,
            'from' => $from?->format($fmt),
            'to' => $to?->format($fmt),
            'from_in' => $from?->format('Y-m-d'),
            'to_in' => $to?->format('Y-m-d'),
        ];
    }

    /**
     * One done column, newest finish first. A card parked in DONE without
     * completed_at is dated by its last update (123 of this month's 126 Done
     * cards on the live board have no completed_at, so filtering on it alone
     * would hide them).
     */
    private function doneQuery(int $boardId, int $columnId, array $win, ?\Closure $visibility, bool $cards)
    {
        $q = Task::query();
        if ($cards) {
            $this->boardCardScope($q, $boardId);
        } else {
            $q->where('board_id', $boardId)->whereNull('parent_id');
        }
        $q->where('tasks.column_id', $columnId);
        if ($visibility) {
            $q->where($visibility);
        }

        $stamp = 'COALESCE(tasks.completed_at, tasks.updated_at)';
        if ($win['from']) {
            $q->whereRaw("$stamp >= ?", [$win['from']]);
        }
        if ($win['to']) {
            $q->whereRaw("$stamp <= ?", [$win['to']]);
        }

        return $q->orderByRaw("$stamp DESC")->orderByDesc('tasks.id');
    }

    /**
     * The next page of cards for a done column — what the board's infinite
     * scroll asks for. Returns the cards as HTML (the same partial the page
     * uses) plus whether more remain.
     */
    public function boardColumnTasks(Request $request, $role, Board $board, int $column)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isEmployee = Str::slug($user->getRoleNames()->first()) == 'employee';
        $canViewAllTasks = $user->hasPermissionTo('view all task');

        $board->load('columns');
        if (!in_array($column, $this->doneColumnIds($board), true)) {
            abort(404);
        }

        $visibility = $this->taskVisibility($user, $isEmployee && !$canViewAllTasks);
        $offset = max(0, (int) $request->query('offset', 0));

        $rows = $this->doneQuery($board->id, $column, $this->doneWindow($request), $visibility, true)
            ->offset($offset)->limit(self::BOARD_PAGE + 1)->get();
        $hasMore = $rows->count() > self::BOARD_PAGE;
        $tasks = $rows->take(self::BOARD_PAGE)->values();

        $project = Project::find($board->project_id);
        $users = User::whereIn('id', $project->team_members ?? [])->get();
        $labels = Label::where('project_id', $board->project_id)->orderBy('name')->get();

        // adminAlerts() reads the tasks off board_columns.column.tasks.
        $shell = clone $board;
        $shell->setRelation('board_columns', collect([(object) ['column' => (object) ['tasks' => $tasks]]]));
        $adminAlerts = $this->adminAlerts($shell, $user);

        return response()->json([
            'html' => view('task-module.task.partials.board-cards', compact('tasks', 'board', 'users', 'labels', 'adminAlerts'))->render(),
            'count' => $tasks->count(),
            'next_offset' => $offset + $tasks->count(),
            'has_more' => $hasMore,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $role, Board $board)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isEmployee = Str::slug($user->getRoleNames()->first()) == 'employee';
        $canViewAllTasks = $user->hasPermissionTo('view all task');

        /**
         * A sub-work item is not a card.
         *
         * Sub-tasks used to be pulled into their column's list like any other
         * task, so a sub-item sat next to its own parent looking exactly like a
         * top-level task with nothing on screen to tell them apart. The board
         * now lists parents only; the items underneath one are reached by
         * expanding the sub-work chip on its card (toggleCardSubtasks in
         * task-subtask.js), which is the only place they appear.
         *
         * The two counts ride along with the parent so the chip can render
         * "1/2" without one request per card.
         */
        $doneIds = $this->doneColumnIds($board);
        $doneWindow = $this->doneWindow($request);

        $topLevelOnly = function ($q) use ($board, $doneIds) {
            $this->boardCardScope($q, $board->id);
            // Done columns hold hundreds of cards. They are NOT loaded here: each
            // is fetched below, one page at a time, inside the chosen period, and
            // the rest arrives as the person scrolls (boardColumnTasks()).
            if ($doneIds) {
                $q->whereNotIn('tasks.column_id', $doneIds);
            }
        };

        $visibility = $this->taskVisibility($user, $isEmployee && !$canViewAllTasks);

        $board = Board::with([
            'board_columns.column.tasks' => function ($q) use ($topLevelOnly, $visibility) {
                $topLevelOnly($q);
                if ($visibility) {
                    $q->where($visibility);
                }
            },
        ])->findOrFail($board->id);

        $doneMeta = [];
        foreach ($board->board_columns as $bc) {
            if (!$bc->column || !in_array($bc->column->id, $doneIds, true)) {
                continue;
            }
            $total = $this->doneQuery($board->id, $bc->column->id, $doneWindow, $visibility, false)->count();
            $page = $this->doneQuery($board->id, $bc->column->id, $doneWindow, $visibility, true)
                ->limit(self::BOARD_PAGE)->get();
            $bc->column->setRelation('tasks', $page);
            $doneMeta[$bc->column->id] = ['total' => $total, 'loaded' => $page->count()];
        }

        $project = Project::find($board->project_id);
        $teamMemberIds = $project->team_members ?? [];

        // Get all boards for the same project (used in the board switcher dropdown)
        $query = Board::with('workspace', 'project', 'user', 'columns');
            $query->whereHas('project', function ($q) use ($user) {
                $q->whereJsonContains('team_members', (string)$user->id);
            });
        $boards = $query->orderBy('id', 'desc')->get();

        $users = User::whereIn('id', $teamMemberIds)->get();
        // $board->load('columns.tasks.users', 'columns.tasks.labels', 'columns.tasks.creator');

        // Get labels for this project
        $labels = Label::where('project_id', $board->project_id)
            ->orderBy('name')
            ->get();

        $adminAlerts = $this->adminAlerts($board, $user);

        return view(
            "task-module.task.show",
            compact("board", "users", "labels", "boards", "adminAlerts", "doneMeta", "doneWindow"),
        );
    }

    /**
     * My Tasks — cross-board, user-specific task list (last 30 days).
     */
    public function myTasks(string $role)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Fetch tasks the user created OR is assigned to, within the last 30 days
        $tasks = Task::with([
            "users",
            "labels",
            "creator",
            "column",
            "board.columns", // belongsToMany with pivot position — used for inline state dropdown
            "subtasks",
        ])
            ->whereNull('parent_id')
            ->where("created_at", ">=", Carbon::now()->subDays(30))
            ->where(function ($q) use ($user) {
                $q->where("created_by", $user->id)->orWhereHas(
                    "users",
                    fn($q2) => $q2->where("users.id", $user->id),
                );
            })
            ->orderBy("due_date", "asc")
            ->paginate(20)
            ->withQueryString();

        // Collect all unique project IDs from the tasks
        $projectIds = $tasks->pluck("project_id")->unique()->filter()->values();

        // Gather all team member IDs from those projects
        $allMemberIds = collect();
        Project::whereIn("id", $projectIds)
            ->get()
            ->each(fn($p) => $allMemberIds->push(...$p->team_members ?? []));

        $users = User::whereIn("id", $allMemberIds->unique()->values())->get();
        $labels = Label::whereIn("project_id", $projectIds)
            ->orderBy("name")
            ->get();

        // Build boardColumnsMap: { boardId: [{id, name, position}, ...] }
        // Used by the JS interception in the blade to populate the drawer/modal state dropdown.
        $boardColumnsMap = [];
        foreach ($tasks as $task) {
            if ($task->board && !isset($boardColumnsMap[$task->board_id])) {
                $boardColumnsMap[$task->board_id] = $task->board->columns
                    ->map(
                        fn($col) => [
                            "id" => $col->id,
                            "name" => $col->name,
                            "position" => $col->pivot->position ?? 1,
                        ],
                    )
                    ->values();
            }
        }

        $viewMode = "my-tasks";

        return view(
            "task-module.task.show",
            compact(
                "tasks",
                "users",
                "labels",
                "role",
                "viewMode",
                "boardColumnsMap",
            ),
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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
            
            // Log each assigned user (one query for all of them, not one each)
            $assignees = User::whereIn('id', $assignedUsers)->get();
            $mailRole = auth()->user()->getRoleNames()->first();
            $mailTargets = [];
            foreach ($assignees as $user) {
                if ($user->id != auth()->id()) {
                    $this->logActivity($task->id, 'assignee_added', "added a new assignee {$user->name}");
                    $mailTargets[] = $user;
                }
            }

            // The emails and phone pushes are slow network calls (one HTTP request
            // per person, per device). They used to run before the response, so
            // "Save" waited on every one of them. They now go to the queue
            // (NotifyTaskAssigned); a failure there can no longer fail or delay the save.
            \App\Jobs\NotifyTaskAssigned::dispatch(
                $task->id,
                (int) auth()->id(),
                (string) $mailRole,
                $assignedUsers,
                collect($mailTargets)->pluck('id')->map(fn ($i) => (int) $i)->all(),
            );
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

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Task created successfully',
                'task_id' => $task->id
            ]);
        }

        return redirect()->back()->with('success', 'Task created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($role, Task $task)
    {
        $task->load('parent', 'creator', 'users', 'labels');
        return response()->json([
            'success' => true,
            'task' => [
                'title'           => $task->title,
                'description'     => $task->description,
                'priority'        => $task->priority,
                'column_id'       => $task->column_id,
                'start_date'      => $task->start_date?->toIso8601String(),
                'due_date'        => $task->due_date?->toIso8601String(),
                'parent_id'       => $task->parent_id,
                'parent_title'    => $task->parent?->title,
                'assignee_ids'    => $task->users->pluck('id'),
                'label_ids'       => $task->labels->pluck('id'),
                'created_by_name' => $task->creator?->name,
                'updated_by'      => ['name' => auth()->user()->name],
                'updated_at'      => $task->updated_at?->toIso8601String(),
            ]
        ]);
    }

    /**
     * One task, printed — what the drawer's "Task report" button downloads.
     *
     * Prints what the drawer shows on screen: the property block, the
     * description, the sub-work items with the time each one banked, links,
     * attachments and the activity trail.
     *
     * Deliberately NOT part of Report\TaskReportController: that one reports
     * across many tasks and sits behind the "view task report" permission, which
     * employees do not have. This is scoped to a single task and is open to
     * anyone who can already open that task's drawer — same reach as show().
     */
    public function report($role, Task $task)
    {
        $task->load([
            'column',
            'board.project',
            'board.workspace',
            'creator',
            'users',
            'labels',
            'parent',
            'links.creator',
            'attachments.uploader',
        ]);

        // Same read-time reconcile the drawer does, so a run left open past the
        // 8h cap prints the capped total rather than the wall-clock one.
        $task->reconcileTimer();

        $subtasks = $task->subtasks()->with(['users', 'column'])->get();
        $subtasks->each(fn (Task $sub) => $sub->reconcileTimer());

        // Activity and comments, merged newest-first — the order the drawer's
        // Activity tab uses.
        $activities = $task->activityLogs()->with('user:id,name')->get()
            ->map(fn (TaskActivityLog $log) => [
                'type'      => 'activity',
                'user_name' => $log->user->name ?? 'Unknown',
                'text'      => (string) $log->description,
                'at'        => $log->created_at,
            ])
            ->concat($task->comments()->with('user:id,name')->get()->map(fn (TaskComment $c) => [
                'type'      => 'comment',
                'user_name' => $c->user->name ?? 'Unknown',
                'text'      => (string) $c->comment,
                'at'        => $c->created_at,
            ]))
            ->sortByDesc(fn (array $row) => $row['at']?->getTimestamp() ?? 0)
            ->values();

        $company = $task->board?->workspace;

        // dompdf has remote fetching disabled, so the logo has to be handed over
        // as a filesystem path — and only when it is really there, or the whole
        // report would fail on a company with a stale logo row.
        $logoPath = $company?->logo ? public_path(ltrim($company->logo, '/')) : null;

        $pdf = Pdf::loadView('task-module.task.report_pdf', [
            'task'        => $task,
            'subtasks'    => $subtasks,
            'activities'  => $activities,
            'trackedMs'   => (int) $subtasks->sum(fn (Task $sub) => $sub->elapsedMs()),
            'ownMs'       => $task->elapsedMs(),
            'company'     => $company,
            'logoPath'    => ($logoPath && is_file($logoPath)) ? $logoPath : null,
            'generatedBy' => Auth::user()?->name ?? 'Unknown',
        ])->setPaper('a4');

        return $pdf->download('TASK-' . $task->id . '_report_' . now()->format('Y-m-d_His') . '.pdf');
    }

    /**
     * Copy a task to the same board and column.
     */
    public function copy($role, Task $task)
    {
        try {
            DB::beginTransaction();

            $position = Task::where('column_id', $task->column_id)->max('position') + 1;

            $newTask = Task::create([
                'company_id' => $task->company_id,
                'project_id' => $task->project_id,
                'board_id' => $task->board_id,
                'column_id' => $task->column_id,
                'title' => 'Copy of ' . $task->title,
                'description' => $task->description,
                'priority' => $task->priority,
                'start_date' => $task->start_date,
                'due_date' => $task->due_date,
                'assigned_to' => $task->assigned_to,
                'position' => $position,
                'created_by' => auth()->id(),
            ]);

            if ($task->users->isNotEmpty()) {
                $newTask->users()->sync($task->users->pluck('id')->all());
            }

            if ($task->labels->isNotEmpty()) {
                $newTask->labels()->sync($task->labels->pluck('id')->all());
            }

            foreach ($task->links as $link) {
                TaskLink::create([
                    'task_id' => $newTask->id,
                    'url' => $link->url,
                    'display_title' => $link->display_title,
                    'created_by' => auth()->id(),
                ]);
            }

            foreach ($task->attachments as $attachment) {
                $sourcePath = public_path($attachment->file_path);
                if (file_exists($sourcePath)) {
                    $destinationDirectory = dirname($sourcePath);
                    $destinationName = uniqid('copy_') . '_' . basename($sourcePath);
                    $destinationPath = $destinationDirectory . DIRECTORY_SEPARATOR . $destinationName;
                    copy($sourcePath, $destinationPath);

                    TaskAttachment::create([
                        'task_id' => $newTask->id,
                        'file_name' => $attachment->file_name,
                        'file_path' => ltrim(str_replace('\\', '/', str_replace(public_path(), '', $destinationPath)), '/'),
                        'file_size' => filesize($destinationPath),
                        'file_type' => $attachment->file_type,
                        'uploaded_by' => auth()->id(),
                    ]);
                }
            }

            $this->logActivity($newTask->id, 'task_created', 'copied the task');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task copied successfully',
                'task_id' => $newTask->id,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to copy task: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, Task $task)
    {
        if ($stop = $this->blockedByRestriction($task)) {
            return $stop;
        }

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
            'status',
            'parent_id'
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

        /* ── Dates and the timer are one fact shown twice ────────────────────
         * The "5.09pm:24/08/2026 → 5.09pm:24/08/2026" range chip and the Start
         * and Due chips describe the same two moments, so whichever the user
         * touches LAST wins and is written to both. This is the dates → timer
         * half; startTimer() and completeTimer() are the timer → dates half.
         *
         * Due date only rewrites completed_at on an item that is ALREADY done.
         * On an open item a due date is a deadline — often in the future — and
         * copying it into completed_at would mark unfinished work as finished
         * and move it in every project's completion percentage.
         */
        if ($this->timersReady()) {
            if (array_key_exists('start_date', $data)) {
                $data['timer_first_started_at'] = $data['start_date'];

                /* BACK-DATING THE START CHIP BACK-DATES THE CLOCK.
                 *
                 * The owner's case: a task is dropped into In Progress at 11:30
                 * but the work actually began at 11:00. Setting the Start chip
                 * to 11:00 has to make the counter read 30 min and carry on from
                 * there — otherwise the two disagree on the same fact and the
                 * chip is the one telling the truth.
                 *
                 * Done by re-basing the running clock rather than adding to the
                 * bank: elapsed becomes exactly now - start_date, which is what
                 * "work began at 11:00" means. Any previously banked time is
                 * subsumed by that statement, so it is cleared — otherwise
                 * correcting a start time would quietly inflate the total.
                 *
                 * Only when the task is actually counting, and only when it has
                 * no sub-work items: a parent's time is the sum of its
                 * children's, and its Start chip says when the first of them
                 * began, not how long the parent has run.
                 *
                 * A start date in the future is harmless — elapsedMs() floors
                 * the run at zero — and one further back than eight hours is
                 * capped by TIMER_MAX_RUN_MS, the same ceiling that stops a
                 * forgotten tab banking a whole night.
                 */
                if (!empty($data['start_date'])
                    && Carbon::parse($data['start_date'])->lessThanOrEqualTo(now())
                    && $task->isTimerRunning()
                    && !$task->subtasks()->exists()) {
                    $data['timer_started_at'] = $data['start_date'];
                    $data['timer_accum_ms']   = 0;
                }
            }

            if (array_key_exists('due_date', $data) && $task->completed_at) {
                $data['completed_at'] = $data['due_date'];
            }
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

                // Same rule as the drag in move(): set to In Progress and a task
                // with no sub-work items starts counting.
                if ($isNewColumnInProgress) {
                    $data = array_merge($data, $this->autoStartFields($task));
                }

                if (!$isOldColumnCompleted && $isNewColumnCompleted) {
                    // Moving to completed column
                    $data['completed_at'] = now();

                    if (!$task->due_date && !array_key_exists('due_date', $data)) {
                        $data['due_date'] = now();
                    }

                    // The State dropdown is the other face of the ✓ button, so it
                    // stops the clock too. Without this a running item dragged to
                    // Done keeps counting with no Pause control left to stop it,
                    // and banks the full 8h cap overnight.
                    $this->bankRunningTime($task);
                    $data['pre_complete_column_id'] = $oldColumn?->id;
                } elseif (!$isNewColumnCompleted) {
                    /* Anything that is not Done has not ended, so it carries no end
                     * date. This used to fire only when coming BACK from Done, which
                     * left the date behind: finishing an item stamps the Due chip,
                     * and dragging it to To Do or In Progress afterwards kept that
                     * stamp on a card that was plainly not finished. Clearing it for
                     * every non-Done target is the rule the board already implies —
                     * the red chip means "ended on", and only Done has an end.
                     */
                    $data['completed_at'] = null;
                    $data['due_date'] = null;
                    $data['pre_complete_column_id'] = null;
                }

                // Back to Note / To Do means the work has not begun: the clock is
                // stopped and every trace of it removed, so nothing in those
                // columns ever shows a time. Moving out to In Progress captures a
                // fresh start above.
                if ($this->timersReady() && $this->isBacklogColumn($newColumn->name)) {
                    $data = array_merge($data, $this->resetTimerFields());
                }

                // Re-update the task with timestamps if they changed
                // pre_complete_column_id rides along with completed_at: it is set in
                // the same branch above, and without it here the second update()
                // drops it and un-ticking cannot restore the original column.
                $timestampUpdates = array_intersect_key($data, array_flip([
                    'start_date', 'due_date', 'completed_at', 'pre_complete_column_id',
                    // the backlog reset above lands after the first update(), so its
                    // fields have to be listed here or they are silently dropped —
                    // and so does the start-date re-base further up
                    'timer_started_at', 'timer_accum_ms', 'timer_first_started_at',
                ]));
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
            $fresh = $task->fresh()->load('labels', 'users');

            return response()->json([
                'success' => true,
                'task' => $fresh,
                // The card's time chip, so setting a Start date or a State
                // repaints it on the spot. Back-dating the Start chip re-bases
                // the clock (see above), and a chip still showing the old total
                // beside a corrected date is the disagreement this is here to
                // prevent.
                'timer'   => $this->timersReady() ? $this->timerPayload($fresh) : null,
                'tracked' => $this->timersReady() ? $this->trackedPayload($fresh) : null,
                'last_edited_by' => auth()->user()->name,
                'last_edited_at' => $task->updated_at->toIso8601String()
            ]);
        }

        return back();
    }

    /**
     * Helper method to log task activities
     */
    /**
     * A task a super admin has locked may not be edited or deleted by anyone
     * else. Enforced here and not only in the drawer, because the drawer is a
     * suggestion and this is the rule: /tasks/{id} accepts a PUT from anything
     * that can sign in.
     *
     * Returns a JSON 403 to stop on, or null to carry on. Silent when the
     * oversight table has not been migrated yet — no table, no locks, exactly
     * the behaviour this app had before the feature existed.
     */
    private function blockedByRestriction(Task $task)
    {
        if (!EmployeeTaskOversightController::hasOversightTable()) {
            return null;
        }

        $user = Auth::user();

        if ($user && ($user->is_super_admin || $user->hasRole('super admin'))) {
            return null;
        }

        try {
            if (!$task->isRestricted()) {
                return null;
            }
        } catch (Throwable $e) {
            // Cannot tell — let the work through. A lock that fails open costs
            // one unguarded edit; a lock that fails closed breaks every board.
            return null;
        }

        return response()->json([
            'success'    => false,
            'restricted' => true,
            'message'    => 'This task is restricted by the Super Admin — you cannot edit or delete it.',
        ], 403);
    }

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
        if ($stop = $this->blockedByRestriction($task)) {
            return $stop;
        }

        try {
            $taskId = $task->id;
            $boardId = $task->board_id;

            // Detach subtasks (set their parent_id to null so they become top-level)
            Task::where('parent_id', $taskId)->update(['parent_id' => null]);

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

            // Delete the task (soft-delete)
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

            // Dropped into In Progress: a task with no sub-work items starts
            // counting. Guarded on the column actually changing so that
            // reordering a card inside In Progress does not restart its clock.
            if ($isNewColumnInProgress && $oldColumnId != $request->column_id) {
                $updateData = array_merge($updateData, $this->autoStartFields($task));
            }

            // Set completed_at based on column change
            if (!$isOldColumnCompleted && $isNewColumnCompleted) {
                // Moving to completed column
                $updateData['completed_at'] = now();

                if (!$task->due_date) {
                    $updateData['due_date'] = now();
                }

                // Same rule as the ✓ button and the State dropdown: finishing a
                // work item stops its clock. (Drag-and-drop on the board lands
                // here rather than in update().)
                $this->bankRunningTime($task);
                $updateData['pre_complete_column_id'] = $oldColumnId;
            } elseif (!$isNewColumnCompleted && $oldColumnId != $request->column_id) {
                // Not Done means not ended: no end date. Guarded on the column
                // actually changing, because this block also runs for a plain
                // reorder inside one column, and dragging a card up its own list
                // must not rewrite its dates.
                $updateData['completed_at'] = null;
                $updateData['due_date'] = null;
                $updateData['pre_complete_column_id'] = null;
            }

            // Dragged back to Note / To Do: the work has not begun, so the clock
            // and the captured start time go with it. Same rule as the State
            // dropdown in update().
            if ($this->timersReady() && $newColumn && $this->isBacklogColumn($newColumn->name)) {
                $updateData = array_merge($updateData, $this->resetTimerFields());
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

        $moved = Task::with('labels', 'users')->find($request->task_id);

        return response()->json([
            'success' => true,
            'task'    => $moved,
            // Dropping a childless task into In Progress starts its clock (see
            // autoStartFields). The board moves cards over AJAX, so without the
            // clock in this answer the card would go on showing "0/0" until
            // somebody refreshed — the timer running invisibly.
            'timer'   => $this->timersReady() && $moved ? $this->timerPayload($moved) : null,
            // A parent's chip shows its sub-work items' total, which a move to
            // Done can change (the ✓ banks a running child). Sent alongside so
            // one answer repaints the chip whichever kind of card it is.
            'tracked' => $this->timersReady() && $moved ? $this->trackedPayload($moved) : null,
        ]);
    }

    /**
     * A column that means "not started yet" — Note, To Do, Backlog, Ideas.
     *
     * Defined by exclusion rather than by a name list, because every company
     * names these differently: anything that is neither in-progress nor done is
     * work that has not begun. Landing here wipes the clock (see resetTimerFor).
     */
    private function isBacklogColumn(?string $columnName): bool
    {
        $columnName = (string) $columnName;

        return $columnName !== ''
            && !$this->isInProgressColumn($columnName)
            && !preg_match(self::DONE_COLUMN_PATTERN, $columnName);
    }

    /**
     * Send a work item back to "never started": stop the clock, drop the banked
     * time, and clear both the Start chip and the range chip.
     *
     * The owner's rule: nothing in Note or To Do carries a time. Moving out to
     * In Progress captures a fresh start; moving back here has to remove it
     * again, or the card claims work that, by its own column, has not begun.
     *
     * Returns the fields to merge into the pending update rather than saving,
     * so it rides along with the caller's single write.
     */
    private function resetTimerFields(): array
    {
        return [
            'start_date'             => null,
            'timer_started_at'       => null,
            'timer_accum_ms'         => 0,
            'timer_first_started_at' => null,
        ];
    }

    /**
     * A task with nothing under it IS the work item, so entering In Progress
     * starts its own clock — the same thing Start does on a sub-work item.
     *
     * A task that HAS sub-work items is left alone: its time is the sum of
     * theirs, and a second clock on the parent would count the same work twice.
     * That is also why storeSubtask() stops this clock the moment the first
     * sub-item appears.
     *
     * Returns fields to merge into the caller's pending update rather than
     * saving, so it rides along with the caller's single write. Empty when
     * nothing should change — already running, has children, or the timer
     * columns have not been migrated yet.
     */
    private function autoStartFields(Task $task): array
    {
        if (!$this->timersReady() || $task->isTimerRunning() || $task->subtasks()->exists()) {
            return [];
        }

        /* Seeded from an existing start_date rather than always from now():
         * startTimer() overwrites start_date because the user pressing Start
         * IS the statement of when work began, but a column move is not, and
         * silently rewriting a date somebody typed would be wrong. */
        $first = $task->timer_first_started_at ?: ($task->start_date ?: now());

        return [
            'timer_started_at'       => now(),
            'timer_first_started_at' => $first,
            'start_date'             => $first,
        ];
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

    /* ═══════════════════════════════════════════════════════════
     | Subtask Methods
     ╚══════════════════════════════════════════════════════════ */

    /**
     * Get all subtasks for a task.
     */
    public function getSubtasks($role, Task $task)
    {
        $subtasks = $task->subtasks()->with(['users', 'column'])->get()->map(function ($sub) {
            // A run left open overnight is force-paused at the 8h cap here, on
            // read, so a forgotten browser tab corrects itself without needing a
            // scheduled job.
            $sub->reconcileTimer();

            return [
                'id'              => $sub->id,
                'title'           => $sub->title,
                'priority'        => $sub->priority,
                'column_name'     => $sub->column?->name ?? '—',
                'column_id'       => $sub->column_id,
                'column_position' => $sub->column?->position ?? 1,
                'completed_at'    => $sub->completed_at?->toIso8601String(),
                'is_done'         => !is_null($sub->completed_at),
                'start_date'      => $sub->start_date?->format('Y-m-d H:i:s'),
                'due_date'        => $sub->due_date?->format('Y-m-d H:i:s'),
                'assignees'    => $sub->users->map(fn($u) => [
                    'id'      => $u->id,
                    'name'    => $u->name,
                    'initial' => strtoupper(substr($u->name, 0, 1)),
                    'image'   => $u->image ? asset($u->image) : null,
                ]),
                'parent_id'    => $sub->parent_id,
                'parent_title' => $sub->title,
                'timer'        => $this->timerPayload($sub),
            ];
        });

        $total = $subtasks->count();
        $done  = $subtasks->where('is_done', true)->count();

        // A task with no sub-work items is its own work item, and the expanded
        // panel is where its Start / Pause / Done live. Sent on every response
        // so the panel never has to ask a second time; the client ignores it
        // once there are children.
        $task->reconcileTimer();

        return response()->json([
            'success'      => true,
            'subtasks'     => $subtasks,
            'total'        => $total,
            'done'         => $done,
            'tracked'      => $this->trackedPayload($task),
            'parent_timer' => $this->timerPayload($task),
        ]);
    }

    /* ═══════════════════════════════════════════════════════════
     | Sub-work item time tracking — Start / Pause / Done
     |
     | Mirrors the reference build's per-phase timers. The rules, in full:
     |   Start  banks and stops any other running sibling under the same parent
     |          (so two clocks can never run on one task at once), stamps the
     |          first-start once, and starts the clock.
     |   Pause  banks now - started_at and stops the clock.
     |   Done   banks anything still running, stamps completed_at, and stamps
     |          first-start too if the item was ticked off without ever running.
     |   Reopen clears completed_at. The reference has no reopen; ours needs one
     |          because completed_at already drives the progress ring.
     |
     | Deliberately NOT done here: moving the item to another column, and
     | touching the parent's state. Column moves keep working exactly as before.
     ╚══════════════════════════════════════════════════════════ */

    /**
     * The timer half of a task's JSON — everything the row needs to draw itself.
     *
     * elapsed_ms is computed server side and paired with a client-side anchor in
     * the browser, so the live counter never depends on the viewer's clock being
     * in step with the server's.
     */
    private function timerPayload(Task $task): array
    {
        $elapsed = $task->elapsedMs();

        return [
            'running'      => $task->isTimerRunning(),
            'elapsed_ms'   => $elapsed,
            'elapsed_text' => $elapsed > 0 || $task->timer_first_started_at
                ? Task::humanDuration($elapsed)
                : 'not started',
            'started'      => (bool) $task->timer_first_started_at,
            'start_label'  => Task::stampLabel($task->timer_first_started_at),
            'end_label'    => Task::stampLabel($task->completed_at),
            'is_done'      => !is_null($task->completed_at),
        ];
    }

    /**
     * Total time banked across a parent's sub-work items, for the section header.
     */
    private function trackedPayload(Task $task): array
    {
        $subtasks = $task->subtasks()->get();
        $total    = $subtasks->sum(fn(Task $sub) => $sub->elapsedMs());

        return [
            'total_ms'   => (int) $total,
            'total_text' => Task::humanDuration((int) $total),
            // The card's chip has to know whether to keep counting or stand
            // still; total_ms alone cannot say.
            'running'    => $subtasks->contains(fn (Task $sub) => $sub->isTimerRunning()),
            // Returned alongside the time so the progress ring can be repainted
            // after a ✓ without re-fetching and re-rendering the whole list.
            'done'       => $subtasks->whereNotNull('completed_at')->count(),
            'total'      => $subtasks->count(),
        ];
    }

    /**
     * Fresh timer state for a task plus its parent's running total — the single
     * response shape all three timer actions return.
     */
    private function timerResponse(Task $task, string $message): \Illuminate\Http\JsonResponse
    {
        $task->refresh();

        return response()->json([
            'success' => true,
            'message' => $message,
            'id'      => $task->id,
            // Start can lift an item out of To Do and Done drops it into the
            // Done column. The board cannot see either from a timer response,
            // so it is told where the task now is and reloads only if that
            // moved — a reload on every Pause would throw away the scroll.
            'column_id' => $task->column_id,
            'timer'   => $this->timerPayload($task),
            'tracked' => $task->parent_id
                ? $this->trackedPayload($task->parent()->first() ?? $task)
                : null,
        ]);
    }

    /**
     * The timer columns exist. Compiled assets are uploaded by hand and can reach
     * the server before the migration does; reads survive that (a missing
     * attribute reads as null, so the row shows "not started"), but a write would
     * throw "Unknown column" and 500. Say so plainly instead.
     */
    private function timersReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasColumn('tasks', 'timer_started_at');
    }

    private function timersNotReady(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Time tracking is not enabled on this server yet — the database migration still has to run.',
        ], 503);
    }

    public function startTimer($role, Task $task)
    {
        if (!$this->timersReady()) {
            return $this->timersNotReady();
        }

        $task->reconcileTimer();

        if ($task->isTimerRunning()) {
            return $this->timerResponse($task, 'Timer already running.');
        }

        DB::transaction(function () use ($task) {
            // One clock per parent: bank and stop every running sibling first.
            if ($task->parent_id) {
                Task::where('parent_id', $task->parent_id)
                    ->where('id', '!=', $task->id)
                    ->whereNotNull('timer_started_at')
                    ->get()
                    ->each(function (Task $sibling) {
                        $this->bankRunningTime($sibling);
                        $this->logActivity($sibling->id, 'timer_paused', 'timer paused automatically — another sub-work item was started');
                    });
            }

            $first = $task->timer_first_started_at ?: now();

            // Nothing in Note / To Do carries a time, so starting the clock on an
            // item sitting there moves it to In Progress rather than leaving a
            // running timer in a column that means "not started". Without this the
            // two rules contradict each other and the next column change wipes the
            // clock the user just started.
            $progress = $this->isBacklogColumn($task->column?->name)
                ? $this->progressColumnFor($task)
                : null;

            $task->forceFill([
                'timer_started_at'       => now(),
                'timer_first_started_at' => $first,
                // The Start chip and the left half of the range chip are the same
                // moment, so running the clock writes it to both. Last action wins.
                'start_date'             => $first,
                'column_id'              => $progress ?: $task->column_id,
            ])->save();
        });

        $this->logActivity($task->id, 'timer_started', 'started the timer');

        return $this->timerResponse($task, 'Timer started.');
    }

    public function pauseTimer($role, Task $task)
    {
        if (!$this->timersReady()) {
            return $this->timersNotReady();
        }

        $task->reconcileTimer();

        if (!$task->isTimerRunning()) {
            return $this->timerResponse($task, 'Timer is not running.');
        }

        $this->bankRunningTime($task);
        $this->logActivity($task->id, 'timer_paused', 'paused the timer at ' . Task::humanDuration($task->fresh()->elapsedMs()));

        return $this->timerResponse($task, 'Timer paused.');
    }

    /**
     * The ✓ button — a toggle, so a mis-click is one click to undo.
     *
     * The tick and the State dropdown are two faces of one thing, so ✓ also moves
     * the item into the board's Done column and un-ticking moves it back where it
     * came from. Without that they drift: the card sits in "To Do" while the ring
     * counts it finished, and `completed_at` — which the company dashboards and
     * every project's completion percentage read — would be set by a control that
     * left no trace on the board.
     */
    public function completeTimer($role, Task $task)
    {
        if (!$this->timersReady()) {
            return $this->timersNotReady();
        }

        $task->reconcileTimer();

        if ($task->completed_at) {
            // Reopen: back to the column it was ticked from.
            $restore = $this->openColumnFor($task);

            $task->forceFill([
                'completed_at'           => null,
                'column_id'              => $restore ?: $task->column_id,
                'pre_complete_column_id' => null,
            ])->save();

            $this->logActivity($task->id, 'reopened', 'reopened the sub-work item');

            return $this->timerResponse($task, 'Sub-work item reopened.');
        }

        // Marking done stops the clock: a finished item must never keep counting.
        $this->bankRunningTime($task);
        $task->refresh();

        $done     = $this->doneColumnFor($task);
        $finished = now();
        $first    = $task->timer_first_started_at ?: $finished;

        $task->forceFill([
            'completed_at'           => $finished,
            // Ticked off without ever starting the clock: stamp a start anyway so
            // the range chip reads "3.59pm:24/08/2026 → 3.59pm:24/08/2026" rather
            // than losing the date entirely.
            'timer_first_started_at' => $first,
            // Both chips mirror the range they sit above — finishing the item is
            // the last action, so it wins over whatever they held before.
            'start_date'             => $first,
            'due_date'               => $finished,
            'pre_complete_column_id' => $done ? $task->column_id : null,
            'column_id'              => $done ?: $task->column_id,
        ])->save();

        $this->logActivity($task->id, 'completed', 'marked done — ' . Task::humanDuration($task->fresh()->elapsedMs()) . ' tracked');

        return $this->timerResponse($task, 'Sub-work item marked done.');
    }

    /**
     * The board's "done" column, by the same name test the board has always used
     * for `completed_at`. Null when the board has no such column — the tick then
     * marks the item done without pretending to move it somewhere that
     * does not exist.
     */
    private function doneColumnFor(Task $task): ?int
    {
        return $this->boardColumns($task)
            ->first(fn($c) => preg_match(self::DONE_COLUMN_PATTERN, (string) $c->name))?->id;
    }

    /**
     * The board's in-progress column, if it has one. Null on a board that does
     * not — starting the clock then leaves the item where it is rather than
     * inventing a move.
     */
    private function progressColumnFor(Task $task): ?int
    {
        return $this->boardColumns($task)
            ->first(fn($c) => $this->isInProgressColumn((string) $c->name))?->id;
    }

    /**
     * Where an un-ticked item goes: the column it was ticked from, or failing
     * that (ticked before this was recorded, or that column since deleted) the
     * board's in-progress column, or its first non-done column.
     */
    private function openColumnFor(Task $task): ?int
    {
        $columns = $this->boardColumns($task);

        if ($task->pre_complete_column_id && $columns->contains('id', $task->pre_complete_column_id)) {
            return (int) $task->pre_complete_column_id;
        }

        $open = $columns->reject(fn($c) => preg_match(self::DONE_COLUMN_PATTERN, (string) $c->name));

        return $open->first(fn($c) => $this->isInProgressColumn((string) $c->name))?->id
            ?? $open->first()?->id;
    }

    /**
     * The columns of the board this task sits on, in board order — read through
     * the same board_columns pivot the board itself renders from, so the tick can
     * never move an item to a column the board does not show.
     */
    private function boardColumns(Task $task)
    {
        $board = $task->board_id ? Board::with('columns')->find($task->board_id) : null;

        return $board ? $board->columns : collect();
    }

    /** The board's long-standing test for "this column means finished". */
    private const DONE_COLUMN_PATTERN = '/done|completed|complete|closed|finish|finished/i';

    /**
     * Move the run in progress into the banked total and stop the clock.
     *
     * The UPDATE is conditional on the clock still being the one we read, so a
     * double-clicked Pause cannot bank the same stretch twice: the second request
     * matches no row and changes nothing. Capped, so one unbroken run can never
     * bank more than eight hours.
     */
    private function bankRunningTime(Task $task): void
    {
        if (!$task->isTimerRunning()) {
            return;
        }

        $startedAt = $task->timer_started_at;
        // Signed for the same reason as Task::elapsedMs() — an absolute diff
        // banks real hours off a start stamp that lies in the future.
        $run       = (int) max(0, now()->getTimestampMs() - $startedAt->getTimestampMs());
        $banked    = min($run, Task::TIMER_MAX_RUN_MS);

        Task::where('id', $task->id)
            ->where('timer_started_at', $startedAt)
            ->update([
                'timer_accum_ms'   => DB::raw('timer_accum_ms + ' . $banked),
                'timer_started_at' => null,
                'updated_at'       => now(),
            ]);
    }

    /**
     * Create a new subtask under a parent task.
     * Inherits board_id, column_id, project_id, company_id from parent.
     */
    public function storeSubtask(Request $request, $role, Task $task)
    {
        /* The parent's own clock only runs while it has nothing under it. From
         * the first sub-work item on, the parent's time is the sum of its
         * children's, so bank whatever it has run and stop it — otherwise the
         * same stretch of work is counted on the parent AND on the item. */
        if ($this->timersReady() && $task->isTimerRunning()) {
            $this->bankRunningTime($task);
            $this->logActivity($task->id, 'timer_paused', 'timer stopped automatically — the work was split into sub-work items');
        }

        $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'priority'      => 'nullable|in:low,medium,high',
            'column_id'     => 'nullable|exists:columns,id',
            'start_date'    => 'nullable|date',
            'due_date'      => 'nullable|date',
            'assigned_users' => 'nullable|array',
            'assigned_users.*' => 'exists:users,id',
            'label_ids'     => 'nullable|array',
            'label_ids.*'   => 'exists:labels,id',
        ]);

        $position = Task::where('column_id', $request->column_id ?? $task->column_id)->max('position') + 1;

        $subtask = Task::create([
            'parent_id'   => $task->id,
            'company_id'  => $task->company_id,
            'project_id'  => $task->project_id,
            'board_id'    => $task->board_id,
            'column_id'   => $request->column_id ?? $task->column_id,
            'title'       => $request->title,
            'description' => $request->description,
            'priority'    => $request->priority,
            'start_date' => $request->start_date ? Carbon::parse($request->start_date)->format('Y-m-d H:i:s') : null,
            'due_date'   => $request->due_date   ? Carbon::parse($request->due_date)->format('Y-m-d H:i:s')   : null,
            'position'   => $position,
            'created_by' => auth()->id(),
        ]);

        if (!empty($request->assigned_users)) {
            $subtask->users()->sync($request->assigned_users);
        }

        if ($request->has('label_ids')) {
            $subtask->labels()->sync($request->label_ids);
        }

        $this->logActivity($subtask->id, 'task_created', 'created as sub-task of #' . $task->id);
        $this->logActivity($task->id,  'subtask_added', 'added a sub-task: ' . $subtask->title);

        return response()->json([
            'success' => true,
            'message' => 'Sub-task created successfully',
            'subtask' => [
                'id'           => $subtask->id,
                'title'        => $subtask->title,
                'priority'     => $subtask->priority,
                'column_name'  => $subtask->column?->name ?? '—',
                'column_id'    => $subtask->column_id,
                'completed_at' => null,
                'is_done'      => false,
                'due_date'     => $subtask->due_date?->format('M d, Y'),
                'assignees'    => [],
                'parent_id'    => $task->id,
                'parent_title' => $task->title,
            ],
        ]);
    }

    /**
     * Set a parent on an existing task (Add existing → subtask).
     */
    public function setParent(Request $request, $role, Task $task)
    {
        $request->validate(['parent_id' => 'required|exists:tasks,id']);

        $parentId = $request->parent_id;

        // Prevent circular references: new parent must not be a descendant of this task
        if ($parentId == $task->id) {
            return response()->json(['success' => false, 'message' => 'A task cannot be its own parent.'], 422);
        }

        $task->update(['parent_id' => $parentId]);

        $parent = Task::find($parentId);
        $this->logActivity($task->id, 'parent_set', 'linked to parent task #' . $parentId);
        $this->logActivity($parentId, 'subtask_added', 'added an existing task as sub-task: ' . $task->title);

        return response()->json([
            'success'      => true,
            'message'      => 'Task linked as sub-task successfully',
            'parent_id'    => $parentId,
            'parent_title' => $parent?->title,
        ]);
    }

    /**
     * Search tasks by title (for "Add existing" sub-task picker).
     */
    public function searchTasks(Request $request, $role)
    {
        $q              = trim($request->get('q', ''));
        $excludeId      = $request->get('exclude_id');
        $boardId        = $request->get('board_id');
        $workspaceId    = $request->get('workspace_id');
        $workspaceLevel = $request->boolean('workspace_level');

        $query = Task::with('column')
            ->whereNull('parent_id'); // Only tasks not already linked to another parent

        if ($q !== '') {
            $query->where('title', 'like', "%{$q}%");
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($workspaceLevel) {
            if ($workspaceId) {
                $query->where('company_id', $workspaceId);
            }
        } elseif ($boardId) {
            $query->where('board_id', $boardId);
        }

        $tasks = $query->orderBy('created_at', 'desc')->limit(15)->get()->map(fn($t) => [
            'id'          => $t->id,
            'title'       => $t->title,
            'column_name' => $t->column?->name ?? '—',
            'priority'    => $t->priority,
        ]);

        return response()->json(['success' => true, 'tasks' => $tasks]);
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
