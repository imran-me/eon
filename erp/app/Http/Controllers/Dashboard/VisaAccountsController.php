<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\VisaProfit;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Visa Accounts (owner, 2026-10-12: Visa to match Ticketing and Contract
 * Flight) — the money side of Visa Processing on one page: P&L with
 * drill-down, Agent / Vendor Ledger tabs with their due sheets, the party
 * ledger rows and the journal entries behind them. Same shape as
 * TicketAccountsController; the P&L figures come from VisaProfit, which the
 * Company Ledger's department table reads too.
 *
 * Nothing here writes.
 */
class VisaAccountsController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:view visa|view all visa')];
    }

    public function index(Request $request, string $role)
    {
        return view('dashboard.visa_accounts.index', $this->figures() + ['role' => $role]);
    }

    /**
     * Agent / Vendor Ledger tabs: Party Statement's own screens, opened on
     * Travels, with this page's switcher and due sheet ($vaLedger).
     */
    public function ledger(Request $request, string $role, string $ledger)
    {
        if (! $request->has('company')) {
            $request->merge(['company' => 2]);
        }
        view()->share('vaLedger', $ledger);
        // Only Visa's own agents / vendors (owner, 2026-10-17) — PartyStatement filters by it.
        $request->attributes->set('party_scope_dept', 'visa');
        try {
            view()->share('vaDue', $this->figures() + ['role' => $role]);
        } catch (\Throwable $e) {
            report($e);
        }
        $ps = app(PartyStatementController::class);

        return $ledger === 'agents' ? $ps->agents($request) : $ps->vendors($request);
    }

    public function figures(): array
    {
        $dept = VisaProfit::summary();
        $invoices = ($dept['ok'] ?? false) ? $dept['invoices'] : collect();
        $t = $dept['totals'] ?? ['revenue' => 0, 'cost' => 0, 'profit' => 0, 'invoices' => 0, 'items' => 0, 'unbilled_count' => 0, 'unbilled_cost' => 0, 'unbilled_price' => 0];

        // ── P&L rows, in the shape the shared P&L script reads ──
        $plRows = $invoices->map(fn ($i) => [
            'key' => 'v'.$i['id'], 'id' => $i['id'], 'label' => $i['invoice'], 'agent' => $i['client'] ?: 'No agent',
            'date' => $i['date'], 'month' => $i['month'], 'earned' => $i['earned'], 'rejected' => $i['rejected'],
            'tickets' => $i['items']->count(),
            'what' => $i['items']->pluck('what')->filter()->unique()->implode(', '),
            'search' => strtolower(implode(' ', [$i['invoice'], $i['client'], $i['items']->pluck('ref')->implode(' '), $i['items']->pluck('passenger')->implode(' '), $i['items']->pluck('passport_no')->implode(' '), $i['items']->pluck('what')->implode(' '), $i['items']->pluck('seller')->implode(' ')])),
            'package' => $i['revenue'], 'billed' => 0.0, 'transfer' => 0.0, 'penalty' => 0.0, 'revenue' => $i['revenue'],
            'ticket_cost' => $i['cost'], 'service_cost' => 0.0, 'change_cost' => 0.0, 'cost' => $i['cost'],
            'profit' => $i['profit'], 'agent_paid' => $i['paid'], 'agent_due' => $i['due'], 'vendor_paid' => $i['vendor_paid'],
            'items' => $i['items'],
        ])->values();

        // ── Agent due sheet (Manage Sales invoices) ──
        $balances = $this->ledgerBalances($invoices->pluck('client_id')->filter()->unique()->values());
        $agentSheet = $invoices->groupBy('client_id')->map(function (Collection $g) use ($balances) {
            $oldest = $g->where('due', '>', 0)->min('date');

            return [
                'client_id' => $g->first()['client_id'], 'name' => $g->first()['client'] ?: 'Agent',
                'invoices' => $g->count(), 'items' => $g->sum(fn ($i) => $i['items']->count()),
                'charged' => (float) $g->sum('revenue'), 'received' => (float) $g->sum('paid'), 'due' => (float) $g->sum('due'),
                'oldest' => $oldest, 'days' => $oldest ? max(0, (int) Carbon::parse($oldest)->diffInDays(now())) : null,
                'ledger' => $balances[$g->first()['client_id']] ?? null,
            ];
        })->sortByDesc('due')->values();
        $unpaidSales = $invoices->where('due', '>', 0)->sortBy('date')->values();

        // ── Vendor due sheet (every application's cost, billed or not) ──
        $apps = DB::table('visa_processes as p')->leftJoin('passport_holders as ph', 'ph.id', '=', 'p.passport_holder_id')
            ->leftJoin('users as v', 'v.id', '=', 'p.vendor_id')->leftJoin('portals as po', 'po.id', '=', 'p.portal_id')
            ->whereNull('p.deleted_at')->where('p.costing_price', '>', 0)
            ->get(['p.id', 'p.application_id', 'p.vendor_id', 'p.portal_id', 'p.costing_price', 'p.cost_paid_amount', 'p.purchase_date', 'p.payable_date', 'p.status', 'p.created_at',
                'ph.name as passenger', DB::raw('COALESCE(v.name, po.name) as seller')])
            ->map(fn ($a) => [
                'id' => $a->id, 'ref' => $a->application_id ?: 'VISA-'.$a->id, 'passenger' => $a->passenger,
                'vendor_id' => $a->vendor_id, 'portal' => ! $a->vendor_id && $a->portal_id, 'seller' => $a->seller,
                'seller_key' => $a->vendor_id ? 'v'.$a->vendor_id : ($a->portal_id ? 'p'.$a->portal_id : '-'),
                'cost' => (float) $a->costing_price, 'paid' => (float) $a->cost_paid_amount,
                'due' => max(0, (float) $a->costing_price - (float) $a->cost_paid_amount),
                'date' => (string) ($a->purchase_date ?: substr((string) $a->created_at, 0, 10)), 'due_date' => $a->payable_date, 'status' => $a->status,
            ]);
        $vBalances = $this->ledgerBalances($apps->pluck('vendor_id')->filter()->unique()->values());
        $vendorSheet = $apps->groupBy('seller_key')->map(fn (Collection $g) => [
            'name' => $g->first()['seller'] ?: 'Seller not set', 'portal' => $g->first()['portal'], 'vendor_id' => $g->first()['vendor_id'],
            'items' => $g->count(), 'billed' => (float) $g->sum('cost'), 'paid' => (float) $g->sum('paid'), 'due' => (float) $g->sum('due'),
            'due_items' => $g->where('due', '>', 0)->count(), 'oldest' => $g->where('due', '>', 0)->min('date'),
            'ledger' => $g->first()['vendor_id'] ? ($vBalances[$g->first()['vendor_id']] ?? null) : null,
        ])->sortByDesc('due')->values();
        $unpaidApps = $apps->where('due', '>', 0)->sortBy('date')->values();
        $noSeller = $apps->where('seller_key', '-');

        // ── Ledger rows and journal entries behind them ──
        $saleIds = $invoices->pluck('id');
        $procIds = $apps->pluck('id');
        $transactions = Transaction::query()->with('user:id,name', 'account:id,name')
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('type', 'visa_sale')->whereIn('invoice_id', $saleIds))
                ->orWhere(fn ($w) => $w->where('type', 'visa_process')->whereIn('invoice_id', $procIds)))
            ->orderByDesc('payment_date')->orderByDesc('id')->limit(400)->get();
        $journal = JournalEntry::query()->with('items.account:id,code,name')
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('source', 'visa_sale')->whereIn('source_id', $saleIds))
                ->orWhere(fn ($w) => $w->where('source', 'visa_process')->whereIn('source_id', $procIds)))
            ->orderByDesc('date')->orderByDesc('id')->limit(200)->get();

        // Books check: Visa Service Income (4120) on the invoices' live journal lines.
        $gl = null;
        try {
            $gl = (float) DB::table('journal_items')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_items.account_id')
                ->whereNull('journal_items.deleted_at')->whereNull('journal_entries.deleted_at')
                ->where('accounts.code', config('accounts.visa_sales_revenue', '4120'))
                ->where('journal_entries.source', 'visa_sale')->whereIn('journal_entries.source_id', $saleIds)
                ->selectRaw('COALESCE(SUM(journal_items.credit - journal_items.debit), 0) n')->value('n');
        } catch (\Throwable $e) {
            report($e);
        }
        $noJournal = $invoices->whereNotIn('id', DB::table('journal_entries')->whereNull('deleted_at')->where('source', 'visa_sale')->pluck('source_id'));

        $overview = [
            'revenue' => $t['revenue'], 'cost' => $t['cost'], 'profit' => $t['profit'],
            'margin' => $t['revenue'] > 0 ? $t['profit'] / $t['revenue'] * 100 : null,
            'invoices' => $t['invoices'], 'items' => $t['items'],
            'agent_received' => (float) $invoices->sum('paid'), 'agent_due' => (float) $invoices->sum('due'),
            'supplier_paid' => (float) $apps->sum('paid'), 'supplier_due' => (float) $apps->sum('due'),
            'unbilled_count' => $t['unbilled_count'], 'unbilled_price' => $t['unbilled_price'], 'unbilled_cost' => $t['unbilled_cost'],
        ];

        // KPI strip: the current month (owner, 2026-10-15) — invoices by their
        // date, applications by purchase date, unbilled work by when it was opened.
        $thisMonth = now()->format('Y-m');
        $inMonth = fn ($date) => str_starts_with((string) $date, $thisMonth);
        $mi = $invoices->where('month', $thisMonth);
        $ma = $apps->filter(fn ($a) => $inMonth($a['date']));
        $mu = (($dept['ok'] ?? false) ? $dept['unbilled'] : collect())->filter(fn ($u) => $inMonth($u['date']));
        $mRevenue = (float) $mi->sum('revenue');
        $mCost = (float) $mi->sum('cost');
        $kpi = [
            'month' => $thisMonth, 'label' => now()->format('M Y'),
            'revenue' => $mRevenue, 'cost' => $mCost, 'profit' => $mRevenue - $mCost,
            'margin' => $mRevenue > 0 ? ($mRevenue - $mCost) / $mRevenue * 100 : null,
            'invoices' => $mi->count(), 'items' => $mi->sum(fn ($i) => $i['items']->count()),
            'agent_received' => (float) $mi->sum('paid'), 'agent_due' => (float) $mi->sum('due'),
            'supplier_paid' => (float) $ma->sum('paid'), 'supplier_due' => (float) $ma->sum('due'),
            'unbilled_count' => $mu->count(), 'unbilled_price' => (float) $mu->sum('price'), 'unbilled_cost' => (float) $mu->sum('cost'),
        ];

        return compact('dept', 'overview', 'plRows', 'agentSheet', 'unpaidSales', 'vendorSheet', 'unpaidApps', 'noSeller',
            'transactions', 'journal', 'gl', 'noJournal', 'kpi');
    }

    private function ledgerBalances(Collection $partyIds): array
    {
        if ($partyIds->isEmpty()) {
            return [];
        }
        $latest = Transaction::whereIn('user_id', $partyIds)->selectRaw('user_id, MAX(id) as last_id')->groupBy('user_id')->pluck('last_id', 'user_id');

        return Transaction::whereIn('id', $latest->values())->pluck('balance', 'user_id')->map(fn ($v) => (float) $v)->all();
    }
}
