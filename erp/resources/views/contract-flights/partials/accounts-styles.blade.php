{{-- Flight Accounts' cfa- styles; shared by the accounts page and the due
     sheets shown under the Agent / Vendor Ledger tabs. --}}
<style>
    /* Flight Accounts — the money side of Contract Flight. Same dress as the
       Flight Dashboard's tables (indigo header, detail lines) under its own
       cfa- prefix so nothing here reaches another screen. */
    .cfa-wrap { display: flex; flex-direction: column; gap: 14px; }
    .cfa-head { display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
    .cfa-head .ic { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 10px; background: #eef2ff; color: #4f46e5; font-size: 18px; flex-shrink: 0; }
    .cfa-head h1 { margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; }
    .cfa-head p { margin: 2px 0 0; font-size: 12px; color: #64748b; }
    .cfa-head .sp { flex: 1; }
    .cfa-btn { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 14px; border-radius: 8px; border: 1px solid #dce2eb; background: #fff; color: #475569; font-size: 12px; font-weight: 700; text-decoration: none; cursor: pointer; }
    .cfa-btn:hover { background: #f8fafc; }
    .cfa-btn.sm { height: 28px; width: 30px; padding: 0; justify-content: center; font-size: 12px; }
    .cfa-btn.sm.warn { color: #b45309; border-color: #fde68a; background: #fffbeb; } .cfa-btn.sm.warn:hover { background: #fef3c7; }
    .cfa-btn.sm.wa { color: #16a34a; border-color: #bbf7d0; background: #f0fdf4; } .cfa-btn.sm.wa:hover { background: #dcfce7; }
    .cfa-btn:disabled { opacity: .4; cursor: not-allowed; }
    .cfa-send { display: inline-flex; gap: 4px; }
    .cfa-btn.primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .cfa-btn.primary:hover { background: #4338ca; }

    /* Filter strip, as on the dashboard. */
    .cfa-filter { display: grid; grid-template-columns: 160px 160px 200px max-content; align-items: end; gap: 10px; padding: 12px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
    .cfa-filter label { display: block; color: #475569; font-size: 11px; font-weight: 600; margin: 0 0 4px; }
    .cfa-filter input, .cfa-filter select { width: 100%; height: 30px; border: 1px solid #dce2eb; border-radius: 9px; background: #fff; color: #334155; font-size: 12px; padding: 0 8px; box-sizing: border-box; }
    .cfa-filter input:focus, .cfa-filter select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px #6366f11a; }
    .cfa-filter .acts { display: flex; gap: 6px; }
    .cfa-filter .acts .cfa-btn { height: 30px; }

    /* KPI strip: the P&L headline. */
    .cfa-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; }
    @media (max-width: 1100px) { .cfa-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .cfa-kpi { padding: 12px 14px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; }
    .cfa-kpi .l { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
    .cfa-kpi .v { margin-top: 4px; font-size: 20px; font-weight: 900; color: #0f172a; font-variant-numeric: tabular-nums; }
    .cfa-kpi .s { margin-top: 2px; font-size: 11px; color: #94a3b8; }
    .cfa-kpi.in .v { color: #4f46e5; } .cfa-kpi.out .v { color: #dc2626; } .cfa-kpi.good .v { color: #059669; } .cfa-kpi.bad .v { color: #dc2626; } .cfa-kpi.due .v { color: #b45309; }

    /* Tabs. */
    .cfa-tabs { display: flex; width: calc(100% + 24px); margin: 0 -12px; gap: 0; padding: 0; background: #fff; border: 0; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; border-radius: 0; overflow-x: auto; box-sizing: border-box; }
    .cfa-tab { flex: 1 1 0; min-width: max-content; display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 30px; padding: 0 12px; border-radius: 0; border: 0; background: transparent; color: #475569; font-size: 12px; font-weight: 700; white-space: nowrap; cursor: pointer; text-decoration: none; transition: background .15s, color .15s, box-shadow .15s; }
    .cfa-tab + .cfa-tab { border-left: 1px solid #e2e8f0; }
    .cfa-tab .ic { display: inline-flex; color: #6366f1; font-size: 11px; }
    .cfa-tab:hover { background: #fff; color: #1e293b; text-decoration: none; }
    .cfa-tab.is-on, .cfa-tab.is-on:hover, a.cfa-tab.is-on, a.cfa-tab.is-on:visited { background: #4f46e5; color: #fff !important; }
    .cfa-tab.is-on .t, .cfa-tab.is-on i { color: #fff !important; }
    .cfa-tab.is-on .ic { color: #fff !important; }
    .cfa-tab .n { padding: 0 7px; border-radius: 999px; background: #e0e7ff; color: #4338ca; font-size: 10px; font-weight: 800; line-height: 16px; }
    .cfa-tab.is-on .n { background: rgba(255,255,255,.25); color: #fff; }
    .cfa-panel[hidden] { display: none; }
    .cfa-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .cfa-card + .cfa-card { margin-top: 14px; }
    .cfa-card-h { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
    .cfa-card-h h3 { margin: 0; font-size: 13.5px; font-weight: 800; color: #1e293b; }
    .cfa-card-h small { color: #64748b; font-size: 11.5px; }
    .cfa-card-h .sp { flex: 1; }

    /* Tables — the ledger's dress. */
    .cfa-scroll { overflow-x: auto; }
    .cfa-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .82rem; }
    .cfa-table th { background: #eef2ff; color: #4338ca; padding: 10px 12px; border-bottom: 1px solid #c7d2fe; text-align: left; font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
    .cfa-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; color: #334155; line-height: 1.3; vertical-align: top; }
    .cfa-table tbody tr:last-child td { border-bottom: 0; }
    .cfa-table tbody tr { transition: background-color .15s ease, box-shadow .15s ease; }
    .cfa-table tbody tr:hover { background: #eef2ff; box-shadow: inset 3px 0 0 #6366f1; }
    .cfa-table tfoot td { background: #f8fafc; font-weight: 800; color: #1e293b; border-top: 2px solid #c7d2fe; }
    .cfa-agent-row { cursor: pointer; }
    .cfa-agent-row.is-on td { background: #eef2ff; }
    .cfa-agent-row.is-on td:first-child { box-shadow: inset 3px 0 0 #4f46e5; }
    .cfa-agent-row:focus-visible { outline: 2px solid #6366f1; outline-offset: -2px; }
    .cfa-table small { display: block; color: #94a3b8; font-size: .7rem; margin-top: 1px; }
    .cfa-table .num, .cfa-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .cfa-table a { color: #4f46e5; font-weight: 600; text-decoration: none; } .cfa-table a:hover { text-decoration: underline; }
    .cfa-table .b { font-weight: 700; color: #1e293b; }
    .cfa-in { color: #4f46e5; } .cfa-out { color: #dc2626; } .cfa-good { color: #059669; } .cfa-bad { color: #dc2626; } .cfa-due { color: #b45309; }
    .cfa-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .68rem; font-weight: 700; background: #eef2ff; color: #4338ca; white-space: nowrap; }
    .cfa-pill.ok { background: #ecfdf5; color: #047857; } .cfa-pill.warn { background: #fffbeb; color: #b45309; } .cfa-pill.bad { background: #fef2f2; color: #b91c1c; } .cfa-pill.muted { background: #f1f5f9; color: #475569; }
    .cfa-empty { padding: 36px 16px; text-align: center; color: #64748b; font-size: .85rem; }
    .cfa-empty i { display: block; font-size: 28px; color: #cbd5e1; margin-bottom: 8px; }
    .cfa-bar { position: relative; height: 6px; border-radius: 999px; background: #e2e8f0; overflow: hidden; min-width: 90px; }
    .cfa-bar > span { position: absolute; inset: 0 auto 0 0; border-radius: 999px; background: #22c55e; }
    .mfp-table tfoot td { background: #f8fafc; font-weight: 800; color: #1e293b; border-top: 2px solid #c7d2fe; padding: 8px 12px; }
    .mfp-table tfoot small { display: block; font-size: .7rem; margin-top: 1px; }
    .cfa-jr td { background: #f8fafc; }
    .cfa-jr table { width: 100%; font-size: .75rem; }
    .cfa-jr table td { padding: 3px 8px; border: 0; color: #475569; }
    .cfa-jr .acc { font-family: ui-monospace, monospace; color: #64748b; }

    /* Profit & Loss: the statement and the expandable flight rows. */
    .cfa-pls { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    @media (max-width: 1000px) { .cfa-pls { grid-template-columns: 1fr; } }
    .cfa-pls-col { padding: 12px 16px; }
    .cfa-pls-col + .cfa-pls-col { border-left: 1px solid #e2e8f0; }
    .cfa-pls-h { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
    .cfa-pls-h span { font-weight: 600; text-transform: none; letter-spacing: 0; color: #94a3b8; margin-left: 4px; }
    .cfa-pls-h.in { color: #4f46e5; } .cfa-pls-h.out { color: #dc2626; } .cfa-pls-h.good { color: #059669; }
    .cfa-pls-r, .cfa-pls-t { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 6px 0; border-bottom: 1px dashed #e2e8f0; font-size: 12.5px; color: #334155; }
    .cfa-pls-r small { display: block; font-size: 10.5px; color: #94a3b8; }
    .cfa-pls-r b, .cfa-pls-t b { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .cfa-pls-t { border-bottom: 0; border-top: 2px solid #e2e8f0; font-weight: 800; margin-top: 2px; }
    .cfa-pls-t.in b { color: #4f46e5; } .cfa-pls-t.out b { color: #dc2626; }
    .cfa-pls-big { padding: 4px 0 10px; border-bottom: 2px solid #e2e8f0; }
    .cfa-pls-big span { display: block; font-size: 12px; font-weight: 700; color: #64748b; }
    .cfa-pls-big b { display: block; font-size: 26px; font-weight: 900; font-variant-numeric: tabular-nums; }
    .cfa-pls-big small { font-size: 11px; color: #64748b; } .cfa-pls-big em { font-style: normal; font-weight: 700; color: #334155; }
    .cfa-nw { white-space: nowrap; }
    .cfa-table td small.cfa-warn-t, .cfa-pl-pax td small.cfa-warn-t, .cfa-warn-t { color: #d97706; font-weight: 700; }
    .cfa-pls-warn { display: flex; gap: 10px; align-items: flex-start; padding: 10px 16px; background: #fffbeb; border-top: 1px solid #fde68a; color: #92400e; font-size: 12.5px; }
    .cfa-pls-warn i { color: #d97706; margin-top: 2px; }
    .cfa-pls-cash { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); border-top: 1px solid #e2e8f0; }
    @media (max-width: 1000px) { .cfa-pls-cash { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .cfa-pls-cash > div { padding: 10px 16px; }
    .cfa-pls-cash > div + div { border-left: 1px solid #f1f5f9; }
    .cfa-pls-cash span { display: block; font-size: 11px; font-weight: 700; color: #64748b; }
    .cfa-pls-cash span small { font-weight: 500; color: #94a3b8; }
    .cfa-pls-cash b { font-size: 16px; font-weight: 900; font-variant-numeric: tabular-nums; }
    .cfa-pls-gl { display: flex; gap: 10px; align-items: flex-start; padding: 9px 16px; border-top: 1px solid #e2e8f0; background: #f8fafc; color: #475569; font-size: 12px; }
    .cfa-pls-gl i { color: #94a3b8; margin-top: 2px; } .cfa-pls-gl small { color: #94a3b8; }
    .cfa-pl-2 { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 14px; }
    @media (max-width: 1100px) { .cfa-pl-2 { grid-template-columns: 1fr; } }
    .cfa-pl-2 > .cfa-card + .cfa-card { margin-top: 0; }
    .cfa-pl-leak { padding: 6px 16px 8px; border-bottom: 1px solid #e2e8f0; }
    .cfa-pl-foot td { background: #f8fafc !important; color: #475569 !important; font-weight: 700 !important; border-top: 1px solid #e2e8f0 !important; }
    .cfa-pl-nested { background: #fff; border: 1px solid #e2e8f0; }
    .cfa-pl-mdetail > td { padding: 12px 12px 14px 20px !important; background: #f1f5f9; }
    /* An opened past month (2026-10-15): the tab's own cards — statement,
       by agent, losses, rows — at the tab's own size, not the small type of
       a detail row. */
    #plMonths > tbody > tr.cfa-pl-mdetail:hover { background: transparent; box-shadow: none; }
    .cfa-pl-mdetail td td { background: transparent; border-bottom: 1px solid #e2e8f0; }
    .cfa-pl-mdetail table.cfa-table { font-size: .82rem; }
    .cfa-pl-mdetail .cfa-pl-nested { border: 0; }
    .cfa-pl-mrows { margin-top: 12px; }
    .cfa-pl-mscope:empty + .cfa-pl-mrows { margin-top: 0; }
    .cfa-pl-mscope .cfa-pls b, .cfa-pl-mscope .cfa-pls-cash b, .cfa-pl-mscope .cfa-pl-leak b, .cfa-pl-mscope .cfa-pls-warn b { font-weight: 700; }
    .cfa-pl-mscope .cfa-pls-big b { font-weight: 800; }
    .cfa-pl-mscope .cfa-pls-big small { display: inline; color: #64748b; font-size: 11px; margin: 0; }
    .cfa-table td small.cfa-pl-up { display: inline; margin-left: 6px; color: #6366f1; font-weight: 600 !important; }
    .cfa-pl-agents td:nth-child(6).cfa-good { color: #475569; font-weight: 400; }
    .cfa-kpi .s b.cfa-kpi-mo { color: #4f46e5; font-weight: 700; }
    .cfa-pl-row, .cfa-pl-month { cursor: pointer; }
    .cfa-pl-month { scroll-margin-top: 90px; }
    .cfa-pl-mi { font-size: 10px; color: #94a3b8; margin-right: 6px; transition: transform .15s; }
    .cfa-pl-month.is-open td { background: #eef2ff; }
    .cfa-pl-month.is-open .cfa-pl-mi { transform: rotate(90deg); color: #4f46e5; }
    .cfa-pl-tg { width: 22px; color: #94a3b8; }
    .cfa-pl-tg i { transition: transform .15s; font-size: 10px; }
    .cfa-pl-row.is-open td { background: #eef2ff; }
    .cfa-pl-row.is-open .cfa-pl-tg i { transform: rotate(90deg); color: #4f46e5; }
    .cfa-pl-detail > td { padding: 0 12px 10px 34px !important; }
    .cfa-pl-pax { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
    .cfa-pl-pax th { padding: 6px 8px !important; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; background: #f8fafc; text-align: left; border-bottom: 1px solid #e2e8f0 !important; }
    .cfa-pl-pax td { padding: 6px 8px !important; border-bottom: 1px solid #f1f5f9 !important; font-size: 12px; vertical-align: top; }
    .cfa-pl-pax .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .cfa-pl-pax small { display: block; font-size: 10.5px; color: #94a3b8; }
    /* Money colours in tables — kept to two signals so the eye finds them:
       profit (green / red for a loss) and money still due (amber). Revenue,
       cost and margin read as plain figures; a loss margin is red. Weight
       stays at 600 — colour carries the meaning, not heaviness. */
    .cfa-table td.cfa-in, .cfa-table td.cfa-out, .cfa-pl-pax td.cfa-in, .cfa-pl-pax td.cfa-out { color: #334155; }
    .cfa-table td.cfa-good, .cfa-pl-pax td.cfa-good { color: #047857; font-weight: 600; }
    .cfa-table td.cfa-bad, .cfa-pl-pax td.cfa-bad { color: #dc2626; font-weight: 600; }
    .cfa-table td.cfa-due, .cfa-pl-pax td.cfa-due { color: #b45309; font-weight: 600; }
    .cfa-table td b, .cfa-pl-pax td b { font-weight: 600; }
    .cfa-table td small, .cfa-pl-pax td small { font-weight: 400 !important; }
    .cfa-table td small.cfa-warn-t, .cfa-pl-pax td small.cfa-warn-t { color: #b45309; }
    .cfa-table td small.cfa-in { color: #94a3b8; }
    /* Margin: neutral unless it is a loss. */
    #plTable td:nth-child(11).cfa-good, #plMonths td[data-mc="margin"].cfa-good, .cfa-pl-nested td:nth-child(11).cfa-good, #plAgents td:nth-child(6).cfa-good { color: #475569; font-weight: 400; }
    #plTable td:nth-child(11).cfa-warn-t { color: #94a3b8; font-weight: 400; }

    /* P&L tidy pass: tighter rows and blocks. */
    [data-panel="pl"] .cfa-table td { padding: 6px 10px; }
    [data-panel="pl"] .cfa-table th { padding: 8px 10px; }
    [data-panel="pl"] .cfa-card-h { padding: 9px 14px; }
    .cfa-pls-col { padding: 8px 14px; }
    .cfa-pls-r, .cfa-pls-t { padding: 4px 0; font-size: 12.5px; }
    .cfa-pls-r span[title], .cfa-pls-h[title] { cursor: help; }
    .cfa-pls-big { padding: 2px 0 6px; }
    .cfa-pls-big b { font-size: 22px; font-weight: 800; }
    .cfa-pls-cash > div { padding: 7px 14px; }
    .cfa-pls-cash b { font-size: 14px; font-weight: 700; }
    .cfa-pls-warn, .cfa-pls-gl { padding: 6px 14px; font-size: 12px; }
    .cfa-pls-gl { cursor: help; }
    .cfa-pl-leak { padding: 4px 14px 6px; }
    [data-panel="pl"] .cfa-wrap, [data-panel="pl"] .cfa-card + .cfa-card { margin-top: 10px; }
    .cfa-pl-2 { gap: 10px; margin-top: 10px; }

    td.cfa-mg.cfa-good { color: #475569 !important; font-weight: 400 !important; }
    .cfa-tab-xl { flex: 0 0 auto; color: #15803d; } .cfa-tab-xl .ic { color: #16a34a; }
    .cfa-pls-out { display: flex; flex-wrap: wrap; gap: 6px 18px; align-items: baseline; padding: 6px 14px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; cursor: help; }
    .cfa-pls-out .h { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; }
    .cfa-pls-out b { color: #334155; font-weight: 700; }

    /* Layout pass (2026-10-12): one even gap between every block of a tab,
       side-by-side cards top-aligned, and coloured card title bars. */
    .cfa-panel > * + * { margin-top: 12px !important; }
    .cfa-pl-2 { margin-top: 12px; align-items: stretch; }
    .cfa-pl-2 > .cfa-card { margin-top: 0 !important; display: flex; flex-direction: column; }
    .cfa-card-h { background: linear-gradient(90deg, #4f46e5 0%, #6d28d9 100%); border-bottom: 0; min-height: 40px; box-sizing: border-box; }
    .cfa-card-h h3 { color: #fff; }
    .cfa-card-h small { color: #c7d2fe; }
    .cfa-card-h .cfa-btn { background: rgba(255,255,255,.15); border-color: rgba(255,255,255,.3); color: #fff; }

    /* Drill-down: every figure opens what is behind it. */
    .cfa-dd-link { cursor: pointer; border-bottom: 1px dotted currentColor; }
    .cfa-dd-link:hover { opacity: .75; }
    .cfa-dd-go { cursor: pointer; }
    .cfa-pls-gl, [data-dd-stock] { cursor: pointer; }
    .cfa-flash td { animation: cfa-flash 1.6s ease; }
    @keyframes cfa-flash { 0%, 40% { background: #fef3c7; } 100% { background: transparent; } }
    .cfa-dd { position: fixed; inset: 0; z-index: 1000; display: none; align-items: flex-start; justify-content: center; padding: 60px 16px 16px; background: rgba(15,23,42,.45); }
    .cfa-dd.is-open { display: flex; }
    .cfa-dd-box { width: min(760px, 100%); max-height: calc(100vh - 90px); display: flex; flex-direction: column; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,.3); }
    .cfa-dd-h { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 14px; background: linear-gradient(90deg, #4f46e5 0%, #6d28d9 100%); }
    .cfa-dd-h h3 { margin: 0; color: #fff; font-size: 14px; font-weight: 800; }
    .cfa-dd-h small { color: #c7d2fe; font-size: 11.5px; }
    .cfa-dd-x { border: 0; background: rgba(255,255,255,.18); color: #fff; width: 28px; height: 28px; border-radius: 7px; font-size: 18px; line-height: 1; cursor: pointer; }
    .cfa-dd-b { overflow: auto; }
    .cfa-dd-b .cfa-table td, .cfa-dd-b .cfa-table th { padding: 6px 12px; }

    @media print { .pb-bar { display: none !important; } .cfa-pl-row[hidden], .cfa-pl-detail[hidden] { display: none !important; } }

    @media print {
        .cfa-filter, .cfa-tabs, .cfa-head .cfa-btn, #sidebar, header, .fd-nav, nav { display: none !important; }
        .cfa-panel[hidden] { display: block !important; }
        .cfa-card { break-inside: avoid; margin-top: 12px; }
        .cfa-table tbody tr:hover { background: transparent; box-shadow: none; }
    }
</style>
