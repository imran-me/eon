{{--
    Ticket Orders → Change (2026-10-06): Re-issue / Reschedule / Refund / Void
    on one ticket. Server: TicketChangeController. A contract-flight ticket is
    posted by the flight's own Re-Issue / Refund, so the dialog says so and
    shows what THAT will post. Nothing posts until Confirm & post; one token
    per open dialog, so a double click can never post twice.
    Owner, same day: "fantastic … specially the What will be posted area";
    then: the modal one fixed, near-full-window size for every tab, and
    "an option to choose, for each cost, who bears it".
    Expects: $role.
--}}
@php
    $tcxUrls = [
        'context' => route('role.ticket-changes.context', ['role' => $role, 'ticketPurchase' => '__ID__']),
        'store' => route('role.ticket-changes.store', ['role' => $role, 'ticketPurchase' => '__ID__']),
        'correctRefund' => route('role.ticket-changes.correct-refund', ['role' => $role, 'ticketPurchase' => '__ID__']),
        // Cancel return (2026-10-14): the airline's window, saved on its own
        'returnWindow' => \App\Services\ReturnCancel::ready() && \Illuminate\Support\Facades\Route::has('role.ticket-changes.return-window')
            ? route('role.ticket-changes.return-window', ['role' => $role, 'ticketPurchase' => '__ID__']) : null,
    ];
@endphp
<style>
/* One fixed frame for every tab: near the full window (the owner's red box
   at 90% zoom on 1365 = ~1275 CSS px), the inside scrolls if it must. */
/* One border colour everywhere — Ticket Entry's field border (owner, 2026-10-06:
   "same border color that we are using other where"). */
