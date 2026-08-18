<?php

namespace Modules\WoodArt\Modules\Materials;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Materials\Models\Material;

/**
 * Wood Art · Materials — everything this module owns lives in this folder.
 * Mirrors the reference's companies/woodart/modules/materials/.
 *
 * Its own framing: Stock is the material register; Movements is the ledger that
 * explains why every stock number is what it is; Reorder is what to buy and
 * roughly what it will cost; Valuation is where the money is sitting.
 */
class MaterialsController extends Controller
{
    /** Tab labels — TAB_COPY[key][0] and module.json agree here. */
    public const SECTIONS = [
        'stock'     => 'Stock',
        'movements' => 'Movements',
        'reorder'   => 'Reorder',
        'valuation' => 'Valuation',
    ];

    /** Verbatim from the module's TAB_COPY (view.js:467-472). */
    private const SUBTITLES = [
        'stock'     => 'Wood, laminates, hardware and finishes — live quantities and what each is worth.',
        'movements' => 'The stock ledger — every receipt, issue, adjustment and wastage, and where it sits.',
        'reorder'   => 'Everything at or below its reorder level, with the refill quantity and cost.',
        'valuation' => 'Where the money is sitting — stock value by category and by item.',
    ];

    /** See ProjectsController::show() for why $role must be declared. */
    public function show(Request $request, string $role, string $section = 'stock'): View
    {
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        $materials = $this->materials();

        // Search — same grammar as the Projects board: a GET param, so the
        // result is bookmarkable and needs no script inside [data-wa-view].
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $materials = $materials->filter(function (Material $m) use ($needle) {
                return str_contains(mb_strtolower(implode(' ', [
                    $m->name, $m->category, $m->supplier, $m->ext_id,
                ])), $needle);
            })->values();
        }

