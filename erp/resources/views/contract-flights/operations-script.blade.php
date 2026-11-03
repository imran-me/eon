@php
    // Built as a plain PHP array first, then emitted through a single @json($var)
    // with no arguments of its own — @json(expr) splits expr on every comma (it
    // also accepts optional $options/$depth params), so a route() call whose
    // array has 3+ keys gets silently truncated if inlined directly.
    $cfpUrls = [
        'passengersIndex' => route('role.contract-flight-passengers.index', ['role' => $role, 'contractFlight' => '__FID__']),
        'update' => route('role.contract-flight-passengers.update', ['role' => $role, 'contractFlightPassenger' => '__ID__']),
        'ticketSearch' => route('role.contract-flight-passengers.ticket-purchases', ['role' => $role, 'contractFlightPassenger' => '__ID__']),
        'linkTicket' => route('role.contract-flight-passengers.link-ticket', ['role' => $role, 'contractFlightPassenger' => '__ID__']),
        'unlinkTicket' => route('role.contract-flight-passengers.unlink-ticket', ['role' => $role, 'contractFlightPassenger' => '__ID__', 'ticketPurchase' => '__TID__']),
        'searchFlights' => route('role.contract-flight-passengers.search-flights', ['role' => $role]),
        'moveFlight' => route('role.contract-flight-passengers.move-flight', ['role' => $role, 'contractFlightPassenger' => '__ID__']),
        // Re-Issue / Refund on one ticket (from live, 2026-09-29; posted our
        // way 2026-09-30). Guarded: a server without the route just shows no
        // buttons, never an error.
        'ticketAdjustment' => \App\Models\ContractFlightPassengerChange::ticketAdjustmentAvailable()
            ? route('role.contract-flight-passengers.ticket-adjustment', ['role' => $role, 'contractFlightPassenger' => '__ID__'])
            : null,
        // Ticket lanes + "move him to the flight on that date" (2026-10-08). Each guarded:
        // a server without the route renders the old flat list, never an error.
        'lanes' => \Illuminate\Support\Facades\Route::has('role.contract-flight-passengers.lanes')
            ? route('role.contract-flight-passengers.lanes', ['role' => $role, 'contractFlightPassenger' => '__ID__']) : null,
        'laneNewTicket' => \Illuminate\Support\Facades\Route::has('role.contract-flight-passengers.lane-new-ticket')
            ? route('role.contract-flight-passengers.lane-new-ticket', ['role' => $role, 'contractFlightPassenger' => '__ID__']) : null,
        'undoChange' => \Illuminate\Support\Facades\Route::has('role.contract-flight-passengers.undo-change')
            ? route('role.contract-flight-passengers.undo-change', ['role' => $role, 'contractFlightPassenger' => '__ID__']) : null,
        'correctRefund' => \Illuminate\Support\Facades\Route::has('role.contract-flight-passengers.correct-refund')
            ? route('role.contract-flight-passengers.correct-refund', ['role' => $role, 'contractFlightPassenger' => '__ID__']) : null,
        'reassignFlights' => \Illuminate\Support\Facades\Route::has('role.contract-flight-passengers.reassign-flights')
            ? route('role.contract-flight-passengers.reassign-flights', ['role' => $role, 'contractFlightPassenger' => '__ID__']) : null,
        'reassign' => \Illuminate\Support\Facades\Route::has('role.contract-flight-passengers.reassign')
            ? route('role.contract-flight-passengers.reassign', ['role' => $role, 'contractFlightPassenger' => '__ID__']) : null,
        // Cancel return (2026-10-14): only once its migration ran and its routes are registered.
        'cancelReturn' => \App\Services\ReturnCancel::ready() && \Illuminate\Support\Facades\Route::has('role.contract-flight-passengers.cancel-return')
            ? route('role.contract-flight-passengers.cancel-return', ['role' => $role, 'contractFlightPassenger' => '__ID__']) : null,
        'returnWindow' => \App\Services\ReturnCancel::ready() && \Illuminate\Support\Facades\Route::has('role.ticket-changes.return-window')
            ? route('role.ticket-changes.return-window', ['role' => $role, 'ticketPurchase' => '__TID__']) : null,
    ];
