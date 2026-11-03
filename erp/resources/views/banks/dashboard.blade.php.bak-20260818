@extends('layout.app')

@section('meta-information')
    <title>Manage Bank Accounts</title>
@endsection

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 40px !important; border: 1.5px solid #e2e8f0 !important; border-radius: 8px !important;
        display: flex; align-items: center; padding: 0 11px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px !important; padding: 0 !important; font-size: 0.85rem; color: #1e293b; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px !important; }
    .select2-container--default .select2-selection--single:focus,
    .select2-container--default.select2-container--focus .select2-selection--single { border-color: #6366f1 !important; }
    .select2-dropdown { border-color: #e2e8f0 !important; border-radius: 8px !important; overflow: hidden; }
    .select2-search--dropdown .select2-search__field { border-radius: 6px !important; border: 1px solid #e2e8f0 !important; }
</style>
@endsection

@section('main-content')
<style>
    .bank-dash-banner {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 55%, #7c3aed 100%);
        border-radius: 16px;
        /* Was 1.5rem 1.75rem. Trimmed with the type and tiles below to take the
           banner's height down roughly a quarter without crowding it. Trimmed
           again when the left side became its own panel, so that panel's
           padding does not add the height back. */
        padding: 0.8rem 1.1rem;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
    }
    /* Two soft blooms — one off the top-right, one under the left corner — so
       the panel reads as lit rather than as a flat gradient. Decoration only:
       no layout box, no pointer target, no height. */
    .bank-dash-banner::before {
        content: ''; position: absolute; inset: 0; pointer-events: none;
        background:
            radial-gradient(120% 150% at 88% -25%, rgba(255,255,255,0.20), transparent 58%),
            radial-gradient(90% 130% at -5% 125%, rgba(255,255,255,0.12), transparent 55%);
    }
    .bank-dash-banner > * { position: relative; z-index: 1; }
    .bank-dash-banner h2 { font-size: 1.1rem; font-weight: 700; margin: 0; letter-spacing: -0.01em; line-height: 1.25; }
    .bank-dash-banner p { font-size: 0.82rem; opacity: 0.85; margin: 2px 0 0; }

    /* Banner is two blocks: title + KPI tiles on the left, the account names on
       the right. The head is sized by its content (flex 0 1 auto) rather than
       taking the full row, so the banner's space-between pushes the name
       columns over to the right half and clear of the tiles. */
    /* The left side is one translucent panel holding the icon, the title and
       both figures, so it reads as a card and the name columns read as the
       banner's body. The figures inside it are plain text divided by a
       hairline — boxes inside a box looked cluttered. */
    .bank-dash-head {
        display: flex; align-items: flex-start; gap: 11px;
        flex: 0 1 auto; min-width: 0;
        background: rgba(255,255,255,0.10);
        border: 1px solid rgba(255,255,255,0.16);
        border-radius: 14px;
        padding: 9px 13px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.12);
    }
    .bank-dash-head .icon-box { flex-shrink: 0; }
    .bank-dash-headtext { min-width: 0; }
    .bank-dash-stats { display: flex; align-items: stretch; gap: 12px; margin-top: 8px; }

    /* The account list was one dot-separated string that wrapped into a ragged
       block. It is now a column list: names run top-to-bottom, then into the
       next column, separated by a hairline. The flex-basis is what holds the
       block on the right — it shrinks on smaller screens and drops to its own
       full-width row once it can no longer fit beside the title. */
    .bank-dash-names {
        /* Four columns rather than three: 12 accounts then need 3 rows instead
           of 4, and that removed row is most of the height saving here. */
        /* flex-grow 1 so the columns spread into whatever the panel leaves,
           rather than sitting at a fixed width with dead space in between. */
        flex: 1 1 480px; min-width: 0;
        list-style: none; margin: 0; padding: 0;
        columns: 4; column-gap: 20px;
        column-rule: 1px solid rgba(255,255,255,0.20);
        font-size: 0.78rem; opacity: 0.92;
    }
    /* break-inside guards against a name being split across a column boundary. */
    .bank-dash-names li {
        break-inside: avoid; line-height: 1.6;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        padding-left: 11px; position: relative;
    }
    /* A dot per account instead of the old ' · ' that used to run the names
       together. Absolutely positioned so it costs no line height. */
    .bank-dash-names li::before {
        content: ''; position: absolute; left: 0; top: 50%;
        width: 4px; height: 4px; margin-top: -2px; border-radius: 50%;
        background: rgba(255,255,255,0.55);
    }
    @media (max-width: 1280px) { .bank-dash-names { flex-basis: 520px; columns: 3; } }
    @media (max-width: 980px)  { .bank-dash-names { flex-basis: 340px; columns: 2; } }
    @media (max-width: 640px)  { .bank-dash-names { flex-basis: 100%; columns: 1; column-rule: none; margin-top: 4px; } }
    .bank-dash-banner .icon-box {
        width: 38px; height: 38px; border-radius: 11px;
        background: rgba(255,255,255,0.16);
        border: 1px solid rgba(255,255,255,0.22);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
    }
    /* No box of their own — the panel is the box. Left-aligned under the title
       so both figures and the heading share one edge, split by a hairline. */
    .bank-dash-stat {
        background: none;
        border: 0;
        border-radius: 0;
        padding: 0;
        text-align: left;
        min-width: 0;
    }
    .bank-dash-stat + .bank-dash-stat {
        border-left: 1px solid rgba(255,255,255,0.22);
        padding-left: 12px;
    }
    /* tabular-nums keeps the balance from reflowing as digits change when you
       click between companies. */
    /* nowrap on both: selecting a company swaps the label for '<Company>
       Balance', and a two-line label would put the banner's height back. */
    .bank-dash-stat .val { font-size: 1.05rem; font-weight: 700; line-height: 1.25; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .bank-dash-stat .lbl { font-size: 0.58rem; opacity: 0.78; text-transform: uppercase; letter-spacing: 0.07em; line-height: 1.35; white-space: nowrap; }

    .company-pick-card:hover { box-shadow: 0 4px 14px rgba(79,70,229,0.15); border-color: #c7d2fe; }
    .company-pick-card.is-active { border-color: #6366f1; box-shadow: 0 4px 14px rgba(79,70,229,0.18); }

    .badge-status { display:inline-block; padding: 2px 9px; border-radius: 99px; font-size: 10px; font-weight: 700; }
    .badge-active   { background: #dcfce7; color: #166534; }
    .badge-inactive { background: #fee2e2; color: #991b1b; }

    .bank-acc-card {
        background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid #6366f1;
        border-radius: 12px; padding: 1.1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        cursor: pointer; transition: box-shadow .15s, border-color .15s;
    }
    .bank-acc-card:hover { box-shadow: 0 4px 14px rgba(79,70,229,0.15); }
    .bank-acc-card.type-cash { border-left-color: #f97316; }
    .bank-acc-card.type-mobile_banking,
    .bank-acc-card.type-digital_wallet { border-left-color: #ec4899; }
    .bank-acc-card .top-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
    .bank-acc-card .name { font-weight: 700; color: #1e293b; font-size: 0.95rem; }
    .bank-acc-card .sub  { font-size: 0.72rem; color: #94a3b8; }
    .bank-acc-card .balance { font-size: 1.35rem; font-weight: 700; color: #1e293b; margin: 6px 0 2px; }
    .bank-acc-card .last-txn { font-size: 0.7rem; color: #94a3b8; }
    .bank-acc-icon {
        width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center;
        font-size: 0.95rem; color: #fff; background: #6366f1; flex-shrink: 0;
    }
    .type-cash .bank-acc-icon { background: #f97316; }
    .type-mobile_banking .bank-acc-icon,
    .type-digital_wallet .bank-acc-icon { background: #ec4899; }

    .bank-btn {
        display: flex; align-items: center; justify-content: center; gap: 4px;
        padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600;
        border: none; cursor: pointer; text-decoration: none;
    }
    .bank-btn-statement { background: #eef2ff; color: #4338ca; }
    .bank-btn-statement:hover { background: #c7d2fe; }
    .bank-btn-deposit { background: #dcfce7; color: #15803d; }
    .bank-btn-deposit:hover { background: #bbf7d0; }
    .bank-btn-withdraw { background: #fee2e2; color: #b91c1c; }
    .bank-btn-withdraw:hover { background: #fecaca; }
    .bank-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    .txn-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.83rem; }
    .txn-table thead th {
        background: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.04em; font-size: 0.7rem; padding: 10px 12px;
        white-space: nowrap;
        /* Outlined header band. border-collapse is separate with 0 spacing, so
           the side borders go on the end cells only and nothing doubles up. */
        border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;
    }
    /* No top radius: the rounded corners belong to .txn-head above, which this
       band continues. Its top border is the divider between the two. */
    .txn-table thead th:first-child { border-left: 1px solid #e2e8f0; }
    .txn-table thead th:last-child  { border-right: 1px solid #e2e8f0; }

    /* Sortable columns. The arrow is hidden until hover on an inactive column
       and stays lit on the active one, so the header shows which column the
       table is ordered by without six arrows competing for attention. */
    /* Scoped to the two bank tables rather than a bare th[data-sort], so the
       rule can only ever reach markup on this page. */
    .txn-table thead th[data-sort],
    #ledgerArea thead th[data-sort] { cursor: pointer; user-select: none; transition: background .15s, color .15s; }
    .txn-table thead th[data-sort]:hover,
    #ledgerArea thead th[data-sort]:hover { background: #f1f5f9; color: #475569; }
    .txn-table thead th .th-sort,
    #ledgerArea thead th .th-sort { margin-left: 5px; font-size: 0.62rem; opacity: 0; transition: opacity .15s; }
    .txn-table thead th[data-sort]:hover .th-sort,
    #ledgerArea thead th[data-sort]:hover .th-sort { opacity: 0.45; }
    .txn-table thead th.is-active-sort,
    #ledgerArea thead th.is-active-sort { color: #4f46e5; }
    .txn-table thead th.is-active-sort .th-sort,
    #ledgerArea thead th.is-active-sort .th-sort { opacity: 1; color: #4f46e5; }

    /* Title, search and the figures sit in a bounded panel that joins straight
       onto the column headings, so the whole header reads as one block. Same
       #e2e8f0 the cards and the table already use — an outline, not a colour,
       and no fill, so nothing about the card's look changes underneath it. */
    .txn-head {
        border: 1px solid #e2e8f0;
        border-bottom: 0;
        border-radius: 10px 10px 0 0;
        padding: 12px 14px;
    }
    .txn-table tbody td { padding: 11px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; }
    .txn-table tbody tr:last-child td { border-bottom: none; }
    /* Green = money arriving, red = money leaving.
       A bank account is an asset, so a DEBIT increases it (a deposit, a
       reversal of a withdrawal) and a CREDIT decreases it (a withdrawal, a
       payment out). The colours used to run the other way, which made a
       withdrawal read as a gain and a deposit as a loss — and contradicted the
       In/Out badge sitting in the same row, which has always been green for
       `debit > 0`. Amount and badge now say the same thing. */
    .txn-amount-debit  { color: #16a34a; font-weight: 600; }
    .txn-amount-credit { color: #dc2626; font-weight: 600; }

    /* Tiny pencil beside a description. Hidden until the row is hovered so it
       never competes with the text, and only rendered on rows the server will
       actually accept an edit for. */
    /* Sits before the text. opacity rather than display keeps its box reserved,
       so the description does not jump sideways as the row is hovered. */
    .desc-edit {
        border: 0; background: none; padding: 0; margin-right: 7px;
        color: #cbd5e1; font-size: 0.62rem; line-height: 1; cursor: pointer;
        opacity: 0; transition: opacity .15s, color .15s;
        vertical-align: middle;
    }
    tr:hover .desc-edit { opacity: 1; }
    .desc-edit:hover { color: #4f46e5; }
    .desc-edit:focus-visible { opacity: 1; outline: 2px solid #6366f1; outline-offset: 2px; }

    /* Search box + the two day-flow figures in the recent-activity header. */
    .txn-search { position: relative; flex: 1 1 240px; min-width: 170px; max-width: 520px; }
    .txn-search i {
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: 0.8rem; pointer-events: none;
    }
    .txn-search input {
        width: 100%; height: 38px; padding: 0 12px 0 32px;
        border: 1px solid #e2e8f0; border-radius: 9px; background: #fff;
        font-size: 0.82rem; color: #1e293b; outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .txn-search input::placeholder { color: #94a3b8; }
    .txn-search input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.14); }

    .txn-kpi { border: 1px solid #e2e8f0; border-radius: 10px; padding: 6px 14px; text-align: right; min-width: 104px; }
    .txn-kpi .v { font-size: 1rem; font-weight: 800; line-height: 1.3; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .txn-kpi .k { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #94a3b8; white-space: nowrap; }
    .txn-kpi-in  { background: #f0fdf4; border-color: #dcfce7; }
    .txn-kpi-in  .v { color: #16a34a; }
    .txn-kpi-out { background: #fef2f2; border-color: #fee2e2; }
    .txn-kpi-out .v { color: #dc2626; }

    .modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.45);
        backdrop-filter: blur(3px); z-index: 1000; align-items: center; justify-content: center;
    }
    .modal-overlay.active { display: flex; }
    .modal-box {
        background: #fff; border-radius: 16px; box-shadow: 0 24px 48px rgba(0,0,0,0.18);
        width: 100%; max-width: 460px; overflow: hidden;
    }
    .modal-header { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); padding: 1.1rem 1.4rem; color: #fff; }
    .modal-header h3 { font-size: 1rem; font-weight: 700; margin: 0; }
    .modal-header p  { font-size: 0.78rem; opacity: 0.8; margin: 2px 0 0; }
    .modal-body  { padding: 1.4rem; }
    .modal-footer { padding: 1rem 1.4rem; background: #f8fafc; display: flex; justify-content: flex-end; gap: 0.5rem; border-top: 1px solid #e2e8f0; }
    .form-group { margin-bottom: 0.85rem; }
    .form-group label { display: block; font-size: 0.78rem; font-weight: 600; color: #475569; margin-bottom: 4px; }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 8px 11px;
        font-size: 0.85rem; color: #1e293b; background: #fff; box-sizing: border-box;
    }
    .form-group .required { color: #ef4444; }
    .btn-cancel { padding: 8px 20px; border-radius: 8px; border: none; background: #e2e8f0; color: #475569; font-weight: 600; cursor: pointer; }
    .btn-submit { padding: 8px 22px; border-radius: 8px; border: none; background: #4f46e5; color: #fff; font-weight: 700; cursor: pointer; }
    .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
    .alert-success { border-left: 4px solid #10b981; background: #f0fdf4; color: #166534; padding: 10px 14px; border-radius: 8px; font-weight: 500; font-size: 0.88rem; margin-bottom: 1rem; }
    .alert-error   { border-left: 4px solid #ef4444; background: #fef2f2; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1rem; }

    /* Search & filter bar */
    .bank-filter-bar { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; }
    .bank-filter-row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .bank-search-wrap { position: relative; flex: 1; min-width: 260px; }
    .bank-search-wrap input {
        width: 100%; padding: 10px 14px 10px 36px; border: 1px solid #e2e8f0; border-radius: 10px;
        font-size: 0.85rem; outline: none; background: #f8fafc;
    }
    .bank-search-wrap input:focus { border-color: #6366f1; background: #fff; }
    .bank-search-wrap i.fa-search { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem; }
    .bank-filter-bar select, .bank-filter-bar input[type=date] {
        padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; background: #f8fafc; outline: none;
    }
    .bank-filter-bar select:focus, .bank-filter-bar input[type=date]:focus { border-color: #6366f1; background: #fff; }
    .bank-load-btn {
        padding: 10px 20px; border-radius: 10px; border: none; background: #4f46e5; color: #fff;
        font-weight: 700; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 6px;
    }
    .bank-load-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .bank-dropdown {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #fff;
        border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        max-height: 280px; overflow-y: auto; z-index: 40;
    }
    .bank-dropdown-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
    .bank-dropdown-item:last-child { border-bottom: none; }
    .bank-dropdown-item:hover { background: #eef2ff; }
    .bank-dropdown-avatar {
        width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg,#6366f1,#4f46e5);
        color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
    }
    .selected-bank-chip {
        display: inline-flex; align-items: center; gap: 8px; background: #eef2ff; color: #4338ca;
        padding: 6px 12px; border-radius: 99px; font-size: 0.8rem; font-weight: 600; margin-top: 10px;
    }
    .selected-bank-chip button { background: none; border: none; color: #4338ca; cursor: pointer; font-size: 0.85rem; }

    /* Ledger panel */
    .ledger-header { border-radius: 14px; background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color: #fff; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
    .ledger-info-bar { display: flex; flex-wrap: wrap; gap: 1.5rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.1rem 1.4rem; margin-bottom: 1.25rem; }
    .ledger-info-bar .lbl { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; }
    .ledger-info-bar .val { font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-top: 2px; }
    .ledger-summary { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem; }
    .ledger-summary .card { flex: 1; min-width: 160px; border-radius: 12px; padding: 1rem 1.25rem; border-left: 4px solid; }
    .ledger-summary .card .lbl { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 6px; }
    .ledger-summary .card .val { font-family: monospace; font-size: 1.2rem; font-weight: 800; }
    .placeholder-box { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
</style>

@php
    $roleSlug = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
    $typeIcons = [
        'bank' => 'fa-university',
        'mobile_banking' => 'fa-mobile-alt',
        'digital_wallet' => 'fa-wallet',
        'cash' => 'fa-money-bill-wave',
    ];
    $typeLabels = [
        'bank' => ['deposit' => 'Deposit', 'withdraw' => 'Withdraw'],
        'mobile_banking' => ['deposit' => 'Cash In', 'withdraw' => 'Cashout'],
        'digital_wallet' => ['deposit' => 'Cash In', 'withdraw' => 'Cashout'],
        'cash' => ['deposit' => 'Cash In', 'withdraw' => 'Cash Out'],
    ];
@endphp

@include('layout.bank-tabs')

@if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert-error">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert-error">{{ $errors->first() }}</div>
@endif

<div class="bank-dash-banner">
    @php $bankDashNames = $banks->pluck('name')->filter()->values(); @endphp
    {{-- Left: title with the two KPI tiles directly beneath it. --}}
    <div class="bank-dash-head">
        <div class="icon-box"><i class="fas fa-landmark"></i></div>
        <div class="bank-dash-headtext">
            <h2>Manage Bank Accounts</h2>
            <div class="bank-dash-stats">
                <div class="bank-dash-stat">
                    <div class="val" id="bannerBalanceStat">৳{{ number_format($totalBalance, 2) }}</div>
                    <div class="lbl" id="bannerBalanceLabel">Total Balance</div>
                </div>
                <div class="bank-dash-stat">
                    <div class="val" id="bannerAccountsStat">{{ $banks->count() }}</div>
                    <div class="lbl">Accounts</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: the account names, one per line, flowing down each column then
         into the next. Its own child of the banner rather than a child of the
         title block, so space-between holds it clear of the KPI tiles.
         title= carries the full name for the rare one long enough to clip. --}}
    @if($bankDashNames->isNotEmpty())
        <ul class="bank-dash-names">
            @foreach($bankDashNames as $bankDashName)
                <li title="{{ $bankDashName }}">{{ $bankDashName }}</li>
            @endforeach
        </ul>
    @else
        <p>No accounts yet</p>
    @endif
</div>

{{-- Company summary strip — click a card to see that company's own recent
     activity and have the banner stats reflect just that company. --}}
@if($bankGroups->isNotEmpty())
<div id="companyPicker" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">
    @foreach($bankGroups as $companyName => $companyBanks)
        <div class="company-pick-card" data-company-name="{{ $companyName }}"
            onclick="selectCompany({{ json_encode($companyName) }})"
            style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.04);cursor:pointer;transition:box-shadow .15s,border-color .15s;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div style="width:36px;height:36px;border-radius:9px;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:0.95rem;flex-shrink:0;">
                    <i class="fas fa-building"></i>
                </div>
                <div style="font-weight:700;color:#1e293b;font-size:0.95rem;">{{ $companyName }}</div>
            </div>
            <div style="font-size:1.2rem;font-weight:700;color:#1e293b;margin-bottom:2px;">৳{{ number_format($companyBanks->sum('journal_balance'), 2) }}</div>
            <div style="font-size:0.72rem;color:#94a3b8;">
                {{ $companyBanks->count() }} account{{ $companyBanks->count() === 1 ? '' : 's' }} <i class="fas fa-chevron-right" style="margin-left:4px;"></i> View
            </div>
        </div>
    @endforeach
</div>
@endif

{{-- Bank cards for the selected company — replaces the company picker above
     once a company is chosen; "All Companies" returns to the picker. --}}
<div id="companyBankCards" style="display:none;margin-bottom:1.5rem;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:0.85rem;">
        <button type="button" onclick="showCompanyPicker()"
            style="border:1px solid #e2e8f0;background:#fff;color:#475569;border-radius:8px;padding:6px 12px;font-size:0.78rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-arrow-left"></i> All Companies
        </button>
        <h3 style="font-size:0.95rem;font-weight:700;color:#1e293b;margin:0;" id="bankCardsCompanyName">—</h3>
    </div>
    <div id="bankCardsGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:1rem;"></div>
</div>

{{-- Search & filter bar — hidden; navigation now happens entirely through the
     company cards and clicking a transaction row. Left in the DOM (not removed)
     because loadLedger()/resolveDates() still read these elements when a row
     click triggers a full ledger load. --}}
<div class="bank-filter-bar" style="display:none;">
    <div class="bank-filter-row">
        <div class="bank-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="bankSearch" placeholder="Search bank, account number, wallet...">
            <div id="bankDropdown" class="bank-dropdown" style="display:none;"></div>
        </div>
        <select id="companyFilter" onchange="onCompanyFilterChange()">
            <option value="">All Companies</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
        <select id="datePreset">
            <option value="this_year">This Year</option>
            <option value="this_month">This Month</option>
            <option value="last_3">Last 3 Months</option>
            <option value="last_6">Last 6 Months</option>
            <option value="all_time">All Time</option>
            <option value="custom">Custom Range</option>
        </select>
        <input type="date" id="dateFrom" style="display:none;">
        <input type="date" id="dateTo" style="display:none;">
        <button type="button" class="bank-load-btn" id="loadBtn" disabled onclick="loadLedger()">
            <i class="fas fa-sync-alt" id="loadIcon"></i> Load
        </button>
    </div>
    <div id="selectedBankChip" class="selected-bank-chip" style="display:none;">
        <i class="fas fa-university"></i> <span id="chipBankName"></span>
        <button type="button" onclick="clearSelectedBank()"><i class="fas fa-times"></i></button>
    </div>
</div>

{{-- Company-scoped recent activity — shown until a specific bank's full
     ledger is loaded. Content is rendered client-side from COMPANY_DATA so
     switching companies (via the cards above) needs no round trip. --}}
<div id="placeholderArea" class="portal-card" style="background:#fff;border-radius:14px;box-shadow:0 2px 16px rgba(0,0,0,0.06);padding:1.5rem 1.75rem;">
    {{-- mb-4 removed deliberately: this panel must sit flush on the column
         headings below for the two to form one bounded block. --}}
    <div class="flex justify-between items-center txn-head" style="flex-wrap:wrap;gap:10px;">
        <div>
            <h2 style="font-size:1.05rem;font-weight:700;color:#1e293b;margin:0;" id="recentTxnTitle">Recent Bank Transactions</h2>
            <p style="font-size:0.75rem;color:#94a3b8;margin:2px 0 0;" id="recentTxnSubtitle">Last 14 days. Click any row to open that account's full ledger.</p>
        </div>
        {{-- Filters the rows already on screen. Purely client-side over the
             transactions this company handed the table, so it issues no request
             and does not change how the table is loaded. --}}
        <div class="txn-search">
            <i class="fas fa-search"></i>
            <input type="search" id="txnSearch" placeholder="Search bank, description or amount…"
                   autocomplete="off" aria-label="Search transactions">
        </div>

        {{-- Today's movement, read off the same 14-day set the table shows.
             Deliberately NOT affected by the search box: they report the day,
             not the current filter. --}}
        <div class="txn-kpi txn-kpi-in">
            <div class="v" id="todayInVal">—</div>
            <div class="k">Today In</div>
        </div>
        <div class="txn-kpi txn-kpi-out">
            <div class="v" id="todayOutVal">—</div>
            <div class="k">Today Out</div>
        </div>

        <div id="netChangeStat" style="border-radius:10px;padding:8px 16px;text-align:right;">
            <div style="font-size:1.05rem;font-weight:800;" id="netChangeVal">—</div>
            <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#94a3b8;">Net Change (14 Days)</div>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="txn-table">
            <thead>
                {{-- data-sort marks a column sortable; the arrow is filled in by
                     paintTxnSortIndicators(). Date descending is the default. --}}
                <tr>
                    <th data-sort="date">Date <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                    <th data-sort="bank">Bank <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                    <th data-sort="description">Description <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                    <th style="text-align:right;" data-sort="debit">Debit <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                    <th style="text-align:right;" data-sort="credit">Credit <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                    <th style="text-align:right;" data-sort="balance">Company Balance <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                </tr>
            </thead>
            <tbody id="recentTxnBody"></tbody>
        </table>
    </div>
</div>

{{-- Inline ledger panel — populated via AJAX once a bank is loaded --}}
<div id="ledgerArea" style="display:none;">
    <div class="ledger-header">
        <div>
            <h2 style="font-size:1.1rem;font-weight:700;margin:0;" id="ledgerBankName">—</h2>
            <p style="font-size:0.8rem;opacity:0.85;margin:3px 0 0;" id="ledgerPeriod">—</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="bank-btn bank-btn-deposit" id="depositBtn" onclick="openTxnModal('deposit')">
                <i class="fas fa-plus"></i> <span id="depositLabel">Deposit</span>
            </button>
            <button type="button" class="bank-btn bank-btn-withdraw" id="withdrawBtn" onclick="openTxnModal('withdraw')">
                <i class="fas fa-minus"></i> <span id="withdrawLabel">Withdraw</span>
            </button>
            <a href="#" id="printStatementLink" target="_blank" class="bank-btn bank-btn-statement">
                <i class="fas fa-print"></i> Print
            </a>
        </div>
    </div>

    <div class="ledger-info-bar">
        <div><div class="lbl">Account Name</div><div class="val" id="infoAccountName">—</div></div>
        <div><div class="lbl">Account Number</div><div class="val" id="infoAccountNumber">—</div></div>
        <div><div class="lbl">Type</div><div class="val" id="infoType">—</div></div>
        <div><div class="lbl">Company</div><div class="val" id="infoCompany">—</div></div>
        <div><div class="lbl">Transactions</div><div class="val" id="infoTxnCount">0</div></div>
    </div>

    <div class="ledger-summary">
        <div class="card" style="background:#f8fafc;border-color:#94a3b8;">
            <div class="lbl"><i class="fas fa-flag mr-1"></i>Opening Balance</div>
            <div class="val" style="color:#475569;" id="summOpening">0.00</div>
        </div>
        {{-- Money in is green, money out is red — the same pairing the tables
             below use. The arrows point the way the cash moves, so the tile and
             its column can never be read as two different things. --}}
        <div class="card" style="background:#f0fdf4;border-color:#16a34a;">
            <div class="lbl"><i class="fas fa-arrow-down mr-1"></i>Total Debit <span style="font-weight:400;opacity:.75;">(in)</span></div>
            <div class="val" style="color:#15803d;" id="summDebit">0.00</div>
        </div>
        <div class="card" style="background:#fef2f2;border-color:#dc2626;">
            <div class="lbl"><i class="fas fa-arrow-up mr-1"></i>Total Credit <span style="font-weight:400;opacity:.75;">(out)</span></div>
            <div class="val" style="color:#b91c1c;" id="summCredit">0.00</div>
        </div>
        <div class="card" style="background:#fffbeb;border-color:#d97706;">
            <div class="lbl"><i class="fas fa-flag-checkered mr-1"></i>Closing Balance</div>
            <div class="val" style="color:#b45309;" id="summClosing">0.00</div>
        </div>
    </div>

    <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
        <div style="overflow-x:auto;">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        {{-- # and Action are not sortable: one is a row counter,
                             the other holds buttons. --}}
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">#</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="date">Date <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="reference">Reference <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="source">Source <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="description">Description / Note <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="debit">Debit <span class="normal-case font-normal tracking-normal opacity-70">(in)</span> <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="credit">Credit <span class="normal-case font-normal tracking-normal opacity-70">(out)</span> <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="balance">Balance <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Action</th>
                    </tr>
                </thead>
                <tbody id="ledgerBody"></tbody>
            </table>
        </div>
        <div id="ledgerEmpty" class="placeholder-box" style="display:none;">
            <i class="fas fa-inbox text-4xl mb-3 block"></i>
            <p>No transactions found for this account in the selected period.</p>
        </div>
    </div>
</div>

{{-- Deposit/Withdraw Modal --}}
<div class="modal-overlay" id="txnModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="txnModalTitle">Record Transaction</h3>
            <p id="txnModalSubtitle">—</p>
        </div>
        <form id="txnForm">
            <input type="hidden" name="type" id="txn_type">
            <div class="modal-body">
                <div class="form-group">
                    <label>Amount <span class="required">*</span></label>
                    <input type="number" name="amount" id="txn_amount" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Date <span class="required">*</span></label>
                    <input type="date" name="date" id="txn_date" required value="{{ date('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label>Contra Account <span class="required">*</span></label>
                    <select name="contra_account_id" id="txn_contra_account" class="select2" style="width:100%;" required>
                        <option value="">-- Select Account --</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" data-account-id="{{ $account->id }}">{{ $account->name }} ({{ $account->code }})</option>
                        @endforeach
                    </select>
                    <p style="font-size:0.72rem;color:#94a3b8;margin-top:4px;">This account's own ledger account is hidden here — picking it would cancel itself out.</p>
                </div>
                <div class="form-group">
                    <label>Reference No.</label>
                    <input type="text" name="reference" id="txn_reference" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <input type="text" name="remarks" id="txn_remarks" placeholder="Optional note">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeTxnModal()">Cancel</button>
                <button type="submit" class="btn-submit" id="txnSubmitBtn">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const SEARCH_URL      = "{{ route('role.banks.search', ['role' => $roleSlug]) }}";
const LOAD_URL        = "{{ route('role.banks.load',   ['role' => $roleSlug]) }}";
const STATEMENT_PDF_BASE = "{{ url($roleSlug . '/banks') }}";
const TXN_BASE         = "{{ url($roleSlug . '/banks') }}";
const REVERSE_BASE      = "{{ url($roleSlug . '/banks/journal') }}";
const CSRF              = "{{ csrf_token() }}";
const DASHBOARD_DATA_URL = "{{ route('role.banks.dashboard.data', ['role' => $roleSlug]) }}";

// `let`, not `const`: refreshDashboardInPlace() swaps the whole array after a
// cash entry so the banner, cards and activity feed re-render from fresh server
// figures without a page load.
let COMPANY_DATA = @json($companiesData);
let activeCompanyName = null;

const typeLabels = {
    bank:            { deposit: 'Deposit',  withdraw: 'Withdraw' },
    mobile_banking:  { deposit: 'Cash In',  withdraw: 'Cashout' },
    digital_wallet:  { deposit: 'Cash In',  withdraw: 'Cashout' },
    cash:            { deposit: 'Cash In',  withdraw: 'Cash Out' },
};
const typeIcons = {
    bank:            'fa-university',
    mobile_banking:  'fa-mobile-alt',
    digital_wallet:  'fa-wallet',
    cash:            'fa-money-bill-wave',
};

let selectedBank  = null;
let searchTimer   = null;

// ── Formatters ───────────────────────────────────────────────────────────────
function fmtMoney(v) { return Number(Math.abs(v)).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmtDate(d) { if (!d) return '—'; return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); }

// The small grey time under a date cell, used by both the recent-activity table
// and the per-bank ledger.
//
// journal_entries.date is a DATE column — a transaction has no clock time of its
// own. recorded_at is when the entry was written, arriving as
// 'YYYY-MM-DD HH:MM:SS' already in the app's Asia/Dhaka timezone. Both halves
// stay strings and are never parsed into a Date, so no browser timezone
// conversion can shift them. The time is shown only when it lands on the row's
// own day, so a backdated entry never displays a time belonging to another date.
function recordedTimeLine(dateYmd, recordedAt) {
    const [recDay, recTime] = (recordedAt || '').split(' ');
    return (recTime && recDay === dateYmd)
        ? `<div style="font-size:0.7rem;color:#94a3b8;margin-top:2px;">${recTime}</div>`
        : '';
}

// ── Date preset ──────────────────────────────────────────────────────────────
function resolveDates() {
    const preset = document.getElementById('datePreset').value;
    const now = new Date();
    let from = '', to = now.toISOString().split('T')[0];
    if      (preset === 'this_year')  from = now.getFullYear() + '-01-01';
    else if (preset === 'this_month') from = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-01';
    else if (preset === 'last_3')     { const d = new Date(now); d.setMonth(d.getMonth()-3); from = d.toISOString().split('T')[0]; }
    else if (preset === 'last_6')     { const d = new Date(now); d.setMonth(d.getMonth()-6); from = d.toISOString().split('T')[0]; }
    else if (preset === 'all_time')   { from = ''; to = ''; }
    else { from = document.getElementById('dateFrom').value; to = document.getElementById('dateTo').value; }
    return { from, to };
}
document.getElementById('datePreset').addEventListener('change', function () {
    const isCustom = this.value === 'custom';
    document.getElementById('dateFrom').style.display = isCustom ? 'inline-block' : 'none';
    document.getElementById('dateTo').style.display   = isCustom ? 'inline-block' : 'none';
});

// ── Company cards drive the whole landing view ────────────────────────────────
// preserveLedger: re-render this company's figures but leave whatever ledger is
// open exactly as it is. Used by the in-place refresh after a cash entry —
// switching companies should still close the ledger, but merely restating the
// same company's numbers must not, or every entry would shut the statement the
// user is working in.
function selectCompany(companyName, preserveLedger = false) {
    const company = COMPANY_DATA.find(c => c.name === companyName);
    if (!company) return;

    activeCompanyName = companyName;
    sessionStorage.setItem('activeBankCompany', companyName);

    document.querySelectorAll('.company-pick-card').forEach(el => {
        el.classList.toggle('is-active', el.dataset.companyName === companyName);
    });

    document.getElementById('bannerBalanceStat').textContent = (company.total_balance < 0 ? '-৳' : '৳') + fmtMoney(company.total_balance);
    document.getElementById('bannerBalanceLabel').textContent = companyName + ' Balance';
    document.getElementById('bannerAccountsStat').textContent = company.account_count;

    document.getElementById('recentTxnTitle').textContent = companyName + ' — Recent Transactions';
    document.getElementById('recentTxnSubtitle').textContent =
        'Last 14 days · ' + company.account_count + ' account' + (company.account_count === 1 ? '' : 's') + '. Click any row to open its full ledger.';

    const netUp = company.net_change >= 0;
    const netStat = document.getElementById('netChangeStat');
    const netVal  = document.getElementById('netChangeVal');
    netStat.style.background = netUp ? '#f0fdf4' : '#fef2f2';
    netVal.style.color = netUp ? '#16a34a' : '#dc2626';
    netVal.innerHTML = (netUp ? '<i class="fas fa-arrow-up"></i> +' : '<i class="fas fa-arrow-down"></i> -') + '৳' + fmtMoney(company.net_change);

    renderRecentTransactions(company.transactions);

    document.getElementById('bankCardsCompanyName').textContent = companyName + ' Accounts';
    renderBankCards(company.banks);
    document.getElementById('companyPicker').style.display = 'none';
    document.getElementById('companyBankCards').style.display = 'block';

    // Switching companies means whatever bank ledger was open no longer applies.
    if (!preserveLedger) {
        document.getElementById('ledgerArea').style.display = 'none';
        document.getElementById('placeholderArea').style.display = 'block';
    }
}

// ── Refresh after a cash entry, without a page load ──────────────────────────
// Re-pulls the same payload the page was rendered from and re-runs the existing
// render functions, so the banner, bank cards and activity feed show real server
// figures rather than numbers patched arithmetically on the client. The open
// ledger reloads too, and the scroll position is restored around the re-render.
//
// Falls back to a reload if the fetch fails — but remembers where the user was
// first, so even the fallback comes back to the same account and scroll offset.
function refreshDashboardInPlace() {
    const scrollY = window.scrollY;
    const openBankId = selectedBank ? selectedBank.id : null;

    return fetch(DASHBOARD_DATA_URL, { headers: { 'Accept': 'application/json' } })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(data => {
            if (!data || !Array.isArray(data.companies)) return Promise.reject();

            COMPANY_DATA = data.companies;

            // The active company may have been renamed away or dropped; fall
            // back rather than leaving the page showing stale figures.
            const stillThere = COMPANY_DATA.find(c => c.name === activeCompanyName);
            selectCompany(stillThere ? activeCompanyName : (COMPANY_DATA[0] || {}).name, true);

            // Re-point selectedBank at the refreshed card so its balance and
            // last-transaction date are current, then restate the ledger.
            if (openBankId) {
                const company = COMPANY_DATA.find(c => c.name === activeCompanyName);
                const fresh = (company ? company.banks : []).find(b => b.id === openBankId);
                if (fresh) selectedBank = Object.assign({}, selectedBank, fresh);
                loadLedger();
            }

            window.scrollTo({ top: scrollY });
        })
        .catch(() => {
            rememberPosition();
            window.location.reload();
        });
}

// ── Position memory, for the reloads that remain ─────────────────────────────
// Reversals and the refresh fallback still reload. Storing the open account and
// scroll offset means the page comes back where it was instead of at the top
// with the ledger closed. sessionStorage, so it dies with the tab.
function rememberPosition() {
    try {
        sessionStorage.setItem('bankDashPos', JSON.stringify({
            bankId: selectedBank ? selectedBank.id : null,
            company: activeCompanyName,
            scrollY: window.scrollY,
        }));
    } catch (e) { /* private mode — losing the position is not worth an error */ }
}

function restorePosition() {
    let pos = null;
    try {
        const raw = sessionStorage.getItem('bankDashPos');
        sessionStorage.removeItem('bankDashPos');
        if (raw) pos = JSON.parse(raw);
    } catch (e) { return; }
    if (!pos) return;

    if (pos.bankId) {
        const company = COMPANY_DATA.find(c => c.name === (pos.company || activeCompanyName));
        const bank = (company ? company.banks : []).find(b => b.id === pos.bankId);
        // Re-open the ledger first, then scroll — the ledger area is display:none
        // until it loads, so scrolling before it exists would land short.
        if (bank) {
            selectBank(bank);
            loadLedger();
        }
    }

    if (pos.scrollY) {
        // Two frames: one for the card/ledger DOM to be laid out, one for the
        // browser to have a scrollable height to honour.
        requestAnimationFrame(() => requestAnimationFrame(() => window.scrollTo({ top: pos.scrollY })));
    }
}

function showCompanyPicker() {
    document.getElementById('companyBankCards').style.display = 'none';
    document.getElementById('companyPicker').style.display = 'grid';
}

function bankCardHtml(bank) {
    const icon = typeIcons[bank.type] || typeIcons.bank;
    const lastTxn = bank.last_transaction_date
        ? 'Last: ' + new Date(bank.last_transaction_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
        : 'No transactions yet';
    const sectionTag = bank.section
        ? `<div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;margin-bottom:0.3rem;"><i class="fas fa-building mr-1"></i>${bank.section}</div>`
        : '';
    return `<div class="bank-acc-card type-${bank.type}" title="Open ${bank.name}'s full ledger" onclick='openBankFromRow(${JSON.stringify(bank)})'>
        <div class="top-row">
            <div style="display:flex;gap:10px;align-items:center;">
                <div class="bank-acc-icon"><i class="fas ${icon}"></i></div>
                <div>
                    ${sectionTag}
                    <div class="name">${bank.name}</div>
                    <div class="sub">${bank.account_name || ''}${bank.branch_name ? ' · ' + bank.branch_name : ''}</div>
                </div>
            </div>
            <span class="badge-status ${bank.status ? 'badge-active' : 'badge-inactive'}">${bank.status ? 'Active' : 'Inactive'}</span>
        </div>
        ${balanceHtml(bank.balance)}
        <div class="last-txn">${lastTxn}</div>
    </div>`;
}

// An overdrawn account must not look like a healthy one.
//
// fmtMoney() runs Math.abs(), so a balance of -100 rendered as "৳100.00" — every
// other place on this page compensates by adding its own sign or Dr/Cr, but the
// card did not. Office Cash sat at -100.00 showing "৳100.00" on 2026-08-13 and
// read as perfectly fine.
function balanceHtml(v) {
    const n = Number(v) || 0;
    if (n < 0) {
        return `<div class="balance" style="color:#dc2626;">-৳${fmtMoney(n)}
            <span style="font-size:0.7rem;font-weight:700;background:#fee2e2;color:#b91c1c;border-radius:20px;padding:1px 7px;margin-left:4px;vertical-align:middle;">OVERDRAWN</span>
        </div>`;
    }
    return `<div class="balance">৳${fmtMoney(n)}</div>`;
}

function renderBankCards(banks) {
    const grid = document.getElementById('bankCardsGrid');
    if (!banks.length) {
        grid.innerHTML = '<div style="color:#94a3b8;font-size:0.85rem;padding:1rem;">No accounts under this company.</div>';
        return;
    }

    // The "Epal Group" umbrella tags each bank with which real company it
    // belongs to. Sort by section so same-company cards still cluster
    // together in reading order, but render as one continuous grid so cards
    // fill each row completely — a 2-card company no longer leaves the rest
    // of that row empty before the next company starts on a new line.
    const sorted = (banks[0] && banks[0].section)
        ? [...banks].sort((a, b) => a.section.localeCompare(b.section))
        : banks;

    grid.style.display = 'grid';
    grid.style.gridTemplateColumns = 'repeat(auto-fill,minmax(270px,1fr))';
    grid.style.gap = '1rem';
    grid.innerHTML = sorted.map(bankCardHtml).join('');
}

// The last set handed to the table, held so the search box can re-filter it
// without another round trip. Every caller still uses renderRecentTransactions.
let RECENT_TXNS = [];

// Sort state for the table. Date descending is the default, so the newest
// transaction is always the first row.
let TXN_SORT = { key: 'date', dir: 'desc' };

// What "date" means to the sorter, for both bank tables.
//
// It cannot be `date` alone. That field is 'YYYY-MM-DD' — journal_entries.date
// is a DATE column with no clock time — so every entry booked today compares as
// EQUAL. Array.sort is stable, so a wall of equal keys keeps the order it
// arrived in, which is the server's chronological one: nine of today's rows
// stayed oldest-first while the header arrow pointed down.
//
// recorded_at is the entry's created_at, 'YYYY-MM-DD HH:MM:SS' — the very clock
// time already printed under each date — and entry_id settles anything booked
// inside the same second. Together the key is unique, so "latest first" now
// means the latest of today too, not just the latest day.
//
// Compared as one string, not parsed: every part is fixed-width, so plain
// lexicographic order IS chronological order, and the id is zero-padded so it
// compares numerically rather than as '9' > '10'.
function txnInstant(t) {
    return (t.date || '') + '|' + (t.recorded_at || '') + '|'
         + String(t.entry_id || '').padStart(12, '0');
}

const TXN_SORT_VALUES = {
    date:        t => txnInstant(t),
    bank:        t => ((t.bank && t.bank.name) || '').toLowerCase(),
    description: t => (t.description || '').toLowerCase(),
    debit:       t => Number(t.debit) || 0,
    credit:      t => Number(t.credit) || 0,
    balance:     t => Number(t.company_balance_after) || 0,
};

// Shared by both bank tables. slice() first — sort mutates, and the stored list
// must keep the order the server sent, which is chronological and the order the
// running balance was computed in.
//
// That arrival order is still the tiebreak for the money and text columns:
// Array.sort is stable, so rows with an equal key hold their sequence. The date
// column deliberately no longer leans on it — txnInstant() returns a unique key,
// because a stable tie there is precisely what kept today's entries oldest-first
// under a descending arrow.
function sortRows(list, state, values) {
    const pick = values[state.key];
    if (!pick) return list;
    const dir = state.dir === 'asc' ? 1 : -1;

    return list.slice().sort((a, b) => {
        const av = pick(a), bv = pick(b);
        if (av < bv) return -dir;
        if (av > bv) return  dir;
        return 0;
    });
}

function sortTxns(list) { return sortRows(list, TXN_SORT, TXN_SORT_VALUES); }

// Fills in the header arrows for whichever table `root` selects.
function paintSortIndicators(root, state) {
    document.querySelectorAll(`${root} thead th[data-sort]`).forEach(th => {
        const active = th.dataset.sort === state.key;
        th.classList.toggle('is-active-sort', active);
        const icon = th.querySelector('.th-sort i');
        if (!icon) return;
        icon.className = 'fas ' + (active ? (state.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort');
    });
}

// Wires a table's headers: click to sort by that column, click again to flip.
function bindSortHeaders(root, state, onChange) {
    document.querySelectorAll(`${root} thead th[data-sort]`).forEach(th => {
        th.addEventListener('click', () => {
            const key = th.dataset.sort;
            if (state.key === key) {
                state.dir = state.dir === 'asc' ? 'desc' : 'asc';
            } else {
                state.key = key;
                // Text reads better A→Z; dates and money read better biggest first.
                state.dir = TEXT_SORT_KEYS.includes(key) ? 'asc' : 'desc';
            }
            paintSortIndicators(root, state);
            onChange();
        });
    });
    paintSortIndicators(root, state);
}

const TEXT_SORT_KEYS = ['bank', 'description', 'reference', 'source'];

// Search then sort, in that order — the visible set for any repaint.
function visibleTxns() {
    return sortTxns(filterTxns(RECENT_TXNS, txnSearchTerm()));
}

function paintTxnSortIndicators() { paintSortIndicators('.txn-table', TXN_SORT); }

function renderRecentTransactions(transactions) {
    RECENT_TXNS = Array.isArray(transactions) ? transactions : [];
    updateTodayFlow(RECENT_TXNS);
    paintRecentTransactions(visibleTxns());
}

function txnSearchTerm() {
    const el = document.getElementById('txnSearch');
    return el ? el.value.trim().toLowerCase() : '';
}

// One place for the row date, so the Today figures below and the DATE column
// can never disagree about which calendar day a transaction falls on.
function shortTxnDate(d) {
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
}

function filterTxns(list, term) {
    if (!term) return list;
    return list.filter(t => {
        const bank = t.bank || {};
        return [bank.name, bank.account_number, bank.company_name,
        t.description, shortTxnDate(t.date), t.recorded_at, t.debit, t.credit]

            .some(v => v !== null && v !== undefined && String(v).toLowerCase().includes(term));
    });
}

// Today's movement across the same 14-day set the table was given. Debit is
// money into the account (the row's 'In' badge), credit is money out.
// Computed from the full set, never the filtered one — these report the day.
function updateTodayFlow(list) {
    const dayKey = d => new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const today = dayKey(new Date());
    let todayIn = 0, todayOut = 0;

    list.forEach(t => {
        if (dayKey(t.date) !== today) return;
        todayIn  += Number(t.debit)  || 0;
        todayOut += Number(t.credit) || 0;
    });

    const inEl  = document.getElementById('todayInVal');
    const outEl = document.getElementById('todayOutVal');
    if (inEl)  inEl.textContent  = '৳' + fmtMoney(todayIn);
    if (outEl) outEl.textContent = '৳' + fmtMoney(todayOut);
}

function paintRecentTransactions(transactions) {
    const tbody = document.getElementById('recentTxnBody');
    if (!transactions.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:1.5rem;">'
            + (txnSearchTerm() ? 'No transactions match your search.' : 'No transactions in the last 14 days.')
            + '</td></tr>';
        return;
    }

    tbody.innerHTML = transactions.map(t => {
        const bank = t.bank;
        const balCls = t.company_balance_after < 0 ? '#dc2626' : '#1d4ed8';
        const rowAttrs = bank
            ? `style="cursor:pointer;" title="Open ${bank.name}'s full ledger" onclick='openBankFromRow(${JSON.stringify(bank)})'`
            : '';
        const timeLine = recordedTimeLine(t.date, t.recorded_at);


        // Quick-scan in/out badge next to the bank name — easier for a
        // non-accountant to read at a glance than separate Debit/Credit cells.
        const isIn = t.debit > 0;
        const flowBadge = `<span style="display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:6px;background:${isIn ? '#dcfce7' : '#fee2e2'};color:${isIn ? '#15803d' : '#b91c1c'};">
            <i class="fas fa-arrow-${isIn ? 'down' : 'up'}"></i> ${isIn ? 'In' : 'Out'}
        </span>`;

        // That specific bank's own opening → closing balance for THIS row —
        // shown small, under the account/company line, so row height doesn't grow.
        const openCls  = t.bank_balance_before < 0 ? '#dc2626' : '#1d4ed8';
        const closeCls = t.bank_balance_after < 0 ? '#dc2626' : '#1d4ed8';
        const bankBalLine = bank ? `<div style="font-size:0.7rem;color:#94a3b8;margin-top:1px;">
            Opening: <span style="color:${openCls};font-weight:600;">৳${fmtMoney(t.bank_balance_before)} ${t.bank_balance_before < 0 ? 'Cr' : 'Dr'}</span>
            &nbsp;·&nbsp; Closing: <span style="color:${closeCls};font-weight:600;">৳${fmtMoney(t.bank_balance_after)} ${t.bank_balance_after < 0 ? 'Cr' : 'Dr'}</span>
        </div>` : '';

        return `<tr ${rowAttrs}>
            <td style="white-space:nowrap;">${t.date}${timeLine}</td>

            <td>
                <div style="font-weight:600;color:#1e293b;">${bank ? bank.name : '—'}${flowBadge}</div>
                ${bank ? `<div style="font-size:0.7rem;color:#94a3b8;margin-top:1px;">${bank.account_number || ''}${bank.company_name ? ' · ' + bank.company_name : ''}</div>` : ''}
                ${bankBalLine}
            </td>
            {{-- stopPropagation: the whole row is a click target that opens the
                 bank's ledger, and the pencil must not trigger it. --}}
            <td>${descEditButton(t)}${t.description || ''}</td>
            <td style="text-align:right;">${t.debit > 0 ? `<span class="txn-amount-debit">৳${fmtMoney(t.debit)}</span>` : '—'}</td>
            <td style="text-align:right;">${t.credit > 0 ? `<span class="txn-amount-credit">৳${fmtMoney(t.credit)}</span>` : '—'}</td>
            <td style="text-align:right;">
                <span style="font-family:monospace;font-weight:700;color:${balCls};">৳${fmtMoney(t.company_balance_after)} <small>${t.company_balance_after < 0 ? 'Cr' : 'Dr'}</small></span>
            </td>
        </tr>`;
    }).join('');
}

// ── Bank search ────────────────────────────────────────────────────────────────
document.getElementById('bankSearch').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 1) { closeDropdown(); return; }
    searchTimer = setTimeout(() => doSearch(q), 280);
});
function doSearch(q) {
    const companyId = document.getElementById('companyFilter').value;
    const params = new URLSearchParams({ q: q || '' });
    if (companyId) params.set('company_id', companyId);
    fetch(`${SEARCH_URL}?${params}`).then(r => r.json()).then(renderDropdown);
}
function renderDropdown(banks) {
    const dd = document.getElementById('bankDropdown');
    if (!banks.length) {
        dd.innerHTML = '<div style="padding:14px;color:#94a3b8;font-size:0.8rem;">No bank accounts found</div>';
        dd.style.display = 'block'; return;
    }
    dd.innerHTML = banks.map(b => `
        <div class="bank-dropdown-item" onclick='selectBank(${JSON.stringify(b)})'>
            <div class="bank-dropdown-avatar">${b.initials}</div>
            <div style="flex:1;min-width:0;">
                <p style="font-size:0.85rem;font-weight:600;color:#1e293b;margin:0;">${b.name}</p>
                <p style="font-size:0.72rem;color:#94a3b8;margin:0;">${b.account_number || ''} ${b.company_name ? '· ' + b.company_name : ''}</p>
            </div>
            <div style="font-size:0.78rem;font-weight:700;color:${b.balance >= 0 ? '#1d4ed8' : '#dc2626'};">৳${fmtMoney(b.balance)}</div>
        </div>
    `).join('');
    dd.style.display = 'block';
}
function closeDropdown() { document.getElementById('bankDropdown').style.display = 'none'; }
function selectBank(b) {
    selectedBank = b;
    document.getElementById('bankSearch').value = b.name;
    document.getElementById('chipBankName').textContent = b.name;
    document.getElementById('selectedBankChip').style.display = 'inline-flex';
    document.getElementById('loadBtn').disabled = false;
    closeDropdown();
}
function clearSelectedBank() {
    selectedBank = null;
    document.getElementById('bankSearch').value = '';
    document.getElementById('selectedBankChip').style.display = 'none';
    document.getElementById('loadBtn').disabled = true;
    document.getElementById('ledgerArea').style.display = 'none';
    document.getElementById('placeholderArea').style.display = 'block';
}

// ── Click a row in "Recent Bank Transactions" to jump straight into that
// account's full ledger — no need to type-search first.
function openBankFromRow(b) {
    selectBank(b);
    loadLedger();
}
document.addEventListener('click', e => {
    if (!e.target.closest('#bankSearch') && !e.target.closest('#bankDropdown')) closeDropdown();
});

// ── Load ledger ──────────────────────────────────────────────────────────────
function loadLedger() {
    if (!selectedBank) return;
    const { from, to } = resolveDates();
    const icon = document.getElementById('loadIcon');
    icon.classList.add('fa-spin');
    document.getElementById('loadBtn').disabled = true;

    const params = new URLSearchParams({ bank_id: selectedBank.id });
    if (from) params.set('from', from);
    if (to)   params.set('to', to);

    fetch(`${LOAD_URL}?${params}`).then(r => r.json())
        .then(renderLedger)
        .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Failed to load statement.' }))
        .finally(() => { icon.classList.remove('fa-spin'); document.getElementById('loadBtn').disabled = false; });
}

function renderLedger(data) {
    const { bank, opening_balance, total_debit, total_credit, closing_balance, transactions, date_from, date_to } = data;

    document.getElementById('ledgerBankName').textContent = bank.name;
    document.getElementById('ledgerPeriod').textContent = date_from ? (fmtDate(date_from) + ' → ' + fmtDate(date_to)) : 'All Time';

    document.getElementById('infoAccountName').textContent   = bank.account_name || '—';
    document.getElementById('infoAccountNumber').textContent = bank.account_number || '—';
    document.getElementById('infoType').textContent          = (bank.type || '').replace('_',' ').replace(/\b\w/g, c => c.toUpperCase()) + (bank.account_type ? ' · ' + bank.account_type.charAt(0).toUpperCase() + bank.account_type.slice(1) : '');
    document.getElementById('infoCompany').textContent       = bank.company_name || '—';
    document.getElementById('infoTxnCount').textContent      = transactions.length;

    document.getElementById('summOpening').textContent = fmtMoney(opening_balance) + (opening_balance < 0 ? ' Cr' : ' Dr');
    document.getElementById('summDebit').textContent   = fmtMoney(total_debit);
    document.getElementById('summCredit').textContent  = fmtMoney(total_credit);
    document.getElementById('summClosing').textContent = fmtMoney(closing_balance) + (closing_balance < 0 ? ' Cr' : ' Dr');

    const labels = typeLabels[bank.type] || typeLabels.bank;
    document.getElementById('depositLabel').textContent  = labels.deposit;
    document.getElementById('withdrawLabel').textContent = labels.withdraw;

    const pdfParams = new URLSearchParams();
    if (date_from) pdfParams.set('date_from', date_from);
    if (date_to)   pdfParams.set('date_to', date_to);
    document.getElementById('printStatementLink').href = `${STATEMENT_PDF_BASE}/${bank.id}/statement/pdf?${pdfParams}`;

    LEDGER_DATA = data;
    paintLedgerRows();

    document.getElementById('ledgerArea').style.display = 'block';
    document.getElementById('placeholderArea').style.display = 'none';

    scrollToLedger();
}

// Bring the statement into view after a bank is opened — it renders below the
// account cards, so without this you land on the same screen you clicked from
// and have to scroll to find what you asked for.
//
// The page scrolls on the window, and layout/header.blade.php is sticky at the
// top, so the offset is measured from the header itself rather than hardcoded:
// if that bar ever changes height, this follows it instead of tucking the
// statement underneath it.
function scrollToLedger() {
    const area = document.getElementById('ledgerArea');
    if (!area) return;

    const header = document.querySelector('header.header');
    const offset = (header ? header.offsetHeight : 0) + 12;
    const top    = area.getBoundingClientRect().top + window.pageYOffset - offset;

    // Honour the OS "reduce motion" setting — the same jump, without the glide.
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    window.scrollTo({ top: Math.max(top, 0), behavior: reduced ? 'auto' : 'smooth' });
}

// The ledger's sort state. Newest first, the house default for every
// transaction table — the entry you just posted is the one you came to see, and
// you should not have to scroll a thousand rows to find it. The statement still
// reads as a statement: paintLedgerRows() flips the Closing/Opening rows to
// match, so the balance the list starts from is always the one printed above it.
// Click a header to change it.
let LEDGER_SORT = { key: 'date', dir: 'desc' };
let LEDGER_DATA = null;

const LEDGER_SORT_VALUES = {
    // Day, then clock time, then id — see txnInstant(). The day alone ties every
    // entry booked today and leaves them oldest-first under a descending arrow.
    date:        r => txnInstant(r),
    reference:   r => (r.reference || '').toLowerCase(),
    source:      r => (r.source || '').toLowerCase(),
    description: r => (r.description || '').toLowerCase(),
    debit:       r => Number(r.debit) || 0,
    credit:      r => Number(r.credit) || 0,
    balance:     r => Number(r.balance) || 0,
};

// JSON for a single-quoted HTML attribute. JSON.stringify escapes double quotes
// but not single ones, so a remark containing an apostrophe would otherwise end
// the attribute early.
function attrJson(v) { return JSON.stringify(v).replace(/'/g, '&#39;'); }

// Push an edited remark into every copy of that transaction the page holds, so
// the recent-activity feed and an open ledger can never disagree.
//
// COMPANY_DATA owns the row objects RECENT_TXNS points at — renderRecentTransactions
// stores the array by reference — so patching here also survives switching
// companies and back without a reload.
//
// Every matching row is updated, not just the first: a deposit whose contra
// account is another bank in the same company produces two rows for one entry.
function applyRemarksEverywhere(entryId, description, note) {
    const patch = list => {
        if (!Array.isArray(list)) return;
        list.forEach(r => {
            if (r.entry_id === entryId) {
                r.description = description;
                r.note        = note;
            }
        });
    };

    COMPANY_DATA.forEach(c => patch(c.transactions));
    if (LEDGER_DATA) patch(LEDGER_DATA.transactions);

    paintRecentTransactions(visibleTxns());
    paintLedgerRows();
}

// The pencil for a row, rendered before the description text. Every row gets
// one; edit_scope decides whether saving rewrites the description or just the
// note. stopPropagation is harmless in the ledger and required in the feed,
// where the whole row is a click target that opens the bank's ledger.
function descEditButton(row) {
    if (!row || !row.entry_id) return '';
    const scope = row.edit_scope === 'description' ? 'description' : 'note';
    return `<button type="button" class="desc-edit" title="Edit ${scope === 'description' ? 'remarks' : 'note'}"
        onclick='event.stopPropagation(); editRemarks(${row.entry_id}, ${attrJson(row.note || "")}, ${attrJson(scope)})'><i class="fas fa-pen"></i></button>`;
}

// Edit the text on a transaction. Never anything numeric — a wrong amount is
// corrected by reversing the entry, which keeps both figures on the record.
//
// For our own deposit/withdraw entries the server rebuilds "Deposit to <bank>"
// around the new remark. For everything else — party payments, transfers,
// reversals — only the note is written, because their descriptions are derived
// from records this screen does not own. The server decides which; scope here
// only sets the wording.
function editRemarks(entryId, currentNote, scope) {
    const editsDescription = scope === 'description';

    Swal.fire({
        title: editsDescription ? 'Edit remarks' : 'Edit note',
        input: 'text',
        inputValue: currentNote || '',
        inputPlaceholder: 'Optional note',
        inputAttributes: { maxlength: 255 },
        // Named for what is actually being typed — the remark — not for the
        // description, which is rebuilt around it and is never typed directly.
        footer: editsDescription
            ? 'Only the remark after the dash. "Deposit to …" is generated and stays as it is.'
            : 'This entry\'s description comes from another record, so only the note below it changes.',
        showCancelButton: true,
        confirmButtonText: 'Save',
        confirmButtonColor: '#4f46e5',
    }).then(res => {
        if (!res.isConfirmed) return;

        fetch(`${REVERSE_BASE}/${entryId}/remarks`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify({ remarks: res.value || '' }),
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Could not update remarks.' });
                return;
            }

            applyRemarksEverywhere(entryId, data.description, data.note);
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' }));
    });
}

function paintLedgerRows() {
    if (!LEDGER_DATA) return;
    const { opening_balance, total_debit, total_credit, closing_balance, transactions, date_from } = LEDGER_DATA;

    const tbody = document.getElementById('ledgerBody');
    tbody.innerHTML = '';

    if (!transactions.length && opening_balance === 0) {
        document.getElementById('ledgerEmpty').style.display = 'block';
    } else {
        document.getElementById('ledgerEmpty').style.display = 'none';

        {{-- Opening and Closing are written outside the loop, so they stay
             pinned to the ends whatever the rows are sorted by. Which end each
             one takes follows the date direction: the balance a reader meets
             first has to be the one the rows below it start from, so on the
             newest-first default Closing sits on top and Opening at the foot.
             Any other sort column has no chronology to honour, so those keep the
             statement's natural Opening-top / Closing-bottom frame. --}}
        const openingRow = `
        <tr style="background:#eff6ff;color:#1d4ed8;font-style:italic;font-size:0.75rem;border-bottom:1px solid #f1f5f9;">
            <td class="px-4 py-2.5">—</td>
            {{-- date_from is already Y-m-d from the date input; printed raw so the
                 opening row matches the transaction rows in this column. --}}
            <td class="px-4 py-2.5">${date_from ? date_from : 'B/F'}</td>
            <td class="px-4 py-2.5" colspan="3"><i class="fas fa-arrow-right mr-1"></i>Opening Balance</td>
            <td class="px-4 py-2.5 text-right">—</td>
            <td class="px-4 py-2.5 text-right">—</td>
            <td class="px-4 py-2.5 text-right font-mono font-bold">${fmtMoney(opening_balance)} ${opening_balance < 0 ? 'Cr' : 'Dr'}</td>
            <td class="px-4 py-2.5"></td>
        </tr>`;

        const closingRow = `
        <tr style="background:#fffbeb;font-weight:700;font-size:0.85rem;">
            <td class="px-4 py-3" colspan="5"><i class="fas fa-flag-checkered mr-1"></i>Closing Balance</td>
            <td class="px-4 py-3 text-right font-mono text-green-700">${fmtMoney(total_debit)}</td>
            <td class="px-4 py-3 text-right font-mono text-red-600">${fmtMoney(total_credit)}</td>
            <td class="px-4 py-3 text-right font-mono ${closing_balance < 0 ? 'text-red-600' : 'text-blue-700'}">${fmtMoney(closing_balance)} ${closing_balance < 0 ? 'Cr' : 'Dr'}</td>
            <td class="px-4 py-3"></td>
        </tr>`;

        const newestFirst = LEDGER_SORT.key === 'date' && LEDGER_SORT.dir === 'desc';

        tbody.innerHTML += newestFirst ? closingRow : openingRow;

        sortRows(transactions, LEDGER_SORT, LEDGER_SORT_VALUES).forEach((row, i) => {
            const balCls = row.balance < 0 ? 'text-red-600' : 'text-blue-700';

            let actionCell;
            if (row.is_reversal) {
                actionCell = `<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:#ede9fe;color:#6d28d9;">Reversal</span>`;
            } else if (row.is_reversed) {
                actionCell = `<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:#f1f5f9;color:#64748b;">Reversed</span>`;
            } else if (row.can_reverse) {
                actionCell = `<button type="button" onclick="reverseTransaction(${row.entry_id})"
                    style="padding:4px 10px;border-radius:6px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;font-size:11px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-rotate-left"></i> Reverse</button>`;
            } else {
                actionCell = '';
            }

            tbody.innerHTML += `
            <tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="px-4 py-2.5 text-gray-400">${i + 1}</td>
                <td class="px-4 py-2.5 whitespace-nowrap text-gray-700">${row.date || '—'}${recordedTimeLine(row.date, row.recorded_at)}</td>
                <td class="px-4 py-2.5">${row.reference ? `<span class="font-mono bg-gray-100 border border-gray-200 rounded px-2 py-0.5 text-xs text-gray-600">${row.reference}</span>` : '<span class="font-mono text-gray-300">—</span>'}</td>
                <td class="px-4 py-2.5"><span class="inline-block rounded px-2 py-0.5 text-xs font-semibold capitalize bg-gray-100 text-gray-600">${(row.source||'').replace(/_/g,' ')}</span></td>
                {{-- The pencil uses can_reverse: the same gate the Reverse button
                     uses, and the same one updateRemarks() enforces server-side.
                     Reversed entries are excluded because the reversal's own
                     description embeds a copy of this text. --}}
                <td class="px-4 py-2.5">
                    <div class="text-sm text-gray-800">${descEditButton(row)}${(row.description||'').slice(0,60)}</div>
                    ${row.note ? `<div class="text-xs text-gray-400 mt-0.5"><i class="fas fa-note-sticky mr-1"></i>${row.note}</div>` : ''}
                </td>
                {{-- Same colour language as the Recent Transactions table above:
                     debit green (money in), credit red (money out). This drawer
                     opens from a click on that table, so the two must agree. --}}
                <td class="px-4 py-2.5 text-right">${row.debit > 0 ? `<span class="font-mono font-semibold text-green-700">${fmtMoney(row.debit)}</span>` : '<span class="font-mono text-gray-300">—</span>'}</td>
                <td class="px-4 py-2.5 text-right">${row.credit > 0 ? `<span class="font-mono font-semibold text-red-600">${fmtMoney(row.credit)}</span>` : '<span class="font-mono text-gray-300">—</span>'}</td>
                <td class="px-4 py-2.5 text-right"><span class="font-mono text-sm font-bold ${balCls}">${fmtMoney(row.balance)} ${row.balance < 0 ? 'Cr' : 'Dr'}</span></td>
                <td class="px-4 py-2.5 text-center">${actionCell}</td>
            </tr>`;
        });

        tbody.innerHTML += newestFirst ? openingRow : closingRow;
    }
}

// ── Reverse a mistaken deposit/withdraw transaction ───────────────────────────
// Posts an equal-and-opposite journal entry instead of deleting the original,
// so the mistake and its correction both stay visible in the ledger.
function reverseTransaction(entryId) {
    Swal.fire({
        title: 'Reverse this transaction?',
        text: 'This posts an equal-and-opposite entry to cancel it out. The original stays in the ledger, tagged as reversed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, reverse it',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (!result.isConfirmed) return;

        fetch(`${REVERSE_BASE}/${entryId}/reverse`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: res.message || 'Reversed', timer: 1800, showConfirmButton: false,
                });
                // A reversal touches BOTH sides' totals, so the same in-place
                // refresh applies — it re-pulls every company's figures, not
                // just the open one.
                refreshDashboardInPlace();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Failed to reverse transaction.' });
            }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' }));
    });
}

// ── Deposit / Withdraw modal (AJAX) ───────────────────────────────────────────
// select2 for the Contra Account field — searchable, and dynamically excludes
// the currently selected bank's own linked account (picking it would post both
// ledger legs to the same account and cancel itself out).
let excludedContraAccountId = null;
function contraAccountMatcher(params, data) {
    if (!data.id) return $.trim(params.term) === '' ? data : null;
    if (excludedContraAccountId && data.element && String(data.element.getAttribute('data-account-id')) === String(excludedContraAccountId)) {
        return null;
    }
    if ($.trim(params.term) === '') return data;
    return data.text.toUpperCase().indexOf(params.term.toUpperCase()) > -1 ? data : null;
}
$(document).ready(function () {
    $('#txn_contra_account').select2({
        width: '100%',
        placeholder: '-- Select Account --',
        allowClear: true,
        dropdownParent: $('#txnModal .modal-box'),
        matcher: contraAccountMatcher,
    });
});

function openTxnModal(type) {
    if (!selectedBank) return;
    const label = (typeLabels[selectedBank.type] || typeLabels.bank)[type];
    document.getElementById('txn_type').value = type;
    document.getElementById('txnModalTitle').innerText = label + ' — ' + selectedBank.name;
    document.getElementById('txnModalSubtitle').innerText = type === 'deposit' ? 'Add money to this account' : 'Remove money from this account';
    document.getElementById('txnSubmitBtn').textContent = label;
    document.getElementById('txnSubmitBtn').style.background = type === 'deposit' ? '#16a34a' : '#dc2626';
    document.getElementById('txnForm').reset();
    document.getElementById('txn_type').value = type;
    document.getElementById('txn_date').value = new Date().toISOString().split('T')[0];

    excludedContraAccountId = selectedBank.account_id;
    $('#txn_contra_account').val(null).trigger('change');

    document.getElementById('txnModal').classList.add('active');
}
function closeTxnModal() { document.getElementById('txnModal').classList.remove('active'); }
document.getElementById('txnModal').addEventListener('click', function(e) { if (e.target === this) closeTxnModal(); });

document.getElementById('txnForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!selectedBank) return;
    const btn = document.getElementById('txnSubmitBtn');
    const originalLabel = btn.textContent;
    btn.disabled = true; btn.textContent = 'Saving…';

    fetch(`${TXN_BASE}/${selectedBank.id}/transaction`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
            type:               document.getElementById('txn_type').value,
            amount:              document.getElementById('txn_amount').value,
            date:                document.getElementById('txn_date').value,
            contra_account_id:   document.getElementById('txn_contra_account').value,
            reference:           document.getElementById('txn_reference').value,
            remarks:             document.getElementById('txn_remarks').value,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeTxnModal();
            // Toast, not a blocking dialog, and no reload: filing five cash
            // entries in a row should be five quick saves, not five page loads
            // that each drop you at the top of the page with the ledger shut.
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: res.message || 'Saved', timer: 1800, showConfirmButton: false,
            });
            refreshDashboardInPlace();
        } else {
            Swal.fire({ icon:'error', title:'Error', text: res.message || 'Failed to record transaction.' });
        }
    })
    .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Network error.' }))
    .finally(() => { btn.disabled = false; btn.textContent = originalLabel; });
});

// Search re-filters what is already loaded; it never refetches, so it stays
// correct whichever company is selected.
(function initTxnSearch() {
    const box = document.getElementById('txnSearch');
    if (!box) return;
    box.addEventListener('input', () => {
        paintRecentTransactions(visibleTxns());
    });
})();

// Click a column to sort by it; click it again to flip the direction. Both bank
// tables are bound the same way — the ledger repaints from LEDGER_DATA, so
// sorting it never refetches the statement.
(function initSortableTables() {
    bindSortHeaders('.txn-table', TXN_SORT, () => paintRecentTransactions(visibleTxns()));
    bindSortHeaders('#ledgerArea', LEDGER_SORT, paintLedgerRows);
})();

// ── Landing state: default to Epal Group, or whichever company was last
// selected this session (so a deposit/reversal reload doesn't bounce you back).
(function initDefaultCompany() {
    if (!COMPANY_DATA.length) return;
    const remembered = sessionStorage.getItem('activeBankCompany');
    const fallback = COMPANY_DATA.find(c => c.name.toLowerCase() === 'epal group') || COMPANY_DATA[0];
    const initial = (remembered && COMPANY_DATA.find(c => c.name === remembered)) ? remembered : fallback.name;
    selectCompany(initial);
    // Re-open whatever account was on screen before a reload, and scroll back to
    // it. A no-op on a normal visit — nothing is stored unless something was
    // about to reload the page.
    restorePosition();
})();
</script>
@endsection