        return view('woodart::materials', [
            'waModule'   => 'materials',
            'waRoute'    => 'role.woodart.materials',
            'waIcon'     => 'boxes',
            'section'    => $section,
            'sections'   => self::SECTIONS,
            'title'      => self::SECTIONS[$section],
            'subtitle'   => self::SUBTITLES[$section],
            'materials'  => $materials,
            'stats'      => $this->stats($materials),
            'categories' => $this->byCategory($materials),
            'q'          => $q,
        ]);
    }

    public function create(string $role): View
    {
        return view('woodart::material-form', $this->formData(null));
    }

    public function store(Request $request, string $role): RedirectResponse
    {
        $material = new Material($this->validated($request) + [
            'ext_id'     => $this->nextExtId(),
            'created_on' => now()->toDateString(),
        ]);
        $material->save();

        return redirect()
            ->route('role.woodart.materials', ['role' => $role, 'section' => 'stock'])
            ->with('wa_status', "{$material->ext_id} — {$material->name} was added.");
    }

    public function edit(string $role, Material $material): View
    {
        return view('woodart::material-form', $this->formData($material));
    }

    public function update(Request $request, string $role, Material $material): RedirectResponse
    {
        $material->fill($this->validated($request))->save();

        return redirect()
            ->route('role.woodart.materials', ['role' => $role, 'section' => 'stock'])
            ->with('wa_status', "{$material->ext_id} — {$material->name} was updated.");
    }

    public function confirmDelete(string $role, Material $material): View
    {
        return view('woodart::material-delete', [
            'waModule' => 'materials',
            'waRoute'  => 'role.woodart.materials',
            'waIcon'   => 'boxes',
            'section'  => 'stock',
            'sections' => self::SECTIONS,
            'title'    => 'Remove Material',
            'subtitle' => "Check this is the right item before removing {$material->ext_id}.",
            'material' => $material,
        ]);
    }

    /** Soft delete — recoverable, and the code is never reused. */
    public function destroy(string $role, Material $material): RedirectResponse
    {
        $label = "{$material->ext_id} — {$material->name}";
        $material->delete();

        return redirect()
            ->route('role.woodart.materials', ['role' => $role, 'section' => 'stock'])
            ->with('wa_status', "{$label} was removed.");
    }

    /** One rule set for create and update. */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:160'],
            'category'  => ['required', Rule::in(Material::CATEGORIES)],
            'unit'      => ['required', Rule::in(Material::UNITS)],
            // Signed on purpose: a negative is a real state worth recording,
            // not an input error to reject.
            //
            // `decimal:0,3` is load-bearing, not decoration. `numeric` bounds
            // MAGNITUDE, not SCALE — without the scale cap, 12.9996 passes
            // validation and MySQL silently rounds it to 13.000 with a Note
            // that STRICT_TRANS_TABLES does not escalate. `numeric` is kept
            // alongside so non-numeric input gets "must be a number" rather
            // than the confusing "must have 0-3 decimal places".
            'stock'     => ['nullable', 'numeric', 'decimal:0,3', 'min:-99999999999.999', 'max:99999999999.999'],
            'reorder'   => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:99999999999.999'],
            // Money stays whole taka this pass — two `=== 0` tests elsewhere
            // depend on the integer cast.
            'unit_cost' => ['nullable', 'integer', 'min:0'],
            'supplier'  => ['nullable', 'string', 'max:160'],
        ], [
            'stock.decimal'   => 'Stock can have at most 3 decimal places (e.g. 12.5 or 12.125).',
            'reorder.decimal' => 'The reorder level can have at most 3 decimal places.',
        ]);

        foreach (['stock', 'reorder', 'unit_cost'] as $n) {
            $data[$n] ??= 0;
        }

        return $data;
    }

    private function formData(?Material $material): array
    {
        return [
            'waModule'   => 'materials',
            'waRoute'    => 'role.woodart.materials',
            'waIcon'     => 'boxes',
            'section'    => 'stock',
            'sections'   => self::SECTIONS,
            'title'      => $material ? "Edit {$material->ext_id}" : 'New Material',
            'subtitle'   => $material
                ? 'Change what has moved on. The material code cannot change.'
                : 'Add an item to the store. Only the name is required.',
            'categories' => Material::CATEGORIES,
            'units'      => Material::UNITS,
            'material'   => $material,
            'nextExt'    => $material?->ext_id ?? $this->nextExtId(),
        ];
    }

    /** The next MAT-nnn code, from the highest existing number. */
    private function nextExtId(): string
    {
        try {
            if (! Schema::hasTable('wa_materials')) {
                return 'MAT-001';
            }

            $max = Material::withTrashed()
                ->selectRaw("MAX(CAST(SUBSTRING(ext_id, 5) AS UNSIGNED)) AS n")
                ->where('ext_id', 'like', 'MAT-%')
                ->value('n');

            return 'MAT-' . str_pad((string) (((int) $max) + 1), 3, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            report($e);

            return 'MAT-001';
        }
    }

    /**
     * The register, cheapest lookup first: name order.
     *
     * Fault-tolerant like every other Wood Art register — a server without this
     * module's migration degrades to the empty state instead of throwing.
     */
    private function materials(): Collection
    {
        try {
            if (! Schema::hasTable('wa_materials')) {
                return collect();
            }

            return Material::query()->orderBy('name')->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /** Every figure the four screens need, derived — none of it stored. */
    private function stats(Collection $materials): array
    {
        $low = $materials->filter->is_low;

        return [
            'items'      => $materials->count(),
            'value'      => (int) $materials->sum('value'),
            'low'        => $low->count(),
            'categories' => $materials->pluck('category')->unique()->count(),
            'suppliers'  => $materials->pluck('supplier')->filter()->unique()->count(),
            'dead'       => $materials->filter->is_dead->count(),
            'avg'        => $materials->count() ? (int) round($materials->sum('value') / $materials->count()) : 0,
            // Reorder screen
            // A QUANTITY, not money: a real shortfall of 0.5 + 0.25 must not
            // render as 0 while material is genuinely missing.
            'short'      => (float) $low->sum('short'),
            'refill'     => (int) $low->sum('refill_cost'),
            'lowSuppliers' => $low->pluck('supplier')->filter()->unique()->count(),
        ];
    }

    /** Stock value per category, for the valuation screen. */
    private function byCategory(Collection $materials): array
    {
        $total = max(1, (int) $materials->sum('value'));

        return collect(Material::CATEGORIES)
            ->map(function (string $c) use ($materials, $total) {
                $in = $materials->where('category', $c);

                return [
                    'label' => $c,
                    'items' => $in->count(),
                    'value' => $v = (int) $in->sum('value'),
                    'share' => (int) round($v / $total * 100),
                ];
            })
            ->filter(fn (array $r) => $r['items'] > 0)
            ->sortByDesc('value')
            ->values()
            ->all();
    }
}
