{{--
    Ticketing toolbar — one row of tabs carrying every link that used to sit
    in the sidebar's "Ticketing" dropdown (owner, 2026-10-04: "remove the
    menus from side bar, and add in the nav bar, like we always do, same
    style"). The sidebar keeps a single "Ticketing" link that lands on the
    first tab the user may open; everything else is reached from here.

    Same permission gates as the old dropdown, so a user never sees a tab for
    a page it would have refused them. Same look as contract-flight-tabs /
    expense-tabs (the shared .ex-* band).

    Ticket Orders and Ticket Services are the same create route told apart by
    ?ops=1 — exactly how the sidebar highlighted them.

    Self-contained on purpose: it resolves its own $role and prefixes locals
    with tkTab*, because the including views define their own $role.
--}}
@php
    $tkTabUser = auth()->user();
    $tkTabRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($tkTabUser)->getRoleNames()->first() ?? '');
    $tkTabOps = request()->boolean('ops');
    $tkTabImport = request()->boolean('import') && request()->routeIs('role.ticket-direct-sale.create');

    $tkTabItems = [
        [
            'label'       => 'Ticket Dashboard',
            'icon'        => 'fa-chart-pie',
            'route'       => 'role.ticketing.dashboard',
            'permissions' => ['view ticket purchase', 'view all ticket purchase', 'view ticket direct sale', 'view all ticket direct sale'],
            'is_active'   => request()->routeIs('role.ticketing.dashboard'),
        ],
        [
            'label'       => 'Ticket Orders',
            'icon'        => 'fa-layer-group',
            'route'       => 'role.ticket-purchase.index',
            'permissions' => ['view ticket purchase', 'view all ticket purchase'],
            'is_active'   => request()->routeIs('role.ticket-purchase.*', 'role.ticket-direct-sale.*', 'role.ticket-bookings.*') && ! $tkTabOps && ! $tkTabImport,
        ],
        // Ticket Entry with the import open (PDF + CSV / Excel — owner, 2026-10-19: "rename to
        // ticket import"); only while its reader route is registered
        ...(\Illuminate\Support\Facades\Route::has('role.ticket-import.read') ? [[
            'label'       => 'Ticket Import',
            'icon'        => 'fa-file-import',
            'route'       => 'role.ticket-direct-sale.create',
            'params'      => ['import' => 1],
            'permissions' => ['create ticket purchase', 'create ticket direct sale'],
            'is_active'   => $tkTabImport,
        ]] : []),
        [
            'label'       => 'Ticket Services',
            'icon'        => 'fa-exchange-alt',
            'route'       => 'role.ticket-direct-sale.create',
            'params'      => ['ops' => 1],
            'permissions' => ['create ticket purchase', 'create ticket direct sale'],
            'is_active'   => request()->routeIs('role.ticket-direct-sale.create') && $tkTabOps,
        ],
        [
            'label'       => 'Manage Sales',
            'icon'        => 'fa-list',
            'route'       => 'role.ticket-sales.index',
            'permissions' => ['view ticket sale', 'view all ticket sale'],
            'is_active'   => request()->routeIs('role.ticket-sales.*'),
        ],
        [
            'label'       => 'Ticket Accounts',
            'icon'        => 'fa-file-invoice-dollar',
            'route'       => 'role.ticketing.accounts',
            'permissions' => ['view ticket purchase', 'view all ticket purchase', 'view ticket direct sale', 'view all ticket direct sale'],
            'is_active'   => request()->routeIs('role.ticketing.accounts', 'role.ticketing.accounts.ledger'),
        ],
    ];

    // Ticket Setup (owner, 2026-10-04: "I work with these so less, but they
    // are important … manage them in one place, in one nav item"). The five
    // master-data pages share ONE band tab; on those pages a pill switcher
    // (same shape as flight-category-tabs) moves between them.
    $tkSetupItems = [
        [
            'label'       => 'Ticket Manage',
            'icon'        => 'fa-ticket-alt',
            'route'       => 'role.tickets.index',
            'permissions' => ['view ticket', 'view all ticket'],
            'is_active'   => request()->routeIs('role.tickets.*'),
        ],
        [
            'label'       => 'Airlines',
            'icon'        => 'fa-plane',
            'route'       => 'role.airlines.index',
            'permissions' => ['view airline', 'view all airline'],
            'is_active'   => request()->routeIs('role.airlines.*'),
        ],
        [
            'label'       => 'Airport Management',
            'icon'        => 'fa-plane-departure',
            'route'       => 'role.airport.index',
            'permissions' => ['view geography', 'view all geography'],
            'is_active'   => request()->routeIs('role.airport.*'),
        ],
        [
            'label'       => 'Country',
            'icon'        => 'fa-flag',
            'route'       => 'role.countries.index',
            'permissions' => ['view geography', 'view all geography'],
            'is_active'   => request()->routeIs('role.countries.*'),
        ],
        [
            'label'       => 'States',
            'icon'        => 'fa-city',
            'route'       => 'role.states.index',
            'permissions' => ['view geography', 'view all geography'],
            'is_active'   => request()->routeIs('role.states.*'),
        ],
    ];

    $tkTabResolve = function (array $items) use ($tkTabUser, $tkTabRole) {
        $out = [];
        foreach ($items as $item) {
            if (! $tkTabUser?->canAny($item['permissions'])) {
                continue;
            }
            // A missing route must never take the whole page down with it.
            try {
                $item['url'] = route($item['route'], ['role' => $tkTabRole] + ($item['params'] ?? []));
            } catch (\Throwable $e) {
                continue;
            }
            $out[] = $item;
        }
        return $out;
    };

    $tkTabLinks = $tkTabResolve($tkTabItems);
    $tkSetupLinks = $tkTabResolve($tkSetupItems);
    $tkSetupActive = collect($tkSetupLinks)->contains('is_active', true);
    if ($tkSetupLinks) {
        // The band tab lands on the first setup page the user may open.
        $tkTabLinks[] = [
            'label'     => 'Ticket Setup',
            'icon'      => 'fa-sliders',
            'url'       => $tkSetupLinks[0]['url'],
            'is_active' => $tkSetupActive,
        ];
    }
