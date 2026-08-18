@extends('layout.app')

@section('meta-information')
    <title>Dashboard | {{ config('app.name') }}</title>
@endsection

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
    <style>
/* ── DASHBOARD CSS (erp-dashboard-ui style) ── */
:root{
  --indigo:#4f46e5;--violet:#7c3aed;--pink:#ec4899;
  --slate-900:#0f172a;--slate-800:#1e293b;--slate-700:#334155;
  --slate-600:#475569;--slate-500:#64748b;--slate-400:#94a3b8;
  --slate-300:#cbd5e1;--slate-200:#e2e8f0;--slate-100:#f1f5f9;--slate-50:#f8fafc;
  --green:#22c55e;--red:#ef4444;--amber:#f59e0b;--blue:#3b82f6;
  --teal:#14b8a6;--orange:#f97316;--emerald:#10b981;--rose:#f43f5e;
}

/* ── NOTICE SLIDER ── */
.ns-wrap{position:relative;border-radius:16px;overflow:hidden;margin-bottom:20px;box-shadow:0 4px 24px rgba(0,0,0,.13);}
.ns-track{display:flex;transition:transform .45s cubic-bezier(.4,0,.2,1);}
.ns-slide{min-width:100%;padding:40px 70px 40px 24px;display:flex;align-items:center;gap:20px;position:relative;box-sizing:border-box;}
.ns-slide-icon{width:56px;height:56px;background:rgba(0,0,0,.15);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0;backdrop-filter:blur(4px);}
.ns-slide-body{flex:1;min-width:0;}
.ns-reminder-badge{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:3px 11px;margin-bottom:7px;letter-spacing:.7px;}
.ns-slide-title{font-size:16px;font-weight:800;margin-bottom:4px;}
.ns-slide-desc{font-size:12px;opacity:.85;line-height:1.6;}
.ns-action-btn{display:inline-flex;align-items:center;gap:6px;margin-top:10px;font-size:12px;font-weight:700;padding:6px 16px;border-radius:999px;background:rgba(255,255,255,.2);border:1.5px solid rgba(255,255,255,.4);cursor:pointer;transition:.2s;text-decoration:none;}
.ns-action-btn:hover{background:rgba(255,255,255,.35);}
.ns-arrow{position:absolute;top:50%;transform:translateY(-50%);width:32px;height:32px;background:rgba(255,255,255,.18);border:1.5px solid rgba(255,255,255,.3);backdrop-filter:blur(4px);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:5;transition:.2s;color:white;font-size:13px;}
.ns-arrow:hover{background:rgba(255,255,255,.32);}
.ns-prev{left:10px;}
.ns-next{right:10px;}
.ns-dots{position:absolute;bottom:8px;right:16px;display:flex;gap:5px;z-index:5;}
.ns-dot{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.4);cursor:pointer;transition:.2s;}
.ns-dot.active{background:white;width:20px;border-radius:4px;}
.ns-slide-img{width:100px;height:100px;border-radius:50%;overflow:hidden;border:3px solid rgba(255,255,255,.35);box-shadow:0 4px 18px rgba(0,0,0,.2);flex-shrink:0;margin-right:6px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;}
.ns-slide-img img{width:100%;height:100%;object-fit:cover;display:block;}
.ns-slide-img-deco{width:100%;height:100%;display:flex;align-items:center;justify-content:center;}

/* ── TODO SLIDER ── */
.ts-wrap{position:relative;border-radius:16px;overflow:hidden;margin-bottom:20px;box-shadow:0 4px 24px rgba(0,0,0,.12);}
.ts-track{display:flex;transition:transform .45s cubic-bezier(.4,0,.2,1);}
.ts-slide{min-width:100%;padding:28px 70px 28px 24px;display:flex;align-items:center;gap:16px;position:relative;box-sizing:border-box;}
.ts-icon{width:52px;height:52px;background:rgba(0,0,0,.15);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;backdrop-filter:blur(4px);}
.ts-body{flex:1;min-width:0;}
.ts-badge{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:2px 10px;margin-bottom:6px;letter-spacing:.7px;}
.ts-title{font-size:15px;font-weight:800;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ts-meta{font-size:11px;opacity:.8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ts-status{display:inline-flex;align-items:center;gap:5px;margin-top:8px;font-size:11px;font-weight:700;padding:4px 12px;border-radius:999px;background:rgba(255,255,255,.2);border:1.5px solid rgba(255,255,255,.4);text-decoration:none;color:white;}
.ts-status:hover{background:rgba(255,255,255,.32);}
.ts-arrow{position:absolute;top:50%;transform:translateY(-50%);width:30px;height:30px;background:rgba(255,255,255,.18);border:1.5px solid rgba(255,255,255,.3);backdrop-filter:blur(4px);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:5;transition:.2s;color:white;font-size:12px;}
.ts-arrow:hover{background:rgba(255,255,255,.32);}
.ts-prev{left:10px;}
.ts-next{right:10px;}
.ts-dots{position:absolute;bottom:7px;right:14px;display:flex;gap:5px;z-index:5;}
.ts-dot{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.4);cursor:pointer;transition:.2s;}
.ts-dot.active{background:white;width:18px;border-radius:3px;}

/* ── WELCOME BANNER ── */
.welcome-banner{background:linear-gradient(135deg,var(--indigo) 0%,var(--violet) 50%,var(--pink) 100%);border-radius:16px;padding:20px 24px;color:white;margin-bottom:20px;position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:16px;}
.welcome-banner::before{content:'';position:absolute;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.06);top:-80px;right:-60px;}
.welcome-banner::after{content:'';position:absolute;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.04);bottom:-60px;left:160px;}
.wb-left{flex:1;min-width:0;position:relative;z-index:1;}
.wb-greeting{font-size:12px;opacity:.75;margin-bottom:2px;}
.wb-name{font-size:20px;font-weight:800;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.wb-sub{font-size:11px;opacity:.65;}
.wb-right{text-align:right;position:relative;z-index:1;flex-shrink:0;}
.wb-clock{font-size:24px;font-weight:800;letter-spacing:-0.5px;white-space:nowrap;}
.wb-date{font-size:11px;opacity:.7;margin-top:1px;}
/* pills: horizontal on desktop */
.wb-pills{display:flex;gap:8px;flex-wrap:nowrap;align-items:center;}
.wb-pill{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:4px 11px;font-size:11px;font-weight:700;backdrop-filter:blur(4px);transition:transform .2s,box-shadow .2s;white-space:nowrap;}
.wb-pill:hover{transform:translateY(-2px);}
.wb-pill-payable{background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.45);color:#fff;}
.wb-pill-receivable{background:rgba(34,197,94,.2);border:1px solid rgba(34,197,94,.45);color:#fff;}
.wb-pill-overdue{background:rgba(245,158,11,.25);border:1px solid rgba(245,158,11,.5);color:#fff;animation:overdue-glow 1.6s ease-in-out infinite;}
.wb-pill-count{font-size:14px;font-weight:900;line-height:1;}
.wb-pill-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;animation:dot-pulse 1.4s ease-in-out infinite;}
.wb-dot-red{background:#ef4444;}
.wb-dot-green{background:#22c55e;animation-delay:.3s;}
.wb-dot-amber{background:#f59e0b;animation-delay:.6s;}
@keyframes dot-pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.4;transform:scale(1.5);}}
@keyframes overdue-glow{0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,.4);}50%{box-shadow:0 0 0 7px rgba(245,158,11,0);}}
.wb-clock-wrap{margin-bottom:8px;}
.wb-filter-form{display:flex;align-items:center;gap:6px;justify-content:flex-end;}
/* mobile banner */
@media(max-width:640px){
  .welcome-banner{flex-direction:column;align-items:stretch;padding:16px 18px;gap:10px;}
  .wb-right{text-align:left;display:flex;align-items:center;justify-content:space-between;gap:10px;}
  .wb-clock-wrap{margin-bottom:0;}
  .wb-clock{font-size:20px;}
  .wb-filter-form{justify-content:flex-start;}
  .wb-pills{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-top:4px;}
  .wb-pill{justify-content:center;font-size:11px;padding:6px 10px;}
  .wb-pill-overdue{grid-column:1/-1;}
}

/* ── SECTION TITLE ── */
.db-section-title{font-size:13px;font-weight:700;color:var(--slate-500);text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.db-section-title::after{content:'';flex:1;height:1px;background:var(--slate-200);}

/* ── DASHBOARD SCHEDULE ACTION BUTTONS ── */
.dash-sched-btn{width:26px;height:26px;border-radius:6px;border:none;cursor:pointer;font-size:11px;display:inline-flex;align-items:center;justify-content:center;transition:.15s;}
.dash-btn-pay{background:#dbeafe;color:#1d4ed8;}.dash-btn-pay:hover{background:#bfdbfe;}
.dash-btn-approve{background:#d1fae5;color:#065f46;}.dash-btn-approve:hover{background:#a7f3d0;}
.dash-btn-reschedule{background:#e0e7ff;color:#3730a3;}.dash-btn-reschedule:hover{background:#c7d2fe;}
.dash-btn-cancel{background:#fef3c7;color:#d97706;}.dash-btn-cancel:hover{background:#fde68a;}
.dash-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;}
.dash-modal-overlay.open{display:flex;}
.dash-modal-box{background:white;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);max-width:480px;width:90%;max-height:90vh;overflow-y:auto;}
.dash-modal-header{padding:16px 20px;border-radius:16px 16px 0 0;display:flex;align-items:center;justify-content:space-between;color:white;}
.dash-modal-body{padding:20px;}
.dash-modal-footer{padding:14px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;}
.dash-form-group{margin-bottom:14px;}
.dash-form-group label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;}
.dash-form-group input,.dash-form-group select,.dash-form-group textarea{width:100%;padding:8px 11px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#1e293b;outline:none;transition:.15s;}
.dash-form-group input:focus,.dash-form-group select:focus,.dash-form-group textarea:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12);}
.dash-form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.dash-btn-primary{padding:8px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:700;font-size:13px;color:white;transition:.15s;}
.dash-btn-secondary{padding:8px 18px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;color:#64748b;cursor:pointer;font-weight:600;font-size:13px;transition:.15s;}.dash-btn-secondary:hover{background:#f1f5f9;}

/* ── KPI CARDS ── */
.kpi-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px;}
.kpi-card{background:white;border-radius:16px;padding:20px;border:1px solid var(--slate-200);box-shadow:0 1px 3px rgba(0,0,0,.04);position:relative;overflow:hidden;transition:box-shadow .2s,transform .2s;}
.kpi-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);transform:translateY(-1px);}
.kpi-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;}
.kpi-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.kpi-badge{font-size:10px;font-weight:700;padding:3px 8px;border-radius:999px;}
.kpi-num{font-size:28px;font-weight:800;color:var(--slate-900);line-height:1;}
.kpi-label{font-size:12px;color:var(--slate-500);margin-top:4px;font-weight:500;}
.kpi-subtext{font-size:11px;color:var(--slate-400);margin-top:4px;}
.kpi-bar{height:3px;background:var(--slate-100);border-radius:3px;margin-top:12px;overflow:hidden;}
.kpi-bar-fill{height:100%;border-radius:3px;}

/* ── PAYROLL / FINANCE CARDS ── */
.finance-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
/* Payroll + wallet rows on the left, Renewal Center as a full-height right rail. */
.db-split-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:16px;margin-bottom:24px;}
.db-split-main{min-width:0;}
.db-split-side{min-width:0;display:flex;flex-direction:column;}
.db-split-side .rn-panel{flex:1;}
/* While the rail sits beside the cards, pin the panel to the rail box so the
   grid row is sized by the left column alone. Without the absolute pin the row
   grows to fit the panel, the list never has a bound to compress into, and it
   spills down the page instead of scrolling. min-height:0 on each flex step is
   what lets that bound reach the list (flex items default to min-height:auto).
   Once stacked (<=1200px) the panel returns to flow and the base
   max-height:320px caps the list instead. */
@media(min-width:1201px){
  .db-split-side{position:relative;}
  .db-split-side > .rn-panel{position:absolute;inset:0;}
  .db-split-side .rn-source-pane.active{flex:1;display:flex;flex-direction:column;min-height:0;}
  .db-split-side .rn-items-scroll-wrap{flex:1;min-height:0;}
  .db-split-side .rn-items{max-height:none;flex:1;min-height:0;}
}
.portal-wallet-grid--flush{margin-bottom:0;}
.finance-card{border-radius:16px;padding:22px;color:white;position:relative;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.12);}
.finance-card::before{content:'';position:absolute;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.1);top:-40px;right:-30px;}
.finance-card::after{content:'';position:absolute;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.06);bottom:-20px;left:20px;}
.fc-icon{font-size:24px;margin-bottom:12px;position:relative;z-index:1;opacity:.8;}
.fc-label{font-size:12px;opacity:.8;margin-bottom:4px;position:relative;z-index:1;font-weight:500;}
.fc-amount{font-size:22px;font-weight:800;position:relative;z-index:1;}
.fc-sub{font-size:11px;opacity:.6;margin-top:4px;position:relative;z-index:1;}

/* ── CHART SECTION ── */
.charts-grid{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:24px;}
.chart-card{background:white;border-radius:16px;padding:20px;border:1px solid var(--slate-200);box-shadow:0 1px 3px rgba(0,0,0,.04);}
.chart-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
.chart-title{font-size:14px;font-weight:700;color:var(--slate-900);}
.chart-subtitle{font-size:11px;color:var(--slate-400);margin-top:2px;}
.chart-badge{font-size:11px;color:var(--slate-500);background:var(--slate-100);border:1px solid var(--slate-200);padding:4px 10px;border-radius:999px;}

/* ── 3-COLUMN LOWER SECTION ── */
.lower-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px;}
.info-card{background:white;border-radius:16px;padding:20px;border:1px solid var(--slate-200);box-shadow:0 1px 3px rgba(0,0,0,.04);}

/* ── ATTENDANCE TABLE ── */
.att-table{width:100%;border-collapse:collapse;}
.att-table th{font-size:11px;font-weight:600;color:var(--slate-500);text-align:left;padding:8px 12px;background:var(--slate-50);border-bottom:1px solid var(--slate-200);}
.att-table td{font-size:12px;padding:10px 12px;border-bottom:1px solid var(--slate-100);vertical-align:middle;}
.att-table tr:last-child td{border-bottom:none;}
.att-avatar{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:white;flex-shrink:0;}
.att-name{font-weight:600;color:var(--slate-800);font-size:12px;}
.att-name-trigger{font-weight:600;color:var(--slate-800);font-size:12px;background:none;border:none;padding:0;cursor:pointer;text-align:left;transition:color .2s;}
.att-name-trigger:hover{color:var(--indigo);}
.att-name-trigger:focus{outline:none;color:var(--indigo);}
.att-role{font-size:10px;color:var(--slate-400);}
.db-status-badge{font-size:10px;font-weight:700;padding:3px 10px;border-radius:999px;display:inline-block;}

/* ── QUICK ACTIONS ── */
.qa-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
.qa-item{background:#fff;border:1px solid var(--slate-200);border-radius:16px;padding:18px 10px 14px;text-align:center;cursor:pointer;transition:.2s;text-decoration:none;display:block;box-shadow:0 1px 4px rgba(0,0,0,.04);}
.qa-item:hover{border-color:var(--indigo);box-shadow:0 4px 16px rgba(79,70,229,.13);transform:translateY(-2px);}
.qa-icon{width:48px;height:48px;border-radius:14px;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;font-size:20px;}
.qa-icon i{font-size:20px;}
.qa-label{font-size:12px;font-weight:600;color:var(--slate-700);}

/* ── QUICK TASKS ── */
.qt-wrapper{position:relative;margin-bottom:24px;display:flex;align-items:center;gap:0;}
.qt-arrow{background:white;border:1.5px solid var(--slate-200);border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.08);transition:.2s;z-index:2;color:var(--slate-600);}
.qt-arrow:hover{border-color:var(--indigo);color:var(--indigo);box-shadow:0 4px 14px rgba(79,70,229,.18);}
.qt-row{overflow:hidden;padding-bottom:6px;flex:1;position:relative;cursor:default;user-select:none;}
.qt-track{display:flex;gap:14px;width:max-content;transition:transform 0.4s cubic-bezier(.4,0,.2,1);}
.qt-card{background:white;border:1.5px solid var(--slate-200);border-radius:18px;padding:22px 20px 16px;text-align:center;cursor:pointer;transition:.2s;text-decoration:none;display:flex;flex-direction:column;align-items:center;min-width:120px;flex-shrink:0;box-shadow:0 1px 4px rgba(0,0,0,.04);}
.qt-card:hover{border-color:var(--indigo);box-shadow:0 6px 20px rgba(79,70,229,.14);transform:translateY(-2px);}
.qt-card.qt-active{border-color:var(--indigo);box-shadow:0 4px 16px rgba(79,70,229,.18);}
.qt-icon-wrap{width:54px;height:54px;border-radius:16px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;font-size:22px;}
.qt-icon-wrap i{font-size:22px;}
.qt-label{font-size:12px;font-weight:700;color:var(--slate-700);white-space:nowrap;}

/* ── BOTTOM STATS ROW ── */
.bottom-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px;}
.bs-card{background:white;border-radius:14px;padding:16px;border:1px solid var(--slate-200);text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.04);}
.bs-icon{width:40px;height:40px;border-radius:11px;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;font-size:17px;}
.bs-num{font-size:20px;font-weight:800;color:var(--slate-900);}
.bs-label{font-size:11px;color:var(--slate-400);margin-top:2px;}

/* ── PERIOD FILTER ── */
.period-filter-form{display:flex;align-items:center;gap:8px;}
.period-filter-form input[type=month]{border:1px solid var(--slate-200);border-radius:8px;padding:5px 10px;font-size:12px;color:var(--slate-700);}
.period-filter-form button{background:var(--indigo);color:white;border:none;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;}

