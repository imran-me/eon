<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskOversight;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * EMPLOYEE TASK OVERSIGHT — the super admin standing behind one employee.
 *
 * Transcribed from the reference build's Group ▸ Task Oversight: pick a person,
 * open THEIR board, and act on it with powers they do not have.
 *
 * The rule that shapes this whole class: what opens must be the employee's
 * board, not a report about it. Same columns, same cards, same drawer, same
 * dropdowns, same drag-and-drop — the filter that decides which tasks appear is
 * the employee's, so a super admin sees exactly the screen the employee sees,
 * with three extra controls in the drawer. Anything else and the two views
 * drift apart the first time somebody changes a card.
 *
 * That is why this renders `task-module.task.show` with the very same variables
 * TaskController::index() builds, plus $oversightFor. The blade branches on
 * that one variable and nothing else.
 *
 * SAFETY — this class must never be the reason a page dies:
 *   • `task_oversight` arrives by migration, and deploys land before migrations
 *     run. Every read of it is behind hasOversightTable(), so on a server whose
 *     migration has not run yet the board renders with no verdicts rather than
 *     throwing.
 *   • The stats on the picker are decoration; a failure there costs the numbers
 *     and nothing else.
 */
class EmployeeTaskOversightController extends Controller
{
    /**
     * Does the verdict table exist? Cached per request — this is asked once per
     * board render and a SHOW TABLES round trip per card would be absurd.
     */
    private static ?bool $hasTable = null;

    public static function hasOversightTable(): bool
    {
        if (self::$hasTable === null) {
            try {
                self::$hasTable = Schema::hasTable('task_oversight');
            } catch (Throwable $e) {
                self::$hasTable = false;
            }
        }

        return self::$hasTable;
    }

    /**
     * Only the super admin. Checked here as well as on the route and in the
     * sidebar, because a route is a door and this is the lock: the URL is
     * guessable and the powers behind it are real.
     */
    private function guard(): void
    {
        $user = Auth::user();

        abort_unless($user && ($user->is_super_admin || $user->hasRole('super admin')), 403);
    }

    /* ── The picker ─────────────────────────────────────────────────────── */

    /**
     * Everyone whose board can be opened, with enough of a summary to know who
     * needs looking at before you click.
     */
    public function index(Request $request, $role)
    {
        $this->guard();

        $q = trim((string) $request->get('q', ''));

        $people = $this->boardablePeople($q);

        $stats = $this->statsFor($people->pluck('id')->all());

        // Sort by "needs attention" first, then by workload: a picker that opens
        // on whoever is flagged saves the scroll that matters most.
        $people = $people->sortByDesc(function ($p) use ($stats) {
            $s = $stats[$p->id] ?? null;

            return [(int) ($s->flags ?? 0), (int) ($s->locks ?? 0), (int) ($s->total ?? 0)];
        })->values();

        return view('task-module.oversight.index', [
            'people' => $people,
            'stats'  => $stats,
            'q'      => $q,
            'role'   => $role,
        ]);
    }

