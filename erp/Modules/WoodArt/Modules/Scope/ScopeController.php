<?php

namespace Modules\WoodArt\Modules\Scope;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Projects\Models\Project;
use Modules\WoodArt\Modules\Scope\Models\Phase;
use Modules\WoodArt\Modules\Scope\Models\Requirement;
use Modules\WoodArt\Modules\Scope\Models\Space;

/**
 * Wood Art · Spaces & Phases — everything this module owns lives in this
 * folder. Mirrors the reference's companies/woodart/modules/scope/.
 *
 * Its own framing: divide a project into spaces, run each space through its
 * phases, and see what that work needs and who is carrying it.
 *
 * ─── OWNERSHIP ───────────────────────────────────────────────────────────
 * This module owns `wa_spaces`, `wa_phases` and `wa_requirements`.
 * `wa_projects` stays owned by Projects — registering a project is still done
 * there, so there is only ever ONE creation path for a project.
 *
 * ─── WHAT IT DOES NOT DO ─────────────────────────────────────────────────
 * A phase reaching Complete does not move its project's progress, and a
 * requirement is a PLAN, not a transaction — it does not post spend, raise a
 * purchase order or move stock. Progress belongs to Projects, spend to
 * Procurement. A second writer for either would give the ERP two answers to
 * one question.
 */
class ScopeController extends Controller
{
    /** Tab labels — module.json's menu, in its order. */
    public const SECTIONS = [
        'spaces'    => 'Spaces',
        'phases'    => 'Phase Board',
        'materials' => 'Material Demand',
        'load'      => 'Team Load',
    ];

    private const SUBTITLES = [
        'spaces'    => 'Every room of every project — its size, and how far its phases have got.',
        'phases'    => 'Each phase by state. Drag a card to move it.',
        'materials' => 'What the planned work needs, and what it was costed at.',
        'load'      => 'Who is carrying which phases, and what is running late.',
    ];

    public function show(Request $request, string $role, string $section = 'spaces'): View
    {
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        $spaces  = $this->spaces();
        $phases  = $this->phases();
        $reqs    = $this->requirements();
        $names   = $this->projectNames();
        $people  = $this->people();

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $spaces = $spaces->filter(fn (Space $s) => str_contains(mb_strtolower(implode(' ', [
                $s->name, $s->ext_id, $s->kind, $s->project, $names[$s->project] ?? '',
            ])), $needle))->values();

            $keep   = $spaces->pluck('ext_id')->all();
            $phases = $phases->filter(fn (Phase $p) => \in_array($p->space, $keep, true)
                || str_contains(mb_strtolower($p->name . ' ' . $p->code . ' ' . $p->ext_id), $needle))->values();
        }

