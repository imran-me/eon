let FD_LEDGER_ID = null;
let FD_LEDGER_REQUEST = 0;
let FD_PASSENGERS = [];
let FD_LEDGER_TRIGGER = null;

function reloadPassengerLedger() {
    if (FD_LEDGER_ID === 'all') return openDashboardPassengers(false);
    if (String(FD_LEDGER_ID || '').startsWith('airline:')) return openAirlineLedger(String(FD_LEDGER_ID).slice(8), false);
    return openFlightLedger(FD_LEDGER_ID);
}

function ledgerMoney(value) {
    return `${Number(value) < 0 ? '−' : ''}৳${esc(fmtMoney(value))}`;
}
// A small grey glyph in front of a detail line's label.
function ledgerIcon(icon) {
    return icon ? `<i class="fas ${icon} fd-ico" aria-hidden="true"></i>` : '';
}
function ledgerDetail(label, value, icon = '') {
    return `<small>${ledgerIcon(icon)}${esc(label)}: ${esc(value ?? '—')}</small>`;
}
// Passenger and Agent cells, the tidy way (owner, 2026-10-03) — the JS twin
// of passenger-row.blade.php's: a bold name, then short grey lines with no
// "Label:" prefix (the tooltip names it); an empty value is not drawn.
function ledgerPassengerCells(p) {
    const passport = `<div class="mfp-ln mfp-mono" title="Passport">${ledgerIcon('fa-passport')}${esc(p.passport_no || '—')}</div>`;
    const catLine = [p.flight_category_name, p.handling_label].filter(Boolean);
    const passenger = (p.name
        ? `<a class="mfp-passenger mfp-name" href="${esc(FD_PASSPORT_URL)}?name=${encodeURIComponent(p.name)}" title="Open ${esc(p.name)}'s passport record">${esc(p.name)}</a>`
        : '<strong class="mfp-name">Passenger not assigned</strong>')
        + passport
        + (catLine.length ? `<div class="mfp-ln" title="Category: ${esc(p.flight_category_name || '—')} · Handling: ${esc(p.handling_label || '—')}">${esc(catLine.join(' · '))}</div>` : '');
    const url = p.agent_id ? `${esc(FD_PARTY_URL)}?party_id=${encodeURIComponent(p.agent_id)}` : '';
    const cls = p.ticket_class_label || 'Economy';
    const agent = (p.agent_id
        ? `<a class="mfp-party mfp-name" href="${url}" title="Open ${esc(p.agent_name || 'agent')}'s statement"><i class="fas fa-user-tie mfp-agent-ico" aria-hidden="true"></i>${esc(p.agent_name || 'Agent')}</a>`
        : '<strong class="mfp-party mfp-name"><i class="fas fa-user-tie mfp-agent-ico" aria-hidden="true"></i>Direct</strong>')
        + `<div class="mfp-ln" title="${p.agent_id ? 'Agent ID · ' : ''}Class${p.flight_category_type ? ' · Type' : ''}">`
        + (p.agent_id ? `<a class="mfp-mono" href="${url}">EP-AG-${esc(String(p.agent_id).padStart(3, '0'))}</a><span class="mfp-tk-sep">·</span>` : '')
        + esc(cls) + (p.flight_category_type ? `<span class="mfp-tk-sep">·</span>${esc(p.flight_category_type)}` : '') + '</div>'
        + ledgerSellers(p);
    return [passenger, agent];
}
// Who sold each live ticket, once per seller, max two — twin of
// passenger-row's $mfpSellers. A vendor carries EP-VN-###; a portal has no code.
function ledgerSellers(p) {
    const seen = new Map();
    (p.ticket_purchases || []).filter(t => t.status !== 'cancelled')
        .slice().sort((a, b) => (a.leg_sequence || 99) - (b.leg_sequence || 99) || a.id - b.id)
        .forEach(t => {
            const key = t.vendor_id ? 'v' + t.vendor_id : (t.portal ? 'p' + t.portal : null);
            if (!key) return;
            const cur = seen.get(key) || { id: t.vendor_id || null, name: t.vendor_id ? (t.vendor || 'Vendor') : t.portal,
                code: t.vendor_id ? 'EP-VN-' + String(t.vendor_id).padStart(3, '0') : 'Portal', pnrs: [] };
            if (t.ticket_no) cur.pnrs.push(t.ticket_no);
            seen.set(key, cur);
        });
    const all = [...seen.values()];
    return all.slice(0, 2).map(v => `<div class="mfp-ln mfp-seller" title="Ticket seller${v.pnrs.length ? ' · ' + esc(v.pnrs.join(', ')) : ''}">${ledgerIcon('fa-store')}${v.id ? `<a class="mfp-seller-name" href="${esc(FD_PARTY_URL)}?party_id=${encodeURIComponent(v.id)}">${esc(v.name)}</a>` : `<span class="mfp-seller-name">${esc(v.name)}</span>`}<span class="mfp-seller-code">${esc(v.code)}</span></div>`).join('')
        + (all.length > 2 ? `<div class="mfp-ln" title="${esc(all.slice(2).map(v => v.name).join(', '))}">… +${all.length - 2} more</div>` : '');
}
// The PNR opens Ticketing filtered to that ticket number.
function ledgerPnr(ticketNo) {
    if (!ticketNo) return '<span class="fd-pnr">PNR pending</span>';
    return `<a class="fd-pnr" href="${esc(FD_TICKETS_URL)}?ticket_no=${encodeURIComponent(ticketNo)}" title="Open ${esc(ticketNo)} in Ticketing">${esc(ticketNo)}</a>`;
}
// A via-route passenger's tickets, two lines each — the JS twin of the
// Flights & Passengers cell (passenger-row.blade.php; styles in
// partials/ticket-legs-styles). Change one, change the other.
function ledgerTicketLegs(p, flight, tickets) {
    const slots = p.ticket_slots || [];
    const n = slots.length;
    const legDate = (raw, fallbackIso) => {
        if (raw) {
            const m = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
            if (!m) return null;
            return { d: new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0)), time: !!m[4] && (m[4] + m[5]) !== '0000' };
        }
        const d = parseIso(fallbackIso);
        return d ? { d, time: true } : null;
    };
    const fmt = (ld, short) => `${String(ld.d.getDate()).padStart(2, '0')} ${MONTHS[ld.d.getMonth()]}${!short || ld.d.getFullYear() !== new Date().getFullYear() ? ' ' + ld.d.getFullYear() : ''}${ld.time ? ', ' + fmtTime(ld.d) : ''}`;
    const state = s => {
        if (s.done) return ['confirm', 'purchased'];
        if (s.purchase_id) return [s.status || 'pending', s.status || 'pending'];
        const h = s.hold;
        if (h) return h.status === 'booked' ? ['hold', 'booked · till ' + (h.expires_at || '—')] : (h.status === 'expired' ? ['cancelled', 'hold expired'] : ['pending', h.status]);
        return ['', 'not ticketed'];
    };
    const legs = slots.map(s => {
        const [cls, txt] = state(s);
        const code = (String(s.airline || '').match(/\(([A-Z0-9]{2,3})\)\s*$/) || [])[1] || s.airline || '';
        const sector = s.from_code && s.to_code ? `${s.from_code} → ${s.to_code}` : (s.sector || `${s.label} route`);
        const ld = legDate(s.travel_date, s.index === 1 ? flight.departure_at : null);
        const tip = `Ticket ${s.index} of ${n} · ${txt} · ${sector}${s.airline ? ' · ' + s.airline : ''}${ld ? ' · ' + fmt(ld, false) : ''}`;
        let pnr;
        if (s.ticket_no) {
            const [, main, sfx] = String(s.ticket_no).match(/^(.*?)\s*(\([a-z]{1,2}\))$/) || [null, s.ticket_no, ''];
            pnr = `<a class="mfp-pnr" href="${esc(FD_TICKETS_URL)}?ticket_no=${encodeURIComponent(s.ticket_no)}" title="Open ${esc(s.ticket_no)} in Ticketing">${esc(main)}${sfx ? `<span class="mfp-tk-sfx">${esc(sfx)}</span>` : ''}</a>`;
        } else {
            pnr = `<span class="mfp-pnr">${esc(s.hold?.reference ? (s.hold.pnr || s.hold.reference) : 'No PNR yet')}</span>`;
        }
        const was = s.ticket_no ? (tickets.find(t => t.ticket_no === s.ticket_no) || {}).was_dates : null;
        return `<div class="mfp-tk${s.done ? ' done' : ' mfp-ticket-missing'}" title="${esc(tip)}">
            <span class="mfp-tk-dot">${s.done ? '<i class="fas fa-check"></i>' : ''}</span>
            <div class="mfp-tk-l1">${pnr}<span class="mfp-tk-air"${s.airline ? ` data-airline="${esc(s.airline)}" title="${esc(s.airline)}"` : ''}>${esc(code)}</span></div>
            <div class="mfp-tk-l2">${s.from_code && s.to_code ? `${esc(s.from_code)}<i class="fas fa-plane mfp-tk-plane"></i>${esc(s.to_code)}` : esc(sector)} ${ld ? `<span class="mfp-tk-sep">·</span><b>${esc(fmt(ld, true))}</b>` : ''}</div>
            ${s.done || txt === 'not ticketed' ? '' : `<div class="mfp-tk-l2"><span class="mfp-tk-tag ${esc(cls)}">${esc(txt)}</span></div>`}
            ${was ? `<div class="mfp-tk-l2 mfp-tk-was" title="Earlier date(s) of this ticket"><i class="fas fa-clock-rotate-left"></i> ${esc(was)}</div>` : ''}
        </div>`;
    }).join('');
    const cancelled = tickets.filter(t => t.status === 'cancelled')
        .map(t => `<small class="fd-clip" title="Cancelled in Ticketing">${ledgerIcon('fa-ban')}Cancelled: ${esc(t.ticket_no || '#' + t.id)}</small>`).join('');
    return legs + cancelled;
}
// One ticket (a direct route): PNR, status, class / trip and its money — the
// twin of passenger-row's single-ticket branch.
function ledgerSingleTickets(tickets) {
    return tickets.map(t => {
        const cls = [t.ticket_class, t.trip_type].filter(Boolean).join(' · ') || '—';
        const money = `Cost: ${ledgerMoney(t.amount)} · Paid: ${ledgerMoney(t.paid_amount)} · Due: ${ledgerMoney(t.due_amount)}`;
        return `<div class="mfp-ticket">${t.ticket_no
                ? `<a class="mfp-pnr" href="${esc(FD_TICKETS_URL)}?ticket_no=${encodeURIComponent(t.ticket_no)}" title="Open ${esc(t.ticket_no)} in Ticketing">${esc(t.ticket_no)}</a>`
                : '<span class="mfp-pnr">PNR pending</span>'}
            <span class="mfp-status${t.status === 'cancelled' ? ' cancelled' : (t.status === 'confirm' ? ' confirm' : '')}">${esc(t.status || 'pending')}</span>
            ${t.was_dates ? `<small class="mfp-clip" style="color:#94a3b8;" title="Earlier date(s) of this ticket"><i class="fas fa-clock-rotate-left mfp-ico"></i>${esc(t.was_dates)}</small>` : ''}
            <small class="mfp-clip" title="Class / Trip: ${esc(cls)}"><i class="fas fa-chair mfp-ico"></i>Class / Trip: ${esc(cls)}</small>
            <small class="mfp-clip" title="${money}"><i class="fas fa-coins mfp-ico"></i>Cost: <span class="mfp-cost">${ledgerMoney(t.amount)}</span> · Paid: <span class="mfp-clear">${ledgerMoney(t.paid_amount)}</span> · Due: <span class="mfp-due">${ledgerMoney(t.due_amount)}</span></small>
        </div>`;
    }).join('') || '<small>No tickets linked</small>';
}
// Charge & Cost Breakdown in one cell — the twin of passenger-row's .mfp-cb:
// the agent's charge with Paid / Due on top, each cost line under a rule.
function ledgerChargeCost(p, ticketCost) {
    const billed = Number(p.penalty_billed) || 0;
    const costs = [['Tickets', ticketCost, 'fa-ticket'], ['Extra baggage', p.baggage_cost, 'fa-suitcase'], ['Manpower', p.manpower_cost, 'fa-users'], ['Boarding', p.boarding_cost, 'fa-plane-up'],
        ['Immigration', p.immigration_cost, 'fa-stamp'], ['Card fee', p.manpower_card_fee, 'fa-id-card'], [p.other_title || 'Other', p.other_cost, 'fa-receipt'],
        ['Reissue', p.reissue_cost, 'fa-rotate', 'Recorded separately — not part of travel cost'],
        ['Penalty', p.penalty_billed, 'fa-gavel', 'Cancellation / reissue / reschedule charge billed to the agent — in Agent Charge'],
        ['Penalty (ours)', p.penalty_borne, 'fa-gavel', 'Cancellation / reissue / reschedule charge we absorb — in the cost']]
        .filter(([, v]) => Number.isFinite(Number(v)) && Number(v) !== 0);
    const pen = label => label.startsWith('Penalty') ? ' mfp-due' : '';
    return `<div class="mfp-cb">
        <span class="k">Charge</span>
        <span class="v mfp-detail" title="Billed to the agent: package ${ledgerMoney(p.package_charged ?? p.agent_price)}${billed > 0 ? ' + penalty ' + ledgerMoney(billed) : ''}">${ledgerMoney(p.agent_total ?? p.agent_price)}</span>
        ${billed > 0 ? `<span class="k sub mfp-due">incl. penalty</span><span class="v sub mfp-due">${ledgerMoney(billed)}</span>` : ''}
        ${Number(p.baggage_charge) > 0 ? `<span class="k sub mfp-due">+ Extra baggage</span><span class="v sub mfp-due">${ledgerMoney(p.baggage_charge)}</span>` : ''}
        ${Number(p.agent_due) > 0
            ? `<span class="k sub mfp-due">Due</span><span class="v sub mfp-due" title="Still owed by the agent">${ledgerMoney(p.agent_due)}</span>`
            : '<span class="k sub mfp-clear">Paid</span><span class="v sub mfp-clear"><i class="fas fa-check"></i></span>'}
        <span class="rule"></span>
        ${costs.map(([label, v, icon, title]) => `<span class="k${pen(label)}" title="${esc(title || label)}"><i class="fas ${icon} mfp-ico"></i>${esc(label.replace(' (ours)', ''))}</span><span class="v${pen(label)}">− ${ledgerMoney(v)}</span>`).join('')
            || '<span class="k">No cost yet</span><span class="v">—</span>'}
    </div>`;
}
// Cost blue, paid green, due red — the same three the flights table uses.
function ledgerAmounts(t) {
    return `<small>${ledgerIcon('fa-coins')}Cost: <span class="fd-cost">${ledgerMoney(t.amount)}</span> · ` +
        `Paid: <span class="fd-clear">${ledgerMoney(t.paid_amount)}</span> · ` +
        `Due: <span class="fd-due">${ledgerMoney(t.due_amount)}</span></small>`;
}

