@extends('layout.app')

@section('title', 'Party Activity Report')

@section('main-content')
@php
    $role = request()->route('role');
    $money = fn ($value) => '৳'.number_format(abs((float) $value), 2);
    $query = request()->except('page');
    $filterQuery = request()->except(['page', 'period', 'from', 'to']);
    $periodUrl = fn (string $period) => route('role.party-statement.activity', ['role' => $role] + $filterQuery + ['period' => $period]);
    $partyTypeLabel = ['agent' => 'Agents', 'vendor' => 'Vendors', 'client' => 'Customers'][$partyType] ?? 'All Parties';
    $selectedPartyName = optional($parties->firstWhere('id', $selectedPartyId))->name ?? 'All Specific Parties';
@endphp
<div class="p-4 md:p-6 bg-slate-50 min-h-screen par">
    @include('layout.party-tabs')

    <section class="par-head">
        <div><div class="par-kicker">Management report</div><h1>Party Activity Report</h1><p>Period movement and current outstanding across agents, vendors and customers.</p></div>
    </section>

    <div class="par-filter-shell">
        <form method="GET" class="par-filter" id="activityFilterForm">
            <input type="hidden" name="period" id="activityPeriod" value="{{ $preset }}">
            <div class="par-scope">
                <label><span>Company</span><b class="par-select-value">{{ $selectedCompany->name ?? 'All Companies' }}</b><select name="company" class="par-native-select"><option value="">All Companies</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected((int)$companyId === (int)$company->id)>{{ $company->name }}</option>@endforeach</select></label>
                <label><span>Party Type</span><b class="par-select-value">{{ $partyTypeLabel }}</b><select name="party_type" class="par-native-select"><option value="all">All Parties</option><option value="agent" @selected($partyType==='agent')>Agents</option><option value="vendor" @selected($partyType==='vendor')>Vendors</option><option value="client" @selected($partyType==='client')>Customers</option></select></label>
                <label><span>Specific Party</span><b class="par-select-value">{{ $selectedPartyName }}</b><select name="party_id" class="par-native-select"><option value="">All Specific Parties</option>@foreach($parties as $party)<option value="{{ $party->id }}" @selected((int)$selectedPartyId === (int)$party->id)>{{ $party->name }}</option>@endforeach</select></label>
            </div>

            <div class="par-period-row">
                <div class="par-presets" aria-label="Quick date filters">
                    <a class="{{ $preset === 'today' ? 'active' : '' }}" href="{{ $periodUrl('today') }}">Today</a>
                    <a class="{{ $preset === 'yesterday' ? 'active' : '' }}" href="{{ $periodUrl('yesterday') }}">Yesterday</a>
                    <a class="{{ $preset === 'last7' ? 'active' : '' }}" href="{{ $periodUrl('last7') }}">Last 7 Days</a>
                    <a class="{{ $preset === 'month' ? 'active' : '' }}" href="{{ $periodUrl('month') }}">This Month</a>
                    <a class="{{ $preset === 'last_month' ? 'active' : '' }}" href="{{ $periodUrl('last_month') }}">Last Month</a>
                    <a class="{{ $preset === 'year' ? 'active' : '' }}" href="{{ $periodUrl('year') }}">This Year</a>
                    <a class="{{ $preset === 'all' ? 'active' : '' }}" href="{{ $periodUrl('all') }}">All</a>
                </div>

                <div class="par-range">
                    <input type="date" name="from" aria-label="From date" value="{{ request('from', $from) }}" data-initial="{{ request('from', $from) }}">
                    <span>to</span>
                    <input type="date" name="to" aria-label="To date" value="{{ request('to', $to) }}" data-initial="{{ request('to', $to) }}">
                </div>

                <div class="par-actions">
                    <button type="submit"><i class="fas fa-filter"></i> Apply</button>
                    <a class="par-reset" href="{{ route('role.party-statement.activity', ['role' => $role]) }}"><i class="fas fa-undo"></i> Reset</a>
                    <a class="par-print" id="activityPrint" href="{{ route('role.party-statement.activity-print', ['role' => $role] + $query) }}"><i class="fas fa-print"></i> Print</a>
                </div>
            </div>
        </form>
    </div>

    <div class="par-period">
        <i class="fas fa-calendar-alt"></i> Reporting period: <b>{{ $periodLabel }}</b>
        @if($selectedCompany)
            · {{ $selectedCompany->name }}
        @else
            · All Companies
        @endif
    </div>

    <section class="par-kpis">
        <article><span>Active Parties</span><b>{{ number_format($summary['parties']) }}</b><small>with movement in period</small></article>
        <article><span>Transactions</span><b>{{ number_format($summary['transactions']) }}</b><small>ledger entries</small></article>
        <article class="red"><span>Total Charged / Cost</span><b>{{ $money($summary['debit']) }}</b><small>period debit</small></article>
        <article class="green"><span>Total Paid / Received</span><b>{{ $money($summary['credit']) }}</b><small>period credit</small></article>
        <article class="violet"><span>Period Net Movement</span><b>{{ $money($summary['net']) }} {{ $summary['net'] > 0 ? 'Dr' : ($summary['net'] < 0 ? 'Cr' : '') }}</b><small>debit minus credit</small></article>
        <article class="amber"><span>Current Outstanding</span><b>{{ $money($summary['current']) }} {{ $summary['current'] > 0 ? 'Dr' : ($summary['current'] < 0 ? 'Cr' : '') }}</b><small>whole scoped ledger</small></article>
    </section>

    @if($companyBreakdown->count() > 1)
    <details class="par-company"><summary><i class="fas fa-building"></i> Company-wise breakdown <span>{{ $companyBreakdown->count() }} companies</span></summary><div class="par-company-grid">@foreach($companyBreakdown as $company)<div><b>{{ $company['name'] }}</b><span>{{ $company['count'] }} entries</span><em>{{ $money($company['debit']) }} charged · {{ $money($company['credit']) }} settled</em><strong>{{ $money($company['net']) }} {{ $company['net'] >= 0 ? 'Dr' : 'Cr' }}</strong></div>@endforeach</div></details>
    @endif

    <section class="par-table"><header><div><h2>Party-wise Summary</h2><p>Period figures are separate from the current all-time balance.</p></div><span>{{ $rows->count() }} parties</span></header><div class="overflow-x-auto"><table><thead><tr><th>Party</th><th>Type</th><th class="num">Transactions</th><th class="num">Charged / Cost</th><th class="num">Paid / Received</th><th class="num">Period Net</th><th class="num">Current Balance</th><th>Last Activity</th></tr></thead><tbody>
        @forelse($rows as $row)<tr><td><b>{{ $row['name'] }}</b><small><a class="par-code" href="{{ route('role.party-statement.index', array_filter(['role' => $role, 'party_id' => $row['id'], 'company' => $companyId])) }}" title="Open {{ $row['name'] }} ledger">{{ $row['code'] }}</a>@if($row['phone']) · {{ $row['phone'] }}@endif</small></td><td><span class="par-type {{ $row['type'] }}">{{ $row['type']==='client'?'Customer':ucfirst($row['type']) }}</span></td><td class="num">{{ $row['count'] }}</td><td class="num debit">{{ $money($row['debit']) }}</td><td class="num credit">{{ $money($row['credit']) }}</td><td class="num {{ $row['net'] < 0 ? 'credit':'debit' }}">{{ $money($row['net']) }} {{ $row['net'] > 0?'Dr':($row['net'] < 0?'Cr':'') }}</td><td class="num balance">{{ $money($row['current']) }} {{ $row['current'] > 0?'Dr':($row['current'] < 0?'Cr':'') }}</td><td>{{ $row['last_at'] ? \Carbon\Carbon::parse($row['last_at'])->format('d M Y') : '—' }}</td></tr>
        @empty<tr><td colspan="8" class="empty">No party transactions found for this selection.</td></tr>@endforelse
    </tbody></table></div></section>
