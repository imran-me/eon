/* ===========================================================================
   WOOD ART INTERIORS — in-suite navigation
   ---------------------------------------------------------------------------
   Why this exists
     Every ERP page is a full document load. Clicking a menu item throws away the
     DOM and rebuilds the whole shell — sidebar (1.7k lines), header, ticker,
     chat widget — even though none of it changed. The browser paints a blank
     frame in between (the "blink"), Alpine re-boots from a CDN so the sidebar's
     x-show menus flash, and the sidebar's saved scroll position is restored a
     frame after paint so it visibly jumps.

     The reference build (platform/core/router.js) has none of this because it
     only ever swaps one element's innerHTML. This does the same thing, for Wood
     Art only: fetch the next page, lift out its [data-wa-view], put it in place
     of the current one. The shell is never touched, so there is nothing to
     blink, reflow, re-boot or re-scroll.

   Isolation contract — see CLAUDE.md, which locks this
     - Loaded ONLY by Wood Art views. No other company's page has this file.
     - Bails out immediately unless [data-wa-view] is on the page, so even if it
       were loaded somewhere else it would do nothing.
     - Intercepts a click ONLY when the target URL is same-origin and its path
       contains /woodart/. Every other link in the sidebar — every other
       company's entire menu — is left to the browser, untouched.
     - The only DOM it writes outside its own region is the `active` class on
       a.wa-nav-sub[data-wa-module], which is Wood Art's own menu markup.
     - Any surprise (bad status, redirect elsewhere, missing region, network
       error, session timeout) falls back to a normal full navigation. Worst
       case is today's behaviour, never a broken page.

   Requirements it places on Wood Art views (also locked in CLAUDE.md)
     - Page content sits inside [data-wa-view]; it is replaced wholesale.
     - No <script> inside [data-wa-view] — it will not re-run. Scripts live
       outside it, load once, and persist across sections.
   ======================================================================== */
