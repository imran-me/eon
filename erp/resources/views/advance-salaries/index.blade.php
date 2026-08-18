@extends('layout.app')
@section('meta-information')
    <title>Manage Advance Salary</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
    <style>
        .modal-container { max-height: 95vh; display: flex; flex-direction: column; }
        .modal-content { overflow: hidden; display: flex; flex-direction: column; }
        .modal-body { overflow-y: auto; flex-grow: 1; }
    </style>
@endsection
@section('main-content')

@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
    $book = app(\App\Services\AdvanceBookService::class);

    // Whole taka, the same way the loan desk and the salary sheet print them.
    $taka = fn ($amount) => number_format(round((float) $amount));

    // The company column earns its place only when the page is showing more than
    // one — otherwise every row repeats the same chip and costs a column doing it.
    $showCompany = $advances->pluck('user.company_id')->filter()->unique()->count() > 1;

    $canEdit = auth()->user()->can('edit advance salary');
    $canDelete = auth()->user()->can('delete advance salary');
    $canManage = $canEdit || $canDelete || auth()->user()->can('view advance salary');

    // Written as the columns themselves rather than a bare number, because
    // Company and Actions both come and go and a hand-counted total drifts.
    $empCols = count(['#', 'Employee', 'Advances', 'Released', 'Recovered', 'Still Due', 'Awaiting Release'])
        + ($showCompany ? 1 : 0);

    $regCols = count(['#', 'Employee', 'For Month', 'Released On', 'Amount', 'Recovered', 'Still Due', 'Status', 'Actions'])
        + ($showCompany ? 1 : 0);

    $txnCols = count(['#', 'Date', 'Employee', 'Type', 'Note', 'Method', 'The Advance', 'Recovered Till Then', 'Due After', 'Amount'])
        + ($showCompany ? 1 : 0);

    $stateChip = [
        'awaiting'    => ['bg-amber-100 text-amber-700 border-amber-200', 'fa-hourglass-half', 'Awaiting release'],
        'outstanding' => ['bg-red-100 text-red-700 border-red-200', 'fa-arrow-trend-up', 'Outstanding'],
        'recovered'   => ['bg-green-100 text-green-700 border-green-200', 'fa-check-circle', 'Recovered'],
    ];
@endphp

