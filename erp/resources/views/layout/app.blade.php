<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ Auth::id() }}">
    {{-- Role-prefixed root, built by Laravel so scripts keep working when the app
         is served from a subdirectory (…/epal_erp_soft/public/super-admin). --}}
    @auth
        <meta name="role-base-url" content="{{ url(\Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first())) }}">
    @endauth
    <link rel="shortcut icon" href="{{ site_setting_url('favicon', asset('airplane-ticket.png')) }}" type="image/x-icon">
    @hasSection('meta-information')
        @yield('meta-information')
    @else
        <title>{{ config('app.name') }}</title>
    @endif
    <!-- Tailwind CSS CDN -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/pages/board-show.js', 'resources/js/notifications.js'])
    @else
        {{-- <script src="https://cdn.tailwindcss.com"></script> --}}
    @endif
    <!-- Write CSS -->
    @yield('css')
    <!-- Chart.js CDN -->
    @yield('import-script')
    <!-- Open the icon CDN connections early. Both stylesheets below are render
         blocking, so the DNS + TLS handshake for each was happening on the
         critical path of every page load; this overlaps them instead. -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <!-- Bootstrap Icons — same version the Modular ERP reference build uses, so
         the company rail's glyphs are the identical artwork, not lookalikes. -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    {{-- Alpine, served locally instead of from //unpkg.com/alpinejs.

         This is the same library — Alpine 3.15.12, the exact build that
         unversioned unpkg URL was resolving to — so nothing about how any page
         behaves changes. What changes is when it arrives: the CDN copy needed a
         DNS lookup, TLS handshake and cross-origin fetch on EVERY page load
         before Alpine could boot, and until it booted the sidebar's x-show
         menus were unresolved. That gap is what still read as a flash after the
         page transitions went in. Locally it is a same-origin, already-warm
         request.

         Pinning is a bonus: the old URL tracked "latest v3", so an upstream
         release could change the sidebar's behaviour with no commit here.

         To update: re-download https://unpkg.com/alpinejs@3/dist/cdn.min.js
         over public/vendor/alpine/alpine.min.js. --}}
    <script src="{{ asset('vendor/alpine/alpine.min.js') }}" defer></script>
    <!-- Custom Style -->
    <style>
        /* ── PAGE TRANSITIONS — every company ────────────────────────────────
           Owner-approved 2026-08-10, and the one deliberate exception to the
           "shared files are append-only behind a guard" rule in CLAUDE.md. It
           is unguarded because the whole point is that it applies everywhere.

           What this does NOT do: it changes no page's HTML, CSS, JS or
           features, and every script still runs exactly when it did before.
           Each navigation is still an ordinary full page load. The browser
           simply cross-fades the outgoing document into the incoming one
           instead of painting a blank white frame between them — that blank
           frame is what read as the "blink".

           Chrome and Edge honour this. Firefox and Safari ignore it and behave
           exactly as they do today, so there is no regression anywhere.

           To undo: delete this block. Nothing else depends on it. */
        @view-transition { navigation: auto; }

        /* The shell is identical from page to page, so name its parts: the
           browser then holds them steady rather than fading them along with
           the content. This is what stops the sidebar and header flickering,
           and stops the sidebar's restored scroll position visibly jumping. */
        #sidebar { view-transition-name: erp-sidebar; }
        #mainContentWrapper > header.header { view-transition-name: erp-header; }

        /* Brisk — this is navigation, not choreography. */
        ::view-transition-old(root),
        ::view-transition-new(root) { animation-duration: .16s; }

        @media (prefers-reduced-motion: reduce) {
            ::view-transition-old(root),
            ::view-transition-new(root) { animation: none; }
        }

        .sidebar {
            scrollbar-width: thin;
        }

        .chevron-rotate {
            transform: rotate(180deg);
        }
        
        /* ── Header ── */
        .header {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 1px 16px rgba(0, 0, 0, 0.06);
            margin-bottom: 15px !important;
        }

        /* ── Notification badge ── */
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border-radius: 999px;
            min-width: 17px;
            height: 17px;
            padding: 0 4px;
            font-size: 9px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 1px 4px rgba(239,68,68,0.4);
            line-height: 1;
        }

        /* ── Notification dropdown animation ── */
        .notification-dropdown {
            transform-origin: top right;
        }
        .notification-dropdown:not(.hidden) {
            animation: dropdownReveal 0.18s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        @keyframes dropdownReveal {
            from { opacity: 0; transform: scale(0.95) translateY(-6px); }
            to   { opacity: 1; transform: scale(1)    translateY(0);    }
        }

        /* ── User dropdown ── */
        .user-dropdown {
            transform-origin: top right;
        }
        .user-dropdown:not(.hidden) {
            animation: dropdownReveal 0.18s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        /* ── Bell ring animation ── */
        @keyframes bellRing {
            0%,100% { transform: rotate(0deg); }
            15%      { transform: rotate(18deg); }
            30%      { transform: rotate(-16deg); }
            45%      { transform: rotate(12deg); }
            60%      { transform: rotate(-8deg); }
            75%      { transform: rotate(5deg); }
        }
        .bell-ring {
            animation: bellRing 0.9s ease both;
            transform-origin: top center;
            display: inline-block;
        }

        /* ── Toast enter animation ── */
        @keyframes toastEnter {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .toast-enter {
            animation: toastEnter 0.28s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        /* ── line-clamp utility (fallback) ── */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ── Dark Mode ── */
        html.dark body { background: #0f172a !important; color: #e2e8f0; }
        html.dark .header {
            background: rgba(15, 23, 42, 0.95) !important;
            border-bottom-color: rgba(51, 65, 85, 0.8) !important;
            box-shadow: 0 1px 16px rgba(0,0,0,0.3) !important;
        }
        html.dark #sidebar {
            background: #1e293b !important;
            border-right-color: #334155 !important;
        }
        html.dark #mainContentWrapper { background: #0f172a; }
        html.dark main { background: transparent; }
        html.dark .bg-white { background: #1e293b !important; }
        html.dark .bg-gray-50 { background: #0f172a !important; }
        html.dark .bg-gray-100\/80 { background: rgba(30,41,59,0.8) !important; }
        html.dark .bg-gray-100 { background: #1e293b !important; }
        html.dark .text-gray-800 { color: #e2e8f0 !important; }
        html.dark .text-gray-700 { color: #cbd5e1 !important; }
        html.dark .text-gray-600 { color: #94a3b8 !important; }
        html.dark .text-gray-500 { color: #64748b !important; }
        html.dark .text-gray-400 { color: #475569 !important; }
        html.dark .border-gray-100 { border-color: #334155 !important; }
        html.dark .border-gray-200 { border-color: #334155 !important; }
        html.dark .hover\:bg-white\/80:hover { background: rgba(30,41,59,0.8) !important; }
        html.dark .notification-dropdown,
        html.dark .user-dropdown { background: #1e293b !important; border-color: #334155 !important; }
        html.dark .bg-gradient-to-r.from-blue-50.to-indigo-50 { background: #1e3a5f !important; }
        html.dark footer { background: #1e293b !important; border-color: #334155 !important; }
        html.dark #darkModeIcon { font-weight: 900; }
        html.dark #darkModeToggle { color: #f59e0b !important; }

        /* ── Command Palette ── */
        #commandPalette {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 15vh;
        }
        #commandPalette.hidden { display: none; }
        #commandPaletteBackdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
        }
        #commandPaletteBox {
            position: relative;
            width: 100%;
            max-width: 580px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25), 0 0 0 1px rgba(0,0,0,0.05);
            overflow: hidden;
            animation: paletteReveal 0.18s cubic-bezier(0.16,1,0.3,1) both;
            margin: 0 16px;
        }
        html.dark #commandPaletteBox { background: #1e293b; border: 1px solid #334155; }
        @keyframes paletteReveal {
            from { opacity: 0; transform: scale(0.96) translateY(-8px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        #commandPaletteInput {
            width: 100%;
            padding: 16px 20px 16px 52px;
            font-size: 15px;
            border: none;
            outline: none;
            background: transparent;
            color: #1e293b;
        }
        html.dark #commandPaletteInput { color: #e2e8f0; }
        html.dark #commandPaletteInput::placeholder { color: #64748b; }
        #commandPaletteResults {
            max-height: 380px;
            overflow-y: auto;
        }
        .cp-section-label {
            padding: 6px 16px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #94a3b8;
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9;
        }
        html.dark .cp-section-label { background: #0f172a; border-color: #1e293b; color: #475569; }
        .cp-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f8fafc;
            transition: background 0.12s;
            text-decoration: none;
            color: inherit;
        }
        .cp-item:hover, .cp-item.cp-active { background: #eff6ff; }
        html.dark .cp-item:hover, html.dark .cp-item.cp-active { background: #1e3a5f; }
        html.dark .cp-item { border-color: #1e293b; color: #e2e8f0; }
        .cp-item-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 13px;
        }
        .cp-item-text { flex: 1; min-width: 0; }
        .cp-item-name { font-size: 13px; font-weight: 500; }
        .cp-item-sub { font-size: 11px; color: #94a3b8; margin-top: 1px; }
        .cp-kbd {
            font-size: 10px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 1px 5px;
            color: #64748b;
            white-space: nowrap;
        }
        html.dark .cp-kbd { background: #0f172a; border-color: #334155; color: #94a3b8; }
        .modal .modal-container{
            min-width: 700px;
        }
        .select2-container .select2-selection--single {        
            height: 42px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px;
            position: absolute;
            top: 1px;
            right: 3px;
            width: 20px;
        }
        #quickTaskColumn + .select2 .select2-selection--single,
        #quickTaskPriority + .select2 .select2-selection--single {
            height: 32px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
        }
        #quickTaskColumn + .select2 .select2-selection--single .select2-selection__rendered,
        #quickTaskPriority + .select2 .select2-selection--single .select2-selection__rendered {
            line-height: 30px;
            font-size: 12px;
            color: #1f2937;
            padding-left: 10px;
        }
        #quickTaskColumn + .select2 .select2-selection--single .select2-selection__arrow,
        #quickTaskPriority + .select2 .select2-selection--single .select2-selection__arrow {
            height: 30px;
            right: 6px;
        }
        #quickTaskColumn + .select2 .select2-selection--single .select2-selection__rendered,
        #quickTaskPriority + .select2 .select2-selection--single .select2-selection__rendered {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #quickTaskAssignees + .select2 .select2-selection--multiple {
            min-height: 32px;
            height: 32px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 0 8px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            background: #fff;
        }
        #quickTaskAssignees + .select2 .select2-selection--multiple .select2-selection__rendered {
            padding: 0;
            margin: 0;
            line-height: 30px;
            font-size: 12px;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        #quickTaskAssignees + .select2 .select2-selection--multiple .select2-selection__choice {
            display: none;
        }
        #quickTaskAssignees + .select2 .select2-selection--multiple .select2-selection__choice__display {
            line-height: 20px;
            font-size: 11px;
        }
        .qt-assignee-stack {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .qt-assignee-stack-avatars {
            display: flex;
            align-items: center;
            margin-left: -2px;
        }
        .qt-assignee-avatar {
            width: 20px;
            height: 20px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
            color: #fff;
            background: #6366f1;
            border: 2px solid #fff;
            margin-left: -6px;
            overflow: hidden;
        }
        .qt-assignee-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .qt-assignee-count {
            font-size: 10px;
            font-weight: 600;
            color: #475569;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            padding: 2px 6px;
            line-height: 1;
        }
        .qt-assignee-placeholder {
            color: #64748b;
            font-size: 12px;
        }
        .quick-task-select2-dropdown .select2-search__field {
            height: 28px;
            font-size: 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
        }
        .quick-task-select2-dropdown .select2-results__option {
            font-size: 12px;
            padding: 6px 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quick-task-select2-dropdown .select2-results__option--highlighted {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .quick-task-select2-dropdown {
            min-width: 260px !important;
        }
    </style>
    @include('change-password-css')

    {{-- ── LINK PRELOADING — every company ─────────────────────────────────
         Owner-approved 2026-08-10, alongside the @view-transition block above.

         When the pointer rests on a menu item, the browser quietly fetches
         that page's HTML in the background, so the click has nothing left to
         wait for. "prefetch" and not "prerender" on purpose: prefetch only
         downloads the HTML and does NOT execute the page's JavaScript, so it
         cannot open a second Pusher/Echo socket, fire notification reads, or
         run anything else early. It is a plain GET of a page the user is
         about to open anyway.

         eagerness "moderate" = on hover, not on sight, so idle pages are
         never fetched in bulk.

         Excluded: export/download routes, which build reports and files
         server-side and must not be generated by a hover. Any link can opt
         out individually with data-no-prefetch.

         To undo: delete this script. --}}
    <script type="speculationrules">
    {
        "prefetch": [
            {
                "where": {
                    "and": [
                        { "href_matches": "/*" },
                        { "not": { "href_matches": "/*export*" } },
                        { "not": { "href_matches": "/*download*" } },
                        { "not": { "selector_matches": "[data-no-prefetch]" } }
                    ]
                },
                "eagerness": "moderate"
            }
        ]
    }
    </script>
</head>
<body class="bg-gray-50">

    <!-- Mobile Menu Button -->
    <button id="mobileMenuButton" class="md:hidden fixed top-4 left-4 z-30 bg-blue-600 text-white p-2 rounded-lg shadow-lg">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    @include('layout.sidebar')

    <!-- Main Content Area -->
    @php
        // Must match sidebar.blade.php's $showCompanyRail width exactly (w-72 vs w-64).
        $mainContentOffset = empty(auth()->user()?->company_id) ? 'md:ml-72' : 'md:ml-64';
    @endphp
    <div id="mainContentWrapper" class="{{ $mainContentOffset }} min-h-screen flex flex-col">
        
        <!-- Header -->        
        @include('layout.header', ['title' => $title ?? 'Dashboard'])
        @include('layout.headline-ticker')

        <!-- Main Content -->
        <main class="flex-1 p-3">

            @yield('main-content')
            
        </main>

        <!-- Admin Footer -->
        <footer class="admin-footer py-3 mx-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
            <div class="container mx-auto">
                <div class="flex flex-col md:flex-row justify-between items-center space-y-2 md:space-y-0">
                    <div class="flex items-center space-x-6">
                        <span class="text-gray-500 text-sm">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                        </span>
                        <span class="hidden md:block text-gray-400 text-xs">|</span>
                        <span class="text-gray-400 text-xs">
                            Last Active On: Today, {{ date('h:i A') }}
                        </span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-400 text-xs">
                            v1.0.0
                        </span>
                        <span class="text-gray-400 text-xs">|</span>
                        <span class="text-gray-400 text-xs">
                            <i class="fas fa-circle text-green-500 mr-1"></i> System Online
                        </span>
                    </div>
                </div>
            </div>
        </footer>
        
    </div>

    <!-- Command Palette Modal -->
    <div id="commandPalette" class="hidden" role="dialog" aria-label="Command palette">
        <div id="commandPaletteBackdrop"></div>
        <div id="commandPaletteBox">
            <div class="flex items-center border-b border-gray-100 px-4" style="border-color: rgba(226,232,240,0.8);">
                <i class="fas fa-search text-gray-400 flex-shrink-0" style="font-size:15px;"></i>
                <input id="commandPaletteInput" type="text" placeholder="Search users, tasks, pages..." autocomplete="off" spellcheck="false">
                <span class="cp-kbd ml-2 flex-shrink-0">Esc</span>
            </div>
            <div id="commandPaletteResults"></div>
        </div>
    </div>

    @include('change-password')

    @auth
        @include('layout.chat-widget')
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @include('change-password-script')

    <script>
        
        const SIDEBAR_DESKTOP_STATE_KEY = 'sidebarDesktopCollapsed';
        const SIDEBAR_SCROLL_KEY = 'sidebarNavScrollTop';
        let sidebarScrollSaveTimer = null;

        function isDesktopViewport() {
            return window.matchMedia('(min-width: 768px)').matches;
        }

        function setDesktopSidebarState(isCollapsed) {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContentWrapper');
            const desktopSidebarIcon = document.getElementById('desktopSidebarIcon');

            if (!sidebar || !mainContent) {
                return;
            }

            // The sidebar's expanded width varies per user (w-72 with the company
            // rail, w-64 without it) — derive the matching offset instead of
            // assuming a fixed one, so collapse/expand stays aligned either way.
            const expandedOffset = sidebar.classList.contains('w-72') ? 'md:ml-72' : 'md:ml-64';

            if (isCollapsed) {
                sidebar.classList.remove('md:translate-x-0');
                sidebar.classList.add('-translate-x-full');
                mainContent.classList.remove('md:ml-72', 'md:ml-64');
                mainContent.classList.add('md:ml-0');
                if (desktopSidebarIcon) {
                    desktopSidebarIcon.classList.remove('fa-xmark');
                    desktopSidebarIcon.classList.add('fa-bars');
                }
            } else {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('md:translate-x-0');
                mainContent.classList.remove('md:ml-0');
                mainContent.classList.add(expandedOffset);
                if (desktopSidebarIcon) {
                    desktopSidebarIcon.classList.remove('fa-bars');
                    desktopSidebarIcon.classList.add('fa-xmark');
                }
            }

            localStorage.setItem(SIDEBAR_DESKTOP_STATE_KEY, isCollapsed ? '1' : '0');
        }

        function initializeSidebarState() {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) {
                return;
            }

            if (isDesktopViewport()) {
                const isCollapsed = localStorage.getItem(SIDEBAR_DESKTOP_STATE_KEY) === '1';
                setDesktopSidebarState(isCollapsed);
                return;
            }

            sidebar.classList.add('-translate-x-full');
        }

        function getSidebarNav() {
            // The actual scrollable element is the detail panel, not .sidebar-nav
            // itself (.sidebar-nav is now just a flex row wrapping the icon rail
            // and the panel side by side).
            return document.querySelector('#sidebarPanel');
        }

        function saveSidebarScrollPosition() {
            const sidebarNav = getSidebarNav();
            console.log('Saving sidebar scroll position:', sidebarNav ? sidebarNav.scrollTop : 'N/A');

            if (!sidebarNav) {
                return;
            }

            sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(sidebarNav.scrollTop));
        }

        function restoreSidebarScrollPosition() {
            const sidebarNav = getSidebarNav();
            if (!sidebarNav) {
                return;
            }

            const savedScrollTop = sessionStorage.getItem(SIDEBAR_SCROLL_KEY);

            requestAnimationFrame(function() {
                if (savedScrollTop !== null) {
                    sidebarNav.scrollTop = Number(savedScrollTop) || 0;
                    return;
                }

                const activeItem = sidebarNav.querySelector('.submenu-item.active, .sidebar-item.active');
                if (activeItem) {
                    activeItem.scrollIntoView({ block: 'nearest' });
                }
            });
        }

        function initializeSidebarScrollMemory() {
            const sidebarNav = getSidebarNav();
            if (!sidebarNav) {
                return;
            }

            restoreSidebarScrollPosition();

            sidebarNav.addEventListener('scroll', function() {
                clearTimeout(sidebarScrollSaveTimer);
                sidebarScrollSaveTimer = setTimeout(saveSidebarScrollPosition, 80);
            }, { passive: true });

            sidebarNav.querySelectorAll('a[href]').forEach(function(link) {
                link.addEventListener('click', saveSidebarScrollPosition);
            });

            window.addEventListener('beforeunload', saveSidebarScrollPosition);
        }

        // Toggle sidebar for both desktop and mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) {
                return;
            }

            if (isDesktopViewport()) {
                const isCollapsed = sidebar.classList.contains('-translate-x-full') && !sidebar.classList.contains('md:translate-x-0');
                setDesktopSidebarState(!isCollapsed);
                return;
            }

            sidebar.classList.toggle('-translate-x-full');
        }

        // Toggle submenu
        function toggleSubmenu(submenuId, button) {
            const sidebarNav = getSidebarNav();
            const currentScrollTop = sidebarNav ? sidebarNav.scrollTop : 0;
            const submenu = document.getElementById(submenuId);
            if (!submenu) return;

            const chevron = button.querySelector('i.fa-chevron-down');
            const isHidden = submenu.classList.contains('hidden');

            // Close sibling submenus (same level, skip parent/child relationships)
            document.querySelectorAll('.submenu').forEach(sub => {
                if (sub === submenu || sub.contains(submenu) || submenu.contains(sub)) return;
                sub.classList.add('hidden');
                const icon = sub.previousElementSibling
                    ? sub.previousElementSibling.querySelector('i.fa-chevron-down')
                    : null;
                if (icon) icon.classList.remove('chevron-rotate');
            });

            if (isHidden) {
                submenu.classList.remove('hidden');
                if (chevron) chevron.classList.add('chevron-rotate');
            } else {
                // Also collapse any nested open submenus
                submenu.querySelectorAll('.submenu').forEach(inner => {
                    inner.classList.add('hidden');
                    const icon = inner.previousElementSibling
                        ? inner.previousElementSibling.querySelector('i.fa-chevron-down')
                        : null;
                    if (icon) icon.classList.remove('chevron-rotate');
                });
                submenu.classList.add('hidden');
                if (chevron) chevron.classList.remove('chevron-rotate');
            }

            if (sidebarNav) {
                console.log('Restoring sidebar scroll position after submenu toggle:', currentScrollTop);
                requestAnimationFrame(function() {
                    sidebarNav.scrollTop = currentScrollTop;
                    saveSidebarScrollPosition();
                });
            }
        }


        // Stamp the active company's id onto every link inside its per-company
        // menu block, so navigating between its feature pages (most of which
        // have no {company} route param — only the literal Company Dashboard
        // route does) keeps the sidebar rail highlighting the right company
        // on the next page load instead of silently falling back to the
        // first visible company.
        document.querySelectorAll('[data-company-id]').forEach(function (panel) {
            const companyId = panel.getAttribute('data-company-id');
            panel.querySelectorAll('a[href]').forEach(function (link) {
                try {
                    const url = new URL(link.href, window.location.origin);
                    if (!url.searchParams.has('company')) {
                        url.searchParams.set('company', companyId);
                        link.href = url.toString();
                    }
                } catch (e) { /* ignore malformed hrefs */ }
            });
        });

        // Toggle user dropdown
        document.getElementById('userMenuButton').addEventListener('click', function() {
            document.getElementById('userDropdown').classList.toggle('hidden');
        });

        // Toggle notification dropdown
        document.getElementById('notificationButton').addEventListener('click', function() {
            document.getElementById('notificationDropdown').classList.toggle('hidden');
        });

        // Quick task modal controls
        const quickTaskButton = document.getElementById('quickTaskButton');
        const quickTaskModal = document.getElementById('quickTaskModal');
        const quickTaskCloseButton = document.getElementById('quickTaskCloseButton');
        const quickTaskCancelButton = document.getElementById('quickTaskCancelButton');
        const quickTaskFallbackCloseButton = document.getElementById('quickTaskFallbackCloseButton');
        const quickTaskModalBackdrop = document.getElementById('quickTaskModalBackdrop');
        const quickTaskTitle = document.getElementById('quickTaskTitle');
        const quickTaskForm = document.getElementById('quickTaskForm');
        const quickTaskDescription = document.getElementById('quickTaskDescription');
        const quickTaskProject = document.getElementById('quickTaskProject');
        const quickTaskBoard = document.getElementById('quickTaskBoard');
        const quickTaskColumn = document.getElementById('quickTaskColumn');
        const quickTaskAssignees = document.getElementById('quickTaskAssignees');
        const quickTaskPriority = document.getElementById('quickTaskPriority');
        const quickTaskWorkspaceId = document.getElementById('quickTaskWorkspaceId');
        const quickTaskAssignedTo = document.getElementById('quickTaskAssignedTo');
        let quickTaskColumnOptions = null;
        const quickTaskAssigneesDropdown = document.querySelector('[data-dropdown="assignees"][data-context="quick-task"]');
        const quickTaskAssigneesHidden = document.getElementById('quickTaskAssigneesHidden');

        function loadQuickTaskScript(src, scriptId) {
            return new Promise((resolve, reject) => {
                if (scriptId && document.getElementById(scriptId)) {
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.src = src;
                script.async = true;
                if (scriptId) script.id = scriptId;
                script.onload = () => resolve();
                script.onerror = () => reject(new Error(`Failed to load script: ${src}`));
                document.head.appendChild(script);
            });
        }

        function loadQuickTaskStyle(href, styleId) {
            if (styleId && document.getElementById(styleId)) {
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            if (styleId) link.id = styleId;
            document.head.appendChild(link);
        }

        async function ensureQuickTaskSelect2Assets() {
            loadQuickTaskStyle('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', 'quickTaskSelect2Css');

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                return;
            }

            if (!window.jQuery) {
                await loadQuickTaskScript('https://code.jquery.com/jquery-3.7.1.min.js', 'quickTaskJqueryJs');
            }

            if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
                await loadQuickTaskScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'quickTaskSelect2Js');
            }
        }

        async function ensureQuickTaskSummernoteAssets() {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote) {
                return;
            }

            loadQuickTaskStyle('https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css', 'quickTaskSummernoteCss');

            if (!window.jQuery) {
                await loadQuickTaskScript('https://code.jquery.com/jquery-3.7.1.min.js', 'quickTaskJqueryJs');
            }

            if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote)) {
                await loadQuickTaskScript('https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js', 'quickTaskSummernoteJs');
            }
        }

        function initQuickTaskAssigneesSelect2() {
            if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) || !quickTaskAssignees) {
                return;
            }

            const $assignees = window.jQuery(quickTaskAssignees);

            if ($assignees.hasClass('select2-hidden-accessible')) {
                $assignees.select2('destroy');
            }

            $assignees.select2({
                width: '100%',
                placeholder: quickTaskAssignees.dataset.placeholder || 'Select assignees',
                closeOnSelect: false,
                dropdownParent: window.jQuery('#quickTaskModal'),
                dropdownCssClass: 'quick-task-select2-dropdown',
                selectionCssClass: 'quick-task-select2-selection',
                templateResult: formatQuickTaskAssigneeOption,
                templateSelection: formatQuickTaskAssigneeOption
            });

            $assignees.on('select2:open', () => {
                const placeholder = quickTaskAssignees.dataset.searchPlaceholder || 'Search...';
                const $field = window.jQuery('.select2-container--open .select2-search__field');
                $field.attr('placeholder', placeholder);
            });

            $assignees.on('change', updateQuickTaskAssigneePreview);
            updateQuickTaskAssigneePreview();
        }

        function initQuickTaskSummernote() {
            if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote) || !quickTaskDescription) {
                return;
            }

            const $description = window.jQuery(quickTaskDescription);
            if ($description.next('.note-editor').length) {
                $description.summernote('destroy');
            }

            $description.summernote({
                height: 140,
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'clear']],
                    ['para', ['ul', 'ol']],
                    ['insert', ['link']]
                ],
                placeholder: 'Click to add description'
            });
        }

        function updateQuickTaskAssigneePreview() {
            if (!quickTaskAssignees || !(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
                return;
            }

            const $select = window.jQuery(quickTaskAssignees);
            const select2 = $select.data('select2');
            if (!select2) {
                return;
            }

            const $rendered = select2.$selection.find('.select2-selection__rendered');
            $rendered.empty();

            const selectedOptions = $select.find('option:selected').toArray();
            if (selectedOptions.length === 0) {
                const $placeholder = window.jQuery('<span class="qt-assignee-placeholder"></span>')
                    .text(quickTaskAssignees.dataset.placeholder || 'Select assignees');
                $rendered.append($placeholder);
                return;
            }

            const maxVisible = 3;
            const visibleOptions = selectedOptions.slice(0, maxVisible);
            const extraCount = selectedOptions.length - maxVisible;

            const $stack = window.jQuery('<span class="qt-assignee-stack"></span>');
            const $avatars = window.jQuery('<span class="qt-assignee-stack-avatars"></span>');

            visibleOptions.forEach((option) => {
                const name = option.text || '';
                const avatarUrl = option.dataset.avatar || '';
                const initials = name
                    .split(' ')
                    .filter(Boolean)
                    .map(part => part[0])
                    .slice(0, 2)
                    .join('')
                    .toUpperCase() || '?';

                const $avatar = window.jQuery('<span class="qt-assignee-avatar"></span>');
                if (avatarUrl) {
                    const $img = window.jQuery('<img />').attr('src', avatarUrl).attr('alt', name);
                    $avatar.append($img);
                } else {
                    $avatar.text(initials);
                }
                $avatar.attr('title', name);
                $avatars.append($avatar);
            });

            $stack.append($avatars);

            if (extraCount > 0) {
                const $count = window.jQuery('<span class="qt-assignee-count"></span>')
                    .text(`+${extraCount}`);
                $stack.append($count);
            }

            $rendered.append($stack);
        }

        function updateQuickTaskAssigneeDropdownPreview(ids = []) {
            if (!quickTaskAssigneesDropdown) {
                return;
            }

            const labelEl = quickTaskAssigneesDropdown.querySelector('[data-role="dropdown-label"]');
            const hidden = quickTaskAssigneesDropdown.querySelector('[data-role="dropdown-selected"]');
            const selectedIds = (ids || []).map(String);
            const set = new Set(selectedIds);

            quickTaskAssigneesDropdown.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
                const id = String(item.dataset.value || '');
                const isSelected = set.has(id);
                item.classList.toggle('bg-blue-50', isSelected);
                const check = item.querySelector('[data-role="dropdown-check"]');
                if (check) check.classList.toggle('hidden', !isSelected);
            });

            if (hidden) hidden.value = selectedIds.join(',');
            if (!labelEl) return;

            if (selectedIds.length === 0) {
                labelEl.textContent = 'Select assignees';
                return;
            }

            const selected = [];
            quickTaskAssigneesDropdown.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
                const id = String(item.dataset.value || '');
                if (!set.has(id)) return;
                const name = item.dataset.name || item.textContent.trim();
                const initial = (name?.[0] || '?').toUpperCase();
                const image = item.dataset.image || '';
                selected.push({ id, name, initial, image });
            });

            const visible = selected.slice(0, 3);
            const extra = selected.length - visible.length;
            const avatars = visible.map(a => {
                const avatarHtml = a.image
                    ? `<img src="${a.image}" alt="${a.name}" class="w-5 h-5 rounded-full object-cover ring-2 ring-white -ml-2 first:ml-0">`
                    : `<div class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[9px] font-semibold flex items-center justify-center ring-2 ring-white -ml-2 first:ml-0">${a.initial}</div>`;
                return avatarHtml;
            }).join('');

            const countHtml = extra > 0
                ? `<span class="qt-assignee-count">+${extra}</span>`
                : '';

            labelEl.innerHTML = `
                <div class="qt-assignee-stack">
                    <div class="qt-assignee-stack-avatars">${avatars}</div>
                    ${countHtml}
                </div>
            `;
        }

        function syncQuickTaskAssigneesHiddenInputs(ids = []) {
            if (!quickTaskAssigneesHidden) {
                return;
            }

            quickTaskAssigneesHidden.innerHTML = '';
            (ids || []).forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'assigned_users[]';
                input.value = String(id);
                quickTaskAssigneesHidden.appendChild(input);
            });
        }

        function formatQuickTaskAssigneeOption(option) {
            if (!option.id || !option.element) {
                return option.text;
            }

            const avatarUrl = option.element.dataset.avatar || '';
            const name = option.text || '';
            const initials = name
                .split(' ')
                .filter(Boolean)
                .map(part => part[0])
                .slice(0, 2)
                .join('')
                .toUpperCase();

            const $container = window.jQuery('<span class="flex items-center gap-2"></span>');
            const $label = window.jQuery('<span></span>').text(name);

            if (avatarUrl) {
                const $avatar = window.jQuery('<img />')
                    .attr('src', avatarUrl)
                    .attr('alt', name)
                    .addClass('w-5 h-5 rounded-full object-cover');
                $container.append($avatar);
            } else {
                const $fallback = window.jQuery('<span></span>')
                    .addClass('w-5 h-5 rounded-full bg-indigo-500 text-[10px] text-white font-semibold flex items-center justify-center')
                    .text(initials || '?');
                $container.append($fallback);
            }

            $container.append($label);
            return $container;
        }

        function formatQuickTaskIconOption(option) {
            if (!option.element) {
                return option.text;
            }

            const iconClass = option.element.dataset.icon;
            const colorClass = option.element.dataset.color || 'text-gray-400';

            if (!iconClass) {
                return option.text;
            }

            const $container = window.jQuery('<span class="flex items-center gap-2"></span>');
            const $icon = window.jQuery('<i></i>').addClass(iconClass).addClass(colorClass);
            const $label = window.jQuery('<span></span>').text(option.text);

            $container.append($icon).append($label);
            return $container;
        }

        function initQuickTaskIconSelects() {
            if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
                return;
            }

            const $column = quickTaskColumn ? window.jQuery(quickTaskColumn) : null;
            const $priority = quickTaskPriority ? window.jQuery(quickTaskPriority) : null;

            [$column, $priority].forEach(($select) => {
                if (!$select || !$select.length) {
                    return;
                }

                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }

                $select.select2({
                    width: '100%',
                    minimumResultsForSearch: 0,
                    dropdownParent: window.jQuery('#quickTaskModal'),
                    dropdownCssClass: 'quick-task-select2-dropdown',
                    selectionCssClass: 'quick-task-select2-selection',
                    templateResult: formatQuickTaskIconOption,
                    templateSelection: formatQuickTaskIconOption
                });

                $select.on('select2:open', () => {
                    const placeholder = ($select[0].dataset.searchPlaceholder || 'Search...');
                    const $field = window.jQuery('.select2-container--open .select2-search__field');
                    $field.attr('placeholder', placeholder);
                });
            });
        }

        function getVisibleOptions(selectElement) {
            if (!selectElement) return [];
            return Array.from(selectElement.options).filter((option, index) => {
                if (index === 0 && option.value === '') return false;
                return option.style.display !== 'none';
            });
        }

        function syncQuickTaskBoardByProject() {
            if (!quickTaskProject || !quickTaskBoard) return;

            const selectedProjectId = quickTaskProject.value;
            Array.from(quickTaskBoard.options).forEach((option, index) => {
                if (index === 0) {
                    option.style.display = '';
                    return;
                }

                const matches = option.dataset.projectId === selectedProjectId;
                option.style.display = matches ? '' : 'none';
            });

            const currentBoardOption = quickTaskBoard.options[quickTaskBoard.selectedIndex];
            const currentBoardVisible = currentBoardOption && currentBoardOption.style.display !== 'none';
            if (!currentBoardVisible) {
                const firstVisibleBoard = getVisibleOptions(quickTaskBoard)[0];
                quickTaskBoard.value = firstVisibleBoard ? firstVisibleBoard.value : '';
            }

            const selectedBoardOption = quickTaskBoard.options[quickTaskBoard.selectedIndex];
            if (quickTaskWorkspaceId) {
                quickTaskWorkspaceId.value = selectedBoardOption ? (selectedBoardOption.dataset.workspaceId || '') : '';
            }
        }

        function syncQuickTaskColumnByBoard() {
            if (!quickTaskBoard || !quickTaskColumn) return;

            if (!quickTaskColumnOptions) {
                quickTaskColumnOptions = Array.from(quickTaskColumn.options).map(option => option.cloneNode(true));
            }

            const selectedBoardId = quickTaskBoard.value;
            const currentValue = quickTaskColumn.value;

            quickTaskColumn.innerHTML = '';
            let hasCurrent = false;

            quickTaskColumnOptions.forEach((option, index) => {
                const isPlaceholder = index === 0 || option.value === '';
                if (isPlaceholder) {
                    quickTaskColumn.appendChild(option.cloneNode(true));
                    return;
                }

                if (option.dataset.boardId === selectedBoardId) {
                    if (option.value === currentValue) {
                        hasCurrent = true;
                    }
                    quickTaskColumn.appendChild(option.cloneNode(true));
                }
            });

            if (!hasCurrent) {
                const fallbackOption = Array.from(quickTaskColumn.options).find((option, index) => index > 0 && option.value !== '');
                quickTaskColumn.value = fallbackOption ? fallbackOption.value : '';
            }

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                const $column = window.jQuery(quickTaskColumn);
                if ($column.hasClass('select2-hidden-accessible')) {
                    $column.select2('destroy');
                }
                initQuickTaskIconSelects();
            }
        }

        function syncQuickTaskAssigneesByProject() {
            if (!quickTaskProject) return;

            if (!quickTaskAssignees && quickTaskAssigneesDropdown) {
                const selectedProjectId = quickTaskProject.value;
                const hidden = quickTaskAssigneesDropdown.querySelector('[data-role="dropdown-selected"]');
                const selectedIds = new Set((hidden?.value || '').split(',').map(s => s.trim()).filter(Boolean));

                quickTaskAssigneesDropdown.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
                    const projectIds = (item.dataset.projectIds || '').split(',').filter(Boolean);
                    const matches = projectIds.length === 0 || projectIds.includes(selectedProjectId);

                    item.style.display = matches ? '' : 'none';
                    if (!matches) {
                        selectedIds.delete(String(item.dataset.value || ''));
                    }
                });

                const updatedIds = Array.from(selectedIds);
                updateQuickTaskAssigneeDropdownPreview(updatedIds);
                syncQuickTaskAssigneesHiddenInputs(updatedIds);
                return;
            }

            if (!quickTaskAssignees) return;

            const selectedProjectId = quickTaskProject.value;
            Array.from(quickTaskAssignees.options).forEach((option) => {
                const projectIds = (option.dataset.projectIds || '').split(',').filter(Boolean);
                const matches = projectIds.length === 0 || projectIds.includes(selectedProjectId);

                option.style.display = matches ? '' : 'none';
                option.hidden = !matches;
                option.disabled = !matches;
                if (!matches) {
                    option.selected = false;
                }
            });

            const selectedAssigneeOption = Array.from(quickTaskAssignees.options).find(option => option.selected && option.style.display !== 'none');
            if (quickTaskAssignedTo) {
                quickTaskAssignedTo.value = selectedAssigneeOption ? selectedAssigneeOption.value : '';
            }

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                window.jQuery(quickTaskAssignees).trigger('change.select2');
            }
        }

        function syncQuickTaskDependencies() {
            syncQuickTaskBoardByProject();
            syncQuickTaskColumnByBoard();
            syncQuickTaskAssigneesByProject();
        }

        async function openQuickTaskModal() {
            if (!quickTaskModal) return;
            syncQuickTaskDependencies();

            try {
                await ensureQuickTaskSelect2Assets();
                await ensureQuickTaskSummernoteAssets();
                initQuickTaskAssigneesSelect2();
                initQuickTaskIconSelects();
                initQuickTaskSummernote();
            } catch (error) {
                console.error('Quick task Select2 initialization failed:', error);
            }

            if (quickTaskAssigneesDropdown) {
                const hidden = quickTaskAssigneesDropdown.querySelector('[data-role="dropdown-selected"]');
                const ids = (hidden?.value || '').split(',').filter(Boolean);
                updateQuickTaskAssigneeDropdownPreview(ids);
                syncQuickTaskAssigneesHiddenInputs(ids);
            }

            quickTaskModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            if (quickTaskTitle) {
                setTimeout(() => quickTaskTitle.focus(), 100);
            }
        }

        function closeQuickTaskModal() {
            if (!quickTaskModal) return;
            quickTaskModal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        if (quickTaskButton) {
            quickTaskButton.addEventListener('click', openQuickTaskModal);
        }

        if (quickTaskCloseButton) {
            quickTaskCloseButton.addEventListener('click', closeQuickTaskModal);
        }

        if (quickTaskCancelButton) {
            quickTaskCancelButton.addEventListener('click', closeQuickTaskModal);
        }

        if (quickTaskFallbackCloseButton) {
            quickTaskFallbackCloseButton.addEventListener('click', closeQuickTaskModal);
        }

        if (quickTaskModalBackdrop) {
            quickTaskModalBackdrop.addEventListener('click', closeQuickTaskModal);
        }

        if (quickTaskProject) {
            quickTaskProject.addEventListener('change', function() {
                syncQuickTaskDependencies();
                initQuickTaskAssigneesSelect2();
            });
        }

        if (quickTaskBoard) {
            quickTaskBoard.addEventListener('change', function() {
                syncQuickTaskColumnByBoard();

                const selectedBoardOption = quickTaskBoard.options[quickTaskBoard.selectedIndex];
                if (quickTaskWorkspaceId) {
                    quickTaskWorkspaceId.value = selectedBoardOption ? (selectedBoardOption.dataset.workspaceId || '') : '';
                }
            });
        }

        if (quickTaskAssignees) {
            quickTaskAssignees.addEventListener('change', function() {
                const selectedAssigneeOption = Array.from(quickTaskAssignees.options).find(option => option.selected && option.style.display !== 'none');
                if (quickTaskAssignedTo) {
                    quickTaskAssignedTo.value = selectedAssigneeOption ? selectedAssigneeOption.value : '';
                }
            });
        }

        document.addEventListener('dropdown:change', (e) => {
            const { type, value, wrapper } = e.detail || {};
            if (!wrapper || wrapper.dataset.context !== 'quick-task') return;

            if (type === 'assignees') {
                const ids = (value || []).map(String);
                updateQuickTaskAssigneeDropdownPreview(ids);
                syncQuickTaskAssigneesHiddenInputs(ids);
            }
        });

        if (quickTaskForm) {
            quickTaskForm.addEventListener('submit', async function(event) {
                event.preventDefault();

                if (quickTaskAssigneesDropdown) {
                    const hidden = quickTaskAssigneesDropdown.querySelector('[data-role="dropdown-selected"]');
                    const ids = (hidden?.value || '').split(',').filter(Boolean);
                    syncQuickTaskAssigneesHiddenInputs(ids);
                }

                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote && quickTaskDescription) {
                    const html = window.jQuery(quickTaskDescription).summernote('code');
                    quickTaskDescription.value = html;
                }

                if (quickTaskBoard && !quickTaskBoard.value) {
                    alert('Please select a board.');
                    return;
                }

                if (quickTaskColumn && !quickTaskColumn.value) {
                    alert('Please select a column.');
                    return;
                }

                const submitButton = quickTaskForm.querySelector('button[type="submit"]');
                const originalButtonText = submitButton ? submitButton.innerHTML : '';

                try {
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML = 'Saving...';
                    }

                    const formData = new FormData(quickTaskForm);

                    const response = await fetch(quickTaskForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: formData
                    });

                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        if (response.status === 422 && result.errors) {
                            const firstKey = Object.keys(result.errors)[0];
                            const firstMessage = firstKey ? result.errors[firstKey][0] : 'Validation failed.';
                            throw new Error(firstMessage);
                        }

                        throw new Error(result.message || 'Failed to create task.');
                    }

                    quickTaskForm.reset();
                    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && quickTaskAssignees) {
                        window.jQuery(quickTaskAssignees).val(null).trigger('change');
                    }
                    syncQuickTaskDependencies();
                    closeQuickTaskModal();

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: result.message || 'Task created successfully.',
                            timer: 2200,
                            showConfirmButton: false
                        });
                    }
                } catch (error) {
                    alert(error.message || 'Failed to create task.');
                } finally {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalButtonText;
                    }
                }
            });

            syncQuickTaskDependencies();
        }

        // Event listeners
        initializeSidebarState();
        initializeSidebarScrollMemory();

        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const closeSidebarButton = document.getElementById('closeSidebar');
        const desktopSidebarButton = document.getElementById('desktopSidebarButton');

        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', toggleSidebar);
        }

        if (closeSidebarButton) {
            closeSidebarButton.addEventListener('click', toggleSidebar);
        }

        if (desktopSidebarButton) {
            desktopSidebarButton.addEventListener('click', toggleSidebar);
        }

        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) {
                return;
            }

            if (isDesktopViewport()) {
                const isCollapsed = localStorage.getItem(SIDEBAR_DESKTOP_STATE_KEY) === '1';
                setDesktopSidebarState(isCollapsed);
            } else {
                sidebar.classList.add('md:translate-x-0');
                sidebar.classList.add('-translate-x-full');
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeQuickTaskModal();
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const mobileMenuButton = document.getElementById('mobileMenuButton');
            const userDropdown = document.getElementById('userDropdown');
            const userMenuButton = document.getElementById('userMenuButton');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationButton = document.getElementById('notificationButton');
            
            // Close sidebar when clicking outside on mobile
            if (window.innerWidth < 768 && 
                !sidebar.contains(event.target) && 
                !mobileMenuButton.contains(event.target) &&
                !sidebar.classList.contains('-translate-x-full')) {
                toggleSidebar();
            }
            
            // Close user dropdown when clicking outside
            if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.classList.add('hidden');
            }
            
            // Close notification dropdown when clicking outside
            if (!notificationButton.contains(event.target) && !notificationDropdown.contains(event.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });
    </script>

    @yield('raw-script')
    @yield('script')
    @yield('js')

    <script>
        if (!window.__globalUserSearchInitialized) {
            window.__globalUserSearchInitialized = true;

            document.addEventListener('DOMContentLoaded', function () {
                const searchInput = document.getElementById('globalSearch');
                const suggestions = document.getElementById('globalSearchSuggestions');
                const userSearchUrl = "{{ route('role.user.search', ['role' => Str::slug(Auth::user()->getRoleNames()->first() ?? 'user')]) }}";
                const userListUrl = "{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first() ?? 'user')]) }}";
                let searchTimer = null;

                if (!searchInput || !suggestions) {
                    return;
                }

                const renderGlobalSearchSuggestions = function (users) {
                    if (!Array.isArray(users) || users.length === 0) {
                        suggestions.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500">No users found.</div>';
                        suggestions.classList.remove('hidden');
                        return;
                    }

                    suggestions.innerHTML = users.map(function (user) {
                        return '<button type="button" class="w-full text-left px-4 py-3 border-b border-gray-100 hover:bg-gray-50 global-search-suggestion-item" data-id="' + user.id + '" data-name="' + (user.name || '') + '">' +
                            (user.name || 'Unknown') +
                            (user.email ? '<span class="block text-[10px] text-gray-400">' + user.email + '</span>' : '') +
                            '</button>';
                    }).join('');

                    suggestions.classList.remove('hidden');
                };

                const hideGlobalSearchSuggestions = function () {
                    suggestions.classList.add('hidden');
                };

                const searchGlobalUsers = function (query) {
                    if (!query.trim()) {
                        hideGlobalSearchSuggestions();
                        return;
                    }

                    fetch(userSearchUrl + '?q=' + encodeURIComponent(query), {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(function (data) {
                            renderGlobalSearchSuggestions(data);
                        })
                        .catch(function () {
                            hideGlobalSearchSuggestions();
                        });
                };

                searchInput.addEventListener('keyup', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(function () {
                        searchGlobalUsers(searchInput.value);
                    }, 250);
                });

                document.addEventListener('click', function (event) {
                    if (!searchInput.contains(event.target) && !suggestions.contains(event.target)) {
                        hideGlobalSearchSuggestions();
                    }
                });

                suggestions.addEventListener('click', function (event) {
                    const button = event.target.closest('.global-search-suggestion-item');
                    if (!button) {
                        return;
                    }

                    const userName = button.dataset.name || '';
                    if (userName) {
                        window.location.href = userListUrl + '?name=' + encodeURIComponent(userName);
                    }
                });
            });
        }
    </script>
    
    @if (session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: "{{ session('success') }}",
                showConfirmButton: true,
                confirmButtonColor: '#3085d6',
                timer: 4000
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: "{{ session('error') }}",
                showConfirmButton: true,
                confirmButtonColor: '#d33',
                timer: 4000
            });
        </script>
    @endif

    @if (session('info'))
        <script>
            Swal.fire({
                icon: 'info',
                title: 'Information',
                text: "{{ session('info') }}",
                showConfirmButton: true,
                confirmButtonColor: '#3085d6',
                timer: 4000
            });
        </script>
    @endif

    @if (session('warning'))
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Warning',
                text: "{{ session('warning') }}",
                showConfirmButton: true,
                confirmButtonColor: '#f6c23e',
                timer: 4000
            });
        </script>
    @endif

    @if(session('success') || session('error'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 4000)"
            x-show="show"
            x-transition
            class="fixed bottom-4 right-4 px-4 py-3 rounded shadow-lg text-white text-sm z-50
                {{ session('success') ? 'bg-green-600' : 'bg-red-600' }}">
            {{ session('success') ?? session('error') }}
        </div>
    @endif

    <script>
    // ── Dark Mode ──────────────────────────────────────────────────────────────
    (function () {
        const toggleBtn  = document.getElementById('darkModeToggle');
        const icon       = document.getElementById('darkModeIcon');
        const html       = document.documentElement;
        const DARK_KEY   = 'epal_dark_mode';

        function applyDark(on) {
            html.classList.toggle('dark', on);
            if (icon) {
                icon.className = on ? 'fas fa-sun text-sm' : 'fas fa-moon text-sm';
            }
        }

        // Restore preference
        applyDark(localStorage.getItem(DARK_KEY) === '1');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const isDark = html.classList.contains('dark');
                applyDark(!isDark);
                localStorage.setItem(DARK_KEY, isDark ? '0' : '1');
            });
        }
    })();

    // ── Command Palette (Ctrl+K) ───────────────────────────────────────────────
    (function () {
        const palette        = document.getElementById('commandPalette');
        const backdrop       = document.getElementById('commandPaletteBackdrop');
        const input          = document.getElementById('commandPaletteInput');
        const results        = document.getElementById('commandPaletteResults');
        const headerSearch   = document.getElementById('globalSearch');

        if (!palette || !input || !results) return;

        const role          = "{{ Str::slug(Auth::user()->getRoleNames()->first() ?? 'user') }}";
        const userSearchUrl = "{{ route('role.user.search', ['role' => Str::slug(Auth::user()->getRoleNames()->first() ?? 'user')]) }}";
        const userListUrl   = "{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first() ?? 'user')]) }}";

        const pages = [
            @can('view dashboard')
            { name: 'Dashboard',      icon: 'fa-house',         bg: 'bg-blue-100 text-blue-600',    href: `/${role}/dashboard` },
            @endcan
            { name: 'My Tasks',       icon: 'fa-list-check',    bg: 'bg-emerald-100 text-emerald-600', href: `/${role}/tasks` },
            { name: 'Notifications',  icon: 'fa-bell',          bg: 'bg-amber-100 text-amber-600',  href: `/${role}/notifications` },
            { name: 'Users',          icon: 'fa-users',         bg: 'bg-purple-100 text-purple-600', href: userListUrl },
            { name: 'Profile Update', icon: 'fa-user-pen',      bg: 'bg-indigo-100 text-indigo-600', href: `/${role}/profile/edit` },
        ];

        let searchTimer = null;
        let activeIdx   = -1;

        function escapeHtml(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        function open() {
            palette.classList.remove('hidden');
            input.value = '';
            activeIdx = -1;
            renderDefault();
            setTimeout(() => input.focus(), 30);
        }

        function close() {
            palette.classList.add('hidden');
            input.value = '';
        }

        function renderDefault() {
            let html = '<div class="cp-section-label">Quick Navigation</div>';
            html += pages.map((p, i) =>
                `<a class="cp-item" href="${escapeHtml(p.href)}" data-idx="${i}">
                    <span class="cp-item-icon ${p.bg}"><i class="fas ${p.icon}"></i></span>
                    <span class="cp-item-text"><span class="cp-item-name">${escapeHtml(p.name)}</span></span>
                </a>`
            ).join('');
            results.innerHTML = html;
            attachItemListeners();
        }

        function renderLoading() {
            results.innerHTML = '<div class="px-4 py-6 text-center text-sm text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i>Searching...</div>';
        }

        function renderResults(query, users) {
            let html = '';

            // Page matches
            const pageMatches = pages.filter(p => p.name.toLowerCase().includes(query.toLowerCase()));
            if (pageMatches.length) {
                html += '<div class="cp-section-label">Pages</div>';
                html += pageMatches.map((p, i) =>
                    `<a class="cp-item" href="${escapeHtml(p.href)}" data-idx="${i}">
                        <span class="cp-item-icon ${p.bg}"><i class="fas ${p.icon}"></i></span>
                        <span class="cp-item-text"><span class="cp-item-name">${escapeHtml(p.name)}</span></span>
                    </a>`
                ).join('');
            }

            // User matches
            if (users && users.length) {
                html += '<div class="cp-section-label">Users</div>';
                html += users.map(u =>
                    `<a class="cp-item" href="${escapeHtml(userListUrl + '?name=' + encodeURIComponent(u.name || ''))}" data-user-id="${u.id}">
                        <span class="cp-item-icon bg-blue-100 text-blue-600"><i class="fas fa-user"></i></span>
                        <span class="cp-item-text">
                            <span class="cp-item-name">${escapeHtml(u.name || 'Unknown')}</span>
                            ${u.email ? `<span class="cp-item-sub">${escapeHtml(u.email)}</span>` : ''}
                        </span>
                    </a>`
                ).join('');
            }

            if (!html) {
                html = `<div class="px-4 py-6 text-center text-sm text-gray-400">No results for "<strong>${escapeHtml(query)}</strong>"</div>`;
            }

            results.innerHTML = html;
            attachItemListeners();
        }

        function attachItemListeners() {
            results.querySelectorAll('.cp-item').forEach(item => {
                item.addEventListener('mouseenter', () => {
                    results.querySelectorAll('.cp-item').forEach(i => i.classList.remove('cp-active'));
                    item.classList.add('cp-active');
                    activeIdx = Array.from(results.querySelectorAll('.cp-item')).indexOf(item);
                });
            });
        }

        function navigate(dir) {
            const items = Array.from(results.querySelectorAll('.cp-item'));
            if (!items.length) return;
            items.forEach(i => i.classList.remove('cp-active'));
            activeIdx = (activeIdx + dir + items.length) % items.length;
            items[activeIdx].classList.add('cp-active');
            items[activeIdx].scrollIntoView({ block: 'nearest' });
        }

        input.addEventListener('keydown', e => {
            if (e.key === 'ArrowDown') { e.preventDefault(); navigate(1); }
            if (e.key === 'ArrowUp')   { e.preventDefault(); navigate(-1); }
            if (e.key === 'Enter') {
                e.preventDefault();
                const active = results.querySelector('.cp-active');
                if (active) active.click();
            }
            if (e.key === 'Escape') close();
        });

        input.addEventListener('input', () => {
            const q = input.value.trim();
            if (!q) { renderDefault(); return; }
            clearTimeout(searchTimer);
            renderLoading();
            searchTimer = setTimeout(() => {
                fetch(userSearchUrl + '?q=' + encodeURIComponent(q), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(data => renderResults(q, Array.isArray(data) ? data : []))
                .catch(() => renderResults(q, []));
            }, 220);
        });

        // Ctrl+K trigger
        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                palette.classList.contains('hidden') ? open() : close();
            }
            if (e.key === 'Escape' && !palette.classList.contains('hidden')) close();
        });

        // Click on header search input to open palette
        if (headerSearch) {
            headerSearch.addEventListener('focus', open);
        }

        backdrop.addEventListener('click', close);
    })();
    </script>

</body>
</html>
