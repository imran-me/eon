@extends('layout.app')

@section('title', 'Party Statement')

@section('main-content')
@php
    $role    = Str::slug(Auth::user()->getRoleNames()->first());
    $company = request()->query('company');
    $project = request()->query('project');
    $fmt     = fn ($v) => '৳' . number_format($v, 0);
    // Where a voucher leads when a bank entry stands behind it: that account's
    // ledger on Manage Bank, landed on that entry (?bank=&entry=, read by
    // banks/dashboard.blade.php openFromQuery()). Route::has(): a missing route
    // must degrade to plain text, never throw while drawing the dashboard.
    $bankDashboard = \Illuminate\Support\Facades\Route::has('role.banks.dashboard')
        ? route('role.banks.dashboard', ['role' => $role]) : null;
    $bankEntryUrl = fn (?array $e) => ($bankDashboard && $e)
        ? $bankDashboard . '?bank=' . (int) $e['bank_id'] . '&entry=' . (int) $e['entry_id'] : null;
    $ledgerUrl = function (string $type, ?int $partyId = null) use ($role, $company, $project) {
        $params = ['role' => $role];
        if ($company) $params['company'] = $company;
        if ($project) $params['project'] = $project;
        if ($partyId) $params['party_id'] = $partyId;
        return route('role.party-statement.' . $type . 's', $params);
    };
    // Accent per type — the same three the role pills use on the statement.
    $accent = [
        'agent'  => ['bg' => '#fffbeb', 'fg' => '#b45309', 'bar' => '#f59e0b', 'icon' => 'fa-user-tie'],
        'client' => ['bg' => '#eff6ff', 'fg' => '#1d4ed8', 'bar' => '#3b82f6', 'icon' => 'fa-user'],
        'vendor' => ['bg' => '#f5f3ff', 'fg' => '#6d28d9', 'bar' => '#8b5cf6', 'icon' => 'fa-truck-field'],
    ];
    $kindLabel = [
        'contract_file_client'=>'File process', 'contract_file'=>'File process cost', 'contract_file_service_cost'=>'File service cost',
        'contract_file_client_adjustment'=>'File process adjustment', 'contract_file_vendor_adjustment'=>'File cost adjustment',
        'party_payment' => 'Payment', 'party_payment_reversal' => 'Payment reversal',
        'customer_advance_refund' => 'Advance returned', 'customer_advance_refund_reversal' => 'Advance return reversal',
        'customer_service_refund' => 'Service refund paid', 'customer_service_refund_reversal' => 'Service refund reversal',
        'customer_advance_received' => 'Payment', 'customer_advance_received_reversal' => 'Payment reversal', 'supplier_advance_paid_reversal' => 'Supplier advance reversal',
        'bank_transfer' => 'Payment', 'party_invoice' => 'Invoice',
        'ticket_sale' => 'Ticket', 'ticket_purchase' => 'Ticket purchase', 'ticket_purchase_refund' => 'Ticket cancelled — refund', 'contract_flight_reissue_fee' => 'Reissue fee', 'ticket_change_fee' => 'Ticket re-issue charge', 'ticket_change_fare' => 'Fare difference', 'contract_flight_reissue_fare' => 'Fare difference', 'ticket_change_charge' => 'Ticket change charge', 'ticket_sale_reversal' => 'Ticket sale reversed', 'vendor_refund' => 'Refund received', 'flight_sale' => 'Flight',
        'file_sale' => 'File', 'visa_sale' => 'Visa', 'visa_process' => 'Visa processing',
        'contract_flight_passenger_agent' => 'Flight passenger',
        'contract_flight_passenger_change' => 'Flight change charge',
        'contract_flight_passenger_agent_reversal' => 'Flight package reversal', 'contract_flight_airline_penalty' => 'Airline penalty', 'contract_flight_fare_difference' => 'Fare difference',
        'contract_flight_price_revision' => 'Flight package revised',   // a cancelled return lowered it (2026-10-14)
        'contract_flight_service_cost' => 'Flight service cost', 'opening_balance' => 'Opening',
    ];
    // Payment-method pill: the bank feed's VIA_PILL, plus the two methods only
    // the party ledger records.
    $viaPill = [
        'cash'           => ['Cash',   '#dcfce7', '#15803d'],
        'bank_transfer'  => ['Bank',   '#dbeafe', '#1e40af'],
        'cheque'         => ['Cheque', '#fef3c7', '#92400e'],
        'card'           => ['Card',   '#ede9fe', '#5b21b6'],
        'mobile_banking' => ['Mobile', '#ffe4e6', '#be123c'],
    ];
