@extends('layout.app')
@section('meta-information')
    <title>Payroll Reports</title>
@endsection
@section('main-content')

@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());

    $tabs = [
        'overall'    => ['label' => 'Overall',    'icon' => 'fa-chart-pie',          'hint' => 'Paid / Due per employee'],
        'individual' => ['label' => 'Individual', 'icon' => 'fa-user',               'hint' => 'One employee, month by month'],
        'loan'       => ['label' => 'Loan',       'icon' => 'fa-hand-holding-dollar','hint' => 'Disbursed / recovered'],
        'advance'    => ['label' => 'Advance',    'icon' => 'fa-sack-dollar',        'hint' => 'Advance salary requests'],
        'payslip'    => ['label' => 'Payslip',    'icon' => 'fa-file-lines',         'hint' => 'Issued payslips'],
    ];

    $statusOptions = match($type) {
        'loan'    => ['Running' => 'Running', 'Completed' => 'Completed'],
        'advance' => ['Pending' => 'Pending', 'Approved' => 'Approved', 'Rejected' => 'Rejected'],
        default   => ['Paid' => 'Paid', 'Pending' => 'Pending'],
    };

    $tone = [
        'money' => 'text-gray-900',
        'count' => 'text-gray-900',
        'good'  => 'text-green-600',
        'warn'  => 'text-amber-600',
        'bad'   => 'text-red-600',
    ];

    $monthName = fn ($m) => \Carbon\Carbon::createFromDate(null, max(1, (int) $m), 1)->format('M');
@endphp

<div class="p-4 md:p-6 space-y-5">
        @include('layout.payroll-tabs')


    {{-- ── Header ── --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900 leading-tight">
                <i class="fas fa-file-invoice-dollar text-blue-500 mr-1.5"></i>Payroll Reports
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Salary · Loan · Advance · Payslip
                @if($from || $to)
                    — {{ $from ? \Carbon\Carbon::createFromFormat('Y-m', $from)->format('M Y') : 'beginning' }}
                    to {{ $to ? \Carbon\Carbon::createFromFormat('Y-m', $to)->format('M Y') : 'now' }}
                @else
                    — all time
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- Back to the standing questions — this page narrows a table, the
                 overview says where the payroll stands. --}}
            <a href="{{ route('role.report.payroll.overview', array_filter(['role' => $role, 'company_id' => $companyId])) }}"
               class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                <i class="fas fa-chart-pie"></i> Overview
            </a>
            <a href="{{ route('role.report.payroll.print', array_merge(['role' => $role], request()->query())) }}"
               target="_blank"
               class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                <i class="fas fa-print text-xs"></i> Print / PDF
            </a>
        </div>
    </div>

    {{-- ── Report type tabs ── --}}
    <div class="flex flex-wrap gap-2">
        @foreach($tabs as $key => $tab)
            @php
                $isActive = $type === $key;
                $url = route('role.report.payroll', array_merge(
                    ['role' => $role],
                    array_filter(request()->only(['company_id', 'employee_id', 'from', 'to', 'search'])),
                    ['type' => $key]
                ));
            @endphp
            <a href="{{ $url }}" title="{{ $tab['hint'] }}"
               class="px-4 py-2 rounded-full text-sm font-semibold border transition-colors {{ $isActive ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                <i class="fas {{ $tab['icon'] }} text-xs mr-1"></i>{{ $tab['label'] }}
            </a>
        @endforeach
    </div>


    {{-- ── Who this report is about (the Individual tab has no per-row
         employee column, so the id belongs here rather than repeated
         down every month) ── --}}
    @if($selectedEmployee)
    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm flex flex-wrap items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#dbeafe;color:#2563eb;">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <p class="text-sm font-bold text-gray-900">{{ $selectedEmployee->name }}</p>
            <p class="text-xs text-gray-500 mt-0.5">
                <span class="font-mono font-semibold">{{ $selectedEmployee->employee_id_no ?: 'No employee ID' }}</span>
                @if(optional($selectedEmployee->profile)->designation)
                    · {{ $selectedEmployee->profile->designation->name }}
                @endif
                @if(optional($selectedEmployee->profile)->department)
                    · {{ $selectedEmployee->profile->department->name }}
                @endif
            </p>
        </div>
    </div>
    @endif

    {{-- ── Summary strip ── --}}
    @if(!empty($summary))
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        @foreach($summary as $label => $cell)
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 font-medium">{{ $label }}</p>
            <p class="text-lg font-extrabold mt-0.5 {{ $tone[$cell['type']] ?? 'text-gray-900' }}">
                {{ $cell['type'] === 'count' ? number_format($cell['value']) : '৳ ' . number_format($cell['value'], 0) }}
            </p>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── Table ── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        {{-- Filter bar — the Manage Flights strip, with month ranges in place
             of day presets (this report is read by month). Company comes from
             the payroll toolbar's switcher, so it rides as a hidden field. --}}
        <form method="GET" action="{{ route('role.report.payroll', ['role' => $role]) }}">
            <input type="hidden" name="type" value="{{ $type }}">
            @if(request()->filled('company_id'))
                <input type="hidden" name="company_id" value="{{ request('company_id') }}">
            @endif
            {{-- One row. Individual needs an employee picked, so it gets the
                 Employee select instead of search; own-records users get neither. --}}
            <x-payroll-filter-bar placeholder="Search employee name or ID…"
                :searchable="$type !== 'individual' && !$ownRecordsOnly"
                :segments="[['name' => 'status', 'label' => 'Status', 'options' => $statusOptions]]"
                :reset="route('role.report.payroll', ['role' => $role])" :keep="['company_id', 'type']"
                :total="($type === 'individual' && !empty($needsEmployee)) ? null : $rows->count()">
                @if($type === 'individual' && !$ownRecordsOnly)
                    <div class="pfb-field">
                        <label>Employee *</label>
                        <select name="employee_id">
                            <option value="">— Select an employee —</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}" @selected($employeeId == $emp->id)>{{ $emp->name }}{{ $emp->employee_id_no ? ' — ' . $emp->employee_id_no : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <x-slot:range>
                    <div class="pfb-field pfb-month-field"><label>From month</label><input type="month" name="from" value="{{ $from }}" aria-label="From month"></div>
                    <div class="pfb-field pfb-month-field"><label>To month</label><input type="month" name="to" value="{{ $to }}" aria-label="To month"></div>
                </x-slot:range>
            </x-payroll-filter-bar>
        </form>
        @if($type === 'individual' && !empty($needsEmployee))
            <div class="text-center py-16 text-gray-400">
                <i class="fas fa-user-tag text-3xl mb-3 block opacity-40"></i>
                <p class="text-sm font-medium">Choose an employee above to see their month-by-month salary.</p>
            </div>
        @elseif($rows->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <i class="fas fa-folder-open text-3xl mb-3 block opacity-40"></i>
                <p class="text-sm font-medium">No records found for this filter.</p>
            </div>
        @else
        <div class="overflow-x-auto">
            @include('report.payroll.partials.table-' . $type)
        </div>
        @endif
    </div>

</div>

@endsection
