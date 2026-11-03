@extends('layout.app')
@section('meta-information')
    <title>Manage Expenses</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
@include('expenses.partials.payment-source-css')
<style>
    .expense-entry-modal .expense-entry-details-row { grid-template-columns:minmax(0,1fr); }
    .expense-entry-modal .expense-entry-details-row > div { min-width:0; }
    @media (min-width:768px) {
        .expense-entry-modal .expense-entry-details-row { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (min-width:1200px) {
        .expense-entry-modal .expense-entry-details-row { grid-template-columns:minmax(150px,.9fr) minmax(260px,1.4fr) minmax(110px,.65fr) minmax(170px,1fr) minmax(190px,1.15fr); gap:12px; }
    }
    .expense-entry-modal .expense-cost-table td { padding:12px 10px; vertical-align:middle; }
    .expense-entry-modal .expense-cost-table th { padding:12px 10px; font-size:12px; }
    .expense-entry-modal .expense-cost-table input { min-height:38px; border-radius:7px; }
    .expense-entry-modal .expense-cost-table tbody tr:hover { background:#f8fafc; }
    .expense-entry-modal .expense-cost-table tfoot { background:#f0fdfa; }
    .expense-entry-modal .expense-cost-table .select2-container { min-width:0; flex:1; }
    .expense-entry-modal .expense-cost-table .select2-selection--single { height:38px!important; border-color:#cbd5e1; border-radius:7px; }
    .expense-entry-modal .expense-cost-table .select2-selection__rendered { line-height:36px!important; font-size:12px; }
    .expense-entry-modal .expense-cost-table .select2-selection__arrow { height:36px!important; }
    .expense-entry-modal .select2-dropdown { font-size:13px; }
    .expense-entry-modal .modal-footer { position:sticky; bottom:0; z-index:10; background:#fff; box-shadow:0 -4px 14px rgba(15,23,42,.04); }
    @media (min-width:900px) {
        .expense-entry-modal [data-classification] > .grid { grid-template-columns:minmax(150px,1fr) minmax(150px,1fr) minmax(130px,.75fr) minmax(245px,1.4fr); gap:12px; }
        .expense-entry-modal .expense-reference-user,
        .expense-entry-modal .expense-reference-number { grid-column:span 1; }
    }

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

    /* Compact register filters. */
    .exp-filter-bar { margin:14px 14px 0; padding:10px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; overflow-x:auto; }
    .exp-filter-grid {
        display:grid!important;
        grid-template-columns:minmax(220px,1fr) auto minmax(300px,380px) auto;
        gap:7px; align-items:center; min-width:900px;
    }
    .exp-filter-field { min-width:0; }
    .exp-filter-field label { display:block; margin:0 0 4px; color:#64748b; font-size:9px; font-weight:800; letter-spacing:.06em; line-height:1; text-transform:uppercase; }
    .exp-filter-field input,.exp-filter-field select { width:100%; height:34px; padding:0 9px; border:1px solid #dbe3ee; border-radius:7px; background:#fff; color:#334155; font-size:11px; outline:none; }
    .exp-filter-field input:focus,.exp-filter-field select:focus { border-color:#14b8a6; box-shadow:0 0 0 3px rgba(20,184,166,.1); }
    .exp-filter-field .select2-container { width:100%!important; }
    .exp-filter-field .select2-selection--single { height:34px!important; border-color:#dbe3ee!important; border-radius:7px!important; }
    .exp-filter-field .select2-selection__rendered { line-height:32px!important; padding-left:9px!important; font-size:11px; color:#334155!important; }
    .exp-filter-field .select2-selection__arrow { height:32px!important; }
    .exp-filter-actions { display:flex; gap:7px; align-items:center; align-self:end; }
    .exp-filter-actions button { height:34px; padding:0 12px; border-radius:7px; cursor:pointer; font-size:11px; font-weight:800; white-space:nowrap; }
    .exp-filter-actions .apply-btn { border:1px solid #0d9488; background:#0d9488; color:#fff; }
    .exp-filter-actions .apply-btn:hover { background:#0f766e; }
    .exp-filter-actions .reset-btn { border:1px solid #cbd5e1; background:#fff; color:#64748b; }
    .exp-filter-actions .reset-btn:hover { border-color:#fca5a5; background:#fef2f2; color:#dc2626; }
    .exp-filter-bar > .filter-header { display:none; }
    .exp-filter-grid.filter-content { display:grid!important; max-height:none!important; padding:0!important; overflow:visible!important; }
    .exp-filter-grid .filter-row { display:contents; }
    .exp-filter-grid .filter-group { min-width:0; margin:0!important; }
    .exp-filter-grid .filter-group label { display:block; margin:0 0 4px; color:#64748b; font-size:9px; font-weight:800; letter-spacing:.06em; line-height:1; text-transform:uppercase; }
    .exp-filter-grid .filter-group .form-control { width:100%; height:34px; min-height:34px; padding:0 9px; border:1px solid #dbe3ee; border-radius:7px; background:#fff; color:#334155; font-size:11px; }
    .exp-filter-grid .filter-group .select2-container { width:100%!important; }
    .exp-filter-grid .filter-group .select2-selection--single { height:34px!important; border-color:#dbe3ee!important; border-radius:7px!important; }
    .exp-filter-grid .filter-group .select2-selection__rendered { line-height:32px!important; padding-left:9px!important; font-size:11px; color:#334155!important; }
    .exp-filter-grid .filter-group .select2-selection__arrow { height:32px!important; }
    .exp-filter-grid .filter-actions { display:flex; gap:7px; align-items:center; justify-content:flex-end; margin:0!important; align-self:end; }
    .exp-filter-grid .filter-group:has(#title) { order:-20; grid-column:auto; }
    .exp-filter-grid .filter-group:has(#title) label { display:none; }
    .exp-filter-grid .filter-group:has(#user_id),
    .exp-filter-grid .filter-group:has(#company_id),
    .exp-filter-grid .filter-group:has(#expense_category_id),
    .exp-filter-grid .filter-group:has(#expense_sub_category_id),
    .exp-filter-grid .filter-group:has(#bank_id),
    .exp-filter-grid .filter-group:has(#payment_source),
    .exp-filter-grid .filter-group:has(#approval_status),
    .exp-filter-grid .filter-group:has(#status) { display:none!important; }
    .exp-filter-quick { order:-19; display:inline-flex; height:34px; overflow:hidden; border:1px solid #dbe3ee; border-radius:7px; background:#fff; }
    .exp-filter-quick a { display:inline-flex; align-items:center; padding:0 10px; border-left:1px solid #e2e8f0; color:#64748b; font-size:10px; font-weight:700; text-decoration:none; white-space:nowrap; }
    .exp-filter-quick a:first-child { border-left:0; }
    .exp-filter-quick a:hover,.exp-filter-quick a.is-on { background:#ecfdf5; color:#0f766e; text-decoration:none; }
    .exp-filter-dates { order:-18; display:grid; grid-template-columns:minmax(115px,1fr) auto minmax(115px,1fr); gap:5px; align-items:center; }
    .exp-filter-dates span { color:#94a3b8; font-size:10px; }
    .exp-filter-dates input { width:100%; height:34px; padding:0 7px; border:1px solid #dbe3ee; border-radius:7px; background:#fff; color:#334155; font-size:10px; }
    .exp-filter-actions .print-btn { display:inline-flex; align-items:center; gap:5px; height:34px; padding:0 11px; border:1px solid #cbd5e1; border-radius:7px; background:#fff; color:#475569; font-size:11px; font-weight:800; text-decoration:none; white-space:nowrap; }
    .exp-filter-actions .print-btn:hover { background:#f8fafc; color:#0f766e; text-decoration:none; }
    @media(max-width:1150px){.exp-filter-grid{grid-template-columns:minmax(220px,1fr) auto minmax(300px,380px) auto!important;}}

    /* Amount cell */
    .amount-cell {
        font-weight: 700;
        color: #0f766e;
        font-size: 13.5px;
        white-space: nowrap;
    }
    .amount-cell::before { content: '৳ '; font-size: 11px; font-weight: 500; color: #5eead4; }

    /* ── Column density ───────────────────────────────────────────────────
       Ten columns have to fit at 90% browser zoom, not just at 70%.

       The horizontal padding has to be overridden HERE, at this specificity,
       and not by changing the cells' Tailwind classes. layout/table-design sets
       `padding: 1rem 0.75rem` via `.states-table .states-table-content .table
       tbody td` — three classes deep — which outranks a single-class `.px-2`
       every time. Editing px-4 to px-2 on the cells looked right in the diff
       and measured as changing precisely nothing.

       Scoped to this page's own <style>, which loads after the shared sheet, so
       only the expenses register gets the tighter gutter. Every other table in
       the ERP keeps 0.75rem. Vertical padding is deliberately left alone — rows
       stay as tall and as readable as they were. */
    .states-table .states-table-content .table thead th,
    .states-table .states-table-content .table tbody td {
        padding-left: 6px;
        padding-right: 6px;
    }

    /* Category hierarchy badge */
    /* Capped so one long category name cannot set the width of the column for
       every row on the page. The badges keep white-space:nowrap — wrapping a
       pill onto two lines looks broken — so they clip instead, and the cell
       carries the full text as a tooltip. align-items:flex-start stops each
       badge stretching to the cap when its own text is short. */
    .cat-hier {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 3px;
        max-width: 130px;
    }
    .cat-hier > span {
        max-width: 100%;
        overflow: hidden;
    }
    /* The label needs its own element to get an ellipsis. The badge is an
       inline-flex box, so its bare text node becomes an anonymous flex item and
       text-overflow never applies to it — the name just got sliced off
       mid-word. min-width:0 lets the item shrink below its content width, which
       a flex item otherwise refuses to do. */
    .cat-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cat-badge-exp {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #eff6ff;
        color: #2563eb;
        font-size: 10px;
        font-weight: 600;
        padding: 2px 5px;
        border-radius: 20px;
        white-space: nowrap;
    }
    .subcat-badge-exp {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f5f3ff;
        color: #7c3aed;
        font-size: 9px;
        font-weight: 500;
        padding: 2px 5px;
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
        /* Explicit, not inherited from Tailwind's preflight: one of these is an
           <a> (Print Slip), and a link is content-box by default, which would
           make it 30x30 against the buttons' 28x28 — the same one-pixel-off row
           this rule set out to fix. Measured both ways. */
        box-sizing: border-box;
        width: 28px; height: 28px;
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
    /* Print Slip. Same greens it carried as a Bootstrap button — border
       green-500, icon green-600, green-500 on hover — so only its size
       changed, not its colour. text-decoration because this one is an <a>:
       it opens the slip in a new tab, and a link in a table cell picks up an
       underline the three <button>s never had. */
    .action-btn.print  { border-color: #22c55e; color: #16a34a; text-decoration: none; }
    .action-btn.print:hover { background: #22c55e; color: #fff; }

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
        padding: 2px 6px;
        border-radius: 20px;
        white-space: nowrap;
    }
    /* Amber, and on purpose the same amber as Pending: both mean something is
       still outstanding. This one is money the company has not paid back yet. */
    .pay-src-owed  { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .pay-src-cash  { background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; }
    .pay-src-bank  { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .pay-src-petty { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    /* Capped and clipped: this sub-line carries a bank or custodian name, and
       those run long — "BRAC BANK (Travels)" was setting the width of the Paid
       From column for the whole page. The full text stays in the cell tooltip. */
    .pay-src-sub   { font-size: 11px; color: #9ca3af; margin-top: 2px;
                     max-width: 118px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

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
    @media (max-width: 720px) {
        .expense-date-time-row { grid-template-columns: 1fr !important; }
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
                <form action="" method="get" id="expenseFilterForm">
                    <div class="filter-container exp-filter-bar">
                        <div class="filter-header">
                            <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="filter-content exp-filter-grid">
                            @php
                                $expenseToday = now()->toDateString();
                                $expenseYesterday = now()->subDay()->toDateString();
                                $expenseWeekFrom = now()->subDays(6)->toDateString();
                                $expenseFilterQuery = request()->except([
                                    'page', 'expense_date', 'date_from', 'date_to', 'user_id', 'company_id',
                                    'expense_category_id', 'expense_sub_category_id', 'bank_id',
                                    'payment_source', 'approval_status', 'status',
                                ]);
                                $expenseRangeUrl = fn ($from = null, $to = null) => route('role.expenses.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())] + array_filter(
                                    array_merge($expenseFilterQuery, ['date_from' => $from, 'date_to' => $to]),
                                    fn ($value) => $value !== null && $value !== ''
                                ));
                            @endphp
                            <div class="exp-filter-quick" aria-label="Quick expense date ranges">
                                <a href="{{ $expenseRangeUrl($expenseToday, $expenseToday) }}" class="{{ request('date_from') === $expenseToday && request('date_to') === $expenseToday ? 'is-on' : '' }}">Today</a>
                                <a href="{{ $expenseRangeUrl($expenseYesterday, $expenseYesterday) }}" class="{{ request('date_from') === $expenseYesterday && request('date_to') === $expenseYesterday ? 'is-on' : '' }}">Yesterday</a>
                                <a href="{{ $expenseRangeUrl($expenseWeekFrom, $expenseToday) }}" class="{{ request('date_from') === $expenseWeekFrom && request('date_to') === $expenseToday ? 'is-on' : '' }}">Last Week</a>
                                <a href="{{ $expenseRangeUrl() }}" class="{{ !request('date_from') && !request('date_to') ? 'is-on' : '' }}">All</a>
                            </div>
                            <div class="exp-filter-dates">
                                <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Expense date from">
                                <span>to</span>
                                <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Expense date to">
                            </div>
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
                                        @include('partials.bank-options', ['banks' => $banks, 'selected' => null])
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
                                    <label for="title">Title</label>
                                    <input type="text" name="title" value="{{ request('title') }}" id="title" class="form-control" placeholder="Search title, person, company, category, bank or status…">
                                </div>
                            </div>
                            <div class="filter-actions exp-filter-actions">
                                <button type="submit" class="apply-btn"><i class="fas fa-filter"></i> Apply</button>
                                <button type="button" class="reset-btn"><i class="fas fa-rotate-left"></i> Reset</button>
                                <button type="button" id="expensePrintButton" class="print-btn"><i class="fas fa-print"></i> Print</button>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Table --}}
                <div class="table-responsive overflow-x-auto" style="padding:15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200 expense-table">
                        <thead class="bg-gray-50">
                            <tr>
                                {{-- Column order, owner's call: Date, Title, Company/Dept,
                                     Amount, then the rest, with Status and Actions last.

                                     It reads the way the row is asked about — when, what,
                                     whose, how much — so the four values that identify an
                                     expense are the four leftmost columns, and Category and
                                     Paid From, which are detail rather than identity, sit
                                     after them. Status and Actions stay at the right where
                                     the eye ends.

                                     # and Actions stay plain: one is a row counter, the
                                     other holds buttons. Sorting is server-side, so it
                                     orders the whole list, not just this page. --}}
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'date', 'label' => 'Date', 'dirDefault' => 'desc'])</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'title', 'label' => 'Title'])</th>
                                {{-- Company and department are ONE column, company above and
                                     department beneath it. They are read as one fact — whose
                                     department this is — and side by side they were spending
                                     ~245px of a ten-column table to say it. Stacked, they cost
                                     one column's width and no information.

                                     Both lines keep their own sort link: merging the columns
                                     must not cost the ability to order by department.

                                     Company only where it can differ. A company-locked user
                                     sees their own name on every row, which is width spent
                                     saying nothing — so for them this stays the plain
                                     department column it always was. --}}
                                @can('view all expense')
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex flex-col items-start leading-tight">
                                        @include('partials.sortable-th', ['key' => 'company', 'label' => 'Company'])
                                        <span class="text-[10px] text-gray-400">@include('partials.sortable-th', ['key' => 'department', 'label' => 'Department'])</span>
                                    </div>
                                </th>
                                @else
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'department', 'label' => 'Department'])</th>
                                @endcan
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'amount', 'label' => 'Amount', 'dirDefault' => 'desc'])</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'category', 'label' => 'Category'])</th>
                                {{-- Paid From is not sortable, and cannot be: the cell under it is
                                     decided by three columns in precedence order — own pocket, then
                                     float, then bank, then the drawer — so there is no single column
                                     to order by. A header that looked clickable would order the list
                                     by something other than what it shows. --}}
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid From</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@include('partials.sortable-th', ['key' => 'status', 'label' => 'Status'])</th>

                                @canany(['view expense', 'edit expense', 'delete expense'])
                                <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width:1%;white-space:nowrap">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)
                                <tr class="hover:bg-teal-50 transition-colors duration-150">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-500" data-label="#">
                                        {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                                    </td>
                                    {{-- The standard date cell: the expense's own date, with the
                                         time it was entered beneath it. d/m/Y to match the bank
                                         transfer list, which is the format the owner reads dates
                                         in on these screens.

                                         The partial, not a local format() call: expense_date is a
                                         DATE column with no clock time of its own, so the time
                                         below is created_at — and the partial only shows it when
                                         the two fall on the same day. Enter today an expense dated
                                         last Tuesday and the row shows Tuesday with no time,
                                         rather than Tuesday over a clock reading from today. --}}
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600" data-label="Date">
                                        <span class="dtc-date">{{ \Carbon\Carbon::parse($value->expense_date)->format('d/m/Y') }}</span>
                                        @if($value->transaction_time)
                                            <span class="dtc-time">{{ \Carbon\Carbon::parse($value->transaction_time)->format('h:i A') }}</span>
                                        @endif
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
                                    @can('view all expense')
                                    {{-- Short name, full name on hover. "EPAL TRAVELS & CONSULTANCY"
                                         set the width of this column for every row on the page while
                                         "EPAL TRAVELS" says the same thing. Capped as well as
                                         shortened, because a short_name is free text and one long
                                         entry would put the column back where it was.

                                         The cap lives on an inner div, not the td: max-width on a
                                         table cell is ignored under table-layout:auto, which is what
                                         this table uses. --}}
                                    <td class="px-4 py-3 text-sm text-gray-700" data-label="Company / Dept">
                                        {{-- Inline style, not a Tailwind arbitrary value: this project
                                             ships a prebuilt CSS bundle, so max-w-[150px] would only
                                             exist if someone re-ran the Vite build. Measured at 90%
                                             browser zoom before and after — the class silently did
                                             nothing and the column stayed exactly as wide.

                                             150px for the pair, where the two columns were 120 + 125
                                             plus their own padding. Both lines clip rather than wrap:
                                             a wrapping department name would make one row taller than
                                             the rest, and the full text is on the title attribute of
                                             the line it belongs to. --}}
                                        <div style="max-width:150px">
                                            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                                 title="{{ $value->company?->name }}">
                                                {{ $value->company?->short_name ?: ($value->company?->name ?? '—') }}
                                            </div>
                                            {{-- The department is the qualifier, not the heading, and it
                                                 is the worse offender of the two for length: project
                                                 departments are named like "Bhairab, Kishoreganj
                                                 Residential". Smaller and greyer so the eye reads the
                                                 company first and the pair as one fact. --}}
                                            <div class="text-xs text-gray-500"
                                                 style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                                 title="{{ $value->expenseDepartment?->name }}">
                                                {{ $value->expenseDepartment?->name ?? '—' }}
                                            </div>
                                        </div>
                                    </td>
                                    @else
                                    {{-- No company column for a company-locked user, so the
                                         department stands alone exactly as it did before. --}}
                                    <td class="px-4 py-3 text-sm text-gray-700" data-label="Department">
                                        <div style="max-width:125px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                             title="{{ $value->expenseDepartment?->name }}">
                                            {{ $value->expenseDepartment?->name ?? '—' }}
                                        </div>
                                    </td>
                                    @endcan
                                    <td class="px-4 py-3" data-label="Amount">
                                        <span class="amount-cell">{{ number_format($value->amount, 2) }}</span>
                                    </td>
                                    <td class="px-4 py-3" data-label="Category" title="{{ trim(($value->expense_category?->name ?? "") . " / " . ($value->expense_sub_category?->name ?? ""), " /") }}">
                                        <div class="cat-hier">
                                            @if($value->expense_category?->name)
                                                <span class="cat-badge-exp"><i class="fas fa-tags" style="font-size:9px"></i><span class="cat-label">{{ $value->expense_category->name }}</span></span>
                                            @endif
                                            @if($value->expense_sub_category?->name)
                                                <span class="subcat-badge-exp"><i class="fas fa-sitemap" style="font-size:8px"></i><span class="cat-label">{{ $value->expense_sub_category->name }}</span></span>
                                            @endif
                                            @if(!$value->expense_category?->name)
                                                <span class="text-gray-400 text-xs">—</span>
                                            @endif
                                        </div>
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
                                    <td class="px-2 py-3 td-actions" data-label="">
                                        <div style="display:grid;grid-template-columns:repeat(2,24px);gap:4px;width:max-content;margin:0 auto;justify-content:center" data-row-actions>
                                            @can('approve expense')
                                                @if ($isPosted)
                                                    <button class="action-btn reverse js-expense-reverse"
                                                        data-id="{{ $value->id }}"
                                                        data-title="{{ $value->title }}"
                                                        data-amount="{{ number_format($value->amount, 2) }}"
                                                        data-url="{{ route('role.expenses.reverse', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $value->id]) }}"
                                                        title="Reverse — posts the opposite entry, keeps both on record" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                                        <i class="fas fa-rotate-left" style="font-size:11px;margin:0"></i>
                                                    </button>
                                                @elseif ($value->status)
                                                    <button class="action-btn approve js-expense-approve"
                                                        data-id="{{ $value->id }}"
                                                        data-title="{{ $value->title }}"
                                                        data-amount="{{ number_format($value->amount, 2) }}"
                                                        data-url="{{ route('role.expenses.approve', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $value->id]) }}"
                                                        title="Approve — posts this expense to the ledger" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                                        <i class="fas fa-check" style="font-size:11px;margin:0"></i>
                                                    </button>
                                                @endif
                                            @endcan
                                            @can('view expense')
                                            {{-- .action-btn, like the other three. It used to be the
                                                 odd one out — a Bootstrap `btn` with px-3 py-1, which
                                                 made it wider and taller than its neighbours and left
                                                 the row of four looking mis-set. Same 28px box now;
                                                 only the colour differs, which is the one thing that
                                                 should differ between these buttons. --}}
                                            <a href="{{ route('role.expenses.slip', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $value->id]) }}"
                                               target="_blank"
                                               class="action-btn print"
                                               title="Print Slip" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                                <i class="fas fa-print" style="font-size:11px;margin:0"></i>
                                            </a>
                                            @endcan
                                            @can('edit expense')
                                            {{-- The wrapper carries the tooltip: a disabled button does not
                                                 fire mouse events in every browser, so its own title can go
                                                 unshown — and the reason it is disabled is the whole point. --}}
                                            <span class="inline-flex" @if ($isPosted) title="Posted to the ledger — reverse this expense before editing" @endif style="display:inline-flex;width:24px;height:24px">
                                            <button class="action-btn edit edit-item-btn" @disabled($isPosted)
                                                data-item_id="{{ $value->id }}"
                                                data-bank_id="{{ $value->bank_id }}"
                                                data-bank_charge_amount="{{ $value->bank_charge_amount }}"
                                                data-bank_charge_account_id="{{ $value->bank_charge_account_id }}"
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
                                                data-reference_user_name="{{ $value->reference_user_name }}"
                                                data-expense_date="{{ $value->expense_date }}"
                                                data-transaction_time="{{ $value->transaction_time ? substr((string) $value->transaction_time, 0, 5) : '' }}"
                                                data-status="{{ $value->status }}"
                                                data-action="{{ route('role.get-expense-sub-category', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                                                title="{{ $isPosted ? 'Reverse this expense before editing' : 'Edit' }}" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                                <i class="fas fa-pen" style="font-size:11px;margin:0"></i>
                                            </button>
                                            </span>
                                            @endcan
                                            @can('delete expense')
                                            <span class="inline-flex" @if ($isPosted) title="Posted to the ledger — reverse this expense before deleting" @endif style="display:inline-flex;width:24px;height:24px">
                                            <button class="action-btn delete" @disabled($isPosted)
                                                onclick="confirmDelete('{{ $value->id }}', '{{ addslashes($value->title) }}')"
                                                title="{{ $isPosted ? 'Reverse this expense before deleting' : 'Delete' }}" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                                <i class="fas fa-trash" style="font-size:11px;margin:0"></i>
                                            </button>
                                            </span>
                                            @endcan
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-14">
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

            function syncExpenseModalPageLock() {
                const expenseModalOpen = $('#createModal, #editModal').filter(function () {
                    return !$(this).hasClass('hidden');
                }).length > 0;

                $('body').toggleClass('overflow-hidden', expenseModalOpen);
            }

            function escapeRegExp(value) {
                return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            $('#create_reference_user, #edit_reference_user').on('change', function () {
                const selectedName = String($(this).val() || '').trim();
                const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
                const $reference = $('#' + prefix + '_reference');
                const previousName = String($reference.data('referenceUser') || '').trim();
                let reference = String($reference.val() || '').trim();

                if (previousName) {
                    reference = reference
                        .replace(new RegExp('(?:\\s*-\\s*)?' + escapeRegExp(previousName) + '$', 'i'), '')
                        .trim();
                }

                if (selectedName && !reference.toLowerCase().includes(selectedName.toLowerCase())) {
                    reference = reference ? reference + ' - ' + selectedName : selectedName;
                }

                $reference.val(reference).data('referenceUser', selectedName);
            });

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            $('.create-new-btn').click(function() {
                $('#createModal').removeClass('hidden');
                syncExpenseModalPageLock();
                if ($('#create_items_body tr').length === 0) {
                    addItemRow('create');
                }
                // Required and used to be left blank, forcing every expense to
                // be typed by hand even though "now" is right almost always —
                // still fully editable for one entered after the fact.
                setTransactionTimeNow('create');
                setReferenceNow('create');
            });

            $('.edit-item-btn').click(function() {
                const item_id                 = $(this).data('item_id');
                const bank_id                 = $(this).data('bank_id');
                const bank_charge_amount      = $(this).data('bank_charge_amount');
                const bank_charge_account_id  = $(this).data('bank_charge_account_id');
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
                const reference_user_name     = $(this).data('reference_user_name');
                const expense_date            = $(this).data('expense_date');
                const transaction_time        = String($(this).data('transaction_time') || '');

                $('#editItemId').val(item_id);
                $('#edit_bank_id').val(bank_id).trigger('change');
                $('#edit_bank_charge_amount').val(bank_charge_amount || '0.00');
                $('#edit_bank_charge_account_id').val(bank_charge_account_id || '').trigger('change');
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
                $('#edit_reference_user').val(reference_user_name || '').trigger('change.select2').data('referenceUser', reference_user_name || '');
                document.getElementById('edit_additional_information').open = Boolean(description || attachment);
                // Native dispatch, not .trigger(): the cash-limit meter listens
                // with addEventListener, and jQuery's trigger does not reach a
                // native listener for a change event. Without this an old
                // expense opens showing TODAY's limit against a date months back.
                $('#edit_expense_date').val(expense_date);
                setExpenseTime('edit', transaction_time);
                document.getElementById('edit_expense_date')
                    ?.dispatchEvent(new Event('change', { bubbles: true }));
                // Load items for this expense
                $('#edit_items_body').empty();
                $('#edit_items_total').text('0.00');
                const itemsUrl = '{{ url(Str::slug(Auth::user()->getRoleNames()->first()) . "/expenses") }}/' + item_id + '/items';
                $.get(itemsUrl, function(response) {
                    if (response.success && response.items.length > 0) {
                        response.items.forEach(function(item) {
                            addItemRow('edit', item.description, item.amount, item.quantity || 1, item.unit_price, item.account_id || '', item.expense_category_id || '', item.expense_sub_category_id || '');
                        });
                    } else {
                        addItemRow('edit');
                    }
                    calcTotal('edit');
                }).fail(function() {
                    addItemRow('edit');
                });

                $('#editModal').removeClass('hidden');
                syncExpenseModalPageLock();

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
                    syncExpenseModalPageLock();
                }
            });
            $('.modal-close-edit, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) {
                    $('#editModal').addClass('hidden');
                    syncExpenseModalPageLock();
                }
            });
            $('.modal-close-delete, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) {
                    $('#deleteModal').addClass('hidden');
                }
            });

            $('.close-btn').click(function() { $(this).closest('.alert').addClass('hidden'); });

            function setExpenseSubmitLoading($button, loading, label) {
                if (loading) {
                    if (!$button.data('idle-html')) $button.data('idle-html', $button.html());
                    $button.prop('disabled', true)
                        .addClass('opacity-70 cursor-not-allowed')
                        .html('<i class="fas fa-spinner fa-spin"></i><span>' + label + '</span>');
                    return;
                }

                $button.prop('disabled', false)
                    .removeClass('opacity-70 cursor-not-allowed')
                    .html($button.data('idle-html'));
            }

            // Create
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (!validateCreateForm()) return;

                const sendCreate = function () {
                    const $submit = $('#createSubmit');
                    if ($submit.prop('disabled')) return;
                    setExpenseSubmitLoading($submit, true, 'Saving...');
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
                                syncExpenseModalPageLock();
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
                        },
                        complete: function () {
                            setExpenseSubmitLoading($submit, false);
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
                const $submit = $(this);
                if ($submit.prop('disabled')) return;
                setExpenseSubmitLoading($submit, true, 'Updating...');
                let formData = new FormData($('#editForm')[0]);
                $.ajax({
                    url: $submit.data('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Expense updated successfully!' });
                            $('#editModal').addClass('hidden');
                            syncExpenseModalPageLock();
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    },
                    complete: function () {
                        setExpenseSubmitLoading($submit, false);
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

        function composeExpenseTime(prefix) {
            const hourValue = document.getElementById(prefix + '_time_hour')?.value || '';
            const minuteValue = document.getElementById(prefix + '_time_minute')?.value ?? '';
            const ampm = document.getElementById(prefix + '_time_ampm')?.value || 'AM';
            const error = document.getElementById(prefix + '_transaction_time_error');
            if (!hourValue || minuteValue === '') {
                error?.classList.remove('hidden');
                return false;
            }
            let hour = Number(hourValue) % 12;
            if (ampm === 'PM') hour += 12;
            document.getElementById(prefix + '_transaction_time').value = String(hour).padStart(2, '0') + ':' + String(minuteValue).padStart(2, '0');
            error?.classList.add('hidden');
            return true;
        }

        function setExpenseTime(prefix, value) {
            const match = String(value || '').match(/^(\d{1,2}):(\d{2})/);
            document.getElementById(prefix + '_transaction_time').value = match ? `${match[1].padStart(2, '0')}:${match[2]}` : '';
            if (!match) {
                $('#' + prefix + '_time_hour').val('');
                $('#' + prefix + '_time_minute').val('');
                $('#' + prefix + '_time_ampm').val('AM');
                return;
            }
            const hour24 = Number(match[1]);
            $('#' + prefix + '_time_hour').val(String(hour24 % 12 || 12));
            $('#' + prefix + '_time_minute').val(String(Number(match[2])));
            $('#' + prefix + '_time_ampm').val(hour24 >= 12 ? 'PM' : 'AM');
        }

        function validateCreateForm() {
            let isValid = true;
            $('#create_items_msg').text('Add at least one line with an amount above zero');
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
            if (!composeExpenseTime('create')) isValid = false;
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
            const missingCategory = $('#create_items_body .line-cost-head').toArray().some(el => !el.value);
            if (missingCategory) {
                $('#create_items_msg').text('Choose an expense head for every line.').removeClass('hidden');
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
            $('#edit_items_msg').text('Add at least one line with an amount above zero');
            $('#editForm .error-message').addClass('hidden');
            $('#edit_company_msg, #edit_department_msg').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            // No title check — see the create form above.
            if (!$('#edit_expense_date').val().trim()) {
                $('#edit_expense_date').next('.error-message').removeClass('hidden');
                $('#edit_expense_date').addClass('border-red-500');
                isValid = false;
            }
            if (!composeExpenseTime('edit')) isValid = false;
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
            const missingCategory = $('#edit_items_body .line-cost-head').toArray().some(el => !el.value);
            if (missingCategory) {
                $('#edit_items_msg').text('Choose an expense head for every line.').removeClass('hidden');
                isValid = false;
            }
            const hasItem = $('#edit_items_body .item-amount').toArray().some(el => parseFloat(el.value) > 0);
            if (!hasItem) {
                $('#edit_items_msg').removeClass('hidden');
                isValid = false;
            }
            return isValid;
        }

        function addItemRow(prefix, desc = '', amount = '', quantity = 1, unitPrice = null, accountId = '', categoryId = '', subCategoryId = '') {
            const idx = Date.now() + Math.random();
            const safeDesc = String(desc || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            const resolvedUnitPrice = unitPrice === null || unitPrice === '' ? amount : unitPrice;
            const row = `<tr class="item-row border-b border-gray-100">
                <td class="item-serial px-2 py-1 text-center font-medium text-gray-500"></td>
                <td class="px-2 py-1">
                    <div class="flex gap-1">
                        <select class="line-cost-head" aria-label="Expense head and category" required></select>
                        @can('create expense category')
                            <button type="button" class="line-new-category shrink-0" title="Create category with expense head" style="padding:0 8px;border:1px solid #cbd5e1;border-radius:6px;color:#0f766e">C+</button>
                        @endcan
                        @can('create expense sub-category')
                            <button type="button" class="line-new-sub shrink-0" title="Create sub-category with expense head" style="padding:0 8px;border:1px solid #cbd5e1;border-radius:6px;color:#0f766e">S+</button>
                        @endcan
                    </div>
                    <input type="hidden" name="items[${idx}][expense_category_id]" class="line-category">
                    <input type="hidden" name="items[${idx}][expense_sub_category_id]" class="line-sub">
                    <input type="hidden" name="items[${idx}][account_id]" class="expense-line-head">
                </td>
                <td class="px-2 py-1">
                    <input type="text" name="items[${idx}][description]" value="${safeDesc}"
                        class="form-input w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Description">
                </td>
                <td class="px-2 py-1">
                    <input type="number" name="items[${idx}][quantity]" value="${quantity}"
                        class="item-quantity form-input w-full px-2 py-1 border border-gray-300 rounded text-sm text-center"
                        step="0.01" min="0.01" oninput="calcTotal('${prefix}')">
                </td>
                <td class="px-2 py-1">
                    <input type="number" name="items[${idx}][unit_price]" value="${resolvedUnitPrice}"
                        class="item-unit-price form-input w-full px-2 py-1 border border-gray-300 rounded text-sm text-right"
                        placeholder="0.00" step="0.01" min="0" oninput="calcTotal('${prefix}')">
                </td>
                <td class="px-2 py-1">
                    <input type="number" name="items[${idx}][amount]" value="${amount}"
                        class="item-amount form-input w-full px-2 py-1 border border-gray-200 bg-gray-50 rounded text-sm text-right"
                        placeholder="0.00" step="0.01" min="0" readonly>
                </td>
                <td class="px-2 py-1 text-center">
                    <button type="button" onclick="removeItemRow(this, '${prefix}')"
                        class="text-red-400 hover:text-red-600 transition"><i class="fas fa-times"></i></button>
                </td>
            </tr>`;
            $(`#${prefix}_items_body`).append(row);
            const rowElement = $(`#${prefix}_items_body tr:last`)[0];
            window.expenseLineClassification?.wire(rowElement, prefix, categoryId, subCategoryId, accountId);
            calcTotal(prefix);
        }

        function removeItemRow(btn, prefix) {
            const $row = $(btn).closest('tr');
            $row.find('.select2-hidden-accessible').select2('destroy');
            $row.remove();
            calcTotal(prefix);
        }

        function calcTotal(prefix) {
            let total = 0;
            $(`#${prefix}_items_body .item-row`).each(function(index) {
                const quantity = parseFloat($(this).find('.item-quantity').val()) || 0;
                const unitPrice = parseFloat($(this).find('.item-unit-price').val()) || 0;
                const lineTotal = Math.round((quantity * unitPrice + Number.EPSILON) * 100) / 100;
                $(this).find('.item-serial').text(index + 1);
                $(this).find('.item-amount').val(lineTotal.toFixed(2));
                total += lineTotal;
            });
            $(`#${prefix}_items_total`).text(total.toFixed(2));

            // The read-only Total at the top of the form is the same figure. It is
            // shown twice because that is where people look for it, not because
            // there are two amounts — only the cost lines are ever submitted.
            $(`#${prefix}_amount`).val(total.toFixed(2));
        }

        // Fills the hh/mm/AM-PM selects from expenses.partials.transaction-time
        // with the current time. Only for a fresh entry — editing an existing
        // expense shows its own saved time instead, never "now".
        function setTransactionTimeNow(prefix) {
            const now = new Date();
            const hour24 = now.getHours();
            $('#' + prefix + '_time_hour').val(String((hour24 % 12) || 12));
            $('#' + prefix + '_time_minute').val(String(now.getMinutes()));
            $('#' + prefix + '_time_ampm').val(hour24 < 12 ? 'AM' : 'PM');
        }

        // Reference No. was left for the person to type and most rows went in
        // blank. Pre-fills the same 'EXP-{YmdHis}' shape the bank ledger's Cash
        // In/Out now previews before saving — still a normal editable field for
        // a real bill or voucher number, and the Reference User select can
        // still append a name onto whatever is here.
        function setReferenceNow(prefix) {
            const field = $('#' + prefix + '_reference');
            if (field.val()) return;
            const now = new Date();
            const pad2 = n => String(n).padStart(2, '0');
            const ymdhis = now.getFullYear() + pad2(now.getMonth() + 1) + pad2(now.getDate())
                + pad2(now.getHours()) + pad2(now.getMinutes()) + pad2(now.getSeconds());
            field.val('EXP-' + ymdhis);
        }

        function resetCreateForm() {
            $('#createForm')[0].reset();
            document.getElementById('create_additional_information').open = false;
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
            document.getElementById('expenseFilterForm')?.addEventListener('submit', function() {
                const from = this.querySelector('[name="date_from"]');
                const to = this.querySelector('[name="date_to"]');
                if (from?.value && !to?.value) to.value = from.value;
                if (to?.value && !from?.value) from.value = to.value;
                if (from?.value && to?.value && from.value > to.value) {
                    [from.value, to.value] = [to.value, from.value];
                }
            });
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

            const printButton = document.getElementById('expensePrintButton');
            printButton?.addEventListener('click', function() {
                const today = new Date();
                const localDate = date => {
                    const offset = date.getTimezoneOffset();
                    return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 10);
                };
                const todayValue = localDate(today);
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                const yesterdayValue = localDate(yesterday);
                const currentParams = new URLSearchParams(window.location.search);
                currentParams.delete('page');
                const hasCurrentFilter = Array.from(currentParams.values()).some(value => value !== '');
                const printBase = @json(route('role.expenses.print', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]));

                const option = (value, title, hint, extra = '', disabled = false) => `
                    <label class="expense-print-option${disabled ? ' is-disabled' : ''}">
                        <input type="radio" name="expensePrintMode" value="${value}" ${value === 'today' ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
                        <span><b>${title}</b>${hint ? `<small>${hint}</small>` : ''}${extra}</span>
                    </label>`;
                const dateInput = (id, value) => `<input type="date" id="${id}" value="${value}" onfocus="this.closest('label').querySelector('input[type=radio]').checked=true">`;

                Swal.fire({
                    title: 'Print expenses',
                    html: `<style>
                        .expense-print-list{display:flex;flex-direction:column;gap:7px;text-align:left}
                        .expense-print-option{display:flex;align-items:flex-start;gap:10px;padding:10px 11px;border:1px solid #dbe3ee;border-radius:8px;cursor:pointer}
                        .expense-print-option:has(input:checked){border-color:#0d9488;background:#f0fdfa}
                        .expense-print-option.is-disabled{opacity:.5;cursor:not-allowed}
                        .expense-print-option>input{margin-top:3px;accent-color:#0d9488}
                        .expense-print-option>span{display:flex;flex:1;flex-direction:column;gap:3px;color:#334155;font-size:13px}
                        .expense-print-option small{color:#64748b;font-size:10px}
                        .expense-print-dates{display:flex;align-items:center;gap:6px;margin-top:4px;color:#94a3b8;font-size:10px}
                        .expense-print-dates input{min-width:0;height:32px;padding:0 7px;border:1px solid #cbd5e1;border-radius:6px;font-size:11px}
                    </style><div class="expense-print-list">
                        ${option('today', 'Today', todayValue)}
                        ${option('yesterday', 'Yesterday', yesterdayValue)}
                        ${option('day', 'A specific day', '', `<div class="expense-print-dates">${dateInput('expensePrintDay', todayValue)}</div>`)}
                        ${option('range', 'A date range', '', `<div class="expense-print-dates">${dateInput('expensePrintFrom', currentParams.get('date_from') || todayValue)}<span>to</span>${dateInput('expensePrintTo', currentParams.get('date_to') || todayValue)}</div>`)}
                        ${option('filtered', 'Current filtered results', hasCurrentFilter ? 'Use the search and date filters currently applied.' : 'Apply a search or date filter first.', '', !hasCurrentFilter)}
                    </div>`,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-print"></i> Print',
                    confirmButtonColor: '#0d9488',
                    width: 460,
                    focusConfirm: false,
                    preConfirm: () => {
                        const mode = document.querySelector('input[name="expensePrintMode"]:checked')?.value;
                        let from = '';
                        let to = '';
                        if (mode === 'today') from = to = todayValue;
                        if (mode === 'yesterday') from = to = yesterdayValue;
                        if (mode === 'day') from = to = document.getElementById('expensePrintDay')?.value || '';
                        if (mode === 'range') {
                            from = document.getElementById('expensePrintFrom')?.value || '';
                            to = document.getElementById('expensePrintTo')?.value || '';
                        }
                        if ((mode === 'day' || mode === 'range') && (!from || !to)) {
                            Swal.showValidationMessage('Please select the required date.');
                            return false;
                        }
                        if (from && to && from > to) [from, to] = [to, from];
                        return { mode, from, to };
                    },
                }).then(result => {
                    if (!result.isConfirmed) return;
                    const params = result.value.mode === 'filtered'
                        ? new URLSearchParams(currentParams)
                        : new URLSearchParams();
                    if (result.value.from) params.set('date_from', result.value.from);
                    if (result.value.to) params.set('date_to', result.value.to);
                    window.open(`${printBase}?${params.toString()}`, '_blank', 'noopener');
                });
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
                    html: opts.html + (opts.requireDate
                        ? '<div style="text-align:left;margin-top:14px"><label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:5px">Reversal Date *</label><input type="date" id="expenseReversalDate" value="{{ now()->toDateString() }}" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px"></div>'
                        : ''),
                    showCancelButton: true,
                    confirmButtonText: opts.confirm,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: opts.colour,
                    reverseButtons: true,
                    preConfirm: function () {
                        if (!opts.requireDate) return {};
                        const date = document.getElementById('expenseReversalDate').value;
                        if (!date) {
                            Swal.showValidationMessage('Reversal date is required.');
                            return false;
                        }
                        return { reversal_date: date };
                    },
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $btn.prop('disabled', true);

                    $.ajax({
                        url: $btn.data('url'),
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: result.value || {},
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
                    requireDate: true,
                });
            });
        });
    </script>

    {{-- The classification picker and the payment behaviour, wired for both the
         create and the edit modal from one implementation each. --}}
    @include('expenses.partials.classification-js')
    @include('expenses.partials.line-classification')
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
                'dm_title', 'dm_amount', 'dm_currency', 'dm_cycle',
                'dm_type_id', 'dm_category_id', 'dm_next_expiry',
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

                // The expense date is deliberately NOT filled from the due date.
                // An expense is dated by the day the money moved, and a renewal
                // is usually paid a little before or after it falls due. Setting
                // it here put a Hostinger payment into October's P&L while the
                // money left the bank in August, and wrote a journal entry dated
                // in the future. The form already defaults to today; the period
                // being settled travels separately as dm_due_date, which is what
                // the payment row keys on.
                $('#create_reference').val('DM-' + String(dm.dm_source_type).toUpperCase() + '-' + dm.dm_id + '-' + dm.dm_due_date);
                $('#create_description').val('Renewal recorded from the Subscriptions desk — ' + (dm.dm_title || ''));

                // The term this renewal buys, shown so it can be CORRECTED before
                // it is written into DM. For a document this is the only source of
                // that date — DM states no cycle and its own dates cannot imply one.
                // Editable on purpose: a wrong date pushed into DM raises no error,
                // it just stops the next reminder arriving.
                if (dm.dm_next_expiry) {
                    var $ref = $('#create_reference').closest('div');
                    $ref.after(
                        $('<div class="mt-3 dm-renewal-extra">').append(
                            $('<label class="block text-sm font-semibold text-gray-700 mb-1.5">')
                                .text('Renewed until'),
                            $('<input type="date" name="dm_next_expiry" id="dm_next_expiry">')
                                .addClass('form-input w-full px-3 py-2 border border-gray-300 rounded-lg text-sm')
                                .val(dm.dm_next_expiry),
                            $('<p class="text-[11px] text-gray-500 mt-1">')
                                .text('The new term is written back to DM when this expense is approved. Correct it if the vendor gave a different date.')
                        )
                    );
                }

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
                    // dm_next_expiry is skipped here because it is rendered as a
                    // visible, editable field above — adding a hidden twin would
                    // submit both and the stale one could win.
                    if (value === null || value === ''
                        || key === 'dm_amount' || key === 'dm_currency' || key === 'dm_next_expiry') return;
                    $('<input>').attr({ type: 'hidden', name: key })
                        .addClass('dm-renewal-extra').val(value).appendTo(form);
                });

                // The create modal is ONE form, reused, and the page never clears
                // it — closing it leaves every value in place. So everything set
                // above would still be sitting there the next time somebody
                // pressed Add Expense for an ordinary purchase, and that expense
                // would be linked to this renewal and push a term into DM when
                // approved. A fresh Add Expense therefore undoes all of it.
                //
                // Clears exactly what this block set and nothing else. A blanket
                // resetCreateForm() would also reset the payment-source fields,
                // whose visible tab state is owned by payment-js and would be left
                // disagreeing with the values underneath it.
                //
                // Bound AFTER the trigger above, so it does not fire for the open
                // happening right now.
                $('.create-new-btn').on('click', function () {
                    $(form).find('.dm-renewal-extra').remove();
                    $('#create_reference, #create_description').val('');
                    $('#create_reference').removeData('referenceUser');
                    $('#create_reference_user').val('').trigger('change.select2');

                    // The cost lines carry the renewal's name and figure, so they
                    // go back to one empty row — the state Add Expense expects.
                    $('#create_items_body').empty();
                    addItemRow('create');
                    calcTotal('create');
                });
            });
        </script>
    @endif
@endsection
