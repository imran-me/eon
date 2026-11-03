@extends('woodart::layouts.suite')
@section('wa-view')
@php
    $PC = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::class;
    extract(\Modules\WoodArt\Modules\Accounts\Support\SiteCosts::constructionPresentation($dept->id), EXTR_SKIP);
@endphp
@include('woodart::project-nav', ['navActive' => $workspace])
<div class="wa-cpm">
    @if($workspace === 'information')
    <div class="wa-cpm-info-layout">
        <div class="wa-cpm-info-stack">
            <section class="wa-cpm-panel">
                <div class="wa-cpm-panel-head"><h2>Basic Information</h2>@if($canEdit)<a href="{{ $projectUrl(['view' => 'collections']) }}">Manage client &amp; contract <i class="bi bi-pencil"></i></a>@endif</div>
                <div class="wa-cpm-panel-body wa-cpm-project-card">
                    <dl class="wa-cpm-info">
                        <dt>Internal project ID</dt><dd>#{{ $dept->id }}</dd>
                        <dt>Project name</dt><dd><strong>{{ $dept->name }}</strong></dd>
                        <dt>Client</dt><dd>{{ $statement['client']?->name ?: 'Not assigned' }}</dd>
                        <dt>Project manager</dt><dd>Not recorded</dd>
                        <dt>Status</dt><dd><span class="wa-cpm-badge {{ $dept->status ? '' : 'is-muted' }}">{{ $dept->status ? 'Active' : 'Inactive' }}</span></dd>
                        <dt>Contract value</dt><dd>{{ $terms?->contract_value === null ? 'Not recorded' : $money($terms->contract_value) }}</dd>
                        <dt>Company</dt><dd>{{ $waCompanyName }}</dd>
                        <dt>Description</dt><dd>{{ $dept->description ?: 'Not recorded' }}</dd>
                    </dl>
                    <div class="wa-cpm-project-visual"><div><i class="bi bi-building"></i><span class="wa-cpm-note">Project image not recorded</span></div></div>
                </div>
            </section>
            <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Project Contacts</h2></div><div class="wa-cpm-panel-body"><div class="wa-cpm-contact-grid"><div class="wa-cpm-contact"><strong>Client Contact</strong><span>{{ $statement['client']?->name ?: 'Not assigned' }}</span><span class="wa-cpm-note">Contact details not recorded here</span></div><div class="wa-cpm-contact"><strong>Project Manager</strong><span>Not assigned</span><span class="wa-cpm-note">Project role unavailable</span></div><div class="wa-cpm-contact"><strong>Site Contact</strong><span>Not assigned</span><span class="wa-cpm-note">Project role unavailable</span></div></div></div></section>
        </div>
        <div class="wa-cpm-info-stack">
            <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Important Dates</h2></div><div class="wa-cpm-panel-body"><dl class="wa-cpm-info"><dt>Start date</dt><dd>Not recorded</dd><dt>End date</dt><dd>Not recorded</dd><dt>Contract signing</dt><dd>Not recorded</dd><dt>Expected completion</dt><dd>Not recorded</dd><dt>Warranty end</dt><dd>Not recorded</dd></dl></div></section>
            <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Additional Details</h2></div><div class="wa-cpm-panel-body"><dl class="wa-cpm-info"><dt>Company</dt><dd>{{ $waCompanyName }}</dd><dt>Project type</dt><dd>Not recorded</dd><dt>Billing type</dt><dd>Not recorded</dd><dt>Location</dt><dd>Not recorded</dd><dt>Contract notes</dt><dd>{{ $terms?->note ?: 'Not recorded' }}</dd></dl><div class="wa-cpm-tools" style="margin-top:14px"><a class="wap-btn wap-btn-ghost" href="{{ $PC::route('project.summary', ['department' => $dept->id]) }}">Overview</a><a class="wap-btn wap-btn-ghost" href="{{ $projectUrl(['view' => 'team']) }}">Team Information</a></div></div></section>
        </div>
    </div>
    <p class="wa-cpm-note">Unrecorded dates, roles, location and project type are shown honestly as unavailable; no project facts are inferred from financial activity.</p>
    @elseif($workspace === 'team')
    <div class="wa-cpm-subtabs"><span class="is-active">Project Members</span><span>Project Tasks</span></div>
    <section class="wa-cpm-panel">
        <div class="wa-cpm-panel-head"><h2>Assigned Project Members</h2></div>
        <div class="wa-cpm-empty"><i class="bi bi-people" aria-hidden="true"></i><strong>Project assignments are not recorded yet.</strong><p>Project managers, member roles and assignment access levels are not available for this project.</p></div>
    </section>
    <section class="wa-cpm-panel">
        <div class="wa-cpm-panel-head"><div><h2>Site Financial Participants</h2><span class="wa-cpm-note">People with own-pocket expense claims or recorded site funds. This is separate from assigned project membership.</span></div><a href="{{ $PC::route('project.summary', ['department' => $dept->id, 'mode' => 'posting']) }}">Site team accounts <i class="bi bi-arrow-right"></i></a></div>
        <div class="wa-cpm-table-wrap"><table class="wa-cpm-table"><thead><tr><th>Person</th><th class="wa-cpm-num">Expense entries</th><th class="wa-cpm-num">Approved claims</th><th class="wa-cpm-num">Pending claims</th><th>Last activity</th><th>Actions</th></tr></thead><tbody>
            @forelse($sitePeople as $person)
                <tr><td><span class="wa-cpm-icon" style="display:inline-grid;width:29px;height:29px;margin-right:8px;font-size:11px" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($person->name, 0, 1)) }}</span><strong>{{ $person->name }}</strong></td><td class="wa-cpm-num">{{ $person->n }}</td><td class="wa-cpm-num">{{ $money($person->posted) }}</td><td class="wa-cpm-num">{{ $money($person->pending) }}</td><td>{{ $person->last ? \Carbon\Carbon::parse($person->last)->format('d M Y') : '—' }}</td><td><a href="{{ $PC::route('project.person', ['department' => $dept->id, 'user' => $person->user_id]) }}">Account</a> · <a href="{{ $projectUrl(['person' => $person->user_id]) }}">Expenses</a></td></tr>
            @empty<tr><td colspan="6" class="wa-cpm-empty">No site financial participants recorded for this project.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @elseif($workspace === 'budget')
    @php
        $budgetCostMinor = $minor($operationalSheet['total']);
        $budgetContractMinor = $terms?->contract_value === null ? null : $minor($terms->contract_value);
        $contractLessCost = $budgetContractMinor === null ? null : $budgetContractMinor - $budgetCostMinor;
    @endphp
    <div class="wa-cpm-kpis">
        <div class="wa-cpm-kpi is-blue"><span class="wa-cpm-icon"><i class="bi bi-wallet2"></i></span><span><span class="wa-cpm-label">Project Budget</span><strong class="wa-cpm-value">Not allocated</strong><span class="wa-cpm-note">No project-category budget records</span></span></div>
        <div class="wa-cpm-kpi"><span class="wa-cpm-icon"><i class="bi bi-receipt"></i></span><span><span class="wa-cpm-label">Approved Actual Cost</span><strong class="wa-cpm-value">{{ $moneyMinor($budgetCostMinor) }}</strong><span class="wa-cpm-note">Expense-desk approved costs</span></span></div>
        <div class="wa-cpm-kpi is-violet"><span class="wa-cpm-icon"><i class="bi bi-calculator"></i></span><span><span class="wa-cpm-label">Contract Less Cost</span><strong class="wa-cpm-value">{{ $contractLessCost === null ? 'Not available' : $moneyMinor($contractLessCost) }}</strong><span class="wa-cpm-note">Not a profit forecast</span></span></div>
        <div class="wa-cpm-kpi is-amber"><span class="wa-cpm-icon"><i class="bi bi-percent"></i></span><span><span class="wa-cpm-label">Budget Utilisation</span><strong class="wa-cpm-value">Not available</strong><span class="wa-cpm-note">Budget denominator is missing</span></span></div>
    </div>
    <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><div><h2>Approved Cost by Category</h2><span class="wa-cpm-note">Budget and variance remain unavailable until project budgets are recorded.</span></div><a href="{{ $projectUrl(['view' => 'reports']) }}">Cost Analysis <i class="bi bi-arrow-right"></i></a></div><div class="wa-cpm-table-wrap"><table class="wa-cpm-table"><thead><tr><th>#</th><th>Category</th><th>Budget</th><th class="wa-cpm-num">Actual Cost</th><th>Variance</th><th class="wa-cpm-num">Share of Actual</th><th>Status</th></tr></thead><tbody>@forelse($operationalSheet['byHead'] as $index => $head)<tr><td>{{ $index + 1 }}</td><td><a href="{{ $projectUrl($headFilters($head)) }}">{{ $head['name'] }}</a></td><td>Not allocated</td><td class="wa-cpm-num">{{ $money($head['total']) }}</td><td>—</td><td class="wa-cpm-num">{{ $percent($minor($head['total']), $budgetCostMinor) ?? 0 }}%</td><td><span class="wa-cpm-badge is-muted">Actual only</span></td></tr>@empty<tr><td colspan="7" class="wa-cpm-empty">No approved project costs recorded.</td></tr>@endforelse</tbody></table></div></section>
    <p class="wa-cpm-note">Contract less approved cost is not recognized profit. Supplier obligations, future costs and a project budget are not combined into that figure.</p>
    @elseif($workspace === 'timeline')
    <div class="wa-cpm-subtabs"><span class="is-active">Timeline</span><span>Milestones</span></div>
    <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Project Timeline &amp; Milestones</h2></div><div class="wa-cpm-empty"><i class="bi bi-signpost-split"></i><strong>No project milestones are recorded.</strong><p>Financial transaction dates are not treated as construction milestones or physical progress.</p></div></section>
    @elseif($workspace === 'materials')
    <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><div><h2>Materials (Procurement)</h2><span class="wa-cpm-note">Purchased quantity, unit, rate and supplier</span></div></div><div class="wa-cpm-empty"><i class="bi bi-boxes"></i><strong>Project material quantities are not tracked.</strong><p>Expense descriptions and amounts cannot reliably establish material, quantity, unit, rate, supplier allocation or consumption.</p></div></section>
    @elseif($workspace === 'documents')
    <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><h2>Documents &amp; Files</h2></div><div class="wa-cpm-empty"><i class="bi bi-folder2-open"></i><strong>No project document register is connected.</strong><p>Contracts, BOQs, drawings, site photos and supplier files need a verified project-document relationship before upload can be enabled here.</p></div></section>
    @elseif($workspace === 'activity')
    @php
        $financialActivity = collect();
        foreach ($operationalSheet['entries']->take(30) as $entry) {
            $financialActivity->push((object) ['date' => $entry->expense_date, 'source' => 'Expense', 'description' => $entry->title, 'amount' => $entry->amount, 'status' => $entry->posted ? 'Posted' : 'Pending']);
        }
        $financialActivity = $financialActivity->sortByDesc('date')->take(50)->values();
    @endphp
    <section class="wa-cpm-panel"><div class="wa-cpm-panel-head"><div><h2>Financial Activity Log</h2><span class="wa-cpm-note">Verified project expense activity · collections and supplier events remain in their own registers</span></div></div><div class="wa-cpm-table-wrap"><table class="wa-cpm-table"><thead><tr><th>#</th><th>Date</th><th>Source</th><th>Description</th><th class="wa-cpm-num">Amount</th><th>Status</th></tr></thead><tbody>@forelse($financialActivity as $index => $activity)<tr><td>{{ $index + 1 }}</td><td>{{ $activity->date ? \Carbon\Carbon::parse($activity->date)->format('d M Y') : '—' }}</td><td>{{ $activity->source }}</td><td>{{ $activity->description ?: '—' }}</td><td class="wa-cpm-num">{{ $money($activity->amount) }}</td><td><span class="wa-cpm-badge {{ $activity->status === 'Pending' ? 'is-pending' : '' }}">{{ $activity->status }}</span></td></tr>@empty<tr><td colspan="6" class="wa-cpm-empty">No financial activity is recorded for this project.</td></tr>@endforelse</tbody></table></div></section>
    @endif
</div>
@endsection