@endphp

@if(count($tkTabLinks) > 1)
<div class="ex-toolbar">
    <nav class="ex-navband tk-navband" aria-label="Ticketing sections">
        @foreach($tkTabLinks as $tkTabLink)
            <a href="{{ $tkTabLink['url'] }}"
               class="ex-navtab {{ $tkTabLink['is_active'] ? 'is-active' : '' }}"
               title="{{ $tkTabLink['label'] }}"
               @if($tkTabLink['is_active']) aria-current="page" @endif>
                <i class="fas {{ $tkTabLink['icon'] }}"></i>
                <span>{{ $tkTabLink['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
@if($tkSetupActive && count($tkSetupLinks) > 1)
<div class="tks-wrap">
    <div class="tks-switcher" role="tablist" aria-label="Ticket setup">
        @foreach($tkSetupLinks as $tkSetupLink)
            <a href="{{ $tkSetupLink['url'] }}" class="tks-item {{ $tkSetupLink['is_active'] ? 'is-active' : '' }}" role="tab"
               @if($tkSetupLink['is_active']) aria-current="page" aria-selected="true" @else aria-selected="false" @endif>
                <i class="fas {{ $tkSetupLink['icon'] }}"></i><span>{{ $tkSetupLink['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
<style>
    /* Ticket Setup switcher — sits under its own tab on the right (owner,
       2026-10-04): white bar, hairline border, the active page an indigo
       gradient chip. Minimal: no icons fighting the label, one accent. */
    .tks-wrap{display:flex;justify-content:flex-end;margin:-4px 0 14px;}
    .tks-switcher{display:inline-flex;flex-wrap:wrap;gap:2px;padding:3px;background:#fff;border:1px solid #e6e9f2;border-radius:11px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px rgba(79,70,229,.06);}
    .tks-item{display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 13px;border-radius:8px;font-size:11.5px;font-weight:600;letter-spacing:.01em;color:#64748b;white-space:nowrap;text-decoration:none;transition:color .15s,background .15s,box-shadow .15s;}
    .tks-item i{font-size:10px;color:#a5b4fc;transition:color .15s;}
    .tks-item:hover{color:#3730a3;background:#f5f6ff;text-decoration:none;}
    .tks-item:hover i{color:#6366f1;}
    .tks-item.is-active{color:#fff;background:linear-gradient(135deg,#4f46e5,#7c3aed);box-shadow:0 2px 8px rgba(79,70,229,.32),inset 0 1px 0 rgba(255,255,255,.18);}
    .tks-item.is-active i{color:#e0e7ff;}
    html.dark .tks-switcher{background:#1e293b;border-color:#334155;box-shadow:none;}
    html.dark .tks-item{color:#94a3b8;}
    html.dark .tks-item:hover{color:#e2e8f0;background:#0f172a;}
    @media(max-width:640px){.tks-wrap{justify-content:flex-start;}}
</style>
@endif
@include('layout.travels-loader')
{{-- Every Ticketing dropdown gets a mark (owner, 2026-10-07: "all the drop downs of
     ticketing, add logos, or if not available, use icon"): airline → its logo, airport
     or route → country flag(s), bank → bank logo, anything else → a coloured icon for
     the field. Done by wrapping $.fn.select2 as Select2 loads, so every page's own init
     (and every modal opened later) is covered without editing it. Only on Ticketing
     pages (this partial). A picker that passes its own templateResult keeps it. --}}
@php
    try {
        $tkAirports = \Illuminate\Support\Facades\Cache::remember('tk_airport_cc', 3600, fn () =>
            \App\Models\Airport::query()->leftJoin('countries', 'countries.id', '=', 'airports.country_id')
                ->whereNotNull('airports.code')->select('airports.code as ap', 'countries.code as cc')->pluck('cc', 'ap')
                ->map(fn ($c) => strtolower((string) $c))->filter()->all());
    } catch (\Throwable $e) { $tkAirports = []; }
    try {
        $tkAirSlugs = collect(glob(public_path('airline-logos/*.png')) ?: [])->map(fn ($f) => basename($f, '.png'))->values()->all();
    } catch (\Throwable $e) { $tkAirSlugs = []; }
@endphp
<script>
(function () {
    const AP = @json($tkAirports), AIR = @json($tkAirSlugs);
    const FLAGS = @json(asset('flags')), AIRL = @json(asset('airline-logos')), BANKL = @json(asset('bank-logos'));
    const BANKS = [['dutch-bangla', /dutch\s*-?\s*bangla|\bdbbl\b/i], ['islami', /islami/i], ['brac', /brac/i], ['city', /city\s*bank/i],
        ['dhaka', /dhaka\s*bank/i], ['bkash', /b\s*-?\s*kash/i], ['petty-cash', /petty\s*-?\s*cash/i], ['office-cash', /office\s*-?\s*cash/i], ['visa-dhaka', /credit\s*-?\s*card/i]];
    // Names the airlines table spells its own way
    const AIR_ALIAS = [['china-eastern', /chi(n|a)n?\s*eastern/i], ['gulf-air', /g(u|o)lf\s*air/i], ['himalaya', /himala?ya/i], ['us-bangla', /us\s*-?\s*bangla/i],
        ['saudia', /saudi/i], ['biman', /biman/i], ['malindo', /malindo|batik/i], ['fitsair', /fits\s*air/i], ['flydubai', /fly\s*dubai/i], ['kuwait', /kuwait/i]];
    const BY_FIELD = [
        [/passport_holder|passenger/, 'fa-passport', '#7c3aed'], [/vendor|seller/, 'fa-store', '#d97706'], [/portal/, 'fa-globe', '#0284c7'],
        [/client|agent|customer|buyer|sold/, 'fa-user-tie', '#059669'], [/ticket_id|route/, 'fa-route', '#4f46e5'], [/currency/, 'fa-coins', '#ca8a04'],
        [/method/, 'fa-money-bill-wave', '#059669'], [/bearer|fault|side/, 'fa-scale-balanced', '#475569'], [/recipient|notify|user|staff|employee/, 'fa-user', '#db2777'],
        [/company/, 'fa-building', '#475569'], [/type|kind/, 'fa-tag', '#7c3aed'], [/class/, 'fa-chair', '#0284c7'],
    ];
    const BY_VALUE = {
        purchased: ['fa-ticket', '#059669'], confirm: ['fa-circle-check', '#059669'], confirmed: ['fa-circle-check', '#059669'], booked: ['fa-clock', '#0284c7'],
        pending: ['fa-hourglass-half', '#d97706'], draft: ['fa-pen', '#64748b'], cancelled: ['fa-ban', '#dc2626'], expired: ['fa-calendar-xmark', '#94a3b8'],
        due: ['fa-circle-exclamation', '#dc2626'], partial: ['fa-circle-half-stroke', '#d97706'], paid: ['fa-circle-check', '#059669'],
        'one-way': ['fa-arrow-right-long', '#4f46e5'], 'two-way': ['fa-right-left', '#7c3aed'], 'multi-city': ['fa-route', '#0891b2'],
        economy: ['fa-chair', '#0284c7'], premium: ['fa-couch', '#7c3aed'], business: ['fa-briefcase', '#b45309'], first: ['fa-crown', '#ca8a04'],
        cash: ['fa-money-bill-wave', '#059669'], bank: ['fa-building-columns', '#4f46e5'], cheque: ['fa-money-check', '#0891b2'],
        customer: ['fa-user', '#059669'], company: ['fa-building', '#475569'], agent: ['fa-user-tie', '#059669'],
        // one icon per option, not per field (owner, 2026-10-11: "unique icons according to titles")
        bdt: ['fa-bangladeshi-taka-sign', '#059669'], usd: ['fa-dollar-sign', '#15803d'], eur: ['fa-euro-sign', '#1d4ed8'],
        gbp: ['fa-sterling-sign', '#7c3aed'], inr: ['fa-indian-rupee-sign', '#ea580c'], sar: ['fa-coins', '#16a34a'], aed: ['fa-coins', '#b45309'],
        qar: ['fa-coins', '#9f1239'], myr: ['fa-coins', '#0284c7'],
        air: ['fa-plane', '#4f46e5'], bus: ['fa-bus', '#d97706'], train: ['fa-train', '#475569'], other: ['fa-ellipsis', '#64748b'],
        card: ['fa-credit-card', '#7c3aed'], advance: ['fa-forward', '#0891b2'], checque: ['fa-money-check', '#0891b2'],
        bank_transfer: ['fa-building-columns', '#4f46e5'], mobile_banking: ['fa-mobile-screen', '#db2777'], online: ['fa-globe', '#0284c7'],
        active: ['fa-circle-play', '#059669'], inactive: ['fa-circle-pause', '#94a3b8'], approved: ['fa-circle-check', '#059669'],
        rejected: ['fa-circle-xmark', '#dc2626'], overdue: ['fa-triangle-exclamation', '#dc2626'], open: ['fa-door-open', '#059669'], closed: ['fa-lock', '#64748b'],
        general: ['fa-id-card', '#64748b'], visa: ['fa-passport', '#7c3aed'], ticket: ['fa-ticket', '#059669'],
        contract_flight: ['fa-plane-up', '#ea580c'], contract_file: ['fa-folder', '#b45309'],
    };
    const esc = t => String(t).replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    const img = (src, cls) => `<img class="${cls}" src="${src}" alt="" onerror="this.remove()">`;
    const flag = cc => cc ? img(`${FLAGS}/${cc}.png`, 'tkm-flag') : '';
    const ic = ([i, c]) => `<i class="fas ${i} tkm-ic" style="color:${c};background:${c}1a"></i>`;
    function airlineSlug(text) {
        if (typeof window.airlineBrand === 'function') { const b = window.airlineBrand(text); if (b && b.slug) return b.slug; }
        const hit = AIR_ALIAS.find(([, re]) => re.test(text)); if (hit) return hit[0];
        const n = text.toLowerCase().replace(/\(.*?\)/g, '').replace(/airlines?|airways/g, '').replace(/[^a-z]+/g, '-').replace(/^-|-$/g, '');
        return AIR.find(sl => n === sl || n.startsWith(sl + '-') || n.replace(/-/g, '') === sl.replace(/-/g, '')) || null;
    }
    function looksAirline(field, $el) {
        if (/airline|carrier/.test(field)) return true;
        const lbl = $el.closest('div').find('label').first().text() || $el.parent().prev('label').text() || '';
        return /airline|carrier/i.test(lbl);
    }
    function mark(o, field, $el) {
        const text = String(o.text || ''), d = (o.element && o.element.dataset) || {};
        if (d.ff || d.tf) return flag(d.ff) + '|' + flag(d.tf);
        if (d.companyName !== undefined || /bank/.test(field)) {
            const b = BANKS.find(([, re]) => re.test(text.replace(/—.*$/, '')));
            return b ? img(`${BANKL}/${b[0]}.png`, 'tkm-logo') : ic(['fa-building-columns', '#4f46e5']);
        }
        if (looksAirline(field, $el)) { const sl = airlineSlug(text); return sl ? img(`${AIRL}/${sl}.png`, 'tkm-logo') : ic(['fa-plane', '#4f46e5']); }
        // a portal is usually an airline's (Air Arabia, Biman, IndiGo): its logo; BSP / others keep the globe
        if (/portal/.test(field)) { const sl = airlineSlug(text); return sl ? img(`${AIRL}/${sl}.png`, 'tkm-logo') : ic(['fa-globe', '#0284c7']); }
        const ap = text.match(/\(([A-Z]{3})\)/);                                   // an airport: "Istanbul Airport (IST)"
        if (ap && AP[ap[1]]) return flag(AP[ap[1]]);
        const rt = text.match(/\b([A-Z]{3})(?:\s*[-–→]\s*[A-Z]{3})+\b/);             // a route: "DAC-BAH-IST-ECN"
        if (rt) { const c = rt[0].split(/\s*[-–→]\s*/); return flag(AP[c[0]]) + '|' + flag(AP[c[c.length - 1]]); }
        const v = BY_VALUE[String(o.id).toLowerCase()]; if (v) return ic(v);
        const f = BY_FIELD.find(([re]) => re.test(field)); if (f) return ic([f[1], f[2]]);
        return ic(['fa-circle-dot', '#94a3b8']);
    }
    function templ($el) {
        const field = String($el.attr('name') || $el.attr('id') || '').toLowerCase();
        return o => {
            if (!o.id || o.children) return o.text;
            let m; try { m = mark(o, field, $el); } catch (e) { return o.text; }
            const [a, b] = m.includes('|') ? m.split('|') : [m, ''];
            return $(`<span class="tkm">${a}<span class="tkm-t">${esc(o.text)}</span>${b}</span>`);
        };
    }
    function wrap($) {
        if (!$ || !$.fn || $.fn.__tkWrapped) return;
        let inner = $.fn.select2;
        const wrapped = function (opts) {
            if (inner && (opts === undefined || (typeof opts === 'object' && opts !== null && !opts.templateResult && !opts.templateSelection))
                && !/quickTask/i.test(this.attr('id') || '')) {
                return this.each(function () {
                    const $e = $(this), t = templ($e);
                    inner.call($e, Object.assign({}, opts || {}, { templateResult: t, templateSelection: t }));
                });
            }
            return inner.apply(this, arguments);
        };
        Object.defineProperty($.fn, 'select2', {
            configurable: true,
            get() { if (!inner) return undefined; wrapped.defaults = inner.defaults; wrapped.amd = inner.amd; return wrapped; },
            set(v) { inner = v; },
        });
        $.fn.__tkWrapped = true;
    }
    // jQuery and Select2 load later, in each page's own scripts: catch them as they arrive.
    if (window.jQuery) wrap(window.jQuery);
    else {
        let jq;
        try {
            Object.defineProperty(window, 'jQuery', { configurable: true, enumerable: true, get() { return jq; },
                set(v) { jq = v; try { wrap(v); } catch (e) {} } });
        } catch (e) {}
    }
})();
</script>
<style>
    .tkm{display:inline-flex;align-items:center;gap:7px;max-width:100%;vertical-align:middle;}
    .tkm-t{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .tkm-flag{width:18px;height:12px;border-radius:2px;object-fit:cover;flex-shrink:0;box-shadow:0 0 0 1px rgba(15,23,42,.12);}
    .tkm-logo{width:20px;height:20px;border-radius:5px;object-fit:contain;background:#fff;flex-shrink:0;box-shadow:0 0 0 1px rgba(15,23,42,.1);}
    .tkm-ic{width:20px;height:20px;border-radius:5px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0;}
</style>
{{-- Ticketing dropdowns (owner, 2026-10-07: "not sharp anywhere, no separators"):
     a crisp edge, a hairline between options, a clear hover and a ticked selection.
     Emitted only on Ticketing pages (this partial), so other screens' Select2 is untouched. --}}
<style>
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
{{-- Same rules as contract-flight-tabs, repeated under the same class names
     so the band matches everywhere without depending on include order. --}}
<style>
    .ex-toolbar{margin:0 0 16px;}
    .p-4 > .ex-toolbar{margin-top:-16px;}
    @media(min-width:768px){ .p-4 > .ex-toolbar{margin-top:-24px;} }

    .ex-navband{display:flex;flex-wrap:wrap;align-items:stretch;gap:0;background:#fff;
        border-bottom:1px solid #e2e8f0;border-radius:10px 10px 0 0;margin-bottom:12px;}
    .ex-navtab{flex:1 0 auto;min-width:0;display:inline-flex;align-items:center;justify-content:center;gap:7px;
        text-align:center;padding:11px 14px 12px;margin-bottom:-1px;border-bottom:3px solid transparent;
        font-size:12px;font-weight:700;color:#64748b;white-space:nowrap;text-decoration:none;
        transition:color .15s,border-color .15s,background .15s;}
    .ex-navtab + .ex-navtab{border-left:1px solid #e2e8f0;}
    .ex-navtab i{font-size:11px;opacity:.85;}
    .ex-navtab:hover{color:#0f172a;background:rgba(37,99,235,.09);text-decoration:none;}
    .ex-navtab.is-active{color:#2563eb;border-bottom-color:#2563eb;}
    .ex-navtab.is-active i{opacity:1;}

    html.dark .ex-navband{background:#1e293b;border-bottom-color:#334155;}
    html.dark .ex-navtab{color:#94a3b8;}
    html.dark .ex-navtab + .ex-navtab{border-left-color:#334155;}
    html.dark .ex-navtab:hover{color:#e2e8f0;background:rgba(59,130,246,.14);}
    html.dark .ex-navtab.is-active{color:#60a5fa;border-bottom-color:#60a5fa;}

    /* Nine tabs: a little less side padding than the shared band so they
       stay on one row at 1365px. Scoped to .tk-navband — other bands keep theirs. */
    .tk-navband .ex-navtab{padding-left:9px;padding-right:9px;gap:6px;}

    @media(max-width:640px){
        .ex-navtab{padding:10px 10px 11px;font-size:11.5px;}
    }
</style>
@endif