// The passenger's route through operations. This deliberately uses the stage
// already returned by the passenger endpoint: it is a view of the authoritative
// pipeline, not a second status system maintained by the dashboard. The list
// itself is the server's (contract_flight_stages, editable via the pencil on
// the strip); each entry is [key, label, icon, conditional, system, id].
@php
    $fdStageTuples = array_map(fn ($s) => [$s['key'], $s['label'], $s['icon'], $s['is_conditional'], $s['is_system'], $s['id']], \App\Models\ContractFlightPassenger::stageRoute());
@endphp
let LEDGER_STAGE_ROUTE = @json(array_values($fdStageTuples));
const LEDGER_STAGE_ICONS = @json(\App\Models\ContractFlightStage::ICONS);
const FD_STAGE_SYNC_URL = @json(route('role.contract-flight-stages.sync', ['role' => $role]));

function ledgerPassengerRoute(p) {
    let current = LEDGER_STAGE_ROUTE.findIndex(([key]) => key === p.stage);
    if (current < 0) current = 0;
    const hasTransit = p.stage === 'transit' || p.exception_flag === 'transit' ||
        (p.journey_events || []).some(event => ['transit', 'transit_issue'].includes(event.code)) ||
        (p.ticket_purchases || []).some(ticket => (ticket.legs || []).length > 1);
    // A route with stops shows "Ticket Issued" once per leg — Ticket 1 ·
    // DAC → IST, Ticket 2 · IST → ECN — each green as its own PNR is
    // confirmed, before the passenger's stage itself moves on (owner,
    // 2026-09-21). One slot means the plain stage.
    const slots = (p.ticket_slots || []).length > 1 ? p.ticket_slots : null;
    // One stage, drawn as the journey card's (partials/journey-card-styles) —
    // the same card as Flights & Passengers' passenger-journey-row. The
    // ledger's fdl-stage-action class stays on the button for its own
    // save-in-place handler.
    const step = (key, label, icon, index, state, stateLabel, sub) => {
        const focus = Math.abs(index - current) <= 1 ? 'is-focus' : 'is-compact';
        const isCur = state.includes('is-current');
        const tip = `${label}\n${sub || stateLabel}${FD_CAN_EDIT_STAGE && !isCur ? '\nClick to set this stage' : ''}`;
        return `<li class="mj-step ${state} ${focus}" aria-current="${isCur ? 'step' : 'false'}">
            <button type="button" class="mj-stage fdl-stage-action" data-tip="${esc(tip)}" data-passenger="${esc(p.id)}" data-stage="${esc(key)}" ${FD_CAN_EDIT_STAGE ? '' : 'disabled'} aria-label="${esc(label)}: ${stateLabel}${FD_CAN_EDIT_STAGE ? '. Select stage' : ''}">
            <span class="mj-pin"><i class="fas ${state === 'is-done' ? 'fa-check' : esc(icon)}" aria-hidden="true"></i></span>
            <span class="mj-copy"><b>${esc(label)}</b><small>${sub ? esc(sub) : stateLabel}</small></span>
            </button>
        </li>`;
    };
    const route = LEDGER_STAGE_ROUTE.map(([key, label, icon, conditional], index) => {
        // A conditional stage that was passed without evidence it happened
        // reads "not required"; transit is the one with evidence to check.
        const skipped = conditional && current > index && !(key === 'transit' && hasTransit);
        const state = skipped ? 'is-skipped' : (index < current ? 'is-done' : (index === current ? 'is-current' : 'is-next'));
        const stateLabel = skipped ? 'Not required' : (index < current ? 'Completed' : (index === current ? 'Current stage' : 'Upcoming'));
        if (key === 'ticket_issued' && slots) {
            let currentMarked = false;
            return slots.map(slot => {
                const done = slot.done || index < current;
                const isCurrent = !done && index === current && !currentMarked;
                if (isCurrent) currentMarked = true;
                // Each ticket says where it stands in Ticket Orders: purchased
                // (green), on hold (blue, with the deadline), pending / draft
                // (grey), hold expired (red) — or nothing yet.
                const hold = !done && slot.hold ? slot.hold : null;
                const holdState = hold ? (hold.status === 'booked' ? 'is-hold' : (hold.status === 'expired' ? 'is-expired' : 'is-pending')) : null;
                const holdLabel = hold ? (hold.status === 'booked' ? 'Booked · hold till ' + (hold.expires_at || '—')
                    : (hold.status === 'expired' ? 'Hold expired · ' + (hold.reference || '') : (hold.status === 'pending' ? 'Pending' : 'Draft') + ' · ' + (hold.reference || ''))) : null;
                let s = done ? 'is-done' : (holdState ? holdState + (isCurrent ? ' is-current' : '') : (isCurrent ? 'is-current' : 'is-next'));
                const sl = done ? ('Purchased · ' + (slot.ticket_no || 'PNR')) : (holdLabel || (isCurrent ? 'Current stage' : 'Upcoming'));
                if (index === current && slot === slots[slots.length - 1] && slots.every(x => x.done)) s = 'is-current';
                const title = slots.length > 1 ? `${slot.label}${slot.sector ? ' · ' + slot.sector : ''}` : `${slot.label} Issued`;
                return step(key, title, icon, index, s, sl, sl);
            }).join('');
        }
        return step(key, label, icon, index, state, stateLabel, null);
    }).join('');
    // The boarding-pass stub, as on Flights & Passengers: % ring · now (+ ✎
    // for the shared stage editor) · next · flag.
    const pct = Math.round(current / Math.max(1, LEDGER_STAGE_ROUTE.length - 1) * 100);
    const next = LEDGER_STAGE_ROUTE[current + 1]?.[1];
    const edit = FD_CAN_EDIT_STAGE && typeof window.openStageEditor === 'function'
        ? '<button type="button" class="mj-edit" onclick="openStageEditor()" title="Edit stages: reorder, rename, add or delete" aria-label="Edit pipeline stages"><i class="fas fa-pen"></i></button>' : '';
    return `<tr class="fdl-route-row"><td colspan="10">
        <div class="mj-card">
            <div class="mj-stub">
                <span class="mj-ring" style="--mj-p:${pct}%" title="${pct}% of the journey done"><span>${pct}%</span></span>
                <span class="mj-now">
                    <small><i class="fas fa-route"></i> Now${edit}</small>
                    <b>${esc(p.stage_label || 'Agent')}</b>
                    <span class="mj-next">${next ? `Next <i class="fas fa-arrow-right"></i> ${esc(next)}` : 'Journey complete'}</span>
                    ${p.exception_label ? `<span class="mj-flag"><i class="fas fa-triangle-exclamation"></i>${esc(p.exception_label)}</span>` : ''}
                </span>
            </div>
            <div class="mj-scroll"><ol class="mj-route" aria-label="${esc(p.name || 'Passenger')} journey; current stage ${esc(p.stage_label || 'Agent')}">${route}</ol></div>
        </div>
    </td></tr>`;
}
let FD_STAGE_DIALOG_OPEN = false;
@include('contract-flights.partials.passenger-sheet-script')

