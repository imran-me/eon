@extends('layout.app')
@section('meta-information')
    <title>Manage Employee Salary</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
    <style>
        .modal-container {
            max-height: 95vh;
            /* Prevents modal from being taller than the screen */
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
            /* Adds the scrollbar only to the middle part */
            flex-grow: 1;
        }
    </style>
@endsection
@section('main-content')

    @php
        $role = Str::slug(Auth::user()->getRoleNames()->first());
        $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $period)->format('F Y');

        // Payroll figures show as whole taka, always rounded UP: 797.03 reads as
        // 798. Display only — the stored amounts keep their paise, so payslips,
        // ledgers and the paid/due maths are untouched.
        $taka = fn ($amount) => number_format(ceil((float) $amount));
    @endphp
    <div class="p-4 md:p-6 space-y-6">
        @include('layout.payroll-tabs')


        {{-- ── Header ── --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-xl font-extrabold text-gray-900 leading-tight">
                    <i class="fas fa-file-invoice-dollar text-blue-500 mr-1.5"></i>Employee Salaries
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">Salary sheet for {{ $periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" class="flex items-center gap-2">
                    @foreach(request()->except(['date', 'page']) as $key => $value)
                        @if(!is_array($value) && $value !== null && $value !== '')
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label class="text-sm text-gray-500 font-medium hidden sm:inline">Period:</label>
                    <input type="month" name="date" value="{{ $period }}"
                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit"
                        class="bg-gray-800 hover:bg-gray-900 text-white px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors">
                        Go
                    </button>
                </form>

                @can('create salary template')
                    <button
                        class="create-new-btn inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                @endcan
            </div>
        </div>

        {{-- Company switching moved to the payroll toolbar at the top of the page
             (layout.payroll-tabs), so the chip row that used to sit here would now
             be a second copy of the same control. --}}

        {{-- ── Summary Cards ── --}}
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                    style="background:#e0f2fe;color:#0369a1;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Headcount</p>
                    <p class="text-xl font-extrabold text-gray-900 mt-0.5">{{ number_format($summary['headcount']) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">On this sheet</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                    style="background:#dbeafe;color:#2563eb;">
                    <i class="fas fa-sack-dollar"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Gross</p>
                    <p class="text-xl font-extrabold text-gray-900 mt-0.5">৳ {{ number_format($summary['gross'], 0) }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                    style="background:#ede9fe;color:#7c3aed;">
                    <i class="fas fa-hand-holding-dollar"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Net Payable</p>
                    <p class="text-xl font-extrabold text-gray-900 mt-0.5">৳ {{ number_format($summary['net_payable'], 0) }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                    style="background:#dcfce7;color:#16a34a;">
                    <i class="fas fa-circle-check"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Paid</p>
                    <p class="text-xl font-extrabold text-green-600 mt-0.5">৳ {{ number_format($summary['paid'], 0) }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                    style="background:#fee2e2;color:#dc2626;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Due / Outstanding</p>
                    <p class="text-xl font-extrabold text-red-600 mt-0.5">৳ {{ number_format($summary['due'], 0) }}</p>
                </div>
            </div>
        </div>        

        {{-- ── Salary Sheet Table ── --}}
        
        <div class="states-table bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="states-table-container">
                <div class="px-6 py-4 flex justify-between items-center gap-4 border-b border-gray-100">
                    <h2 class="text-gray-800 text-base font-bold">
                        <i class="fas fa-list mr-2 text-blue-500"></i>Salary Sheet
                        @if($companyId)
                            @php $activeCompany = $companies->firstWhere('id', $companyId); @endphp
                            — <span class="text-blue-600">{{ $activeCompany->short_name ?? $activeCompany->name ?? '' }}</span>
                        @endif
                    </h2>
                    <div class="flex items-center gap-3 shrink-0">
                        <a href="{{ route('role.employee-salaries.paid-due-report', ['role' => $role]) }}"
                            class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                            <i class="fas fa-file-invoice"></i> Paid / Due Report
                        </a>
                        <span class="text-xs text-gray-400">{{ $periodLabel }}</span>
                    </div>
                </div>

                <div class="states-table-content">
                    {{-- Filter bar — the Manage Flights strip. The sheet is one
                         month (the header's picker, hidden date=), so no date
                         range and no template filter here. --}}
                    <form action="{{ route('role.employee-salaries.index', ['role' => $role]) }}" method="get">
                        <input type="hidden" name="date" value="{{ $period }}">
                        @if($companyId)
                            <input type="hidden" name="company_id" value="{{ $companyId }}">
                        @endif
                        <x-payroll-filter-bar placeholder="Search employee name or ID…" :dates="false"
                            :segments="[['name' => 'status', 'label' => 'Status', 'options' => ['Pending' => 'Pending', 'Paid' => 'Paid']]]"
                            :reset="route('role.employee-salaries.index', ['role' => $role])" :keep="['company_id', 'date']"
                            :total="$datas->total()">
                        </x-payroll-filter-bar>
                    </form>

                    <!-- Table with Data -->
                    <div class="table-responsive overflow-x-auto" style="padding: 15px">
                        <table class="table table-hover min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:4%">
                                        #
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:14%">
                                        <i class="fas fa-user mr-1 text-blue-400"></i>Employee
                                    </th>
                                    @if(!$companyId)
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                            <i class="fas fa-building mr-1 text-cyan-500"></i>Company
                                        </th>
                                    @endif
                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                        <i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Gross
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                        <i class="fas fa-minus-circle mr-1 text-red-400"></i>Deductions
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:13%">
                                        <i class="fas fa-plus-circle mr-1 text-emerald-400"></i>Additional
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                        <i class="fas fa-hand-holding-usd mr-1 text-teal-400"></i>Net Salary
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                        <i class="fas fa-calendar-check mr-1 text-cyan-400"></i>Scheduled
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                        <i class="fas fa-book mr-1 text-indigo-400"></i>Paid From
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:8%">
                                        <i class="fas fa-tag mr-1 text-yellow-400"></i>Status
                                    </th>
                                    @canany(['edit salary', 'delete salary', 'view salary', 'edit payment schedule'])
                                        <th class="px-2 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:1%">
                                            <i class="fas fa-cogs mr-1 text-gray-400"></i>Actions
                                        </th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @forelse ($datas as $key => $value)
                                    <tr class="hover:bg-blue-50 transition-colors duration-150">
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs font-bold">
                                                {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($value->user)
                                                <a href="{{ route('role.employee-ledger.show', ['role' => $role, 'user' => $value->user_id]) }}"
                                                    class="flex items-center gap-2 group" title="View {{ $value->user->name }}'s profile">
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold uppercase">
                                                        {{ strtoupper(substr($value->user->name, 0, 2)) }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <span class="block text-sm font-medium text-gray-800 group-hover:text-blue-600 group-hover:underline">{{ $value->user->name }}</span>
                                                        <span class="block text-xs font-mono {{ $value->user->employee_id_no ? 'text-gray-500' : 'text-gray-300' }}">
                                                            {{ $value->user->employee_id_no ?: '—' }}
                                                        </span>
                                                    </div>
                                                </a>
                                            @else
                                                <div class="flex items-center gap-2">
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-xs font-bold uppercase">?</div>
                                                    <span class="text-sm font-medium text-gray-800">—</span>
                                                </div>
                                            @endif
                                        </td>
                                        @if(!$companyId)
                                            @php
                                                $rowCompany = $value->user?->company;
                                                $companyColor = companyBadgeColor($rowCompany?->id);
                                            @endphp
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border"
                                                    style="background:{{ $companyColor['bg'] }};color:{{ $companyColor['text'] }};border-color:{{ $companyColor['border'] }};"
                                                    title="{{ $rowCompany?->name ?? 'No company' }}">
                                                    {{ $rowCompany?->short_name ?? $rowCompany?->name ?? '—' }}
                                                </span>
                                            </td>
                                        @endif
                                        <td class="px-4 py-3 whitespace-nowrap text-right">
                                            <span class="text-sm font-medium text-gray-700">
                                                ৳ {{ $taka($value->gross_salary) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right">
                                            <span class="text-sm font-medium text-red-600">
                                                ৳ {{ $taka($value->total_deductions) }}
                                            </span>
                                        </td>
                                        @php
                                            // Everything paid on top of the template salary — the same three
                                            // parts the create form adds up as "Total Additions".
                                            $overtimePay = (float) ($value->overtime_salary ?? 0);
                                            $bonusPay = (float) ($value->bonus_amount ?? 0);
                                            $adjustmentPay = (float) ($value->salary_adjustment ?? 0);
                                            $additionalPay = $overtimePay + $bonusPay + $adjustmentPay;
                                            $additionalParts = array_filter([
                                                $overtimePay ? 'OT ৳ ' . $taka($overtimePay) : null,
                                                $bonusPay ? ($value->bonus_label ?: 'Bonus') . ' ৳ ' . $taka($bonusPay) : null,
                                                $adjustmentPay ? 'Adj. ৳ ' . $taka($adjustmentPay) : null,
                                            ]);
                                        @endphp
                                        <td class="px-4 py-3 text-right">
                                            @if($additionalPay != 0)
                                                <span class="text-sm font-semibold {{ $additionalPay > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                                    {{ $additionalPay > 0 ? '+' : '−' }} ৳ {{ $taka(abs($additionalPay)) }}
                                                </span>
                                                <p class="text-[11px] text-gray-400 leading-tight mt-0.5">
                                                    {{ implode(' · ', $additionalParts) }}
                                                </p>
                                            @else
                                                <span class="text-sm text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right">
                                            <span class="text-sm font-bold text-teal-700">
                                                ৳ {{ $taka($value->net_salary) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            {{ $value->scheduled_date ? \Carbon\Carbon::parse($value->scheduled_date)->format('d M Y') : '—' }}
                                        </td>
                                        @php $paidFrom = $paidFromAccounts->get($value->id); @endphp
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($paidFrom)
                                                <span class="text-sm font-medium text-gray-700">{{ $paidFrom->account_name }}</span>
                                                <p class="text-[11px] text-gray-400 font-mono leading-tight">{{ $paidFrom->account_code }}</p>
                                            @else
                                                {{-- No journal posting yet: the salary is generated but nothing has left an account. --}}
                                                <span class="text-sm text-gray-300">—</span>
                                            @endif
                                        </td>
                                        @php
                                            $activeSchedule = $value->schedules->firstWhere(fn($s) => in_array($s->status, ['pending', 'overdue', 'approved']));
                                            $paidSoFar = \App\Models\EmployeePaymentAllocation::paidForSalary($value->id);
                                            $due = max(0, (float) $value->net_salary - $paidSoFar);
                                            $isPaid = $due <= 0.01;
                                            $isPartial = !$isPaid && $paidSoFar > 0;
                                        @endphp
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            @if($isPaid)
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                                                    <i class="fas fa-check-circle text-xs"></i> Paid
                                                </span>
                                            @elseif($isPartial)
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">
                                                    <i class="fas fa-hourglass-half text-xs"></i> Partial
                                                </span>
                                                <p class="text-[11px] text-red-600 font-semibold mt-1">Due ৳ {{ $taka($due) }}</p>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700 border border-yellow-200">
                                                    <i class="fas fa-hourglass-half text-xs"></i> Pending
                                                </span>
                                            @endif
                                        </td>
                                        @canany(['edit salary', 'delete salary', 'view salary', 'edit payment schedule'])
                                            <td class="px-2 py-3 whitespace-nowrap text-center">
                                                <div class="grid gap-1 mx-auto" style="grid-template-columns:repeat(2,24px);width:max-content" data-pay-actions>
                                                    @can('view salary')
                                                        <a href="{{ route('role.employee-salaries.show', ['role' => $role, 'employee_salary' => $value->id]) }}"
                                                            class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-green-50 text-green-700 border border-green-200 hover:bg-green-600 hover:text-white transition-colors duration-150"
                                                            title="View Salary Slip">
                                                            <i class="fas fa-file-invoice-dollar" style="font-size:11px"></i>
                                                        </a>
                                                    @endcan
                                                    @can('edit payment schedule')
                                                        @if(!$isPaid && $due > 0.01)
                                                            <button type="button"
                                                                class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-600 hover:text-white transition-colors duration-150 salary-pay-now-btn"
                                                                data-employee_name="{{ $value->user?->name ?? 'Employee' }}"
                                                                data-user_id="{{ $value->user_id }}"
                                                                data-salary_id="{{ $value->id }}"
                                                                data-due_amount="{{ $due }}"
                                                                title="Pay this payslip (schedule independent)">
                                                                <i class="fas fa-money-bill-wave" style="font-size:11px"></i>
                                                            </button>
                                                        @endif
                                                    @endcan
                                                    @can('edit salary')
                                                        @php
                                                            $postedPaymentMethod = optional(optional($value->paymentAllocations->first())->payment)->payment_method;
                                                            $editPaymentMethod = $postedPaymentMethod ?: ($value->payment_method ?: 'cash');
                                                        @endphp
                                                        <button
                                                            class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-yellow-50 text-yellow-600 border border-yellow-200 hover:bg-yellow-500 hover:text-white transition-colors duration-150 edit-item-btn"
                                                            data-item_id="{{ $value->id }}"
                                                            data-user_id="{{ $value->user_id }}"
                                                            data-salary_template_id="{{ $value->salary_template_id }}"
                                                            data-month="{{ $value->month }}"
                                                            data-year="{{ $value->year }}"
                                                            data-gross_salary="{{ $value->gross_salary }}"
                                                            data-total_deductions="{{ $value->total_deductions }}"
                                                            data-net_salary="{{ $value->net_salary }}"
                                                            {{-- The parts Net Salary is made of. Without them the modal
                                                                 could only ever recompute gross − deductions, which is
                                                                 how an edit used to quietly drop the overtime pay. --}}
                                                            data-absent_deduction="{{ $value->absent_deduction }}"
                                                            data-leave_deduction="{{ $value->leave_deduction }}"
                                                            data-late_deduction="{{ $value->late_deduction }}"
                                                            data-early_leave_deduction="{{ $value->early_leave_deduction }}"
                                                            data-loan_deduction="{{ $value->loan_deduction }}"
                                                            data-advance_salary_deduction="{{ $value->advance_salary_deduction }}"
                                                            data-over_time="{{ $value->over_time }}"
                                                            data-over_time_days="{{ $value->over_time_days }}"
                                                            data-overtime_salary="{{ $value->overtime_salary }}"
                                                            data-bonus_amount="{{ $value->bonus_amount }}"
                                                            data-salary_adjustment="{{ $value->salary_adjustment }}"
                                                            data-payment_date="{{ $value->salary_generation_date ? \Carbon\Carbon::parse($value->salary_generation_date)->format('Y-m-d') : date('Y-m-d') }}"
                                                            data-scheduled_date="{{ $value->scheduled_date ? \Carbon\Carbon::parse($value->scheduled_date)->format('Y-m-d') : '' }}"
                                                            data-payment_method="{{ $editPaymentMethod }}"
                                                            data-bank_id="{{ $value->bank_id }}"
                                                            data-status="{{ $value->status }}"
                                                            data-notes="{{ $value->notes }}"
                                                            title="Edit">
                                                            <i class="fas fa-edit" style="font-size:11px"></i>
                                                        </button>
                                                    @endcan
                                                    @can('delete salary')
                                                        @if (strtolower($value->status) != 'paid')
                                                            <button
                                                                class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-red-50 text-red-600 border border-red-200 hover:bg-red-600 hover:text-white transition-colors duration-150"
                                                                onclick="confirmDelete('{{ $value->id }}', 'this item')"
                                                                title="Delete">
                                                                <i class="fas fa-trash" style="font-size:11px"></i>
                                                            </button>
                                                        @endif
                                                    @endcan
                                                </div>
                                            </td>
                                        @endcan
                                    </tr>
                                @empty
                                    @php
                                        // #, Employee, Gross, Deductions, Additional, Net, Scheduled, Paid From, Status
                                        // plus the two columns that only render conditionally.
                                        $emptyColspan = 9
                                            + ($companyId ? 0 : 1)
                                            + (auth()->user()->canAny(['edit salary', 'delete salary', 'view salary']) ? 1 : 0);
                                    @endphp
                                    <tr>
                                        <td colspan="{{ $emptyColspan }}" class="text-center py-12">
                                            <div class="flex flex-col items-center gap-2">
                                                <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                                                    <i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i>
                                                </div>
                                                <h4 class="text-gray-500 text-base font-semibold mt-1">No salary records found</h4>
                                                <p class="text-gray-400 text-sm">No salary sheet generated for {{ $periodLabel }} yet, or try adjusting your filters.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                            @php
                                // Totals come from $companyStats (the whole period) rather than
                                // $datas, which is only the current page of 20 — a footer that
                                // summed the page would change every time you paged through.
                                $footStats = $companyStats
                                    ->filter(fn ($s) => $s['headcount'] > 0)
                                    ->when($companyId, fn ($c) => $c->only([$companyId]));

                                // #, Employee (+ Company when every concern is listed)
                                $footLabelSpan = $companyId ? 2 : 3;
                                // Scheduled, Paid From, Status (+ Actions when the user has any row action)
                                $footTailSpan = 3
                                    + (auth()->user()->canAny(['edit salary', 'delete salary', 'view salary']) ? 1 : 0);
                            @endphp

                            @if($footStats->isNotEmpty())
                                <tfoot class="border-t-2 border-gray-200">
                                    {{-- Per-company rows collapse by default: the same breakdown sits in
                                         the "Payable & Due — Company Wise" card right below, so showing
                                         both at once was the duplication. The Overall row stays put and
                                         doubles as the toggle. --}}
                                    @foreach($footStats as $footCompanyId => $footStat)
                                        @php $footCompany = $companies->firstWhere('id', $footCompanyId); @endphp
                                        <tr class="js-foot-company bg-gray-50 text-[13px] sm:text-sm hidden">
                                            <td colspan="{{ $footLabelSpan }}" class="px-4 py-2.5 text-left font-semibold text-gray-600">
                                                <i class="fas fa-building mr-1.5 text-cyan-500"></i>{{ $footCompany->short_name ?? $footCompany->name ?? '—' }}
                                                <span class="text-gray-400 font-normal text-xs">· {{ $footStat['headcount'] }} staff</span>
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-semibold text-gray-700">৳ {{ $taka($footStat['gross']) }}</td>
                                            <td class="px-4 py-2.5 text-right font-semibold text-red-600">৳ {{ $taka($footStat['deductions']) }}</td>
                                            <td class="px-4 py-2.5 text-right font-semibold text-emerald-600">
                                                {{ $footStat['additions'] > 0 ? '৳ ' . $taka($footStat['additions']) : '—' }}
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-bold text-teal-700">৳ {{ $taka($footStat['net_payable']) }}</td>
                                            <td colspan="{{ $footTailSpan }}" class="px-4 py-2.5 text-left text-gray-500 whitespace-nowrap">
                                                Paid <span class="text-green-600 font-semibold">৳ {{ $taka($footStat['paid']) }}</span>
                                                · Due <span class="text-red-600 font-semibold">৳ {{ $taka($footStat['due']) }}</span>
                                            </td>
                                        </tr>
                                    @endforeach

                                    <tr id="footOverallRow"
                                        class="bg-gray-100 text-sm sm:text-[15px] border-t border-gray-300 cursor-pointer hover:bg-gray-200 transition-colors"
                                        role="button" tabindex="0" aria-expanded="false" aria-controls="footCompanyRows"
                                        title="Show the per-company breakdown">
                                        <td colspan="{{ $footLabelSpan }}" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                                            <i id="footToggleIcon" class="fas fa-chevron-right mr-2 text-blue-500 text-xs transition-transform"></i>
                                            <i class="fas fa-calculator mr-1.5 text-blue-500"></i>Overall — {{ $periodLabel }}
                                            <span class="text-gray-500 font-normal text-xs">· {{ $footStats->sum('headcount') }} staff</span>
                                            <span class="hidden sm:inline text-blue-600 font-semibold text-xs ml-2" id="footToggleHint">
                                                show {{ $footStats->count() }} {{ Str::plural('company', $footStats->count()) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">৳ {{ $taka($footStats->sum('gross')) }}</td>
                                        <td class="px-4 py-3.5 text-right font-extrabold text-red-700">৳ {{ $taka($footStats->sum('deductions')) }}</td>
                                        <td class="px-4 py-3.5 text-right font-extrabold text-emerald-700">
                                            {{ $footStats->sum('additions') > 0 ? '৳ ' . $taka($footStats->sum('additions')) : '—' }}
                                        </td>
                                        <td class="px-4 py-3.5 text-right font-extrabold text-teal-800">৳ {{ $taka($footStats->sum('net_payable')) }}</td>
                                        <td colspan="{{ $footTailSpan }}" class="px-4 py-3.5 text-left text-gray-600 whitespace-nowrap">
                                            Paid <span class="text-green-700 font-bold">৳ {{ $taka($footStats->sum('paid')) }}</span>
                                            · Due <span class="text-red-700 font-bold">৳ {{ $taka($footStats->sum('due')) }}</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="p-3 border-t border-gray-200">
                        {{ $datas->links() }}
                    </div>
                </div>
            </div>
        </div>
        
        {{-- ── Company-wise Payable & Due ── --}}
        @if($companies->count() > 1)
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <button type="button" id="companyWiseToggle"
                    class="w-full px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3 text-left hover:bg-gray-50 transition-colors"
                    aria-expanded="true" aria-controls="companyWiseBody">
                    <span>
                        <span class="block text-sm sm:text-base font-bold text-gray-700">
                            <i class="fas fa-building mr-1.5 text-blue-400"></i>Payable &amp; Due — Company Wise
                        </span>
                        <span class="block text-xs text-gray-400 mt-0.5">{{ $periodLabel }} · click a company to view its salary sheet</span>
                    </span>
                    <i id="companyWiseIcon" class="fas fa-chevron-up text-gray-400 text-sm shrink-0 transition-transform"></i>
                </button>
                <div id="companyWiseBody" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-5 py-2.5 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Company</th>
                                <th class="px-5 py-2.5 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Headcount</th>
                                <th class="px-5 py-2.5 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Gross</th>
                                <th class="px-5 py-2.5 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Net Payable</th>
                                <th class="px-5 py-2.5 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Paid</th>
                                <th class="px-5 py-2.5 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Due</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($companies as $c)
                                @php
                                    $stat = $companyStats->get($c->id, ['headcount' => 0, 'gross' => 0, 'net_payable' => 0, 'paid' => 0, 'due' => 0]);
                                    $rowActive = $companyId == $c->id;
                                    $cColor = companyBadgeColor($c->id);
                                @endphp
                                <tr class="cursor-pointer transition-colors {{ $rowActive ? 'bg-blue-50' : 'hover:bg-gray-50' }}"
                                    onclick="window.location='{{ request()->fullUrlWithQuery(['company_id' => $c->id, 'page' => null]) }}'">
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <span class="inline-block w-2 h-2 rounded-full mr-1.5 align-middle"
                                            style="background:{{ $cColor['dot'] }};"></span>
                                        <span class="text-sm font-semibold text-gray-800">{{ $c->short_name ?? $c->name }}</span>
                                        @if($rowActive)
                                            <i class="fas fa-check-circle text-blue-500 ml-1 text-xs"></i>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-right text-sm text-gray-600">{{ number_format($stat['headcount']) }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap text-right text-sm text-gray-700">৳ {{ number_format($stat['gross'], 0) }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-semibold text-gray-800">৳ {{ number_format($stat['net_payable'], 0) }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap text-right text-sm text-green-600">৳ {{ number_format($stat['paid'], 0) }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-semibold {{ $stat['due'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                                        ৳ {{ number_format($stat['due'], 0) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 font-bold">
                                <td class="px-5 py-3 text-sm text-gray-700">Total</td>
                                <td class="px-5 py-3 text-right text-sm text-gray-700">{{ number_format($companyStats->sum('headcount')) }}</td>
                                <td class="px-5 py-3 text-right text-sm text-gray-700">৳ {{ number_format($companyStats->sum('gross'), 0) }}</td>
                                <td class="px-5 py-3 text-right text-sm text-gray-800">৳ {{ number_format($companyStats->sum('net_payable'), 0) }}</td>
                                <td class="px-5 py-3 text-right text-sm text-green-700">৳ {{ number_format($companyStats->sum('paid'), 0) }}</td>
                                <td class="px-5 py-3 text-right text-sm text-red-700">৳ {{ number_format($companyStats->sum('due'), 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        {{-- ── Payroll History ── --}}
        @if($payrollHistory->count() > 1)
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 flex justify-between items-center gap-4 border-b border-gray-100">
                    <h2 class="text-gray-800 text-base font-bold">
                        <i class="fas fa-clock-rotate-left mr-2 text-blue-500"></i>Payroll History
                    </h2>
                    <p class="text-xs text-gray-400 shrink-0">
                        every payroll month · click one to open its salary sheet
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Month</th>
                                <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Staff Paid</th>
                                <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Gross</th>
                                <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Net Paid</th>
                                <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Still Outstanding</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Run Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($payrollHistory as $historyRow)
                                @php
                                    // Keep the company filter when jumping months, so the history
                                    // drills into the same slice the page is already showing.
                                    $historyUrl = request()->fullUrlWithQuery([
                                        'date' => $historyRow->period,
                                        'page' => null,
                                    ]);
                                @endphp
                                <tr class="hover:bg-blue-50 transition-colors cursor-pointer {{ $historyRow->period === $period ? 'bg-blue-50/60' : '' }}"
                                    onclick="window.location='{{ $historyUrl }}'"
                                    title="Open the {{ $historyRow->label }} salary sheet">
                                    <td class="px-5 py-3 whitespace-nowrap text-sm font-semibold text-gray-800">
                                        {{ $historyRow->label }}
                                        @if($historyRow->period === $period)
                                            <span class="ml-1 text-[10px] font-bold text-blue-600 uppercase">viewing</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-right text-sm text-gray-600">
                                        {{ $historyRow->staff_paid }} / {{ $historyRow->staff_total }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-right text-sm text-gray-700">৳ {{ $taka($historyRow->gross) }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-semibold text-green-600">৳ {{ $taka($historyRow->net_paid) }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap text-right text-sm {{ $historyRow->outstanding > 0 ? 'font-semibold text-red-600' : 'text-gray-300' }}">
                                        {{ $historyRow->outstanding > 0 ? '৳ ' . $taka($historyRow->outstanding) : '—' }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        @if($historyRow->fully_paid)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                                <i class="fas fa-check-circle text-[10px]"></i> Paid
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                                                <i class="fas fa-hourglass-half text-[10px]"></i>
                                                Mixed · {{ $historyRow->runs }} {{ Str::plural('run', $historyRow->runs) }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 border-t-2 border-gray-200">
                                <td class="px-5 py-3 text-left text-sm font-extrabold text-gray-800">
                                    {{ $payrollHistory->count() }} {{ Str::plural('month', $payrollHistory->count()) }}
                                </td>
                                {{-- A sum of payslips, not of people: the same employee appears once per month. --}}
                                <td class="px-5 py-3 text-right text-sm font-bold text-gray-700">
                                    {{ $payrollHistory->sum('staff_paid') }} / {{ $payrollHistory->sum('staff_total') }}
                                </td>
                                <td class="px-5 py-3 text-right text-sm font-extrabold text-gray-800">৳ {{ $taka($payrollHistory->sum('gross')) }}</td>
                                <td class="px-5 py-3 text-right text-sm font-extrabold text-green-700">৳ {{ $taka($payrollHistory->sum('net_paid')) }}</td>
                                <td class="px-5 py-3 text-right text-sm font-extrabold text-red-700">
                                    {{ $payrollHistory->sum('outstanding') > 0 ? '৳ ' . $taka($payrollHistory->sum('outstanding')) : '—' }}
                                </td>
                                <td class="px-5 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

    </div>

    @include('employee-salaries.create-modal')
    @include('employee-salaries.edit-modal')
    @include('employee-salaries.delete-modal')

    {{-- Mark Paid / Partial Payment modal --}}
    <div id="markPaidModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
        <div class="bg-white w-11/12 md:max-w-md mx-auto rounded-lg shadow-lg z-50 overflow-y-auto">
            <div class="py-4 text-left px-6">
                <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-money-bill-wave text-blue-500 mr-1.5"></i>Record Payment</h3>
                    <button type="button" class="modal-close-pay text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
                </div>
                <form id="markPaidForm" method="POST"
                    data-url-template="{{ route('role.payment-schedules.mark-paid', ['role' => $role, 'schedule' => 'REPLACE_ID']) }}"
                    data-direct-url-template="{{ route('role.user.pay-due-amount', ['role' => $role, 'user' => 'REPLACE_USER']) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="employee_salary_id" id="mp_employee_salary_id">
                    <input type="hidden" name="amount" id="mp_direct_amount">
                    <div class="py-4">
                        <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 flex items-center justify-between mb-4">
                            <div>
                                <p class="text-xs text-gray-500 font-semibold uppercase">Employee</p>
                                <p id="mp_employee_name" class="font-bold text-gray-800"></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500 font-semibold uppercase">Amount Due</p>
                                <p id="mp_due_amount" class="font-extrabold text-blue-700 text-lg"></p>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3 cursor-pointer">
                            <input type="checkbox" id="mp_partial_toggle" class="w-4 h-4">
                            Partial payment
                        </label>

                        <div id="mp_partial_fields" class="hidden bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 space-y-2">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Amount Paying Now</label>
                                <input type="number" name="paid_amount" id="mp_paid_amount" step="0.01" min="0.01"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Remainder Due Date</label>
                                <input type="date" name="remainder_date" id="mp_remainder_date"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <p class="text-xs text-amber-700 flex justify-between pt-1 border-t border-amber-200">
                                <span>Remaining after this payment:</span>
                                <span id="mp_remaining_display" class="font-bold">৳ 0</span>
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Payment Date</label>
                                {{-- max: this page renders no $errors block, so a server-side
                                     rejection would bounce back silently — the browser has to
                                     be the one that refuses a future payment date here. --}}
                                <input type="date" name="payment_date" id="mp_payment_date" required
                                    value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Payment Method</label>
                                <select name="payment_method" id="mp_payment_method" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">— Select —</option>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_banking">Mobile Banking</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Bank Account</label>
                                <select name="bank_id" id="mp_bank_id" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">— Select Bank —</option>
                                    @include('partials.bank-options', ['banks' => $banks, 'selected' => null])
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Note (optional)</label>
                                <input type="text" name="note" id="mp_note" placeholder="Reference / cheque no."
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                        <button type="button" class="modal-close-pay px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-check-double mr-1"></i>Confirm Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Whole-taka display, always rounded up — the JS-side twin of the blade
        // $taka() helper. Used only for on-screen figures; the amount actually
        // posted still comes from the untouched input value.
        //
        // Declared here, at page scope, on purpose. It used to live inside the
        // first $(document).ready() block while the create form's attendance
        // handler — a separate <script> block, so a separate scope — called it.
        // That threw ReferenceError on the first breakdown line and killed the
        // rest of the handler, which is where the net salary was recalculated
        // and the "Fetching attendance data..." note was cleared. Three ৳50,000
        // payslips were filed at ৳8,064.60, ৳6,666.58 and ৳6,451.70 because of
        // it between 2026-08-04 and 2026-08-20. Keep it out here.
        function takaWhole(amount) {
            return Math.ceil(parseFloat(amount) || 0).toLocaleString('en-US');
        }
    </script>
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
                setCreateSubmitting(false);
                $('#createProcessingNotice').addClass('hidden');
                $('#createModal').removeClass('hidden');
            });

            // Show/require bank account only when status is Paid and payment method isn't cash
            $('#edit_status, #edit_payment_method').on('change', toggleEditBankField);

            // Show edit modal
            $('.edit-item-btn').click(function() {
                setEditSubmitting(false);
                const item_id = $(this).data('item_id');
                const user_id = $(this).data('user_id');
                const salary_template_id = $(this).data('salary_template_id');
                const month = $(this).data('month');
                const year = $(this).data('year');
                const gross_salary = $(this).data('gross_salary');
                const total_deductions = $(this).data('total_deductions');
                const net_salary = $(this).data('net_salary');
                const payment_date = $(this).data('payment_date');
                const scheduled_date = $(this).data('scheduled_date');
                const payment_method = $(this).data('payment_method');
                const bank_id = $(this).data('bank_id');
                const status = $(this).data('status');
                const notes = $(this).data('notes');

                // Build dynamic action URL
                const urlTemplate = $('#editSubmit').data('url-template');
                const updateUrl = urlTemplate.replace('REPLACE_ID', item_id);
                $('#editSubmit').data('action', updateUrl);

                // The parts Net Salary is built from. Loaded before anything
                // that can trigger a recalculation, so the first recalculation
                // already has the overtime and bonus in hand.
                $('#edit_absent_deduction').val($(this).data('absent_deduction') || 0);
                $('#edit_leave_deduction').val($(this).data('leave_deduction') || 0);
                $('#edit_late_deduction').val($(this).data('late_deduction') || 0);
                $('#edit_early_leave_deduction').val($(this).data('early_leave_deduction') || 0);
                $('#edit_over_time').val($(this).data('over_time') || 0);
                $('#edit_over_time_days').val($(this).data('over_time_days') || 0);
                $('#edit_overtime_salary').val($(this).data('overtime_salary') || 0);
                $('#edit_bonus_amount').val($(this).data('bonus_amount') || 0);
                $('#edit_salary_adjustment').val($(this).data('salary_adjustment') || 0);
                // Reference only — not posted, but a recalculated Total
                // Deductions still has to carry them.
                $('#edit_loan_deduction_ref').val($(this).data('loan_deduction') || 0);
                $('#edit_advance_salary_deduction_ref').val($(this).data('advance_salary_deduction') || 0);

                // A fresh open starts with no recalculation on screen — what is
                // shown is what is stored on the row.
                $('#edit_deduction_breakdown_container, #edit_overtime_salary_container, #edit_attendance_summary_container, #edit_fixed_salary_notice')
                    .addClass('hidden');
                $('#edit_recalculate_status').text('').removeClass('text-red-600 text-green-600').addClass('text-gray-500');

                // Set values in the edit form
                $('#editItemId').val(item_id);
                $('#edit_user_id').val(user_id).trigger('change');
                $('#edit_salary_template_id').val(salary_template_id).trigger('change');
                $('#edit_date').val(`${year}-${String(month).padStart(2, '0')}`);
                $('#edit_gross_salary').val(gross_salary);
                $('#edit_total_deductions').val(total_deductions);
                // Last, and deliberately: the template/gross handlers above
                // recompute this field, and on opening we want the figure the
                // row actually holds, not a fresh one.
                $('#edit_net_salary').val(net_salary);
                $('#edit_payment_date').val(payment_date);
                $('#edit_scheduled_date').val(scheduled_date);
                $('#edit_payment_method').val(payment_method).trigger('change');
                $('#edit_bank_id').val(bank_id || '').trigger('change');
                $('#edit_status').val(status).trigger('change');
                $('#edit_notes').val(notes);
                toggleEditBankField();
                $('#editModal').removeClass('hidden');
            });

            // ── Collapsible company breakdowns ────────────────────────────────
            // Both blocks show the same per-company figures, so each can be folded
            // away independently. The choice is remembered per browser: someone who
            // works company-by-company shouldn't have to re-open it every visit.
            (function () {
                function restore(key, fallback) {
                    try { var v = localStorage.getItem(key); return v === null ? fallback : v === '1'; }
                    catch (e) { return fallback; }
                }
                function remember(key, open) {
                    try { localStorage.setItem(key, open ? '1' : '0'); } catch (e) { /* private mode */ }
                }

                // Salary-sheet footer: per-company rows, collapsed by default.
                var overall = document.getElementById('footOverallRow');
                if (overall) {
                    var rows = document.querySelectorAll('.js-foot-company');
                    var icon = document.getElementById('footToggleIcon');
                    var hint = document.getElementById('footToggleHint');
                    var count = rows.length;

                    var applyFoot = function (open) {
                        rows.forEach(function (r) { r.classList.toggle('hidden', !open); });
                        overall.setAttribute('aria-expanded', open ? 'true' : 'false');
                        if (icon) icon.style.transform = open ? 'rotate(90deg)' : '';
                        if (hint) hint.textContent = (open ? 'hide ' : 'show ') + count + (count === 1 ? ' company' : ' companies');
                        overall.title = (open ? 'Hide' : 'Show') + ' the per-company breakdown';
                    };

                    var footOpen = restore('salarySheetFootOpen', false);
                    applyFoot(footOpen);

                    var toggleFoot = function () { footOpen = !footOpen; applyFoot(footOpen); remember('salarySheetFootOpen', footOpen); };
                    overall.addEventListener('click', toggleFoot);
                    overall.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleFoot(); }
                    });
                }

                // "Payable & Due — Company Wise" card: open by default, it's a section
                // of its own rather than a detail hanging off the sheet.
                var cwToggle = document.getElementById('companyWiseToggle');
                if (cwToggle) {
                    var cwBody = document.getElementById('companyWiseBody');
                    var cwIcon = document.getElementById('companyWiseIcon');

                    var applyCw = function (open) {
                        if (cwBody) cwBody.classList.toggle('hidden', !open);
                        cwToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                        if (cwIcon) { cwIcon.classList.toggle('fa-chevron-up', open); cwIcon.classList.toggle('fa-chevron-down', !open); }
                    };

                    var cwOpen = restore('companyWiseOpen', true);
                    applyCw(cwOpen);

                    cwToggle.addEventListener('click', function () { cwOpen = !cwOpen; applyCw(cwOpen); remember('companyWiseOpen', cwOpen); });
                }
            })();

            // takaWhole() is declared at page scope near the top of this file —
            // every modal script block on this page needs it.

            // Note field doubled as the payment's reference number but was
            // left for the person to type. Pre-fills the same 'SAL-{YmdHis}'
            // shape the bank ledger's Cash In/Out and other modules now
            // preview before saving — still a normal editable field for a
            // real cheque or transfer reference.
            function setSalaryNoteNow() {
                const now = new Date();
                const pad2 = n => String(n).padStart(2, '0');
                const ymdhis = now.getFullYear() + pad2(now.getMonth() + 1) + pad2(now.getDate())
                    + pad2(now.getHours()) + pad2(now.getMinutes()) + pad2(now.getSeconds());
                $('#mp_note').val('SAL-' + ymdhis);
            }

            // Show mark-paid modal
            $('.pay-item-btn').click(function() {
                $('#markPaidForm').data('direct-payment', false).find('input[name="_method"]').prop('disabled', false);
                $('#mp_employee_salary_id, #mp_direct_amount').val('');
                const scheduleId = $(this).data('schedule_id');
                const employeeName = $(this).data('employee_name');
                const dueAmount = parseFloat($(this).data('due_amount')) || 0;

                window._mpDueAmount = dueAmount;
                $('#mp_employee_name').text(employeeName);
                $('#mp_due_amount').text('৳ ' + takaWhole(dueAmount));
                $('#mp_payment_date').val('{{ now()->format('Y-m-d') }}');
                $('#mp_payment_method').val('');
                $('#mp_bank_id').val('');
                setSalaryNoteNow();
                $('#mp_partial_toggle').prop('checked', false);
                $('#mp_partial_fields').addClass('hidden');
                $('#mp_paid_amount').val('').prop('required', false).attr('max', dueAmount);
                $('#mp_remainder_date').val($(this).data('scheduled_date'));
                $('#mp_remaining_display').text('৳ 0');

                const actionUrlTemplate = $('#markPaidForm').data('url-template');
                $('#markPaidForm').attr('action', actionUrlTemplate.replace('REPLACE_ID', scheduleId));

                $('#markPaidModal').removeClass('hidden').addClass('flex');
            });

            $('.salary-pay-now-btn').click(function() {
                const userId = $(this).data('user_id');
                const salaryId = $(this).data('salary_id');
                const dueAmount = parseFloat($(this).data('due_amount')) || 0;
                window._mpDueAmount = dueAmount;
                $('#mp_employee_name').text($(this).data('employee_name'));
                $('#mp_due_amount').text('৳ ' + takaWhole(dueAmount));
                $('#mp_payment_date').val('{{ now()->format('Y-m-d') }}');
                $('#mp_payment_method, #mp_bank_id').val('');
                $('#mp_paid_amount').val('');
                setSalaryNoteNow();
                $('#mp_partial_toggle').prop('checked', false);
                $('#mp_partial_fields').addClass('hidden');
                $('#mp_paid_amount').prop('required', false).attr('max', dueAmount);
                $('#mp_employee_salary_id').val(salaryId);
                $('#mp_direct_amount').val(dueAmount);
                const form = $('#markPaidForm');
                form.data('direct-payment', true);
                form.find('input[name="_method"]').prop('disabled', true);
                form.attr('action', form.data('direct-url-template').replace('REPLACE_USER', userId));
                $('#markPaidModal').removeClass('hidden').addClass('flex');
            });

            $('#mp_partial_toggle').on('change', function() {
                const checked = $(this).is(':checked');
                $('#mp_partial_fields').toggleClass('hidden', !checked);
                $('#mp_paid_amount').prop('required', checked);
                if (checked) {
                    $('#mp_paid_amount').trigger('focus');
                    updateMpRemaining();
                }
            });

            $('#mp_paid_amount').on('input', updateMpRemaining);

            function updateMpRemaining() {
                const paid = parseFloat($('#mp_paid_amount').val()) || 0;
                const remaining = Math.max(0, (window._mpDueAmount || 0) - paid);
                $('#mp_remaining_display').text('৳ ' + takaWhole(remaining));
            }

            $('#markPaidForm').on('submit', function(e) {
                const form = $(this);
                if (!form.data('direct-payment')) return;
                e.preventDefault();
                const amount = $('#mp_partial_toggle').is(':checked')
                    ? (parseFloat($('#mp_paid_amount').val()) || 0)
                    : (window._mpDueAmount || 0);
                $('#mp_direct_amount').val(amount);
                const submit = form.find('button[type="submit"]').prop('disabled', true);
                $.ajax({ url: form.attr('action'), method: 'POST', data: form.serialize() })
                    .done(function(response) {
                        if (response.success) window.location.reload();
                        else { Swal.fire('Payment failed', response.message || 'Something went wrong.', 'error'); submit.prop('disabled', false); }
                    })
                    .fail(function(xhr) {
                        Swal.fire('Payment failed', xhr.responseJSON?.message || 'Something went wrong.', 'error');
                        submit.prop('disabled', false);
                    });
            });

            $('.modal-close-pay, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-pay').length) {
                    $('#markPaidModal').addClass('hidden').removeClass('flex');
                }
            });

            function setCreateSubmitting(isSubmitting) {
                $('#createProcessingNotice').toggleClass('hidden', !isSubmitting);
                $('#createSubmit')
                    .prop('disabled', isSubmitting)
                    .toggleClass('opacity-70 cursor-not-allowed', isSubmitting);
                $('#createSubmitText').toggleClass('hidden', isSubmitting);
                $('#createSubmitLoading').toggleClass('hidden', !isSubmitting);
                $('.modal-close-create')
                    .prop('disabled', isSubmitting)
                    .toggleClass('opacity-60 cursor-not-allowed', isSubmitting);
            }

            // A previous AJAX request can finish after the modal has already
            // closed. Always initialise the create controls in their idle state;
            // the spinner becomes visible only from AJAX beforeSend.
            setCreateSubmitting(false);

            // Close modals
            $('.modal-close-create, .modal-backdrop').click(function(e) {
                if ($('#createSubmit').prop('disabled')) {
                    return;
                }

                if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass(
                        'modal-backdrop')) {
                    $('#createModal').addClass('hidden');
                }
            });

            $('.modal-close-edit, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit')
                    .length) {
                    $('#editModal').addClass('hidden');
                }
            });

            $('.modal-close-delete, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete')
                    .length) {
                    $('#deleteModal').addClass('hidden');
                }
            });

            // Close success alert
            $('.close-btn').click(function() {
                $(this).closest('.alert').addClass('hidden');
            });

            // Create salary form submission
            $('#createSubmit').click(function(e) {
                e.preventDefault();

                if ($(this).prop('disabled')) {
                    return;
                }

                console.log(validateCreateForm());
                if (validateCreateForm()) {
                    let formData = new FormData($('#createForm')[0]);
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        beforeSend: function() {
                            setCreateSubmitting(true);
                        },
                        success: function(response) {
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
                        error: function(xhr) {
                            console.error(xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Failed to create data.'
                            });
                        },
                        complete: function() {
                            setCreateSubmitting(false);
                        }
                    });
                }
            });

            function setEditSubmitting(isSubmitting) {
                $('#editSubmit').prop('disabled', isSubmitting)
                    .toggleClass('opacity-60 cursor-not-allowed', isSubmitting);
                $('#editSubmitIdle').toggleClass('hidden', isSubmitting);
                $('#editSubmitLoading').toggleClass('hidden', !isSubmitting)
                    .toggleClass('inline-flex', isSubmitting);
            }

            setEditSubmitting(false);

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
                        beforeSend: function() {
                            setEditSubmitting(true);
                        },
                        success: function(response) {
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
                        error: function(xhr) {
                            console.error('❌ Error:', xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Something went wrong!'
                            });
                        },
                        complete: function() {
                            setEditSubmitting(false);
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
                    success: function(response) {
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
                    error: function(xhr) {
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
            if (!$('#create_payment_method').val() || ($('#create_payment_method').val() == '') || ($(
                    '#create_payment_method').val() == null)) {
                $('#create_payment_method_msg').removeClass('hidden');
                isValid = false;
            }
            if (!$('#create_status').val() || ($('#create_status').val() == '') || ($(
                    '#create_status').val() == null)) {
                $('#create_status_msg').removeClass('hidden');
                isValid = false;
            }
            if (!$('#create_payment_date').val().trim()) {
                $('#create_payment_date').next('.error-message').removeClass('hidden');
                $('#create_payment_date').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#create_scheduled_date').val().trim()) {
                $('#create_scheduled_date').next('.error-message').removeClass('hidden');
                $('#create_scheduled_date').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#create_date').val().trim()) {
                $('#create_date').next('.error-message').removeClass('hidden');
                $('#create_date').addClass('border-red-500');
                isValid = false;
            }

            return isValid;
        }

        // Show/hide + require the Bank Account field based on the selected
        // status and payment method — cash payments don't need a bank.
        function toggleEditBankField() {
            const isPaid = $('#edit_status').val() === 'Paid';
            const isCash = $('#edit_payment_method').val() === 'cash';
            $('#edit_bank_id_wrapper').toggle(isPaid && !isCash);
            if (!isPaid || isCash) {
                $('#edit_bank_id_msg').addClass('hidden');
            }
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
            if (!$('#edit_salary_template_id').val() || ($('#edit_salary_template_id').val() == '') || ($(
                    '#edit_salary_template_id').val() == null)) {
                $('#edit_salary_template_msg').removeClass('hidden');
                isValid = false;
            }
            if (!$('#edit_payment_method').val() || ($('#edit_payment_method').val() == '') || ($(
                    '#edit_payment_method').val() == null)) {
                $('#edit_payment_method_msg').removeClass('hidden');
                isValid = false;
            }
            if (!$('#edit_status').val() || ($('#edit_status').val() == '') || ($(
                    '#edit_status').val() == null)) {
                $('#edit_status_msg').removeClass('hidden');
                isValid = false;
            }
            if ($('#edit_status').val() === 'Paid' && $('#edit_payment_method').val() !== 'cash' && !$('#edit_bank_id').val()) {
                $('#edit_bank_id_msg').removeClass('hidden');
                isValid = false;
            }
            if (!$('#edit_payment_date').val().trim()) {
                $('#edit_payment_date').next('.error-message').removeClass('hidden');
                $('#edit_payment_date').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#edit_date').val().trim()) {
                $('#edit_date').next('.error-message').removeClass('hidden');
                $('#edit_date').addClass('border-red-500');
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
        function confirmDelete(id, name = null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }
    </script>
    <script>
        $(document).ready(function() {

            const num = (selector) => parseFloat($(selector).val()) || 0;

            // Same expression the server derives Net Salary from, so what the
            // modal shows is what gets saved. The old version was
            // gross − deductions, which silently threw away overtime pay,
            // bonus and adjustment every time a payslip was edited.
            function recalculateEditSalary() {
                const netSalary = Math.ceil(num('#edit_gross_salary')
                    + num('#edit_overtime_salary')
                    + num('#edit_bonus_amount')
                    + num('#edit_salary_adjustment')
                    - num('#edit_total_deductions'));

                $('#edit_net_salary').val(netSalary.toFixed(0));
            }

            // When salary template changes - EDIT
            $('#edit_salary_template_id').on('change', function() {
                let salary = $(this).find(':selected').data('salary');
                if (salary) {
                    $('#edit_gross_salary').val(salary);
                    recalculateEditSalary();
                }
            });

            $('#edit_gross_salary, #edit_total_deductions').on('input', recalculateEditSalary);

            // ── Recalculate from Attendance ───────────────────────────────
            // Fixing an absent day to present in the Attendance module changed
            // nothing on an already-generated payslip: the modal kept the
            // deductions worked out the day it was created, and there was no
            // way to pull them again short of deleting the payslip. This asks
            // the same endpoint the create form uses, for the employee and
            // month currently selected.
            $('#edit_recalculate_btn').on('click', function() {
                const userId = $('#edit_user_id').val();
                const dateVal = $('#edit_date').val(); // YYYY-MM
                const $status = $('#edit_recalculate_status');
                const $btn = $(this);

                if (!userId || !dateVal) {
                    $status.removeClass('text-gray-500 text-green-600').addClass('text-red-600')
                        .text('Select an employee and a salary month first.');
                    return;
                }

                $btn.prop('disabled', true).addClass('opacity-60');
                $status.removeClass('text-red-600 text-green-600').addClass('text-gray-500')
                    .text('Fetching attendance…');

                $.ajax({
                    url: "{{ route('role.employee-salaries.attendance', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}",
                    method: 'GET',
                    data: { user_id: userId, month: dateVal },
                    success: function(data) {
                        const fmt = (val) => takaWhole(val);

                        const absent = parseFloat(data.absent_deduction) || 0;
                        const leave = parseFloat(data.leave_deduction) || 0;
                        const late = parseFloat(data.late_deduction) || 0;
                        const earlyOut = parseFloat(data.early_out_deduction) || 0;

                        // Loan and advance instalments are carried over from the
                        // row rather than taken from the response. Rewriting them
                        // here would leave the loan register holding a repayment
                        // this payslip no longer takes — a separate ledger this
                        // screen has no business touching.
                        const loan = num('#edit_loan_deduction_ref');
                        const advance = num('#edit_advance_salary_deduction_ref');

                        $('#edit_absent_deduction').val(absent);
                        $('#edit_leave_deduction').val(leave);
                        $('#edit_late_deduction').val(late);
                        $('#edit_early_leave_deduction').val(earlyOut);
                        $('#edit_over_time').val(data.summary?.overtime_minutes || 0);
                        $('#edit_over_time_days').val(data.summary?.overtime_days || 0);
                        $('#edit_overtime_salary').val(parseFloat(data.overtime_salary) || 0);

                        $('#edit_total_deductions').val((absent + leave + late + earlyOut + loan + advance).toFixed(2));
                        recalculateEditSalary();

                        $('#edit_breakdown_absent').text(fmt(absent));
                        $('#edit_breakdown_leave').text(fmt(leave));
                        $('#edit_breakdown_late').text(fmt(late));
                        $('#edit_breakdown_early_out').text(fmt(earlyOut));
                        $('#edit_breakdown_advance').text(fmt(advance));
                        $('#edit_breakdown_loan').text(fmt(loan));
                        $('#edit_deduction_breakdown_container').removeClass('hidden');
                        $('#edit_fixed_salary_notice').toggleClass('hidden', !data.fixed_salary);

                        const overtimeSalary = parseFloat(data.overtime_salary) || 0;
                        if (overtimeSalary > 0 || (data.summary?.overtime_minutes || 0) > 0) {
                            $('#edit_breakdown_overtime_minutes').text(data.summary?.overtime_minutes || 0);
                            $('#edit_breakdown_overtime_days').text(data.summary?.overtime_days || 0);
                            $('#edit_breakdown_overtime_salary').text(
                                data.overtime_eligible ? '৳ ' + fmt(overtimeSalary) : 'Not eligible for overtime pay'
                            );
                            $('#edit_overtime_salary_container').removeClass('hidden');
                        } else {
                            $('#edit_overtime_salary_container').addClass('hidden');
                        }

                        if (data.summary) {
                            $('#edit_sum_total_days').text(data.summary.total_days);
                            $('#edit_sum_present').text(data.summary.present);
                            $('#edit_sum_absent').text(data.summary.absent);
                            $('#edit_sum_leave').text(data.summary.leave);
                            $('#edit_sum_late').text(`${data.summary.late} (${data.summary.late_minutes}m)`);
                            $('#edit_sum_early_out').text(`${data.summary.early_out} (${data.summary.early_minutes}m)`);
                            $('#edit_sum_holiday').text(data.summary.holiday);
                            $('#edit_sum_overtime').text(`${data.summary.overtime_days} (${data.summary.overtime_minutes} mins)`);
                            $('#edit_attendance_summary_container').removeClass('hidden');
                        }

                        $status.removeClass('text-gray-500 text-red-600').addClass('text-green-600')
                            .text('Recalculated. Review the figures, then press Update to save.');
                    },
                    error: function() {
                        $status.removeClass('text-gray-500 text-green-600').addClass('text-red-600')
                            .text('Could not load attendance. Nothing was changed.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).removeClass('opacity-60');
                    }
                });
            });
            // ── END Recalculate from Attendance ───────────────────────────

        });
    </script>
    <script>
        $(document).ready(function() {
            // Helper to update Net Salary automatically
            function recalculateSalary() {
                    const gross = parseFloat($('#create_gross_salary').val()) || 0;
                    const deductions = parseFloat($('#create_total_deductions').val()) || 0;
                    const bonus = parseFloat($('#create_bonus_amount').val()) || 0;
                    const salaryAdjustment = parseFloat($('#create_salary_adjustment').val()) || 0;
                    const overtimeSalary = parseFloat($('#hidden_overtime_salary').val()) || 0;

                    const totalAdditions = overtimeSalary + bonus + salaryAdjustment;
                    $('#create_total_additions').val(totalAdditions.toFixed(2));

                    // Formula: Gross + Total Additions - Total Deductions
                    const netSalary = Math.ceil(gross + totalAdditions - deductions);
                    $('#create_net_salary').val(netSalary.toFixed(0));
                }

            $('#create_gross_salary, #create_total_deductions, #create_bonus_amount, #create_salary_adjustment').on(
                'input', recalculateSalary);

            $('#create_user_id, #create_date').on('change', function() {
                $('#deduction_breakdown_container, #attendance_summary_container, #overtime_salary_container, #fixed_salary_notice').addClass('hidden');
                $('#breakdown_absent, #breakdown_leave, #breakdown_late, #breakdown_advance, #breakdown_loan').text('0');
                $('#hidden_overtime_salary').val(0);

                const userId = $('#create_user_id').val();
                const dateVal = $('#create_date').val(); // Format: YYYY-MM
                const selectedUser = $('#create_user_id option:selected');
                if (selectedUser.data('salary')) {
                    $('#create_gross_salary').val(selectedUser.data('salary')).trigger('input');
                }

                if (userId && dateVal) {
                    // Split YYYY-MM for your database 'month' and 'year' columns
                    const dateParts = dateVal.split('-');
                    $('#hidden_year').val(dateParts[0]);
                    $('#hidden_month').val(dateParts[1]);

                    const attendanceUrl =
                        "{{ route('role.employee-salaries.attendance', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";

                    $.ajax({
                        url: attendanceUrl,
                        method: "GET",
                        data: {
                            user_id: userId,
                            month: dateVal
                        },
                        beforeSend: function() {
                            $('#create_notes').val('Fetching attendance data...');
                        },
                        success: function(data) {
                            // 1. Map IDs to hidden fields
                            $('#hidden_loan_id').val(data.loan_id || '');
                            $('#hidden_advance_salary_id').val(data.advance_salary_id || '');

                            // 2. Map individual deductions to hidden fields for saving
                            $('#hidden_absent_deduction').val(data.absent_deduction || 0);
                            $('#hidden_leave_deduction').val(data.leave_deduction || 0);
                            $('#hidden_late_deduction').val(data.late_deduction || 0);
                            $('#hidden_early_leave_deduction').val(data.early_out_deduction ||
                                0);
                            $('#hidden_advance_salary_deduction').val(data.advance_salary || 0);
                            $('#hidden_loan_deduction').val(data.loan_deduction || 0);
                            $('#hidden_salary_adjustment').val(data.salary_adjustment || 0);
                            $('#hidden_over_time').val(data.summary.overtime_minutes || 0);
                            $('#hidden_over_time_days').val(data.summary.overtime_days || 0);
                            $('#hidden_overtime_salary').val(data.overtime_salary || 0);

                            // 3. Set Total Deductions
                            $('#create_total_deductions').val(data.total_deductions);

                            // 4. Recalculate net salary, and clear the status
                            //    message parked in the Note box.
                            //
                            //    Both of these deliberately run before a single
                            //    line of display code. They used to sit at the
                            //    bottom, after the breakdown boxes were painted,
                            //    so when one cosmetic line threw the handler died
                            //    with the net salary still holding the previous
                            //    employee's figure and the placeholder note still
                            //    in the box — and the form saved exactly that.
                            //    Nothing below may be allowed to decide what gets
                            //    stored.
                            recalculateSalary();
                            $('#create_notes').val('');

                            // Display only — #create_total_deductions above still receives the raw
                            // value, so what gets saved is unaffected by this rounding.
                            const fmt = (val) => takaWhole(val);
                            $('#breakdown_absent').text(fmt(data.absent_deduction));
                            $('#breakdown_leave').text(fmt(data.leave_deduction));
                            $('#breakdown_late').text(fmt(data.late_deduction));
                            $('#breakdown_early_out').text(fmt(data.early_out_deduction));
                            $('#breakdown_advance').text(fmt(data.advance_salary));
                            $('#breakdown_loan').text(fmt(data.loan_deduction));

                            $('#deduction_breakdown_container').removeClass('hidden');
                            $('#fixed_salary_notice').toggleClass('hidden', !data.fixed_salary);

                            // 5. Show overtime breakdown
                            const overtimeSalary = parseFloat(data.overtime_salary || 0);
                            if (overtimeSalary > 0) {
                                $('#breakdown_overtime_minutes').text(data.summary.overtime_minutes || 0);
                                $('#breakdown_overtime_days').text(data.summary.overtime_days || 0);
                                $('#breakdown_overtime_salary').text('৳ ' + fmt(overtimeSalary));
                                $('#overtime_salary_container').removeClass('hidden');
                            } else if (!data.overtime_eligible && data.summary.overtime_minutes > 0) {
                                $('#breakdown_overtime_minutes').text(data.summary.overtime_minutes || 0);
                                $('#breakdown_overtime_days').text(data.summary.overtime_days || 0);
                                $('#breakdown_overtime_salary').text('Not eligible for overtime pay');
                                $('#overtime_salary_container').removeClass('hidden');
                            } else {
                                $('#overtime_salary_container').addClass('hidden');
                            }

                            // 6. Attendance Summary
                            if (data.summary) {
                                $('#attendance_summary_container').removeClass('hidden');
                                $('#sum_total_days').text(data.summary.total_days);
                                $('#sum_present').text(data.summary.present);
                                $('#sum_absent').text(data.summary.absent);
                                $('#sum_leave').text(data.summary.leave);
                                $('#sum_late').text(
                                    `${data.summary.late} (${data.summary.late_minutes}m)`);
                                $('#sum_holiday').text(data.summary.holiday);
                                $('#sum_early_out').text(
                                    `${data.summary.early_out} (${data.summary.early_minutes}m)`
                                    );
                                $('#sum_overtime').text(
                                    `${data.summary.overtime_days} (${data.summary.overtime_minutes} mins)`
                                    );
                            }
                        },
                        error: function() {
                            $('#create_notes').val('Error loading data.');
                        }
                    });
                }
            });
        });
    </script>
@endsection
