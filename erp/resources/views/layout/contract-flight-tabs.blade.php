{{--
    Contract Flight toolbar — one row of tabs mirroring every link in the
    sidebar's "Contract Flight" submenu (layout/sidebar.blade.php), so the
    whole section is reachable from the band and the sidebar dropdown is no
    longer the only way to move between its pages. Same permission gates as
    the sidebar, so a user never sees a tab for a page it would have refused
    them.

    Built to the same shape as layout/expense-tabs.blade.php. No "Add Flight"
    tab here on purpose — that's a data-entry action, not a section to
    navigate to, so it lives as a page-level button on each screen instead
    (Flight Schedule and Flights & Passengers already had their own; Passenger
    Board links to Flights & Passengers' create modal via ?open=create).
    The report tab keeps the sidebar's target="_blank" — it generates a
    printable page, not a screen to navigate within. (Leg-wise Cost Report
    came out of the band and the sidebar on the owner's instruction,
    2026-09-14; its route still exists. Flight Schedule came out of the band
    on the owner's instruction, 2026-09-20 — same treatment: the route and
    page stay, the tab is gone.)

    The "Flight Category" tab covers four pages
    (Category/Type/Presets/Officers); this band sits above
    layout/flight-category-tabs.blade.php's pill switcher on all four so a
    user lands on one top-level tab plus a secondary control for which face
    of it they're on, not two competing rows of full-width tabs.

    Self-contained on purpose: it resolves its own $role and prefixes locals
    with cftTab*, because the including views define their own $role further
    down.
--}}
@php
    $cftTabUser = auth()->user();
    $cftTabRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($cftTabUser)->getRoleNames()->first() ?? '');

    $cftTabItems = [
        [
            'label'       => 'Flight Dashboard',
            'icon'        => 'fa-chart-pie',
            'route'       => 'role.contract-flights.dashboard',
            'permissions' => ['view contract flight', 'view all contract flight'],
            'active'      => ['role.contract-flights.dashboard'],
        ],
        [
            'label'       => 'Flights & Passengers',
            'icon'        => 'fa-suitcase',
            'route'       => 'role.contract-flights.index',
            'permissions' => ['view contract flight', 'view all contract flight'],
            // Not contract-flights.* — that would light this tab on the dashboard too.
            'active'      => ['role.contract-flights.index', 'role.contract-flight-passengers.*'],
        ],
        [
            'label'       => 'Passenger Board',
            'icon'        => 'fa-columns',
            'route'       => 'role.contract-flight-board.index',
            'permissions' => ['view contract flight', 'view all contract flight'],
            'active'      => ['role.contract-flight-board.*'],
        ],
        [
            'label'       => 'Flight Accounts',
            'icon'        => 'fa-file-invoice-dollar',
            'route'       => 'role.contract-flights.accounts',
            'permissions' => ['view contract flight', 'view all contract flight'],
            'active'      => ['role.contract-flights.accounts', 'role.contract-flights.accounts.ledger'],
        ],
        [
            'label'       => 'Messages',
            'icon'        => 'fa-comment-sms',
            'route'       => 'role.contract-flights.messages',
            'permissions' => ['view contract flight', 'view all contract flight'],
            'active'      => ['role.contract-flights.messages'],
        ],
        [
            'label'       => 'Flight Category',
            'icon'        => 'fa-tags',
            'route'       => 'role.flight-categories.index',
            'permissions' => ['view flight category', 'view all flight category'],
            'active'      => [
                'role.flight-categories.*', 'role.flight-category-types.*',
                'role.flight-price-presets.*', 'role.flight-officers.*',
            ],
        ],
    ];

    $cftTabLinks = [];

    foreach ($cftTabItems as $cftTabItem) {
        if (! $cftTabUser?->canAny($cftTabItem['permissions'])) {
            continue;
        }

        // A missing route must never take the whole page down with it.
        try {
            $cftTabItem['url'] = route($cftTabItem['route'], [
                'role' => $cftTabRole,
                'company' => \App\Services\CompanyFunds::COMPANY_ID,
            ] + ($cftTabItem['params'] ?? []));
        } catch (\Throwable $e) {
            continue;
        }

        $cftTabItem['is_active'] = $cftTabItem['active'] ? request()->routeIs(...$cftTabItem['active']) : false;

        $cftTabLinks[] = $cftTabItem;
    }
@endphp

@if(count($cftTabLinks) > 1)
<div class="ex-toolbar">
    <nav class="ex-navband" aria-label="Contract flight sections">
        @foreach($cftTabLinks as $cftTabLink)
            <a href="{{ $cftTabLink['url'] }}"
               class="ex-navtab {{ $cftTabLink['is_active'] ? 'is-active' : '' }}"
               title="{{ $cftTabLink['label'] }}"
               @if(($cftTabLink['target'] ?? null) === '_blank') target="_blank" rel="noopener" @endif
               @if($cftTabLink['is_active']) aria-current="page" @endif>
                <i class="fas {{ $cftTabLink['icon'] }}"></i>
                <span>{{ $cftTabLink['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
{{-- .ex-toolbar/.ex-navband/.ex-navtab are shared with expense-tabs and
     flight-category-tabs. Repeated here under the same class names (rather
     than assuming one of those partials already ran on this page) so the
     band matches everywhere it appears without depending on include order. --}}
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

    @media(max-width:640px){
        .ex-navtab{padding:10px 10px 11px;font-size:11.5px;}
    }
</style>
@endif

{{-- Travels page-load animation (owner, 2026-10-07: across all of Travels) --}}
@include('layout.travels-loader')
@include('contract-flights.partials.list-print')
