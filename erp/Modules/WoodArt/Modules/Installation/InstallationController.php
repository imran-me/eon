<?php

namespace Modules\WoodArt\Modules\Installation;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Installation\Models\Install;
use Modules\WoodArt\Modules\Projects\Models\Project;

/**
 * Wood Art · Site & Install — everything this module owns lives in this folder.
 * Mirrors the reference's companies/woodart/modules/installation/.
 *
 * As with Workshop, the key and the title differ: the module key and URL
 * segment are `installation`, the label is `Site & Install` (module.json
 * "key"/"title"). The sidebar and woodart-nav.js key on `installation`.
 *
 * Its own framing: Schedule is every site visit and who is going; Snag List is
 * what is stopping handover; Teams is who is where and which crew is carrying
 * the snags.
 *
 * ─── WHAT THIS MODULE DELIBERATELY DOES NOT DO ───────────────────────────
 * 1. It does NOT bill the handover (reference decision I4). Billing belongs to
 *    Projects; a second posting path would double-bill every project. The
 *    reference calls this the most damaging thing this module could have done.
 * 2. It does NOT edit individual snag items (decision I5). It reads the list,
 *    shows the count and orders the queue. Two editors for one list is a bug
 *    factory — see Install::$snag_list for how both shapes are read.
 */
class InstallationController extends Controller
{
    /** Tab labels — TAB_COPY[key][0] and module.json agree here. */
    public const SECTIONS = [
        'schedule' => 'Schedule',
        'snags'    => 'Snag List',
        'teams'    => 'Teams',
    ];

    /** Verbatim from the module's TAB_COPY (view.js:240-244). */
    private const SUBTITLES = [
        'schedule' => 'Every site visit — team, date, status and open snags.',
        'snags'    => 'What is stopping handover, worst site first.',
        'teams'    => 'Who is where, and which crew is carrying the snags.',
    ];

    /** See ProjectsController::show() for why $role must be declared. */
    public function show(Request $request, string $role, string $section = 'schedule'): View
    {
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        $installs = $this->installs();
        $names    = $this->projectNames();

        // Search — a GET param, same grammar as every other Wood Art register,
        // so the result is bookmarkable and needs no script inside
        // [data-wa-view].
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $installs = $installs->filter(function (Install $i) use ($needle, $names) {
                return str_contains(mb_strtolower(implode(' ', [
                    $i->site, $i->ext_id, $i->team, $i->status,
                    $i->project, $names[$i->project] ?? '',
                ])), $needle);
            })->values();
        }

