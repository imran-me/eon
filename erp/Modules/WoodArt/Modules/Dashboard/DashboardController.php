<?php

namespace Modules\WoodArt\Modules\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Clients\Models\Client;
use Modules\WoodArt\Modules\Design\Models\Drawing;
use Modules\WoodArt\Modules\Estimates\Models\Estimate;
use Modules\WoodArt\Modules\Installation\Models\Install;
use Modules\WoodArt\Modules\Materials\Models\Material;
use Modules\WoodArt\Modules\Procurement\Models\Purchase;
use Modules\WoodArt\Modules\Production\Models\Job;
use Modules\WoodArt\Modules\Projects\Models\Project;
use Modules\WoodArt\Modules\Scope\Models\Phase;

/**
 * Wood Art · Dashboard — the one screen that reads across the whole suite.
 *
 * It is the first item in Wood Art's menu and, until now, the only one with no
 * route behind it: the sidebar's Route::has() guard rendered it as an inert
 * toggle, so Wood Art had no landing screen at all. The shared ERP dashboard
 * is explicitly hidden for company 6 (sidebar.blade.php:422,
 * `activeCompanyId !== 6`), so nothing was filling the gap.
 *
 * ─── IT OWNS NO TABLE ────────────────────────────────────────────────────
 * Every figure here is READ from a module that already owns it. This module
 * has no migration and no model of its own, deliberately: a dashboard that
 * stored its own copy of a number would be a second source of truth, and the
 * first thing to go stale. If a figure looks wrong, it is wrong in the module
 * that owns it, and that is where it gets fixed.
 *
 * ─── WHY EVERY READ IS WRAPPED ───────────────────────────────────────────
 * This is the only screen that touches nine tables at once, which makes it the
 * likeliest place for a half-deployed server to throw. Each read is guarded by
 * hasTable() AND try/catch, and each returns a neutral value on failure, so a
 * module that has not been migrated yet simply shows as empty rather than
 * taking the page down. A dashboard that dies because one module is missing is
 * worse than one that admits the gap.
 *
 * ─── WHAT IT DELIBERATELY DOES NOT SHOW ──────────────────────────────────
 * No revenue, profit or margin. The reference's dashboard builds those from
 * the group's shared books (db.finance / acc_entries / postSale), which Wood
 * Art's isolation rule forbids it from touching, and no decision has been made
 * about where a Wood Art invoice is recorded instead. Quoted and planned
 * values ARE shown, because those live in Wood Art's own tables and are
 * honestly labelled as quotes, not income. Inventing a revenue number from
 * quotations would be the most misleading thing this screen could do.
 */
class DashboardController extends Controller
{
    /** One screen; the key exists so the sidebar link builds like every other. */
    public const SECTIONS = [
        'overview' => 'Overview',
    ];

    public function show(string $role, string $section = 'overview'): View
    {
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        return view('woodart::dashboard', [
            'waModule' => 'dashboard',
            'waRoute'  => 'role.woodart.dashboard',
            'waIcon'   => 'speedometer2',
            'section'  => $section,
            'sections' => self::SECTIONS,
            'title'    => 'Dashboard',
            'subtitle' => 'Where every Wood Art job stands — projects, quotes, workshop, site and store.',
            'cards'    => $this->cards(),
            'projects' => $this->projectRows(),
            'alerts'   => $this->alerts(),
        ]);
    }

    /**
     * Run a read that touches one module's table, or return $fallback.
     *
     * One helper rather than nine try/catch blocks, so no read can be added
     * later that forgets the guard.
     */
    private function safely(string $table, callable $fn, mixed $fallback = 0): mixed
    {
        try {
            if (! Schema::hasTable($table)) {
                return $fallback;
            }

            return $fn();
        } catch (\Throwable $e) {
            report($e);

            return $fallback;
        }
    }

