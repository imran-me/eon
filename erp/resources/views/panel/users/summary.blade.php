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

        .employee-ledger-wide {
            width: 100%;
            max-width: 1600px;
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
    $isEmployeeLedgerRoute = request()->routeIs('role.employee-ledger.*');
@endphp
    <div class="summary-shell rounded-2xl p-3 md:p-5">
        <div class="{{ $isEmployeeLedgerRoute ? 'employee-ledger-wide' : 'max-w-7xl' }} mx-auto space-y-5">
            @if($isEmployeeLedgerRoute)
            <div class="summary-card p-3 md:p-4">
                <div class="flex flex-col xl:flex-row xl:items-end gap-3">
                    <div class="flex-1 min-w-0">
                        <label class="summary-label block mb-1.5"><i class="fas fa-search mr-1"></i> Employee</label>
                        <select id="employeeLedgerPicker" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white text-sm focus:ring-2 focus:ring-blue-500">
                            @foreach($employeeOptions as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $employee->id)>
                                    {{ $option->name }} — {{ $option->employee_id_no ?: 'EMP-'.str_pad($option->id, 3, '0', STR_PAD_LEFT) }}{{ $option->company ? ' · '.$option->company->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0 rounded-lg bg-slate-100 p-1">
                        <a target="_blank" title="Print statement" href="{{ route('role.user.salary-transaction-report.print', ['role' => $role, 'user' => $employee->id]) }}" class="inline-flex items-center px-3 py-2 rounded-md bg-white text-slate-700 hover:text-blue-700 text-xs font-semibold shadow-sm"><i class="fas fa-print mr-1.5"></i> Print</a>
                        <button type="button" title="Email PDF statement" data-ledger-send="email" class="cursor-pointer inline-flex items-center px-3 py-2 rounded-md text-blue-700 hover:bg-white text-xs font-semibold"><i class="fas fa-envelope mr-1.5"></i> Email</button>
                        <button type="button" title="Share through WhatsApp" id="employeeLedgerWhatsApp" class="cursor-pointer inline-flex items-center px-3 py-2 rounded-md text-emerald-700 hover:bg-white text-xs font-semibold"><i class="fab fa-whatsapp mr-1.5"></i> WhatsApp</button>
                        <button type="button" title="Send statement summary by SMS" data-ledger-send="sms" class="cursor-pointer inline-flex items-center px-3 py-2 rounded-md text-amber-700 hover:bg-white text-xs font-semibold"><i class="fas fa-comment-sms mr-1.5"></i> SMS</button>
                    </div>
                    <a href="{{ route('role.employee-salaries.index', ['role' => $role]) }}" class="shrink-0 inline-flex items-center justify-center px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 text-xs font-semibold">
                        <i class="fas fa-arrow-left mr-1.5"></i> Back to Payroll
                    </a>
                </div>
            </div>
            @endif
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
                            <p class="text-[11px] uppercase tracking-[0.08em] font-bold text-blue-200">{{ $isEmployeeLedgerRoute ? 'Employee Ledger' : 'Employee Overview' }}</p>
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
                    @unless($isEmployeeLedgerRoute)
                    <div class="flex items-center gap-2">
                        <button type="button" data-jump-tab="payslips"
                            class="inline-flex items-center px-4 py-2 rounded-lg bg-white/15 border border-white/30 text-white hover:bg-white/25 transition text-sm font-medium">
                            <i class="fas fa-file-invoice-dollar mr-2"></i> Payslip
                        </button>
                        <a href="{{ $isEmployeeLedgerRoute ? route('role.employee-salaries.index', ['role' => $role]) : $returnUrl }}"
                            class="inline-flex items-center px-4 py-2 rounded-lg bg-white/15 border border-white/30 text-white hover:bg-white/25 transition text-sm font-medium">
                            <i class="fas fa-arrow-left mr-2"></i> {{ $isEmployeeLedgerRoute ? 'Back to Payroll' : 'Back to User List' }}
                        </a>
                    </div>
                    @endunless
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
                        <p class="text-[10px] text-slate-400 mt-1">{{ $advanceOut > 0 ? 'Pending payroll recovery' : 'No outstanding advance' }}</p>
                    </div>
                    <div class="summary-kpi p-4">
                        <p class="summary-label">Loan Out</p>
                        <p class="text-xl font-extrabold text-red-600 mt-1">৳ {{ number_format((float) ($loanStats->total_remaining ?? 0), 0) }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">{{ (float) ($loanStats->total_remaining ?? 0) > 0 ? 'Remaining employee loan' : 'No outstanding loan' }}</p>
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
                                <p class="text-xs text-slate-500">{{ number_format($pendingReconciliation['earned_days'], 1) }} earned · {{ number_format($pendingReconciliation['used_leave_days'], 1) }} used · {{ number_format($pendingReconciliation['encashable_days'], 1) }} encashable days</p>
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
                        <a href="{{ route('role.user.leave-encashment-report.print', ['role' => $role, 'user' => $employee->id]) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 mb-3 ml-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition-colors">
                            <i class="fas fa-print"></i> Print Leave Encashment
                        </a>
                        @if((auth()->user()->is_super_admin || auth()->user()->hasRole('super admin')) && $pendingReconciliation['projected_total_payout'] > 0)
                            @if($canAct) @can('create salary')
                            <form id="exitLeaveEncashmentForm" class="inline-block mb-3 ml-1" action="{{ route('role.user.leave-encashment-exit-payout', ['role' => $role, 'user' => $employee->id]) }}" method="POST" onsubmit="return confirm('Post this leave encashment to the ledger, journal and payment schedule?')">
                                @csrf
                                <input type="hidden" name="payout_date" value="{{ now()->format('Y-m-d') }}">
                                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition-colors"><i class="fas fa-file-invoice-dollar"></i> Create Encashment Payout</button>
                            </form>
                            @endcan @endif
                        @endif
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead>
                                    <tr class="bg-slate-50">
                                        <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Month</th>
                                        <th class="px-4 py-2 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Earned Days</th>
                                        <th class="px-4 py-2 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Used Days</th>
                                        <th class="px-4 py-2 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Earned Value</th>
                                        <th class="px-4 py-2 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Leave Deduction (৳)</th>
                                        <th class="px-4 py-2 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Absent Deduction (৳)</th>
                                        <th class="px-4 py-2 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Cumulative Accrued (৳)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    @php
                                        $runningEarned = 0; $runningEarnedDays = 0; $runningUsedDays = 0;
                                        $runningConsumed = 0; $runningRefund = 0; $runningTotal = 0;
                                        $isFullCycle = $pendingReconciliation['earned_days'] >= 24;
                                    @endphp
                                    @if($pendingReconciliation['opening_entry'])
                                        @php $runningRefund += (float) $pendingReconciliation['opening_entry']->amount; $runningTotal = $runningRefund; @endphp
                                        <tr class="bg-slate-50/60">
                                            <td colspan="4" class="px-4 py-2 whitespace-nowrap text-sm text-slate-600">
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
                                        @php
                                            $runningEarned += (float) $row->earned_value;
                                            $runningEarnedDays += (float) $row->earned_days;
                                            $runningUsedDays += (float) $row->used_leave_days;
                                            $runningConsumed += (float) $row->consumed_leave_value;
                                            $runningRefund += (float) $row->leave_deduction;
                                            $runningTotal = $isFullCycle
                                                ? max(0, $pendingReconciliation['current_gross_salary'] * (($runningEarnedDays - $runningUsedDays) / 24)) + $runningRefund
                                                : max(0, $runningEarned - $runningConsumed) + $runningRefund;
                                            $runningTotal += (float) ($pendingReconciliation['opening_entry']->amount ?? 0);
                                        @endphp
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="px-4 py-2 whitespace-nowrap text-sm text-slate-600">{{ \Carbon\Carbon::create((int) $row->year, (int) $row->month, 1)->format('F Y') }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm text-slate-700">{{ number_format($row->earned_days, 1) }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm text-slate-700">{{ number_format($row->used_leave_days, 1) }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium text-blue-600">{{ number_format($row->earned_value, 2) }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium text-blue-600">৳ {{ number_format($row->leave_deduction, 2) }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm text-slate-400">৳ {{ number_format($row->absent_deduction, 2) }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-bold text-slate-800">৳ {{ number_format($runningTotal, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-8 text-sm text-slate-400">No payroll months in this accrual window yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div id="employee-ledger-transactions" class="summary-card overflow-hidden">
                    <form method="GET" action="{{ url()->current() }}">
                        <input type="hidden" name="tab" value="accounts">
                        <x-list-filter-bar
                            search="ledger_q"
                            placeholder="Search amount, reference, note, transaction type or payment method..."
                            from="ledger_from"
                            to="ledger_to"
                            :reset="url()->current().'?tab=accounts#employee-ledger-transactions'"
                            :total="$ledgerEntries->total()">
                            <x-slot:leading>
                                <select name="ledger_status" aria-label="Transaction status" onchange="this.form.submit()">
                                    <option value="active" @selected($ledgerStatus === 'active')>Active transactions</option>
                                    <option value="all" @selected($ledgerStatus === 'all')>All transactions</option>
                                </select>
                                <button type="button"
                                    data-ledger-reversal-history
                                    class="lfb-action !w-[34px] !px-0 !justify-center"
                                    aria-label="Reversal history ({{ $ledgerReversalCount }})"
                                    title="Reversal history ({{ $ledgerReversalCount }})">
                                    <i class="fas fa-rotate-left" aria-hidden="true"></i>
                                </button>
                            </x-slot:leading>
                            <x-slot:actions>
                                <button type="button" data-open-modal="employeeLedgerPrintModal" class="lfb-action">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </x-slot:actions>
                        </x-list-filter-bar>
                    </form>
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
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse ($ledgerEntries as $entry)
                                    @php
                                        $employeePayment = $entry->source instanceof \App\Models\EmployeePayment ? $entry->source : null;
                                        $advancePayment = $entry->source instanceof \App\Models\AdvanceSalary ? $entry->source : null;
                                        $advanceJournal = $advancePayment?->releaseJournal;
                                        $loanPayment = $entry->source instanceof \App\Models\Loan ? $entry->source : null;
                                        $loanJournal = $loanPayment ? \App\Models\JournalEntry::where('source', 'loan')->where('source_id', $loanPayment->id)->first() : null;
                                        $paymentReversed = $employeePayment?->status === 'reversed';
                                        $entryIsReversal = str_ends_with((string) $entry->type, '_reversal');
                                        $canReverseEntry = ! $entryIsReversal && (($employeePayment && ! $paymentReversed) || ($advancePayment && $advancePayment->is_released && ! $advancePayment->is_reversed));
                                        $reverseUrl = $employeePayment && ! $paymentReversed
                                            ? route('role.employee-payments.reverse', ['role' => $role, 'payment' => $employeePayment->id])
                                            : ($advancePayment && $advancePayment->is_released && ! $advancePayment->is_reversed && Route::has('role.advance-salaries.reverse')
                                                ? route('role.advance-salaries.reverse', ['role' => $role, 'id' => $advancePayment->id])
                                                : '');
                                        $rawEntryTime = $entry->transaction_time ?: $employeePayment?->transaction_time ?: $advanceJournal?->entry_time ?: $loanJournal?->entry_time;
                                        $correctionTime = $rawEntryTime ? substr((string) $rawEntryTime, 0, 5) : '00:00';
                                        $correctionAmount = max((float) $entry->debit, (float) $entry->credit);
                                        $correctionMethod = $employeePayment?->payment_method ?: $advanceJournal?->beneficiary_method ?: $loanJournal?->beneficiary_method ?: 'ledger';
                                        $correctionBank = $employeePayment?->bank?->name ?: $advanceJournal?->beneficiary_bank ?: $loanPayment?->bank?->name ?: 'Employee ledger';
                                    @endphp
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-500">
                                            <div>{{ $entry->entry_date->format('M d, Y') }}</div>
                                            @if($entry->display_time)
                                                <div class="mt-0.5 text-[11px] font-medium text-slate-400">
                                                    <i class="far fa-clock mr-1"></i>{{ $entry->display_time }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            @php
                                                $badgeClass = match(true) {
                                                    $entry->credit > 0 || $advancePayment || $loanPayment => 'bg-green-50 text-green-700 border border-green-100',
                                                    $entry->type === 'bonus' => 'bg-purple-50 text-purple-700 border border-purple-100',
                                                    $entry->type === 'salary_reconciliation' => 'bg-blue-50 text-blue-700 border border-blue-100',
                                                    $entry->type === 'opening_balance' => 'bg-slate-100 text-slate-700 border border-slate-200',
                                                    default => 'bg-indigo-50 text-indigo-700 border border-indigo-100',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeClass }}">
                                                {{ \Illuminate\Support\Str::headline($entry->type) }}
                                            </span>
                                            @if($paymentReversed)
                                                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-50 text-red-700 border border-red-200">Reversed</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-sm text-slate-600">
                                            @php
                                                // For a Pay Due Amount / Mark Paid row, $entry->reference is the
                                                // TRNX-style code, not a sentence — the readable line comes from
                                                // the payment's own method/note instead. Salary Earned/Due
                                                // Settlement/etc. already write a sentence into reference, so
                                                // those pass through unchanged.
                                                $methodLabels = [
                                                    'cash' => 'cash', 'bank_transfer' => 'bank transfer',
                                                    'mobile_banking' => 'mobile banking', 'cheque' => 'cheque', 'card' => 'card',
                                                ];
                                            @endphp
                                            @if($loanPayment)
                                                <div class="text-slate-700 font-medium">Staff loan paid from {{ $loanPayment->bank?->name ?: 'company account' }}</div>
                                                @if($loanJournal?->beneficiary_bank || $loanJournal?->beneficiary_ref)
                                                    <div class="text-xs text-slate-500 mt-0.5">{{ $loanJournal?->beneficiary_bank }}{{ $loanJournal?->beneficiary_bank && $loanJournal?->beneficiary_ref ? ' — ' : '' }}{{ $loanJournal?->beneficiary_ref }}</div>
                                                @endif
                                            @elseif($advancePayment)
                                                <div class="text-slate-700 font-medium">
                                                    Advance salary paid via {{ $methodLabels[$advanceJournal?->beneficiary_method] ?? ($advanceJournal?->beneficiary_method ?: 'cash') }}
                                                </div>
                                                @if($advanceJournal?->beneficiary_bank || $advanceJournal?->beneficiary_ref)
                                                    <div class="text-xs text-slate-500 mt-0.5">
                                                        {{ $advanceJournal?->beneficiary_bank }}{{ $advanceJournal?->beneficiary_bank && $advanceJournal?->beneficiary_ref ? ' — ' : '' }}{{ $advanceJournal?->beneficiary_ref }}
                                                    </div>
                                                @endif
                                            @elseif($employeePayment)
                                                <div class="text-slate-700 font-medium">
                                                    Employee paid via {{ $methodLabels[$employeePayment->payment_method] ?? ($employeePayment->payment_method ?: 'cash') }}
                                                </div>
                                                @if($employeePayment->recipient_bank || $employeePayment->recipient_account)
                                                    <div class="text-xs text-slate-500 mt-0.5">
                                                        {{ $employeePayment->recipient_bank }}{{ $employeePayment->recipient_bank && $employeePayment->recipient_account ? ' — ' : '' }}{{ $employeePayment->recipient_account }}
                                                    </div>
                                                @endif
                                            @else
                                                <div class="text-slate-700">{{ $entry->reference }}</div>
                                            @endif
                                            @if($entry->note)
                                                <div class="text-xs text-slate-500 mt-0.5"><i class="fas fa-note-sticky mr-1 text-slate-300"></i>{{ $entry->note }}</div>
                                            @endif
                                            @if($loanPayment)
                                                <div class="text-[11px] font-mono text-slate-400 mt-0.5">{{ $loanJournal?->reference ?: $entry->reference }}</div>
                                            @elseif($advancePayment)
                                                <div class="text-[11px] font-mono text-slate-400 mt-0.5">{{ $advanceJournal?->reference ?: $entry->reference }}</div>
                                            @elseif($employeePayment)
                                                <div class="text-[11px] font-mono text-slate-400 mt-0.5">{{ $employeePayment->reference }}</div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-medium text-slate-700">
                                            {{ $entry->debit > 0 ? '৳ ' . number_format($entry->debit, 2) : '—' }}
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-medium text-green-600">
                                            {{ $loanPayment ? '৳ ' . number_format($loanPayment->amount, 2) : ($advancePayment ? '৳ ' . number_format($advancePayment->amount, 2) : ($entry->credit > 0 ? '৳ ' . number_format($entry->credit, 2) : '—')) }}
                                            @if($loanPayment || $advancePayment)
                                                <span class="block text-[10px] font-normal text-slate-400">{{ $advancePayment?->is_reversed ? 'Reversed · no payroll recovery' : 'History only · recovered via payroll' }}</span>
                                            @endif
                                        </td>
                                        @php
                                            $netDue = $entry->running_balance ?? $entry->balance;
                                            $netDueCls = $netDue > 0 ? 'text-amber-700' : ($netDue < 0 ? 'text-green-600' : 'text-slate-800');
                                        @endphp
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-bold {{ $netDueCls }}">
                                            ৳ {{ number_format($netDue, 2) }}
                                            @if($netDue != 0)
                                                <span class="block text-[10px] font-semibold uppercase tracking-wide {{ $netDue > 0 ? 'text-amber-500' : 'text-green-500' }}">{{ $netDue > 0 ? 'Due' : 'Advance' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right">
                                            @if($canAct)
                                                @can('edit payment schedule')
                                                    <button type="button"
                                                        data-employee-payment-correction
                                                        data-entry-id="{{ $entry->id }}"
                                                        data-reference="{{ $employeePayment?->reference ?: $entry->reference ?: 'Ledger entry #'.$entry->id }}"
                                                        data-amount="{{ number_format($correctionAmount, 2, '.', '') }}"
                                                        data-debit="{{ number_format($entry->debit, 2, '.', '') }}"
                                                        data-credit="{{ number_format($entry->credit, 2, '.', '') }}"
                                                        data-type="{{ \Illuminate\Support\Str::headline($entry->type) }}"
                                                        data-date="{{ $entry->entry_date->format('Y-m-d') }}"
                                                        data-time="{{ $correctionTime }}"
                                                        data-method="{{ $methodLabels[$correctionMethod] ?? \Illuminate\Support\Str::headline($correctionMethod) }}"
                                                        data-bank="{{ $correctionBank }}"
                                                        data-charge="{{ number_format($employeePayment?->bank_charge_amount ?? 0, 2, '.', '') }}"
                                                        data-reverse-url="{{ $canReverseEntry ? $reverseUrl : '' }}"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-700 transition hover:-translate-y-0.5 hover:bg-blue-100 hover:shadow-sm"
                                                        title="Correct transaction date/time{{ $canReverseEntry ? ' or reverse transaction' : '' }}"
                                                        aria-label="Correct transaction">
                                                        <i class="fas fa-pencil-alt text-xs"></i>
                                                    </button>
                                                @endcan
                                            @elseif($advancePayment)
                                                @if($advancePayment->is_reversed)
                                                    <span class="px-2.5 py-1 rounded text-xs font-semibold bg-slate-100 text-slate-500">Reversed</span>
                                                @elseif($advancePayment->is_released && $canAct)
                                                    @can('edit advance salary')
                                                        @if(Route::has('role.advance-salaries.reverse'))
                                                            <button type="button" data-reverse-advance="{{ $advancePayment->id }}" data-reference="{{ $advanceJournal?->reference }}" data-amount="{{ number_format($advancePayment->amount, 2) }}" class="px-2.5 py-1 rounded text-xs font-semibold bg-red-50 text-red-700 border border-red-200 hover:bg-red-100" title="Reverse an erroneous advance posting">
                                                                <i class="fas fa-rotate-left"></i> Reverse
                                                            </button>
                                                        @endif
                                                    @endcan
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-12">
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
                    @if($ledgerEntries->hasPages() || $ledgerEntries->total())
                        <div class="px-5 py-3 border-t border-slate-100 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <p class="text-xs text-slate-500">
                                Showing {{ $ledgerEntries->firstItem() ?? 0 }}–{{ $ledgerEntries->lastItem() ?? 0 }} of {{ $ledgerEntries->total() }} transactions
                            </p>
                            <div>{{ $ledgerEntries->onEachSide(1)->links() }}</div>
                        </div>
                    @endif
                </div>
            </div>{{-- /accounts panel --}}

            <div data-profile-panel="payslips" class="profile-tab-panel hidden space-y-5">
                <div class="summary-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">Payslips</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Monthly payslips, including any leave encashment paid with that month</p>
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
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Period</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Earnings</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Deductions</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Net Pay</th>
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
                                            {{ $slip->period_label }}
                                            @if($slip->encashment_amount > 0)
                                                <div class="mt-1"><span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-700 border border-blue-200">Includes Leave Encashment · {{ number_format($slip->encashment_earned_days, 1) }} accrued days · ৳ {{ number_format($slip->encashment_amount, 2) }}</span></div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm text-slate-700">৳ {{ number_format($slip->earnings_amount, 2) }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm text-red-600">৳ {{ number_format($slip->total_deductions, 2) }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-bold text-slate-800">৳ {{ number_format($slip->net_pay_amount, 2) }}</td>
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
                <div class="summary-card p-5">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800"><i class="fas fa-calendar-check text-blue-600 mr-2"></i>Monthly Attendance</h2>
                            <p class="text-xs text-slate-500 mt-1">Daily attendance, working hours, late arrivals, leave and overtime.</p>
                        </div>
                        <form method="GET" class="flex items-center gap-2" id="attendanceMonthForm">
                            <input type="hidden" name="tab" value="attendance">
                            @unless($isEmployeeLedgerRoute)
                                <input type="hidden" name="return_url" value="{{ $returnUrl }}">
                            @endunless
                            <input type="month" name="att_month" value="{{ $attMonthInput }}"
                                class="h-10 border border-slate-300 rounded-lg px-3 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit" class="h-10 bg-blue-600 hover:bg-blue-700 text-white px-4 rounded-lg text-sm font-semibold transition-colors"><i class="fas fa-filter mr-1"></i>Apply</button>
                            <a target="_blank" href="{{ route('role.user.attendance-report.print', ['role' => $role, 'user' => $employee->id, 'month' => $attMonthInput]) }}" class="h-10 inline-flex items-center px-4 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 text-sm font-semibold"><i class="fas fa-print mr-1.5"></i>Print</a>
                        </form>
                    </div>
                </div>

                @php
                    $workedHours = intdiv($attendanceWorkedMinutes, 60);
                    $workedMinutes = $attendanceWorkedMinutes % 60;
                    $attendanceMetrics = [
                        ['Total Days', $attendanceSummary['total_days'], 'text-slate-800'],
                        ['Present', $attendanceSummary['present'], 'text-emerald-600'],
                        ['Absent', $attendanceSummary['absent'], 'text-red-600'],
                        ['Late Days', $attendanceSummary['late'], 'text-orange-600'],
                        ['Leave', $attendanceSummary['leave'], 'text-violet-600'],
                        ['Worked Hours', $workedHours.'H '.$workedMinutes.'M', 'text-blue-700'],
                    ];
                @endphp
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                    @foreach($attendanceMetrics as [$metricLabel, $metricValue, $metricColor])
                        <div class="summary-kpi p-4"><p class="summary-label">{{ $metricLabel }}</p><p class="text-xl font-extrabold mt-1 {{ $metricColor }}">{{ $metricValue }}</p></div>
                    @endforeach
                </div>

                <div class="summary-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Date</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Day</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Shift</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Check In</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Check Out</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Worked</th>
                                    <th class="px-5 py-2.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse ($attendanceHistory as $att)
                                    @php
                                        $worked = '—';
                                        if ($att->check_in && $att->check_out) {
                                            $workedStart = \Carbon\Carbon::parse($att->date.' '.$att->check_in);
                                            $workedEnd = \Carbon\Carbon::parse($att->date.' '.$att->check_out);
                                            if ($workedEnd->lt($workedStart)) $workedEnd->addDay();
                                            $rowMinutes = $workedStart->diffInMinutes($workedEnd);
                                            $worked = intdiv($rowMinutes, 60).'h '.($rowMinutes % 60).'m';
                                        } elseif ($att->check_in) {
                                            $worked = 'Missing Out';
                                        }
                                        $statusKey = strtolower((string) $att->status);
                                        $statusClass = match(true) {
                                            str_starts_with($statusKey, 'present') => 'bg-green-100 text-green-700 border border-green-200',
                                            str_starts_with($statusKey, 'late') => 'bg-orange-100 text-orange-700 border border-orange-200',
                                            $statusKey === 'absent' => 'bg-red-100 text-red-700 border border-red-200',
                                            in_array($statusKey, ['leave', 'early leave'], true) => 'bg-violet-100 text-violet-700 border border-violet-200',
                                            $statusKey === 'holiday' => 'bg-blue-100 text-blue-700 border border-blue-200',
                                            default => 'bg-slate-100 text-slate-700 border border-slate-200',
                                        };
                                        $attendanceNote = !empty($att->out_status)
                                            ? $att->out_status.(!empty($att->note) ? ' — '.$att->note : '')
                                            : ($att->note ?? '—');
                                    @endphp
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-5 py-3 whitespace-nowrap text-sm font-semibold text-slate-700">{{ \Carbon\Carbon::parse($att->date)->format('d M Y') }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-500">{{ \Carbon\Carbon::parse($att->date)->format('l') }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-500">{{ data_get($att, 'shift.name') ?? ($att->shift_name ?? '—') }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-600">{{ $att->check_in ? \Carbon\Carbon::parse($att->check_in)->format('h:i A') : '—' }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-600">{{ $att->check_out ? \Carbon\Carbon::parse($att->check_out)->format('h:i A') : '—' }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-sm font-medium {{ $worked === 'Missing Out' ? 'text-red-500' : 'text-slate-600' }}">{{ $worked }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">{{ ucfirst($att->status) }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-xs text-slate-500 min-w-[190px]">{{ $attendanceNote }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-12">
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
                    <div class="px-5 py-3 border-t border-slate-100 bg-slate-50 flex flex-wrap gap-x-6 gap-y-2 text-xs text-slate-500">
                        <span>Late: <strong class="text-orange-600">{{ $attendanceSummary['late_minutes'] }} mins</strong></span>
                        <span>Early leave: <strong class="text-amber-600">{{ $attendanceSummary['early_out'] }} days / {{ $attendanceSummary['early_minutes'] }} mins</strong></span>
                        <span>Holiday: <strong class="text-blue-600">{{ $attendanceSummary['holiday'] }}</strong></span>
                        <span>Overtime: <strong class="text-emerald-600">{{ $attendanceSummary['overtime_days'] }} days / {{ $attendanceSummary['overtime_minutes'] }} mins</strong></span>
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
        <div class="relative bg-white w-11/12 md:max-w-2xl mx-auto rounded-xl shadow-xl p-6 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Give Advance</h3>
                <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form id="advanceForm" action="{{ route('role.advance-salaries.store', ['role' => $role]) }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" value="{{ $employee->id }}">
                <input type="hidden" name="status" value="Approved">
                <div class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2.5"><i class="fas fa-sack-dollar mr-1 text-slate-400"></i> Advance Details</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Amount</label><input type="number" step="0.01" min="0.01" name="amount" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">For Month</label><input type="month" name="month" required value="{{ now()->format('Y-m') }}" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Payment Date <span class="text-red-500">*</span></label><input type="date" name="transaction_date" required value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                            <div>
                                <label class="block text-slate-600 text-sm font-semibold mb-1">Custom Time <span class="text-red-500">*</span></label>
                                <input type="hidden" name="transaction_time" id="advanceTransactionTime">
                                <div class="flex items-center gap-1"><select id="advanceHour" required class="w-20 px-2 py-2 bg-white border border-slate-300 rounded-lg text-sm">@for($hour=1;$hour<=12;$hour++)<option value="{{ $hour }}">{{ str_pad($hour,2,'0',STR_PAD_LEFT) }}</option>@endfor</select><span>:</span><select id="advanceMinute" required class="w-20 px-2 py-2 bg-white border border-slate-300 rounded-lg text-sm">@for($minute=0;$minute<60;$minute++)<option value="{{ $minute }}">{{ str_pad($minute,2,'0',STR_PAD_LEFT) }}</option>@endfor</select><select id="advancePeriod" required class="w-20 px-2 py-2 bg-white border border-slate-300 rounded-lg text-sm"><option>AM</option><option>PM</option></select></div>
                            </div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Reason (optional)</label><input type="text" name="reason" maxlength="500" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2.5"><i class="fas fa-money-bill-transfer mr-1 text-slate-400"></i> Money Route</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Payment Method</label><select name="payment_method" id="advancePaymentMethod" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"><option value="">— Select —</option><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="mobile_banking">Mobile Banking (bKash/Nagad)</option><option value="cheque">Cheque</option><option value="card">Card</option></select></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Company Account (money out from)</label><select name="bank_id" id="advanceBankId" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"><option value="">— Select —</option>@include('partials.bank-options', ['banks' => $advanceBanks, 'selected' => null])</select></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Recorded in</label><select name="contra_account_id" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm">@foreach($advanceAccounts as $account)<option value="{{ $account->id }}" @selected((int)$account->id === (int)$advanceDefaultId)>{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Reference No. / TRNX ID</label><input type="text" name="reference" id="advanceReference" maxlength="191" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                        </div>
                        <div id="advanceRecipientFields" class="hidden rounded-lg border border-amber-100 bg-amber-50/60 p-3 mt-3"><p id="advanceRecipientTitle" class="text-xs font-bold uppercase tracking-wide text-amber-700 mb-2">Employee received into</p><div class="grid grid-cols-1 sm:grid-cols-2 gap-3"><div><label id="advanceRecipientBankLabel" class="block text-slate-600 text-xs font-semibold mb-1">Employee Bank Name</label><input type="text" name="recipient_bank" id="advanceRecipientBank" maxlength="150" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div><div><label id="advanceRecipientAccountLabel" class="block text-slate-600 text-xs font-semibold mb-1">Account Number</label><input type="text" name="recipient_account" id="advanceRecipientAccount" maxlength="100" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div></div></div>
                        <p class="text-xs text-slate-500 mt-3"><i class="fas fa-book mr-1"></i> Journal: Debit the selected advance asset, credit the company account above.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-amber-600 text-white hover:bg-amber-700 transition-colors">Give Advance</button>
                </div>
            </form>
        </div>
    </div>
    @endcan @endif

    @if($canAct) @can('create loan')
    <div id="loanModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50" data-close-modal></div>
        <div class="relative bg-white w-11/12 md:max-w-2xl mx-auto rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Give Loan</h3>
                <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form id="loanForm" action="{{ route('role.loans.store', ['role' => $role]) }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" value="{{ $employee->id }}">
                <input type="hidden" name="transaction_time" id="loanTransactionTime">
                <div class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2.5"><i class="fas fa-sack-dollar mr-1 text-slate-400"></i> Loan Details</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Amount <span class="text-red-500">*</span></label><input type="number" step="0.01" min="0.01" name="amount" required placeholder="Enter loan amount" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Monthly Deduction</label><input type="number" step="0.01" min="0" name="monthly_deduction" placeholder="Amount per salary" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Payment Date <span class="text-red-500">*</span></label><input type="date" name="start_date" required value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Custom Time <span class="text-red-500">*</span></label><div class="flex items-center gap-1"><select id="loanHour" required class="w-20 px-2 py-2 bg-white border border-slate-300 rounded-lg text-sm">@for($hour=1;$hour<=12;$hour++)<option value="{{ $hour }}">{{ str_pad($hour,2,'0',STR_PAD_LEFT) }}</option>@endfor</select><span>:</span><select id="loanMinute" required class="w-20 px-2 py-2 bg-white border border-slate-300 rounded-lg text-sm">@for($minute=0;$minute<60;$minute++)<option value="{{ $minute }}">{{ str_pad($minute,2,'0',STR_PAD_LEFT) }}</option>@endfor</select><select id="loanPeriod" required class="w-20 px-2 py-2 bg-white border border-slate-300 rounded-lg text-sm"><option>AM</option><option>PM</option></select></div></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Reason (optional)</label><input type="text" name="reason" maxlength="500" placeholder="Why this loan was given" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2.5"><i class="fas fa-money-bill-transfer mr-1 text-slate-400"></i> Money Route</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Payment Method <span class="text-red-500">*</span></label><select name="payment_method" id="loanPaymentMethod" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"><option value="">— Select payment method —</option><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="mobile_banking">Mobile Banking</option><option value="cheque">Cheque</option><option value="card">Card</option></select></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Company Account (money out from) <span class="text-red-500">*</span></label><select name="bank_id" id="loanBankId" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"><option value="">— Select company account —</option>@include('partials.bank-options', ['banks' => $advanceBanks, 'selected' => null])</select></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Recorded in</label><input value="{{ config('accounts.loan_receivable', '1457') }} · Employee Loan" readonly class="w-full px-3 py-2 bg-slate-100 border border-slate-300 rounded-lg text-sm"></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Reference No. / TRNX ID</label><input type="text" name="reference" id="loanReference" placeholder="Auto-generated if blank" maxlength="191" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                        </div>
                        <div id="loanRecipientFields" class="hidden rounded-lg border border-amber-100 bg-amber-50/60 p-3 mt-3"><p id="loanRecipientTitle" class="text-xs font-bold uppercase tracking-wide text-amber-700 mb-2">Employee received into</p><div class="grid grid-cols-1 sm:grid-cols-2 gap-3"><div><label id="loanRecipientBankLabel" class="block text-slate-600 text-xs font-semibold mb-1">Employee Bank Name</label><input type="text" name="recipient_bank" id="loanRecipientBank" maxlength="150" placeholder="e.g. Dutch-Bangla Bank" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div><div><label id="loanRecipientAccountLabel" class="block text-slate-600 text-xs font-semibold mb-1">Account Number</label><input type="text" name="recipient_account" id="loanRecipientAccount" maxlength="100" placeholder="Employee account number" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div></div></div>
                        <p class="text-xs text-slate-500 mt-3"><i class="fas fa-book mr-1"></i> Journal: Debit Employee Loan, credit the selected company account.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-purple-600 text-white hover:bg-purple-700 transition-colors">Give Loan</button>
                </div>
            </form>
        </div>
    </div>
    @endcan @endif

    @if($canAct) @can('edit payment schedule')
    <div id="reversePaymentModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50" data-close-modal></div>
        <div class="relative bg-white w-11/12 md:max-w-md mx-auto rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 id="reversePaymentTitle" class="text-lg font-semibold text-slate-800">Reverse Employee Payment</h3>
                <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <p class="text-sm text-slate-600 mb-4">Reverse <strong id="reversePaymentReference"></strong> (<span id="reversePaymentAmount"></span>)? The original payment and allocation audit will be preserved.</p>
            <p id="reverseAdvanceWarning" class="hidden text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">Use this only to cancel a mistaken or duplicate advance posting, not to record money returned by the employee. Existing salary payments stay unchanged. Payroll recovery stops; reversal is blocked if payroll already deducted this advance.</p>
            <form id="reversePaymentForm" method="POST">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Reversal Date</label>
                        <input type="date" name="reversal_date" required value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-slate-600 text-sm font-semibold mb-1">Reason</label>
                        <textarea name="reason" required maxlength="500" rows="3" placeholder="Why is this payment being reversed?" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">Confirm Reversal</button>
                </div>
            </form>
        </div>
    </div>

    <div id="payDueModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50" data-close-modal></div>
        <div class="relative bg-white w-11/12 md:max-w-2xl mx-auto rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Pay Due Amount</h3>
                <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <p class="text-xs text-slate-500 mb-3">
                Current due: <span class="font-bold text-red-600">৳ {{ number_format(max($ledgerBalance, 0), abs(max($ledgerBalance, 0) - round(max($ledgerBalance, 0))) < 0.001 ? 0 : 2) }}</span> —
                oldest unpaid salary/encashment months are settled first automatically.
            </p>
            <form id="payDueForm" action="{{ route('role.user.pay-due-amount', ['role' => $role, 'user' => $employee->id]) }}" method="POST">
                @csrf
                <div class="space-y-4">
                    {{-- ── Payment Details ── --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2.5"><i class="fas fa-sack-dollar mr-1 text-slate-400"></i> Payment Details</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-slate-600 text-sm font-semibold mb-1">Amount</label>
                                <input type="number" step="0.01" min="0.01" max="{{ max($ledgerBalance, 0) }}" name="amount" required
                                    value="{{ rtrim(rtrim(number_format(max($ledgerBalance, 0), 2, '.', ''), '0'), '.') }}"
                                    class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-slate-600 text-sm font-semibold mb-1">Payment Date <span class="text-red-500">*</span></label>
                                <input type="date" name="payment_date" required value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-slate-600 text-sm font-semibold mb-1 whitespace-nowrap">Custom Time <span class="text-red-500">*</span></label>
                                <input type="hidden" name="transaction_time" id="payDueTransactionTime">
                                <div class="flex items-center gap-1 flex-nowrap">
                                    <select id="payDueHour" required class="w-16 min-w-0 px-1.5 py-2 bg-white border border-slate-300 rounded-lg text-sm">
                                        <option value="">hh</option>
                                        @for($hour = 1; $hour <= 12; $hour++)<option value="{{ $hour }}">{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}</option>@endfor
                                    </select>
                                    <span class="font-bold text-slate-400">:</span>
                                    <select id="payDueMinute" required class="w-16 min-w-0 px-1.5 py-2 bg-white border border-slate-300 rounded-lg text-sm">
                                        <option value="">mm</option>
                                        @for($minute = 0; $minute < 60; $minute++)<option value="{{ $minute }}">{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}</option>@endfor
                                    </select>
                                    <select id="payDuePeriod" required class="w-16 min-w-0 px-1.5 py-2 bg-white border border-slate-300 rounded-lg text-sm">
                                        <option value="AM">AM</option>
                                        <option value="PM">PM</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="block text-slate-600 text-sm font-semibold mb-1">Allocate to</label>
                            <select name="employee_salary_id" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm">
                                <option value="">Oldest unpaid payslip first (automatic)</option>
                                @foreach($payslips->where('document_type', 'salary')->where('due_amount', '>', 0) as $salaryOption)
                                    <option value="{{ $salaryOption->id }}">{{ \Carbon\Carbon::createFromDate($salaryOption->year, $salaryOption->month, 1)->format('F Y') }} — due ৳ {{ number_format($salaryOption->due_amount, abs((float) $salaryOption->due_amount - round((float) $salaryOption->due_amount)) < 0.001 ? 0 : 2) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- ── Money Route ── --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2.5"><i class="fas fa-money-bill-transfer mr-1 text-slate-400"></i> Money Route</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-600 text-sm font-semibold mb-1">Payment Method</label>
                                <select name="payment_method" id="payDuePaymentMethod" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">— Select —</option>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_banking">Mobile Banking (bKash/Nagad)</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="card">Card</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-slate-600 text-sm font-semibold mb-1">Company Account (money out from)</label>
                                <select name="bank_id" id="payDueBankId" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">— Select —</option>
                                    @include('partials.bank-options', ['banks' => $banks, 'selected' => null])
                                </select>
                            </div>
                        </div>
                        <div id="payDueRecipientBankFields" class="hidden rounded-lg border border-blue-100 bg-blue-50/60 p-3 mt-3">
                            <p id="payDueRecipientBankTitle" class="text-xs font-bold uppercase tracking-wide text-blue-700 mb-2"><i class="fas fa-building-columns mr-1"></i> Employee received into</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label id="payDueRecipientBankLabel" class="block text-slate-600 text-xs font-semibold mb-1">Employee Bank Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="recipient_bank" id="payDueRecipientBank" maxlength="150" placeholder="e.g. Dutch-Bangla Bank" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label id="payDueRecipientAccountLabel" class="block text-slate-600 text-xs font-semibold mb-1">Account Number <span class="text-red-500">*</span></label>
                                    <input type="text" name="recipient_account" id="payDueRecipientAccount" maxlength="100" placeholder="Employee account number" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                            <div>
                                <label class="block text-slate-600 text-sm font-semibold mb-1">Reference No. / TRNX ID</label>
                                <input type="text" name="reference" id="payDueReference" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-slate-600 text-sm font-semibold mb-1">Note (optional)</label>
                                <input type="text" name="note" id="payDueNote" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
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
        <div class="relative bg-white w-11/12 md:max-w-2xl mx-auto rounded-xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Pay Bonus</h3>
                <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form id="bonusForm" action="{{ route('role.user.ledger.bonus', ['role' => $role, 'user' => $employee->id]) }}" method="POST">
                @csrf
                <input type="hidden" name="transaction_time" id="bonusTransactionTime">
                <div class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2.5"><i class="fas fa-gift mr-1 text-slate-400"></i> Bonus Details</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Amount <span class="text-red-500">*</span></label><input type="number" step="0.01" min="0.01" name="amount" required placeholder="Enter bonus amount" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Payment Date <span class="text-red-500">*</span></label><input type="date" name="entry_date" required value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Custom Time <span class="text-red-500">*</span></label><div class="flex items-center gap-1"><select id="bonusHour" required class="w-20 px-2 py-2 bg-white border border-slate-300 rounded-lg text-sm">@for($hour=1;$hour<=12;$hour++)<option value="{{ $hour }}">{{ str_pad($hour,2,'0',STR_PAD_LEFT) }}</option>@endfor</select><span>:</span><select id="bonusMinute" required class="w-20 px-2 py-2 bg-white border border-slate-300 rounded-lg text-sm">@for($minute=0;$minute<60;$minute++)<option value="{{ $minute }}">{{ str_pad($minute,2,'0',STR_PAD_LEFT) }}</option>@endfor</select><select id="bonusPeriod" required class="w-20 px-2 py-2 bg-white border border-slate-300 rounded-lg text-sm"><option>AM</option><option>PM</option></select></div></div>
                        </div>
                        <div class="mt-3"><label class="block text-slate-600 text-sm font-semibold mb-1">Reason (optional)</label><input type="text" name="reason" maxlength="255" placeholder="e.g. Eid bonus or performance bonus" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2.5"><i class="fas fa-money-bill-transfer mr-1 text-slate-400"></i> Money Route</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Payment Method <span class="text-red-500">*</span></label><select name="payment_method" id="bonusPaymentMethod" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"><option value="">— Select payment method —</option><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="mobile_banking">Mobile Banking</option><option value="cheque">Cheque</option><option value="card">Card</option></select></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Company Account (money out from) <span class="text-red-500">*</span></label><select name="bank_id" id="bonusBankId" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"><option value="">— Select company account —</option>@include('partials.bank-options', ['banks' => $advanceBanks, 'selected' => null])</select></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Recorded in</label><input value="{{ config('accounts.salary_expense') }} · Salary Expense" readonly class="w-full px-3 py-2 bg-slate-100 border border-slate-300 rounded-lg text-sm"></div>
                            <div><label class="block text-slate-600 text-sm font-semibold mb-1">Reference No. / TRNX ID</label><input type="text" name="reference" id="bonusReference" maxlength="191" placeholder="Auto-generated if blank" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div>
                        </div>
                        <div id="bonusRecipientFields" class="hidden rounded-lg border border-amber-100 bg-amber-50/60 p-3 mt-3"><p id="bonusRecipientTitle" class="text-xs font-bold uppercase tracking-wide text-amber-700 mb-2">Employee received into</p><div class="grid grid-cols-1 sm:grid-cols-2 gap-3"><div><label id="bonusRecipientBankLabel" class="block text-slate-600 text-xs font-semibold mb-1">Employee Bank Name</label><input type="text" name="recipient_bank" id="bonusRecipientBank" maxlength="150" placeholder="e.g. Dutch-Bangla Bank" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div><div><label id="bonusRecipientAccountLabel" class="block text-slate-600 text-xs font-semibold mb-1">Account Number</label><input type="text" name="recipient_account" id="bonusRecipientAccount" maxlength="100" placeholder="Employee account number" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm"></div></div></div>
                        <p class="text-xs text-slate-500 mt-3"><i class="fas fa-book mr-1"></i> Journal: Debit Salary Expense, credit the selected company account.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-green-600 text-white hover:bg-green-700 transition-colors">Pay Bonus</button>
                </div>
            </form>
        </div>
    </div>
    @endcan @endif

    <div id="employeeLedgerPrintModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50" data-close-modal></div>
        <div class="relative bg-white w-11/12 max-w-md mx-auto rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div><h3 class="text-xl font-bold text-slate-800">Print transactions</h3><p class="text-xs text-slate-500 mt-0.5">Choose which ledger transactions to print</p></div>
                <button type="button" data-close-modal class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"><i class="fas fa-times"></i></button>
            </div>
            <form id="employeeLedgerPrintForm" method="GET" target="_blank" action="{{ route('role.user.salary-transaction-report.print', ['role' => $role, 'user' => $employee->id]) }}" class="p-5">
                <input type="hidden" name="current_q" value="{{ request('ledger_q') }}">
                <input type="hidden" name="current_from" value="{{ request('ledger_from') }}">
                <input type="hidden" name="current_to" value="{{ request('ledger_to') }}">
                <div class="space-y-2.5">
                    @foreach([
                        'today' => ['Today', now()->format('d/m/Y')],
                        'yesterday' => ['Yesterday', now()->subDay()->format('d/m/Y')],
                    ] as $scope => $printOption)
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:border-blue-300 hover:bg-blue-50/40">
                            <input type="radio" name="print_scope" value="{{ $scope }}" class="mt-1">
                            <span><span class="block text-sm font-semibold text-slate-700">{{ $printOption[0] }}</span><span class="block text-xs text-slate-400 mt-0.5">{{ $printOption[1] }}</span></span>
                        </label>
                    @endforeach
                    <label class="block p-3 rounded-xl border border-slate-200 cursor-pointer hover:border-blue-300 hover:bg-blue-50/40">
                        <span class="flex items-center gap-3"><input type="radio" name="print_scope" value="day"><span class="text-sm font-semibold text-slate-700">A specific day</span></span>
                        <input type="date" name="print_day" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="mt-2 ml-6 px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </label>
                    <label class="block p-3 rounded-xl border border-slate-200 cursor-pointer hover:border-blue-300 hover:bg-blue-50/40">
                        <span class="flex items-center gap-3"><input type="radio" name="print_scope" value="range"><span class="text-sm font-semibold text-slate-700">A date range</span></span>
                        <span class="mt-2 ml-6 flex items-center gap-2"><input type="date" name="print_from" value="{{ now()->startOfMonth()->toDateString() }}" max="{{ now()->toDateString() }}" class="min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm"><span class="text-xs text-slate-400">to</span><input type="date" name="print_to" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm"></span>
                    </label>
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-blue-300 bg-blue-50/50 cursor-pointer">
                        <input type="radio" name="print_scope" value="current" class="mt-1" checked>
                        <span><span class="block text-sm font-semibold text-slate-700">What the table shows now</span><span class="block text-xs text-slate-400 mt-0.5">Uses the current search and date filters</span></span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 mt-5 pt-4 border-t border-slate-100">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700"><i class="fas fa-print mr-1.5"></i>Print</button>
                </div>
            </form>
        </div>
    </div>
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
            } else if (@json($isEmployeeLedgerRoute)) {
                activateTab('accounts');
            }

            const employeePicker = document.getElementById('employeeLedgerPicker');
            if (employeePicker) {
                const ledgerUrl = @json(route('role.employee-ledger.show', ['role' => $role, 'user' => '__EMPLOYEE__']));
                employeePicker.addEventListener('change', function() {
                    window.location.href = ledgerUrl.replace('__EMPLOYEE__', encodeURIComponent(this.value));
                });
            }

            const sendUrl = @json($isEmployeeLedgerRoute ? route('role.employee-ledger.send', ['role' => $role, 'user' => $employee->id]) : null);
            document.querySelectorAll('[data-ledger-send]').forEach(function(button) {
                button.addEventListener('click', async function() {
                    const channel = button.dataset.ledgerSend;
                    const original = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending';
                    try {
                        const response = await fetch(sendUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ channel }),
                        });
                        const data = await response.json();
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not send the statement.');
                        window.Swal ? Swal.fire({icon:'success', title:'Sent', text:data.message}) : alert(data.message);
                    } catch (error) {
                        window.Swal ? Swal.fire({icon:'error', title:'Not sent', text:error.message}) : alert(error.message);
                    } finally {
                        button.disabled = false;
                        button.innerHTML = original;
                    }
                });
            });

            const whatsappButton = document.getElementById('employeeLedgerWhatsApp');
            if (whatsappButton) {
                whatsappButton.addEventListener('click', function() {
                    let phone = @json((string) ($employee->phone ?? ''));
                    phone = phone.replace(/\D/g, '');
                    if (phone.startsWith('0')) phone = '88' + phone;
                    if (!phone) {
                        window.Swal ? Swal.fire({icon:'warning', title:'No phone number', text:'Add a phone number to the employee profile first.'}) : alert('No phone number found.');
                        return;
                    }
                    const balance = @json(number_format(abs($ledgerBalance), 2));
                    const position = @json($ledgerBalance > 0 ? 'company owes' : ($ledgerBalance < 0 ? 'advance/overpaid' : 'settled'));
                    const employeeName = @json($employee->name);
                    const text = `Dear ${employeeName}, your employee account statement balance is Tk ${balance} (${position}). Please contact accounts if you need the full PDF statement.`;
                    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, '_blank', 'noopener');
                });
            }
        })();

        // ── Quick-action modals (Advance / Loan / Bonus) ──────────────────
        (function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            document.querySelectorAll('[data-open-modal]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const modalId = btn.getAttribute('data-open-modal');
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    }
                    // Custom Time was required and left blank on every open —
                    // defaulted to now, same as the date beside it; still fully
                    // editable for a payment recorded after the fact. Only
                    // payDueModal carries a time picker.
                    if (modalId === 'payDueModal') {
                        const now = new Date();
                        const hour24 = now.getHours();
                        document.getElementById('payDueHour').value = String((hour24 % 12) || 12);
                        document.getElementById('payDueMinute').value = String(now.getMinutes());
                        document.getElementById('payDuePeriod').value = hour24 < 12 ? 'AM' : 'PM';

                        // Reference No. was left for the person to type. Pre-fills
                        // the same 'SAL-{YmdHis}' shape the salary Mark Paid/Pay Now
                        // modals now preview — still a normal editable field. Note
                        // stays genuinely blank; it is a separate free-text remark.
                        const refField = document.getElementById('payDueReference');
                        if (refField && !refField.value) {
                            const pad2 = n => String(n).padStart(2, '0');
                            const ymdhis = now.getFullYear() + pad2(now.getMonth() + 1) + pad2(now.getDate())
                                + pad2(now.getHours()) + pad2(now.getMinutes()) + pad2(now.getSeconds());
                            refField.value = 'SAL-' + ymdhis;
                        }

                        filterPayDueBanks();
                        syncPayDueRouteFields();
                    }
                    if (modalId === 'advanceModal') {
                        const now = new Date();
                        const hour24 = now.getHours();
                        document.getElementById('advanceHour').value = String((hour24 % 12) || 12);
                        document.getElementById('advanceMinute').value = String(now.getMinutes());
                        document.getElementById('advancePeriod').value = hour24 < 12 ? 'AM' : 'PM';
                        const ref = document.getElementById('advanceReference');
                        if (ref && !ref.value) ref.value = 'ADV-' + transactionStamp(now);
                        filterAdvanceBanks();
                        syncAdvanceRouteFields();
                    }
                    if (modalId === 'loanModal') {
                        const now = new Date();
                        const hour24 = now.getHours();
                        document.getElementById('loanHour').value = String((hour24 % 12) || 12);
                        document.getElementById('loanMinute').value = String(now.getMinutes());
                        document.getElementById('loanPeriod').value = hour24 < 12 ? 'AM' : 'PM';
                        const ref = document.getElementById('loanReference');
                        if (ref && !ref.value) ref.value = 'LOAN-' + transactionStamp(now);
                        filterLoanBanks();
                        syncLoanRouteFields();
                    }
                    if (modalId === 'bonusModal') {
                        const now = new Date();
                        const hour24 = now.getHours();
                        document.getElementById('bonusHour').value = String((hour24 % 12) || 12);
                        document.getElementById('bonusMinute').value = String(now.getMinutes());
                        document.getElementById('bonusPeriod').value = hour24 < 12 ? 'AM' : 'PM';
                        const ref = document.getElementById('bonusReference');
                        if (ref && !ref.value) ref.value = 'BONUS-' + transactionStamp(now);
                        filterBonusBanks();
                        syncBonusRouteFields();
                    }
                });
            });

            function transactionStamp(date) {
                const pad2 = n => String(n).padStart(2, '0');
                return date.getFullYear() + pad2(date.getMonth() + 1) + pad2(date.getDate())
                    + pad2(date.getHours()) + pad2(date.getMinutes()) + pad2(date.getSeconds());
            }

            // The bank picker holds every company account regardless of kind —
            // narrow it to what the chosen payment method could actually move
            // through (a bKash payment cannot leave a current account), and for
            // Cash specifically there is only ever one right answer, so pick it
            // rather than make the person choose it every time.
            const payDueTypesByMethod = {
                cash: ['cash'],
                bank_transfer: ['bank'],
                cheque: ['bank'],
                card: ['bank'],
                mobile_banking: ['mobile_banking', 'digital_wallet'],
            };
            function filterPayDueBanks() {
                const method = document.getElementById('payDuePaymentMethod')?.value;
                const bankSelect = document.getElementById('payDueBankId');
                if (!bankSelect) return;
                const wantedTypes = payDueTypesByMethod[method] || null;

                let firstVisible = null;
                [...bankSelect.options].forEach(function (option) {
                    if (!option.value) return;
                    const hidden = !!wantedTypes && !wantedTypes.includes(option.dataset.type || '');
                    option.hidden = hidden;
                    option.disabled = hidden;
                    if (!hidden && !firstVisible) firstVisible = option;
                });
                bankSelect.querySelectorAll('optgroup').forEach(function (group) {
                    const hasVisible = [...group.querySelectorAll('option')].some(o => !o.hidden);
                    group.hidden = !hasVisible;
                    group.disabled = !hasVisible;
                });
                if (bankSelect.selectedOptions[0]?.hidden) bankSelect.value = '';

                if (method === 'cash' && firstVisible) {
                    // Prefer the account actually named "Office Cash"; fall back
                    // to whichever cash-type account is first if this company's
                    // is named something else.
                    const officeCash = [...bankSelect.options].find(function (o) {
                        return !o.hidden && /office\s*cash/i.test(o.textContent);
                    });
                    bankSelect.value = (officeCash || firstVisible).value;
                }
            }
            document.getElementById('payDuePaymentMethod')?.addEventListener('change', filterPayDueBanks);

            function filterAdvanceBanks() {
                const method = document.getElementById('advancePaymentMethod')?.value;
                const bankSelect = document.getElementById('advanceBankId');
                if (!bankSelect) return;
                const wantedTypes = payDueTypesByMethod[method] || null;
                let firstVisible = null;
                [...bankSelect.options].forEach(function(option) {
                    if (!option.value) return;
                    const hidden = !!wantedTypes && !wantedTypes.includes(option.dataset.type || '');
                    option.hidden = hidden;
                    option.disabled = hidden;
                    if (!hidden && !firstVisible) firstVisible = option;
                });
                bankSelect.querySelectorAll('optgroup').forEach(function(group) {
                    const visible = [...group.querySelectorAll('option')].some(option => !option.hidden);
                    group.hidden = !visible;
                    group.disabled = !visible;
                });
                if (bankSelect.selectedOptions[0]?.hidden) bankSelect.value = '';
                if (method === 'cash' && firstVisible) {
                    const officeCash = [...bankSelect.options].find(option => !option.hidden && /office\s*cash/i.test(option.textContent));
                    bankSelect.value = (officeCash || firstVisible).value;
                }
            }
            document.getElementById('advancePaymentMethod')?.addEventListener('change', filterAdvanceBanks);

            function filterLoanBanks() {
                const method = document.getElementById('loanPaymentMethod')?.value;
                const bankSelect = document.getElementById('loanBankId');
                if (!bankSelect) return;
                const wantedTypes = payDueTypesByMethod[method] || null;
                let firstVisible = null;
                [...bankSelect.options].forEach(function(option) {
                    if (!option.value) return;
                    const hidden = !!wantedTypes && !wantedTypes.includes(option.dataset.type || '');
                    option.hidden = hidden;
                    option.disabled = hidden;
                    if (!hidden && !firstVisible) firstVisible = option;
                });
                bankSelect.querySelectorAll('optgroup').forEach(function(group) {
                    const visible = [...group.querySelectorAll('option')].some(option => !option.hidden);
                    group.hidden = !visible;
                    group.disabled = !visible;
                });
                if (bankSelect.selectedOptions[0]?.hidden) bankSelect.value = '';
                if (method === 'cash' && firstVisible) {
                    const officeCash = [...bankSelect.options].find(option => !option.hidden && /office\s*cash/i.test(option.textContent));
                    bankSelect.value = (officeCash || firstVisible).value;
                }
            }
            document.getElementById('loanPaymentMethod')?.addEventListener('change', filterLoanBanks);

            function filterBonusBanks() {
                const method = document.getElementById('bonusPaymentMethod')?.value;
                const bankSelect = document.getElementById('bonusBankId');
                if (!bankSelect) return;
                const wantedTypes = payDueTypesByMethod[method] || null;
                let firstVisible = null;
                [...bankSelect.options].forEach(function(option) {
                    if (!option.value) return;
                    const hidden = !!wantedTypes && !wantedTypes.includes(option.dataset.type || '');
                    option.hidden = hidden; option.disabled = hidden;
                    if (!hidden && !firstVisible) firstVisible = option;
                });
                bankSelect.querySelectorAll('optgroup').forEach(function(group) {
                    const visible = [...group.querySelectorAll('option')].some(option => !option.hidden);
                    group.hidden = !visible; group.disabled = !visible;
                });
                if (bankSelect.selectedOptions[0]?.hidden) bankSelect.value = '';
                if (method === 'cash' && firstVisible) {
                    const officeCash = [...bankSelect.options].find(option => !option.hidden && /office\s*cash/i.test(option.textContent));
                    bankSelect.value = (officeCash || firstVisible).value;
                }
            }

            document.querySelectorAll('[data-close-modal]').forEach(function(el) {
                el.addEventListener('click', function() {
                    const modal = el.closest('.fixed.inset-0');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                });
            });

            @php
                $employeePaymentDateRoute = route('role.user.ledger.update-payment-date-time', [
                    'role' => $role,
                    'user' => $employee->id,
                    'entry' => '__ENTRY__',
                ]);
            @endphp
            const employeePaymentDateRoute = {{ Illuminate\Support\Js::from($employeePaymentDateRoute) }};
            const employeePaymentCsrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const employeeLedgerReversalHistory = {{ Illuminate\Support\Js::from($ledgerReversalHistory) }};

            function escapeEmployeePaymentHtml(value) {
                return String(value ?? '').replace(/[&<>'"]/g, function(char) {
                    return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char];
                });
            }

            function openEmployeeLedgerReversalHistory() {
                const money = function(value) {
                    return new Intl.NumberFormat('en-BD', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(Number(value || 0));
                };
                const cards = employeeLedgerReversalHistory.map(function(group) {
                    const rows = group.rows.map(function(row) {
                        return `<div class="elrh-row"><div><b>${escapeEmployeePaymentHtml(row.type)}</b><small>${escapeEmployeePaymentHtml(row.date)}${row.time ? ` · ${escapeEmployeePaymentHtml(row.time)}` : ''}</small></div><div><small>Reference</small><span>${escapeEmployeePaymentHtml(row.reference || '—')}</span></div><div class="elrh-money debit"><small>Debit</small><b>${Number(row.debit) > 0 ? `৳${money(row.debit)}` : '—'}</b></div><div class="elrh-money credit"><small>Credit</small><b>${Number(row.credit) > 0 ? `৳${money(row.credit)}` : '—'}</b></div>${row.note ? `<p>${escapeEmployeePaymentHtml(row.note)}</p>` : ''}</div>`;
                    }).join('');
                    return `<details class="elrh-card"><summary><span><i class="fas fa-caret-right"></i><b>${escapeEmployeePaymentHtml(group.title)}</b></span><span>৳${money(group.amount)}</span></summary><div class="elrh-body">${rows}</div></details>`;
                }).join('');

                Swal.fire({
                    title: 'Reversal history',
                    width: 780,
                    html: `<style>
                        .elrh-list{max-height:62vh;overflow-y:auto;padding:2px 7px 2px 1px;text-align:left}.elrh-card{margin-bottom:9px;border:1px solid #c7d2fe;border-radius:10px;background:#fff;overflow:hidden}.elrh-card summary{min-height:43px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;cursor:pointer;color:#475569;font-size:12px;list-style:none}.elrh-card summary::-webkit-details-marker{display:none}.elrh-card summary>span{display:flex;align-items:center;gap:8px}.elrh-card summary i{transition:transform .16s}.elrh-card[open] summary{background:#f5f7ff;color:#312e81}.elrh-card[open] summary i{transform:rotate(90deg)}.elrh-body{border-top:1px solid #e2e8f0}.elrh-row{display:grid;grid-template-columns:1.15fr 1.35fr .65fr .65fr;gap:10px;align-items:center;padding:10px 12px;border-top:1px solid #f1f5f9;font-size:10px}.elrh-row:first-child{border-top:0}.elrh-row>div{min-width:0}.elrh-row small{display:block;margin-bottom:2px;color:#94a3b8;font-size:8px;font-weight:800;text-transform:uppercase}.elrh-row span,.elrh-row b{overflow-wrap:anywhere}.elrh-row p{grid-column:1/-1;margin:0;padding:7px 8px;border-radius:7px;background:#f8fafc;color:#64748b;line-height:1.45}.elrh-money{text-align:right}.elrh-money.debit b{color:#b91c1c}.elrh-money.credit b{color:#15803d}.elrh-empty{padding:34px;text-align:center;color:#94a3b8}.elrh-swal{border-radius:14px!important}.elrh-swal .swal2-html-container{margin:12px 0 0!important}.elrh-swal .swal2-confirm{border-radius:8px!important;background:#64748b!important;font-size:11px!important;font-weight:800!important}@media(max-width:650px){.elrh-row{grid-template-columns:1fr 1fr}.elrh-row p{grid-column:1/-1}}
                    </style><div class="elrh-list">${cards || '<div class="elrh-empty"><i class="fas fa-rotate-left"></i><p>No reversal history yet.</p></div>'}</div>`,
                    customClass: {popup: 'elrh-swal'},
                    confirmButtonText: 'Close',
                    showCloseButton: true,
                    allowOutsideClick: false,
                });
            }

            document.querySelector('[data-ledger-reversal-history]')?.addEventListener('click', openEmployeeLedgerReversalHistory);

            async function postEmployeePaymentCorrection(url, payload) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': employeePaymentCsrf,
                    },
                    body: JSON.stringify(payload),
                });
                const result = await response.json().catch(function() {
                    return {success: false, message: 'The server returned an unreadable response.'};
                });
                if (!response.ok || !result.success) {
                    throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || 'Correction failed.');
                }
                return result;
            }

            function openEmployeePaymentCorrection(button) {
                const row = button.dataset;
                let action = null;
                const formatter = new Intl.NumberFormat('en-BD', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const principal = Number(row.amount || 0);
                const charge = Number(row.charge || 0);
                const money = formatter.format(principal);
                const chargeMoney = formatter.format(charge);
                const bankCredit = formatter.format(principal + charge);
                const header = `
                    <div class="elcw-header">
                        <div class="elcw-header-copy"><small>Employee ledger correction workspace</small><b>${escapeEmployeePaymentHtml(row.reference)}</b><span>${escapeEmployeePaymentHtml(row.type)} · ${escapeEmployeePaymentHtml(row.method)}</span></div>
                        <span class="elcw-status">Posted</span>
                    </div>`;
                const back = '<button type="button" class="elcw-back"><i class="fas fa-arrow-left"></i> Back to overview</button>';

                Swal.fire({
                    width: 790,
                    title: '',
                    html: `<style>
                        .elcw{text-align:left;color:#0f172a}.elcw *{box-sizing:border-box}.elcw-header{display:flex;align-items:flex-start;gap:12px;padding:2px 2px 14px;margin-bottom:12px;border-bottom:1px solid #e2e8f0}.elcw-header-copy{display:flex;min-width:0;flex:1;flex-direction:column}.elcw-header-copy small{font-size:9px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#2563eb}.elcw-header-copy b{margin-top:4px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:17px;font-weight:700;color:#0f172a}.elcw-header-copy span{margin-top:4px;font-size:10px;color:#64748b}.elcw-status{margin-right:32px;padding:5px 9px;border-radius:999px;background:#dcfce7;color:#166534;font-size:9px;font-weight:900;text-transform:uppercase}.elcw-panel{animation:elcwIn .18s ease}.elcw-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:7px;margin-bottom:12px}.elcw-summary>div{min-height:52px;padding:9px 10px;border:1px solid #dbe4ef;border-radius:9px;background:#f8fafc}.elcw-summary small,.elcw-section-title{display:block;font-size:8px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#64748b}.elcw-summary b{display:block;margin-top:4px;font-size:11px;color:#0f172a;overflow-wrap:anywhere}.elcw-posting{margin-bottom:11px;overflow:hidden;border:1px solid #dbe4ef;border-radius:10px}.elcw-posting>strong{display:block;padding:8px 10px;background:#eff4f9;font-size:10px}.elcw-posting table{width:100%;border-collapse:collapse}.elcw-posting th{padding:7px 9px;color:#64748b;font-size:8px;text-transform:uppercase;text-align:left}.elcw-posting th:nth-child(n+2),.elcw-posting td:nth-child(n+2){text-align:right}.elcw-posting td{padding:8px 9px;border-top:1px solid #e2e8f0;font-size:10px}.elcw-posting tfoot td{font-weight:900;background:#f8fafc}.elcw-actions-wrap{padding:11px;border:1px solid #dbe4ef;border-radius:10px;background:#f8fafc}.elcw-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:8px}.elcw-choice{min-height:68px;display:flex;align-items:flex-start;gap:10px;padding:11px;border:1px solid #dbe4ef;border-radius:9px;background:#fff;text-align:left;cursor:pointer;transition:.16s ease}.elcw-choice:hover{border-color:#60a5fa;box-shadow:0 6px 15px rgba(37,99,235,.09);transform:translateY(-1px)}.elcw-choice.danger{border-color:#fecaca}.elcw-choice.danger:hover{border-color:#f87171}.elcw-choice>i{width:29px;height:29px;display:grid;place-items:center;flex:0 0 29px;border-radius:8px;background:#dbeafe;color:#1d4ed8}.elcw-choice.danger>i{background:#fee2e2;color:#b91c1c}.elcw-choice b{display:block;font-size:11px;color:#1e293b}.elcw-choice small{display:block;margin-top:3px;font-size:9px;line-height:1.4;color:#64748b}.elcw-back{display:inline-flex;align-items:center;gap:7px;padding:0 0 11px;border:0;background:transparent;color:#2563eb;font-size:11px;font-weight:800;cursor:pointer}.elcw-heading{display:flex;align-items:flex-start;gap:11px;padding:12px 13px;margin-bottom:11px;border:1px solid #dbeafe;border-radius:10px;background:#f8fbff}.elcw-heading.danger{border-color:#fecaca;background:#fff7f7}.elcw-heading i{width:34px;height:34px;display:grid;place-items:center;flex:0 0 34px;border-radius:9px;background:#dbeafe;color:#1d4ed8}.elcw-heading.danger i{background:#fee2e2;color:#b91c1c}.elcw-heading b{display:block;font-size:13px}.elcw-heading small{display:block;margin-top:3px;font-size:10px;line-height:1.45;color:#64748b}.elcw-card{padding:14px;border:1px solid #dbe4ef;border-radius:10px;background:#fff}.elcw-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.elcw-field.full{grid-column:1/-1}.elcw-field label{display:block;margin-bottom:6px;font-size:9px;font-weight:900;letter-spacing:.06em;text-transform:uppercase;color:#475569}.elcw-field input,.elcw-field textarea{width:100%;min-height:41px;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;font:inherit;font-size:12px;outline:none}.elcw-field textarea{min-height:76px;resize:vertical}.elcw-field input:focus,.elcw-field textarea:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}.elcw-warning{display:flex;gap:8px;padding:10px 11px;margin-bottom:12px;border:1px solid #fecaca;border-radius:9px;background:#fff1f2;color:#991b1b;font-size:10px;line-height:1.5}.elcw-confirm{margin-top:12px;padding:11px;border:1px dashed #93c5fd;border-radius:9px;background:#eff6ff}.elcw-confirm.danger{border-color:#fca5a5;background:#fff7f7}.elcw-confirm label{display:flex;justify-content:space-between;margin-bottom:6px;color:#1e40af;font-size:10px;font-weight:800}.elcw-confirm.danger label{color:#991b1b}.elcw-confirm code{padding:2px 6px;border-radius:4px;background:#dbeafe}.elcw-confirm.danger code{background:#fee2e2}.elcw-swal{border-radius:14px!important;padding:19px 22px 16px!important}.elcw-swal .swal2-html-container{margin:0!important}.elcw-swal .swal2-close{top:12px!important;right:12px!important;color:#94a3b8!important}.elcw-swal .swal2-actions{width:100%;justify-content:flex-end;margin:14px 0 0!important;padding-top:13px;border-top:1px solid #e2e8f0}.elcw-swal .swal2-confirm{min-height:38px;border-radius:8px!important;background:#2563eb!important;font-size:11px!important;font-weight:800!important}.elcw-swal .swal2-confirm.elcw-danger{background:#dc2626!important}@keyframes elcwIn{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}@media(max-width:700px){.elcw-summary{grid-template-columns:repeat(2,1fr)}.elcw-actions,.elcw-grid{grid-template-columns:1fr}}
                    </style><div class="elcw">${header}
                        <div id="elcwOverview" class="elcw-panel">
                            <div class="elcw-summary"><div><small>Principal</small><b>৳${money}</b></div><div><small>Charge</small><b>৳${chargeMoney}</b></div><div><small>Paid via</small><b>${escapeEmployeePaymentHtml(row.method)}</b></div><div><small>Money account</small><b>${escapeEmployeePaymentHtml(row.bank)}</b></div><div><small>Date & time</small><b>${escapeEmployeePaymentHtml(row.date)}<br>${escapeEmployeePaymentHtml(row.time)}</b></div></div>
                            <div class="elcw-posting"><strong>Current ledger posting</strong><table><thead><tr><th>Ledger / source</th><th>Debit</th><th>Credit</th></tr></thead><tbody><tr><td>${escapeEmployeePaymentHtml(row.type)}</td><td>${Number(row.debit || 0) > 0 ? `৳${formatter.format(Number(row.debit))}` : '—'}</td><td>${Number(row.credit || 0) > 0 ? `৳${formatter.format(Number(row.credit))}` : '—'}</td></tr></tbody><tfoot><tr><td>Recorded movement</td><td>${Number(row.debit || 0) > 0 ? `৳${formatter.format(Number(row.debit))}` : '—'}</td><td>${Number(row.credit || 0) > 0 ? `৳${formatter.format(Number(row.credit))}` : '—'}</td></tr></tfoot></table></div>
                            <div class="elcw-actions-wrap"><span class="elcw-section-title">Choose a correction</span><div class="elcw-actions" style="grid-template-columns:${row.reverseUrl ? '1fr 1fr' : '1fr'}"><button type="button" data-elcw="date" class="elcw-choice"><i class="fas fa-calendar-check"></i><span><b>Date & time</b><small>Correct posting order without changing money.</small></span></button>${row.reverseUrl ? '<button type="button" data-elcw="reverse" class="elcw-choice danger"><i class="fas fa-rotate-left"></i><span><b>Reverse</b><small>Post an equal-and-opposite audited entry.</small></span></button>' : ''}</div></div>
                        </div>
                        <div id="elcwDate" class="elcw-panel" style="display:none">${back}<div class="elcw-heading"><i class="fas fa-calendar-day"></i><span><b>Correct date & time</b><small>Employee ledger, payment source and journal chronology update together.</small></span></div><div class="elcw-card"><div class="elcw-grid"><div class="elcw-field"><label>Posting date *</label><input id="elcwDateInput" type="date" value="${escapeEmployeePaymentHtml(row.date)}"></div><div class="elcw-field"><label>Posting time *</label><input id="elcwTimeInput" type="time" value="${escapeEmployeePaymentHtml(row.time)}"></div><div class="elcw-field full"><label>Correction reason *</label><textarea id="elcwDateReason" maxlength="500" placeholder="Explain why the date or time is being corrected"></textarea></div></div><div class="elcw-confirm"><label><span>Confirm date & time correction *</span><span>Type <code>CONFIRM</code></span></label><div class="elcw-field"><input id="elcwDateConfirm" autocomplete="off" placeholder="Type CONFIRM to save"></div></div></div></div>
                        <div id="elcwReverse" class="elcw-panel" style="display:none">${back}<div class="elcw-heading danger"><i class="fas fa-triangle-exclamation"></i><span><b>Reverse employee payment</b><small>The original remains visible and the opposite journal restores salary due and money-account balance.</small></span></div><div class="elcw-card"><div class="elcw-warning"><i class="fas fa-shield-halved"></i><span>This is a permanent accounting action. Payment allocations are reopened automatically.</span></div><div class="elcw-grid"><div class="elcw-field"><label>Reversal date *</label><input id="elcwReverseDate" type="date" value="{{ now()->toDateString() }}"></div><div class="elcw-field full"><label>Reversal reason *</label><textarea id="elcwReverseReason" maxlength="500" placeholder="Explain why this payment must be reversed"></textarea></div></div><div class="elcw-confirm danger"><label><span>Final confirmation *</span><span>Type <code>CONFIRM</code></span></label><div class="elcw-field"><input id="elcwConfirm" autocomplete="off" placeholder="Type CONFIRM to reverse"></div></div></div></div>
                    </div>`,
                    customClass: {popup: 'elcw-swal'},
                    showCancelButton: false,
                    showConfirmButton: true,
                    showCloseButton: true,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    confirmButtonText: 'Save correction',
                    didOpen: function() {
                        const confirm = Swal.getConfirmButton();
                        const overview = document.getElementById('elcwOverview');
                        confirm.style.display = 'inline-flex';
                        confirm.textContent = 'Close';
                        const openPanel = function(name) {
                            action = name;
                            overview.style.display = 'none';
                            document.querySelectorAll('.elcw-panel').forEach(function(panel) { panel.style.display = 'none'; });
                            document.getElementById(name === 'date' ? 'elcwDate' : 'elcwReverse').style.display = 'block';
                            confirm.style.display = 'inline-flex';
                            confirm.textContent = name === 'reverse' ? 'Reverse payment' : 'Save date & time';
                            confirm.classList.toggle('elcw-danger', name === 'reverse');
                            Swal.resetValidationMessage();
                        };
                        document.querySelectorAll('[data-elcw]').forEach(function(card) {
                            card.addEventListener('click', function() { openPanel(card.dataset.elcw); });
                        });
                        document.querySelectorAll('.elcw-back').forEach(function(backButton) {
                            backButton.addEventListener('click', function() {
                                action = null;
                                document.querySelectorAll('.elcw-panel').forEach(function(panel) { panel.style.display = 'none'; });
                                overview.style.display = 'block';
                                confirm.style.display = 'inline-flex';
                                confirm.textContent = 'Close';
                                confirm.classList.remove('elcw-danger');
                                Swal.resetValidationMessage();
                            });
                        });
                    },
                    preConfirm: async function() {
                        try {
                            if (action === 'date') {
                                const entryDate = document.getElementById('elcwDateInput').value;
                                const transactionTime = document.getElementById('elcwTimeInput').value;
                                const reason = document.getElementById('elcwDateReason').value.trim();
                                if (!entryDate || !transactionTime || reason.length < 5) throw new Error('Enter date, time and a correction reason of at least 5 characters.');
                                if (document.getElementById('elcwDateConfirm').value.trim().toUpperCase() !== 'CONFIRM') throw new Error('Type CONFIRM to save the date and time correction.');
                                return await postEmployeePaymentCorrection(employeePaymentDateRoute.replace('__ENTRY__', row.entryId), {entry_date: entryDate, transaction_time: transactionTime, reason: reason});
                            }
                            if (action === 'reverse') {
                                const reversalDate = document.getElementById('elcwReverseDate').value;
                                const reason = document.getElementById('elcwReverseReason').value.trim();
                                if (!reversalDate || reason.length < 5) throw new Error('Enter the reversal date and a reason of at least 5 characters.');
                                if (document.getElementById('elcwConfirm').value.trim().toUpperCase() !== 'CONFIRM') throw new Error('Type CONFIRM to reverse this payment.');
                                return await postEmployeePaymentCorrection(row.reverseUrl, {reversal_date: reversalDate, reason: reason});
                            }
                            return {success: false, closeOnly: true};
                        } catch (error) {
                            Swal.showValidationMessage(error.message);
                            return false;
                        }
                    },
                }).then(function(result) {
                    if (!result.isConfirmed || !result.value?.success) return;
                    Swal.fire({icon: 'success', title: 'Correction posted', text: result.value.message, confirmButtonColor: '#4f46e5'}).then(function() { window.location.reload(); });
                });
            }

            document.querySelectorAll('[data-employee-payment-correction]').forEach(function(button) {
                button.addEventListener('click', function() { openEmployeePaymentCorrection(button); });
            });

            document.querySelectorAll('[data-reverse-payment]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.getElementById('reversePaymentTitle').textContent = 'Reverse Employee Payment';
                    document.getElementById('reverseAdvanceWarning').classList.add('hidden');
                    const modal = document.getElementById('reversePaymentModal');
                    const form = document.getElementById('reversePaymentForm');
                    form.action = @json(route('role.employee-payments.reverse', ['role' => $role, 'payment' => '__PAYMENT__'])).replace('__PAYMENT__', btn.dataset.reversePayment);
                    document.getElementById('reversePaymentReference').textContent = btn.dataset.reference;
                    document.getElementById('reversePaymentAmount').textContent = '৳ ' + btn.dataset.amount;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });

            @if(Route::has('role.advance-salaries.reverse'))
            document.querySelectorAll('[data-reverse-advance]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const modal = document.getElementById('reversePaymentModal');
                    const form = document.getElementById('reversePaymentForm');
                    form.reset();
                    form.action = @json(route('role.advance-salaries.reverse', ['role' => $role, 'id' => '__ADVANCE__'])).replace('__ADVANCE__', btn.dataset.reverseAdvance);
                    document.getElementById('reversePaymentTitle').textContent = 'Reverse Advance Salary';
                    document.getElementById('reverseAdvanceWarning').classList.remove('hidden');
                    document.getElementById('reversePaymentReference').textContent = btn.dataset.reference;
                    document.getElementById('reversePaymentAmount').textContent = '৳ ' + btn.dataset.amount;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });
            @endif

            const payDueMethod = document.getElementById('payDuePaymentMethod');
            const payDueBankFields = document.getElementById('payDueRecipientBankFields');
            const payDueRecipientBank = document.getElementById('payDueRecipientBank');
            const payDueRecipientAccount = document.getElementById('payDueRecipientAccount');
            // Same idea as Manage Bank's Cash In/Out "Received via" box — a
            // cheque needs to say which bank it's drawn on and its own number,
            // same two underlying fields (recipient_bank/recipient_account) a
            // bank transfer uses for the employee's receiving bank, just
            // relabeled for what's actually being recorded.
            const payDueRouteCopy = {
                bank_transfer: {
                    title: '<i class="fas fa-building-columns mr-1"></i> Employee received into',
                    bankLabel: 'Employee Bank Name', bankPlaceholder: 'e.g. Dutch-Bangla Bank',
                    accountLabel: 'Account Number', accountPlaceholder: 'Employee account number',
                },
                cheque: {
                    title: '<i class="fas fa-money-check mr-1"></i> Cheque details',
                    bankLabel: 'Bank Name (cheque drawn on)', bankPlaceholder: 'e.g. Dutch-Bangla Bank',
                    accountLabel: 'Cheque No.', accountPlaceholder: 'Cheque number',
                },
            };
            function syncPayDueRouteFields() {
                if (!payDueMethod) return;
                const copy = payDueRouteCopy[payDueMethod.value];
                const needsBank = !!copy;
                payDueBankFields?.classList.toggle('hidden', !needsBank);
                if (copy) {
                    document.getElementById('payDueRecipientBankTitle').innerHTML = copy.title;
                    document.getElementById('payDueRecipientBankLabel').innerHTML = copy.bankLabel + ' <span class="text-red-500">*</span>';
                    document.getElementById('payDueRecipientAccountLabel').innerHTML = copy.accountLabel + ' <span class="text-red-500">*</span>';
                    payDueRecipientBank.placeholder = copy.bankPlaceholder;
                    payDueRecipientAccount.placeholder = copy.accountPlaceholder;
                }
                [payDueRecipientBank, payDueRecipientAccount].forEach(function(field) {
                    if (!field) return;
                    field.required = needsBank;
                    if (!needsBank) field.value = '';
                });
            }
            payDueMethod?.addEventListener('change', syncPayDueRouteFields);
            syncPayDueRouteFields();

            const advanceMethod = document.getElementById('advancePaymentMethod');
            const advanceRecipientFields = document.getElementById('advanceRecipientFields');
            const advanceRecipientBank = document.getElementById('advanceRecipientBank');
            const advanceRecipientAccount = document.getElementById('advanceRecipientAccount');
            function syncAdvanceRouteFields() {
                if (!advanceMethod) return;
                const copy = payDueRouteCopy[advanceMethod.value];
                const required = !!copy;
                advanceRecipientFields?.classList.toggle('hidden', !required);
                if (copy) {
                    document.getElementById('advanceRecipientTitle').innerHTML = copy.title;
                    document.getElementById('advanceRecipientBankLabel').innerHTML = copy.bankLabel + ' <span class="text-red-500">*</span>';
                    document.getElementById('advanceRecipientAccountLabel').innerHTML = copy.accountLabel + ' <span class="text-red-500">*</span>';
                    advanceRecipientBank.placeholder = copy.bankPlaceholder;
                    advanceRecipientAccount.placeholder = copy.accountPlaceholder;
                }
                [advanceRecipientBank, advanceRecipientAccount].forEach(function(field) {
                    if (!field) return;
                    field.required = required;
                    if (!required) field.value = '';
                });
            }
            advanceMethod?.addEventListener('change', syncAdvanceRouteFields);
            syncAdvanceRouteFields();

            const loanMethod = document.getElementById('loanPaymentMethod');
            const loanRecipientFields = document.getElementById('loanRecipientFields');
            const loanRecipientBank = document.getElementById('loanRecipientBank');
            const loanRecipientAccount = document.getElementById('loanRecipientAccount');
            function syncLoanRouteFields() {
                if (!loanMethod) return;
                const copy = payDueRouteCopy[loanMethod.value];
                const required = !!copy;
                loanRecipientFields?.classList.toggle('hidden', !required);
                if (copy) {
                    document.getElementById('loanRecipientTitle').innerHTML = copy.title;
                    document.getElementById('loanRecipientBankLabel').innerHTML = copy.bankLabel + ' <span class="text-red-500">*</span>';
                    document.getElementById('loanRecipientAccountLabel').innerHTML = copy.accountLabel + ' <span class="text-red-500">*</span>';
                    loanRecipientBank.placeholder = copy.bankPlaceholder;
                    loanRecipientAccount.placeholder = copy.accountPlaceholder;
                }
                [loanRecipientBank, loanRecipientAccount].forEach(function(field) {
                    if (!field) return;
                    field.required = required;
                    if (!required) field.value = '';
                });
            }
            loanMethod?.addEventListener('change', function() {
                filterLoanBanks();
                syncLoanRouteFields();
            });
            syncLoanRouteFields();

            const bonusMethod = document.getElementById('bonusPaymentMethod');
            const bonusRecipientFields = document.getElementById('bonusRecipientFields');
            const bonusRecipientBank = document.getElementById('bonusRecipientBank');
            const bonusRecipientAccount = document.getElementById('bonusRecipientAccount');
            function syncBonusRouteFields() {
                if (!bonusMethod) return;
                const copy = payDueRouteCopy[bonusMethod.value];
                const required = !!copy;
                bonusRecipientFields?.classList.toggle('hidden', !required);
                if (copy) {
                    document.getElementById('bonusRecipientTitle').innerHTML = copy.title;
                    document.getElementById('bonusRecipientBankLabel').innerHTML = copy.bankLabel + ' <span class="text-red-500">*</span>';
                    document.getElementById('bonusRecipientAccountLabel').innerHTML = copy.accountLabel + ' <span class="text-red-500">*</span>';
                    bonusRecipientBank.placeholder = copy.bankPlaceholder;
                    bonusRecipientAccount.placeholder = copy.accountPlaceholder;
                }
                [bonusRecipientBank, bonusRecipientAccount].forEach(function(field) {
                    if (!field) return;
                    field.required = required;
                    if (!required) field.value = '';
                });
            }
            bonusMethod?.addEventListener('change', function() { filterBonusBanks(); syncBonusRouteFields(); });
            syncBonusRouteFields();

            ['advanceForm', 'loanForm', 'bonusForm', 'payDueForm', 'reversePaymentForm', 'openingBalanceForm', 'leaveEncashmentOpeningForm', 'exitLeaveEncashmentForm'].forEach(function(formId) {
                const form = document.getElementById(formId);
                if (!form) return;

                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (form.id === 'advanceForm') {
                        const hour = parseInt(document.getElementById('advanceHour').value, 10);
                        const minute = document.getElementById('advanceMinute').value;
                        const period = document.getElementById('advancePeriod').value;
                        const hour24 = period === 'PM' ? (hour === 12 ? 12 : hour + 12) : (hour === 12 ? 0 : hour);
                        document.getElementById('advanceTransactionTime').value = String(hour24).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                        const ref = document.getElementById('advanceReference');
                        if (ref && /^ADV-\d+$/.test(ref.value)) ref.value = 'ADV-' + transactionStamp(new Date()) + String(Date.now()).slice(-2);
                    }

                    if (form.id === 'loanForm') {
                        const hour = parseInt(document.getElementById('loanHour').value, 10);
                        const minute = document.getElementById('loanMinute').value;
                        const period = document.getElementById('loanPeriod').value;
                        const hour24 = period === 'PM' ? (hour === 12 ? 12 : hour + 12) : (hour === 12 ? 0 : hour);
                        document.getElementById('loanTransactionTime').value = String(hour24).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                        const ref = document.getElementById('loanReference');
                        if (ref && !ref.value.trim()) ref.value = 'LOAN-' + transactionStamp(new Date()) + String(Date.now()).slice(-2);
                    }

                    if (form.id === 'bonusForm') {
                        const hour = parseInt(document.getElementById('bonusHour').value, 10);
                        const minute = document.getElementById('bonusMinute').value;
                        const period = document.getElementById('bonusPeriod').value;
                        const hour24 = period === 'PM' ? (hour === 12 ? 12 : hour + 12) : (hour === 12 ? 0 : hour);
                        document.getElementById('bonusTransactionTime').value = String(hour24).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                        const ref = document.getElementById('bonusReference');
                        if (ref && !ref.value.trim()) ref.value = 'BONUS-' + transactionStamp(new Date()) + String(Date.now()).slice(-2);
                    }

                    if (form.id === 'payDueForm') {
                        const hour = parseInt(document.getElementById('payDueHour').value, 10);
                        const minute = document.getElementById('payDueMinute').value;
                        const period = document.getElementById('payDuePeriod').value;

                        if (!hour || minute === '' || !period) {
                            alert('Please select the custom transaction time.');
                            return;
                        }

                        const hour24 = period === 'PM'
                            ? (hour === 12 ? 12 : hour + 12)
                            : (hour === 12 ? 0 : hour);
                        document.getElementById('payDueTransactionTime').value =
                            String(hour24).padStart(2, '0') + ':' + String(minute).padStart(2, '0');

                        // employee_payments.reference is unique — the preview
                        // filled in when the modal opened could go stale (and
                        // collide) if this is a retry after a validation error
                        // on some other field, since the modal stays open with
                        // the same value. Refresh it right before send, but
                        // only while it still looks auto-generated — a value
                        // the person typed themselves is left alone.
                        const refField = document.getElementById('payDueReference');
                        if (refField && /^SAL-\d+$/.test(refField.value)) {
                            const now = new Date();
                            const pad2 = n => String(n).padStart(2, '0');
                            refField.value = 'SAL-' + now.getFullYear() + pad2(now.getMonth() + 1) + pad2(now.getDate())
                                + pad2(now.getHours()) + pad2(now.getMinutes()) + pad2(now.getSeconds()) + pad2(now.getMilliseconds() % 100);
                        }
                    }

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
                                if (form.id === 'exitLeaveEncashmentForm') {
                                    const url = new URL(window.location.href);
                                    url.searchParams.set('tab', 'payslips');
                                    window.location.href = url.toString();
                                } else {
                                    window.location.reload();
                                }
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
