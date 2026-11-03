{{--
    Ticket Trail (owner, 2026-10-26): Ticket Orders → Change → "All Trails and Edit".
    One ticket's whole story — every edit, payment, sale and change with its time
    (left), the editor that corrects any of it (middle), and where it is posted
    now / what a correction will change, ledger by ledger (right). Every edit is
    previewed by the server — the real postings, rolled back — so the right side
    is exactly what Confirm & post will do. Old entries are REPLACED, a copy kept
    in the history. Server: TicketTrailController + App\Services\TicketTrail.
    Super admin only; closes only on the ✕. Expects: $role. Exposes
    window.ticketTrailOpen(id) for the Change dialog's button.
--}}
@php
    $ttrUrls = [
        'show' => route('role.ticket-trail.show', ['role' => $role, 'ticketPurchase' => '__ID__']),
        'preview' => route('role.ticket-trail.preview', ['role' => $role, 'ticketPurchase' => '__ID__']),
        'apply' => route('role.ticket-trail.apply', ['role' => $role, 'ticketPurchase' => '__ID__']),
    ];
@endphp
<style>
/* the page behind the Trail does not scroll — a class, so no inline reset by another script can undo it */
html.ttr-lock,html.ttr-lock body{overflow:hidden !important;}
html.ttr-lock body{padding-right:var(--ttr-bar,0px) !important;}   /* the layout's body has no padding of its own */
.ttr-back{position:fixed;inset:0;z-index:10050;   /* above the page's floating chat button */background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;}
.ttr-pop{--ttr-line:#d2d8e7;width:min(1280px,calc(100vw - 32px));height:calc(100vh - 24px);background:#fff;border-radius:16px;box-shadow:0 24px 60px rgba(15,23,42,.3);display:flex;flex-direction:column;overflow:hidden;font-size:12.5px;color:#334155;}
.ttr-top{flex:none;display:flex;align-items:center;gap:10px;padding:16px 22px 0;}
.ttr-title{font-size:17px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:9px;}
.ttr-title i{color:#4f46e5;}
.ttr-pnr{font-family:ui-monospace,Menlo,Consolas,monospace;color:#4338ca;}
.ttr-x{margin-left:auto;border:0;background:none;font-size:22px;line-height:1;color:#94a3b8;cursor:pointer;padding:4px 6px;}
.ttr-x:hover{color:#0f172a;}
.ttr-head{flex:none;margin:12px 22px 0;display:flex;flex-wrap:wrap;gap:6px 20px;font-size:12px;color:#475569;padding:10px 14px;border:1px solid var(--ttr-line);border-radius:10px;background:#f8fafc;}
.ttr-head b{color:#0f172a;font-weight:600;}
.ttr-block{flex:none;margin:10px 22px 0;padding:9px 13px;border:1px solid #fde68a;background:#fffbeb;color:#92400e;border-radius:10px;font-size:12px;}
.ttr-body{flex:1 1 auto;min-height:0;display:grid;grid-template-columns:290px minmax(0,1.12fr) minmax(0,1fr);gap:16px;padding:14px 22px 0;}
.ttr-col{min-height:0;overflow:auto;overscroll-behavior:contain;display:flex;flex-direction:column;gap:12px;padding:0 4px 8px 0;}
.ttr-col>*{flex-shrink:0;}   /* each section keeps its height; the column scrolls */
.ttr-h{position:sticky;top:0;z-index:1;background:#fff;font-size:10.5px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.06em;padding-bottom:6px;border-bottom:1px solid var(--ttr-line);display:flex;justify-content:space-between;gap:8px;}
.ttr-h span{text-transform:none;letter-spacing:0;font-weight:600;color:#64748b;}
/* history */
.ttr-tl{display:flex;flex-direction:column;}
.ttr-ev{position:relative;padding:0 0 12px 18px;border-left:2px solid #e2e8f0;margin-left:6px;}
.ttr-ev:last-child{border-left-color:transparent;}
.ttr-ev::before{content:'';position:absolute;left:-7px;top:1px;width:12px;height:12px;border-radius:50%;background:#fff;border:2px solid #94a3b8;}
.ttr-ev.ok::before{border-color:#10b981;} .ttr-ev.bad::before{border-color:#ef4444;} .ttr-ev.warn::before{border-color:#f59e0b;background:#fef3c7;} .ttr-ev.info::before{border-color:#6366f1;}
.ttr-ev-at{font-size:11px;color:#64748b;}
.ttr-ev-t{font-weight:700;color:#0f172a;margin:1px 0 2px;}
.ttr-ev-l{font-size:11.5px;color:#475569;line-height:1.4;}
.ttr-ev-by{font-size:11px;color:#94a3b8;margin-top:2px;}
/* editor */
.ttr-band{border:1px solid var(--ttr-line);border-radius:12px;overflow:hidden;background:#fff;}
.ttr-band>header{display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;}
.ttr-band>header span{margin-left:auto;text-transform:none;letter-spacing:0;font-weight:600;font-size:11.5px;}
.ttr-band.t>header{background:#f1f5f9;color:#334155;}
.ttr-band.s>header{background:#fffbeb;color:#92400e;}
.ttr-band.a>header{background:#eef2ff;color:#3730a3;}
.ttr-band.c>header{background:#f0fdfa;color:#115e59;}
.ttr-band>.ttr-in{padding:12px;display:flex;flex-direction:column;gap:10px;}
.ttr-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.ttr-grid3{display:grid;grid-template-columns:1.2fr .9fr .9fr;gap:10px;}
.ttr-f{display:flex;flex-direction:column;gap:4px;min-width:0;}
.ttr-f label{font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ttr-f input,.ttr-f select{width:100%;box-sizing:border-box;height:34px;border:1px solid var(--ttr-line);border-radius:8px;padding:5px 9px;font-size:12.5px;color:#0f172a;background:#fff;}
.ttr-f input:focus,.ttr-f select:focus{outline:none;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
.ttr-f input[readonly],.ttr-f input:disabled,.ttr-f select:disabled{background:#f8fafc;color:#64748b;}
.ttr-f input.m{text-align:right;}
.ttr-f input.r{color:#dc2626;font-weight:700;}
.ttr-f.ch input,.ttr-f.ch select{border-color:#f59e0b;background:#fffbeb;}
.ttr-hint{font-size:11px;color:#94a3b8;}
.ttr-pay{display:grid;grid-template-columns:minmax(0,1.3fr) 110px 130px 28px;gap:8px;align-items:end;}
.ttr-pay.wallet{grid-template-columns:minmax(0,1.3fr) 110px 130px 28px;}
.ttr-del{width:28px;height:34px;border:1px solid #fecaca;background:#fff;color:#dc2626;border-radius:8px;cursor:pointer;}
.ttr-add{align-self:flex-start;border:1px dashed #a5b4fc;background:#fff;color:#4338ca;border-radius:8px;padding:6px 11px;font-size:12px;font-weight:700;cursor:pointer;}
.ttr-card{border:1px solid var(--ttr-line);border-left:4px solid #14b8a6;border-radius:10px;padding:10px 12px;display:flex;flex-direction:column;gap:9px;}
.ttr-card.k-refund,.ttr-card.k-void,.ttr-card.k-cancel,.ttr-card.k-return_cancel{border-left-color:#ef4444;}
.ttr-card.gone{opacity:.55;background:#f8fafc;}
.ttr-card.gone .ttr-cbody{display:none;}
.ttr-chead{display:flex;align-items:center;gap:8px;}
.ttr-chead b{color:#0f172a;}
.ttr-chead small{color:#94a3b8;}
.ttr-chead select{height:30px;border:1px solid var(--ttr-line);border-radius:7px;font-size:12px;font-weight:700;color:#0f172a;padding:2px 6px;margin-left:auto;}
.ttr-rm{border:1px solid #fecaca;background:#fff;color:#dc2626;border-radius:7px;padding:4px 9px;font-size:11.5px;font-weight:700;cursor:pointer;white-space:nowrap;}
.ttr-card.gone .ttr-rm{border-color:#c7d2fe;color:#4338ca;}
.ttr-cbody{display:flex;flex-direction:column;gap:9px;}
.ttr-who{display:flex;gap:6px;}
.ttr-who button{flex:1;border:1px solid var(--ttr-line);background:#fff;border-radius:999px;height:34px;font-size:11.5px;font-weight:600;color:#475569;cursor:pointer;white-space:nowrap;}
.ttr-who button.on[data-b="customer"]{background:#0f766e;border-color:#0f766e;color:#fff;}
.ttr-who button.on[data-b="company"]{background:#334155;border-color:#334155;color:#fff;}
.ttr-who button:disabled{opacity:.45;cursor:not-allowed;}
.ttr-tick{display:flex;align-items:center;gap:7px;font-size:12px;color:#334155;cursor:pointer;}
/* right side */
.ttr-sum{border:1px solid #c7d2fe;background:#eef2ff;border-radius:10px;padding:9px 12px;color:#312e81;}
.ttr-sum ol{margin:4px 0 0;padding-left:17px;display:flex;flex-direction:column;gap:3px;}
.ttr-err{border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:10px;padding:10px 12px;}
.ttr-wait{color:#64748b;font-style:italic;}
.ttr-led{border:1px solid var(--ttr-line);border-radius:10px;overflow:hidden;}
.ttr-led.ch{border-color:#fcd34d;}
.ttr-led>header{display:flex;align-items:baseline;gap:8px;padding:7px 11px;background:#f8fafc;border-bottom:1px solid var(--ttr-line);}
.ttr-led>header b{color:#0f172a;}
.ttr-led>header .k{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b;}
.ttr-led>header .net{margin-left:auto;font-weight:700;color:#0f172a;white-space:nowrap;}
.ttr-row{display:grid;grid-template-columns:64px minmax(0,1fr) auto;gap:8px;padding:6px 11px;border-top:1px dashed #eef2f7;align-items:baseline;}
.ttr-row:first-of-type{border-top:0;}
.ttr-row .d{font-size:11px;color:#64748b;white-space:nowrap;}
.ttr-row .w{min-width:0;}
.ttr-row .w small{display:block;color:#94a3b8;font-size:10.5px;}
.ttr-row .a{font-weight:700;white-space:nowrap;text-align:right;}
.ttr-row .a .dr{color:#b91c1c;} .ttr-row .a .cr{color:#047857;}
.ttr-row.same{color:#94a3b8;} .ttr-row.same .a{font-weight:600;color:#94a3b8;}
.ttr-row.removed{background:#fef2f2;} .ttr-row.removed .w,.ttr-row.removed .a{text-decoration:line-through;color:#b91c1c;}
.ttr-row.added{background:#f0fdf4;} .ttr-row.added .a{color:#047857;}
.ttr-row.changed,.ttr-row.moved{background:#fffbeb;}
.ttr-tag{display:inline-block;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;padding:1px 6px;border-radius:999px;margin-right:4px;vertical-align:1px;}
.ttr-tag.removed{background:#fee2e2;color:#b91c1c;} .ttr-tag.added{background:#dcfce7;color:#15803d;} .ttr-tag.changed,.ttr-tag.moved{background:#fef3c7;color:#92400e;}
.ttr-acc{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:4px 12px;font-size:12px;padding:8px 11px;}
.ttr-acc .ch{color:#92400e;font-weight:700;}
.ttr-acc .same{color:#94a3b8;}
/* footer */
.ttr-foot{flex:none;display:flex;align-items:flex-end;gap:12px;padding:12px 22px 16px;border-top:1px solid var(--ttr-line);margin-top:12px;background:#fcfcfe;}
.ttr-reason{flex:1 1 auto;display:flex;flex-direction:column;gap:4px;border:1px solid #fed7aa;background:#fffaf0;border-radius:10px;padding:8px 10px;}
.ttr-reason label,.ttr-confirm label{font-size:10px;font-weight:800;text-transform:uppercase;color:#9a3412;letter-spacing:.04em;}
.ttr-reason textarea{border:1px solid #fdba74;border-radius:7px;padding:6px 8px;font-size:12.5px;height:38px;resize:none;}
.ttr-confirm{flex:none;width:170px;display:flex;flex-direction:column;gap:4px;border:1px dashed #fb923c;background:#fff7ed;border-radius:10px;padding:8px 10px;}
.ttr-confirm input{border:1px solid #fdba74;border-radius:7px;height:32px;padding:4px 8px;font-weight:800;letter-spacing:.06em;}
.ttr-btns{flex:none;display:flex;flex-direction:column;gap:6px;align-items:stretch;}
.ttr-go{border:0;background:#16a34a;color:#fff;font-weight:800;font-size:13px;border-radius:10px;padding:11px 18px;cursor:pointer;box-shadow:0 6px 16px rgba(22,163,74,.35);animation:ttrPulse 1.8s ease-in-out infinite;}
.ttr-go:disabled{background:#94a3b8;box-shadow:none;animation:none;cursor:not-allowed;}
@keyframes ttrPulse{0%,100%{box-shadow:0 6px 16px rgba(22,163,74,.35);}50%{box-shadow:0 6px 22px rgba(22,163,74,.6);}}
.ttr-reset{border:1px solid var(--ttr-line);background:#fff;color:#475569;border-radius:10px;padding:7px 12px;font-weight:700;cursor:pointer;font-size:12px;}
.ttr-notify{display:flex;align-items:center;gap:6px;font-size:11.5px;color:#475569;white-space:nowrap;}
.ttr-idle{flex:1;color:#94a3b8;font-size:12px;align-self:center;}
@media (max-width:1100px){.ttr-body{grid-template-columns:1fr;overflow:auto;}.ttr-col{overflow:visible;}.ttr-foot{flex-wrap:wrap;}}
</style>
<script>
(function () {
    const URLS = @json($ttrUrls);
    const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const tk = n => '৳' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const day = (s, withTime = true) => {
        const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
        if (!m) return s ? String(s) : '—';
        const t = m[4] ? m[4] + ':' + m[5] : '00:00';
        return m[3] + ' ' + MON[+m[2] - 1] + ' ' + m[1] + (withTime && t !== '00:00' ? ', ' + t : '');
    };
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const clone = o => JSON.parse(JSON.stringify(o));
    const KIND = { reissue: 'Re-issue', reschedule: 'Reschedule', refund: 'Refund', void: 'Void', cancel: 'Cancelled', return_cancel: 'Cancel return', newticket: 'New ticket' };
    const KINDS_FOR = k => k === 'newticket' ? ['newticket'] : k === 'return_cancel' ? ['return_cancel'] : ['reissue', 'reschedule', 'refund', 'void', 'cancel'];
    let S = null;

    window.ticketTrailOpen = function (id) {
        Swal.fire({ title: 'Loading the trail…', didOpen: () => Swal.showLoading(), allowOutsideClick: false, showConfirmButton: false });
        fetch(URLS.show.replace('__ID__', id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(r => r.json().then(j => ({ ok: r.ok, j })))
            .then(({ ok, j }) => {
                if (!ok || !j.success) throw new Error(j.message || 'Could not load the trail.');
                Swal.close();
                S = { id, data: j, model: clone(j.state), base: JSON.stringify(j.state), baseObj: clone(j.state), preview: null, error: null, busy: false, timer: null, seq: 0 };
                frame();
                paint();
            })
            .catch(err => Swal.fire({ icon: 'error', title: 'Ticket trail', text: err.message }));
    };

    function frame() {
        dropFrame();
        const d = S.data, t = d.state.ticket, sale = d.state.sale;
        const el = document.createElement('div');
        el.className = 'ttr-back';
        el.innerHTML = `<div class="ttr-pop" role="dialog" aria-modal="true" aria-label="Ticket trail">
            <div class="ttr-top"><div class="ttr-title"><i class="fas fa-clock-rotate-left"></i> Ticket trail <span class="ttr-pnr">${esc(t.ticket_no)}</span></div>
                <button type="button" class="ttr-x" title="Close" aria-label="Close">&times;</button></div>
            <div class="ttr-head">
                <span><b>${esc(t.passenger || '—')}</b>${t.passport ? ' · ' + esc(t.passport) : ''}</span>
                <span>${esc(t.route || '—')}</span>
                <span>Bought for <b>${esc(day(t.travel_at))}</b></span>
                <span>Cost <b>${tk(t.amount)}</b></span>
                <span>Seller <b>${esc(t.seller_name || '—')}</b></span>
                ${sale ? `<span>Sold to <b>${esc(sale.client)}</b> · ${tk(sale.price)} · ${esc(sale.invoice)}${sale.tickets > 1 ? ' (' + sale.tickets + ' tickets)' : ''}</span>` : '<span style="color:#94a3b8">not sold</span>'}
                <span>Status <b>${t.status === 'cancelled' ? 'Cancelled' : 'Purchased'}</b></span>
            </div>
            ${d.blocked ? `<div class="ttr-block"><i class="fas fa-lock"></i> ${esc(d.blocked)}</div>` : ''}
            <div class="ttr-body">
                <section class="ttr-col" id="ttrHist"></section>
                <section class="ttr-col" id="ttrEdit"></section>
                <section class="ttr-col" id="ttrSide"></section>
            </div>
            <div class="ttr-foot" id="ttrFoot"></div>
        </div>`;
        document.body.appendChild(el);
        lockPage(true);
        el.querySelector('.ttr-x').addEventListener('click', close);
        // edits: one delegated listener for the whole editor
        const ed = el.querySelector('#ttrEdit');
        ed.addEventListener('input', onEdit);
        ed.addEventListener('change', onEdit);
        ed.addEventListener('click', onClick);
    }

    function close() {
        if (S && dirty() && !confirm('Close without posting? Your edits are not saved.')) return;
        dropFrame();
        lockPage(false);
        S = null;
    }

    // The page behind stays still while the Trail is open (owner, 2026-10-26, asked
    // twice: its scrollbar must not work). The class rule is !important, so neither
    // another script's inline overflow nor SweetAlert handing the page back after
    // Change / the loading box closes can undo it; the scrollbar's width is padded
    // back the same way, so the page does not jump sideways when the bar goes.
    const barWidth = () => {
        const d = document.createElement('div');
        d.style.cssText = 'position:absolute;top:-9999px;width:100px;height:100px;overflow:scroll';
        document.body.appendChild(d);
        const w = d.offsetWidth - d.clientWidth;
        d.remove();
        return w;
    };
    function lockPage(on) {
        const html = document.documentElement;
        if (on) {
            html.style.setProperty('--ttr-bar', html.scrollHeight > window.innerHeight ? barWidth() + 'px' : '0px');
            html.classList.add('ttr-lock');
        } else {
            html.classList.remove('ttr-lock');
            html.style.removeProperty('--ttr-bar');
        }
    }

    const dirty = () => S && JSON.stringify(S.model) !== S.base;

    // ── reading edits ──
    function setPath(path, value) {
        const keys = path.split('.');
        let o = S.model;
        for (let i = 0; i < keys.length - 1; i++) o = o[keys[i]];
        o[keys[keys.length - 1]] = value;
    }
    function onEdit(e) {
        const f = e.target.closest('[data-f]');
        if (!f) return;
        if (f.tagName === 'SELECT' && e.type === 'input') return;   // a select is read once, on change
        let v = f.type === 'checkbox' ? f.checked : f.value;
        if (f.dataset.num !== undefined) v = v === '' ? null : Number(v);
        if (f.type === 'datetime-local') v = v ? v.replace('T', ' ') : null;
        if (f.dataset.f === 'sale.client_id') {
            // the buyer decides which money fields can be typed: a redraw
            if (!v) S.model.sale = null;
            else { S.model.sale = S.model.sale || { client_id: null, price: null }; S.model.sale.client_id = Number(v); }
            paintEdit();
            schedule();
            return;
        }
        if (f.dataset.f === 'sale.price' && !S.model.sale) S.model.sale = { client_id: null, price: null };
        if (/^events\.\d+\.kind$/.test(f.dataset.f)) {
            setPath(f.dataset.f, v);
            const ev = S.model.events[+f.dataset.f.split('.')[1]];
            if ((v === 'refund' || v === 'void' || v === 'cancel') && (ev.refund === null || ev.refund === undefined)) ev.refund = v === 'refund' ? null : S.model.ticket.amount;
            paintEdit();
            schedule();
            return;
        }
        setPath(f.dataset.f, v);
        // only a change of shape redraws the form; anything else is marked in place, so
        // focus and the typing stay where they are
        marks();
        schedule();
    }
    const getPath = (o, path) => path.split('.').reduce((x, k) => (x === null || x === undefined ? undefined : x[k]), o);
    const same = (a, b) => {
        const n = x => (x === '' || x === undefined) ? null : (typeof x === 'string' && x !== '' && !isNaN(x) && !/^\d{4}-/.test(x) ? Number(x) : x);
        return JSON.stringify(n(a)) === JSON.stringify(n(b));
    };
    function marks() {
        const box = document.getElementById('ttrEdit');
        box.querySelectorAll('[data-f]').forEach(el => {
            const w = el.closest('.ttr-f');
            if (w) w.classList.toggle('ch', !same(getPath(S.model, el.dataset.f), getPath(S.baseObj, el.dataset.f)));
        });
        const cost = Number(S.model.ticket.amount) || 0;
        box.querySelectorAll('[data-kept]').forEach(el => {
            const ev = S.model.events[+el.dataset.kept];
            el.value = ev.refund === null || ev.refund === undefined || ev.refund === '' ? '' : tk(Math.max(0, cost - Number(ev.refund)));
        });
        const paid = box.querySelector('[data-paidsum]');
        if (paid) paid.textContent = tk(S.model.payments.reduce((s2, p) => s2 + (Number(p.amount) || 0), 0)) + ' of ' + tk(cost);
    }
    function onClick(e) {
        const b = e.target.closest('button');
        if (!b) return;
        if (b.dataset.who) { setPath(b.dataset.who, b.dataset.b); paintEdit(); schedule(); }
        else if (b.dataset.rm !== undefined) { const ev = S.model.events[+b.dataset.rm]; ev.remove = !ev.remove; paintEdit(); schedule(); }
        else if (b.dataset.delpay !== undefined) { S.model.payments.splice(+b.dataset.delpay, 1); paintEdit(); schedule(); }
        else if (b.dataset.addpay !== undefined) {
            S.model.payments.push({ id: null, kind: 'bank', bank_id: null, amount: null, date: new Date().toISOString().slice(0, 10), method: 'bank_transfer', ref: null, at_purchase: false });
            paintEdit();
        }
    }

    // ── preview ──
    function schedule() {
        clearTimeout(S.timer);
        S.preview = null; S.error = null;
        if (!dirty()) { paintSide(); paintFoot(); return; }
        S.busy = true; paintSide(); paintFoot();
        S.timer = setTimeout(preview, 650);
    }
    function preview() {
        const seq = ++S.seq;
        post(URLS.preview, { edit: S.model }).then(({ ok, j }) => {
            if (!S || seq !== S.seq) return;
            S.busy = false;
            if (!ok || !j.success) { S.error = j.message || Object.values(j.errors || {})[0]?.[0] || 'This correction cannot be posted.'; S.preview = null; }
            else { S.preview = j; S.error = null; }
            paintSide(); paintFoot();
        }).catch(() => { if (!S) return; S.busy = false; S.error = 'Network error — try again.'; paintSide(); paintFoot(); });
    }
    function post(url, body) {
        return fetch(url.replace('__ID__', S.id), { method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(body) })
            .then(r => r.json().then(j => ({ ok: r.ok, j })));
    }

    // ── painting ──
    function paint() { paintHist(); paintEdit(); paintSide(); paintFoot(); }

    function paintHist() {
        const items = (S.data.timeline || []).slice().reverse();
        document.getElementById('ttrHist').innerHTML = `<div class="ttr-h">All history<span>${items.length} · newest first</span></div><div class="ttr-tl">`
            + (items.length ? items.map(h => `<div class="ttr-ev ${esc(h.tone)}"><div class="ttr-ev-at">${esc(day(h.at))}</div>
                <div class="ttr-ev-t"><i class="fas ${esc(h.icon)}" style="color:#94a3b8;margin-right:4px"></i>${esc(h.title)}</div>
                ${(h.lines || []).map(l => `<div class="ttr-ev-l">${esc(l)}</div>`).join('')}
                ${h.by ? `<div class="ttr-ev-by">by ${esc(h.by)}</div>` : ''}</div>`).join('') : '<div class="ttr-hint">Nothing recorded yet.</div>') + '</div>';
    }

    function opt(list, sel, blank) {
        return (blank !== undefined ? `<option value="">${esc(blank)}</option>` : '') + list.map(o => `<option value="${esc(o.id)}" ${String(o.id) === String(sel ?? '') ? 'selected' : ''}>${esc(o.text)}</option>`).join('');
    }
    function field(label, inner, changed, hint) {
        return `<div class="ttr-f ${changed ? 'ch' : ''}"><label title="${esc(label)}">${esc(label)}</label>${inner}${hint ? `<span class="ttr-hint">${hint}</span>` : ''}</div>`;
    }
    function money(path, value, opts = {}) {
        return `<input type="number" min="0" step="0.01" class="m ${opts.red ? 'r' : ''}" data-f="${path}" data-num ${opts.ro ? 'readonly' : ''} ${opts.off ? 'disabled' : ''} value="${value === null || value === undefined ? '' : Number(value).toFixed(2)}">`;
    }
    function who(path, value, canBill) {
        return `<div class="ttr-who"><button type="button" data-who="${path}" data-b="customer" class="${value === 'customer' ? 'on' : ''}" ${canBill ? '' : 'disabled title="Not sold — nobody to bill"'}>Agent pays</button>
            <button type="button" data-who="${path}" data-b="company" class="${value === 'company' ? 'on' : ''}">We bear it</button></div>`;
    }

    function paintEdit() {
        const box = document.getElementById('ttrEdit');
        const keep = box.scrollTop;
        const m = S.model, o = S.data.options, b = S.data.state, ro = !!S.data.blocked, dis = ro ? 'disabled' : '';
        const ch = (a, x) => JSON.stringify(a ?? null) !== JSON.stringify(x ?? null);
        const t = m.ticket, bt = b.ticket;
        const canBill = !!(m.sale && m.sale.client_id);
        const html = [];
        html.push(`<div class="ttr-h">Edit<span>${ro ? 'read only' : 'every field — the right side shows what changes'}</span></div>`);

        html.push(`<div class="ttr-band t"><header><i class="fas fa-ticket"></i> Ticket</header><div class="ttr-in">
            <div class="ttr-grid">${field('Passenger', `<select data-f="ticket.passport_holder_id" data-num ${dis || (b.cf ? 'disabled title="The flight passenger\'s passport — changed on his card"' : '')}>${opt(o.passengers, t.passport_holder_id)}</select>`, ch(t.passport_holder_id, bt.passport_holder_id))}
                ${field('Route', `<select data-f="ticket.ticket_id" data-num ${dis}>${opt(o.routes, t.ticket_id)}</select>`, ch(t.ticket_id, bt.ticket_id))}</div>
            <div class="ttr-grid3">${field('PNR', `<input type="text" data-f="ticket.ticket_no" value="${esc(t.ticket_no)}" ${dis}>`, ch(t.ticket_no, bt.ticket_no))}
                ${field('Class', `<select data-f="ticket.ticket_class" ${dis}>${opt([{ id: 'economy', text: 'Economy' }, { id: 'business', text: 'Business' }, { id: 'first', text: 'First' }], String(t.ticket_class || '').toLowerCase(), '—')}</select>`, ch(t.ticket_class, bt.ticket_class))}
                ${field('Trip', `<select data-f="ticket.trip_type" ${dis}>${opt([{ id: 'one-way', text: 'One way' }, { id: 'two-way', text: 'Round trip' }, { id: 'multi-city', text: 'Multi-city' }], t.trip_type, '—')}</select>`, ch(t.trip_type, bt.trip_type))}</div>
            <div class="ttr-grid">${field('Purchase date', `<input type="date" data-f="ticket.purchase_date" value="${esc(t.purchase_date || '')}" ${dis}>`, ch(t.purchase_date, bt.purchase_date))}
                ${field('Travel date as bought', `<input type="datetime-local" data-f="ticket.travel_at" value="${esc((t.travel_at || '').replace(' ', 'T'))}" ${dis}>`, ch(t.travel_at, bt.travel_at), 'a re-issue\'s new date is on that change')}</div>
        </div></div>`);

        html.push(`<div class="ttr-band s"><header><i class="fas fa-store"></i> Bought from &amp; cost</header><div class="ttr-in">
            <div class="ttr-grid">${field('Seller', `<select data-f="ticket.seller" ${dis}>${opt(o.sellers, t.seller)}</select>`, ch(t.seller, bt.seller))}
                ${field('Cost', money('ticket.amount', t.amount, { off: ro }), ch(t.amount, bt.amount))}</div>
            <div class="ttr-h" style="position:static;margin-top:2px">Paid to the seller for this ticket<span data-paidsum>${tk(m.payments.reduce((s, p) => s + (Number(p.amount) || 0), 0))} of ${tk(t.amount)}</span></div>
            ${m.payments.length ? m.payments.map((p, i) => p.kind === 'portal'
                ? `<div class="ttr-pay wallet">${field('From the wallet', `<input type="text" value="${esc(p.bank || 'Portal wallet')}" readonly>`)}${field('Amount', money(`payments.${i}.amount`, p.amount, { off: ro }))}${field('Date', `<input type="date" data-f="payments.${i}.date" value="${esc(p.date || '')}" ${dis}>`)}<span></span></div>`
                : `<div class="ttr-pay">${field('Bank', `<select data-f="payments.${i}.bank_id" data-num ${dis}>${opt(o.banks, p.bank_id, 'Choose a bank')}</select>`)}${field('Amount', money(`payments.${i}.amount`, p.amount, { off: ro }))}${field('Date', `<input type="date" data-f="payments.${i}.date" value="${esc(p.date || '')}" ${dis}>`)}
                    ${ro ? '<span></span>' : `<button type="button" class="ttr-del" data-delpay="${i}" title="Remove this payment"><i class="fas fa-trash"></i></button>`}</div>`).join('')
                : '<div class="ttr-hint">Nothing paid against this ticket itself. Payments to the seller as a whole (Party Statement) sit on the seller\'s ledger, not on one ticket.</div>'}
            ${ro ? '' : '<button type="button" class="ttr-add" data-addpay><i class="fas fa-plus"></i> Add a payment</button>'}
        </div></div>`);

        const s = m.sale, bs = b.sale, cf = b.cf;
        if (cf) {
            // on a contract flight: billed per head there — the agent and his package live on the passenger card
            html.push(`<div class="ttr-band a"><header><i class="fas fa-plane-departure"></i> Contract flight<span>${esc(cf.flight || '—')}</span></header><div class="ttr-in">
                <div class="ttr-grid">${field('Agent', `<input type="text" readonly value="${esc(cf.agent || '—')}">`)}${field('Package (per head)', `<input type="text" class="m" readonly value="${tk(cf.package)}">`)}</div>
                <div class="ttr-hint">Billed per head on the flight, not sold on its own. The agent, the package and the passenger are changed on his card in Flights &amp; Passengers; a change to this ticket's cost or seller flows into the flight's totals.</div>
            </div></div>`);
        } else {
            html.push(`<div class="ttr-band a"><header><i class="fas fa-user-tie"></i> Sold to<span>${bs ? esc(bs.invoice) + (bs.tickets > 1 ? ' · ' + bs.tickets + ' tickets on it' : '') : 'not sold'}</span></header><div class="ttr-in">
                <div class="ttr-grid">${field('Agent / customer', `<select data-f="sale.client_id" ${dis}>${opt(o.buyers, s ? s.client_id : '', '— not sold —')}</select>`, ch(s && s.client_id, bs && bs.client_id),
                        bs && bs.tickets > 1 && s && s.client_id && s.client_id !== bs.client_id ? 'moves to a new invoice for this agent — the other tickets stay on ' + esc(bs.invoice) : '')}
                    ${field('Agent price', money('sale.price', s ? s.price : null, { off: ro || !s }), ch(s && s.price, bs && bs.price))}</div>
            </div></div>`);
        }

        html.push(`<div class="ttr-band c"><header><i class="fas fa-arrows-rotate"></i> Changes<span>${m.events.length ? m.events.length + ' on this ticket' : 'none'}</span></header><div class="ttr-in">`
            + (m.events.length ? m.events.map((e, i) => card(e, i, b.events.find(x => x.key === e.key), canBill, ro)).join('')
                : `<div class="ttr-hint">${cf ? 'A contract-flight ticket is re-issued and refunded by the flight — its changes are in the history and on the right, and are edited on his flight trail (Flights & Passengers → Re-assign → All Trails and Edit).' : 'No re-issue, refund or void on this ticket. New changes are made from the Change dialog.'}</div>`)
            + '</div></div>');
        releasePickers(box);
        box.innerHTML = html.join('');
        box.scrollTop = keep;
    }

    // An open select2 pins the scroll of every scrollable box around it and lets go on close,
    // found by walking up from its own picker. Picking a change's kind or the buyer redraws this
    // column from the change event — before select2 closes — so it found nothing to let go of
    // and the columns stayed stuck (owner, 2026-10-26, live). Close it while it is still in the
    // page, and drop any pin already left behind.
    function releasePickers(box) {
        if (!window.jQuery) return;
        jQuery(box).find('select').each(function () {
            const s2 = jQuery(this).data('select2');
            if (s2 && s2.isOpen()) jQuery(this).select2('close');
        });
        jQuery('#ttrHist, #ttrEdit, #ttrSide, .ttr-body, .ttr-pop, .ttr-back').off('scroll.select2');
    }
    // the window goes the same way: a picker left open would keep its handler on the page
    function dropFrame() {
        const back = document.querySelector('.ttr-back');
        if (!back) return;
        releasePickers(back);
        back.remove();
    }

    function card(e, i, o, canBill, ro) {
        const p = `events.${i}`, dis = ro ? 'disabled' : '';
        const ch = f => o && JSON.stringify(o[f] ?? null) !== JSON.stringify(e[f] ?? null);
        const cost = Number(S.model.ticket.amount) || 0;
        const kinds = KINDS_FOR(o ? o.kind : e.kind);
        const f = [];
        f.push(`<div class="ttr-grid">${field('Posted on', `<input type="date" data-f="${p}.date" value="${esc(e.date || '')}" ${dis}>`, ch('date'), o && !ch('date') ? 'each row keeps its own date and time' : 'every row moves to this day')}`);
        if (e.kind === 'reissue' || e.kind === 'reschedule') {
            f.push(field('New travel date', `<input type="datetime-local" data-f="${p}.new_travel_at" value="${esc((e.new_travel_at || '').replace(' ', 'T'))}" ${dis}>`, ch('new_travel_at')) + '</div>');
            f.push(`<div class="ttr-grid">${field('Airline penalty — owed to the seller', money(p + '.penalty', e.penalty, { red: true, off: ro }), ch('penalty'))}${field('Who bears the penalty', who(p + '.penalty_bearer', e.penalty_bearer, canBill), ch('penalty_bearer'))}</div>`);
            if (Number(e.fare_difference) > 0 || (o && Number(o.fare_difference) > 0))
                f.push(`<div class="ttr-grid">${field('Fare difference', money(p + '.fare_difference', e.fare_difference, { red: true, off: ro }), ch('fare_difference'))}${field('Who bears it', who(p + '.fare_bearer', e.fare_bearer, canBill), ch('fare_bearer'))}</div>`);
            f.push(`<div class="ttr-grid">${field('Our charge — billed to the agent', money(p + '.service_fee', e.service_fee, { off: ro || !canBill }), ch('service_fee'))}<span></span></div>`);
        } else if (e.kind === 'refund' || e.kind === 'void' || e.kind === 'cancel') {
            const kept = e.refund === null || e.refund === undefined ? null : Math.max(0, cost - Number(e.refund));
            f.push(field('Airline refund', money(p + '.refund', e.refund, { off: ro }), ch('refund'), 'cost ' + tk(cost)) + '</div>');
            f.push(`<div class="ttr-grid">${field('Airline keeps', `<input type="text" class="m r" readonly data-kept="${i}" value="${kept === null ? '' : tk(kept)}">`)}${e.kind === 'cancel' ? '<span></span>' : field('Who bears what it keeps', who(p + '.penalty_bearer', e.penalty_bearer, canBill), ch('penalty_bearer'))}</div>`);
            if (e.kind !== 'cancel') {
                f.push(`<div class="ttr-grid">${field('Agent price taken back', money(p + '.agent_reversal', e.agent_reversal, { off: ro || !canBill }), ch('agent_reversal'), canBill ? 'of ' + tk(S.model.sale.price) : 'not sold')}
                    ${field('Our charge — billed to the agent', money(p + '.service_fee', e.service_fee, { off: ro || !canBill }), ch('service_fee'))}</div>`);
                if (e.penalty_bearer === 'customer' && kept > 0)
                    f.push(`<label class="ttr-tick"><input type="checkbox" data-f="${p}.penalty_billed" ${e.penalty_billed ? 'checked' : ''} ${dis}> Bill what the airline keeps on his line (untick when his smaller refund already covers it)</label>`);
            }
        } else if (e.kind === 'return_cancel') {
            f.push(field('Airline refund for the return', money(p + '.refund', e.refund, { off: ro }), ch('refund')) + '</div>');
            f.push(`<div class="ttr-grid">${field('Airline penalty', money(p + '.penalty', e.penalty, { red: true, off: ro }), ch('penalty'))}${field('Who bears it', who(p + '.penalty_bearer', e.penalty_bearer, canBill), ch('penalty_bearer'))}</div>`);
            f.push(`<div class="ttr-grid">${field('Given back to the agent', money(p + '.agent_reversal', e.agent_reversal, { off: ro || !canBill }), ch('agent_reversal'))}${field('Our charge', money(p + '.service_fee', e.service_fee, { off: ro || !canBill }), ch('service_fee'))}</div>`);
        } else if (e.kind === 'newticket') {
            f.push(field('New ticket', `<input type="text" readonly value="${esc(e.new_ticket || '—')}">`) + '</div>');
            f.push(`<div class="ttr-grid">${field('Fare difference (new − old)', `<input type="text" class="m r" readonly value="${tk(e.fare_difference)}">`)}${field('Who bears it', who(p + '.fare_bearer', e.fare_bearer, canBill), ch('fare_bearer'))}</div>`);
            f.push(`<div class="ttr-grid">${field('Our charge', money(p + '.service_fee', e.service_fee, { off: ro || !canBill }), ch('service_fee'))}<span></span></div>`);
        } else {
            f.push('<span></span></div>');
        }
        return `<div class="ttr-card k-${esc(e.kind)} ${e.remove ? 'gone' : ''}">
            <div class="ttr-chead"><b>Change ${i + 1}</b><small>${o ? esc(day(o.at)) + (o.by ? ' · ' + esc(o.by) : '') : 'new'}</small>
                <select data-f="${p}.kind" ${ro || kinds.length < 2 || e.remove ? 'disabled' : ''} aria-label="Kind of change">${kinds.map(k => `<option value="${k}" ${k === e.kind ? 'selected' : ''}>${KIND[k]}</option>`).join('')}</select>
                ${ro ? '' : `<button type="button" class="ttr-rm" data-rm="${i}">${e.remove ? '<i class="fas fa-rotate-left"></i> Keep' : '<i class="fas fa-trash"></i> Remove'}</button>`}</div>
            ${e.remove ? '<div class="ttr-hint">This change and everything it posted will be removed.</div>' : ''}
            <div class="ttr-cbody">${f.join('')}</div></div>`;
    }

    const ROW = r => ({
        'tx:ticket_purchase': r.bank ? 'Paid · ' + r.bank : 'Ticket cost', 'tx:ticket_purchase_refund': 'Airline refund',
        'tx:ticket_sale': r.bank ? 'Receipt · ' + r.bank : 'Invoice ' + (r.ref || '') + ' (whole invoice)',
        'tx:ticket_change_fee': 'Re-issue charge owed', 'tx:ticket_change_fare': 'Fare difference owed',
        'tx:ticket_change_charge': 'Change charge', 'tx:ticket_sale_reversal': 'Agent price taken back',
        'pb:ticket_purchase': 'Paid from the wallet', 'pb:ticket_purchase_refund': 'Refund into the wallet',
        'pb:ticket_change_fee': 'Re-issue charge from the wallet', 'pb:ticket_change_fare': 'Fare difference from the wallet',
        // the contract flight's own lines (whole passenger)
        'tx:contract_flight_passenger_agent': 'Package (per head)', 'tx:contract_flight_passenger_agent_reversal': r.credit > 0 ? 'Package reversed' : 'Package charged again',
        'tx:contract_flight_passenger_reissue': 'Re-issue charge (flight)', 'tx:contract_flight_passenger_change': 'Flight change charge',
        'tx:contract_flight_reissue_fee': 'Re-issue fee owed (flight)', 'pb:contract_flight_reissue_fee': 'Re-issue fee owed (flight)',
        'tx:contract_flight_reissue_fare': 'Fare difference owed (flight)', 'tx:contract_flight_service_cost': 'Trainer / consultant fee',
        'tx:contract_flight_airline_penalty': 'Airline penalty (flight)', 'tx:contract_flight_fare_difference': 'Fare difference (flight)',
        'tx:contract_flight_price_revision': 'Package revised', 'tx:contract_flight_deport_cost': 'Deport cost',
    })[r.src + ':' + r.type] || String(r.type).replace(/_/g, ' ');
    const EVT = (ev, events) => ({ purchase: 'Purchase', payment: 'Payment', sale: 'Sale', receipt: 'Receipt', cancel: 'Cancelled' })[ev] || ((events || []).find(e => e.key === ev) || {}).label || 'Change';
    const amt = (dr, cr) => dr > 0 ? `<span class="dr">Dr ${tk(dr)}</span>` : `<span class="cr">Cr ${tk(cr)}</span>`;
    const net = v => tk(Math.abs(v)) + (v > 0 ? ' Dr' : v < 0 ? ' Cr' : '');
    const KINDNAME = { seller: 'Seller', agent: 'Agent', portal: 'Portal wallet' };

    function paintSide() {
        const box = document.getElementById('ttrSide');
        const html = [];
        if (!dirty()) {
            const fp = S.data.footprint, tot = S.data.totals;
            html.push('<div class="ttr-h">Posted now<span>every ledger this ticket hit</span></div>');
            tot.ledgers.forEach(l => {
                const rows = fp.rows.filter(r => r.ledger === l.ledger);
                html.push(`<div class="ttr-led"><header><span class="k">${KINDNAME[l.kind] || ''}</span><b>${esc(l.name)}</b><span class="net">${net(l.debit - l.credit)}</span></header>`
                    + rows.map(r => `<div class="ttr-row"><span class="d">${esc(day(r.date, false))}</span><span class="w">${esc(ROW(r))}<small>${esc(EVT(r.event, fp.events))}${r.remarks ? ' · ' + esc(r.remarks) : ''}</small></span><span class="a">${amt(r.debit, r.credit)}</span></div>`).join('')
                    + '</div>');
            });
            if (!tot.ledgers.length) html.push('<div class="ttr-hint">Nothing posted on any ledger.</div>');
            html.push(`<div class="ttr-led"><header><span class="k">Journal</span><b>By account head</b></header><div class="ttr-acc">`
                + tot.accounts.map(a => `<span>${esc(a.account)}</span><b>${net(a.debit - a.credit)}</b>`).join('') + '</div></div>');
        } else if (S.busy) {
            html.push('<div class="ttr-h">What will change<span></span></div><div class="ttr-wait"><i class="fas fa-spinner fa-spin"></i> Working out what changes — the real postings, rolled back…</div>');
        } else if (S.error) {
            html.push(`<div class="ttr-h">What will change<span></span></div><div class="ttr-err"><i class="fas fa-triangle-exclamation"></i> ${esc(S.error)}</div>`);
        } else if (S.preview) {
            const pv = S.preview, d = pv.diff;
            html.push('<div class="ttr-h">What will change<span>old entries replaced</span></div>');
            if (pv.summary.length) html.push(`<div class="ttr-sum"><b>You are changing</b><ol>${pv.summary.map(x => `<li>${esc(x)}</li>`).join('')}</ol></div>`);
            const leds = d.ledgers.slice().sort((a, b) => (b.changed - a.changed));
            leds.forEach(l => {
                html.push(`<div class="ttr-led ${l.changed ? 'ch' : ''}"><header><span class="k">${KINDNAME[l.kind] || ''}</span><b>${esc(l.name)}</b><span class="net">${l.changed ? net(l.net_before) + ' → ' + net(l.net_after) : net(l.net_after)}</span></header>`
                    + l.rows.map(r => {
                        const tag = r.status === 'same' ? '' : `<span class="ttr-tag ${r.status}">${({ removed: 'removed', added: 'new', changed: 'changed', moved: 'date' })[r.status]}</span>`;
                        const a = r.status === 'changed' ? `${tk(r.before)} → ${tk(r.after)}` : tk(r.after ?? r.before);
                        const dt = r.status === 'moved' ? day(r.date_before, false) + ' → ' + day(r.date_after, false) : day(r.date_after || r.date_before, false);
                        return `<div class="ttr-row ${r.status}"><span class="d">${esc(dt)}</span><span class="w">${tag}${esc(r.label)}<small>${esc(r.event)}${r.shared ? ' · whole invoice' : ''}</small></span><span class="a">${r.side} ${a}</span></div>`;
                    }).join('') + '</div>');
            });
            const accCh = d.accounts.filter(a => a.changed), accSame = d.accounts.length - accCh.length;
            html.push(`<div class="ttr-led ${accCh.length ? 'ch' : ''}"><header><span class="k">Journal</span><b>Account heads</b><span class="net">${accCh.length ? accCh.length + ' change' + (accCh.length > 1 ? 's' : '') : 'unchanged'}</span></header><div class="ttr-acc">`
                + accCh.map(a => `<span class="ch">${esc(a.account)}</span><b class="ch">${net(a.before)} → ${net(a.after)}</b>`).join('')
                + (accSame ? `<span class="same">${accSame} other head${accSame > 1 ? 's' : ''} unchanged</span><span></span>` : '') + '</div></div>');
            if (!d.changed) html.push('<div class="ttr-hint">The books come out exactly the same.</div>');
        }
        box.innerHTML = html.join('');
    }

    function paintFoot() {
        const foot = document.getElementById('ttrFoot');
        if (S.data.blocked) { foot.innerHTML = '<span class="ttr-idle">Read only.</span><button type="button" class="ttr-reset" data-close>Close</button>'; foot.querySelector('[data-close]').onclick = close; return; }
        if (!dirty()) { foot.innerHTML = '<span class="ttr-idle"><i class="fas fa-pen" style="margin-right:5px"></i>Change anything on the left — the right side shows where it hits before anything is posted.</span>'; return; }
        const ready = S.preview && !S.error && !S.busy;
        const keep = { r: foot.querySelector('#ttrReason')?.value || '', c: foot.querySelector('#ttrConfirm')?.value || '', n: foot.querySelector('#ttrNotify')?.checked || false };
        foot.innerHTML = `<div class="ttr-reason"><label for="ttrReason">Reason — kept in the history</label><textarea id="ttrReason" maxlength="1000" placeholder="Why is this being corrected?">${esc(keep.r)}</textarea></div>
            <div class="ttr-confirm"><label for="ttrConfirm">Type CONFIRM</label><input id="ttrConfirm" autocomplete="off" value="${esc(keep.c)}"></div>
            <div class="ttr-btns"><label class="ttr-notify"><input type="checkbox" id="ttrNotify" ${keep.n ? 'checked' : ''}> Email / SMS the parties</label>
                <button type="button" class="ttr-reset" id="ttrReset"><i class="fas fa-rotate-left"></i> Undo all edits</button></div>
            <button type="button" class="ttr-go" id="ttrGo" ${ready ? '' : 'disabled'}><i class="fas fa-check"></i> Confirm &amp; post</button>`;
        const go = foot.querySelector('#ttrGo');
        const check = () => { go.disabled = !(ready && foot.querySelector('#ttrReason').value.trim().length >= 5 && foot.querySelector('#ttrConfirm').value.trim() === 'CONFIRM'); };
        foot.querySelector('#ttrReason').addEventListener('input', check);
        foot.querySelector('#ttrConfirm').addEventListener('input', check);
        check();
        foot.querySelector('#ttrReset').onclick = () => { S.model = JSON.parse(S.base); paintEdit(); schedule(); };
        go.onclick = () => {
            go.disabled = true; go.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting…';
            post(URLS.apply, { edit: S.model, reason: foot.querySelector('#ttrReason').value.trim(), confirm: foot.querySelector('#ttrConfirm').value.trim(), notify: foot.querySelector('#ttrNotify').checked })
                .then(({ ok, j }) => {
                    if (!ok || !j.success) { S.error = j.message || Object.values(j.errors || {})[0]?.[0] || 'Nothing was posted.'; S.preview = null; paintSide(); paintFoot(); return; }
                    dropFrame(); lockPage(false); S = null;
                    Swal.fire({ icon: 'success', title: 'Corrected', text: j.message, confirmButtonText: 'OK' }).then(() => window.location.reload());
                })
                .catch(() => { go.disabled = false; go.innerHTML = '<i class="fas fa-check"></i> Confirm &amp; post'; S.error = 'Network error — nothing was posted.'; paintSide(); });
        };
    }
})();
</script>
