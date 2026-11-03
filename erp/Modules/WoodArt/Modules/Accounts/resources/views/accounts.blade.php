{{-- Production project-accounting workspace. All figures remain sourced from the ERP ledgers. --}}
@extends('woodart::layouts.suite')

@php
    $PC = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::class;
    $role = request()->route('role');
    $mny = fn ($amount) => '৳ ' . number_format((float) $amount, 2);
    $sectionUrl = fn ($name) => $PC::route('', ['role' => $role, 'section' => $name]);
@endphp

@section('wa-head-actions')
    @if(in_array($section, ['overview', 'projects', 'expenses'], true))
        <a class="wap-btn wap-btn-primary" href="{{ $PC::route('bulk', ['role' => $role]) }}"><i class="bi bi-plus-lg"></i> New Expense</a>
    @endif
@endsection

@section('wa-view')
<style>
.wa-fin-kpis{grid-template-columns:repeat(6,minmax(145px,1fr));gap:10px}.wa-fin-kpis .wap-kpi-card{min-height:88px;padding:14px}.wa-fin-kpis .wap-kpi-label{font-size:9px}.wa-fin-kpis .wap-kpi-value{font-size:19px}.wa-fin-kpis .wap-kpi-foot{font-size:10px}.wa-fin-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:14px 0 9px}.wa-fin-toolbar h2{margin:0;color:#0f172a;font-size:16px}.wa-fin-toolbar p{margin:3px 0 0;color:#64748b;font-size:11px}.wa-fin-filter{display:grid;grid-template-columns:minmax(180px,1fr) repeat(4,minmax(120px,180px)) auto;gap:8px;align-items:end;margin-bottom:12px;padding:12px;background:#fff;border:1px solid #e2e8f0;border-radius:11px}.wa-fin-filter label{display:block;margin-bottom:4px;color:#64748b;font-size:9px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.wa-fin-filter .wap-input{width:100%;height:36px;font-size:12px}.wa-fin-filter-actions{display:flex;gap:6px}.wa-fin-table td{vertical-align:middle}.wa-fin-project{display:block;color:#0f172a;font-weight:700;text-decoration:none}.wa-fin-sub{display:block;margin-top:2px;color:#64748b;font-size:10px}.wa-fin-status{display:inline-flex;align-items:center;gap:5px;padding:3px 7px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:10px;font-weight:800}.wa-fin-status.is-pending{background:#fff7ed;color:#b45309}.wa-fin-status.is-due{background:#fff1f2;color:#be123c}.wa-fin-status.is-neutral{background:#f1f5f9;color:#475569}.wa-fin-actions{display:flex;gap:5px;justify-content:flex-end}.wa-fin-actions .wap-btn{padding:6px 8px;font-size:10px}.wa-fin-pagination{margin-top:14px}.wa-fin-metric-bad{color:#be123c!important}.wa-fin-source{white-space:nowrap}.wa-fin-empty{padding:42px 18px}.wa-fin-progress{width:90px;height:6px;margin-top:5px;overflow:hidden;border-radius:999px;background:#e2e8f0}.wa-fin-progress span{display:block;height:100%;border-radius:inherit;background:#0f9488}.wa-fin-context{display:flex;gap:6px;flex-wrap:wrap}.wa-fin-context a{padding:5px 8px;border:1px solid #dbe4ec;border-radius:7px;color:#475569;text-decoration:none;font-size:10px}.wa-fin-context a:hover{border-color:#99f6e4;background:#f0fdfa;color:#0f766e}
@media(max-width:1250px){.wa-fin-kpis{grid-template-columns:repeat(3,1fr)}.wa-fin-filter{grid-template-columns:repeat(3,minmax(0,1fr))}.wa-fin-filter-actions{grid-column:1/-1}}@media(max-width:720px){.wa-fin-kpis{grid-template-columns:repeat(2,1fr)}.wa-fin-filter{grid-template-columns:1fr}.wa-fin-filter-actions{grid-column:auto}.wa-fin-toolbar{align-items:flex-start;flex-direction:column}.wa-fin-table{min-width:920px}}
</style>

@if($section === 'overview')
    @php $totals = $financialTotals ?? []; $rows = $projectRows; @endphp
    <div class="wap-kpi-grid wa-fin-kpis">
        @foreach([
            ['Total Contract Value', $totals['contract'] ?? 0, 'file-earmark-text', 'contracts on file'],
            ['Client Collections', $totals['received'] ?? 0, 'cash-coin', 'recorded receipts'],
            ['Approved Project Costs', $totals['cost'] ?? 0, 'receipt', 'approved ERP expenses'],
            ['Client Receivables', $totals['client_due'] ?? 0, 'person-check', 'contract less collections'],
            ['Supplier Payables', $totals['supplier_due'] ?? 0, 'truck', 'obligations less payments'],
            ['Contract Less Approved Costs', $totals['contract_less_cost'] ?? 0, 'calculator', 'not recognized profit'],
        ] as [$label, $value, $icon, $note])
            <div class="wap-kpi-card"><div class="wap-kpi-top"><span class="wap-kpi-label">{{ $label }}</span><span class="wap-kpi-ico"><i class="bi bi-{{ $icon }}"></i></span></div><div class="wap-kpi-value {{ $value < 0 ? 'wa-fin-metric-bad' : '' }}">{{ $mny($value) }}</div><div class="wap-kpi-foot">{{ $note }}</div></div>
        @endforeach
    </div>
    <div class="wa-fin-toolbar"><div><h2>Active projects</h2><p>Highest-priority financial context without loading every project.</p></div><a class="wap-btn wap-btn-ghost" href="{{ $sectionUrl('projects') }}">View all projects <i class="bi bi-arrow-right"></i></a></div>
    @include('woodart::partials.project-finance-table', ['rows' => $rows, 'mny' => $mny, 'compact' => true])

@elseif($section === 'projects')
    @php $rows = $projectRows; $f = $filters ?? []; @endphp
    <form method="GET" class="wa-fin-filter" action="{{ $sectionUrl('projects') }}">
        <div><label>Project or client</label><input class="wap-input" type="search" name="q" value="{{ $f['q'] ?? '' }}"></div>
        <div><label>Financial status</label><select class="wap-input" name="status"><option value="">All projects</option><option value="active" @selected(($f['status'] ?? '') === 'active')>Active financially</option><option value="settled" @selected(($f['status'] ?? '') === 'settled')>Settled</option><option value="unconfigured" @selected(($f['status'] ?? '') === 'unconfigured')>Contract not set</option></select></div>
        <div><label>Sort by</label><select class="wap-input" name="sort"><option value="name">Project name</option><option value="contract" @selected(($f['sort'] ?? '') === 'contract')>Contract value</option><option value="received" @selected(($f['sort'] ?? '') === 'received')>Amount received</option><option value="cost" @selected(($f['sort'] ?? '') === 'cost')>Approved cost</option><option value="client_due" @selected(($f['sort'] ?? '') === 'client_due')>Client outstanding</option><option value="supplier_due" @selected(($f['sort'] ?? '') === 'supplier_due')>Supplier payable</option></select></div>
        <div><label>Direction</label><select class="wap-input" name="direction"><option value="asc">Ascending</option><option value="desc" @selected(($f['direction'] ?? '') === 'desc')>Descending</option></select></div>
        <div class="wa-fin-filter-actions"><button class="wap-btn wap-btn-primary" type="submit">Apply</button><a class="wap-btn wap-btn-ghost" href="{{ $sectionUrl('projects') }}">Reset</a></div>
    </form>
    @include('woodart::partials.project-finance-table', ['rows' => $rows, 'mny' => $mny])

@elseif($section === 'expenses')
    @php $rows = $expenseRows; $f = $filters ?? []; @endphp
    <form method="GET" class="wa-fin-filter" action="{{ $sectionUrl('expenses') }}">
        <div><label>Search</label><input class="wap-input" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Description, person, project..."></div>
        <div><label>Project</label><select class="wap-input" name="project"><option value="">All projects</option>@foreach($projectOptions as $project)<option value="{{ $project->id }}" @selected(($f['project'] ?? null) == $project->id)>{{ $project->name }}</option>@endforeach</select></div>
        <div><label>Category</label><select class="wap-input" name="category"><option value="">All categories</option>@foreach($categoryOptions as $category)<option value="{{ $category->id }}" @selected(($f['category'] ?? null) == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div><label>Approval status</label><select class="wap-input" name="status"><option value="">All statuses</option><option value="pending" @selected(($f['status'] ?? '') === 'pending')>Pending</option><option value="approved" @selected(($f['status'] ?? '') === 'approved')>Approved</option><option value="rejected" @selected(($f['status'] ?? '') === 'rejected')>Rejected</option></select></div>
        <div><label>From</label><input class="wap-input" type="date" name="from" value="{{ $f['from'] ?? '' }}"></div><div><label>To</label><input class="wap-input" type="date" name="to" value="{{ $f['to'] ?? '' }}"></div>
        <div class="wa-fin-filter-actions"><button class="wap-btn wap-btn-primary" type="submit">Apply</button><a class="wap-btn wap-btn-ghost" href="{{ $sectionUrl('expenses') }}">Reset</a></div>
    </form>
    <div class="wap-card"><div class="wap-card-head"><h3><i class="bi bi-receipt-cutoff"></i> Expense Register</h3><span class="wap-card-sub">Approved and pending are shown separately; approval remains in the Expense desk.</span></div><div class="wap-card-body">
        @if(! $rows || $rows->isEmpty())<div class="wap-empty wa-fin-empty"><i class="bi bi-receipt"></i><div class="wap-empty-title">No expenses match these filters</div><div class="wap-empty-sub">Adjust the filters or create a new project expense.</div></div>@else
        <div class="wap-table-wrap"><table class="wap-table wa-fin-table"><thead><tr><th>Date</th><th>Project</th><th>Expense head</th><th>Description</th><th>Paid by / source</th><th class="wap-t-num">Amount</th><th>Status</th><th></th></tr></thead><tbody>@foreach($rows as $expense)
            @php $source = $expense->reimburse_to_user_id ? 'Own pocket' : ($expense->petty_cash_float_id ? 'Company advance' : ($expense->bank_id ? 'Bank' : ucfirst($expense->payment_mode ?: 'Cash'))); @endphp
            <tr><td>{{ \Carbon\Carbon::parse($expense->expense_date)->format('d M Y') }}</td><td><a class="wa-fin-project" href="{{ $PC::route('project.summary', ['department' => $expense->project_id]) }}">{{ $expense->project ?: '—' }}</a></td><td>{{ $expense->head ?: '—' }}</td><td><strong>{{ \Illuminate\Support\Str::limit($expense->title, 55) }}</strong>@if($expense->description)<span class="wa-fin-sub">{{ \Illuminate\Support\Str::limit($expense->description, 70) }}</span>@endif</td><td class="wa-fin-source">{{ $expense->reference_user_name ?: '—' }}<span class="wa-fin-sub">{{ $source }}</span></td><td class="wap-t-num wap-t-strong">{{ $mny($expense->amount) }}</td><td><span class="wa-fin-status {{ $expense->approval_status === 'approved' ? '' : 'is-pending' }}">{{ ucfirst($expense->approval_status) }}</span></td><td><a class="wap-proj-act" href="{{ $PC::route('project', ['department' => $expense->project_id]) }}?q={{ urlencode($expense->title) }}" aria-label="Open expense in project"><i class="bi bi-arrow-right"></i></a></td></tr>
        @endforeach</tbody></table></div><div class="wa-fin-pagination">{{ $rows->links() }}</div>@endif
    </div></div>

@elseif($section === 'receivables')
    @php $rows = $receivableRows; $f = $filters ?? []; @endphp
    <form method="GET" class="wa-fin-filter" action="{{ $sectionUrl('receivables') }}"><div><label>Project or client</label><input class="wap-input" type="search" name="q" value="{{ $f['q'] ?? '' }}"></div><div><label>Payment status</label><select class="wap-input" name="status"><option value="">All</option><option value="due" @selected(($f['status'] ?? '') === 'due')>Outstanding</option><option value="paid" @selected(($f['status'] ?? '') === 'paid')>Collected</option></select></div><div class="wa-fin-filter-actions"><button class="wap-btn wap-btn-primary">Apply</button><a class="wap-btn wap-btn-ghost" href="{{ $sectionUrl('receivables') }}">Reset</a></div></form>
    <div class="wap-card"><div class="wap-card-head"><h3><i class="bi bi-person-check"></i> Client Receivables</h3><span class="wap-card-sub">Contract schedule and recorded collections; no invoice is created here.</span></div><div class="wap-card-body">
        @if(! $rows || $rows->isEmpty())<div class="wap-empty wa-fin-empty"><i class="bi bi-wallet2"></i><div class="wap-empty-title">No client receivables found</div><div class="wap-empty-sub">Set the client and contract terms inside a project workspace.</div></div>@else<div class="wap-table-wrap"><table class="wap-table wa-fin-table"><thead><tr><th>Project</th><th>Client</th><th class="wap-t-num">Contract value</th><th class="wap-t-num">Billed in books</th><th class="wap-t-num">Received</th><th class="wap-t-num">Outstanding</th><th>Status</th><th></th></tr></thead><tbody>@foreach($rows as $row)<tr><td><a class="wa-fin-project" href="{{ $PC::route('project.summary', ['department' => $row->project_id]) }}">{{ $row->project }}</a></td><td>{{ $row->client ?: 'Not assigned' }}</td><td class="wap-t-num">{{ $mny($row->contract_value) }}</td><td class="wap-t-num">{{ $mny($row->billed) }}</td><td class="wap-t-num">{{ $mny($row->received) }}</td><td class="wap-t-num wap-t-strong {{ $row->outstanding > 0 ? 'wap-t-bad' : '' }}">{{ $mny($row->outstanding) }}</td><td><span class="wa-fin-status {{ $row->outstanding > 0 ? 'is-due' : '' }}">{{ $row->outstanding > 0 ? 'Outstanding' : 'Collected' }}</span></td><td><a class="wap-proj-act" href="{{ $PC::route('project.summary', ['department' => $row->project_id]) }}#wa-client"><i class="bi bi-arrow-right"></i></a></td></tr>@endforeach</tbody></table></div><div class="wa-fin-pagination">{{ $rows->links() }}</div>@endif
    </div></div>

@elseif($section === 'payables')
    @php $rows = $payableRows; $f = $filters ?? []; @endphp
    <form method="GET" class="wa-fin-filter" action="{{ $sectionUrl('payables') }}"><div><label>Project or supplier</label><input class="wap-input" type="search" name="q" value="{{ $f['q'] ?? '' }}"></div><div><label>Project</label><select class="wap-input" name="project"><option value="">All projects</option>@foreach($projectOptions as $project)<option value="{{ $project->id }}" @selected(($f['project'] ?? null) == $project->id)>{{ $project->name }}</option>@endforeach</select></div><div class="wa-fin-filter-actions"><button class="wap-btn wap-btn-primary">Apply</button><a class="wap-btn wap-btn-ghost" href="{{ $sectionUrl('payables') }}">Reset</a></div></form>
    <div class="wap-card"><div class="wap-card-head"><h3><i class="bi bi-truck"></i> Supplier Payables</h3><span class="wap-card-sub">Supplier obligations, payments and outstanding balances from project statements.</span></div><div class="wap-card-body">
        @if(! $rows || $rows->isEmpty())<div class="wap-empty wa-fin-empty"><i class="bi bi-check2-circle"></i><div class="wap-empty-title">No supplier balance is outstanding</div><div class="wap-empty-sub">Posted obligations and partial payments are calculated supplier by supplier.</div></div>@else<div class="wap-table-wrap"><table class="wap-table wa-fin-table"><thead><tr><th>Project</th><th>Supplier / contractor</th><th class="wap-t-num">Obligations</th><th class="wap-t-num">Payments</th><th class="wap-t-num">Outstanding</th><th>Last transaction</th><th></th></tr></thead><tbody>@foreach($rows as $row)<tr><td><a class="wa-fin-project" href="{{ $PC::route('project.summary', ['department' => $row->project_id]) }}">{{ $row->project }}</a></td><td class="wap-t-strong">{{ $row->party ?: '—' }}</td><td class="wap-t-num">{{ $mny($row->obligations) }}</td><td class="wap-t-num">{{ $mny($row->paid) }}</td><td class="wap-t-num wap-t-strong wap-t-bad">{{ $mny($row->outstanding) }}</td><td>{{ $row->last_transaction_date ? \Carbon\Carbon::parse($row->last_transaction_date)->format('d M Y') : '—' }}</td><td><div class="wa-fin-actions">@if($row->party_id)<a class="wap-btn wap-btn-ghost" href="{{ route('role.party-statement.vendors', ['role' => $role, 'company' => $PC::id(), 'project' => $row->project_id, 'party_id' => $row->party_id]) }}">Ledger</a>@endif<a class="wap-proj-act" href="{{ $PC::route('project.summary', ['department' => $row->project_id]) }}#wa-suppliers"><i class="bi bi-arrow-right"></i></a></div></td></tr>@endforeach</tbody></table></div><div class="wa-fin-pagination">{{ $rows->links() }}</div>@endif
    </div></div>
@endif
@endsection
