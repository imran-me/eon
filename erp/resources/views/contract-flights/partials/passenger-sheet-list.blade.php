{{-- The eye on a Flights & Passengers row opens the same A4 passenger record
     as the Flight Dashboard's Passenger Ledger (owner, 2026-09-30): same
     builder, same styles, same passenger endpoint. Wrapped in an IIFE so the
     dashboard's page globals stay local here. --}}
@php
    $cfpSheetFlights = [];
    foreach ($datas as $f) {
        try { $cfpSheetFlights[$f->id] = $f->sheetFlightPayload(); } catch (\Throwable) { $cfpSheetFlights[$f->id] = ['flight_number' => $f->flight_number]; }
    }
@endphp
<style>
@include('contract-flights.partials.passenger-sheet-styles')
/* Edit passenger, Edit flight, Delete and Cancel from flight live in the eye's sheet bar
   (owner, 2026-09-30). Hidden, not removed: the bar clicks these originals.
   One row on the A4 width (owner, 2026-10-19): Delete in red, a divider, then Print / Close. */
.fdl-sheet-btn.cfp-sheet-act { padding: 6px 10px; }
.fdl-sheet-btn.cfp-sheet-act.is-danger { background: #b91c1c; border-color: rgba(252,165,165,.5); }
.fdl-sheet-btn.cfp-sheet-act.is-danger:hover { background: #dc2626; }
.cfp-sheet-sep { width: 1px; height: 20px; background: rgba(255,255,255,.35); flex-shrink: 0; }
/* Two sheets live on this page (the eye's and Re-assign's confirmation):
   only the open one prints. */
@media print { body.fdl-printing .fdl-sheet-overlay[hidden] { display: none !important; } }
</style>
<script>
(function () {
    const FD_LOGO_BASE = @json(asset('airline-logos'));
    const FD_FLAG_BASE = @json(asset('flags'));
    const SHEET_URL = @json(route('role.contract-flight-passengers.index', ['role' => $role, 'contractFlight' => '__FLIGHT__']));
    const SHEET_FLIGHTS = @json((object) $cfpSheetFlights);
    const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function parseIso(iso) { if (!iso) return null; const d = new Date(iso); return isNaN(d.getTime()) ? null : d; }
    function fmtTime(d) {
        if (!d) return '';
        let h = d.getHours(); const m = String(d.getMinutes()).padStart(2, '0');
        const ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
        return `${String(h).padStart(2, '0')}:${m} ${ap}`;
    }
    function fmtDeparture(d) { return d ? `${String(d.getDate()).padStart(2, '0')} ${MONTHS[d.getMonth()]} ${d.getFullYear()}, ${fmtTime(d)}` : 'Date pending'; }
    function fmtTableDate(d) { return d ? `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}` : '—'; }
    function fmtMoney(v) { return Number(Math.abs(v) || 0).toLocaleString('en-BD', { maximumFractionDigits: 0 }); }
    function ledgerMoney(value) { return `${Number(value) < 0 ? '−' : ''}৳${esc(fmtMoney(value))}`; }

@include('contract-flights.partials.airline-brands-script')
@include('contract-flights.partials.passenger-sheet-script')

    // The row's own Edit flight / Delete / Cancel-from-flight buttons, repeated
    // in the sheet's bar. Each one closes the sheet and clicks the original,
    // so it runs exactly the handler the table runs.
    const BAR_ACTIONS = [['.fa-user-pen', 'fa-user-pen', 'Edit passenger'], ['.fa-pencil-alt', 'fa-pencil-alt', 'Edit flight'], ['.fa-user-xmark', 'fa-user-xmark', 'Cancel from flight'], ['.fa-trash', 'fa-trash', 'Delete', 'is-danger']];
    function sheetBarActions(eye) {
        const bar = fdSheetOverlay().querySelector('.fdl-sheet-bar');
        bar.querySelectorAll('.cfp-sheet-act, .cfp-sheet-sep').forEach(b => b.remove());
        const row = eye && eye.closest('tr');
        if (!row) return;
        const before = bar.querySelector('[data-sheet-print]');
        BAR_ACTIONS.forEach(([sel, icon, label, tone]) => {
            const original = row.querySelector('.mfp-actions ' + sel)?.closest('button, a');
            if (!original) return;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'fdl-sheet-btn cfp-sheet-act' + (tone ? ' ' + tone : '');
            b.innerHTML = `<i class="fas ${icon}" aria-hidden="true"></i> ${label}`;
            b.addEventListener('click', () => { closePassengerSheet(); original.click(); });
            bar.insertBefore(b, before);
        });
        if (bar.querySelector('.cfp-sheet-act')) {
            const sep = document.createElement('span');
            sep.className = 'cfp-sheet-sep';
            sep.setAttribute('aria-hidden', 'true');
            bar.insertBefore(sep, before);
        }
    }

    window.cfpOpenPassengerSheet = async function (flightId, passengerId, eye) {
        const flight = SHEET_FLIGHTS[flightId] || {};
        fdShowSheet('<p class="muted">Loading…</p>', flight.flight_number || 'Passenger');
        sheetBarActions(eye);
        try {
            const res = await fetch(SHEET_URL.replace('__FLIGHT__', encodeURIComponent(flightId)), { headers: { 'Accept': 'application/json' } });
            const p = ((await res.json()).passengers || []).find(x => String(x.id) === String(passengerId));
            if (!p) throw new Error('not found');
            fdShowSheet(passengerSheetHtml(p, flight), (p.name || 'Passenger') + ' · ' + (flight.flight_number || ''));
        } catch (e) {
            fdShowSheet('<p class="muted">Could not load this passenger. Please try again.</p>', flight.flight_number || 'Passenger');
        }
    };
    // Print straight from the row: open the same sheet, then print it.
    window.cfpPrintPassengerSheet = async function (flightId, passengerId, btn) {
        await window.cfpOpenPassengerSheet(flightId, passengerId, btn);
        printPassengerSheet();
    };
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && FD_SHEET_EL && !FD_SHEET_EL.hidden) closePassengerSheet(); });

@include('contract-flights.partials.ticket-logos-script')
    window.mfpTicketLogos();
})();
</script>
