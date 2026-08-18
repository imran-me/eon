<?php

namespace Modules\WoodArt\Modules\Procurement;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Procurement\Models\Purchase;
use Modules\WoodArt\Modules\Procurement\Models\Vendor;
use Modules\WoodArt\Modules\Projects\Models\Project;

/**
 * Wood Art · Procurement — everything this module owns lives in this folder.
 * Mirrors the reference's companies/woodart/modules/procurement/.
 *
 * Its own framing: Purchase Orders is what has been ordered and what is still
 * owed; Vendors is who Woodart buys board, laminate, hardware, finishes and
 * fabric from; Spend is where the procurement money actually goes.
 */
class ProcurementController extends Controller
{
    /** Tab labels — TAB_COPY[key][0] and module.json agree here. */
    public const SECTIONS = [
        'orders'  => 'Purchase Orders',
        'vendors' => 'Vendors',
        'spend'   => 'Spend',
    ];

    /** Verbatim from the module's TAB_COPY (view.js:566-570). */
    private const SUBTITLES = [
        'orders'  => 'Every order raised on a vendor — what is on its way and what is still owed.',
        'vendors' => 'Who Woodart buys board, laminate, hardware, finishes and fabric from.',
        'spend'   => 'Where the procurement money goes — by category and by vendor.',
    ];

    /** See ProjectsController::show() for why $role must be declared. */
    public function show(Request $request, string $role, string $section = 'orders'): View
    {
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        $orders  = $this->orders();
        $vendors = $this->vendors();
        $spend   = $this->spend($orders, $vendors);

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle  = mb_strtolower($q);
            $orders  = $orders->filter(fn (Purchase $p) => str_contains(
                mb_strtolower(implode(' ', [$p->ext_id, $p->supplier, $p->project, $p->status])), $needle))->values();
            $vendors = $vendors->filter(fn (Vendor $v) => str_contains(
                mb_strtolower(implode(' ', [$v->ext_id, $v->name, $v->category, $v->area])), $needle))->values();
        }

