@extends('layout.app')
@section('meta-information')
    <title>Ticket Purchase</title>
@endsection
@section('css')
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
    :root {
      --bg:#f4f6fb; --bg2:#ffffff; --bg3:#f0f2f8;
      --border:#e4e8f0; --border2:#d0d6e8;
      --text:#1a2035; --text2:#5a6480; --text3:#9aa3bc;
      --accent:#2563eb; --accent2:#1d4ed8; --accent-light:#eff6ff;
      --green:#16a34a; --green-light:#f0fdf4;
      --amber:#d97706; --red:#dc2626;
      --shadow:0 1px 4px rgba(30,50,100,.07);
      --shadow2:0 4px 16px rgba(30,50,100,.10);
    }
    body { font-family:'DM Sans',sans-serif; }

    .tv-page-head{background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px;margin-bottom:0;box-shadow:var(--shadow);}
    .tv-page-logo{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#5b21b6,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
    .tv-page-title{font-size:18px;font-weight:700;color:var(--text);}
    .tv-page-sub{font-size:12px;color:var(--text2);margin-top:1px;}
    .tv-page-actions{margin-left:auto;display:flex;gap:8px;}
    .erp-btn{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:500;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;transition:all .15s;display:inline-flex;align-items:center;gap:5px;text-decoration:none;}
    .btn-ghost{background:var(--bg3);color:var(--text2);border:1px solid var(--border);}
    .btn-ghost:hover{border-color:var(--border2);color:var(--text);}
    .btn-primary{background:var(--accent);color:#fff;}
    .btn-primary:hover{background:var(--accent2);}
    .btn-green-s{background:#16a34a;color:#fff;}
    .btn-green-s:hover{background:#15803d;}
    .btn-danger{background:#dc2626;color:#fff;}
    .btn-warning{background:#f59e0b;color:#fff;}

    /* Tab bar */
    .ops-tab-bar{background:linear-gradient(135deg,#5b21b6 0%,#7c3aed 100%);border-radius:0;padding:0 8px;display:flex;gap:2px;overflow-x:auto;box-shadow:0 4px 14px rgba(124,58,237,.18);margin-top:14px;}
    .op-tab{padding:13px 18px;color:rgba(255,255,255,.78);font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;border-bottom:3px solid transparent;transition:all .15s;white-space:nowrap;user-select:none;}
    .op-tab:hover{color:#fff;}
    .op-tab.active{color:#fff;border-bottom-color:#fbbf24;background:rgba(255,255,255,.10);}
    .tab-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}
    .dot-sale{background:#34d399;} .dot-refund{background:#f87171;} .dot-reissue{background:#60a5fa;} .dot-void{background:#fb923c;} .dot-emd{background:#c084fc;}
    .tab-content{display:none;padding:20px;background:#fff;border:1px solid var(--border);border-top:none;border-radius:0 0 14px 14px;box-shadow:var(--shadow2);}
    .tab-content.active{display:block;}

    /* Sections */
    .ds-section{background:#fff;border:1px solid var(--border);border-radius:12px;margin-bottom:14px;overflow:hidden;box-shadow:var(--shadow);}
    .ds-section-h{padding:13px 16px;background:#f8fafc;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
    .ds-sec-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
    .ds-sec-title{font-size:13.5px;font-weight:700;color:var(--text);}
    .ds-sec-sub{font-size:11.5px;color:var(--text2);}
    .ds-section-h .right{margin-left:auto;}
    .ds-head-tools{display:flex;align-items:center;gap:12px;}
    .ds-head-currency{position:relative;width:150px;} .ds-head-currency .ds-field-lbl{background:#f8fafc;}
    .ds-section-body{padding:16px;}
    .ds-add-pax{padding:8px 14px;border-radius:9px;background:linear-gradient(120deg,#6d28d9,#7c3aed);color:#fff;border:0;font-size:12px;font-weight:600;cursor:pointer;box-shadow:0 6px 14px rgba(109,40,217,.22);transition:all .15s;}
    .ds-add-pax:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(109,40,217,.3);}
    .ds-legs-hd{display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:10.5px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#6d28d9;}
    .ds-legs-hd i.ic{width:24px;height:24px;border-radius:7px;background:#ede9fe;display:inline-flex;align-items:center;justify-content:center;font-size:11px;color:#6d28d9;}
    .ds-legs-hd::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,#ddd6fe,transparent);}
    .ds-legs-hd small{font-weight:500;letter-spacing:0;text-transform:none;color:#94a3b8;}
    .ds-legs-hd .ds-add-pax{background:#fff;color:#6d28d9;border:1px solid #ddd6fe;box-shadow:none;padding:6px 12px;}
    .ds-legs-hd .ds-add-pax:hover{background:#f5f3ff;transform:none;}

    /* Grid */
    .ds-row{display:grid;gap:12px 14px;margin-bottom:12px;}
    .ds-row:last-child{margin-bottom:0;}
    .ds-row.cols-2{grid-template-columns:repeat(2,1fr);}
    .ds-row.cols-3{grid-template-columns:repeat(3,1fr);}
    .ds-row.cols-4{grid-template-columns:repeat(4,1fr);}
    .ds-row.cols-5{grid-template-columns:repeat(5,1fr);}
    .ds-row.cols-6{grid-template-columns:repeat(6,1fr);}
    .span-2{grid-column:span 2;}
    @media(max-width:1024px){.ds-row.cols-4,.ds-row.cols-5,.ds-row.cols-6{grid-template-columns:repeat(2,1fr);}.ds-row.cols-3{grid-template-columns:1fr 1fr;}}
    @media(max-width:640px){.ds-row.cols-2,.ds-row.cols-3,.ds-row.cols-4,.ds-row.cols-5,.ds-row.cols-6{grid-template-columns:1fr;}}

    /* Fields */
    /* Notched field titles (owner, 2026-09-21): the title sits ON the input's top
       border, like the Add Flight modal — no heading row above the box. */
    div:has(> .ds-field-lbl){position:relative;}
    .ds-field-lbl{position:absolute;top:-7px;left:10px;z-index:3;display:inline-flex;align-items:center;gap:4px;max-width:calc(100% - 20px);padding:0 5px;background:#fff;border-radius:4px;line-height:14px;font-size:10px;color:#64748b;font-weight:700;letter-spacing:.5px;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;pointer-events:none;}
    .ds-field-lbl .ds-flag{margin-right:1px;}
    .ds-row{padding-top:5px;}
    div:has(> .ds-field-lbl):focus-within > .ds-field-lbl{color:#6d28d9;}
    .ds-field-lbl .req{color:#dc2626;}
    .ds-field-lbl span:not(.req){text-transform:none;letter-spacing:0;font-weight:500;}
    .ds-field-lbl .req{color:#dc2626;margin-left:2px;}
    .ds-field-input,.ds-field-select{width:100%;height:42px;padding:9px 13px;border:1px solid #d2d8e7;border-radius:10px;font-size:13px;background:#fff;color:var(--text);outline:none;transition:border-color .12s;font-family:'DM Sans',sans-serif;}
    .ds-field-input:focus,.ds-field-select:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    .ds-field-input.highlight{border-color:#7c3aed;background:#faf5ff;}
    .ds-field-input.sale-price{border-color:#6d28d9;background:#faf5ff;font-weight:600;color:#5b21b6;}
    .ds-field-input[readonly]{background:#f8fafc;color:var(--text2);}
    .ds-field-input.success{border-color:#16a34a;background:#f0fdf4;}
    .ds-field-input.warning{border-color:#f59e0b;background:#fffbeb;}
    .ds-field-input.danger{border-color:#dc2626;background:#fef2f2;}
    .ds-field-input.ds-invalid,.ds-field-select.ds-invalid{border-color:#dc2626!important;background:#fef2f2!important;box-shadow:0 0 0 2px rgba(220,38,38,.08)!important;}
    .select2-container.ds-invalid .select2-selection--single{border-color:#dc2626!important;background:#fef2f2!important;box-shadow:0 0 0 2px rgba(220,38,38,.08)!important;}
    .ds-validation-error{display:block;width:100%;margin:4px 2px 0;color:#dc2626;font-size:10px;font-weight:600;line-height:1.35;}
    .ds-form-error{margin:0 0 8px;padding:8px 10px;border:1px solid #fecaca;border-radius:8px;background:#fef2f2;color:#b91c1c;font-size:10.5px;font-weight:600;line-height:1.4;}

    /* Select2 */
    /* merged 2026-09-21: main's width/ellipsis fixes + this branch's 42px look */
    .tab-content .select2-container{width:100%!important;max-width:100%;min-width:0;display:block;}
    .ds-select-action{display:flex;gap:6px;align-items:center;min-width:0;}
    .ds-select-action .select2-container{width:auto!important;flex:1 1 0;min-width:0;}
    .select2-container--default .select2-selection--single{height:42px;border:1px solid #d2d8e7;border-radius:10px;display:flex;align-items:center;font-family:'DM Sans',sans-serif;font-size:13px;}
    .select2-container--default .select2-selection--single .select2-selection__rendered{width:100%;line-height:40px;padding-left:12px;padding-right:30px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .select2-container--default .select2-selection--single .select2-selection__arrow{height:40px;}
    .select2-container--default.select2-container--focus .select2-selection--single,.select2-container--default.select2-container--open .select2-selection--single{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}

    /* Passenger cards */
    .ds-pax-card{position:relative;background:#fff;border:1px solid #e4e1f7;border-radius:14px;padding:16px 18px 16px 22px;margin-bottom:12px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
    .ds-pax-card::before{content:'';position:absolute;left:0;top:16px;bottom:16px;width:4px;border-radius:0 4px 4px 0;background:linear-gradient(180deg,#7c3aed,#a78bfa);}
    .ds-pax-h{display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #f1f0fa;}
    .ds-pax-badge{display:inline-flex;align-items:center;gap:8px;padding:6px 12px 6px 6px;border-radius:999px;background:#f5f3ff;color:#4c1d95;font-size:12px;font-weight:700;letter-spacing:.3px;border:1px solid #e9e5fb;}
    .ds-flag{width:18px;height:13px;border-radius:2px;object-fit:cover;box-shadow:0 0 0 1px rgba(15,23,42,.12);vertical-align:-2px;margin-right:4px;}
    .ds-pax-legs.is-chain .ds-legs-hd{display:none;}
    .ds-pax-legs.is-chain{border-top:0;margin-top:0;padding-top:0;margin-bottom:2px;}
    .ds-pax-legs.is-chain [data-leg-index] button{display:none;}
    .ds-status-wrap{position:relative;width:190px;margin-left:8px;}
    .ds-status-wrap .select2-container--default .select2-selection--single{background:#fff;border-color:#c4b5fd;}
    .ds-pax-card[data-status="booked"]{border-color:#bae6fd;} .ds-pax-card[data-status="booked"]::before{background:linear-gradient(180deg,#0284c7,#38bdf8);}
    .ds-pax-card[data-status="draft"], .ds-pax-card[data-status="pending"]{border-style:dashed;} .ds-pax-card[data-status="draft"]::before, .ds-pax-card[data-status="pending"]::before{background:#cbd5e1;}
    .ds-pax-card[data-status="cancelled"]::before{background:#ef4444;} .ds-pax-card[data-status="cancelled"] .ds-row{opacity:.55;}
    .ds-book-box{margin:0 0 12px;padding:14px 14px 4px;border-radius:12px;background:#f0f9ff;border:1px solid #bae6fd;}
    .ds-book-hd{font-size:12px;color:#0369a1;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
    .ds-book-box .ds-field-lbl{background:#f0f9ff;}
    .ds-book-ch{display:flex;align-items:center;gap:16px;height:42px;padding:0 13px;border:1px solid #bae6fd;border-radius:10px;background:#fff;font-size:12.5px;color:#0f172a;}
    .ds-book-hint{display:block;font-size:10px;color:#64748b;margin-top:4px;}
    .ds-book-hd small{margin-left:auto;font-weight:500;color:#0369a1;opacity:.8;} .ds-book-ch label{display:inline-flex;align-items:center;gap:5px;cursor:pointer;} .ds-book-ch input{accent-color:#0284c7;}
    .ds-row.cols-1{grid-template-columns:1fr;}
    .ds-leg-strip{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:-4px 0 14px;padding:9px 14px;border-radius:10px;background:#f5f3ff;border:1px solid #e9e5fb;font-size:13px;color:#312e81;}
    .ds-leg-strip .k{font-weight:800;color:#5b21b6;letter-spacing:.3px;}
    .ds-leg-strip .sec{display:inline-flex;align-items:center;gap:6px;font-weight:700;font-size:14px;color:#1e1b4b;}
    .ds-leg-strip .sec i{color:#a78bfa;font-size:11px;}
    .ds-leg-strip .sec .ds-flag{margin-right:2px;}
    .ds-leg-strip .dot{color:#a5b4fc;}
    .ds-leg-strip .note{margin-left:auto;font-size:11.5px;color:#7c7aa5;}
    .ds-pax-badge .ds-leg-tag{display:inline-flex;align-items:center;gap:5px;color:#5b21b6;font-weight:600;}
    .ds-pax-badge .ds-leg-tag i{font-size:10px;color:#a78bfa;}
    .ds-pax-badge::before{content:attr(data-n);width:24px;height:24px;border-radius:50%;background:linear-gradient(120deg,#6d28d9,#8b5cf6);color:#fff;font-size:11px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(109,40,217,.25);}
    .ds-pax-remove{margin-left:auto;padding:6px 12px;border:1px solid #fecaca;background:#fff;color:#dc2626;font-size:11.5px;font-weight:600;border-radius:8px;cursor:pointer;transition:all .15s;}
    .ds-pax-remove:hover{background:#fee2e2;}
    .ds-add-another{display:block;width:100%;padding:11px;border:2px dashed #cbd5e1;background:transparent;color:var(--text2);font-size:12.5px;font-weight:600;border-radius:9px;cursor:pointer;margin-top:8px;transition:all .15s;font-family:'DM Sans',sans-serif;}
    .ds-add-another:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-light);}

    /* Summary bar */
    .ds-summary-bar{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--border);border:1px solid var(--border);border-radius:11px;overflow:hidden;margin-bottom:14px;}
    .ds-summary-bar.cols-2{grid-template-columns:repeat(2,1fr);}
    .ds-sum-cell{background:#fff;padding:14px 16px;}
    .ds-sum-label{font-size:11px;color:var(--text2);font-weight:600;letter-spacing:.3px;text-transform:uppercase;margin-bottom:4px;}
    .ds-sum-value{font-size:18px;font-weight:700;font-family:'DM Mono',monospace;}
    .ds-sum-value.cost{color:#dc2626;} .ds-sum-value.sale{color:#0d9488;} .ds-sum-value.profit{color:#16a34a;} .ds-sum-value.penalty{color:#d97706;} .ds-sum-value.refund{color:#dc2626;}
    .taka{font-family:inherit;margin-right:2px;}

    /* Due line */
    .ds-due-line{padding:10px 16px;background:#fef2f2;border-top:1px solid #fecaca;display:flex;justify-content:space-between;align-items:center;}
    .ds-due-label{font-size:12px;color:var(--text2);font-weight:600;}
    .ds-due-amount{font-size:15px;font-weight:700;color:#dc2626;font-family:'DM Mono',monospace;}

    /* Action bar */
    .ds-action-bar{display:flex;gap:8px;justify-content:flex-end;padding:6px 0 0;}

    /* Alerts */
    .ds-alert{padding:11px 14px;border-radius:9px;font-size:12.5px;display:flex;align-items:center;gap:9px;margin-bottom:14px;font-weight:500;}
    .ds-alert.danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;}
    .ds-alert.warning{background:#fffbeb;color:#92400e;border:1px solid #fde68a;}
    .ds-alert.info{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;}

    /* Steps */
    .ds-steps{display:flex;gap:6px;margin-bottom:14px;background:#f8fafc;padding:10px;border-radius:10px;border:1px solid var(--border);}
    .ds-step{flex:1;padding:8px 12px;background:#fff;border-radius:7px;font-size:11.5px;font-weight:600;color:var(--text2);border:1px solid var(--border);text-align:center;}
    .ds-step.done{background:#dcfce7;color:#166534;border-color:#bbf7d0;}
    .ds-step.active{background:#dbeafe;color:#1e40af;border-color:#bfdbfe;}

    /* Orig ref */
    .ds-orig-ref{background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:10px 14px;display:flex;flex-wrap:wrap;gap:14px 22px;align-items:center;margin-bottom:14px;font-size:11.5px;color:var(--text2);}
    .ds-orig-ref .ref-val{color:var(--text);font-weight:600;font-family:'DM Mono',monospace;margin-left:4px;}
    .ds-status-tag{padding:3px 10px;border-radius:11px;font-size:10.5px;font-weight:700;letter-spacing:.4px;margin-left:auto;}
    .ds-status-tag.confirm{background:#dcfce7;color:#166534;}
    .ds-status-tag.reissue{background:#dbeafe;color:#1e40af;}
    .ds-status-tag.void{background:#ffedd5;color:#9a3412;}

    /* Calc rows */
    .ds-calc{margin-top:12px;padding-top:12px;border-top:1px dashed var(--border);}
    .ds-calc-row{display:flex;justify-content:space-between;padding:5px 0;font-size:12.5px;color:var(--text2);max-width:360px;margin-left:auto;}
    .ds-calc-row.total{font-weight:700;color:var(--text);font-size:14px;border-top:1px solid var(--border);margin-top:5px;padding-top:8px;}
    .ds-calc-row .sec{color:var(--text3);}

    /* Passport Holder quick-create */
    .ph-create-btn{flex-shrink:0;width:38px;height:42px;border-radius:10px;border:1px solid var(--accent);background:var(--accent-light);color:var(--accent);font-size:18px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;padding:0;line-height:1;}
    .ph-create-btn:hover{background:var(--accent);color:#fff;}

    /* Modal */
    .ph-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;display:none;align-items:center;justify-content:center;}
    .ph-modal-overlay.open{display:flex;}
    /* Select2 inside modals */
    .tk-modal-box .select2-container .select2-selection--single{height:42px;border:1px solid #d2d8e7;border-radius:10px;display:flex;align-items:center;}
    .tk-modal-box .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:40px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding-left:10px;font-size:13px;color:var(--text);}
    .tk-modal-box .select2-container--default .select2-selection--single .select2-selection__arrow{height:40px;}
    .tk-modal-box .select2-container--default.select2-container--focus .select2-selection--single{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light);}
    .tk-modal-box .select2-container--default .select2-selection--single .select2-selection__placeholder{color:#9aa3bc;}
    .ph-modal{background:#fff;border-radius:14px;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,.18);overflow:hidden;animation:phSlideIn .18s ease;}
    @keyframes phSlideIn{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
    .ph-modal-head{padding:16px 20px;background:linear-gradient(135deg,#2563eb,#1d4ed8);display:flex;align-items:center;gap:10px;}
    .ph-modal-title{font-size:14px;font-weight:700;color:#fff;}
    .ph-modal-close{margin-left:auto;background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:6px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;}
    .ph-modal-close:hover{background:rgba(255,255,255,.35);}
    .ph-modal-body{padding:18px 20px;}
    .ph-modal-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;}
    /* Fields are width:100% with padding — border-box keeps them inside their
       column (the route modal's right column spilled past the edge, 2026-10-06). */
    .ph-modal *,.ph-modal *::before,.ph-modal *::after{box-sizing:border-box;}
    .ph-modal .select2-container{width:100%!important;max-width:100%;}
    /* Route quick-create: big and roomy like the red box the owner drew. */
    .ph-modal.tk-modal-box{max-width:min(880px,94vw);}
    .tk-modal-box .ph-modal-head{padding:18px 26px;}
    .tk-modal-box .ph-modal-title{font-size:15px;}
    .tk-modal-box .ph-modal-body{padding:24px 26px 26px;}
    .tk-modal-box .ph-modal-grid{gap:18px 22px;}
    .tk-modal-box .ph-modal-lbl{font-size:11.5px;margin-bottom:6px;}
    .tk-modal-box .ph-modal-input{height:42px;border-color:#d2d8e7;border-radius:10px;}
    .tk-modal-box .ph-modal-foot{padding:16px 26px;}
    /* "+" route modal with Manage Tickets' via airports (tickets/route-via, 2026-10-17): its ticket cards in
       this modal's dress; the body scrolls inside the window when several tickets appear */
    #qcModal .ph-modal-body{max-height:calc(100vh - 170px);overflow:auto;scrollbar-gutter:stable;}   /* the scrollbar's room kept: ticket cards appearing do not shift the rows */
    #qcModal .qc-sub{font-weight:400;color:#8a94a6;}
    #qcModal .tk-lbl{display:block;font-size:11.5px;font-weight:600;color:var(--text2);margin-bottom:6px;letter-spacing:.2px;}
    #qcModal .tk-input{width:100%;height:42px;padding:9px 12px;border:1px solid #d2d8e7;border-radius:10px;font-size:13px;outline:none;}
    #qcModal .tk-input:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    #qcModal .tk-direct-hide{display:none !important;}
    #qcModal .tk-stops:empty{margin-bottom:0;}
    .ph-modal-grid .full{grid-column:1/-1;}
    .ph-modal-lbl{font-size:11px;font-weight:600;color:var(--text2);margin-bottom:5px;display:block;letter-spacing:.2px;}
    .ph-modal-lbl .req{color:#dc2626;}
    .ph-modal-input,.ph-modal-select{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .12s;}
    .ph-modal-input:focus,.ph-modal-select:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light);}
    .ph-modal-foot{padding:14px 20px;background:#f8fafc;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;}
    .ph-modal-err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;padding:8px 12px;font-size:12px;margin-bottom:12px;display:none;}
    
    /* Ticket card, regrouped (owner, 2026-10-04: "restructure, more unique,
       not visually polluted, well aligned"): three titled zones on one
       6-column grid, quieter borders, one accent per card. */
    .ds-pax-card{padding:16px 20px 14px 24px;border-color:#ebe8f7;box-shadow:0 1px 2px rgba(15,23,42,.03),0 6px 18px rgba(79,70,229,.04);}
    .ds-pax-card::before{width:3px;}
    .ds-pax-h{margin-bottom:6px;padding-bottom:12px;}
    .ds-zone{padding:12px 0 2px;}
    .ds-zone + .ds-zone{border-top:1px dashed #ece9f8;}
    .ds-zone-h{display:flex;align-items:center;gap:8px;margin:0 0 12px;font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#8b88a8;}
    .ds-zone-h i{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:7px;background:#f5f3ff;color:#7c3aed;font-size:10px;}
    .ds-zone .ds-row{gap:14px;margin-bottom:10px;}
    .ds-row.cols-6{grid-template-columns:repeat(6,minmax(0,1fr));}
    .ds-row .span-2{grid-column:span 2;}
    .ds-route-btn{border-color:#c4b5fd!important;background:#f5f3ff!important;color:#7c3aed!important;}
    .ds-file{padding-top:9px!important;font-size:12px!important;color:#64748b;}
    .ds-file::file-selector-button{margin-right:8px;padding:3px 10px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc;color:#475569;font-size:11.5px;font-weight:600;cursor:pointer;}
    @media(max-width:1180px){.ds-row.cols-6{grid-template-columns:repeat(3,minmax(0,1fr));}.ds-row.cols-6 .span-2{grid-column:span 3;}}
    @media(max-width:640px){.ds-row.cols-6{grid-template-columns:1fr;}.ds-row.cols-6 .span-2{grid-column:auto;}}
    /* Route legs heading in the zone style. */
    .ds-pax-legs{border-top:1px dashed #ece9f8!important;}
    .ds-legs-hd{font-size:10.5px!important;font-weight:700!important;letter-spacing:.08em!important;text-transform:uppercase!important;color:#8b88a8!important;}
    .ds-legs-hd small{text-transform:none;letter-spacing:0;font-weight:500;color:#a8a6c0;}
    .ds-legs-hd .ic{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:7px;background:#f5f3ff;color:#7c3aed!important;font-size:10px;}

    /* ── Ticket entry v2 (2026-10-04): e-ticket card + summary rail ───── */
    .ds-entry-grid{display:grid;grid-template-columns:minmax(0,1fr) 270px;gap:16px;align-items:start;}
    .ds-entry-main{min-width:0;}
    .ds-rail{position:sticky;top:84px;}
    .ds-rail-card{background:#fff;border:1px solid #e6e9f2;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px rgba(79,70,229,.06);}
    .ds-rail-h{display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#8b88a8;}
    .ds-rail-h i{width:22px;height:22px;border-radius:7px;background:#f5f3ff;color:#7c3aed;display:inline-flex;align-items:center;justify-content:center;font-size:10px;}
    .ds-rail-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:12.5px;color:#64748b;}
    .ds-rail-row b{font-weight:600;color:#334155;font-variant-numeric:tabular-nums;}
    .ds-rail-row b.g{color:#059669;} .ds-rail-row b.r{color:#dc2626;}
    .ds-rail-row.is-strong{color:#334155;font-weight:700;} .ds-rail-row.is-strong b{font-weight:700;color:#1e1b4b;}
    .ds-rail-row.is-strong b.g{color:#059669;} .ds-rail-row.is-strong b.r{color:#dc2626;}
    .ds-rail-sep{border-top:1px dashed #e2e8f0;margin:6px 0;}
    .ds-rail-total{display:flex;justify-content:space-between;align-items:baseline;margin:6px 0;padding:10px 0;border-top:1px dashed #e2e8f0;border-bottom:1px dashed #e2e8f0;font-size:12px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.04em;}
    .ds-rail-total b{font-size:18px;color:#1e1b4b;letter-spacing:0;text-transform:none;}
    .ds-rail-warn{margin:10px 0 12px;display:flex;flex-direction:column;gap:6px;}
    .ds-rail-ok{font-size:11.5px;color:#059669;display:flex;align-items:center;gap:6px;}
    .ds-rail-act{display:flex;flex-direction:column;gap:8px;}
    .ds-post{margin:10px 0 4px;padding-top:10px;border-top:1px dashed #e2e8f0;max-height:42vh;overflow:auto;}
    .ds-post h4{margin:0 0 8px;font-size:10.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#334155;}
    .ds-post ol{margin:0;padding-left:16px;display:flex;flex-direction:column;gap:7px;font-size:12px;color:#334155;line-height:1.35;}
    .ds-post li small{display:block;color:#94a3b8;font-size:10.5px;}
    .ds-post .who{display:block;font-size:10.5px;font-weight:700;color:#6d28d9;text-transform:uppercase;letter-spacing:.03em;}
    .ds-post li{font-size:12px;} .ds-post li b{font-weight:600;}
    .ds-post .r{color:#dc2626;font-weight:600;} .ds-post .g{color:#059669;font-weight:600;} .ds-post .m{color:#94a3b8;}
    .ds-post small.ds-post-warn{color:#b45309;}
    .ds-post ol{gap:10px;list-style:none;padding-left:0;}
    .ds-post li + li{padding-top:8px;border-top:1px dashed #e2e8f0;}
    .ds-post .ds-pl{display:flex;justify-content:space-between;gap:8px;padding:2px 0;cursor:default;}
    .ds-post .ds-pl span{color:#475569;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .ds-post .ds-pl b{font-variant-numeric:tabular-nums;white-space:nowrap;color:#334155;}
    .ds-post .ds-pl b.r{color:#dc2626;} .ds-post .ds-pl b.g{color:#059669;}
    .ds-post .ds-pl-warn{font-size:11px;color:#b45309;margin-top:2px;}
    .ds-post .who{margin-bottom:3px;}
    .ds-post-sum{margin-top:10px;padding-top:8px;border-top:1px solid #f1f5f9;display:grid;grid-template-columns:1fr auto;gap:4px 10px;font-size:12px;color:#64748b;}
    .ds-post-sum b{font-variant-numeric:tabular-nums;color:#334155;} .ds-post-sum b.r{color:#dc2626;} .ds-post-sum b.g{color:#059669;}
    .ds-rail-act .erp-btn{width:100%;justify-content:center;}
    .ds-card-warn{display:flex;flex-wrap:wrap;gap:6px;margin-top:2px;}
    .ds-card-warn:empty{display:none;}
    .ds-chk{display:inline-flex;align-items:flex-start;gap:6px;padding:5px 9px;border-radius:8px;font-size:11.5px;line-height:1.35;}
    .ds-chk i{font-size:10px;margin-top:2px;}
    .ds-chk.bad{background:#fef2f2;color:#b91c1c;border:1px solid #fee2e2;}
    .ds-chk.warn{background:#fffbeb;color:#b45309;border:1px solid #fef3c7;}
    .ds-chk.info{background:#eef2ff;color:#4338ca;border:1px solid #e0e7ff;}
    .ds-net{font-weight:700!important;color:#1e1b4b!important;background:#fafaff!important;}
    .ds-upper{text-transform:uppercase;}
    @media(max-width:1180px){.ds-entry-grid{grid-template-columns:1fr;}.ds-rail{position:static;}}
    /* A card narrower than ~860px (rail beside it) lays its zones out in 3 roomy columns. */
    #passengerContainer .ds-pax-card{container-type:inline-size;}
    @container (max-width: 860px){.ds-row.cols-6,.ds-row.cols-5{grid-template-columns:repeat(3,minmax(0,1fr));grid-auto-flow:row dense;}.ds-row.cols-6 .span-2{grid-column:span 2;}}
    /* Compact ticket card (owner, 2026-10-04: "fantastic — just make the card
       take this much space"): 36px fields, tighter gaps. Ticket cards only. */
    #passengerContainer .ds-pax-card{padding:12px 16px 10px 20px;}
    #passengerContainer .ds-pax-h{margin-bottom:2px;padding-bottom:8px;}
    #passengerContainer .ds-zone{padding:8px 0 0;}
    #passengerContainer .ds-zone-h{margin:0 0 9px;font-size:10px;}
    #passengerContainer .ds-zone-h i{width:19px;height:19px;font-size:9px;}
    #passengerContainer .ds-zone .ds-row{gap:10px;margin-bottom:9px;}
    #passengerContainer .ds-field-input,#passengerContainer .ds-field-select{height:36px;padding:6px 11px;font-size:12.5px;border-radius:8px;}
    #passengerContainer .ds-file{padding-top:6px!important;}
    #passengerContainer .ds-field-lbl{font-size:9.5px;top:-6px;}
    #passengerContainer .select2-container--default .select2-selection--single{height:36px;border-radius:8px;font-size:12.5px;}
    #passengerContainer .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:34px;}
    #passengerContainer .select2-container--default .select2-selection--single .select2-selection__arrow{height:34px;}
    #passengerContainer .ph-create-btn{width:36px;height:36px;border-radius:8px;}
    #passengerContainer .ds-leg-strip{margin:0 0 8px;padding:6px 12px;}
    #passengerContainer .ds-pax-legs{margin-top:8px!important;padding-top:8px!important;}
    #passengerContainer .ds-book-box{padding:10px 12px 2px;}
    #passengerContainer .ds-chk{padding:4px 8px;font-size:11px;}
    .ds-rail-card{padding:12px 14px;} .ds-rail-row{padding:4px 0;font-size:12px;}
    /* Even more compact (owner, 2026-10-04): 32px fields, zone titles inline. */
    #passengerContainer .ds-pax-card{padding:10px 14px 8px 18px;margin-bottom:10px;}
    #passengerContainer .ds-pax-h{padding-bottom:6px;}
    #passengerContainer .ds-zone{padding:6px 0 0;}
    #passengerContainer .ds-zone-h{margin:0 0 7px;}
    #passengerContainer .ds-zone .ds-row{gap:8px;margin-bottom:8px;}
    #passengerContainer .ds-field-input,#passengerContainer .ds-field-select{height:32px;padding:4px 10px;font-size:12px;border-radius:7px;}
    #passengerContainer .ds-field-lbl{font-size:9px;top:-6px;}
    #passengerContainer .select2-container--default .select2-selection--single{height:32px;border-radius:7px;font-size:12px;}
    #passengerContainer .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:30px;padding-left:10px;}
    #passengerContainer .select2-container--default .select2-selection--single .select2-selection__arrow{height:30px;}
    #passengerContainer .ph-create-btn{width:32px;height:32px;border-radius:7px;font-size:15px;}
    #passengerContainer .ds-file{padding-top:4px!important;}
    #passengerContainer .ds-file::file-selector-button{padding:2px 8px;}
    #passengerContainer .ds-pax-badge{padding:4px 10px 4px 4px;font-size:11.5px;}
    #passengerContainer .ds-pax-badge::before{width:20px;height:20px;font-size:10px;}
    #passengerContainer .ds-status-wrap .select2-container--default .select2-selection--single{height:32px;}
    /* Zone title runs into a line across the card (owner, 2026-10-04: "like this"); no separate border. */
    #passengerContainer .ds-zone + .ds-zone{border-top:0;}
    #passengerContainer .ds-zone-h::after{content:'';flex:1;height:0;margin-left:4px;border-top:2px dotted #c4b5fd;}
    /* Each zone its own tinted panel (owner, 2026-10-07: "so I can understand these are different parts"). */
    #passengerContainer .ds-zone{--z:#7c3aed;--zb:#faf8ff;--zl:#d9cefb;background:var(--zb);border:1.5px solid var(--zl);border-left:4px solid var(--z);border-radius:10px;padding:8px 12px 2px!important;margin-top:12px;box-shadow:0 1px 2px rgba(15,23,42,.06),0 4px 12px rgba(15,23,42,.07);}
    #passengerContainer .ds-zone + .ds-zone{border-top:1.5px solid var(--zl);}
    #passengerContainer .ds-zone:has(> .ds-zone-h .fa-ticket-alt){--z:#2563eb;--zb:#f7faff;--zl:#c5d7f7;}
    #passengerContainer .ds-zone:has(> .ds-zone-h .fa-plane-departure){--z:#0891b2;--zb:#f5fcfd;--zl:#b8e2ea;}
    #passengerContainer .ds-zone:has(> .ds-zone-h .fa-coins){--z:#059669;--zb:#f6fcf9;--zl:#b5e2cd;}
    #passengerContainer .ds-zone-h{color:var(--z);font-weight:800;}
    #passengerContainer .ds-zone-h i{background:#fff;color:var(--z);box-shadow:0 0 0 1px var(--zl);}
    #passengerContainer .ds-zone-h::after{border-top:1px solid var(--zl)!important;}
    /* Flight zone on ONE line (owner, 2026-10-04): flight no · departure · arrival · RBD · baggage. */
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]){grid-template-columns:repeat(12,minmax(0,1fr));}
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > *{grid-column:span 2;}
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(2),#passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(3){grid-column:span 3;}
    /* One line, one meaning (owner, 2026-10-04: "in the same way, rearrange"). */
    /* Every zone sits on the same 12 tracks (2026-10-05) so column edges run straight down the
       card: route/trip/class, ticket/seller and payment break at 4 · 8, fare and flight at 2 · 8 · 10. */
    #passengerContainer .ph-create-btn{border-color:#c4b5fd;background:#f5f3ff;color:#7c3aed;}
    /* Traveller & route — passenger · route · trip · class */
    #passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]){grid-template-columns:repeat(12,minmax(0,1fr));}
    #passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]) > *{grid-column:span 2;}
    #passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]) > :nth-child(-n+2){grid-column:span 4;}
    /* Ticket & seller — the ticket (PNR · e-ticket · issue date), then who issued / sold it */
    #passengerContainer .ds-zone .ds-row:has([name$="[ticket_no]"]){grid-template-columns:repeat(12,minmax(0,1fr));}
    #passengerContainer .ds-zone .ds-row:has([name$="[ticket_no]"]) > *{grid-column:span 4;}
    #passengerContainer .ds-zone .ds-row:has([name$="[ticket_no]"]) > :nth-child(1){order:1;}
    #passengerContainer .ds-zone .ds-row:has([name$="[ticket_no]"]) > :nth-child(2){order:2;}
    #passengerContainer .ds-zone .ds-row:has([name$="[ticket_no]"]) > :nth-child(4){order:3;}
    #passengerContainer .ds-zone .ds-row:has([name$="[ticket_no]"]) > :nth-child(3){order:4;}
    #passengerContainer .ds-zone .ds-row:has([name$="[ticket_no]"]) > :nth-child(5){order:5;}
    #passengerContainer .ds-zone .ds-row:has([name$="[ticket_no]"]) > :nth-child(6){order:6;}
    /* Fare build-up on one line — base fare · taxes · tax codes · commission % · commission ৳ */
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]){grid-template-columns:repeat(12,minmax(0,1fr));}
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > *{grid-column:span 2;}
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(3){grid-column:span 4;}
    /* Payment — net · paid · status, then bank · due date · attachment. Columns are pinned so a
       hidden due date (portal purchase) leaves a gap instead of sliding the attachment left. */
    #passengerContainer .ds-zone .ds-row:has([name$="[cost_amount]"]){grid-template-columns:repeat(12,minmax(0,1fr));}
    #passengerContainer .ds-zone .ds-row:has([name$="[cost_amount]"]) > :nth-child(3n+1){grid-column:1 / span 4;}
    #passengerContainer .ds-zone .ds-row:has([name$="[cost_amount]"]) > :nth-child(3n+2){grid-column:5 / span 4;}
    #passengerContainer .ds-zone .ds-row:has([name$="[cost_amount]"]) > :nth-child(3n){grid-column:9 / span 4;}
    #passengerContainer .ds-zone .ds-row > *{min-width:0;}
    /* Dropdown lists in the ticket theme (owner, 2026-10-04). This page only. */
    .select2-container--open .select2-dropdown{border:1px solid #e4e1f7;border-radius:10px;overflow:hidden;box-shadow:0 10px 28px rgba(79,70,229,.14),0 2px 6px rgba(15,23,42,.06);margin-top:4px;}
    .select2-container--open .select2-dropdown--above{margin-top:-4px;}
    .select2-search--dropdown{padding:8px;}
    .select2-search--dropdown .select2-search__field{height:30px;border:1px solid #e4e1f7!important;border-radius:7px;padding:4px 10px;font-size:12.5px;outline:none;}
    .select2-search--dropdown .select2-search__field:focus{border-color:#a78bfa!important;box-shadow:0 0 0 3px rgba(124,58,237,.1);}
    .select2-results > .select2-results__options{padding:4px;max-height:240px!important;}
    .select2-results__option{padding:7px 10px;border-radius:7px;font-size:12.5px;line-height:1.35;color:#334155;}
    .select2-results__option + .select2-results__option{margin-top:1px;}
    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable,
    .select2-container--default .select2-results__option--highlighted[aria-selected]{background:#f5f3ff;color:#4c1d95;}
    .select2-container--default .select2-results__option--selected,
    .select2-container--default .select2-results__option[aria-selected=true]{background:#ede9fe;color:#5b21b6;font-weight:600;}
    .select2-results__option[aria-disabled=true]:first-child,.select2-results__option:first-child:not([id]){color:#94a3b8;}
    .select2-results__options::-webkit-scrollbar{width:6px;}
    .select2-results__options::-webkit-scrollbar-thumb{background:#ddd6fe;border-radius:3px;}
    /* Trip type (2026-10-04): Return line under the flight; Itinerary for multi-city. */
    #passengerContainer .ds-zone .ds-row.ds-return-row{grid-template-columns:repeat(12,minmax(0,1fr));}
    #passengerContainer .ds-zone .ds-row.ds-return-row > :nth-child(1){grid-column:span 2;}
    #passengerContainer .ds-zone .ds-row.ds-return-row > :nth-child(2){grid-column:span 3;}
    #passengerContainer .ds-zone .ds-row.ds-return-row > :nth-child(3){grid-column:span 7;}
    .ds-return-note{display:flex;align-items:center;gap:6px;font-size:11px;color:#8b88a8;padding-left:4px;}
    /* Cancel return (2026-10-14), on the return row (owner, 2026-10-15): Return Flight ·
       Return Departure · Cancel return by · Family ticket at the right end. One-way /
       multi-city (a separate return ticket): only the Family pair, tick first. */
    #passengerContainer .ds-zone .ds-row.ds-return-row.ds-has-fam{align-items:end;}
    #passengerContainer .ds-zone .ds-row.ds-return-row > .ds-fam-by{grid-column:span 3;}
    #passengerContainer .ds-zone .ds-row.ds-return-row > .ds-fam-tick{grid-column:span 4;justify-content:flex-end;}
    #passengerContainer .ds-pax-card:not([data-trip="two-way"]) .ds-return-row > .ds-ret-f{display:none;}
    #passengerContainer .ds-pax-card:not([data-trip="two-way"]) .ds-return-row > .ds-fam-tick{order:-1;grid-column:span 3;justify-content:flex-start;}
    /* not a <label> (owner, 2026-10-15): only the box itself ticks — not the words, not the empty space */
    .ds-fam-tick{display:flex;align-items:center;align-self:stretch;gap:7px;font-size:12px;font-weight:600;color:#5a6480;cursor:default;}
    .ds-fam-tick input{width:15px;height:15px;accent-color:#7c3aed;cursor:pointer;}
    .ds-return-row:not(.on) .ds-fam-by{visibility:hidden;}
    .ds-return-note i{color:#a78bfa;font-size:10px;}
    /* Paired fields (2026-10-05): boxes that belong together share one capsule — departure /
       arrival, base fare / taxes, commission % / ৳, trip / class. No seam badges (owner: "these
       icons look bad"). Same grid tracks, no extra room. */
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(2),
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(1),
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(4){margin-right:-4px;}
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(3),
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(2),
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(5){margin-left:-5px;}
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(2) > .ds-field-input,
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(1) > .ds-field-input,
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(4) > .ds-field-input{border-top-right-radius:0;border-bottom-right-radius:0;}
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(3) > .ds-field-input,
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(2) > .ds-field-input,
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(5) > .ds-field-input{border-top-left-radius:0;border-bottom-left-radius:0;}
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(2) > .ds-field-input:focus,
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(1) > .ds-field-input:focus,
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(4) > .ds-field-input:focus,
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(3) > .ds-field-input:focus,
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(2) > .ds-field-input:focus,
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(5) > .ds-field-input:focus{position:relative;z-index:1;}
    /* Trip type / Class are one capsule too (owner, 2026-10-05: "do the same"). */
    #passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]) > :nth-child(3){margin-right:-4px;}
    #passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]) > :nth-child(4){margin-left:-5px;}
    #passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]) > :nth-child(3) > .select2-container .select2-selection--single{border-top-right-radius:0;border-bottom-right-radius:0;}
    #passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]) > :nth-child(4) > .select2-container .select2-selection--single{border-top-left-radius:0;border-bottom-left-radius:0;}
    #passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]) > :nth-child(4) > .select2-container .select2-selection--single .select2-selection__rendered{padding-left:10px;}
    #passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]) > :nth-child(3) > .select2-container--focus,#passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]) > :nth-child(3) > .select2-container--open,#passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]) > :nth-child(4) > .select2-container--focus,#passengerContainer .ds-zone .ds-row:has([name$="[passport_holder_id]"]) > :nth-child(4) > .select2-container--open{z-index:1;}
    /* Fare line order (owner, 2026-10-05): base + taxes · commission % ⇄ ৳ · tax codes last. */
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(1),#passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(2){order:1;}
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(4),#passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(5){order:2;}
    #passengerContainer .ds-zone .ds-row:has([name$="[base_fare]"]) > :nth-child(3){order:3;}
    /* Zones tick themselves off (2026-10-05): once every required box in a zone is filled, its
       icon and rule turn green — progress at a glance, read straight from the browser's own
       validity, no script. Zones with nothing required (Flight) stay neutral. */
    #passengerContainer .ds-zone:has([required]):not(:has(:invalid)) > .ds-zone-h{color:#15803d;}
    #passengerContainer .ds-zone:has([required]):not(:has(:invalid)) > .ds-zone-h i{background:#dcfce7;color:#16a34a!important;}
    #passengerContainer .ds-zone:has([required]):not(:has(:invalid)) > .ds-zone-h::after{border-top-color:#86efac;}
    #passengerContainer .ds-zone-h,#passengerContainer .ds-zone-h i,#passengerContainer .ds-zone-h::after{transition:color .25s,background-color .25s,border-color .25s;}
    /* Codes read like a boarding pass: PNR and e-ticket in even-width type, spaced. */
    #passengerContainer .ds-pnr,#passengerContainer .ds-eticket{font-family:ui-monospace,'JetBrains Mono',Consolas,monospace;letter-spacing:.08em;font-size:12.5px;}
    #passengerContainer .ds-pnr:not(:placeholder-shown){font-weight:700;color:#4c1d95;}
    #passengerContainer input[name$="[rbd]"]{text-align:center;font-weight:700;letter-spacing:.1em;}
    /* Pay status wears its meaning: due red, partial amber, paid green. */
    #passengerContainer div:has(> select[name$="[purchase_pay_status]"] option[value="due"]:checked) .select2-selection--single{background:#fef2f2;border-color:#fecaca;}
    #passengerContainer div:has(> select[name$="[purchase_pay_status]"] option[value="due"]:checked) .select2-selection__rendered{color:#b91c1c!important;font-weight:600;}
    #passengerContainer div:has(> select[name$="[purchase_pay_status]"] option[value="partial"]:checked) .select2-selection--single{background:#fffbeb;border-color:#fde68a;}
    #passengerContainer div:has(> select[name$="[purchase_pay_status]"] option[value="partial"]:checked) .select2-selection__rendered{color:#b45309!important;font-weight:600;}
    #passengerContainer div:has(> select[name$="[purchase_pay_status]"] option[value="paid"]:checked) .select2-selection--single{background:#f0fdf4;border-color:#bbf7d0;}
    #passengerContainer div:has(> select[name$="[purchase_pay_status]"] option[value="paid"]:checked) .select2-selection__rendered{color:#15803d!important;font-weight:600;}
    #passengerContainer div:has(> select[name$="[purchase_pay_status]"]) > .ds-field-lbl{background:linear-gradient(180deg,#fff 50%,transparent 50%);}
    /* RBD | Baggage — one capsule, like the other pairs (owner, 2026-10-05: "merge this too"). */
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(4){margin-right:-4px;}
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(5){margin-left:-5px;}
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(4) > .ds-field-input{border-top-right-radius:0;border-bottom-right-radius:0;}
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(5) > .ds-field-input{border-top-left-radius:0;border-bottom-left-radius:0;}
    #passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(4) > .ds-field-input:focus,#passengerContainer .ds-zone .ds-row.cols-6:has([name$="[departure_at]"]) > :nth-child(5) > .ds-field-input:focus{position:relative;z-index:1;}
    /* Route legs on the same 12 tracks: From | To capsule · travel date · cost · remove. */
    #passengerContainer [data-leg-index]{grid-template-columns:repeat(12,minmax(0,1fr))!important;gap:8px!important;margin-bottom:8px!important;}
    #passengerContainer [data-leg-index] > :nth-child(1),#passengerContainer [data-leg-index] > :nth-child(2){grid-column:span 2;}
    #passengerContainer [data-leg-index] > :nth-child(3){grid-column:span 4;}
    #passengerContainer [data-leg-index] > :nth-child(4){grid-column:span 3;}
    #passengerContainer [data-leg-index] > button{grid-column:span 1;height:32px!important;border-radius:7px!important;font-size:12px!important;}
    #passengerContainer [data-leg-index] [name$="[cost]"]{text-align:right;font-variant-numeric:tabular-nums;}
    #passengerContainer [data-leg-index] [name$="[from_location]"],#passengerContainer [data-leg-index] [name$="[to_location]"]{font-weight:600;letter-spacing:.06em;}
    #passengerContainer [data-leg-index] > :nth-child(1){margin-right:-4px;}
    #passengerContainer [data-leg-index] > :nth-child(2){margin-left:-5px;}
    #passengerContainer [data-leg-index] > :nth-child(1) > .ds-field-input{border-top-right-radius:0;border-bottom-right-radius:0;}
    #passengerContainer [data-leg-index] > :nth-child(2) > .ds-field-input{border-top-left-radius:0;border-bottom-left-radius:0;}
    #passengerContainer [data-leg-index] > :nth-child(1) > .ds-field-input:focus,#passengerContainer [data-leg-index] > :nth-child(2) > .ds-field-input:focus{position:relative;z-index:1;}
    /* Hold box: booked | expires capsule, then who is told and how — same tracks as the card. */
    #passengerContainer .ds-book-box .ds-row:has([name$="[booking_at]"]),#passengerContainer .ds-book-box .ds-row:has([name$="[notification_recipient_id]"]){grid-template-columns:repeat(12,minmax(0,1fr));gap:8px;}
    #passengerContainer .ds-book-box .ds-row:has([name$="[booking_at]"]) > *{grid-column:span 4;}
    #passengerContainer .ds-book-box .ds-row:has([name$="[notification_recipient_id]"]) > :nth-child(1){grid-column:span 4;}
    #passengerContainer .ds-book-box .ds-row:has([name$="[notification_recipient_id]"]) > :nth-child(2){grid-column:span 8;}
    #passengerContainer .ds-book-ch{height:32px;border-radius:7px;}
    #passengerContainer .ds-book-box .ds-row:has([name$="[booking_at]"]) > :nth-child(1){margin-right:-4px;}
    #passengerContainer .ds-book-box .ds-row:has([name$="[booking_at]"]) > :nth-child(2){margin-left:-5px;}
    #passengerContainer .ds-book-box .ds-row:has([name$="[booking_at]"]) > :nth-child(1) > .ds-field-input{border-top-right-radius:0;border-bottom-right-radius:0;}
    #passengerContainer .ds-book-box .ds-row:has([name$="[booking_at]"]) > :nth-child(2) > .ds-field-input{border-top-left-radius:0;border-bottom-left-radius:0;}
    #passengerContainer .ds-book-box .ds-row:has([name$="[booking_at]"]) > :nth-child(1) > .ds-field-input:focus,#passengerContainer .ds-book-box .ds-row:has([name$="[booking_at]"]) > :nth-child(2) > .ds-field-input:focus{position:relative;z-index:1;}
    /* Ticket status wears its colour, matching the card's edge: purchased violet, booked blue,
       pending / draft grey, cancelled red. */
    #passengerContainer .ds-status-wrap .ds-field-lbl{background:linear-gradient(180deg,#fff 50%,transparent 50%);}
    #passengerContainer .ds-status-wrap:has(option[value="purchased"]:checked) .select2-selection--single{background:#f5f3ff!important;border-color:#c4b5fd!important;}
    #passengerContainer .ds-status-wrap:has(option[value="purchased"]:checked) .select2-selection__rendered{color:#5b21b6!important;font-weight:700;}
    #passengerContainer .ds-status-wrap:has(option[value="booked"]:checked) .select2-selection--single{background:#f0f9ff!important;border-color:#7dd3fc!important;}
    #passengerContainer .ds-status-wrap:has(option[value="booked"]:checked) .select2-selection__rendered{color:#0369a1!important;font-weight:700;}
    #passengerContainer .ds-status-wrap:has(option[value="pending"]:checked) .select2-selection--single{background:#f8fafc!important;border-color:#cbd5e1!important;border-style:dashed!important;}
    #passengerContainer .ds-status-wrap:has(option[value="pending"]:checked) .select2-selection__rendered{color:#475569!important;font-weight:700;}
    #passengerContainer .ds-status-wrap:has(option[value="draft"]:checked) .select2-selection--single{background:#f8fafc!important;border-color:#cbd5e1!important;border-style:dashed!important;}
    #passengerContainer .ds-status-wrap:has(option[value="draft"]:checked) .select2-selection__rendered{color:#475569!important;font-weight:700;}
    #passengerContainer .ds-status-wrap:has(option[value="cancelled"]:checked) .select2-selection--single{background:#fef2f2!important;border-color:#fca5a5!important;}
    #passengerContainer .ds-status-wrap:has(option[value="cancelled"]:checked) .select2-selection__rendered{color:#b91c1c!important;font-weight:700;}
    /* No example text inside the boxes (owner, 2026-10-05: "remove these shadow text") — the
       notched title already names each box. Net Cost keeps its "Type net cost" prompt. Hidden
       with CSS so the date picker's own default and the JS-built leg rows are covered too. */
    #passengerContainer input.ds-field-input:not(.ds-net)::placeholder{color:transparent;}
    /* Money reads like a ledger: figures right-aligned on even digits. */
    #passengerContainer .ds-fare,#passengerContainer .ds-tax,#passengerContainer .ds-compct,#passengerContainer .ds-comamt,#passengerContainer .ds-net,#passengerContainer .ds-paid{text-align:right;font-variant-numeric:tabular-nums;}
    /* Net Cost is typed directly (owner, 2026-10-05: "enter the net cost without the other
       amounts") — the fare boxes only pre-fill it. Tinted as the key figure, never shown as derived. */
    #passengerContainer .ds-net{background:linear-gradient(90deg,#f5f3ff,#faf9ff)!important;border-color:#c4b5fd!important;color:#4c1d95!important;font-size:13px!important;}
    #passengerContainer div:has(> .ds-net) > .ds-field-lbl{background:linear-gradient(180deg,#fff 50%,#f5f3ff 50%);}
    /* The "+" create buttons dock onto their dropdown as one control — the full height of the
       box, its square end (owner, 2026-10-25: a 32px "+" beside the 42px box "looks bad"). The
       !important beats the shared Ticketing dropdown style (42px, radius 6px !important). */
    #passengerContainer div:has(> .ph-create-btn){gap:0!important;align-items:stretch!important;}
    #passengerContainer div:has(> .ph-create-btn) > .select2-container .select2-selection--single{border-top-right-radius:0!important;border-bottom-right-radius:0!important;}
    #passengerContainer .ph-create-btn{margin-left:-1px;width:36px;height:auto;border-radius:0 6px 6px 0;}
    #passengerContainer .ds-pax-card[data-trip="two-way"] .ds-return-row{background:#faf9ff;border-radius:8px;padding:8px 6px 0;margin:-2px -6px 8px;}
    #passengerContainer .ds-pax-card[data-trip="multi-city"] .ds-pax-legs{background:#faf9ff;border:1px solid #ede9fe!important;border-radius:10px;padding:10px 12px 4px!important;}
    #passengerContainer .ds-pax-card[data-trip="multi-city"] .ds-legs-hd{color:#6d28d9!important;}
    #passengerContainer .ds-pax-card[data-trip="multi-city"] .ds-legs-hd::before{content:'Itinerary · ';font-weight:800;}

    /* agent price → profit (2026-10-05) */
    .ds-profit{background:#f8fafc!important;font-weight:700;text-align:right;}
    .ds-profit.pos{color:#15803d;background:#f0fdf4!important;}
    .ds-profit.neg{color:#b91c1c;background:#fef2f2!important;}
    .ds-sale{text-align:right;}
    .ds-sale-note{align-self:center;font-size:11.5px;color:#b45309;}
    .ds-rt{display:inline-flex;align-items:center;gap:7px;max-width:100%;}
    .ds-rt-t{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .ds-blogo{width:20px;height:20px;border-radius:5px;object-fit:contain;background:#fff;flex-shrink:0;box-shadow:0 0 0 1px rgba(15,23,42,.1);}
    .ds-oic{width:20px;height:20px;border-radius:5px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0;}
    .ds-blogo-x{display:inline-block;background:#eef2ff;}
    .ds-alogo-x{display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:8px;font-weight:800;letter-spacing:.02em;}
    .ds-route-path{display:flex;flex-wrap:wrap;align-items:center;gap:5px;margin-top:5px;font-size:11.5px;font-weight:700;color:#312e81;letter-spacing:.03em;}
    .ds-route-path .ap{display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:6px;background:#f5f3ff;border:1px solid #e9e5fb;}
    .ds-route-path .ap.tr{background:#ecfeff;border-color:#bae6fd;color:#0e7490;}
    .ds-route-path .ap small{font-size:9px;font-weight:700;text-transform:uppercase;}
    .ds-route-path i{color:#a78bfa;font-size:9px;}
    .ds-rflag{width:18px;height:12px;border-radius:2px;object-fit:cover;flex-shrink:0;box-shadow:0 0 0 1px rgba(15,23,42,.12);}
    /* Sold to · cost bank · pay status — three equal columns, lined up with the payment row (owner, 2026-10-07). */
    #passengerContainer .ds-row.ds-sale-row{grid-template-columns:repeat(3,minmax(0,1fr));}
    #passengerContainer .ds-row.ds-sale-row > .ds-sale-note{grid-column:1 / -1;}
    #passengerContainer .ds-row.ds-sale-row > .ds-sale-note:empty{display:none;}
    .ds-sale-row.ds-cf-billed, .ds-pax-card:has(.ds-cf-billed) .ds-sale{opacity:.55;}

    /* ── Import PDF (2026-10-05) ── */
    .ti-panel{background:#fff;border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);padding:14px 16px;margin:0 0 16px;}
    .ti-panel[hidden]{display:none;}
    .ti-head{display:flex;align-items:baseline;gap:10px;margin-bottom:10px;}
    .ti-title{font-size:14px;font-weight:700;color:var(--text);}
    .ti-title i{color:#7c3aed;margin-right:4px;}
    .ti-sub{font-size:11.5px;color:var(--text3);}
    .ti-x{margin-left:auto;border:0;background:transparent;color:var(--text3);font-size:14px;cursor:pointer;padding:2px 6px;border-radius:6px;}
    .ti-x:hover{background:var(--bg3);color:var(--text);}
    .ti-drop{display:flex;flex-direction:column;align-items:center;gap:4px;padding:18px;border:2px dashed #d8d2f5;border-radius:12px;background:#faf9ff;cursor:pointer;text-align:center;transition:all .15s;}
    .ti-drop i{font-size:22px;color:#7c3aed;}
    .ti-drop span{font-size:13px;color:var(--text);}
    .ti-drop small{font-size:11.5px;color:var(--text2);}
    .ti-drop:hover,.ti-drop.over{border-color:#7c3aed;background:#f5f3ff;}
    /* Ticket Import (owner, 2026-10-19): PDF and CSV / Excel side by side; one zone = full width */
    .ti-drops{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;}
    .ti-head .ti-title{white-space:nowrap;flex-shrink:0;}
    /* Ticket Import tab: the header card is hidden, so the 20px that spaced it from the entry goes too —
       tab band → import panel 40px → 20px (owner, 2026-10-19: "reduce this space by 50%") */
    .ti-import-tab > #tab-sale{padding-top:0;}
    /* no white box behind the cards on this tab ("i said to remove this too") — they sit on the page, edge to
       edge with the tab band, like every card below it */
    .ti-import-tab > #tab-sale{background:transparent;border:0;box-shadow:none;border-radius:0;padding:0;}
    .ti-drop .ti-drop-t{font-size:13.5px;font-weight:700;color:var(--text);}
    /* a file with its corner folded, labelled — the icon font's file-pdf glyph reads "PNG" here */
    .ti-drop .ti-kind{display:inline-flex;align-items:flex-end;justify-content:center;flex-shrink:0;width:26px;height:32px;padding-bottom:5px;box-sizing:border-box;border-radius:4px;clip-path:polygon(0 0,66% 0,100% 26%,100% 100%,0 100%);color:#fff;font-size:8.5px;font-weight:800;letter-spacing:.3px;line-height:1;}
    /* owner, 2026-10-19 ("maintain this size exactly", red boxes ≈ 490 × 84): each zone is one fixed-height
       strip — badge left, three one-line rows; only the grey list is cut, its full text in the tooltip */
    .ti-drops .ti-drop{flex-direction:row;justify-content:center;gap:12px;padding:11px 16px;text-align:left;}
    .ti-drops .ti-drop-txt{display:flex;flex-direction:column;gap:2px;min-width:0;}
    .ti-drops .ti-drop-txt > *{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:18px;}
    .ti-drops .ti-drop-txt > small{line-height:16px;}
    .ti-drops .ti-drop-h{display:flex;align-items:center;gap:8px;}
    .ti-drops .ti-drop-h .ti-tpl{margin-left:0;line-height:14px;}
    .ti-drop .ti-kind.pdf{background:#dc2626;}
    .ti-drop .ti-kind.xls{background:#16a34a;}
    .ti-drop .ti-tpl{display:inline-flex;align-items:center;gap:5px;margin-left:4px;border:1px solid #bbf7d0;background:#f0fdf4;color:#15803d;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap;text-decoration:none;}
    .ti-drop .ti-tpl:hover{background:#dcfce7;}
    .ti-drop .ti-tpl i{font-size:10px;color:inherit;}
    .ti-list{display:flex;flex-direction:column;gap:6px;margin-top:10px;}
    .ti-list:empty{display:none;}
    .ti-row{display:grid;grid-template-columns:22px minmax(0,1.3fr) minmax(0,1fr) minmax(0,2fr) 54px;gap:10px;align-items:center;padding:8px 12px;border:1px solid var(--border);border-radius:10px;font-size:12.5px;}
    .ti-row .ti-btns{display:flex;gap:4px;justify-content:flex-end;}
    .ti-row .ti-eye{width:24px;height:24px;border:1px solid #e0e7ff;background:#eef2ff;color:#4f46e5;border-radius:6px;cursor:pointer;font-size:11px;display:inline-flex;align-items:center;justify-content:center;}
    .ti-row .ti-eye:hover{background:#e0e7ff;}
    .ti-page-drop{position:fixed;inset:0;z-index:8400;background:rgba(79,70,229,.10);border:3px dashed #7c3aed;display:none;align-items:center;justify-content:center;pointer-events:none;}
    .ti-page-drop.on{display:flex;}
    .ti-page-drop span{background:#fff;color:#4c1d95;font-weight:700;font-size:15px;padding:14px 22px;border-radius:14px;box-shadow:0 12px 30px rgba(76,29,149,.18);}
    .ti-page-drop span i{margin-right:8px;color:#7c3aed;}
    .ti-row .ti-rm{width:24px;height:24px;border:1px solid #fecaca;background:#fef2f2;color:#dc2626;border-radius:6px;cursor:pointer;font-size:11px;display:inline-flex;align-items:center;justify-content:center;}
    .ti-row .ti-rm:hover{background:#fee2e2;}
    .ti-row .ic{font-size:14px;text-align:center;}
    .ti-row .fn{font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .ti-row .ly{color:var(--text2);}
    .ti-row .rs{color:var(--text2);}
    .ti-row.ok .ic{color:var(--green);}
    .ti-row.wait .ic{color:var(--text3);}
    .ti-row.bad{background:#fffbfb;border-color:#fee2e2;}
    .ti-row.bad .ic,.ti-row.bad .rs{color:#b91c1c;}
    .ti-mono{font-family:'DM Mono',monospace;letter-spacing:.3px;}
    /* the strip a PDF leaves on its card: where it came from + what staff must still decide */
    .ti-strip{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin:0 0 8px;padding:7px 10px;border-radius:10px;background:#f8fafc;border:1px solid var(--border);font-size:11.5px;color:var(--text2);}
    .ti-strip .src{font-weight:600;color:var(--text);margin-right:2px;}
    .ti-strip .src i{color:#dc2626;margin-right:4px;}
    .ti-strip .src i.fa-file-excel{color:#16a34a;}
    .ti-strip .ds-chk{padding:3px 8px;font-size:11px;}
    .ti-strip .ti-q{display:inline-flex;align-items:center;flex-wrap:wrap;gap:5px;padding:3px 4px 3px 8px;border-radius:8px;background:#fffbeb;border:1px solid #fef3c7;color:#b45309;}
    .ti-pick{border:1px solid #e0e7ff;background:#fff;color:#4338ca;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;}
    .ti-pick:hover{background:#eef2ff;}
    .ti-pick.new{border-color:#ddd6fe;color:#6d28d9;}
    .ti-pick.on{background:#4f46e5;border-color:#4f46e5;color:#fff;}
    .ti-reopen{margin-top:10px;display:flex;justify-content:flex-end;}

    /* the guided modal: one step per passenger with something missing, then Review */
    .ti-wiz{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:8500;display:none;align-items:center;justify-content:center;padding:16px;}
    .ti-wiz.open{display:flex;}
    .ti-wiz-box{background:#fff;border-radius:16px;box-shadow:0 24px 60px rgba(15,23,42,.3);width:min(1280px,100%);height:min(720px, calc(100vh - 32px));display:flex;flex-direction:column;overflow:hidden;}
    .ti-wiz-hd{display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid var(--border);}
    .ti-wiz-hd .t{font-size:15px;font-weight:700;color:var(--text);}
    .ti-wiz-hd .s{font-size:12px;color:var(--text2);}
    .ti-wiz-dots{display:flex;gap:4px;flex-wrap:wrap;margin-left:auto;max-width:45%;justify-content:flex-end;}
    .ti-wiz-dots i{width:9px;height:9px;border-radius:50%;background:#e2e8f0;cursor:pointer;}
    .ti-wiz-dots i.ok{background:#22c55e;}
    .ti-wiz-dots i.miss{background:#f59e0b;}
    .ti-wiz-dots i.cur{outline:2px solid #4f46e5;outline-offset:1px;}
    .ti-wiz-dots i.rev{border-radius:3px;background:#c7d2fe;}
    .ti-wiz-body{padding:18px 22px;overflow:auto;flex:1;min-height:0;overscroll-behavior:contain;}
    #tiWiz .select2-results__options{overscroll-behavior:contain;}
    .ti-wiz-ft{display:flex;align-items:center;gap:8px;padding:12px 20px;border-top:1px solid var(--border);background:#fafbff;}
    .ti-wiz-ft .sp{flex:1;}
    .ti-facts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px 18px;padding:12px 16px;border-radius:12px;background:#f8fafc;border:1px solid var(--border);margin-bottom:16px;}
    .ti-facts .k{font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--text3);}
    .ti-facts .v{font-size:13px;color:var(--text);font-weight:600;overflow-wrap:anywhere;}
    .ti-facts .v small{display:block;font-weight:400;color:var(--text2);font-size:11.5px;}
    .ti-sec{border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:12px;}
    .ti-sec.done{border-color:#bbf7d0;background:#f7fef9;}
    .ti-sec-h{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:var(--text);margin-bottom:10px;}
    .ti-sec-h .n{width:20px;height:20px;border-radius:50%;background:#eef2ff;color:#4f46e5;display:inline-flex;align-items:center;justify-content:center;font-size:10.5px;}
    .ti-sec.done .ti-sec-h .n{background:#22c55e;color:#fff;}
    .ti-sec.dup{border-color:#fecaca;background:#fef2f2;}
    .ti-sec.dup .ti-sec-h{color:#b91c1c;}
    .ti-sec.dup .ti-sec-h .n{background:#dc2626;color:#fff;}
    .ti-sec.dup ul{margin:0 0 10px 18px;padding:0;font-size:12.5px;color:#7f1d1d;}
    .ti-link{display:flex;align-items:center;gap:8px;padding:9px 14px;border-radius:10px;background:#eff6ff;border:1px solid #dbeafe;color:#1d4ed8;font-size:12.5px;margin-bottom:12px;}
    .ti-rev .dupl{color:#b91c1c;}
    /* the one-table popup (2026-10-05) */
    .ti-tbl{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12.5px;}
    .ti-tbl th{font-size:10px;letter-spacing:.5px;text-transform:uppercase;color:var(--text3);text-align:left;padding:6px 6px 8px;border-bottom:1px solid var(--border);white-space:nowrap;position:sticky;top:-18px;background:#fff;z-index:2;}
    .ti-tbl th.r,.ti-tbl td.r{text-align:right;}
    /* Seller and Agent 35% wider (owner, 2026-10-06): 13 → 17.5 %, 12 → 16.2 %; the room
       comes from Passenger, Ticket, Route, Profit and the state column. */
    /* widths add up inside the box (26px + 120px + 86%): the route gets room for its flags and name (2026-10-07) */
    .ti-tbl th:nth-child(1){width:26px}.ti-tbl th:nth-child(2){width:12%}.ti-tbl th:nth-child(3){width:9%}.ti-tbl th:nth-child(4){width:16.5%}
    .ti-tbl th:nth-child(5){width:14.5%}.ti-tbl th:nth-child(6){width:7.5%}.ti-tbl th:nth-child(7){width:13%}.ti-tbl th:nth-child(8){width:7.5%}
    .ti-tbl th:nth-child(9){width:6%}.ti-tbl th:nth-child(10){width:120px}
    .ti-tbl td.ti-state .ti-link{display:block;margin-top:3px;}
    /* Picker lists in the import sheet: one name per line, rows grow with
       the text (names were wrapping onto 2–3 lines and overlapping), and the
       list is wide enough for a full agent / vendor name. */
    #tiWiz .select2-container--open .select2-dropdown{min-width:300px;}
    #tiWiz .select2-results__options{max-height:300px!important;}
    #tiWiz .select2-results__option{height:auto!important;min-height:0;line-height:1.4;padding:8px 12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    #tiWiz .select2-results__group{padding:8px 12px 4px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;}
    #tiWiz .select2-search--dropdown .select2-search__field{height:34px;}
    .ti-tbl td{padding:8px 6px;border-bottom:1px solid #f1f5f9;vertical-align:middle;overflow:hidden;}
    .ti-tbl td small{display:block;color:var(--text3);font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .ti-tbl .ti-t{font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:inline-block;max-width:calc(100% - 18px);vertical-align:middle;}
    .ti-tbl .ti-new{color:#4f46e5;}
    .ti-tbl .ti-ok{color:#16a34a;font-size:11px;margin-right:5px;}
    .ti-tbl .ti-dim{color:var(--text3);}
    .ti-tbl .ti-num{height:34px;text-align:right;padding:4px 8px;}
    .ti-tbl .ti-num-c{text-align:right;}
    .ti-tbl .ti-need .select2-selection,.ti-tbl input.ti-need,.ti-tbl .ti-pick-pax.ti-need .select2-selection{border-color:#f87171!important;background:#fef2f2!important;}
    .ti-tbl .ti-pick-pax{display:flex;gap:4px;align-items:center;}
    .ti-tbl .ti-pick-pax .select2-container{flex:1;min-width:0;}
    .ti-tbl .ti-mini{border:1px solid #ddd6fe;background:#fff;color:#6d28d9;border-radius:6px;padding:3px 7px;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;margin-top:3px;}
    .ti-tbl .ti-mini.ti-warn{border-color:#fde68a;background:#fffbeb;color:#b45309;}
    .ti-tbl .ti-link{border:0;background:none;color:#6366f1;font-size:11px;cursor:pointer;padding:0;}
    .ti-tbl .ti-warn-t{color:#dc2626;font-size:11px;font-weight:600;}
    .ti-tbl .ti-pf{display:inline-block;font-size:11px;font-weight:700;padding:1px 8px;border-radius:999px;background:#ecfdf5;color:#047857;}
    .ti-tbl .ti-pf.neg{background:#fef2f2;color:#dc2626;}
    .ti-tbl .ti-down{border:1px solid #e0e7ff;background:#eef2ff;color:#4f46e5;border-radius:5px;font-size:10px;line-height:1;padding:2px 5px;margin-left:4px;cursor:pointer;}
    .ti-tbl tr.ti-row-need td:first-child{box-shadow:inset 3px 0 0 #f87171;}
    /* import notes under the table: what the reader said about each ticket (owner, 2026-10-11) */
    .ti-notes{margin-top:16px;border:1px solid #fde68a;background:#fffbeb;border-radius:10px;padding:10px 14px;}
    .ti-notes .h{font-size:12px;font-weight:700;color:#92400e;margin-bottom:6px;}
    .ti-notes .tk{margin:6px 0 2px;font-size:12px;font-weight:600;color:#334155;}
    .ti-notes ul{margin:0;padding-left:18px;font-size:12px;color:#78350f;}
    .ti-notes li{margin:2px 0;}
    .ti-notes li.need{color:#b91c1c;font-weight:600;}
    /* after "Close & edit on page": red = still to type, amber = read but check it */
    @keyframes tiGlowRed{0%,100%{box-shadow:0 0 0 2px rgba(239,68,68,.35);}50%{box-shadow:0 0 0 5px rgba(239,68,68,.15);}}
    @keyframes tiGlowAmber{0%,100%{box-shadow:0 0 0 2px rgba(245,158,11,.35);}50%{box-shadow:0 0 0 5px rgba(245,158,11,.12);}}
    #passengerContainer .ti-glow{border-color:#ef4444!important;background:#fef2f2!important;animation:tiGlowRed 1.6s ease-in-out infinite;}
    #passengerContainer .ti-glow-check{border-color:#f59e0b!important;background:#fffbeb!important;animation:tiGlowAmber 1.6s ease-in-out infinite;}
    .ti-tbl tr.ti-subrow td{background:#fffdf5;padding:4px 6px 12px;}
    .ti-tbl tfoot td{font-weight:700;border-bottom:0;border-top:2px solid var(--border);padding-top:10px;}
    .ti-tbl .select2-container--default .select2-selection--single{height:34px;}
    .ti-tbl .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:32px;font-size:12px;}
    .ti-profit{height:38px;display:flex;align-items:center;justify-content:flex-end;padding:0 12px;border-radius:8px;background:#f8fafc;border:1px solid var(--border);font-weight:700;font-variant-numeric:tabular-nums;}
    .ti-profit.pos{color:#15803d;background:#f0fdf4;border-color:#bbf7d0;}
    .ti-profit.neg{color:#b91c1c;background:#fef2f2;border-color:#fecaca;}
    .ti-rev .lnk{color:#1d4ed8;}
    .ti-sec-h .cur{margin-left:auto;font-weight:500;color:#15803d;font-size:12px;}
    .ti-opts{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
    .ti-opts .ti-pick{padding:6px 12px;font-size:12px;border-radius:8px;}
    .ti-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
    .ti-grid .ds-field-lbl{position:static;display:block;margin-bottom:4px;background:none;padding:0;}
    .ti-or{font-size:11px;color:var(--text3);text-align:center;align-self:end;padding-bottom:10px;}
    .ti-apply{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text2);margin-top:2px;}
    .ti-rev{width:100%;border-collapse:collapse;font-size:12.5px;}
    .ti-rev th{text-align:left;font-size:10px;letter-spacing:.5px;text-transform:uppercase;color:var(--text3);padding:6px 8px;border-bottom:1px solid var(--border);position:sticky;top:-18px;background:#fff;}
    .ti-rev td{padding:8px;border-bottom:1px solid #f1f5f9;vertical-align:top;}
    .ti-rev td small{display:block;color:var(--text2);font-size:11px;}
    .ti-rev tr{cursor:pointer;}
    .ti-rev tr:hover td{background:#fafbff;}
    .ti-rev .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap;}
    .ti-rev .miss{color:#b91c1c;font-weight:600;}
    .ti-rev tfoot td{font-weight:700;border-top:2px solid var(--border);border-bottom:0;cursor:default;}
    @media (max-width:760px){.ti-facts{grid-template-columns:repeat(2,minmax(0,1fr));}.ti-grid{grid-template-columns:1fr;}}
</style>
@endsection
@section('main-content')
@if(request('embed') === 'cf')
{{-- inside a contract flight's Import PDF overlay: only the form, no ERP shell --}}
<style>#sidebar,header.header,.headline-ticker,.ticker-wrap,#tkLoader,.ex-toolbar,.ex-navband,footer{display:none!important;}#mainContentWrapper{margin-left:0!important;padding-left:0!important;width:100%!important;}main{padding:6px!important;}body{overflow:auto;}</style>
@endif
@include('layout.ticketing-tabs')
@php
  // Import PDF (2026-10-05) — offered on a plain entry only, and only while
  // its reader route is registered: a server whose route cache lags shows
  // the page exactly as before, without the button.
  $tiReadUrl = null;
  if (! ($opsMode ?? false) && ! $bookingToConvert) {
    try {
      if (\Illuminate\Support\Facades\Route::has('role.ticket-import.read')) {
        $tiReadUrl = route('role.ticket-import.read', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]);
      }
    } catch (\Throwable $e) {
      $tiReadUrl = null;
    }
  }
  // Ticket Import → CSV / Excel (owner, 2026-10-19) — same guard; not in a contract flight's
  // PDF overlay, which stays exactly as it was.
  $tiSheetUrl = $tiTemplateUrl = null;
  if ($tiReadUrl && request('embed') !== 'cf') {
    try {
      if (\Illuminate\Support\Facades\Route::has('role.ticket-import.sheet') && class_exists(\App\Services\TicketImport\TicketSheetImporter::class)) {
        $tiSheetUrl = route('role.ticket-import.sheet', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]);
        if (\Illuminate\Support\Facades\Route::has('role.ticket-import.template')) {
          $tiTemplateUrl = route('role.ticket-import.template', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]);
        }
      }
    } catch (\Throwable $e) {
      $tiSheetUrl = $tiTemplateUrl = null;
    }
  }
@endphp

<div style="padding:4px 0 20px;" @if($tiReadUrl && request()->boolean('import')) class="ti-import-tab" @endif>

  {{-- Page header --}}
  {{-- Hidden on the Ticket Import tab (owner, 2026-10-19: "dont need this part") — the tab band already
       names the page and links Ticket Orders. Kept in the DOM: switchOpTab() writes its title. --}}
  <div class="tv-page-head" @if($tiReadUrl && request()->boolean('import')) style="display:none" @endif>
    <div class="tv-page-logo">✈</div>
    <div>
      <div class="tv-page-title" id="ds-page-title">{{ $bookingToConvert ? 'Confirm Booking & Create Purchase' : ($opsMode ? 'Ticket Services' : 'Ticket Entry') }}</div>
      <div class="tv-page-sub" id="ds-page-sub">{{ $bookingToConvert ? 'Booking '.$bookingToConvert->booking_reference.' · verify final cost and payment before saving' : ($opsMode ? 'Refund · Re-Issue · Void · EMD / Ancillary' : 'Purchase or book each ticket — its status decides what is written') }}</div>
    </div>
    <div class="tv-page-actions">
      @if($tiReadUrl)
      <button type="button" class="erp-btn btn-ghost" id="tiOpenBtn" onclick="tiToggle(true)"><i class="fas fa-file-import"></i> Ticket Import</button>
      @endif
      <a href="{{ route('role.ticket-purchase.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
         class="erp-btn btn-ghost"><i class="fas fa-layer-group"></i> Ticket Orders</a>
    </div>
  </div>

  {{-- Tab bar. Two doors to this view (owner, 2026-09-21): Ticket Orders →
       Add Entry shows only the Ticket Entry tab; the sidebar's "Ticket Services"
       (?ops=1) shows Refund / Re-Issue / Void / EMD and no entry tab. Same
       forms, same endpoints — only which tabs are offered differs. --}}
  @php $opsMode = (bool) ($opsMode ?? false); $opsFirst = in_array(request('tab'), ['refund','reissue','void','emd']) ? request('tab') : 'refund'; @endphp
  {{-- A new passenger (passport holder "+") becomes a client in Sold to (owner, 2026-10-25): only with the Customer screen's own permission, and only once its route is registered (a lagging server just skips it). --}}
  @php $canCreateClient = (bool) auth()->user()?->can('create customer') && \Illuminate\Support\Facades\Route::has('role.ticket-quick-create-client'); @endphp
  <div class="ops-tab-bar" @if(!$opsMode) style="display:none" @endif>
    <div class="op-tab active" onclick="switchOpTab('sale',this,'Ticket Entry','Purchase or book each ticket — its status decides what is written')" @if($opsMode) style="display:none" @endif>
      <div class="tab-dot dot-sale"></div> Ticket Entry
    </div>
    <div class="op-tab" data-tab="refund" onclick="switchOpTab('refund',this,'Ticket Refund','Process passenger refund &amp; airline recovery')">
      <div class="tab-dot dot-refund"></div> Refund
    </div>
    <div class="op-tab" data-tab="reissue" onclick="switchOpTab('reissue',this,'Ticket Re-Issue','Change flight, route or date on existing ticket')">
      <div class="tab-dot dot-reissue"></div> Re-Issue
    </div>
    <div class="op-tab" data-tab="void" onclick="switchOpTab('void',this,'Ticket Void','Cancel ticket within void window (24 hrs)')">
      <div class="tab-dot dot-void"></div> Void
    </div>
    <div class="op-tab" data-tab="emd" onclick="switchOpTab('emd',this,'EMD / Ancillary','Electronic miscellaneous document &amp; extras')">
      <div class="tab-dot dot-emd"></div> EMD / Ancillary
    </div>
  </div>

  {{-- ============================================================
       TAB 1 — DIRECT SALE
  ============================================================ --}}
  <div class="tab-content active" id="tab-sale">
    @if($tiReadUrl)
    {{-- Ticket Import (owner, 2026-10-19: "rename to ticket import … two entry options, import PDF,
         import CSV / Excel"): each ticket PDF or spreadsheet row becomes filled cards below; nothing is saved until Save. --}}
    <div class="ti-panel" id="tiPanel" @if(!request()->boolean('import')) hidden @endif>
      <div class="ti-head">
        <span class="ti-title"><i class="fas fa-file-import"></i> Ticket Import</span>
        <span class="ti-sub">Drop files here or anywhere on this page — each passenger becomes a card below; nothing is saved until Save.</span>
        <button type="button" class="ti-x" onclick="tiToggle(false)" title="Close">✕</button>
      </div>
      <div class="ti-drops">
        <label class="ti-drop" id="tiDrop">
          <input type="file" id="tiFiles" accept=".pdf,application/pdf" multiple hidden>
          <span class="ti-kind pdf">PDF</span>
          <span class="ti-drop-txt">
            <b class="ti-drop-t">Import PDF</b>
            <span>Drop ticket PDFs or click to choose</span>
            <small title="Several at once · Galileo · Sabre · US-Bangla · Air Arabia · IndiGo · flydubai · Jazeera · portal orders · scanned PDFs · any other airline. Each PDF is attached to its card; the seller you chose last time for that airline is filled in.">Several at once · Galileo · Sabre · US-Bangla · Air Arabia · IndiGo · flydubai · Jazeera · portal orders · scanned PDFs · any other airline</small>
          </span>
        </label>
        @if($tiSheetUrl)
        <label class="ti-drop" id="tiSheetDrop">
          <input type="file" id="tiSheetFiles" accept=".csv,.xlsx,.xls" multiple hidden>
          <span class="ti-kind xls">XLS</span>
          <span class="ti-drop-txt">
            <span class="ti-drop-h"><b class="ti-drop-t">Import CSV / Excel</b>@if($tiTemplateUrl)<a class="ti-tpl" href="{{ $tiTemplateUrl }}" download data-no-prefetch><i class="fas fa-download"></i> Daily sheet template</a>@endif</span>
            <span>Drop the day's sheet or click to choose</span>
            <small title="One row per ticket · Excel or CSV · columns found by their heading, in any order: Pax Name · PNR · Ticket Number · Route · Flight Date · Vendor Name · Vendor Price · Agent Name · Agent Price">One row per ticket · headings in any order: Pax Name · PNR · Ticket Number · Route · Flight Date · Vendor Name · Vendor Price · Agent Name · Agent Price</small>
          </span>
        </label>
        @endif
      </div>
      <div class="ti-list" id="tiList"></div>
      <div class="ti-reopen" id="tiReopen" hidden><button type="button" class="erp-btn btn-primary" onclick="tiWizOpen()"><i class="fas fa-list-check"></i> Complete details &amp; confirm</button></div>
    </div>
    {{-- Guided completion: closes only on the ✕ (modals-close-only-on-the-cross) --}}
    <div class="ti-wiz" id="tiWiz" role="dialog" aria-modal="true" aria-labelledby="tiWizTitle">
      <div class="ti-wiz-box">
        <div class="ti-wiz-hd">
          <div><div class="t" id="tiWizTitle">Complete imported tickets</div><div class="s" id="tiWizSub"></div></div>
          <div class="ti-wiz-dots" id="tiWizDots"></div>
          <button type="button" class="ti-x" onclick="tiWizClose()" title="Close">✕</button>
        </div>
        <div class="ti-wiz-body" id="tiWizBody"></div>
        <div class="ti-wiz-ft" id="tiWizFt"></div>
      </div>
    </div>
    @endif
    <form id="directSaleForm"
          action="{{ route('role.ticket-direct-sale.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
          method="POST" enctype="multipart/form-data">
      @csrf
      @if($bookingToConvert)<input type="hidden" name="booking_id" value="{{ $bookingToConvert->id }}">@endif

      @if($bookingToConvert)
      <div class="ds-alert warning"><i class="fas fa-exchange-alt"></i><span><strong>Converting {{ $bookingToConvert->booking_reference }} to a purchase.</strong> Review the final cost, paid amount, bank/portal and due date. The booking will be confirmed only after this purchase saves successfully.</span></div>
      @endif

      <div class="ds-entry-grid"><div class="ds-entry-main">
      {{-- Passengers & Tickets --}}
      <div class="ds-section">
        <div class="ds-section-h">
          <div class="ds-sec-dot" style="background:#818cf8"></div>
          <span class="ds-sec-title">Passengers &amp; Tickets</span>
          <span class="ds-sec-sub">(one row per passenger — pick from Manage Tickets)</span>
          <div class="right ds-head-tools">
            {{-- currency is shared by every ticket on this entry (was the "Purchase Header" card) --}}
            <div class="ds-head-currency">
              <label class="ds-field-lbl">Currency <span class="req">*</span></label>
              <select name="currency" required class="select2 ds-field-select" style="width:100%">
                <option value="BDT" selected>BDT (৳)</option>
                <option value="USD">USD ($)</option>
                <option value="EUR">EUR (€)</option>
                <option value="SAR">SAR</option>
                <option value="AED">AED</option>
              </select>
            </div>
            <button type="button" class="ds-add-pax" id="addPassengerBtn" @if($bookingToConvert) style="display:none" @endif>+ Add Passenger</button>
          </div>
        </div>
        <div class="ds-section-body">
          <div id="passengerContainer">
            <div class="ds-pax-card" data-pax="1">
              <div class="ds-pax-h">
                <span class="ds-pax-badge" data-n="1">Passenger 1</span>
                <div class="ds-status-wrap">
                  <label class="ds-field-lbl">Ticket status</label>
                  <select name="passengers[0][status]" class="select2 ds-field-select pax-status" onchange="onStatusChange(this)" style="width:100%"><option value="purchased" selected>Purchased</option><option value="booked">Booked (hold)</option><option value="pending">Pending</option><option value="draft">Draft</option><option value="cancelled">Cancelled</option></select>
                </div>
                <button type="button" class="ds-pax-remove" onclick="removePassenger(this)" style="display:none">✕ Remove</button>
              </div>
              <div class="ds-zone">
                <div class="ds-zone-h"><i class="fas fa-user"></i> Traveller &amp; route</div>
                <div class="ds-row cols-6">
                  <div class="span-2">
                    <label class="ds-field-lbl">Passport Holder <span class="req">*</span></label>
                    <div style="display:flex;gap:6px;align-items:center;">
                      <select name="passengers[0][passport_holder_id]" required class="select2 ds-field-select ph-select" style="flex:1;min-width:0;">
                        <option value="">— Select passenger —</option>
                        @foreach ($passport_holders as $ph)
                          <option value="{{ $ph->id }}">{{ $ph->name }}{{ $ph->passport_no ? " — " . $ph->passport_no : "" }}</option>
                        @endforeach
                      </select>
                      <button type="button" class="ph-create-btn" onclick="openPhModal(this)" title="Create new passport holder">+</button>
                    </div>
                  </div>
                  <div class="span-2">
                    <label class="ds-field-lbl">Ticket Route <span class="req">*</span></label>
                    <div style="display:flex;gap:6px;align-items:center;">
                      <select name="passengers[0][ticket_id]" required class="select2 ds-field-select ticket-select" style="flex:1;min-width:0;" onchange="onTicketChange(this,0)">
                        <option value="">— Select from Manage Tickets —</option>
                        @foreach ($tickets as $t)
                          <option value="{{ $t->id }}" data-price="{{ $t->price }}" data-vendor="{{ $t->vendor_id }}" data-portal="{{ $t->portal_id }}" data-legs='@json($t->legsArray())' data-trip="{{ $t->isRoundTrip() ? 'two-way' : '' }}" data-ff="{{ strtolower($t->from_airport?->country?->code ?? '') }}" data-tf="{{ strtolower($t->to_airport?->country?->code ?? '') }}">{{ $t->title }}</option>
                        @endforeach
                      </select>
                      <button type="button" class="ph-create-btn ds-route-btn" onclick="openTicketModal(this)" title="Create new ticket route">+</button>
                    </div>
                  </div>
                  <div>
                    <label class="ds-field-lbl">Trip Type <span class="req">*</span></label>
                    <select name="passengers[0][trip_type]" required class="select2 ds-field-select ds-trip" style="width:100%" onchange="onTripChange(this)">
                      <option value="one-way">One-way</option>
                      <option value="two-way">Round Trip</option>
                      <option value="multi-city">Multi-city</option>
                    </select>
                    <input type="hidden" name="passengers[0][ticket_type]" value="air">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Class <span class="req">*</span></label>
                    <select name="passengers[0][ticket_class]" required class="select2 ds-field-select" style="width:100%">
                      <option value="economy">Economy</option>
                      <option value="business">Business</option>
                      <option value="first">First Class</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="ds-zone">
                <div class="ds-zone-h"><i class="fas fa-ticket-alt"></i> Ticket &amp; seller</div>
                <div class="ds-row cols-6">
                  <div>
                    <label class="ds-field-lbl">PNR <span class="req">*</span></label>
                    <input type="text" name="passengers[0][ticket_no]" required placeholder="e.g. ABCD12" class="ds-field-input highlight ds-pnr">
                  </div>
                  <div>
                    <label class="ds-field-lbl">E-ticket No.</label>
                    <input type="text" name="passengers[0][name_ticket_no]" maxlength="100" placeholder="072-2345678901" class="ds-field-input ds-eticket" oninput="runChecks(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Validating carrier</label>
                    <select name="passengers[0][validating_carrier_id]" class="select2 ds-field-select ds-vc" style="width:100%">
                      <option value="">— Airline that issues —</option>
                      @foreach ($airlines as $al)
                        <option value="{{ $al->id }}">{{ $al->name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div>
                    <label class="ds-field-lbl">Issue Date <span class="req">*</span></label>
                    <input type="date" name="passengers[0][purchase_date]" required value="{{ date('Y-m-d') }}" class="ds-field-input ds-issue" onchange="runChecks(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Vendor</label>
                    <select name="passengers[0][vendor_id]" class="select2 ds-field-select pax-vendor" style="width:100%" onchange="onVendorChange(this)">
                      <option value="">— Select vendor —</option>
                      @foreach ($vendors as $v)
                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div>
                    <label class="ds-field-lbl">Portal</label>
                    <select name="passengers[0][portal_id]" class="select2 ds-field-select pax-portal" style="width:100%" onchange="onPortalChange(this)">
                      <option value="">— Select portal —</option>
                      @foreach ($portals as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
              <div class="ds-zone">
                <div class="ds-zone-h"><i class="fas fa-plane-departure"></i> Flight</div>
                <div class="ds-row cols-6">
                  <div>
                    <label class="ds-field-lbl">Flight No.</label>
                    <input type="text" name="passengers[0][flight_no]" maxlength="40" placeholder="GF 249" class="ds-field-input ds-upper">
                  </div>
                  <div class="span-2">
                    <label class="ds-field-lbl">Departure</label>
                    <input type="datetime-local" name="passengers[0][departure_at]" class="ds-field-input ds-dep" onchange="runChecks(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Arrival</label>
                    <input type="datetime-local" name="passengers[0][arrival_at]" class="ds-field-input">
                  </div>
                  <div>
                    <label class="ds-field-lbl">RBD</label>
                    <input type="text" name="passengers[0][rbd]" maxlength="4" placeholder="Q" class="ds-field-input ds-upper" title="Booking class letter (fare bucket)">
                  </div>
                  <div>
                    @if(\App\Services\TicketBaggage::ready())
                    <label class="ds-field-lbl">Free baggage KG</label>
                    <input type="number" step="0.01" min="0" name="passengers[0][free_baggage_kg]" class="ds-field-input">
                    <input type="hidden" name="passengers[0][baggage]">
                    @else
                    <label class="ds-field-lbl">Baggage</label>
                    <input type="text" name="passengers[0][baggage]" maxlength="40" placeholder="30 kg + 7 kg" class="ds-field-input">
                    @endif
                  </div>
                </div>
                {{-- Return + Family on ONE row (owner, 2026-10-15): Return Flight · Return Departure ·
                     Cancel return by · Family ticket at the right end. A one-way ticket (a separate
                     return ticket) shows only the Family pair; before the Cancel return migration
                     the row is the return line alone, as it was. --}}
                @php $dsRetOn = \App\Services\ReturnCancel::ready(); @endphp
                <div class="ds-row ds-return-row{{ $dsRetOn ? ' ds-has-fam' : '' }}" @unless($dsRetOn) style="display:none" @endunless>
                  <div class="ds-ret-f">
                    <label class="ds-field-lbl" title="Return flight number">Return Flight</label>
                    <input type="text" name="passengers[0][return_flight_no]" maxlength="40" placeholder="GF 250" class="ds-field-input ds-upper">
                  </div>
                  <div class="ds-ret-f">
                    <label class="ds-field-lbl">Return Departure</label>
                    <input type="datetime-local" name="passengers[0][return_at]" class="ds-field-input ds-ret" onchange="runChecks(this)">
                  </div>
                  @if($dsRetOn)
                  <div class="ds-fam-by">
                    <label class="ds-field-lbl" title="The airline's deadline to cancel the return. Reminders go to whoever entered the ticket — 3 days before, 1 day before, and on the day">Cancel return by</label>
                    <input type="text" data-dt name="passengers[0][return_cancel_by]" class="ds-field-input" placeholder="date, time optional" autocomplete="off">
                  </div>
                  <span class="ds-fam-tick" title="A family passenger (family flight / family visa): the return he does not use can be cancelled later — Ticket Orders → Change → Cancel return. Round trip: only its return half. A separate return ticket: tick it on that ticket's card."><input type="checkbox" name="passengers[0][family_return]" value="1" aria-label="Family ticket" onchange="dsFamilyToggle(this)"> Family ticket</span>
                  @else
                  <div class="ds-return-note"><i class="fas fa-rotate-left"></i> Return leg of the same ticket</div>
                  @endif
                </div>
              </div>
              @include('dashboard.ticket_direct_sales.partials.baggage-fields', ['prefix' => 'passengers[0]'])
              <div class="ds-zone">
                <div class="ds-zone-h"><i class="fas fa-coins"></i> Fare &amp; payment</div>
                <div class="ds-row cols-6">
                  <div>
                    <label class="ds-field-lbl">Base Fare</label>
                    <input type="number" name="passengers[0][base_fare]" step="0.01" min="0" placeholder="0.00" class="ds-field-input ds-fare" oninput="calcFare(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Taxes</label>
                    <input type="number" name="passengers[0][tax_amount]" step="0.01" min="0" placeholder="0.00" class="ds-field-input ds-tax" oninput="calcFare(this)">
                  </div>
                  <div class="span-2">
                    <label class="ds-field-lbl">Tax codes</label>
                    <input type="text" name="passengers[0][tax_breakdown]" maxlength="255" placeholder="YQ 12000, BD 500, E5 300" class="ds-field-input ds-taxcodes" oninput="calcTaxCodes(this)" title="Codes and amounts; their sum fills Taxes">
                  </div>
                  <div>
                    <label class="ds-field-lbl" title="Commission %">Comm. %</label>
                    <input type="number" name="passengers[0][commission_pct]" step="0.01" min="0" max="100" placeholder="0" class="ds-field-input ds-compct" oninput="calcFare(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl" title="Commission amount">Comm. ৳</label>
                    <input type="number" name="passengers[0][commission_amount]" step="0.01" min="0" placeholder="0.00" class="ds-field-input ds-comamt" oninput="calcFare(this)">
                  </div>
                </div>
                <div class="ds-row cols-6">
                  <div>
                    <label class="ds-field-lbl">Net Cost <span class="req">*</span></label>
                    <input type="number" name="passengers[0][cost_amount]" required step="0.01" placeholder="Type net cost" class="ds-field-input pax-cost ds-net" oninput="this.dataset.edited=this.value!==''?'1':'';recalcSummary()" title="Type the net cost directly, or fill base fare / taxes / commission to work it out">
                  </div>
                  <div>
                    <label class="ds-field-lbl" title="What the agent pays for this ticket">Agent Price <span class="req ds-sale-required" hidden>*</span></label>
                    <input type="number" name="passengers[0][sale_price]" step="0.01" min="0" class="ds-field-input ds-sale" oninput="calcProfit(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Cost Paid</label>
                    <input type="number" name="passengers[0][purchase_paid]" step="0.01" placeholder="0.00" class="ds-field-input ds-paid" oninput="recalcSummary()">
                  </div>
                  <div>
                    <label class="ds-field-lbl" title="Agent price − net cost">Profit</label>
                    <input type="text" class="ds-field-input ds-profit" readonly tabindex="-1">
                  </div>
                  <div class="cost-sched-wrap">
                    <label class="ds-field-lbl">Cost Due Date</label>
                    <input type="date" name="passengers[0][cost_due_date]" class="ds-field-input" title="Payable schedule date">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Attachment</label>
                    <input type="file" name="passengers[0][attachment]" accept=".pdf,.jpg,.jpeg,.png" class="ds-field-input ds-file">
                  </div>
                </div>
                <div class="ds-row cols-6 ds-sale-row">
                  <div>
                    <label class="ds-field-lbl" title="The agent or customer this ticket is sold to — billed on Save through Manage Sales">Sold to <span class="req ds-buyer-required" hidden>*</span></label>
                    <select name="passengers[0][sale_client_id]" class="select2 ds-field-select ds-sale-to" style="width:100%" onchange="calcProfit(this)"><option value="">— Not sold yet —</option>@include('dashboard.ticket_direct_sales.partials.buyer-options')</select>
                  </div>
                  <div>
                    <label class="ds-field-lbl">Cost Bank</label>
                    <select name="passengers[0][purchase_bank_id]" class="select2 ds-field-select pax-cost-bank" style="width:100%">
                      <option value="">— Select bank —</option>
                      @include('partials.bank-options', ['banks' => $banks, 'selected' => null])
                    </select>
                  </div>
                  <div>
                    <label class="ds-field-lbl">Pay Status <span class="req">*</span></label>
                    <select name="passengers[0][purchase_pay_status]" required class="select2 ds-field-select" style="width:100%">
                      <option value="due">Due</option><option value="partial">Partial</option><option value="paid">Paid</option>
                    </select>
                  </div>
                  <div class="span-2 ds-sale-note"></div>
                </div>
              </div>
              <div class="ds-card-warn" aria-live="polite"></div>
    <div class="ds-book-box" style="display:none">
      <div class="ds-book-hd"><i class="fas fa-clock"></i> Hold — the ticket is reserved, not paid. Nothing is posted to the ledger until it is marked Purchased. <small>Net cost above = quoted amount · departure is in the Flight zone.</small></div>
      <div class="ds-row cols-4">
        <div><label class="ds-field-lbl">Booking date &amp; time</label><input type="datetime-local" name="passengers[0][booking_at]" class="ds-field-input" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
        <div><label class="ds-field-lbl" style="color:#d97706;">Hold expires at <span class="req">*</span></label><input type="datetime-local" name="passengers[0][expires_at]" class="ds-field-input warning pax-expires" value="{{ now()->addHours(6)->format('Y-m-d\TH:i') }}"><span class="ds-book-hint">Defaults to 6 hours from now; set the airline's real deadline.</span></div>
      </div>
      <div class="ds-row cols-4">
        <div class="span-2"><label class="ds-field-lbl">Notify boss / user</label><select name="passengers[0][notification_recipient_id]" class="select2 ds-field-select" style="width:100%"><option value="">— nobody —</option>@foreach($bookingRecipients as $recipient)<option value="{{$recipient->id}}" @selected($bookingBoss?->id===$recipient->id)>{{$recipient->name}}{{$recipient->name==='Md Mohsin'?' (Boss)':''}} — {{$recipient->email?:$recipient->phone}}</option>@endforeach</select></div>
        <div class="span-2"><label class="ds-field-lbl">Notification channels</label><div class="ds-book-ch">@foreach(['notify_in_app'=>'In-app bell','notify_email'=>'Email','notify_sms'=>'SMS'] as $key=>$label)<label><input type="hidden" name="passengers[0][{{$key}}]" value="0"><input type="checkbox" name="passengers[0][{{$key}}]" value="1" checked> {{$label}}</label>@endforeach</div><span class="ds-book-hint">Automatic reminders: 24 hours, 3 hours, 1 hour before and at the expiry time.</span></div>
      </div>
      <div class="ds-row cols-1"><div><label class="ds-field-lbl">Notes</label><textarea name="passengers[0][notes]" rows="2" class="ds-field-input" style="height:auto;min-height:60px;" placeholder="Airline instructions, fare rules or follow-up notes…"></textarea></div></div>
    </div>
              <div class="ds-pax-legs" style="margin-top:14px;border-top:1px solid #f1f0fa;padding-top:12px;">
                <div class="ds-legs-hd">
                  <i class="fas fa-route ic"></i> Route legs <small>— sectors of this ticket, priced per segment</small>
                  <button type="button" class="ds-add-pax" onclick="addLegRow(0)" title="Add a route leg">+ Add Leg</button>
                </div>
                <div id="legsContainer_0"></div>
              </div>
            </div>
          </div>
          <button type="button" class="ds-add-another" id="addAnotherPax" @if($bookingToConvert) style="display:none" @endif>⊕ Add Another Passenger</button>
        </div>
      </div>

      </div>
      {{-- Summary rail (2026-10-04): totals, warnings and Save stay in view while typing. --}}
      <aside class="ds-rail">
        <div class="ds-rail-card">
          <div class="ds-rail-h"><i class="fas fa-receipt"></i> Entry summary</div>
          <div class="ds-rail-row"><span>Tickets</span><b id="sumCount">1</b></div>
          <div class="ds-rail-row"><span>Base fare</span><b id="sumFare">৳0.00</b></div>
          <div class="ds-rail-row"><span>Taxes</span><b id="sumTax">৳0.00</b></div>
          <div class="ds-rail-row"><span>Commission</span><b id="sumCom" class="g">৳0.00</b></div>
          <div class="ds-rail-row is-strong"><span>Net cost</span><b id="sumCost">৳0.00</b></div>
          <div class="ds-rail-row"><span>Agent price</span><b id="sumSale">—</b></div>
          <div class="ds-rail-row is-strong"><span>Profit</span><b id="sumProfit">—</b></div>
          <div class="ds-rail-sep"></div>
          <div class="ds-rail-row"><span>Paid now</span><b id="sumPaid">৳0.00</b></div>
          <div class="ds-rail-row"><span>Due to sellers</span><b id="sumDue" class="r">৳0.00</b></div>
          {{-- What Save will post (owner, 2026-10-07: the summary + the Change dialog's
               "What will be posted" in one rail). Read from the cards; posts nothing. --}}
          <div class="ds-post" id="railPost"></div>
          <div class="ds-rail-warn" id="railWarn"></div>
          <div class="ds-rail-act">
            <button type="button" id="submitBtn" class="erp-btn btn-green-s">{{ $bookingToConvert ? '✓ Confirm & Create Purchase' : '💾 Save Purchase(s)' }}</button>
            <a href="{{ route('role.ticket-direct-sale.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="erp-btn btn-ghost">✕ Cancel</a>
          </div>
        </div>
      </aside>
      </div>
    </form>
  </div>

  {{-- ============================================================
       TAB 2 — REFUND
  ============================================================ --}}
  <div class="tab-content" id="tab-refund">
    <form id="refundForm"
          action="{{ route('role.ticket-refund.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
          method="POST" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="currency" value="BDT">

      <div class="ds-alert danger">⚠ Refund will reverse the original sale. Ensure airline has confirmed the refund amount before proceeding.</div>

      <div class="ds-section">
        <div class="ds-section-h"><div class="ds-sec-dot" style="background:#f87171"></div><span class="ds-sec-title">Refund Header</span></div>
        <div class="ds-section-body">
          <div class="ds-row cols-4">
            <div>
              <label class="ds-field-lbl">Agent <span class="req">*</span></label>
              <select name="agent_id" required class="select2 ds-field-select" style="width:100%">
                <option value="">— Select agent —</option>
                @foreach ($agents as $ag)<option value="{{ $ag->id }}">{{ $ag->name }}</option>@endforeach
              </select>
            </div>
            <div><label class="ds-field-lbl">Refund Date <span class="req">*</span></label><input type="date" name="refund_date" required value="{{ date('Y-m-d') }}" class="ds-field-input"></div>
            <div><label class="ds-field-lbl">Original Invoice Ref <span class="req">*</span></label><input type="text" name="original_invoice" required id="refInvoiceInput" placeholder="INV000007" class="ds-field-input danger" onblur="lookupInvoice('refund')"></div>
            <div>
              <label class="ds-field-lbl">Refund Status <span class="req">*</span></label>
              <select name="status" required class="select2 ds-field-select" style="width:100%">
                <option value="confirm">Confirm</option><option value="processing">Processing</option><option value="pending_airline">Pending Airline</option><option value="completed">Completed</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="ds-section">
        <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fca5a5"></div><span class="ds-sec-title">Original Ticket Info</span><span class="ds-sec-sub">(auto-filled from invoice ref)</span></div>
        <div class="ds-section-body">
          <div class="ds-orig-ref" id="refOrigInfo">
            <div>Passenger: <span class="ref-val" id="refPassenger">—</span></div>
            <div>PNR: <span class="ref-val" id="refPnr">—</span></div>
            <div>Route: <span class="ref-val" id="refRoute">—</span></div>
            <div>Original Cost: <span class="ref-val" id="refOrgCostDisplay">—</span></div>
            <div>Original Sale: <span class="ref-val" id="refOrgSaleDisplay">—</span></div>
            <span class="ds-status-tag confirm" id="refStatusTag">Not loaded</span>
          </div>
          <div class="ds-row cols-3">
            <div>
              <label class="ds-field-lbl">Vendor</label>
              <select name="vendor_id" id="refVendor" class="select2 ds-field-select" style="width:100%">
                <option value="">— Select vendor —</option>
                @foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
              </select>
            </div>
            <div>
              <label class="ds-field-lbl">Portal</label>
              <select name="portal_id" id="refPortal" class="select2 ds-field-select" style="width:100%">
                <option value="">— Select portal —</option>
                @foreach ($portals as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
              </select>
            </div>
            <div><label class="ds-field-lbl">Refund PNR / Ref No</label><input type="text" name="refund_ref_no" id="refPnrInput" placeholder="Enter refund ref" class="ds-field-input danger" onblur="lookupByRefPnr()"></div>
          </div>
        </div>
      </div>

      <div class="ds-section">
        <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fbbf24"></div><span class="ds-sec-title">Refund Calculation</span><span class="ds-sec-sub">(per ticket)</span></div>
        <div class="ds-section-body">
          <div class="ds-row cols-3">
            <div><label class="ds-field-lbl">Original Ticket Cost <span class="req">*</span></label><input type="number" name="org_cost" required placeholder="0.00" step="0.01" class="ds-field-input" id="rOrgCost" oninput="calcRefund()"></div>
            <div><label class="ds-field-lbl">Airline Refund Amount <span class="req">*</span></label><input type="number" name="airline_refund" required placeholder="0.00" step="0.01" class="ds-field-input success" id="rAirlineRefund" oninput="calcRefund()"></div>
            <div><label class="ds-field-lbl">Airline Penalty / Fee</label><input type="number" name="penalty" placeholder="0.00" step="0.01" class="ds-field-input warning" id="rPenalty" oninput="calcRefund()"></div>
          </div>
          <div class="ds-row cols-3">
            <div><label class="ds-field-lbl">Agent Service Charge</label><input type="number" name="service_charge" placeholder="0.00" step="0.01" class="ds-field-input warning" id="rServiceCharge" oninput="calcRefund()"></div>
            <div><label class="ds-field-lbl">Original Sale Price <span class="req">*</span></label><input type="number" name="org_sale" required placeholder="0.00" step="0.01" class="ds-field-input" id="rOrgSale" oninput="calcRefund()"></div>
            <div><label class="ds-field-lbl">Net Refund to Customer <span class="req">*</span></label><input type="number" name="net_refund" required placeholder="0.00" step="0.01" class="ds-field-input danger" id="rNetRefund" readonly></div>
          </div>
          <div class="ds-calc">
            <div class="ds-calc-row"><span class="sec">Airline refund</span><span>৳ <span id="rCalcAirline">0.00</span></span></div>
            <div class="ds-calc-row"><span class="sec">Airline penalty</span><span style="color:#d97706">− ৳ <span id="rCalcPenalty">0.00</span></span></div>
            <div class="ds-calc-row"><span class="sec">Agent service charge</span><span style="color:#d97706">− ৳ <span id="rCalcService">0.00</span></span></div>
            <div class="ds-calc-row total"><span>Net refund to customer</span><span style="color:#dc2626">৳ <span id="rCalcNet">0.00</span></span></div>
          </div>
        </div>
      </div>

      <div class="ds-summary-bar">
        <div class="ds-sum-cell"><div class="ds-sum-label">Total Airline Refund</div><div class="ds-sum-value sale"><span class="taka">৳</span> <span id="rSumAirline">0.00</span></div></div>
        <div class="ds-sum-cell"><div class="ds-sum-label">Total Penalty</div><div class="ds-sum-value penalty"><span class="taka">৳</span> <span id="rSumPenalty">0.00</span></div></div>
        <div class="ds-sum-cell"><div class="ds-sum-label">Net Refund to Customer</div><div class="ds-sum-value refund"><span class="taka">৳</span> <span id="rSumNet">0.00</span></div></div>
      </div>

      <div class="ds-section">
        <div class="ds-section-h"><div class="ds-sec-dot" style="background:#f87171"></div><span class="ds-sec-title">Refund Payment to Customer / Agent</span></div>
        <div class="ds-section-body">
          <div class="ds-row cols-3">
            <div><label class="ds-field-lbl">Refund Amount Paid</label><input type="number" name="paid_amount" id="rPaidAmount" placeholder="0.00" step="0.01" class="ds-field-input" oninput="calcRefundDue()"></div>
            <div>
              <label class="ds-field-lbl">Payment Method</label>
              <select name="pay_method" class="select2 ds-field-select" style="width:100%"><option value="">— Select method —</option><option value="cash">Cash</option><option value="bkash">bKash</option><option value="nagad">Nagad</option><option value="bank_transfer">Bank Transfer</option><option value="card_reversal">Card Reversal</option></select>
            </div>
            <div>
              <label class="ds-field-lbl">Payment Status <span class="req">*</span></label>
              <select name="payment_status" required class="select2 ds-field-select" style="width:100%"><option value="due">Due</option><option value="partial">Partial</option><option value="paid">Paid</option></select>
            </div>
          </div>
          <div class="ds-row cols-2">
            <div>
              <label class="ds-field-lbl">Paid from Account (Bank)</label>
              <select name="bank_id" class="select2 ds-field-select" style="width:100%"><option value="">— Select bank —</option>@include('partials.bank-options', ['banks' => $banks, 'selected' => null])</select>
            </div>
            <div><label class="ds-field-lbl">Attachment / Proof</label><input type="file" name="attachment" class="ds-field-input"></div>
          </div>
        </div>
        <div class="ds-due-line"><span class="ds-due-label">Refund Due to Customer:</span><span class="ds-due-amount" id="rDueDisplay">৳ 0.00</span></div>
      </div>

      <div class="ds-action-bar">
        <button type="button" class="erp-btn btn-ghost" onclick="document.getElementById('refundForm').reset()">✕ Cancel</button>
        <button type="submit" class="erp-btn btn-danger" id="refundSubmitBtn">↩ Process Refund</button>
      </div>
    </form>
  </div>

  {{-- ============================================================
       TAB 3 — RE-ISSUE
  ============================================================ --}}
  <div class="tab-content" id="tab-reissue">
    <form id="reissueForm"
          action="{{ route('role.ticket-reissue.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
          method="POST" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="currency" value="BDT">

      <div class="ds-alert info">ℹ Re-issue changes the flight / date / route. Both old and new ticket details must be recorded for full audit trail.</div>

      <div class="ds-steps">
        <div class="ds-step done">1 — Original Ticket</div>
        <div class="ds-step active">2 — New Ticket Details</div>
        <div class="ds-step">3 — Charges &amp; Pricing</div>
        <div class="ds-step">4 — Payment</div>
      </div>

      <div class="ds-section">
        <div class="ds-section-h"><div class="ds-sec-dot" style="background:#93c5fd"></div><span class="ds-sec-title">Original Ticket Reference</span></div>
        <div class="ds-section-body">
          <div class="ds-row cols-4">
            <div>
              <label class="ds-field-lbl">Agent <span class="req">*</span></label>
              <select name="agent_id" required class="select2 ds-field-select" style="width:100%"><option value="">— Select agent —</option>@foreach ($agents as $ag)<option value="{{ $ag->id }}">{{ $ag->name }}</option>@endforeach</select>
            </div>
            <div><label class="ds-field-lbl">Re-Issue Date <span class="req">*</span></label><input type="date" name="reissue_date" required value="{{ date('Y-m-d') }}" class="ds-field-input"></div>
            <div><label class="ds-field-lbl">Original Invoice <span class="req">*</span></label><input type="text" name="original_invoice" required id="riInvoiceInput" placeholder="INV000007" class="ds-field-input" style="border-color:#3b82f6;" onblur="lookupInvoice('reissue')"></div>
            <div><label class="ds-field-lbl">Original PNR</label><input type="text" id="riOrgPnrDisplay" class="ds-field-input" readonly placeholder="— Auto-filled —"></div>
          </div>
          <div class="ds-orig-ref" style="margin-top:14px;" id="riOrigInfo">
            <div>Passenger: <span class="ref-val" id="riPassenger">—</span></div>
            <div>Old Route: <span class="ref-val" id="riRoute">—</span></div>
            <div>Old Date: <span class="ref-val" id="riDate">—</span></div>
            <div>Original Fare: <span class="ref-val" id="riOrigFare">—</span></div>
            <span class="ds-status-tag reissue" id="riStatusTag">Not loaded</span>
          </div>
        </div>
      </div>

      <div class="ds-section">
        <div class="ds-section-h"><div class="ds-sec-dot" style="background:#60a5fa"></div><span class="ds-sec-title">New Ticket Details</span></div>
        <div class="ds-section-body">
          <div class="ds-row cols-5">
            <div>
              <label class="ds-field-lbl">New Ticket Route <span class="req">*</span></label>
              <select name="new_ticket_id" required class="select2 ds-field-select" style="width:100%;border-color:#3b82f6;"><option value="">— Select new route —</option>@foreach ($tickets as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach</select>
            </div>
            <div>
              <label class="ds-field-lbl">New Trip Type <span class="req">*</span></label>
              <select name="new_trip_type" required class="select2 ds-field-select" style="width:100%"><option value="one-way">One-way</option><option value="two-way">Round Trip</option></select>
            </div>
            <div>
              <label class="ds-field-lbl">New Class <span class="req">*</span></label>
              <select name="new_ticket_class" required class="select2 ds-field-select" style="width:100%"><option value="economy">Economy</option><option value="business">Business</option><option value="first">First Class</option></select>
            </div>
            <div><label class="ds-field-lbl">New PNR / Ticket No <span class="req">*</span></label><input type="text" name="new_ticket_no" required placeholder="Enter new PNR" class="ds-field-input" style="border-color:#3b82f6;"></div>
            <div><label class="ds-field-lbl">New Name Ticket No.</label><input type="text" name="new_name_ticket_no" maxlength="100" placeholder="e.g. 997-2345678901" class="ds-field-input"></div>
          </div>
          <div class="ds-row cols-4">
            <div>
              <label class="ds-field-lbl">New Vendor</label>
              <select name="new_vendor_id" class="select2 ds-field-select" style="width:100%"><option value="">— Select vendor —</option>@foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select>
            </div>
            <div>
              <label class="ds-field-lbl">New Portal</label>
              <select name="new_portal_id" class="select2 ds-field-select" style="width:100%"><option value="">— Select portal —</option>@foreach ($portals as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
            </div>
            <div><label class="ds-field-lbl">New Travel Date</label><input type="text" data-dt name="new_travel_date" class="ds-field-input"></div>
            <div><label class="ds-field-lbl">New Purchase Date <span class="req">*</span></label><input type="date" name="new_purchase_date" required value="{{ date('Y-m-d') }}" class="ds-field-input"></div>
          </div>
        </div>
      </div>

      <div class="ds-section">
        <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fbbf24"></div><span class="ds-sec-title">Re-Issue Charges &amp; Pricing</span></div>
        <div class="ds-section-body">
          <div class="ds-row cols-3">
            <div><label class="ds-field-lbl">Original Cost Price</label><input type="number" name="org_cost" placeholder="0.00" step="0.01" class="ds-field-input" id="riOrgCost" oninput="calcReissue()"></div>
            <div><label class="ds-field-lbl">Airline Re-Issue Penalty <span class="req">*</span></label><input type="number" name="penalty" required placeholder="0.00" step="0.01" class="ds-field-input warning" id="riPenalty" oninput="calcReissue()"></div>
            <div><label class="ds-field-lbl">Fare Difference (new − old) <span class="req">*</span></label><input type="number" name="fare_diff" required placeholder="0.00" step="0.01" class="ds-field-input" style="border-color:#3b82f6;" id="riFareDiff" oninput="calcReissue()"></div>
          </div>
          <div class="ds-row cols-3">
            <div><label class="ds-field-lbl">New Total Cost Price</label><input type="number" name="new_cost" required placeholder="0.00" step="0.01" class="ds-field-input success" id="riNewCost" readonly></div>
            <div><label class="ds-field-lbl">Agent Service Charge</label><input type="number" name="service_charge" placeholder="0.00" step="0.01" class="ds-field-input" id="riService" oninput="calcReissue()"></div>
            <div><label class="ds-field-lbl">New Sale Price <span class="req">*</span></label><input type="number" name="new_sale_price" required placeholder="0.00" step="0.01" class="ds-field-input sale-price" id="riNewSale" oninput="calcReissue()"></div>
          </div>
          <div class="ds-row cols-3">
            <div>
              <label class="ds-field-lbl">Who bears this re-issue?</label>
              <select name="bearer" id="riBearer" class="select2 ds-field-select" style="width:100%" onchange="calcReissue()">
                @foreach (\App\Models\TicketReissue::REISSUE_BEARERS as $key => $label)
                  <option value="{{ $key }}" {{ $key === 'agent' ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
              <div id="riBearerHint" style="font-size:10.5px;color:#0f766e;margin-top:4px;min-height:13px;"></div>
            </div>
          </div>
          <div class="ds-calc">
            <div class="ds-calc-row"><span class="sec">Airline penalty</span><span style="color:#d97706">৳ <span id="riCalcPenalty">0.00</span></span></div>
            <div class="ds-calc-row"><span class="sec">Fare difference</span><span>৳ <span id="riCalcFare">0.00</span></span></div>
            <div class="ds-calc-row"><span class="sec">Agent service</span><span>৳ <span id="riCalcService">0.00</span></span></div>
            <div class="ds-calc-row total"><span>Additional charge to customer</span><span style="color:#2563eb">৳ <span id="riCalcTotal">0.00</span></span></div>
          </div>
        </div>
      </div>

      <div class="ds-summary-bar">
        <div class="ds-sum-cell"><div class="ds-sum-label">New Cost Price</div><div class="ds-sum-value cost"><span class="taka">৳</span> <span id="riSumCost">0.00</span></div></div>
        <div class="ds-sum-cell"><div class="ds-sum-label">New Sale Price</div><div class="ds-sum-value sale"><span class="taka">৳</span> <span id="riSumSale">0.00</span></div></div>
        <div class="ds-sum-cell"><div class="ds-sum-label">Re-Issue Profit</div><div class="ds-sum-value profit"><span class="taka">৳</span> <span id="riSumProfit">0.00</span></div></div>
      </div>

      <div class="ds-section">
        <div class="ds-section-h"><div class="ds-sec-dot" style="background:#60a5fa"></div><span class="ds-sec-title">Additional Payment from Agent</span></div>
        <div class="ds-section-body">
          <div class="ds-row cols-3">
            <div><label class="ds-field-lbl">Additional Amount Due</label><input type="number" placeholder="0.00" step="0.01" class="ds-field-input" id="riPayDue" readonly></div>
            <div><label class="ds-field-lbl">Amount Received</label><input type="number" name="paid_amount" id="riPaidAmount" placeholder="0.00" step="0.01" class="ds-field-input" oninput="calcReissueDue()"></div>
            <div>
              <label class="ds-field-lbl">Payment Status <span class="req">*</span></label>
              <select name="payment_status" required class="select2 ds-field-select" style="width:100%"><option value="due">Due</option><option value="partial">Partial</option><option value="paid">Paid</option></select>
            </div>
          </div>
          <div class="ds-row cols-3">
            <div>
              <label class="ds-field-lbl">Payment Method</label>
              <select name="pay_method" class="select2 ds-field-select" style="width:100%"><option value="">— Select method —</option><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank Transfer</option><option value="mobile_banking">Mobile Banking</option><option value="advance">Advance</option></select>
            </div>
            <div><label class="ds-field-lbl">Receive into Account (Bank)</label><select name="bank_id" class="select2 ds-field-select" style="width:100%"><option value="">— Select bank —</option>@include('partials.bank-options', ['banks' => $banks, 'selected' => null])</select></div>
            <div><label class="ds-field-lbl">Attachment</label><input type="file" name="attachment" class="ds-field-input"></div>
          </div>
        </div>
        <div class="ds-due-line"><span class="ds-due-label">Due from Agent (Re-Issue):</span><span class="ds-due-amount" id="riDueDisplay">৳ 0.00</span></div>
      </div>

      <div class="ds-action-bar">
        <button type="button" class="erp-btn btn-ghost" onclick="document.getElementById('reissueForm').reset()">✕ Cancel</button>
        <button type="submit" class="erp-btn btn-primary" style="background:#1d4ed8;" id="reissueSubmitBtn">✈ Save Re-Issue</button>
      </div>
    </form>
  </div>

  {{-- ============================================================
       TAB 4 — VOID
  ============================================================ --}}
  <div class="tab-content" id="tab-void">
    <div class="ds-alert warning">⚠ Void is only allowed within the airline's void window (usually same day or 24 hrs). Late void may incur full penalty. This action cannot be undone.</div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fb923c"></div><span class="ds-sec-title">Void Header</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-4">
          <div>
            <label class="ds-field-lbl">Agent <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option value="">— Select agent —</option>@foreach ($agents as $ag)<option value="{{ $ag->id }}">{{ $ag->name }}</option>@endforeach</select>
          </div>
          <div><label class="ds-field-lbl">Void Date <span class="req">*</span></label><input type="date" value="{{ date('Y-m-d') }}" class="ds-field-input"></div>
          <div><label class="ds-field-lbl">Original Invoice <span class="req">*</span></label><input type="text" placeholder="INV000007" class="ds-field-input danger"></div>
          <div>
            <label class="ds-field-lbl">Void Reason <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option value="">— Select reason —</option><option>Wrong booking</option><option>Customer cancelled (same-day)</option><option>Schedule conflict</option><option>Duplicate booking</option><option>Other</option></select>
          </div>
        </div>
      </div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fdba74"></div><span class="ds-sec-title">Ticket(s) to Void</span><span class="ds-sec-sub">(select from original invoice)</span></div>
      <div class="ds-section-body">
        <div class="ds-orig-ref">
          <div>Passenger: <span class="ref-val">—</span></div>
          <div>PNR: <span class="ref-val">—</span></div>
          <div>Ticket No: <span class="ref-val">—</span></div>
          <div>Route: <span class="ref-val">—</span></div>
          <div>Issue Date: <span class="ref-val">—</span></div>
          <div>Cost: <span class="ref-val">—</span></div>
          <div>Sale: <span class="ref-val">—</span></div>
          <span class="ds-status-tag void">Void Pending</span>
        </div>
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Vendor</label><select class="select2 ds-field-select" style="width:100%"><option>— Auto-filled —</option>@foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select></div>
          <div><label class="ds-field-lbl">Portal</label><select class="select2 ds-field-select" style="width:100%"><option>— Auto-filled —</option>@foreach ($portals as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
          <div><label class="ds-field-lbl">Airline Confirmation No.</label><input type="text" placeholder="Enter void ref" class="ds-field-input danger"></div>
        </div>
      </div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fbbf24"></div><span class="ds-sec-title">Void Charges &amp; Reversal</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Original Cost Price</label><input type="number" placeholder="0.00" class="ds-field-input" id="vOrgCost" oninput="calcVoid()"></div>
          <div><label class="ds-field-lbl">Void Penalty / Fee</label><input type="number" placeholder="0.00" class="ds-field-input warning" id="vPenalty" oninput="calcVoid()"></div>
          <div><label class="ds-field-lbl">Net Cost Reversal</label><input type="number" placeholder="0.00" class="ds-field-input success" id="vNetCost" readonly></div>
        </div>
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Original Sale Price</label><input type="number" placeholder="0.00" class="ds-field-input" id="vOrgSale" oninput="calcVoid()"></div>
          <div><label class="ds-field-lbl">Agent Void Fee</label><input type="number" placeholder="0.00" class="ds-field-input warning" id="vAgentFee" oninput="calcVoid()"></div>
          <div><label class="ds-field-lbl">Net Sale Reversal</label><input type="number" placeholder="0.00" class="ds-field-input danger" id="vNetSale" readonly></div>
        </div>
        <div class="ds-calc">
          <div class="ds-calc-row"><span class="sec">Original sale price</span><span>৳ <span id="vCalcSale">0.00</span></span></div>
          <div class="ds-calc-row"><span class="sec">Void penalty</span><span style="color:#d97706">− ৳ <span id="vCalcPenalty">0.00</span></span></div>
          <div class="ds-calc-row"><span class="sec">Agent void fee</span><span style="color:#d97706">− ৳ <span id="vCalcFee">0.00</span></span></div>
          <div class="ds-calc-row total"><span>Refund to agent / customer</span><span style="color:#dc2626">৳ <span id="vCalcRefund">0.00</span></span></div>
        </div>
      </div>
    </div>

    <div class="ds-summary-bar cols-2">
      <div class="ds-sum-cell"><div class="ds-sum-label">Total Void Penalty</div><div class="ds-sum-value penalty"><span class="taka">৳</span> <span id="vSumPenalty">0.00</span></div></div>
      <div class="ds-sum-cell"><div class="ds-sum-label">Refund to Customer</div><div class="ds-sum-value refund"><span class="taka">৳</span> <span id="vSumRefund">0.00</span></div></div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fb923c"></div><span class="ds-sec-title">Payment Reversal / Refund</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Refund Amount</label><input type="number" placeholder="0.00" class="ds-field-input"></div>
          <div><label class="ds-field-lbl">Refund Method <span class="req">*</span></label><select class="select2 ds-field-select" style="width:100%"><option value="">— Select method —</option><option>Cash</option><option>bKash</option><option>Nagad</option><option>Bank Transfer</option><option>Card Reversal</option></select></div>
          <div><label class="ds-field-lbl">Refund Status <span class="req">*</span></label><select class="select2 ds-field-select" style="width:100%"><option>Pending</option><option>Processing</option><option>Completed</option></select></div>
        </div>
        <div class="ds-row cols-2">
          <div><label class="ds-field-lbl">Paid from Account (Bank)</label><select class="select2 ds-field-select" style="width:100%"><option value="">— Select bank —</option>@include('partials.bank-options', ['banks' => $banks, 'selected' => null])</select></div>
          <div><label class="ds-field-lbl">Void Proof / Attachment</label><input type="file" class="ds-field-input"></div>
        </div>
      </div>
      <div class="ds-due-line"><span class="ds-due-label">Net Due to Agent:</span><span class="ds-due-amount">৳ 0.00</span></div>
    </div>

    <div class="ds-action-bar">
      <button type="button" class="erp-btn btn-ghost">✕ Cancel</button>
      <button type="button" class="erp-btn btn-warning">⊗ Confirm Void</button>
    </div>
  </div>

  {{-- ============================================================
       TAB 5 — EMD / ANCILLARY
  ============================================================ --}}
  <div class="tab-content" id="tab-emd">
    <div class="ds-alert info">ℹ EMD is used for ancillary charges: excess baggage, seat upgrade, meals, airport tax, visa fee, travel insurance, and other services.</div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#c084fc"></div><span class="ds-sec-title">EMD / Ancillary Header</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-4">
          <div>
            <label class="ds-field-lbl">Agent <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option value="">— Select agent —</option>@foreach ($agents as $ag)<option value="{{ $ag->id }}">{{ $ag->name }}</option>@endforeach</select>
          </div>
          <div><label class="ds-field-lbl">Issue Date <span class="req">*</span></label><input type="date" value="{{ date('Y-m-d') }}" class="ds-field-input"></div>
          <div><label class="ds-field-lbl">Link to Invoice (optional)</label><select class="select2 ds-field-select" style="width:100%"><option value="">— None —</option></select></div>
          <div>
            <label class="ds-field-lbl">Currency <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option value="BDT" selected>BDT (৳)</option><option value="USD">USD ($)</option><option value="EUR">EUR (€)</option></select>
          </div>
        </div>
      </div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h">
        <div class="ds-sec-dot" style="background:#a855f7"></div>
        <span class="ds-sec-title">Ancillary / EMD Items</span>
        <span class="ds-sec-sub">(one row per service)</span>
        <div class="right"><button type="button" class="ds-add-pax" id="addEmdItemBtn" style="background:#faf5ff;color:#7c3aed;border-color:#d8b4fe;">+ Add Item</button></div>
      </div>
      <div class="ds-section-body">
        <div id="emdItemContainer">
          <div class="ds-pax-card" data-emd="1">
            <div class="ds-pax-h">
              <span class="ds-pax-badge" style="background:#f3e8ff;color:#7c3aed;">Item 1</span>
              <button type="button" class="ds-pax-remove" onclick="removeEmdItem(this)" style="display:none">✕ Remove</button>
            </div>
            <div class="ds-row cols-3">
              <div>
                <label class="ds-field-lbl">Passenger / Customer <span class="req">*</span></label>
                <select class="select2 ds-field-select" style="width:100%"><option value="">— Select passenger —</option>@foreach ($passport_holders as $ph)<option value="{{ $ph->id }}">{{ $ph->name }}{{ $ph->passport_no ? " — " . $ph->passport_no : "" }}</option>@endforeach</select>
              </div>
              <div>
                <label class="ds-field-lbl">Service Type <span class="req">*</span></label>
                <select class="select2 ds-field-select" style="width:100%"><option value="">— Select type —</option><option>Excess Baggage</option><option>Seat Upgrade</option><option>Meal</option><option>Airport Tax</option><option>Visa Fee</option><option>Travel Insurance</option><option>Lounge Access</option><option>Other</option></select>
              </div>
              <div><label class="ds-field-lbl">EMD / Reference No.</label><input type="text" placeholder="Enter EMD no." class="ds-field-input" style="border-color:#9333ea;"></div>
            </div>
            <div class="ds-row cols-4">
              <div><label class="ds-field-lbl">Vendor / Airline</label><select class="select2 ds-field-select" style="width:100%"><option value="">— Select vendor —</option>@foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select></div>
              <div><label class="ds-field-lbl">Description</label><input type="text" placeholder="e.g. 23kg baggage" class="ds-field-input"></div>
              <div><label class="ds-field-lbl">Cost Price <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input emd-cost" oninput="recalcEmd()"></div>
              <div><label class="ds-field-lbl">Sale Price <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input sale-price emd-sale" oninput="recalcEmd()"></div>
            </div>
          </div>
        </div>
        <button type="button" class="ds-add-another" id="addAnotherEmd" style="color:#7c3aed;">⊕ Add Another Item</button>
      </div>
    </div>

    <div class="ds-summary-bar">
      <div class="ds-sum-cell"><div class="ds-sum-label">Total Cost</div><div class="ds-sum-value cost"><span class="taka">৳</span> <span id="emdSumCost">0.00</span></div></div>
      <div class="ds-sum-cell"><div class="ds-sum-label">Total Sale</div><div class="ds-sum-value sale"><span class="taka">৳</span> <span id="emdSumSale">0.00</span></div></div>
      <div class="ds-sum-cell"><div class="ds-sum-label">Gross Profit</div><div class="ds-sum-value profit"><span class="taka">৳</span> <span id="emdSumProfit">0.00</span></div></div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#c084fc"></div><span class="ds-sec-title">Payment from Agent</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Total Sale Amount</label><input type="number" value="0.00" class="ds-field-input" id="emdTotalDisplay" readonly></div>
          <div><label class="ds-field-lbl">Amount Received</label><input type="number" placeholder="0.00" class="ds-field-input" id="emdPaid" oninput="calcEmdDue()"></div>
          <div><label class="ds-field-lbl">Payment Status <span class="req">*</span></label><select class="select2 ds-field-select" style="width:100%"><option>Due</option><option>Partial</option><option>Paid</option></select></div>
        </div>
        <div class="ds-row cols-2">
          <div><label class="ds-field-lbl">Receive into Account (Bank)</label><select class="select2 ds-field-select" style="width:100%"><option value="">— Select bank —</option>@include('partials.bank-options', ['banks' => $banks, 'selected' => null])</select></div>
          <div><label class="ds-field-lbl">Attachment</label><input type="file" class="ds-field-input"></div>
        </div>
      </div>
      <div class="ds-due-line"><span class="ds-due-label">Due from Agent:</span><span class="ds-due-amount">৳ <span id="emdDueDisplay">0.00</span></span></div>
    </div>

    <div class="ds-action-bar">
      <button type="button" class="erp-btn btn-ghost">✕ Cancel</button>
      <button type="button" class="erp-btn btn-primary" style="background:#7c3aed;">💾 Save EMD / Ancillary</button>
    </div>
  </div>

</div>

{{-- ============================================================
     PASSPORT HOLDER QUICK-CREATE MODAL
============================================================ --}}
<div class="ph-modal-overlay" id="phModalOverlay">
  <div class="ph-modal">
    <div class="ph-modal-head">
      <span style="font-size:18px;">🪪</span>
      <span class="ph-modal-title">Create New Passport Holder</span>
      <button type="button" class="ph-modal-close" onclick="closePhModal()">✕</button>
    </div>
    <div class="ph-modal-body">
      <div class="ph-modal-err" id="phModalErr"></div>
      <div class="ph-modal-grid">
        <div class="full">
          <label class="ph-modal-lbl">Full Name <span class="req">*</span></label>
          <input type="text" id="phName" class="ph-modal-input" placeholder="e.g. Md. Rahman">
        </div>
        <div>
          <label class="ph-modal-lbl">Passport No <span style="font-weight:400;color:#94a3b8">— optional</span></label>
          <input type="text" id="phPassportNo" class="ph-modal-input" placeholder="e.g. BD1234567">
        </div>
        <div>
          <label class="ph-modal-lbl">Nationality <span class="req">*</span></label>
          <input type="text" id="phNationality" class="ph-modal-input" value="Bangladeshi" placeholder="e.g. Bangladeshi">
        </div>
        <div>
          <label class="ph-modal-lbl">Phone</label>
          <input type="text" id="phPhone" class="ph-modal-input" placeholder="e.g. 01XXXXXXXXX">
        </div>
        <div>
          <label class="ph-modal-lbl">Date of Birth</label>
          <input type="date" id="phDob" class="ph-modal-input">
        </div>
        <div>
          <label class="ph-modal-lbl">Category <span class="req">*</span></label>
          <select id="phCategory" class="ph-modal-select">
            <option value="">— Select category —</option>
            @foreach ($ph_categories as $cat)
              <option value="{{ $cat->id }}" @selected(strtolower(trim($cat->name)) === 'ordinary passport')>{{ $cat->name }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="ph-modal-lbl">Type <span class="req">*</span></label>
          <select id="phType" class="ph-modal-select">
            @include('passport-holder.type-options')
          </select>
        </div>
        <div>
          <label class="ph-modal-lbl">Issue Date</label>
          <input type="date" id="phIssueDate" class="ph-modal-input">
        </div>
        <div>
          <label class="ph-modal-lbl">Expiry Date</label>
          <input type="date" id="phExpiryDate" class="ph-modal-input">
        </div>
      </div>
    </div>
    <div class="ph-modal-foot">
      <button type="button" class="erp-btn btn-ghost" onclick="closePhModal()">Cancel</button>
      <button type="button" class="erp-btn btn-primary" id="phSaveBtn" onclick="submitPhModal()">💾 Save Passport Holder</button>
    </div>
  </div>
</div>

{{-- ============================================================
     TICKET ROUTE QUICK-CREATE MODAL
============================================================ --}}
<div class="ph-modal-overlay" id="ticketModalOverlay">
  {{-- data-tk-prefix: Manage Tickets' via-airport script (tickets/route-via-script) works this modal as "qc" --}}
  <div class="ph-modal tk-modal-box" id="qcModal" data-tk-prefix="qc">
    <div class="ph-modal-head" style="background:linear-gradient(135deg,#5b21b6,#7c3aed);">
      <span style="font-size:18px;">✈</span>
      <span class="ph-modal-title">Create New Ticket Route</span>
      <button type="button" class="ph-modal-close" onclick="closeTicketModal()">✕</button>
    </div>
    <div class="ph-modal-body">
      <div class="ph-modal-err" id="ticketModalErr"></div>
      <div class="ph-modal-grid">
        <div class="full">
          <label class="ph-modal-lbl">Route Title <span class="req">*</span></label>
          <input type="text" id="tkTitle" class="ph-modal-input" placeholder="e.g. DAC-DXB">
        </div>
        <div>
          <label class="ph-modal-lbl">From Airport</label>
          <select id="qc_from_airport_id" class="ph-modal-select" data-ph="— Select airport —" style="width:100%">
            <option value="">— Select airport —</option>
            @foreach ($airports as $ap)
              <option value="{{ $ap->id }}" data-flag="{{ strtolower($ap->country?->code ?? '') }}">{{ $ap->name }}{{ $ap->code && ! str_contains((string) $ap->name, '('.$ap->code.')') ? ' ('.$ap->code.')' : '' }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="ph-modal-lbl">To Airport</label>
          <select id="qc_to_airport_id" class="ph-modal-select" data-ph="— Select airport —" style="width:100%">
            <option value="">— Select airport —</option>
            @foreach ($airports as $ap)
              <option value="{{ $ap->id }}" data-flag="{{ strtolower($ap->country?->code ?? '') }}">{{ $ap->name }}{{ $ap->code && ! str_contains((string) $ap->name, '('.$ap->code.')') ? ' ('.$ap->code.')' : '' }}</option>
            @endforeach
          </select>
        </div>
        {{-- Via airports (owner, 2026-10-17: "need this feature in the add ticket, the + icon") — the same
             rows as Manage Tickets → Add New Ticket: each stop is a TRANSIT inside the ticket or the place a
             NEW TICKET starts; the ticket cards below follow. Same posting fields, same server code. --}}
        <div class="full tk-stops-wrap">
          <label class="ph-modal-lbl">Via airports <span class="qc-sub">— optional. Add each stop in order; mark where a new ticket starts</span></label>
          <input type="hidden" id="qc_via_airport_id" name="via_airport_id" value="">
          <div class="tk-stops" id="qc_stops"></div>
          <button type="button" class="tk-stop-add" data-prefix="qc"><i class="fas fa-plus"></i> Add via airport</button>
        </div>
        @include('tickets.route-trip', ['prefix' => 'qc'])
        <div class="tk-direct-only">
          <label class="ph-modal-lbl">Airline <span style="font-weight:400;opacity:.6">(optional — or choose it on each ticket)</span></label>
          <select id="tkAirline" class="ph-modal-select" data-ph="— Select airline —" style="width:100%">
            <option value="">— Select airline —</option>
            @foreach ($airlines as $al)
              <option value="{{ $al->id }}">{{ $al->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="tk-direct-only">
          <label class="ph-modal-lbl">Default Cost Price</label>
          <input type="number" id="qc_price" class="ph-modal-input" placeholder="0.00" step="0.01" min="0">
        </div>
        @include('tickets.route-via', ['prefix' => 'qc', 'airlines' => $airlines, 'vendors' => $vendors, 'allPortals' => $portals])
      </div>
    </div>
    <div class="ph-modal-foot">
      <button type="button" class="erp-btn btn-ghost" onclick="closeTicketModal()">Cancel</button>
      <button type="button" class="erp-btn btn-primary" id="tkSaveBtn" onclick="submitTicketModal()" style="background:#7c3aed;">💾 Save Route</button>
    </div>
  </div>
</div>

@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
{{-- Manage Tickets' via airports, for the "+" route modal (#qcModal, prefix "qc") — 2026-10-17 --}}
@include('tickets.route-via-script')
<script>
// ── Passport holder options blob (for dynamic rows) ───────────
let phOptions = `@foreach ($passport_holders as $ph)<option value="{{ $ph->id }}">{{ $ph->name }}{{ $ph->passport_no ? " — " . $ph->passport_no : "" }}</option>@endforeach`;
function allPhOptions() { return phOptions; }
// Cancel return (2026-10-14): the Family row shows only once its migration has run.
const DS_RET_ON = {{ \App\Services\ReturnCancel::ready() ? 'true' : 'false' }};
function dsFamilyToggle(cb) {
  const row = cb.closest('.ds-return-row');
  if (!row) return;
  row.classList.toggle('on', cb.checked);
  if (!cb.checked) { const by = row.querySelector('[name$="[return_cancel_by]"]'); if (by) { by.value = ''; if (by._flatpickr) by._flatpickr.clear(); } }
}
const FLAG_BASE = @json(asset('flags'));
let ticketOptions = `@foreach ($tickets as $t)<option value="{{ $t->id }}" data-price="{{ $t->price }}" data-vendor="{{ $t->vendor_id }}" data-portal="{{ $t->portal_id }}" data-legs='@json($t->legsArray())' data-trip="{{ $t->isRoundTrip() ? 'two-way' : '' }}" data-ff="{{ strtolower($t->from_airport?->country?->code ?? '') }}" data-tf="{{ strtolower($t->to_airport?->country?->code ?? '') }}">{{ $t->title }}</option>@endforeach`;
const vendorOptions = `@foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach`;
const portalOptions = `@foreach ($portals as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach`;
const bankOptions   = `@include('partials.bank-options', ['banks' => $banks, 'selected' => null])`;
// let: a client made from the Sold to "+" is added here too, so later cards list him
let buyerOptions = `@include('dashboard.ticket_direct_sales.partials.buyer-options')`;
const LEG_AIRLINE = @json(\App\Models\TicketLeg::hasAirline());   // the column exists only after its migration
let airlineOptions = `@foreach ($airlines as $al)<option value="{{ $al->id }}">{{ $al->name }}</option>@endforeach`;
const ENTRY_CHECKS = @json($entryChecks ?? ['expiry' => [], 'tickets' => []]);

$(document).ready(function () {
  $('.select2').select2();
  routeFlagSelect($('#passengerContainer .ticket-select'));
  bankLogoSelect($('select:has(option[data-company-name])'));
  airlineLogoSelect($('#passengerContainer .ds-vc'));
  iconSelect($('select'));
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
  @if($opsMode)
  { const first = document.querySelector('.ops-tab-bar .op-tab[data-tab="{{ $opsFirst }}"]'); if (first) first.click(); }
  @endif
  @if($bookingToConvert)
  const booking = {{ Illuminate\Support\Js::from($bookingConversionData) }};
  const setBookingField = (name, value) => {
    const field = document.querySelector(`[name="${name}"]`);
    if (!field || value === null || value === undefined) return;
    $(field).val(String(value)).trigger('change');
  };
  setBookingField('currency', booking.currency);
  setBookingField('passengers[0][passport_holder_id]', booking.passport_holder_id);
  setBookingField('passengers[0][ticket_id]', booking.ticket_id);
  setBookingField('passengers[0][trip_type]', booking.trip_type);
  setBookingField('passengers[0][ticket_class]', booking.ticket_class);
  setBookingField('passengers[0][purchase_date]', booking.purchase_date);
  setBookingField('passengers[0][ticket_no]', booking.ticket_no);
  setBookingField('passengers[0][name_ticket_no]', booking.name_ticket_no);
  @foreach(\App\Services\TicketBaggage::FIELDS as $bagField)
  setBookingField('passengers[0][{{ $bagField }}]', booking['{{ $bagField }}']);
  @endforeach
  // A via route just expanded into one card per ticket, each already carrying
  // its leg's own seller and price — the booking's single total must not
  // overwrite ticket 1's figures, so these only apply to a direct route.
  // a booking is ONE ticket: on a via route keep only that leg's card
  if (booking.leg_sequence) {
    document.querySelectorAll('#passengerContainer .ds-pax-card').forEach(c => {
      const seq = c.querySelector('.pax-leg-seq');
      if (seq && String(seq.value) !== String(booking.leg_sequence)) c.remove();
    });
    renumberPax();
  }
  const firstCard = document.querySelector('#passengerContainer .ds-pax-card');
  if (!(firstCard && firstCard.dataset.chainGroup)) {
    setBookingField('passengers[0][cost_amount]', booking.cost_amount);
    if (booking.vendor_id) setBookingField('passengers[0][vendor_id]', booking.vendor_id);
    if (booking.portal_id) setBookingField('passengers[0][portal_id]', booking.portal_id);
  }
  recalcSummary();
  @endif
});

// ── Tab switcher ──────────────────────────────────────────────
function switchOpTab(id, el, title, sub) {
  document.querySelectorAll('.op-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  if (el) el.classList.add('active');
  const target = document.getElementById('tab-' + id);
  if (target) target.classList.add('active');
  document.getElementById('ds-page-title').textContent = title;
  document.getElementById('ds-page-sub').textContent   = sub;
  if (history.replaceState) { const u = new URL(location.href); u.searchParams.set('tab', id); history.replaceState(null, '', u); }
}

// ── Passenger rows ────────────────────────────────────────────
let paxCount = 1;
document.getElementById('addPassengerBtn').addEventListener('click', addPassenger);
document.getElementById('addAnotherPax').addEventListener('click', addPassenger);

function addPassenger() {
  const idx = paxCount;
  const html = `
  <div class="ds-pax-card" data-pax="${idx+1}">
    <div class="ds-pax-h">
      <span class="ds-pax-badge" data-n="${idx+1}">Passenger ${idx+1}</span>
      <div class="ds-status-wrap">
        <label class="ds-field-lbl">Ticket status</label>
        <select name="passengers[${idx}][status]" class="select2-dyn ds-field-select pax-status" onchange="onStatusChange(this)" style="width:100%"><option value="purchased" selected>Purchased</option><option value="booked">Booked (hold)</option><option value="pending">Pending</option><option value="draft">Draft</option><option value="cancelled">Cancelled</option></select>
      </div>
      <button type="button" class="ds-pax-remove" onclick="removePassenger(this)">✕ Remove</button>
    </div>
    <div class="ds-zone">
      <div class="ds-zone-h"><i class="fas fa-user"></i> Traveller &amp; route</div>
      <div class="ds-row cols-6">
        <div class="span-2"><label class="ds-field-lbl">Passport Holder <span class="req">*</span></label>
          <div style="display:flex;gap:6px;align-items:center;">
            <select name="passengers[${idx}][passport_holder_id]" required class="select2-dyn ds-field-select ph-select" style="flex:1;min-width:0;"><option value="">— Select passenger —</option>${allPhOptions()}</select>
            <button type="button" class="ph-create-btn" onclick="openPhModal(this)" title="Create new passport holder">+</button>
          </div></div>
        <div class="span-2"><label class="ds-field-lbl">Ticket Route <span class="req">*</span></label>
          <div style="display:flex;gap:6px;align-items:center;">
            <select name="passengers[${idx}][ticket_id]" required class="select2-dyn ds-field-select ticket-select" style="flex:1;min-width:0;" onchange="onTicketChange(this,${idx})"><option value="">— Select from Manage Tickets —</option>${ticketOptions}</select>
            <button type="button" class="ph-create-btn ds-route-btn" onclick="openTicketModal(this)" title="Create new ticket route">+</button>
          </div></div>
        <div><label class="ds-field-lbl">Trip Type <span class="req">*</span></label>
          <select name="passengers[${idx}][trip_type]" required class="select2-dyn ds-field-select ds-trip" style="width:100%" onchange="onTripChange(this)"><option value="one-way">One-way</option><option value="two-way">Round Trip</option><option value="multi-city">Multi-city</option></select>
          <input type="hidden" name="passengers[${idx}][ticket_type]" value="air"></div>
        <div><label class="ds-field-lbl">Class <span class="req">*</span></label>
          <select name="passengers[${idx}][ticket_class]" required class="select2-dyn ds-field-select" style="width:100%"><option value="economy">Economy</option><option value="business">Business</option><option value="first">First Class</option></select></div>
      </div>
    </div>
              <div class="ds-zone">
                <div class="ds-zone-h"><i class="fas fa-ticket-alt"></i> Ticket &amp; seller</div>
                <div class="ds-row cols-6">
                  <div>
                    <label class="ds-field-lbl">PNR <span class="req">*</span></label>
                    <input type="text" name="passengers[${idx}][ticket_no]" required placeholder="e.g. ABCD12" class="ds-field-input highlight ds-pnr">
                  </div>
                  <div>
                    <label class="ds-field-lbl">E-ticket No.</label>
                    <input type="text" name="passengers[${idx}][name_ticket_no]" maxlength="100" placeholder="072-2345678901" class="ds-field-input ds-eticket" oninput="runChecks(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Validating carrier</label>
                    <select name="passengers[${idx}][validating_carrier_id]" class="select2-dyn ds-field-select ds-vc" style="width:100%">
                      <option value="">— Airline that issues —</option>
                      ${airlineOptions}
                    </select>
                  </div>
                  <div>
                    <label class="ds-field-lbl">Issue Date <span class="req">*</span></label>
                    <input type="date" name="passengers[${idx}][purchase_date]" required value="{{ date('Y-m-d') }}" class="ds-field-input ds-issue" onchange="runChecks(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Vendor</label>
                    <select name="passengers[${idx}][vendor_id]" class="select2-dyn ds-field-select pax-vendor" style="width:100%" onchange="onVendorChange(this)">
                      <option value="">— Select vendor —</option>
                      ${vendorOptions}
                    </select>
                  </div>
                  <div>
                    <label class="ds-field-lbl">Portal</label>
                    <select name="passengers[${idx}][portal_id]" class="select2-dyn ds-field-select pax-portal" style="width:100%" onchange="onPortalChange(this)">
                      <option value="">— Select portal —</option>
                      ${portalOptions}
                    </select>
                  </div>
                </div>
              </div>
              <div class="ds-zone">
                <div class="ds-zone-h"><i class="fas fa-plane-departure"></i> Flight</div>
                <div class="ds-row cols-6">
                  <div>
                    <label class="ds-field-lbl">Flight No.</label>
                    <input type="text" name="passengers[${idx}][flight_no]" maxlength="40" placeholder="GF 249" class="ds-field-input ds-upper">
                  </div>
                  <div class="span-2">
                    <label class="ds-field-lbl">Departure</label>
                    <input type="datetime-local" name="passengers[${idx}][departure_at]" class="ds-field-input ds-dep" onchange="runChecks(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Arrival</label>
                    <input type="datetime-local" name="passengers[${idx}][arrival_at]" class="ds-field-input">
                  </div>
                  <div>
                    <label class="ds-field-lbl">RBD</label>
                    <input type="text" name="passengers[${idx}][rbd]" maxlength="4" placeholder="Q" class="ds-field-input ds-upper" title="Booking class letter (fare bucket)">
                  </div>
                  <div>
                    @if(\App\Services\TicketBaggage::ready())
                    <label class="ds-field-lbl">Free baggage KG</label>
                    <input type="number" step="0.01" min="0" name="passengers[${idx}][free_baggage_kg]" class="ds-field-input">
                    <input type="hidden" name="passengers[${idx}][baggage]">
                    @else
                    <label class="ds-field-lbl">Baggage</label>
                    <input type="text" name="passengers[${idx}][baggage]" maxlength="40" placeholder="30 kg + 7 kg" class="ds-field-input">
                    @endif
                  </div>
                </div>
                <div class="ds-row ds-return-row${DS_RET_ON ? ' ds-has-fam' : ''}"${DS_RET_ON ? '' : ' style="display:none"'}>
                  <div class="ds-ret-f">
                    <label class="ds-field-lbl" title="Return flight number">Return Flight</label>
                    <input type="text" name="passengers[${idx}][return_flight_no]" maxlength="40" placeholder="GF 250" class="ds-field-input ds-upper">
                  </div>
                  <div class="ds-ret-f">
                    <label class="ds-field-lbl">Return Departure</label>
                    <input type="datetime-local" name="passengers[${idx}][return_at]" class="ds-field-input ds-ret" onchange="runChecks(this)">
                  </div>${DS_RET_ON ? `
                  <div class="ds-fam-by">
                    <label class="ds-field-lbl" title="The airline's deadline to cancel the return. Reminders go to whoever entered the ticket — 3 days before, 1 day before, and on the day">Cancel return by</label>
                    <input type="text" data-dt name="passengers[${idx}][return_cancel_by]" class="ds-field-input" placeholder="date, time optional" autocomplete="off">
                  </div>
                  <span class="ds-fam-tick" title="A family passenger (family flight / family visa): the return he does not use can be cancelled later — Ticket Orders → Change → Cancel return. Round trip: only its return half. A separate return ticket: tick it on that ticket's card."><input type="checkbox" name="passengers[${idx}][family_return]" value="1" aria-label="Family ticket" onchange="dsFamilyToggle(this)"> Family ticket</span>` : `
                  <div class="ds-return-note"><i class="fas fa-rotate-left"></i> Return leg of the same ticket</div>`}
                </div>
              </div>
              @include('dashboard.ticket_direct_sales.partials.baggage-fields', ['prefix' => 'passengers[${idx}]'])
              <div class="ds-zone">
                <div class="ds-zone-h"><i class="fas fa-coins"></i> Fare &amp; payment</div>
                <div class="ds-row cols-6">
                  <div>
                    <label class="ds-field-lbl">Base Fare</label>
                    <input type="number" name="passengers[${idx}][base_fare]" step="0.01" min="0" placeholder="0.00" class="ds-field-input ds-fare" oninput="calcFare(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Taxes</label>
                    <input type="number" name="passengers[${idx}][tax_amount]" step="0.01" min="0" placeholder="0.00" class="ds-field-input ds-tax" oninput="calcFare(this)">
                  </div>
                  <div class="span-2">
                    <label class="ds-field-lbl">Tax codes</label>
                    <input type="text" name="passengers[${idx}][tax_breakdown]" maxlength="255" placeholder="YQ 12000, BD 500, E5 300" class="ds-field-input ds-taxcodes" oninput="calcTaxCodes(this)" title="Codes and amounts; their sum fills Taxes">
                  </div>
                  <div>
                    <label class="ds-field-lbl" title="Commission %">Comm. %</label>
                    <input type="number" name="passengers[${idx}][commission_pct]" step="0.01" min="0" max="100" placeholder="0" class="ds-field-input ds-compct" oninput="calcFare(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl" title="Commission amount">Comm. ৳</label>
                    <input type="number" name="passengers[${idx}][commission_amount]" step="0.01" min="0" placeholder="0.00" class="ds-field-input ds-comamt" oninput="calcFare(this)">
                  </div>
                </div>
                <div class="ds-row cols-6">
                  <div>
                    <label class="ds-field-lbl">Net Cost <span class="req">*</span></label>
                    <input type="number" name="passengers[${idx}][cost_amount]" required step="0.01" placeholder="Type net cost" class="ds-field-input pax-cost ds-net" oninput="this.dataset.edited=this.value!==''?'1':'';recalcSummary()" title="Type the net cost directly, or fill base fare / taxes / commission to work it out">
                  </div>
                  <div>
                    <label class="ds-field-lbl" title="What the agent pays for this ticket">Agent Price <span class="req ds-sale-required" hidden>*</span></label>
                    <input type="number" name="passengers[${idx}][sale_price]" step="0.01" min="0" class="ds-field-input ds-sale" oninput="calcProfit(this)">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Cost Paid</label>
                    <input type="number" name="passengers[${idx}][purchase_paid]" step="0.01" placeholder="0.00" class="ds-field-input ds-paid" oninput="recalcSummary()">
                  </div>
                  <div>
                    <label class="ds-field-lbl" title="Agent price − net cost">Profit</label>
                    <input type="text" class="ds-field-input ds-profit" readonly tabindex="-1">
                  </div>
                  <div class="cost-sched-wrap">
                    <label class="ds-field-lbl">Cost Due Date</label>
                    <input type="date" name="passengers[${idx}][cost_due_date]" class="ds-field-input" title="Payable schedule date">
                  </div>
                  <div>
                    <label class="ds-field-lbl">Attachment</label>
                    <input type="file" name="passengers[${idx}][attachment]" accept=".pdf,.jpg,.jpeg,.png" class="ds-field-input ds-file">
                  </div>
                </div>
                <div class="ds-row cols-6 ds-sale-row">
                  <div>
                    <label class="ds-field-lbl" title="The agent or customer this ticket is sold to — billed on Save through Manage Sales">Sold to <span class="req ds-buyer-required" hidden>*</span></label>
                    <select name="passengers[${idx}][sale_client_id]" class="select2-dyn ds-field-select ds-sale-to" style="width:100%" onchange="calcProfit(this)"><option value="">— Not sold yet —</option>${buyerOptions}</select>
                  </div>
                  <div>
                    <label class="ds-field-lbl">Cost Bank</label>
                    <select name="passengers[${idx}][purchase_bank_id]" class="select2-dyn ds-field-select pax-cost-bank" style="width:100%">
                      <option value="">— Select bank —</option>
                      ${bankOptions}
                    </select>
                  </div>
                  <div>
                    <label class="ds-field-lbl">Pay Status <span class="req">*</span></label>
                    <select name="passengers[${idx}][purchase_pay_status]" required class="select2-dyn ds-field-select" style="width:100%">
                      <option value="due">Due</option><option value="partial">Partial</option><option value="paid">Paid</option>
                    </select>
                  </div>
                  <div class="span-2 ds-sale-note"></div>
                </div>
              </div>
              <div class="ds-card-warn" aria-live="polite"></div>
    <div class="ds-book-box" style="display:none">
      <div class="ds-book-hd"><i class="fas fa-clock"></i> Hold — the ticket is reserved, not paid. Nothing is posted to the ledger until it is marked Purchased. <small>Net cost above = quoted amount · departure is in the Flight zone.</small></div>
      <div class="ds-row cols-4">
        <div><label class="ds-field-lbl">Booking date &amp; time</label><input type="datetime-local" name="passengers[${idx}][booking_at]" class="ds-field-input" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
        <div><label class="ds-field-lbl" style="color:#d97706;">Hold expires at <span class="req">*</span></label><input type="datetime-local" name="passengers[${idx}][expires_at]" class="ds-field-input warning pax-expires" value="{{ now()->addHours(6)->format('Y-m-d\TH:i') }}"><span class="ds-book-hint">Defaults to 6 hours from now; set the airline's real deadline.</span></div>
      </div>
      <div class="ds-row cols-4">
        <div class="span-2"><label class="ds-field-lbl">Notify boss / user</label><select name="passengers[${idx}][notification_recipient_id]" class="select2-dyn ds-field-select" style="width:100%"><option value="">— nobody —</option>@foreach($bookingRecipients as $recipient)<option value="{{$recipient->id}}" @selected($bookingBoss?->id===$recipient->id)>{{$recipient->name}}{{$recipient->name==='Md Mohsin'?' (Boss)':''}} — {{$recipient->email?:$recipient->phone}}</option>@endforeach</select></div>
        <div class="span-2"><label class="ds-field-lbl">Notification channels</label><div class="ds-book-ch">@foreach(['notify_in_app'=>'In-app bell','notify_email'=>'Email','notify_sms'=>'SMS'] as $key=>$label)<label><input type="hidden" name="passengers[${idx}][{{$key}}]" value="0"><input type="checkbox" name="passengers[${idx}][{{$key}}]" value="1" checked> {{$label}}</label>@endforeach</div><span class="ds-book-hint">Automatic reminders: 24 hours, 3 hours, 1 hour before and at the expiry time.</span></div>
      </div>
      <div class="ds-row cols-1"><div><label class="ds-field-lbl">Notes</label><textarea name="passengers[${idx}][notes]" rows="2" class="ds-field-input" style="height:auto;min-height:60px;" placeholder="Airline instructions, fare rules or follow-up notes…"></textarea></div></div>
    </div>
    <div class="ds-pax-legs" style="margin-top:14px;border-top:1px solid #f1f0fa;padding-top:12px;">
      <div class="ds-legs-hd">
        <i class="fas fa-route ic"></i> Route legs <small>— sectors of this ticket, priced per segment</small>
        <button type="button" class="ds-add-pax" onclick="addLegRow(${idx})" title="Add a route leg">+ Add Leg</button>
      </div>
      <div id="legsContainer_${idx}"></div>
    </div>
  </div>`;
  document.getElementById('passengerContainer').insertAdjacentHTML('beforeend', html);
  $(document.getElementById('passengerContainer').lastElementChild).find('.select2-dyn').select2();
  routeFlagSelect($(document.getElementById('passengerContainer').lastElementChild).find('.ticket-select'));
  bankLogoSelect($(document.getElementById('passengerContainer').lastElementChild).find('select:has(option[data-company-name])'));
  airlineLogoSelect($(document.getElementById('passengerContainer').lastElementChild).find('.ds-vc'));
  iconSelect($(document.getElementById('passengerContainer').lastElementChild).find('select'));
  paxCount++;
  updateRemoveVis();
  recalcSummary();
}

// ── Route legs (per passenger) ───────────────────────────────────
// Optional per-segment breakdown (e.g. DAC→BAH, BAH→IST) so a combined
// multi-city ticket can still report cost per leg — see the Contract
// Flight "Leg-wise Cost" report.
const legCounters = {};
function addLegRow(paxIdx, prefill) {
  prefill = prefill || {};
  if (legCounters[paxIdx] === undefined) legCounters[paxIdx] = 0;
  const legIdx = legCounters[paxIdx]++;
  const flag = cc => cc ? `<img class="ds-flag" src="${FLAG_BASE}/${String(cc).replace(/[^a-z]/g, '')}.png" alt="" onerror="this.remove()">` : '';
  const row = document.createElement('div');
  row.setAttribute('data-leg-index', legIdx);
  row.style.cssText = `display:grid;grid-template-columns:1fr 1fr ${LEG_AIRLINE ? '1.4fr ' : ''}1fr 1fr 40px;gap:12px;margin-bottom:10px;padding-top:6px;align-items:end;`;
  // each sector's own airline, optional (owner, 2026-10-07): DAC → BAH Qatar, BAH → IST Pegasus on one ticket
  const al = prefill.airline_id ? airlineOptions.replace(`value="${prefill.airline_id}"`, `value="${prefill.airline_id}" selected`) : airlineOptions;
  row.innerHTML = `
    <div><label class="ds-field-lbl">${flag(prefill.from_flag)}From</label><input type="text" name="passengers[${paxIdx}][legs][${legIdx}][from_location]" class="ds-field-input" placeholder="e.g. DAC" value="${prefill.from_location || ''}"></div>
    <div><label class="ds-field-lbl">${flag(prefill.to_flag)}To</label><input type="text" name="passengers[${paxIdx}][legs][${legIdx}][to_location]" class="ds-field-input" placeholder="e.g. IST" value="${prefill.to_location || ''}"></div>
    ${LEG_AIRLINE ? `<div><label class="ds-field-lbl">Airline <span style="font-weight:400;opacity:.6">(optional)</span></label><select name="passengers[${paxIdx}][legs][${legIdx}][airline_id]" class="ds-field-select ds-leg-airline" style="width:100%"><option value="">— Airline —</option>${al}</select></div>` : ''}
    <div><label class="ds-field-lbl">Travel Date</label><input type="text" data-dt name="passengers[${paxIdx}][legs][${legIdx}][travel_date]" class="ds-field-input" value="${prefill.travel_date || ''}"></div>
    <div><label class="ds-field-lbl">Cost (৳)</label><input type="number" min="0" step="0.01" name="passengers[${paxIdx}][legs][${legIdx}][cost]" class="ds-field-input" value="${prefill.cost ?? ''}"></div>
    <button type="button" onclick="this.closest('[data-leg-index]').remove()" style="color:#dc2626;font-size:14px;height:42px;border:1px solid #fecaca;border-radius:10px;background:#fef2f2;cursor:pointer;" title="Remove leg"><i class="fas fa-trash"></i></button>`;
  document.getElementById('legsContainer_' + paxIdx).appendChild(row);
  if (LEG_AIRLINE) airlineLogoSelect($(row).find('.ds-leg-airline'));
}

function removePassenger(btn) {
  btn.closest('.ds-pax-card').remove();
  renumberPax();
  recalcSummary();
}



function renumberPax() {
  let paxNo = 0;
  document.querySelectorAll('#passengerContainer .ds-pax-card').forEach((c,i) => {
    // a chain-child card is the SAME passenger's next ticket: it keeps the parent's number
    if (!c.dataset.chainChild) paxNo++;
    const b = c.querySelector('.ds-pax-badge'); b.firstChild.nodeValue = 'Passenger ' + paxNo; b.setAttribute('data-n', paxNo);
  });
  updateRemoveVis();
}

function updateRemoveVis() {
  const cards = document.querySelectorAll('#passengerContainer .ds-pax-card');
  cards.forEach(c => {
    const btn = c.querySelector('.ds-pax-remove');
    if (btn) btn.style.display = cards.length > 1 ? '' : 'none';
  });
}

// The selected route's airports under its picker (owner, 2026-10-11): from the route's saved
// tickets (transit stops marked), else read from its title, e.g. DAC → DOH → ESB → ECN.
function showRoutePath(sel, opt) {
  const box = sel.closest('.span-2') || sel.parentElement.parentElement;
  box.querySelector('.ds-route-path')?.remove();
  if (!opt || !opt.value) return;
  const esc = t => $('<span>').text(t || '').html();
  const flag = cc => cc ? `<img class="ds-flag" src="${FLAG_BASE}/${esc(cc)}.png" alt="" onerror="this.remove()">` : '';
  let legs = []; try { legs = JSON.parse(opt.dataset.legs || '[]') || []; } catch (e) {}
  let pts = [];
  if (legs.length) {
    pts.push({ c: legs[0].from_code, f: legs[0].from_flag });
    legs.forEach((l, i) => {
      (l.stops || []).forEach(s => pts.push({ c: s.code, f: s.flag, tr: 1 }));
      pts.push({ c: l.to_code, f: l.to_flag, nt: i < legs.length - 1 });
    });
  } else {
    const codes = (opt.text.match(/[A-Z]{3}/g) || []);
    pts = codes.map((c, i) => ({ c, f: i === 0 ? opt.dataset.ff : (i === codes.length - 1 ? opt.dataset.tf : ''), tr: i > 0 && i < codes.length - 1 }));
  }
  if (!pts.length) return;
  const html = pts.map(p => `<span class="ap${p.tr ? ' tr' : ''}">${flag(p.f)}${esc(p.c)}${p.tr ? ' <small>transit</small>' : (p.nt ? ' <small>new ticket</small>' : '')}</span>`).join('<i class="fas fa-arrow-right-long"></i>');
  box.insertAdjacentHTML('beforeend', `<div class="ds-route-path">${html}</div>`);
}
// A round-trip route sets its cards' Trip type (owner, 2026-10-18): return on the same ticket = each of
// its tickets is Round Trip; a separate return = each ticket One-way (the return is its own card). Only a
// round-trip route sets it — and a card it set goes back to One-way when a one-way route is chosen.
function dsApplyRouteTrip(card, opt, legs) {
  const s = card && card.querySelector('.ds-trip'); if (!s) return;
  const round = !!opt && opt.dataset.trip === 'two-way';
  const want = round ? ((legs || []).some(l => l.direction === 'return') ? 'one-way' : 'two-way') : (card.dataset.tripAuto ? 'one-way' : null);
  if (round) card.dataset.tripAuto = '1'; else delete card.dataset.tripAuto;
  if (want && s.value !== want) $(s).val(want).trigger('change');
}
function onTicketChange(sel) {
  const opt  = sel.options[sel.selectedIndex];
  const card = sel.closest('.ds-pax-card');
  showRoutePath(sel, opt);
  const price = parseFloat(opt.dataset.price) || 0;
  const ci = card.querySelector('.pax-cost');
  // A chain route (Manage Tickets → Legs: DAC-IST-ECN = [DAC-IST] +
  // [IST-ECN]) is bought as one ticket PER LEG, each on the leg's own route
  // with its own vendor / portal, airline and price. So this row becomes
  // leg 1 and a row is added for every further leg, same passenger.
  let legs = [];
  try { legs = JSON.parse(opt.dataset.legs || '[]') || []; } catch (e) { legs = []; }
  // Already expanded for this same route, all its tickets still on the page: do not add them
  // again. A route set from code — .val(id).trigger('change'): the PDF import, a booking being
  // confirmed, the "+" new route — reaches here twice (Ticketing's dropdown helper re-sends the
  // trigger as a real change event), and the second expansion added every further ticket twice:
  // two cards, two purchases, for one leg (2026-10-18). A pick by hand comes once, as before.
  const grp = card.dataset.chainGroup;
  if (legs.length > 1 && !card.dataset.expanding && grp && card.dataset.chainRoute === sel.value
      && document.querySelectorAll('#passengerContainer .ds-pax-card[data-chain-group="' + grp + '"]').length >= legs.length) {
    recalcSummary();
    return;
  }
  if (legs.length > 1 && !card.dataset.expanding) {
    const chainId = sel.value;
    // unique per expansion: the PDF import builds several cards in the same millisecond, and two
    // passengers sharing a group id share a passport holder (the .ph-select sync below) (2026-10-18)
    const groupId = 'g' + Date.now() + '-' + (window.dsChainSeq = (window.dsChainSeq || 0) + 1);
    card.dataset.chainGroup = groupId;
    card.dataset.chainRoute = chainId;
    const holder = card.querySelector('.ph-select') ? card.querySelector('.ph-select').value : '';
    const paxIdxOf = c => parseInt((c.querySelector('.ticket-select').name.match(/passengers\[(\d+)\]/) || [])[1], 10);
    const setLeg = (c, leg, n) => {
      c.dataset.expanding = '1';
      const rs = c.querySelector('.ticket-select');
      if (rs.value !== chainId) $(rs).val(chainId).trigger('change.select2');
      const vs = c.querySelector('.pax-vendor'), ps = c.querySelector('.pax-portal');
      if (leg.vendor_id && vs) { $(vs).val(String(leg.vendor_id)).trigger('change'); }
      else if (leg.portal_id && ps) { $(ps).val(String(leg.portal_id)).trigger('change'); }
      const cost = c.querySelector('.pax-cost');
      if (cost) cost.value = (Number(leg.cost) || 0).toFixed(2);
      dsApplyRouteTrip(c, opt, legs);
      const i = paxIdxOf(c), lc = document.getElementById('legsContainer_' + i);
      if (lc) { lc.innerHTML = ''; legCounters[i] = 0; addLegRow(i, { from_location: leg.from_code || '', to_location: leg.to_code || '', from_flag: leg.from_flag || '', to_flag: leg.to_flag || '', cost: leg.cost == null ? '' : leg.cost, airline_id: leg.airline_id || '' }); }
      // One plain strip says what this card is: "Ticket 1 of 2 · DAC → DXB".
      // The Route legs box is hidden for these cards — it only repeated the same sector.
      const esc = t => $('<span>').text(t || '').html();
      const flag = cc => cc ? `<img class="ds-flag" src="${FLAG_BASE}/${esc(cc)}.png" alt="" onerror="this.remove()">` : '';
      c.querySelectorAll('.ds-leg-strip').forEach(e => e.remove());
      c.querySelector('.ds-pax-h').insertAdjacentHTML('afterend', `<div class="ds-leg-strip">
        <span class="k">${leg.direction === 'return' ? 'Return · ' : ''}Ticket ${n} of ${legs.length}</span>
        <span class="sec">${flag(leg.from_flag)}${esc(leg.from_code)} <i class="fas fa-arrow-right-long"></i> ${flag(leg.to_flag)}${esc(leg.to_code)}</span>
        ${leg.airline ? `<span class="dot">·</span><span>${esc(leg.airline)}</span>` : ''}
        <span class="note">part of route ${esc(opt.text.trim())}</span>
      </div>`);
      // keep the From / To / Travel date / Cost row of this ticket; drop the "Route legs" heading + Add Leg
      // …and move that row to the top, right under the strip, so From / To sit beside the ticket
      const lb = c.querySelector('.ds-pax-legs'); if (lb) { lb.style.display = ''; lb.classList.add('is-chain'); c.querySelector('.ds-leg-strip').insertAdjacentElement('afterend', lb); }
      let seq = c.querySelector('.pax-leg-seq');
      if (!seq) { seq = document.createElement('input'); seq.type = 'hidden'; seq.className = 'pax-leg-seq'; c.appendChild(seq); }
      seq.name = 'passengers[' + paxIdxOf(c) + '][leg_sequence]'; seq.value = n;
      delete c.dataset.expanding;
    };
    setLeg(card, legs[0], 1);
    legs.slice(1).forEach((leg, k) => {
      addPassenger();
      const next = document.getElementById('passengerContainer').lastElementChild;
      next.dataset.chainChild = '1'; // same passenger, next ticket — not a new passenger
      next.dataset.chainGroup = groupId;
      if (holder && next.querySelector('.ph-select')) $(next.querySelector('.ph-select')).val(holder).trigger('change');
      setLeg(next, leg, k + 2);
    });
    renumberPax();
    Swal.fire({ toast: true, position: 'top-end', icon: 'info', timer: 3500, showConfirmButton: false,
      title: `${legs.length} tickets — one per leg`, text: 'Each leg has its own vendor, airline and price.' });
    recalcSummary();
    return;
  }
  dsApplyRouteTrip(card, opt, legs);
  if (ci) { ci.value = price.toFixed(2); }
  recalcSummary();
}

// The tickets of one via route belong to ONE passenger: choosing the passport
// holder on any of them fills the same holder on the others.
$(document).on('change', '.ph-select', function () {
  const card = this.closest('.ds-pax-card'); const g = card && card.dataset.chainGroup;
  if (!g) return;
  const v = this.value;
  document.querySelectorAll('#passengerContainer .ds-pax-card[data-chain-group="' + g + '"] .ph-select').forEach(o => {
    if (o !== this && o.value !== v) $(o).val(v).trigger('change.select2');
  });
});

// Each ticket's status decides what Save writes: Purchased → a purchase (ledger,
// stock); Booked → a hold with a deadline and reminders; Draft / Pending /
// Cancelled → a record only. The hold box shows for Booked; purchase-only
// requirements are enforced on the server for Purchased rows.
function onStatusChange(sel) {
  const card = sel.closest('.ds-pax-card'); if (!card) return;
  const st = sel.value || 'purchased';
  card.dataset.status = st;
  const box = card.querySelector('.ds-book-box'); if (box) box.style.display = st === 'booked' ? '' : 'none';
  card.querySelectorAll('.ds-req-purchase').forEach(e => e.style.display = st === 'purchased' ? '' : 'none');
  if (typeof runChecks === 'function') runChecks();
}

function onVendorChange(sel) {
  const card = sel.closest('.ds-pax-card');
  if (!card) return;
  const portalSel = card.querySelector('.pax-portal');
  if (!portalSel) return;
  setTimeout(function () {
    if (sel.value) {
      $(portalSel).val(null).prop('disabled', true).trigger('change.select2');
    } else {
      $(portalSel).prop('disabled', false).trigger('change.select2');
    }
    toggleCostBank(portalSel);
  }, 0);
}

function onPortalChange(sel) {
  const card = sel.closest('.ds-pax-card');
  if (!card) return;
  const vendorSel = card.querySelector('.pax-vendor');
  if (!vendorSel) return;
  setTimeout(function () {
    if (sel.value) {
      $(vendorSel).val(null).prop('disabled', true).trigger('change.select2');
    } else {
      $(vendorSel).prop('disabled', false).trigger('change.select2');
    }
    toggleCostBank(sel);
  }, 0);
}

function toggleCostBank(sel) {
  const card = sel.closest('.ds-pax-card');
  if (!card) return;
  const bs = card.querySelector('.pax-cost-bank');
  if (bs) {
    bs.disabled = !!sel.value;
    if (sel.value) { bs.value = ''; $(bs).trigger('change'); }
  }
  const schedWrap = card.querySelector('.cost-sched-wrap');
  if (schedWrap) {
    schedWrap.style.display = sel.value ? 'none' : '';
    if (sel.value) { const di = schedWrap.querySelector('input[type=date]'); if (di) di.value = ''; }
  }
}

// ── Trip type (2026-10-04): the card follows it ─────────────────
// One-way: one flight line. Round trip: a Return line under it. Multi-city:
// the route legs become the Itinerary, opened with two sectors to fill.
function onTripChange(sel) {
  const card = sel.closest('.ds-pax-card'); if (!card) return;
  const trip = sel.value || 'one-way';
  card.dataset.trip = trip;
  const ret = card.querySelector('.ds-return-row');
  if (ret) {
    // the row also carries the Family tick (2026-10-15): it stays for every trip
    // type; only the return flight fields follow the trip — and only they are cleared
    ret.style.display = trip === 'two-way' || ret.classList.contains('ds-has-fam') ? '' : 'none';
    if (trip !== 'two-way') ret.querySelectorAll('.ds-ret-f input').forEach(i => i.value = '');
  }
  const legs = card.querySelector('.ds-pax-legs');
  if (legs) {
    const small = legs.querySelector('.ds-legs-hd small');
    if (small) small.textContent = trip === 'multi-city' ? '— every sector of the journey, in order' : '— sectors of this ticket, priced per segment';
    const box = legs.querySelector('[id^="legsContainer_"]');
    const idx = box ? box.id.replace('legsContainer_', '') : null;
    if (trip === 'multi-city' && box && idx !== null) {
      while (box.querySelectorAll('[data-leg-index]').length < 2) addLegRow(idx);
    }
  }
  if (typeof runChecks === 'function') runChecks();
}
// ── Fare maths (2026-10-04) ─────────────────────────────────────
// Net cost = base fare + taxes − commission (commission on the base fare, the
// BSP way). It fills itself until somebody types a net cost by hand; the
// commission amount likewise follows the % until typed over.
const num = v => parseFloat(v) || 0;
// Agent price − net cost = profit (owner, 2026-10-05), on every card. The
// sale itself is posted on Save by Manage Sales' own code; a contract-flight
// ticket is never sold here (billed per head on the flight).
function calcProfit(el) { const card = el.closest('.ds-pax-card'); if (card) profitOf(card); recalcSummary(); }
function syncPurchaseSaleRequired(card) {
  const price = card.querySelector('.ds-sale');
  const buyer = card.querySelector('.ds-sale-to');
  const priceEntered = price?.value !== '';
  const buyerSelected = !!buyer?.value;
  card.querySelector('.ds-buyer-required')?.toggleAttribute('hidden', !priceEntered);
  card.querySelector('.ds-sale-required')?.toggleAttribute('hidden', !buyerSelected);
  if (buyer) buyer.required = priceEntered;
  if (price) price.required = buyerSelected;
}
function profitOf(card) {
  const out = card.querySelector('.ds-profit'); if (!out) return;
  const price = card.querySelector('.ds-sale')?.value, to = card.querySelector('.ds-sale-to')?.value;
  const net = parseFloat(card.querySelector('.pax-cost')?.value) || 0;
  const note = card.querySelector('.ds-sale-note');
  syncPurchaseSaleRequired(card);
  if (price === '' || price == null) { out.value = ''; out.classList.remove('pos', 'neg'); if (note) note.textContent = to ? 'Type the agent price.' : ''; return; }
  const p = (parseFloat(price) || 0) - net + baggageAmounts(card).profit;
  out.value = p.toFixed(2);
  out.classList.toggle('pos', p > 0); out.classList.toggle('neg', p < 0);
  if (note) note.textContent = to ? '' : 'Choose who it is sold to — or clear the price.';
}
function calcFare(el) {
  const card = el.closest('.ds-pax-card'); if (!card) return;
  const q = s => card.querySelector(s);
  const fare = num(q('.ds-fare')?.value), tax = num(q('.ds-tax')?.value), pct = num(q('.ds-compct')?.value);
  const com = q('.ds-comamt'), net = q('.ds-net'), pctEl = q('.ds-compct');
  // % and ৳ fill each other (owner, 2026-10-07): type the amount → the % is worked out;
  // type the % (or change the base fare) → the amount is worked out.
  // A % needs a base fare to be a % of — say so in the box instead of leaving it blank.
  if (pctEl) pctEl.placeholder = (!fare && com && com.value !== '') ? 'needs base fare' : '0';
  if (com && el === com) {
    if (pctEl) pctEl.value = (com.value !== '' && fare) ? String(+(num(com.value) / fare * 100).toFixed(4)) : (com.value === '' ? '' : pctEl.value);
  } else if (com) {
    if (pct) com.value = (fare * pct / 100).toFixed(2);
    else if (el === pctEl) com.value = '';
    else if (pctEl && com.value !== '' && fare) pctEl.value = String(+(num(com.value) / fare * 100).toFixed(4)); // amount typed before the fare
  }
  if (net && !net.dataset.edited && (fare || tax)) net.value = (fare + tax - num(com?.value)).toFixed(2);
  recalcSummary();
}
// "YQ 12000, BD 500, E5 300" → Taxes = 12800
function calcTaxCodes(el) {
  const card = el.closest('.ds-pax-card'); if (!card) return;
  let sum = 0, m; const re = /([A-Z0-9]{2})\s*[:=]?\s*(\d[\d,]*(?:\.\d+)?)/gi;
  while ((m = re.exec(el.value)) !== null) sum += num(m[2].replace(/,/g, ''));
  const tax = card.querySelector('.ds-tax');
  if (tax && sum) { tax.value = sum.toFixed(2); calcFare(tax); }
}

// ── Smart checks: shown on the card and gathered in the rail ────
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const dmy = s => { const d = new Date(String(s).slice(0, 10) + 'T00:00'); return isNaN(d) ? s : String(d.getDate()).padStart(2, '0') + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear(); };
function cardChecks(card) {
  const v = s => (card.querySelector(s)?.value || '').trim();
  const out = [];
  const ph = v('.ph-select'), tk = v('.ticket-select'), seq = v('input[name$="[leg_sequence]"]') || '1';
  const status = v('.pax-status') || 'purchased';
  const issue = v('.ds-issue'), dep = v('.ds-dep');
  if (ph && tk) {
    const hit = (ENTRY_CHECKS.tickets || []).find(x => String(x.ph) === ph && String(x.t) === tk && String(x.s || 1) === seq);
    if (hit) out.push(['bad', `Already ticketed on this route: PNR ${hit.pnr || '—'} (${dmy(hit.d)})`]);
    const twins = [...document.querySelectorAll('#passengerContainer .ds-pax-card')].filter(c => c !== card
      && (c.querySelector('.ph-select')?.value || '') === ph && (c.querySelector('.ticket-select')?.value || '') === tk
      && ((c.querySelector('input[name$="[leg_sequence]"]')?.value || '1') === seq));
    if (twins.length) out.push(['bad', 'The same passenger, route and leg appears twice in this entry']);
  }
  const exp = ph && (ENTRY_CHECKS.expiry || {})[ph];
  if (exp) {
    const ref = new Date((dep || issue || new Date().toISOString()).slice(0, 10) + 'T00:00');
    const e = new Date(exp + 'T00:00'), six = new Date(ref); six.setMonth(six.getMonth() + 6);
    if (e < ref) out.push(['bad', `Passport expired ${dmy(exp)}`]);
    else if (e < six) out.push(['warn', `Passport expires ${dmy(exp)} — under 6 months from travel`]);
  }
  const ret = v('.ds-ret');
  if (ret && dep && ret < dep) out.push(['bad', 'Return is before the departure']);
  const et = v('.ds-eticket');
  if (et && !/^\d{3}[- ]?\d{10}$/.test(et)) out.push(['warn', 'E-ticket no. is normally 13 digits: 3-digit airline code + 10 (e.g. 072-2345678901)']);
  if (status === 'purchased' && issue === new Date().toISOString().slice(0, 10)) out.push(['info', 'Issued today — voidable without penalty until 23:59 (BSP)']);
  return out;
}
function runChecks() {
  const all = [];
  document.querySelectorAll('#passengerContainer .ds-pax-card').forEach((card, i) => {
    const list = cardChecks(card);
    const box = card.querySelector('.ds-card-warn');
    if (box) box.innerHTML = list.map(([k, m]) => `<span class="ds-chk ${k}"><i class="fas ${k === 'info' ? 'fa-clock' : 'fa-triangle-exclamation'}"></i>${m}</span>`).join('');
    list.filter(([k]) => k !== 'info').forEach(([k, m]) => all.push([k, `Passenger ${i + 1}: ${m}`]));
  });
  const rail = document.getElementById('railWarn');
  if (typeof renderPostings === 'function') renderPostings();
  if (rail) rail.innerHTML = all.length ? all.map(([k, m]) => `<div class="ds-chk ${k}"><i class="fas fa-triangle-exclamation"></i>${m}</div>`).join('') : '<div class="ds-rail-ok"><i class="fas fa-circle-check"></i> No warnings</div>';
}
$(document).on('change', '#passengerContainer .ph-select, #passengerContainer .ticket-select, #passengerContainer .pax-status', () => setTimeout(runChecks, 0));
$(document).on('input', '#passengerContainer .ds-upper', function () { const p = this.selectionStart; this.value = this.value.toUpperCase(); this.setSelectionRange(p, p); });
// ── What will be posted (rail) — mirrors store(): a Purchased card = seller
// bill (Dr 5110 / Cr Payable, the paid part Cr bank or portal); an agent price
// = Manage Sales' sale (Dr 1311 / Cr 4110). Any other status touches no money.
function renderPostings() {
  const box = document.getElementById('railPost'); if (!box) return;
  const esc = t => String(t ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const tk = n => '৳' + (Number(n) || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  const txt = (card, s) => { const el = card.querySelector(s); if (!el || !el.value) return ''; const o = el.options?.[el.selectedIndex]; return (o ? o.text : el.value).trim(); };
  const num = (card, s) => parseFloat(card.querySelector(s)?.value) || 0;
  const L = [];
  document.querySelectorAll('#passengerContainer .ds-pax-card').forEach((card, i) => {
    const st = card.querySelector('.pax-status')?.value || 'purchased';
    const name = txt(card, '.ph-select') || 'Passenger ' + (i + 1);
    const pnr = (card.querySelector('[name$="[ticket_no]"]')?.value || '').trim();
    const head = `<span class="who">${i + 1}. ${esc(name)}${pnr ? ' · ' + esc(pnr) : ''}</span>`;
    if (st !== 'purchased') {
      const exp = card.querySelector('.pax-expires')?.value;
      const what = st === 'booked' ? `Held${exp ? ' until <b>' + esc(dmy(exp.slice(0, 10)) + ' ' + exp.slice(11, 16)) + '</b>' : ''} — Booked, not purchased`
        : st === 'cancelled' ? 'Recorded as Cancelled' : 'Saved as ' + st[0].toUpperCase() + st.slice(1);
      L.push(`<li>${head}${what}<small>No money — nothing posted until it is purchased</small></li>`);
      return;
    }
    const cost = num(card, '.pax-cost'), paid = Math.min(num(card, '.ds-paid'), cost);
    const seller = txt(card, '.pax-vendor') || txt(card, '.pax-portal') || 'the seller';
    const viaPortal = !card.querySelector('.pax-vendor')?.value && !!card.querySelector('.pax-portal')?.value;
    // One line per posting: what · amount. The accounts are in the tooltip, not on screen
    // (owner, 2026-10-07: "so many lines, make easy to understand").
    const row = (label, amt, cls, tip) => `<div class="ds-pl"${tip ? ' title="' + esc(tip) + '"' : ''}><span>${label}</span><b class="${cls || ''}">${amt}</b></div>`;
    let h = head;
    const baggage = baggageAmounts(card);
    if (baggage.cost) h += row('Extra baggage from ' + esc(seller), tk(baggage.cost), 'r');
    if (baggage.charge) h += row('Baggage charged to ' + esc(txt(card, '.ds-sale-to') || 'ticket buyer'), tk(baggage.charge), 'r');
    if (baggage.cost || baggage.charge) h += row('Baggage profit', tk(baggage.profit), baggage.profit < 0 ? 'r' : 'g');
    if (cost > 0) {
      h += row('Bill from ' + esc(seller), tk(cost), 'r', 'Seller ledger · Dr 5110 Ticket Cost / Cr ' + (viaPortal ? 'portal wallet' : 'Payable'));
      const com = num(card, '.ds-comamt');
      if (com > 0) h += row('Commission income', tk(com), 'g', 'Dr 5110 Ticket Cost / Cr 4171 IATA Air Ticket Commission');
      if (paid > 0) h += row('Paid now', tk(paid), '', 'Dr Payable / Cr ' + (txt(card, '.pax-cost-bank') || 'bank or portal'));
    } else h += '<div class="ds-pl"><span class="m">No cost entered yet</span></div>';
    const to = card.querySelector('.ds-sale-to')?.value, priceRaw = card.querySelector('.ds-sale')?.value;
    if (priceRaw !== '' && priceRaw != null && !card.querySelector('.ds-sale')?.disabled) {
      const price = parseFloat(priceRaw) || 0, p = price - cost;
      h += row(to ? 'Bill to ' + esc(txt(card, '.ds-sale-to')) : 'Agent price', tk(price), '', 'Agent ledger · Dr 1311 Receivable / Cr 4110 Air Ticket Sales');
      h += row('Profit', tk(p), p < 0 ? 'r' : 'g', tk(price) + ' − ' + tk(cost) + ' net cost');
      if (!to) h += '<div class="ds-pl-warn">Pick "Sold to" to bill the agent</div>';
    }
    L.push('<li>' + h + '</li>');
  });
  box.innerHTML = '<h4>What will be posted</h4>' + (L.length ? '<ol>' + L.join('') + '</ol>' : '');
}
let dsPostTimer;
$(() => setTimeout(recalcSummary, 0));   // fill the rail on first load, not only after the first keystroke
$(document).on('input change', '#passengerContainer', () => { clearTimeout(dsPostTimer); dsPostTimer = setTimeout(renderPostings, 150); });
// Ticket Route picker with the origin and final-destination flags (owner, 2026-10-07):
// 🇧🇩 DAC-BAH-IST-ECN 🇹🇷. Flags from public/flags/{cc}.png; a missing one just drops out.
function routeFlagSelect($sel) {
  const base = @json(asset('flags'));
  const flag = cc => cc ? `<img class="ds-rflag" src="${base}/${cc}.png" alt="" onerror="this.remove()">` : '';
  const tpl = o => {
    if (!o.id || !o.element) return o.text;
    const d = o.element.dataset, t = $('<span>').text(o.text).html();
    return $(`<span class="ds-rt">${flag(d.ff)}<span class="ds-rt-t">${t}</span>${flag(d.tf)}</span>`);
  };
  $sel.each(function () {
    if ($(this).data('select2')) $(this).select2('destroy');
    $(this).select2({ width: '100%', templateResult: tpl, templateSelection: tpl });
  });
}
@include('contract-flights.partials.airline-brands-script')
// Validating carrier with the airline's logo (owner, 2026-10-07) — same marks and
// matching as the flight dashboard and Ticket Orders (public/airline-logos/<slug>.png).
function airlineLogoSelect($sel) {
  const base = @json(asset('airline-logos'));
  const tpl = o => {
    if (!o.id || !o.element) return o.text;
    const b = airlineBrand(o.text), t = $('<span>').text(o.text).html();
    const mark = b.slug ? `<img class="ds-blogo" src="${base}/${b.slug}.png" alt="" onerror="this.remove()">`
      : `<span class="ds-blogo ds-alogo-x" style="background:${b.color}">${(airlineCode(o.text) || o.text.slice(0, 2)).toUpperCase()}</span>`;
    return $(`<span class="ds-rt">${mark}<span class="ds-rt-t">${t}</span></span>`);
  };
  $sel.each(function () {
    if ($(this).data('select2')) $(this).select2('destroy');
    $(this).select2({ width: '100%', templateResult: tpl, templateSelection: tpl });
  });
}
// Every other picker gets a small coloured icon per option (owner, 2026-10-07), chosen
// by the field's name and, for fixed lists, by the option's value. Pickers that
// already carry a flag / logo (route, banks, carrier) are left as they are.
const DS_ICONS = {
  status: { purchased: ['fa-ticket', '#059669'], booked: ['fa-clock', '#0284c7'], pending: ['fa-hourglass-half', '#d97706'],
            draft: ['fa-pen', '#64748b'], cancelled: ['fa-ban', '#dc2626'], expired: ['fa-calendar-xmark', '#94a3b8'], confirm: ['fa-circle-check', '#059669'] },
  trip_type: { 'one-way': ['fa-arrow-right-long', '#4f46e5'], 'two-way': ['fa-right-left', '#7c3aed'], 'multi-city': ['fa-route', '#0891b2'] },
  ticket_class: { economy: ['fa-chair', '#0284c7'], premium: ['fa-couch', '#7c3aed'], business: ['fa-briefcase', '#b45309'], first: ['fa-crown', '#ca8a04'] },
  pay_status: { due: ['fa-circle-exclamation', '#dc2626'], partial: ['fa-circle-half-stroke', '#d97706'], paid: ['fa-circle-check', '#059669'] },
  passport_holder_id: ['fa-passport', '#7c3aed'], vendor_id: ['fa-store', '#d97706'], portal_id: ['fa-globe', '#0284c7'],
  sale_client_id: ['fa-user-tie', '#059669'], agent_id: ['fa-user-tie', '#059669'], notification_recipient_id: ['fa-bell', '#db2777'],
  currency: { bdt: ['fa-bangladeshi-taka-sign', '#059669'], usd: ['fa-dollar-sign', '#15803d'], eur: ['fa-euro-sign', '#1d4ed8'],
              gbp: ['fa-sterling-sign', '#7c3aed'], inr: ['fa-indian-rupee-sign', '#ea580c'], sar: ['fa-coins', '#16a34a'], aed: ['fa-coins', '#b45309'] },
  pay_method: ['fa-money-bill-wave', '#059669'], bearer: ['fa-scale-balanced', '#475569'],
  // passport type (the + passenger modal): one icon per kind (owner, 2026-10-11)
  phType: { general: ['fa-id-card', '#64748b'], visa: ['fa-passport', '#7c3aed'], ticket: ['fa-ticket', '#059669'],
            contract_flight: ['fa-plane-up', '#ea580c'], contract_file: ['fa-folder', '#b45309'] },
};
function iconSelect($sel) {
  $sel.each(function () {
    const sel = this, $s = $(sel);
    if (!$s.data('select2') || sel.matches('.ticket-select, .ds-vc, .pax-cost-bank, [name="bank_id"]') || sel.querySelector('option[data-company-name]')) return;
    const key = ((sel.name || '').match(/\[?([a-z_]+)\]?$/) || [])[1] || sel.id || '';
    const k = key.replace(/^(new_|purchase_)/, '').replace(/^payment_status$/, 'pay_status').replace(/^pay_status$/, 'pay_status');
    const conf = DS_ICONS[k]; if (!conf) return;
    const tpl = o => {
      if (!o.id || !o.element) return o.text;
      // a portal is usually an airline's (Air Arabia, Biman, IndiGo): its logo, as the carrier picker shows it
      if (k === 'portal_id' && typeof airlineBrand === 'function') {
        const b = airlineBrand(o.text);
        if (b.slug) return $(`<span class="ds-rt"><img class="ds-blogo" src="${@json(asset('airline-logos'))}/${b.slug}.png" alt="" onerror="this.remove()"><span class="ds-rt-t">${$('<span>').text(o.text).html()}</span></span>`);
      }
      let ic = Array.isArray(conf) ? conf : conf[String(o.id).toLowerCase()]; if (!ic) return o.text;
      // Sold to: a client (direct buyer, no agent) wears his own icon under the agents (2026-10-25)
      if (k === 'sale_client_id' && o.element.parentElement?.label === 'Clients') ic = ['fa-user', '#0284c7'];
      return $(`<span class="ds-rt"><i class="fas ${ic[0]} ds-oic" style="color:${ic[1]};background:${ic[1]}1a"></i><span class="ds-rt-t">${$('<span>').text(o.text).html()}</span></span>`);
    };
    const opts = $s.data('select2').options.options;
    $s.select2('destroy');
    $s.select2({ width: opts.width || '100%', dropdownParent: opts.dropdownParent, templateResult: tpl, templateSelection: tpl });
  });
}
// Bank pickers on this page (owner, 2026-10-07): EPAL TRAVELS' accounts first — this
// is Travels' own screen — and each bank's logo. Logos and name patterns are the
// Manage Bank dashboard's (public/bank-logos/<slug>.png); an unknown bank shows none.
// Reorders in the browser only: the shared partials/bank-options is not touched.
const DS_BANK_BRANDS = [
  ['dutch-bangla', /dutch\s*-?\s*bangla|\bdbbl\b/i], ['islami', /islami/i], ['brac', /brac/i],
  ['city', /city\s*bank/i], ['dhaka', /dhaka\s*bank/i], ['bkash', /b\s*-?\s*kash/i],
  ['petty-cash', /petty\s*-?\s*cash/i], ['office-cash', /office\s*-?\s*cash/i], ['visa-dhaka', /credit\s*-?\s*card/i],
];
function bankLogoSelect($sel) {
  const base = @json(asset('bank-logos'));
  const tpl = o => {
    if (!o.id || !o.element) return o.text;
    const name = o.text, hit = DS_BANK_BRANDS.find(([, re]) => re.test(name.replace(/—.*$/, '')));
    const img = hit ? `<img class="ds-blogo" src="${base}/${hit[0]}.png" alt="" onerror="this.remove()">` : '<span class="ds-blogo ds-blogo-x"></span>';
    return $(`<span class="ds-rt">${img}<span class="ds-rt-t">${$('<span>').text(name).html()}</span></span>`);
  };
  $sel.each(function () {
    const tr = [...this.querySelectorAll('optgroup')].find(g => /travels/i.test(g.label));
    if (tr && tr !== this.querySelector('optgroup')) this.insertBefore(tr, this.querySelector('optgroup'));
    if ($(this).data('select2')) $(this).select2('destroy');
    $(this).select2({ width: '100%', templateResult: tpl, templateSelection: tpl });
  });
}
// ── Entry summary (rail) ──────────────────────────────────────
function recalcSummary() {
  document.querySelectorAll('#passengerContainer .ds-pax-card').forEach(c => { try { profitOf(c); } catch (e) {} });
  const cards = [...document.querySelectorAll('#passengerContainer .ds-pax-card')];
  const val = (c, s) => parseFloat(c.querySelector(s)?.value) || 0;
  const sum = s => cards.reduce((t, c) => t + val(c, s), 0);
  const tk = n => '৳' + (Number(n) || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  const set = (id, v, cls) => { const el = document.getElementById(id); if (!el) return; el.textContent = v; if (cls !== undefined) el.className = cls; };
  const cost = sum('.pax-cost'), paid = cards.reduce((t, c) => t + Math.min(val(c, '.ds-paid'), val(c, '.pax-cost')), 0);
  // Profit = agent price − net cost, over the tickets that carry an agent price only
  // (an unpriced ticket is not a loss of its whole cost).
  const priced = cards.filter(c => (c.querySelector('.ds-sale')?.value ?? '') !== '' && !c.querySelector('.ds-sale')?.disabled);
  const sale = priced.reduce((t, c) => t + val(c, '.ds-sale'), 0);
  const baggage = cards.reduce((sum, c) => { const b = baggageAmounts(c); return {cost: sum.cost + b.cost, charge: sum.charge + b.charge, profit: sum.profit + b.profit}; }, {cost:0, charge:0, profit:0});
  const profit = sale - priced.reduce((t, c) => t + val(c, '.pax-cost'), 0) + baggage.profit;
  set('sumCount', cards.length);
  set('sumFare', tk(sum('.ds-fare')));
  set('sumTax', tk(sum('.ds-tax')));
  set('sumCom', tk(sum('.ds-comamt')), 'g');
  set('sumCost', tk(cost + baggage.cost));
  set('sumSale', priced.length || baggage.charge ? tk(sale + baggage.charge) : '—');
  set('sumProfit', priced.length || baggage.cost || baggage.charge ? tk(profit) : '—', priced.length || baggage.cost || baggage.charge ? (profit < 0 ? 'r' : 'g') : '');
  const baggagePaid = cards.reduce((sum, card) => sum + (card.querySelector('.pax-portal')?.value && !card.querySelector('.pax-vendor')?.value ? baggageAmounts(card).cost : 0), 0);
  set('sumPaid', tk(paid + baggagePaid));
  set('sumDue', tk(Math.max(0, cost + baggage.cost - paid - baggagePaid)), cost + baggage.cost - paid - baggagePaid > 0 ? 'r' : '');
  if (typeof runChecks === 'function') runChecks();
}

// ── Invoice lookup (shared) ───────────────────────────────────
const invoiceLookupUrl = "{{ route('role.ticket-invoice-lookup', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";

function fillRefundFromData(data) {
  const pax = data.passengers[0] || {};
  document.getElementById('refPassenger').textContent      = pax.passenger || '—';
  document.getElementById('refPnr').textContent            = pax.pnr || '—';
  document.getElementById('refRoute').textContent          = pax.route || '—';
  document.getElementById('refOrgCostDisplay').textContent = pax.org_cost?.toFixed(2) ?? '—';
  document.getElementById('refOrgSaleDisplay').textContent = pax.sale_price?.toFixed(2) ?? '—';
  document.getElementById('refStatusTag').textContent      = data.status || '—';
  document.getElementById('rOrgCost').value  = pax.org_cost   || '';
  document.getElementById('rOrgSale').value  = pax.sale_price || '';
  if (pax.vendor_id) { $('#refVendor').val(pax.vendor_id).trigger('change'); }
  if (pax.portal_id) { $('#refPortal').val(pax.portal_id).trigger('change'); }
  if (pax.pnr && pax.pnr !== '—') {
    const pnrInput = document.getElementById('refPnrInput');
    if (!pnrInput.value.trim()) pnrInput.value = pax.pnr;
  }
  calcRefund();
}

function lookupByRefPnr() {
  const val = (document.getElementById('refPnrInput').value || '').trim();
  if (!val) return;
  fetch(invoiceLookupUrl + '?pnr=' + encodeURIComponent(val))
    .then(r => r.json())
    .then(data => {
      if (!data.success) { alert(data.message); return; }
      fillRefundFromData(data);
      const invInput = document.getElementById('refInvoiceInput');
      if (!invInput.value.trim()) invInput.value = data.invoice_no || '';
    })
    .catch(() => alert('PNR lookup failed. Check network.'));
}

function lookupInvoice(tab) {
  const inp = tab === 'refund'
    ? document.getElementById('refInvoiceInput')
    : document.getElementById('riInvoiceInput');
  const val = (inp.value || '').trim();
  if (!val) return;

  fetch(invoiceLookupUrl + '?invoice_no=' + encodeURIComponent(val))
    .then(r => r.json())
    .then(data => {
      if (!data.success) { alert(data.message); return; }
      const pax = data.passengers[0] || {};
      if (tab === 'refund') {
        fillRefundFromData(data);
      } else {
        document.getElementById('riPassenger').textContent = pax.passenger || '—';
        document.getElementById('riRoute').textContent     = pax.route || '—';
        document.getElementById('riDate').textContent      = pax.purchase_date || '—';
        document.getElementById('riOrigFare').textContent  = pax.sale_price?.toFixed(2) ?? '—';
        document.getElementById('riStatusTag').textContent = data.status || '—';
        document.getElementById('riOrgPnrDisplay').value   = pax.pnr || '';
        document.getElementById('riOrgCost').value = pax.org_cost || '';
        calcReissue();
      }
    })
    .catch(() => alert('Lookup failed. Check network.'));
}

// ── Refund calc ───────────────────────────────────────────────
function calcRefund() {
  const a = parseFloat(document.getElementById('rAirlineRefund').value)||0;
  const p = parseFloat(document.getElementById('rPenalty').value)||0;
  const s = parseFloat(document.getElementById('rServiceCharge').value)||0;
  const net = Math.max(0, a-p-s);
  document.getElementById('rNetRefund').value = net.toFixed(2);
  document.getElementById('rCalcAirline').textContent = a.toFixed(2);
  document.getElementById('rCalcPenalty').textContent = p.toFixed(2);
  document.getElementById('rCalcService').textContent = s.toFixed(2);
  document.getElementById('rCalcNet').textContent     = net.toFixed(2);
  document.getElementById('rSumAirline').textContent  = a.toFixed(2);
  document.getElementById('rSumPenalty').textContent  = p.toFixed(2);
  document.getElementById('rSumNet').textContent      = net.toFixed(2);
  calcRefundDue();
}

function calcRefundDue() {
  const net  = parseFloat(document.getElementById('rNetRefund').value) || 0;
  const paid = parseFloat(document.getElementById('rPaidAmount').value) || 0;
  const due  = Math.max(0, net - paid);
  document.getElementById('rDueDisplay').textContent = '৳ ' + due.toFixed(2);
}

// ── Re-issue calc ─────────────────────────────────────────────
function calcReissue() {
  const orgCost  = parseFloat(document.getElementById('riOrgCost').value)||0;
  const penalty  = parseFloat(document.getElementById('riPenalty').value)||0;
  const fareDiff = parseFloat(document.getElementById('riFareDiff').value)||0;
  const service  = parseFloat(document.getElementById('riService').value)||0;
  const newCost  = orgCost + penalty + fareDiff;
  const bearer   = document.getElementById('riBearer').value || 'agent';
  const companyBears = bearer === 'company';

  const newSaleInput = document.getElementById('riNewSale');
  const paidInput     = document.getElementById('riPaidAmount');
  newSaleInput.readOnly = companyBears;
  paidInput.readOnly     = companyBears;
  if (companyBears) { newSaleInput.value = '0'; paidInput.value = '0'; }

  const newSale  = companyBears ? 0 : (parseFloat(newSaleInput.value)||0);
  const addCharge= companyBears ? 0 : (penalty + fareDiff + service);

  document.getElementById('riNewCost').value = newCost.toFixed(2);
  document.getElementById('riCalcPenalty').textContent = penalty.toFixed(2);
  document.getElementById('riCalcFare').textContent    = fareDiff.toFixed(2);
  document.getElementById('riCalcService').textContent = service.toFixed(2);
  document.getElementById('riCalcTotal').textContent   = addCharge.toFixed(2);
  document.getElementById('riSumCost').textContent     = newCost.toFixed(2);
  document.getElementById('riSumSale').textContent     = newSale.toFixed(2);
  document.getElementById('riSumProfit').textContent   = (newSale - newCost).toFixed(2);
  document.getElementById('riPayDue').value            = addCharge.toFixed(2);

  const hint = document.getElementById('riBearerHint');
  hint.textContent = companyBears
    ? 'We bear it — the agent is not billed for this re-issue (new cost ৳' + newCost.toFixed(2) + ' stays our cost).'
    : (addCharge > 0 ? 'Billed to the agent on Save — ৳' + addCharge.toFixed(2) + '.' : '');

  calcReissueDue();
}

function calcReissueDue() {
  const bearer = document.getElementById('riBearer').value || 'agent';
  if (bearer === 'company') {
    document.getElementById('riDueDisplay').textContent = '৳ 0.00';
    return;
  }
  const due  = parseFloat(document.getElementById('riPayDue').value) || 0;
  const paid = parseFloat(document.getElementById('riPaidAmount').value) || 0;
  const rem  = Math.max(0, due - paid);
  document.getElementById('riDueDisplay').textContent = '৳ ' + rem.toFixed(2);
}

// ── Refund form submit ────────────────────────────────────────
document.getElementById('refundForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const btn = document.getElementById('refundSubmitBtn');
  btn.disabled = true; btn.textContent = 'Processing…';
  const fd = new FormData(this);
  fetch(this.action, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
      alert(data.message);
      if (data.success) this.reset();
    })
    .catch(() => alert('Request failed.'))
    .finally(() => { btn.disabled = false; btn.textContent = '↩ Process Refund'; });
});

// ── Re-issue form submit ──────────────────────────────────────
document.getElementById('reissueForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const btn = document.getElementById('reissueSubmitBtn');
  btn.disabled = true; btn.textContent = 'Saving…';
  const fd = new FormData(this);
  fetch(this.action, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
      alert(data.message);
      if (data.success) this.reset();
    })
    .catch(() => alert('Request failed.'))
    .finally(() => { btn.disabled = false; btn.textContent = '✈ Save Re-Issue'; });
});

// ── Void calc ─────────────────────────────────────────────────
function calcVoid() {
  const orgCost  = parseFloat(document.getElementById('vOrgCost').value)||0;
  const penalty  = parseFloat(document.getElementById('vPenalty').value)||0;
  const orgSale  = parseFloat(document.getElementById('vOrgSale').value)||0;
  const agentFee = parseFloat(document.getElementById('vAgentFee').value)||0;
  const netCost  = Math.max(0, orgCost - penalty);
  const netSale  = Math.max(0, orgSale - penalty - agentFee);
  document.getElementById('vNetCost').value = netCost.toFixed(2);
  document.getElementById('vNetSale').value = netSale.toFixed(2);
  document.getElementById('vCalcSale').textContent    = orgSale.toFixed(2);
  document.getElementById('vCalcPenalty').textContent = penalty.toFixed(2);
  document.getElementById('vCalcFee').textContent     = agentFee.toFixed(2);
  document.getElementById('vCalcRefund').textContent  = netSale.toFixed(2);
  document.getElementById('vSumPenalty').textContent  = (penalty+agentFee).toFixed(2);
  document.getElementById('vSumRefund').textContent   = netSale.toFixed(2);
}

// ── EMD items ─────────────────────────────────────────────────
let emdCount = 1;
document.getElementById('addEmdItemBtn').addEventListener('click', addEmdItem);
document.getElementById('addAnotherEmd').addEventListener('click', addEmdItem);

function addEmdItem() {
  const idx = emdCount;
  const html = `
  <div class="ds-pax-card" data-emd="${idx+1}">
    <div class="ds-pax-h">
      <span class="ds-pax-badge" style="background:#f3e8ff;color:#7c3aed;">Item ${idx+1}</span>
      <button type="button" class="ds-pax-remove" onclick="removeEmdItem(this)">✕ Remove</button>
    </div>
    <div class="ds-row cols-3">
      <div><label class="ds-field-lbl">Passenger / Customer <span class="req">*</span></label><select class="select2-dyn ds-field-select" style="width:100%"><option value="">— Select passenger —</option>${phOptions}</select></div>
      <div><label class="ds-field-lbl">Service Type <span class="req">*</span></label><select class="select2-dyn ds-field-select" style="width:100%"><option value="">— Select type —</option><option>Excess Baggage</option><option>Seat Upgrade</option><option>Meal</option><option>Airport Tax</option><option>Visa Fee</option><option>Travel Insurance</option><option>Lounge Access</option><option>Other</option></select></div>
      <div><label class="ds-field-lbl">EMD / Reference No.</label><input type="text" placeholder="Enter EMD no." class="ds-field-input" style="border-color:#9333ea;"></div>
    </div>
    <div class="ds-row cols-4">
      <div><label class="ds-field-lbl">Vendor / Airline</label><select class="select2-dyn ds-field-select" style="width:100%"><option value="">— Select vendor —</option>${vendorOptions}</select></div>
      <div><label class="ds-field-lbl">Description</label><input type="text" placeholder="e.g. 23kg baggage" class="ds-field-input"></div>
      <div><label class="ds-field-lbl">Cost Price <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input emd-cost" oninput="recalcEmd()"></div>
      <div><label class="ds-field-lbl">Sale Price <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input sale-price emd-sale" oninput="recalcEmd()"></div>
    </div>
  </div>`;
  document.getElementById('emdItemContainer').insertAdjacentHTML('beforeend', html);
  $(document.getElementById('emdItemContainer').lastElementChild).find('.select2-dyn').select2();
  emdCount++;
  updateEmdRemoveVis();
}

function removeEmdItem(btn) {
  btn.closest('.ds-pax-card').remove();
  recalcEmd();
  updateEmdRemoveVis();
}

function updateEmdRemoveVis() {
  const cards = document.querySelectorAll('#emdItemContainer .ds-pax-card');
  cards.forEach(c => {
    const btn = c.querySelector('.ds-pax-remove');
    if (btn) btn.style.display = cards.length > 1 ? '' : 'none';
  });
}

function recalcEmd() {
  let tc = 0, ts = 0;
  document.querySelectorAll('#emdItemContainer .emd-cost').forEach(el => tc += parseFloat(el.value)||0);
  document.querySelectorAll('#emdItemContainer .emd-sale').forEach(el => ts += parseFloat(el.value)||0);
  document.getElementById('emdSumCost').textContent   = tc.toFixed(2);
  document.getElementById('emdSumSale').textContent   = ts.toFixed(2);
  document.getElementById('emdSumProfit').textContent = (ts-tc).toFixed(2);
  document.getElementById('emdTotalDisplay').value    = ts.toFixed(2);
  calcEmdDue();
}

function calcEmdDue() {
  const total = parseFloat(document.getElementById('emdTotalDisplay').value)||0;
  const paid  = parseFloat(document.getElementById('emdPaid').value)||0;
  document.getElementById('emdDueDisplay').textContent = Math.max(0, total-paid).toFixed(2);
}

// ── Direct Sale AJAX submit ───────────────────────────────────
@if(request('embed') === 'cf')
// Opened from a contract flight's Import PDF (2026-10-08): every successful save of this
// form — the plain Save or the import's Confirm — is handed back to the flight, which
// links the tickets to its passenger card and closes this frame.
$(document).ajaxSuccess(function (e, xhr, opts, data) {
  if (!data || !data.success || !Array.isArray(data.ticket_purchases)) return;
  if (String(opts.url || '').split('?')[0] !== String($('#directSaleForm').attr('action') || '').split('?')[0]) return;
  try { window.parent.postMessage({ type: 'tk-ticket-saved', ticket_purchases: data.ticket_purchases }, location.origin); } catch (x) {}
});
@endif
function clearPurchaseValidation() {
  const form = document.getElementById('directSaleForm');
  form.querySelectorAll('.ds-validation-error').forEach(error => error.remove());
  form.querySelectorAll('.ds-invalid').forEach(field => field.classList.remove('ds-invalid'));
  form.querySelectorAll('.ds-form-error').forEach(error => error.remove());
}

function showPurchaseGeneralError(message) {
  document.querySelectorAll('#directSaleForm .ds-form-error').forEach(error => error.remove());
  const error = document.createElement('div');
  error.className = 'ds-form-error';
  error.textContent = message || 'The purchase could not be saved.';
  document.getElementById('submitBtn').before(error);
  error.scrollIntoView({ behavior:'smooth', block:'center' });
}

function purchaseFieldName(key) {
  const parts = String(key).split('.');
  return parts.shift() + parts.map(part => `[${part}]`).join('');
}

function purchaseFieldWrap(field) {
  let wrap = field.parentElement;
  const card = field.closest('.ds-pax-card');
  while (wrap && wrap !== card) {
    if ([...wrap.children].some(child => child.matches?.('.ds-field-lbl'))) return wrap;
    wrap = wrap.parentElement;
  }
  return field.parentElement;
}

function showPurchaseValidation(errors) {
  clearPurchaseValidation();
  const form = document.getElementById('directSaleForm');
  let first = null;

  Object.entries(errors || {}).forEach(([key, messages]) => {
    const name = purchaseFieldName(key);
    const field = form.elements.namedItem(name);
    const control = field instanceof RadioNodeList ? field[0] : field;
    if (!control) return;

    const wrap = purchaseFieldWrap(control);
    const message = document.createElement('small');
    message.className = 'ds-validation-error';
    message.textContent = Array.isArray(messages) ? messages[0] : messages;
    wrap.appendChild(message);

    control.classList.add('ds-invalid');
    if ($(control).hasClass('select2-hidden-accessible')) {
      $(control).next('.select2-container').addClass('ds-invalid');
    }
    if (!first) first = control;
  });

  if (first) {
    first.closest('.ds-pax-card, .ds-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => {
      if ($(first).hasClass('select2-hidden-accessible')) $(first).select2('open');
      else first.focus({ preventScroll: true });
    }, 350);
  }
  return first;
}

function clientPurchaseErrors() {
  const errors = {};
  const add = (field, message) => {
    if (field?.name && !errors[field.name.replace(/\[([^\]]+)\]/g, '.$1')]) {
      errors[field.name.replace(/\[([^\]]+)\]/g, '.$1')] = [message];
    }
  };
  const form = document.getElementById('directSaleForm');
  const currency = form.elements.namedItem('currency');
  if (!currency?.value) add(currency, 'Please select a currency.');

  form.querySelectorAll('#passengerContainer .ds-pax-card').forEach((card, index) => {
    const field = suffix => card.querySelector(`[name$="[${suffix}]"]`);
    const status = field('status')?.value || 'purchased';
    const holder = field('passport_holder_id');
    if (!holder?.value) add(holder, `Ticket ${index + 1}: please select the passport holder.`);

    if (status === 'purchased') {
      const route = field('ticket_id'), pnr = field('ticket_no'), issueDate = field('purchase_date');
      const vendor = field('vendor_id'), portal = field('portal_id'), cost = field('cost_amount');
      if (!route?.value) add(route, `Ticket ${index + 1}: please select the ticket route.`);
      if (!pnr?.value.trim()) add(pnr, `Ticket ${index + 1}: enter the PNR / ticket number.`);
      if (!issueDate?.value) add(issueDate, `Ticket ${index + 1}: select the issue date.`);
      if (!vendor?.value && !portal?.value) add(vendor, `Ticket ${index + 1}: select a vendor or portal.`);
      if (cost?.value === '') add(cost, `Ticket ${index + 1}: enter the net cost.`);
      const buyer = field('sale_client_id'), price = field('sale_price');
      syncPurchaseSaleRequired(card);
      if (price?.value !== '' && !buyer?.value) add(buyer, `Ticket ${index + 1}: select who the ticket is sold to.`);
      if (buyer?.value && price?.value === '') add(price, `Ticket ${index + 1}: enter the agent price.`);
    }
    if (status === 'booked') {
      const expires = field('expires_at');
      if (!expires?.value) add(expires, `Ticket ${index + 1}: enter the hold deadline.`);
    }
  });
  return errors;
}

$(document).on('input change', '#directSaleForm .ds-invalid', function () {
  this.classList.remove('ds-invalid');
  $(this).next('.select2-container').removeClass('ds-invalid');
  purchaseFieldWrap(this)?.querySelector('.ds-validation-error')?.remove();
});

$('#submitBtn').click(async function(e) {
  e.preventDefault();
  // One click, one save (owner, 2026-10-15): a page that did not move on after a save
  // was clicked three times and made three tickets, each with its own invoice.
  const btn = this;
  if (btn.dataset.busy) return;
  btn.dataset.busy = '1'; btn.dataset.label = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  const release = () => { delete btn.dataset.busy; btn.disabled = false; btn.innerHTML = btn.dataset.label; };
  // a passenger the imported PDF names but nobody picked is created first, exactly as
  // the import modal's Confirm does (tiCreateHolders) — then the usual check runs
  if (typeof tiCreateHolders === 'function' && [...document.querySelectorAll('#passengerContainer .ds-pax-card[data-ti-key]')].some(c => tiAutoHolder(c))) {
    try { await tiCreateHolders(); }
    catch (x) { release(); return Swal.fire({ icon: 'error', title: 'Passenger not created', text: x.message + ' — nothing was saved.' }); }
  }
  const clientErrors = clientPurchaseErrors();
  if (Object.keys(clientErrors).length) {
    release();
    showPurchaseValidation(clientErrors);
    return;
  }

  $.ajax({
    url: $('#directSaleForm').attr('action'),
    method: 'POST',
    data: new FormData($('#directSaleForm')[0]),
    processData: false,
    contentType: false,
    success: function(res) {
      if (res.success) {
        // owner, 2026-10-11: land on Ticket Orders with the new rows lit up — that IS the
        // confirmation. Go at once (no second dialog, no wait: 2026-10-15 "taking so
        // long"); the loading dialog says Saved while Ticket Orders opens. The button
        // stays locked — this page is done.
        const fresh = [...(res.ticket_purchases || []).map(p => 'purchase-' + p.id), ...(res.ticket_bookings || []).map(b => 'booking-' + b.id)];
        const ordersUrl = typeof TI_ORDERS_URL !== 'undefined' && TI_ORDERS_URL && fresh.length ? TI_ORDERS_URL + '?new=' + encodeURIComponent(fresh.join(',')) : null;
        btn.innerHTML = 'Saved ✓';
        try { tiLearn(); } catch (e) {}
        res.redirect_url ? window.location.assign(res.redirect_url) : ordersUrl ? window.location.assign(ordersUrl) : window.location.reload();
      } else {
        release();
        const errors = res.errors || clientPurchaseErrors();
        if (Object.keys(errors).length) showPurchaseValidation(errors);
        else showPurchaseGeneralError(res.message);
      }
    },
    error: function(xhr) {
      release();
      if (xhr.status === 422 && xhr.responseJSON?.errors) {
        showPurchaseValidation(xhr.responseJSON.errors);
        return;
      }
      showPurchaseGeneralError(xhr.responseJSON?.message || 'Something went wrong.');
    }
  });
});

// ── Passport Holder quick-create modal ───────────────────────
let _phTargetBtn = null;
const phStoreUrl = "{{ route('role.passport-holder.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";

function openPhModal(btn) {
  _phTargetBtn = btn;
  document.getElementById('phName').value        = '';
  document.getElementById('phPassportNo').value  = '';
  document.getElementById('phNationality').value = 'Bangladeshi';
  document.getElementById('phDob').value         = '';
  const categorySelect = document.getElementById('phCategory');
  categorySelect.value = Array.from(categorySelect.options).find(option => option.text.trim().toLowerCase() === 'ordinary passport')?.value || '';
  document.getElementById('phType').value        = 'ticket';
  document.getElementById('phIssueDate').value   = '';
  document.getElementById('phExpiryDate').value  = '';
  document.getElementById('phModalErr').style.display = 'none';
  document.getElementById('phModalOverlay').classList.add('open');
}

function closePhModal() {
  document.getElementById('phModalOverlay').classList.remove('open');
  _phTargetBtn = null;
}

// Backdrop click deliberately does NOT close this (owner, 2026-09-22).
// Only the ✕ / Cancel closes it — a stray click beside the box used to
// discard a half-entered passport holder.

function submitPhModal() {
  const err = document.getElementById('phModalErr');
  err.style.display = 'none';

  const payload = {
    name:          document.getElementById('phName').value.trim(),
    passport_no:   document.getElementById('phPassportNo').value.trim(),
    nationality:   document.getElementById('phNationality').value.trim(),
    phone:         document.getElementById('phPhone').value.trim(),
    date_of_birth: document.getElementById('phDob').value,
    category_id:   document.getElementById('phCategory').value,
    type:          document.getElementById('phType').value,
    issue_date:    document.getElementById('phIssueDate').value,
    expiry_date:   document.getElementById('phExpiryDate').value,
    status:        1,
    _token:        $('meta[name="csrf-token"]').attr('content'),
  };

  const saveBtn = document.getElementById('phSaveBtn');
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving…';

  $.ajax({
    url: phStoreUrl,
    method: 'POST',
    data: payload,
    success: function(res) {
      saveBtn.disabled = false;
      saveBtn.innerHTML = '💾 Save Passport Holder';
      if (!res.success) {
        err.textContent = res.message || 'Failed to save.';
        err.style.display = 'block';
        return;
      }
      const newId   = res.data.id;
      // option text is "Name — PASSPORT" so select2's search matches either
      const newName = res.data.name + (res.data.passport_no ? ' — ' + res.data.passport_no : '');

      // Add new option to all existing ph-select dropdowns (select2 compatible)
      $('.ph-select').each(function() {
        if (!$(this).find('option[value="' + newId + '"]').length) {
          $(this).append(new Option(newName, newId, false, false));
        }
      });

      // Auto-select in the triggering dropdown
      if (_phTargetBtn) {
        const card = _phTargetBtn.closest('.ds-pax-card');
        const sel  = card ? card.querySelector('.ph-select') : null;
        if (sel) {
          $(sel).val(newId).trigger('change');
        }
      }

      // Update phOptions so future addPassenger() rows include this entry
      phOptions += `<option value="${newId}">${newName}</option>`;

      closePhModal();
      // ...and he is a client now: listed under Clients in every Sold to (owner, 2026-10-25)
      makeClientFromHolder(res.data.name, payload.phone).always(client => {
        Swal.fire({ icon: 'success', title: 'Created!', text: newName + ' added successfully.' + (client ? ' Also listed under Clients in Sold to.' : ''), timer: 2200, showConfirmButton: false });
      });
    },
    error: function(xhr) {
      saveBtn.disabled = false;
      saveBtn.innerHTML = '💾 Save Passport Holder';
      const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error.';
      err.textContent = msg;
      err.style.display = 'block';
    }
  });
}

// ── A new passenger is a client too (owner, 2026-10-25) ───────
// "After entering new passport, he should be added automatically to the client
// list, then can be selected from the Sold to." The passport holder "+" saves him,
// then this makes him a client (Party Management → Customer's record) and lists
// him under Clients in every Sold to — not selected: an agent's passenger is still
// sold to the agent. A client of the same name is reused, never made twice.
const clientStoreUrl = @json($canCreateClient ? route('role.ticket-quick-create-client', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) : null);

function makeClientFromHolder(name, phone) {
  if (!clientStoreUrl || !name) return $.Deferred().resolve(null).promise();
  return $.ajax({
    url: clientStoreUrl,
    method: 'POST',
    data: { name: name, phone: phone && phone.length <= 20 ? phone : '', _token: $('meta[name="csrf-token"]').attr('content') },
  }).then(res => {
    if (!res || !res.success) return null;
    addClientOption(res.data.id, res.data.name);
    return res.data;
  }, xhr => { console.warn('Client not made from the new passenger:', xhr.responseJSON?.message || xhr.status); return $.Deferred().resolve(null).promise(); });
}

function addClientOption(id, name) {
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  document.querySelectorAll('.ds-sale-to').forEach(sel => {
    if (sel.querySelector('option[value="' + id + '"]')) return;
    let grp = sel.querySelector('optgroup[label="Clients"]');
    if (!grp) { grp = document.createElement('optgroup'); grp.label = 'Clients'; sel.appendChild(grp); }
    grp.appendChild(new Option(name, id, false, false));
  });
  // later cards and the import's Sold to lists are built from this string
  if (!buyerOptions.includes('value="' + id + '"')) {
    const opt = `<option value="${Number(id)}">${esc(name)}</option>`;
    buyerOptions = buyerOptions.includes('<optgroup label="Clients">')
      ? buyerOptions.replace(/<\/optgroup>\s*$/, opt + '</optgroup>')
      : buyerOptions + `<optgroup label="Clients">${opt}</optgroup>`;
  }
}

// ── Ticket Route quick-create modal ──────────────────────────
let _activeTicketBtn = null;
const ticketQuickCreateUrl = "{{ route('role.ticket-quick-create-route', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";

function openTicketModal(btn) {
  _activeTicketBtn = btn;
  document.getElementById('tkTitle').value = '';
  document.getElementById('qc_price').value = '';
  const errEl = document.getElementById('ticketModalErr');
  errEl.style.display = 'none';
  errEl.textContent   = '';
  document.getElementById('ticketModalOverlay').classList.add('open');

  // Init Select2 inside modal (dropdownParent = modal box)
  const $modal = $('.tk-modal-box');
  const defaults = @json(app(\App\Services\TicketRouteDefaults::class)->values());
  const defaultFields = {'#tkAirline': 'airline_id', '#qc_from_airport_id': 'from_airport_id', '#qc_to_airport_id': 'to_airport_id'};
  ['#tkAirline', '#qc_from_airport_id', '#qc_to_airport_id'].forEach(function(id) {
    if ($(id).data('select2')) { $(id).select2('destroy'); }
    $(id).val(defaults[defaultFields[id]] || null).select2({ dropdownParent: $modal, width: '100%', placeholder: $(id).data('ph') });
  });
  // no via airports from the last route (Manage Tickets' via script, tickets/route-via-script)
  if (window.tkVia) tkVia.reset('qc');
}

function closeTicketModal() {
  document.getElementById('ticketModalOverlay').classList.remove('open');
  _activeTicketBtn = null;
}

// Backdrop click deliberately does NOT close this (owner, 2026-09-22).
// Only the ✕ / Cancel closes it — a stray click beside the box used to
// discard a half-entered route.

function submitTicketModal() {
  const title = document.getElementById('tkTitle').value.trim();
  const errEl = document.getElementById('ticketModalErr');
  errEl.style.display = 'none';

  if (!title) {
    errEl.textContent   = 'Route title is required.';
    errEl.style.display = 'block';
    return;
  }

  const saveBtn = document.getElementById('tkSaveBtn');
  saveBtn.disabled    = true;
  saveBtn.textContent = 'Saving…';

  // + the via airports and each ticket's airline / seller / price — the same fields Manage Tickets posts
  const via = $('#qcModal').find('[name="via_airport_id"], [name^="route_stops"], [name^="legs"], [name="trip_type"], [name="return_mode"], [name^="return_stops"]').serialize();
  $.ajax({
    url:    ticketQuickCreateUrl,
    method: 'POST',
    data: $.param({
      title:           title,
      airline_id:      document.getElementById('tkAirline').value          || '',
      from_airport_id: document.getElementById('qc_from_airport_id').value || '',
      to_airport_id:   document.getElementById('qc_to_airport_id').value   || '',
      price:           document.getElementById('qc_price').value           || 0,
    }) + (via ? '&' + via : ''),
    success: function(res) {
      saveBtn.disabled  = false;
      saveBtn.innerHTML = '💾 Save Route';
      if (!res.success) {
        errEl.textContent   = res.message || 'Failed to save.';
        errEl.style.display = 'block';
        return;
      }

      const t          = res.ticket;
      const id         = String(t.id); // Select2 matches string values
      const triggerBtn = _activeTicketBtn; // save before closeTicketModal() nulls it

      // Update global blob so future dynamic rows include this ticket
      const ff = String(t.ff || '').replace(/[^a-z]/g, ''), tf = String(t.tf || '').replace(/[^a-z]/g, '');
      // its tickets (data-legs) and seller ride along, so a via route expands on the card at once
      const legsJson = JSON.stringify(t.legs || []).replace(/'/g, '&#39;');
      ticketOptions += `<option value="${id}" data-price="${t.price}" data-vendor="${t.vendor_id || ''}" data-portal="${t.portal_id || ''}" data-legs='${legsJson}' data-trip="${t.trip_type === 'two-way' ? 'two-way' : ''}" data-ff="${ff}" data-tf="${tf}">${t.title}</option>`;

      // Add option to every existing ticket select (use class to avoid jQuery bracket-selector issue)
      $('.ticket-select, select[name="new_ticket_id"]').each(function() {
        if (!$(this).find('option[value="' + id + '"]').length) {
          const $opt = new Option(t.title, id, false, false);
          $opt.dataset.price  = t.price  || 0;
          $opt.dataset.vendor = t.vendor_id || '';
          $opt.dataset.portal = t.portal_id || '';
          $opt.dataset.legs   = JSON.stringify(t.legs || []);
          $opt.dataset.ff = ff; $opt.dataset.tf = tf;   // flags on the card's route picker too
          $opt.dataset.trip = t.trip_type === 'two-way' ? 'two-way' : '';   // a round-trip route (2026-10-18)
          $(this).append($opt);
        }
      });

      closeTicketModal();

      // Auto-select in the triggering passenger row
      if (triggerBtn) {
        const card = triggerBtn.closest('.ds-pax-card');
        const sel  = card ? card.querySelector('select.ticket-select') : null;
        if (sel) {
          $(sel).val(id).trigger('change');
          onTicketChange(sel);
        }
      }

      Swal.fire({ icon: 'success', title: 'Created!', text: '"' + t.title + '" added and selected.', timer: 1800, showConfirmButton: false });
    },
    error: function(xhr) {
      saveBtn.disabled    = false;
      saveBtn.innerHTML   = '💾 Save Route';
      const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error.';
      errEl.textContent   = msg;
      errEl.style.display = 'block';
    }
  });
}
</script>
@if($tiReadUrl)
<script>
// ── Import PDF (2026-10-05) ─────────────────────────────────────
// pdf.js reads each PDF here in the browser (the same pinned build as the
// bank statement compare) and only its text lines go to the server, which
// says what the ticket is (TicketPdfImporter) and what it matches in this
// ERP (TicketImportMatcher). Each passenger then becomes an ordinary card,
// filled as if typed, with the PDF attached — Save is the normal Save.
// Nothing here writes anything.
const TI_READ_URL = @json($tiReadUrl);
const TI_CONFLICT_URL = @json(\Illuminate\Support\Facades\Route::has('role.ticket-import.conflict') ? route('role.ticket-import.conflict', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) : null);
// Opened from a contract flight's passenger card (?embed=cf): its import rules (owner, 2026-10-17)
const TI_CF = @json(request('embed') === 'cf');
const TI_ORDERS_URL = @json(\Illuminate\Support\Facades\Route::has('role.ticket-direct-sale.index') ? route('role.ticket-direct-sale.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) : null);
const TI_LEARN_URL =@json(\Illuminate\Support\Facades\Route::has('role.ticket-import.learn') ? route('role.ticket-import.learn', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) : null);
const TI_RESOLVE_URL = @json(\Illuminate\Support\Facades\Route::has('role.ticket-import.resolve') ? route('role.ticket-import.resolve', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) : null);
const TI_PDFJS  = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
const TI_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
const TI = {};          // card key → { key, res, pax, file }
let tiSeq = 0, tiBusy = false;
const tiEsc = t => $('<span>').text(t == null ? '' : String(t)).html();

function tiToggle(open) {
  const p = document.getElementById('tiPanel'); if (!p) return;
  p.hidden = !open;
  if (open) p.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function tiLoadPdfJs() {
  if (window.pdfjsLib) return Promise.resolve();
  return new Promise((ok, fail) => {
    const s = document.createElement('script');
    s.src = TI_PDFJS;
    s.onload = () => ok();
    s.onerror = () => fail(new Error('The PDF reader could not be loaded (no internet access to the CDN?). Try again.'));
    document.head.appendChild(s);
  });
}

// The PDF's visual lines from pdf.js's positioned text: pieces on one
// baseline form a line, left to right; a wide gap between two pieces is a
// column break, written " | ". Pages are separated by "<<PAGE>>". The
// readers on the server are written against exactly this — change both.
async function tiExtract(file) {
  await tiLoadPdfJs();
  window.pdfjsLib.GlobalWorkerOptions.workerSrc = TI_WORKER;
  const pdf = await window.pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
  const lines = [];
  for (let p = 1; p <= pdf.numPages; p++) {
    const page = await pdf.getPage(p);
    const content = await page.getTextContent();
    const items = content.items
      .filter(it => typeof it.str === 'string' && it.str.trim() !== '' && Math.abs(it.transform[1]) < 0.01 && Math.abs(it.transform[2]) < 0.01)
      .map(it => ({ x: it.transform[4], y: it.transform[5], w: it.width || 0, h: Math.abs(it.transform[3]) || it.height || 8, s: it.str }))
      .sort((a, b) => (b.y - a.y) || (a.x - b.x));
    let cur = null; const rows = [];
    items.forEach(it => {
      const tol = Math.max(1.5, it.h * 0.35);
      if (!cur || Math.abs(cur.y - it.y) > tol) { cur = { y: it.y, items: [] }; rows.push(cur); }
      cur.items.push(it);
    });
    rows.forEach(row => {
      row.items.sort((a, b) => a.x - b.x);
      let text = '', endX = null;
      row.items.forEach(it => {
        if (endX !== null) {
          const gap = it.x - endX;
          if (gap > Math.max(6, it.h * 0.9)) text = text.replace(/\s+$/, '') + ' | ';
          else if (gap > 1 && !/\s$/.test(text) && !/^\s/.test(it.s)) text += ' ';
        }
        text += it.s; endX = it.x + it.w;
      });
      text = text.replace(/[  ]+/g, ' ').trim();
      if (text) lines.push(text);
    });
    lines.push('<<PAGE>>');
  }
  return lines;
}

// Scanned picture PDFs (owner: "build our own", no outside service): every
// page is drawn by pdf.js and read by Tesseract — open-source OCR running in
// this browser tab, so the picture never leaves the computer. Loaded only
// the first time a scan is dropped. Lines go to the same readers, marked
// "<<OCR>>" so each card says to check it against the PDF.
const TI_OCR_JS = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
function tiLoadOcr() {
  if (window.Tesseract) return Promise.resolve();
  return new Promise((ok, fail) => {
    const s = document.createElement('script');
    s.src = TI_OCR_JS;
    s.onload = () => ok();
    s.onerror = () => fail(new Error('The scan reader (OCR) could not be loaded — no internet access to the CDN?'));
    document.head.appendChild(s);
  });
}
async function tiOcr(file) {
  await Promise.all([tiLoadPdfJs(), tiLoadOcr()]);
  window.pdfjsLib.GlobalWorkerOptions.workerSrc = TI_WORKER;
  const pdf = await window.pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
  const worker = await window.Tesseract.createWorker('eng');
  const lines = ['<<OCR>>'];
  try {
    for (let p = 1; p <= Math.min(pdf.numPages, 6); p++) {
      const page = await pdf.getPage(p);
      const vp = page.getViewport({ scale: 2.5 });
      const cv = document.createElement('canvas');
      cv.width = vp.width; cv.height = vp.height;
      await page.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise;
      const { data } = await worker.recognize(cv);
      String(data.text || '').split('\n').map(l => l.replace(/\s+/g, ' ').trim()).filter(Boolean).forEach(l => lines.push(l));
      lines.push('<<PAGE>>');
    }
  } finally {
    await worker.terminate();
  }
  return lines;
}

async function tiHandle(fileList) {
  const files = [...fileList].filter(f => /\.pdf$/i.test(f.name) || f.type === 'application/pdf');
  if (!files.length) return;
  const list = document.getElementById('tiList');
  const rows = files.map(f => {
    const r = document.createElement('div');
    r.className = 'ti-row wait';
    r.innerHTML = `<span class="ic"><i class="fas fa-spinner fa-spin"></i></span><span class="fn" title="${tiEsc(f.name)}">${tiEsc(f.name)}</span><span class="ly">Reading…</span><span class="rs"></span><span class="ti-btns"><button type="button" class="ti-eye" title="View this PDF" onclick="tiViewFile(this)"><i class="fas fa-eye"></i></button><button type="button" class="ti-rm" title="Remove this PDF and its cards" onclick="tiRemoveFile(this)"><i class="fas fa-xmark"></i></button></span>`;
    r.__tiFile = f;
    list.appendChild(r);
    return r;
  });
  const setRow = (r, ok, layout, text) => {
    r.className = 'ti-row ' + (ok ? 'ok' : 'bad');
    r.querySelector('.ic').innerHTML = `<i class="fas ${ok ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i>`;
    r.querySelector('.ly').textContent = layout || '—';
    r.querySelector('.rs').innerHTML = text;
  };

  const sent = [];
  for (let i = 0; i < files.length; i++) {
    try {
      let lines = await tiExtract(files[i]);
      // no text at all = a scanned picture: read it by OCR, here in the browser
      if (lines.join('').replace(/<<PAGE>>/g, '').trim().length < 20) {
        rows[i].querySelector('.ly').textContent = 'Scanned picture — reading it (OCR)…';
        lines = await tiOcr(files[i]);
      }
      sent.push({ i, name: files[i].name, lines });
    }
    catch (e) { setRow(rows[i], false, '', tiEsc(e.message || 'Could not open this PDF.')); }
  }
  if (!sent.length) return;

  let results;
  try {
    const res = await $.ajax({ url: TI_READ_URL, method: 'POST', contentType: 'application/json',
      data: JSON.stringify({ files: sent.map(s => ({ name: s.name, lines: s.lines })) }) });
    results = res.results || [];
  } catch (xhr) {
    sent.forEach(s => setRow(rows[s.i], false, '', 'The server could not read it — try again.'));
    return;
  }

  let added = 0;
  sent.forEach((s, k) => {
    const res = results[k];
    if (!res || !['read', 'partial'].includes(res.status)) { setRow(rows[s.i], false, res?.layout_label, tiEsc(res?.message || 'Not read.')); return; }
    // (the ticket's own PNR, not the airline's, is chosen by the server since 2026-10-17 — res.airline_pnr keeps the other)
    let made = 0, skipped = 0;
    // No name read: still one card with whatever was read (PNR, flights, fare) —
    // staff pick the passenger before saving (owner, 2026-10-11).
    const paxList = (res.passengers || []).length ? res.passengers
      : [{ name: '', raw_name: '', title: null, passport_no: null, dob: null, eticket: null, pax_type: 'adult', fare: null }];
    const keysBefore = new Set(Object.keys(TI));
    res.lines = s.lines;   // kept so a Save can teach the reader what it missed (tiLearn)
    paxList.forEach(pax => { tiAddCard(res, pax, files[s.i]) ? made++ : skipped++; });
    added += made;
    const n = (res.passengers || []).length;
    // what this PDF did not give — to be typed on the card before Save
    const MISS_LBL = { passenger: 'passenger', route: 'route', seller: 'seller', cost: 'net cost', pnr: 'ticket PNR', duplicate: 'earlier ticket decision' };
    const miss = new Set();
    Object.keys(TI).filter(k => !keysBefore.has(k)).forEach(k => tiMissing(tiCardByKey(k)).forEach(x => miss.add(MISS_LBL[x] || x)));
    setRow(rows[s.i], made > 0, res.layout_label,
      (res.pnr ? `PNR <b class="ti-mono">${tiEsc(res.pnr)}</b> · ` : '') + `${n} passenger${n === 1 ? '' : 's'} → ${made} card${made === 1 ? '' : 's'}`
      + (skipped ? ` · ${skipped} already in this entry` : '')
      + ((res.added || []).length ? ` · <span style="color:#15803d">added ${tiEsc(res.added.join(', '))}</span>` : '')
      + (miss.size ? `<br><span style="color:#b45309">Not in the PDF — enter before saving: <b>${tiEsc([...miss].join(', '))}</b></span>` : ''));
  });
  renumberPax(); runChecks(); recalcSummary();
  if (added) {
    document.getElementById('tiReopen').hidden = false;
    tiWizOpen();
  }
}

// ✕ on a file row: that PDF leaves the import with every card it made
// (and the cards its via route added). The entry always keeps one card.
function tiRemoveFile(btn) {
  const row = btn.closest('.ti-row'), file = row && row.__tiFile;
  Object.values(TI).filter(t => t.file === file).forEach(t => {
    const card = document.querySelector(`#passengerContainer .ds-pax-card[data-ti-key="${t.key}"]`);
    if (card) {
      const g = card.dataset.chainGroup;
      if (g) document.querySelectorAll(`#passengerContainer .ds-pax-card[data-chain-group="${g}"]:not([data-ti-key])`).forEach(c => c.remove());
      card.remove();
    }
    delete TI[t.key];
  });
  if (!document.querySelector('#passengerContainer .ds-pax-card')) addPassenger();
  row.remove();
  const left = document.querySelectorAll('#passengerContainer .ds-pax-card[data-ti-key]').length;
  document.getElementById('tiReopen').hidden = !left;
  renumberPax(); runChecks(); recalcSummary();
  if (tiWizIsOpen()) left ? tiWizRender() : tiWizClose();
}

// The first untouched card is used before a new one is added.
function tiTargetCard() {
  const blank = [...document.querySelectorAll('#passengerContainer .ds-pax-card')].find(c => !c.dataset.tiKey
    && !c.querySelector('.ph-select')?.value && !c.querySelector('.ticket-select')?.value && !c.querySelector('[name$="[ticket_no]"]')?.value);
  if (blank) return blank;
  addPassenger();
  return document.getElementById('passengerContainer').lastElementChild;
}

// After a successful Save: for every imported card, send what the PDF gave
// next to what was saved; the server learns the labels of what staff had to
// type (Lessons::learn). Fire-and-forget — it never blocks or fails a Save.
function tiLearn() {
  if (!TI_LEARN_URL) return;
  const val = (card, f) => (tiField(card, f)?.value || '').trim();
  const cards = [];
  document.querySelectorAll('#passengerContainer .ds-pax-card[data-ti-key]').forEach(card => {
    const t = TI[card.dataset.tiKey]; if (!t || !t.res.lines) return;
    const seg = (t.res.segments || [])[0] || {};
    cards.push({
      lines: t.res.lines,
      layout: t.res.layout_label || null,
      read: { pnr: t.res.pnr, eticket: t.pax.eticket, name: t.pax.name, passport: t.pax.passport_no,
              flight: seg.flight_no, from: seg.from, to: seg.to, date: (seg.dep || '').slice(0, 10) },
      final: { pnr: val(card, 'ticket_no'), eticket: val(card, 'name_ticket_no'), flight: val(card, 'flight_no'),
               date: val(card, 'departure_at').slice(0, 10),
               holder_id: card.querySelector('.ph-select')?.value || null,
               ticket_id: card.querySelector('.ticket-select')?.value || null },
    });
  });
  if (!cards.length) return;
  fetch(TI_LEARN_URL, { method: 'POST', keepalive: cards.length < 8, credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
    body: JSON.stringify({ cards }) }).catch(() => {});
}

function tiAddCard(res, pax, file) {
  // the same person on the same PNR is already a card of this entry (PDF dropped twice)
  const dup = Object.values(TI).some(t => t.res.pnr === res.pnr
    && (pax.eticket ? t.pax.eticket === pax.eticket : t.pax.name === pax.name)
    && document.querySelector(`#passengerContainer [data-ti-key="${t.key}"]`));
  if (dup) return false;
  const card = tiTargetCard();
  const key = 'ti' + (++tiSeq);
  TI[key] = { key, res, pax, file };
  card.dataset.tiKey = key;
  tiBuild(card);
  return true;
}

// Route first (a via route expands into one card per leg — keep the leg
// this PDF is), then the fields, then the strip of what is left to decide.
function tiBuild(card) {
  const t = TI[card.dataset.tiKey]; if (!t) return;
  tiBusy = true;
  try {
    const sel = card.querySelector('.ticket-select');
    const r = t.res.route || {};
    // A route staff chose stands. Otherwise the same passenger's other leg in
    // THIS entry decides: both legs on one route = one order (2026-10-05 test:
    // leg 1 picked #29, leg 2 auto-matched the look-alike #30 → two orders).
    const chosen = sel.value && card.dataset.tiRouteAuto !== '1' ? sel.value : '';
    let want = chosen || tiMateRoute(card, t) || sel.value || (r.match ? String(r.match.id) : '');
    // two routes fit equally: take the one this passenger's other leg is on
    if (!want && (r.candidates || []).length) {
      const ph = card.querySelector('.ph-select')?.value || String(t.pax.holder?.id || '');
      // his other leg first (connect), then this same leg (a reissue / duplicate of it)
      const mine = (c, same) => (ENTRY_CHECKS.tickets || []).some(x => String(x.ph) === ph && String(x.t) === String(c.id)
        && (same ? String(x.s || 1) === String(c.leg || 1) : (String(x.s || 1) !== String(c.leg) && x.g)));
      const c = ph && (r.candidates.find(c => c.leg && mine(c, false)) || r.candidates.find(c => mine(c, true)));
      if (c) { want = String(c.id); r.match = c; }
    }
    // still several: the route that IS the ticket's sector (DAC-IST for a DAC → IST ticket) beats
    // a longer route that only contains it as a leg (owner, 2026-10-07) — still changeable in the table
    if (!want && (r.candidates || []).length) {
      const norm = s => String(s || '').toUpperCase().replace(/\(.*?\)/g, '').replace(/[^A-Z]+/g, '-').replace(/^-|-$/g, '');
      const sector = norm([r.from, ...(r.via || []), r.to].filter(Boolean).join('-'));
      const c = r.candidates.find(c => !c.leg && norm(c.title) === sector);
      if (c) { want = String(c.id); r.match = c; }
    }
    const autoSet = !!want && !chosen;
    if (want && sel.value !== want) $(sel).val(want).trigger('change');
    card = tiKeepLeg(card, t);
    if (autoSet) card.dataset.tiRouteAuto = '1';
    tiFill(card, t);
    tiStrip(card, t);
  } finally {
    tiBusy = false;
  }
  renumberPax(); runChecks(); recalcSummary();
  if (tiWizIsOpen()) tiWizRender();
}

// the same person on another imported card (same passport, else same name)
const tiPersonKey = t => String(t?.pax.passport_no || t?.pax.name || '').toUpperCase();
function tiMates(card) {
  const t = TI[card.dataset.tiKey]; if (!t) return [];
  return [...document.querySelectorAll('#passengerContainer .ds-pax-card[data-ti-key]')]
    .filter(c => c !== card && tiPersonKey(TI[c.dataset.tiKey]) === tiPersonKey(t));
}
// a route one of his other cards is on that also fits this card (as another leg) — chosen ones first
function tiMateRoute(card, t) {
  // a route whose stops differ from this ticket's (scored ≤ 50 by the matcher) is not followed
  const fits = new Set((t.res.route?.candidates || []).filter(c => (c.score ?? 100) > 50).map(c => String(c.id)));
  const mates = tiMates(card).sort((a, b) => (a.dataset.tiRouteAuto === '1') - (b.dataset.tiRouteAuto === '1'));
  for (const m of mates) {
    const v = m.querySelector('.ticket-select')?.value;
    if (v && fits.has(v)) return v;
  }
  return '';
}

function tiKeepLeg(card, t) {
  const g = card.dataset.chainGroup; if (!g) return card;
  const sel = card.querySelector('.ticket-select');
  const opt = sel.options[sel.selectedIndex];
  let legs = [];
  try { legs = JSON.parse(opt?.dataset.legs || '[]') || []; } catch (e) { legs = []; }
  const r = t.res.route || {};
  let want = (r.match && String(r.match.id) === sel.value) ? r.match.leg : null;
  if (!want) { const i = legs.findIndex(l => l.from_code === r.from && l.to_code === r.to); if (i >= 0) want = i + 1; }
  const group = [...document.querySelectorAll(`#passengerContainer .ds-pax-card[data-chain-group="${g}"]`)];
  // A route that does not contain this ticket's sector: staff's choice
  // stands, but one PDF ticket is still ONE card — the empty cards the
  // route's other legs just added go (they were "missing" passenger rows).
  if (!want) {
    group.forEach(c => { if (c !== card && !c.dataset.tiKey) c.remove(); });
    return card;
  }
  const keep = group.find(c => String(c.querySelector('.pax-leg-seq')?.value) === String(want)) || card;
  group.forEach(c => { if (c !== keep) c.remove(); });
  keep.dataset.tiKey = t.key;
  delete keep.dataset.chainChild;   // now the passenger's only card
  return keep;
}

function tiSetVal(card, field, v) {
  const el = card.querySelector(`[name$="[${field}]"]`);
  if (!el || v === null || v === undefined || v === '') return;
  if (el.tagName === 'SELECT') {
    if (![...el.options].some(o => o.value === String(v))) return;
    $(el).val(String(v)).trigger('change');
    return;
  }
  if (el.type === 'datetime-local' || el.hasAttribute('data-dt') || el.classList.contains('epal-dt')) {
    if (window.EpalDateTime) { window.EpalDateTime.upgrade(el); el.value = v; }
    else el.value = el.type === 'datetime-local' ? String(v).replace(' ', 'T') : v;
    return;
  }
  el.value = v;
}

function tiFill(card, t) {
  const { res, pax } = t;
  const segs = res.segments || [];
  const out = segs.filter(s => s.direction !== 'return'), ret = segs.filter(s => s.direction === 'return');
  const first = out[0] || segs[0] || {}, last = out[out.length - 1] || first;
  const flights = a => a.map(s => s.flight_no).filter(Boolean).join(' / ').slice(0, 40);

  const ph = card.querySelector('.ph-select');
  if (ph && !ph.value && pax.holder?.id) tiSetVal(card, 'passport_holder_id', pax.holder.id);
  tiSetVal(card, 'trip_type', res.trip_type);
  tiSetVal(card, 'ticket_class', first.cabin);
  tiSetVal(card, 'ticket_no', res.pnr);
  tiSetVal(card, 'name_ticket_no', pax.eticket);
  tiSetVal(card, 'validating_carrier_id', res.carrier_airline?.id);
  if (res.sheet) tiSheetFill(card, t);
  if (!res.sheet?.seller) tiRecallSeller(card, t);
  tiSetVal(card, 'purchase_date', res.issue_date);
  tiSetVal(card, 'flight_no', flights(out));
  tiSetVal(card, 'departure_at', first.dep);
  tiSetVal(card, 'arrival_at', last.arr);
  tiSetVal(card, 'rbd', first.rbd);
  tiSetVal(card, 'baggage', first.baggage);
  const freeKg = String(first.baggage || '').match(/^\s*(\d+(?:\.\d+)?)\s*(?:kgs?)?\s*$/i);
  if (freeKg) tiSetVal(card, 'free_baggage_kg', freeKg[1]);
  if (ret.length) { tiSetVal(card, 'return_flight_no', flights(ret)); tiSetVal(card, 'return_at', ret[0].dep); }

  const f = pax.fare;
  if (f) {
    if (f.base != null) tiSetVal(card, 'base_fare', Number(f.base).toFixed(2));
    if (f.tax != null) tiSetVal(card, 'tax_amount', Number(f.tax).toFixed(2));
    if (f.tax_breakdown) tiSetVal(card, 'tax_breakdown', f.tax_breakdown);
    const net = card.querySelector('.ds-net');
    if (net && f.total != null) { net.value = Number(f.total).toFixed(2); net.dataset.edited = '1'; }
  }

  // Route legs: every sector of the ticket. A via-route leg card keeps the
  // route's own leg row and only takes the travel date.
  const idx = parseInt((card.querySelector('.ticket-select').name.match(/passengers\[(\d+)\]/) || [])[1], 10);
  const box = document.getElementById('legsContainer_' + idx);
  if (box && !card.dataset.chainGroup) {
    box.innerHTML = ''; legCounters[idx] = 0;
    segs.forEach(s => addLegRow(idx, { from_location: s.from || '', to_location: s.to || '', from_flag: s.from_airport?.flag || '', to_flag: s.to_airport?.flag || '', travel_date: s.dep || '', airline_id: s.airline_id || '' }));
  } else if (box) {
    const td = box.querySelector('[name$="[travel_date]"]');
    if (td && first.dep) { if (window.EpalDateTime) window.EpalDateTime.upgrade(td); td.value = first.dep; }
  }
  // one ticket = one leg of the route; its transit stops are shown, not made into legs (owner, 2026-10-07)
  const strip = card.dataset.chainGroup && card.querySelector('.ds-leg-strip');
  if (strip) {
    strip.querySelectorAll('.ti-transit').forEach(e => e.remove());
    const stops = out.slice(0, -1).map(s => s.to).filter(Boolean);
    if (stops.length) strip.querySelector('.sec')?.insertAdjacentHTML('afterend', `<span class="dot ti-transit">·</span><span class="ti-transit">transit ${tiEsc(stops.join(', '))}</span>`);
  }

  const fi = card.querySelector('.ds-file');
  // a spreadsheet is never attached: it holds everyone's rows, not this ticket
  if (fi && t.file && !res.sheet && window.DataTransfer) {
    try { const dt = new DataTransfer(); dt.items.add(t.file); fi.files = dt.files; } catch (e) { /* attach by hand */ }
  }
}

// What the PDF could not settle, on the card itself: who the passenger is,
// which route, master data that is missing, and anything already on file.
function tiStrip(card, t) {
  const { res, pax } = t;
  const cfb = tiCfBound(card), saleRow = card.querySelector('.ds-sale-row');
  if (saleRow) {
    saleRow.classList.toggle('ds-cf-billed', !!cfb);
    card.querySelectorAll('.ds-sale-to, .ds-sale').forEach(e => { e.disabled = !!cfb; if (cfb) { e.value = ''; $(e).trigger('change.select2'); } });
    const note = saleRow.querySelector('.ds-sale-note');
    if (note && cfb) note.textContent = 'Billed per head on ' + cfb + '.';
    profitOf(card);
  }
  card.querySelectorAll('.ti-strip').forEach(e => e.remove());
  const chip = (k, m) => `<span class="ds-chk ${k}"><i class="fas ${k === 'info' ? 'fa-circle-info' : 'fa-triangle-exclamation'}"></i>${m}</span>`;
  const bits = [];

  const ph = card.querySelector('.ph-select');
  if (!ph.value) {
    const opts = (pax.holder?.options || []).map(o => `<button type="button" class="ti-pick" onclick="tiPickHolder(this, ${Number(o.id)})">${tiEsc(o.label)}</button>`).join('');
    // tiCreateHolders() makes him on Save (the page's and the table's) — say so, or "+ Create" reads as a job to do
    const auto = !opts && tiAutoHolder(card) ? ' — created automatically on Save' : '';
    bits.push(`<span class="ti-q">${opts ? 'Is this' : 'New passenger'} <b>${tiEsc(pax.name)}</b>${pax.passport_no ? ' · <span class="ti-mono">' + tiEsc(pax.passport_no) + '</span>' : ''}${opts ? '?' : ''}${auto} ${opts}<button type="button" class="ti-pick new" onclick="tiNewHolder(this)">+ Create</button></span>`);
  } else if (pax.holder?.id && String(pax.holder.id) === ph.value && pax.holder.how !== 'passport') {
    bits.push(chip('info', 'Passenger matched by name — check it is the same person'));
  }

  const r = res.route || {};
  if (!card.querySelector('.ticket-select').value) {
    const cands = (r.candidates || []).map(c => `<button type="button" class="ti-pick" onclick="tiPickRoute(this, ${Number(c.id)})">${tiEsc(c.title)}${c.leg ? ' · ticket ' + Number(c.leg) : ''}</button>`).join('');
    const sector = [r.from, ...(r.via || []), r.to].filter(Boolean).join(' → ');
    // the route modal fills whatever airports were found and leaves the rest to pick,
    // so the offer never depends on the server knowing both codes
    const canNew = !!r.title;
    bits.push(`<span class="ti-q">Route <b>${tiEsc(sector)}</b>${cands ? ' — choose' : ' is not in Manage Tickets'} ${cands}${canNew ? `<button type="button" class="ti-pick new" onclick="tiNewRoute(this)">+ New route ${tiEsc(r.title)}</button>` : ''}</span>`);
  }

  const segs = res.segments || [];
  const missAp = [...new Set(segs.flatMap(s => [[s.from, s.from_airport], [s.to, s.to_airport]]).filter(([c, a]) => c && !a).map(([c]) => c))];
  if (missAp.length) bits.push(chip('warn', `Airport ${tiEsc(missAp.join(', '))} is not in Airport Management`));
  const missAl = [...new Set(segs.filter(s => s.carrier && !s.airline_id).map(s => s.carrier))];
  if (missAl.length) bits.push(chip('warn', `Airline ${tiEsc(missAl.join(', '))} is not in Airlines`));

  const links = tiConnect(card);
  const dec = tiDecision(card);
  if (links.dupes.length && dec && dec.kind !== 'keep') bits.push(`<span class="ds-chk info"><i class="fas fa-clock-rotate-left"></i>${tiEsc(tiDecisionLine(dec))}</span>`);
  else links.dupes.forEach(d => bits.push(chip('bad', tiEsc(d.msg) + (dec?.kind === 'keep' ? ' — kept as a different ticket' : ''))));
  if (links.sibling) bits.push(`<span class="ds-chk info"><i class="fas fa-link"></i>Joins the order of ticket ${Number(links.sibling.s)} · PNR ${tiEsc(links.sibling.pnr)} (${tiEsc(tiWhen(links.sibling.d))})</span>`);
  if (!links.dupes.length && !links.sibling && res.on_file?.pnr_count) bits.push(chip('warn', `PNR ${tiEsc(res.pnr)} already has ${Number(res.on_file.pnr_count)} ticket(s) on file`));
  (res.warnings || []).forEach(w => bits.push(chip('warn', tiEsc(w))));

  card.querySelector('.ds-pax-h').insertAdjacentHTML('afterend',
    `<div class="ti-strip"><span class="src"><i class="fas ${res.sheet ? 'fa-file-excel' : 'fa-file-lines'}"></i>${tiEsc(t.file?.name || res.file)}</span><span>${tiEsc(res.layout_label)}${res.airline_pnr ? ' · Airline PNR <span class="ti-mono">' + tiEsc(res.airline_pnr) + '</span>' : res.gds_pnr ? ' · GDS <span class="ti-mono">' + tiEsc(res.gds_pnr) + '</span>' : ''}</span>${bits.join('')}</div>`);
}

const tiCardOf = el => el.closest('.ds-pax-card');
function tiPickHolder(btn, id) {
  const card = tiCardOf(btn);
  $(card.querySelector('.ph-select')).val(String(id)).trigger('change');
}
function tiPickRoute(btn, id) {
  const card = tiCardOf(btn);
  $(card.querySelector('.ticket-select')).val(String(id)).trigger('change');
}
// The existing "+ passport holder" modal, filled from the ticket.
function tiNewHolder(btn) {
  const t = TI[tiCardOf(btn).dataset.tiKey]; if (!t) return;
  openPhModal(btn);
  document.getElementById('phName').value = t.pax.name || '';
  document.getElementById('phPassportNo').value = t.pax.passport_no || '';
  if (t.pax.dob) document.getElementById('phDob').value = t.pax.dob;
  const cat = document.getElementById('phCategory');
  if (cat && !cat.value) {
    const o = [...cat.options].find(o => /ordinary/i.test(o.text));
    if (o) $(cat).val(o.value).trigger('change');
  }
  (t.pax.passport_no ? document.getElementById('phCategory') : document.getElementById('phPassportNo')).focus();
}
// The existing "+ route" modal, filled from the ticket.
function tiNewRoute(btn) {
  const t = TI[tiCardOf(btn).dataset.tiKey]; if (!t) return;
  const r = t.res.route || {};
  openTicketModal(btn);
  // a round-trip PDF is one round-trip ticket: the route is a round trip, return on the same ticket (2026-10-18)
  const round = t.res.trip_type === 'two-way' && r.from && r.to;
  document.getElementById('tkTitle').value = (r.title || '') + (round && r.title ? '-' + r.from : '');
  if (round && window.tkVia && tkVia.trip) tkVia.trip('qc', 'two-way', 'same');
  if (r.airline_id) $('#tkAirline').val(String(r.airline_id)).trigger('change');
  if (r.from_airport_id) $('#qc_from_airport_id').val(String(r.from_airport_id)).trigger('change');
  if (r.to_airport_id) $('#qc_to_airport_id').val(String(r.to_airport_id)).trigger('change');
  // the PDF's stops (DAC-BAH-IST → BAH) come in as TRANSITS — one PDF is one ticket (2026-10-17)
  if (window.tkVia && Array.isArray(r.via)) r.via.forEach(code => {
    const opt = [...document.querySelectorAll('#qc_from_airport_id option')].find(o => o.value && o.textContent.includes('(' + String(code).toUpperCase() + ')'));
    if (opt) tkVia.add('qc', opt.value, false);
  });
}

// A route chosen afterwards (a pick, the dropdown, or a new route) keeps the
// right leg and puts the PDF's figures back — choosing a route resets the
// cost to the route's default price. A passenger chosen afterwards refreshes
// the strip.
$(document).on('change', '#passengerContainer .ticket-select', function () {
  if (tiBusy) return;
  const card = tiCardOf(this);
  if (card && card.dataset.tiKey && this.value) {
    delete card.dataset.tiRouteAuto;   // staff chose this route
    setTimeout(() => {
      if (card.isConnected) tiBuild(card);
      // his other legs that were only auto-matched follow onto the same route
      const now = document.querySelector(`#passengerContainer .ds-pax-card[data-ti-key="${card.dataset.tiKey}"]`) || card;
      tiMates(now).filter(m => m.dataset.tiRouteAuto === '1' || !m.querySelector('.ticket-select').value).forEach(m => tiBuild(m));
    }, 0);
  }
});
$(document).on('change', '#passengerContainer .ph-select', function () {
  if (tiBusy) return;
  const card = tiCardOf(this);
  if (!card || !card.dataset.tiKey || !TI[card.dataset.tiKey]) return;
  // no route yet: building again lets the passenger's other leg pick between tied routes
  if (!card.querySelector('.ticket-select').value) { setTimeout(() => { if (card.isConnected) tiBuild(card); }, 0); return; }
  tiStrip(card, TI[card.dataset.tiKey]);
  if (tiWizIsOpen()) tiWizRender();
});

// ── Guided completion (owner, 2026-10-05) ───────────────────────
// "a pop up modal … for each passenger, for missing infos, so we can select
// and save just, and at the last, see full details and confirm". One step
// per imported card that still lacks something, showing only that; then a
// Review of every ticket; Confirm presses the page's own Save. Every choice
// is written straight into the real card, so the page and the modal can
// never disagree, and closing the modal loses nothing.
let tiWizKeys = [], tiWizAt = 0;   // tiWizAt === tiWizKeys.length → Review
const tiWizIsOpen = () => !!document.getElementById('tiWiz')?.classList.contains('open');
const tiCardByKey = k => document.querySelector(`#passengerContainer .ds-pax-card[data-ti-key="${k}"]`);
const tiField = (card, f) => card?.querySelector(`[name$="[${f}]"]`);
const tiSelText = el => (el && el.value) ? (el.options[el.selectedIndex]?.text || '').trim() : '';
const tiMoney = v => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const tiWhen = s => { const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/); if (!m) return s || ''; return `${m[3]} ${['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][+m[2] - 1]} ${m[1]}${m[4] ? ', ' + m[4] : ''}`; };

// ── Already entered? Another leg of the same order? (2026-10-05) ──
// This passenger's earlier tickets on this card's route (ENTRY_CHECKS, plus
// the server's look-up on both PNRs). The SAME leg near the same date, or on
// either of this PDF's PNRs, is the same ticket again → blocked until staff
// remove the card or say it is a different ticket. ANOTHER leg is the other
// half of the order → this card joins that order, so Ticket Orders shows
// "Ticket 1 of 2" and "Ticket 2 of 2" together however far apart they came.
const tiDay = s => { const d = new Date(String(s || '').slice(0, 10) + 'T00:00'); return isNaN(d) ? null : d; };
function tiLegLinks(card) {
  const out = { dupes: [], sibling: null };
  const t = TI[card.dataset.tiKey];
  const ph = card.querySelector('.ph-select')?.value, route = card.querySelector('.ticket-select')?.value;
  if (!ph) return out;
  const seq = String(tiField(card, 'leg_sequence')?.value || 1);
  const dep = tiDay(tiField(card, 'departure_at')?.value);
  const pnrs = t ? [t.res.pnr, t.res.gds_pnr, t.res.airline_pnr].filter(Boolean) : [];
  const onPnr = p => pnrs.some(x => String(p || '') === x || String(p || '').startsWith(x + ' ('));
  const near = (d, days) => { const a = tiDay(d); return !!(a && dep && Math.abs(a - dep) <= days * 864e5); };
  const seen = new Set();
  // the server's look-up: his live tickets on either PNR of this PDF
  if (t && String(t.pax.holder?.id || '') === ph) {
    (t.pax.existing || []).forEach(x => { seen.add(x.pnr); out.dupes.push({ msg: `Already entered: PNR ${x.pnr} (${tiWhen(x.date)}${x.amount ? ', ' + tiMoney(x.amount) : ''})`, id: x.id || null }); });
  }
  if (t?.pax.eticket && t.res.on_file?.etickets?.[t.pax.eticket] && !seen.has(t.res.on_file.etickets[t.pax.eticket])) {
    out.dupes.push({ msg: `E-ticket ${t.pax.eticket} is already entered (PNR ${t.res.on_file.etickets[t.pax.eticket]})`, id: null });
  }
  if (!route) return out;
  let best = null;
  (ENTRY_CHECKS.tickets || []).forEach(x => {
    if (String(x.ph) !== ph || String(x.t) !== route || seen.has(x.pnr)) return;
    if (String(x.s || 1) === seq) {
      if (onPnr(x.pnr) || near(x.d, 3)) out.dupes.push({ msg: `Already ${x.k === 'purchase' ? 'entered' : 'held (' + x.k + ')'} on this route${seq !== '1' || tiField(card, 'leg_sequence') ? ' · ticket ' + seq : ''}: PNR ${x.pnr} (${tiWhen(x.d)})`, id: x.k === 'purchase' ? x.id : null });
    } else if (x.g && (!dep || near(x.d, 60))) {
      const gap = dep && tiDay(x.d) ? Math.abs(tiDay(x.d) - dep) : 0;
      if (!best || gap < best.gap) best = { ...x, gap };
    }
  });
  // the same person, route and leg twice in THIS entry
  const twins = [...document.querySelectorAll('#passengerContainer .ds-pax-card')].filter(c => c !== card
    && c.querySelector('.ph-select')?.value === ph && c.querySelector('.ticket-select')?.value === route
    && String(tiField(c, 'leg_sequence')?.value || 1) === seq);
  if (twins.length) out.dupes.push({ msg: 'The same passenger, route and leg is on another card of this entry', id: null });
  out.sibling = best;
  return out;
}
// write (or clear) the order this card joins
function tiConnect(card) {
  const links = tiLegLinks(card);
  let inp = tiField(card, 'order_group');
  const g = links.sibling?.g || '';
  if (!inp && g) {
    inp = document.createElement('input'); inp.type = 'hidden'; inp.className = 'ti-order-group';
    inp.name = card.querySelector('.ticket-select').name.replace('[ticket_id]', '[order_group]');
    card.appendChild(inp);
  }
  if (inp) inp.value = g;
  return links;
}
// "What happened to the earlier ticket?" — the answer lives on the card
const TI_CONFLICT = {}, tiConflictAsked = new Set();
const tiDecision = card => { try { return JSON.parse(card?.dataset.tiDecision || 'null'); } catch (e) { return null; } };
const tiOld = d => (d && d.oldId) ? TI_CONFLICT[d.oldId] : null;
const tiPenalty = d => { const o = tiOld(d); return o && d.refund !== undefined && d.refund !== null && d.refund !== '' ? Math.max(0, o.amount - Number(d.refund)) : 0; };
function tiDecisionLine(d) {
  const o = tiOld(d), pnr = o?.pnr || ('#' + d.oldId);
  if (d.kind === 'reissue') return `Reissue: replaces PNR ${pnr}` + (tiPenalty(d) > 0 ? ` · airline keeps ${tiMoney(tiPenalty(d))}${d.fault ? ' (' + (d.fault === 'agent' ? 'agent side' : 'our side') + ')' : ''}` : ' · full refund');
  if (d.kind === 'reschedule') return `Reschedule: PNR ${pnr} moves to this flight and date — no new ticket`;
  return '';
}
// what the chosen answer still needs ('' = complete)
function tiDecisionGap(card) {
  const d = tiDecision(card);
  if (!d || !d.kind) return 'choose';
  if (d.kind === 'keep') return '';
  if (!d.oldId) return 'choose';
  const o = tiOld(d), cf = o?.contract_flight;
  if (!o) return 'loading';
  if (d.kind === 'reissue' && (card.querySelector('.pax-status')?.value || 'purchased') !== 'purchased') return 'purchased';
  if (d.kind === 'reissue' && cf && tiPenalty(d) > 0.004 && !d.fault) return 'fault';
  if (cf && Number(d.fee) > 0 && !d.fee_bearer) return 'fee_bearer';
  return '';
}
const tiBlocking = (card, links) => links.dupes.length && !!tiDecisionGap(card);
function tiFetchConflicts(ids) {
  ids = ids.filter(id => !tiConflictAsked.has(id));
  if (!ids.length || !TI_CONFLICT_URL) return;
  ids.forEach(id => tiConflictAsked.add(id));
  $.get(TI_CONFLICT_URL, { ids }).done(r => { (r.tickets || []).forEach(t => { TI_CONFLICT[t.id] = t; }); if (tiWizIsOpen()) tiWizRender(); });
}

function tiMissing(card) {
  if (!card) return [];
  const m = [];
  if (card.dataset.tiKey && tiDecision(card)?.kind === 'reschedule') return tiDecisionGap(card) ? ['duplicate'] : [];
  if (card.dataset.tiKey && tiBlocking(card, tiLegLinks(card))) m.push('duplicate');
  if (!card.querySelector('.ph-select')?.value && !tiAutoHolder(card)) m.push('passenger');
  if (!card.querySelector('.ticket-select')?.value) m.push('route');
  // Contract Flight (owner, 2026-10-17): passenger, ticket PNR, route, vendor and its cost are
  // required — everything else is optional and listed on the passenger card instead
  if (TI_CF && !(tiField(card, 'ticket_no')?.value || '').trim()) m.push('pnr');
  if ((card.querySelector('.pax-status')?.value || 'purchased') === 'purchased') {
    if (!card.querySelector('.pax-vendor')?.value && !card.querySelector('.pax-portal')?.value) m.push('seller');
    if (!(parseFloat(tiField(card, 'cost_amount')?.value) > 0)) m.push('cost');
  }
  return m;
}
// everyone imported on the same PNR — a group is answered once
function tiGroupCards(card) {
  const t = TI[card.dataset.tiKey];
  if (!t || !t.res.pnr) return [card];
  return [...document.querySelectorAll('#passengerContainer .ds-pax-card[data-ti-key]')].filter(c => TI[c.dataset.tiKey]?.res.pnr === t.res.pnr);
}

function tiWizOpen() {
  tiWizKeys = [...document.querySelectorAll('#passengerContainer .ds-pax-card[data-ti-key]')].map(c => c.dataset.tiKey);
  if (!tiWizKeys.length) return;
  tiWizAt = 0;
  tiOpenRow = null;
  document.getElementById('tiWiz').classList.add('open');
  tiWizRender();
}
function tiWizClose() { document.getElementById('tiWiz').classList.remove('open'); tiGlowFields(); }

const TI_NEED_LBL = { passenger: 'Passenger — choose or create', route: 'Route — choose from Manage Tickets', seller: 'Seller — vendor or portal', cost: 'Net cost', pnr: 'Ticket PNR — the booking’s own code, not the airline’s', duplicate: 'Already entered — say what happened to the earlier ticket' };

// The reader's notes for every imported ticket, under the table (owner, 2026-10-11):
// what is still to be typed (red) and what was read but should be checked (amber).
function tiWizNotesHtml() {
  const blocks = tiWizKeys.map((k, i) => {
    const card = tiCardByKey(k), t = TI[k]; if (!card || !t) return '';
    const need = tiMissing(card).map(m => `<li class="need">Fill by hand: ${tiEsc(TI_NEED_LBL[m] || m)}</li>`);
    const notes = (t.res.warnings || []).map(w => `<li>${tiEsc(w)}</li>`);
    if (!need.length && !notes.length) return '';
    return `<div class="tk">${i + 1}. ${tiEsc(t.pax.name || 'Passenger not read')} · ${tiEsc(t.res.pnr || 'no PNR')} <span class="ti-dim">— ${tiEsc(t.file?.name || '')}</span></div><ul>${need.join('')}${notes.join('')}</ul>`;
  }).join('');
  return blocks ? `<div class="ti-notes"><div class="h"><i class="fas fa-triangle-exclamation"></i> Notes from the import</div>${blocks}</div>` : '';
}

// After the modal closes: the fields still to fill glow red on the page, the ones
// the reader only guessed glow amber. Each stops glowing once it is changed.
function tiCheckFields(t) {
  const w = (t.res.warnings || []).join(' ').toLowerCase(), f = new Set();
  if (/passenger|name/.test(w)) f.add('passenger');
  if (/route|flight could not/.test(w)) f.add('route');
  if (/total|fare|per person|cost/.test(w)) f.add('cost');
  if (/e-?ticket|ticket number/.test(w)) f.add('eticket');
  return f;
}
function tiGlowEls(card, field) {
  const one = el => el ? [el] : [];
  switch (field) {
    case 'passenger': return one(card.querySelector('.ph-select'));
    case 'route':     return one(card.querySelector('.ticket-select'));
    case 'seller':    return [...one(card.querySelector('.pax-vendor')), ...one(card.querySelector('.pax-portal'))];
    case 'cost':      return one(tiField(card, 'cost_amount'));
    case 'eticket':   return one(tiField(card, 'name_ticket_no'));
  }
  return [];
}
function tiGlowFields() {
  let first = null;
  document.querySelectorAll('#passengerContainer .ds-pax-card[data-ti-key]').forEach(card => {
    const t = TI[card.dataset.tiKey]; if (!t) return;
    const need = new Set(tiMissing(card)), check = tiCheckFields(t);
    ['passenger', 'route', 'seller', 'cost', 'eticket'].forEach(field => {
      const cls = need.has(field) ? 'ti-glow' : check.has(field) ? 'ti-glow-check' : null;
      tiGlowEls(card, field).forEach(el => {
        // a select2 picker shows its own box — that is the one to light up
        const box = el.tagName === 'SELECT' ? el.nextElementSibling?.querySelector('.select2-selection') || el : el;
        box.classList.remove('ti-glow', 'ti-glow-check');
        if (!cls) return;
        box.classList.add(cls);
        if (cls === 'ti-glow' && !first) first = box;
        $(el).off('change.tiglow input.tiglow').on('change.tiglow input.tiglow', () => {
          box.classList.remove('ti-glow', 'ti-glow-check');
          $(el).off('change.tiglow input.tiglow');
        });
      });
    });
  });
  if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function tiWizGo(i) { tiWizApply(); tiWizAt = Math.max(0, Math.min(i, tiWizKeys.length)); tiWizRender(); }
function tiWizNext() {
  tiWizApply();
  let i = tiWizAt + 1;
  while (i < tiWizKeys.length && !tiMissing(tiCardByKey(tiWizKeys[i])).length) i++;
  tiWizAt = i; tiWizRender();
}

// Seller + cost from the step into the card. With "use for all" ticked, the
// field just changed goes to everyone on the PNR — what staff type for the
// group is the group's figure, even over a route's default price.
// ── Easier (2026-10-05) ──────────────────────────────────────────
// The seller last chosen for this kind of ticket on this airline (e.g.
// Galileo + Gulf Air → BSP IATA) is filled in next time. This browser only;
// a convenience — every value stays editable.
const tiSellerKey = t => 'tiSeller:' + (t.res.layout || '') + ':' + (t.res.carrier || '');
function tiRememberSeller(card) {
  const t = TI[card?.dataset.tiKey]; if (!t) return;
  const v = card.querySelector('.pax-vendor')?.value || '', p = card.querySelector('.pax-portal')?.value || '';
  if (!v && !p) return;
  try { localStorage.setItem(tiSellerKey(t), JSON.stringify({ v, p })); } catch (e) { /* storage off: nothing remembered */ }
}
function tiRecallSeller(card, t) {
  const vs = card.querySelector('.pax-vendor'), ps = card.querySelector('.pax-portal');
  if (!vs || !ps || vs.value || ps.value) return;
  let m = null;
  try { m = JSON.parse(localStorage.getItem(tiSellerKey(t)) || 'null'); } catch (e) { m = null; }
  if (m?.v && [...vs.options].some(o => o.value === m.v)) $(vs).val(m.v).trigger('change');
  else if (m?.p && [...ps.options].some(o => o.value === m.p)) $(ps).val(m.p).trigger('change');
}
// 👁 on a file row: the PDF in a new tab, to check what was read against it
function tiViewFile(btn) {
  const f = btn.closest('.ti-row')?.__tiFile; if (!f) return;
  const url = URL.createObjectURL(f);
  window.open(url, '_blank', 'noopener');
  setTimeout(() => URL.revokeObjectURL(url), 60000);
}

// ── Import CSV / Excel (owner, 2026-10-19) ──────────────────────
// One row per passenger ticket. The server reads the sheet (columns found by
// their heading — TicketSheetImporter) into the same result a PDF gives, so
// each row becomes an ordinary card through tiAddCard: same matcher, same
// Complete-details table, same Save. The sheet is never stored or attached.
const TI_SHEET_URL = @json($tiSheetUrl);
const TI_SHEET_MISS = { passenger: 'passenger', route: 'route', seller: 'seller', cost: 'net cost', pnr: 'ticket PNR', duplicate: 'earlier ticket decision' };
const tiIsPdf = f => /\.pdf$/i.test(f.name) || f.type === 'application/pdf';
const tiIsSheet = f => /\.(csv|xlsx|xls)$/i.test(f.name);

// Dropped files go to their own reader, whichever zone (or page spot) they land on.
function tiDropFiles(fileList) {
  const files = [...(fileList || [])];
  const pdfs = files.filter(tiIsPdf), sheets = TI_SHEET_URL ? files.filter(tiIsSheet) : [];
  if (!pdfs.length && !sheets.length) return false;
  tiToggle(true);
  if (pdfs.length) tiHandle(pdfs);
  if (sheets.length) tiHandleSheets(sheets);
  return true;
}

async function tiHandleSheets(files) {
  for (const f of files) await tiReadSheet(f);
}

async function tiReadSheet(file) {
  const r = document.createElement('div');
  r.className = 'ti-row wait';
  r.innerHTML = `<span class="ic"><i class="fas fa-spinner fa-spin"></i></span><span class="fn" title="${tiEsc(file.name)}">${tiEsc(file.name)}</span><span class="ly">Reading…</span><span class="rs"></span><span class="ti-btns"><button type="button" class="ti-rm" title="Remove this file and its cards" onclick="tiRemoveFile(this)"><i class="fas fa-xmark"></i></button></span>`;
  r.__tiFile = file;
  document.getElementById('tiList').appendChild(r);
  const done = (ok, layout, html) => {
    r.className = 'ti-row ' + (ok ? 'ok' : 'bad');
    r.querySelector('.ic').innerHTML = `<i class="fas ${ok ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i>`;
    r.querySelector('.ly').textContent = layout || '—';
    r.querySelector('.rs').innerHTML = html;
  };

  const fd = new FormData();
  fd.append('file', file);
  let res;
  // the token is sent here, not left to the page's ready-time ajaxSetup: a sheet dropped while the page is still loading must not get a 419
  try { res = await $.ajax({ url: TI_SHEET_URL, method: 'POST', data: fd, processData: false, contentType: false, headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } }); }
  catch (x) { done(false, '', tiEsc(x.responseJSON?.message || 'The server could not read it — try again.')); return; }
  if (!res.success) { done(false, '', tiEsc(res.message || 'Not read.')); return; }
  const label = res.label + (res.label === 'Excel' && res.sheet ? ' · ' + res.sheet : '');
  const n = (res.results || []).length;
  if (!n) { done(false, label, 'No ticket rows under the heading row.'); return; }

  const keysBefore = new Set(Object.keys(TI));
  let made = 0, skipped = 0;
  res.results.forEach(one => {
    // a row with no passenger is still one card with what it gave — staff pick the passenger
    const pax = (one.passengers || [])[0] || { name: '', raw_name: '', title: null, passport_no: null, dob: null, eticket: null, pax_type: 'adult', fare: null };
    tiAddCard(one, pax, file) ? made++ : skipped++;
  });
  const added = [...new Set(res.results.flatMap(one => one.added || []))];
  const miss = new Set();
  Object.keys(TI).filter(k => !keysBefore.has(k)).forEach(k => tiMissing(tiCardByKey(k)).forEach(x => miss.add(TI_SHEET_MISS[x] || x)));
  done(made > 0, label,
    `${n} row${n === 1 ? '' : 's'} → ${made} card${made === 1 ? '' : 's'}`
    + (skipped ? ` · ${skipped} already in this entry` : '')
    + (res.skipped ? ` · ${Number(res.skipped)} total / note row${res.skipped === 1 ? '' : 's'} left out` : '')
    + (added.length ? ` · <span style="color:#15803d">added ${tiEsc(added.join(', '))}</span>` : '')
    + (res.truncated ? `<br><span style="color:#b45309">Only the first ${Number(res.limit)} rows were read — import the rest as a second file.</span>` : '')
    + (res.turned || []).map(m => `<br><span style="color:#b45309">${tiEsc(m)} — check the dates on the cards.</span>`).join('')
    + ((res.unused || []).length ? `<br><span style="color:var(--text3)">Columns not used: ${tiEsc(res.unused.join(', '))}</span>` : '')
    + (miss.size ? `<br><span style="color:#b45309">Not in the sheet — enter before saving: <b>${tiEsc([...miss].join(', '))}</b></span>` : ''));
  renumberPax(); runChecks(); recalcSummary();
  if (made) {
    document.getElementById('tiReopen').hidden = false;
    tiWizOpen();
  }
}

// A row's Seller / Agent / Agent price, matched by name against the card's own
// lists: one clear match is filled; none or several is left for staff — never guessed.
// The daily sheet writes "SOGIB VAI [FLIGHT]" where the ERP has "MR SOGIB MIAH (CYP)":
// the bracketed tag and the courtesy words are not the name.
const tiPartyKey = s => String(s || '').toUpperCase().replace(/[(\[][^)\]]*([)\]]|$)/g, ' ')
  .replace(/\b(MR|MRS|MS|MISS|DR|SIR|VAI|BHAI|BHAIYA|APA)\b\.?/g, ' ').replace(/[^A-Z0-9]+/g, '');
function tiOptByName(sel, name) {
  const full = s => String(s || '').toUpperCase().replace(/[^A-Z0-9]+/g, '');
  if (!sel || !full(name)) return null;
  const opts = [...sel.options].filter(o => o.value);
  const one = list => (list.length === 1 ? list[0].value : '');   // several = not ours to choose
  let hit = opts.filter(o => full(o.text) === full(name));
  if (hit.length) return { value: one(hit), exact: true };
  const want = tiPartyKey(name);
  if (want.length < 4) return null;
  hit = opts.filter(o => tiPartyKey(o.text) === want);
  if (!hit.length) hit = opts.filter(o => { const k = tiPartyKey(o.text); return k.length >= 4 && (k.includes(want) || want.includes(k)); });
  return { value: one(hit), exact: false };
}
function tiSheetFill(card, t) {
  const s = t.res.sheet || {};
  const note = m => { if (!t.res.warnings.includes(m)) t.res.warnings.push(m); };
  // the seller the row names wins over the route's default seller — never over one picked by hand
  const vs = card.querySelector('.pax-vendor'), ps = card.querySelector('.pax-portal');
  if (s.seller && vs && ps && card.dataset.tiSellerByHand !== '1') {
    const v = tiOptByName(vs, s.seller), p = v?.value ? null : tiOptByName(ps, s.seller);
    const [el, other, m] = v?.value ? [vs, ps, v] : p?.value ? [ps, vs, p] : [null, null, null];
    if (el) {
      if (other.value) $(other).val('').trigger('change');
      if (el.value !== m.value) $(el).val(m.value).trigger('change');
      if (!m.exact) note(`Seller "${s.seller}" read as ${tiSelText(el)} — check it`);
    } else note(`Seller "${s.seller}" is not one vendor or portal here — check the seller`);
  }
  if (tiCfBound(card)) return;   // billed per head on the contract flight — no agent or price here
  const to = card.querySelector('.ds-sale-to'), pr = card.querySelector('.ds-sale');
  if (s.agent && to && !to.value) {
    const a = tiOptByName(to, s.agent);
    if (a?.value) {
      $(to).val(a.value).trigger('change.select2');
      if (!a.exact) note(`Agent "${s.agent}" read as ${tiSelText(to)} — check it`);
    } else note(`Agent "${s.agent}" is not one agent here — choose it`);
  }
  if (s.sale != null && pr && pr.value === '') pr.value = Number(s.sale).toFixed(2);
}
// a seller chosen by hand on an imported card stays — the sheet's name no longer overrides it
$(document).on('select2:select select2:clear', '#passengerContainer .pax-vendor, #passengerContainer .pax-portal', function () {
  const c = tiCardOf(this);
  if (c) c.dataset.tiSellerByHand = '1';
});

function tiWizApply(changed) {
  if (!tiWizIsOpen() || tiWizAt >= tiWizKeys.length) return;
  const card = tiCardByKey(tiWizKeys[tiWizAt]); if (!card) return;
  const vEl = document.getElementById('tiWzVendor'), pEl = document.getElementById('tiWzPortal'), cEl = document.getElementById('tiWzCost');
  if (!vEl && !cEl) return;
  const v = vEl?.value || '', p = pEl?.value || '', cost = cEl ? cEl.value : '';
  const all = document.getElementById('tiWzAll')?.checked;
  const group = all ? tiGroupCards(card) : [card];
  const setSeller = c => {
    const cv = c.querySelector('.pax-vendor'), cp = c.querySelector('.pax-portal');
    if (v && cv.value !== v) { if (cp.value) $(cp).val('').trigger('change'); $(cv).val(v).trigger('change'); }
    if (!v && p && cp.value !== p) { if (cv.value) $(cv).val('').trigger('change'); $(cp).val(p).trigger('change'); }
  };
  const setCost = c => { const net = tiField(c, 'cost_amount'); if (net && cost !== '') { net.value = (+cost).toFixed(2); net.dataset.edited = '1'; } };
  if (v || p) (changed === 'seller' || changed === 'all' ? group : [card]).forEach(setSeller);
  (changed === 'cost' || changed === 'all' ? group : [card]).forEach(setCost);
  const bEl = document.getElementById('tiWzBuyer'), sEl = document.getElementById('tiWzSale');
  if (bEl || sEl) {
    const setSale = c => {
      if (tiCfBound(c)) return;   // billed per head on the contract flight
      const to = c.querySelector('.ds-sale-to'), pr = c.querySelector('.ds-sale');
      if (to && bEl && to.value !== bEl.value) $(to).val(bEl.value).trigger('change.select2');
      if (pr && sEl) pr.value = sEl.value;
      profitOf(c);
    };
    (changed === 'sale' || changed === 'all' ? group : [card]).forEach(setSale);
    const out = document.getElementById('tiWzProfit');
    if (out) { const pv = card.querySelector('.ds-profit')?.value; out.textContent = pv === '' || pv == null ? '—' : tiMoney(pv); out.className = 'ti-profit' + (pv > 0 ? ' pos' : pv < 0 ? ' neg' : ''); }
  }
  recalcSummary(); runChecks();
}

// A ticket that is (or becomes) a contract-flight passenger's is billed per
// head on the flight — never sold again here (that would bill the agent twice).
function tiCfBound(card) {
  const d = tiDecision(card);
  if (d && ['reissue', 'reschedule'].includes(d.kind) && TI_CONFLICT[d.oldId]?.contract_flight) return TI_CONFLICT[d.oldId].contract_flight.flight_no || 'contract flight';
  const sib = card.dataset.tiKey ? tiLegLinks(card).sibling : null;
  return sib && Number(sib.cf) > 0 ? 'the contract flight of PNR ' + sib.pnr : '';
}
function tiSaleStepHtml(card) {
  const cf = tiCfBound(card);
  if (cf) return `<p style="font-size:12px;color:var(--text2);margin:10px 0 0"><i class="fas fa-plane"></i> Billed per head on ${tiEsc(cf)} — the agent price and profit are there, not on this ticket.</p>`;
  const to = card.querySelector('.ds-sale-to')?.value || '', price = card.querySelector('.ds-sale')?.value || '';
  const opts = to ? buyerOptions.replace(`value="${to}"`, `value="${to}" selected`) : buyerOptions;
  const pv = card.querySelector('.ds-profit')?.value;
  return `<div class="ti-grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr) minmax(0,1fr);margin-top:12px">
    <div><label class="ds-field-lbl">Sold to (agent)</label><select id="tiWzBuyer" class="ti-s2" data-ph="— Not sold yet —" onchange="tiWizApply('sale')"><option value=""></option>${opts}</select></div>
    <div><label class="ds-field-lbl">Agent price</label><input type="number" id="tiWzSale" class="ds-field-input" step="0.01" min="0" style="text-align:right" value="${tiEsc(price)}" onchange="tiWizApply('sale')"></div>
    <div><label class="ds-field-lbl">Profit</label><div id="tiWzProfit" class="ti-profit${pv > 0 ? ' pos' : pv < 0 ? ' neg' : ''}">${pv === '' || pv == null ? '—' : tiMoney(pv)}</div></div></div>`;
}

function tiWzHolder(id) {
  if (!id) return;
  tiWizApply();
  $(tiCardByKey(tiWizKeys[tiWizAt]).querySelector('.ph-select')).val(String(id)).trigger('change');
}
function tiWzRoute(id) {
  if (!id) return;
  tiWizApply();
  $(tiCardByKey(tiWizKeys[tiWizAt]).querySelector('.ticket-select')).val(String(id)).trigger('change');
}
function tiWzRemove() {
  const card = tiCardByKey(tiWizKeys[tiWizAt]); if (!card) return;
  if (document.querySelectorAll('#passengerContainer .ds-pax-card').length === 1) addPassenger();   // the entry keeps one card
  card.remove();
  renumberPax(); runChecks(); recalcSummary();
  tiWizRender();
}
function tiWzKeep() {
  const card = tiCardByKey(tiWizKeys[tiWizAt]); if (!card) return;
  card.dataset.tiKeep = '1';
  card.dataset.tiDecision = JSON.stringify({ kind: 'keep' });
  tiStrip(card, TI[card.dataset.tiKey]);
  tiWizRender();
}
function tiWzNewHolder() { tiWizApply(); tiNewHolder(tiCardByKey(tiWizKeys[tiWizAt]).querySelector('.ph-create-btn')); }
function tiWzNewRoute() { tiWizApply(); tiNewRoute(tiCardByKey(tiWizKeys[tiWizAt]).querySelector('.ds-route-btn')); }

// ONE table instead of steps (owner, 2026-10-05: "still it looks
// complicated" → the modal steps). A row per imported ticket: what is
// already settled is plain text with a ✓, only what is missing is a box,
// ↓ copies the first row's value down (a group PNR is answered once), a
// duplicate opens its question under its own row. Every box writes straight
// into the real card; Confirm & Save stays locked while anything is red.
let tiOpenRow = null;   // the ticket key whose "already entered" question is open
const tiSellerOptions = () =>
  `<optgroup label="Vendors">${vendorOptions.replace(/value="(\d+)"/g, 'value="v:$1"')}</optgroup><optgroup label="Portals">${portalOptions.replace(/value="(\d+)"/g, 'value="p:$1"')}</optgroup>`;
const tiSel = (html, val) => val ? html.replace(`value="${val}"`, `value="${val}" selected`) : html;

function tiWizRender() {
  tiWizKeys = tiWizKeys.filter(k => tiCardByKey(k));
  if (!tiWizKeys.length) { tiWizClose(); return; }
  const need = tiWizKeys.filter(k => tiMissing(tiCardByKey(k)).length).length;
  const $body = $('#tiWizBody');
  $body.find('select').each(function () { if ($(this).data('select2')) $(this).select2('destroy'); });
  document.getElementById('tiWizDots').innerHTML = '';
  document.getElementById('tiWizSub').textContent = `${tiWizKeys.length} ticket${tiWizKeys.length === 1 ? '' : 's'}` + (need ? ` · ${need} to complete — fill the red boxes` : ' · all complete');

  let cost = 0, sale = 0, profit = 0;
  const rows = tiWizKeys.map((k, i) => {
    const card = tiCardByKey(k), t = TI[k], miss = tiMissing(card);
    const dec = tiDecision(card), resched = dec?.kind === 'reschedule', cfb = tiCfBound(card);
    const links = tiLegLinks(card);
    const ok = '<i class="fas fa-check ti-ok"></i>';
    const red = m => miss.includes(m) ? ' ti-need' : '';
    const out = (t.res.segments || []).filter(s => s.direction !== 'return');
    const sector = [out[0]?.from, ...out.map(s => s.to)].filter(Boolean).join(' → ');   // transit stops shown

    // passenger
    const ph = card.querySelector('.ph-select'), auto = tiAutoHolder(card);
    const pax = ph.value
      ? `${ok}<span class="ti-t">${tiEsc(tiSelText(ph))}</span>`
      : auto
        ? `<span class="ti-t ti-new">New: ${tiEsc(auto.name)}</span><small class="ti-mono">${tiEsc(auto.passport_no)}</small>`
        : `<div class="ti-pick-pax${red('passenger')}"><select class="ti-s2" data-ph="${tiEsc(t.pax.name)} — choose" onchange="tiRow('${k}','holder',this.value)"><option value=""></option>${(t.pax.holder?.options || []).map(o => `<option value="${o.id}">★ ${tiEsc(o.label)}</option>`).join('')}${allPhOptions()}</select>
           <button type="button" class="ti-mini" title="Create ${tiEsc(t.pax.name)} as a new passenger" onclick="tiRow('${k}','newHolder')">+ New</button></div>`;

    // route
    const rs = card.querySelector('.ticket-select'), seq = tiField(card, 'leg_sequence')?.value;
    const cands = (t.res.route?.candidates || []);
    // always a picker (owner, 2026-10-07): the import's match is pre-selected but stays changeable
    const candOpts = cands.filter(c => String(c.id) !== String(rs.value)).map(c => {
      const o = ticketOptions.match(new RegExp(`<option value="${c.id}"[^>]*>`));
      return (o ? o[0] : `<option value="${c.id}">`) + `★ ${tiEsc(c.title)}${c.leg ? ' · ticket ' + c.leg : ''}</option>`;
    }).join('');
    const route = `<div class="${red('route').trim()}"><select class="ti-s2 ti-route" data-ph="Choose route" onchange="tiRow('${k}','route',this.value)"><option value=""></option>${candOpts}${tiSel(ticketOptions, rs.value)}</select></div>`
        + (rs.value && seq ? `<small>ticket ${tiEsc(seq)}</small>` : '')
        + (!rs.value && t.res.route?.title ? `<button type="button" class="ti-mini" onclick="tiRow('${k}','newRoute')">+ New ${tiEsc(t.res.route.title)}</button>` : '');

    // seller · cost · agent · price · profit
    const v = card.querySelector('.pax-vendor')?.value, p = card.querySelector('.pax-portal')?.value;
    const netV = tiField(card, 'cost_amount')?.value || '';
    const net = resched ? 0 : (parseFloat(netV) || 0);
    const buyer = card.querySelector('.ds-sale-to')?.value || '', priceV = card.querySelector('.ds-sale')?.value || '';
    const pr = priceV === '' || resched || cfb ? null : (parseFloat(priceV) || 0) - net;
    cost += net; if (pr !== null) { sale += parseFloat(priceV) || 0; profit += pr; }
    const money = resched
      ? `<td colspan="5" class="ti-dim">No new ticket — ${tiEsc(tiDecisionLine(dec))}</td>`
      : `<td><div class="${red('seller').trim()}"><select class="ti-s2" data-ph="Seller" onchange="tiRow('${k}','seller',this.value)"><option value=""></option>${tiSel(tiSellerOptions(), v ? 'v:' + v : p ? 'p:' + p : '')}</select></div></td>
         <td><input type="number" min="0" step="0.01" class="ds-field-input ti-num${red('cost')}" value="${tiEsc(String(netV).replace(/\.00$/, ''))}" onchange="tiRow('${k}','cost',this.value)"></td>
         ${cfb ? `<td colspan="2" class="ti-dim"><i class="fas fa-plane"></i> billed on ${tiEsc(cfb)}</td>`
               : `<td><select class="ti-s2" data-ph="Not sold" onchange="tiRow('${k}','buyer',this.value)"><option value=""></option>${tiSel(buyerOptions, buyer)}</select></td>
                  <td><input type="number" min="0" step="0.01" class="ds-field-input ti-num" value="${tiEsc(priceV)}" onchange="tiRow('${k}','price',this.value)"></td>`}
         <td class="ti-num-c">${pr === null ? '<span class="ti-dim">—</span>' : `<span class="ti-pf ${pr < 0 ? 'neg' : ''}">${pr < 0 ? '−' : '+'}${tiMoney(Math.abs(pr))}</span>`}</td>`;

    // state: done, still missing, or a decision to make
    const dupOpen = tiOpenRow === k;
    const state = links.dupes.length && (!dec || tiDecisionGap(card))
      ? `<button type="button" class="ti-mini ti-warn" onclick="tiRow('${k}','dup')">${dupOpen ? '▾' : '▸'} Already entered</button>`
      : dec && dec.kind !== 'keep' ? `<span class="ti-dim" title="${tiEsc(tiDecisionLine(dec))}"><i class="fas fa-rotate"></i> ${dec.kind}</span> <button type="button" class="ti-link" onclick="tiRow('${k}','dup')">change</button>`
      : miss.length ? '' : ok;
    const empty = tiDetEmpty(card), detOpen = tiDetOpen === k;
    const det = `<button type="button" class="ti-link" style="white-space:nowrap" title="Ticket details the PDF did not fill — e-ticket, issue date, class, fare, each flight's airline" onclick="tiRow('${k}','det')">${detOpen ? '▾' : '▸'} Details${empty ? ` <span class="ti-dim">(${empty} empty)</span>` : ''}</button>`;

    const row = `<tr class="${miss.length ? 'ti-row-need' : ''}">
      <td class="ti-dim">${i + 1}</td>
      <td>${pax}</td>
      <td>${TI_CF && miss.includes('pnr')
        // Contract Flight (owner, 2026-10-17): the ticket PNR is required — typed here when the PDF gave none
        ? `<input type="text" maxlength="20" class="ds-field-input ti-mono ti-need" style="height:32px;max-width:130px" title="Ticket PNR — the booking’s own code, not the airline’s" onchange="tiRow('${k}','pnr',this.value)">`
        : `<span class="ds-rt">${tiAirlineMark(card)}<span class="ti-t ti-mono"${t.res.airline_pnr ? ` title="Airline PNR ${tiEsc(t.res.airline_pnr)}"` : ''}>${tiEsc((TI_CF ? tiField(card, 'ticket_no')?.value : '') || t.res.pnr || '—')}</span></span>`}<small>${tiEsc(sector)}${out[0]?.dep ? ' · ' + tiEsc(tiWhen(out[0].dep)) : ''}</small></td>
      <td>${route}</td>
      ${money}
      <td class="ti-state">${state} ${det}</td></tr>`;
    const detRow = detOpen ? `<tr class="ti-subrow"><td></td><td colspan="10">${tiDetHtml(card, k)}</td></tr>` : '';
    if (!dupOpen) return row + detRow;
    tiWizAt = i;   // the question's buttons (tiWzDec / tiWzRemove / tiWzKeep) act on this row
    return row + `<tr class="ti-subrow"><td></td><td colspan="10">${tiDecisionHtml(card, links)}</td></tr>` + detRow;
  }).join('');

  const down = f => `<button type="button" class="ti-down" title="Same for every row (copies row 1)" onclick="tiCopyDown('${f}')">↓</button>`;
  $body.html(tiMissingAirlinesHtml() + `<table class="ti-tbl">
    <thead><tr><th>#</th><th>Passenger</th><th>Ticket</th><th>Route</th><th>Seller ${down('seller')}</th><th class="r">Net cost ${down('cost')}</th><th>Agent ${down('buyer')}</th><th class="r">Agent price ${down('price')}</th><th class="r">Profit</th><th></th></tr></thead>
    <tbody>${rows}</tbody>
    <tfoot><tr><td></td><td colspan="4">Total</td><td class="r">${tiMoney(cost)}</td><td></td><td class="r">${sale ? tiMoney(sale) : '—'}</td><td class="r">${sale ? `<span class="ti-pf ${profit < 0 ? 'neg' : ''}">${profit < 0 ? '−' : '+'}${tiMoney(Math.abs(profit))}</span>` : '—'}</td><td></td></tr></tfoot>
  </table>` + tiWizNotesHtml());
  $body.find('select.ti-s2').each(function () {
    const tpl = tiWizTpl(this);
    $(this).select2({ dropdownParent: $('#tiWiz .ti-wiz-box'), width: '100%', placeholder: $(this).data('ph') || '', templateResult: tpl, templateSelection: tpl });
  });

  document.getElementById('tiWizFt').innerHTML = `<span class="ti-dim" style="font-size:12px">Saved exactly as Save Purchase(s) — ledger and journals post as usual.</span><span class="sp"></span>
    <button type="button" class="erp-btn btn-ghost" onclick="tiWizClose()">Close &amp; edit on page</button>
    <button type="button" class="erp-btn btn-green-s" id="tiWizConfirm" onclick="tiWizConfirm()" ${need ? 'disabled title="Fill the red boxes first" style="opacity:.5;cursor:not-allowed"' : ''}><i class="fas fa-check"></i> Confirm &amp; Save</button>`;
}

// Logos in the import table (owner, 2026-10-07): route flags, airline mark, seller / agent icons —
// the same marks as the page's own pickers.
function tiAirlineMark(card) {
  const vc = card.querySelector('.ds-vc'), name = vc && vc.value ? tiSelText(vc) : '';
  if (!name || typeof airlineBrand !== 'function') return '';
  const b = airlineBrand(name);
  return b.slug ? `<img class="ds-blogo" src="${@json(asset('airline-logos'))}/${b.slug}.png" alt="" title="${tiEsc(name)}" onerror="this.remove()">`
    : `<span class="ds-blogo ds-alogo-x" style="background:${b.color}" title="${tiEsc(name)}">${tiEsc((airlineCode(name) || name.slice(0, 2)).toUpperCase())}</span>`;
}
function tiWizTpl(sel) {
  const flags = @json(asset('flags'));
  const flag = cc => cc ? `<img class="ds-rflag" src="${flags}/${cc}.png" alt="" onerror="this.remove()">` : '';
  const icon = (ic, c, t) => $(`<span class="ds-rt"><i class="fas ${ic} ds-oic" style="color:${c};background:${c}1a"></i><span class="ds-rt-t">${t}</span></span>`);
  return o => {
    if (!o.id || !o.element) return o.text;
    const t = $('<span>').text(o.text).html();
    if (sel.classList.contains('ti-route')) { const d = o.element.dataset; return $(`<span class="ds-rt">${flag(d.ff)}<span class="ds-rt-t">${t}</span>${flag(d.tf)}</span>`); }
    const id = String(o.id);
    if (sel.classList.contains('ti-al') && typeof airlineBrand === 'function') {
      const b = airlineBrand(o.text);
      return $(`<span class="ds-rt">${b.slug ? `<img class="ds-blogo" src="${@json(asset('airline-logos'))}/${b.slug}.png" alt="" onerror="this.remove()">` : `<span class="ds-blogo ds-alogo-x" style="background:${b.color}">${tiEsc((airlineCode(o.text) || o.text.slice(0, 2)).toUpperCase())}</span>`}<span class="ds-rt-t">${t}</span></span>`);
    }
    if (id.startsWith('v:')) return icon('fa-store', '#d97706', t);
    if (id.startsWith('p:')) return icon('fa-globe', '#0284c7', t);
    if (/'buyer'/.test(sel.getAttribute('onchange') || '')) return icon('fa-user-tie', '#059669', t);
    if (/'holder'/.test(sel.getAttribute('onchange') || '')) return icon('fa-passport', '#7c3aed', t);
    return o.text;
  };
}

// one box of one row → the real card
function tiRow(k, what, val) {
  const card = tiCardByKey(k); if (!card) return;
  const i = tiWizKeys.indexOf(k); if (i >= 0) tiWizAt = i;
  switch (what) {
    case 'holder': if (val) $(card.querySelector('.ph-select')).val(String(val)).trigger('change'); return;    // the change handler repaints
    case 'route': if (val) $(card.querySelector('.ticket-select')).val(String(val)).trigger('change'); return;
    case 'newHolder': tiNewHolder(card.querySelector('.ph-create-btn')); return;
    case 'newRoute': tiNewRoute(card.querySelector('.ds-route-btn')); return;
    case 'dup': tiOpenRow = tiOpenRow === k ? null : k; break;
    case 'det': tiDetOpen = tiDetOpen === k ? null : k; break;
    case 'seller': {
      card.dataset.tiSellerByHand = '1';   // a sheet row's seller name no longer overrides it
      const cv = card.querySelector('.pax-vendor'), cp = card.querySelector('.pax-portal');
      const [kind, id] = String(val || ':').split(':');
      if (kind === 'v') { if (cp.value) $(cp).val('').trigger('change'); $(cv).val(id).trigger('change'); }
      else if (kind === 'p') { if (cv.value) $(cv).val('').trigger('change'); $(cp).val(id).trigger('change'); }
      else { $(cv).val('').trigger('change'); $(cp).val('').trigger('change'); }
      break;
    }
    case 'cost': { const n = tiField(card, 'cost_amount'); if (n) { n.value = val === '' ? '' : (+val).toFixed(2); n.dataset.edited = val === '' ? '' : '1'; } break; }
    case 'buyer': $(card.querySelector('.ds-sale-to')).val(val || '').trigger('change.select2'); break;
    case 'price': { const s = card.querySelector('.ds-sale'); if (s) s.value = val; break; }
    // Contract Flight (2026-10-17): the ticket PNR typed in the table's Ticket cell
    case 'pnr': { const f = tiField(card, 'ticket_no'); if (f) { f.value = String(val || '').trim().toUpperCase(); f.dispatchEvent(new Event('input', { bubbles: true })); } break; }
  }
  profitOf(card); recalcSummary(); runChecks();
  tiWizRender();
}

// ── What the PDF did not fill, filled here (owner, 2026-10-07) ─────────────
// "Details" on a row opens the rest of that ticket's fields, written straight
// into its card; an airline the PDF names but Airlines does not know is added
// from the top of the table, like + New route, and lands on every ticket that
// flies it.
let tiDetOpen = null;
const TI_AIRLINE_URL = @json(Route::has('role.airlines.store') ? route('role.airlines.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) : null);
const TI_AL_NAMES = { QR: 'Qatar Airways', PC: 'Pegasus Airlines', GF: 'Gulf Air', TK: 'Turkish Airlines', EK: 'Emirates', FZ: 'Flydubai',
  G9: 'Air Arabia', '6E': 'IndiGo', BS: 'US-Bangla Airlines', BG: 'Biman Bangladesh Airlines', SV: 'Saudia', WY: 'Oman Air', J9: 'Jazeera Airways',
  KU: 'Kuwait Airways', MH: 'Malaysia Airlines', OD: 'Batik Air Malaysia', AK: 'AirAsia', UL: 'SriLankan Airlines', AI: 'Air India', TG: 'Thai Airways',
  SQ: 'Singapore Airlines', CZ: 'China Southern Airlines', MU: 'China Eastern Airlines', EY: 'Etihad Airways', XY: 'flynas', '3L': 'Air Arabia Abu Dhabi' };
const TI_DET_FIELDS = [
  ['name_ticket_no', 'E-ticket no.', 'text'], ['purchase_date', 'Issue date', 'date'], ['ticket_class', 'Class', 'select'],
  ['rbd', 'RBD', 'text'], ['baggage', 'Baggage', 'text'], ['flight_no', 'Flight no.', 'text'],
  ['base_fare', 'Base fare', 'number'], ['tax_amount', 'Tax', 'number'], ['tax_breakdown', 'Tax breakdown', 'text'],
];

// the card's Route leg rows: [{ from, to, sel }] — sel is the row's airline picker, when shown
function tiLegRows(card) {
  const idx = parseInt((card.querySelector('.ticket-select')?.name.match(/passengers\[(\d+)\]/) || [])[1], 10);
  return [...(document.getElementById('legsContainer_' + idx)?.querySelectorAll('[data-leg-index]') || [])].map(r => ({
    from: (r.querySelector('[name$="[from_location]"]')?.value || '').toUpperCase(),
    to: (r.querySelector('[name$="[to_location]"]')?.value || '').toUpperCase(),
    sel: r.querySelector('.ds-leg-airline'),
  }));
}
function tiDetEmpty(card) {
  const f = TI_DET_FIELDS.filter(([n]) => n !== 'tax_breakdown' && !(tiField(card, n)?.value || '').trim()).length;
  return f + tiLegRows(card).filter(l => l.sel && !l.sel.value).length;
}
function tiDetHtml(card, k) {
  const cell = ([n, label, type]) => {
    const el = tiField(card, n); if (!el) return '';
    const v = el.value || '', blank = !v.trim() ? ' style="border-style:dashed;border-color:#f59e0b"' : '';
    const on = `onchange="tiDet('${k}','${n}',this.value)"`;
    const input = type === 'select'
      ? `<select class="ds-field-input" ${on}${blank}>${[...el.options].map(o => `<option value="${tiEsc(o.value)}" ${o.value === v ? 'selected' : ''}>${tiEsc(o.text)}</option>`).join('')}</select>`
      : `<input type="${type}" class="ds-field-input" value="${tiEsc(type === 'date' ? v.slice(0, 10) : v)}" ${type === 'number' ? 'step="0.01" min="0" style="text-align:right"' : ''} ${on}${blank}>`;
    return `<div><label class="ds-field-lbl">${label}</label>${input}</div>`;
  };
  const legs = tiLegRows(card).map((l, i) => l.sel
    ? `<div><label class="ds-field-lbl">${tiEsc(l.from || '?')} → ${tiEsc(l.to || '?')} airline</label><select class="ti-s2 ti-al" data-ph="— Airline —" onchange="tiDetLeg('${k}',${i},this.value)"><option value=""></option>${tiSel(airlineOptions, l.sel.value)}</select></div>` : '').join('');
  return `<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;padding:6px 0">${TI_DET_FIELDS.map(cell).join('')}${legs}</div>`;
}
function tiDet(k, n, v) {
  const card = tiCardByKey(k); if (!card) return;
  const el = tiField(card, n); if (!el) return;
  if (el.tagName === 'SELECT') $(el).val(v).trigger('change');
  else if (el.hasAttribute('data-dt') || el.classList.contains('epal-dt')) { if (window.EpalDateTime) window.EpalDateTime.upgrade(el); el.value = v; $(el).trigger('change'); }
  else { el.value = v; $(el).trigger('input').trigger('change'); }
  profitOf(card); recalcSummary(); runChecks();
  tiWizRender();
}
function tiDetLeg(k, i, v) {
  const card = tiCardByKey(k), l = card && tiLegRows(card)[i]; if (!l?.sel) return;
  $(l.sel).val(v || '').trigger('change');
  tiWizRender();
}

// airline codes on the imported tickets that Airlines does not know
function tiMissingAirlines() {
  const codes = new Set();
  tiWizKeys.forEach(k => {
    const res = TI[k]?.res; if (!res) return;
    (res.segments || []).forEach(s => { if (s.carrier && !s.airline_id) codes.add(s.carrier); });
    if (res.carrier && !res.carrier_airline) codes.add(res.carrier);
  });
  return [...codes];
}
function tiMissingAirlinesHtml() {
  const codes = tiMissingAirlines(); if (!codes.length) return '';
  const btns = codes.map(c => TI_AIRLINE_URL
    ? `<button type="button" class="ti-mini" onclick="tiAddAirline('${tiEsc(c)}')">+ Add ${tiEsc(TI_AL_NAMES[c] || c)} (${tiEsc(c)})</button>`
    : `<b>${tiEsc(c)}</b>`).join(' ');
  return `<div class="ds-chk warn" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 10px"><i class="fas fa-triangle-exclamation"></i>Not in Airlines: ${btns}</div>`;
}
async function tiAddAirline(code) {
  const r = await Swal.fire({ title: 'Add airline', input: 'text', inputValue: `${TI_AL_NAMES[code] || ''} (${code})`.trim(),
    inputLabel: 'Name with the IATA code in brackets — that is how tickets find it', showCancelButton: true, confirmButtonText: 'Add',
    inputValidator: v => !new RegExp(`\\(${code}\\)\\s*$`, 'i').test(v || '') ? `Keep "(${code})" at the end of the name` : undefined });
  if (!r.isConfirmed) return;
  $.ajax({ url: TI_AIRLINE_URL, method: 'POST', headers: { Accept: 'application/json' }, data: { _token: $('meta[name="csrf-token"]').attr('content'), name: r.value.trim(), status: 1 } })
    .done(res => { if (res?.airline) tiAirlineAdded(code, res.airline); })
    .fail(x => Swal.fire({ icon: 'error', title: 'Not added', text: x.responseJSON?.errors?.name?.[0] || x.responseJSON?.message || (x.status === 403 ? 'You do not have permission to create airlines.' : 'Server error.') }));
}
// the new airline into every picker on the page, and onto every imported ticket that flies it
function tiAirlineAdded(code, a) {
  const id = String(a.id);
  airlineOptions += `<option value="${id}">${tiEsc(a.name)}</option>`;
  $('.ds-vc, .ds-leg-airline').each(function () { if (!this.querySelector(`option[value="${id}"]`)) $(this).append(new Option(a.name, id, false, false)); });
  Object.values(TI).forEach(t => {
    let hit = false;
    (t.res.segments || []).forEach(s => { if (s.carrier === code && !s.airline_id) { s.airline_id = a.id; hit = true; } });
    if (t.res.carrier === code && !t.res.carrier_airline) { t.res.carrier_airline = { id: a.id, name: a.name }; hit = true; }
    const card = hit && tiCardByKey(t.key); if (!card) return;
    const vc = card.querySelector('.ds-vc');
    if (vc && !vc.value && t.res.carrier === code) $(vc).val(id).trigger('change');
    tiLegRows(card).forEach(l => {
      const s = (t.res.segments || []).find(s => s.carrier === code && s.from === l.from && s.to === l.to);
      if (s && l.sel && !l.sel.value) $(l.sel).val(id).trigger('change');
    });
    tiStrip(card, t);
  });
  Swal.fire({ icon: 'success', title: 'Added', text: `${a.name} added and filled in.`, timer: 1600, showConfirmButton: false });
  tiWizRender();
}

// ↓ in a header: row 1's value into every other row (a contract-flight row keeps no sale)
function tiCopyDown(f) {
  const first = tiCardByKey(tiWizKeys[0]); if (!first) return;
  const val = {
    seller: () => { const v = first.querySelector('.pax-vendor')?.value, p = first.querySelector('.pax-portal')?.value; return v ? 'v:' + v : p ? 'p:' + p : ''; },
    cost: () => tiField(first, 'cost_amount')?.value || '',
    buyer: () => first.querySelector('.ds-sale-to')?.value || '',
    price: () => first.querySelector('.ds-sale')?.value || '',
  }[f]();
  if (val === '') return;
  tiWizKeys.slice(1).forEach(k => {
    const c = tiCardByKey(k);
    if (!c || tiDecision(c)?.kind === 'reschedule' || ((f === 'buyer' || f === 'price') && tiCfBound(c))) return;
    const save = tiWizRender; tiWizRender = () => {};   // one repaint at the end, not one per row
    try { tiRow(k, f, val); } finally { tiWizRender = save; }
  });
  tiWizRender();
}


function tiWizStepHtml(card) {
  const t = TI[card.dataset.tiKey], { res, pax } = t;
  const segs = res.segments || [], out = segs.filter(s => s.direction !== 'return'), ret = segs.filter(s => s.direction === 'return');
  const sector = [out[0]?.from, ...out.map(s => s.to)].filter(Boolean).join(' → ');
  const miss = tiMissing(card);
  const group = tiGroupCards(card);
  let n = 0;
  const sec = (done, title, cur, inner) => `<div class="ti-sec${done ? ' done' : ''}"><div class="ti-sec-h"><span class="n">${done ? '✓' : ++n}</span>${title}${cur ? `<span class="cur">${cur}</span>` : ''}</div>${done ? '' : inner}</div>`;

  let html = `<div class="ti-facts">
    <div><div class="k">Passenger</div><div class="v">${tiEsc(pax.name)}<small>${[pax.passport_no ? 'Passport ' + pax.passport_no : '', pax.eticket ? 'E-ticket ' + pax.eticket : ''].filter(Boolean).map(tiEsc).join(' · ') || '&nbsp;'}</small></div></div>
    <div><div class="k">PNR</div><div class="v ti-mono">${tiEsc(res.pnr || '—')}<small>${tiEsc(res.layout_label || '')}${group.length > 1 ? ' · group of ' + group.length : ''}</small></div></div>
    <div><div class="k">Journey</div><div class="v">${tiEsc(sector || '—')}${ret.length ? ' <small>+ return ' + tiEsc(tiWhen(ret[0].dep)) + '</small>' : ''}<small>${tiEsc(out.map(s => s.flight_no).filter(Boolean).join(' / '))}</small></div></div>
    <div><div class="k">Departure</div><div class="v">${tiEsc(tiWhen(out[0]?.dep))}<small><i class="fas fa-file-pdf" style="color:#dc2626"></i> ${tiEsc(t.file?.name || res.file)}</small></div></div>
  </div>`;

  const links = tiLegLinks(card);
  if (links.dupes.length) html += tiDecisionHtml(card, links);
  // a reschedule is not a new ticket: nothing else to fill on this card
  if (tiDecision(card)?.kind === 'reschedule') return html;
  if (links.sibling) {
    html += `<div class="ti-link"><i class="fas fa-link"></i><span>Ticket ${Number(links.sibling.s)} of this route is already entered for this passenger — <b class="ti-mono">PNR ${tiEsc(links.sibling.pnr)}</b> (${tiEsc(tiWhen(links.sibling.d))}). This ticket joins the same order, so Ticket Orders shows them together.</span></div>`;
  }

  // 1. passenger
  const ph = card.querySelector('.ph-select');
  const opts = (pax.holder?.options || []).map(o => `<button type="button" class="ti-pick" onclick="tiWzHolder(${Number(o.id)})">${tiEsc(o.label)}</button>`).join('');
  const auto = tiAutoHolder(card);
  if (auto) html += `<div class="ti-sec done"><div class="ti-sec-h"><span class="n">✓</span>Passenger<span class="cur">New — created on Confirm: ${tiEsc(auto.name)} · <span class="ti-mono">${tiEsc(auto.passport_no)}</span></span></div>
     <div class="ti-grid"><div><label class="ds-field-lbl">Or choose an existing passenger instead</label>
       <select class="ti-s2" data-ph="Search name or passport…" onchange="tiWzHolder(this.value)"><option value=""></option>${allPhOptions()}</select></div><div></div></div></div>`;
  else html += sec(!miss.includes('passenger'), 'Passenger', tiEsc(tiSelText(ph)),
    `${opts ? `<div class="ti-opts"><span style="font-size:12px;color:var(--text2);align-self:center">Same person?</span>${opts}</div>` : ''}
     <div class="ti-grid"><div><label class="ds-field-lbl">Choose an existing passenger</label>
       <select class="ti-s2" data-ph="Search name or passport…" onchange="tiWzHolder(this.value)"><option value=""></option>${allPhOptions()}</select></div>
       <div style="align-self:end"><button type="button" class="erp-btn btn-ghost" onclick="tiWzNewHolder()" style="height:38px"><i class="fas fa-user-plus"></i> Create new passenger — ${tiEsc(pax.name)}</button></div></div>`);

  // 2. route
  const r = res.route || {};
  const cands = (r.candidates || []).map(c => `<button type="button" class="ti-pick" onclick="tiWzRoute(${Number(c.id)})">${tiEsc(c.title)}${c.leg ? ' · ticket ' + Number(c.leg) : ''}</button>`).join('');
  const rsel = card.querySelector('.ticket-select');
  html += sec(!miss.includes('route'), 'Route', tiEsc(tiSelText(rsel)) + (tiField(card, 'leg_sequence')?.value ? ' · ticket ' + tiEsc(tiField(card, 'leg_sequence').value) : ''),
    `${cands ? `<div class="ti-opts"><span style="font-size:12px;color:var(--text2);align-self:center">Fits ${tiEsc(r.from)} → ${tiEsc(r.to)}:</span>${cands}</div>` : ''}
     <div class="ti-grid"><div><label class="ds-field-lbl">Choose any route</label>
       <select class="ti-s2" data-ph="Search routes…" onchange="tiWzRoute(this.value)"><option value=""></option>${ticketOptions}</select></div>
       <div style="align-self:end">${r.from_airport_id && r.to_airport_id ? `<button type="button" class="erp-btn btn-ghost" onclick="tiWzNewRoute()" style="height:38px"><i class="fas fa-route"></i> Create route ${tiEsc(r.title)}</button>` : ''}</div></div>`);

  // 3. seller and 4. cost — only for a ticket being purchased
  if ((card.querySelector('.pax-status')?.value || 'purchased') === 'purchased') {
    const cv = card.querySelector('.pax-vendor'), cp = card.querySelector('.pax-portal');
    const pick = (list, val) => list.replace(new RegExp(`value="${String(val).replace(/[^0-9]/g, '')}"`), `value="${String(val).replace(/[^0-9]/g, '')}" selected`);
    html += sec(false, 'Seller &amp; cost', '',
      `<div class="ti-grid" style="grid-template-columns:minmax(0,1fr) 24px minmax(0,1fr) minmax(0,.8fr)">
         <div><label class="ds-field-lbl">Vendor</label><select id="tiWzVendor" class="ti-s2" data-ph="— Select vendor —" onchange="if(this.value)$('#tiWzPortal').val('').trigger('change.select2');tiWizApply('seller')"><option value=""></option>${cv.value ? pick(vendorOptions, cv.value) : vendorOptions}</select></div>
         <div class="ti-or">or</div>
         <div><label class="ds-field-lbl">Portal</label><select id="tiWzPortal" class="ti-s2" data-ph="— Select portal —" onchange="if(this.value)$('#tiWzVendor').val('').trigger('change.select2');tiWizApply('seller')"><option value=""></option>${cp.value ? pick(portalOptions, cp.value) : portalOptions}</select></div>
         <div><label class="ds-field-lbl">Net cost (${tiEsc($('[name="currency"]').val() || 'BDT')})</label><input type="number" id="tiWzCost" class="ds-field-input" step="0.01" min="0" value="${parseFloat(tiField(card, 'cost_amount')?.value) > 0 ? tiEsc(tiField(card, 'cost_amount').value) : ''}" onchange="tiWizApply('cost')" style="text-align:right"></div>
       </div>
       ${tiSaleStepHtml(card)}
       ${group.length > 1 ? `<label class="ti-apply"><input type="checkbox" id="tiWzAll" checked onchange="if(this.checked)tiWizApply('all')"> Use this seller, cost and agent price for all ${group.length} passengers on PNR <b class="ti-mono">${tiEsc(res.pnr)}</b></label>` : ''}`);
  }
  return html;
}

function tiWizReviewHtml() {
  let total = 0, saleTotal = 0, profitTotal = 0;
  const rows = [...document.querySelectorAll('#passengerContainer .ds-pax-card')].map((c, i) => {
    const k = c.dataset.tiKey, at = k ? tiWizKeys.indexOf(k) : -1, miss = tiMissing(c);
    const purchased = (c.querySelector('.pax-status')?.value || 'purchased') === 'purchased';
    const resched = tiDecision(c)?.kind === 'reschedule';   // not a new ticket: no seller, no cost
    const cost = resched ? 0 : (parseFloat(tiField(c, 'cost_amount')?.value) || 0); total += cost;
    const saleV = tiField(c, 'sale_price')?.value, sale = saleV === '' || saleV == null || resched ? null : parseFloat(saleV) || 0;
    if (sale !== null) { saleTotal += sale; profitTotal += sale - cost; }
    const cfb = k ? tiCfBound(c) : '';
    const M = '<span class="miss">missing</span>';
    const seller = tiSelText(c.querySelector('.pax-vendor')) || tiSelText(c.querySelector('.pax-portal'));
    const seq = tiField(c, 'leg_sequence')?.value;
    return `<tr ${at >= 0 ? `onclick="tiWizGo(${at})" title="Open this passenger"` : ''}>
      <td>${i + 1}</td>
      <td>${miss.includes('passenger') ? M + `<small>${tiEsc(k ? TI[k].pax.name : '')}</small>` : (tiAutoHolder(c) ? `<span class="lnk">New: ${tiEsc(tiAutoHolder(c).name)}</span><small class="ti-mono">${tiEsc(tiAutoHolder(c).passport_no)} · created on Confirm</small>` : tiEsc(tiSelText(c.querySelector('.ph-select'))))}${(() => {
        if (!k) return '';
        const L = tiLegLinks(c);
        const D = tiDecision(c);
        return (L.dupes.length ? (D && D.kind !== 'keep' ? `<small class="lnk"><i class="fas fa-clock-rotate-left"></i> ${tiEsc(tiDecisionLine(D))}</small>` : `<small class="dupl">${D?.kind === 'keep' ? 'Kept: ' : '⚠ '}${tiEsc(L.dupes[0].msg)}</small>`) : '')
          + (L.sibling && D?.kind !== 'reschedule' ? `<small class="lnk"><i class="fas fa-link"></i> joins ticket ${Number(L.sibling.s)} · PNR ${tiEsc(L.sibling.pnr)}</small>` : '');
      })()}</td>
      <td class="ti-mono">${miss.includes('pnr') ? M : tiEsc(tiField(c, 'ticket_no')?.value || '—')}<small>${tiEsc(tiField(c, 'name_ticket_no')?.value || '')}</small></td>
      <td>${miss.includes('route') ? M : tiEsc(tiSelText(c.querySelector('.ticket-select')))}${seq ? `<small>ticket ${tiEsc(seq)}</small>` : ''}</td>
      <td>${tiEsc(tiField(c, 'flight_no')?.value || '')}<small>${tiEsc(tiWhen(tiField(c, 'departure_at')?.value))}</small></td>
      <td>${resched ? '—' : purchased ? (seller ? tiEsc(seller) : M) : `<small>${tiEsc(tiSelText(c.querySelector('.pax-status')))}</small>`}</td>
      <td class="num">${resched ? '—' : miss.includes('cost') ? M : tiMoney(cost)}</td>
      <td class="num">${sale === null ? (cfb ? '<small>on flight</small>' : '—') : tiMoney(sale)}<small>${tiEsc(tiSelText(c.querySelector('.ds-sale-to')))}</small></td>
      <td class="num ${sale !== null ? (sale - cost >= 0 ? 'lnk' : 'dupl') : ''}">${sale === null ? '—' : tiMoney(sale - cost)}</td>
      <td>${c.querySelector('.ds-file')?.files?.[0] ? '<i class="fas fa-file-pdf" style="color:#dc2626" title="PDF attached"></i>' : ''}</td>
    </tr>`;
  }).join('');
  return `<table class="ti-rev"><thead><tr><th>#</th><th>Passenger</th><th>PNR · E-ticket</th><th>Route</th><th>Flight · Departure</th><th>Seller</th><th class="num">Net cost</th><th class="num">Agent price</th><th class="num">Profit</th><th></th></tr></thead>
    <tbody>${rows}</tbody>
    <tfoot><tr><td colspan="6">Total</td><td class="num">${tiMoney(total)}</td><td class="num">${saleTotal ? tiMoney(saleTotal) : '—'}</td><td class="num ${profitTotal >= 0 ? 'lnk' : 'dupl'}">${saleTotal ? tiMoney(profitTotal) : '—'}</td><td></td></tr></tfoot></table>
    <p style="font-size:12px;color:var(--text2);margin:12px 2px 0">Confirm saves every ticket above exactly as <b>Save Purchase(s)</b> does — ledger and journals post as usual. Click a row to change it.</p>`;
}

function tiDecisionHtml(card, links) {
  const d = tiDecision(card) || {};
  const ids = [...new Set(links.dupes.map(x => x.id).filter(Boolean))];
  tiFetchConflicts(ids);
  const oldId = d.oldId || ids[0] || null, o = oldId ? TI_CONFLICT[oldId] : null, cf = o?.contract_flight;
  const gap = tiDecisionGap(card);
  const pick = (on, label, js) => `<button type="button" class="ti-pick ${on ? 'on' : ''}" onclick="${js}">${label}</button>`;
  const side = key => pick(d[key] === 'agent', 'Agent side — bill the agent', `tiWzDec({${key}:'agent'})`) + pick(d[key] === 'company', 'Our side — we bear it', `tiWzDec({${key}:'company'})`);
  const lbl = t => `<span style="font-size:12px;color:var(--text2);align-self:center">${t}</span>`;
  let h = `<div class="ti-sec ${gap ? 'dup' : 'done'}"><div class="ti-sec-h"><span class="n">${gap ? '!' : '✓'}</span>Already entered — what happened to the earlier ticket?</div>
    <ul>${links.dupes.map(x => `<li>${tiEsc(x.msg)}</li>`).join('')}</ul>`;
  if (o) {
    h += `<div class="ti-facts" style="margin-bottom:10px;background:#fff">
      <div><div class="k">Earlier ticket</div><div class="v ti-mono">${tiEsc(o.pnr)}<small>${tiEsc(o.eticket || '')}</small></div></div>
      <div><div class="k">Cost · seller</div><div class="v">${tiMoney(o.amount)}<small>${tiEsc(o.seller || '—')}</small></div></div>
      <div><div class="k">Flight</div><div class="v">${tiEsc(o.flight_no || o.route || '—')}<small>${tiEsc(tiWhen(o.departure))}</small></div></div>
      <div><div class="k">Contract flight</div><div class="v">${cf ? tiEsc(cf.flight_no || 'yes') + `<small>${tiEsc(cf.agent ? 'Agent ' + cf.agent : 'no agent')}</small>` : '—<small>not on a contract flight</small>'}</div></div></div>`;
  } else if (ids.length) {
    h += `<p style="font-size:12px;color:var(--text2)">Loading the earlier ticket…</p>`;
  }
  if (ids.length > 1) h += `<div class="ti-opts">${lbl('Which one?')}${ids.map(id => pick(String(id) === String(oldId), tiEsc(TI_CONFLICT[id]?.pnr || '#' + id), `tiWzDec({oldId:${Number(id)}})`)).join('')}</div>`;
  h += `<div class="ti-opts">
    ${pick(false, '<i class="fas fa-trash"></i> Same ticket — remove this card', 'tiWzRemove()')}
    ${oldId ? pick(d.kind === 'reissue', '<i class="fas fa-rotate"></i> Reissued — this new ticket replaces it', `tiWzDec({kind:'reissue',oldId:${Number(oldId)}})`) : ''}
    ${oldId ? pick(d.kind === 'reschedule', '<i class="fas fa-calendar-day"></i> Rescheduled — same ticket, new date', `tiWzDec({kind:'reschedule',oldId:${Number(oldId)}})`) : ''}
    ${pick(d.kind === 'keep', 'Different ticket — keep both', 'tiWzKeep()')}</div>`;
  const num = (key, label, hint) => `<div><label class="ds-field-lbl">${label}</label><input type="number" min="0" step="0.01" class="ds-field-input ti-dec-${key}" style="text-align:right" value="${d[key] ?? ''}" onchange="tiWzDec({${key}: this.value === '' ? null : this.value})">${hint ? `<small style="font-size:11px;color:var(--text3)">${hint}</small>` : ''}</div>`;
  if (d.kind === 'reissue' && o) {
    h += `<div class="ti-grid" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-top:6px">
      ${num('refund', 'Airline refund on ' + tiEsc(o.pnr), 'Blank = everything back. What the airline keeps is the penalty' + (tiPenalty(d) > 0 ? ': <b>' + tiMoney(tiPenalty(d)) + '</b>' : '.'))}
      ${cf ? num('fare_difference', 'Fare difference', 'Blank = worked out from the two tickets') : ''}
      ${num('fee', 'Reissue fee (to the seller)', cf ? '' : 'Posted to the seller as ticket cost')}</div>`;
    if (cf && tiPenalty(d) > 0) h += `<div class="ti-opts" style="margin-top:8px">${lbl('Penalty — whose side?')}${side('fault')}</div>`;
    if (cf) h += `<div class="ti-opts">${lbl('Fare difference — who pays?')}${side('fare_bearer')}</div>`;
    if (cf && Number(d.fee) > 0) h += `<div class="ti-opts">${lbl('Reissue fee — who pays?')}${side('fee_bearer')}</div>`;
    h += cf
      ? `<p style="font-size:12px;color:var(--text2);margin:6px 0 0">Done through Contract Flight ${tiEsc(cf.flight_no || '')}'s own Re-assign (he stays on his flight): ${tiEsc(o.pnr)} cancelled with the refund, the penalty / fare difference / fee posted to the side you choose, and this new ticket linked to him.</p>`
      : `<p style="font-size:12px;color:var(--text2);margin:6px 0 0">${tiEsc(o.pnr)} is cancelled in Ticket Orders with the airline's refund added beside it; what the airline keeps stays in ticket cost. This card is saved as the new ticket.</p>`;
  }
  if (d.kind === 'reschedule' && o) {
    h += `<p style="font-size:12px;color:var(--text2);margin:6px 0">This card is <b>not</b> saved as a new ticket — ${tiEsc(o.pnr)} takes this PDF's flight and date.</p>`;
    h += cf
      ? `<div class="ti-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">${num('fee', 'Change fee (to the seller)', '')}</div>` + (Number(d.fee) > 0 ? `<div class="ti-opts" style="margin-top:8px">${lbl('Change fee — who pays?')}${side('fee_bearer')}</div>` : '')
      : `<div class="ti-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">${num('fee', 'Change fee (to the seller)', 'Posted to the seller as ticket cost')}</div>`;
  }
  return h + '</div>';
}
function tiWzDec(patch) {
  const card = tiCardByKey(tiWizKeys[tiWizAt]); if (!card) return;
  const before = tiDecision(card) || {};
  const d = { ...before, ...patch };
  if (patch.kind && patch.kind !== before.kind) ['refund', 'fault', 'fare_difference', 'fare_bearer', 'fee', 'fee_bearer'].forEach(k => delete d[k]);
  card.dataset.tiDecision = JSON.stringify(d);
  delete card.dataset.tiKeep;
  tiStrip(card, TI[card.dataset.tiKey]);
  tiWizRender();
}

// Confirm: the page's own Save for the new tickets, then each answer carried
// out through Ticketing / Contract Flight (TicketImportController::resolve).
// A passenger the PDF names that no holder matches: made automatically on Confirm
// (owner: "added the passenger, passport … automatically"; 2026-10-08: passport is not
// mandatory, so it is made from the name alone when the PDF prints no passport),
// through the same endpoint as the "+ passport holder" modal.
function tiAutoHolder(card) {
  const t = card && TI[card.dataset.tiKey];
  if (!t || card.querySelector('.ph-select')?.value || t.pax.holder?.id || !String(t.pax.name || '').trim()) return null;
  const pp = t.pax.passport_no ? String(t.pax.passport_no).toUpperCase() : '';
  return { name: String(t.pax.name).trim(), passport_no: pp, dob: t.pax.dob || '', key: pp || ('name:' + String(t.pax.name).trim().toUpperCase() + '|' + (t.pax.dob || '')) };
}
// a name as one person: upper case, letters only, MD / MOHAMMAD … dropped, words sorted — "MD RIDOY MIA" = "RIDOY MIA MD"
const tiNameKey = n => String(n || '').toUpperCase().replace(/[^A-Z ]+/g, ' ').split(/\s+/)
  .filter(w => w && !['MD', 'MOHAMMAD', 'MOHAMMED', 'MUHAMMAD', 'MR', 'MRS', 'MS', 'MSTR', 'MISS'].includes(w)).sort().join(' ');
async function tiCreateHolders() {
  const cat = [...(document.getElementById('phCategory')?.options || [])].find(o => /ordinary/i.test(o.text))?.value || '';
  const made = {};   // one holder per passport, however many cards carry it
  const cards = [...document.querySelectorAll('#passengerContainer .ds-pax-card[data-ti-key]')];
  // One passport, two different people = a misread: one holder would be given to both, and the second
  // person's ticket saved under the first (owner, 2026-10-18: MD RIDOY MIA's UFUMMB was saved as SALA
  // UDDIN's). Stop before anything is created or saved.
  const byKey = {};
  cards.forEach(card => { const a = tiAutoHolder(card); if (a) (byKey[a.key] = byKey[a.key] || new Map()).set(tiNameKey(a.name), a.name); });
  const clash = Object.entries(byKey).find(([, names]) => names.size > 1);
  if (clash) throw new Error(`One passport (${clash[0]}) is read for different passengers: ${[...clash[1].values()].join(' and ')}. Correct the passport on their cards`);
  // every imported card on the page — not only the modal's list, so the page's own
  // Save (after "Close & edit on page") creates them too (owner, 2026-10-11)
  for (const card of cards) {
    const a = tiAutoHolder(card); if (!a) continue;
    if (!made[a.key]) {
      let res;
      try {
        res = await $.ajax({ url: phStoreUrl, method: 'POST', data: { name: a.name, passport_no: a.passport_no, nationality: 'Bangladeshi',
          date_of_birth: a.dob, category_id: cat, type: 'ticket', status: 1, _token: $('meta[name="csrf-token"]').attr('content') } });
      } catch (x) { res = { success: false, message: x.responseJSON?.message }; }
      if (!res?.success) throw new Error(`Could not create passenger ${a.name}${a.passport_no ? ' (' + a.passport_no + ')' : ''}: ${res?.message || 'server error'}`);
      const label = res.data.name + (res.data.passport_no ? ' — ' + res.data.passport_no : '');
      $('.ph-select').each(function () { if (!$(this).find(`option[value="${res.data.id}"]`).length) $(this).append(new Option(label, res.data.id, false, false)); });
      phOptions += `<option value="${res.data.id}">${tiEsc(label)}</option>`;
      made[a.key] = res.data.id;
    }
    tiBusy = true;
    try { $(card.querySelector('.ph-select')).val(String(made[a.key])).trigger('change'); } finally { tiBusy = false; }
  }
}

async function tiWizConfirm() {
  if (tiWizKeys.some(k => tiMissing(tiCardByKey(k)).length)) return;
  try { await tiCreateHolders(); }
  catch (e) { Swal.fire({ icon: 'error', title: 'Passenger not created', text: e.message + ' — nothing was saved.' }); return; }
  const decided = tiWizKeys.map(tiCardByKey).filter(c => c && ['reissue', 'reschedule'].includes(tiDecision(c)?.kind));
  tiWizClose();
  // later legs joining an earlier leg's order: that leg's contract flight gets this one too
  const joiners = tiWizKeys.map(tiCardByKey).filter(c => c && !decided.includes(c)
    && (c.querySelector('.pax-status')?.value || 'purchased') === 'purchased')
    .map(c => [c, tiLegLinks(c).sibling]).filter(([, s]) => s && s.k === 'purchase' && s.id);
  if (!decided.length && !joiners.length) { $('#submitBtn').trigger('click'); return; }

  // a reschedule is not a new ticket: its card leaves the form before Save
  const parked = decided.filter(c => tiDecision(c).kind === 'reschedule').map(c => { const ph = document.createElement('div'); c.replaceWith(ph); return [c, ph]; });
  const unpark = () => parked.forEach(([c, ph]) => ph.replaceWith(c));
  const cards = [...document.querySelectorAll('#passengerContainer .ds-pax-card')];
  let saved = { success: true, ticket_purchases: [] };
  Swal.fire({ title: 'Saving…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
  if (cards.length) {
    try {
      saved = await $.ajax({ url: $('#directSaleForm').attr('action'), method: 'POST', data: new FormData($('#directSaleForm')[0]), processData: false, contentType: false });
    } catch (x) { saved = { success: false, message: x.responseJSON?.message || 'Something went wrong.' }; }
    if (!saved.success) { unpark(); Swal.fire({ icon: 'error', title: 'Failed', text: saved.message }); return; }
  }
  // purchased cards, in form order, are the saved purchases, in order
  const bought = cards.filter(c => (c.querySelector('.pax-status')?.value || 'purchased') === 'purchased');
  const done = [];
  for (const c of decided) {
    const d = tiDecision(c), t = TI[c.dataset.tiKey];
    const out = (t.res.segments || []).filter(s => s.direction !== 'return');
    const body = { kind: d.kind, old_id: d.oldId };
    ['refund', 'fault', 'fare_difference', 'fare_bearer', 'fee', 'fee_bearer'].forEach(k => { if (d[k] !== undefined && d[k] !== null && d[k] !== '') body[k] = d[k]; });
    if (d.kind === 'reissue') {
      body.new_id = saved.ticket_purchases?.[bought.indexOf(c)]?.id;
      if (!body.new_id) { done.push([false, `${t.pax.name}: the new ticket was not found after saving — make the reissue from Contract Flight / Ticket Orders.`]); continue; }
    } else {
      body.flight_no = out.map(s => s.flight_no).filter(Boolean).join(' / ').slice(0, 40);
      if (out[0]?.dep) body.departure_at = out[0].dep;
      if (out[out.length - 1]?.arr) body.arrival_at = out[out.length - 1].arr;
    }
    try {
      const r = await $.ajax({ url: TI_RESOLVE_URL, method: 'POST', data: body });
      done.push([!!r.success, `${t.pax.name}: ${r.message}`]);
    } catch (x) { done.push([false, `${t.pax.name}: ${x.responseJSON?.message || 'failed'}`]); }
  }
  for (const [c, sib] of joiners) {
    const t = TI[c.dataset.tiKey], newId = saved.ticket_purchases?.[bought.indexOf(c)]?.id;
    if (!newId) continue;
    try {
      const r = await $.ajax({ url: TI_RESOLVE_URL, method: 'POST', data: { kind: 'join', old_id: sib.id, new_id: newId } });
      done.push([!!r.success, `${t.pax.name}: ${r.message}`]);
    } catch (x) { done.push([false, `${t.pax.name}: ${x.responseJSON?.message || 'could not be added to the contract flight'}`]); }
  }
  const allOk = done.every(r => r[0]);
  await Swal.fire({ icon: allOk ? 'success' : 'warning', title: allOk ? 'Saved' : 'Saved — with problems',
    html: (cards.length ? `<p>${tiEsc(saved.message || 'Tickets saved.')}</p>` : '') + `<ul style="text-align:left;font-size:13px">${done.map(([ok, m]) => `<li>${ok ? '✓' : '✗'} ${tiEsc(m)}</li>`).join('')}</ul>`
      + (allOk ? '' : '<p style="font-size:12px">Lines marked ✗ were not changed — finish them from Contract Flight or Ticket Orders.</p>') });
  window.location.assign(saved.redirect_url || window.location.pathname);
}

(function () {
  const inp = document.getElementById('tiFiles'), drop = document.getElementById('tiDrop');
  if (!inp || !drop) return;
  inp.addEventListener('change', () => { const f = [...inp.files]; inp.value = ''; tiHandle(f); });
  // the CSV / Excel zone (2026-10-19), when its route is registered
  const sinp = document.getElementById('tiSheetFiles'), sdrop = document.getElementById('tiSheetDrop');
  if (sinp) sinp.addEventListener('change', () => { const f = [...sinp.files].filter(tiIsSheet); sinp.value = ''; tiHandleSheets(f); });
  const zones = [drop, sdrop].filter(Boolean), inZone = el => zones.some(z => z.contains(el));
  zones.forEach(z => {
    ['dragenter', 'dragover'].forEach(e => z.addEventListener(e, ev => { ev.preventDefault(); z.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(e => z.addEventListener(e, ev => { ev.preventDefault(); z.classList.remove('over'); }));
    z.addEventListener('drop', ev => tiDropFiles(ev.dataTransfer.files));
  });

  // Drop ticket PDFs (or a sheet) ANYWHERE on the page (2026-10-05) — the panel opens itself.
  const veil = document.createElement('div');
  veil.className = 'ti-page-drop';
  veil.innerHTML = `<span><i class="fas fa-file-import"></i>${TI_SHEET_URL ? 'Drop ticket PDFs or a CSV / Excel file to import them' : 'Drop ticket PDFs to import them'}</span>`;
  document.body.appendChild(veil);
  const hasFiles = ev => [...(ev.dataTransfer?.types || [])].includes('Files');
  let depth = 0;
  document.addEventListener('dragenter', ev => { if (!hasFiles(ev) || inZone(ev.target)) return; depth++; veil.classList.add('on'); });
  document.addEventListener('dragleave', ev => { if (!hasFiles(ev)) return; depth = Math.max(0, depth - 1); if (!depth) veil.classList.remove('on'); });
  document.addEventListener('dragover', ev => { if (hasFiles(ev) && !inZone(ev.target)) ev.preventDefault(); });
  document.addEventListener('drop', ev => {
    if (!hasFiles(ev) || inZone(ev.target)) return;
    ev.preventDefault(); depth = 0; veil.classList.remove('on');
    tiDropFiles(ev.dataTransfer.files);
  });

  // The wheel anywhere on the open table scrolls the table, never the page behind it (owner, 2026-10-19:
  // "scrolling is not working" — over its header, footer or the dim edge the page scrolled instead).
  // Its body and an open picker list scroll themselves; their ends do not chain to the page (CSS above).
  document.getElementById('tiWiz')?.addEventListener('wheel', ev => {
    if (ev.ctrlKey || ev.target.closest('#tiWizBody, .select2-dropdown')) return;   // ctrl + wheel = zoom
    ev.preventDefault();
    document.getElementById('tiWizBody').scrollTop += ev.deltaMode === 1 ? ev.deltaY * 16 : ev.deltaY;
  }, { passive: false });

  // In the modal: Enter = Save & next, Ctrl+Enter on Review = Confirm & Save.
  // Esc does nothing — modals close only on the ✕ (owner's rule).
  document.addEventListener('keydown', ev => {
    if (!tiWizIsOpen()) return;
    if (ev.key === 'Escape') { ev.preventDefault(); return; }
    if (ev.key !== 'Enter' || ev.target.closest('.select2-container, .select2-search, textarea')) return;
    if (document.querySelector('.ph-modal-overlay.open')) return;   // the passenger / route modal above has its own keys
    ev.preventDefault();
    if (ev.target.matches && ev.target.matches('input')) ev.target.dispatchEvent(new Event('change'));   // a typed amount counts
    if (ev.ctrlKey || ev.metaKey) tiWizConfirm();
  });
  // a seller chosen on an imported card is remembered too
  $(document).on('change', '#passengerContainer .pax-vendor, #passengerContainer .pax-portal', function () { if (!tiBusy) tiRememberSeller(tiCardOf(this)); });
})();
</script>
@endif
@if(\App\Models\TicketChange::available() && Route::has('role.ticket-changes.refunded'))
{{-- Refund → new ticket on Ticket Entry (owner, 2026-10-07): "after refund, if we entry like a new
     ticket, the system detects the elder refund, and the fare difference appears, and who is
     bearing + charges". Picking a passenger with a refunded ticket shows it on the card; the card
     posts replaces_purchase_id and store() hands it to TicketChangeController::settleFromEntry(). --}}
<style>
.rfx-box{margin-top:10px;border:1px solid #fecaca;background:#fff7f7;border-radius:10px;padding:10px 12px;display:flex;flex-direction:column;gap:8px;font-size:12.5px;color:#334155;}
.rfx-head{display:flex;align-items:center;gap:8px;font-weight:700;color:#b91c1c;font-size:12px;}
.rfx-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:10px;align-items:end;}
.rfx-grid label{display:block;font-size:10.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px;}
.rfx-grid select,.rfx-grid input{width:100%;box-sizing:border-box;height:34px;border:1px solid #d2d8e7;border-radius:8px;padding:4px 8px;font-size:12.5px;background:#fff;}
.rfx-val{height:34px;display:flex;align-items:center;font-weight:700;color:#0f172a;}
.rfx-val.r{color:#dc2626;}
.rfx-pay{display:flex;gap:6px;}
.rfx-pay button{flex:1;border:1px solid #d2d8e7;background:#fff;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:600;color:#475569;cursor:pointer;}
.rfx-pay button.on[data-b="customer"]{background:#0f766e;border-color:#0f766e;color:#fff;}
.rfx-pay button.on[data-b="company"]{background:#334155;border-color:#334155;color:#fff;}
.rfx-pay button:disabled{opacity:.45;cursor:not-allowed;}
.rfx-hint{font-size:11.5px;color:#64748b;}
@media (max-width:900px){.rfx-grid{grid-template-columns:1fr 1fr;}}
</style>
<script>
(function () {
  const URL_REFUNDED = "{{ route('role.ticket-changes.refunded', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'holder' => '__ID__']) }}";
  const cache = {};
  const tk = n => '৳' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const idxOf = card => (card.querySelector('.ph-select')?.name.match(/^passengers\[(\d+)\]/) || [])[1];
  const taken = (id, except) => [...document.querySelectorAll('#passengerContainer .rfx-pick')].some(s => s !== except && s.value === String(id));

  function load(holder) {
    if (!cache[holder]) cache[holder] = fetch(URL_REFUNDED.replace('__ID__', holder), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(r => r.json()).then(j => (j && j.success ? j.tickets : [])).catch(() => []);
    return cache[holder];
  }
  function draw(card) {
    const holder = card.querySelector('.ph-select')?.value, i = idxOf(card);
    card.querySelector('.rfx-box')?.remove();
    if (!holder || i === undefined) return;
    load(holder).then(list => {
      if (!card.isConnected || card.querySelector('.ph-select')?.value !== holder) return;
      card.querySelector('.rfx-box')?.remove();
      if (!list.length) return;
      const box = document.createElement('div');
      box.className = 'rfx-box';
      box.innerHTML = `<div class="rfx-head"><i class="fas fa-rotate-left"></i> This passenger has ${list.length === 1 ? 'a refunded ticket' : list.length + ' refunded tickets'} — is this his new ticket?</div>
        <div class="rfx-grid">
          <div><label>Replaces refunded ticket</label><select class="rfx-pick" name="passengers[${i}][replaces_purchase_id]"><option value="">No — a separate ticket</option>${list.map(t =>
            `<option value="${t.id}">${esc(t.pnr)} · ${esc(t.route || '—')} · cost ${tk(t.amount)}${t.refund !== null ? ' · refunded ' + tk(t.refund) : ''}${t.client ? ' · ' + esc(t.client) : ''}</option>`).join('')}</select></div>
          <div><label>Old cost</label><div class="rfx-val rfx-old">—</div></div>
          <div><label>Fare difference (new − old)</label><div class="rfx-val r rfx-fare">—</div></div>
          <div><label>Our service fee</label><input type="number" min="0" step="0.01" class="rfx-fee" name="passengers[${i}][replace_fee]" placeholder="0.00"></div>
        </div>
        <div class="rfx-grid" style="grid-template-columns:2fr 2fr">
          <div><label>Who bears the fare difference</label><div class="rfx-pay"><button type="button" data-b="customer">Agent pays</button><button type="button" data-b="company">We bear it</button></div>
            <input type="hidden" class="rfx-bearer" name="passengers[${i}][replace_fare_bearer]" value=""></div>
          <div class="rfx-hint rfx-note"></div>
        </div>`;
      const anchor = card.querySelector('.ds-sale-row') || card.querySelector('.pax-cost')?.closest('.ds-row');
      if (!anchor) return;
      anchor.after(box);
      const pick = box.querySelector('.rfx-pick');
      const first = list.find(t => !taken(t.id, pick));
      if (first) pick.value = String(first.id);   // the newest refund not already claimed by another card
      box._list = list;
      box.querySelectorAll('.rfx-pay button').forEach(b => b.addEventListener('click', () => {
        if (b.disabled) return; box.querySelector('.rfx-bearer').value = b.dataset.b; calc(card);
      }));
      calc(card);
    });
  }
  function calc(card) {
    const box = card.querySelector('.rfx-box'); if (!box) return;
    const pick = box.querySelector('.rfx-pick'), old = (box._list || []).find(t => String(t.id) === pick.value);
    const on = !!old;
    box.querySelectorAll('.rfx-grid')[1].style.display = on ? '' : 'none';
    box.querySelector('.rfx-fee').disabled = !on;
    box.querySelector('.rfx-bearer').disabled = !on;
    if (!on) { box.querySelector('.rfx-old').textContent = '—'; box.querySelector('.rfx-fare').textContent = '—'; return; }
    const costEl = card.querySelector('.pax-cost'), net = costEl && costEl.value !== '' ? parseFloat(costEl.value) || 0 : null;
    const fare = net === null ? null : Math.max(0, Math.round((net - old.amount) * 100) / 100);
    box.querySelector('.rfx-old').textContent = tk(old.amount);
    box.querySelector('.rfx-fare').textContent = fare === null ? 'type the net cost' : (fare > 0 ? tk(fare) : (net < old.amount ? 'none — ' + tk(old.amount - net) + ' cheaper' : 'none'));
    const buyerSel = card.querySelector('.ds-sale-to'), buyer = buyerSel?.value ? buyerSel.options[buyerSel.selectedIndex]?.text : (old.client || '');
    const bearer = box.querySelector('.rfx-bearer');
    const agentBtn = box.querySelector('.rfx-pay button[data-b="customer"]');
    agentBtn.disabled = !buyer;
    if (!buyer && bearer.value === 'customer') bearer.value = '';
    box.querySelectorAll('.rfx-pay button').forEach(b => b.classList.toggle('on', bearer.value === b.dataset.b));
    box.querySelector('.rfx-pay').style.opacity = fare > 0 ? '' : '.5';
    const fee = parseFloat(box.querySelector('.rfx-fee').value) || 0, his = (bearer.value === 'customer' ? (fare || 0) : 0) + fee;
    box.querySelector('.rfx-note').innerHTML = !fare && !fee ? 'No fare difference to post — the refund is just linked to this ticket.'
      : (fare > 0 && !bearer.value ? '<span style="color:#b91c1c">Choose who bears the fare difference.</span> ' : '')
        + (fare > 0 ? `Fare difference ${tk(fare)} → Company Ledger (Dr 5172 / Cr 5110). ` : '')
        + (his > 0 ? `<b>${esc(buyer || 'the agent')}</b> charged ${tk(his)} on one line (Dr 1311 / Cr 4182 · 4183).` : (fare > 0 && bearer.value === 'company' ? 'We bear it.' : ''));
  }

  $(document).on('change', '#passengerContainer .ph-select', function () { const c = this.closest('.ds-pax-card'); if (c) draw(c); });
  document.addEventListener('input', e => { const c = e.target.closest && e.target.closest('#passengerContainer .ds-pax-card'); if (c) calc(c); });
  $(document).on('change', '#passengerContainer .rfx-pick, #passengerContainer .ds-sale-to, #passengerContainer .pax-cost', function () { const c = this.closest('.ds-pax-card'); if (c) calc(c); });
  // costs filled by code (fare calculator, PDF import) do not fire input — re-check after any click/keyup
  document.addEventListener('keyup', e => { const c = e.target.closest && e.target.closest('#passengerContainer .ds-pax-card'); if (c) calc(c); });
  document.querySelectorAll('#passengerContainer .ds-pax-card').forEach(c => { if (c.querySelector('.ph-select')?.value) draw(c); });
})();
</script>
@endif
@include('dashboard.ticket_direct_sales.partials.baggage-script')
@include('partials.datetime-picker'){{-- date + optional time on operational date boxes (2026-09-30) --}}
@endsection
