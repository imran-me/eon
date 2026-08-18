<?php

namespace Modules\WoodArt\Modules\Projects;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Projects\Models\Project;

/**
 * Wood Art · Projects — everything this module owns lives in this folder:
 * controller, routes.php, Models, Database/Migrations and resources/views.
 * Mirrors the reference's companies/woodart/modules/projects/.
 *
 * Copy source: reference view.js (pageHead + subDesc), transcribed verbatim.
 *
 * This is the first Wood Art module to read real data. Everything it reads
 * comes from `wa_projects` — a table no other company touches (see
 * Models/Project.php). Nothing here queries a shared table.
 */
class ProjectsController extends Controller
{
    /** Menu labels, mirroring the module's registry entry (module.json → menu). */
    public const SECTIONS = [
        'active'     => 'Active Projects',
        'design'     => 'Design Studio',
        'milestones' => 'Milestones',
        'gallery'    => 'Gallery',
    ];

    /**
     * Page-head titles differ from the MENU labels in the reference: the
     * milestones screen is headed "Milestones & Billing" while its menu entry
     * reads "Milestones" (view.js:145).
     */
    private const HEAD_TITLES = [
        'active'     => 'Active Projects',
        'design'     => 'Design Studio',
        'milestones' => 'Milestones & Billing',
        'gallery'    => 'Gallery',
    ];

    /** Verbatim from the module's subDesc() (view.js:165-170). */
    private const SUBTITLES = [
        'active'     => 'Live portfolio — stage, progress, margin and deadline countdown per fit-out.',
        'design'     => 'Design-build pipeline board — drag a project card to advance its stage.',
        'milestones' => 'Production and installation milestones, snag status and client billing ledger.',
        'gallery'    => 'Visual portfolio wall — every Woodart interior at a glance.',
    ];

    /**
     * $role is the {role} URL segment every route in this group carries. It is
     * unused here, but MUST be declared: the router passes route parameters
     * positionally, so omitting it makes $section receive the role instead.
     */
    public function show(Request $request, string $role, string $section = 'active'): View
    {
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        // Both the portfolio and the stage board read the same rows; only the
        // shaping differs, so there is one query and no chance of the two
        // screens disagreeing about what exists.
        $projects = \in_array($section, ['active', 'design', 'gallery', 'milestones'], true)
            ? $this->projects()
            : collect();

        // Search, mirroring the reference's own board filter (view.js:715):
        // a case-insensitive substring over name + client + designer + code.
        // It is a GET query param rather than a JS filter, so it survives a
        // reload, can be bookmarked, and needs no script inside [data-wa-view].
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $projects = $projects->filter(function (Project $p) use ($needle) {
                $hay = mb_strtolower(implode(' ', [$p->name, $p->client, $p->designer, $p->ext_id]));

                return str_contains($hay, $needle);
            })->values();
        }

