{{-- Contextual financial navigation for one authorized project. --}}
@php
    $PCn = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::class;
    $roleN = request()->route('role');
    $act = $navActive ?? 'summary';
    $overviewUrl = $PCn::route('project.summary', ['role' => $roleN, 'department' => $dept->id]);
    $expenseUrl = $PCn::route('project', ['role' => $roleN, 'department' => $dept->id]);
@endphp
@if($PCn::key() === 'construction')
    @include('woodart::construction.header')
    <nav class="wa-cpm wa-cpm-tabs" aria-label="Project workspace">
        @foreach([
            ['summary', $overviewUrl, 'speedometer2', 'Overview'],
            ['information', $expenseUrl.'?view=information', 'info-circle', 'Information'],
            ['team', $expenseUrl.'?view=team', 'people', 'Team & Tasks'],
            ['reports', $expenseUrl.'?view=reports', 'pie-chart', 'Cost Analysis'],
            ['expenses', $expenseUrl, 'receipt', 'Expenses'],
            ['collections', $expenseUrl.'?view=collections', 'person-check', 'Client Collections'],
            ['payables', $expenseUrl.'?view=payables', 'truck', 'Supplier Payables'],
            ['budget', $expenseUrl.'?view=budget', 'calculator', 'Budget & Profitability'],
            ['timeline', $expenseUrl.'?view=timeline', 'signpost-split', 'Timeline'],
            ['materials', $expenseUrl.'?view=materials', 'boxes', 'Materials'],
            ['documents', $expenseUrl.'?view=documents', 'folder2-open', 'Documents'],
            ['activity', $expenseUrl.'?view=activity', 'activity', 'Activity'],
            ['journals', $PCn::route('project.journals', ['department' => $dept->id]), 'journal-text', 'Journals'],
        ] as [$tabKey, $tabUrl, $tabIcon, $tabLabel])
            <a href="{{ $tabUrl }}" @if($act === $tabKey) aria-current="page" @endif><i class="bi bi-{{ $tabIcon }}" aria-hidden="true"></i>{{ $tabLabel }}</a>
        @endforeach
    </nav>
@else
<nav class="wa-project-nav" aria-label="Project financial workspace">
    <a class="{{ $act === 'summary' ? 'active' : '' }}" href="{{ $overviewUrl }}"><i class="bi bi-speedometer2"></i> Overview</a>
    <a class="{{ $act === 'expenses' ? 'active' : '' }}" href="{{ $expenseUrl }}"><i class="bi bi-receipt"></i> Expenses</a>
    <a class="{{ $act === 'collections' ? 'active' : '' }}" href="{{ $expenseUrl }}?view=collections"><i class="bi bi-person-check"></i> Client Collections</a>
    <a class="{{ $act === 'payables' ? 'active' : '' }}" href="{{ $expenseUrl }}?view=payables"><i class="bi bi-truck"></i> Supplier Payables</a>
    <a class="{{ in_array($act, ['reports', 'journals'], true) ? 'active' : '' }}" href="{{ $expenseUrl }}?view=reports"><i class="bi bi-bar-chart"></i> Reports</a>
    @if($act === 'person')<span class="active"><i class="bi bi-person-lines-fill"></i> Site Team Account</span>@endif
</nav>
<style>
.wa-project-nav{display:flex;gap:2px;overflow-x:auto;margin:0 0 14px;padding:4px;background:#fff;border:1px solid #e2e8f0;border-radius:10px}.wa-project-nav a,.wa-project-nav span{display:inline-flex;align-items:center;gap:6px;flex:none;padding:8px 10px;border-radius:7px;color:#475569;font-size:11px;font-weight:700;text-decoration:none}.wa-project-nav a:hover{background:#f0fdfa;color:#0f766e}.wa-project-nav .active{background:#0f766e;color:#fff}.wa-project-nav i{font-size:12px}
</style>
@endif
