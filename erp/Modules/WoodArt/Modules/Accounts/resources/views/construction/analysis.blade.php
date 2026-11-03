@extends('woodart::layouts.suite')
@section('wa-view')
@php extract(\Modules\WoodArt\Modules\Accounts\Support\SiteCosts::constructionPresentation($dept->id), EXTR_SKIP); @endphp
@include('woodart::project-nav', ['navActive' => 'reports'])
@include('woodart::construction.filters')
@php
    $totalMinor = $minor($sheet['total']);
    $claims = $sheet['entries']->where('posted', true)->whereNotNull('reimburse_to_user_id')->groupBy('reimburse_to_user_id');
    $peopleNames = \Illuminate\Support\Facades\DB::table('users')->whereIn('id', $claims->keys())->pluck('name', 'id');
    $drillParams = \Illuminate\Support\Arr::only($constructionFilters, ['from','to','q','person']);
    $analysisColors = ['#00a99b','#338fff','#8b7beb','#e6ab4b','#b87581','#64becf','#738ba7'];
    $analysisPosition = 0;
    $analysisStops = [];
    foreach ($sheet['byHead'] as $index => $head) {
        $analysisNext = $analysisPosition + ($percent($minor($head['total']), $totalMinor) ?? 0);
        $analysisStops[] = $analysisColors[$index % count($analysisColors)].' '.$analysisPosition.'% '.$analysisNext.'%';
        $analysisPosition = $analysisNext;
    }
    $largestHeadMinor = max(1, (int) $sheet['byHead']->max(fn ($head) => $minor($head['total'])));
