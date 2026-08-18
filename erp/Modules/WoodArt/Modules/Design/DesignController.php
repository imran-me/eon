<?php

namespace Modules\WoodArt\Modules\Design;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Design\Models\Drawing;
use Modules\WoodArt\Modules\Design\Models\Revision;
use Modules\WoodArt\Modules\Projects\Models\Project;

/**
 * Wood Art · Design & 3D — everything this module owns lives in this folder.
 * Mirrors the reference's companies/woodart/modules/design/.
 *
 * ─── WHY THIS MODULE EXISTS ──────────────────────────────────────────────
 * Each delivery phase has ONE module owning the records it produces: Workshop
 * owns fabrication jobs, Site & Install owns site visits. The architecture and
 * 3D phase had no owner — its work lived in a board column and nowhere else.
 * This is that owner.
 *
 * Its own framing: Drawing Register is every deliverable and where it stands;
 * Approvals is what is sitting with the client; Design Load is who is carrying
 * the work.
 *
 * ─── THE PHASE GATE ──────────────────────────────────────────────────────
 * A project's design phase is complete only when it HAS deliverables and every
 * one of them is Approved. A project with none has NOT STARTED design — which
 * is emphatically not the same as being finished, and must never be counted as
 * such. See projectStatus().
 *
 * ─── WHAT THIS MODULE DOES NOT DO ────────────────────────────────────────
 * It stores no files. A drawing here is the RECORD of a deliverable — its
 * revision, its status, its trail — not the DWG or the render itself. Adding
 * uploads means a storage decision the owner has not made, and inventing one
 * would put Wood Art's files somewhere nobody agreed to.
 */
class DesignController extends Controller
{
    /** Tab labels — TAB_COPY[key][0] and module.json agree here. */
    public const SECTIONS = [
        'register'  => 'Drawing Register',
        'approvals' => 'Approvals',
        'load'      => 'Design Load',
    ];

    private const SUBTITLES = [
        'register'  => 'Every drawing and 3D deliverable — its revision, and where it stands.',
        'approvals' => 'What is sitting with the client, longest wait first.',
        'load'      => 'Who is carrying the design work, and how much has been revised.',
    ];

    public function show(Request $request, string $role, string $section = 'register'): View
    {
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        $drawings = $this->drawings();
        $names    = $this->projectNames();

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $drawings = $drawings->filter(fn (Drawing $d) => str_contains(mb_strtolower(implode(' ', [
                $d->title, $d->ext_id, $d->kind, $d->designer, $d->status,
                $d->project, $names[$d->project] ?? '',
            ])), $needle))->values();
        }