function openPassengerSheet(passengerId) {
    const p = FD_PASSENGERS.find(x => String(x.id) === String(passengerId));
    if (!p) return;
    // Resolved exactly as the row resolves it, so the sheet's flight is the
    // one the row was drawn from — including on the "all flights" ledger.
    const flight = fdFindFlight(p.dashboard_flight_id ?? FD_LEDGER_ID) || {};
    fdShowSheet(passengerSheetHtml(p, flight), (p.name || 'Passenger') + ' · ' + (flight.flight_number || ''));
}

/**
 * The whole batch on one sheet: the flight, who is billed for it, and every
 * passenger on it (owner, 2026-09-22).
 *
 * Laid out for a single page and allowed to run onto a second rather than
 * listing some passengers and hiding the rest — a manifest that stops
 * halfway is worse than a manifest on two sheets.
 */
function flightSheetHtml(flight, passengers) {
    const dep = parseIso(flight.departure_at);
    const arr = parseIso(flight.arrival_at);
    const brand = (typeof airlineBrand === 'function' ? airlineBrand(flight.airline) : { slug: null });
    const initials = String(flight.airline || 'EP').replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase() || 'EP';
    const headMark = brand.slug
        ? `<img class="fdl-mark" src="${FD_LOGO_BASE}/${esc(brand.slug)}.png" alt="${esc(flight.airline || '')}" onerror="this.remove()">`
        : `<span class="fdl-mark-txt">${esc(initials)}</span>`;

    const hops = (flight.route_hops || []).length
        ? flight.route_hops
        : [{ code: flight.from?.code, flag: flight.from?.country }, { code: flight.to?.code, flag: flight.to?.country }].filter(h => h.code);
    const routeStrip = hops.map((h, i) => `${i ? '<span class="sep">→</span>' : ''}<span class="hop">${shFlagCc(h.flag)}${esc(h.code)}</span>`).join('');

    // Money is summed from the passengers on the sheet, not read off the
    // flight row, so the totals and the list can never disagree with each
    // other on the page someone is holding.
    const sum = key => passengers.reduce((t, p) => t + Number(p[key] || 0), 0);
    const charge = sum('agent_price'), cost = sum('total_travel_cost'), due = sum('agent_due');
    const ticketed = passengers.filter(p => (p.ticket_slots || []).length
        ? p.ticket_slots.every(s => s.done)
        : (p.ticket_purchases || []).some(t => t.status === 'confirm')).length;

    // One line per agent on the batch — the aircraft routinely carries
    // several agents' people, so "the agent" is a list, not a field.
    const byAgent = new Map();
    passengers.forEach(p => {
        const key = p.agent_id ? String(p.agent_id) : 'direct';
        const row = byAgent.get(key) || { name: p.agent_name || 'Direct', id: p.agent_id, heads: 0, charge: 0, due: 0 };
        row.heads++; row.charge += Number(p.agent_price || 0); row.due += Number(p.agent_due || 0);
        byAgent.set(key, row);
    });
    const agentRows = [...byAgent.values()].map(a => `<tr>
            <td>${esc(a.name)}</td>
            <td>${esc(a.id ? 'EP-AG-' + String(a.id).padStart(3, '0') : '—')}</td>
            <td class="num">${a.heads}</td>
            <td class="num">${ledgerMoney(a.charge)}</td>
            <td class="num">${ledgerMoney(a.due)}</td>
        </tr>`).join('');

    const paxRows = passengers.length ? passengers.map((p, i) => {
        const pnrs = (p.ticket_purchases || []).map(t => t.ticket_no).filter(Boolean).join(', ');
        const slots = p.ticket_slots || [];
        const done = slots.length ? slots.filter(s => s.done).length + ' / ' + slots.length : ((p.ticket_purchases || []).length ? 'issued' : '—');
        return `<tr>
            <td class="num">${i + 1}</td>
            <td>${esc(p.name || 'Passenger not assigned')}</td>
            <td>${esc(p.passport_no || '—')}</td>
            <td>${esc(p.agent_name || 'Direct')}</td>
            <td>${esc(p.flight_category_name || '—')}</td>
            <td>${esc(pnrs || 'PNR pending')}</td>
            <td>${esc(done)}</td>
            <td>${esc(p.stage_label || '—')}${p.exception_label ? ` <span class="bad">${esc(p.exception_label)}</span>` : ''}</td>
            <td class="num">${ledgerMoney(p.agent_price)}</td>
            <td class="num">${ledgerMoney(p.total_travel_cost)}</td>
            <td class="num">${ledgerMoney(p.profit)}</td>
        </tr>`;
    }).join('') : '<tr><td colspan="11" class="muted">No passengers on this batch yet.</td></tr>';

    return `<div class="fdl-sheet-hd">
            <div><h1>${esc(flight.flight_number || 'Flight')}</h1>
                <p class="sub">Flight record · ${esc(passengers.length)} passenger${passengers.length === 1 ? '' : 's'}</p></div>
            <div class="ref">${headMark}<span><b>${esc(flight.airline || 'Airline pending')}</b>${esc(flight.airline_flight_no || flight.status || '')}</span></div>
        </div>

        <div class="fdl-sheet-route">${routeStrip}<span class="when">${esc(dep ? fmtDeparture(dep) : 'Date pending')}</span></div>

        ${shSection('Flight', shGrid([
            shField('Flight number', flight.flight_number),
            shField('Airline flight no', flight.airline_flight_no),
            shField('Status', flight.status),
            shFieldHtml('From', `${shAp(flight.from)} <span class="muted">${esc(flight.from?.name || '')}</span>`),
            shFieldHtml('To', `${shAp(flight.to)} <span class="muted">${esc(flight.to?.name || '')}</span>`),
            shField('Full route', flight.route_path || `${flight.from?.code || '—'} → ${flight.to?.code || '—'}`),
            shField('Departure', dep ? `${fmtTableDate(dep)}, ${fmtTime(dep)}` : 'Date pending'),
            shField('Arrival', arr ? `${fmtTableDate(arr)}, ${fmtTime(arr)}` : '—'),
            shField('Category · class', `${flight.category || 'Not set'}${flight.ticket_class ? ' · ' + flight.ticket_class : ''}`),
        ]))}

        ${shSection('Agents on this batch', `<table><thead><tr>
            <th>Agent</th><th>Agent ID</th><th class="num">Heads</th><th class="num">Charge</th><th class="num">Due</th>
        </tr></thead><tbody>${agentRows || '<tr><td colspan="5" class="muted">No agent assigned.</td></tr>'}</tbody></table>`)}

        ${shSection('Batch totals', `<div class="fdl-money">
            <div><span class="k">Passengers</span><span class="v">${esc(passengers.length)}</span></div>
            <div><span class="k">Ticketed</span><span class="v">${esc(ticketed)} / ${esc(passengers.length)}</span></div>
            <div><span class="k">Agent charge</span><span class="v">${ledgerMoney(charge)}</span></div>
            <div class="m-profit${charge - cost < 0 ? ' neg' : ''}"><span class="k">Profit</span><span class="v">${ledgerMoney(charge - cost)}</span></div>
        </div>`)}

        ${shSection('Passengers', `<table><thead><tr>
            <th class="num">#</th><th>Passenger</th><th>Passport</th><th>Agent</th><th>Category</th>
            <th>PNR</th><th>Tickets</th><th>Stage</th>
            <th class="num">Charge</th><th class="num">Cost</th><th class="num">Profit</th>
        </tr></thead><tbody>${paxRows}</tbody>
        <tfoot><tr><td colspan="8">Batch</td>
            <td class="num">${ledgerMoney(charge)}</td><td class="num">${ledgerMoney(cost)}</td><td class="num">${ledgerMoney(charge - cost)}</td>
        </tr></tfoot></table>`)}

        <p class="fdl-sheet-note">Epal ERP · Contract Flight · agent due on this batch ${ledgerMoney(due)}. Generated on ${esc(fmtTableDate(new Date()))}; figures are the ones held at the time of printing.</p>`;
}

/**
 * The eye on a flight row. Passengers come from the same endpoint the
 * ledger uses, so the sheet and the ledger can never list a batch
 * differently. Cached per flight for the life of the page.
 */
const FD_FLIGHT_PAX = new Map();

async function openFlightSheet(flightId) {
    const flight = fdFindFlight(flightId);
    if (!flight) return;
    const editUrl = `${FD_FLIGHTS_URL}?search=${encodeURIComponent(flight.flight_number || '')}&edit=${encodeURIComponent(flight.id)}`;

    let passengers = FD_FLIGHT_PAX.get(String(flightId));
    if (!passengers) {
        fdShowSheet('<p class="muted">Loading the batch…</p>', flight.flight_number || 'Flight', { flow: true, edit: editUrl });
        try {
            const response = await fetch(FD_PASSENGERS_URL.replace('__FLIGHT__', encodeURIComponent(flightId)), { headers: { 'Accept': 'application/json' } });
            const body = await response.json();
            passengers = body.passengers || [];
            FD_FLIGHT_PAX.set(String(flightId), passengers);
        } catch (e) {
            // The flight's own details are still worth printing, so the sheet
            // renders with an empty list rather than refusing to open.
            passengers = [];
        }
    }
    fdShowSheet(flightSheetHtml(flight, passengers), `${flight.flight_number || 'Flight'} · ${flight.airline || ''}`, { flow: true, edit: editUrl });
}

document.addEventListener('click', event => {
    const eye = event.target.closest('.fdl-flight-eye');
    if (!eye) return;
    // The row itself opens the passenger ledger; the eye must not do both.
    event.stopPropagation();
    openFlightSheet(eye.dataset.flight);
});

