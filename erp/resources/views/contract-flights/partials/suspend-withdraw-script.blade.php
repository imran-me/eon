{{-- Suspend withdraw (owner, 2026-11-02): the Re-assign dialog's fourth card. Its own state, its own
     endpoint — reassign-script only calls the four window.cfsw* hooks. Emitted only when available(). --}}
@if(\App\Models\ContractFlightSuspendWithdrawal::available())
<script>
(function () {
    const URL = @json(route('role.contract-flight-passengers.suspend-withdraw', ['role' => $role, 'contractFlightPassenger' => '__ID__']));
    const esc = s => $('<div>').text(s == null ? '' : String(s)).html();
    const taka = v => '৳' + (Number(v) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let p = null;

    const active = () => $('input[name="cfr_kind"]:checked').val() === 'suspend_withdraw';
    window.cfswActive = active;

    // Re-assign opened: the card only for someone on a flight holding a live ticket.
    window.cfswOpen = function (cfr) {
        p = cfr;
        const can = !!(cfr && cfr.on_flight && (cfr.held || []).length);
        $('#cfr_kind_sw').toggle(can);
        $('#cfr_kinds_main').toggleClass('has-4', can && $('#cfr_kind_off').length > 0 && $('#cfr_kind_off')[0].style.display !== 'none'); // the dialog is still hidden here
        $('#cfsw_ticket').html((cfr.held || []).map(t => `<option value="${t.id}">${esc(t.no)}</option>`).join(''));
        // One card per ticket, Reschedule's layout (UI only). The fields are parked first so re-rendering
        // the cards never destroys them, then moved into the ticked ticket.
        $('#cfsw_park').append($('#cfsw_fields'));
        const flag = cc => cc ? `<img src="${@json(asset('flags'))}/${esc(String(cc).toLowerCase())}.png" alt="" onerror="this.remove()">` : '';
        $('#cfsw_tickets').html((cfr.held || []).map((t, i) => {
            const was = t.date ? new Date(t.date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : null;
            return `<div class="cfsw-card" data-id="${t.id}">
                <div class="cfr-tk"><span><span class="cfr-tk-route">${t.from || t.to ? `${flag(t.from_flag)}${esc(t.from || '—')} <i class="fas fa-plane"></i> ${flag(t.to_flag)}${esc(t.to || '—')}` : 'Route not recorded'}</span>`
                + `<span class="cfr-tk-tag">${esc(t.no)}</span>${was ? ` <small>was ${esc(was)}</small>` : ''}
                    <label class="cfsw-on"><input type="radio" name="cfsw_tk" value="${t.id}"${i === 0 ? ' checked' : ''}> Suspended — this leg</label></span></div>
                <div class="cfsw-cb"><div><span>Ticket cost</span><span>${taka(t.fare)}</span></div></div>
                <div class="cfsw-slot"></div>
                <div class="cfsw-ph wide"></div><div class="cfsw-ph"></div><div class="cfsw-ph"></div><div class="cfsw-ph who"></div>
            </div>`;
        }).join(''));
        placeFields();
        $('#cfsw_vendor, #cfsw_bearer, #cfsw_cost, #cfsw_charge, #cfsw_note, #cfsw_agent_amount').val('');
        $('#cfsw_bearer option[value="agent"], #cfsw_bearer option[value="split"]').prop('disabled', !cfr.agent);
        $('#cfsw_sec').hide();
    };

    // Kind changed: true = this card owns the dialog, the rest stays hidden.
    window.cfswApply = function () {
        const on = active();
        $('#cfsw_sec').toggle(on);
        if (!on) return false;
        $('#cfr_off_sec, #cfr_refund_sec, #cfr_flight_sec, #cfr_fare_sec, #cfr_stay_note').hide();
        $('#cfr_charge').closest('.cfr-sec').hide();
        $('#cfr_save').html('<i class="fas fa-unlock"></i> Post suspend withdraw');
        paint();
        return true;
    };
    // Leaving this card: give back the shared charge section and the Save label (the other kinds
    // only relabel it in some branches — Reschedule kept "Post suspend withdraw", 2026-11-02).
    $(document).on('change', 'input[name="cfr_kind"]', () => {
        if (active()) return;
        $('#cfr_charge').closest('.cfr-sec').show();
        if ($('#cfr_save').text().includes('suspend withdraw')) $('#cfr_save').html('<i class="fas fa-plane-circle-check"></i> Re-assign');
    });

    function figures() {
        const cost = Math.max(0, Number($('#cfsw_cost').val()) || 0);
        const charge = Math.max(0, Number($('#cfsw_charge').val()) || 0);
        const bearer = $('#cfsw_bearer').val();
        const agentCost = bearer === 'agent' ? cost : (bearer === 'split' ? Number($('#cfsw_agent_amount').val()) || 0 : 0);
        return { cost, charge, agentBears: agentCost > 0, agentCost, agentLine: agentCost + charge, ours: cost - agentCost };
    }
    function paint() {
        if (!active()) return;
        const f = figures(), ag = esc((p && p.agent) || 'the agent');
        $('#cfsw_split').toggle($('#cfsw_bearer').val() === 'split');
        $('#cfsw_agent_amount').attr('max', f.cost);
        $('#cfsw_ours').text('We bear ' + taka(Math.max(0, f.ours)));
        const vendor = $('#cfsw_vendor option:selected').val() ? esc($('#cfsw_vendor option:selected').text()) : 'the vendor';
        $('#cfsw_total').html(`<b>${vendor}</b>'s ledger <b style="color:#dc2626;">${taka(f.cost)}</b> · `
            + (f.agentLine > 0 ? `<b>${ag}</b>'s ONE line <b style="color:#dc2626;">${taka(f.agentLine)}</b>`
                + (f.agentBears ? ` (cost share ${taka(f.agentCost)} + our charge ${taka(f.charge)})` : ' (our charge)') : `nothing to ${ag}`)
            + ` · we bear <b>${taka(f.ours)}</b>`);
        $('#cfr_summary').html(`He stays on <b>${esc((p && p.from_flight) || 'his flight')}</b>; his ticket stays live. Nothing is written until you confirm.`);
    }
    window.cfswSummary = () => { if (active()) { paint(); return true; } return false; };
    $(document).on('input change', '#cfsw_cost, #cfsw_charge, #cfsw_bearer, #cfsw_vendor, #cfsw_agent_amount', paint);
    // The ticked ticket holds the fields; the others are dimmed, as an unticked leg in Reschedule.
    function placeFields() {
        const id = $('input[name="cfsw_tk"]:checked').val();
        $('#cfsw_ticket').val(id);
        $('.cfsw-card').each(function () {
            const on = String($(this).data('id')) === String(id);
            $(this).toggleClass('is-off', !on);
            if (on) $(this).find('.cfsw-slot').append($('#cfsw_fields'));
        });
        window.cfrSwPaint && window.cfrSwPaint();
    }
    $(document).on('change', 'input[name="cfsw_tk"]', placeFields);
    $(document).on('click', '.cfsw-card.is-off', function () { $(this).find('input[name="cfsw_tk"]').prop('checked', true).trigger('change'); });

    window.cfswSave = function () {
        if (!active()) return false;
        const err = $('#cfr_error').addClass('hidden').text('');
        const f = figures();
        const bad = !$('#cfsw_vendor').val() ? 'Choose the vendor who withdrew the suspension.'
            : f.cost <= 0 ? 'Enter the vendor\'s cost.'
            : !$('#cfsw_bearer').val() ? 'Choose who bears the cost.'
            : (f.agentLine > 0 && !(p && p.agent)) ? 'This passenger has no agent to bill.' : null;
        if (bad) { err.removeClass('hidden').text(bad); return true; }
        if ($('#cfsw_bearer').val() === 'split' && ($('#cfsw_agent_amount').val() === '' || !Number.isFinite(Number($('#cfsw_agent_amount').val())) || f.agentCost < 0 || f.agentCost > f.cost)) { err.removeClass('hidden').text('Enter the agent share between zero and the vendor cost.'); return true; }
        const data = {
            ticket_purchase_id: $('#cfsw_ticket').val() || '',
            vendor_id: $('#cfsw_vendor').val(),
            cost: f.cost,
            bearer: $('#cfsw_bearer').val(),
            agent_amount: $('#cfsw_bearer').val() === 'split' ? $('#cfsw_agent_amount').val() : null,
            our_charge: f.charge,
            note: $('#cfsw_note').val(),
        };
        const vendor = esc($('#cfsw_vendor option:selected').text()), ag = esc((p && p.agent) || 'agent');
        Swal.fire({
            title: 'Post suspend withdraw?',
            html: `<div style="text-align:left;font-size:13px;line-height:1.7;">
                <div><b>${vendor}</b> (vendor ledger): <b>${taka(f.cost)}</b></div>
                ${f.ours <= 0 ? '' : `<div>We bear: <b>${taka(f.ours)}</b> (Company Ledger)</div>`}
                ${f.agentLine > 0 ? `<div><b>${ag}</b> — ONE line: <b>${taka(f.agentLine)}</b>${f.agentBears && f.charge > 0 ? ` (cost share ${taka(f.agentCost)} + our charge ${taka(f.charge)})` : ''}</div>` : ''}
            </div>`,
            showCancelButton: true, confirmButtonText: 'Confirm & post', confirmButtonColor: '#b45309',
            showLoaderOnConfirm: true, allowOutsideClick: false,
            preConfirm: () => $.ajax({ url: URL.replace('__ID__', p.id), method: 'POST', data })
                .catch(xhr => { Swal.showValidationMessage((xhr.responseJSON && (xhr.responseJSON.message || Object.values(xhr.responseJSON.errors || {})[0]?.[0])) || 'Could not post.'); }),
        }).then(res => {
            if (!res.isConfirmed || !res.value) return;
            Swal.fire({ icon: 'success', title: 'Suspend withdraw posted', text: res.value.message, timer: 2400, showConfirmButton: false })
                .then(() => window.location.reload());
        });
        return true;
    };
})();
</script>
@endif
