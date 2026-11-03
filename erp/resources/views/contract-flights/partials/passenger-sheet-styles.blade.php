/* ── Passenger sheet ──────────────────────────────────────────────────
   The eye on a ledger row opens the whole record as an A4 document
   (owner, 2026-09-22) rather than expanding the row. Real millimetres, so
   what is on screen is what comes out of the printer, and the whole record
   is sized to land on ONE page — a passenger record that runs to a second
   sheet stops being something you hand across a desk.

   Deliberately plain: the airline's mark and the country flags carry the
   colour, and everything else stays black on white so the figures read
   first. print-color-adjust keeps the marks through the printer. */
.fdl-sheet-overlay { position: fixed; inset: 0; z-index: 9400; background: rgba(15, 23, 42, .58);
    display: flex; flex-direction: column; align-items: center; padding: 14px; overflow: auto; }
.fdl-sheet-overlay[hidden] { display: none; }
/* flex-shrink:0 on both: the overlay is a column flex container, which
   would otherwise squeeze the A4 box to fit the window and quietly make the
   page a different size from the paper. */
.fdl-sheet-bar { width: 210mm; max-width: 100%; display: flex; align-items: center; gap: 10px;
    margin-bottom: 9px; color: #fff; font-size: .82rem; flex-shrink: 0; }
.fdl-sheet-bar strong { font-size: .92rem; margin-right: auto; }
/* The passenger record's bar is a solid strip: it lands right on the page
   header, and the header's buttons showed through the gaps between ours. */
.fdl-sheet-bar.is-strip { background: #1e293b; border-radius: 10px; padding: 5px 5px 5px 14px; box-sizing: border-box; gap: 6px;
    box-shadow: 0 8px 24px rgba(0,0,0,.28); }
/* nowrap + no shrink: a full bar wraps the title, never a button's label.
   Solid, not see-through: the bar sits exactly over the page header, whose
   own buttons (My Tasks, Quick Task) showed through and tinted these. */
.fdl-sheet-btn { border: 1px solid rgba(255,255,255,.28); background: #334155; color: #fff;
    border-radius: 8px; padding: 6px 12px; font-size: .78rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    white-space: nowrap; flex-shrink: 0; min-height: 33px; box-sizing: border-box; }
.fdl-sheet-btn:hover { background: #475569; }
/* Print and Close: icon squares, the word in the tooltip. */
.fdl-sheet-btn.is-icon { width: 33px; padding: 0; justify-content: center; }

.fdl-sheet { width: 210mm; height: 297mm; flex-shrink: 0; background: #fff; color: #1a2035; padding: 11mm 12mm;
    box-shadow: 0 24px 60px rgba(0,0,0,.35); font-size: 8.6pt; line-height: 1.33; box-sizing: border-box;
    overflow: hidden; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.fdl-sheet * { box-sizing: border-box; }
/* The flight sheet carries a whole batch, and a batch has as many passengers
   as it has (owner, 2026-09-22): laid out for one page, but allowed to run
   onto a second rather than hiding anyone. The page-break rules below keep a
   passenger row whole across the break. */
.fdl-sheet.is-flow { height: auto; min-height: 297mm; overflow: visible; }
.fdl-sheet.is-flow tr, .fdl-sheet.is-flow .fdl-sheet-grid { page-break-inside: avoid; break-inside: avoid; }
.fdl-sheet.is-flow thead { display: table-header-group; }
.fdl-sheet.is-flow h2 { page-break-after: avoid; break-after: avoid; }

.fdl-sheet-hd { display: flex; align-items: flex-start; gap: 10px; border-bottom: 2px solid #1a2035;
    padding-bottom: 5px; margin-bottom: 6px; }
.fdl-sheet-hd h1 { font-size: 15pt; margin: 0 0 1px; letter-spacing: .2px; line-height: 1.1; }
.fdl-sheet-hd .sub { font-size: 7.6pt; color: #64748b; margin: 0; text-transform: uppercase; letter-spacing: .5px; }
.fdl-sheet-hd .ref { margin-left: auto; text-align: right; font-size: 7.8pt; color: #475569; display: flex; align-items: center; gap: 8px; }
.fdl-sheet-hd .ref b { display: block; font-size: 12pt; color: #1a2035; line-height: 1.1; }
/* The carrier's mark, at its own aspect — a logo box that crops a wide
   wordmark is worse than no logo at all. */
.fdl-mark { height: 26px; max-width: 74px; object-fit: contain; }
.fdl-mark-txt { font-weight: 800; font-size: 11pt; color: #334155; letter-spacing: .5px; }

/* Route line: a flag at every hop, including the via airport. */
.fdl-sheet-route { display: flex; align-items: center; flex-wrap: wrap; gap: 5px; background: #f1f5f9;
    border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px; margin-bottom: 6px; font-size: 10pt; font-weight: 700; }
.fdl-sheet-route .hop { display: inline-flex; align-items: center; gap: 4px; }
.fdl-sheet-route .sep { color: #94a3b8; font-weight: 400; }
.fdl-sheet-route .when { margin-left: auto; font-size: 7.8pt; font-weight: 600; color: #475569; }

.fdl-sheet h2 { font-size: 7.4pt; text-transform: uppercase; letter-spacing: .8px; color: #64748b;
    margin: 5px 0 2px; padding-bottom: 2px; border-bottom: 1px solid #e2e8f0; font-weight: 700; }
.fdl-sheet-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3px 10px; }
.fdl-sheet-f { min-width: 0; }
.fdl-sheet-f .k { display: block; font-size: 6.6pt; text-transform: uppercase; letter-spacing: .4px; color: #94a3b8; line-height: 1.25; }
.fdl-sheet-f .v { display: block; font-size: 8.6pt; word-break: break-word; font-weight: 600; line-height: 1.3; }

.fdl-sheet table { width: 100%; border-collapse: collapse; margin-top: 2px; font-size: 7.8pt; }
.fdl-sheet th { text-align: left; background: #f1f5f9; color: #475569; font-size: 6.6pt; text-transform: uppercase;
    letter-spacing: .3px; padding: 3px 5px; border: 1px solid #e2e8f0; font-weight: 700; }
.fdl-sheet td { padding: 3px 5px; border: 1px solid #e2e8f0; vertical-align: top; }
.fdl-sheet td.num, .fdl-sheet th.num { text-align: right; white-space: nowrap; }
.fdl-sheet tfoot td { font-weight: 700; background: #f8fafc; }
.fdl-sheet .muted { color: #94a3b8; }
.fdl-sheet .ok { color: #15803d; font-weight: 700; }
.fdl-sheet .bad { color: #b91c1c; font-weight: 700; }
.fdl-sheet .warn { color: #b45309; font-weight: 700; }
/* Flags: the same public/flags marks the cards use. */
.fdl-sheet .fl { width: 15px; height: 10px; object-fit: cover; border-radius: 2px; vertical-align: -1px;
    box-shadow: 0 0 0 1px #e2e8f0; margin-right: 3px; }
.fdl-sheet .al { height: 12px; max-width: 34px; object-fit: contain; vertical-align: -2px; margin-right: 3px; }

.fdl-money { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin-top: 2px; }
.fdl-money div { border: 1px solid #e2e8f0; border-radius: 5px; padding: 4px 7px; }
.fdl-money .k { display: block; font-size: 6.6pt; text-transform: uppercase; letter-spacing: .4px; color: #94a3b8; }
.fdl-money .v { display: block; font-size: 11pt; font-weight: 800; }
.fdl-money .m-profit .v { color: #15803d; }
.fdl-money .m-profit.neg .v { color: #b91c1c; }
.fdl-money .m-due .v { color: #b91c1c; }

.fdl-sheet-note { margin-top: 5px; padding-top: 3px; border-top: 1px solid #e2e8f0; font-size: 6.6pt; color: #94a3b8; }

/* Printing: the sheet is the only thing on the page. Everything else is
   removed rather than hidden, so no blank leading pages come out. */
@media print {
    body.fdl-printing > *:not(.fdl-sheet-overlay) { display: none !important; }
    body.fdl-printing .fdl-sheet-overlay { position: static !important; padding: 0 !important; background: #fff !important; overflow: visible !important; display: block !important; }
    body.fdl-printing .fdl-sheet-bar { display: none !important; }
    body.fdl-printing .fdl-sheet { width: auto !important; height: auto !important; box-shadow: none !important; padding: 0 !important; }
    @page { size: A4; margin: 11mm 12mm; }
}
@media (max-width: 230mm) {
    .fdl-sheet, .fdl-sheet-bar { width: 100%; }
    .fdl-sheet { height: auto; padding: 16px; }
    .fdl-sheet-grid { grid-template-columns: repeat(2, 1fr); }
    .fdl-money { grid-template-columns: repeat(2, 1fr); }
}
