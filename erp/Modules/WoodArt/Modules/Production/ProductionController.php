<?php

namespace Modules\WoodArt\Modules\Production;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Production\Models\Job;
use Modules\WoodArt\Modules\Projects\Models\Project;

/**
 * Wood Art · Workshop — everything this module owns lives in this folder.
 * Mirrors the reference's companies/woodart/modules/production/.
 *
 * NOTE ON THE NAME: the module key is `production` and the title is `Workshop`
 * — that split is the reference's own (module.json "key": "production",
 * "title": "Workshop"), and both matter. The sidebar's entry, the nav script's
 * DEFAULT_SUB key and the URL segment must all be `production`, or
 * woodart-nav.js cannot match the menu row to the page. Only what the user
 * reads says "Workshop".
 *
 * Its own framing: Job Register is every fabrication job on the floor; Workshop
 * Board is the four workshop states side by side; Station Load is where the
 * workshop is busy.
 *
 * ─── WHAT THIS MODULE DELIBERATELY DOES NOT DO (reference decision W6) ────
 * No material consumption, no capacity model, no labour cost, no project
 * progress derivation. Running a job through CNC really does eat board, but
 * wiring that needs the stock-movement ledger Materials does not have yet;
 * half-building it would make stock silently wrong. Progress belongs to
 * Projects — deriving a second answer here would give the ERP two.
 */
class ProductionController extends Controller
{
    /** Tab labels — TAB_COPY[key][0] and module.json agree here. */
    public const SECTIONS = [
        'jobs'  => 'Job Register',
        'board' => 'Workshop Board',
        'load'  => 'Station Load',
    ];

    /** Verbatim from the module's TAB_COPY (view.js:238-242). */
    private const SUBTITLES = [
        'jobs'  => 'Every fabrication job on the shop floor — station, owner, due date and status.',
        'board' => 'The four workshop states side by side. Click a card to open the job.',
        'load'  => 'Where the workshop is busy — open jobs, blockages and overdue work per station.',
    ];

    /**
     * The board's four columns, in the reference's order, with the authored
     * empty copy for a column with nothing in it (template.html:125-158).
     * FIXED STRUCTURE, NOT DATA — these are the workshop's four states, which
     * is why the reference writes them out as markup (decision W5). Only the
     * cards inside are per-record.
     */
    public const BOARD_COLUMNS = [
        ['Queued',  '#f4b740', 'inbox',          'Nothing queued'],
        ['Running', '#2f6bff', 'inbox',          'Nothing running'],
        ['Blocked', '#f0506e', 'check2-circle',  'Nothing blocked'],
        ['Done',    '#23c17e', 'inbox',          'Nothing finished yet'],
    ];

    /** See ProjectsController::show() for why $role must be declared. */
    public function show(Request $request, string $role, string $section = 'jobs'): View
    {
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        $jobs  = $this->jobs();
        $names = $this->projectNames();

        // Search — a GET param, same grammar as every other Wood Art register,
        // so the result is bookmarkable and needs no script inside
        // [data-wa-view].
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $jobs = $jobs->filter(function (Job $j) use ($needle, $names) {
                return str_contains(mb_strtolower(implode(' ', [
                    $j->job, $j->ext_id, $j->station, $j->assigned_to,
                    $j->status, $j->project, $names[$j->project] ?? '',
                ])), $needle);
            })->values();
        }

