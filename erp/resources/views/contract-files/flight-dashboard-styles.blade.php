{{-- The Flight Dashboard's own sheet, copied rule for rule so the File Dashboard
     reads exactly like it (owner, 2026-10-25: "match the contract file with the
     contract flight ... even a bit font difference"). Source:
     contract-flights/dashboard.blade.php (banner, master filter, tables) and
     contract-flights/dashboard-ledger-styles.blade.php (the .fdl-bar filter).
     Same order as there, so the later overrides win the same way. The flight
     tables' ids became #cfdActiveTable / #cfdClosedTable. Change a value
     there and change it here. --}}
<style>
    .fd-banner {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 55%, #7c3aed 100%);
        border-radius: 16px; padding: 0.55rem 0.9rem; color: #fff;
        display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
        margin-bottom: 1rem; position: relative; overflow: hidden;
    }
    .fd-banner::before {
        content: ''; position: absolute; inset: 0; pointer-events: none;
        background:
            radial-gradient(120% 150% at 88% -25%, rgba(255,255,255,0.20), transparent 58%),
            radial-gradient(90% 130% at -5% 125%, rgba(255,255,255,0.12), transparent 55%);
    }
    .fd-banner > * { position: relative; z-index: 1; }
    .fd-banner h2 { font-size: 1.1rem; font-weight: 700; margin: 0; letter-spacing: -0.01em; line-height: 1.25; }
    .fd-banner p { font-size: 0.78rem; opacity: 0.85; margin: 2px 0 0; }
    .fd-head { display: flex; align-items: center; gap: 11px; min-width: 0; }
    .fd-head .fd-icon {
        width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0;
        background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.22);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
        display: flex; align-items: center; justify-content: center; font-size: 1rem;
    }
    .fd-stats {
        display: flex; align-items: center; gap: 14px; flex-shrink: 0; flex-wrap: wrap;
        background: rgba(255,255,255,0.10); border: 1px solid rgba(255,255,255,0.16);
        border-radius: 12px; padding: 7px 14px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.12);
    }
    .fd-stat { text-align: left; min-width: 0; }
    .fd-stat + .fd-stat { border-left: 1px solid rgba(255,255,255,0.22); padding-left: 12px; }
    .fd-stat .val { font-size: 1.05rem; font-weight: 700; line-height: 1.25; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fd-stat .lbl { font-size: 0.58rem; opacity: 0.78; text-transform: uppercase; letter-spacing: 0.07em; line-height: 1.35; white-space: nowrap; }
    .fd-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 0.85rem; }
    .fd-bar h3 { font-size: 0.95rem; font-weight: 700; color: #1e293b; margin: 0; }
    .fd-bar .fd-count { font-size: 0.72rem; font-weight: 600; color: #94a3b8; }

    /* Small inline flags — the brief's route line and the full card's route
       band. 3:2, a hairline so a white-edged flag (Japan) still has an edge. */
    .fdc-flag { width: 15px; height: 10px; border-radius: 2px; object-fit: cover; flex-shrink: 0;
                box-shadow: 0 0 0 1px rgba(15,23,42,0.12); display: inline-block; vertical-align: -1px; }
    .fd-tool {
        flex: 0 0 auto; width: 30px; height: 30px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid #e2e8f0; background: #fff; color: #64748b;
        border-radius: 8px; cursor: pointer; font-size: 0.82rem;
        transition: background .15s, color .15s, border-color .15s;
    }
    .fd-tool:hover { background: #f8fafc; color: #334155; border-color: #cbd5e1; }
    .fd-tool.is-alt { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
    .fd-tool.is-alt:hover { background: #e0e7ff; }
    .fd-empty { grid-column: 1 / -1; background: #fff; border: 1px dashed #cbd5e1; border-radius: 14px;
                padding: 2.5rem 1rem; text-align: center; color: #64748b; }
    .fd-empty i { font-size: 1.8rem; color: #cbd5e1; display: block; margin-bottom: 0.6rem; }
    .fd-empty a { color: #4f46e5; font-weight: 600; }
    .fd-table-panel { margin-top: 24px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
    .fd-table-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px; border-bottom: 1px solid #e2e8f0; }
    .fd-table-head h3 { margin: 0; color: #1e293b; font-size: .95rem; font-weight: 700; }
    .fd-table-head a, .fd-table a { color: #4f46e5; font-weight: 600; }
    .fd-table-head a { font-size: .8rem; }
    .fd-table-scroll { overflow-x: auto; }
    .fd-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .83rem; }
    .fd-table th { background: #eef2ff; color: #4338ca; padding: 13px 12px; border-bottom: 1px solid #c7d2fe; text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
    .fd-table td { padding: 8px 12px; border-bottom: 1px solid #b8c4d4; color: #334155; }
    .fd-table tbody tr:last-child td { border-bottom: none; }
    .fd-table tbody tr { transition: background-color .15s ease, box-shadow .15s ease; }
    .fd-table tbody tr:hover { background: #eef2ff; box-shadow: inset 3px 0 0 #6366f1; }
    .fd-table .fd-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fd-table .fd-passengers { text-align: left; font-variant-numeric: tabular-nums; white-space: nowrap; }
    :is(#cfdActiveTable, #cfdClosedTable) .fdl-eye { margin-top: 6px; border: 1px solid #dbe3ef; background: #fff; color: #64748b;
        width: 26px; height: 24px; border-radius: 6px; cursor: pointer; font-size: .72rem; line-height: 1; padding: 0; }
    :is(#cfdActiveTable, #cfdClosedTable) .fdl-eye:hover { background: #eef2ff; border-color: #a5b4fc; color: #4338ca; }
    .fd-table .fd-clip { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    :is(#cfdActiveTable, #cfdClosedTable) { table-layout: fixed; width: 100%; }
    :is(#cfdActiveTable, #cfdClosedTable) th { white-space: normal; padding: 12px 6px; }
    .fd-column-sort {
        display: flex; align-items: center; gap: 5px; width: 100%; padding: 0;
        border: 0; background: transparent; color: inherit; font: inherit;
        letter-spacing: inherit; text-transform: inherit; text-align: inherit; cursor: pointer;
    }
    .fd-column-sort i { flex-shrink: 0; opacity: .45; font-size: 10px; }
    :is(#cfdActiveTable, #cfdClosedTable) .fd-num .fd-column-sort { justify-content: flex-end; }
    :is(#cfdActiveTable, #cfdClosedTable) th[aria-sort="ascending"] .fd-column-sort i,
    :is(#cfdActiveTable, #cfdClosedTable) th[aria-sort="descending"] .fd-column-sort i { opacity: 1; color: #4338ca; }
    .fd-column-sort:hover { color: #4338ca; }
    .fd-column-sort:focus-visible { outline: 2px solid #6366f1; outline-offset: 4px; border-radius: 3px; }
    :is(#cfdActiveTable, #cfdClosedTable) td { padding: 8px 6px; }
    /* Every cell reads against the row's FIRST line (owner, 2026-09-22).
       .fd-table td is vertical-align:middle, so on a row four lines deep the
       one-line figures floated ~25px below the flight they belong to. Applied
       to the whole row, not just the money columns — top-aligning those alone
       would lift them ABOVE the departure and airline beside them. This is
       what #fdPassengerTable already does, which is why the ledger reads
       right. */
    :is(#cfdActiveTable, #cfdClosedTable) td { vertical-align: top; }
    /* And the eye sits UNDER its figure rather than beside it — inline, it
       was pushing "৳0" off the right edge of the column. */
    :is(#cfdActiveTable, #cfdClosedTable) .fdl-eye { display: block; margin-left: auto; }
    /* The passenger and the agent are both links; the agent's name is bold
       and violet so the party reads apart from the person travelling. */
    /* The PNR is a plain-weight link into Ticketing. */
    .fd-table .fd-nowrap { white-space: nowrap; }
    .fd-table .fd-agent-name { display: flex; align-items: center; gap: 5px; font-weight: 400; color: #334155; }   /* same as the route line (owner, 2026-10-03: "so bold") */
    .fd-table .fd-agent-name i { flex: 0 0 auto; color: #6366f1; font-size: .65rem; }
    .fd-table .fd-airport-detail { display: flex; align-items: center; gap: 4px; }
    .fd-table .fd-airport-detail i { flex: 0 0 auto; width: 11px; color: #818cf8; font-size: .6rem; text-align: center; }
    .fd-table small { display: block; color: #94a3b8; font-size: .7rem; margin-top: 1px; }
    .fd-table .fd-ledger-date { color: #1e293b; font-weight: 600; }   /* the list's .mfp-date */
    .fd-table-status { display: inline-block; padding: 3px 8px; border-radius: 6px; background: #fffbeb; color: #b45309; font-size: .7rem; font-weight: 600; }
    .fd-table-status.boarding { background: #eff6ff; color: #2563eb; }
    .fd-table .fd-table-empty { text-align: center; padding: 32px; color: #64748b; }
    .fd-table-panel { border-radius: 12px; }
    .fd-table-head { padding: 10px 12px; background: #f8fafc; flex-wrap: wrap; gap: 8px; }
    .fd-table-head input, .fd-table-head select, .fd-table-head button { border: 1px solid #dbe3ef; border-radius: 7px; padding: 8px 10px; font-size: .72rem; background: #fff; color: #64748b; min-width: 0; }
    .fd-table-head input[type="search"] { flex: 1 1 260px; }
    .fd-table-head button { background: #4f46e5; color: #fff; cursor: pointer; }
    .fd-table-head label, #cfdTableCount { font-size: .7rem; color: #94a3b8; }
    .fd-summary-strip {
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
        gap: 8px 16px; width: calc(100% + 32px); flex: 0 0 calc(100% + 32px);
        margin: -10px -16px 0; padding: 10px 16px;
        background: linear-gradient(110deg, #4338ca, #5b4ddd 65%, #6d42d8); color: #fff;
    }
    .fd-summary-strip .fd-stat .val { color: #fff; }
    .fd-summary-strip .fd-stat .lbl { color: #eef2ff; }
    .fd-summary-strip .fd-stats { gap: 8px; }
    .fd-summary-strip .fd-stat, .fd-summary-strip .fd-stat + .fd-stat {
        padding: 5px 11px; border: 1px solid #ffffff30; border-radius: 8px;
        background: linear-gradient(180deg, #ffffff24, #ffffff10);
        box-shadow: 0 3px 6px #24166b26, inset 0 1px 0 #ffffff20;
        transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
    }
    .fd-summary-strip .fd-stat:hover {
        transform: translateY(-2px); background-color: #ffffff12;
        box-shadow: 0 5px 10px #24166b33, inset 0 1px 0 #ffffff30;
    }
    .fd-stat-link { font: inherit; color: inherit; cursor: pointer; }
    .fd-stat-link:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    .fd-stat-link.is-active { background: #ffffff30; box-shadow: inset 0 0 0 1px #ffffff80; }
    @media (prefers-reduced-motion: reduce) {
        .fd-summary-strip .fd-stat { transition: none; }
        .fd-summary-strip .fd-stat:hover { transform: none; }
    }
    .fd-banner .fd-summary-strip #cfdTableCount { background: #ffffff20; color: #fff; }
    .fd-summary-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
    .fd-summary-actions .fd-tool { width: 26px; height: 26px; color: #fff; border-color: #ffffff40; background: #ffffff18; }
    .fd-summary-actions .fd-tool:hover, .fd-summary-actions .fd-tool.is-alt { color: #fff; background: #ffffff30; border-color: #ffffff70; }
    .fd-summary-actions .fd-tool:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    .fd-summary-actions .fd-add-flight {
        display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 7px;
        background: #fff; color: #4338ca; font-size: 11px; font-weight: 700; white-space: nowrap; text-decoration: none;
        transition: background .15s ease, transform .15s ease;
    }
    .fd-summary-actions .fd-add-flight:hover { background: #eef2ff; transform: translateY(-1px); }
    .fd-summary-actions .fd-add-flight:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    @media (max-width: 640px) {
        .fd-summary-strip { width: calc(100% + 24px); flex-basis: calc(100% + 24px); margin-inline: -12px; padding-inline: 12px; }
    }
    /* Quiet dashboard header with a dedicated filter surface. */
    .fd-banner {
        padding: 10px 16px; gap: 4px; color: #1e293b;
        background: linear-gradient(115deg, #ffffff 55%, #f5f3ff);
        border: 1px solid #e2e8f0; border-radius: 16px;
        box-shadow: 0 4px 20px #0f172a05, 0 1px 3px #0f172a04;
    }
    .fd-banner::before { display: none; }
    .fd-banner h2 { font-size: 20px; font-weight: 750; letter-spacing: -.025em; }
    .fd-banner p { margin-top: 5px; color: #64748b; opacity: 1; font-size: 12px; }
    .fd-head .fd-icon {
        width: 44px; height: 44px; color: #4f46e5; background: #eef2ff;
        border-color: #e0e7ff; border-radius: 13px; box-shadow: none;
    }
    .fd-stats { background: transparent; border: 0; box-shadow: none; padding: 0; gap: 20px; }
    .fd-banner .fd-stat { display: flex; align-items: baseline; gap: 7px; }
    .fd-stat + .fd-stat { border-color: #e2e8f0; padding-left: 20px; }
    .fd-stat .val { font-size: 16px; color: #0f172a; letter-spacing: -.025em; }
    .fd-stat .lbl { margin-top: 0; color: #64748b; opacity: 1; font-size: 10px; letter-spacing: .045em; }
    .fd-banner .fd-master-filter {
        display: grid; grid-template-columns: minmax(220px, 1fr) 150px auto 118px 118px;
        align-items: end; flex: 1 0 100%; width: 100%; gap: 10px;
        padding: 4px 0 0; background: transparent; border-top: 1px solid #e8ecf3; border-bottom: 0;
    }
    .fd-master-filter > .fd-filter-field:nth-child(2) { grid-column: 2; }
    .fd-master-filter > .fd-filter-field:nth-child(3) { grid-column: 4; }
    .fd-master-filter > .fd-filter-field:nth-child(4) { grid-column: 5; }
    .fd-filter-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
    .fd-master-filter label { color: #475569; font-size: 11px; font-weight: 600; margin: 0; }
    .fd-master-filter input, .fd-master-filter select {
        width: 100%; height: 28px; min-width: 0; border: 1px solid #dce2eb;
        border-radius: 9px; background: #fff; color: #334155; font-size: 12px;
        padding: 0 7px; box-shadow: 0 1px 2px #0f172a03;
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .fd-master-filter input[type="search"] { flex: none; padding-left: 36px; }
    .fd-filter-search { position: relative; }
    .fd-filter-search > i { position: absolute; left: 13px; top: 11px; color: #94a3b8; font-size: 12px; pointer-events: none; }
    .fd-master-filter input::placeholder { color: #8793a5; }
    .fd-master-filter input:hover, .fd-master-filter select:hover { border-color: #a5b4fc; }
    .fd-master-filter input:focus, .fd-master-filter select:focus {
        outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px #6366f11a;
    }
    .fd-master-filter button {
        height: 34px; padding: 0 14px; border: 1px solid #dce2eb; border-radius: 9px;
        background: #f8fafc; color: #475569; font-size: 12px; font-weight: 600; white-space: nowrap;
        transition: background .18s ease, border-color .18s ease;
    }
    .fd-master-filter button i { margin-right: 6px; }
    .fd-master-filter button:hover { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
    .fd-master-filter button:focus-visible { outline: 2px solid #6366f1; outline-offset: 3px; }
    .fd-date-presets {
        grid-column: 3; grid-row: 1; display: flex; align-items: center; gap: 5px; flex-wrap: wrap;
        padding-top: 2px;
    }
    .fd-date-presets button {
        height: 28px; padding: 0 8px; border-radius: 7px; font-size: 10px;
        background: #fff; color: #64748b;
    }
    .fd-date-presets button:hover, .fd-date-presets button.is-active {
        background: #eef2ff; border-color: #c7d2fe; color: #4338ca;
    }
    .fd-filter-footer { grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .fd-filter-hint { color: #64748b; font-size: 11px; }
    .fd-filter-hint i { margin-right: 6px; color: #818cf8; }
    .fd-banner #cfdTableCount { color: #4338ca; background: #eef2ff; border-radius: 6px; padding: 4px 8px; font-size: 11px; font-weight: 600; white-space: nowrap; }
    @media (max-width: 1100px) {
        .fd-banner .fd-master-filter { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .fd-filter-field:first-child { grid-column: span 2; }
        .fd-master-filter > .fd-filter-field:nth-child(2),
        .fd-master-filter > .fd-filter-field:nth-child(3),
        .fd-master-filter > .fd-filter-field:nth-child(4),
        .fd-date-presets { grid-column: auto; grid-row: auto; }

    }
    @media (max-width: 640px) {
        .fd-banner { padding: 10px 12px; gap: 8px; }
        .fd-banner h2 { font-size: 18px; }
        .fd-stats { width: 100%; justify-content: space-between; gap: 8px; }
        .fd-stat + .fd-stat { padding-left: 10px; }
        .fd-banner .fd-stat { flex-direction: column; gap: 2px; }
        .fd-stat .val { font-size: 16px; }
        .fd-stat .lbl { font-size: 8px; }
        .fd-banner .fd-master-filter { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .fd-filter-field:first-child { grid-column: 1 / -1; }
        .fd-date-presets { grid-column: 1 / -1; }
        .fd-master-filter button { grid-column: 1 / -1; }
        .fd-filter-footer { align-items: flex-start; flex-wrap: wrap; }
    }
    @media (prefers-reduced-motion: reduce) {
        .fd-master-filter input, .fd-master-filter select, .fd-master-filter button { transition: none; }
    }
    .fd-table td { line-height: 1.3; vertical-align: middle; }
    .fd-table small { max-width: 220px; line-height: 1.3; }
    .fd-table .fd-detail { color: #6366f1; }
    .fd-table .fd-due { color: #dc2626; }
    .fd-table .fd-clear { color: #059669; }
    .fd-table small a { font-weight: inherit; }
    .fd-table a:hover, .fd-table a:focus-visible { text-decoration: underline; }
    .fd-table-status { font-size: .6rem; padding: 1px 5px; margin-left: 4px; }
    /* Departed Flights table (owner, 2026-10-01): the same table as above, its
       own title bar. Badges say why a batch left the wall. */
    .fd-table-status.closed { background: #ecfdf5; color: #047857; }
    .fd-table-status.departed { background: #f1f5f9; color: #475569; }
    .fd-departed-head { display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 10px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .fd-departed-head h3 { margin: 0; color: #1e293b; font-size: .9rem; font-weight: 700; }
    .fd-departed-head h3 i { color: #6366f1; margin-right: 6px; }
    .fd-departed-head span { font-size: .7rem; color: #94a3b8; }
    .fd-table .fd-agents-more { display: block; margin-top: 2px; padding: 0; border: 0; background: none; color: #4f46e5;
        font-size: .7rem; font-weight: 600; cursor: pointer; }
    .fd-table .fd-agents-more:hover, .fd-table .fd-agents-more:focus-visible { text-decoration: underline; }
    .fd-table .fd-agent-line { font-weight: 400; color: #334155; line-height: 1.18; }
    .fd-table .fd-agent-line i { color: #6366f1; font-size: .65rem; }
    .fd-table .fd-agent-line a { font-size: .7rem; font-weight: 500; color: #6366f1; margin-left: 2px; }
    /* ── The filter bar (the bank's .lf-bar) ────────────────────────────── */
    .fdl-bar {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 11px 14px; background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .fdl-search { position: relative; flex: 1 1 260px; min-width: 200px; }
    .fdl-search > i {
        position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: 12px; pointer-events: none;
    }
    .fdl-search input {
        width: 100%; height: 34px; padding: 0 30px 0 30px;
        border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;
        font-size: 12.5px; color: #1e293b; outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .fdl-search input::placeholder { color: #a3adbb; }
    .fdl-search input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    .fdl-clear {
        position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
        border: 0; background: none; padding: 4px; cursor: pointer;
        color: #cbd5e1; font-size: 12px; line-height: 1; display: none;
    }
    .fdl-clear:hover { color: #64748b; }
    .fdl-search.has-q .fdl-clear { display: block; }

    /* flex:0 0 auto — the group must never shrink; the search gives way. */
    .fdl-seg { display: flex; flex: 0 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff; }
    .fdl-seg button {
        border: 0; border-left: 1px solid #e2e8f0; background: none;
        padding: 0 11px; height: 34px; cursor: pointer;
        font-size: 11.5px; font-weight: 600; color: #64748b; white-space: nowrap;
        transition: background .15s, color .15s;
    }
    .fdl-seg button:first-child { border-left: 0; }
    .fdl-seg button:hover { background: #f1f5f9; color: #334155; }
    .fdl-seg button.is-on { background: #eef2ff; color: #4338ca; font-weight: 700; }

    .fdl-dates { display: flex; flex: 0 0 auto; align-items: center; gap: 6px; }
    .fdl-dates span { font-size: 11px; color: #94a3b8; }
    /* A dd/mm/yyyy box and the real calendar, side by side in one border —
       see the bank sheet's .lf-date block for why the date input is stripped
       down to its calendar button. */
    .fdl-date {
        position: relative; display: inline-flex; align-items: center;
        height: 34px; padding-right: 5px; background: #fff;
        border: 1px solid #e2e8f0; border-radius: 8px;
        transition: border-color .15s, background .15s;
    }
    .fdl-date:focus-within { border-color: #6366f1; }
    .fdl-dates input.fdl-date-text {
        width: 88px; height: 32px; padding: 0 2px 0 9px;
        border: 0; background: none; outline: none;
        font-size: 12px; color: #334155;
    }
    .fdl-date.is-bad { border-color: #fca5a5; background: #fef2f2; }
    .fdl-dates input.fdl-date-text.is-bad { color: #b91c1c; }
    .fdl-dates input.fdl-date-native {
        width: 22px; height: 22px; flex: 0 0 auto;
        margin: 0; padding: 0; border: 0; background: none;
        color: transparent; overflow: hidden; cursor: pointer;
    }
    .fdl-date-native::-webkit-datetime-edit,
    .fdl-date-native::-webkit-inner-spin-button,
    .fdl-date-native::-webkit-clear-button { display: none; }
    .fdl-date-native::-webkit-calendar-picker-indicator {
        width: 22px; height: 22px; margin: 0; padding: 0;
        opacity: 0.5; cursor: pointer; transition: opacity .15s;
    }
    .fdl-date:hover .fdl-date-native::-webkit-calendar-picker-indicator,
    .fdl-date-native:focus::-webkit-calendar-picker-indicator { opacity: 0.9; }
    .fdl-dates.is-open .fdl-date { border-color: #c7d2fe; background: #f5f6ff; }
    .fdl-go {
        height: 34px; padding: 0 12px; border: 0; border-radius: 8px; cursor: pointer;
        background: #4f46e5; color: #fff; font-size: 11.5px; font-weight: 700;
    }
    .fdl-go:hover { background: #4338ca; }
    .fdl-print {
        height: 34px; padding: 0 12px; border: 1px solid #e2e8f0; border-radius: 8px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        background: #fff; color: #475569; font-size: 11.5px; font-weight: 700;
        transition: background .15s, color .15s, border-color .15s;
    }
    .fdl-print:hover { background: #f8fafc; color: #334155; border-color: #cbd5e1; text-decoration: none; }
    .fdl-print i { font-size: 11px; }
</style>
