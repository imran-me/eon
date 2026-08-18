@extends('layout.app')
@section('meta-information')
    <title>Manage Expenses</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
@include('expenses.partials.payment-source-css')
<style>
    /* Override shared title color */
    .states-table .states-table-header .states-table-title {
        color: #fff !important;
    }

    .exp-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }

    /* Summary bar */
    .exp-summary-bar {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        padding: 14px 16px 0;
    }
    .exp-summary-card {
        border-radius: 10px;
        padding: 10px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 160px;
        border: 1px solid;
    }
    .exp-summary-card.teal   { background: #f0fdfa; border-color: #99f6e4; }
    .exp-summary-card.teal   .sc-icon  { color: #0d9488; font-size: 20px; }
    .exp-summary-card.teal   .sc-value { color: #0f766e; }

    .exp-summary-card.blue   { background: #eff6ff; border-color: #bfdbfe; }
    .exp-summary-card.blue   .sc-icon  { color: #2563eb; font-size: 20px; }
    .exp-summary-card.blue   .sc-value { color: #1d4ed8; }

    .exp-summary-card.green  { background: #f0fdf4; border-color: #bbf7d0; }
    .exp-summary-card.green  .sc-icon  { color: #16a34a; font-size: 20px; }
    .exp-summary-card.green  .sc-value { color: #15803d; }

    .exp-summary-card.violet { background: #f5f3ff; border-color: #ddd6fe; }
    .exp-summary-card.violet .sc-icon  { color: #7c3aed; font-size: 20px; }
    .exp-summary-card.violet .sc-value { color: #6d28d9; }

    .exp-summary-card.amber  { background: #fffbeb; border-color: #fde68a; }
    .exp-summary-card.amber  .sc-icon  { color: #d97706; font-size: 20px; }
    .exp-summary-card.amber  .sc-value { color: #b45309; }

    .sc-label { font-size: 11px; color: #6b7280; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; }
    .sc-value  { font-size: 19px; font-weight: 700; line-height: 1.15; }
    .sc-sub    { font-size: 10.5px; color: #6b7280; margin-top: 3px; line-height: 1.45; }
    /* A custodian with nothing left is a stop sign, not a detail — the same
       reading the entry form's empty-pocket panel takes. */
    .sc-sub .is-empty { color: #b91c1c; font-weight: 700; }

    /* Two of these cards are filters rather than decoration: they carry the
       reader into the rows the number is about, and clicking an active one
       clears it again. Anchors, so middle-click and copy-link behave. */
    a.exp-summary-card { text-decoration: none; cursor: pointer; transition: box-shadow .15s, transform .15s; }
    a.exp-summary-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,.08); transform: translateY(-1px); text-decoration: none; }
    .exp-summary-card.violet.is-on { box-shadow: inset 0 0 0 2px #7c3aed; }
    .exp-summary-card.amber.is-on  { box-shadow: inset 0 0 0 2px #d97706; }
    .sc-on-hint { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin-top: 3px; }
    .violet .sc-on-hint { color: #6d28d9; }
    .amber  .sc-on-hint { color: #b45309; }

    /* Amount cell */
    .amount-cell {
        font-weight: 700;
        color: #0f766e;
        font-size: 13.5px;
        white-space: nowrap;
    }
    .amount-cell::before { content: '৳ '; font-size: 11px; font-weight: 500; color: #5eead4; }

    /* Category hierarchy badge */
    .cat-hier {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    .cat-badge-exp {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #eff6ff;
        color: #2563eb;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 20px;
        white-space: nowrap;
    }
    .subcat-badge-exp {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f5f3ff;
        color: #7c3aed;
        font-size: 10px;
        font-weight: 500;
        padding: 2px 7px;
        border-radius: 20px;
        white-space: nowrap;
    }

    /* Title cell */
    .title-cell { display: flex; align-items: center; gap: 7px; }
    .title-dot  {
        width: 7px; height: 7px;
        border-radius: 50%;
        background: #14b8a6;
        flex-shrink: 0;
    }

    /* Action buttons */
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px; height: 32px;
        border-radius: 6px;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.15s;
        font-size: 13px;
        background: transparent;
    }
    .action-btn.edit   { border-color: #0d9488; color: #0d9488; }
    .action-btn.edit:hover { background: #0d9488; color: #fff; }
    .action-btn.delete { border-color: #ef4444; color: #ef4444; }
    .action-btn.delete:hover { background: #ef4444; color: #fff; }

    /* Reverse and Approve, styled here rather than with Tailwind utilities.
       They used to carry `hover:bg-orange-500` / `hover:bg-teal-500`, and neither
       utility exists in the compiled bundle — it was built before these buttons
       were, and Tailwind only generates the classes it can see. `hover:text-white`
       DID survive, so hovering turned the icon white over a background that never
       arrived and the button vanished mid-click. Same reason their borders never
       showed: `.action-btn`'s `border: 1px solid transparent` and Tailwind's
       `.border-orange-500` are equal specificity, and this block wins on order.
       Written as plain CSS so the page cannot depend on what the last `npm run
       build` happened to catch. */
    .action-btn.reverse { border-color: #f97316; color: #ea580c; }
    .action-btn.reverse:hover { background: #f97316; color: #fff; }
    .action-btn.approve { border-color: #14b8a6; color: #0d9488; }
    .action-btn.approve:hover { background: #14b8a6; color: #fff; }

    /* Posted to the ledger. Edit and delete stay in the row so the workflow
       stays readable, but they are inert until the expense is reversed —
       which is what the server already enforces. Last in the sheet on
       purpose: it has to beat the .edit/.delete hover rules above. */
    .action-btn:disabled,
    .action-btn:disabled:hover {
        border-color: #e5e7eb;
        color: #9ca3af;
        background: transparent;
        cursor: not-allowed;
    }

    /* Where the money actually left from. Three sources, because the ledger
       credits three different accounts — see ExpenseController::settlementAccountId().
       Cash keeps the teal and Bank the blue that the entry form's source cards
       already use, so the list and the form never describe the same expense in
       two different colours. Petty cash gets its own violet: it is cash, but not
       the drawer's, and that is the distinction the column exists to show. */
    .pay-src {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        white-space: nowrap;
    }
    /* Amber, and on purpose the same amber as Pending: both mean something is
       still outstanding. This one is money the company has not paid back yet. */
    .pay-src-owed  { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .pay-src-cash  { background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; }
    .pay-src-bank  { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .pay-src-petty { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    .pay-src-sub   { font-size: 11px; color: #9ca3af; margin-top: 2px; }

    /* Print button */
    .btn-print {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fff;
        color: #059669;
        border: none;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.15s, color 0.15s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .btn-print:hover { background: #ecfdf5; color: #047857; text-decoration: none; }

    /* Mobile card layout */
    @media (max-width: 768px) {
        .states-table-header { flex-direction: column; gap: 10px; align-items: flex-start !important; }
        .exp-header-actions  { width: 100%; }
        .exp-summary-bar     { padding: 10px 10px 0; }
        .exp-summary-card    { flex: 1; min-width: 140px; }

        .expense-table thead { display: none; }
        .expense-table tbody tr {
            display: block;
            margin: 10px 0;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 4px 0;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .expense-table tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 14px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
        }
        .expense-table tbody td:last-child { border-bottom: none; }
        .expense-table tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: #374151;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
            margin-right: 10px;
            flex-shrink: 0;
        }
        .expense-table tbody td.td-actions { justify-content: flex-end; }
        .expense-table tbody td.td-actions::before { content: ""; }
    }
    @media (min-width: 769px) {
        .expense-table tbody td::before { display: none; }
    }
</style>
@endsection
@section('main-content')

    @include('layout.expense-tabs')


    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">

            {{-- Header --}}
            <div class="states-table-header px-6 py-4 flex justify-between items-center" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%)">
                <h2 class="states-table-title text-xl font-semibold" style="color:#fff; margin:0">
                    <i class="fas fa-file-invoice-dollar mr-2"></i>Expense List
                </h2>
                <div class="exp-header-actions">
                    <a href="{{ route('role.expenses.print', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}?{{ http_build_query(request()->all()) }}"
                       target="_blank" class="btn-print">
                        <i class="fas fa-print"></i>Print / PDF
                    </a>
                    @can('create expense')
                    <button class="create-new-btn inline-flex items-center gap-2 font-semibold px-4 py-2 rounded-lg shadow transition duration-200 text-sm"
                        style="background:#fff; color:#0d9488">
                        <i class="fas fa-plus"></i>Add Expense
                    </button>
                    @endcan
                </div>
            </div>

            {{-- Summary bar.
                 Figures cover the whole FILTERED set, not the page — "amount on
                 this page" was honest but answered a question nobody asks, since
                 it changed every time you turned the page. The cash figures come
                 from variables index() already loads for the entry form, so this
                 strip costs no query of its own. --}}
            @php
                $pettyHeld  = $pettyCashFloats->sum('held');
                $pettySpent = $pettyCashFloats->sum('spent_today');
                $fundTotal  = (float) ($dailyFundToday['total_fund'] ?? 0);
                $fundSpent  = (float) ($dailyFundToday['total_spent'] ?? 0);

                $pettyOn   = request('payment_source') === 'petty';
                $pendingOn = request('approval_status') === \App\Models\Expense::PENDING;

                // Toggle a card's filter while keeping every other one. Dropping
                // `page` too, because filtered results rarely have the page you
                // were on.
                $cardUrl = function (array $params) {
                    $q = array_filter(
                        array_merge(request()->except('page'), $params),
                        fn ($v) => $v !== null && $v !== ''
                    );

                    return request()->url() . ($q ? '?' . http_build_query($q) : '');
                };
            @endphp
            <div class="exp-summary-bar">
                <div class="exp-summary-card teal">
                    <span class="sc-icon"><i class="fas fa-receipt"></i></span>
                    <div>
                        <div class="sc-label">Expenses {{ request()->except('page') ? '(filtered)' : '' }}</div>
                        <div class="sc-value">{{ number_format($summary->rows_count) }}</div>
                        <div class="sc-sub">৳ {{ number_format($summary->amount_total, 2) }} in total</div>
                    </div>
                </div>

                <a href="{{ $cardUrl(['approval_status' => $pendingOn ? null : \App\Models\Expense::PENDING]) }}"
                   class="exp-summary-card amber {{ $pendingOn ? 'is-on' : '' }}"
                   title="{{ $pendingOn ? 'Showing pending only — click to clear' : 'Show only expenses still waiting for approval' }}">
                    <span class="sc-icon"><i class="fas fa-hourglass-half"></i></span>
                    <div>
                        <div class="sc-label">Pending approval</div>
                        <div class="sc-value">{{ number_format($pendingSummary->rows_count) }}</div>
                        <div class="sc-sub">৳ {{ number_format($pendingSummary->amount_total, 2) }} not yet posted</div>
                        @if ($pendingOn)<div class="sc-on-hint">Filtered · click to clear</div>@endif
                    </div>
                </a>

                @if ($pettyCashFloats->isNotEmpty())
                <a href="{{ $cardUrl(['payment_source' => $pettyOn ? null : 'petty']) }}"
                   class="exp-summary-card violet {{ $pettyOn ? 'is-on' : '' }}"
                   title="{{ $pettyOn ? 'Showing petty cash expenses only — click to clear' : 'Show only expenses settled from a custodian\'s float' }}">
                    <span class="sc-icon"><i class="fas fa-wallet"></i></span>
                    <div>
                        <div class="sc-label">Petty cash held</div>
                        <div class="sc-value">৳ {{ number_format($pettyHeld, 2) }}</div>
                        <div class="sc-sub">
                            {{-- Per custodian, not one lump. A single total hides the
                                 person whose pocket is nearly empty, which is the one
                                 fact worth acting on. --}}
                            @foreach ($pettyCashFloats as $float)
                                <span class="{{ $float->held <= 0 ? 'is-empty' : '' }}">{{ $float->custodian?->name ?? 'Unassigned' }} ৳{{ number_format($float->held, 0) }}</span>{{ !$loop->last ? ' · ' : '' }}
                            @endforeach
                            @if ($pettySpent > 0)
                                <br>৳ {{ number_format($pettySpent, 2) }} spent today
                            @endif
                        </div>
                        @if ($pettyOn)<div class="sc-on-hint">Filtered · click to clear</div>@endif
                    </div>
                </a>
                @endif

                {{-- Group-wide figure: cashPotBalance() sums the pot across every
                     company, so it is shown only to someone entitled to see all of
                     them. A company-locked user would otherwise read the group's
                     cash as their own. --}}
                @can('view all expense')
                @if (!is_null($cashPotBalance))
                <div class="exp-summary-card green">
                    <span class="sc-icon"><i class="fas fa-money-bill-wave"></i></span>
                    <div>
                        <div class="sc-label">Cash in hand</div>
                        <div class="sc-value">৳ {{ number_format($cashPotBalance, 2) }}</div>
                        <div class="sc-sub">{{ $cashPotName }} · all companies</div>
                    </div>
                </div>
                @endif
                @endcan

                {{-- Only when a ceiling actually exists. DailyFundService totals
                     cover companies that have one set, so with none configured this
                     card would read "৳0 spent" and mean nothing. --}}
                @if ($fundTotal > 0)
                <div class="exp-summary-card blue">
                    <span class="sc-icon"><i class="fas fa-gauge-high"></i></span>
                    <div>
                        <div class="sc-label">Today's allowance</div>
                        <div class="sc-value">৳ {{ number_format($fundSpent, 2) }}</div>
                        <div class="sc-sub">of ৳ {{ number_format($fundTotal, 2) }} · ৳ {{ number_format(max($fundTotal - $fundSpent, 0), 2) }} left</div>
                    </div>
                </div>
                @endif
            </div>

            <div class="states-table-content">

                {{-- Filter --}}
                <form action="" method="get">
                    <div class="filter-container">
                        <div class="filter-header">
                            <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="filter-content">
                            <div class="closest filter-row">
                                <div class="filter-group">
                                    <label for="user_id">User</label>
                                    <select id="user_id" name="user_id" class="form-control select2" style="width:100%">
                                        <option value="">All</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" {{ $user->id == request('user_id') ? 'selected' : '' }}>{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="company_id">Company</label>
                                    <select id="company_id" name="company_id" class="form-control select2" style="width:100%">
                                        <option value="">All</option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" {{ $company->id == request('company_id') ? 'selected' : '' }}>{{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="expense_category_id">Category</label>
                                    <select id="expense_category_id" name="expense_category_id"
                                        onchange="getExpSubCategory(this, '#expense_sub_category_id')"
                                        data-action="{{ route('role.get-expense-sub-category', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                                        class="form-control select2" style="width:100%">
                                        <option value="">All</option>
                                        @foreach ($expense_categories as $expense_category)
                                            <option value="{{ $expense_category->id }}" {{ $expense_category->id == request('expense_category_id') ? 'selected' : '' }}>{{ $expense_category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="expense_sub_category_id">Sub-Category</label>
                                    <select id="expense_sub_category_id" name="expense_sub_category_id" class="form-control select2" style="width:100%">
                                        <option value="">All</option>
                                        @if (!empty($req_subdatas))
                                            @foreach ($req_subdatas as $subdata)
                                                <option value="{{ $subdata->id }}" {{ $subdata->id == request('expense_sub_category_id') ? 'selected' : '' }}>{{ $subdata->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="closest filter-row">
                                <div class="filter-group">
                                    <label for="bank_id">Bank</label>
                                    <select id="bank_id" name="bank_id" class="form-control select2" style="width:100%">
                                        <option value="">All</option>
                                        @foreach ($banks as $bank)
                                            <option value="{{ $bank->id }}" {{ $bank->id == request('bank_id') ? 'selected' : '' }}>{{ $bank->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="payment_source">Paid From</label>
                                    <select id="payment_source" name="payment_source" class="form-control select2" style="width:100%">
                                        <option value="">All</option>
                                        <option value="petty" {{ request('payment_source') === 'petty' ? 'selected' : '' }}>Petty Cash</option>
                                        <option value="bank"  {{ request('payment_source') === 'bank'  ? 'selected' : '' }}>Bank</option>
                                        <option value="cash"  {{ request('payment_source') === 'cash'  ? 'selected' : '' }}>Cash in Hand</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="approval_status">Approval</label>
                                    <select id="approval_status" name="approval_status" class="form-control select2" style="width:100%">
                                        <option value="">All</option>
                                        <option value="{{ \App\Models\Expense::PENDING }}" {{ request('approval_status') === \App\Models\Expense::PENDING ? 'selected' : '' }}>Pending</option>
                                        <option value="{{ \App\Models\Expense::APPROVED }}" {{ request('approval_status') === \App\Models\Expense::APPROVED ? 'selected' : '' }}>Approved</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control select2" style="width:100%">
                                        <option value="">All</option>
                                        <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="expense_date">Date</label>
                                    <input type="date" name="expense_date" value="{{ request('expense_date') }}" id="expense_date" class="form-control">
                                </div>
                                <div class="filter-group">
                                    <label for="title">Title</label>
                                    <input type="text" name="title" value="{{ request('title') }}" id="title" class="form-control" placeholder="Search title…">
                                </div>
                            </div>
                            <div class="filter-actions">
                                <button type="button" class="reset-btn">Reset</button>
                                <button type="submit" class="apply-btn">Apply Filters</button>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Table --}}
                <div class="table-responsive overflow-x-auto" style="padding:15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200 expense-table">
                        <thead class="bg-gray-50">
                            <tr>
                                {{-- # and Actions stay plain: one is a row counter, the
                                     other holds buttons. Sorting is server-side, so it
                                     orders the whole list, not just this page. --}}
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'title', 'label' => 'Title'])</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'category', 'label' => 'Category'])</th>
                                {{-- Company only where it can differ. A company-locked user
                                sees their own name on every row, which is a column of
                                width spent saying nothing. --}}
                                @can('view all expense')
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'company', 'label' => 'Company'])</th>
                                @endcan
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'department', 'label' => 'Department'])</th>
                                {{-- Paid From is not sortable, and cannot be: the cell under it is
                                     decided by three columns in precedence order — own pocket, then
                                     float, then bank, then the drawer — so there is no single column
                                     to order by. A header that looked clickable would order the list
                                     by something other than what it shows. --}}
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid From</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'date', 'label' => 'Date', 'dirDefault' => 'desc'])</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'amount', 'label' => 'Amount', 'dirDefault' => 'desc'])</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'status', 'label' => 'Status'])</th>

                                @canany(['view expense', 'edit expense', 'delete expense'])
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)
                                <tr class="hover:bg-teal-50 transition-colors duration-150">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-500" data-label="#">
                                        {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                                    </td>
                                    <td class="px-4 py-3" data-label="Title">
                                        <div class="title-cell">
                                            <span class="title-dot"></span>
                                            <span class="font-medium text-gray-800 text-sm">{{ $value->title }}</span>
                                        </div>
                                        @if($value->reference)
                                            <div class="text-xs text-gray-400 ml-4 mt-0.5">Ref: {{ $value->reference }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3" data-label="Category">
                                        <div class="cat-hier">
                                            @if($value->expense_category?->name)
                                                <span class="cat-badge-exp"><i class="fas fa-tags" style="font-size:9px"></i>{{ $value->expense_category->name }}</span>
                                            @endif
                                            @if($value->expense_sub_category?->name)
                                                <span class="subcat-badge-exp"><i class="fas fa-sitemap" style="font-size:8px"></i>{{ $value->expense_sub_category->name }}</span>
                                            @endif
                                            @if(!$value->expense_category?->name)
                                                <span class="text-gray-400 text-xs">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    @can('view all expense')
                                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap" data-label="Company">{{ $value->company?->name ?? '—' }}</td>
                                    @endcan
                                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap" data-label="Department">
                                        {{ $value->expenseDepartment?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap" data-label="Paid From">
                                        {{-- Float first, then bank, then the drawer — the same order of
                                             precedence settlementAccountId() applies when it decides which
                                             account to credit. Read the float and the bank themselves rather
                                             than payment_mode: the two can disagree, and it is the settlement
                                             account that says where the money actually came from. --}}
                                        @if ($value->reimburse_to_user_id)
                                            <span class="pay-src pay-src-owed"><i class="fas fa-hand-holding-dollar" style="font-size:9px"></i>Own Pocket</span>
                                            <div class="pay-src-sub">{{ $value->reimburseTo?->name ?? 'Staff member removed' }} — owed</div>
                                        @elseif ($value->pettyCashFloat)
                                            <span class="pay-src pay-src-petty"><i class="fas fa-wallet" style="font-size:9px"></i>Petty Cash</span>
                                            <div class="pay-src-sub">{{ $value->pettyCashFloat->custodian?->name ?? 'Custodian removed' }}</div>
                                        @elseif ($value->bank)
                                            <span class="pay-src pay-src-bank"><i class="fas fa-university" style="font-size:9px"></i>Bank</span>
                                            <div class="pay-src-sub">{{ $value->bank->name }}</div>
                                        @else
                                            <span class="pay-src pay-src-cash"><i class="fas fa-money-bill-wave" style="font-size:9px"></i>Cash in Hand</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600" data-label="Date">
                                        <i class="fas fa-calendar-alt text-gray-400 mr-1" style="font-size:11px"></i>{{ $value->expense_date }}
                                    </td>
                                    <td class="px-4 py-3" data-label="Amount">
                                        <span class="amount-cell">{{ number_format($value->amount, 2) }}</span>
                                    </td>
                                    <td class="px-4 py-3" data-label="Status">
                                        {{-- Approval first: it is the one that says whether the money
                                             is in the accounts. Active/Inactive is the record's own
                                             flag and no longer decides anything about the ledger. --}}
                                        @if ($value->approval_status === \App\Models\Expense::APPROVED)
                                            <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-semibold px-2.5 py-1 rounded-full"
                                                  title="Posted to the ledger{{ $value->approver?->name ? ' by ' . $value->approver->name : '' }}{{ $value->approved_at ? ' on ' . $value->approved_at->format('d M Y') : '' }}">
                                                <i class="fas fa-check-circle" style="font-size:10px"></i>Approved
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 text-xs font-semibold px-2.5 py-1 rounded-full"
                                                  title="Not posted to the ledger yet">
                                                <i class="fas fa-hourglass-half" style="font-size:10px"></i>Pending
                                            </span>
                                        @endif
                                        @unless ($value->status)
                                            <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 text-xs font-semibold px-2.5 py-1 rounded-full mt-1"
                                                  title="Inactive record">
                                                <i class="fas fa-circle" style="font-size:6px"></i>Inactive
                                            </span>
                                        @endunless
                                    </td>
                                    @canany(['view expense', 'edit expense', 'delete expense'])
                                    @php
                                        // Posted to the ledger. update() and destroy() both refuse this
                                        // state and tell the user to reverse first — the row stops
                                        // offering them so that refusal is not discovered only after the
                                        // whole edit form has been filled in.
                                        $isPosted = $value->approval_status === \App\Models\Expense::APPROVED;
                                    @endphp
                                    <td class="px-4 py-3 td-actions" data-label="">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @can('approve expense')
                                                @if ($isPosted)
                                                    <button class="action-btn reverse js-expense-reverse"
                                                        data-id="{{ $value->id }}"
                                                        data-title="{{ $value->title }}"
                                                        data-amount="{{ number_format($value->amount, 2) }}"
                                                        data-url="{{ route('role.expenses.reverse', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $value->id]) }}"
                                                        title="Reverse — posts the opposite entry, keeps both on record">
                                                        <i class="fas fa-rotate-left"></i>
                                                    </button>
                                                @elseif ($value->status)
                                                    <button class="action-btn approve js-expense-approve"
                                                        data-id="{{ $value->id }}"
                                                        data-title="{{ $value->title }}"
                                                        data-amount="{{ number_format($value->amount, 2) }}"
                                                        data-url="{{ route('role.expenses.approve', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $value->id]) }}"
                                                        title="Approve — posts this expense to the ledger">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                @endif
                                            @endcan
                                            @can('view expense')
                                            <a href="{{ route('role.expenses.slip', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $value->id]) }}"
                                               target="_blank"
                                               class="btn btn-outline-success border border-green-500 text-green-600 hover:bg-green-500 hover:text-white px-3 py-1 rounded-md transition duration-200"
                                               title="Print Slip">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endcan
                                            @can('edit expense')
                                            {{-- The wrapper carries the tooltip: a disabled button does not
                                                 fire mouse events in every browser, so its own title can go
                                                 unshown — and the reason it is disabled is the whole point. --}}
                                            <span class="inline-flex" @if ($isPosted) title="Posted to the ledger — reverse this expense before editing" @endif>
                                            <button class="action-btn edit edit-item-btn" @disabled($isPosted)
                                                data-item_id="{{ $value->id }}"
                                                data-bank_id="{{ $value->bank_id }}"
                                                data-petty_cash_float_id="{{ $value->petty_cash_float_id }}"
                                                data-reimburse_to_user_id="{{ $value->reimburse_to_user_id }}"
                                                data-account_id="{{ $value->account_id }}"
                                                data-company_id="{{ $value->company_id }}"
                                                data-expense_department_id="{{ $value->expense_department_id }}"
                                                data-other_note="{{ $value->other_note }}"
                                                data-expense_category_id="{{ $value->expense_category_id }}"
                                                data-expense_sub_category_id="{{ $value->expense_sub_category_id }}"
                                                data-title="{{ $value->title }}"
                                                data-description="{{ $value->description }}"
                                                data-amount="{{ $value->amount }}"
                                                data-payment_mode="{{ $value->payment_mode }}"
                                                data-attachment="{{ $value->attachment }}"
                                                data-reference="{{ $value->reference }}"
                                                data-expense_date="{{ $value->expense_date }}"
                                                data-status="{{ $value->status }}"
                                                data-action="{{ route('role.get-expense-sub-category', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                                                title="{{ $isPosted ? 'Reverse this expense before editing' : 'Edit' }}">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            </span>
                                            @endcan
                                            @can('delete expense')
                                            <span class="inline-flex" @if ($isPosted) title="Posted to the ledger — reverse this expense before deleting" @endif>
                                            <button class="action-btn delete" @disabled($isPosted)
                                                onclick="confirmDelete('{{ $value->id }}', '{{ addslashes($value->title) }}')"
                                                title="{{ $isPosted ? 'Reverse this expense before deleting' : 'Delete' }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            </span>
                                            @endcan
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center py-14">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background:#f0fdfa">
                                            <i class="fas fa-file-invoice-dollar text-2xl" style="color:#14b8a6"></i>
                                        </div>
                                        <h4 class="text-gray-600 font-semibold text-lg">No expenses found</h4>
                                        <p class="text-gray-400 text-sm">Try adjusting your filters or add a new expense.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="p-3 border-t border-gray-200">
                    {{ $datas->appends(request()->all())->links() }}
                </div>
            </div>
        </div>
    </div>

    @include('expenses.create-modal')
    @include('expenses.edit-modal')
    @include('expenses.delete-modal')

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

            $('.create-new-btn').click(function() {
                $('#createModal').removeClass('hidden');
                if ($('#create_items_body tr').length === 0) {
                    addItemRow('create');
                }
            });

            $('.edit-item-btn').click(function() {
                const item_id                 = $(this).data('item_id');
                const bank_id                 = $(this).data('bank_id');
                const account_id              = $(this).data('account_id');
                const company_id              = $(this).data('company_id');
                const expense_category_id     = $(this).data('expense_category_id');
                const expense_sub_category_id = $(this).data('expense_sub_category_id');
                const title                   = $(this).data('title');
                const description             = $(this).data('description');
                const amount                  = $(this).data('amount');
                const payment_mode            = $(this).data('payment_mode');
                const attachment              = $(this).data('attachment');
                const reference               = $(this).data('reference');
                const expense_date            = $(this).data('expense_date');
                const status                  = $(this).data('status');

                $('#editItemId').val(item_id);
                $('#edit_bank_id').val(bank_id).trigger('change');
                // Set before synceditPayment() runs below, so a cash expense
                // reopens showing the float it was actually settled against.
                $('#edit_petty_cash_float_id').val($(this).data('petty_cash_float_id') || '').trigger('change');
                // Same reason, and it decides which of the two CASH cards opens:
                // sync() tells "Petty Cash" from "My Own Pocket" by this field
                // alone, since both are payment_mode 'cash'. Left unset, a claim
                // would reopen looking like a float spend.
                $('#edit_reimburse_to_user_id').val($(this).data('reimburse_to_user_id') || '');
                $('#edit_account_id').val(account_id).trigger('change');
                $('#edit_company_id').val(company_id).trigger('change');
                $('#edit_title').val(title);
                $('#edit_description').val(description);
                $('#edit_amount').val(amount);
                $('#edit_payment_mode').val(payment_mode).trigger('change');
                if (attachment) {
                    $('#preview_attc').attr('src', window.location.origin + '/' + attachment).removeClass('hidden');
                } else {
                    $('#preview_attc').addClass('hidden').attr('src', '');
                }
                $('#edit_reference').val(reference);
                // Native dispatch, not .trigger(): the cash-limit meter listens
                // with addEventListener, and jQuery's trigger does not reach a
                // native listener for a change event. Without this an old
                // expense opens showing TODAY's limit against a date months back.
                $('#edit_expense_date').val(expense_date);
                document.getElementById('edit_expense_date')
                    ?.dispatchEvent(new Event('change', { bubbles: true }));
                $('#edit_status').val(status).trigger('change');
                // Load items for this expense
                $('#edit_items_body').empty();
                $('#edit_items_total').text('0.00');
                const itemsUrl = '{{ url(Str::slug(Auth::user()->getRoleNames()->first()) . "/expenses") }}/' + item_id + '/items';
                $.get(itemsUrl, function(response) {
                    if (response.success && response.items.length > 0) {
                        response.items.forEach(function(item) {
                            addItemRow('edit', item.description, item.amount);
                        });
                    } else {
                        addItemRow('edit');
                    }
                    calcTotal('edit');
                }).fail(function() {
                    addItemRow('edit');
                });

                $('#editModal').removeClass('hidden');

                /* The classification picker rebuilds its own lists — it holds the
                   whole graph — so it is handed the four ids in one event rather
                   than being populated field by field over AJAX. That also keeps
                   the resolve-upward rules in one place instead of half here. */
                const clsRoot = document.querySelector('[data-classification="edit"]');
                if (clsRoot) {
                    clsRoot.dispatchEvent(new CustomEvent('classification:sync', {
                        detail: {
                            company_id: company_id,
                            expense_department_id: $(this).data('expense_department_id'),
                            expense_category_id: expense_category_id,
                            expense_sub_category_id: expense_sub_category_id,
                            other_note: $(this).data('other_note'),
                        }
                    }));
                }

                // The bank picker only exists for methods that use one.
                if (window.synceditPayment) window.synceditPayment();
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

            $('.close-btn').click(function() { $(this).closest('.alert').addClass('hidden'); });

            // Create
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (!validateCreateForm()) return;

                const sendCreate = function () {
                    let formData = new FormData($('#createForm')[0]);
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Done', text: 'Data created successfully!' });
                                $('#createModal').addClass('hidden');
                                $('#createForm')[0].reset();
                                $('#create_items_body').empty();
                                $('#create_items_total').text('0.00');
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                            }
                        },
                        error: function () {
                            Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create data.' });
                        }
                    });
                };

                /* Over today's cash limit — ask, do not refuse.
                   Blocking would stop a genuine urgent cost dead, and the person
                   filing it would then put it under a company that still had
                   room, which is worse than an honest overspend: the limit would
                   look respected and the books would be wrong. So the save always
                   goes through; it just cannot happen by accident.

                   Absent or unreadable, the helper returns null and this is a
                   plain save — the daily fund can never prevent an expense from
                   being recorded. */
                const overFund = (typeof window.expDailyFundVerdictcreate === 'function')
                    ? window.expDailyFundVerdictcreate()
                    : null;

                if (!overFund) {
                    sendCreate();
                    return;
                }

                const taka = (n) => '৳ ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                Swal.fire({
                    icon: 'warning',
                    title: "Over today's cash fund",
                    html: '<div style="text-align:left;font-size:14px;line-height:1.6;">'
                        + '<strong>' + $('<div>').text(overFund.name).html() + '</strong> has a daily cash limit of <strong>'
                        + taka(overFund.fund) + '</strong>, and <strong>' + taka(overFund.already)
                        + '</strong> has already been spent today.<br><br>'
                        + 'This expense of <strong>' + taka(overFund.typing) + '</strong> takes the day to <strong>'
                        + taka(overFund.after) + '</strong> — <strong style="color:#c2410c;">'
                        + taka(-overFund.left) + ' over</strong>.<br><br>'
                        + '<span style="color:#64748b;font-size:12.5px;">The limit is a control, not a block. Saving is fine '
                        + 'if the cost is real — the overspend will simply show on the Petty Cash page.</span>'
                        + '</div>',
                    showCancelButton: true,
                    confirmButtonText: 'Save anyway',
                    cancelButtonText: 'Go back',
                    confirmButtonColor: '#ea580c',
                    reverseButtons: true,
                }).then(function (r) {
                    if (r.isConfirmed) sendCreate();
                });
            });

            // Edit
            $('#editSubmit').click(function() {
                if (!validateEditForm()) return;
                let formData = new FormData($('#editForm')[0]);
                $.ajax({
                    url: $(this).data('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Expense updated successfully!' });
                            $('#editModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            });

            // Delete
            $('#confirmDeleteBtn').click(function() {
                const dataId    = $(this).data('item-id');
                const deleteUrl = $(this).data('action');
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: { item_id: dataId },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Expense deleted successfully!' });
                            $('#deleteModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            });
        });

        function getExpSubCategory(obj, targetId) {
            $.ajax({
                url: $(obj).data('action'),
                method: 'GET',
                data: { expense_category_id: $(obj).val() },
                success: function(response) {
                    if (response.success) {
                        const targetSelect = $(obj).closest('.closest').find(targetId);
                        const wrapper = targetSelect.closest('[id$="_subcategory_wrapper"]');
                        targetSelect.empty();
                        targetSelect.append('<option value="">Select an Item</option>');
                        if (response.data && response.data.length > 0) {
                            $.each(response.data, function(index, item) {
                                targetSelect.append(`<option value="${item.id}">${item.name}</option>`);
                            });
                            wrapper.removeClass('hidden');
                        } else {
                            wrapper.addClass('hidden');
                        }
                        if (targetSelect.hasClass('select2-hidden-accessible')) {
                            targetSelect.trigger('change.select2');
                        }
                    } else {
                        Swal.fire({ icon: "error", title: "Opps...", text: response.message });
                    }
                }
            });
        }

        function select2SetValueNoEvent(selectId, value) {
            var $select = $(selectId);
            $select.val(value);
            var text = $select.find('option:selected').text() || '';
            $select.data('select2').$container.find('.select2-selection__rendered').text(text);
        }

        function validateCreateForm() {
            let isValid = true;
            $('#createForm .error-message').addClass('hidden');
            $('#create_company_msg, #create_department_msg').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');
            // No title check: the field is hidden and the server derives it from
            // the first cost line (ExpenseController::resolveExpenseTitle).
            if (!$('#create_expense_date').val().trim()) {
                $('#create_expense_date').next('.error-message').removeClass('hidden');
                $('#create_expense_date').addClass('border-red-500');
                isValid = false;
            }
            // Company and Department are required too — both decide whose
            // books this lands in, and the server refuses without them.
            if (!$('#create_company_id').val()) {
                $('#create_company_msg').removeClass('hidden');
                $('#create_company_id').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#create_expense_department_id').val()) {
                $('#create_department_msg').removeClass('hidden');
                $('#create_expense_department_id').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#create_expense_category_id').val()) {
                $('#create_expense_category_msg').removeClass('hidden');
                isValid = false;
            }
            const hasItem = $('#create_items_body .item-amount').toArray().some(el => parseFloat(el.value) > 0);
            if (!hasItem) {
                $('#create_items_msg').removeClass('hidden');
                isValid = false;
            }
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            $('#editForm .error-message').addClass('hidden');
            $('#edit_company_msg, #edit_department_msg').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            // No title check — see the create form above.
            if (!$('#edit_expense_date').val().trim()) {
                $('#edit_expense_date').next('.error-message').removeClass('hidden');
                $('#edit_expense_date').addClass('border-red-500');
                isValid = false;
            }
            // Company and Department are required too — both decide whose
            // books this lands in, and the server refuses without them.
            if (!$('#edit_company_id').val()) {
                $('#edit_company_msg').removeClass('hidden');
                $('#edit_company_id').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#edit_expense_department_id').val()) {
                $('#edit_department_msg').removeClass('hidden');
                $('#edit_expense_department_id').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#edit_expense_category_id').val()) {
                $('#edit_expense_category_msg').removeClass('hidden');
                isValid = false;
            }
            const hasItem = $('#edit_items_body .item-amount').toArray().some(el => parseFloat(el.value) > 0);
            if (!hasItem) {
                $('#edit_items_msg').removeClass('hidden');
                isValid = false;
            }
            return isValid;
        }

        function addItemRow(prefix, desc = '', amount = '') {
            const idx = Date.now() + Math.random();
            const safeDesc = String(desc).replace(/"/g, '&quot;');
            const row = `<tr class="item-row border-b border-gray-100">
                <td class="px-2 py-1">
                    <input type="text" name="items[${idx}][description]" value="${safeDesc}"
                        class="form-input w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Description">
                </td>
                <td class="px-2 py-1">
                    <input type="number" name="items[${idx}][amount]" value="${amount}"
                        class="item-amount form-input w-full px-2 py-1 border border-gray-300 rounded text-sm text-right"
                        placeholder="0.00" step="0.01" min="0" oninput="calcTotal('${prefix}')">
                </td>
                <td class="px-2 py-1 text-center">
                    <button type="button" onclick="removeItemRow(this, '${prefix}')"
                        class="text-red-400 hover:text-red-600 transition"><i class="fas fa-times"></i></button>
                </td>
            </tr>`;
            $(`#${prefix}_items_body`).append(row);
        }

        function removeItemRow(btn, prefix) {
            $(btn).closest('tr').remove();
            calcTotal(prefix);
        }

        function calcTotal(prefix) {
            let total = 0;
            $(`#${prefix}_items_body .item-amount`).each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            $(`#${prefix}_items_total`).text(total.toFixed(2));

            // The read-only Total at the top of the form is the same figure. It is
            // shown twice because that is where people look for it, not because
            // there are two amounts — only the cost lines are ever submitted.
            $(`#${prefix}_amount`).val(total.toFixed(2));
        }

        function resetCreateForm() {
            $('#createForm')[0].reset();
            $('#createForm .error-message').addClass('hidden');
            $('#create_company_msg, #create_department_msg').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');
        }

        function confirmDelete(id, name = null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterHeader  = document.querySelector('.filter-container .filter-header');
            const filterContent = document.querySelector('.filter-container .filter-content');
            filterHeader.addEventListener('click', function() {
                this.classList.toggle('active');
                filterContent.classList.toggle('active');
            });
            document.querySelector('.reset-btn').addEventListener('click', function(e) {
                e.preventDefault();
                window.location = "{{ route('role.expenses.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";
            });
        });
    </script>

    {{-- Approve / Reverse.
         Both confirm first: approving puts money in the accounts and reversing
         takes it back out, and neither is undone by pressing back. Delegated
         binding so the buttons keep working after the table repaints. --}}
    <script>
        $(function () {
            function ledgerAction($btn, opts) {
                Swal.fire({
                    icon: opts.icon,
                    title: opts.title,
                    html: opts.html,
                    showCancelButton: true,
                    confirmButtonText: opts.confirm,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: opts.colour,
                    reverseButtons: true,
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $btn.prop('disabled', true);

                    $.ajax({
                        url: $btn.data('url'),
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        success: function (res) {
                            if (res.success) {
                                Swal.fire({ icon: 'success', title: 'Done', text: res.message })
                                    .then(function () { location.reload(); });
                            } else {
                                $btn.prop('disabled', false);
                                Swal.fire({ icon: 'error', title: 'Not done', text: res.message || 'Something went wrong.' });
                            }
                        },
                        error: function (xhr) {
                            $btn.prop('disabled', false);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'The request failed.',
                            });
                        },
                    });
                });
            }

            $(document).on('click', '.js-expense-approve', function () {
                const $b = $(this);
                ledgerAction($b, {
                    icon: 'question',
                    title: 'Approve this expense?',
                    html: '<b>' + $b.data('title') + '</b> — ' + $b.data('amount')
                        + '<br><span style="font-size:13px;color:#64748b">It will be posted to the ledger. '
                        + 'After that it can only be reversed, not edited or deleted.</span>',
                    confirm: 'Approve &amp; post',
                    colour: '#0d9488',
                });
            });

            $(document).on('click', '.js-expense-reverse', function () {
                const $b = $(this);
                ledgerAction($b, {
                    icon: 'warning',
                    title: 'Reverse this posting?',
                    html: '<b>' + $b.data('title') + '</b> — ' + $b.data('amount')
                        + '<br><span style="font-size:13px;color:#64748b">The opposite entry is written and both stay on the record. '
                        + 'The expense returns to Pending so it can be corrected.</span>',
                    confirm: 'Reverse',
                    colour: '#ea580c',
                });
            });
        });
    </script>

    {{-- The classification picker and the payment behaviour, wired for both the
         create and the edit modal from one implementation each. --}}
    @include('expenses.partials.classification-js')
    @include('expenses.partials.payment-js')


    {{-- ── Recording a DM renewal ───────────────────────────────────────────
         Rendered ONLY when the Subscriptions desk sent us here. Without these
         query parameters this @if produces nothing at all, so for every ordinary
         visit to the expense list the page is byte-identical to what it was
         before this block existed.

         It deliberately reuses the existing open path (.create-new-btn) rather
         than reimplementing it, so the modal opens, the first cost line is added
         and the totals wire themselves up exactly as they do for a hand-filed
         expense. All this adds is: fill some values, and carry the DM reference
         through as hidden inputs so ExpenseController::store() can link the two.

         Classification is left blank on purpose — DM's `access_type` is its own
         taxonomy, not this ledger's chart of accounts, so the category is chosen
         by hand rather than guessed. --}}
    @if (request()->filled('dm_source_type') && request()->filled('dm_id') && request()->filled('dm_due_date'))
        @php
            $dmPrefill = request()->only([
                'dm_source_type', 'dm_id', 'dm_group_id', 'dm_due_date',
                'dm_title', 'dm_amount', 'dm_currency',
            ]);
        @endphp
        <script>
            $(function () {
                var dm = {!! json_encode($dmPrefill) !!};

                var form = document.getElementById('createForm');
                if (!form) return;

                // Opens the modal and adds the first cost line through the page's
                // own handler — no second copy of that logic to drift.
                $('.create-new-btn').first().trigger('click');

                $('#create_expense_date').val(dm.dm_due_date);
                $('#create_reference').val('DM-' + String(dm.dm_source_type).toUpperCase() + '-' + dm.dm_id + '-' + dm.dm_due_date);
                $('#create_description').val('Renewal recorded from the Subscriptions desk — ' + (dm.dm_title || ''));

                // The cost line. dm_amount is only ever sent for a BDT commitment;
                // a foreign-currency bill arrives blank so that whoever files it
                // types the taka that actually left the bank.
                var $row = $('#create_items_body tr').first();
                $row.find('input[name$="[description]"]').val(dm.dm_title || '');
                if (dm.dm_amount) {
                    $row.find('input[name$="[amount]"]').val(dm.dm_amount).trigger('input');
                }

                // What ties the resulting expense back to the renewal period.
                $.each(dm, function (key, value) {
                    if (value === null || value === '' || key === 'dm_amount' || key === 'dm_currency') return;
                    $('<input>').attr({ type: 'hidden', name: key }).val(value).appendTo(form);
                });
            });
        </script>
    @endif
@endsection