document.addEventListener('click', event => {
    const eye = event.target.closest('.fdl-eye');
    if (!eye) return;
    openPassengerSheet(eye.dataset.passenger);
});

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && FD_SHEET_EL && !FD_SHEET_EL.hidden) closePassengerSheet();
});

document.addEventListener('click', async event => {
    const button = event.target.closest('.fdl-stage-action');
    if (!button || !FD_CAN_EDIT_STAGE || FD_STAGE_DIALOG_OPEN) return;
    const passenger = FD_PASSENGERS.find(p => String(p.id) === button.dataset.passenger);
    const stage = LEDGER_STAGE_ROUTE.find(([key]) => key === button.dataset.stage);
    if (!passenger || !stage) return;
    const request = FD_LEDGER_REQUEST;
    FD_STAGE_DIALOG_OPEN = true;
    try {
        const isCurrent = passenger.stage === stage[0];
        const result = await Swal.fire({
            title: stage[1],
            text: isCurrent
                ? `${passenger.name || 'This passenger'} is already at this stage.`
                : `Set ${passenger.name || 'this passenger'} from ${passenger.stage_label || passenger.stage} to ${stage[1]}? Earlier stages will appear completed and later stages upcoming.`,
            icon: 'info',
            showCancelButton: !isCurrent,
            confirmButtonText: isCurrent ? 'Close' : 'Mark as current stage',
            confirmButtonColor: '#4f46e5',
            showLoaderOnConfirm: !isCurrent,
            allowOutsideClick: () => !Swal.isLoading(),
            allowEscapeKey: () => !Swal.isLoading(),
            preConfirm: isCurrent ? undefined : async () => {
                try {
                    const response = await fetch(FD_STAGE_URL.replace('__PASSENGER__', encodeURIComponent(passenger.id)), {
                        method: 'PATCH', credentials: 'same-origin',
                        headers: {'Accept': 'application/json', 'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                        body: JSON.stringify({stage: stage[0]})
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) throw new Error(data.message || 'Could not save the stage. Please try again.');
                    return true;
                } catch (error) {
                    Swal.showValidationMessage(error.message);
                    return false;
                }
            }
        });
        if (result.isConfirmed && !isCurrent && request === FD_LEDGER_REQUEST) {
            passenger.stage = stage[0];
            passenger.stage_label = stage[1];
            renderPassengerLedger();
            const replacement = Array.from(document.querySelectorAll('.fdl-stage-action')).find(el =>
                el.dataset.passenger === String(passenger.id) && el.dataset.stage === stage[0]);
            replacement?.focus({preventScroll: true});
            Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Stage saved',
                showConfirmButton: false, timer: 2200});
        }
    } finally {
        FD_STAGE_DIALOG_OPEN = false;
    }
});

// ── Stage editor ─────────────────────────────────────────────────────────
// The editor itself is partials/stage-editor (shared with Flights &
// Passengers). After a save it hands back the settled list; the ledger takes
// it, relabels the passengers and repaints in place.
window.onStageEditorSaved = function (stages) {
    LEDGER_STAGE_ROUTE = stages.map(s => [s.key, s.label, s.icon, s.is_conditional, s.is_system, s.id]);
    const labels = Object.fromEntries(LEDGER_STAGE_ROUTE.map(([key, label]) => [key, label]));
    FD_PASSENGERS.forEach(p => { if (labels[p.stage]) p.stage_label = labels[p.stage]; });
    if (!document.getElementById('fdPassengerTable').hidden) renderPassengerLedger();
};




// ── Dock swell ───────────────────────────────────────────────────────────
// Each step gets --fdl-near (0..1) from its distance to the pointer, so the
// pins nearest the cursor grow. One rAF per move, reset on leave. Delegated,
// so re-rendered strips need no rebind. Mouse only; touch is left alone.
let fdlDockFrame = null;
document.addEventListener('pointermove', event => {
    if (event.pointerType && event.pointerType !== 'mouse') return;
    const route = event.target.closest?.('.fdl-route');
    if (!route) return;
    if (fdlDockFrame) cancelAnimationFrame(fdlDockFrame);
    const x = event.clientX;
    fdlDockFrame = requestAnimationFrame(() => {
        fdlDockFrame = null;
        route.querySelectorAll('.fdl-stage-action').forEach(el => {
            const r = el.getBoundingClientRect();
            const d = Math.abs(x - (r.left + r.width / 2));
            const near = Math.max(0, 1 - d / 130);
            el.style.setProperty('--fdl-near', (near * near).toFixed(3));
        });
    });
}, { passive: true });
document.addEventListener('pointerleave', event => {
    const route = event.target?.classList?.contains('fdl-route') ? event.target : null;
    if (!route) return;
    route.querySelectorAll('.fdl-stage-action').forEach(el => el.style.removeProperty('--fdl-near'));
}, true);

const LEDGER_COST_ICONS = {
    'Tickets': 'fa-ticket', 'Manpower': 'fa-users', 'Boarding': 'fa-plane-up', 'Immigration': 'fa-stamp',
    'Card fee': 'fa-id-card', 'Reissue': 'fa-rotate',
};

// ── The account card ─────────────────────────────────────────────────────
// The Manage Banks statement header, worn by a flight (see the map at the
// top of dashboard-ledger-styles). Band 1 is painted from the flight's own
// card data the moment it opens — nothing to wait for — and the rail fills
// once the passengers arrive.

// Set text and keep the untruncated value on hover — every line in the card
// clips rather than wraps, so the tooltip is the only way back to the full
// string. Pass html:true when the value carries its own ৳ / tag markup.
function ledgerPut(id, text, tip, html) {
    const el = document.getElementById(id);
    if (!el) return;
    if (html) el.innerHTML = text; else el.textContent = text;
    el.title = tip !== undefined ? tip : el.textContent;
}
// ৳ is punctuation around the number, not part of it, so it is marked up
// separately and set smaller and dimmer by the sheet — the bank's rule, which
// is what keeps the figures in the rail looking like one set.
function ledgerFig(value) {
    return `${Number(value) < 0 ? '−' : ''}<span class="cur">৳</span>${esc(fmtMoney(value))}`;
}

function paintLedgerHeader(f) {
    const brand   = airlineBrand(f.airline);
    const airline = f.airline ? tidyAirline(f.airline) : 'Airline pending';
    const ink     = STATUS_INK[f.status] || { label: f.status || '—' };
    const dep     = parseIso(f.departure_at);
    const arr     = parseIso(f.arrival_at);
    const state   = depState(dep);

    // Zone 1 — the mark, the airline, the route, the batch number.
    ledgerPut('fdlName', airline, [f.airline || 'Airline pending', f.flight_number, f.category, f.ticket_class].filter(Boolean).join(' · '));
    paintLedgerMark(brand, airline, !!f.airline);
    const fromCode = f.from?.code || '—', toCode = f.to?.code || '—';
    ledgerPut('fdlRoute',
        (f.from || f.to)
            ? `${flagImg(f.from, 'fdc-flag')} ${esc(fromCode)} → ${esc(toCode)} ${flagImg(f.to, 'fdc-flag')}`
            : 'Route pending',
        [f.from?.name || 'origin not set', f.to?.name || 'destination not set'].join(' → ')
            + (f.airline_flight_no ? ' · airline flight ' + f.airline_flight_no : ''),
        true);
    ledgerPut('fdlNumber', f.flight_number || '—');
    // Always shown under the batch (owner, 2026-10-03: "flight number? show
    // here"). Not set yet → a pale prompt that opens Edit flight to add it.
    const flightNoLine = document.getElementById('fdlFlightNoLine');
    flightNoLine.hidden = false;
    flightNoLine.classList.toggle('is-unset', !f.airline_flight_no);
    ledgerPut('fdlFlightNo', f.airline_flight_no || 'Flight no. not set',
        f.airline_flight_no ? 'Airline flight ' + f.airline_flight_no : 'No airline flight number yet — click to add it in Edit flight');
    paintLedgerBadge(f);
    ledgerPut('fdlStatus', ink.label, 'Status · ' + ink.label);

    // Zone 2 — who brought the batch, and when it leaves.
    // The agent and the vendor are links to their own ledgers. One agent's
    // name is the link; several agents (passengers billed to different
    // people) link the first and say how many in the tooltip.
    const agents    = f.agents || [];
    const agent     = agents[0];
    const agentUrl  = agent ? `${FD_AGENTS_URL}?party_id=${encodeURIComponent(agent.id)}` : FD_AGENTS_URL;
    const vendorUrl = f.vendor_id ? `${FD_VENDORS_URL}?party_id=${encodeURIComponent(f.vendor_id)}` : FD_VENDORS_URL;
    const agentTitle = agent
        ? (agents.length > 1 ? `${agents.length} agents on this batch — opens ${agent.name}'s ledger` : `Open ${agent.name}'s ledger`)
        : 'No agent on this batch — opens the Agent ledger';
    ledgerPut('fdlAgent',
        agent ? `<a href="${esc(agentUrl)}">${esc(f.agent || agent.name)}</a>` : 'Direct — no agent',
        agents.length ? agents.map(a => `${a.name} (${a.display_id})`).join(' · ') : 'No agent on this batch or its passengers',
        true);
    // Several agents: every name, each a link to its own ledger, in the
    // header's free space. One column, as sketched; two only past four names.
    const agentsZone = document.getElementById('fdlAgentsZone');
    agentsZone.hidden = agents.length < 2;
    if (agents.length > 1) {
        ledgerPut('fdlAgentsCount', `(${agents.length})`);
        const list = document.getElementById('fdlAgents');
        list.classList.toggle('is-two', agents.length > 4);
        list.innerHTML = agents.map(a => `<a href="${esc(FD_AGENTS_URL)}?party_id=${encodeURIComponent(a.id)}" title="Open ${esc(a.name)}'s ledger · ${esc(a.display_id)}"><i class="fas fa-user-tie" aria-hidden="true"></i>${esc(a.name)}</a>`).join('');
    }
    ledgerPut('fdlDepQual', dep ? `(${state.txt})` : '(not set)');
    // Day and time, as the card prints them; the zone is 195px and the year
    // pushed the time off its end. The weekday, year and arrival are in the tooltip.
    ledgerPut('fdlDeparture',
        dep ? `${esc(fmtDay(dep))}<span class="tag">${esc(fmtTime(dep))}</span>` : '—',
        dep ? [`Departs ${WDAYS[dep.getDay()]} ${fmtFull(dep)} ${fmtTime(dep)}`, arr ? `arrives ${fmtFull(arr)} ${fmtTime(arr)}` : ''].filter(Boolean).join(' · ')
            : 'Departure not set',
        true);

    // Zone 3 — what the vendor is owed. Due is the bank's "paid out" red,
    // clear its "received" green, and a batch with no vendor says so.
    const due  = Number(f.vendor_due) || 0;
    const kind = (!f.vendor && !due) ? 'none' : (due > 0 ? 'out' : 'in');
    const pill = document.getElementById('fdlVendorPill');
    pill.className = 'fdl-pill fdl-pill-' + kind;
    pill.querySelector('i').className = 'fas ' + { out: 'fa-arrow-up', in: 'fa-check', none: 'fa-minus' }[kind];
    document.getElementById('fdlVendorDue').className = 'fdl-last-amt' + (kind === 'none' ? '' : ' fdl-amt-' + kind);
    ledgerPut('fdlVendorDir', { out: 'Due', in: 'Clear', none: 'None' }[kind]);
    ledgerPut('fdlVendorDue', kind === 'none' ? '—' : ledgerFig(due),
        kind === 'none' ? 'No vendor on this batch' : `Owed to the vendor: ৳${fmtMoney(due)}`, true);
    const tv = f.ticket_vendors || [];
    ledgerPut('fdlVendorName',
        f.vendor ? `<a href="${esc(vendorUrl)}">${esc(f.vendor)}</a>${f.vendor_display_id ? ' · ' + esc(f.vendor_display_id) : ''}`
            // One vendor per line, each under the one before (owner, 2026-10-01):
            // joined with commas the second was always cut off by the ellipsis.
            : (tv.length ? `<span class="fdl-tv"><span class="fdl-tv-k">Ticket vendor:</span>${tv.map(v => `<a href="${FD_VENDORS_URL}?party_id=${encodeURIComponent(v.id)}" title="Open ${esc(v.name)}'s ledger">${esc(v.name)}</a>`).join('')}</span>` : 'No vendor on this batch'),
        f.vendor ? `Open ${f.vendor}'s ledger` : (tv.length ? 'From the linked PNRs — no batch (flight-cost) vendor is set' : 'No vendor on this batch'), true);
    ledgerPut('fdlVendorWhen', f.payable_date ? 'Payable by ' + fmtTableDate(parseIso(f.payable_date)) : (f.vendor ? 'No payable date' : (tv.length ? '' : '—')));

    // The actions. Every one goes somewhere: Agent / Vendor open the party's
    // ledger, or the Agent / Vendor ledger list when the batch has none.
    const link = (id, href, title, list) => {
        const a = document.getElementById(id);
        a.href = href;
        a.classList.toggle('is-list', !!list);
        a.title = title;
    };
    link('fdlAgentBtn', agentUrl, agentTitle, !agent);
    link('fdlVendorBtn', vendorUrl, f.vendor ? `Open ${f.vendor}'s ledger` : 'No vendor on this batch — opens the Vendor ledger', !f.vendor_id);
    // Edit opens Flights & Passengers with this batch's Edit Flight modal
    // already open (step 2 holds the departure time, route and officers).
    // The search keeps the flight on the first page so the modal can find it.
    link('fdlEditBtn', `${FD_FLIGHTS_URL}?search=${encodeURIComponent(f.flight_number || '')}&edit=${encodeURIComponent(f.id)}`, 'Edit this flight — date & time, route, officers and its passengers');
    link('fdlManageBtn', `${FD_FLIGHTS_URL}?search=${encodeURIComponent(f.flight_number || '')}`, 'Open this flight on Flights & Passengers');
    link('fdlBoardBtn', `${FD_BOARD_URL}?contract_flight_id=${encodeURIComponent(f.id)}`, 'Open the passenger pipeline board for this flight');
    ['fdlEditBtn', 'fdlBoardBtn'].forEach(id => { document.getElementById(id).hidden = false; });
    document.getElementById('fdlManageBtn').innerHTML = '<i class="fas fa-list"></i> Manage';
    document.getElementById('fdlPrint').href = FD_INVOICE_URL.replace('__FLIGHT__', encodeURIComponent(f.id));
}