    /** One card per module, each linking to the screen that owns the detail. */
    private function cards(): array
    {
        return [
            'projects' => [
                'total'  => $this->safely('wa_projects', fn () => Project::count()),
                'active' => $this->safely('wa_projects', fn () => Project::where('stage', '!=', 'Completed')->count()),
                'value'  => $this->safely('wa_projects', fn () => (int) Project::sum('value')),
            ],
            'estimates' => [
                'total'    => $this->safely('wa_estimates', fn () => Estimate::count()),
                'pipeline' => $this->safely('wa_estimates', fn () => (int) Estimate::whereNotIn('status', ['Approved', 'Rejected'])->get()->sum('sale')),
                'approved' => $this->safely('wa_estimates', fn () => (int) Estimate::where('status', 'Approved')->get()->sum('sale')),
                'expired'  => $this->safely('wa_estimates', fn () => Estimate::all()->filter->is_expired->count()),
            ],
            'design' => [
                'total'   => $this->safely('wa_drawings', fn () => Drawing::count()),
                'waiting' => $this->safely('wa_drawings', fn () => Drawing::waiting()->count()),
                'open'    => $this->safely('wa_drawings', fn () => Drawing::open()->count()),
            ],
            'production' => [
                'total'   => $this->safely('wa_production', fn () => Job::count()),
                // Open is "not Done", so it includes Queued — not just the
                // running and blocked ones.
                'open'    => $this->safely('wa_production', fn () => Job::open()->count()),
                'running' => $this->safely('wa_production', fn () => Job::where('status', 'Running')->count()),
                'blocked' => $this->safely('wa_production', fn () => Job::where('status', 'Blocked')->count()),
                'overdue' => $this->safely('wa_production', fn () => Job::overdue()->count()),
            ],
            'installation' => [
                'total'   => $this->safely('wa_installs', fn () => Install::count()),
                'open'    => $this->safely('wa_installs', fn () => Install::open()->count()),
                'snags'   => $this->safely('wa_installs', fn () => (int) Install::all()->sum('open_snags')),
                'overdue' => $this->safely('wa_installs', fn () => Install::overdue()->count()),
            ],
            'materials' => [
                'items' => $this->safely('wa_materials', fn () => Material::count()),
                'value' => $this->safely('wa_materials', fn () => (int) Material::all()->sum('value')),
                'low'   => $this->safely('wa_materials', fn () => Material::all()->filter->is_low->count()),
            ],
            'procurement' => [
                'orders' => $this->safely('wa_purchases', fn () => Purchase::count()),
                'open'   => $this->safely('wa_purchases', fn () => Purchase::where('status', '!=', 'Received')->count()),
                'value'  => $this->safely('wa_purchases', fn () => (int) Purchase::sum('amount')),
            ],
            'scope' => [
                'phases'   => $this->safely('wa_phases', fn () => Phase::count()),
                'active'   => $this->safely('wa_phases', fn () => Phase::where('status', 'Active')->count()),
                'complete' => $this->safely('wa_phases', fn () => Phase::where('status', 'Complete')->count()),
            ],
            'clients' => [
                'total' => $this->safely('wa_clients', fn () => Client::count()),
            ],
        ];
    }

    /**
     * Every live project with what each module knows about it.
     *
     * Joined on the project's ext_id STRING, the same way the rest of the suite
     * joins — never a foreign key.
     */
    private function projectRows(): array
    {
        $projects = $this->safely('wa_projects', fn () => Project::orderBy('ext_id')->get(), collect());

        if ($projects->isEmpty()) {
            return [];
        }

        $codes = $projects->pluck('ext_id')->all();

        $jobs = $this->safely('wa_production',
            fn () => Job::whereIn('project', $codes)->get()->groupBy('project'), collect());
        $installs = $this->safely('wa_installs',
            fn () => Install::whereIn('project', $codes)->get()->groupBy('project'), collect());
        $drawings = $this->safely('wa_drawings',
            fn () => Drawing::whereIn('project', $codes)->get()->groupBy('project'), collect());
        $phases = $this->safely('wa_phases',
            fn () => Phase::whereIn('project', $codes)->get()->groupBy('project'), collect());

        return $projects->map(function (Project $p) use ($jobs, $installs, $drawings, $phases) {
            $myJobs     = $jobs->get($p->ext_id, collect());
            $myInstalls = $installs->get($p->ext_id, collect());
            $myDrawings = $drawings->get($p->ext_id, collect());
            $myPhases   = $phases->get($p->ext_id, collect());

            return [
                'project'       => $p,
                'jobsOpen'      => $myJobs->filter->is_open->count(),
                'jobsTotal'     => $myJobs->count(),
                'snags'         => (int) $myInstalls->sum('open_snags'),
                'installsOpen'  => $myInstalls->filter->is_open->count(),
                'drawingsOpen'  => $myDrawings->filter->is_open->count(),
                'drawingsTotal' => $myDrawings->count(),
                'phasesDone'    => $myPhases->where('status', 'Complete')->count(),
                'phasesTotal'   => $myPhases->count(),
            ];
        })->all();
    }