@endphp
<script>
    const flightPresetMatchUrl = @json(route('role.flight-price-presets.match', ['role' => $role]));
    const flightPassengerStoreUrl = @json(route('role.passport-holder.store', ['role' => $role]));
    let flightPassengerTarget = null;
    let hydratingFlight = false;

    // Passenger pipeline tracking — URL templates carry a placeholder id the
    // caller swaps in, since the route needs a real record before it exists.
    const cfpUrls = @json($cfpUrls);
    const cfpPassengersIndexUrlTpl = cfpUrls.passengersIndex;
    const cfpUpdateUrlTpl = cfpUrls.update;
    const cfpTicketSearchUrlTpl = cfpUrls.ticketSearch;
    const cfpLinkTicketUrlTpl = cfpUrls.linkTicket;
    const cfpUnlinkTicketUrlTpl = cfpUrls.unlinkTicket;
    const cfpSearchFlightsUrl = cfpUrls.searchFlights;
    const cfpMoveFlightUrlTpl = cfpUrls.moveFlight;
    const cfpTicketAdjustmentUrlTpl = cfpUrls.ticketAdjustment;
    let cfpCurrentPassengers = [];

    const baseFlightDetails = window.viewFlight;
    window.viewFlight = function (id) {
        if (typeof baseFlightDetails === 'function') baseFlightDetails(id);
        const flight = mfFlights.find(item => String(item.id) === String(id));
        if (!flight) return;
        $('#view_ticket_class').text((flight.ticket_class || 'economy').replace(/\b\w/g, c => c.toUpperCase()));
        $('#view_arrival').text(flight.arrival_at ? new Date(flight.arrival_at).toLocaleString() : '-');
        $('#view_boarding_officer').text(flight.boarding_officer?.user?.name || '-');
        $('#view_immigration_officer').text(flight.immigration_officer?.user?.name || '-');
        $('#view_passenger_count').text((flight.passengers || []).length);
        loadFlightPassengers(id);
    };

    // A list row edits one passenger without opening the flight first: pull
    // that flight's passengers into the same cache the view modal uses, then
    // open the editor. Saving from here reloads so the row reflects the edit.
    let pemOpenedFromList = false;
    window.editPassengerFromList = function (flightId, passengerId) {
        $.get(cfpPassengersIndexUrlTpl.replace('__FID__', flightId)).done(function (response) {
            if (!response.success) return;
            cfpCurrentPassengers = response.passengers || [];
            pemOpenedFromList = true;
            openPassengerEditModal(passengerId);
        });
    };

    function loadFlightPassengers(flightId) {
        $('#view_passengers_loading').removeClass('hidden');
        $.get(cfpPassengersIndexUrlTpl.replace('__FID__', flightId)).done(function (response) {
            if (!response.success) return;
            cfpCurrentPassengers = response.passengers || [];
            renderFlightPassengers();
        }).always(function () {
            $('#view_passengers_loading').addClass('hidden');
        });
    }

    function renderFlightPassengers() {
        updateFlightTotals();
        const tbody = $('#view_passengers_tbody').empty();
        if (!cfpCurrentPassengers.length) {
            tbody.append('<tr><td colspan="11" style="padding:10px 8px;color:#9ca3af;">No passengers on this flight yet.</td></tr>');
            return;
        }
        const money = v => '৳' + (Number(v) || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
        cfpCurrentPassengers.forEach(function (p) {
            const pnrs = (p.ticket_purchases || []).map(t => t.ticket_no || ('#' + t.id)).join(', ') || '<span style="color:#9ca3af;">Not linked</span>';
            const stageBadge = '<span style="background:#e0f2fe;color:#075985;padding:2px 7px;border-radius:5px;font-size:11px;">' + p.stage_label + '</span>'
                + (p.exception_label ? ' <span style="background:#fee2e2;color:#991b1b;padding:2px 7px;border-radius:5px;font-size:11px;">⚠ ' + p.exception_label + '</span>' : '');
            const contractBadge = p.contract_confirmed
                ? '<span style="color:#15803d;font-weight:600;">Confirmed</span>'
                : '<span style="color:#9ca3af;">Pending</span>';
            const profitColor = (Number(p.profit) || 0) < 0 ? '#dc2626' : '#1e40af';
            tbody.append(
                '<tr style="border-top:1px solid #f1f5f9;">' +
                '<td style="padding:6px 8px;">' + (p.name || 'Unknown') + '</td>' +
                '<td style="padding:6px 8px;">' + (p.flight_category_name || '—') + '</td>' +
                '<td style="padding:6px 8px;">' + (p.ticket_class_label || 'Economy') + '</td>' +
                '<td style="padding:6px 8px;">' + stageBadge + '</td>' +
                '<td style="padding:6px 8px;">' + contractBadge + '</td>' +
                '<td style="padding:6px 8px;">' + (p.agent_name || '—') + '</td>' +
                '<td style="padding:6px 8px;">' + pnrs + '</td>' +
                '<td style="padding:6px 8px;text-align:right;">' + money(p.agent_price) + '</td>' +
                '<td style="padding:6px 8px;text-align:right;">' + money(p.total_travel_cost) + '</td>' +
                '<td style="padding:6px 8px;text-align:right;color:' + profitColor + ';font-weight:600;">' + money(p.profit) + '</td>' +
                '<td style="padding:6px 8px;text-align:right;"><button type="button" class="fcm-btn fcm-btn-ghost" style="padding:4px 10px;font-size:11px;" onclick="openPassengerEditModal(' + p.id + ')">Edit</button></td>' +
                '</tr>'
            );
        });
    }

    // Summed from real per-passenger data (agent price, ticket+manpower+
    // boarding cost) — replaces the flight's old flat sale_price_per_pax
    // estimate, which never reflected what each passenger actually costs.
    function updateFlightTotals() {
        const fmt = v => '৳' + v.toLocaleString(undefined, { maximumFractionDigits: 0 });
        const totalAgentCharge = cfpCurrentPassengers.reduce((sum, p) => sum + (Number(p.agent_price) || 0), 0);
        const totalTravelCost = cfpCurrentPassengers.reduce((sum, p) => sum + (Number(p.total_travel_cost) || 0), 0);
        const totalProfit = cfpCurrentPassengers.reduce((sum, p) => sum + (Number(p.profit) || 0), 0);
        $('#view_total_agent_charge').text(fmt(totalAgentCharge));
        $('#view_total_travel_cost').text(fmt(totalTravelCost));
        $('#view_total_profit').text(fmt(totalProfit)).css('color', totalProfit < 0 ? '#dc2626' : '#1e40af');
    }

    function openPassengerEditModal(passengerId) {
        const p = cfpCurrentPassengers.find(item => item.id === passengerId);
        if (!p) return;
        $('#pem_id').val(p.id);
        $('#pem_passenger_name').text(p.name || 'Passenger');
        $('#pem_stage').val(p.stage).trigger('change');
        $('#pem_ticket_class').val(p.ticket_class || 'economy').trigger('change');
        $('#pem_flight_category_id').val(p.flight_category_id ? String(p.flight_category_id) : '').trigger('change');
        $('#pem_exception_flag').val(p.exception_flag || '').trigger('change');
        $('#pem_contract_confirmed').prop('checked', !!p.contract_confirmed);
        $('#pem_has_bmet').prop('checked', !!p.has_bmet);
        $('#pem_has_manpower_card').prop('checked', !!p.has_manpower_card);
        $('#pem_boarding_training').prop('checked', p.boarding_training !== false);
        toggleBoardingCostField();
        $('#pem_manpower_card_fee').val(p.manpower_card_fee || '');
        $('#pem_agent_id').val(p.agent_id ? String(p.agent_id) : '').trigger('change');
        $('#pem_agent_price').val(p.agent_price || '');
        $('#pem_agent_paid_amount').val(p.agent_paid_amount || '');
        $('#pem_manpower_cost').val(p.manpower_cost || '');
        $('#pem_boarding_cost').val(p.boarding_cost || '');
        $('#pem_immigration_cost').val(p.immigration_cost || '');
        $('#pem_reissue_cost').val(p.reissue_cost || '');
        $('#pem_reissue_bearer').val(p.reissue_bearer || '').trigger('change');
        $('#pem_contact_phone').val(p.contact_phone || p.phone || '');
        $('#pem_tickets_required').val(String(Math.max(1, parseInt(p.tickets_required, 10) || 1)));
        $('#pem_remarks').val(p.remarks || '');
        $('#pem_journey_event').val('').trigger('change');
        $('#pem_journey_event_at').val(new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16));
        $('#pem_journey_event_note').val('');
        $('#pem_ticket_search').val('');
        $('#pem_ticket_results').empty();
        $('#pem_current_flight').text(p.flight_number || '—');
        $('#pem_flight_search').val('');
        $('#pem_flight_results').empty();
        $('#pem_error').addClass('hidden');
        toggleManpowerFeeField();
        renderPassengerTickets(p.ticket_purchases || []);
        pemTicketTotal = (p.ticket_purchases || []).reduce((sum, t) => sum + (Number(t.amount) || 0), 0);
        updateTotalTravelCost();
        updateReissueHint();
        renderJourneyHistory(p.journey_events || []);
        // Moves between flights — only where reassign-script is loaded.
        if (typeof window.renderFlightChanges === 'function') window.renderFlightChanges(p.flight_changes || []);
        $('#passengerEditOverlay').removeClass('hidden');
    }

    // Ticket amounts are fixed for the session the modal is open (editing
    // them happens on the Ticketing screen, not here); manpower/boarding/card
    // fee and agent price recompute live so the total and profit reflect
    // what's about to be saved.
    let pemTicketTotal = 0;
    function money(v) { return '৳' + v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function updateTotalTravelCost() {
        // Mirrors ContractFlightPassenger::getTotalTravelCostAttribute() —
        // reissue is deliberately not in either, so the figure shown here and
        // the one the server stores can never disagree.
        const total = pemTicketTotal
            + (Number($('#pem_manpower_cost').val()) || 0)
            + (Number($('#pem_boarding_cost').val()) || 0)
            + (Number($('#pem_immigration_cost').val()) || 0)
            + (Number($('#pem_manpower_card_fee').val()) || 0);
        $('#pem_total_travel_cost').text(money(total));
        // agent_price is what we charge the agent (receivable), not a
        // commission we pay them — so profit is the margin on top of cost.
        const profit = (Number($('#pem_agent_price').val()) || 0) - total;
        $('#pem_profit').text(money(profit)).css('color', profit < 0 ? '#dc2626' : '#92400e');
    }
    $(document).on('input', '#pem_manpower_cost, #pem_boarding_cost, #pem_immigration_cost, #pem_manpower_card_fee, #pem_agent_price', updateTotalTravelCost);

    // Tells the person editing the card exactly what Save is about to do —
    // billed to the agent is a real ledger post, not just a note, so it
    // must not be a surprise after the fact.
    function updateReissueHint() {
        const bearer = $('#pem_reissue_bearer').val();
        const amount = Number($('#pem_reissue_cost').val()) || 0;
        const hint = $('#pem_reissue_hint');
        if (bearer === 'agent' && amount > 0) {
            const agentName = $('#pem_agent_id option:selected').text() || 'the agent';
            hint.text('On Save: ' + money(amount) + ' will be invoiced to ' + agentName + '.').css('color', '#0f766e');
        } else if (bearer === 'company' && amount > 0) {
            hint.text('On Save: absorbed as our own exception cost — nothing billed to the agent.').css('color', '#64748b');
        } else {
            hint.text('');
        }
    }
    $(document).on('input change', '#pem_reissue_cost, #pem_reissue_bearer, #pem_agent_id', updateReissueHint);

    function closePassengerEditModal() {
        $('#passengerEditOverlay').addClass('hidden');
    }

    // Boarding Training is a ticked service (2026-09-21): its cost box
    // exists only while it is on, like the builder card.
    $(document).on('change', '#pem_boarding_training', toggleBoardingCostField);
    function toggleBoardingCostField() {
        const on = $('#pem_boarding_training').is(':checked');
        $('#pem_boarding_cost').closest('div').toggle(on);
        if (!on) $('#pem_boarding_cost').val('');
        updateTotalTravelCost();
    }

    $(document).on('change', '#pem_has_manpower_card', toggleManpowerFeeField);
    function toggleManpowerFeeField() {
        const hasCard = $('#pem_has_manpower_card').is(':checked');
        $('#pem_manpower_fee_wrap').toggle(!hasCard);
        if (hasCard) $('#pem_manpower_card_fee').val('');
        updateTotalTravelCost();
    }

    // Backdrop click deliberately does NOT close this (owner, 2026-09-22).
    // The passenger card is an entry form; only the ✕ closes it.

    // "Record a Journey Event" is a write-only box — it always resets on
    // open so the next save is a fresh entry, not an edit of the last one.
    // Without this, saving one looked like it vanished: the field really
    // was blank again, but the event itself was recorded server-side.
    function renderJourneyHistory(events) {
        const wrap = $('#pem_journey_history').empty();
        if (!events.length) {
            wrap.append('<div style="font-size:11.5px;color:#9ca3af;">No events logged yet.</div>');
            return;
        }
        // events() (ContractFlightPassenger relation) is already newest-first.
        events.slice(0, 5).forEach(function (e) {
            const when = e.occurred_at ? new Date(e.occurred_at).toLocaleString(undefined, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
            wrap.append(
                '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;font-size:12px;">' +
                '<div style="display:flex;justify-content:space-between;gap:8px;"><b>' + $('<div>').text(e.label || e.code || 'Event').html() + '</b>' +
                '<span style="color:#9ca3af;font-size:11px;white-space:nowrap;">' + when + '</span></div>' +
                (e.note ? '<div style="color:#64748b;margin-top:2px;">' + $('<div>').text(e.note).html() + '</div>' : '') +
                (e.created_by ? '<div style="color:#9ca3af;font-size:10.5px;margin-top:2px;">by ' + $('<div>').text(e.created_by).html() + '</div>' : '') +
                '</div>'
            );
        });
    }

    // ── Ticket lanes (owner, 2026-10-08) ─────────────────────────────────────
    // One lane per ticket on his route — DAC-BAH-IST is lane 1, IST-ECN lane 2 —
    // each with its own history and its own Re-Issue / Refund / New ticket. Lanes
    // never touch each other. He stays on the flight his FIRST ticket refers to:
    // when lane 1's live date and his flight differ, the lanes say so and offer the move.
    function renderPassengerTickets(tickets) {
        const id = $('#pem_id').val();
        if (cfpUrls.lanes && id) { cfpLoadLanes(id, tickets); return; }
        cfpFlatTickets(tickets);
    }
    function cfpLoadLanes(id, tickets) {
        $.get(cfpUrls.lanes.replace('__ID__', id)).done(function (r) {
            if (!r || !r.success) return cfpFlatTickets(tickets);
            cfpRenderLanes(r, tickets);
        }).fail(() => cfpFlatTickets(tickets));
    }
    function cfpRenderLanes(r, tickets) {
        const wrap = $('#pem_tickets_list').empty();
        const esc = v => $('<div>').text(v == null ? '' : String(v)).html();
        const tk = n => '৳' + (Number(n) || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
        const dm = d => d ? new Date(String(d).slice(0, 10) + 'T00:00').toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) : '';
        const icon = { issued: 'fa-ticket', new: 'fa-plus', reissue: 'fa-arrows-rotate', refund: 'fa-rotate-left', other: 'fa-circle-info' };
        if (r.move) {
            wrap.append('<div class="cfl-move"><i class="fas fa-plane-departure"></i><div><b>His first ticket now flies on ' + esc(r.move.day_label) + '</b>'
                + '<small>He is still on ' + esc(r.move.flight) + ' (' + esc(r.move.flight_day) + '). He belongs on the flight his first ticket refers to.</small></div>'
                + (cfpUrls.reassign ? '<button type="button" class="cfl-btn cfl-move-btn" onclick="cfpMoveToLeg1Day()">Move him</button>' : '') + '</div>');
            wrap.data('cflMove', r.move);
        }
        // Re-assign from here too (owner, 2026-10-10) — the row's own button, same data, same dialog.
        if (cfpReassignButtonFor($('#pem_id').val())) {
            wrap.append('<div style="display:flex;justify-content:flex-end;margin-bottom:8px"><button type="button" class="cfl-btn t" onclick="cfpReassignFromEditModal()"><i class="fas fa-plane-circle-check"></i> Re-assign</button></div>');
        }
        // Ticket N departs before ticket N−1: he would be stranded at the transit.
        (r.clashes || []).forEach(function (c) {
            wrap.append('<div class="cfl-move" style="background:#fef2f2;border-color:#fecaca;color:#991b1b"><i class="fas fa-triangle-exclamation" style="color:#dc2626"></i><div>'
                + '<b>Ticket ' + c.lane + ' (' + esc(c.pnr) + ') flies ' + esc(c.day) + ' — before ticket ' + c.prev_lane + ' (' + esc(c.prev_pnr) + ', ' + esc(c.prev_day) + ')</b>'
                + '<small>He would miss the connection. Re-issue ticket ' + c.lane + ' to ' + esc(c.prev_day) + ' or later.</small></div></div>');
        });
        (r.lanes || []).forEach(function (lane) {
            const live = lane.live;
            const ev = (lane.events || []).map(e => '<li class="t-' + e.type + '"><i class="fas ' + (icon[e.type] || 'fa-circle') + '"></i>'
                + '<span class="cfl-what"><b>' + esc(e.label) + '</b> ' + esc(e.pnr || '') + (e.travel ? ' · flies ' + esc(dm(e.travel)) : '') + '</span>'
                + '<span class="cfl-when">' + esc(dm(e.at)) + (e.amount ? ' · ' + tk(e.amount) : '')
                + (e.undo_id && cfpUrls.undoChange ? ' <button type="button" class="cfl-btn g" style="padding:1px 7px;font-size:10.5px" title="Entered by mistake? Adds reversing rows — nothing is deleted" onclick="cfpUndoChange(' + e.undo_id + ', ' + JSON.stringify(String(e.label) + ' ' + String(e.pnr || '')).replace(/"/g, '&quot;') + ')"><i class="fas fa-rotate-left"></i> Undo</button>' : '')
                + (e.refund_edit && cfpUrls.correctRefund ? ' <button type="button" class="cfl-btn g" style="padding:1px 7px;font-size:10.5px" title="The airline refunded a different amount" onclick="cfpCorrectRefund(' + JSON.stringify(Object.assign({ pnr: e.pnr }, e.refund_edit)).replace(/"/g, '&quot;') + ')"><i class="fas fa-pen"></i> Edit refund</button>' : '') + '</span>'
                + (e.note && e.type !== 'issued' && e.type !== 'new' ? '<small>' + esc(String(e.note).slice(0, 160)) + '</small>' : '') + '</li>').join('');
            const pnrArg = live ? JSON.stringify(live.pnr || '').replace(/"/g, '&quot;') : '';
            // Cancel return (2026-10-14): a family passenger's ticket with a return on it — never his
            // outbound ones (a return the lanes cannot see is marked from Ticket Orders → Change).
            const ret = live && live.ret && live.ret.family && live.ret.shape ? live.ret : null;
            const retArg = ret ? JSON.stringify({ id: live.id, pnr: live.pnr, amount: live.amount, ret: ret, package: r.package }).replace(/"/g, '&quot;') : '';
            const retActs = ret && !ret.done
                ? (ret.eligible && cfpUrls.cancelReturn ? '<button type="button" class="cfl-btn r" title="He stays on — cancel the unused return" onclick="cfpCancelReturn(' + retArg + ')"><i class="fas fa-plane-arrival"></i> Cancel return</button>' : '')
                  + (cfpUrls.returnWindow ? '<button type="button" class="cfl-btn g" title="Return window — the airline\'s deadline to cancel it" onclick="cfpReturnWindow(' + retArg + ')"><i class="fas fa-clock"></i></button>' : '')
                : '';
            const acts = live
                ? (cfpTicketAdjustmentUrlTpl ? '<button type="button" class="cfl-btn t" onclick="cfpAdjustTicket(' + live.id + ', null, null, ' + (Number(live.amount) || 0) + ', \'reissue\', \'' + (live.travel || '') + '\', ' + pnrArg + ')"><i class="fas fa-arrows-rotate"></i> Re-Issue</button>'
                    + '<button type="button" class="cfl-btn r" onclick="cfpAdjustTicket(' + live.id + ', null, null, ' + (Number(live.amount) || 0) + ', \'refund\', \'' + (live.travel || '') + '\', ' + pnrArg + ')"><i class="fas fa-rotate-left"></i> Refund</button>' : '')
                  + retActs
                  + '<button type="button" class="cfl-btn g" onclick="unlinkPassengerTicket(' + live.id + ')">Unlink</button>'
                : (lane.covered_by || lane.is_return) ? ''
                : '<button type="button" class="cfl-btn n" onclick="cfpLaneNewTicket(' + lane.n + ', ' + JSON.stringify(lane.sector || '').replace(/"/g, '&quot;') + ', ' + JSON.stringify(lane.refunded || null).replace(/"/g, '&quot;') + ')"><i class="fas fa-plus"></i> New ticket</button>';
            wrap.append('<div class="cfl-lane' + (lane.n === 1 ? ' main' : '') + '">'
                + '<div class="cfl-hd"><span class="cfl-n">' + lane.n + '</span><b>' + esc(lane.sector || lane.label) + '</b>'
                + (lane.n === 1 ? '<em>decides his flight</em>' : '') + (lane.is_return ? '<em style="color:#b91c1c;background:#fef2f2">return · cancelled</em>' : '') + '</div>'
                + '<div class="cfl-live">' + (live
                    ? '<span class="cfl-pnr">' + esc(live.pnr) + '</span><span>flies <b>' + esc(dm(live.travel)) + '</b></span><span>' + tk(live.amount) + '</span>' + (live.seller ? '<span class="cfl-s">' + esc(live.seller) + '</span>' : '')
                      + (live.ret_badge ? '<span class="cfl-ret ' + (live.ret.done ? 'done' : esc(live.ret.tone)) + '"><i class="fas ' + (live.ret.done ? 'fa-plane-arrival' : 'fa-clock') + '"></i> ' + esc(live.ret_badge) + '</span>' : '')
                    : lane.is_return
                        ? '<span class="cfl-none" style="color:#64748b">Return cancelled — he stays on, no ticket needed</span>'
                    : lane.covered_by
                        ? '<span class="cfl-none" style="color:#047857">Covered by <b>' + esc(lane.covered_by) + '</b> — one through ticket for this leg too</span>'
                    : (lane.hold
                        ? '<span class="cfl-pnr" style="background:#e0f2fe;color:#075985">' + esc(lane.hold.pnr || lane.hold.reference || 'Hold') + '</span><span>' + esc(lane.hold.status === 'booked' ? 'Booked (hold)' : lane.hold.status) + (lane.hold.expires_at ? ' · till <b>' + esc(lane.hold.expires_at) + '</b>' : '') + '</span><span class="cfl-s">not purchased yet — confirm it in Ticketing</span>'
                        : '<span class="cfl-none">No live ticket — refunded or not issued yet</span>'))
                + '<span class="cfl-acts">' + acts + '</span></div>'
                + (ev ? '<ol class="cfl-ev">' + ev + '</ol>' : '') + '</div>');
        });
        if (!(r.lanes || []).length) cfpFlatTickets(tickets);
    }
    // A lane without a live ticket: issue it in Ticketing (or import its PDF), then link it here.
    // A refunded lane: pick his new PNR, see new − old, choose who bears the
    // difference, optional our fee — one press links it and posts (laneNewTicket()).
    window.cfpLaneNewTicket = function (n, sector, refunded) {
        const id = $('#pem_id').val();
        if (refunded && cfpUrls.laneNewTicket && id) return cfpLaneNewTicketDialog(id, n, sector, refunded);
        $('#pem_ticket_search').val('').trigger('focus');
        Swal.fire({ toast: true, position: 'top-end', icon: 'info', timer: 4500, showConfirmButton: false,
            title: 'Lane ' + n + (sector ? ' (' + sector + ')' : '') + ': search the new PNR below and Link it — or issue it in Ticketing first.' });
    };
    // The Re-assign button of this passenger's own row (it carries everything the dialog needs).
    function cfpReassignButtonFor(id) {
        if (!id || !window.cfpOpenReassign) return null;
        return [...document.querySelectorAll('button[onclick*="cfpOpenReassign"][data-cfp]')].find(b => {
            try { return String(JSON.parse(b.getAttribute('data-cfp')).id) === String(id); } catch (e) { return false; }
        }) || null;
    }
    window.cfpReassignFromEditModal = function () {
        const btn = cfpReassignButtonFor($('#pem_id').val());
        if (btn) window.cfpOpenReassign(btn);
    };
    // Undo a wrong Re-Issue / New ticket (2026-10-10): reversing rows beside the originals.
    window.cfpUndoChange = function (changeId, what) {
        const id = $('#pem_id').val();
        if (!id || !cfpUrls.undoChange) return;
        Swal.fire({
            title: 'Undo this entry?',
            html: '<div style="text-align:left;font-size:13px"><b>' + $('<div>').text(what).html() + '</b><div style="margin:8px 0;color:#475569">Every amount it posted gets a reversing line beside it (seller, agent, Company Ledger), and a date it moved goes back. Nothing is deleted — the entry stays in the history marked Undone.</div>'
                + '<input id="cfuNote" class="swal2-input" style="margin:0;width:100%;height:34px;font-size:13px" placeholder="Why (optional) — e.g. entered on the wrong ticket"></div>',
            showCancelButton: true, confirmButtonText: 'Undo it', confirmButtonColor: '#b91c1c',
            preConfirm: () => $.post(cfpUrls.undoChange.replace('__ID__', id), { change_id: changeId, note: $('#cfuNote').val() || '' })
                .then(r => { if (!r.success) { Swal.showValidationMessage(r.message || 'Could not undo.'); return false; } return r; })
                .catch(r => { Swal.showValidationMessage(r.responseJSON?.message || 'Could not undo.'); return false; }),
        }).then(r => { if (r.isConfirmed) Swal.fire({ icon: 'success', title: 'Undone', text: r.value.message, timer: 2200, showConfirmButton: false }).then(() => location.reload()); });
    };
    // Refund posted with the amount typed at the time; the airline confirms another
    // (owner, 2026-10-10: "editable later"). Adds correcting rows — nothing is changed in place.
    window.cfpCorrectRefund = function (x) {
        const id = $('#pem_id').val();
        if (!id || !cfpUrls.correctRefund) return;
        const tk = v => '৳' + (Number(v) || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
        const esc = v => $('<div>').text(v == null ? '' : String(v)).html();
        Swal.fire({
            title: 'Correct refund — ' + esc(x.pnr || ''),
            width: 520,
            html: '<div style="text-align:left;font-size:13px">'
                + '<div style="margin-bottom:8px;color:#475569">Ticket cost ' + tk(x.cost) + ' · refund recorded ' + tk(x.refund) + ' · airline kept ' + tk(x.cost - x.refund) + '</div>'
                + '<div style="display:grid;grid-template-columns:150px 1fr;gap:8px;align-items:center">'
                + '<span>Actual refund</span><input id="cfrAmt" type="number" min="0" max="' + x.cost + '" step="0.01" class="swal2-input" style="margin:0;height:34px;font-size:13px" value="' + x.refund + '">'
                + '<span id="cfrBLbl">Penalty borne by</span><span class="cfr-sw" id="cfrBSw"><button type="button" class="cfr-sw-b" data-b="agent">Agent pays</button><button type="button" class="cfr-sw-b" data-b="company">We bear it</button></span>'
                + '<span>Note</span><input id="cfrNote" type="text" class="swal2-input" style="margin:0;height:34px;font-size:13px" placeholder="e.g. airline confirmed on 15 Oct">'
                + '</div><div id="cfrSum" style="margin-top:10px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc"></div></div>',
            showCancelButton: true, confirmButtonText: 'Post correction', confirmButtonColor: '#0f766e',
            didOpen: () => {
                // The bearer is asked only when there was no penalty before and now there is.
                const paint = () => {
                    const r = Math.min(Number($('#cfrAmt').val()) || 0, x.cost), pen = x.cost - r, was = x.cost - x.refund;
                    $('#cfrBLbl, #cfrBSw').toggle(!x.fault && pen > 0);
                    $('#cfrSum').html('Airline keeps <b>' + tk(pen) + '</b> (was ' + tk(was) + ')'
                        + (Math.abs(pen - was) > 0.004 ? ' — ' + (pen > was ? 'adds ' : 'returns ') + tk(Math.abs(pen - was)) + (x.fault ? ' to ' + (x.fault === 'agent' ? 'the agent\'s bill' : 'our cost') : '') : ''));
                };
                $(document).on('input.cfr', '#cfrAmt', paint);
                $(document).on('click.cfr', '#cfrBSw .cfr-sw-b', function () { $('#cfrBSw .cfr-sw-b').removeClass('on'); $(this).addClass('on'); });
                paint();
            },
            willClose: () => $(document).off('input.cfr click.cfr'),
            preConfirm: () => {
                const amt = $('#cfrAmt').val();
                if (amt === '' || Number(amt) < 0) { Swal.showValidationMessage('Enter the refund the airline gave.'); return false; }
                return $.post(cfpUrls.correctRefund.replace('__ID__', id), {
                    change_id: x.change_id, ticket_purchase_id: x.ticket_id, refund: amt, bearer: $('#cfrBSw .cfr-sw-b.on').data('b') || '', note: $('#cfrNote').val() || '',
                }).then(r => { if (!r.success) { Swal.showValidationMessage(r.message || 'Could not save.'); return false; } return r; })
                  .catch(r => { Swal.showValidationMessage(r.responseJSON?.message || 'Could not save.'); return false; });
            },
        }).then(r => { if (r.isConfirmed) Swal.fire({ icon: 'success', title: 'Corrected', text: r.value.message, timer: 2200, showConfirmButton: false }).then(() => location.reload()); });
    };
    // ── Cancel return (owner, 2026-10-14) ────────────────────────────────────
    // A family passenger flew out and stays on: his unused return is cancelled inside
    // the airline's window. Round trip = only its return half (the ticket stays live);
    // a separate return ticket = the whole ticket. Refund, the airline's penalty and
    // who bears it, our charge, and his package kept or revised — posted by
    // ContractFlightPassengerController::cancelReturn, every row ADDED.
    const crtTk = v => '৳' + (Number(v) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const crtEsc = v => $('<div>').text(v == null ? '' : String(v)).html();
    function crtWindowLine(ret) {
        if (!ret || !ret.cancel_by) return '<div class="crt-win none"><i class="fas fa-clock"></i> No window entered — the airline\'s deadline is not on file.</div>';
        if (ret.tone === 'late') return '<div class="crt-win late"><i class="fas fa-triangle-exclamation"></i> The window closed on <b>' + crtEsc(ret.cancel_by_label) + '</b>. The airline may refuse the refund or keep more — check with the seller before you post.</div>';
        return '<div class="crt-win ' + ret.tone + '"><i class="fas fa-clock"></i> Cancel by <b>' + crtEsc(ret.cancel_by_label) + '</b> · '
            + (ret.days_left === 0 ? 'last day' : ret.days_left + ' day' + (ret.days_left === 1 ? '' : 's') + ' left') + '</div>';
    }
    window.cfpCancelReturn = function (x) {
        const id = $('#pem_id').val();
        if (!id || !cfpUrls.cancelReturn || !x || !x.ret) return;
        const round = x.ret.shape === 'round_trip', cost = Number(x.amount) || 0, pkg = Number(x.package) || 0;
        Swal.fire({
            title: 'Cancel return — ' + crtEsc(x.pnr || ''),
            width: 640, showCloseButton: true, allowOutsideClick: false, allowEscapeKey: false,
            html: '<div class="crt">'
                + '<div class="crt-hd"><span class="cfl-pnr">' + crtEsc(x.pnr || '') + '</span> · ticket ' + crtTk(cost) + ' · '
                + (round ? '<b>Round trip</b> — only its return half is cancelled; the ticket stays live for the way he flew'
                         : '<b>Separate return ticket</b> — the whole ticket is cancelled') + '</div>'
                + crtWindowLine(x.ret)
                + '<div class="crt-g">'
                + '<span>Airline refund</span><input id="crtRefund" type="number" min="0" max="' + cost + '" step="0.01" class="crt-in" placeholder="৳ 0.00">'
                + '<span>Airline penalty</span><input id="crtPenalty" type="number" min="0" step="0.01" class="crt-in" ' + (round ? 'placeholder="what the airline keeps for the return"' : 'max="' + cost + '" title="Ticket cost − refund — type either, the other follows"') + '>'
                + '<span id="crtBLbl">Penalty borne by</span><span class="cfr-sw" id="crtBSw"><button type="button" class="cfr-sw-b" data-b="agent">Agent pays</button><button type="button" class="cfr-sw-b" data-b="company">We bear it</button></span>'
                + '<span>Our charge</span><input id="crtFee" type="number" min="0" step="0.01" class="crt-in" placeholder="0 — billed to the agent">'
                + '<span>Agent\'s total</span><span class="crt-m" id="crtMode"><button type="button" data-m="keep" class="on">Keep ' + crtTk(pkg) + '</button><button type="button" data-m="total">New total</button><button type="button" data-m="giveback">Give back</button></span>'
                + '<span id="crtVLbl"></span><input id="crtVal" type="number" min="0" step="0.01" class="crt-in">'
                + '<span>Note</span><input id="crtNote" type="text" class="crt-in" placeholder="optional — e.g. staying on with his family">'
                + '</div><div id="crtSum" class="crt-sum"></div></div>',
            showCancelButton: true, confirmButtonText: 'Cancel return & post', cancelButtonText: 'Close', confirmButtonColor: '#b91c1c',
            didOpen: () => {
                const mode = () => $('#crtMode .on').data('m') || 'keep';
                const paint = () => {
                    const r = Math.min(Math.max(Number($('#crtRefund').val()) || 0, 0), cost);
                    // a separate return ticket: penalty = cost − refund (not while the penalty is being typed)
                    if (!round && document.activeElement !== $('#crtPenalty')[0]) $('#crtPenalty').val($('#crtRefund').val() === '' ? '' : (cost - r).toFixed(2));
                    const pen = round ? (Number($('#crtPenalty').val()) || 0) : cost - r;
                    const fee = Number($('#crtFee').val()) || 0, by = $('#crtBSw .on').data('b') || '';
                    const m = mode(), val = Number($('#crtVal').val()) || 0;
                    $('#crtBLbl, #crtBSw').toggle(pen > 0.004);
                    $('#crtVLbl, #crtVal').toggle(m !== 'keep');
                    $('#crtVLbl').text(m === 'total' ? 'New total' : 'Give back');
                    const after = m === 'total' ? val : (m === 'giveback' ? pkg - val : pkg);
                    const his = (by === 'agent' ? pen : 0) + fee;
                    const lines = [];
                    lines.push('Seller gives back <b>' + crtTk(r) + '</b>' + (round ? ' for the return half — the ticket stays live' : ' — the ticket is cancelled in Ticketing'));
                    if (round && r + pen > cost + 0.004) lines.push('<span class="crt-bad">Refund + penalty (' + crtTk(r + pen) + ') is more than the ticket (' + crtTk(cost) + ').</span>');
                    if (pen > 0.004) lines.push('Airline keeps <b>' + crtTk(pen) + '</b> — ' + (by === 'agent' ? 'billed to the agent' : by === 'company' ? 'we bear it (Company Ledger)' : '<span class="crt-bad">choose who bears it</span>'));
                    if (his > 0.004) lines.push('Agent\'s statement: <b>one line ' + crtTk(his) + '</b> — Return cancellation penalty' + (by === 'agent' && pen > 0 && fee > 0 ? ' (penalty ' + crtTk(pen) + ' + our charge ' + crtTk(fee) + ')' : ''));
                    lines.push(m === 'keep' ? 'Package stays <b>' + crtTk(pkg) + '</b>'
                        : (after < 0 || after >= pkg ? '<span class="crt-bad">' + (m === 'total' ? 'The new total must be below ' + crtTk(pkg) : 'Give back more than 0 and at most ' + crtTk(pkg)) + '</span>'
                            : 'Package ' + crtTk(pkg) + ' → <b>' + crtTk(after) + '</b> — ' + crtTk(pkg - after) + ' credited to the agent as its own line'));
                    $('#crtSum').html(lines.map(l => '<div>' + l + '</div>').join(''));
                };
                // separate return ticket (owner, 2026-10-15: "both should be calculated automatically"):
                // type the penalty and the refund follows — registered before paint, which reads it
                if (!round) $(document).on('input.crt', '#crtPenalty', function () {
                    if (this.value === '') { $('#crtRefund').val(''); return; }
                    const pen = Math.min(Math.max(Number(this.value) || 0, 0), cost);
                    $('#crtRefund').val((cost - pen).toFixed(2));
                });
                $(document).on('input.crt', '#crtRefund, #crtPenalty, #crtFee, #crtVal', paint);
                $(document).on('click.crt', '#crtBSw .cfr-sw-b', function () { $('#crtBSw .cfr-sw-b').removeClass('on'); $(this).addClass('on'); paint(); });
                $(document).on('click.crt', '#crtMode button', function () { $('#crtMode button').removeClass('on'); $(this).addClass('on'); $('#crtVal').val(''); paint(); });
                paint();
            },
            willClose: () => $(document).off('input.crt click.crt'),
            preConfirm: () => {
                const refund = $('#crtRefund').val();
                if (refund === '' || Number(refund) < 0 || Number(refund) > cost) { Swal.showValidationMessage('Enter the airline refund — between 0 and the ticket cost.'); return false; }
                const pen = round ? (Number($('#crtPenalty').val()) || 0) : cost - Number(refund);
                const by = $('#crtBSw .on').data('b') || '';
                if (pen > 0.004 && !by) { Swal.showValidationMessage('Choose who bears the airline penalty.'); return false; }
                const m = $('#crtMode .on').data('m') || 'keep';
                if (m !== 'keep' && $('#crtVal').val() === '') { Swal.showValidationMessage(m === 'total' ? 'Type his new total.' : 'Type the amount to give back.'); return false; }
                return $.post(cfpUrls.cancelReturn.replace('__ID__', id), {
                    ticket_purchase_id: x.id, refund: refund, penalty: round ? pen : '', bearer: pen > 0.004 ? by : '',
                    fee: $('#crtFee').val() || 0, agent_mode: m, agent_value: m === 'keep' ? '' : $('#crtVal').val(), note: $('#crtNote').val() || '',
                }).then(r => { if (!r.success) { Swal.showValidationMessage(r.message || 'Could not post.'); return false; } return r; })
                  .catch(r => { Swal.showValidationMessage(r.responseJSON?.message || Object.values(r.responseJSON?.errors || {})[0]?.[0] || 'Could not post.'); return false; });
            },
        }).then(r => { if (r.isConfirmed) Swal.fire({ icon: 'success', title: 'Return cancelled', text: r.value.message, timer: 2200, showConfirmButton: false }).then(() => location.reload()); });
    };
    // The airline's window: "the return can be cancelled until …" — reminders 3 days and 1 day
    // before, and on the day, go to whoever issued the ticket. No money moves.
    window.cfpReturnWindow = function (x) {
        if (!cfpUrls.returnWindow || !x) return;
        const ret = x.ret || {}, canTick = ret.shape !== 'round_trip';
        Swal.fire({
            title: 'Return window — ' + crtEsc(x.pnr || ''),
            width: 520, showCloseButton: true, allowOutsideClick: false,
            html: '<div class="crt">'
                + '<div class="crt-g">'
                + '<span>Cancel by</span><input id="crwBy" type="text" data-dt class="crt-in" placeholder="date, time optional" value="' + crtEsc(ret.cancel_by ? String(ret.cancel_by).replace('T', ' ') : '') + '">'
                + (canTick ? '<span></span><label class="crt-tick"><input id="crwRet" type="checkbox"' + (ret.flagged || ret.shape === 'return_ticket' ? ' checked' : '') + '> This ticket is his return</label>' : '')
                + '</div><div class="crt-sum" style="margin-top:10px">Reminders go to whoever issued the ticket — 3 days before, 1 day before, and on the day. Clear the date to stop them.</div></div>',
            showCancelButton: true, confirmButtonText: 'Save window', cancelButtonText: 'Close', confirmButtonColor: '#0f766e',
            preConfirm: () => {
                const payload = { cancel_by: $('#crwBy').val() || '' };
                if (canTick) payload.family = $('#crwRet').is(':checked') ? 1 : 0;
                return $.post(cfpUrls.returnWindow.replace('__TID__', x.id), payload)
                    .then(r => { if (!r.success) { Swal.showValidationMessage(r.message || 'Could not save.'); return false; } return r; })
                    .catch(r => { Swal.showValidationMessage(r.responseJSON?.message || Object.values(r.responseJSON?.errors || {})[0]?.[0] || 'Could not save.'); return false; });
            },
        }).then(r => { if (r.isConfirmed) { Swal.fire({ icon: 'success', title: 'Saved', text: r.value.message, timer: 1800, showConfirmButton: false }); cfpLoadLanes($('#pem_id').val(), []); } });
    };
    function cfpLaneNewTicketDialog(id, n, sector, old) {
        const esc = v => $('<div>').text(v == null ? '' : String(v)).html();
        const tk = v => '৳' + (Number(v) || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
        $.get(cfpTicketSearchUrlTpl.replace('__ID__', id), { q: '' }).done(function (res) {
            const list = ((res && res.ticket_purchases) || []).filter(t => t.status !== 'cancelled' && String(t.id) !== String(old.id));
            const rows = list.map((t, i) => '<label class="cfl-opt"><input type="radio" name="cflNew" value="' + t.id + '" data-amt="' + (Number(t.amount) || 0) + '"' + (i === 0 ? ' checked' : '') + '> <b>' + esc(t.ticket_no || '#' + t.id) + '</b> · ' + tk(t.amount) + (t.status ? ' · ' + esc(t.status) : '') + '</label>').join('');
            Swal.fire({
                title: 'Lane ' + n + ' — new ticket after refund',
                width: 560,
                html: '<div style="text-align:left;font-size:13px">'
                    + '<div style="margin-bottom:8px;color:#475569">Refunded: <b>' + esc(old.pnr || '#' + old.id) + '</b> · ' + tk(old.amount) + (sector ? ' · ' + esc(sector) : '') + '</div>'
                    + (rows || '<div style="color:#b45309;margin-bottom:6px">No unlinked live ticket for him yet — issue it in Ticketing (or import its PDF) first, then come back.</div>')
                    + '<div id="cflDiff" style="margin:10px 0;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc"></div>'
                    + '<div style="display:grid;grid-template-columns:150px 1fr;gap:8px;align-items:center">'
                    + '<span id="cflBearerLbl">Fare difference</span><span class="cfr-sw" id="cflBearerSw"><button type="button" class="cfr-sw-b" data-b="agent"></button><button type="button" class="cfr-sw-b" data-b="company"></button>@if(\App\Models\ContractFlightPassengerChange::splitFareAvailable())<button type="button" class="cfr-sw-b" data-b="split">Split</button>@endif</span>'
                    + '<span class="cflSplit" style="display:none">Agent bears</span><input id="cflAgentFare" type="number" min="0" step="0.01" class="swal2-input cflSplit" style="display:none;margin:0;height:34px;font-size:13px;color:#dc2626">'
                    + '<span class="cflSplit" style="display:none">We bear</span><span id="cflOurFare" class="cflSplit" style="display:none;color:#b45309"></span>'
                    + '<span>Our fee (agent)</span><input id="cflFee type="number" min="0" step="0.01" class="swal2-input" style="margin:0;height:34px;font-size:13px" placeholder="0">'
                    + '<span>Note</span><input id="cflNote" type="text" class="swal2-input" style="margin:0;height:34px;font-size:13px">'
                    + '</div></div>',
                showCancelButton: true, showLoaderOnConfirm: true, allowOutsideClick: () => !Swal.isLoading(), confirmButtonText: 'Link & post', confirmButtonColor: '#0f766e',
                didOpen: () => {
                    const paint = () => {
                        const r = document.querySelector('input[name="cflNew"]:checked');
                        const sd = r ? Number(r.dataset.amt) - Number(old.amount) : 0;
                        $('#cflDiff').html(!r ? 'Pick his new ticket above.'
                            : 'New ' + tk(r.dataset.amt) + ' − old ' + tk(old.amount) + ' = '
                              + (sd < 0 ? '<b style="color:#047857">cheaper by ' + tk(-sd) + '</b>' : '<b>' + tk(sd) + '</b>' + (sd > 0 ? '' : ' — same fare, nothing to bear')));
                        // Dearer: who pays the difference. Cheaper (owner, 2026-10-10): who takes the saving.
                        const cheaper = sd < 0;
                        $('#cflBearerLbl').text(cheaper ? 'Saving goes to' : 'Fare difference');
                        $('#cflBearerSw [data-b="agent"]').text(cheaper ? 'Agent gets it' : 'Agent pays');
                        $('#cflBearerSw [data-b="company"]').text(cheaper ? 'We keep it' : 'We bear it');
                        $('#cflBearerLbl, #cflBearerSw').toggle(!!r && sd !== 0);
                        $('#cflBearerSw [data-b="split"]').toggle(sd > 0);
                        if (sd <= 0) $('#cflBearerSw [data-b="split"]').removeClass('on');
                        $('.cflSplit').toggle(sd > 0 && $('#cflBearerSw .on').data('b') === 'split');
                        $('#cflAgentFare').attr('max', Math.max(0, sd));
                        $('#cflOurFare').text(tk(sd - (Number($('#cflAgentFare').val()) || 0)));
                    };
                    $(document).on('change.cfl', 'input[name="cflNew"]', paint);
                    // Nothing pre-selected: whose side is always picked by staff.
                    $(document).on('click.cfl', '#cflBearerSw .cfr-sw-b', function () {
                        $('#cflBearerSw .cfr-sw-b').removeClass('on'); $(this).addClass('on'); paint();
                    });
                    $(document).on('input.cfl', '#cflAgentFare', paint);
                    paint();
                },
                willClose: () => $(document).off('change.cfl click.cfl input.cfl'),
                preConfirm: () => {
                    const r = document.querySelector('input[name="cflNew"]:checked');
                    if (!r) { Swal.showValidationMessage('Pick his new ticket.'); return false; }
                    const sd = Number(r.dataset.amt) - Number(old.amount);
                    const bearer = $('#cflBearerSw .cfr-sw-b.on').data('b') || (sd === 0 ? 'company' : '');
                    if (!bearer) { Swal.showValidationMessage(sd < 0 ? 'Choose who takes the saving.' : 'Choose who bears the fare difference.'); return false; }
                    const share = Number($('#cflAgentFare').val());
                    if (bearer === 'split' && ($('#cflAgentFare').val() === '' || !Number.isFinite(share) || share < 0 || share > sd)) {
                        Swal.showValidationMessage('Enter the agent share between zero and the fare difference.'); return false;
                    }
                    return $.post(cfpUrls.laneNewTicket.replace('__ID__', id), {
                        old_ticket_purchase_id: old.id, ticket_purchase_id: r.value, fare_bearer: bearer === 'split' ? 'agent' : bearer,
                        ...(bearer === 'split' ? { agent_fare_amount: share } : {}),
                        charge: $('#cflFee').val() || 0, note: $('#cflNote').val() || '',
                    }).then(x => { if (!x.success) { Swal.showValidationMessage(x.message || 'Could not save.'); return false; } return x; })
                      .catch(x => { Swal.showValidationMessage(x.responseJSON?.message || 'Could not save.'); return false; });
                },
            }).then(r => { if (r.isConfirmed) Swal.fire({ icon: 'success', title: 'Saved', text: r.value.message, timer: 2200, showConfirmButton: false }).then(() => location.reload()); });
        });
    }
    // Lane 1 moved to another day: put him on the flight of that day (asked, never automatic).
    window.cfpMoveToLeg1Day = function () {
        const id = $('#pem_id').val(), mv = $('#pem_tickets_list').data('cflMove');
        if (!id || !mv || !cfpUrls.reassignFlights || !cfpUrls.reassign) return;
        $.get(cfpUrls.reassignFlights.replace('__ID__', id)).done(function (res) {
            const all = (res && res.flights) || [];
            const same = all.filter(f => f.departure_at && String(f.departure_at).slice(0, 10) === mv.day && (!res.source_route_key || f.route_key === res.source_route_key));
            const esc = v => $('<div>').text(v == null ? '' : String(v)).html();
            const opts = same.map((f, i) => '<label class="cfl-opt"><input type="radio" name="cflTo" value="' + f.id + '"' + (i === 0 ? ' checked' : '') + '> <b>' + esc(f.flight_number) + '</b> · ' + esc(f.departure_label || '') + ' · ' + (f.passengers || 0) + ' pax</label>').join('');
            Swal.fire({
                title: 'Move him to ' + mv.day_label + '?',
                html: '<div style="text-align:left;font-size:13px">' + (opts || '<div style="color:#64748b;margin-bottom:6px">No flight on ' + esc(mv.day_label) + ' on his route yet.</div>')
                    + '<label class="cfl-opt"><input type="radio" name="cflTo" value="new"' + (same.length ? '' : ' checked') + '> <b>New flight</b> on ' + esc(mv.day_label) + ' (next CF number, his route)</label>'
                    + '<div style="font-size:11.5px;color:#64748b;margin-top:8px">A move only — no charge, no ticket touched. His tickets and their history stay as they are.</div></div>',
                showCancelButton: true, confirmButtonText: 'Move him', cancelButtonText: 'Keep him on ' + mv.flight, confirmButtonColor: '#0f766e',
                preConfirm: () => {
                    const to = (document.querySelector('input[name="cflTo"]:checked') || {}).value;
                    if (!to) { Swal.showValidationMessage('Choose a flight.'); return false; }
                    const payload = { _method: 'PATCH', kind: 'reschedule', contract_flight_id: to, note: 'First ticket now flies ' + mv.day };
                    if (to === 'new') payload.new_flight = { ticket_id: mv.ticket_id, departure_at: mv.day };
                    return $.post(cfpUrls.reassign.replace('__ID__', id), payload)
                        .then(x => { if (!x.success) { Swal.showValidationMessage(x.message || 'Could not move him.'); return false; } return x; })
                        .catch(x => { Swal.showValidationMessage(x.responseJSON?.message || 'Could not move him.'); return false; });
                },
            }).then(r => { if (r.isConfirmed) { Swal.fire({ icon: 'success', title: 'Moved', text: r.value.message || 'He is on the new flight.', timer: 1800, showConfirmButton: false }).then(() => location.reload()); } });
        });
    };
    function cfpFlatTickets(tickets) {
        const wrap = $('#pem_tickets_list').empty();
        if (!tickets.length) {
            wrap.append('<div style="font-size:12px;color:#9ca3af;">No ticket linked yet.</div>');
            return;
        }
        tickets.forEach(function (t) {
            const tDate = (t.legs && t.legs[0] && t.legs[0].travel_date) ? String(t.legs[0].travel_date).slice(0, 10) : '';
            wrap.append(
                '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;font-size:12.5px;">' +
                '<span style="flex:1;"><b>' + (t.ticket_no || '—') + '</b> · ' + (t.status || '') + (t.amount ? ' · BDT ' + Number(t.amount).toLocaleString() : '') + '</span>' +
                (cfpTicketAdjustmentUrlTpl && t.status !== 'cancelled' ? '<button type="button" class="fcm-btn fcm-btn-ghost" style="padding:2px 8px;font-size:11px;color:#0f766e;" onclick="cfpAdjustTicket(' + t.id + ', null, null, ' + (Number(t.amount) || 0) + ', \'reissue\', \'' + tDate + '\', ' + JSON.stringify(t.ticket_no || '').replace(/"/g, '&quot;') + ')">Re-Issue</button>' : '') +
                (cfpTicketAdjustmentUrlTpl && t.status !== 'cancelled' ? '<button type="button" class="fcm-btn fcm-btn-ghost" style="padding:2px 8px;font-size:11px;color:#dc2626;" onclick="cfpAdjustTicket(' + t.id + ', null, null, ' + (Number(t.amount) || 0) + ', \'refund\', \'' + tDate + '\', ' + JSON.stringify(t.ticket_no || '').replace(/"/g, '&quot;') + ')">Refund</button>' : '') +
                '<button type="button" style="color:#dc2626;font-size:11px;" onclick="unlinkPassengerTicket(' + t.id + ')">Unlink</button>' +
                '</div>'
            );
        });
    }

    // ── Re-Issue / Refund on one ticket, same flight (from live 2026-09-29) ──
    // Money posts OUR way (2026-09-30), see ContractFlightPassengerController::
    // ticketAdjustment(): a reissue is a fee the ticket's vendor/portal
    // charges us (Dr 5173 / Cr payable) and, when the agent pays, recovered
    // from him (Dr 1311 / Cr 4183); a refund is Ticketing's own cancel + the
    // airline's penalty to the Company Ledger. Each save ADDS rows, never
    // edits — so the POST sits inside preConfirm to block a double submit.
    function cfpAdjustTicket(ticketPurchaseId, passengerId, builderNode, originalCost, type, currentTravelDate, pnr) {
        const id = passengerId || $('#pem_id').val();
        if (!id || !cfpTicketAdjustmentUrlTpl) return;
        const orig = Number(originalCost) || 0;
        const curDate = currentTravelDate ? String(currentTravelDate).slice(0, 10) : '';
        const isRefund = type === 'refund';
        const F = 'width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:7px 9px;font-size:13px;';
        const L = 'display:block;font-size:11.5px;color:#64748b;margin-bottom:3px;';
        const tk = n => '৳' + (Number(n) || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
        Swal.fire({
            title: (isRefund ? 'Refund ' : 'Re-Issue ') + (pnr || 'this ticket'),
            html: `<div style="text-align:left;font-size:13px;">
                ${isRefund ? `
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                    <div><label style="${L}">Ticket cost</label><input type="number" value="${orig.toFixed(2)}" readonly style="${F}background:#f9fafb;color:#6b7280;"></div>
                    <div><label style="${L}">Airline refund</label><input id="cfaRefund" type="number" min="0" max="${orig}" step="0.01" placeholder="৳ 0.00" style="${F}border-color:#bbf7d0;"></div>
                    <div style="grid-column:1 / -1;"><label style="${L}">Airline penalty (the loss) <span style="color:#9ca3af;">— cost minus refund</span></label>
                        <input id="cfaLoss" type="number" readonly style="${F}background:#fef2f2;color:#dc2626;font-weight:700;"></div>
                </div>
                <div style="font-size:11.5px;color:#7f1d1d;background:#fff7f7;border:1px solid #fecaca;border-radius:8px;padding:6px 9px;margin-bottom:8px;">
                    The ticket is cancelled in Ticketing (kept at its amount, refund posted beside it). The penalty goes to the Company Ledger, and is billed to the agent if it was his side.</div>` : `
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                    <div><label style="${L}">Main fare</label><input type="number" value="${orig.toFixed(2)}" readonly style="${F}background:#f9fafb;color:#6b7280;"></div>
                    <div><label style="${L}">New total</label><input id="cfaNewTotal" type="number" readonly style="${F}background:#f0fdf4;color:#15803d;font-weight:700;"></div>
                    <div><label style="${L}">Airline re-issue penalty</label><input id="cfaPenalty" type="number" min="0" step="0.01" placeholder="৳ 0.00" style="${F}color:#dc2626;font-weight:700;"></div>
                    <div><label style="${L}">Fare difference (new − old)</label><input id="cfaFareDiff" type="number" min="0" step="0.01" placeholder="৳ 0.00" style="${F}color:#dc2626;font-weight:700;"></div>
                    <div style="grid-column:1 / -1;"><label style="${L}">New travel date <span style="color:#9ca3af;">— change it only if this reissue moves the date${curDate ? ' (now ' + curDate + ')' : ''}</span></label>
                        <input id="cfaTravelDate" type="date" value="${curDate}" data-original="${curDate}" style="${F}"></div>
                </div>
                <div style="font-size:11.5px;color:#134e4a;background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;padding:6px 9px;margin-bottom:8px;">
                    Same PNR. The fee is owed to this ticket's vendor / portal and shows in the Company Ledger; if the agent pays, it is billed to him and recovered.</div>`}
                <label style="${L}">${isRefund ? 'Whose side caused it?' : 'Who pays?'}</label>
                <select id="cfaBearer" style="${F}margin-bottom:8px;">
                    <option value="">— choose —</option>
                    <option value="agent">${isRefund ? 'Passenger / agent side — bill the agent' : 'Agent pays'}</option>
                    <option value="company">${isRefund ? 'Our side — we bear it' : 'We bear it'}</option>
                </select>
                <label style="${L}">Note (optional)</label>
                <textarea id="cfaNote" rows="2" style="${F}"></textarea>
            </div>`,
            showCancelButton: true,
            confirmButtonText: isRefund ? 'Save refund' : 'Save re-issue',
            confirmButtonColor: isRefund ? '#dc2626' : '#0f766e',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                if (isRefund) {
                    const r = document.getElementById('cfaRefund'), loss = document.getElementById('cfaLoss');
                    const calc = () => { loss.value = (orig - Math.min(Math.max(Number(r.value) || 0, 0), orig)).toFixed(2); };
                    r.addEventListener('input', calc); calc();
                } else {
                    const p = document.getElementById('cfaPenalty'), fd = document.getElementById('cfaFareDiff'), tot = document.getElementById('cfaNewTotal');
                    const calc = () => { tot.value = (orig + (Number(p.value) || 0) + (Number(fd.value) || 0)).toFixed(2); };
                    p.addEventListener('input', calc); fd.addEventListener('input', calc); calc();
                }
            },
            preConfirm: () => {
                // Never pre-selected (owner): staff choose who bears it every time.
                if (!document.getElementById('cfaBearer').value) { Swal.showValidationMessage(isRefund ? 'Choose whose side caused it.' : 'Choose who pays.'); return false; }
                const payload = { ticket_purchase_id: ticketPurchaseId, type: isRefund ? 'refund' : 'reissue',
                    bearer: document.getElementById('cfaBearer').value, note: document.getElementById('cfaNote').value || null };
                if (isRefund) {
                    const refund = Number(document.getElementById('cfaRefund').value);
                    if (document.getElementById('cfaRefund').value === '' || refund < 0 || refund > orig) { Swal.showValidationMessage('Enter the airline refund — between 0 and the ticket cost.'); return false; }
                    payload.refund = refund;
                } else {
                    const penalty = Number(document.getElementById('cfaPenalty').value) || 0;
                    const fareDiff = Number(document.getElementById('cfaFareDiff').value) || 0;
                    if (penalty <= 0 && fareDiff <= 0) { Swal.showValidationMessage('Enter the airline re-issue penalty or a fare difference.'); return false; }
                    payload.penalty = penalty;
                    payload.fare_diff = fareDiff;
                    const d = document.getElementById('cfaTravelDate');
                    payload.new_travel_date = d.value && d.value !== d.dataset.original ? d.value : null;
                }
                return $.post(cfpTicketAdjustmentUrlTpl.replace('__ID__', id), payload)
                    .then(r => { if (!r.success) { Swal.showValidationMessage(r.message || 'Something went wrong.'); return false; } return r; })
                    .catch(xhr => { Swal.showValidationMessage(xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {})[0]?.[0] || 'Something went wrong.'); return false; });
            },
        }).then(result => {
            if (!result.isConfirmed || !result.value) return;
            const p = result.value.passenger || {};
            if (String($('#pem_id').val()) === String(id)) {
                if (typeof updatePassengerCache === 'function') updatePassengerCache(p);
                if (typeof renderPassengerTickets === 'function') renderPassengerTickets(p.ticket_purchases || []);
                if (typeof window.renderFlightChanges === 'function') window.renderFlightChanges(p.flight_changes || []);
            }
            if (builderNode && builderNode.length) {
                builderNode.data('pbFlightChanges', p.flight_changes || [])
                    .data('penBilled', Number(p.penalty_billed) || 0).data('penBorne', Number(p.penalty_borne) || 0);
                pbRenderLinked(builderNode, p.ticket_purchases || []);
                pbLoadHolderTickets(builderNode, builderNode.find('.pb-pnr-search').val());
                pbRefresh(pbPrefixOf(builderNode));
            }
            // Leg 1 moved to another day: the flight card follows leg 1, so
            // say so plainly (a notice, never a block — owner, 2026-10-04).
            if (result.value.notice && cfpUrls.lanes && cfpUrls.reassign && String($('#pem_id').val()) === String(id)) {
                // lane 1 moved: ask now whether he moves to the flight of that day
                $.get(cfpUrls.lanes.replace('__ID__', id)).done(function (lr) {
                    if (lr && lr.success) cfpRenderLanes(lr, p.ticket_purchases || []);
                    if (lr && lr.move) cfpMoveToLeg1Day(); else Swal.fire({ icon: 'info', title: 'Saved and posted', html: result.value.notice });
                });
            } else if (result.value.notice) {
                Swal.fire({ icon: 'info', title: 'Saved and posted', html: result.value.notice, confirmButtonText: 'OK' });
            } else {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', timer: 2500, showConfirmButton: false, title: result.value.message || 'Saved and posted' });
            }
        });
    }

    function searchPassengerTickets() {
        const id = $('#pem_id').val();
        const q = $('#pem_ticket_search').val();
        $.get(cfpTicketSearchUrlTpl.replace('__ID__', id), { q: q }).done(function (response) {
            const wrap = $('#pem_ticket_results').empty();
            if (!response.success || !response.ticket_purchases.length) {
                wrap.append('<div style="font-size:12px;color:#9ca3af;">No matching ticket purchase found for this passenger.</div>');
                return;
            }
            response.ticket_purchases.forEach(function (t) {
                wrap.append(
                    '<div style="display:flex;align-items:center;gap:8px;border:1px dashed #d1d5db;border-radius:6px;padding:6px 10px;font-size:12.5px;">' +
                    '<span style="flex:1;">' + (t.ticket_no || '—') + ' · ' + (t.status || '') + (t.amount ? ' · BDT ' + Number(t.amount).toLocaleString() : '') + '</span>' +
                    '<button type="button" class="fcm-btn fcm-btn-save" style="padding:3px 10px;font-size:11px;" onclick="linkPassengerTicket(' + t.id + ')">Link</button>' +
                    '</div>'
                );
            });
        });
    }

    function linkPassengerTicket(ticketPurchaseId) {
        const id = $('#pem_id').val();
        $.post(cfpLinkTicketUrlTpl.replace('__ID__', id), { ticket_purchase_id: ticketPurchaseId }).done(function (response) {
            if (!response.success) return;
            updatePassengerCache(response.passenger);
            renderPassengerTickets(response.passenger.ticket_purchases || []);
            pemTicketTotal = (response.passenger.ticket_purchases || []).reduce((sum, t) => sum + (Number(t.amount) || 0), 0);
            updateTotalTravelCost();
            $('#pem_ticket_results').empty();
            $('#pem_ticket_search').val('');
        }).fail(function (xhr) {
            $('#pem_error').removeClass('hidden').text(xhr.responseJSON?.message || 'Unable to link ticket.');
        });
    }

    function unlinkPassengerTicket(ticketPurchaseId) {
        const id = $('#pem_id').val();
        $.ajax({ url: cfpUnlinkTicketUrlTpl.replace('__ID__', id).replace('__TID__', ticketPurchaseId), method: 'DELETE' })
            .done(function (response) {
                if (!response.success) return;
                updatePassengerCache(response.passenger);
                renderPassengerTickets(response.passenger.ticket_purchases || []);
                pemTicketTotal = (response.passenger.ticket_purchases || []).reduce((sum, t) => sum + (Number(t.amount) || 0), 0);
                updateTotalTravelCost();
            });
    }

    function searchMoveFlights() {
        const q = $('#pem_flight_search').val();
        const currentFlightId = cfpCurrentPassengers.find(item => item.id === Number($('#pem_id').val()))?.contract_flight_id;
        $.get(cfpSearchFlightsUrl, { q: q, exclude: currentFlightId || '' }).done(function (response) {
            const wrap = $('#pem_flight_results').empty();
            if (!response.success || !response.flights.length) {
                wrap.append('<div style="font-size:12px;color:#9ca3af;">No other flight matches that number.</div>');
                return;
            }
            response.flights.forEach(function (f) {
                const when = f.departure_at ? new Date(f.departure_at).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) : 'Date pending';
                wrap.append(
                    '<div style="display:flex;align-items:center;gap:8px;border:1px dashed #d1d5db;border-radius:6px;padding:6px 10px;font-size:12.5px;">' +
                    '<span style="flex:1;"><b>' + (f.flight_number || '—') + '</b>' + (f.route ? ' · ' + f.route : '') + ' · ' + when + '</span>' +
                    '<button type="button" class="fcm-btn fcm-btn-save" style="padding:3px 10px;font-size:11px;" onclick="moveToFlight(' + f.id + ', \'' + (f.flight_number || '').replace(/'/g, "\\'") + '\')">Move here</button>' +
                    '</div>'
                );
            });
        });
    }

    // A move changes which flight this passenger belongs to, so they drop
    // out of whatever flight-scoped list is currently open — the safest way
    // to reflect that everywhere (the list page, the View Flight modal) is
    // the same full reload savePassengerEdit() already does after an edit
    // opened from the list.
    function moveToFlight(flightId, flightNumber) {
        const id = $('#pem_id').val();
        Swal.fire({
            icon: 'warning',
            title: 'Move this passenger?',
            html: 'Move to <b>' + $('<div>').text(flightNumber).html() + '</b>? The agent charge, reissue billing and service costs already posted stay exactly as they are — only the flight batch changes.',
            showCancelButton: true,
            confirmButtonText: 'Move',
            confirmButtonColor: '#b45309',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: cfpMoveFlightUrlTpl.replace('__ID__', id),
                method: 'PATCH',
                data: { contract_flight_id: flightId },
            }).done(function (response) {
                if (!response.success) {
                    $('#pem_error').removeClass('hidden').text(response.message || 'Unable to move this passenger.');
                    return;
                }
                Swal.fire({ icon: 'success', title: 'Moved', text: 'Passenger moved to ' + flightNumber + '.', timer: 1600, showConfirmButton: false })
                    .then(function () { window.location.reload(); });
            }).fail(function (xhr) {
                $('#pem_error').removeClass('hidden').text(xhr.responseJSON?.message || 'Unable to move this passenger.');
            });
        });
    }

    function updatePassengerCache(passenger) {
        const idx = cfpCurrentPassengers.findIndex(item => item.id === passenger.id);
        if (idx > -1) cfpCurrentPassengers[idx] = passenger;
        renderFlightPassengers();
    }

    // Flags that cost money (2026-09-28): raising one asks, before saving,
    // what the airline refunds on his ticket and whose side caused it —
    // cfpMoneyEventDialog (partials.reassign-script). Absent (feature not
    // installed) the save goes through exactly as before.
    const CFP_MONEY_FLAGS = ['flight_cancelled', 'flight_missed', 'boarding_denied', 'immigration_offload', 'deported', 'deported_we_bear'];

    function savePassengerEdit() {
        const id = Number($('#pem_id').val());
        const flag = $('#pem_exception_flag').val() || null;
        const cached = (typeof cfpCurrentPassengers !== 'undefined' ? cfpCurrentPassengers : []).find(x => x.id === id);
        if (flag && CFP_MONEY_FLAGS.includes(flag) && cached && cached.exception_flag !== flag && typeof window.cfpMoneyEventDialog === 'function') {
            window.cfpMoneyEventDialog(cached, flag, $('#pem_exception_flag option:selected').text()).then(ev => {
                if (ev) doSavePassengerEdit(ev);
            });
            return;
        }
        doSavePassengerEdit(null);
    }

    function doSavePassengerEdit(moneyEvent) {
        const id = $('#pem_id').val();
        const button = $('#pem_save').prop('disabled', true);
        $('#pem_error').addClass('hidden');
        $.ajax({
            url: cfpUpdateUrlTpl.replace('__ID__', id),
            method: 'PATCH',
            data: {
                stage: $('#pem_stage').val(),
                ticket_class: $('#pem_ticket_class').val(),
                flight_category_id: $('#pem_flight_category_id').val() || null,
                exception_flag: $('#pem_exception_flag').val() || null,
                contract_confirmed: $('#pem_contract_confirmed').is(':checked') ? 1 : 0,
                has_bmet: $('#pem_has_bmet').is(':checked') ? 1 : 0,
                has_manpower_card: $('#pem_has_manpower_card').is(':checked') ? 1 : 0,
                boarding_training: $('#pem_boarding_training').is(':checked') ? 1 : 0,
                manpower_card_fee: $('#pem_manpower_card_fee').val() || null,
                agent_id: $('#pem_agent_id').val() || null,
                agent_price: $('#pem_agent_price').val() || null,
                agent_paid_amount: $('#pem_agent_paid_amount').val() || 0,
                manpower_cost: $('#pem_manpower_cost').val() || null,
                boarding_cost: $('#pem_boarding_cost').val() || null,
                immigration_cost: $('#pem_immigration_cost').val() || null,
                reissue_cost: $('#pem_reissue_cost').val() || null,
                reissue_bearer: $('#pem_reissue_bearer').val() || null,
                contact_phone: $('#pem_contact_phone').val() || null,
                tickets_required: parseInt($('#pem_tickets_required').val(), 10) || 1,
                remarks: $('#pem_remarks').val() || null,
                journey_event: $('#pem_journey_event').val() || null,
                journey_event_at: $('#pem_journey_event_at').val() || null,
                journey_event_note: $('#pem_journey_event_note').val() || null,
                money_event: moneyEvent,
            },
        }).done(function (response) {
            if (!response.success) {
                $('#pem_error').removeClass('hidden').text(response.message || 'Unable to save.');
                return;
            }
            updatePassengerCache(response.passenger);
            closePassengerEditModal();
            if (pemOpenedFromList) { pemOpenedFromList = false; window.location.reload(); }
        }).fail(function (xhr) {
            $('#pem_error').removeClass('hidden').text(xhr.responseJSON?.message || 'Unable to save.');
        }).always(function () {
            button.prop('disabled', false);
        });
    }

    $(function () {
        $(document).on('pointerdown', '.edit-btn', function () {
            hydratingFlight = true;
        });
        ['create', 'edit'].forEach(function (prefix) {
            const parent = $('#' + prefix + 'Modal');
            $('#' + prefix + '_pricing_airline_id, #' + prefix + '_boarding_officer_id, #' + prefix + '_immigration_officer_id')
                .select2({ dropdownParent: parent, width: '100%' });
            // The old multi-select of passport holders is gone — passengers
            // are built one card at a time now (passenger-builder-script),
            // because each one carries their own ticket and their own costs.
            $('#' + prefix + '_agent_ids').select2({
                dropdownParent: parent,
                width: '100%',
                placeholder: 'Select agents…',
                closeOnSelect: false
            });
            $('#' + prefix + '_vendor_id').select2({ dropdownParent: parent, width: '100%', placeholder: 'Select vendor…', allowClear: true });
            $('#' + prefix + '_portal_id').select2({ dropdownParent: parent, width: '100%', placeholder: 'Select portal…', allowClear: true });
        });

        // Passenger edit modal — never wired to select2 when first built, so
        // Agent (a long list) rendered as a plain unstyled native <select>.
        $('#pem_stage, #pem_ticket_class, #pem_flight_category_id, #pem_exception_flag, #pem_journey_event, #pem_reissue_bearer').select2({ dropdownParent: $('#passengerEditOverlay'), width: '100%' });
        $('#pem_agent_id').select2({ dropdownParent: $('#passengerEditOverlay'), width: '100%', placeholder: 'Select agent...', allowClear: true });

        $(document).on('change', '.ticket-route-select', function () {
            const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
            const airlineId = $(this).find(':selected').data('airline-id');
            if (airlineId) $('#' + prefix + '_pricing_airline_id').val(String(airlineId)).trigger('change');
        });

        $('.create-btn').on('click', function () {
            hydratingFlight = true;
            $('#create_pricing_airline_id, #create_boarding_officer_id, #create_immigration_officer_id').val(null).trigger('change');
            hydratingFlight = false;
        });

        $(document).on('click', '.edit-btn', function () {
            const flight = mfFlights.find(item => String(item.id) === String($(this).data('item_id')));
            if (!flight) return;
            hydratingFlight = true;
            $('#edit_pricing_airline_id').val(String(flight.ticket?.airline_id || flight.airline?.id || '')).trigger('change');
            $('#edit_boarding_officer_id').val(flight.boarding_officer_id || '').trigger('change');
            $('#edit_immigration_officer_id').val(flight.immigration_officer_id || '').trigger('change');
            $('#edit_arrival_at').val(dateTimeLocalValue(flight.arrival_at));
            // Class, category, service mix and rates are not hydrated here:
            // they are per passenger now, and passenger-builder-script fetches
            // them from the server rather than reading mfFlights — the Flight
            // Schedule screen's copy carries no ticket links, and hydrating
            // from it would drop every PNR.
            hydratingFlight = false;
        });
    });

    function dateTimeLocalValue(value) {
        if (!value) return '';
        const date = new Date(value);
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        return date.toISOString().slice(0, 16);
    }

    function filterFlightResources(prefix) {
        // Airline metadata is informational; selecting a route updates the pricing airline.
        $('#' + prefix + '_ticket_id, #' + prefix + '_boarding_officer_id, #' + prefix + '_immigration_officer_id')
            .find('option')
            .prop('disabled', false)
            .end()
            .trigger('change.select2');
    }

    function validateFlightForm(prefix) {
        let isValid = true;
        const form = prefix === 'edit' ? $('#editeForm') : $('#createForm');
        form.find('.error-message').addClass('hidden');

        // Nothing on the flight is mandatory: date, route, flight number,
        // category and price are filled in as the batch progresses (the date
        // only exists once a ticket does) and each one moves the passenger's
        // stage forward on its own.
        const requiredFields = [];

        requiredFields.forEach(function (field) {
            const value = $('#' + prefix + '_' + field[0]).val();
            if (value === null || String(value).trim() === '') {
                $('#' + prefix + '_' + field[0] + '_msg').removeClass('hidden').text(field[1]);
                isValid = false;
            }
        });

        // (No flight-level officer check: the responsible person is chosen per
        // passenger, and may come later — the Review step lists what is missing.)

        const departure = $('#' + prefix + '_departure_at').val();
        const arrival = $('#' + prefix + '_arrival_at').val();
        // Either may be a date alone (time is optional): then only the day is compared.
        const beforeDeparture = (arrival.length < 16 || departure.length < 16)
            ? arrival.slice(0, 10) < departure.slice(0, 10)
            : arrival.replace('T', ' ') < departure.replace('T', ' ');
        if (departure && arrival && beforeDeparture) {
            $('#' + prefix + '_arrival_at_msg').removeClass('hidden');
            isValid = false;
        }

        // Seat counts, category, class, service mix and pricing are no longer
        // typed on the flight — the passenger cards carry all of them — so
        // there is nothing left here to contradict.

        // The passenger cards check themselves (a holder and a category on
        // every card, no duplicate, an agent price). Kept as a separate call
        // so the builder owns its own rules.
        if (typeof window.pbValidate === 'function' && !window.pbValidate(prefix)) {
            isValid = false;
        }

        // An error on a step that is not on screen is an error nobody can see,
        // so open the first step that has one and badge the rest.
        if (typeof window.fmRevealErrors === 'function') {
            window.fmRevealErrors(prefix);
        }

        return isValid;
    }

    function openFlightPassengerModal(targetId) {
        flightPassengerTarget = targetId;
        $('#flightPassengerName, #flightPassengerPassport, #flightPassengerPhone').val('');
        $('#flightPassengerNationality').val('Bangladeshi');   // default nationality (owner, 2026-09-21)
        $('#flightPassengerCategory').val('');
        $('#flightPassengerType').val('contract_flight');
        $('#flightPassengerError').hide();
        $('#flightPassengerOverlay').addClass('open');
    }
    function closeFlightPassengerModal() {
        $('#flightPassengerOverlay').removeClass('open');
        flightPassengerTarget = null;
    }
    function saveFlightPassenger() {
        const button = $('#flightPassengerSave').prop('disabled', true);
        $('#flightPassengerError').hide();
        $.ajax({
            url: flightPassengerStoreUrl,
            method: 'POST',
            headers: { Accept: 'application/json' },
            data: {
                name: $('#flightPassengerName').val(),
                passport_no: $('#flightPassengerPassport').val(),
                nationality: $('#flightPassengerNationality').val(),
                phone: $('#flightPassengerPhone').val(),
                category_id: $('#flightPassengerCategory').val(),
                type: $('#flightPassengerType').val(),
                status: 1
            }
        }).done(function (response) {
            if (!response.success) {
                $('#flightPassengerError').text(response.message || 'Unable to save passenger.').show();
                return;
            }
            const item = response.data;
            $('.passenger-select').each(function () {
                if (!$(this).find('option[value="' + item.id + '"]').length) {
                    $(this).append(new Option(item.name + ' - ' + item.passport_no, item.id));
                }
            });
            if (flightPassengerTarget) {
                const select = $('#' + flightPassengerTarget);
                select.val((select.val() || []).concat(String(item.id))).trigger('change');
            }
            // The builder's holder dropdowns are built from a JS list rather
            // than server-rendered options, so they are told separately — and
            // the new person drops straight into the card that asked for them.
            if (typeof window.pbRegisterHolder === 'function') window.pbRegisterHolder(item);
            closeFlightPassengerModal();
        }).fail(function (xhr) {
            $('#flightPassengerError').text(xhr.responseJSON?.message || 'Unable to save passenger.').show();
        }).always(function () {
            button.prop('disabled', false);
        });
    }
</script>

{{-- The per-passenger builder. Included from here (rather than from either
     index view) so both the Manage Flight and Flight Schedule screens pick it
     up automatically — this partial is already inside their @section('js'),
     after jQuery, select2 and SweetAlert2 have loaded. --}}
@include('contract-flights.flight-modal-steps-script')
@include('contract-flights.passenger-builder-script')
@include('contract-flights.partials.who-pays-switch'){{-- Agent pays / We bear it pill for every who-bears select (2026-10-08) --}}
@include('partials.datetime-picker'){{-- date + optional time on operational date boxes (2026-09-30) --}}
