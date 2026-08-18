@extends('layout.app')
@section('meta-information')
    <title>Manage Loan</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
    <style>
        .modal-container {
            max-height: 95vh;
            display: flex;
            flex-direction: column;
        }

        .modal-content {
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-body {
            overflow-y: auto;
            flex-grow: 1;
        }
    </style>
@endsection
@section('main-content')

@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());

    // Loan figures show as whole taka, the same way the salary sheet next door
    // prints them. Display only — the stored amounts keep their paise.
    $taka = fn ($amount) => number_format(round((float) $amount));

    // The company column earns its place only when the page is actually showing
    // more than one — on a single-company view every row would repeat the same
    // chip and cost a column doing it.
    $showCompany = $loans->pluck('user.company_id')->filter()->unique()->count() > 1;

    $canCollect = auth()->user()->can('create loan');

    // Column counts for the empty states. Written as the columns themselves
    // rather than as a bare number, because Company and Actions both come and go
    // and a hand-counted total is what drifts out of step with the header.
    $empCols = count(['#', 'Employee', 'Loan taken', 'Paid so far', 'Still due', 'Repaid via', 'Monthly EMI'])
        + ($showCompany ? 1 : 0) + ($canCollect ? 1 : 0);

    $regCols = count(['#', 'Employee', 'Taken on', 'Loan taken', 'Paid till now', 'Still due', 'Repaid via', 'Status', 'Actions'])
        + ($showCompany ? 1 : 0);

    $txnCols = count(['#', 'Date', 'Employee', 'Type', 'Note', 'Method', 'The loan', 'Paid till then', 'Due after', 'Amount'])
        + ($showCompany ? 1 : 0);
@endphp

