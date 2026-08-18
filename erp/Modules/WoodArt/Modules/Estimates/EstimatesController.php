<?php

namespace Modules\WoodArt\Modules\Estimates;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Clients\Models\Client;
use Modules\WoodArt\Modules\Estimates\Models\Estimate;
use Modules\WoodArt\Modules\Materials\Models\Material;
use Modules\WoodArt\Modules\Projects\Models\Project;

/**
 * Wood Art · Estimates & BOQ — everything this module owns lives in this
 * folder. Mirrors the reference's companies/woodart/modules/estimates/.
 *
 * Its own framing: Quotations is what was quoted and what the client said;
 * Bill of Materials sets every quoted line against TODAY's register price;
 * Costing is which quotations actually make money.
 *
 * ─── OWNERSHIP ───────────────────────────────────────────────────────────
 * `wa_estimates` belongs to the PROJECTS module, which holds its migration.
 * This module owns the screens and the write path. See Estimate for why, and
 * do not add a migration for that table here.
 *
 * ─── THE PRICE-DRIFT COMPARISON ──────────────────────────────────────────
 * The BOQ and Costing screens set each quoted line against what the SAME
 * material costs in the register today. A quote written months ago against
 * plywood that has since gone up is the commonest way an interiors job loses
 * its margin before a sheet is cut, and nothing else in the suite shows it.
 * Materials is read, never written.
 *
 * ─── WHAT THIS MODULE DOES NOT DO ────────────────────────────────────────
 * It does not bill, invoice or post to any ledger. An approved estimate is
 * still only a quotation; turning one into money is Projects' job, and a
 * second path here would double-count revenue.
 */
class EstimatesController extends Controller
{
    /** Tab labels — TAB_COPY[key][0] and module.json agree here. */
    public const SECTIONS = [
        'quotations' => 'Quotations',
        'boq'        => 'Bill of Materials',
        'costing'    => 'Costing',
    ];

    /** Verbatim from the module's TAB_COPY (view.js:308-312). */
    private const SUBTITLES = [
        'quotations' => 'What Woodart has quoted, and what the client said.',
        'boq'        => "Every quoted line, against today's register price.",
        'costing'    => 'Which quotations actually make money.',
    ];

    /** How many empty rows the line editor offers on top of what exists. */
    private const BLANK_ROWS = 5;

    /** See ProjectsController::show() for why $role must be declared. */
    public function show(Request $request, string $role, string $section = 'quotations'): View
    {
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        $estimates = $this->estimates();
        $live      = $this->livePrices();

        // Search — a GET param, same grammar as every other Wood Art register.
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $estimates = $estimates->filter(function (Estimate $e) use ($needle) {
                $inLines = implode(' ', array_map(
                    fn (array $l) => ($l['item'] ?? '') . ' ' . ($l['code'] ?? ''),
                    $e->line_rows
                ));

                return str_contains(mb_strtolower(implode(' ', [
                    $e->title, $e->ext_id, $e->client, $e->project_ext, $e->status, $inLines,
                ])), $needle);
            })->values();
        }

