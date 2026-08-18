{{--
    Export / PDF for one payroll table, styled as the outline buttons Salary
    Manage puts in its card header. Shared by the Loans and Payslip desks.

    Both links carry the CURRENT querystring, so what comes down is what is on
    screen — same company chip, same employee, same status, same date range. A
    download that quietly ignored the filters above it would be the fastest way
    to get a wrong number into a meeting.

    $routePrefix — e.g. 'role.loans.export'; '.excel' and '.pdf' are appended
    $table       — which table of that desk to export, when it has more than one
    $pageKeys    — this desk's paginator query keys, dropped from the links
    $count       — rows currently in scope; with none, the buttons are dead
                   rather than handing back an empty file that looks like an answer
--}}
@php
    $exportRole = $exportRole ?? Str::slug(Auth::user()->getRoleNames()->first());
    $count = $count ?? 0;
    $pageKeys = $pageKeys ?? ['page', 'reg_page', 'txn_page', 'emp_page'];

    $exportQuery = array_merge(
        // Page positions belong to the screen, not to a file that contains
        // every row anyway.
        collect(request()->query())->except($pageKeys)->all(),
        ['role' => $exportRole],
        // A desk with more than one table says which; a desk with one omits it.
        isset($table) && $table !== null ? ['table' => $table] : [],
        $extraQuery ?? []
    );

    $btn = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors';
@endphp

<div class="flex items-center gap-2 shrink-0">
    @if($count > 0)
        <a href="{{ route($routePrefix . '.excel', $exportQuery) }}"
           class="{{ $btn }} bg-white text-gray-700 border-gray-300 hover:bg-green-600 hover:text-white hover:border-green-600"
           title="Download these rows as a spreadsheet">
            <i class="fas fa-file-excel"></i> Export
        </a>
        {{-- New tab, the way the party statement opens its own print page: the
             report is a document to read, print or save as PDF, not a navigation
             away from the desk the user is working on. --}}
        <a href="{{ route($routePrefix . '.pdf', $exportQuery) }}"
           target="_blank" rel="noopener"
           class="{{ $btn }} bg-white text-gray-700 border-gray-300 hover:bg-red-600 hover:text-white hover:border-red-600"
           title="Open the printable report — print it or save it as a PDF">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
    @else
        <span class="{{ $btn }} bg-gray-50 text-gray-300 border-gray-200 cursor-not-allowed" title="Nothing to export">
            <i class="fas fa-file-excel"></i> Export
        </span>
        <span class="{{ $btn }} bg-gray-50 text-gray-300 border-gray-200 cursor-not-allowed" title="Nothing to export">
            <i class="fas fa-file-pdf"></i> PDF
        </span>
    @endif
</div>
