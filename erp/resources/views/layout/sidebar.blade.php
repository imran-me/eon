<style>
    #sidebar {
        background: white;
        border-right: 1px solid #e5e7eb;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.06);
    }

    .sidebar-section-label {
        padding: 10px 14px 4px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #9ca3af;
    }

    .sidebar-icon-box {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        flex-shrink: 0;
        color: #6b7280;
        transition: background 0.15s, color 0.15s;
    }

    #sidebar a.sidebar-item,
    #sidebar .sidebar-item > button.sidebar-top-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 10px;
        color: #4b5563;
        font-weight: 500;
        font-size: 13.5px;
        transition: all 0.15s ease;
        border-left: 3px solid transparent;
        width: 100%;
        text-align: left;
        cursor: pointer;
    }

    #sidebar a.sidebar-item:hover,
    #sidebar .sidebar-item > button.sidebar-top-btn:hover {
        background: #f9fafb;
        color: #1f2937;
    }

    #sidebar a.sidebar-item:hover .sidebar-icon-box,
    #sidebar .sidebar-item > button.sidebar-top-btn:hover .sidebar-icon-box {
        background: #eff6ff;
        color: #2563eb;
    }

    #sidebar a.sidebar-item.active {
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 600;
        border-left: 3px solid #2563eb;
    }

    #sidebar a.sidebar-item.active .sidebar-icon-box {
        background: #dbeafe;
        color: #1d4ed8;
    }

    #sidebar .submenu {
        margin-top: 2px;
        margin-left: 14px;
        padding-left: 14px;
        border-left: 2px solid #e5e7eb;
    }

    #sidebar .submenu-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 7px 10px;
        border-radius: 8px;
        color: #6b7280;
        font-size: 12.5px;
        font-weight: 500;
        transition: all 0.15s ease;
        margin: 1px 0;
        cursor: pointer;
    }

    #sidebar .submenu-item:hover {
        background: #f3f4f6;
        color: #111827;
    }

    #sidebar .submenu-item.active {
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 600;
    }

    .submenu-group-btn {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 7px 10px;
        border-radius: 8px;
        color: #4b5563;
        font-size: 12.5px;
        font-weight: 600;
        transition: all 0.15s ease;
        cursor: pointer;
    }

    .submenu-group-btn:hover {
        background: #f3f4f6;
        color: #111827;
    }

    .submenu-group-btn.is-active {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .sidebar-badge {
        margin-left: auto;
        background: #2563eb;
        color: white;
        font-size: 10px;
        padding: 1px 6px;
        border-radius: 50px;
        font-weight: 700;
        line-height: 1.6;
    }

    #sidebar .sidebar-nav::-webkit-scrollbar { width: 10px; }
    #sidebar .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
    #sidebar .sidebar-nav::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 9999px; }
    #sidebar .sidebar-nav::-webkit-scrollbar-thumb:hover { background: #d1d5db; }

    [x-cloak] { display: none !important; }

    #sidebarRail {
        background: #f9fafb;
    }

    .rail-icon-btn {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 2px solid transparent;
        background: transparent;
        transition: all 0.15s ease;
        cursor: pointer;
        padding: 0;
        overflow: hidden;
    }

    .rail-icon-btn img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 8px;
    }

    .rail-icon-btn span {
        width: 100%;
        height: 100%;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
    }

    .rail-icon-btn:hover { background: #eff6ff; }

    /* Border and halo follow the company's own accent, so the selected tile
       reads as one colour with its glyph. The blue values stay as fallbacks for
       anything rendering a rail button without the accent vars set. */
    .rail-icon-btn.active {
        border-color: var(--rail-accent, #2563eb);
        box-shadow: 0 0 0 2px var(--rail-tint, #eff6ff);
    }

    /* The rail draws a Bootstrap Icons glyph where it used to draw the uploaded
       logo. These two rules only size and colour that glyph — the tile itself
       (40px box, radius, border, hover, active ring) is untouched above, so the
       rail measures exactly what it did before. 20px is the glyph size the
       Modular ERP reference build uses for the same rail. */
    .rail-icon-btn i {
        font-size: 20px;
        line-height: 1;
        color: #64748b;
    }

    .rail-icon-btn.active i { color: var(--rail-accent, #2563eb); }

    #sidebarPanel::-webkit-scrollbar { width: 8px; }
    #sidebarPanel::-webkit-scrollbar-track { background: transparent; }
    #sidebarPanel::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 9999px; }

    #sidebarRail::-webkit-scrollbar { width: 0; }
</style>

@php
    // The icon-rail + switchable panel only makes sense for users who can see
    // more than one company. Anyone with a company_id is locked to their own
    // (occasionally two, via the Travel-permission escape hatch below) — they
    // get the original flat single-column sidebar instead, no rail needed.
    $userCompanyId = auth()->user()?->company_id;
    $showCompanyRail = empty($userCompanyId);
@endphp
<aside id="sidebar"
    class="sidebar fixed top-0 left-0 h-full {{ $showCompanyRail ? 'w-72' : 'w-64' }} z-20 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col"
    x-data="{ search: '', activeCompanyId: null }">

    {{-- ── Logo ── --}}
    <div class="flex justify-between items-center px-4 py-3.5 border-b border-gray-100 shrink-0">
        <div class="flex items-center gap-3">
            @php
                $logoUrl = site_setting_url('logo', asset('image/company/69cb5771cc187.png'));
                $appName = site_setting('app_name', config('app.name'));
            @endphp
            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 overflow-hidden"
                 style="box-shadow:0 4px 12px rgba(37,99,235,0.3);">
                <img src="{{ $logoUrl }}" alt="{{ $appName }}"
                     style="height:32px;max-width:100%;object-fit:contain;"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                <span class="text-white text-sm font-bold" style="display:none;">EP</span>
            </div>
            <div>
                <div class="text-sm font-extrabold text-gray-900 leading-tight">{{ $appName }}</div>
                <div class="text-xs text-gray-400">Enterprise Suite</div>
            </div>
        </div>
        <button id="closeSidebar" class="text-gray-400 md:hidden hover:text-gray-600 transition-colors p-1">
            <i class="fas fa-times text-base"></i>
        </button>
    </div>

    {{-- ── Search ── --}}
    <div class="px-3 pt-3 pb-1 shrink-0">
        <div class="flex items-center gap-2 bg-gray-100 rounded-xl px-3 py-2">
            <i class="fas fa-search text-gray-400" style="font-size:11px;"></i>
            <input x-model="search" type="text" placeholder="Search menu..."
                   class="bg-transparent text-sm text-gray-700 outline-none w-full placeholder-gray-400">
        </div>
    </div>

    @php
        $role = Str::slug(Auth::user()->getRoleNames()->first());
    @endphp

    {{-- ── Navigation ── --}}
    @php
        $travelCompanyId = 2;
        $travelPermissions = [
            'view vendor',
            'view all vendor',
            'create vendor',
            'edit vendor',
            'delete vendor',
            'view portal',
            'view all portal',
            'create portal',
            'edit portal',
            'delete portal',
            'manage portal',
            'view airline',
            'view all airline',
            'view ticket',
            'view all ticket',
            'view ticket direct sale',
            'view all ticket direct sale',
            'create ticket direct sale',
            'view ticket sale',
            'view all ticket sale',
            'view geography',
            'view all geography',
            'view flight schedule',
            'view all flight schedule',
            'create flight schedule',
            'view contract flight',
            'view all contract flight',
            'view contract flight sale',
            'view all contract flight sale',
            'view flight category',
            'view all flight category',
            'view passport holder',
            'view all passport holder',
            'view visa',
            'view all visa',
        ];
        $visibleCompanyIds = [];
        $query = \App\Models\Company::orderBy('order', 'asc');

        if (!empty($userCompanyId)) {
            $visibleCompanyIds[] = $userCompanyId;

            if (auth()->user()?->hasAnyPermission($travelPermissions)) {
                $visibleCompanyIds[] = $travelCompanyId;
            }

            $query->whereIn('id', array_unique($visibleCompanyIds));
        }

        $allCompanies = $query->where('status', 1)->get();

        // Default rail selection: whichever company's dashboard is in the
        // current URL (route param), else whichever company the user was
        // last browsing within (carried via a ?company= query param that
        // JS stamps onto every link inside the active company's menu —
        // most feature pages like Portal Management have no {company}
        // route param at all, so without this the rail would silently
        // fall back to the first company on every page except the literal
        // Company Dashboard), else the user's own company, else the first
        // visible company.
        $routeCompanyId = request()->route('company') ?? request()->query('company');
        // Wood Art module routes (role.woodart.*) carry no {company} param —
        // they ARE company-6 context. Guarded addition: the condition is false
        // on every non-Wood-Art route, so nothing else changes. Without it a
        // bare /woodart/... URL (bookmark, shared link) falls back to the first
        // company's panel under Wood Art content.
        if (!$routeCompanyId && request()->routeIs('role.woodart.*')) {
            $routeCompanyId = 6;
        }
        $activeCompanyId = $routeCompanyId
            ? (int) $routeCompanyId
            : ($userCompanyId ?: optional($allCompanies->first())->id);
    @endphp
    <nav class="sidebar-nav flex-1 flex flex-row min-h-0" x-init="activeCompanyId = {{ $activeCompanyId ?? 'null' }}">

        {{-- ── Company icon rail — only for users who can see more than one company ── --}}
        @if($showCompanyRail && $allCompanies->isNotEmpty())
        <div id="sidebarRail" class="shrink-0 w-16 flex flex-col items-center gap-1.5 py-3 overflow-y-auto border-r border-gray-100">
            @php
                // TRUE only while the page you are looking at belongs to Wood Art
                // itself: its Company Dashboard (/company/6/...) or any module
                // screen (role.woodart.*). Deliberately keyed on the ROUTE, not
                // $routeCompanyId: /admin/dashboard?company=6 is the mother
                // dashboard merely *displaying* Wood Art's menu, and must keep
                // the original behaviour.
                $onWoodArtPage  = request()->route('company') == 6
                    || request()->routeIs('role.woodart.*');
                // role.dashboard is gated by 'view dashboard'; without it, fall
                // through to the original button rather than offer a 403 link.
                $canMotherDash  = auth()->user()?->can('view dashboard');
            @endphp
            @foreach($allCompanies as $c)
                @php $railIcon = company_icon($c); @endphp
                @if($c->id == 6)
                {{-- ONLY Wood Art's icon navigates — it opens its Company
                     Dashboard. Rendered as an <a> instead of a <button>;
                     .rail-icon-btn is class-scoped, not button-scoped, so the tile
                     looks identical. The @click still fires so the menu switches
                     immediately rather than waiting for the page load.

                     Every other company's icon is left EXACTLY as it was — a plain
                     panel-switcher that sets activeCompanyId and does not navigate
                     — on every page EXCEPT a Wood Art page (see the @elseif). Do
                     not "fix" that asymmetry by turning them all into links. --}}
                <a href="{{ route('role.company.dashboard', ['role' => $role, 'company' => $c->id]) }}"
                    @click="activeCompanyId = {{ $c->id }}"
                    :class="activeCompanyId === {{ $c->id }} ? 'rail-icon-btn active' : 'rail-icon-btn'"
                    style="--rail-accent:{{ $railIcon['accent'] }};--rail-tint:{{ $railIcon['tint'] }};"
                    title="{{ $c->name }}">
                    <i class="{{ $railIcon['class'] }}"></i>
                </a>
                @elseif($onWoodArtPage && $canMotherDash)
                {{-- Leaving Wood Art. Because Wood Art's icon navigates, you can be
                     sitting ON its Company Dashboard — and a panel-switcher alone
                     would leave Wood Art's content on screen under another
                     company's menu. So while (and only while) you are on a Wood Art
                     page, the other icons carry you back to the mother dashboard,
                     with ?company= so that company's menu is the one shown — the
                     view these icons have always landed you on.

                     On every other page this branch is false and the untouched
                     <button> below renders instead, so nothing changes for these
                     companies anywhere outside Wood Art. --}}
                <a href="{{ route('role.dashboard', ['role' => $role, 'company' => $c->id]) }}"
                    @click="activeCompanyId = {{ $c->id }}"
                    :class="activeCompanyId === {{ $c->id }} ? 'rail-icon-btn active' : 'rail-icon-btn'"
                    style="--rail-accent:{{ $railIcon['accent'] }};--rail-tint:{{ $railIcon['tint'] }};"
                    title="{{ $c->name }}">
                    <i class="{{ $railIcon['class'] }}"></i>
                </a>
                @else
                <button type="button"
                    @click="activeCompanyId = {{ $c->id }}"
                    :class="activeCompanyId === {{ $c->id }} ? 'rail-icon-btn active' : 'rail-icon-btn'"
                    style="--rail-accent:{{ $railIcon['accent'] }};--rail-tint:{{ $railIcon['tint'] }};"
                    title="{{ $c->name }}">
                    <i class="{{ $railIcon['class'] }}"></i>
                </button>
                @endif
            @endforeach
        </div>
        @endif

        {{-- ── Detail panel ── --}}
        <div id="sidebarPanel" class="flex-1 min-w-0 overflow-y-auto px-2 py-2 space-y-0.5">

        @can('view dashboard')
            {{-- Hidden while the Wood Art panel is active. That company's menu
                 carries its own Dashboard module (it is the first entry in the
                 suite's own registry), so showing this global link as well put
                 "Dashboard" in the sidebar twice. Every other company's panel
                 opens with "Company Dashboard" and has no plain Dashboard entry,
                 so they are unaffected and keep this link. --}}
            <a href="{{ route('role.dashboard', ['role' => $role]) }}"
               x-show="(!search || 'dashboard'.includes(search.toLowerCase())) && activeCompanyId !== 6"
               class="sidebar-item flex items-center gap-2.5 {{ request()->routeIs('role.dashboard') ? 'active' : '' }}">
                <div class="sidebar-icon-box"><i class="fas fa-chart-line"></i></div>
                <span>Dashboard</span>
            </a>
        @endcan

        {{-- COMPANIES --}}
        @if($allCompanies->isNotEmpty())
        @if($showCompanyRail)
        <div class="sidebar-section-label" x-show="!search" style="color:#2563eb;">Companies</div>
        @endif
        @foreach($allCompanies as $c)
        @php
            $initials = collect(explode(' ', $c->name))
                ->filter()->map(fn($w) => strtoupper($w[0]))->take(2)->implode('');
            $colors = ['#3b82f6','#8b5cf6','#06b6d4','#f59e0b','#10b981','#f43f5e','#6366f1','#a16207'];
            $color  = $colors[$c->id % count($colors)];
            $isActive = request()->route('company') == $c->id;
            $isTravelSectionActive = $c->id == 2 && request()->routeIs('role.vendor.*', 'role.customer.*', 'role.portal-management.*','role.passport-holder.*','role.passport-holder-category.*','role.visa.*','role.visa-sales.*', 'role.other-visa-services.*','role.other-service-types.*','role.visa-category.*','role.countries.*','role.states.*','role.airlines.*','role.ticket-direct-sale.*','role.ticket-purchase.*','role.airport.*','role.ticket-sales.*','role.agent.*','role.tickets.*','role.flight-categories.*','role.flight-category-types.*','role.flight-price-presets.*','role.flight-officers.*','role.flight-schedules.*','role.contract-flight-sales.*','role.contract-flights.*','role.contract-file-categories.*','role.contract-files.*','role.contract-file-sales.*');
        @endphp
        {{-- First paint on a WOOD ART page must already show the correct menu.

             All panels are x-cloak'd until Alpine boots (deferred, from a CDN),
             so on a cold cache the sidebar shows no company menu for up to a
             second. That flash predates Wood Art everywhere, but only Wood Art
             pages actually land users on it (its rail icon navigates), so ONLY
             the Wood Art context ($activeCompanyId == 6) gets the fix: the
             active panel renders visible, the rest inline-hidden, and Alpine's
             x-show takes over from exactly that state. Every other context
             keeps the original x-cloak byte-for-byte (CLAUDE.md: no unguarded
             change to a shared line). The same flash on other companies' pages
             is a pre-existing issue — reported, deliberately not fixed here. --}}
        <div @if($showCompanyRail) x-show="activeCompanyId === {{ $c->id }}" @if($activeCompanyId == 6) @if($c->id != $activeCompanyId) style="display:none" @endif @else x-cloak @endif @endif>
        @if($c->id == 2)
        @php
            $companyLogoUrl = null;
            if (!empty($c->icon)) {
                $companyLogoUrl = \Illuminate\Support\Str::startsWith($c->icon, ['http://', 'https://'])
                    ? $c->icon
                    : asset($c->icon);
            }
        @endphp
        <div class="sidebar-item"
             x-show="!search || '{{ strtolower($c->name) }}'.includes(search.toLowerCase()) || 'epal travels'.includes(search.toLowerCase())">
            {{-- Once a company is selected (rail click) or for a company-locked
                 user, its whole menu shows directly as the main menu — no
                 second click-to-expand step. The header below is just a
                 label for context, not a toggle. --}}
            @if($showCompanyRail)
            {{--<div class="sidebar-top-btn w-full flex justify-between items-center {{ $isActive || $isTravelSectionActive ? 'active' : '' }}" style="cursor:default;">
                <div class="flex items-center gap-2.5">
                    @if($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="{{ $c->name }}" class="rounded-md" style="width:25px;height:25px;object-fit:cover;flex-shrink:0;" />
                    @else
                    <span style="width:25px;height:25px;border-radius:5px;background:{{ $color }};display:inline-flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#fff;flex-shrink:0;">{{ $initials }}</span>
                    @endif
                    <span class="text-sm font-medium">{{ $c->short_name ?? $c->name }}</span>
                </div>
            </div> --}}
            @endif
            {{-- Deliberately NOT tagged "submenu" here, for either rail users or
                 company-locked users, since toggleSubmenu() treats every
                 ".submenu" in the document as a closeable sibling — this
                 content must stay open regardless of what else gets toggled
                 elsewhere in the sidebar. --}}
            <div id="company2Submenu" data-company-id="{{ $c->id }}"
                 class="space-y-0.5" style="margin-left:0;padding-left:0;border-left:none;">
                <a href="{{ route('role.company.dashboard', ['role' => $role, 'company' => $c->id]) }}"
                   class="submenu-item {{ $isActive ? 'active' : '' }}">
                    <div class="sidebar-icon-box"><i class="fas fa-building"></i></div>
                    <span>Company Dashboard</span>
                </a>

                {{-- OPERATIONS --}}
                <div class="sidebar-section-label" style="padding:8px 4px 2px;font-size:9px;letter-spacing:1.2px;color:#9ca3af;">OPERATIONS</div>

                @canany(['view vendor','view all vendor','create vendor','edit vendor','delete vendor','view customer','view all customer','create customer','edit customer','delete customer'])
                <div class="sidebar-item" x-show="!search || 'party management vendor agent customer'.includes(search.toLowerCase())">
                    <button onclick="toggleSubmenu('vendorSubmenu', this)" class="sidebar-top-btn w-full flex justify-between items-center">
                        <div class="flex items-center gap-2.5">
                            <div class="sidebar-icon-box {{ request()->routeIs('role.vendor.*','role.agent.*','role.customer.*','role.party-statement.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-users"></i></div>
                            <span class="text-sm font-medium">Party Management</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="vendorSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.vendor.*','role.agent.*','role.customer.*','role.party-statement.*') ? '' : 'hidden' }}">
                        @canany(['view vendor','view all vendor'])
                        <a href="{{ route('role.vendor.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.vendor.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Manage Vendor</a>
                        @endcanany
                        @canany(['view vendor','view all vendor'])
                        <a href="{{ route('role.agent.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.agent.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Manage Agent</a>
                        @endcanany
                        @canany(['view customer','view all customer'])
                        <a href="{{ route('role.customer.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.customer.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Manage Customer</a>
                        @endcanany
                        <a href="{{ route('role.party-statement.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.party-statement.*') ? 'active' : '' }}"><i class="fas fa-book text-xs w-3"></i> Party Statement</a>
                    </div>
                </div>
                @endcanany

                @canany(['view portal','view all portal','create portal','edit portal','delete portal','manage portal'])
                <div class="sidebar-item" x-show="!search || 'portal management'.includes(search.toLowerCase())">
                    <button onclick="toggleSubmenu('portalSubmenu', this)" class="sidebar-top-btn w-full flex justify-between items-center">
                        <div class="flex items-center gap-2.5">
                            <div class="sidebar-icon-box {{ request()->routeIs('role.portal-management.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-globe"></i></div>
                            <span class="text-sm font-medium">Portal Management</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="portalSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.portal-management.*') ? '' : 'hidden' }}">
                        @can('create portal')
                        <a href="{{ route('role.portal-management.create', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.portal-management.create') ? 'active' : '' }}"><i class="fas fa-plus text-xs w-3"></i> Add Portal</a>
                        @endcan
                        @canany(['view portal','view all portal'])
                        <a href="{{ route('role.portal-management.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.portal-management.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Manage Portal</a>
                        @endcanany
                    </div>
                </div>
                @endcanany

                @canany(['view passport holder','view all passport holder'])
                <div class="sidebar-item" x-show="!search || 'passport management holder'.includes(search.toLowerCase())">
                    <button onclick="toggleSubmenu('PassportHolderSubmenu', this)" class="sidebar-top-btn w-full flex justify-between items-center">
                        <div class="flex items-center gap-2.5">
                            <div class="sidebar-icon-box {{ request()->routeIs('role.passport-holder.*','role.passport-holder-category.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-id-card"></i></div>
                            <span class="text-sm font-medium">Passport Management</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="PassportHolderSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.passport-holder.*','role.passport-holder-category.*') ? '' : 'hidden' }}">
                        <a href="{{ route('role.passport-holder-category.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.passport-holder-category.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Passport Category</a>
                        <a href="{{ route('role.passport-holder.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.passport-holder.index') ? 'active' : '' }}"><i class="fas fa-id-card text-xs w-3"></i> Passport Holder</a>
                    </div>
                </div>

                @endcanany

                {{-- SERVICES --}}
                <div class="sidebar-section-label" style="padding:8px 4px 2px;font-size:9px;letter-spacing:1.2px;color:#9ca3af;">SERVICES</div>

                {{-- Air Ticketing --}}
                @canany(['view airline','view all airline','view ticket','view all ticket','view ticket purchase','view all ticket purchase','view ticket direct sale','view all ticket direct sale','create ticket direct sale','view ticket sale','view all ticket sale','view geography','view all geography'])
                <div class="sidebar-item" x-show="!search || 'air ticketing airline'.includes(search.toLowerCase())">
                    <button onclick="toggleSubmenu('airTicketingSubmenu', this)" class="sidebar-top-btn w-full flex justify-between items-center">
                        <div class="flex items-center gap-2.5">
                            <div class="sidebar-icon-box {{ request()->routeIs('role.airlines.*','role.tickets.*','role.ticket-purchase.*','role.ticket-direct-sale.*','role.ticket-sales.*','role.airport.*','role.countries.*','role.states.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-ticket-alt"></i></div>
                            <span class="text-sm font-medium">Air Ticketing</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="airTicketingSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.airlines.*','role.tickets.*','role.ticket-purchase.*','role.ticket-direct-sale.*','role.ticket-sales.*','role.airport.*','role.countries.*','role.states.*') ? '' : 'hidden' }}">
                        @canany(['view ticket','view all ticket'])
                        <a href="{{ route('role.tickets.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.tickets.*') ? 'active' : '' }}"><i class="fas fa-ticket-alt text-xs w-3"></i> Ticket Manage</a>
                        @endcanany
                        @canany(['view ticket purchase','view all ticket purchase'])
                        <a href="{{ route('role.ticket-purchase.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.ticket-purchase.*') ? 'active' : '' }}"><i class="fas fa-shopping-cart text-xs w-3"></i> Ticket Purchase</a>
                        @endcanany
                        @can('create ticket direct sale')
                        <a href="{{ route('role.ticket-direct-sale.create', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.ticket-direct-sale.*') ? 'active' : '' }}"><i class="fas fa-ticket-alt text-xs w-3"></i> Ticketing</a>
                        @endcan
                        @canany(['view ticket sale','view all ticket sale'])
                        <a href="{{ route('role.ticket-sales.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.ticket-sales.*') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Manage Sales</a>
                        @endcanany
                        @canany(['view airline','view all airline'])
                        <a href="{{ route('role.airlines.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.airlines.*') ? 'active' : '' }}"><i class="fas fa-plane text-xs w-3"></i> Airlines</a>
                        @endcanany
                        @canany(['view geography','view all geography'])
                        <a href="{{ route('role.airport.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.airport.index') ? 'active' : '' }}"><i class="fas fa-plane-departure text-xs w-3"></i> Airport Management</a>
                        <a href="{{ route('role.countries.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.countries.index') ? 'active' : '' }}"><i class="fas fa-flag text-xs w-3"></i> Country</a>
                        <a href="{{ route('role.states.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.states.index') ? 'active' : '' }}"><i class="fas fa-city text-xs w-3"></i> States</a>
                        @endcanany
                    </div>
                </div>
                @endcanany

                {{-- Contract Flight --}}
                @canany(['view flight schedule','view all flight schedule','create flight schedule','view contract flight','view all contract flight','view contract flight sale','view all contract flight sale','view flight category','view all flight category'])
                <div class="sidebar-item" x-show="!search || 'contract flight'.includes(search.toLowerCase())">
                    <button onclick="toggleSubmenu('contractFlightSubmenu', this)" class="sidebar-top-btn w-full flex justify-between items-center">
                        <div class="flex items-center gap-2.5">
                            <div class="sidebar-icon-box {{ request()->routeIs('role.flight-categories.*','role.flight-category-types.*','role.flight-price-presets.*','role.flight-officers.*','role.flight-schedules.*','role.contract-flight-sales.*','role.contract-flights.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-file-signature"></i></div>
                            <span class="text-sm font-medium">Contract Flight</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="contractFlightSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.flight-categories.*','role.flight-category-types.*','role.flight-price-presets.*','role.flight-officers.*','role.flight-schedules.*','role.contract-flight-sales.*','role.contract-flights.*') ? '' : 'hidden' }}">
                        @canany(['view flight schedule','view all flight schedule'])
                        <a href="{{ route('role.flight-schedules.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.flight-schedules.*') ? 'active' : '' }}"><i class="fas fa-calendar-alt text-xs w-3"></i> Flight Schedule</a>
                        @endcanany
                        @can('create flight schedule')
                        <a href="{{ route('role.flight-schedules.index', ['role' => $role, 'open' => 'create']) }}" class="submenu-item"><i class="fas fa-plus text-xs w-3"></i> Add Flight</a>
                        @endcan
                        @canany(['view contract flight','view all contract flight'])
                        <a href="{{ route('role.contract-flights.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.contract-flights.*') ? 'active' : '' }}"><i class="fas fa-suitcase text-xs w-3"></i> Manage Flight</a>
                        @endcanany
                        @canany(['view contract flight sale','view all contract flight sale'])
                        <a href="{{ route('role.contract-flight-sales.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.contract-flight-sales.*') ? 'active' : '' }}"><i class="fas fa-briefcase text-xs w-3"></i> Manage Sales</a>
                        @endcanany
                        @canany(['view flight category','view all flight category'])
                        <a href="{{ route('role.flight-categories.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.flight-categories.*') ? 'active' : '' }}"><i class="fas fa-tags text-xs w-3"></i> Flight Category</a>
                        <a href="{{ route('role.flight-category-types.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.flight-category-types.*') ? 'active' : '' }}"><i class="fas fa-tag text-xs w-3"></i> Flight Category Type</a>
                        <a href="{{ route('role.flight-price-presets.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.flight-price-presets.*') ? 'active' : '' }}"><i class="fas fa-coins text-xs w-3"></i> Price Presets</a>
                        <a href="{{ route('role.flight-officers.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.flight-officers.*') ? 'active' : '' }}"><i class="fas fa-user-shield text-xs w-3"></i> Officers</a>
                        @endcanany
                    </div>
                </div>
                @endcanany

                {{-- Contract File --}}
                @canany(['view contract file category','view all contract file category','view contract file','view all contract file','create contract file','view contract file sale','view all contract file sale'])
                <div class="sidebar-item" x-show="!search || 'contract file'.includes(search.toLowerCase())">
                    <button onclick="toggleSubmenu('contractFileSubmenu', this)" class="sidebar-top-btn w-full flex justify-between items-center">
                        <div class="flex items-center gap-2.5">
                            <div class="sidebar-icon-box {{ request()->routeIs('role.contract-file-categories.*','role.contract-files.*','role.contract-file-sales.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-folder"></i></div>
                            <span class="text-sm font-medium">Contract File</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="contractFileSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.contract-file-categories.*','role.contract-files.*','role.contract-file-sales.*') ? '' : 'hidden' }}">
                        @canany(['view contract file category','view all contract file category'])
                        <a href="{{ route('role.contract-file-categories.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.contract-file-categories.*') ? 'active' : '' }}"><i class="fas fa-folder text-xs w-3"></i> File Category</a>
                        @endcanany
                        @can('create contract file')
                        <a href="{{ route('role.contract-files.index', ['role' => $role, 'open' => 'create']) }}" class="submenu-item"><i class="fas fa-plus text-xs w-3"></i> New File</a>
                        @endcan
                        @canany(['view contract file','view all contract file'])
                        <a href="{{ route('role.contract-files.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.contract-files.*') ? 'active' : '' }}"><i class="fas fa-clipboard-list text-xs w-3"></i> Applications Board</a>
                        @endcanany
                        @canany(['view contract file sale','view all contract file sale'])
                        <a href="{{ route('role.contract-file-sales.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.contract-file-sales.*') ? 'active' : '' }}"><i class="fas fa-briefcase text-xs w-3"></i> Manage Sales</a>
                        @endcanany
                    </div>
                </div>
                @endcanany

                {{-- Visa Processing --}}
                @canany(['view visa','view all visa'])
                <div class="sidebar-item" x-show="!search || 'visa processing'.includes(search.toLowerCase())">
                    <button onclick="toggleSubmenu('visaProcessingSubmenu', this)" class="sidebar-top-btn w-full flex justify-between items-center">
                        <div class="flex items-center gap-2.5">
                            <div class="sidebar-icon-box {{ request()->routeIs('role.visa.*','role.visa-category.*','role.visa-sales.*','role.other-visa-services.*','role.other-service-types.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-passport"></i></div>
                            <span class="text-sm font-medium">Visa Processing</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="visaProcessingSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.visa.*','role.visa-category.*','role.visa-sales.*','role.other-visa-services.*','role.other-service-types.*') ? '' : 'hidden' }}">
                        @canany(['view visa','view all visa'])
                        <a href="{{ route('role.visa.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.visa.index') ? 'active' : '' }}"><i class="fas fa-th-large text-xs w-3"></i> Application Board</a>
                        <a href="{{ route('role.visa-sales.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.visa-sales.*') ? 'active' : '' }}"><i class="fas fa-receipt text-xs w-3"></i> Manage Sales</a>
                        <a href="{{ route('role.other-visa-services.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.other-visa-services.*') ? 'active' : '' }}"><i class="fas fa-puzzle-piece text-xs w-3"></i> Other Services</a>
                        <a href="{{ route('role.other-service-types.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.other-service-types.*') ? 'active' : '' }}"><i class="fas fa-tags text-xs w-3"></i> Service Types</a>
                    {{-- <a href="{{ route('role.visa.create', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.visa.create') ? 'active' : '' }}"><i class="fas fa-plus text-xs w-3"></i> New Application</a> --}}
                        <a href="{{ route('role.visa-category.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.visa-category.*') ? 'active' : '' }}"><i class="fas fa-folder text-xs w-3"></i> Visa Category</a>
                    {{-- <a href="{{ route('role.visa.doc-tracker', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.visa.doc-tracker') ? 'active' : '' }}"><i class="fas fa-file-alt text-xs w-3"></i> Docs Tracker</a> --}}
                        @endcanany
                    </div>
                </div>
                @endcanany

                {{-- FINANCE & HR — relocated here so Travel's manager has everything
                     (operations + accounts + payroll) under one company menu instead
                     of a separate global section. Same permission checks as before,
                     just nested one level deeper, with unique IDs to avoid collisions
                     with the global copy (still shown to every other company). --}}
                {{--
                @canany(['view expense','view bank','view department','view designation','view attendance','view attendance settings','view shift','view holidays','view leave','view leaves','view attendances','view resignation','view salary template','view salary','view loan','view payslip','view advance salary','view commission'])
                <div class="sidebar-section-label" style="padding:8px 4px 2px;font-size:9px;letter-spacing:1.2px;color:#9ca3af;">FINANCE &amp; HR</div>

                    @canany(['view expense','view bank','view general ledger report','view trial balance report','view profit loss report','view balance sheet report','view account ledger report','view account statement report','view journal entry report','view account balance report'])
                    <div>
                        <button onclick="toggleSubmenu('travelAccountsMenu', this)"
                            class="submenu-group-btn {{ request()->routeIs('role.expense*','role.petty-cash.*','role.accounts.*','role.banks.*','role.bank_transfers.*','role.journals.*','role.payment-schedules.*','role.report.general-ledger','role.report.trial-balance','role.report.profit-loss','role.report.balance-sheet','role.report.account-ledger','role.report.account-statement','role.report.journal-entries','role.report.account-balances') ? 'is-active' : '' }}">
                            <div class="flex items-center gap-2"><div class="sidebar-icon-box"><i class="fas fa-coins"></i></div><span>Accounts</span></div>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                        </button>
                        <div id="travelAccountsMenu" class="submenu space-y-0.5 {{ request()->routeIs('role.expense*','role.petty-cash.*','role.accounts.*','role.banks.*','role.bank_transfers.*','role.journals.*','role.payment-schedules.*','role.report.general-ledger','role.report.trial-balance','role.report.profit-loss','role.report.balance-sheet','role.report.account-ledger','role.report.account-statement','role.report.journal-entries','role.report.account-balances') ? '' : 'hidden' }}">
                            @can('view expense')
                            <a href="{{ route('role.expenses.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.expense*','role.petty-cash.*') ? 'active' : '' }}"><i class="fas fa-money-bill-wave text-xs w-3"></i> Expenses</a>
                            @endcan
                            <a href="{{ route('role.accounts.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.accounts.index') ? 'active' : '' }}"><i class="fas fa-user-circle text-xs w-3"></i> Manage Accounts</a>
                            <a href="{{ route('role.journals.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.journals.index') ? 'active' : '' }}"><i class="fas fa-book text-xs w-3"></i> Manage Journals</a>
                            @can('view payment schedule')
                                <a href="{{ route('role.payment-schedules.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.payment-schedules.*') ? 'active' : '' }}"><i class="fas fa-calendar-alt text-xs w-3"></i> Payment Schedules</a>
                            @endcan
                            <a href="{{ route('role.party-types.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.party-types.*') ? 'active' : '' }}"><i class="fas fa-tags text-xs w-3"></i> Party Types</a>
                            @can('view bank')
                            <div>
                                <button onclick="toggleSubmenu('travelBankSubmenu', this)"
                                    class="submenu-group-btn {{ request()->routeIs('role.banks.*','role.bank_transfers.*') ? 'is-active' : '' }}">
                                    <div class="flex items-center gap-2"><div class="sidebar-icon-box"><i class="fas fa-building-columns"></i></div><span>Manage Banks</span></div>
                                    <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                                </button>
                                <div id="travelBankSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.banks.*','role.bank_transfers.*') ? '' : 'hidden' }}">
                                    <a href="{{ route('role.banks.dashboard', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.banks.dashboard') ? 'active' : '' }}"><i class="fas fa-chart-pie text-xs w-3"></i> Bank Accounts Dashboard</a>
                                    <a href="{{ route('role.banks.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.banks.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> All Banks</a>
                                    <a href="{{ route('role.bank_transfers.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.bank_transfers.index') ? 'active' : '' }}"><i class="fas fa-exchange-alt text-xs w-3"></i> Bank Transfers</a>
                                </div>
                            </div>
                            @endcan
                            @canany(['view general ledger report', 'view trial balance report', 'view profit loss report', 'view balance sheet report', 'view account ledger report', 'view account statement report', 'view journal entry report', 'view account balance report'])
                            <a href="{{ route('role.report.general-ledger', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.report.general-ledger','role.report.trial-balance','role.report.profit-loss','role.report.balance-sheet','role.report.account-ledger','role.report.account-statement','role.report.journal-entries','role.report.account-balances') ? 'active' : '' }}"><i class="fas fa-chart-pie text-xs w-3"></i> Account Reports</a>
                            @endcanany
                        </div>
                    </div>
                    @endcanany

                    @canany(['view department','view designation','view attendance','view attendance settings','view shift','view holidays','view leave','view leaves','view attendances','view resignation'])
                    <div>
                        <button onclick="toggleSubmenu('travelHrmSubmenu', this)"
                            class="submenu-group-btn {{ request()->routeIs('role.departments.*','role.designations.*','role.holidays.*','role.shifts.*','role.leaves.*','role.attendances.*','role.leave-types.*','role.attendence-settings.*','role.resignations.*') ? 'is-active' : '' }}">
                            <div class="flex items-center gap-2"><div class="sidebar-icon-box"><i class="fas fa-users"></i></div><span>HRM</span></div>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                        </button>
                        <div id="travelHrmSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.departments.*','role.designations.*','role.holidays.*','role.shifts.*','role.leaves.*','role.attendances.*','role.leave-types.*','role.attendence-settings.*','role.resignations.*') ? '' : 'hidden' }}">
                            @can('view department')
                            <a href="{{ route('role.departments.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.departments.index') ? 'active' : '' }}"><i class="fas fa-building text-xs w-3"></i> Department</a>
                            @endcan
                            @can('view designation')
                            <a href="{{ route('role.designations.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.designations.index') ? 'active' : '' }}"><i class="fas fa-id-badge text-xs w-3"></i> Designation</a>
                            @endcan
                            @can('view attendance settings')
                            <a href="{{ route('role.attendence-settings.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.attendence-settings.index') ? 'active' : '' }}"><i class="fas fa-cog text-xs w-3"></i> Attendance Settings</a>
                            @endcan
                            @can('view shift')
                            <a href="{{ route('role.shifts.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.shifts.index') ? 'active' : '' }}"><i class="fas fa-clock text-xs w-3"></i> Shift</a>
                            @endcan
                            @can('view holiday')
                            <a href="{{ route('role.holidays.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.holidays.index') ? 'active' : '' }}"><i class="fas fa-calendar text-xs w-3"></i> Holidays</a>
                            @endcan
                            @can('view leave type')
                            <a href="{{ route('role.leave-types.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.leave-types.index') ? 'active' : '' }}"><i class="fas fa-calendar-minus text-xs w-3"></i> Leave Types</a>
                            @endcan
                            @can('view leave')
                            <a href="{{ route('role.leaves.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.leaves.index') ? 'active' : '' }}"><i class="fas fa-calendar-check text-xs w-3"></i> All Leaves</a>
                            @endcan
                            @can('view resignation')
                            <a href="{{ route('role.resignations.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.resignations.index') ? 'active' : '' }}"><i class="fas fa-sign-out-alt text-xs w-3"></i> Resignations</a>
                            @endcan
                            @can('view attendance')
                            <a href="{{ route('role.attendances.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.attendances.index') ? 'active' : '' }}"><i class="fas fa-fingerprint text-xs w-3"></i> All Attendances</a>
                            @endcan
                        </div>
                    </div>
                    @endcanany

                    @canany(['view salary template','view salary','view loan','view payslip','view advance salary','view commission'])
                    <div>
                        <button onclick="toggleSubmenu('travelPayrollSubmenu', this)"
                            class="submenu-group-btn {{ request()->routeIs('role.salary-templates.*','role.employee-salaries.*','role.loans.*','role.payslips.*','role.advance-salaries.*','role.commissions.*','role.report.payroll','role.report.payroll.*') ? 'is-active' : '' }}">
                            <div class="flex items-center gap-2"><div class="sidebar-icon-box"><i class="fas fa-money-check-alt"></i></div><span>Payroll Travel</span></div>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                        </button>
                        <div id="travelPayrollSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.salary-templates.*','role.employee-salaries.*','role.loans.*','role.payslips.*','role.advance-salaries.*','role.commissions.*','role.report.payroll','role.report.payroll.*') ? '' : 'hidden' }}">
                            @can('view salary template')
                            <a href="{{ route('role.salary-templates.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.salary-templates.index') ? 'active' : '' }}"><i class="fas fa-file-invoice-dollar text-xs w-3"></i> Salary Template</a>
                            @endcan
                            @can('view salary')
                            <a href="{{ route('role.employee-salaries.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.employee-salaries.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Salary Manage</a>
                            @endcan
                            @can('view loan')
                            <a href="{{ route('role.loans.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.loans.index') ? 'active' : '' }}"><i class="fas fa-hand-holding-dollar text-xs w-3"></i> Loan Management</a>
                            @endcan
                            @can('view payslip')
                            <a href="{{ route('role.payslips.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.payslips.index') ? 'active' : '' }}"><i class="fas fa-file-lines text-xs w-3"></i> Payslip</a>
                            @endcan
                            @can('view advance salary')
                            <a href="{{ route('role.advance-salaries.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.advance-salaries.index') ? 'active' : '' }}"><i class="fas fa-sack-dollar text-xs w-3"></i> Advance Salary</a>
                            @endcan
                        </div>
                    </div>
                    @endcanany
                @endcanany
                --}}
            </div>
        </div>
        @elseif($c->id == 6)
        {{-- ── WOOD ART INTERIORS ──────────────────────────────────────────────
             Menu mirrored from the interior suite's own registry,
             `platform/core/config.js` → WOODART_MODULES (19 modules). That file
             is the source of truth, NOT companies/woodart/module.json, which is
             a declarative copy and is out of date (it omits `payroll`).

             In the source, `sectionEnd:true` on an item draws a divider after
             it. Those four flags (crm, clients, procurement, analytics) cut the
             list into the five bands named in companies/woodart/ROOT-MAP.md,
             which is what the labels below are. Labels rather than bare rules,
             because that is how the Travels menu above already groups itself.

             Items carry `data-wa-module` / `data-wa-sub` and deliberately have
             NO href: the interior screens are not mounted in this ERP yet, so
             there is nothing to route to. Those attributes are the join key for
             whenever the pages do land. Note that a placeholder `href="#"` would
             NOT be inert here — the `[data-company-id]` stamper in
             app.blade.php rewrites every `a[href]` in this panel to add
             `?company=`, which would turn each click into a page reload. --}}
        @php
            $waBands = [
                'NAVIGATE' => [
                    ['dashboard', 'Dashboard',   'speedometer2',       []],
                    ['crm',       'Leads & CRM', 'person-lines-fill',  []],
                ],
                'SELL & DESIGN' => [
                    ['projects',  'Projects',        'easel2-fill',     [['active','Active Projects'],['design','Design Studio'],['milestones','Milestones'],['gallery','Gallery']]],
                    ['scope',     'Spaces & Phases', 'diagram-3-fill',  [['spaces','Spaces'],['phases','Phase Board'],['materials','Material Demand'],['load','Team Load']]],
                    ['design',    'Design & 3D',     'vector-pen',      [['register','Drawing Register'],['approvals','Approvals'],['load','Design Load']]],
                    ['estimates', 'Estimates & BOQ', 'calculator-fill', [['quotations','Quotations'],['boq','Bill of Materials'],['costing','Costing']]],
                    ['clients',   'Clients',         'person-hearts',   [['directory','Directory'],['portfolio','Portfolio'],['segments','Segments']]],
                ],
                'MAKE & DELIVER' => [
                    ['materials',    'Materials',      'boxes',     [['stock','Stock'],['movements','Movements'],['reorder','Reorder'],['valuation','Valuation']]],
                    ['production',   'Workshop',       'hammer',    [['jobs','Job Register'],['board','Workshop Board'],['load','Station Load']]],
                    ['installation', 'Site & Install', 'truck',     [['schedule','Schedule'],['snags','Snag List'],['teams','Teams']]],
                    ['procurement',  'Procurement',    'cart-fill', [['orders','Purchase Orders'],['vendors','Vendors'],['spend','Spend']]],
                ],
                'MONEY & REPORTING' => [
                    ['accounts',  'Accounts',  'cash-stack', [['overview','Overview'],['income','Income'],['expenses','Expenses'],['payables','Payables'],['pnl','Project P&L'],['payroll','Payroll'],['recurring','Recurring'],['banks','Banks'],['cash','Manage Cash'],['journals','Journals'],['schedules','Schedules']]],
                    ['payroll',   'Payroll',   'cash-coin',              []],
                    ['ledgers',   'Ledgers',   'journal-text',           []],
                    ['hrm',       'HRM',       'people-fill',            []],
                    ['reports',   'Reports',   'file-earmark-bar-graph', []],
                    ['analytics', 'Analytics', 'graph-up',               []],
                ],
                'ADMIN' => [
                    ['tasks',    'My Tasks', 'kanban',    []],
                    ['settings', 'Settings', 'gear-fill', []],
                ],
            ];
        @endphp
        @php
            // The modules actually MOUNTED in this ERP — everything the sidebar
            // needs to wire one up: its route name and its default section (the
            // one the bare route serves, mirroring each module's registry).
            // Adding the next module here is the whole sidebar change.
            $waLiveModules = [
                'dashboard' => ['route' => 'role.woodart.dashboard', 'default' => 'overview'],
                'projects'  => ['route' => 'role.woodart.projects',  'default' => 'active'],
                'scope'     => ['route' => 'role.woodart.scope',     'default' => 'spaces'],
                'design'    => ['route' => 'role.woodart.design',    'default' => 'register'],
                'estimates' => ['route' => 'role.woodart.estimates', 'default' => 'quotations'],
                'clients'   => ['route' => 'role.woodart.clients',   'default' => 'directory'],
                'materials' => ['route' => 'role.woodart.materials', 'default' => 'stock'],
                'production' => ['route' => 'role.woodart.production', 'default' => 'jobs'],
                'installation' => ['route' => 'role.woodart.installation', 'default' => 'schedule'],
                'procurement' => ['route' => 'role.woodart.procurement', 'default' => 'orders'],
                'accounts'  => ['route' => 'role.woodart.accounts',  'default' => 'overview'],
            ];
        @endphp
        {{-- WOOD ART MENU CSS — inside the $c->id == 6 branch ON PURPOSE, not in
             the shared style block above: it ships only when this panel renders,
             so a user who cannot see company 6 receives zero Wood Art bytes
             (CLAUDE.md, "shared files are append-only, behind a condition").

             Values transcribed from the reference build, not guessed:
               platform/design-system/css/layout.css  → .nav-item / .nav-sub /
                                                        .nav-ico / .nav-divider
               platform/design-system/css/tokens.css  → [data-theme="light"]
                 --text #131a2c · --text-dim #454f68 · --text-mute #5a6379
                 --surface-2 #f6f8fd · --border-strong rgba(26,67,191,.22)
               config.js → woodart accent #6f9c1c
             Every selector is scoped to #company6Submenu / wa-* classes only the
             Wood Art panel emits. --}}
        <style>
            #sidebar #company6Submenu {
                --wa-accent: #6f9c1c;
                --wa-text: #131a2c;
                --wa-text-dim: #454f68;
                --wa-text-mute: #5a6379;
                --wa-surface-2: #f6f8fd;
                --wa-border-strong: rgba(26, 67, 191, .22);
            }

            /* .nav-item — the reference uses a BARE 22px glyph, not the ERP's
               boxed icon, and a noticeably larger label than the ERP menu. */
            #sidebar .wa-nav-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: clamp(7px, 1vh, 11px) 11px;
                border-radius: 10px;
                color: var(--wa-text-dim);
                font-weight: 500;
                font-size: clamp(14.5px, 1.75vh, 15.5px);
                line-height: 1.2;
                position: relative;
                width: 100%;
                text-align: left;
                background: none;
                border: 0;
                cursor: pointer;
                transition: all .14s ease;
            }

            #sidebar .wa-nav-item:hover {
                background: var(--wa-surface-2);
                color: var(--wa-text);
            }

            #sidebar .wa-nav-item.active {
                background: color-mix(in srgb, var(--wa-accent) 14%, transparent);
                color: var(--wa-text);
                font-weight: 600;
            }

            #sidebar .wa-nav-item.active .wa-nav-ico { color: var(--wa-accent); }

            #sidebar .wa-nav-item.active::before {
                content: '';
                position: absolute;
                left: 0; top: 8px; bottom: 8px;
                width: 3px;
                border-radius: 3px;
                background: var(--wa-accent);
            }

            #sidebar .wa-nav-ico {
                width: 22px;
                display: grid;
                place-items: center;
                color: var(--wa-text-mute);
                font-size: clamp(13px, 1.7vh, 15px);
                flex: none;
                transition: color .14s ease;
            }

            #sidebar .wa-nav-label { flex: 1; }

            /* Caret points RIGHT and rotates 90° when open (reference
               behaviour); the ERP menu's down-chevron flips 180° instead. */
            #sidebar .wa-nav-caret {
                color: var(--wa-text-mute);
                font-size: 11px;
                flex: none;
                width: 22px; height: 22px;
                margin: -4px -4px -4px 0;
                border-radius: 6px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: transform .24s cubic-bezier(.16,.84,.44,1), background .14s ease, color .14s ease;
            }

            #sidebar .wa-nav-caret.open { transform: rotate(90deg); }

            /* Group divider — the reference's `sectionEnd`: a hairline fading
               at both ends, NOT an uppercase caption. */
            #sidebar .wa-nav-divider {
                height: 1px;
                flex: none;
                margin: clamp(5px, 1vh, 10px) 12px;
                background: linear-gradient(90deg, transparent, var(--wa-border-strong), transparent);
                box-shadow: 0 1px 0 color-mix(in srgb, var(--wa-text) 5%, transparent);
            }

            #sidebar .wa-nav-subs-inner { padding-left: 22px; }

            #sidebar .wa-nav-sub {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: clamp(5px, 0.7vh, 8px) 11px;
                border-radius: 7px;
                color: var(--wa-text-mute);
                font-size: clamp(13.5px, 1.6vh, 14.5px);
                line-height: 1.2;
                transition: all .14s ease;
            }

            #sidebar .wa-nav-sub:hover {
                color: var(--wa-text);
                background: var(--wa-surface-2);
            }

            #sidebar .wa-nav-sub.active {
                color: var(--wa-accent);
                font-weight: 600;
            }

            #sidebar .wa-sub-dot {
                width: 5px;
                height: 5px;
                border-radius: 50%;
                background: currentColor;
                opacity: .55;
                flex: none;
            }
        </style>
        <div class="sidebar-item"
             x-show="!search || '{{ strtolower($c->name) }}'.includes(search.toLowerCase()) || 'wood art interiors'.includes(search.toLowerCase())">
            {{-- Same reasoning as the Travels panel: NOT tagged ".submenu", so
                 toggleSubmenu() cannot close it as a sibling. --}}
            <div id="company6Submenu" data-company-id="{{ $c->id }}"
                 class="space-y-0.5" style="margin-left:0;padding-left:0;border-left:none;">

                <a href="{{ route('role.company.dashboard', ['role' => $role, 'company' => $c->id]) }}"
                   class="submenu-item {{ $isActive ? 'active' : '' }}">
                    <div class="sidebar-icon-box"><i class="fas fa-building"></i></div>
                    <span>Company Dashboard</span>
                </a>

                @foreach($waBands as $waBandLabel => $waModules)
                {{-- The reference prints NO band captions. Its four `sectionEnd`
                     flags (crm · clients · procurement · analytics) render as
                     hairline dividers only. Those flags fall exactly on the last
                     item of each band below, so emitting a rule after every band
                     but the last reproduces the reference precisely. The uppercase
                     captions that used to sit here were an ERP convention the
                     reference does not share. --}}

                    @foreach($waModules as [$waKey, $waLabel, $waIcon, $waSubs])
                    @php
                        // Match on the module name *and* its sub-item names, so
                        // searching "snag" surfaces Site & Install.
                        $waTerms = strtolower($waLabel . ' ' . collect($waSubs)->pluck(1)->implode(' '));
                        $waId    = 'wa' . ucfirst($waKey) . 'Submenu';
                    @endphp

                    @if(empty($waSubs))
                    @php
                        // A module with no sub-items still LINKS once it is
                        // mounted — Dashboard is the first row in this menu and
                        // was rendering as dead text. Same lookup and same
                        // CLAUDE.md rule 6 guard as the sub-item branch below:
                        // if the route is not registered (half-deployed server,
                        // stale route cache), degrade to the inert row rather
                        // than throwing and taking every company's page down.
                        $waFlatDef = $waLiveModules[$waKey] ?? null;

                        if ($waFlatDef && ! \Illuminate\Support\Facades\Route::has($waFlatDef['route'])) {
                            $waFlatDef = null;
                        }
                    @endphp
                    <a class="wa-nav-item {{ $waFlatDef && request()->routeIs($waFlatDef['route']) ? 'active' : '' }}"
                       data-wa-module="{{ $waKey }}"
                       @if($waFlatDef) href="{{ route($waFlatDef['route'], ['role' => $role, 'section' => $waFlatDef['default']]) }}" @endif
                       x-show="!search || @js($waTerms).includes(search.toLowerCase())">
                        <span class="wa-nav-ico"><i class="bi bi-{{ $waIcon }}"></i></span>
                        <span class="wa-nav-label">{{ $waLabel }}</span>
                    </a>
                    @else
                    @php
                        // Mounted modules render their own screens, so their top row
                        // is a LINK to the module's default section — clicking
                        // "Projects" opens Active Projects, exactly as it does in the
                        // reference build. Unmounted modules keep the plain
                        // toggle-only button.
                        $waLiveDef = $waLiveModules[$waKey] ?? null;

                        // CLAUDE.md rule 6 — this branch runs for superadmin, so a
                        // throw here takes down EVERY company's page, not just Wood
                        // Art. That is the 2026-08-10 outage: the WoodArt provider
                        // was dropped silently (composer autoload had no Modules\
                        // namespace), route() below threw RouteNotFoundException
                        // while rendering this shared sidebar, and superadmin 500'd.
                        // If the routes are not registered, degrade to the inert
                        // toggle rendering instead of erroring.
                        if ($waLiveDef && ! \Illuminate\Support\Facades\Route::has($waLiveDef['route'])) {
                            $waLiveDef = null;
                        }

                        $waHere    = $waLiveDef && request()->routeIs($waLiveDef['route']);
                    @endphp
                    <div x-show="!search || @js($waTerms).includes(search.toLowerCase())">
                        @if($waLiveDef)
                        {{-- No toggleSubmenu() call: the click navigates, and the
                             submenu is then rendered already-open server-side by the
                             $waHere check below, so the section stays expanded. --}}
                        <a href="{{ route($waLiveDef['route'], ['role' => $role, 'section' => $waLiveDef['default']]) }}"
                           class="wa-nav-item {{ $waHere ? 'active' : '' }}">
                            <span class="wa-nav-ico"><i class="bi bi-{{ $waIcon }}"></i></span>
                            <span class="wa-nav-label">{{ $waLabel }}</span>
                            <i class="bi bi-chevron-right wa-nav-caret {{ $waHere ? 'open' : '' }}"></i>
                        </a>
                        @else
                        <button type="button" onclick="toggleSubmenu('{{ $waId }}', this)"
                                class="wa-nav-item">
                            <span class="wa-nav-ico"><i class="bi bi-{{ $waIcon }}"></i></span>
                            <span class="wa-nav-label">{{ $waLabel }}</span>
                            <i class="bi bi-chevron-right wa-nav-caret"></i>
                        </button>
                        @endif
                        {{-- Keeps .submenu so the host's toggleSubmenu() still drives
                             open/close; wa-nav-subs-inner supplies the reference's
                             22px indent. Open while you are inside the module, the
                             same way the Travels menu opens its active section. --}}
                        <div id="{{ $waId }}" class="submenu wa-nav-subs-inner {{ $waHere ? '' : 'hidden' }}">
                            @foreach($waSubs as [$waSubKey, $waSubLabel])
                            @if($waLiveDef)
                            <a href="{{ route($waLiveDef['route'], ['role' => $role, 'section' => $waSubKey]) }}"
                               class="wa-nav-sub {{ $waHere && request()->route('section', $waLiveDef['default']) === $waSubKey ? 'active' : '' }}"
                               data-wa-module="{{ $waKey }}" data-wa-sub="{{ $waSubKey }}">
                                <span class="wa-sub-dot"></span><span>{{ $waSubLabel }}</span>
                            </a>
                            @else
                            {{-- Not mounted yet: inert, but carrying the join key. --}}
                            <a class="wa-nav-sub" data-wa-module="{{ $waKey }}" data-wa-sub="{{ $waSubKey }}">
                                <span class="wa-sub-dot"></span><span>{{ $waSubLabel }}</span>
                            </a>
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @endforeach
                    {{-- sectionEnd → hairline, on every band but the last. --}}
                    @unless($loop->last)<div class="wa-nav-divider"></div>@endunless
                @endforeach
            </div>
            <script>
            (function () {
                // Wood Art menu behaviour. Everything here is scoped to
                // #company6Submenu — no other company's menu is read or written.

                // The host's toggleSubmenu() rotates an `i.fa-chevron-down`. The
                // Wood Art menu uses the reference's right-caret (rotate 90° when
                // open) instead, so drive it from the submenu's own state.
                function syncWaCarets() {
                    document.querySelectorAll('#company6Submenu .wa-nav-item').forEach(function (row) {
                        var caret = row.querySelector('.wa-nav-caret');
                        var subs  = row.nextElementSibling;
                        if (!caret || !subs || !subs.classList.contains('submenu')) return;
                        caret.classList.toggle('open', !subs.classList.contains('hidden'));
                    });
                }

                // A LIVE module's top row is a link: from elsewhere, clicking it
                // navigates (and the submenu arrives open). But while you are
                // ALREADY inside that module the row is marked .active, and a
                // second click used to re-navigate to the page you were on —
                // so it never collapsed. Inside the module, the row is a pure
                // toggle instead. This script runs at parse time, before the
                // deferred woodart-nav.js, so its preventDefault() also stops
                // the nav script from claiming the click (it checks
                // e.defaultPrevented).
                document.addEventListener('click', function (e) {
                    var row = e.target.closest && e.target.closest('#company6Submenu a.wa-nav-item[href]');
                    if (row && row.classList.contains('active')) {
                        var subs = row.nextElementSibling;
                        if (subs && subs.classList.contains('submenu')) {
                            e.preventDefault();
                            subs.classList.toggle('hidden');
                        }
                    }
                    if (e.target.closest && e.target.closest('#company6Submenu')) {
                        setTimeout(syncWaCarets, 0);
                    }
                });

                // Cross-module swaps: woodart-nav.js replaces only the view
                // region, so the sidebar never re-renders. Its own syncMenu()
                // repaints the SUB links; the top rows and which accordion is
                // open are this menu's job. One module open at a time — the
                // same state a full server render produces.
                document.addEventListener('wa:navigated', function () {
                    var parts  = location.pathname.split('/').filter(Boolean);
                    var at     = parts.indexOf('woodart');
                    var module = at >= 0 ? parts[at + 1] : null;
                    document.querySelectorAll('#company6Submenu a.wa-nav-item[href]').forEach(function (row) {
                        var subs = row.nextElementSibling;
                        if (!subs || !subs.classList.contains('submenu')) return;
                        var key  = subs.querySelector('[data-wa-module]');
                        var mine = !!(module && key && key.getAttribute('data-wa-module') === module);
                        row.classList.toggle('active', mine);
                        subs.classList.toggle('hidden', !mine);
                    });
                    syncWaCarets();
                });

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', syncWaCarets);
                } else {
                    syncWaCarets();
                }
            })();
            </script>
        </div>
        @else
        @php
            $companyLogoUrl = null;
            if (!empty($c->icon)) {
                $companyLogoUrl = \Illuminate\Support\Str::startsWith($c->icon, ['http://', 'https://'])
                    ? $c->icon
                    : asset($c->icon);
            }
        @endphp
        <a href="{{ route('role.company.dashboard', ['role' => $role, 'company' => $c->id]) }}"
           x-show="!search || '{{ strtolower($c->name) }}'.includes(search.toLowerCase())"
           class="sidebar-item flex items-center gap-2.5 {{ $isActive ? 'active' : '' }}">
            @if($companyLogoUrl)
            <img src="{{ $companyLogoUrl }}" alt="{{ $c->name }}" class="rounded-md" style="width:25px;height:25px;object-fit:cover;flex-shrink:0;" />
            @else
            <span style="width:25px;height:25px;border-radius:5px;background:{{ $color }};display:inline-flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#fff;flex-shrink:0;">{{ $initials }}</span>
            @endif
            <span class="truncate">{{ $c->short_name ?? $c->name }}</span>
            @if($isActive)
            <span style="margin-left:auto;background:#2563eb;color:#fff;font-size:9px;padding:1px 5px;border-radius:4px;font-weight:700;">ACTIVE</span>
            @endif
        </a>
        @endif
        </div>
        @endforeach
        @endif

        {{-- BUSINESS --}}
        @canany(['view users','view product','view purchase','view sales','view stock transfer','view stock movement','view return reference'])
        <div class="sidebar-section-label" x-show="!search">Business</div>

        @can('view users')
        <div class="sidebar-item"
             x-show="!search || 'user management users employees documents promotions'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('userSubmenu', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ request()->routeIs('role.user.*','role.promotions.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-user-friends"></i></div>
                    <span class="text-sm font-medium">User Management</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="userSubmenu"
                 class="submenu space-y-0.5 {{ request()->routeIs('role.user.create','role.user.index','role.user.documents','role.user.summary','role.promotions.*') ? '' : 'hidden' }}">
                @can('create users')
                <a href="{{ route('role.user.create', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.user.create') ? 'active' : '' }}"><i class="fas fa-plus text-xs w-3"></i> Add User</a>
                @endcan
                @can('view users')
                @php
                    // How far this viewer can actually see. Mirrors
                    // UserController::applyUserScope() — if the two disagree the
                    // menu offers pages that come back empty.
                    //
                    //   all     → every user, every company   (super admin, admin)
                    //   company → own company only            (hr, Operation)
                    //   self    → own record only             (employee)
                    $viewer    = auth()->user();
                    $userScope = $viewer?->can('view all users')
                        ? 'all'
                        : ($viewer?->can('view company users') ? 'company' : 'self');
                @endphp

                @if($userScope === 'self')
                    {{-- Every role tab would list this one person and Employee
                         Documents would hold only their own file, so offer the
                         single page that is genuinely theirs instead of eleven
                         that are not. summary() serves employees only, hence the
                         role check — a self-scoped non-employee gets nothing
                         rather than a link that redirects straight back. --}}
                    @if($viewer && $viewer->hasRole('employee'))
                    <a href="{{ route('role.user.summary', ['role' => $role, 'user' => $viewer->id]) }}"
                       class="submenu-item {{ request()->routeIs('role.user.summary') ? 'active' : '' }}">
                        <i class="fas fa-id-badge text-xs w-3"></i> My Profile
                    </a>
                    @endif
                @else
                    @php
                        $roles = \Spatie\Permission\Models\Role::whereNotIn('name', ['customer','supplier'])->get();

                        // EnsurePanelRoleIsPermitted refuses an admin prefix to
                        // anyone who is not an administrator, so drop the tabs
                        // that would now 403 rather than leaving dead links in
                        // the menu. `all` keeps the full list exactly as before.
                        if ($userScope !== 'all') {
                            $ownRoleSlugs = $viewer->getRoleNames()
                                ->map(fn ($r) => \Illuminate\Support\Str::slug($r));

                            $roles = $roles->reject(function ($r) use ($ownRoleSlugs) {
                                $slug = \Illuminate\Support\Str::slug($r->name);

                                return in_array($slug, ['super-admin', 'superadmin', 'admin'], true)
                                    && ! $ownRoleSlugs->contains($slug);
                            });
                        }
                    @endphp
                    @foreach ($roles as $role_name)
                    @php $roleNameSlug = \Illuminate\Support\Str::slug($role_name->name); @endphp
                    <a href="{{ route('role.user.index', ['role' => $roleNameSlug]) }}"
                       class="submenu-item {{ request()->route('role') == $roleNameSlug ? 'active' : '' }}">
                        <i class="fas fa-user text-xs w-3"></i> {{ ucfirst($role_name->name) }} Users
                    </a>
                    @endforeach
                    <a href="{{ route('role.user.documents', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.user.documents') ? 'active' : '' }}"><i class="fas fa-id-card text-xs w-3"></i> Employee Documents</a>
                @endif
                @endcan
                <a href="{{ route('role.promotions.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.promotions.index') ? 'active' : '' }}"><i class="fas fa-trophy text-xs w-3"></i> User Promotions</a>
            </div>
        </div>
        @endcan

        @canany(['view product','view purchase','view sales','view stock transfer','view stock movement','view return reference'])
        <div class="sidebar-item"
             x-show="!search || 'business operations products inventory sales purchase stock customers suppliers'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('businessOpsSubmenu', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ request()->routeIs('role.units.*','role.brands.*','role.categories.*','role.sub-categories.*','role.products.*','role.sales.*','role.purchases.*','role.stock-transfers.*','role.stock-movements.*','role.return-refs.*','role.customers.*','role.suppliers.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-building"></i></div>
                    <span class="text-sm font-medium">Business Operations</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="businessOpsSubmenu"
                 class="submenu space-y-0.5 {{ request()->routeIs('role.units.*','role.brands.*','role.categories.*','role.sub-categories.*','role.products.*','role.sales.*','role.purchases.*','role.stock-transfers.*','role.stock-movements.*','role.return-refs.*','role.customers.*','role.suppliers.*') ? '' : 'hidden' }}">
                @can('view product')
                <div>
                    <button onclick="toggleSubmenu('productsSubmenu', this)"
                        class="submenu-group-btn {{ request()->routeIs('role.units.*','role.brands.*','role.categories.*','role.sub-categories.*','role.products.*') ? 'is-active' : '' }}">
                        <div class="flex items-center gap-2"><i class="fas fa-box text-xs w-4 text-center"></i><span>Products</span></div>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                    </button>
                    <div id="productsSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.units.*','role.brands.*','role.categories.*','role.sub-categories.*','role.products.*') ? '' : 'hidden' }}">
                        @can('view unit')
                        <a href="{{ route('role.units.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.units.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Unit</a>
                        @endcan
                        @can('view brand')
                        <a href="{{ route('role.brands.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.brands.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Brand</a>
                        @endcan
                        @can('view category')
                        <a href="{{ route('role.categories.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.categories.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Category</a>
                        @endcan
                        @can('view subcategory')
                        <a href="{{ route('role.sub-categories.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.sub-categories.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Sub Category</a>
                        @endcan
                        @can('view product')
                        <a href="{{ route('role.products.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.products.index') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> Product</a>
                        @endcan
                    </div>
                </div>
                @endcan

                @canany(['view purchase','view sales','view stock transfer','view stock movement','view return reference'])
                <div>
                    <button onclick="toggleSubmenu('inventorySubmenu', this)"
                        class="submenu-group-btn {{ request()->routeIs('role.sales.*','role.purchases.*','role.stock-transfers.*','role.stock-movements.*','role.return-refs.*','role.customers.*','role.suppliers.*') ? 'is-active' : '' }}">
                        <div class="flex items-center gap-2"><i class="fas fa-boxes-stacked text-xs w-4 text-center"></i><span>Inventory</span></div>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                    </button>
                    <div id="inventorySubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.sales.*','role.purchases.*','role.stock-transfers.*','role.stock-movements.*','role.return-refs.*','role.customers.*','role.suppliers.*') ? '' : 'hidden' }}">
                        @can('view customers')
                        <a href="{{ route('role.customers.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.customers.index') ? 'active' : '' }}"><i class="fas fa-user-group text-xs w-3"></i> Customers</a>
                        @endcan
                        @can('view suppliers')
                        <a href="{{ route('role.suppliers.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.suppliers.index') ? 'active' : '' }}"><i class="fas fa-truck text-xs w-3"></i> Suppliers</a>
                        @endcan
                        @can('view sales')
                        <a href="{{ route('role.sales.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.sales.*') ? 'active' : '' }}"><i class="fas fa-chart-pie text-xs w-3"></i> Sale</a>
                        @endcan
                        @can('view purchases')
                        <a href="{{ route('role.purchases.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.purchases.*') ? 'active' : '' }}"><i class="fas fa-dollar-sign text-xs w-3"></i> Purchase</a>
                        @endcan
                        @can('view stock transfer')
                        <a href="{{ route('role.stock-transfers.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.stock-transfers.*') ? 'active' : '' }}"><i class="fas fa-exchange-alt text-xs w-3"></i> Stock Transfer</a>
                        @endcan
                        @can('view stock movement')
                        <a href="{{ route('role.stock-movements.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.stock-movements.*') ? 'active' : '' }}"><i class="fas fa-warehouse text-xs w-3"></i> Stock Adjustment</a>
                        @endcan
                        @can('view return reference')
                        <a href="{{ route('role.return-refs.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.return-refs.*') ? 'active' : '' }}"><i class="fas fa-undo text-xs w-3"></i> Return Reference</a>
                        @endcan
                    </div>
                </div>
                @endcanany
            </div>
        </div>
        @endcanany
        @endcanany

        {{-- FINANCE & HR — hidden here for Travel users, who get this same content consolidated inside their own company submenu above instead. --}}
        @canany(['view expense','view bank','view department','view designation','view attendance','view attendance settings','view shift','view holidays','view leave','view leaves','view attendances','view resignation','view salary template','view salary','view loan','view payslip','view advance salary','view commission'])
        <div class="sidebar-section-label" x-show="!search">Finance & HR</div>

        @canany(['view expense','view bank','view general ledger report','view trial balance report','view profit loss report','view balance sheet report','view account ledger report','view account statement report','view journal entry report','view account balance report'])
        <div class="sidebar-item"
             x-show="!search || 'accounts finance expenses banks journals ledger transfers schedules payment'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('accountsMenu', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ request()->routeIs('role.expense*','role.petty-cash.*','role.accounts.*','role.banks.*','role.bank_transfers.*','role.journals.*','role.payment-schedules.*','role.report.general-ledger','role.report.trial-balance','role.report.profit-loss','role.report.balance-sheet','role.report.account-ledger','role.report.account-statement','role.report.journal-entries','role.report.account-balances') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-coins"></i></div>
                    <span class="text-sm font-medium">Accounts</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="accountsMenu"
                 class="submenu space-y-0.5 {{ request()->routeIs('role.expense*','role.petty-cash.*','role.accounts.*','role.banks.*','role.bank_transfers.*','role.journals.*','role.payment-schedules.*','role.report.general-ledger','role.report.trial-balance','role.report.profit-loss','role.report.balance-sheet','role.report.account-ledger','role.report.account-statement','role.report.journal-entries','role.report.account-balances') ? '' : 'hidden' }}">
                @can('view expense')
                {{-- One link, not a dropdown: the seven expense pages are reached from
                     the tab bar on the pages themselves (layout/expense-tabs). --}}
                <a href="{{ route('role.expenses.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.expense*','role.petty-cash.*') ? 'active' : '' }}"><i class="fas fa-money-bill-wave text-xs w-3"></i> Expenses</a>
                @endcan
                @canany(['view account','view all account'])
                <a href="{{ route('role.accounts.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.accounts.index') ? 'active' : '' }}"><i class="fas fa-user-circle text-xs w-3"></i> Manage Accounts</a>
                @endcanany
                @canany(['view journal','view all journal'])
                <a href="{{ route('role.journals.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.journals.index') ? 'active' : '' }}"><i class="fas fa-book text-xs w-3"></i> Manage Journals</a>
                @endcanany
                @can('view payment schedule')
                    <a href="{{ route('role.payment-schedules.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.payment-schedules.*') ? 'active' : '' }}"><i class="fas fa-calendar-alt text-xs w-3"></i> Payment Schedules</a>
                @endcan
                @canany(['view account','view all account'])
                <a href="{{ route('role.party-types.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.party-types.*') ? 'active' : '' }}"><i class="fas fa-tags text-xs w-3"></i> Party Types</a>
                @endcanany
                @can('view bank')
                {{-- One link, not a dropdown: the three bank pages are reached from
                     the tab bar on the pages themselves (layout/bank-tabs). --}}
                <a href="{{ route('role.banks.dashboard', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.banks.*','role.bank_transfers.*') ? 'active' : '' }}"><i class="fas fa-building-columns text-xs w-3"></i> Manage Banks</a>
                @endcan
                {{-- Capital & Financing — the loan book (lent out, borrowed, and the
                     payroll employee book mirrored read-only).

                     Route::has() before route(): this desk may be deployed before the
                     server's route cache is rebuilt, and an unresolvable route name
                     inside the SHARED sidebar would throw while rendering every page
                     for every company — the exact outage the isolation rule exists to
                     prevent. If the route is absent the link simply does not render. --}}
                @canany(['view financing', 'view all financing'])
                @if(\Illuminate\Support\Facades\Route::has('role.financing.index'))
                <a href="{{ route('role.financing.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.financing.*') ? 'active' : '' }}"><i class="fas fa-scale-balanced text-xs w-3"></i> Capital &amp; Financing</a>
                @endif
                @endcanany
                @canany(['view general ledger report', 'view trial balance report', 'view profit loss report', 'view balance sheet report', 'view account ledger report', 'view account statement report', 'view journal entry report', 'view account balance report'])
                <a href="{{ route('role.report.general-ledger', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.report.general-ledger','role.report.trial-balance','role.report.profit-loss','role.report.balance-sheet','role.report.account-ledger','role.report.account-statement','role.report.journal-entries','role.report.account-balances') ? 'active' : '' }}"><i class="fas fa-chart-pie text-xs w-3"></i> Account Reports</a>
                @endcanany
            </div>
        </div>
        @endcanany

        @php
            // Payroll now lives inside HRM as its own group, so both menus'
            // routes decide whether the HRM parent shows as open/active.
            $payrollRoutes = ['role.salary-templates.*','role.employee-salaries.*','role.loans.*','role.payslips.*','role.advance-salaries.*','role.commissions.*','role.report.payroll','role.report.payroll.*'];
            $hrmOwnRoutes  = ['role.departments.*','role.designations.*','role.holidays.*','role.shifts.*','role.leaves.*','role.attendances.*','role.leave-types.*','role.attendence-settings.*','role.resignations.*'];

            $payrollActive = request()->routeIs(...$payrollRoutes);
            $hrmActive     = $payrollActive || request()->routeIs(...$hrmOwnRoutes);
        @endphp

        @canany(['view department','view designation','view attendance','view attendance settings','view shift','view holidays','view leave','view leaves','view attendances','view resignation','view salary template','view salary','view loan','view payslip','view advance salary','view commission'])
        <div class="sidebar-item"
             x-show="!search || 'hrm human resource department designation attendance shift holiday leave resignation payroll salary loan payslip advance commission'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('hrmSubmenu', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ $hrmActive ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-users"></i></div>
                    <span class="text-sm font-medium">HRM</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="hrmSubmenu" class="submenu space-y-0.5 {{ $hrmActive ? '' : 'hidden' }}">

                {{-- Payroll — single entry; the six screens are reached from the in-page tab bar. --}}
                @canany(['view salary template','view salary','view loan','view payslip','view advance salary','view commission'])
                    @include('layout.payroll-menu-link')
                @endcanany

                @can('view department')
                <a href="{{ route('role.departments.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.departments.index') ? 'active' : '' }}"><i class="fas fa-building text-xs w-3"></i> Department</a>
                @endcan
                @can('view designation')
                <a href="{{ route('role.designations.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.designations.index') ? 'active' : '' }}"><i class="fas fa-id-badge text-xs w-3"></i> Designation</a>
                @endcan
                @can('view attendance settings')
                <a href="{{ route('role.attendence-settings.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.attendence-settings.index') ? 'active' : '' }}"><i class="fas fa-cog text-xs w-3"></i> Attendance Settings</a>
                @endcan
                @can('view shift')
                <a href="{{ route('role.shifts.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.shifts.index') ? 'active' : '' }}"><i class="fas fa-clock text-xs w-3"></i> Shift</a>
                @endcan
                @can('view holiday')
                <a href="{{ route('role.holidays.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.holidays.index') ? 'active' : '' }}"><i class="fas fa-calendar text-xs w-3"></i> Holidays</a>
                @endcan
                @can('view leave type')
                <a href="{{ route('role.leave-types.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.leave-types.index') ? 'active' : '' }}"><i class="fas fa-calendar-minus text-xs w-3"></i> Leave Types</a>
                @endcan
                @can('view leave')
                <a href="{{ route('role.leaves.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.leaves.index') ? 'active' : '' }}"><i class="fas fa-calendar-check text-xs w-3"></i> All Leaves</a>
                @endcan
                @can('view resignation')
                <a href="{{ route('role.resignations.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.resignations.index') ? 'active' : '' }}"><i class="fas fa-sign-out-alt text-xs w-3"></i> Resignations</a>
                @endcan
                @can('view attendance')
                <a href="{{ route('role.attendances.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.attendances.index') ? 'active' : '' }}"><i class="fas fa-fingerprint text-xs w-3"></i> All Attendances</a>
                @endcan
            </div>
        </div>
        @endcanany


        @endcanany

        {{-- CRM / TASK / REPORT --}}
        @canany(['view lead source','view lead manager','view lead followup','view lead reminder','view contract','view deal','view proposal','view estimate','view lead project category','view all lead project category','view lead project','view all lead project','view task'])
        <div class="sidebar-section-label" x-show="!search">Operations</div>

        @canany(['view lead source','view lead manager','view lead followup','view lead reminder','view contract','view deal','view proposal','view estimate'])
        <div class="sidebar-item"
             x-show="!search || 'crm lead project contract deal proposal estimate'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('crmSubmenu', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ request()->routeIs('role.lead-source.*','role.lead-followup.*','role.lead-reminders.*','role.lead-manager.*','role.project-categories.*','role.contract-types.*','role.deals.*','role.proposals.*','role.projects.*','role.contracts.*','role.estimates.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-handshake"></i></div>
                    <span class="text-sm font-medium">CRM</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="crmSubmenu"
                 class="submenu space-y-0.5 {{ request()->routeIs('role.lead-source.*','role.lead-followup.*','role.lead-reminders.*','role.lead-manager.*','role.project-categories.*','role.contract-types.*','role.deals.*','role.proposals.*','role.projects.*','role.contracts.*','role.estimates.*') ? '' : 'hidden' }}">
                @canany(['view lead source','view lead manager','view lead followup','view lead reminder'])
                @can('view deal')
                <a href="{{ route('role.crm.dashboard', ['role' => $role]) }}"
                                        class="submenu-item block p-1 text-blue-200 hover:text-white cursor-pointer {{ request()->routeIs('role.crm.dashboard') ? 'active' : '' }}">
                                        <i class="fa-solid fa-chart-pie"></i> CRM Dashboard
                                    </a>
                @endcan
                <div>
                    <button onclick="toggleSubmenu('crmLeadSubmenu', this)"
                        class="submenu-group-btn {{ request()->routeIs('role.lead-source.*','role.lead-manager.*','role.lead-followup.*','role.lead-reminders.*') ? 'is-active' : '' }}">
                        <div class="flex items-center gap-2"><i class="fas fa-lightbulb text-xs w-4 text-center"></i><span>Lead</span></div>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                    </button>
                    <div id="crmLeadSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.lead-source.*','role.lead-manager.*','role.lead-followup.*','role.lead-reminders.*') ? '' : 'hidden' }}">
                        @can('view lead source')
                        
                        <a href="{{ route('role.lead-source.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.lead-source.index') ? 'active' : '' }}"><i class="fas fa-bullseye text-xs w-3"></i> Lead Source</a>
                        @endcan
                        @can('view lead manager')
                        <a href="{{ route('role.lead-manager.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.lead-manager.index') ? 'active' : '' }}"><i class="fas fa-user-plus text-xs w-3"></i> Lead Manager</a>
                        @endcan
                        @can('view lead followup')
                        <a href="{{ route('role.lead-followup.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.lead-followup.index') ? 'active' : '' }}"><i class="fas fa-phone text-xs w-3"></i> Lead Followup</a>
                        @endcan
                        @can('view lead reminder')
                        <a href="{{ route('role.lead-reminders.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.lead-reminders.index') ? 'active' : '' }}"><i class="fas fa-bell text-xs w-3"></i> Lead Reminder</a>
                        @endcan
                    </div>
                </div>
                @endcanany

                {{-- @canany(['view project category','view project'])
                <div>
                    <button onclick="toggleSubmenu('crmProjectSubmenu', this)"
                        class="submenu-group-btn {{ request()->routeIs('role.project-categories.*','role.projects.*') ? 'is-active' : '' }}">
                        <div class="flex items-center gap-2"><i class="fas fa-briefcase text-xs w-4 text-center"></i><span>Project</span></div>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                    </button>
                    <div id="crmProjectSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.project-categories.*','role.projects.*') ? '' : 'hidden' }}">
                        @can('view project category')
                        <a href="{{ route('role.project-categories.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.project-categories.index') ? 'active' : '' }}"><i class="fas fa-folder-tree text-xs w-3"></i> Project Category</a>
                        @endcan
                        @can('view project')
                        <a href="{{ route('role.projects.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.projects.index') ? 'active' : '' }}"><i class="fas fa-diagram-project text-xs w-3"></i> Projects</a>
                        @endcan
                    </div>
                </div>
                @endcanany --}}

                @can('view contract')
                <a href="{{ route('role.contract-types.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.contract-types.index') ? 'active' : '' }}"><i class="fas fa-file-signature text-xs w-3"></i> Contract Type</a>
                <a href="{{ route('role.contracts.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.contracts.index') ? 'active' : '' }}"><i class="fas fa-file-contract text-xs w-3"></i> Contracts</a>
                @endcan
                @can('view deal')
                <a href="{{ route('role.deals.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.deals.index') ? 'active' : '' }}"><i class="fas fa-handshake text-xs w-3"></i> Deal Manager</a>
                @endcan
                @can('view proposal')
                <a href="{{ route('role.proposals.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.proposals.index') ? 'active' : '' }}"><i class="fas fa-file-contract text-xs w-3"></i> Proposal Manager</a>
                @endcan
                @can('view estimate')
                <a href="{{ route('role.estimates.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.estimates.index') ? 'active' : '' }}"><i class="fas fa-calculator text-xs w-3"></i> Estimate</a>
                @endcan
            </div>
        </div>
        @endcanany

        @canany(['view lead project category','view all lead project category','view lead project','view all lead project'])
        <div class="sidebar-item"
             x-show="!search || 'project category department type'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('projectMainSubmenu', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ request()->routeIs('role.project-categories.*','role.projects.*','role.require-types.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-diagram-project"></i></div>
                    <span class="text-sm font-medium">Project</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="projectMainSubmenu"
                 class="submenu space-y-0.5 {{ request()->routeIs('role.project-categories.*','role.projects.*') ? '' : 'hidden' }}">
                @canany(['view lead project category','view all lead project category'])
                <a href="{{ route('role.project-categories.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.project-categories.index') ? 'active' : '' }}"><i class="fas fa-folder-tree text-xs w-3"></i> Project Category</a>
                @endcanany
                @canany(['view lead project','view all lead project'])
                <a href="{{ route('role.projects.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.projects.index') ? 'active' : '' }}"><i class="fas fa-briefcase text-xs w-3"></i> Projects</a>
                
                @endcanany
            </div>
        </div>
        @endcanany

        @canany(['view employee request','verify require assignment','escalate require assignment'])
        <div class="sidebar-item"
             x-show="!search || 'employee request require assignment report'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('empRequestSubmenu', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ request()->routeIs('role.employee-requests.*','role.require-assignments.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-inbox"></i></div>
                    <span class="text-sm font-medium">Require</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="empRequestSubmenu"
                 class="submenu space-y-0.5 {{ request()->routeIs('role.employee-requests.*','role.require-assignments.*','role.require-types.*') ? '' : 'hidden' }}">
                @can('view employee request')
                <a href="{{ route('role.require-types.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.require-types.*') ? 'active' : '' }}"><i class="fas fa-layer-group text-xs w-3"></i> Require Type</a>
                <a href="{{ route('role.employee-requests.index', ['role' => $role]) }}"
                   class="submenu-item {{ request()->routeIs('role.employee-requests.index') ? 'active' : '' }}">
                    <i class="fas fa-list text-xs w-3"></i> All Require
                </a>
                <a href="{{ route('role.employee-requests.self-service', ['role' => $role]) }}"
                   class="submenu-item {{ request()->routeIs('role.employee-requests.self-service') ? 'active' : '' }}">
                    <i class="fas fa-user text-xs w-3"></i> My Require
                </a>
                <a href="{{ route('role.employee-requests.report', ['role' => $role]) }}"
                   class="submenu-item {{ request()->routeIs('role.employee-requests.report') ? 'active' : '' }}">
                    <i class="fas fa-chart-bar text-xs w-3"></i> Report
                </a>
                {{-- <a href="{{ route('role.require-assignments.overdue', ['role' => $role]) }}"
                   class="submenu-item {{ request()->routeIs('role.require-assignments.overdue') ? 'active' : '' }}">
                    <i class="fas fa-exclamation-triangle text-xs w-3"></i> Overdue
                </a> --}}
                @endcan
                {{-- @canany(['verify require assignment','escalate require assignment']) --}}
                
                {{-- @endcanany --}}
            </div>
        </div>
        @endcanany

        @canany(['view task','view task project','view all task project'])
        <div class="sidebar-item"
             x-show="!search || 'task workspace board column label project category'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('taskSubmenu', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ request()->routeIs('role.workspace-users.*','role.boards.*','role.columns.*','role.labels.*','role.tasks.*','role.project-categories.*','role.projects.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-list-check"></i></div>
                    <span class="text-sm font-medium">Task</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="taskSubmenu"
                 class="submenu space-y-0.5 {{ request()->routeIs('role.workspace-users.*','role.boards.*','role.columns.*','role.labels.*','role.tasks.*','role.project-categories.*','role.projects.*') ? '' : 'hidden' }}">
                @can('view workspace')
                <a href="{{ route('role.workspace-users.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.workspace-users.index') ? 'active' : '' }}"><i class="fas fa-people-group text-xs w-3"></i> Workspace</a>
                @endcan
                @can('view board')
                <a href="{{ route('role.boards.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.boards.index') ? 'active' : '' }}"><i class="fas fa-table-columns text-xs w-3"></i> Board</a>
                <a href="{{ route('role.columns.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.columns.index') ? 'active' : '' }}"><i class="fas fa-bars-staggered text-xs w-3"></i> Column</a>
                @endcan
                @can('view label')
                <a href="{{ route('role.labels.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.labels.index') ? 'active' : '' }}"><i class="fas fa-tag text-xs w-3"></i> Label</a>
                @endcan
                @can('view task')
                <a href="{{ route('role.tasks.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.tasks.index','role.tasks.board') ? 'active' : '' }}"><i class="fas fa-tasks text-xs w-3"></i> My Tasks</a>
                @endcan
                @canany(['view task project','view all task project'])
                <div>
                    <button onclick="toggleSubmenu('taskProjectSubmenu', this)"
                        class="submenu-group-btn {{ request()->routeIs('role.task-projects.*') ? 'is-active' : '' }}">
                        <div class="flex items-center gap-2"><i class="fas fa-diagram-project text-xs w-4 text-center"></i><span>Project</span></div>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                    </button>
                    <div id="taskProjectSubmenu" class="submenu space-y-0.5 {{ request()->routeIs('role.task-projects.*') ? '' : 'hidden' }}">
                        <a href="{{ route('role.task-projects.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.task-projects.index') ? 'active' : '' }}"><i class="fas fa-briefcase text-xs w-3"></i> Task Projects</a>
                    </div>
                </div>
                @endcanany
            </div>
        </div>
        @endcanany

        @canany(['view monthly attendance report','view task report','view general ledger report','view trial balance report','view profit loss report','view balance sheet report','view account ledger report','view account statement report','view journal entry report','view account balance report'])
        <div class="sidebar-item"
             x-show="!search || 'report attendance ledger trial balance profit loss account statement journal'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('reportMenu', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ request()->routeIs('role.report.monthly-profit*','role.report.monthly-attendances','role.report.task.reports') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-chart-bar"></i></div>
                    <span class="text-sm font-medium">Report</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="reportMenu" class="submenu space-y-0.5 {{ request()->routeIs('role.report.monthly-profit*','role.report.monthly-attendances','role.report.task.reports') ? '' : 'hidden' }}">
                <a href="{{ route('role.report.monthly-profit', ['role' => $role]) }}"
                   class="submenu-item {{ request()->routeIs('role.report.monthly-profit*') ? 'active' : '' }}">
                    <i class="fas fa-chart-line text-xs w-3"></i> Monthly Profit Sheet
                </a>
                @can('view monthly attendance report')
                <a href="{{ route('role.report.monthly-attendances', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.report.monthly-attendances') ? 'active' : '' }}"><i class="fas fa-calendar-check text-xs w-3"></i> Monthly Attendance</a>
                @endcan
                @can('view task report')
                <a href="{{ route('role.report.task.reports', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.report.task.reports') ? 'active' : '' }}"><i class="fas fa-clipboard-list text-xs w-3"></i> Employee Task Report</a>
                @endcan
            </div>
        </div>
        @endcanany
        @endcanany

        {{-- COMMUNICATIONS --}}
        <div class="sidebar-section-label" x-show="!search">Communications</div>

        <div class="sidebar-item"
             x-show="!search || 'reminder support ticket notice notification'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('supportMenu', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ request()->routeIs('role.ticket-departments.*','role.support-tickets.*','role.notices.*','role.notifications.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-bell"></i></div>
                    <span class="text-sm font-medium">Reminder</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="supportMenu"
                 class="submenu space-y-0.5 {{ request()->routeIs('role.ticket-departments.*','role.support-tickets.*','role.notices.*','role.notifications.*') ? '' : 'hidden' }}">
                @can('view support ticket')
                <a href="{{ route('role.ticket-departments.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.ticket-departments.index') ? 'active' : '' }}"><i class="fas fa-ticket text-xs w-3"></i> Ticket Department</a>
                <a href="{{ route('role.support-tickets.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.support-tickets.index') ? 'active' : '' }}"><i class="fas fa-headset text-xs w-3"></i> Support Ticket</a>
                @endcan
                @can('view notice')
                <a href="{{ route('role.notices.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.notices.index') ? 'active' : '' }}"><i class="fas fa-envelope text-xs w-3"></i> Notices</a>
                @endcan
                <a href="{{ route('role.notifications.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.notifications.index') ? 'active' : '' }}"><i class="fas fa-bell text-xs w-3"></i> Notifications</a>
            </div>
        </div>

        {{-- OFFICE TODOS --}}
        @php
            $todoCount = \App\Models\OfficeTodoAssignee::where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'in_progress'])->count();
            if (auth()->user()->hasRole('super admin') || auth()->user()->hasRole('admin')) {
                $todoCount = \App\Models\OfficeTodo::whereIn('status', ['pending', 'in_progress'])->count();
            }
        @endphp
        <a href="{{ route('role.office-todos.index', ['role' => $role]) }}"
           x-show="!search || 'office todo task assign pending'.includes(search.toLowerCase())"
           class="sidebar-item {{ request()->routeIs('role.office-todos.*') ? 'active' : '' }}">
            <div class="sidebar-icon-box {{ request()->routeIs('role.office-todos.*') ? '!bg-purple-100 !text-purple-700' : '' }}">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <span class="text-sm font-medium">Office Todos</span>
            @if($todoCount > 0)
            <span class="sidebar-badge" style="background:#7c3aed;">{{ $todoCount }}</span>
            @endif
        </a>

        @can('view marketing')
        <div class="sidebar-item"
             x-show="!search || 'marketing email sms whatsapp'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('marketingMenu', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ request()->routeIs('role.email-marketing.*','role.sms-marketing.*','role.whatsapp-marketing.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-bullhorn"></i></div>
                    <span class="text-sm font-medium">Marketing</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="marketingMenu"
                 class="submenu space-y-0.5 {{ request()->routeIs('role.email-marketing.*','role.sms-marketing.*','role.whatsapp-marketing.*') ? '' : 'hidden' }}">
                <a href="{{ route('role.email-marketing.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.email-marketing.index') ? 'active' : '' }}"><i class="fas fa-envelope text-xs w-3"></i> Email Marketing</a>
                <a href="{{ route('role.sms-marketing.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.sms-marketing.index') ? 'active' : '' }}"><i class="fas fa-sms text-xs w-3"></i> SMS Marketing</a>
                <a href="{{ route('role.whatsapp-marketing.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.whatsapp-marketing.index') ? 'active' : '' }}"><i class="fab fa-whatsapp text-xs w-3"></i> WhatsApp Marketing</a>
            </div>
        </div>
        @endcan

        {{-- SETTINGS --}}
        @can('view company setting')
        <div class="sidebar-section-label" x-show="!search">Settings</div>

        <div class="sidebar-item"
             x-show="!search || 'settings site company sms template device invoice role permission'.includes(search.toLowerCase())">
            <button onclick="toggleSubmenu('setting', this)"
                class="sidebar-top-btn w-full flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="sidebar-icon-box {{ request()->routeIs('role.sms_templates.*','role.site-settings.*','role.company-settings.*','role.device-settings.*','role.invoice-templates.*','role.role-permission.*','role.demo-data.*') ? '!bg-blue-100 !text-blue-700' : '' }}"><i class="fas fa-cog"></i></div>
                    <span class="text-sm font-medium">Settings</span>
                </div>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="setting"
                 class="submenu space-y-0.5 {{ request()->routeIs('role.sms_templates.*','role.site-settings.*','role.company-settings.*','role.device-settings.*','role.invoice-templates.*','role.role-permission.*','role.demo-data.*') ? '' : 'hidden' }}">
                <a href="{{ route('role.sms_templates.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.sms_templates.*') ? 'active' : '' }}"><i class="fas fa-list text-xs w-3"></i> SMS Templates</a>
                <a href="{{ route('role.site-settings.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.site-settings.*') ? 'active' : '' }}"><i class="fas fa-sitemap text-xs w-3"></i> Site Settings</a>
                <a href="{{ route('role.company-settings.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.company-settings.*') ? 'active' : '' }}"><i class="fas fa-building text-xs w-3"></i> Company Settings</a>
                <a href="{{ route('role.device-settings.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.device-settings.*') ? 'active' : '' }}"><i class="fas fa-mobile-alt text-xs w-3"></i> Device Settings</a>
                <a href="{{ route('role.invoice-templates.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.invoice-templates.*') ? 'active' : '' }}"><i class="fas fa-file-invoice text-xs w-3"></i> Invoice Settings</a>
                <a href="{{ route('role.role-permission.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.role-permission.*') ? 'active' : '' }}"><i class="fas fa-shield-alt text-xs w-3"></i> Role Settings</a>
                <a href="{{ route('role.demo-data.index', ['role' => $role]) }}" class="submenu-item {{ request()->routeIs('role.demo-data.*') ? 'active' : '' }}"><i class="fas fa-trash-alt text-xs w-3"></i> Demo Data</a>
            </div>
        </div>
        @endcan

        <a href="{{ route('role.trash.index', ['role' => $role]) }}"
           x-show="!search || 'trash deleted'.includes(search.toLowerCase())"
           class="sidebar-item flex items-center gap-2.5 {{ request()->routeIs('role.trash.*') ? 'active' : '' }}">
            <div class="sidebar-icon-box" style="background:#fee2e2;color:#ef4444;"><i class="fas fa-trash-alt"></i></div>
            <span>Trash</span>
        </a>

        </div>
    </nav>

    {{-- ── User Card ── --}}
    <div class="shrink-0 p-3 border-t border-gray-100">
        <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3 border border-gray-200">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                 style="background:linear-gradient(135deg,#2563eb,#7c3aed);">
                {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-bold text-gray-900 truncate">{{ Auth::user()->name }}</div>
                <div class="text-xs text-gray-500 truncate">{{ ucfirst(Auth::user()->getRoleNames()->first()) }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <button type="submit" class="text-gray-400 hover:text-red-500 transition-colors p-1" title="Logout">
                    <i class="fas fa-sign-out-alt text-sm"></i>
                </button>
            </form>
        </div>
    </div>

</aside>
