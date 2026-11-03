{{--
    Flight Category switcher — a compact segmented control for Flight
    Category / Category Type / Price Presets / Officers, nested under the
    "Flight Category" tab in layout/contract-flight-tabs.blade.php (that band
    is included just above this one on all four pages). Styled as a pill
    switcher rather than a second row of full-width tabs, so it reads as "the
    four faces of Flight Category" and not a second, competing top nav.

    Self-contained on purpose: it resolves its own $role and prefixes locals
    with fctTab*, because the including views define their own $role further
    down (some redeclare it via a raw PHP block, harmlessly — same value either way).
--}}
@php
    $fctTabUser = auth()->user();
    $fctTabRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($fctTabUser)->getRoleNames()->first() ?? '');

    $fctTabItems = [
        [
            'label'       => 'Flight Category',
            'icon'        => 'fa-folder',
            'route'       => 'role.flight-categories.index',
            'permissions' => ['view flight category', 'view all flight category'],
            'active'      => ['role.flight-categories.*'],
        ],
        [
            'label'       => 'Category Type',
            'icon'        => 'fa-tags',
            'route'       => 'role.flight-category-types.index',
            'permissions' => ['view flight category', 'view all flight category'],
            'active'      => ['role.flight-category-types.*'],
        ],
        [
            'label'       => 'Price Presets',
            'icon'        => 'fa-coins',
            'route'       => 'role.flight-price-presets.index',
            'permissions' => ['view flight category', 'view all flight category'],
            'active'      => ['role.flight-price-presets.*'],
        ],
        [
            'label'       => 'Officers',
            'icon'        => 'fa-user-shield',
            'route'       => 'role.flight-officers.index',
            'permissions' => ['view contract flight', 'view all contract flight'],
            'active'      => ['role.flight-officers.*'],
        ],
    ];

    $fctTabLinks = [];

    foreach ($fctTabItems as $fctTabItem) {
        if (! $fctTabUser?->canAny($fctTabItem['permissions'])) {
            continue;
        }

        // A missing route must never take the whole page down with it.
        try {
            $fctTabItem['url'] = route($fctTabItem['route'], ['role' => $fctTabRole]);
        } catch (\Throwable $e) {
            continue;
        }

        $fctTabItem['is_active'] = request()->routeIs(...$fctTabItem['active']);

        $fctTabLinks[] = $fctTabItem;
    }
@endphp

@if(count($fctTabLinks) > 1)
<div class="fct-switcher-wrap">
    <div class="fct-switcher" role="tablist" aria-label="Flight Category switcher">
        @foreach($fctTabLinks as $fctTabLink)
            <a href="{{ $fctTabLink['url'] }}"
               class="fct-switch-item {{ $fctTabLink['is_active'] ? 'is-active' : '' }}"
               role="tab"
               title="{{ $fctTabLink['label'] }}"
               @if($fctTabLink['is_active']) aria-current="page" aria-selected="true" @else aria-selected="false" @endif>
                <i class="fas {{ $fctTabLink['icon'] }}"></i>
                <span>{{ $fctTabLink['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
<style>
    .fct-switcher-wrap{display:flex;margin:-4px 0 16px;}
    .fct-switcher{display:inline-flex;flex-wrap:wrap;gap:2px;background:#f1f5f9;border:1px solid #e2e8f0;
        border-radius:999px;padding:3px;}
    .fct-switch-item{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;
        font-size:11.5px;font-weight:700;color:#64748b;white-space:nowrap;text-decoration:none;
        transition:color .15s,background .15s,box-shadow .15s;}
    .fct-switch-item i{font-size:10.5px;opacity:.8;}
    .fct-switch-item:hover{color:#0f172a;text-decoration:none;}
    .fct-switch-item.is-active{color:#1d4ed8;background:#fff;box-shadow:0 1px 3px rgba(30,50,100,.15);}
    .fct-switch-item.is-active i{opacity:1;}

    html.dark .fct-switcher{background:#0f172a;border-color:#334155;}
    html.dark .fct-switch-item{color:#94a3b8;}
    html.dark .fct-switch-item:hover{color:#e2e8f0;}
    html.dark .fct-switch-item.is-active{color:#60a5fa;background:#1e293b;}

    @media(max-width:640px){
        .fct-switch-item{padding:6px 11px;font-size:11px;}
    }
</style>
@endif

{{-- Travels page-load animation (owner, 2026-10-07: across all of Travels) --}}
@include('layout.travels-loader')
