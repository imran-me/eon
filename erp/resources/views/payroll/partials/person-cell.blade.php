{{--
    An employee, as the payroll tables show them: avatar initials, name, and the
    employee ID under it — the same cell Salary Manage, Loans and the Payslip
    desk use.

    The name is a link to that person's individual payroll report, the same
    drill-down the detailed report tables use (report/payroll/partials/
    table-overall.blade.php). A name in a liability table is the start of a
    question — "why does this person still owe?" — and the individual report is
    where that gets answered, so it should not be a dead end.

    Someone who has left is marked rather than dropped. These tables carry money
    that is still owed in one direction or the other, and a row that quietly said
    nothing about employment status would leave the reader wondering why a name
    they know has gone appears on a live liability.

    $user — the employee
    $link — pass false where a drill-down makes no sense (a print document, say)
--}}
@php
    $hasLeft = $user && strtolower((string) $user->status) !== 'active';
    $linkable = ($link ?? true) && $user?->id;

    $personRole = Str::slug(Auth::user()->getRoleNames()->first());
    $personUrl = $linkable
        ? route('role.report.payroll', array_filter([
            'role'        => $personRole,
            'type'        => 'individual',
            'employee_id' => $user->id,
            'company_id'  => request('company_id'),
        ]))
        : null;
@endphp

<{{ $linkable ? 'a' : 'div' }}
    @if($linkable) href="{{ $personUrl }}" title="Open {{ $user->name }}'s payroll report" @endif
    class="flex items-center gap-2 {{ $linkable ? 'group' : '' }}">
    <div class="flex-shrink-0 w-8 h-8 rounded-full {{ $hasLeft ? 'bg-gray-100 text-gray-500' : 'bg-blue-100 text-blue-600' }} flex items-center justify-center text-xs font-bold uppercase">
        {{ strtoupper(substr($user?->name ?? '?', 0, 2)) }}
    </div>
    <div class="min-w-0">
        <span class="block text-sm font-medium text-gray-800 {{ $linkable ? 'group-hover:text-blue-600 group-hover:underline' : '' }}">
            {{ $user?->name ?? 'Employee' }}
            @if($hasLeft)
                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-500 border border-gray-200 no-underline"
                      title="No longer active — the balance is still owed">
                    {{ ucfirst($user->status ?: 'inactive') }}
                </span>
            @endif
        </span>
        <span class="block text-xs font-mono {{ $user?->employee_id_no ? 'text-gray-500' : 'text-gray-300' }}">
            {{ $user?->employee_id_no ?: '—' }}
        </span>
    </div>
</{{ $linkable ? 'a' : 'div' }}>
