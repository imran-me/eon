@extends('woodart::layouts.suite')
@php
    $PC = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::class;
    extract(\Modules\WoodArt\Modules\Accounts\Support\SiteCosts::constructionPresentation(0), EXTR_SKIP);
    $sectionUrl = fn ($section) => $PC::route('', ['section' => $section]);
    $collectionPct = $percent($minor($totals['received'] ?? 0), $minor($totals['contract'] ?? 0));
    $costPct = $percent($minor($totals['cost'] ?? 0), $minor($totals['contract'] ?? 0));
    $colors = ['#00a99b','#338fff','#8b7beb','#e6ab4b','#b87581','#64becf','#738ba7'];
    $position = 0;
    $stops = [];
    foreach ($costHeads as $index => $head) {
        $next = $position + ($percent($minor($head->total), $minor($totals['cost'] ?? 0)) ?? 0);
        $stops[] = $colors[$index % count($colors)].' '.$position.'% '.$next.'%';
        $position = $next;
    }
@endphp
@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost" href="{{ $sectionUrl('projects') }}"><i class="bi bi-buildings"></i> All Projects</a>
    @if(auth()->user()?->can('view all expense') && auth()->user()?->can('approve expense'))<a class="wap-btn wap-btn-primary" href="{{ $PC::route('bulk') }}"><i class="bi bi-plus-lg"></i> New Expense</a>@endif