        return view('woodart::procurement', [
            'waModule'   => 'procurement',
            'waRoute'    => 'role.woodart.procurement',
            'waIcon'     => 'cart-fill',
            'section'    => $section,
            'sections'   => self::SECTIONS,
            'title'      => self::SECTIONS[$section],
            'subtitle'   => self::SUBTITLES[$section],
            'orders'     => $orders,
            'vendors'    => $vendors,
            'spend'      => $spend,
            'stats'      => $this->stats($orders, $vendors, $spend),
            'statuses'   => Purchase::STATUSES,
            'q'          => $q,
        ]);
    }

    /* ── purchase orders ──────────────────────────────────────────────────── */

    public function createOrder(string $role): View
    {
        return view('woodart::purchase-form', $this->orderForm(null));
    }

    public function storeOrder(Request $request, string $role): RedirectResponse
    {
        $order = new Purchase($this->validatedOrder($request) + [
            'ext_id'     => $this->nextExtId('wa_purchases', 'WPO', 101),
            'created_on' => now()->toDateString(),
        ]);
        $order->save();

        return redirect()
            ->route('role.woodart.procurement', ['role' => $role, 'section' => 'orders'])
            ->with('wa_status', "{$order->ext_id} — {$order->supplier} was raised.");
    }

    public function editOrder(string $role, Purchase $order): View
    {
        return view('woodart::purchase-form', $this->orderForm($order));
    }

    public function updateOrder(Request $request, string $role, Purchase $order): RedirectResponse
    {
        $order->fill($this->validatedOrder($request))->save();

        return redirect()
            ->route('role.woodart.procurement', ['role' => $role, 'section' => 'orders'])
            ->with('wa_status', "{$order->ext_id} was updated.");
    }

    public function confirmDeleteOrder(string $role, Purchase $order): View
    {
        return view('woodart::purchase-delete', $this->orderForm($order) + [
            'title'    => 'Remove Purchase Order',
            'subtitle' => "Check this is the right order before removing {$order->ext_id}.",
        ]);
    }

    public function destroyOrder(string $role, Purchase $order): RedirectResponse
    {
        $label = $order->ext_id;
        $order->delete();

        return redirect()
            ->route('role.woodart.procurement', ['role' => $role, 'section' => 'orders'])
            ->with('wa_status', "{$label} was removed.");
    }

    private function validatedOrder(Request $request): array
    {
        $data = $request->validate([
            'supplier' => ['required', 'string', 'max:160'],
            // Nullable means GENERAL STOCK, not "unknown" — see the migration.
            'project'  => ['nullable', 'string', 'max:40'],
            'items'    => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'amount'   => ['nullable', 'integer', 'min:0'],
            'status'   => ['required', Rule::in(Purchase::STATUSES)],
            'date'     => ['nullable', 'date'],
        ]);

        foreach (['items', 'amount'] as $n) {
            $data[$n] ??= 0;
        }

        return $data;
    }

    private function orderForm(?Purchase $order): array
    {
        return [
            'waModule' => 'procurement',
            'waRoute'  => 'role.woodart.procurement',
            'waIcon'   => 'cart-fill',
            'section'  => 'orders',
            'sections' => self::SECTIONS,
            'title'    => $order ? "Edit {$order->ext_id}" : 'New Purchase Order',
            'subtitle' => $order
                ? 'Change what has moved on. The order code cannot change.'
                : 'Raise an order on a vendor. Leave the project blank when buying for general stock.',
            'order'    => $order,
            'statuses' => Purchase::STATUSES,
            'vendors'  => $this->vendors(),
            'projects' => $this->projectCodes(),
            'nextExt'  => $order?->ext_id ?? $this->nextExtId('wa_purchases', 'WPO', 101),
        ];
    }

    /** Project codes for the order form's picker — read-only, never written. */
    private function projectCodes(): Collection
    {
        try {
            return Schema::hasTable('wa_projects')
                ? Project::query()->orderBy('ext_id')->get(['ext_id', 'name'])
                : collect();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /* ── vendors ──────────────────────────────────────────────────────────── */

    public function createVendor(string $role): View
    {
        return view('woodart::vendor-form', $this->vendorForm(null));
    }

    public function storeVendor(Request $request, string $role): RedirectResponse
    {
        $vendor = new Vendor($this->validatedVendor($request) + [
            'ext_id'     => $this->nextExtId('wa_vendors', 'WAV', 101),
            'created_on' => now()->toDateString(),
        ]);
        $vendor->save();

        return redirect()
            ->route('role.woodart.procurement', ['role' => $role, 'section' => 'vendors'])
            ->with('wa_status', "{$vendor->ext_id} — {$vendor->name} was added.");
    }

    public function editVendor(string $role, Vendor $vendor): View
    {
        return view('woodart::vendor-form', $this->vendorForm($vendor));
    }

    public function updateVendor(Request $request, string $role, Vendor $vendor): RedirectResponse
    {
        $before = $vendor->name;
        $vendor->fill($this->validatedVendor($request))->save();

        $note = $vendor->name !== $before
            ? " Orders still naming “{$before}” will no longer roll up to this vendor."
            : '';

        return redirect()
            ->route('role.woodart.procurement', ['role' => $role, 'section' => 'vendors'])
            ->with('wa_status', "{$vendor->ext_id} — {$vendor->name} was updated.{$note}");
    }

    public function confirmDeleteVendor(string $role, Vendor $vendor): View
    {
        // Compute this vendor's own figures directly. Reusing spend() here was
        // wrong: it sorts by value and mixes in every unregistered supplier, so
        // first() returned whichever row happened to be largest rather than
        // this vendor — and the "orders will be orphaned" warning silently
        // never appeared.
        $linked = $this->vendorFigures($vendor);

        return view('woodart::vendor-delete', $this->vendorForm($vendor) + [
            'title'    => 'Remove Vendor',
            'subtitle' => "Check what is attached before removing {$vendor->ext_id}.",
            'linked'   => $linked,
        ]);
    }

    public function destroyVendor(string $role, Vendor $vendor): RedirectResponse
    {
        $label = "{$vendor->ext_id} — {$vendor->name}";
        $vendor->delete();

        return redirect()
            ->route('role.woodart.procurement', ['role' => $role, 'section' => 'vendors'])
            ->with('wa_status', "{$label} was removed. Their orders are untouched.");
    }

    private function validatedVendor(Request $request): array
    {
        return $request->validate([
            'name'     => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(Vendor::CATEGORIES)],
            'contact'  => ['nullable', 'string', 'max:160'],
            'phone'    => ['nullable', 'string', 'max:40'],
            'email'    => ['nullable', 'email', 'max:160'],
            'area'     => ['nullable', 'string', 'max:120'],
            'terms'    => ['nullable', Rule::in(Vendor::TERMS)],
            'since'    => ['nullable', 'date'],
        ]);
    }

    private function vendorForm(?Vendor $vendor): array
    {
        return [
            'waModule'   => 'procurement',
            'waRoute'    => 'role.woodart.procurement',
            'waIcon'     => 'cart-fill',
            'section'    => 'vendors',
            'sections'   => self::SECTIONS,
            'title'      => $vendor ? "Edit {$vendor->ext_id}" : 'New Vendor',
            'subtitle'   => $vendor
                ? 'Change what has moved on. The vendor code cannot change.'
                : 'Add a supplier of board, laminate, hardware, finishes or fabric.',
            'vendor'     => $vendor,
            'categories' => Vendor::CATEGORIES,
            'terms'      => Vendor::TERMS,
            'nextExt'    => $vendor?->ext_id ?? $this->nextExtId('wa_vendors', 'WAV', 101),
        ];
    }

    /** Shared next-code helper: highest existing number for the prefix, + 1. */
    private function nextExtId(string $table, string $prefix, int $floor): string
    {
        try {
            if (! Schema::hasTable($table)) {
                return "{$prefix}-{$floor}";
            }

            $len = \strlen($prefix) + 2;   // skip "PREFIX-"
            $max = DB::table($table)
                ->where('company_id', Vendor::COMPANY)
                ->where('ext_id', 'like', "{$prefix}-%")
                ->selectRaw("MAX(CAST(SUBSTRING(ext_id, {$len}) AS UNSIGNED)) AS n")
                ->value('n');

            return $prefix . '-' . max($floor, ((int) $max) + 1);
        } catch (\Throwable $e) {
            report($e);

            return "{$prefix}-{$floor}";
        }
    }

    /** Orders, newest first. Degrades to empty if the table is absent. */
    private function orders(): Collection
    {
        try {
            return Schema::hasTable('wa_purchases')
                ? Purchase::query()->orderByDesc('date')->orderByDesc('id')->get()
                : collect();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    private function vendors(): Collection
    {
        try {
            return Schema::hasTable('wa_vendors')
                ? Vendor::query()->orderBy('name')->get()
                : collect();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Spend per vendor.
     *
     * ⚠ JOINED BY NAME. `wa_purchases.supplier` holds the vendor's name, not an
     * id (the reference's design). Orders naming a vendor who is not in the
     * register are gathered under "unregistered" rather than dropped — a
     * missing roll-up is how a spend total quietly goes wrong.
     */
    private function spend(Collection $orders, Collection $vendors): Collection
    {
        $byName = $orders->groupBy(fn (Purchase $p) => mb_strtolower(trim((string) $p->supplier)));

        $known = $vendors->map(fn (Vendor $v) => $this->figures(
            $v->name, $v, $v->category, $byName->get(mb_strtolower(trim($v->name)), collect())
        ));

        // Orders whose supplier is not a registered vendor.
        $registered = $vendors->map(fn (Vendor $v) => mb_strtolower(trim($v->name)))->all();
        $orphans    = $byName->reject(fn ($v, $k) => \in_array($k, $registered, true));

        $extra = $orphans->map(fn (Collection $own) => $this->figures(
            $own->first()->supplier, null, 'Unregistered', $own
        ))->values();

        return $known->concat($extra)->sortByDesc('value')->values();
    }

    /** One supplier's order figures. Shared so no two screens can disagree. */
    private function figures(string $name, ?Vendor $vendor, string $category, Collection $own): array
    {
        $open = $own->filter->is_open;

        return [
            'name'        => $name,
            'vendor'      => $vendor,
            'category'    => $category,
            'orders'      => $own->count(),
            'open'        => $open->count(),
            'value'       => (int) $own->sum('amount'),
            'outstanding' => (int) $open->sum('amount'),
        ];
    }

    /**
     * This vendor's own figures, matched by name.
     *
     * Used by the delete confirmation, which must report THIS vendor's orders —
     * not whatever the largest supplier happens to be.
     */
    private function vendorFigures(Vendor $vendor): array
    {
        $own = $this->orders()->filter(
            fn (Purchase $p) => mb_strtolower(trim((string) $p->supplier)) === mb_strtolower(trim($vendor->name))
        );

        return $this->figures($vendor->name, $vendor, $vendor->category, $own);
    }

    private function stats(Collection $orders, Collection $vendors, Collection $spend): array
    {
        $open = $orders->filter->is_open;

        return [
            'orders'      => $orders->count(),
            'value'       => (int) $orders->sum('amount'),
            'received'    => (int) $orders->where('status', 'Received')->sum('amount'),
            'open'        => $open->count(),
            'outstanding' => (int) $open->sum('amount'),
            'vendorsUsed' => $orders->pluck('supplier')->filter()->unique()->count(),
            'vendors'     => $vendors->count(),
            'top'         => $spend->first()['name'] ?? null,
            'idle'        => $spend->filter(fn (array $r) => $r['vendor'] && $r['orders'] === 0)->count(),
            'avg'         => $orders->count() ? (int) round($orders->sum('amount') / $orders->count()) : 0,
            'unregistered' => $spend->filter(fn (array $r) => ! $r['vendor'])->count(),
        ];
    }
}
