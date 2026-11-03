@php
    $vptUser = auth()->user();
    $vptRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($vptUser)->getRoleNames()->first() ?? '');
    $vptItems = [
        // Visa Dashboard first, as Flight Dashboard leads Contract Flight (owner, 2026-10-13).
        ['label' => 'Visa Dashboard', 'icon' => 'fa-chart-pie', 'route' => 'role.visa-dashboard', 'active' => ['role.visa-dashboard']],
        ['label' => 'Application Board', 'icon' => 'fa-th-large', 'route' => 'role.visa.index', 'active' => ['role.visa.*']],
        ['label' => 'Manage Sales', 'icon' => 'fa-receipt', 'route' => 'role.visa-sales.index', 'active' => ['role.visa-sales.*']],
        ['label' => 'Other Services', 'icon' => 'fa-puzzle-piece', 'route' => 'role.other-visa-services.index', 'active' => ['role.other-visa-services.*']],
        ['label' => 'Visa Accounts', 'icon' => 'fa-file-invoice-dollar', 'route' => 'role.visa-accounts.index', 'active' => ['role.visa-accounts.*']],
        // Visa Setup (owner, 2026-10-13: "merge these two, make one nav") —
        // Visa Category + Service Types share ONE band tab; on those pages a
        // switcher under the band moves between them (as Ticket Setup does).
        // Last, after Accounts (owner: "swap the positions").
        ['label' => 'Visa Setup', 'icon' => 'fa-sliders', 'route' => 'role.visa-category.index', 'active' => ['role.visa-category.*', 'role.other-service-types.*'], 'setup' => true],
    ];
    $vptSetupItems = [
        ['label' => 'Visa Category', 'icon' => 'fa-folder-open', 'route' => 'role.visa-category.index', 'active' => ['role.visa-category.*']],
        ['label' => 'Service Types', 'icon' => 'fa-tags', 'route' => 'role.other-service-types.index', 'active' => ['role.other-service-types.*']],
    ];
    $vptLinks = [];
    if ($vptUser?->canAny(['view visa', 'view all visa'])) {
        foreach ($vptItems as $vptItem) {
            try {
                $vptItem['url'] = route($vptItem['route'], ['role' => $vptRole]);
                $vptItem['is_active'] = request()->routeIs(...$vptItem['active']);
                $vptLinks[] = $vptItem;
            } catch (\Throwable $e) {
                // Keep the remaining navigation usable if one optional route is unavailable.
            }
        }
    }
    $vptSetupLinks = [];
    $vptSetupActive = request()->routeIs('role.visa-category.*', 'role.other-service-types.*');
    if ($vptSetupActive && $vptUser?->canAny(['view visa', 'view all visa'])) {
        foreach ($vptSetupItems as $vptItem) {
            try {
                $vptItem['url'] = route($vptItem['route'], ['role' => $vptRole]);
                $vptItem['is_active'] = request()->routeIs(...$vptItem['active']);
                $vptSetupLinks[] = $vptItem;
            } catch (\Throwable $e) {
            }
        }
    }
@endphp

@if(count($vptLinks) > 1)
{{-- Same band as Ticketing / Contract Flight / Contract File (owner, 2026-10-12:
     "not matching the layout, theme and design with ticketing"): flat, full
     width, hairline separators, the active tab underlined. Shared .ex-* names,
     styles repeated here so the band does not depend on include order. --}}
<div class="ex-toolbar">
    <nav class="ex-navband" aria-label="Visa processing sections">
        @foreach($vptLinks as $vptLink)
            <a href="{{ $vptLink['url'] }}"
               class="ex-navtab {{ $vptLink['is_active'] ? 'is-active' : '' }}"
               title="{{ $vptLink['label'] }}"
               @if($vptLink['is_active']) aria-current="page" @endif>
                <i class="fas {{ $vptLink['icon'] }}"></i>
                <span>{{ $vptLink['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
@if(count($vptSetupLinks) > 1)
<div class="vps-wrap">
    <div class="vps-switcher" role="tablist" aria-label="Visa setup">
        @foreach($vptSetupLinks as $vptSetupLink)
            <a href="{{ $vptSetupLink['url'] }}" class="vps-item {{ $vptSetupLink['is_active'] ? 'is-active' : '' }}" role="tab"
               @if($vptSetupLink['is_active']) aria-current="page" aria-selected="true" @else aria-selected="false" @endif>
                <i class="fas {{ $vptSetupLink['icon'] }}"></i><span>{{ $vptSetupLink['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
<style>
    /* Visa Setup switcher — same shape as Ticketing's Ticket Setup switcher. */
    .vps-wrap{display:flex;justify-content:flex-end;margin:-4px 0 14px;}
    .vps-switcher{display:inline-flex;flex-wrap:wrap;gap:2px;padding:3px;background:#fff;border:1px solid #e6e9f2;border-radius:11px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px rgba(79,70,229,.06);}
    .vps-item{display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 13px;border-radius:8px;font-size:11.5px;font-weight:600;color:#64748b;white-space:nowrap;text-decoration:none;transition:color .15s,background .15s;}
    .vps-item i{font-size:10px;color:#a5b4fc;}
    .vps-item:hover{color:#3730a3;background:#f5f6ff;text-decoration:none;}
    .vps-item.is-active{color:#fff;background:linear-gradient(135deg,#4f46e5,#7c3aed);box-shadow:0 2px 8px rgba(79,70,229,.32);}
    .vps-item.is-active i{color:#fff;}
    html.dark .vps-switcher{background:#1e293b;border-color:#334155;box-shadow:none;}
    html.dark .vps-item{color:#94a3b8;}
    @media(max-width:640px){.vps-wrap{justify-content:flex-start;}}
</style>
@endif
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
    @media(max-width:640px){ .ex-navtab{padding:10px 10px 11px;font-size:11.5px;} }
</style>
@endif

{{-- Travels page-load animation (owner, 2026-10-07: across all of Travels) --}}
@include('layout.travels-loader')
