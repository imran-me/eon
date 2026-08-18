@extends('layout.app')
@section('meta-information')
    <title>Petty Cash Report</title>
@endsection
@section('css')
@include('layout.table-design')
<style>
    /* Same restraint as the petty cash desk: ink and grey carry the page, and the
       only colour is reserved for the one thing that needs it — money the company
       still owes. A report read at month end must not look like a dashboard. */
    :root {
        --pr-ink: #0f172a; --pr-body: #334155; --pr-muted: #64748b;
        --pr-border: #e2e8f0; --pr-bg: #f8fafc; --pr-subtle: #f1f5f9;
        --pr-owe: #b45309;
    }

    .pr-shell { background: var(--pr-bg); padding: 18px; }
    .pr-card { background: #fff; border: 1px solid var(--pr-border); border-radius: 14px; margin-bottom: 16px; overflow: hidden; }
    .pr-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 18px 20px 14px; flex-wrap: wrap; }
    .pr-head h2 { font-size: 17px; font-weight: 800; color: var(--pr-ink); margin: 0; }
    .pr-head .sub { font-size: 12.5px; color: var(--pr-muted); margin-top: 3px; }

    .pr-btn { display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 9px;
        border: 1px solid var(--pr-border); background: #fff; color: var(--pr-body); font-size: 12.5px;
        font-weight: 700; cursor: pointer; text-decoration: none; transition: .15s; }
    .pr-btn:hover { background: var(--pr-subtle); color: var(--pr-ink); text-decoration: none; }
    .pr-btn.solid { background: var(--pr-ink); color: #fff; border-color: var(--pr-ink); }
    .pr-btn.solid:hover { background: #1e293b; color: #fff; }

    /* ── Period control ── */
    .pr-filters { border-top: 1px solid var(--pr-border); background: var(--pr-subtle); padding: 14px 20px; }
    .pr-tabs { display: inline-flex; border: 1px solid var(--pr-border); border-radius: 9px; overflow: hidden; background: #fff; }
    .pr-tab { padding: 7px 16px; font-size: 12.5px; font-weight: 700; color: var(--pr-muted);
        background: #fff; border: 0; cursor: pointer; text-decoration: none; }
    .pr-tab + .pr-tab { border-left: 1px solid var(--pr-border); }
    .pr-tab.is-on { background: var(--pr-ink); color: #fff; }
    .pr-tab:hover:not(.is-on) { background: var(--pr-subtle); color: var(--pr-ink); text-decoration: none; }

    .pr-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; margin-top: 12px; }
    .pr-group { display: flex; flex-direction: column; gap: 4px; min-width: 150px; }
    .pr-group label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--pr-muted); }
    .pr-group select, .pr-group input {
        padding: 7px 10px; border: 1px solid var(--pr-border); border-radius: 8px;
        font-size: 12.5px; background: #fff; color: var(--pr-ink);
    }

    /* ── Summary ── */
    .pr-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0;
        border-top: 1px solid var(--pr-border); }
    .pr-kpi { padding: 14px 18px; border-right: 1px solid var(--pr-border); }
    .pr-kpi:last-child { border-right: 0; }
    .pr-kpi .k { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--pr-muted); }
    .pr-kpi .v { font-size: 19px; font-weight: 800; color: var(--pr-ink); margin-top: 4px; line-height: 1.1; }
    .pr-kpi .n { font-size: 10.5px; color: var(--pr-muted); margin-top: 3px; }
    .pr-kpi.owe .v { color: var(--pr-owe); }
    /* The closing figure is the answer the report exists for. */
    .pr-kpi.lead { background: var(--pr-subtle); }

    /* ── Table ── */
    .pr-table th { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: var(--pr-muted); font-weight: 700; }
    .pr-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .pr-name { font-weight: 700; color: var(--pr-ink); font-size: 13.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
    .pr-name i { font-size: 9px; opacity: 0; transform: translateX(-3px); transition: .15s; }
    .pr-name:hover { text-decoration: underline; color: var(--pr-ink); }
    .pr-name:hover i { opacity: .55; transform: translateX(0); }
    .pr-owe-num { color: var(--pr-owe); font-weight: 800; }
    .pr-zero { color: #cbd5e1; }
    .pr-total-row td { background: var(--pr-subtle); font-weight: 800; color: var(--pr-ink); border-top: 2px solid var(--pr-border); }
    .pr-closed { font-size: 9.5px; font-weight: 700; color: var(--pr-muted); background: var(--pr-subtle); padding: 1px 7px; border-radius: 9px; margin-left: 6px; }

    .pr-empty { text-align: center; padding: 46px 0; color: #94a3b8; }

    .pr-note { padding: 12px 20px 18px; font-size: 11.5px; color: var(--pr-muted); line-height: 1.65; border-top: 1px solid var(--pr-border); }
</style>
@endsection

@section('main-content')

    @include('layout.expense-tabs')

@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
    $money = fn ($n) => '৳ ' . number_format($n, 2);

    // Carried into every period link so switching Daily/Weekly/Monthly keeps the
    // company and custodian you were already looking at.
    $keep = request()->only(['company_id', 'custodian_id']);
    $periodUrl = fn ($p) => route('role.petty-cash.report', ['role' => $role] + $keep + ['period' => $p]);
@endphp

<div class="pr-shell">
    <div class="pr-card">
        <div class="pr-head">
            <div>
                <h2><i class="fas fa-chart-column mr-2"></i>Petty Cash Report</h2>
                <div class="sub">
                    Where the cash went, and who is holding what — <strong>{{ $periodLabel }}</strong>.
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                {{-- The other half of the picture, one click away. This page says
                     where the cash WENT; that one says what it BOUGHT, and it can
                     already answer it — Money Source is set to Petty Cash for you. --}}
                <a href="{{ route('role.expenses.report', ['role' => $role] + $keep + ['payment_source' => 'petty', 'period' => $period, 'month' => $selectedMonth, 'year' => $selectedYear, 'date' => $from, 'from' => $customFrom, 'to' => $customTo]) }}"
                   class="pr-btn">
                    <i class="fas fa-tags"></i>What it was spent on
                </a>
                <a href="{{ route('role.petty-cash.report.print', ['role' => $role] + request()->query()) }}"
                   target="_blank" class="pr-btn solid">
                    <i class="fas fa-print"></i>Print / PDF
                </a>
                <a href="{{ route('role.petty-cash.index', ['role' => $role]) }}" class="pr-btn">
                    <i class="fas fa-arrow-left"></i>Back
                </a>
            </div>
        </div>

        {{-- ── Period ── --}}
        <div class="pr-filters">
            <div class="pr-tabs">
                @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'custom' => 'Custom'] as $key => $labelText)
                    <a href="{{ $periodUrl($key) }}" class="pr-tab {{ $period === $key ? 'is-on' : '' }}">{{ $labelText }}</a>
                @endforeach
            </div>

            <form method="GET" action="">
                <input type="hidden" name="period" value="{{ $period }}">
                <div class="pr-row">
                    @if($period === 'daily' || $period === 'weekly')
                        <div class="pr-group">
                            <label for="date">{{ $period === 'weekly' ? 'Any day in the week' : 'Date' }}</label>
                            <input type="date" id="date" name="date" value="{{ $from }}">
                        </div>
                    @elseif($period === 'monthly')
                        <div class="pr-group">
                            <label for="month">Month</label>
                            <select id="month" name="month">
                                @foreach(range(1, 12) as $m)
                                    <option value="{{ $m }}" {{ $selectedMonth === $m ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pr-group">
                            <label for="year">Year</label>
                            <select id="year" name="year">
                                @foreach(range(now()->year - 4, now()->year + 1) as $y)
                                    <option value="{{ $y }}" {{ $selectedYear === $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="pr-group">
                            <label for="from">From</label>
                            <input type="date" id="from" name="from" value="{{ $customFrom }}">
                        </div>
                        <div class="pr-group">
                            <label for="to">To</label>
                            <input type="date" id="to" name="to" value="{{ $customTo }}">
                        </div>
                    @endif

                    @if($companies->count() > 1)
                    <div class="pr-group">
                        <label for="company_id">Company</label>
                        <select id="company_id" name="company_id">
                            <option value="">All companies</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                    {{ $company->short_name ?: $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="pr-group">
                        <label for="custodian_id">Custodian</label>
                        <select id="custodian_id" name="custodian_id">
                            <option value="">Everyone</option>
                            @foreach($custodianFilters as $person)
                                <option value="{{ $person->id }}" {{ request('custodian_id') == $person->id ? 'selected' : '' }}>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="pr-btn solid"><i class="fas fa-filter"></i>Apply</button>
                </div>
            </form>
        </div>

        {{-- ── The period in one line ──
             Read left to right it is the whole arithmetic: what they started
             with, what came in, what went out, what is left. --}}
        <div class="pr-kpis">
            <div class="pr-kpi">
                <div class="k">Opening</div>
                <div class="v">{{ $money($totals['opening']) }}</div>
                <div class="n">Held when the period began</div>
            </div>
            <div class="pr-kpi">
                <div class="k">Issued</div>
                <div class="v">{{ $money($totals['issued']) }}</div>
                <div class="n">Handed out in the period</div>
            </div>
            <div class="pr-kpi">
                <div class="k">Spent</div>
                <div class="v">{{ $money($totals['spent']) }}</div>
                <div class="n">Backed by receipts</div>
            </div>
            <div class="pr-kpi">
                <div class="k">Returned</div>
                <div class="v">{{ $money($totals['returned']) }}</div>
                <div class="n">Given back to the drawer</div>
            </div>
            <div class="pr-kpi lead">
                <div class="k">Closing</div>
                <div class="v">{{ $money($totals['closing']) }}</div>
                <div class="n">Should be in pockets now</div>
            </div>
            @if($totals['owed'] > 0)
            <div class="pr-kpi owe">
                <div class="k">Owed to staff</div>
                <div class="v">{{ $money($totals['owed']) }}</div>
                <div class="n">Their own money, not paid back</div>
            </div>
            @endif
        </div>

        {{-- ── Per person ── --}}
        <div class="table-responsive" style="padding: 8px 12px 4px;">
            <table class="table w-full pr-table">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Custodian</th>
                        <th class="px-4 py-3 text-left">Company</th>
                        <th class="px-4 py-3 pr-num">Opening</th>
                        <th class="px-4 py-3 pr-num">Issued</th>
                        <th class="px-4 py-3 pr-num">Spent</th>
                        <th class="px-4 py-3 pr-num">Returned</th>
                        @if($totals['other'] != 0)
                        <th class="px-4 py-3 pr-num">Other</th>
                        @endif
                        <th class="px-4 py-3 pr-num">Closing</th>
                        <th class="px-4 py-3 pr-num">Owed</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('role.petty-cash.statement', ['role' => $role, 'float' => $r['float_id']]) }}"
                               class="pr-name" title="Open {{ $r['custodian_name'] }}'s full statement">
                                {{ $r['custodian_name'] }}<i class="fas fa-arrow-right"></i>
                            </a>
                            @unless($r['active'])<span class="pr-closed">Closed</span>@endunless
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $r['company_name'] }}</td>
                        <td class="px-4 py-3 pr-num text-sm {{ abs($r['opening']) < 0.001 ? 'pr-zero' : '' }}">{{ number_format($r['opening'], 2) }}</td>
                        <td class="px-4 py-3 pr-num text-sm {{ abs($r['issued']) < 0.001 ? 'pr-zero' : '' }}">{{ number_format($r['issued'], 2) }}</td>
                        <td class="px-4 py-3 pr-num text-sm {{ abs($r['spent']) < 0.001 ? 'pr-zero' : '' }}">{{ number_format($r['spent'], 2) }}</td>
                        <td class="px-4 py-3 pr-num text-sm {{ abs($r['returned']) < 0.001 ? 'pr-zero' : '' }}">{{ number_format($r['returned'], 2) }}</td>
                        @if($totals['other'] != 0)
                        <td class="px-4 py-3 pr-num text-sm {{ abs($r['other']) < 0.001 ? 'pr-zero' : '' }}">{{ number_format($r['other'], 2) }}</td>
                        @endif
                        <td class="px-4 py-3 pr-num text-sm font-bold">{{ number_format($r['closing'], 2) }}</td>
                        <td class="px-4 py-3 pr-num text-sm {{ $r['owed'] > 0.001 ? 'pr-owe-num' : 'pr-zero' }}">{{ number_format($r['owed'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="pr-empty">
                            <i class="fas fa-wallet block mb-2" style="font-size:26px; opacity:.35;"></i>
                            <div class="font-semibold text-gray-600">Nothing moved in this period</div>
                            <div class="text-xs mt-1">No cash was issued, spent or returned between
                                {{ \Carbon\Carbon::parse($from)->format('d M Y') }} and
                                {{ \Carbon\Carbon::parse($to)->format('d M Y') }}.</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                <tfoot>
                    <tr class="pr-total-row">
                        <td class="px-4 py-3" colspan="2">Total</td>
                        <td class="px-4 py-3 pr-num">{{ number_format($totals['opening'], 2) }}</td>
                        <td class="px-4 py-3 pr-num">{{ number_format($totals['issued'], 2) }}</td>
                        <td class="px-4 py-3 pr-num">{{ number_format($totals['spent'], 2) }}</td>
                        <td class="px-4 py-3 pr-num">{{ number_format($totals['returned'], 2) }}</td>
                        @if($totals['other'] != 0)
                        <td class="px-4 py-3 pr-num">{{ number_format($totals['other'], 2) }}</td>
                        @endif
                        <td class="px-4 py-3 pr-num">{{ number_format($totals['closing'], 2) }}</td>
                        <td class="px-4 py-3 pr-num {{ $totals['owed'] > 0.001 ? 'pr-owe-num' : '' }}">{{ number_format($totals['owed'], 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        <div class="pr-note">
            <strong>Opening + Issued − Spent − Returned = Closing.</strong>
            Every figure is read from the ledger, so this report and the accounts cannot disagree.
            Where a receipt came to more than the pocket held, only the float's share is counted as
            spent — the rest was the custodian's own money and appears under <em>Owed</em> instead,
            which is why that column is not part of the arithmetic above.
            @if($totals['other'] != 0)
            <br><strong>Other</strong> is movement on a float that was neither an issue, a return nor a
            receipt — an opening balance or a correction posted by hand. It is shown rather than folded
            into another column so the closing figure always adds up.
            @endif
        </div>
    </div>
</div>
@endsection
