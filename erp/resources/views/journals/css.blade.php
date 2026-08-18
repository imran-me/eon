<style>
    /* ── Base variables ──────────────────────────────────── */
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --primary-light: #dbeafe;
        --success: #16a34a;
        --success-light: #d1fae5;
        --danger: #dc2626;
        --danger-light: #fee2e2;
        --warning: #d97706;
        --warning-light: #fef3c7;
        --info: #0369a1;
        --info-light: #e0f2fe;
        --surface: #f8fafc;
        --border: #e2e8f0;
        --text-primary: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
        --radius: 10px;
        --shadow-sm: 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
        --shadow-md: 0 4px 16px rgba(0,0,0,.08), 0 2px 6px rgba(0,0,0,.05);
        --shadow-lg: 0 20px 60px rgba(0,0,0,.15), 0 8px 24px rgba(0,0,0,.08);
    }

    /* ── Page Header ─────────────────────────────────────── */
    .jnl-page-header {
        background: linear-gradient(135deg, #1e40af 0%, #2563eb 60%, #3b82f6 100%);
        padding: 20px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .jnl-page-header .header-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .jnl-page-header .header-icon {
        width: 42px;
        height: 42px;
        background: rgba(255,255,255,.18);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #fff;
        flex-shrink: 0;
    }
    .jnl-page-header h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        line-height: 1.2;
    }
    .jnl-page-header h2 small {
        display: block;
        font-size: 12px;
        font-weight: 400;
        color: rgba(255,255,255,.7);
        margin-top: 2px;
    }
    .jnl-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .btn-hdr {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all .2s;
        white-space: nowrap;
        line-height: 1;
    }
    .btn-hdr-credit  { background: #fff; color: #16a34a; }
    .btn-hdr-credit:hover  { background: #f0fdf4; box-shadow: 0 4px 12px rgba(22,163,74,.3); }
    .btn-hdr-debit   { background: rgba(255,255,255,.15); color: #fff; border: 1.5px solid rgba(255,255,255,.4); }
    .btn-hdr-debit:hover   { background: rgba(255,255,255,.25); }

    /* Opening Balances dropdown */
    .opening-dropdown { position: relative; }
    .btn-hdr-opening {
        background: rgba(255,255,255,.1);
        color: #fff;
        border: 1.5px solid rgba(255,255,255,.3);
    }
    .btn-hdr-opening:hover { background: rgba(255,255,255,.22); }
    .btn-hdr-opening .caret { font-size: 10px; transition: transform .2s; }
    .opening-dropdown.open .btn-hdr-opening .caret { transform: rotate(180deg); }
    .opening-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 6px);
        background: #fff;
        border-radius: 10px;
        box-shadow: var(--shadow-lg);
        padding: 6px;
        min-width: 200px;
        z-index: 100;
        display: none;
    }
    .opening-dropdown.open .opening-menu { display: block; }
    .opening-menu a, .opening-menu button {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 9px 12px;
        border-radius: 7px;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-primary);
        background: none;
        border: none;
        cursor: pointer;
        text-align: left;
        transition: background .15s;
    }
    .opening-menu button:hover { background: var(--surface); }
    .opening-menu button i { width: 16px; text-align: center; }
    .opening-menu .om-rcv i { color: #0369a1; }
    .opening-menu .om-pay i { color: #d97706; }
    .opening-menu .om-ast i { color: #059669; }

    /* ── Stats strip ─────────────────────────────────────── */
    .jnl-stats-strip {
        display: flex;
        gap: 1px;
        background: var(--border);
        border-bottom: 1px solid var(--border);
    }
    .jnl-stat-card {
        flex: 1;
        background: #fff;
        padding: 14px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .jnl-stat-card .stat-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }
    .jnl-stat-card .stat-label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; }
    .jnl-stat-card .stat-value { font-size: 17px; font-weight: 700; color: var(--text-primary); font-family: monospace; }
    .stat-total .stat-icon  { background: #eff6ff; color: var(--primary); }
    .stat-debit  .stat-icon { background: #eff6ff; color: #1d4ed8; }
    .stat-credit .stat-icon { background: #f0fdf4; color: #16a34a; }
    .stat-balanced .stat-icon { background: #f0fdf4; color: #059669; }

    /* ── Filter Panel ────────────────────────────────────── */
    .filter-container { margin: 16px; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
    .filter-header { background: #fff; padding: 14px 18px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid var(--primary); transition: background .2s; }
    .filter-header:hover { background: var(--surface); }
    .filter-header h3 { margin: 0; font-size: 14px; font-weight: 600; color: var(--text-primary); }
    .filter-header .filter-badge { background: var(--primary-light); color: var(--primary-dark); font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 20px; margin-left: 8px; }
    .toggle-icon { transition: transform .3s; color: var(--text-muted); }
    .filter-header.active .toggle-icon { transform: rotate(180deg); }
    .filter-content { background: var(--surface); padding: 0; max-height: 0; overflow: hidden; transition: max-height .35s ease-out, padding .3s ease-out; }
    .filter-content.active { padding: 18px; max-height: 500px; }
    .filter-row { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 14px; }
    .filter-group { flex: 1; min-width: 190px; }
    .filter-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 12px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .4px; }
    .filter-group select, .filter-group input {
        width: 100%; padding: 9px 12px; border: 1.5px solid var(--border); border-radius: 7px;
        font-size: 13px; transition: border-color .2s; background: #fff; color: var(--text-primary);
    }
    .filter-group select:focus, .filter-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
    .filter-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 4px; }
    .filter-actions button { padding: 9px 18px; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: 6px; }
    .apply-btn { background: var(--primary); color: #fff; border: none; }
    .apply-btn:hover { background: var(--primary-dark); box-shadow: 0 4px 12px rgba(37,99,235,.3); }
    .reset-btn  { background: #fff; color: var(--text-secondary); border: 1.5px solid var(--border); }
    .reset-btn:hover { background: var(--surface); }

    /* ── Select2 fix ─────────────────────────────────────── */
    .select2-container .select2-selection--single { height: 42px; border: 1.5px solid var(--border) !important; border-radius: 7px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px; color: var(--text-primary); }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; top: 1px; right: 6px; width: 20px; }
    .select2-container--default.select2-container--focus .select2-selection--single { border-color: var(--primary) !important; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
    .modal-overlay .select2-dropdown { z-index: 1060 !important; }
    .modal-overlay .select2-container { z-index: 1055 !important; }

    /* ── Pagination ──────────────────────────────────────── */
    span [aria-current="page"] span { background-color: var(--primary) !important; color: #fff; border-color: var(--primary); }

    /* ── Print button ────────────────────────────────────── */
    .btn-print {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
        cursor: pointer; border: 1.5px solid var(--border);
        background: #fff; color: var(--text-secondary); transition: all .2s;
    }
    .btn-print:hover { background: var(--surface); border-color: #94a3b8; color: var(--text-primary); }

    /* ── Table wrapper ───────────────────────────────────── */
    .table-wrapper { padding: 0 16px 16px; }
    .table-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 14px 0 10px; }
    .table-toolbar .toolbar-info { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .table-scroll { overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--border); }

    /* ── Table ───────────────────────────────────────────── */
    .journal-table { width: 100%; border-collapse: collapse; }
    .journal-table thead tr { background: linear-gradient(to right, #f1f5f9, #f8fafc); }
    .journal-table thead th {
        padding: 11px 14px; text-align: left; font-weight: 600; font-size: 11.5px;
        color: var(--text-secondary); text-transform: uppercase; letter-spacing: .6px;
        border-bottom: 2px solid var(--border); white-space: nowrap;
    }
    .journal-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .12s; }
    .journal-table tbody tr:last-child { border-bottom: none; }
    .journal-table tbody tr:hover { background: #f8fafc; }
    .journal-table tbody td { padding: 11px 14px; font-size: 13.5px; color: var(--text-primary); vertical-align: middle; }
    .row-num { font-size: 12px; color: var(--text-muted); font-weight: 600; width: 36px; text-align: center; }

    /* ── Source badge ────────────────────────────────────── */
    .source-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; text-transform: capitalize; white-space: nowrap; }
    .source-manual              { background: #e0e7ff; color: #3730a3; }
    .source-sale                { background: #d1fae5; color: #065f46; }
    .source-purchase            { background: #fef3c7; color: #92400e; }
    .source-expense             { background: #fee2e2; color: #b91c1c; }
    .source-salary              { background: #dbeafe; color: #1d4ed8; }
    .source-opening_receivable  { background: #e0f2fe; color: #0369a1; }
    .source-opening_payable     { background: #fef3c7; color: #92400e; }
    .source-opening_asset       { background: #d1fae5; color: #065f46; }

    /* ── Balance badge ───────────────────────────────────── */
    .balanced-yes { background: #dcfce7; color: #15803d; display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }
    .balanced-no  { background: #fee2e2; color: #b91c1c; display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }

    /* ── Amount cells ────────────────────────────────────── */
    .amount-debit  { font-family: 'Courier New', monospace; font-weight: 700; color: #1d4ed8; font-size: 13px; }
    .amount-credit { font-family: 'Courier New', monospace; font-weight: 700; color: #15803d; font-size: 13px; }

    /* ── Action buttons ──────────────────────────────────── */
    .action-cell { display: flex; align-items: center; justify-content: center; gap: 6px; }
    .btn-action { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 7px; border: none; cursor: pointer; font-size: 12px; transition: all .2s; }
    .btn-edit   { background: #eff6ff; color: #2563eb; }
    .btn-edit:hover { background: #dbeafe; transform: translateY(-1px); }
    .btn-delete { background: #fff5f5; color: #dc2626; }
    .btn-delete:hover { background: #fee2e2; transform: translateY(-1px); }
    .btn-view   { background: #f0fdf4; color: #16a34a; }
    .btn-view:hover { background: #dcfce7; transform: translateY(-1px); }
    .btn-voucher { background: #faf5ff; color: #7c3aed; text-decoration: none; }
    .btn-voucher:hover { background: #ede9fe; transform: translateY(-1px); }
    .btn-voucher-party { background: #7c3aed; color: #fff !important; text-decoration: none; }
    .btn-voucher-party:hover { background: #6d28d9; transform: translateY(-1px); }

    /* ── Modal ───────────────────────────────────────────── */
    .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: flex; align-items: center; justify-content: center; z-index: 1040; opacity: 0; pointer-events: none; transition: opacity .25s; backdrop-filter: blur(2px); }
    .modal-overlay.show { opacity: 1; pointer-events: all; }
    .modal-box { background: #fff; border-radius: 14px; width: 100%; max-width: 900px; max-height: 96vh; overflow: visible; display: flex; flex-direction: column; box-shadow: var(--shadow-lg); transform: translateY(22px) scale(.98); transition: transform .28s cubic-bezier(.34,1.56,.64,1); }
    .modal-body { overflow-y: auto; flex: 1; max-height: calc(96vh - 140px); padding: 22px 24px; }
    .modal-overlay.show .modal-box { transform: translateY(0) scale(1); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; border-bottom: 1px solid rgba(255,255,255,.15); border-radius: 14px 14px 0 0; position: sticky; top: 0; z-index: 10; }
    .modal-header h4 { margin: 0; color: #fff; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
    .modal-header h4 small { font-size: 12px; font-weight: 400; opacity: .75; }
    .modal-close { background: rgba(255,255,255,.18); border: none; color: #fff; width: 30px; height: 30px; border-radius: 7px; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; transition: background .2s; }
    .modal-close:hover { background: rgba(255,255,255,.32); }
    .modal-footer { padding: 14px 24px; display: flex; justify-content: flex-end; gap: 10px; background: #fff; border-top: 1px solid var(--border); border-radius: 0 0 14px 14px; }
    .btn-modal-cancel {
        padding: 9px 18px; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer;
        background: var(--surface); color: var(--text-secondary); border: 1.5px solid var(--border); transition: all .2s;
    }
    .btn-modal-cancel:hover { background: var(--border); }
    .btn-modal-save {
        padding: 9px 20px; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer;
        color: #fff; border: none; display: inline-flex; align-items: center; gap: 6px; transition: all .2s;
    }
    .btn-modal-save:hover { filter: brightness(1.1); box-shadow: 0 4px 14px rgba(0,0,0,.2); }

    /* ── Form ────────────────────────────────────────────── */
    .form-row { display: flex; flex-wrap: wrap; gap: 14px; }
    .form-group { margin-bottom: 14px; }
    .form-group.half { flex: 1; min-width: 200px; }
    .form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 5px; text-transform: uppercase; letter-spacing: .4px; }
    .form-group label span.req { color: #ef4444; margin-left: 2px; }
    .form-group .form-control { width: 100%; padding: 9px 12px; border: 1.5px solid var(--border); border-radius: 7px; font-size: 13.5px; color: var(--text-primary); transition: border-color .2s, box-shadow .2s; box-sizing: border-box; background: #fff; }
    .form-group .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
    .form-group .form-control.is-invalid { border-color: #ef4444; }
    .invalid-feedback { font-size: 11.5px; color: #ef4444; margin-top: 4px; display: none; }

    /* ── Accounting flow indicator ───────────────────────── */
    .flow-indicator { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 12.5px; font-weight: 500; }
    .flow-indicator .fi-tag { padding: 3px 10px; border-radius: 5px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; }
    .flow-indicator .fi-arrow { color: var(--text-muted); font-size: 11px; }

    /* ── Items section ───────────────────────────────────── */
    .items-section { margin-top: 8px; }
    .items-section .section-title { font-size: 13px; font-weight: 700; border-bottom: 2px solid var(--primary-light); padding-bottom: 8px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; color: #1e40af; }
    .items-table { width: 100%; border-collapse: collapse; }
    .items-table thead th { background: #f1f5f9; padding: 8px 10px; font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .5px; border-bottom: 2px solid var(--border); }
    .items-table tbody td { padding: 6px 6px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .items-table .item-input { width: 100%; padding: 7px 10px; border: 1.5px solid var(--border); border-radius: 6px; font-size: 13px; box-sizing: border-box; transition: border-color .2s; }
    .items-table .item-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(37,99,235,.1); }
    .items-table .item-input.is-invalid { border-color: #ef4444; }
    .btn-remove-row { background: #fff5f5; border: none; color: #dc2626; width: 27px; height: 27px; border-radius: 6px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; transition: background .2s; }
    .btn-remove-row:hover { background: #fee2e2; }
    .btn-add-row { background: #eff6ff; border: none; color: var(--primary); padding: 6px 13px; border-radius: 7px; font-size: 12px; font-weight: 700; cursor: pointer; transition: background .2s; display: inline-flex; align-items: center; gap: 5px; }
    .btn-add-row:hover { background: var(--primary-light); }

    /* ── Balance bar ─────────────────────────────────────── */
    .balance-bar { display: flex; gap: 20px; align-items: center; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px 16px; margin-top: 12px; font-size: 13px; }
    .balance-bar .bal-item { display: flex; flex-direction: column; gap: 2px; }
    .balance-bar .bal-label { font-size: 10.5px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; }
    .balance-bar .bal-value { font-size: 15px; font-weight: 700; font-family: monospace; }
    .balance-bar .bal-debit  { color: #1d4ed8; }
    .balance-bar .bal-credit { color: #16a34a; }
    .balance-bar .bal-diff.ok   { color: #16a34a; }
    .balance-bar .bal-diff.warn { color: #ef4444; }
    .balance-bar .bal-spacer { flex: 1; }

    /* ── Preview block ───────────────────────────────────── */
    .preview-block { border-radius: 8px; padding: 12px 14px; margin-top: 10px; font-size: 13px; }
    .preview-block strong { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-size: 12.5px; }
    .preview-table { width: 100%; border-collapse: collapse; }
    .preview-table thead tr th { padding: 5px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
    .preview-table tbody td { padding: 5px 10px; border-bottom: 1px solid rgba(0,0,0,.05); }
    .preview-table tfoot td { padding: 5px 10px; font-weight: 700; border-top: 2px solid rgba(0,0,0,.1); }

    /* ── View modal (read-only) ──────────────────────────── */
    .view-items-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .view-items-table th { background: #f1f5f9; padding: 9px 12px; font-size: 11.5px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; border-bottom: 2px solid var(--border); }
    .view-items-table td { padding: 9px 12px; font-size: 13px; color: var(--text-primary); border-bottom: 1px solid #f1f5f9; }
    .view-items-table tfoot td { background: var(--surface); font-weight: 700; }
    .code-chip { font-family: 'Courier New', monospace; background: #f1f5f9; border: 1px solid var(--border); border-radius: 5px; padding: 2px 8px; font-size: 12px; color: var(--text-secondary); }

    /* ── Empty state ─────────────────────────────────────── */
    .empty-state { text-align: center; padding: 56px 24px; color: var(--text-muted); }
    .empty-state .empty-icon { width: 64px; height: 64px; background: var(--surface); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 16px; border: 1px solid var(--border); }
    .empty-state h4 { font-size: 16px; font-weight: 600; color: var(--text-secondary); margin: 0 0 6px; }
    .empty-state p { font-size: 13.5px; margin: 0; }

    /* ── Flash alert ─────────────────────────────────────── */
    .flash-alert { display: flex; align-items: center; gap: 10px; padding: 11px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 500; margin: 14px 16px 0; }
    .flash-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }

    /* ── Pagination ──────────────────────────────────────── */
    .pagination-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0 4px; font-size: 13px; color: var(--text-muted); }

    /* ── Grid body ───────────────────────────────────────── */
    .grid-body { display: grid; gap: 20px; grid-template-columns: repeat(2, 1fr); }

    /* ── Responsive ──────────────────────────────────────── */
    @media (max-width: 900px) {
        .jnl-stats-strip { flex-wrap: wrap; }
        .jnl-stat-card { min-width: 140px; }
    }
    @media (max-width: 768px) {
        .jnl-page-header { padding: 16px; }
        .jnl-page-header h2 { font-size: 17px; }
        .journal-table thead { display: none; }
        .journal-table tbody tr { display: block; margin-bottom: 12px; border: 1px solid var(--border); border-radius: 10px; }
        .journal-table tbody td { display: flex; justify-content: space-between; align-items: center; padding: 9px 14px; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody td::before { content: attr(data-label); font-weight: 600; color: var(--text-secondary); font-size: 11px; text-transform: uppercase; }
        .modal-box { max-width: 98vw; border-radius: 10px; }
        .table-wrapper { padding: 0 10px 10px; }
        .filter-container { margin: 10px; }
    }
    @media (max-width: 560px) {
        .jnl-header-actions { width: 100%; }
        .btn-hdr { flex: 1; justify-content: center; font-size: 12px; padding: 8px 10px; }
        .jnl-stat-card { padding: 11px 14px; }
        .jnl-stat-card .stat-value { font-size: 15px; }
    }

    /* ── Print styles ────────────────────────────────────── */
    @media print {
        /* Hide everything except the journal table area */
        body * { visibility: hidden; }
        #printArea, #printArea * { visibility: visible; }
        #printArea { position: absolute; inset: 0; }

        /* Hide non-essential UI */
        .no-print,
        .jnl-page-header,
        .jnl-stats-strip,
        .filter-container,
        .table-toolbar,
        .pagination-row,
        .modal-overlay,
        nav, header, aside, footer,
        .states-table-header { display: none !important; }

        /* Reset page chrome */
        .states-table { box-shadow: none !important; border: none !important; }
        .table-scroll { border: none !important; overflow: visible !important; }
        .table-wrapper { padding: 0 !important; }

        /* Print header */
        #printArea::before {
            content: "Journal Entries Report";
            display: block;
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        #printArea .print-meta {
            display: block !important;
            font-size: 11px;
            color: #64748b;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }

        /* Table print styles */
        .journal-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .journal-table thead tr { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .journal-table thead th { padding: 7px 8px; border-bottom: 2px solid #cbd5e1; color: #475569; font-size: 10px; }
        .journal-table tbody td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even) td { background: #fafafa; }

        /* Keep badge colors */
        .source-badge, .balanced-yes, .balanced-no { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .amount-debit { color: #1d4ed8 !important; }
        .amount-credit { color: #15803d !important; }

        /* Print summary totals row */
        #printTotalsRow { display: table-row !important; }

        @page { margin: 15mm 12mm; size: A4 landscape; }
    }

    /* Hidden print metadata (shown only during print) */
    .print-meta { display: none; }
    #printTotalsRow { display: none; }
</style>
