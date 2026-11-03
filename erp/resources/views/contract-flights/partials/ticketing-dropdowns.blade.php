{{-- Ticketing's dropdowns on Contract Flight (owner, 2026-10-08: "pixel to pixel same,
     just the dropdowns"). The open list, the option rows, and the mark in front of each
     option — airline logo, route flags, bank logo, coloured icon — are copied from
     ticket_direct_sales/create (ds-rt / ds-blogo / ds-oic / ds-rflag, routeFlagSelect,
     airlineLogoSelect, bankLogoSelect, DS_ICONS). Include right AFTER select2.min.js:
     it wraps $.fn.select2 on this page only, so every picker the flight screens
     initialise later picks the template up without each call site being touched.
     A call that brings its own templateResult (passport holder) keeps it. --}}
<style>
.ds-rt{display:inline-flex;align-items:center;gap:7px;max-width:100%;}
.ds-rt-t{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ds-blogo{width:20px;height:20px;border-radius:5px;object-fit:contain;background:#fff;flex-shrink:0;box-shadow:0 0 0 1px rgba(15,23,42,.1);}
.ds-oic{width:20px;height:20px;border-radius:5px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0;}
.ds-blogo-x{display:inline-block;background:#eef2ff;}
.ds-alogo-x{display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:8px;font-weight:800;letter-spacing:.02em;}
.ds-rflag{width:18px;height:12px;border-radius:2px;object-fit:cover;flex-shrink:0;box-shadow:0 0 0 1px rgba(15,23,42,.12);}
.select2-selection__rendered .ds-rt{vertical-align:middle;}
.select2-container--default .select2-selection--single{height:42px!important;border:1px solid #d2d8e7!important;border-radius:6px!important;display:flex!important;align-items:center;background:#fff;font-family:'DM Sans',sans-serif;font-size:13px;box-sizing:border-box;}
.select2-container--default .select2-selection--single .select2-selection__rendered{width:100%;line-height:40px!important;padding-left:12px!important;padding-right:30px!important;color:#1a2035!important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px!important;}
.select2-container--default .select2-selection--single .select2-selection__arrow{height:40px!important;}
.select2-container--default .select2-selection--single .select2-selection__placeholder{color:#9aa3bc;}
.select2-container--default.select2-container--focus .select2-selection--single,.select2-container--default.select2-container--open .select2-selection--single{border-color:#4f46e5!important;box-shadow:0 0 0 2px rgba(79,70,229,.15)!important;}
.select2-container--default.select2-container--disabled .select2-selection--single{background:#f1f5f9;}
/* Passenger cards and the edit modal: Ticketing's passenger-card size. */
.pb-card .select2-container--default .select2-selection--single,#passengerEditOverlay .select2-container--default .select2-selection--single{height:32px!important;border-radius:5px!important;font-size:12px;}
.pb-card .select2-container--default .select2-selection--single .select2-selection__rendered,#passengerEditOverlay .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:30px!important;padding-left:10px!important;font-size:12px!important;}
.pb-card .select2-container--default .select2-selection--single .select2-selection__arrow,#passengerEditOverlay .select2-container--default .select2-selection--single .select2-selection__arrow{height:30px!important;}
/* Filter bars keep their compact height. */
.mfp-filter .select2-container--default .select2-selection--single,.fd-filter-field .select2-container--default .select2-selection--single{height:28px!important;border-radius:5px!important;}
.mfp-filter .select2-container--default .select2-selection--single .select2-selection__rendered,.fd-filter-field .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:26px!important;font-size:12px!important;}
.mfp-filter .select2-container--default .select2-selection--single .select2-selection__arrow,.fd-filter-field .select2-container--default .select2-selection--single .select2-selection__arrow{height:26px!important;}
/* Open list: the sharp version from layout/ticketing-tabs (hairline between options, crisp edge). */
    .select2-container.select2-container--open .select2-dropdown{border:1px solid #c7cdd8;border-radius:8px;overflow:hidden;background:#fff;
        box-shadow:0 12px 32px rgba(15,23,42,.18),0 2px 6px rgba(15,23,42,.08);}
    .select2-container.select2-container--open .select2-dropdown--below{margin-top:3px;}
    .select2-container.select2-container--open .select2-dropdown--above{margin-top:-3px;}
    .select2-container--open .select2-search--dropdown{padding:8px;background:#f8fafc;border-bottom:1px solid #e2e8f0;}
    .select2-container--open .select2-search--dropdown .select2-search__field{height:32px;border:1px solid #cbd5e1!important;border-radius:6px;
        padding:4px 10px;font-size:12.5px;background:#fff;outline:none;}
    .select2-container--open .select2-search--dropdown .select2-search__field:focus{border-color:#6366f1!important;box-shadow:0 0 0 3px rgba(99,102,241,.15);}
    .select2-container--open .select2-results > .select2-results__options{padding:0!important;max-height:260px!important;}
    /* A grouped list (Sold to: Agents / Customers) nests one list per group. Only the outer list
       scrolls — a capped, scrolling inner list is what drew the groups on top of each other. */
    .select2-container--open .select2-results__options--nested{max-height:none!important;overflow:visible!important;padding:0!important;}
    .select2-container--open .select2-results__option[role=group]{padding:0!important;border-bottom:0;background:none!important;box-shadow:none!important;}
    .select2-container--open .select2-results__option{position:relative;margin:0!important;padding:8px 12px 8px 14px;border-radius:0!important;
        font-size:12.5px;line-height:1.35;color:#1e293b;border-bottom:1px solid #eef1f5;}
    .select2-container--open .select2-results__option:last-child{border-bottom:0;}
    .select2-container--default.select2-container--open .select2-results__option--highlighted.select2-results__option--selectable,
    .select2-container--default.select2-container--open .select2-results__option--highlighted[aria-selected]{background:#eef2ff;color:#312e81;
        box-shadow:inset 3px 0 0 #4f46e5;}
    .select2-container--default.select2-container--open .select2-results__option--selected{background:#f5f3ff;color:#4c1d95;font-weight:700;padding-right:28px;}
    .select2-container--default.select2-container--open .select2-results__option--selected::after{content:'✓';position:absolute;right:11px;top:50%;
        transform:translateY(-50%);font-size:12px;color:#4f46e5;}
    .select2-container--open .select2-results__option[role=group] .select2-results__group{padding:6px 12px;font-size:10.5px;font-weight:800;
        letter-spacing:.05em;text-transform:uppercase;color:#64748b;background:#f8fafc;border-bottom:1px solid #e2e8f0;}
    .select2-container--open .select2-results > .select2-results__options::-webkit-scrollbar{width:8px;}
    .select2-container--open .select2-results > .select2-results__options::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px;border:2px solid #fff;}
    html.dark .select2-container.select2-container--open .select2-dropdown{background:#1e293b;border-color:#475569;}
    html.dark .select2-container--open .select2-search--dropdown{background:#0f172a;border-bottom-color:#334155;}
    html.dark .select2-container--open .select2-results__option{color:#e2e8f0;border-bottom-color:#334155;}
    html.dark .select2-container--default.select2-container--open .select2-results__option--highlighted.select2-results__option--selectable{background:#312e81;color:#fff;}
</style>
<script>
(function () {
// A page whose jQuery comes from the deferred app bundle (or that never loads Select2)
// is not ready yet: wait for the page, load Select2 if it is missing, then run.
// Where jQuery and Select2 are already here (the flight pages) it runs at once, as before.
if (!window.jQuery || !jQuery.fn.select2) {
    const load = (src, then) => { const s = document.createElement('script'); s.src = src; s.onload = then; document.head.appendChild(s); };
    const withSelect2 = () => {
        if (jQuery.fn.select2) return install();
        if (!document.querySelector('link[href*="select2"]')) {
            const css = document.createElement('link');
            css.rel = 'stylesheet'; css.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
            document.head.appendChild(css);
        }
        load('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', install);
    };
    document.addEventListener('DOMContentLoaded', function () {
        // a page with no jQuery at all (Party Statement, Contract File Sales): the
        // picker needs it — the same jQuery the other Travels pages load
        if (window.jQuery) withSelect2();
        else load('https://code.jquery.com/jquery-3.7.1.min.js', withSelect2);
    });
    return;
}
install();
function install() {
    if (!window.jQuery || !jQuery.fn.select2 || jQuery.fn.select2.__cfTd) return;
    // Airline marks in a scope of their own: some flight pages already declare
    // AIRLINE_BRANDS at top level, and a second const would be a SyntaxError.
    let brand = null, code = null;
    try {
        (function () {
            @include('contract-flights.partials.airline-brands-script')
            brand = airlineBrand; code = airlineCode;
        })();
    } catch (e) { brand = null; }

    const $ = jQuery;
    const esc = t => $('<span>').text(t).html();
    const FLAGS = @json(asset('flags')), LOGOS = @json(asset('airline-logos')), BANKS = @json(asset('bank-logos'));
    const BANK_BRANDS = [
        ['dutch-bangla', /dutch\s*-?\s*bangla|\bdbbl\b/i], ['islami', /islami/i], ['brac', /brac/i],
        ['city', /city\s*bank/i], ['dhaka', /dhaka\s*bank/i], ['bkash', /b\s*-?\s*kash/i],
        ['petty-cash', /petty\s*-?\s*cash/i], ['office-cash', /office\s*-?\s*cash/i], ['visa-dhaka', /credit\s*-?\s*card/i],
    ];
    // Same icons and colours as Ticketing's DS_ICONS, matched by class / id / name here.
    const ICONS = [
        ['.pb-nt-vendor, .pb-sc-vendor, #tkrVendor, [name$="vendor_id"], [id$="_vendor_id"]', ['fa-store', '#d97706']],
        ['.pb-nt-portal, #tkrPortal, [name$="portal_id"], [id$="_portal_id"]', ['fa-globe', '#0284c7']],
        ['.pb-agent, [name$="agent_id"], [name$="agent_ids[]"], [id$="_agent_id"], [id$="_agent_ids"]', ['fa-user-tie', '#059669']],
        ['#tkrFromAirport, .pb-route-from', ['fa-plane-departure', '#4f46e5']],
        ['#tkrToAirport, .pb-route-to', ['fa-plane-arrival', '#7c3aed']],
        ['[name$="bearer"], [id$="_bearer"]', ['fa-scale-balanced', '#475569']],
    ];
    const VALUE_ICONS = {
        purchased: ['fa-ticket', '#059669'], booked: ['fa-clock', '#0284c7'], pending: ['fa-hourglass-half', '#d97706'],
        draft: ['fa-pen', '#64748b'], cancelled: ['fa-ban', '#dc2626'], expired: ['fa-calendar-xmark', '#94a3b8'], confirm: ['fa-circle-check', '#059669'],
        'one-way': ['fa-arrow-right-long', '#4f46e5'], 'two-way': ['fa-right-left', '#7c3aed'], 'multi-city': ['fa-route', '#0891b2'],
        economy: ['fa-chair', '#0284c7'], premium: ['fa-couch', '#7c3aed'], business: ['fa-briefcase', '#b45309'], first: ['fa-crown', '#ca8a04'],
        due: ['fa-circle-exclamation', '#dc2626'], unpaid: ['fa-circle-exclamation', '#dc2626'], partial: ['fa-circle-half-stroke', '#d97706'], paid: ['fa-circle-check', '#059669'],
        open: ['fa-door-open', '#059669'], closed: ['fa-lock', '#64748b'], completed: ['fa-flag-checkered', '#059669'], active: ['fa-circle-play', '#059669'],
        agent: ['fa-user-tie', '#059669'], company: ['fa-building', '#4f46e5'], customer: ['fa-user', '#059669'], mixed: ['fa-scale-balanced', '#475569'],
        yes: ['fa-check', '#059669'], no: ['fa-xmark', '#dc2626'],
        // the rest of Travels (owner, 2026-10-11: "unique icons according to titles")
        received: ['fa-folder-open', '#0284c7'], in_embassy: ['fa-landmark', '#7c3aed'], approved: ['fa-circle-check', '#059669'],
        delivered: ['fa-box-open', '#0891b2'], rejected: ['fa-circle-xmark', '#dc2626'], in_progress: ['fa-spinner', '#0284c7'],
        done: ['fa-flag-checkered', '#059669'], departed: ['fa-plane-departure', '#4f46e5'], boarding: ['fa-person-walking-luggage', '#0891b2'],
        overdue: ['fa-triangle-exclamation', '#dc2626'], inactive: ['fa-circle-pause', '#94a3b8'], advance: ['fa-forward', '#0891b2'],
        payable: ['fa-arrow-up-from-bracket', '#dc2626'], receivable: ['fa-arrow-down-to-bracket', '#059669'],
        cash: ['fa-money-bill-wave', '#059669'], bank_transfer: ['fa-building-columns', '#4f46e5'], cheque: ['fa-money-check', '#0284c7'],
        checque: ['fa-money-check', '#0284c7'], card: ['fa-credit-card', '#7c3aed'], mobile_banking: ['fa-mobile-screen', '#db2777'],
        online: ['fa-globe', '#0284c7'], bus: ['fa-bus', '#d97706'], train: ['fa-train', '#475569'], flight: ['fa-plane', '#4f46e5'],
        ticket: ['fa-ticket', '#059669'], visa: ['fa-passport', '#7c3aed'], contract_flight: ['fa-plane-up', '#ea580c'], contract_file: ['fa-folder', '#b45309'],
        ticket_sale: ['fa-ticket', '#059669'], visa_sale: ['fa-passport', '#7c3aed'], flight_sale: ['fa-plane-up', '#ea580c'], file_sale: ['fa-folder', '#b45309'],
        general: ['fa-id-card', '#64748b'], other: ['fa-ellipsis', '#64748b'], vendor: ['fa-store', '#d97706'], client: ['fa-user-tie', '#059669'],
        direct: ['fa-user', '#0284c7'], all: ['fa-layer-group', '#64748b'], email: ['fa-envelope', '#0284c7'], sms: ['fa-comment-sms', '#d97706'],
        whatsapp: ['fa-brands fa-whatsapp', '#16a34a'], seasonal: ['fa-sun', '#d97706'], auto: ['fa-wand-magic-sparkles', '#7c3aed'], manual: ['fa-hand-pointer', '#475569'],
        unallocated: ['fa-inbox', '#0891b2'], this_month: ['fa-calendar-day', '#0284c7'], this_year: ['fa-calendar', '#4f46e5'],
        all_time: ['fa-infinity', '#64748b'], custom: ['fa-sliders', '#475569'], party_invoice: ['fa-file-invoice', '#4f46e5'], party_payment: ['fa-money-bill-transfer', '#059669'],
        immigration_wise: ['fa-passport', '#7c3aed'], manpower_wise: ['fa-people-group', '#b45309'],
        bdt: ['fa-bangladeshi-taka-sign', '#059669'], usd: ['fa-dollar-sign', '#15803d'], eur: ['fa-euro-sign', '#1d4ed8'],
        gbp: ['fa-sterling-sign', '#7c3aed'], inr: ['fa-indian-rupee-sign', '#ea580c'], sar: ['fa-coins', '#16a34a'], aed: ['fa-coins', '#b45309'],
        qar: ['fa-coins', '#9f1239'], myr: ['fa-coins', '#0284c7'], air: ['fa-plane', '#4f46e5'],
    };
    // Option TITLES that say what they are, whatever their value (Visa stages, project
    // names, purposes…) — first match wins, before the field's own icon.
    const TEXT_ICONS = [
        [/^new\b/i, ['fa-wand-magic-sparkles', '#4f46e5']], [/document collection|collect/i, ['fa-folder-plus', '#0284c7']],
        [/verif/i, ['fa-magnifying-glass', '#0891b2']], [/embassy|submission/i, ['fa-landmark', '#7c3aed']],
        [/biometric|interview|finger/i, ['fa-fingerprint', '#db2777']], [/approv/i, ['fa-circle-check', '#059669']],
        [/ready for delivery|deliver/i, ['fa-box-open', '#0891b2']], [/reject|denied|refus/i, ['fa-circle-xmark', '#dc2626']],
        [/document|paper/i, ['fa-file-lines', '#0284c7']], [/air ?ticket|ticketing/i, ['fa-ticket', '#059669']],
        [/visa/i, ['fa-passport', '#7c3aed']], [/contract flight|contact flight/i, ['fa-plane-up', '#ea580c']],
        [/contract file|contact file/i, ['fa-folder', '#b45309']], [/passport/i, ['fa-passport', '#7c3aed']],
        [/hotel/i, ['fa-hotel', '#0891b2']], [/umrah|hajj/i, ['fa-kaaba', '#475569']], [/tour|holiday|package/i, ['fa-umbrella-beach', '#d97706']],
        [/manpower|bmet|work permit/i, ['fa-people-group', '#b45309']], [/training|trainer/i, ['fa-chalkboard-user', '#4f46e5']],
        [/consult/i, ['fa-handshake', '#059669']], [/insurance/i, ['fa-shield-heart', '#0891b2']], [/medical|health/i, ['fa-briefcase-medical', '#dc2626']],
        [/student|study|universit/i, ['fa-graduation-cap', '#4f46e5']], [/tourist|visit/i, ['fa-camera', '#0891b2']],
        [/business/i, ['fa-briefcase', '#b45309']], [/work|job|employ/i, ['fa-briefcase', '#b45309']], [/transit/i, ['fa-right-left', '#0891b2']],
        [/agent/i, ['fa-user-tie', '#059669']], [/vendor|supplier|seller/i, ['fa-store', '#d97706']], [/customer|client/i, ['fa-user', '#059669']],
        [/bank/i, ['fa-building-columns', '#4f46e5']], [/cash/i, ['fa-money-bill-wave', '#059669']], [/others?\b/i, ['fa-ellipsis', '#64748b']],
    ];
    const textIcon = t => (TEXT_ICONS.find(([re]) => re.test(String(t || ''))) || [])[1];
    const KEYWORDS = [
        [/holder|passport/, ['fa-passport', '#7c3aed']], [/categor/, ['fa-tag', '#7c3aed']], [/stage/, ['fa-diagram-next', '#4f46e5']],
        [/exception|flag/, ['fa-flag', '#dc2626']], [/journey|event/, ['fa-route', '#0891b2']], [/trip/, ['fa-route', '#0891b2']],
        [/pay|method/, ['fa-money-bill-wave', '#059669']], [/recipient|notify/, ['fa-bell', '#db2777']], [/currency/, ['fa-coins', '#ca8a04']],
        [/ticket/, ['fa-ticket', '#059669']], [/status/, ['fa-circle-check', '#059669']],
        [/airport/, ['fa-plane', '#4f46e5']], [/flight/, ['fa-plane', '#ea580c']],
        [/bmet|boarding|handling|service/, ['fa-briefcase', '#b45309']], [/bearer|fault|side/, ['fa-scale-balanced', '#475569']],
        [/company/, ['fa-building', '#4f46e5']], [/officer|user|employee|staff/, ['fa-user', '#0284c7']], [/type|kind/, ['fa-user', '#64748b']],
    ];
    const CLASS_ICONS = { economy: ['fa-chair', '#0284c7'], premium: ['fa-couch', '#7c3aed'], business: ['fa-briefcase', '#b45309'], first: ['fa-crown', '#ca8a04'] };

    const flag = cc => cc ? `<img class="ds-rflag" src="${FLAGS}/${cc}.png" alt="" onerror="this.remove()">` : '';
    // Country pickers show the country's flag (Visa's destination, Passport's nationality …),
    // the flags Ticketing's routes use. Name → ISO code from Country management.
    @php
        try {
            $ddCountries = \Illuminate\Support\Facades\Cache::remember('dd_country_cc', 3600, fn () =>
                \Illuminate\Support\Facades\DB::table('countries')->whereNotNull('code')->pluck('code', 'name')
                    ->mapWithKeys(fn ($c, $n) => [mb_strtoupper(trim($n)) => strtolower(trim($c))])->all());
        } catch (\Throwable $e) {
            $ddCountries = [];
        }
    @endphp
    const COUNTRY_CC = @json((object) $ddCountries);
    const row = (mark, text, tail) => $(`<span class="ds-rt">${mark}<span class="ds-rt-t">${esc(text)}</span>${tail || ''}</span>`);
    const icon = (ic, text) => row(`<i class="${/fa-brands/.test(ic[0]) ? '' : 'fas '}${ic[0]} ds-oic" style="color:${ic[1]};background:${ic[1]}1a"></i>`, text);

    function templateFor(sel) {
        if (sel.matches('.pb-nt-route, .ticket-route-select, #create_ticket_id, #edit_ticket_id')) {
            return o => (!o.id || !o.element) ? o.text : row(flag(o.element.dataset.ff), o.text, flag(o.element.dataset.tf));
        }
        if (brand && sel.matches('.pb-route-airline, .pricing-airline, .pb-nt-vc, #tkrAirline, [name$="airline_id"]')) {
            return o => {
                if (!o.id || !o.element) return o.text;
                const b = brand(o.text);
                const mark = b.slug ? `<img class="ds-blogo" src="${LOGOS}/${b.slug}.png" alt="" onerror="this.remove()">`
                    : `<span class="ds-blogo ds-alogo-x" style="background:${b.color}">${esc((code(o.text) || o.text.slice(0, 2)).toUpperCase())}</span>`;
                return row(mark, o.text);
            };
        }
        if (sel.matches('.pb-nt-bank, .pb-sc-bank, [name$="bank_id"]')) {
            return o => {
                if (!o.id || !o.element) return o.text;
                const hit = BANK_BRANDS.find(([, re]) => re.test(o.text.replace(/—.*$/, '')));
                return row(hit ? `<img class="ds-blogo" src="${BANKS}/${hit[0]}.png" alt="" onerror="this.remove()">` : '<span class="ds-blogo ds-blogo-x"></span>', o.text);
            };
        }
        if (sel.matches('[name$="ticket_class"], [id$="_ticket_class"], .pb-class')) {
            return o => { const ic = o.id && CLASS_ICONS[String(o.id).toLowerCase()]; return ic ? icon(ic, o.text) : o.text; };
        }
        // a portal is usually an airline's (Air Arabia, Biman, IndiGo): its logo; BSP / others the globe
        if (brand && /portal/i.test([sel.name, sel.id, sel.className].join(' '))) {
            return o => {
                if (!o.id || !o.element) return o.text;
                const b = brand(o.text);
                return b.slug ? row(`<img class="ds-blogo" src="${LOGOS}/${b.slug}.png" alt="" onerror="this.remove()">`, o.text) : icon(['fa-globe', '#0284c7'], o.text);
            };
        }
        // "1 ticket / 2 tickets / 3 tickets": the number itself
        if (/tickets_required/i.test([sel.name, sel.id].join(' '))) {
            return o => (!o.id || !o.element) ? o.text : icon(['fa-' + Math.min(9, Math.max(1, parseInt(o.id, 10) || 1)), '#4f46e5'], o.text);
        }
        if (/countr|nationalit/i.test([sel.name, sel.id].join(' '))) {
            return o => {
                if (!o.id || !o.element) return o.text;
                const cc = COUNTRY_CC[String(o.text).trim().toUpperCase()];
                return cc ? row(flag(cc), o.text) : icon(['fa-earth-asia', '#0284c7'], o.text);
            };
        }
        const hit = ICONS.find(([q]) => sel.matches(q));
        if (hit) return o => (!o.id || !o.element) ? o.text : icon(hit[1], o.text);
        // No logo: an icon per option value where the list is fixed (Ticketing's
        // DS_ICONS), otherwise one icon for the field, chosen by its name.
        const key = [sel.name, sel.id, sel.className].join(' ').toLowerCase();
        const fallback = (KEYWORDS.find(([re]) => re.test(key)) || [null, ['fa-circle-dot', '#64748b']])[1];
        return o => {
            if (!o.id || !o.element) return o.text;
            return icon(VALUE_ICONS[String(o.id).toLowerCase()] || textIcon(o.text) || fallback, o.text);
        };
    }

    // On Ticketing pages the tab bar (layout/ticketing-tabs) already wraps Select2 and
    // marks every picker itself. Wrapping it again would make the two call each other
    // forever, so there only the plain dropdowns are converted (below) — through its wrapper.
    if (!$.fn.__tkWrapped) {
    const orig = $.fn.select2;
    const wrapped = function (opts) {
        if (opts !== undefined && (typeof opts !== 'object' || opts === null)) return orig.apply(this, arguments);
        this.each(function () {
            let tpl = null;
            // the shared header's Quick Task keeps its own look (it is every company's)
            if (!/quickTask/i.test(this.id || '')) { try { tpl = templateFor(this); } catch (e) { tpl = null; } }
            const o = Object.assign({}, opts || {});
            if (tpl) {
                if (!o.templateResult) o.templateResult = tpl;
                if (!o.templateSelection) o.templateSelection = tpl;
            }
            orig.call($(this), o);
        });
        return this;
    };
    Object.keys(orig).forEach(k => { wrapped[k] = orig[k]; });
    wrapped.defaults = orig.defaults;
    wrapped.amd = orig.amd;
    wrapped.__cfTd = true;
    $.fn.select2 = wrapped;
    }
    if ($.fn.__cfTdDone) return;     // a second copy of this partial on the same page
    $.fn.__cfTdDone = true;

    // Every plain dropdown on the flight screens becomes the same picker (owner,
    // 2026-10-08: "all dropdowns ... exact same as ticketing"). Only visible ones:
    // the builder's <template> and its pristine leg block stay native, because the
    // builder clones them and initialises the clones itself.
    const SKIP = '[data-no-s2], .flatpickr-calendar select, .cfr-sw-src, [multiple], [size], .select2-hidden-accessible, .fdl-se select, .dataTables_length select, #quickTaskModal select, [id^="quickTask"]';
    function parentOf(sel) {
        const box = sel.closest('[role="dialog"], .fcm-box, .modal, [id$="Modal"], [id$="Overlay"]');
        return box ? $(box) : undefined;
    }
    function enhance() {
        document.querySelectorAll('select').forEach(sel => {
            if (sel.matches(SKIP) || sel.hidden || !sel.offsetParent) return;
            try {
                $(sel).select2({ width: '100%', dropdownParent: parentOf(sel), minimumResultsForSearch: sel.options.length > 8 ? 0 : Infinity });
                sel.dataset.cfAuto = '1';
            } catch (e) {}
        });
        // A select the page hides keeps its picker hidden too.
        document.querySelectorAll('select.select2-hidden-accessible').forEach(sel => {
            const c = sel.nextElementSibling;
            if (c && c.classList.contains('select2')) {
                const want = (sel.hidden || sel.style.display === 'none') ? 'none' : '';
                if (c.style.display !== want) c.style.display = want;
            }
        });
    }
    // select2 announces a pick with a jQuery trigger, which never reaches
    // addEventListener('change') handlers. On the pickers made here, turn it into
    // one real change event, so jQuery and native listeners each run once.
    $(document).on('change', 'select[data-cf-auto]', function (e) {
        if (!e.isTrigger || e.namespace) return;
        e.stopImmediatePropagation();
        this.dispatchEvent(new Event('change', { bubbles: true }));
    });
    // .val(x) without a trigger would leave the picker showing the old choice.
    const origVal = $.fn.val;
    $.fn.val = function () {
        const out = origVal.apply(this, arguments);
        if (arguments.length) this.each(function () {
            if (this.tagName === 'SELECT' && this.classList.contains('select2-hidden-accessible')) $(this).trigger('change.select2');
        });
        return out;
    };
    let timer = null;
    // At most once per 120ms — never "wait until the page is quiet": the live bulletin
    // ticker changes the page many times a second, and a reset-on-every-change wait
    // never ran, so a form opened later kept its plain dropdowns.
    const later = () => { if (!timer) timer = setTimeout(() => { timer = null; enhance(); }, 120); };
    $(later);
    // Only changes that can show or add a dropdown: new nodes, or a class/style/hidden
    // change on something that holds a <select> (a modal opening). The ticker's
    // constant style changes hold none, so they no longer make it rescan.
    new MutationObserver(records => {
        if (records.some(r => r.type === 'childList' || r.target.tagName === 'SELECT' || (r.target.querySelector && r.target.querySelector('select')))) later();
    }).observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style', 'hidden'] });

}
})();
</script>