        return view('woodart::estimates', [
            'waModule'  => 'estimates',
            'waRoute'   => 'role.woodart.estimates',
            'waIcon'    => 'calculator-fill',
            'section'   => $section,
            'sections'  => self::SECTIONS,
            'title'     => self::SECTIONS[$section],
            'subtitle'  => self::SUBTITLES[$section],
            'estimates' => $estimates->sortByDesc->sale->values(),
            // Needed so the register can flag a quotation whose project has
            // been removed, the same way every other Wood Art register does.
            'projectNames' => $this->projectNames(),
            'stats'     => $this->summary($estimates),
            'lines'     => $this->flatLines($estimates, $live),
            'demand'    => $this->demand($estimates, $live),
            'costing'   => $this->costing($estimates, $live),
            'q'         => $q,
        ]);
    }

    public function create(string $role): View
    {
        return view('woodart::estimate-form', $this->formData(null));
    }

    public function store(Request $request, string $role): RedirectResponse
    {
        $estimate = new Estimate($this->validated($request) + [
            'ext_id'     => $this->nextExtId(),
            'created_on' => now()->toDateString(),
        ]);
        $estimate->save();

        return redirect()
            ->route('role.woodart.estimates.edit', ['role' => $role, 'estimate' => $estimate])
            ->with('wa_status', "{$estimate->ext_id} was created — add its bill of quantities below.");
    }

    public function edit(string $role, Estimate $estimate): View
    {
        return view('woodart::estimate-form', $this->formData($estimate));
    }

    public function update(Request $request, string $role, Estimate $estimate): RedirectResponse
    {
        $estimate->fill($this->validated($request))->save();

        return redirect()
            ->route('role.woodart.estimates', ['role' => $role, 'section' => 'quotations'])
            ->with('wa_status', "{$estimate->ext_id} — {$estimate->title} was updated.");
    }

    public function confirmDelete(string $role, Estimate $estimate): View
    {
        return view('woodart::estimate-delete', [
            'waModule' => 'estimates',
            'waRoute'  => 'role.woodart.estimates',
            'waIcon'   => 'calculator-fill',
            'section'  => 'quotations',
            'sections' => self::SECTIONS,
            'title'    => 'Remove Estimate',
            'subtitle' => "Check this is the right quotation before removing {$estimate->ext_id}.",
            'estimate' => $estimate,
        ]);
    }

    /** Soft delete — recoverable, and the code is never reused. */
    public function destroy(string $role, Estimate $estimate): RedirectResponse
    {
        $label = "{$estimate->ext_id} — {$estimate->title}";
        $estimate->delete();

        return redirect()
            ->route('role.woodart.estimates', ['role' => $role, 'section' => 'quotations'])
            ->with('wa_status', "{$label} was removed.");
    }

    /**
     * One rule set for create and update.
     *
     * THE LINES are submitted as an array of rows. A row whose Item is blank is
     * DROPPED, which is how a line is deleted without a script inside
     * [data-wa-view]. Every surviving row is round-tripped through
     * Estimate::normaliseLine() so `code`, `kind` and `unit` — which the live
     * bill of quantities carries and the reference's own seam forgets — are
     * preserved rather than silently erased.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'client'      => ['nullable', 'string', 'max:160'],
            'project_ext' => ['nullable', 'string', 'max:40'],
            'status'      => ['required', Rule::in(Estimate::STATUSES)],
            'valid_till'  => ['nullable', 'date'],

            'lines'              => ['nullable', 'array'],
            'lines.*.code'       => ['nullable', 'string', 'max:120'],
            'lines.*.item'       => ['nullable', 'string', 'max:200'],
            'lines.*.kind'       => ['nullable', Rule::in(Estimate::KINDS)],
            'lines.*.unit'       => ['nullable', 'string', 'max:20'],
            'lines.*.qty'        => ['nullable', 'numeric', 'min:0'],
            'lines.*.unitCost'   => ['nullable', 'numeric', 'min:0'],
            'lines.*.unitSale'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $rows = [];
        foreach ($data['lines'] ?? [] as $l) {
            if (trim((string) ($l['item'] ?? '')) === '') {
                continue;   // a blank Item means "remove this line"
            }
            $rows[] = Estimate::normaliseLine($l);
        }

        $data['lines'] = $rows;

        return $data;
    }

    private function formData(?Estimate $estimate): array
    {
        $rows = $estimate?->line_rows ?? [];

        // Existing lines plus a few blanks. Adding more than BLANK_ROWS at once
        // means saving and reopening — the honest cost of having no script
        // inside the swapped region.
        for ($i = 0; $i < self::BLANK_ROWS; $i++) {
            $rows[] = ['code' => '', 'item' => '', 'kind' => 'material', 'unit' => 'pcs',
                       'qty' => '', 'unitCost' => '', 'unitSale' => ''];
        }

        return [
            'waModule'  => 'estimates',
            'waRoute'   => 'role.woodart.estimates',
            'waIcon'    => 'calculator-fill',
            'section'   => 'quotations',
            'sections'  => self::SECTIONS,
            'title'     => $estimate ? "Edit {$estimate->ext_id}" : 'New Estimate',
            'subtitle'  => $estimate
                ? 'Change the quotation and its bill of quantities. The code cannot change.'
                : 'Start a quotation. Only the title is required — the bill of quantities can follow.',
            'statuses'  => Estimate::STATUSES,
            'kinds'     => Estimate::KINDS,
            'units'     => Estimate::UNITS,
            'projects'  => $this->projectOptions(),
            'clients'   => $this->clientOptions(),
            'materials' => $this->materialOptions(),
            'estimate'  => $estimate,
            'rows'      => $rows,
            'nextExt'   => $estimate?->ext_id ?? $this->nextExtId(),
        ];
    }

    /** The next EST-nnn code, from the highest existing number. */
    private function nextExtId(): string
    {
        try {
            if (! Schema::hasTable('wa_estimates')) {
                return 'EST-101';
            }

            $max = Estimate::withTrashed()
                ->selectRaw("MAX(CAST(SUBSTRING(ext_id, 5) AS UNSIGNED)) AS n")
                ->where('ext_id', 'like', 'EST-%')
                ->value('n');

            // The live register starts at EST-101, so a first estimate on an
            // empty table continues that series rather than restarting at 001.
            return 'EST-' . str_pad((string) (max(100, (int) $max) + 1), 3, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            report($e);

            return 'EST-101';
        }
    }

    /**
     * Fault-tolerant like every other Wood Art register — a server without the
     * Projects migration degrades to the empty state instead of throwing.
     */
    private function estimates(): Collection
    {
        try {
            if (! Schema::hasTable('wa_estimates')) {
                return collect();
            }

            return Estimate::query()->orderBy('ext_id')->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Material name => today's unit cost, for the drift comparison.
     *
     * Reads the Materials module's table and NEVER writes it. Wrapped so
     * Estimates still serves where Materials is not migrated — the drift
     * columns then simply read "not in the register".
     */
    private function livePrices(): array
    {
        try {
            if (! Schema::hasTable('wa_materials')) {
                return [];
            }

            return Material::query()->pluck('unit_cost', 'name')
                ->map(fn ($v) => (int) $v)->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** The header figures every screen quotes. One calculation, one truth. */
    private function summary(Collection $estimates): array
    {
        $approved = $estimates->where('status', 'Approved');

        return [
            'count'    => $estimates->count(),
            // Anything still in play: quoted but not yet answered.
            'pipeline' => (int) $estimates->whereNotIn('status', ['Approved', 'Rejected'])->sum('sale'),
            'approved' => (int) $approved->sum('sale'),
            'expired'  => $estimates->filter->is_expired->count(),
            'winRate'  => $estimates->count()
                ? (int) round($approved->count() / $estimates->count() * 100)
                : 0,
            'cost'     => (int) $estimates->sum('cost'),
            'sale'     => (int) $estimates->sum('sale'),
            'margin'   => (int) $estimates->sum('margin'),
            'lines'    => (int) $estimates->sum('line_count'),
        ];
    }

    /**
     * Every BOQ line across every estimate, flattened, dearest first.
     *
     * `live` is what the same item costs in the register today and `drift` is
     * the gap. Both are NULL — not zero — when the item is not in the register
     * at all: "we do not stock this" and "it costs nothing" are different
     * facts, and collapsing them would invent a saving that does not exist.
     */
    private function flatLines(Collection $estimates, array $live): array
    {
        $out = [];
        foreach ($estimates as $e) {
            foreach ($e->line_rows as $idx => $l) {
                $qty   = (float) ($l['qty'] ?? 0);
                $cost  = (float) ($l['unitCost'] ?? 0);
                $sale  = (float) ($l['unitSale'] ?? 0);
                $known = \array_key_exists($l['item'] ?? '', $live);

                $out[] = [
                    'key'      => $e->ext_id . '#' . $idx,
                    'estimate' => $e->ext_id,
                    'project'  => $e->project_ext,
                    'client'   => $e->client,
                    'status'   => $e->status,
                    'code'     => $l['code'] ?? '',
                    'item'     => $l['item'] ?? '',
                    'kind'     => $l['kind'] ?? 'material',
                    'unit'     => $l['unit'] ?? '',
                    'qty'      => $qty,
                    'unitCost' => (int) $cost,
                    'unitSale' => (int) $sale,
                    'lineCost' => (int) ($qty * $cost),
                    'lineSale' => (int) ($qty * $sale),
                    'margin'   => (int) ($qty * ($sale - $cost)),
                    'live'     => $known ? $live[$l['item']] : null,
                    'drift'    => $known ? $live[$l['item']] - (int) $cost : null,
                    'known'    => $known,
                ];
            }
        }

        usort($out, fn (array $a, array $b) => $b['lineSale'] <=> $a['lineSale']);

        return $out;
    }

    /**
     * Totals per material across every live BOQ — what the pipeline will
     * consume. A REJECTED estimate is excluded: it is not future demand, and
     * counting it would have the workshop buying timber for a job we lost.
     */
    private function demand(Collection $estimates, array $live): array
    {
        $bag = [];
        foreach ($this->flatLines($estimates, $live) as $l) {
            if ($l['status'] === 'Rejected' || $l['kind'] !== 'material') {
                continue;
            }

            $k = $l['item'];
            $bag[$k] ??= ['item' => $k, 'unit' => $l['unit'], 'qty' => 0,
                          'cost' => 0, 'estimates' => [], 'known' => $l['known']];
            $bag[$k]['qty']  += $l['qty'];
            $bag[$k]['cost'] += $l['lineCost'];
            $bag[$k]['estimates'][$l['estimate']] = true;
        }

        $rows = array_map(function (array $b) {
            $b['estimates'] = \count($b['estimates']);

            return $b;
        }, array_values($bag));

        usort($rows, fn (array $a, array $b) => $b['cost'] <=> $a['cost']);

        return $rows;
    }

    /**
     * Per-estimate margin, WORST FIRST — the quotations worth arguing about.
     *
     * `marginToday` is what the margin becomes if today's material prices hold:
     * only UPWARD drift is counted, because a line that got cheaper is a
     * windfall nobody should bank in advance.
     */
    private function costing(Collection $estimates, array $live): array
    {
        $byEstimate = [];
        foreach ($this->flatLines($estimates, $live) as $l) {
            if ($l['drift'] === null || $l['drift'] <= 0) {
                continue;
            }
            $byEstimate[$l['estimate']]['n'] ??= 0;
            $byEstimate[$l['estimate']]['v'] ??= 0;
            $byEstimate[$l['estimate']]['n']++;
            $byEstimate[$l['estimate']]['v'] += (int) ($l['drift'] * $l['qty']);
        }

        $rows = $estimates->map(function (Estimate $e) use ($byEstimate) {
            $drifted    = $byEstimate[$e->ext_id]['n'] ?? 0;
            $driftValue = $byEstimate[$e->ext_id]['v'] ?? 0;

            return [
                'ext_id'      => $e->ext_id,
                'title'       => $e->title,
                'client'      => $e->client,
                'project'     => $e->project_ext,
                'status'      => $e->status,
                'lineCount'   => $e->line_count,
                'cost'        => $e->cost,
                'sale'        => $e->sale,
                'margin'      => $e->margin,
                'marginPct'   => $e->margin_pct,
                'drifted'     => $drifted,
                'driftValue'  => $driftValue,
                'marginToday' => $e->margin - $driftValue,
                'pctToday'    => $e->sale > 0
                    ? (int) round(($e->margin - $driftValue) / $e->sale * 100)
                    : 0,
            ];
        })->sortBy('marginPct')->values()->all();

        return $rows;
    }

    /* ── option lists, all read-only over other modules' tables ───────────── */

    /**
     * Project ext_id => name. Reads the Projects module's table, never writes
     * it, and degrades to an empty map where that table is absent.
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

    private function projectOptions(): array
    {
        $out = [];
        foreach ($this->projectNames() as $ext => $name) {
            $out[$ext] = $ext . ' · ' . $name;
        }
        ksort($out);

        return $out;
    }

    private function clientOptions(): array
    {
        try {
            if (! Schema::hasTable('wa_clients')) {
                return [];
            }

            return Client::query()->orderBy('name')->pluck('name')->unique()->values()->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    private function materialOptions(): array
    {
        try {
            if (! Schema::hasTable('wa_materials')) {
                return [];
            }

            return Material::query()->orderBy('name')->pluck('name')->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
