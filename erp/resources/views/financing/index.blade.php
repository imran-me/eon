@extends('layout.app')

@section('meta-information')
    <title>Capital &amp; Financing</title>
@endsection

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* select2 sits inside .fin-modal, a fixed overlay at z-index 1000, so its
           list has to render into the modal box or it opens behind the sheet. The
           height rules only match the native control this page already uses. */
        .fin-modal .select2-container{ z-index:1200; width:100% !important; }
        .select2-container--default .select2-selection--single{
            height:2.45rem; border:1px solid var(--fin-line,#e5e7eb); border-radius:.55rem; }
        .select2-container--default .select2-selection--single .select2-selection__rendered{
            line-height:2.45rem; font-size:.85rem; color:#111827; padding-left:.7rem; }
        .select2-container--default .select2-selection--single .select2-selection__arrow{ height:2.35rem; }
        .select2-results__option{ font-size:.85rem; }
        html.dark .select2-container--default .select2-selection--single{ background:#0f172a; border-color:#334155; }
        html.dark .select2-container--default .select2-selection--single .select2-selection__rendered{ color:#e2e8f0; }
        html.dark .select2-dropdown{ background:#1e293b; border-color:#334155; color:#e2e8f0; }
        /* Capital register filter follows the Manage Banks transaction bar. */
        .cap-lf-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:11px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
        .cap-lf-search{position:relative;flex:1 1 260px;min-width:200px}.cap-lf-search>i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;pointer-events:none}
        .cap-lf-search input{width:100%;height:34px;padding:0 30px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;font-size:12.5px;color:#1e293b;outline:none}.cap-lf-search input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}.cap-lf-search input::placeholder{color:#a3adbb}
        .cap-lf-kind,.cap-lf-dates input{height:34px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;padding:0 9px;font-size:12px;color:#334155;outline:none}.cap-lf-kind{min-width:150px}.cap-lf-kind:focus,.cap-lf-dates input:focus{border-color:#6366f1}
        .cap-lf-seg{display:flex;flex:0 0 auto;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#fff}.cap-lf-seg a{display:inline-flex;align-items:center;height:34px;padding:0 11px;border-left:1px solid #e2e8f0;color:#64748b;font-size:11.5px;font-weight:600;text-decoration:none;white-space:nowrap}.cap-lf-seg a:first-child{border-left:0}.cap-lf-seg a:hover{background:#f1f5f9;color:#334155}.cap-lf-seg a.is-on{background:#eef2ff;color:#4338ca;font-weight:700}
        .cap-lf-dates{display:flex;flex:0 0 auto;align-items:center;gap:6px}.cap-lf-dates span{font-size:11px;color:#94a3b8}.cap-lf-go{height:34px;padding:0 12px;border:0;border-radius:8px;background:#4f46e5;color:#fff;font-size:11.5px;font-weight:700;cursor:pointer}.cap-lf-go:hover{background:#4338ca}
        .cap-lf-action{height:34px;padding:0 12px;border:1px solid #e2e8f0;border-radius:8px;display:inline-flex;align-items:center;gap:6px;background:#fff;color:#475569;font-size:11.5px;font-weight:700;text-decoration:none}.cap-lf-action:hover{background:#f8fafc;color:#334155;border-color:#cbd5e1;text-decoration:none}
        html.dark .cap-lf-bar{background:#0f172a;border-color:#334155}html.dark .cap-lf-search input,html.dark .cap-lf-kind,html.dark .cap-lf-dates input,html.dark .cap-lf-seg,html.dark .cap-lf-action{background:#1e293b;color:#e2e8f0;border-color:#334155}
        /* Capital desk: one visual language for the overview and owner sheet. */
        .fin-scope-capital:not(.fin-owner-sheet)>.fin-hero{padding:18px 20px;margin-bottom:14px;border-radius:16px;background:linear-gradient(135deg,#253a9b 0%,#4f46e5 55%,#7c3aed 100%);box-shadow:0 10px 24px rgba(67,56,202,.16)}
        .fin-scope-capital:not(.fin-owner-sheet)>.fin-hero h2,.fin-scope-capital:not(.fin-owner-sheet)>.fin-hero p{color:#fff}.fin-scope-capital:not(.fin-owner-sheet)>.fin-hero p{opacity:.78}.fin-scope-capital:not(.fin-owner-sheet)>.fin-hero .fin-sep{background:rgba(255,255,255,.3)}
        .fin-scope-capital:not(.fin-owner-sheet)>.fin-hero .fin-btn-primary,.fin-scope-capital:not(.fin-owner-sheet)>.fin-hero .fin-btn{height:38px;border:0;box-shadow:none}.fin-scope-capital:not(.fin-owner-sheet)>.fin-hero .fin-btn-primary{background:#dcfce7;color:#15803d}.fin-scope-capital:not(.fin-owner-sheet)>.fin-hero .fin-btn{background:#fff1f2;color:#be123c}
        .fin-scope-capital:not(.fin-owner-sheet)>.grid{gap:12px!important;margin-bottom:14px!important}.fin-scope-capital:not(.fin-owner-sheet)>.grid>div{border-radius:14px!important;border-color:#e2e8f0!important;box-shadow:0 3px 10px rgba(15,23,42,.04)!important}
        .fin-scope-capital:not(.fin-owner-sheet)>.fin-note{border-radius:11px;margin-bottom:14px;background:#eff6ff;border-color:#bfdbfe}
        .fin-scope-capital:not(.fin-owner-sheet)>.fin-card{border-radius:15px;border:1px solid #e2e8f0;box-shadow:0 4px 16px rgba(15,23,42,.05);overflow:hidden}
        .fin-scope-capital:not(.fin-owner-sheet) .fin-card-head{padding:15px 16px!important;margin:0!important;background:#fff;border-bottom:1px solid #e2e8f0}
        .fin-scope-capital:not(.fin-owner-sheet) .fin-table thead th{background:#f1f5f9;color:#475569;padding:11px 12px}.fin-scope-capital:not(.fin-owner-sheet) .fin-table tbody td{padding:12px;border-color:#e2e8f0}.fin-scope-capital:not(.fin-owner-sheet) .fin-table tbody tr:hover{background:#f8fafc}
        .fin-scope-capital:not(.fin-owner-sheet) .fin-table .fin-strong{font-size:12.5px}.fin-scope-capital:not(.fin-owner-sheet) .fin-table .fin-sub{line-height:1.45}
        /* Capital entry sheet: grouped fields, more breathing room, clear route. */
        /* The base .fin-modal-box (styles.blade.php) is itself
           max-height:92vh + overflow-y:auto, scrolling head+body+foot as one
           unit. This modal ALSO capped .fin-modal-body to its own max-height
           + overflow-y:auto below — two nested scroll regions fighting over
           the same wheel events. On a short viewport the outer box needed its
           own scroll to reach the footer, but hovering the form (the inner
           region) routed scrolling to the body instead, so Save was reachable
           only by scrolling with the cursor somewhere outside the form —
           indistinguishable from "the button is gone". Fixed by making the
           box a column flexbox with ONE scroller (the body); head and foot
           stay fixed size and the footer is always visible without hunting
           for the right spot to scroll. */
        #finCapModal .fin-modal-box{width:min(920px,calc(100vw - 28px));max-width:920px;border-radius:18px;box-shadow:0 28px 70px rgba(15,23,42,.3);display:flex;flex-direction:column;overflow:hidden}
        /* .fin-modal-head/.fin-modal-body/.fin-modal-foot are children of
           #finCapForm, not of .fin-modal-box directly — the form itself
           (a plain block element) broke the flex-column chain, so their
           flex:0/flex:1/overflow-y:auto rules below never had any effect:
           .fin-modal-body just grew to fit all its content with nothing to
           scroll, and once that pushed the whole box past max-height:92vh,
           .fin-modal-box's overflow:hidden silently clipped the footer off
           with no way to reach it. The form itself must be the flex column. */
        #finCapModal .fin-modal-box>form{display:flex;flex-direction:column;min-height:0;flex:1 1 auto;overflow:hidden}
        /* .fin-modal-head is a plain block (styles.blade.php) — this modal is
           the only one in the file that puts a close button inside it, and
           margin-left:auto does nothing without a flex row to push against,
           so the X fell to its own line below the title instead of sitting
           top-right for both Investment and Drawing. */
        #finCapModal .fin-modal-head{padding:20px 24px;background:linear-gradient(135deg,#253a9b,#5b4ce0);color:#fff;border:0;flex:0 0 auto;display:flex;align-items:flex-start;justify-content:space-between;gap:16px}#finCapModal .fin-modal-head h3{font-size:19px;color:#fff}#finCapModal .fin-modal-head p{color:#dbeafe}
        #finCapModal .fin-cap-close{flex:0 0 auto;width:34px;height:34px;border:1px solid rgba(255,255,255,.25);border-radius:9px;background:rgba(255,255,255,.12);color:#fff;cursor:pointer}#finCapModal .fin-cap-close:hover{background:rgba(255,255,255,.22)}
        #finCapModal.is-drawing .fin-modal-head{background:linear-gradient(135deg,#991b1b,#e11d48)}
        #finCapModal .fin-modal-body{padding:20px 24px;background:#f8fafc;flex:1 1 auto;min-height:0;overflow-y:auto}#finCapModal .fin-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:14px 16px}
        #finCapModal .fin-field label{font-size:10px;letter-spacing:.035em;color:#475569}#finCapModal .fin-field input,#finCapModal .fin-field select,#finCapModal .fin-field textarea{background:#fff;height:40px;border-radius:8px}#finCapModal .fin-field textarea{height:auto}
        #finCapModal .fin-cap-section-title{grid-column:1/-1;display:flex;align-items:center;gap:8px;margin:3px 0 -2px;padding-bottom:8px;border-bottom:1px solid #dbe3ef;color:#334155;font-size:11px;font-weight:850;text-transform:uppercase;letter-spacing:.07em}#finCapModal .fin-cap-section-title i{color:#4f46e5}
        #finCapModal .fin-modal-body>.fin-field{margin-top:14px}#finCapModal .fin-hint{margin-top:14px;background:#eef2ff;border-color:#c7d2fe;color:#475569}#finCapModal .fin-modal-foot{padding:14px 24px;background:#fff;flex:0 0 auto}
        @media(max-width:760px){#finCapModal .fin-grid{grid-template-columns:1fr 1fr}}@media(max-width:520px){#finCapModal .fin-grid{grid-template-columns:1fr}.fin-scope-capital:not(.fin-owner-sheet)>.fin-hero{align-items:flex-start}.fin-scope-capital:not(.fin-owner-sheet)>.fin-hero>div:last-child{width:100%;flex-wrap:wrap}}
        .cap-print-options{display:grid;gap:8px;text-align:left}.cap-print-choice{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 12px;border:1px solid #dbe3ef;border-radius:9px;background:#fff;cursor:pointer}.cap-print-choice:hover{border-color:#818cf8;background:#f8faff}.cap-print-choice>span:first-child{display:flex;align-items:flex-start;gap:7px;flex-wrap:wrap}.cap-print-choice b{font-size:13px;color:#334155}.cap-print-choice small{display:block;width:100%;padding-left:21px;color:#94a3b8;font-size:10px}.cap-print-choice input[type=date]{height:31px;border:1px solid #cbd5e1;border-radius:6px;padding:0 7px;font-size:11px}.cap-print-range{display:flex;align-items:center;gap:5px}.cap-print-range em{font-style:normal;color:#94a3b8;font-size:10px}
    </style>
@endsection

@section('main-content')
@php
    $role = request()->route('role') ?: \Illuminate\Support\Str::slug(auth()->user()->getRoleNames()->first() ?? '');
    $capitalPrintBaseUrl = route('role.financing.capital.print', array_filter([
        'role' => $role,
        'owner_id' => $capitalOwnerId ?? null,
        'person' => ($capitalOwnerId ?? null) ? null : ($capitalPerson ?? null),
        'kind' => request('kind'),
        'date_from' => request('date_from'),
        'date_to' => request('date_to'),
        'q' => request('q'),
    ], static fn ($value) => $value !== null && $value !== ''));

    // Totals for the tiles. Read from the rows on this page only when the list
    // is paginated, which is why the labels say so — an honest "this page"
    // beats a total that silently means something else.
    $rows = $book === 'employee' ? $staffLoans : $loans;
@endphp

<div class="fin-scope fin-scope-{{ $book }} {{ $book === 'capital' && $capitalOwner ? 'fin-owner-sheet' : '' }}">

@include('layout.financing-tabs')

@if(session('success'))
    <div class="fin-note" style="background:#ecfdf5;border-color:#d1fae5;color:#065f46;">
        <i class="fas fa-circle-check" style="color:#059669;"></i>{{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="fin-note" style="background:#fff1f2;border-color:#ffe4e6;color:#9f1239;">
        <i class="fas fa-triangle-exclamation" style="color:#e11d48;"></i>{{ session('error') }}
    </div>
@endif

@php
    $finMeta = [
        'borrowed' => ['Loans We Took', 'What the group owes — bank loans, car and equipment EMI, working capital.', 'fa-building-columns', 'We owe'],
        'lent'     => ['Loans We Gave', 'Money the group lent out, and services taken now and paid for monthly.', 'fa-hand-holding-dollar', 'Owed to us'],
        'capital'  => ['Capital & Drawings', 'Money put into the company, and profit taken out.', 'fa-sack-dollar', 'Owner money'],
        'employee' => ['Employee Loans', 'Mirrored from Payroll › Loan Management — read-only here.', 'fa-user-shield', 'Payroll mirror'],
        'categories' => ['Categories', 'How loans are classified. A sub-category inherits its parent’s book.', 'fa-sliders', 'Setup'],
        'liability'  => ['Liability', 'Internal dues — who owes whom, and what is still unpaid.', 'fa-right-left', 'Between companies'],
    ];
    // Fall back rather than index blindly: a book added to the tab bar but not
    // to this array would take the whole desk down with an undefined index, for
    // every company, on a page that was working a moment ago.
    [$finTitle, $finBlurb, $finIcon, $finBadge] = $finMeta[$book] ?? $finMeta['borrowed'];
@endphp

{{-- Header and KPI cards follow the payroll Loan Management desk exactly: the
     two show the same kind of thing, so a user moving between them should not
     have to relearn the page. The KPI card itself is payroll's own partial,
     INCLUDED rather than reimplemented, so the two cannot drift apart. --}}
@php
    // Whole taka, the way the payroll book prints them. Display only — the
    // stored amounts keep their paise.
    $taka = fn ($amount) => number_format(round((float) $amount));
@endphp

{{-- .fin-hero holds ONLY the title and the action button. It is a flex row at
     desktop width, so anything left inside it lands on the same line — the KPI
     cards and the table below must stay outside it. --}}
<div class="fin-hero">
        {{-- Title and blurb share a baseline on one line, wrapping to two only
             when the width genuinely runs out. --}}
        <div class="fin-hero-title">
            <h2><i class="fas {{ $finIcon }}"></i>{{ $finTitle }}</h2>
            {{-- Decorative only, so it is hidden from assistive tech. --}}
            <span class="fin-sep" aria-hidden="true"></span>
            <p>{{ $finBlurb }}</p>
        </div>

        {{-- Match the book explicitly rather than excluding one. The earlier
             "not employee" test let Categories fall through to the loan button. --}}
        @if($book === 'capital')
            @can('create financing')
                <div style="display:flex;align-items:center;gap:.55rem;">
                    <button type="button" class="fin-btn-primary" onclick="openCapitalModal('investment')"><i class="fas fa-arrow-down"></i> Add Investment</button>
                    <button type="button" class="fin-btn" onclick="openCapitalModal('drawings')"><i class="fas fa-arrow-up"></i> Record Drawing</button>
                </div>
            @endcan
        @elseif($book === 'borrowed' || $book === 'lent')
            @can('create financing')
                <button type="button" class="fin-btn-primary" onclick="document.getElementById('finAddModal').style.display='flex'">
                    <i class="fas fa-plus"></i> Add {{ $book === 'borrowed' ? 'Borrowing' : 'Loan Given' }}
                </button>
            @endcan
        @elseif($book === 'categories')
            @can('create financing')
                <button type="button" class="fin-btn-primary" onclick="document.getElementById('finCatModal').style.display='flex'">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            @endcan
        @elseif($book === 'liability')
            {{-- Which KIND of liability. Cross-company is the only one built;
                 the rest are named here on purpose, so the shape of the desk is
                 visible before the work lands and nobody designs a second home
                 for the next kind. Rendered as disabled buttons rather than
                 links — a chip that navigates to an empty page is worse than one
                 that plainly says it is not ready. --}}
            @include('financing.partials.liability-switch', ['role' => $role])
        @endif
</div>{{-- /.fin-hero — everything below is a normal block --}}

@if($book === 'capital')
    @php
        $capIn = $capitalTotals['investment'];
        $capOut = $capitalTotals['drawings'];
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @include('payroll.partials.kpi', ['label' => 'Put In', 'value' => '৳ ' . $taka($capIn),
            'icon' => 'fa-arrow-down', 'iconBg' => '#dcfce7', 'iconText' => '#16a34a',
            'valueTone' => 'text-green-600', 'foot' => 'investment, filtered register'])
        @include('payroll.partials.kpi', ['label' => 'Taken Out', 'value' => '৳ ' . $taka($capOut),
            'icon' => 'fa-arrow-up', 'iconBg' => '#fee2e2', 'iconText' => '#dc2626',
            'valueTone' => 'text-red-600', 'foot' => 'drawings, filtered register'])
        @include('payroll.partials.kpi', ['label' => 'Net Position', 'value' => '৳ ' . $taka($capIn - $capOut),
            'icon' => 'fa-scale-balanced', 'iconBg' => '#ede9fe', 'iconText' => '#7c3aed',
            'foot' => 'in less out, filtered register'])
        @include('payroll.partials.kpi', ['label' => 'Entries', 'value' => number_format($capitalTotals['count']),
            'icon' => 'fa-list', 'iconBg' => '#dbeafe', 'iconText' => '#2563eb',
            'foot' => 'all owner movements'])
    </div>
@elseif($book === 'employee')
    @php
        $slAdvanced  = $staffLoans->sum(fn ($l) => (float) ($l->amount ?? 0));
        $slRemaining = $staffLoans->sum(fn ($l) => (float) ($l->remaining_amount ?? 0));
        $slEmiTotal  = $staffLoans->sum(fn ($l) => (float) ($l->monthly_deduction ?? 0));
        $slCarrying  = $staffLoans->filter(fn ($l) => (float) ($l->remaining_amount ?? 0) > 0)->count();
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @include('payroll.partials.kpi', [
            'label'     => 'Loan Outstanding',
            'value'     => '৳ ' . $taka($slRemaining),
            'icon'      => 'fa-building-columns',
            'iconBg'    => '#fee2e2',
            'iconText'  => '#dc2626',
            'valueTone' => $slRemaining > 0 ? 'text-red-600' : 'text-green-600',
            'goodDown'  => true,
            'foot'      => $slCarrying
                ? $slCarrying . ' ' . \Illuminate\Support\Str::plural('person', $slCarrying) . ' carrying a loan'
                : 'nobody is carrying a loan',
        ])
        @include('payroll.partials.kpi', ['label' => 'Total Disbursed', 'value' => '৳ ' . $taka($slAdvanced),
            'icon' => 'fa-money-bill-transfer', 'iconBg' => '#dbeafe', 'iconText' => '#2563eb',
            'foot' => $staffLoans->total() . ' ' . \Illuminate\Support\Str::plural('loan', $staffLoans->total()) . ', this page'])
        @include('payroll.partials.kpi', ['label' => 'Repaid', 'value' => '৳ ' . $taka(max(0, $slAdvanced - $slRemaining)),
            'icon' => 'fa-hand-holding-dollar', 'iconBg' => '#dcfce7', 'iconText' => '#16a34a',
            'valueTone' => 'text-green-600', 'foot' => 'recovered so far'])
        @include('payroll.partials.kpi', ['label' => 'Monthly EMI', 'value' => '৳ ' . $taka($slEmiTotal),
            'icon' => 'fa-calendar-check', 'iconBg' => '#ede9fe', 'iconText' => '#7c3aed',
            'foot' => $slEmiTotal ? 'scheduled payslip recovery' : 'no repayment schedule set'])
    </div>
@elseif($book === 'categories')
        @php
            $catTop  = $categoryTree->count();
            $catSub  = $categoryTree->sum(fn ($c) => $c->children->count());
            $catOff  = $categoryTree->filter(fn ($c) => ! $c->status)->count()
                     + $categoryTree->sum(fn ($c) => $c->children->filter(fn ($k) => ! $k->status)->count());
            $catUsed = $categoryTree->sum(fn ($c) => ($c->loans_count ?? 0) + $c->children->sum(fn ($k) => $k->loans_count ?? 0));
        @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @include('payroll.partials.kpi', ['label' => 'Categories', 'value' => number_format($catTop),
            'icon' => 'fa-folder', 'iconBg' => '#dbeafe', 'iconText' => '#2563eb', 'foot' => 'top level'])
        @include('payroll.partials.kpi', ['label' => 'Sub-categories', 'value' => number_format($catSub),
            'icon' => 'fa-folder-tree', 'iconBg' => '#ede9fe', 'iconText' => '#7c3aed', 'foot' => 'one level down'])
        @include('payroll.partials.kpi', ['label' => 'Loans Classified', 'value' => number_format($catUsed),
            'icon' => 'fa-tags', 'iconBg' => '#dcfce7', 'iconText' => '#16a34a', 'foot' => 'filed under a category'])
        @include('payroll.partials.kpi', ['label' => 'Switched Off', 'value' => number_format($catOff),
            'icon' => 'fa-eye-slash', 'iconBg' => '#f3f4f6', 'iconText' => '#6b7280',
            'foot' => 'hidden from new loans'])
    </div>
@elseif($book === 'liability')
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @include('payroll.partials.kpi', [
            'label'     => 'Still Owed',
            'value'     => '৳ ' . $taka($liabilityTotals['outstanding']),
            'icon'      => 'fa-right-left',
            'iconBg'    => '#fee2e2',
            'iconText'  => '#dc2626',
            'valueTone' => $liabilityTotals['outstanding'] > 0 ? 'text-red-600' : 'text-green-600',
            'goodDown'  => true,
            'foot'      => $liabilityTotals['active'] . ' active of ' . $liabilityTotals['count'],
        ])
        @include('payroll.partials.kpi', [
            'label'    => 'Raised In All',
            'value'    => '৳ ' . $taka($liabilityTotals['raised']),
            'icon'     => 'fa-money-bill-transfer',
            'iconBg'   => '#dbeafe',
            'iconText' => '#2563eb',
            'foot'     => 'every cross-company payment on record',
        ])
        @include('payroll.partials.kpi', [
            'label'    => 'Settled So Far',
            'value'    => '৳ ' . $taka($liabilityTotals['settled']),
            'icon'     => 'fa-circle-check',
            'iconBg'   => '#dcfce7',
            'iconText' => '#16a34a',
            'foot'     => 'paid back between companies',
        ])
        @include('payroll.partials.kpi', [
            'label'    => 'Company Pairs',
            'value'    => number_format($liabilityTotals['pairs']),
            'icon'     => 'fa-building',
            'iconBg'   => '#ede9fe',
            'iconText' => '#7c3aed',
            'foot'     => $liabilityTotals['pairs'] === 1 ? 'one pair owes' : 'pairs with something outstanding',
        ])
    </div>
@endif

{{-- ── Categories: the taxonomy, both levels in one tree ─────────────── --}}
@if($book === 'categories')
    <div class="fin-note">
        <i class="fas fa-circle-info"></i>
        A sub-category inherits its parent’s book, so it can never appear on a screen its
        parent has left. A category still used by a loan cannot be deleted — switch it off
        instead, and it stays on old records while disappearing from new ones.
    </div>

    <div class="fin-card">
        <div class="fin-card-head">
            <strong>Categories</strong>
            <span class="fin-sub" style="margin:0;">Edit a name in place, then Save on that row.</span>
        </div>

        <div style="overflow-x:auto;">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Book</th>
                        <th style="text-align:right;">Order</th>
                        <th style="text-align:right;">In use</th>
                        <th>Status</th>
                        <th style="width:1%;"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($categoryTree as $cat)
                    @php $catRows = collect([$cat])->concat($cat->children); @endphp
                    @foreach($catRows as $row)
                        @php $isChild = (bool) $row->parent_id; @endphp
                        <tr>
                            <td>
                                <form method="POST" action="{{ route('role.financing.categories.update', ['role' => $role, 'category' => $row->id]) }}"
                                      style="display:flex;align-items:center;gap:8px;" id="catForm{{ $row->id }}">
                                    @csrf @method('PUT')
                                    @if($isChild)
                                        <span class="fin-dim" style="padding-left:14px;">↳</span>
                                    @endif
                                    <input type="text" name="name" value="{{ $row->name }}"
                                           style="border:1px solid transparent;background:none;padding:5px 8px;border-radius:6px;
                                                  font-weight:{{ $isChild ? '400' : '600' }};color:var(--fin-ink);min-width:180px;"
                                           onfocus="this.style.borderColor='var(--fin-line)';this.style.background='#fff'"
                                           onblur="this.style.borderColor='transparent';this.style.background='none'">
                                </form>
                            </td>
                            <td>
                                @if($isChild)
                                    <span class="fin-dim">follows parent</span>
                                @else
                                    <select name="direction" form="catForm{{ $row->id }}"
                                            style="border:1px solid var(--fin-line);border-radius:6px;padding:5px 8px;font-size:.78rem;background:#fff;">
                                        <option value="" {{ $row->direction === null ? 'selected' : '' }}>Both books</option>
                                        <option value="borrowed" {{ $row->direction === 'borrowed' ? 'selected' : '' }}>Loans we took</option>
                                        <option value="lent" {{ $row->direction === 'lent' ? 'selected' : '' }}>Loans we gave</option>
                                    </select>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <input type="number" name="sort_order" value="{{ $row->sort_order }}" min="0" max="9999"
                                       form="catForm{{ $row->id }}"
                                       style="width:66px;text-align:right;border:1px solid var(--fin-line);border-radius:6px;padding:5px 8px;font-size:.78rem;">
                            </td>
                            <td style="text-align:right;" class="fin-num">{{ $row->loans_count ?? 0 }}</td>
                            <td>
                                <label style="display:inline-flex;align-items:center;gap:7px;cursor:pointer;font-size:.76rem;color:var(--fin-mute);">
                                    <input type="checkbox" name="status" value="1" form="catForm{{ $row->id }}"
                                           {{ $row->status ? 'checked' : '' }} style="width:auto;">
                                    {{ $row->status ? 'Active' : 'Off' }}
                                </label>
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                @can('edit financing')
                                    <button type="submit" form="catForm{{ $row->id }}" class="fin-btn"
                                            style="height:28px;padding:0 10px;font-size:.72rem;">Save</button>
                                @endcan
                                @can('delete financing')
                                    <button type="submit" form="catDel{{ $row->id }}" class="fin-btn-ghost"
                                            style="height:28px;padding:0 9px;font-size:.72rem;"
                                            onclick="return confirm('Remove {{ addslashes($row->name) }}?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="6" class="fin-empty">
                        <i class="fas fa-sliders"></i>
                        No categories yet. Add one — for example <em>Car Loan</em> under Loans we took.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Delete forms live outside the table: a form cannot be nested inside
         another, and each row already carries its update form. --}}
    @can('delete financing')
        @foreach($categoryTree as $cat)
            @foreach(collect([$cat])->concat($cat->children) as $row)
                <form method="POST" id="catDel{{ $row->id }}" style="display:none;"
                      action="{{ route('role.financing.categories.destroy', ['role' => $role, 'category' => $row->id]) }}">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        @endforeach
    @endcan

    @can('create financing')
    <div class="fin-modal" id="finCatModal">
        <div class="fin-modal-box" style="max-width:520px;">
            <form method="POST" action="{{ route('role.financing.categories.store', ['role' => $role]) }}">
                @csrf
                <div class="fin-modal-head">
                    <h3>New category</h3>
                    <p>Leave the parent empty for a top-level category.</p>
                </div>
                <div class="fin-modal-body">
                    <div class="fin-field">
                        <label>Name <span>*</span></label>
                        <input type="text" name="name" required placeholder="Car Loan">
                    </div>
                    <div class="fin-field">
                        <label>Parent</label>
                        <select name="parent_id">
                            <option value="">— none, this is a top-level category —</option>
                            @foreach($categoryTree as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fin-field">
                        <label>Book</label>
                        <select name="direction">
                            <option value="">Both books</option>
                            <option value="borrowed">Loans we took</option>
                            <option value="lent">Loans we gave</option>
                        </select>
                    </div>
                    <p class="fin-hint">
                        <i class="fas fa-circle-info"></i>
                        A sub-category ignores this and takes its parent’s book.
                    </p>
                </div>
                <div class="fin-modal-foot">
                    <button type="button" class="fin-btn-ghost" onclick="document.getElementById('finCatModal').style.display='none'">Cancel</button>
                    <button type="submit" class="fin-btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
    @endcan

{{-- ── Capital & drawings: a plain register, no schedule ─────────────── --}}
@elseif($book === 'capital')
    @if($capitalOwner)
        @include('financing.partials.owner-statement')
    @else
    <div class="fin-note">
        <i class="fas fa-circle-info"></i>
        Bank and cash movements recorded here post automatically. Do not enter the same movement again in Manage Banks.
    </div>

    <div class="fin-card">
        @php
            $today = now()->toDateString();
            $yesterday = now()->subDay()->toDateString();
            $lastWeekFrom = now()->subDays(6)->toDateString();
            $all14From = now()->subDays(13)->toDateString();
            $capQuery = request()->except('page');
            $capQuickUrl = fn ($from = null, $to = null) => route('role.financing.index', ['role' => $role] + array_filter(
                array_merge($capQuery, ['book' => 'capital', 'date_from' => $from, 'date_to' => $to]),
                fn ($value) => $value !== null && $value !== ''
            ));
            $capPrintUrl = route('role.financing.capital.print', ['role' => $role] + array_filter(
                array_merge($capQuery, ['book' => 'capital']),
                fn ($value) => $value !== null && $value !== ''
            ));
        @endphp
        <form method="GET" class="cap-lf-bar">
            <input type="hidden" name="book" value="capital">
            @if($capitalPerson)
                @if($capitalOwnerId)
                    <input type="hidden" name="owner_id" value="{{ $capitalOwnerId }}">
                @else
                    <input type="hidden" name="person" value="{{ $capitalPerson }}">
                @endif
            @endif
            <div class="cap-lf-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search person, reason or reference…">
            </div>
            <select name="kind" class="cap-lf-kind" onchange="this.form.submit()">
                <option value="">Money in and out</option>
                <option value="investment" {{ request('kind') === 'investment' ? 'selected' : '' }}>Money in — investment</option>
                <option value="drawings" {{ request('kind') === 'drawings' ? 'selected' : '' }}>Money out — drawings</option>
            </select>
            <div class="cap-lf-seg">
                <a href="{{ $capQuickUrl($today, $today) }}" class="{{ request('date_from') === $today && request('date_to') === $today ? 'is-on' : '' }}">Today</a>
                <a href="{{ $capQuickUrl($yesterday, $yesterday) }}" class="{{ request('date_from') === $yesterday && request('date_to') === $yesterday ? 'is-on' : '' }}">Yesterday</a>
                <a href="{{ $capQuickUrl($lastWeekFrom, $today) }}" class="{{ request('date_from') === $lastWeekFrom && request('date_to') === $today ? 'is-on' : '' }}">Last Week</a>
                <a href="{{ $capQuickUrl($all14From, $today) }}" class="{{ request('date_from') === $all14From && request('date_to') === $today ? 'is-on' : '' }}">All 14 Days</a>
            </div>
            <div class="cap-lf-dates">
                <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="From date">
                <span>to</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="To date">
                <button type="submit" class="cap-lf-go">Apply</button>
            </div>
            <button type="button" onclick="openCapitalPrint()" class="cap-lf-action"><i class="fas fa-print"></i> Print</button>
            <a href="{{ route('role.financing.index', ['role' => $role, 'book' => 'capital']) }}" class="cap-lf-action"><i class="fas fa-rotate-left"></i> Reset</a>
        </form>

        @if($capitalPeople->count())
            <div class="fin-card-head" style="padding:1rem 0 .75rem;margin-top:.5rem;">
                <div>
                    <h2 class="text-gray-800 text-base font-bold" style="margin:0;">
                        <i class="fas fa-address-book mr-2 text-blue-500"></i>Personal Sheets
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5" style="margin:0;">
                        Boss, Nayeem, Parvez style owner-wise capital position
                    </p>
                </div>
                @if($capitalPerson)
                    <span class="fin-chip fin-chip-active">
                        Showing {{ $capitalPerson }}
                        @if($capitalOwner?->employee_id_no) · {{ $capitalOwner->employee_id_no }} @endif
                    </span>
                @else
                    <span class="text-xs text-gray-400">{{ $capitalPeople->count() }} {{ \Illuminate\Support\Str::plural('person', $capitalPeople->count()) }}</span>
                @endif
            </div>
            <div style="overflow-x:auto;margin-bottom:1rem;">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Person</th>
                            <th style="text-align:right;">Put In</th>
                            <th style="text-align:right;">Taken Out</th>
                            <th style="text-align:right;">Net Balance</th>
                            <th style="text-align:center;">Entries</th>
                            <th>Last Movement</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($capitalPeople as $personRow)
                        @php
                            $pIn = (float) $personRow->investment_total;
                            $pOut = (float) $personRow->drawings_total;
                            $pNet = $pIn - $pOut;
                            $personOwner = $personRow->party_id
                                ? $capitalOwnerUsers->firstWhere('id', (int) $personRow->party_id)
                                : $capitalOwnerUsers->firstWhere('name', $personRow->person_name);
                            $personUrl = route('role.financing.index', array_filter([
                                'role' => $role,
                                'book' => 'capital',
                                'owner_id' => $personOwner?->id,
                                'person' => $personOwner ? null : $personRow->person_name,
                                'q' => request('q') ?: null,
                            ], fn ($value) => $value !== null && $value !== ''));
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ $personUrl }}" class="fin-strong" style="color:#2563eb;">{{ $personRow->person_name }}</a>
                                @if($personOwner?->employee_id_no)
                                    <div><a href="{{ $personUrl }}" class="fin-sub" style="color:#4f46e5;font-family:monospace;">{{ $personOwner->employee_id_no }}</a></div>
                                @endif
                            </td>
                            <td style="text-align:right;" class="text-emerald-600 font-semibold">৳{{ number_format($pIn, 2) }}</td>
                            <td style="text-align:right;" class="text-red-600 font-semibold">৳{{ number_format($pOut, 2) }}</td>
                            <td style="text-align:right;" class="font-bold {{ $pNet < 0 ? 'text-red-600' : 'text-gray-800' }}">৳{{ number_format($pNet, 2) }}</td>
                            <td style="text-align:center;">{{ number_format((int) $personRow->movement_count) }}</td>
                            <td>{{ $personRow->last_movement_at ? \Carbon\Carbon::parse($personRow->last_movement_at)->format('d M Y') : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="fin-card-head" style="margin:0!important;border-top:1px solid #e2e8f0;">
            <div>
                <h2 class="text-gray-800 text-base font-bold" style="margin:0;"><i class="fas fa-clock-rotate-left mr-2 text-indigo-500"></i>Recent Capital Movements</h2>
                <p class="text-xs text-gray-400 mt-0.5" style="margin:0;">Investments and drawings across all owners</p>
            </div>
            <span class="text-xs text-gray-400">{{ number_format($capital->total()) }} entries</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Direction</th>
                        <th>Person</th>
                        <th>Reason</th>
                        <th>Method</th>
                        <th>Posting</th>
                        <th style="text-align:right;">Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($capital as $i => $c)
                    <tr>
                        <td><label style="display:flex;align-items:center;gap:7px;"><input type="checkbox" class="capital-print-row" value="{{ $c->id }}" aria-label="Select transaction"><span class="fin-idx">{{ $capital->firstItem() + $i }}</span></label></td>
                        <td>
                            <span class="dtc-date">{{ \Carbon\Carbon::parse($c->date)->format('Y-m-d') }}</span>
                            @if($c->transaction_time)
                                <div class="dtc-time">{{ \Carbon\Carbon::parse($c->transaction_time)->format('h:i A') }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="fin-chip {{ $c->kind === 'investment' ? 'fin-chip-paid' : 'fin-chip-written_off' }}">
                                {{ $c->kind === 'investment' ? 'money in' : 'money out' }}
                            </span>
                        </td>
                        @php
                            $ownerUrl = route('role.financing.index', array_filter([
                                'role' => $role,
                                'book' => 'capital',
                                'owner_id' => $c->party_id,
                                'person' => $c->party_id ? null : $c->person_name,
                            ], fn ($value) => $value !== null && $value !== ''));
                        @endphp
                        <td><a href="{{ $ownerUrl }}" class="fin-strong" style="color:#2563eb;">{{ $c->person_name }}</a>
                            @if($c->owner?->employee_id_no)
                                <div><a href="{{ $ownerUrl }}" class="fin-sub" style="color:#4f46e5;font-family:monospace;">{{ $c->owner->employee_id_no }}</a></div>
                            @endif
                            @if($c->reference)<div class="fin-sub">{{ $c->reference }}</div>@endif
                        </td>
                        <td>
                            <span class="fin-strong">{{ $c->reason }}</span>
                            @if($c->notes)<div class="fin-sub">{{ $c->notes }}</div>@endif
                        </td>
                        <td>
                            {{ $c->method === 'bank' ? 'Company Bank' : ($c->method === 'cash' ? 'Office Cash' : 'Non-cash Adjustment') }}
                            @if($c->bank)<div class="fin-sub">{{ $c->bank->name }}</div>@endif
                            @if($c->method !== 'adjustment' && $c->counterparty_method)
                                <div class="fin-sub">
                                    {{ $c->kind === 'investment' ? 'Received via' : 'Paid via' }}
                                    {{ $c->counterparty_method === 'bank' ? 'bank transfer' : $c->counterparty_method }}
                                    @if($c->counterparty_bank) · {{ $c->counterparty_bank }} @endif
                                    @if($c->counterparty_ref) · {{ $c->counterparty_ref }} @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            @php $posting = $capitalPosting->get($c->id); @endphp
                            @if($posting?->reversedBy)
                                <span class="fin-chip fin-chip-cancelled">Reversed</span>
                            @elseif($posting)
                                <span class="fin-chip fin-chip-paid">Posted</span>
                                <div class="fin-sub">{{ $posting->reference }}</div>
                            @elseif($c->method === 'adjustment')
                                <span class="fin-chip fin-chip-waived">Non-cash</span>
                            @else
                                <span class="fin-chip fin-chip-due">Not posted</span>
                            @endif
                        </td>
                        <td style="text-align:right;" class="fin-strong">
                            {{ $c->kind === 'drawings' ? '−' : '' }}৳{{ number_format((float) $c->amount, 2) }}
                        </td>
                        <td style="text-align:right;">
                            @can('delete financing')
                                @if($posting?->reversedBy)
                                    <button type="button" class="fin-btn-ghost" disabled style="height:28px;padding:0 9px;font-size:.7rem;opacity:.55;cursor:not-allowed;">
                                        <i class="fas fa-rotate-left"></i> Reversed
                                    </button>
                                @elseif($posting)
                                    <form method="POST" action="{{ route('role.financing.capital.destroy', ['role' => $role, 'capital' => $c->id]) }}"
                                          onsubmit="return confirm('Reverse this capital/drawing entry? This will create an audit reversal and keep the original row visible.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="fin-btn-ghost" style="height:28px;padding:0 9px;font-size:.7rem;color:#dc2626;">
                                            <i class="fas fa-rotate-left"></i> Reverse
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('role.financing.capital.destroy', ['role' => $role, 'capital' => $c->id]) }}"
                                          onsubmit="return confirm('Remove this non-posted entry?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="fin-btn-ghost" style="height:28px;padding:0 9px;font-size:.7rem;">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="fin-empty">Nothing recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($capital->hasPages())
            <div class="fin-pager">{{ $capital->links() }}</div>
        @endif
    </div>

    @endif
    @can('create financing')
    <div class="fin-modal" id="finCapModal">
        <div class="fin-modal-box">
            <form method="POST" id="finCapForm" action="{{ route('role.financing.capital.store', ['role' => $role]) }}">
                @csrf
                <input type="hidden" name="kind" id="finCapKind" value="investment">
                <div class="fin-modal-head">
                    <div><h3 id="finCapTitle">Add Investment</h3><p id="finCapSubtitle">The owner is putting money into the company.</p></div>
                    <button type="button" class="fin-cap-close" onclick="document.getElementById('finCapModal').style.display='none'" aria-label="Close"><i class="fas fa-times"></i></button>
                </div>
                <div class="fin-modal-body">
                    <div class="fin-grid">
                        <div class="fin-cap-section-title"><i class="fas fa-user-tie"></i> Owner &amp; transaction details</div>
                        <div class="fin-field" style="display:none;">
                            <label>Direction <span>*</span></label>
                            <select id="finCapKindDisplay" disabled>
                                <option value="investment">Money IN — investment</option>
                                <option value="drawings">Money OUT — profit / drawings</option>
                            </select>
                        </div>
                        <div class="fin-field">
                            <label>Amount (৳) <span>*</span></label>
                            <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                        </div>
                        <div class="fin-field">
                            <label>Date <span>*</span></label>
                            <input type="date" name="date" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="fin-field">
                            <label>Custom Time <span>*</span></label>
                            <input type="hidden" name="transaction_time" id="finCapTime">
                            <div class="fin-time">
                                <select id="finCapHour" required aria-label="Hour">
                                    <option value="">hh</option>
                                    @for($h = 1; $h <= 12; $h++)<option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>@endfor
                                </select>
                                <b>:</b>
                                <select id="finCapMinute" required aria-label="Minute">
                                    <option value="">mm</option>
                                    @for($m = 0; $m < 60; $m++)<option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>@endfor
                                </select>
                                <select id="finCapAmPm" required aria-label="AM or PM">
                                    <option value="AM">AM</option><option value="PM">PM</option>
                                </select>
                            </div>
                        </div>
                        <div class="fin-field">
                            <label>Company <span>*</span></label>
                            <select name="company_id" id="finCapCompany" required onchange="filterCapitalBanks();">
                                @foreach($companies as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fin-field">
                            <label>Person <span>*</span></label>
                            <select name="owner_user_id" id="finCapPerson" required>
                                <option value="">Choose person</option>
                                @foreach($capitalOwnerUsers as $ownerUser)
                                    <option value="{{ $ownerUser->id }}">{{ $ownerUser->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fin-field">
                            <label>Purpose <span>*</span></label>
                            <input type="text" name="reason" id="finCapPurpose" list="finReasons" required placeholder="Working capital">
                            <datalist id="finReasons">
                                <option value="Working capital"><option value="Cover negative balance">
                                <option value="New project funding"><option value="Asset purchase">
                                <option value="Profit withdrawal"><option value="Dividend"><option value="Personal use">
                            </datalist>
                        </div>
                        <div class="fin-cap-section-title"><i class="fas fa-money-bill-transfer"></i> Money route</div>
                        <div class="fin-field">
                            <label id="finCapCompanyRouteLabel">Received Into <span>*</span></label>
                            <select name="method" id="finCapMethod" required onchange="toggleCapitalBank()">
                                <option value="cash">Office Cash</option>
                                <option value="bank">Company Bank Account</option>
                                <option value="adjustment">Non-cash Adjustment</option>
                            </select>
                        </div>
                        <div class="fin-field" id="finCapBankField">
                            <label>Company Bank Account <span>*</span></label>
                            <select name="bank_id" id="finCapBank" required>
                                <option value="">—</option>
                                @include('partials.bank-options', ['banks' => $banks, 'selected' => null])
                            </select>
                        </div>
                        <div class="fin-field" id="finCapCounterpartyMethodField">
                            <label id="finCapCounterpartyMethodLabel">Received Via <span>*</span></label>
                            <select name="counterparty_method" id="finCapCounterpartyMethod" required onchange="toggleCapitalCounterpartyDetails()">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="fin-field" id="finCapCounterpartyBankField" style="display:none;">
                            <label id="finCapCounterpartyBankLabel">Their Bank <span>*</span></label>
                            <input type="text" name="counterparty_bank" id="finCapCounterpartyBank" maxlength="150" placeholder="e.g. DBBL">
                        </div>
                        <div class="fin-field" id="finCapCounterpartyRefField" style="display:none;">
                            <label id="finCapCounterpartyRefLabel">Account No. <span>*</span></label>
                            <input type="text" name="counterparty_ref" id="finCapCounterpartyRef" maxlength="100" placeholder="Account number">
                        </div>
                    </div>
                    <div class="fin-field">
                        <label>Reference No.</label>
                        <input type="text" name="reference" id="finCapReference" placeholder="Transaction, cheque or slip no.">
                    </div>
                    <div class="fin-field">
                        <label>Notes</label>
                        <textarea name="notes" rows="2"></textarea>
                    </div>
                    <p class="fin-hint">
                        <i class="fas fa-circle-info"></i>
                        Bank and cash movements post automatically. Adjustment changes no cash or bank ledger.
                    </p>
                </div>
                <div class="fin-modal-foot">
                    <button type="button" class="fin-btn-ghost" onclick="document.getElementById('finCapModal').style.display='none'">Cancel</button>
                    <button type="submit" class="fin-btn-primary" id="finCapSubmit">Save Investment</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    function openCapitalModal(kind) {
        const isDrawing = kind === 'drawings';
        document.getElementById('finCapForm').reset();
        document.getElementById('finCapModal').classList.toggle('is-drawing', isDrawing);
        document.getElementById('finCapKind').value = kind;
        document.getElementById('finCapTitle').textContent = isDrawing ? 'Record Drawing' : 'Add Investment';
        document.getElementById('finCapSubtitle').textContent = isDrawing ? 'The owner is taking money out for personal use or profit withdrawal.' : 'The owner is putting money into the company.';
        document.getElementById('finCapSubmit').textContent = isDrawing ? 'Save Drawing' : 'Save Investment';
        document.getElementById('finCapCompanyRouteLabel').innerHTML = (isDrawing ? 'Paid From' : 'Received Into') + ' <span>*</span>';
        document.getElementById('finCapCounterpartyMethodLabel').innerHTML = (isDrawing ? 'Paid Via' : 'Received Via') + ' <span>*</span>';
        document.getElementById('finCapCounterpartyBankLabel').innerHTML = (isDrawing ? 'Recipient Bank' : 'Their Bank') + ' <span>*</span>';
        document.getElementById('finCapPurpose').placeholder = isDrawing ? 'Personal use / dividend' : 'Working capital';
        const fixedOwnerId = @json($capitalOwner?->id);
        if (fixedOwnerId) document.getElementById('finCapPerson').value = String(fixedOwnerId);
        // Required and used to be left blank on every open — defaulted to now,
        // same as the date above it; still fully editable for a movement
        // recorded after the fact.
        const finCapNow = new Date();
        const finCapHour24 = finCapNow.getHours();
        document.getElementById('finCapHour').value = String((finCapHour24 % 12) || 12);
        document.getElementById('finCapMinute').value = String(finCapNow.getMinutes());
        document.getElementById('finCapAmPm').value = finCapHour24 < 12 ? 'AM' : 'PM';
        // Same auto-preview the bank ledger's Cash In/Out now shows — still a
        // normal editable field for a real cheque or slip number.
        const finCapPad2 = n => String(n).padStart(2, '0');
        const finCapYmdhis = finCapNow.getFullYear() + finCapPad2(finCapNow.getMonth() + 1) + finCapPad2(finCapNow.getDate())
            + finCapPad2(finCapNow.getHours()) + finCapPad2(finCapNow.getMinutes()) + finCapPad2(finCapNow.getSeconds());
        document.getElementById('finCapReference').value = (isDrawing ? 'DRW-' : 'INV-') + finCapYmdhis;
        document.getElementById('finCapModal').style.display = 'flex';
        filterCapitalBanks(); toggleCapitalBank(); toggleCapitalCounterpartyDetails();
    }
    function toggleCapitalBank() {
        const method = document.getElementById('finCapMethod'), field = document.getElementById('finCapBankField'), bank = document.getElementById('finCapBank');
        if (!method || !field || !bank) return;
        const visible = method.value === 'bank'; field.style.display = visible ? '' : 'none'; bank.required = visible;
        if (!visible) bank.value = '';
        const routeField = document.getElementById('finCapCounterpartyMethodField');
        const route = document.getElementById('finCapCounterpartyMethod');
        const isAdjustment = method.value === 'adjustment';
        routeField.style.display = isAdjustment ? 'none' : '';
        route.required = !isAdjustment;
        if (isAdjustment) route.value = '';
        toggleCapitalCounterpartyDetails();
    }
    function toggleCapitalCounterpartyDetails() {
        const method = document.getElementById('finCapCounterpartyMethod');
        const companyMethod = document.getElementById('finCapMethod');
        const bankField = document.getElementById('finCapCounterpartyBankField');
        const refField = document.getElementById('finCapCounterpartyRefField');
        const bank = document.getElementById('finCapCounterpartyBank');
        const ref = document.getElementById('finCapCounterpartyRef');
        const visible = companyMethod.value !== 'adjustment' && ['bank', 'cheque'].includes(method.value);
        bankField.style.display = visible ? '' : 'none';
        refField.style.display = visible ? '' : 'none';
        bank.required = visible; ref.required = visible;
        document.getElementById('finCapCounterpartyRefLabel').innerHTML = (method.value === 'cheque' ? 'Cheque No.' : 'Account No.') + ' <span>*</span>';
        ref.placeholder = method.value === 'cheque' ? 'Cheque number' : 'Account number';
        if (!visible) { bank.value = ''; ref.value = ''; }
    }
    document.getElementById('finCapForm').addEventListener('submit', function (event) {
        const hourValue = document.getElementById('finCapHour').value;
        const minuteValue = document.getElementById('finCapMinute').value;
        if (!hourValue || minuteValue === '') {
            event.preventDefault();
            return;
        }
        let hour = Number(hourValue) % 12;
        if (document.getElementById('finCapAmPm').value === 'PM') hour += 12;
        document.getElementById('finCapTime').value = String(hour).padStart(2, '0') + ':' + String(minuteValue).padStart(2, '0');
    });
    function filterCapitalBanks() {
        const company = document.getElementById('finCapCompany'), bank = document.getElementById('finCapBank');
        if (!company || !bank) return;

        let visibleCount = 0;

        [...bank.options].forEach(option => {
            if (!option.value) return;

            const hidden = option.dataset.company && option.dataset.company !== company.value;
            option.hidden = hidden;
            option.disabled = hidden;

            if (!hidden) visibleCount++;
        });

        bank.querySelectorAll('optgroup').forEach(group => {
            const hasVisibleOption = [...group.querySelectorAll('option')].some(option => !option.hidden);
            group.hidden = !hasVisibleOption;
            group.disabled = !hasVisibleOption;
        });

        if (bank.selectedOptions[0]?.hidden) bank.value = '';

        const placeholder = bank.querySelector('option[value=""]');
        if (placeholder) {
            placeholder.textContent = visibleCount > 0
                ? '— Select Bank —'
                : 'No bank account for selected company';
        }
    }
    document.addEventListener('DOMContentLoaded', () => { filterCapitalBanks(); toggleCapitalBank(); });

    function openCapitalPrint() {
        const selected = [...document.querySelectorAll('.capital-print-row:checked')].map(el => el.value);
        const today = new Date().toISOString().slice(0, 10);
        const yesterdayDate = new Date(); yesterdayDate.setDate(yesterdayDate.getDate() - 1);
        const yesterday = yesterdayDate.toISOString().slice(0, 10);
        const currentFrom = @json(request('date_from')) || '';
        const currentTo = @json(request('date_to')) || '';
        const row = (value, title, detail, extra = '') => `<label class="cap-print-choice"><span><input type="radio" name="cap_print_scope" value="${value}"> <b>${title}</b><small>${detail}</small></span>${extra}</label>`;
        Swal.fire({
            title: 'Print statement',
            width: 460,
            html: `<div class="cap-print-options">
                ${row('today','Today',today)}
                ${row('yesterday','Yesterday',yesterday)}
                ${row('day','A specific day','Choose one date','<input type="date" id="capPrintDay" value="'+today+'">')}
                ${row('range','A date range','Choose start and end','<span class="cap-print-range"><input type="date" id="capPrintFrom" value="'+(currentFrom||today)+'"><em>to</em><input type="date" id="capPrintTo" value="'+(currentTo||today)+'"></span>')}
                ${row('selected','Selected transactions',selected.length ? selected.length+' transaction(s) selected' : 'Tick the rows you want in the table')}
                ${row('shown','What the table shows now',(currentFrom||'All time')+' — '+(currentTo||'Today'))}
            </div>`,
            showCancelButton: true, confirmButtonText: '<i class="fas fa-print"></i> Print', cancelButtonText: 'Cancel', confirmButtonColor: '#4f46e5',
            didOpen: () => { const radio = document.querySelector(`input[name="cap_print_scope"][value="${selected.length ? 'selected' : 'shown'}"]`); if (radio) radio.checked = true; },
            preConfirm: () => {
                const scope = document.querySelector('input[name="cap_print_scope"]:checked')?.value;
                if (!scope) return Swal.showValidationMessage('Choose what you want to print.');
                if (scope === 'selected' && !selected.length) return Swal.showValidationMessage('Select at least one transaction from the table.');
                return {scope, day:document.getElementById('capPrintDay')?.value, from:document.getElementById('capPrintFrom')?.value, to:document.getElementById('capPrintTo')?.value};
            }
        }).then(result => {
            if (!result.isConfirmed) return;
            const url = new URL(@json($capitalPrintBaseUrl), window.location.origin);
            const scope = result.value.scope;
            if (scope === 'today') { url.searchParams.set('date_from', today); url.searchParams.set('date_to', today); }
            if (scope === 'yesterday') { url.searchParams.set('date_from', yesterday); url.searchParams.set('date_to', yesterday); }
            if (scope === 'day') { const day = result.value.day || today; url.searchParams.set('date_from', day); url.searchParams.set('date_to', day); }
            if (scope === 'range') { url.searchParams.set('date_from', result.value.from || today); url.searchParams.set('date_to', result.value.to || today); }
            if (scope === 'selected') {
                ['date_from','date_to','q','kind'].forEach(key => url.searchParams.delete(key));
                url.searchParams.set('ids', selected.join(','));
            }
            window.open(url.toString(), '_blank');
        });
    }
    </script>
    @endcan

{{-- ── The employee book: a read-only mirror ─────────────────────────── --}}
@elseif($book === 'employee')
    <div class="fin-note">
        <i class="fas fa-circle-info"></i>
        These live in the payroll loan desk and recover automatically as payslip EMI.
        This desk shows them so the whole portfolio reads in one place; nothing here changes them.
    </div>

    {{-- ── 1 · The fold, by person ──────────────────────────────────────
         Only those still carrying a balance. Anyone settled belongs in the
         register below, which keeps everyone. Totals read the WHOLE book, not
         one page, so a per-person figure is never a partial sum. --}}
    <div class="fin-card" style="margin-bottom:1.5rem;">
        <div class="fin-card-head">
            <div>
                <h2 class="text-gray-800 text-base font-bold" style="margin:0;">
                    <i class="fas fa-users mr-2 text-blue-500"></i>Employees Carrying a Loan
                </h2>
                <p class="text-xs text-gray-400 mt-0.5" style="margin:0;">
                    taken · paid · still due, per person — settled borrowers are in the register below
                </p>
            </div>
            <span class="text-xs text-gray-400">
                {{ $staffFold->count() }} {{ \Illuminate\Support\Str::plural('person', $staffFold->count()) }}
            </span>
        </div>
        <div style="overflow-x:auto;">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th style="text-align:center;width:4%;">#</th>
                        <th><i class="fas fa-user mr-1 text-blue-400"></i>Employee</th>
                        @if($showCompany)
                            <th><i class="fas fa-building mr-1 text-cyan-500"></i>Company</th>
                        @endif
                        <th style="text-align:right;"><i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Loan Taken</th>
                        <th style="text-align:right;"><i class="fas fa-rotate-left mr-1 text-emerald-400"></i>Paid So Far</th>
                        <th style="text-align:right;"><i class="fas fa-hourglass-half mr-1 text-red-400"></i>Still Due</th>
                        <th><i class="fas fa-arrow-right-arrow-left mr-1 text-indigo-400"></i>Repaid Via</th>
                        <th style="text-align:right;"><i class="fas fa-calendar-check mr-1 text-teal-400"></i>Monthly EMI</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($staffFold as $i => $f)
                    @php $fPct = $f['taken'] > 0 ? round($f['paid'] / $f['taken'] * 100, 1) : 0; @endphp
                    <tr>
                        <td style="text-align:center;"><span class="fin-idx">{{ $i + 1 }}</span></td>
                        <td>
                            <div class="fin-who">
                                <span class="fin-ava">{{ strtoupper(mb_substr($f['user']->name ?? '?', 0, 2)) }}</span>
                                <div style="min-width:0;">
                                    <span class="fin-strong">{{ $f['user']->name ?? '—' }}</span>
                                    <div class="fin-sub">
                                        {{ $f['loans'] }} {{ \Illuminate\Support\Str::plural('loan', $f['loans']) }}@if($f['latest']) · latest {{ \Illuminate\Support\Carbon::parse($f['latest'])->format('d M Y') }}@endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        @if($showCompany)
                            <td>@include('payroll.partials.company-chip', ['company' => $f['user']?->company])</td>
                        @endif
                        <td style="text-align:right;" class="fin-num">৳ {{ $taka($f['taken']) }}</td>
                        <td style="text-align:right;" class="fin-num">
                            <span class="font-semibold text-emerald-600">৳ {{ $taka($f['paid']) }}</span>
                            <div class="fin-bar {{ $fPct >= 100 ? 'is-done' : '' }}"><i style="width:{{ max(2, min(100, $fPct)) }}%"></i></div>
                            <div class="fin-sub">{{ $fPct }}% of what was lent</div>
                        </td>
                        <td style="text-align:right;" class="fin-num"><span class="font-bold text-red-600">৳ {{ $taka($f['due']) }}</span></td>
                        {{-- The salary-vs-cash split: an EMI withheld from a payslip
                             is not the same event as money handed over, and this is
                             the column that tells them apart. --}}
                        <td class="text-xs whitespace-nowrap">
                            <span class="text-gray-400">Salary</span>
                            <span class="text-gray-700 font-semibold">৳ {{ $taka($f['via_salary']) }}</span>
                            <span class="text-gray-300">·</span>
                            <span class="text-gray-400">Cash</span>
                            <span class="text-gray-700 font-semibold">৳ {{ $taka($f['via_cash']) }}</span>
                        </td>
                        <td style="text-align:right;" class="fin-num">{{ $f['emi'] > 0 ? '৳ ' . $taka($f['emi']) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 7 + ($showCompany ? 1 : 0) }}" class="fin-empty">
                        <i class="fas fa-user-check"></i>Nobody is carrying a loan.
                    </td></tr>
                @endforelse
                </tbody>
                @if($staffFold->count())
                    <tfoot class="border-t-2 border-gray-200">
                        <tr class="bg-gray-100 text-sm border-t border-gray-300">
                            <td colspan="{{ 2 + ($showCompany ? 1 : 0) }}" class="px-4 py-3 text-left font-extrabold text-gray-800">
                                <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $staffFold->count() }}
                                {{ \Illuminate\Support\Str::plural('Person', $staffFold->count()) }}
                            </td>
                            <td class="px-4 py-3 text-right font-extrabold text-gray-800 fin-num">৳ {{ $taka($staffFold->sum('taken')) }}</td>
                            <td class="px-4 py-3 text-right font-extrabold text-emerald-700 fin-num">৳ {{ $taka($staffFold->sum('paid')) }}</td>
                            <td class="px-4 py-3 text-right font-extrabold text-red-700 fin-num">৳ {{ $taka($staffFold->sum('due')) }}</td>
                            <td class="px-4 py-3 text-left text-gray-500 text-xs whitespace-nowrap">
                                Salary <span class="text-gray-700 font-semibold">৳ {{ $taka($staffFold->sum('via_salary')) }}</span>
                                · Cash <span class="text-gray-700 font-semibold">৳ {{ $taka($staffFold->sum('via_cash')) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-extrabold text-gray-800 fin-num">৳ {{ $taka($staffFold->sum('emi')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ── 2 · The register — every loan, settled ones included ────────── --}}
    <div class="fin-card">
        <div class="fin-card-head">
            <div>
                <h2 class="text-gray-800 text-base font-bold" style="margin:0;">
                    <i class="fas fa-users mr-2 text-blue-500"></i>Employees with Loans
                </h2>
                <p class="text-xs text-gray-400 mt-0.5" style="margin:0;">
                    advanced · recovered · still due, per person — maintained in Payroll
                </p>
            </div>
            <span class="text-xs text-gray-400">
                {{ $staffLoans->total() }} {{ \Illuminate\Support\Str::plural('record', $staffLoans->total()) }}
            </span>
        </div>

        <div style="overflow-x:auto;">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th style="text-align:center;width:4%;">#</th>
                        <th><i class="fas fa-user mr-1 text-blue-400"></i>Employee</th>
                        @if($showCompany)
                            <th><i class="fas fa-building mr-1 text-cyan-500"></i>Company</th>
                        @endif
                        <th><i class="fas fa-calendar-day mr-1 text-cyan-400"></i>Taken On</th>
                        <th style="text-align:right;"><i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Loan Taken</th>
                        <th style="text-align:right;"><i class="fas fa-rotate-left mr-1 text-emerald-400"></i>Paid Till Now</th>
                        <th style="text-align:right;"><i class="fas fa-hourglass-half mr-1 text-red-400"></i>Still Due</th>
                        <th><i class="fas fa-arrow-right-arrow-left mr-1 text-indigo-400"></i>Repaid Via</th>
                        <th><i class="fas fa-circle-info mr-1 text-gray-400"></i>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($staffLoans as $i => $sl)
                    @php
                        // Derived the same way the loan desk derives everything:
                        // from the figures on the row, so this mirror never
                        // contradicts payroll.
                        $slAmount    = (float) ($sl->amount ?? 0);
                        $slRemaining = (float) ($sl->remaining_amount ?? 0);
                        $slRecovered = max(0, round($slAmount - $slRemaining, 2));
                        $slPct       = $slAmount > 0 ? round($slRecovered / $slAmount * 100, 1) : 0;
                        $slEmi       = (float) ($sl->monthly_deduction ?? 0);
                        // Months left at the current deduction — payroll stores a
                        // flat EMI with no schedule behind it, so this is the only
                        // honest projection available.
                        $slMonths    = $slEmi > 0 ? (int) ceil($slRemaining / $slEmi) : null;
                        $slRunsTo    = $slMonths !== null && $slRemaining > 0
                            ? now()->addMonthsNoOverflow($slMonths)->format('Y-m')
                            : null;
                    @endphp
                    <tr>
                        <td style="text-align:center;"><span class="fin-idx">{{ $staffLoans->firstItem() + $i }}</span></td>
                        <td>
                            <div class="fin-who">
                                <span class="fin-ava">{{ strtoupper(mb_substr($sl->user->name ?? '?', 0, 2)) }}</span>
                                <div style="min-width:0;">
                                    <span class="fin-strong">{{ $sl->user->name ?? '—' }}</span>
                                    <div class="fin-sub">{{ $sl->transactions->count() }} movement{{ $sl->transactions->count() === 1 ? '' : 's' }}</div>
                                </div>
                            </div>
                        </td>
                        @if($showCompany)
                            <td>@include('payroll.partials.company-chip', ['company' => $sl->user?->company])</td>
                        @endif
                        <td>@include('partials.date-time-cell', ['date' => $sl->start_date ?? $sl->created_at, 'recordedAt' => $sl->created_at])</td>
                        <td style="text-align:right;" class="fin-num">৳ {{ $taka($slAmount) }}</td>
                        <td style="text-align:right;" class="fin-num">
                            {{-- Colour carries the meaning here the way payroll does it:
                                 recovered green, still owed red. --}}
                            <span class="font-semibold text-emerald-600">৳ {{ $taka($slRecovered) }}</span>
                            <div class="fin-bar {{ $slPct >= 100 ? 'is-done' : '' }}">
                                <i style="width:{{ max(2, min(100, $slPct)) }}%"></i>
                            </div>
                            <div class="fin-sub">{{ $slPct }}% of what was lent</div>
                        </td>
                        <td style="text-align:right;" class="fin-num">
                            <span class="font-bold {{ $slRemaining > 0 ? 'text-red-600' : 'text-emerald-600' }}">৳ {{ $taka($slRemaining) }}</span>
                        </td>
                        {{-- Per-loan salary-vs-cash split, from the model's own
                             repaidByMethod() so it agrees with the fold above. --}}
                        @php
                            $slVia = method_exists($sl, 'repaidByMethod')
                                ? $sl->repaidByMethod()
                                : ['salary' => 0, 'cash' => 0];
                            $slViaCash = (float) $slVia['cash'] + (float) ($sl->opening_paid_amount ?? 0);
                        @endphp
                        <td class="text-xs whitespace-nowrap">
                            <span class="text-gray-400">Salary</span>
                            <span class="text-gray-700 font-semibold">৳ {{ $taka($slVia['salary']) }}</span>
                            <span class="text-gray-300">·</span>
                            <span class="text-gray-400">Cash</span>
                            <span class="text-gray-700 font-semibold">৳ {{ $taka($slViaCash) }}</span>
                            @if($slEmi > 0 && $slRemaining > 0)
                                <div class="fin-sub">৳ {{ $taka($slEmi) }}/mo · {{ $slMonths }} left</div>
                            @endif
                        </td>
                        <td><span class="fin-chip fin-chip-{{ $slRemaining <= 0 ? 'paid' : 'active' }}">{{ $slRemaining <= 0 ? 'cleared' : ucfirst((string) ($sl->status ?? 'active')) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 8 + ($showCompany ? 1 : 0) }}" class="fin-empty">
                        <i class="fas fa-user-shield"></i>
                        No employee loans. These are created in Payroll &rsaquo; Loan Management.
                    </td></tr>
                @endforelse
                </tbody>

                @if($staffLoans->count())
                    {{-- Totals across the rows on this page, the way the payroll
                         book closes each of its tables. --}}
                    <tfoot class="border-t-2 border-gray-200">
                        <tr class="bg-gray-100 text-sm border-t border-gray-300">
                            <td colspan="{{ 3 + ($showCompany ? 1 : 0) }}" class="px-4 py-3 text-left font-extrabold text-gray-800">
                                <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $staffLoans->count() }}
                                {{ \Illuminate\Support\Str::plural('Loan', $staffLoans->count()) }}
                            </td>
                            <td class="px-4 py-3 text-right font-extrabold text-gray-800 fin-num">৳ {{ $taka($slAdvanced) }}</td>
                            <td class="px-4 py-3 text-right font-extrabold text-emerald-700 fin-num">৳ {{ $taka(max(0, $slAdvanced - $slRemaining)) }}</td>
                            <td class="px-4 py-3 text-right font-extrabold text-red-700 fin-num">৳ {{ $taka($slRemaining) }}</td>
                            <td class="px-4 py-3 text-left text-gray-500 text-xs whitespace-nowrap">
                                ৳ {{ $taka($slEmiTotal) }}/mo scheduled
                            </td>
                            <td class="px-4 py-3 text-left text-gray-500 text-xs whitespace-nowrap">
                                <span class="text-yellow-700 font-semibold">{{ $slCarrying }} running</span>
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        @if($staffLoans->hasPages())
            <div class="fin-pager">{{ $staffLoans->links() }}</div>
        @endif
    </div>

    {{-- ── 3 · The movement trail ──────────────────────────────────────
         What actually changed hands, newest first. `method` is what tells a
         payslip-withheld EMI apart from a cash or bank repayment, which is the
         distinction the register alone cannot show. --}}
    <div class="fin-card" style="margin-top:1.5rem;">
        <div class="fin-card-head">
            <div>
                <h2 class="text-gray-800 text-base font-bold" style="margin:0;">
                    <i class="fas fa-receipt mr-2 text-blue-500"></i>Loan Transactions
                </h2>
                <p class="text-xs text-gray-400 mt-0.5" style="margin:0;">
                    every movement on the book — money lent out and money coming back
                </p>
            </div>
            <span class="text-xs text-gray-400">
                {{ $staffTxns->total() }} {{ \Illuminate\Support\Str::plural('movement', $staffTxns->total()) }}
            </span>
        </div>
        <div style="overflow-x:auto;">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th style="text-align:center;width:4%;">#</th>
                        <th><i class="fas fa-calendar-day mr-1 text-cyan-400"></i>Date</th>
                        <th><i class="fas fa-user mr-1 text-blue-400"></i>Employee</th>
                        @if($showCompany)
                            <th><i class="fas fa-building mr-1 text-cyan-500"></i>Company</th>
                        @endif
                        <th style="text-align:center;"><i class="fas fa-tag mr-1 text-yellow-400"></i>Type</th>
                        <th><i class="fas fa-note-sticky mr-1 text-gray-400"></i>Note</th>
                        <th><i class="fas fa-arrow-right-arrow-left mr-1 text-indigo-400"></i>Method</th>
                        <th style="text-align:right;"><i class="fas fa-building-columns mr-1 text-purple-400"></i>The Loan</th>
                        <th style="text-align:right;"><i class="fas fa-rotate-left mr-1 text-emerald-400"></i>Paid Till Then</th>
                        <th style="text-align:right;"><i class="fas fa-hourglass-half mr-1 text-red-400"></i>Due After</th>
                        <th style="text-align:right;"><i class="fas fa-money-bill-wave mr-1 text-green-400"></i>Amount</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($staffTxns as $i => $t)
                    @php
                        $isOut = $t->type !== 'repay';
                        // Where that loan stood at THIS movement — payroll's own
                        // running balance, keyed by transaction id.
                        $at = $staffBalances[$t->id] ?? null;
                    @endphp
                    <tr>
                        <td style="text-align:center;"><span class="fin-idx">{{ $staffTxns->firstItem() + $i }}</span></td>
                        <td>@include('partials.date-time-cell', ['date' => $t->date, 'recordedAt' => $t->created_at])</td>
                        <td>
                            <div class="fin-who">
                                <span class="fin-ava">{{ strtoupper(mb_substr($t->user->name ?? '?', 0, 2)) }}</span>
                                <span class="fin-strong">{{ $t->user->name ?? '—' }}</span>
                            </div>
                        </td>
                        @if($showCompany)
                            <td>@include('payroll.partials.company-chip', ['company' => $t->user?->company])</td>
                        @endif
                        <td style="text-align:center;">
                            @if($isOut)
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">
                                    <i class="fas fa-arrow-up text-xs"></i> Disburse
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                                    <i class="fas fa-arrow-down text-xs"></i> Repay
                                </span>
                            @endif
                        </td>
                        <td class="text-sm text-gray-600">{{ $t->note ?: '—' }}</td>
                        <td>
                            @if($t->method === 'salary')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                    Salary deduction
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                                    {{ $t->bank?->name ?: 'Cash / bank' }}
                                </span>
                            @endif
                        </td>
                        <td style="text-align:right;" class="fin-num">
                            @if($at)
                                <span class="text-sm font-medium text-gray-700">৳ {{ $taka($at['loan']->amount) }}</span>
                                <div class="fin-sub">taken {{ $at['loan']->start_date ? \Illuminate\Support\Carbon::parse($at['loan']->start_date)->format('d M Y') : '—' }}</div>
                            @else
                                <span class="text-sm text-gray-300">—</span>
                            @endif
                        </td>
                        <td style="text-align:right;" class="fin-num">
                            @if($at)
                                <span class="text-sm font-semibold text-emerald-600">৳ {{ $taka($at['paid']) }}</span>
                            @else
                                <span class="text-sm text-gray-300">—</span>
                            @endif
                        </td>
                        <td style="text-align:right;" class="fin-num">
                            @if($at)
                                <span class="text-sm font-semibold {{ $at['due'] > 0 ? 'text-red-600' : 'text-green-600' }}">৳ {{ $taka($at['due']) }}</span>
                            @else
                                <span class="text-sm text-gray-300">—</span>
                            @endif
                        </td>
                        <td style="text-align:right;" class="fin-num">
                            <span class="text-sm font-bold {{ $isOut ? 'text-amber-700' : 'text-emerald-700' }}">৳ {{ $taka($t->amount) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 10 + ($showCompany ? 1 : 0) }}" class="fin-empty">
                        <i class="fas fa-receipt"></i>No movements recorded.
                    </td></tr>
                @endforelse
                </tbody>

                @if($staffTxns->count())
                    @php
                        $tLent   = $staffTxns->where('type', 'disburse')->sum('amount');
                        $tRepaid = $staffTxns->where('type', 'repay')->sum('amount');
                    @endphp
                    {{-- Net, not a column sum: adding a disbursement to a repayment
                         would total two opposite movements into a meaningless
                         figure, and the balance columns are states, not amounts. --}}
                    <tfoot class="border-t-2 border-gray-200">
                        <tr class="bg-gray-100 text-sm border-t border-gray-300">
                            <td colspan="{{ 6 + ($showCompany ? 1 : 0) }}" class="px-4 py-3 text-left font-extrabold text-gray-800">
                                <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $staffTxns->count() }}
                                {{ \Illuminate\Support\Str::plural('Transaction', $staffTxns->count()) }}
                            </td>
                            <td colspan="3" class="px-4 py-3 text-right text-gray-400 text-xs italic">balances, not sums</td>
                            <td class="px-4 py-3 text-right font-extrabold text-gray-800 fin-num">
                                ৳ {{ $taka($tLent - $tRepaid) }} <span class="text-xs font-normal text-gray-500">net</span>
                                <div class="fin-sub">৳ {{ $taka($tLent) }} lent · ৳ {{ $taka($tRepaid) }} repaid</div>
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        @if($staffTxns->hasPages())
            <div class="fin-pager">{{ $staffTxns->links() }}</div>
        @endif
    </div>

{{-- ── The liability book: what one company owes another ─────────────── --}}
@elseif($book === 'liability')
    @include('financing.partials.liability-book')

{{-- ── The lent / borrowed books ─────────────────────────────────────── --}}
@else
    {{-- Where this book stands, before any of the tables. Computed over the
         whole book by the controller, not over a page. --}}
    @include('financing.partials.position-strip', ['bookTotals' => $bookTotals, 'book' => $book])

    @if($book === 'lent' && $activeLoans->count())
        @php
            // Credit-risk profile of what is owed to us, on the Bangladesh Bank
            // scale (BRPD 15 of 2024). Shown only on the lent book: on a
            // borrowing, arrears are our own lateness and it is the lender who
            // classifies us, so a provision figure there would be meaningless.
            //
            // Read from the ACTIVE table, not the register. A loan that has been
            // collected in full carries no risk of not being collected, and
            // including settled rows would dilute the NPL ratio towards zero by
            // counting successes as if they were still exposures.
            $bands = ['standard' => 'Standard', 'sma' => 'SMA', 'substandard' => 'Sub-standard', 'doubtful' => 'Doubtful', 'bad' => 'Bad / Loss'];
            $byBand = [];
            foreach ($bands as $bk => $bl) {
                $rows = $activeLoans->filter(fn ($l) => $l->classification === $bk);
                $byBand[$bk] = [
                    'label'     => $bl,
                    'count'     => $rows->count(),
                    'exposure'  => $rows->sum(fn ($l) => $l->outstanding),
                    'provision' => $rows->sum(fn ($l) => $l->provision_amount),
                    'rate'      => $rows->first()?->provision_rate,
                ];
            }
            $totalExposure  = collect($byBand)->sum('exposure');
            $totalProvision = collect($byBand)->sum('provision');
            $nplExposure    = $byBand['substandard']['exposure'] + $byBand['doubtful']['exposure'] + $byBand['bad']['exposure'];
            $nplRatio       = $totalExposure > 0 ? round($nplExposure / $totalExposure * 100, 1) : 0;
        @endphp

        <div class="fin-card" style="margin-bottom:1.15rem;">
            <div class="fin-card-head">
                <strong>Credit risk — what is owed to us</strong>
                <span class="fin-sub" style="margin:0;">
                    NPL ratio {{ $nplRatio }}% · suggested provision ৳{{ number_format($totalProvision, 2) }}
                </span>
            </div>
            <div style="overflow-x:auto;">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Band</th>
                            <th>Overdue</th>
                            <th style="text-align:right;">Loans</th>
                            <th style="text-align:right;">Exposure</th>
                            <th style="text-align:right;">Rate</th>
                            <th style="text-align:right;">Provision</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach([
                        ['standard','nothing overdue','1%'],
                        ['sma','up to 3 months','5%'],
                        ['substandard','3 to 6 months','20%'],
                        ['doubtful','6 to 12 months','50%'],
                        ['bad','over 12 months','100%'],
                    ] as [$bk, $window, $rate])
                        <tr style="{{ $byBand[$bk]['count'] === 0 ? 'opacity:.45;' : '' }}">
                            <td><span class="fin-chip {{ in_array($bk, ['substandard','doubtful','bad'], true) ? 'fin-chip-written_off' : ($bk === 'sma' ? 'fin-chip-due' : 'fin-chip-paid') }}">{{ $byBand[$bk]['label'] }}</span></td>
                            <td class="fin-dim">{{ $window }}</td>
                            <td style="text-align:right;" class="fin-num">{{ $byBand[$bk]['count'] }}</td>
                            <td style="text-align:right;" class="fin-num">৳{{ number_format($byBand[$bk]['exposure'], 2) }}</td>
                            <td style="text-align:right;" class="fin-dim fin-num">{{ $rate }}</td>
                            <td style="text-align:right;" class="fin-strong fin-num">৳{{ number_format($byBand[$bk]['provision'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:.9rem 1.15rem;border-top:1px solid var(--fin-line);">
                <p style="margin:0;font-size:.73rem;color:var(--fin-faint);line-height:1.6;">
                    Bands and rates follow Bangladesh Bank BRPD Circular 15 (Nov 2024, effective 1 April 2025),
                    which moved non-performing to three months overdue from six. Those rules bind a bank's own
                    book — Epal is not a bank, so this is a management view of credit risk on a recognised
                    yardstick, not a regulatory return. Provision is calculated on what is still outstanding,
                    since money already collected is not at risk — which is also why settled loans are left
                    out entirely. Figures cover the active loans on this page.
                </p>
            </div>
        </div>
    @endif

    <div class="fin-card">
        <form method="GET" class="fin-filter">
            <input type="hidden" name="book" value="{{ $book }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search lender, kind, reference or purpose…">
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach(['active' => 'Active', 'closed' => 'Closed', 'written_off' => 'Written off', 'cancelled' => 'Cancelled'] as $sv => $sl)
                    <option value="{{ $sv }}" {{ request('status') === $sv ? 'selected' : '' }}>{{ $sl }}</option>
                @endforeach
            </select>
            @if($book === 'borrowed')
                <select name="for" onchange="this.form.submit()">
                    <option value="">Company and personal</option>
                    <option value="company" {{ request('for') === 'company' ? 'selected' : '' }}>Company only</option>
                    <option value="personal" {{ request('for') === 'personal' ? 'selected' : '' }}>Personal only</option>
                </select>
            @endif
            {{-- Two genuinely different arrangements share this table. A fixed
                 loan has a schedule and a rate; a running account has neither,
                 so being able to look at one without the other is the difference
                 between reading the book and squinting at it. --}}
            <select name="shape" onchange="this.form.submit()">
                <option value="">Fixed loans and running accounts</option>
                <option value="term" {{ request('shape') === 'term' ? 'selected' : '' }}>Fixed loans only</option>
                <option value="running" {{ request('shape') === 'running' ? 'selected' : '' }}>Running accounts only</option>
            </select>
            {{-- Dropdowns apply on change; this is only for the text box, which
                 must not fire a request per keystroke. Enter works too. --}}
            <button type="submit" class="fin-btn"><i class="fas fa-magnifying-glass"></i> Search</button>
            <a href="{{ route('role.financing.index', ['role' => $role, 'book' => $book]) }}" class="fin-btn-ghost"><i class="fas fa-rotate-left"></i> Reset</a>
        </form>
    </div>

    {{-- ── 1 · Active ──────────────────────────────────────────────────────
         What still needs attention, and the EMI view in the same table: what is
         left, the next date due, and the button to act on it. A settled loan
         drops out of here the moment its last instalment lands. --}}
    @include('financing.partials.loan-table', [
        'rows'      => $activeLoans,
        'book'      => $book,
        'role'      => $role,
        'anchor'    => 'active',
        'title'     => $book === 'borrowed' ? 'Active loans — what we are still paying' : 'Active loans — what is still owed to us',
        'subtitle'  => 'balances, instalments and the next date due',
        'emptyText' => $bookTotals['count'] > 0
            ? ($book === 'borrowed'
                ? 'Nothing outstanding. Every borrowing on this book is settled — they are in the register below.'
                : 'Nothing outstanding. Everything lent has come back — see the register below.')
            : 'Nothing here yet — add your first ' . ($book === 'borrowed' ? 'borrowing' : 'loan given') . ' and the instalment schedule builds itself.',
    ])

    {{-- ── 2 · Register ────────────────────────────────────────────────────
         The finished ones. A loan leaves the table above the moment it settles
         and lands here, so the two never carry the same row and neither has to
         be read past — but the question "what did we borrow in 2024" still has
         somewhere to land. --}}
    @include('financing.partials.loan-table', [
        'rows'      => $loans,
        'book'      => $book,
        'role'      => $role,
        'anchor'    => 'register',
        'title'     => 'Loan register — settled and closed',
        'subtitle'  => 'arrangements that are finished with, kept as the record',
        'emptyText' => 'Nothing settled yet. Loans move here once their last instalment lands, and written-off or cancelled ones collect here too.',
    ])

    {{-- ── 3 · Movements ─────────────────────────────────────────────────── --}}
    @include('financing.partials.loan-txn-table', [
        'rows' => $loanTxns,
        'book' => $book,
        'role' => $role,
    ])

    {{-- ── Add modal ─────────────────────────────────────────────────── --}}
    @can('create financing')
    <div class="fin-modal" id="finAddModal">
        <div class="fin-modal-box">
            <form method="POST" action="{{ route('role.financing.store', ['role' => $role]) }}">
                @csrf
                <input type="hidden" name="direction" value="{{ $book }}">

                <div class="fin-modal-head">
                    <h3>Add {{ $book === 'borrowed' ? 'a borrowing' : 'a loan given' }}</h3>
                    <p id="finLoanSubtitle">The instalment schedule is generated from these terms when you save.</p>
                </div>

                <div class="fin-modal-body">
                    @if($book === 'borrowed')
                        {{-- Whose debt it is AND what shape it takes, asked as one
                             grid.

                             The two are genuinely independent — the company can hold
                             a revolving line, and the boss can borrow on fixed terms
                             — but they are always answered together, and stacking
                             them as two questions made the form read as more
                             decisions than it actually contains.

                             ONE radio group of four, not two groups of two: that way
                             the browser itself guarantees exactly one combination is
                             live and enforces `required` without any script. The
                             controller splits the value back into taken_for and
                             loan_shape before validating, so nothing downstream
                             knows or cares that they arrived joined. --}}
                        <div class="fin-field">
                            <label>What kind of borrowing is this <span>*</span></label>
                            <div class="fin-quad">
                                @foreach([
                                    ['who' => 'company', 'mod' => 'co', 'label' => 'The company', 'icon' => 'fa-building',
                                     'tag' => 'on the books',
                                     // Kept to one line on purpose. The full reasoning is in the
                                     // strip below, which changes with the choice — repeating it
                                     // here only made both cards taller than the answer needs.
                                     'desc' => "Sits on the balance sheet. Interest is a real cost."],
                                    ['who' => 'personal', 'mod' => 'pers', 'label' => 'Personal', 'icon' => 'fa-user',
                                     'tag' => 'off the books',
                                     'desc' => "The boss's own. No interest claimable."],
                                ] as $opt)
                                    <div class="fin-quad-card fin-quad-card--{{ $opt['mod'] }}">
                                        <div class="fin-quad-head">
                                            <i class="fas {{ $opt['icon'] }}"></i>{{ $opt['label'] }}
                                            <span class="fin-quad-tag">{{ $opt['tag'] }}</span>
                                        </div>
                                        <p class="fin-quad-desc">{{ $opt['desc'] }}</p>
                                        <div class="fin-quad-opts">
                                            @foreach([
                                                ['term', 'fa-calendar-check', 'Fixed', 'one sum, set term'],
                                                ['running', 'fa-arrows-rotate', 'Running', 'taken bit by bit'],
                                            ] as [$shape, $shapeIcon, $shapeLabel, $shapeDesc])
                                                <label class="fin-quad-opt">
                                                    <input type="radio" name="arrangement" value="{{ $opt['who'] }}:{{ $shape }}" required
                                                           @checked($opt['who'] === 'company' && $shape === 'term')
                                                           onchange="finArrangement('{{ $opt['who'] }}', '{{ $shape }}')">
                                                    <span>
                                                        <span><i class="fas {{ $shapeIcon }}"></i>{{ $shapeLabel }}</span>
                                                        <small>{{ $shapeDesc }}</small>
                                                    </span>
                                                    {{-- Only the live choice is ticked. Two cards each
                                                         showing a shaded half would read as two answers. --}}
                                                    <i class="fas fa-circle-check fin-quad-tick"></i>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- What saving will actually DO. The consequence is the
                                 part people get wrong, so it is stated before the
                                 save rather than discovered a month later in a
                                 trial balance that will not agree. --}}
                            <p class="fin-quad-out" id="finQuadOut"></p>
                        </div>
                    @else
                        {{-- The lent book has no company/personal split — money we
                             gave out is the company's either way — so only the shape
                             is asked, in the plain two-tile form. --}}
                        <div class="fin-field">
                            <label>What kind of arrangement <span>*</span></label>
                            <div class="fin-seg">
                                <label class="fin-seg-opt">
                                    <input type="radio" name="loan_shape" value="term" checked onchange="finShape('term')">
                                    <span>
                                        <span><i class="fas fa-calendar-check"></i>Fixed loan</span>
                                        <small>An agreed sum over an agreed term. Builds an instalment schedule.</small>
                                    </span>
                                </label>
                                <label class="fin-seg-opt">
                                    <input type="radio" name="loan_shape" value="running" onchange="finShape('running')">
                                    <span>
                                        <span><i class="fas fa-arrows-rotate"></i>Running account</span>
                                        <small>Money taken bit by bit, repaid whenever. No schedule, no fixed amount.</small>
                                    </span>
                                </label>
                            </div>
                            <small class="fin-hint" id="finShapeHint" style="display:none;">
                                Opens with a nil balance. Each taking is recorded on the account's own
                                page and posted on its own, so the balance is always the sum of what
                                actually moved.
                            </small>
                        </div>
                    @endif

                    <script>
                        // One click answers both questions, so both handlers run —
                        // and then finSyncDisb settles the single field they disagree
                        // about. Order matters: whichever ran last would otherwise
                        // win, and "personal, fixed" would put the disbursement
                        // account back on screen.
                        function finArrangement(who, shape) {
                            finTakenFor(who);
                            finShape(shape);
                            finSyncDisb();
                            finQuadOut(who, shape);
                        }

                        // One sentence naming the consequence of the choice, in the
                        // card's own colour so the strip and the selection read as
                        // the same statement. Written as what the SAVE will do, not
                        // as what the option is called — "personal" tells you
                        // nothing; "the whole instalment lands in Drawings" does.
                        var FIN_OUTCOMES = {
                            'company:term': {
                                tone: '#2563eb', tint: '#eff6ff', soft: 'rgb(37 99 235 / .18)', icon: 'fa-building-columns',
                                html: 'Saving puts this on the balance sheet: <strong>Dr the account it landed in, Cr the liability you pick below</strong>, with any deducted fee expensed on day one. The instalment schedule is built from the terms.'
                            },
                            'company:running': {
                                tone: '#2563eb', tint: '#eff6ff', soft: 'rgb(37 99 235 / .18)', icon: 'fa-arrows-rotate',
                                html: 'Opens at a <strong>nil balance</strong> — nothing posts today. For an old loan, save first and then use <strong>Record opening liability</strong> on its detail page. Use Record drawing only for new money actually received.'
                            },
                            'personal:term': {
                                tone: '#d97706', tint: '#fffbeb', soft: 'rgb(217 119 6 / .2)', icon: 'fa-user-shield',
                                html: 'Nothing reaches the company\'s books. Only an instalment the company actually pays gets recorded — and that <strong>whole amount is Drawings</strong>, never an expense and never split into interest.'
                            },
                            'personal:running': {
                                tone: '#d97706', tint: '#fffbeb', soft: 'rgb(217 119 6 / .2)', icon: 'fa-user-shield',
                                html: 'Opens at a nil balance and stays off the company\'s books. Anything the company pays towards it is <strong>Drawings</strong> — money taken out for private use, never a business cost.'
                            }
                        };

                        function finQuadOut(who, shape) {
                            var box = document.getElementById('finQuadOut');
                            if (!box) return;
                            var o = FIN_OUTCOMES[who + ':' + shape];
                            if (!o) { box.style.display = 'none'; return; }
                            box.style.display = '';
                            box.style.setProperty('--qo', o.tone);
                            box.style.setProperty('--qo-tint', o.tint);
                            box.style.setProperty('--qo-soft', o.soft);
                            box.innerHTML = '<i class="fas ' + o.icon + '"></i><span>' + o.html + '</span>';
                        }

                        function finTakenFor(v) {
                            var isPersonal = v === 'personal';
                            var q = function (s) { return document.querySelector('#finAddModal ' + s); };
                            var co   = document.getElementById('finCoWrap');
                            var dept = document.getElementById('finDeptWrap');
                            var who  = document.getElementById('finWhoWrap');
                            if (co)   co.style.display   = isPersonal ? 'none' : '';
                            if (dept) dept.style.display = isPersonal ? 'none' : '';
                            if (who)  who.style.display  = isPersonal ? '' : 'none';

                            // Toggle required with the fields, or the browser blocks
                            // submit on an input nobody can see and gives no reason.
                            var coSel = q('[name=company_id]');
                            var whoIn = q('[name=personal_of]');
                            if (coSel) coSel.required = !isPersonal;
                            if (whoIn) whoIn.required = isPersonal;
                        }

                        function finShape(v) {
                            var running = v === 'running';
                            document.querySelectorAll('#finAddModal .fin-term-only').forEach(function (el) {
                                el.style.display = running ? 'none' : '';
                                // required comes off with the field for the same
                                // reason as above.
                                el.querySelectorAll('[required]').forEach(function (i) { i.dataset.wasRequired = '1'; i.required = false; });
                                if (!running) {
                                    el.querySelectorAll('[data-was-required]').forEach(function (i) { i.required = true; });
                                }
                            });
                            var hint = document.getElementById('finShapeHint');
                            if (hint) hint.style.display = running ? '' : 'none';
                            var submit = document.getElementById('finLoanSubmit');
                            if (submit) submit.textContent = running ? 'Open running account' : 'Save & build schedule';
                            var subtitle = document.getElementById('finLoanSubtitle');
                            if (subtitle) subtitle.textContent = running
                                ? 'Opens with a nil balance. Add an old outstanding amount from the detail page.'
                                : 'The instalment schedule is generated from these terms when you save.';
                        }

                        // "Money received into" is the one field BOTH answers govern,
                        // so neither handler owns it. It exists only for a company
                        // borrowing on fixed terms: a personal loan never touched a
                        // company account on the way in, and a running account is
                        // funded one taking at a time, each naming its own account.
                        //
                        // Cleared as well as hidden — a value left behind would post
                        // a cash leg for money that never arrived.
                        function finSyncDisb() {
                            var disb = document.getElementById('finDisbWrap');
                            if (!disb) return;

                            var picked = document.querySelector('#finAddModal [name=arrangement]:checked');
                            var value  = picked ? picked.value : 'company:term';
                            var hide   = value.indexOf('personal') === 0 || value.indexOf('running') > 0;

                            disb.style.display = hide ? 'none' : '';
                            if (hide) {
                                var sel = disb.querySelector('select');
                                if (sel) {
                                    sel.value = '';
                                    // select2 mirrors the native select into its own markup and
                                    // only re-reads it on change. Without this it keeps showing the
                                    // bank name while the field submits nothing.
                                    if (window.jQuery) jQuery(sel).trigger('change');
                                }
                            }
                        }

                        // Paint the opening state. "Company, fixed" is preselected,
                        // so the strip has to say so before anything is clicked —
                        // an empty box under a made choice reads as broken.
                        (function () {
                            var picked = document.querySelector('#finAddModal [name=arrangement]:checked');
                            if (!picked) return;
                            var parts = picked.value.split(':');
                            finQuadOut(parts[0], parts[1]);
                        })();
                    </script>

                    <div class="fin-grid">
                        <div class="fin-field" id="finCoWrap">
                            <label>Company <span>*</span></label>
                            <select name="company_id" required>
                                @foreach($companies as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($book === 'borrowed')
                            <div class="fin-field" id="finWhoWrap" style="display:none;">
                                <label>Whose loan <span>*</span></label>
                                <input type="text" name="personal_of" placeholder="Name of the person">
                            </div>
                        @endif

                        <div class="fin-field">
                            <label>{{ $book === 'borrowed' ? 'Lender' : 'Borrower' }} <span>*</span></label>
                            <input type="text" name="counterparty_name" required
                                   placeholder="{{ $book === 'borrowed' ? 'City Bank PLC' : 'Name of the person or business' }}">
                        </div>

                        @if($book === 'borrowed')
                            <div class="fin-field" id="finDeptWrap">
                                <label>Department</label>
                                <select name="department_id" class="fin-search">
                                    <option value="">— whole company —</option>
                                    @foreach($departments as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            {{-- Client instalment plans: what they already settled, and
                                 which sale it is against. Principal stays the FINANCED
                                 balance so no figure double counts. --}}
                            <div class="fin-field">
                                <label>Paid up front (৳)</label>
                                <input type="number" name="down_payment" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="fin-field">
                                <label>Against which sale</label>
                                <input type="text" name="linked_label" placeholder="Visa INV-2026-0142 / ticket no.">
                            </div>
                        @endif
                        {{-- Managed taxonomy. Sub-categories for every parent are
                             printed once and hidden; picking a category reveals only
                             its own children, so no request is needed to filter them. --}}
                        <div class="fin-field">
                            <label>Category</label>
                            <select name="category_id" id="finCatSel" onchange="finSubs(this.value)">
                                <option value="">— none —</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fin-field" id="finSubWrap" style="display:none;">
                            <label>Sub-category</label>
                            <select name="sub_category_id" id="finSubSel">
                                <option value="">— none —</option>
                                @foreach($categories as $cat)
                                    @foreach($cat->children as $child)
                                        <option value="{{ $child->id }}" data-parent="{{ $cat->id }}" hidden>{{ $child->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <script>
                            function finSubs(parentId) {
                                var wrap = document.getElementById('finSubWrap');
                                var sel  = document.getElementById('finSubSel');
                                var any  = false;
                                Array.prototype.forEach.call(sel.options, function (o) {
                                    if (!o.value) return;
                                    var mine = o.dataset.parent === parentId;
                                    o.hidden = !mine;
                                    if (mine) any = true;
                                });
                                sel.value = '';
                                wrap.style.display = any ? '' : 'none';
                            }

                            // Spells out the third figure a fee creates, because the
                            // form only asks for two: sanctioned and fee. Someone
                            // reconciling against the bank statement needs to know
                            // what will actually land, and that it is NOT the debt.
                            function finNet() {
                                var p = document.getElementById('finPrincipal');
                                var f = document.getElementById('finFee');
                                var h = document.getElementById('finNetHint');
                                if (!p || !f || !h) return;

                                var principal = parseFloat(p.value) || 0;
                                var fee = parseFloat(f.value) || 0;

                                if (fee <= 0 || principal <= 0) { h.style.display = 'none'; return; }

                                if (fee >= principal) {
                                    h.textContent = 'The fee cannot be the whole loan — check both figures.';
                                    h.style.display = '';
                                    return;
                                }

                                h.textContent = '৳' + (principal - fee).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})
                                    + ' will reach the account. ৳' + principal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})
                                    + ' is still owed.';
                                h.style.display = '';
                            }
                        </script>

                        <div class="fin-field">
                            <label>Kind <span style="color:var(--fin-faint);font-weight:500;text-transform:none;letter-spacing:0;">· free text</span></label>
                            <input type="text" name="kind" list="finKinds"
                                   placeholder="{{ $book === 'borrowed' ? 'Car Loan' : 'Personal Loan' }}">
                            <datalist id="finKinds">
                                @if($book === 'borrowed')
                                    <option value="Bank Loan"><option value="Car Loan"><option value="Flat / Property Loan">
                                    <option value="Equipment Loan"><option value="Working Capital"><option value="Lease">
                                @else
                                    <option value="Personal Loan"><option value="Business Loan"><option value="Goodwill Loan">
                                    <option value="Service on Instalments">
                                @endif
                            </datalist>
                        </div>
                        <div class="fin-field">
                            <label>Reference / account no.</label>
                            <input type="text" name="account_no" placeholder="CBL-TL-77120">
                        </div>

                        <div class="fin-field fin-term-only">
                            <label>{{ $book === 'borrowed' ? 'Principal sanctioned' : 'Principal' }} (৳) <span>*</span></label>
                            <input type="number" name="principal" step="0.01" min="0.01" required placeholder="0.00"
                                   @if($book === 'borrowed') id="finPrincipal" oninput="finNet()" @endif>
                        </div>
                        @if($book === 'borrowed')
                            {{-- Deducted by the lender before the money lands, so the
                                 account shows less than was sanctioned — but the full
                                 principal is still what has to be repaid. --}}
                            <div class="fin-field fin-term-only">
                                <label>Processing fee deducted (৳)</label>
                                <input type="number" name="processing_fee" step="0.01" min="0" placeholder="0.00"
                                       id="finFee" oninput="finNet()">
                                <small class="fin-hint" id="finNetHint" style="display:none;"></small>
                            </div>
                        @endif
                        {{-- The cash leg, and BOTH books need one — naming the account is
                             what puts the arrangement on the ledger at all.

                             It used to sit inside the borrowed-only block, which left the
                             lent book with no way to say where the money went out from.
                             lentPosts() requires exactly this field on a fixed loan, so
                             every loan given was recorded on the desk and never reached
                             the books — the cash simply vanished from the bank while the
                             statement showed it gone.

                             Blank stays meaningful on the lent side: an instalment plan
                             against a sale never paid anything out, and the sale already
                             booked the receivable. That is the one case which must not
                             post, and leaving this empty is how it says so.

                             A personal borrowing and a running account both null it in the
                             controller, so finSyncDisb() hides it for those. --}}
                        <div class="fin-field fin-term-only" id="finDisbWrap">
                            <label>{{ $book === 'borrowed' ? 'Money received into' : 'Money paid from' }}<span class="fin-i-wrap"><button type="button" class="fin-i" aria-expanded="false" aria-label="What naming this account does">i</button><span class="fin-i-pop">@if($book === 'borrowed')Naming the account is what <strong>posts the loan to the ledger</strong>: the cash that actually arrived, the lender's fee as an expense on day one, and the <strong>full sanctioned principal</strong> as the liability. Left blank, the loan is recorded on this desk only.@else Naming the account is what <strong>posts the loan to the ledger</strong>: cash leaves it and the borrower's debt to you takes its place. Leave it blank for an <strong>instalment plan against a sale</strong> — nothing was paid out, and the sale already put the balance on the books.@endif</span></span></label>
                            <select name="disbursement_bank_id" class="fin-search">
                                <option value="">{{ $book === 'borrowed' ? '— not received into a company account —' : '— no cash left a company account —' }}</option>
                                @include('partials.bank-options', ['banks' => $banks, 'selected' => null])
                            </select>
                        </div>
                        <div class="fin-field fin-term-only">
                            <label>Interest method <span>*</span></label>
                            <select name="interest_method" required>
                                <option value="none">No interest</option>
                                <option value="reducing" selected>Reducing balance</option>
                                <option value="flat">Flat</option>
                            </select>
                        </div>
                        <div class="fin-field fin-term-only">
                            <label>Annual rate (%)</label>
                            <input type="number" name="interest_rate" step="0.001" min="0" max="100" placeholder="0">
                        </div>
                        <div class="fin-field fin-term-only">
                            <label>Tenure (months) <span>*</span></label>
                            <input type="number" name="tenure_months" min="1" max="600" required placeholder="36">
                        </div>
                        <div class="fin-field">
                            <label>Start date <span>*</span></label>
                            <input type="date" name="start_date" required value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="fin-field">
                        <label>Purpose</label>
                        <input type="text" name="purpose" placeholder="What the money is for">
                    </div>
                    <div class="fin-field">
                        <label>Security / collateral</label>
                        <input type="text" name="security" placeholder="Property mortgage, vehicle hypothecation…">
                    </div>

                    {{-- Posting hints. Decided once here by someone who knows what
                         the loan is, then shown on every screen so whoever records
                         the bank entry does not have to guess the contra account.
                         Stored only — nothing on this desk posts a journal. --}}
                    <div class="fin-grid">
                        <div class="fin-field">
                            <label>Posts against — principal</label>
                            <select name="gl_account_id" class="fin-search">
                                <option value="">— decide later —</option>
                                @foreach($glAccounts as $glType => $glRows)
                                    <optgroup label="{{ ucfirst($glType) }}">
                                        @foreach($glRows as $ga)
                                            <option value="{{ $ga->id }}"
                                                {{-- Defaults come from config/accounts.php, never from codes typed
                                                     in here. The old list (2210, 2120, 1050) was written against the
                                                     previous chart; after the renumber 2210 became Salaries Payable,
                                                     2120 stopped existing and 1050 became Mobile Financial Services —
                                                     so a borrowing saved without touching this field posted its
                                                     liability to the payroll account. --}}
                                                @if($book === 'borrowed' && $ga->code === config('accounts.loan_payable')) selected @endif
                                                @if($book === 'lent' && $ga->code === config('accounts.director_current_account')) selected @endif
                                            >{{ $ga->code }} — {{ $ga->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="fin-field">
                            <label>Posts against — interest</label>
                            <select name="gl_interest_account_id" class="fin-search">
                                <option value="">— none / no interest —</option>
                                @foreach($glAccounts as $glType => $glRows)
                                    <optgroup label="{{ ucfirst($glType) }}">
                                        @foreach($glRows as $ga)
                                            <option value="{{ $ga->id }}"
                                                {{-- Same rule as the principal select above: codes come from
                                                     config, never typed here. 7002 and 4201 were the old chart's
                                                     interest accounts and neither survived the renumber, so this
                                                     select silently defaulted to nothing and an asset account got
                                                     picked by hand for a cost. --}}
                                                @if($book === 'borrowed' && $ga->code === config('accounts.loan_interest_expense')) selected @endif
                                                @if($book === 'lent' && $ga->code === config('accounts.loan_interest_income')) selected @endif
                                            >{{ $ga->code }} — {{ $ga->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="fin-field">
                        <label>Notes</label>
                        <textarea name="notes" rows="2"></textarea>
                    </div>

                    {{-- The old note here said this desk posts nothing. That stopped
                         being true when the posting service landed, and a stale
                         reassurance is worse than none — somebody would have entered
                         the bank movement a second time in Manage Banks on the
                         strength of it. What actually happens is now stated per
                         choice, in the strip under the four options. --}}
                </div>

                <div class="fin-modal-foot">
                    <button type="button" class="fin-btn-ghost" onclick="document.getElementById('finAddModal').style.display='none'">Cancel</button>
                    <button type="submit" class="fin-btn-primary" id="finLoanSubmit">Save &amp; build schedule</button>
                </div>
            </form>
        </div>
    </div>
    @endcan
@endif

</div>{{-- /.fin-scope --}}

{{-- Clicking the dim outside a modal cancels it, exactly as its Cancel button
     does — hide it and leave the fields as they were (owner, 2026-08-22).

     Guarded on where the press STARTED, not on where the click landed. A `click`
     fires on the nearest common ancestor of its mousedown and its mouseup, so
     dragging to select the text in a field and releasing out over the dim would
     report the overlay as the target and shut the modal on someone mid-edit.
     Only a press that both begins and ends on the dim itself closes it.

     Scoped to this page's own `.fin-modal` elements, bound one by one. Nothing
     here is delegated off the document, so no modal on any other screen — and
     no other company's — can be reached by it. --}}
<script>
    (function () {
        document.querySelectorAll('.fin-modal').forEach(function (modal) {
            var startedOnBackdrop = false;
            modal.addEventListener('mousedown', function (e) {
                startedOnBackdrop = (e.target === modal);
            });
            modal.addEventListener('click', function (e) {
                if (startedOnBackdrop && e.target === modal) {
                    modal.style.display = 'none';
                }
                startedOnBackdrop = false;
            });
        });
    })();
</script>

@include('financing.partials.styles')
@endsection

@section('js')
{{-- jQuery FIRST: select2 is a jQuery plugin and the layout does not provide
     jQuery globally, so without this the search box silently never appears. --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    'use strict';

    /* Department and the two posting accounts are the long lists on this form —
       the chart of accounts alone runs to hundreds of rows, and hunting one in a
       native <select> is the slow part of filing a loan.

       Only these three are upgraded. The arrangement radios, the shape toggle and
       the interest method are short, and finShape()/finTakenFor() show and hide
       their wrappers directly — select2 would put its own container in the way. */
    function wire() {
        if (!window.jQuery || !jQuery.fn.select2) return;

        jQuery('.fin-search').each(function () {
            var $el = jQuery(this);
            if ($el.data('select2')) return;          // already upgraded

            $el.select2({
                width: '100%',
                minimumResultsForSearch: 5,
                // .fin-modal is a fixed overlay; without this the list renders
                // into <body> and opens behind the sheet.
                dropdownParent: $el.closest('.fin-modal-box').length
                    ? $el.closest('.fin-modal-box')
                    : jQuery(document.body),
            });
        });
    }

    document.addEventListener('DOMContentLoaded', wire);
})();
</script>
@endsection