    /**
     * Everyone who can actually have a task board, with their company,
     * department and designation loaded.
     *
     * The install carries 107 user accounts, but 84 of them are travel agents,
     * vendors and customers — and not one of those is on a task, has created a
     * task, or sits on a project team. They cannot have a board, so listing
     * them buried the twenty-odd people who can behind a wall of names like
     * "BANGLA AIR".
     *
     * The rule is written as a POSITIVE test rather than a blacklist, so it
     * corrects itself: hold any role that is not agent/vendor/customer, or be
     * on a task, or have created one, or sit on a project team, and you are
     * here. Give a vendor account a task tomorrow and they appear tomorrow.
     *
     * $q searches everything a person might be looked up by — name, employee
     * ID, email, phone, their company, their department, their designation and
     * their role.
     */
    private function boardablePeople(?string $q = null)
    {
        $external = ['agent', 'vendor', 'customer'];

        // team_members is a JSON array of stringified ids on projects.
        $teamIds = Project::pluck('team_members')
            ->flatMap(fn ($j) => is_array($j) ? $j : (json_decode((string) $j, true) ?: []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return User::query()
            ->with([
                'company:id,name,short_name',
                'profile:id,user_id,department_id,designation_id',
                'profile.department:id,name',
                'profile.designation:id,name',
                'roles:id,name',
            ])
            ->where(function ($w) use ($external, $teamIds) {
                $w->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', $external))
                    ->orWhereHas('roles', fn ($r) => $r->whereNotIn('name', $external))
                    ->orWhereHas('tasks')
                    ->orWhereExists(fn ($sq) => $sq->selectRaw('1')->from('tasks')
                        ->whereColumn('tasks.created_by', 'users.id')
                        ->whereNull('tasks.deleted_at'))
                    ->orWhereIn('users.id', $teamIds ?: [0]);
            })
            ->when($q !== null && $q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('users.name', 'like', "%{$q}%")
                        ->orWhere('users.email', 'like', "%{$q}%")
                        ->orWhere('users.employee_id_no', 'like', "%{$q}%")
                        ->orWhere('users.phone', 'like', "%{$q}%")
                        ->orWhereHas('company', fn ($c) => $c
                            ->where('name', 'like', "%{$q}%")
                            ->orWhere('short_name', 'like', "%{$q}%"))
                        ->orWhereHas('profile.department', fn ($d) => $d->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('profile.designation', fn ($d) => $d->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('roles', fn ($r) => $r->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderBy('users.name')
            ->get();
    }

    /**
     * The same people, in company order, for the board's switcher. Anyone
     * without a company lands in a bucket of their own at the end rather than
     * vanishing.
     */
    private function peopleByCompany()
    {
        return $this->boardablePeople()
            ->groupBy(fn ($p) => $p->company->short_name ?? $p->company->name ?? 'No company')
            ->sortKeys();
    }

    /**
     * Task and verdict totals for a set of people, in two queries rather than
     * two per row. Assigned tasks only — "their board" means the work that is
     * on them, which is the pivot.
     */
    private function statsFor(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        try {
            $rows = DB::table('task_user')
                ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
                ->whereIn('task_user.user_id', $userIds)
                ->whereNull('tasks.deleted_at')
                ->whereNull('tasks.parent_id')
                ->groupBy('task_user.user_id')
                ->select(
                    'task_user.user_id',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN tasks.completed_at IS NOT NULL THEN 1 ELSE 0 END) as done'),
                    DB::raw('SUM(CASE WHEN tasks.due_date IS NOT NULL AND tasks.due_date < NOW() AND tasks.completed_at IS NULL THEN 1 ELSE 0 END) as overdue'),
                )
                ->get()
                ->keyBy('user_id');

            $verdicts = collect();

            if (self::hasOversightTable()) {
                $verdicts = DB::table('task_oversight')
                    ->join('tasks', 'tasks.id', '=', 'task_oversight.task_id')
                    ->join('task_user', 'task_user.task_id', '=', 'tasks.id')
                    ->whereIn('task_user.user_id', $userIds)
                    ->whereNull('tasks.deleted_at')
                    ->groupBy('task_user.user_id')
                    ->select(
                        'task_user.user_id',
                        DB::raw('SUM(task_oversight.red_flag) as flags'),
                        DB::raw('SUM(task_oversight.is_restricted) as locks'),
                        DB::raw('AVG(task_oversight.rating) as avg_rating'),
                        DB::raw('COUNT(task_oversight.rating) as rated'),
                    )
                    ->get()
                    ->keyBy('user_id');
            }

            $out = [];

            foreach ($userIds as $id) {
                $t = $rows[$id] ?? null;
                $v = $verdicts[$id] ?? null;

                $out[$id] = (object) [
                    'total'      => (int) ($t->total ?? 0),
                    'done'       => (int) ($t->done ?? 0),
                    'overdue'    => (int) ($t->overdue ?? 0),
                    'flags'      => (int) ($v->flags ?? 0),
                    'locks'      => (int) ($v->locks ?? 0),
                    'rated'      => (int) ($v->rated ?? 0),
                    'avg_rating' => $v && $v->avg_rating !== null ? round((float) $v->avg_rating, 1) : null,
                ];
            }

            return $out;
        } catch (Throwable $e) {
            // Decoration only — a broken aggregate must not cost the picker.
            return [];
        }
    }

    /* ── The board ──────────────────────────────────────────────────────── */

    /**
     * No board named: open the one the employee themselves would land on —
     * the board of their most recent task, falling back to any board their
     * projects put them on.
     */
    public function openEmployee($role, User $employee)
    {
        $this->guard();

        $boardId = Task::whereHas('users', fn ($q) => $q->where('users.id', $employee->id))
            ->whereNotNull('board_id')
            ->latest('created_at')
            ->value('board_id');

        $board = $boardId ? Board::find($boardId) : $this->boardsFor($employee)->first();

        if (!$board) {
            return redirect()
                ->route('role.task-oversight.index', ['role' => $role])
                ->with('error', $employee->name . ' has no task board yet.');
        }

        return redirect()->route('role.task-oversight.board', [
            'role'     => $role,
            'employee' => $employee->id,
            'board'    => $board->id,
        ]);
    }

    /**
     * The employee's board, rendered by the employee's own view.
     *
     * Every variable here is the one TaskController::index() would have built
     * had the employee loaded this page themselves — the task filter especially.
     * $oversightFor is the single addition, and it is what the blade branches on.
     */
    public function board($role, User $employee, Board $board)
    {
        $this->guard();

        $board = Board::with([
            'board_columns.column.tasks' => function ($q) use ($board, $employee) {
                $q->where('board_id', $board->id)
                    ->whereNull('parent_id')
                    ->with($this->cardRelations())
                    ->withCount([
                        'subtasks' => fn ($sq) => $sq->reorder(),
                        'subtasks as done_subtasks_count' => fn ($sq) => $sq->reorder()->whereNotNull('completed_at'),
                        'comments',
                    ])
                    // The footer's clock chip, exactly as TaskController::index()
                    // builds it — the oversight board is the employee's board and
                    // a card that counted time there must count it here.
                    ->when(Schema::hasColumn('tasks', 'timer_started_at'), fn ($sq) => $sq
                        ->withSum(['subtasks as sub_accum_ms' => fn ($x) => $x->reorder()], 'timer_accum_ms')
                        ->withMax(
                            ['subtasks as sub_running_since' => fn ($x) => $x->reorder()->whereNotNull('timer_started_at')],
                            'timer_started_at'
                        ))
                    // Verbatim the filter TaskController::index() applies for an
                    // employee: assigned to them, created by them, or theirs by
                    // inheritance through a sub-work item. Change one and change
                    // the other, or the two boards stop matching.
                    ->where(function ($query) use ($employee) {
                        $query->whereHas('users', fn ($q2) => $q2->where('users.id', $employee->id))
                            ->orWhere('created_by', $employee->id)
                            ->orWhereHas('subtasks', function ($q3) use ($employee) {
                                $q3->where(function ($q4) use ($employee) {
                                    $q4->whereHas('users', fn ($q5) => $q5->where('users.id', $employee->id))
                                        ->orWhere('created_by', $employee->id);
                                });
                            });
                    });
            },
        ])->findOrFail($board->id);

        $project       = Project::find($board->project_id);
        $teamMemberIds = $project->team_members ?? [];

        // The switcher lists the EMPLOYEE's boards, so moving between them is
        // the same walk they would take — that is the "navigate like the
        // employee" part of the brief.
        $boards = $this->boardsFor($employee);

        $users  = User::whereIn('id', $teamMemberIds)->get();
        $labels = Label::where('project_id', $board->project_id)->orderBy('name')->get();

        // Their ribbons, not mine: this is their board, so it shows what they
        // have not read yet. Opening a task here stamps it seen for me and
        // leaves their ribbon exactly where it was.
        $adminAlerts = app(TaskController::class)->adminAlerts($board, $employee);

        return view('task-module.task.show', [
            'board'            => $board,
            'users'            => $users,
            'labels'           => $labels,
            'boards'           => $boards,
            'adminAlerts'      => $adminAlerts,
            'oversightFor'     => $employee,
            // The person-switcher in the oversight bar: jump straight from one
            // employee's board to the next without going back to the picker,
            // grouped by company and searchable by anything a person is known
            // by. Only people who can actually have a board.
            'oversightGroups'  => $this->peopleByCompany(),
        ]);
    }

    /**
     * Relations the card markup touches. `oversight` rides along so the lock
     * and flag can paint without a query per card — but only when the table is
     * actually there.
     */
    private function cardRelations(): array
    {
        $with = ['users', 'labels', 'creator', 'column'];

        if (self::hasOversightTable()) {
            $with[] = 'oversight';
        }

        return $with;
    }

    /**
     * Boards the employee can reach — their projects' boards, newest first,
     * the same set their own board switcher shows them.
     */
    private function boardsFor(User $employee)
    {
        return Board::with('workspace', 'project', 'user', 'columns')
            ->whereHas('project', fn ($q) => $q->whereJsonContains('team_members', (string) $employee->id))
            ->orderBy('id', 'desc')
            ->get();
    }

    /* ── The verdict ────────────────────────────────────────────────────── */

    /**
     * Read one task's verdict. Open to any signed-in user, deliberately: a lock
     * the employee cannot see is a lock that only produces a baffling error, so
     * the drawer shows everybody the state and shows only the super admin the
     * controls.
     */
    public function verdict($role, Task $task)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false], 401);
        }

        if (!self::hasOversightTable()) {
            return response()->json(['success' => true, 'verdict' => null, 'can_manage' => false]);
        }

        $row = TaskOversight::with(['restrictedBy:id,name', 'redFlaggedBy:id,name', 'ratedBy:id,name'])
            ->where('task_id', $task->id)
            ->first();

        return response()->json([
            'success'    => true,
            'can_manage' => (bool) ($user->is_super_admin || $user->hasRole('super admin')),
            'verdict'    => $row ? $this->present($row) : null,
        ]);
    }

    /**
     * Write it. Partial by design — the drawer sends only the control that was
     * touched, so saving a rating cannot silently clear a lock.
     */
    public function saveVerdict(Request $request, $role, Task $task)
    {
        $this->guard();

        if (!self::hasOversightTable()) {
            return response()->json([
                'success' => false,
                'message' => 'Task oversight is not installed on this server yet.',
            ], 503);
        }

        $data = $request->validate([
            'is_restricted'    => 'sometimes|boolean',
            'restriction_note' => 'nullable|string|max:500',
            'red_flag'         => 'sometimes|boolean',
            'red_flag_note'    => 'nullable|string|max:500',
            'rating'           => 'sometimes|nullable|integer|min:1|max:5',
            'rating_note'      => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $row  = TaskOversight::firstOrNew(['task_id' => $task->id]);

        if ($request->has('is_restricted')) {
            $on = (bool) $data['is_restricted'];
            // Stamp only on a real change, so re-saving a note does not rewrite
            // who locked it and when.
            if ($on !== (bool) $row->is_restricted) {
                $row->restricted_by = $on ? $user->id : null;
                $row->restricted_at = $on ? now() : null;
            }
            $row->is_restricted    = $on;
            $row->restriction_note = $on ? ($data['restriction_note'] ?? $row->restriction_note) : null;
        }

        if ($request->has('red_flag')) {
            $on = (bool) $data['red_flag'];
            if ($on !== (bool) $row->red_flag) {
                $row->red_flagged_by = $on ? $user->id : null;
                $row->red_flagged_at = $on ? now() : null;
            }
            $row->red_flag      = $on;
            $row->red_flag_note = $on ? ($data['red_flag_note'] ?? $row->red_flag_note) : null;
        }

        if ($request->has('rating')) {
            $rating = $data['rating'] ?? null;
            $row->rating      = $rating;
            $row->rating_note = $rating === null ? null : ($data['rating_note'] ?? $row->rating_note);
            $row->rated_by    = $rating === null ? null : $user->id;
            $row->rated_at    = $rating === null ? null : now();
        } elseif ($request->has('rating_note') && $row->rating !== null) {
            // A note edited on its own, without touching the stars.
            $row->rating_note = $data['rating_note'];
        }

        // Nothing left to say — drop the row rather than keep a blank verdict,
        // so "has a row" stays a meaningful signal on the board.
        if ($row->isEmpty()) {
            if ($row->exists) {
                $row->delete();
            }

            $this->log($task, 'oversight_cleared', 'cleared the oversight marks');

            return response()->json(['success' => true, 'verdict' => null]);
        }

        $row->save();

        $this->log($task, 'oversight_updated', $this->describe($row));

        $row->load(['restrictedBy:id,name', 'redFlaggedBy:id,name', 'ratedBy:id,name']);

        return response()->json(['success' => true, 'verdict' => $this->present($row)]);
    }

    /**
     * One shape for the browser, used by both read and write so the drawer has
     * exactly one thing to render.
     */
    private function present(TaskOversight $row): array
    {
        return [
            'is_restricted'    => (bool) $row->is_restricted,
            'restriction_note' => $row->restriction_note,
            'restricted_by'    => $row->restrictedBy->name ?? null,
            'restricted_at'    => optional($row->restricted_at)->format('d/m/y H:i'),
            'red_flag'         => (bool) $row->red_flag,
            'red_flag_note'    => $row->red_flag_note,
            'red_flagged_by'   => $row->redFlaggedBy->name ?? null,
            'red_flagged_at'   => optional($row->red_flagged_at)->format('d/m/y H:i'),
            'rating'           => $row->rating,
            'rating_note'      => $row->rating_note,
            'rated_by'         => $row->ratedBy->name ?? null,
            'rated_at'         => optional($row->rated_at)->format('d/m/y H:i'),
        ];
    }

    private function describe(TaskOversight $row): string
    {
        $bits = [];

        if ($row->is_restricted) {
            $bits[] = 'restricted it';
        }

        if ($row->red_flag) {
            $bits[] = 'red-flagged it';
        }

        if ($row->rating !== null) {
            $bits[] = 'rated it ' . $row->rating . '/5';
        }

        return $bits ? implode(', ', $bits) : 'updated the oversight marks';
    }

    /**
     * Activity log entry, best effort. A verdict that saved must not report
     * failure because its footnote could not be written.
     */
    private function log(Task $task, string $type, string $text): void
    {
        try {
            \App\Models\TaskActivityLog::create([
                'task_id'     => $task->id,
                'user_id'     => Auth::id(),
                'activity_type' => $type,
                'description' => $text,
            ]);
        } catch (Throwable $e) {
            // nothing — the verdict is saved, the footnote is not worth a 500
        }
    }
}
