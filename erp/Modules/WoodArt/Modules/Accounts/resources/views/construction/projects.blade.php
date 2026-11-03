@extends('woodart::layouts.suite')
@php
    $PC = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::class;
    $listUrl = $PC::route('', ['section' => 'projects']);
    $f = $filters;
    $money = function ($value) {
        $number = (string) round((float) $value);
        $lastThree = substr($number, -3);
        $rest = substr($number, 0, -3);
        if ($rest !== '') {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        }

        return '৳ '.($rest !== '' ? $rest.',' : '').$lastThree;
    };
    $filterUrl = fn ($changes) => $listUrl.'?'.http_build_query(array_merge($f, ['page' => 1], $changes));
@endphp
@section('wa-head-actions')
@can('create expense')<a class="wap-btn wap-btn-primary" href="{{ route('role.construction.accounts.projects.create', ['role' => request()->route('role')]) }}"><i class="bi bi-plus-lg"></i> New Project</a>@endcan
@endsection
@section('wa-view')
@include('woodart::construction.styles')
<div class="wa-cpm wa-cpm-project-list">
    <div class="wa-cpm-kpis wa-cpm-project-counts">
    @foreach([
        ['Total Projects', $counts['all'], 'buildings', '', 'is-blue'],
        ['In Progress', $counts['in_progress'], 'play-circle', 'in_progress', ''],
        ['On Hold', $counts['on_hold'], 'clock', 'on_hold', 'is-amber'],
        ['Completed', $counts['completed'], 'check-circle', 'completed', ''],
        ['Total Contract Value', $totals['contract'] ?? 0, 'file-earmark-check', null, 'is-blue'],
        ['Total Client Received', $totals['received'] ?? 0, 'cash-coin', null, ''],
        ['Total Outstanding', $totals['client_due'] ?? 0, 'file-earmark-minus', null, 'is-red'],
    ] as [$label, $value, $icon, $status, $tone])
        <a class="wa-cpm-kpi {{ $tone }}" href="{{ $status !== null ? $filterUrl(['status' => $status]) : '#' }}"><span class="wa-cpm-icon"><i class="bi bi-{{ $icon }}"></i></span><span><span class="wa-cpm-label">{{ $label }}</span><strong class="wa-cpm-value">{{ $status === null ? $money($value) : number_format((float) $value) }}</strong></span></a>
    @endforeach
    </div>
    <form id="cpm-project-filter" method="GET" action="{{ $listUrl }}" class="wa-cpm-filter wa-cpm-project-filter">
        <div class="wa-cpm-field wa-cpm-search"><label for="cpm-q">Project or client</label><input id="cpm-q" type="search" name="q" maxlength="160" value="{{ $f['q'] ?? '' }}" placeholder="Search by project name, code or client..."></div>
        <div class="wa-cpm-field"><label for="cpm-status">Status</label><select id="cpm-status" name="status"><option value="">All Status</option>@foreach(['in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed', 'unconfigured' => 'Contract not set'] as $key => $label)<option value="{{ $key }}" @selected(($f['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="wa-cpm-field"><label for="cpm-client">Client</label><select id="cpm-client" name="client"><option value="">All Clients</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected((string) ($f['client'] ?? '') === (string) $client->id)>{{ $client->name }}</option>@endforeach</select></div>
        <div class="wa-cpm-field"><label for="cpm-manager">Manager</label><select id="cpm-manager" name="manager"><option value="">All Managers</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected((string) ($f['manager'] ?? '') === (string) $manager->id)>{{ $manager->name }}</option>@endforeach</select></div>
        <div class="wa-cpm-field wa-cpm-date"><label for="cpm-from">Expense period</label><span><input id="cpm-from" name="from" type="date" value="{{ $f['from'] ?? '' }}"><input id="cpm-to" name="to" type="date" value="{{ $f['to'] ?? '' }}"></span></div>
        <input type="hidden" name="sort" value="{{ $f['sort'] ?? 'code' }}"><input type="hidden" name="direction" value="{{ $f['direction'] ?? 'asc' }}">
        <div class="wa-cpm-tools"><button type="submit" class="wap-btn wap-btn-primary"><i class="bi bi-funnel"></i> Apply</button><a class="wap-btn wap-btn-ghost" href="{{ $listUrl }}">Reset</a>@if($ready)<a class="wap-btn wap-btn-ghost" data-no-prefetch href="{{ $filterUrl(['export' => 'csv']) }}"><i class="bi bi-download"></i> Export</a>@endif</div>
    </form>
    @if($errors->any())<div class="wa-cpm-alert" role="alert">{{ $errors->first() }}</div>@endif
    <div class="wa-cpm-panel">
    @if(! $ready)<div class="wa-cpm-empty">Project statements are not installed on this server.</div>
    @elseif($rows->isEmpty())<div class="wa-cpm-empty"><i class="bi bi-buildings"></i>No projects match these filters.</div>
    @else
        <div class="wa-cpm-table-wrap"><table class="wa-cpm-table"><thead><tr><th class="wa-cpm-check"><input type="checkbox" aria-label="Select all projects"></th><th>#</th>
            @foreach(['Project Name', 'Project Code', 'Client', 'Project Manager', 'Start Date', 'End Date', 'Contract Value', 'Client Received', 'Client Outstanding', 'Approved Cost', 'Progress', 'Status', 'Action'] as $heading)<th class="{{ in_array($heading, ['Contract Value', 'Client Received', 'Client Outstanding', 'Approved Cost']) ? 'wa-cpm-num' : '' }}">{{ $heading }}</th>@endforeach
        </tr></thead><tbody>
        @foreach($rows as $project)
            <tr><td class="wa-cpm-check"><input type="checkbox" aria-label="Select {{ $project->name }}"></td><td>{{ $rows->firstItem() + $loop->index }}</td>
                <td><div class="wa-cpm-project-cell"><span class="wa-cpm-project-thumb"><i class="bi bi-buildings"></i></span><span><a class="wa-cpm-primary" href="{{ $PC::route('project.summary', ['department' => $project->id]) }}">{{ $project->name }}</a><span class="wa-cpm-note">{{ $project->project_type ?: 'Project type not set' }}</span></span></div></td>
                <td>{{ $project->project_code ?: '—' }}</td><td>{{ $project->client_name ?: 'Not assigned' }}</td><td><strong>{{ $project->manager_name ?: 'Not assigned' }}</strong><span class="wa-cpm-note">Project Manager</span></td><td>{{ $project->start_date ? \Carbon\Carbon::parse($project->start_date)->format('d M Y') : '—' }}</td><td>{{ $project->end_date ? \Carbon\Carbon::parse($project->end_date)->format('d M Y') : '—' }}</td>
                <td class="wa-cpm-num">{{ $project->contract_value === null ? '—' : $money($project->contract_value) }}</td><td class="wa-cpm-num wa-cpm-positive">{{ $money($project->received) }}</td><td class="wa-cpm-num {{ $project->client_due > 0 ? 'wa-cpm-negative' : '' }}">{{ $project->contract_value === null ? '—' : $money($project->client_due) }}</td><td class="wa-cpm-num"><strong>{{ $money($project->approved_cost) }}</strong></td>
                <td><div class="wa-cpm-progress"><span><i style="width:{{ (int) ($project->progress_percent ?? 0) }}%"></i></span><b>{{ $project->progress_percent === null ? '—' : $project->progress_percent.'%' }}</b></div></td><td><span class="wa-cpm-badge is-{{ $project->construction_status ?: 'unset' }}">{{ match($project->construction_status) {'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed', default => 'Not Set'} }}</span></td><td><a class="wa-cpm-more" href="{{ $PC::route('project.summary', ['department' => $project->id]) }}" aria-label="Open {{ $project->name }}"><i class="bi bi-three-dots"></i></a></td></tr>
        @endforeach
        </tbody></table></div>
        <div class="wa-cpm-footer"><span class="wa-cpm-note">Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }} projects</span><div class="wa-cpm-tools"><label for="cpm-per-page" class="wa-cpm-note">Rows</label><select id="cpm-per-page" name="per_page" form="cpm-project-filter" class="wa-cpm-project-page-size" onchange="this.form.requestSubmit()">@foreach([10, 25, 50, 100] as $size)<option value="{{ $size }}" @selected((int) ($f['per_page'] ?? 25) === $size)>{{ $size }}</option>@endforeach</select>{{ $rows->links() }}</div></div>
    @endif
    </div>
</div>
<style>
.wap:has(.wa-cpm-project-list){font-family:Inter,"Segoe UI",Arial,sans-serif;background:#f7fafc}.wap:has(.wa-cpm-project-list)>.wap-tabs{display:none}.wap:has(.wa-cpm-project-list) .wap-head{background:transparent;color:#102a43;border:0;border-radius:0;padding:8px 4px 12px;margin-bottom:0}.wap:has(.wa-cpm-project-list) .wap-title{color:#102a43;font-size:22px;font-weight:800;letter-spacing:-.025em}.wap:has(.wa-cpm-project-list) .wap-title i{display:none}.wap:has(.wa-cpm-project-list) .wap-sub{color:#6b8298;margin:4px 0 0;font-size:11px}.wap:has(.wa-cpm-project-list) .wap-head .wap-btn-primary{padding:10px 17px;border-color:#07988e;border-radius:6px;background:linear-gradient(135deg,#12a79c,#07877f);color:#fff;font-size:11px;box-shadow:0 4px 10px #07988e24}
.wa-cpm-project-list .wa-cpm-project-counts{grid-template-columns:repeat(7,minmax(0,1fr));gap:7px;margin:0 0 12px}.wa-cpm-project-counts .wa-cpm-kpi{align-items:center;padding:10px 11px;min-height:64px;border-color:#e1eaf0;border-radius:7px;box-shadow:0 2px 7px #18394f0b}.wa-cpm-project-counts .wa-cpm-label{font-size:9px;font-weight:600;white-space:nowrap}.wa-cpm-project-counts .wa-cpm-value{margin:3px 0 0;font-size:13px;font-weight:800;line-height:1.15;white-space:nowrap;overflow:visible;overflow-wrap:normal}.wa-cpm-project-counts .wa-cpm-icon{width:33px;height:36px;border-radius:7px;font-size:15px}
.wa-cpm-project-list .wa-cpm-project-filter{align-items:center;margin:0 0 9px;padding:9px;gap:7px;flex-wrap:nowrap;border-color:#dfe8ee;border-radius:7px;box-shadow:0 2px 7px #18394f08}.wa-cpm-project-filter .wa-cpm-field{min-width:105px;flex:0 1 145px}.wa-cpm-project-filter .wa-cpm-search{flex:1 1 260px}.wa-cpm-project-filter .wa-cpm-date{flex:1 1 260px}.wa-cpm-project-filter .wa-cpm-date span{display:flex;gap:5px}.wa-cpm-project-filter label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}.wa-cpm-project-filter input,.wa-cpm-project-filter select{height:34px;border-color:#d8e4eb;border-radius:5px;font-size:10px}.wa-cpm-project-filter input::placeholder{color:#8da0b1}.wa-cpm-project-filter .wap-btn{height:34px;padding:7px 10px;border-radius:5px;font-size:10px;white-space:nowrap}.wa-cpm-project-filter .wap-btn-primary{background:#0b9185;border-color:#0b9185;color:#fff}
.wa-cpm-project-list .wa-cpm-panel{border-color:#dfe8ee;border-radius:7px;box-shadow:0 3px 12px #17384f0b}.wa-cpm-project-list .wa-cpm-table{font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:10px;color:#25435e}.wa-cpm-project-list .wa-cpm-table th{height:33px;padding:8px 7px;border-right:1px solid #edf2f6;background:#f7fafc;color:#536f88;font-size:9px;font-weight:750;letter-spacing:0}.wa-cpm-project-list .wa-cpm-table td{height:46px;padding:5px 7px;border-right:1px solid #edf2f6}.wa-cpm-project-list .wa-cpm-table tbody tr:nth-child(even){background:#fcfefe}.wa-cpm-project-list .wa-cpm-table tbody tr:hover{background:#f2fbf9}.wa-cpm-project-list .wa-cpm-table .wa-cpm-note{font-size:8px;margin-top:2px;color:#7b90a4}.wa-cpm-project-list .wa-cpm-primary{font-size:10.5px;font-weight:800;color:#123b5b}.wa-cpm-project-list .wa-cpm-footer{padding:10px 13px;background:#fff}.wa-cpm-project-page-size{border:1px solid #dce6ed;border-radius:5px;padding:5px 6px;font-size:10px;background:#fff}.wa-cpm-check{width:22px;text-align:center!important}.wa-cpm-check input{width:13px;height:13px;accent-color:#0aa08e}.wa-cpm-project-cell{display:flex;align-items:center;gap:7px;min-width:165px}.wa-cpm-project-thumb{display:grid;place-items:center;flex:none;width:32px;height:32px;border-radius:6px;background:linear-gradient(145deg,#dff7f2,#e9f5fb);color:#008f84;font-size:15px}.wa-cpm-positive{color:#079c63!important}.wa-cpm-negative{color:#ed3f56!important}.wa-cpm-more{display:grid!important;width:25px;height:25px;place-items:center;border:1px solid #d8e4ea;border-radius:6px;background:#fff;color:#315a75}.wa-cpm-progress{display:flex;align-items:center;gap:6px;min-width:92px}.wa-cpm-progress>span{width:62px;height:7px;overflow:hidden;border-radius:10px;background:#e6eef3}.wa-cpm-progress i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#14a990,#37bea5)}.wa-cpm-progress b{font-size:8.5px}.wa-cpm-badge{padding:5px 8px;border-radius:5px;font-size:8.5px}.wa-cpm-badge.is-on_hold{background:#fff3d8;color:#b67d04}.wa-cpm-badge.is-completed{background:#dcfaf0;color:#058265}.wa-cpm-badge.is-unset{background:#edf2f7;color:#65758a}
@media(max-width:1350px){.wa-cpm-project-list .wa-cpm-project-counts{grid-template-columns:repeat(4,minmax(0,1fr))}}@media(max-width:1150px){.wa-cpm-project-list .wa-cpm-project-filter{flex-wrap:wrap}}@media(max-width:650px){.wa-cpm-project-list .wa-cpm-project-counts{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
@endsection
