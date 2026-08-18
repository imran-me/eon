{{--
    WHAT WAS THIS FOR — Company › Department › Category › Sub-category.

    Two things make this different from four cascading dropdowns:

    1. YOU CAN START ANYWHERE. The person spending the money knows the specific
       thing ("office fuel"), not the group it files under. Picking the specific
       thing fills in everything above it; picking the company first narrows
       everything below. A strict top-down cascade punishes the one person who
       already knows the answer.

    2. EVERY LEVEL RESOLVES UPWARD AS A FACT. A sub-category names its category,
       and both a category and an expense department name their company — all
       stored ids, so nothing here is a guess. That is why the expense desk keeps
       its own departments rather than borrowing HR's, which are group-wide and
       could never name a company. See ExpenseDepartment.

    The search box on top is the fast path: type "fuel", pick the match, and all
    four levels land at once. The four selects underneath are the same state, so
    nothing is hidden behind the search.

    $prefix — 'create' or 'edit', so both modals can host one copy of this
    $companies / $classification — from ExpenseController::index()
--}}
@php
    $prefix = $prefix ?? 'create';
@endphp

<div class="md:col-span-2 rounded-xl border border-gray-200 bg-gray-50/60 p-4" data-classification="{{ $prefix }}">

    <div class="flex items-center justify-between gap-3 mb-3">
        <label class="block text-sm font-semibold text-gray-700">
            What was this for? <span class="text-red-500">*</span>
        </label>
        <span class="text-[11px] text-gray-400 hidden sm:block">
            pick any level — the rest fills in
        </span>
    </div>

    {{-- ── The fast path ── --}}
    <div class="relative mb-3">
        <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
        <input type="text" id="{{ $prefix }}_cls_search" autocomplete="off"
               class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
               placeholder="Search a department or category — e.g. common, fuel, rent…">
        <div id="{{ $prefix }}_cls_results"
             class="hidden absolute z-30 left-0 right-0 mt-1 max-h-56 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg"></div>
    </div>

    {{-- ── The chain, always visible and always editable ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        {{-- Department first, Company second, and deliberately so: a department
             belongs to exactly one company, so choosing one ANSWERS the company.
             Asking for the company first put the field that fills itself in ahead
             of the field that fills it. The department list is grouped by company,
             so nothing is lost by not narrowing it first. --}}
        <div>
            <label for="{{ $prefix }}_expense_department_id" class="block text-xs font-semibold text-gray-500 mb-1">
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-100 text-blue-600 text-[9px] font-bold mr-1">1</span>Department
                <span class="text-red-500">*</span>
            </label>
            {{-- Required: the department is the cost centre, and it is the only
                 thing that separates a company's own costs from what it paid on
                 everyone's behalf — "Group Office" against "Common". Without it an
                 expense lands in a company's books with no way to tell which.

                 No "Others…" here, and none on Category either — only
                 Sub-category still offers it. Both of those columns are NOT NULL
                 foreign keys, and the option posted the literal string
                 "__other", so it could never be stored: on Department it failed
                 exists:expense_departments,id, and on Category it reached
                 ExpenseClassificationService::accountFor() and 500d the save.
                 The option never worked, it only looked like it did.

                 A missing department is a setup gap: add it on the
                 Classification tab, where it takes one click. A category that
                 does not fit is what the shared "Miscellaneous" row is for —
                 it is real, it is mapped to its own ledger account, and its
                 sub-category list is a single "Others…" for typing what it
                 actually was. --}}
            <select id="{{ $prefix }}_expense_department_id" name="expense_department_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select department</option>
                @foreach($classification['departments'] as $dept)
                    <option value="{{ $dept['id'] }}" data-company="{{ $dept['company_id'] }}">{{ $dept['name'] }}</option>
                @endforeach
            </select>
            <p id="{{ $prefix }}_department_msg" class="hidden text-[11px] text-red-600 mt-1">
                Choose the department this cost belongs to.
            </p>
        </div>

        <div>
            <label for="{{ $prefix }}_company_id" class="block text-xs font-semibold text-gray-500 mb-1">
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-100 text-blue-600 text-[9px] font-bold mr-1">2</span>Company
                <span class="text-red-500">*</span>
            </label>
            {{-- Always required, and the blank option always asks rather than
                 answering. A CATEGORY can belong to no company — that means shared,
                 and it is true. An EXPENSE cannot: it is real money leaving one
                 company's cash or bank, expenses.company_id is a single column, and
                 journal_entries.company_id is NOT NULL — so a blank company does
                 not save "for everyone", it fails on the journal insert. --}}
            <select id="{{ $prefix }}_company_id" name="company_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select company</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->short_name ?: $company->name }}</option>
                @endforeach
            </select>
            {{-- Leads with what to DO. The earlier wording opened with "shared by
                 every company", which read as permission to leave it blank — the
                 opposite of what it meant. --}}
            <p id="{{ $prefix }}_company_note" class="hidden text-[11px] text-blue-700 mt-1">
                <i class="fas fa-circle-info"></i>
                <span></span>
            </p>
            <p id="{{ $prefix }}_company_msg" class="hidden text-[11px] text-red-600 mt-1">
                Choose the company this expense belongs to.
            </p>
        </div>

        <div>
            <label for="{{ $prefix }}_expense_category_id" class="block text-xs font-semibold text-gray-500 mb-1">
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-100 text-blue-600 text-[9px] font-bold mr-1">3</span>Category <span class="text-red-500">*</span>
            </label>
            <select id="{{ $prefix }}_expense_category_id" name="expense_category_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select category</option>
            </select>
        </div>

        <div>
            <label for="{{ $prefix }}_expense_sub_category_id" class="block text-xs font-semibold text-gray-500 mb-1">
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-100 text-blue-600 text-[9px] font-bold mr-1">4</span>Sub-category
            </label>
            <select id="{{ $prefix }}_expense_sub_category_id" name="expense_sub_category_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select sub-category</option>
                <option value="__other">Others…</option>
            </select>
        </div>
    </div>

    {{-- ── The escape hatch ──
         Shown only when a level is set to "Others…", so the common case never
         sees it. Typing what it actually was beats inventing a category that
         nobody will use a second time. --}}
    <div id="{{ $prefix }}_other_wrap" class="hidden mt-3">
        <label for="{{ $prefix }}_other_note" class="block text-xs font-semibold text-gray-500 mb-1">
            What was it? <span class="font-normal text-gray-400">— recorded against this expense</span>
        </label>
        <input type="text" id="{{ $prefix }}_other_note" name="other_note" maxlength="255"
               class="w-full px-3 py-2 border border-amber-300 bg-amber-50/50 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
               placeholder="e.g. Eid bonus for site guards">
    </div>

    {{-- ── The resolved path, read back ──
         The point of the picker is that it fills things in; showing what it
         filled in is how the user knows it got it right. --}}
    <p id="{{ $prefix }}_cls_path" class="hidden mt-3 text-xs text-gray-500"></p>
</div>
