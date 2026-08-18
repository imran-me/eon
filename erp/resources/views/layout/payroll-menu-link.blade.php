{{--
    Single Payroll entry for the sidebar.

    Payroll used to be an expandable group listing all six screens. Navigation
    between them now lives in the in-page tab bar (layout.payroll-tabs), so the
    sidebar only needs one link — it opens the first payroll screen the current
    user is actually allowed to see, and the tabs take it from there.

    Pass $payrollMenuLabel to override the caption (defaults to "Payroll").
--}}
@php
    $payrollMenuRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional(auth()->user())->getRoleNames()->first() ?? '');

    // Ordered by usefulness as a landing page, not by menu order — Salary Manage
    // is the screen people actually come here for.
    $payrollMenuCandidates = [
        ['route' => 'role.employee-salaries.index', 'permission' => 'view salary'],
        ['route' => 'role.salary-templates.index',  'permission' => 'view salary template'],
        ['route' => 'role.loans.index',             'permission' => 'view loan'],
        ['route' => 'role.payslips.index',          'permission' => 'view payslip'],
        ['route' => 'role.advance-salaries.index',  'permission' => 'view advance salary'],
    ];

    $payrollMenuUrl = null;

    foreach ($payrollMenuCandidates as $payrollMenuCandidate) {
        if (! auth()->user()?->can($payrollMenuCandidate['permission'])) {
            continue;
        }

        try {
            $payrollMenuUrl = route($payrollMenuCandidate['route'], ['role' => $payrollMenuRole]);
            break;
        } catch (\Throwable $e) {
            continue;
        }
    }

    // Stays lit on every payroll screen, including the report hub, so the sidebar
    // still shows where you are once you move around with the tabs.
    $payrollMenuActive = request()->routeIs(
        'role.salary-templates.*',
        'role.employee-salaries.*',
        'role.loans.*',
        'role.payslips.*',
        'role.advance-salaries.*',
        'role.commissions.*',
        'role.report.payroll',
        'role.report.payroll.*'
    );
@endphp

@if($payrollMenuUrl)
    <a href="{{ $payrollMenuUrl }}"
       class="submenu-item {{ $payrollMenuActive ? 'active' : '' }}">
        <i class="fas fa-money-check-alt text-xs w-3"></i> {{ $payrollMenuLabel ?? 'Payroll' }}
    </a>
@endif
