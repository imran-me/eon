{{--
    Party Statement toolbar — one row of tabs across the party desk:
    Dashboard, then one ledger per party type.

    Drawn as layout/bank-tabs.blade.php, deliberately identical (owner,
    2026-09-15: "pixel perfect same"): two sections of the app that navigate
    the same way should look the same. Prefixed .pt- rather than reusing .bk-
    so a future change to one desk's toolbar cannot silently move the other's.

    Self-contained: resolves its own $role and prefixes locals with partyTab*,
    because the including views define their own $role, $type and $meta.

    Every tab carries ?company= when the page has one. The sidebar's company
    rail keys off that param and app.blade.php only stamps it onto SIDEBAR
    links — a tab here without it would drop the rail back to the first
    company (the trap the Contract Flight band hit; see sidebar.blade.php).
--}}
@php
    $partyTabUser = auth()->user();
    $partyTabRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($partyTabUser)->getRoleNames()->first() ?? '');
    $partyTabCompany = request()->query('company');
    $partyTabProject = request()->query('project');

    $partyTabItems = [
        [
            'label'  => 'Dashboard',
            'icon'   => 'fa-chart-pie',
            'route'  => 'role.party-statement.index',
            'active' => ['role.party-statement.index'],
        ],
        [
            'label'  => 'Agent Ledger',
            'icon'   => 'fa-user-tie',
            'route'  => 'role.party-statement.agents',
            'active' => ['role.party-statement.agents'],
        ],
        [
            'label'  => 'Vendor Ledger',
            'icon'   => 'fa-truck',
            'route'  => 'role.party-statement.vendors',
            'active' => ['role.party-statement.vendors'],
        ],
        [
            'label'  => 'Customer Ledger',
            'icon'   => 'fa-user',
            'route'  => 'role.party-statement.clients',
            'active' => ['role.party-statement.clients'],
        ],
        [
            'label'  => 'Company Ledger',
            'icon'   => 'fa-building',
            'route'  => 'role.party-statement.company',
            'active' => ['role.party-statement.company'],
        ],
        [
            'label'  => 'Activity Report',
            'icon'   => 'fa-chart-line',
            'route'  => 'role.party-statement.activity',
            'active' => ['role.party-statement.activity', 'role.party-statement.activity-print'],
        ],
        [
            'label'  => 'Import Transactions',
            'icon'   => 'fa-file-excel',
            'route'  => 'role.party-transaction-import.index',
            'active' => ['role.party-transaction-import.*', 'role.party-invoice-import.*'],
        ],
    ];

    $partyTabLinks = [];

    foreach ($partyTabItems as $partyTabItem) {
        // A missing route must never take the whole page down with it.
        try {
            $partyTabParams = ['role' => $partyTabRole];
            if ($partyTabCompany) {
                $partyTabParams['company'] = $partyTabCompany;
            }
            if ($partyTabProject) {
                $partyTabParams['project'] = $partyTabProject;
            }
            $partyTabItem['url'] = route($partyTabItem['route'], $partyTabParams);
        } catch (\Throwable $e) {
            continue;
        }

        $partyTabItem['is_active'] = request()->routeIs(...$partyTabItem['active']);
        $partyTabLinks[] = $partyTabItem;
    }
@endphp

@if(count($partyTabLinks) > 1)
<div class="pt-toolbar">
    <nav class="pt-navband" aria-label="Party Statement sections">
        @foreach($partyTabLinks as $partyTabLink)
            <a href="{{ $partyTabLink['url'] }}"
               class="pt-navtab {{ $partyTabLink['is_active'] ? 'is-active' : '' }}"
               title="{{ $partyTabLink['label'] }}"
               @if($partyTabLink['is_active']) aria-current="page" @endif>
                <i class="fas {{ $partyTabLink['icon'] }}"></i>
                <span>{{ $partyTabLink['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>

<style>
    /* Same band as layout/bank-tabs.blade.php, value for value. Keep in step
       with it: a change to one is a change to both, or the desks drift. */
    .pt-toolbar{margin:0 0 16px;}
    .p-4 > .pt-toolbar{margin-top:-16px;}
    @media(min-width:768px){ .p-4 > .pt-toolbar{margin-top:-24px;} }

    .pt-navband{display:flex;flex-wrap:wrap;align-items:stretch;gap:0;background:#fff;
        border-bottom:1px solid #e2e8f0;border-radius:10px 10px 0 0;margin-bottom:12px;}
    .pt-navtab{flex:1 0 auto;min-width:0;display:inline-flex;align-items:center;justify-content:center;gap:7px;
        text-align:center;padding:11px 14px 12px;margin-bottom:-1px;border-bottom:3px solid transparent;
        font-size:12px;font-weight:700;color:#64748b;white-space:nowrap;text-decoration:none;
        transition:color .15s,border-color .15s,background .15s;}
    .pt-navtab + .pt-navtab{border-left:1px solid #e2e8f0;}
    .pt-navtab i{font-size:11px;opacity:.85;}
    .pt-navtab:hover{color:#0f172a;background:rgba(37,99,235,.09);text-decoration:none;}
    .pt-navtab.is-active{color:#2563eb;border-bottom-color:#2563eb;}
    .pt-navtab.is-active i{opacity:1;}

    html.dark .pt-navband{background:#1e293b;border-bottom-color:#334155;}
    html.dark .pt-navtab{color:#94a3b8;}
    html.dark .pt-navtab + .pt-navtab{border-left-color:#334155;}
    html.dark .pt-navtab:hover{color:#e2e8f0;background:rgba(59,130,246,.14);}
    html.dark .pt-navtab.is-active{color:#60a5fa;border-bottom-color:#60a5fa;}

    @media(max-width:640px){
        .pt-navtab{padding:10px 10px 11px;font-size:11.5px;}
    }
    @media print { .pt-toolbar{display:none !important;} }
</style>
@endif