@endphp
<div class="wa-cpm">
    <div class="wa-cpm-kpis">
        <a class="wa-cpm-kpi is-blue" href="{{ $projectUrl($drillParams + ['approval' => 'approved']) }}"><span class="wa-cpm-icon"><i class="bi bi-receipt"></i></span><span><span class="wa-cpm-label">Approved Expense Cost</span><strong class="wa-cpm-value">{{ $moneyMinor($totalMinor) }}</strong><span class="wa-cpm-note">Selected period and filters</span></span></a>
        <div class="wa-cpm-kpi"><span class="wa-cpm-icon"><i class="bi bi-list-check"></i></span><span><span class="wa-cpm-label">Approved Transactions</span><strong class="wa-cpm-value">{{ $sheet['entries']->where('posted', true)->count() }}</strong><span class="wa-cpm-note">Expense-desk records</span></span></div>
        <a class="wa-cpm-kpi is-amber" href="{{ $projectUrl(array_merge($drillParams, ['approval' => 'pending'])) }}"><span class="wa-cpm-icon"><i class="bi bi-hourglass-split"></i></span><span><span class="wa-cpm-label">Pending Approval</span><strong class="wa-cpm-value">{{ $money($sheet['pending']) }}</strong><span class="wa-cpm-note">Excluded from approved cost</span></span></a>
        <div class="wa-cpm-kpi is-violet"><span class="wa-cpm-icon"><i class="bi bi-tags"></i></span><span><span class="wa-cpm-label">Expense Heads</span><strong class="wa-cpm-value">{{ $sheet['byHead']->count() }}</strong><span class="wa-cpm-note">From actual expense classifications</span></span></div>
    </div>
    <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><div><h2>Category-wise Cost Analysis</h2><span class="wa-cpm-note">Approved expenses · {{ $from ?: 'Beginning' }} to {{ $to ?: 'present' }}</span></div><a href="{{ $projectUrl($drillParams + ['approval' => 'approved']) }}">Detailed transactions <i class="bi bi-arrow-right"></i></a></div>
        <div class="wa-cpm-table-wrap"><table class="wa-cpm-table"><thead><tr><th>Expense head</th><th class="wa-cpm-num">Transactions</th><th class="wa-cpm-num">Approved cost</th><th class="wa-cpm-num">% of total</th><th>Share</th><th></th></tr></thead><tbody>
            @forelse($sheet['byHead'] as $head)
            @php $share = $percent($minor($head['total']), $totalMinor) ?? 0; @endphp
            <tr><td><a class="wa-cpm-primary" href="{{ $projectUrl(array_merge($drillParams, $headFilters($head))) }}">{{ $head['name'] }}</a></td><td class="wa-cpm-num">{{ $head['n'] }}</td><td class="wa-cpm-num"><strong>{{ $money($head['total']) }}</strong></td><td class="wa-cpm-num">{{ $share }}%</td><td style="min-width:140px"><div class="wa-cpm-bar"><span style="width:{{ min(100,max(0,$share)) }}%"></span></div></td><td><a href="{{ $projectUrl(array_merge($drillParams, $headFilters($head))) }}">View <i class="bi bi-arrow-right"></i></a></td></tr>
            @empty<tr><td colspan="6" class="wa-cpm-empty">No approved expenses match these filters.</td></tr>@endforelse
        </tbody><tfoot><tr><th>Total</th><th class="wa-cpm-num">{{ $sheet['entries']->where('posted', true)->count() }}</th><th class="wa-cpm-num">{{ $moneyMinor($totalMinor) }}</th><th colspan="3"></th></tr></tfoot></table></div>
    </section>
    <div class="wa-cpm-analysis-grid">
        <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Category Cost Comparison</h2><span class="wa-cpm-note">Approved actual cost · budget not allocated</span></div><div class="wa-cpm-panel-body"><div class="wa-cpm-bars" role="img" aria-label="Approved cost comparison by expense head">@forelse($sheet['byHead']->take(8) as $head)<div class="wa-cpm-bar-column" title="{{ $head['name'] }} · {{ $money($head['total']) }}"><div class="wa-cpm-bar-stick" style="height:{{ max(3, round(($minor($head['total']) / $largestHeadMinor) * 100, 1)) }}%"></div><span>{{ $head['name'] }}</span></div>@empty<div class="wa-cpm-empty">No approved category costs.</div>@endforelse</div></div></section>
        <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Expense Distribution</h2></div><div class="wa-cpm-panel-body">@if($sheet['byHead']->isEmpty())<div class="wa-cpm-empty">No approved category costs.</div>@else<div class="wa-cpm-donut-wrap"><div class="wa-cpm-donut" style="background:conic-gradient({{ implode(',', $analysisStops) }})"><div class="wa-cpm-donut-center"><strong>{{ $moneyMinor($totalMinor) }}</strong><span class="wa-cpm-note">Total cost</span></div></div><ul class="wa-cpm-legend">@foreach($sheet['byHead']->take(6) as $index => $head)<li><a href="{{ $projectUrl(array_merge($drillParams, $headFilters($head))) }}"><span class="wa-cpm-dot" style="background:{{ $analysisColors[$index % count($analysisColors)] }}"></span>{{ $head['name'] }}</a><strong>{{ $percent($minor($head['total']), $totalMinor) ?? 0 }}%</strong></li>@endforeach</ul></div>@endif</div></section>
    </div>
    <section class="wa-cpm-panel" id="people"><div class="wa-cpm-panel-head"><div><h2>Person-wise Own-pocket Expenses</h2><span class="wa-cpm-note">Verified claimant relationship · approved expenses in the selected period</span></div></div><div class="wa-cpm-table-wrap"><table class="wa-cpm-table"><thead><tr><th>Own-pocket claimant</th><th class="wa-cpm-num">Expense count</th><th class="wa-cpm-num">Approved amount</th><th>Last expense</th><th></th></tr></thead><tbody>
        @forelse($claims as $personId => $entries)
        @php $claimTotal = $entries->reduce(fn ($carry, $entry) => $carry + $minor($entry->amount), 0); @endphp
        <tr><td><a href="{{ $projectUrl(array_merge($drillParams,['person' => $personId, 'approval' => 'approved'])) }}">{{ $peopleNames[$personId] ?? '#'.$personId }}</a></td><td class="wa-cpm-num">{{ $entries->count() }}</td><td class="wa-cpm-num">{{ $moneyMinor($claimTotal) }}</td><td>{{ $entries->max('expense_date') ? \Carbon\Carbon::parse($entries->max('expense_date'))->format('d M Y') : '—' }}</td><td><a href="{{ $projectUrl(array_merge($drillParams,['person' => $personId, 'approval' => 'approved'])) }}">View transactions</a></td></tr>
        @empty<tr><td colspan="5" class="wa-cpm-empty">No approved own-pocket claims match these filters.</td></tr>@endforelse
    </tbody></table></div></section>
    <div class="wa-cpm-grid">
        <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Monthly Approved Costs</h2></div><div class="wa-cpm-table-wrap"><table class="wa-cpm-table"><thead><tr><th>Month</th><th class="wa-cpm-num">Transactions</th><th class="wa-cpm-num">Cost</th></tr></thead><tbody>@forelse($sheet['byMonth'] as $month)<tr><td>{{ \Carbon\Carbon::parse($month['month'].'-01')->format('M Y') }}</td><td class="wa-cpm-num">{{ $month['n'] }}</td><td class="wa-cpm-num">{{ $money($month['total']) }}</td></tr>@empty<tr><td colspan="3" class="wa-cpm-empty">No approved costs in this period.</td></tr>@endforelse</tbody></table></div></section>
        <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Recorded Paid By</h2><span class="wa-cpm-note">Entry reference text</span></div><div class="wa-cpm-table-wrap"><table class="wa-cpm-table"><thead><tr><th>Recorded payer</th><th class="wa-cpm-num">Transactions</th><th class="wa-cpm-num">Cost</th></tr></thead><tbody>@forelse($sheet['byPayer'] as $payer)<tr><td>{{ $payer['name'] }}</td><td class="wa-cpm-num">{{ $payer['n'] }}</td><td class="wa-cpm-num">{{ $money($payer['total']) }}</td></tr>@empty<tr><td colspan="3" class="wa-cpm-empty">No recorded payer summary.</td></tr>@endforelse</tbody></table></div></section>
    </div>
    <div class="wa-cpm-grid"><section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Reports &amp; Accounting</h2></div><div class="wa-cpm-panel-body wa-cpm-tools"><a class="wap-btn wap-btn-ghost" href="{{ $projectUrl(['view' => 'collections']) }}">Client Collections</a><a class="wap-btn wap-btn-ghost" href="{{ $projectUrl(['view' => 'payables']) }}">Supplier Payables</a><a class="wap-btn wap-btn-ghost" href="{{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::route('project.journals', ['department' => $dept->id]) }}">Journal audit trail</a></div></section><section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Data Coverage</h2></div><div class="wa-cpm-panel-body"><p class="wa-cpm-note">Company/category budgets are not allocated to these projects, so budget variance and profit forecasts are unavailable. A general Spent By relationship is also unavailable: the report uses explicit own-pocket claims, and never treats the creator or recorded payer as the spender.</p></div></section></div>
</div>
@endsection
