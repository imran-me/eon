{{-- Emitted only when available(): route() on a name a stale route cache
     does not have would throw and take the whole page down. Absent, the
     buttons that call into it are not rendered either. --}}
@if(\App\Models\ContractFlightPassengerChange::available())
@php
    // One passenger off a flight and onto another (owner, 2026-09-26) —
    // ContractFlightRebookController. Built as a PHP array and emitted with a
    // single @json, same reason as operations-script: @json(expr) splits its
    // expression on commas.
    $cfrConfig = [
        'cancel' => route('role.contract-flight-passengers.cancel-from-flight', ['role' => $role, 'contractFlightPassenger' => '__ID__']),
        'flights' => route('role.contract-flight-passengers.reassign-flights', ['role' => $role, 'contractFlightPassenger' => '__ID__']),
        'reassign' => route('role.contract-flight-passengers.reassign', ['role' => $role, 'contractFlightPassenger' => '__ID__']),
        'restore' => route('role.contract-flight-passengers.restore', ['role' => $role, 'contractFlightPassenger' => '__ID__']),
        'undoPreview' => \Illuminate\Support\Facades\Route::has('role.contract-flight-passengers.undo-preview')
            ? route('role.contract-flight-passengers.undo-preview', ['role' => $role, 'contractFlightPassenger' => '__ID__']) : null,
        'charge' => route('role.contract-flight-passenger-changes.charge', ['role' => $role, 'contractFlightPassengerChange' => '__ID__']),
        'reasons' => \App\Models\ContractFlightPassengerChange::CANCEL_REASONS,
        'bearers' => \App\Models\ContractFlightPassengerChange::BEARERS,
        'faults' => \App\Models\ContractFlightPassengerChange::FAULTS,
        'hasFault' => \App\Models\ContractFlightPassengerChange::hasFaultColumns(),
        'flagBase' => asset('flags'),
        'index' => route('role.contract-flights.index', ['role' => $role]),
        // Offload / Deported (2026-10-07): null when its route is not registered.
        'offload' => \App\Models\ContractFlightPassengerChange::offloadAvailable()
            ? route('role.contract-flight-passengers.offload', ['role' => $role, 'contractFlightPassenger' => '__ID__']) : null,
        'offloadEvents' => \App\Models\ContractFlightPassengerChange::OFFLOAD_EVENTS,
        'deportCosts' => \App\Models\ContractFlightPassengerChange::DEPORT_COSTS,
    ];
