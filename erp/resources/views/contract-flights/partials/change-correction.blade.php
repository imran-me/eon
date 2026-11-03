{{--
    Correct a posted entry (owner, 2026-10-19: "a way so I can edit those if mistakes happen —
    amounts, which ledger to hit, who bears"). Passenger edit modal → Flight changes → Correct.
    Super admin only; emitted only when the service and its routes exist (a stale route cache
    leaves the page exactly as before). The money is App\Services\ContractFlightChangeCorrection:
    nothing is edited or deleted — one row per difference is added, shown here before it posts.
--}}
@php
    $cfcConfig = null;
    try {
        if (\App\Support\UserRoleSetting\Access::allows('travels.contract-flight', 'e') && class_exists(\App\Services\ContractFlightChangeCorrection::class)
            && \App\Services\ContractFlightChangeCorrection::available()
            && Route::has('role.contract-flight-passenger-changes.correction.apply')) {
            $cfcArgs = ['role' => $role, 'contractFlightPassengerChange' => '__ID__'];
            $cfcConfig = [
                'show' => route('role.contract-flight-passenger-changes.correction', $cfcArgs),
                'preview' => route('role.contract-flight-passenger-changes.correction.preview', $cfcArgs),
                'apply' => route('role.contract-flight-passenger-changes.correction.apply', $cfcArgs),
            ];
        }
    } catch (\Throwable $e) {
        $cfcConfig = null;
    }
@endphp
@if($cfcConfig)
<style>
    .cfc-ov{position:fixed;inset:0;z-index:9600;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;padding:12px 16px;box-sizing:border-box;}
    .cfc-ov[hidden]{display:none;}
    .cfc-box{width:min(1280px,calc(100vw - 32px));height:calc(100vh - 24px);background:#fff;border-radius:16px;box-shadow:0 24px 60px rgba(15,23,42,.3);display:flex;flex-direction:column;overflow:hidden;font-size:13px;color:#0f172a;}
    .cfc-hd{display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid #c7d2fe;background:linear-gradient(90deg,#eef2ff,#f5f3ff);}
    .cfc-hd .t{color:#3730a3;}
    .cfc-hd .t{font-size:15px;font-weight:700;}
    .cfc-hd .s{font-size:12px;color:#64748b;margin-top:2px;}
    .cfc-x{margin-left:auto;border:0;background:transparent;font-size:16px;color:#64748b;cursor:pointer;padding:4px 8px;border-radius:8px;}
    .cfc-x:hover{background:#f1f5f9;color:#0f172a;}
    .cfc-body{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);}
    .cfc-form{overflow:auto;padding:18px 22px;border-right:1px solid #d2d8e7;}
    .cfc-side{overflow:auto;padding:18px 22px;background:#fafbff;}
    .cfc-card{border:1px solid #d2d8e7;border-radius:12px;padding:14px 16px;margin-bottom:14px;}
    /* each section's heading is a coloured band (owner, 2026-10-19: "so white, irritating, give heading colors"):
       agent = the Agent pays pill's indigo, seller = amber, our charge = green (profit), airline kept = red */
    .cfc-card{padding:0 16px 14px;overflow:hidden;}
    .cfc-card h4{margin:0 -16px 12px;padding:9px 16px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;display:flex;align-items:center;gap:8px;
        background:#f1f5f9;color:#334155;border-bottom:1px solid #e2e8f0;}
    .cfc-card.c-agent h4{background:#eef2ff;color:#3730a3;border-color:#c7d2fe;}
    .cfc-card.c-seller h4{background:#fffbeb;color:#92400e;border-color:#fde68a;}
    .cfc-card.c-ours h4{background:#ecfdf5;color:#047857;border-color:#a7f3d0;}
    .cfc-card.c-kept h4{background:#fff1f2;color:#be123c;border-color:#fecdd3;}
    .cfc-card.c-fare h4{background:#f0f9ff;color:#0369a1;border-color:#bae6fd;}
    .cfc-card h4 i{color:inherit;opacity:.85;}
    .cfc-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px 16px;}
    .cfc-facts .k{font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:#94a3b8;}
    .cfc-facts .v{font-size:13px;font-weight:600;overflow-wrap:anywhere;}
    .cfc-note{margin-top:8px;font-size:11.5px;color:#64748b;overflow-wrap:anywhere;}
    .cfc-lbl{display:block;font-size:10.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px;}
    .cfc-in,.cfc-sel{width:100%;box-sizing:border-box;height:36px;border:1px solid #d2d8e7;border-radius:8px;padding:6px 10px;font-size:13px;background:#fff;font-family:inherit;}
    .cfc-in:focus,.cfc-sel:focus{outline:0;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    .cfc-in.num{text-align:right;font-variant-numeric:tabular-nums;}
    .cfc-tbl{width:100%;border-collapse:collapse;table-layout:fixed;}
    .cfc-tbl td.part{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .cfc-tbl th{font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:#94a3b8;text-align:left;padding:4px 6px 6px;}
    .cfc-tbl td{padding:5px 6px;vertical-align:middle;border-top:1px solid #eef1f7;}
    .cfc-tbl .pnr{font-family:'DM Mono',monospace;font-weight:600;white-space:nowrap;}
    .cfc-tbl .r{text-align:right;}
    .cfc-row2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;align-items:end;}
    .cfc-kept{font-weight:700;color:#b91c1c;font-variant-numeric:tabular-nums;}
    .cfc-hint{font-size:11.5px;color:#64748b;margin-top:6px;}
    .cfc-ft{display:flex;align-items:flex-end;gap:12px;padding:12px 20px;border-top:1px solid #d2d8e7;background:#fafbff;}
    .cfc-ft .grow{flex:1;}
    .cfc-post{border:0;border-radius:10px;padding:10px 20px;font-weight:700;font-size:13.5px;color:#fff;background:#16a34a;box-shadow:0 6px 16px rgba(22,163,74,.35);cursor:pointer;white-space:nowrap;animation:cfcPulse 1.8s ease-in-out infinite;}
    .cfc-post:disabled{background:#94a3b8;box-shadow:none;cursor:not-allowed;animation:none;}
    @keyframes cfcPulse{0%,100%{box-shadow:0 6px 16px rgba(22,163,74,.35);}50%{box-shadow:0 6px 24px rgba(22,163,74,.6);}}
    .cfc-side h5{margin:-18px -22px 14px;padding:11px 22px;font-size:12px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#fff;background:linear-gradient(90deg,#4f46e5,#7c3aed);}
    .cfc-changed{margin:0 0 14px;padding:10px 12px 10px 28px;border:1px solid #ddd6fe;border-left:4px solid #7c3aed;border-radius:10px;background:#fff;font-size:12.5px;}
    .cfc-changed li{margin:2px 0;}
    .cfc-op{border:1px solid #d2d8e7;border-radius:10px;background:#fff;padding:0 12px 10px;margin-bottom:10px;overflow:hidden;}
    .cfc-op .h{font-weight:700;font-size:12.5px;margin:0 -12px 8px;padding:8px 12px;background:#f1f5f9;color:#334155;border-bottom:1px solid #e2e8f0;}
    .cfc-op.k-agent .h,.cfc-op.k-fare_agent .h{background:#eef2ff;color:#3730a3;border-color:#c7d2fe;}
    .cfc-op.k-seller .h{background:#fffbeb;color:#92400e;border-color:#fde68a;}
    .cfc-op.k-refund .h{background:#f0f9ff;color:#0369a1;border-color:#bae6fd;}
    .cfc-op.k-penalty .h{background:#fff1f2;color:#be123c;border-color:#fecdd3;}
    .cfc-op.k-fare_company .h{background:#f0f9ff;color:#0369a1;border-color:#bae6fd;}
    .cfc-party{display:flex;gap:8px;align-items:baseline;font-size:12px;padding:4px 0 6px;border-bottom:1px dashed #e2e8f0;margin-bottom:6px;}
    .cfc-party .who{font-weight:600;}
    .cfc-party .txt{color:#64748b;flex:1;min-width:0;overflow-wrap:anywhere;}
    .cfc-amt{font-variant-numeric:tabular-nums;white-space:nowrap;font-weight:700;}
    .cfc-amt.dr{color:#b91c1c;}
    .cfc-amt.cr{color:#15803d;}
    .cfc-jl{display:grid;grid-template-columns:minmax(0,1fr) 92px 92px;gap:4px 10px;font-size:12px;}
    .cfc-jl .n{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .cfc-jl .d,.cfc-jl .c{text-align:right;font-variant-numeric:tabular-nums;}
    .cfc-jl .hd{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;}
    .cfc-tot{margin-top:12px;padding:10px 12px;border-radius:10px;border:1px solid #bbf7d0;background:#f0fdf4;font-size:12.5px;display:flex;gap:16px;flex-wrap:wrap;}
    .cfc-err{padding:10px 12px;border-radius:10px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;font-size:12.5px;}
    .cfc-empty{color:#64748b;font-size:12.5px;}
    #cfcModal .select2-container{width:100%!important;}
    #cfcModal .select2-container .select2-selection--single{height:36px;border-color:#d2d8e7;border-radius:8px;}
    #cfcModal .select2-container .select2-selection--single .select2-selection__rendered{line-height:34px;font-size:13px;}
    @media (max-width:900px){.cfc-body{grid-template-columns:1fr;}.cfc-form{border-right:0;border-bottom:1px solid #d2d8e7;}.cfc-facts{grid-template-columns:1fr 1fr;}}
</style>
<div id="cfcModal" class="cfc-ov" hidden role="dialog" aria-modal="true" aria-labelledby="cfcTitle">
    <div class="cfc-box">
        <div class="cfc-hd">
            <div><div class="t" id="cfcTitle">Correct entry</div><div class="s" id="cfcSub"></div></div>
            <button type="button" class="cfc-x" onclick="cfcClose()" title="Close">✕</button>
        </div>
        <div class="cfc-body">
            <div class="cfc-form" id="cfcForm"></div>
            <div class="cfc-side"><h5>What will be posted</h5><div id="cfcPreview"><div class="cfc-empty">Change a box on the left — each difference is shown here before anything posts.</div></div></div>
        </div>
        <div class="cfc-ft">
            <div style="width:170px"><label class="cfc-lbl" for="cfcDate">Post on</label><input type="date" id="cfcDate" class="cfc-in"></div>
            <div class="grow"><label class="cfc-lbl" for="cfcReason">Why it is corrected</label><input type="text" id="cfcReason" class="cfc-in" maxlength="300"></div>
            <button type="button" class="cfc-post" id="cfcPost" disabled onclick="cfcPost()"><i class="fas fa-check"></i> Confirm &amp; post</button>
        </div>
    </div>
</div>
<script>
(function () {
    const URLS = @json($cfcConfig);
    const esc = s => $('<div>').text(s == null ? '' : String(s)).html();
    const taka = v => '৳' + (Number(v) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const csrf = () => $('meta[name="csrf-token"]').attr('content');
    const fail = (x, fb) => (x && x.responseJSON && (x.responseJSON.message || Object.values(x.responseJSON.errors || {})[0]?.[0])) || fb;
    let E = null, timer = null, lastOk = null, seq = 0;

    const sideSelect = (name, value, attrs = '') => `<select class="cfr-sw-src cfc-side-sel" data-f="${name}" ${attrs}><option value=""></option>`
        + `<option value="agent"${value === 'agent' ? ' selected' : ''}>Agent pays</option><option value="company"${value === 'company' ? ' selected' : ''}>We bear it</option></select>`;
    const sellerOpts = sel => E.options.sellers.map(s => `<option value="${esc(s.id)}"${s.id === sel ? ' selected' : ''}>${esc(s.name)}</option>`).join('');
    const agentOpts = sel => '<option value="">— nobody —</option>' + E.options.agents.map(a => `<option value="${a.id}"${a.id === sel ? ' selected' : ''}>${esc(a.name)}</option>`).join('');

    window.cfcOpen = function (changeId) {
        $.ajax({ url: URLS.show.replace('__ID__', changeId), headers: { Accept: 'application/json' } })
            .done(r => { if (!r.success) { Swal.fire({ icon: 'error', title: 'Cannot open', text: r.message }); return; } render(r.entry); })
            .fail(x => Swal.fire({ icon: 'error', title: 'Cannot open', text: fail(x, 'Could not load the entry.') }));
    };
    window.cfcClose = function () { $('#cfcModal').attr('hidden', true); E = null; };

    function render(entry) {
        E = entry;
        $('#cfcSub').text([entry.passenger, entry.title, entry.reason_label].filter(Boolean).join(' · '));
        let h = `<div class="cfc-card c-entry"><h4><i class="fas fa-receipt"></i> The entry</h4><div class="cfc-facts">
            <div><div class="k">Passenger</div><div class="v">${esc(entry.passenger || '—')}</div></div>
            <div><div class="k">Entry</div><div class="v">${esc(entry.title)}</div></div>
            <div><div class="k">Posted</div><div class="v">${esc(entry.at || '—')}${entry.by ? ' · ' + esc(entry.by) : ''}</div></div></div>
            ${entry.note ? `<div class="cfc-note">${esc(entry.note)}</div>` : ''}
            ${entry.halves.length ? `<div class="cfc-note">With its “we bear” half: ${entry.halves.map(x => esc(x.note)).join(' · ')}</div>` : ''}</div>`;
        if (entry.problem) {
            $('#cfcForm').html(h + `<div class="cfc-err">${esc(entry.problem)}</div>`);
            $('#cfcPreview').html('<div class="cfc-empty">Nothing can be posted on this entry.</div>');
            $('#cfcPost').prop('disabled', true);
            open();
            return;
        }
        h += `<div class="cfc-card c-agent"><h4><i class="fas fa-user-tie"></i> Agent billed</h4><select class="cfc-sel cfc-s2" data-f="agent_id">${agentOpts(entry.agent_id)}</select>
            <div class="cfc-hint">The agent's ONE line for this entry: the penalties he bears plus our charge. Moved to another agent, it is reversed on the first and posted on the second.</div></div>`;
        if (entry.per_ticket) {
            h += `<div class="cfc-card c-seller"><h4><i class="fas fa-store"></i> The actual penalty — what the seller charged us</h4><table class="cfc-tbl">
                <colgroup><col style="width:96px"><col style="width:112px"><col><col style="width:122px"><col style="width:158px"></colgroup>
                <thead><tr><th>PNR</th><th>Part</th><th>Seller (whose ledger)</th><th class="r">Amount</th><th>Who bears it</th></tr></thead><tbody>`;
            entry.tickets.forEach(t => t.parts.forEach((p, i) => {
                h += `<tr data-ticket="${t.ticket_id}" data-key="${esc(p.key)}">
                    ${i === 0 ? `<td class="pnr" rowspan="${t.parts.length}">${esc(t.pnr)}</td>` : ''}
                    <td class="part" title="${esc(p.label)}">${esc(p.label)}${p.split ? ' <span title="Posted on more than one seller" style="color:#b45309">⚠</span>' : ''}</td>
                    <td><select class="cfc-sel cfc-s2 cfc-seller"><option value=""></option>${sellerOpts(p.seller)}</select></td>
                    <td><input type="number" min="0" step="0.01" class="cfc-in num cfc-amt-in" value="${p.amount || ''}"></td>
                    ${i === 0 ? `<td rowspan="${t.parts.length}">${sideSelect('bearer', t.bearer, `data-ticket="${t.ticket_id}"`)}${t.agent_amount != null ? `<label class="cfc-lbl">Agent share</label><input type="number" min="0" step="0.01" class="cfc-in cfc-ticket-share" data-share-ticket="${t.ticket_id}" value="${t.agent_amount}">` : ''}</td>` : ''}</tr>`;
            }));
            h += `</tbody></table><div class="cfc-hint">We owe the seller this whoever bears it. Agent pays: it is on his line and comes back as Recovered (4181). We bear it: the Company Ledger carries it.</div></div>`;
            h += `<div class="cfc-card c-ours"><h4><i class="fas fa-hand-holding-dollar"></i> Our charge — billed to the agent</h4><div class="cfc-row2"><div><label class="cfc-lbl">Our charge</label><input type="number" min="0" step="0.01" class="cfc-in num" data-f="ours" value="${entry.ours || ''}"></div>
                <div class="cfc-hint">Our own fee (profit, 4183), always the agent's — it stays billed when the penalty moves to us, unless it is changed here.</div></div></div>`;
        } else {
            h += `<div class="cfc-card c-ours"><h4><i class="fas fa-hand-holding-dollar"></i> Our charge — billed to the agent</h4><div class="cfc-row2"><div><label class="cfc-lbl">Our charge</label><input type="number" min="0" step="0.01" class="cfc-in num" data-f="charge" value="${entry.charge || ''}"></div>
                <div>${entry.charge_bearer === 'company' ? `<label class="cfc-lbl">Who bears it</label>${sideSelect('charge_bearer', 'company')}` : '<div class="cfc-hint">Our own fee (profit, 4183), on the agent\'s ONE line with any penalty he bears.</div>'}</div></div></div>`;
        }
        if (entry.penalty) {
            h += `<div class="cfc-card c-kept"><h4><i class="fas fa-plane-circle-xmark"></i> What the airline kept</h4>`;
            if (entry.penalty.refunds.length) {
                h += `<table class="cfc-tbl"><thead><tr><th>PNR</th><th class="r">Ticket cost</th><th class="r">Airline refund</th><th class="r">Kept</th></tr></thead><tbody>`
                    + entry.penalty.refunds.map(r => `<tr data-refund="${r.ticket_id}"><td class="pnr">${esc(r.pnr)}</td><td class="r">${taka(r.cost)}</td>
                        <td class="r" style="width:150px"><input type="number" min="0" step="0.01" class="cfc-in num cfc-refund" data-cost="${r.cost}" data-was="${r.refund}" value="${r.refund}"></td>
                        <td class="r cfc-kept">${taka(r.cost - r.refund)}</td></tr>`).join('') + '</tbody></table>';
            }
            h += `<div class="cfc-row2" style="margin-top:10px"><div><label class="cfc-lbl">Airline kept on this entry</label><div class="cfc-kept" id="cfcPenalty" style="font-size:15px">${taka(entry.penalty.amount)}</div></div>
                <div><label class="cfc-lbl">Whose side it is</label>${sideSelect('fault', entry.penalty.fault)}</div></div>
                <div class="cfc-hint">A refund is corrected through Ticketing (the seller's refund row); what the airline kept moves with it.</div></div>`;
        }
        if (entry.fare) {
            h += `<div class="cfc-card c-fare"><h4><i class="fas fa-scale-unbalanced"></i> Fare difference</h4><div class="cfc-row2"><div><label class="cfc-lbl">Fare difference</label><input type="number" step="0.01" class="cfc-in num" data-f="fare" value="${entry.fare.amount}"></div>
                <div><label class="cfc-lbl">Who bears it</label>${sideSelect('fare_bearer', entry.fare.bearer)}</div></div>${entry.fare.agent_amount != null ? `<label class="cfc-lbl">Agent share (the remainder is ours)</label><input type="number" min="0" step="0.01" class="cfc-in num" data-f="agent_fare_amount" value="${entry.fare.agent_amount}">` : ''}
                <div class="cfc-hint">Inside the new ticket's price — only its bearer and figure move: Company 5172, the agent's share 4182.</div></div>`;
        }
        $('#cfcForm').html(h);
        $('#cfcDate').val(entry.date).attr('max', entry.date);
        $('#cfcReason').val('');
        if ($.fn.select2) {
            $('#cfcForm .cfc-s2').each(function () { $(this).select2({ dropdownParent: $('#cfcModal .cfc-box'), width: '100%', placeholder: '— choose —' }); });
        }
        if (window.cfrSwPaint) window.cfrSwPaint();
        lastOk = null;
        $('#cfcPreview').html('<div class="cfc-empty">Change a box on the left — each difference is shown here before anything posts.</div>');
        open();
        preview();
    }

    function open() { $('#cfcModal').removeAttr('hidden'); }

    function payload() {
        const d = { date: $('#cfcDate').val(), reason: $('#cfcReason').val().trim(), signature: E.signature, _token: csrf() };
        d.agent_id = $('#cfcForm [data-f="agent_id"]').val() || '';
        ['ours', 'charge', 'fare'].forEach(f => { const el = $(`#cfcForm [data-f="${f}"]`); if (el.length) d[f] = el.val() === '' ? 0 : el.val(); });
        ['fault', 'fare_bearer', 'charge_bearer'].forEach(f => { const el = $(`#cfcForm select[data-f="${f}"]`); if (el.length) d[f] = el.val() || ''; });
        if ($('#cfcForm [data-f="agent_fare_amount"]').length) d.agent_fare_amount = $('#cfcForm [data-f="agent_fare_amount"]').val();
        if (E.per_ticket) {
            d.tickets = E.tickets.map(t => ({
                ticket_id: t.ticket_id,
                bearer: $(`#cfcForm select[data-f="bearer"][data-ticket="${t.ticket_id}"]`).val() || '',
                agent_amount: $(`#cfcForm [data-share-ticket="${t.ticket_id}"]`).val() ?? null,
                parts: t.parts.map(p => { const tr = $(`#cfcForm tr[data-key="${p.key}"]`); return { key: p.key, seller: tr.find('.cfc-seller').val() || '', amount: tr.find('.cfc-amt-in').val() === '' ? 0 : tr.find('.cfc-amt-in').val() }; }),
            }));
        }
        if (E.penalty && E.penalty.refunds.length) {
            d.refunds = {};
            $('#cfcForm .cfc-refund').each(function () { d.refunds[$(this).closest('tr').data('refund')] = $(this).val(); });
        }
        return d;
    }

    function keptLive() {
        if (!E || !E.penalty) return;
        let pen = Number(E.penalty.amount) || 0;
        $('#cfcForm .cfc-refund').each(function () {
            const cost = Number($(this).data('cost')), was = Number($(this).data('was')), now = Math.min(cost, Math.max(0, Number($(this).val()) || 0));
            $(this).closest('tr').find('.cfc-kept').text(taka(cost - now));
            pen -= (now - was);
        });
        $('#cfcPenalty').text(taka(Math.max(0, pen)));
    }

    function preview() {
        if (!E || E.problem) return;
        const my = ++seq;
        $.ajax({ url: URLS.preview.replace('__ID__', E.id), method: 'POST', data: payload(), headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } })
            .done(r => { if (my === seq) show(r); })
            .fail(x => { if (my !== seq) return; lastOk = null; $('#cfcPreview').html(`<div class="cfc-err">${esc(fail(x, 'Could not work out the correction.'))}</div>`); gate(); });
    }

    function show(r) {
        lastOk = r;
        if (!r.preview.length && !r.changed.length && !r.rows_change) {
            $('#cfcPreview').html('<div class="cfc-empty">Nothing changes yet — the entry already says this.</div>');
            gate();
            return;
        }
        let h = r.changed.length ? `<ul class="cfc-changed">${r.changed.map(c => `<li>${esc(c)}</li>`).join('')}</ul>` : '';
        let dr = 0, cr = 0, rows = 0;
        r.preview.forEach(op => {
            h += `<div class="cfc-op k-${esc(op.kind || '')}"><div class="h">${esc(op.title)}</div>`;
            if (op.party) {
                rows++;
                h += `<div class="cfc-party"><span class="who">${esc(op.party.ledger)} · ${esc(op.party.name)}</span><span class="txt">${esc(op.party.text)} · ${esc(op.party.date)}</span>`
                    + (op.party.dr > 0 ? `<span class="cfc-amt dr">Dr ${taka(op.party.dr)}</span>` : '') + (op.party.cr > 0 ? `<span class="cfc-amt cr">Cr ${taka(op.party.cr)}</span>` : '') + '</div>';
            }
            if (op.journal) {
                h += `<div class="cfc-jl"><span class="hd">Journal · ${esc(op.journal.date)}</span><span class="hd d">Debit</span><span class="hd c">Credit</span>`
                    + op.journal.lines.map(l => { dr += l.dr; cr += l.cr; return `<span class="n">${esc(l.code)} ${esc(l.name)}</span><span class="d">${l.dr ? taka(l.dr) : ''}</span><span class="c">${l.cr ? taka(l.cr) : ''}</span>`; }).join('') + '</div>';
            }
            h += '</div>';
        });
        if (!r.preview.length) h += '<div class="cfc-empty">No money moves — only how the entry reads (who pays) is put right.</div>';
        h += `<div class="cfc-tot"><span><b>${rows}</b> party row${rows === 1 ? '' : 's'}</span><span>Journals: Dr <b>${taka(dr)}</b> = Cr <b>${taka(cr)}</b> ${Math.abs(dr - cr) < 0.005 ? '✓ balanced' : '✗ NOT balanced'}</span><span>Nothing is deleted — every row is added beside the original.</span></div>`;
        $('#cfcPreview').html(h);
        gate();
    }

    function gate() {
        const ok = lastOk && (lastOk.preview.length || lastOk.rows_change) && $('#cfcReason').val().trim() !== '' && $('#cfcDate').val();
        $('#cfcPost').prop('disabled', !ok).attr('title', !lastOk ? 'Fix the boxes first' : (!$('#cfcReason').val().trim() ? 'Write why it is corrected' : ''));
    }

    window.cfcPost = function () {
        if ($('#cfcPost').prop('disabled')) return;
        $('#cfcPost').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Posting…');
        $.ajax({ url: URLS.apply.replace('__ID__', E.id), method: 'POST', data: payload(), headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } })
            .done(r => {
                if (!r.success) { Swal.fire({ icon: 'error', title: 'Not posted', text: r.message }); return; }
                cfcClose();
                if (typeof window.renderFlightChanges === 'function' && r.changes) window.renderFlightChanges(r.changes);
                Swal.fire({ icon: 'success', title: 'Correction posted', text: r.message }).then(() => window.location.reload());
            })
            .fail(x => Swal.fire({ icon: 'error', title: 'Not posted', text: fail(x, 'Could not post the correction.') }))
            .always(() => $('#cfcPost').html('<i class="fas fa-check"></i> Confirm &amp; post'));
    };

    $(document).on('input change', '#cfcForm input, #cfcForm select', function () {
        keptLive();
        clearTimeout(timer);
        timer = setTimeout(preview, 350);
    });
    $(document).on('input change', '#cfcDate', () => { clearTimeout(timer); timer = setTimeout(preview, 350); });
    $(document).on('input', '#cfcReason', gate);
    // closes only on the ✕ (owner's rule): Esc does nothing here
    $(document).on('keydown', e => { if (e.key === 'Escape' && E && !$('#cfcModal').is('[hidden]')) e.stopPropagation(); });
})();
</script>
@endif
