{{--
    Financial statements — shared styles (Profit & Loss, Balance Sheet, Cash Flow, Expenses by Category).

    Teal, like the Expense report: the same family of screens. Every selector is .fr- prefixed and the
    tokens live on .fr-shell, not :root, so nothing here can reach a page that does not include it.
    The screen views and the layout-free print page both include this one file, so a statement looks the
    same on screen and on paper.
--}}
<style>
    .fr-shell {
        --fr-teal: #0d9488; --fr-teal-dark: #0f766e; --fr-ink: #0f172a; --fr-text: #1f2937; --fr-muted: #64748b;
        --fr-line: #e2e8f0; --fr-soft: #f8fafc; --fr-up: #dc2626; --fr-down: #059669;
        background: #f5f7fb; padding: 16px; border-radius: 14px; color: var(--fr-text);
    }
    .fr-card { background: #fff; border: 1px solid var(--fr-line); border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(15,23,42,.06); }

    .fr-head { background: linear-gradient(135deg, var(--fr-teal) 0%, var(--fr-teal-dark) 100%); color: #fff; padding: 16px 20px;
        display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .fr-head h1 { margin: 0; font-size: 18px; font-weight: 800; letter-spacing: -.01em; color: #fff; }
    .fr-head .fr-sub { color: rgba(255,255,255,.85); font-size: 12px; margin-top: 3px; }
    .fr-head-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .fr-btn { display: inline-flex; align-items: center; gap: 7px; border: 1px solid rgba(255,255,255,.25); background: rgba(255,255,255,.14); color: #fff;
        border-radius: 9px; padding: 8px 13px; font-size: 12.5px; font-weight: 700; cursor: pointer; text-decoration: none; transition: background .15s; }
    .fr-btn:hover { background: rgba(255,255,255,.26); color: #fff; text-decoration: none; }
    .fr-btn.fr-solid { background: #fff; color: var(--fr-teal-dark); border-color: #fff; }

    .fr-controls { padding: 14px 16px 12px; border-bottom: 1px dashed var(--fr-line); display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
    .fr-field { display: flex; flex-direction: column; gap: 5px; }
    .fr-field > label { font-size: 10.5px; font-weight: 800; color: var(--fr-muted); letter-spacing: .07em; text-transform: uppercase; margin: 0; }
    .fr-field input, .fr-field select { min-width: 150px; height: 38px; border: 1px solid var(--fr-line); border-radius: 9px; padding: 0 11px; font-size: 13px; color: var(--fr-text); background: #fff; }
    .fr-field input:focus, .fr-field select:focus { outline: 2px solid rgba(13,148,136,.35); outline-offset: -1px; }
    .fr-go { height: 38px; padding: 0 18px; border-radius: 9px; border: none; font-size: 13px; font-weight: 800; cursor: pointer; background: var(--fr-teal); color: #fff; }
    .fr-go:hover { background: var(--fr-teal-dark); }
    .fr-seg { display: inline-flex; background: #eef2f7; border-radius: 10px; padding: 3px; gap: 2px; flex-wrap: wrap; }
    .fr-seg a { padding: 7px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; color: var(--fr-muted); text-decoration: none; white-space: nowrap; }
    .fr-seg a:hover { color: var(--fr-ink); text-decoration: none; }
    .fr-seg a.is-on { background: #fff; color: var(--fr-teal-dark); box-shadow: 0 1px 3px rgba(15,23,42,.12); }

    .fr-scope { padding: 10px 20px; background: #f0fdfa; border-bottom: 1px solid #ccfbf1; font-size: 12.5px; color: #115e59; display: flex; gap: 14px; flex-wrap: wrap; align-items: center; }
    .fr-scope b { font-weight: 800; }
    .fr-warn { margin: 12px 16px 0; padding: 10px 14px; border-radius: 10px; font-size: 12.5px; background: #fef3c7; border: 1px solid #fcd34d; color: #92400e; }
    .fr-ok { margin: 12px 16px 0; padding: 10px 14px; border-radius: 10px; font-size: 12.5px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }

    .fr-kpis { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; padding: 14px 16px 4px; }
    .fr-kpi { background: #fff; border: 1px solid var(--fr-line); border-radius: 12px; padding: 13px 15px; border-top: 3px solid var(--fr-teal); }
    .fr-kpi.k-red { border-top-color: #ef4444; } .fr-kpi.k-blue { border-top-color: #0ea5e9; } .fr-kpi.k-amber { border-top-color: #f59e0b; }
    .fr-kpi .fr-k-label { font-size: 10.5px; font-weight: 800; color: var(--fr-muted); text-transform: uppercase; letter-spacing: .07em; margin-bottom: 7px; }
    .fr-kpi .fr-k-value { font-size: 21px; font-weight: 900; color: var(--fr-ink); line-height: 1.05; font-variant-numeric: tabular-nums; }
    .fr-kpi .fr-k-note { font-size: 11.5px; color: var(--fr-muted); margin-top: 5px; }

    /* statement table */
    .fr-body { padding: 14px 16px 18px; }
    .fr-st { width: 100%; border-collapse: collapse; font-size: 13px; }
    .fr-st th { background: var(--fr-soft); color: var(--fr-muted); font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; font-weight: 800; padding: 10px 14px; border-bottom: 1px solid var(--fr-line); text-align: left; white-space: nowrap; }
    .fr-st th.fr-r, .fr-st td.fr-r { text-align: right; font-variant-numeric: tabular-nums; }
    .fr-st td { padding: 8px 14px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
    .fr-st tr.fr-sec td { background: #f1f5f9; font-weight: 900; color: #334155; font-size: 12.5px; padding: 10px 14px; border-top: 1px solid var(--fr-line); }
    .fr-st tr.fr-grp td { color: var(--fr-muted); font-weight: 800; font-size: 11.5px; padding-top: 10px; border-bottom: none; }
    .fr-st tr.fr-row td:first-child { padding-left: 34px; }
    .fr-st tr.fr-row.fr-in td:first-child { padding-left: 52px; }
    .fr-st tr.fr-row:hover td { background: #f0fdfa; }
    .fr-st tr.fr-sub td { font-weight: 800; border-top: 1px solid var(--fr-line); background: #fff; }
    .fr-st tr.fr-sub td:first-child { padding-left: 20px; }
    .fr-st tr.fr-key td { font-weight: 900; background: #ecfdf5; color: #065f46; border-top: 2px solid #a7f3d0; border-bottom: 2px solid #a7f3d0; font-size: 13.5px; }
    .fr-st tr.fr-key.fr-neg td { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
    .fr-st tr.fr-grand td { font-weight: 900; background: var(--fr-ink); color: #fff; font-size: 13.5px; }
    .fr-st .fr-code { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 11px; color: var(--fr-muted); background: #f1f5f9; border-radius: 5px; padding: 1px 6px; margin-right: 8px; }
    .fr-st a.fr-link { color: var(--fr-text); text-decoration: none; border-bottom: 1px dotted #94a3b8; }
    .fr-st a.fr-link:hover { color: var(--fr-teal-dark); border-bottom-color: var(--fr-teal); }
    .fr-neg-n { color: var(--fr-up); }
    .fr-up { color: var(--fr-up); font-weight: 800; } .fr-down { color: var(--fr-down); font-weight: 800; }
    .fr-pct { height: 6px; background: #eef2f7; border-radius: 999px; overflow: hidden; min-width: 70px; }
    .fr-pct i { display: block; height: 100%; background: linear-gradient(90deg, #14b8a6, #0f766e); }
    .fr-empty { text-align: center; color: var(--fr-muted); padding: 40px 12px; font-size: 13px; }
    .fr-foot { padding: 0 16px 14px; font-size: 11.5px; color: var(--fr-muted); }

    @media (max-width: 860px) { .fr-kpis { grid-template-columns: repeat(2, minmax(0,1fr)); } }
    html.dark .fr-shell { background: #0f172a; }
    html.dark .fr-card, html.dark .fr-kpi { background: #1e293b; border-color: #334155; }
    html.dark .fr-st td { border-bottom-color: #334155; }
    html.dark .fr-st tr.fr-sec td, html.dark .fr-st th { background: #0f172a; color: #94a3b8; }
    html.dark .fr-kpi .fr-k-value { color: #e2e8f0; }
    html.dark .fr-field input, html.dark .fr-field select { background: #0f172a; color: #e2e8f0; border-color: #334155; }
    .fr-pill { display: inline-block; padding: 3px 11px; border-radius: 999px; font-size: 11.5px; font-weight: 800; }
    .fr-pill.good { background: #d1fae5; color: #065f46; } .fr-pill.moderate { background: #fef3c7; color: #92400e; }
    .fr-pill.worse { background: #fee2e2; color: #991b1b; } .fr-pill.nodata { background: #f1f5f9; color: #64748b; }
    .fr-h2 { font-size: 13.5px; font-weight: 900; color: #334155; margin: 22px 0 8px; display: flex; align-items: center; gap: 8px; }
    .fr-h2:first-child { margin-top: 0; }
    .fr-gap { border: 1px solid var(--fr-line); border-left: 4px solid #f59e0b; border-radius: 10px; padding: 11px 14px; margin-bottom: 9px; background: #fff; }
    .fr-gap b { color: var(--fr-ink); font-size: 13px; }
    .fr-gap ul { margin: 6px 0 0 18px; padding: 0; font-size: 12.5px; color: var(--fr-text); } .fr-gap li { margin: 2px 0; }
    .fr-recs { margin: 0 0 0 18px; padding: 0; font-size: 13px; } .fr-recs li { margin: 5px 0; }
    .fr-verdict { margin-top: 14px; padding: 12px 15px; border-radius: 10px; background: #f0fdfa; border: 1px solid #99f6e4; color: #115e59; font-weight: 700; font-size: 13px; }
</style>