</div>

<style>
.par{color:#24324a}.par-head{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:18px 20px;border-radius:12px 12px 0 0;background:linear-gradient(100deg,#1d4ed8,#4f46e5 58%,#7c3aed);color:#fff}.par-kicker{font-size:9px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#c7d2fe}.par-head h1{margin:3px 0;font-size:19px;font-weight:800}.par-head p{font-size:10px;color:#dbeafe}.par-filter-shell{padding:10px;background:#fff;border:1px solid #e2e8f0;overflow-x:auto}.par-filter{display:flex;align-items:center;gap:7px;min-width:1180px}.par-filter>select,.par-range input{height:34px;padding:0 9px;border:1px solid #dbe3ee;border-radius:7px;background:#fff;color:#334155;font-size:10px}.par-filter>select:nth-of-type(1){width:175px}.par-filter>select:nth-of-type(2){width:125px}.par-filter>select:nth-of-type(3){width:190px}.par-presets{display:flex;height:34px;border:1px solid #dbe3ee;border-radius:7px;overflow:hidden;white-space:nowrap}.par-presets a{display:flex;align-items:center;padding:0 9px;border-right:1px solid #e2e8f0;color:#475569;font-size:9px;font-weight:700;text-decoration:none}.par-presets a:last-child{border-right:0}.par-presets a:hover,.par-presets a.active{background:#ecfdf5;color:#047857}.par-range{display:flex;align-items:center;gap:6px}.par-range input{width:130px}.par-range span{color:#94a3b8;font-size:9px}.par-actions{display:flex;gap:6px;margin-left:auto}.par-actions button,.par-actions a{display:inline-flex;align-items:center;justify-content:center;gap:5px;height:34px;padding:0 11px;border-radius:7px;font-size:9px;font-weight:800;text-decoration:none;white-space:nowrap}.par-actions button{border:0;background:#2563eb;color:#fff}.par-actions .par-reset,.par-actions .par-print{border:1px solid #cbd5e1;background:#fff;color:#475569}.par-actions .par-print{color:#1e3a5f}.par-period{padding:8px 12px;border:1px solid #bfdbfe;border-top:0;background:#eff6ff;color:#1e40af;font-size:10px}.par-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px;margin:13px 0}.par-kpis article{padding:12px;border:1px solid #dbe4ef;border-radius:10px;background:#fff}.par-kpis span,.par-kpis small{display:block;font-size:8px;color:#64748b;text-transform:uppercase}.par-kpis b{display:block;margin:4px 0;color:#1d4ed8;font-size:16px}.par-kpis .red b{color:#dc2626}.par-kpis .green b{color:#059669}.par-kpis .violet b{color:#7c3aed}.par-kpis .amber b{color:#b45309}.par-company,.par-table{margin-top:12px;border:1px solid #dbe4ef;border-radius:10px;background:#fff}.par-company summary{padding:12px 14px;cursor:pointer;font-size:11px;font-weight:800}.par-company summary span{float:right;color:#94a3b8;font-weight:500}.par-company-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;padding:0 12px 12px}.par-company-grid div{padding:10px;border-radius:8px;background:#f8fafc}.par-company-grid b,.par-company-grid span,.par-company-grid em,.par-company-grid strong{display:block}.par-company-grid b{font-size:11px}.par-company-grid span,.par-company-grid em{font-size:9px;color:#64748b;font-style:normal}.par-company-grid strong{margin-top:4px;font-size:12px;color:#4f46e5}.par-table header{display:flex;justify-content:space-between;align-items:center;padding:13px 15px}.par-table h2{font-size:13px;font-weight:800}.par-table header p{font-size:9px;color:#94a3b8}.par-table header>span{font-size:9px;color:#64748b}.par-table table{width:100%;border-collapse:collapse}.par-table th{padding:9px 12px;background:#f1f5f9;color:#64748b;font-size:8px;text-align:left;text-transform:uppercase}.par-table td{padding:10px 12px;border-top:1px solid #e2e8f0;font-size:10px}.par-table td b,.par-table td small{display:block}.par-table td small{margin-top:2px;color:#94a3b8;font-size:8px}.par-table .num{text-align:right}.par-table .debit{color:#dc2626;font-weight:700}.par-table .credit{color:#059669;font-weight:700}.par-table .balance{color:#1d4ed8;font-weight:800}.par-type{padding:3px 7px;border-radius:999px;background:#e0e7ff;color:#4338ca;font-size:8px;font-weight:800}.par-type.vendor{background:#ede9fe;color:#6d28d9}.par-type.client{background:#cffafe;color:#0e7490}.par-table .empty{text-align:center;padding:30px;color:#94a3b8}@media(max-width:1100px){.par-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:650px){.par-head{align-items:flex-start}.par-kpis{grid-template-columns:1fr 1fr}}
.par-filter-shell{overflow:visible}.par-filter{display:grid;gap:9px;min-width:0}.par-scope{display:grid;grid-template-columns:1.1fr .8fr 1.35fr;gap:8px}.par-scope label{min-width:0}.par-scope label span{display:block;margin:0 0 4px 2px;color:#64748b;font-size:8px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.par-scope select{width:100%;height:34px;padding:0 9px;border:1px solid #dbe3ee;border-radius:7px;background:#fff;color:#334155;font-size:10px}.par-period-row{display:flex;align-items:center;gap:8px;padding-top:9px;border-top:1px solid #eef2f7}.par-period-row .par-presets{flex:0 0 auto}.par-period-row .par-presets a{padding:0 10px}.par-period-row .par-range{display:flex;flex:1 1 auto;min-width:280px}.par-period-row .par-range input{width:100%;min-width:125px;margin-top:0}.par-period-row .par-actions{flex:0 0 auto}.par-presets a:hover,.par-presets a.active{background:#dff8ef;color:#047857}@media(max-width:1050px){.par-period-row{overflow-x:auto;padding-bottom:3px}.par-period-row>*{flex-shrink:0}.par-period-row .par-range{width:290px}.par-scope{grid-template-columns:1fr 1fr 1.2fr}}@media(max-width:650px){.par-scope{grid-template-columns:1fr}.par-period-row{align-items:center}}
.par-filter{grid-template-columns:1fr}
.par-scope .select2-container{display:block;width:100%!important}.par-scope .select2-container .select2-selection--single{display:flex;align-items:center;height:34px!important;border:1px solid #dbe3ee!important;border-radius:7px!important;background:#fff}.par-scope .select2-container .select2-selection__rendered{display:flex!important;align-items:center;width:100%;height:32px;padding:0 32px 0 9px!important;line-height:normal!important;color:#334155;font-size:10px;font-weight:500}.par-scope .select2-container .select2-selection__rendered>.tkm{display:flex;align-items:center;width:100%;height:32px;gap:7px}.par-scope .select2-container .select2-selection__rendered .tkm-t{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.par-scope .select2-container .select2-selection__arrow{top:0!important;right:5px!important;width:22px!important;height:32px!important}.par-scope .select2-container--focus .select2-selection--single,.par-scope .select2-container--open .select2-selection--single{border-color:#60a5fa!important;box-shadow:0 0 0 2px rgba(59,130,246,.12)}
.par .par-scope label{position:relative;display:block}.par .par-scope select.par-native-select{display:block!important;width:100%!important;height:34px!important;margin:0!important;padding:0 32px 0 10px!important;border:1px solid #dbe3ee!important;border-radius:7px!important;background:#fff!important;color:#334155!important;font-family:inherit!important;font-size:10px!important;font-weight:500!important;line-height:32px!important;text-indent:0!important;vertical-align:middle!important;appearance:none!important;-webkit-appearance:none!important}.par .par-scope label::after{position:absolute;right:11px;bottom:13px;width:0;height:0;border-top:4px solid #64748b;border-right:4px solid transparent;border-left:4px solid transparent;content:"";pointer-events:none}.par .par-scope select.par-native-select:focus{border-color:#60a5fa!important;outline:0!important;box-shadow:0 0 0 2px rgba(59,130,246,.12)!important}
.par-filter-shell{overflow-x:auto}.par-filter{display:flex;align-items:flex-end;gap:7px;min-width:1240px}.par-scope,.par-period-row{display:contents}.par-scope label span{display:none}.par-scope label:nth-child(1){width:190px}.par-scope label:nth-child(2){width:115px}.par-scope label:nth-child(3){width:190px}.par-period-row .par-presets a{padding:0 7px}.par-period-row .par-range{flex:0 0 218px;min-width:218px}.par-period-row .par-range input{min-width:0}.par-period-row .par-actions{margin-left:auto}.par-period-row .par-actions button,.par-period-row .par-actions a{padding:0 9px}@media(max-width:1050px){.par-filter{display:flex;grid-template-columns:none}.par-period-row{overflow:visible;padding:0;border:0}.par-period-row .par-range{width:auto}.par-scope{grid-template-columns:none}}@media(max-width:650px){.par-scope{grid-template-columns:none}}
.par-scope label{height:34px;border:1px solid #dbe3ee;border-radius:7px;background:#fff}.par-scope .par-select-value{display:block;overflow:hidden;height:32px;padding:0 28px 0 10px;color:#334155;font-size:10px;font-weight:500;line-height:32px;text-overflow:ellipsis;white-space:nowrap}.par-scope .select2-container{display:none!important}.par .par-scope select.par-native-select{position:absolute!important;inset:0!important;z-index:2!important;width:100%!important;height:34px!important;clip:auto!important;clip-path:none!important;cursor:pointer!important;opacity:0!important}.par .par-scope label::after{z-index:1}
.par-code{color:#2563eb;font-weight:800;text-decoration:none}.par-code:hover{text-decoration:underline}
</style>
<script>
document.getElementById('activityFilterForm')?.addEventListener('submit', function () {
    const from = this.querySelector('[name="from"]');
    const to = this.querySelector('[name="to"]');

    if (from.value !== from.dataset.initial || to.value !== to.dataset.initial) {
        document.getElementById('activityPeriod').value = 'custom';
    }
});

function keepActivityFiltersNative() {
    if (!window.jQuery?.fn?.select2) {
        return;
    }

    window.jQuery('.par-native-select.select2-hidden-accessible').each(function () {
        window.jQuery(this).select2('destroy');
    });
}

keepActivityFiltersNative();
document.addEventListener('DOMContentLoaded', keepActivityFiltersNative);
window.setTimeout(keepActivityFiltersNative, 300);

document.querySelectorAll('.par-native-select').forEach(function (select) {
    select.addEventListener('change', function () {
        const value = this.options[this.selectedIndex]?.text || '';
        this.closest('label')?.querySelector('.par-select-value').replaceChildren(value);
    });
});

document.getElementById('activityPrint')?.addEventListener('click', function (event) {
    event.preventDefault();
    const currentUrl = this.href;

    if (!window.Swal) {
        window.open(currentUrl, '_blank');
        return;
    }

    const from = document.querySelector('#activityFilterForm [name="from"]')?.value || '';
    const to = document.querySelector('#activityFilterForm [name="to"]')?.value || '';

    Swal.fire({
        title: 'Print activity report',
        width: 540,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-print"></i> Print',
        confirmButtonColor: '#4f46e5',
        focusConfirm: false,
        html: `
            <style>
                .par-print-list{display:flex;flex-direction:column;gap:7px;text-align:left}
                .par-print-option{display:flex;align-items:flex-start;gap:10px;padding:11px 12px;border:1px solid #e2e8f0;border-radius:9px;cursor:pointer}
                .par-print-option:has(input:checked){border-color:#6366f1;background:#f5f6ff}
                .par-print-option input[type=radio]{margin-top:3px;accent-color:#4f46e5}
                .par-print-copy{display:flex;flex:1;flex-direction:column;gap:3px}
                .par-print-copy b{color:#1e293b;font-size:13px}
                .par-print-copy small{color:#64748b;font-size:11px}
                .par-print-dates{display:flex;align-items:center;gap:8px;margin-top:5px}
                .par-print-dates input{width:145px;padding:6px 8px;border:1px solid #dbe3ee;border-radius:6px;color:#334155;font-size:11px}
                .par-print-dates span{color:#94a3b8;font-size:10px}
            </style>
            <div class="par-print-list">
                <label class="par-print-option">
                    <input type="radio" name="activityPrintMode" value="shown" checked>
                    <span class="par-print-copy"><b>What the report shows now</b><small>Uses the current company, party and period filters.</small></span>
                </label>
                <label class="par-print-option">
                    <input type="radio" name="activityPrintMode" value="range">
                    <span class="par-print-copy"><b>A date range</b><small>Keep the current party filters and choose another period.</small><span class="par-print-dates"><input type="date" id="activityPrintFrom" value="${from}"><span>to</span><input type="date" id="activityPrintTo" value="${to}"></span></span>
                </label>
            </div>`,
        preConfirm: function () {
            const mode = document.querySelector('input[name="activityPrintMode"]:checked')?.value;
            if (mode !== 'range') {
                return currentUrl;
            }

            const rangeFrom = document.getElementById('activityPrintFrom').value;
            const rangeTo = document.getElementById('activityPrintTo').value;
            if (!rangeFrom || !rangeTo) {
                Swal.showValidationMessage('Select both start and end dates.');
                return false;
            }

            const url = new URL(currentUrl);
            url.searchParams.set('period', 'custom');
            url.searchParams.set('from', rangeFrom > rangeTo ? rangeTo : rangeFrom);
            url.searchParams.set('to', rangeFrom > rangeTo ? rangeFrom : rangeTo);
            return url.toString();
        },
    }).then(function (result) {
        if (result.isConfirmed && result.value) {
            window.open(result.value, '_blank');
        }
    });
});
</script>
@endsection

