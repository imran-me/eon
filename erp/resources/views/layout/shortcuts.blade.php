{{--
    Personal shortcuts — the strip of pinned tabs and the right-click menu that
    creates them. Included by layout/app.blade.php directly after the LIVE
    BULLETIN ticker, for every signed-in user, in every company.

    Two kinds of shortcut, both per user, both stored in user_shortcuts:

      tab   — a pill rendered here, in the 24px gap the ticker's bottom margin
              and <main>'s top padding already leave between the bulletin and
              the page. The strip is a zero-height block and the pills are
              absolutely positioned inside it, so the page keeps exactly the
              layout it had — nothing below moves when tabs appear or go.
      menu  — a link in the sidebar's "Shortcuts" section, under Dashboard and
              above Companies. The markup for that section is in
              sidebar.blade.php; the script below fills and empties it.

    Right-click any link in the sidebar to get "Add Shortcut Tab" / "Add to
    Shortcut Menu". Right-click a shortcut, or hover it for the ×, to remove
    it. Shift+right-click keeps the browser's own menu.

    Everything here is prefixed erp-sc- and scoped to its own elements: no
    bare element selectors, no listeners that act on anything outside the
    sidebar's links and the shortcut elements themselves.

    Nothing in this file can throw. user_shortcuts() swallows a missing
    table, and the two routes are Route::has()-guarded so a stale route
    cache leaves the strip inert rather than 500ing the shell.
--}}
@auth
@php
    $scTabs = user_shortcuts(\App\Models\UserShortcut::KIND_TAB);
    $scRole = request()->route('role') ?: \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
    $scCurrentPath = '/' . ltrim(request()->path(), '/');
    $scStoreUrl = \Illuminate\Support\Facades\Route::has('role.shortcuts.store')
        ? route('role.shortcuts.store', ['role' => $scRole])
        : null;
    $scDestroyBase = \Illuminate\Support\Facades\Route::has('role.shortcuts.destroy')
        ? preg_replace('#/0$#', '', route('role.shortcuts.destroy', ['role' => $scRole, 'shortcut' => 0]))
        : null;
@endphp

