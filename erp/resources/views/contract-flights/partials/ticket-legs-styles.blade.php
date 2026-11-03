{{-- The passenger cells, tidied (owner, 2026-10-03): via-route tickets in two
     lines each, and Passenger / Agent / Route / Cost-Profit below them. CSS
     only, no <style> tag: included inside the <style> of both the Flights &
     Passengers table (passenger-table-styles) and the Flight Dashboard's
     Passenger Ledger (dashboard-ledger-styles), so the two cells can never
     look different. Markup: passenger-row.blade.php (Blade) and
     ledgerTicketLegs() in dashboard-ledger-script (JS) — change both. --}}
/* Via-route ticket, two lines each (owner, 2026-10-03): green tick = purchased,
   empty amber ring = not yet (replaces the "1 / 2 ●" pill — owner rejected the
   counters), a dotted journey line joins leg 1 to leg 2. Line 1 is a grid:
   the PNR in a fixed track, so every airline pill (small logo + code) starts
   at the same x on every row. */
:is(.mfp-table,.fd-table) .mfp-tk{position:relative;display:grid;grid-template-columns:18px minmax(0,1fr);column-gap:7px;padding-bottom:9px;}
:is(.mfp-table,.fd-table) .mfp-tk:last-of-type{padding-bottom:0;}
:is(.mfp-table,.fd-table) .mfp-tk:not(:last-of-type)::before{content:"";position:absolute;left:8px;top:19px;bottom:-1px;border-left:2px dotted #cbd5e1;}
:is(.mfp-table,.fd-table) .mfp-tk.done:has(+ .mfp-tk.done)::before{border-left-color:#6ee7b7;}   /* both legs bought: the journey line goes green */
:is(.mfp-table,.fd-table) .mfp-tk-dot{grid-row:1 / span 4;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.58rem;color:#fff;background:#fff;border:2px solid #fcd34d;box-sizing:border-box;}
:is(.mfp-table,.fd-table) .mfp-tk.done .mfp-tk-dot{color:#038d02;background:#ffffff;border-color:#10b981;}
:is(.mfp-table,.fd-table) .mfp-tk-l1{display:grid;grid-template-columns:84px auto;justify-content:start;align-items:center;column-gap:6px;min-width:0;line-height:18px;}
:is(.mfp-table,.fd-table) .mfp-tk-l1 .mfp-pnr{font-weight:600;letter-spacing:.03em;font-variant-numeric:tabular-nums;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;}
:is(.mfp-table,.fd-table) .mfp-tk-air{white-space:nowrap;display:inline-flex;align-items:center;gap:4px;font-size:.6rem;font-weight:700;letter-spacing:.04em;color:#475569;background:#fff;border:0;border-radius:999px;padding:0 6px 0 2px;line-height:16px;}   /* flat: no shadow, no border (owner, 2026-10-03) */
:is(.mfp-table,.fd-table) .mfp-tk-air:empty{visibility:hidden;}
:is(.mfp-table,.fd-table) .mfp-tk-air img{width:14px;height:14px;object-fit:contain;border-radius:3px;flex:none;}
:is(.mfp-table,.fd-table) .mfp-tk-tag{display:inline-block;margin-top:2px;font-size:.6rem;font-weight:600;padding:0 6px;border-radius:999px;line-height:15px;background:#fffbeb;color:#b45309;white-space:nowrap;}
:is(.mfp-table,.fd-table) .mfp-tk-tag.hold{background:#e0f2fe;color:#0369a1;} :is(.mfp-table,.fd-table) .mfp-tk-tag.cancelled{background:#fef2f2;color:#b91c1c;}
:is(.mfp-table,.fd-table) .mfp-tk-l2{font-size:.7rem;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.35;}
:is(.mfp-table,.fd-table) .mfp-tk-l2 b{color:#334155;font-weight:600;}
:is(.mfp-table,.fd-table) .mfp-tk-sfx{margin-left:3px;font-size:.66rem;font-weight:500;color:#a5b4fc;letter-spacing:0;}
:is(.mfp-table,.fd-table) .mfp-tk-plane{font-size:.52rem;color:#cbd5e1;margin:0 4px;vertical-align:1px;}
:is(.mfp-table,.fd-table) .mfp-tk-l2 b{font-variant-numeric:tabular-nums;}
:is(.mfp-table,.fd-table) .mfp-tk-sep{margin:0 4px 0 1px;color:#cbd5e1;}
:is(.mfp-table,.fd-table) .mfp-tk-was{font-size:.66rem;}
/* The ledger has no .mfp-pnr / .mfp-ticket-missing rules of its own. */
.fd-table .mfp-tk-l1 .mfp-pnr{color:#4f46e5;}
.fd-table .mfp-ticket-missing{opacity:.7;}
.fd-table .mfp-ticket-missing .mfp-pnr{color:#b45309;font-style:italic;}
/* ── The other cells, same discipline (owner, 2026-10-03): a bold first line,
   then short grey lines with no "Label:" prefix (the tooltip names it). No
   pills in Route (owner: three labels looked odd) — coloured text instead. Used by Passenger / Agent / Route / Cost-Profit on
   Flights & Passengers and by the Passenger Ledger's twins. */
:is(.mfp-table,.fd-table) .mfp-name{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.25;margin-bottom:2px;}
:is(.mfp-table,.fd-table) .mfp-ln{display:block;font-size:.7rem;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.45;}
:is(.mfp-table,.fd-table) .mfp-ln b{color:#334155;font-weight:600;font-variant-numeric:tabular-nums;}
:is(.mfp-table,.fd-table) .mfp-ln a{color:#6366f1;font-weight:500;}
:is(.mfp-table,.fd-table) .mfp-mono{letter-spacing:.03em;font-variant-numeric:tabular-nums;}
:is(.mfp-table,.fd-table) .mfp-ln.mfp-mono{color:#64748b;}
:is(.mfp-table,.fd-table) .mfp-ln .mfp-ico{margin-right:5px;}
:is(.mfp-table,.fd-table) .mfp-rt{display:flex;align-items:center;gap:4px;white-space:nowrap;font-weight:600;color:#1e293b;line-height:18px;margin-bottom:1px;}
:is(.mfp-table,.fd-table) .mfp-rt .mfp-tk-plane{margin:0 2px;font-size:.55rem;}
:is(.mfp-table,.fd-table) .mfp-rt img{width:15px;height:10px;border-radius:2px;object-fit:cover;}
:is(.mfp-table,.fd-table) .mfp-cost-total{display:block;font-weight:600;color:#1e293b;}
:is(.mfp-table,.fd-table) .mfp-profit{display:inline-block;margin-top:4px;font-size:.64rem;font-weight:700;padding:1px 7px;border-radius:999px;background:#ecfdf5;color:#047857;white-space:nowrap;}
:is(.mfp-table,.fd-table) .mfp-profit.neg{background:#fef2f2;color:#dc2626;}
:is(.mfp-table,.fd-table) .mfp-rt-flight,:is(.mfp-table,.fd-table) .mfp-ln a.mfp-rt-flight{color:#4f46e5;font-weight:600;letter-spacing:.02em;}
:is(.mfp-table,.fd-table) .mfp-rt-via{color:#b45309;font-weight:600;}
:is(.mfp-table,.fd-table) .mfp-ln.mfp-rt-flag{color:#dc2626;font-weight:600;}
:is(.mfp-table,.fd-table) .mfp-ln.mfp-rt-flag .mfp-ico{color:#f87171;}
/* Ticket sellers under the agent (2026-10-03): name in slate, code small and pale. */
:is(.mfp-table,.fd-table) .mfp-seller{display:flex;align-items:center;color:#64748b;}   /* the name shortens, the code never */
:is(.mfp-table,.fd-table) .mfp-seller-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
:is(.mfp-table,.fd-table) .mfp-seller .mfp-ico,:is(.mfp-table,.fd-table) .mfp-seller .fd-ico{flex:none;}
:is(.mfp-table,.fd-table) .mfp-ln.mfp-seller a{color:#475569;font-weight:500;}
:is(.mfp-table,.fd-table) .mfp-seller-code{flex:none;margin-left:5px;font-size:.6rem;font-weight:600;letter-spacing:.03em;color:#a5b4fc;font-variant-numeric:tabular-nums;}
