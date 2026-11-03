{{-- The table bar: search, range segment, dd/mm/yyyy dates with the native
     picker, Apply, Print, count pill. Value for value the bank feed's .lf-bar
     in banks/dashboard.blade.php (owner, 2026-09-15: "the same header of the
     table … like I have in Manage Bank"), prefixed pb- so a change to one
     desk cannot move the other. Keep the two in step. --}}
<style>
    #partyLedgerPager { background: #fff !important; padding: 12px 16px !important; }
    .party-pager-info { font-size: 11.5px; font-weight: 600; color: #64748b; }
    .party-pager-nav { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-left: auto; }
    .party-pager-nav button { min-width: 32px; height: 32px; padding: 0 9px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; transition: background .15s, color .15s, border-color .15s; }
    .party-pager-nav button:hover:not(:disabled) { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }
    .party-pager-nav button.is-on { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; font-weight: 800; }
    .party-pager-nav button:disabled { opacity: .4; cursor: default; }
    .party-pager-gap { padding: 0 2px; color: #94a3b8; font-size: 12px; }
    .pb-bar {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 11px 14px; background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .pb-search { position: relative; flex: 1 1 260px; min-width: 200px; }
    #stBar .pb-search { flex: 1 1 280px; min-width: 220px; max-width: 400px; }
    /* one row on a laptop and up (owner, 2026-10-26): the view select + Reversal history pushed the dates down */
    @media (min-width: 1200px) {
        #stBar { flex-wrap: nowrap; gap: 8px; }
        #stBar .pb-search { flex: 1 1 140px; min-width: 120px; }
        #stBar > * { flex-shrink: 1; min-width: 0; }
    }
    .pb-search > i {
        position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: 12px; pointer-events: none;
    }
    .pb-search input {
        width: 100%; height: 34px; padding: 0 30px 0 30px;
        border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;
        font-size: 12.5px; color: #1e293b; outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .pb-search input::placeholder { color: #a3adbb; }
    .pb-search input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    .pb-clear {
        position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
        border: 0; background: none; padding: 4px; cursor: pointer;
        color: #cbd5e1; font-size: 12px; line-height: 1; display: none;
    }
    .pb-clear:hover { color: #64748b; }
    .pb-search.has-q .pb-clear { display: block; }
    /* Type dropdown beside the search box (party tables) */
    .pb-kind { position: relative; flex: 0 0 auto; }
    .pb-kind-btn {
        display: flex; align-items: center; gap: 7px; width: 170px; height: 34px; padding: 0 11px;
        border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; cursor: pointer;
        font-size: 11.5px; font-weight: 600; color: #334155; white-space: nowrap;
    }
    .pb-kind-btn span { flex: 1; text-align: left; overflow: hidden; text-overflow: ellipsis; }
    .pb-kind-btn .pb-kind-car { font-size: 9px; color: #94a3b8; }
    .pb-kind.is-on .pb-kind-btn { border-color: #c7d2fe; background: #eef2ff; }
    .pb-kind-menu {
        display: none; position: absolute; right: 0; top: calc(100% + 4px); z-index: 30; min-width: 180px;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px;
        box-shadow: 0 10px 25px rgba(15,23,42,.12);
    }
    .pb-kind.is-open .pb-kind-menu { display: block; }
    .pb-bar:has(.pb-kind.is-open) { position: relative; z-index: 40; }
    .pb-kind-menu button {
        display: flex; align-items: center; gap: 9px; width: 100%; padding: 7px 10px; border: 0; border-radius: 6px;
        background: none; cursor: pointer; font-size: 12px; color: #334155; text-align: left;
    }
    .pb-kind-menu button i { width: 14px; text-align: center; }
    .pb-kind-menu button:hover, .pb-kind-menu button.is-sel { background: #f1f5f9; }
    .pb-kind-menu button.is-sel { font-weight: 700; }

    .pb-seg { display: flex; flex: 0 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff; }
    .pb-seg button {
        border: 0; border-left: 1px solid #e2e8f0; background: none;
        padding: 0 11px; height: 34px; cursor: pointer;
        font-size: 11.5px; font-weight: 600; color: #64748b; white-space: nowrap;
        transition: background .15s, color .15s;
    }
    .pb-seg button:first-child { border-left: 0; }
    .pb-seg button:hover { background: #f1f5f9; color: #334155; }
    .pb-seg button.is-on { background: #eef2ff; color: #4338ca; font-weight: 700; }

    .pb-dates {
        display: flex; flex: 0 0 auto; align-items: stretch; gap: 0;
        height: 34px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }
    .pb-dates:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    .pb-dates > span { display: flex; align-items: center; padding: 0 4px; font-size: 11px; color: #94a3b8; }
    .pb-date {
        position: relative; display: inline-flex; align-items: center;
        height: 32px; padding-right: 5px; background: none;
        transition: background .15s;
    }
    .pb-dates input.pb-date-text {
        width: 88px; height: 32px; padding: 0 2px 0 9px;
        border: 0; background: none; outline: none;
        font-size: 12px; color: #334155;
    }
    .pb-date.is-bad { background: #fef2f2; }
    .pb-dates:has(.pb-date.is-bad) { border-color: #fca5a5; }
    .pb-dates input.pb-date-text.is-bad { color: #b91c1c; }
    .pb-dates input.pb-date-native {
        width: 22px; height: 22px; flex: 0 0 auto;
        margin: 0; padding: 0; border: 0; background: none;
        color: transparent; overflow: hidden; cursor: pointer;
    }
    .pb-date-native::-webkit-datetime-edit,
    .pb-date-native::-webkit-inner-spin-button,
    .pb-date-native::-webkit-clear-button { display: none; }
    .pb-date-native::-webkit-calendar-picker-indicator {
        width: 22px; height: 22px; margin: 0; padding: 0;
        opacity: 0.5; cursor: pointer; transition: opacity .15s;
    }
    .pb-date:hover .pb-date-native::-webkit-calendar-picker-indicator,
    .pb-date-native:focus::-webkit-calendar-picker-indicator { opacity: 0.9; }
    .pb-dates.is-open { border-color: #c7d2fe; }
    .pb-dates.is-open .pb-date { background: #f5f6ff; }

    .pb-go {
        height: 32px; padding: 0 11px; border: 0; border-left: 1px solid #e2e8f0; border-radius: 0; cursor: pointer;
        background: none; color: #64748b; font-size: 11.5px; font-weight: 600; white-space: nowrap;
        transition: background .15s, color .15s;
    }
    .pb-go:hover { background: #f1f5f9; color: #334155; }
    .pb-dates.is-open .pb-go { background: #eef2ff; color: #4338ca; font-weight: 700; }
    .pb-date-reset {
        display: none; height: 32px; padding: 0 9px; border: 0; border-left: 1px solid #e2e8f0;
        background: #fff; color: #dc2626; cursor: pointer; font-size: 11px; font-weight: 700; white-space: nowrap;
    }
    .pb-dates.is-open .pb-date-reset { display: inline-flex; align-items: center; gap: 4px; }
    .pb-date-reset:hover { background: #fef2f2; }
    .pb-print {
        height: 34px; padding: 0 12px; border: 1px solid #e2e8f0; border-radius: 8px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none; cursor: pointer;
        background: #fff; color: #475569; font-size: 11.5px; font-weight: 700;
        transition: background .15s, color .15s, border-color .15s;
    }
    .pb-print:hover { background: #f8fafc; color: #334155; border-color: #cbd5e1; text-decoration: none; }
    .pb-print i { font-size: 11px; }
    .pb-count {
        flex-shrink: 0; font-size: 11px; font-weight: 700; color: #4338ca;
        background: #eef2ff; border-radius: 20px; padding: 4px 10px; white-space: nowrap;
    }
    .pb-count.is-none { color: #b91c1c; background: #fef2f2; }

    /* Row picking for print — Manage Bank's .txn-pick / .lf-sel, element for
       element. The tick column exists only while Print → "Selected …" has
       switched the table into picking mode: is-picking goes on the table or
       its wrapper (psPrintSetup's host), and the strip under the bar shows
       the count and the shortcuts. */
    .ps-pick { width: 34px; text-align: center !important; padding-left: 10px !important; padding-right: 4px !important; display: none; }
    .is-picking .ps-pick { display: table-cell; }
    .is-picking tbody tr[data-pick-id] { cursor: pointer; }
    .ps-pick-box { width: 15px; height: 15px; margin: 0; vertical-align: middle; cursor: pointer; accent-color: #4f46e5; }
    tr.is-picked td { background: #f5f6ff !important; }
    .pb-sel {
        display: none; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 7px 14px; background: #eef2ff; border-bottom: 1px solid #e0e7ff;
        font-size: 11.5px; color: #3730a3; font-weight: 600;
    }
    .pb-sel.is-on { display: flex; }
    .pb-sel .pb-sel-n { font-weight: 800; }
    .pb-sel button {
        height: 26px; padding: 0 10px; border: 1px solid #c7d2fe; border-radius: 6px; cursor: pointer;
        background: #fff; color: #4338ca; font-size: 11px; font-weight: 700;
    }
    .pb-sel button:hover { background: #f5f6ff; border-color: #a5b4fc; }
    .pb-sel .pb-sel-print { background: #4f46e5; color: #fff; border: 1px solid #4f46e5; text-decoration: none;
        height: 26px; padding: 0 12px; border-radius: 6px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
    .pb-sel .pb-sel-print:hover { background: #4338ca; color: #fff; }
    .pb-sel .pb-sel-cancel { background: #fff !important; color: #64748b !important; border-color: #e2e8f0 !important; }
    .pb-print.has-sel { border-color: #4f46e5; color: #4f46e5; background: #f5f6ff; }
</style>
