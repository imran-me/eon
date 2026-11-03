{{--
    Contract File → Setup switcher (owner, 2026-10-25) — Contract Flight's Flight Category pill switcher
    (layout/flight-category-tabs.blade.php), same look, for Requirements / Price Presets / Visa Types /
    Job Categories, nested under the "Setup" tab of layout/contract-file-tabs.blade.php. A missing route
    (server not refreshed yet) drops its pill instead of taking the page down. Locals are cfsTab*-prefixed
    because the including views define their own $role.
--}}
@php
    $cfsTabUser = auth()->user();
    $cfsTabRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($cfsTabUser)->getRoleNames()->first() ?? '');
    $cfsTabKind = request()->route('kind');

    $cfsTabItems = [
        ['label' => 'Requirements', 'icon' => 'fa-list-check', 'route' => 'role.contract-file-setup.index', 'params' => ['kind' => 'requirements'], 'on' => $cfsTabKind === 'requirements'],
        ['label' => 'Price Presets', 'icon' => 'fa-coins', 'route' => 'role.contract-file-setup.index', 'params' => ['kind' => 'price-presets'], 'on' => $cfsTabKind === 'price-presets'],
        ['label' => 'Visa Types', 'icon' => 'fa-passport', 'route' => 'role.contract-file-setup.index', 'params' => ['kind' => 'visa-types'], 'on' => $cfsTabKind === 'visa-types'],
        ['label' => 'Job Categories', 'icon' => 'fa-folder', 'route' => 'role.contract-file-categories.index', 'params' => [], 'on' => request()->routeIs('role.contract-file-categories.*')],
    ];

    $cfsTabLinks = [];
    foreach ($cfsTabItems as $cfsTabItem) {
        if (! $cfsTabUser?->canAny(['view contract file category', 'view all contract file category'])) {
            continue;
        }
        try {
            if (! \Illuminate\Support\Facades\Route::has($cfsTabItem['route'])) {
                continue;
            }
            $cfsTabItem['url'] = route($cfsTabItem['route'], ['role' => $cfsTabRole] + $cfsTabItem['params']);
        } catch (\Throwable $e) {
            continue;
        }
        $cfsTabLinks[] = $cfsTabItem;
    }
@endphp

@if(count($cfsTabLinks) > 1)
<div class="fct-switcher-wrap">
    <div class="fct-switcher" role="tablist" aria-label="Contract File setup">
        @foreach($cfsTabLinks as $cfsTabLink)
            <a href="{{ $cfsTabLink['url'] }}"
               class="fct-switch-item {{ $cfsTabLink['on'] ? 'is-active' : '' }}"
               role="tab"
               title="{{ $cfsTabLink['label'] }}"
               @if($cfsTabLink['on']) aria-current="page" aria-selected="true" @else aria-selected="false" @endif>
                <i class="fas {{ $cfsTabLink['icon'] }}"></i>
                <span>{{ $cfsTabLink['label'] }}</span>
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