    /**
     * What needs somebody today, worst first.
     *
     * Only conditions a person can act on — a blocked job, a late site visit, a
     * drawing the client has sat on. Nothing here is decorative: if the list is
     * empty, that is a real answer, not a missing feature.
     */
    private function alerts(): array
    {
        $out = [];

        $blocked = $this->safely('wa_production', fn () => Job::where('status', 'Blocked')->count());
        if ($blocked > 0) {
            $out[] = ['icon' => 'exclamation-octagon', 'tone' => 'bad', 'count' => $blocked,
                      'text' => \Illuminate\Support\Str::plural('job', $blocked) . ' blocked on the workshop floor',
                      'route' => 'role.woodart.production', 'section' => 'board'];
        }

        $lateJobs = $this->safely('wa_production', fn () => Job::overdue()->count());
        if ($lateJobs > 0) {
            $out[] = ['icon' => 'alarm', 'tone' => 'bad', 'count' => $lateJobs,
                      'text' => \Illuminate\Support\Str::plural('job', $lateJobs) . ' past the due date',
                      'route' => 'role.woodart.production', 'section' => 'jobs'];
        }

        $snags = $this->safely('wa_installs', fn () => (int) Install::all()->sum('open_snags'));
        if ($snags > 0) {
            $out[] = ['icon' => 'exclamation-diamond', 'tone' => 'bad', 'count' => $snags,
                      'text' => 'open ' . \Illuminate\Support\Str::plural('snag', $snags) . ' stopping handover',
                      'route' => 'role.woodart.installation', 'section' => 'snags'];
        }

        $lateVisits = $this->safely('wa_installs', fn () => Install::overdue()->count());
        if ($lateVisits > 0) {
            $out[] = ['icon' => 'truck', 'tone' => 'warn', 'count' => $lateVisits,
                      'text' => 'site ' . \Illuminate\Support\Str::plural('visit', $lateVisits) . ' past their date',
                      'route' => 'role.woodart.installation', 'section' => 'schedule'];
        }

        $waiting = $this->safely('wa_drawings', fn () => Drawing::waiting()->count());
        if ($waiting > 0) {
            $out[] = ['icon' => 'send', 'tone' => 'warn', 'count' => $waiting,
                      'text' => \Illuminate\Support\Str::plural('drawing', $waiting) . ' waiting on client approval',
                      'route' => 'role.woodart.design', 'section' => 'approvals'];
        }

        $low = $this->safely('wa_materials', fn () => Material::all()->filter->is_low->count());
        if ($low > 0) {
            $out[] = ['icon' => 'boxes', 'tone' => 'warn', 'count' => $low,
                      'text' => \Illuminate\Support\Str::plural('material', $low) . ' at or below the reorder level',
                      'route' => 'role.woodart.materials', 'section' => 'reorder'];
        }

        $expired = $this->safely('wa_estimates', fn () => Estimate::all()->filter->is_expired->count());
        if ($expired > 0) {
            $out[] = ['icon' => 'calendar-x', 'tone' => 'warn', 'count' => $expired,
                      'text' => \Illuminate\Support\Str::plural('quotation', $expired) . ' past validity, still unanswered',
                      'route' => 'role.woodart.estimates', 'section' => 'quotations'];
        }

        return $out;
    }
}
