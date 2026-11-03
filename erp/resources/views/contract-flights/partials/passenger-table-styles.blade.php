{{-- The passenger table's dress — one row per passenger in the Flight
     Dashboard ledger's style. Included by Flights & Passengers and Flight
     Accounts so a passenger reads the same on both. Every selector is
     mfp- prefixed (plus .cf-row's hover), nothing here reaches other screens. --}}
<style>
    .cf-row { transition: background-color .15s ease, box-shadow .15s ease; }
    .cf-row:hover { background: #eef2ff; box-shadow: inset 3px 0 0 #6366f1; }
    /* Passenger table — transcribed from the Flight Dashboard ledger's
       .fd-table rules so a passenger reads the same on both screens. */
    .mfp-table { width: 100%; min-width: 1180px; table-layout: fixed; border-collapse: separate; border-spacing: 0; font-size: .83rem; }
    .mfp-table th { background: #eef2ff; color: #4338ca; padding: 11px 12px; border-bottom: 1px solid #c7d2fe; text-align: left; font-size: .56rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; white-space: normal; vertical-align: middle; }
    .mfp-table td { padding: 8px 12px; border-bottom: 1px solid #b8c4d4; color: #334155; line-height: 1.3; vertical-align: top; overflow-wrap: anywhere; }
    .mfp-table tbody tr:last-child td { border-bottom: none; }
    .mfp-table a { color: #4f46e5; font-weight: 600; }
    .mfp-table a:hover, .mfp-table a:focus-visible { text-decoration: underline; }
    .mfp-table small { display: block; color: #94a3b8; font-size: .7rem; margin-top: 1px; line-height: 1.3; max-width: 220px; }
    .mfp-table small a { font-weight: inherit; }
    /* Every row wears its KPI card's colour, always on — the hover tint of
       the other tables, made permanent and per-category here only. */
    .mfp-table tbody tr.mfp-row { background: var(--mfp-a, #fff); box-shadow: inset 3px 0 0 var(--mfp-ring, transparent); }
    .mfp-table tbody tr.mfp-row:hover { background: var(--mfp-b, #eef2ff); box-shadow: inset 3px 0 0 var(--mfp-ring, #6366f1); }
    .mfp-table tbody tr.mfp-row:has(+ .mfp-journey-row) { cursor: pointer; }   /* a click opens its tracker */
    .mfp-table .mfp-clip { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mfp-table .mfp-nowrap { white-space: nowrap; }
    .mfp-table .mfp-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .mfp-table th.mfp-num, .mfp-table th.mfp-center { white-space: normal; }
    .mfp-table th.mfp-num { text-align: right; }
    .mfp-table .mfp-center { text-align: center; }
    .mfp-table .mfp-sl { color: #94a3b8; font-size: .75rem; }
    .mfp-table .mfp-ico { color: #b6c0cf; font-size: .62rem; width: 12px; margin-right: 4px; text-align: center; }
    .mfp-table a.mfp-passenger { color: #1e293b; font-weight: 700; }
    .mfp-table .mfp-party, .mfp-table a.mfp-party { color: #1e293b; font-weight: 400; }   /* black, regular (owner, 2026-10-03) */
    .mfp-table .mfp-agent-ico { color: #6366f1; font-size: .65rem; margin-right: 5px; vertical-align: 1px; }   /* the flights table's agent icon */
    .mfp-table .mfp-pnr { font-weight: 500; color: #4f46e5; }
    .mfp-table .mfp-date { color: #1e293b; font-weight: 600; }
    .mfp-table .mfp-legs { display: inline-flex; align-items: center; gap: 4px; font-size: .68rem; font-weight: 600; color: #b45309; background: #fffbeb; border: 1px solid #fde68a; border-radius: 999px; padding: 1px 8px 1px 6px; margin-bottom: 3px; white-space: nowrap; }
    .mfp-table .mfp-legs.done { color: #047857; background: #ecfdf5; border-color: #a7f3d0; }
    .mfp-table .mfp-leg-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #e5e7eb; margin-left: 2px; }
    .mfp-table .mfp-legs.done .mfp-leg-dot.on, .mfp-table .mfp-leg-dot.on { background: currentColor; }
    .mfp-table .mfp-leg-no { display: inline-block; font-size: .6rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #6366f1; background: #eef2ff; border-radius: 4px; padding: 0 5px; margin-right: 4px; }
    .mfp-table .mfp-ticket-missing { opacity: .7; }
    /* Charge & Cost Breakdown, one cell (owner, 2026-10-01): label left,
       figure right, the charge on top and the cost lines under a rule. */
    .mfp-table .mfp-cb { display: grid; grid-template-columns: minmax(0, 1fr) auto; column-gap: 5px; row-gap: 1px; line-height: 1.3; }
    .mfp-table .mfp-cb .k .mfp-ico { display: none; }   /* narrow column: the words carry it */
    .mfp-table .mfp-cb .k { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .7rem; color: #64748b; }
    .mfp-table .mfp-cb .v { text-align: right; white-space: nowrap; font-size: .72rem; font-weight: 600; color: #334155; font-variant-numeric: tabular-nums; }
    .mfp-table .mfp-cb > .k:first-child { font-size: .64rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #6366f1; align-self: center; }
    .mfp-table .mfp-cb > .v.mfp-detail { font-size: .74rem; font-weight: 500; color: #4f46e5; }   /* owner: smaller, normal weight */
    .mfp-table .mfp-cb .sub { font-size: .66rem; font-weight: 600; }
    .mfp-table .mfp-cb .k.mfp-due, .mfp-table .mfp-cb .v.mfp-due { color: #dc2626; }
    .mfp-table .mfp-cb .k.mfp-clear, .mfp-table .mfp-cb .v.mfp-clear { color: #059669; }
    /* Change lines, one per ticket and event (2026-10-10): "PNR Reissue 1 … amount" on one row;
       the left edge says who bears it — indigo = agent, amber = ours. Full text in the tooltip. */
    .mfp-table .mfp-cb .k.mfp-chg { font-size: .64rem; color: #475569; padding-left: 5px; border-left: 3px solid #cbd5e1; }
    .mfp-table .mfp-cb .k.mfp-chg.agent { border-left-color: #6366f1; }
    .mfp-table .mfp-cb .k.mfp-chg.company { border-left-color: #f59e0b; }
    .mfp-table .mfp-cb .mfp-chg-d { display: none; }
    .mfp-table .mfp-cb.mfp-open .mfp-chg-d { display: block; }
    .mfp-table .mfp-cb .mfp-chg-sum { cursor: pointer; }
    .mfp-table .mfp-cb .k.mfp-chg-sum.company { padding-left: 15px; }
    .mfp-table .mfp-cb .mfp-chg-caret { display: inline-block; width: 8px; margin-right: 2px; transition: transform .15s; }
    .mfp-table .mfp-cb.mfp-open .mfp-chg-caret { transform: rotate(90deg); }
    .mfp-table .mfp-cb .k.mfp-chg-pnr { grid-column: 1 / -1; font-size: .64rem; font-weight: 700; color: #1e293b; margin-top: 2px; }
    .mfp-table .mfp-cb .k.mfp-chg b { color: #1e293b; font-weight: 700; }
    .mfp-table .mfp-cb .v.mfp-chg-v { font-size: .64rem; color: #b91c1c; }
    .mfp-table .mfp-cb .v.mfp-chg-v.info { color: #64748b; font-weight: 500; }
    .mfp-table .mfp-cb .k.mfp-chg-sum { font-weight: 700; margin-top: 2px; padding-left: 5px; border-left: 3px solid; }
    .mfp-table .mfp-cb .k.mfp-chg-sum.agent { color: #3730a3; border-left-color: #6366f1; }
    .mfp-table .mfp-cb .k.mfp-chg-sum.company { color: #92400e; border-left-color: #f59e0b; margin-top: 0; }
    .mfp-table .mfp-cb .v.mfp-chg-sum { margin-top: 2px; color: #dc2626; font-weight: 700; }
    .mfp-table .mfp-cb .rule { grid-column: 1 / -1; border-top: 1px dashed #cbd5e1; margin: 3px 0 2px; }
    .mfp-table .mfp-leg-pill { margin: 0 4px 0 0; padding: 0 6px 0 5px; font-size: .64rem; vertical-align: 1px; }
    .mfp-table .mfp-ticket-missing .mfp-pnr { color: #b45309; font-style: italic; }
    .mfp-table .mfp-cost { color: #2563eb; }
    .mfp-table .mfp-detail { color: #6366f1; }
    .mfp-table .mfp-due { color: #dc2626; }
    .mfp-table .mfp-clear { color: #059669; }
    .mfp-table .mfp-ticket { margin-bottom: 6px; }
    .mfp-table .mfp-ticket:last-child { margin-bottom: 0; }
    .mfp-status { display: inline-block; padding: 3px 8px; border-radius: 6px; background: #fffbeb; color: #b45309; font-size: .7rem; font-weight: 600; }
    .mfp-status.confirm { background: #ecfdf5; color: #047857; }
    .mfp-status.cancelled { background: #fef2f2; color: #b91c1c; }
    .mfp-stage { display: inline-block; max-width: 100%; padding: 3px 8px; border-radius: 999px; background: #eef2ff; color: #4338ca; font-size: .7rem; font-weight: 700; line-height: 1.25; }
    .mfp-flag-tag { display: inline-flex !important; align-items: center; gap: 4px; margin-top: 4px !important; padding: 2px 7px; border-radius: 999px; background: #fff1f2; color: #be123c !important; font-weight: 600; white-space: nowrap; max-width: 100%; overflow: hidden; text-overflow: ellipsis; box-sizing: border-box; }
    .mfp-flag { display: inline-block; width: 15px; height: 10px; border-radius: 2px; object-fit: cover; vertical-align: -1px; }
    .mfp-empty { text-align: center; padding: 48px 16px; }
    /* Row actions: two 24px buttons per row, same as every other ERP table (2026-09-27). */
    .mfp-actions { display: grid; grid-template-columns: repeat(2, 24px); gap: 4px; width: max-content; margin: 0 auto; }
    .mfp-table .mfp-table-empty, .mfp-table td.mfp-empty { overflow-wrap: normal; }
    .mfp-table td:last-child { padding-left: 6px; padding-right: 6px; }
    .mfp-btn { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; border: 1px solid; font-size: 11px; cursor: pointer; transition: background-color .15s ease; }
    .mfp-btn.blue { border-color: #bfdbfe; background: #eff6ff; color: #2563eb; } .mfp-btn.blue:hover { background: #dbeafe; }
    .mfp-btn.amber { border-color: #fde68a; background: #fffbeb; color: #d97706; } .mfp-btn.amber:hover { background: #fef3c7; }
    .mfp-btn.violet { border-color: #ddd6fe; background: #f5f3ff; color: #7c3aed; } .mfp-btn.violet:hover { background: #ede9fe; }
    .mfp-btn.green { border-color: #a7f3d0; background: #ecfdf5; color: #059669; } .mfp-btn.green:hover { background: #d1fae5; }
    .mfp-btn.red { border-color: #fecaca; background: #fef2f2; color: #dc2626; } .mfp-btn.red:hover { background: #fee2e2; }
</style>
<style>
/* two-ticket rows: one line per ticket / leg (2026-09-21) */
.mfp-status.hold{background:#e0f2fe;color:#0369a1;}
.mfp-leg-route{padding:3px 0;} .mfp-leg-route + .mfp-leg-route{border-top:1px dashed #e2e8f0;margin-top:3px;}
.mfp-leg-route .mfp-leg-no{margin-bottom:2px;}
/* Via-route ticket cells: shared with the dashboard's Passenger Ledger. */
@include('contract-flights.partials.ticket-legs-styles')
</style>
<script>
// Change lines open/close on a click of the Agent pays / We bear sums (2026-10-10).
// Delegated once; stops the click so the row's own handlers do not also fire.
if (!window.mfpChgToggle) {
    window.mfpChgToggle = true;
    document.addEventListener('click', function (e) {
        const sum = e.target.closest('.mfp-cb .mfp-chg-sum');
        if (!sum) return;
        e.stopPropagation();
        sum.closest('.mfp-cb').classList.toggle('mfp-open');
    }, true);
}
</script>
