@php
    $filterBase = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::route('project', ['department' => $dept->id]);
    $cf = $constructionFilters ?? [];
    $periodParams = \Illuminate\Support\Arr::only(request()->query(), ['q','head','category','person','approval','from','to','without_head','without_category','per_page']);
    $reportParams = ['view' => $workspace] + $periodParams;
@endphp
<div class="wa-cpm">
    <form method="GET" action="{{ $filterBase }}" class="wa-cpm-filter">
        <input type="hidden" name="view" value="{{ $workspace }}">
        @foreach(['head','category','person','without_head','without_category'] as $field)@if(isset($cf[$field]))<input type="hidden" name="{{ $field }}" value="{{ $cf[$field] }}">@endif @endforeach
        <div class="wa-cpm-field wa-cpm-search"><label for="cpm-exp-q">Search transactions</label><input id="cpm-exp-q" type="search" name="q" value="{{ $q }}" maxlength="160"></div>
        <div class="wa-cpm-field"><label for="cpm-exp-from">From</label><input id="cpm-exp-from" type="date" name="from" value="{{ $from }}"></div>
        <div class="wa-cpm-field"><label for="cpm-exp-to">To</label><input id="cpm-exp-to" type="date" name="to" value="{{ $to }}"></div>
        <div class="wa-cpm-field"><label for="cpm-exp-approval">Expense approval</label><select id="cpm-exp-approval" name="approval"><option value="">All approvals</option><option value="approved" @selected(($cf['approval'] ?? '') === 'approved')>Approved</option><option value="pending" @selected(($cf['approval'] ?? '') === 'pending')>Pending</option></select></div>
        @if($workspace === 'expenses')<div class="wa-cpm-field"><label for="cpm-exp-size">Rows</label><select id="cpm-exp-size" name="per_page">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected((int) ($cf['per_page'] ?? 25) === $size)>{{ $size }}</option>@endforeach</select></div>@endif
        <div class="wa-cpm-tools"><button type="submit" class="wap-btn wap-btn-primary">Apply</button><a class="wap-btn wap-btn-ghost" href="{{ $filterBase.'?'.http_build_query(['view' => $workspace]) }}">Reset</a><a class="wap-btn wap-btn-ghost" data-no-prefetch href="{{ $filterBase.'?'.http_build_query($reportParams + ['export' => 'csv']) }}"><i class="bi bi-download"></i> CSV</a>@if($workspace === 'expenses')<button type="button" class="wap-btn wap-btn-ghost" data-wa-project-print="{{ $filterBase }}"><i class="bi bi-printer"></i> Print / PDF</button>@else<a class="wap-btn wap-btn-ghost" data-no-prefetch href="{{ $filterBase.'?'.http_build_query($reportParams + ['print' => 1]) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Print / PDF</a>@endif</div>
    </form>
    <div class="wa-cpm-tools" style="margin-bottom:12px">
        @foreach(['All time' => [null, null], 'Today' => [now()->toDateString(),now()->toDateString()], 'This week' => [now()->startOfWeek()->toDateString(),now()->endOfWeek()->toDateString()], 'This month' => [now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString()], 'Last month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(),now()->subMonthNoOverflow()->endOfMonth()->toDateString()], 'This year' => [now()->startOfYear()->toDateString(),now()->endOfYear()->toDateString()]] as $preset => [$presetFrom,$presetTo])<a class="wap-btn wap-btn-ghost" href="{{ $filterBase.'?'.http_build_query(array_merge($reportParams, ['from' => $presetFrom, 'to' => $presetTo])) }}">{{ $preset }}</a>@endforeach
        @if(!empty($cf['head']) || !empty($cf['category']) || !empty($cf['person']) || !empty($cf['without_head']))<span class="wa-cpm-badge is-muted">Drill-down active</span><a class="wap-btn wap-btn-ghost" href="{{ $filterBase.'?'.http_build_query(['view' => 'reports'] + \Illuminate\Support\Arr::only($cf, ['from','to','q'])) }}"><i class="bi bi-arrow-left"></i> Back to analysis</a>@endif
    </div>
    @if($errors->any())<div class="wa-cpm-alert" role="alert">{{ $errors->first() }}</div>@endif
</div>
