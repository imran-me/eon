<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Visa Dashboard (owner, 2026-10-13: "bring a dashboard similar to contract
 * flight") — the Visa band's first tab. Same shape as the Flight Dashboard:
 * a banner of live figures, a card wall of the open applications (soonest
 * expected decision first), and the finished ones in a table below.
 *
 * Read-only. Every figure comes from the applications, their invoice lines
 * and their invoices — nothing here writes.
 *
 *   Expected   date opened + the category's processing days (upper bound of
 *              "3-5"); none on the category → the planned travel date
 *   Agent      the client on the invoice the application was billed on
 *   Due        the invoice's unpaid amount, shared across its lines by price
 */
class VisaDashboardController implements HasMiddleware
{
    // 'delivered' is labelled IN PROGRESS on the board — it is not a decision.
    public const FINAL = ['approved', 'rejected'];

    public const STATUS = [
        'pending'    => ['New', '#64748b', '100, 116, 139'],
        'received'   => ['Documents', '#0891b2', '8, 145, 178'],
        'in_embassy' => ['In Embassy', '#d97706', '217, 119, 6'],
        'delivered'  => ['In Progress', '#ea580c', '234, 88, 12'],
        'approved'   => ['Approved', '#059669', '5, 150, 105'],
        'rejected'   => ['Rejected', '#dc2626', '220, 38, 38'],
    ];

    public static function middleware(): array
    {
        return [new Middleware('permission:view visa|view all visa')];
    }

    public function index(Request $request, string $role)
    {
        $apps = $this->applications();
        $open = $apps->reject(fn ($a) => in_array($a['status'], self::FINAL, true))
            ->sortBy(fn ($a) => $a['expected'] ?? '9999-12-31')->values();
        $done = $apps->filter(fn ($a) => in_array($a['status'], self::FINAL, true))
            ->sortByDesc('decided')->values();

        $monthStart = now()->startOfMonth()->toDateString();
        $kpis = [
            'open'       => $open->count(),
            'in_embassy' => $open->where('status', 'in_embassy')->count(),
            'overdue'    => $open->where('overdue', true)->count(),
            'approved'   => $done->where('status', 'approved')->where('decided', '>=', $monthStart)->count(),
            'rejected'   => $done->where('status', 'rejected')->count(),
            'agent_due'  => (float) DB::table('visa_sales')->whereNull('deleted_at')->sum('due_amount'),
            'vendor_due' => (float) $apps->sum('vendor_due'),
        ];

        return view('dashboard.visa_dashboard.index', [
            'role' => $role, 'open' => $open, 'done' => $done, 'kpis' => $kpis,
            'countries' => $apps->pluck('country', 'country_code')->filter()->sort(),
            'agents' => $apps->pluck('agent')->filter()->unique()->sort()->values(),
            'statuses' => self::STATUS,
        ]);
    }

    /** Every live application, reduced to what a card or a row shows. */
    private function applications(): Collection
    {
        $billed = DB::table('visa_sale_items as i')
            ->join('visa_sales as s', 's.id', '=', 'i.visa_sale_id')->whereNull('s.deleted_at')
            ->leftJoin('users as c', 'c.id', '=', 's.client_id')
            ->whereNotNull('i.visa_process_id')
            ->get(['i.visa_process_id', 'i.sale_price', 's.id as sale_id', 's.invoice_number', 's.total_amount', 's.due_amount', 's.client_id', 'c.name as client'])
            ->keyBy('visa_process_id');

        $today = now()->startOfDay();

        return DB::table('visa_processes as p')
            ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
            ->leftJoin('countries as co', 'co.id', '=', 'p.country_id')
            ->leftJoin('visa_categories as vc', 'vc.id', '=', 'p.visa_category_id')
            ->leftJoin('users as v', 'v.id', '=', 'p.vendor_id')
            ->leftJoin('portals as po', 'po.id', '=', 'p.portal_id')
            ->leftJoin('users as o', 'o.id', '=', 'p.assigned_officer_id')
            ->whereNull('p.deleted_at')
            ->get([
                'p.id', 'p.application_id', 'p.passport_holder_id', 'p.status', 'p.stage', 'p.visa_type', 'p.travel_date',
                'p.costing_price', 'p.cost_paid_amount', 'p.sale_price', 'p.created_at', 'p.updated_at',
                'h.name as applicant', 'h.passport_no', 'co.name as country', 'co.code as country_code',
                'vc.name as category', 'vc.avg_processing_days', DB::raw('COALESCE(v.name, po.name) as seller'), 'o.name as officer',
            ])
            ->map(function ($p) use ($billed, $today) {
                $opened = Carbon::parse($p->created_at)->startOfDay();
                preg_match_all('/\d+/', (string) $p->avg_processing_days, $m);
                $days = $m[0] ? (int) max($m[0]) : null;
                $expected = $days ? $opened->copy()->addDays($days) : ($p->travel_date ? Carbon::parse($p->travel_date) : null);
                $final = in_array($p->status, self::FINAL, true);
                $b = $billed[$p->id] ?? null;
                $charge = $b ? (float) $b->sale_price : (float) $p->sale_price;
                $due = $b && (float) $b->total_amount > 0 ? round((float) $b->due_amount * (float) $b->sale_price / (float) $b->total_amount, 2) : null;
                $toGo = $expected ? (int) $today->diffInDays($expected, false) : null;
                return [
                    'id' => $p->id, 'ref' => $p->application_id ?: 'VISA-' . $p->id, 'holder_id' => $p->passport_holder_id,
                    'applicant' => $p->applicant, 'passport_no' => $p->passport_no,
                    'country' => $p->country, 'country_code' => $p->country_code ? strtolower($p->country_code) : null,
                    'category' => $p->category ?: ($p->visa_type ?: 'Visa'), 'visa_type' => $p->visa_type,
                    'status' => $p->status, 'stage' => $p->stage, 'officer' => $p->officer, 'seller' => $p->seller,
                    'opened' => $opened->toDateString(), 'in_process' => (int) $opened->diffInDays($final ? Carbon::parse($p->updated_at) : $today),
                    'expected' => $expected?->toDateString(), 'to_go' => $toGo, 'overdue' => ! $final && $toGo !== null && $toGo < 0,
                    'decided' => $final ? substr((string) $p->updated_at, 0, 10) : null,
                    'billed' => (bool) $b, 'invoice' => $b->invoice_number ?? null, 'sale_id' => $b->sale_id ?? null,
                    'agent' => $b ? ($b->client ?: $b->invoice_number . ' · no client') : null, 'agent_id' => $b->client_id ?? null,
                    'charge' => $charge, 'cost' => (float) $p->costing_price, 'profit' => $charge - (float) $p->costing_price,
                    'due' => $due, 'vendor_due' => max(0, (float) $p->costing_price - (float) $p->cost_paid_amount),
                ];
            });
    }
}
