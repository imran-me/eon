{{--
    Contract Flight Trail (owner, 2026-11-01): one passenger's every entry — package and each change —
    with what it posted, editable, and corrected by REPLACING its rows (App\Services\ContractFlightTrail).
    Opened from the Re-assign window's header ("All Trails and Edit") and from Flight changes → Edit.
    Super admin only; included by partials.reassign-modal only when the service and its routes exist.
    Closes only on the ✕; the page behind does not scroll while it is open.
--}}
@php
    $cftArgs = ['role' => $role, 'contractFlightPassenger' => '__ID__'];
    $cftUrls = [
        'show' => route('role.contract-flight-trail.show', $cftArgs),
        'preview' => route('role.contract-flight-trail.preview', $cftArgs),
        'apply' => route('role.contract-flight-trail.apply', $cftArgs),
    ];
@endphp
<style>
html.cft-lock,html.cft-lock body{overflow:hidden !important;}
html.cft-lock body{padding-right:var(--cft-bar,0px) !important;}
/* above the page's chat bubble (9999); SweetAlert above the Trail while it is open */
html.cft-lock .swal2-container{z-index:10060 !important;}
.cft-ov{position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;padding:8px 12px;box-sizing:border-box;}
.cft-ov[hidden]{display:none;}
/* near the whole window (owner, 2026-11-01: "make the modal more bigger, making space for Every entry") */
.cft-box{width:min(1760px,calc(100vw - 24px));height:calc(100vh - 16px);background:#fff;border-radius:16px;box-shadow:0 24px 60px rgba(15,23,42,.3);display:flex;flex-direction:column;overflow:hidden;font-size:13px;color:#0f172a;}
.cft-box *,.cft-box *::before,.cft-box *::after{box-sizing:border-box;}
.cft-hd{display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid #c7d2fe;background:linear-gradient(90deg,#eef2ff,#f5f3ff);}
.cft-hd .t{font-size:15px;font-weight:700;color:#3730a3;}
.cft-hd .s{font-size:12px;color:#64748b;margin-top:2px;}
.cft-x{margin-left:auto;border:0;background:transparent;font-size:16px;color:#64748b;cursor:pointer;padding:4px 8px;border-radius:8px;}
.cft-x:hover{background:#f1f5f9;color:#0f172a;}
/* the owner's own split, drawn on his screenshot (2026-11-01): entries ~28% · editor ~43% · what will change ~29% */
.cft-body{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,0.96fr) minmax(0,1.5fr) minmax(0,1fr);}
.cft-list{overflow:auto;border-right:1px solid #d2d8e7;background:#fafbff;padding:12px;}
.cft-ed{overflow:auto;padding:14px 18px;border-right:1px solid #d2d8e7;}
.cft-side{overflow:auto;padding:14px 18px;background:#fafbff;}
.cft-lh{font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#64748b;margin:2px 2px 8px;}
.cft-it{position:relative;border:1px solid #d2d8e7;border-radius:10px;background:#fff;padding:8px 10px;margin-bottom:8px;cursor:pointer;}
.cft-it:hover{border-color:#a5b4fc;}
.cft-it.on{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
.cft-it .a{font-weight:700;font-size:12.5px;padding-right:66px;}
.cft-it .b{font-size:11px;color:#64748b;margin-top:2px;}
.cft-it .m{font-size:11.5px;margin-top:4px;display:flex;flex-wrap:wrap;gap:4px 10px;}
/* the timeline (owner, 2026-11-01: "timeline wise, and session wise … via which functionalities") */
.cft-sess{margin-bottom:14px;}
.cft-sess-h{position:sticky;top:-12px;z-index:2;background:#eef2ff;border:1px solid #c7d2fe;border-radius:9px;padding:6px 9px;margin-bottom:7px;font-size:11.5px;color:#3730a3;box-shadow:0 2px 6px rgba(15,23,42,.06);}
.cft-sess-h .w{font-weight:700;}
.cft-via{display:inline-block;font-size:10px;font-weight:600;background:#fff;border:1px solid #c7d2fe;border-radius:999px;padding:0 7px;margin:4px 4px 0 0;color:#4338ca;}
.cft-tl-row{display:grid;grid-template-columns:54px minmax(0,1fr);gap:6px;align-items:start;}
.cft-tl-t{font-size:10.5px;color:#64748b;font-weight:600;padding-top:9px;text-align:right;white-space:nowrap;}
.cft-log{font-size:11.5px;color:#475569;padding:5px 9px;border-left:3px solid #d2d8e7;margin:0 0 7px;background:#fff;border-radius:0 8px 8px 0;overflow-wrap:anywhere;}
.cft-log b{color:#334155;font-weight:700;}
.cft-log.is-stage{color:#94a3b8;border-left-color:#e2e8f0;background:transparent;}
.cft-log.is-link{cursor:pointer;border-left-color:#a5b4fc;}
.cft-log.is-link:hover{background:#f5f3ff;}
.cft-it .via{font-size:11px;color:#4338ca;margin-top:3px;}
.cft-chip{display:inline-block;font-size:10px;font-weight:700;border-radius:999px;padding:1px 7px;border:1px solid #d2d8e7;color:#64748b;background:#fff;}
.cft-grp{margin:4px 0 6px;font-size:10.5px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;color:#475569;}
.cft-grp span{font-weight:600;letter-spacing:0;text-transform:none;color:#94a3b8;margin-left:4px;}
.cft-line{padding-left:36px;}
.cft-no{position:absolute;left:9px;top:9px;width:20px;height:20px;border-radius:50%;background:#eef2ff;color:#4338ca;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;}
.cft-tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
.cft-tab{min-height:28px;padding:4px 11px;border:1px solid #d2d8e7;border-radius:999px;background:#fff;color:#475569;font-size:11.5px;font-weight:700;cursor:pointer;}
.cft-tab.on{background:#4f46e5;border-color:#4f46e5;color:#fff;}
.cft-mode{display:block;margin:6px 0;min-height:28px;border:0;background:none;color:#4f46e5;font-size:11.5px;font-weight:700;text-decoration:underline;cursor:pointer;}
.cft-lnh{display:flex;gap:8px;align-items:flex-start;padding-right:0 !important;}
.cft-lnh>span{flex:1;min-width:0;overflow-wrap:anywhere;}
.cft-lnh .cft-undo-btn{position:static;flex:none;}
.cft-parts{color:#4338ca;font-weight:700;}
.cft-why{margin-top:4px;font-size:10.5px;color:#94a3b8;}
.cft-undo-btn{position:absolute;top:6px;right:8px;display:inline-flex;align-items:center;gap:5px;min-height:28px;padding:3px 10px;border:1px solid #fca5a5;border-radius:7px;background:#fef2f2;color:#b91c1c;font-size:11px;font-weight:700;cursor:pointer;}
.cft-undo-btn:disabled{border-color:#e2e8f0;background:#f8fafc;color:#94a3b8;cursor:not-allowed;}
.cft-undo-btn:not(:disabled):hover{background:#dc2626;border-color:#dc2626;color:#fff;}
.cft-chip.ok{color:#047857;border-color:#a7f3d0;background:#ecfdf5;}
.cft-card{border:1px solid #d2d8e7;border-radius:12px;padding:0 14px 12px;margin-bottom:12px;overflow:hidden;}
.cft-card h4{margin:0 -14px 10px;padding:8px 14px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;display:flex;align-items:center;gap:8px;background:#f1f5f9;color:#334155;border-bottom:1px solid #e2e8f0;}
.cft-card.c-agent h4{background:#eef2ff;color:#3730a3;border-color:#c7d2fe;}
.cft-card.c-seller h4{background:#fffbeb;color:#92400e;border-color:#fde68a;}
.cft-card.c-ours h4{background:#ecfdf5;color:#047857;border-color:#a7f3d0;}
.cft-card.c-kept h4{background:#fff1f2;color:#be123c;border-color:#fecdd3;}
.cft-card.c-fare h4{background:#f0f9ff;color:#0369a1;border-color:#bae6fd;}
.cft-card.c-del h4{background:#fef2f2;color:#b91c1c;border-color:#fecaca;}
.cft-lbl{display:block;font-size:10.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px;}
.cft-in,.cft-sel{width:100%;height:34px;border:1px solid #d2d8e7;border-radius:8px;padding:5px 9px;font-size:13px;background:#fff;font-family:inherit;}
.cft-in:focus,.cft-sel:focus{outline:0;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
.cft-in.num{text-align:right;font-variant-numeric:tabular-nums;}
.cft-row2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px;align-items:end;}
.cft-tbl{width:100%;border-collapse:collapse;table-layout:fixed;}
.cft-tbl th{font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:#94a3b8;text-align:left;padding:4px 5px 6px;}
.cft-tbl td{padding:4px 5px;vertical-align:middle;border-top:1px solid #eef1f7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.cft-tbl .r{text-align:right;font-variant-numeric:tabular-nums;}
.cft-tbl .pnr{font-weight:600;}
.cft-hint{font-size:11.5px;color:#64748b;margin-top:6px;}
.cft-err{padding:10px 12px;border-radius:10px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;font-size:12.5px;}
.cft-note{padding:9px 12px;border-radius:10px;border:1px solid #d2d8e7;background:#f8fafc;color:#475569;font-size:12px;}
.cft-del-btn{border:1px solid #fecaca;background:#fff;color:#b91c1c;border-radius:8px;padding:6px 12px;font-weight:700;font-size:12px;cursor:pointer;}
.cft-del-btn.on{background:#b91c1c;color:#fff;border-color:#b91c1c;}
.cft-side h5{margin:-14px -18px 12px;padding:10px 18px;font-size:12px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#fff;background:linear-gradient(90deg,#4f46e5,#7c3aed);}
.cft-sum{margin:0 0 12px;padding:9px 12px 9px 26px;border:1px solid #ddd6fe;border-left:4px solid #7c3aed;border-radius:10px;background:#fff;font-size:12.5px;}
.cft-led{border:1px solid #d2d8e7;border-radius:10px;background:#fff;margin-bottom:10px;overflow:hidden;}
.cft-led .h{display:flex;gap:8px;align-items:baseline;padding:7px 10px;background:#f8fafc;border-bottom:1px solid #eef1f7;font-weight:700;font-size:12.5px;}
.cft-led .h .n{margin-left:auto;font-variant-numeric:tabular-nums;white-space:nowrap;}
.cft-led .k{font-size:10px;font-weight:700;text-transform:uppercase;color:#94a3b8;}
.cft-dr{display:grid;grid-template-columns:62px minmax(0,1fr) 92px 92px;gap:2px 8px;padding:5px 10px;font-size:11.5px;border-top:1px dashed #eef1f7;align-items:baseline;}
.cft-dr .st{font-size:10px;font-weight:700;text-transform:uppercase;}
.cft-dr .v{text-align:right;font-variant-numeric:tabular-nums;}
.st-same{color:#94a3b8;} .st-changed,.st-moved{color:#b45309;} .st-added{color:#15803d;} .st-removed{color:#b91c1c;}
.cft-dr.s-removed .v.after,.cft-dr.s-removed .lab{text-decoration:line-through;color:#94a3b8;}
.cft-acc{width:100%;border-collapse:collapse;font-size:12px;}
.cft-acc td,.cft-acc th{padding:4px 6px;border-top:1px solid #eef1f7;}
.cft-acc th{font-size:10px;text-transform:uppercase;color:#94a3b8;text-align:left;}
.cft-acc .r{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap;}
.cft-acc tr.ch td{background:#fffbeb;font-weight:600;}
.cft-tot{margin-top:12px;padding:10px 12px;border-radius:10px;border:1px solid #bbf7d0;background:#f0fdf4;font-size:12.5px;display:flex;gap:14px;flex-wrap:wrap;}
.cft-empty{color:#64748b;font-size:12.5px;}
.cft-ft{display:flex;align-items:flex-end;gap:12px;padding:10px 20px;border-top:1px solid #d2d8e7;background:#fafbff;}
.cft-ft .grow{flex:1;}
.cft-post{border:0;border-radius:10px;padding:10px 20px;font-weight:700;font-size:13.5px;color:#fff;background:#16a34a;box-shadow:0 6px 16px rgba(22,163,74,.35);cursor:pointer;white-space:nowrap;}
.cft-post.del{background:#b91c1c;box-shadow:0 6px 16px rgba(185,28,28,.3);}
.cft-post:disabled{background:#94a3b8;box-shadow:none;cursor:not-allowed;}
.cft-posted td{font-size:11.5px;}
.cft-part{border-top:1px solid #eef1f7;padding:8px 0;}
.cft-part:first-of-type{border-top:0;}
.cft-part-h{font-size:12px;margin-bottom:5px;color:#475569;}
.cft-part-g{display:grid;grid-template-columns:minmax(0,1fr) 112px 136px;gap:8px;align-items:end;}
#cftModal .select2-container{width:100%!important;}
#cftModal .select2-container .select2-selection--single{height:34px;border-color:#d2d8e7;border-radius:8px;}
#cftModal .select2-container .select2-selection--single .select2-selection__rendered{line-height:32px;font-size:13px;}
@media (max-width:1100px){.cft-body{grid-template-columns:280px minmax(0,1fr);}.cft-side{grid-column:1 / -1;border-top:1px solid #d2d8e7;}}
</style>
<div id="cftModal" class="cft-ov" hidden role="dialog" aria-modal="true" aria-labelledby="cftTitle">
    <div class="cft-box">
        <div class="cft-hd">
            <div style="min-width:0"><div class="t" id="cftTitle">All Trails and Edit</div><div class="s" id="cftSub"></div></div>
            <button type="button" class="cft-x" onclick="cftClose()" title="Close">✕</button>
        </div>
        <div class="cft-body">
            <div class="cft-list"><div class="cft-lh">Timeline · by session, newest first — click an entry to edit</div><div id="cftList"></div></div>
            <div class="cft-ed" id="cftEd"></div>
            <div class="cft-side"><h5>What will change</h5><div id="cftDiff"><div class="cft-empty">Change a box — every ledger and account it hits is shown here, before and after, before anything posts.</div></div></div>
        </div>
        <div class="cft-ft">
            <div class="grow"><label class="cft-lbl" for="cftReason">Why it is corrected</label><input type="text" id="cftReason" class="cft-in" maxlength="300"></div>
            <button type="button" class="cft-post" id="cftPost" disabled onclick="cftPost()"><i class="fas fa-check"></i> Confirm &amp; replace</button>
        </div>
    </div>
</div>
<script>
(function () {
    const URLS = @json($cftUrls);
    const esc = s => $('<div>').text(s == null ? '' : String(s)).html();
    const taka = v => '৳' + (Number(v) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const csrf = () => $('meta[name="csrf-token"]').attr('content');
    const fail = (x, fb) => (x && x.responseJSON && (x.responseJSON.message || Object.values(x.responseJSON.errors || {})[0]?.[0])) || fb;
    let T = null, PID = null, E = null, del = false, timer = null, seq = 0, last = null, dirty = false;

    const side = (name, value, attrs = '') => `<select class="cft-sel" data-f="${name}" ${attrs}><option value=""></option>`
        + `<option value="agent"${value === 'agent' ? ' selected' : ''}>Agent pays</option><option value="company"${value === 'company' ? ' selected' : ''}>We bear it</option></select>`;
    const agentOpts = (list, sel) => '<option value="">— nobody —</option>' + list.map(a => `<option value="${a.id}"${Number(a.id) === Number(sel) ? ' selected' : ''}>${esc(a.name)}</option>`).join('');
    // date boxes use the page's shared date+time picker through data-dt (a text box): handing it a
    // datetime-local box loses a midnight value (it writes a date-only value Chrome then rejects)
    const dtv = v => { const s = String(v || '').replace('T', ' ').slice(0, 16); return s.endsWith(' 00:00') ? s.slice(0, 10) : s; };
    function payRow(x) {
        const banks = (T.options.banks || []).map(b => `<option value="${b.id}"${Number(b.id) === Number(x.bank_id) ? ' selected' : ''}>${esc(b.name)}</option>`).join('');
        const portal = x.kind === 'portal';
        return `<tr data-id="${x.id || ''}" data-kind="${portal ? 'portal' : 'bank'}" data-method="${esc(x.method || '')}" data-ref="${esc(x.ref || '')}" data-at="${x.at_purchase ? 1 : ''}">
            <td>${portal ? `<span title="Paid from the portal's wallet with the purchase">${esc(x.bank || 'Portal wallet')}</span>` : `<select class="cft-sel cft-pbank"><option value=""></option>${banks}</select>`}</td>
            <td><input type="number" min="0" step="0.01" class="cft-in num cft-pamt" value="${x.amount || ''}"${portal ? ' readonly' : ''}></td>
            <td><input type="date" class="cft-in cft-pdate" value="${esc(x.date || '')}"${portal ? ' readonly' : ''}></td>
            <td style="text-align:center">${portal ? '' : '<input type="checkbox" class="cft-prem" title="Remove this payment">'}</td></tr>`;
    }
    const sellerOpts = (list, sel) => list.map(s => `<option value="${esc(s.id)}"${s.id === sel ? ' selected' : ''}>${esc(s.name)}</option>`).join('');

    function lock(on) {
        const html = document.documentElement;
        if (on) {
            html.style.setProperty('--cft-bar', (window.innerWidth - html.clientWidth) + 'px');
            html.classList.add('cft-lock');
        } else {
            html.classList.remove('cft-lock');
            html.style.removeProperty('--cft-bar');
        }
    }

    window.cftOpen = function (passengerId, entryKey) {
        PID = passengerId;
        $.ajax({ url: URLS.show.replace('__ID__', passengerId), headers: { Accept: 'application/json' } })
            .done(r => {
                if (!r.success) { Swal.fire({ icon: 'error', title: 'Cannot open', text: r.message }); return; }
                T = r.trail;
                $('#cftReason').val('');
                $('#cftModal').removeAttr('hidden');
                lock(true);
                drawList();
                const first = T.entries.find(e => e.key === entryKey) || T.entries.slice().reverse().find(e => !e.edit_problem) || T.entries[0];
                pick(first.key);
            })
            .fail(x => Swal.fire({ icon: 'error', title: 'Cannot open', text: fail(x, 'Could not load his trail.') }));
    };
    window.cftOpenFromReassign = function () {
        const id = Number($('#cfr_id').val());
        if (!id) return;
        if (typeof window.cfpCloseReassign === 'function') window.cfpCloseReassign();
        cftOpen(id);
    };
    window.cftClose = function () {
        $('#cftModal').attr('hidden', true);
        lock(false);
        T = E = null;
        if (dirty) window.location.reload();
    };

    // ── left: every entry, what it holds per ledger ─────────────────
    function nets(e) {
        const by = {};
        (e.rows || []).forEach(r => { by[r.ledger_name] = (by[r.ledger_name] || 0) + r.debit - r.credit; });
        return Object.entries(by).filter(([, v]) => Math.abs(v) >= 0.005);
    }
    function drawList() {
        const p = T.passenger;
        $('#cftSub').text([p.name, p.passport, p.flight ? 'on ' + p.flight : 'off flight', p.agent].filter(Boolean).join(' · '));
        const card = (e, it) => {
            const n = nets(e);
            const via = (it && it.via) || e.via;
            return `<div class="cft-it${E && E.key === e.key ? ' on' : ''}" data-key="${esc(e.key)}">
                <div class="a">${esc(e.title)}</div>
                ${via ? `<div class="via"><i class="fas fa-arrow-pointer"></i> via ${esc(via)}${it && it.what ? ' · ' + esc(it.what) : ''}</div>` : ''}
                <div class="m">${n.length ? n.map(([k, v]) => `<span>${esc(k)} <b>${v > 0 ? 'Dr' : 'Cr'} ${taka(Math.abs(v))}</b></span>`).join('') : '<span style="color:#94a3b8">nothing posted</span>'}</div>
                <div class="m">${e.edit_problem ? '<span class="cft-chip">read only</span>' : '<span class="cft-chip ok">editable</span>'}${e.delete_problem ? '' : ' <span class="cft-chip ok">' + (e.kind === 'ticket' || e.kind === 'service' ? 'deletable' : 'can undo') + '</span>'}</div>
                ${e.key === 'package' ? '' : `<button type="button" class="cft-undo-btn" data-key="${esc(e.key)}"${e.delete_problem ? ` disabled title="${esc(e.delete_problem)}"` : ''}>${e.kind === 'ticket' || e.kind === 'service' ? '<i class="fas fa-trash"></i> Delete' : '<i class="fas fa-rotate-left"></i> Undo'}</button>`}
            </div>`;
        };
        if (!full) { $('#cftList').html(simpleList()); return; }
        // sessions, newest first; inside one, in the order it happened (ContractFlightTrail::timeline())
        const byKey = Object.fromEntries(T.entries.map(e => [e.key, e]));
        const shown = new Set();
        let h = (T.timeline || []).map(s => {
            const rows = s.items.map(it => {
                if (it.type === 'entry') {
                    const e = byKey[it.key];
                    if (!e) return '';
                    shown.add(e.key);
                    return `<div class="cft-tl-row"><span class="cft-tl-t">${esc(it.time)}</span>${card(e, it)}</div>`;
                }
                return `<div class="cft-tl-row"><span class="cft-tl-t">${esc(it.time)}</span><div class="cft-log${it.key && byKey[it.key] ? ' is-link' : ''}${it.stage ? ' is-stage' : ''}"${it.key && byKey[it.key] ? ` data-key="${esc(it.key)}" title="Open the entry it changed"` : ''}>
                    <b>${esc(it.via || '')}</b> ${esc(it.text || '')}${it.by && it.by !== s.by ? ' · ' + esc(it.by) : ''}</div></div>`;
            }).join('');
            return `<div class="cft-sess"><div class="cft-sess-h"><div class="w"><i class="fas fa-clock-rotate-left"></i> ${esc(s.day)} · ${esc(s.from)}${s.to !== s.from ? ' – ' + esc(s.to) : ''}${s.by ? ' · ' + esc(s.by) : ''}</div>
                <div>${(s.vias || []).map(v => `<span class="cft-via">${esc(v)}</span>`).join('')}</div></div>${rows}</div>`;
        }).join('');
        const rest = T.entries.filter(e => !shown.has(e.key));
        if (rest.length) {
            h += `<div class="cft-sess"><div class="cft-sess-h"><div class="w">No date recorded</div></div>${rest.map(e => `<div class="cft-tl-row"><span class="cft-tl-t"></span>${card(e)}</div>`).join('')}</div>`;
        }
        $('#cftList').html('<button type="button" class="cft-mode" data-full="0"><i class="fas fa-arrow-left"></i> Back to actions</button>' + h);
    }

    // One card per action (owner, 2026-11-02: "so many cards ... one card for every action, clicking
    // that will open all editing related to that action"). An action = one flight change; a ticket or
    // service entered before it rides on it (the first change at or after it). The middle then shows a
    // tab per part. The session cards stay one click away ("Full history").
    var full = false;
    function actions() {
        const ts = e => String(e.ts || e.at || '');
        const acts = T.entries.filter(e => String(e.key).startsWith('change:'))
            .sort((a, b) => ts(a).localeCompare(ts(b)) || a.id - b.id).map(e => ({ main: e, parts: [e] }));
        const alone = [];
        T.entries.filter(e => !String(e.key).startsWith('change:')).forEach(e => {
            const host = e.key !== 'package' && ts(e) ? acts.find(g => ts(g.main) >= ts(e)) : null;
            if (host) host.parts.splice(host.parts.length - 1, 0, e); else alone.push({ main: e, parts: [e] });
        });
        return { acts, alone };
    }
    function actionOf(key) {
        const { acts, alone } = actions();
        return acts.concat(alone).find(g => g.parts.some(e => e.key === key));
    }
    function simpleList() {
        const btn = e => e.key === 'package' ? '' : `<button type="button" class="cft-undo-btn" data-key="${esc(e.key)}"${e.delete_problem ? ` disabled title="${esc(e.delete_problem)}"` : ''}>${e.kind === 'ticket' || e.kind === 'service' ? '<i class="fas fa-trash"></i> Delete' : '<i class="fas fa-rotate-left"></i> Undo'}</button>`;
        const money = g => {
            const by = {};
            g.parts.forEach(e => nets(e).forEach(([k, v]) => { by[k] = (by[k] || 0) + v; }));
            const n = Object.entries(by).filter(([, v]) => Math.abs(v) >= 0.005);
            return n.length ? n.map(([k, v]) => `${esc(k)} <b>${v > 0 ? 'Dr' : 'Cr'} ${taka(Math.abs(v))}</b>`).join(' · ') : '<span style="color:#94a3b8">no money</span>';
        };
        const card = (g, no) => {
            const e = g.main, on = E && g.parts.some(x => x.key === E.key);
            const more = g.parts.filter(x => x !== e);
            return `<div class="cft-it cft-line${on ? ' on' : ''}" data-key="${esc(e.key)}">
                ${no ? `<span class="cft-no">${no}</span>` : ''}<div class="a cft-lnh"><span>${esc(e.title)}</span>${btn(e)}</div>
                <div class="via">${e.at ? esc(e.at) : ''}${e.by ? ' · ' + esc(e.by) : ''}${more.length ? ` · <span class="cft-parts" title="${esc(more.map(x => x.title).join(' · '))}">+ ${more.length} more part${more.length > 1 ? 's' : ''}</span>` : ''}</div>
                <div class="m">${money(g)}</div>
                ${e.key !== 'package' && e.delete_problem ? `<div class="cft-why"><i class="fas fa-lock"></i> ${esc(e.delete_problem)}</div>` : ''}</div>`;
        };
        const { acts, alone } = actions();
        let h = `<div class="cft-grp">What was done <span>newest first · undo from the top</span></div>`;
        h += acts.length ? acts.slice().reverse().map((g, i) => card(g, acts.length - i)).join('') : '<div class="cft-empty">Nothing done on him yet.</div>';
        if (alone.length) {
            h += `<div class="cft-grp">Package, tickets &amp; services</div>` + alone.map(g => card(g)).join('');
        }
        return h + '<button type="button" class="cft-mode" data-full="1"><i class="fas fa-clock-rotate-left"></i> Full history</button>';
    }

    // ── middle: the entry, what it posted, its boxes ────────────────
    function pick(key) {
        E = T.entries.find(e => e.key === key);
        del = false;
        last = null;
        drawList();
        drawEditor();
        $('#cftDiff').html('<div class="cft-empty">Change a box — every ledger and account it hits is shown here, before and after, before anything posts.</div>');
        gate();
    }

    function postedTable(e) {
        if (!(e.rows || []).length && !(e.journals || []).length) return '<div class="cft-empty">Nothing posted.</div>';
        let h = `<table class="cft-tbl cft-posted"><colgroup><col style="width:84px"><col><col style="width:96px"><col style="width:96px"></colgroup>
            <thead><tr><th>Date</th><th>Ledger · what</th><th class="r">Debit</th><th class="r">Credit</th></tr></thead><tbody>`;
        h += (e.rows || []).map(r => `<tr title="${esc(r.remarks || '')}"><td>${esc(r.date)}</td><td>${esc(r.ledger_name)} · <span style="color:#64748b">${esc(r.remarks || r.type)}</span></td>
            <td class="r">${r.debit ? taka(r.debit) : ''}</td><td class="r">${r.credit ? taka(r.credit) : ''}</td></tr>`).join('');
        h += '</tbody></table>';
        if ((e.journals || []).length) {
            h += `<details style="margin-top:6px"><summary style="cursor:pointer;font-size:11.5px;color:#4f46e5;font-weight:600">${e.journals.length} journal${e.journals.length === 1 ? '' : 's'}</summary>`
                + e.journals.map(j => `<div style="margin-top:6px;font-size:11px;color:#64748b">${esc(j.date)} · ${esc(j.description || j.source)}</div>
                    <table class="cft-tbl"><colgroup><col><col style="width:96px"><col style="width:96px"></colgroup><tbody>`
                    + j.lines.map(l => `<tr><td>${esc(l.account)}${l.party ? ' · ' + esc(l.party) : ''}</td><td class="r">${l.debit ? taka(l.debit) : ''}</td><td class="r">${l.credit ? taka(l.credit) : ''}</td></tr>`).join('')
                    + '</tbody></table>').join('') + '</details>';
        }
        return h;
    }

    function drawEditor() {
        const e = E;
        const g = full ? null : actionOf(e.key);
        let h = g && g.parts.length > 1 ? `<div class="cft-tabs">${g.parts.map(x => `<button type="button" class="cft-tab${x.key === e.key ? ' on' : ''}" data-key="${esc(x.key)}">${esc(x.title)}</button>`).join('')}</div>` : '';
        h += `<div class="cft-card"><h4><i class="fas fa-receipt"></i> ${esc(e.title)}</h4>
            ${e.note ? `<div class="cft-hint" style="margin:0 0 8px">${esc(e.note)}</div>` : ''}${postedTable(e)}</div>`;
        if (e.edit_problem) {
            h += `<div class="cft-note">${esc(e.edit_problem)}</div>`;
        } else if (e.key === 'package') {
            h += `<div class="cft-card c-agent"><h4><i class="fas fa-user-tie"></i> Package billed to the agent</h4><div class="cft-row2">
                <div><label class="cft-lbl">Agent</label><select class="cft-sel cft-s2" data-f="agent_id">${agentOpts(T.options.agents, e.form.agent_id)}</select></div>
                <div><label class="cft-lbl">Agent price (per head)</label><input type="number" min="0" step="0.01" class="cft-in num" data-f="agent_price" value="${e.form.agent_price}"></div></div>
                <div class="cft-hint">His package row is set to this figure in place, as the passenger edit does; a reversal on it follows.</div></div>`;
        } else if (e.kind === 'ticket') {
            const f = e.form, t = f.ticket, o = T.options;
            const opts = (list, sel, none) => (none ? `<option value="">${none}</option>` : '<option value=""></option>')
                + list.map(x => `<option value="${esc(x.id)}"${String(x.id) === String(sel) ? ' selected' : ''}>${esc(x.name)}</option>`).join('');
            const cls = ['economy', 'business', 'first'].map(c => `<option value="${c}"${String(t.ticket_class || '').toLowerCase() === c ? ' selected' : ''}>${c[0].toUpperCase() + c.slice(1)}</option>`).join('');
            h += `<div class="cft-card c-seller"><h4><i class="fas fa-ticket"></i> The ticket — as bought</h4>
                <div class="cft-row2"><div><label class="cft-lbl">Route</label><select class="cft-sel cft-s2" data-t="ticket_id">${opts(o.routes, t.ticket_id)}</select></div>
                    <div><label class="cft-lbl">PNR</label><input type="text" class="cft-in" data-t="ticket_no" maxlength="60" value="${esc(t.ticket_no)}"></div></div>
                <div class="cft-row2" style="margin-top:8px"><div><label class="cft-lbl">Airline</label><select class="cft-sel cft-s2" data-f="airline_id">${opts(o.airlines, f.airline_id, '— none —')}</select></div>
                    <div><label class="cft-lbl">Class</label><select class="cft-sel" data-t="ticket_class">${cls}</select></div></div>
                <div class="cft-row2" style="margin-top:8px"><div><label class="cft-lbl">Travel date &amp; time</label><input type="text" data-dt class="cft-in" data-t="travel_at" value="${esc(dtv(t.travel_at))}"></div>
                    <div><label class="cft-lbl">Bought on</label><input type="date" class="cft-in" data-t="purchase_date" value="${esc(t.purchase_date || '')}"></div></div>
                <div class="cft-row2" style="margin-top:8px"><div><label class="cft-lbl">Seller (whose ledger)</label><select class="cft-sel cft-s2" data-t="seller">${opts(o.sellers, t.seller)}</select></div>
                    <div><label class="cft-lbl">Cost</label><input type="number" min="0" step="0.01" class="cft-in num" data-t="amount" value="${t.amount}"></div></div>
                <div class="cft-hint">Posted through Ticketing's own Trail — Ticket Orders shows the same; his travel cost and the flight's cost follow. The flight's re-issues and refunds of this ticket are their own entries.</div></div>`;
            h += `<div class="cft-card"><h4><i class="fas fa-building-columns"></i> Payments to the seller</h4><table class="cft-tbl" id="cftPays">
                <colgroup><col><col style="width:110px"><col style="width:130px"><col style="width:64px"></colgroup>
                <thead><tr><th>Paid from</th><th class="r">Amount</th><th>Date</th><th>Remove</th></tr></thead><tbody>`
                + f.payments.map(x => payRow(x)).join('') + `</tbody></table>
                <button type="button" class="cft-del-btn" id="cftAddPay" style="margin-top:8px;color:#4f46e5;border-color:#c7d2fe"><i class="fas fa-plus"></i> Add a payment</button></div>`;
        } else if (e.kind === 'service') {
            const f = e.form;
            h += `<div class="cft-card c-seller"><h4><i class="fas fa-chalkboard-user"></i> Who is paid · how much</h4><div class="cft-row2">
                <div><label class="cft-lbl">Responsible person</label><select class="cft-sel cft-s2" data-f="vendor_id"><option value=""></option>${f.providers.map(x => `<option value="${x.id}"${Number(x.id) === Number(f.vendor_id) ? ' selected' : ''}>${esc(x.name)}</option>`).join('')}</select></div>
                <div><label class="cft-lbl">Cost</label><input type="number" min="0" step="0.01" class="cft-in num" data-f="amount" value="${f.amount}"></div></div>
                <div class="cft-hint">We pay this person — it sits on their ledger as a payable; the package stays the agent's.</div></div>`;
        } else {
            const f = e.form;
            h += `<div class="cft-card c-agent"><h4><i class="fas fa-user-tie"></i> Agent billed</h4><select class="cft-sel cft-s2" data-f="agent_id">${agentOpts(f.options.agents, f.agent_id)}</select>
                <div class="cft-hint">The agent's ONE line for this entry: the penalties he bears plus our charge.</div></div>`;
            if (f.per_ticket) {
                h += `<div class="cft-card c-seller"><h4><i class="fas fa-store"></i> The actual penalty — what the seller charged us</h4>`;
                f.tickets.forEach(t => t.parts.forEach((p, i) => {
                    h += `<div class="cft-part" data-ticket="${t.ticket_id}" data-key="${esc(p.key)}">
                        <div class="cft-part-h"><b>${esc(t.pnr)}</b> · ${esc(p.label)}</div>
                        <div class="cft-part-g">
                            <div><label class="cft-lbl">Seller (whose ledger)</label><select class="cft-sel cft-s2 cft-seller"><option value=""></option>${sellerOpts(f.options.sellers, p.seller)}</select></div>
                            <div><label class="cft-lbl">Amount</label><input type="number" min="0" step="0.01" class="cft-in num cft-amt" value="${p.amount || ''}"></div>
                            <div>${i === 0 ? `<label class="cft-lbl">Who bears it</label>${side('bearer', t.bearer, `data-ticket="${t.ticket_id}"`)}${t.agent_amount != null ? `<label class="cft-lbl">Agent share</label><input type="number" min="0" step="0.01" class="cft-in cft-ticket-share" data-share-ticket="${t.ticket_id}" value="${t.agent_amount}">` : ''}` : ''}</div>
                        </div></div>`;
                }));
                h += `</div><div class="cft-card c-ours"><h4><i class="fas fa-hand-holding-dollar"></i> Our charge — billed to the agent</h4>
                    <div class="cft-row2"><div><label class="cft-lbl">Our charge</label><input type="number" min="0" step="0.01" class="cft-in num" data-f="ours" value="${f.ours || ''}"></div>
                    <div class="cft-hint">Our own fee (profit, 4183), always the agent's.</div></div></div>`;
            } else {
                h += `<div class="cft-card c-ours"><h4><i class="fas fa-hand-holding-dollar"></i> Our charge — billed to the agent</h4><div class="cft-row2">
                    <div><label class="cft-lbl">Our charge</label><input type="number" min="0" step="0.01" class="cft-in num" data-f="charge" value="${f.charge || ''}"></div>
                    <div>${f.charge_bearer === 'company' ? `<label class="cft-lbl">Who bears it</label>${side('charge_bearer', 'company')}` : '<div class="cft-hint">On the agent\'s ONE line with any penalty he bears.</div>'}</div></div></div>`;
            }
            if (f.penalty) {
                h += `<div class="cft-card c-kept"><h4><i class="fas fa-plane-circle-xmark"></i> What the airline kept</h4>`;
                if (f.penalty.refunds.length) {
                    h += `<table class="cft-tbl"><thead><tr><th>PNR</th><th class="r">Ticket cost</th><th class="r">Airline refund</th><th class="r">Kept</th></tr></thead><tbody>`
                        + f.penalty.refunds.map(r => `<tr data-refund="${r.ticket_id}"><td class="pnr">${esc(r.pnr)}</td><td class="r">${taka(r.cost)}</td>
                            <td class="r"><input type="number" min="0" step="0.01" class="cft-in num cft-refund" data-cost="${r.cost}" data-was="${r.refund}" value="${r.refund}"></td>
                            <td class="r cft-kept" style="color:#b91c1c;font-weight:700">${taka(r.cost - r.refund)}</td></tr>`).join('') + '</tbody></table>';
                }
                h += `<div class="cft-row2" style="margin-top:8px"><div><label class="cft-lbl">Airline kept on this entry</label><div id="cftPenalty" style="font-size:15px;font-weight:700;color:#b91c1c">${taka(f.penalty.amount)}</div></div>
                    <div><label class="cft-lbl">Whose side it is</label>${side('fault', f.penalty.fault)}</div></div>
                    <div class="cft-hint">A refund is replaced through Ticketing — its refund rows removed and posted again with this figure.</div></div>`;
            }
            if (f.fare) {
                h += `<div class="cft-card c-fare"><h4><i class="fas fa-scale-unbalanced"></i> Fare difference</h4><div class="cft-row2">
                    <div><label class="cft-lbl">Fare difference</label><input type="number" step="0.01" class="cft-in num" data-f="fare" value="${f.fare.amount}"></div>
                    <div><label class="cft-lbl">Who bears it</label>${side('fare_bearer', f.fare.bearer)}</div></div>${f.fare.agent_amount != null ? `<label class="cft-lbl">Agent share (the remainder is ours)</label><input type="number" min="0" step="0.01" class="cft-in num" data-f="agent_fare_amount" value="${f.fare.agent_amount}">` : ''}</div>`;
            }
            h += `<div class="cft-card"><h4><i class="fas fa-calendar-day"></i> Posted on</h4><div class="cft-row2">
                <div><label class="cft-lbl">Date &amp; time of every row of this entry</label><input type="text" data-dt class="cft-in" data-f="at" value="${esc(dtv(e.at))}"></div>
                <div class="cft-hint">An airline refund keeps Ticketing's own date.</div></div></div>`;
        }
        if (!e.delete_problem) {
            const what = e.kind === 'ticket' ? 'The ticket and every row it posted (seller, wallet, journals) are removed and it comes off him — Ticket Orders no longer lists it.'
                : e.kind === 'service' ? 'Its cost and its rows on the responsible person\'s ledger are removed.'
                : 'Its rows are removed from every ledger and what it did is undone — a refunded ticket goes back live and onto him, a re-issued date goes back, a move puts him back on the flight he came from (or back under Cancelled), a cancelled passenger goes back on his flight.';
            // an entry of his history is UNDONE (owner, 2026-11-02: "undo one by one, so I can start
            // from refund again"); a ticket or a service is deleted
            const undo = e.kind !== 'ticket' && e.kind !== 'service';
            const lbl = undo ? '<i class="fas fa-rotate-left"></i> Undo this entry' : '<i class="fas fa-trash"></i> Delete this entry';
            h += `<div class="cft-card c-del"><h4>${lbl}</h4>
                <div class="cft-hint" style="margin:0 0 8px">${undo ? 'Undo goes one by one, latest first. ' : ''}${what}</div>
                <button type="button" class="cft-del-btn" id="cftDelBtn" data-lbl="${esc(lbl)}">${lbl}</button></div>`;
        } else if (e.key !== 'package') {
            h += `<div class="cft-hint"><i class="fas fa-circle-info"></i> ${e.kind === 'ticket' || e.kind === 'service' ? 'Delete' : 'Undo'}: ${esc(e.delete_problem)}</div>`;
        }
        $('#cftEd').html(h);
        if ($.fn.select2) {
            $('#cftEd .cft-s2').each(function () { $(this).select2({ dropdownParent: $('#cftModal .cft-box'), width: '100%', placeholder: '— choose —' }); });
        }
    }

    function payload() {
        const e = E;
        const d = { entry: e.kind === 'ticket' || e.kind === 'service' || e.key === 'package' ? e.key : String(e.id), action: del ? 'delete' : 'edit', input: {}, _token: csrf() };
        if (del || e.edit_problem) return d;
        const val = f => $(`#cftEd [data-f="${f}"]`);
        if (e.key === 'package') {
            d.input = { agent_id: val('agent_id').val() || '', agent_price: val('agent_price').val() || 0 };
            return d;
        }
        if (e.kind === 'service') {
            d.input = { vendor_id: val('vendor_id').val() || '', amount: val('amount').val() || 0 };
            return d;
        }
        if (e.kind === 'ticket') {
            const tk = {};
            $('#cftEd [data-t]').each(function () { tk[$(this).data('t')] = $(this).val(); });
            d.input = { ticket: tk, airline_id: val('airline_id').val() || '', payments: [] };
            $('#cftPays tbody tr').each(function () {
                const r = $(this);
                d.input.payments.push({ id: r.data('id') || '', kind: r.data('kind'), bank_id: r.find('.cft-pbank').val() || '', amount: r.find('.cft-pamt').val() || 0,
                    date: r.find('.cft-pdate').val() || '', method: r.data('method') || '', ref: r.data('ref') || '', at_purchase: r.data('at') ? 1 : 0, remove: r.find('.cft-prem').is(':checked') ? 1 : 0 });
            });
            return d;
        }
        const f = e.form, i = d.input;
        i.agent_id = val('agent_id').val() || '';
        ['ours', 'charge', 'fare'].forEach(k => { if (val(k).length) i[k] = val(k).val() === '' ? 0 : val(k).val(); });
        ['fault', 'fare_bearer', 'charge_bearer'].forEach(k => { const el = $(`#cftEd select[data-f="${k}"]`); if (el.length) i[k] = el.val() || ''; });
        if (val('agent_fare_amount').length) i.agent_fare_amount = val('agent_fare_amount').val();
        if (f.per_ticket) {
            i.tickets = f.tickets.map(t => ({
                ticket_id: t.ticket_id,
                bearer: $(`#cftEd select[data-f="bearer"][data-ticket="${t.ticket_id}"]`).val() || '',
                agent_amount: $(`#cftEd [data-share-ticket="${t.ticket_id}"]`).val() ?? null,
                parts: t.parts.map(p => { const tr = $(`#cftEd .cft-part[data-key="${p.key}"]`); return { key: p.key, seller: tr.find('.cft-seller').val() || '', amount: tr.find('.cft-amt').val() === '' ? 0 : tr.find('.cft-amt').val() }; }),
            }));
        }
        if (f.penalty && f.penalty.refunds.length) {
            i.refunds = {};
            $('#cftEd .cft-refund').each(function () { i.refunds[$(this).closest('tr').data('refund')] = $(this).val(); });
        }
        const at = val('at').val();
        if (at) d.at = at;
        return d;
    }

    function keptLive() {
        if (!E || !E.form || !E.form.penalty) return;
        let pen = Number(E.form.penalty.amount) || 0;
        $('#cftEd .cft-refund').each(function () {
            const cost = Number($(this).data('cost')), was = Number($(this).data('was')), now = Math.min(cost, Math.max(0, Number($(this).val()) || 0));
            $(this).closest('tr').find('.cft-kept').text(taka(cost - now));
            pen -= (now - was);
        });
        $('#cftPenalty').text(taka(Math.max(0, pen)));
    }

    // ── right: before → after, per ledger and per account head ──────
    function preview() {
        if (!E || (E.edit_problem && !del)) return;
        const my = ++seq;
        $('#cftDiff').html('<div class="cft-empty"><i class="fas fa-spinner fa-spin"></i> Working it out…</div>');
        $.ajax({ url: URLS.preview.replace('__ID__', PID), method: 'POST', data: payload(), headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } })
            .done(r => { if (my === seq) show(r); })
            .fail(x => { if (my !== seq) return; last = null; $('#cftDiff').html(`<div class="cft-err">${esc(fail(x, 'Could not work it out.'))}</div>`); gate(); });
    }

    function show(r) {
        last = r;
        if (!r.changed) {
            $('#cftDiff').html('<div class="cft-empty">Nothing changes yet — the entry already says this.</div>');
            gate();
            return;
        }
        let h = `<ul class="cft-sum">${r.summary.map(s => `<li>${esc(s)}</li>`).join('')}</ul>`;
        const led = r.diff.ledgers.filter(l => l.changed);
        led.forEach(l => {
            h += `<div class="cft-led"><div class="h"><span class="k">${esc(l.kind)}</span> ${esc(l.name)}<span class="n">${taka(l.net_before)} → <b>${taka(l.net_after)}</b></span></div>`
                + l.rows.filter(x => x.status !== 'same').map(x => `<div class="cft-dr s-${x.status}"><span class="st st-${x.status}">${esc(x.status)}</span>
                    <span class="lab">${esc(x.label)} · ${esc(x.side)}${x.date_after && x.date_before && x.date_after !== x.date_before ? ' · ' + esc(x.date_before) + ' → ' + esc(x.date_after) : ' · ' + esc(x.date_after || x.date_before || '')}</span>
                    <span class="v">${x.before !== null ? taka(x.before) : '—'}</span><span class="v after">${x.after !== null ? taka(x.after) : '—'}</span></div>`).join('') + '</div>';
        });
        const acc = r.diff.accounts.filter(a => a.changed);
        if (acc.length) {
            h += `<table class="cft-acc"><thead><tr><th>Account head</th><th class="r">Before</th><th class="r">After</th></tr></thead><tbody>`
                + acc.map(a => `<tr class="ch"><td>${esc(a.account)}</td><td class="r">${taka(a.before)}</td><td class="r">${taka(a.after)}</td></tr>`).join('') + '</tbody></table>';
        }
        if (!led.length && !acc.length) h += '<div class="cft-empty">No amount moves — only dates or how the entry reads.</div>';
        h += `<div class="cft-tot"><span><b>${led.length}</b> ledger${led.length === 1 ? '' : 's'} change</span><span><b>${acc.length}</b> account head${acc.length === 1 ? '' : 's'}</span>
            <span>Wrong rows are REPLACED — removed and posted again once; a copy of each is kept in the log.</span></div>`;
        $('#cftDiff').html(h);
        gate();
    }

    function gate() {
        const ok = last && last.changed && $('#cftReason').val().trim() !== '';
        $('#cftPost').prop('disabled', !ok).toggleClass('del', del)
            .html(del ? '<i class="fas fa-trash"></i> Confirm &amp; delete' : '<i class="fas fa-check"></i> Confirm &amp; replace')
            .attr('title', !last ? 'Change a box first' : (!$('#cftReason').val().trim() ? 'Write why it is corrected' : ''));
    }

    window.cftPost = function () {
        if ($('#cftPost').prop('disabled')) return;
        const d = payload();
        d.reason = $('#cftReason').val().trim();
        d.signature = T.signature;
        $('#cftPost').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Posting…');
        $.ajax({ url: URLS.apply.replace('__ID__', PID), method: 'POST', data: d, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } })
            .done(r => {
                if (!r.success) { Swal.fire({ icon: 'error', title: 'Not posted', text: r.message }); gate(); return; }
                dirty = true;
                T = r.trail;
                $('#cftReason').val('');
                Swal.fire({ icon: 'success', title: del ? 'Deleted' : 'Replaced', text: r.message, timer: 2600, showConfirmButton: false });
                const keep = T.entries.find(e => E && e.key === E.key) ? E.key : 'package';
                drawList();
                pick(keep);
            })
            .fail(x => { Swal.fire({ icon: 'error', title: 'Not posted', text: fail(x, 'Could not post.') }); gate(); });
    };

    // jQuery loads after this partial on the page — bind once the page is parsed
    const bind = () => {
    $(document).on('click', '#cftList .cft-it, #cftList .cft-log.is-link', function () { pick($(this).data('key')); });
    $(document).on('click', '#cftEd .cft-tab', function () { pick($(this).data('key')); });
    $(document).on('click', '#cftList .cft-mode', function () { full = $(this).data('full') == 1; drawList(); });
    // the card's own Undo / Delete: open the entry already switched to undo, preview shown
    $(document).on('click', '#cftList .cft-undo-btn', function (ev) {
        ev.stopPropagation();
        pick($(this).data('key'));
        if (!del) $('#cftDelBtn').trigger('click');
        $('#cftDelBtn')[0]?.scrollIntoView({ block: 'center' });
    });
    $(document).on('click', '#cftDelBtn', function () {
        del = !del;
        $(this).toggleClass('on', del).html(del ? '<i class="fas fa-ban"></i> Keep this entry' : $(this).data('lbl'));
        $('#cftEd input, #cftEd select').not('#cftDelBtn').prop('disabled', del);
        preview();
    });
    $(document).on('input change', '#cftEd input, #cftEd select', function () {
        keptLive();
        clearTimeout(timer);
        timer = setTimeout(preview, 400);
    });
    $(document).on('input', '#cftReason', gate);
    $(document).on('click', '#cftAddPay', function () {
        $('#cftPays tbody').append(payRow({ kind: 'bank', amount: '', date: new Date().toISOString().slice(0, 10) }));
    });
    $(document).on('keydown', e => { if (e.key === 'Escape' && T && !$('#cftModal').is('[hidden]')) e.stopPropagation(); });
    };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', bind) : bind();
})();
</script>
