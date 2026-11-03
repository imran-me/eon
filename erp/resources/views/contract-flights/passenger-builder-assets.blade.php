{{--
    Styles + the <template> a passenger card is cloned from, plus the inline
    "New Ticket" overlay. Included ONCE per page (from create-modal, which
    both the Manage Flight and Flight Schedule screens already include).

    Every selector is pb- prefixed so nothing here can reach the surrounding
    ERP shell or another company's page.

    The card is laid out in two columns, in the same card language step 1
    uses (tinted header + icon + note): who the person is and what we do for
    them on the left, their ticket and their money on the right. A single
    stack measured 664px tall inside a 622px modal body — one passenger did
    not fit on screen, and three made a 1400px scroll.
--}}
<style>
/* Stated rather than inherited from Tailwind's preflight: the inputs are
   width:100% inside 1fr grid tracks, so without border-box their padding and
   border add on top of the track and the last field in every row spills 7px
   past the card, where overflow:hidden clips it. */
.pb-cards,.pb-cards *,.pb-agent-bar,.pb-agent-bar *{box-sizing:border-box;}

.pb-agent-bar{display:flex;gap:10px;align-items:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:3px 12px;margin-bottom:8px;}
.pb-agent-note{flex:1;min-width:0;font-size:11px;color:#64748b;line-height:1.4;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pb-agent-note i{color:#2563eb;margin-right:5px;}

.pb-cards{display:flex;flex-direction:column;gap:10px;}
.pb-card{border:1px solid #dbe3ef;border-radius:12px;background:#fff;overflow:hidden;}

/* ── Header: the whole strip is the collapse toggle ───────────────── */
.pb-card-head{display:flex;align-items:center;gap:9px;padding:9px 13px;background:#f8fafc;border-bottom:1px solid #eef2f7;cursor:pointer;}
.pb-card.pb-collapsed .pb-card-head{border-bottom-color:transparent;}
.pb-seq{width:23px;height:23px;border-radius:7px;background:#1d4ed8;color:#fff;font-size:11.5px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.pb-card-name{font-size:13px;font-weight:700;color:#1a2035;flex-shrink:0;max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
/* Chips only while collapsed — open, the fields themselves already say it. */
.pb-card-tags{display:none;gap:5px;align-items:center;flex:1;min-width:0;overflow:hidden;}
.pb-card.pb-collapsed .pb-card-tags{display:flex;}
/* Too many chips for the width fade out rather than being sliced through the
   middle of a word, which reads as a rendering fault. */
.pb-card-tags{-webkit-mask-image:linear-gradient(to right,#000 calc(100% - 18px),transparent);mask-image:linear-gradient(to right,#000 calc(100% - 18px),transparent);}
/* Narrow: the chips take their own line under the name instead of competing
   with the cost/profit figure for the same strip. */
@media(max-width:900px){
    .pb-card-head{flex-wrap:wrap;}
    .pb-card-tags{order:3;flex:1 0 100%;overflow:visible;flex-wrap:wrap;-webkit-mask-image:none;mask-image:none;}
    .pb-card-name{max-width:none;flex:1;}
}
.pb-tag{font-size:10.5px;font-weight:600;color:#475569;background:#eef2f7;border-radius:20px;padding:2px 8px;white-space:nowrap;}
.pb-tag-warn{background:#fef3c7;color:#92400e;}
.pb-tag-ok{background:#dcfce7;color:#166534;}
.pb-card-sum{font-size:11.5px;color:#64748b;white-space:nowrap;margin-left:auto;}
.pb-sum-empty{font-style:normal;color:#b6c2d2;}
.pb-card-sum b{color:#0f766e;}
.pb-card-sum b.neg{color:#dc2626;}
.pb-icon-btn{width:26px;height:26px;border-radius:7px;border:1px solid #e2e8f0;background:#fff;color:#64748b;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.pb-icon-btn:hover{background:#fee2e2;color:#dc2626;border-color:#fca5a5;}
.pb-icon-btn.pb-collapse:hover{background:#e0e7ff;color:#1d4ed8;border-color:#a5b4fc;}

.pb-card-body{padding:10px;}
/* Says "this row, the one already here" when Add Passenger is pressed on a
   card nobody has filled in yet. */
@keyframes pbFlash{0%{box-shadow:0 0 0 0 rgba(29,78,216,.35);}100%{box-shadow:0 0 0 7px rgba(29,78,216,0);}}
.pb-card.pb-flash{animation:pbFlash .75s ease-out 2;border-color:#93c5fd;}
.pb-card.pb-collapsed .pb-card-body{display:none;}

/* ── Two columns inside the card ──────────────────────────────────── */
/* Wide (owner's sketch, 2026-09-21): the left half is Traveller on top and
   the Ticket section beneath it, both full width of that half — the ticket
   form is the one that grows; the Services & money panel stands tall on the right.
   Narrower: the two stacked columns as before. */
/* Owner, 2026-09-21: Traveller + Ticket (left) take ~60%, Services & money ~40%. */
.pb-cols{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,2fr);gap:10px;align-items:start;}
.pb-col-left{display:flex;flex-direction:column;gap:10px;min-width:0;grid-column:1;}
.pb-col-right{display:contents;}
/* Services & money is one panel, standing the full height of the right 40% */
.pb-sub-services{grid-column:2;min-width:0;}
/* Traveller, being wide now: holder | category+phone, then agent | remarks */
.pb-sub-traveller .pb-sub-bd{grid-template-columns:1fr 1fr;align-items:start;gap:6px 8px;padding:7px 9px;}
.pb-sub-ticket .pb-sub-bd{padding:7px 9px;}
.pb-sub-traveller .pb-agent-row{margin-top:0;}
@media(max-width:1200px){
  .pb-col{display:flex;flex-direction:column;gap:10px;min-width:0;}
  .pb-sub-traveller .pb-sub-bd{grid-template-columns:1fr;}
  .pb-sub-traveller .pb-agent-row{margin-top:8px;}
}
@media(max-width:900px){.pb-cols{grid-template-columns:1fr;} .pb-col-left,.pb-sub-services{grid-column:auto;}}

.pb-sub{border:1px solid #e7ecf3;border-radius:10px;overflow:hidden;}
.pb-sub-hd{display:flex;align-items:center;gap:7px;padding:7px 11px;background:#fafbfd;border-bottom:1px solid #eef2f7;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#64748b;}
.pb-sub-hd i{color:#94a3b8;font-size:11px;}
.pb-sub-note{margin-left:auto;font-weight:500;letter-spacing:0;text-transform:none;font-size:10.5px;color:#b6c2d2;white-space:nowrap;}
.pb-sub-bd{padding:9px;display:grid;gap:8px;}
.pb-sub-bd > *{min-width:0;}

/* auto-fit, so hiding Immigration on a manpower-wise passenger reflows the
   row instead of leaving a hole and orphaning the field after it. */
.pb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(128px,1fr));gap:10px;}
.pb-grid > *{min-width:0;}
.pb-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.pb-grid-2 > *{min-width:0;}
.pb-full{grid-column:1/-1;}
.pb-nt-status-row{display:flex;align-items:center;gap:12px;}
.pb-nt-tickets{display:grid;gap:12px;}
.pb-nt-ticket{position:relative;border:1px solid #e4e1f7;border-radius:12px;background:#fff;padding:12px 12px 12px 16px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
.pb-nt-ticket::before{content:'';position:absolute;left:0;top:12px;bottom:12px;width:4px;border-radius:0 4px 4px 0;background:linear-gradient(180deg,#7c3aed,#a78bfa);}
.pb-nt-ticket[data-status="booked"]::before{background:linear-gradient(180deg,#0284c7,#38bdf8);}
.pb-nt-ticket[data-status="draft"]::before,.pb-nt-ticket[data-status="pending"]::before{background:#cbd5e1;} .pb-nt-ticket[data-status="cancelled"]::before{background:#ef4444;}
.pb-nt-strip{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;padding:8px 12px;border-radius:9px;background:#f5f3ff;border:1px solid #e9e5fb;font-size:12px;color:#312e81;}
.pb-nt-strip .k{font-weight:800;color:#5b21b6;} .pb-nt-strip .sec{display:inline-flex;align-items:center;gap:6px;font-weight:700;font-size:13px;color:#1e1b4b;}
.pb-nt-strip .sec i{color:#a78bfa;font-size:10px;} .pb-nt-strip .note{margin-left:auto;font-size:10.5px;color:#7c7aa5;}
.pb-nt-strip .pb-flag{width:18px;height:13px;border-radius:2px;object-fit:cover;box-shadow:0 0 0 1px rgba(15,23,42,.12);vertical-align:-2px;margin-right:3px;}
.pb-nt-leg.is-fixed .pb-leg-remove{display:none;}
.pb-slot-hd{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:800;letter-spacing:.3px;text-transform:uppercase;color:#7c7aa5;margin-top:2px;}
.pb-slot-hd i{font-size:11px;color:#c4b5fd;} .pb-slot-hd.is-done{color:#5b21b6;} .pb-slot-hd.is-done i{color:#7c3aed;}
.pb-slot-hd em{font-style:normal;font-weight:500;text-transform:none;letter-spacing:0;color:#94a3b8;}
.pb-nt-status-wrap{width:190px;flex-shrink:0;}
.pb-nt-hold{padding:10px 10px 4px;border-radius:10px;background:#f0f9ff;border:1px solid #bae6fd;}
.pb-nt-hold .pb-lbl{background:#f0f9ff;} .pb-nt-hold .pb-lbl-warn{color:#d97706;}
.pb-nt-hold-hd{font-size:11px;color:#0369a1;font-weight:600;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.pb-nt-channels{display:flex;align-items:center;gap:12px;height:38px;padding:0 10px;border:1px solid #bae6fd;border-radius:10px;background:#fff;font-size:12px;} .pb-nt-channels label{display:inline-flex;align-items:center;gap:4px;cursor:pointer;} .pb-nt-channels input{accent-color:#0284c7;}
.pb-hold{display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px dashed #7dd3fc;border-radius:8px;background:#f0f9ff;font-size:12px;color:#0c4a6e;}
.pb-hold .pb-hold-st{font-size:10px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;padding:2px 7px;border-radius:999px;background:#e0f2fe;color:#0369a1;}
.pb-hold .pb-hold-st.is-expired{background:#fee2e2;color:#b91c1c;} .pb-hold .pb-hold-st.is-draft,.pb-hold .pb-hold-st.is-pending{background:#f1f5f9;color:#475569;}
.pb-hold .pb-hold-meta{color:#64748b;} .pb-hold a{margin-left:auto;color:#0369a1;font-weight:600;text-decoration:none;}
@media(max-width:560px){.pb-grid-2{grid-template-columns:1fr;}}

.pb-lbl{display:block;font-size:11.5px;font-weight:600;color:#475569;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pb-lbl .pb-opt{color:#94a3b8;font-weight:400;}
.pb-card .pb-lbl:has(~ input, ~ select, ~ textarea, ~ .select2-container, ~ div select),
.pb-nt-leg .pb-field-title{position:relative;z-index:1;display:block;width:fit-content;max-width:calc(100% - 16px);margin:0 0 -6px 8px;padding:0 4px;background:#fff;font-size:10.5px;line-height:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pb-card .pb-input,.pb-card .pb-select{padding-top:9px;}
.pb-nt-leg > .pb-lbl{overflow:visible;margin-bottom:0;}
.pb-req{color:#ef4444;}
.pb-input,.pb-select{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:7px 10px;font-size:12.5px;outline:none;font-family:'DM Sans',sans-serif;color:#1a2035;background:#fff;}
.pb-input:focus,.pb-select:focus{border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,.09);}
.pb-input[readonly]{background:#f1f5f9;color:#334155;font-weight:700;}
.pb-input:disabled{background:#f1f5f9;color:#94a3b8;}
.pb-holder-row{display:flex;gap:7px;align-items:flex-end;}
.pb-agent-row{margin-top:8px;}
.pb-legs-lbl{margin-left:auto;display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:500;color:#64748b;text-transform:none;letter-spacing:0;}
.pb-legs-lbl .pb-tickets-required{width:auto;min-width:46px;padding:2px 6px;height:24px;font-size:12px;}
.pb-tag-agent{background:#ede9fe;color:#5b21b6;}
.pb-holder-row > div{flex:1;min-width:0;}

/* Service mix — the three yes/no decisions that drive which costs apply.
   Stacked one per line: each carries a sentence, and three sentences side by
   side in a half-width column wrapped to four lines apiece. */
/* ── Service rows: tick + cost on one line, the accounting folded under ── */
.pb-svc-list{display:grid;gap:6px;}
.pb-svc{border:1px solid #e2e8f0;border-radius:9px;background:#fff;transition:border-color .12s,background .12s;}
.pb-svc:hover{border-color:#cbd5e1;}
.pb-svc:has(.pb-svc-tick input:checked){border-color:#6366f1;background:#eef2ff;}
.pb-svc-head{display:flex;align-items:center;gap:10px;padding:5px 10px 5px 8px;min-height:40px;}
.pb-svc-tick{display:flex;gap:9px;align-items:center;flex:1;min-width:0;cursor:pointer;margin:0;}
.pb-svc-tick input{flex-shrink:0;margin:0;accent-color:#4f46e5;}
.pb-svc-txt{display:flex;flex-direction:column;gap:1px;min-width:0;}
.pb-svc-txt b{font-size:12.5px;font-weight:700;color:#1a2035;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pb-svc-txt em{font-style:normal;font-size:11px;color:#94a3b8;line-height:1.35;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pb-svc .pb-svc-on{display:none;}
.pb-svc:has(.pb-svc-tick input:checked) .pb-svc-on{display:block;color:#4338ca;}
.pb-svc:has(.pb-svc-tick input:checked) .pb-svc-off{display:none;}
/* The cost box and (Others) the title share the head line with the tick. The
   notched label sits on a tinted row, so it takes the row's colour. */
.pb-svc-cost{width:128px;flex-shrink:0;}
.pb-svc-title{flex:1.4;min-width:120px;}
.pb-svc:has(.pb-svc-tick input:checked) .pb-svc-head .pb-lbl{background:#eef2ff;}
.pb-svc-head .pb-input{padding:7px 8px;font-size:11.5px;}
/* Accounting for the ticked service: a white tray inside the tinted row. */
.pb-svc .pb-sc-row{margin:0 6px 6px;border:1px solid #dbeafe;border-radius:8px;background:#fff;padding:7px 8px;}
.pb-sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;}
@media(max-width:560px){
  .pb-svc-head{flex-wrap:wrap;}
  .pb-svc-cost,.pb-svc-title{width:100%;flex:1 0 100%;}
}

/* Ticket panel header — existing tickets show directly (no toggle needed to
   see them); "Issue New Ticket" is the one explicit action that switches the
   panel away from them, and "Use existing ticket" switches back. Both are
   still .pb-mode-existing/.pb-mode-new so the existing click delegate (which
   only ever needs to show one pane and hide the other) needs no JS change. */
.pb-ticket-hd{display:flex;align-items:center;gap:8px;margin-bottom:8px;}
.pb-issue-new-btn{flex-shrink:0;border:1px dashed #93c5fd;background:#eff6ff;color:#1d4ed8;border-radius:7px;padding:6px 12px;font-size:11.5px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;white-space:nowrap;}
.pb-issue-new-btn:hover{background:#dbeafe;}
.pb-back-existing-btn{border:none;background:none;color:#64748b;font-size:11.5px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:5px;padding:0;}
.pb-back-existing-btn:hover{color:#1d4ed8;}
.pb-hint{font-size:11px;color:#94a3b8;margin:0;}
/* The type is a property of the category, so it is shown, never chosen. */
.pb-cat-type{font-size:10.5px;color:#94a3b8;margin-top:3px;min-height:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pb-cat-type:not(:empty)::before{content:"Type: ";}
.pb-hint.pb-warn{color:#b45309;}

.pb-pnr-list{display:flex;flex-direction:column;gap:6px;}
.pb-pnr-list:empty{display:none;}
.pb-pnr{display:flex;align-items:center;gap:7px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;padding:6px 9px;font-size:12px;}
.pb-pnr.pb-pick{border-style:dashed;cursor:pointer;}
.pb-pnr.pb-pick:hover{border-color:#1d4ed8;background:#eff6ff;}
.pb-pnr-no{font-family:'DM Mono',monospace;font-weight:700;color:#1d4ed8;}
.pb-pnr-meta{color:#64748b;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pb-pnr-amt{font-weight:700;color:#0f766e;white-space:nowrap;}
.pb-chip{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:2px 7px;border-radius:20px;}
.pb-chip-confirm{background:#dcfce7;color:#166534;}
.pb-chip-pending{background:#fef3c7;color:#92400e;}
.pb-chip-draft{background:#e2e8f0;color:#475569;}
.pb-chip-cancelled{background:#fee2e2;color:#991b1b;}
.pb-link-btn{border:none;background:#1d4ed8;color:#fff;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;}
.pb-unlink{border:none;background:transparent;color:#dc2626;font-size:11px;cursor:pointer;font-weight:600;}

/* Per-head money under the service rows */
.pb-svc-money{border-top:1px solid #eef2f7;padding-top:9px;display:grid;gap:8px;}
.pb-svc-money-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;}
.pb-svc-money-grid > *{min-width:0;}
@media(max-width:560px){.pb-svc-money-grid{grid-template-columns:1fr 1fr;}}
.pb-money{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;padding:5px 11px;font-size:11.5px;}
.pb-money span{color:#0f766e;font-weight:600;}
.pb-money b{font-size:13px;}
.pb-money .pb-neg{color:#dc2626;}
/* Re-booking box (2026-09-26): a passenger re-added from Cancelled — his card
   is filled from his previous one; this carries how, the charge and who pays. */
.pb-rebook{border:1px solid #fecaca;background:#fff7f7;border-radius:8px;padding:8px 10px;margin-bottom:8px;}
.pb-rebook-head{display:flex;gap:7px;align-items:flex-start;font-size:11.5px;color:#991b1b;line-height:1.35;margin-bottom:6px;}
.pb-rebook-head i{margin-top:2px;}
.pb-rebook-grid{display:grid;grid-template-columns:170px 160px 140px minmax(0,1fr);gap:8px;}
.pb-rebook-f{display:flex;flex-direction:column;gap:3px;margin:0;min-width:0;}
.pb-rebook-f span{font-size:10.5px;font-weight:600;color:#7f1d1d;}
.pb-rebook-f select,.pb-rebook-f input{width:100%;box-sizing:border-box;border:1px solid #fca5a5;border-radius:7px;padding:5px 8px;font-size:12px;background:#fff;}
.pb-rebook-hint{margin-top:6px;font-size:11px;color:#7f1d1d;min-height:14px;}
.pb-rebook-hint b{color:#991b1b;}
@media (max-width: 900px){.pb-rebook-grid{grid-template-columns:1fr 1fr;}}

/* Responsible person & payment fields, three to a line inside the service
   row: who / status / paid, then account / cost date / due or payment date. */
/* Column 1 takes the slack, columns 2 and 3 are fixed — both rows keep the
   same tracks so "Responsible person" and "Bank / cash account" line up, and
   so do status/cost-date and paid/due-date.
   Why 108px: the widest thing in columns 2-3 is a date input, and measured in
   Chrome it clips "mm/dd/yyyy" and the picker icon below 104px (96px shows
   "mm/dd/yy"). Pay status only ever needs 67px ("Partial"), Paid amount 64px.
   That leaves column 1 ~168px at the real ~400px panel width, enough for a
   full name like "Mohammad Abdul Karim" (168px) where an equal third gave it
   128px and truncated every instructor to "— select instruct…". */
.pb-sc-grid{display:grid;grid-template-columns:minmax(0,1fr) 108px 108px;gap:6px 8px;}
.pb-sc-grid > *{min-width:0;}
.pb-sc-grid .select2-container{max-width:100%;}
.pb-sc-grid .pb-lbl{font-size:10.5px;}
.pb-sc-grid .pb-input,.pb-sc-grid .pb-select{font-size:11.5px;padding:7px 8px;}
@media(max-width:700px){.pb-sc-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:480px){.pb-sc-grid{grid-template-columns:1fr;}}

.pb-empty{border:1px dashed #cbd5e1;border-radius:12px;padding:26px 16px;text-align:center;color:#94a3b8;font-size:12.5px;}
.pb-empty i{display:block;font-size:22px;margin-bottom:8px;opacity:.5;}

.pb-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;}
.pb-add-btn{border:1px dashed #93c5fd;background:#eff6ff;color:#1d4ed8;border-radius:9px;padding:9px 16px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;}
.pb-add-btn:hover{background:#dbeafe;}
.pb-add-btn-top{padding:5px 12px;border-radius:7px;text-transform:none;letter-spacing:normal;flex-shrink:0;}
.pb-rollup{margin-left:auto;display:flex;gap:14px;flex-wrap:wrap;font-size:11px;color:#64748b;text-transform:none;letter-spacing:normal;margin-right:10px;}
.pb-rollup b{color:#1a2035;font-size:12.5px;}
.pb-rollup b.pb-profit{color:#0f766e;}
.pb-rollup b.pb-profit.neg{color:#dc2626;}

.pb-card .select2-container .select2-selection--single{height:34px !important;border:1px solid #d1d5db;border-radius:8px;display:flex;align-items:center;}
.pb-card .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:32px;padding-left:9px;font-size:12.5px;color:#1a2035;}
.pb-card .select2-container--default .select2-selection--single .select2-selection__arrow{height:32px;}

/* Passport holder / vendor / portal search list — themed the same way the
   Quick Task picker's dropdown is (see quick-task-select2-dropdown in
   layout/app.blade.php), since it also renders outside .pb-card via
   dropdownParent and select2's default theme would otherwise show through
   unstyled. */
.pb-select2-dropdown{border:1px solid #dbe3ef;border-radius:10px;box-shadow:0 12px 28px -10px rgba(15,23,42,.22);overflow:hidden;font-family:'DM Sans',sans-serif;}
.pb-select2-dropdown .select2-search{padding:8px;}
.pb-select2-dropdown .select2-search__field{border:1px solid #d1d5db;border-radius:7px;padding:6px 10px;font-size:12.5px;outline:none;}
.pb-select2-dropdown .select2-search__field:focus{border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,.09);}
.pb-select2-dropdown .select2-results__options{padding:4px;max-height:220px;}
.pb-select2-dropdown .select2-results__option{border-radius:7px;padding:7px 10px;font-size:12.5px;color:#1a2035;}
.pb-select2-dropdown .select2-results__option--highlighted[aria-selected]{background:#fff1e9;color:#c2410c;}
.pb-select2-dropdown .select2-results__option[aria-selected=true]:not(.select2-results__option--highlighted){background:#f8fafc;font-weight:700;}
.pb-holder-opt-name{font-weight:600;line-height:1.3;}
.pb-holder-opt-meta{font-size:11px;color:#94a3b8;font-weight:400;margin-top:1px;}
.select2-results__option--highlighted .pb-holder-opt-meta{color:#c2410c;opacity:.75;}
.pb-z{--z:#2563eb;--zb:#f7faff;--zl:#c5d7f7;background:var(--zb);border:1.5px solid var(--zl);border-left:4px solid var(--z);border-radius:10px;padding:7px 10px 2px;margin-top:2px;box-shadow:0 1px 2px rgba(15,23,42,.05),0 3px 10px rgba(15,23,42,.05);}
.pb-z.z-flight{--z:#0891b2;--zb:#f5fcfd;--zl:#b8e2ea;} .pb-z.z-fare{--z:#059669;--zb:#f6fcf9;--zl:#b5e2cd;}
.pb-z-h{display:flex;align-items:center;gap:7px;margin:0 0 6px;font-size:9.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--z);}
.pb-z-h i{width:17px;height:17px;border-radius:5px;background:#fff;color:var(--z);display:inline-flex;align-items:center;justify-content:center;font-size:8.5px;box-shadow:0 0 0 1px var(--zl);}
.pb-z-h::after{content:'';flex:1;border-top:1px solid var(--zl);margin-left:4px;}
.pb-zr{display:grid;gap:8px;margin-bottom:7px;align-items:end;}
.pb-zr > *{min-width:0;}
.pb-zr.c3{grid-template-columns:repeat(3,minmax(0,1fr));}
.pb-zr.c-fare{grid-template-columns:1fr 1fr .8fr 1fr 1.4fr;}
.pb-zr.c-fl{grid-template-columns:repeat(12,minmax(0,1fr));}
.pb-zr.c-fl > *{grid-column:span 3;} .pb-zr.c-fl > :nth-child(-n+3){grid-column:span 4;}
.pb-zr .pb-input,.pb-zr .pb-select{width:100%;box-sizing:border-box;}
@media(max-width:900px){.pb-zr.c3,.pb-zr.c-fare,.pb-zr.c-fl{grid-template-columns:repeat(2,minmax(0,1fr));} .pb-zr.c-fl > *,.pb-zr.c-fl > :nth-child(-n+3){grid-column:auto;}}
.pb-import-pdf{margin-right:6px;}
.pb-imp-take{position:absolute;inset:0;z-index:60;display:flex;flex-direction:column;background:#fff;border-radius:inherit;overflow:hidden;}
.pb-imp-hd{display:flex;align-items:center;gap:12px;padding:10px 14px;background:linear-gradient(90deg,#c2410c,#ea580c);color:#fff;font-weight:700;font-size:12.5px;flex-shrink:0;}
.pb-imp-back{border:0;border-radius:8px;background:#fff;color:#c2410c;font-weight:800;font-size:12px;padding:6px 12px;cursor:pointer;}
.pb-imp-back:hover{background:#fff7ed;}
.pb-imp-take iframe{flex:1;width:100%;border:0;}
.pbs-card{border:1px solid #e2e8f0;border-left:4px solid #7c3aed;border-radius:10px;background:#fff;padding:10px 12px;margin-bottom:8px;box-shadow:0 1px 2px rgba(15,23,42,.05),0 4px 12px rgba(15,23,42,.05);}
.pbs-card.is-cancelled{border-left-color:#ef4444;opacity:.75;}
.pbs-top{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;}
.pbs-route{font-size:14px;font-weight:800;color:#1e1b4b;letter-spacing:.02em;} .pbs-route i{color:#7c3aed;font-size:12px;margin-right:4px;}
.pbs-tags{display:flex;gap:5px;flex-wrap:wrap;} .pbs-tags span{font-size:10.5px;font-weight:700;text-transform:capitalize;padding:2px 8px;border-radius:999px;background:#f5f3ff;color:#5b21b6;}
.pbs-tags i{margin-right:3px;}
.pbs-seller{margin:6px 0 8px;font-size:12px;color:#475569;font-weight:600;} .pbs-seller i{color:#d97706;margin-right:5px;} .pbs-seller em{font-style:normal;font-size:10px;color:#0284c7;}
.pbs-money{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;}
.pbs-money > div{background:#f8fafc;border:1px solid #eef1f5;border-radius:8px;padding:6px 8px;min-width:0;}
.pbs-money small{display:block;font-size:9.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#94a3b8;}
.pbs-money b{font-size:12.5px;color:#1e293b;font-variant-numeric:tabular-nums;white-space:nowrap;} .pbs-money b.g{color:#059669;} .pbs-money b.r{color:#dc2626;}
.pbs-pill{display:inline-block;margin-top:2px;font-size:10.5px;font-weight:800;text-transform:uppercase;padding:2px 8px;border-radius:999px;background:#fef2f2;color:#b91c1c;}
.pbs-pill.paid{background:#ecfdf5;color:#047857;} .pbs-pill.partial{background:#fffbeb;color:#b45309;}
.pbs-meta{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px;font-size:11.5px;color:#64748b;} .pbs-meta i{color:#94a3b8;margin-right:4px;}
.pbs-legs{list-style:none;margin:10px 0 0;padding:8px 0 0 4px;border-top:1px dashed #e2e8f0;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:6px 12px;}
.pbs-legs li{position:relative;padding-left:16px;}
.pbs-legs .pbs-dot{position:absolute;left:0;top:5px;width:8px;height:8px;border-radius:50%;background:#0891b2;box-shadow:0 0 0 3px #cffafe;}
.pbs-legs b{display:block;font-size:12px;color:#0f172a;} .pbs-legs b i{font-size:9px;color:#0891b2;margin:0 3px;}
.pbs-legs small{font-size:10.5px;color:#64748b;}
@media(max-width:700px){.pbs-money{grid-template-columns:repeat(2,minmax(0,1fr));}}
.pbs-ids{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:7px 0 2px;}
.pbs-ids span{display:inline-flex;align-items:center;gap:6px;height:26px;padding:0 10px;border-radius:7px;background:#faf5ff;border:1px solid #ede9fe;font-size:12px;font-weight:700;font-family:ui-monospace,monospace;color:#4c1d95;white-space:nowrap;}
.pbs-ids span > i{font-size:10px;color:#a78bfa;}
/* what a linked ticket still lacks (owner, 2026-10-17) — optional on a contract flight, listed so it can be completed */
.pbs-missing{display:flex;align-items:center;gap:6px;margin:6px 0 2px;padding:5px 9px;border-radius:7px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:11.5px;font-weight:600;cursor:help;} .pbs-missing i{color:#d97706;}
.pbs-flag{width:16px;height:11px;border-radius:2px;object-fit:cover;margin-right:4px;vertical-align:-1px;box-shadow:0 0 0 1px rgba(15,23,42,.12);}
.pbs-logo{width:22px;height:22px;border-radius:6px;object-fit:contain;background:#fff;flex-shrink:0;box-shadow:0 0 0 1px rgba(15,23,42,.1);}
.pbs-ids .pbs-logo{width:16px;height:16px;border-radius:4px;}
.pbs-logo-x{display:inline-flex;align-items:center;justify-content:center;font-size:8px;font-weight:800;color:#fff;background:#4f46e5;font-family:system-ui,sans-serif;}
.pbs-legs{grid-template-columns:1fr!important;gap:0!important;padding:6px 0 0!important;}
.pbs-legs li{display:grid!important;grid-template-columns:24px 64px minmax(40px,1fr) 64px auto;align-items:center;gap:8px;padding:6px 4px!important;border-bottom:1px solid #f1f5f9;}
.pbs-legs li:last-child{border-bottom:0;}
.pbs-ap{display:flex;align-items:center;font-size:12.5px;font-weight:800;color:#0f172a;white-space:nowrap;}
.pbs-line{position:relative;height:1px;border-top:1.5px dashed #a5b4fc;}
.pbs-line i{position:absolute;left:50%;top:-8px;margin-left:-6px;font-size:11px;color:#0891b2;background:#fff;padding:0 2px;}
.pbs-lmeta{font-size:11px;color:#64748b;white-space:nowrap;text-align:right;}
.pbs-lmeta b{display:block;font-size:11px;color:#334155;font-weight:700;}
</style>

{{-- ── One passenger card, cloned per row ─────────────────────────── --}}
<template id="pbCardTemplate">
    <div class="pb-card" data-row="">
        <div class="pb-card-head">
            <span class="pb-seq"></span>
            <span class="pb-card-name">New passenger</span>
            <span class="pb-card-tags"></span>
            <span class="pb-card-sum"></span>
            <button type="button" class="pb-icon-btn pb-collapse" title="Collapse"><i class="fas fa-chevron-up"></i></button>
            <button type="button" class="pb-icon-btn pb-remove" title="Remove passenger"><i class="fas fa-trash"></i></button>
        </div>
        <div class="pb-card-body">
            <div class="pb-cols">

                {{-- ── Left: traveller and ticket ── --}}
                <div class="pb-col pb-col-left">
                    <section class="pb-sub pb-sub-traveller">
                        <header class="pb-sub-hd"><i class="fas fa-user"></i> Traveller</header>
                        <div class="pb-sub-bd">
                            <div class="pb-holder-row">
                                <div>
                                    <label class="pb-lbl">Passport holder <span class="pb-req">*</span></label>
                                    <select class="pb-holder" style="width:100%"></select>
                                </div>
                                <button type="button" class="fcm-mini-add pb-new-holder" style="height:34px;width:34px;flex:0 0 34px;" title="Add a passport holder"><i class="fas fa-plus"></i></button>
                            </div>
                            <div class="pb-grid-2">
                                <div>
                                    <label class="pb-lbl">Category <span class="pb-opt">(why they travel)</span></label>
                                    <select class="pb-select pb-category">
                                        <option value="">— Select —</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}" data-type="{{ optional($cat->categoryType)->name }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="pb-cat-type"></div>
                                </div>
                                <div>
                                    <label class="pb-lbl">Contact phone <span class="pb-opt">— optional</span></label>
                                    <input type="text" class="pb-input pb-phone" placeholder="+880 1XXX-XXXXXX">
                                </div>
                            </div>
                            {{-- One aircraft routinely carries several agents' people, so the
                                 agent is chosen per head. Step 1's agent is only the default. --}}
                            <div class="pb-agent-row">
                                <label class="pb-lbl">Billed to agent <span class="pb-req">*</span> <span class="pb-opt">— who pays</span></label>
                                <select class="pb-agent" style="width:100%"></select>
                            </div>
                            <div>
                                <label class="pb-lbl">Remarks <span class="pb-opt">— optional</span></label>
                                <input type="text" class="pb-input pb-remarks" placeholder="Anything ops needs to know…">
                            </div>
                        </div>
                    </section>

                    <section class="pb-sub pb-sub-ticket">
                        <header class="pb-sub-hd">
                            <i class="fas fa-ticket"></i> Ticket
                            <span class="pb-sub-note">Sets the ticket cost</span>
                            {{-- DAC → ECN travels on two PNRs (DAC-IST + IST-ECN); the
                                 passenger counts as ticketed only when every leg is confirmed. --}}
                            <label class="pb-legs-lbl" title="How many separate tickets this route takes">
                                Tickets on route
                                <select class="pb-select pb-tickets-required">
                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option>
                                </select>
                            </label>
                        </header>
                        <div class="pb-sub-bd">
                            <div class="pb-existing" style="display:grid;gap:8px;">
                                <div class="pb-ticket-hd">
                                    <input type="text" class="pb-input pb-pnr-search" placeholder="Search this passenger's PNR…" style="flex:1;">
                                    {{-- Ticket Entry's own PDF import, opened inside the flight (owner, 2026-10-08):
                                         same readers, same save, same postings; what it saves is linked here. --}}
                                    <button type="button" class="pb-issue-new-btn pb-import-pdf" title="Read a ticket PDF — same as Ticketing → Import PDF"><i class="fas fa-file-pdf"></i> Import PDF</button>
                                    <button type="button" class="pb-mode-new pb-issue-new-btn">
                                        <i class="fas fa-plus"></i> Issue New Ticket
                                    </button>
                                </div>
                                <div class="pb-pnr-list pb-linked"></div>
                                <div class="pb-pnr-list pb-holds"></div>
                                <div class="pb-pnr-list pb-results"></div>
                                <div class="pb-hint pb-ticket-hint">Choose a passport holder to see the tickets already on file for them.</div>
                            </div>

                            <div class="pb-newticket" style="display:none;">
                                <div class="pb-ticket-hd" style="justify-content:space-between;">
                                    <span class="pb-hint" style="margin:0;">New PNR for this passenger — same fields as the Ticketing screen.</span>
                                    <button type="button" class="pb-mode-existing pb-back-existing-btn">
                                        <i class="fas fa-arrow-left"></i> Use existing ticket
                                    </button>
                                </div>
                                <div class="pb-grid">
                                    <div class="pb-full">
                                        <label class="pb-lbl">Ticket route <span class="pb-opt">— needed to purchase</span></label>
                                        <div style="display:flex;gap:6px;">
                                            <select class="pb-select pb-nt-route" style="width:100%"></select>
                                            <button type="button" class="pb-link-btn pb-route-toggle" title="Create ticket route">+</button>
                                        </div>
                                    </div>
                                    <div class="pb-full pb-route-create" style="display:none;">
                                        <div class="pb-grid-2">
                                            <div><label class="pb-lbl">Route title *</label><input class="pb-input pb-route-title" type="text" maxlength="255"></div>
                                            <div><label class="pb-lbl">Default cost</label><input class="pb-input pb-route-price" type="number" min="0" step="0.01"></div>
                                            <div><label class="pb-lbl">Airline</label><select class="pb-select pb-route-airline"><option value="">Select airline</option>@foreach($airlines as $airline)<option value="{{ $airline->id }}">{{ $airline->name }}</option>@endforeach</select></div>
                                            <div><label class="pb-lbl">From airport</label><select class="pb-select pb-route-from"><option value="">Select airport</option>@foreach($airports as $airport)<option value="{{ $airport->id }}">{{ $airport->name }}</option>@endforeach</select></div>
                                            <div><label class="pb-lbl">To airport</label><select class="pb-select pb-route-to"><option value="">Select airport</option>@foreach($airports as $airport)<option value="{{ $airport->id }}">{{ $airport->name }}</option>@endforeach</select></div>
                                        </div>
                                        <button type="button" class="pb-link-btn pb-route-save" style="margin-top:8px;">Save route</button>
                                        <p class="pb-hint pb-warn pb-route-error" role="alert"></p>
                                    </div>
                                    {{-- One block per TICKET (owner, 2026-09-21): a direct route is one
                                         block; a via route (DAC-IST-ECN) is two — Ticket 1 of 2 · DAC → IST,
                                         Ticket 2 of 2 · IST → ECN — each with its own status, seller, PNR,
                                         money, attachment and hold, exactly as Ticket Entry lays them out.
                                         Block 0 is the template; pbEnsureTicketBlocks() clones it. --}}
                                    <div class="pb-full pb-nt-tickets">
                                    <div class="pb-nt-ticket" data-leg="0">
                                        <div class="pb-nt-strip" style="display:none;"><span class="k"></span><span class="sec"></span><span class="note"></span></div>
                                        <div class="pb-grid">
                                    {{-- Same choice as Ticket Entry (owner, 2026-09-21): Purchased writes
                                         the purchase and hits the ledger; Booked / Pending / Draft /
                                         Cancelled write a Ticketing booking row and touch no money. --}}
                                    <div class="pb-full pb-nt-status-row">
                                        <div class="pb-nt-status-wrap">
                                            <label class="pb-lbl">Ticket status</label>
                                            <select class="pb-select pb-nt-status">
                                                <option value="purchased" selected>Purchased</option>
                                                <option value="booked">Booked (hold)</option>
                                                <option value="pending">Pending</option>
                                                <option value="draft">Draft</option>
                                                <option value="cancelled">Cancelled</option>
                                            </select>
                                        </div>
                                        <span class="pb-hint pb-nt-status-hint" style="margin:0;">Purchased posts to the vendor / portal ledger. Anything else is a record in Ticket Orders only.</span>
                                    </div>
                                    {{-- Laid out like Ticket Entry (owner, 2026-10-08: "restructure and align it, I liked
                                         the Ticketing module's style"): three tinted panels, aligned rows. --}}
                                    <div class="pb-full pb-z z-ticket">
                                        <div class="pb-z-h"><i class="fas fa-ticket-alt"></i> Ticket &amp; seller</div>
                                        <div class="pb-zr c3">
                                            <div>
                                                <label class="pb-lbl">PNR / Ticket no <span class="pb-opt">— needed to purchase</span></label>
                                                <input type="text" class="pb-input pb-nt-no" placeholder="e.g. 2H4KLM">
                                            </div>
                                            <div>
                                                <label class="pb-lbl">Name Ticket No. <span class="pb-opt">— optional</span></label>
                                                <input type="text" class="pb-input pb-nt-name-no" maxlength="100" placeholder="e.g. 997-2345678901">
                                            </div>
                                            <div>
                                                <label class="pb-lbl">Purchase date</label>
                                                <input type="date" class="pb-input pb-nt-date">
                                            </div>
                                        </div>
                                        <div class="pb-zr c3">
                                            <div><label class="pb-lbl">Validating carrier</label><select class="pb-select pb-nt-vc"><option value="">— Airline that issues —</option>@foreach($airlines as $airline)<option value="{{ $airline->id }}">{{ $airline->name }}</option>@endforeach</select></div>
                                            <div>
                                                <label class="pb-lbl">Vendor</label>
                                                <select class="pb-select pb-nt-vendor" style="width:100%"></select>
                                            </div>
                                            <div>
                                                <label class="pb-lbl">Portal</label>
                                                <select class="pb-select pb-nt-portal" style="width:100%"></select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pb-full pb-z z-flight">
                                        <div class="pb-z-h"><i class="fas fa-plane-departure"></i> Flight</div>
                                        <div class="pb-zr c-fl">
        <div><label class="pb-lbl">Flight no.</label><input type="text" maxlength="20" class="pb-input pb-nt-flightno" placeholder="GF 249" style="text-transform:uppercase"></div>
        <div><label class="pb-lbl">Departure</label><input type="text" data-dt class="pb-input pb-nt-dep"></div>
        <div><label class="pb-lbl">Arrival</label><input type="text" data-dt class="pb-input pb-nt-arr"></div>
        <div><label class="pb-lbl">RBD</label><input type="text" maxlength="4" class="pb-input pb-nt-rbd" placeholder="Y" style="text-transform:uppercase;text-align:center"></div>
        <div><label class="pb-lbl">Free baggage KG</label><input type="number" min="0" step="0.01" class="pb-input pb-nt-free-bag"></div>
                                            <div>
                                                <label class="pb-lbl">Trip</label>
                                                <select class="pb-select pb-nt-trip">
                                                    <option value="one-way">One-way</option>
                                                    <option value="two-way">Two-way</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="pb-lbl">Class</label>
                                                <select class="pb-select pb-class">
                                                    @foreach(\App\Models\ContractFlightPassenger::TICKET_CLASSES as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    @if(\App\Services\TicketBaggage::ready())
                                    <div class="pb-full pb-z z-baggage">
                                        <div class="pb-z-h"><i class="fas fa-suitcase"></i> Extra baggage</div>
                                        <div class="pb-zr c-fare">
                                            <div><label class="pb-lbl">Extra KG</label><input type="number" min="0" step="0.01" class="pb-input pb-nt-extra-bag"></div>
                                            <div><label class="pb-lbl">Cost</label><input type="number" min="0" step="0.01" class="pb-input pb-nt-bag-cost" style="text-align:right;color:#dc2626"></div>
                                            <div><label class="pb-lbl">Agent charge</label><input type="number" min="0" step="0.01" class="pb-input pb-nt-bag-charge" style="text-align:right;color:#dc2626"></div>
                                            <div><label class="pb-lbl">Who pays</label><select class="pb-select pb-nt-bag-bearer" style="box-sizing:border-box;min-height:0"><option value="">Choose</option><option value="customer">Agent pays</option><option value="company">We bear it</option></select></div>
                                            <div><label class="pb-lbl">Baggage profit</label><input type="text" readonly tabindex="-1" class="pb-input pb-nt-bag-profit"></div>
                                        </div>
                                    </div>
                                    @endif
                                    <div class="pb-full pb-z z-fare">
                                        <div class="pb-z-h"><i class="fas fa-coins"></i> Fare &amp; payment</div>
                                        <div class="pb-zr c-fare">
        <div><label class="pb-lbl">Base fare</label><input type="number" min="0" step="0.01" class="pb-input pb-nt-fare-in" placeholder="0.00"></div>
        <div><label class="pb-lbl">Taxes</label><input type="number" min="0" step="0.01" class="pb-input pb-nt-tax" placeholder="0.00"></div>
        <div><label class="pb-lbl">Comm. %</label><input type="number" min="0" max="100" step="0.0001" class="pb-input pb-nt-compct" placeholder="0"></div>
        <div><label class="pb-lbl">Comm. ৳</label><input type="number" min="0" step="0.01" class="pb-input pb-nt-comamt" placeholder="0.00"></div>
        <div><label class="pb-lbl">Tax codes</label><input type="text" maxlength="255" class="pb-input pb-nt-taxcodes" placeholder="YQ 12000, BD 500"></div>
                                        </div>
                                        <div class="pb-zr c3">
                                            <div>
                                                <label class="pb-lbl">Ticket cost <span class="pb-opt">— needed to purchase</span></label>
                                                <input type="number" min="0" step="0.01" class="pb-input pb-nt-amount" placeholder="0.00">
                                            </div>
                                            <div>
                                                <label class="pb-lbl">Cost paid</label>
                                                <input type="number" min="0" step="0.01" class="pb-input pb-nt-paid" placeholder="0.00" value="0">
                                            </div>
                                            <div>
                                                <label class="pb-lbl">Pay status</label>
                                                <select class="pb-select pb-nt-pay-status">
                                                    <option value="due">Due</option>
                                                    <option value="partial">Partial</option>
                                                    <option value="paid">Paid</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="pb-zr c3">
                                            <div>
                                                <label class="pb-lbl">Cost bank</label>
                                                <select class="pb-select pb-nt-bank" style="width:100%"></select>
                                            </div>
                                            <div>
                                                <label class="pb-lbl">Cost due date <span style="font-weight:400;color:#cbd5e1;">(payable schedule)</span></label>
                                                <input type="date" class="pb-input pb-nt-due-date">
                                            </div>
                                            <div>
                                                <label class="pb-lbl">Attachment <span class="pb-opt">— pdf / image</span></label>
                                                <input type="file" class="pb-input pb-nt-attach" accept=".pdf,.jpg,.jpeg,.png">
                                            </div>
                                        </div>
                                    </div>
                                    {{-- Hold — shown while status = Booked. Same fields as Ticket Entry's hold box. --}}
                                    <div class="pb-full pb-nt-hold" style="display:none;">
                                        <div class="pb-nt-hold-hd"><i class="fas fa-clock"></i> Hold — reserved, not paid. Cost above = quoted amount. Reminders 24h / 3h / 1h before and at expiry.</div>
                                        <div class="pb-grid">
                                            <div><label class="pb-lbl">Booking date &amp; time</label><input type="datetime-local" class="pb-input pb-nt-booking-at" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
                                            <div><label class="pb-lbl">Departure date &amp; time</label><input type="datetime-local" class="pb-input pb-nt-departure-at"></div>
                                            <div><label class="pb-lbl">Return date &amp; time</label><input type="datetime-local" class="pb-input pb-nt-return-at"></div>
                                            <div><label class="pb-lbl pb-lbl-warn">Hold expires at <span class="pb-opt">— needed to book</span></label><input type="datetime-local" class="pb-input pb-nt-expires-at" value="{{ now()->addHours(6)->format('Y-m-d\TH:i') }}"></div>
                                            <div class="pb-full pb-grid-2">
                                                <div><label class="pb-lbl">Notify boss / user</label><select class="pb-select pb-nt-recipient"><option value="">— nobody —</option>@foreach(($bookingRecipients ?? collect()) as $recipient)<option value="{{ $recipient->id }}" @selected(($bookingBoss ?? null)?->id === $recipient->id)>{{ $recipient->name }}{{ $recipient->name === 'Md Mohsin' ? ' (Boss)' : '' }} — {{ $recipient->email ?: $recipient->phone }}</option>@endforeach</select></div>
                                                <div><label class="pb-lbl">Notification channels</label><div class="pb-nt-channels"><label><input type="checkbox" class="pb-nt-ch" data-key="notify_in_app" checked> In-app bell</label><label><input type="checkbox" class="pb-nt-ch" data-key="notify_email" checked> Email</label><label><input type="checkbox" class="pb-nt-ch" data-key="notify_sms" checked> SMS</label></div></div>
                                            </div>
                                            <div class="pb-full"><label class="pb-lbl">Notes</label><textarea class="pb-input pb-nt-notes" rows="2" style="height:auto;min-height:52px;" placeholder="Airline instructions, fare rules or follow-up notes…"></textarea></div>
                                        </div>
                                    </div>
                                    <div class="pb-full">
                                        <div class="pb-nt-legs-hd" style="display:flex;justify-content:space-between;align-items:center;">
                                            <label class="pb-lbl">Route legs <span class="pb-opt">(optional, cost per segment)</span></label>
                                            <button type="button" class="pb-link-btn pb-leg-add">+ Add leg</button>
                                        </div>
                                        <div class="pb-nt-legs" style="display:grid;gap:8px;margin-top:8px;"></div>
                                    </div>
                                        </div>
                                    </div>
                                    </div>
                                    <div class="pb-full" style="display:flex;align-items:flex-end;">
                                        <button type="button" class="pb-link-btn pb-nt-save" style="width:100%;padding:8px;">
                                            <i class="fas fa-plus"></i> <span class="pb-nt-save-txt">Create &amp; link</span>
                                        </button>
                                    </div>
                                </div>
                                <p class="pb-hint" style="margin-top:8px;">
                                    Passenger comes from the Traveller section. The selected route supplies the default ticket cost; you can adjust it before creating the ticket. Segment costs describe the itinerary and do not add to the ticket total.
                                </p>
                                <p class="pb-hint pb-warn pb-nt-err" style="display:none;margin-top:6px;"></p>
                            </div>
                        </div>
                    </section>
                </div>

                {{-- ── Right: services and money, one panel ──
                     Restructured 2026-09-21: the tick, its cost and the
                     responsible person / payment for a service used to live in
                     three boxes (Services, Money, Responsible person). Now each
                     service is one row — tick + cost on the head line, the
                     accounting fields folded out beneath it while it is ticked —
                     so nothing about a service is entered in two places. The
                     per-head money (ticket, agent price, reissue, totals) sits
                     under the rows. Every class the script reads is unchanged. --}}
                @php
                    $pbServiceRows = [
                        // 'default' is the standing fee a new card starts with (owner,
                        // 2026-09-22). It is only a starting figure: it fills an empty,
                        // untouched box on a NEW card, a rate preset overrides it, and
                        // the moment someone types in the box it is never re-applied.
                        // A saved passenger never receives one — a blank recorded on
                        // a real passenger is a deliberate blank.
                        'immigration' => ['tick' => 'pb-svc-immigration', 'wrap' => 'pb-immigration-wrap', 'cost' => 'pb-immigration', 'checked' => false, 'default' => 25000, 'name' => 'Immigration Training', 'on' => 'Trainer-led', 'off' => 'Goes alone'],
                        'boarding'    => ['tick' => 'pb-svc-boarding',    'wrap' => 'pb-boarding-wrap',    'cost' => 'pb-boarding',    'checked' => true,  'default' => 15000, 'name' => 'Boarding Training',    'on' => 'Trainer-led', 'off' => 'Not taken'],
                        'manpower'    => ['tick' => 'pb-svc-bmet',        'wrap' => 'pb-manpower-wrap',    'cost' => 'pb-manpower',    'checked' => false, 'default' => null,  'name' => 'BMET / Work Permit',   'on' => 'Consultant arranges it', 'off' => 'Already holds a permit'],
                        'other'       => ['tick' => 'pb-svc-other',       'wrap' => 'pb-other-fields',     'cost' => 'pb-other-cost',  'checked' => false, 'default' => null,  'name' => 'Others',               'on' => '', 'off' => ''],
                    ];
                @endphp
                <div class="pb-col pb-col-right">
                    <section class="pb-sub pb-sub-services">
                        <header class="pb-sub-hd">
                            <i class="fas fa-clipboard-list"></i> Services &amp; money
                            <span class="pb-sub-note">Per head, billed to the agent</span>
                        </header>
                        <div class="pb-sub-bd">
                            <div class="pb-svc-list">
                                @foreach($pbServiceRows as $costType => $svc)
                                    @php $costDefinition = \App\Models\ContractFlightPassengerServiceCost::TYPES[$costType]; @endphp
                                    <div class="pb-svc">
                                        <div class="pb-svc-head">
                                            <label class="pb-svc-tick">
                                                <input type="checkbox" class="{{ $svc['tick'] }}" @checked($svc['checked'])>
                                                <span class="pb-svc-txt">
                                                    <b>{{ $svc['name'] }}</b>
                                                    @if($svc['on'])<em class="pb-svc-on">{{ $svc['on'] }}</em>@endif
                                                    @if($svc['off'])<em class="pb-svc-off">{{ $svc['off'] }}</em>@endif
                                                </span>
                                            </label>
                                            @if($costType === 'other')
                                                <div class="pb-svc-title pb-other-fields" style="display:none;">
                                                    <label class="pb-lbl">Service title</label>
                                                    <input type="text" maxlength="255" class="pb-input pb-other-title" placeholder="What we do for them" aria-label="Other service title">
                                                </div>
                                            @endif
                                            <div class="pb-svc-cost {{ $svc['wrap'] }}" @if(! $svc['checked']) style="display:none;" @endif>
                                                <label class="pb-lbl">{{ $costDefinition['provider'] === 'trainer' ? 'Fee' : 'Cost' }} </label>
                                                <input type="number" min="0" step="0.01" class="pb-input pb-cost {{ $svc['cost'] }}" @isset($svc['default']) data-default="{{ $svc['default'] }}" @endisset placeholder="0.00">
                                            </div>
                                        </div>
                                        {{-- Responsible person & payment for this service. Shown by
                                             pbSyncServiceCostRows() while the service is ticked. The
                                             voucher / reference is generated by the server
                                             (serviceCostReference). --}}
                                        <div class="pb-sc-row" data-cost-type="{{ $costType }}" data-provider="{{ $costDefinition['provider'] }}" style="display:none;">
                                            <span class="pb-sc-label pb-sr-only">{{ $costDefinition['label'] }}</span>
                                            <div class="pb-sc-grid">
                                                <div>
                                                    <label class="pb-lbl">{{ $costDefinition['provider'] === 'trainer' ? 'Responsible person' : 'Vendor / provider' }}</label>
                                                    <select class="pb-select pb-sc-vendor" data-sc-field="vendor_id" style="width:100%"></select>
                                                </div>
                                                <div>
                                                    <label class="pb-lbl">Pay status</label>
                                                    <select class="pb-select pb-sc-status" data-sc-field="payment_status">
                                                        <option value="due">Due</option>
                                                        <option value="partial">Partial</option>
                                                        <option value="paid">Paid</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="pb-lbl">Paid amount</label>
                                                    <input type="number" min="0" step="0.01" value="0" class="pb-input pb-sc-paid" data-sc-field="paid_amount">
                                                </div>
                                                <div>
                                                    <label class="pb-lbl">Bank / cash account</label>
                                                    <select class="pb-select pb-sc-bank" data-sc-field="bank_id" style="width:100%"></select>
                                                </div>
                                                <div>
                                                    <label class="pb-lbl">Cost date</label>
                                                    <input type="date" class="pb-input pb-sc-incurred" data-sc-field="incurred_on">
                                                </div>
                                                <div class="pb-sc-due-date-wrap">
                                                    <label class="pb-lbl">Due date</label>
                                                    <input type="date" class="pb-input pb-sc-due-date" data-sc-field="due_date">
                                                </div>
                                                <div class="pb-sc-payment-date-wrap" style="display:none;">
                                                    <label class="pb-lbl">Payment date</label>
                                                    <input type="date" class="pb-input pb-sc-payment-date" data-sc-field="payment_date">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- The three selects the rest of the code reads. Kept in the DOM
                                 (not rebuilt as checkboxes) so hydrate/submit/validation and
                                 the passenger edit modal all keep speaking the same values. --}}
                            <select class="pb-select pb-bmet" hidden>
                                <option value="0">Not yet</option>
                                <option value="1">Has BMET</option>
                            </select>
                            <select class="pb-select pb-boarding-on" hidden>
                                <option value="1">Boarding training</option>
                                <option value="0">No boarding training</option>
                            </select>
                            <select class="pb-select pb-handling" hidden>
                                @foreach(\App\Models\ContractFlightPassenger::HANDLING_TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            {{-- Per head: what the ticket cost us, what the agent pays, and
                                 the reissue that is recorded but kept out of the margin. --}}
                            <div class="pb-svc-money">
                                <div class="pb-svc-money-grid">
                                    <div>
                                        <label class="pb-lbl">Ticket <span class="pb-opt">(from PNR)</span></label>
                                        <input type="number" class="pb-input pb-ticket-cost" readonly value="0">
                                    </div>
                                    <div>
                                        <label class="pb-lbl">Agent price</label>
                                        <input type="number" min="0" step="0.01" class="pb-input pb-cost pb-agent-price" placeholder="0.00">
                                    </div>
                                    <div>
                                        <label class="pb-lbl">Reissue <span class="pb-opt">(not in cost)</span></label>
                                        <input type="number" min="0" step="0.01" class="pb-input pb-reissue" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="pb-money">
                                    <span>Cost <b class="pb-total-cost">৳0.00</b></span>
                                    <span>Charge <b class="pb-total-charge">৳0.00</b></span>
                                    <span>Profit <b class="pb-total-profit">৳0.00</b></span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
