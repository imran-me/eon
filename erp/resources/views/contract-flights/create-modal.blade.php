<style>
html:has(.fcm-overlay:not(.hidden)),
body:has(.fcm-overlay:not(.hidden)){overflow:hidden;}
/* Uses the window's height (12px off top and bottom) but is capped at
   1200px wide — on the owner's 1365px screen that leaves the chat bubble
   on the right clear (2026-09-21). */
.fcm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;display:flex;align-items:flex-start;justify-content:center;padding:12px;overflow:hidden;box-sizing:border-box;}
.fcm-overlay.hidden{display:none;}
/* SweetAlert2 defaults to z-index:1060 — below .fcm-overlay's 9000, so any
   Swal.fire() opened while a Contract Flight modal is up (Move to Another
   Flight's confirm, delete confirms, etc.) rendered behind it. Same fix as
   flight-schedules/index.blade.php. */
.swal2-container{z-index:100000!important;}
/* .fcm-box is a flex column capped to the viewport (minus the overlay's own
   24px top+bottom padding) so the box itself can never be taller than the
   screen. .fcm-body is the only flexed, scrolling child — head/steps/context/
   foot all take their natural height via flex-shrink:0. That guarantees one
   scrollbar, always: .fcm-body used to have a height guessed from the chrome
   above it (head + step bar + context strip), and the context strip's real
   height varies with what's filled in — whenever it ran taller than the
   guess, the box overflowed the viewport and .fcm-overlay's own
   overflow-y:auto kicked in as a second, outer scrollbar alongside
   .fcm-body's inner one. */
