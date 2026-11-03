<style>
/* ── Summary Cards ── */
.bt-summary-card {
    border-left: 4px solid transparent;
    transition: transform .15s ease, box-shadow .15s ease;
}
.bt-summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
}
.bt-summary-card--total     { border-left-color: #6b7280; }
.bt-summary-card--completed { border-left-color: #10b981; }
.bt-summary-card--pending   { border-left-color: #f59e0b; }
.bt-summary-card--amount    { border-left-color: #3b82f6; }

/* ── Status Badges ── */
.bt-badge {
    display: inline-flex;
    align-items: center;
    padding: .2rem .6rem;
    border-radius: 9999px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
}
.bt-badge--completed { background: #d1fae5; color: #065f46; }
.bt-badge--pending   { background: #fef3c7; color: #92400e; }
.bt-badge--cancelled { background: #fee2e2; color: #991b1b; }

/* ── Transfer Arrow (create / edit / show) ── */
.bt-arrow-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
}
.bt-arrow {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .25rem;
    color: #3b82f6;
}
.bt-arrow svg { width: 2rem; height: 2rem; }
.bt-arrow span { font-size: .75rem; font-weight: 700; color: #1d4ed8; }

/* ── Bank Selection Card (create / edit) ── */
.bt-bank-box {
    border: 2px solid #e5e7eb;
    border-radius: .75rem;
    padding: 1rem;
    transition: border-color .2s, background .2s;
}
.bt-bank-box:focus-within {
    border-color: #3b82f6;
    background: #eff6ff;
}

/* ── Ledger Timeline (show) ── */
.bt-timeline { position: relative; padding-left: 1.75rem; }
.bt-timeline-item { position: relative; padding-bottom: 1.25rem; }
.bt-timeline-item::before {
    content: '';
    position: absolute;
    left: -1.25rem;
    top: 1rem;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}
.bt-timeline-item:last-child::before { display: none; }
.bt-timeline-dot {
    position: absolute;
    left: -1.625rem;
    top: .3rem;
    width: .75rem;
    height: .75rem;
    border-radius: 50%;
    border: 2px solid #fff;
}
.bt-timeline-dot--debit  { background: #ef4444; box-shadow: 0 0 0 2px #ef4444; }
.bt-timeline-dot--credit { background: #10b981; box-shadow: 0 0 0 2px #10b981; }

/* ── Sticky Sidebar ── */
.bt-sidebar-sticky {
    position: sticky;
    top: 1rem;
}

/* ── Table Hover ── */
.bt-table tr:hover td { background: #f9fafb; }

/* ── The list holds one line per row, at any browser zoom ──────────────
   Owner, 2026-08-22: "there are two view of 90% and 80%. 80% one is perfect,
   i want the same for 90%."

   At 80% zoom the table has about 1332px and every row is one line. At 90% it
   has about 1146px, and that was 186px short: the reference number broke across
   three lines at its hyphens, "PETTY CASH (GROUP)" took two, and the rows grew
   from 53px to 85px. Measured in headless Chrome against this stylesheet, the
   table wrapped at anything under about 1330px.

   Three things buy the 186px back, and none of them drops a fact off the page:

     nowrap        no cell may break mid-value. A reference number, a bank name
                   and a date are single values; a value split over three lines
                   is harder to read than a narrower column.
     .75rem sides  the cells' own px-4 becomes px-3. Nine columns, 8px each, 72px.
     remarks 160   the only column with no natural width — a note can be any
                   length. Capped, it stops setting the table's minimum, and the
                   tail becomes an ellipsis with the whole note still in the
                   cell's tooltip.

   Min-content is now 1125px, so the 90% view fits with about 20px to spare and
   the 80% view is untouched — it has room left over and every column takes the
   same width it took before. Below about 1125px (100% zoom on a 1366 screen)
   the panel scrolls sideways rather than wrapping: one line per row is the
   thing being protected.

   Scoped to .bt-table--list, which only the transfers list carries. The table
   on a transfer's own page shares .bt-table and is deliberately left alone. */
.bt-table--list th,
.bt-table--list td {
    white-space: nowrap;
    padding-left: .75rem;
    padding-right: .75rem;
}
/* ── The width budget, re-cut for the remarks (owner, 2026-08-22) ──────
   "reduce the size of status column by 40% and, from bank to bank by 20%,
   to make more spaces for remark."

   The table's width is a fixed budget — at 90% zoom about 1146px — so a column
   can only grow by another one giving room up. Measured at that width, before
   and after:

       From Bank   189 -> 148   (-21%)
       To Bank     205 -> 148   (-27%)
       Status      116 ->  69   (-40%)
       Remarks     164 -> 304   (+87%)

   Both bank columns are capped at the SAME 143px even though that is a deeper
   cut for To Bank than for From Bank. They draw from one list of banks — the
   same name can appear on either side — so two different widths would only look
   like an accident. The name and the account line each clip with an ellipsis and
   the cell's title carries both in full.

   Status reaches 40% by giving up the cell's own side padding rather than by
   shrinking the word past reading: 12px each side becomes 2px, and the badge
   goes from 11.2px to 8.8px. "COMPLETED" still fits on one line — that was the
   floor the column could not go under, and it is why the cut is exactly 40% and
   not more.

   Everything still holds one line per row: measured 0 wrapped runs of text at
   1400 / 1332 / 1146 / 1125px, with min-content now 1108px — 10px lower than
   before, so the 90% view keeps its headroom. */
.bt-table--list .bt-remarks {
    max-width: 300px;
    min-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* The two bank columns. Capped on the cell, clipped on each of its two lines —
   the name and the account number are separate boxes, so the ellipsis has to be
   asked for on both. */
.bt-table--list .bt-bank { max-width: 143px; }
.bt-table--list .bt-bank > span,
.bt-table--list .bt-bank > div {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bt-table--list .bt-status {
    padding-left: .125rem;
    padding-right: .125rem;
    text-align: center;
}
/* Scoped to the list. The badge on a transfer's own page keeps its full size —
   there is no width to fight for on a detail screen. */
.bt-table--list .bt-badge {
    font-size: .55rem;
    padding: .13rem .28rem;
    letter-spacing: .01em;
}

/* The clock under the date, transcribed from the banks ledger's recordedTimeLine
   so the two screens print the same cell. */
.bt-table--list .bt-when-t {
    font-size: .7rem;
    color: #94a3b8;
    margin-top: 2px;
}

/* ── Print ── */
@media print {
    .no-print { display: none !important; }
    .bt-summary-card { break-inside: avoid; }
}

/* ── Insufficient balance shake animation ── */
@keyframes bt-shake {
    0%, 100% { transform: translateX(0); }
    25%       { transform: translateX(-4px); }
    75%       { transform: translateX(4px); }
}
.bt-shake { animation: bt-shake .3s ease; }

/* Bank transfer list header and create dialog */
.bt-page-header { background:linear-gradient(120deg,#0f4c91,#0b72df);border-radius:14px;padding:18px 20px;color:#fff;box-shadow:0 10px 24px rgba(15,76,145,.18) }
.bt-page-header h1 { font-size:1.35rem;font-weight:800;display:flex;align-items:center;gap:10px }
.bt-page-header p { margin-top:3px;font-size:.78rem;color:#dbeafe }
.bt-filter-toolbar { display:flex;align-items:center;gap:5px;padding:7px;white-space:nowrap }
.bt-filter-toolbar select,.bt-filter-toolbar input { height:34px;border:1px solid #d8e0eb;background:#fff;border-radius:7px;padding:0 9px;font-size:.7rem;color:#475569;outline:none }
.bt-filter-toolbar select:focus,.bt-filter-toolbar input:focus { border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,.1) }
.bt-filter-search { position:relative;flex:1;min-width:170px }.bt-filter-search i{position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:.65rem;color:#94a3b8}.bt-filter-search input{width:100%;padding-left:28px}
.bt-filter-toolbar select[name="from_bank"],.bt-filter-toolbar select[name="to_bank"] { width:165px }
.bt-filter-toolbar select[name="status"] { width:90px }
.bt-filter-toolbar input[type="date"] { width:124px;padding-right:5px }
.bt-quick-ranges { display:flex;height:34px;border:1px solid #d8e0eb;border-radius:7px;overflow:hidden }
.bt-quick-ranges button { border:0;border-left:1px solid #e2e8f0;background:#fff;color:#52647a;padding:0 9px;font-size:.65rem;cursor:pointer }.bt-quick-ranges button:first-child{border-left:0}.bt-quick-ranges button:hover{background:#eff6ff;color:#1d4ed8}
.bt-date-separator { color:#94a3b8;font-size:.65rem }
.bt-filter-apply { height:34px;border:0;border-radius:7px;background:#315bd6;color:#fff;padding:0 14px;font-size:.7rem;font-weight:750;cursor:pointer }
.bt-filter-reset { height:34px;padding:0 10px;border:1px solid #d8e0eb;border-radius:7px;display:inline-flex;align-items:center;gap:5px;color:#64748b;font-size:.68rem;font-weight:700;white-space:nowrap }
@media(max-width:1200px){.bt-filter-toolbar{overflow-x:auto}.bt-filter-search{min-width:210px;flex:0 0 210px}}
.bt-modal { position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;padding:18px }
.bt-modal.is-open { display:flex }
.bt-modal-backdrop { position:absolute;inset:0;background:rgba(15,23,42,.62);backdrop-filter:blur(3px) }
.bt-modal-panel { position:relative;width:min(920px,96vw);max-height:94vh;background:#f8fafc;border-radius:16px;box-shadow:0 28px 70px rgba(2,6,23,.35);overflow:hidden;animation:btModalIn .18s ease-out }
@keyframes btModalIn { from{opacity:0;transform:translateY(14px) scale(.985)} to{opacity:1;transform:none} }
.bt-modal-header { background:linear-gradient(120deg,#0759b8,#0b74ed);color:#fff;padding:15px 20px;display:flex;align-items:center;gap:12px }
.bt-modal-icon { width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.16);display:grid;place-items:center }
.bt-modal-header h2 { font-size:1rem;font-weight:800 }.bt-modal-header p{font-size:.7rem;color:#dbeafe;margin-top:2px}
.bt-modal-close { margin-left:auto;width:34px;height:34px;border:0;border-radius:9px;background:rgba(255,255,255,.14);font-size:1.55rem;line-height:1;color:#fff;cursor:pointer }
.bt-modal-close:hover { background:rgba(255,255,255,.25) }
.bt-modal-body { padding:16px 18px;max-height:calc(94vh - 142px);overflow-y:auto;display:grid;gap:12px }
.bt-section { background:#fff;border:1px solid #dbe4ef;border-radius:11px;padding:13px }
.bt-section-title { color:#52647a;text-transform:uppercase;letter-spacing:.07em;font-size:.68rem;font-weight:800;margin-bottom:10px;display:flex;align-items:center;gap:7px }
.bt-section-title i{color:#1681ed}.bt-section-title em{margin-left:auto;font-style:normal;font-weight:600;color:#94a3b8}
.bt-route-grid { display:grid;grid-template-columns:1fr 40px 1fr;align-items:center;gap:10px }
.bt-route-arrow { width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#eff6ff;color:#1681ed }
.bt-form-grid { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px }
.bt-form-grid--four { grid-template-columns:repeat(4,minmax(0,1fr)) }
.bt-optional-section { background:#fbfdff }
.bt-field label { display:block;color:#475569;font-size:.69rem;font-weight:700;margin-bottom:5px }.bt-field label span{color:#ef4444}
.bt-field input,.bt-field select,.bt-field textarea { width:100%;border:1px solid #cbd5e1;border-radius:8px;background:#fff;padding:8px 10px;font-size:.78rem;color:#1e293b;outline:none;transition:.15s }
.bt-field input:focus,.bt-field select:focus,.bt-field textarea:focus { border-color:#1681ed;box-shadow:0 0 0 3px rgba(22,129,237,.12) }
.bt-field small { display:block;margin-top:4px;color:#8493a7;font-size:.65rem }
.bt-account-balance { width:max-content;min-width:126px;padding:5px 9px;border-radius:7px;border:1px solid;display:flex!important;align-items:center;justify-content:space-between;gap:10px }
.bt-account-balance span { font-size:.6rem;font-weight:650 }
.bt-account-balance strong { font-size:.78rem;font-weight:850 }
.bt-account-balance--source { background:#ecfdf5;border-color:#a7f3d0;color:#047857!important }
.bt-account-balance--destination { background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8!important }
.bt-time-picker { display:grid;grid-template-columns:1fr auto 1fr 1.1fr;gap:4px;align-items:center }
.bt-time-picker select { padding-left:7px;padding-right:4px;text-align:center }
.bt-time-picker > span { color:#94a3b8;font-weight:800 }
.bt-form-errors { background:#fff1f2;border:1px solid #fecdd3;color:#9f1239;border-radius:9px;padding:9px 12px;font-size:.72rem }.bt-form-errors ul{margin:4px 0 0 16px;list-style:disc}
.bt-transfer-summary { overflow:hidden;border:1px solid #bfdbfe;border-radius:10px;background:#fff }
.bt-summary-title { padding:8px 11px;background:#eff6ff;color:#1d4ed8;text-transform:uppercase;letter-spacing:.06em;font-size:.68rem;font-weight:800 }
.bt-summary-title i { margin-right:5px }
.bt-summary-content { display:grid;grid-template-columns:2.2fr 2.2fr 1.15fr .95fr 1fr 1.1fr 1.05fr;align-items:stretch }
.bt-summary-line { min-width:0;padding:9px 10px;border-right:1px solid #e5edf7;display:flex;flex-direction:column;gap:3px }
.bt-summary-line span { color:#748399;font-size:.61rem;text-transform:uppercase;letter-spacing:.03em }
.bt-summary-line strong { overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#26384f;font-size:.72rem }
.bt-summary-line:nth-child(1) strong,.bt-summary-line:nth-child(2) strong { white-space:normal;overflow:visible;text-overflow:clip;line-height:1.35 }
.bt-summary-line.bt-summary-amount strong { color:#0874e6;font-size:.9rem }
.bt-summary-line:last-of-type { border-right:0 }
.bt-summary-line strong.is-negative { color:#dc2626 }
.bt-balance-warning { display:none;grid-column:1/-1;padding:7px 10px;background:#fff1f2;border-top:1px solid #fecdd3;color:#be123c;font-size:.68rem;font-weight:650 }
.bt-balance-warning.is-visible { display:block }
.bt-balance-warning i { margin-right:5px }
.bt-modal-footer { background:#fff;border-top:1px solid #e2e8f0;padding:12px 18px;display:flex;align-items:center;justify-content:flex-end;gap:9px }
.bt-modal-total { margin-right:auto;display:flex;flex-direction:column;color:#64748b;font-size:.66rem }.bt-modal-total strong{font-size:1rem;color:#0759b8}
.bt-btn-secondary,.bt-btn-primary { border-radius:8px;padding:9px 15px;font-size:.75rem;font-weight:750;cursor:pointer }
.bt-btn-secondary { background:#fff;border:1px solid #cbd5e1;color:#475569 }.bt-btn-primary{border:0;background:#0874e6;color:#fff;min-width:158px}
.bt-btn-primary:disabled{cursor:wait;opacity:.78}.bt-submit-loading{display:none}.bt-btn-primary.is-loading .bt-submit-idle{display:none}.bt-btn-primary.is-loading .bt-submit-loading{display:inline}
body.bt-modal-open { overflow:hidden }
@media(max-width:760px){.bt-form-grid,.bt-form-grid--four{grid-template-columns:1fr 1fr}.bt-route-grid{grid-template-columns:1fr}.bt-route-arrow{transform:rotate(90deg);margin:auto}.bt-modal-total{display:none}.bt-summary-content{grid-template-columns:1fr 1fr}.bt-summary-line{border-bottom:1px solid #e5edf7}}
@media(max-width:480px){.bt-form-grid,.bt-form-grid--four{grid-template-columns:1fr}.bt-modal{padding:7px}.bt-modal-footer{position:sticky;bottom:0}}
</style>
