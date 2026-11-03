{{-- The transactions table — the dashboard's Latest entries and every party
     statement are the same table, drawn from these rules (Manage Bank's
     .ca-panel / .txn-table, copied value for value). Every selector is
     scoped under .ps-recent / .ps-panel. --}}
<style>
/* The Latest-entries table is drawn as Manage Bank's Recent Transactions
   table (owner, 2026-09-15: "same table style, like in manage bank") — the
   values below are .ca-panel / .txn-table from banks/dashboard.blade.php,
   copied rather than shared so a change to one desk cannot move the other.
   Keep them in step. */
.ps-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,0.06); }
/* The feed title row sits half as tall as a card header (owner, 2026-09-15). */
.ps-panel > .ps-card-h { padding: 5px 14px 4px; }
.ps-panel > .ps-card-h .ps-ic { width: 24px; height: 24px; border-radius: 7px; font-size: 11px; }
.ps-recent { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.83rem; }
.ps-recent thead th {
    background: #eef2ff; color: #4338ca; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.04em; font-size: 0.7rem; padding: 13px 12px; white-space: nowrap;
    border-bottom: 1px solid #c7d2fe; text-align: left;
}
.ps-recent tbody td { padding: 11px 12px; border-bottom: 1px solid #b8c4d4; color: #334155; vertical-align: middle; }
.ps-recent tbody tr:last-child td { border-bottom: none; }
.ps-recent tbody tr[data-href] { cursor: pointer; }
.ps-recent tbody tr[data-href]:hover td { background: #f8fafc; }
.ps-recent .ps-nm { font-weight: 600; color: #1e293b; }
.ps-recent .ps-sub { font-size: 0.7rem; color: #94a3b8; margin-top: 1px; }
.ps-recent .ps-id { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: 0.02em; color: #4f46e5; font-weight: 700; }
.ps-recent .ps-vno { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.75rem; color: #4f46e5; font-weight: 600; }
/* Debit raises what a party owes, credit settles it — so the colours are the
   party statement's (Dr red, Cr green), not the bank feed's, which reads the
   same two columns from the account's side. */
.ps-recent .ps-amt-dr { color: #dc2626; font-weight: 600; }
.ps-recent .ps-amt-cr { color: #16a34a; font-weight: 600; }
.ps-recent .ps-amt-none { color: #cbd5e1; }
/* One fact per line: a small icon, then the text. The first line carries the
   name in the row's own ink; the rest are the sub-line grey. */
/* Details is drawn with the Party column's own .ps-nm / .ps-sub, so the two
   read alike; no rule of its own. The arrow beneath is the statement's
   "Passengers" toggle: small, blue, chevron flips when open. */
.ps-more { border: 0; background: none; padding: 0; margin-top: 3px; cursor: pointer; font-size: 10px; font-weight: 700; color: #2563eb; display: inline-flex; align-items: center; gap: 3px; }
.ps-more:hover { color: #1e40af; }
.ps-more i { font-size: 9px; transition: transform .15s; }
.ps-more.is-open i { transform: rotate(180deg); }
.ps-recent tr.ps-detail-row td { border-bottom: 1px solid #b8c4d4; overflow-wrap: normal; }
.ps-recent tr.ps-detail-row table { table-layout: auto; }
.ps-recent tbody tr[data-open="1"], .ps-recent tbody tr.ps-detail-row { position: relative; z-index: 2; }
.ps-recent tbody tr[data-open="1"] > td {
    background: #fff !important; border-bottom: 0 !important;
    box-shadow: inset 0 1px 0 var(--ps-detail-accent, #4f46e5) !important;
}
.ps-recent tbody tr[data-open="1"] > td:first-child { box-shadow: inset 0 1px 0 var(--ps-detail-accent, #4f46e5), inset 1px 0 0 var(--ps-detail-accent, #4f46e5) !important; }
.ps-recent:not(.is-picking) tbody tr[data-open="1"] > td:nth-child(2) { box-shadow: inset 0 1px 0 var(--ps-detail-accent, #4f46e5), inset 1px 0 0 var(--ps-detail-accent, #4f46e5) !important; }
.ps-recent tbody tr[data-open="1"] > td:last-child { box-shadow: inset 0 1px 0 var(--ps-detail-accent, #4f46e5), inset -1px 0 0 var(--ps-detail-accent, #4f46e5) !important; }
.ps-recent tbody tr.ps-detail-row { filter: drop-shadow(0 7px 8px rgba(15,23,42,.18)); }
.ps-recent tbody tr.ps-detail-row > td {
    background: var(--ps-detail-soft, #eef2ff) !important; border-bottom: 0 !important;
    box-shadow: inset 0 -1px 0 var(--ps-detail-accent, #4f46e5) !important;
}
.ps-recent tbody tr.ps-detail-row > td:first-child { box-shadow: inset 1px 0 0 var(--ps-detail-accent, #4f46e5), inset 0 -1px 0 var(--ps-detail-accent, #4f46e5) !important; }
.ps-recent:not(.is-picking) tbody tr.ps-detail-row > td:nth-child(2) { box-shadow: inset 1px 0 0 var(--ps-detail-accent, #4f46e5), inset -1px 0 0 var(--ps-detail-accent, #4f46e5), inset 0 -1px 0 var(--ps-detail-accent, #4f46e5) !important; }
.ps-recent tbody tr.ps-detail-row > td:last-child { box-shadow: inset -1px 0 0 var(--ps-detail-accent, #4f46e5), inset 0 -1px 0 var(--ps-detail-accent, #4f46e5) !important; }
.ps-detail-card {
    position: relative; transform: translateY(-2px); overflow: hidden; background: #fff; border: 1px solid var(--ps-detail-accent, #4f46e5);
    border-radius: 11px; box-shadow: 0 10px 20px rgba(15,23,42,.20), 0 2px 5px rgba(15,23,42,.10);
}
.ps-detail-card thead tr { background: var(--ps-detail-soft, #eef2ff); }
.ps-detail-card table th, .ps-detail-card table td { border: 0 !important; box-shadow: none !important; background: transparent !important; }
.ps-detail-card table th { color: var(--ps-detail-accent, #4f46e5) !important; }
.ps-detail-card table th, .ps-detail-card table td { padding: 6px 12px !important; }
/* Column widths as the owner allocated them (2026-09-15, drawn on the page
   at 90% zoom) — and Party must not move by a pixel. So every column is a
   PIXEL width except Entry, which is the one column left auto: in a fixed
   layout the slack goes only to auto columns, so whatever the viewport does,
   Party stays 247px and Entry takes or gives the difference. Widths are the
   whole column, padding included (border-box). */
.ps-recent { table-layout: fixed; }
.ps-recent th { box-sizing: border-box; }
.ps-recent th.ps-c-date { width: 71px; }
.ps-recent th.ps-c-party { width: 247px; }
/* .ps-c-entry: auto — absorbs the slack */
.ps-recent th.ps-c-details { width: 200px; }   /* room for a name at the row size; Entry gives the 25px */
.ps-recent th.ps-c-via { width: 140px; }
/* The party statement draws the same eight columns with the same widths
   (owner, 2026-09-16) — no statement-only columns. */
.ps-recent th.ps-c-dr, .ps-recent th.ps-c-cr { width: 82px; }
.ps-recent th.ps-c-bal { width: 128px; }
.ps-recent td { overflow-wrap: anywhere; }
.ps-recent td.ps-date { overflow-wrap: normal; }
/* The date column a fifth narrower (owner, 2026-09-15): tighter side padding
   and the date one step smaller; the time line beneath is unchanged. */
.ps-recent .ps-date { white-space: nowrap; padding-left: 6px; padding-right: 6px; }
.ps-recent td.ps-date { font-size: 0.72rem; }
.ps-date-edit { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; margin-top:4px; border:1px solid #bfdbfe; border-radius:6px; background:#eff6ff; color:#2563eb; cursor:pointer; }
.ps-date-edit:hover { border-color:#60a5fa; background:#dbeafe; color:#1d4ed8; }
.ps-date-edit i { font-size:9px; }

.ps-recent .ps-flow { display: inline-flex; align-items: center; gap: 3px; font-size: 9px; font-weight: 700; padding: 1px 6px; border-radius: 20px; margin-left: 6px; vertical-align: middle; }
.ps-recent .ps-bal { font-family: monospace; font-weight: 700; white-space: nowrap; }
.ps-recent .ps-vno { display: block; margin-top: 2px; font-size: 0.68rem; color: #a3adbb; font-weight: 400; letter-spacing: 0.02em; }
/* A voucher with a bank entry behind it reads as a link: indigo, mono, bold;
   underlined only on hover, as the beneficiary codes on Manage Bank. */
.ps-recent a.ps-vno-link { color: #4f46e5; font-weight: 700; text-decoration: none; width: max-content; }
.ps-recent a.ps-vno-link:hover { color: #3730a3; text-decoration: underline; }
.ps-recent a.ps-vno-link i { font-size: 9px; margin-right: 2px; opacity: .8; }
</style>