@endphp
<script>
(function () {
    const CFR = @json($cfrConfig);
    const esc = s => $('<div>').text(s == null ? '' : String(s)).html();
    const taka = v => '৳' + (Number(v) || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
    const failText = (xhr, fallback) => (xhr && xhr.responseJSON && (xhr.responseJSON.message
        || Object.values(xhr.responseJSON.errors || {})[0]?.[0])) || fallback;
    const splitFields = prefix => `<div class="${prefix}-split" style="display:none;margin-top:6px;font-size:11px;"><label>Agent bears<input type="number" class="${prefix}-agent" min="0" step="0.01" style="width:100%;box-sizing:border-box;height:30px;border:1px solid #cbd5e1;border-radius:6px;color:#dc2626;"></label><div class="${prefix}-ours" style="margin-top:4px;color:#b45309;"></div></div>`;
    const agentPart = (amount, side, share) => side === 'agent' ? amount : (side === 'split' ? Number(share) || 0 : 0);
    const invalidSplit = (amount, side, share) => side === 'split' && (share == null || share === '' || !Number.isFinite(Number(share)) || Number(share) < 0 || Number(share) > amount);
    const sideText = (side, amount, share) => side === 'split' ? 'Agent ' + taka(share) + ' / we bear ' + taka(amount - Number(share)) : (side === 'agent' ? 'Agent pays' : 'We bear it');
    function paintSplit(card, prefix, amount, side) {
        card.find('.' + prefix + '-split').toggle(side === 'split');
        card.find('.' + prefix + '-agent').attr('max', amount);
        card.find('.' + prefix + '-ours').text('We bear ' + taka(Math.max(0, amount - Number(card.find('.' + prefix + '-agent').val()))));
    }


    // ── Cancel from flight ────────────────────────────────────────────
    // p: { id, name, flight, agent, agent_price, pnrs[] }. Nothing is
    // written until the dialog is confirmed; the backdrop never closes it.
    function cancelDialog(p, opts = {}) {
        const reasons = Object.entries(CFR.reasons).map(([k, v]) => `<option value="${esc(k)}">${esc(v)}</option>`).join('');
        const tickets = p.tickets || [];
        const hasTicket = tickets.length > 0 || !!(p.pnrs && p.pnrs.length);
        // Laid out wide, in two columns (owner, 2026-09-28: "no scroll
        // needed"): the decisions on the left, the ticket and its refund on
        // the right, and one short line on what Save does underneath.
        const F = 'width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:6px 9px;font-size:13px;';
        const L = 'display:block;font-weight:600;font-size:12px;margin:0 0 3px;color:#334155;';
        const H = 'font-size:11px;color:#64748b;line-height:1.35;margin:3px 0 8px;';
        const refundRows = tickets.map(t => `
            <div style="display:grid;grid-template-columns:minmax(0,1fr) 110px;gap:6px;align-items:center;margin-top:4px;">
                <span style="font-size:12px;color:#7f1d1d;">${esc(t.no)} · cost ${taka(t.amount)}</span>
                <input type="number" class="cfr-refund" data-id="${t.id}" data-cost="${Number(t.amount) || 0}" min="0" max="${Number(t.amount) || 0}" step="0.01" value="${Number(t.amount) || 0}"
                    title="Airline refund on this ticket" style="width:100%;box-sizing:border-box;border:1px solid #fca5a5;border-radius:6px;padding:4px 7px;font-size:12px;">
            </div>`).join('');
        // From the Re-assign button (owner, 2026-09-30): his ticket is NOT
        // cancelled here — Re-assign decides it (reschedule keeps it, reissue
        // cancels it then). cancel_ticket posts 0 because the box is absent.
        const ticketBox = opts.keepTicket ? `<div style="font-size:12px;color:#166534;border:1px solid #bbf7d0;background:#f0fdf4;border-radius:8px;padding:8px 10px;">
                <b>His ticket is kept</b>${hasTicket ? ' — ' + esc((p.pnrs || tickets.map(t => t.no)).join(', ')) : ''}.<br>
                Next, on <b>Re-assign</b> under Cancelled: <b>Reschedule</b> = same ticket · <b>Reissue</b> = old ticket cancelled then, new one issued.</div>`
            : hasTicket ? `
            <div style="border:1px solid #fecaca;background:#fff7f7;border-radius:8px;padding:8px 10px;">
                <label style="display:flex;gap:8px;align-items:flex-start;cursor:pointer;margin:0;">
                    <input type="checkbox" id="cfrCancelTicket" checked style="margin-top:3px;">
                    <span style="font-size:12px;color:#7f1d1d;line-height:1.35;"><b>Cancel his ticket too</b> — ${esc((p.pnrs || tickets.map(t => t.no)).join(', '))}<br>
                    <span style="color:#991b1b;">Untick only if he will fly later on this same ticket.</span></span>
                </label>
                ${refundRows ? `<div id="cfrRefunds" style="margin-top:6px;"><div style="font-size:11px;font-weight:700;color:#7f1d1d;">Airline refund <span style="font-weight:400;">— what the airline gives back</span></div>${refundRows}<div id="cfrPenaltyNote" style="font-size:11px;color:#b91c1c;margin-top:4px;"></div></div>` : ''}
            </div>` : `<div style="font-size:12px;color:#64748b;border:1px dashed #cbd5e1;border-radius:8px;padding:8px 10px;">He holds no live ticket — nothing to cancel.</div>`;
        const faultSel = CFR.hasFault ? `<label style="${L}">Whose side caused it?</label>
            <select id="cfrCancelFault" class="cfr-sw-src" style="${F}">
                <option value="">— Choose —</option>
                ${Object.entries(CFR.faults).filter(([k]) => k !== 'agent' || p.agent).map(([k, v]) => `<option value="${esc(k)}">${esc(v)}</option>`).join('')}
            </select>
            <div style="${H}"><b>Our side</b>: we moved or cancelled it — we bear any penalty.<br><b>Passenger / agent side</b>: he or his agent caused it — the penalty is billed to the agent.</div>` : '';
        const what = [
            `He leaves <b>${esc(p.flight || 'the flight')}</b> and waits under <b>Cancelled</b>`,
            (p.agent && Number(p.agent_price) > 0) ? `his package <b>${taka(p.agent_price)}</b> gets a reversal line on ${esc(p.agent)}'s statement (charged again when re-booked)` : null,
            'nothing is deleted',
        ].filter(Boolean).join(' · ');

        Swal.fire({
            // No icon: on a laptop (≈623px) the icon pushed the buttons off screen.
            title: `<span style="font-size:19px;">Take ${esc(p.name)} off ${esc(p.flight || 'this flight')}?</span>`,
            width: 880,
            padding: '14px 18px 12px',
            html: `<div style="text-align:left;font-size:13px;">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;">
                        <div>
                            <label style="${L}">Why</label>
                            <select id="cfrCancelReason" style="${F}margin-bottom:8px;">
                                <option value="">— Choose a reason —</option>${reasons}
                            </select>
                            ${faultSel}
                        </div>
                        <div>
                            ${ticketBox}
                            ${p.agent ? `<label style="${L}margin-top:8px;">Cancellation charge <span style="font-weight:400;color:#94a3b8;">(optional — our own fee, billed to ${esc(p.agent)})</span></label>
                            <input id="cfrCancelCharge" type="number" min="0" step="0.01" placeholder="৳ 0.00 — none" style="${F}">` : ''}
                            <label style="${L}margin-top:8px;">Note <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
                            <input id="cfrCancelNote" maxlength="2000" type="text" style="${F}">
                        </div>
                    </div>
                    <div style="margin-top:10px;padding:7px 10px;border-radius:8px;background:#f8fafc;border:1px solid #e2e8f0;font-size:11.5px;color:#475569;line-height:1.4;">${what}.</div>
                   </div>`,
            showCancelButton: true,
            confirmButtonText: 'Cancel from flight',
            cancelButtonText: 'Keep him on',
            confirmButtonColor: '#dc2626',
            allowOutsideClick: false,
            focusConfirm: false,
            didOpen: () => {
                const paint = () => {
                    const box = document.getElementById('cfrCancelTicket');
                    const wrap = document.getElementById('cfrRefunds');
                    if (wrap) wrap.style.display = box && box.checked ? '' : 'none';
                    let pen = 0;
                    document.querySelectorAll('.cfr-refund').forEach(i => { pen += Math.max(0, (Number(i.dataset.cost) || 0) - (Number(i.value) || 0)); });
                    const note = document.getElementById('cfrPenaltyNote');
                    if (note) note.innerHTML = pen > 0 ? `Airline penalty <b>${taka(pen)}</b> — who bears it follows "Whose side caused it?".` : 'Full refund — no penalty.';
                };
                document.querySelectorAll('.cfr-refund, #cfrCancelTicket').forEach(el => el.addEventListener('input', paint));
                document.getElementById('cfrCancelTicket')?.addEventListener('change', paint);
                paint();
            },
            preConfirm: () => {
                const reason = document.getElementById('cfrCancelReason').value;
                if (!reason) { Swal.showValidationMessage('Choose why he is coming off the flight.'); return false; }
                const faultEl = document.getElementById('cfrCancelFault');
                const box = document.getElementById('cfrCancelTicket');
                const chargeEl = document.getElementById('cfrCancelCharge');
                const charge = chargeEl ? (Number(chargeEl.value) || 0) : 0;
                if (charge < 0) { Swal.showValidationMessage('The charge cannot be negative.'); return false; }
                const refunds = {};
                let pen = 0, bad = false;
                if (box && box.checked) {
                    document.querySelectorAll('.cfr-refund').forEach(i => {
                        const v = Number(i.value) || 0, cost = Number(i.dataset.cost) || 0;
                        if (v < 0 || v > cost) bad = true;
                        refunds[i.dataset.id] = v;
                        pen += Math.max(0, cost - v);
                    });
                }
                if (bad) { Swal.showValidationMessage('A refund must be between 0 and the ticket cost.'); return false; }
                if (faultEl && pen > 0 && !faultEl.value) { Swal.showValidationMessage('The airline keeps a penalty — say whose side caused it.'); return false; }
                return { reason, note: document.getElementById('cfrCancelNote').value.trim() || null, cancel_ticket: box && box.checked ? 1 : 0,
                    charge: charge > 0 ? charge : null, fault: faultEl ? (faultEl.value || null) : null, refunds };
            },
        }).then(result => {
            if (!result.isConfirmed) return;
            $.ajax({ url: CFR.cancel.replace('__ID__', p.id), method: 'PATCH', data: result.value })
                .done(r => {
                    if (!r.success) { Swal.fire({ icon: 'error', title: 'Not cancelled', text: r.message || 'Something went wrong.' }); return; }
                    Swal.fire({ icon: 'success', title: 'Moved to Cancelled', text: r.message, timer: 1800, showConfirmButton: false })
                        .then(() => window.location.reload());
                })
                .fail(xhr => Swal.fire({ icon: 'error', title: 'Not cancelled', text: failText(xhr, 'Could not take him off the flight.') }));
        });
    }

    // ── A money-affecting flag from the edit modal (2026-09-28) ─────────
    // Flight cancelled / missed / boarding denied / deported: what happens to
    // his ticket, what the airline refunds, and whose side caused it. Resolves
    // with the money_event payload, or null if the person backs out.
    window.cfpMoneyEventDialog = function (p, flag, label) {
        const tickets = (p.ticket_purchases || []).filter(t => t.status !== 'cancelled')
            .map(t => ({ id: t.id, no: t.ticket_no || ('#' + t.id), amount: Number(t.amount) || 0 }));
        // A deported passenger flew: his ticket was used, nothing to refund.
        const deported = flag === 'deported' || flag === 'deported_we_bear';
        const flown = deported;
        const rows = tickets.map(t => `
            <div style="display:grid;grid-template-columns:minmax(0,1fr) 120px;gap:6px;align-items:center;margin-top:4px;">
                <span style="font-size:12px;color:#7f1d1d;">${esc(t.no)} · cost ${taka(t.amount)}</span>
                <input type="number" class="cfm-refund" data-id="${t.id}" data-cost="${t.amount}" min="0" max="${t.amount}" step="0.01" value="${t.amount}"
                    style="width:100%;box-sizing:border-box;border:1px solid #fca5a5;border-radius:6px;padding:4px 7px;font-size:12px;">
            </div>`).join('');
        const voids = flag === 'flight_cancelled';
        return Swal.fire({
            title: `<span style="font-size:19px;">${esc(label || 'Exception')} — ${esc(p.name || 'passenger')}</span>`,
            width: 880,
            padding: '14px 18px 12px',
            html: `<div style="text-align:left;font-size:13px;"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;align-items:start;">
                <div>
                ${CFR.hasFault ? `<label style="display:block;font-weight:600;font-size:12px;margin:0 0 4px;">Whose side caused it?</label>
                <select id="cfmFault" class="cfr-sw-src" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:7px 9px;font-size:13px;margin-bottom:6px;">
                    <option value="">— Choose —</option>
                    ${Object.entries(CFR.faults).filter(([k]) => k !== 'agent' || p.agent_id).map(([k, v]) => `<option value="${esc(k)}">${esc(v)}</option>`).join('')}
                </select>` : ''}
                <ul style="margin:0 0 0 16px;padding:0;list-style:disc;color:#475569;font-size:12px;line-height:1.45;">
                    ${deported
                        ? `<li><b>Passenger / agent side:</b> the agent pays his package${Number(p.agent_price) > 0 ? ' <b>' + taka(p.agent_price) + '</b>' : ''} — the charge stands and the case closes. <b>Our side:</b> we bear it — the package is reversed.</li>`
                        : (voids && Number(p.agent_price) > 0 ? `<li>His package <b>${taka(p.agent_price)}</b> stays on the agent's statement and a reversal is added beside it.</li>` : '<li>His package charge stands — he still travels on our arrangement.</li>')}
                    <li>A penalty the airline keeps: <b>our side</b> → Company Ledger; <b>passenger / agent side</b> → also billed to the agent.</li>
                </ul>
                </div>
                <div>
                ${tickets.length ? `<div style="border:1px solid #fecaca;background:#fff7f7;border-radius:8px;padding:8px 10px;margin:0 0 8px;">
                    <label style="display:flex;gap:8px;align-items:flex-start;cursor:pointer;margin:0;">
                        <input type="checkbox" id="cfmCancelTicket" ${flown ? '' : 'checked'} style="margin-top:3px;">
                        <span style="font-size:12px;color:#7f1d1d;"><b>Cancel his ticket</b> — ${esc(tickets.map(t => t.no).join(', '))}<br>
                        <span style="color:#991b1b;">Stays in Ticket Orders at its amount; the refund is posted beside it. Leave unticked if the ticket was used or will be reused.</span></span>
                    </label>
                    <div id="cfmRefunds" style="margin-top:4px;"><div style="font-size:11px;font-weight:700;color:#7f1d1d;">Airline refund</div>${rows}
                    <div id="cfmPenalty" style="font-size:11px;color:#b91c1c;margin-top:4px;"></div></div>
                </div>` : '<div style="font-size:12px;color:#64748b;border:1px dashed #cbd5e1;border-radius:8px;padding:8px 10px;">He holds no live ticket — nothing to cancel.</div>'}
                </div></div></div>`,
            showCancelButton: true,
            confirmButtonText: 'Save',
            confirmButtonColor: '#dc2626',
            allowOutsideClick: false,
            focusConfirm: false,
            didOpen: () => {
                const paint = () => {
                    const on = document.getElementById('cfmCancelTicket')?.checked;
                    const wrap = document.getElementById('cfmRefunds');
                    if (wrap) wrap.style.display = on ? '' : 'none';
                    let pen = 0;
                    document.querySelectorAll('.cfm-refund').forEach(i => { pen += Math.max(0, (Number(i.dataset.cost) || 0) - (Number(i.value) || 0)); });
                    const n = document.getElementById('cfmPenalty');
                    if (n) n.innerHTML = pen > 0 ? `Airline penalty <b>${taka(pen)}</b>.` : 'Full refund — no penalty.';
                };
                document.querySelectorAll('.cfm-refund').forEach(el => el.addEventListener('input', paint));
                document.getElementById('cfmCancelTicket')?.addEventListener('change', paint);
                paint();
            },
            preConfirm: () => {
                const on = !!document.getElementById('cfmCancelTicket')?.checked;
                const refunds = {};
                let pen = 0, bad = false;
                if (on) document.querySelectorAll('.cfm-refund').forEach(i => {
                    const v = Number(i.value) || 0, c = Number(i.dataset.cost) || 0;
                    if (v < 0 || v > c) bad = true;
                    refunds[i.dataset.id] = v;
                    pen += Math.max(0, c - v);
                });
                if (bad) { Swal.showValidationMessage('A refund must be between 0 and the ticket cost.'); return false; }
                const fault = document.getElementById('cfmFault')?.value || null;
                if (CFR.hasFault && !fault && (pen > 0 || deported)) { Swal.showValidationMessage(deported ? 'Say whose side the deportation was — it decides whether the agent pays the package.' : 'The airline keeps a penalty — say whose side caused it.'); return false; }
                return { cancel_ticket: on ? 1 : 0, refunds, fault };
            },
        }).then(r => r.isConfirmed ? r.value : null);
    };

    // From a list row: the button carries everything in data-cfp.
    window.cfpCancelFromFlight = function (btn, opts) {
        let p = {};
        try { p = JSON.parse(btn.getAttribute('data-cfp') || '{}'); } catch (e) { return; }
        cancelDialog(p, opts || {});
    };

    // From the passenger edit modal: read the passenger already in the cache.
    window.cfpCancelFromEditModal = function () {
        const id = Number($('#pem_id').val());
        const p = (typeof cfpCurrentPassengers !== 'undefined' ? cfpCurrentPassengers : []).find(x => x.id === id);
        if (!p) return;
        cancelDialog({
            id: p.id, name: p.name || 'Passenger', flight: p.flight_number, agent: p.agent_name, agent_price: p.agent_price,
            pnrs: (p.ticket_purchases || []).filter(t => t.status !== 'cancelled').map(t => t.ticket_no || ('#' + t.id)),
            tickets: (p.ticket_purchases || []).filter(t => t.status !== 'cancelled').map(t => ({ id: t.id, no: t.ticket_no || ('#' + t.id), amount: Number(t.amount) || 0 })),
        });
    };

    // ── Re-assign ─────────────────────────────────────────────────────
    let cfr = null;          // the passenger being re-assigned (data-cfp)
    let cfrFlights = [];
    let cfrSourceRouteKey = null;
    let cfrServices = [], cfrPayees = [], cfrOffBuilt = false;

    window.cfpOpenReassign = function (btn) {
        try { cfr = JSON.parse(btn.getAttribute('data-cfp') || '{}'); } catch (e) { return; }
        $('#cfr_id').val(cfr.id);
        $('#cfr_title').text('Re-assign ' + (cfr.name || 'passenger'));
        $('#cfr_sub').text(cfr.on_flight ? 'On ' + (cfr.from_flight || 'a flight') + ' — moves from there'
            : ['Off flight', cfr.from_flight ? 'was ' + cfr.from_flight : null, cfr.reason].filter(Boolean).join(' · '));
        // Same ticket is only possible while he still holds a live one.
        const canReschedule = (cfr.held || []).length > 0;
        $('input[name="cfr_kind"][value="reschedule"]').prop('disabled', !canReschedule).closest('.cfr-kind').css('opacity', canReschedule ? '' : '.5')
            .find('.cfr-kind-s').html(canReschedule ? 'Same ticket, new flight — his live PNR goes with him.'
                : `<span style="color:#b91c1c;">Not available — he holds no live ticket${(cfr.cancelled || []).length ? ' (' + esc(cfr.cancelled.join(', ')) + ' already cancelled)' : ''}. Link or issue a ticket first.</span>`);
        $('input[name="cfr_kind"][value="' + (canReschedule ? 'reschedule' : 'reissue') + '"]').prop('checked', true);
        // Offload / Deported: only for someone still on a flight.
        const canOffload = !!(cfr.on_flight && CFR.offload);
        $('#cfr_kind_off').toggle(canOffload);
        $('#cfr_kinds_main').toggleClass('has-3', canOffload);
        window.cfswOpen && window.cfswOpen(cfr); // Suspend withdraw card (partials.suspend-withdraw-script)
        $('input[name="cfr_off_event"][value="immigration_offload"], input[name="cfr_off_next"][value="rebook"]').prop('checked', true);
        $('#cfr_off_fault').val('');
        $('#cfr_pkg_agent, #cfr_fare_agent').val('');
        $('#cfr_pkg_split, #cfr_fare_split_fields').hide();
        $('#cfr_fare_split').prop('checked', false);
        $('#cfr_dep_from').val('');
        cfrServices = []; cfrPayees = [];
        $('#cfr_off_fault option[value="agent"], #cfr_off_fault option[value="split"]').prop('disabled', !cfr.agent);
        $('#cfr_charge').prop('disabled', false).attr('placeholder', '৳ 0.00 (none)');
        $('#cfr_search').val('');
        cfrOffBuilt = false;
        $('#cfr_charge').val('');
        $('#cfr_bearer').val(cfr.agent ? 'agent' : 'company');
        $('#cfr_fare').val('');
        $('#cfr_fare_bearer').val('company');
        $('#cfr_fare_bearer option[value="agent"]').prop('disabled', !cfr.agent);
        $('#cfr_nf').removeClass('is-open');
        $('#cfr_nf_no, #cfr_nf_dep').val('');
        $('#cfr_fare_hint').text('Leave blank on a reissue: it is worked out when his new ticket is linked.');
        $('#cfr_note').val('');
        applyKind();
        $('#cfr_error').addClass('hidden').text('');
        $('#cfr_flights').html('<div class="cfr-empty">Loading open flights…</div>');
        cfrFlights = [];
        cfrSourceRouteKey = null;
        renderSummary();
        $('#cfpReassignOverlay').removeClass('hidden');

        $.get(CFR.flights.replace('__ID__', cfr.id), { route_id: cfr.route_id || '' }).done(r => {
            cfrFlights = (r && r.flights) || [];
            cfrSourceRouteKey = (r && r.source_route_key) || null;
            cfrServices = (r && r.services) || [];
            cfrPayees = (r && r.payees) || [];
            cfrOffBuilt = false;
            const sel = $('#cfr_nf_route').html('<option value="">Route — pick later</option>');
            ((r && r.routes) || []).forEach(o => sel.append(`<option value="${o.id}">${esc(o.label)}</option>`));
            if (r && Number(r.old_ticket_cost) > 0) $('#cfr_fare_hint').html(`Old ticket cost <b>${taka(r.old_ticket_cost)}</b>. Blank on a reissue = worked out from the new ticket.`);
            applyKind();
        }).fail(() => $('#cfr_flights').html('<div class="cfr-empty">Could not load flights — close and try again.</div>'));
    };

    // Reschedule / Reissue: same route, same legs, same tickets — only the
    // flight and its date change, with a charge per ticket (owner,
    // 2026-09-30). The per-ticket figures sum into the change's one charge
    // (same posting, same bearer rule) and are itemised in its note.
    const isResched = () => $('input[name="cfr_kind"]:checked').val() === 'reschedule';
    const isOffload = () => $('input[name="cfr_kind"]:checked').val() === 'offload';
    const offEvent = () => $('input[name="cfr_off_event"]:checked').val() || 'immigration_offload';
    const offNext = () => offEvent() === 'deported' ? 'hold' : ($('input[name="cfr_off_next"]:checked').val() || 'rebook');
    const perTicketMode = () => isResched() && cfr && (cfr.held || []).length > 0;
    // Refund / New Ticket with tickets to refund: our charge sits on each
    // refund card beside its penalty (owner, 2026-10-13 — as Offload does);
    // the cards' sum is what #cfr_charge posts, billed to the agent.
    const refundCardsMode = () => !isResched() && !isOffload() && cfr && (cfr.held || []).length > 0;
    function syncRefundFees() {
        if (!refundCardsMode()) return;
        let fee = 0;
        $('.cfr-rf').each(function () {
            const on = $(this).find('.cfr-rf-on').prop('checked');
            if (on) fee += Number($(this).find('.cfr-rf-fee').val()) || 0;
        });
        $('#cfr_charge').val(fee > 0 ? fee.toFixed(2) : '');
        if (cfr && cfr.agent) $('#cfr_bearer').val('agent');
    }
    function ticketCharges() {
        return $('.cfr-tc').filter(function () { return $(this).find('.cfr-tc-on').prop('checked'); }).map(function () {
            return { ticket: String($(this).data('no')), ticket_id: $(this).data('id') || null,
                travel_date: $(this).find('.cfr-tc-date').val() || null,
                ticket_number: ($(this).find('.cfr-tc-tno').val() || '').trim() || null,
                amount: Number($(this).find('.cfr-tc-amt').val()) || 0,
                fee: Number($(this).find('.cfr-tc-fee').val()) || 0,
                agent_amount: $(this).find('.cfr-tc-agent').val(),
                bearer: $(this).find('.cfr-tc-b.on').data('b') || 'agent' };
        }).get();
    }
    function chargeTotal() {
        return perTicketMode() ? ticketCharges().reduce((t, c) => t + c.amount + c.fee, 0) : (Number($('#cfr_charge').val()) || 0);
    }
    // Cost breakdown per leg: main fare, every earlier reissue penalty on
    // that ticket, the one being typed now, and the total.
    const cfrTicketMoney = {};
    function paintBreakdowns() {
        $('.cfr-tc').each(function () {
            const card = $(this);
            const m = cfrTicketMoney[card.data('no')] || { fare: 0, penalties: [] };
            const on = card.find('.cfr-tc-on').prop('checked');
            const now = on ? (Number(card.find('.cfr-tc-amt').val()) || 0) : 0;
            const n = m.penalties.length;
            const row = (cls, k, v) => `<div class="${cls}"><span>${k}</span><b>${taka(v)}</b></div>`;
            card.find('.cfr-tc-cb').html([
                row('', 'Main fare', m.fare),
                ...m.penalties.map((v, i) => row('pen', 'Reissue penalty' + ((n + (now > 0 ? 1 : 0)) > 1 ? ' ' + (i + 1) : ''), v)),
                now > 0 ? row('now', 'Reissue penalty' + (n ? ' ' + (n + 1) : ''), now) : '',
                row('tot', 'Total', m.fare + m.penalties.reduce((t, v) => t + v, 0) + now),
            ].join(''));
        });
    }
    function paintTicketTotals() {
        paintBreakdowns();
        const rows = ticketCharges();
        $('.cfr-tc').each(function () { const c = rows.find(x => String(x.ticket_id) === String($(this).data('id'))); if (c) paintSplit($(this), 'cfr-tc', c.amount, c.bearer); });
        // our charge is always the agent's (owner, 2026-10-13)
        const a = rows.reduce((t, c) => t + agentPart(c.amount, c.bearer, c.agent_amount), 0) + rows.reduce((t, c) => t + c.fee, 0);
        const w = rows.reduce((t, c) => t + c.amount - agentPart(c.amount, c.bearer, c.agent_amount), 0);
        $('#cfr_tc_total').html(`<span><i class="fas fa-user-tie"></i> Agent pays <b>${taka(a)}</b></span><span><i class="fas fa-building"></i> We bear <b>${taka(w)}</b></span><span class="cfr-tc-sum">Total <b>${taka(a + w)}</b></span>`);
    }
    // Leg dates are free (owner, 2026-10-04): each leg is reissued or
    // refunded on its own, so no leg's date blocks another's. Kept as a
    // no-op so every caller stays as it was.
    function checkLegOrder() {
        $('.cfr-tc').removeClass('is-bad').find('.cfr-tc-err').html('');
        return true;
    }

    // Only a later leg changes (owner, 2026-10-04): the flight card follows
    // the FIRST leg, so while leg 1 is untouched he stays on his flight -
    // the later leg's reissue / refund posts exactly as before, he just is
    // not moved. Leg 1 switched on (alone or with others) = he moves.
    function stayOnFlight() {
        if (!cfr || !cfr.on_flight || !cfr.flight_id || closing() || isOffload()) return false;
        const held = cfr.held || [];
        const refund = refundMode();
        let any = false, first = false;
        $(refund ? '.cfr-rf' : '.cfr-tc').each(function () {
            if (!$(this).find(refund ? '.cfr-rf-on' : '.cfr-tc-on').prop('checked')) return;
            any = true;
            const t = held.find(h => String(h.id) === String($(this).data('id')));
            if (!t || Number(t.seq || 1) <= 1) first = true;
        });
        return any && !first;
    }
    function applyStay() {
        const stay = stayOnFlight();
        if (!$('#cfr_stay_note').length) {
            $('#cfr_flight_sec').after('<div class="cfr-sec" id="cfr_stay_note" style="display:none;"><div class="cfr-h">Flight</div><div class="cfr-stay-msg" style="font-size:12.5px;color:#0f766e;background:#f0fdfa;border:1px dashed #99f6e4;border-radius:10px;padding:9px 12px;"></div></div>');
        }
        $('#cfr_stay_note').toggle(stay).find('.cfr-stay-msg').html(stay
            ? `<i class="fas fa-plane"></i> Stays on <b>${esc(cfr.from_flight || 'his flight')}</b> &mdash; only a later leg changes, and the flight follows the first leg.`
            : '');
        $('#cfr_flight_sec').toggle(!closing() && !stay && (!isOffload() || offNext() === 'rebook'));
    }

    function applyKind() {
        if (window.cfswApply && window.cfswApply()) return;
        const r = perTicketMode();
        const canAgent = !!(cfr && cfr.agent);
        $('#cfr_ticket_charges').html(r ? `<style>
                #cfpReassignOverlay .cfr-tc{display:grid;grid-template-columns:minmax(0,1fr) 150px 120px 125px 105px 105px auto;gap:16px;align-items:center;border:1px solid #e2e8f0;border-left:4px solid #0f766e;border-radius:10px;padding:12px 16px;background:#fff;}
                #cfpReassignOverlay .cfr-tc-pnr{display:flex;align-items:center;gap:9px;min-width:0;}
                #cfpReassignOverlay .cfr-tc-ic{width:30px;height:30px;border-radius:8px;background:#ccfbf1;color:#0f766e;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
                #cfpReassignOverlay .cfr-tc-pnr b{display:block;font-size:13px;color:#0f172a;}
                #cfpReassignOverlay .cfr-tc-pnr small{display:block;font-size:10.5px;color:#64748b;}
                #cfpReassignOverlay .cfr-tc-route{display:flex;align-items:center;gap:5px;font-size:13px;font-weight:400;color:#0f172a;}
                #cfpReassignOverlay .cfr-tc-route img{width:18px;height:12px;object-fit:cover;border-radius:2px;box-shadow:0 0 0 1px #e2e8f0;}
                #cfpReassignOverlay .cfr-tc-route i{color:#0f766e;font-size:11px;}
                #cfpReassignOverlay .cfr-tc-pnrtag{display:inline-block;margin-top:2px;font-size:10.5px;font-weight:700;color:#4f46e5;background:#eef2ff;border-radius:5px;padding:1px 6px;}
                #cfpReassignOverlay .cfr-tc-date{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:6px 8px;font-size:12.5px;}
                #cfpReassignOverlay .cfr-tc.is-off{opacity:.55;border-left-color:#cbd5e1;background:#f8fafc;}
                #cfpReassignOverlay .cfr-tc.is-bad{border-color:#fca5a5;border-left-color:#dc2626;background:#fff7f7;}
                #cfpReassignOverlay .cfr-tc.is-bad .cfr-tc-date{border-color:#dc2626;color:#b91c1c;}
                #cfpReassignOverlay .cfr-tc-err{grid-column:1/-1;font-size:11.5px;color:#b91c1c;font-weight:600;}
                #cfpReassignOverlay .cfr-tc-err:empty{display:none;}
                #cfpReassignOverlay .cfr-tc-sw{display:flex;align-items:center;gap:5px;margin-top:4px;font-size:10.5px;font-weight:700;color:#0f766e;cursor:pointer;user-select:none;}
                #cfpReassignOverlay .cfr-tc.is-off .cfr-tc-sw{color:#64748b;}
                #cfpReassignOverlay .cfr-tc-tno{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:6px 8px;font-size:12.5px;}
                #cfpReassignOverlay .cfr-tc-date{padding:6px 4px;font-size:12px;}
                #cfpReassignOverlay .cfr-tc-cb{font-size:11px;color:#334155;line-height:1.75;border-left:1px dashed #cbd5e1;padding-left:14px;}
                #cfpReassignOverlay .cfr-tc-cb div{gap:14px !important;}
                #cfpReassignOverlay .cfr-tc-route{margin-bottom:6px;}
                #cfpReassignOverlay .cfr-tc-pnr > span > small{white-space:nowrap;margin-left:6px;}
                #cfpReassignOverlay .cfr-tc-sw{margin-top:8px !important;}
                #cfpReassignOverlay .cfr-tc-cb div{display:flex;justify-content:space-between;gap:6px;white-space:nowrap;}
                #cfpReassignOverlay .cfr-tc-cb .pen{color:#dc2626;}
                #cfpReassignOverlay .cfr-tc-cb .now{color:#dc2626;}
                #cfpReassignOverlay .cfr-tc-cb b{font-weight:400;}
                #cfpReassignOverlay .cfr-tc-amt,#cfpReassignOverlay .cfr-tc-fee{color:#dc2626;}
                #cfpReassignOverlay .cfr-tc-total b{color:#dc2626;}
                #cfpReassignOverlay .cfr-tc-cb .tot{border-top:1px solid #e2e8f0;margin-top:2px;padding-top:2px;color:#0f172a;}
                #cfpReassignOverlay .cfr-tc-lbl{display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#94a3b8;margin-bottom:2px;}
                #cfpReassignOverlay .cfr-tc-money{position:relative;}
                #cfpReassignOverlay .cfr-tc-money span{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-weight:700;font-size:13px;}
                #cfpReassignOverlay .cfr-tc-amt,#cfpReassignOverlay .cfr-tc-fee{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:7px 9px 7px 24px;font-size:13px;font-weight:700;}
                #cfpReassignOverlay .cfr-tc-seg{display:inline-grid;grid-auto-flow:column;grid-auto-columns:minmax(0,1fr);width:260px;gap:4px;border:0;border-radius:0;padding:0;background:transparent;}
                #cfpReassignOverlay .cfr-tc-b{display:inline-flex;align-items:center;justify-content:center;gap:5px;min-height:28px;border:0;background:#e2e8f0;border-radius:7px;padding:4px 6px;font-size:11.5px;font-weight:600;color:#475569;cursor:pointer;white-space:nowrap;transition:background .15s,color .15s,box-shadow .15s;}
                #cfpReassignOverlay .cfr-tc-b i{font-size:10.5px;opacity:.75;}
                #cfpReassignOverlay .cfr-tc-b:hover:not(:disabled):not(.on){background:#cbd5e1;color:#0f172a;}
                #cfpReassignOverlay .cfr-tc-b:focus-visible{outline:2px solid #0d9488;outline-offset:1px;}
                #cfpReassignOverlay .cfr-tc-b.on{font-weight:700;color:#fff;}
                #cfpReassignOverlay .cfr-tc-b.on i{opacity:1;}
                #cfpReassignOverlay .cfr-tc-b.on[data-b="agent"]{background:#4f46e5;color:#fff;box-shadow:0 1px 2px rgba(79,70,229,.35);}
                #cfpReassignOverlay .cfr-tc-b.on[data-b="company"]{background:#b45309;color:#fff;box-shadow:0 1px 2px rgba(180,83,9,.35);}
                #cfpReassignOverlay .cfr-tc-b.on[data-b="split"]{background:#0f766e;color:#fff;}
                #cfpReassignOverlay .cfr-tc-b:disabled{opacity:.4;cursor:not-allowed;}
                #cfpReassignOverlay .cfr-tc-total{display:flex;flex-wrap:wrap;gap:14px;align-items:center;font-size:12px;color:#475569;background:#f0fdfa;border:1px dashed #99f6e4;border-radius:10px;padding:7px 12px;}
                #cfpReassignOverlay .cfr-tc-sum{margin-left:auto;color:#0f766e;}
                @media (max-width:640px){#cfpReassignOverlay .cfr-tc{grid-template-columns:1fr;}}
            </style>` + cfr.held.map(t => {
                const flag = cc => cc ? `<img src="${CFR.flagBase}/${esc(String(cc).toLowerCase())}.png" alt="" onerror="this.remove()">` : '';
                const route = t.from || t.to
                    ? `${flag(t.from_flag)}${esc(t.from || '—')} <i class="fas fa-plane"></i> ${flag(t.to_flag)}${esc(t.to || '—')}`
                    : 'Route not recorded';
                const was = t.date ? new Date(t.date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : null;
                cfrTicketMoney[t.no] = { fare: Number(t.fare) || 0, penalties: (t.penalties || []).map(Number) };
                return `<div class="cfr-tc" data-no="${esc(t.no)}" data-id="${esc(t.id || '')}" data-was="${esc(t.date || '')}" data-route="${esc((t.from || '—') + ' → ' + (t.to || '—'))}">
                <div class="cfr-tc-pnr">
                    <span><span class="cfr-tc-route">${route}</span><span class="cfr-tc-pnrtag">${esc(t.no)}</span>${was ? ` <small style="display:inline;">was ${esc(was)}</small>` : ''}
                        <label class="cfr-tc-sw"><input type="checkbox" class="cfr-tc-on" checked> Reissue this leg</label></span></div>
                <div class="cfr-tc-cb"></div>
                <div><span class="cfr-tc-lbl">New date</span><input type="text" data-dt class="cfr-tc-date" value="${esc(t.date || '')}" title="New travel date for this ticket"></div>
                <div><span class="cfr-tc-lbl">Ticket number</span><input type="text" class="cfr-tc-tno" maxlength="60" placeholder="e.g. 235-1234567890" title="The ticket number for this leg"></div>
                <div><span class="cfr-tc-lbl">Our charge</span><div class="cfr-tc-money"><span>৳</span><input type="number" class="cfr-tc-fee" min="0" step="0.01" placeholder="0 — none"${canAgent ? ' title="Our fee — billed to the agent on the same line, our profit"' : ' disabled title="He has no agent to bill"'}></div></div>
                <div><span class="cfr-tc-lbl">Penalty</span><div class="cfr-tc-money"><span>৳</span><input type="number" class="cfr-tc-amt" min="0" step="0.01" placeholder="0 — none" title="The airline's reissue penalty — owed to the ticket's seller"></div></div>
                <div><span class="cfr-tc-lbl">Who pays</span><div class="cfr-tc-seg" role="group" aria-label="Who pays this ticket's charge">
                    <button type="button" class="cfr-tc-b${canAgent ? ' on' : ''}" data-b="agent"${canAgent ? '' : ' disabled title="He has no agent to bill"'}><i class="fas fa-user-tie"></i>Agent pays</button>
                    <button type="button" class="cfr-tc-b${canAgent ? '' : ' on'}" data-b="company"><i class="fas fa-building"></i>We bear it</button>
                    ${canAgent ? '<button type="button" class="cfr-tc-b" data-b="split">Split</button>' : ''}
                </div>${splitFields('cfr-tc')}</div>
                <div class="cfr-tc-err"></div>
            </div>`; }).join('') + '<div class="cfr-tc-total" id="cfr_tc_total"></div>' : '').toggle(r);
        if (r) { paintTicketTotals(); checkLegOrder(); }
        $('#cfr_charge, #cfr_bearer').toggle(!r && !refundCardsMode());
        $('#cfr_fare_sec').toggle(!isResched());
        applyRefundMode();
        renderFlights();
        applyStay();
        applyOffload();
    }

    // ── Offload / Deported (owner, 2026-10-07) ─────────────────────────
    // Offload: every live ticket and every paid service in one table — cost,
    // what the airline / responsible person refunds (blank = not refunded,
    // ticket kept), the penalty left, who bears it, and our charge on it.
    // Deported: nothing refunded; the return ticket and the airport charges
    // each with a bearer (and an optional payee), plus our deport charge.
    const sideOpts = s => `<option value="company"${s !== 'agent' ? ' selected' : ''}>We bear</option>`
        + (cfr && cfr.agent ? `<option value="agent"${s === 'agent' ? ' selected' : ''}>Agent bears</option><option value="split">Split</option>` : '');
    function buildOffItems() {
        const def = $('#cfr_off_fault').val() || 'company';
        const rows = [
            ...((cfr && cfr.held) || []).map(t => ({ key: 't' + t.id, kind: 't', id: t.id, label: 'Ticket ' + t.no, t,
                sub: [t.from, t.to].filter(Boolean).join(' → '), who: 'Airline', cost: Number(t.fare) || 0 })),
            ...cfrServices.map(v => ({ key: 's' + v.id, kind: 's', id: v.id, label: v.label,
                sub: Number(v.refunded) > 0 ? 'already refunded ' + taka(v.refunded) : '', who: v.provider || '—', cost: Number(v.left) || 0 })),
        ];
        // One card per ticket / service, in the Reschedule card's look (2026-11-02, UI only — same
        // classes and data-* the code reads: .cfr-oi, .cfr-oi-ref, .cfr-oi-pen, .cfr-oi-chg, .cfr-oi-side).
        const flag = cc => cc ? `<img src="${CFR.flagBase}/${esc(String(cc).toLowerCase())}.png" alt="" onerror="this.remove()">` : '';
        const head = r => {
            if (r.kind !== 't') return `<div class="cfr-tk"><span><b>${esc(r.label)}</b>${r.sub ? `<small>${esc(r.sub)}</small>` : ''}</span></div>`;
            const t = r.t, was = t.date ? new Date(t.date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : null;
            return `<div class="cfr-tk"><span><span class="cfr-tk-route">${t.from || t.to ? `${flag(t.from_flag)}${esc(t.from || '—')} <i class="fas fa-plane"></i> ${flag(t.to_flag)}${esc(t.to || '—')}` : 'Route not recorded'}</span>`
                + `<span class="cfr-tk-tag">${esc(t.no)}</span>${was ? ` <small>was ${esc(was)}</small>` : ''}</span></div>`;
        };
        $('#cfr_off_items').html(rows.length ? '<div class="cfr-cards">' + rows.map(r => `<div class="cfr-card cfr-oi-card cfr-oi" data-key="${esc(r.key)}" data-kind="${r.kind}" data-id="${esc(r.id)}" data-cost="${r.cost}" data-label="${esc(r.label)}">
                ${head(r)}
                <div><span class="cfr-lbl">Refund from</span><span class="cfr-val">${esc(r.who)}</span></div>
                <div><span class="cfr-lbl">Cost</span><span class="cfr-val">${taka(r.cost)}</span></div>
                <div><span class="cfr-lbl">Refund</span><input type="number" class="cfr-oi-ref" min="0" max="${r.cost}" step="0.01" placeholder="${r.kind === 't' ? 'not refunded' : 'no refund'}" title="Blank = not refunded${r.kind === 't' ? ' (ticket kept for re-booking)' : ''}"></div>
                <div><span class="cfr-lbl">Penalty</span><span class="cfr-val cfr-oi-pen">—</span></div>
                <div><span class="cfr-lbl">Our charge</span><input type="number" class="cfr-oi-chg" min="0" step="0.01" placeholder="৳ 0"${cfr && cfr.agent ? '' : ' disabled title="No agent to bill"'}></div>
                <div><span class="cfr-lbl">Who pays</span><select class="cfr-oi-side cfr-sw-src">${sideOpts(def)}</select>${splitFields('cfr-oi')}</div>
            </div>`).join('') + '<div class="cfr-card-total" id="cfr_oi_total"></div></div>'
            : '<div class="cfr-empty">He holds no live ticket and no paid service — only our charge can be added (none to enter here).</div>');
        cfrOffBuilt = true;
    }
    function offItems() {
        return $('.cfr-oi').map(function () {
            const c = $(this), cost = Number(c.data('cost')) || 0, v = c.find('.cfr-oi-ref').val();
            const refunded = v !== '' && v != null;
            const refund = refunded ? Math.min(Math.max(Number(v) || 0, 0), cost) : 0;
            // A service with no refund typed is still a loss: its whole cost
            // is the penalty only when it is switched on by a refund figure.
            return { key: String(c.data('key')), kind: String(c.data('kind')), id: c.data('id'), label: String(c.data('label')), cost,
                refunded, refund, penalty: refunded ? Math.round((cost - refund) * 100) / 100 : 0,
                agent_amount: c.find('.cfr-oi-agent').val(), side: c.find('.cfr-oi-side').val() || 'company', charge: Math.max(0, Number(c.find('.cfr-oi-chg').val()) || 0) };
        }).get();
    }
    function offTotals() {
        const it = offItems();
        const agentPen = it.reduce((t, i) => t + agentPart(i.penalty, i.side, i.agent_amount), 0);
        const ourPen = it.reduce((t, i) => t + i.penalty, 0) - agentPen;
        const charge = it.reduce((t, i) => t + i.charge, 0);
        return { it, agentPen, ourPen, charge, refund: it.reduce((t, i) => t + i.refund, 0), agentLine: agentPen + charge };
    }
    function paintOffItems() {
        $('.cfr-oi').each(function () {
            const i = offItems().find(x => x.key === String($(this).data('key')));
            if (i) paintSplit($(this), 'cfr-oi', i.penalty, i.side);
            $(this).find('.cfr-oi-pen').html(!i || !i.refunded ? '<span style="color:#94a3b8;">—</span>'
                : (i.penalty > 0 ? `<b style="color:#dc2626;">${taka(i.penalty)}</b>` : '<span style="color:#15803d;">Full refund</span>'));
        });
        const t = offTotals();
        $('#cfr_oi_total').html(`Refunds <b>${taka(t.refund)}</b> · penalty <b style="color:#dc2626;">${taka(t.agentPen + t.ourPen)}</b>`
            + ` · <b>${esc(cfr.agent || 'agent')}</b>'s ONE line <b style="color:#dc2626;">${taka(t.agentLine)}</b> (his penalty ${taka(t.agentPen)} + our charge ${taka(t.charge)})`
            + ` · we bear <b>${taka(t.ourPen)}</b>`);
        renderSummary();
    }
    function buildDepCosts() {
        const def = $('#cfr_off_fault').val() || 'company';
        const payees = '<option value="">Paid to — choose</option>' + cfrPayees.map(v => `<option value="${v.id}">${esc(v.name)}</option>`).join('');
        $('#cfr_dep_costs').html(`<table class="cfr-oi-t"><thead><tr><th>Cost of sending him home</th><th style="width:130px;">Amount</th><th>Paid to</th><th style="width:280px;">Who pays</th></tr></thead><tbody>`
            + Object.entries(CFR.deportCosts).map(([k, label]) => `<tr class="cfr-dc" data-key="${esc(k)}" data-label="${esc(label)}">
                <td><b>${esc(label)}</b></td>
                <td><input type="number" class="cfr-dc-amt" min="0" step="0.01" placeholder="৳ 0"></td>
                <td><select class="cfr-dc-vendor">${payees}</select></td>
                <td><select class="cfr-dc-side cfr-sw-src"><option value="">— choose —</option>${sideOpts('')}</select>${splitFields('cfr-dc')}</td></tr>`).join('')
            + `<tr><td><b>Our deport charge</b><small>Our fee — billed to the agent, our profit</small></td>
                <td><input type="number" id="cfr_dep_charge" min="0" step="0.01" placeholder="৳ 0"${cfr && cfr.agent ? '' : ' disabled title="No agent to bill"'}></td>
                <td colspan="2" style="color:#64748b;font-size:11px;">Agent pays — Company Ledger (Recovered, 4183)</td></tr>`
            + `</tbody><tfoot><tr><td colspan="4" id="cfr_dc_total"></td></tr></tfoot></table>`);
    }
    function depCosts() {
        return $('.cfr-dc').map(function () {
            const c = $(this);
            return { key: String(c.data('key')), label: String(c.data('label')), amount: Math.max(0, Number(c.find('.cfr-dc-amt').val()) || 0),
                agent_amount: c.find('.cfr-dc-agent').val(), side: c.find('.cfr-dc-side').val() || '', vendor_id: c.find('.cfr-dc-vendor').val() || null,
                vendor: c.find('.cfr-dc-vendor option:selected').val() ? c.find('.cfr-dc-vendor option:selected').text() : null };
        }).get();
    }
    function depTotals() {
        const c = depCosts(), charge = cfr && cfr.agent ? Math.max(0, Number($('#cfr_dep_charge').val()) || 0) : 0;
        const agent = c.reduce((t, x) => t + agentPart(x.amount, x.side, x.agent_amount), 0);
        const ours = c.reduce((t, x) => t + x.amount, 0) - agent;
        return { c, charge, agent, ours, agentLine: agent + charge };
    }
    function paintDep() {
        const t = depTotals();
        $('.cfr-dc').each(function () { const c = t.c.find(x => x.key === String($(this).data('key'))); if (c) paintSplit($(this), 'cfr-dc', c.amount, c.side); });
        $('#cfr_dc_total').html(`Costs <b style="color:#dc2626;">${taka(t.agent + t.ours)}</b> · <b>${esc(cfr.agent || 'agent')}</b>'s ONE line <b style="color:#dc2626;">${taka(t.agentLine)}</b> (costs he bears ${taka(t.agent)} + our charge ${taka(t.charge)}) · we bear <b>${taka(t.ours)}</b>`);
        renderSummary();
    }
    function applyOffload() {
        const on = isOffload();
        $('#cfr_off_sec').toggle(on);
        $('#cfr_bearer').toggle(!on && !perTicketMode() && !refundCardsMode());
        $('#cfr_charge').toggle(!on && !perTicketMode() && !refundCardsMode());
        // an offload / deport has its charge in its own table: no second "charge" here, just the note
        $('#cfr_charge_h').toggle(!on);
        $('#cfr_note').css('grid-column', on ? '1 / -1' : '')
            .attr('placeholder', on ? 'Note (optional) — kept in his Flight changes history' : 'Note (optional)');
        $('#cfr_next_h, #cfr_next_opts').toggle(!on);
        if (!on) return;
        const dep = offEvent() === 'deported';
        if (!cfrOffBuilt) { buildOffItems(); buildDepCosts(); }
        $('#cfr_refund_sec, #cfr_fare_sec').hide();
        $('#cfr_off_items_wrap').toggle(!dep);
        $('#cfr_dep_box').toggle(dep);
        $('#cfr_off_next_h, #cfr_off_next').toggle(!dep);
        $('#cfr_off_fault_row').toggle(dep);
        if (dep) {
            // his package in plain words — the costs above have their own bearers; this is the one amount not in them
            const pkg = Number(cfr && cfr.agent_price) || 0, ag = esc((cfr && cfr.agent) || 'the agent');
            $('#cfr_pkg_amt').html(pkg > 0 ? `<b>${taka(pkg)}</b>` : '');
            $('#cfr_pkg_who').html(cfr && cfr.agent ? `billed to <b>${ag}</b> for him${cfr.from_flight ? ' on ' + esc(cfr.from_flight) : ''}` : 'no agent on his card');
            $('#cfr_off_fault_hint').html(pkg > 0 && cfr.agent
                ? `<b>Agent pays</b> — ${ag} still pays ${taka(pkg)} for him. <b>We bear it</b> — the ${taka(pkg)} is given back to ${ag}.`
                : 'No package was billed for him — choose who bears the deportation; it closes his case.');
        }
        $('#cfr_save').html(dep ? '<i class="fas fa-plane-arrival"></i> Post deportation' : (offNext() === 'close' ? '<i class="fas fa-lock"></i> Offload &amp; close' : '<i class="fas fa-user-slash"></i> Post offload'));
        applyStay();
        renderFlights();
        dep ? paintDep() : paintOffItems();
    }
    $(document).on('change', 'input[name="cfr_off_event"], input[name="cfr_off_next"]', applyOffload);
    // choosing who bears his package answers the message that asked for it
    $(document).on('change', '#cfr_off_fault', () => { if ($('#cfr_off_fault').val()) $('#cfr_error').addClass('hidden').text(''); });
    // The side above is the default for every row not set by hand.
    $(document).on('change', '#cfr_off_fault', function () {
        const v = this.value;
        if (v) $('.cfr-oi-side').not('[data-touched]').val(v === 'agent' && !(cfr && cfr.agent) ? 'company' : v);
        applyOffload();
    });
    $(document).on('change', '.cfr-oi-side', function () { this.setAttribute('data-touched', '1'); isOffload() && (offEvent() === 'deported' ? paintDep() : paintOffItems()); });
    $(document).on('input change', '.cfr-oi-ref, .cfr-oi-chg, .cfr-oi-agent', () => { paintOffItems(); renderFlights(); });
    $(document).on('input change', '.cfr-dc-amt, .cfr-dc-vendor, .cfr-dc-side, .cfr-dc-agent, #cfr_dep_charge', paintDep);

    // ── Refund (Cancel) / New Ticket (owner, 2026-09-30) ─────────────────
    // Per old ticket: its cost, what the airline refunds (default: all of
    // it) and the penalty it keeps (red). One "whose side" for the whole
    // refund, as the Cancel dialog has, so the agent gets ONE line.
    const refundMode = () => !isResched();
    const closing = () => refundMode() && $('input[name="cfr_next"]:checked').val() === 'close';
    function refundRows() {
        return $('.cfr-rf').filter(function () { return $(this).find('.cfr-rf-on').prop('checked'); }).map(function () {
            const c = $(this), fare = Number(c.data('fare')) || 0;
            const v = c.find('.cfr-rf-amt').val();
            const refund = v === '' ? fare : Math.min(Math.max(Number(v) || 0, 0), fare);
            return { id: c.data('id'), no: String(c.data('no')), route: c.data('route') || '', fare, refund, penalty: Math.round((fare - refund) * 100) / 100,
                side: c.find('.cfr-rf-side').val() || '', agentShare: c.find('.cfr-rf-agent').val(),
                agentPenalty: c.find('.cfr-rf-side').val() === 'split' ? Number(c.find('.cfr-rf-agent').val()) : (c.find('.cfr-rf-side').val() === 'agent' ? Math.round((fare - refund) * 100) / 100 : 0) };
        }).get();
    }
    function paintRefunds() {
        syncRefundFees();
        let pen = 0;
        $('.cfr-rf').each(function () {
            const row = refundRows().find(r => String(r.id) === String($(this).data('id')));
            if (!row) { $(this).find('.cfr-rf-pen').html('<span style="color:#64748b;">Not refunded — ticket stays live</span>'); return; }
            pen += row.penalty;
            $(this).find('.cfr-rf-split').toggle(row.side === 'split');
            $(this).find('.cfr-rf-agent').attr('max', row.penalty);
            $(this).find('.cfr-rf-ours').text('We bear ' + taka(row.penalty - row.agentPenalty));
            $(this).find('.cfr-rf-pen').html(row.penalty > 0 ? `<b style="color:#dc2626;">${taka(row.penalty)}</b>` : '<span style="color:#15803d;">Full refund</span>');
        });
        $('#cfr_refund_total').html(`Airline refunds <b>${taka(refundRows().reduce((t, r) => t + r.refund, 0))}</b> · keeps <b style="color:#dc2626;">${taka(pen)}</b>`);
        $('#cfr_fault_row').hide();   // whose side is chosen per leg now
        $('.cfr-rf').each(function () {
            const row = refundRows().find(r => String(r.id) === String($(this).data('id')));
            // Always shown (owner, 2026-10-08) — same as the Reissue row's Who pays.
            $(this).find('.cfr-rf-sidewrap').css('visibility', 'visible');
        });
        renderSummary();
    }
    function applyRefundMode() {
        const on = !isResched();
        $('#cfr_refund_sec').toggle(on);
        if (!on) return;
        // Closing is for someone still on a flight; from Cancelled he is
        // already off it.
        $('#cfr_next_close').toggle(!!(cfr && cfr.on_flight));
        if (!(cfr && cfr.on_flight)) $('input[name="cfr_next"][value="fly"]').prop('checked', true);
        const held = (cfr && cfr.held) || [];
        const flag = cc => cc ? `<img src="${CFR.flagBase}/${esc(String(cc).toLowerCase())}.png" alt="" style="width:18px;height:12px;object-fit:cover;border-radius:2px;vertical-align:-1px;" onerror="this.remove()">` : '';
        $('#cfr_refund_cards').html(held.length ? held.map(t => {
            const route = t.from || t.to ? `${flag(t.from_flag)} ${esc(t.from || '—')} <i class="fas fa-plane" style="color:#0f766e;font-size:11px;"></i> ${flag(t.to_flag)} ${esc(t.to || '—')}` : '';
            return `<div class="cfr-rf" data-id="${esc(t.id)}" data-no="${esc(t.no)}" data-fare="${Number(t.fare) || 0}" data-route="${esc((t.from || '') + (t.to ? ' → ' + t.to : ''))}"
                    style="display:grid;grid-template-columns:minmax(0,1fr) 85px 115px 95px 100px 260px;gap:16px;align-items:center;border:1px solid #e2e8f0;border-left:4px solid #0f766e;border-radius:10px;padding:12px 16px;background:#fff;transition:opacity .15s;">
                <div style="min-width:0;"><div style="font-size:13px;display:flex;align-items:center;gap:5px;white-space:nowrap;margin-bottom:6px;">${route}</div>
                    <span style="display:inline-block;font-size:10.5px;font-weight:700;color:#4f46e5;background:#eef2ff;border-radius:5px;padding:1px 6px;">${esc(t.no)}</span>${t.date ? ` <small style="font-size:10.5px;color:#64748b;white-space:nowrap;margin-left:6px;">was ${esc(new Date(t.date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }))}</small>` : ''}
                    <label style="display:flex;align-items:center;gap:5px;margin-top:8px;font-size:10.5px;font-weight:700;color:#0f766e;cursor:pointer;user-select:none;"><input type="checkbox" class="cfr-rf-on"${isOffload() ? '' : ' checked'}> Refund this leg</label></div>
                <div><span style="display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;color:#94a3b8;">Ticket cost</span><span style="font-size:12.5px;">${taka(t.fare)}</span></div>
                <div><span style="display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;color:#94a3b8;">Airline refund</span>
                    <input type="number" class="cfr-rf-amt" min="0" max="${Number(t.fare) || 0}" step="0.01" value="${Number(t.fare) || 0}" style="width:100%;box-sizing:border-box;border:1px solid #bbf7d0;border-radius:8px;padding:6px 8px;font-size:12.5px;"></div>
                <div><span style="display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;color:#94a3b8;">Penalty (kept)</span><span class="cfr-rf-pen"></span></div>
                <div><span style="display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;color:#94a3b8;">Our charge</span>
                    <input type="number" class="cfr-rf-fee" min="0" step="0.01" placeholder="৳ 0"${cfr.agent ? ' title="Our fee — billed to the agent on the same line, our profit"' : ' disabled title="He has no agent to bill"'} style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:6px 8px;font-size:12.5px;font-weight:700;color:#dc2626;"></div>
                <div class="cfr-rf-sidewrap"><span class="cfr-tc-lbl" style="display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#94a3b8;margin-bottom:2px;">Who pays</span>
                    <select class="cfr-rf-side cfr-sw-src">
                        <option value="">Choose</option><option value="agent"${cfr.agent ? '' : ' disabled'}>Agent pays</option>
                        <option value="company"${cfr.agent ? '' : ' selected'}>We bear it</option>
                        ${cfr.agent ? '<option value="split">Split</option>' : ''}
                    </select><div class="cfr-rf-split" style="display:none;margin-top:6px;font-size:11px;">
                        <label>Agent bears <input type="number" class="cfr-rf-agent" min="0" step="0.01" style="width:100%;box-sizing:border-box;height:30px;border:1px solid #cbd5e1;border-radius:6px;color:#dc2626;"></label>
                        <div class="cfr-rf-ours" style="margin-top:4px;color:#b45309;"></div>
                    </div></div>
            </div>`;
        }).join('') + '<div id="cfr_refund_total" style="font-size:12px;color:#475569;background:#fff7f7;border:1px dashed #fecaca;border-radius:10px;padding:7px 12px;"></div>'
            : `<div class="cfr-empty">He holds no live ticket${(cfr && (cfr.cancelled || []).length) ? ' — ' + esc(cfr.cancelled.join(', ')) + ' already cancelled' : ''}. Nothing to refund.</div>`);
        applyNext();
        if (isOffload()) $('.cfr-rf-on').trigger('change');
        paintRefunds();
    }
    function applyNext() {
        if (isOffload()) { applyStay(); renderSummary(); return; }
        const close = closing();
        applyStay();
        $('#cfr_fare_sec').toggle(refundMode() && !close);
        $('#cfr_save').html(close ? '<i class="fas fa-lock"></i> Close case' : '<i class="fas fa-plane-circle-check"></i> Re-assign');
        renderSummary();
    }
    $(document).on('input', '.cfr-rf-amt, .cfr-rf-fee', paintRefunds);
    // Refund one leg or both — same switch as "Reissue this leg".
    $(document).on('change', '.cfr-rf-on', function () {
        const card = $(this).closest('.cfr-rf'), on = this.checked;
        card.css({ opacity: on ? '' : '.55', background: on ? '#fff' : '#f8fafc', borderLeftColor: on ? '#0f766e' : '#cbd5e1' });
        card.find('.cfr-rf-amt, .cfr-rf-side, .cfr-rf-agent').prop('disabled', !on);
        card.find('.cfr-rf-fee').prop('disabled', !on || !(cfr && cfr.agent));
        window.cfrSwPaint && window.cfrSwPaint();
        paintRefunds();
        applyStay();
    });
    $(document).on('change', 'input[name="cfr_next"]', applyNext);
    $(document).on('change', '#cfr_fault, .cfr-rf-side', () => paintRefunds());
    $(document).on('input', '.cfr-rf-agent', () => paintRefunds());
    $(document).on('change', 'input[name="cfr_kind"]', applyKind);
    $(document).on('input', '.cfr-tc-amt, .cfr-tc-fee, .cfr-tc-agent', () => { paintTicketTotals(); renderSummary(); });
    $(document).on('input change', '.cfr-tc-date', checkLegOrder);
    // The new flight's date and leg 1's New date mirror each other (owner,
    // 2026-11-01) — leg 1 is the leg the flight follows. Leg 1 switched off
    // keeps its old date, so it is left alone.
    $(document).on('input change', '#cfr_nf_dep', function () {
        const leg1 = $('#cfpReassignOverlay .cfr-tc').first().find('.cfr-tc-date');
        if (!leg1.length || leg1.prop('disabled')) return;
        leg1.val($(this).val());
        checkLegOrder();
    });
    $(document).on('input change', '#cfpReassignOverlay .cfr-tc-date', function () {
        if (!$(this).closest('.cfr-tc').is($('#cfpReassignOverlay .cfr-tc').first())) return;
        $('#cfr_nf_dep').val($(this).val());
        renderSummary();
    });
    // Switched off: that leg keeps its date and carries no charge.
    $(document).on('change', '.cfr-tc-on', function () {
        const card = $(this).closest('.cfr-tc');
        const on = this.checked;
        card.toggleClass('is-off', !on);
        card.find('.cfr-tc-date, .cfr-tc-tno, .cfr-tc-amt, .cfr-tc-fee, .cfr-tc-b, .cfr-tc-agent').prop('disabled', !on);
        if (!on) { card.find('.cfr-tc-date').val(card.data('was') || ''); card.find('.cfr-tc-amt, .cfr-tc-fee, .cfr-tc-tno').val(''); }
        else if (!cfr.agent) card.find('.cfr-tc-b[data-b="agent"], .cfr-tc-fee').prop('disabled', true);
        checkLegOrder(); paintTicketTotals(); applyStay(); renderSummary();
    });
    $(document).on('click', '.cfr-tc-b', function () {
        if (this.disabled) return;
        $(this).addClass('on').siblings().removeClass('on');
        paintTicketTotals(); renderSummary();
    });

    window.cfpCloseReassign = function () {
        $('#cfpReassignOverlay').addClass('hidden');
    };

    function renderFlights() {
        const q = ($('#cfr_search').val() || '').trim().toLowerCase();
        const chosen = $('input[name="cfr_flight"]:checked').val();
        const sameRoute = (isResched() || (isOffload() && !offItems().some(i => i.kind === 't' && i.refunded))) && cfr && (cfrSourceRouteKey || cfr.route_id);
        const list = cfrFlights.filter(f => (!sameRoute || (cfrSourceRouteKey
                ? String(f.route_key || '') === String(cfrSourceRouteKey)
                : String(f.ticket_id) === String(cfr.route_id)))
            && (!q || [f.flight_number, f.airline, f.route, f.departure_label, f.status].join(' ').toLowerCase().includes(q)));
        if (sameRoute) $('#cfr_nf_route').val(String(cfr.route_id)).prop('disabled', true);
        else $('#cfr_nf_route').prop('disabled', false);
        const box = $('#cfr_flights').empty();
        // Always first: a brand-new flight, created on Re-assign.
        box.append(`<label class="cfr-flight cfr-new">
            <input type="radio" name="cfr_flight" value="new"${chosen === 'new' ? ' checked' : ''}>
            <span style="min-width:0;"><b><i class="fas fa-plus"></i> New flight</b> <small>Create an open flight now and put him on it</small></span>
            <em>next GF no.</em>
        </label>`);
        if (!list.length) {
            box.append(`<div class="cfr-empty">${sameRoute ? 'No other flight on his route — use New flight.' : (cfrFlights.length ? 'No flight matches that.' : 'No other flight — use New flight.')}</div>`);
            renderSummary();
            return;
        }
        list.forEach(f => {
            const meta = [f.airline, f.route, f.legs > 1 ? f.legs + ' tickets' : null].filter(Boolean).join(' · ');
            box.append(`<label class="cfr-flight">
                <input type="radio" name="cfr_flight" value="${f.id}"${String(f.id) === String(chosen) ? ' checked' : ''}>
                <span style="min-width:0;"><b>${esc(f.flight_number)}</b>${['open', 'boarding'].includes(f.status) ? '' : ` <span style="font-size:10px;font-weight:700;text-transform:uppercase;color:#92400e;background:#fef3c7;border-radius:6px;padding:1px 6px;">${esc(f.status)}</span>`} <small>${esc(meta || 'Route pending')}</small></span>
                <em>${esc(f.departure_label || 'Date pending')}<br>${f.passengers} pax</em>
            </label>`);
        });
        renderSummary();
    }

    // What Save is about to do — the charge is a real ledger post, so it
    // must never be a surprise afterwards.
    // Who-pays switch: partials.who-pays-switch (any select.cfr-sw-src).
    function renderSummary() {
        window.cfrSwPaint && window.cfrSwPaint();
        if (!cfr) return;
        if (window.cfswSummary && window.cfswSummary()) return;
        const kind = $('input[name="cfr_kind"]:checked').val();
        const picked = $('input[name="cfr_flight"]:checked').val();
        $('#cfr_nf').toggleClass('is-open', picked === 'new' && !addFlightAfterRefund());
        const f = picked === 'new'
            ? { flight_number: 'a new flight', departure_label: $('#cfr_nf_dep').val() ? $('#cfr_nf_dep').val().replace('T', ' ') : '' }
            : cfrFlights.find(x => String(x.id) === String(picked));
        if (isOffload()) { offloadSummary(f); return; }
        const charge = chargeTotal();
        const fare = isResched() ? 0 : (Number($('#cfr_fare').val()) || 0);
        const fareBearer = $('#cfr_fare_bearer').val();
        const bearer = $('#cfr_bearer').val();
        const held = (cfr.held || []).map(t => t.no).join(', ');
        const where = f ? `<b>${esc(f.flight_number)}</b>` : 'the new flight';
        const lines = [
            f ? `He joins <b>${esc(f.flight_number)}</b>${f.departure_label ? ' · ' + esc(f.departure_label) : ''}.` : 'Choose the flight he goes on.',
            (cfr.agent && Number(cfr.agent_price) > 0) ? (cfr.on_flight ? (refundMode() ? `His package <b>${taka(cfr.agent_price)}</b> is reversed on <b>${esc(cfr.agent)}</b>'s statement with the refund, and charged again on the new flight.` : `His package <b>${taka(cfr.agent_price)}</b> on <b>${esc(cfr.agent)}</b> moves with him — no new charge.`) : `His package <b>${taka(cfr.agent_price)}</b> is charged to <b>${esc(cfr.agent)}</b> again — a new line; the earlier charge and its reversal stay.`) : null,
            kind === 'reschedule'
                ? `PNR <b>${esc(held)}</b> goes with him — same route, same legs, new flight and date.`
                : `${held ? `His old ticket <b>${esc(held)}</b> is cancelled (full refund posted; it stays in Ticket Orders). ` : ((cfr.cancelled || []).length ? `His old ticket <b>${esc(cfr.cancelled.join(', '))}</b> was already cancelled. ` : '')}Issue the new ticket from ${where}'s passenger card.`,
            perTicketMode()
                ? (() => {
                    const rows = ticketCharges().filter(c => c.amount > 0 || c.fee > 0);
                    if (!rows.length) return 'No charge for the change.';
                    const a = rows.reduce((t, c) => t + agentPart(c.amount, c.bearer, c.agent_amount), 0) + rows.reduce((t, c) => t + c.fee, 0);
                    const w = rows.reduce((t, c) => t + c.amount - agentPart(c.amount, c.bearer, c.agent_amount), 0);
                    return [a > 0 ? `<b style="color:#dc2626;">${taka(a)}</b> is invoiced to <b>${esc(cfr.agent || '')}</b>` : null,
                        w > 0 ? `<b style="color:#dc2626;">${taka(w)}</b> is recorded as ours — nobody is billed` : null].filter(Boolean).join(' · ') + '.';
                })()
            : charge > 0
                ? (bearer === 'agent'
                    ? (cfr.agent ? `Change charge <b style="color:#dc2626;">${taka(charge)}</b> is invoiced to <b>${esc(cfr.agent)}</b>.` : `<span style="color:#dc2626;">He has no agent to bill — choose "We bear it".</span>`)
                    : `Change charge <b style="color:#dc2626;">${taka(charge)}</b> is recorded as ours — nobody is billed.`)
                : 'No charge for the change.',
            fare > 0
                ? (fareBearer === 'agent'
                    ? `Fare difference <b>${taka(fare)}</b> is billed to <b>${esc(cfr.agent || '')}</b> and shows on the Company Ledger as recovered.`
                    : `Fare difference <b>${taka(fare)}</b> goes to the <b>Company Ledger</b> as borne by us.`)
                : (kind === 'reissue'
                    ? `Fare difference: worked out when his new ticket is linked (new − old cost), then ${fareBearer === 'agent' ? 'billed to the agent' : 'borne by us'}.`
                    : null),
            'His stage restarts from what his details prove on the new flight.',
        ].filter(Boolean);
        if (refundMode()) {
            const rr = refundRows(), pen = rr.reduce((t, r) => t + r.penalty, 0);
            const agentPen = rr.reduce((t, r) => t + r.agentPenalty, 0);
            const ourPen = pen - agentPen;
            const unset = rr.some(r => r.penalty > 0 && !r.side);
            const refundLines = rr.length ? [
                `Old ticket(s) ${esc(rr.map(r => r.no).join(', '))} cancelled in Ticketing — airline refund <b>${taka(rr.reduce((t, r) => t + r.refund, 0))}</b> posted to the vendor / portal.`,
                pen > 0 ? `Penalty kept <b style="color:#dc2626;">${taka(pen)}</b> → Company Ledger` + [agentPen > 0 ? ` · <b style="color:#dc2626;">${taka(agentPen)}</b> billed to <b>${esc(cfr.agent || 'the agent')}</b>` : '', ourPen > 0 ? ` · <b style="color:#dc2626;">${taka(ourPen)}</b> we bear` : ''].join('')
                    + (unset ? ' — <span style="color:#dc2626;">choose whose side on each leg</span>' : '') + '.' : 'Full refund — no penalty.',
            ] : [];
            const fault = agentPen > 0 ? 'agent' : 'company';
            if (closing()) {
                const pkg = Number(cfr.agent_price) || 0, back = agentPen;
                const closeLines = [
                    `<b>${esc(cfr.name)}</b> leaves <b>${esc(cfr.from_flight || 'his flight')}</b> — case <b>Closed</b>, he will not fly.`,
                    ...refundLines,
                    cfr.agent && pkg > 0 ? `Package <b>${taka(pkg)}</b> reversed on ${esc(cfr.agent)}'s statement${back > 0 ? `, penalty <b style="color:#dc2626;">${taka(back)}</b> billed back` : ''} → net credit to the agent <b>${taka(pkg - back - chargeTotal())}</b>.` : null,
                    chargeTotal() > 0 ? `Our cancellation fee <b style="color:#dc2626;">${taka(chargeTotal())}</b> billed to the agent on the same line.` : null,
                ].filter(Boolean);
                $('#cfr_summary').html('<b>On Close case:</b><ul style="margin:4px 0 0;padding:0;">' + closeLines.map(l => `<li>${l}</li>`).join('') + '</ul>');
                return;
            }
            const at = lines.findIndex(l => String(l).includes('Issue the new ticket'));
            if (at >= 0) lines.splice(at, 1, ...refundLines); else lines.push(...refundLines);
        }
        $('#cfr_summary').html('<b>On Re-assign:</b><ul style="margin:4px 0 0;padding:0;">' + lines.map(l => `<li>${l}</li>`).join('') + '</ul>');
    }

    // Whose side: Deported asks it outright (it decides the package); an
    // offload has no top control (owner, 2026-10-08) — agent if any row
    // is borne by the agent, otherwise ours. Rows post their own side.
    function offFault() {
        if (offEvent() === 'deported') return $('#cfr_off_fault').val();
        return offTotals().agentPen > 0 ? 'agent' : 'company';
    }
    function offloadLines(f) {
        const ev = offEvent(), dep = ev === 'deported', next = offNext(), fault = offFault();
        const pkg = Number(cfr.agent_price) || 0;
        const reversed = (dep && fault === 'company') || next === 'close';
        const lines = [
            `<b>${esc(cfr.name)}</b> — <b>${esc(CFR.offloadEvents[ev])}</b> on <b>${esc(cfr.from_flight || 'his flight')}</b>${dep && $('#cfr_dep_from').val() ? ' · from ' + esc($('#cfr_dep_from').val()) : ''}.`,
            fault ? null : `<span style="color:#dc2626;">${dep ? 'His package: choose who bears it.' : 'Choose whose side caused it.'}</span>`,
            cfr.agent && pkg > 0 ? (fault === 'split' ? `Package split: agent <b>${taka($('#cfr_pkg_agent').val())}</b>, we bear <b>${taka(pkg - Number($('#cfr_pkg_agent').val()))}</b>; the company share is credited to the agent.` : reversed
                ? `Package <b>${taka(pkg)}</b> reversed on <b>${esc(cfr.agent)}</b>'s statement (a credit line — the charge stays beside it).`
                : `Package <b>${taka(pkg)}</b> on <b>${esc(cfr.agent)}</b> stands.`) : null,
        ];
        if (dep) {
            const t = depTotals();
            t.c.filter(x => x.amount > 0).forEach(x => lines.push(`${esc(x.label)} <b style="color:#dc2626;">${taka(x.amount)}</b> — ${sideText(x.side, x.amount, x.agent_amount)}${x.vendor ? ', owed to ' + esc(x.vendor) : ''} (Company Ledger, Dr 5171 / Cr Payable).`));
            lines.push(t.agentLine > 0 ? `ONE line on <b>${esc(cfr.agent)}</b>'s statement: <b style="color:#dc2626;">${taka(t.agentLine)}</b> (costs ${taka(t.agent)} + our deport charge ${taka(t.charge)}).` : 'Nothing billed to the agent beyond the package.');
            lines.push('Nothing is refunded — he flew. He stays on this flight flagged Deported; the case closes.');
        } else {
            const t = offTotals();
            t.it.filter(i => i.refunded).forEach(i => lines.push(`${esc(i.label)}: refund <b>${taka(i.refund)}</b>${i.penalty > 0 ? `, penalty <b style="color:#dc2626;">${taka(i.penalty)}</b> — ${sideText(i.side, i.penalty, i.agent_amount)}` : ' — full refund'}${i.kind === 't' ? ' (ticket cancelled in Ticketing)' : ' (posted to his ledger)'}.`));
            const kept = t.it.filter(i => i.kind === 't' && !i.refunded);
            if (kept.length) lines.push(`Kept, not refunded: ${esc(kept.map(i => i.label).join(', '))}.`);
            lines.push(t.agentLine > 0 ? `ONE line on <b>${esc(cfr.agent)}</b>'s statement: <b style="color:#dc2626;">${taka(t.agentLine)}</b> (penalty ${taka(t.agentPen)} + our charge ${taka(t.charge)} — our charge is profit).` : 'Nothing billed to the agent beyond the package.');
            if (t.ourPen > 0) lines.push(`We bear <b style="color:#dc2626;">${taka(t.ourPen)}</b> — Company Ledger.`);
            lines.push(next === 'close' ? 'He leaves the flight and shows under Cancelled as <b>Closed — will not fly</b>.'
                : next === 'hold' ? 'He stays on this flight flagged Offloaded — Re-assign him later.'
                : (f ? `Then he is re-booked onto <b>${esc(f.flight_number)}</b>${f.departure_label ? ' · ' + esc(f.departure_label) : ''}${t.it.some(i => i.kind === 't' && i.refunded) ? ' — issue his new ticket on that flight\'s card' : ' on the same ticket'}.` : '<span style="color:#dc2626;">Choose the flight he is re-booked on.</span>'));
        }
        lines.push('Nothing is deleted — every line is added, and the event goes into his Flight changes history.');
        return lines.filter(Boolean);
    }
    function offloadSummary(f) {
        $('#cfr_summary').html('<b>On ' + esc(offEvent() === 'deported' ? 'Deported' : 'Offload') + ':</b><ul style="margin:4px 0 0;padding:0;">' + offloadLines(f).map(l => `<li>${l}</li>`).join('') + '</ul>');
    }

    function saveOffload() {
        const err = $('#cfr_error');
        const ev = offEvent(), next = offNext(), fault = offFault();
        if (!fault) { err.removeClass('hidden').text(ev === 'deported' ? 'His package: choose who bears it — Agent pays or We bear it.' : 'Choose whose side caused it.'); return; }
        if (ev === 'deported' && invalidSplit(Number(cfr.agent_price) || 0, fault, $('#cfr_pkg_agent').val())) { err.removeClass('hidden').text('Enter a valid agent share for his package.'); return; }
        const note = $('#cfr_note').val().trim() || null;
        let data, followUp = null;
        if (ev === 'deported') {
            const t = depTotals(), costs = {};
            // Owner, 2026-10-07: every cost names who it is paid to and who bears it.
            const missing = t.c.find(x => x.amount > 0 && (!x.vendor_id || !x.side));
            if (missing) { err.removeClass('hidden').text(`${missing.label}: choose ${!missing.vendor_id ? 'who it is paid to' : 'who bears it'}.`); return; }
            if (t.c.some(x => x.amount > 0 && invalidSplit(x.amount, x.side, x.agent_amount))) { err.removeClass('hidden').text('Enter a valid agent share for each split deportation cost.'); return; }
            t.c.forEach(x => { costs[x.key] = { amount: x.amount, side: x.side, vendor_id: x.vendor_id, agent_amount: x.agent_amount };  });
            data = { event: ev, fault, package_agent_amount: fault === 'split' ? $('#cfr_pkg_agent').val() : null, next: 'hold', deport_from: $('#cfr_dep_from').val().trim() || null, deport_costs: costs,
                charge: t.charge > 0 ? t.charge : null, note };
        } else {
            const it = offItems();
            if (it.some(i => i.refunded && (Number($(`.cfr-oi[data-key="${i.key}"] .cfr-oi-ref`).val()) > i.cost))) { err.removeClass('hidden').text('A refund cannot be more than the cost.'); return; }
            const tk = it.filter(i => i.kind === 't' && i.refunded), sv = it.filter(i => i.kind === 's' && i.refunded);
            const refunds = {}, faults = {}, services = {}, charges = {}, penalty_agent_amounts = {};
            if (it.some(i => i.refunded && invalidSplit(i.penalty, i.side, i.agent_amount))) { err.removeClass('hidden').text('Enter a valid agent share for each split penalty.'); return; }
            tk.forEach(i => { refunds[i.id] = i.refund; faults[i.id] = i.side; if (i.side === 'split') penalty_agent_amounts[i.id] = i.agent_amount; });
            sv.forEach(i => { services[i.id] = { refund: i.refund, side: i.side, agent_amount: i.agent_amount }; });
            it.filter(i => i.charge > 0).forEach(i => { charges[i.key] = i.charge; });
            data = { event: ev, fault, next, only_tickets: tk.map(i => i.id).join(','), refunds, faults, services, charges, penalty_agent_amounts, note };
            if (next === 'rebook') {
                const flightId = $('input[name="cfr_flight"]:checked').val();
                if (!flightId) { err.removeClass('hidden').text('Choose the flight he is re-booked on.'); return; }
                followUp = { url: CFR.reassign.replace('__ID__', cfr.id), data: {
                    kind: tk.length ? 'reissue' : 'reschedule', contract_flight_id: flightId, stay_on_flight: 0, only_tickets: '',
                    fare_bearer: fault === 'agent' && cfr.agent ? 'agent' : 'company',
                    new_flight: flightId === 'new' ? { ticket_id: $('#cfr_nf_route').val() || null, airline_flight_no: $('#cfr_nf_no').val().trim() || null, departure_at: $('#cfr_nf_dep').val() || null } : null,
                    note: 'After ' + CFR.offloadEvents[ev].toLowerCase() + ' on ' + (cfr.from_flight || 'his flight') + (note ? ' · ' + note : '') } };
            }
        }
        cfrShowConfirm(data, [], CFR.offload.replace('__ID__', cfr.id), 'PATCH', [], 'offload', followUp);
    }

    // "New flight" (owner, 2026-09-28): close Re-assign and open the real
    // Add Flight modal, with him already on it. Picking his passport on the
    // builder card is the re-booking door the builder already has
    // (pbCheckRebook → his previous card's details + the Re-booking box), so
    // the flight is saved with the full form, not a stub. His agent becomes
    // the batch agent and the route he was on is pre-selected.
    // Refund (Cancel) / New Ticket onto a NEW flight, for someone still on a
    // flight (owner, 2026-09-30): the builder's re-booking door only takes a
    // passenger under Cancelled, so his old tickets are cancelled first (with
    // the airline's refund, confirmed on the A4 sheet) and THEN Add Flight
    // opens pre-filled — the same door as from Cancelled.
    const addFlightAfterRefund = () => !!(cfr && cfr.on_flight && refundMode() && $('input[name="cfr_flight"]:checked').val() === 'new' && $('.create-btn').length);

    $(document).on('change', 'input[name="cfr_flight"][value="new"]', function () {
        const p = cfr;
        const opener = $('.create-btn').first();
        if (!p || !opener.length) return; // no Add Flight on this page: keep the inline form
        // Still on a flight: reschedule uses the inline new-flight form; a
        // refund goes through Re-assign's confirm first (addFlightAfterRefund).
        if (p.on_flight) {
            renderSummary();
            // Refund → new flight: go straight on — the refund is confirmed on
            // the A4 sheet, then Add Flight opens (no second click on Re-assign).
            if (refundMode()) cfpSaveReassign();
            return;
        }
        cfrOpenAddFlight(p);
    });

    function cfrOpenAddFlight(p) {
        const opener = $('.create-btn').first();
        if (!opener.length) return;
        cfpCloseReassign();
        opener.trigger('click');
        setTimeout(function () {
            if (p.agent_id) {
                const ag = $('#create_agent_ids');
                if (ag.length && ag.find('option[value="' + p.agent_id + '"]').length) ag.val([String(p.agent_id)]).trigger('change');
            }
            if (p.route_id) {
                const rt = $('#create_ticket_id');
                if (rt.find('option[value="' + p.route_id + '"]').length) rt.val(String(p.route_id)).trigger('change');
            }
            const holder = $('#createModal .pb-card').first().find('.pb-holder');
            if (holder.length && p.holder_id) {
                if (!holder.find('option[value="' + p.holder_id + '"]').length) {
                    holder.append(new Option(p.holder_label || p.name || ('#' + p.holder_id), p.holder_id));
                }
                holder.val(String(p.holder_id)).trigger('change');
            }
        }, 50);
    }

    $(document).on('change', 'input[name="cfr_kind"], input[name="cfr_flight"], #cfr_bearer', renderSummary);
    // Refund → an existing flight: straight to the confirm sheet, like + New flight.
    $(document).on('change', 'input[name="cfr_flight"]:not([value="new"])', function () {
        if (cfr && cfr.on_flight && refundMode()) cfpSaveReassign();
    });
    $(document).on('input', '#cfr_charge, #cfr_fare, #cfr_nf_dep', renderSummary);
    $(document).on('input change', '#cfr_off_fault, #cfr_pkg_agent', function () {
        const split = $('#cfr_off_fault').val() === 'split';
        $('#cfr_pkg_split').toggle(split);
        $('#cfr_pkg_agent').attr('max', Number(cfr?.agent_price) || 0);
        $('#cfr_pkg_ours').text('We bear ' + taka(Math.max(0, (Number(cfr?.agent_price) || 0) - Number($('#cfr_pkg_agent').val()))));
        renderSummary();
    });
    $(document).on('input change', '#cfr_fare_split, #cfr_fare_agent, #cfr_fare', function () {
        $('#cfr_fare_split_fields').toggle($('#cfr_fare_split').prop('checked'));
        $('#cfr_fare_ours').text('We bear ' + taka(Math.max(0, Number($('#cfr_fare').val()) - Number($('#cfr_fare_agent').val()))));
    });
    $(document).on('change', '#cfr_fare_bearer', renderSummary);
    $(document).on('input', '#cfr_search', renderFlights);

    // ── Confirmation sheet (owner, 2026-09-30) ─────────────────────────
    // Re-assign first shows everything on one A4 page — old against new,
    // every ticket, the money and exactly what will be posted — and only
    // "Confirm & post" sends it. Same look as the eye's passenger record.
    let cfrConfirmEl = null;
    function cfrConfirmOverlay() {
        if (cfrConfirmEl) return cfrConfirmEl;
        cfrConfirmEl = document.createElement('div');
        cfrConfirmEl.className = 'fdl-sheet-overlay';
        cfrConfirmEl.style.zIndex = '9600';
        cfrConfirmEl.hidden = true;
        cfrConfirmEl.innerHTML = `<div class="fdl-sheet-bar">
                <strong>Confirm re-assign</strong>
                <button type="button" class="fdl-sheet-btn" data-cfr-back><i class="fas fa-arrow-left"></i> Back to edit</button>
                <button type="button" class="fdl-sheet-btn" data-cfr-print><i class="fas fa-print"></i> Print</button>
                <button type="button" class="fdl-sheet-btn cfr-go-btn" data-cfr-go><i class="fas fa-check"></i> Confirm &amp; post</button>
            </div><style>
                /* The one button that writes — bright, raised, gently breathing. */
                .cfr-go-btn{background:linear-gradient(135deg,#22c55e,#16a34a) !important;border:0 !important;color:#fff !important;
                    font-size:.9rem !important;font-weight:800;padding:9px 20px !important;border-radius:999px !important;
                    box-shadow:0 8px 22px rgba(34,197,94,.55),0 0 0 3px rgba(255,255,255,.25);transform:translateY(-2px);
                    animation:cfrGoPulse 1.8s ease-in-out infinite;transition:transform .15s ease,box-shadow .15s ease;}
                .cfr-go-btn:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(34,197,94,.7),0 0 0 4px rgba(255,255,255,.35);}
                .cfr-go-btn:disabled{animation:none;opacity:.75;cursor:wait;}
                @keyframes cfrGoPulse{0%,100%{box-shadow:0 8px 22px rgba(34,197,94,.55),0 0 0 3px rgba(255,255,255,.25);}50%{box-shadow:0 10px 26px rgba(34,197,94,.75),0 0 0 7px rgba(34,197,94,.18);}}
                @media (prefers-reduced-motion: reduce){.cfr-go-btn{animation:none;}}
            </style><div class="fdl-sheet is-flow" role="document"></div>`;
        document.body.appendChild(cfrConfirmEl);
        cfrConfirmEl.addEventListener('click', e => {
            if (e.target.closest('[data-cfr-back]')) cfrConfirmEl.hidden = true;
            if (e.target.closest('[data-cfr-print]')) {
                document.body.classList.add('fdl-printing');
                window.print();
                setTimeout(() => document.body.classList.remove('fdl-printing'), 1500);
            }
        });
        return cfrConfirmEl;
    }

    function cfrConfirmHtml(data, perTicket, refundRowsList, isClose, followUp) {
        refundRowsList = refundRowsList || [];
        const refundTable = refundRowsList.length ? `<h2>Old ticket(s) — cancelled with the airline's refund</h2>
            <table><thead><tr><th>PNR</th><th>Leg</th><th class="num">Ticket cost</th><th class="num">Airline refund</th><th class="num">Penalty kept</th><th>Whose side</th></tr></thead><tbody>
            ${refundRowsList.map(r => `<tr><td><b>${esc(r.no)}</b></td><td>${esc(r.route || '—')}</td><td class="num">${taka(r.fare)}</td><td class="num">${taka(r.refund)}</td>
                <td class="num">${r.penalty > 0 ? `<b class="bad">${taka(r.penalty)}</b>` : '—'}</td><td>${r.penalty > 0 ? (r.side === 'split' ? 'Agent ' + taka(r.agentPenalty) + ' / We bear ' + taka(r.penalty - r.agentPenalty) : (r.side === 'agent' ? 'Agent side' : 'Our side')) : '—'}</td></tr>`).join('')}
            </tbody></table>` : '';
        if (isClose === 'offload') {
            const dep = data.event === 'deported';
            const f = followUp ? (followUp.data.contract_flight_id === 'new' ? { flight_number: 'a new flight' }
                : cfrFlights.find(x => String(x.id) === String(followUp.data.contract_flight_id))) : null;
            const pkg = Number(cfr.agent_price) || 0;
            const reversed = (dep && data.fault === 'company') || data.next === 'close';
            const T = dep ? depTotals() : offTotals();
            const table = dep
                ? `<h2>Deportation${data.deport_from ? ' — from ' + esc(data.deport_from) : ''}</h2><table><thead><tr><th>Cost</th><th>Paid to</th><th class="num">Amount</th><th>Who bears</th></tr></thead><tbody>
                    ${T.c.filter(x => x.amount > 0).map(x => `<tr><td>${esc(x.label)}</td><td>${esc(x.vendor || '—')}</td><td class="num"><b class="bad">${taka(x.amount)}</b></td><td>${sideText(x.side, x.amount, x.agent_amount)}</td></tr>`).join('') || '<tr><td colspan="4" class="muted">No costs entered.</td></tr>'}
                    ${T.charge > 0 ? `<tr><td>Our deport charge</td><td>—</td><td class="num">${taka(T.charge)}</td><td>Agent</td></tr>` : ''}</tbody></table>`
                : `<h2>Tickets &amp; services</h2><table><thead><tr><th>Item</th><th class="num">Cost</th><th class="num">Refund</th><th class="num">Penalty</th><th>Who bears</th><th class="num">Our charge</th></tr></thead><tbody>
                    ${T.it.map(i => `<tr><td><b>${esc(i.label)}</b>${i.kind === 't' && !i.refunded ? ' <span class="muted">(kept)</span>' : ''}</td><td class="num">${taka(i.cost)}</td><td class="num">${i.refunded ? taka(i.refund) : '—'}</td>
                        <td class="num">${i.penalty > 0 ? `<b class="bad">${taka(i.penalty)}</b>` : '—'}</td><td>${i.penalty > 0 ? sideText(i.side, i.penalty, i.agent_amount) : '—'}</td><td class="num">${i.charge > 0 ? taka(i.charge) : '—'}</td></tr>`).join('') || '<tr><td colspan="6" class="muted">No tickets or services.</td></tr>'}</tbody></table>`;
            const packageCompany = dep && data.fault === 'split' ? pkg - Number(data.package_agent_amount) : 0;
            const borne = (dep ? T.ours : T.ourPen) + packageCompany;
            return `<div class="fdl-sheet-hd"><div><h1>${esc(cfr.name)}</h1><p class="sub">${esc(CFR.offloadEvents[data.event])} · ${esc(CFR.faults[data.fault] || (data.fault === 'split' ? 'Package split between agent and company' : ''))}</p></div>
                    <div class="ref"><span><b>${esc(cfr.from_flight || '—')}</b>${esc(new Date().toLocaleDateString('en-GB'))}</span></div></div>
                ${table}
                <h2>Agent — ${esc(cfr.agent || 'Direct')}</h2>
                <div class="fdl-money">
                    <div><span class="k">Package</span><span class="v">${data.fault === 'split' ? 'Agent bears ' + taka(data.package_agent_amount) : (reversed ? 'Reversed ' : 'Stands ') + taka(pkg)}</span></div>
                    <div class="m-due"><span class="k">ONE line to agent</span><span class="v">${taka(T.agentLine)}</span></div>
                    <div class="m-due"><span class="k">We bear</span><span class="v">${taka(borne)}</span></div>
                    <div class="m-profit"><span class="k">Our charge (profit)</span><span class="v">${taka(T.charge)}</span></div>
                </div>
                <h2>What will be posted</h2>
                <ul style="margin:2px 0 0 16px;padding:0;font-size:8.2pt;line-height:1.55;">
                    ${dep ? `<li>Each cost → Company Ledger, Dr 5171 / Cr Supplier Payable; a named payee gets the bill on his statement.</li>`
                        : `<li>Refunded tickets cancelled in Ticketing (vendor refund row, Dr Payable / Cr 5110) and unlinked; refunded services → the responsible person's ledger (Cr), Dr Payable / Cr 5150.</li>
                           <li>Each penalty → Company Ledger, Dr 5171 / Cr 5110 (ticket) or 5150 (service).</li>`}
                    ${T.agentLine > 0 ? `<li>ONE line on ${esc(cfr.agent)}'s statement <b class="bad">${taka(T.agentLine)}</b> — Dr 1311 / Cr 4181 (what he bears, recovered) / Cr 4183 (our charge = profit).</li>` : ''}
                    ${packageCompany > 0 ? `<li>Company package share <b>${taka(packageCompany)}</b> credited to the agent; package remaining <b>${taka(data.package_agent_amount)}</b>.</li>` : ''}
                    ${pkg > 0 && reversed ? `<li>Package reversal on ${esc(cfr.agent || 'the agent')}'s statement — Cr ${taka(pkg)}.</li>` : ''}
                    <li>${dep ? 'He stays on the flight flagged Deported.' : data.next === 'close' ? 'He leaves the flight — under Cancelled as Closed, will not fly.' : data.next === 'hold' ? 'He stays on the flight flagged Offloaded.' : `Then he is re-booked onto <b>${esc(f ? f.flight_number : 'the chosen flight')}</b>${followUp && followUp.data.kind === 'reissue' ? ' — his card there opens with Issue New Ticket ready' : ' on the same ticket'}.`}</li>
                    <li>The agent is told, if Messages → Offloaded is switched on.</li>
                </ul>
                ${data.note ? `<h2>Note</h2><p>${esc(data.note)}</p>` : ''}
                <p class="fdl-sheet-note">Epal ERP · Contract Flight · ${dep ? 'deportation' : 'offload'} preview. Nothing has been saved yet — "Confirm &amp; post" writes it.</p>`;
        }
        if (isClose) {
            const toNew = isClose === 'newflight' || isClose === 'refund-rebook';
            const target = followUp ? (followUp.data.contract_flight_id === 'new' ? 'a new flight'
                : (Number(followUp.data.stay_on_flight) ? (cfr.from_flight || 'his flight') + ' (he stays - only a later leg changes)'
                : ((cfrFlights.find(x => String(x.id) === String(followUp.data.contract_flight_id)) || {}).flight_number || 'the chosen flight'))) : null;
            const pen = refundRowsList.reduce((t, r) => t + r.penalty, 0), back = refundRowsList.reduce((t, r) => t + r.agentPenalty, 0);
            const pkg = Number(cfr.agent_price) || 0, fee = Number(data.charge) || 0;
            return `<div class="fdl-sheet-hd"><div><h1>${esc(cfr.name)}</h1><p class="sub">${toNew ? 'Refund old ticket(s) — new ticket on a new flight next' : 'Close case — will not fly'}</p></div>
                    <div class="ref"><span><b>${esc(cfr.from_flight || '—')}</b>${esc(new Date().toLocaleDateString('en-GB'))}</span></div></div>
                ${refundTable}
                <h2>Agent — ${esc(cfr.agent || 'Direct')}</h2>
                <div class="fdl-money">
                    <div><span class="k">Package reversed</span><span class="v">${taka(pkg)}</span></div>
                    <div class="m-due"><span class="k">Penalty billed back</span><span class="v">${taka(back)}</span></div>
                    <div class="m-due"><span class="k">Our fee</span><span class="v">${taka(fee)}</span></div>
                    <div class="m-profit"><span class="k">${toNew ? 'Agent net now' : 'Net credit to agent'}</span><span class="v">${taka(pkg - back - fee)}</span></div>
                </div>
                <h2>What will be posted</h2>
                <ul style="margin:2px 0 0 16px;padding:0;font-size:8.2pt;line-height:1.55;">
                    <li>Old ticket(s) cancelled in Ticketing — vendor / portal refund row, journal Dr Payable / Cr 5110 — and <b>unlinked from him</b>. They stay in Ticket Orders at their amount; nothing deleted.</li>
                    ${pen > 0 ? `<li>Penalty <b class="bad">${taka(pen)}</b> → Company Ledger (Dr 5171 / Cr 5110)${back > 0 ? '; billed to the agent, Dr 1311 / Cr 4181' : ''}.</li>` : ''}
                    ${pkg > 0 ? `<li>Package reversal on ${esc(cfr.agent || 'the agent')}'s statement — Cr ${taka(pkg)}.</li>` : ''}
                    ${target
                        ? `<li>Then he is re-booked onto <b>${esc(target)}</b> — the package is charged to the agent again — and his card there opens with Issue New Ticket ready. The fare difference (new − old) is worked out when the new ticket is linked, ${data.fault === 'agent' || (followUp && followUp.data.fare_bearer === 'agent') ? 'billed as chosen' : 'borne as chosen'}.</li>`
                        : toNew
                        ? `<li>Then <b>Add Flight</b> opens with him on the first card: issue his new ticket(s) there. Saving it re-books him — the package is charged to the agent again, and the fare difference (new − old) is worked out when the new ticket is linked.</li>`
                        : `<li>He shows under Cancelled as <b>Closed — will not fly</b>. The agent's credit is settled later from Party Statement → Record Payment.</li>`}
                </ul>
                <p class="fdl-sheet-note">Epal ERP · Contract Flight · ${toNew ? 'refund' : 'close-case'} preview. Nothing has been saved yet — "Confirm &amp; post" writes it.</p>`;
        }
        const kind = data.kind;
        const picked = data.contract_flight_id;
        const nf = picked === 'new';
        const stayed = !!Number(data.stay_on_flight);
        const f = nf ? null : (stayed ? { flight_number: cfr.from_flight, departure_label: cfr.from_date, route: cfr.from_route } : cfrFlights.find(x => String(x.id) === String(picked)));
        const newNo = nf ? 'New flight (next GF no.)' : (f ? f.flight_number : '—');
        const newWhen = nf ? (data.new_flight.departure_at ? data.new_flight.departure_at.replace('T', ' ') : 'Date to be set') : (f ? (f.departure_label || 'Date pending') : '—');
        const newRoute = nf ? ($('#cfr_nf_route option:selected').text() || '—') : (f ? [f.route, f.airline].filter(Boolean).join(' · ') : '—');
        const fld = (k, v) => `<div class="fdl-sheet-f"><span class="k">${esc(k)}</span><span class="v">${v}</span></div>`;
        const fmtD = iso => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

        // Tickets: from the cards, as they will be saved.
        const cards = $('.cfr-tc').map(function () {
            const c = $(this), on = c.find('.cfr-tc-on').prop('checked');
            const m = cfrTicketMoney[c.data('no')] || { fare: 0, penalties: [] };
            const now = on ? (Number(c.find('.cfr-tc-amt').val()) || 0) : 0;
            return { no: c.data('no'), route: c.data('route'), was: c.data('was'), on,
                date: on ? c.find('.cfr-tc-date').val() : c.data('was'), tno: on ? (c.find('.cfr-tc-tno').val() || '').trim() : '',
                fare: m.fare, past: m.penalties.reduce((t, v) => t + v, 0), now,
                agent_amount: c.find('.cfr-tc-agent').val(), bearer: c.find('.cfr-tc-b.on').data('b') || 'agent' };
        }).get();
        const ticketRows = cards.length ? cards.map(t => `<tr>
                <td><b>${esc(t.no)}</b>${t.on ? '' : ' <span class="muted">(unchanged)</span>'}</td>
                <td>${esc(t.route || '—')}</td>
                <td>${esc(fmtD(t.was))}</td>
                <td>${t.date !== t.was ? `<b class="ok">${esc(fmtD(t.date))}</b>` : esc(fmtD(t.date))}</td>
                <td>${esc(t.tno || '—')}</td>
                <td class="num">${taka(t.fare)}</td>
                <td class="num">${t.past ? `<span class="bad">${taka(t.past)}</span>` : '—'}</td>
                <td class="num">${t.now ? `<b class="bad">${taka(t.now)}</b>` : '—'}</td>
                <td>${t.now ? sideText(t.bearer, t.now, t.agent_amount) : '—'}</td>
                <td class="num">${taka(t.fare + t.past + t.now)}</td>
            </tr>`).join('') : `<tr><td colspan="10" class="muted">${(cfr.held || []).length ? 'Tickets unchanged.' : 'He holds no live ticket.'}</td></tr>`;

        // Money and what posts.
        const rows = perTicket.filter(c => c.amount > 0);
        const agentSum = rows.reduce((t, c) => t + agentPart(c.amount, c.bearer, c.agent_amount), 0)
            + (!perTicket.length && Number(data.charge) > 0 && data.bearer === 'agent' ? Number(data.charge) : 0);
        const ourSum = rows.reduce((t, c) => t + c.amount - agentPart(c.amount, c.bearer, c.agent_amount), 0)
            + (!perTicket.length && Number(data.charge) > 0 && data.bearer !== 'agent' ? Number(data.charge) : 0);
        const posts = [
            stayed
                ? `<li><b>${esc(cfr.name)}</b> stays on <b>${esc(newNo)}</b> &mdash; only the switched-on later leg changes; the flight follows his first leg.</li>`
                : `<li><b>${esc(cfr.name)}</b> leaves <b>${esc(cfr.from_flight || 'Cancelled')}</b> and joins <b>${esc(newNo)}</b>; both flights' head count and money are re-worked.</li>`,
            (cfr.agent && Number(cfr.agent_price) > 0) ? (cfr.on_flight
                ? `<li>Package <b>${taka(cfr.agent_price)}</b> on ${esc(cfr.agent)} moves with him — no new charge.</li>`
                : `<li>Package <b>${taka(cfr.agent_price)}</b> is charged to ${esc(cfr.agent)} again.</li>`) : '',
            kind === 'reschedule'
                ? `<li>Same ticket(s) — ${esc((cfr.held || []).map(t => t.no).join(', ') || 'none')}; changed leg dates are written to Ticketing.</li>`
                : `<li>His live ticket(s) are cancelled with a full refund posted; issue the new ticket on ${esc(newNo)}'s card.</li>`,
            agentSum > 0 ? `<li><b class="bad">${taka(agentSum)}</b> → <b>${esc(cfr.agent || 'agent')}</b>'s statement · journal Dr 1311 Receivable / Cr 4183 Change Charges Recovered.</li>` : '',
            rows.length ? `<li>Each ticket's fee → its <b>vendor</b> (or portal wallet) · journal Dr 5173 Change Fees (Company Borne) / Cr Supplier Payable — shows in the Company Ledger.</li>` : '',
            ourSum > 0 ? `<li><b class="bad">${taka(ourSum)}</b> borne by us — Company Ledger (Borne).</li>` : '',
            Number(data.fare_difference) > 0 ? `<li>Fare difference <b class="bad">${taka(data.fare_difference)}</b> — ${data.fare_bearer === 'agent' ? 'billed to the agent' : 'borne by us'}.</li>` : '',
            '<li>Nothing is deleted — every line is added, with this change in his Flight changes history.</li>',
        ].filter(Boolean).join('');

        return `<div class="fdl-sheet-hd">
                <div><h1>${esc(cfr.name)}</h1><p class="sub">Re-assign · ${kind === 'reschedule' ? 'Reschedule / Reissue — same ticket' : 'Refund (Cancel) / New ticket'}</p></div>
                <div class="ref"><span><b>${esc(cfr.from_flight || 'Cancelled')} → ${esc(newNo)}</b>${esc(new Date().toLocaleDateString('en-GB'))}</span></div>
            </div>
            <h2>Old → New</h2>
            <table><thead><tr><th></th><th>Flight</th><th>Departure</th><th>Route</th></tr></thead><tbody>
                <tr><td class="muted">Old</td><td>${esc(cfr.from_flight || 'Off flight')}</td><td>${esc(cfr.from_date || '—')}</td><td>${esc(cfr.from_route || '—')}</td></tr>
                <tr><td><b>New</b></td><td><b>${esc(newNo)}</b></td><td><b>${esc(newWhen)}</b></td><td>${esc(newRoute)}</td></tr>
            </tbody></table>
            ${refundTable}
            ${refundRowsList.length ? '' : `<h2>Tickets</h2>`}
            ${refundRowsList.length ? '' : `<table><thead><tr><th>PNR</th><th>Leg</th><th>Old date</th><th>New date</th><th>Ticket no.</th>
                <th class="num">Main fare</th><th class="num">Earlier penalties</th><th class="num">This charge</th><th>Who pays</th><th class="num">Total</th></tr></thead>
                <tbody>${ticketRows}</tbody></table>`}
            <h2>Money</h2>
            <div class="fdl-money">
                <div><span class="k">Agent</span><span class="v" style="font-size:9pt;">${esc(cfr.agent || 'Direct')}</span></div>
                <div class="m-due"><span class="k">Agent pays</span><span class="v">${taka(agentSum)}</span></div>
                <div class="m-due"><span class="k">We bear</span><span class="v">${taka(ourSum)}</span></div>
                <div class="m-due"><span class="k">Total charge</span><span class="v">${taka(agentSum + ourSum)}</span></div>
            </div>
            <h2>What will be posted</h2>
            <ul style="margin:2px 0 0 16px;padding:0;font-size:8.2pt;line-height:1.55;">${posts}</ul>
            ${data.note ? `<h2>Note</h2>${fld('', esc(data.note))}` : ''}
            <p class="fdl-sheet-note">Epal ERP · Contract Flight · re-assign preview. Nothing has been saved yet — "Confirm &amp; post" writes it.</p>`;
    }

    window.cfpSaveReassign = function () {
        const err = $('#cfr_error').addClass('hidden').text('');
        // Whatever stops the save below must be seen, not left off-screen.
        setTimeout(() => { if (!err.hasClass('hidden')) err[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 0);
        if (isOffload()) { saveOffload(); return; }
        if (window.cfswSave && window.cfswSave()) return;
        const stay = stayOnFlight();
        const flightId = stay ? String(cfr.flight_id) : $('input[name="cfr_flight"]:checked').val();
        const charge = chargeTotal();
        const fare = isResched() ? 0 : (Number($('#cfr_fare').val()) || 0);
        const perTicket = perTicketMode() ? ticketCharges() : [];
        if (perTicket.some(c => invalidSplit(c.amount, c.bearer, c.agent_amount))) { err.removeClass('hidden').text('Enter the agent share between zero and the penalty on each split ticket.'); return; }
        if (perTicket.some(c => c.amount < 0 || c.fee < 0)) { err.removeClass('hidden').text('Amounts cannot be negative.'); return; }
        const rr = refundMode() ? refundRows() : [];
        const fareSplit = $('#cfr_fare_split').prop('checked');
        const fareAgent = $('#cfr_fare_agent').val();
        if (fareSplit && (invalidSplit(fare, 'split', fareAgent) || !cfr.agent || fare <= 0)) { err.removeClass('hidden').text('Enter the fare difference and a valid agent share for the split.'); return; }
        const penaltyTotal = rr.reduce((t, r) => t + r.penalty, 0);
        if (refundMode() && CFR.hasFault && rr.some(r => r.penalty > 0 && !r.side)) { err.removeClass('hidden').text('The airline keeps a penalty — choose whose side on each leg with a penalty.'); return; }
        if (refundMode() && $('.cfr-rf').length && !rr.length) { err.removeClass('hidden').text('Switch on at least one leg to refund.'); return; }
        const onlyTickets = rr.map(r => r.id).join(',');
        if (rr.some(r => r.side === 'split' && (r.agentShare === '' || !Number.isFinite(r.agentPenalty) || r.agentPenalty < 0 || r.agentPenalty > r.penalty))) {
            err.removeClass('hidden').text('Enter the agent share between zero and the penalty on each split leg.'); return;
        }
        const refundMap = {}, faultMap = {}, penaltyShares = {};
        rr.forEach(r => { refundMap[r.id] = r.refund; if (r.penalty > 0) faultMap[r.id] = r.side; if (r.side === 'split') penaltyShares[r.id] = r.agentPenalty; });
        const legFault = rr.some(r => r.agentPenalty > 0) ? 'agent' : (penaltyTotal > 0 ? 'company' : null);
        if (closing()) {
            const closeData = { reason: 'will_not_fly', cancel_ticket: rr.length ? 1 : 0, only_tickets: onlyTickets, refunds: refundMap, faults: faultMap, penalty_agent_amounts: penaltyShares,
                fault: legFault, charge: charge > 0 ? charge : null, note: $('#cfr_note').val().trim() || null };
            cfrShowConfirm(closeData, [], CFR.cancel.replace('__ID__', cfr.id), 'PATCH', rr, true);
            return;
        }
        if (!flightId) { err.removeClass('hidden').text('Choose the flight he goes on.'); return; }
        // Refund rule (owner, 2026-09-30): a refund reverses the package in
        // full and puts the penalty on whoever bears it — on every path. So
        // someone still on a flight is refunded FIRST (Cancel from flight:
        // tickets cancelled with the airline's refund, package reversed,
        // penalty + our fee on one agent line), then re-booked from
        // Cancelled, which charges the package again.
        if (cfr.on_flight && refundMode()) {
            const cancelData = { reason: 'new_ticket', cancel_ticket: rr.length ? 1 : 0, only_tickets: onlyTickets, refunds: refundMap, faults: faultMap, penalty_agent_amounts: penaltyShares,
                fault: legFault, charge: charge > 0 ? charge : null, note: $('#cfr_note').val().trim() || null };
            if (!stay && addFlightAfterRefund()) {
                cfrShowConfirm(cancelData, [], CFR.cancel.replace('__ID__', cfr.id), 'PATCH', rr, 'newflight');
                return;
            }
            const heldAll = cfr.held || [];
            const oneLeg = rr.length === 1 && heldAll.length > 1 ? (heldAll.find(h => String(h.id) === String(rr[0].id)) || {}).seq : null;
            const rebook = { kind: 'reissue', contract_flight_id: flightId, stay_on_flight: stay ? 1 : 0, only_tickets: '', _leg: oneLeg || null,
                fare_difference: fare > 0 ? fare : null, fare_bearer: fareSplit ? 'agent' : $('#cfr_fare_bearer').val(),
                ...(fareSplit ? {agent_fare_amount: fareAgent} : {}),
                new_flight: flightId === 'new' ? { ticket_id: $('#cfr_nf_route').val() || null, airline_flight_no: $('#cfr_nf_no').val().trim() || null, departure_at: $('#cfr_nf_dep').val() || null } : null,
                note: $('#cfr_note').val().trim() || null };
            cfrShowConfirm(cancelData, [], CFR.cancel.replace('__ID__', cfr.id), 'PATCH', rr, 'refund-rebook',
                { url: CFR.reassign.replace('__ID__', cfr.id), data: rebook });
            return;
        }
        if (charge < 0 || fare < 0) { err.removeClass('hidden').text('Amounts cannot be negative.'); return; }
        const data = {
                kind: $('input[name="cfr_kind"]:checked').val(),
                contract_flight_id: flightId,
                stay_on_flight: stay ? 1 : 0,
                charge: !perTicket.length && charge > 0 ? charge : null,
                bearer: !perTicket.length && charge > 0 ? $('#cfr_bearer').val() : null,
                ticket_charges: perTicket.filter(c => c.amount > 0 || c.fee > 0 || c.travel_date || c.ticket_number),
                fare_difference: fare > 0 ? fare : null,
                fare_bearer: fareSplit ? 'agent' : ((fare > 0 || $('input[name="cfr_kind"]:checked').val() === 'reissue') ? $('#cfr_fare_bearer').val() : null),
                ...(fareSplit ? {agent_fare_amount: fareAgent} : {}),
                new_flight: flightId === 'new' ? {
                    ticket_id: $('#cfr_nf_route').val() || null,
                    airline_flight_no: $('#cfr_nf_no').val().trim() || null,
                    departure_at: $('#cfr_nf_dep').val() || null,
                } : null,
                note: $('#cfr_note').val().trim() || null,
        };
        if (refundMode()) {
            data.refunds = refundMap;
            data.faults = faultMap;
            data.penalty_agent_amounts = penaltyShares;
            data.only_tickets = onlyTickets;
            data.fault = legFault;
        }
        cfrShowConfirm(data, perTicket, CFR.reassign.replace('__ID__', cfr.id), 'PATCH', rr, false);
    };

    // Preview first; only the sheet's "Confirm & post" sends it.
    function cfrShowConfirm(data, perTicket, url, method, refundRowsList, isClose, followUp) {
        const err = $('#cfr_error');
        const overlay = cfrConfirmOverlay();
        overlay.querySelector('.fdl-sheet').innerHTML = cfrConfirmHtml(data, perTicket, refundRowsList, isClose, followUp);
        overlay.hidden = false;
        overlay.scrollTop = 0;
        const go = $(overlay).find('[data-cfr-go]').prop('disabled', false).off('click').on('click', function () {
            go.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Posting…');
            $.ajax({ url, method, data })
                .done(r => {
                    if (!r.success) { overlay.hidden = true; err.removeClass('hidden').text(r.message || 'Could not save.'); return; }
                    overlay.hidden = true;
                    if (followUp) {
                        // Step 2: re-book him from Cancelled onto the chosen flight.
                        $.ajax({ url: followUp.url, method: 'PATCH', data: followUp.data })
                            .done(r2 => {
                                cfpCloseReassign();
                                const off = isClose === 'offload';
                                if (!r2.success) {
                                    Swal.fire({ icon: 'warning', title: off ? 'Posted — not re-booked yet' : 'Refunded — not re-booked yet', text: (r2.message || '') + (off ? ' He is still on his flight, flagged; use Re-assign.' : ' He is under Cancelled; use Re-assign there.') }).then(() => window.location.reload());
                                    return;
                                }
                                const next = r2.flight_id && (!off || followUp.data.kind === 'reissue') ? CFR.index + '?search=' + encodeURIComponent(r2.flight_number || '') + '&edit=' + r2.flight_id + '&issue=' + r2.passenger_id + (followUp.data._leg ? '&leg=' + followUp.data._leg : '') : null;
                                Swal.fire({ icon: 'success', title: off ? 'Offload posted & re-booked' : 'Refunded, re-booked & posted', text: r2.message + (next ? ' Opening his card to issue the new ticket…' : ''), timer: 2400, showConfirmButton: false })
                                    .then(() => { if (next) window.location.href = next; else window.location.reload(); });
                            })
                            .fail(xhr => {
                                cfpCloseReassign();
                                Swal.fire({ icon: 'warning', title: isClose === 'offload' ? 'Posted — not re-booked yet' : 'Refunded — not re-booked yet', text: failText(xhr, 'The move failed.') + (isClose === 'offload' ? ' He is still on his flight, flagged; use Re-assign.' : ' He is under Cancelled; use Re-assign there.') }).then(() => window.location.reload());
                            });
                        return;
                    }
                    if (isClose === 'newflight') {
                        // Old tickets refunded, he is under Cancelled now —
                        // straight into Add Flight with him on the first card.
                        const who = Object.assign({}, cfr);
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', timer: 2600, showConfirmButton: false,
                            title: 'Old tickets refunded & posted — issue the new ticket on the new flight' });
                        cfrOpenAddFlight(who);
                        return;
                    }
                    cfpCloseReassign();
                    // New ticket next: open his card on the new flight with
                    // Issue New Ticket ready (owner, 2026-09-30).
                    const next = !isClose && data.kind === 'reissue' && r.flight_id
                        ? CFR.index + '?search=' + encodeURIComponent(r.flight_number || '') + '&edit=' + r.flight_id + '&issue=' + r.passenger_id
                        : null;
                    Swal.fire({ icon: 'success', title: isClose === 'offload' ? (data.event === 'deported' ? 'Deportation posted' : 'Offload posted') : isClose ? 'Case closed & posted' : 'Re-assigned & posted', text: r.message + (next ? ' Opening his card to issue the new ticket…' : ''), timer: 2400, showConfirmButton: false })
                        .then(() => { if (next) window.location.href = next; else window.location.reload(); });
                })
                .fail(xhr => { overlay.hidden = true; err.removeClass('hidden').text(failText(xhr, 'Could not save.')); })
                .always(() => go.prop('disabled', false).html('<i class="fas fa-check"></i> Confirm &amp; post'));
        });
    }

    // ── Undo ──────────────────────────────────────────────────────────
    // His old flight was deleted: nothing to put him back on. Say so, and offer the way that
    // does work — Re-assign onto another flight (owner, 2026-10-26: "why these 2 is not working?").
    window.cfpUndoGone = function (btn) {
        const flight = btn.getAttribute('data-flight'), name = btn.getAttribute('data-name') || 'this passenger';
        const reassign = btn.closest('.mfp-actions') && btn.closest('.mfp-actions').querySelector('button[onclick*="cfpOpenReassign"]');
        Swal.fire({
            icon: 'info',
            title: 'Undo is not possible here',
            html: `<div style="font-size:13px;color:#475569;">Undo puts him back on the flight he was taken off${flight ? ` — <b>${esc(flight)}</b> — but that flight was deleted` : ', and there is no record of it'}, so there is nothing to put <b>${esc(name)}</b> back on.<br><br>Use <b>Re-assign</b> to put him on another flight. His package is billed to the agent again then — once.</div>`,
            showCancelButton: !!reassign,
            confirmButtonText: reassign ? 'Re-assign him' : 'OK',
            cancelButtonText: 'Close',
        }).then(r => { if (r.isConfirmed && reassign) window.cfpOpenReassign(reassign); });
    };

    window.cfpRestore = function (btn) {
        let p = {};
        try { p = JSON.parse(btn.getAttribute('data-cfp') || '{}'); } catch (e) { return; }
        if (p.undo_full && CFR.undoPreview) {
            // a wrong cancellation, undone completely: show what goes, then do it
            $.get(CFR.undoPreview.replace('__ID__', p.id)).done(pl => {
                if (!pl.success) { Swal.fire({ icon: 'info', title: 'Cannot undo', text: pl.message || 'Not possible.' }); return; }
                Swal.fire({
                    icon: 'warning',
                    title: `Undo ${esc(p.name)}'s cancellation?`,
                    html: `<div style="font-size:13px;color:#475569;text-align:left;">He goes back on <b>${esc(pl.flight)}</b> exactly as before${pl.restore_flight ? ' (the deleted flight is restored)' : ''}. These lines are <b>removed</b>:<ul style="margin:8px 0 0 18px;">${(pl.lines || []).map(l => `<li>${esc(l)}</li>`).join('') || '<li>nothing was posted</li>'}</ul></div>`,
                    showCancelButton: true, confirmButtonText: 'Undo completely', confirmButtonColor: '#dc2626', allowOutsideClick: false,
                }).then(r => {
                    if (!r.isConfirmed) return;
                    $.ajax({ url: CFR.restore.replace('__ID__', p.id), method: 'PATCH' })
                        .done(x => { if (!x.success) { Swal.fire({ icon: 'error', title: 'Not undone', text: x.message || 'Something went wrong.' }); return; }
                            Swal.fire({ icon: 'success', title: 'Undone', text: x.message, timer: 2000, showConfirmButton: false }).then(() => window.location.reload()); })
                        .fail(xhr => Swal.fire({ icon: 'error', title: 'Not undone', text: failText(xhr, 'Could not undo.') }));
                });
            }).fail(xhr => Swal.fire({ icon: 'error', title: 'Cannot undo', text: failText(xhr, 'Could not load what would be undone.') }));
            return;
        }
        Swal.fire({
            icon: 'question',
            title: `Put ${esc(p.name)} back on ${esc(p.from_flight)}?`,
            html: `<div style="font-size:13px;color:#475569;">The cancellation is undone: he is back on <b>${esc(p.from_flight)}</b> exactly as before${(p.agent && Number(p.agent_price) > 0) ? `, and <b>${taka(p.agent_price)}</b> is billed to <b>${esc(p.agent)}</b> again` : ''}. No new charge — anything already billed for the cancellation (charge, airline penalty) stays on the agent's statement.${(p.cancelled || []).length ? `<br><span style="color:#b91c1c;">His ticket <b>${esc(p.cancelled.join(', '))}</b> was cancelled when he was taken off and stays cancelled — issue a new one from his card.</span>` : ''}</div>`,
            showCancelButton: true,
            confirmButtonText: 'Put him back',
            confirmButtonColor: '#2563eb',
            allowOutsideClick: false,
        }).then(result => {
            if (!result.isConfirmed) return;
            $.ajax({ url: CFR.restore.replace('__ID__', p.id), method: 'PATCH' })
                .done(r => {
                    if (!r.success) { Swal.fire({ icon: 'error', title: 'Not restored', text: r.message || 'Something went wrong.' }); return; }
                    Swal.fire({ icon: 'success', title: 'Back on the flight', text: r.message, timer: 1800, showConfirmButton: false })
                        .then(() => window.location.reload());
                })
                .fail(xhr => Swal.fire({ icon: 'error', title: 'Not restored', text: failText(xhr, 'Could not put him back.') }));
        });
    };

    // ── Flight changes panel (passenger edit modal) ───────────────────
    // History of his moves, and the one way to correct a charge afterwards.
    window.renderFlightChanges = function (changes) {
        const panel = $('#pem_changes_panel');
        const wrap = $('#pem_changes_list').empty();
        changes = changes || [];
        panel.toggle(changes.length > 0);
        changes.forEach(c => {
            const move = [c.from_flight, c.to_flight].filter(Boolean).join(' → ');
            const money = c.chargeable
                ? (Number(c.charge) > 0 ? `${taka(c.charge)} · ${esc(c.bearer_label || '')}` : 'No charge')
                : null;
            const extra = [
                Number(c.airline_penalty) > 0 ? `Airline penalty ${taka(c.airline_penalty)}${c.fault_label ? ' · ' + esc(c.fault_label) : ''}` : null,
                Number(c.fare_difference) > 0 ? `Fare difference ${taka(c.fare_difference)}${c.fare_bearer_label ? ' · ' + esc(c.fare_bearer_label) : ''}` : null,
            ].filter(Boolean).join(' · ');
            wrap.append(`<div style="display:flex;align-items:center;gap:8px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;font-size:12px;">
                <span style="flex:1;min-width:0;"><b>${esc(c.kind_label)}</b>${move ? ' · ' + esc(move) : ''}${c.reason_label ? ' · ' + esc(c.reason_label) : ''}
                    <span style="display:block;color:#94a3b8;font-size:10.5px;">${esc(c.at || '')}${c.by ? ' · by ' + esc(c.by) : ''}${c.note ? ' · ' + esc(c.note) : ''}</span>${extra ? `<span style="display:block;color:#b91c1c;font-size:10.5px;">${extra}</span>` : ''}</span>
                ${money !== null ? `<span style="white-space:nowrap;color:#0f766e;font-weight:600;">${money}</span>` : ''}
                ${typeof window.cftOpen === 'function'
                    // super admin (2026-11-01): the Trail replaces Correct — the entry edited, its rows REPLACED (partials.cf-trail)
                    ? `<button type="button" class="fcm-btn fcm-btn-ghost" style="padding:3px 9px;font-size:11px;" onclick="cftOpen(Number($('#pem_id').val()), 'change:${c.id}')"><i class="fas fa-pen-to-square"></i> Edit</button>`
                    : typeof window.cfcOpen === 'function' && c.kind !== 'restored'
                    // super admin (2026-10-19): one Correct for every part of the entry — partials.change-correction
                    ? `<button type="button" class="fcm-btn fcm-btn-ghost" style="padding:3px 9px;font-size:11px;" onclick="cfcOpen(${c.id})"><i class="fas fa-pen-to-square"></i> Correct</button>`
                    : (money !== null ? `<button type="button" class="fcm-btn fcm-btn-ghost" style="padding:3px 9px;font-size:11px;" onclick="cfpEditChangeCharge(${c.id})">Edit charge</button>` : '')}
            </div>`);
        });
        panel.data('changes', changes);
    };

    window.cfpEditChangeCharge = function (changeId) {
        const c = ($('#pem_changes_panel').data('changes') || []).find(x => x.id === changeId);
        if (!c) return;
        const opts = Object.entries(CFR.bearers).map(([k, v]) => `<option value="${esc(k)}"${(c.bearer || 'agent') === k ? ' selected' : ''}>${esc(v)}</option>`).join('');
        Swal.fire({
            title: 'Charge for this move',
            html: `<div style="text-align:left;font-size:13px;">
                    <div style="color:#64748b;font-size:12px;margin-bottom:8px;">${esc(c.kind_label)}${c.to_flight ? ' · ' + esc(c.to_flight) : ''}. Billed to the agent posts to their statement; clearing it posts zero there (the reversal stays visible).</div>
                    <input id="cfrChargeAmt" type="number" min="0" step="0.01" value="${Number(c.charge) || ''}" placeholder="৳ 0.00 (none)" style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:7px 9px;font-size:13px;margin-bottom:8px;">
                    <select id="cfrChargeBearer" class="cfr-sw-src" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:7px 9px;font-size:13px;">${opts}</select>
                   </div>`,
            showCancelButton: true,
            confirmButtonText: 'Save charge',
            confirmButtonColor: '#0f766e',
            allowOutsideClick: false,
            preConfirm: () => {
                const charge = Number(document.getElementById('cfrChargeAmt').value) || 0;
                if (charge < 0) { Swal.showValidationMessage('The charge cannot be negative.'); return false; }
                return { charge: charge > 0 ? charge : null, bearer: charge > 0 ? document.getElementById('cfrChargeBearer').value : null };
            },
        }).then(result => {
            if (!result.isConfirmed) return;
            $.ajax({ url: CFR.charge.replace('__ID__', changeId), method: 'PATCH', data: result.value })
                .done(r => {
                    if (!r.success) { Swal.fire({ icon: 'error', title: 'Not saved', text: r.message || 'Something went wrong.' }); return; }
                    renderFlightChanges(r.changes || []);
                    const id = Number($('#pem_id').val());
                    const cached = (typeof cfpCurrentPassengers !== 'undefined' ? cfpCurrentPassengers : []).find(x => x.id === id);
                    if (cached) cached.flight_changes = r.changes || [];
                    Swal.fire({ icon: 'success', title: 'Charge saved', timer: 1300, showConfirmButton: false });
                })
                .fail(xhr => Swal.fire({ icon: 'error', title: 'Not saved', text: failText(xhr, 'Could not save the charge.') }));
        });
    };
})();
</script>
@endif
