/*
 * Styles for partials/monthly-summary.blade.php.
 *
 * Kept separate from the markup so each host view can drop it inside its own
 * <style> block in <head> — DomPDF is far more reliable about stylesheets it
 * finds in the head than about a <style> tag sitting mid-document.
 *
 * Every selector is `ms-` prefixed. Nothing here targets a bare element, so
 * including it cannot restyle the payslip around it.
 *
 * A CSS comment rather than a Blade one so editors parsing this as a
 * stylesheet do not flag the whole file.
 */
.ms-wrap {
    margin-top: 18px;
    font-family: 'Helvetica', sans-serif;
    color: #334155;
    font-size: 12px;
}

.ms-title {
    font-size: 14px;
    font-weight: bold;
    color: #0f172a;
    border-bottom: 2px solid #0f172a;
    padding-bottom: 6px;
    margin-bottom: 12px;
}

.ms-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 14px;
}

.ms-th {
    background-color: #f1f5f9;
    color: #0f172a;
    font-size: 11px;
    font-weight: bold;
    padding: 6px 8px;
    border-bottom: 1px solid #cbd5e1;
}

.ms-td {
    padding: 5px 8px;
    border-bottom: 1px solid #eef2f7;
}

.ms-td.ms-last {
    border-bottom: none;
    font-weight: bold;
}

.ms-minus {
    color: #b91c1c;
}

.ms-plus {
    color: #047857;
}

/* Currency prefix used instead of U+09F3: the PDF engine's bundled fonts do
   not contain that glyph and render it as a question mark or empty square. */
.ms-taka {
    font-size: 0.72em;
    letter-spacing: 0.02em;
}

.ms-status {
    margin-bottom: 12px;
    line-height: 1.9;
}

.ms-status-label {
    font-weight: bold;
    color: #0f172a;
    margin-right: 6px;
}

.ms-chip {
    display: inline-block;
    background-color: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #334155;
    font-size: 10px;
    padding: 2px 7px;
    margin-right: 5px;
}

.ms-note {
    font-size: 10px;
    color: #64748b;
    line-height: 1.5;
}

/* Payment-status line under the net box on page 1 of the payslip. */
.ms-paid-line {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
}

.ms-paid-label {
    font-size: 10px;
    color: #64748b;
    text-transform: uppercase;
    padding: 6px 2px;
}

.ms-paid-val {
    font-size: 11px;
    color: #334155;
    padding: 6px 2px;
}

/* Live outstanding table on the signed view page. */
.ms-out-total td {
    border-top: 1px solid #cbd5e1;
    font-weight: bold;
    padding-top: 8px;
}

.ms-asof {
    font-size: 10px;
    color: #64748b;
    font-weight: normal;
}

.ms-hist-current td {
    background-color: #f8fafc;
    font-weight: bold;
}

/* The whole month lands on one page. Sized against the measured output: 31
   rows plus a header and a two-line footer fill the sheet without spilling —
   the first cut used 8px/2px and left a third of the page empty. */
.ms-att .ms-th,
.ms-att .ms-td {
    font-size: 9px;
    padding: 3px 5px;
}

.ms-att-note {
    font-size: 9px;
    color: #64748b;
}

.ms-st-late    { color: #b45309; }
.ms-st-absent  { color: #b91c1c; }
.ms-st-holiday { color: #0369a1; }
.ms-st-leave   { color: #7c3aed; }
.ms-st-present { color: #047857; }

.ms-hist .ms-th,
.ms-hist .ms-td {
    font-size: 11px;
    padding: 9px 6px;
}

/* Highlight panel under the payroll history. With only a few months on file
   the table alone leaves the sheet half empty, and these are figures worth
   calling out anyway rather than making the reader add up a column. */
.ms-cards {
    width: 100%;
    border-collapse: collapse;
    margin: 6px 0 14px 0;
}

.ms-card-cell {
    border: 1px solid #e2e8f0;
    background-color: #f8fafc;
    padding: 14px 12px;
    width: 25%;
    text-align: center;
}

.ms-card-label {
    font-size: 9px;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 6px;
}

.ms-card-value {
    font-size: 15px;
    font-weight: bold;
    color: #0f172a;
}
