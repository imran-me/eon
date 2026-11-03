// ── Passenger sheet ─────────────────────────────────────────────────────
// The eye on a row opens the whole record as an A4 page (owner,
// 2026-09-22) instead of expanding the row: the table stays one line per
// passenger and the detail gets a layout that is already the shape people
// print. Read straight from FD_PASSENGERS — the same objects the row is
// drawn from, so the sheet can never disagree with the table.

let FD_SHEET_EL = null;

function fdSheetOverlay() {
    if (FD_SHEET_EL) return FD_SHEET_EL;
    FD_SHEET_EL = document.createElement('div');
    FD_SHEET_EL.className = 'fdl-sheet-overlay';
    FD_SHEET_EL.hidden = true;
    FD_SHEET_EL.innerHTML = `<div class="fdl-sheet-bar is-strip">
            <strong>Passenger record</strong>
            <a class="fdl-sheet-btn" data-sheet-edit hidden><i class="fas fa-pen" aria-hidden="true"></i> Edit</a>
            <button type="button" class="fdl-sheet-btn is-icon" data-sheet-print title="Print" aria-label="Print"><i class="fas fa-print" aria-hidden="true"></i></button>
            <button type="button" class="fdl-sheet-btn is-icon" data-sheet-close title="Close" aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div><div class="fdl-sheet" role="document"></div>`;
    // Appended to <body> so the print rule can strip every sibling: a rule
    // that hid only the dashboard would still leave whatever wraps it.
    document.body.appendChild(FD_SHEET_EL);
    FD_SHEET_EL.addEventListener('click', e => {
        if (e.target.closest('[data-sheet-close]')) closePassengerSheet();
        if (e.target.closest('[data-sheet-print]')) printPassengerSheet();
    });
    return FD_SHEET_EL;
}

function closePassengerSheet() {
    if (FD_SHEET_EL) FD_SHEET_EL.hidden = true;
    document.body.classList.remove('fdl-printing');
}

function printPassengerSheet() {
    document.body.classList.add('fdl-printing');
    window.print();
    // Some browsers fire afterprint late; clearing on both paths keeps the
    // class from surviving a cancelled dialog and breaking the next print.
    window.addEventListener('afterprint', () => document.body.classList.remove('fdl-printing'), { once: true });
    setTimeout(() => document.body.classList.remove('fdl-printing'), 1500);
}

// Small builders. Every value goes through esc(); a passport name or an
// agent's is free text typed by a person and this page is printed and sent.
const shField = (k, v) => `<div class="fdl-sheet-f"><span class="k">${esc(k)}</span><span class="v">${esc(v == null || v === '' ? '—' : v)}</span></div>`;
// Same field, but the value is already markup (a flag, a logo).
const shFieldHtml = (k, html) => `<div class="fdl-sheet-f"><span class="k">${esc(k)}</span><span class="v">${html}</span></div>`;
const shSection = (title, body) => body ? `<h2>${esc(title)}</h2>${body}` : '';
const shGrid = fields => `<div class="fdl-sheet-grid">${fields.join('')}</div>`;
const shDate = iso => { const d = parseIso(iso); return d ? fmtTableDate(d) : '—'; };
// A status still reads as a word; only its colour changes, so the sheet
// stays black on white and the eye is drawn to the exceptions.
const shState = st => {
    const s = String(st || '').toLowerCase();
    const cls = ['confirm', 'confirmed', 'purchased', 'paid'].includes(s) ? 'ok'
        : ['cancelled', 'expired', 'deported', 'deported_we_bear'].includes(s) ? 'bad'
        : ['due', 'pending', 'draft', 'booked'].includes(s) ? 'warn' : 'muted';
    return `<span class="${cls}">${esc(st || '—')}</span>`;
};
// Flags and airline marks come from the same public/ folders the flight
// cards use. A country or carrier with no file on disk drops its image
// rather than showing a broken one.
const shFlagCc = cc => cc ? `<img class="fl" src="${FD_FLAG_BASE}/${esc(String(cc).toLowerCase())}.png" alt="" loading="lazy" onerror="this.remove()">` : '';
const shAp = ap => `${shFlagCc(ap?.country)}${esc(ap?.code || '—')}`;
const shAirlineMark = name => {
    const brand = (typeof airlineBrand === 'function' ? airlineBrand(name) : { slug: null });
    return brand.slug ? `<img class="al" src="${FD_LOGO_BASE}/${esc(brand.slug)}.png" alt="" loading="lazy" onerror="this.remove()">` : '';
};

