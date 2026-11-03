@extends('layout.app')
@section('meta-information')
    <title>Ticket Dashboard</title>
@endsection
@section('css')
<style>
    /* ══════════════════════════════════════════════════════════════════════
       TICKET DASHBOARD (2026-10-04) — the Flight Dashboard's look, applied to
       the ticket desk. Everything is tkd- prefixed except the standard
       filter bar (.fdl-*), which is repeated under its own class names so the
       register looks exactly like every other table in the ERP.

       Reading order is the desk's order of urgency:
         1. banner      this month at a glance
         2. radar       what costs money if nobody acts today
         3. departures  who is flying, grouped by flight like an airport board
         4. insights    trend · where tickets went · airlines · suppliers · fares
         5. register    every ticket, with the standard filter bar
       ══════════════════════════════════════════════════════════════════════ */

    /* ── 1. Banner: the Flight Dashboard's summary strip ─────────────────── */
    .tkd-banner {
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px 16px;
        padding: 10px 14px; margin-bottom: 12px; border-radius: 14px; color: #fff;
        background: linear-gradient(110deg, #4338ca, #5b4ddd 65%, #6d42d8);
        box-shadow: 0 6px 18px #4338ca22;
    }
    .tkd-stats { display: flex; flex-wrap: wrap; gap: 8px; }
    .tkd-stat {
        display: inline-flex; align-items: baseline; gap: 7px; padding: 5px 11px; border-radius: 8px;
        border: 1px solid #ffffff30; background: linear-gradient(180deg, #ffffff24, #ffffff10);
        box-shadow: 0 3px 6px #24166b26, inset 0 1px 0 #ffffff20; color: #fff; font: inherit; cursor: pointer;
        transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
    }
    .tkd-stat:hover { transform: translateY(-2px); background-color: #ffffff12; box-shadow: 0 5px 10px #24166b33, inset 0 1px 0 #ffffff30; }
    .tkd-stat .val { font-size: 16px; font-weight: 700; letter-spacing: -.02em; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .tkd-stat .lbl { font-size: 10px; text-transform: uppercase; letter-spacing: .045em; color: #eef2ff; white-space: nowrap; }
    .tkd-stat .tkd-delta { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 20px; background: #ffffff26; }
    .tkd-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
    .tkd-add {
        display: inline-flex; align-items: center; gap: 6px; height: 28px; padding: 0 11px; border-radius: 7px;
        background: #fff; color: #4338ca; font-size: 11px; font-weight: 700; white-space: nowrap; text-decoration: none;
        transition: background .15s ease, transform .15s ease;
    }
    .tkd-add:hover { background: #eef2ff; transform: translateY(-1px); text-decoration: none; color: #4338ca; }
    .tkd-clock { font-size: 11px; color: #e0e7ff; font-variant-numeric: tabular-nums; white-space: nowrap; }

    /* ── 2. Radar: six tiles, one fact each ──────────────────────────────── */
    .tkd-radar { display: grid; grid-template-columns: repeat(auto-fill, minmax(158px, 1fr)); gap: 8px; margin-bottom: 12px; }
    .tkd-tile {
        --t: #64748b; --t-bg: #f1f5f9;
        position: relative; display: block; text-align: left; width: 100%; overflow: hidden;
        padding: 10px 12px 9px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        box-shadow: 0 1px 3px #0f172a08; cursor: pointer; font: inherit; color: inherit;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .tkd-tile::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 3px; background: var(--t); opacity: .9; }
    .tkd-tile:hover { transform: translateY(-2px); border-color: color-mix(in srgb, var(--t) 40%, #e2e8f0); box-shadow: 0 6px 16px #0f172a10; }
    .tkd-tile.is-calm { --t: #cbd5e1; --t-bg: #f8fafc; }
    .tkd-tile-top { display: flex; align-items: center; gap: 8px; }
    .tkd-tile-ic {
        flex: 0 0 26px; width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
        background: var(--t-bg); color: var(--t); font-size: 11px;
    }
    .tkd-tile.is-calm .tkd-tile-ic { color: #94a3b8; }
    .tkd-tile-lbl { font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #64748b; line-height: 1.2; }
    .tkd-tile-val { margin-top: 7px; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .tkd-tile.is-calm .tkd-tile-val { color: #94a3b8; }
    .tkd-tile-val small { font-size: 12px; font-weight: 700; color: #64748b; margin-left: 4px; }
    .tkd-tile-sub { margin-top: 3px; font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tkd-tile-sub b { color: var(--t); font-weight: 700; }
    .tkd-red { color: #dc2626 !important; }
    .tkd-green { color: #059669 !important; }

    /* ── Panels ──────────────────────────────────────────────────────────── */
    .tkd-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 3px #0f172a06; min-width: 0; }
    .tkd-panel-head { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-bottom: 1px solid #eef2f7; }
    .tkd-panel-head h3 { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 13.5px; font-weight: 700; color: #1e293b; white-space: nowrap; }
    .tkd-panel-head h3 i { color: #6366f1; font-size: 12px; }
    .tkd-panel-head .tkd-meta { margin-left: auto; font-size: 11px; color: #94a3b8; white-space: nowrap; }
    .tkd-panel-body { padding: 10px 14px 12px; }
    .tkd-seg { display: inline-flex; margin-left: auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff; flex: 0 0 auto; }
    .tkd-seg button { border: 0; border-left: 1px solid #e2e8f0; background: none; padding: 0 10px; height: 28px; cursor: pointer; font-size: 11px; font-weight: 600; color: #64748b; white-space: nowrap; }
    .tkd-seg button:first-child { border-left: 0; }
    .tkd-seg button:hover { background: #f1f5f9; color: #334155; }
    .tkd-seg button.is-on { background: #eef2ff; color: #4338ca; font-weight: 700; }
    .tkd-empty { padding: 26px 10px; text-align: center; color: #94a3b8; font-size: 12px; }
    .tkd-empty i { display: block; font-size: 22px; color: #cbd5e1; margin-bottom: 6px; }

    /* Airline logo on a white floating tile; flags at text height. */
    .tkd-logo {
        flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; overflow: hidden;
        width: 30px; height: 30px; border-radius: 8px; background: #fff; border: 1px solid #eef2f7;
        box-shadow: 0 2px 6px #0f172a12; font-size: 9px; font-weight: 800; color: #fff;
    }
    .tkd-logo img { width: 78%; height: 78%; object-fit: contain; }
    .tkd-logo.sm { width: 22px; height: 22px; border-radius: 6px; font-size: 7.5px; }
    .tkd-flag { width: 16px; height: 11px; object-fit: cover; border-radius: 2px; box-shadow: 0 0 0 1px #0f172a14; vertical-align: -1px; }
    .tkd-route { display: inline-flex; align-items: center; gap: 5px; font-weight: 700; color: #1e293b; white-space: nowrap; }
    .tkd-route .tkd-plane { color: #6366f1; font-size: 10px; }
    .tkd-pnr { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #4338ca; letter-spacing: .02em; }
    .tkd-pnr .sfx { font-weight: 500; color: #a5b4fc; font-size: .85em; }
    .tkd-tick { display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background: #10b981; color: #fff; font-size: 8px; flex: 0 0 auto; }
    .tkd-ring { display: inline-block; width: 14px; height: 14px; border-radius: 50%; border: 2px solid #cbd5e1; flex: 0 0 auto; }
    .tkd-tag { display: inline-flex; align-items: center; gap: 4px; padding: 1px 7px; border-radius: 20px; font-size: 10px; font-weight: 700; white-space: nowrap; }
    .tkd-tag.hold { background: #eef2ff; color: #4338ca; }
    .tkd-tag.warn { background: #fef3c7; color: #b45309; }
    .tkd-tag.bad { background: #fee2e2; color: #b91c1c; }

    /* ── 3. Departures board ─────────────────────────────────────────────── */
    .tkd-board-day { display: flex; align-items: center; gap: 8px; padding: 9px 14px 5px; font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #6366f1; }
    .tkd-board-day::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, #e0e7ff, transparent); }
    .tkd-board-day.is-today { color: #dc2626; }
    .tkd-flt {
        display: grid; grid-template-columns: 92px 34px minmax(170px, 1.25fr) minmax(150px, 1fr) minmax(110px, .8fr) 118px;
        align-items: center; gap: 12px; padding: 8px 14px; border-top: 1px solid #f1f5f9; cursor: pointer;
        transition: background .15s ease, box-shadow .15s ease;
    }
    .tkd-flt:hover { background: #f5f7ff; box-shadow: inset 3px 0 0 #6366f1; }
    .tkd-flt-time b { display: block; font-size: 15px; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
    .tkd-flt-time span { font-size: 10.5px; font-weight: 700; color: #6366f1; }
    .tkd-flt-time span.is-near { color: #dc2626; }
    .tkd-flt-line2 { font-size: 11px; color: #94a3b8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tkd-flt-pax { font-size: 12px; color: #334155; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tkd-flt-state { display: flex; align-items: center; justify-content: flex-end; gap: 6px; font-size: 11px; font-weight: 700; color: #059669; white-space: nowrap; }
    .tkd-flt-state.is-part { color: #b45309; }
    .tkd-flt-seats { display: flex; gap: 3px; justify-content: flex-end; margin-top: 4px; }
    .tkd-flt-seats i { width: 8px; height: 8px; border-radius: 2px; background: #10b981; }
    .tkd-flt-seats i.hold { background: #a5b4fc; }
    .tkd-flt-seats i.gone { background: #fca5a5; }
    .tkd-flt-detail { display: none; padding: 4px 14px 10px 152px; background: #fafbff; border-top: 1px dashed #e0e7ff; }
    .tkd-flt.is-open + .tkd-flt-detail { display: block; }
    .tkd-flt-detail table { width: 100%; font-size: 11.5px; border-collapse: collapse; }
    .tkd-flt-detail td { padding: 4px 8px 4px 0; color: #475569; border-bottom: 1px solid #eef2f7; white-space: nowrap; }
    .tkd-more { display: block; width: 100%; padding: 8px; border: 0; border-top: 1px solid #f1f5f9; background: #fafbff; color: #4f46e5; font-size: 11.5px; font-weight: 700; cursor: pointer; }
    .tkd-more:hover { background: #eef2ff; }

    /* ── 4. Insights ─────────────────────────────────────────────────────── */
    .tkd-section-head { display: flex; align-items: center; gap: 10px; margin: 16px 2px 8px; }
    .tkd-section-head h2 { margin: 0; font-size: 14px; font-weight: 800; color: #1e293b; letter-spacing: -.01em; }
    .tkd-section-head p { margin: 0; font-size: 11px; color: #94a3b8; }
    .tkd-grid { display: grid; gap: 10px; margin-bottom: 10px; }
    .tkd-grid.g-21 { grid-template-columns: 2fr 1fr; }
    .tkd-grid.g-111 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    @media (max-width: 1100px) { .tkd-grid.g-21, .tkd-grid.g-111 { grid-template-columns: 1fr; } }

    .tkd-trend-sum { display: flex; gap: 18px; margin-bottom: 8px; }
    .tkd-trend-sum div { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; font-weight: 700; }
    .tkd-trend-sum b { display: block; font-size: 16px; color: #0f172a; letter-spacing: -.02em; text-transform: none; font-variant-numeric: tabular-nums; }
    .tkd-trend svg { display: block; width: 100%; height: 150px; overflow: visible; }
    .tkd-trend .bar { fill: #c7d2fe; transition: fill .15s; }
    .tkd-trend .bar.is-now { fill: #6366f1; }
    .tkd-trend .bar-g:hover .bar { fill: #4338ca; }
    .tkd-trend .axis { font-size: 9.5px; fill: #94a3b8; }
    .tkd-trend .grid { stroke: #eef2f7; }
    .tkd-trend .cost { fill: none; stroke: #f59e0b; stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; }
    .tkd-trend .cost-dot { fill: #fff; stroke: #f59e0b; stroke-width: 1.5; }
    .tkd-legend { display: flex; gap: 12px; font-size: 10.5px; color: #64748b; margin-top: 6px; }
    .tkd-legend i { display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin-right: 4px; vertical-align: -1px; }

    .tkd-flow-bar { display: flex; height: 12px; border-radius: 6px; overflow: hidden; background: #f1f5f9; margin: 4px 0 10px; }
    .tkd-flow-bar span { height: 100%; transition: width .3s ease; }
    .tkd-flow-row { display: grid; grid-template-columns: 12px 1fr auto auto; gap: 8px; align-items: center; padding: 6px 0; border-top: 1px solid #f1f5f9; font-size: 12px; cursor: pointer; }
    .tkd-flow-row:hover { background: #fafbff; }
    .tkd-flow-row i { width: 10px; height: 10px; border-radius: 3px; }
    .tkd-flow-row .n { font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; }
    .tkd-flow-row .a { color: #64748b; font-variant-numeric: tabular-nums; min-width: 92px; text-align: right; }
    .tkd-flow-row small { display: block; font-size: 10.5px; color: #94a3b8; }

    .tkd-rank { display: grid; align-items: center; gap: 10px; padding: 7px 0; border-top: 1px solid #f1f5f9; font-size: 12px; }
    .tkd-rank:first-child { border-top: 0; }
    .tkd-rank.air { grid-template-columns: 30px 1fr 74px; }
    .tkd-rank.sup { grid-template-columns: 1fr auto; }
    .tkd-rank.fare { grid-template-columns: 1fr auto auto; }
    .tkd-rank-name { font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tkd-rank-sub { font-size: 10.5px; color: #94a3b8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tkd-rank-num { text-align: right; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .tkd-rank-num small { display: block; font-size: 10.5px; font-weight: 500; color: #94a3b8; }
    .tkd-share { height: 5px; border-radius: 3px; background: #eef2ff; margin-top: 4px; overflow: hidden; }
    .tkd-share span { display: block; height: 100%; border-radius: 3px; background: linear-gradient(90deg, #6366f1, #8b5cf6); }
    .tkd-trendchip { font-size: 10.5px; font-weight: 800; white-space: nowrap; }
    .tkd-portal-tag { font-size: 9.5px; font-weight: 700; color: #7c3aed; background: #f5f3ff; padding: 0 6px; border-radius: 10px; margin-left: 4px; vertical-align: 1px; }

    /* ── 5. Register — the standard filter bar, repeated by name ─────────── */
    .fdl-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 11px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .fdl-search { position: relative; flex: 1 1 260px; min-width: 200px; }
    .fdl-search > i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; pointer-events: none; }
    .fdl-search input { width: 100%; height: 34px; padding: 0 30px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; font-size: 12.5px; color: #1e293b; outline: none; transition: border-color .15s, box-shadow .15s; }
    .fdl-search input::placeholder { color: #a3adbb; }
    .fdl-search input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    .fdl-clear { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: 0; background: none; padding: 4px; cursor: pointer; color: #cbd5e1; font-size: 12px; line-height: 1; display: none; }
    .fdl-clear:hover { color: #64748b; }
    .fdl-search.has-q .fdl-clear { display: block; }
    .fdl-seg { display: flex; flex: 0 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff; }
    .fdl-seg button { border: 0; border-left: 1px solid #e2e8f0; background: none; padding: 0 11px; height: 34px; cursor: pointer; font-size: 11.5px; font-weight: 600; color: #64748b; white-space: nowrap; transition: background .15s, color .15s; }
    .fdl-seg button:first-child { border-left: 0; }
    .fdl-seg button:hover { background: #f1f5f9; color: #334155; }
    .fdl-seg button.is-on { background: #eef2ff; color: #4338ca; font-weight: 700; }
    .fdl-dates { display: flex; flex: 0 0 auto; align-items: center; gap: 6px; }
    .fdl-dates span { font-size: 11px; color: #94a3b8; }
    .fdl-date { position: relative; display: inline-flex; align-items: center; height: 34px; padding-right: 5px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; transition: border-color .15s, background .15s; }
    .fdl-date:focus-within { border-color: #6366f1; }
    .fdl-dates input.fdl-date-text { width: 88px; height: 32px; padding: 0 2px 0 9px; border: 0; background: none; outline: none; font-size: 12px; color: #334155; }
    .fdl-date.is-bad { border-color: #fca5a5; background: #fef2f2; }
    .fdl-dates input.fdl-date-native { width: 22px; height: 22px; flex: 0 0 auto; margin: 0; padding: 0; border: 0; background: none; color: transparent; overflow: hidden; cursor: pointer; }
    .fdl-date-native::-webkit-datetime-edit, .fdl-date-native::-webkit-inner-spin-button, .fdl-date-native::-webkit-clear-button { display: none; }
    .fdl-date-native::-webkit-calendar-picker-indicator { width: 22px; height: 22px; margin: 0; padding: 0; opacity: .5; cursor: pointer; transition: opacity .15s; }
    .fdl-date:hover .fdl-date-native::-webkit-calendar-picker-indicator, .fdl-date-native:focus::-webkit-calendar-picker-indicator { opacity: .9; }
    .fdl-go { height: 34px; padding: 0 12px; border: 0; border-radius: 8px; cursor: pointer; background: #4f46e5; color: #fff; font-size: 11.5px; font-weight: 700; }
    .fdl-go:hover { background: #4338ca; }
    .fdl-print { height: 34px; padding: 0 12px; border: 1px solid #e2e8f0; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; background: #fff; color: #475569; font-size: 11.5px; font-weight: 700; cursor: pointer; transition: background .15s, color .15s, border-color .15s; }
    .fdl-print:hover { background: #f8fafc; color: #334155; border-color: #cbd5e1; }
    .fdl-print i { font-size: 11px; }
    .fdl-count { flex-shrink: 0; font-size: 11px; font-weight: 700; color: #4338ca; background: #eef2ff; border-radius: 20px; padding: 4px 10px; white-space: nowrap; }
    .fdl-count.is-none { color: #b91c1c; background: #fef2f2; }

    .tkd-table-scroll { overflow-x: auto; }
    .tkd-table { width: 100%; min-width: 980px; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
    .tkd-table th { background: #eef2ff; color: #4338ca; padding: 11px 10px; border-bottom: 1px solid #c7d2fe; text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
    .tkd-table th.num, .tkd-table td.num { text-align: right; }
    .tkd-table td { padding: 8px 10px; border-bottom: 1px solid #e5eaf1; color: #334155; vertical-align: middle; height: 58px; }
    .tkd-table tbody tr { transition: background-color .15s ease, box-shadow .15s ease; }
    .tkd-table tbody tr:hover { background: #eef2ff; box-shadow: inset 3px 0 0 #6366f1; }
    .tkd-sort { display: inline-flex; align-items: center; gap: 5px; border: 0; background: none; padding: 0; color: inherit; font: inherit; text-transform: inherit; letter-spacing: inherit; cursor: pointer; }
    .tkd-sort i { opacity: .45; font-size: 9px; }
    th[aria-sort="ascending"] .tkd-sort i, th[aria-sort="descending"] .tkd-sort i { opacity: 1; }
    .tkd-c1 { font-weight: 700; color: #1e293b; white-space: nowrap; }
    .tkd-c2 { font-size: 11px; color: #94a3b8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
    .tkd-air-pill { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; padding: 1px 7px 1px 2px; border-radius: 20px; background: #fff; border: 1px solid #e2e8f0; font-size: 10.5px; font-weight: 700; color: #475569; }
    .tkd-use-cf { color: #4f46e5; font-weight: 700; }
    .tkd-use-sale { color: #059669; font-weight: 700; }
    .tkd-use-idle { color: #b45309; font-weight: 700; }
    .tkd-use-cx { color: #dc2626; font-weight: 700; }
    .tkd-act { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; border: 1px solid #e2e8f0; color: #475569; font-size: 11px; background: #fff; }
    .tkd-act:hover { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
    #tkdRegister { scroll-margin-top: 90px; }
    #tkdBoard { scroll-margin-top: 90px; }

    html.dark .tkd-panel, html.dark .tkd-tile { background: #1e293b; border-color: #334155; }
    html.dark .tkd-panel-head h3, html.dark .tkd-tile-val, html.dark .tkd-c1, html.dark .tkd-rank-name, html.dark .tkd-section-head h2, html.dark .tkd-flt-time b { color: #e2e8f0; }
    @media (max-width: 760px) {
        .tkd-flt { grid-template-columns: 78px 30px 1fr; }
        .tkd-flt > :nth-child(n+4) { grid-column: 3; }
        .tkd-flt-detail { padding-left: 14px; }
    }
    @media (prefers-reduced-motion: reduce) { .tkd-stat, .tkd-tile { transition: none; } .tkd-stat:hover, .tkd-tile:hover { transform: none; } }

    /* Calmer type (owner, 2026-10-04: "doesn't match the font with the ERP,
       a bit more dark, bold, looks a bit over"): the ERP's own weights and
       slate ink, normal tracking, the font's own digits. */
    .tkd-stat .val, .tkd-tile-val, .tkd-flt-time b, .tkd-trend-sum b, .tkd-flow-row .n, .tkd-rank-num,
    .tkd-route, .tkd-c1, .tkd-rank-name, .tkd-flt-pax, .tkd-section-head h2, .tkd-panel-head h3 {
        letter-spacing: normal; font-variant-numeric: normal;
    }
    .tkd-stat .val { font-weight: 600; }
    .tkd-tile-val { font-size: 18px; font-weight: 600; color: #334155; }
    .tkd-flt-time b { font-size: 13.5px; font-weight: 600; color: #334155; }
    .tkd-route { font-weight: 600; color: #334155; }
    .tkd-trend-sum b { font-size: 15px; font-weight: 600; color: #334155; }
    .tkd-flow-row .n, .tkd-rank-num { font-weight: 600; color: #334155; }
    .tkd-c1, .tkd-rank-name { font-weight: 600; color: #334155; }
    .tkd-flt-pax { font-weight: 500; }
    .tkd-section-head h2 { font-weight: 700; color: #334155; }
    .tkd-panel-head h3 { font-weight: 600; color: #334155; }
    .tkd-board-day, .tkd-trendchip { font-weight: 700; }
    .tkd-pnr { font-weight: 600; }
    /* Connected leg: hangs off the leg before it on a dotted line. */
    .tkd-conn { position: relative; height: 12px; margin-left: 40px; border-left: 2px dotted #c7d2fe; }
    .tkd-conn i { position: absolute; left: -7px; top: 50%; transform: translateY(-50%); width: 12px; height: 12px; border-radius: 50%; background: #fff; color: #818cf8; font-size: 7.5px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 1px #e0e7ff; }
    .tkd-conn.is-same i { color: #cbd5e1; box-shadow: none; }
    .tkd-flt-time b.is-none { font-size: 13px; color: #cbd5e1; letter-spacing: .1em; }
    .tkd-flt-time span.is-date { color: #94a3b8; font-weight: 600; }
    .tkd-board-day { padding-top: 12px; font-size: 10px; letter-spacing: .08em; }
    .tkd-flt.is-conn { border-top: 0; }
    .tkd-conn.is-same { border-left-color: #e2e8f0; }
    /* One journey = one card (owner, 2026-10-04: "whose part is this? make
       each pair easy to tell apart"). Day headings sit between cards. */
    #tkdBoardBody { padding-bottom: 8px; }
    .tkd-journey { margin: 4px 12px 8px; border: 1px solid #e6e9f2; border-radius: 12px; background: #fff; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.03); }
    .tkd-journey:hover { border-color: #c7d2fe; box-shadow: 0 4px 14px rgba(79,70,229,.08); }
    .tkd-journey > .tkd-flt:first-child { border-top: 0; }
    .tkd-journey .tkd-flt { padding-left: 12px; padding-right: 12px; }
    .tkd-journey .tkd-conn { margin-left: 34px; }
    .tkd-board-day { padding: 12px 14px 2px; }
    /* Journey card header — who the pair belongs to. */
    .tkd-jh { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 7px 14px; background: #fafbff; border-bottom: 1px solid #eef0f8; font-size: 11.5px; color: #64748b; }
    .tkd-jh .dot { font-style: normal; color: #cbd5e1; }
    .tkd-jh i.fas { font-size: 10px; color: #a5b4fc; margin-right: 2px; }
    .tkd-jh-cf { color: #4338ca; font-weight: 700; }
    .tkd-jh-cf.is-none { color: #94a3b8; font-weight: 600; }
    .tkd-journey > .tkd-jh + .tkd-flt { border-top: 0; }
    /* Journey cards v2: every row carries its own date (no day headings). */
    .tkd-flt-time b { font-size: 12.5px; }
    .tkd-flt-time span { color: #64748b; font-weight: 600; }
    .tkd-flt-time span i.is-none { font-style: normal; color: #cbd5e1; font-weight: 500; }
    .tkd-jh-pair { color: #6366f1; font-weight: 600; }
    #tkdBoardBody { padding-top: 6px; }
    /* Clickable identifiers: look like text, underline on hover. */
    a.tkd-lnk { color: inherit; text-decoration: none; }
    a.tkd-lnk:hover { text-decoration: underline; text-underline-offset: 2px; color: #4338ca; }

    .tkd-use-ic { font-size: 10px; color: #818cf8; margin-right: 3px; }
    /* Passenger list under an opened flight. */
    .tkd-flt-detail { padding: 6px 14px 10px 58px; background: #fbfbfe; border-top: 1px solid #eef0f8; }
    .tkd-px { display: grid; grid-template-columns: 26px minmax(160px, 1.4fr) 120px minmax(110px, 1fr) 90px 70px 28px; align-items: center; gap: 10px; padding: 6px 8px; border-radius: 8px; font-size: 12px; }
    .tkd-px + .tkd-px { border-top: 1px solid #f1f3f9; }
    .tkd-px:hover { background: #f5f6ff; }
    .tkd-px-av { width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #eef2ff; color: #4f46e5; font-size: 11px; font-weight: 700; }
    .tkd-px-name b { display: block; font-weight: 600; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tkd-px-name small { display: block; font-size: 10.5px; color: #94a3b8; }
    .tkd-px-pnr { display: inline-flex; padding: 2px 8px; border-radius: 6px; background: #f5f3ff; border: 1px solid #ede9fe; font-size: 11px; }
    .tkd-px-seller { color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tkd-px-state { display: flex; justify-content: center; }
    .tkd-px-cf { color: #4338ca; font-weight: 600; font-size: 11.5px; }
    .tkd-px-act { display: flex; justify-content: flex-end; }
    .tkd-px-cx { font-size: 10.5px; font-weight: 600; color: #dc2626; }
    .tkd-px.is-gone { opacity: .62; }
    .tkd-px.is-gone .tkd-px-av { background: #fef2f2; color: #dc2626; }
    .tkd-px.is-gone .tkd-px-pnr { background: #fff; border-color: #fee2e2; text-decoration: line-through; text-decoration-color: #fca5a5; }
    .tkd-px-div { display: flex; align-items: center; gap: 8px; margin: 8px 0 2px; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #ef4444; }
    .tkd-px-div::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, #fecaca, transparent); }
    /* Time column: two calm lines, no wrapping; countdown in the tooltip. */
    .tkd-flt-time b, .tkd-flt-time span { white-space: nowrap; }
    .tkd-flt-time b.is-near { color: #dc2626; }
    .tkd-flt-time span { color: #64748b; font-size: 11.5px; }

</style>
@endsection

@section('main-content')
@include('layout.ticketing-tabs')
@php
    $tkdRole = request()->route('role');
    $tkdUrl = function (string $name, array $params = []) use ($tkdRole) {
        try {
            return \Illuminate\Support\Facades\Route::has($name) ? route($name, ['role' => $tkdRole] + $params) : null;
        } catch (\Throwable) {
            return null;
        }
    };
    $tkdTaka = '৳';
    $tkdDelta = $kpis['issued_prev'] > 0 ? round(($kpis['issued_mtd'] - $kpis['issued_prev']) / $kpis['issued_prev'] * 100) : null;
@endphp

{{-- 1. Banner — this month at a glance. Each pill jumps to where its number lives. --}}
<div class="tkd-banner">
    <div class="tkd-stats">
        <button type="button" class="tkd-stat" onclick="tkdRegisterFilter({ seg: 'all', month: true })" title="Tickets issued since the 1st (last month, same days: {{ $kpis['issued_prev'] }})">
            <span class="val">{{ $kpis['issued_mtd'] }}</span><span class="lbl">Issued this month</span>
            @if($tkdDelta !== null)<span class="tkd-delta">{{ $tkdDelta >= 0 ? '▲' : '▼' }} {{ abs($tkdDelta) }}%</span>@endif
        </button>
        <button type="button" class="tkd-stat" onclick="tkdRegisterFilter({ seg: 'all', month: true })" title="Ticket cost issued this month; a cancelled ticket counts only what the airline kept">
            <span class="val">{{ $tkdTaka }}{{ number_format($kpis['cost_mtd'], 0) }}</span><span class="lbl">Net cost this month</span>
        </button>
        <button type="button" class="tkd-stat" onclick="document.getElementById('tkdSuppliers').scrollIntoView({ behavior: 'smooth', block: 'center' })" title="Every unpaid ticket balance owed to vendors and portals, any age">
            <span class="val">{{ $tkdTaka }}{{ number_format($kpis['supplier_due'], 0) }}</span><span class="lbl">Supplier due</span>
        </button>
        <button type="button" class="tkd-stat" onclick="tkdRegisterFilter({ seg: 'hold' })" title="Seats on hold, waiting to be purchased">
            <span class="val">{{ $kpis['holds'] }}</span><span class="lbl">Active holds</span>
        </button>
        <button type="button" class="tkd-stat" onclick="document.getElementById('tkdBoard').scrollIntoView({ behavior: 'smooth' })" title="Ticket legs departing in the next 7 days">
            <span class="val">{{ $kpis['flying_7d'] }}</span><span class="lbl">Flying in 7 days</span>
        </button>
    </div>
    <div class="tkd-actions">
        <span class="tkd-clock" id="tkdClock"></span>
        @canany(['create ticket purchase', 'create ticket direct sale'])
            @if($u = $tkdUrl('role.ticket-direct-sale.create'))
                <a class="tkd-add" href="{{ $u }}"><i class="fas fa-plus"></i> Add Entry</a>
            @endif
        @endcanany
    </div>
</div>

{{-- 2. Radar — six things that cost money when nobody acts. Calm grey at zero. --}}
<section class="tkd-radar" id="tkdRadar" aria-label="Needs attention"></section>

{{-- 3. Departures — tickets grouped by flight (same day, sector, airline), like an airport board. --}}
<section class="tkd-panel" id="tkdBoard" style="margin-bottom:10px">
    <div class="tkd-panel-head">
        <h3><i class="fas fa-plane-departure"></i> Departures</h3>
        <span class="tkd-meta" id="tkdBoardMeta"></span>
        <div class="tkd-seg" id="tkdBoardSeg" style="margin-left:10px">
            <button type="button" data-v="next" class="is-on">Next 14 days</button>
            <button type="button" data-v="flown">Flown · last 14 days</button>
        </div>
    </div>
    <div id="tkdBoardBody"></div>
</section>

{{-- 4. Insights — counted in the browser from the same rows, per period. --}}
<div class="tkd-section-head">
    <div>
        <h2>Insights</h2>
        <p id="tkdPeriodNote"></p>
    </div>
    <div class="tkd-seg" id="tkdPeriod">
        <button type="button" data-p="30" class="is-on">30 days</button>
        <button type="button" data-p="90">90 days</button>
        <button type="button" data-p="365">12 months</button>
    </div>
</div>
<div class="tkd-grid g-21">
    <section class="tkd-panel">
        <div class="tkd-panel-head"><h3><i class="fas fa-chart-column"></i> Issuance</h3><span class="tkd-meta" id="tkdTrendMeta"></span></div>
        <div class="tkd-panel-body tkd-trend" id="tkdTrend"></div>
    </section>
    <section class="tkd-panel">
        <div class="tkd-panel-head"><h3><i class="fas fa-route"></i> Where tickets went</h3></div>
        <div class="tkd-panel-body" id="tkdFlow"></div>
    </section>
</div>
<div class="tkd-grid g-111">
    <section class="tkd-panel">
        <div class="tkd-panel-head"><h3><i class="fas fa-plane"></i> Airlines</h3><span class="tkd-meta">tickets · cost</span></div>
        <div class="tkd-panel-body" id="tkdAirlines"></div>
    </section>
    <section class="tkd-panel" id="tkdSuppliers">
        <div class="tkd-panel-head"><h3><i class="fas fa-store"></i> Suppliers</h3><span class="tkd-meta">cost · due</span></div>
        <div class="tkd-panel-body" id="tkdSupplierList"></div>
    </section>
    <section class="tkd-panel">
        <div class="tkd-panel-head"><h3><i class="fas fa-tags"></i> Fare watch</h3><span class="tkd-meta" title="Average ticket cost per sector, against the period before">avg cost · vs before</span></div>
        <div class="tkd-panel-body" id="tkdFares"></div>
    </section>
</div>

{{-- 5. Register — every ticket and open hold of the last 12 months, standard filter bar. --}}
<section class="tkd-panel" id="tkdRegister" style="margin-top:6px">
    <div class="tkd-panel-head">
        <h3><i class="fas fa-ticket-alt"></i> Ticket Register</h3>
        <span class="tkd-meta">last 12 months · newest first</span>
    </div>
    <div class="fdl-bar">
        <div class="fdl-search" id="tkdSearchWrap">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="tkdSearch" autocomplete="off" placeholder="Search PNR, passenger, passport, airline, route, seller or flight…" aria-label="Search tickets">
            <button type="button" class="fdl-clear" id="tkdClear" title="Clear search"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="fdl-seg" id="tkdSeg">
            <button type="button" data-s="purchased" title="Purchased tickets">Purchased</button>
            <button type="button" data-s="hold" title="Holds, drafts and pending tickets not purchased yet">Holds</button>
            <button type="button" data-s="idle" title="Purchased, but not sold and not on a contract flight">Idle</button>
            <button type="button" data-s="cancelled" title="Cancelled — refund and penalty">Cancelled</button>
            <button type="button" data-s="all" class="is-on">All</button>
        </div>
        <div class="fdl-dates" id="tkdDates">
            <span class="fdl-date">
                <input type="text" id="tkdFromText" class="fdl-date-text" placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Issued from" title="Issue date — from">
                <input type="date" id="tkdFrom" class="fdl-date-native" aria-label="Pick the from date" title="Pick a date">
            </span>
            <span>to</span>
            <span class="fdl-date">
                <input type="text" id="tkdToText" class="fdl-date-text" placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Issued to" title="Issue date — to">
                <input type="date" id="tkdTo" class="fdl-date-native" aria-label="Pick the to date" title="Pick a date">
            </span>
            <button type="button" class="fdl-go" id="tkdApply">Apply</button>
            <button type="button" class="fdl-print" id="tkdPrint" title="Print the tickets on screen (A4)"><i class="fas fa-print"></i> Print</button>
        </div>
        <span class="fdl-count" id="tkdCount" hidden></span>
    </div>
    <div class="tkd-table-scroll">
        <table class="tkd-table" id="tkdTable">
            <thead>
                <tr>
                    <th style="width:40px">SL</th>
                    <th data-k="issued" aria-sort="descending"><button type="button" class="tkd-sort">Issued <i class="fas fa-sort"></i></button></th>
                    <th data-k="passenger" aria-sort="none"><button type="button" class="tkd-sort">Passenger <i class="fas fa-sort"></i></button></th>
                    <th data-k="pnr" aria-sort="none"><button type="button" class="tkd-sort">Ticket <i class="fas fa-sort"></i></button></th>
                    <th data-k="route" aria-sort="none"><button type="button" class="tkd-sort">Route <i class="fas fa-sort"></i></button></th>
                    <th data-k="seller" aria-sort="none"><button type="button" class="tkd-sort">Seller <i class="fas fa-sort"></i></button></th>
                    <th data-k="use" aria-sort="none"><button type="button" class="tkd-sort">Used for <i class="fas fa-sort"></i></button></th>
                    <th data-k="cost" aria-sort="none" class="num"><button type="button" class="tkd-sort">Cost <i class="fas fa-sort"></i></button></th>
                    <th style="width:46px"></th>
                </tr>
            </thead>
            <tbody id="tkdBody"></tbody>
        </table>
    </div>
    <button type="button" class="tkd-more" id="tkdMore" hidden></button>
</section>
@endsection

@section('js')
<script>
(function () {
// ── Data (TicketDashboardController) ─────────────────────────────────────
const TKD = {
    tickets: @json($tickets),
    holds: @json($holds),
    radar: @json($radar),
    portals: @json($portals),
    now: new Date(@json($now)),
    urls: {!! json_encode([
        'orders' => $tkdUrl('role.ticket-purchase.index'),
        'holds' => $tkdUrl('role.ticket-purchase.index', ['view' => 'booking']),
        'schedules' => $tkdUrl('role.payment-schedules.index'),
        'party' => $tkdUrl('role.party-statement.index'),
        'flights' => $tkdUrl('role.contract-flights.index'),
        'sales' => $tkdUrl('role.ticket-sales.index'),
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!},
};
const FD_LOGO_BASE = @json(asset('airline-logos'));
const TKD_FLAGS = @json(asset('flags'));

// Airline marks — the same matcher and ink-trim as the Flight Dashboard.
@include('contract-flights.partials.airline-brands-script')
@include('contract-flights.partials.ticket-logos-script')

// ── Formatting ───────────────────────────────────────────────────────────
const MON = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const DOW = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const taka = n => '৳' + Math.round(Number(n) || 0).toLocaleString('en-US');
const parse = s => {
    if (!s) return null;
    if (s instanceof Date) return s;
    const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    return m && !/[zZ]|[+-]\d{2}:\d{2}$/.test(s) ? new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0)) : new Date(s);
};
const pad = n => String(n).padStart(2, '0');
const ymd = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
const hasTime = d => d && (d.getHours() || d.getMinutes());
// "01 Oct" · "01 Oct 2025" when not this year · ", 09:55 AM" when there is a time
function fmtDate(s, withTime = true) {
    const d = parse(s);
    if (!d || isNaN(d)) return '';
    let out = pad(d.getDate()) + ' ' + MON[d.getMonth()] + (d.getFullYear() !== TKD.now.getFullYear() ? ' ' + d.getFullYear() : '');
    if (withTime && hasTime(d)) out += ', ' + fmtTime(d);
    return out;
}
const fmtTime = d => { let h = d.getHours() % 12 || 12; return pad(h) + ':' + pad(d.getMinutes()) + ' ' + (d.getHours() < 12 ? 'AM' : 'PM'); };
function untilText(target) {
    let ms = parse(target) - new Date();
    const past = ms < 0; ms = Math.abs(ms);
    const d = Math.floor(ms / 864e5), h = Math.floor(ms % 864e5 / 36e5), m = Math.floor(ms % 36e5 / 6e4);
    const t = d ? d + 'd ' + h + 'h' : (h ? h + 'h ' + m + 'm' : m + 'm');
    return past ? t + ' ago' : 'in ' + t;
}
// "JK2564VB (a)" → the airline PNR bold, our group letter small and pale.
const pnrHtml = p => { const m = String(p || '').match(/^(.*?)(\s*\([a-z]+\))$/i); return p ? `<span class="tkd-pnr">${esc(m ? m[1] : p)}${m ? `<span class="sfx">${esc(m[2])}</span>` : ''}</span>` : '<span class="tkd-c2">No PNR</span>'; };
// Clickable identifiers (owner, 2026-10-04): PNR → the ticket, seller ID → its statement,
// CF → the flight, invoice → Manage Sales. No URL on the server → plain text.
const lnk = (url, html, title) => url ? `<a class="tkd-lnk" href="${esc(url)}" title="${esc(title || '')}">${html}</a>` : html;
const q = (base, k, v) => base ? base + (base.includes('?') ? '&' : '?') + k + '=' + encodeURIComponent(v) : null;
const flag = cc => cc ? `<img class="tkd-flag" src="${TKD_FLAGS}/${esc(cc)}.png" alt="" onerror="this.remove()">` : '';
const routeHtml = (f, t) => (f?.code || t?.code)
    ? `<span class="tkd-route" title="${esc((f?.name || '') + ' → ' + (t?.name || ''))}">${flag(f?.flag)}${esc(f?.code || '?')} <i class="fas fa-plane tkd-plane"></i> ${flag(t?.flag)}${esc(t?.code || '?')}</span>`
    : '<span class="tkd-c2">Route not set</span>';
const logo = (name, sm) => `<span class="tkd-logo${sm ? ' sm' : ''}" data-airline="${esc(name || '')}" title="${esc(name || 'Airline not set')}"></span>`;
const airCode = n => airlineCode(n) || String(n || '').replace(/\(.*\)/, '').trim().split(/\s+/).map(w => w[0]).join('').slice(0, 2).toUpperCase();

// Paint every unpainted logo tile: the trimmed mark, else initials on the brand colour.
function paintLogos(root) {
    (root || document).querySelectorAll('.tkd-logo[data-airline]:not([data-done])').forEach(el => {
        el.dataset.done = '1';
        const name = el.dataset.airline;
        const brand = airlineBrand(name);
        const initials = () => { el.style.background = brand.color; el.style.borderColor = brand.color; el.textContent = airCode(name) || '✈'; };
        if (!name) { el.style.color = '#94a3b8'; el.textContent = '✈'; return; }
        if (!brand.slug) return initials();
        tkTrim(`${FD_LOGO_BASE}/${brand.slug}.png`).then(url => {
            if (!url) return initials();
            el.innerHTML = `<img src="${url}" alt="">`;
        });
    });
}

// ── 2. Radar ─────────────────────────────────────────────────────────────
function renderRadar() {
    const r = TKD.radar, p = r.payables || {};
    const tiles = [
        { key: 'holds', icon: 'fa-hourglass-half', tone: '#d97706', bg: '#fef3c7', label: 'Holds expiring 24h',
          val: r.holds_24h, calm: !r.holds_all,
          sub: r.holds_all ? (r.holds_next ? `next <b data-until="${esc(r.holds_next)}">${untilText(r.holds_next)}</b>` : '') + ` · ${r.holds_all} on hold` : 'No seats on hold',
          go: () => tkdRegisterFilter({ seg: 'hold' }) },
        { key: 'void', icon: 'fa-rotate-left', tone: '#4f46e5', bg: '#eef2ff', label: 'Void window open',
          val: r.void_count, calm: !r.void_count,
          sub: r.void_count ? `${taka(r.void_amount)} · closes <b data-until="${esc(r.void_deadline)}">${untilText(r.void_deadline)}</b>` : 'Nothing issued today',
          title: 'Tickets issued today. On BSP a ticket voids without penalty only until midnight of its issue day.',
          go: () => tkdRegisterFilter({ seg: 'purchased', from: ymd(new Date()), to: ymd(new Date()) }) },
        { key: 'fly', icon: 'fa-plane-departure', tone: '#0284c7', bg: '#e0f2fe', label: 'Flying in 72h',
          val: r.flying_count, calm: !r.flying_count,
          sub: r.flying_next ? `first <b>${fmtDate(r.flying_next)}</b>` : 'No departures soon',
          go: () => document.getElementById('tkdBoard').scrollIntoView({ behavior: 'smooth' }) },
        { key: 'pay', icon: 'fa-file-invoice-dollar', tone: p.overdue_count ? '#dc2626' : '#0d9488', bg: p.overdue_count ? '#fee2e2' : '#ccfbf1', label: 'Supplier payments',
          val: taka((p.overdue || 0) + (p.week || 0)), calm: !p.overdue_count && !p.week_count,
          sub: (p.overdue_count || p.week_count) ? [p.overdue_count ? `<b class="tkd-red">${taka(p.overdue)} overdue</b>` : '', p.week_count ? `${taka(p.week)} in 7 days` : ''].filter(Boolean).join(' · ') : 'Nothing due this week',
          title: 'Ticket vendor payables on the payment schedule: overdue, and due within 7 days',
          go: () => { if (TKD.urls.schedules) location.href = TKD.urls.schedules; } },
        { key: 'idle', icon: 'fa-box-open', tone: '#b45309', bg: '#ffedd5', label: 'Idle tickets',
          val: r.idle_count, calm: !r.idle_count,
          sub: r.idle_count ? `<b>${taka(r.idle_amount)}</b> tied up` : 'Every ticket is in use',
          title: 'Purchased tickets not on a sale invoice and not linked to a contract flight passenger — cost with no revenue behind it',
          go: () => tkdRegisterFilter({ seg: 'idle' }) },
        { key: 'cx', icon: 'fa-ban', tone: '#e11d48', bg: '#ffe4e6', label: 'Cancelled this month',
          val: r.cancel_count, calm: !r.cancel_count,
          sub: r.cancel_count ? `<b class="tkd-red">${taka(r.cancel_penalty)} penalty</b> · ${taka(r.cancel_refund)} refund` : 'No cancellations',
          title: 'Penalty = what the airline kept (amount − refund)',
          go: () => tkdRegisterFilter({ seg: 'cancelled', month: true }) },
    ];
    const box = document.getElementById('tkdRadar');
    box.innerHTML = tiles.map((t, i) => `
        <button type="button" class="tkd-tile${t.calm ? ' is-calm' : ''}" data-i="${i}" style="--t:${t.tone};--t-bg:${t.bg}" title="${esc(t.title || '')}">
            <div class="tkd-tile-top"><span class="tkd-tile-ic"><i class="fas ${t.icon}"></i></span><span class="tkd-tile-lbl">${t.label}</span></div>
            <div class="tkd-tile-val">${t.calm && typeof t.val === 'number' ? '0' : esc(t.val)}</div>
            <div class="tkd-tile-sub">${t.sub}</div>
        </button>`).join('');
    box.querySelectorAll('.tkd-tile').forEach(b => b.addEventListener('click', () => tiles[b.dataset.i].go()));
}

// ── 3. Departures board ──────────────────────────────────────────────────
// One card per journey (owner, 2026-10-04): the passengers who fly the SAME
// leg 1 and the SAME leg 2 share a card — ticket 1, a link, ticket 2. A
// single-leg ticket is a card of one row, in the same style. No day
// headings: cards are sorted by their first departure and every row carries
// its own date.
let boardView = 'next', boardAll = false;
function boardLegs() {
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const lo = boardView === 'next' ? today : new Date(today - 14 * 864e5);
    const hi = boardView === 'next' ? new Date(+today + 15 * 864e5) : today;
    const legs = [];
    TKD.tickets.forEach(t => { const d = parse(t.travel); if (d && d >= lo && d < hi) legs.push({ ...t, d, kind: t.status === 'cancelled' ? 'gone' : 'bought' }); });
    TKD.holds.forEach(h => { const d = parse(h.travel); if (d && d >= lo && d < hi) legs.push({ ...h, d, kind: 'hold', status: 'hold' }); });
    return legs;
}
function boardCards() {
    // Each passenger's journey: leg number → the ticket that flies it (a live
    // ticket wins over a cancelled one; the newest wins among equals).
    const byPax = new Map(), extra = new Map();   // extra: the cancelled tickets a live one replaced
    boardLegs().forEach(l => {
        const k = l.group ? 'g' + l.group : 'h' + l.kind + l.id;
        if (!byPax.has(k)) byPax.set(k, new Map());
        const m = byPax.get(k), s = l.seq || 1, cur = m.get(s), ek = k + '#' + s;
        if (!cur || (cur.kind === 'gone' && l.kind !== 'gone') || (cur.kind === l.kind && l.d > cur.d)) {
            if (cur && cur.kind === 'gone') { if (!extra.has(ek)) extra.set(ek, []); extra.get(ek).push(cur); }
            m.set(s, l);
        } else if (l.kind === 'gone') { if (!extra.has(ek)) extra.set(ek, []); extra.get(ek).push(l); }
    });
    const flightKey = l => [l.d.getTime(), l.from?.code, l.to?.code, l.airline].join('|');
    const cards = new Map();
    byPax.forEach((m, k) => {
        const entries = [...m.entries()].sort((a, b) => a[0] - b[0]);
        const legs = entries.map(e => e[1]);
        const sig = legs.map(flightKey).join('>');
        if (!cards.has(sig)) cards.set(sig, { first: legs[0].d, legs: legs.map(l => ({ d: l.d, from: l.from, to: l.to, airline: l.airline, leg: l.leg, via: l.via, pax: [] })) });
        // the leg's own ticket, then any cancelled tickets it replaced (shown at the bottom)
        legs.forEach((l, i) => cards.get(sig).legs[i].pax.push(l, ...(extra.get(k + '#' + entries[i][0]) || [])));
    });
    return [...cards.values()].sort((a, b) => boardView === 'next' ? a.first - b.first : b.first - a.first);
}
// The passengers under an opened flight (owner, 2026-10-04): who flies first;
// cancelled tickets at the bottom under their own divider, each person's
// cancellations kept together.
function paxListHtml(pax) {
    const live = pax.filter(p => p.kind !== 'gone').sort((a, b) => String(a.passenger).localeCompare(String(b.passenger)));
    const gone = pax.filter(p => p.kind === 'gone').sort((a, b) => String(a.passenger).localeCompare(String(b.passenger)) || (b.issued_at || '').localeCompare(a.issued_at || ''));
    const row = p => `
        <div class="tkd-px${p.kind === 'gone' ? ' is-gone' : ''}">
            <span class="tkd-px-av">${esc(String(p.passenger || '?').replace(/^(mr|mrs|ms|md|mst)\.?\s+/i, '').replace(/^(md|mst)\.?\s+/i, '').charAt(0).toUpperCase())}</span>
            <div class="tkd-px-name"><b>${esc(p.passenger || '—')}</b><small>${esc(p.passport || '')}</small></div>
            <div>${p.pnr ? lnk(p.url, `<span class="tkd-px-pnr">${pnrHtml(p.pnr)}</span>`, 'Open ticket') : '<span class="tkd-c2">No PNR</span>'}</div>
            <div class="tkd-px-seller" title="${esc(p.seller || '')}">${esc(p.seller || '—')}</div>
            <div class="tkd-px-state">${p.kind === 'hold' ? '<span class="tkd-tag hold">Hold</span>' : (p.kind === 'gone' ? '<span class="tkd-px-cx">Cancelled</span>' : '<span class="tkd-tick"><i class="fas fa-check"></i></span>')}</div>
            <div class="tkd-px-cf">${p.use?.kind === 'cf' ? lnk(q(TKD.urls.flights, 'search', p.use.label), esc(p.use.label), 'Open in Flights & Passengers') : ''}</div>
            <div class="tkd-px-act">${p.url ? `<a class="tkd-act" href="${esc(p.url)}" title="Open ticket"><i class="fas fa-eye"></i></a>` : ''}</div>
        </div>`;
    return live.map(row).join('')
        + (gone.length ? `<div class="tkd-px-div"><span>Cancelled · ${gone.length}</span></div>` + gone.map(row).join('') : '');
}
function boardRowHtml(g, near) {
    const bought = g.pax.filter(p => p.kind === 'bought').length, held = g.pax.filter(p => p.kind === 'hold').length;
    const live = g.pax.filter(p => p.kind !== 'gone').length;
    const names = g.pax.filter(p => p.kind !== 'gone').map(p => p.passenger).filter(Boolean);
    const state = live && bought === live
        ? `<span class="tkd-tick"><i class="fas fa-check"></i></span>`
        : `<span class="tkd-ring"></span>${held ? `<span class="tkd-tag hold">${held} on hold</span>` : ''}`;
    return `
        <div class="tkd-flt" title="Click to see the passengers">
            <div class="tkd-flt-time" title="${esc(untilText(g.d))}"><b class="${near ? 'is-near' : ''}">${DOW[g.d.getDay()]} ${fmtDate(g.d, false)}</b><span>${hasTime(g.d) ? fmtTime(g.d) : '<i class="is-none" title="Time not set">—</i>'}</span></div>
            ${logo(g.airline)}
            <div>${routeHtml(g.from, g.to)}<div class="tkd-flt-line2">${esc(g.airline || 'Airline not set')}${g.leg ? ' · Ticket ' + esc(g.leg.replace('/', ' of ')) : ''}${g.via ? ' · ' + esc(g.via) : ''}</div></div>
            <div><div class="tkd-flt-pax" title="${esc(names.join(', '))}">${esc(names.slice(0, 2).join(', ') || '—')}${names.length > 2 ? ` <span class="tkd-c2" style="display:inline">+${names.length - 2}</span>` : ''}</div>
                <div class="tkd-flt-line2">${[...new Set(g.pax.map(p => p.seller).filter(Boolean))].slice(0, 2).map(esc).join(' · ') || '—'}</div></div>
            <div><div class="tkd-flt-pax">${live} passenger${live === 1 ? '' : 's'}</div><div class="tkd-flt-line2">${[...new Set(g.pax.map(p => p.use?.label).filter(Boolean))].slice(0, 2).map(esc).join(' · ') || '&nbsp;'}</div></div>
            <div><div class="tkd-flt-state${live && bought < live ? ' is-part' : ''}">${state}</div>
                <div class="tkd-flt-seats">${g.pax.slice(0, 12).map(p => `<i class="${p.kind === 'hold' ? 'hold' : (p.kind === 'gone' ? 'gone' : '')}" title="${esc(p.passenger || '')}"></i>`).join('')}</div></div>
        </div>
        <div class="tkd-flt-detail">${paxListHtml(g.pax)}</div>`;
}
function boardHeadHtml(card) {
    // Who this journey belongs to: CF batch, airline flight no., agent, head count.
    const jp = card.legs.flatMap(x => x.pax);
    const uniq = a => [...new Set(a.filter(Boolean))];
    const cfs = uniq(jp.map(p => p.use?.kind === 'cf' ? p.use.label : null));
    const fnos = uniq(jp.map(p => p.flight_no));
    const agents = uniq(jp.map(p => p.agent));
    const heads = uniq(jp.filter(p => p.kind !== 'gone').map(p => p.group || ('h' + p.id))).length;
    return '<div class="tkd-jh">' + [
        cfs.length ? `<b class="tkd-jh-cf"><i class="fas fa-plane"></i> ${cfs.slice(0, 2).map(c => lnk(q(TKD.urls.flights, 'search', c), esc(c), 'Open in Flights & Passengers')).join(' · ')}${cfs.length > 2 ? ' +' + (cfs.length - 2) : ''}</b>` : '<b class="tkd-jh-cf is-none">No contract flight</b>',
        fnos.length ? `<span title="Airline flight number">${esc(fnos.slice(0, 2).join(' · '))}</span>` : '',
        agents.length ? `<span title="${esc(agents.join(', '))}"><i class="fas fa-user-tie"></i> ${esc(agents[0])}${agents.length > 1 ? ' +' + (agents.length - 1) : ''}</span>` : '',
        `<span>${heads} passenger${heads === 1 ? '' : 's'}</span>`,
        card.legs.length > 1 ? `<span class="tkd-jh-pair"><i class="fas fa-link"></i> ${card.legs.length} legs</span>` : '',
    ].filter(Boolean).join('<i class="dot">·</i>') + '</div>';
}
function renderBoard() {
    const cards = boardCards(), list = boardAll ? cards : cards.slice(0, 10);
    const body = document.getElementById('tkdBoardBody');
    const seats = new Set(cards.flatMap(c => c.legs.flatMap(l => l.pax.map(p => p.group || ('h' + p.id))))).size;
    document.getElementById('tkdBoardMeta').textContent = cards.length ? `${cards.length} journey${cards.length > 1 ? 's' : ''} · ${seats} passenger${seats > 1 ? 's' : ''}` : '';
    if (!cards.length) {
        body.innerHTML = `<div class="tkd-empty"><i class="fas fa-plane-slash"></i>${boardView === 'next' ? 'No ticketed departures in the next 14 days' : 'Nothing flew in the last 14 days'}</div>`;
        return;
    }
    let html = '';
    list.forEach(card => {
        html += '<div class="tkd-journey">' + boardHeadHtml(card);
        card.legs.forEach((g, k) => {
            if (k > 0) html += `<div class="tkd-conn" title="Connecting leg · ${DOW[g.d.getDay()]} ${fmtDate(g.d, false)}"><i class="fas fa-link"></i></div>`;
            html += boardRowHtml(g, boardView === 'next' && (g.d - new Date()) < 72 * 36e5);
        });
        html += '</div>';
    });
    if (cards.length > 10) html += `<button type="button" class="tkd-more" id="tkdBoardMore">${boardAll ? 'Show fewer' : `Show all ${cards.length} journeys`}</button>`;
    body.innerHTML = html;
    body.querySelectorAll('.tkd-flt').forEach(r => r.addEventListener('click', e => { if (!e.target.closest('a')) r.classList.toggle('is-open'); }));
    document.getElementById('tkdBoardMore')?.addEventListener('click', () => { boardAll = !boardAll; renderBoard(); });
    paintLogos(body);
}

// ── 4. Insights, per period ──────────────────────────────────────────────
let period = 30;
function inPeriod(rows, days, offset = 0) {
    const end = new Date(); end.setHours(23, 59, 59, 999); end.setDate(end.getDate() - offset * days);
    const start = new Date(end); start.setDate(start.getDate() - days + 1); start.setHours(0, 0, 0, 0);
    const a = ymd(start), b = ymd(end);
    return rows.filter(t => t.issued >= a && t.issued <= b);
}
// Net cost: a cancelled ticket costs what the airline kept.
const netCost = t => t.status === 'cancelled' ? Number(t.penalty ?? t.cost) : Number(t.cost);

function renderInsights() {
    const rows = inPeriod(TKD.tickets, period);
    const prev = inPeriod(TKD.tickets, period, 1);
    document.getElementById('tkdPeriodNote').textContent = `Tickets issued in the last ${period === 365 ? '12 months' : period + ' days'} · ${rows.length} ticket${rows.length === 1 ? '' : 's'}`;
    renderTrend(rows);
    renderFlow(rows);
    renderAirlines(rows);
    renderSuppliers(rows);
    renderFares(rows, prev);
}

function renderTrend(rows) {
    // Bins: days for 30, weeks for 90, months for 12 months.
    const bins = [], now = new Date(); now.setHours(0, 0, 0, 0);
    if (period === 30) {
        for (let i = 29; i >= 0; i--) { const d = new Date(now - i * 864e5); bins.push({ a: ymd(d), b: ymd(d), label: d.getDate() === 1 || i === 29 || i === 0 ? fmtDate(d, false) : (i % 5 === 0 ? String(d.getDate()) : ''), title: fmtDate(d, false) }); }
    } else if (period === 90) {
        for (let i = 12; i >= 0; i--) { const e = new Date(now - i * 7 * 864e5), s = new Date(e - 6 * 864e5); bins.push({ a: ymd(s), b: ymd(e), label: i % 3 === 0 ? fmtDate(s, false) : '', title: fmtDate(s, false) + ' – ' + fmtDate(e, false) }); }
    } else {
        for (let i = 11; i >= 0; i--) { const s = new Date(now.getFullYear(), now.getMonth() - i, 1), e = new Date(s.getFullYear(), s.getMonth() + 1, 0); bins.push({ a: ymd(s), b: ymd(e), label: MON[s.getMonth()], title: MON[s.getMonth()] + ' ' + s.getFullYear() }); }
    }
    bins.forEach(b => { const r = rows.filter(t => t.issued >= b.a && t.issued <= b.b); b.n = r.length; b.cost = r.reduce((s, t) => s + netCost(t), 0); });
    const total = rows.length, cost = rows.reduce((s, t) => s + netCost(t), 0);
    const maxN = Math.max(1, ...bins.map(b => b.n)), maxC = Math.max(1, ...bins.map(b => b.cost));
    const W = 600, H = 132, top = 8, base = 112, bw = W / bins.length;
    const y = n => base - (n / maxN) * (base - top), yc = c => base - (c / maxC) * (base - top);
    const grid = [0.5, 1].map(f => `<line class="grid" x1="0" x2="${W}" y1="${y(maxN * f)}" y2="${y(maxN * f)}"/>`).join('');
    const bars = bins.map((b, i) => `<g class="bar-g"><title>${esc(b.title)}: ${b.n} ticket${b.n === 1 ? '' : 's'} · ${taka(b.cost)}</title>
        <rect class="bar${i === bins.length - 1 ? ' is-now' : ''}" x="${i * bw + bw * 0.18}" y="${b.n ? y(b.n) : base - 1}" width="${bw * 0.64}" height="${b.n ? base - y(b.n) : 1}" rx="3"/>
        <rect x="${i * bw}" y="0" width="${bw}" height="${base}" fill="transparent"/>
        ${b.label ? `<text class="axis" x="${i * bw + bw / 2}" y="${base + 14}" text-anchor="middle">${esc(b.label)}</text>` : ''}</g>`).join('');
    const pts = bins.map((b, i) => [i * bw + bw / 2, yc(b.cost)]);
    const line = `<polyline class="cost" points="${pts.map(p => p.join(',')).join(' ')}"/>` + pts.map((p, i) => bins[i].cost ? `<circle class="cost-dot" cx="${p[0]}" cy="${p[1]}" r="2.4"/>` : '').join('');
    const busiest = bins.reduce((m, b) => b.n > m.n ? b : m, bins[0]);
    document.getElementById('tkdTrendMeta').textContent = busiest.n ? `busiest: ${busiest.title} (${busiest.n})` : '';
    document.getElementById('tkdTrend').innerHTML = `
        <div class="tkd-trend-sum">
            <div>Tickets<b>${total}</b></div>
            <div>Net cost<b>${taka(cost)}</b></div>
            <div>Avg per ticket<b>${total ? taka(cost / total) : '—'}</b></div>
            <div>Per ${period === 30 ? 'day' : (period === 90 ? 'week' : 'month')}<b>${(total / bins.length).toFixed(1)}</b></div>
        </div>
        <svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" role="img" aria-label="Tickets issued per ${period === 30 ? 'day' : (period === 90 ? 'week' : 'month')}">${grid}${bars}${line}</svg>
        <div class="tkd-legend"><span><i style="background:#c7d2fe"></i>Tickets issued</span><span><i style="background:#f59e0b"></i>Net cost</span></div>`;
}

// Where each ticket of the period ended up — the reason it was bought.
function renderFlow(rows) {
    const parts = [
        { k: 'cf', label: 'Contract flights', color: '#6366f1', sub: 'linked to a passenger' },
        { k: 'sale', label: 'Sold on invoice', color: '#10b981', sub: 'Manage Sales' },
        { k: 'idle', label: 'Idle', color: '#f59e0b', sub: 'bought, not used yet', seg: 'idle' },
        { k: 'cancelled', label: 'Cancelled', color: '#f43f5e', sub: 'penalty kept by airline', seg: 'cancelled' },
    ].map(p => { const r = rows.filter(t => t.use.kind === p.k); return { ...p, n: r.length, amt: r.reduce((s, t) => s + (p.k === 'cancelled' ? Number(t.penalty ?? 0) : Number(t.cost)), 0) }; });
    const total = Math.max(1, rows.length);
    document.getElementById('tkdFlow').innerHTML = rows.length ? `
        <div class="tkd-flow-bar">${parts.map(p => `<span style="width:${p.n / total * 100}%;background:${p.color}" title="${esc(p.label)}: ${p.n}"></span>`).join('')}</div>
        ${parts.map(p => `<div class="tkd-flow-row" data-seg="${p.seg || p.k}" title="Show these tickets">
            <i style="background:${p.color}"></i>
            <div>${p.label}<small>${p.sub}</small></div>
            <span class="n">${p.n}</span>
            <span class="a ${p.k === 'cancelled' && p.amt ? 'tkd-red' : ''}">${p.n ? taka(p.amt) : '—'}</span></div>`).join('')}`
        : `<div class="tkd-empty"><i class="fas fa-route"></i>No tickets in this period</div>`;
    document.querySelectorAll('#tkdFlow .tkd-flow-row').forEach(r => r.addEventListener('click', () => tkdRegisterFilter({ seg: ['idle', 'cancelled'].includes(r.dataset.seg) ? r.dataset.seg : 'all', use: r.dataset.seg, days: period })));
}

const groupBy = (rows, key) => { const m = new Map(); rows.forEach(r => { const k = key(r); if (!m.has(k)) m.set(k, []); m.get(k).push(r); }); return m; };

function renderAirlines(rows) {
    const live = rows.filter(t => t.status !== 'cancelled');
    const g = [...groupBy(live, t => t.airline || '').entries()].map(([name, r]) => ({ name, n: r.length, cost: r.reduce((s, t) => s + Number(t.cost), 0) }))
        .sort((a, b) => b.n - a.n || b.cost - a.cost).slice(0, 6);
    const max = Math.max(1, ...g.map(a => a.n));
    const el = document.getElementById('tkdAirlines');
    el.innerHTML = g.length ? g.map(a => `
        <div class="tkd-rank air">${logo(a.name)}
            <div style="min-width:0"><div class="tkd-rank-name">${esc(a.name || 'Airline not set')}</div><div class="tkd-share"><span style="width:${a.n / max * 100}%"></span></div></div>
            <div class="tkd-rank-num">${a.n}<small>${taka(a.cost)}</small></div></div>`).join('')
        : `<div class="tkd-empty"><i class="fas fa-plane"></i>No tickets in this period</div>`;
    paintLogos(el);
}

// Suppliers: cost in the period; DUE is every open balance (any period) —
// a payable does not stop being owed because the ticket is old.
function renderSuppliers(rows) {
    const dueBy = groupBy(TKD.tickets.filter(t => t.due > 0), t => t.seller || '');
    const periodBy = groupBy(rows.filter(t => t.status !== 'cancelled'), t => t.seller || '');
    const names = new Set([...periodBy.keys(), ...dueBy.keys()]);
    const wallet = new Map(TKD.portals.map(p => [p.name, p.balance]));
    // This BSP half-month (1–15 / 16–end): what was issued through a BSP seller in it.
    const now = new Date(), half = now.getDate() <= 15;
    const bspFrom = ymd(new Date(now.getFullYear(), now.getMonth(), half ? 1 : 16));
    const bspLabel = half ? `1–15 ${MON[now.getMonth()]}` : `16–${new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()} ${MON[now.getMonth()]}`;
    const list = [...names].map(name => {
        const r = periodBy.get(name) || [], d = dueBy.get(name) || [];
        const isPortal = (r[0] || d[0])?.portal === 1;
        const bsp = /bsp|iata/i.test(name) ? TKD.tickets.filter(t => t.seller === name && t.status !== 'cancelled' && t.issued >= bspFrom).reduce((s, t) => s + Number(t.cost), 0) : null;
        return { name, isPortal, n: r.length, cost: r.reduce((s, t) => s + Number(t.cost), 0), due: d.reduce((s, t) => s + Number(t.due), 0), wallet: wallet.has(name) ? wallet.get(name) : null, bsp };
    }).sort((a, b) => b.due - a.due || b.cost - a.cost).slice(0, 6);
    document.getElementById('tkdSupplierList').innerHTML = list.length ? list.map(s => `
        <div class="tkd-rank sup">
            <div style="min-width:0"><div class="tkd-rank-name" title="${esc(s.name)}">${esc(s.name || 'Seller not set')}${s.isPortal ? '<span class="tkd-portal-tag">Portal</span>' : ''}</div>
                <div class="tkd-rank-sub">${s.n} ticket${s.n === 1 ? '' : 's'} · ${taka(s.cost)}${s.wallet !== null ? ` · wallet <span class="${s.wallet < 0 ? 'tkd-red' : ''}">${taka(s.wallet)}</span>` : ''}${s.bsp !== null ? ` · <span title="Issued through this seller in the current BSP half-month">${bspLabel}: ${taka(s.bsp)}</span>` : ''}</div></div>
            <div class="tkd-rank-num ${s.due > 0 ? 'tkd-red' : 'tkd-green'}">${s.due > 0 ? taka(s.due) : 'Settled'}<small>${s.due > 0 ? 'due' : '&nbsp;'}</small></div>
        </div>`).join('')
        : `<div class="tkd-empty"><i class="fas fa-store"></i>No suppliers in this period</div>`;
}

// Fare watch: the average cost of a sector in the period, against the period
// before it. Up = paying more (red), down = paying less (green).
function renderFares(rows, prev) {
    const key = t => (t.from?.code || '?') + '-' + (t.to?.code || '?');
    const live = r => r.filter(t => t.status !== 'cancelled' && t.cost > 0 && (t.from?.code || t.to?.code));
    const before = groupBy(live(prev), key);
    const list = [...groupBy(live(rows), key).entries()].map(([k, r]) => {
        const avg = r.reduce((s, t) => s + Number(t.cost), 0) / r.length;
        const b = before.get(k), bAvg = b ? b.reduce((s, t) => s + Number(t.cost), 0) / b.length : null;
        return { from: r[0].from, to: r[0].to, n: r.length, avg, min: Math.min(...r.map(t => Number(t.cost))), max: Math.max(...r.map(t => Number(t.cost))), change: bAvg ? (avg - bAvg) / bAvg * 100 : null };
    }).sort((a, b) => b.n - a.n).slice(0, 6);
    document.getElementById('tkdFares').innerHTML = list.length ? list.map(f => `
        <div class="tkd-rank fare">
            <div style="min-width:0">${routeHtml(f.from, f.to)}<div class="tkd-rank-sub">${f.n} ticket${f.n === 1 ? '' : 's'} · ${taka(f.min)} – ${taka(f.max)}</div></div>
            <div class="tkd-rank-num">${taka(f.avg)}<small>avg</small></div>
            <div class="tkd-trendchip ${f.change === null ? '' : (f.change > 0.5 ? 'tkd-red' : (f.change < -0.5 ? 'tkd-green' : ''))}" title="Against the ${period === 365 ? '12 months' : period + ' days'} before">${f.change === null ? '<span class="tkd-c2" style="display:inline">new</span>' : (Math.abs(f.change) < 0.5 ? '= 0%' : (f.change > 0 ? '▲ ' : '▼ ') + Math.abs(f.change).toFixed(0) + '%')}</div>
        </div>`).join('')
        : `<div class="tkd-empty"><i class="fas fa-tags"></i>No priced sectors in this period</div>`;
}

// ── 5. Register ──────────────────────────────────────────────────────────
// Tickets and open holds as one list, newest first. Holds read "hold" in
// the Used-for column with their deadline.
const REG = [
    ...TKD.tickets.map(t => ({ ...t, kind: 'ticket' })),
    ...TKD.holds.map(h => ({ ...h, kind: 'hold', use: { kind: 'hold', label: null } })),
];
REG.forEach(r => { r._q = [r.pnr, r.ref, r.passenger, r.passport, r.airline, r.from?.code, r.to?.code, r.from?.name, r.to?.name, r.seller, r.use?.label, r.seller_code].join(' ').toLowerCase(); });
const reg = { seg: 'all', q: '', from: '', to: '', use: '', sort: 'issued', dir: -1, shown: 50 };
const segOf = r => r.kind === 'hold' ? 'hold' : (r.status === 'cancelled' ? 'cancelled' : (r.use.kind === 'idle' ? 'idle' : 'purchased'));
const sortVal = {
    issued: r => (r.issued || '') + (r.issued_at || ''), passenger: r => (r.passenger || '').toLowerCase(), pnr: r => r.pnr || r.ref || '',
    route: r => (r.from?.code || '') + (r.to?.code || ''), seller: r => (r.seller || '').toLowerCase(), use: r => r.use?.label || r.use?.kind || '', cost: r => Number(r.cost) || 0,
};
function regRows() {
    return REG.filter(r => {
        if (reg.seg === 'purchased' && segOf(r) !== 'purchased' && segOf(r) !== 'idle') return false;
        if (reg.seg !== 'all' && reg.seg !== 'purchased' && segOf(r) !== reg.seg) return false;
        if (reg.use && ['cf', 'sale'].includes(reg.use) && r.use?.kind !== reg.use) return false;
        if (reg.from && r.issued < reg.from) return false;
        if (reg.to && r.issued > reg.to) return false;
        return !reg.q || reg.q.split(/\s+/).every(w => r._q.includes(w));
    }).sort(regCompare);
}
// Issued order keeps a passenger's legs together (owner, 2026-10-04): groups by
// their newest ticket, then ticket 1 before ticket 2, the newest first within a leg.
const groupLatest = new Map();
REG.forEach(r => { const k = r.group || ('h' + r.id), v = sortVal.issued(r); r._g = k; if (!groupLatest.has(k) || groupLatest.get(k) < v) groupLatest.set(k, v); });
function regCompare(a, b) {
    const c = (x, y) => (x > y ? 1 : x < y ? -1 : 0);
    if (reg.sort === 'issued') {
        return c(groupLatest.get(a._g), groupLatest.get(b._g)) * reg.dir || c(a._g, b._g) || c(a.seq || 1, b.seq || 1) || -c(sortVal.issued(a), sortVal.issued(b));
    }
    return c(sortVal[reg.sort](a), sortVal[reg.sort](b)) * reg.dir;
}
function useCell(r) {
    if (r.kind === 'hold') {
        const exp = r.status === 'booked' && r.expires;
        const past = exp && parse(r.expires) < new Date();
        return `<div class="tkd-c1"><span class="tkd-tag ${past ? 'bad' : (r.status === 'booked' ? 'hold' : 'warn')}">${r.status === 'booked' ? 'Hold' : esc(r.status.charAt(0).toUpperCase() + r.status.slice(1))}</span></div>`
            + (exp ? `<div class="tkd-c2 ${past ? 'tkd-red' : ''}" data-until="${esc(r.expires)}">${past ? 'expired' : 'expires'} ${untilText(r.expires)}</div>` : '');
    }
    const u = r.use;
    if (u.kind === 'cf') return `<div class="tkd-use-cf"><i class="fas fa-plane tkd-use-ic"></i> ${lnk(q(TKD.urls.flights, 'search', u.label), esc(u.label), 'Open in Flights & Passengers')}</div><div class="tkd-c2">Contract flight</div>`;
    if (u.kind === 'sale') return `<div class="tkd-use-sale">${lnk(q(TKD.urls.sales, 'invoice_no', u.label), esc(u.label), 'Open in Manage Sales')}</div><div class="tkd-c2">Sold on invoice</div>`;
    if (u.kind === 'cancelled') return `<div class="tkd-use-cx">Cancelled</div>`;
    return `<div class="tkd-use-idle">Idle</div><div class="tkd-c2">not sold or linked</div>`;
}
function costCell(r) {
    if (r.kind === 'hold') return `<div class="tkd-c1">${r.cost ? taka(r.cost) : '—'}</div><div class="tkd-c2">quoted</div>`;
    if (r.status === 'cancelled') return `<div class="tkd-c1">${taka(r.cost)}</div>`
        + (r.refund !== null ? `<div class="tkd-c2 tkd-green">Refund ${taka(r.refund)}</div>` : '')
        + (r.penalty ? `<div class="tkd-c2 tkd-red">Penalty ${taka(r.penalty)}</div>` : '');
    return `<div class="tkd-c1">${taka(r.cost)}</div>` + (r.due > 0 ? `<div class="tkd-c2 tkd-red">Due ${taka(r.due)}</div>` : `<div class="tkd-c2 tkd-green">Paid</div>`);
}
function renderRegister() {
    const rows = regRows(), list = rows.slice(0, reg.shown);
    const body = document.getElementById('tkdBody');
    body.innerHTML = list.length ? list.map((r, i) => {
        const iss = r.issued_at ? parse(r.issued_at) : parse(r.issued);
        return `<tr>
        <td class="tkd-c2" style="max-width:none">${i + 1}</td>
        <td><div class="tkd-c1">${fmtDate(r.issued, false)}</div><div class="tkd-c2">${r.issued_at ? fmtTime(iss) : ''}</div></td>
        <td><div class="tkd-c1" style="max-width:190px;overflow:hidden;text-overflow:ellipsis">${esc(r.passenger || '—')}</div><div class="tkd-c2">${esc(r.passport || '')}</div></td>
        <td>${r.kind === 'hold' ? `<div class="tkd-c1">${esc(r.ref)}</div>` : ''}${r.pnr || r.kind !== 'hold' ? `<div>${lnk(r.url, pnrHtml(r.pnr), 'Open ticket')}</div>` : ''}
            ${r.airline ? `<span class="tkd-air-pill" title="${esc(r.airline)}">${logo(r.airline, true)}${esc(airCode(r.airline))}</span>` : ''}</td>
        <td>${routeHtml(r.from, r.to)}<div class="tkd-c2">${[r.travel ? fmtDate(r.travel) : '', r.leg ? 'Ticket ' + r.leg.replace('/', ' of ') : '', r.via || ''].filter(Boolean).map(esc).join(' · ')}</div></td>
        <td><div class="tkd-c1" style="font-weight:600;max-width:170px;overflow:hidden;text-overflow:ellipsis" title="${esc(r.seller || '')}">${esc(r.seller || '—')}</div><div class="tkd-c2">${[r.seller_code ? lnk(q(TKD.urls.party, 'party_id', r.seller_id), esc(r.seller_code), 'Open party statement') : '', r.portal ? 'Portal' : ''].filter(Boolean).join(' · ')}</div></td>
        <td>${useCell(r)}</td>
        <td class="num">${costCell(r)}</td>
        <td>${r.url ? `<a class="tkd-act" href="${esc(r.url)}" title="${r.kind === 'hold' ? 'View hold' : 'Open ticket'}"><i class="fas ${r.kind === 'hold' ? 'fa-eye' : 'fa-pencil-alt'}"></i></a>` : ''}</td>
        </tr>`; }).join('')
        : `<tr><td colspan="9"><div class="tkd-empty"><i class="fas fa-inbox"></i>No tickets match</div></td></tr>`;
    const filtered = reg.seg !== 'all' || reg.q || reg.from || reg.to || reg.use;
    const c = document.getElementById('tkdCount');
    c.hidden = !filtered; c.textContent = rows.length ? `${rows.length} of ${REG.length}` : 'No match'; c.classList.toggle('is-none', !rows.length);
    const more = document.getElementById('tkdMore');
    more.hidden = rows.length <= reg.shown;
    more.textContent = `Show ${Math.min(50, rows.length - reg.shown)} more · ${rows.length - reg.shown} left`;
    document.querySelectorAll('#tkdTable th[data-k]').forEach(th => th.setAttribute('aria-sort', th.dataset.k === reg.sort ? (reg.dir > 0 ? 'ascending' : 'descending') : 'none'));
    paintLogos(body);
}

// Bar wiring — the same dd/mm/yyyy box + calendar pair as every fdl-bar.
const dmy = s => s ? s.slice(8, 10) + '/' + s.slice(5, 7) + '/' + s.slice(0, 4) : '';
const fromDmy = s => { const m = String(s || '').trim().match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/); return m ? m[3] + '-' + pad(m[2]) + '-' + pad(m[1]) : (s ? null : ''); };
function setDates(a, b) {
    reg.from = a || ''; reg.to = b || '';
    document.getElementById('tkdFrom').value = reg.from; document.getElementById('tkdFromText').value = dmy(reg.from);
    document.getElementById('tkdTo').value = reg.to; document.getElementById('tkdToText').value = dmy(reg.to);
}
function setSeg(s) { reg.seg = s; document.querySelectorAll('#tkdSeg button').forEach(b => b.classList.toggle('is-on', b.dataset.s === s)); }
window.tkdRegisterFilter = function (o) {
    setSeg(o.seg || 'all'); reg.use = o.use || ''; reg.shown = 50;
    if (o.month) { const n = new Date(); setDates(ymd(new Date(n.getFullYear(), n.getMonth(), 1)), ymd(n)); }
    else if (o.days) { const n = new Date(); setDates(ymd(new Date(Date.now() - (o.days - 1) * 864e5)), ymd(n)); }
    else setDates(o.from, o.to);
    renderRegister();
    document.getElementById('tkdRegister').scrollIntoView({ behavior: 'smooth' });
};
document.querySelectorAll('#tkdSeg button').forEach(b => b.addEventListener('click', () => { setSeg(b.dataset.s); reg.use = ''; reg.shown = 50; renderRegister(); }));
const sIn = document.getElementById('tkdSearch');
sIn.addEventListener('input', () => { reg.q = sIn.value.trim().toLowerCase(); reg.shown = 50; document.getElementById('tkdSearchWrap').classList.toggle('has-q', !!reg.q); renderRegister(); });
document.getElementById('tkdClear').addEventListener('click', () => { sIn.value = ''; sIn.dispatchEvent(new Event('input')); sIn.focus(); });
['From', 'To'].forEach(w => {
    const nat = document.getElementById('tkd' + w), txt = document.getElementById('tkd' + w + 'Text');
    nat.addEventListener('change', () => { txt.value = dmy(nat.value); txt.closest('.fdl-date').classList.remove('is-bad'); });
    txt.addEventListener('input', () => { const v = fromDmy(txt.value); txt.closest('.fdl-date').classList.toggle('is-bad', v === null && txt.value.length >= 8); if (v) nat.value = v; if (v === '') nat.value = ''; });
    txt.addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('tkdApply').click(); });
});
document.getElementById('tkdApply').addEventListener('click', () => {
    const a = fromDmy(document.getElementById('tkdFromText').value), b = fromDmy(document.getElementById('tkdToText').value);
    if (a === null || b === null) return;
    setDates(a, b); reg.shown = 50; renderRegister();
});
document.querySelectorAll('#tkdTable th[data-k] .tkd-sort').forEach(btn => btn.addEventListener('click', () => {
    const k = btn.closest('th').dataset.k;
    reg.dir = reg.sort === k ? -reg.dir : (['issued', 'cost'].includes(k) ? -1 : 1); reg.sort = k; renderRegister();
}));
document.getElementById('tkdMore').addEventListener('click', () => { reg.shown += 50; renderRegister(); });

// Print: the rows the bar shows, on an A4 sheet.
document.getElementById('tkdPrint').addEventListener('click', () => {
    const rows = regRows();
    const segName = { all: 'All tickets', purchased: 'Purchased', hold: 'Holds', idle: 'Idle tickets', cancelled: 'Cancelled' }[reg.seg];
    const range = reg.from || reg.to ? ` · issued ${reg.from ? dmy(reg.from) : '…'} – ${reg.to ? dmy(reg.to) : '…'}` : '';
    const sum = (f) => rows.reduce((s, r) => s + (Number(f(r)) || 0), 0);
    const tr = rows.map((r, i) => `<tr><td>${i + 1}</td><td>${fmtDate(r.issued, false)}</td><td>${esc(r.passenger || '—')}<br><small>${esc(r.passport || '')}</small></td>
        <td>${esc(r.pnr || r.ref || '')}<br><small>${esc(r.airline || '')}</small></td><td>${esc((r.from?.code || '?') + ' → ' + (r.to?.code || '?'))}<br><small>${r.travel ? fmtDate(r.travel) : ''}</small></td>
        <td>${esc(r.seller || '—')}</td><td>${esc(r.kind === 'hold' ? 'Hold' : (r.use.label || { idle: 'Idle', cancelled: 'Cancelled' }[r.use.kind] || ''))}</td>
        <td class="n">${taka(r.cost)}${r.due > 0 ? `<br><small class="red">Due ${taka(r.due)}</small>` : ''}${r.penalty ? `<br><small class="red">Penalty ${taka(r.penalty)}</small>` : ''}</td></tr>`).join('');
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Ticket Register</title><style>
        @page{size:A4;margin:12mm}body{font-family:Arial,sans-serif;color:#1e293b;font-size:10.5px}
        h1{font-size:16px;margin:0}p{margin:3px 0 10px;color:#64748b}table{width:100%;border-collapse:collapse}
        th{background:#eef2ff;color:#4338ca;text-align:left;padding:6px;font-size:9.5px;text-transform:uppercase}
        td{padding:5px 6px;border-bottom:1px solid #e2e8f0;vertical-align:top}small{color:#94a3b8}.red{color:#dc2626}.n{text-align:right;white-space:nowrap}
        tfoot td{font-weight:700;border-top:2px solid #c7d2fe}</style></head><body>
        <h1>Ticket Register</h1><p>${esc(segName)}${esc(range)}${reg.q ? ' · search “' + esc(reg.q) + '”' : ''} · ${rows.length} ticket${rows.length === 1 ? '' : 's'} · printed ${fmtDate(new Date())}</p>
        <table><thead><tr><th>SL</th><th>Issued</th><th>Passenger</th><th>Ticket</th><th>Route</th><th>Seller</th><th>Used for</th><th class="n">Cost</th></tr></thead>
        <tbody>${tr}</tbody><tfoot><tr><td colspan="7">Total</td><td class="n">${taka(sum(r => r.cost))}<br><small class="red">Due ${taka(sum(r => r.due))}</small></td></tr></tfoot></table>
        <script>onload=()=>print()<\/script></body></html>`);
    w.document.close();
});

// ── Wiring ───────────────────────────────────────────────────────────────
document.querySelectorAll('#tkdBoardSeg button').forEach(b => b.addEventListener('click', () => {
    boardView = b.dataset.v; boardAll = false;
    document.querySelectorAll('#tkdBoardSeg button').forEach(x => x.classList.toggle('is-on', x === b));
    renderBoard();
}));
document.querySelectorAll('#tkdPeriod button').forEach(b => b.addEventListener('click', () => {
    period = +b.dataset.p;
    document.querySelectorAll('#tkdPeriod button').forEach(x => x.classList.toggle('is-on', x === b));
    renderInsights();
}));

// Live: every countdown and the desk clock tick each 30 s.
function tick() {
    document.querySelectorAll('[data-until]').forEach(el => {
        const past = parse(el.dataset.until) < new Date();
        el.textContent = (el.textContent.startsWith('expire') ? (past ? 'expired ' : 'expires ') : '') + untilText(el.dataset.until);
    });
    const n = new Date();
    document.getElementById('tkdClock').textContent = DOW[n.getDay()] + ', ' + fmtDate(n, false) + ' · ' + fmtTime(n);
}

renderRadar();
renderBoard();
renderInsights();
renderRegister();
tick();
setInterval(tick, 30000);
})();
</script>
@endsection
