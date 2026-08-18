{{--
    The petty cash desk — who is holding company money right now.

    Every "Held now" figure on this page is read back from the ledger, not stored,
    because three different flows write to the same float account: issuing,
    returning, and the expense form settling a receipt against it. A stored total
    would have to be right in three places at once.
--}}
@extends('layout.app')

@section('meta-information')
    <title>Petty Cash</title>
@endsection

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
<style>
    /* ── Minimal palette ──────────────────────────────────────────────────
       Ink on paper. Every surface is white or near-white, every border is one
       grey, and nothing is coloured for decoration.

       Colour is reserved for STATE, and there are only three:
         danger  — a pot that is empty, a limit that is blown, a delete
         warn    — near a limit, or short of covering one
         nothing — everything else

       That is the whole point of spending it so carefully: on a page whose
       gradients were teal and violet, red meant "this row is red" and nothing
       more. On this one it is the only colour on screen, so it cannot be
       missed and cannot be mistaken for styling. */
    :root {
        --pc-ink:     #0f172a;   /* headings, figures */
        --pc-body:    #334155;   /* body copy */
        --pc-muted:   #64748b;   /* labels, captions */
        --pc-border:  #e2e8f0;   /* every border on the page */
        --pc-bg:      #f8fafc;   /* page behind the cards */
        --pc-subtle:  #f1f5f9;   /* tracks, hovers, footers */

        --pc-danger:  #dc2626;
        --pc-warning: #b45309;

        /* Kept because the modals and pills still reference them; both now
           resolve to ink so nothing renders coloured by accident. */
        --pc-primary: #0f172a;
        --pc-primary-dark: #0f172a;
        --pc-success: #334155;
    }

    .pc-shell { background: var(--pc-bg); padding: 18px; }

    /* Flat, bordered, barely lifted. A heavy shadow is decoration where a 1px
       line does the same job. */
    .pc-card {
        background: #fff;
        border: 1px solid var(--pc-border);
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }

    .pc-header {
        background: #fff;
        color: var(--pc-ink);
        border-bottom: 1px solid var(--pc-border);
        padding: 18px 22px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        justify-content: space-between;
    }
    .pc-header h2 { font-size: 17px; font-weight: 700; margin: 0; }
    .pc-header .subtext { font-size: 12.5px; color: var(--pc-muted); margin-top: 4px; line-height: 1.5; }

    .pc-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 13px; border-radius: 8px; font-size: 12.5px; font-weight: 600;
        border: 1px solid var(--pc-border); background: #fff; color: var(--pc-body);
        cursor: pointer; transition: background .15s, border-color .15s;
    }
    .pc-btn:hover { background: var(--pc-subtle); }
    /* One filled button per screen area — the primary action, in ink. */
    .pc-btn.add, .pc-btn.fund { background: var(--pc-ink); color: #fff; border-color: var(--pc-ink); }
    .pc-btn.add:hover, .pc-btn.fund:hover { background: #1e293b; border-color: #1e293b; }
    .pc-btn.reset { background: #fff; color: var(--pc-body); }

    .pc-summary {
        display: grid; gap: 14px; padding: 18px 22px;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        border-bottom: 1px solid var(--pc-border);
    }
    .pc-stat { border: 1px solid var(--pc-border); border-radius: 10px; padding: 13px 15px; background: #fff; }
    .pc-stat .label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; color: var(--pc-muted); font-weight: 700; }
    .pc-stat .value { font-size: 21px; font-weight: 700; margin-top: 4px; color: var(--pc-ink); }
    .pc-stat .note  { font-size: 11px; color: var(--pc-muted); margin-top: 2px; }
    /* The coloured left rules are gone: they classified tiles by nothing the
       reader needed. Only a genuinely bad figure gets a colour now. */
    .pc-stat.danger .value { color: var(--pc-danger); }

    .pc-filters { padding: 15px 22px; border-bottom: 1px solid var(--pc-border); background: var(--pc-subtle); }
    .pc-filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 190px; }
    .pc-filter-group label { font-size: 11px; font-weight: 700; color: var(--pc-muted); text-transform: uppercase; }
    .pc-filter-group select, .pc-filter-group input {
        border: 1px solid var(--pc-border); border-radius: 8px; padding: 7px 10px; font-size: 13px;
    }

    .pc-pill {
        display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px;
        border-radius: 99px; font-size: 11px; font-weight: 700;
    }
    /* Holding / empty / closed told apart by weight and wording, not by hue —
       none of the three is a problem, so none of them earns a colour. */
    .pc-pill.ok    { background: var(--pc-subtle); color: var(--pc-ink); }
    .pc-pill.empty { background: var(--pc-subtle); color: var(--pc-muted); }
    .pc-pill.closed{ background: var(--pc-subtle); color: var(--pc-muted); }
    /* The one state on this page that IS a claim on the company rather than a
       description of its own cash, so it is the one that earns amber — the same
       amber the expense desk uses for anything still outstanding. */
    .pc-pill.owed  { background: #fffbeb; color: #b45309; }

    /* Money running the other way, and coloured so a glance down the column finds
       it. Held is the company's and reads neutral; this is not ours to keep. */
    .pc-owed { font-weight: 800; font-size: 13.5px; color: var(--pc-warning); white-space: nowrap; }

    /* The custodian's name, as the way into their transactions.
       No colour, on purpose. This page spends its whole palette on one thing —
       red for the irreversible action — and a column of blue links would take
       that away for the sake of an affordance an underline already provides. So
       the name keeps the ink every other name on the page has, and earns an
       underline and a small arrow when it is pointed at. */
    .pc-name-link {
        display: inline-flex; align-items: center; gap: 5px;
        font-weight: 600; font-size: 13.5px; color: var(--pc-ink);
        text-decoration: none;
    }
    .pc-name-link i { font-size: 9px; opacity: 0; transform: translateX(-3px); transition: opacity .15s, transform .15s; }
    .pc-name-link:hover,
    /* Keyboard users get the same cue — hover is not the only way in. */
    .pc-name-link:focus-visible { text-decoration: underline; }
    .pc-name-link:hover i,
    .pc-name-link:focus-visible i { opacity: .55; transform: translateX(0); }
    .pc-name-link:focus-visible { outline: 2px solid var(--pc-ink); outline-offset: 3px; border-radius: 2px; }

    .pc-kpi.is-owed .value { color: #b45309; }

    .pc-act {
        display: inline-flex; align-items: center; justify-content: center;
        width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--pc-border);
        background: #fff; color: var(--pc-body); cursor: pointer; transition: .15s; font-size: 12px;
    }
    .pc-act:hover { background: var(--pc-subtle); }
    .pc-act.issue  { color: var(--pc-ink); font-weight: 700; }
    .pc-act.give   { color: var(--pc-body); }
    /* Delete keeps its red. It is the one irreversible thing on the row, and
       that is exactly what the page's only spare colour is for. */
    .pc-act.danger { color: var(--pc-danger); }
    .pc-act.danger:hover { background: #fef2f2; border-color: #fecaca; }
    /* Paying someone back. Amber like the balance it settles, so the button and
       the figure it acts on are plainly the same subject. */
    .pc-act.payback { color: #b45309; border-color: #fde68a; background: #fffbeb; }
    .pc-act.payback:hover { background: #fef3c7; }
    .pc-act.reverse { color: #ea580c; border-color: #fed7aa; }
    .pc-act.reverse:hover { background: #fff7ed; }
    /* The two money-moving actions carry their name; the rest stay icons. */
    .pc-act.labelled { width: auto; gap: 6px; padding: 0 11px; font-weight: 700; font-size: 12px; }

    .pc-modal {
        position: fixed; inset: 0; background: rgba(15,23,42,.55);
        display: none; align-items: center; justify-content: center; z-index: 1050; padding: 16px;
    }
    .pc-modal.show { display: flex; }
    .pc-modal-box { background: #fff; border-radius: 12px; width: 100%; max-width: 480px; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,.22); }
    /* White head with a rule, not a coloured band. The title carries the modal;
       a gradient behind it only made it louder. */
    .pc-modal-head { padding: 15px 20px; background: #fff; color: var(--pc-ink); border-bottom: 1px solid var(--pc-border); display: flex; justify-content: space-between; align-items: center; }
    .pc-modal-head h3 { font-size: 15px; font-weight: 700; margin: 0; }
    .pc-modal-head button { color: var(--pc-muted) !important; }
    .pc-modal-body { padding: 20px; display: flex; flex-direction: column; gap: 14px; max-height: 70vh; overflow-y: auto; }
    .pc-modal-foot { padding: 14px 20px; background: var(--pc-subtle); border-top: 1px solid var(--pc-border); display: flex; justify-content: flex-end; gap: 8px; }
    .pc-field label { display: block; font-size: 12px; font-weight: 700; color: var(--pc-body); margin-bottom: 5px; }
    .pc-field input, .pc-field select, .pc-field textarea {
        width: 100%; border: 1px solid var(--pc-border); border-radius: 9px; padding: 9px 11px; font-size: 13px;
    }
    .pc-hint { font-size: 11px; color: var(--pc-muted); margin-top: 4px; }
    .pc-modal-foot .cancel { background: #fff; border: 1px solid var(--pc-border); color: var(--pc-body); }
    .pc-modal-foot .save   { background: var(--pc-ink); color: #fff; border-color: var(--pc-ink); }
    .pc-modal-foot .save:hover { background: #1e293b; }
    .pc-modal-foot .save.warn { background: var(--pc-warning); border-color: var(--pc-warning); }
    .pc-modal-foot .save.danger { background: var(--pc-danger); border-color: var(--pc-danger); }

    /* Opening hand-over block — set apart from the fields above it because those
       describe the arrangement and this one moves money. */
    .pc-openbox { border: 1px dashed #cbd5e1; border-radius: 12px; padding: 12px 14px; background: #f8fafc; }
    .pc-openbox-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .pc-openbox-toggle { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;
        font-size: 13px; font-weight: 700; color: #0f172a; }
    .pc-openbox-toggle input { width: 15px; height: 15px; cursor: pointer; accent-color: var(--pc-primary); }
    .pc-openbox-tag { font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
        color: #64748b; background: #e2e8f0; border-radius: 20px; padding: 2px 8px; }
    .pc-openbox-lead { font-size: 11.5px; color: #64748b; margin: 7px 0 0; line-height: 1.5; }
    .pc-openbox-fields { display: flex; flex-direction: column; gap: 12px; margin-top: 12px;
        padding-top: 12px; border-top: 1px solid #e2e8f0; }

    /* ── The money card ───────────────────────────────────────────────────
       KPIs sit INSIDE the gradient rather than as white tiles below it: the four
       figures are the headline of this page, and a header that states them is
       one glance instead of two. Teal, because everything in it is real cash —
       the violet card below it is limits, and the colour is the fastest way to
       keep the two apart. */
    .pc-moneycard { margin-bottom: 16px; }
    .pc-moneyhead { background: #fff; color: var(--pc-ink); padding: 18px 22px 20px; }
    .pc-moneyhead-top {
        display: flex; flex-wrap: wrap; gap: 12px;
        align-items: flex-start; justify-content: space-between; margin-bottom: 16px;
    }
    .pc-moneyhead h2 { font-size: 17px; font-weight: 700; margin: 0; }
    .pc-moneyhead .subtext { font-size: 12.5px; color: var(--pc-muted); margin-top: 5px; max-width: 62ch; line-height: 1.5; }
    .pc-moneyhead-actions { display: flex; gap: 8px; flex-wrap: wrap; }

    .pc-kpis { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(168px, 1fr)); }
    .pc-kpi { background: #fff; border: 1px solid var(--pc-border); border-radius: 10px; padding: 12px 14px; }
    .pc-kpi .label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; color: var(--pc-muted); }
    .pc-kpi .value { font-size: 21px; font-weight: 700; margin-top: 4px; line-height: 1.15; color: var(--pc-ink); }
    .pc-kpi .note  { font-size: 10.5px; color: var(--pc-muted); margin-top: 3px; }

    /* The pot leads. With no colour to spend, the emphasis is size and a solid
       ink rule down its edge — enough to rank it first without implying that
       the other three are in a different state. */
    .pc-kpi.is-lead { border-left: 3px solid var(--pc-ink); background: var(--pc-subtle); }
    .pc-kpi.is-lead .value { font-size: 26px; }

    /* An empty pot is the one thing on this card that is wrong, so it is the one
       thing that gets colour. */
    .pc-kpi.is-lead.is-empty { border-color: #fecaca; border-left-color: var(--pc-danger); background: #fef2f2; }
    .pc-kpi.is-lead.is-empty .value,
    .pc-kpi.is-lead.is-empty .label,
    .pc-kpi.is-lead.is-empty .note { color: var(--pc-danger); }

    .pc-potwarn {
        margin-top: 14px; padding: 10px 13px; border-radius: 8px;
        border: 1px solid #fecaca; background: #fef2f2; color: var(--pc-danger);
        font-size: 11.5px; line-height: 1.55; display: flex; gap: 9px; align-items: flex-start;
    }
    .pc-potwarn > i { margin-top: 2px; }

    /* The float table's own header, now that the figures have left it. */
    .pc-header-slim { padding: 14px 24px; }
    .pc-header-slim h2 { font-size: 16px; }

    /* ── Daily cost fund ──────────────────────────────────────────────────
       Its own card, and deliberately a different colour from the teal petty
       cash card below it. The two are different kinds of thing — one is a
       ceiling, the other is money — and a reader who cannot tell them apart
       will eventually treat "remaining today" as cash in hand, which it is not.
       Violet is the reference design's colour for it. */
    .pc-fundcard { margin-bottom: 16px; }
    .pc-fundhead {
        background: #fff; color: var(--pc-ink);
        border-bottom: 1px solid var(--pc-border);
        padding: 15px 22px;
        display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;
    }
    /* No bottom rule when shut — the bar is then the whole card. */
    .pc-fundcard.is-collapsed .pc-fundhead { border-bottom: 0; }
    .pc-fundhead h2 { font-size: 17px; font-weight: 700; margin: 0; }
    .pc-fundhead .subtext { font-size: 12px; opacity: .85; margin-top: 3px; }
    .pc-fundhead-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

    /* Collapsible. The whole bar is the control, so the cursor says so, but the
       date picker and Add/Edit button inside it stop the click from reaching
       here — a toggle that also fired on the button would be unusable. */
    .pc-fundhead { cursor: pointer; user-select: none; }
    .pc-fundhead:hover { background: var(--pc-subtle); }
    .pc-fundhead:focus-visible { outline: 2px solid var(--pc-ink); outline-offset: -3px; }
    .pc-fundhead-title { display: flex; align-items: center; gap: 11px; }
    .pc-fundchev { transition: transform .18s ease; font-size: 13px; opacity: .85; }

    /* Collapsed state, driven by a class on the CARD so the chevron and the body
       can never disagree about which way round they are. */
    .pc-fundcard.is-collapsed .pc-fundchev { transform: rotate(-90deg); }
    .pc-fundcard.is-collapsed #pcFundBody { display: none; }

    /* The figure that stays visible when the section is shut. Hidden while open
       because the tiles below say the same thing with more room. */
    .pc-fundpeek { display: none; align-items: baseline; gap: 8px; margin-left: auto; margin-right: 14px; }
    .pc-fundcard.is-collapsed .pc-fundpeek { display: flex; }
    .pc-fundpeek-fig { font-size: 15px; font-weight: 700; color: var(--pc-ink); }
    .pc-fundpeek-fig .sep { color: var(--pc-muted); font-weight: 500; margin: 0 1px; }
    .pc-fundpeek-cap { font-size: 11px; color: var(--pc-muted); }
    .pc-fundpeek-over {
        font-size: 10.5px; font-weight: 700; background: #fef2f2; color: var(--pc-danger);
        border: 1px solid #fecaca; border-radius: 20px; padding: 1px 8px;
    }

    /* Ceilings that outrun the pot behind them. */
    .pc-fundshort {
        margin: 0 24px; padding: 10px 13px; border-radius: 10px;
        border: 1px solid #fde68a; background: #fffbeb; color: #92400e;
        font-size: 11.5px; line-height: 1.55; display: flex; gap: 9px; align-items: flex-start;
    }
    .pc-fundshort > i { margin-top: 2px; }
    .pc-fundhead input[type="date"] {
        border: 1px solid var(--pc-border); background: #fff; color: var(--pc-body);
        border-radius: 8px; padding: 7px 10px; font-size: 12.5px;
    }
    .pc-fundhead input[type="date"]::-webkit-calendar-picker-indicator { opacity: .55; cursor: pointer; }

    .pc-fundstats {
        display: grid; gap: 14px; padding: 18px 24px;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        border-bottom: 1px solid var(--pc-border);
    }
    .pc-fundstat { border: 1px solid var(--pc-border); border-radius: 10px; padding: 13px 15px; background: #fff; }
    .pc-fundstat .label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; color: var(--pc-muted); font-weight: 700; }
    .pc-fundstat .value { font-size: 21px; font-weight: 700; margin-top: 4px; color: var(--pc-ink); }
    /* Three figures of the same kind, so three of the same colour. Only a
       negative remainder — spent past the ceiling — is worth a red. */
    .pc-fundstat.left.negative .value { color: var(--pc-danger); }
    .pc-fundstat .note { font-size: 11px; color: var(--pc-muted); margin-top: 2px; }

    .pc-fundlist { padding: 16px 24px 20px; }
    .pc-fundlist-title { font-size: 13px; font-weight: 700; color: var(--pc-ink); margin-bottom: 4px; }
    .pc-fundlist-lead { font-size: 11.5px; color: var(--pc-muted); margin-bottom: 12px; }

    .pc-fundrow {
        display: grid; grid-template-columns: minmax(140px, 1.3fr) minmax(120px, 2fr) minmax(120px, auto);
        gap: 16px; align-items: center; padding: 11px 6px;
        border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background .12s;
    }
    .pc-fundrow:hover { background: var(--pc-subtle); }
    .pc-fundrow:last-child { border-bottom: 0; }
    .pc-fundrow-name { font-size: 13px; font-weight: 600; color: var(--pc-ink); }
    .pc-fundrow-track { height: 6px; background: var(--pc-border); border-radius: 999px; overflow: hidden; }
    .pc-fundrow-fill { height: 100%; border-radius: inherit; transition: width .25s ease; }
    /* Spending within your limit is not an achievement, so it is not green — it
       is just the bar filling up. The colours start only when it matters. */
    .pc-fundrow-fill.ok   { background: #94a3b8; }
    .pc-fundrow-fill.near { background: var(--pc-warning); }
    .pc-fundrow-fill.over { background: var(--pc-danger); }
    .pc-fundrow-fig { font-size: 12.5px; text-align: right; white-space: nowrap; color: var(--pc-muted); }
    .pc-fundrow-fig .spent { font-weight: 700; }
    .pc-fundrow.is-over .pc-fundrow-fig .spent { color: var(--pc-danger); }
    .pc-fundrow.is-unset { cursor: pointer; }
    .pc-fundrow-unset { font-size: 11.5px; color: #94a3b8; font-style: italic; }

    /* The set-funds modal is a table, so it needs more room than the 480px the
       money-moving modals use. */
    .pc-modal-box.wide { max-width: 620px; }
    .pc-fundedit { width: 100%; border-collapse: collapse; }
    .pc-fundedit th {
        text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em;
        color: var(--pc-muted); font-weight: 700; padding: 0 8px 8px; border-bottom: 1px solid var(--pc-border);
    }
    .pc-fundedit th:last-child, .pc-fundedit td:last-child { text-align: right; }
    .pc-fundedit td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    .pc-fundedit input[type="number"] {
        width: 130px; border: 1px solid var(--pc-border); border-radius: 8px;
        padding: 7px 9px; font-size: 13px; text-align: right;
    }
    .pc-fundedit tfoot td { font-weight: 700; border-bottom: 0; padding-top: 12px; }

    .pc-fundnote {
        background: var(--pc-subtle); border: 1px solid var(--pc-border); border-radius: 8px;
        padding: 10px 12px; font-size: 11.5px; color: var(--pc-body); line-height: 1.55;
    }

    /* Breakdown modal */
    .pc-bd { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .pc-bd th {
        text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em;
        color: var(--pc-muted); font-weight: 700; padding: 0 8px 8px; border-bottom: 1px solid var(--pc-border);
    }
    .pc-bd td { padding: 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .pc-bd th:last-child, .pc-bd td:last-child { text-align: right; white-space: nowrap; }
    .pc-bd tfoot td { font-weight: 700; border-bottom: 0; padding-top: 10px; }
    .pc-bd-sub { color: var(--pc-muted); font-size: 11px; }

    @media (max-width: 640px) {
        .pc-fundrow { grid-template-columns: 1fr auto; }
        .pc-fundrow-track { grid-column: 1 / -1; order: 3; }
    }

    /* select2 inside a pc-modal: the modal is a fixed overlay, so the dropdown is
       rendered into the modal box (dropdownParent) rather than <body>, or it would
       open behind the overlay. */
    .pc-modal .select2-container { z-index: 10000; }
    .pc-modal .select2-container--default .select2-selection--single {
        height: 38px; border-color: var(--pc-border); border-radius: 8px;
    }
    .pc-modal .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px; font-size: 13px; color: var(--pc-ink);
    }
    .pc-modal .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
</style>
@endsection

@section('main-content')

    @include('layout.expense-tabs')

@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
    $canManage = auth()->user()->can('create expense');
    // Handing money BACK is its own act — see the migration that adds it. Someone
    // may keep the float ledger without being trusted to pay claims.
    $canReimburse = auth()->user()->can('reimburse employee');
@endphp

@php
    $fundIsToday = $fundDate === now()->toDateString();
    $fundWhen    = $fundIsToday ? 'today' : \Illuminate\Support\Carbon::parse($fundDate)->format('j M Y');
@endphp

<div class="pc-shell">

    {{-- ── 1. THE MONEY ─────────────────────────────────────────────────────
         Read first, because it is the only thing on this page that is actually
         cash. The pot leads: everything below it — what custodians hold, what
         they have spent — came out of it, and it is the figure that decides
         whether anything can happen today at all.

         Deliberately above the Daily Cost Fund. The ceiling is a decision
         somebody typed in; this is money that exists. When the two disagree it
         is always the money that wins, so the money is what you see first. --}}
    <div class="pc-card pc-moneycard">
        <div class="pc-moneyhead">
            <div class="pc-moneyhead-top">
                <div>
                    <h2><i class="fas fa-wallet mr-2"></i>Petty Cash</h2>
                    <div class="subtext">
                        Everything cash is issued and spent from <strong>{{ $summary['pot_name'] }}</strong>.
                        Top it up from Office Cash when it runs low.
                    </div>
                </div>
                <div class="pc-moneyhead-actions">
                    {{-- This page is "right now"; the report is "over a period".
                         Two different questions, so the report is a place you go
                         rather than a filter that changes what this page means. --}}
                    <a href="{{ route('role.petty-cash.report', ['role' => $role]) }}" class="pc-btn">
                        <i class="fas fa-chart-column"></i>Report
                    </a>
                    <a href="{{ route('role.petty-cash.index', ['role' => $role]) }}" class="pc-btn reset">
                        <i class="fas fa-rotate-left"></i>Reset
                    </a>
                    @if($canManage)
                    <button type="button" class="pc-btn add" onclick="pcOpen('#pcCreateModal')">
                        <i class="fas fa-plus"></i>New Float
                    </button>
                    @endif
                </div>
            </div>

            {{-- The cash chain, left to right: what is in the pot, what has left
                 it for a pocket, what has been accounted for out of those
                 pockets. Reading them in that order is reading the money's
                 journey, which is why they are not sorted by size. --}}
            <div class="pc-kpis">
                <div class="pc-kpi is-lead {{ $summary['pot_balance'] <= 0 ? 'is-empty' : '' }}">
                    <div class="label">In the pot</div>
                    <div class="value">৳ {{ number_format($summary['pot_balance'], 2) }}</div>
                    <div class="note">
                        {{ $summary['pot_balance'] > 0
                            ? 'Available to issue or spend'
                            : 'Empty — nothing can be issued' }}
                    </div>
                </div>
                <div class="pc-kpi">
                    <div class="label">Held by custodians</div>
                    <div class="value">৳ {{ number_format($summary['total_held'], 2) }}</div>
                    <div class="note">Out of the pot, not yet spent</div>
                </div>
                <div class="pc-kpi">
                    <div class="label">Spent from floats</div>
                    <div class="value">৳ {{ number_format($summary['total_spent'], 2) }}</div>
                    <div class="note">Receipts filed against a custodian</div>
                </div>
                {{-- The money running the other way. Last in the row on purpose:
                     the three before it follow the company's cash out of the pot,
                     and this one is not the company's cash at all — it is staff
                     money the company is holding on to. --}}
                <div class="pc-kpi {{ $summary['total_owed'] > 0 ? 'is-owed' : '' }}">
                    <div class="label">Owed to staff</div>
                    <div class="value">৳ {{ number_format($summary['total_owed'], 2) }}</div>
                    <div class="note">
                        {{ $summary['total_owed'] > 0
                            ? 'Their own money, not paid back yet'
                            : 'Nobody is out of pocket' }}
                    </div>
                </div>
                <div class="pc-kpi">
                    <div class="label">Custodians</div>
                    <div class="value">{{ number_format($summary['custodians']) }}</div>
                    <div class="note">People holding cash</div>
                </div>
            </div>

            @if($summary['pot_balance'] <= 0)
            <div class="pc-potwarn">
                <i class="fas fa-triangle-exclamation"></i>
                <span>
                    <strong>{{ $summary['pot_name'] }}</strong> is empty, so no cash can be issued or spent
                    however the limits below are set. Top it up with a journal —
                    <strong>Dr {{ $summary['pot_name'] }}</strong>, <strong>Cr Office Cash</strong> — or a
                    bank transfer into it.
                </span>
            </div>
            @endif
        </div>
    </div>

    {{-- ── 2. THE CEILING ───────────────────────────────────────────────────
         How much each company MAY spend in cash on this date. Its own card
         because it is its own kind of thing: nothing here is money and nothing
         here posts to the ledger — it is a decision somebody typed in.

         Collapsible, and that is a statement about how often it is read: limits
         are set once and stand until changed, so the detail is worth a click
         rather than a permanent third of the screen. The bar stays honest when
         shut — today's spend against today's ceiling is right there, so
         collapsing hides the controls, never the number. --}}
    <div class="pc-card pc-fundcard" id="pcFundCard">
        <div class="pc-fundhead" id="pcFundToggle" role="button" tabindex="0"
             aria-controls="pcFundBody" aria-expanded="true"
             title="Show or hide the daily limits">
            <div class="pc-fundhead-title">
                <i class="fas fa-chevron-down pc-fundchev"></i>
                <div>
                    <h2>Daily Cost Fund</h2>
                    <div class="subtext">A spending limit per company — not money.</div>
                </div>
            </div>

            {{-- Shown only while collapsed: the one number the section exists to
                 report, so shutting it never costs information. --}}
            <div class="pc-fundpeek">
                <span class="pc-fundpeek-fig">
                    ৳{{ number_format($dailyFund['total_spent'], 0) }}
                    <span class="sep">/</span>
                    ৳{{ number_format($dailyFund['total_fund'], 0) }}
                </span>
                <span class="pc-fundpeek-cap">{{ $fundIsToday ? 'spent today' : 'spent ' . $fundWhen }}</span>
                @if($dailyFund['over_count'])
                    <span class="pc-fundpeek-over">{{ $dailyFund['over_count'] }} over</span>
                @endif
            </div>

            {{-- Controls live in the bar but must not toggle it. --}}
            <div class="pc-fundhead-actions" onclick="event.stopPropagation()">
                <form method="GET" action="" style="display:flex; gap:8px; align-items:center;">
                    {{-- The float table's filters ride along so changing the date
                         does not silently reset them. --}}
                    @foreach(['company_id', 'custodian_id', 'status'] as $carry)
                        @if(request()->filled($carry))
                            <input type="hidden" name="{{ $carry }}" value="{{ request($carry) }}">
                        @endif
                    @endforeach
                    <input type="date" name="fund_date" value="{{ $fundDate }}" max="{{ now()->toDateString() }}"
                           onchange="this.form.submit()" title="Show another day">
                </form>
                @if($canManage)
                <button type="button" class="pc-btn fund" onclick="pcOpen('#pcFundModal')">
                    <i class="fas fa-plus"></i>Add / Edit Fund
                </button>
                @endif
            </div>
        </div>

        <div id="pcFundBody">

        <div class="pc-fundstats">
            <div class="pc-fundstat total">
                <div class="label">{{ $fundIsToday ? "Today's Total Fund" : 'Total Fund' }}</div>
                <div class="value">৳ {{ number_format($dailyFund['total_fund'], 2) }}</div>
                <div class="note">
                    Ceiling across {{ $dailyFund['rows']->where('has_fund', true)->count() }} compan{{ $dailyFund['rows']->where('has_fund', true)->count() === 1 ? 'y' : 'ies' }}
                </div>
            </div>
            <div class="pc-fundstat spent">
                <div class="label">Spent {{ $fundIsToday ? 'Today' : $fundWhen }}</div>
                <div class="value">৳ {{ number_format($dailyFund['total_spent'], 2) }}</div>
                <div class="note">Cash only — bank payments are not counted</div>
            </div>
            <div class="pc-fundstat left {{ $dailyFund['total_remaining'] < 0 ? 'negative' : '' }}">
                <div class="label">Remaining {{ $fundIsToday ? 'Today' : '' }}</div>
                <div class="value">৳ {{ number_format($dailyFund['total_remaining'], 2) }}</div>
                {{-- Said plainly, because this is the figure most likely to be
                     misread as cash in hand. It is allowance left, not taka left. --}}
                <div class="note">Allowance left to spend — not cash in hand</div>
            </div>
        </div>

        {{-- The pot that funds these ceilings lives in the card above, not here.
             A limit is only worth reading next to the cash behind it, but that
             cash is money and this card is not — keeping the figure in one place
             stops it being read as part of the allowance. When the pot cannot
             cover what is still allowed, the strip below says so. --}}
        @php
            $potShort = $summary['pot_balance'] > 0
                && $dailyFund['total_remaining'] > 0
                && $summary['pot_balance'] < $dailyFund['total_remaining'];
        @endphp
        @if($potShort)
        <div class="pc-fundshort">
            <i class="fas fa-triangle-exclamation"></i>
            <span>
                Today's remaining allowance is <strong>৳{{ number_format($dailyFund['total_remaining'], 2) }}</strong>,
                but <strong>{{ $summary['pot_name'] }}</strong> only holds
                <strong>৳{{ number_format($summary['pot_balance'], 2) }}</strong>. These limits cannot all
                be spent until it is topped up.
            </span>
        </div>
        @endif

        <div class="pc-fundlist">
            <div class="pc-fundlist-title">Company-wise Daily Fund Pool</div>
            <div class="pc-fundlist-lead">
                Click a company to see what made up its {{ $fundWhen === 'today' ? "day's" : $fundWhen }} spend.
                @if($dailyFund['over_count'])
                    <span style="color:var(--pc-danger); font-weight:600;">
                        {{ $dailyFund['over_count'] }} over its limit.
                    </span>
                @endif
            </div>

            @forelse($dailyFund['rows'] as $row)
                <div class="pc-fundrow {{ $row['state'] === 'over' ? 'is-over' : '' }} {{ $row['has_fund'] ? '' : 'is-unset' }}"
                     onclick="pcFundBreakdown({{ $row['company_id'] }})"
                     title="See the expenses behind this figure">
                    <div class="pc-fundrow-name">{{ $row['company_name'] }}</div>

                    @if($row['has_fund'])
                        <div class="pc-fundrow-track">
                            {{-- Width clamps at 100% so an overspend cannot draw
                                 past the track; the figure beside it still says
                                 the true number. --}}
                            <div class="pc-fundrow-fill {{ $row['state'] === 'over' ? 'over' : ($row['state'] === 'near' ? 'near' : 'ok') }}"
                                 style="width: {{ min($row['usage'], 100) }}%"></div>
                        </div>
                        <div class="pc-fundrow-fig">
                            <span class="spent">{{ number_format($row['spent'], 0) }}</span>
                            / {{ number_format($row['fund'], 0) }}
                        </div>
                    @else
                        <div class="pc-fundrow-unset">No daily fund set</div>
                        <div class="pc-fundrow-fig">
                            {{ $row['spent'] > 0 ? number_format($row['spent'], 0) . ' spent' : '—' }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center text-gray-400 text-sm" style="padding:18px 0;">
                    No companies to show.
                </div>
            @endforelse
        </div>
        </div>{{-- /#pcFundBody --}}
    </div>

    {{-- ── 3. THE DETAIL ────────────────────────────────────────────────────
         Who is holding what. The headline figures moved into the money card at
         the top, so this card is now the list and the filters that narrow it —
         which is all it was ever read for once the totals were known. --}}
    <div class="pc-card">

        <div class="pc-header pc-header-slim">
            <div>
                {{-- Not "Custodian Floats" any more. The table now also carries
                     people the company OWES — someone who spent their own money and
                     may never have held a float at all — so a heading about floats
                     would not describe half its rows. --}}
                <h2><i class="fas fa-users mr-2"></i>Staff Cash &amp; Claims</h2>
                <div class="subtext">
                    Both directions at once: cash of ours sitting in someone's pocket, and money of theirs we have not paid back.
                </div>
            </div>
            {{-- Reset and New Float live in the money card's header now — one
                 place for the actions, rather than a second set that looks like
                 it might do something different. --}}
        </div>

        <div class="pc-filters">
            <form method="GET" action="">
                <div class="flex flex-wrap items-end gap-3">
                    @if($companies->count() > 1)
                    <div class="pc-filter-group">
                        <label for="company_id">Company</label>
                        <select name="company_id" id="company_id" class="select2">
                            <option value="">All companies</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                    {{ $company->short_name ?: $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="pc-filter-group">
                        <label for="custodian_id">Custodian</label>
                        <select name="custodian_id" id="custodian_id" class="select2">
                            <option value="">Anyone</option>
                            {{-- $custodianFilters, not $custodians: this filters
                                 the table, so it lists people who actually hold a
                                 float — including anyone who has since resigned,
                                 whose float is still on screen and still holds
                                 real cash. The "New Float" modal below is the one
                                 restricted to active employees. --}}
                            @foreach($custodianFilters as $person)
                                <option value="{{ $person->id }}" {{ request('custodian_id') == $person->id ? 'selected' : '' }}>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pc-filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="select2">
                            <option value="">All</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Closed</option>
                        </select>
                    </div>
                    <button type="submit" class="pc-btn" style="background:var(--pc-primary); color:#fff;">
                        <i class="fas fa-filter"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="table-responsive" style="padding: 8px 12px 20px;">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Custodian</th>
                        <th class="px-4 py-3 text-left">Company</th>
                        <th class="px-4 py-3 text-right">Held Now</th>
                        <th class="px-4 py-3 text-right">Spent So Far</th>
                        {{-- The opposite direction. Kept in its own column rather than
                             netted against Held, because one is an asset (1015) and the
                             other a liability (2240) — a single combined figure would
                             appear nowhere in the accounts. --}}
                        <th class="px-4 py-3 text-right">Owed Back</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($floats as $float)
                    @php
                        $held  = $balances[$float->id] ?? 0;
                        $spent = $spentByFloat[$float->id] ?? 0;
                        // Owed to this person in THIS company's books. Keyed by both,
                        // because a claim filed under one company is settled from that
                        // company's cash and the two must never be added together.
                        $owed  = $owedByPerson[$float->custodian_id . '|' . $float->company_id] ?? 0;

                        // Two states, because there are only two: money is with this
                        // person or it is not. There is no limit to be under or over
                        // — the office hands over what the day needs.
                        if (!$float->status)    { $pill = ['closed', 'Closed']; }
                        elseif ($held <= 0.001) { $pill = ['empty', 'Nothing held']; }
                        else                    { $pill = ['ok',    'Holding cash']; }
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            {{-- The name is the way in. The statement icon in Actions
                                 stays, but nobody looks for a page behind an unlabelled
                                 square — the thing you actually want to click is the
                                 person whose money you are reading about. --}}
                            <a href="{{ route('role.petty-cash.statement', ['role' => $role, 'float' => $float->id]) }}"
                               class="pc-name-link" title="Open {{ $float->custodian->name ?? 'this custodian' }}'s transactions">
                                {{ $float->custodian->name ?? '—' }}<i class="fas fa-arrow-right"></i>
                            </a>
                            @if($float->note)
                                <div class="text-xs text-gray-400">{{ $float->note }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $float->company->short_name ?: $float->company->name }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="font-bold text-sm" style="color:{{ $held > 0 ? 'var(--pc-ink)' : 'var(--pc-muted)' }}">
                                ৳ {{ number_format($held, 2) }}
                            </div>
                        </td>
                        {{-- What has been accounted for out of this pocket. Held and
                             spent together are the whole story: the rest is still
                             with them. --}}
                        <td class="px-4 py-3 text-right text-sm {{ $spent > 0 ? 'text-gray-700' : 'text-gray-400' }}">
                            {{ $spent > 0 ? '৳ ' . number_format($spent, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($owed > 0.001)
                                <span class="pc-owed">৳ {{ number_format($owed, 2) }}</span>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="pc-pill {{ $pill[0] }}">{{ $pill[1] }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div style="display:flex; gap:6px; justify-content:center;">
                                <a href="{{ route('role.petty-cash.statement', ['role' => $role, 'float' => $float->id]) }}"
                                   class="pc-act" title="Statement"><i class="fas fa-list"></i></a>
                                @if($canManage && $float->status)
                                {{-- Named, not a bare arrow. Issuing is the action that
                                     actually moves money, and an unlabelled icon is not
                                     something you find when you are looking for it. --}}
                                <button type="button" class="pc-act issue labelled" title="Hand cash to this custodian"
                                        onclick="pcMove({{ $float->id }}, 'issue', '{{ addslashes($float->custodian->name ?? '') }}', 0, {{ (int) $float->company_id }})">
                                    <i class="fas fa-arrow-right"></i><span>Issue</span>
                                </button>
                                @if($held > 0)
                                <button type="button" class="pc-act give labelled" title="Take cash back"
                                        onclick="pcMove({{ $float->id }}, 'return', '{{ addslashes($float->custodian->name ?? '') }}', {{ $held }}, {{ (int) $float->company_id }})">
                                    <i class="fas fa-arrow-left"></i><span>Take back</span>
                                </button>
                                @endif
                                @endif
                                {{-- The opposite of Take back: that collects our cash,
                                     this returns theirs. Only when something is actually
                                     owed, so the row does not offer a payment of nothing. --}}
                                @if($canReimburse && $owed > 0.001)
                                <button type="button" class="pc-act payback labelled" title="Pay back what they are owed"
                                        onclick="pcPayBack({{ $float->custodian_id }}, '{{ addslashes($float->custodian->name ?? '') }}', {{ (int) $float->company_id }}, '{{ addslashes($float->company->short_name ?: $float->company->name) }}', {{ number_format($owed, 2, '.', '') }})">
                                    <i class="fas fa-hand-holding-dollar"></i><span>Pay back</span>
                                </button>
                                @endif
                                @if($canManage)
                                <button type="button" class="pc-act" title="Edit"
                                        onclick="pcEdit({{ $float->id }}, '{{ addslashes($float->note ?? '') }}', {{ $float->status ? 1 : 0 }})">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="pc-act danger" title="Delete"
                                        onclick="pcDelete({{ $float->id }}, '{{ addslashes($float->custodian->name ?? '') }}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    @if($claimOnly->isEmpty())
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">
                            <i class="fas fa-wallet block mb-2" style="font-size:26px; opacity:.4;"></i>
                            Nobody is holding company cash, and nobody is owed any.
                        </td>
                    </tr>
                    @endif
                @endforelse

                {{-- People the company owes who hold no float.
                     They were never issued cash — they simply paid for something
                     themselves — so there is no float row for them to appear in.
                     A table built only from floats would leave them off the screen
                     entirely, which is how someone ends up unpaid for two months. --}}
                @foreach($claimOnly as $person)
                    <tr>
                        <td class="px-4 py-3">
                            {{-- No float means no float statement, so this name goes
                                 to the expense list filtered to them instead. Same
                                 promise as the rows above — click the person, see what
                                 they spent — answered from the only place that has it. --}}
                            <a href="{{ route('role.expenses.index', ['role' => $role]) }}?user_id={{ $person->user_id }}"
                               class="pc-name-link" title="See what {{ $person->user_name }} has spent">
                                {{ $person->user_name }}<i class="fas fa-arrow-right"></i>
                            </a>
                            <div class="text-xs text-gray-400">Paid from their own pocket</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $person->company_name }}</td>
                        <td class="px-4 py-3 text-right text-sm text-gray-400">—</td>
                        <td class="px-4 py-3 text-right text-sm text-gray-400">—</td>
                        <td class="px-4 py-3 text-right"><span class="pc-owed">৳ {{ number_format($person->owed, 2) }}</span></td>
                        <td class="px-4 py-3"><span class="pc-pill owed">Owed money</span></td>
                        <td class="px-4 py-3">
                            <div style="display:flex; gap:6px; justify-content:center;">
                                @if($canReimburse)
                                <button type="button" class="pc-act payback labelled" title="Pay back what they are owed"
                                        onclick="pcPayBack({{ $person->user_id }}, '{{ addslashes($person->user_name) }}', {{ (int) $person->company_id }}, '{{ addslashes($person->company_name) }}', {{ number_format($person->owed, 2, '.', '') }})">
                                    <i class="fas fa-hand-holding-dollar"></i><span>Pay back</span>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- What has already been handed back.
             Here rather than on a page of its own, because the only reason to look
             at it is to check or undo a payment made from the table above — and a
             mistake with no visible way back is how a person ends up paid twice. --}}
        @if($reimbursementPayments->isNotEmpty())
        <div class="pc-header pc-header-slim" style="border-top:1px solid var(--pc-border); margin-top:4px;">
            <div>
                <h2 style="font-size:15px"><i class="fas fa-receipt mr-2"></i>Recently Paid Back</h2>
                <div class="subtext">The last {{ $reimbursementPayments->count() }} settled. Reversing one puts the amount back on the list above.</div>
            </div>
        </div>
        <div class="table-responsive" style="padding: 8px 12px 20px;">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Paid on</th>
                        <th class="px-4 py-3 text-left">To</th>
                        <th class="px-4 py-3 text-left">Company</th>
                        <th class="px-4 py-3 text-left">From</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-left">Note</th>
                        @if($canReimburse)
                        <th class="px-4 py-3 text-center">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @foreach($reimbursementPayments as $payment)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $payment->paid_on?->format('Y-m-d') }}
                            <div class="text-xs text-gray-400">by {{ $payment->creator?->name ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm font-semibold text-gray-800">{{ $payment->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $payment->company?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $payment->paid_from }}</td>
                        <td class="px-4 py-3 text-right text-sm font-bold" style="color:var(--pc-ink)">৳ {{ number_format($payment->amount, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $payment->note ?: '—' }}</td>
                        @if($canReimburse)
                        <td class="px-4 py-3">
                            <div style="display:flex; gap:6px; justify-content:center;">
                                <button type="button" class="pc-act reverse" title="Reverse — posts the opposite entry, keeps both on record"
                                        onclick="pcReversePay('{{ route('role.employee-reimbursements.reverse', ['role' => $role, 'reimbursement' => $payment->id]) }}', '{{ addslashes($payment->user?->name ?? '') }}', '{{ number_format($payment->amount, 2) }}')">
                                    <i class="fas fa-rotate-left"></i>
                                </button>
                            </div>
                        </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- ── Set the daily cost fund ──
     Every company on one form, saved in one transaction. Twelve separate saves
     would leave the set half-applied the first time one of them failed. --}}
@if($canManage)
<div class="pc-modal" id="pcFundModal">
    <div class="pc-modal-box wide">
        <div class="pc-modal-head">
            <h3><i class="fas fa-gauge-high mr-2"></i>Daily Cost Fund</h3>
            <button type="button" onclick="pcClose('#pcFundModal')" style="color:#fff;background:none;border:none;font-size:18px;">&times;</button>
        </div>
        <form id="pcFundForm" action="{{ route('role.petty-cash.daily-fund.save', ['role' => $role]) }}" method="POST">
            @csrf
            <div class="pc-modal-body">
                <div class="pc-fundnote">
                    <strong>This moves no money.</strong> It sets how much each company may spend
                    in cash per day — no journal entry is written and no balance changes.
                    Cash itself is handed over on the Petty Cash card below.
                </div>

                <div class="pc-field">
                    <label>In force from <span style="color:var(--pc-danger)">*</span></label>
                    <input type="date" name="effective_from" value="{{ $fundDate }}" required>
                    <div class="pc-hint">
                        The figure stands from this date until you change it again — there is nothing
                        to re-enter tomorrow. Earlier days keep whatever limit they had, so past
                        reports stay correct.
                    </div>
                </div>

                <table class="pc-fundedit">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Daily limit (৳)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dailyFund['rows'] as $row)
                            <tr>
                                <td>{{ $row['company_name'] }}</td>
                                <td>
                                    {{-- Left blank means "no limit set", which is not the
                                         same as a limit of zero — zero would forbid all
                                         cash spending. --}}
                                    <input type="number" step="0.01" min="0" placeholder="none"
                                           name="funds[{{ $row['company_id'] }}]"
                                           value="{{ $row['has_fund'] ? number_format($row['fund'], 2, '.', '') : '' }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total per day</td>
                            <td id="pcFundTotal">৳ 0.00</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="pc-hint" style="margin-top:-4px;">
                    Clear a box to remove that company's limit altogether. A limit of <strong>0</strong>
                    means no cash spending is allowed at all.
                </div>

                <div class="pc-field">
                    <label>Note</label>
                    <input type="text" name="note" maxlength="255" placeholder="e.g. raised for Ramadan">
                </div>
            </div>
            <div class="pc-modal-foot">
                <button type="button" class="pc-btn cancel" onclick="pcClose('#pcFundModal')">Cancel</button>
                <button type="submit" class="pc-btn save">Save Funds</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ── What made up a day's spend ── --}}
<div class="pc-modal" id="pcBreakdownModal">
    <div class="pc-modal-box wide">
        <div class="pc-modal-head">
            <h3 id="pcBreakdownTitle">Spend breakdown</h3>
            <button type="button" onclick="pcClose('#pcBreakdownModal')" style="color:#fff;background:none;border:none;font-size:18px;">&times;</button>
        </div>
        <div class="pc-modal-body" id="pcBreakdownBody">
            <div class="text-center text-gray-400 text-sm" style="padding:24px 0;">Loading…</div>
        </div>
        <div class="pc-modal-foot">
            <button type="button" class="pc-btn cancel" onclick="pcClose('#pcBreakdownModal')">Close</button>
        </div>
    </div>
</div>

{{-- ── New float ── --}}
<div class="pc-modal" id="pcCreateModal">
    <div class="pc-modal-box">
        <div class="pc-modal-head">
            <h3><i class="fas fa-wallet mr-2"></i>New Petty Cash Float</h3>
            <button type="button" onclick="pcClose('#pcCreateModal')" style="color:#fff;background:none;border:none;font-size:18px;">&times;</button>
        </div>
        <form id="pcCreateForm" action="{{ route('role.petty-cash.store', ['role' => $role]) }}" method="POST">
            @csrf
            <div class="pc-modal-body">
                {{-- Custodian first, company second — the reverse of how this
                     read before. Every employee is already assigned to a company,
                     so asking for the company first asked a question the next
                     answer settles, and let the two disagree in between. Picking
                     the person now fills the company in. Same idea as the expense
                     form, where choosing a department fills its company
                     (clsSetCompany in expenses/partials/classification-js). --}}
                <div class="pc-field">
                    <label>Custodian <span style="color:var(--pc-danger)">*</span></label>
                    <select name="custodian_id" id="pcCreateCustodian" class="pc-select" required>
                        <option value="">Who will hold the cash?</option>
                        @foreach($custodians as $person)
                            <option value="{{ $person->id }}" data-company="{{ $person->company_id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if($companies->count() > 1)
                <div class="pc-field">
                    <label>Company <span style="color:var(--pc-danger)">*</span></label>
                    <select name="company_id" id="pcCreateCompany" class="pc-select" required>
                        <option value="">Select company</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->short_name ?: $company->name }}</option>
                        @endforeach
                    </select>
                    {{-- Left editable on purpose. The float's company decides which
                         set of books the cash sits in, and that is not always the
                         custodian's own — a group employee can hold a subsidiary's
                         float. The hint says where the value came from so an
                         override is a decision rather than an accident. --}}
                    <div class="pc-hint" id="pcCreateCompanyHint" hidden></div>
                </div>
                @endif
                <div class="pc-field">
                    <label>Note</label>
                    <input type="text" name="note" maxlength="255" placeholder="e.g. 12th floor daily costs">
                </div>

                {{-- The opening hand-over. Optional, because a float can be opened
                     today and funded on Monday — but offered here because the limit
                     above moves no money, and a custodian holding 0.00 with no
                     explanation is the thing people trip over. --}}
                <div class="pc-openbox">
                    <div class="pc-openbox-head">
                        <label class="pc-openbox-toggle">
                            <input type="checkbox" id="pcOpeningToggle">
                            <span><i class="fas fa-hand-holding-dollar mr-1.5"></i>Hand cash over now</span>
                        </label>
                        <span class="pc-openbox-tag">optional</span>
                    </div>
                    <p class="pc-openbox-lead">
                        This is the step that actually moves money: it leaves the source below and
                        lands on the custodian's float. It is not an expense — the cash is still the
                        company's until a receipt says what it bought.
                    </p>
                    <div id="pcOpeningFields" class="pc-openbox-fields" hidden>
                        <div class="pc-field">
                            <label>Amount handed over</label>
                            <input type="number" name="opening_amount" id="pcOpeningAmount" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="pc-field">
                            <label>Money comes from</label>
                            <select name="bank_id" id="pcOpeningBank" class="pc-select">
                                {{-- Blank = the pot, named from the chart rather than
                             hardcoded: which account that is comes from
                             config/accounts.php, and the label must not claim
                             one account while the posting credits another. --}}
                        <option value="">{{ $summary['pot_name'] }} (the pot)</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}" data-company="{{ $bank->company_id }}">{{ $bank->name }}</option>
                                @endforeach
                            </select>
                            <div class="pc-hint" id="pcOpeningPosting">
                                Posts <strong>Petty Cash Float</strong> up, <strong>{{ $summary['pot_name'] }}</strong> down.
                            </div>
                        </div>
                        <div class="pc-field">
                            <label>Date handed over</label>
                            <input type="date" name="opening_date" id="pcOpeningDate" value="{{ date('Y-m-d') }}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="pc-modal-foot">
                <button type="button" class="pc-btn cancel" onclick="pcClose('#pcCreateModal')">Cancel</button>
                <button type="submit" class="pc-btn save">Create Float</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Issue / return ── --}}
<div class="pc-modal" id="pcMoveModal">
    <div class="pc-modal-box">
        <div class="pc-modal-head">
            <h3 id="pcMoveTitle">Issue Cash</h3>
            <button type="button" onclick="pcClose('#pcMoveModal')" style="color:#fff;background:none;border:none;font-size:18px;">&times;</button>
        </div>
        <form id="pcMoveForm" method="POST">
            @csrf
            <div class="pc-modal-body">
                <p id="pcMoveLead" class="text-sm text-gray-600"></p>
                <div class="pc-field">
                    <label>Amount <span style="color:var(--pc-danger)">*</span></label>
                    <input type="number" name="amount" id="pcMoveAmount" step="0.01" min="0.01" required>
                    <div class="pc-hint" id="pcMoveHint"></div>
                </div>
                <div class="pc-field">
                    <label>Date <span style="color:var(--pc-danger)">*</span></label>
                    <input type="date" name="date" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="pc-field">
                    <label id="pcMoveSourceLabel">Money comes from</label>
                    <select name="bank_id" id="pcMoveBank" class="pc-select">
                        {{-- Blank = the pot, named from the chart rather than
                             hardcoded: which account that is comes from
                             config/accounts.php, and the label must not claim
                             one account while the posting credits another. --}}
                        <option value="">{{ $summary['pot_name'] }} (the pot)</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}" data-company="{{ $bank->company_id }}">{{ $bank->name }}</option>
                        @endforeach
                    </select>
                    <div class="pc-hint">No expense account is touched — the money only changes hands.</div>
                </div>
                <div class="pc-field">
                    <label>Note</label>
                    <input type="text" name="note" maxlength="255">
                </div>
            </div>
            <div class="pc-modal-foot">
                <button type="button" class="pc-btn cancel" onclick="pcClose('#pcMoveModal')">Cancel</button>
                <button type="submit" class="pc-btn save" id="pcMoveSubmit">Confirm</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Edit ── --}}
<div class="pc-modal" id="pcEditModal">
    <div class="pc-modal-box">
        <div class="pc-modal-head">
            <h3><i class="fas fa-pen mr-2"></i>Edit Float</h3>
            <button type="button" onclick="pcClose('#pcEditModal')" style="color:#fff;background:none;border:none;font-size:18px;">&times;</button>
        </div>
        <form id="pcEditForm" method="POST">
            @csrf
            @method('PUT')
            <div class="pc-modal-body">
                <div class="pc-field">
                    <label>Note</label>
                    <input type="text" name="note" id="pcEditNote" maxlength="255">
                </div>
                <div class="pc-field">
                    <label>Status</label>
                    <select name="status" id="pcEditStatus" class="pc-select">
                        <option value="1">Active</option>
                        <option value="0">Closed</option>
                    </select>
                    <div class="pc-hint">A float still holding cash cannot be closed — take the balance back first.</div>
                </div>
            </div>
            <div class="pc-modal-foot">
                <button type="button" class="pc-btn cancel" onclick="pcClose('#pcEditModal')">Cancel</button>
                <button type="submit" class="pc-btn save">Save</button>
            </div>
        </form>
    </div>
</div>

@if($canReimburse)
{{-- ── Pay back ──
     The mirror of Issue: that hands company cash out, this returns money that was
     never the company's. Same shape as the move modal so the desk reads the two
     the same way. --}}
<div class="pc-modal" id="pcPayModal">
    <div class="pc-modal-box">
        <div class="pc-modal-head">
            <h3><i class="fas fa-hand-holding-dollar mr-2"></i>Pay Back</h3>
            <button type="button" onclick="pcClose('#pcPayModal')" style="color:#fff;background:none;border:none;font-size:18px;">&times;</button>
        </div>
        <form id="pcPayForm" method="POST" action="{{ route('role.employee-reimbursements.store', ['role' => $role]) }}">
            @csrf
            <input type="hidden" name="user_id" id="pcPayUser">
            <input type="hidden" name="company_id" id="pcPayCompany">
            <div class="pc-modal-body">
                <p id="pcPayLead" class="text-sm text-gray-600"></p>
                <div class="pc-field">
                    <label>Amount <span style="color:var(--pc-danger)">*</span></label>
                    <input type="number" name="amount" id="pcPayAmount" step="0.01" min="0.01" required>
                    <div class="pc-hint" id="pcPayHint"></div>
                </div>
                <div class="pc-field">
                    <label>Date <span style="color:var(--pc-danger)">*</span></label>
                    <input type="date" name="paid_on" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="pc-field">
                    <label>Money comes from</label>
                    <select name="bank_id" id="pcPayBank" class="pc-select">
                        <option value="">{{ $summary['pot_name'] }} (the pot)</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}" data-company="{{ $bank->company_id }}">{{ $bank->name }}</option>
                        @endforeach
                    </select>
                    <div class="pc-hint">No expense account is touched — this settles a debt that was recorded when the claim was approved.</div>
                </div>
                <div class="pc-field">
                    <label>Note</label>
                    <input type="text" name="note" maxlength="255">
                </div>
            </div>
            <div class="pc-modal-foot">
                <button type="button" class="pc-btn cancel" onclick="pcClose('#pcPayModal')">Cancel</button>
                <button type="submit" class="pc-btn save">Record payment</button>
            </div>
        </form>
    </div>
</div>

<form id="pcReverseForm" method="POST" style="display:none">@csrf</form>
@endif
@endsection

@section('js')
{{-- jQuery FIRST. select2 is a jQuery plugin and every form on this page submits
     through $.ajax, so without this the page loads, the buttons look fine, and
     nothing saves — "$ is not defined" on submit. The layout does not provide
     jQuery globally (it lazy-loads its own copy for the quick-task widget only),
     so each page that needs it loads it, same as the other expense screens.
     SweetAlert2 IS global, from layout/app.blade.php. --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    'use strict';

    const ROLE = @json($role);
    const BASE = @json(url('/')) + '/' + ROLE + '/petty-cash';

    // The day the fund panel is showing — the breakdown has to ask about the
    // same date, or clicking a bar for last Tuesday would list today's receipts.
    const FUND_DATE = @json($fundDate);

    // The account an issue with no bank named actually debits out of. Named from
    // the chart so the posting hint cannot go on asserting "Office Cash" after
    // config/accounts.php has moved the pot somewhere else.
    const CASH_POT = @json($summary['pot_name']);

    window.pcOpen  = (sel) => document.querySelector(sel).classList.add('show');
    window.pcClose = (sel) => document.querySelector(sel).classList.remove('show');

    /* Issue and return share a modal because only the direction and the wording
       differ; two near-identical forms would drift apart the first time one was
       edited. */
    window.pcMove = function (id, type, name, suggested, companyId) {
        const issuing = type === 'issue';

        document.getElementById('pcMoveTitle').innerHTML =
            '<i class="fas fa-' + (issuing ? 'arrow-right' : 'arrow-left') + ' mr-2"></i>'
            + (issuing ? 'Issue Cash' : 'Take Cash Back');

        document.getElementById('pcMoveLead').textContent = issuing
            ? 'Handing cash to ' + name + '. This is not an expense — it becomes one when a receipt says what it bought.'
            : name + ' is handing cash back. Their balance falls by this amount.';

        document.getElementById('pcMoveSourceLabel').textContent = issuing ? 'Money comes from' : 'Money goes back to';

        const hint = document.getElementById('pcMoveHint');
        if (!issuing) {
            hint.textContent = name + ' is holding ' + Number(suggested).toFixed(2) + ' — you cannot take back more than that.';
        } else {
            hint.textContent = '';
        }

        const amount = document.getElementById('pcMoveAmount');
        // Nothing is pre-filled on an issue: the office hands over what the
        // day needs, and there is no limit to compute a figure from.
        amount.value = issuing ? '' : Number(suggested).toFixed(2);
        amount.max   = issuing ? '' : Number(suggested).toFixed(2);

        const submit = document.getElementById('pcMoveSubmit');
        submit.textContent = issuing ? 'Issue Cash' : 'Take Back';
        submit.classList.toggle('warn', !issuing);

        // The server only accepts a bank belonging to THIS float's company.
        filterBanks('#pcMoveBank', companyId);

        document.getElementById('pcMoveForm').action = BASE + '/' + id + '/' + (issuing ? 'issue' : 'return');
        pcOpen('#pcMoveModal');
    };

    /* Paying someone back. Deliberately NOT folded into pcMove(): that one moves
       the company's cash between its own pockets, this one settles a debt on the
       payable account. Same-looking modal, different ledger, different route. */
    window.pcPayBack = function (userId, name, companyId, companyName, owed) {
        document.getElementById('pcPayUser').value    = userId;
        document.getElementById('pcPayCompany').value = companyId;

        document.getElementById('pcPayLead').textContent =
            name + ' paid for company things out of their own pocket in ' + companyName
            + '. This hands that money back — no expense is recorded, the cost was booked when the claim was approved.';

        document.getElementById('pcPayHint').textContent =
            name + ' is owed ' + Number(owed).toFixed(2) + ' — you cannot pay back more than that.';

        // Pre-filled with the whole balance, since settling in full is the usual
        // case, but editable: part payments leave the rest owed.
        const amount = document.getElementById('pcPayAmount');
        amount.value = Number(owed).toFixed(2);
        amount.max   = Number(owed).toFixed(2);

        // A claim filed under one company is settled from that company's cash.
        filterBanks('#pcPayBank', companyId);

        pcOpen('#pcPayModal');
    };

    window.pcReversePay = function (url, name, amount) {
        const go = () => {
            const form = document.getElementById('pcReverseForm');
            form.action = url;
            form.submit();
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Reverse this payment?',
                html: '৳' + amount + ' paid to <strong>' + name + '</strong>.<br>'
                    + '<span style="font-size:12px;color:#6b7280">Both the payment and its reversal stay on the ledger, '
                    + 'and the amount goes back to being owed.</span>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, reverse it',
                confirmButtonColor: '#ea580c',
                reverseButtons: true,
            }).then(r => { if (r.isConfirmed) go(); });
        } else if (confirm('Reverse the ৳' + amount + ' paid to ' + name + '?')) {
            go();
        }
    };

    window.pcEdit = function (id, note, status) {
        document.getElementById('pcEditNote').value = note || '';
        document.getElementById('pcEditStatus').value = status ? '1' : '0';
        document.getElementById('pcEditForm').action = BASE + '/' + id;
        pcOpen('#pcEditModal');
    };

    window.pcDelete = function (id, name) {
        Swal.fire({
            icon: 'warning',
            title: 'Delete this float?',
            text: name + "'s petty cash float will be removed. Only possible when nothing is held.",
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#dc2626',
        }).then((r) => {
            if (!r.isConfirmed) return;

            $.ajax({
                url: BASE + '/' + id,
                method: 'POST',
                data: { _method: 'DELETE', _token: '{{ csrf_token() }}' },
                success: (res) => report(res),
                error: (xhr) => reportError(xhr),
            });
        });
    };

    const money = (n) => '৳ ' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    /* Daily Cost Fund open/shut.
       Remembered, because whether this section is worth reading is a property of
       the person, not of the visit: someone who sets limits once a month wants
       it shut every time, and re-opening it on every page load would be the
       screen forgetting what it was told. Kept in localStorage rather than a
       preference column — it is a display state, not data about the business.

       Falls back silently: a browser with storage blocked simply opens the
       section every time, which is the old behaviour and harms nothing. */
    (function () {
        const card   = document.getElementById('pcFundCard');
        const toggle = document.getElementById('pcFundToggle');
        if (!card || !toggle) return;

        const KEY = 'pc.dailyFund.collapsed';

        const read = () => { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } };
        const write = (v) => { try { localStorage.setItem(KEY, v ? '1' : '0'); } catch (e) {} };

        function apply(collapsed) {
            card.classList.toggle('is-collapsed', collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }

        apply(read());

        function flip() {
            const next = !card.classList.contains('is-collapsed');
            apply(next);
            write(next);
        }

        toggle.addEventListener('click', flip);

        // Keyboard: the bar is a button in all but name, so it answers like one.
        toggle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                flip();
            }
        });
    })();

    /* The set-funds table totals itself as you type, because "what am I
       committing across all twelve companies" is the question the form exists to
       answer and adding it up by eye is how a digit gets missed. */
    window.pcFundTotal = function () {
        const cell = document.getElementById('pcFundTotal');
        if (!cell) return;

        let total = 0;
        document.querySelectorAll('#pcFundForm input[name^="funds["]').forEach((input) => {
            const v = parseFloat(input.value);
            if (!isNaN(v)) total += v;
        });

        cell.textContent = money(total);
    };

    /* The expenses behind one bar. Fetched rather than rendered up front: twelve
       companies' worth of receipts on a page nobody has clicked into yet is a lot
       of markup for a panel that is mostly read as three numbers. */
    window.pcFundBreakdown = function (companyId) {
        const body  = document.getElementById('pcBreakdownBody');
        const title = document.getElementById('pcBreakdownTitle');

        title.textContent = 'Spend breakdown';
        body.innerHTML = '<div class="text-center text-gray-400 text-sm" style="padding:24px 0;">Loading…</div>';
        pcOpen('#pcBreakdownModal');

        $.ajax({
            url: BASE + '/daily-fund/' + companyId + '/breakdown',
            method: 'GET',
            data: { fund_date: FUND_DATE },
            success: function (res) {
                if (!res || !res.success) {
                    body.innerHTML = '<div class="text-center text-gray-400 text-sm" style="padding:24px 0;">Nothing to show.</div>';
                    return;
                }

                title.textContent = res.company + ' — ' + res.date;

                const esc = (s) => $('<div>').text(s == null ? '' : s).html();

                let head = '<div class="pc-fundnote" style="margin-bottom:14px;">';
                if (res.has_fund) {
                    const left = res.fund - res.spent;
                    head += 'Limit <strong>' + money(res.fund) + '</strong> · spent <strong>' + money(res.spent) + '</strong> · '
                        + (left < 0
                            ? '<strong style="color:#dc2626;">' + money(Math.abs(left)) + ' over</strong>'
                            : '<strong>' + money(left) + '</strong> left');
                } else {
                    head += 'No daily limit set for this company. Spent <strong>' + money(res.spent) + '</strong>.';
                }
                head += '</div>';

                if (!res.rows.length) {
                    body.innerHTML = head + '<div class="text-center text-gray-400 text-sm" style="padding:18px 0;">'
                        + 'No cash expenses on this date.</div>';
                    return;
                }

                let rows = '';
                res.rows.forEach((r) => {
                    rows += '<tr>'
                        + '<td><div>' + esc(r.title) + '</div>'
                        + (r.category ? '<div class="pc-bd-sub">' + esc(r.category) + '</div>' : '')
                        + '</td>'
                        + '<td>' + esc(r.source)
                        + (r.by ? '<div class="pc-bd-sub">by ' + esc(r.by) + '</div>' : '')
                        + '</td>'
                        + '<td>' + money(r.amount) + '</td>'
                        + '</tr>';
                });

                body.innerHTML = head
                    + '<table class="pc-bd"><thead><tr><th>Expense</th><th>Paid from</th><th>Amount</th></tr></thead>'
                    + '<tbody>' + rows + '</tbody>'
                    + '<tfoot><tr><td colspan="2">Total</td><td>' + money(res.spent) + '</td></tr></tfoot>'
                    + '</table>';
            },
            error: function () {
                body.innerHTML = '<div class="text-center text-red-500 text-sm" style="padding:24px 0;">Could not load the breakdown.</div>';
            },
        });
    };

    /* One submit handler for every form here — they all post, all return the same
       shape, and all want the page back afterwards so the ledger-derived balances
       are re-read rather than guessed at in the browser. */
    document.querySelectorAll('#pcCreateForm, #pcMoveForm, #pcEditForm, #pcFundForm').forEach((form) => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            $.ajax({
                url: form.action,
                method: 'POST',
                data: $(form).serialize(),
                success: (res) => report(res),
                error: (xhr) => reportError(xhr),
            });
        });
    });

    function report(res) {
        if (res && res.success) {
            Swal.fire({ icon: 'success', title: 'Done', text: res.message, timer: 1400, showConfirmButton: false });
            setTimeout(() => window.location.reload(), 700);
            return;
        }
        Swal.fire({ icon: 'error', title: 'Not saved', text: (res && res.message) || 'Something went wrong.' });
    }

    // Laravel throws 422 with the real reason on a failed validate(); showing the
    // generic message instead would hide "only 320.00 is held on this float".
    function reportError(xhr) {
        const body = xhr.responseJSON || {};
        const firstError = body.errors ? Object.values(body.errors)[0][0] : null;

        Swal.fire({
            icon: 'error',
            title: 'Not saved',
            text: firstError || body.message || 'Request failed.',
        });
    }

    $(function () {
        /* Every dropdown on this desk is searchable — custodian and bank lists are
           the ones that grow, and hunting for a name in a native <select> is the
           slow part of issuing cash.

           minimumResultsForSearch: 5 keeps a search box off two-option lists like
           Status, where it would only add a click. */
        $('.select2').select2({ width: '100%', minimumResultsForSearch: 5 });

        /* Modal dropdowns render INTO their modal box. .pc-modal is a fixed
           overlay at z-index 1050; select2 appends to <body> by default, which puts
           the list behind the overlay. */
        $('.pc-select').each(function () {
            const $el = $(this);
            $el.select2({
                width: '100%',
                minimumResultsForSearch: 5,
                dropdownParent: $el.closest('.pc-modal-box'),
            });
        });

        // Keep the set-funds footer honest while typing, and show the current
        // total the moment the modal is opened rather than after the first keypress.
        $('#pcFundForm').on('input', 'input[name^="funds["]', window.pcFundTotal);
        window.pcFundTotal();

        /* Opening hand-over: hidden until asked for, and cleared when unchecked so
           an amount typed and then thought better of is not posted anyway. */
        const $toggle = $('#pcOpeningToggle');
        const fields  = document.getElementById('pcOpeningFields');

        $toggle.on('change', function () {
            fields.hidden = !this.checked;

            if (this.checked) {
                document.getElementById('pcOpeningAmount').focus();
            } else {
                document.getElementById('pcOpeningAmount').value = '';
                $('#pcOpeningBank').val('').trigger('change');
            }
        });

        // Name the account the money will actually leave, rather than making the
        // user trust that "Office cash" means something.
        $('#pcOpeningBank').on('change', function () {
            const label = $(this).find('option:selected').text().trim();
            document.getElementById('pcOpeningPosting').innerHTML = this.value
                ? 'Posts <strong>Petty Cash Float</strong> up, <strong>' + $('<div>').text(label).html() + '</strong> down.'
                : 'Posts <strong>Petty Cash Float</strong> up, <strong>' + $('<div>').text(CASH_POT).html() + '</strong> down.';
        });

        // Banks belong to a company; offering another company's would only produce
        // a validation error after the click.
        const $company = $('#pcCreateForm select[name="company_id"]');
        const applyCompany = () => filterBanks('#pcOpeningBank', $company.length ? $company.val() : null);

        $company.on('change', applyCompany);
        applyCompany();

        // ── Custodian fills the company ──────────────────────────────────
        // Every employee carries a company_id, so the person answers the
        // question. Fires the company's own change event rather than setting
        // .val() alone: select2 renders from that event, and the bank list is
        // narrowed by it — skip it and the source dropdown keeps offering the
        // previous company's banks.
        const $custodian = $('#pcCreateCustodian');
        const hint = document.getElementById('pcCreateCompanyHint');

        const showHint = (text) => {
            if (!hint) return;
            hint.textContent = text || '';
            hint.hidden = !text;
        };

        $custodian.on('change', function () {
            if (!$company.length) return;   // Company-locked user: no select to fill.

            const companyId = $(this).find('option:selected').data('company');
            const person = $(this).find('option:selected').text().trim();

            if (!companyId) {
                // No employee currently lacks a company, but a new record could:
                // leave whatever is chosen and ask, rather than blanking it.
                showHint(person ? 'No company is recorded against ' + person + ' — choose one.' : '');
                return;
            }

            if (String($company.val()) !== String(companyId)) {
                $company.val(String(companyId)).trigger('change');
                flashField($company);
            }

            showHint(person + ' is assigned to this company. Change it only if the float belongs to another.');
        });
    });

    /* Briefly ring a field that was filled for the user, so a value appearing on
       its own is noticed. Same 900ms cue as the expense form's classification. */
    function flashField($node) {
        const el = $node[0];
        if (!el) return;
        const box = el.nextElementSibling && el.nextElementSibling.classList.contains('select2')
            ? el.nextElementSibling : el;
        box.style.transition = 'box-shadow .2s';
        box.style.boxShadow = '0 0 0 2px #94a3b8';
        setTimeout(() => { box.style.boxShadow = ''; }, 900);
    }

    /*
     * Narrow a bank picker to one company.
     *
     * Options are rebuilt rather than hidden: select2 reads the <option> elements
     * and ignores the `hidden` attribute, so a hidden option still shows up in its
     * dropdown. The full list is cached on first call so switching company can
     * restore it.
     */
    function filterBanks(selector, companyId) {
        const $select = $(selector);
        if (!$select.length) return;

        let all = $select.data('pcAllOptions');

        if (!all) {
            all = $select.find('option').toArray().map((o) => ({
                value: o.value,
                label: o.textContent,
                company: o.getAttribute('data-company'),
            }));
            $select.data('pcAllOptions', all);
        }

        const wanted = companyId ? String(companyId) : null;
        const previous = $select.val();

        $select.empty();

        all.forEach((o) => {
            // The blank option is office cash — always a valid source.
            if (o.value && wanted && String(o.company) !== wanted) return;
            $select.append($('<option>').val(o.value).text(o.label).attr('data-company', o.company));
        });

        // Keep the choice only if it survived the filter.
        $select.val($select.find('option[value="' + previous + '"]').length ? previous : '');
        $select.trigger('change');
    }
})();
</script>
@endsection