        return view('woodart::installation', [
            'waModule'     => 'installation',
            'waRoute'      => 'role.woodart.installation',
            'waIcon'       => 'truck',
            'section'      => $section,
            'sections'     => self::SECTIONS,
            'title'        => self::SECTIONS[$section],
            'subtitle'     => self::SUBTITLES[$section],
            'installs'     => $installs,
            'queue'        => $this->snagQueue($installs),
            'stats'        => $this->summary($installs),
            'teams'        => $this->byTeam($installs),
            'projectNames' => $names,
            'q'            => $q,
        ]);
    }

    public function create(string $role): View
    {
        return view('woodart::install-form', $this->formData(null));
    }

    public function store(Request $request, string $role): RedirectResponse
    {
        $install = new Install($this->validated($request) + [
            'ext_id'     => $this->nextExtId(),
            'created_on' => now()->toDateString(),
        ]);
        $install->save();

        return redirect()
            ->route('role.woodart.installation', ['role' => $role, 'section' => 'schedule'])
            ->with('wa_status', "{$install->ext_id} — {$install->site} was scheduled.");
    }

    public function edit(string $role, Install $install): View
    {
        return view('woodart::install-form', $this->formData($install));
    }

    public function update(Request $request, string $role, Install $install): RedirectResponse
    {
        $install->fill($this->validated($request))->save();

        return redirect()
            ->route('role.woodart.installation', ['role' => $role, 'section' => 'schedule'])
            ->with('wa_status', "{$install->ext_id} — {$install->site} was updated.");
    }

    public function confirmDelete(string $role, Install $install): View
    {
        return view('woodart::install-delete', [
            'waModule'     => 'installation',
            'waRoute'      => 'role.woodart.installation',
            'waIcon'       => 'truck',
            'section'      => 'schedule',
            'sections'     => self::SECTIONS,
            'title'        => 'Remove Site Visit',
            'subtitle'     => "Check this is the right visit before removing {$install->ext_id}.",
            'install'      => $install,
            'projectNames' => $this->projectNames(),
        ]);
    }

    /** Soft delete — recoverable, and the code is never reused. */
    public function destroy(string $role, Install $install): RedirectResponse
    {
        $label = "{$install->ext_id} — {$install->site}";
        $install->delete();

        return redirect()
            ->route('role.woodart.installation', ['role' => $role, 'section' => 'schedule'])
            ->with('wa_status', "{$label} was removed.");
    }

    /**
     * One rule set for create and update.
     *
     * `snags` is accepted as a plain number, which is the only shape this build
     * writes. If the row already carries an itemised list, the model recomputes
     * the count from that list on save and this input is overridden — decision
     * I2, enforced in Install::booted() so no write path can bypass it.
     *
     * `project` is deliberately NOT validated against the projects table
     * (decision I8): that table may not be migrated on a given host, and an
     * install whose project was later removed must still save.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'site'    => ['required', 'string', 'max:160'],
            'project' => ['nullable', 'string', 'max:40'],
            'team'    => ['nullable', 'string', 'max:120'],
            'status'  => ['required', Rule::in(Install::STATUSES)],
            'date'    => ['nullable', 'date'],
            'snags'   => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $data['snags'] ??= 0;

        return $data;
    }

    private function formData(?Install $install): array
    {
        return [
            'waModule' => 'installation',
            'waRoute'  => 'role.woodart.installation',
            'waIcon'   => 'truck',
            'section'  => 'schedule',
            'sections' => self::SECTIONS,
            'title'    => $install ? "Edit {$install->ext_id}" : 'New Site Visit',
            'subtitle' => $install
                ? 'Change what has moved on. The visit code cannot change.'
                : 'Schedule a delivery or fitting. Only the site is required.',
            'statuses' => Install::STATUSES,
            'projects' => $this->projectOptions(),
            'crews'    => $this->teamOptions(),
            'install'  => $install,
            'nextExt'  => $install?->ext_id ?? $this->nextExtId(),
        ];
    }

    /** The next INS-nnn code, from the highest existing number. */
    private function nextExtId(): string
    {
        try {
            if (! Schema::hasTable('wa_installs')) {
                return 'INS-001';
            }

            $max = Install::withTrashed()
                ->selectRaw("MAX(CAST(SUBSTRING(ext_id, 5) AS UNSIGNED)) AS n")
                ->where('ext_id', 'like', 'INS-%')
                ->value('n');

            return 'INS-' . str_pad((string) (((int) $max) + 1), 3, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            report($e);

            return 'INS-001';
        }
    }

    /**
     * The schedule's order: soonest visit first, UNDATED LAST, then by code.
     *
     * Fault-tolerant like every other Wood Art register — a server without this
     * module's migration degrades to the empty state instead of throwing.
     */
    private function installs(): Collection
    {
        try {
            if (! Schema::hasTable('wa_installs')) {
                return collect();
            }

            return Install::query()
                ->orderByRaw("COALESCE(date, '9999-12-31') asc")
                ->orderBy('ext_id')
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Project ext_id => name. Reads the Projects module's table and NEVER
     * writes it; wrapped so Site & Install still serves on a host where
     * Projects is not migrated.
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

    /** Ext id => label, for the form's project picker. */
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
     * Team names already in use, for the form's picker.
     *
     * The reference falls back to four invented crew names on an empty store.
     * This one does not: inventing "Team Alpha" for a real business would put a
     * crew that does not exist in front of the owner. An empty list simply
     * means the field is free text until the first team is named.
     */
    private function teamOptions(): array
    {
        try {
            if (! Schema::hasTable('wa_installs')) {
                return [];
            }

            return Install::query()
                ->whereNotNull('team')
                ->where('team', '!=', '')
                ->distinct()
                ->orderBy('team')
                ->pluck('team')
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * The handover queue: only sites still carrying open snags, worst first.
     * Ordered on `open_snags`, which reads whichever snag shape the record has.
     */
    private function snagQueue(Collection $installs): Collection
    {
        return $installs
            ->filter(fn (Install $i) => $i->open_snags > 0)
            ->sortByDesc->open_snags
            ->values();
    }

    /** The header figures every screen quotes. One calculation, one truth. */
    private function summary(Collection $installs): array
    {
        $handedOver = $installs->reject->is_open;
        $withSnags  = $installs->filter(fn (Install $i) => $i->open_snags > 0);
        $load       = $this->byTeam($installs);
        $worst      = $this->snagQueue($installs)->first();

        return [
            'installs'  => $installs->count(),
            'active'    => $installs->whereIn('status', ['In Progress', 'Snagging'])->count(),
            'handover'  => $handedOver->count(),
            'open'      => $installs->count() - $handedOver->count(),
            'overdue'   => $installs->filter->is_overdue->count(),
            'snags'     => (int) $installs->sum('open_snags'),
            'sites'     => $withSnags->count(),
            // Handed over AND nothing outstanding — see Install::is_clean_handover.
            'clean'     => $installs->filter->is_clean_handover->count(),
            'attention' => $installs->filter(
                fn (Install $i) => $i->status === 'Snagging' || $i->is_overdue
            )->count(),
            'teams'     => $installs->filter->is_open->pluck('team')->filter()->unique()->count(),
            'allTeams'  => count($load),
            'rate'      => $installs->count()
                ? (int) round($handedOver->count() / $installs->count() * 100)
                : 0,
            'top'       => ($load && $load[0]['open'] > 0) ? $load[0]['name'] : null,
            'worst'     => $worst?->site ?? $worst?->ext_id,
        ];
    }

    /** Load per team, busiest (most OPEN sites) first — the reference's order. */
    private function byTeam(Collection $installs): array
    {
        $rows = [];
        foreach ($installs as $i) {
            $k = $i->team ?: 'Unassigned';
            $rows[$k] ??= [
                'name' => $k, 'sites' => 0, 'open' => 0,
                'snags' => 0, 'overdue' => 0, 'handover' => 0,
            ];

            $rows[$k]['sites']++;
            $rows[$k]['snags'] += $i->open_snags;
            $i->is_open ? $rows[$k]['open']++ : $rows[$k]['handover']++;
            if ($i->is_overdue) {
                $rows[$k]['overdue']++;
            }
        }

        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b) => [$b['open'], $b['sites']] <=> [$a['open'], $a['sites']]);

        // The bar is drawn relative to the busiest crew, so an empty schedule
        // cannot divide by zero.
        $peak = max(1, max(array_column($rows, 'open') ?: [0]));
        foreach ($rows as &$r) {
            $r['share'] = (int) round($r['open'] / $peak * 100);
        }

        return $rows;
    }
}