        return view('woodart::production', [
            'waModule'     => 'production',
            'waRoute'      => 'role.woodart.production',
            'waIcon'       => 'hammer',
            'section'      => $section,
            'sections'     => self::SECTIONS,
            'title'        => self::SECTIONS[$section],
            'subtitle'     => self::SUBTITLES[$section],
            'columns'      => self::BOARD_COLUMNS,
            'jobs'         => $jobs,
            'board'        => $this->board($jobs),
            'stats'        => $this->summary($jobs),
            'load'         => $this->byStation($jobs),
            'projectNames' => $names,
            'statuses'     => Job::STATUSES,
            'q'            => $q,
        ]);
    }

    public function create(string $role): View
    {
        return view('woodart::job-form', $this->formData(null));
    }

    public function store(Request $request, string $role): RedirectResponse
    {
        $job = new Job($this->validated($request) + [
            'ext_id'     => $this->nextExtId(),
            'created_on' => now()->toDateString(),
        ]);
        $job->save();

        return redirect()
            ->route('role.woodart.production', ['role' => $role, 'section' => 'jobs'])
            ->with('wa_status', "{$job->ext_id} — {$job->job} was added to the floor.");
    }

    public function edit(string $role, Job $job): View
    {
        return view('woodart::job-form', $this->formData($job));
    }

    public function update(Request $request, string $role, Job $job): RedirectResponse
    {
        $job->fill($this->validated($request))->save();

        return redirect()
            ->route('role.woodart.production', ['role' => $role, 'section' => 'jobs'])
            ->with('wa_status', "{$job->ext_id} — {$job->job} was updated.");
    }

    /**
     * Move a job between the four workshop states.
     *
     * Serves BOTH paths: the drag on the board (which posts in the background
     * and wants JSON) and the arrow buttons on each card (a normal form submit
     * that wants a redirect). The board therefore works identically with
     * JavaScript unavailable — see woodart-board.js on why that matters.
     *
     * Unlike Projects' stage move, nothing is rewritten as a side effect: a job
     * reaching Done does not touch its project's progress. Progress is the
     * Projects module's number, and this module must not own a second copy of
     * it (decision W6).
     */
    public function updateStatus(Request $request, string $role, Job $job): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Job::STATUSES)],
        ]);

        $job->status = $data['status'];
        $job->save();

        $message = "{$job->ext_id} moved to {$job->status}.";

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'status'  => $job->status,
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('role.woodart.production', ['role' => $role, 'section' => 'board'])
            ->with('wa_status', $message);
    }

    public function confirmDelete(string $role, Job $job): View
    {
        return view('woodart::job-delete', [
            'waModule'     => 'production',
            'waRoute'      => 'role.woodart.production',
            'waIcon'       => 'hammer',
            'section'      => 'jobs',
            'sections'     => self::SECTIONS,
            'title'        => 'Remove Job',
            'subtitle'     => "Check this is the right job before removing {$job->ext_id}.",
            'job'          => $job,
            'projectNames' => $this->projectNames(),
        ]);
    }

    /** Soft delete — recoverable, and the code is never reused. */
    public function destroy(string $role, Job $job): RedirectResponse
    {
        $label = "{$job->ext_id} — {$job->job}";
        $job->delete();

        return redirect()
            ->route('role.woodart.production', ['role' => $role, 'section' => 'jobs'])
            ->with('wa_status', "{$label} was removed.");
    }

    /**
     * One rule set for create and update.
     *
     * `project` is deliberately NOT validated against the projects table
     * (reference decision W3). That table may not even be migrated on a given
     * host, and a job whose project was later removed must still save. The
     * screens flag such a job as an orphan instead.
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'job'         => ['required', 'string', 'max:160'],
            'project'     => ['nullable', 'string', 'max:40'],
            'station'     => ['required', Rule::in(Job::STATIONS)],
            'assigned_to' => ['nullable', 'string', 'max:160'],
            'status'      => ['required', Rule::in(Job::STATUSES)],
            'due'         => ['nullable', 'date'],
        ]);
    }

    private function formData(?Job $job): array
    {
        return [
            'waModule' => 'production',
            'waRoute'  => 'role.woodart.production',
            'waIcon'   => 'hammer',
            'section'  => 'jobs',
            'sections' => self::SECTIONS,
            'title'    => $job ? "Edit {$job->ext_id}" : 'New Job',
            'subtitle' => $job
                ? 'Change what has moved on. The job code cannot change.'
                : 'Put a job on the floor. Only what is being made is required.',
            'stations' => Job::STATIONS,
            'statuses' => Job::STATUSES,
            'projects' => $this->projectOptions(),
            'crew'     => $this->crewOptions(),
            'job'      => $job,
            'nextExt'  => $job?->ext_id ?? $this->nextExtId(),
        ];
    }

    /** The next JOB-nnn code, from the highest existing number. */
    private function nextExtId(): string
    {
        try {
            if (! Schema::hasTable('wa_production')) {
                return 'JOB-001';
            }

            $max = Job::withTrashed()
                ->selectRaw("MAX(CAST(SUBSTRING(ext_id, 5) AS UNSIGNED)) AS n")
                ->where('ext_id', 'like', 'JOB-%')
                ->value('n');

            return 'JOB-' . str_pad((string) (((int) $max) + 1), 3, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            report($e);

            return 'JOB-001';
        }
    }

    /**
     * The register's order: soonest due first, UNDATED LAST, then by code.
     *
     * Fault-tolerant like every other Wood Art register — a server without this
     * module's migration degrades to the empty state instead of throwing.
     */
    private function jobs(): Collection
    {
        try {
            if (! Schema::hasTable('wa_production')) {
                return collect();
            }

            return Job::query()
                ->orderByRaw("COALESCE(due, '9999-12-31') asc")
                ->orderBy('ext_id')
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Project ext_id => name, for resolving a job's parent.
     *
     * Reads the Projects module's table and NEVER writes it. Wrapped because
     * Workshop must still serve on a host where Projects is not migrated —
     * that is exactly the half-deployed state CLAUDE.md says to design for.
     */
    private function projectNames(): array
    {
        try {
            if (! Schema::hasTable('wa_projects')) {
                return [];
            }

            return Project::query()->pluck('name', 'ext_id')->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** Ext id => label, for the job form's project picker. */
    private function projectOptions(): array
    {
        $out = [];
        foreach ($this->projectNames() as $ext => $name) {
            $out[$ext] = $ext . ' · ' . $name;
        }
        ksort($out);

        return $out;
    }

    /**
     * Who can be assigned — the names already used on jobs.
     *
     * The reference pulls the employee directory here. This one deliberately
     * does not: that directory is a SHARED table, and reading it would give
     * Wood Art a dependency on another company's data. Names typed on jobs are
     * enough to keep the picker useful, and the field stays free text.
     */
    private function crewOptions(): array
    {
        try {
            if (! Schema::hasTable('wa_production')) {
                return [];
            }

            return Job::query()
                ->whereNotNull('assigned_to')
                ->where('assigned_to', '!=', '')
                ->distinct()
                ->orderBy('assigned_to')
                ->pluck('assigned_to')
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * The four board columns, every job appearing in exactly one.
     *
     * A job on a status no longer in the vocabulary gets its own extra column
     * rather than vanishing — the same treatment Projects gives a retired
     * stage, and for the same reason: a record you cannot see is a record you
     * cannot fix.
     */
    private function board(Collection $jobs): array
    {
        $out = [];
        foreach (Job::STATUSES as $status) {
            $out[$status] = $jobs->where('status', $status)->values();
        }

        foreach ($jobs->pluck('status')->unique() as $status) {
            if (! \array_key_exists($status, $out)) {
                $out[$status] = $jobs->where('status', $status)->values();
            }
        }

        return $out;
    }

    /** The header figures every screen quotes. One calculation, one truth. */
    private function summary(Collection $jobs): array
    {
        $done    = $jobs->reject->is_open->count();
        $overdue = $jobs->filter->is_overdue->count();
        $blocked = $jobs->where('status', 'Blocked')->count();
        $load    = $this->byStation($jobs);

        return [
            'jobs'      => $jobs->count(),
            'running'   => $jobs->where('status', 'Running')->count(),
            'blocked'   => $blocked,
            'overdue'   => $overdue,
            'done'      => $done,
            'open'      => $jobs->count() - $done,
            'attention' => $blocked + $overdue,
            'pct'       => $jobs->count() ? (int) round($done / $jobs->count() * 100) : 0,
            'crew'      => $jobs->pluck('assigned_to')->filter()->unique()->count(),
            'stations'  => $jobs->pluck('station')->filter()->unique()->count(),
            'top'       => ($load && $load[0]['open'] > 0) ? $load[0]['name'] : null,
        ];
    }

    /**
     * Load per station, busiest first — ranked by OPEN jobs, not total
     * (reference decision W4). Finished work is history and does not compete
     * for a machine.
     */
    private function byStation(Collection $jobs): array
    {
        $rows = [];
        foreach ($jobs as $j) {
            $k = $j->station ?: 'Unassigned';
            $rows[$k] ??= [
                'name' => $k, 'total' => 0, 'open' => 0,
                'running' => 0, 'blocked' => 0, 'overdue' => 0, 'done' => 0,
            ];

            $rows[$k]['total']++;
            $j->is_open ? $rows[$k]['open']++ : $rows[$k]['done']++;
            if ($j->status === 'Running') {
                $rows[$k]['running']++;
            }
            if ($j->status === 'Blocked') {
                $rows[$k]['blocked']++;
            }
            if ($j->is_overdue) {
                $rows[$k]['overdue']++;
            }
        }

        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b) => [$b['open'], $b['total']] <=> [$a['open'], $a['total']]);

        // The bar on the load screen is drawn relative to the busiest station,
        // so an empty floor cannot divide by zero.
        $peak = max(1, max(array_column($rows, 'open') ?: [0]));
        foreach ($rows as &$r) {
            $r['share'] = (int) round($r['open'] / $peak * 100);
        }

        return $rows;
    }
}
