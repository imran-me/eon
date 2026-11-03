<?php

namespace Modules\EON\Intelligence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\EON\Intelligence\Support\Period;
use Throwable;

/**
 * EON's data layer: every number the engine reasons about is read here, and
 * nowhere else. The analyzers do arithmetic on what this class returns and
 * never touch the database themselves — which is what makes them testable and
 * makes "where did that figure come from?" a question with one answer.
 *
 * READ-ONLY, ALWAYS. This class issues SELECTs against shared tables (tasks,
 * task_user, task_activity_logs, task_oversight, columns) and writes nothing,
 * anywhere. EON's own tables are written by EonController alone.
 *
 * BULK BY DESIGN. The org dashboard scores ~40 people at once. Every method
 * here takes a LIST of user ids and answers for all of them in one query
 * grouped by user, because the per-person alternative is forty round trips per
 * panel. Per-person detail (the rated task list, the overdue list) is separate
 * and only ever runs on one dossier.
 *
 * THE DEFINITIONS, stated once so the pages can quote them:
 *
 *  • A person's tasks are the tasks ASSIGNED to them — the task_user pivot —
 *    excluding soft-deleted rows and sub-work items (parent_id). That is the
 *    same scope the Employee Task Oversight picker counts, so the two screens
 *    can never disagree about how many tasks somebody has.
 *
 *  • DONE means completed_at is set. CANCELLED, TO DO and PROGRESS are read
 *    from the column's name, because that is where this ERP keeps state; the
 *    five columns are shared across boards.
 *
 *  • ON TIME means finished on or before the due DATE. Due dates in this
 *    install carry a time of day (16:10, 07:16) that in practice reads as "some
 *    time that day", and 3,096 tasks were finished on the right day but after
 *    the stated hour. Judging those late would brand four fifths of the
 *    workforce late and the number would stop meaning anything. The stricter
 *    reading is still counted, as late_by_hour, and reported separately.
 */
final class TaskMetrics
{
    private CarbonImmutable $now;

    /** Cached per instance — asked once per page, not once per user. */
    private ?bool $hasOversight = null;

