/* ============================================================
   EON · eon-sidebar — the ERP's side menu, collapsible.

   The ERP ships a fixed sidebar (#sidebar, w-72 or w-64) with a
   64px company rail (#sidebarRail) beside the scrolling menu
   (#sidebarPanel), and a main column offset by a matching margin
   (#mainContentWrapper). None of that is ours to edit, so this
   collapses it from the outside:

     · a toggle in the sidebar header, and Ctrl+\ from anywhere
     · collapsed = the rail alone (64px); with no rail, fully away
     · hovering the collapsed rail peeks the menu as a flyout
     · the choice is remembered per browser
     · below 768px nothing happens — the ERP's own off-canvas
       drawer already owns that case

   Every element it adds is prefixed `eon-sb` and carries data-eon,
   and every ERP element it needs is *tagged* at runtime rather
   than matched by class, so an ERP restyle cannot silently break
   it. Removing this file returns the ERP to exactly what it was.
   ============================================================ */
(function () {
  'use strict';
  if (window.__EON_SIDEBAR_LOADED) return;
  window.__EON_SIDEBAR_LOADED = true;

  var KEY = 'eon_sidebar';           // 'collapsed' | 'expanded'
  var RAIL = 64;                     // the ERP's own w-16 rail
  var BREAKPOINT = 768;              // Tailwind md — below this the ERP takes over
  var root = document.documentElement;
  var state = { railed: false, width: 288, ready: false };

  function stored() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }
  function remember(v) {
    try { localStorage.setItem(KEY, v); } catch (e) {}
  }

  /* ---------- paint the class before first paint, so it never flashes ---------- */
  if (stored() === 'collapsed') root.classList.add('eon-sb-collapsed');

  /* ---------- styles ---------- */
  function styles() {
    if (document.getElementById('eon-sb-css')) return;
    var css = [
      /* the toggle itself */
      '.eon-sb-toggle{display:inline-flex;align-items:center;justify-content:center;',
      '  width:28px;height:28px;border:0;border-radius:8px;cursor:pointer;flex:0 0 auto;',
      '  background:#f3f4f6;color:#6b7280;transition:background .15s,color .15s,transform .2s;}',
      '.eon-sb-toggle:hover{background:#eff6ff;color:#2563eb;}',
      '.eon-sb-toggle:focus-visible{outline:2px solid #2563eb;outline-offset:2px;}',
      '.eon-sb-toggle svg{width:15px;height:15px;pointer-events:none;}',

      /* only ever act on a real desktop layout */
      '@media (min-width:' + BREAKPOINT + 'px){',

      '  html.eon-sb-collapsed #sidebar{width:' + RAIL + 'px !important;}',
      '  html.eon-sb-collapsed.eon-sb-norail #sidebar{width:0 !important;border-right:0 !important;overflow:hidden !important;}',

      '  html.eon-sb-collapsed [data-eon-sb="panel"],',
      '  html.eon-sb-collapsed [data-eon-sb="search"],',
      '  html.eon-sb-collapsed [data-eon-sb="brandtext"],',
      '  html.eon-sb-collapsed [data-eon-sb="foottext"]{display:none !important;}',

      /* the header and footer shrink to the rail width and centre what is left */
      '  html.eon-sb-collapsed [data-eon-sb="brand"],',
      '  html.eon-sb-collapsed [data-eon-sb="foot"]{',
      '    padding-left:0 !important;padding-right:0 !important;justify-content:center !important;gap:0 !important;}',
      '  html.eon-sb-collapsed [data-eon-sb="brand"] > div{justify-content:center !important;gap:0 !important;}',

      /* the main column follows the sidebar */
      '  #mainContentWrapper{transition:margin-left .3s ease;}',
      '  html.eon-sb-collapsed #mainContentWrapper{margin-left:' + RAIL + 'px !important;}',
      '  html.eon-sb-collapsed.eon-sb-norail #mainContentWrapper{margin-left:0 !important;}',

      /* peek: hovering the collapsed rail slides the menu back as a flyout */
      '  html.eon-sb-collapsed #sidebar{transition:width .25s ease;}',
      '  html.eon-sb-collapsed.eon-sb-peek #sidebar{width:var(--eon-sb-w,288px) !important;',
      '    box-shadow:8px 0 28px rgba(0,0,0,.14) !important;z-index:40 !important;}',
      '  html.eon-sb-collapsed.eon-sb-peek [data-eon-sb="panel"],',
      '  html.eon-sb-collapsed.eon-sb-peek [data-eon-sb="search"],',
      '  html.eon-sb-collapsed.eon-sb-peek [data-eon-sb="brandtext"],',
      '  html.eon-sb-collapsed.eon-sb-peek [data-eon-sb="foottext"]{display:block !important;}',
      '  html.eon-sb-collapsed.eon-sb-peek [data-eon-sb="panel"]{display:block !important;}',
      '  html.eon-sb-collapsed.eon-sb-peek [data-eon-sb="brand"],',
      '  html.eon-sb-collapsed.eon-sb-peek [data-eon-sb="foot"]{justify-content:space-between !important;}',
      /* the main column must NOT move while peeking — the flyout floats over it */
      '  html.eon-sb-collapsed.eon-sb-peek #mainContentWrapper{margin-left:' + RAIL + 'px !important;}',

      '}',

      /* a collapsed sidebar with no rail still needs a way back */
      '.eon-sb-reveal{position:fixed;top:14px;left:14px;z-index:60;width:34px;height:34px;',
      '  border:0;border-radius:10px;cursor:pointer;background:#2563eb;color:#fff;',
      '  box-shadow:0 4px 14px rgba(37,99,235,.35);display:none;align-items:center;justify-content:center;}',
      '@media (min-width:' + BREAKPOINT + 'px){html.eon-sb-collapsed.eon-sb-norail .eon-sb-reveal{display:flex;}}',
    ].join('\n');

    var s = document.createElement('style');
    s.id = 'eon-sb-css';
    s.setAttribute('data-eon', '');
    s.textContent = css;
    (document.head || document.documentElement).appendChild(s);
  }

  /* ---------- icons ---------- */
  var ICON_IN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/><path d="M15 9l-3 3 3 3"/></svg>';
  var ICON_OUT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/><path d="M13 9l3 3-3 3"/></svg>';

  /* ---------- find the ERP's parts once and tag them ---------- */
  function tag(sidebar) {
    var panel = sidebar.querySelector('#sidebarPanel');
    var rail = sidebar.querySelector('#sidebarRail');
    if (panel) panel.setAttribute('data-eon-sb', 'panel');
    if (rail) rail.setAttribute('data-eon-sb', 'rail');

    // the search box — found by its input, not by its classes
    var input = sidebar.querySelector('input[placeholder*="Search menu" i], input[placeholder*="Search" i]');
    if (input) {
      var box = input.closest('div');
      // climb to the padded wrapper, but never as far as the sidebar itself
      while (box && box.parentElement && box.parentElement !== sidebar) box = box.parentElement;
      if (box && box !== sidebar) box.setAttribute('data-eon-sb', 'search');
    }

    // the brand block is the first child; its text half is the div beside the logo
    var brand = sidebar.firstElementChild;
    if (brand && brand !== panel && brand !== rail) {
      brand.setAttribute('data-eon-sb', 'brand');
      var inner = brand.querySelector('div');
      if (inner) {
        var texts = inner.querySelectorAll(':scope > div');
        // the one WITHOUT an <img> is the name/subtitle pair
        Array.prototype.forEach.call(texts, function (t) {
          if (!t.querySelector('img') && t.textContent.trim()) t.setAttribute('data-eon-sb', 'brandtext');
        });
      }
    }

    // the footer block is the last child
    var foot = sidebar.lastElementChild;
    if (foot && foot !== panel && foot !== rail && foot !== brand) {
      foot.setAttribute('data-eon-sb', 'foot');
      var fi = foot.querySelectorAll('div');
      Array.prototype.forEach.call(fi, function (t) {
        if (!t.querySelector('img,svg,i') && t.textContent.trim() && !t.hasAttribute('data-eon-sb')) {
          t.setAttribute('data-eon-sb', 'foottext');
        }
      });
    }

    state.railed = !!rail;
    root.classList.toggle('eon-sb-norail', !rail);

    // remember the natural width so peek can restore it exactly
    var w = Math.round(sidebar.getBoundingClientRect().width);
    if (!root.classList.contains('eon-sb-collapsed') && w > RAIL) {
      state.width = w;
      root.style.setProperty('--eon-sb-w', w + 'px');
    } else if (!root.style.getPropertyValue('--eon-sb-w')) {
      root.style.setProperty('--eon-sb-w', state.width + 'px');
    }
  }

  /* ---------- the toggle ---------- */
  function isCollapsed() { return root.classList.contains('eon-sb-collapsed'); }

  function paintToggle() {
    var on = isCollapsed();
    Array.prototype.forEach.call(document.querySelectorAll('.eon-sb-toggle'), function (b) {
      b.innerHTML = on ? ICON_OUT : ICON_IN;
      b.setAttribute('aria-expanded', on ? 'false' : 'true');
      b.title = (on ? 'Expand the menu' : 'Collapse the menu') + '  (Ctrl+\\)';
      b.setAttribute('aria-label', b.title);
    });
  }

  function setCollapsed(on) {
    root.classList.toggle('eon-sb-collapsed', !!on);
    root.classList.remove('eon-sb-peek');
    remember(on ? 'collapsed' : 'expanded');
    paintToggle();
    // let the ERP's own charts and tables re-measure against the new width
    setTimeout(function () {
      try { window.dispatchEvent(new Event('resize')); } catch (e) {}
    }, 320);
  }

  function toggle() { setCollapsed(!isCollapsed()); }

  function mountToggle(sidebar) {
    if (sidebar.querySelector('.eon-sb-toggle')) return;
    var brand = sidebar.querySelector('[data-eon-sb="brand"]') || sidebar.firstElementChild;
    if (!brand) return;
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'eon-sb-toggle';
    b.setAttribute('data-eon', '');
    b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); toggle(); });
    brand.appendChild(b);

    // and a way back when there is no rail to click
    if (!document.querySelector('.eon-sb-reveal')) {
      var r = document.createElement('button');
      r.type = 'button';
      r.className = 'eon-sb-reveal';
      r.setAttribute('data-eon', '');
      r.innerHTML = ICON_OUT;
      r.title = 'Show the menu  (Ctrl+\\)';
      r.addEventListener('click', function (e) { e.preventDefault(); setCollapsed(false); });
      document.body.appendChild(r);
    }
    paintToggle();
  }

  /* ---------- peek on hover, only while collapsed ---------- */
  function peek(sidebar) {
    var t = null;
    var enter = function () {
      if (!isCollapsed() || innerWidth < BREAKPOINT) return;
      clearTimeout(t);
      t = setTimeout(function () { root.classList.add('eon-sb-peek'); }, 180);
    };
    var leave = function () {
      clearTimeout(t);
      t = setTimeout(function () { root.classList.remove('eon-sb-peek'); }, 220);
    };
    sidebar.addEventListener('mouseenter', enter);
    sidebar.addEventListener('mouseleave', leave);
    sidebar.addEventListener('focusin', enter);
  }

  /* ---------- keyboard ---------- */
  document.addEventListener('keydown', function (e) {
    if (!e.ctrlKey || e.altKey || e.metaKey) return;
    if (e.key !== '\\') return;
    var t = e.target;
    if (t && /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)) return;
    if (t && t.isContentEditable) return;
    e.preventDefault();
    toggle();
  });

  /* ---------- boot ---------- */
  function boot() {
    var sidebar = document.getElementById('sidebar');
    if (!sidebar) return false;
    styles();
    tag(sidebar);
    mountToggle(sidebar);
    peek(sidebar);
    state.ready = true;
    return true;
  }

  if (!boot()) {
    // the ERP renders server-side, but a slow page (or Alpine) can be late
    var tries = 0;
    var iv = setInterval(function () {
      if (boot() || ++tries > 40) clearInterval(iv);
    }, 100);
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  }

  /* ---------- a small public handle, for EON itself ---------- */
  window.EonSidebar = {
    collapse: function () { setCollapsed(true); },
    expand: function () { setCollapsed(false); },
    toggle: toggle,
    collapsed: isCollapsed,
    ready: function () { return state.ready; },
  };
})();
