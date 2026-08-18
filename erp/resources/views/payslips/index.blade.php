@extends('layout.app')
@section('meta-information')
    <title>Payslip Management</title>
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
    $book = app(\App\Services\PayslipBookService::class);

    // Payroll figures show as whole taka, the same way the salary sheet next
    // door prints them. Display only — the stored amounts keep their paise.
    $taka = fn ($amount) => number_format(round((float) $amount));

    // The company column earns its place only when the page is showing more than
    // one — otherwise every row repeats the same chip and costs a column doing it.
    $showCompany = $slips->pluck('user.company_id')->filter()->unique()->count() > 1;

    $canManage = auth()->user()->canAny(['edit payslip', 'delete payslip']);

    // Written as the columns themselves rather than a bare number, because
    // Company and Actions both come and go and a hand-counted total drifts.
    $cols = count(['#', 'Employee', 'Month', 'Issued', 'Gross', 'Additions', 'Deductions', 'Net', 'Still Due', 'Status', 'Slip'])
        + ($showCompany ? 1 : 0) + ($canManage ? 1 : 0);

    $statusChip = [
        'paid'    => ['bg-green-100 text-green-700 border-green-200', 'fa-check-circle'],
        'partial' => ['bg-amber-100 text-amber-700 border-amber-200', 'fa-hourglass-half'],
        'accrued' => ['bg-blue-100 text-blue-700 border-blue-200', 'fa-file-invoice-dollar'],
    ];
@endphp