<div class="p-4 md:p-6 space-y-6">
    @include('layout.payroll-tabs')

    {{-- ── Header ── --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900 leading-tight">
                <i class="fas fa-hand-holding-dollar text-blue-500 mr-1.5"></i>Loan Management
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Every staff loan — what was lent, what has come back, and what is still owed.
            </p>
        </div>
        @can('create loan')
            <div class="flex flex-wrap items-center gap-2">
                <button class="create-new-btn inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                    <i class="fas fa-plus"></i> Disburse Loan
                </button>
            </div>
        @endcan
    </div>

    {{-- ── Summary Cards ──
         All four read off the same scoped book, so "Repaid" is always exactly
         "Total Disbursed" less "Loan Outstanding" — the cards add up. --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @include('payroll.partials.kpi', [
            'label'     => 'Loan Outstanding',
            'value'     => '৳ ' . $taka($summary['outstanding']),
            'icon'      => 'fa-building-columns',
            'iconBg'    => '#fee2e2',
            'iconText'  => '#dc2626',
            'valueTone' => $summary['outstanding'] > 0 ? 'text-red-600' : 'text-green-600',
            'goodDown'  => true,
            'series'    => $series['outstanding'],
            'foot'      => $summary['borrowers']
                ? $summary['borrowers'] . ' ' . Str::plural('person', $summary['borrowers']) . ' carrying a loan'
                : 'nobody is carrying a loan',
        ])

        @include('payroll.partials.kpi', [
            'label'    => 'Total Disbursed',
            'value'    => '৳ ' . $taka($summary['disbursed']),
            'icon'     => 'fa-money-bill-transfer',
            'iconBg'   => '#dbeafe',
            'iconText' => '#2563eb',
            'series'   => $series['disbursed'],
            'foot'     => $summary['loan_count'] . ' ' . Str::plural('loan', $summary['loan_count']) . ', all time',
        ])

        @include('payroll.partials.kpi', [
            'label'    => 'Active Loans',
            'value'    => number_format($summary['active_loans']),
            'icon'     => 'fa-users',
            'iconBg'   => '#ede9fe',
            'iconText' => '#7c3aed',
            'goodDown' => true,
            'series'   => $series['active'],
            'foot'     => $summary['emi_total']
                ? '৳ ' . $taka($summary['emi_total']) . '/mo scheduled EMI'
                : 'no repayment schedule set',
        ])

        @include('payroll.partials.kpi', [
            'label'     => 'Repaid',
            'value'     => '৳ ' . $taka($summary['repaid']),
            'icon'      => 'fa-circle-check',
            'iconBg'    => '#dcfce7',
            'iconText'  => '#16a34a',
            'valueTone' => 'text-green-600',
            'series'    => $series['repaid'],
            'foot'      => $summary['disbursed'] > 0
                ? $summary['repaid_pct'] . '% of everything lent'
                : 'nothing lent yet',
        ])
    </div>

    {{-- ══ EMPLOYEES WITH LOANS ══
         The book folded by person. Only those still carrying a balance appear —
         a settled borrower belongs in the register below, which keeps everyone. --}}
    <div class="states-table bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="states-table-container">
            <div class="px-6 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-100">
                <div>
                    <h2 class="text-gray-800 text-base font-bold">
                        <i class="fas fa-users mr-2 text-blue-500"></i>Employees with Loans
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        taken · paid · still due, per person{{ $showCompany ? ', across every company' : '' }}{{ $canCollect ? ' · click a row to record a repayment' : '' }}
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-xs text-gray-400">{{ $employeeBook->total() }} {{ Str::plural('record', $employeeBook->total()) }}</span>
                    @include('payroll.partials.export-buttons', ['routePrefix' => 'role.loans.export', 'table' => 'employees', 'count' => $employeeBook->total(), 'exportRole' => $role])
                </div>
            </div>

            <div class="states-table-content">
                {{-- ── Filters ──
                     One panel for the whole page: all three tables read the same
                     scoped book, so a filter that only narrowed one of them would
                     leave the three disagreeing about what is on the desk. --}}
                @if('employee' != Str::slug(Auth::user()->getRoleNames()->first()))
                    <form action="" method="get">
                        @if(request('company_id'))
                            <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                        @endif
                        <div class="filter-container">
                            <div class="filter-header {{ request()->hasAny(['user_id', 'status', 'search', 'start_date', 'end_date']) ? 'active' : '' }}">
                                <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </div>
                            <div class="filter-content {{ request()->hasAny(['user_id', 'status', 'search', 'start_date', 'end_date']) ? 'active' : '' }}">
                                <div class="closest filter-row">
                                    <div class="filter-group">
                                        <label for="search">Employee ID / Name</label>
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
                                        <label for="status">Status</label>
                                        <select id="status" name="status" class="form-control select2" style="width: 100%">
                                            <option value="">All</option>
                                            <option value="running" {{ request('status') === 'running' ? 'selected' : '' }}>Running</option>
                                            <option value="cleared" {{ request('status') === 'cleared' ? 'selected' : '' }}>Cleared</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="start_date">Taken from</label>
                                        <input type="month" name="start_date" value="{{ request('start_date') }}" id="start_date" class="form-control">
                                    </div>
                                    <div class="filter-group">
                                        <label for="end_date">Taken up to</label>
                                        <input type="month" name="end_date" value="{{ request('end_date') }}" id="end_date" class="form-control">
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
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:20%">
                                    <i class="fas fa-user mr-1 text-blue-400"></i>Employee
                                </th>
                                @if($showCompany)
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                        <i class="fas fa-building mr-1 text-cyan-500"></i>Company
                                    </th>
                                @endif
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:13%">
                                    <i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Loan Taken
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:14%">
                                    <i class="fas fa-rotate-left mr-1 text-emerald-400"></i>Paid So Far
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                    <i class="fas fa-hourglass-half mr-1 text-red-400"></i>Still Due
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:15%">
                                    <i class="fas fa-arrow-right-arrow-left mr-1 text-indigo-400"></i>Repaid Via
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                    <i class="fas fa-calendar-check mr-1 text-teal-400"></i>Monthly EMI
                                </th>
                                @if($canCollect)
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:8%">
                                        <i class="fas fa-cogs mr-1 text-gray-400"></i>Actions
                                    </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($employeeBook as $key => $row)
                                <tr class="hover:bg-blue-50 transition-colors duration-150 {{ $canCollect ? 'cursor-pointer' : '' }}"
                                    @if($canCollect) data-repay-user="{{ $row['user_id'] }}" @endif>
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
                                                    {{ $row['loans'] }} {{ Str::plural('loan', $row['loans']) }}@if($row['latest']) · latest {{ \Carbon\Carbon::parse($row['latest'])->format('d M Y') }}@endif
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    @if($showCompany)
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @include('payroll.partials.company-chip', ['company' => $row['user']?->company])
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-medium text-gray-700">৳ {{ $taka($row['taken']) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-sm font-semibold text-emerald-600">৳ {{ $taka($row['paid']) }}</span>
                                        <p class="text-[11px] text-gray-400 leading-tight mt-0.5">
                                            {{ $row['taken'] > 0 ? round($row['paid'] / $row['taken'] * 100) : 0 }}% of what was lent
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-bold text-red-600">৳ {{ $taka($row['due']) }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @include('loans.partials.repaid-via', ['salary' => $row['via_salary'], 'cash' => $row['via_cash']])
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        @if($row['emi'] > 0)
                                            <span class="text-sm font-medium text-gray-700">৳ {{ $taka($row['emi']) }}</span>
                                        @else
                                            <span class="text-sm text-gray-300">—</span>
                                        @endif
                                    </td>
                                    @if($canCollect)
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button type="button"
                                                    class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-green-50 text-green-600 border border-green-200 hover:bg-green-600 hover:text-white transition-colors duration-150 repay-btn"
                                                    data-repay-user="{{ $row['user_id'] }}"
                                                    title="Record a repayment">
                                                    <i class="fas fa-rotate-left text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $empCols }}" class="text-center py-12">
                                        <div class="flex flex-col items-center gap-2">
                                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i class="fas fa-building-columns fa-2x text-gray-300"></i>
                                            </div>
                                            <h4 class="text-gray-500 text-base font-semibold mt-1">No active loans</h4>
                                            <p class="text-gray-400 text-sm">Nobody on this payroll is carrying a balance right now.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        {{-- THE FOOT. "Repaid via" is the one column a single figure
                             cannot carry, so it foots as the split — how much came
                             out of salary and how much was handed in, which is the
                             whole reason the column exists. --}}
                        @if($employeeBook->total() > 0)
                            @php
                                $page = $employeeBook->getCollection();
                                $ftSalary = $page->sum('via_salary');
                                $ftCash = $page->sum('via_cash');
                            @endphp
                            <tfoot class="border-t-2 border-gray-200">
                                <tr class="bg-gray-100 text-sm border-t border-gray-300">
                                    <td colspan="{{ 2 + ($showCompany ? 1 : 0) }}" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $page->count() }} {{ Str::plural('Person', $page->count()) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">৳ {{ $taka($page->sum('taken')) }}</td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-emerald-700">৳ {{ $taka($page->sum('paid')) }}</td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-red-700">৳ {{ $taka($page->sum('due')) }}</td>
                                    <td class="px-4 py-3.5 text-left text-gray-500 text-xs whitespace-nowrap">
                                        Salary <span class="text-gray-700 font-semibold">৳ {{ $taka($ftSalary) }}</span>
                                        · Cash <span class="text-gray-700 font-semibold">৳ {{ $taka($ftCash) }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">৳ {{ $taka($page->sum('emi')) }}</td>
                                    @if($canCollect)<td></td>@endif
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-t border-gray-200">
                    {{ $employeeBook->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- ══ LOAN REGISTER ══
         One row per loan, running and cleared alike: "how much of the ৳30,000
         taken in May is left" is a question about a LOAN, not about a person. --}}
    <div class="states-table bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="states-table-container">
            <div class="px-6 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-100">
                <div>
                    <h2 class="text-gray-800 text-base font-bold">
                        <i class="fas fa-book-bookmark mr-2 text-blue-500"></i>Loan Register
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        every loan ever taken{{ $showCompany ? ', in every company' : '' }} — click one for its whole history
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-xs text-gray-400">{{ $register->total() }} {{ Str::plural('record', $register->total()) }}</span>
                    @include('payroll.partials.export-buttons', ['routePrefix' => 'role.loans.export', 'table' => 'register', 'count' => $register->total(), 'exportRole' => $role])
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
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-calendar-day mr-1 text-cyan-400"></i>Taken On
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Loan Taken
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:13%">
                                    <i class="fas fa-rotate-left mr-1 text-emerald-400"></i>Paid Till Now
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-hourglass-half mr-1 text-red-400"></i>Still Due
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:13%">
                                    <i class="fas fa-arrow-right-arrow-left mr-1 text-indigo-400"></i>Repaid Via
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-tag mr-1 text-yellow-400"></i>Status
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-cogs mr-1 text-gray-400"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($register as $key => $loan)
                                @php
                                    $via = $loan->repaidByMethod();
                                    // Pre-history repayments have no method on record; they
                                    // count as cash so the split still adds to what was paid.
                                    $viaCash = $via['cash'] + (float) $loan->opening_paid_amount;
                                @endphp
                                <tr class="hover:bg-blue-50 transition-colors duration-150 cursor-pointer" data-loan-id="{{ $loan->id }}">
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs font-bold">
                                            {{ ($register->currentPage() - 1) * $register->perPage() + $key + 1 }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold uppercase">
                                                {{ strtoupper(substr($loan->user?->name ?? '?', 0, 2)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <span class="block text-sm font-medium text-gray-800">
                                                    {{ $loan->user?->name ?? 'Employee #' . $loan->user_id }}
                                                </span>
                                                <span class="block text-xs font-mono {{ $loan->user?->employee_id_no ? 'text-gray-500' : 'text-gray-300' }}">
                                                    {{ $loan->user?->employee_id_no ?: '—' }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    @if($showCompany)
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @include('payroll.partials.company-chip', ['company' => $loan->user?->company])
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-sm text-gray-700">
                                            {{ $loan->start_date ? \Carbon\Carbon::parse($loan->start_date)->format('d M Y') : '—' }}
                                        </span>
                                        <p class="text-[11px] text-gray-400 leading-tight mt-0.5">
                                            {{ $loan->emi_months ? $loan->emi_months . '-month EMI plan' : 'no EMI plan' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-medium text-gray-700">৳ {{ $taka($loan->amount) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-sm font-semibold text-emerald-600">৳ {{ $taka($loan->paid_amount) }}</span>
                                        <p class="text-[11px] text-gray-400 leading-tight mt-0.5">
                                            {{ $loan->progress_pct }}%@if($loan->last_paid_on) · last {{ \Carbon\Carbon::parse($loan->last_paid_on)->format('d M Y') }}@endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-bold {{ $loan->outstanding > 0 ? 'text-red-600' : 'text-green-600' }}">
                                            ৳ {{ $taka($loan->outstanding) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @include('loans.partials.repaid-via', ['salary' => $via['salary'], 'cash' => $viaCash])
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        @if($loan->is_cleared)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                                                <i class="fas fa-check-circle text-xs"></i> Cleared
                                            </span>
                                            <p class="text-[11px] text-gray-400 mt-1">
                                                {{ $loan->cleared_on ? \Carbon\Carbon::parse($loan->cleared_on)->format('d M Y') : '' }}
                                            </p>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700 border border-yellow-200">
                                                <i class="fas fa-hourglass-half text-xs"></i> Running
                                            </span>
                                            <p class="text-[11px] text-gray-500 mt-1">
                                                @if($loan->monthly_deduction > 0)
                                                    ৳ {{ $taka($loan->monthly_deduction) }}/mo · {{ $loan->instalments_left }} left
                                                @else
                                                    no EMI plan
                                                @endif
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-green-50 text-green-700 border border-green-200 hover:bg-green-600 hover:text-white transition-colors duration-150 loan-view-btn"
                                                data-loan-id="{{ $loan->id }}" title="Open this loan">
                                                <i class="fas fa-eye"></i>
                                                <span>View</span>
                                            </button>
                                            @can('edit loan')
                                                @if($loan->paid_amount == 0)
                                                    <button
                                                        class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-yellow-50 text-yellow-600 border border-yellow-200 hover:bg-yellow-500 hover:text-white transition-colors duration-150 edit-item-btn"
                                                        data-item_id="{{ $loan->id }}"
                                                        data-user_id="{{ $loan->user_id }}"
                                                        data-bank_id="{{ $loan->bank_id }}"
                                                        data-amount="{{ $loan->amount }}"
                                                        data-remaining_amount="{{ $loan->remaining_amount }}"
                                                        data-monthly_deduction="{{ $loan->monthly_deduction }}"
                                                        data-status="{{ $loan->status }}"
                                                        data-start_date="{{ $loan->start_date }}"
                                                        data-end_date="{{ $loan->end_date }}"
                                                        title="Edit this loan">
                                                        <i class="fas fa-edit text-xs"></i>
                                                    </button>
                                                @endif
                                            @endcan
                                            @can('delete loan')
                                                @if($loan->paid_amount == 0)
                                                    <button type="button"
                                                        class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-50 text-red-600 border border-red-200 hover:bg-red-600 hover:text-white transition-colors duration-150"
                                                        onclick="event.stopPropagation(); confirmDelete('{{ $loan->id }}', 'this loan')"
                                                        title="Delete this loan">
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
                                            <h4 class="text-gray-500 text-base font-semibold mt-1">No loan has been disbursed yet</h4>
                                            <p class="text-gray-400 text-sm">Disburse one and it will appear here with its whole history.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        {{-- THE FOOT: the three money columns sum; STATUS counts
                             instead, because "running or cleared" has no total — and
                             a book whose foot says how many loans are still alive
                             answers the question the total would raise. --}}
                        @if($register->total() > 0)
                            @php
                                $page = $register->getCollection();
                                $rgOpen = $page->filter(fn ($l) => ! $l->is_cleared)->count();
                                $rgSalary = $page->sum(fn ($l) => $l->repaidByMethod()['salary']);
                                $rgCash = $page->sum(fn ($l) => $l->repaidByMethod()['cash'] + (float) $l->opening_paid_amount);
                            @endphp
                            <tfoot class="border-t-2 border-gray-200">
                                <tr class="bg-gray-100 text-sm border-t border-gray-300">
                                    <td colspan="{{ 3 + ($showCompany ? 1 : 0) }}" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $page->count() }} {{ Str::plural('Loan', $page->count()) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">৳ {{ $taka($page->sum(fn ($l) => (float) $l->amount)) }}</td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-emerald-700">৳ {{ $taka($page->sum(fn ($l) => $l->paid_amount)) }}</td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-red-700">৳ {{ $taka($page->sum(fn ($l) => $l->outstanding)) }}</td>
                                    <td class="px-4 py-3.5 text-left text-gray-500 text-xs whitespace-nowrap">
                                        Salary <span class="text-gray-700 font-semibold">৳ {{ $taka($rgSalary) }}</span>
                                        · Cash <span class="text-gray-700 font-semibold">৳ {{ $taka($rgCash) }}</span>
                                    </td>
                                    <td colspan="2" class="px-4 py-3.5 text-center text-gray-500 text-xs whitespace-nowrap">
                                        <span class="text-yellow-700 font-semibold">{{ $rgOpen }} running</span>
                                        · <span class="text-green-700 font-semibold">{{ $page->count() - $rgOpen }} cleared</span>
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-t border-gray-200">
                    {{ $register->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- ══ LOAN TRANSACTIONS ══
         The trail told as loans rather than as movements: every row names its
         loan and carries where that loan stood at that moment. --}}
    <div class="states-table bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="states-table-container">
            <div class="px-6 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-100">
                <div>
                    <h2 class="text-gray-800 text-base font-bold">
                        <i class="fas fa-receipt mr-2 text-blue-500"></i>Loan Transactions{{ $showCompany ? ' — Every Company' : '' }}
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        every movement on the book — money lent out and money coming back
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-xs text-gray-400">{{ $transactions->total() }} {{ Str::plural('record', $transactions->total()) }}</span>
                    @include('payroll.partials.export-buttons', ['routePrefix' => 'role.loans.export', 'table' => 'transactions', 'count' => $transactions->total(), 'exportRole' => $role])
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
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:8%">
                                    <i class="fas fa-tag mr-1 text-yellow-400"></i>Type
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:14%">
                                    <i class="fas fa-note-sticky mr-1 text-gray-400"></i>Note
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-arrow-right-arrow-left mr-1 text-indigo-400"></i>Method
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-building-columns mr-1 text-purple-400"></i>The Loan
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-rotate-left mr-1 text-emerald-400"></i>Paid Till Then
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
                                @php $at = $balances[$txn->id] ?? null; @endphp
                                <tr class="hover:bg-blue-50 transition-colors duration-150 cursor-pointer" data-loan-id="{{ $txn->loan_id }}">
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs font-bold">
                                            {{ ($transactions->currentPage() - 1) * $transactions->perPage() + $key + 1 }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                        {{ \Carbon\Carbon::parse($txn->date)->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800">
                                        {{ $txn->user?->name ?? 'Employee #' . $txn->user_id }}
                                    </td>
                                    @if($showCompany)
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @include('payroll.partials.company-chip', ['company' => $txn->user?->company])
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        @if($txn->type === 'repay')
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                                                <i class="fas fa-arrow-down text-xs"></i> Repay
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">
                                                <i class="fas fa-arrow-up text-xs"></i> Disburse
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $txn->note ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($txn->method === 'salary')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                                Salary deduction
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                                                {{ $txn->bank?->name ?: 'Cash / bank' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($at)
                                            <span class="text-sm font-medium text-gray-700">৳ {{ $taka($at['loan']->amount) }}</span>
                                            <p class="text-[11px] text-gray-400 leading-tight mt-0.5">
                                                taken {{ $at['loan']->start_date ? \Carbon\Carbon::parse($at['loan']->start_date)->format('d M Y') : '—' }}
                                            </p>
                                        @else
                                            <span class="text-sm text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        @if($at)
                                            <span class="text-sm font-semibold text-emerald-600">৳ {{ $taka($at['paid']) }}</span>
                                        @else
                                            <span class="text-sm text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        @if($at)
                                            <span class="text-sm font-semibold {{ $at['due'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                                ৳ {{ $taka($at['due']) }}
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-bold {{ $txn->type === 'repay' ? 'text-emerald-700' : 'text-amber-700' }}">
                                            ৳ {{ $taka($txn->amount) }}
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
                                            <h4 class="text-gray-500 text-base font-semibold mt-1">No transactions</h4>
                                            <p class="text-gray-400 text-sm">Disbursements and repayments both land here as they happen.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        {{-- THE FOOT, and the one table here where a single Amount
                             total would be a lie: these rows run in both directions,
                             so summing the column nets a disbursement against a
                             repayment and calls the result "amount". It foots as
                             both directions plus the net. "Due after" is a balance
                             at a moment in time — adding fifteen of them together
                             would produce a figure that never existed. --}}
                        @if($transactions->total() > 0)
                            @php
                                $page = $transactions->getCollection();
                                $lent = $page->where('type', 'disburse')->sum(fn ($t) => (float) $t->amount);
                                $back = $page->where('type', 'repay')->sum(fn ($t) => (float) $t->amount);
                            @endphp
                            <tfoot class="border-t-2 border-gray-200">
                                <tr class="bg-gray-100 text-sm border-t border-gray-300">
                                    <td colspan="{{ 7 + ($showCompany ? 1 : 0) }}" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $page->count() }} {{ Str::plural('Transaction', $page->count()) }}
                                    </td>
                                    <td colspan="2" class="px-4 py-3.5 text-right text-gray-400 text-xs italic whitespace-nowrap">
                                        balances, not sums
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        <span class="font-extrabold text-gray-800">৳ {{ $taka($lent - $back) }}</span>
                                        <span class="text-[11px] text-gray-400 font-normal">net</span>
                                        <p class="text-[11px] text-gray-400 font-normal leading-tight mt-0.5">
                                            ৳ {{ $taka($lent) }} lent · ৳ {{ $taka($back) }} repaid
                                        </p>
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-t border-gray-200">
                    {{ $transactions->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Modals ── --}}
@include('loans.create-modal')
@include('loans.edit-modal')
@include('loans.delete-modal')
@include('loans.repay-modal')

<div id="loanDetailModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="bg-white w-11/12 md:max-w-4xl mx-auto rounded-2xl shadow-lg z-50 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100 sticky top-0 bg-white z-10">
            <h3 class="text-base font-bold text-gray-800">
                <i class="fas fa-building-columns mr-2 text-blue-500"></i>Staff Loan
            </h3>
            <button class="modal-close-detail text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <div id="loanDetailBody" class="p-6">
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

            $('.select2').select2();

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            /* ── Disburse ── */
            $('.create-new-btn').click(function() {
                $('#createModal').removeClass('hidden');
            });

            /* ── Edit ── */
            $('.edit-item-btn').click(function(e) {
                e.stopPropagation();
                $('#editItemId').val($(this).data('item_id'));
                $('#edit_user_id').val($(this).data('user_id')).trigger('change');
                $('#edit_bank_id').val($(this).data('bank_id')).trigger('change');
                $('#edit_amount').val($(this).data('amount'));
                $('#edit_remaining_amount').val($(this).data('remaining_amount'));
                $('#edit_monthly_deduction').val($(this).data('monthly_deduction'));
                $('#edit_start_date').val($(this).data('start_date'));
                $('#edit_end_date').val($(this).data('end_date'));
                $('#edit_status').val($(this).data('status')).trigger('change');
                $('#editModal').removeClass('hidden');
            });

            /* ── One loan's whole history ──
               Fetched rather than rendered inline: the register can run to
               hundreds of rows, and shipping every loan's payment table with the
               page would cost far more than the one the user actually opens. */
            function openLoan(id) {
                $('#loanDetailBody').html('<div class="text-center text-gray-400 py-10"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
                $('#loanDetailModal').removeClass('hidden');

                $.get('{{ route('role.loans.index', ['role' => $role]) }}/' + id)
                    .done(function(html) { $('#loanDetailBody').html(html); })
                    .fail(function() {
                        $('#loanDetailBody').html('<p class="text-center text-red-500 py-10">This loan could not be opened.</p>');
                    });
            }

            $(document).on('click', '.loan-view-btn', function(e) {
                e.stopPropagation();
                openLoan($(this).data('loan-id'));
            });

            $(document).on('click', 'tr[data-loan-id]', function() {
                openLoan($(this).data('loan-id'));
            });

            $(document).on('click', '#loanDetailBody .loan-repay-btn', function() {
                $('#loanDetailModal').addClass('hidden');
                openRepay($(this).data('loan-id'));
            });

            /* ── Record a repayment ── */
            function openRepay(loanId, userId) {
                $('#repayForm')[0].reset();
                $('#repay_bank_row').addClass('hidden');
                $('#repayError').addClass('hidden').text('');

                if (loanId) {
                    $('#repay_loan_id').val(loanId).trigger('change');
                } else if (userId) {
                    // First open loan this person is carrying — the picker still
                    // lets the user switch when they have more than one.
                    var $match = $('#repay_loan_id option[data-user="' + userId + '"]').first();
                    if ($match.length) $('#repay_loan_id').val($match.val()).trigger('change');
                }

                $('#repayModal').removeClass('hidden');
            }

            $(document).on('click', '.repay-btn', function(e) {
                e.stopPropagation();
                openRepay(null, $(this).data('repay-user'));
            });

            $(document).on('click', 'tr[data-repay-user]', function() {
                openRepay(null, $(this).data('repay-user'));
            });

            // The outstanding hint and the max the form will accept both follow
            // the chosen loan, so an over-payment is caught before it is sent.
            $('#repay_loan_id').on('change', function() {
                var $opt = $(this).find('option:selected');
                var due = parseFloat($opt.data('due') || 0);

                $('#repay_due_hint').text(due > 0 ? '৳ ' + due.toLocaleString() + ' still due on this loan' : '');
                $('#repay_amount').attr('max', due > 0 ? due : null);
            });

            $('#repay_method').on('change', function() {
                $('#repay_bank_row').toggleClass('hidden', $(this).val() !== 'bank');
            });

            $('#repaySubmit').click(function() {
                var $btn = $(this);

                if (!$('#repay_loan_id').val()) {
                    $('#repayError').removeClass('hidden').text('Pick the loan this money is coming back on.');
                    return;
                }
                if (!($('#repay_amount').val() > 0)) {
                    $('#repayError').removeClass('hidden').text('Enter how much came back.');
                    return;
                }

                $btn.prop('disabled', true);

                $.ajax({
                    url: '{{ route('role.loans.repay', ['role' => $role]) }}',
                    method: 'POST',
                    data: $('#repayForm').serialize(),
                    success: function(res) {
                        $btn.prop('disabled', false);
                        if (res.success) {
                            $('#repayModal').addClass('hidden');
                            Swal.fire({ icon: 'success', title: 'Recorded', text: res.message });
                            setTimeout(function() { window.location.reload(); }, 900);
                        } else {
                            $('#repayError').removeClass('hidden').text(res.message || 'The repayment could not be recorded.');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false);
                        $('#repayError').removeClass('hidden').text('Something went wrong.');
                    }
                });
            });

            /* ── Closing ── */
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
            $('.modal-close-repay, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-repay').length) {
                    $('#repayModal').addClass('hidden');
                }
            });
            $('.modal-close-detail, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-detail').length) {
                    $('#loanDetailModal').addClass('hidden');
                }
            });

            /* ── Disburse form ── */
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (!validateCreateForm()) return;

                $.ajax({
                    url: $('#createForm').attr('action'),
                    method: 'POST',
                    data: new FormData($('#createForm')[0]),
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Loan disbursed.' });
                            $('#createModal').addClass('hidden');
                            $('#createForm')[0].reset();
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to disburse the loan.' });
                    }
                });
            });

            /* ── Edit form ── */
            $('#editSubmit').click(function() {
                if (!validateEditForm()) return;

                $.ajax({
                    url: $(this).data('action'),
                    method: 'POST',
                    data: new FormData($('#editForm')[0]),
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Loan updated.' });
                            $('#editModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' });
                        }
                    },
                    error: function (xhr) {
                        console.error('❌ Error:', xhr.responseText);
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            });

            /* ── Delete ── */
            $('#confirmDeleteBtn').click(function() {
                $.ajax({
                    url: $(this).data('action'),
                    method: 'DELETE',
                    data: { item_id: $(this).data('item-id') },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Loan deleted.' });
                            $('#deleteModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message });
                        }
                    },
                    error: function (xhr) {
                        console.error('❌ Error:', xhr.responseText);
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            });
        });

        function validateCreateForm() {
            let isValid = true;
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');

            if (!$('#create_user_id').val()) {
                $('#create_user_msg').removeClass('hidden');
                isValid = false;
            }
            if (!$('#create_amount').val().trim()) {
                $('#create_amount').next('.error-message').removeClass('hidden');
                $('#create_amount').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#create_start_date').val().trim()) {
                $('#create_start_date').next('.error-message').removeClass('hidden');
                $('#create_start_date').addClass('border-red-500');
                isValid = false;
            }

            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');

            if (!$('#edit_user_id').val()) {
                $('#edit_user_msg').removeClass('hidden');
                isValid = false;
            }
            if (!$('#edit_amount').val().trim()) {
                $('#edit_amount').next('.error-message').removeClass('hidden');
                $('#edit_amount').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#edit_start_date').val().trim()) {
                $('#edit_start_date').next('.error-message').removeClass('hidden');
                $('#edit_start_date').addClass('border-red-500');
                isValid = false;
            }

            return isValid;
        }

        function confirmDelete(id, name = null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterHeader = document.querySelector('.filter-container .filter-header');
            const filterContent = document.querySelector('.filter-container .filter-content');

            if (filterHeader && filterContent) {
                filterHeader.addEventListener('click', function() {
                    this.classList.toggle('active');
                    filterContent.classList.toggle('active');
                });
            }

            const resetBtn = document.querySelector('.filter-container .reset-btn');
            if (resetBtn) {
                resetBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    // Company stays: it is a payroll-wide context set by the
                    // toolbar, not one of this page's own filters.
                    window.location = '{{ route('role.loans.index', array_filter(['role' => $role, 'company_id' => request('company_id')])) }}';
                });
            }
        });
    </script>
@endsection
