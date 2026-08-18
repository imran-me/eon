/* ============================================================
   EON · embed — one script tag puts EON on the ERP.

     <script src="https://eon.gulfrabit.com/embed/eon-embed.js" defer></script>

   It adds three things to a page it does not own:
     1. the companion — EON walks on the ERP, same as before
     2. the brain + the ERP domain, fed by the EON server
     3. a button that opens EON's own panel in a new tab

   It touches nothing else: the stylesheet is a pointer-events:none
   overlay with --eon-* variables only, so the ERP stays pixel for
   pixel what it was, and every element it creates is prefixed
   `eon-` and carries data-eon so it can be found and removed.

   Options (set before this script, or as data- attributes on the tag):
     window.EON_EMBED = {
       server:  'https://eon.gulfrabit.com/server/api',
       panel:   'https://eon.gulfrabit.com/eon/',
       company: null,          // scope EON to one company id
       button:  true,          // show the "EON panel" button
       avatar:  true,          // show the walking companion
       position:'bottom-left', // bottom-left | bottom-right | top-right
     }
   ============================================================ */
(function () {
  'use strict';
  if (window.__EON_EMBED_LOADED) return;                 // one EON per page
  window.__EON_EMBED_LOADED = true;

  const tag = document.currentScript || Array.prototype.slice.call(document.querySelectorAll('script[src*="eon-embed"]')).pop();
  const here = new URL(tag && tag.src ? tag.src : location.href, location.href);
  const BASE = here.href.replace(/\/embed\/[^/]*$/, '');   // …/eon-embed.js → the site root
  const d = (k, f) => (tag && tag.dataset && tag.dataset[k] != null ? tag.dataset[k] : f);
  const O = Object.assign({
    server: BASE + '/server/api',
    panel: BASE + '/eon/',
    company: null,
    button: true,
    avatar: true,
    position: 'bottom-left',
  }, window.EON_EMBED || {});
  O.panel = d('panel', O.panel); O.server = d('server', O.server); O.position = d('position', O.position);
  if (d('button', null) === 'false') O.button = false;
  if (d('avatar', null) === 'false') O.avatar = false;
  if (d('company', null) != null) O.company = +d('company', null);

  const el = (t, a) => { const n = document.createElement(t); Object.assign(n, a || {}); n.setAttribute('data-eon', ''); return n; };
  const head = document.head || document.documentElement;

  /* ---------- 1. the button: EON's own panel, in a new tab ---------- */
  function button() {
    if (!O.button || document.getElementById('eon-panel-btn')) return;
    // already docked beside EON in the workspace? then the button would be noise
    try { if (window.parent !== window && window.parent.EonWorkspace) return; } catch {}
    const pos = {
      'bottom-left': 'left:18px;bottom:18px',
      'bottom-right': 'right:18px;bottom:78px',
      'top-right': 'right:18px;top:78px',
    }[O.position] || 'left:18px;bottom:18px';
    const style = el('style');
    style.textContent = `
      #eon-panel-btn{position:fixed;${pos};z-index:2147483000;display:inline-flex;align-items:center;gap:8px;
        padding:10px 14px;border-radius:999px;border:0;cursor:pointer;text-decoration:none;
        font:600 13px/1 "Inter","Segoe UI",system-ui,sans-serif;color:#fff;
        background:linear-gradient(135deg,#4f46e5,#2f8bff);box-shadow:0 8px 24px rgba(31,109,255,.35);
        transition:transform .12s ease, box-shadow .12s ease}
      #eon-panel-btn:hover{transform:translateY(-1px);box-shadow:0 12px 30px rgba(31,109,255,.45)}
      #eon-panel-btn .eon-dot{width:8px;height:8px;border-radius:50%;background:#7ed957;box-shadow:0 0 8px #7ed957}
      #eon-panel-btn .eon-arrow{opacity:.85;font-weight:400}
      #eon-panel-alt{position:fixed;${pos};margin-left:96px;z-index:2147483000;
        display:inline-grid;place-items:center;width:30px;height:30px;border-radius:50%;text-decoration:none;
        background:#fff;color:#4f46e5;border:1px solid #e2e7f0;box-shadow:0 4px 14px rgba(19,26,46,.12);
        font:600 13px "Inter","Segoe UI",system-ui,sans-serif}
      #eon-panel-alt:hover{background:#f5f7fc}
      @media print{#eon-panel-alt{display:none}}
      @media print{#eon-panel-btn{display:none}}`;
    head.appendChild(style);
    const workspace = O.panel.replace(/\/?$/, '/') + 'workspace.html?to=' + encodeURIComponent(location.pathname + location.search);
    const a = el('a', { id: 'eon-panel-btn', href: workspace, title: 'Work with EON beside the ERP (right-click for a separate tab)' });
    a.innerHTML = '<span class="eon-dot"></span>EON <span class="eon-arrow">⇥</span>';
    // left click docks EON beside the ERP; middle/ctrl click opens the panel on its own
    a.addEventListener('click', (e) => {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;   // let the browser do its thing
      e.preventDefault();
      location.href = workspace;
    });
    const alt = el('a', { id: 'eon-panel-alt', href: O.panel, target: '_blank', rel: 'noopener', title: 'Open EON in a separate tab' });
    alt.textContent = '↗';
    (document.body || document.documentElement).appendChild(a);
    (document.body || document.documentElement).appendChild(alt);
  }

  /* ---------- 2. the companion ---------- */
  function companion() {
    if (!O.avatar) return;
    ['companion.css', 'home.css', 'animations.css'].forEach((f) => {
      if (document.querySelector(`link[href*="ai-companion/css/${f}"]`)) return;
      head.appendChild(el('link', { rel: 'stylesheet', href: `${BASE}/ai-companion/css/${f}` }));
    });

    // three.js is imported by name inside the companion modules — the map must exist
    // before any module runs, and a page may already have one of its own.
    if (!document.querySelector('script[type="importmap"]')) {
      const map = el('script', { type: 'importmap' });
      map.textContent = JSON.stringify({ imports: { three: `${BASE}/ai-companion/vendor/three.module.js` } });
      head.appendChild(map);
    }

    // the adapter is a classic script and must run before the modules ask for data
    const adapter = el('script', { src: `${BASE}/ai-companion/adapters/erp-adapter.js` });
    adapter.onload = () => {
      head.appendChild(el('script', { type: 'module', src: `${BASE}/ai-companion/js/boot.js` }));
      head.appendChild(el('script', { type: 'module', src: `${BASE}/ai-companion/eon-brain/voice.js` }));
      // the conversation in place: same brain, same actions, on the ERP page itself
      head.appendChild(el('script', { src: `${BASE}/embed/eon-ask.js`, defer: true }));
    };
    adapter.onerror = () => console.warn('[EON embed] adapter did not load from ' + BASE);
    head.appendChild(adapter);
  }

  /* ---------- 3. configuration the adapter reads ---------- */
  window.EON_CONFIG = Object.assign({ server: O.server, company: O.company, demo: false }, window.EON_CONFIG || {});
  window.EON_BRAIN_CONFIG = Object.assign({ space: 'erp', embedded: true, panel: O.panel }, window.EON_BRAIN_CONFIG || {});

  /* ---------- go ---------- */
  function go() { try { button(); } catch (e) { console.warn('[EON embed] button', e); } try { companion(); } catch (e) { console.warn('[EON embed] companion', e); } }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go, { once: true });
  else go();

  window.EonEmbed = { options: O, base: BASE, openPanel: () => window.open(O.panel, '_blank', 'noopener') };
})();