<div class="p-4 md:p-6 space-y-6">
    @include('layout.payroll-tabs')

    {{-- ── Header ── --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900 leading-tight">
                <i class="fas fa-sack-dollar text-blue-500 mr-1.5"></i>Advance Salary
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Every staff advance — what went out, what has come back, and what is still owed.
            </p>
        </div>
        @can('create advance salary')
            <div class="flex flex-wrap items-center gap-2">
                <button class="create-new-btn inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                    <i class="fas fa-plus"></i> New Advance
                </button>
            </div>
        @endcan
    </div>

    {{-- ── Summary Cards ──
         All four read the same scoped book, so "Recovered" is always exactly
         "Total Released" less "Advance Outstanding" — the cards add up.
         Awaiting Release is deliberately its own tile and NOT part of
         Outstanding: until an advance is handed over the money is owed to the
         employee, not by them, and the two would cancel each other out. --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @include('payroll.partials.kpi', [
            'label'     => 'Advance Outstanding',
            'value'     => '৳ ' . $taka($summary['outstanding']),
            'icon'      => 'fa-sack-dollar',
            'iconBg'    => $summary['outstanding'] > 0 ? '#fee2e2' : '#dcfce7',
            'iconText'  => $summary['outstanding'] > 0 ? '#dc2626' : '#16a34a',
            'valueTone' => $summary['outstanding'] > 0 ? 'text-red-600' : 'text-green-600',
            'goodDown'  => true,
            'series'    => $series['outstanding'],
            'foot'      => $summary['holders']
                ? $summary['holders'] . ' ' . Str::plural('person', $summary['holders']) . ' holding an advance'
                : 'nobody is holding an advance',
        ])

        @include('payroll.partials.kpi', [
            'label'    => 'Total Released',
            'value'    => '৳ ' . $taka($summary['released']),
            'icon'     => 'fa-money-bill-transfer',
            'iconBg'   => '#dbeafe',
            'iconText' => '#2563eb',
            'series'   => $series['released'],
            'foot'     => $summary['advance_count'] . ' ' . Str::plural('advance', $summary['advance_count']) . ', all time',
        ])

        @include('payroll.partials.kpi', [
            'label'     => 'Awaiting Release',
            'value'     => '৳ ' . $taka($summary['awaiting']),
            'icon'      => 'fa-hourglass-half',
            'iconBg'    => '#fef3c7',
            'iconText'  => '#b45309',
            'valueTone' => $summary['awaiting'] > 0 ? 'text-amber-600' : 'text-gray-900',
            'foot'      => $summary['awaiting_count']
                ? $summary['awaiting_count'] . ' approved, not yet paid out'
                : 'nothing queued to go out',
        ])

        @include('payroll.partials.kpi', [
            'label'     => 'Recovered',
            'value'     => '৳ ' . $taka($summary['recovered']),
            'icon'      => 'fa-circle-check',
            'iconBg'    => '#dcfce7',
            'iconText'  => '#16a34a',
            'valueTone' => 'text-green-600',
            'series'    => $series['recovered'],
            'foot'      => $summary['released'] > 0
                ? $summary['recovered_pct'] . '% of everything released'
                : 'nothing released yet',
        ])
    </div>

    {{-- ══ EMPLOYEES WITH ADVANCES ══
         The book folded by person. Only those with a live position appear — a
         settled holder belongs in the register below, which keeps everyone. --}}
    <div class="states-table bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="states-table-container">
            <div class="px-6 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-100">
                <div>
                    <h2 class="text-gray-800 text-base font-bold">
                        <i class="fas fa-users mr-2 text-blue-500"></i>Employees with Advances
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        released · recovered · still due{{ $showCompany ? ', across every company' : '' }}
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-xs text-gray-400">{{ $employeeBook->total() }} {{ Str::plural('record', $employeeBook->total()) }}</span>
                    @include('payroll.partials.export-buttons', ['routePrefix' => 'role.advance-salaries.export', 'table' => 'employees', 'count' => $employeeBook->total(), 'exportRole' => $role, 'pageKeys' => ['emp_page', 'reg_page', 'txn_page']])
                </div>
            </div>

            <div class="states-table-content">
                {{-- ── Filters ──
                     One panel for the whole page: all three tables read the same
                     scoped book, so a filter that narrowed only one of them would
                     leave the three disagreeing about what is on the desk. --}}
                @if('employee' != $role)
                    <form action="" method="get">
                        @if(request('company_id'))
                            <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                        @endif
                        <div class="filter-container">
                            <div class="filter-header {{ request()->hasAny(['user_id', 'month', 'state', 'search']) ? 'active' : '' }}">
                                <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </div>
                            <div class="filter-content {{ request()->hasAny(['user_id', 'month', 'state', 'search']) ? 'active' : '' }}">
                                <div class="closest filter-row">
                                    <div class="filter-group">
                                        <label for="search">Employee / Reason</label>
                                        <input type="text" id="search" name="search" class="form-control"
                                               value="{{ request('search') }}" placeholder="e.g. EG25 109" style="width:100%">
                                    </div>
                                    <div class="filter-group">
                                        <label for="user_id">Employee</label>
                                        <select id="user_id" name="user_id" class="form-control select2" style="width: 100%">
                                            <option value="">All</option>
                                            @foreach ($users as $user)
                                                <option value="{{ $user->id }}" {{ $user->id == request('user_id') ? 'selected' : '' }}>{{ $user->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="state">State</label>
                                        <select id="state" name="state" class="form-control select2" style="width: 100%">
                                            <option value="">All</option>
                                            <option value="awaiting" {{ request('state') === 'awaiting' ? 'selected' : '' }}>Awaiting release</option>
                                            <option value="outstanding" {{ request('state') === 'outstanding' ? 'selected' : '' }}>Outstanding</option>
                                            <option value="recovered" {{ request('state') === 'recovered' ? 'selected' : '' }}>Recovered</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="month">Month</label>
                                        <select id="month" name="month" class="form-control select2" style="width: 100%">
                                            <option value="">All</option>
                                            @foreach($periods as $period)
                                                <option value="{{ $period }}" {{ $period === request('month') ? 'selected' : '' }}>
                                                    {{ $book->monthLabel($period) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="filter-actions">
                                    <button type="button" class="reset-btn">Reset</button>
                                    <button type="submit" class="apply-btn">Apply Filters</button>
                                </div>
                            </div>
                        </div>
                    </form>
                @endif

                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:4%">#</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:22%">
                                    <i class="fas fa-user mr-1 text-blue-400"></i>Employee
                                </th>
                                @if($showCompany)
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                        <i class="fas fa-building mr-1 text-cyan-500"></i>Company
                                    </th>
                                @endif
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:9%">
                                    <i class="fas fa-list-ol mr-1 text-gray-400"></i>Advances
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:14%">
                                    <i class="fas fa-money-bill-transfer mr-1 text-blue-400"></i>Released
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:14%">
                                    <i class="fas fa-rotate-left mr-1 text-emerald-400"></i>Recovered
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                    <i class="fas fa-hourglass-half mr-1 text-red-400"></i>Still Due
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:13%">
                                    <i class="fas fa-clock mr-1 text-amber-400"></i>Awaiting Release
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($employeeBook as $key => $row)
                                <tr class="hover:bg-blue-50 transition-colors duration-150">
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs font-bold">
                                            {{ ($employeeBook->currentPage() - 1) * $employeeBook->perPage() + $key + 1 }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold uppercase">
                                                {{ strtoupper(substr($row['user']?->name ?? '?', 0, 2)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <span class="block text-sm font-medium text-gray-800">
                                                    {{ $row['user']?->name ?? 'Employee #' . $row['user_id'] }}
                                                </span>
                                                <span class="block text-[11px] text-gray-400 leading-tight">
                                                    {{ $row['count'] }} {{ Str::plural('advance', $row['count']) }}@if($row['latest']) · latest {{ $book->monthLabel($row['latest']) }}@endif
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    @if($showCompany)
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @include('payroll.partials.company-chip', ['company' => $row['user']?->company])
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-700">{{ number_format($row['count']) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-medium text-gray-700">৳ {{ $taka($row['taken']) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-sm font-semibold text-emerald-600">৳ {{ $taka($row['recovered']) }}</span>
                                        <p class="text-[11px] text-gray-400 leading-tight mt-0.5">
                                            {{ $row['taken'] > 0 ? round($row['recovered'] / $row['taken'] * 100) : 0 }}% of what went out
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        @if($row['due'] > 0)
                                            <span class="text-sm font-bold text-red-600">৳ {{ $taka($row['due']) }}</span>
                                        @else
                                            <span class="text-sm text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        @if($row['awaiting'] > 0)
                                            <span class="text-sm font-semibold text-amber-600">৳ {{ $taka($row['awaiting']) }}</span>
                                        @else
                                            <span class="text-sm text-gray-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $empCols }}" class="text-center py-12">
                                        <div class="flex flex-col items-center gap-2">
                                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i class="fas fa-sack-dollar fa-2x text-gray-300"></i>
                                            </div>
                                            <h4 class="text-gray-500 text-base font-semibold mt-1">No live advances</h4>
                                            <p class="text-gray-400 text-sm">Nobody is holding an advance or waiting on one right now.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        @if($employeeBook->total() > 0)
                            @php $page = $employeeBook->getCollection(); @endphp
                            <tfoot class="border-t-2 border-gray-200">
                                <tr class="bg-gray-100 text-sm border-t border-gray-300">
                                    <td colspan="{{ 2 + ($showCompany ? 1 : 0) }}" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $page->count() }} {{ Str::plural('Person', $page->count()) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">{{ number_format($page->sum('count')) }}</td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">৳ {{ $taka($page->sum('taken')) }}</td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-emerald-700">৳ {{ $taka($page->sum('recovered')) }}</td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-red-700">৳ {{ $taka($page->sum('due')) }}</td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-amber-700">৳ {{ $taka($page->sum('awaiting')) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <div class="p-3 border-t border-gray-200">{{ $employeeBook->links() }}</div>
            </div>
        </div>
    </div>

    {{-- ══ ADVANCE REGISTER ══
         One row per advance, live and settled alike: "how much of the ৳5,000
         taken in February is left" is a question about an ADVANCE, not a person. --}}
    <div class="states-table bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="states-table-container">
            <div class="px-6 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-100">
                <div>
                    <h2 class="text-gray-800 text-base font-bold">
                        <i class="fas fa-book-bookmark mr-2 text-blue-500"></i>Advance Register
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        every advance ever approved{{ $showCompany ? ', in every company' : '' }} — click one for its whole history
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-xs text-gray-400">{{ $register->total() }} {{ Str::plural('record', $register->total()) }}</span>
                    @include('payroll.partials.export-buttons', ['routePrefix' => 'role.advance-salaries.export', 'table' => 'register', 'count' => $register->total(), 'exportRole' => $role, 'pageKeys' => ['emp_page', 'reg_page', 'txn_page']])
                </div>
            </div>

            <div class="states-table-content">
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:4%">#</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:17%">
                                    <i class="fas fa-user mr-1 text-blue-400"></i>Employee
                                </th>
                                @if($showCompany)
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                        <i class="fas fa-building mr-1 text-cyan-500"></i>Company
                                    </th>
                                @endif
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-calendar-day mr-1 text-cyan-400"></i>For Month
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-money-bill-transfer mr-1 text-indigo-400"></i>Released On
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Amount
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                    <i class="fas fa-rotate-left mr-1 text-emerald-400"></i>Recovered
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-hourglass-half mr-1 text-red-400"></i>Still Due
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-tag mr-1 text-yellow-400"></i>Status
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                    <i class="fas fa-cogs mr-1 text-gray-400"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($register as $key => $value)
                                @php [$chipClass, $chipIcon, $chipLabel] = $stateChip[$value->state]; @endphp
                                <tr class="hover:bg-blue-50 transition-colors duration-150 cursor-pointer" data-advance-id="{{ $value->id }}">
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs font-bold">
                                            {{ ($register->currentPage() - 1) * $register->perPage() + $key + 1 }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold uppercase">
                                                {{ strtoupper(substr($value->user?->name ?? '?', 0, 2)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <span class="block text-sm font-medium text-gray-800">
                                                    {{ $value->user?->name ?? 'Employee #' . $value->user_id }}
                                                </span>
                                                <span class="block text-xs font-mono {{ $value->user?->employee_id_no ? 'text-gray-500' : 'text-gray-300' }}">
                                                    {{ $value->user?->employee_id_no ?: '—' }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    @if($showCompany)
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @include('payroll.partials.company-chip', ['company' => $value->user?->company])
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $book->monthLabel($value->month) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($value->is_released && $value->paid_at)
                                            <span class="text-sm text-gray-700">{{ \Carbon\Carbon::parse($value->paid_at)->format('d M Y') }}</span>
                                        @else
                                            <span class="text-sm text-gray-300">not released</span>
                                        @endif
                                        @if($value->reason)
                                            <p class="text-[11px] text-gray-400 leading-tight mt-0.5">{{ Str::limit($value->reason, 28) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-medium text-gray-700">৳ {{ $taka($value->amount) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-sm font-semibold text-emerald-600">৳ {{ $taka($value->recovered_amount) }}</span>
                                        <p class="text-[11px] text-gray-400 leading-tight mt-0.5">
                                            {{ $value->progress_pct }}%@if($value->last_recovered_on) · last {{ \Carbon\Carbon::parse($value->last_recovered_on)->format('d M Y') }}@endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-bold {{ $value->outstanding > 0 ? 'text-red-600' : 'text-green-600' }}">
                                            ৳ {{ $taka($value->outstanding) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold border {{ $chipClass }}">
                                            <i class="fas {{ $chipIcon }} text-xs"></i> {{ $chipLabel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-green-50 text-green-700 border border-green-200 hover:bg-green-600 hover:text-white transition-colors duration-150 advance-view-btn"
                                                data-advance-id="{{ $value->id }}" title="Open this advance">
                                                <i class="fas fa-eye"></i><span>View</span>
                                            </button>
                                            @can('view advance salary')
                                                <a href="{{ route('role.advance-salaries.payment-slip', ['role' => $role, 'id' => $value->id]) }}"
                                                   onclick="event.stopPropagation()" target="_blank" rel="noopener"
                                                   class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-gray-50 text-gray-600 border border-gray-200 hover:bg-gray-600 hover:text-white transition-colors duration-150"
                                                   title="Payment slip">
                                                    <i class="fas fa-receipt text-xs"></i>
                                                </a>
                                            @endcan
                                            @can('edit advance salary')
                                                @if($value->status == 'Pending' && !$value->is_released)
                                                    <button class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-yellow-50 text-yellow-600 border border-yellow-200 hover:bg-yellow-500 hover:text-white transition-colors duration-150 edit-item-btn"
                                                        onclick="event.stopPropagation()"
                                                        data-item_id="{{ $value->id }}"
                                                        data-user_id="{{ $value->user_id }}"
                                                        data-amount="{{ $value->amount }}"
                                                        data-month="{{ $value->month }}"
                                                        data-reason="{{ $value->reason }}"
                                                        data-status="{{ $value->status }}"
                                                        title="Edit this advance">
                                                        <i class="fas fa-edit text-xs"></i>
                                                    </button>
                                                @endif
                                            @endcan
                                            @can('delete advance salary')
                                                @if(!$value->is_released)
                                                    <button type="button"
                                                        class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-50 text-red-600 border border-red-200 hover:bg-red-600 hover:text-white transition-colors duration-150"
                                                        onclick="event.stopPropagation(); confirmDelete('{{ $value->id }}', 'this advance')"
                                                        title="Delete this advance">
                                                        <i class="fas fa-trash text-xs"></i>
                                                    </button>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $regCols }}" class="text-center py-12">
                                        <div class="flex flex-col items-center gap-2">
                                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i class="fas fa-book-bookmark fa-2x text-gray-300"></i>
                                            </div>
                                            <h4 class="text-gray-500 text-base font-semibold mt-1">No advance has been approved yet</h4>
                                            <p class="text-gray-400 text-sm">Approve one and it will appear here with its whole history.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        {{-- THE FOOT: the three money columns sum; STATUS counts
                             instead, because "awaiting or recovered" has no total —
                             and a book whose foot says how many are still live
                             answers the question the total would raise. --}}
                        @if($register->total() > 0)
                            @php
                                $page = $register->getCollection();
                                $byState = $page->countBy(fn ($a) => $a->state);
                            @endphp
                            <tfoot class="border-t-2 border-gray-200">
                                <tr class="bg-gray-100 text-sm border-t border-gray-300">
                                    <td colspan="{{ 4 + ($showCompany ? 1 : 0) }}" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $page->count() }} {{ Str::plural('Advance', $page->count()) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">৳ {{ $taka($page->sum(fn ($a) => (float) $a->amount)) }}</td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-emerald-700">৳ {{ $taka($page->sum(fn ($a) => $a->recovered_amount)) }}</td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-red-700">৳ {{ $taka($page->sum(fn ($a) => $a->outstanding)) }}</td>
                                    <td colspan="2" class="px-4 py-3.5 text-center text-gray-500 text-xs whitespace-nowrap">
                                        {{ collect($byState)->map(fn ($n, $k) => $n . ' ' . $k)->implode(' · ') }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <div class="p-3 border-t border-gray-200">{{ $register->links() }}</div>
            </div>
        </div>
    </div>

    {{-- ══ ADVANCE TRANSACTIONS ══
         The trail told as advances rather than as movements: every row names its
         advance and carries where that advance stood at that moment. --}}
    <div class="states-table bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="states-table-container">
            <div class="px-6 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-100">
                <div>
                    <h2 class="text-gray-800 text-base font-bold">
                        <i class="fas fa-receipt mr-2 text-blue-500"></i>Advance Transactions{{ $showCompany ? ' — Every Company' : '' }}
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        every movement on the book — money released and money recovered
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-xs text-gray-400">{{ $transactions->total() }} {{ Str::plural('record', $transactions->total()) }}</span>
                    @include('payroll.partials.export-buttons', ['routePrefix' => 'role.advance-salaries.export', 'table' => 'transactions', 'count' => $transactions->total(), 'exportRole' => $role, 'pageKeys' => ['emp_page', 'reg_page', 'txn_page']])
                </div>
            </div>

            <div class="states-table-content">
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:4%">#</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:9%">
                                    <i class="fas fa-calendar-day mr-1 text-cyan-400"></i>Date
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:14%">
                                    <i class="fas fa-user mr-1 text-blue-400"></i>Employee
                                </th>
                                @if($showCompany)
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:9%">
                                        <i class="fas fa-building mr-1 text-cyan-500"></i>Company
                                    </th>
                                @endif
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:9%">
                                    <i class="fas fa-tag mr-1 text-yellow-400"></i>Type
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:15%">
                                    <i class="fas fa-note-sticky mr-1 text-gray-400"></i>Note
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-arrow-right-arrow-left mr-1 text-indigo-400"></i>Method
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-sack-dollar mr-1 text-purple-400"></i>The Advance
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-rotate-left mr-1 text-emerald-400"></i>Recovered Till Then
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:9%">
                                    <i class="fas fa-hourglass-half mr-1 text-red-400"></i>Due After
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($transactions as $key => $txn)
                                <tr class="hover:bg-blue-50 transition-colors duration-150 cursor-pointer" data-advance-id="{{ $txn['advance']->id }}">
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs font-bold">
                                            {{ ($transactions->currentPage() - 1) * $transactions->perPage() + $key + 1 }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                        {{ \Carbon\Carbon::parse($txn['date'])->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800">
                                        {{ $txn['user']?->name ?? 'Employee' }}
                                    </td>
                                    @if($showCompany)
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @include('payroll.partials.company-chip', ['company' => $txn['user']?->company])
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        @if($txn['type'] === 'recovery')
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                                                <i class="fas fa-arrow-down text-xs"></i> Recovery
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">
                                                <i class="fas fa-arrow-up text-xs"></i> Release
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $txn['note'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $txn['method'] === 'Salary deduction' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-gray-100 text-gray-600 border border-gray-200' }}">
                                            {{ $txn['method'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-sm font-medium text-gray-700">৳ {{ $taka($txn['advance']->amount) }}</span>
                                        <p class="text-[11px] text-gray-400 leading-tight mt-0.5">for {{ $book->monthLabel($txn['advance']->month) }}</p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-semibold text-emerald-600">৳ {{ $taka($txn['recovered']) }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-semibold {{ $txn['due'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                            ৳ {{ $taka($txn['due']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-bold {{ $txn['type'] === 'recovery' ? 'text-emerald-700' : 'text-amber-700' }}">
                                            ৳ {{ $taka($txn['amount']) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $txnCols }}" class="text-center py-12">
                                        <div class="flex flex-col items-center gap-2">
                                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i class="fas fa-receipt fa-2x text-gray-300"></i>
                                            </div>
                                            <h4 class="text-gray-500 text-base font-semibold mt-1">No movements yet</h4>
                                            <p class="text-gray-400 text-sm">Releases and salary recoveries both land here as they happen.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        {{-- THE FOOT, and the one table here where a single Amount
                             total would be a lie: these rows run in both directions,
                             so summing the column nets a release against a recovery
                             and calls the result "amount". It foots as both
                             directions plus the net. "Due after" is a balance at a
                             moment in time — adding them would produce a figure
                             that never existed. --}}
                        @if($transactions->total() > 0)
                            @php
                                $page = $transactions->getCollection();
                                $out = $page->where('type', 'release')->sum('amount');
                                $back = $page->where('type', 'recovery')->sum('amount');
                            @endphp
                            <tfoot class="border-t-2 border-gray-200">
                                <tr class="bg-gray-100 text-sm border-t border-gray-300">
                                    <td colspan="{{ 7 + ($showCompany ? 1 : 0) }}" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $page->count() }} {{ Str::plural('Movement', $page->count()) }}
                                    </td>
                                    <td colspan="2" class="px-4 py-3.5 text-right text-gray-400 text-xs italic whitespace-nowrap">
                                        balances, not sums
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        <span class="font-extrabold text-gray-800">৳ {{ $taka($out - $back) }}</span>
                                        <span class="text-[11px] text-gray-400 font-normal">net</span>
                                        <p class="text-[11px] text-gray-400 font-normal leading-tight mt-0.5">
                                            ৳ {{ $taka($out) }} out · ৳ {{ $taka($back) }} back
                                        </p>
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <div class="p-3 border-t border-gray-200">{{ $transactions->links() }}</div>
            </div>
        </div>
    </div>
</div>

{{-- ── Modals ── --}}
@include('advance-salaries.create-modal')
@include('advance-salaries.edit-modal')
@include('advance-salaries.delete-modal')

<div id="advanceDetailModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="bg-white w-11/12 md:max-w-4xl mx-auto rounded-2xl shadow-lg z-50 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100 sticky top-0 bg-white z-10">
            <h3 class="text-base font-bold text-gray-800">
                <i class="fas fa-sack-dollar mr-2 text-blue-500"></i>Advance Salary
            </h3>
            <button class="modal-close-detail text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <div id="advanceDetailBody" class="p-6">
            <div class="text-center text-gray-400 py-10"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
        </div>
    </div>
</div>

@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {

            // initialized select2
            $('.select2').select2();

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Show create modal
            $('.create-new-btn').click(function() {
                $('#createModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-item-btn').click(function() {
                const item_id = $(this).data('item_id');
                const user_id = $(this).data('user_id');                
                const amount = $(this).data('amount');
                const reason = $(this).data('reason');
                const month = $(this).data('month');
                const status = $(this).data('status');
                
                // Set values in the edit form
                $('#editItemId').val(item_id);
                $('#edit_user_id').val(user_id).trigger('change');
                $('#edit_amount').val(amount);
                $('#edit_month').val(`${month}`);
                $('#edit_reason').val(reason);
                $('#edit_status').val(status).trigger('change'); 
                $('#editModal').removeClass('hidden'); 
            });

            // Close modals
            $('.modal-close-create, .modal-backdrop').click(function(e) {
                if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) {
                    $('#createModal').addClass('hidden');
                }
            });

            $('.modal-close-edit, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) {
                    $('#editModal').addClass('hidden');
                }
            });

            $('.modal-close-delete, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) {
                    $('#deleteModal').addClass('hidden');
                }
            });

            // Close success alert
            $('.close-btn').click(function() {
                $(this).closest('.alert').addClass('hidden');
            });

            // Create state form submission
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                console.log(validateCreateForm());                
                if (validateCreateForm()) {
                    let formData = new FormData($('#createForm')[0]);
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Done',
                                    text: 'Data created successfully!'
                                });
                                $('#createModal').addClass('hidden');
                                $('#createForm')[0].reset();
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: response.message || 'Something went wrong.'
                                });
                            }
                        },
                        error: function (xhr) {
                            console.error(xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Failed to create data.'
                            });
                        }
                    });                                     
                }
            });

            // Edit state form submission
            $('#editSubmit').click(function() {
                if (validateEditForm()) {
                    let formData = new FormData($('#editForm')[0]);
                    $.ajax({
                        url: $(this).data('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: "success",
                                    title: "Done",
                                    text: "Data updated successfully!",
                                });
                                $('#editModal').addClass('hidden');
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({
                                    icon: "error",
                                    title: "Oops...",
                                    text: response.message || "Update failed.",
                                });
                            }
                        },
                        error: function (xhr) {
                            console.error('❌ Error:', xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Something went wrong!'
                            });
                        }
                    }); 
                }
            });

            // Delete confirmation
            $('#confirmDeleteBtn').click(function() {
                const dataId = $(this).data('item-id');
                const deleteUrl = $(this).data('action');
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: {
                        item_id: dataId,
                    },
                    success: function (response) {
                        console.log(response);
                        if (response.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Done",
                                text: "Data deleted successfully!",
                            });
                            $('#deleteModal').addClass('hidden');
                            console.log('trigger reload');                                
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Opps...",
                                text: response.message,
                            });
                        }
                    },
                    error: function (xhr) {
                        console.error('❌ Error:', xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Something went wrong!'
                        });
                    }
                });  
            });
        });

        function select2SetValueNoEvent(selectId, value) {
            var $select = $(selectId);

            // Set the underlying value
            $select.val(value);

            // Find the selected option text
            var text = $select.find('option:selected').text() || '';

            // Update the visible Select2 box manually
            $select.data('select2').$container.find('.select2-selection__rendered').text(text);
        }
        
        // Form validation functions
        function validateCreateForm() {
            let isValid = true;
            
            // Reset error messages
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');                        
            
            if (!$('#create_user_id').val() || ($('#create_user_id').val() == '') || ($('#create_user_id').val() == null)) {
                $('#create_user_msg').removeClass('hidden');                
                isValid = false;
            }        
            if (!$('#create_amount').val().trim()) {
                $('#create_amount').next('.error-message').removeClass('hidden');
                $('#create_amount').addClass('border-red-500');
                isValid = false;
            }                  
            if (!$('#create_month').val().trim()) {
                $('#create_month').next('.error-message').removeClass('hidden');
                $('#create_month').addClass('border-red-500');
                isValid = false;
            }                  
                                                                                              
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            
            if (!$('#edit_user_id').val() || ($('#edit_user_id').val() == '') || ($('#edit_user_id').val() == null)) {
                $('#edit_user_msg').removeClass('hidden');                
                isValid = false;
            }            
            if (!$('#edit_amount').val().trim()) {
                $('#edit_amount').next('.error-message').removeClass('hidden');
                $('#edit_amount').addClass('border-red-500');
                isValid = false;
            }                  
            if (!$('#edit_month').val().trim()) {
                $('#edit_month').next('.error-message').removeClass('hidden');
                $('#edit_month').addClass('border-red-500');
                isValid = false;
            }  
            
            return isValid;
        }

        // Reset create form
        function resetCreateForm() {
            $('#createForm')[0].reset();
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');
        }

        // Delete confirmation
        function confirmDelete(id, name=null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterHeader = document.querySelector('.filter-container .filter-header');
            const filterContent = document.querySelector('.filter-container .filter-content');
            
            filterHeader.addEventListener('click', function() {
                this.classList.toggle('active');
                filterContent.classList.toggle('active');
            });
            
            // Reset button functionality
            document.querySelector('.filter-container .reset-btn').addEventListener('click', function() {
                const inputs = document.querySelectorAll('.filter-container select, .filter-container input');
                inputs.forEach(input => {
                    if (input.type === 'date') {
                        input.value = '';
                    } else {
                        input.selectedIndex = 0;
                    }
                });
            });
            document.querySelector('.reset-btn').addEventListener('click', function (e) {
                e.preventDefault();
                // Company stays: it is a payroll-wide context set by the toolbar,
                // not one of this page's own filters.
                window.location = '{{ route('role.advance-salaries.index', array_filter(['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'company_id' => request('company_id')])) }}';
            });
        });
    </script>
    <script>
        /* ── One advance's whole history ──
           Fetched rather than rendered inline: the register can run long, and
           shipping every advance's recovery table with the page would cost far
           more than the one the user actually opens. */
        $(document).ready(function () {
            function openAdvance(id) {
                $('#advanceDetailBody').html('<div class="text-center text-gray-400 py-10"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
                $('#advanceDetailModal').removeClass('hidden');

                $.get('{{ route('role.advance-salaries.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}/' + id)
                    .done(function (html) { $('#advanceDetailBody').html(html); })
                    .fail(function () {
                        $('#advanceDetailBody').html('<p class="text-center text-red-500 py-10">This advance could not be opened.</p>');
                    });
            }

            $(document).on('click', '.advance-view-btn', function (e) {
                e.stopPropagation();
                openAdvance($(this).data('advance-id'));
            });

            $(document).on('click', 'tr[data-advance-id]', function () {
                openAdvance($(this).data('advance-id'));
            });

            $('.modal-close-detail, .modal-backdrop').click(function (e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-detail').length) {
                    $('#advanceDetailModal').addClass('hidden');
                }
            });
        });
    </script>
@endsection