/* ── RENEWAL & SUBSCRIPTION ALERTS PANEL ── */
.rn-panel{background:white;border:1px solid var(--slate-200);border-radius:16px;padding:20px;display:flex;flex-direction:column;gap:0;box-shadow:0 1px 3px rgba(0,0,0,.04);}
.rn-panel-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;padding-bottom:12px;border-bottom:1px solid var(--slate-100);}
.rn-panel-title{font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:6px;}
.rn-bell-ic{font-size:15px;animation:rn-bell-shake 3s ease-in-out infinite;}
@keyframes rn-bell-shake{0%,90%,100%{transform:rotate(0);}92%{transform:rotate(-12deg);}96%{transform:rotate(12deg);}98%{transform:rotate(-8deg);}}
.rn-panel-badge{background:#fee2e2;color:#dc2626;font-size:10px;font-weight:700;padding:3px 9px;border-radius:12px;letter-spacing:.3px;white-space:nowrap;}
/* Overdue sits next to "Due" — filled red so the worse number reads first,
   muted while the count is zero so a clean panel stays calm. */
.rn-panel-badge-overdue{background:#dc2626;color:#fff;}
.rn-panel-badge-overdue.is-zero{background:var(--slate-100,#f1f5f9);color:var(--slate-400,#94a3b8);}
.rn-panel-actions{display:flex;align-items:center;gap:8px;flex-shrink:0;} 
.rn-source-nav{display:inline-flex;align-items:center;gap:6px;padding:2px 6px;border:1px solid var(--slate-200);border-radius:999px;background:var(--slate-50);} 
.rn-source-btn{width:22px;height:22px;border:none;border-radius:999px;background:#fff;color:var(--slate-600);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;} 
.rn-source-btn:hover{background:var(--indigo);color:#fff;} 
.rn-source-btn:disabled{opacity:.4;cursor:not-allowed;background:#fff;color:var(--slate-400);} 
.rn-source-label{font-size:10px;font-weight:700;color:var(--slate-600);letter-spacing:.5px;white-space:nowrap;} 
.rn-panel-sub{font-size:10.5px;color:var(--text3);margin-bottom:12px;letter-spacing:.3px;}
.rn-filter-row{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:10px;}
.rn-chip{font-size:10.5px;font-weight:600;padding:3px 10px;border-radius:20px;border:1px solid var(--border);background:var(--bg3);color:var(--text2);cursor:pointer;transition:all .15s;white-space:nowrap;}
.rn-chip:hover{border-color:var(--accent);color:var(--accent);}
.rn-chip.active{background:var(--accent);color:#fff;border-color:var(--accent);}
.rn-items{display:flex;flex-direction:column;gap:0;max-height:320px;overflow-y:auto;scroll-behavior:smooth;border:1px solid var(--slate-200);border-radius:14px;background:#fff;scrollbar-width:thin;scrollbar-color:#cbd5e1 #f1f5f9;}
.rn-items::-webkit-scrollbar{width:8px;}
.rn-items::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px;border:2px solid #f1f5f9;}
.rn-items::-webkit-scrollbar-thumb:hover{background:#94a3b8;}
.rn-items::-webkit-scrollbar-track{background:#f1f5f9;border-radius:0 14px 14px 0;}
.rn-items-scroll-wrap{display:flex;flex-direction:column;}
.rn-scroll-btn-row{display:flex;justify-content:flex-end;gap:6px;margin-top:8px;}
.rn-scroll-btn{width:24px;height:24px;border-radius:7px;border:1px solid var(--border);background:var(--bg3);color:var(--text2);font-size:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;}
.rn-scroll-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-light);}
.rn-scroll-btn:disabled{opacity:.45;cursor:not-allowed;}
.rn-more-items{margin-top:7px;}
.rn-item{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-radius:0;border-left:3px solid transparent;background:#fff;gap:8px;transition:all .15s;border-bottom:1px solid var(--slate-100);}
.rn-item:last-child{border-bottom:none;}
.rn-item:hover{background:#f8fafc;}
.rn-critical{border-left-color:var(--red);}
.rn-high{border-left-color:var(--orange);}
.rn-medium{border-left-color:var(--amber);}
.rn-item-left{display:flex;align-items:center;gap:9px;flex:1;min-width:0;}
.rn-item-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
.rn-ic-red{background:#fee2e2;}
.rn-ic-orange{background:#ffedd5;}
.rn-ic-amber{background:#fffbeb;}
.rn-ic-blue{background:#dbeafe;}
.rn-ic-purple{background:#f5f3ff;}
.rn-item-info{flex:1;min-width:0;}
.rn-item-name{font-size:12px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.rn-item-meta{font-size:10px;color:var(--text3);margin-top:1px;}
.rn-item-meta strong{color:var(--text2);}
.rn-item-right{display:flex;align-items:center;gap:6px;flex-shrink:0;}
.rn-days-badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px;font-family:'DM Mono',monospace;}
.rn-days-critical{background:#fee2e2;color:var(--red);}
.rn-days-high{background:#ffedd5;color:var(--orange);}
.rn-days-medium{background:#fffbeb;color:var(--amber);}
.rn-prio-btn{width:18px;height:18px;border-radius:50%;border:2px solid;cursor:pointer;font-size:8px;display:flex;align-items:center;justify-content:center;transition:all .15s;background:transparent;}
.rn-prio-critical{border-color:var(--red);color:var(--red);}
.rn-prio-high{border-color:var(--orange);color:var(--orange);}
.rn-prio-medium{border-color:var(--amber);color:var(--amber);}
.rn-prio-btn:hover{transform:scale(1.2);}
.rn-src-tag{display:inline-block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:1px 6px;border-radius:6px;margin-right:5px;vertical-align:1px;}
.rn-src-subscription{background:#ffedd5;color:#c2410c;}
.rn-src-document{background:#dbeafe;color:#1d4ed8;}
.rn-overdue-flag{font-weight:700;color:var(--red);}
.rn-group-divider{position:sticky;top:0;z-index:1;background:var(--slate-50,#f8fafc);border-bottom:1px solid var(--slate-200);padding:5px 14px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--slate-400,#94a3b8);}

/* Chat: keep long links inside the message panel */
#chatWindow,
#chatMessages {
  max-width: 100%;
  overflow-x: hidden;
}

#chatMessages .message-row-sent,
#chatMessages .message-row-received {
  max-width: 100%;
  min-width: 0;
}

#chatMessages .message-bubble {
  max-width: min(85%, 100%);
  min-width: 0;
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
}

#chatMessages .message-bubble * {
  max-width: 100%;
  overflow-wrap: anywhere;
  word-break: break-word;
}
.rn-view-more-row{display:flex;align-items:center;justify-content:space-between;margin-top:10px;}
.rn-view-more-btn{background:none;border:1px dashed var(--border2);border-radius:8px;padding:5px 14px;font-size:11px;font-weight:600;color:var(--accent);cursor:pointer;transition:all .15s;width:100%;}
.rn-view-more-btn:hover{background:var(--accent-light);border-color:var(--accent);}
.rn-total-note{font-size:10px;color:var(--text3);white-space:nowrap;margin-left:8px;}
.rn-source-pane{display:none;} 
.rn-source-pane.active{display:block;} 
.rn-doc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 12px;min-width:0;} 
.rn-doc-cell{min-width:0;} 
.rn-doc-label{display:block;font-size:9px;font-weight:700;color:var(--slate-400);text-transform:uppercase;letter-spacing:.4px;line-height:1.2;} 
.rn-doc-value{display:block;font-size:11px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.35;} 
.rn-doc-meta{font-size:10px;color:var(--text3);margin-top:2px;} 
.rn-modal{display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.58);align-items:center;justify-content:center;padding:16px;}
.rn-modal-card{width:100%;max-width:560px;max-height:90vh;overflow:hidden;background:#fff;border-radius:18px;box-shadow:0 18px 60px rgba(0,0,0,.24);display:flex;flex-direction:column;}
.rn-modal-h{padding:16px 18px;border-bottom:1px solid #e2e8f0;display:flex;align-items:flex-start;justify-content:space-between;gap:10px;}
.rn-modal-title{font-size:16px;font-weight:800;color:#0f172a;line-height:1.35;}
.rn-modal-sub{font-size:11px;color:#64748b;margin-top:3px;}
.rn-modal-close{width:30px;height:30px;border:none;border-radius:999px;background:#f1f5f9;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;line-height:1;flex-shrink:0;}
.rn-modal-body{padding:16px 18px;overflow:auto;}
.rn-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.rn-modal-item{border:1px solid #e2e8f0;border-radius:10px;padding:8px 10px;background:#f8fafc;}
.rn-modal-item.full{grid-column:1 / -1;}
.rn-modal-k{font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.35px;}
.rn-modal-v{font-size:13px;color:#0f172a;margin-top:2px;word-break:break-word;}
.rn-modal-v a{color:#2563eb;text-decoration:none;}
.rn-modal-v a:hover{text-decoration:underline;}

@media(max-width:640px){
    .rn-modal-grid{grid-template-columns:1fr;}
}

/* ── PORTAL WALLET CARDS ── */
.portal-section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.portal-section-header .db-section-title{margin-bottom:0;}
.portal-manage-link{font-size:12px;font-weight:700;color:var(--indigo);text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:.15s;}
.portal-manage-link:hover{color:var(--violet);}
.portal-wallet-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.portal-wallet-card{border-radius:16px;padding:20px;color:white;position:relative;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.15);transition:box-shadow .2s,transform .2s;}
.portal-wallet-card:hover{box-shadow:0 8px 28px rgba(0,0,0,.22);transform:translateY(-3px);}
.portal-wallet-card::before{content:'';position:absolute;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.1);top:-40px;right:-30px;}
.portal-wallet-card::after{content:'';position:absolute;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.06);bottom:-20px;left:20px;}
.pw-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;position:relative;z-index:1;}
.pw-logo{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;letter-spacing:.5px;flex-shrink:0;background:rgba(255,255,255,.22)!important;backdrop-filter:blur(4px);}
.pw-status{font-size:9px;font-weight:700;padding:3px 9px;border-radius:999px;letter-spacing:.5px;text-transform:uppercase;flex-shrink:0;}
/* The cards are narrower now — let the portal name truncate so it can't shove
   the status badge past the card edge. */
.pw-ident{display:flex;align-items:center;gap:10px;position:relative;z-index:1;flex:1;min-width:0;}
.pw-ident-text{min-width:0;}
.pw-name,.pw-code{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pw-status-active{background:rgba(255,255,255,.25);color:#fff;}
.pw-status-inactive{background:rgba(0,0,0,.25);color:rgba(255,255,255,.85);}
.pw-status-topup{background:rgba(255,255,255,.2);color:#fef08a;}
.pw-status-bsp{background:rgba(255,255,255,.3);color:#fff;}
.pw-name{font-size:13px;font-weight:800;color:#fff;margin-bottom:2px;line-height:1.2;}
.pw-code{font-size:10px;color:rgba(255,255,255,.65);font-weight:500;}
.pw-balance{font-size:22px;font-weight:800;color:#fff;margin:12px 0 4px;letter-spacing:-.3px;position:relative;z-index:1;}
.pw-footer{display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.2);position:relative;z-index:1;}
.pw-meta{font-size:10px;color:rgba(255,255,255,.7);}
.pw-meta strong{color:#fff;font-weight:700;}
.pw-meta-block{display:flex;flex-direction:column;gap:2px;}
.pw-meta-block--right{text-align:right;}
.pw-meta-label{font-size:9px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.6px;font-weight:600;}
.pw-meta-value{font-size:12px;color:#fff;font-weight:700;letter-spacing:-.2px;}
.pw-meta-divider{width:1px;background:rgba(255,255,255,.25);height:28px;flex-shrink:0;}
@media(max-width:1200px){.portal-wallet-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:640px){.portal-wallet-grid{grid-template-columns:1fr 1fr;}}

@media(max-width:1200px){
  .kpi-grid{grid-template-columns:repeat(2,1fr);}
  .charts-grid{grid-template-columns:1fr;}
  .lower-grid{grid-template-columns:1fr 1fr;}
  .bottom-stats{grid-template-columns:repeat(3,1fr);}
  .finance-grid{grid-template-columns:1fr 1fr;}
  /* Too narrow for a side rail — stack it under the main column. */
  .db-split-grid{grid-template-columns:1fr;}
  .db-split-side{margin-top:8px;}
  .portal-wallet-grid--flush{margin-bottom:24px;}
}
@media(max-width:768px){
  .kpi-grid{grid-template-columns:1fr 1fr;}
  .finance-grid{grid-template-columns:1fr;}
  .lower-grid{grid-template-columns:1fr;}
  .bottom-stats{grid-template-columns:repeat(2,1fr);}
  .welcome-banner{flex-direction:column;gap:16px;}
}

    </style>
@endsection

@section('main-content')
    @php
        $periodLabel = \Carbon\Carbon::createFromDate($selectedYear, $selectedMonth, 1)->format('F Y');
        $greeting = date('H') < 12 ? 'Good Morning' : (date('H') < 18 ? 'Good Afternoon' : 'Good Evening');
        $incomeMax = max($total_income, $total_expense, 1);
        $incomePercent = min(100, round(($total_income / $incomeMax) * 100));
        $expensePercent = min(100, round(($total_expense / $incomeMax) * 100));
    @endphp


@if(Auth::check() && auth()->user()->hasRole('super admin'))
    {{-- WELCOME BANNER --}}
    <div class="welcome-banner">
        <div class="wb-left">
            {{-- <div class="wb-greeting">{{ $greeting }} 👋</div> --}}
            {{-- <div class="wb-name">{{ auth()->user()->name }}</div> --}}
            {{-- <div class="wb-sub">
                @php
                    $roleName = ucfirst(auth()->user()->getRoleNames()->first() ?? '');
                    $deptName = optional(optional(auth()->user()->profile)->department)->name ?? '';
                    $wbSub = array_filter([$roleName, $deptName, $periodLabel]);
                @endphp
                {{ implode(' · ', $wbSub) }}
            </div> --}}
            @php
                $today = \Carbon\Carbon::today()->toDateString();
                $todayPayableCount    = $dashboardPayable->filter(fn($s) => in_array($s->status, ['pending','approved']) && \Carbon\Carbon::parse($s->scheduled_date)->toDateString() === $today)->count();
                $todayReceivableCount = $dashboardReceivable->filter(fn($s) => in_array($s->status, ['pending','approved']) && \Carbon\Carbon::parse($s->scheduled_date)->toDateString() === $today)->count();
                $overdueTotal         = $dashboardPayable->where('status','overdue')->count() + $dashboardReceivable->where('status','overdue')->count();
            @endphp
            <div class="wb-pills">
                <div class="wb-pill wb-pill-payable">
                    <span class="wb-pill-dot wb-dot-red"></span>
                    <span class="wb-pill-count">{{ $todayPayableCount }}</span>
                    Payable Today
                </div>
                <div class="wb-pill wb-pill-receivable">
                    <span class="wb-pill-dot wb-dot-green"></span>
                    <span class="wb-pill-count">{{ $todayReceivableCount }}</span>
                    Receivable Today
                </div>
                @if($overdueTotal > 0)
                <div class="wb-pill wb-pill-overdue">
                    <span class="wb-pill-dot wb-dot-amber"></span>
                    <span class="wb-pill-count">{{ $overdueTotal }}</span>
                    Overdue
                </div>
                @endif
            </div>
        </div>
        <div class="wb-right">
            {{-- <div class="wb-clock-wrap">
                <div class="wb-clock" id="live-clock">--:--:-- --</div>
                <div class="wb-date">Live System Clock</div>
            </div> --}}
            <form method="GET" action="" class="wb-filter-form">
                <input type="month" name="period" value="{{ $period }}" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:8px;padding:5px 10px;font-size:11px;color:white;color-scheme:dark;">
                <button type="submit" style="background:rgba(255,255,255,.2);color:white;border:1px solid rgba(255,255,255,.3);border-radius:8px;padding:5px 12px;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;">Filter</button>
            </form>
        </div>
    </div>
    {{-- NOTICE SLIDER --}}
    @if ($notices->isNotEmpty())
    <div class="ns-wrap" id="noticeSlider">
        <div class="ns-track" id="nsTrack">
            @foreach ($notices as $i => $notice)
                @php
                    $colors = explode(',', $notice->card_color ?? '#f97316,#f59e0b');
                    $from = trim($colors[0] ?? '#f97316');
                    $to   = trim($colors[1] ?? '#f59e0b');
                    $textColor = $notice->text_color ?? '#ffffff';
                    $icon = $notice->icon ?? '📢';
                @endphp
                <div class="ns-slide" style="background:linear-gradient(135deg,{{ $from }},{{ $to }});color:{{ $textColor }};">
                    <div class="ns-slide-icon" style="color:{{ $textColor }};font-size:22px;"><i class="{{ $icon }}"></i></div>
                    <div class="ns-slide-body">
                        <div class="ns-reminder-badge" style="color:{{ $textColor }};">
                            <i class="{{ $notice->badge_icon ?? 'fas fa-bell' }}"></i>
                            {{ strtoupper($notice->badge_label ?? 'REMINDER') }}
                        </div>
                        <div class="ns-slide-title" style="color:{{ $textColor }};">{{ $notice->title }}</div>
                        <div class="ns-slide-desc" style="color:{{ $textColor }};">{{ $notice->description }}</div>
                    </div>
                    @if($notice->slide_image)
                    <div class="ns-slide-img">
                        <img src="{{ asset('storage/' . $notice->slide_image) }}" alt="">
                    </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if($notices->count() > 1)
        <button class="ns-arrow ns-prev" id="nsPrev"><i class="fas fa-chevron-left"></i></button>
        <button class="ns-arrow ns-next" id="nsNext"><i class="fas fa-chevron-right"></i></button>
        <div class="ns-dots" id="nsDots">
            @foreach($notices as $i => $n)
            <div class="ns-dot {{ $i === 0 ? 'active' : '' }}" data-index="{{ $i }}"></div>
            @endforeach
        </div>
        @endif
    </div>
    <script>
    (function(){
        var track = document.getElementById('nsTrack');
        var dots  = document.querySelectorAll('#nsDots .ns-dot');
        var total = {{ $notices->count() }};
        var cur   = 0;
        var timer;

        function goTo(n){
            cur = (n + total) % total;
            track.style.transform = 'translateX(-' + (cur * 100) + '%)';
            dots.forEach(function(d,i){ d.classList.toggle('active', i===cur); });
        }
        function next(){ goTo(cur + 1); }
        function prev(){ goTo(cur - 1); }
        function startTimer(){ timer = setInterval(next, 5000); }
        function stopTimer(){ clearInterval(timer); }

        var pBtn = document.getElementById('nsPrev');
        var nBtn = document.getElementById('nsNext');
        if(pBtn) pBtn.addEventListener('click', function(){ stopTimer(); prev(); startTimer(); });
        if(nBtn) nBtn.addEventListener('click', function(){ stopTimer(); next(); startTimer(); });
        dots.forEach(function(d){ d.addEventListener('click', function(){ stopTimer(); goTo(+this.dataset.index); startTimer(); }); });

        var wrap = document.getElementById('noticeSlider');
        wrap.addEventListener('mouseenter', stopTimer);
        wrap.addEventListener('mouseleave', startTimer);

        if(total > 1) startTimer();
    })();
    </script>
    @endif

    {{-- QUICK TASKS --}}
    @php
        $qtRoleSlug = \Illuminate\Support\Str::slug(auth()->user()->getRoleNames()->first() ?? 'admin');
        $quickTasks = [
            ['icon'=>'fas fa-user-plus','label'=>'Add Employee','bg'=>'#ede9fe','color'=>'#7c3aed','route'=>'role.employees.create','active'=>true],
            ['icon'=>'fas fa-user-check','label'=>'Mark Attendance','bg'=>'#dcfce7','color'=>'#16a34a','route'=>'role.attendances.index','active'=>false],
            ['icon'=>'fas fa-calendar-minus','label'=>'Apply Leave','bg'=>'#dbeafe','color'=>'#2563eb','route'=>'role.leaves.index','active'=>false],
            ['icon'=>'fas fa-file-invoice-dollar','label'=>'Payroll','bg'=>'#ccfbf1','color'=>'#0d9488','route'=>'role.payrolls.index','active'=>false],
            ['icon'=>'fas fa-tasks','label'=>'Create Task','bg'=>'#fef9c3','color'=>'#ca8a04','route'=>'role.tasks.index','active'=>false],
            ['icon'=>'fas fa-ticket-alt','label'=>'Support Ticket','bg'=>'#fce7f3','color'=>'#db2777','route'=>'role.support-tickets.index','active'=>false],
            ['icon'=>'fas fa-chart-bar','label'=>'Reports','bg'=>'#f1f5f9','color'=>'#475569','route'=>'role.reports.index','active'=>false],
        ];
    @endphp
    <div class="db-section-title">⚡ Quick Action</div>
    <div class="qt-wrapper">
        <button class="qt-arrow" id="qt-prev" aria-label="Previous"><i class="fas fa-chevron-left" style="font-size:13px;"></i></button>
        <div class="qt-row" id="qt-row">
            <div class="qt-track" id="qt-track">
                {{-- DM Portal SSO Button --}}
                <a href="{{ route('sso.redirect') }}" class="qt-card" target="_blank" title="DM Portal এ auto login">
                    <div class="qt-icon-wrap" style="background:#e0f2fe;"><i class="fas fa-external-link-alt" style="color:#0284c7;"></i></div>
                    <div class="qt-label">DM Portal</div>
                </a>
                @foreach ($quickTasks as $qt)
                    @php
                        try { $qtUrl = route($qt['route'], ['role' => $qtRoleSlug]); } catch(\Exception $e) { $qtUrl = '#'; }
                    @endphp
                    <a href="{{ $qtUrl }}" class="qt-card {{ $qt['active'] ? 'qt-active' : '' }}">
                        <div class="qt-icon-wrap" style="background:{{ $qt['bg'] }};"><i class="{{ $qt['icon'] }}" style="color:{{ $qt['color'] }};"></i></div>
                        <div class="qt-label">{{ $qt['label'] }}</div>
                    </a>
                @endforeach
                {{-- duplicate set 1 for infinite loop --}}
                <a href="{{ route('sso.redirect') }}" class="qt-card" target="_blank" aria-hidden="true" tabindex="-1">
                    <div class="qt-icon-wrap" style="background:#e0f2fe;"><i class="fas fa-external-link-alt" style="color:#0284c7;"></i></div>
                    <div class="qt-label">DM Portal</div>
                </a>
                @foreach ($quickTasks as $qt)
                    @php
                        try { $qtUrl = route($qt['route'], ['role' => $qtRoleSlug]); } catch(\Exception $e) { $qtUrl = '#'; }
                    @endphp
                    <a href="{{ $qtUrl }}" class="qt-card {{ $qt['active'] ? 'qt-active' : '' }}" aria-hidden="true" tabindex="-1">
                        <div class="qt-icon-wrap" style="background:{{ $qt['bg'] }};"><i class="{{ $qt['icon'] }}" style="color:{{ $qt['color'] }};"></i></div>
                        <div class="qt-label">{{ $qt['label'] }}</div>
                    </a>
                @endforeach
            </div>
        </div>
        <button class="qt-arrow" id="qt-next" aria-label="Next"><i class="fas fa-chevron-right" style="font-size:13px;"></i></button>
    </div>

    
    
    {{-- FINANCE KPI ROW --}}
    <div class="db-section-title">Accounting &amp; Finance</div>
    <div class="kpi-grid" style="margin-bottom:16px;">
        <div class="kpi-card" style="border-top:3px solid #10b981;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#d1fae5;">💹</div>
                <span class="kpi-badge" style="background:#d1fae5;color:#059669;">Income</span>
            </div>
            <div class="kpi-num" style="font-size:20px;">৳{{ number_format($total_income, 0) }}</div>
            <div class="kpi-label">Total Income ({{ $periodLabel }})</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ $incomePercent }}%;background:#10b981;"></div></div>
        </div>
        <div class="kpi-card" style="border-top:3px solid #ef4444;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#fef2f2;">💸</div>
                <span class="kpi-badge" style="background:#fef2f2;color:#dc2626;">Expense</span>
            </div>
            <div class="kpi-num" style="font-size:20px;">৳{{ number_format($total_expense, 0) }}</div>
            <div class="kpi-label">Total Expense ({{ $periodLabel }})</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ $expensePercent }}%;background:#ef4444;"></div></div>
        </div>
        <div class="kpi-card" style="border-top:3px solid #4f46e5;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#ede9fe;">📊</div>
                <span class="kpi-badge" style="{{ $total_profit >= 0 ? 'background:#d1fae5;color:#059669;' : 'background:#fef2f2;color:#dc2626;' }}">{{ $total_profit >= 0 ? 'Profit' : 'Loss' }}</span>
            </div>
            <div class="kpi-num" style="font-size:20px;">৳{{ number_format(abs($total_profit), 0) }}</div>
            <div class="kpi-label">Net Profit ({{ $periodLabel }})</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ $incomePercent }}%;background:#4f46e5;"></div></div>
        </div>
        <div class="kpi-card" style="border-top:3px solid #14b8a6;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#ccfbf1;">🏦</div>
                <span class="kpi-badge" style="background:#ccfbf1;color:#0d9488;">Live</span>
            </div>
            <div class="kpi-num" style="font-size:20px;">৳{{ number_format($total_bank_balance, 0) }}</div>
            <div class="kpi-label">Bank Balance</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:80%;background:#14b8a6;"></div></div>
        </div>
        <div class="kpi-card" style="border-top:3px solid #f59e0b;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#fef3c7;">💵</div>
                <span class="kpi-badge" style="background:#fef3c7;color:#b45309;">Live</span>
            </div>
            <div class="kpi-num" style="font-size:20px;">৳{{ number_format($total_office_cash, 0) }}</div>
            <div class="kpi-label">Office Cash</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:80%;background:#f59e0b;"></div></div>
        </div>
    </div>

    {{-- Renewal Center data (shared by the sidebar and the lower panel) --}}
    @php
        $dmAccessCollection = collect($dmAccesses ?? []);
        $dmDocumentCollection = collect($dmDocuments ?? []);
        $dmAllCollection = collect($dmAllItems ?? []);
        $dmOverdueCount = $dmAllCollection->where('is_overdue', true)->count();

        // The Subscriptions / Documents panes render straight off the raw DM
        // payloads, so their overdue tallies are derived here rather than in
        // the controller digest.
        $dmToday = \Carbon\Carbon::today();
        $dmCountOverdue = function ($collection, array $dateKeys) use ($dmToday) {
            return $collection->filter(function ($row) use ($dateKeys, $dmToday) {
                foreach ($dateKeys as $key) {
                    $raw = data_get($row, $key);
                    if (empty($raw)) {
                        continue;
                    }

                    try {
                        return \Carbon\Carbon::parse($raw)->startOfDay()->lt($dmToday);
                    } catch (\Throwable $e) {
                        return false;
                    }
                }

                return false;
            })->count();
        };

        $dmSources = [
            [
                'key' => 'all',
                'label' => 'All',
                'count' => $dmAllCollection->count(),
                'overdue' => $dmOverdueCount,
                'note' => 'Live from Data Management · ' . $dmToday->format('F Y')
                    . ' subscriptions & document renewals'
                    . ($dmOverdueCount > 0 ? ' · ' . $dmOverdueCount . ' overdue listed first' : ''),
            ],
            [
                'key' => 'subscriptions',
                'label' => 'Subscriptions',
                'count' => $dmAccessCollection->count(),
                'overdue' => $dmCountOverdue($dmAccessCollection, ['expired_date', 'renewal_date']),
                'note' => 'Live from Data Management · Next renewals and expired access items',
            ],
            [
                'key' => 'documents',
                'label' => 'Documents',
                'count' => $dmDocumentCollection->count(),
                'overdue' => $dmCountOverdue($dmDocumentCollection, ['renewal_date', 'expired_date', 'documents.renewal_date']),
                'note' => 'Live from Data Management · Expired and upcoming document renewals',
            ],
        ];
    @endphp

    {{-- PAYROLL SUMMARY + PORTAL WALLETS + RENEWAL SIDEBAR --}}
    @php
        $portalGradients = [
            ['#4f46e5','#7c3aed'],
            ['#dc2626','#be185d'],
            ['#0d9488','#059669'],
            ['#f97316','#eab308'],
            ['#2563eb','#0ea5e9'],
            ['#7c3aed','#c026d3'],
            ['#059669','#14b8a6'],
            ['#d97706','#ef4444'],
        ];
        $portalAbbrevs = function(string $name): string {
            $words = preg_split('/[\s_\-]+/', strtoupper($name));
            if (count($words) >= 2) return substr($words[0],0,1).substr($words[1],0,1).substr($words[count($words)-1],0,1);
            return substr($name,0,3);
        };

        $portalShown = $portalWallets->take(3)->values();
    @endphp

    <div class="db-split-grid">
        <div class="db-split-main">
            <div class="db-section-title">Payroll Summary</div>
            <div class="finance-grid">
                <div class="finance-card" style="background:linear-gradient(135deg,#dc2626,#be185d);">
                    <div class="fc-icon">⚖️</div>
                    <div class="fc-label">Total Liability</div>
                    <div class="fc-amount">৳{{ number_format($total_liability, 0) }}</div>
                    <div class="fc-sub">Cumulative balance</div>
                </div>
                <div class="finance-card" style="background:linear-gradient(135deg,#f97316,#eab308);">
                    <div class="fc-icon">💳</div>
                    <div class="fc-label">Accounts Payable</div>
                    <div class="fc-amount">৳{{ number_format($total_payable, 0) }}</div>
                    <div class="fc-sub">{{ $periodLabel }}</div>
                </div>
                <div class="finance-card" style="background:linear-gradient(135deg,#059669,#0d9488);">
                    <div class="fc-icon">💰</div>
                    <div class="fc-label">Accounts Receivable</div>
                    <div class="fc-amount">৳{{ number_format($total_receivable, 0) }}</div>
                    <div class="fc-sub">Accounts receivable</div>
                </div>
            </div>

            @if($portalShown->count() > 0)
            <div class="portal-section-header">
                <div class="db-section-title" style="margin-bottom:0;">✈️ Portal Accounts &mdash; IATA &amp; GDS Wallets</div>
                @can('view portal')
                @php try { $portalMgmtUrl = route('role.portal-management.index', ['role' => $qtRoleSlug]); } catch(\Exception $e) { $portalMgmtUrl = '#'; } @endphp
                <a href="{{ $portalMgmtUrl }}" class="portal-manage-link">Manage Portals <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
                @endcan
            </div>
            <div class="portal-wallet-grid portal-wallet-grid--flush">
                @foreach($portalShown as $i => $pw)
                    @include('panel.dashboard.partials.portal-wallet-card')
                @endforeach
            </div>
            @endif
        </div>

        <div class="db-split-side">
            @include('panel.dashboard.partials.renewal-center')
        </div>
    </div>


    {{-- PAYMENT SCHEDULES --}}
    @can('view payment schedule')
    @php
        $roleSlugForSched = \Illuminate\Support\Str::slug(auth()->user()->getRoleNames()->first() ?? 'admin');
        $schedTodayUrl   = request()->fullUrlWithQuery(['schedule_filter' => 'today']);
        $sched7DaysUrl   = request()->fullUrlWithQuery(['schedule_filter' => '7days']);
        $schedRouteUrl   = route('role.payment-schedules.index', ['role' => $roleSlugForSched]);
    @endphp
    <div class="db-section-title" style="margin-top:8px;">Payment Schedules</div>
    <div style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">
                @if($scheduleFilter === '7days')
                    Upcoming 7 Days ({{ \Carbon\Carbon::today()->format('d M') }} – {{ \Carbon\Carbon::today()->addDays(6)->format('d M Y') }})
                @else
                    Today — {{ \Carbon\Carbon::today()->format('d M Y') }}
                @endif
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <a href="{{ $schedTodayUrl }}" style="padding:4px 14px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid;text-decoration:none;transition:.15s;{{ $scheduleFilter !== '7days' ? 'background:#4f46e5;color:white;border-color:#4f46e5;' : 'background:white;color:#64748b;border-color:#e2e8f0;' }}">Today</a>
                <a href="{{ $sched7DaysUrl }}" style="padding:4px 14px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid;text-decoration:none;transition:.15s;{{ $scheduleFilter === '7days' ? 'background:#4f46e5;color:white;border-color:#4f46e5;' : 'background:white;color:#64748b;border-color:#e2e8f0;' }}">Upcoming 7 Days</a>
                <a href="{{ $schedRouteUrl }}" style="padding:4px 14px;border-radius:999px;font-size:11px;font-weight:600;text-decoration:none;color:#4f46e5;background:#ede9fe;border:1px solid #c4b5fd;">View All</a>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            {{-- Payable --}}
            <div style="background:white;border-radius:16px;border:1px solid #fecaca;box-shadow:0 1px 3px rgba(0,0,0,.04);overflow:hidden;">
                <div style="padding:14px 18px;background:#fef2f2;border-bottom:1px solid #fecaca;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:10px;background:#fee2e2;display:flex;align-items:center;justify-content:center;"><i class="fas fa-arrow-circle-up" style="color:#dc2626;font-size:15px;"></i></div>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#dc2626;">Payable</div>
                            <div style="font-size:10px;color:#f87171;">{{ $dashboardPayable->count() }} schedule(s)</div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:17px;font-weight:800;color:#dc2626;">৳{{ number_format($dashboardPayable->sum('amount'), 0) }}</div>
                        <div style="font-size:10px;color:#94a3b8;">Total due</div>
                    </div>
                </div>
                <div style="max-height:260px;overflow-y:auto;">
                    @forelse($dashboardPayable as $sched)
                    @php
                        $sStyle = match($sched->status) {
                            'overdue'  => 'background:#fee2e2;color:#dc2626;',
                            'approved' => 'background:#dbeafe;color:#2563eb;',
                            default    => 'background:#fef3c7;color:#d97706;',
                        };
                        $pri = $sched->priority ?? 'medium';
                        $priStyle = match($pri) {
                            'high'   => 'background:#fee2e2;color:#991b1b;',
                            'low'    => 'background:#e0e7ff;color:#3730a3;',
                            default  => 'background:#fef3c7;color:#92400e;',
                        };
                        $priIcon = match($pri) {
                            'high'  => '▲',
                            'low'   => '▼',
                            default => '●',
                        };
                    @endphp
                    <div style="padding:10px 18px;border-bottom:1px solid #fff1f2;display:flex;align-items:center;gap:8px;{{ $pri === 'high' ? 'border-left:3px solid #ef4444;' : '' }}">
                        <div style="min-width:0;flex:1;">
                            <div style="display:flex;align-items:center;gap:5px;">
                                <div style="font-size:12px;font-weight:600;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $sched->party_name ?? '—' }}</div>
                                <span style="font-size:9px;font-weight:800;padding:1px 5px;border-radius:999px;flex-shrink:0;{{ $priStyle }}">{{ $priIcon }} {{ ucfirst($pri) }}</span>
                            </div>
                            <div style="font-size:10px;color:#94a3b8;margin-top:2px;">
                                {{ \Carbon\Carbon::parse($sched->scheduled_date)->format('d M Y') }}@if($sched->source_label) &middot; {{ $sched->source_label }}@endif
                            </div>
                            <div style="display:flex;align-items:center;gap:5px;margin-top:3px;flex-wrap:wrap;">
                                <span style="font-size:9px;font-weight:700;padding:1px 6px;border-radius:999px;background:#fee2e2;color:#b91c1c;text-transform:capitalize;">{{ $sched->party_type ?? '—' }}</span>
                                @if($sched->projectCategory)
                                    <span style="font-size:9px;font-weight:700;padding:1px 6px;border-radius:999px;background:#ede9fe;color:#6d28d9;"><i class="fas fa-folder" style="margin-right:2px;font-size:8px;"></i>{{ $sched->projectCategory->name }}</span>
                                @endif
                            </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-size:12px;font-weight:800;color:#dc2626;">৳{{ number_format($sched->amount, 0) }}</div>
                            <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;{{ $sStyle }}">{{ ucfirst($sched->status) }}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:3px;flex-shrink:0;">
                            @if($sched->status === 'approved')
                                <button class="dash-sched-btn dash-btn-pay" title="Mark as Paid"
                                    onclick="dashOpenMarkPaid({{ $sched->id }},'{{ addslashes($sched->party_name ?? '') }}','{{ number_format($sched->amount,2) }}',{{ $sched->amount }},'{{ $sched->type }}','{{ $sched->scheduled_date->format('Y-m-d') }}')">
                                    <i class="fas fa-money-bill-wave"></i></button>
                            @endif
                            @if(in_array($sched->status,['pending','overdue']))
                                @can('approve payment schedule')
                                <button class="dash-sched-btn dash-btn-approve" title="Approve"
                                    onclick="dashOpenApprove({{ $sched->id }},'{{ addslashes($sched->party_name ?? '') }}','{{ number_format($sched->amount,2) }}')">
                                    <i class="fas fa-check"></i></button>
                                @endcan
                            @endif
                            @if(!in_array($sched->status,['paid','cancelled']))
                                <button class="dash-sched-btn dash-btn-reschedule" title="Reschedule"
                                    onclick="dashOpenReschedule({{ $sched->id }},'{{ addslashes($sched->party_name ?? '') }}','{{ $sched->scheduled_date->format('Y-m-d') }}')">
                                    <i class="fas fa-calendar-alt"></i></button>
                                <button class="dash-sched-btn dash-btn-cancel" title="Cancel"
                                    onclick="dashCancelSchedule({{ $sched->id }},'{{ addslashes($sched->party_name ?? '') }}')">
                                    <i class="fas fa-ban"></i></button>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div style="padding:28px 18px;text-align:center;color:#94a3b8;font-size:12px;">No payable schedules.</div>
                    @endforelse
                </div>
            </div>

            {{-- Receivable --}}
            <div style="background:white;border-radius:16px;border:1px solid #bbf7d0;box-shadow:0 1px 3px rgba(0,0,0,.04);overflow:hidden;">
                <div style="padding:14px 18px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:10px;background:#d1fae5;display:flex;align-items:center;justify-content:center;"><i class="fas fa-arrow-circle-down" style="color:#16a34a;font-size:15px;"></i></div>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#16a34a;">Receivable</div>
                            <div style="font-size:10px;color:#4ade80;">{{ $dashboardReceivable->count() }} schedule(s)</div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:17px;font-weight:800;color:#16a34a;">৳{{ number_format($dashboardReceivable->sum('amount'), 0) }}</div>
                        <div style="font-size:10px;color:#94a3b8;">Total due</div>
                    </div>
                </div>
                <div style="max-height:260px;overflow-y:auto;">
                    @forelse($dashboardReceivable as $sched)
                    @php
                        $rStyle = match($sched->status) {
                            'overdue'  => 'background:#fee2e2;color:#dc2626;',
                            'approved' => 'background:#dbeafe;color:#2563eb;',
                            default    => 'background:#fef3c7;color:#d97706;',
                        };
                        $rPri = $sched->priority ?? 'medium';
                        $rPriStyle = match($rPri) {
                            'high'   => 'background:#fee2e2;color:#991b1b;',
                            'low'    => 'background:#e0e7ff;color:#3730a3;',
                            default  => 'background:#fef3c7;color:#92400e;',
                        };
                        $rPriIcon = match($rPri) {
                            'high'  => '▲',
                            'low'   => '▼',
                            default => '●',
                        };
                    @endphp
                    <div style="padding:10px 18px;border-bottom:1px solid #f0fdf4;display:flex;align-items:center;gap:8px;{{ $rPri === 'high' ? 'border-left:3px solid #ef4444;' : '' }}">
                        <div style="min-width:0;flex:1;">
                            <div style="display:flex;align-items:center;gap:5px;">
                                <div style="font-size:12px;font-weight:600;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $sched->party_name ?? '—' }}</div>
                                <span style="font-size:9px;font-weight:800;padding:1px 5px;border-radius:999px;flex-shrink:0;{{ $rPriStyle }}">{{ $rPriIcon }} {{ ucfirst($rPri) }}</span>
                            </div>
                            <div style="font-size:10px;color:#94a3b8;margin-top:2px;">
                                {{ \Carbon\Carbon::parse($sched->scheduled_date)->format('d M Y') }}@if($sched->source_label) &middot; {{ $sched->source_label }}@endif
                            </div>
                            <div style="display:flex;align-items:center;gap:5px;margin-top:3px;flex-wrap:wrap;">
                                <span style="font-size:9px;font-weight:700;padding:1px 6px;border-radius:999px;background:#dcfce7;color:#15803d;text-transform:capitalize;">{{ $sched->party_type ?? '—' }}</span>
                                @if($sched->projectCategory)
                                    <span style="font-size:9px;font-weight:700;padding:1px 6px;border-radius:999px;background:#ede9fe;color:#6d28d9;"><i class="fas fa-folder" style="margin-right:2px;font-size:8px;"></i>{{ $sched->projectCategory->name }}</span>
                                @endif
                            </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-size:12px;font-weight:800;color:#16a34a;">৳{{ number_format($sched->amount, 0) }}</div>
                            <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;{{ $rStyle }}">{{ ucfirst($sched->status) }}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:3px;flex-shrink:0;">
                            @if($sched->status === 'approved')
                                <button class="dash-sched-btn dash-btn-pay" title="Mark as Paid"
                                    onclick="dashOpenMarkPaid({{ $sched->id }},'{{ addslashes($sched->party_name ?? '') }}','{{ number_format($sched->amount,2) }}',{{ $sched->amount }},'{{ $sched->type }}','{{ $sched->scheduled_date->format('Y-m-d') }}')">
                                    <i class="fas fa-money-bill-wave"></i></button>
                            @endif
                            @if(in_array($sched->status,['pending','overdue']))
                                @can('approve payment schedule')
                                <button class="dash-sched-btn dash-btn-approve" title="Approve"
                                    onclick="dashOpenApprove({{ $sched->id }},'{{ addslashes($sched->party_name ?? '') }}','{{ number_format($sched->amount,2) }}')">
                                    <i class="fas fa-check"></i></button>
                                @endcan
                            @endif
                            @if(!in_array($sched->status,['paid','cancelled']))
                                <button class="dash-sched-btn dash-btn-reschedule" title="Reschedule"
                                    onclick="dashOpenReschedule({{ $sched->id }},'{{ addslashes($sched->party_name ?? '') }}','{{ $sched->scheduled_date->format('Y-m-d') }}')">
                                    <i class="fas fa-calendar-alt"></i></button>
                                <button class="dash-sched-btn dash-btn-cancel" title="Cancel"
                                    onclick="dashCancelSchedule({{ $sched->id }},'{{ addslashes($sched->party_name ?? '') }}')">
                                    <i class="fas fa-ban"></i></button>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div style="padding:28px 18px;text-align:center;color:#94a3b8;font-size:12px;">No receivable schedules.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- PAID / RECEIVED PAYMENTS --}}
    <div class="db-section-title" style="margin-top:8px;">Payment History</div>
    <div style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">
                @if($scheduleFilter === '7days')
                    Last 7 Days ({{ \Carbon\Carbon::today()->subDays(6)->format('d M') }} – {{ \Carbon\Carbon::today()->format('d M Y') }})
                @else
                    Today — {{ \Carbon\Carbon::today()->format('d M Y') }}
                @endif
            </div>
            <div style="font-size:11px;color:#94a3b8;">Completed payments &amp; receipts</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            {{-- Paid Out --}}
            <div style="background:white;border-radius:16px;border:1px solid #e9d5ff;box-shadow:0 1px 3px rgba(0,0,0,.04);overflow:hidden;">
                <div style="padding:14px 18px;background:#faf5ff;border-bottom:1px solid #e9d5ff;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center;"><i class="fas fa-paper-plane" style="color:#7c3aed;font-size:15px;"></i></div>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#7c3aed;">Paid Out</div>
                            <div style="font-size:10px;color:#a78bfa;">{{ $dashboardPaidPayments->count() }} payment(s)</div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:17px;font-weight:800;color:#7c3aed;">৳{{ number_format($dashboardPaidPayments->sum('paid_amount'), 0) }}</div>
                        <div style="font-size:10px;color:#94a3b8;">Total paid</div>
                    </div>
                </div>
                <div style="max-height:260px;overflow-y:auto;">
                    @forelse($dashboardPaidPayments as $sched)
                    <div style="padding:10px 18px;border-bottom:1px solid #faf5ff;display:flex;align-items:center;justify-content:space-between;gap:10px;">
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:12px;font-weight:600;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $sched->party_name ?? '—' }}</div>
                            <div style="font-size:10px;color:#94a3b8;margin-top:2px;">
                                Paid: {{ $sched->paid_date ? \Carbon\Carbon::parse($sched->paid_date)->format('d M Y') : '—' }}
                                @if($sched->source_label) &middot; {{ $sched->source_label }}@endif
                            </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-size:12px;font-weight:800;color:#7c3aed;">৳{{ number_format($sched->paid_amount ?? $sched->amount, 0) }}</div>
                            <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;background:#ede9fe;color:#7c3aed;">Paid</span>
                        </div>
                    </div>
                    @empty
                    <div style="padding:28px 18px;text-align:center;color:#94a3b8;font-size:12px;">No paid payments
                        @if($scheduleFilter === '7days') in last 7 days @else today @endif.
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Received --}}
            <div style="background:white;border-radius:16px;border:1px solid #bae6fd;box-shadow:0 1px 3px rgba(0,0,0,.04);overflow:hidden;">
                <div style="padding:14px 18px;background:#f0f9ff;border-bottom:1px solid #bae6fd;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:10px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;"><i class="fas fa-hand-holding-usd" style="color:#0284c7;font-size:15px;"></i></div>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#0284c7;">Received</div>
                            <div style="font-size:10px;color:#38bdf8;">{{ $dashboardPaidReceivables->count() }} receipt(s)</div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:17px;font-weight:800;color:#0284c7;">৳{{ number_format($dashboardPaidReceivables->sum('paid_amount'), 0) }}</div>
                        <div style="font-size:10px;color:#94a3b8;">Total received</div>
                    </div>
                </div>
                <div style="max-height:260px;overflow-y:auto;">
                    @forelse($dashboardPaidReceivables as $sched)
                    <div style="padding:10px 18px;border-bottom:1px solid #f0f9ff;display:flex;align-items:center;justify-content:space-between;gap:10px;">
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:12px;font-weight:600;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $sched->party_name ?? '—' }}</div>
                            <div style="font-size:10px;color:#94a3b8;margin-top:2px;">
                                Received: {{ $sched->paid_date ? \Carbon\Carbon::parse($sched->paid_date)->format('d M Y') : '—' }}
                                @if($sched->source_label) &middot; {{ $sched->source_label }}@endif
                            </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-size:12px;font-weight:800;color:#0284c7;">৳{{ number_format($sched->paid_amount ?? $sched->amount, 0) }}</div>
                            <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;background:#e0f2fe;color:#0284c7;">Received</span>
                        </div>
                    </div>
                    @empty
                    <div style="padding:28px 18px;text-align:center;color:#94a3b8;font-size:12px;">No receipts
                        @if($scheduleFilter === '7days') in last 7 days @else today @endif.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endcan

    {{-- HR KPI ROW --}}

    <div class="db-section-title">HR &amp; Attendance Overview</div>
    @php
        $hrStatusCounts = collect($reportData ?? [])
            ->map(fn ($row) => strtolower((string) ($row->status ?? '')))
            ->countBy();
        $hrTodayPresent = (int) (($hrStatusCounts['present'] ?? 0) + ($hrStatusCounts['late'] ?? 0));
        $hrTodayLate = (int) ($hrStatusCounts['late'] ?? 0);
        $hrTodayAbsent = (int) ($hrStatusCounts['absent'] ?? 0);
        $hrTodayLeave = (int) ($hrStatusCounts['leave'] ?? 0);
    @endphp
    {{--
    <div class="kpi-grid" style="margin-bottom:16px;">
        <div class="kpi-card" style="border-top:3px solid #3b82f6;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#dbeafe;"><i class="fas fa-users" style="color:#2563eb;"></i></div>
                <span class="kpi-badge" style="background:#dbeafe;color:#2563eb;">Active</span>
            </div>
            <div class="kpi-num">{{ $totalEmployees }}</div>
            <div class="kpi-label">Total Employees</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:100%;background:#3b82f6;"></div></div>
        </div>
        <div class="kpi-card" style="border-top:3px solid #22c55e;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#dcfce7;"><i class="fas fa-user-check" style="color:#16a34a;"></i></div>
                <span class="kpi-badge" style="background:#dcfce7;color:#16a34a;">Today</span>
            </div>
            <div class="kpi-num" style="display:flex;align-items:baseline;gap:6px;">
                <span>{{ $hrTodayPresent }}</span>
                <span style="font-size:12px;font-weight:700;color:var(--slate-400);">(Today)</span>
            </div>
            <div class="kpi-label">Present</div>
            <div class="kpi-subtext">{{ $monthAttendanceRate }}% avg. for {{ \Carbon\Carbon::createFromDate($selectedYear, $selectedMonth, 1)->format('F') }}</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ $monthAttendanceRate }}%;background:#22c55e;"></div></div>
        </div>
        <div class="kpi-card" style="border-top:3px solid #eab308;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#fef9c3;"><i class="fas fa-clock" style="color:#ca8a04;"></i></div>
                <span class="kpi-badge" style="background:#fef9c3;color:#a16207;">Today</span>
            </div>
            <div class="kpi-num" style="display:flex;align-items:baseline;gap:6px;">
                <span>{{ $hrTodayLate }}</span>
                <span style="font-size:12px;font-weight:700;color:var(--slate-400);">(Today)</span>
            </div>
            <div class="kpi-label">Late</div>
            <div class="kpi-subtext">Included in today's present total</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ $hrTodayPresent > 0 ? min(100, round(($hrTodayLate / $hrTodayPresent) * 100)) : 0 }}%;background:#eab308;"></div></div>
        </div>
        <div class="kpi-card" style="border-top:3px solid #ef4444;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#fef2f2;"><i class="fas fa-user-times" style="color:#dc2626;"></i></div>
                <span class="kpi-badge" style="background:#fef2f2;color:#dc2626;">Today</span>
            </div>
            <div class="kpi-num" style="display:flex;align-items:baseline;gap:6px;">
                <span>{{ $hrTodayAbsent }}</span>
                <span style="font-size:12px;font-weight:700;color:var(--slate-400);">(Today)</span>
            </div>
            <div class="kpi-label">Absent</div>
            <div class="kpi-subtext">{{ $monthAbsentCount }} total this month</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ $monthAbsentCount > 0 ? 100 : 0 }}%;background:#ef4444;"></div></div>
        </div>
        <div class="kpi-card" style="border-top:3px solid #f59e0b;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#fef3c7;"><i class="fas fa-calendar-minus" style="color:#d97706;"></i></div>
                <span class="kpi-badge" style="background:#fef3c7;color:#d97706;">Today</span>
            </div>
            <div class="kpi-num" style="display:flex;align-items:baseline;gap:6px;">
                <span>{{ $hrTodayLeave }}</span>
                <span style="font-size:12px;font-weight:700;color:var(--slate-400);">(Today)</span>
            </div>
            <div class="kpi-label">On Leave</div>
            <div class="kpi-subtext">{{ $monthLeaveCount }} approved for {{ \Carbon\Carbon::createFromDate($selectedYear, $selectedMonth, 1)->format('F') }}</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ $monthLeaveCount > 0 ? 100 : 0 }}%;background:#f59e0b;"></div></div>
        </div>
    </div>
    --}}

    {{-- CRM PIPELINE & ONGOING PROJECTS --}}
    @canany(['view lead manager', 'view deal', 'view lead project', 'view all lead project'])
    @php
        $pipelineStageLabels = ['new'=>'New','contacted'=>'Contacted','qualified'=>'Qualified','proposal_sent'=>'Quoted','negotiation'=>'Negotiation'];
        $pipelineStageBadge  = ['new'=>'#dbeafe|#1d4ed8','contacted'=>'#e0e7ff|#4338ca','qualified'=>'#fef9c3|#a16207','proposal_sent'=>'#ede9fe|#6d28d9','negotiation'=>'#ffedd5|#c2410c'];
    @endphp
    <div class="db-section-title">CRM Pipeline &amp; Ongoing Projects</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;" class="crm-pipeline-grid">

        {{-- PIPELINE CARD --}}
        <div style="background:white;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);border:1px solid #e2e8f0;">
            <div style="background:linear-gradient(135deg,#1e40af,#3b82f6);padding:16px 20px;min-height: 147px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="color:#bfdbfe;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">Pipeline Projects</div>
                        <div style="color:white;font-size:30px;font-weight:800;line-height:1.1;margin-top:4px;">{{ $pipelineCount }}</div>
                        <div style="color:#bfdbfe;font-size:11px;margin-top:2px;">Active leads in CRM pipeline</div>
                    </div>
                    <div style="font-size:36px;opacity:.25;">📋</div>
                </div>
                {{-- Stage breakdown pills --}}
                @if($pipelineByStage->isNotEmpty())
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;">
                    @foreach($pipelineStageLabels as $slug => $label)
                        @if(($pipelineByStage[$slug] ?? 0) > 0)
                            @php [$bg, $txt] = explode('|', $pipelineStageBadge[$slug]); @endphp
                            <span style="background:{{ $bg }};color:{{ $txt }};font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;">
                                {{ $label }}: {{ $pipelineByStage[$slug] }}
                            </span>
                        @endif
                    @endforeach
                </div>
                @endif
            </div>
            <div style="padding:12px 16px;">
                @forelse($pipelineLeads as $pl)
                    @php $sc = $pipelineStageBadge[$pl->status] ?? '#f1f5f9|#475569'; [$sbg,$stxt] = explode('|',$sc); @endphp
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f1f5f9;gap:8px;">
                        <div style="min-width:0;flex:1;">
                            <div style="font-weight:600;font-size:13px;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $pl->name }}</div>
                            @if($pl->assignedEmployee)
                            <div style="display:inline-flex;align-items:center;gap:4px;margin-top:3px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:999px;padding:2px 8px 2px 4px;">
                                <span style="width:18px;height:18px;border-radius:50%;background:#3b82f6;color:white;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    {{ strtoupper(substr($pl->assignedEmployee->name, 0, 1)) }}
                                </span>
                                <span style="font-size:10px;font-weight:600;color:#1d4ed8;">{{ $pl->assignedEmployee->name }}</span>
                            </div>
                            @else
                            <div style="font-size:10px;color:#cbd5e1;margin-top:2px;">Unassigned</div>
                            @endif
                        </div>
                        <span style="background:{{ $sbg }};color:{{ $stxt }};font-size:10px;font-weight:700;padding:3px 9px;border-radius:999px;white-space:nowrap;flex-shrink:0;">
                            {{ $pipelineStageLabels[$pl->status] ?? ucfirst($pl->status) }}
                        </span>
                    </div>
                @empty
                    <div style="text-align:center;padding:20px 0;color:#94a3b8;font-size:12px;">কোনো active pipeline lead নেই</div>
                @endforelse
                @if($pipelineCount > 5)
                <a href="{{ route('role.lead-manager.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'status' => '']) }}"
                   style="display:block;text-align:center;margin-top:8px;font-size:12px;color:#2563eb;font-weight:600;text-decoration:none;">
                    সব {{ $pipelineCount }} টি দেখুন →
                </a>
                @endif
            </div>
        </div>

        {{-- ONGOING CARD --}}
        <div style="background:white;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);border:1px solid #e2e8f0;">
            <div style="background:linear-gradient(135deg,#0f766e,#2dd4bf);padding:16px 20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="color:#99f6e4;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">Ongoing Projects</div>
                        <div style="color:white;font-size:30px;font-weight:800;line-height:1.1;margin-top:4px;">{{ $ongoingCount }}</div>
                        <div style="color:#99f6e4;font-size:11px;margin-top:2px;">Won leads converted to projects</div>
                    </div>
                    <div style="font-size:36px;opacity:.25;">🚀</div>
                </div>
                <div style="margin-top:10px;background:rgba(255,255,255,.15);border-radius:8px;padding:8px 12px;display:flex;align-items:center;gap:6px;">
                    <span style="color:white;font-size:10px;font-weight:700;">CRM → Project conversion rate:</span>
                    @php
                        $totalWon = \App\Models\Lead::where('status','won')->count();
                        $convRate = $totalWon > 0 ? round(($ongoingCount / $totalWon) * 100) : 0;
                    @endphp
                    <span style="color:white;font-size:12px;font-weight:800;">{{ $convRate }}%</span>
                    <div style="flex:1;background:rgba(255,255,255,.25);border-radius:4px;height:5px;overflow:hidden;">
                        <div style="width:{{ $convRate }}%;background:white;height:100%;border-radius:4px;transition:.3s;"></div>
                    </div>
                </div>
            </div>
            <div style="padding:12px 16px;">
                @forelse($ongoingProjects as $op)
                    @php
                        $opMembers = $op->teamMembers();
                        $opColors  = ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#ef4444','#06b6d4'];
                    @endphp
                    <div style="padding:10px 0;border-bottom:1px solid #f1f5f9;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                            <div style="min-width:0;flex:1;">
                                <div style="font-weight:700;font-size:13px;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $op->project_name }}</div>
                                {{-- Meta tags: company, category, department --}}
                                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;">
                                    @if($op->company)
                                    <span style="display:inline-flex;align-items:center;gap:3px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:999px;padding:1px 7px;font-size:10px;color:#15803d;font-weight:600;">
                                        <svg style="width:9px;height:9px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"/></svg>
                                        {{ $op->company->name }}
                                    </span>
                                    @endif
                                    @if($op->projectCategory)
                                    <span style="display:inline-flex;align-items:center;gap:3px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:999px;padding:1px 7px;font-size:10px;color:#1d4ed8;font-weight:600;">
                                        <svg style="width:9px;height:9px;" fill="currentColor" viewBox="0 0 20 20"><path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"/></svg>
                                        {{ $op->projectCategory->name }}
                                    </span>
                                    @endif
                                    @if($op->department)
                                    <span style="display:inline-flex;align-items:center;gap:3px;background:#fdf4ff;border:1px solid #e9d5ff;border-radius:999px;padding:1px 7px;font-size:10px;color:#7c3aed;font-weight:600;">
                                        <svg style="width:9px;height:9px;" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
                                        {{ $op->department->name }}
                                    </span>
                                    @endif
                                </div>
                                {{-- Assigned team members --}}
                                @if($opMembers->isNotEmpty())
                                <div style="display:flex;align-items:center;gap:4px;margin-top:6px;">
                                    <div style="display:flex;margin-right:2px;">
                                        @foreach($opMembers->take(4) as $mi => $member)
                                        <div title="{{ $member->name }}"
                                             style="width:22px;height:22px;border-radius:50%;background:{{ $opColors[$mi % count($opColors)] }};color:white;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid white;margin-left:{{ $mi > 0 ? '-6px' : '0' }};z-index:{{ 10 - $mi }};">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </div>
                                        @endforeach
                                    </div>
                                    @if($opMembers->count() == 1)
                                    <span style="font-size:10px;font-weight:600;color:#475569;">{{ $opMembers->first()->name }}</span>
                                    @elseif($opMembers->count() <= 4)
                                    <span style="font-size:10px;color:#64748b;">{{ $opMembers->count() }} members</span>
                                    @else
                                    <span style="font-size:10px;font-weight:600;color:#64748b;">+{{ $opMembers->count() - 4 }} more</span>
                                    @endif
                                </div>
                                @endif
                            </div>
                            <a href="{{ route('role.projects.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'project' => $op->id]) }}"
                               style="background:#ccfbf1;color:#0f766e;font-size:10px;font-weight:700;padding:4px 10px;border-radius:999px;text-decoration:none;white-space:nowrap;flex-shrink:0;margin-top:2px;">
                                Ongoing →
                            </a>
                        </div>
                    </div>
                @empty
                    <div style="text-align:center;padding:20px 0;color:#94a3b8;font-size:12px;">কোনো ongoing project নেই</div>
                @endforelse
                @if($ongoingCount > 5)
                <a href="{{ route('role.projects.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                   style="display:block;text-align:center;margin-top:8px;font-size:12px;color:#0d9488;font-weight:600;text-decoration:none;">
                    সব {{ $ongoingCount }} টি দেখুন →
                </a>
                @endif
            </div>
        </div>
    </div>
    <style>
    @media (max-width: 768px) {
        .crm-pipeline-grid { grid-template-columns: 1fr !important; }
    }
    </style>
    @endcanany

    {{-- ANALYTICS CHARTS --}}
    <div class="db-section-title">Analytics</div>
    <div class="charts-grid" style="margin-bottom:24px;">
        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Weekly Task Trend</div>
                    <div class="chart-subtitle">Created vs Completed</div>
                </div>
                <span class="chart-badge">{{ $periodLabel }}</span>
            </div>
            <div style="height:160px;"><canvas id="taskWeeklyChart" style="width:100%;height:100%;"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Income vs Expense</div>
                    <div class="chart-subtitle">{{ $selectedYear }}</div>
                </div>
            </div>
            <div style="height:160px;"><canvas id="incomeExpenseChart" style="width:100%;height:100%;"></canvas></div>
        </div>
    </div>

    <div class="charts-grid" style="margin-bottom:24px;">
        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Monthly Task Trend</div>
                    <div class="chart-subtitle">{{ $selectedYear }} — Month {{ $selectedMonth }} highlighted</div>
                </div>
            </div>
            <div style="height:160px;"><canvas id="taskMonthlyChart" style="width:100%;height:100%;"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Profit / Loss Trend</div>
                    <div class="chart-subtitle">Monthly net — {{ $selectedYear }}</div>
                </div>
            </div>
            <div style="height:160px;"><canvas id="profitLossChart" style="width:100%;height:100%;"></canvas></div>
        </div>
    </div>

    {{-- ATTENDANCE TABLE + QUICK ACTIONS --}}
    <div class="db-section-title">Today's Attendance · Quick Actions</div>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:24px;">
        {{-- Attendance Table --}}
        <div class="info-card">
            <div class="chart-header" style="margin-bottom:12px;">
                <div>
                    <div class="chart-title">📋 Attendance — {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</div>
                    <div class="chart-subtitle">{{ count($reportData) }} employees</div>
                </div>
                {{-- Attendance Rate Circle --}}
                @php
                    $todayAttendanceRate = $totalEmployees > 0 ? round(($todayPresentCount / $totalEmployees) * 100, 1) : 0;

                    $plCirc = round(2 * M_PI * 38, 2);
                    $plDsh = round(($plCirc * $todayAttendanceRate) / 100, 2);
                    $plClr = $todayAttendanceRate >= 75 ? '#22c55e' : ($todayAttendanceRate >= 50 ? '#f59e0b' : '#ef4444');
                    $plTxtClr = $todayAttendanceRate >= 75 ? '#16a34a' : ($todayAttendanceRate >= 50 ? '#d97706' : '#dc2626');
                @endphp
                <div style="border-top:1px solid #f1f5f9;display:flex;align-items:center;gap:14px;">
                    <div style="position:relative;width:80px;height:80px;flex-shrink:0;">
                        <svg width="80" height="80" viewBox="0 0 92 92" style="transform:rotate(-90deg);">
                            <circle cx="46" cy="46" r="38" fill="none" stroke="#f1f5f9" stroke-width="10"/>
                            <circle cx="46" cy="46" r="38" fill="none" stroke="{{ $plClr }}" stroke-width="10" stroke-linecap="round" stroke-dasharray="{{ $plDsh }} {{ $plCirc }}"/>
                        </svg>
                        <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                            <span style="font-size:16px;font-weight:800;color:{{ $plTxtClr }};">{{ $todayAttendanceRate }}%</span>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:12px;font-weight:700;color:#334155;">Attendance Rate</div>
                        <div style="font-size:10px;color:#94a3b8;">Today · {{ \Carbon\Carbon::today()->format('d M Y') }}</div>
                        <div style="font-size:10px;color:#64748b;margin-top:4px;">{{ $todayPresentCount }} / {{ $totalEmployees }} present</div>
                    </div>
                </div>

            </div>
            <div style="overflow-x:auto;max-height:320px;overflow-y:auto;">
                <table class="att-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Shift</th>
                            <th>In</th>
                            <th>Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reportData as $att)
                            @php
                                $avatarColors = ['#4f46e5','#ec4899','#10b981','#f59e0b','#8b5cf6','#3b82f6','#ef4444','#14b8a6'];
                                $ci = abs(crc32($att->user_name)) % count($avatarColors);
                                $avatarColor = $avatarColors[$ci];
                                $statusStyle = match(strtolower($att->status)) {
                                    'present'  => 'background:#dcfce7;color:#16a34a;',
                                    'late'     => 'background:#fef3c7;color:#d97706;',
                                    'absent'   => 'background:#fef2f2;color:#dc2626;',
                                    'leave'    => 'background:#eff6ff;color:#2563eb;',
                                    'holiday'  => 'background:#f3e8ff;color:#7c3aed;',
                                    default    => 'background:#f1f5f9;color:#64748b;',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div class="att-avatar" style="background:{{ $avatarColor }};">{{ strtoupper(mb_substr($att->user_name, 0, 1)) }}</div>
                                        @if ($att->id)
                                            <button
                                                type="button"
                                                class="att-name-trigger edit-item-btn"
                                                data-item_id="{{ $att->id }}"
                                                data-user_id="{{ $att->user_id }}"
                                                data-company_id="{{ $att->company_id }}"
                                                data-shift_id="{{ $att->shift_id }}"
                                                data-date="{{ $att->date }}"
                                                data-check_in="{{ $att->check_in ? \Carbon\Carbon::parse($att->check_in)->format('H:i') : '' }}"
                                                data-check_out="{{ $att->check_out ? \Carbon\Carbon::parse($att->check_out)->format('H:i') : '' }}"
                                                data-note="{{ $att->note }}"
                                                data-status="{{ strtolower($att->status) }}"
                                                title="Click to update attendance">
                                                {{ $att->user_name }}
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                class="att-name-trigger create-from-row-btn"
                                                data-user_id="{{ $att->user_id }}"
                                                data-company_id="{{ $att->company_id }}"
                                                data-shift_id="{{ $att->shift_id }}"
                                                data-date="{{ $att->date }}"
                                                data-check_in="{{ $att->check_in ? \Carbon\Carbon::parse($att->check_in)->format('H:i') : '' }}"
                                                data-check_out="{{ $att->check_out ? \Carbon\Carbon::parse($att->check_out)->format('H:i') : '' }}"
                                                data-note="{{ $att->note === 'No record' ? '' : $att->note }}"
                                                data-status="{{ in_array(strtolower($att->status), ['present', 'absent', 'leave', 'holiday']) ? strtolower($att->status) : 'present' }}"
                                                title="Click to add attendance">
                                                {{ $att->user_name }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td style="font-size:11px;color:#64748b;">{{ $att->shift_name }}</td>
                                <td style="font-size:12px;font-weight:600;">{{ $att->check_in ? \Carbon\Carbon::parse($att->check_in)->format('h:i A') : '--' }}</td>
                                <td style="font-size:12px;font-weight:600;">{{ $att->check_out ? \Carbon\Carbon::parse($att->check_out)->format('h:i A') : '--' }}</td>
                                <td><span class="db-status-badge" style="{{ $statusStyle }}">{{ $att->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:20px;">No attendance data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Subscription / Document Renewal Alert --}}
        @include("panel.dashboard.partials.renewal-center")

    </div>

    {{-- TASK STATS --}}
    {{-- @can('view all task')
    <div class="db-section-title">Task Overview</div>
    <div class="kpi-grid" style="margin-bottom:24px;">
        <div class="kpi-card" style="border-top:3px solid #4f46e5;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#ede9fe;">📋</div>
                <span class="kpi-badge" style="background:#ede9fe;color:#4f46e5;">Total</span>
            </div>
            <div class="kpi-num">{{ $task_total }}</div>
            <div class="kpi-label">Total Tasks ({{ $periodLabel }})</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:100%;background:#4f46e5;"></div></div>
        </div>
        <div class="kpi-card" style="border-top:3px solid #10b981;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#d1fae5;">✅</div>
                @php $completedPct = $task_total > 0 ? round(($task_completed / $task_total) * 100) : 0; @endphp
                <span class="kpi-badge" style="background:#d1fae5;color:#059669;">{{ $completedPct }}%</span>
            </div>
            <div class="kpi-num">{{ $task_completed }}</div>
            <div class="kpi-label">Completed Tasks</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ $completedPct }}%;background:#10b981;"></div></div>
        </div>
        <div class="kpi-card" style="border-top:3px solid #f97316;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#ffedd5;">📅</div>
                <span class="kpi-badge" style="background:#ffedd5;color:#ea580c;">Due</span>
            </div>
            <div class="kpi-num">{{ $task_due_today }}</div>
            <div class="kpi-label">Due This Month</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ $task_total > 0 ? min(100,round(($task_due_today/$task_total)*100)) : 0 }}%;background:#f97316;"></div></div>
        </div>
        <div class="kpi-card" style="border-top:3px solid #ef4444;">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:#fef2f2;">⚠️</div>
                <span class="kpi-badge" style="background:#fef2f2;color:#dc2626;">Overdue</span>
            </div>
            <div class="kpi-num">{{ $task_overdue }}</div>
            <div class="kpi-label">Overdue Tasks</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ $task_total > 0 ? min(100,round(($task_overdue/$task_total)*100)) : 0 }}%;background:#ef4444;"></div></div>
        </div>
    </div>
    @endcan --}}

    {{-- ALL MODULE OVERVIEW --}}
    <div class="db-section-title">All Module Overview</div>
    <div class="bottom-stats">
        <div class="bs-card">
            <div class="bs-icon" style="background:#dbeafe;">👥</div>
            <div class="bs-num">{{ $totalEmployees }}</div>
            <div class="bs-label">Employees</div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:#dcfce7;">✅</div>
            <div class="bs-num">{{ $hrTodayPresent }}</div>
            <div class="bs-label">Present</div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:#fef9c3;"><i class="fas fa-clock" style="color:#ca8a04;"></i></div>
            <div class="bs-num">{{ $hrTodayLate }}</div>
            <div class="bs-label">Late</div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:#fef2f2;">❌</div>
            <div class="bs-num">{{ $hrTodayAbsent }}</div>
            <div class="bs-label">Absent</div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:#fef3c7;">🏖️</div>
            <div class="bs-num">{{ $hrTodayLeave }}</div>
            <div class="bs-label">On Leave</div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:#d1fae5;">💹</div>
            <div class="bs-num" style="font-size:14px;">৳{{ number_format($total_income/1000,1) }}K</div>
            <div class="bs-label">Income</div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:#fef2f2;">💸</div>
            <div class="bs-num" style="font-size:14px;">৳{{ number_format($total_expense/1000,1) }}K</div>
            <div class="bs-label">Expense</div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:#ccfbf1;">🏦</div>
            <div class="bs-num" style="font-size:14px;">৳{{ number_format($total_bank_balance/1000,1) }}K</div>
            <div class="bs-label">Bank Balance</div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:#ede9fe;">📋</div>
            <div class="bs-num">{{ $task_total }}</div>
            <div class="bs-label">Tasks</div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:#f0fdf4;">✅</div>
            <div class="bs-num">{{ $task_completed }}</div>
            <div class="bs-label">Tasks Done</div>
        </div>
        <div class="bs-card">
            <div class="bs-icon" style="background:#fef3c7;">📊</div>
            <div class="bs-num">{{ $todayAttendanceRate }}%</div>
            <div class="bs-label">Att. Rate</div>
        </div>
    </div>

    {{-- OFFICE ISSUES — ACTION REQUIRED (Admin) --}}
    @php
        $issueRoleSlug = \Illuminate\Support\Str::slug(auth()->user()->getRoleNames()->first());
        $issueListUrl  = route('role.office-todos.index', ['role' => $issueRoleSlug]);
    @endphp
    <div style="margin-bottom:28px;">
        {{-- Section Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-times-circle" style="color:#dc2626;font-size:17px;"></i>
                </div>
                <span style="font-size:17px;font-weight:800;color:#0f172a;letter-spacing:-.2px;">Office ToDos — Action Required</span>
            </div>
            <a href="{{ $issueListUrl }}" style="font-size:12px;font-weight:700;color:#4f46e5;text-decoration:none;">
                View All Issues <i class="fas fa-arrow-right" style="font-size:11px;margin-left:4px;"></i>
            </a>
        </div>

        {{-- 4 Stat Cards --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px;">
            {{-- Urgent --}}
            <div style="background:linear-gradient(135deg,#ef4444,#dc2626);border-radius:18px;padding:22px 20px;color:white;display:flex;align-items:center;gap:16px;cursor:pointer;box-shadow:0 4px 18px rgba(239,68,68,.25);" onclick="location.href='{{ $issueListUrl }}?priority=high&status=pending'">
                <div style="width:52px;height:52px;border-radius:15px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-bell" style="font-size:22px;"></i>
                </div>
                <div>
                    <div style="font-size:36px;font-weight:900;line-height:1.1;">{{ $todoStats['urgent'] ?? 0 }}</div>
                    <div style="font-size:13px;font-weight:700;margin-top:2px;">Urgent</div>
                    <div style="font-size:10px;opacity:.75;margin-top:1px;">24h SLA</div>
                </div>
            </div>

            {{-- High Priority --}}
            <div style="background:linear-gradient(135deg,#f97316,#ea580c);border-radius:18px;padding:22px 20px;color:white;display:flex;align-items:center;gap:16px;cursor:pointer;box-shadow:0 4px 18px rgba(249,115,22,.25);" onclick="location.href='{{ $issueListUrl }}?priority=high'">
                <div style="width:52px;height:52px;border-radius:15px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-exclamation-triangle" style="font-size:22px;"></i>
                </div>
                <div>
                    <div style="font-size:36px;font-weight:900;line-height:1.1;">{{ $todoStats['high'] ?? 0 }}</div>
                    <div style="font-size:13px;font-weight:700;margin-top:2px;">High Priority</div>
                </div>
            </div>

            {{-- Medium --}}
            <div style="background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:18px;padding:22px 20px;color:white;display:flex;align-items:center;gap:16px;cursor:pointer;box-shadow:0 4px 18px rgba(245,158,11,.25);" onclick="location.href='{{ $issueListUrl }}?priority=medium'">
                <div style="width:52px;height:52px;border-radius:15px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-clipboard-list" style="font-size:22px;"></i>
                </div>
                <div>
                    <div style="font-size:36px;font-weight:900;line-height:1.1;">{{ $todoStats['medium_week'] ?? 0 }}</div>
                    <div style="font-size:13px;font-weight:700;margin-top:2px;">Medium</div>
                    <div style="font-size:10px;opacity:.75;margin-top:1px;">This Week</div>
                </div>
            </div>

            {{-- Resolved Today --}}
            <div style="background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:18px;padding:22px 20px;color:white;display:flex;align-items:center;gap:16px;cursor:pointer;box-shadow:0 4px 18px rgba(34,197,94,.25);" onclick="location.href='{{ $issueListUrl }}?status=completed'">
                <div style="width:52px;height:52px;border-radius:15px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-check-circle" style="font-size:22px;"></i>
                </div>
                <div>
                    <div style="font-size:36px;font-weight:900;line-height:1.1;">{{ $todoStats['resolved_today'] ?? 0 }}</div>
                    <div style="font-size:13px;font-weight:700;margin-top:2px;">Resolved Today</div>
                </div>
            </div>
        </div>

        {{-- Active Issues List --}}
        <div style="background:white;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.05);overflow:hidden;">
            <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-tools" style="color:#64748b;font-size:13px;"></i>
                    <span style="font-size:13px;font-weight:700;color:#374151;">Active Office ToDO</span>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button onclick="openReportTodoModal()" style="display:inline-flex;align-items:center;gap:6px;background:#4f46e5;color:white;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:600;border:none;cursor:pointer;">
                        <i class="fas fa-plus"></i> Report ToDo
                    </button>
                    <a href="{{ $issueListUrl }}" style="display:inline-flex;align-items:center;gap:6px;background:#f1f5f9;color:#374151;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid #e2e8f0;">
                        <i class="fas fa-list"></i> View All
                    </a>
                </div>
            </div>

            <div id="admin-todo-active-list" style="max-height:430px;overflow-y:auto;">
            @forelse(($todoActiveIssues ?? collect()) as $issue)
            @php
                $iDue     = $issue->due_date ? \Carbon\Carbon::parse($issue->due_date) : null;
                $iOverdue = $iDue && $iDue->lt(\Carbon\Carbon::today());
                $iUrgent  = $issue->priority === 'high' && ($iOverdue || ($iDue && $iDue->isToday()));
                $iBadge   = $iUrgent ? 'URGENT' : strtoupper($issue->priority ?? 'LOW');
                $iLeftClr = $iUrgent ? '#dc2626' : match($issue->priority) {
                    'high'   => '#f97316',
                    'medium' => '#f59e0b',
                    default  => '#22c55e',
                };
                $iBadgeSt = $iUrgent
                    ? 'background:#fef2f2;color:#dc2626;border:1px solid #fecaca;'
                    : match($issue->priority) {
                        'high'   => 'background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;',
                        'medium' => 'background:#fefce8;color:#ca8a04;border:1px solid #fde68a;',
                        default  => 'background:#f0fdf4;color:#16a34a;border:1px solid #86efac;',
                    };
                $iIconCls = $iUrgent ? 'fas fa-fire' : match($issue->priority) {
                    'high'   => 'fas fa-exclamation-circle',
                    'medium' => 'fas fa-clipboard',
                    default  => 'fas fa-check',
                };
                $iIconBg  = $iUrgent ? '#fee2e2' : match($issue->priority) {
                    'high'   => '#fff7ed',
                    'medium' => '#fefce8',
                    default  => '#f0fdf4',
                };
                $iTimeAgo = $issue->created_at ? $issue->created_at->diffForHumans() : '';
                $iDept    = optional($issue->department)->name;
                $iCreator = optional($issue->creator)->name ?? 'Unknown';
                $iStStyle = match($issue->status) {
                    'completed'   => 'background:#dcfce7;color:#16a34a;',
                    'in_progress' => 'background:#dbeafe;color:#1d4ed8;',
                    default       => 'background:#f3f4f6;color:#6b7280;',
                };
                $iStLabel = match($issue->status) {
                    'in_progress' => 'In Progress',
                    default       => ucfirst($issue->status),
                };
            @endphp
            <div style="padding:14px 20px;border-bottom:1px solid #f8fafc;border-left:3px solid {{ $iLeftClr }};">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                    <div style="display:flex;align-items:flex-start;gap:12px;min-width:0;flex:1;">
                        <div style="width:38px;height:38px;border-radius:11px;background:{{ $iIconBg }};display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                            <i class="{{ $iIconCls }}" style="color:{{ $iLeftClr }};font-size:15px;"></i>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:13px;font-weight:700;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $issue->title }}</div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                                Reported by: <strong style="color:#64748b;">{{ $iCreator }}</strong>
                                @if($iTimeAgo) &middot; {{ $iTimeAgo }} @endif
                                @if($iDept) &middot; {{ $iDept }} @endif
                            </div>
                            @if(!$issue->is_self && $issue->assignees->isNotEmpty())
                            <div style="display:flex;align-items:center;gap:2px;margin-top:7px;flex-wrap:wrap;">
                                <span style="font-size:10px;color:#94a3b8;margin-right:3px;">Assigned:</span>
                                @foreach($issue->assignees->take(5) as $aUser)
                                <img src="{{ $aUser->image ? asset($aUser->image) : 'https://ui-avatars.com/api/?name='.urlencode($aUser->name).'&size=22&background=4f46e5&color=fff' }}"
                                     style="width:22px;height:22px;border-radius:50%;border:1.5px solid white;object-fit:cover;margin-right:-4px;"
                                     title="{{ $aUser->name }}">
                                @endforeach
                                @if($issue->assignees->count() > 5)
                                <span style="width:22px;height:22px;border-radius:50%;background:#e5e7eb;color:#374151;font-size:9px;display:inline-flex;align-items:center;justify-content:center;border:1.5px solid white;font-weight:700;margin-right:4px;">+{{ $issue->assignees->count() - 5 }}</span>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0;">
                        <span style="font-size:10px;font-weight:800;padding:3px 10px;border-radius:999px;white-space:nowrap;letter-spacing:.4px;{{ $iBadgeSt }}">{{ $iBadge }}</span>
                        <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;white-space:nowrap;{{ $iStStyle }}">{{ $iStLabel }}</span>
                        <div style="display:flex;gap:5px;margin-top:2px;">
                            <button onclick="openDashStatusModal({{ $issue->id }}, '{{ $issue->status }}')"
                                style="font-size:11px;font-weight:600;color:#4f46e5;background:#eef2ff;border:none;border-radius:6px;padding:3px 9px;cursor:pointer;">
                                <i class="fas fa-sync-alt"></i> Status
                            </button>
                            <button onclick="openDashDetailsModal({{ $issue->id }})"
                                style="font-size:11px;font-weight:600;color:#374151;background:#f1f5f9;border:none;border-radius:6px;padding:3px 9px;cursor:pointer;">
                                <i class="fas fa-eye"></i> Details
                            </button>
                            <button onclick="openDashEditModal({{ $issue->id }})"
                                style="font-size:11px;font-weight:600;color:#047857;background:#d1fae5;border:none;border-radius:6px;padding:3px 9px;cursor:pointer;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div style="padding:36px 20px;text-align:center;color:#94a3b8;font-size:13px;">
                <i class="fas fa-check-double" style="font-size:28px;color:#22c55e;display:block;margin-bottom:10px;"></i>
                All caught up! No active issues.
            </div>
            @endforelse
            </div>{{-- #admin-todo-active-list --}}
        </div>
    </div>

@endif

     {{-- Employee Profile Card --}}
@if(Auth::check() && auth()->user()->hasRole('employee'))
    <div class="bg-white rounded-2xl shadow-md border border-gray-100 mb-6">
        <div class="px-5 py-5">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                <div class="flex items-start gap-4 min-w-0">
                    @php
                        $myAvatar = null;
                        $imgVal = auth()->user()->image ?? '';
                        if ($imgVal) {
                            if (\Illuminate\Support\Str::startsWith($imgVal, ['http://', 'https://'])) {
                                $myAvatar = $imgVal;
                            } elseif (file_exists(public_path('image/' . $imgVal))) {
                                $myAvatar = asset('image/' . $imgVal);
                            } else {
                                $myAvatar = asset($imgVal);
                            }
                        }
                    @endphp

                    @if($myAvatar)
                        <img src="{{ $myAvatar }}" alt="{{ auth()->user()->name }}"
                            class="w-16 h-16 rounded-2xl shadow object-cover border border-gray-100">
                    @else
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-2xl font-bold shadow">
                            {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                        </div>
                    @endif

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            @if(auth()->user()->employee_id_no)
                                <span class="text-[11px] bg-indigo-50 text-indigo-700 font-semibold px-3 py-1 rounded-full border border-indigo-200">
                                    ID: {{ auth()->user()->employee_id_no }}
                                </span>
                            @endif
                            <span class="text-[11px] bg-green-100 text-green-700 font-semibold px-3 py-1 rounded-full">
                                <span class="inline-block w-1.5 h-1.5 rounded-full bg-green-500 mr-1 align-middle"></span>
                                Active
                            </span>
                        </div>

                        <h2 class="text-2xl font-bold text-gray-800 leading-tight">{{ auth()->user()->name }}</h2>
                        <p class="text-sm text-indigo-600 font-medium mt-1">
                            {{ $profile?->designation?->name ?? '' }}
                            @if($profile?->designation?->name && $profile?->department?->name)
                                <span class="text-gray-300 mx-1">&bull;</span>
                            @endif
                            {{ $profile?->department?->name ?? '' }}
                        </p>

                        <div class="flex flex-wrap gap-2 mt-3">
                            @if($profile?->employment_type)
                                <span class="inline-flex items-center gap-1.5 text-xs bg-blue-50 text-blue-700 rounded-full px-3 py-1.5 font-medium">
                                    <i class="fas fa-briefcase text-[10px]"></i>
                                    {{ ucwords(str_replace('_', ' ', $profile->employment_type)) }}
                                </span>
                            @endif
                            @if($profile?->joining_date)
                                <span class="inline-flex items-center gap-1.5 text-xs bg-emerald-50 text-emerald-700 rounded-full px-3 py-1.5 font-medium">
                                    <i class="fas fa-calendar-check text-[10px]"></i>
                                    Joined {{ \Carbon\Carbon::parse($profile->joining_date)->format('M j, Y') }}
                                </span>
                            @endif
                            @if(auth()->user()->email)
                                <span class="inline-flex items-center gap-1.5 text-xs bg-gray-100 text-gray-600 rounded-full px-3 py-1.5 font-medium">
                                    <i class="fas fa-envelope text-[10px]"></i>
                                    {{ auth()->user()->email }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                @if($employeeStats)
                    <div class="grid grid-cols-3 gap-3 lg:min-w-[340px] w-full lg:w-auto">
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-center">
                            <p class="text-2xl font-extrabold text-indigo-600">{{ $employeeStats->attendance_this_month }}</p>
                            <p class="text-xs text-gray-500 mt-1">Present this month</p>
                        </div>
                        <div class="rounded-xl border border-purple-100 bg-purple-50 px-4 py-3 text-center">
                            <p class="text-2xl font-extrabold text-purple-600">{{ $employeeStats->tasks_count }}</p>
                            <p class="text-xs text-gray-500 mt-1">Tasks assigned</p>
                        </div>
                        <div class="rounded-xl border border-pink-100 bg-pink-50 px-4 py-3 text-center">
                            <p class="text-2xl font-extrabold text-pink-500">{{ $employeeStats->leaves_this_year }}</p>
                            <p class="text-xs text-gray-500 mt-1">Leaves this year</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>


    @php
        $employeeRoleSlug = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
        $employeeMyTasksUrl = !empty($employeeLatestTaskBoardId)
            ? route('role.tasks.board', ['role' => $employeeRoleSlug, 'board' => $employeeLatestTaskBoardId])
            : route('role.tasks.index', ['role' => $employeeRoleSlug]);
        $attendanceBadgeClass = match (strtolower((string) ($employeeTodayAttendance->status ?? ''))) {
            'present' => 'bg-green-100 text-green-700',
            'late' => 'bg-yellow-100 text-yellow-700',
            'on leave' => 'bg-blue-100 text-blue-700',
            'holiday' => 'bg-purple-100 text-purple-700',
            default => 'bg-rose-100 text-rose-700',
        };
    @endphp

    {{-- NOTICE SLIDER --}}
    @if ($notices->isNotEmpty())
    <div class="ns-wrap" id="noticeSlider">
        <div class="ns-track" id="nsTrack">
            @foreach ($notices as $i => $notice)
                @php
                    $colors = explode(',', $notice->card_color ?? '#f97316,#f59e0b');
                    $from = trim($colors[0] ?? '#f97316');
                    $to   = trim($colors[1] ?? '#f59e0b');
                    $textColor = $notice->text_color ?? '#ffffff';
                    $icon = $notice->icon ?? '📢';
                @endphp
                <div class="ns-slide" style="background:linear-gradient(135deg,{{ $from }},{{ $to }});color:{{ $textColor }};">
                    <div class="ns-slide-icon" style="color:{{ $textColor }};font-size:22px;"><i class="{{ $icon }}"></i></div>
                    <div class="ns-slide-body">
                        <div class="ns-reminder-badge" style="color:{{ $textColor }};">
                            <i class="{{ $notice->badge_icon ?? 'fas fa-bell' }}"></i>
                            {{ strtoupper($notice->badge_label ?? 'REMINDER') }}
                        </div>
                        <div class="ns-slide-title" style="color:{{ $textColor }};">{{ $notice->title }}</div>
                        <div class="ns-slide-desc" style="color:{{ $textColor }};">{{ $notice->description }}</div>
                    </div>
                    @if($notice->slide_image)
                    <div class="ns-slide-img">
                        <img src="{{ asset('storage/' . $notice->slide_image) }}" alt="">
                    </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if($notices->count() > 1)
        <button class="ns-arrow ns-prev" id="nsPrev"><i class="fas fa-chevron-left"></i></button>
        <button class="ns-arrow ns-next" id="nsNext"><i class="fas fa-chevron-right"></i></button>
        <div class="ns-dots" id="nsDots">
            @foreach($notices as $i => $n)
            <div class="ns-dot {{ $i === 0 ? 'active' : '' }}" data-index="{{ $i }}"></div>
            @endforeach
        </div>
        @endif
    </div>
    <script>
    (function(){
        var track = document.getElementById('nsTrack');
        var dots  = document.querySelectorAll('#nsDots .ns-dot');
        var total = {{ $notices->count() }};
        var cur   = 0;
        var timer;

        function goTo(n){
            cur = (n + total) % total;
            track.style.transform = 'translateX(-' + (cur * 100) + '%)';
            dots.forEach(function(d,i){ d.classList.toggle('active', i===cur); });
        }
        function next(){ goTo(cur + 1); }
        function prev(){ goTo(cur - 1); }
        function startTimer(){ timer = setInterval(next, 5000); }
        function stopTimer(){ clearInterval(timer); }

        var pBtn = document.getElementById('nsPrev');
        var nBtn = document.getElementById('nsNext');
        if(pBtn) pBtn.addEventListener('click', function(){ stopTimer(); prev(); startTimer(); });
        if(nBtn) nBtn.addEventListener('click', function(){ stopTimer(); next(); startTimer(); });
        dots.forEach(function(d){ d.addEventListener('click', function(){ stopTimer(); goTo(+this.dataset.index); startTimer(); }); });

        var wrap = document.getElementById('noticeSlider');
        wrap.addEventListener('mouseenter', stopTimer);
        wrap.addEventListener('mouseleave', startTimer);

        if(total > 1) startTimer();
    })();
    </script>
    @endif

    {{-- TODO SLIDER (Assigned Todos) --}}
    @if(($todoActiveIssues ?? collect())->isNotEmpty())
    @php
        $todoSliderRoleSlug = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
        $todoSliderListUrl  = route('role.office-todos.index', ['role' => $todoSliderRoleSlug]);
    @endphp
    <div class="ts-wrap" id="todoSlider">
        <div class="ts-track" id="tsTrack">
            @foreach($todoActiveIssues as $tsi => $tsTodo)
            @php
                $tsMyStatus  = optional($tsTodo->assignees->first())->pivot->status ?? $tsTodo->status;
                $tsDue       = $tsTodo->due_date ? \Carbon\Carbon::parse($tsTodo->due_date) : null;
                $tsOverdue   = $tsDue && $tsDue->lt(\Carbon\Carbon::today());
                $tsUrgent    = $tsTodo->priority === 'high' && ($tsOverdue || ($tsDue && $tsDue->isToday()));
                [$tsFrom, $tsTo] = match(true) {
                    $tsUrgent                     => ['#dc2626', '#ef4444'],
                    $tsTodo->priority === 'high'  => ['#ea580c', '#f97316'],
                    $tsTodo->priority === 'medium' => ['#d97706', '#f59e0b'],
                    default                        => ['#059669', '#10b981'],
                };
                $tsPriorityLabel = $tsUrgent ? 'URGENT' : strtoupper($tsTodo->priority ?? 'LOW');
                $tsPriorityIcon  = $tsUrgent ? 'fas fa-fire' : match($tsTodo->priority) {
                    'high'   => 'fas fa-exclamation-circle',
                    'medium' => 'fas fa-clipboard-list',
                    default  => 'fas fa-check-circle',
                };
                $tsStatusLabel = match($tsMyStatus) {
                    'in_progress' => 'In Progress',
                    'completed'   => 'Completed',
                    default       => 'Pending',
                };
                $tsDueLabel = $tsDue
                    ? ($tsOverdue ? 'Overdue: ' . $tsDue->format('d M Y') : 'Due: ' . $tsDue->format('d M Y'))
                    : 'No due date';
                $tsCreator  = optional($tsTodo->creator)->name ?? 'Admin';
                $tsDept     = optional($tsTodo->department)->name;
            @endphp
            <div class="ts-slide" style="background:linear-gradient(135deg,{{ $tsFrom }},{{ $tsTo }});color:white;">
                <div class="ts-icon">
                    <i class="{{ $tsPriorityIcon }}"></i>
                </div>
                <div class="ts-body">
                    <div class="ts-badge">
                        <i class="fas fa-tasks" style="font-size:9px;"></i>
                        MY TODO &nbsp;&middot;&nbsp; {{ $tsPriorityLabel }}
                    </div>
                    <div class="ts-title">{{ $tsTodo->title }}</div>
                    <div class="ts-meta">
                        Assigned by: <strong>{{ $tsCreator }}</strong>
                        @if($tsDept) &nbsp;&middot;&nbsp; {{ $tsDept }} @endif
                        &nbsp;&middot;&nbsp;
                        <span style="{{ $tsOverdue ? 'font-weight:800;' : '' }}">{{ $tsDueLabel }}</span>
                    </div>
                    <a href="{{ $todoSliderListUrl }}" class="ts-status">
                        <i class="fas {{ $tsMyStatus === 'completed' ? 'fa-check-double' : ($tsMyStatus === 'in_progress' ? 'fa-spinner' : 'fa-clock') }}"></i>
                        {{ $tsStatusLabel }} &nbsp;&rarr;&nbsp; Update Status
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        @if($todoActiveIssues->count() > 1)
        <button class="ts-arrow ts-prev" id="tsPrev"><i class="fas fa-chevron-left"></i></button>
        <button class="ts-arrow ts-next" id="tsNext"><i class="fas fa-chevron-right"></i></button>
        <div class="ts-dots" id="tsDots">
            @foreach($todoActiveIssues as $tsi => $n)
            <div class="ts-dot {{ $tsi === 0 ? 'active' : '' }}" data-index="{{ $tsi }}"></div>
            @endforeach
        </div>
        @endif
    </div>
    <script>
    (function(){
        var tsTrack = document.getElementById('tsTrack');
        var tsDots  = document.querySelectorAll('#tsDots .ts-dot');
        var tsTotal = {{ $todoActiveIssues->count() }};
        var tsCur   = 0;
        var tsTimer;
        function tsGoTo(n){ tsCur = (n+tsTotal)%tsTotal; tsTrack.style.transform='translateX(-'+(tsCur*100)+'%)'; tsDots.forEach(function(d,i){ d.classList.toggle('active',i===tsCur); }); }
        function tsNext(){ tsGoTo(tsCur+1); }
        function tsPrev(){ tsGoTo(tsCur-1); }
        function tsStart(){ tsTimer = setInterval(tsNext, 4000); }
        function tsStop(){ clearInterval(tsTimer); }
        var tsP = document.getElementById('tsPrev');
        var tsN = document.getElementById('tsNext');
        if(tsP) tsP.addEventListener('click', function(){ tsStop(); tsPrev(); tsStart(); });
        if(tsN) tsN.addEventListener('click', function(){ tsStop(); tsNext(); tsStart(); });
        tsDots.forEach(function(d){ d.addEventListener('click', function(){ tsStop(); tsGoTo(+this.dataset.index); tsStart(); }); });
        var tsWrap = document.getElementById('todoSlider');
        tsWrap.addEventListener('mouseenter', tsStop);
        tsWrap.addEventListener('mouseleave', tsStart);
        if(tsTotal > 1) tsStart();
    })();
    </script>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-900">Today Attendance Status</h3>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold {{ $attendanceBadgeClass }}">
                    {{ $employeeTodayAttendance->status ?? 'Unknown' }}
                </span>
            </div>
            <div class="p-5">
                <p class="text-sm text-gray-500 mb-4">{{ $employeeTodayAttendance->note ?? 'No status available.' }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3">
                        <p class="text-xs text-gray-400 mb-1">Check In</p>
                        <p class="text-sm font-semibold text-gray-800">
                            {{ !empty($employeeTodayAttendance?->check_in) ? \Carbon\Carbon::parse($employeeTodayAttendance->check_in)->format('h:i A') : '--:--' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3">
                        <p class="text-xs text-gray-400 mb-1">Check Out</p>
                        <p class="text-sm font-semibold text-gray-800">
                            {{ !empty($employeeTodayAttendance?->check_out) ? \Carbon\Carbon::parse($employeeTodayAttendance->check_out)->format('h:i A') : '--:--' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3">
                        <p class="text-xs text-gray-400 mb-1">Shift</p>
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $employeeTodayAttendance->shift_name ?? 'Not set' }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3">
                        <p class="text-xs text-gray-400 mb-1">Late</p>
                        <p class="text-sm font-semibold text-gray-800">{{ (int) ($employeeTodayAttendance->late_minutes ?? 0) }} min</p>
                    </div>
                </div>
            </div>



        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">Quick Actions</h3>
            </div>
            <div class="p-5 grid grid-cols-2 gap-3">
                <a href="{{ route('role.leaves.index', ['role' => $employeeRoleSlug]) }}"
                    class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-4 hover:bg-blue-100 transition">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center">
                            <i class="fas fa-calendar-plus text-sm"></i>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Apply Leave</p>
                            <p class="text-xs text-gray-500">Manage leave requests</p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('role.attendances.index', ['role' => $employeeRoleSlug]) }}"
                    class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-4 hover:bg-emerald-100 transition">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center">
                            <i class="fas fa-fingerprint text-sm"></i>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Attendance</p>
                            <p class="text-xs text-gray-500">View attendance log</p>
                        </div>
                    </div>
                </a>
                <a href="{{ $employeeMyTasksUrl }}"
                    class="rounded-xl border border-purple-100 bg-purple-50 px-4 py-4 hover:bg-purple-100 transition">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-purple-600 text-white flex items-center justify-center">
                            <i class="fas fa-list-check text-sm"></i>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">My Tasks</p>
                            <p class="text-xs text-gray-500">Open assigned boards</p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('role.support-tickets.index', ['role' => $employeeRoleSlug]) }}"
                    class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-4 hover:bg-amber-100 transition">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center">
                            <i class="fas fa-headset text-sm"></i>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Support Ticket</p>
                            <p class="text-xs text-gray-500">Create or track tickets</p>
                        </div>
                    </div>
                </a>
            </div>



        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">Upcoming Leave / Holiday</h3>

            </div>
            <div class="p-5 space-y-4">
                <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-4">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <p class="text-sm font-semibold text-gray-800">Upcoming Leave</p>
                        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-blue-100 text-blue-700">Approved</span>
                    </div>
                    @if($employeeUpcomingLeave)
                        <p class="text-sm text-gray-700">
                            {{ $employeeUpcomingLeave->leave_type->name ?? 'Leave' }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ \Carbon\Carbon::parse($employeeUpcomingLeave->start_date)->format('d M Y') }}
                            to
                            {{ \Carbon\Carbon::parse($employeeUpcomingLeave->end_date)->format('d M Y') }}
                        </p>
                    @else
                        <p class="text-sm text-gray-500">No upcoming approved leave found.</p>
                    @endif
                </div>

                <div class="rounded-xl border border-purple-100 bg-purple-50 px-4 py-4">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <p class="text-sm font-semibold text-gray-800">Next Holiday</p>
                        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-purple-100 text-purple-700">Company</span>
                    </div>
                    @if($employeeUpcomingHoliday)
                        <p class="text-sm text-gray-700">{{ $employeeUpcomingHoliday->name }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ \Carbon\Carbon::parse($employeeUpcomingHoliday->start_date)->format('d M Y') }}
                            to
                            {{ \Carbon\Carbon::parse($employeeUpcomingHoliday->end_date)->format('d M Y') }}
                        </p>
                    @else
                        <p class="text-sm text-gray-500">No upcoming holiday scheduled.</p>
                    @endif
                </div>
            </div>



        </div>
    </div>

    {{-- OFFICE ISSUES — ACTION REQUIRED (Employee) --}}
    @php
        $empIssueRoleSlug = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
        $empIssueListUrl  = route('role.office-todos.index', ['role' => $empIssueRoleSlug]);
    @endphp
    <div class="mb-6">
        {{-- Section Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-times-circle" style="color:#dc2626;font-size:17px;"></i>
                </div>
                <span style="font-size:17px;font-weight:800;color:#0f172a;letter-spacing:-.2px;">Office Issues — Action Required</span>
            </div>
            <a href="{{ $empIssueListUrl }}" style="font-size:12px;font-weight:700;color:#4f46e5;text-decoration:none;">
                View All Issues <i class="fas fa-arrow-right" style="font-size:11px;margin-left:4px;"></i>
            </a>
        </div>

        {{-- 4 Stat Cards --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px;">
            {{-- Urgent --}}
            <div style="background:linear-gradient(135deg,#ef4444,#dc2626);border-radius:18px;padding:22px 20px;color:white;display:flex;align-items:center;gap:16px;cursor:pointer;box-shadow:0 4px 18px rgba(239,68,68,.25);" onclick="location.href='{{ $empIssueListUrl }}?priority=high&status=pending'">
                <div style="width:52px;height:52px;border-radius:15px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-bell" style="font-size:22px;"></i>
                </div>
                <div>
                    <div style="font-size:36px;font-weight:900;line-height:1.1;">{{ $todoStats['urgent'] ?? 0 }}</div>
                    <div style="font-size:13px;font-weight:700;margin-top:2px;">Urgent</div>
                    <div style="font-size:10px;opacity:.75;margin-top:1px;">24h SLA</div>
                </div>
            </div>

            {{-- High Priority --}}
            <div style="background:linear-gradient(135deg,#f97316,#ea580c);border-radius:18px;padding:22px 20px;color:white;display:flex;align-items:center;gap:16px;cursor:pointer;box-shadow:0 4px 18px rgba(249,115,22,.25);" onclick="location.href='{{ $empIssueListUrl }}?priority=high'">
                <div style="width:52px;height:52px;border-radius:15px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-exclamation-triangle" style="font-size:22px;"></i>
                </div>
                <div>
                    <div style="font-size:36px;font-weight:900;line-height:1.1;">{{ $todoStats['high'] ?? 0 }}</div>
                    <div style="font-size:13px;font-weight:700;margin-top:2px;">High Priority</div>
                </div>
            </div>

            {{-- Medium --}}
            <div style="background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:18px;padding:22px 20px;color:white;display:flex;align-items:center;gap:16px;cursor:pointer;box-shadow:0 4px 18px rgba(245,158,11,.25);" onclick="location.href='{{ $empIssueListUrl }}?priority=medium'">
                <div style="width:52px;height:52px;border-radius:15px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-clipboard-list" style="font-size:22px;"></i>
                </div>
                <div>
                    <div style="font-size:36px;font-weight:900;line-height:1.1;">{{ $todoStats['medium_week'] ?? 0 }}</div>
                    <div style="font-size:13px;font-weight:700;margin-top:2px;">Medium</div>
                    <div style="font-size:10px;opacity:.75;margin-top:1px;">This Week</div>
                </div>
            </div>

            {{-- Resolved Today --}}
            <div style="background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:18px;padding:22px 20px;color:white;display:flex;align-items:center;gap:16px;cursor:pointer;box-shadow:0 4px 18px rgba(34,197,94,.25);" onclick="location.href='{{ $empIssueListUrl }}?status=completed'">
                <div style="width:52px;height:52px;border-radius:15px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-check-circle" style="font-size:22px;"></i>
                </div>
                <div>
                    <div style="font-size:36px;font-weight:900;line-height:1.1;">{{ $todoStats['resolved_today'] ?? 0 }}</div>
                    <div style="font-size:13px;font-weight:700;margin-top:2px;">Resolved Today</div>
                </div>
            </div>
        </div>

        {{-- Active Issues List --}}
        <div style="background:white;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.05);overflow:hidden;">
            <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-tools" style="color:#64748b;font-size:13px;"></i>
                    <span style="font-size:13px;font-weight:700;color:#374151;">Active Office Issues</span>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button onclick="openReportTodoModal()" style="display:inline-flex;align-items:center;gap:6px;background:#4f46e5;color:white;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:600;border:none;cursor:pointer;">
                        <i class="fas fa-plus"></i> Report ToDo
                    </button>
                    <a href="{{ $empIssueListUrl }}" style="display:inline-flex;align-items:center;gap:6px;background:#6366f1;color:white;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:600;text-decoration:none;">
                        <i class="fas fa-eye"></i> View My Issues
                    </a>
                </div>
            </div>

            <div id="emp-todo-active-list" style="max-height:430px;overflow-y:auto;">
            @forelse(($todoActiveIssues ?? collect()) as $issue)
            @php
                $eDue     = $issue->due_date ? \Carbon\Carbon::parse($issue->due_date) : null;
                $eOverdue = $eDue && $eDue->lt(\Carbon\Carbon::today());
                $eUrgent  = $issue->priority === 'high' && ($eOverdue || ($eDue && $eDue->isToday()));
                $eBadge   = $eUrgent ? 'URGENT' : strtoupper($issue->priority ?? 'LOW');
                $eLeftClr = $eUrgent ? '#dc2626' : match($issue->priority) {
                    'high'   => '#f97316',
                    'medium' => '#f59e0b',
                    default  => '#22c55e',
                };
                $eBadgeSt = $eUrgent
                    ? 'background:#fef2f2;color:#dc2626;border:1px solid #fecaca;'
                    : match($issue->priority) {
                        'high'   => 'background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;',
                        'medium' => 'background:#fefce8;color:#ca8a04;border:1px solid #fde68a;',
                        default  => 'background:#f0fdf4;color:#16a34a;border:1px solid #86efac;',
                    };
                $eIconCls = $eUrgent ? 'fas fa-fire' : match($issue->priority) {
                    'high'   => 'fas fa-exclamation-circle',
                    'medium' => 'fas fa-clipboard',
                    default  => 'fas fa-check',
                };
                $eIconBg  = $eUrgent ? '#fee2e2' : match($issue->priority) {
                    'high'   => '#fff7ed',
                    'medium' => '#fefce8',
                    default  => '#f0fdf4',
                };
                $eTimeAgo  = $issue->created_at ? $issue->created_at->diffForHumans() : '';
                $eDept     = optional($issue->department)->name;
                $eCreator  = optional($issue->creator)->name ?? 'Unknown';
                $eMyStatus = optional($issue->assignees->first())->pivot->status ?? $issue->status ?? 'pending';
                $eMyNote   = optional($issue->assignees->first())->pivot->note ?? '';
                $eStStyle  = match($eMyStatus) {
                    'completed'   => 'background:#dcfce7;color:#16a34a;',
                    'in_progress' => 'background:#dbeafe;color:#1d4ed8;',
                    default       => 'background:#f3f4f6;color:#6b7280;',
                };
                $eStLabel  = match($eMyStatus) {
                    'in_progress' => 'In Progress',
                    default       => ucfirst($eMyStatus),
                };
            @endphp
            <div style="padding:14px 20px;border-bottom:1px solid #f8fafc;border-left:3px solid {{ $eLeftClr }};">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                    <div style="display:flex;align-items:flex-start;gap:12px;min-width:0;flex:1;">
                        <div style="width:38px;height:38px;border-radius:11px;background:{{ $eIconBg }};display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                            <i class="{{ $eIconCls }}" style="color:{{ $eLeftClr }};font-size:15px;"></i>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:13px;font-weight:700;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $issue->title }}</div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                                Assigned by: <strong style="color:#64748b;">{{ $eCreator }}</strong>
                                @if($eTimeAgo) &middot; {{ $eTimeAgo }} @endif
                                @if($eDept) &middot; {{ $eDept }} @endif
                            </div>
                            @if($eMyNote)
                            <div style="font-size:11px;color:#64748b;margin-top:4px;background:#f8fafc;padding:4px 8px;border-radius:6px;font-style:italic;">{{ Str::limit($eMyNote, 60) }}</div>
                            @endif
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0;">
                        <span style="font-size:10px;font-weight:800;padding:3px 10px;border-radius:999px;white-space:nowrap;letter-spacing:.4px;{{ $eBadgeSt }}">{{ $eBadge }}</span>
                        <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;white-space:nowrap;{{ $eStStyle }}">My: {{ $eStLabel }}</span>
                        <div style="display:flex;gap:5px;margin-top:2px;">
                            <button onclick="openDashStatusModal({{ $issue->id }}, '{{ $eMyStatus }}', '{{ addslashes($eMyNote) }}')"
                                style="font-size:11px;font-weight:600;color:#4f46e5;background:#eef2ff;border:none;border-radius:6px;padding:3px 9px;cursor:pointer;">
                                <i class="fas fa-sync-alt"></i> Status
                            </button>
                            <button onclick="openDashDetailsModal({{ $issue->id }})"
                                style="font-size:11px;font-weight:600;color:#374151;background:#f1f5f9;border:none;border-radius:6px;padding:3px 9px;cursor:pointer;">
                                <i class="fas fa-eye"></i> Details
                            </button>
                            @if(Auth::user()->hasRole('super admin') || Auth::user()->hasRole('admin'))
                            <button onclick="openDashEditModal({{ $issue->id }})"
                                style="font-size:11px;font-weight:600;color:#047857;background:#d1fae5;border:none;border-radius:6px;padding:3px 9px;cursor:pointer;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div style="padding:36px 20px;text-align:center;color:#94a3b8;font-size:13px;">
                <i class="fas fa-check-double" style="font-size:28px;color:#22c55e;display:block;margin-bottom:10px;"></i>
                All caught up! No active issues assigned to you.
            </div>
            @endforelse
            </div>{{-- #emp-todo-active-list --}}
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">Recent Activities</h3>



            </div>

            @if(($employeeRecentActivities ?? collect())->isNotEmpty())
                <div class="divide-y divide-gray-100">
                    @foreach($employeeRecentActivities as $activity)
                        <div class="px-5 py-4 flex items-center justify-between gap-4">
                            <div class="min-w-0 flex items-center gap-3">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $activity->dot_class }}"></span>
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-800 truncate">{{ $activity->title }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $activity->subtitle }}</p>
                                </div>
                            </div>
                            <span class="shrink-0 inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold {{ $activity->badge_class }}">
                                {{ $activity->badge }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-8 text-sm text-gray-500">
                    No recent employee activity found.
                </div>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">Latest 5 Assigned Tasks</h3>
            </div>

            @if(($employeeDueTasks ?? collect())->isNotEmpty())
                <div class="divide-y divide-gray-100">
                    @foreach($employeeDueTasks as $task)
                        <div class="px-5 py-4 flex items-center justify-between gap-4">
                            <div class="min-w-0 flex items-center gap-3">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $task->dot_class }}"></span>
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-800 truncate">{{ $task->title }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $task->status }}</p>
                                </div>
                            </div>
                            <span class="shrink-0 inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold {{ $task->priority_class }}">
                                {{ $task->priority }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-8 text-sm text-gray-500">
                    No assigned tasks found.
                </div>
            @endif
        </div>
    </div>
    @endif
    {{-- Footer --}}
    <div style="text-align:center;padding:20px 0 32px;border-top:1px solid #e2e8f0;margin-top:8px;">
        <div style="font-size:13px;font-weight:700;color:#4f46e5;">EPAL ERP System</div>
        <div style="font-size:11px;color:#94a3b8;margin-top:4px;">All modules in one view · {{ $periodLabel }}</div>
    </div>

    <script>
        (function(){
            function updateClock(){
                var el = document.getElementById('live-clock');
                if(el) el.textContent = new Date().toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
            }
            setInterval(updateClock, 1000);
            updateClock();
        })();

        (function(){
            var sources = @json($dmSources ?? []);
            if(!sources.length) return;

            var step = 110;
            var panels = document.querySelectorAll('.rn-panel');
            if(!panels.length) return;

            // Live Bulletin deep links land on a row that may be sitting in a
            // pane the user has navigated away from; each panel registers a
            // revealer so the ticker can switch back to it before scrolling.
            var revealers = [];

            // The panel is rendered more than once (sidebar + lower section), so
            // every lookup is scoped to its own root and each copy keeps its own
            // source index — switching one must not move the other.
            panels.forEach(function (panel) {
                var sourceIndex = 0;
                var list = null;
                var upBtn = null;
                var downBtn = null;

                var prevSourceBtn = panel.querySelector('.rn-source-btn[data-nav="prev"]');
                var nextSourceBtn = panel.querySelector('.rn-source-btn[data-nav="next"]');
                var sourceLabel = panel.querySelector('.rn-source-label');
                var panelSub = panel.querySelector('.rn-panel-sub');
                var countBadge = panel.querySelector('.rn-count-badge');
                var overdueBadge = panel.querySelector('.rn-overdue-badge');
                var totalNote = panel.querySelector('.rn-total-note');

                function updateScrollButtons(){
                    if(!list || !upBtn || !downBtn) return;

                    var maxScrollTop = list.scrollHeight - list.clientHeight;

                    if(maxScrollTop <= 2){
                        upBtn.style.display = 'none';
                        downBtn.style.display = 'none';
                        return;
                    }

                    upBtn.style.display = 'flex';
                    downBtn.style.display = 'flex';
                    upBtn.disabled = list.scrollTop <= 2;
                    downBtn.disabled = list.scrollTop >= (maxScrollTop - 2);
                }

                function setActiveSource(nextIndex){
                    sourceIndex = (nextIndex + sources.length) % sources.length;
                    var active = sources[sourceIndex];

                    panel.setAttribute('data-active-source', active.key);

                    var activePane = null;

                    panel.querySelectorAll('.rn-source-pane').forEach(function (pane) {
                        var isActive = pane.dataset.source === active.key;
                        pane.classList.toggle('active', isActive);
                        if (isActive) activePane = pane;
                    });

                    if (sourceLabel) sourceLabel.textContent = active.label;
                    if (panelSub) panelSub.textContent = active.note;
                    if (countBadge) countBadge.textContent = active.count + ' Due';

                    if (overdueBadge) {
                        var overdue = active.overdue || 0;
                        overdueBadge.textContent = overdue + ' Overdue';
                        overdueBadge.classList.toggle('is-zero', overdue === 0);
                    }

                    if (totalNote) totalNote.textContent = active.count + ' total · live sync';

                    // Each pane owns its own scroller and scroll buttons, so resolve
                    // them from the pane that just became visible.
                    list = activePane ? activePane.querySelector('.rn-items') : null;
                    upBtn = activePane ? activePane.querySelector('.rn-scroll-btn[data-dir="up"]') : null;
                    downBtn = activePane ? activePane.querySelector('.rn-scroll-btn[data-dir="down"]') : null;

                    updateScrollButtons();
                }

                panel.querySelectorAll('.rn-scroll-btn[data-dir]').forEach(function (btn) {
                    btn.addEventListener('click', function(){
                        if (!list) return;
                        list.scrollBy({ top: btn.dataset.dir === 'up' ? -step : step, behavior: 'smooth' });
                    });
                });

                panel.querySelectorAll('.rn-items').forEach(function (items) {
                    items.addEventListener('scroll', updateScrollButtons);
                });

                if(prevSourceBtn){
                    prevSourceBtn.addEventListener('click', function(){
                        setActiveSource(sourceIndex - 1);
                    });
                }

                if(nextSourceBtn){
                    nextSourceBtn.addEventListener('click', function(){
                        setActiveSource(sourceIndex + 1);
                    });
                }

                revealers.push(function (el) {
                    if (!panel.contains(el)) return false;

                    var pane = el.closest('.rn-source-pane');
                    if (!pane) return false;

                    var index = sources.findIndex(function (source) {
                        return source.key === pane.dataset.source;
                    });

                    if (index >= 0 && index !== sourceIndex) setActiveSource(index);

                    return true;
                });

                window.addEventListener('resize', updateScrollButtons);
                setActiveSource(0);
            });

            window.EpalRenewalCenter = {
                reveal: function (el) {
                    return revealers.some(function (revealer) {
                        return revealer(el);
                    });
                }
            };
        })();

        // Qt-row: auto-scroll + button controls + mouse drag
        (function(){
            var row     = document.querySelector('.qt-row');
            var track   = document.querySelector('.qt-track');
            var prevBtn = document.getElementById('qt-prev');
            var nextBtn = document.getElementById('qt-next');
            if(!row || !track) return;

            var isDragging    = false;
            var startX;
            var currentOffset = 0;
            var STEP          = 150; // px per step
            var AUTO_INTERVAL = 2500; // ms between auto-slides
            var autoTimer;

            function getTrackOffset(){
                var style  = window.getComputedStyle(track);
                var matrix = new DOMMatrix(style.transform);
                return matrix.m41;
            }

            function applyOffset(val){
                var halfWidth = track.scrollWidth / 2;
                if(halfWidth <= 0){ currentOffset = val; track.style.transform = 'translateX('+val+'px)'; return; }
                val = ((val % halfWidth) - halfWidth) % halfWidth;
                currentOffset = val;
                track.style.transform = 'translateX(' + val + 'px)';
            }

            function autoNext(){ applyOffset(getTrackOffset() - STEP); }

            function startAuto(){ autoTimer = setInterval(autoNext, AUTO_INTERVAL); }
            function stopAuto() { clearInterval(autoTimer); }
            function resetAuto(){ stopAuto(); startAuto(); }

            startAuto();

            // pause on hover, resume on leave
            row.addEventListener('mouseenter', stopAuto);
            row.addEventListener('mouseleave', startAuto);

            if(prevBtn){
                prevBtn.addEventListener('click', function(){
                    applyOffset(getTrackOffset() + STEP);
                    resetAuto();
                });
                prevBtn.addEventListener('mouseenter', stopAuto);
                prevBtn.addEventListener('mouseleave', startAuto);
            }
            if(nextBtn){
                nextBtn.addEventListener('click', function(){
                    applyOffset(getTrackOffset() - STEP);
                    resetAuto();
                });
                nextBtn.addEventListener('mouseenter', stopAuto);
                nextBtn.addEventListener('mouseleave', startAuto);
            }

            row.addEventListener('mousedown', function(e){
                isDragging    = true;
                startX        = e.clientX;
                currentOffset = getTrackOffset();
                track.style.transform = 'translateX(' + currentOffset + 'px)';
                row.style.cursor = 'grabbing';
                stopAuto();
                e.preventDefault();
            });

            document.addEventListener('mousemove', function(e){
                if(!isDragging) return;
                var dx = e.clientX - startX;
                track.style.transform = 'translateX(' + (currentOffset + dx) + 'px)';
            });

            document.addEventListener('mouseup', function(){
                if(!isDragging) return;
                isDragging = false;
                row.style.cursor = '';
                var finalOffset = getTrackOffset();
                var halfWidth   = track.scrollWidth / 2;
                var normalized  = ((finalOffset % halfWidth) - halfWidth) % halfWidth;
                currentOffset   = normalized;
                track.style.transform = 'translateX(' + normalized + 'px)';
                startAuto();
            });
        })();
    </script>

    @include('attendances.edit-modal')
    @include('attendances.create-modal')

    {{-- Report ToDo Modal --}}
    <div id="reportTodoModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.55);align-items:center;justify-content:center;padding:16px;">
        <div style="background:white;border-radius:20px;width:100%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;max-height:90vh;display:flex;flex-direction:column;">
            {{-- Modal Header --}}
            <div style="padding:20px 24px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:#eef2ff;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-clipboard-list" style="color:#4f46e5;font-size:15px;"></i>
                    </div>
                    <div>
                        <div style="font-size:16px;font-weight:800;color:#0f172a;">Report Office ToDo</div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:1px;">নতুন office todo যোগ করুন</div>
                    </div>
                </div>
                <button onclick="closeReportTodoModal()" style="width:32px;height:32px;border-radius:50%;background:#f1f5f9;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:16px;">&times;</button>
            </div>

            {{-- Modal Body --}}
            <div style="padding:20px 24px;overflow-y:auto;flex:1;">
                <form id="reportTodoForm" enctype="multipart/form-data">
                    @csrf

                    {{-- Title --}}
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">
                            Title <span style="color:#ef4444;">*</span>
                        </label>
                        <input type="text" name="title" id="rtTitle" placeholder="Todo এর বিষয় লিখুন..."
                            style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;box-sizing:border-box;">
                    </div>

                    {{-- Description --}}
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Description</label>
                        <textarea name="description" id="rtDescription" rows="3" placeholder="বিস্তারিত লিখুন..."
                            style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
                    </div>

                    {{-- Checklist --}}
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">
                            <i class="fas fa-list-check" style="color:#4f46e5;margin-right:4px;"></i>Checklist Items
                        </label>
                        <div id="rtChecklistList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:8px;"></div>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="rtChecklistInput" placeholder="একটি checklist item লিখুন..."
                                style="flex:1;padding:9px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;box-sizing:border-box;">
                            <button type="button" id="rtAddChecklistBtn"
                                style="padding:9px 16px;border-radius:10px;border:none;background:#eef2ff;color:#4f46e5;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">
                                + Add
                            </button>
                        </div>
                    </div>

                    {{-- Priority & Due Date --}}
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Priority</label>
                            <select name="priority" id="rtPriority"
                                style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;background:white;box-sizing:border-box;">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Due Date</label>
                            <input type="date" name="due_date" id="rtDueDate"
                                style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;box-sizing:border-box;">
                        </div>
                    </div>

                    {{-- Department --}}
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Department</label>
                        <select name="department_id" id="rtDepartment"
                            style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;background:white;box-sizing:border-box;">
                            <option value="">-- Select Department --</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Assigned To --}}
                    <div style="margin-bottom:16px;" id="rtAssignedWrap">
                        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Assign To</label>
                        <select name="assigned_to[]" id="rtAssignedTo" multiple
                            style="width:100%;">
                            @foreach($users as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                            @endforeach
                        </select>
                        <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="is_self" id="rtIsSelf" value="1" style="width:14px;height:14px;">
                            <label for="rtIsSelf" style="font-size:12px;color:#64748b;cursor:pointer;">Self — নিজের জন্য todo</label>
                        </div>
                    </div>

                    {{-- Error area --}}
                    <div id="rtError" style="display:none;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px 14px;font-size:13px;color:#dc2626;margin-bottom:12px;"></div>
                </form>
            </div>

            {{-- Modal Footer --}}
            <div style="padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-shrink:0;">
                <button onclick="closeReportTodoModal()" style="padding:9px 20px;border-radius:10px;border:1px solid #e2e8f0;background:white;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;">Cancel</button>
                <button id="rtSubmitBtn" onclick="submitReportTodo()" style="padding:9px 22px;border-radius:10px;border:none;background:#4f46e5;color:white;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-plus" id="rtSubmitIcon"></i>
                    <span id="rtSubmitText">Add ToDo</span>
                </button>
            </div>
        </div>
    </div>

    <div id="rnDetailsModal" class="rn-modal" aria-hidden="true">
        <div class="rn-modal-card">
            <div class="rn-modal-h">
                <div>
                    <div class="rn-modal-title" id="rnModalTitle">Subscription</div>
                    <div class="rn-modal-sub" id="rnModalSub">Details</div>
                </div>
                <button type="button" id="rnModalClose" class="rn-modal-close" aria-label="Close subscription details">&times;</button>
            </div>
            <div class="rn-modal-body">
                <div class="rn-modal-grid">
                    <div class="rn-modal-item">
                        <div class="rn-modal-k">Access Type</div>
                        <div class="rn-modal-v" id="rnModalAccessType">—</div>
                    </div>
                    <div class="rn-modal-item">
                        <div class="rn-modal-k">Subscription Type</div>
                        <div class="rn-modal-v" id="rnModalSubscriptionType">—</div>
                    </div>
                    <div class="rn-modal-item">
                        <div class="rn-modal-k">Amount</div>
                        <div class="rn-modal-v" id="rnModalAmount">—</div>
                    </div>
                    <div class="rn-modal-item">
                        <div class="rn-modal-k">Priority</div>
                        <div class="rn-modal-v" id="rnModalPriority">—</div>
                    </div>
                    <div class="rn-modal-item">
                        <div class="rn-modal-k">Due Date</div>
                        <div class="rn-modal-v" id="rnModalDueDate">—</div>
                    </div>
                    <div class="rn-modal-item">
                        <div class="rn-modal-k">Days Left</div>
                        <div class="rn-modal-v" id="rnModalDaysLeft">—</div>
                    </div>
                    <div class="rn-modal-item">
                        <div class="rn-modal-k">Renewal Date</div>
                        <div class="rn-modal-v" id="rnModalRenewalDate">—</div>
                    </div>
                    <div class="rn-modal-item">
                        <div class="rn-modal-k">Expired Date</div>
                        <div class="rn-modal-v" id="rnModalExpiredDate">—</div>
                    </div>
                    <div class="rn-modal-item">
                        <div class="rn-modal-k">Email / Username</div>
                        <div class="rn-modal-v" id="rnModalEmail">—</div>
                    </div>
                    <div class="rn-modal-item">
                        <div class="rn-modal-k">Phone</div>
                        <div class="rn-modal-v" id="rnModalPhone">—</div>
                    </div>
                    <div class="rn-modal-item full">
                        <div class="rn-modal-k">Access URL</div>
                        <div class="rn-modal-v" id="rnModalUrl">—</div>
                    </div>
                    <div class="rn-modal-item full">
                        <div class="rn-modal-k">Notes</div>
                        <div class="rn-modal-v" id="rnModalNotes">No notes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
        
    {{-- ── Dashboard: Todo Details Modal ───────────────────────────────────────── --}}
    <div id="dashTodoDetailsModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.55);align-items:center;justify-content:center;padding:16px;">
        <div style="background:white;border-radius:20px;width:100%;max-width:640px;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;max-height:90vh;display:flex;flex-direction:column;">
            <div style="padding:20px 24px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:#eef2ff;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-clipboard-list" style="color:#4f46e5;font-size:15px;"></i>
                    </div>
                    <div>
                        <div style="font-size:16px;font-weight:800;color:#0f172a;">Todo Details</div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:1px;">Full information</div>
                    </div>
                </div>
                <button onclick="closeDashDetailsModal()" style="width:32px;height:32px;border-radius:50%;background:#f1f5f9;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:18px;line-height:1;">&times;</button>
            </div>
            <div style="padding:20px 24px;overflow-y:auto;flex:1;" id="dashTodoDetailsBody">
                <div style="text-align:center;padding:40px;color:#94a3b8;">
                    <i class="fas fa-spinner fa-spin" style="font-size:24px;"></i>
                    <div style="margin-top:10px;font-size:13px;">Loading...</div>
                </div>
            </div>
            <div style="padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;flex-shrink:0;">
                <button onclick="closeDashDetailsModal()" style="padding:9px 20px;border-radius:10px;border:1px solid #e2e8f0;background:white;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;">Close</button>
            </div>
        </div>
    </div>

    {{-- ── Dashboard: Quick Status Update Modal ────────────────────────────────── --}}
    <div id="dashTodoStatusModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.55);align-items:center;justify-content:center;padding:16px;">
        <div style="background:white;border-radius:20px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;">
            <div style="padding:20px 24px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-tasks" style="color:#16a34a;font-size:15px;"></i>
                    </div>
                    <div style="font-size:16px;font-weight:800;color:#0f172a;">Update Status</div>
                </div>
                <button onclick="closeDashStatusModal()" style="width:32px;height:32px;border-radius:50%;background:#f1f5f9;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:18px;line-height:1;">&times;</button>
            </div>
            <div style="padding:20px 24px;">
                <input type="hidden" id="dashStatusTodoId">
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Status <span style="color:#ef4444;">*</span></label>
                    <select id="dashStatusSelect" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;background:white;box-sizing:border-box;">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div style="margin-bottom:4px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Note <span style="font-size:11px;font-weight:400;color:#94a3b8;">(optional)</span></label>
                    <textarea id="dashStatusNote" rows="3" placeholder="Add a note..."
                        style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
                </div>
                <div id="dashStatusError" style="display:none;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:8px 12px;font-size:12px;color:#dc2626;margin-top:10px;"></div>
            </div>
            <div style="padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;gap:10px;justify-content:flex-end;">
                <button onclick="closeDashStatusModal()" style="padding:9px 20px;border-radius:10px;border:1px solid #e2e8f0;background:white;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;">Cancel</button>
                <button id="dashStatusSubmitBtn" onclick="submitDashStatus()" style="padding:9px 22px;border-radius:10px;border:none;background:#4f46e5;color:white;font-size:13px;font-weight:700;cursor:pointer;">Save</button>
            </div>
        </div>
    </div>

    {{-- ── Dashboard: Todo Edit Modal ───────────────────────────────────────── --}}
    @if(Auth::user()->hasRole('super admin') || Auth::user()->hasRole('admin'))
    <div id="dashTodoEditModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.55);align-items:center;justify-content:center;padding:16px;">
        <div style="background:white;border-radius:20px;width:100%;max-width:640px;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;max-height:90vh;display:flex;flex-direction:column;">
            <div style="padding:20px 24px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:#d1fae5;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-edit" style="color:#047857;font-size:15px;"></i>
                    </div>
                    <div>
                        <div style="font-size:16px;font-weight:800;color:#0f172a;">Edit Todo</div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:1px;">Update todo information</div>
                    </div>
                </div>
                <button onclick="closeDashEditModal()" style="width:32px;height:32px;border-radius:50%;background:#f1f5f9;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:18px;line-height:1;">&times;</button>
            </div>
            <div style="padding:20px 24px;overflow-y:auto;flex:1;">
                <form id="dashEditForm" enctype="multipart/form-data">
                    <input type="hidden" id="dashEditId">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div style="grid-column:1/-1;">
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Title <span style="color:#ef4444;">*</span></label>
                            <input type="text" id="dashEditTitle" placeholder="Todo title..."
                                style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;box-sizing:border-box;">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Description</label>
                            <textarea id="dashEditDescription" rows="3" placeholder="Description..."
                                style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Department</label>
                            <select id="dashEditDepartment" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;background:white;box-sizing:border-box;">
                                <option value="">— None —</option>
                                @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Priority</label>
                            <select id="dashEditPriority" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;background:white;box-sizing:border-box;">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Start Date</label>
                            <input type="date" id="dashEditStartDate"
                                style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Due Date</label>
                            <input type="date" id="dashEditDueDate"
                                style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Status</label>
                            <select id="dashEditStatus" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;background:white;box-sizing:border-box;">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;padding-top:20px;">
                            <input type="checkbox" id="dashEditIsSelf" style="width:16px;height:16px;cursor:pointer;accent-color:#4f46e5;">
                            <label for="dashEditIsSelf" style="font-size:12px;font-weight:600;color:#374151;cursor:pointer;">Self — নিজের জন্য</label>
                        </div>
                        <div style="grid-column:1/-1;" id="dashEditAssigneeBlock">
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Assign To</label>
                            <select id="dashEditAssignees" multiple
                                style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;box-sizing:border-box;min-height:80px;">
                                @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;"><i class="fas fa-list-check" style="color:#4f46e5;margin-right:4px;"></i>Checklist Items</label>
                            <div id="dashEditChecklistList" style="margin-bottom:8px;"></div>
                            <div style="display:flex;gap:8px;">
                                <input type="text" id="dashEditChecklistInput" placeholder="Add a checklist item..."
                                    style="flex:1;padding:9px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;box-sizing:border-box;">
                                <button type="button" id="dashEditAddChecklistBtn"
                                    style="padding:9px 16px;background:#eef2ff;color:#4f46e5;border:none;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">+ Add</button>
                            </div>
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Replace Attachment</label>
                            <input type="file" id="dashEditAttachment" name="attachment"
                                style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;box-sizing:border-box;">
                        </div>
                    </div>
                    <div id="dashEditError" style="display:none;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:8px 12px;font-size:12px;color:#dc2626;margin-top:12px;"></div>
                </form>
            </div>
            <div style="padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;gap:10px;justify-content:flex-end;flex-shrink:0;">
                <button onclick="closeDashEditModal()" style="padding:9px 20px;border-radius:10px;border:1px solid #e2e8f0;background:white;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;">Cancel</button>
                <button id="dashEditSubmitBtn" onclick="submitDashEdit()" style="padding:9px 22px;border-radius:10px;border:none;background:#047857;color:white;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;"><i class="fas fa-save"></i> Update</button>
            </div>
        </div>
    </div>
    @endif

@endsection

@section('raw-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .cl-ghost { opacity: .45; background: #eef2ff !important; }
        .dash-cl-handle:active, .dash-cb-handle:active { cursor: grabbing !important; }
    </style>
    <script>
        $(document).ready(function() {
            const taskWeeklyCtx = document.getElementById('taskWeeklyChart');
            const taskMonthlyCtx = document.getElementById('taskMonthlyChart');

            const taskWeeklyLabels = @json($taskWeeklyLabels ?? []);
            const taskWeeklyCreatedData = @json($taskWeeklyCreatedData ?? []);
            const taskWeeklyCompletedData = @json($taskWeeklyCompletedData ?? []);
            const taskMonthlyLabels = @json($taskMonthlyLabels ?? []);
            const taskMonthlyCreatedData = @json($taskMonthlyCreatedData ?? []);
            const taskMonthlyCompletedData = @json($taskMonthlyCompletedData ?? []);
            const monthlyIncomeLabels = @json($monthlyIncomeLabels ?? []);
            const monthlyIncomeData = @json($monthlyIncomeData ?? []);
            const monthlyExpenseData = @json($monthlyExpenseData ?? []);
            const selectedMonthIndex = {{ $selectedMonth - 1 }}; // 0-based index for chart highlight

            if (taskWeeklyCtx) {
                new Chart(taskWeeklyCtx, {
                    type: 'bar',
                    data: {
                        labels: taskWeeklyLabels,
                        datasets: [{
                                label: 'Created',
                                data: taskWeeklyCreatedData,
                                backgroundColor: 'rgba(59, 130, 246, 0.75)',
                                borderRadius: 6
                            },
                            {
                                label: 'Completed',
                                data: taskWeeklyCompletedData,
                                backgroundColor: 'rgba(16, 185, 129, 0.75)',
                                borderRadius: 6
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }

            if (taskMonthlyCtx) {
                // Build per-point radius: bigger dot on selected month
                const taskMonthlyPointRadius = taskMonthlyLabels.map((_, i) => i === selectedMonthIndex ? 6 : 3);
                const taskMonthlyPointBgCreated = taskMonthlyLabels.map((_, i) => i === selectedMonthIndex ? '#2563eb' : 'rgba(37,99,235,0.6)');
                const taskMonthlyPointBgCompleted = taskMonthlyLabels.map((_, i) => i === selectedMonthIndex ? '#059669' : 'rgba(5,150,105,0.6)');

                new Chart(taskMonthlyCtx, {
                    type: 'line',
                    data: {
                        labels: taskMonthlyLabels,
                        datasets: [{
                                label: 'Created',
                                data: taskMonthlyCreatedData,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37, 99, 235, 0.15)',
                                borderWidth: 2,
                                pointRadius: taskMonthlyPointRadius,
                                pointBackgroundColor: taskMonthlyPointBgCreated,
                                tension: 0.35,
                                fill: true
                            },
                            {
                                label: 'Completed',
                                data: taskMonthlyCompletedData,
                                borderColor: '#059669',
                                backgroundColor: 'rgba(5, 150, 105, 0.15)',
                                borderWidth: 2,
                                pointRadius: taskMonthlyPointRadius,
                                pointBackgroundColor: taskMonthlyPointBgCompleted,
                                tension: 0.35,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: { position: 'top' },
                            annotation: {}
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 }
                            }
                        }
                    }
                });
            }

            const incomeExpenseCtx = document.getElementById('incomeExpenseChart');

            if (incomeExpenseCtx) {
                // Highlight the selected month's bars with a brighter colour
                const incomeBgColors  = monthlyIncomeLabels.map((_, i) =>
                    i === selectedMonthIndex ? 'rgba(14,165,233,1)' : 'rgba(14,165,233,0.55)');
                const expenseBgColors = monthlyIncomeLabels.map((_, i) =>
                    i === selectedMonthIndex ? 'rgba(249,115,22,1)' : 'rgba(249,115,22,0.55)');

                new Chart(incomeExpenseCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyIncomeLabels,
                        datasets: [
                            {
                                label: 'Income',
                                data: monthlyIncomeData,
                                backgroundColor: incomeBgColors,
                                borderRadius: 5,
                                borderSkipped: false,
                                barPercentage: 0.45,
                                categoryPercentage: 0.75,
                            },
                            {
                                label: 'Expense',
                                data: monthlyExpenseData,
                                backgroundColor: expenseBgColors,
                                borderRadius: 5,
                                borderSkipped: false,
                                barPercentage: 0.45,
                                categoryPercentage: 0.75,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        return ' ' + ctx.dataset.label + ': ' +
                                            new Intl.NumberFormat(undefined, { minimumFractionDigits: 2 }).format(ctx.parsed.y);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0,
                                    callback: function(value) {
                                        if (value >= 1000) return (value / 1000).toFixed(1) + 'k';
                                        return value;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // ── Profit / Loss Trend Chart ────────────────────────────────────────
            const profitLossCtx = document.getElementById('profitLossChart');
            if (profitLossCtx) {
                const profitLossData = monthlyIncomeData.map((inc, i) =>
                    parseFloat((inc - (monthlyExpenseData[i] || 0)).toFixed(2))
                );

                // Per-point colors: green = profit, red = loss
                const plPointColors = profitLossData.map(v => v >= 0 ? '#16a34a' : '#dc2626');
                const plPointRadius = profitLossData.map((_, i) => i === selectedMonthIndex ? 7 : 4);

                new Chart(profitLossCtx, {
                    type: 'line',
                    data: {
                        labels: monthlyIncomeLabels,
                        datasets: [{
                            label: 'Net Profit/Loss',
                            data: profitLossData,
                            borderWidth: 2.5,
                            pointRadius: plPointRadius,
                            pointBackgroundColor: plPointColors,
                            pointBorderColor: plPointColors,
                            tension: 0.35,
                            fill: {
                                target: { value: 0 },
                                above: 'rgba(22,163,74,0.12)',
                                below: 'rgba(220,38,38,0.12)',
                            },
                            segment: {
                                borderColor: ctx => {
                                    const y0 = ctx.p0.parsed.y;
                                    const y1 = ctx.p1.parsed.y;
                                    if (y0 >= 0 && y1 >= 0) return '#16a34a';
                                    if (y0 < 0 && y1 < 0)  return '#dc2626';
                                    return '#94a3b8'; // crossing zero
                                }
                            }
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const val = ctx.parsed.y;
                                        const sign = val >= 0 ? 'Profit' : 'Loss';
                                        return ' ' + sign + ': ' +
                                            new Intl.NumberFormat(undefined, { minimumFractionDigits: 2 })
                                                .format(Math.abs(val));
                                    },
                                    labelColor: function(ctx) {
                                        const color = ctx.parsed.y >= 0 ? '#16a34a' : '#dc2626';
                                        return { backgroundColor: color, borderColor: color };
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                grid: { color: 'rgba(0,0,0,0.05)' },
                                ticks: {
                                    callback: function(value) {
                                        if (Math.abs(value) >= 1000) return (value / 1000).toFixed(1) + 'k';
                                        return value;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            const currentUserId = {{ auth()->id() }};
            const selectedReceiverStorageKey = 'chat_selected_receiver_id';
            const selectedReceiverNameStorageKey = 'chat_selected_receiver_name';
            let currentUserFilter = 'all';

            let receiver_id = null;

            const chatSendUrl =
                "{{ route('role.chat.send', ['role' => Str::slug(Auth::user()->getRoleNames()->first() ?? 'user')]) }}";

            const chatFetchUrlTemplate =
                "{{ route('role.chat.fetch', ['role' => Str::slug(Auth::user()->getRoleNames()->first() ?? 'user'), 'receiver_id' => 'PLACEHOLDER']) }}";

            const userListUrl =
                "{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first() ?? 'user')]) }}";

            function getReceiverNameById(userId) {
                return $(`.chat-user-item[data-id="${userId}"]`).data('name') || 'Unknown';
            }

            function getReceiverAvatarById(userId) {
                return $(`.chat-user-item[data-id="${userId}"]`).data('avatar') || '';
            }

            function getReceiverInitialById(userId) {
                const name = getReceiverNameById(userId);
                if (!name) return 'U';
                return String(name).trim().charAt(0).toUpperCase() || 'U';
            }

            function getUserItemById(userId) {
                return $(`.chat-user-item[data-id="${userId}"]`);
            }

            function updateChatHeaderUser(userId) {
                const $item = getUserItemById(userId);

                if (!$item.length) {
                    $('#chatHeaderUserMeta').addClass('hidden').removeClass('flex');
                    $('#chatHeaderTitle').removeClass('hidden');
                    return;
                }

                const name = $item.data('name') || 'Unknown';
                const avatar = $item.data('avatar') || '';
                const isOnline = String($item.data('online')) === '1';
                const initial = String(name).trim().charAt(0).toUpperCase() || 'U';

                $('#chatHeaderUserName').text(name);
                $('#chatHeaderUserStatus').text(isOnline ? 'Online' : 'Offline');
                $('#chatHeaderUserDot')
                    .removeClass('status-online status-offline')
                    .addClass(isOnline ? 'status-online' : 'status-offline');

                if (avatar) {
                    $('#chatHeaderUserAvatar').attr('src', avatar).removeClass('hidden');
                    $('#chatHeaderUserFallback').addClass('hidden').removeClass('flex');
                } else {
                    $('#chatHeaderUserAvatar').addClass('hidden').attr('src', '');
                    $('#chatHeaderUserFallback').text(initial).removeClass('hidden').addClass('flex');
                }

                $('#chatHeaderTitle').addClass('hidden');
                $('#chatHeaderUserMeta').removeClass('hidden').addClass('flex');
            }

            function updateUnreadBadge(userId, count) {
                const $badge = getUserItemById(userId).find('.chat-unread-count');
                if (!$badge.length) return;

                const safeCount = Math.max(0, parseInt(count || 0, 10));
                if (safeCount > 0) {
                    $badge.text(safeCount).removeClass('hidden');
                } else {
                    $badge.text('0').addClass('hidden');
                }

                updateTotalUnreadBadge();
            }

            function incrementUnreadBadge(userId) {
                const $badge = getUserItemById(userId).find('.chat-unread-count');
                if (!$badge.length) return;

                const currentCount = parseInt($badge.text() || '0', 10);
                updateUnreadBadge(userId, currentCount + 1);
            }

            function updateTotalUnreadBadge() {
                let totalUnread = 0;

                $('.chat-unread-count').each(function() {
                    const count = parseInt($(this).text() || '0', 10);
                    if (!$(this).hasClass('hidden')) {
                        totalUnread += isNaN(count) ? 0 : count;
                    }
                });

                const $totalBadge = $('#chatTotalUnreadCount');
                if (!$totalBadge.length) return;

                if (totalUnread > 0) {
                    $totalBadge.text(totalUnread).removeClass('hidden');
                } else {
                    $totalBadge.text('0').addClass('hidden');
                }
            }

            function renderMessages(name, messages) {
                let html = `<div class="text-center mb-4">
                        <span class="text-[10px] bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full font-bold uppercase tracking-wider">
                            Chat with ${name}
                        </span>
                    </div>`;

                messages.forEach(msg => {
                    let isMine = (msg.sender_id == currentUserId);
                    let side = isMine ? 'message-sent' : 'message-received';
                    let alignClass = isMine ? 'items-end' : 'items-start';
                    let timeText = msg.created_at ? new Date(msg.created_at).toLocaleString() : '';
                    let statusText = isMine ? (msg.read_at ? 'Seen' : 'Sent') : '';
                    let receiverAvatar = getReceiverAvatarById(msg.receiver_id);
                    let receiverInitial = getReceiverInitialById(msg.receiver_id);
                    let seenIndicator = (isMine && msg.read_at) ?
                        `<div class="message-seen-indicator">${receiverAvatar
                            ? `<img src="${receiverAvatar}" class="message-seen-avatar" alt="seen">`
                            : `<span class="message-seen-fallback">${receiverInitial}</span>`}</div>` :
                        '';

                    html += `<div class="flex flex-col ${alignClass} ${isMine ? 'message-row-sent' : 'message-row-received'}">
                        <div class="message-bubble ${side}">${msg.message}</div>
                        <div class="message-time">${timeText}${statusText ? `<span class="message-meta-status">${statusText}</span>` : ''}</div>
                        ${seenIndicator}
                    </div>`;
                });

                $('#chatMessages').html(html);
                $("#chatMessages").scrollTop($("#chatMessages")[0].scrollHeight);
            }

            $('#chatMessages').on('click', '.message-bubble', function() {
                const $time = $(this).next('.message-time');
                if (!$time.length) return;

                $('.message-time').not($time).hide();
                $time.toggle();
            });

            function loadMessages(userId, name) {
                let fetchUrl = chatFetchUrlTemplate.replace('PLACEHOLDER', userId);

                $.get(fetchUrl, function(messages) {
                    renderMessages(name, messages);
                    updateUnreadBadge(userId, 0);
                }).fail(function() {
                    $('#chatMessages').html(
                        `<div class="text-center text-xs text-red-500 py-4">Failed to load messages.</div>`
                        );
                });
            }

            // Initialize Echo listener for Pusher
            if (typeof Echo !== 'undefined') {
                window.Echo.private(`chat.{{ auth()->id() }}`)
                    .listen('.message.sent', (e) => {
                        // Only append if the message is from the user currently selected in the sidebar
                        if (receiver_id == e.chat.sender_id && $('#chatWindow').hasClass('active')) {
                            loadMessages(receiver_id, getReceiverNameById(receiver_id));
                        } else {
                            // Highlight the user in the sidebar to show a new message has arrived
                            $(`.chat-user-item[data-id="${e.chat.sender_id}"]`).addClass(
                                'bg-indigo-50 border-r-4 border-indigo-500');
                            incrementUnreadBadge(e.chat.sender_id);
                        }
                    })
                    .listen('.message.read', (e) => {
                        const isCurrentConversation = String(receiver_id || '') === String(e.reader_id || '');
                        if (isCurrentConversation) {
                            loadMessages(receiver_id, getReceiverNameById(receiver_id));
                        }
                    });
            }

            $('.select2').select2();

            // CHAT LOGIC
            $('#chatToggle').click(function() {
                $('#chatWindow').toggleClass('active');

                if ($('#chatWindow').hasClass('active')) {
                    $('#chatSidebar').removeClass('hidden');

                    if (!receiver_id) {
                        const lastReceiverId = localStorage.getItem(selectedReceiverStorageKey);
                        if (lastReceiverId) {
                            receiver_id = lastReceiverId;
                        }
                    }

                    if (receiver_id) {
                        $('.chat-user-item').removeClass('active');
                        $(`.chat-user-item[data-id="${receiver_id}"]`).addClass('active');
                        updateChatHeaderUser(receiver_id);
                        loadMessages(receiver_id, getReceiverNameById(receiver_id));
                    } else {
                        updateChatHeaderUser(null);
                    }
                }
            });

            $('#closeChat').click(() => $('#chatWindow').removeClass('active'));

            $('#chatSidebarToggle').click(function() {
                $('#chatSidebar').toggleClass('hidden');
            });

            function applyUserFilters() {
                let value = ($('#userSearchInput').val() || '').toLowerCase();

                $('#chatUsers .chat-user-item').each(function() {
                    const nameMatched = $(this).find('.user-search-name').text().toLowerCase().indexOf(
                        value) > -1;
                    const isOnline = String($(this).data('online')) === '1';

                    const statusMatched = currentUserFilter === 'all' ?
                        true :
                        (currentUserFilter === 'online' ? isOnline : !isOnline);

                    $(this).toggle(nameMatched && statusMatched);
                });
            }

            function renderGlobalSearchSuggestions(users) {
                const $suggestions = $('#globalSearchSuggestions');
                if (!users.length) {
                    $suggestions.html('<div class="px-4 py-3 text-sm text-gray-500">No users found.</div>').removeClass('hidden');
                    return;
                }
                const items = users.map(user => {
                    return `<button type="button" class="w-full text-left px-4 py-3 border-b border-gray-100 hover:bg-gray-50 global-search-suggestion-item" data-id="${user.id}" data-name="${user.name}">${user.name}${user.email ? `<span class="block text-[10px] text-gray-400">${user.email}</span>` : ''}</button>`;
                }).join('');
                $suggestions.html(items).removeClass('hidden');
            }

            function hideGlobalSearchSuggestions() {
                $('#globalSearchSuggestions').addClass('hidden');
            }

            function searchGlobalUsers(query) {
                if (!query.trim()) {
                    hideGlobalSearchSuggestions();
                    return;
                }

                $.get("{{ route('role.user.search', ['role' => Str::slug(Auth::user()->getRoleNames()->first() ?? 'user')]) }}", { q: query })
                    .done(function(data) {
                        renderGlobalSearchSuggestions(data);
                    })
                    .fail(function() {
                        hideGlobalSearchSuggestions();
                    });
            }

            let globalSearchTimer = null;

            // Header user search suggestions
            $('#globalSearch').on('keyup', function() {
                const value = $(this).val() || '';
                clearTimeout(globalSearchTimer);
                globalSearchTimer = setTimeout(() => searchGlobalUsers(value), 250);
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('#globalSearch, #globalSearchSuggestions').length) {
                    hideGlobalSearchSuggestions();
                }
            });

            $(document).on('click', '.global-search-suggestion-item', function(e) {
                e.stopPropagation();
                const userId = $(this).data('id');
                const userName = $(this).data('name');

                if (userId) {
                    window.location.href = userListUrl + '?name=' + encodeURIComponent(userName);
                    return;
                }

                hideGlobalSearchSuggestions();
            });

            // Chat sidebar search
            $('#userSearchInput').on('keyup', function() {
                applyUserFilters();
            });

            $('.chat-filter-btn').on('click', function() {
                currentUserFilter = $(this).data('filter');
                $('.chat-filter-btn').removeClass('bg-indigo-600 text-white').addClass(
                    'bg-gray-100 text-gray-600');
                $(this).removeClass('bg-gray-100 text-gray-600').addClass('bg-indigo-600 text-white');
                applyUserFilters();
            });

            $('.chat-user-item').click(function() {
                receiver_id = $(this).data('id');
                let selectedName = $(this).data('name');

                localStorage.setItem(selectedReceiverStorageKey, receiver_id);
                localStorage.setItem(selectedReceiverNameStorageKey, selectedName);

                $('.chat-user-item').removeClass('active');
                $(this).addClass('active');

                updateChatHeaderUser(receiver_id);
                loadMessages(receiver_id, selectedName);
            });

            $('#sendChatBtn').click(function() {
                let msg = $('#chatMessageInput').val().trim();
                if (!receiver_id || msg === '') return;

                $.post(chatSendUrl, {
                    message: msg,
                    receiver_id: receiver_id,
                    _token: '{{ csrf_token() }}'
                }, function(res) {
                    $('#chatMessageInput').val('');
                    loadMessages(receiver_id, getReceiverNameById(receiver_id));
                });
            });

            $('#chatMessageInput').keypress((e) => {
                if (e.which == 13) $('#sendChatBtn').click();
            });

            $('#chatEmojiBtn').click(function(e) {
                e.stopPropagation();
                $('#chatEmojiPanel').toggleClass('hidden');
            });

            $('#chatEmojiPanel').on('click', '.chat-emoji-item', function() {
                const emoji = $(this).data('emoji');
                const $input = $('#chatMessageInput');
                $input.val(($input.val() || '') + emoji).focus();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('#chatEmojiPanel, #chatEmojiBtn').length) {
                    $('#chatEmojiPanel').addClass('hidden');
                }
            });

            // ATTENDANCE MODAL LOGIC
            function normalizeTimeValue(value) {
                if (!value) {
                    return '';
                }

                const stringValue = String(value).trim();
                if (!stringValue) {
                    return '';
                }

                return stringValue.length >= 5 ? stringValue.substring(0, 5) : stringValue;
            }

            $('.create-from-row-btn').click(function() {
                $('#create_user_id').val($(this).data('user_id')).trigger('change');
                $('#create_company_id').val($(this).data('company_id')).trigger('change');
                $('#create_shift_id').val($(this).data('shift_id')).trigger('change');
                $('#create_date').val($(this).data('date'));
                $('#create_status').val($(this).data('status') || 'present').trigger('change');
                $('#create_check_in').val(normalizeTimeValue($(this).data('check_in')));
                $('#create_check_out').val(normalizeTimeValue($(this).data('check_out')));
                $('#create_note').val($(this).data('note') || '');
                $('#createModal').removeClass('hidden');
            });

            $('.edit-item-btn').click(function() {
                $('#editItemId').val($(this).data('item_id'));
                $('#edit_user_id').val($(this).data('user_id')).trigger('change');
                $('#edit_company_id').val($(this).data('company_id')).trigger('change');
                $('#edit_shift_id').val($(this).data('shift_id')).trigger('change');
                $('#edit_date').val($(this).data('date'));
                $('#edit_check_in').val(normalizeTimeValue($(this).data('check_in')));
                $('#edit_check_out').val(normalizeTimeValue($(this).data('check_out')));
                $('#edit_note').val($(this).data('note'));
                $('#edit_status').val($(this).data('status')).trigger('change');
                $('#editModal').removeClass('hidden');
            });

            $('.modal-close-create, .modal-close-edit, .modal-backdrop').click(function() {
                $('#createModal, #editModal').addClass('hidden');
            });

            function validateCreateAttendanceForm() {
                let isValid = true;

                $('#createForm .error-message').addClass('hidden');
                $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');

                if (!$('#create_user_id').val()) {
                    $('#create_user_msg').removeClass('hidden');
                    isValid = false;
                }

                if (!($('#create_date').val() || '').trim()) {
                    $('#create_date').next('.error-message').removeClass('hidden');
                    $('#create_date').addClass('border-red-500');
                    isValid = false;
                }

                return isValid;
            }

            function validateEditAttendanceForm() {
                let isValid = true;

                $('#editForm .error-message').addClass('hidden');
                $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');

                if (!$('#edit_user_id').val()) {
                    $('#edit_user_msg').removeClass('hidden');
                    isValid = false;
                }

                if (!($('#edit_date').val() || '').trim()) {
                    $('#edit_date').next('.error-message').removeClass('hidden');
                    $('#edit_date').addClass('border-red-500');
                    isValid = false;
                }

                return isValid;
            }

            $('#createSubmit').click(function(e) {
                e.preventDefault();

                if (!validateCreateAttendanceForm()) {
                    return;
                }

                let formData = new FormData($('#createForm')[0]);
                $.ajax({
                    url: $('#createForm').attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Done',
                                text: 'Attendance created successfully!'
                            });
                            $('#createModal').addClass('hidden');
                            $('#createForm')[0].reset();
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops...',
                                text: response.message || 'Failed to create attendance.'
                            });
                        }
                    },
                    error: function(xhr) {
                        const errorMessage = xhr.responseJSON?.message || 'Failed to create attendance.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: errorMessage
                        });
                    }
                });
            });

            $('#editSubmit').click(function(e) {
                e.preventDefault();

                if (!validateEditAttendanceForm()) {
                    return;
                }

                let formData = new FormData($('#editForm')[0]);
                $.ajax({
                    url: $(this).data('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Done',
                                text: 'Attendance updated successfully!'
                            });
                            $('#editModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops...',
                                text: response.message || 'Failed to update attendance.'
                            });
                        }
                    },
                    error: function(xhr) {
                        const errorMessage = xhr.responseJSON?.message || 'Failed to update attendance.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: errorMessage
                        });
                    }
                });
            });

            // Restore last selected chat user after page reload/open
            const lastReceiverId = localStorage.getItem(selectedReceiverStorageKey);
            if (lastReceiverId) {
                const $lastUser = $(`.chat-user-item[data-id="${lastReceiverId}"]`);
                if ($lastUser.length) {
                    $lastUser.trigger('click');
                }
            }

            updateTotalUnreadBadge();
        });

        // ── Custom Month-Year Picker ──────────────────────────────────────────
        (function () {
            const MONTHS    = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const MAX_YEAR  = {{ now()->year }};
            const MAX_MONTH = {{ now()->month }}; // 1-based

            let mpYear  = {{ $selectedYear }};
            let mpMonth = {{ $selectedMonth }}; // 1-based, currently selected

            const wrapper   = document.getElementById('monthPickerWrapper');
            const btn       = document.getElementById('monthPickerBtn');
            const dropdown  = document.getElementById('monthPickerDropdown');
            const chevron   = document.getElementById('monthPickerChevron');
            const yearLabel = document.getElementById('mpYear');
            const grid      = document.getElementById('mpMonthGrid');
            const prevYearBtn = document.getElementById('mpPrevYear');
            const nextYearBtn = document.getElementById('mpNextYear');

            // Elements not present on this page — skip silently
            if (!wrapper || !btn || !dropdown || !grid) return;

            function navigate(period) {
                const url = new URL(window.location.href);
                url.searchParams.set('period', period);
                window.location.href = url.toString();
            }

            function renderGrid() {
                yearLabel.textContent = mpYear;

                // Prev year button
                prevYearBtn.disabled = mpYear <= 2000;
                prevYearBtn.classList.toggle('opacity-30', mpYear <= 2000);
                prevYearBtn.classList.toggle('cursor-not-allowed', mpYear <= 2000);

                // Next year button
                nextYearBtn.disabled = mpYear >= MAX_YEAR;
                nextYearBtn.classList.toggle('opacity-30', mpYear >= MAX_YEAR);
                nextYearBtn.classList.toggle('cursor-not-allowed', mpYear >= MAX_YEAR);

                grid.innerHTML = '';
                MONTHS.forEach(function (name, idx) {
                    const monthNum = idx + 1;
                    const isFuture   = mpYear > MAX_YEAR || (mpYear === MAX_YEAR && monthNum > MAX_MONTH);
                    const isSelected = mpYear === {{ $selectedYear }} && monthNum === {{ $selectedMonth }};

                    const cell = document.createElement('button');
                    cell.type        = 'button';
                    cell.textContent = name;

                    if (isSelected) {
                        cell.className = 'py-2 text-sm rounded-lg font-bold bg-indigo-600 text-white';
                    } else if (isFuture) {
                        cell.className = 'py-2 text-sm rounded-lg text-gray-300 cursor-not-allowed';
                        cell.disabled  = true;
                    } else {
                        cell.className = 'py-2 text-sm rounded-lg font-medium text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition';
                        cell.addEventListener('click', function () {
                            navigate(mpYear + '-' + String(monthNum).padStart(2, '0'));
                        });
                    }

                    grid.appendChild(cell);
                });
            }

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                mpYear = {{ $selectedYear }}; // reset to selected year each open
                const isHidden = dropdown.classList.contains('hidden');
                dropdown.classList.toggle('hidden', !isHidden);
                chevron.style.transform = isHidden ? 'rotate(180deg)' : '';
                if (isHidden) renderGrid();
            });

            prevYearBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (mpYear > 2000) { mpYear--; renderGrid(); }
            });

            nextYearBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (mpYear < MAX_YEAR) { mpYear++; renderGrid(); }
            });

            // Close on outside click
            document.addEventListener('click', function (e) {
                if (!wrapper.contains(e.target)) {
                    dropdown.classList.add('hidden');
                    chevron.style.transform = '';
                }
            });
        })();

        // ── Report ToDo Modal ─────────────────────────────────────────────────
        const todoStoreUrl   = "{{ route('role.office-todos.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first() ?? 'user')]) }}";
        const todoListUrl    = "{{ route('role.dashboard.todo-list', ['role' => Str::slug(Auth::user()->getRoleNames()->first() ?? 'user')]) }}";
        const todoListCsrf   = "{{ csrf_token() }}";

        // is_self toggling
        $(document).ready(function () {
            $('#rtIsSelf').on('change', function () {
                const disabled = this.checked;
                $('#rtAssignedTo').prop('disabled', disabled);
                if ($('#rtAssignedTo').hasClass('select2-hidden-accessible')) {
                    $('#rtAssignedTo').prop('disabled', disabled).trigger('change');
                }
            });
        });

        function openReportTodoModal() {
            document.getElementById('reportTodoModal').style.display = 'flex';
            // Init Select2 after modal becomes visible
            setTimeout(function () {
                if (!$('#rtAssignedTo').hasClass('select2-hidden-accessible')) {
                    $('#rtAssignedTo').select2({
                        dropdownParent: $('#reportTodoModal > div'),
                        placeholder: 'কাউকে assign করুন...',
                        allowClear: true,
                        width: '100%',
                    });
                }
                // Uncheck is_self and enable select on fresh open
                $('#rtIsSelf').prop('checked', false);
                $('#rtAssignedTo').prop('disabled', false);
            }, 50);
        }

        function closeReportTodoModal() {
            document.getElementById('reportTodoModal').style.display = 'none';
            document.getElementById('reportTodoForm').reset();
            if ($('#rtAssignedTo').hasClass('select2-hidden-accessible')) {
                $('#rtAssignedTo').val(null).trigger('change');
            }
            $('#rtIsSelf').prop('checked', false);
            $('#rtAssignedTo').prop('disabled', false);
            $('#rtError').hide().text('');
            $('#rtChecklistList').empty();
            $('#rtChecklistInput').val('');
        }
        
        // ===== RENEWAL PANEL =====
        (function () {
            const modal = document.getElementById('rnDetailsModal');
            if (!modal) return;

            const closeBtn = document.getElementById('rnModalClose');
            const titleEl = document.getElementById('rnModalTitle');
            const subEl = document.getElementById('rnModalSub');
            const accessTypeEl = document.getElementById('rnModalAccessType');
            const subscriptionTypeEl = document.getElementById('rnModalSubscriptionType');
            const amountEl = document.getElementById('rnModalAmount');
            const priorityEl = document.getElementById('rnModalPriority');
            const dueDateEl = document.getElementById('rnModalDueDate');
            const daysLeftEl = document.getElementById('rnModalDaysLeft');
            const renewalDateEl = document.getElementById('rnModalRenewalDate');
            const expiredDateEl = document.getElementById('rnModalExpiredDate');
            const emailEl = document.getElementById('rnModalEmail');
            const phoneEl = document.getElementById('rnModalPhone');
            const urlEl = document.getElementById('rnModalUrl');
            const notesEl = document.getElementById('rnModalNotes');

            function safeValue(value) {
                if (value === null || value === undefined || value === '') return '—';
                return String(value);
            }

            function openModal(item) {
                const ds = item.dataset;

                titleEl.textContent = safeValue(ds.title);
                subEl.textContent = safeValue(ds.company);
                accessTypeEl.textContent = safeValue(ds.accessType);
                subscriptionTypeEl.textContent = safeValue(ds.subscriptionType);

                const currency = ds.currency ? (ds.currency + ' ') : '';
                amountEl.textContent = safeValue((ds.amount && ds.amount !== '—') ? (currency + ds.amount) : '—');

                priorityEl.textContent = safeValue(ds.priorityLabel);
                dueDateEl.textContent = safeValue(ds.dueDate);
                daysLeftEl.textContent = safeValue(ds.daysLabel);
                renewalDateEl.textContent = safeValue(ds.renewalDate);
                expiredDateEl.textContent = safeValue(ds.expiredDate);
                emailEl.textContent = safeValue(ds.email);
                phoneEl.textContent = safeValue(ds.phone);

                const url = (ds.linkUrl || '').trim();
                if (url && url !== '#') {
                    urlEl.innerHTML = '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + url + '</a>';
                } else {
                    urlEl.textContent = '—';
                }

                notesEl.textContent = safeValue(ds.notes);

                modal.style.display = 'flex';
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            document.querySelectorAll('.rn-item-open').forEach(function (item) {
                item.addEventListener('click', function (e) {
                    if (e.target.closest('a')) return;
                    openModal(item);
                });

                item.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openModal(item);
                    }
                });
            });

            if (closeBtn) closeBtn.addEventListener('click', closeModal);

            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.style.display === 'flex') {
                    closeModal();
                }
            });
        })();

        // Close on backdrop click
        document.getElementById('reportTodoModal').addEventListener('click', function (e) {
            if (e.target === this) closeReportTodoModal();
        });

        // ── Checklist builder for dashboard modal ─────────────────────────────
        (function () {
            var rtSeq = 0;

            function makeRtRow(text, parentKey) {
                var idx   = rtSeq++;
                var base  = 'checklists[' + idx + ']';
                var key   = 'rk' + idx;
                var isSub = !!parentKey;
                var safe  = String(text == null ? '' : text).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');

                return $(
                    '<div class="rt-cl-row" data-key="' + key + '"' +
                        (isSub ? ' data-parent-key="' + parentKey + '"' : '') +
                        ' style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;margin-bottom:6px;' +
                        (isSub ? 'border-left:3px solid #c7d2fe;' : '') + '">' +
                        '<div style="display:flex;align-items:center;gap:8px;">' +
                            '<i class="fas fa-grip-vertical rt-handle" style="color:#cbd5e1;font-size:11px;cursor:grab;"></i>' +
                            '<span class="rt-serial" style="font-size:11px;font-weight:700;color:#94a3b8;min-width:26px;"></span>' +
                            '<input type="hidden" name="' + base + '[key]" value="' + key + '">' +
                            (isSub ? '<input type="hidden" name="' + base + '[parent_key]" value="' + parentKey + '">' : '') +
                            '<input type="text" name="' + base + '[title]" value="' + safe + '" placeholder="' + (isSub ? 'Sub-item…' : 'Checklist item…') + '" style="flex:1;font-size:13px;color:#374151;border:1px solid #e2e8f0;border-radius:5px;padding:4px 8px;">' +
                            (isSub ? '' : '<button type="button" class="rt-add-sub" title="Add sub-item" style="background:none;border:none;cursor:pointer;color:#4f46e5;font-size:11px;font-weight:700;white-space:nowrap;padding:0;"><i class="fas fa-plus"></i> Sub</button>') +
                            '<button type="button" class="rt-remove-checklist" style="background:none;border:none;cursor:pointer;color:#f87171;font-size:13px;line-height:1;padding:0;">' +
                                '<i class="fas fa-times"></i>' +
                            '</button>' +
                        '</div>' +
                        '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:6px;padding-left:19px;">' +
                            '<select name="' + base + '[priority]" style="font-size:11px;border:1px solid #e2e8f0;border-radius:5px;padding:3px 6px;background:#fff;">' +
                                '<option value="low">Low</option>' +
                                '<option value="medium" selected>Medium</option>' +
                                '<option value="high">High</option>' +
                            '</select>' +
                            '<input type="date" name="' + base + '[start_date]" title="Start date" style="font-size:11px;border:1px solid #e2e8f0;border-radius:5px;padding:3px 6px;">' +
                            '<input type="date" name="' + base + '[end_date]" title="End date" style="font-size:11px;border:1px solid #e2e8f0;border-radius:5px;padding:3px 6px;">' +
                        '</div>' +
                        (isSub ? '' : '<div class="rt-cb-children" data-parent-key="' + key + '" style="margin-left:22px;margin-top:6px;"></div>') +
                    '</div>'
                );
            }

            function addRtChecklistItem(text) {
                text = text.trim();
                if (!text) return;
                $('#rtChecklistList').append(makeRtRow(text, ''));
                dashCbInitSortable();
                dashCbRenumber();
                $('#rtChecklistInput').val('').focus();
            }

            $(document).on('click', '.rt-add-sub', function () {
                var $parent = $(this).closest('.rt-cl-row');
                var $row    = makeRtRow('', $parent.attr('data-key'));

                $parent.children('.rt-cb-children').append($row);
                dashCbInitSortable();
                dashCbRenumber();
                $row.find('input[name$="[title]"]').focus();
            });

            // A parent takes its sub-items with it — they're nested inside it.
            $(document).on('click', '.rt-remove-checklist', function () {
                $(this).closest('.rt-cl-row').remove();
                dashCbRenumber();
            });

            $('#rtAddChecklistBtn').on('click', function () {
                addRtChecklistItem($('#rtChecklistInput').val());
            });

            $('#rtChecklistInput').on('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); addRtChecklistItem($(this).val()); }
            });
        })();

        function submitReportTodo() {
            const $btn  = $('#rtSubmitBtn');
            const $icon = $('#rtSubmitIcon');
            const $text = $('#rtSubmitText');
            const $err  = $('#rtError');

            $err.hide().text('');
            $btn.prop('disabled', true);
            $icon.removeClass('fa-plus').addClass('fa-spinner fa-spin');
            $text.text('Saving...');

            const formData = new FormData(document.getElementById('reportTodoForm'));

            $.ajax({
                url: todoStoreUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': todoListCsrf },
                success: function (res) {
                    if (res.success) {
                        closeReportTodoModal();
                        refreshTodoList();
                        Swal.fire({ icon: 'success', title: 'সফল!', text: res.message || 'ToDo যোগ হয়েছে।', timer: 2000, showConfirmButton: false });
                    } else {
                        $err.text(res.message || 'কিছু একটা সমস্যা হয়েছে।').show();
                    }
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON?.message || 'Server error. আবার চেষ্টা করুন।';
                    $err.text(msg).show();
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $icon.removeClass('fa-spinner fa-spin').addClass('fa-plus');
                    $text.text('Add ToDo');
                }
            });
        }

        function refreshTodoList() {
            $.getJSON(todoListUrl, function (res) {
                if (!res.success) return;
                const items = res.data;

                const priorityColors = {
                    high:   { left: '#f97316', iconBg: '#fff7ed', iconColor: '#f97316', icon: 'fas fa-exclamation-circle', badge: 'background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;' },
                    medium: { left: '#f59e0b', iconBg: '#fefce8', iconColor: '#f59e0b', icon: 'fas fa-clipboard',          badge: 'background:#fefce8;color:#ca8a04;border:1px solid #fde68a;' },
                    low:    { left: '#22c55e', iconBg: '#f0fdf4', iconColor: '#22c55e', icon: 'fas fa-check',              badge: 'background:#f0fdf4;color:#16a34a;border:1px solid #86efac;' },
                };
                const urgentColor = { left: '#dc2626', iconBg: '#fee2e2', iconColor: '#dc2626', icon: 'fas fa-fire', badge: 'background:#fef2f2;color:#dc2626;border:1px solid #fecaca;' };
                const statusStyles = {
                    completed:   'background:#dcfce7;color:#16a34a;',
                    in_progress: 'background:#dbeafe;color:#1d4ed8;',
                    pending:     'background:#f3f4f6;color:#6b7280;',
                };
                const statusLabels = { completed: 'Completed', in_progress: 'In Progress', pending: 'Pending' };

                let html = '';
                if (!items || items.length === 0) {
                    html = `<div style="padding:36px 20px;text-align:center;color:#94a3b8;font-size:13px;">
                        <i class="fas fa-check-double" style="font-size:28px;color:#22c55e;display:block;margin-bottom:10px;"></i>
                        All caught up! No active issues.
                    </div>`;
                } else {
                    items.forEach(function (issue) {
                        const cfg       = issue.urgent ? urgentColor : (priorityColors[issue.priority] || priorityColors.low);
                        const badge     = issue.urgent ? 'URGENT' : String(issue.priority || 'low').toUpperCase();
                        const stStyle   = statusStyles[issue.status] || statusStyles.pending;
                        const stLabel   = statusLabels[issue.status] || 'Pending';
                        const meta      = 'Reported by: <strong style="color:#64748b;">' + (issue.creator || 'Unknown') + '</strong>'
                            + (issue.time_ago   ? ' &middot; ' + issue.time_ago   : '')
                            + (issue.department ? ' &middot; ' + issue.department : '');

                        let assigneesHtml = '';
                        if (!issue.is_self && issue.assignees && issue.assignees.length) {
                            assigneesHtml = '<div style="display:flex;align-items:center;gap:2px;margin-top:7px;flex-wrap:wrap;"><span style="font-size:10px;color:#94a3b8;margin-right:3px;">Assigned:</span>';
                            issue.assignees.slice(0, 5).forEach(function (a) {
                                const av = a.image || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(a.name) + '&size=22&background=4f46e5&color=fff');
                                assigneesHtml += `<img src="${av}" style="width:22px;height:22px;border-radius:50%;border:1.5px solid white;object-fit:cover;margin-right:-4px;" title="${a.name}">`;
                            });
                            if (issue.assignees.length > 5) {
                                assigneesHtml += `<span style="width:22px;height:22px;border-radius:50%;background:#e5e7eb;color:#374151;font-size:9px;display:inline-flex;align-items:center;justify-content:center;border:1.5px solid white;font-weight:700;margin-right:4px;">+${issue.assignees.length - 5}</span>`;
                            }
                            assigneesHtml += '</div>';
                        }

                        const clPct = issue.checklists_total
                            ? Math.round(issue.checklists_checked / issue.checklists_total * 100)
                            : 0;
                        const clBadge = (issue.checklists_total > 0)
                            ? `<span onclick="openDashDetailsModal(${issue.id})" style="display:inline-flex;align-items:center;gap:5px;margin-top:5px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;background:#eef2ff;color:#4f46e5;cursor:pointer;border:1px solid #c7d2fe;">
                                <i class="fas fa-list-check"></i> ${issue.checklists_checked}/${issue.checklists_total} done
                                <span style="display:inline-block;width:38px;height:4px;background:#c7d2fe;border-radius:999px;overflow:hidden;vertical-align:middle;">
                                    <span style="display:block;height:100%;width:${clPct}%;background:${clPct === 100 ? '#16a34a' : '#4f46e5'};"></span>
                                </span>
                                ${clPct}%
                               </span>`
                            : '';

                        html += `<div style="padding:14px 20px;border-bottom:1px solid #f8fafc;border-left:3px solid ${cfg.left};">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                                <div style="display:flex;align-items:flex-start;gap:12px;min-width:0;flex:1;">
                                    <div style="width:38px;height:38px;border-radius:11px;background:${cfg.iconBg};display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                                        <i class="${cfg.icon}" style="color:${cfg.iconColor};font-size:15px;"></i>
                                    </div>
                                    <div style="min-width:0;flex:1;">
                                        <div style="font-size:13px;font-weight:700;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${issue.title}</div>
                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">${meta}</div>
                                        ${clBadge}
                                        ${assigneesHtml}
                                    </div>
                                </div>
                                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0;">
                                    <span style="font-size:10px;font-weight:800;padding:3px 10px;border-radius:999px;white-space:nowrap;letter-spacing:.4px;${cfg.badge}">${badge}</span>
                                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;white-space:nowrap;${stStyle}">${stLabel}</span>
                                    <div style="display:flex;gap:5px;margin-top:2px;">
                                        <button onclick="openDashStatusModal(${issue.id}, '${issue.status}')" style="font-size:11px;font-weight:600;color:#4f46e5;background:#eef2ff;border:none;border-radius:6px;padding:3px 9px;cursor:pointer;"><i class="fas fa-sync-alt"></i> Status</button>
                                        <button onclick="openDashDetailsModal(${issue.id})" style="font-size:11px;font-weight:600;color:#374151;background:#f1f5f9;border:none;border-radius:6px;padding:3px 9px;cursor:pointer;"><i class="fas fa-eye"></i> Details</button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    });
                }

                $('#admin-todo-active-list').html(html);
                $('#emp-todo-active-list').html(html);
            });
        }

        // ── Dashboard: Todo Details Modal ─────────────────────────────────────────
        const dashTodoBaseUrl = "{{ url(Str::slug(Auth::user()->getRoleNames()->first() ?? 'user') . '/office-todos') }}";

        function openDashDetailsModal(id) {
            document.getElementById('dashTodoDetailsModal').style.display = 'flex';
            document.getElementById('dashTodoDetailsBody').innerHTML =
                '<div style="text-align:center;padding:40px;color:#94a3b8;"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i><div style="margin-top:10px;font-size:13px;">Loading...</div></div>';

            $.get(dashTodoBaseUrl + '/' + id + '/edit', function (res) {
                if (!res.success) {
                    document.getElementById('dashTodoDetailsBody').innerHTML =
                        '<div style="padding:20px;color:#dc2626;font-size:13px;">Failed to load data.</div>';
                    return;
                }
                document.getElementById('dashTodoDetailsBody').innerHTML = buildDashDetailHtml(res.data);
                dashClRenumber();
                dashClInitSortable();
            }).fail(function () {
                document.getElementById('dashTodoDetailsBody').innerHTML =
                    '<div style="padding:20px;color:#dc2626;font-size:13px;">Error loading details.</div>';
            });
        }

        function closeDashDetailsModal() {
            document.getElementById('dashTodoDetailsModal').style.display = 'none';
        }

        document.getElementById('dashTodoDetailsModal').addEventListener('click', function (e) {
            if (e.target === this) closeDashDetailsModal();
        });

        // ── Dashboard: Todo Edit Modal ─────────────────────────────────────────
        @if(Auth::user()->hasRole('super admin') || Auth::user()->hasRole('admin'))
        function openDashEditModal(id) {
            const modal = document.getElementById('dashTodoEditModal');
            modal.style.display = 'flex';
            document.getElementById('dashEditError').style.display = 'none';
            document.getElementById('dashEditForm').reset();
            document.getElementById('dashEditId').value = '';
            document.getElementById('dashEditSubmitBtn').disabled = true;
            document.getElementById('dashEditSubmitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

            // Init Select2 on the edit assignees select
            setTimeout(function () {
                if (!$('#dashEditAssignees').hasClass('select2-hidden-accessible')) {
                    $('#dashEditAssignees').select2({
                        dropdownParent: $('#dashTodoEditModal > div'),
                        placeholder: 'কাউকে assign করুন...',
                        allowClear: true,
                        width: '100%',
                    });
                }
            }, 50);

            $.get(dashTodoBaseUrl + '/' + id + '/edit', function (res) {
                if (!res.success) {
                    closeDashEditModal();
                    alert('Failed to load data.');
                    return;
                }
                const d = res.data;
                document.getElementById('dashEditId').value        = d.id;
                document.getElementById('dashEditTitle').value     = d.title || '';
                document.getElementById('dashEditDescription').value = d.description || '';
                document.getElementById('dashEditDepartment').value = d.department_id || '';
                document.getElementById('dashEditPriority').value  = d.priority || 'low';
                document.getElementById('dashEditStartDate').value = d.start_date ? d.start_date.substring(0,10) : '';
                document.getElementById('dashEditDueDate').value   = d.due_date   ? d.due_date.substring(0,10)   : '';
                document.getElementById('dashEditStatus').value    = d.status || 'pending';
                const isSelf = d.is_self == 1 || d.is_self === true;
                document.getElementById('dashEditIsSelf').checked  = isSelf;
                document.getElementById('dashEditAssigneeBlock').style.display = isSelf ? 'none' : 'block';

                const $sel = $('#dashEditAssignees');
                $sel.val(null).trigger('change');
                if (!isSelf && d.assignees && d.assignees.length) {
                    const ids = d.assignees.map(function(a){ return String(a.id); });
                    $sel.val(ids).trigger('change');
                }

                // populate checklist
                dashEditRenderChecklists(d.checklists || []);

                document.getElementById('dashEditSubmitBtn').disabled = false;
                document.getElementById('dashEditSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Update';
            }).fail(function () {
                closeDashEditModal();
                alert('Error loading todo data.');
            });
        }

        // Rows carry their own id so an edit updates them in place; without it
        // the server has to recreate the list and every tick is lost.
        var dashClSeq = 0;

        function dashEditMakeChecklistRow(item, parentKey) {
            var data  = (typeof item === 'string') ? { title: item } : (item || {});
            var idx   = dashClSeq++;
            var base  = 'checklists[' + idx + ']';
            var key   = 'dk' + idx;
            var isSub = !!parentKey;
            var pr    = data.priority || 'medium';
            var day   = function (v) { return v ? String(v).substr(0, 10) : ''; };
            var esc   = function (s) {
                return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            };
            var opt   = function (v, label) {
                return '<option value="' + v + '"' + (pr === v ? ' selected' : '') + '>' + label + '</option>';
            };

            return $('<div class="dash-cl-row" data-key="' + key + '"' +
                (isSub ? ' data-parent-key="' + esc(parentKey) + '"' : '') +
                ' style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;margin-bottom:6px;' +
                (isSub ? 'border-left:3px solid #c7d2fe;' : '') + '">' +
                '<div style="display:flex;align-items:center;gap:8px;">' +
                    '<i class="fas fa-grip-vertical dash-cb-handle" style="color:#cbd5e1;font-size:11px;cursor:grab;"></i>' +
                    '<span class="dash-cb-serial" style="font-size:11px;font-weight:700;color:#94a3b8;min-width:26px;"></span>' +
                    '<input type="hidden" name="' + base + '[key]" value="' + key + '">' +
                    (isSub ? '<input type="hidden" name="' + base + '[parent_key]" value="' + esc(parentKey) + '">' : '') +
                    (data.id ? '<input type="hidden" name="' + base + '[id]" value="' + esc(data.id) + '">' : '') +
                    '<input type="text" name="' + base + '[title]" value="' + esc(data.title) + '" placeholder="' + (isSub ? 'Sub-item…' : 'Checklist item…') + '" style="flex:1;font-size:13px;color:#374151;border:1px solid #e2e8f0;border-radius:5px;padding:4px 8px;">' +
                    (data.is_checked ? '<span style="font-size:11px;font-weight:700;color:#16a34a;white-space:nowrap;"><i class="fas fa-check-circle"></i> done</span>' : '') +
                    (isSub ? '' : '<button type="button" class="dash-add-sub-cl" title="Add sub-item" style="background:none;border:none;cursor:pointer;color:#4f46e5;font-size:11px;font-weight:700;white-space:nowrap;padding:0;"><i class="fas fa-plus"></i> Sub</button>') +
                    '<button type="button" class="dash-edit-remove-cl" style="background:none;border:none;cursor:pointer;color:#f87171;font-size:13px;padding:0;"><i class="fas fa-times"></i></button>' +
                '</div>' +
                '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:6px;padding-left:19px;">' +
                    '<select name="' + base + '[priority]" style="font-size:11px;border:1px solid #e2e8f0;border-radius:5px;padding:3px 6px;background:#fff;">' +
                        opt('low', 'Low') + opt('medium', 'Medium') + opt('high', 'High') +
                    '</select>' +
                    '<input type="date" name="' + base + '[start_date]" value="' + day(data.start_date) + '" title="Start date" style="font-size:11px;border:1px solid #e2e8f0;border-radius:5px;padding:3px 6px;">' +
                    '<input type="date" name="' + base + '[end_date]" value="' + day(data.end_date) + '" title="End date" style="font-size:11px;border:1px solid #e2e8f0;border-radius:5px;padding:3px 6px;">' +
                '</div>' +
                // Nested, so the form still submits a parent ahead of its own
                // children (document order) while a drag moves the block together.
                (isSub ? '' : '<div class="dash-cb-children" data-parent-key="' + key + '" style="margin-left:22px;margin-top:6px;"></div>') +
            '</div>');
        }

        function dashEditRenderChecklists(checklists) {
            var $list = $('#dashEditChecklistList').empty();
            if (!checklists || !checklists.length) return;

            var byId = {}, roots = [];
            checklists.forEach(function (it) { byId[it.id] = { item: it, children: [] }; });
            checklists.forEach(function (it) {
                if (it.parent_id && byId[it.parent_id]) byId[it.parent_id].children.push(byId[it.id]);
                else roots.push(byId[it.id]);
            });

            roots.forEach(function (node) {
                var $row = dashEditMakeChecklistRow(node.item, '');
                $list.append($row);
                var pKey  = $row.attr('data-key');
                var $kids = $row.children('.dash-cb-children');
                node.children.forEach(function (child) {
                    $kids.append(dashEditMakeChecklistRow(child.item, pKey));
                });
            });

            dashCbInitSortable();
            dashCbRenumber();
        }

        $(document).on('click', '.dash-add-sub-cl', function () {
            var $parent = $(this).closest('.dash-cl-row');
            var $row    = dashEditMakeChecklistRow({ title: '' }, $parent.attr('data-key'));

            $parent.children('.dash-cb-children').append($row);
            dashCbInitSortable();
            dashCbRenumber();
            $row.find('input[name$="[title]"]').focus();
        });

        function dashCbRenumber() {
            $('#dashEditChecklistList, #rtChecklistList').each(function () {
                $(this).children('.dash-cl-row, .rt-cl-row').each(function (i) {
                    var top = i + 1;
                    $(this).children('div').children('.dash-cb-serial, .rt-serial').text(top + '.');
                    $(this).children('.dash-cb-children, .rt-cb-children').children('.dash-cl-row, .rt-cl-row').each(function (j) {
                        $(this).children('div').children('.dash-cb-serial, .rt-serial').text(top + '.' + (j + 1));
                    });
                });
            });
        }

        function dashCbInitSortable() {
            if (typeof Sortable === 'undefined') return;

            var opts = {
                handle: '.dash-cb-handle, .rt-handle',
                animation: 150,
                ghostClass: 'cl-ghost',
                fallbackOnBody: true,
                onEnd: dashCbRenumber
            };

            // Marked so re-running after adding a row doesn't stack instances.
            $('#dashEditChecklistList, #rtChecklistList, .dash-cb-children, .rt-cb-children').each(function () {
                if (this.dataset.sortableReady) return;
                this.dataset.sortableReady = '1';

                var isTop = this.id === 'dashEditChecklistList' || this.id === 'rtChecklistList';
                Sortable.create(this, $.extend({
                    group: isTop ? 'dash-cb-top-' + this.id : 'dash-cb-kids-' + $(this).data('parent-key')
                }, opts));
            });
        }

        $('#dashEditAddChecklistBtn').on('click', function () {
            var val = $('#dashEditChecklistInput').val().trim();
            if (!val) return;
            $('#dashEditChecklistList').append(dashEditMakeChecklistRow(val));
            $('#dashEditChecklistInput').val('').focus();
        });

        $('#dashEditChecklistInput').on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); $('#dashEditAddChecklistBtn').trigger('click'); }
        });

        // Removing a parent takes its sub-items with it — they're nested inside
        // it, so there's nothing left behind to orphan.
        $(document).on('click', '.dash-edit-remove-cl', function () {
            $(this).closest('.dash-cl-row').remove();
            dashCbRenumber();
        });

        function closeDashEditModal() {
            document.getElementById('dashTodoEditModal').style.display = 'none';
            if ($('#dashEditAssignees').hasClass('select2-hidden-accessible')) {
                $('#dashEditAssignees').val(null).trigger('change');
            }
        }

        document.getElementById('dashTodoEditModal').addEventListener('click', function (e) {
            if (e.target === this) closeDashEditModal();
        });

        document.getElementById('dashEditIsSelf').addEventListener('change', function () {
            document.getElementById('dashEditAssigneeBlock').style.display = this.checked ? 'none' : 'block';
            if ($('#dashEditAssignees').hasClass('select2-hidden-accessible')) {
                if (this.checked) {
                    $('#dashEditAssignees').val(null).trigger('change');
                }
            }
        });

        function submitDashEdit() {
            const id = document.getElementById('dashEditId').value;
            if (!id) return;
            const title = document.getElementById('dashEditTitle').value.trim();
            if (!title) {
                const err = document.getElementById('dashEditError');
                err.style.display = 'block';
                err.textContent = 'Title is required.';
                return;
            }
            document.getElementById('dashEditError').style.display = 'none';

            const btn = document.getElementById('dashEditSubmitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            const fd = new FormData();
            fd.append('_method', 'PUT');
            fd.append('title',         title);
            fd.append('description',   document.getElementById('dashEditDescription').value);
            fd.append('department_id', document.getElementById('dashEditDepartment').value);
            fd.append('priority',      document.getElementById('dashEditPriority').value);
            fd.append('start_date',    document.getElementById('dashEditStartDate').value);
            fd.append('due_date',      document.getElementById('dashEditDueDate').value);
            fd.append('status',        document.getElementById('dashEditStatus').value);
            fd.append('is_self',       document.getElementById('dashEditIsSelf').checked ? '1' : '0');

            if (!document.getElementById('dashEditIsSelf').checked) {
                var assignedIds = $('#dashEditAssignees').val() || [];
                assignedIds.forEach(function (uid) {
                    fd.append('assigned_to[]', uid);
                });
            }

            // checklists — send id/title/priority/dates per row so existing rows
            // are updated in place instead of being recreated (which loses ticks)
            $('#dashEditChecklistList').children('.dash-cl-row').each(function () {
                var $row = $(this);
                if (!$row.find('input[name$="[title]"]').val().trim()) return;
                $row.find('input[name^="checklists"], select[name^="checklists"]').each(function () {
                    fd.append(this.name, this.value);
                });
            });

            const fileInput = document.getElementById('dashEditAttachment');
            if (fileInput.files.length > 0) {
                fd.append('attachment', fileInput.files[0]);
            }

            $.ajax({
                url: dashTodoBaseUrl + '/' + id,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': todoListCsrf },
                success: function (res) {
                    if (res.success) {
                        closeDashEditModal();
                        refreshTodoList();
                        Swal.fire({ icon: 'success', title: 'Updated!', text: 'Todo updated successfully.', timer: 1800, showConfirmButton: false });
                    } else {
                        const err = document.getElementById('dashEditError');
                        err.style.display = 'block';
                        err.textContent = res.message || 'Failed to update.';
                    }
                },
                error: function (xhr) {
                    const err = document.getElementById('dashEditError');
                    err.style.display = 'block';
                    err.textContent = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred.';
                },
                complete: function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Update';
                }
            });
        }
        @endif

        // Checklist status change inside details modal. Only leaves carry the
        // control — parents follow from what their sub-items add up to.
        const DASH_CL_ST = {
            completed:   'background:#dcfce7;color:#16a34a;',
            in_progress: 'background:#dbeafe;color:#1d4ed8;',
            pending:     'background:#f3f4f6;color:#6b7280;',
        };
        const DASH_CL_LB = { pending: 'Pending', in_progress: 'In Progress', completed: 'Completed' };

        $(document).on('click', '.dash-cl-parent-row', function (e) {
            // The sub-item list sits inside the parent now, so a click on a
            // sub-item, the drag grip or a status control would otherwise bubble
            // up here and fold the parent out from under the user.
            if ($(e.target).closest('.dash-cl-children, .dash-cl-handle, select, option').length) return;

            const $row      = $(this);
            const collapsed = $row.attr('data-collapsed') === '1';

            $row.attr('data-collapsed', collapsed ? '0' : '1');
            $row.children('div').children('.dash-cl-caret')
                .css('transform', collapsed ? 'rotate(0deg)' : 'rotate(-90deg)');
            $row.children('.dash-cl-children').css('display', collapsed ? 'block' : 'none');
        });

        // 1, 2, 3 down the top level and 1.1, 1.2 inside each parent, recomputed
        // from the DOM after every drop.
        function dashClRenumber() {
            $('#dashClList').children('.dash-cl-node').each(function (i) {
                const top = i + 1;
                $(this).children('div').children('.dash-cl-serial').text(top + '.');
                $(this).children('.dash-cl-children').children('.dash-cl-node').each(function (j) {
                    $(this).children('div').children('.dash-cl-serial').text(top + '.' + (j + 1));
                });
            });
        }

        function dashClPersistOrder() {
            const ids = [];
            $('#dashClList').children('.dash-cl-node').each(function () {
                ids.push($(this).data('checklist-id'));
                $(this).children('.dash-cl-children').children('.dash-cl-node').each(function () {
                    ids.push($(this).data('checklist-id'));
                });
            });

            $.ajax({
                url: dashTodoBaseUrl + '/' + window._dashDetailTodoId + '/checklists/reorder',
                method: 'POST',
                data: { _token: todoListCsrf, _method: 'PATCH', order: ids },
                success: function () { refreshTodoList(); },
                error: function () { alert('Could not save the new order. Reopen the todo to see the stored arrangement.'); }
            });
        }

        function dashClInitSortable() {
            if (typeof Sortable === 'undefined') return;

            const opts = {
                handle: '.dash-cl-handle',
                animation: 150,
                ghostClass: 'cl-ghost',
                fallbackOnBody: true,
                onEnd: function () { dashClRenumber(); dashClPersistOrder(); }
            };

            const list = document.getElementById('dashClList');
            if (list) Sortable.create(list, $.extend({ group: 'dash-cl-top' }, opts));

            // A group name per parent is what keeps a sub-item inside its own
            // parent for now.
            $('#dashClList').find('.dash-cl-children').each(function () {
                Sortable.create(this, $.extend({ group: 'dash-cl-kids-' + $(this).data('parent') }, opts));
            });
        }

        function dashPaintClProgress() {
            const $sels   = $('#dashClList').find('.dash-cl-status');
            const total   = $sels.length;
            const checked = $sels.filter(function () { return $(this).val() === 'completed'; }).length;
            const pct     = total ? Math.round((checked / total) * 100) : 0;
            $('#dashClProgress').html(checked + '/' + total + ' done · <span style="color:#4f46e5;">' + pct + '%</span>');
            $('#dashClBar').css({ width: pct + '%', background: pct === 100 ? '#16a34a' : '#4f46e5' });
        }

        $(document).on('change', '.dash-cl-status', function () {
            const $sel   = $(this);
            const itemId = $sel.data('id');
            const todoId = window._dashDetailTodoId;
            const status = $sel.val();
            const prev   = $sel.data('prev') || 'pending';
            const $label = $sel.closest('li').find('.dash-cl-label');

            const paintRow = function (st) {
                $sel.attr('style', 'font-size:10px;font-weight:700;border:none;border-radius:999px;padding:2px 6px;cursor:pointer;flex-shrink:0;' + (DASH_CL_ST[st] || DASH_CL_ST.pending));
                $label.css({
                    'text-decoration': st === 'completed' ? 'line-through' : 'none',
                    'color': st === 'completed' ? '#9ca3af' : '#374151',
                });
            };

            paintRow(status);

            $.ajax({
                url: dashTodoBaseUrl + '/' + todoId + '/checklists/' + itemId + '/toggle',
                method: 'POST',
                data: { _token: todoListCsrf, _method: 'PATCH', status: status },
                success: function (res) {
                    if (!res.success) {
                        $sel.val(prev); paintRow(prev);
                        return;
                    }
                    $sel.data('prev', res.status);

                    if (res.parent) {
                        $('.dash-cl-parent-badge[data-id="' + res.parent.id + '"]')
                            .attr('style', 'font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;white-space:nowrap;flex-shrink:0;' + (DASH_CL_ST[res.parent.status] || DASH_CL_ST.pending))
                            .text(DASH_CL_LB[res.parent.status] || 'Pending');
                    }

                    dashPaintClProgress();
                    refreshTodoList();
                },
                error: function () {
                    $sel.val(prev); paintRow(prev);
                }
            });
        });

        function buildDashDetailHtml(d) {
            const pColors = {
                high:   'background:#fff7ed;color:#ea580c;',
                medium: 'background:#fefce8;color:#ca8a04;',
                low:    'background:#f0fdf4;color:#16a34a;',
            };
            const stColors = {
                completed:   'background:#dcfce7;color:#16a34a;',
                in_progress: 'background:#dbeafe;color:#1d4ed8;',
                pending:     'background:#f3f4f6;color:#6b7280;',
            };
            const stLabels = { completed: 'Completed', in_progress: 'In Progress', pending: 'Pending' };
            window._dashDetailTodoId = d.id;

            let assigneesHtml = '';
            if (d.assignees && d.assignees.length) {
                assigneesHtml = d.assignees.map(function (a) {
                    const aStatus = (a.pivot && a.pivot.status) ? a.pivot.status : 'pending';
                    const aNote   = (a.pivot && a.pivot.note)   ? a.pivot.note   : '';
                    const av      = a.image
                        ? '/' + a.image
                        : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(a.name) + '&size=32&background=4f46e5&color=fff';
                    return `<div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f1f5f9;">
                        <img src="${av}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:600;color:#1e293b;">${a.name}</div>
                            ${aNote ? `<div style="font-size:11px;color:#64748b;margin-top:2px;font-style:italic;">${aNote}</div>` : ''}
                        </div>
                        <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;flex-shrink:0;${stColors[aStatus] || stColors.pending}">${stLabels[aStatus] || 'Pending'}</span>
                    </div>`;
                }).join('');
            } else {
                assigneesHtml = '<div style="font-size:12px;color:#94a3b8;padding:10px 0;">No assignees</div>';
            }

            const dueDateStr   = d.due_date   ? `<div style="padding:10px 14px;background:#f8fafc;border-radius:10px;"><div style="font-size:10px;color:#94a3b8;font-weight:600;margin-bottom:3px;">DUE DATE</div><div style="font-size:13px;font-weight:600;color:#1e293b;">${d.due_date}</div></div>` : '';
            const startDateStr = d.start_date ? `<div style="padding:10px 14px;background:#f8fafc;border-radius:10px;"><div style="font-size:10px;color:#94a3b8;font-weight:600;margin-bottom:3px;">START DATE</div><div style="font-size:13px;font-weight:600;color:#1e293b;">${d.start_date}</div></div>` : '';
            const creatorName  = (d.creator && d.creator.name) ? d.creator.name : 'Unknown';
            const deptName     = (d.department && d.department.name) ? d.department.name : '—';

            let checklistHtml = '';
            if (d.checklists && d.checklists.length) {
                const clDay    = function (v) { return v ? String(v).substr(0, 10) : ''; };
                const clStatus = function (it) { return it.is_checked ? 'completed' : (it.status || 'pending'); };

                // Parents are headings for their sub-items, so only leaves count.
                const hasChild = {};
                d.checklists.forEach(function (it) { if (it.parent_id) hasChild[it.parent_id] = true; });
                const leaves  = d.checklists.filter(function (it) { return !hasChild[it.id]; });
                const total   = leaves.length;
                const checked = leaves.filter(function (c) { return clStatus(c) === 'completed'; }).length;
                const pct     = total ? Math.round((checked / total) * 100) : 0;

                const byId = {}, roots = [];
                d.checklists.forEach(function (it) { byId[it.id] = { item: it, children: [] }; });
                d.checklists.forEach(function (it) {
                    if (it.parent_id && byId[it.parent_id]) byId[it.parent_id].children.push(byId[it.id]);
                    else roots.push(byId[it.id]);
                });

                const renderClNode = function (node, depth) {
                    const item        = node.item;
                    const isParent    = node.children.length > 0;
                    const st          = clStatus(item);
                    const lineThrough = st === 'completed' ? 'text-decoration:line-through;color:#9ca3af;' : 'color:#374151;';
                    const ipr         = item.priority || 'medium';
                    const dates = (item.start_date || item.end_date)
                        ? `<span style="font-size:10px;color:#64748b;"><i class="fas fa-calendar-alt" style="margin-right:3px;"></i>${clDay(item.start_date) || '—'} → ${clDay(item.end_date) || '—'}</span>`
                        : '';
                    const subCount = isParent
                        ? `<span style="font-size:10px;color:#64748b;"><i class="fas fa-list-ul" style="margin-right:3px;"></i>${node.children.filter(function (c) { return clStatus(c.item) === 'completed'; }).length}/${node.children.length} sub-items</span>`
                        : '';

                    // A parent's state is derived, so it gets a read-only badge;
                    // only leaves expose the editable control.
                    const control = isParent
                        ? `<span class="dash-cl-parent-badge" data-id="${item.id}" style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;white-space:nowrap;flex-shrink:0;${stColors[st] || stColors.pending}">${stLabels[st] || 'Pending'}</span>`
                        : `<select class="dash-cl-status" data-id="${item.id}" data-prev="${st}" style="font-size:10px;font-weight:700;border:none;border-radius:999px;padding:2px 6px;cursor:pointer;flex-shrink:0;${stColors[st] || stColors.pending}">
                               <option value="pending" ${st === 'pending' ? 'selected' : ''}>Pending</option>
                               <option value="in_progress" ${st === 'in_progress' ? 'selected' : ''}>In Progress</option>
                               <option value="completed" ${st === 'completed' ? 'selected' : ''}>Completed</option>
                           </select>`;

                    // Parents open folded so a long checklist stays scannable —
                    // the status badge and "x/y sub-items" already say enough to
                    // judge one without expanding it. The caret doubles as the
                    // affordance and the state.
                    const caret = isParent
                        ? `<i class="fas fa-chevron-down dash-cl-caret" style="color:#94a3b8;font-size:11px;margin-top:4px;flex-shrink:0;width:11px;cursor:pointer;transition:transform .15s;transform:rotate(-90deg);"></i>`
                        : '';

                    // Sub-items are nested inside their parent, so dragging a
                    // parent takes its own sub-items along and one can never be
                    // dropped above the parent it belongs to.
                    const kids = isParent
                        ? `<ul class="dash-cl-children" data-parent="${item.id}" style="list-style:none;margin:8px 0 0 22px;padding:0;display:none;">${node.children.map(function (c) { return renderClNode(c, depth + 1); }).join('')}</ul>`
                        : '';

                    return `<li class="dash-cl-node${isParent ? ' dash-cl-parent-row' : ''}" data-checklist-id="${item.id}"${isParent ? ` data-parent="${item.id}" data-collapsed="1"` : ''} style="padding:8px 10px;border-radius:8px;border:1px solid #f1f5f9;margin-bottom:6px;${depth ? 'border-left:3px solid #e0e7ff;' : ''}background:#fff;transition:background .15s;">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <i class="fas fa-grip-vertical dash-cl-handle" style="color:#cbd5e1;font-size:11px;margin-top:4px;cursor:grab;flex-shrink:0;"></i>
                            <span class="dash-cl-serial" style="font-size:11px;font-weight:700;color:#94a3b8;margin-top:2px;min-width:26px;flex-shrink:0;"></span>
                            ${caret}
                            <div style="flex:1;min-width:0;">
                                <div class="dash-cl-label" style="font-size:13px;${lineThrough}${isParent ? 'font-weight:600;' : ''}">${item.title}</div>
                                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:4px;">
                                    <span style="font-size:10px;font-weight:700;padding:1px 7px;border-radius:999px;${pColors[ipr] || pColors.medium}">${ipr.toUpperCase()}</span>
                                    ${dates}
                                    ${subCount}
                                </div>
                            </div>
                            ${control}
                        </div>
                        ${kids}
                    </li>`;
                };

                let itemsHtml = roots.map(function (n) { return renderClNode(n, 0); }).join('');
                checklistHtml = `
                    <div style="margin-bottom:16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <div style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;"><i class="fas fa-list-check" style="color:#4f46e5;margin-right:4px;"></i>Checklist</div>
                            <div style="font-size:11px;color:#64748b;font-weight:600;" id="dashClProgress">${checked}/${total} done · <span style="color:#4f46e5;">${pct}%</span></div>
                        </div>
                        <div style="height:6px;background:#f1f5f9;border-radius:999px;margin-bottom:10px;overflow:hidden;">
                            <div id="dashClBar" style="height:100%;width:${pct}%;background:${pct === 100 ? '#16a34a' : '#4f46e5'};border-radius:999px;transition:width .3s;"></div>
                        </div>
                        <ul style="list-style:none;padding:0;margin:0;" id="dashClList">${itemsHtml}</ul>
                    </div>`;
            }

            return `
                <div style="margin-bottom:16px;">
                    <div style="font-size:17px;font-weight:800;color:#0f172a;margin-bottom:8px;">${d.title}</div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;${pColors[d.priority] || pColors.low}">${(d.priority || 'low').toUpperCase()}</span>
                        <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;${stColors[d.status] || stColors.pending}">${stLabels[d.status] || 'Pending'}</span>
                    </div>
                </div>
                ${d.description ? `<div style="margin-bottom:16px;padding:12px;background:#f8fafc;border-radius:10px;font-size:13px;color:#374151;line-height:1.6;">${d.description}</div>` : ''}
                ${checklistHtml}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div style="padding:10px 14px;background:#f8fafc;border-radius:10px;">
                        <div style="font-size:10px;color:#94a3b8;font-weight:600;margin-bottom:3px;">CREATED BY</div>
                        <div style="font-size:13px;font-weight:600;color:#1e293b;">${creatorName}</div>
                    </div>
                    <div style="padding:10px 14px;background:#f8fafc;border-radius:10px;">
                        <div style="font-size:10px;color:#94a3b8;font-weight:600;margin-bottom:3px;">DEPARTMENT</div>
                        <div style="font-size:13px;font-weight:600;color:#1e293b;">${deptName}</div>
                    </div>
                    ${dueDateStr}${startDateStr}
                </div>
                <div>
                    <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;">Assignees</div>
                    ${assigneesHtml}
                </div>`;
        }

        // ── Dashboard: Quick Status Update Modal ──────────────────────────────────
        function openDashStatusModal(id, currentStatus, currentNote) {
            document.getElementById('dashStatusTodoId').value  = id;
            document.getElementById('dashStatusSelect').value  = currentStatus || 'pending';
            document.getElementById('dashStatusNote').value    = currentNote   || '';
            document.getElementById('dashStatusError').style.display = 'none';
            document.getElementById('dashTodoStatusModal').style.display = 'flex';
        }

        function closeDashStatusModal() {
            document.getElementById('dashTodoStatusModal').style.display = 'none';
        }

        document.getElementById('dashTodoStatusModal').addEventListener('click', function (e) {
            if (e.target === this) closeDashStatusModal();
        });

        function submitDashStatus() {
            const id     = document.getElementById('dashStatusTodoId').value;
            const status = document.getElementById('dashStatusSelect').value;
            const note   = document.getElementById('dashStatusNote').value;
            const $btn   = $('#dashStatusSubmitBtn');
            const $err   = $('#dashStatusError');

            $btn.prop('disabled', true).text('Saving...');
            $err.hide();

            $.ajax({
                url:    dashTodoBaseUrl + '/' + id + '/quick-status',
                method: 'POST',
                data:   { _token: todoListCsrf, _method: 'PATCH', status: status, note: note },
                success: function (res) {
                    if (res.success) {
                        closeDashStatusModal();
                        refreshTodoList();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'success', title: 'Done!', text: res.message || 'Status updated.', timer: 1500, showConfirmButton: false });
                        }
                    } else {
                        $err.text(res.message || 'Failed to update.').show();
                    }
                },
                error: function (xhr) {
                    $err.text((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred.').show();
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Save');
                }
            });
        }
    </script>

    {{-- ═══════════════════════════════════════════════════════════
         DASHBOARD SCHEDULE MODALS
    ═══════════════════════════════════════════════════════════════ --}}
    @php $dashRole = \Illuminate\Support\Str::slug(auth()->user()->getRoleNames()->first() ?? 'admin'); @endphp

    {{-- APPROVE --}}
    <div class="dash-modal-overlay" id="dashApproveModal">
        <div class="dash-modal-box">
            <div class="dash-modal-header" style="background:#065f46;">
                <span style="font-weight:700;font-size:14px;"><i class="fas fa-check mr-2"></i>Approve Schedule</span>
                <button onclick="dashCloseModal('dashApproveModal')" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;">&times;</button>
            </div>
            <form id="dashApproveForm" method="POST">
                @csrf @method('PATCH')
                <div class="dash-modal-body">
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#166534;">
                        <strong id="dashApproveParty"></strong> — ৳<span id="dashApproveAmt"></span>
                    </div>
                    <div class="dash-form-group">
                        <label>Approval Note (Optional)</label>
                        <textarea name="approval_note" id="dashApproveNote" rows="2" class="" style="width:100%;padding:8px 11px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:none;" placeholder="Optional note…"></textarea>
                    </div>
                </div>
                <div class="dash-modal-footer">
                    <button type="button" class="dash-btn-secondary" onclick="dashCloseModal('dashApproveModal')">Cancel</button>
                    <button type="submit" class="dash-btn-primary" style="background:#065f46;">Approve</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MARK PAID --}}
    <div class="dash-modal-overlay" id="dashMarkPaidModal">
        <div class="dash-modal-box">
            <div class="dash-modal-header" style="background:#1e40af;">
                <span style="font-weight:700;font-size:14px;"><i class="fas fa-money-bill-wave mr-2"></i>Mark as Paid</span>
                <button onclick="dashCloseModal('dashMarkPaidModal')" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;">&times;</button>
            </div>
            <form id="dashMarkPaidForm" method="POST">
                @csrf @method('PATCH')
                <div class="dash-modal-body">
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <div style="font-size:11px;color:#64748b;font-weight:600;">Party</div>
                            <div id="dashMpParty" style="font-weight:700;color:#1e293b;font-size:14px;"></div>
                        </div>
                        <div style="text-align:right;">
                            <div id="dashMpTypeLabel" style="font-size:11px;color:#64748b;font-weight:600;"></div>
                            <div id="dashMpAmount" style="font-weight:800;font-size:18px;color:#1e40af;font-family:monospace;"></div>
                        </div>
                    </div>
                    {{-- Partial toggle --}}
                    <div style="margin-bottom:12px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;color:#374151;font-size:12px;">
                            <input type="checkbox" id="dashMpPartialToggle" onchange="dashTogglePartial()" style="width:15px;height:15px;cursor:pointer;">
                            Partial Payment
                            <span style="font-size:11px;color:#64748b;font-weight:400;">(আংশিক পেমেন্ট)</span>
                        </label>
                    </div>
                    <div id="dashMpPartialFields" style="display:none;background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;margin-bottom:12px;">
                        <div class="dash-form-row">
                            <div class="dash-form-group" style="margin-bottom:0;">
                                <label>এখন পরিশোধ <span style="color:red;">*</span></label>
                                <input type="number" name="paid_amount" id="dashMpPaidAmount" step="0.01" min="0.01" placeholder="0.00" oninput="dashUpdateRemainder()">
                            </div>
                            <div class="dash-form-group" style="margin-bottom:0;">
                                <label>বাকির নতুন তারিখ</label>
                                <input type="date" name="remainder_date" id="dashMpRemainderDate">
                            </div>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:1px dashed #fde68a;margin-top:10px;">
                            <span style="font-size:12px;color:#92400e;font-weight:600;">বাকি থাকবে:</span>
                            <span id="dashMpRemainingDisplay" style="font-size:14px;font-weight:800;color:#b45309;font-family:monospace;">৳ 0.00</span>
                        </div>
                    </div>
                    <div class="dash-form-row">
                        <div class="dash-form-group">
                            <label>Payment Date <span style="color:red;">*</span></label>
                            <input type="date" name="payment_date" id="dashMpDate" required>
                        </div>
                        <div class="dash-form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" id="dashMpMethod" style="width:100%;padding:8px 11px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                                <option value="">— Select —</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="mobile_banking">Mobile Banking</option>
                                <option value="cheque">Cheque</option>
                                <option value="card">Card</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="dash-form-group">
                            <label>Bank Account <span style="color:red;">*</span></label>
                            <select name="bank_id" id="dashMpBank" required style="width:100%;padding:8px 11px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                                <option value="">— Select Bank —</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="dash-form-group">
                            <label>Reference / Note</label>
                            <input type="text" name="note" id="dashMpNote" placeholder="Optional…">
                        </div>
                    </div>
                </div>
                <div class="dash-modal-footer">
                    <button type="button" class="dash-btn-secondary" onclick="dashCloseModal('dashMarkPaidModal')">Cancel</button>
                    <button type="submit" class="dash-btn-primary" style="background:#1e40af;"><i class="fas fa-check-double mr-1"></i>Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>

    {{-- RESCHEDULE --}}
    <div class="dash-modal-overlay" id="dashRescheduleModal">
        <div class="dash-modal-box">
            <div class="dash-modal-header" style="background:#3730a3;">
                <span style="font-weight:700;font-size:14px;"><i class="fas fa-calendar-alt mr-2"></i>Reschedule</span>
                <button onclick="dashCloseModal('dashRescheduleModal')" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;">&times;</button>
            </div>
            <form id="dashRescheduleForm" method="POST">
                @csrf @method('PATCH')
                <div class="dash-modal-body">
                    <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#3730a3;">
                        <strong id="dashRsParty"></strong> &nbsp;|&nbsp; Current date: <strong id="dashRsOldDate"></strong>
                    </div>
                    <div class="dash-form-row">
                        <div class="dash-form-group">
                            <label>New Date <span style="color:red;">*</span></label>
                            <input type="date" name="new_date" id="dashRsNewDate" required>
                        </div>
                        <div class="dash-form-group">
                            <label>Reason <span style="color:red;">*</span></label>
                            <input type="text" name="reason" id="dashRsReason" placeholder="Reason for rescheduling…" required>
                        </div>
                    </div>
                </div>
                <div class="dash-modal-footer">
                    <button type="button" class="dash-btn-secondary" onclick="dashCloseModal('dashRescheduleModal')">Cancel</button>
                    <button type="submit" class="dash-btn-primary" style="background:#3730a3;">Reschedule</button>
                </div>
            </form>
        </div>
    </div>

    {{-- CANCEL hidden form --}}
    <form id="dashCancelForm" method="POST" style="display:none;">
        @csrf @method('PATCH')
    </form>

    <script>
        var _dashRole    = '{{ $dashRole }}';
        var _dashMpRaw   = 0;

        function dashCloseModal(id) {
            document.getElementById(id).classList.remove('open');
        }
        function dashOpenModal(id) {
            document.getElementById(id).classList.add('open');
        }
        // Close on backdrop click
        document.querySelectorAll('.dash-modal-overlay').forEach(function(el) {
            el.addEventListener('click', function(e) {
                if (e.target === el) dashCloseModal(el.id);
            });
        });

        /* ── APPROVE ─────────────────────────── */
        function dashOpenApprove(id, party, amt) {
            document.getElementById('dashApproveParty').textContent = party;
            document.getElementById('dashApproveAmt').textContent   = amt;
            document.getElementById('dashApproveNote').value        = '';
            document.getElementById('dashApproveForm').action       =
                '/' + _dashRole + '/payment-schedules/' + id + '/approve';
            dashOpenModal('dashApproveModal');
        }

        /* ── MARK PAID ───────────────────────── */
        function dashOpenMarkPaid(id, party, displayAmt, rawAmt, type, scheduledDate) {
            _dashMpRaw = rawAmt;
            document.getElementById('dashMpParty').textContent       = party;
            document.getElementById('dashMpAmount').textContent      = '৳ ' + displayAmt;
            document.getElementById('dashMpTypeLabel').textContent   = type === 'pay' ? 'Amount to Pay Out' : 'Amount to Collect';
            document.getElementById('dashMpDate').value              = new Date().toISOString().split('T')[0];
            document.getElementById('dashMpMethod').value            = '';
            document.getElementById('dashMpBank').value              = '';
            document.getElementById('dashMpNote').value              = '';
            document.getElementById('dashMpPartialToggle').checked   = false;
            document.getElementById('dashMpPartialFields').style.display = 'none';
            var paidInput     = document.getElementById('dashMpPaidAmount');
            paidInput.value   = '';
            paidInput.max     = rawAmt;
            paidInput.required = false;
            document.getElementById('dashMpRemainderDate').value     = scheduledDate;
            document.getElementById('dashMpRemainingDisplay').textContent = '৳ 0.00';
            document.getElementById('dashMarkPaidForm').action       =
                '/' + _dashRole + '/payment-schedules/' + id + '/mark-paid';
            dashOpenModal('dashMarkPaidModal');
        }
        function dashTogglePartial() {
            var checked = document.getElementById('dashMpPartialToggle').checked;
            document.getElementById('dashMpPartialFields').style.display = checked ? 'block' : 'none';
            var input = document.getElementById('dashMpPaidAmount');
            input.required = checked;
            if (checked) { input.focus(); dashUpdateRemainder(); }
        }
        function dashUpdateRemainder() {
            var paid      = parseFloat(document.getElementById('dashMpPaidAmount').value) || 0;
            var remaining = Math.max(0, _dashMpRaw - paid);
            document.getElementById('dashMpRemainingDisplay').textContent =
                '৳ ' + remaining.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        /* ── RESCHEDULE ──────────────────────── */
        function dashOpenReschedule(id, party, currentDate) {
            document.getElementById('dashRsParty').textContent  = party;
            document.getElementById('dashRsOldDate').textContent = currentDate;
            document.getElementById('dashRsNewDate').value       = '';
            document.getElementById('dashRsReason').value        = '';
            document.getElementById('dashRescheduleForm').action =
                '/' + _dashRole + '/payment-schedules/' + id + '/reschedule';
            dashOpenModal('dashRescheduleModal');
        }

        /* ── CANCEL ──────────────────────────── */
        function dashCancelSchedule(id, party) {
            if (!confirm('Cancel schedule for "' + party + '"?')) return;
            var form = document.getElementById('dashCancelForm');
            form.action = '/' + _dashRole + '/payment-schedules/' + id + '/cancel';
            form.submit();
        }
    </script>
@endsection
