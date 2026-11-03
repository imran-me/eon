{{-- Contract File section navigation. The sidebar has one section link; the
     section destinations live here while New File stays a page-level action. --}}
@php
    $cfTabUser = auth()->user();
    $cfTabRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($cfTabUser)->getRoleNames()->first() ?? '');

    $cfTabItems = [
        [
            'label' => 'File Dashboard', 'icon' => 'fa-chart-pie',
            'route' => 'role.contract-files.dashboard',
            'permissions' => ['view contract file', 'view all contract file'],
            'active' => ['role.contract-files.dashboard'],
        ],
        [
            'label'       => 'Files & Passengers',
            'icon'        => 'fa-clipboard-list',
            'route'       => 'role.contract-files.index',
            'permissions' => ['view contract file', 'view all contract file'],
            'active'      => ['role.contract-files.index','role.contract-files.agent-files','role.contract-files.show'],
        ],
        [
            'label' => 'Passenger Board', 'icon' => 'fa-columns',
            'route' => 'role.contract-files.board',
            'permissions' => ['view contract file', 'view all contract file'],
            'active' => ['role.contract-files.board'],
        ],
        [
            'label' => 'File Accounts', 'icon' => 'fa-file-invoice-dollar',
            'route' => 'role.contract-files.accounts',
            'permissions' => ['view contract file', 'view all contract file'],
            'active' => ['role.contract-files.accounts','role.contract-file-sales.*'],
        ],
        // "File Category" became Setup (owner, 2026-10-25): the requirement builder, price presets,
        // visa types and job categories. Lands on Requirements; falls back to Job Categories while
        // the server has not registered the Setup routes yet.
        \Illuminate\Support\Facades\Route::has('role.contract-file-setup.index') ? [
            'label' => 'Setup', 'icon' => 'fa-sliders',
            'route' => 'role.contract-file-setup.index', 'params' => ['kind' => 'requirements'],
            'permissions' => ['view contract file category', 'view all contract file category'],
            'active' => ['role.contract-file-categories.*', 'role.contract-file-setup.*'],
        ] : [
            'label' => 'Setup', 'icon' => 'fa-sliders',
            'route' => 'role.contract-file-categories.index',
            'permissions' => ['view contract file category', 'view all contract file category'],
            'active' => ['role.contract-file-categories.*'],
        ],
    ];

    $cfTabLinks = [];

    foreach ($cfTabItems as $cfTabItem) {
        if (! $cfTabUser?->canAny($cfTabItem['permissions'])) {
            continue;
        }

        try {
            $cfTabItem['url'] = route($cfTabItem['route'], ['role' => $cfTabRole] + ($cfTabItem['params'] ?? []));
        } catch (\Throwable $e) {
            continue;
        }

        $cfTabItem['is_active'] = request()->routeIs(...$cfTabItem['active']);
        $cfTabLinks[] = $cfTabItem;
    }
@endphp

@if(count($cfTabLinks) > 1)
{{-- The Contract Flight band, verbatim (owner, 2026-10-25: "make the contract
     file according to the Contract Flight"). Same .ex-* markup and rules as
     layout/contract-flight-tabs.blade.php, repeated here for the same reason
     that file repeats them: the band must not depend on include order. --}}
<div class="ex-toolbar">
    <nav class="ex-navband" aria-label="Contract file sections">
        @foreach($cfTabLinks as $cfTabLink)
            <a href="{{ $cfTabLink['url'] }}"
               class="ex-navtab {{ $cfTabLink['is_active'] ? 'is-active' : '' }}"
               title="{{ $cfTabLink['label'] }}"
               @if($cfTabLink['is_active']) aria-current="page" @endif>
                <i class="fas {{ $cfTabLink['icon'] }}"></i>
                <span>{{ $cfTabLink['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
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