@endphp

<form method="GET" action="{{ route('role.party-statement.index', ['role' => $role]) }}" class="mb-4 flex items-center justify-end gap-2 print:hidden">
    <label for="psCompany" class="text-xs font-semibold text-slate-500">Company</label>
    <select id="psCompany" name="company" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
        @if(Auth::user()->is_super_admin || Auth::user()->can('view all party statement'))<option value="">All Companies / Group</option>@endif
        @foreach($companies as $co)<option value="{{ $co->id }}" @selected((int)$companyId === (int)$co->id)>{{ $co->name }}</option>@endforeach
    </select>
    @if($companyId)
    <label for="psProject" class="text-xs font-semibold text-slate-500 ml-2">Project</label>
    <select id="psProject" name="project" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
        <option value="">All projects</option>
        @foreach($projects as $projectRow)<option value="{{ $projectRow->id }}" @selected((int)$projectId === (int)$projectRow->id)>{{ $projectRow->name }}</option>@endforeach
    </select>
    @endif
</form>

<style>
/* ── Party-type cards ─────────────────────────────────────────────────────
   One card per type; the accent (stripe, icon, bars, code pills) comes in
   through the --ps-* custom properties set on the card, so the three cards
   share one stylesheet and differ only in colour. */
.ps-card { position: relative; display: flex; flex-direction: column; background: #fff; border: 1px solid #e8edf4; border-radius: 18px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 14px 32px -22px rgba(15,23,42,.22); overflow: hidden; }
.ps-card::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 4px; background: var(--ps-grad); }
.ps-card-h { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 14px 9px; }
.ps-card-t { display: flex; align-items: center; gap: 10px; min-width: 0; }
.ps-card-t .ps-ic { flex: none; width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #fff; background: var(--ps-grad); box-shadow: 0 6px 14px -6px var(--ps-bar); }
.ps-card-name { display: flex; align-items: center; gap: 7px; font-size: 13.5px; font-weight: 700; color: #334155; line-height: 1.2; }
.ps-card-meta { margin-top: 2px; font-size: 10.5px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ps-card-meta b { color: #475569; font-weight: 700; }
.ps-card-meta .ps-dot { display: inline-block; width: 3px; height: 3px; border-radius: 50%; background: #cbd5e1; margin: 0 6px; vertical-align: middle; }
.ps-pill { display: inline-block; padding: 1px 7px; border-radius: 20px; font-size: 10px; font-weight: 800; background: var(--ps-soft); color: var(--ps-fg); white-space: nowrap; }
.ps-open { flex: none; display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 999px; font-size: 11px; font-weight: 700; color: #4338ca; background: #eef2ff; text-decoration: none; white-space: nowrap; transition: background .15s, color .15s; }
.ps-open:hover { background: #4f46e5; color: #fff; text-decoration: none; }
.ps-open i { font-size: 10px; transition: transform .15s; }
.ps-open:hover i { transform: translateX(2px); }

/* Hero — the outstanding total, with the advance beside it */
.ps-hero { display: grid; grid-template-columns: 1.4fr 1fr; gap: 10px; padding: 2px 14px 8px; }
.ps-fig-l { display: flex; align-items: center; gap: 5px; font-size: 9.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #94a3b8; }
.ps-fig-l .ps-led { width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: .85; }
.ps-hero-v { font-size: 21px; font-weight: 800; letter-spacing: -.02em; color: #0f172a; line-height: 1.1; margin-top: 3px; font-variant-numeric: tabular-nums; }
.ps-side-v { font-size: 15px; font-weight: 800; letter-spacing: -.01em; color: #0f172a; line-height: 1.15; margin-top: 5px; font-variant-numeric: tabular-nums; }
.ps-fig-s { font-size: 10.5px; color: #94a3b8; margin-top: 2px; }
.ps-meter { display: flex; align-items: center; gap: 8px; margin-top: 5px; font-size: 10.5px; color: #64748b; }
.ps-meter .ps-track { flex: 1; height: 4px; border-radius: 3px; background: #edf1f6; overflow: hidden; }
.ps-meter .ps-track > span { display: block; height: 100%; border-radius: 3px; background: var(--ps-grad); }
.ps-meter b { color: #334155; font-weight: 700; font-variant-numeric: tabular-nums; }

/* This month — billed against received, and how much of it is settled */
.ps-month { margin: 0 14px 8px; padding: 8px 12px 9px; border-radius: 10px; background: #f8fafc; border: 1px solid #eef2f7; }
.ps-month-h { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 5px; }
.ps-month-h .ps-fig-l { color: #64748b; }
.ps-month-h .ps-tag { font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 999px; background: #fff; border: 1px solid #e2e8f0; color: #475569; white-space: nowrap; }
.ps-month-h .ps-tag.is-good { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
.ps-month-h .ps-tag.is-warn { background: #fffbeb; border-color: #fde68a; color: #b45309; }
.ps-month-g { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.ps-month-g .ps-k { font-size: 10px; font-weight: 600; color: #94a3b8; }
.ps-month-g .ps-v { font-size: 13px; font-weight: 700; color: #334155; margin-top: 0; font-variant-numeric: tabular-nums; }
.ps-month-g .ps-v.is-in { color: #059669; }
.ps-month-g > div:last-child { text-align: right; }
.ps-settle { height: 5px; border-radius: 3px; background: #e2e8f0; overflow: hidden; margin-top: 6px; display: flex; }
.ps-settle > span { display: block; height: 100%; background: linear-gradient(90deg, #34d399, #059669); border-radius: 3px; }

/* Largest outstanding — five rows, each a link to that party's statement */
.ps-top-h { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 0 14px 2px; }
.ps-stale { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 700; color: #b45309; background: #fffbeb; border: 1px solid #fde68a; border-radius: 999px; padding: 2px 8px; text-decoration: none; white-space: nowrap; }
.ps-stale:hover { background: #fef3c7; text-decoration: none; }
.ps-top { list-style: none; margin: 0; padding: 0 6px 5px; flex: 1; }
.ps-top li { display: grid; grid-template-columns: minmax(0, 1fr) 96px; align-items: center; column-gap: 10px; padding: 3px 8px; border-radius: 8px; cursor: pointer; transition: background .12s; }
.ps-top li:hover { background: var(--ps-soft); }
.ps-top .ps-who { min-width: 0; }
.ps-top .ps-nm { display: flex; align-items: center; gap: 6px; min-width: 0; font-size: 12px; font-weight: 600; color: #334155; line-height: 1.2; }
.ps-top .ps-nm > span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ps-top .ps-nm .ps-late { flex: none; font-size: 9.5px; color: #d97706; }
.ps-top .ps-line { display: flex; align-items: center; gap: 8px; margin-top: 2px; }
.ps-top .ps-bar { flex: 1; height: 4px; border-radius: 2px; background: #edf1f6; overflow: hidden; }
.ps-top .ps-bar > span { display: block; height: 100%; border-radius: 2px; background: var(--ps-grad); }
.ps-top .ps-amt { text-align: right; font-size: 12px; font-weight: 700; color: #334155; white-space: nowrap; font-variant-numeric: tabular-nums; }
.ps-top .ps-share { text-align: right; font-size: 9.5px; color: #94a3b8; margin-top: 1px; font-variant-numeric: tabular-nums; }
.ps-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 9px; font-weight: 700; letter-spacing: .02em; color: #475569; white-space: nowrap; }
.ps-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; padding: 16px 14px; font-size: 12px; color: #94a3b8; text-align: center; }
.ps-empty i { font-size: 18px; color: #34d399; }
/* Narrow card (sidebar open on a ~1130px window, or a zoomed-in 1365): the
   card queries its own width, not the viewport, so browser zoom cannot fool
   it. Below 300px the ledger button drops to its arrow, the advance sits
   under the hero instead of beside it, and the share loses the words. */
.ps-card { container-type: inline-size; }
@container (max-width: 300px) {
    .ps-open { width: 26px; padding: 0; justify-content: center; }
    .ps-open > span { display: none; }
    .ps-hero { grid-template-columns: 1fr; gap: 8px; }
    .ps-hero > div:last-child { display: flex; align-items: baseline; flex-wrap: wrap; gap: 0 8px; padding-top: 8px; border-top: 1px dashed #eef2f7; }
    .ps-hero > div:last-child .ps-side-v { margin-top: 0; font-size: 13.5px; }
    .ps-hero > div:last-child .ps-fig-s { margin-top: 0; }
    .ps-top li { grid-template-columns: minmax(0, 1fr) 82px; }
    .ps-top .ps-share > span { display: none; }
}
/* .ps-panel / .ps-recent — see partials/feed-table-styles, shared with the statement. */
</style>

@include('layout.party-tabs')
@include('party-statement.partials.bar-styles')
@include('party-statement.partials.feed-table-styles')
<div class="min-h-screen bg-gray-50">

    <div class="px-2.5 py-2 space-y-2">

        {{-- ══ ONE CARD PER PARTY TYPE ══ --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-2">
            @foreach($types as $type => $t)
                @php
                    $a = $accent[$type];
                    $receivableSide = $t['meta']['side'] === 'receivable';
                    $owedLabel = $receivableSide ? 'Receivable' : 'Payable';
                    $upLabel   = $receivableSide ? 'Billed' : 'Purchased';
                    $downLabel = $receivableSide ? 'Received' : 'Paid';
                    $top = $t['top'];
                    $max = $top->max('owed') ?: 1;
                    $owedColor = $t['owed'] > 0 ? ($receivableSide ? '#dc2626' : '#b45309') : '#0f172a';
                    $owePct = $t['count'] > 0 ? round($t['owing'] / $t['count'] * 100) : 0;
                    // Settled share of the month: credits against debits. With
                    // nothing raised this month, anything received is "ahead".
                    $mUp = $month[$type]['up']; $mDown = $month[$type]['down'];
                    $settlePct = $mUp > 0 ? min(100, round($mDown / $mUp * 100)) : ($mDown > 0 ? 100 : 0);
                    if ($mUp <= 0 && $mDown <= 0)   { $settleTag = ['', 'Quiet month']; }
                    elseif ($mUp <= 0)              { $settleTag = ['is-good', 'Ahead']; }
                    elseif ($settlePct >= 100)      { $settleTag = ['is-good', 'Fully settled']; }
                    elseif ($settlePct >= 50)       { $settleTag = ['', $settlePct . '% settled']; }
                    else                            { $settleTag = ['is-warn', $settlePct . '% settled']; }
                    // Customers share their column with the Company Ledger card
                    // (owner, 2026-09-28): with nobody owing, their empty
                    // "Largest receivable" list gives its room to that card.
                    $stackCompany = $type === 'client';
                    $hideTop = $stackCompany && $top->isEmpty();
                @endphp
                @if($stackCompany)<div class="flex flex-col gap-2">@endif
                <div class="ps-card"style="--ps-fg:{{ $a['fg'] }};--ps-soft:{{ $a['bg'] }};--ps-bar:{{ $a['bar'] }};--ps-grad:linear-gradient(135deg,{{ $a['bar'] }},{{ $a['fg'] }})">
                    <div class="ps-card-h">
                        <div class="ps-card-t">
                            <span class="ps-ic"><i class="fas {{ $a['icon'] }}"></i></span>
                            <div style="min-width:0;">
                                <div class="ps-card-name">{{ $t['meta']['plural'] }} <span class="ps-pill">{{ $t['count'] }}</span></div>
                                <div class="ps-card-meta">
                                    <b>{{ $t['owing'] }}</b> {{ $t['owing'] === 1 ? 'owes' : 'owe' }}<span class="ps-dot"></span><b>{{ $t['active'] }}</b> active @if($t['stale'] > 0)<span class="ps-dot"></span><b style="color:#b45309">{{ $t['stale'] }}</b> follow-up @endif
                                </div>
                            </div>
                        </div>
                        <a href="{{ $ledgerUrl($type) }}" class="ps-open" data-no-prefetch><span>Open ledger</span> <i class="fas fa-arrow-right"></i></a>
                    </div>

                    <div class="ps-hero">
                        <div>
                            <div class="ps-fig-l" style="color:{{ $owedColor }}"><span class="ps-led"></span>{{ $owedLabel }}</div>
                            <div class="ps-hero-v" style="color:{{ $owedColor }}">{{ $fmt($t['owed']) }}</div>
                            <div class="ps-meter">
                                <span class="ps-track"><span style="width:{{ $owePct }}%"></span></span>
                                <span><b>{{ $t['owing'] }}</b> of {{ $t['count'] }} {{ $t['owing'] === 1 ? 'owes' : 'owe' }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="ps-fig-l">Advance</div>
                            <div class="ps-side-v" style="color:{{ $t['advance'] > 0 ? '#059669' : '#94a3b8' }}">{{ $fmt($t['advance']) }}</div>
                            <div class="ps-fig-s">{{ $receivableSide ? 'paid ahead by them' : 'paid ahead by us' }}</div>
                        </div>
                    </div>

                    <div class="ps-month">
                        <div class="ps-month-h">
                            <div class="ps-fig-l"><i class="far fa-calendar" style="font-size:10px;"></i> This month</div>
                            <span class="ps-tag {{ $settleTag[0] }}">{{ $settleTag[1] }}</span>
                        </div>
                        <div class="ps-month-g">
                            <div>
                                <div class="ps-k">{{ $upLabel }}</div>
                                <div class="ps-v">{{ $fmt($mUp) }}</div>
                            </div>
                            <div>
                                <div class="ps-k">{{ $downLabel }}</div>
                                <div class="ps-v is-in">{{ $fmt($mDown) }}</div>
                            </div>
                        </div>
                        <div class="ps-settle" title="{{ $downLabel }} {{ $fmt($mDown) }} of {{ $fmt($mUp) }} {{ strtolower($upLabel) }}"><span style="width:{{ $settlePct }}%"></span></div>
                    </div>

                    @unless($hideTop)
                    <div class="ps-top-h">
                        <span class="ps-fig-l">Largest {{ strtolower($owedLabel) }}</span>
                        @if($t['stale'] > 0)
                            <a href="{{ $ledgerUrl($type) }}" class="ps-stale" data-no-prefetch title="Money outstanding and nothing has moved in {{ \App\Http\Controllers\Dashboard\PartyStatementController::STALE_DAYS }} days">
                                <i class="fas fa-clock"></i> {{ $t['stale'] }} need follow-up
                            </a>
                        @endif
                    </div>
                    @if($top->isEmpty())
                        <div class="ps-empty"><i class="fas fa-circle-check"></i>Nothing outstanding.</div>
                    @else
                        <ul class="ps-top">
                            @foreach($top as $p)
                                <li onclick="location.href='{{ $ledgerUrl($type, $p['id']) }}'" title="Open {{ $p['name'] }}&rsquo;s statement">
                                    <span class="ps-who">
                                        <span class="ps-nm"><span>{{ $p['name'] }}</span>@if($p['stale'])<i class="fas fa-clock ps-late" title="Nothing has moved recently"></i>@endif</span>
                                        <span class="ps-line">
                                            <span class="ps-code">{{ $p['code'] }}</span>
                                            <span class="ps-bar"><span style="width:{{ max(4, round($p['owed'] / $max * 100)) }}%"></span></span>
                                        </span>
                                    </span>
                                    <span>
                                        <div class="ps-amt">{{ $fmt($p['owed']) }}</div>
                                        <div class="ps-share">{{ $t['owed'] > 0 ? round($p['owed'] / $t['owed'] * 100) : 0 }}%<span> of total</span></div>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @endunless
                </div>
                @if($stackCompany)
                    @include('party-statement.partials.company-card', ['cl' => $companyLedger ?? ['installed' => false], 'fmt' => $fmt])
                    </div>
                @endif
            @endforeach
        </div>

        {{-- ══ RECENT ACTIVITY ══
             Column for column the shape of Manage Bank's Recent Transactions
             feed, read from the party's side: the bank column becomes the
             party (name, direction badge, code, that party's opening →
             closing on this row), the beneficiary column becomes "paid via"
             (method and the bank it went through), and Company Balance
             becomes the party's balance after the row. --}}
        <div class="ps-panel" id="feedCard">
            <div class="ps-card-h">
                <div class="ps-card-t">
                    <span class="ps-ic" style="background:#eef2ff;color:#4338ca"><i class="fas fa-clock-rotate-left"></i></span>
                    Latest entries
                    <span class="text-xs font-medium text-gray-400">across every party · <span id="pfPeriodLabel">Last 14 days</span></span>
                </div>
                <span class="text-[11px] text-gray-400" id="pfPeriodNote">This feed holds the last 14 days only</span>
            </div>
            {{-- The bank feed's bar: search, Today / Yesterday / Last Week /
                 All 14 Days, a date range pulled back to the 14-day window,
                 Print. All of it works on the rows already on the page. --}}
            @include('party-statement.partials.table-bar', [
                'prefix'      => 'pf',
                'placeholder' => 'Search party, code, entry, voucher, bank, date or amount…',
                'segments'    => [['today', 'Today'], ['yesterday', 'Yesterday'], ['week', 'Last Week'], ['all', 'All 14 Days']],
                'dates'       => true,
                'print'       => true,
            ])
            @if($recent->isEmpty())
                <div class="ps-empty">No ledger entries in the last 14 days.</div>
            @else
            <div style="overflow-x:auto">
                <table class="ps-recent">
                    <thead>
                        <tr>
                            <th class="ps-pick"><input type="checkbox" class="ps-pick-box ps-pick-all" title="Tick every row shown" aria-label="Select all shown"></th>
                            <th class="ps-date ps-c-date">Date</th><th class="ps-c-party">Party</th><th class="ps-c-entry">Entry</th><th class="ps-details ps-c-details">Details</th><th class="ps-c-via">Paid via</th>
                            <th class="ps-c-dr" style="text-align:right">Debit</th><th class="ps-c-cr" style="text-align:right">Credit</th><th class="ps-c-bal" style="text-align:right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recent as $r)
                            @php
                                $a = $r['type'] ? $accent[$r['type']] : null;
                                $receivableRow = $r['type'] && \App\Http\Controllers\Dashboard\PartyStatementController::TYPES[$r['type']]['side'] === 'receivable';
                                $isDr = $r['debit'] > 0;
                                // What the movement means for this kind of party — the
                                // feed's In/Out badge, read from the ledger's side.
                                $flow = $isDr ? ($receivableRow ? 'Charged' : 'Bill') : ($receivableRow ? 'Received' : 'Paid');
                                $recDay = substr((string) $r['recorded_at'], 0, 10);
                                $recTime = $recDay === $r['date'] ? \Carbon\Carbon::parse($r['recorded_at'])->format('H:i:s') : null;
                                $via = $viaPill[$r['method']] ?? null;
                                $drcr = fn ($v) => $fmt(abs($v)) . ' ' . ($v < 0 ? 'Cr' : 'Dr');
                                $balCol = fn ($v) => $v < 0 ? '#dc2626' : '#1d4ed8';
                            @endphp
                            {{-- The whole row opens the party's statement, as a
                                 bank row opens the account's ledger. --}}
                            <tr data-date="{{ $r['date'] }}" data-pick-id="{{ $r['id'] }}" style="--ps-detail-accent:{{ $a['fg'] ?? '#4f46e5' }};--ps-detail-soft:{{ $a['bg'] ?? '#eef2ff' }};"
                                data-hay="{{ strtolower(implode(' ', array_filter([$r['party'], $r['code'], $kindLabel[$r['kind']] ?? $r['kind'], $r['remarks'], $r['ref'], $r['bank_entry']['txn_no'] ?? null, implode(' ', array_column($r['reason'], 'text')), $r['bank'], $r['bank_no'], $r['method'], $r['date'] ? \Carbon\Carbon::parse($r['date'])->format('d/m/Y') : null, $r['debit'] > 0 ? number_format($r['debit']) . ' ' . number_format($r['debit'], 0, '', '') : null, $r['credit'] > 0 ? number_format($r['credit']) . ' ' . number_format($r['credit'], 0, '', '') : null]))) }}"
                                @if($r['type']) data-href="{{ $ledgerUrl($r['type'], $r['user_id']) }}" onclick="location.href=this.dataset.href" title="Open {{ $r['party'] }}'s statement" @endif>
                                <td class="ps-pick"><input type="checkbox" class="ps-pick-box" aria-label="Select this entry"></td>
                                <td class="ps-date">
                                    {{ $r['date'] ? \Carbon\Carbon::parse($r['date'])->format('d/m/Y') : '—' }}
                                    @if($recTime)<div class="ps-sub" style="margin-top:2px;">{{ $recTime }}</div>@endif
                                </td>
                                <td>
                                    <div class="ps-nm">{{ $r['party'] ?? '—' }}<span class="ps-flow" style="background:{{ $isDr ? '#fee2e2' : '#dcfce7' }};color:{{ $isDr ? '#b91c1c' : '#15803d' }};"><i class="fas fa-arrow-{{ $isDr ? 'up' : 'down' }}"></i> {{ $flow }}</span></div>
                                    @if($r['code'])<div class="ps-sub">{{ \App\Http\Controllers\Dashboard\PartyStatementController::TYPES[$r['type']]['label'] }} ID: <span class="ps-id">{{ $r['code'] }}</span></div>@endif
                                    <div class="ps-sub">Opening: <span style="color:{{ $balCol($r['balance_before']) }};font-weight:600;">{{ $drcr($r['balance_before']) }}</span>
                                        &nbsp;·&nbsp; Closing: <span style="color:{{ $balCol($r['balance_after']) }};font-weight:600;">{{ $drcr($r['balance_after']) }}</span></div>
                                </td>
                                <td>
                                    <div class="ps-nm">{{ $kindLabel[$r['kind']] ?? ucwords(str_replace('_', ' ', (string) $r['kind'])) }}</div>
                                    @if($r['remarks'])<div class="ps-sub">{{ Str::limit($r['remarks'], 70) }}</div>@endif
                                    {{-- The voucher: a link to the bank transaction behind it
                                         when one resolved, plain text otherwise. stopPropagation —
                                         the row itself opens the party's statement. --}}
                                    @if($bankEntryUrl($r['bank_entry'] ?? null))
                                        <a href="{{ $bankEntryUrl($r['bank_entry']) }}" class="ps-vno ps-vno-link" onclick="event.stopPropagation();" title="Open this transaction on Manage Bank"><i class="fas fa-building-columns"></i> {{ $r['ref'] ?: ($r['bank_entry']['txn_no'] ?? '') }}</a>
                                    @elseif($r['ref'])
                                        <div class="ps-vno">{{ $r['ref'] }}</div>
                                    @endif
                                </td>
                                {{-- What the entry was for, read from the module that
                                     posted it: passenger and passport, PNR and route,
                                     visa country, flight and stage. See reasonsFor(). --}}
                                <td class="ps-details">
                                    {{-- Drawn exactly as the Party cell: the name in .ps-nm, the
                                         rest in .ps-sub (owner, 2026-09-15: "maintain view like
                                         the party"). --}}
                                    @forelse($r['reason'] as $line)
                                        <div class="{{ $loop->first ? 'ps-nm' : 'ps-sub' }}">{{ $line['text'] }}</div>
                                    @empty
                                        <span class="ps-amt-none">—</span>
                                    @endforelse
                                    {{-- The arrow: opens the full record in a row beneath, as the
                                         statement's "Passengers" does. stopPropagation — the row
                                         itself opens the party's statement. --}}
                                    @if($r['detail'])
                                        <button type="button" class="ps-more" id="pf-more-{{ $r['id'] }}" onclick="event.stopPropagation(); psToggleDetail('pf-detail-{{ $r['id'] }}', this)"><i class="fas fa-chevron-down"></i> Details</button>
                                    @endif
                                </td>
                                <td>
                                    @if($via || $r['bank'])
                                        <div class="ps-nm">{{ $r['bank'] ?: ($via[0] ?? '') }}@if($via)<span class="ps-flow" style="background:{{ $via[1] }};color:{{ $via[2] }};">{{ $via[0] }}</span>@endif</div>
                                        @if($r['bank_no'])<div class="ps-sub">A/C {{ $r['bank_no'] }}</div>@endif
                                    @else
                                        <span class="ps-amt-none">—</span>
                                    @endif
                                </td>
                                <td style="text-align:right;">@if($r['debit'] > 0)<span class="ps-amt-dr">{{ $fmt($r['debit']) }}</span>@else<span class="ps-amt-none">—</span>@endif</td>
                                <td style="text-align:right;">@if($r['credit'] > 0)<span class="ps-amt-cr">{{ $fmt($r['credit']) }}</span>@else<span class="ps-amt-none">—</span>@endif</td>
                                <td style="text-align:right;"><span class="ps-bal" style="color:{{ $balCol($r['balance_after']) }};">{{ $fmt(abs($r['balance_after'])) }} <small>{{ $r['balance_after'] < 0 ? 'Cr' : 'Dr' }}</small></span></td>
                            </tr>
                            @if($r['detail'])
                                {{-- Follows its row; the feed's filter hides it with the row. --}}
                                {{-- Same cells as the row above: the hidden tick cell first, then
                                     one cell across the eight visible columns. A colspan that does
                                     not match the row structure makes the browser invent a column,
                                     which is what squeezed Entry when this opened. --}}
                                <tr class="ps-detail-row" id="pf-detail-{{ $r['id'] }}" style="display:none;--ps-detail-accent:{{ $a['fg'] ?? '#4f46e5' }};--ps-detail-soft:{{ $a['bg'] ?? '#eef2ff' }};">
                                    <td class="ps-pick"></td>
                                    <td colspan="8" style="padding:0 12px 12px 12px;background:#f8fafc;">
                                        <div style="padding-top:10px;">@include('party-statement.partials.detail-table', ['detail' => $r['detail']])</div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div id="pfEmpty" class="ps-empty" style="display:none;">No entries match.</div>
            @endif
        </div>

    </div>
</div>
@endsection

@section('js')
<script>
@include('party-statement.partials.bar-scripts')

// Open/close the full record under a row. Remembers the state on the row so
// the feed's filter can restore it after hiding and showing the row.
function psToggleDetail(rowId, btn) {
    const det = document.getElementById(rowId);
    if (!det) return;
    const open = det.style.display === 'none';
    if (open) {
        det.closest('tbody').querySelectorAll('tr.ps-detail-row').forEach(other => {
            if (other === det) return;
            other.style.display = 'none';
            const otherRow = other.previousElementSibling;
            if (otherRow) {
                otherRow.dataset.open = '0';
                otherRow.querySelector('.ps-more')?.classList.remove('is-open');
            }
        });
    }
    det.style.display = open ? '' : 'none';
    const row = det.previousElementSibling;
    if (row) row.dataset.open = open ? '1' : '0';
    if (btn) btn.classList.toggle('is-open', open);
}

// ── The feed's bar: search + range over the rows already on the page ──────────
(function initFeedBar() {
    const rows = Array.from(document.querySelectorAll('#feedCard tbody tr[data-date]'));
    const win  = @json($feedWindow);
    let q = '', from = '', to = '', range = 'all';
    const names = { today: 'Today', yesterday: 'Yesterday', week: 'Last 7 days', all: 'Last 14 days' };

    const paint = (clamped) => {
        let shown = 0;
        rows.forEach(tr => {
            const d = tr.dataset.date;
            const ok = (!q || tr.dataset.hay.includes(q)) && (!from || d >= from) && (!to || d <= to);
            tr.style.display = ok ? '' : 'none';
            // Its detail row (if opened) goes with it.
            const det = tr.nextElementSibling;
            if (det && det.classList.contains('ps-detail-row')) det.style.display = (ok && tr.dataset.open === '1') ? '' : 'none';
            if (ok) shown++;
        });
        psPaintCount('pf', q !== '', shown, rows.length);
        const empty = document.getElementById('pfEmpty');
        if (empty) empty.style.display = rows.length && !shown ? '' : 'none';
        const label = document.getElementById('pfPeriodLabel'), note = document.getElementById('pfPeriodNote');
        if (label) label.textContent = range === 'custom' ? (from === to ? psDMY(from) : psDMY(from) + ' – ' + psDMY(to)) : (names[range] || 'Last 14 days');
        if (note) note.textContent = clamped ? 'Pulled back — this feed holds the last 14 days only' : 'This feed holds the last 14 days only';
    };

    psInitBar('pf', {
        onSearch: term => { q = term; paint(false); },
        onRange: (key, f, t, clamped) => { range = key; from = f; to = t; paint(clamped); },
        ranges: {
            today:     () => [psYmd(new Date()), psYmd(new Date())],
            yesterday: () => [psDaysBack(1), psDaysBack(1)],
            week:      () => [psDaysBack(6), psYmd(new Date())],
            all:       () => ['', ''],
        },
        window: win,
        initial: 'all',
    });
    // Print — Manage Bank's chooser; the sheet is built server-side from the
    // whole ledger, so a day outside the fourteen still prints.
    psPrintSetup('pf', {
        host:       document.querySelector('#feedCard table'),
        dates:      true,
        noun:       'entries',
        title:      'Print ledger entries',
        period:     () => range === 'all' ? { from: win.from, to: win.to } : { from, to },
        shownLabel: () => range === 'custom' ? (from === to ? psDMY(from) : psDMY(from) + ' – ' + psDMY(to)) : (names[range] || 'Last 14 days'),
        searching:  () => q !== '',
        rows:       () => rows.map(tr => [Number(tr.dataset.pickId), tr.dataset.date]),
        url: ({ from: f, to: t, ids }) => {
            const p = new URLSearchParams();
            if (ids && ids.length) p.set('items', ids.join(','));
            else { if (f) p.set('date_from', f); if (t) p.set('date_to', t); }
            return @json(route('role.party-statement.print', ['role' => $role, 'company' => request('company')])) + (@json(request('company') ? '&' : '?')) + p;
        },
    });
    paint(false);
})();
</script>
@endsection