function paintAirlineLedgerHeader(summary) {
    paintLedgerHeader(summary);
    const next = parseIso(summary.departure_at);
    const latest = parseIso(summary.latest_departure_at);
    const range = [fmtFull(next), fmtFull(latest)].filter(Boolean).filter((value, index, values) => index === 0 || value !== values[0]).join(' - ');
    ledgerPut('fdlNumber', summary.flight_number || 'Flights');
    ledgerPut('fdlStatus', (summary.status_summary || 'Upcoming').toUpperCase(), summary.status_breakdown || `${summary.flight_number || 'Flights'} for ${summary.airline || 'this airline'}`);
    if (summary.route_count > 1) {
        ledgerPut('fdlRoute', `<i class="fas fa-route"></i> ${esc(summary.route_count)} Routes`, summary.route_labels.join(' · '), true);
    }
    if (summary.agents.length > 1) {
        ledgerPut('fdlAgent', `${summary.agents.length} agents`, summary.agents.map(agent => `${agent.name} (${agent.display_id || 'ID pending'})`).join(' · '));
        const agentButton = document.getElementById('fdlAgentBtn');
        agentButton.href = FD_AGENTS_URL;
        agentButton.title = `View all ${summary.agents.length} agents`;
    }
    if (summary.vendor_count > 1) {
        ledgerPut('fdlVendorName', `${summary.vendor_count} vendors`, summary.ticket_vendors.map(vendor => vendor.name).join(' · '));
        const vendorButton = document.getElementById('fdlVendorBtn');
        vendorButton.href = FD_VENDORS_URL;
        vendorButton.title = `View all ${summary.vendor_count} vendors`;
    }
    ledgerPut('fdlVendorWhen', summary.vendor_count ? `Across ${summary.flight_number}` : 'No vendors');
    const flightNoLine = document.getElementById('fdlFlightNoLine');
    flightNoLine.hidden = false;
    flightNoLine.classList.remove('is-unset');
    ledgerPut('fdlFlightNo', range || 'Dates pending', `Departure range: ${range || 'not set'}`);
    ledgerPut('fdlDepQual', '(next)');
    ledgerPut('fdlDeparture', next ? `${esc(fmtDay(next))}<span class="tag">${esc(fmtTime(next))}</span>` : '—', next ? `Next departure ${fmtFull(next)} ${fmtTime(next)}${latest ? `; latest ${fmtFull(latest)} ${fmtTime(latest)}` : ''}` : 'Departure not set', true);
    document.getElementById('fdlEditBtn').hidden = true;
    document.getElementById('fdlBoardBtn').hidden = true;
    const manage = document.getElementById('fdlManageBtn');
    manage.href = `${FD_FLIGHTS_URL}?search=${encodeURIComponent(summary.airline || '')}`;
    manage.title = `View ${summary.airline || 'airline'} flights`;
    manage.innerHTML = '<i class="fas fa-list"></i> View flights';
}

// The mark plate. Three states, in falling order: the airline's mark, its
// initials, or the plane glyph when the batch has no airline yet. The <img>
// onerror in the markup demotes a 404 to the initials on its own.
const FDL_MARK_H = 64, FDL_MARK_MAX_W = 96;

// The second airline on a via route: its mark on a small white badge at the
// tile's corner, or its initials when no mark is on file. Hidden on a route
// flown by one airline.
function paintLedgerBadge(f) {
    const badge = document.getElementById('fdlBadge');
    const other = (f.airlines || []).find(name => name && name !== f.airline) || null;
    badge.hidden = !other;
    if (!other) return;
    const brand = airlineBrand(other);
    const img = badge.querySelector('img');
    badge.title = 'Also flies ' + other;
    badge.querySelector('.fdl-badge-mono').textContent = initials(tidyAirline(other));
    badge.classList.toggle('is-mono', !brand.slug);
    if (brand.slug) { img.alt = other; img.src = `${FD_LOGO_BASE}/${brand.slug}.png`; }
    else img.removeAttribute('src');
}

function paintLedgerMark(brand, airline, hasAirline) {
    const plate = document.getElementById('fdlAvatar');
    const img   = document.getElementById('fdlMark');
    plate.classList.remove('is-fit');
    plate.style.width = '';
    plate.querySelector('.fdl-crop').style.cssText = '';
    img.style.cssText = '';
    document.getElementById('fdlInitials').textContent = initials(airline);
    plate.classList.toggle('is-glyph', !hasAirline);
    plate.classList.add('is-mono');           // stay on the fallback until the mark decodes
    if (!brand.slug) { img.onload = null; img.removeAttribute('src'); return; }
    const url = `${FD_LOGO_BASE}/${brand.slug}.png`;
    img.alt = airline;
    img.onload = () => { plate.classList.remove('is-mono'); fitLedgerMark(plate, img, url); };
    // The same mark as the flight before: already decoded, no load event coming.
    if (img.getAttribute('src') === url && img.complete && img.naturalWidth) { img.onload(); return; }
    img.src = url;
}

// Zoomed to the ink, exactly as the cards are — same inkBox() scan, same
// cache — but laid into the bank header's 92px plate rather than the card's
// 58px box, at the compact 64px. The plate keeps its height and takes the width the ink asks for,
// 64 to 96: a roundel stays square, a wordmark gets room instead of
// shrinking to a sliver. Fails soft: unreadable pixels leave the mark
// contained in the 64px box, which is already correct.
function layoutLedgerMark(plate) {
    const crop = plate._crop, wrap = plate.querySelector('.fdl-crop'), img = plate.querySelector('img');
    if (!crop || !wrap || !img) return;
    const cs  = getComputedStyle(plate);   // the tile's own padding, both sides
    const pad = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0);
    const W = (plate.clientWidth || FDL_MARK_H) - pad, H = FDL_MARK_H - pad;
    const s = Math.min(W / crop.w, H / crop.h);
    wrap.style.width  = (crop.w  * s) + 'px';
    wrap.style.height = (crop.h  * s) + 'px';
    img.style.width   = (crop.nw * s) + 'px';
    img.style.height  = (crop.nh * s) + 'px';
    img.style.left    = (-crop.x * s) + 'px';
    img.style.top     = (-crop.y * s) + 'px';
}
function fitLedgerMark(plate, img, url) {
    if (plate.classList.contains('is-mono')) return;
    const known = inkFromCache(url);
    let ink = (known && known.nw === img.naturalWidth && known.nh === img.naturalHeight) ? known : null;
    if (!ink) {
        ink = inkBox(img);
        if (!ink) return;
        inkToCache(url, ink);
    }
    plate._crop = cropOf(ink);
    plate.style.width = Math.max(FDL_MARK_H, Math.min(FDL_MARK_MAX_W, Math.round(FDL_MARK_H * plate._crop.w / plate._crop.h))) + 'px';
    plate.classList.add('is-fit');
    layoutLedgerMark(plate);
    // A wordmark widens the plate, so the row's need changes: re-fit.
    fitFlightLedgerHeader();
}