function passengerSheetHtml(p, flight) {
    const tickets = (p.ticket_purchases || []);
    const ticketCost = tickets.filter(t => t.status !== 'cancelled').reduce((s, t) => s + Number(t.amount || 0), 0);
    const dep = parseIso(flight.departure_at);
    const arr = parseIso(flight.arrival_at);

    const brand = (typeof airlineBrand === 'function' ? airlineBrand(flight.airline) : { slug: null });
    const initials = String(flight.airline || 'EP').replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase() || 'EP';
    const headMark = brand.slug
        ? `<img class="fdl-mark" src="${FD_LOGO_BASE}/${esc(brand.slug)}.png" alt="${esc(flight.airline || '')}" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'fdl-mark-txt',textContent:${JSON.stringify(initials)}}))">`
        : `<span class="fdl-mark-txt">${esc(initials)}</span>`;

    // route_hops carries every airport with its own country, so the VIA
    // airport gets a flag too. Falls back to from/to on a payload old enough
    // not to have it — a flagless strip, never a missing one.
    const hops = (flight.route_hops || []).length
        ? flight.route_hops
        : [{ code: flight.from?.code, flag: flight.from?.country }, { code: flight.to?.code, flag: flight.to?.country }].filter(h => h.code);
    const routeStrip = hops.map((h, i) => `${i ? '<span class="sep">→</span>' : ''}<span class="hop">${shFlagCc(h.flag)}${esc(h.code)}</span>`).join('');

    const ticketRows = tickets.length ? tickets.map(t => `<tr>
            <td><b>${esc(t.ticket_no || 'PNR pending')}</b> ${shState(t.status)}</td>
            <td>${esc(t.route || '—')}${(t.legs || []).map(l => `<br><span class="muted">${esc(l.from_location || '—')} → ${esc(l.to_location || '—')} · ${esc(shDate(l.travel_date))}</span>`).join('')}${t.was_dates ? `<br><span class="muted">${esc(t.was_dates)}</span>` : ''}</td>
            <td>${esc([t.ticket_class, t.trip_type].filter(Boolean).join(' · ') || '—')}</td>
            <td>${esc(shDate(t.purchase_date))}</td>
            <td>${esc(t.vendor || t.portal || '—')}</td>
            <td class="num">${ledgerMoney(t.amount)}</td>
            <td class="num">${ledgerMoney(t.paid_amount)}</td>
            <td class="num">${ledgerMoney(t.due_amount)}</td>
            <td>${esc(shDate(t.cost_due_date))}</td>
        </tr>`).join('') : '<tr><td colspan="9" class="muted">No tickets linked.</td></tr>';

    const costLines = [['Tickets', ticketCost], ['Manpower', p.manpower_cost], ['Boarding', p.boarding_cost],
        ['Immigration', p.immigration_cost], ['Card fee', p.manpower_card_fee], [p.other_title || 'Other', p.other_cost]]
        .filter(([, v]) => Number.isFinite(Number(v)) && Number(v) !== 0);
    if (Number(p.reissue_cost)) costLines.push(['Reissue (not in travel cost)', p.reissue_cost]);

    const services = Object.values(p.service_costs || {});
    const serviceRows = services.length ? services.map(s => `<tr>
            <td>${esc(s.title || s.cost_type || '—')}</td>
            <td>${esc(s.vendor_name || '—')}</td>
            <td class="num">${ledgerMoney(s.amount)}</td>
            <td class="num">${ledgerMoney(s.paid_amount)}</td>
            <td class="num">${ledgerMoney(s.due_amount)}</td>
            <td>${shState(s.payment_status)}</td>
            <td>${esc(s.bank_name || '—')}</td>
            <td>${esc(s.incurred_on ? shDate(s.incurred_on) : '—')}</td>
            <td>${esc(s.reference_no || '—')}</td>
        </tr>`).join('') : '';

    // The page is one sheet, so the journey shows the most recent steps and
    // says how many it left behind rather than running onto a second page.
    const allEvents = (p.journey_events || []).slice().reverse();
    const events = allEvents.slice(0, 4);
    const profitNeg = Number(p.profit) < 0;

    return `<div class="fdl-sheet-hd">
            <div><h1>${esc(p.name || 'Passenger not assigned')}</h1>
                <p class="sub">Passenger record · ${esc(p.passport_no || 'passport pending')}</p></div>
            <div class="ref">${headMark}<span><b>${esc(flight.flight_number || '—')}</b>${esc(flight.airline || 'Airline pending')}</span></div>
        </div>

        <div class="fdl-sheet-route">${routeStrip}<span class="when">${esc(dep ? fmtDeparture(dep) : 'Date pending')}</span></div>

        ${shSection('Passenger', shGrid([
            shField('Passport no', p.passport_no), shField('Category', p.flight_category_name), shField('Type', p.flight_category_type),
            shField('Handling', p.handling_label), shField('Class', p.ticket_class_label), shField('Contact phone', p.contact_phone || p.phone),
            shField('Stage', p.stage_label), shField('Exception', p.exception_label), shField('Contract confirmed', p.contract_confirmed ? shDate(p.contract_confirmed_at) : 'No'),
        ]))}

        ${shSection('Agent & flight', shGrid([
            shField('Billed to', p.agent_name || 'Direct'),
            shField('Agent ID', p.agent_id ? 'EP-AG-' + String(p.agent_id).padStart(3, '0') : '—'),
            shField('Tickets required', p.tickets_required),
            shFieldHtml('Airline', `${shAirlineMark(flight.airline)}${esc(flight.airline || '—')}`),
            shFieldHtml('From', `${shAp(flight.from)} <span class="muted">${esc(flight.from?.name || '')}</span>`),
            shFieldHtml('To', `${shAp(flight.to)} <span class="muted">${esc(flight.to?.name || '')}</span>`),
            shField('Flight status', flight.status),
            shField('Full route', flight.route_path || `${flight.from?.code || '—'} → ${flight.to?.code || '—'}`),
            ...(p.previous_flights || []).map(pf => shField('Was on', `${pf.flight || '—'} · ${pf.departure || 'no date'}`)),
            shField('Arrival', arr ? `${fmtTableDate(arr)}, ${fmtTime(arr)}` : '—'),
        ]))}

        ${shSection('Tickets', `<table><thead><tr>
            <th>PNR</th><th>Route / legs</th><th>Class · trip</th><th>Purchased</th><th>Seller</th>
            <th class="num">Cost</th><th class="num">Paid</th><th class="num">Due</th><th>Cost due</th>
        </tr></thead><tbody>${ticketRows}</tbody></table>`)}

        ${serviceRows ? shSection('Services & responsible people', `<table><thead><tr>
            <th>Service</th><th>Responsible / vendor</th><th class="num">Fee</th><th class="num">Paid</th><th class="num">Due</th>
            <th>Status</th><th>Bank</th><th>Cost date</th><th>Reference</th>
        </tr></thead><tbody>${serviceRows}</tbody></table>`) : ''}

        ${shSection('Cost breakdown', `<table><tbody>
            ${costLines.map(([label, v]) => `<tr><td>${esc(label)}</td><td class="num">${ledgerMoney(v)}</td></tr>`).join('') || '<tr><td class="muted">Nothing costed yet.</td><td class="num">—</td></tr>'}
        </tbody><tfoot>
            <tr><td>Travel cost</td><td class="num">${ledgerMoney(p.total_travel_cost)}</td></tr>
        </tfoot></table>`)}

        ${shSection('Money', `<div class="fdl-money">
            <div><span class="k">Agent charge</span><span class="v">${ledgerMoney(p.agent_total ?? p.agent_price)}</span></div>
            <div><span class="k">Travel cost</span><span class="v">${ledgerMoney(p.total_travel_cost)}</span></div>
            <div class="m-due"><span class="k">Agent due</span><span class="v">${ledgerMoney(p.agent_due)}</span></div>
            <div class="m-profit${profitNeg ? ' neg' : ''}"><span class="k">Profit</span><span class="v">${ledgerMoney(p.profit)}</span></div>
        </div>`)}

        ${p.remarks ? shSection('Remarks', `<div class="fdl-sheet-f"><span class="v">${esc(p.remarks)}</span></div>`) : ''}

        ${events.length ? shSection('Journey', `<table><thead><tr><th>When</th><th>Event</th><th>Note</th><th>By</th></tr></thead><tbody>
            ${events.map(e => `<tr><td>${esc(shDate(e.occurred_at))}</td><td>${esc(e.label || e.code || '—')}</td><td>${esc(e.note || '—')}</td><td>${esc(e.created_by || '—')}</td></tr>`).join('')}
            ${allEvents.length > events.length ? `<tr><td colspan="4" class="muted">+ ${allEvents.length - events.length} earlier step(s) — see the passenger's journey on screen.</td></tr>` : ''}
        </tbody></table>`) : ''}

        <p class="fdl-sheet-note">Epal ERP · Contract Flight · generated from the passenger ledger on ${esc(fmtTableDate(new Date()))}. Figures are the ones held against this passenger at the time of printing.</p>`;
}

/**
 * Show a built sheet. `edit` is the URL the bar's Edit button goes to, or
 * null to hide it — a passenger record has no single thing to edit, a flight
 * does.
 */
function fdShowSheet(html, title, { flow = false, edit = null } = {}) {
    const overlay = fdSheetOverlay();
    const sheet = overlay.querySelector('.fdl-sheet');
    sheet.classList.toggle('is-flow', flow);
    sheet.innerHTML = html;
    overlay.querySelector('.fdl-sheet-bar strong').textContent = title;
    const editBtn = overlay.querySelector('[data-sheet-edit]');
    editBtn.hidden = !edit;
    if (edit) editBtn.href = edit;
    overlay.hidden = false;
    overlay.scrollTop = 0;
}