<div class="p-4 md:p-6 space-y-6">
    @include('layout.payroll-tabs')

    {{-- ── Header ── --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900 leading-tight">
                <i class="fas fa-file-lines text-blue-500 mr-1.5"></i>Payslip Management
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Every payslip issued — what it was worth, what came off it, and what is still owed.
            </p>
        </div>
        @can('create payslip')
            <div class="flex flex-wrap items-center gap-2">
                <button class="create-item-btn inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                    <i class="fas fa-plus"></i> Issue Payslip
                </button>
            </div>
        @endcan
    </div>

    {{-- ── Summary Cards ──
         Net less Still Due is exactly Paid, so the cards reconcile against one
         another the way the loan tiles do. --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @include('payroll.partials.kpi', [
            'label'    => 'Payslips Issued',
            'value'    => number_format($summary['count']),
            'icon'     => 'fa-file-lines',
            'iconBg'   => '#dbeafe',
            'iconText' => '#2563eb',
            'foot'     => $summary['count']
                ? $summary['employees'] . ' ' . Str::plural('employee', $summary['employees'])
                    . ' · ' . $summary['months'] . ' ' . Str::plural('month', $summary['months'])
                : 'nothing issued yet',
        ])

        @include('payroll.partials.kpi', [
            'label'    => 'Gross',
            'value'    => '৳ ' . $taka($summary['gross']),
            'icon'     => 'fa-money-bill-wave',
            'iconBg'   => '#ede9fe',
            'iconText' => '#7c3aed',
            // Gross + additions − deductions = net, so the card says the two
            // pieces that close the gap rather than leaving it unexplained.
            'foot'     => '+ ৳ ' . $taka($summary['additions']) . ' additions · − ৳ ' . $taka($summary['deductions']) . ' deductions',
        ])

        @include('payroll.partials.kpi', [
            'label'     => 'Net Payable',
            'value'     => '৳ ' . $taka($summary['net']),
            'icon'      => 'fa-hand-holding-dollar',
            'iconBg'    => '#cffafe',
            'iconText'  => '#0891b2',
            'foot'      => $book->statusSplit($summary['by_status']),
        ])

        @include('payroll.partials.kpi', [
            'label'     => 'Still Due',
            'value'     => '৳ ' . $taka($summary['due']),
            'icon'      => 'fa-hourglass-half',
            'iconBg'    => $summary['due'] > 0 ? '#fee2e2' : '#dcfce7',
            'iconText'  => $summary['due'] > 0 ? '#dc2626' : '#16a34a',
            'valueTone' => $summary['due'] > 0 ? 'text-red-600' : 'text-green-600',
            'foot'      => $summary['net'] > 0
                ? '৳ ' . $taka($summary['paid']) . ' paid · ' . $summary['paid_pct'] . '% of net'
                : 'nothing payable',
        ])
    </div>

    {{-- ── Statement picker ──
         Pick a person and a month and open their payslip statement. Only months
         that actually have a payslip are offered: a month with nothing issued has
         no statement to open. --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <form action="{{ route('role.payslips.statement.lookup', ['role' => $role]) }}" method="get" target="_blank"
              class="flex flex-wrap items-end gap-3">
            @if(request('company_id'))
                <input type="hidden" name="company_id" value="{{ request('company_id') }}">
            @endif
            <div class="min-w-[220px] flex-1 sm:flex-none">
                <label for="statement_user" class="block text-xs font-semibold text-gray-500 mb-1">Employee</label>
                <select id="statement_user" name="user_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}@if($user->employee_id_no) · {{ $user->employee_id_no }}@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[160px]">
                <label for="statement_period" class="block text-xs font-semibold text-gray-500 mb-1">Month</label>
                <select id="statement_period" name="period"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @forelse($periods as $period)
                        <option value="{{ $period }}">{{ $book->periodLabel($period) }}</option>
                    @empty
                        <option value="">No payslips issued yet</option>
                    @endforelse
                </select>
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                <i class="fas fa-receipt"></i> View Statement
            </button>
        </form>
    </div>

    {{-- ══ THE REGISTER ══ --}}
    <div class="states-table bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="states-table-container">
            <div class="px-6 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-100">
                <div>
                    <h2 class="text-gray-800 text-base font-bold">
                        <i class="fas fa-list mr-2 text-blue-500"></i>All Payslips{{ $showCompany ? ' — Every Company' : '' }}
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        month · gross · deductions · net · still due · click a row for the statement
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-xs text-gray-400">{{ $register->total() }} {{ Str::plural('record', $register->total()) }}</span>
                    @include('payroll.partials.export-buttons', [
                        'routePrefix' => 'role.payslips.export',
                        'count'       => $register->total(),
                        'exportRole'  => $role,
                        'pageKeys'    => ['page'],
                    ])
                </div>
            </div>

            <div class="states-table-content">
                {{-- ── Status quick filter ── --}}
                @php $activeStatus = request('status'); @endphp
                <div class="px-4 pt-4 flex flex-wrap items-center gap-2">
                    @foreach([null => 'All', 'accrued' => 'Accrued', 'paid' => 'Paid', 'partial' => 'Partial'] as $value => $chipLabel)
                        @php
                            $isOn = (string) $activeStatus === (string) $value;
                            $chipUrl = request()->fullUrlWithQuery(['status' => $value ?: null, 'page' => null]);
                        @endphp
                        <a href="{{ $chipUrl }}"
                           class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors
                                  {{ $isOn ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400 hover:text-blue-600' }}">
                            {{ $chipLabel }}
                            @if($value){{ ' (' . ($summary['by_status'][$value] ?? 0) . ')' }}@endif
                        </a>
                    @endforeach
                </div>

                {{-- ── Filters ── --}}
                <form action="" method="get">
                    @if(request('company_id'))
                        <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                    @endif
                    @if(request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    <div class="filter-container">
                        <div class="filter-header {{ request()->hasAny(['user_id', 'period', 'search']) ? 'active' : '' }}">
                            <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="filter-content {{ request()->hasAny(['user_id', 'period', 'search']) ? 'active' : '' }}">
                            <div class="closest filter-row">
                                <div class="filter-group">
                                    <label for="search">Employee / Payslip No.</label>
                                    <input type="text" id="search" name="search" class="form-control"
                                           value="{{ request('search') }}" placeholder="e.g. EG25 109" style="width:100%">
                                </div>
                                <div class="filter-group">
                                    <label for="user_id">Employee</label>
                                    <select id="user_id" name="user_id" class="form-control select2" style="width: 100%"
                                            onchange="getEmpSalaries(this, '#employee_salary_id')"
                                            data-action="{{ route('role.get-employee-salary', ['role' => $role]) }}">
                                        <option value="">All</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" {{ $user->id == request('user_id') ? 'selected' : '' }}>{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="period">Month</label>
                                    <select id="period" name="period" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        @foreach($periods as $period)
                                            <option value="{{ $period }}" {{ $period === request('period') ? 'selected' : '' }}>
                                                {{ $book->periodLabel($period) }}
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
                                    <i class="fas fa-calendar-day mr-1 text-cyan-400"></i>Month
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-calendar-check mr-1 text-indigo-400"></i>Issued
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Gross
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-plus-circle mr-1 text-emerald-400"></i>Additions
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-minus-circle mr-1 text-red-400"></i>Deductions
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-hand-holding-dollar mr-1 text-teal-400"></i>Net
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-hourglass-half mr-1 text-red-400"></i>Still Due
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:9%">
                                    <i class="fas fa-tag mr-1 text-yellow-400"></i>Status
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:8%">
                                    <i class="fas fa-file-pdf mr-1 text-gray-400"></i>Slip
                                </th>
                                @if($canManage)
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:8%">
                                        <i class="fas fa-cogs mr-1 text-gray-400"></i>Actions
                                    </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($register as $key => $value)
                                @php
                                    $salary = $value->employee_salary;
                                    $status = $book->status($value);
                                    $due = $book->due($value);
                                    [$chipClass, $chipIcon] = $statusChip[$status];
                                    $statementUrl = route('role.payslips.statement', ['role' => $role, 'payslip' => $value->id]);
                                @endphp
                                <tr class="hover:bg-blue-50 transition-colors duration-150 cursor-pointer"
                                    data-statement="{{ $statementUrl }}">
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
                                                <span class="block text-xs font-mono {{ $value->payslip_number ? 'text-gray-500' : 'text-gray-300' }}">
                                                    {{ $value->payslip_number ?: '—' }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    @if($showCompany)
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @include('payroll.partials.company-chip', ['company' => $value->user?->company])
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                        {{ $book->periodLabel($book->period($value)) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $value->issue_date ? \Carbon\Carbon::parse($value->issue_date)->format('d M Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-medium text-gray-700">৳ {{ $taka($salary->gross_salary ?? 0) }}</span>
                                    </td>
                                    {{-- Additions earns its column: without it the row does not add
                                         up, and a reader who tries gross − deductions and comes up
                                         short will assume the table is wrong. --}}
                                    @php $additions = $book->additions($value); @endphp
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        @if($additions != 0)
                                            <span class="text-sm font-semibold {{ $additions > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                                {{ $additions > 0 ? '+' : '−' }} ৳ {{ $taka(abs($additions)) }}
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-medium text-red-600">৳ {{ $taka($salary->total_deductions ?? 0) }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-bold text-teal-700">৳ {{ $taka($salary->net_salary ?? 0) }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        @if($due > 0)
                                            <span class="text-sm font-bold text-red-600">৳ {{ $taka($due) }}</span>
                                        @else
                                            <span class="text-sm text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold border {{ $chipClass }}">
                                            <i class="fas {{ $chipIcon }} text-xs"></i> {{ ucfirst($status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <a href="{{ $statementUrl }}" target="_blank" rel="noopener"
                                               onclick="event.stopPropagation()"
                                               class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-green-50 text-green-700 border border-green-200 hover:bg-green-600 hover:text-white transition-colors duration-150"
                                               title="Open the statement — print it or save it as a PDF">
                                                <i class="fas fa-print"></i>
                                                <span>Print</span>
                                            </a>
                                            @if($value->pdf_path)
                                                <a href="{{ asset($value->pdf_path) }}" target="_blank" rel="noopener"
                                                   onclick="event.stopPropagation()"
                                                   class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-600 hover:text-white transition-colors duration-150"
                                                   title="The uploaded slip file">
                                                    <i class="fas fa-paperclip text-xs"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                    @if($canManage)
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                @can('edit payslip')
                                                    <button
                                                        class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-yellow-50 text-yellow-600 border border-yellow-200 hover:bg-yellow-500 hover:text-white transition-colors duration-150 edit-item-btn"
                                                        onclick="event.stopPropagation()"
                                                        data-item_id="{{ $value->id }}"
                                                        data-user_id="{{ $value->user_id }}"
                                                        data-employee_salary_id="{{ $value->employee_salary_id }}"
                                                        data-payslip_number="{{ $value->payslip_number }}"
                                                        data-issue_date="{{ $value->issue_date }}"
                                                        data-pdf_path="{{ $value->pdf_path }}"
                                                        data-get_target_action="{{ route('role.get-employee-salary', ['role' => $role]) }}"
                                                        title="Edit this payslip">
                                                        <i class="fas fa-edit text-xs"></i>
                                                    </button>
                                                @endcan
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $cols }}" class="text-center py-12">
                                        <div class="flex flex-col items-center gap-2">
                                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i class="fas fa-file-lines fa-2x text-gray-300"></i>
                                            </div>
                                            <h4 class="text-gray-500 text-base font-semibold mt-1">No payslips found</h4>
                                            <p class="text-gray-400 text-sm">Finalise a salary month, then issue its payslip here.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        {{-- THE FOOT: gross, deductions, net and due all sum, because
                             each row is that month's own figure rather than a running
                             balance. STATUS has no total — it counts instead, which is
                             the question the column actually raises. --}}
                        @if($register->total() > 0)
                            @php
                                $page = $register->getCollection();
                                $ftStatus = $page->countBy(fn ($s) => $book->status($s));
                            @endphp
                            <tfoot class="border-t-2 border-gray-200">
                                <tr class="bg-gray-100 text-sm border-t border-gray-300">
                                    <td colspan="{{ 4 + ($showCompany ? 1 : 0) }}" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $page->count() }} {{ Str::plural('Payslip', $page->count()) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">
                                        ৳ {{ $taka($page->sum(fn ($s) => (float) ($s->employee_salary->gross_salary ?? 0))) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-emerald-700">
                                        @php $ftAdd = $page->sum(fn ($s) => $book->additions($s)); @endphp
                                        {{ $ftAdd != 0 ? '৳ ' . $taka($ftAdd) : '—' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-red-700">
                                        ৳ {{ $taka($page->sum(fn ($s) => (float) ($s->employee_salary->total_deductions ?? 0))) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-teal-800">
                                        ৳ {{ $taka($page->sum(fn ($s) => (float) ($s->employee_salary->net_salary ?? 0))) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-red-700">
                                        ৳ {{ $taka($page->sum(fn ($s) => $book->due($s))) }}
                                    </td>
                                    <td colspan="{{ 2 + ($canManage ? 1 : 0) }}" class="px-4 py-3.5 text-center text-gray-500 text-xs whitespace-nowrap">
                                        {{ $book->statusSplit($ftStatus->all()) }}
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
</div>

@include('payslips.create-modal')
@include('payslips.edit-modal')
@include('payslips.delete-modal')

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
            $('.create-item-btn').click(function() {
                $('#createModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-item-btn').click(function() {
                const item_id = $(this).data('item_id');
                const userId = $(this).data('user_id');
                const employee_salary_id = $(this).data('employee_salary_id');
                const payslip_number = $(this).data('payslip_number');
                const issue_date = $(this).data('issue_date');
                // const pdf_path = $(this).data('pdf_path');
                
                // Set values in the edit form
                $('#editItemId').val(item_id);
                select2SetValueNoEvent('#edit_user_id', userId);                
                $('#edit_payslip_number').val(payslip_number);
                $('#edit_issue_date').val(issue_date);
                // $('#edit_pdf_path').val(pdf_path);
                $('#editModal').removeClass('hidden');
                if (userId) {
                    $.ajax({
                        url: $(this).data('get_target_action'),
                        method: 'GET',
                        data: {
                            user_id: userId
                        },
                        success: function (response) {
                            console.log(response);
                            if (response.success) {                                                                               
                                const targetSelect = $('#edit_employee_salary_id');                                
                                console.log(targetSelect);                        
                                targetSelect.empty();
                                targetSelect.append('<option value="">Select a Item</option>');
                                $.each(response.data, function(index, item) {
                                    targetSelect.append(
                                        `<option value="${item.id}" ${(employee_salary_id == item.id) ? 'selected' : ''}>${item.year}-${item.month} (${item.net_salary})</option>`
                                    );
                                });
                                if (targetSelect.hasClass('select2-hidden-accessible')) {
                                    targetSelect.trigger('change.select2');
                                }
                            } else{
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
                } else {
                    $('#edit_employee_salary_id').val('').trigger('change');  
                }  
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

        function getEmpSalaries(obj, target_id){
            $.ajax({
                url: $(obj).data('action'),
                method: 'GET',
                data: {
                    user_id: $(obj).val()
                },
                success: function (response) {
                    console.log(response);
                    if (response.success) {                                                            
                        const targetSelect = $(obj).closest('.closest').find(target_id);
                        console.log(targetSelect);                        
                        targetSelect.empty();
                        targetSelect.append('<option value="">Select a Item</option>');
                        $.each(response.data, function(index, item) {
                            targetSelect.append(
                                `<option value="${item.id}">${item.year}-${item.month} (${item.net_salary})</option>`
                            );
                        });
                        if (targetSelect.hasClass('select2-hidden-accessible')) {
                            targetSelect.trigger('change.select2');
                        }
                    } else{
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
        }

        function isValidUrl(value) {
            try {
                new URL(value);
                return true;
            } catch (e) {
                return false;
            }
        }
        
        // Form validation functions
        function validateCreateForm() {
            let isValid = true;
            
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');                        

            if (!$('#create_user_id').val() || ($('#create_user_id').val() == '') || ($('#create_user_id').val() == null)) {
                $('#create_user_msg').removeClass('hidden');                
                isValid = false;
            }            
            if (!$('#create_employee_salary_id').val() || ($('#create_employee_salary_id').val() == '') || ($('#create_employee_salary_id').val() == null)) {
                $('#create_employee_salary_msg').removeClass('hidden');
                isValid = false;
            }            
            if (!$('#create_payslip_number').val().trim()) {
                $('#create_payslip_number').next('.error-message').removeClass('hidden');
                $('#create_payslip_number').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#create_issue_date').val().trim()) {
                $('#create_issue_date').next('.error-message').removeClass('hidden');
                $('#create_issue_date').addClass('border-red-500');
                isValid = false;
            }
            
            // const pdfPath = $('#create_pdf_path').val().trim();
            // if (!pdfPath || !isValidUrl(pdfPath)) {
            //     $('#create_pdf_path').next('.error-message').removeClass('hidden');
            //     $('#create_pdf_path').addClass('border-red-500');
            //     isValid = false;
            // }
            
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            
            if (!$('#edit_user_id').val() || ($('#edit_user_id').val() == '') || ($('#edit_user_id').val() == null)) {
                $('#edit_user_msg').removeClass('hidden');                
                isValid = false;
            }            
            if (!$('#edit_employee_salary_id').val() || ($('#edit_employee_salary_id').val() == '') || ($('#edit_employee_salary_id').val() == null)) {
                $('#edit_employee_salary_msg').removeClass('hidden');
                isValid = false;
            }            
            if (!$('#edit_payslip_number').val().trim()) {
                $('#edit_payslip_number').next('.error-message').removeClass('hidden');
                $('#edit_payslip_number').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#edit_issue_date').val().trim()) {
                $('#edit_issue_date').next('.error-message').removeClass('hidden');
                $('#edit_issue_date').addClass('border-red-500');
                isValid = false;
            }                                                         
            
            // const pdfPath = $('#edit_pdf_path').val().trim();
            // if (!pdfPath || !isValidUrl(pdfPath)) {
            //     $('#edit_pdf_path').next('.error-message').removeClass('hidden');
            //     $('#edit_pdf_path').addClass('border-red-500');
            //     isValid = false;
            // }
                                                    
            
            return isValid;
        }

        // Reset create form
        function resetCreateForm() {
            $('#createForm')[0].reset();
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');
        }

        // Delete confirmation
        function confirmDelete(id, name) {
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
                window.location = '{{ route('role.payslips.index', array_filter(['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'company_id' => request('company_id')])) }}';
            });

            // A row opens that payslip's statement, the way a loan row opens its
            // history. The actions inside the row stop the event so a print or
            // edit click is not also a row click.
            document.querySelectorAll('tr[data-statement]').forEach(function (row) {
                row.addEventListener('click', function () {
                    window.open(this.dataset.statement, '_blank', 'noopener');
                });
            });
        });
    </script>
@endsection