        return view('woodart::scope', [
            'waModule'     => 'scope',
            'waRoute'      => 'role.woodart.scope',
            'waIcon'       => 'diagram-3-fill',
            'section'      => $section,
            'sections'     => self::SECTIONS,
            'title'        => self::SECTIONS[$section],
            'subtitle'     => self::SUBTITLES[$section],
            'spaces'       => $this->spaceRows($spaces, $phases),
            'board'        => $this->board($phases),
            'statuses'     => Phase::STATUSES,
            'demand'       => $this->demand($reqs),
            'load'         => $this->byOwner($phases, $people),
            'stats'        => $this->summary($spaces, $phases, $reqs),
            'projectNames' => $names,
            'spaceNames'   => $spaces->pluck('name', 'ext_id')->all(),
            'people'       => $people,
            'q'            => $q,
        ]);
    }

    /* ── spaces ───────────────────────────────────────────────────────────── */

    public function createSpace(string $role): View
    {
        return view('woodart::space-form', $this->spaceForm(null));
    }

    public function storeSpace(Request $request, string $role): RedirectResponse
    {
        $space = new Space($this->validatedSpace($request) + [
            'ext_id'     => $this->nextExt('wa_spaces', 'SPC', 3),
            'created_on' => now()->toDateString(),
        ]);
        $space->save();

        return redirect()
            ->route('role.woodart.scope', ['role' => $role, 'section' => 'spaces'])
            ->with('wa_status', "{$space->ext_id} — {$space->name} was added.");
    }

    public function editSpace(string $role, Space $space): View
    {
        return view('woodart::space-form', $this->spaceForm($space));
    }

    public function updateSpace(Request $request, string $role, Space $space): RedirectResponse
    {
        $space->fill($this->validatedSpace($request))->save();

        return redirect()
            ->route('role.woodart.scope', ['role' => $role, 'section' => 'spaces'])
            ->with('wa_status', "{$space->ext_id} — {$space->name} was updated.");
    }

    public function confirmDeleteSpace(string $role, Space $space): View
    {
        return view('woodart::space-delete', [
            'waModule' => 'scope',
            'waRoute'  => 'role.woodart.scope',
            'waIcon'   => 'diagram-3-fill',
            'section'  => 'spaces',
            'sections' => self::SECTIONS,
            'title'    => 'Remove Space',
            'subtitle' => "Check what is attached before removing {$space->ext_id}.",
            'space'    => $space,
            'attached' => $this->phases()->where('space', $space->ext_id),
        ]);
    }

    /**
     * Soft delete the space ONLY.
     *
     * Its phases are deliberately KEPT. They are the record that the work was
     * planned and, in many cases, done — deleting them because the room grouping
     * changed would erase real history. They show as orphans on the board until
     * a space with the same code exists again, which is a problem you can SEE
     * and fix, unlike silently vanished rows.
     */
    public function destroySpace(string $role, Space $space): RedirectResponse
    {
        $label = "{$space->ext_id} — {$space->name}";
        $kept  = $this->phases()->where('space', $space->ext_id)->count();
        $space->delete();

        $note = $kept > 0
            ? " Its {$kept} " . \Illuminate\Support\Str::plural('phase', $kept) . ' were kept and now show as unassigned.'
            : '';

        return redirect()
            ->route('role.woodart.scope', ['role' => $role, 'section' => 'spaces'])
            ->with('wa_status', "{$label} was removed.{$note}");
    }

    /* ── phases ───────────────────────────────────────────────────────────── */

    public function createPhase(string $role): View
    {
        return view('woodart::phase-form', $this->phaseForm(null));
    }

    public function storePhase(Request $request, string $role): RedirectResponse
    {
        $phase = new Phase($this->validatedPhase($request) + [
            'ext_id' => $this->nextExt('wa_phases', 'PHS', 4),
        ]);
        $phase->save();

        return redirect()
            ->route('role.woodart.scope', ['role' => $role, 'section' => 'phases'])
            ->with('wa_status', "{$phase->ext_id} — {$phase->name} was added.");
    }

    public function editPhase(string $role, Phase $phase): View
    {
        return view('woodart::phase-form', $this->phaseForm($phase));
    }

    public function updatePhase(Request $request, string $role, Phase $phase): RedirectResponse
    {
        $phase->fill($this->validatedPhase($request))->save();

        return redirect()
            ->route('role.woodart.scope', ['role' => $role, 'section' => 'phases'])
            ->with('wa_status', "{$phase->ext_id} — {$phase->name} was updated.");
    }

    /**
     * Move a phase between the three states.
     *
     * Serves BOTH the board drag (JSON) and the posted arrow buttons
     * (redirect), so the board works identically without JavaScript. Writes
     * `status` and nothing else — see the class note on why a completed phase
     * does not touch project progress.
     */
    public function updatePhaseStatus(Request $request, string $role, Phase $phase): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Phase::STATUSES)],
        ]);

        $phase->status = $data['status'];
        $phase->save();

        $message = "{$phase->ext_id} moved to {$phase->status}.";

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'status' => $phase->status, 'message' => $message]);
        }

        return redirect()
            ->route('role.woodart.scope', ['role' => $role, 'section' => 'phases'])
            ->with('wa_status', $message);
    }

    public function confirmDeletePhase(string $role, Phase $phase): View
    {
        return view('woodart::phase-delete', [
            'waModule' => 'scope',
            'waRoute'  => 'role.woodart.scope',
            'waIcon'   => 'diagram-3-fill',
            'section'  => 'phases',
            'sections' => self::SECTIONS,
            'title'    => 'Remove Phase',
            'subtitle' => "Check what is attached before removing {$phase->ext_id}.",
            'phase'    => $phase,
            'needs'    => $this->requirements()->where('phase', $phase->ext_id),
            'spaceName' => $this->spaces()->firstWhere('ext_id', $phase->space)?->name,
        ]);
    }

    /** Soft delete. Its requirements are kept, for the same reason as above. */
    public function destroyPhase(string $role, Phase $phase): RedirectResponse
    {
        $label = "{$phase->ext_id} — {$phase->name}";
        $phase->delete();

        return redirect()
            ->route('role.woodart.scope', ['role' => $role, 'section' => 'phases'])
            ->with('wa_status', "{$label} was removed.");
    }

    /* ── validation ───────────────────────────────────────────────────────── */

    private function validatedSpace(Request $request): array
    {
        $data = $request->validate([
            'project' => ['required', 'string', 'max:40'],
            'name'    => ['required', 'string', 'max:120'],
            'kind'    => ['required', 'string', 'max:40'],
            'area'    => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'sort'    => ['nullable', 'integer', 'min:0', 'max:65535'],
            'note'    => ['nullable', 'string', 'max:5000'],
        ]);

        $data['area'] ??= 0;
        $data['sort'] ??= 1;

        return $data;
    }

    /**
     * `space` is NOT validated against wa_spaces, and `owner_id` is not
     * validated against the user register — the same rule the rest of the
     * suite follows. A phase whose space or owner was later removed must still
     * save; the screens flag it rather than refusing the write.
     */
    private function validatedPhase(Request $request): array
    {
        $data = $request->validate([
            'project'  => ['required', 'string', 'max:40'],
            'space'    => ['nullable', 'string', 'max:40'],
            'name'     => ['required', 'string', 'max:120'],
            'code'     => ['nullable', 'string', 'max:60'],
            'sort'     => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status'   => ['required', Rule::in(Phase::STATUSES)],
            'owner_id' => ['nullable', 'string', 'max:40'],
            'start'    => ['nullable', 'date'],
            'finish'   => ['nullable', 'date', 'after_or_equal:start'],
            'note'     => ['nullable', 'string', 'max:5000'],
        ], [
            'finish.after_or_equal' => 'The finish date cannot fall before the start date.',
        ]);

        $data['sort']  ??= 0;
        $data['space'] ??= '';

        return $data;
    }

    /* ── form payloads ────────────────────────────────────────────────────── */

    private function spaceForm(?Space $space): array
    {
        return [
            'waModule' => 'scope',
            'waRoute'  => 'role.woodart.scope',
            'waIcon'   => 'diagram-3-fill',
            'section'  => 'spaces',
            'sections' => self::SECTIONS,
            'title'    => $space ? "Edit {$space->ext_id}" : 'New Space',
            'subtitle' => $space
                ? 'Change what has moved on. The space code cannot change — phases point at it.'
                : 'Add a room to a project. Its phases are added separately.',
            'kinds'    => Space::KINDS,
            'projects' => $this->projectOptions(),
            'space'    => $space,
            'nextExt'  => $space?->ext_id ?? $this->nextExt('wa_spaces', 'SPC', 3),
        ];
    }

    private function phaseForm(?Phase $phase): array
    {
        return [
            'waModule'  => 'scope',
            'waRoute'   => 'role.woodart.scope',
            'waIcon'    => 'diagram-3-fill',
            'section'   => 'phases',
            'sections'  => self::SECTIONS,
            'title'     => $phase ? "Edit {$phase->ext_id}" : 'New Phase',
            'subtitle'  => $phase
                ? 'Change what has moved on. The phase code cannot change.'
                : 'Add a stage to a space. Only the project and a name are required.',
            'statuses'  => Phase::STATUSES,
            'projects'  => $this->projectOptions(),
            'spaceList' => $this->spaces(),
            'trades'    => $this->tradeOptions(),
            'people'    => $this->people(),
            'phase'     => $phase,
            'nextExt'   => $phase?->ext_id ?? $this->nextExt('wa_phases', 'PHS', 4),
        ];
    }

    /* ── reads, every one fault-tolerant ──────────────────────────────────── */

    private function nextExt(string $table, string $prefix, int $pad): string
    {
        try {
            if (! Schema::hasTable($table)) {
                return $prefix . '-' . str_pad('1', $pad, '0', STR_PAD_LEFT);
            }

            $max = DB::table($table)
                ->where('company_id', 'woodart')
                ->where('ext_id', 'like', $prefix . '-%')
                ->selectRaw('MAX(CAST(SUBSTRING(ext_id, ' . (\strlen($prefix) + 2) . ') AS UNSIGNED)) AS n')
                ->value('n');

            return $prefix . '-' . str_pad((string) (((int) $max) + 1), $pad, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            report($e);

            return $prefix . '-' . str_pad('1', $pad, '0', STR_PAD_LEFT);
        }
    }

    private function spaces(): Collection
    {
        try {
            if (! Schema::hasTable('wa_spaces')) {
                return collect();
            }

            return Space::query()->orderBy('project')->orderBy('sort')->orderBy('ext_id')->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    private function phases(): Collection
    {
        try {
            if (! Schema::hasTable('wa_phases')) {
                return collect();
            }

            return Phase::query()->orderBy('space')->orderBy('sort')->orderBy('ext_id')->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    private function requirements(): Collection
    {
        try {
            if (! Schema::hasTable('wa_requirements')) {
                return collect();
            }

            return Requirement::query()->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

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

    private function projectOptions(): array
    {
        $out = [];
        foreach ($this->projectNames() as $ext => $name) {
            $out[$ext] = $ext . ' · ' . $name;
        }
        ksort($out);

        return $out;
    }

    /** Trades already used on a phase, for the form's picker. */
    private function tradeOptions(): array
    {
        try {
            if (! Schema::hasTable('wa_phases')) {
                return [];
            }

            return Phase::query()->whereNotNull('code')->where('code', '!=', '')
                ->distinct()->orderBy('code')->pluck('code')->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Employee CODE => name, for showing who owns a phase.
     *
     * This reads the SHARED user register, which is allowed because it is
     * read-only AND scoped to Wood Art's own company id — it never sees
     * another company's people. It is wrapped twice over (hasTable plus
     * try/catch) because a shared table's shape is not this module's to
     * guarantee: if anything about it changes, Scope must degrade to showing
     * the raw code, never throw. A code that does not resolve is shown as-is
     * and flagged, never hidden.
     */
    private function people(): array
    {
        try {
            if (! Schema::hasTable('users')) {
                return [];
            }

            return DB::table('users')
                ->where('company_id', 6)
                ->whereNotNull('employee_id_no')
                ->where('employee_id_no', '!=', '')
                ->pluck('name', 'employee_id_no')
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /* ── derived views of the data ────────────────────────────────────────── */

    /** Each space with its phase progress rolled up. */
    private function spaceRows(Collection $spaces, Collection $phases): array
    {
        $bySpace = $phases->groupBy('space');

        return $spaces->map(function (Space $s) use ($bySpace) {
            $mine  = $bySpace->get($s->ext_id, collect());
            $done  = $mine->where('status', 'Complete')->count();
            $total = $mine->count();

            return [
                'space'    => $s,
                'phases'   => $total,
                'done'     => $done,
                'active'   => $mine->where('status', 'Active')->count(),
                'overdue'  => $mine->filter->is_overdue->count(),
                'pct'      => $total ? (int) round($done / $total * 100) : 0,
            ];
        })->all();
    }

    /**
     * The three board columns, every phase in exactly one. A phase on a status
     * no longer in the vocabulary gets its own extra column rather than
     * vanishing — the same treatment Projects and Workshop give.
     */
    private function board(Collection $phases): array
    {
        $out = [];
        foreach (Phase::STATUSES as $s) {
            $out[$s] = $phases->where('status', $s)->values();
        }
        foreach ($phases->pluck('status')->unique() as $s) {
            $out[$s] ??= $phases->where('status', $s)->values();
        }

        return $out;
    }

    /** Planned material demand, dearest first. Contract work is listed apart. */
    private function demand(Collection $reqs): array
    {
        $bag = [];
        foreach ($reqs as $r) {
            $k = $r->kind . '|' . $r->item;
            $bag[$k] ??= [
                'item' => $r->item, 'kind' => $r->kind, 'unit' => $r->unit,
                'qty' => 0.0, 'cost' => 0, 'sale' => 0, 'phases' => [], 'statuses' => [],
            ];
            $bag[$k]['qty']  += $r->qty;
            $bag[$k]['cost'] += $r->line_cost;
            $bag[$k]['sale'] += $r->line_sale;
            $bag[$k]['phases'][$r->phase] = true;
            $bag[$k]['statuses'][$r->status] = true;
        }

        $rows = array_map(function (array $b) {
            $b['phases']   = \count($b['phases']);
            $b['statuses'] = array_keys($b['statuses']);
            $b['margin']   = $b['sale'] - $b['cost'];

            return $b;
        }, array_values($bag));

        usort($rows, fn (array $a, array $b) => $b['cost'] <=> $a['cost']);

        return $rows;
    }

    /** Phases per owner, busiest (most OPEN) first. */
    private function byOwner(Collection $phases, array $people): array
    {
        $rows = [];
        foreach ($phases as $p) {
            $code = $p->owner_id ?: '';
            $key  = $code ?: '__none__';
            $rows[$key] ??= [
                'code'   => $code,
                'name'   => $code === '' ? 'Unassigned' : ($people[$code] ?? $code),
                'known'  => $code === '' || \array_key_exists($code, $people),
                'total'  => 0, 'open' => 0, 'active' => 0, 'done' => 0, 'overdue' => 0,
            ];

            $rows[$key]['total']++;
            $p->is_open ? $rows[$key]['open']++ : $rows[$key]['done']++;
            if ($p->status === 'Active') {
                $rows[$key]['active']++;
            }
            if ($p->is_overdue) {
                $rows[$key]['overdue']++;
            }
        }

        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b) => [$b['open'], $b['total']] <=> [$a['open'], $a['total']]);

        $peak = max(1, max(array_column($rows, 'open') ?: [0]));
        foreach ($rows as &$r) {
            $r['share'] = (int) round($r['open'] / $peak * 100);
        }

        return $rows;
    }

    private function summary(Collection $spaces, Collection $phases, Collection $reqs): array
    {
        $done = $phases->where('status', 'Complete')->count();

        return [
            'spaces'   => $spaces->count(),
            'area'     => (int) $spaces->sum('area'),
            'projects' => $spaces->pluck('project')->unique()->count(),
            'phases'   => $phases->count(),
            'active'   => $phases->where('status', 'Active')->count(),
            'done'     => $done,
            'open'     => $phases->count() - $done,
            'overdue'  => $phases->filter->is_overdue->count(),
            'pct'      => $phases->count() ? (int) round($done / $phases->count() * 100) : 0,
            'owners'   => $phases->pluck('owner_id')->filter()->unique()->count(),
            'needs'    => $reqs->count(),
            'cost'     => (int) $reqs->sum('line_cost'),
            'sale'     => (int) $reqs->sum('line_sale'),
        ];
    }
}
