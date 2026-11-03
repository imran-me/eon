{{-- Flights & Passengers' own sheet in the file module (owner, 2026-10-25:
     "match the contract file with the contract flight"). Copied rule for rule
     from contract-flights/index.blade.php (filter bar, KPI cards) and
     partials/passenger-journey-assets.blade.php (journey row, CSS only — its
     script posts to the FLIGHT stage endpoint and is not loaded here). The
     table itself and the journey card are the flight's own partials, included
     as they are, so a change there reaches this module too. --}}
@once
<style>
    /* Filter bar — the Flight Dashboard's filter strip, under this page's prefix. */
    .mfp-filter {
        display: grid; grid-template-columns: minmax(200px, 1.4fr) 150px 150px max-content 118px 118px max-content;
        align-items: end; gap: 10px; padding: 10px 12px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
    }
    .mfp-filter-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
    .mfp-filter label { color: #475569; font-size: 11px; font-weight: 600; margin: 0; }
    .mfp-filter input, .mfp-filter select {
        width: 100%; height: 28px; min-width: 0; border: 1px solid #dce2eb; border-radius: 9px; background: #fff;
        color: #334155; font-size: 12px; padding: 0 7px; box-shadow: 0 1px 2px #0f172a03; box-sizing: border-box;
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .mfp-filter input[type="search"] { padding-left: 32px; }
    .mfp-filter-search { position: relative; }
    .mfp-filter-search > i { position: absolute; left: 11px; top: 8px; color: #94a3b8; font-size: 12px; pointer-events: none; }
    .mfp-filter input::placeholder { color: #8793a5; }
    .mfp-filter input:hover, .mfp-filter select:hover { border-color: #a5b4fc; }
    .mfp-filter input:focus, .mfp-filter select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px #6366f11a; }
    .mfp-filter .select2-container .select2-selection--single { height: 28px !important; border: 1px solid #dce2eb !important; border-radius: 9px !important; background: #fff; box-shadow: 0 1px 2px #0f172a03; }
    .mfp-filter .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 26px !important; padding-left: 7px; padding-right: 28px; font-size: 12px; color: #334155; }
    .mfp-filter .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #334155; }
    .mfp-filter .select2-container--default .select2-selection--single .select2-selection__arrow { height: 26px !important; right: 4px; }
    .mfp-filter .select2-container--default .select2-selection--single .select2-selection__clear { margin-right: 14px; font-size: 14px; color: #94a3b8; }
    .mfp-filter .select2-container--default.select2-container--open .select2-selection--single,
    .mfp-filter .select2-container--default.select2-container--focus .select2-selection--single { border-color: #6366f1 !important; box-shadow: 0 0 0 3px #6366f11a; }
    .mfp-filter .select2-container:hover .select2-selection--single { border-color: #a5b4fc !important; }
    .mfp-date-presets { display: flex; align-items: center; gap: 5px; flex-wrap: nowrap; padding-bottom: 0; }
    .mfp-date-presets button, .mfp-filter-actions button, .mfp-filter-reset {
        height: 28px; padding: 0 8px; border: 1px solid #dce2eb; border-radius: 7px; background: #fff; color: #64748b;
        font-size: 10px; font-weight: 600; white-space: nowrap; cursor: pointer; transition: background .18s ease, border-color .18s ease, color .18s ease;
    }
    .mfp-date-presets button:hover, .mfp-date-presets button.is-active, .mfp-filter-reset:hover { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
    .mfp-filter-actions { display: flex; align-items: center; gap: 6px; }
    .mfp-filter-actions button, .mfp-filter-reset { font-size: 12px; padding: 0 14px; display: inline-flex; align-items: center; text-decoration: none; }
    .mfp-filter-actions button i { margin-right: 6px; }
    .mfp-filter-actions button.is-primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .mfp-filter-actions button.is-primary:hover { background: #4338ca; }
    @media (max-width: 1200px) {
        .mfp-filter { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .mfp-filter-search-field { grid-column: 1 / 3; }
        .mfp-date-presets { grid-column: 1 / -1; flex-wrap: wrap; }
        .mfp-filter-actions { grid-column: 3 / -1; justify-content: flex-end; }
    }
    @media (max-width: 640px) { .mfp-filter { grid-template-columns: 1fr 1fr; } .mfp-filter-actions { grid-column: 1 / -1; } }
    /* Eight cards on one row: compact, icon beside the number, the hint
       only where there is room for it. */
    #mfpTable { scroll-margin-top: 84px; }
    .mfp-kpis { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 8px; }
    @media (max-width: 1100px) { .mfp-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .mfp-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .mfp-kpi { position: relative; display: grid; grid-template-columns: 30px 1fr; grid-template-rows: auto auto; column-gap: 8px; row-gap: 2px; align-items: center; padding: 10px 10px 9px; border-radius: 10px; text-decoration: none; min-width: 0;
        background: linear-gradient(135deg, var(--mfp-a), var(--mfp-b)); border: 1px solid transparent;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease; }
    .mfp-kpi-icon { grid-row: 1; grid-column: 1; display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 9px; color: #fff; font-size: 12px;
        background: linear-gradient(135deg, var(--mfp-ia), var(--mfp-ib)); }
    .mfp-kpi-num { grid-row: 1; grid-column: 2; font-size: 1.35rem; line-height: 1; font-weight: 900; color: var(--mfp-num); }
    .mfp-kpi-lbl { grid-row: 2; grid-column: 1 / -1; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; line-height: 1.15; color: var(--mfp-lbl); }
    .mfp-kpi-hint { display: none; grid-row: 3; grid-column: 1 / -1; font-size: 10px; line-height: 1.2; color: var(--mfp-hint); }
    @media (min-width: 1500px) { .mfp-kpi-hint { display: block; } }
    .mfp-kpi.is-on .mfp-kpi-hint { display: block; }
    .mfp-kpi:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, .08); }
    .mfp-kpi.is-on { border-color: var(--mfp-ring); box-shadow: 0 0 0 3px color-mix(in srgb, var(--mfp-ring) 25%, transparent), 0 8px 20px rgba(15, 23, 42, .08); }
    /* Cancelled card: the off-flight block's dividers. */
    .mfp-table .mfp-section-row td { background: #fef2f2; border-top: 1px solid #fecaca; border-bottom: 1px solid #fecaca; color: #991b1b; font-size: 11.5px; font-weight: 700; padding: 7px 12px; }
    .mfp-table .mfp-section-row td i { margin-right: 6px; }
    .mfp-table .mfp-section-row td b { display: inline-block; min-width: 20px; margin-left: 4px; padding: 0 6px; border-radius: 999px; background: #dc2626; color: #fff; font-size: 10.5px; text-align: center; }
    .mfp-table .mfp-section-row td span { margin-left: 10px; color: #b91c1c; font-weight: 500; font-size: 10.5px; }
    .mfp-kpi.is-on::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; top: 6px; right: 7px; width: 16px; height: 16px; border-radius: 50%; background: var(--mfp-ring); color: #fff; font-size: 8px; display: flex; align-items: center; justify-content: center; }
    /* the toggle */
    .mfp-status-btn.is-on { border-color: #c7d2fe; background: #eef2ff; color: #4338ca; }

    /* the row: sits under its passenger, no line between the two */
    .mfp-table tr:has(+ .mfp-journey-row:not([hidden])) td { border-bottom: 0; padding-bottom: 4px; }
    /* white background as before, no coloured side edge (owner, 2026-10-03),
       room above and below, even gutters both sides */
    .mfp-table .mfp-journey-row td { padding: 10px 12px 14px; border-bottom: 1px solid #e2e8f0; background: #fff; }
    .mfp-table .mfp-journey-row:hover td { background: #f8fafc; }

@include('contract-flights.partials.journey-card-styles')
    @media print { .mfp-journey-row, .mfp-status-btn { display: none !important; } }

    /* ── File-only additions ─────────────────────────────────────────── */
    /* The bar is the flight's grid, one row. Only when the card is too narrow for
       that row (under ~1146px — below 90% zoom on a 1365 window) does it fold
       into the flight's own narrow layout, instead of clipping Reset off the end. */
    .cfp-table-card { container-type: inline-size; }
    /* 20px narrower search floor and Stage box than the flight's, so the row
       still fits at 90% zoom with the page scrollbar showing (~1495px). */
    #cfp-filter.mfp-filter { grid-template-columns: minmax(180px, 1.4fr) 150px 130px max-content 118px 118px max-content; }
    @container (max-width: 1145px) {
        #cfp-filter.mfp-filter { grid-template-columns: 1fr 1fr 1fr 1fr; }
        #cfp-filter .mfp-filter-search-field { grid-column: 1 / 3; }
        #cfp-filter .mfp-date-presets { grid-column: 1 / -1; flex-wrap: wrap; }
        #cfp-filter .mfp-filter-actions { grid-column: 3 / -1; justify-content: flex-end; }
    }
    @container (max-width: 640px) {
        #cfp-filter.mfp-filter { grid-template-columns: 1fr 1fr; }
        #cfp-filter .mfp-filter-actions { grid-column: 1 / -1; }
    }
    /* The money buttons ride in the actions grid as cells of their own; the
       Refund / Re-process / Others ones open from the edit modal only. */
    .mfp-actions .cfmoney-actions { display: contents; }
    .mfp-actions [data-cf-money] { display: none !important; }
    .mfp-actions [data-cf-cancel] .cfmoney-cancel-label { display: none; }
    .mfp-actions a.mfp-btn { text-decoration: none; }
    .mfp-table tr[data-file-row] { cursor: pointer; }
    .mfp-table tr[data-file-row]:focus-visible { outline: 2px solid #6366f1; outline-offset: -2px; }
    .cfw-selected-file td { background: #edf2ff !important; box-shadow: inset 0 2px #6b75e8; }
    /* The group ledger's server-side bar: the .fdl-bar controls' 34px boxes. */
    .fdl-group-filter select { width: auto; flex: 0 0 190px; }
    .fdl-group-filter select, .fdl-group-filter input[type="date"] { height: 34px; padding: 0 9px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; font-size: 12px; color: #334155; outline: none; box-sizing: border-box; }
    .fdl-group-filter select:focus, .fdl-group-filter input[type="date"]:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    #cf-dashboard-ledger .cfp-ledger { margin-top: 24px; }
    /* Those selects become the Travels select2 picker (layout → ticketing-dropdowns):
       give the picker the bar's 34px box instead of the page's full-width default. */
    :is(.fdl-group-filter, .cfac-filter) .select2-container { flex: 0 0 210px; width: 210px !important; }
    :is(.fdl-group-filter, .cfac-filter) .select2-container .select2-selection--single { height: 34px !important; border: 1px solid #e2e8f0 !important; border-radius: 8px !important; background: #fff; }
    :is(.fdl-group-filter, .cfac-filter) .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 32px !important; padding-left: 9px; padding-right: 26px; font-size: 12px; color: #334155; }
    :is(.fdl-group-filter, .cfac-filter) .select2-container--default .select2-selection--single .select2-selection__arrow { height: 32px !important; right: 4px; }
    :is(.fdl-group-filter, .cfac-filter) .select2-container--open .select2-selection--single, :is(.fdl-group-filter, .cfac-filter) .select2-container--focus .select2-selection--single { border-color: #6366f1 !important; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
</style>
@include('contract-flights.partials.passenger-table-styles')
@endonce