<style>
    /* ── Tab strip ─────────────────────────────────────────────────────── */
    .erp-sc-strip {
        position: relative;
        display: flex;          /* not a plain block: margins must not collapse through it */
        height: 0;
        /* same gutter as the ticker above it. margin-top: the ticker lost its
           12px bottom margin (2026-10-04, gap halved), so a strip WITH tabs
           puts it back — its pills keep the exact 24px gap they were built
           for, and users without shortcut tabs get the tighter 12px. */
        margin: 12px 16px 0;
        z-index: 5;
    }
    .erp-sc-strip[hidden] { display: none !important; }

    .erp-sc-row {
        position: absolute;
        left: 0;
        right: 0;
        top: -11px;             /* ticker bottom + 1px … content top − 2px */
        height: 21px;
        display: flex;
        align-items: center;
        gap: 6px;
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: none;
    }
    .erp-sc-row::-webkit-scrollbar { display: none; }

    .erp-sc-tab {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: 0 0 auto;
        max-width: 160px;
        height: 21px;
        padding: 0 20px 0 9px;  /* right side reserves room for the × */
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        background: #fff;
        color: #475569;
        font-size: 11px;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        text-decoration: none;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
        transition: border-color .15s, color .15s, background .15s;
    }
    .erp-sc-tab i { font-size: 10px; color: #7c3aed; flex-shrink: 0; }
    .erp-sc-tab .erp-sc-label { overflow: hidden; text-overflow: ellipsis; }
    .erp-sc-tab:hover { border-color: #c7d2fe; background: #f8fafc; color: #1e293b; }
    .erp-sc-tab.is-active { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; }
    .erp-sc-tab.is-active i { color: #2563eb; }
    .erp-sc-tab.is-removing { opacity: .4; pointer-events: none; }

    .erp-sc-x {
        position: absolute;
        right: 4px;
        top: 50%;
        transform: translateY(-50%);
        width: 13px;
        height: 13px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        line-height: 1;
        color: #94a3b8;
        opacity: .45;
        cursor: pointer;
        transition: opacity .12s, background .12s, color .12s;
    }
    .erp-sc-tab:hover .erp-sc-x { opacity: 1; }
    .erp-sc-x:hover { background: #fee2e2; color: #dc2626; }

    /* ── Sidebar "Shortcuts" section (markup in sidebar.blade.php) ─────── */
    #sidebar [data-shortcut-menu][hidden] { display: none !important; }
    #sidebar a.erp-sc-menu-item .erp-sc-menu-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }
    #sidebar a.erp-sc-menu-item .erp-sc-menu-x {
        margin-left: auto;
        width: 18px;
        height: 18px;
        border-radius: 5px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 14px;
        line-height: 1;
        color: #94a3b8;
        opacity: 0;
        transition: opacity .12s, background .12s, color .12s;
    }
    #sidebar a.erp-sc-menu-item:hover .erp-sc-menu-x { opacity: 1; }
    #sidebar a.erp-sc-menu-item .erp-sc-menu-x:hover { background: #fee2e2; color: #dc2626; }
    #sidebar a.erp-sc-menu-item.is-removing { opacity: .4; pointer-events: none; }

    /* ── Right-click menu ──────────────────────────────────────────────── */
    .erp-sc-ctx {
        position: fixed;
        z-index: 10000;
        min-width: 220px;
        max-width: 300px;
        padding: 6px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, .14);
    }
    .erp-sc-ctx[hidden] { display: none !important; }
    .erp-sc-ctx-title {
        padding: 6px 10px 5px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #94a3b8;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .erp-sc-ctx-item {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 8px 10px;
        border: 0;
        border-radius: 7px;
        background: none;
        color: #334155;
        font-size: 13px;
        font-weight: 500;
        text-align: left;
        cursor: pointer;
    }
    .erp-sc-ctx-item[hidden] { display: none !important; }
    .erp-sc-ctx-item:hover { background: #f1f5f9; color: #0f172a; }
    .erp-sc-ctx-item i { width: 16px; text-align: center; font-size: 12px; color: #7c3aed; }
    .erp-sc-ctx-item.is-danger i { color: #dc2626; }
    .erp-sc-ctx-item.is-danger:hover { background: #fef2f2; color: #b91c1c; }

    /* ── Dark mode (html.dark, toggled from the header) ────────────────── */
    html.dark .erp-sc-tab { background: #1e293b; border-color: #334155; color: #cbd5e1; box-shadow: none; }
    html.dark .erp-sc-tab:hover { background: #273449; border-color: #475569; color: #f1f5f9; }
    html.dark .erp-sc-tab.is-active { background: #1e3a8a; border-color: #3b82f6; color: #dbeafe; }
    html.dark .erp-sc-x:hover { background: #7f1d1d; color: #fecaca; }
    html.dark .erp-sc-ctx { background: #1e293b; border-color: #334155; box-shadow: 0 12px 32px rgba(0, 0, 0, .45); }
    html.dark .erp-sc-ctx-item { color: #cbd5e1; }
    html.dark .erp-sc-ctx-item:hover { background: #334155; color: #f8fafc; }
    html.dark .erp-sc-ctx-item.is-danger:hover { background: #450a0a; color: #fecaca; }

    @media print { .erp-sc-strip, .erp-sc-ctx { display: none !important; } }
</style>

<div id="erpShortcutTabs" class="erp-sc-strip" data-shortcut-strip @if($scTabs->isEmpty()) hidden @endif>
    <div class="erp-sc-row" data-shortcut-tab-list aria-label="Shortcut tabs">
        @foreach($scTabs as $s)
            @php $sPath = parse_url($s->url, PHP_URL_PATH) ?: $s->url; @endphp
            <a href="{{ $s->url }}"
               class="erp-sc-tab {{ $sPath === $scCurrentPath ? 'is-active' : '' }}"
               data-shortcut-id="{{ $s->id }}" data-shortcut-kind="tab" data-shortcut-url="{{ $s->url }}"
               title="{{ $s->label }}">
                <i class="{{ $s->icon ?: 'fas fa-bookmark' }}"></i>
                <span class="erp-sc-label">{{ $s->label }}</span>
                <span class="erp-sc-x" data-shortcut-remove role="button" title="Remove tab" aria-label="Remove tab">&times;</span>
            </a>
        @endforeach
    </div>
</div>

<div id="erpShortcutCtx" class="erp-sc-ctx" role="menu" hidden>
    <div class="erp-sc-ctx-title" data-ctx-title></div>
    <button type="button" class="erp-sc-ctx-item" data-ctx-action="add-tab" role="menuitem"><i class="fas fa-window-restore"></i> Add Shortcut Tab</button>
    <button type="button" class="erp-sc-ctx-item is-danger" data-ctx-action="remove-tab" role="menuitem" hidden><i class="fas fa-xmark"></i> Remove Shortcut Tab</button>
    <button type="button" class="erp-sc-ctx-item" data-ctx-action="add-menu" role="menuitem"><i class="fas fa-thumbtack"></i> Add to Shortcut Menu</button>
    <button type="button" class="erp-sc-ctx-item is-danger" data-ctx-action="remove-menu" role="menuitem" hidden><i class="fas fa-xmark"></i> Remove from Shortcut Menu</button>
</div>

<script>
(function () {
    var strip   = document.getElementById('erpShortcutTabs');
    var ctx     = document.getElementById('erpShortcutCtx');
    var tabList = strip ? strip.querySelector('[data-shortcut-tab-list]') : null;
    if (!strip || !ctx || !tabList) return;

    var STORE_URL    = @json($scStoreUrl);
    var DESTROY_BASE = @json($scDestroyBase);
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var HEADERS = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF,
        'X-Requested-With': 'XMLHttpRequest'
    };
    var FALLBACK_ICON = 'fas fa-bookmark';
    var target = null; // what the open context menu is about: {url, label, icon}

    function menuWrap() { return document.querySelector('#sidebar [data-shortcut-menu]'); }
    function menuList() { return document.querySelector('#sidebar [data-shortcut-menu-list]'); }

    function toast(icon, title) {
        if (!window.Swal) return;
        Swal.fire({ toast: true, position: 'top-end', icon: icon, title: title, showConfirmButton: false, timer: 1800 });
    }

    // ── Reading a link ────────────────────────────────────────────────────
    // Only the icon-font classes survive; Tailwind sizing (text-xs, w-3) does
    // not belong in a stored icon.
    function iconOf(el) {
        var i = el.querySelector('i');
        if (!i) return null;
        var keep = [];
        for (var k = 0; k < i.classList.length; k++) {
            var c = i.classList[k];
            if (/^(fa|fas|far|fab|fal|fad|fa-[a-z0-9-]+|bi|bi-[a-z0-9-]+)$/.test(c)) keep.push(c);
        }
        return keep.length ? keep.join(' ') : null;
    }

    function labelOf(el) {
        var own = el.querySelector('.erp-sc-label, .erp-sc-menu-label');
        if (own) return own.textContent.trim();
        var clone = el.cloneNode(true);
        var junk = clone.querySelectorAll('i, .sidebar-badge, .sidebar-icon-box, [data-shortcut-remove], .erp-sc-x, .erp-sc-menu-x');
        for (var k = 0; k < junk.length; k++) junk[k].parentNode.removeChild(junk[k]);
        return clone.textContent.replace(/\s+/g, ' ').trim().slice(0, 60);
    }

    // Same-origin path (+query) or null. "#", javascript: and cross-origin
    // links are not pages and cannot be pinned.
    function pathOf(a) {
        var href = a.getAttribute('href');
        if (!href || href === '#' || /^(javascript|mailto|tel):/i.test(href)) return null;
        var u;
        try { u = new URL(a.href, location.href); } catch (e) { return null; }
        if (u.origin !== location.origin) return null;
        var p = u.pathname + u.search;
        return (p && p !== '/') ? p : null;
    }

    function metaOf(el) {
        if (el.hasAttribute('data-shortcut-url')) {
            return { url: el.getAttribute('data-shortcut-url'), label: labelOf(el), icon: iconOf(el) };
        }
        var url = pathOf(el);
        if (!url) return null;
        var label = labelOf(el);
        if (!label) return null;
        return { url: url, label: label, icon: iconOf(el) };
    }

    function isCurrent(url) {
        try { return new URL(url, location.origin).pathname === location.pathname; } catch (e) { return false; }
    }

    function findExisting(kind, url) {
        var list = kind === 'tab' ? tabList : menuList();
        if (!list) return null;
        var nodes = list.querySelectorAll('[data-shortcut-id]');
        for (var k = 0; k < nodes.length; k++) {
            if (nodes[k].getAttribute('data-shortcut-url') === url) return nodes[k];
        }
        return null;
    }

    // ── Rendering ─────────────────────────────────────────────────────────
    function el(tag, className, text) {
        var n = document.createElement(tag);
        if (className) n.className = className;
        if (text != null) n.textContent = text;
        return n;
    }

    function removeButton(className, title) {
        var x = el('span', className, '×');
        x.setAttribute('data-shortcut-remove', '');
        x.setAttribute('role', 'button');
        x.setAttribute('title', title);
        x.setAttribute('aria-label', title);
        return x;
    }

    function stamp(a, s, kind) {
        a.href = s.url;
        a.setAttribute('data-shortcut-id', s.id);
        a.setAttribute('data-shortcut-kind', kind);
        a.setAttribute('data-shortcut-url', s.url);
        a.title = s.label;
    }

    function renderTab(s) {
        var a = el('a', 'erp-sc-tab' + (isCurrent(s.url) ? ' is-active' : ''));
        stamp(a, s, 'tab');
        a.appendChild(el('i', s.icon || FALLBACK_ICON));
        a.appendChild(el('span', 'erp-sc-label', s.label));
        a.appendChild(removeButton('erp-sc-x', 'Remove tab'));
        tabList.appendChild(a);
        strip.hidden = false;
        tabList.scrollLeft = tabList.scrollWidth; // show the new pill; never scroll the page
    }

    function renderMenu(s) {
        var wrap = menuWrap(), list = menuList();
        if (!wrap || !list) return;
        var a = el('a', 'sidebar-item erp-sc-menu-item' + (isCurrent(s.url) ? ' active' : ''));
        stamp(a, s, 'menu');
        // Same search filter the rest of the sidebar uses; Alpine initialises
        // nodes added inside its tree, so this works on a live page too.
        a.setAttribute('x-show', '!search || ' + JSON.stringify(s.label.toLowerCase()) + '.includes(search.toLowerCase())');
        var box = el('div', 'sidebar-icon-box');
        box.appendChild(el('i', s.icon || FALLBACK_ICON));
        a.appendChild(box);
        a.appendChild(el('span', 'erp-sc-menu-label', s.label));
        a.appendChild(removeButton('erp-sc-menu-x', 'Remove from Shortcut menu'));
        list.appendChild(a);
        wrap.hidden = false;
    }

    function dropNode(node) {
        var kind = node.getAttribute('data-shortcut-kind');
        node.parentNode && node.parentNode.removeChild(node);
        if (kind === 'tab') {
            if (!tabList.querySelector('[data-shortcut-id]')) strip.hidden = true;
        } else {
            var wrap = menuWrap(), list = menuList();
            if (wrap && list && !list.querySelector('[data-shortcut-id]')) wrap.hidden = true;
        }
    }

    // ── Server calls ──────────────────────────────────────────────────────
    function parse(r) {
        return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, body: j || {} }; });
    }

    function add(kind, meta) {
        if (!STORE_URL) { toast('error', 'Shortcuts are not available yet.'); return; }
        fetch(STORE_URL, {
            method: 'POST', headers: HEADERS, credentials: 'same-origin',
            body: JSON.stringify({ kind: kind, label: meta.label, url: meta.url, icon: meta.icon })
        }).then(parse).then(function (res) {
            var j = res.body;
            if (!j.ok) {
                var errs = j.errors ? Object.keys(j.errors).map(function (k) { return j.errors[k][0]; }) : [];
                toast('error', j.message || errs[0] || 'Could not add the shortcut.');
                return;
            }
            if (!j.existed) (kind === 'tab' ? renderTab : renderMenu)(j.shortcut);
            toast('success', kind === 'tab' ? 'Added as a shortcut tab' : 'Added to the Shortcut menu');
        }).catch(function () { toast('error', 'Could not add the shortcut.'); });
    }

    function remove(node) {
        if (!node) return;
        var id = node.getAttribute('data-shortcut-id');
        if (!id || !DESTROY_BASE) return;
        node.classList.add('is-removing');
        fetch(DESTROY_BASE + '/' + encodeURIComponent(id), {
            method: 'DELETE', headers: HEADERS, credentials: 'same-origin'
        }).then(parse).then(function (res) {
            // 404 means it is already gone — the outcome the user wanted.
            if (res.status === 200 || res.status === 404) {
                dropNode(node);
                toast('success', 'Shortcut removed');
            } else {
                node.classList.remove('is-removing');
                toast('error', res.body.message || 'Could not remove the shortcut.');
            }
        }).catch(function () {
            node.classList.remove('is-removing');
            toast('error', 'Could not remove the shortcut.');
        });
    }

    // ── Context menu ──────────────────────────────────────────────────────
    function closeCtx() { ctx.hidden = true; target = null; }

    function openCtx(x, y, meta) {
        target = meta;
        ctx.querySelector('[data-ctx-title]').textContent = meta.label;
        var hasTab = !!findExisting('tab', meta.url), hasMenu = !!findExisting('menu', meta.url);
        ctx.querySelector('[data-ctx-action="add-tab"]').hidden = hasTab;
        ctx.querySelector('[data-ctx-action="remove-tab"]').hidden = !hasTab;
        ctx.querySelector('[data-ctx-action="add-menu"]').hidden = hasMenu;
        ctx.querySelector('[data-ctx-action="remove-menu"]').hidden = !hasMenu;
        ctx.hidden = false;
        var r = ctx.getBoundingClientRect();
        ctx.style.left = Math.max(8, Math.min(x, window.innerWidth - r.width - 8)) + 'px';
        ctx.style.top  = Math.max(8, Math.min(y, window.innerHeight - r.height - 8)) + 'px';
    }

    document.addEventListener('contextmenu', function (e) {
        if (e.shiftKey) return; // the browser's own menu is still reachable
        var hit = e.target.closest ? e.target.closest('[data-shortcut-id], #sidebar a[href]') : null;
        if (!hit) { if (!ctx.hidden) closeCtx(); return; }
        var meta = metaOf(hit);
        if (!meta) return;
        e.preventDefault();
        openCtx(e.clientX, e.clientY, meta);
    });

    ctx.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ctx-action]');
        if (!btn || !target) return;
        var action = btn.getAttribute('data-ctx-action'), meta = target;
        closeCtx();
        if (action === 'add-tab')          add('tab', meta);
        else if (action === 'add-menu')    add('menu', meta);
        else if (action === 'remove-tab')  remove(findExisting('tab', meta.url));
        else if (action === 'remove-menu') remove(findExisting('menu', meta.url));
    });

    // Capture phase: the × sits inside the shortcut's own <a>, so the click
    // must be stopped here before the link — or any page-level link handler —
    // sees it.
    document.addEventListener('click', function (e) {
        var x = e.target.closest ? e.target.closest('[data-shortcut-remove]') : null;
        if (x) {
            e.preventDefault();
            e.stopPropagation();
            remove(x.closest('[data-shortcut-id]'));
            return;
        }
        if (!ctx.hidden && !e.target.closest('#erpShortcutCtx')) closeCtx();
    }, true);

    // The strip is one line with its scrollbar hidden; a plain wheel over it
    // scrolls it sideways so pills past the edge stay reachable without Shift.
    tabList.addEventListener('wheel', function (e) {
        if (tabList.scrollWidth <= tabList.clientWidth || e.deltaY === 0) return;
        e.preventDefault();
        tabList.scrollLeft += e.deltaY;
    }, { passive: false });

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !ctx.hidden) closeCtx(); });
    window.addEventListener('resize', closeCtx);
    window.addEventListener('scroll', function () { if (!ctx.hidden) closeCtx(); }, true);
})();
</script>
@endauth
