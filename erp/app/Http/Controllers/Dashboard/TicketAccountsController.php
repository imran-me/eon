<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\JournalEntry;
use App\Models\TicketChange;
use App\Models\Transaction;
use App\Services\ReturnCancel;
use App\Services\TicketBaggage;
use App\Services\Ticketing\TicketWorkbook;
use App\Services\TicketingProfit;
use App\Support\TicketCompanyScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ticket Accounts (owner, 2026-10-04: "bring one more nav item, ticket
 * accounts, like we have in the contract flight") — the money side of
 * Ticketing on one page, built the same way as ContractFlightAccountsController:
 * P&L, vendor due sheet, agent due sheet, refunds & cancellations, the party
 * ledger rows and the journal entries behind them.
 *
 * Nothing here writes. Every figure is read from what Ticketing already
 * recorded (ticket_purchases, ticket_sales/items, ticket_refunds,
 * ticket_reissues, transactions, journal_entries), so this page cannot
 * disagree with those screens.
 *
 * Money rules, the same as everywhere else in Ticketing:
 *   ticket cost   a live ticket costs its amount; a cancelled one only what
 *                 the airline kept (amount − refund_amount)
 *   revenue       Manage Sales invoices (ticket_sale_items.price) — a ticket
 *                 linked to a contract flight is billed inside the flight's
 *                 package, so its revenue lives on Flight Accounts, not here
 *   supplier due  ticket_purchases.due_amount of tickets not cancelled
 */
class TicketAccountsController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:view ticket purchase|view all ticket purchase|view ticket direct sale|view all ticket direct sale')];
    }

    public function index(Request $request, string $role)
    {
        return view('dashboard.ticket_accounts.index', $this->figures($request) + ['role' => $role]);
    }

    /**
     * Ticketing workbook (owner, 2026-10-11: "all entry goes there automatically,
     * if edited in the system that gets updated too"): an .xlsx built from the
     * database at the moment it is opened, so it is never behind. Same figures
     * as this page (figures()), plus every ticket order.
     */
    public function workbook(Request $request, string $role)
    {
        $f = $this->figures($request);
        $wb = new TicketWorkbook;
        $book = $wb->build($f, TicketCompanyScope::resolve($request, auth()->user()));

        // ?download=1 → the .xlsx file itself
        if ($request->boolean('download')) {
            $file = 'Ticketing '.now()->format('Y-m-d H.i').'.xlsx';

            return response()->streamDownload(function () use ($book) {
                (new Xlsx($book))->setIncludeCharts(true)->save('php://output');
            }, $file, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store']);
        }

        // otherwise the same workbook as a page in a new tab (owner, 2026-10-11: "it was
        // supposed to open in a new tab") — a browser cannot open .xlsx itself
        // — drawn from the same rows the sheets were built from (TicketWorkbook::$views)
        return response()->view('dashboard.ticket_accounts.workbook', [
            'views' => array_values($wb->views),
            'overview' => $f['overview'],
            'monthly' => $f['monthly']->reverse()->values(),
            'sellerDues' => $f['vendorSheet']->where('due', '>', 0)->take(5)->values(),
            'agentDues' => $f['agentSheet']->where('due', '>', 0)->take(5)->values(),
            'from' => $f['from'], 'to' => $f['to'],
            'downloadUrl' => $request->fullUrlWithQuery(['download' => 1]),
            'builtAt' => now(),
        ])->header('Cache-Control', 'no-store');
    }

    /** Everything this page shows, for the page and for the workbook. */
    public function figures(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : null;
        $seller = (string) $request->input('seller', '');   // "v:12" vendor / "p:3" portal
        $companyId = TicketCompanyScope::resolve($request, auth()->user());

        $tickets = $this->tickets($companyId, $from, $to, $seller);
        $sales = $this->sales($from, $to);
        // Ticket Orders -> Change (2026-10-06), Ticketing-only ones: a contract
        // flight ticket's change is accounted on Flight Accounts. Dated by when
        // the change was made. sold_cost = what it did to a SOLD ticket's cost:
        // + fee owed to the seller, - the airline's refund on a refund / void.
        $changes = $this->changes($companyId, $from, $to, $seller)
            ->concat(TicketBaggage::movements($companyId, $from, $to, $seller));

        // ── Overview (every month; the workbook reads it) and the KPI strip
        //    (the current month — owner, 2026-10-15) ──
        $cancelled = $tickets->where('status', 'cancelled');
        $overview = $this->overview($tickets, $sales, $changes);
        $thisMonth = now()->format('Y-m');
        $kpi = $this->overview($tickets->where('month', $thisMonth), $sales->where('month', $thisMonth), $changes->where('month', $thisMonth))
            + ['month' => $thisMonth, 'label' => now()->format('M Y')];

        // ── P&L, month by month (issue month for tickets, sale month for invoices) ──
        $months = $tickets->pluck('month')->merge($sales->pluck('month'))->merge($changes->pluck('month'))->filter()->unique()->sortDesc()->values();
        $monthly = $months->map(function ($month) use ($tickets, $sales, $changes) {
            $t = $tickets->where('month', $month);
            $s = $sales->where('month', $month);
            $c = $changes->where('month', $month);
            $revenue = (float) $s->sum('total') + (float) $c->sum('revenue');
            $soldCost = (float) $s->sum('cost') + (float) $c->sum('sold_cost');

            return [
                'label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'tickets' => $t->count(),
                'cost' => (float) $t->sum('net_cost') + (float) $c->where('kind', '!=', 'baggage')->sum('cost_added'),
                'penalty' => (float) $t->where('status', 'cancelled')->sum('penalty'),
                'cf_cost' => (float) $t->where('use', 'cf')->sum('cost'),
                'idle_cost' => (float) $t->where('use', 'idle')->sum('cost'),
                'invoices' => $s->count(),
                'revenue' => $revenue,
                'sold_cost' => $soldCost,
                'profit' => $revenue - $soldCost,
                'margin' => $revenue > 0 ? ($revenue - $soldCost) / $revenue * 100 : null,
            ];
        });

        // ── Vendor due sheet — one row per seller (vendor or portal) ──
        $balances = $this->ledgerBalances($tickets->pluck('vendor_id')->merge($sales->pluck('client_id'))->filter()->unique()->values());
        $wallets = DB::table('portals')->whereNull('deleted_at')->pluck('balance', 'id')->map(fn ($v) => (float) $v);
        $vendorSheet = $tickets->groupBy('seller_key')->map(function (Collection $g) use ($balances, $wallets) {
            $first = $g->first();
            $live = $g->where('status', '!=', 'cancelled');

            return [
                'name' => $first['seller'] ?: 'Seller not set',
                'portal' => $first['portal_id'] && ! $first['vendor_id'],
                'vendor_id' => $first['vendor_id'],
                'tickets' => $g->count(),
                'billed' => (float) $g->sum('cost'),
                'paid' => (float) $g->sum('paid'),
                'refunds' => (float) $g->sum('refund'),
                'due' => (float) $live->sum('due') + (float) $g->where('status', 'cancelled')->sum('baggage_due'),
                'due_items' => $live->where('due', '>', 0)->count(),
                'oldest' => $live->where('due', '>', 0)->min('issued'),
                'ledger' => $first['vendor_id'] ? ($balances[$first['vendor_id']] ?? null) : null,
                'wallet' => (! $first['vendor_id'] && $first['portal_id']) ? ($wallets[$first['portal_id']] ?? null) : null,
            ];
        })->sortByDesc('due')->values();
        $unpaidTickets = $tickets->where('due', '>', 0)->sortBy('issued')->values();

        // ── Agent due sheet (Manage Sales invoices) ──
        $agentSheet = $sales->groupBy('client_id')->map(function (Collection $g) use ($balances, $changes) {
            $oldest = $g->where('due', '>', 0)->min('date');
            // + change charges - agent prices reversed, for this agent
            $adj = (float) $changes->where('client_id', $g->first()['client_id'])->sum('revenue');

            return [
                'client_id' => $g->first()['client_id'],
                'name' => $g->first()['client'] ?: 'Agent',
                'invoices' => $g->count(),
                'tickets' => (int) $g->sum('items'),
                'charged' => (float) $g->sum('total') + $adj,
                'received' => (float) $g->sum('paid'),
                'due' => (float) $g->sum('due') + $adj,
                'oldest' => $oldest,
                'days' => $oldest ? max(0, (int) Carbon::parse($oldest)->diffInDays(now())) : null,
                'ledger' => $balances[$g->first()['client_id']] ?? null,
            ];
        })->sortByDesc('due')->values();
        $unpaidSales = $sales->where('due', '>', 0)->sortBy('date')->values();

        // ── Refunds & cancellations ──
        $refunds = Schema::hasTable('ticket_refunds')
            ? DB::table('ticket_refunds as r')->leftJoin('users as a', 'a.id', '=', 'r.agent_id')
                ->when($from, fn ($q) => $q->where('r.refund_date', '>=', $from->toDateString()))
                ->when($to, fn ($q) => $q->where('r.refund_date', '<=', $to->toDateString()))
                ->orderByDesc('r.refund_date')->get(['r.*', 'a.name as agent_name'])
            : collect();
        $reissues = Schema::hasTable('ticket_reissues')
            ? DB::table('ticket_reissues as r')->leftJoin('users as a', 'a.id', '=', 'r.agent_id')
                ->when($from, fn ($q) => $q->where('r.reissue_date', '>=', $from->toDateString()))
                ->when($to, fn ($q) => $q->where('r.reissue_date', '<=', $to->toDateString()))
                ->orderByDesc('r.reissue_date')->get(['r.*', 'a.name as agent_name'])
            : collect();

        // ── Ledger rows and journal entries behind the tickets and invoices ──
        $ticketIds = $tickets->pluck('id');
        $saleIds = $sales->pluck('id');
        $refundIds = $refunds->pluck('id');
        $changeIds = $changes->where('kind', '!=', 'baggage')->pluck('id');
        $changeTypes = [TicketChange::FEE_TYPE, TicketChange::FARE_TYPE, TicketChange::CHARGE_TYPE,
            TicketChange::SALE_REVERSAL_TYPE, TicketChange::PENALTY_SOURCE];
        $transactions = Transaction::query()->with('user:id,name', 'account:id,name')
            ->where(function ($q) use ($ticketIds, $saleIds, $refundIds, $changeIds, $changeTypes) {
                $q->where(fn ($w) => $w->whereIn('type', ['ticket_purchase', 'ticket_purchase_refund', TicketBaggage::COST, TicketBaggage::CHARGE])->whereIn('invoice_id', $ticketIds))
                    ->orWhere(fn ($w) => $w->whereIn('type', ['ticket_sale', 'ticket_reissue'])->whereIn('invoice_id', $saleIds))
                    ->orWhere(fn ($w) => $w->where('type', 'ticket_refund')->whereIn('invoice_id', $refundIds))
                    ->orWhere(fn ($w) => $w->whereIn('type', $changeTypes)->whereIn('invoice_id', $changeIds));
            })
            ->orderByDesc('payment_date')->orderByDesc('id')->limit(400)->get();
        $journal = JournalEntry::query()->with('items.account:id,code,name')
            ->where(function ($q) use ($ticketIds, $saleIds, $refundIds, $changeIds, $changeTypes) {
                $q->where(fn ($w) => $w->whereIn('source', [TicketBaggage::COST, TicketBaggage::CHARGE, 'ticket_purchase', 'ticket_purchase_cancel'])->whereIn('source_id', $ticketIds))
                    ->orWhere(fn ($w) => $w->where('source', 'ticket_sale')->whereIn('source_id', $saleIds))
                    ->orWhere(fn ($w) => $w->where('source', 'ticket_refund')->whereIn('source_id', $refundIds))
                    ->orWhere(fn ($w) => $w->whereIn('source', $changeTypes)->whereIn('source_id', $changeIds));
            })
            ->orderByDesc('date')->orderByDesc('id')->limit(200)->get();

        $sellers = DB::table('ticket_purchases as p')->leftJoin('users as v', 'v.id', '=', 'p.vendor_id')->leftJoin('portals as po', 'po.id', '=', 'p.portal_id')
            ->whereNull('p.deleted_at')->where(fn ($q) => $q->whereNotNull('p.vendor_id')->orWhereNotNull('p.portal_id'))
            ->selectRaw("DISTINCT CASE WHEN p.vendor_id IS NOT NULL THEN CONCAT('v:', p.vendor_id) ELSE CONCAT('p:', p.portal_id) END k, COALESCE(v.name, po.name) n")
            ->orderBy('n')->get();

        $dept = TicketingProfit::summary();
        $plRows = $this->plRows($sales, $changes, $dept);

        // Books check: what the ledger's ticket-sales income (4110) says
        // against this page's invoice revenue. Live lines only — replaced
        // lines are soft-deleted, not removed.
        $gl = null;
        try {
            $gl = (float) DB::table('journal_items')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_items.account_id')
                ->whereNull('journal_items.deleted_at')->whereNull('journal_entries.deleted_at')
                ->where('accounts.code', config('accounts.ticket_sales_revenue', '4110'))
                ->where('journal_entries.source', 'ticket_sale')->whereIn('journal_entries.source_id', $sales->pluck('id'))
                ->selectRaw('COALESCE(SUM(journal_items.credit - journal_items.debit), 0) n')->value('n');
        } catch (\Throwable $e) {
            report($e);
        }

        return compact(
            'overview', 'monthly', 'sales', 'vendorSheet', 'unpaidTickets', 'agentSheet', 'unpaidSales',
            'cancelled', 'refunds', 'reissues', 'transactions', 'journal', 'sellers', 'from', 'to', 'seller', 'changes', 'tickets',
            'plRows', 'gl', 'dept', 'kpi'
        );
    }

    /** The headline figures of a set of tickets, invoices and changes (all of them, or one month's). */
    private function overview(Collection $tickets, Collection $sales, Collection $changes): array
    {
        $live = $tickets->where('status', '!=', 'cancelled');
        $cancelled = $tickets->where('status', 'cancelled');
        $overview = [
            'tickets' => $tickets->count(),
            'invoices' => $sales->count(),
            'cost' => (float) $tickets->sum('net_cost'),
            'live_cost' => (float) $live->sum('cost'),
            'penalty' => (float) $cancelled->sum('penalty'),
            'cancelled' => $cancelled->count(),
            'revenue' => (float) $sales->sum('total'),
            'sold_cost' => (float) $sales->sum('cost'),
            'sold_profit' => (float) $sales->sum('total') - (float) $sales->sum('cost'),
            'cf_count' => $tickets->where('use', 'cf')->count(),
            'cf_cost' => (float) $tickets->where('use', 'cf')->sum('cost'),
            'idle_count' => $tickets->where('use', 'idle')->count(),
            'idle_cost' => (float) $tickets->where('use', 'idle')->sum('cost'),
            'supplier_paid' => (float) $tickets->sum('paid'),
            'supplier_due' => (float) $live->sum('due') + (float) $cancelled->sum('baggage_due'),
            'agent_received' => (float) $sales->sum('paid'),
            'agent_due' => (float) $sales->sum('due'),
        ];
        $overview['cost'] += (float) $changes->where('kind', '!=', 'baggage')->sum('cost_added');
        $overview['revenue'] += (float) $changes->sum('revenue');
        $overview['sold_cost'] += (float) $changes->sum('sold_cost');
        $overview['sold_profit'] = $overview['revenue'] - $overview['sold_cost'];
        $overview['agent_due'] += (float) $changes->sum('revenue');
        $overview['margin'] = $overview['revenue'] > 0 ? $overview['sold_profit'] / $overview['revenue'] * 100 : null;

        return $overview;
    }

    /**
     * Agent / Vendor Ledger tabs: Party Statement's own screens, opened on
     * Travels, with this page's switcher and due sheet ($tkLedger). Same
     * pattern as ContractFlightAccountsController::ledger().
     */
    public function ledger(Request $request, string $role, string $ledger)
    {
        if (! $request->has('company')) {
            $request->merge(['company' => 2]);
        }
        view()->share('tkLedger', $ledger);
        // Only Ticketing's own agents / vendors (owner, 2026-10-17) — PartyStatement filters by it.
        $request->attributes->set('party_scope_dept', 'ticketing');
        try {
            view()->share('tkaDue', $this->figures($request) + ['role' => $role]);
        } catch (\Throwable $e) {
            report($e);
        }
        $ps = app(PartyStatementController::class);

        return $ledger === 'agents' ? $ps->agents($request) : $ps->vendors($request);
    }

    /**
     * The P&L tab's rows: one per Manage Sales invoice (with its tickets) and
     * one per sold-ticket change from Ticket Orders. Profit is earned when
     * the travel is done: every ticket on the invoice has departed.
     */
    private function plRows(Collection $sales, Collection $changes, array $dept = []): Collection
    {
        $items = DB::table('ticket_sale_items as i')
            ->join('ticket_purchases as p', 'p.id', '=', 'i.ticket_purchase_id')
            ->leftJoin('passport_holders as ph', 'ph.id', '=', 'p.passport_holder_id')
            ->leftJoin('tickets as t', 't.id', '=', 'p.ticket_id')
            ->leftJoin('users as v', 'v.id', '=', 'p.vendor_id')
            ->leftJoin('portals as po', 'po.id', '=', 'p.portal_id')
            ->whereIn('i.ticket_sale_id', $sales->pluck('id'))
            ->get(['i.ticket_sale_id', 'i.price', 'p.id', 'p.ticket_no', 'p.amount', 'p.paid_amount', 'p.departure_at', 'p.status',
                'ph.name as passenger', 't.title as route', DB::raw('COALESCE(v.name, po.name) as seller')])
            ->groupBy('ticket_sale_id');
        $now = now();

        $rows = $sales->map(function ($s) use ($items, $now) {
            $its = $items->get($s['id'], collect());
            $earned = $its->isNotEmpty() && $its->every(fn ($i) => $i->departure_at && Carbon::parse($i->departure_at)->lte($now));
            $first = $its->pluck('departure_at')->filter()->min();

            return [
                'key' => 's'.$s['id'], 'kind' => 'invoice', 'id' => $s['id'], 'label' => $s['invoice'],
                'agent' => $s['client'] ?: 'No agent', 'date' => $s['date'], 'month' => $s['month'],
                'travel' => $first ? substr((string) $first, 0, 16) : null, 'earned' => $earned,
                'tickets' => $its->count(), 'route' => $its->pluck('route')->filter()->unique()->implode(', '),
                'search' => strtolower(implode(' ', [$s['invoice'], $s['client'], $its->pluck('ticket_no')->implode(' '), $its->pluck('passenger')->implode(' '), $its->pluck('route')->implode(' '), $its->pluck('seller')->implode(' ')])),
                'package' => $s['total'], 'billed' => 0.0, 'transfer' => 0.0, 'penalty' => 0.0, 'revenue' => $s['total'],
                'ticket_cost' => $s['cost'], 'service_cost' => 0.0, 'change_cost' => 0.0, 'cost' => $s['cost'],
                'profit' => $s['total'] - $s['cost'], 'agent_paid' => $s['paid'], 'agent_due' => $s['due'],
                'vendor_paid' => (float) $its->sum('paid_amount'),
                'items' => $its->map(fn ($i) => [
                    'pnr' => $i->ticket_no, 'passenger' => $i->passenger, 'route' => $i->route, 'seller' => $i->seller,
                    'travel' => $i->departure_at, 'price' => (float) $i->price, 'cost' => (float) $i->amount,
                    'paid' => (float) $i->paid_amount, 'status' => $i->status,
                ])->values(),
            ];
        });

        // A change on an unsold ticket has no agent and no revenue — its cost
        // is in "outside this profit" (penalties / idle), not here.
        $kinds = ['reissue' => 'Re-issue', 'reschedule' => 'Reschedule', 'refund' => 'Refund', 'void' => 'Void', 'newticket' => 'New ticket', 'return_cancel' => 'Cancel return'];
        $changeRows = $changes->filter(fn ($c) => $c['client_id'])->map(fn ($c) => [
            'key' => 'c'.$c['id'], 'kind' => 'change', 'id' => $c['id'], 'label' => ($kinds[$c['kind']] ?? ucfirst($c['kind'])).' · '.($c['pnr'] ?: '—'),
            'agent' => $c['client'] ?: 'No agent', 'date' => substr($c['date'], 0, 10), 'month' => $c['month'],
            'travel' => null, 'earned' => true, 'tickets' => 1, 'route' => '',
            'search' => strtolower(implode(' ', [$c['kind'], $c['pnr'], $c['passenger'], $c['client'], $c['note']])),
            'package' => 0.0, 'billed' => $c['revenue'], 'transfer' => 0.0, 'penalty' => 0.0, 'revenue' => $c['revenue'],
            'ticket_cost' => 0.0, 'service_cost' => 0.0, 'change_cost' => $c['sold_cost'], 'cost' => $c['sold_cost'],
            'profit' => $c['revenue'] - $c['sold_cost'], 'agent_paid' => 0.0, 'agent_due' => $c['revenue'],
            'vendor_paid' => 0.0, 'note' => $c['note'], 'passenger' => $c['passenger'], 'items' => collect(),
        ]);

        // Department rows (TicketingProfit), one per month: tickets passed to
        // Contract Flight at net price (revenue = cost, profit 0), and the
        // department's own losses on tickets nobody bought from it.
        $deptRows = collect();
        if ($dept['ok'] ?? false) {
            $mk = fn ($g, $kind, $month) => [
                'key' => ($kind === 'transfer' ? 't' : 'o').$month, 'kind' => $kind, 'id' => null,
                'label' => ($kind === 'transfer' ? 'To Contract Flight' : 'Own losses').' · '.$g->count().' ticket'.($g->count() === 1 ? '' : 's'),
                'agent' => $kind === 'transfer' ? 'Contract Flight dept' : 'Ticketing (own)',
                'date' => $g->max('date') ? substr((string) $g->max('date'), 0, 10) : $month.'-01', 'month' => $month,
                'travel' => null, 'earned' => true, 'tickets' => $g->count(), 'route' => '',
                'search' => strtolower(($kind === 'transfer' ? 'contract flight transfer ' : 'own loss penalty ').$g->pluck('pnr')->implode(' ')),
                'package' => 0.0, 'billed' => 0.0,
                'transfer' => $kind === 'transfer' ? (float) $g->sum('amount') : 0.0,
                'penalty' => $kind === 'own' ? (float) $g->sum('amount') : 0.0,
                'revenue' => $kind === 'transfer' ? (float) $g->sum('amount') : 0.0,
                'ticket_cost' => 0.0, 'service_cost' => 0.0, 'change_cost' => 0.0, 'cost' => (float) $g->sum('amount'),
                'profit' => $kind === 'transfer' ? 0.0 : -(float) $g->sum('amount'),
                'agent_paid' => 0.0, 'agent_due' => 0.0, 'vendor_paid' => 0.0, 'passenger' => null,
                'items' => collect(), 'lines' => $g->sortBy('date')->values(),
            ];
            foreach ($dept['transfers']->groupBy('month') as $month => $g) {
                $deptRows->push($mk($g, 'transfer', $month));
            }
            foreach ($dept['own_losses']->groupBy('month') as $month => $g) {
                $deptRows->push($mk($g, 'own', $month));
            }
        }

        return $rows->concat($changeRows)->concat($deptRows)->sortByDesc('date')->values();
    }

    /** One row per ticket_purchases row issued in range, with what it cost and where it went. */
    private function tickets(?int $companyId, ?Carbon $from, ?Carbon $to, string $seller): Collection
    {
        [$sk, $sid] = array_pad(explode(':', $seller, 2), 2, null);
        $hasRefund = Schema::hasColumn('ticket_purchases', 'refund_amount');
        $hasNote = Schema::hasColumn('ticket_purchases', 'cancel_note');
        $hasReturn = ReturnCancel::ready() && Schema::hasColumn('ticket_purchases', 'return_penalty');
        $rows = DB::table('ticket_purchases as p')
            ->leftJoin('passport_holders as ph', 'ph.id', '=', 'p.passport_holder_id')
            ->leftJoin('tickets as t', 't.id', '=', 'p.ticket_id')
            ->leftJoin('users as v', 'v.id', '=', 'p.vendor_id')
            ->leftJoin('portals as po', 'po.id', '=', 'p.portal_id')
            ->whereNull('p.deleted_at')
            ->when($companyId, fn ($q) => $q->where('p.company_id', $companyId))
            ->when($from, fn ($q) => $q->where('p.purchase_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('p.purchase_date', '<=', $to->toDateString()))
            ->when($sk === 'v' && $sid, fn ($q) => $q->where('p.vendor_id', (int) $sid))
            ->when($sk === 'p' && $sid, fn ($q) => $q->whereNull('p.vendor_id')->where('p.portal_id', (int) $sid))
            ->orderByDesc('p.purchase_date')->orderByDesc('p.id')
            ->get([
                'p.id', 'p.ticket_no', 'p.purchase_date', 'p.amount', 'p.paid_amount', 'p.due_amount', 'p.status', 'p.vendor_id', 'p.portal_id',
                TicketBaggage::ready() ? 'p.extra_baggage_cost' : DB::raw('NULL as extra_baggage_cost'),
                $hasRefund ? 'p.refund_amount' : DB::raw('NULL as refund_amount'),
                $hasRefund ? 'p.cancelled_at' : DB::raw('NULL as cancelled_at'),
                $hasNote ? 'p.cancel_note' : DB::raw('NULL as cancel_note'),
                $hasReturn ? 'p.return_cancelled_at' : DB::raw('NULL as return_cancelled_at'),
                $hasReturn ? 'p.return_refund_amount' : DB::raw('NULL as return_refund_amount'),
                $hasReturn ? 'p.return_penalty' : DB::raw('NULL as return_penalty'),
                'ph.name as passenger', 't.title as route', 'v.name as vendor_name', 'po.name as portal_name',
            ]);

        $ids = $rows->pluck('id');
        $cf = Schema::hasTable('contract_flight_passenger_tickets')
            ? DB::table('contract_flight_passenger_tickets as l')->join('contract_flight_passengers as cp', 'cp.id', '=', 'l.contract_flight_passenger_id')
                ->leftJoin('contract_flights as f', 'f.id', '=', 'cp.contract_flight_id')
                ->whereIn('l.ticket_purchase_id', $ids)->pluck('f.flight_number', 'l.ticket_purchase_id')
            : collect();
        $sold = DB::table('ticket_sale_items as i')->join('ticket_sales as s', 's.id', '=', 'i.ticket_sale_id')->whereNull('s.deleted_at')
            ->whereIn('i.ticket_purchase_id', $ids)->pluck('s.invoice_no', 'i.ticket_purchase_id');

        return $rows->map(function ($r) use ($cf, $sold) {
            $cancelled = $r->status === 'cancelled';
            $refund = $cancelled && $r->refund_amount !== null ? (float) $r->refund_amount : 0.0;
            $penalty = $cancelled ? max(0.0, (float) $r->amount - $refund) : 0.0;
            // A round trip whose return half was cancelled (2026-10-14) stays live, less that refund.
            $returnOff = ! $cancelled && $r->return_cancelled_at;
            if ($returnOff) {
                $refund = (float) ($r->return_refund_amount ?? 0);
                $penalty = (float) ($r->return_penalty ?? 0);
            }
            $use = $cancelled ? 'cancelled' : ($cf->has($r->id) ? 'cf' : ($sold->has($r->id) ? 'sale' : 'idle'));

            return [
                'id' => $r->id,
                'pnr' => $r->ticket_no,
                'passenger' => $r->passenger,
                'route' => $r->route,
                'issued' => (string) $r->purchase_date,
                'month' => $r->purchase_date ? substr((string) $r->purchase_date, 0, 7) : null,
                'status' => $r->status,
                'baggage_cost' => (float) ($r->extra_baggage_cost ?? 0),
                'baggage_due' => $r->vendor_id ? (float) ($r->extra_baggage_cost ?? 0) : 0.0,
                'cost' => (float) $r->amount + (float) ($r->extra_baggage_cost ?? 0),
                'net_cost' => ($cancelled ? $penalty : (float) $r->amount - ($returnOff ? $refund : 0.0)) + (float) ($r->extra_baggage_cost ?? 0),
                'paid' => (float) $r->paid_amount + (! $r->vendor_id && $r->portal_id ? (float) ($r->extra_baggage_cost ?? 0) : 0.0),
                'due' => ($cancelled ? 0.0 : (float) $r->due_amount) + ($r->vendor_id ? (float) ($r->extra_baggage_cost ?? 0) : 0.0),
                'refund' => $refund,
                'penalty' => $penalty,
                'cancelled_at' => $r->cancelled_at,
                'note' => $r->cancel_note,
                'vendor_id' => $r->vendor_id,
                'portal_id' => $r->portal_id,
                'seller' => $r->vendor_name ?: $r->portal_name,
                'seller_key' => $r->vendor_id ? 'v'.$r->vendor_id : ($r->portal_id ? 'p'.$r->portal_id : '-'),
                'use' => $use,
                'use_label' => $use === 'cf' ? ($cf[$r->id] ?: 'Contract flight') : ($use === 'sale' ? $sold[$r->id] : null),
            ];
        });
    }

    /** Manage Sales invoices dated in range, with the cost of the tickets on them. */
    private function sales(?Carbon $from, ?Carbon $to): Collection
    {
        $rows = DB::table('ticket_sales as s')->leftJoin('users as c', 'c.id', '=', 's.client_id')
            ->whereNull('s.deleted_at')->where('s.status', '!=', 'cancelled')
            ->when($from, fn ($q) => $q->where('s.sale_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('s.sale_date', '<=', $to->toDateString()))
            ->orderByDesc('s.sale_date')->orderByDesc('s.id')
            ->get(['s.id', 's.invoice_no', 's.client_id', 's.sale_date', 's.total_amount', 's.paid_amount', 's.due_amount', 's.status', 'c.name as client']);
        $items = DB::table('ticket_sale_items as i')->join('ticket_purchases as p', 'p.id', '=', 'i.ticket_purchase_id')
            ->whereIn('i.ticket_sale_id', $rows->pluck('id'))
            ->groupBy('i.ticket_sale_id')->selectRaw('i.ticket_sale_id, COUNT(*) n, SUM(p.amount) cost')->get()->keyBy('ticket_sale_id');

        return $rows->map(function ($s) use ($items) {
            $total = (float) $s->total_amount;
            $cost = (float) ($items[$s->id]->cost ?? 0);

            return [
                'id' => $s->id, 'invoice' => $s->invoice_no, 'client_id' => $s->client_id, 'client' => $s->client,
                'date' => (string) $s->sale_date, 'month' => substr((string) $s->sale_date, 0, 7), 'status' => $s->status,
                'items' => (int) ($items[$s->id]->n ?? 0), 'total' => $total, 'cost' => $cost,
                'profit' => $total - $cost, 'margin' => $total > 0 ? ($total - $cost) / $total * 100 : null,
                'paid' => (float) $s->paid_amount, 'due' => (float) $s->due_amount,
            ];
        });
    }

    /** Ticket Orders changes made in range (Ticketing-only ones), with what each did to the books. */
    private function changes(?int $companyId, ?Carbon $from, ?Carbon $to, string $seller): Collection
    {
        if (! Schema::hasTable('ticket_changes')) {
            return collect();
        }
        [$sk, $sid] = array_pad(explode(':', $seller, 2), 2, null);

        return DB::table('ticket_changes as c')
            ->join('ticket_purchases as p', 'p.id', '=', 'c.ticket_purchase_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.client_id')
            ->leftJoin('passport_holders as ph', 'ph.id', '=', 'p.passport_holder_id')
            ->where('c.source', 'ticketing')
            ->when($companyId, fn ($q) => $q->where('p.company_id', $companyId))
            ->when($from, fn ($q) => $q->where('c.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('c.created_at', '<=', $to))
            ->when($sk === 'v' && $sid, fn ($q) => $q->where('p.vendor_id', (int) $sid))
            ->when($sk === 'p' && $sid, fn ($q) => $q->whereNull('p.vendor_id')->where('p.portal_id', (int) $sid))
            ->orderByDesc('c.id')
            ->get(['c.*', 'p.ticket_no', 'u.name as client', 'ph.name as passenger'])
            ->map(function ($c) {
                $sold = (bool) $c->client_id;
                $refundedBack = in_array($c->kind, ['refund', 'void', 'return_cancel'], true) ? (float) $c->refund_amount : 0.0;

                return [
                    'id' => (int) $c->id, 'kind' => $c->kind, 'pnr' => $c->ticket_no, 'passenger' => $c->passenger,
                    'date' => (string) $c->created_at, 'month' => substr((string) $c->created_at, 0, 7),
                    'client_id' => $c->client_id ? (int) $c->client_id : null, 'client' => $c->client,
                    'cost_added' => (float) $c->cost_added, 'charged' => (float) $c->charged, 'reversed' => (float) $c->sale_reversed,
                    'revenue' => (float) $c->charged - (float) $c->sale_reversed,
                    'sold_cost' => $sold ? (float) $c->cost_added - $refundedBack : 0.0,
                    'note' => $c->note,
                ];
            });
    }

    /** Each party's running Party Statement balance, keyed by user id. */
    private function ledgerBalances(Collection $partyIds): array
    {
        if ($partyIds->isEmpty()) {
            return [];
        }
        $latest = Transaction::whereIn('user_id', $partyIds)->selectRaw('user_id, MAX(id) as last_id')->groupBy('user_id')->pluck('last_id', 'user_id');

        return Transaction::whereIn('id', $latest->values())->pluck('balance', 'user_id')->map(fn ($v) => (float) $v)->all();
    }
}
