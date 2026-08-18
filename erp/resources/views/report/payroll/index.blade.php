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

    {{-- ── Filters ── --}}
    <form method="GET" class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="flex flex-wrap items-end gap-3">

            @if($companies->count() > 1)
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Company</label>
                <select name="company_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[150px] focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Companies</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected($companyId == $c->id)>{{ $c->short_name ?? $c->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Employee ID / Name</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="text" name="search" id="payrollSearch" value="{{ $search }}" placeholder="e.g. EG25 109"
                        class="border border-gray-300 rounded-lg pl-8 pr-3 py-2 text-sm min-w-[180px] focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">
                    Employee @if($type === 'individual')<span class="text-red-500">*</span>@endif
                </label>
                <select name="employee_id" id="payrollEmployee" @disabled($ownRecordsOnly)
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[190px] focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                    <option value="" data-emp-id="">{{ $type === 'individual' ? '— Select an employee —' : 'All Employees' }}</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" data-emp-id="{{ $emp->employee_id_no }}" @selected($employeeId == $emp->id)>
                            {{ $emp->name }}{{ $emp->employee_id_no ? ' — ' . $emp->employee_id_no : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">From</label>
                <input type="month" name="from" value="{{ $from }}"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">To</label>
                <input type="month" name="to" value="{{ $to }}"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
                <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[130px] focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All</option>
                    @foreach($statusOptions as $val => $label)
                        <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white px-5 py-2 rounded-lg text-sm font-semibold transition-colors">
                    <i class="fas fa-rotate mr-1 text-xs"></i>Load
                </button>
                <a href="{{ route('role.report.payroll', ['role' => $role, 'type' => $type]) }}"
                   class="text-sm text-gray-500 hover:text-gray-700 underline">Clear</a>
            </div>
        </div>

        {{-- Quick month ranges — the three period modes, without a separate control --}}
        <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Quick range</span>
            @php
                $quick = [
                    'This Month' => [now()->format('Y-m'), now()->format('Y-m')],
                    'Last Month' => [now()->subMonthNoOverflow()->format('Y-m'), now()->subMonthNoOverflow()->format('Y-m')],
                    'This Year'  => [now()->startOfYear()->format('Y-m'), now()->format('Y-m')],
                    'Last Year'  => [now()->subYear()->startOfYear()->format('Y-m'), now()->subYear()->endOfYear()->format('Y-m')],
                ];
            @endphp
            @foreach($quick as $label => [$qf, $qt])
                @php $on = $from === $qf && $to === $qt; @endphp
                <a href="{{ route('role.report.payroll', array_merge(['role' => $role], array_filter(request()->only(['company_id','employee_id','status','type','search'])), ['from' => $qf, 'to' => $qt])) }}"
                   class="px-3 py-1 rounded-full text-xs font-semibold border transition-colors {{ $on ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
            <a href="{{ route('role.report.payroll', array_merge(['role' => $role], array_filter(request()->only(['company_id','employee_id','status','type','search'])))) }}"
               class="px-3 py-1 rounded-full text-xs font-semibold border transition-colors {{ !$from && !$to ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50' }}">
                All Time
            </a>
        </div>
    </form>

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

<script>
    (function () {
        var picker = document.getElementById('payrollEmployee');
        var search = document.getElementById('payrollSearch');
        if (!picker || !search) return;

        // Picking someone from the list fills their id into the search box, so
        // the two controls agree instead of silently narrowing on different
        // people. Clearing the picker clears the box it filled.
        picker.addEventListener('change', function () {
            var empId = this.options[this.selectedIndex].getAttribute('data-emp-id') || '';

            if (this.value && empId) {
                search.value = empId;
                search.dataset.autofilled = '1';
                return;
            }

            // Back to "All Employees" — only wipe the box if we were the ones
            // who filled it. Someone picked with no id on file leaves whatever
            // was typed alone rather than blanking it.
            if (!this.value && search.dataset.autofilled === '1') {
                search.value = '';
                search.dataset.autofilled = '0';
            }
        });

        // Typing by hand means the box is the user's again, not ours to clear.
        search.addEventListener('input', function () { this.dataset.autofilled = '0'; });
    })();
</script>
@endsection