        return view('woodart::projects', [
            'waModule' => 'projects',
            'waRoute'  => 'role.woodart.projects',
            'waIcon'   => 'easel2-fill',
            'section'  => $section,
            'sections' => self::SECTIONS,
            'title'    => self::HEAD_TITLES[$section],
            'subtitle' => self::SUBTITLES[$section],
            'projects' => $projects,
            'stats'    => $this->stats($projects),
            // Every stage gets a column, including the empty ones — the board
            // is the workflow, so a stage nobody is in must still be visible.
            //
            // Any stage found in the DATA that is not one of the five gets a
            // column too, appended at the end. Without that, a project on a
            // retired stage silently vanishes from the board — which is exactly
            // what happened on 2026-08-11 to a live job left on 'Approval'
            // after that value was removed. The reference's house rule applies:
            // an unrecognised value is shown and flagged, never hidden.
            'board'       => collect(Project::STAGES)
                ->merge($projects->pluck('stage')->unique()->diff(Project::STAGES)->values())
                ->mapWithKeys(fn (string $s) => [$s => $projects->where('stage', $s)->values()]),
            'stages'      => Project::STAGES,
            'stageColors' => Project::STAGE_COLORS,
            'typeColors'  => Project::TYPE_COLORS,
            'q'           => $q,
            'milestones'  => $section === 'milestones' ? $this->milestones($projects) : [],
        ]);
    }

    /**
     * The "New Project" form. A full page rather than a modal on purpose:
     * CLAUDE.md forbids <script> inside [data-wa-view], and a modal would need
     * one. A page needs no JavaScript at all, and because it lives under the
     * woodart/ prefix the nav script still swaps it in without a reload.
     */
    public function create(string $role): View
    {
        return view('woodart::project-form', $this->formData(null));
    }

    /**
     * Persist a new project.
     *
     * Writes one row to `wa_projects` and nothing else — no shared table is
     * read or written anywhere in this method.
     */
    public function store(Request $request, string $role): RedirectResponse
    {
        $data = $this->validated($request);

        $project = new Project($data + [
            'ext_id'     => $this->nextExtId(),
            'created_on' => now()->toDateString(),
        ]);
        $project->save();

        return redirect()
            ->route('role.woodart.projects', ['role' => $role, 'section' => 'active'])
            ->with('wa_status', "{$project->ext_id} — {$project->name} was registered.");
    }

    /** The same form, populated. */
    public function edit(string $role, Project $project): View
    {
        return view('woodart::project-form', $this->formData($project));
    }

    /**
     * Save changes to an existing project.
     *
     * `ext_id` is deliberately NOT editable: every other Wood Art record points
     * at a project by that string rather than by a foreign key (the reference's
     * own design), so renaming it would orphan the job's spaces, phases,
     * estimates and movements with nothing to detect it.
     */
    public function update(Request $request, string $role, Project $project): RedirectResponse
    {
        $project->fill($this->validated($request))->save();

        return redirect()
            ->route('role.woodart.projects', ['role' => $role, 'section' => 'active'])
            ->with('wa_status', "{$project->ext_id} — {$project->name} was updated.");
    }

    /**
     * Move a project to another stage from the board.
     *
     * The reference does this by dragging a card, which needs JavaScript inside
     * the swapped region — forbidden here (CLAUDE.md). A pair of posted arrows
     * gives the same outcome with no script at all, and keeps the move in the
     * browser history so Back undoes a misclick.
     *
     * Like the reference (view.js:733-746) a move is free in both directions,
     * and landing on Completed also forces progress to 100 — a finished job
     * showing 42% is the inconsistency that rule exists to prevent. It does NOT
     * bill: in the reference, billing is a separate, guarded, one-shot action,
     * and that separation is deliberate.
     */
    public function updateStage(Request $request, string $role, Project $project): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(Project::STAGES)],
        ]);

        $project->stage = $data['stage'];
        if ($data['stage'] === 'Completed') {
            $project->progress = 100;
        }
        $project->save();

        $message = "{$project->ext_id} moved to {$project->stage}.";

        // Dragging a card posts this in the background and needs the new
        // progress back, because landing on Completed rewrites it. The posted
        // arrows still get a normal redirect, so the board works identically
        // with JavaScript unavailable.
        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'stage'    => $project->stage,
                'progress' => (int) $project->progress,
                'message'  => $message,
            ]);
        }

        return redirect()
            ->route('role.woodart.projects', ['role' => $role, 'section' => 'design'])
            ->with('wa_status', $message);
    }

    /**
     * The delete confirmation page.
     *
     * A page, not a JS confirm(): no script may run inside [data-wa-view], and
     * the reference's own dialog spells out what is removed and what is kept
     * rather than asking "are you sure?".
     */
    public function confirmDelete(string $role, Project $project): View
    {
        return view('woodart::project-delete', [
            'waModule' => 'projects',
            'waRoute'  => 'role.woodart.projects',
            'waIcon'   => 'easel2-fill',
            'section'  => 'active',
            'sections' => self::SECTIONS,
            'title'    => 'Remove Project',
            'subtitle' => "Check this is the right job before removing {$project->ext_id}.",
            'project'  => $project,
        ]);
    }

    /**
     * Remove a project.
     *
     * A SOFT delete: the row is retained with a `deleted_at` stamp so the job
     * can be recovered and so its code is never silently reused. Nothing else
     * is touched — this module owns no other table yet, and when it does, the
     * reference's rule applies: stock movements and book entries are KEPT,
     * because "the material really did leave the store and the money really did
     * move; the corrections for those are an Adjustment and a reversal, never
     * an erasure."
     */
    public function destroy(string $role, Project $project): RedirectResponse
    {
        $label = "{$project->ext_id} — {$project->name}";
        $project->delete();

        return redirect()
            ->route('role.woodart.projects', ['role' => $role, 'section' => 'active'])
            ->with('wa_status', "{$label} was removed.");
    }

    /**
     * One rule set for both create and update, so the two can never drift.
     * Numeric fields are defaulted here rather than in the caller because the
     * columns are NOT NULL with a 0 default.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:200'],
            'client'   => ['nullable', 'string', 'max:160'],
            'type'     => ['required', Rule::in(Project::TYPES)],
            'area'     => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'value'    => ['nullable', 'integer', 'min:0'],
            'cost'     => ['nullable', 'integer', 'min:0'],
            'stage'    => ['required', Rule::in(Project::STAGES)],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'designer' => ['nullable', 'string', 'max:160'],
            'start'    => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start'],
        ], [
            'deadline.after_or_equal' => 'The deadline cannot fall before the start date.',
        ]);

        foreach (['area', 'value', 'cost', 'progress'] as $n) {
            $data[$n] ??= 0;
        }

        return $data;
    }

    /** Shared payload for the create and edit forms. */
    private function formData(?Project $project): array
    {
        return [
            'waModule' => 'projects',
            'waRoute'  => 'role.woodart.projects',
            'waIcon'   => 'easel2-fill',
            // Keep the Active tab lit: these pages belong to that section.
            'section'  => 'active',
            'sections' => self::SECTIONS,
            'title'    => $project ? "Edit {$project->ext_id}" : 'New Project',
            'subtitle' => $project
                ? 'Change what has moved on. The project code cannot change — other records point at it.'
                : 'Register a fit-out. Only the name is required — the rest can be filled in as the job firms up.',
            'stages'   => Project::STAGES,
            'types'    => Project::TYPES,
            'project'  => $project,
            'nextExt'  => $project?->ext_id ?? $this->nextExtId(),
        ];
    }

    /**
     * The next WAP-nnn code. The reference numbers projects from 101, and the
     * column is unique per company, so this reads the highest existing number
     * rather than counting rows (which would collide after a delete).
     */
    private function nextExtId(): string
    {
        try {
            if (! Schema::hasTable('wa_projects')) {
                return 'WAP-101';
            }

            $max = Project::withTrashed()
                ->selectRaw("MAX(CAST(SUBSTRING(ext_id, 5) AS UNSIGNED)) AS n")
                ->where('ext_id', 'like', 'WAP-%')
                ->value('n');

            return 'WAP-' . max(101, ((int) $max) + 1);
        } catch (\Throwable $e) {
            report($e);

            return 'WAP-101';
        }
    }

    /**
     * The portfolio, newest deadline pressure first.
     *
     * Deliberately fault-tolerant. A server whose database has not had this
     * module's migration run yet has no `wa_projects` table, and querying it
     * would throw. Rather than error, the screen falls back to the empty state
     * it showed before the data layer existed — the same "degrade, never
     * throw" discipline as the sidebar's Route::has() guard (CLAUDE.md rule 6).
     * The failure is contained to this Wood Art screen either way.
     */
    private function projects(): Collection
    {
        try {
            if (! Schema::hasTable('wa_projects')) {
                return collect();
            }

            return Project::query()
                ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END')
                ->orderBy('deadline')
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Milestones & Billing — the half of it this module can answer honestly.
     *
     * The reference's screen (view.js:775-846) needs four KPIs, two charts and
     * a billing ledger. Three of the four KPIs and the ledger read data we do
     * not hold: posted sales, `wa_production` and `wa_installs`. Those are
     * reported as UNKNOWN — a dash naming the desk that will supply them —
     * rather than as zero. Printing "0 jobs running" when no workshop exists
     * would not be an empty state, it would be a false statement.
     *
     * What IS answerable comes from this module's own rows:
     *   · Awaiting Billing — at Handover or Completed and not yet billed. The
     *     `billed` column has existed since the first migration and nothing has
     *     read it until now.
     *   · Projects by Stage and Portfolio Value by Type — the reference draws
     *     these as canvas charts; rendered here as bar rows, which need no
     *     chart library and therefore no script inside [data-wa-view].
     */
    private function milestones(Collection $projects): array
    {
        $total = max(1, (int) $projects->sum('value'));   // guards a ÷0 on an empty portfolio

        return [
            'awaitingBilling' => $projects
                ->whereIn('stage', Project::CLOSED_STAGES)
                ->where('billed', false)
                ->count(),

            'byStage' => collect(Project::STAGES)->map(fn (string $s) => [
                'label' => $s,
                'count' => $n = $projects->where('stage', $s)->count(),
                'share' => $projects->count() ? (int) round($n / $projects->count() * 100) : 0,
                'color' => Project::STAGE_COLORS[$s] ?? '#6f9c1c',
            ])->all(),

            'byType' => collect(Project::TYPES)->map(fn (string $t) => [
                'label' => $t,
                'value' => $v = (int) $projects->where('type', $t)->sum('value'),
                'share' => (int) round($v / $total * 100),
                'color' => Project::TYPE_COLORS[$t] ?? '#6f9c1c',
            ])->filter(fn (array $r) => $r['value'] > 0)->values()->all(),
        ];
    }

    /**
     * The five KPIs the reference computes for this screen (view.js:648-659):
     * portfolio value, committed cost, margin, live count and deadline risk.
     */
    private function stats(Collection $projects): array
    {
        return [
            'value'  => (int) $projects->sum('value'),
            'cost'   => (int) $projects->sum('cost'),
            'margin' => (int) $projects->sum('value') - (int) $projects->sum('cost'),
            'live'   => $projects->filter->is_live->count(),
            'risk'   => $projects->filter->is_at_risk->count(),
        ];
    }
}