// THE CARD FITS ITS ROW, AT ANY WINDOW OR BROWSER ZOOM — the bank page's
// fitLedgerHeader(), verbatim, pointed at this card. The band is drawn to a
// fixed shape (identity 320 + agent 200 + vendor 230 + a 2×2 button block, about
// 1000px with the zone padding) and whether that fits depends on the window
// AND the browser's own zoom, which no CSS breakpoint can see. So the card is
// measured: laid out once at natural size to read what one row needs, and
// scaled by exactly the shortfall. Floor 0.78 — below that the sheet's own
// two-row branch is the better answer. Never scales up.
const FDL_FIT_FLOOR = 0.78;

function fitFlightLedgerHeader() {
    try {
        const card = document.querySelector('#fdLedger .fdl-header');
        // offsetParent is null while the ledger is hidden; measuring then
        // returns zeroes and would scale the card to its floor.
        if (!card || card.offsetParent === null) return;
        const row = card.querySelector('.fdl-head-top');
        const actions = card.querySelector('.fdl-actions');
        const first = card.querySelector('.fdl-zone-id');
        if (!row || !actions || !first) return;

        // Have the buttons fallen off the first line?
        const broken = () => actions.getBoundingClientRect().top
                           - first.getBoundingClientRect().top > 8;

        card.style.zoom = '';
        if (!broken()) return;                // fits as drawn — leave it alone

        const avail = row.clientWidth;
        if (!avail) return;

        // What one unbroken row would take, at the shape as drawn.
        const wrap = row.style.flexWrap, width = row.style.width;
        row.style.flexWrap = 'nowrap';
        row.style.width = 'max-content';
        const needed = row.scrollWidth;
        row.style.flexWrap = wrap;
        row.style.width = width;
        if (!needed) return;

        let z = Math.max(FDL_FIT_FLOOR, Math.floor((avail / needed) * 100) / 100);
        card.style.zoom = z;
        // scrollWidth reads the zones' CONTENT, but they are laid out at their
        // flex-basis when that is wider (a short route, stacked vendors), so
        // the estimate can come up short. Keep stepping down until the row
        // really is one line, or the floor is reached.
        for (let i = 0; i < 30 && broken() && z > FDL_FIT_FLOOR; i++) {
            z = Math.max(FDL_FIT_FLOOR, Math.round((z - 0.01) * 100) / 100);
            card.style.zoom = z;
        }

        // Hand back the over-shrink a point at a time and stop at the last
        // size that still holds one row.
        for (let i = 0; i < 10 && z < 1; i++) {
            const test = Math.min(1, Math.round((z + 0.01) * 100) / 100);
            card.style.zoom = test;
            if (broken()) { card.style.zoom = z; break; }
            z = test;
        }
        if (z >= 1) card.style.zoom = '';
    } catch (e) {
        // Layout reads can throw in odd states; the card then keeps the
        // stylesheet's own behaviour.
    }
}
let fdlFitTimer = null;
window.addEventListener('resize', () => {
    clearTimeout(fdlFitTimer);
    fdlFitTimer = setTimeout(fitFlightLedgerHeader, 150);
});

// Band 2 — the figures rail. The WHOLE batch, always: the filter bar below
// narrows the rows, not these. null paints the dashes while passengers load.
function paintLedgerRail(list) {
    const $id = id => document.getElementById(id);
    const ids = ['fdlPax', 'fdlTicketed', 'fdlPending', 'fdlFlagged', 'fdlCharge', 'fdlCost', 'fdlProfit'];
    if (!list) {
        ids.forEach(id => { $id(id).textContent = '—'; $id(id).title = 'Loading passengers…'; $id(id).closest('div').classList.remove('is-zero', 'is-loss'); });
        $id('fdlFlagged').closest('div').classList.remove('is-out');
        return;
    }
    // A zero drops the colour but not the contrast — the bank rail's rule.
    const cell = (id, html, tip, zero) => {
        const el = $id(id);
        el.innerHTML = html;
        el.title = tip;
        el.closest('div').classList.toggle('is-zero', zero);
    };
    const n        = list.length;
    const ticketed = list.filter(p => (p.ticket_purchases || []).length).length;   // the card's own reading: any PNR on file
    const pending  = n - ticketed;
    const flagged  = list.filter(p => p.exception_flag).length;
    const sum      = key => list.reduce((total, p) => total + Number(p[key] || 0), 0);
    const charge = sum('agent_price'), cost = sum('total_travel_cost'), profit = sum('profit');

    cell('fdlPax',      esc(n),        `${n} passenger${n === 1 ? '' : 's'} on this batch`, !n);
    cell('fdlTicketed', esc(ticketed), `${ticketed} of ${n} hold a PNR`, !ticketed);
    cell('fdlPending',  esc(pending),  `${pending} still to be ticketed`, !pending);
    cell('fdlFlagged',  esc(flagged),  flagged ? `${flagged} carrying an exception flag — cancelled, rescheduled, transit or deported` : 'No exception flags', !flagged);
    $id('fdlFlagged').closest('div').classList.toggle('is-out', flagged > 0);
    cell('fdlCharge',   ledgerFig(charge), `Billed to the agent across every passenger: ৳${fmtMoney(charge)}`, !charge);
    cell('fdlCost',     ledgerFig(cost),   `Travel cost across every passenger, reissue excluded: ৳${fmtMoney(cost)}`, !cost);
    cell('fdlProfit',   ledgerFig(profit), `Agent charge less travel cost: ${profit < 0 ? '−' : ''}৳${fmtMoney(profit)}`, !profit);
    $id('fdlProfit').closest('div').classList.toggle('is-loss', profit < 0);
}

async function openFlightLedger(id) {
    hideDashboardPassengers();
    const flight = fdFindFlight(id);
    if (!flight) return;
    document.querySelector('#fdLedger .fdl-header').hidden = false;
    document.getElementById('fdlPrint').hidden = false;
    FD_LEDGER_ID = flight.id;
    const request = ++FD_LEDGER_REQUEST;
    FD_PASSENGERS = [];
    document.getElementById('fdPassengerSearch').disabled = true;
    document.querySelectorAll('#fdGrid [data-flight-id]').forEach(card => {
        card.setAttribute('aria-pressed', String(card.dataset.flightId === String(id)));
    });
    document.querySelectorAll('#fdGrid [data-airline-key]').forEach(card => card.setAttribute('aria-pressed', 'false'));
    document.getElementById('fdOverview').hidden = true;
    const panel = document.getElementById('fdLedger');
    panel.hidden = false;
    document.getElementById('fdLedgerCount').textContent = flight.flight_number || '';
    paintLedgerHeader(flight);
    // Visible now, so it can be measured: the airline name, the route and
    // the vendor line all change width with the flight.
    fitFlightLedgerHeader();
    paintLedgerRail(null);
    paintPassengerBar();
    document.getElementById('fdPassengerCount').style.display = 'none';   // nothing to count until the rows arrive
    document.getElementById('fdPassengerTable').hidden = true;
    const message = document.getElementById('fdLedgerMessage');
    message.hidden = false;
    message.textContent = 'Loading passengers…';
    panel.setAttribute('aria-busy', 'true');
    document.getElementById('fdLedgerTitle').focus({preventScroll:true});
    panel.scrollIntoView({behavior:'smooth', block:'start'});
    try {
        const response = await fetch(FD_PASSENGERS_URL.replace('__FLIGHT__', encodeURIComponent(id)), {
            headers: {'Accept':'application/json'}, credentials:'same-origin'
        });
        if (!response.ok) throw new Error('Unable to load');
        const data = await response.json();
        if (!data.success || !Array.isArray(data.passengers)) throw new Error('Invalid response');
        if (request !== FD_LEDGER_REQUEST) return;
        FD_PASSENGERS = data.passengers;
        document.getElementById('fdPassengerSearch').disabled = false;
        document.getElementById('fdLedgerCount').textContent =
            `${flight.flight_number} · ${FD_PASSENGERS.length} passenger${FD_PASSENGERS.length === 1 ? '' : 's'}`;
        paintLedgerRail(FD_PASSENGERS);
        renderPassengerLedger();
    } catch (error) {
        if (request !== FD_LEDGER_REQUEST) return;
        message.textContent = 'Could not load passengers. Please use the reload button to try again.';
    } finally {
        if (request === FD_LEDGER_REQUEST) panel.setAttribute('aria-busy', 'false');
    }
}

// ── The filter bar ───────────────────────────────────────────────────────
// Independent client-side axes over the rows already loaded:
//   FDL_STATE   ticketed / no PNR / flagged / all
//   FDL_FROM/TO airline view: departure date; single flight: ticket purchase
//               date. No PNR falls outside a single-flight purchase range.
let FDL_STATE = 'all';
let FDL_FROM = '', FDL_TO = '';

function passengerMatches(p, query) {
    const selectedAgent = document.getElementById('fdTableAgent')?.value || '';
    if (selectedAgent && String(p.agent_id || '') !== selectedAgent) return false;
    if (query && ![p.name, p.passport_no, p.phone, p.agent_name,
        p.agent_id ? 'EP-AG-' + String(p.agent_id).padStart(3, '0') : '', p.stage_label, p.exception_label,
        p.flight_category_name, ...(p.ticket_purchases || []).map(t => t.ticket_no)].join(' ').toLowerCase().includes(query)) return false;
    const tickets = p.ticket_purchases || [];
    if (FDL_STATE === 'ticketed'  && !tickets.length)      return false;
    if (FDL_STATE === 'pending'   && tickets.length)       return false;
    if (FDL_STATE === 'flagged'   && !p.exception_flag)    return false;
    if (FDL_FROM || FDL_TO) {
        const airlineView = String(FD_LEDGER_ID || '').startsWith('airline:');
        const flightDay = String(fdFindFlight(p.dashboard_flight_id ?? FD_LEDGER_ID)?.departure_at || '').slice(0, 10);
        const days = airlineView ? [flightDay].filter(Boolean) : tickets.map(t => String(t.purchase_date || '').slice(0, 10)).filter(Boolean);
        if (!days.some(d => (!FDL_FROM || d >= FDL_FROM) && (!FDL_TO || d <= FDL_TO))) return false;
    }
    return true;
}