@endsection
@section('wa-view')
@include('woodart::construction.styles')
<div class="wa-cpm">
    <div class="wa-cpm-kpis is-six">
        @foreach([
            ['Contract Value','contract','file-earmark-text','is-blue','projects','Contracts on file'],
            ['Client Received','received','cash-coin','','receivables','Recorded installments'],
            ['Client Outstanding','client_due','file-earmark-minus','is-red','receivables','Contract less recorded collections'],
            ['Approved Expense Cost','cost','receipt','is-violet','expenses','Approved Expense-desk entries'],
            ['Supplier Payables','supplier_due','truck','is-amber','payables','Posted obligations less settlements'],
            ['Contract Less Approved Costs','contract_less_cost','calculator','is-blue','projects','Not recognized profit'],
        ] as [$label,$key,$icon,$tone,$target,$note])
        <a class="wa-cpm-kpi {{ $tone }}" href="{{ $sectionUrl($target) }}"><span class="wa-cpm-icon"><i class="bi bi-{{ $icon }}"></i></span><span><span class="wa-cpm-label">{{ $label }}</span><strong class="wa-cpm-value">{{ $ready ? $money($totals[$key] ?? 0) : 'Not available' }}</strong><span class="wa-cpm-note">{{ $note }}</span></span></a>
        @endforeach
    </div>
    @if(!$ready)<div class="wa-cpm-alert">Project statement tables are not installed on this server. Contract and payable balances are unavailable.</div>@endif
    <div class="wa-cpm-grid">
        <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Portfolio Financial Progress</h2><span class="wa-cpm-note">Lifetime · all Construction projects</span></div><div class="wa-cpm-panel-body">
            <div class="wa-cpm-line"><strong>Client collections / contract value</strong><strong>{{ $collectionPct === null ? '—' : $collectionPct.'%' }}</strong></div><div class="wa-cpm-bar"><span style="width:{{ min(100,max(0,$collectionPct ?? 0)) }}%"></span></div><span class="wa-cpm-note">{{ $money($totals['received'] ?? 0) }} recorded / {{ $money($totals['contract'] ?? 0) }} contracted</span>
            <div class="wa-cpm-line"><strong>Approved expense cost / contract value</strong><strong>{{ $costPct === null ? '—' : $costPct.'%' }}</strong></div><div class="wa-cpm-bar is-blue"><span style="width:{{ min(100,max(0,$costPct ?? 0)) }}%"></span></div><span class="wa-cpm-note">Financial expenditure · physical progress is not tracked here</span>
            <div class="wa-cpm-alert"><div class="wa-cpm-line" style="margin:0"><span><i class="bi bi-hourglass-split"></i> Pending expense approval</span><strong>{{ $money($totals['pending'] ?? 0) }}</strong></div><a href="{{ $sectionUrl('expenses') }}?status=pending">Review pending expenses <i class="bi bi-arrow-right"></i></a></div>
        </div></section>
        <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Approved Cost Breakdown</h2><a href="{{ $sectionUrl('expenses') }}?status=approved">View expenses <i class="bi bi-arrow-right"></i></a></div><div class="wa-cpm-panel-body">
            @if($costHeads->isEmpty())<div class="wa-cpm-empty">No approved Construction expenses yet.</div>@else
            <div class="wa-cpm-donut-wrap"><div class="wa-cpm-donut" role="img" aria-label="Approved Construction costs by expense head" style="background:conic-gradient({{ implode(',',$stops) }})"><div class="wa-cpm-donut-center"><strong>{{ $money($totals['cost'] ?? 0) }}</strong><span class="wa-cpm-note">Approved cost</span></div></div><ul class="wa-cpm-legend">@foreach($costHeads->take(6) as $index => $head)<li><span><span class="wa-cpm-dot" style="background:{{ $colors[$index % count($colors)] }}"></span>{{ $head->name }}</span><strong>{{ $money($head->total) }}</strong><span>{{ $percent($minor($head->total),$minor($totals['cost'] ?? 0)) ?? 0 }}%</span></li>@endforeach</ul></div>
            @if($costHeads->count() > 6)<p class="wa-cpm-note">Chart includes all {{ $costHeads->count() }} expense heads. Largest six listed.</p>@endif
            @endif
        </div></section>
    </div>
    <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><div><h2>Construction Projects</h2><span class="wa-cpm-note">Open a project for its financial overview, analysis and transactions.</span></div><a href="{{ $sectionUrl('projects') }}">View all projects <i class="bi bi-arrow-right"></i></a></div><div class="wa-cpm-table-wrap"><table class="wa-cpm-table"><thead><tr><th>Project / Client</th><th>Status</th><th class="wa-cpm-num">Contract</th><th class="wa-cpm-num">Received</th><th class="wa-cpm-num">Client outstanding</th><th class="wa-cpm-num">Approved cost</th><th class="wa-cpm-num">Supplier payable</th><th></th></tr></thead><tbody>
        @forelse($projects ?? [] as $project)<tr><td><a class="wa-cpm-primary" href="{{ $PC::route('project.summary',['department' => $project->id]) }}">{{ $project->name }}</a><span class="wa-cpm-note">{{ $project->client_name ?: 'Client not assigned' }}</span></td><td><span class="wa-cpm-badge {{ $project->record_status ? '' : 'is-muted' }}">{{ $project->record_status ? 'Active' : 'Inactive' }}</span></td><td class="wa-cpm-num">{{ $project->contract_value === null ? '—' : $money($project->contract_value) }}</td><td class="wa-cpm-num">{{ $money($project->received) }}</td><td class="wa-cpm-num wa-cpm-negative">{{ $project->contract_value === null ? '—' : $money($project->client_due) }}</td><td class="wa-cpm-num"><strong>{{ $money($project->approved_cost) }}</strong></td><td class="wa-cpm-num">{{ $money($project->supplier_due) }}</td><td><a href="{{ $PC::route('project.summary',['department' => $project->id]) }}">Open Overview <i class="bi bi-arrow-right"></i></a></td></tr>@empty<tr><td colspan="8" class="wa-cpm-empty">No project financial data available.</td></tr>@endforelse
    </tbody></table></div></section>
    <div class="wa-cpm-grid">
        <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Recent Construction Expenses</h2><a href="{{ $sectionUrl('expenses') }}">View all</a></div><div class="wa-cpm-table-wrap"><table class="wa-cpm-table"><thead><tr><th>Date / Project</th><th>Expense</th><th class="wa-cpm-num">Amount</th><th>Approval</th></tr></thead><tbody>@forelse($recentExpenses as $expense)<tr><td>{{ \Carbon\Carbon::parse($expense->expense_date)->format('d M Y') }}<span class="wa-cpm-note">{{ $expense->project ?: 'Unassigned' }}</span></td><td>{{ \Illuminate\Support\Str::limit($expense->title,35) }}<span class="wa-cpm-note">{{ $expense->head }}</span></td><td class="wa-cpm-num">{{ $money($expense->amount) }}</td><td><span class="wa-cpm-badge {{ $expense->approval_status === 'approved' ? '' : 'is-pending' }}">{{ ucfirst($expense->approval_status) }}</span></td></tr>@empty<tr><td colspan="4" class="wa-cpm-empty">No expenses recorded.</td></tr>@endforelse</tbody></table></div></section>
        <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Supplier Outstanding</h2><a href="{{ $sectionUrl('payables') }}">View all</a></div><div class="wa-cpm-table-wrap"><table class="wa-cpm-table"><thead><tr><th>Supplier / Project</th><th class="wa-cpm-num">Posted payable</th><th></th></tr></thead><tbody>@forelse($suppliers ?? [] as $supplier)<tr><td>{{ $supplier->party }}<span class="wa-cpm-note">{{ $supplier->project }}</span></td><td class="wa-cpm-num">{{ $money($supplier->outstanding) }}</td><td><a href="{{ $PC::route('project',['department' => $supplier->project_id,'view' => 'payables']) }}">View</a></td></tr>@empty<tr><td colspan="3" class="wa-cpm-empty">No posted supplier balances outstanding.</td></tr>@endforelse</tbody></table></div></section>
    </div>
    <p class="wa-cpm-note">Recorded collections include unposted installments. Approved cost covers Expense-desk entries; separately posted supplier obligations are not added to this measure. Open a project to review its recorded and posted balances.</p>
</div>
@endsection