.fcm-box{background:#fff;border-radius:12px;width:100%;max-width:min(1500px,100vw - 220px);height:calc(100vh - 24px);max-height:calc(100vh - 24px);box-shadow:0 20px 60px rgba(0,0,0,.18);overflow:hidden;animation:fcmSlideIn .18s ease;font-family:'DM Sans',sans-serif;display:flex;flex-direction:column;}
@keyframes fcmSlideIn{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
.fcm-head{padding:9px 18px;background:linear-gradient(135deg,#ea580c,#c2410c);display:flex;align-items:center;gap:12px;flex-shrink:0;}
.fcm-head-ic{width:34px;height:34px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex-shrink:0;}
.fcm-title{font-size:15px;font-weight:700;color:#fff;}
.fcm-sub{font-size:11.5px;color:#fed7aa;margin-top:1px;}
.fcm-close{margin-left:auto;background:rgba(255,255,255,.2);border:none;color:#fff;width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:background .15s;}
.fcm-close:hover{background:rgba(255,255,255,.35);}
.fcm-body{padding:8px 16px 10px;flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;}
.fcm-section{margin-bottom:0;}
.fcm-section:last-child{margin-bottom:0;}
.fcm-section-title{font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#6b7280;display:flex;align-items:center;gap:6px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #f3f4f6;}
.fcm-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fcm-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
.fcm-grid-4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;}
.fcm-grid-2 .fcm-full,.fcm-grid-3 .fcm-full,.fcm-grid-4 .fcm-full{grid-column:1/-1;}
.fcm-lbl{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;}
.fcm-lbl sup,.fcm-lbl .req{color:#ef4444;}
.fcm-input,.fcm-select{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:9px 12px;font-size:13px;outline:none;transition:border-color .15s;font-family:'DM Sans',sans-serif;color:#1a2035;background:#fff;}
.fcm-input:focus,.fcm-select:focus{border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,.1);}
.fcm-foot{padding:8px 18px;background:#f8fafc;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;align-items:center;flex-shrink:0;}

/* ── Steps ─────────────────────────────────────────────────────────
   The form outgrew a single scroll once passengers became cards, so it
   is split into three panes. Everything stays inside ONE <form>, so
   serialize() still posts the whole thing in one go from any pane. */
/* Step bar and the context strip share one row (2026-09-21) — the modal
   must fit a flight, or one passenger, without a scrollbar at 1365x718. */
.fm-bar{display:flex;align-items:stretch;gap:12px;background:#f8fafc;border-bottom:1px solid #e5e7eb;padding:0 18px;flex-shrink:0;}
.fm-steps{display:flex;gap:0;flex-shrink:0;}
.fm-step{position:relative;display:flex;align-items:center;gap:9px;padding:7px 14px 6px;background:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:#94a3b8;border-bottom:2px solid transparent;margin-bottom:-1px;}
.fm-step:hover{color:#475569;}
.fm-step.active{color:#c2410c;border-bottom-color:#ea580c;}
.fm-step-n{width:21px;height:21px;border-radius:50%;background:#e2e8f0;color:#64748b;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.fm-step.active .fm-step-n{background:#ea580c;color:#fff;}
.fm-step.has-error .fm-step-n{background:#dc2626;color:#fff;}
.fm-step.has-error{color:#dc2626;}
.fm-step-sub{display:block;font-size:10.5px;font-weight:500;color:#94a3b8;margin-top:1px;}
.fm-step.active .fm-step-sub{color:#ea580c;opacity:.75;}
.fm-step-txt{text-align:left;line-height:1.2;}
@media(max-width:760px){.fm-bar{padding:0 10px;}.fm-step{padding:10px 11px;}.fm-step-sub{display:none;}}

.fm-pane{display:none;}
.fm-pane.active{display:block;}

/* Context strip — the flight stays visible while you work on passengers. */
.fm-context{display:flex;flex-wrap:wrap;gap:0 18px;align-items:center;justify-content:flex-end;margin-left:auto;min-width:0;padding:0;font-size:11.5px;color:#9a5b33;}
@media(max-width:1100px){.fm-context{display:none;}}
.fm-context b{color:#7c2d12;font-weight:700;}
.fm-context .fm-ctx-item{display:flex;gap:6px;align-items:baseline;}
.fm-context .fm-ctx-muted{color:#c89b7f;}

/* Computed figures are results, not fields — so they read as a strip. */
.fm-readout{display:flex;flex-wrap:wrap;gap:10px 30px;align-items:center;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-top:12px;}
.fm-readout-item{display:flex;flex-direction:column;gap:2px;}
.fm-readout-lbl{font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#94a3b8;}
.fm-readout-val{font-size:16px;font-weight:800;color:#1e293b;font-variant-numeric:tabular-nums;}
.fm-readout-val.pos{color:#15803d;}
.fm-readout-val.neg{color:#dc2626;}
.fm-readout-sep{width:1px;align-self:stretch;background:#dbe3ec;}
.fm-readout-note{margin-left:auto;font-size:11px;color:#94a3b8;max-width:280px;line-height:1.45;}

.fcm-foot-spacer{margin-right:auto;font-size:11.5px;color:#94a3b8;}
/* Same cost-vs-charge split the passenger cards use, so the two screens
   speak the same language about which side of the margin a figure is on. */
.fm-sub{border-top:1px solid #eef2f7;margin-top:14px;padding-top:12px;}
.fm-sub:first-of-type{border-top:none;margin-top:0;padding-top:0;}
.fm-sub-title{font-size:10.5px;font-weight:700;letter-spacing:.7px;text-transform:uppercase;color:#94a3b8;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.fm-sub-title .fm-sub-note{margin-left:auto;font-weight:500;letter-spacing:0;text-transform:none;font-size:11px;color:#cbd5e1;}
.fcm-btn-step{background:#fff;color:#c2410c;border:1px solid #fed7aa;}
.fcm-btn-step:hover{background:#fff7ed;}

/* ── Step 1 layout ─────────────────────────────────────────────────
   Two columns, because the modal is 1120px wide and step 1 used to be
   four stacked grids that each left a column empty. Left holds the
   aircraft and its times, right holds the people attached to it. */
/* Stated rather than inherited from Tailwind's preflight: without it the
   inputs' padding adds to their 100% width and spills past the card edge,
   where overflow:hidden clips them. Scoped to step 1's own subtree. */
.fs-cols,.fs-cols *{box-sizing:border-box;}
.fs-cols{display:grid;grid-template-columns:1.35fr 1fr;gap:12px;align-items:start;}
.fs-col-hidden{display:none !important;}
@media(max-width:1200px){.fs-cols{grid-template-columns:1.42fr 1fr;}}
.fs-col{display:flex;flex-direction:column;gap:14px;min-width:0;}
@media(max-width:900px){.fs-cols{grid-template-columns:1fr;}}

.fs-card{border:1px solid #e4e9f1;border-radius:12px;background:#fff;overflow:hidden;}
.fs-card-hd{display:flex;align-items:center;gap:9px;padding:10px 15px;background:#fafbfd;border-bottom:1px solid #eef1f6;}
.fs-card-hd > i{color:#c2410c;font-size:11.5px;width:14px;text-align:center;flex-shrink:0;}
.fs-card-hd > span{font-size:10.5px;font-weight:700;letter-spacing:.75px;text-transform:uppercase;color:#5b6779;}
.fs-card-hd .fs-hd-note{margin-left:auto;font-size:10.5px;font-weight:500;letter-spacing:0;text-transform:none;color:#a8b2c1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
@media(max-width:1000px){.fs-card-hd .fs-hd-note{display:none;}}
.fs-card-bd{padding:12px;display:grid;gap:11px;}
/* Grid items default to min-width:auto, so a row of date inputs refuses to
   shrink past its intrinsic width and overflows the card — which the card's
   overflow:hidden then clips. Every track has to be allowed to shrink. */
.fs-card-bd > *,.fs-row-2 > *{min-width:0;}

.fs-row-2{display:grid;grid-template-columns:1fr 1fr;gap:13px;}
@media(max-width:560px){.fs-row-2{grid-template-columns:1fr;}}
.fs-field{min-width:0;}
.fs-req{color:#e11d48;font-weight:700;}
.fs-note{font-weight:400;color:#a3adbd;font-size:11px;}
.fs-hint{font-size:11px;color:#8a94a6;margin:0;display:flex;gap:7px;align-items:flex-start;line-height:1.5;}
.fs-hint i{color:#2563eb;margin-top:2px;flex-shrink:0;}
/* One consistent error style, tight enough that revealing it barely moves
   the field below it. */
.fs-field .error-message{margin-top:5px;font-size:11px;line-height:1.35;}
/* Field titles sit on the control's top border — the passenger cards'
   ticket sub-forms already do this (.pb-sub-bd), and the owner asked for
   the whole modal to follow (2026-09-21): a field is one line, not two. */
.fs-card .fcm-lbl{position:relative;z-index:1;display:block;width:fit-content;max-width:calc(100% - 20px);margin:0 0 -7px 10px;padding:0 5px;background:#fff;font-size:10.5px;line-height:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.fs-card .fcm-lbl .fs-note{font-weight:400;color:#94a3b8;}
.fs-card .fcm-input,.fs-card .fcm-select{padding-top:11px;}
.fs-card .fcm-hint{margin-top:2px !important;}
.fs-card textarea.fcm-input{resize:vertical;min-height:64px;}
/* The route picker's "New" button has to line up with the select next to it. */
.fs-card .fcm-add-route-btn{height:40px;padding:0 14px;}

/* ── Review step ───────────────────────────────────────────────── */
.rv-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media(max-width:760px){.rv-grid{grid-template-columns:1fr;}}
.rv-block{border:1px solid #e2e8f0;border-radius:10px;background:#fff;overflow:hidden;}
.rv-block-title{font-size:10.5px;font-weight:700;letter-spacing:.7px;text-transform:uppercase;color:#64748b;background:#f8fafc;padding:9px 14px;border-bottom:1px solid #eef2f7;display:flex;align-items:center;gap:8px;}
.rv-count{background:#e0e7ff;color:#3730a3;border-radius:20px;padding:1px 8px;font-size:11px;}
.rv-dl{margin:0;padding:12px 14px;display:grid;grid-template-columns:auto 1fr;gap:7px 16px;font-size:12.5px;}
.rv-dl dt{color:#94a3b8;white-space:nowrap;}
.rv-dl dd{margin:0;color:#1a2035;font-weight:600;text-align:right;}
.rv-dl dd.rv-missing{color:#dc2626;font-weight:600;}
.rv-table-wrap{overflow-x:auto;}
.rv-table{width:100%;border-collapse:collapse;font-size:12px;}
.rv-table th{text-align:left;padding:8px 12px;font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:#94a3b8;border-bottom:1px solid #eef2f7;white-space:nowrap;}
.rv-table td{padding:9px 12px;border-bottom:1px solid #f5f7fa;color:#1a2035;vertical-align:top;}
.rv-table tr:last-child td{border-bottom:none;}
.rv-table .r{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap;}
.rv-table .rv-neg{color:#dc2626;font-weight:700;}
.rv-table .rv-pos{color:#0f766e;font-weight:700;}
.rv-pax-name{font-weight:700;}
.rv-pax-sub{color:#94a3b8;font-size:11px;margin-top:2px;}
.rv-tag{display:inline-block;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;margin:0 4px 3px 0;white-space:nowrap;}
.rv-tag-svc{background:#eef2ff;color:#3730a3;}
.rv-tag-own{background:#f1f5f9;color:#64748b;}
.rv-tag-pnr{background:#dcfce7;color:#166534;font-family:'DM Mono',monospace;}
.rv-tag-none{background:#fef3c7;color:#92400e;}
.rv-totals{display:flex;flex-wrap:wrap;gap:10px 34px;align-items:center;margin-top:14px;background:#f0fdfa;border:1px solid #99f6e4;border-radius:10px;padding:13px 18px;}
.rv-total-item{display:flex;flex-direction:column;gap:2px;}
.rv-total-lbl{font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#5eaaa2;}
.rv-total-val{font-size:17px;font-weight:800;color:#0f766e;font-variant-numeric:tabular-nums;}
.rv-total-val.neg{color:#dc2626;}
.rv-problems{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:14px;font-size:12.5px;color:#991b1b;}
.rv-problems ul{margin:6px 0 0;padding-left:18px;}
.rv-problems li{margin-bottom:3px;}
.rv-pending{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;margin-bottom:14px;font-size:12.5px;color:#92400e;}
.rv-pending ul{margin:6px 0 0;padding-left:18px;}
.rv-pending li,.rv-problems li{margin:3px 0;}
.rv-jump{color:inherit;text-decoration:underline dotted;text-underline-offset:3px;cursor:pointer;} .rv-jump:hover{text-decoration-style:solid;}
.rv-jump-ic{font-size:9px;opacity:.55;margin-left:4px;transform:rotate(90deg);}
.rv-flash{animation:rvFlash 1.6s ease;} @keyframes rvFlash{0%,60%{box-shadow:0 0 0 4px rgba(245,158,11,.45);border-radius:10px;}100%{box-shadow:none;}}
.rv-pending li{margin-bottom:3px;}
.rv-empty{padding:22px;text-align:center;color:#94a3b8;font-size:12.5px;}
.fcm-btn{padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;transition:all .15s;display:inline-flex;align-items:center;gap:6px;}
.fcm-btn-ghost{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;}
.fcm-btn-ghost:hover{background:#e5e7eb;}
.fcm-btn-save{background:#ea580c;color:#fff;}
.fcm-btn-save:hover{background:#c2410c;}
/* select2 multiple (the agents list): same box as the single selects, chips inside */
.fcm-box .select2-container--default .select2-selection--multiple{min-height:40px;border:1px solid #d1d5db;border-radius:10px;padding:4px 8px 0 8px;display:flex;flex-wrap:wrap;align-items:center;}
.fcm-box .select2-container--default .select2-selection--multiple .select2-selection__rendered{display:flex;flex-wrap:wrap;gap:4px;padding:0;margin:0;width:100%;align-items:center;}
.fcm-box .select2-container--default .select2-selection--multiple .select2-selection__choice{margin:0 0 4px;padding:2px 8px 2px 6px;border:1px solid #fed7aa;border-radius:999px;background:#fff7ed;color:#9a3412;font-size:12px;font-weight:600;line-height:1.4;display:inline-flex;align-items:center;gap:4px;}
.fcm-box .select2-container--default .select2-selection--multiple .select2-selection__choice__remove{border:0;background:none;color:#c2410c;font-size:13px;padding:0;margin:0;order:2;line-height:1;}
.fcm-box .select2-container--default .select2-selection--multiple .select2-selection__choice__display{padding:0;order:1;}
.fcm-box .select2-container--default.select2-container--disabled .select2-selection--single{background:#f1f5f9;color:#94a3b8;cursor:not-allowed;}
.fcm-box .select2-container--default.select2-container--disabled .select2-selection__rendered{color:#94a3b8;}
.fcm-box .select2-container--default .select2-search--inline{flex:1;min-width:90px;margin:0 0 4px;}
.fcm-box .select2-container--default .select2-search--inline .select2-search__field{margin:0;height:24px;font-size:13px;font-family:'DM Sans',sans-serif;}
.fcm-box .select2-container--default.select2-container--focus .select2-selection--multiple{border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,.1);}
.fcm-box .select2-selection__clear{color:#94a3b8;font-size:16px;margin-right:6px;}
.fcm-route-row{display:flex;gap:8px;align-items:flex-start;}
.fcm-route-row .fcm-select-wrap{flex:1;}
.fcm-add-route-btn{height:40px;padding:0 12px;border-radius:10px;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;flex-shrink:0;}
.fcm-add-route-btn:hover{background:#fed7aa;}
.fcm-readonly{background:#f1f5f9!important;color:#334155;font-weight:700;}
.fcm-positive{background:#ecfdf5!important;color:#15803d;font-weight:700;}
.fcm-help{font-size:11px;color:#64748b;margin-top:7px;}
.fcm-passenger-row{display:flex;gap:8px;align-items:flex-start;}
.fcm-passenger-row .select2-container{flex:1;}
.fcm-mini-add{height:40px;width:40px;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 40px;cursor:pointer;}
.handling-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.handling-card{position:relative;border:1px solid #dbe3ef;border-radius:8px;padding:13px 14px 13px 42px;cursor:pointer;background:#fff}.handling-card input{position:absolute;left:15px;top:17px}.handling-card:has(input:checked){border-color:#2563eb;background:#eff6ff}.handling-title{font-size:12px;font-weight:800;color:#374151;text-transform:uppercase}.handling-sub{font-size:10.5px;color:#64748b;margin-top:3px;line-height:1.35}@media(max-width:760px){.handling-grid,.fcm-grid-2,.fcm-grid-3,.fcm-grid-4{grid-template-columns:1fr}.fcm-box{max-width:100%}}

.fcm-box .select2-container .select2-selection--single{height:40px !important;border:1px solid #d1d5db;border-radius:10px;display:flex;align-items:center;}
.fcm-box .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:38px;padding-left:10px;font-size:13px;color:#1a2035;}
.fcm-box .select2-container--default .select2-selection--single .select2-selection__arrow{height:38px;}
.fcm-box .select2-container--default.select2-container--focus .select2-selection--single{border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,.1);}

/* Quick-create Ticket Route overlay (nested above create/edit modal) */
.tkr-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9500;display:none;align-items:center;justify-content:center;padding:1rem;}
.tkr-overlay.open{display:flex;}
.tkr-box{background:#fff;border-radius:14px;width:100%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;font-family:'DM Sans',sans-serif;max-height:90vh;overflow-y:auto;}
.tkr-head{padding:16px 20px;background:linear-gradient(135deg,#5b21b6,#7c3aed);display:flex;align-items:center;gap:10px;}
.tkr-head-title{font-size:14px;font-weight:700;color:#fff;}
.tkr-head-close{margin-left:auto;background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:6px;cursor:pointer;font-size:16px;}
.tkr-body{padding:18px 20px;}
.tkr-err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;padding:8px 12px;font-size:12px;margin-bottom:12px;display:none;}
.tkr-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.tkr-grid .tkr-full{grid-column:1/-1;}
.tkr-foot{padding:14px 20px;background:#f8fafc;border-top:1px solid #e4e8f0;display:flex;justify-content:flex-end;gap:8px;}
</style>

@php
    $flightStoreRoute = $flightStoreRoute ?? route('role.contract-flights.store', ['role' => $role]);
    $flightCreateTitle = $flightCreateTitle ?? 'Add Contract Flight';
    $flightCreateSub = $flightCreateSub ?? 'The flight first — then its passengers, then a read-back before saving';
@endphp

<div id="createModal" class="fcm-overlay hidden modal-backdrop">
    <div class="fcm-box">
        <div class="fcm-head">
            <div class="fcm-head-ic"><i class="fas fa-plane"></i></div>
            <div>
                <div class="fcm-title">{{ $flightCreateTitle }}</div>
                <div class="fcm-sub">{{ $flightCreateSub }}</div>
            </div>
            <button type="button" class="fcm-close modal-close-create">✕</button>
        </div>
        @include('contract-flights.flight-modal-steps', ['prefix' => 'create'])
        <div class="fcm-body">
            <form class="closest" id="createForm"
                action="{{ $flightStoreRoute }}"
                method="POST">
                @csrf

                {{-- ── Step 1: the flight itself — nothing here is mandatory ── --}}
                <div class="fm-pane active" data-pane="create-flight">

                    @include("contract-flights.flight-basics", ["prefix" => "create"])

                </div>{{-- /Step 1 --}}

                {{-- ── Step 2: the people, their agent, tickets, services and money ── --}}
                <div class="fm-pane" data-pane="create-passengers">
                    @include('contract-flights.passenger-builder', ['prefix' => 'create'])
                </div>

                {{-- ── Step 3: read it back, then confirm ── --}}
                <div class="fm-pane" data-pane="create-review">
                    @include('contract-flights.flight-review', ['prefix' => 'create'])
                </div>
            </form>
        </div>
        <div class="fcm-foot">
            <span class="fcm-foot-spacer" id="create_foot_hint"></span>
            <button type="button" class="fcm-btn fcm-btn-ghost modal-close-create">✕ Cancel</button>
            <button type="button" class="fcm-btn fcm-btn-step fm-back" data-prefix="create" style="display:none;"><i class="fas fa-arrow-left"></i> Back</button>
            <button type="button" class="fcm-btn fcm-btn-step fm-next" data-prefix="create">Next <i class="fas fa-arrow-right"></i></button>
            <button id="createSubmit" type="button" class="fcm-btn fcm-btn-save" style="display:none;"><i class="fas fa-save"></i> Save Flight</button>
        </div>
    </div>
</div>

{{-- Passenger-card styles and the <template> both builders clone from.
     Included here rather than in the builder partial itself because both the
     create and edit modals render a builder, and the template may only exist
     once per page. Every screen that shows the edit modal also shows this
     one, so this is the single reliable place for it. --}}
@include('contract-flights.passenger-builder-assets')

{{-- ══════════════════════════════════════════
     QUICK-CREATE TICKET ROUTE (shared by create & edit modals)
     Reuses the same fields/endpoint as Tickets → Add New Ticket
══════════════════════════════════════════ --}}
<div class="tkr-overlay" id="flightPassengerOverlay">
    <div class="tkr-box">
        <div class="tkr-head" style="background:#2563eb">
            <span class="tkr-head-title"><i class="fas fa-passport"></i> Add Passport Holder</span>
            <button type="button" class="tkr-head-close" onclick="closeFlightPassengerModal()">&times;</button>
        </div>
        <div class="tkr-body">
            <div class="tkr-err" id="flightPassengerError"></div>
            <div class="tkr-grid">
                <div><label class="fcm-lbl">Passenger Name *</label><input id="flightPassengerName" class="fcm-input" placeholder="Full name as passport"></div>
                <div><label class="fcm-lbl">Passport No <span style="font-weight:400;color:#94a3b8">— optional</span></label><input id="flightPassengerPassport" class="fcm-input" placeholder="e.g. EB0912345"></div>
                <div><label class="fcm-lbl">Nationality *</label><input id="flightPassengerNationality" class="fcm-input" value="Bangladeshi" placeholder="e.g. Bangladeshi"></div>
                <div><label class="fcm-lbl">Phone <span style="font-weight:400;color:#94a3b8;">— optional</span></label><input id="flightPassengerPhone" class="fcm-input" placeholder="+880 1XXX-XXXXXX"></div>
                <div class="tkr-full">
                    <label class="fcm-lbl">Passport Holder Category *</label>
                    <select id="flightPassengerCategory" class="fcm-select">
                        <option value="">Select category</option>
                        @foreach($passportHolderCategories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                    </select>
                </div>
                <div class="tkr-full">
                    <label class="fcm-lbl">Type *</label>
                    <select id="flightPassengerType" class="fcm-select">
                        @include('passport-holder.type-options')
                    </select>
                </div>
            </div>
        </div>
        <div class="tkr-foot">
            <button type="button" class="fcm-btn fcm-btn-ghost" onclick="closeFlightPassengerModal()">Cancel</button>
            <button type="button" id="flightPassengerSave" class="fcm-btn fcm-btn-save" onclick="saveFlightPassenger()"><i class="fas fa-save"></i> Save Passenger</button>
        </div>
    </div>
</div>

<div class="tkr-overlay" id="tkrOverlay">
    <div class="tkr-box">
        <div class="tkr-head">
            <span style="font-size:18px;">✈</span>
            <span class="tkr-head-title">Add New Ticket Route</span>
            <button type="button" class="tkr-head-close" onclick="closeTicketRouteModal()">✕</button>
        </div>
        <div class="tkr-body">
            <div class="tkr-err" id="tkrErr"></div>
            <div class="tkr-grid">
                <div class="tkr-full">
                    <label class="fcm-lbl">Ticket Title <span class="req">*</span></label>
                    <input type="text" id="tkrTitle" class="fcm-input" placeholder="e.g. Dhaka to Jeddah">
                </div>
                <div>
                    <label class="fcm-lbl">From Airport</label>
                    <select id="tkrFromAirport" class="fcm-select" style="width:100%">
                        <option value="">— Select airport —</option>
                        @foreach($airports as $airport)
                            <option value="{{ $airport->id }}">{{ $airport->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fcm-lbl">To Airport</label>
                    <select id="tkrToAirport" class="fcm-select" style="width:100%">
                        <option value="">— Select airport —</option>
                        @foreach($airports as $airport)
                            <option value="{{ $airport->id }}">{{ $airport->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fcm-lbl">Airline <span class="req">*</span></label>
                    <select id="tkrAirline" class="fcm-select" style="width:100%">
                        <option value="">— Select airline —</option>
                        @foreach($airlines as $airline)
                            <option value="{{ $airline->id }}">{{ $airline->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fcm-lbl">Vendor <span class="text-gray-400 font-normal" title="At least one of vendor or portal is required">*</span></label>
                    <select id="tkrVendor" class="fcm-select" style="width:100%">
                        <option value="">— Select vendor —</option>
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fcm-lbl">Portal <span class="text-gray-400 font-normal" title="At least one of vendor or portal is required">*</span></label>
                    <select id="tkrPortal" class="fcm-select" style="width:100%">
                        <option value="">— Select portal —</option>
                        @foreach($portals as $portal)
                            <option value="{{ $portal->id }}">{{ $portal->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fcm-lbl">Default Cost Price</label>
                    <input type="number" id="tkrPrice" min="0" step="0.01" class="fcm-input" value="0" placeholder="0.00">
                </div>
                <div>
                    <label class="fcm-lbl">Total Stock Quantity</label>
                    <input type="number" id="tkrQty" min="0" class="fcm-input" value="0" placeholder="0">
                </div>
            </div>
        </div>
        <div class="tkr-foot">
            <button type="button" class="fcm-btn fcm-btn-ghost" onclick="closeTicketRouteModal()">✕ Cancel</button>
            <button type="button" class="fcm-btn" style="background:#7c3aed;color:#fff;" id="tkrSaveBtn" onclick="submitTicketRouteModal()">💾 Save Route</button>
        </div>
    </div>
</div>