        return view('woodart::design', [
            'waModule'     => 'design',
            'waRoute'      => 'role.woodart.design',
            'waIcon'       => 'vector-pen',
            'section'      => $section,
            'sections'     => self::SECTIONS,
            'title'        => self::SECTIONS[$section],
            'subtitle'     => self::SUBTITLES[$section],
            'drawings'     => $drawings,
            'queue'        => $this->queue($drawings),
            'stats'        => $this->summary($drawings),
            'load'         => $this->byDesigner($drawings),
            'kinds'        => $this->byKind($drawings),
            'projectRows'  => $this->projectStatus($drawings, $names),
            'projectNames' => $names,
            'q'            => $q,
        ]);
    }

    public function create(string $role): View
    {
        return view('woodart::drawing-form', $this->formData(null));
    }

    /**
     * A new drawing opens its own trail: the first revision row is written
     * alongside it, because a deliverable that exists but has no history looks
     * identical to one whose history was lost.
     */
    public function store(Request $request, string $role): RedirectResponse
    {
        $drawing = new Drawing($this->validated($request) + [
            'ext_id'     => $this->nextExtId('wa_drawings', 'DWG'),
            'created_on' => now()->toDateString(),
        ]);
        $drawing->save();

        $this->logRevision($drawing, [
            'rev'    => $drawing->rev,
            'action' => 'Drafted',
            'by'     => $drawing->designer,
            'note'   => 'Created',
            'date'   => now()->toDateString(),
        ]);

        return redirect()
            ->route('role.woodart.design', ['role' => $role, 'section' => 'register'])
            ->with('wa_status', "{$drawing->ext_id} — {$drawing->title} was added.");
    }

    public function edit(string $role, Drawing $drawing): View
    {
        return view('woodart::drawing-form', $this->formData($drawing));
    }

    public function update(Request $request, string $role, Drawing $drawing): RedirectResponse
    {
        $drawing->fill($this->validated($request))->save();

        return redirect()
            ->route('role.woodart.design', ['role' => $role, 'section' => 'register'])
            ->with('wa_status', "{$drawing->ext_id} — {$drawing->title} was updated.");
    }

    /** The form for appending one entry to a drawing's trail. */
    public function createRevision(string $role, Drawing $drawing): View
    {
        return view('woodart::revision-form', [
            'waModule' => 'design',
            'waRoute'  => 'role.woodart.design',
            'waIcon'   => 'vector-pen',
            'section'  => 'register',
            'sections' => self::SECTIONS,
            'title'    => "Log a revision on {$drawing->ext_id}",
            'subtitle' => 'The trail is evidence — an entry is appended, never edited away.',
            'drawing'  => $drawing,
            'trail'    => $this->trail($drawing->ext_id),
            'actions'  => Revision::ACTIONS,
            'nextExt'  => $this->nextExtId('wa_revisions', 'RVN'),
        ]);
    }

    /**
     * Append a revision AND move the drawing to match.
     *
     * The two writes belong together: a trail entry saying "Approved" while the
     * drawing still reads "Issued" is exactly the disagreement the trail exists
     * to prevent. Revision::RESULTING_STATUS owns that mapping so it cannot be
     * invented differently here.
     */
    public function storeRevision(Request $request, string $role, Drawing $drawing): RedirectResponse
    {
        $data = $request->validate([
            'rev'    => ['required', 'string', 'max:4'],
            'action' => ['required', Rule::in(Revision::ACTIONS)],
            'by'     => ['nullable', 'string', 'max:160'],
            'note'   => ['nullable', 'string', 'max:500'],
            'date'   => ['nullable', 'date'],
        ]);

        $data['rev']  = strtoupper(substr($data['rev'], 0, 4));
        $data['date'] ??= now()->toDateString();

        $this->logRevision($drawing, $data);

        // Bring the deliverable in step with what was just recorded.
        $drawing->rev    = $data['rev'];
        $drawing->status = Revision::RESULTING_STATUS[$data['action']] ?? $drawing->status;

        if ($data['action'] === 'Issued') {
            $drawing->issued = $data['date'];
        }
        if ($data['action'] === 'Approved') {
            $drawing->approved = $data['date'];
        }

        $drawing->save();

        return redirect()
            ->route('role.woodart.design', ['role' => $role, 'section' => 'register'])
            ->with('wa_status', "{$drawing->ext_id} rev {$drawing->rev} — {$data['action']}.");
    }

    public function confirmDelete(string $role, Drawing $drawing): View
    {
        return view('woodart::drawing-delete', [
            'waModule' => 'design',
            'waRoute'  => 'role.woodart.design',
            'waIcon'   => 'vector-pen',
            'section'  => 'register',
            'sections' => self::SECTIONS,
            'title'    => 'Remove Drawing',
            'subtitle' => "Check this is the right deliverable before removing {$drawing->ext_id}.",
            'drawing'  => $drawing,
            'trail'    => $this->trail($drawing->ext_id),
        ]);
    }

    /**
     * Soft delete the drawing ONLY. Its revision trail is KEPT — it is the
     * evidence of what was issued to and agreed with a client, and deleting it
     * because the deliverable record went would destroy exactly the history
     * that matters in a dispute.
     */
    public function destroy(string $role, Drawing $drawing): RedirectResponse
    {
        $label = "{$drawing->ext_id} — {$drawing->title}";
        $kept  = $this->trail($drawing->ext_id)->count();
        $drawing->delete();

        $note = $kept > 0
            ? " Its {$kept} revision " . \Illuminate\Support\Str::plural('entry', $kept) . ' were kept.'
            : '';

        return redirect()
            ->route('role.woodart.design', ['role' => $role, 'section' => 'register'])
            ->with('wa_status', "{$label} was removed.{$note}");
    }

    /* ── writes ───────────────────────────────────────────────────────────── */

    private function logRevision(Drawing $drawing, array $data): void
    {
        $revision = new Revision([
            'ext_id'  => $this->nextExtId('wa_revisions', 'RVN'),
            'drawing' => $drawing->ext_id,
            'rev'     => $data['rev'] ?? $drawing->rev,
            'action'  => $data['action'] ?? 'Drafted',
            'by'      => $data['by'] ?? $drawing->designer,
            'note'    => $data['note'] ?? null,
            'date'    => $data['date'] ?? now()->toDateString(),
        ]);
        $revision->save();
    }

    /* ── validation ───────────────────────────────────────────────────────── */

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'    => ['required', 'string', 'max:200'],
            'kind'     => ['required', Rule::in(Drawing::KINDS)],
            'project'  => ['nullable', 'string', 'max:40'],
            'designer' => ['nullable', 'string', 'max:160'],
            'rev'      => ['required', 'string', 'max:4'],
            'status'   => ['required', Rule::in(Drawing::STATUSES)],
            'issued'   => ['nullable', 'date'],
            'approved' => ['nullable', 'date'],
        ]);

        $data['rev'] = strtoupper(substr($data['rev'], 0, 4));

        return $data;
    }

    private function formData(?Drawing $drawing): array
    {
        return [
            'waModule'  => 'design',
            'waRoute'   => 'role.woodart.design',
            'waIcon'    => 'vector-pen',
            'section'   => 'register',
            'sections'  => self::SECTIONS,
            'title'     => $drawing ? "Edit {$drawing->ext_id}" : 'New Drawing',
            'subtitle'  => $drawing
                ? 'Change what has moved on. The drawing code cannot change — its trail points at it.'
                : 'Register a deliverable. Its first trail entry is written automatically.',
            'kinds'     => Drawing::KINDS,
            'statuses'  => Drawing::STATUSES,
            'projects'  => $this->projectOptions(),
            'designers' => $this->designerOptions(),
            'drawing'   => $drawing,
            'trail'     => $drawing ? $this->trail($drawing->ext_id) : collect(),
            'nextExt'   => $drawing?->ext_id ?? $this->nextExtId('wa_drawings', 'DWG'),
        ];
    }

    /* ── reads, every one fault-tolerant ──────────────────────────────────── */

    private function nextExtId(string $table, string $prefix): string
    {
        try {
            if (! Schema::hasTable($table)) {
                return $prefix . '-101';
            }

            $model = $table === 'wa_drawings' ? Drawing::class : Revision::class;
            $max = $model::withTrashed()
                ->selectRaw('MAX(CAST(SUBSTRING(ext_id, 5) AS UNSIGNED)) AS n')
                ->where('ext_id', 'like', $prefix . '-%')
                ->value('n');

            // The live register starts at 101, so a first record on an empty
            // table continues that series rather than restarting at 001.
            return $prefix . '-' . (max(100, (int) $max) + 1);
        } catch (\Throwable $e) {
            report($e);

            return $prefix . '-101';
        }
    }

    /** Most recently issued first, undated last — the register's order. */
    private function drawings(): Collection
    {
        try {
            if (! Schema::hasTable('wa_drawings')) {
                return collect();
            }

            return Drawing::query()
                ->orderByRaw('issued IS NULL')
                ->orderByDesc('issued')
                ->orderBy('ext_id')
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /** One deliverable's trail, oldest revision first. */
    private function trail(string $drawingExtId): Collection
    {
        try {
            if (! Schema::hasTable('wa_revisions')) {
                return collect();
            }

            return Revision::query()->for($drawingExtId)
                ->orderBy('rev')->orderBy('ext_id')->get();
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

    /**
     * Designers already named on a drawing.
     *
     * The reference also pulls the employee directory here. This one does not,
     * for the same reason Workshop's crew picker does not: the field stays free
     * text, and Wood Art does not take a dependency on a shared table to fill
     * a suggestion list.
     */
    private function designerOptions(): array
    {
        try {
            if (! Schema::hasTable('wa_drawings')) {
                return [];
            }

            return Drawing::query()->whereNotNull('designer')->where('designer', '!=', '')
                ->distinct()->orderBy('designer')->pluck('designer')->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /* ── derived views ────────────────────────────────────────────────────── */

    /** Only what is with the client, longest wait first. */
    private function queue(Collection $drawings): Collection
    {
        return $drawings->filter->is_waiting
            ->sortByDesc(fn (Drawing $d) => $d->waiting_days ?? 0)
            ->values();
    }

    /**
     * Design progress per project — and THE PHASE GATE.
     *
     * `complete` is true only when a project HAS deliverables and none is still
     * open. A project with no drawings at all is reported as "not started",
     * never as complete: `total > 0` is what stops an empty project from
     * looking finished, and it is the single most important line here.
     */
    private function projectStatus(Collection $drawings, array $names): array
    {
        $rows = [];
        foreach ($drawings as $d) {
            $k = $d->project ?: '';
            $key = $k ?: '__none__';
            $rows[$key] ??= [
                'project' => $k,
                'name'    => $k === '' ? 'Not linked to a project' : ($names[$k] ?? $k),
                'known'   => $k === '' || \array_key_exists($k, $names),
                'total' => 0, 'open' => 0, 'waiting' => 0, 'approved' => 0,
            ];

            $rows[$key]['total']++;
            $d->is_open ? $rows[$key]['open']++ : $rows[$key]['approved']++;
            if ($d->is_waiting) {
                $rows[$key]['waiting']++;
            }
        }

        foreach ($rows as &$r) {
            $r['complete'] = $r['total'] > 0 && $r['open'] === 0;
            $r['pct']      = $r['total'] > 0 ? (int) round($r['approved'] / $r['total'] * 100) : 0;
        }

        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b) => [$b['open'], $b['total']] <=> [$a['open'], $a['total']]);

        return $rows;
    }

    /** Load per designer, busiest (most OPEN) first. */
    private function byDesigner(Collection $drawings): array
    {
        $rows = [];
        foreach ($drawings as $d) {
            $k = $d->designer ?: 'Unassigned';
            $rows[$k] ??= ['name' => $k, 'total' => 0, 'open' => 0,
                           'waiting' => 0, 'revisions' => 0, 'approved' => 0];

            $rows[$k]['total']++;
            $rows[$k]['revisions'] += $d->rev_count;
            $d->is_open ? $rows[$k]['open']++ : $rows[$k]['approved']++;
            if ($d->is_waiting) {
                $rows[$k]['waiting']++;
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

    /** The deliverable mix. */
    private function byKind(Collection $drawings): array
    {
        $total = max(1, $drawings->count());

        return collect(Drawing::KINDS)
            ->map(fn (string $k) => [
                'name'  => $k,
                'count' => $n = $drawings->where('kind', $k)->count(),
                'share' => (int) round($n / $total * 100),
            ])
            ->filter(fn (array $r) => $r['count'] > 0)
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function summary(Collection $drawings): array
    {
        $queue    = $this->queue($drawings);
        $approved = $drawings->reject->is_open->count();

        return [
            'drawings'  => $drawings->count(),
            'draft'     => $drawings->where('status', 'Draft')->count(),
            'issued'    => $drawings->where('status', 'Issued')->count(),
            'commented' => $drawings->where('status', 'Commented')->count(),
            'approved'  => $approved,
            'open'      => $drawings->count() - $approved,
            // With the client, or come back with comments — either way it needs
            // somebody to act on it.
            'attention' => $drawings->whereIn('status', ['Issued', 'Commented'])->count(),
            'waiting'   => $queue->count(),
            'oldest'    => $queue->first()?->waiting_days,
            'revisions' => (int) $drawings->sum('rev_count'),
            'designers' => $drawings->pluck('designer')->filter()->unique()->count(),
            'pct'       => $drawings->count() ? (int) round($approved / $drawings->count() * 100) : 0,
        ];
    }
}
