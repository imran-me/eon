@php
    $managePartyUser = auth()->user();
    $managePartyRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($managePartyUser)->getRoleNames()->first() ?? '');

    $managePartyItems = [
        [
            'label' => 'Vendors',
            'icon' => 'fa-truck-field',
            'route' => 'role.vendor.index',
            'permissions' => ['view vendor', 'view all vendor'],
            'active' => ['role.vendor.*'],
        ],
        [
            'label' => 'Agents',
            'icon' => 'fa-user-tie',
            'route' => 'role.agent.index',
            'permissions' => ['view agent', 'view all agent'],
            'active' => ['role.agent.*'],
        ],
        [
            'label' => 'Customers',
            'icon' => 'fa-user-group',
            'route' => 'role.customer.index',
            'permissions' => ['view customer', 'view all customer'],
            'active' => ['role.customer.*'],
        ],
    ];

    $managePartyLinks = collect($managePartyItems)
        ->filter(fn ($item) => $managePartyUser?->canAny($item['permissions']))
        ->map(function ($item) use ($managePartyRole) {
            $item['url'] = route($item['route'], ['role' => $managePartyRole]);
            $item['is_active'] = request()->routeIs(...$item['active']);

            return $item;
        });
@endphp

@if($managePartyLinks->isNotEmpty())
    <div class="mp-toolbar">
        <nav class="mp-navband" aria-label="Manage Parties sections">
            @foreach($managePartyLinks as $managePartyLink)
                <a href="{{ $managePartyLink['url'] }}"
                   class="mp-navtab {{ $managePartyLink['is_active'] ? 'is-active' : '' }}"
                   @if($managePartyLink['is_active']) aria-current="page" @endif>
                    <i class="fas {{ $managePartyLink['icon'] }}"></i>
                    <span>{{ $managePartyLink['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    <style>
        .mp-toolbar{margin:0 0 16px}
        .mp-navband{display:flex;align-items:stretch;background:#fff;border-bottom:1px solid #e2e8f0;border-radius:10px 10px 0 0}
        .mp-navtab{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 14px 12px;margin-bottom:-1px;border-bottom:3px solid transparent;color:#64748b;font-size:12px;font-weight:700;text-decoration:none;transition:color .15s,border-color .15s,background .15s}
        .mp-navtab + .mp-navtab{border-left:1px solid #e2e8f0}
        .mp-navtab:hover{color:#0f172a;background:rgba(37,99,235,.09);text-decoration:none}
        .mp-navtab.is-active{color:#2563eb;border-bottom-color:#2563eb}
        html.dark .mp-navband{background:#1e293b;border-bottom-color:#334155}
        html.dark .mp-navtab{color:#94a3b8}
        html.dark .mp-navtab + .mp-navtab{border-left-color:#334155}
        html.dark .mp-navtab:hover{color:#e2e8f0;background:rgba(59,130,246,.14)}
        html.dark .mp-navtab.is-active{color:#60a5fa;border-bottom-color:#60a5fa}
        @media(max-width:640px){.mp-navtab{padding:10px;font-size:11.5px}}
    </style>
@endif