    public function __construct(?CarbonImmutable $now = null)
    {
        $this->now = $now ?: CarbonImmutable::now();
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function hasOversightTable(): bool
    {
        if ($this->hasOversight === null) {
            try {
                $this->hasOversight = Schema::hasTable('task_oversight');
            } catch (Throwable $e) {
                $this->hasOversight = false;
            }
        }

        return $this->hasOversight;
    }

    /* ── Series ─────────────────────────────────────────────────────────── */

    /**
     * Month-by-month history for a set of people: what landed on them, what
     * they finished, how much of it was on time, and how long it took.
     *
     * @return array<int, array<string, array<string, float|int|null>>>
     *         [userId][YYYY-MM] => ['created','completed','on_time','late','with_due','late_by_hour','cycle_days','late_days']
     */
    public function monthlySeries(array $userIds, int $months = 12): array
    {
        $skeleton = [];

        foreach (array_keys(Period::months($months, $this->now)) as $key) {
            $skeleton[$key] = [
                'created'      => 0,
                'completed'    => 0,
                'on_time'      => 0,
                'late'         => 0,
                'with_due'     => 0,
                'late_by_hour' => 0,
                'cycle_days'   => null,
                'late_days'    => null,
            ];
        }

        $out = [];

        foreach ($userIds as $id) {
            $out[(int) $id] = $skeleton;
        }

        if (!$userIds) {
            return $out;
        }

        $from = $this->now->startOfMonth()->subMonths($months - 1);

        try {
            $created = $this->assignedScope($userIds)
                ->where('tasks.created_at', '>=', $from)
                ->groupBy('task_user.user_id', 'ym')
                ->select(
                    'task_user.user_id',
                    DB::raw("DATE_FORMAT(tasks.created_at, '%Y-%m') as ym"),
                    DB::raw('COUNT(*) as c')
                )
                ->get();

            foreach ($created as $row) {
                if (isset($out[(int) $row->user_id][$row->ym])) {
                    $out[(int) $row->user_id][$row->ym]['created'] = (int) $row->c;
                }
            }

            $completed = $this->assignedScope($userIds)
                ->whereNotNull('tasks.completed_at')
                ->where('tasks.completed_at', '>=', $from)
                ->groupBy('task_user.user_id', 'ym')
                ->select(
                    'task_user.user_id',
                    DB::raw("DATE_FORMAT(tasks.completed_at, '%Y-%m') as ym"),
                    DB::raw('COUNT(*) as c'),
                    DB::raw('SUM(tasks.due_date IS NOT NULL) as with_due'),
                    DB::raw('SUM(tasks.due_date IS NOT NULL AND DATE(tasks.completed_at) <= DATE(tasks.due_date)) as on_time'),
                    DB::raw('SUM(tasks.due_date IS NOT NULL AND DATE(tasks.completed_at) >  DATE(tasks.due_date)) as late'),
                    DB::raw('SUM(tasks.due_date IS NOT NULL AND DATE(tasks.completed_at) = DATE(tasks.due_date) AND tasks.completed_at > tasks.due_date) as late_by_hour'),
                    DB::raw('AVG(TIMESTAMPDIFF(HOUR, tasks.created_at, tasks.completed_at)) as cycle_hours'),
                    DB::raw('AVG(CASE WHEN tasks.due_date IS NOT NULL AND DATE(tasks.completed_at) > DATE(tasks.due_date)
                                      THEN TIMESTAMPDIFF(HOUR, tasks.due_date, tasks.completed_at) END) as late_hours')
                )
                ->get();

            foreach ($completed as $row) {
                $uid = (int) $row->user_id;

                if (!isset($out[$uid][$row->ym])) {
                    continue;
                }

                $out[$uid][$row->ym] = [
                    'created'      => $out[$uid][$row->ym]['created'],
                    'completed'    => (int) $row->c,
                    'on_time'      => (int) $row->on_time,
                    'late'         => (int) $row->late,
                    'with_due'     => (int) $row->with_due,
                    'late_by_hour' => (int) $row->late_by_hour,
                    'cycle_days'   => $row->cycle_hours === null ? null : round(((float) $row->cycle_hours) / 24, 2),
                    'late_days'    => $row->late_hours === null ? null : round(((float) $row->late_hours) / 24, 2),
                ];
            }
        } catch (Throwable $e) {
            // A failed aggregate costs the trend, not the page. The skeleton of
            // zeroes is a truthful "nothing known", and every analyzer treats a
            // flat empty series as "not enough evidence".
        }

        return $out;
    }

    /**
     * Everything about one window of time, per person — the shape both
     * "this month so far" and "the same days last month" are read with, so the
     * two are guaranteed comparable.
     *
     * @return array<int, object>
     */
    public function window(array $userIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $blank = (object) [
            'created'      => 0,
            'completed'    => 0,
            'on_time'      => 0,
            'late'         => 0,
            'with_due'     => 0,
            'late_by_hour' => 0,
            'cycle_days'   => null,
            'activity'     => 0,
            'active_days'  => 0,
            // Load & scope (2026-10-07): how widely the finished work is spread,
            // and the work this person handed to others that got done.
            'boards'       => 0,
            'workspaces'   => 0,
            'companies'    => 0,
            'delegated'    => 0,
        ];

        $out = [];

        foreach ($userIds as $id) {
            $out[(int) $id] = clone $blank;
        }

        if (!$userIds) {
            return $out;
        }

        try {
            $created = $this->assignedScope($userIds)
                ->whereBetween('tasks.created_at', [$start, $end])
                ->groupBy('task_user.user_id')
                ->select('task_user.user_id', DB::raw('COUNT(*) as c'))
                ->get();

            foreach ($created as $row) {
                $out[(int) $row->user_id]->created = (int) $row->c;
            }

            $done = $this->assignedScope($userIds)
                ->whereNotNull('tasks.completed_at')
                ->whereBetween('tasks.completed_at', [$start, $end])
                ->groupBy('task_user.user_id')
                ->select(
                    'task_user.user_id',
                    DB::raw('COUNT(*) as c'),
                    DB::raw('SUM(tasks.due_date IS NOT NULL) as with_due'),
                    DB::raw('SUM(tasks.due_date IS NOT NULL AND DATE(tasks.completed_at) <= DATE(tasks.due_date)) as on_time'),
                    DB::raw('SUM(tasks.due_date IS NOT NULL AND DATE(tasks.completed_at) >  DATE(tasks.due_date)) as late'),
                    DB::raw('SUM(tasks.due_date IS NOT NULL AND DATE(tasks.completed_at) = DATE(tasks.due_date) AND tasks.completed_at > tasks.due_date) as late_by_hour'),
                    DB::raw('AVG(TIMESTAMPDIFF(HOUR, tasks.created_at, tasks.completed_at)) as cycle_hours')
                )
                ->get();

            foreach ($done as $row) {
                $uid = (int) $row->user_id;

                $out[$uid]->completed    = (int) $row->c;
                $out[$uid]->with_due     = (int) $row->with_due;
                $out[$uid]->on_time      = (int) $row->on_time;
                $out[$uid]->late         = (int) $row->late;
                $out[$uid]->late_by_hour = (int) $row->late_by_hour;
                $out[$uid]->cycle_days   = $row->cycle_hours === null ? null : round(((float) $row->cycle_hours) / 24, 2);
            }

            // Scope: distinct boards / workspaces / companies their finished work came from.
            // Its own try, so a failure here costs only the Load pillar.
            try {
                $spread = $this->assignedScope($userIds)
                    ->whereNotNull('tasks.completed_at')
                    ->whereBetween('tasks.completed_at', [$start, $end])
                    ->groupBy('task_user.user_id')
                    ->select(
                        'task_user.user_id',
                        DB::raw('COUNT(DISTINCT tasks.board_id) as boards'),
                        DB::raw('COUNT(DISTINCT tasks.workspace_id) as workspaces'),
                        DB::raw('COUNT(DISTINCT tasks.company_id) as companies')
                    )
                    ->get();

                foreach ($spread as $row) {
                    $uid = (int) $row->user_id;
                    $out[$uid]->boards     = (int) $row->boards;
                    $out[$uid]->workspaces = (int) $row->workspaces;
                    $out[$uid]->companies  = (int) $row->companies;
                }

                // Delegated: tasks they created, assigned to somebody else, finished in the window.
                $handed = DB::table('tasks')
                    ->join('task_user', 'task_user.task_id', '=', 'tasks.id')
                    ->whereIn('tasks.created_by', $userIds)
                    ->whereColumn('task_user.user_id', '<>', 'tasks.created_by')
                    ->whereNull('tasks.deleted_at')
                    ->whereNull('tasks.parent_id')
                    ->whereNotNull('tasks.completed_at')
                    ->whereBetween('tasks.completed_at', [$start, $end])
                    ->groupBy('tasks.created_by')
                    ->select('tasks.created_by', DB::raw('COUNT(DISTINCT tasks.id) as c'))
                    ->get();

                foreach ($handed as $row) {
                    if (isset($out[(int) $row->created_by])) {
                        $out[(int) $row->created_by]->delegated = (int) $row->c;
                    }
                }
            } catch (Throwable $e) {
                // blanks — the Load pillar then reads "not known"
            }

            $acts = DB::table('task_activity_logs')
                ->whereIn('user_id', $userIds)
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('user_id')
                ->select(
                    'user_id',
                    DB::raw('COUNT(*) as c'),
                    DB::raw('COUNT(DISTINCT DATE(created_at)) as days')
                )
                ->get();

            foreach ($acts as $row) {
                $uid = (int) $row->user_id;

                if (!isset($out[$uid])) {
                    continue;
                }

                $out[$uid]->activity    = (int) $row->c;
                $out[$uid]->active_days = (int) $row->days;
            }
        } catch (Throwable $e) {
            // As above — blanks, not a broken page.
        }

        return $out;
    }

    /* ── Standing state ─────────────────────────────────────────────────── */

    /**
     * Where each person stands right now: the backlog, its age, and how much of
     * it is past due. Point-in-time, not a window — this is the part of an
     * evaluation that a good month cannot paper over.
     *
     * @return array<int, object>
     */
    public function standing(array $userIds): array
    {
        $blank = (object) [
            'total'            => 0,
            'done'             => 0,
            'open'             => 0,
            'todo'             => 0,
            'in_progress'      => 0,
            'cancelled'        => 0,
            'unmarked_done'    => 0,
            'unmarked_overdue' => 0,
            'overdue'          => 0,
            'overdue_week'     => 0,
            'overdue_month'    => 0,
            'overdue_older'    => 0,
            'oldest_overdue'   => null,
            'open_age_days'    => null,
            'first_task_at'    => null,
            'last_completed_at' => null,
        ];

        $out = [];

        foreach ($userIds as $id) {
            $out[(int) $id] = clone $blank;
        }

        if (!$userIds) {
            return $out;
        }

        try {
            /* THE DISTINCTION THIS QUERY EXISTS TO DRAW.
             *
             * 394 tasks in this install sit in the DONE column with completed_at
             * still NULL — somebody dragged the card and the timestamp was never
             * written. They are 365 of the company's 417 "past due" items.
             *
             * Counted naively, they are overdue work, and the Timeliness pillar
             * would mark most of the workforce down for a recording gap rather
             * than for late delivery. Counted as complete, EON would quietly
             * adopt a definition of "done" that no other screen in the ERP uses,
             * and its totals would stop matching the oversight picker's.
             *
             * So they are counted SEPARATELY: `unmarked_done` is reported and
             * raised as its own finding, and the overdue figures cover only work
             * that is genuinely still open. Nothing is hidden and nothing is
             * scored twice.
             */
            $isCancelled = "UPPER(COALESCE(columns.name, '')) = 'CANCELLED'";
            $isDoneCol   = "UPPER(COALESCE(columns.name, '')) = 'DONE'";

            // Sitting in DONE, never stamped complete.
            $unmarked = "(tasks.completed_at IS NULL AND {$isDoneCol})";

            // Genuinely still open: not complete, not cancelled, not parked in DONE.
            $live = "(tasks.completed_at IS NULL AND NOT ({$isCancelled}) AND NOT ({$isDoneCol}))";

            $isOverdue = "({$live} AND tasks.due_date IS NOT NULL AND tasks.due_date < NOW())";

            $rows = $this->assignedScope($userIds)
                ->leftJoin('columns', 'columns.id', '=', 'tasks.column_id')
                ->groupBy('task_user.user_id')
                ->select(
                    'task_user.user_id',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(tasks.completed_at IS NOT NULL) as done'),
                    DB::raw("SUM({$live}) as open"),
                    DB::raw("SUM(tasks.completed_at IS NULL AND UPPER(COALESCE(columns.name,'')) = 'TO DO') as todo"),
                    DB::raw("SUM(tasks.completed_at IS NULL AND UPPER(COALESCE(columns.name,'')) = 'PROGRESS') as in_progress"),
                    DB::raw("SUM({$isCancelled}) as cancelled"),
                    DB::raw("SUM({$unmarked}) as unmarked_done"),
                    DB::raw("SUM({$unmarked} AND tasks.due_date IS NOT NULL AND tasks.due_date < NOW()) as unmarked_overdue"),
                    DB::raw("SUM({$isOverdue}) as overdue"),
                    DB::raw("SUM({$isOverdue} AND tasks.due_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as overdue_week"),
                    DB::raw("SUM({$isOverdue} AND tasks.due_date <  DATE_SUB(NOW(), INTERVAL 7 DAY) AND tasks.due_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as overdue_month"),
                    DB::raw("SUM({$isOverdue} AND tasks.due_date <  DATE_SUB(NOW(), INTERVAL 30 DAY)) as overdue_older"),
                    DB::raw("MIN(CASE WHEN {$isOverdue} THEN tasks.due_date END) as oldest_overdue"),
                    DB::raw("AVG(CASE WHEN {$live} THEN TIMESTAMPDIFF(HOUR, tasks.created_at, NOW()) END) as open_age_hours"),
                    DB::raw('MIN(tasks.created_at) as first_task_at'),
                    DB::raw('MAX(tasks.completed_at) as last_completed_at')
                )
                ->get();

            foreach ($rows as $row) {
                $uid = (int) $row->user_id;

                $out[$uid] = (object) [
                    'total'             => (int) $row->total,
                    'done'              => (int) $row->done,
                    'open'              => (int) $row->open,
                    'todo'              => (int) $row->todo,
                    'in_progress'       => (int) $row->in_progress,
                    'cancelled'         => (int) $row->cancelled,
                    'unmarked_done'     => (int) $row->unmarked_done,
                    'unmarked_overdue'  => (int) $row->unmarked_overdue,
                    'overdue'           => (int) $row->overdue,
                    'overdue_week'      => (int) $row->overdue_week,
                    'overdue_month'     => (int) $row->overdue_month,
                    'overdue_older'     => (int) $row->overdue_older,
                    'oldest_overdue'    => $row->oldest_overdue
                        ? (int) CarbonImmutable::parse($row->oldest_overdue)->diffInDays($this->now)
                        : null,
                    'open_age_days'     => $row->open_age_hours === null ? null : round(((float) $row->open_age_hours) / 24, 1),
                    'first_task_at'     => $row->first_task_at ? CarbonImmutable::parse($row->first_task_at) : null,
                    'last_completed_at' => $row->last_completed_at ? CarbonImmutable::parse($row->last_completed_at) : null,
                ];
            }
        } catch (Throwable $e) {
            // blanks
        }

        return $out;
    }

    /**
     * The human verdicts already on record — the manual stars, the red flags,
     * the locks — summarised per person.
     *
     * Behind hasOversightTable() at every step: deploys land before migrations
     * run, and EON asking for a table that is not there yet must cost the panel,
     * not the page. Same discipline as EmployeeTaskOversightController.
     *
     * @return array<int, object>
     */
    public function verdicts(array $userIds): array
    {
        $blank = (object) [
            'flags'        => 0,
            'locks'        => 0,
            'rated'        => 0,
            'avg_rating'   => null,
            'low_ratings'  => 0,
            'high_ratings' => 0,
            'last_rated_at' => null,
            'recent_avg'   => null,
            'earlier_avg'  => null,
        ];

        $out = [];

        foreach ($userIds as $id) {
            $out[(int) $id] = clone $blank;
        }

        if (!$userIds || !$this->hasOversightTable()) {
            return $out;
        }

        try {
            /* Written into the SQL rather than bound. It is a timestamp this
               class derived from now() — never user input — and a bound
               parameter inside a SELECT list has to be positioned by hand
               against Laravel's select/join/where binding order. One less
               thing to get subtly wrong on a query nobody will read again. */
            $cut = $this->now->subDays(90)->format('Y-m-d H:i:s');

            $rows = $this->assignedScope($userIds)
                ->join('task_oversight', 'task_oversight.task_id', '=', 'tasks.id')
                ->groupBy('task_user.user_id')
                ->select(
                    'task_user.user_id',
                    DB::raw('SUM(task_oversight.red_flag) as flags'),
                    DB::raw('SUM(task_oversight.is_restricted) as locks'),
                    DB::raw('COUNT(task_oversight.rating) as rated'),
                    DB::raw('AVG(task_oversight.rating) as avg_rating'),
                    DB::raw('SUM(task_oversight.rating <= 2) as low_ratings'),
                    DB::raw('SUM(task_oversight.rating >= 4) as high_ratings'),
                    DB::raw('MAX(task_oversight.rated_at) as last_rated_at'),
                    DB::raw("AVG(CASE WHEN task_oversight.rated_at >= '{$cut}' THEN task_oversight.rating END) as recent_avg"),
                    DB::raw("AVG(CASE WHEN task_oversight.rated_at <  '{$cut}' THEN task_oversight.rating END) as earlier_avg")
                )
                ->get();

            foreach ($rows as $row) {
                $uid = (int) $row->user_id;

                $out[$uid] = (object) [
                    'flags'         => (int) $row->flags,
                    'locks'         => (int) $row->locks,
                    'rated'         => (int) $row->rated,
                    'avg_rating'    => $row->avg_rating === null ? null : round((float) $row->avg_rating, 2),
                    'low_ratings'   => (int) $row->low_ratings,
                    'high_ratings'  => (int) $row->high_ratings,
                    'last_rated_at' => $row->last_rated_at ? CarbonImmutable::parse($row->last_rated_at) : null,
                    'recent_avg'    => $row->recent_avg === null ? null : round((float) $row->recent_avg, 2),
                    'earlier_avg'   => $row->earlier_avg === null ? null : round((float) $row->earlier_avg, 2),
                ];
            }
        } catch (Throwable $e) {
            // blanks
        }

        return $out;
    }

    /** When each person last did anything at all in the task module. */
    public function lastSeenWorking(array $userIds): array
    {
        $out = [];

        if (!$userIds) {
            return $out;
        }

        try {
            $rows = DB::table('task_activity_logs')
                ->whereIn('user_id', $userIds)
                ->groupBy('user_id')
                ->select('user_id', DB::raw('MAX(created_at) as at'))
                ->get();

            foreach ($rows as $row) {
                $out[(int) $row->user_id] = $row->at ? CarbonImmutable::parse($row->at) : null;
            }
        } catch (Throwable $e) {
            // nothing
        }

        return $out;
    }

    /* ── One person, in detail ──────────────────────────────────────────── */

    /** Completions per week for the cadence strip. */
    public function weeklyCompletions(int $userId, int $weeks = 14): array
    {
        $buckets = [];

        foreach (Period::weeks($weeks, $this->now) as $key => $week) {
            $buckets[$key] = ['label' => $week['label'], 'count' => 0];
        }

        try {
            $from = $this->now->startOfWeek()->subWeeks($weeks - 1);

            $rows = $this->assignedScope([$userId])
                ->whereNotNull('tasks.completed_at')
                ->where('tasks.completed_at', '>=', $from)
                ->groupBy('wk')
                ->select(
                    DB::raw('DATE(DATE_SUB(tasks.completed_at, INTERVAL WEEKDAY(tasks.completed_at) DAY)) as wk'),
                    DB::raw('COUNT(*) as c')
                )
                ->get();

            foreach ($rows as $row) {
                if (isset($buckets[$row->wk])) {
                    $buckets[$row->wk]['count'] = (int) $row->c;
                }
            }
        } catch (Throwable $e) {
            // flat strip
        }

        return $buckets;
    }

    /**
     * The open work that is past due, oldest first — the "act on this" list.
     *
     * Matches standing()'s definition exactly, DONE-column exclusion included:
     * a list that showed rows the counter above it had excluded would be worse
     * than no list, because somebody would try to reconcile the two.
     */
    public function overdueTasks(int $userId, int $limit = 12): array
    {
        try {
            return $this->assignedScope([$userId])
                ->leftJoin('columns', 'columns.id', '=', 'tasks.column_id')
                ->whereNull('tasks.completed_at')
                ->whereNotNull('tasks.due_date')
                ->where('tasks.due_date', '<', $this->now)
                ->whereRaw("UPPER(COALESCE(columns.name,'')) NOT IN ('CANCELLED', 'DONE')")
                ->orderBy('tasks.due_date')
                ->limit($limit)
                ->select(
                    'tasks.id',
                    'tasks.title',
                    'tasks.priority',
                    'tasks.due_date',
                    'tasks.board_id',
                    'columns.name as column_name'
                )
                ->get()
                ->map(function ($t) {
                    $t->days_late = (int) CarbonImmutable::parse($t->due_date)->diffInDays($this->now);

                    return $t;
                })
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Cards parked in the DONE column that were never stamped complete —
     * the recording gap, listed so it can be cleared rather than argued about.
     */
    public function unmarkedDoneTasks(int $userId, int $limit = 12): array
    {
        try {
            return $this->assignedScope([$userId])
                ->leftJoin('columns', 'columns.id', '=', 'tasks.column_id')
                ->whereNull('tasks.completed_at')
                ->whereRaw("UPPER(COALESCE(columns.name,'')) = 'DONE'")
                ->orderBy('tasks.due_date')
                ->limit($limit)
                ->select('tasks.id', 'tasks.title', 'tasks.due_date', 'tasks.updated_at')
                ->get()
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Tasks somebody has already judged: the stars, the flags, the locks, with
     * their notes. This is the human half of the evaluation and the pages show
     * it beside the machine half, never merged into it.
     */
    public function judgedTasks(int $userId, int $limit = 40): array
    {
        if (!$this->hasOversightTable()) {
            return [];
        }

        try {
            return $this->assignedScope([$userId])
                ->join('task_oversight', 'task_oversight.task_id', '=', 'tasks.id')
                ->leftJoin('users as rater', 'rater.id', '=', 'task_oversight.rated_by')
                ->leftJoin('users as flagger', 'flagger.id', '=', 'task_oversight.red_flagged_by')
                ->leftJoin('users as locker', 'locker.id', '=', 'task_oversight.restricted_by')
                ->orderByRaw('COALESCE(task_oversight.rated_at, task_oversight.red_flagged_at, task_oversight.restricted_at, task_oversight.updated_at) DESC')
                ->limit($limit)
                ->select(
                    'tasks.id',
                    'tasks.title',
                    'tasks.board_id',
                    'tasks.completed_at',
                    'tasks.due_date',
                    'task_oversight.rating',
                    'task_oversight.rating_note',
                    'task_oversight.rated_at',
                    'task_oversight.red_flag',
                    'task_oversight.red_flag_note',
                    'task_oversight.red_flagged_at',
                    'task_oversight.is_restricted',
                    'task_oversight.restriction_note',
                    'task_oversight.restricted_at',
                    'rater.name as rated_by_name',
                    'flagger.name as flagged_by_name',
                    'locker.name as locked_by_name'
                )
                ->get()
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * The rating history as a month series, so the manual stars get the same
     * trend treatment as everything else.
     */
    public function ratingSeries(int $userId, int $months = 12): array
    {
        $out = [];

        foreach (array_keys(Period::months($months, $this->now)) as $key) {
            $out[$key] = ['count' => 0, 'avg' => null];
        }

        if (!$this->hasOversightTable()) {
            return $out;
        }

        try {
            $from = $this->now->startOfMonth()->subMonths($months - 1);

            $rows = $this->assignedScope([$userId])
                ->join('task_oversight', 'task_oversight.task_id', '=', 'tasks.id')
                ->whereNotNull('task_oversight.rating')
                ->where('task_oversight.rated_at', '>=', $from)
                ->groupBy('ym')
                ->select(
                    DB::raw("DATE_FORMAT(task_oversight.rated_at, '%Y-%m') as ym"),
                    DB::raw('COUNT(*) as c'),
                    DB::raw('AVG(task_oversight.rating) as avg_rating')
                )
                ->get();

            foreach ($rows as $row) {
                if (isset($out[$row->ym])) {
                    $out[$row->ym] = [
                        'count' => (int) $row->c,
                        'avg'   => round((float) $row->avg_rating, 2),
                    ];
                }
            }
        } catch (Throwable $e) {
            // flat
        }

        return $out;
    }

    /**
     * What kinds of thing the person did in the module — the activity mix.
     * Reads as engagement: a person who only ever fires task_created is using
     * the board as a notebook, not working it.
     */
    public function activityMix(int $userId, int $days = 90): array
    {
        try {
            return DB::table('task_activity_logs')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $this->now->subDays($days))
                ->groupBy('activity_type')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->select('activity_type', DB::raw('COUNT(*) as c'))
                ->pluck('c', 'activity_type')
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    /* ── The one shared scope ───────────────────────────────────────────── */

    /**
     * Tasks assigned to these people: the pivot, alive, top level only.
     *
     * Every aggregate in this class starts here, which is what guarantees the
     * dashboard's "42 tasks" and the dossier's "42 tasks" are the same 42.
     */
    private function assignedScope(array $userIds)
    {
        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->whereIn('task_user.user_id', $userIds)
            ->whereNull('tasks.deleted_at')
            ->whereNull('tasks.parent_id');
    }
}
