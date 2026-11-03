<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visa as a department (owner, 2026-10-12). Read-only — it writes nothing,
 * and no agent / vendor ledger reads it.
 *
 *   Unit       a Manage Sales invoice (visa_sales) and the applications /
 *              other services on it (visa_sale_items)
 *   Revenue    the invoice amount charged to the agent
 *   Cost       each application's costing price, each other service's cost
 *   Earned     every application on the invoice has reached a final status
 *              (approved or rejected); until then it is booked
 *   Unbilled   applications / services on no invoice: work done, not yet
 *              charged — shown apart, not profit or loss
 */
class VisaProfit
{
    // 'delivered' is labelled IN PROGRESS on the board — not a final status.
    public const FINAL = ['approved', 'rejected'];

    public static function summary(): array
    {
        try {
            return ['ok' => true] + self::compute();
        } catch (\Throwable $e) {
            \Log::warning('VisaProfit: ' . $e->getMessage());
            return ['ok' => false];
        }
    }

    public static function compute(): array
    {
        $sales = DB::table('visa_sales as s')->leftJoin('users as c', 'c.id', '=', 's.client_id')
            ->whereNull('s.deleted_at')
            ->orderByDesc('s.voucher_date')->orderByDesc('s.id')
            ->get(['s.id', 's.invoice_number', 's.client_id', 's.voucher_date', 's.created_at', 's.total_amount', 's.paid_amount', 's.due_amount', 's.status', 'c.name as client']);

        $items = DB::table('visa_sale_items as i')
            ->leftJoin('visa_processes as p', 'p.id', '=', 'i.visa_process_id')
            ->leftJoin('other_visa_services as o', 'o.id', '=', 'i.other_visa_service_id')
            ->leftJoin('passport_holders as ph', DB::raw('ph.id'), '=', DB::raw('COALESCE(p.passport_holder_id, o.passport_holder_id)'))
            ->leftJoin('countries as co', DB::raw('co.id'), '=', DB::raw('COALESCE(p.country_id, o.country_id)'))
            ->leftJoin('visa_categories as vc', 'vc.id', '=', 'p.visa_category_id')
            ->leftJoin('users as v', 'v.id', '=', 'p.vendor_id')
            ->leftJoin('portals as po', 'po.id', '=', 'p.portal_id')
            ->leftJoin('other_service_types as ot', 'ot.id', '=', 'o.other_service_type_id')
            ->whereIn('i.visa_sale_id', $sales->pluck('id'))
            ->get([
                'i.visa_sale_id', 'i.sale_price', 'i.visa_process_id', 'i.other_visa_service_id',
                'p.application_id', 'p.status as p_status', 'p.costing_price', 'p.cost_paid_amount', 'p.travel_date',
                'o.service_code', 'o.status as o_status', 'o.cost_price',
                'ph.name as passenger', 'ph.passport_no', 'co.name as country', 'vc.name as category', 'p.visa_type',
                DB::raw('COALESCE(v.name, po.name) as seller'), 'ot.name as service_type',
            ])
            ->map(function ($i) {
                $isProc = (bool) $i->visa_process_id;
                return [
                    'sale_id' => $i->visa_sale_id,
                    'kind' => $isProc ? 'application' : 'service',
                    'ref' => $isProc ? ($i->application_id ?: 'VISA-' . $i->visa_process_id) : ($i->service_code ?: 'OS-' . $i->other_visa_service_id),
                    'passenger' => $i->passenger, 'passport_no' => $i->passport_no,
                    'what' => $isProc ? trim(($i->country ?: '') . ' · ' . ($i->category ?: $i->visa_type ?: 'Visa'), ' ·') : trim(($i->service_type ?: 'Other service') . ($i->country ? ' · ' . $i->country : '')),
                    'seller' => $i->seller,
                    'status' => $isProc ? $i->p_status : $i->o_status,
                    'final' => $isProc ? in_array($i->p_status, self::FINAL, true) : $i->o_status === 'done',
                    'price' => (float) $i->sale_price,
                    'cost' => $isProc ? (float) $i->costing_price : (float) $i->cost_price,
                    'paid' => $isProc ? (float) $i->cost_paid_amount : 0.0,
                    'travel' => $i->travel_date,
                ];
            })
            ->groupBy('sale_id');

        $invoices = $sales->map(function ($s) use ($items) {
            $its = $items->get($s->id, collect())->values();
            $revenue = (float) $s->total_amount;
            $cost = (float) $its->sum('cost');
            $date = (string) ($s->voucher_date ?: substr((string) $s->created_at, 0, 10));
            return [
                'id' => $s->id, 'invoice' => $s->invoice_number, 'client_id' => $s->client_id, 'client' => $s->client,
                'date' => $date, 'month' => substr($date, 0, 7),
                'revenue' => $revenue, 'cost' => $cost, 'profit' => $revenue - $cost,
                'paid' => (float) $s->paid_amount, 'due' => (float) $s->due_amount, 'status' => $s->status,
                'earned' => $its->isNotEmpty() && $its->every(fn ($i) => $i['final']),
                'rejected' => $its->where('status', 'rejected')->count(),
                'items' => $its,
                'vendor_paid' => (float) $its->sum('paid'),
            ];
        });

        // Work done and not yet on any invoice.
        $billedProc = DB::table('visa_sale_items')->whereNotNull('visa_process_id')->pluck('visa_process_id')->flip();
        $billedOther = DB::table('visa_sale_items')->whereNotNull('other_visa_service_id')->pluck('other_visa_service_id')->flip();
        $unbilled = DB::table('visa_processes as p')->leftJoin('passport_holders as ph', 'ph.id', '=', 'p.passport_holder_id')
            ->whereNull('p.deleted_at')->get(['p.id', 'p.application_id', 'p.status', 'p.costing_price', 'p.sale_price', 'p.created_at', 'ph.name as passenger'])
            ->reject(fn ($p) => $billedProc->has($p->id))
            ->map(fn ($p) => ['ref' => $p->application_id ?: 'VISA-' . $p->id, 'passenger' => $p->passenger, 'status' => $p->status,
                'cost' => (float) $p->costing_price, 'price' => (float) $p->sale_price, 'date' => substr((string) $p->created_at, 0, 10)])
            ->values();
        if (Schema::hasTable('other_visa_services')) {
            $unbilled = $unbilled->concat(DB::table('other_visa_services as o')->leftJoin('passport_holders as ph', 'ph.id', '=', 'o.passport_holder_id')
                ->whereNull('o.deleted_at')->get(['o.id', 'o.service_code', 'o.status', 'o.cost_price', 'o.sale_price', 'o.created_at', 'ph.name as passenger'])
                ->reject(fn ($o) => $billedOther->has($o->id))
                ->map(fn ($o) => ['ref' => $o->service_code ?: 'OS-' . $o->id, 'passenger' => $o->passenger, 'status' => $o->status,
                    'cost' => (float) $o->cost_price, 'price' => (float) $o->sale_price, 'date' => substr((string) $o->created_at, 0, 10)]))->values();
        }

        $revenue = (float) $invoices->sum('revenue');
        $cost = (float) $invoices->sum('cost');
        return [
            'invoices' => $invoices,
            'unbilled' => $unbilled,
            'totals' => [
                'revenue' => $revenue, 'cost' => $cost, 'profit' => $revenue - $cost,
                'invoices' => $invoices->count(), 'items' => $invoices->sum(fn ($i) => $i['items']->count()),
                'unbilled_count' => $unbilled->count(), 'unbilled_cost' => (float) $unbilled->sum('cost'), 'unbilled_price' => (float) $unbilled->sum('price'),
            ],
        ];
    }
}
