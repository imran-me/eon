@extends('layout.app')

@section('meta-information')
    <title>Employee Profile Summary</title>
@endsection

@section('css')
    <style>
        .summary-shell {
            background: radial-gradient(circle at top right, rgba(14, 165, 233, 0.08), transparent 45%),
                radial-gradient(circle at top left, rgba(59, 130, 246, 0.08), transparent 40%);
        }

        .summary-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 6px 20px rgba(2, 6, 23, 0.04);
        }

        .summary-kpi {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .summary-label {
            font-size: 11px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
        }

        .profile-tab-btn {
            border-bottom: 2px solid transparent;
            color: #64748b;
        }

        .profile-tab-btn.active {
            border-bottom-color: #2563eb;
            color: #1d4ed8;
        }

        .profile-tab-panel.hidden {
            display: none;
        }
    </style>
@endsection

@section('main-content')

@php
    // Who is looking, and what may they do here.
    //
    // UserController::authorizeEmployeeAccess() has already decided the viewer
    // is ALLOWED on this page. These two decide what the page offers them.
    //
    //   $isSelf  — an employee reading their own record. They see their own
    //              money (that is the point of the page) but get no controls.
    //   $canAct  — may record money against SOMEONE ELSE. Never true for your
    //              own record, so no one grants themselves a bonus, an advance
    //              or a loan; administrators included. Each button is then
    //              gated again on the permission its own endpoint enforces.
    $isSelf = auth()->id() === $employee->id;
    $canAct = ! $isSelf && auth()->user()?->can('view all salary');
@endphp
    <div class="summary-shell rounded-2xl p-3 md:p-5">
        <div class="max-w-7xl mx-auto space-y-5">
            <div class="p-6 md:p-7 rounded-2xl bg-gradient-to-r from-slate-900 via-blue-900 to-slate-900 text-white shadow-lg border border-slate-800/40">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-5">
                    <div class="flex items-center gap-4">
                    @php
                        $avatar = $employee->image ? asset($employee->image) : null;
                    @endphp
                    @if($avatar)
                            <img src="{{ $avatar }}" alt="{{ $employee->name }}"
                                class="w-16 h-16 rounded-full object-cover border-2 border-white/40 shadow-lg">
                    @else
                            <div class="w-16 h-16 rounded-full bg-white/20 text-white font-bold text-2xl flex items-center justify-center border border-white/50 shadow-lg">
                                {{ strtoupper(substr($employee->name, 0, 1)) }}
                            </div>
                    @endif
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.08em] font-bold text-blue-200">Employee Overview</p>
                            <h1 class="text-2xl md:text-3xl font-extrabold mt-1">{{ $employee->name }}</h1>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <span class="text-sm text-slate-200">{{ $employee->profile->department->name ?? 'Unassigned' }} &middot; {{ $employee->company->name ?? '-' }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/15 border border-white/25 text-white">
                                    {{ \Illuminate\Support\Str::headline($employee->profile->employment_type ?? '-') }}
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $employee->status === 'active' ? 'bg-emerald-500/20 text-emerald-200 border border-emerald-400/30' : 'bg-slate-500/20 text-slate-300 border border-slate-400/30' }}">
                                    {{ \Illuminate\Support\Str::headline($employee->status ?? '-') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" data-jump-tab="payslips"
                            class="inline-flex items-center px-4 py-2 rounded-lg bg-white/15 border border-white/30 text-white hover:bg-white/25 transition text-sm font-medium">
                            <i class="fas fa-file-invoice-dollar mr-2"></i> Payslip
                        </button>
                        <a href="{{ route('role.user.index', ['role' => request()->route('role')]) }}"
                            class="inline-flex items-center px-4 py-2 rounded-lg bg-white/15 border border-white/30 text-white hover:bg-white/25 transition text-sm font-medium">
                            <i class="fas fa-arrow-left mr-2"></i> Back to User List
                        </a>
                    </div>
                </div>
            </div>

            <div class="summary-card p-5 md:p-6">
                <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                    <div class="summary-kpi p-4">
                        <p class="summary-label">Salary</p>
                        <p class="text-xl font-extrabold text-slate-800 mt-1">৳ {{ number_format((float) ($employee->profile->salary ?? 0), 0) }}</p>
                    </div>
                    <div class="summary-kpi p-4">
                        <p class="summary-label">Company Owes</p>
                        <p class="text-xl font-extrabold mt-1 {{ $ledgerBalance > 0 ? 'text-red-600' : ($ledgerBalance < 0 ? 'text-amber-600' : 'text-slate-800') }}">৳ {{ number_format(abs($ledgerBalance), 0) }}</p>
                    </div>
                    <div class="summary-kpi p-4">
                        <p class="summary-label">Salary Due</p>
                        <p class="text-xl font-extrabold text-amber-600 mt-1">৳ {{ number_format($salaryDue, 0) }}</p>
                    </div>
                    <div class="summary-kpi p-4">
                        <p class="summary-label">Advance Out</p>
                        <p class="text-xl font-extrabold text-purple-600 mt-1">৳ {{ number_format($advanceOut, 0) }}</p>
                    </div>
                    <div class="summary-kpi p-4">
                        <p class="summary-label">Loan Out</p>
                        <p class="text-xl font-extrabold text-red-600 mt-1">৳ {{ number_format((float) ($loanStats->total_remaining ?? 0), 0) }}</p>
                    </div>
                    <div class="p-4 rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50">
                        <p class="summary-label text-blue-600">Leave Encash</p>
                        @if($pendingReconciliation)
                            <p class="text-xl font-extrabold text-blue-700 mt-1">{{ number_format($pendingReconciliation['leave_days_taken'], 1) }}d &middot; ৳ {{ number_format($pendingReconciliation['accrued_leave_deduction'], 0) }}</p>
                            <p class="text-[11px] text-blue-500 mt-1">Year {{ $pendingReconciliation['service_year_in_progress'] }} in progress &middot; next {{ $pendingReconciliation['next_anniversary_date']->format('M d, Y') }}</p>
                        @else
                            <p class="text-xl font-extrabold text-blue-700 mt-1">&mdash;</p>
                            <p class="text-[11px] text-blue-500 mt-1">No joining date on file</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-6 border-b border-slate-200 px-1">
                <button type="button" data-profile-tab="overview" class="profile-tab-btn active px-1 pb-3 text-sm font-semibold transition-colors">
                    Overview
                </button>
                <button type="button" data-profile-tab="accounts" class="profile-tab-btn px-1 pb-3 text-sm font-semibold transition-colors">
                    Accounts
                    @if($ledgerBalance != 0)
                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $ledgerBalance > 0 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                            ৳ {{ number_format(abs($ledgerBalance), 0) }}
                        </span>
                    @endif
                </button>
                <button type="button" data-profile-tab="payslips" class="profile-tab-btn px-1 pb-3 text-sm font-semibold transition-colors">
                    Payslips
                </button>
                <button type="button" data-profile-tab="attendance" class="profile-tab-btn px-1 pb-3 text-sm font-semibold transition-colors">
                    Attendance
                </button>
                <button type="button" data-profile-tab="all-details" class="profile-tab-btn px-1 pb-3 text-sm font-semibold transition-colors">
                    All Details
                </button>
            </div>

            <div data-profile-panel="overview" class="profile-tab-panel space-y-5">

            <div class="summary-card p-5 md:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-slate-800">Profile Details</h2>
                    <span class="summary-label">Current</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                    <div class="summary-kpi p-3"><p class="text-slate-500">Email</p><p class="font-semibold text-slate-800 mt-1 break-words">{{ $employee->email ?? '-' }}</p></div>
                    <div class="summary-kpi p-3"><p class="text-slate-500">Phone</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->phone ?? '-' }}</p></div>
                    <div class="summary-kpi p-3"><p class="text-slate-500">Company</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->company->name ?? '-' }}</p></div>
                    <div class="summary-kpi p-3"><p class="text-slate-500">Department</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->profile->department->name ?? '-' }}</p></div>
                    <div class="summary-kpi p-3"><p class="text-slate-500">Designation</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->profile->designation->name ?? '-' }}</p></div>
                    <div class="summary-kpi p-3"><p class="text-slate-500">Employment Type</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->profile->employment_type ?? '-' }}</p></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="summary-card p-5">
                    <p class="summary-label">Task Total</p>
                    <p class="text-3xl font-extrabold text-indigo-600 mt-2">{{ $taskTotal }}</p>
                    <p class="text-xs text-slate-500 mt-1">Assigned in workspace</p>
                </div>
                <div class="summary-card p-5">
                    <p class="summary-label">Completed</p>
                    <p class="text-3xl font-extrabold text-emerald-600 mt-2">{{ $taskCompleted }}</p>
                    <p class="text-xs text-slate-500 mt-1">Moved to done columns</p>
                </div>
                <div class="summary-card p-5">
                    <p class="summary-label">Pending</p>
                    <p class="text-3xl font-extrabold text-amber-600 mt-2">{{ $taskPending }}</p>
                    <p class="text-xs text-slate-500 mt-1">Remaining active tasks</p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                <div class="summary-card p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold text-slate-800">Weekly Task Performance</h2>
                        <span class="summary-label">This Week</span>
                    </div>
                    <div class="h-[280px]">
                        <canvas id="weeklyTaskPerformanceChart"></canvas>
                    </div>
                </div>

                <div class="summary-card p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold text-slate-800">Monthly Task Performance</h2>
                        <span class="summary-label">{{ $currentYear }}</span>
                    </div>
                    <div class="h-[280px]">
                        <canvas id="monthlyTaskPerformanceChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="summary-card p-6">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Attendance Summary ({{ \Carbon\Carbon::create()->month($currentMonth)->format('F') }} {{ $currentYear }})</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                    <div class="summary-kpi p-4">
                        <p class="text-sm text-slate-500">Present Days ({{ \Carbon\Carbon::create()->month($currentMonth)->format('F') }} {{ $currentYear }})</p>
                        <p class="text-2xl font-extrabold text-green-600 mt-1">{{ array_sum($attendanceMonthPresentData ?? []) }}</p>
                    </div>
                    <div class="summary-kpi p-4">
                        <p class="text-sm text-slate-500">Late Time ({{ \Carbon\Carbon::create()->month($currentMonth)->format('F') }} {{ $currentYear }})</p>
                        <p class="text-2xl font-extrabold text-orange-600 mt-1">{{ number_format((float) ($attendanceMonthTotalLateMinutes ?? 0), 2) }} min</p>
                    </div>
                    <div class="summary-kpi p-4">
                        <p class="text-sm text-slate-500">Working Hour ({{ \Carbon\Carbon::create()->month($currentMonth)->format('F') }} {{ $currentYear }})</p>
                        <p class="text-2xl font-extrabold text-blue-600 mt-1">{{ number_format((float) ($attendanceMonthTotalWorkingHours ?? 0), 2) }} hr</p>
                    </div>
                </div>

                <div class="summary-kpi p-4 mt-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-semibold text-slate-700">Late Time & Working Hour ({{ \Carbon\Carbon::create()->month($currentMonth)->format('F') }} {{ $currentYear }})</p>
                        <span class="text-xs text-slate-500">Daily</span>
                    </div>
                    <div class="h-[240px]">
                        <canvas id="attendanceMonthChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="summary-card p-6">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Leave Summary ({{ $currentYear }})</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="summary-kpi p-4"><p class="text-sm text-slate-500">Approved</p><p class="text-2xl font-extrabold text-green-600 mt-1">{{ (int) ($leaveSummary->approved_count ?? 0) }}</p></div>
                    <div class="summary-kpi p-4"><p class="text-sm text-slate-500">Pending</p><p class="text-2xl font-extrabold text-yellow-600 mt-1">{{ (int) ($leaveSummary->pending_count ?? 0) }}</p></div>
                    <div class="summary-kpi p-4"><p class="text-sm text-slate-500">Rejected</p><p class="text-2xl font-extrabold text-red-600 mt-1">{{ (int) ($leaveSummary->rejected_count ?? 0) }}</p></div>
                    <div class="summary-kpi p-4"><p class="text-sm text-slate-500">Used Leave Days</p><p class="text-2xl font-extrabold text-indigo-600 mt-1">{{ (int) $leaveDaysUsed }}</p></div>
                </div>
            </div>

            <div class="summary-card p-6">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Salary & Loan Summary</h2>
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <div class="summary-kpi p-4 space-y-2">
                        <h3 class="text-sm font-semibold text-slate-700">Salary</h3>
                        <p class="text-sm text-slate-600">Paid Records ({{ $currentYear }}): <span class="font-semibold text-green-600">{{ (int) ($salaryStats->paid_count ?? 0) }}</span></p>
                        <p class="text-sm text-slate-600">Pending Records ({{ $currentYear }}): <span class="font-semibold text-amber-600">{{ (int) ($salaryStats->pending_count ?? 0) }}</span></p>
                        <p class="text-sm text-slate-600">Total Net Salary ({{ $currentYear }}): <span class="font-semibold text-indigo-600">{{ number_format((float) ($salaryStats->total_net_salary ?? 0), 2) }}</span></p>
                        <p class="text-sm text-slate-600">Latest Net Salary: <span class="font-semibold text-slate-800">{{ number_format((float) ($latestSalary->net_salary ?? 0), 2) }}</span></p>
                    </div>
                    <div class="summary-kpi p-4 space-y-2">
                        <h3 class="text-sm font-semibold text-slate-700">Loan & Advance Salary</h3>
                        <p class="text-sm text-slate-600">Running Loans: <span class="font-semibold text-red-600">{{ (int) ($loanStats->running_count ?? 0) }}</span></p>
                        <p class="text-sm text-slate-600">Completed Loans: <span class="font-semibold text-green-600">{{ (int) ($loanStats->completed_count ?? 0) }}</span></p>
                        <p class="text-sm text-slate-600">Loan Remaining Amount: <span class="font-semibold text-indigo-600">{{ number_format((float) ($loanStats->total_remaining ?? 0), 2) }}</span></p>
                        <p class="text-sm text-slate-600">Pending Advance Salary: <span class="font-semibold text-amber-600">{{ number_format((float) ($advanceSalaryStats->pending_amount ?? 0), 2) }}</span></p>
                        <p class="text-sm text-slate-600">Approved Advance Salary: <span class="font-semibold text-green-600">{{ number_format((float) ($advanceSalaryStats->approved_amount ?? 0), 2) }}</span></p>
                    </div>
                </div>
            </div>

            </div>{{-- /overview panel --}}

            <div data-profile-panel="accounts" class="profile-tab-panel hidden space-y-5">
                <div class="summary-card p-5 md:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-1">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">Accounts — full transaction history</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Every salary earned and paid event for {{ $employee->name }}, running balance included</p>
                        </div>
                        <div class="text-right">
                            <p class="summary-label">{{ $ledgerBalance >= 0 ? 'Company Owes' : 'Overpaid' }}</p>
                            <p class="text-2xl font-extrabold mt-0.5 {{ $ledgerBalance > 0 ? 'text-red-600' : ($ledgerBalance < 0 ? 'text-amber-600' : 'text-slate-800') }}">
                                ৳ {{ number_format(abs($ledgerBalance), 2) }}
                            </p>
                        </div>
                    </div>
                    {{-- Each button is gated on the permission its OWN endpoint
                         already enforces, so the page offers only what the viewer
                         can actually carry out. $canAct additionally blocks acting
                         on yourself: nobody records their own bonus, advance or
                         loan, administrator or not. --}}
                    <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-slate-100">
                        @if($canAct)
                            @if($ledgerBalance > 0)
                                @can('edit payment schedule')
                                    <button type="button" data-open-modal="payDueModal" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 transition-colors">
                                        <i class="fas fa-money-check-dollar"></i> Pay Due Amount
                                    </button>
                                @endcan
                            @endif
                            @can('create advance salary')
                                <button type="button" data-open-modal="advanceModal" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 transition-colors">
                                    <i class="fas fa-hand-holding-dollar"></i> Advance
                                </button>
                            @endcan
                            @can('create loan')
                                <button type="button" data-open-modal="loanModal" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 transition-colors">
                                    <i class="fas fa-sack-dollar"></i> Loan
                                </button>
                            @endcan
                            @can('create salary')
                                <button type="button" data-open-modal="bonusModal" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 transition-colors">
                                    <i class="fas fa-gift"></i> Bonus
                                </button>
                                <button type="button" data-open-modal="openingBalanceModal" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-50 text-slate-700 border border-slate-200 hover:bg-slate-100 transition-colors">
                                    <i class="fas fa-scale-balanced"></i> Opening Balance
                                </button>
                            @endcan
                        @endif
                        <a href="{{ route('role.user.salary-transaction-report.print', ['role' => $role, 'user' => $employee->id]) }}"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition-colors">
                            <i class="fas fa-print"></i> Print Transaction Report
                        </a>
                    </div>
                </div>

                @if($pendingReconciliation)
                    <div class="summary-card p-5 md:p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-800">Leave Encashment — Year {{ $pendingReconciliation['service_year_in_progress'] }} accrual</h2>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Building since {{ $pendingReconciliation['period_start']->format('M Y') }}, refundable in full on {{ $pendingReconciliation['next_anniversary_date']->format('M d, Y') }} (company leave-encashment payout month)
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="summary-label">Projected Total Payout</p>
                                <p class="text-2xl font-extrabold text-blue-700 mt-0.5">৳ {{ number_format($pendingReconciliation['projected_total_payout'], 2) }}</p>
                            </div>
                        </div>
                        @if($pendingReconciliation['service_year_in_progress'] == 1)
                            @if($canAct) @can('create salary')
                            <button type="button" data-open-modal="leaveEncashmentOpeningModal" class="mb-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-50 text-slate-700 border border-slate-200 hover:bg-slate-100 transition-colors">
                                <i class="fas fa-clock-rotate-left"></i> {{ $pendingReconciliation['opening_entry'] ? 'Edit' : 'Set' }} Opening Entry
                            </button>
                            @endcan @endif
                        @endif
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead>
                                    <tr class="bg-slate-50">
                                        <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Month</th>
                                        <th class="px-4 py-2 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Leave Deduction (৳)</th>
                                        <th class="px-4 py-2 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Absent Deduction (৳)</th>
                                        <th class="px-4 py-2 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Cumulative Accrued (৳)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    @php $runningTotal = 0; @endphp
                                    @if($pendingReconciliation['opening_entry'])
                                        @php $runningTotal += (float) $pendingReconciliation['opening_entry']->amount; @endphp
                                        <tr class="bg-slate-50/60">
                                            <td class="px-4 py-2 whitespace-nowrap text-sm text-slate-600">
                                                Opening Entry
                                                @if($pendingReconciliation['opening_entry']->as_of_date)
                                                    <span class="text-xs text-slate-400">(as of {{ $pendingReconciliation['opening_entry']->as_of_date->format('M d, Y') }})</span>
                                                @endif
                                                <span class="text-xs text-slate-400">— {{ number_format($pendingReconciliation['opening_entry']->days, 1) }} days</span>
                                            </td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium text-blue-600">৳ {{ number_format($pendingReconciliation['opening_entry']->amount, 2) }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm text-slate-400">—</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-bold text-slate-800">৳ {{ number_format($runningTotal, 2) }}</td>
                                        </tr>
                                    @endif
                                    @forelse ($pendingReconciliation['monthly_breakdown'] as $row)
                                        @php $runningTotal += (float) $row->leave_deduction; @endphp
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="px-4 py-2 whitespace-nowrap text-sm text-slate-600">{{ \Carbon\Carbon::create((int) $row->year, (int) $row->month, 1)->format('F Y') }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium text-blue-600">৳ {{ number_format($row->leave_deduction, 2) }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm text-slate-400">৳ {{ number_format($row->absent_deduction, 2) }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-bold text-slate-800">৳ {{ number_format($runningTotal, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-8 text-sm text-slate-400">No payroll months in this accrual window yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="summary-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Date</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Type</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Detail</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Owed to Emp</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Paid / Recovered</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Net Due</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse ($ledgerEntries as $entry)
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-500">{{ $entry->entry_date->format('M d, Y') }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            @php
                                                $badgeClass = match(true) {
                                                    $entry->credit > 0 => 'bg-green-50 text-green-700 border border-green-100',
                                                    $entry->type === 'bonus' => 'bg-purple-50 text-purple-700 border border-purple-100',
                                                    $entry->type === 'salary_reconciliation' => 'bg-blue-50 text-blue-700 border border-blue-100',
                                                    $entry->type === 'opening_balance' => 'bg-slate-100 text-slate-700 border border-slate-200',
                                                    default => 'bg-indigo-50 text-indigo-700 border border-indigo-100',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeClass }}">
                                                {{ \Illuminate\Support\Str::headline($entry->type) }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-slate-600">{{ $entry->reference }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-medium text-slate-700">
                                            {{ $entry->debit > 0 ? '৳ ' . number_format($entry->debit, 2) : '—' }}
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-medium text-green-600">
                                            {{ $entry->credit > 0 ? '৳ ' . number_format($entry->credit, 2) : '—' }}
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-bold text-slate-800">
                                            ৳ {{ number_format($entry->balance, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-12">
                                            <div class="flex flex-col items-center gap-2">
                                                <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center">
                                                    <i class="fas fa-receipt fa-2x text-slate-300"></i>
                                                </div>
                                                <h4 class="text-slate-500 text-base font-semibold mt-1">No account activity yet</h4>
                                                <p class="text-slate-400 text-sm">Salary earned and paid events for {{ $employee->name }} will show up here.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>{{-- /accounts panel --}}

            <div data-profile-panel="payslips" class="profile-tab-panel hidden space-y-5">
                <div class="summary-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">Payslips</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Every monthly salary sheet row generated for {{ $employee->name }}</p>
                        </div>
                        <a href="{{ route('role.user.payslips-report.print', ['role' => $role, 'user' => $employee->id]) }}"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition-colors shrink-0">
                            <i class="fas fa-print"></i> Print Payslips Report
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Month</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Gross</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Deductions</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Net Salary</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Paid</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Due</th>
                                    <th class="px-5 py-2.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                                    <th class="px-5 py-2.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse ($payslips as $slip)
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-5 py-3 whitespace-nowrap text-sm font-medium text-slate-700">
                                            {{ \Carbon\Carbon::createFromDate((int) $slip->year, (int) $slip->month, 1)->format('F Y') }}
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm text-slate-700">৳ {{ number_format($slip->gross_salary, 2) }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm text-red-600">৳ {{ number_format($slip->total_deductions, 2) }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-bold text-slate-800">৳ {{ number_format($slip->net_salary, 2) }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm text-green-600">৳ {{ number_format($slip->paid_amount, 2) }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm {{ $slip->due_amount > 0 ? 'text-red-600 font-semibold' : 'text-slate-400' }}">৳ {{ number_format($slip->due_amount, 2) }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-center">
                                            @if($slip->display_status === 'Paid')
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">Paid</span>
                                            @elseif($slip->display_status === 'Partial')
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 border border-orange-200">Partial</span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700 border border-yellow-200">Pending</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <a href="{{ route('role.employee-salaries.show', ['role' => $role, 'employee_salary' => $slip->id]) }}"
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-600 hover:text-white transition-colors">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="{{ route('role.employee-salaries.action', ['role' => $role, 'id' => $slip->id, 'action' => 'download']) }}"
                                                    class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-slate-50 text-slate-600 border border-slate-200 hover:bg-slate-600 hover:text-white transition-colors"
                                                    title="Download PDF">
                                                    <i class="fas fa-download text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-12">
                                            <div class="flex flex-col items-center gap-2">
                                                <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center">
                                                    <i class="fas fa-file-invoice-dollar fa-2x text-slate-300"></i>
                                                </div>
                                                <h4 class="text-slate-500 text-base font-semibold mt-1">No payslips yet</h4>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>{{-- /payslips panel --}}

            <div data-profile-panel="attendance" class="profile-tab-panel hidden space-y-5">
                <div class="summary-card p-5 md:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <h2 class="text-lg font-semibold text-slate-800">Attendance History</h2>
                        <form method="GET" class="flex items-center gap-2" id="attendanceMonthForm">
                            <input type="hidden" name="tab" value="attendance">
                            <input type="month" name="att_month" value="{{ $attMonthInput }}"
                                class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors">Go</button>
                        </form>
                    </div>
                </div>

                <div class="summary-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Date</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Day</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Check In</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Check Out</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Shift</th>
                                    <th class="px-5 py-2.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse ($attendanceHistory as $att)
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-5 py-3 whitespace-nowrap text-sm font-medium text-slate-700">{{ \Carbon\Carbon::parse($att->date)->format('M d, Y') }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-500">{{ \Carbon\Carbon::parse($att->date)->format('D') }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-600">{{ $att->check_in ? \Carbon\Carbon::parse($att->check_in)->format('h:i A') : '—' }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-600">{{ $att->check_out ? \Carbon\Carbon::parse($att->check_out)->format('h:i A') : '—' }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-500">{{ $att->shift->name ?? '—' }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-center">
                                            @php
                                                $statusClass = match(strtolower($att->status)) {
                                                    'present' => 'bg-green-100 text-green-700 border border-green-200',
                                                    'absent' => 'bg-red-100 text-red-700 border border-red-200',
                                                    'leave' => 'bg-amber-100 text-amber-700 border border-amber-200',
                                                    'holiday' => 'bg-blue-100 text-blue-700 border border-blue-200',
                                                    default => 'bg-slate-100 text-slate-700 border border-slate-200',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">{{ ucfirst($att->status) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-12">
                                            <div class="flex flex-col items-center gap-2">
                                                <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center">
                                                    <i class="fas fa-calendar-xmark fa-2x text-slate-300"></i>
                                                </div>
                                                <h4 class="text-slate-500 text-base font-semibold mt-1">No attendance records for this month</h4>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>{{-- /attendance panel --}}

            <div data-profile-panel="all-details" class="profile-tab-panel hidden space-y-5">
                <div class="summary-card p-5 md:p-6">
                    <h2 class="text-lg font-semibold text-slate-800 mb-4">Employment Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                        <div class="summary-kpi p-3"><p class="text-slate-500">Full Name</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->name }}</p></div>
                        <div class="summary-kpi p-3"><p class="text-slate-500">Email</p><p class="font-semibold text-slate-800 mt-1 break-words">{{ $employee->email ?? '-' }}</p></div>
                        <div class="summary-kpi p-3"><p class="text-slate-500">Phone</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->phone ?? '-' }}</p></div>
                        <div class="summary-kpi p-3"><p class="text-slate-500">Company</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->company->name ?? '-' }}</p></div>
                        <div class="summary-kpi p-3"><p class="text-slate-500">Department</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->profile->department->name ?? '-' }}</p></div>
                        <div class="summary-kpi p-3"><p class="text-slate-500">Designation</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->profile->designation->name ?? '-' }}</p></div>
                        <div class="summary-kpi p-3"><p class="text-slate-500">Employment Type</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->profile->employment_type ?? '-' }}</p></div>
                        <div class="summary-kpi p-3"><p class="text-slate-500">Joining Date</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->profile->joining_date ? \Carbon\Carbon::parse($employee->profile->joining_date)->format('M d, Y') : '-' }}</p></div>
                        <div class="summary-kpi p-3"><p class="text-slate-500">Base Salary</p><p class="font-semibold text-slate-800 mt-1">{{ $employee->profile->salary ? '৳ ' . number_format($employee->profile->salary, 2) : '-' }}</p></div>
                    </div>
                </div>

                <div class="summary-card p-5 md:p-6">
                    <h2 class="text-lg font-semibold text-slate-800 mb-4">Documents</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                        @foreach ([
                            ['label' => 'NID', 'field' => 'nid', 'icon' => 'fa-id-card'],
                            ['label' => 'Appointment Letter', 'field' => 'appointment_letter', 'icon' => 'fa-file-lines'],
                            ['label' => 'Passport Size Photo', 'field' => 'passport_size_image', 'icon' => 'fa-image'],
                        ] as $doc)
                            @php $filePath = $employeeDocument?->{$doc['field']}; @endphp
                            <div class="summary-kpi p-4 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="fas {{ $doc['icon'] }} text-slate-400"></i>
                                    <span class="text-slate-600">{{ $doc['label'] }}</span>
                                </div>
                                @if($filePath)
                                    <a href="{{ asset($filePath) }}" target="_blank" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">View</a>
                                @else
                                    <span class="text-slate-400 text-xs">Not uploaded</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>{{-- /all-details panel --}}

        </div>
    </div>

    {{-- ── Quick-action modals ── --}}
    @if($canAct) @can('create advance salary')
    <div id="advanceModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50" data-close-modal></div>
        <div class="relative bg-white w-11/12 md:max-w-md mx-auto rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Give Advance</h3>
                <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form id="advanceForm" action="{{ route('role.advance-salaries.store', ['role' => $role]) }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" value="{{ $employee->id }}">
                <input type="hidden" name="status" value="Pending">
                <div class="space-y-3">
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">For Month</label>
                        <input type="month" name="month" required value="{{ now()->format('Y-m') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Reason (optional)</label>
                        <textarea name="reason" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-amber-600 text-white hover:bg-amber-700 transition-colors">Save Advance</button>
                </div>
            </form>
        </div>
    </div>
    @endcan @endif

    @if($canAct) @can('create loan')
    <div id="loanModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50" data-close-modal></div>
        <div class="relative bg-white w-11/12 md:max-w-md mx-auto rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Give Loan</h3>
                <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form id="loanForm" action="{{ route('role.loans.store', ['role' => $role]) }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" value="{{ $employee->id }}">
                <div class="space-y-3">
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Monthly Deduction</label>
                        <input type="number" step="0.01" min="0" name="monthly_deduction" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Start Date</label>
                        <input type="date" name="start_date" required value="{{ now()->format('Y-m-d') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Bank (money out from)</label>
                        <select name="bank_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Not specified —</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-purple-600 text-white hover:bg-purple-700 transition-colors">Save Loan</button>
                </div>
            </form>
        </div>
    </div>
    @endcan @endif

    @if($canAct) @can('edit payment schedule')
    <div id="payDueModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50" data-close-modal></div>
        <div class="relative bg-white w-11/12 md:max-w-md mx-auto rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Pay Due Amount</h3>
                <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <p class="text-xs text-slate-500 mb-3">
                Current due: <span class="font-bold text-red-600">৳ {{ number_format(max($ledgerBalance, 0), 2) }}</span> —
                oldest unpaid salary/encashment months are settled first automatically.
            </p>
            <form id="payDueForm" action="{{ route('role.user.pay-due-amount', ['role' => $role, 'user' => $employee->id]) }}" method="POST">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" max="{{ max($ledgerBalance, 0) }}" name="amount" required
                            value="{{ number_format(max($ledgerBalance, 0), 2, '.', '') }}"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Payment Date</label>
                        <input type="date" name="payment_date" required value="{{ now()->format('Y-m-d') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Payment Method</label>
                        <select name="payment_method" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Select —</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_banking">Mobile Banking (bKash/Nagad)</option>
                            <option value="cheque">Cheque</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Bank (money out from)</label>
                        <select name="bank_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Select —</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Note (optional)</label>
                        <input type="text" name="note" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700 transition-colors">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
    @endcan @endif

    @if($canAct) @can('create salary')
    <div id="openingBalanceModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50" data-close-modal></div>
        <div class="relative bg-white w-11/12 md:max-w-md mx-auto rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Add Opening Balance</h3>
                <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <p class="text-xs text-slate-500 mb-3">
                For money genuinely owed from before proper ledger tracking existed for this employee — e.g. a salary month created outside the normal flow. Posts once as a running-balance adjustment (Dr Opening Balance Equity / Cr Salary Payable), not tied to any specific salary record.
            </p>
            <form id="openingBalanceForm" action="{{ route('role.user.ledger.opening-balance', ['role' => $role, 'user' => $employee->id]) }}" method="POST">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Date</label>
                        <input type="date" name="entry_date" required value="{{ now()->format('Y-m-d') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Note (optional)</label>
                        <input type="text" name="reference" placeholder="e.g. Pre-system salary due, March-June 2026" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-700 text-white hover:bg-slate-800 transition-colors">Save Opening Balance</button>
                </div>
            </form>
        </div>
    </div>
    @endcan @endif

    @php $openingEntry = $pendingReconciliation['opening_entry'] ?? null; @endphp
    @if($canAct) @can('create salary')
    <div id="leaveEncashmentOpeningModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50" data-close-modal></div>
        <div class="relative bg-white w-11/12 md:max-w-md mx-auto rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">{{ $openingEntry ? 'Edit' : 'Set' }} Leave Encashment Opening Entry</h3>
                <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <p class="text-xs text-slate-500 mb-3">
                One-time credit toward this employee's <strong>first</strong> leave-encashment payout only, for service time before clean monthly tracking existed. Does not post to the ledger now — it's added automatically when the first payout is calculated.
                @if($openingEntry)
                    <span class="block mt-1 text-slate-400">Saving again replaces the values below — it does not add a second entry.</span>
                @endif
            </p>
            <form id="leaveEncashmentOpeningForm" action="{{ route('role.user.leave-encashment-opening', ['role' => $role, 'user' => $employee->id]) }}" method="POST">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Days</label>
                        <input type="number" step="0.1" min="0" name="days" required value="{{ $openingEntry ? rtrim(rtrim(number_format($openingEntry->days, 2, '.', ''), '0'), '.') : '' }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Amount</label>
                        <input type="number" step="0.01" min="0" name="amount" required value="{{ $openingEntry ? number_format($openingEntry->amount, 2, '.', '') : '' }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">As Of Date</label>
                        <input type="date" name="as_of_date" required value="{{ $openingEntry && $openingEntry->as_of_date ? $openingEntry->as_of_date->format('Y-m-d') : '' }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-[11px] text-slate-400 mt-1">The last date these days/amount already cover — live tracking only counts leave <strong>after</strong> this date, so nothing gets counted twice.</p>
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Note (optional)</label>
                        <input type="text" name="notes" value="{{ $openingEntry->notes ?? '' }}" placeholder="e.g. Pre-Feb 2026 estimated leave taken" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 transition-colors">Save Opening Entry</button>
                </div>
            </form>
        </div>
    </div>
    @endcan @endif

    @if($canAct) @can('create salary')
    <div id="bonusModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50" data-close-modal></div>
        <div class="relative bg-white w-11/12 md:max-w-md mx-auto rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Record Bonus</h3>
                <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form id="bonusForm" action="{{ route('role.user.ledger.bonus', ['role' => $role, 'user' => $employee->id]) }}" method="POST">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Date</label>
                        <input type="date" name="entry_date" required value="{{ now()->format('Y-m-d') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Reason (optional)</label>
                        <input type="text" name="reference" placeholder="e.g. Eid bonus, performance bonus" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-green-600 text-white hover:bg-green-700 transition-colors">Save Bonus</button>
                </div>
            </form>
        </div>
    </div>
    @endcan @endif
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function() {
            const tabButtons = document.querySelectorAll('[data-profile-tab]');
            const tabPanels = document.querySelectorAll('[data-profile-panel]');

            function activateTab(target) {
                const btn = document.querySelector('[data-profile-tab="' + target + '"]');
                if (!btn) return;
                tabButtons.forEach(function(b) { b.classList.toggle('active', b === btn); });
                tabPanels.forEach(function(panel) {
                    panel.classList.toggle('hidden', panel.getAttribute('data-profile-panel') !== target);
                });
            }

            tabButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    activateTab(btn.getAttribute('data-profile-tab'));
                });
            });

            document.querySelectorAll('[data-jump-tab]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    activateTab(btn.getAttribute('data-jump-tab'));
                });
            });

            const requestedTab = new URLSearchParams(window.location.search).get('tab');
            if (requestedTab) {
                activateTab(requestedTab);
            }
        })();

        // ── Quick-action modals (Advance / Loan / Bonus) ──────────────────
        (function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            document.querySelectorAll('[data-open-modal]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const modal = document.getElementById(btn.getAttribute('data-open-modal'));
                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    }
                });
            });

            document.querySelectorAll('[data-close-modal]').forEach(function(el) {
                el.addEventListener('click', function() {
                    const modal = el.closest('.fixed.inset-0');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                });
            });

            ['advanceForm', 'loanForm', 'bonusForm', 'payDueForm', 'openingBalanceForm', 'leaveEncashmentOpeningForm'].forEach(function(formId) {
                const form = document.getElementById(formId);
                if (!form) return;

                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.textContent;
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Saving...';

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: new FormData(form),
                    })
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            if (data.success) {
                                window.location.reload();
                            } else {
                                alert(data.message || 'Something went wrong.');
                                submitBtn.disabled = false;
                                submitBtn.textContent = originalText;
                            }
                        })
                        .catch(function() {
                            alert('Something went wrong. Please try again.');
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalText;
                        });
                });
            });
        })();
    </script>
    <script>
        (function() {
            const weeklyCanvas = document.getElementById('weeklyTaskPerformanceChart');
            const monthlyCanvas = document.getElementById('monthlyTaskPerformanceChart');
            const attendanceMonthCanvas = document.getElementById('attendanceMonthChart');

            const weeklyLabels = @json($taskWeeklyLabels ?? []);
            const weeklyCreated = @json($taskWeeklyCreatedData ?? []);
            const weeklyCompleted = @json($taskWeeklyCompletedData ?? []);

            const monthlyLabels = @json($taskMonthlyLabels ?? []);
            const monthlyCreated = @json($taskMonthlyCreatedData ?? []);
            const monthlyCompleted = @json($taskMonthlyCompletedData ?? []);
            const attendanceMonthLabels = @json($attendanceMonthLabels ?? []);
            const attendanceMonthLateMinutesData = @json($attendanceMonthLateMinutesData ?? []);
            const attendanceMonthWorkingHoursData = @json($attendanceMonthWorkingHoursData ?? []);

            if (weeklyCanvas) {
                new Chart(weeklyCanvas, {
                    type: 'bar',
                    data: {
                        labels: weeklyLabels,
                        datasets: [{
                                label: 'Assigned',
                                data: weeklyCreated,
                                backgroundColor: 'rgba(59, 130, 246, 0.75)',
                                borderRadius: 6
                            },
                            {
                                label: 'Completed',
                                data: weeklyCompleted,
                                backgroundColor: 'rgba(16, 185, 129, 0.75)',
                                borderRadius: 6
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }

            if (monthlyCanvas) {
                new Chart(monthlyCanvas, {
                    type: 'line',
                    data: {
                        labels: monthlyLabels,
                        datasets: [{
                                label: 'Assigned',
                                data: monthlyCreated,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37, 99, 235, 0.16)',
                                borderWidth: 2,
                                pointRadius: 3,
                                tension: 0.35,
                                fill: true
                            },
                            {
                                label: 'Completed',
                                data: monthlyCompleted,
                                borderColor: '#059669',
                                backgroundColor: 'rgba(5, 150, 105, 0.16)',
                                borderWidth: 2,
                                pointRadius: 3,
                                tension: 0.35,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }

            if (attendanceMonthCanvas) {
                new Chart(attendanceMonthCanvas, {
                    type: 'line',
                    data: {
                        labels: attendanceMonthLabels,
                        datasets: [{
                                label: 'Late Time (min)',
                                data: attendanceMonthLateMinutesData,
                                borderColor: '#ea580c',
                                backgroundColor: 'rgba(234, 88, 12, 0.12)',
                                borderWidth: 2,
                                pointRadius: 2,
                                tension: 0.35,
                                fill: true,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Working Hour',
                                data: attendanceMonthWorkingHoursData,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37, 99, 235, 0.10)',
                                borderWidth: 2,
                                pointRadius: 2,
                                tension: 0.35,
                                fill: true,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    maxTicksLimit: 10
                                }
                            },
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Minutes'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                beginAtZero: true,
                                grid: {
                                    drawOnChartArea: false
                                },
                                title: {
                                    display: true,
                                    text: 'Hours'
                                }
                            }
                        }
                    }
                });
            }
        })();
    </script>
@endsection
