@extends('layout.app')
@section('meta-information')
    <title>Payroll Reports — Overview</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
@endsection
@section('main-content')

@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());

    // Whole taka, the same way the salary sheet, the loan desk and the payslip
    // desk print them. Display only.
    $taka = fn ($amount) => number_format(round((float) $amount));

    // The company column earns its place only when the page is showing more than
    // one — otherwise every row repeats the same chip and costs a column doing it.
    $showCompany = ! $companyId && $companies->count() > 1;

    $th = 'px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider';
    $thR = 'px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider';
    $thead = 'background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);';
    $tbl = 'table table-hover min-w-full divide-y divide-gray-200';
    $tfoot = 'bg-gray-100 text-sm border-t border-gray-300';
@endphp

<div class="p-4 md:p-6 space-y-6">
    @include('layout.payroll-tabs')

    {{-- ── Header ── --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900 leading-tight">
                <i class="fas fa-chart-pie text-blue-500 mr-1.5"></i>Payroll Reports
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">
                What the payroll owes, where the money went, and what each department costs.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- The five filter-driven report tabs are still there; this page
                 answers the standing questions, they answer "narrow this table". --}}
            <a href="{{ route('role.report.payroll', array_filter(['role' => $role, 'company_id' => $companyId])) }}"
               class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                <i class="fas fa-table-list"></i> Detailed Reports
            </a>
        </div>
    </div>

    {{-- ── Summary Cards ── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @include('payroll.partials.kpi', [
            'label'     => 'Leave Encash Liability',
            'value'     => '৳ ' . $taka($summary['liability']),
            'icon'      => 'fa-piggy-bank',
            'iconBg'    => '#fef3c7',
            'iconText'  => '#b45309',
            'valueTone' => 'text-amber-600',
            'foot'      => $summary['liability'] > 0
                ? $summary['accruing'] . ' accruing · ' . $summary['encashable'] . ' encashable now'
                : 'nothing accrued yet',
        ])

        @include('payroll.partials.kpi', [
            'label'     => 'Salary Due',
            'value'     => '৳ ' . $taka($summary['salary_due']),
            'icon'      => 'fa-hourglass-half',
            'iconBg'    => $summary['salary_due'] > 0 ? '#fee2e2' : '#dcfce7',
            'iconText'  => $summary['salary_due'] > 0 ? '#dc2626' : '#16a34a',
            'valueTone' => $summary['salary_due'] > 0 ? 'text-red-600' : 'text-green-600',
            'foot'      => $summary['due_heads']
                ? $summary['due_heads'] . ' ' . Str::plural('employee', $summary['due_heads']) . ' still owed'
                : 'every payslip is settled',
        ])

        @include('payroll.partials.kpi', [
            'label'    => 'Advance Outstanding',
            'value'    => '৳ ' . $taka($summary['advance_out']),
            'icon'     => 'fa-sack-dollar',
            'iconBg'   => '#ede9fe',
            'iconText' => '#7c3aed',
            'foot'     => $summary['advance_holders']
                ? $summary['advance_holders'] . ' staff holding an advance'
                : 'no advances outstanding',
        ])

        @include('payroll.partials.kpi', [
            'label'    => 'Loan Outstanding',
            'value'    => '৳ ' . $taka($summary['loan_out']),
            'icon'     => 'fa-building-columns',
            'iconBg'   => '#dbeafe',
            'iconText' => '#2563eb',
            'foot'     => $summary['loan_count']
                ? $summary['loan_count'] . ' active ' . Str::plural('loan', $summary['loan_count'])
                    . ($summary['emi_total'] ? ' · ৳ ' . $taka($summary['emi_total']) . '/mo EMI' : '')
                : 'no active loans',
        ])
    </div>

    {{-- ══ WHERE THE MONEY WENT ══ --}}
    @php
        $periodPicker = view('report.payroll.partials.period-picker', [
            'role' => $role, 'companyId' => $companyId, 'months' => $months,
        ])->render();
    @endphp
    @component('payroll.partials.report-card', [
        'title' => 'Where the Money Went',
        'icon' => 'fa-building-columns',
        'sub' => 'every payroll taka by the account it left, on the day it moved',
        'section' => 'money-flow',
        'count' => $flow['rows']->count(),
        'role' => $role,
        'right' => $periodPicker,
        'emptyTitle' => 'Nothing moved in this period',
        'emptyMsg' => 'No salary, advance or loan payment left an account between '
            . $flow['from']->format('d M Y') . ' and ' . $flow['to']->format('d M Y') . '.',
    ])
        <table class="{{ $tbl }}">
            <thead>
                <tr style="{{ $thead }}">
                    <th class="{{ $th }}"><i class="fas fa-university mr-1 text-blue-400"></i>Paid From</th>
                    <th class="{{ $thR }}"><i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Salary</th>
                    <th class="{{ $thR }}"><i class="fas fa-sack-dollar mr-1 text-violet-400"></i>Advance</th>
                    <th class="{{ $thR }}"><i class="fas fa-building-columns mr-1 text-indigo-400"></i>Staff Loan</th>
                    <th class="{{ $thR }}"><i class="fas fa-arrow-up mr-1 text-red-400"></i>Total Paid Out</th>
                    <th class="{{ $thR }}"><i class="fas fa-arrow-down mr-1 text-emerald-400"></i>Came Back In</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @foreach($flow['rows'] as $row)
                    <tr class="hover:bg-blue-50 transition-colors duration-150">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800">{{ $row['account'] }}</td>
                        @foreach(['salary', 'advance', 'loan'] as $key)
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                @if($row[$key] > 0)
                                    <span class="text-sm font-medium text-gray-700">৳ {{ $taka($row[$key]) }}</span>
                                @else
                                    <span class="text-sm text-gray-300">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-3 whitespace-nowrap text-right">
                            <span class="text-sm font-bold text-gray-900">৳ {{ $taka($row['out']) }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">
                            @if($row['back'] > 0)
                                <span class="text-sm font-semibold text-emerald-600">৳ {{ $taka($row['back']) }}</span>
                            @else
                                <span class="text-sm text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-gray-200">
                <tr class="{{ $tfoot }}">
                    <td class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $flow['rows']->count() }} {{ Str::plural('Account', $flow['rows']->count()) }}
                    </td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">৳ {{ $taka($flow['rows']->sum('salary')) }}</td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">৳ {{ $taka($flow['rows']->sum('advance')) }}</td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">৳ {{ $taka($flow['rows']->sum('loan')) }}</td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-900">৳ {{ $taka($flow['out']) }}</td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-emerald-700">৳ {{ $taka($flow['back']) }}</td>
                </tr>
            </tfoot>
        </table>

        {{-- The two figures that do NOT belong in an account column, said plainly
             underneath instead of being folded into one. --}}
        <p class="px-1 pt-3 text-xs text-gray-500 leading-relaxed">
            ৳ {{ $taka($flow['out']) }} left {{ $flow['rows']->count() }} {{ Str::plural('account', $flow['rows']->count()) }}
            over {{ Str::lower(\App\Services\PayrollOverviewService::PERIODS[$months]) }}
            · ৳ {{ $taka($flow['back']) }} came back in (loan repayments)
            @if($flow['recovered'] > 0)
                · ৳ {{ $taka($flow['recovered']) }} was recovered inside a salary payment and never touched an account
            @endif
        </p>
    @endcomponent

    {{-- ══ LEAVE ENCASHMENT LIABILITY ══ --}}
    @component('payroll.partials.report-card', [
        'title' => 'Leave Encashment Liability',
        'icon' => 'fa-piggy-bank',
        'sub' => '৳ ' . $taka($summary['liability']) . ' total provision · pro-rata of the February payout',
        'section' => 'encashment',
        'count' => $encashment->count(),
        'role' => $role,
        'emptyTitle' => 'No accrued encashment',
        'emptyMsg' => 'Nobody has accrued leave encashment in the current February–January cycle yet.',
    ])
        <table class="{{ $tbl }}">
            <thead>
                <tr style="{{ $thead }}">
                    <th class="{{ $th }}"><i class="fas fa-user mr-1 text-blue-400"></i>Employee</th>
                    @if($showCompany)<th class="{{ $th }}"><i class="fas fa-building mr-1 text-cyan-500"></i>Company</th>@endif
                    <th class="{{ $th }}"><i class="fas fa-sitemap mr-1 text-indigo-400"></i>Dept</th>
                    <th class="{{ $thR }}"><i class="fas fa-calendar-day mr-1 text-cyan-400"></i>Accrued Months</th>
                    <th class="{{ $thR }}"><i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Value</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">
                        <i class="fas fa-tag mr-1 text-yellow-400"></i>Eligibility
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @foreach($encashment as $row)
                    <tr class="hover:bg-blue-50 transition-colors duration-150">
                        <td class="px-4 py-3 whitespace-nowrap">
                            @include('payroll.partials.person-cell', ['user' => $row['user']])
                        </td>
                        @if($showCompany)
                            <td class="px-4 py-3 whitespace-nowrap">@include('payroll.partials.company-chip', ['company' => $row['company']])</td>
                        @endif
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200">{{ $row['dept'] }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-700">{{ number_format($row['months'], 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">
                            <span class="text-sm font-bold text-gray-900">৳ {{ $taka($row['value']) }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">
                            @if($row['eligible'])
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                                    <i class="fas fa-check-circle text-xs"></i> Eligible
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">
                                    <i class="fas fa-hourglass-half text-xs"></i> Accruing
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-gray-200">
                <tr class="{{ $tfoot }}">
                    <td colspan="{{ 2 + ($showCompany ? 1 : 0) }}" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $encashment->count() }} {{ Str::plural('Employee', $encashment->count()) }}
                    </td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">{{ number_format($encashment->sum('months'), 2) }}</td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-amber-700">৳ {{ $taka($encashment->sum('value')) }}</td>
                    <td class="px-4 py-3.5 text-center text-gray-500 text-xs whitespace-nowrap">
                        {{ $encashment->where('eligible', true)->count() }} eligible · {{ $encashment->where('eligible', false)->count() }} accruing
                    </td>
                </tr>
            </tfoot>
        </table>
    @endcomponent

    {{-- ══ SALARY DUE ══ --}}
    @component('payroll.partials.report-card', [
        'title' => 'Salary Due',
        'icon' => 'fa-hourglass-half',
        'sub' => $summary['due_heads'] . ' ' . Str::plural('employee', $summary['due_heads']) . ' owed · net less whatever has been paid against it',
        'section' => 'salary-due',
        'count' => $salaryDue->count(),
        'role' => $role,
        'emptyTitle' => 'Every payslip is settled',
        'emptyMsg' => 'Nothing is outstanding against any salary in scope.',
    ])
        @include('report.payroll.partials.people-table', [
            'rows' => $salaryDue, 'showCompany' => $showCompany, 'taka' => $taka,
            'amountLabel' => 'Outstanding', 'amountTone' => 'text-red-600',
        ])
    @endcomponent

    {{-- ══ ADVANCE OUTSTANDING ══ --}}
    @component('payroll.partials.report-card', [
        'title' => 'Advance Outstanding',
        'icon' => 'fa-sack-dollar',
        'sub' => 'who is holding an advance right now',
        'section' => 'advance',
        'count' => $advances->count(),
        'role' => $role,
        'emptyTitle' => 'No advances outstanding',
        'emptyMsg' => 'Every approved advance has been recovered or settled.',
    ])
        @include('report.payroll.partials.people-table', [
            'rows' => $advances, 'showCompany' => $showCompany, 'taka' => $taka,
            'amountLabel' => 'Advance Held', 'amountTone' => 'text-violet-700',
        ])
    @endcomponent

    {{-- ══ LOAN OUTSTANDING ══
         The one report that cannot be a name and a number: a loan balance means
         nothing without what was taken, when, and how much has come back. --}}
    @component('payroll.partials.report-card', [
        'title' => 'Loan Outstanding',
        'icon' => 'fa-building-columns',
        'sub' => $loans->count() . ' ' . Str::plural('loan', $loans->count()) . ' in progress · taken · paid till now · still due',
        'section' => 'loan',
        'count' => $loans->count(),
        'role' => $role,
        'emptyTitle' => 'Nothing outstanding',
        'emptyMsg' => 'No staff loan is still running in this scope.',
    ])
        <table class="{{ $tbl }}">
            <thead>
                <tr style="{{ $thead }}">
                    <th class="{{ $th }}"><i class="fas fa-user mr-1 text-blue-400"></i>Employee</th>
                    @if($showCompany)<th class="{{ $th }}"><i class="fas fa-building mr-1 text-cyan-500"></i>Company</th>@endif
                    <th class="{{ $th }}"><i class="fas fa-sitemap mr-1 text-indigo-400"></i>Dept</th>
                    <th class="{{ $th }}"><i class="fas fa-calendar-day mr-1 text-cyan-400"></i>Taken On</th>
                    <th class="{{ $thR }}"><i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Loan Taken</th>
                    <th class="{{ $thR }}"><i class="fas fa-rotate-left mr-1 text-emerald-400"></i>Paid Till Now</th>
                    <th class="{{ $thR }}"><i class="fas fa-hourglass-half mr-1 text-red-400"></i>Still Due</th>
                    <th class="{{ $thR }}"><i class="fas fa-calendar-check mr-1 text-teal-400"></i>EMI</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @foreach($loans as $loan)
                    <tr class="hover:bg-blue-50 transition-colors duration-150">
                        <td class="px-4 py-3 whitespace-nowrap">
                            @include('payroll.partials.person-cell', ['user' => $loan->user])
                        </td>
                        @if($showCompany)
                            <td class="px-4 py-3 whitespace-nowrap">@include('payroll.partials.company-chip', ['company' => $loan->user?->company])</td>
                        @endif
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                                {{ $loan->user?->profile?->department?->name ?: 'Unassigned' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                            {{ $loan->start_date ? \Carbon\Carbon::parse($loan->start_date)->format('d M Y') : '—' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium text-gray-700">৳ {{ $taka($loan->amount) }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="text-sm font-semibold text-emerald-600">৳ {{ $taka($loan->paid_amount) }}</span>
                            <p class="text-[11px] text-gray-400 leading-tight mt-0.5">{{ $loan->progress_pct }}%</p>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">
                            <span class="text-sm font-bold text-red-600">৳ {{ $taka($loan->outstanding) }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">
                            @if($loan->monthly_deduction > 0)
                                <span class="text-sm font-medium text-gray-700">৳ {{ $taka($loan->monthly_deduction) }}/mo</span>
                            @else
                                <span class="text-sm text-gray-300">no plan</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-gray-200">
                <tr class="{{ $tfoot }}">
                    <td colspan="{{ 3 + ($showCompany ? 1 : 0) }}" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $loans->count() }} {{ Str::plural('Loan', $loans->count()) }}
                    </td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">৳ {{ $taka($loans->sum(fn ($l) => (float) $l->amount)) }}</td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-emerald-700">৳ {{ $taka($loans->sum(fn ($l) => $l->paid_amount)) }}</td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-red-700">৳ {{ $taka($loans->sum(fn ($l) => $l->outstanding)) }}</td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">৳ {{ $taka($loans->sum(fn ($l) => (float) $l->monthly_deduction)) }}</td>
                </tr>
            </tfoot>
        </table>
    @endcomponent

    {{-- ══ DEPARTMENT COST ══ --}}
    @component('payroll.partials.report-card', [
        'title' => 'Department Cost (monthly gross)',
        'icon' => 'fa-sitemap',
        'sub' => 'salary cost by department, merged across the scope',
        'section' => 'department',
        'count' => $departments->count(),
        'role' => $role,
        'emptyTitle' => 'No department cost',
        'emptyMsg' => 'No current staff carry a salary in this scope.',
    ])
        <table class="{{ $tbl }}">
            <thead>
                <tr style="{{ $thead }}">
                    <th class="{{ $th }}"><i class="fas fa-sitemap mr-1 text-indigo-400"></i>Department</th>
                    <th class="{{ $thR }}"><i class="fas fa-users mr-1 text-blue-400"></i>Headcount</th>
                    <th class="{{ $thR }}"><i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Monthly Cost</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @foreach($departments as $dept)
                    <tr class="hover:bg-blue-50 transition-colors duration-150">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800">{{ $dept['dept'] }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-700">{{ number_format($dept['heads']) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">
                            <span class="text-sm font-bold text-gray-900">৳ {{ $taka($dept['cost']) }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            {{-- Departments are disjoint, so the headcount column really does sum
                 to the payroll — unlike a month-by-month headcount, which would
                 count the same person more than once. --}}
            <tfoot class="border-t-2 border-gray-200">
                <tr class="{{ $tfoot }}">
                    <td class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $departments->count() }} {{ Str::plural('Department', $departments->count()) }}
                    </td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">{{ number_format($departments->sum('heads')) }}</td>
                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-900">৳ {{ $taka($departments->sum('cost')) }}</td>
                </tr>
            </tfoot>
        </table>
    @endcomponent
</div>

@endsection