// The count pill says what the bar did to the list. Only shown while
// something filters — a count of everything is not information.
function paintPassengerCount(shown, query) {
    const el = document.getElementById('fdPassengerCount');
    const active = !!(query || FDL_STATE !== 'all' || FDL_FROM || FDL_TO);
    el.style.display = active ? '' : 'none';
    el.classList.toggle('is-none', active && shown === 0);
    el.textContent = active ? `${shown} of ${FD_PASSENGERS.length}` : '';
}

function paintPassengerBar() {
    document.querySelectorAll('#fdlSeg button').forEach(b => b.classList.toggle('is-on', b.dataset.state === FDL_STATE));
    const airlineView = String(FD_LEDGER_ID || '').startsWith('airline:');
    const dateKind = document.getElementById('fdlDateKind');
    dateKind.textContent = airlineView ? 'Departure' : 'Ticket purchase';
    const description = airlineView ? 'Flight departure date' : 'Ticket purchase date';
    [['fdlFromText', 'from'], ['fdlToText', 'to'], ['fdlFrom', 'from'], ['fdlTo', 'to']].forEach(([id, edge]) => {
        const input = document.getElementById(id);
        input.setAttribute('aria-label', `${description} ${edge}`);
        input.title = `${description} — ${edge}`;
    });
    // A live date range tints the two boxes indigo — that is what says the
    // rows on screen are the ones the dates were actually applied to.
    document.getElementById('fdlDates').classList.toggle('is-open', !!(FDL_FROM || FDL_TO));
}

// A segment key sets the state; 'custom' reads the two date boxes. One date
// on its own means THAT DAY; fill the second box and it becomes a range;
// backwards bounds are swapped. 'clear' lifts the date filter — and so does
// emptying EITHER box: with one date meaning a day, a lone emptied box
// would only be refilled from the other, and the range could never be
// taken off without the seg buttons, which here filter something else.
function applyPassengerRange(key) {
    if (key === 'custom' || key === 'clear') {
        let from = key === 'clear' ? '' : document.getElementById('fdlFrom').value;
        let to   = key === 'clear' ? '' : document.getElementById('fdlTo').value;
        if      (from && !to) to   = from;
        else if (to && !from) from = to;
        else if (from > to)   { const t = from; from = to; to = t; }
        FDL_FROM = from; FDL_TO = to;
        setPassengerDateBoxes(from, to);
    } else {
        FDL_STATE = key;
    }
    paintPassengerBar();
    // Mid-load, the state is kept and the pending load renders with it.
    if (FD_LEDGER_ID !== null && document.getElementById('fdLedger').getAttribute('aria-busy') !== 'true') renderPassengerLedger();
}

function resetPassengerFilters(render = false) {
    FDL_STATE = 'all';
    FDL_FROM = FDL_TO = '';
    setPassengerDateBoxes('', '');
    const search = document.getElementById('fdPassengerSearch');
    search.value = '';
    document.getElementById('fdlSearchWrap').classList.remove('has-q');
    paintPassengerBar();
    if (render && FD_LEDGER_ID !== null && document.getElementById('fdLedger').getAttribute('aria-busy') !== 'true') renderPassengerLedger();
}

function printPassengerLedger(event) {
    if (!String(FD_LEDGER_ID || '').startsWith('airline:')) return true;
    event?.preventDefault();
    const query = document.getElementById('fdPassengerSearch').value.trim().toLowerCase();
    const passengers = FD_PASSENGERS.filter(passenger => passengerMatches(passenger, query));
    passengers.sort((a, b) => {
        const departure = passenger => parseIso(fdFindFlight(passenger.dashboard_flight_id)?.departure_at)?.getTime() ?? Number.NEGATIVE_INFINITY;
        return departure(b) - departure(a);
    });
    if (!passengers.length) {
        Swal.fire('No passengers', 'No passengers match the current airline filters.', 'info');
        return false;
    }
    if (!window.cfPrintList) {
        Swal.fire('Print unavailable', 'The Contract Flight print designer could not be loaded.', 'error');
        return false;
    }
    const headers = [
        {key: 'departure', label: 'Departure'},
        {key: 'passenger', label: 'Passenger / Passport'},
        {key: 'agent', label: 'Agent / Travel Details'},
        {key: 'tickets', label: 'Tickets / PNR'},
        {key: 'route', label: 'Route'},
        {key: 'status', label: 'Current Status'},
        {key: 'charge', label: 'Charge & Cost'},
        {key: 'profit', label: 'Cost / Profit'},
    ];
    const records = passengers.map(passenger => {
        const flight = fdFindFlight(passenger.dashboard_flight_id) || {};
        const departure = parseIso(flight.departure_at);
        const tickets = passenger.ticket_purchases || [];
        const ticketCost = tickets.filter(ticket => ticket.status !== 'cancelled').reduce((sum, ticket) => sum + Number(ticket.amount || 0), 0);
        const route = flight.route_path || [flight.from?.code, flight.to?.code].filter(Boolean).join(' → ') || 'Route pending';
        return {
            id: `airline-${flight.id}-${passenger.id}`,
            row: null,
            table: 0,
            date: String(flight.departure_at || '').slice(0, 10),
            category: passenger.flight_category_name || flight.category || 'Other',
            cells: {
                departure: `<b>${esc(fmtFull(departure))}</b><br>${departure ? esc(fmtTime(departure)) : 'Date not set'}`,
                passenger: `<b>${esc(passenger.name || 'Passenger')}</b><br>${esc(passenger.passport_no || 'Passport not recorded')}`,
                agent: `<b>${esc(passenger.agent_name || 'Direct')}</b>${passenger.agent_id ? `<br>EP-AG-${String(passenger.agent_id).padStart(3, '0')}` : ''}`,
                tickets: tickets.length ? tickets.map(ticket => esc(ticket.ticket_no || 'PNR pending')).join('<br>') : 'No PNR',
                route: `<b>${esc(flight.flight_number || '—')}</b><br>${esc(route)}`,
                status: `${esc(passenger.stage_label || 'Stage pending')}${passenger.exception_label ? `<br><b style="color:#dc2626">${esc(passenger.exception_label)}</b>` : ''}`,
                charge: ledgerChargeCost(passenger, ticketCost),
                profit: `${ledgerMoney((Number(passenger.total_travel_cost) || 0) + (Number(passenger.penalty_borne) || 0))}<br>Profit ${ledgerMoney(passenger.profit)}`,
            },
        };
    });
    const airline = fdFindFlight(passengers[0].dashboard_flight_id)?.airline || 'Airline';
    window.cfPrintList({headers, records, period: {from: FDL_FROM, to: FDL_TO}, title: `${airline} Passenger Ledger`});
    return false;
}

// The visible text box and the hidden date input are two halves of one
// control and must never disagree, so nothing sets one without the other.
function setPassengerDateBoxes(from, to) {
    [['fdlFrom', 'fdlFromText', from], ['fdlTo', 'fdlToText', to]].forEach(([nativeId, textId, ymd]) => {
        const native = document.getElementById(nativeId);
        const text   = document.getElementById(textId);
        if (native) native.value = ymd || '';
        if (text) { text.value = ymd ? ledgerDMY(ymd) : ''; markPassengerDateBad(text, false); }
    });
}
function markPassengerDateBad(text, bad) {
    text.classList.toggle('is-bad', bad);
    const wrap = text.closest('.fdl-date');
    if (wrap) wrap.classList.toggle('is-bad', bad);
}
// "2026-09-16" → "16/09/2026". String surgery, never new Date(): a bare
// Y-m-d parses as UTC and would print the day before in a negative offset.
function ledgerDMY(ymd) {
    const m = String(ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : (ymd || '');
}
// dd/mm/yyyy in, Y-m-d out. '' for an empty box, null for a typo — the
// caller tells those apart: empty is a cleared bound, null holds on screen.
function parsePassengerDate(str) {
    const t = String(str || '').trim();
    if (!t) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(t)) return t;
    const m = t.match(/^(\d{1,2})\D(\d{1,2})\D(\d{4})$/) || t.match(/^(\d{2})(\d{2})(\d{4})$/);
    if (!m) return null;
    const d = m[1].padStart(2, '0'), mo = m[2].padStart(2, '0');
    if (+d < 1 || +d > 31 || +mo < 1 || +mo > 12) return null;
    return m[3] + '-' + mo + '-' + d;
}