(function () {
    'use strict';

    if (window.__waNav) return;          /* never bind twice */

    var VIEW      = '[data-wa-view]';
    var SCOPE     = '/woodart/';         /* the ONLY paths this file will claim */
    var FADE_MS   = 150;
    var BAR_AFTER = 140;                 /* only show progress if it's slow    */
    var PREFETCH_TTL = 25000;

    if (!document.querySelector(VIEW)) return;
    window.__waNav = true;

    var cache    = new Map();            /* url -> { html, at }                */
    var inflight = null;                 /* AbortController for the live nav   */
    var barTimer = null;

    /* ── what this file is allowed to claim ──────────────────────────────── */

    function inScope(url) {
        return url.origin === location.origin && url.pathname.indexOf(SCOPE) !== -1;
    }

    /* The shell stamps ?company=6 onto sidebar links so the company rail keeps
       the right company highlighted after a load (app.blade.php:656). It only
       reaches links inside the [data-company-id] panel, so the in-page tab bar
       never had it. That did not show before, because a tab click was a fresh
       load that never left a bookmarkable URL behind — now these URLs go into
       history, so carry the param across ourselves. Only ever copies a value
       already present on the current URL. */
    function carryCompany(url) {
        var here = new URL(location.href).searchParams.get('company');
        if (here && !url.searchParams.has('company')) url.searchParams.set('company', here);
        return url;
    }

    /* A plain left-click on an ordinary in-suite link. Anything else — new tab,
       download, modifier key, external host, another company — is not ours. */
    function claimable(e, a) {
        if (e.defaultPrevented || e.button !== 0) return false;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return false;
        if (!a || !a.href) return false;
        if (a.target && a.target !== '_self') return false;
        if (a.hasAttribute('download') || a.hasAttribute('data-wa-hard')) return false;
        if (a.getAttribute('href').charAt(0) === '#') return false;

        var url;
        try { url = new URL(a.href, location.href); } catch (err) { return false; }
        if (!inScope(url)) return false;
        carryCompany(url);
        /* Same page, no hash to jump to — nothing to do. */
        if (url.href === location.href) return false;
        return url;
    }

    /* ── fetching ────────────────────────────────────────────────────────── */

    function fetchPage(url, signal) {
        var hit = cache.get(url);
        if (hit && Date.now() - hit.at < PREFETCH_TTL) return Promise.resolve(hit.html);

        return fetch(url, {
            credentials: 'same-origin',
            redirect: 'follow',
            signal: signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-WA-Nav': '1' }
        }).then(function (res) {
            /* Bounced to a login screen or out of the suite: let the browser do
               it properly rather than half-rendering something. */
            if (!res.ok) throw new Error('status ' + res.status);
            if (!inScope(new URL(res.url, location.href))) throw new Error('left scope');
            return res.text();
        }).then(function (html) {
            cache.set(url, { html: html, at: Date.now() });
            return html;
        });
    }

    /* ── the swap ────────────────────────────────────────────────────────── */

    function swap(html, url, push) {
        var doc  = new DOMParser().parseFromString(html, 'text/html');
        var next = doc.querySelector(VIEW);
        var here = document.querySelector(VIEW);
        if (!next || !here) throw new Error('no view region');

        if (push) history.pushState({ waNav: 1 }, '', url);

        var title = doc.querySelector('title');
        if (title) document.title = title.textContent;

        syncCrumb(doc);

        /* Carry over any state attributes the server put on the region (active
           section, module key) so CSS hooks keep working. */
        Array.prototype.forEach.call(next.attributes, function (attr) {
            if (attr.name !== 'class') here.setAttribute(attr.name, attr.value);
        });
        here.className = next.className;

        here.replaceChildren.apply(here, Array.prototype.slice.call(next.childNodes));

        syncMenu(url);
        resetScroll(here);

        /* Fade IN only. Fading out first would add its own delay to every click
           — the point is that this feels instant, not that it animates. */
        here.classList.remove('wa-view-enter');
        void here.offsetWidth;                       /* restart the animation  */
        here.classList.add('wa-view-enter');
        setTimeout(function () { here.classList.remove('wa-view-enter'); }, FADE_MS + 60);

        document.dispatchEvent(new CustomEvent('wa:navigated', {
            detail: { url: url, view: here }
        }));
    }

    /* The shell's breadcrumb ("Active Projects") is rendered by the shared
       header from the controller's $title, so without this it would keep showing
       the section you arrived on. We do not edit the shared header to add a hook
       — layout/header.blade.php must stay byte-identical for every other company
       (CLAUDE.md). Instead we read the value the server already rendered in the
       fetched page and copy it across.

       Deliberately timid: it writes only when the selector resolves to exactly
       one node in BOTH documents. If the header is ever restructured this
       silently does nothing and the breadcrumb simply lags — cosmetic, never a
       broken page, and still nothing outside Wood Art is touched. */
    var CRUMB = 'header.header nav span.text-blue-600.font-medium';

    function syncCrumb(doc) {
        var from = doc.querySelectorAll(CRUMB);
        var to   = document.querySelectorAll(CRUMB);
        if (from.length !== 1 || to.length !== 1) return;
        to[0].textContent = from[0].textContent;
    }

    /* Repaint Wood Art's own menu rows. Scoped to a.wa-nav-sub[data-wa-module],
       which exists only inside Wood Art's panel — no other company's sidebar
       markup carries these attributes, so nothing else can be matched. */
    /* A bare module URL (no section segment) serves the module's DEFAULT
       section, and that default is per-module — mirroring each module's
       registry. Keyed here so the menu highlight agrees with what the server
       actually rendered. */
    var DEFAULT_SUB = { projects: 'active', scope: 'spaces', design: 'register', estimates: 'quotations', clients: 'directory', materials: 'stock', production: 'jobs', installation: 'schedule', procurement: 'orders', accounts: 'overview' };

    function syncMenu(url) {
        var parts  = new URL(url, location.href).pathname.split('/').filter(Boolean);
        var at     = parts.indexOf('woodart');
        var module = parts[at + 1] || '';
        var sub    = parts[at + 2] || DEFAULT_SUB[module] || 'active';

        document.querySelectorAll('a.wa-nav-sub[data-wa-module]').forEach(function (a) {
            var mine = a.getAttribute('data-wa-module') === module &&
                       a.getAttribute('data-wa-sub')    === sub;
            a.classList.toggle('active', mine);
        });
    }

    function resetScroll(view) {
        var host = view.closest('[data-wa-scene]') || view;
        if (host.scrollTop) host.scrollTop = 0;
        if (window.scrollY) window.scrollTo(0, 0);
    }

    /* ── progress bar, only when the wait is long enough to notice ───────── */

    function barOn() {
        clearTimeout(barTimer);
        barTimer = setTimeout(function () {
            document.documentElement.classList.add('wa-nav-busy');
        }, BAR_AFTER);
    }
    function barOff() {
        clearTimeout(barTimer);
        document.documentElement.classList.remove('wa-nav-busy');
    }

    /* ── navigation ──────────────────────────────────────────────────────── */

    function hard(url) { barOff(); location.href = url; }

    function go(url, push) {
        if (inflight) inflight.abort();
        inflight = new AbortController();
        var signal = inflight.signal;

        barOn();
        fetchPage(url, signal).then(function (html) {
            if (signal.aborted) return;
            barOff();
            swap(html, url, push);
            inflight = null;
        }).catch(function (err) {
            if (err && err.name === 'AbortError') return;
            hard(url);                              /* always end up somewhere */
        });
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;
        var url = claimable(e, a);
        if (!url) return;

        e.preventDefault();
        go(url.href, true);
    });

    window.addEventListener('popstate', function () {
        if (!inScope(new URL(location.href))) { location.reload(); return; }
        go(location.href, false);
    });

    /* ── prefetch on intent ──────────────────────────────────────────────────
       Warm the next page while the pointer is still travelling to the link, so
       by the time the click lands the HTML is usually already in hand and the
       swap is immediate. Same-origin GET of a page the user is about to open
       anyway — no side effects beyond one extra render. */
    var warmTimer = null;
    function warm(e) {
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || !a.href) return;
        var url;
        try { url = new URL(a.href, location.href); } catch (err) { return; }
        if (!inScope(url)) return;
        carryCompany(url);                 /* same key the click will use */
        if (url.href === location.href) return;
        if (cache.has(url.href)) return;

        clearTimeout(warmTimer);
        warmTimer = setTimeout(function () {
            fetchPage(url.href).catch(function () { cache.delete(url.href); });
        }, 60);
    }
    document.addEventListener('mouseover', warm, { passive: true });
    document.addEventListener('touchstart', warm, { passive: true });
    document.addEventListener('mouseout', function () { clearTimeout(warmTimer); }, { passive: true });
})();