.tcx-pop{--tcx-line:#d2d8e7;}
.tcx-pop.swal2-popup{width:min(1280px, calc(100vw - 32px))!important;height:calc(100vh - 24px)!important;max-height:none!important;display:flex!important;flex-direction:column;padding:0 0 16px!important;border-radius:16px!important;overflow:hidden;}
.tcx-pop .swal2-close{position:absolute;top:12px;right:14px;margin:0;z-index:3;}
.tcx-pop .swal2-title{flex:none;font-size:17px!important;padding:18px 24px 0!important;text-align:left!important;color:#0f172a;}
.tcx-pop .swal2-html-container{flex:1 1 auto;min-height:0;overflow:hidden!important;margin:12px 0 0!important;padding:0 24px!important;text-align:left!important;display:flex!important;flex-direction:column;}
.tcx-pop .swal2-validation-message{flex:none;margin:10px 24px 0!important;}
.tcx-pop .swal2-actions{flex:none;margin-top:14px!important;}
.tcx-wrap{display:flex;flex-direction:column;gap:12px;flex:1 1 auto;min-height:0;}
.tcx-head{flex:none;display:flex;flex-wrap:wrap;gap:6px 20px;font-size:12px;color:#475569;padding:10px 14px;border:1px solid var(--tcx-line);border-radius:10px;background:#f8fafc;}
.tcx-head b{color:#0f172a;font-weight:600;}
.tcx-head .tcx-pnr{font-family:ui-monospace,Menlo,Consolas,monospace;font-weight:700;color:#4338ca;}
.tcx-cf{flex:none;font-size:12px;color:#3730a3;background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:8px 14px;}
.tcx-seg{flex:none;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
.tcx-seg button{border:1px solid var(--tcx-line);background:#fff;border-radius:10px;padding:9px 6px;font-size:12.5px;font-weight:600;color:#475569;display:flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;}
.tcx-seg button:hover{border-color:#a5b4fc;color:#4338ca;}
.tcx-seg button.on{border-color:#4f46e5;background:#eef2ff;color:#3730a3;box-shadow:0 0 0 1px #4f46e5 inset;}
.tcx-seg button[data-k="refund"].on,.tcx-seg button[data-k="void"].on{border-color:#dc2626;background:#fef2f2;color:#b91c1c;box-shadow:0 0 0 1px #dc2626 inset;}
.tcx-body{flex:1 1 auto;min-height:0;display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr);gap:20px;}
.tcx-left{min-height:0;overflow:auto;padding:2px 6px 4px 2px;display:flex;flex-direction:column;gap:14px;}
.tcx-sec{display:flex;justify-content:space-between;gap:12px;font-size:10.5px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.06em;margin-bottom:-4px;}
.tcx-sec span{text-transform:none;letter-spacing:0;font-weight:600;color:#0f766e;font-size:11.5px;}
.tcx-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.tcx-f{display:flex;flex-direction:column;gap:5px;min-width:0;}
.tcx-f label{font-size:10.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.03em;}
.tcx-in,.tcx-f textarea{width:100%;box-sizing:border-box;height:38px;border:1px solid var(--tcx-line);border-radius:8px;padding:6px 10px;font-size:13px;color:#0f172a;background:#fff;}
.tcx-f textarea{height:52px;resize:vertical;}
.tcx-f textarea.tcx-note1{height:38px;}
.tcx-in:focus,.tcx-f textarea:focus{outline:none;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
.tcx-in[readonly]{background:#f8fafc;color:#64748b;}
.tcx-in:disabled{background:#f1f5f9;color:#94a3b8;}
.tcx-in.tcx-red{color:#dc2626;font-weight:700;}
.tcx-money{text-align:right;}
.tcx-hint{font-size:11px;color:#94a3b8;}
/* A cost = one row: what it is · the amount · who bears it */
.tcx-costs{display:flex;flex-direction:column;gap:10px;}
.tcx-cost{display:grid;grid-template-columns:minmax(0,1fr) 150px minmax(0,1.35fr);gap:12px;align-items:center;padding:8px 12px;border:1px solid var(--tcx-line);border-radius:10px;background:#fff;}
.tcx-cost-t{display:block;font-size:12.5px;color:#0f172a;}
.tcx-cost-s{display:block;font-size:11px;color:#94a3b8;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tcx-pay{display:flex;gap:6px;min-width:0;}
.tcx-pay button{flex:1;min-width:0;border:1px solid var(--tcx-line);background:#fff;border-radius:999px;padding:8px 10px;font-size:12px;font-weight:600;color:#475569;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tcx-pay button.on[data-b="customer"]{background:#0f766e;border-color:#0f766e;color:#fff;}
.tcx-pay button.on[data-b="company"]{background:#334155;border-color:#334155;color:#fff;}
.tcx-pay button:disabled{opacity:.45;cursor:not-allowed;}
.tcx-fixed{font-size:12px;color:#0f766e;font-weight:600;padding:8px 12px;border:1px solid var(--tcx-line);border-radius:999px;background:#f0fdfa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:center;}
.tcx-fixed.mute{color:#94a3b8;background:#f8fafc;}
.tcx-info{font-size:12.5px;color:#334155;padding:10px 12px;border:1px solid var(--tcx-line);border-radius:10px;background:#f8fafc;}
.tcx-post{min-height:0;overflow:auto;display:flex;flex-direction:column;border:1px solid var(--tcx-line);border-radius:12px;padding:14px 16px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04);}
.tcx-post h4{margin:0 0 10px;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#334155;}
.tcx-post ol{margin:0;padding-left:18px;display:flex;flex-direction:column;gap:9px;font-size:12.5px;color:#334155;line-height:1.4;}
.tcx-acc{display:block;color:#94a3b8;font-size:11px;}
.tcx-post .tcx-r{color:#dc2626;font-weight:700;}
.tcx-post .tcx-g{color:#059669;font-weight:700;}
.tcx-none{color:#94a3b8;font-size:12px;}
.tcx-sum{margin-top:auto;padding-top:12px;border-top:1px dashed var(--tcx-line);display:grid;grid-template-columns:1fr auto;gap:5px 12px;font-size:12px;color:#475569;}
.tcx-sum b{text-align:right;color:#0f172a;font-weight:700;}
.tcx-sum b.r{color:#dc2626;} .tcx-sum b.g{color:#059669;}
.tcx-hist{font-size:11.5px;color:#64748b;}
.tcx-hist div{padding:4px 0;border-top:1px dashed var(--tcx-line);}
/* All history (2026-10-26): a coloured edge per kind — red cancel, amber correction, green money */
.tcx-hist .tcx-hi{padding:5px 0 5px 9px;border-left:3px solid #cbd5e1;margin-top:2px;}
.tcx-hist .tcx-hi-ok{border-left-color:#10b981;} .tcx-hist .tcx-hi-bad{border-left-color:#ef4444;}
.tcx-hist .tcx-hi-warn{border-left-color:#f59e0b;} .tcx-hist .tcx-hi-info{border-left-color:#6366f1;}
.tcx-hist .tcx-hi b{color:#0f172a;font-weight:600;}
.tcx-hi-at{color:#334155;white-space:nowrap;} .tcx-hi-by{color:#94a3b8;}
/* What will be posted + All Trails and Edit, top-right (owner's screenshot, 2026-10-26) */
.tcx-posthead{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;}
.tcx-posthead h4{margin:0!important;}
.tcx-trailbtn{flex:none;display:inline-flex;align-items:center;gap:6px;border:1px solid #c7d2fe;background:#eef2ff;color:#3730a3;border-radius:8px;padding:6px 11px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;}
.tcx-trailbtn:hover{background:#e0e7ff;border-color:#818cf8;}
.tcx-ok.swal2-confirm{background:#16a34a!important;box-shadow:0 6px 16px rgba(22,163,74,.35)!important;border-radius:10px!important;font-weight:700!important;animation:tcxPulse 1.8s ease-in-out infinite;}
@keyframes tcxPulse{0%,100%{box-shadow:0 6px 16px rgba(22,163,74,.35);}50%{box-shadow:0 6px 22px rgba(22,163,74,.6);}}
/* Cancel return (2026-10-14) */
.tcx-win{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:12px;align-items:end;}
.tcx-tick{display:flex;align-items:center;gap:7px;height:38px;font-size:12.5px;color:#334155;cursor:pointer;white-space:nowrap;}
.tcx-wbtn{height:38px;border:1px solid #99f6e4;background:#f0fdfa;color:#0f766e;border-radius:8px;padding:0 14px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;}
.tcx-wbtn:disabled{opacity:.5;cursor:wait;}
.tcx-sec .tcx-wt.ok{color:#0f766e;} .tcx-sec .tcx-wt.soon{color:#b45309;} .tcx-sec .tcx-wt.late{color:#b91c1c;} .tcx-sec .tcx-wt.none{color:#94a3b8;}
.tcx-info.tcx-late{border-color:#fecaca;background:#fef2f2;color:#991b1b;}
.tcx-mode{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;}
#tcxLeft.tcx-rc{gap:11px;}   /* this tab only: fits 1365×718 without scrolling */
.tcx-mode button{border:1px solid var(--tcx-line);background:#fff;border-radius:999px;padding:8px 10px;font-size:12px;font-weight:600;color:#475569;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tcx-mode button.on{background:#0f766e;border-color:#0f766e;color:#fff;}
.tcx-mode button:disabled{opacity:.45;cursor:not-allowed;}
.tcx-corr-pop.swal2-popup{width:min(720px,calc(100vw - 28px))!important;padding:0!important;border-radius:15px!important;overflow:hidden;text-align:left!important;}
.tcx-corr-pop .swal2-html-container{margin:0!important;padding:0!important;text-align:left!important;overflow:visible!important;}
.tcx-corr-pop .swal2-close{top:12px;right:14px;color:#64748b;}
.tcx-corr-pop .swal2-actions{width:100%;box-sizing:border-box;justify-content:flex-end;margin:0!important;padding:14px 22px;background:#f8fafc;border-top:1px solid #e2e8f0;}
.tcx-corr-pop .swal2-confirm,.tcx-corr-pop .swal2-cancel{font-size:12px!important;font-weight:800!important;border-radius:8px!important;padding:10px 16px!important;}
.tcx-corr{color:#334155;font-size:12px;}
.tcx-corr-head{display:flex;align-items:center;gap:12px;padding:20px 24px 15px;border-bottom:1px solid #e2e8f0;background:linear-gradient(135deg,#f8fbff,#fff);}
.tcx-corr-icon{width:42px;height:42px;display:grid;place-items:center;flex:none;border-radius:11px;background:#dbeafe;color:#1677e8;font-size:17px;}
.tcx-corr-kicker{font-size:9px;font-weight:900;letter-spacing:.13em;text-transform:uppercase;color:#1677e8;}
.tcx-corr-head h3{font-size:19px;line-height:1.15;color:#0f172a;margin:3px 0 2px;font-weight:800;}
.tcx-corr-head p{margin:0;color:#64748b;font-size:11px;}
.tcx-corr-body{padding:16px 24px 20px;display:flex;flex-direction:column;gap:12px;}
.tcx-corr-alert{display:flex;gap:9px;padding:10px 12px;border:1px solid #bfdbfe;border-radius:9px;background:#eff6ff;color:#1e40af;font-size:11px;line-height:1.45;}
.tcx-corr-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
.tcx-corr-card{border:1px solid #dbe4ef;border-radius:9px;background:#f8fafc;padding:9px 11px;min-width:0;}
.tcx-corr-card span{display:block;font-size:8px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin-bottom:3px;}
.tcx-corr-card b{font-size:13px;color:#0f172a;white-space:nowrap;}
.tcx-corr-panel{border:1px solid #dbe4ef;border-radius:11px;padding:13px;background:#fff;}
.tcx-corr-grid{display:grid;grid-template-columns:1fr 1fr;gap:11px;}
.tcx-corr-field label{display:flex;justify-content:space-between;gap:8px;font-size:9px;font-weight:900;text-transform:uppercase;color:#334155;margin:0 0 5px;}
.tcx-corr-field label small{font-size:8px;font-weight:600;color:#94a3b8;text-transform:none;}
.tcx-corr-field input,.tcx-corr-field select,.tcx-corr-field textarea{display:block;width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;padding:9px 10px;font-size:12px;outline:none;box-shadow:none;}
.tcx-corr-field input:focus,.tcx-corr-field select:focus,.tcx-corr-field textarea:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1);}
.tcx-corr-field input[readonly]{background:#f1f5f9;color:#475569;font-weight:700;}
.tcx-corr-field textarea{min-height:68px;resize:vertical;}
.tcx-corr-reason{border-color:#fed7aa;background:#fffaf0;}
.tcx-corr-confirm{border:1px dashed #fb923c;border-radius:9px;padding:10px;background:#fff7ed;}
.tcx-corr-confirm input{border-color:#fdba74;font-weight:800;letter-spacing:.04em;}
@media(max-width:620px){.tcx-corr-cards,.tcx-corr-grid{grid-template-columns:1fr}.tcx-corr-body{padding:14px}.tcx-corr-head{padding:18px 16px}.tcx-corr-pop .swal2-actions{padding:12px 14px}}
@media (max-width:860px){.tcx-body{grid-template-columns:1fr;overflow:auto;}.tcx-left{overflow:visible;}.tcx-cost{grid-template-columns:1fr 130px;}.tcx-cost .tcx-pay,.tcx-cost .tcx-fixed{grid-column:1 / -1;}}
</style>
<script>
(function () {
    const URLS = @json($tcxUrls);
    // Re-issue and Reschedule are one tab (owner, 2026-10-07): the server kind is picked on send —
    // a seller charge makes it a re-issue, a date move alone a reschedule.
    const KINDS = { reissue: ['Re-issue / Reschedule', 'fa-arrows-rotate'], refund: ['Refund', 'fa-rotate-left'], void: ['Void', 'fa-ban'] };
    const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const tk = n => '৳' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // "12 Sep 2026, 22:00" — the ERP's way, in every browser locale.
    const day = s => {
        const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
        if (!m) return s ? String(s) : '—';
        const t = m[4] ? m[4] + ':' + m[5] : '00:00';
        return m[3] + ' ' + MON[+m[2] - 1] + ' ' + m[1] + (t !== '00:00' ? ', ' + t : '');
    };
    // UUID without crypto.randomUUID — the local .test site is plain HTTP.
    const token = () => { const a = new Uint8Array(16); (window.crypto || window.msCrypto).getRandomValues(a); return Array.from(a, b => b.toString(16).padStart(2, '0')).join(''); };
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-ticket-change]');
        if (!btn) return;
        e.preventDefault();
        open(btn.getAttribute('data-ticket-change'));
    });

    function open(id) {
        Swal.fire({ title: 'Loading ticket…', didOpen: () => Swal.showLoading(), allowOutsideClick: false, showConfirmButton: false });
        fetch(URLS.context.replace('__ID__', id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(r => r.json()).then(ctx => {
                if (!ctx.success) throw new Error(ctx.message || 'Could not load the ticket.');
                // a refunded / voided ticket opens on its Refund tab: history + a new ticket (2026-10-07)
                if (ctx.ticket.status !== 'confirm' && ctx.ticket.status !== 'cancelled') throw new Error('Only a purchased ticket can be changed.');
                if (ctx.cf && !ctx.cf.can) throw new Error('This ticket belongs to a contract flight passenger — changing it needs the "edit contract flight" permission.');
                dialog(id, ctx);
            })
            .catch(err => Swal.fire({ icon: 'error', title: 'Cannot change this ticket', text: err.message }));
    }

    function correctRefund(id, ctx) {
        const c = ctx.refund_correction;
        if (!c) return;
        Swal.fire({
            title: '',
            customClass: { popup: 'tcx-corr-pop', confirmButton: 'tcx-ok' },
            showCloseButton: true,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check"></i> Save correction',
            cancelButtonText: 'Cancel',
            allowOutsideClick: false,
            html: `<div class="tcx-corr">
                <div class="tcx-corr-head"><div class="tcx-corr-icon"><i class="fas fa-receipt"></i></div><div><div class="tcx-corr-kicker">Ticket refund correction</div><h3>Correct refund posting</h3><p>${esc(ctx.ticket.pnr)} · ${esc(ctx.ticket.passenger)} · Agent ${esc(c.agent)}</p></div></div>
                <div class="tcx-corr-body">
                    <div class="tcx-corr-alert"><i class="fas fa-circle-info"></i><span>The original posting stays in audit history. Only balanced correction entries will be added to the agent ledger and journal.</span></div>
                    <div class="tcx-corr-cards"><div class="tcx-corr-card"><span>Airline refund</span><b>${tk(c.airline_refund)}</b></div><div class="tcx-corr-card"><span>Airline kept</span><b style="color:#dc2626">${tk(c.airline_penalty)}</b></div><div class="tcx-corr-card"><span>Original agent price</span><b>${tk(c.agent_sale_price)}</b></div></div>
                    <div class="tcx-corr-panel"><div class="tcx-corr-grid">
                        <div class="tcx-corr-field"><label for="tcxCorrRefund">Refund back to agent *</label><input id="tcxCorrRefund" type="number" min="0" max="${c.agent_sale_price}" step="0.01" value="${Number(c.agent_refund).toFixed(2)}"></div>
                        <div class="tcx-corr-field"><label>Airline penalty treatment</label><input value="Already included in the reduced refund" readonly></div>
                        <div class="tcx-corr-field"><label>Airline penalty <small>Cost − airline refund; not charged again</small></label><input value="${Number(c.airline_penalty).toFixed(2)}" readonly></div>
                        <div class="tcx-corr-field"><label for="tcxCorrFee">Our service charge <small>0 if none</small></label><input id="tcxCorrFee" type="number" min="0" step="0.01" value="${Number(c.service_fee).toFixed(2)}"></div>
                    </div></div>
                    <div class="tcx-corr-panel tcx-corr-reason"><div class="tcx-corr-field"><label for="tcxCorrReason">Correction reason * <small>Saved permanently in history</small></label><textarea id="tcxCorrReason" maxlength="500" placeholder="Explain why these refund amounts must change"></textarea></div></div>
                    <div class="tcx-corr-confirm"><div class="tcx-corr-field"><label for="tcxCorrConfirm">Final confirmation * <small>Type CONFIRM</small></label><input id="tcxCorrConfirm" autocomplete="off" placeholder="Type CONFIRM to continue"></div></div>
                </div></div>`,
            preConfirm: () => {
                const refund = Number(document.getElementById('tcxCorrRefund').value);
                const fee = Number(document.getElementById('tcxCorrFee').value);
                const reason = document.getElementById('tcxCorrReason').value.trim();
                const confirm = document.getElementById('tcxCorrConfirm').value.trim();
                if (!Number.isFinite(refund) || refund < 0 || refund > Number(c.agent_sale_price)) return Swal.showValidationMessage('Agent refund must be between 0 and ' + tk(c.agent_sale_price) + '.');
                if (!Number.isFinite(fee) || fee < 0) return Swal.showValidationMessage('Service charge cannot be negative.');
                if (reason.length < 5) return Swal.showValidationMessage('Type a correction reason of at least 5 characters.');
                if (confirm !== 'CONFIRM') return Swal.showValidationMessage('Type CONFIRM exactly to continue.');
                const body = new URLSearchParams({ agent_refund: refund.toFixed(2),
                    service_fee: fee.toFixed(2), reason, confirm, token: token() });
                return fetch(URLS.correctRefund.replace('__ID__', id), { method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/x-www-form-urlencoded' }, body })
                    .then(r => r.json().then(j => ({ ok: r.ok, j }))).then(({ ok, j }) => {
                        if (!ok || !j.success) { Swal.showValidationMessage(j.message || Object.values(j.errors || {})[0]?.[0] || 'Correction failed.'); return false; }
                        return j;
                    }).catch(() => { Swal.showValidationMessage('Network error — nothing was posted.'); return false; });
            },
        }).then(res => {
            if (!res.isConfirmed || !res.value) return;
            Swal.fire({ icon: 'success', title: 'Refund corrected', text: res.value.message, confirmButtonText: 'OK' }).then(() => window.location.reload());
        });
    }

    function dialog(id, ctx) {
        const t = ctx.ticket, cf = ctx.cf, sale = ctx.sale;
        // who[part] = 'customer' | 'company' | null — never pre-selected (owner).
        // A refunded / voided ticket (owner, 2026-10-07): only its Refund tab, showing the refund
        // history and the new ticket the customer buys instead ("newticket").
        const after = t.status === 'cancelled';
        const madeNew = (ctx.history || []).some(h => h.code === 'newticket');
        // Cancel return (2026-10-14): its own tab once installed — a contract flight
        // passenger only when his category is a family one. A return ticket cancelled that
        // way opens on it, and is never "refunded → new ticket".
        const retDone = !!(ctx.ret && ctx.ret.done);
        const showRet = !!(ctx.ret && (!cf || ctx.ret.family) && (!after || retDone));
        const KIND_TABS = Object.assign({}, KINDS, showRet ? { return_cancel: ['Cancel return', 'fa-plane-arrival'] } : {});
        const canNew = after && ctx.can_new && !ctx.was_cf && !madeNew && !retDone;
        const st = { kind: after ? (retDone ? 'return_cancel' : 'newticket') : 'reissue', who: { penalty: null, fare: null, kept: null }, mode: 'keep', token: token() };
        const isPortal = /\(portal\)/.test(t.seller || '');
        const payerName = cf ? (cf.agent || 'Agent') : (sale ? sale.client : null);
        const canBill = cf ? !!cf.agent : !!sale;
        const curAt = t.travel_at ? String(t.travel_at).slice(0, 16).replace(' ', 'T') : '';

        const head = `<div class="tcx-head">
                <span><span class="tcx-pnr">${esc(t.pnr)}</span></span>
                <span><b>${esc(t.passenger || '—')}</b>${t.passport ? ' · ' + esc(t.passport) : ''}</span>
                <span>${esc(t.route || '—')}</span>
                <span>Flies <b>${esc(day(t.travel_at))}</b></span>
                <span>Cost <b>${tk(t.amount)}</b>${t.due > 0 ? ' · <span style="color:#dc2626">due ' + tk(t.due) + '</span>' : ' · paid'}</span>
                <span>Seller <b>${esc(t.seller || '—')}</b></span>
                ${sale ? `<span>Sold to <b>${esc(sale.client)}</b> · ${tk(sale.price)}</span>` : (cf ? '' : '<span style="color:#94a3b8">not sold</span>')}
            </div>
            ${cf ? `<div class="tcx-cf"><i class="fas fa-plane-departure"></i> Contract flight <b>${esc(cf.flight || 'off flight')}</b>${cf.flight_date ? ' · ' + esc(cf.flight_date) : ''}${cf.agent ? ' · agent <b>' + esc(cf.agent) + '</b>' : ''}.
                Posted by the flight's own Re-Issue / Refund — the same rows as from his card, never twice. He stays on his flight; <a href="${esc(cf.url)}" style="color:#4338ca;font-weight:700">Re-assign</a> moves him to another.</div>` : ''}`;
        const tabOn = k => after ? (retDone ? k === 'return_cancel' : k === 'refund') : true;
        const seg = `<div class="tcx-seg" style="grid-template-columns:repeat(${Object.keys(KIND_TABS).length},1fr)">${Object.entries(KIND_TABS).map(([k, [l, ic]]) => `<button type="button" data-k="${k}" ${!tabOn(k) ? 'disabled title="This ticket is already ' + (retDone ? 'cancelled' : 'refunded') + '"' : ''}><i class="fas ${ic}"></i>${l}</button>`).join('')}</div>`;
        // History sits on the right, under what will be posted — each tab shows its own kinds
        // (owner, 2026-10-07: "multiple reissue can happen").
        const TAB_CODES = { reissue: ['reissue', 'reschedule'], refund: ['refund', 'void', 'refund_correction', 'newticket'], newticket: ['refund', 'void', 'refund_correction', 'newticket'], void: ['refund', 'void', 'refund_correction', 'newticket'], return_cancel: ['return_cancel'] };
        const histFor = k => {
            const rows = (ctx.history || []).filter(h => (TAB_CODES[k] || []).includes(h.code));
            return `<div class="tcx-hist"><div class="tcx-sec" style="margin-bottom:4px">History${rows.length ? ` <span style="color:#64748b">${rows.length}</span>` : ''}</div>`
                + (rows.length ? rows.map(h => `<div>${esc(h.when)} · <b>${esc(h.kind)}</b> — ${esc(h.note || '')}${h.by ? ' · ' + esc(h.by) : ''}</div>`).join('') : '<div class="tcx-none" style="border:0">None yet.</div>') + '</div>';
        };
        // All history of the ticket, newest first (owner, 2026-10-26): entered, sold, paid,
        // every change, every edit and correction — not only this tab's kinds.
        const histAll = () => {
            const rows = ctx.timeline || [];
            return `<div class="tcx-hist"><div class="tcx-sec" style="margin-bottom:4px">History${rows.length ? ` <span style="color:#64748b">${rows.length}</span>` : ''}</div>`
                + (rows.length ? rows.map(h => `<div class="tcx-hi tcx-hi-${esc(h.tone)}"><span class="tcx-hi-at">${esc(day(h.at))}</span> · <b>${esc(h.title)}</b>`
                    + `${h.lines && h.lines.length ? ' — ' + esc(h.lines.join(' · ')) : ''}${h.by ? ' <span class="tcx-hi-by">· ' + esc(h.by) + '</span>' : ''}</div>`).join('')
                    : '<div class="tcx-none" style="border:0">None yet.</div>') + '</div>';
        };

        Swal.fire({
            title: (after ? (retDone ? 'Cancelled return ' : 'Refunded ticket ') : 'Change ticket ') + t.pnr,
            customClass: { popup: 'tcx-pop', confirmButton: 'tcx-ok' },
            html: `<div class="tcx-wrap">${head}${seg}<div class="tcx-body"><div class="tcx-left" id="tcxLeft"></div><div class="tcx-post" id="tcxPost"></div></div></div>`,
            showCloseButton: true,
            showCancelButton: true,
            confirmButtonText: after ? '<i class="fas fa-check"></i> Make new ticket & post' : '<i class="fas fa-check"></i> Confirm & post',
            showConfirmButton: !after || canNew,
            cancelButtonText: after && !canNew ? 'Close' : 'Cancel',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: (popup) => {
                popup.querySelectorAll('.tcx-seg button').forEach(b => b.addEventListener('click', () => { if (b.disabled || after) return; st.kind = b.dataset.k; render(popup); }));
                render(popup);
            },
            preConfirm: () => {
                const p = document.querySelector('.tcx-pop');
                const v = read(p);
                const err = check(v);
                if (err) { Swal.showValidationMessage(err); return false; }
                const isRefund = st.kind === 'refund' || st.kind === 'void';
                const body = new URLSearchParams();
                const fx = figures(v);
                Object.entries(st.kind === 'return_cancel' ? {
                    kind: 'return_cancel', token: st.token, note: v.note, refund: v.refund ?? '', penalty: fx.round ? (v.retPen ?? 0) : '',
                    penalty_bearer: fx.kept > 0 ? (st.who.kept || '') : '', service_fee: v.fee ?? '',
                    agent_mode: st.mode, agent_value: st.mode === 'keep' ? '' : (v.aval ?? ''),
                } : st.kind === 'newticket' ? {
                    kind: 'newticket', token: st.token, note: v.note, new_pnr: v.pnr, new_cost: v.newCost ?? '', sale_price: v.price ?? '',
                    new_travel_at: v.newAt || '', fare_bearer: figures(v).fare > 0 ? (st.who.fare || '') : '', service_fee: v.fee ?? '',
                } : {
                    kind: st.kind === 'reissue' && figures(v).seller <= 0 ? 'reschedule' : st.kind, token: st.token, note: v.note,
                    penalty: isRefund ? '' : (v.penalty ?? ''), fare_difference: isRefund ? '' : (v.fare ?? ''),
                    service_fee: v.fee ?? '', refund: v.refund ?? '', new_travel_at: v.newAt || '',
                    agent_reversal: st.kind === 'void' && sale && !cf ? (v.agent ?? '') : '',
                    penalty_bearer: (isRefund ? st.who.kept : st.who.penalty) || '', fare_bearer: isRefund ? '' : (st.who.fare || ''),
                }).forEach(([k, x]) => body.append(k, x ?? ''));
                return fetch(URLS.store.replace('__ID__', id), { method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/x-www-form-urlencoded' }, body })
                    .then(r => r.json().then(j => ({ ok: r.ok, j })))
                    .then(({ ok, j }) => {
                        if (!ok || !j.success) { Swal.showValidationMessage(j.message || Object.values(j.errors || {})[0]?.[0] || 'Something went wrong.'); return false; }
                        return j;
                    })
                    .catch(() => { Swal.showValidationMessage('Network error — nothing was posted. Try again.'); return false; });
            },
        }).then(res => {
            if (!res.isConfirmed || !res.value) return;
            const done = () => window.location.reload();
            if (res.value.notice) Swal.fire({ icon: 'success', title: 'Saved and posted', html: res.value.notice, confirmButtonText: 'OK' }).then(done);
            else Swal.fire({ icon: 'success', title: res.value.message || 'Saved and posted', timer: 1800, showConfirmButton: false }).then(done);
        });

        // ── building blocks ──
        function money(id, opts = {}) {
            return `<input id="${id}" class="tcx-in tcx-money ${opts.red ? 'tcx-red' : ''}" type="number" min="0" step="0.01" inputmode="decimal" ${opts.ro ? 'readonly' : ''} ${opts.off ? 'disabled' : ''} value="${opts.value ?? ''}">`;
        }
        function pay(part) {
            return `<div class="tcx-pay" data-part="${part}">
                <button type="button" data-b="customer" ${canBill ? `title="${esc(payerName)} pays"` : 'disabled title="Not sold to anyone — nobody to bill"'}>Agent pays</button>
                <button type="button" data-b="company">We bear it</button></div>`;
        }
        function cost(part, title, sub, input, right) {
            return `<div class="tcx-cost" data-cost="${part}"><div><span class="tcx-cost-t">${title}</span><span class="tcx-cost-s" title="${esc(sub)}">${esc(sub)}</span></div>${input}${right}</div>`;
        }
        // Our charge — beside the penalty on every tab, contract flight tickets
        // too (owner, 2026-10-13): always billed to the agent, our profit.
        function feeRow() {
            return cost('fee', 'Our charge', 'Optional — our profit', money('tcxFee', { off: !canBill }),
                canBill ? `<div class="tcx-fixed" title="Billed to ${esc(payerName)}, on the same line">Always billed to the agent</div>`
                    : `<div class="tcx-fixed mute">${cf ? 'No agent — nobody to bill' : 'Not sold — nobody to bill'}</div>`);
        }
        const secHead = () => `<div class="tcx-sec">Costs — who bears each${payerName ? `<span>Agent: ${esc(payerName)}</span>` : '<span style="color:#94a3b8">Not sold — only "We bear it"</span>'}</div>`;
        function render(p) {
            p.querySelectorAll('.tcx-seg button').forEach(b => b.classList.toggle('on', b.dataset.k === (st.kind === 'newticket' ? 'refund' : st.kind)));
            // Cancel return posts only when its return can go; the window saves on its own button.
            const okBtn = Swal.getConfirmButton();
            if (okBtn) okBtn.style.display = st.kind === 'return_cancel' ? ((ctx.ret || {}).eligible ? '' : 'none') : (!after || canNew ? '' : 'none');
            const keep = read(p);
            const k = st.kind, seller = t.seller || 'the seller', f = [];
            if (k === 'newticket') {
                // Refund → new ticket (owner, 2026-10-07): the fare difference is the new ticket vs the old.
                const ra = ctx.refund_amount;
                // No "Correct refund" button here (owner, 2026-11-01) — All Trails and Edit corrects a refund.
                f.push(`<div class="tcx-info">Refunded — airline refund <b>${ra === null ? '—' : tk(ra)}</b>${ra !== null && ra < t.amount ? ', airline kept <b>' + tk(t.amount - ra) + '</b>' : ''}. The refund's postings are in the history on the right.</div>`);
                if (ctx.was_cf) f.push(`<div class="tcx-cf"><i class="fas fa-plane-departure"></i> This was a contract flight passenger's ticket. His new ticket is made with <b>Re-assign</b> on Flights &amp; Passengers, so the flight's money stays right.</div>`);
                else if (madeNew) f.push('<div class="tcx-info">A new ticket has already been made for this refund — see the history on the right.</div>');
                else if (!ctx.can_new) f.push('<div class="tcx-info">Making a new ticket needs the "create ticket purchase" permission.</div>');
                else {
                    f.push(`<div class="tcx-sec">New ticket<span style="color:#64748b">Same passenger, route ${esc(t.route || '')} and seller ${esc(seller)}</span></div>`);
                    f.push(`<div class="tcx-grid"><div class="tcx-f"><label for="tcxPnr">New PNR</label><input id="tcxPnr" class="tcx-in" type="text" maxlength="200" autocomplete="off"></div>
                        <div class="tcx-f"><label for="tcxNewAt">New travel date &amp; time</label><input id="tcxNewAt" class="tcx-in" type="datetime-local"><span class="tcx-hint">Old ticket flew ${esc(day(t.travel_at))}</span></div></div>`);
                    f.push(`<div class="tcx-grid"><div class="tcx-f"><label for="tcxNewCost">New ticket cost</label>${money('tcxNewCost')}<span class="tcx-hint">Old ticket ${tk(t.amount)} · owed to ${esc(seller)}</span></div>
                        <div class="tcx-f"><label for="tcxPrice">Agent charge (new ticket's price)</label>${money('tcxPrice', { off: !sale, value: sale ? Number(sale.price).toFixed(2) : '' })}<span class="tcx-hint">${sale ? 'Sold to ' + esc(sale.client) + ' · old price ' + tk(sale.price) + ' was reversed on the refund' : 'Old ticket was not sold — nobody to bill'}</span></div></div>`);
                    f.push(secHead());
                    f.push('<div class="tcx-costs">'
                        + cost('fare', 'Fare difference (new − old)', 'New ticket cost − ' + tk(t.amount), money('tcxFare', { red: true, ro: true }), pay('fare'))
                        + feeRow() + '</div>');
                    f.push(`<div class="tcx-f"><label for="tcxNote">Note</label><textarea id="tcxNote"></textarea></div>`);
                }
            } else if (k === 'reissue') {
                f.push(`<div class="tcx-grid"><div class="tcx-f"><label for="tcxNewAt">New travel date &amp; time</label>
                    <input id="tcxNewAt" class="tcx-in" type="datetime-local" value="${curAt}"><span class="tcx-hint">Now ${esc(day(t.travel_at))}</span></div>
                    <div class="tcx-f"><label for="tcxNote">Note</label><textarea id="tcxNote" class="tcx-note1" rows="1"></textarea></div></div>`);
                f.push(secHead());
                f.push('<div class="tcx-costs">'
                    // Same ticket, only the airline's charge (owner, 2026-10-07) — fare difference belongs
                // to refund → new ticket, never here.
                + cost('penalty', 'Airline penalty', 'Owed to ' + seller, money('tcxPenalty', { red: true }), pay('penalty'))
                    + feeRow() + '</div>');
            } else if (k === 'refund') {
                f.push(`<div class="tcx-grid"><div class="tcx-f"><label>Ticket cost</label>${money('tcxCost', { ro: true, value: Number(t.amount).toFixed(2) })}</div>
                    <div class="tcx-f"><label for="tcxRefund">Airline refund</label>${money('tcxRefund')}</div></div>`);
                f.push(secHead());
                f.push('<div class="tcx-costs">'
                    + cost('kept', 'Airline keeps (penalty)', 'Ticket cost − refund', money('tcxKept', { red: true }), pay('kept'))
                    + feeRow() + '</div>');
            } else if (k === 'return_cancel') {
                // Cancel return (owner, 2026-10-14): a family passenger stays on; his unused return
                // is cancelled inside the airline's window. Round trip = its return half only.
                const r = ctx.ret || {}, round = r.shape === 'round_trip';
                // The Family tick: a Ticketing ticket's own; a contract flight passenger is family by
                // his category, so there it only says "this ticket is his return" (one-way tickets).
                const tick = cf ? (round ? null : 'This ticket is his return') : (round ? 'Family ticket' : 'Family — this is the return ticket');
                const winTxt = !r.cancel_by ? 'not entered'
                    : r.tone === 'late' ? 'closed ' + r.cancel_by_label
                    : 'cancel by ' + r.cancel_by_label + ' · ' + (r.days_left === 0 ? 'last day' : r.days_left + ' day' + (r.days_left === 1 ? '' : 's') + ' left');
                f.push(`<div class="tcx-sec">Return window<span class="tcx-wt ${esc(r.tone || 'none')}">${esc(winTxt)}</span></div>`);
                f.push(`<div class="tcx-win"><div class="tcx-f"><input id="tcxBy" class="tcx-in" type="text" data-dt autocomplete="off" title="The return can be cancelled until — date, time optional" placeholder="Can be cancelled until — date, time optional" value="${esc(r.cancel_by ? String(r.cancel_by).replace('T', ' ') : '')}"></div>`
                    + (tick && !r.done ? `<label class="tcx-tick"><input id="tcxFam" type="checkbox" ${r.flagged ? 'checked' : ''}> ${esc(tick)}</label>` : '<span></span>')
                    + (URLS.returnWindow && !r.done ? '<button type="button" class="tcx-wbtn" id="tcxWinSave"><i class="fas fa-clock"></i> Save window</button>' : '<span></span>') + '</div>');
                if (r.done) {
                    f.push(`<div class="tcx-info">Return cancelled on <b>${esc(r.cancelled_at || '')}</b>${r.refund !== null && r.refund !== undefined ? ' — airline refund <b>' + tk(r.refund) + '</b>' : ''}. Its postings are in the history on the right.</div>`);
                } else if (!r.family) {
                    f.push(`<div class="tcx-info">${cf ? 'Cancel return is for family passengers — his flight category is not a family one, and he holds no family visa.' : 'Cancel return is for family tickets. Tick <b>Family</b> above and save the window first.'}</div>`);
                } else if (!r.shape) {
                    f.push(`<div class="tcx-info">This is a one-way ticket with no return on it. If it <b>is</b> his return ticket, tick that above and save.</div>`);
                } else if (!r.eligible) {
                    f.push('<div class="tcx-info">Only a purchased ticket can have its return cancelled.</div>');
                } else {
                    f.push(`<div class="tcx-grid"><div class="tcx-f"><label>Ticket cost</label>${money('tcxCost', { ro: true, value: Number(t.amount).toFixed(2) })}<span class="tcx-hint">${round ? '<b>Round trip</b> — only its return half goes; the ticket stays live' : '<b>Separate return ticket</b> — the whole ticket is cancelled'}</span></div>
                        <div class="tcx-f"><label for="tcxRefund">Airline refund for the return</label>${money('tcxRefund')}</div></div>`);
                    f.push(secHead());
                    f.push('<div class="tcx-costs">'
                        + cost('kept', 'Airline keeps (penalty)', round ? 'For the return half — as the airline says' : 'Ticket cost − refund · type either', money('tcxRetPen', { red: true }), pay('kept'))
                        + feeRow() + '</div>');
                    const canRevise = cf ? !!cf.agent : !!sale;
                    f.push(`<div class="tcx-sec">${cf ? 'His package' : 'Agent\'s total for this ticket'}<span>${canRevise ? 'now ' + tk(ctx.agent_total) : '<span style="color:#94a3b8">not sold — nothing to revise</span>'}</span></div>`);
                    f.push(`<div class="tcx-mode">${[['keep', 'Keep ' + tk(ctx.agent_total)], ['total', 'Type a new total'], ['giveback', 'Type an amount to give back']]
                        .map(([m, l]) => `<button type="button" data-m="${m}" ${!canRevise && m !== 'keep' ? 'disabled' : ''}>${esc(l)}</button>`).join('')}</div>`);
                    f.push(`<div class="tcx-grid"><div class="tcx-f" id="tcxAvalBox"><label for="tcxAval">New total</label>${money('tcxAval')}</div>
                        <div class="tcx-f"><label for="tcxNote">Note</label><textarea id="tcxNote" class="tcx-note1" rows="1"></textarea></div></div>`);
                }
            } else {
                // Void (owner, 2026-10-11): two figures — what the seller gives back (vendor
                // price) and what comes off the agent (agent price). Both start at the full
                // amounts; change either and the postings follow.
                f.push(`<div class="tcx-grid"><div class="tcx-f"><label for="tcxRefund">Vendor price — refund from ${esc(seller)}</label>${money('tcxRefund', { value: Number(t.amount).toFixed(2) })}<span class="tcx-hint">Ticket cost ${tk(t.amount)}</span></div>
                    <div class="tcx-f"><label for="tcxAgent">Agent price — reversed off the agent</label>${money('tcxAgent', { off: !sale || !!cf, value: sale && !cf ? Number(sale.price).toFixed(2) : '' })}<span class="tcx-hint">${cf ? 'Contract flight — the flight bills the agent' : sale ? 'Sold to ' + esc(sale.client) + ' at ' + tk(sale.price) : 'Not sold — nobody to reverse'}</span></div></div>`);
                f.push(secHead());
                f.push('<div class="tcx-costs">'
                    + cost('kept', 'Airline keeps (penalty)', 'Ticket cost − vendor refund', money('tcxKept', { red: true }), pay('kept'))
                    + feeRow() + '</div>');
            }
            // Re-issue / Reschedule keep the note beside the date (fits a 620px-tall window).
            if (k === 'refund' || k === 'void') f.push(`<div class="tcx-f"><label for="tcxNote">Note</label><textarea id="tcxNote"></textarea></div>`);
            const box = p.querySelector('#tcxLeft');
            box.classList.toggle('tcx-rc', k === 'return_cancel');
            box.innerHTML = f.join('');
            box.querySelector('#tcxCorrectRefund')?.addEventListener('click', () => correctRefund(id, ctx));
            // keep what was typed across tabs
            [['tcxPenalty', keep.penalty], ['tcxFare', keep.fare], ['tcxFee', keep.fee], ['tcxRefund', keep.refund], ['tcxAgent', keep.agent], ['tcxNote', keep.note], ['tcxRetPen', keep.retPen], ['tcxAval', keep.aval]]
                .forEach(([i, x]) => { const el = box.querySelector('#' + i); if (el && !el.disabled && x !== null && x !== undefined && x !== '') el.value = x; });
            box.querySelectorAll('.tcx-pay button').forEach(b => b.addEventListener('click', () => {
                if (b.disabled) return; st.who[b.closest('.tcx-pay').dataset.part] = b.dataset.b; paint(p);
            }));
            // Refund / Void (owner, 2026-10-11): type the penalty and the refund is worked out
            // (ticket cost − penalty); type the refund and the penalty is (paint()). One rule, both ways.
            const keptEl = box.querySelector('#tcxKept'), refEl = box.querySelector('#tcxRefund');
            if (keptEl && refEl) keptEl.addEventListener('input', () => {
                if (keptEl.value === '') { refEl.value = ''; return; }
                const kept = Math.min(Math.max(0, Number(keptEl.value) || 0), Number(t.amount));
                refEl.value = (Number(t.amount) - kept).toFixed(2);
            });
            // Cancel return on a separate return ticket (owner, 2026-10-15: "both should be
            // calculated automatically") — the same rule both ways: penalty → refund = ticket
            // cost − penalty; refund → penalty in paint(). A round trip has no price of its own
            // for the return half, so there both are still the airline's figures.
            const retPenEl = box.querySelector('#tcxRetPen');
            if (retPenEl && refEl && (ctx.ret || {}).shape !== 'round_trip') retPenEl.addEventListener('input', () => {
                if (retPenEl.value === '') { refEl.value = ''; return; }
                const kept = Math.min(Math.max(0, Number(retPenEl.value) || 0), Number(t.amount));
                refEl.value = (Number(t.amount) - kept).toFixed(2);
            });
            box.querySelectorAll('.tcx-mode button').forEach(b => b.addEventListener('click', () => {
                if (b.disabled) return; st.mode = b.dataset.m; const a = box.querySelector('#tcxAval'); if (a) a.value = ''; paint(p);
            }));
            const ws = box.querySelector('#tcxWinSave');
            if (ws) ws.addEventListener('click', () => {
                const body = new URLSearchParams();
                body.append('cancel_by', box.querySelector('#tcxBy')?.value || '');
                const fam = box.querySelector('#tcxFam');
                if (fam) body.append('family', fam.checked ? '1' : '0');
                ws.disabled = true;
                fetch(URLS.returnWindow.replace('__ID__', id), { method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/x-www-form-urlencoded' }, body })
                    .then(r => r.json().then(j => ({ ok: r.ok, j })))
                    .then(({ ok, j }) => {
                        ws.disabled = false;
                        if (!ok || !j.success) { Swal.showValidationMessage(j.message || Object.values(j.errors || {})[0]?.[0] || 'The window was not saved.'); return; }
                        Swal.resetValidationMessage();
                        ctx.ret = j.ret;
                        render(p);
                    })
                    .catch(() => { ws.disabled = false; Swal.showValidationMessage('Network error — the window was not saved.'); });
            });
            box.querySelectorAll('input,textarea').forEach(el => el.addEventListener('input', () => paint(p)));
            paint(p);
        }
        function num(p, id) { const el = p.querySelector('#' + id); if (!el || el.disabled || el.value === '') return null; const n = Number(el.value); return isNaN(n) ? null : Math.max(0, n); }
        function read(p) {
            const newAt = p.querySelector('#tcxNewAt')?.value || '';
            return { penalty: num(p, 'tcxPenalty'), fare: num(p, 'tcxFare'), fee: num(p, 'tcxFee'), refund: num(p, 'tcxRefund'), agent: num(p, 'tcxAgent'),
                newAt: newAt && (after || newAt !== curAt) ? newAt.replace('T', ' ') : '', note: p.querySelector('#tcxNote')?.value || '',
                pnr: (p.querySelector('#tcxPnr')?.value || '').trim(), newCost: num(p, 'tcxNewCost'), price: num(p, 'tcxPrice'),
                retPen: num(p, 'tcxRetPen'), aval: num(p, 'tcxAval') };
        }
        // Cancel return: what comes off the agent's total — none, the old total − a typed one, or a typed amount.
        function reduction(v) {
            const cur = Number(ctx.agent_total) || 0;
            if (st.mode === 'total') return v.aval === null ? 0 : Math.round((cur - v.aval) * 100) / 100;
            if (st.mode === 'giveback') return v.aval === null ? 0 : v.aval;
            return 0;
        }
        function figures(v) {
            const k = st.kind, isRefund = k === 'refund' || k === 'void';
            if (k === 'return_cancel') {
                // round trip: the penalty is typed; a separate return: cost − refund
                const round = (ctx.ret || {}).shape === 'round_trip';
                const refund = v.refund === null ? null : Math.min(v.refund, t.amount);
                const kept = refund === null ? 0 : (round ? (v.retPen || 0) : Math.max(0, Math.round((t.amount - refund) * 100) / 100));
                const fee = canBill ? (v.fee || 0) : 0;
                return { isRefund: false, rc: true, round, pen: 0, fare: 0, fee, refund, kept,
                    his: st.who.kept === 'customer' ? kept : 0, ours: st.who.kept === 'company' ? kept : 0, seller: 0 };
            }
            if (k === 'newticket') {
                // fare difference = new cost − old cost, never below zero (a cheaper ticket has none)
                const fare = v.newCost === null ? 0 : Math.max(0, Math.round((v.newCost - t.amount) * 100) / 100), fee = v.fee || 0;
                const his = st.who.fare === 'customer' ? fare : 0, ours = st.who.fare === 'company' ? fare : 0;
                return { isRefund: false, nt: true, pen: 0, fare, fee, refund: null, kept: 0, his, ours, seller: 0 };
            }
            const pen = isRefund ? 0 : (v.penalty || 0), fare = isRefund ? 0 : (v.fare || 0), fee = canBill ? (v.fee || 0) : 0;
            const refund = k === 'void' ? (v.refund === null ? t.amount : Math.min(v.refund, t.amount)) : (k === 'refund' ? v.refund : null);
            const kept = isRefund && refund !== null ? Math.max(0, t.amount - Math.min(refund, t.amount)) : 0;
            const his = (st.who.penalty === 'customer' ? pen : 0) + (st.who.fare === 'customer' ? fare : 0) + (st.who.kept === 'customer' ? kept : 0);
            const ours = (st.who.penalty === 'company' ? pen : 0) + (st.who.fare === 'company' ? fare : 0) + (st.who.kept === 'company' ? kept : 0);
            return { isRefund, pen, fare, fee, refund, kept, his, ours, seller: pen + fare };
        }
        function check(v) {
            const k = st.kind, x = figures(v);
            if (k === 'return_cancel') {
                const r = ctx.ret || {}, cur = Number(ctx.agent_total) || 0, red = reduction(v);
                if (!r.eligible) return r.done ? 'The return on this ticket is already cancelled.' : 'This ticket\'s return cannot be cancelled — see the note above.';
                if (v.refund === null) return 'Enter what the airline refunds for the return (0 if nothing).';
                if (v.refund > t.amount) return 'The refund cannot be more than the ticket cost.';
                if (x.round && x.refund + x.kept > t.amount + 0.004) return 'The refund and the penalty together are more than the ticket cost.';
                if (x.kept > 0 && !st.who.kept) return 'Choose who bears what the airline keeps.';
                if (st.mode === 'total' && (v.aval === null || red <= 0)) return 'Type a new total below ' + tk(cur) + '.';
                if (st.mode === 'giveback' && (v.aval === null || red <= 0 || red > cur + 0.004)) return 'Type an amount to give back — more than 0, up to ' + tk(cur) + '.';
                return null;
            }
            if (k === 'newticket') {
                if (!v.pnr) return 'Enter the new ticket\'s PNR.';
                if (!v.newAt) return 'Choose the new ticket\'s travel date.';
                if (v.newCost === null) return 'Enter the new ticket\'s cost.';
                if (x.fare > 0 && !st.who.fare) return 'Choose who bears the fare difference.';
                return null;
            }
            if (k === 'reissue' && x.seller <= 0 && !x.fee && !v.newAt) return 'Choose the new travel date, or enter the airline penalty or our charge.';
            if (k === 'refund' && v.refund === null) return 'Enter what the airline refunds (0 if nothing).';
            if (k === 'refund' && v.refund > t.amount) return 'The refund cannot be more than the ticket cost.';
            if (k === 'void' && v.refund !== null && v.refund > t.amount) return 'The vendor refund cannot be more than the ticket cost.';
            if (k === 'void' && sale && !cf && v.agent !== null && v.agent > Number(sale.price)) return 'The agent price reversed cannot be more than he was sold at (' + tk(sale.price) + ').';
            if (x.pen > 0 && !st.who.penalty) return 'Choose who bears the airline penalty.';
            if (x.fare > 0 && !st.who.fare) return 'Choose who bears the fare difference.';
            if (x.kept > 0 && !st.who.kept) return 'Choose who bears what the airline keeps.';
            if (cf && !x.isRefund && cf.off_flight && x.seller + x.fee > 0) return 'He is off his flight — use Re-assign on Flights & Passengers.';
            return null;
        }
        // What the server will post, line by line — the same rules as TicketChangeController.
        function paint(p) {
            p.querySelectorAll('.tcx-pay').forEach(g => g.querySelectorAll('button').forEach(b => b.classList.toggle('on', st.who[g.dataset.part] === b.dataset.b)));
            const v = read(p), k = st.kind, x = figures(v), L = [];
            // the box being typed in is left as typed; the other one follows
            const keptBox = p.querySelector('#tcxKept');
            if (keptBox && document.activeElement !== keptBox) keptBox.value = x.refund === null ? '' : x.kept.toFixed(2);
            const who = payerName || 'customer';
            const line = (txt, acc) => L.push(`<li>${txt}${acc ? `<span class="tcx-acc">${acc}</span>` : ''}</li>`);
            const parts = (list) => list.filter(([, a]) => a > 0).map(([n, a]) => n + ' ' + tk(a)).join(' + ');
            const sum = [];
            if (x.nt) {
                if (p.querySelector('#tcxFare')) p.querySelector('#tcxFare').value = v.newCost === null ? '' : x.fare.toFixed(2);
                if (!canNew) {
                    // history only
                } else if (v.newCost === null) {
                    L.push('<li class="tcx-none">Enter the new ticket\'s cost to see the postings.</li>');
                } else {
                    line(`New ticket <b>${esc(v.pnr || 'PNR')}</b>${v.newAt ? ' · ' + esc(day(v.newAt)) : ''} — cost <span class="tcx-r">${tk(v.newCost)}</span> owed to ${esc(t.seller || 'the seller')}`,
                        'Through Ticket Entry, as if typed there · seller ledger Bill · Dr 5110 Ticket Cost / Cr ' + (isPortal ? 'portal wallet' : 'Payable'));
                    if (v.price !== null && sale) line(`<b>${esc(sale.client)}</b> billed the agent price <span class="tcx-r">${tk(v.price)}</span>`, 'Manage Sales invoice · Dr 1311 Receivable / Cr 4110 Air Ticket Sales');
                    if (x.fare > 0) line(`Fare difference <span class="tcx-r">${tk(x.fare)}</span> (${tk(v.newCost)} − ${tk(t.amount)}) → Company Ledger` + (st.who.fare === 'customer' ? `, billed to <b>${esc(who)}</b>` : st.who.fare === 'company' ? ', we bear it' : ''),
                        'Dr 5172 Fare Difference (Borne) / Cr 5110' + (st.who.fare === 'customer' ? ' · recovered Cr 4182' : ''));
                    else if (v.newCost < t.amount) line(`New ticket is <span class="tcx-g">${tk(t.amount - v.newCost)}</span> cheaper — no fare difference`);
                    if (x.his + x.fee > 0) line(`<b>${esc(who)}</b> charged <span class="tcx-r">${tk(x.his + x.fee)}</span> on one line (${parts([['fare difference', x.his], ['our fee', x.fee]])})`, 'Dr 1311 Receivable / Cr 4182 Fare Difference Recovered · Cr 4183 Change Charges Recovered');
                    if (x.fare > 0 && !st.who.fare) L.push('<li class="tcx-none">Choose who bears the fare difference.</li>');
                    sum.push(['Owed to the seller', tk(v.newCost), 'r']);
                    if (v.price !== null && sale) sum.push(['Agent price', tk(v.price), '']);
                }
            } else if (x.rc) {
                const r = ctx.ret || {};
                const penBox = p.querySelector('#tcxRetPen');
                // (not while it is being typed in — that one sets the refund instead)
                if (penBox && !x.round && document.activeElement !== penBox) penBox.value = x.refund === null ? '' : x.kept.toFixed(2);
                p.querySelectorAll('.tcx-mode button').forEach(b => b.classList.toggle('on', b.dataset.m === st.mode));
                const avBox = p.querySelector('#tcxAvalBox');
                if (avBox) { avBox.style.visibility = st.mode === 'keep' ? 'hidden' : 'visible'; avBox.querySelector('label').textContent = st.mode === 'total' ? 'New total' : 'Give back'; }
                const cur = Number(ctx.agent_total) || 0, red = reduction(v);
                if (!r.eligible) {
                    // window / family only — nothing to post from here
                } else if (x.refund === null) {
                    L.push('<li class="tcx-none">Enter the airline refund for the return to see the postings.</li>');
                } else {
                    if (cf) line(`Posted by ${esc(cf.flight || 'the flight')}'s own Cancel return — the same rows as from his card`, 'Recorded here too, so Ticket Orders shows it');
                    if (x.round) line(`Return half cancelled — <b>${esc(t.pnr)}</b> stays live at ${tk(t.amount)} for the way he flew`, 'The return\'s refund and penalty are kept on the ticket — its cost is ' + tk(t.amount - x.refund) + ' now');
                    else line(`Ticket cancelled in Ticketing — kept at ${tk(t.amount)} with a note${cf ? ', and unlinked from him' : ''}`, 'The seat goes back; nothing is deleted');
                    if (x.refund > 0) line(`Airline refund <span class="tcx-g">${tk(x.refund)}</span> due from ${esc(t.seller || 'the seller')}`, 'Seller ledger Cr · Dr ' + (isPortal ? 'portal wallet' : 'Payable') + ' / Cr 5110 Ticket Cost');
                    if (x.round && x.refund + x.kept > t.amount + 0.004) L.push('<li class="tcx-r">The refund and the penalty together are more than the ticket cost.</li>');
                    if (x.kept > 0) line(`Airline keeps <span class="tcx-r">${tk(x.kept)}</span> → Company Ledger` + (st.who.kept === 'customer' ? `, billed to <b>${esc(who)}</b>` : st.who.kept === 'company' ? ', we bear it' : ''),
                        'Dr 5171 Penalty (Borne) / Cr 5110' + (st.who.kept === 'customer' ? ' · Dr 1311 / Cr 4181 Recovered' : ''));
                    if (x.his + x.fee > 0) line(`<b>${esc(who)}</b> charged <span class="tcx-r">${tk(x.his + x.fee)}</span> as ONE line — Return cancellation penalty (${parts([['airline penalty', x.his], ['our charge', x.fee]])})`, 'Dr 1311 Receivable / Cr 4181 Penalty Recovered' + (x.fee > 0 ? ' · Cr 4183 our charge = profit' : ''));
                    const what = cf ? 'His package' : 'Agent total';
                    if (st.mode === 'keep') line(`${what} stays <b>${tk(cur)}</b>`, 'Nothing credited back');
                    else if (red > 0 && red <= cur + 0.004) line(`${what} ${tk(cur)} → <b>${tk(cur - red)}</b> — <span class="tcx-g">${tk(red)}</span> credited to <b>${esc(who)}</b> as its own line`,
                        cf ? 'Agent ledger Cr · Dr 4150 Flight Sales / Cr 1311 Receivable' : 'Customer ledger Cr · Dr 4110 Air Ticket Sales / Cr 1311 Receivable');
                    else L.push(`<li class="tcx-none">${st.mode === 'total' ? 'Type a new total below ' + tk(cur) : 'Type an amount to give back, up to ' + tk(cur)}.</li>`);
                    if (x.kept > 0 && !st.who.kept) L.push('<li class="tcx-none">Choose who bears what the airline keeps.</li>');
                    sum.push(['Refund due from the seller', tk(x.refund), 'g']);
                    if (st.mode !== 'keep' && red > 0 && red <= cur + 0.004) sum.push(['Credited to ' + esc(who), tk(red), 'g']);
                }
            } else if (!x.isRefund) {
                if (v.newAt) line(`Travel date ${esc(day(t.travel_at))} → <b>${esc(day(v.newAt))}</b>`, cf ? 'His card and the ticket move together; the flight card follows leg 1' : 'Ticket leg and Ticket Orders date');
                if (cf) {
                    const agentPart = (st.who.penalty === 'customer' ? x.pen : 0) + (st.who.fare === 'customer' ? x.fare : 0);
                    const ourPart = (st.who.penalty === 'company' ? x.pen : 0) + (st.who.fare === 'company' ? x.fare : 0);
                    const acc = 'Through ' + esc(cf.flight || 'the flight') + '\'s Re-Issue · Dr 5173 Rebooking & Change Fees / Cr ' + (isPortal ? 'portal wallet' : 'Payable');
                    if (agentPart > 0) line(`Re-issue fee <span class="tcx-r">${tk(agentPart)}</span> owed to ${esc(t.seller || 'the seller')} — billed to <b>${esc(who)}</b> on one line`, acc + ' · Dr 1311 Receivable / Cr 4181 Penalty Recovered');
                    if (ourPart > 0) line(`Re-issue fee <span class="tcx-r">${tk(ourPart)}</span> owed to ${esc(t.seller || 'the seller')} — we bear it`, acc + ' — Company Ledger, Borne');
                    if (x.fee > 0) line(`Our charge <span class="tcx-r">${tk(x.fee)}</span> billed to <b>${esc(who)}</b> — on the same ONE penalty line${agentPart > 0 ? ' (' + tk(agentPart + x.fee) + ' in all)' : ''}`, 'Dr 1311 Receivable / Cr 4183 Change Charges — our profit');
                    if (x.seller > 0 && agentPart + ourPart < x.seller) L.push('<li class="tcx-none">Choose who bears each cost to see its posting.</li>');
                    if (!x.seller && !x.fee && v.newAt) line('No charge — the date only', 'Logged on his card');
                } else {
                    if (x.seller > 0) line(`<span class="tcx-r">${tk(x.seller)}</span> owed to ${esc(t.seller || 'the seller')} (${parts([['penalty', x.pen], ['fare difference', x.fare]])})`, 'Seller ledger Bill · Dr 5110 Ticket Cost / Cr ' + (isPortal ? 'portal wallet' : 'Payable'));
                    if (x.his + x.fee > 0) line(`<b>${esc(who)}</b> charged <span class="tcx-r">${tk(x.his + x.fee)}</span> as ONE penalty line (${parts([['airline penalty', st.who.penalty === 'customer' ? x.pen : 0], ['our charge', x.fee]])})`, 'Dr 1311 Receivable / Cr 4181 Penalty Recovered' + (x.fee > 0 ? ' · Cr 4183 our charge = profit' : ''));
                    if (x.ours > 0) line(`We bear <span class="tcx-r">${tk(x.ours)}</span> (${parts([['penalty', st.who.penalty === 'company' ? x.pen : 0], ['fare difference', st.who.fare === 'company' ? x.fare : 0]])})`, 'Stays in the ticket\'s cost — profit goes down');
                    if (x.seller > 0 && x.his + x.ours < x.seller) L.push('<li class="tcx-none">Choose who bears each cost to see its posting.</li>');
                    if (!x.seller && !x.fee && v.newAt) line('No money — the date only');
                }
                if (x.seller > 0) sum.push(['Owed to the seller', tk(x.seller), 'r']);
            } else if (x.refund === null) {
                L.push('<li class="tcx-none">Enter the airline refund to see the postings.</li>');
            } else if (cf) {
                line(`Ticket cancelled in Ticketing (kept at ${tk(t.amount)}, with a note) and unlinked from him`, 'Through ' + esc(cf.flight || 'the flight') + '\'s own Refund');
                line(`Airline refund <span class="tcx-g">${tk(x.refund)}</span> due from ${esc(t.seller || 'the seller')}`, 'Seller ledger Cr · Dr ' + (isPortal ? 'portal wallet' : 'Payable') + ' / Cr 5110 Ticket Cost');
                if (x.kept > 0) line(`Airline keeps <span class="tcx-r">${tk(x.kept)}</span> → Company Ledger` + (st.who.kept === 'customer' ? `, billed to <b>${esc(who)}</b> on one line` : st.who.kept === 'company' ? ', we bear it' : ''),
                    'Dr 5171 Penalty (Borne) / Cr 5110' + (st.who.kept === 'customer' ? ' · Dr 1311 / Cr 4181 Recovered' : ''));
                if (x.fee > 0) line(`Our charge <span class="tcx-r">${tk(x.fee)}</span> billed to <b>${esc(who)}</b> — on the same ONE penalty line`, 'Dr 1311 Receivable / Cr 4183 Change Charges — our profit');
                line('His stage steps back to the ticket stage; Re-assign moves him to another flight');
                sum.push(['Refund due from the seller', tk(x.refund), 'g']);
            } else {
                line(`Ticket cancelled in Ticketing — kept at ${tk(t.amount)} with a note`, 'The seat goes back; nothing is deleted');
                if (x.refund > 0) line(`Airline refund <span class="tcx-g">${tk(x.refund)}</span> due from ${esc(t.seller || 'the seller')}`, 'Seller ledger Cr · Dr ' + (isPortal ? 'portal wallet' : 'Payable') + ' / Cr 5110 Ticket Cost');
                // void: the agent price typed (never more than he was sold at); refund: in full
                const back = sale ? (k === 'void' && v.agent !== null ? Math.min(v.agent, Number(sale.price)) : Number(sale.price)) : 0;
                if (sale && back > 0) line(`<b>${esc(sale.client)}</b>: agent price <span class="tcx-g">${tk(back)}</span> reversed`
                    + (back < Number(sale.price) ? ` <span style="color:#64748b">(of ${tk(sale.price)} — ${tk(Number(sale.price) - back)} stays on him)</span>` : ''), 'Customer ledger Cr · Dr 4110 Air Ticket Sales / Cr 1311 Receivable');
                if (x.his + x.fee > 0) line(`<b>${esc(who)}</b> charged <span class="tcx-r">${tk(x.his + x.fee)}</span> as ONE penalty line (${parts([['airline penalty', x.his], ['our charge', x.fee]])})`, 'Dr 1311 Receivable / Cr 4181 Penalty Recovered' + (x.fee > 0 ? ' · Cr 4183 our charge = profit' : ''));
                if (x.ours > 0) line(`We bear the penalty <span class="tcx-r">${tk(x.ours)}</span>`, 'Stays in 5110 Ticket Cost — profit goes down');
                if (x.kept > 0 && !st.who.kept) L.push('<li class="tcx-none">Choose who bears what the airline keeps.</li>');
                sum.push(['Refund due from the seller', tk(x.refund), 'g']);
                if (sale) sum.push(['Agent price reversed', tk(back), '']);
            }
            if (x.his + x.fee > 0) sum.push([esc(who) + ' pays', tk(x.his + x.fee), 'r']);
            if (x.ours > 0) sum.push(['We bear', tk(x.ours), 'r']);
            // Cancel return: a passed window is said on the right, so the form keeps its height
            const late = k === 'return_cancel' && (ctx.ret || {}).tone === 'late' && !(ctx.ret || {}).done
                ? '<div class="tcx-info tcx-late" style="margin-bottom:12px"><i class="fas fa-triangle-exclamation"></i> The airline\'s window has closed. It may refuse the refund or keep more — check with the seller before you post.</div>' : '';
            // All Trails and Edit (owner, 2026-10-26): top-right of this panel; it closes Change
            // and opens the ticket's Trail. Super admin only — the server says so.
            const trailBtn = ctx.trail && window.ticketTrailOpen
                ? '<button type="button" class="tcx-trailbtn" id="tcxTrail" title="Every edit, payment and posting of this ticket — and correct any of it"><i class="fas fa-clock-rotate-left"></i> All Trails and Edit</button>' : '';
            p.querySelector('#tcxPost').innerHTML = late + `<div class="tcx-posthead"><h4>What will be posted</h4>${trailBtn}</div>`
                + (L.length ? '<ol>' + L.join('') + '</ol>' : `<div class="tcx-none">${after && !canNew ? 'Nothing to post.' : 'Fill in the change to see the postings.'}</div>`)
                + (sum.length ? '<div class="tcx-sum">' + sum.map(([l, a, c]) => `<span>${l}</span><b class="${c}">${a}</b>`).join('') + '</div>' : '')
                + `<div style="margin-top:14px">${Array.isArray(ctx.timeline) ? histAll() : histFor(k)}</div>`;
            p.querySelector('#tcxTrail')?.addEventListener('click', () => { Swal.close(); window.ticketTrailOpen(id); });
        }
    }
})();
</script>