function renderPassengerLedger() {
    const query = document.getElementById('fdPassengerSearch').value.trim().toLowerCase();
    const showDeparture = String(FD_LEDGER_ID || '').startsWith('airline:');
    const departureTime = p => parseIso(fdFindFlight(p.dashboard_flight_id ?? FD_LEDGER_ID)?.departure_at)?.getTime() ?? Number.NEGATIVE_INFINITY;
    const passengers = FD_PASSENGERS.filter(p => passengerMatches(p, query));
    if (showDeparture) passengers.sort((a, b) => departureTime(b) - departureTime(a));
    document.getElementById('fdPassengerDepartureCol').hidden = !showDeparture;
    document.getElementById('fdPassengerDepartureHeading').hidden = !showDeparture;
    paintPassengerCount(passengers.length, query);
    document.getElementById('fdLedgerMessage').hidden = true;
    document.getElementById('fdPassengerTable').hidden = false;
    // The route is the batch's: every passenger on a contract flight flies the
    // flight's sector. Three lines, as the owner asked: the flights table's
    // flag · code → code · flag line, then one line per airport name, each
    // clipped with an ellipsis (the full name and the arrival sit in the
    // tooltips). The "(DAC)" suffix is dropped — the code is on line one.
    document.getElementById('fdPassengerBody').innerHTML = passengers.map((p, i) => {
    const flight = fdFindFlight(p.dashboard_flight_id ?? FD_LEDGER_ID) || {};
    const arrival = parseIso(flight.arrival_at);
    const airportName = (ap, fallback) => (ap?.name || fallback).replace(/\s*\([A-Z0-9]{3,4}\)\s*$/, '');
    const fromName = airportName(flight.from, 'Origin pending'), toName = airportName(flight.to, 'Destination pending');
    // Departure line, same dress as the Manage Flights list ("18 Sep 2026,
    // 05:16 PM") so the passenger reads the same on both screens.
    const dep = parseIso(flight.departure_at);
    const depLine = fmtDeparture(dep);
    const departureCell = `<td class="fd-nowrap" data-sort="${dep ? dep.getTime() : ''}" title="${esc(depLine)}"${showDeparture ? '' : ' hidden'}>${esc(fmtFull(dep))}<small>${dep ? esc(fmtTime(dep)) : 'Date not set'}</small></td>`;
    // Route exactly as Flights & Passengers draws it (owner, 2026-10-03: "should
    // look like the passenger table, same") — twin of passenger-row: flags and
    // codes, the date, the flight · via, then the exception flag in red.
    const via = flight.route_path ? String(flight.route_path).split(/→|->/).map(x => x.trim()).slice(1, -1) : [];
    const flightBits = [`<span class="mfp-rt-flight">${esc(flight.flight_number || '—')}</span>`,
        via.length ? `<span class="mfp-rt-via" title="${esc(flight.route_path)}">via ${esc(via.join(', '))}</span>` : ''].filter(Boolean);
    const routeCell = `<td><div class="mfp-rt" title="${esc(fromName)} → ${esc(toName)}${arrival ? `\nArrival: ${esc(fmtTableDate(arrival))}, ${esc(fmtTime(arrival))}` : ''}">${flagImg(flight.from, 'fdc-flag')}${esc(flight.from?.code || '—')}<i class="fas fa-plane mfp-tk-plane"></i>${esc(flight.to?.code || '—')}${flagImg(flight.to, 'fdc-flag')}</div>
                <div class="mfp-ln"><i class="fas fa-plane-departure mfp-ico"></i>${flightBits.join('<span class="mfp-tk-sep">·</span>')}</div>
                ${p.exception_label ? `<div class="mfp-ln mfp-rt-flag"><i class="fas fa-triangle-exclamation mfp-ico"></i>${esc(p.exception_label)}</div>` : ''}</td>`;

        const tickets = p.ticket_purchases || [];
        const ticketCost = tickets.filter(t => t.status !== 'cancelled').reduce((sum, t) => sum + Number(t.amount || 0), 0);
        const statusCell = `<td class="fdl-passenger-status"><span>${esc(p.stage_label || 'Stage pending')}</span>${p.exception_label ? `<small>${esc(p.exception_label)}</small>` : ''}</td>`;
        return `<tr class="fdl-passenger-row mfp-row">
            ${departureCell}
            <td class="mfp-sl">${i + 1}</td>
            ${(([pc, ac]) => `<td>${pc}</td><td>${ac}</td>`)(ledgerPassengerCells(p))}
            <td>${(p.ticket_slots || []).length > 1 ? ledgerTicketLegs(p, flight, tickets) : ledgerSingleTickets(tickets)}</td>
            ${routeCell}
            ${statusCell}
            <td data-sort="${Number(p.agent_total ?? p.agent_price) || 0}">${ledgerChargeCost(p, ticketCost)}</td>
            <td class="mfp-num" data-sort="${(Number(p.total_travel_cost) || 0) + (Number(p.penalty_borne) || 0)}"><span class="mfp-cost-total" title="Travel cost">${ledgerMoney((Number(p.total_travel_cost) || 0) + (Number(p.penalty_borne) || 0))}</span><span class="mfp-profit${Number(p.profit) < 0 ? ' neg' : ''}" title="Profit = Charge − Cost">Profit ${ledgerMoney(p.profit)}</span></td>
            <td><div class="mfp-actions">
                <button type="button" class="mfp-btn blue" onclick="openPassengerSheet(${Number(p.id)}); printPassengerSheet();" title="Print ${esc(p.name || 'this passenger')}'s record"><i class="fas fa-print"></i></button>
                <button type="button" class="mfp-btn blue" onclick="openPassengerSheet(${Number(p.id)})" title="Open the full record for ${esc(p.name || 'this passenger')}"><i class="fas fa-eye"></i></button>
            </div></td>
        </tr>${ledgerPassengerRoute(p)}`;
    }).join('') || '<tr><td colspan="10" class="fd-table-empty">No passengers found for this flight or search.</td></tr>';
    // Keep the column order the user picked across every repaint.
    if (window.cfSortReapply) cfSortReapply(document.getElementById('fdPassengerTable'));
    if (window.mfpTicketLogos) mfpTicketLogos(document.getElementById('fdPassengerBody'));
}

function closeFlightLedger() {
    hideDashboardPassengers();
    ++FD_LEDGER_REQUEST;
    FD_LEDGER_ID = null;
    document.querySelectorAll('#fdGrid [data-flight-id]').forEach(card => card.setAttribute('aria-pressed', 'false'));
    document.querySelectorAll('#fdGrid [data-airline-key]').forEach(card => card.setAttribute('aria-pressed', 'false'));
    document.getElementById('fdLedger').hidden = true;
    document.getElementById('fdOverview').hidden = false;
    if (FD_LEDGER_TRIGGER?.isConnected) FD_LEDGER_TRIGGER.focus({preventScroll:true});
    document.getElementById('fdOverview').scrollIntoView({behavior:'smooth', block:'start'});
}

for (const container of [document.getElementById('fdGrid'), document.getElementById('fdTableBody'), ...Object.keys(FD_HISTORY).map(section => document.getElementById('fd' + section + 'Body'))].filter(Boolean)) {
    const activate = event => {
        const item = event.target.closest('[data-flight-id], [data-airline-key]');
        if (!item) return;
        const link = event.target.closest('a');
        if (link && !link.getAttribute('href').startsWith('#flight-ledger-')) return;
        if (event.type === 'keydown' && (event.target !== item || !['Enter', ' '].includes(event.key))) return;
        event.preventDefault();
        FD_LEDGER_TRIGGER = item;
        // A new flight or airline opens on a clean bar; reload keeps it.
        resetPassengerFilters();
        if (item.dataset.airlineKey) openAirlineLedger(item.dataset.airlineKey);
        else openFlightLedger(item.dataset.flightId);
    };
    container.addEventListener('click', activate);
    container.addEventListener('keydown', activate);
}

// ── Wiring the bar ───────────────────────────────────────────────────────
// The bank statement's own bindings: a debounced search that Escape and the
// × clear, segment buttons, a date that applies the moment it is picked or
// typed, and a click on the wrapper's padding that still opens the picker.
(function wirePassengerBar() {
    const search = document.getElementById('fdPassengerSearch');
    const wrap   = document.getElementById('fdlSearchWrap');
    if (!search || !wrap) return;
    const busy = () => FD_LEDGER_ID === null || document.getElementById('fdLedger').getAttribute('aria-busy') === 'true';

    let timer = null;
    const run = () => {
        wrap.classList.toggle('has-q', search.value.trim() !== '');
        if (!busy()) renderPassengerLedger();
    };
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 140); });
    search.addEventListener('keydown', e => {
        if (e.key === 'Escape') { search.value = ''; clearTimeout(timer); run(); }
    });
    document.getElementById('fdlClear').addEventListener('click', () => {
        search.value = '';
        clearTimeout(timer);
        run();
        search.focus();
    });

    document.querySelectorAll('#fdlSeg button').forEach(b => {
        b.addEventListener('click', () => applyPassengerRange(b.dataset.state));
    });

    // The picker half. Picking a date applies it straight away; the
    // picker's own Clear empties the box, which lifts the range.
    document.querySelectorAll('#fdlFrom, #fdlTo').forEach(el => {
        el.addEventListener('change', () => applyPassengerRange(el.value ? 'custom' : 'clear'));
    });
    // A click on the wrapper's own padding opens the picker too. Guarded:
    // showPicker() throws without a user gesture in some browsers, and a
    // dead click beats an uncaught error in a filter bar.
    document.querySelectorAll('#fdlDates .fdl-date').forEach(box => {
        box.addEventListener('click', e => {
            if (e.target !== box) return;
            const native = box.querySelector('.fdl-date-native');
            if (native && native.showPicker) { try { native.showPicker(); } catch (_) {} }
        });
    });
    // The typed half. change fires on blur, so tabbing out applies it
    // exactly as Enter does. A typo holds on screen in red; an emptied box
    // lifts the range.
    [['fdlFromText', 'fdlFrom'], ['fdlToText', 'fdlTo']].forEach(([textId, nativeId]) => {
        const text = document.getElementById(textId);
        if (!text) return;
        const commit = () => {
            const ymd = parsePassengerDate(text.value);
            if (ymd === null) { markPassengerDateBad(text, true); return; }
            markPassengerDateBad(text, false);
            document.getElementById(nativeId).value = ymd;
            text.value = ymd ? ledgerDMY(ymd) : '';
            applyPassengerRange(ymd ? 'custom' : 'clear');
        };
        text.addEventListener('change', commit);
        text.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); commit(); } });
    });

    paintPassengerBar();
})();

// A click on a row's empty space flips THAT passenger's journey strip
// against the Status setting (owner, 2026-10-03) — the same row click as
// Flights & Passengers. Links, buttons and controls keep their own job.
document.addEventListener('click', event => {
    const row = event.target.closest('#fdPassengerTable tr.fdl-passenger-row');
    const strip = row?.nextElementSibling;
    if (!strip || !strip.classList.contains('fdl-route-row')) return;
    if (event.target.closest('a, button, input, select, textarea, label, [onclick], [role="button"]')) return;
    if (String(window.getSelection?.() || '').trim()) return;
    strip.classList.toggle('is-flipped');
});

// ── Status button: journey strips on / off ───────────────────────────────
// Remembered per browser. Default: collapsed; clicking a passenger row opens
// only that passenger's journey strip.
const FDL_JOURNEY_KEY = 'fdlJourneysOpenV2';
function applyLedgerJourneys() {
    let on = false;
    try { on = localStorage.getItem(FDL_JOURNEY_KEY) === '1'; } catch (e) { on = false; }
    const table = document.getElementById('fdPassengerTable');
    if (table) table.classList.toggle('fdl-journeys-off', !on);
    // The Status button speaks for every row again: one-row flips reset.
    table?.querySelectorAll('.fdl-route-row.is-flipped').forEach(tr => tr.classList.remove('is-flipped'));
    const btn = document.getElementById('fdlStatusBtn');
    if (btn) {
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.title = on ? 'Collapse every passenger journey' : 'Show every passenger journey';
        const label = btn.querySelector('span');
        if (label) label.textContent = on ? 'Hide all stages' : 'Show all stages';
    }
}
function toggleLedgerJourneys() {
    let on = false;
    try { on = localStorage.getItem(FDL_JOURNEY_KEY) === '1'; } catch (e) { on = false; }
    try { localStorage.setItem(FDL_JOURNEY_KEY, on ? '0' : '1'); } catch (e) { /* private window */ }
    applyLedgerJourneys();
}
document.addEventListener('DOMContentLoaded', applyLedgerJourneys);
