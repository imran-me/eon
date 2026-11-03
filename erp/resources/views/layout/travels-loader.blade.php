@once
{{-- Travels loader (owner, 2026-10-07; across all of Travels same day; full-screen scene
     2026-10-08: "more fun, across the white page"): a sky wash, drifting clouds, dotted
     world routes with pulsing city pins, planes crossing the screen with contrails, and the
     EPAL TRAVELS logo with its orbiting plane in the middle. CSS only.
     Included only by the Travels tab bars (ticketing, contract flight, contract file, visa,
     flight category); @once keeps it single when a page has two.
     Never sticks: hidden on DOMContentLoaded / pageshow (back button), skipped for
     downloads, new tabs and in-page links, and dropped after 12s whatever happens. --}}
@php
    try { $tkLogo = optional(\App\Models\Company::find(2))->logo; } catch (\Throwable $e) { $tkLogo = null; }
    $tkLogoUrl = $tkLogo ? asset($tkLogo) : null;
    $tkPlane = '<path fill="currentColor" d="M21 16v-2l-8-5V3.5A1.5 1.5 0 0 0 11.5 2 1.5 1.5 0 0 0 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>';
@endphp
<div id="tkLoader" class="tkl is-on" aria-hidden="true">
    <div class="tkl-sky"></div>
    <div class="tkl-center">
        <div class="tkl-stage">
            <div class="tkl-glow"></div>
            <div class="tkl-ring"></div>
            <div class="tkl-ring r2"></div>
            <div class="tkl-orbit"><svg class="tkl-plane" viewBox="0 0 24 24" aria-hidden="true">{!! $tkPlane !!}</svg></div>
            <div class="tkl-logo">
                @if($tkLogoUrl)<img src="{{ $tkLogoUrl }}" alt="" onerror="this.replaceWith(Object.assign(document.createElement('b'),{textContent:'EPAL TRAVELS'}))">@else<b>EPAL TRAVELS</b>@endif
            </div>
        </div>
    </div>
</div>
<style>
    .tkl{position:fixed;inset:0;z-index:99990;overflow:hidden;opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s;}
    .tkl.is-on{opacity:1;visibility:visible;}
    .tkl-sky{position:absolute;inset:0;background:radial-gradient(1200px 600px at 50% 45%,rgba(255,255,255,.94),rgba(238,242,255,.92) 55%,rgba(224,231,255,.94));backdrop-filter:blur(4px);}
    .tkl-cloud{position:absolute;left:-220px;width:180px;height:56px;border-radius:60px;background:#fff;opacity:1;filter:drop-shadow(0 0 1px rgba(99,102,241,.45)) drop-shadow(0 10px 18px rgba(79,70,229,.22));animation:tkl-drift linear infinite;}
    .tkl-cloud::before,.tkl-cloud::after{content:'';position:absolute;background:#fff;border-radius:50%;}
    .tkl-cloud::before{width:80px;height:80px;top:-38px;left:28px;}
    .tkl-cloud::after{width:60px;height:60px;top:-26px;left:92px;}
    .tkl-cloud.c1{top:12%;animation-duration:14s;}
    .tkl-cloud.c2{top:32%;scale:.7;animation-duration:19s;animation-delay:-6s;}
    .tkl-cloud.c3{top:68%;scale:1.1;animation-duration:17s;animation-delay:-11s;}
    .tkl-cloud.c4{top:86%;scale:.6;animation-duration:22s;animation-delay:-3s;}
    @keyframes tkl-drift{to{left:calc(100% + 220px);}}
    .tkl-route{position:absolute;inset:0;width:100%;height:100%;}
    .tkl-route path{fill:none;stroke:#a5b4fc;stroke-width:2;stroke-dasharray:2 10;stroke-linecap:round;vector-effect:non-scaling-stroke;animation:tkl-dash 1.2s linear infinite;}
    .tkl-route .r2{stroke:#c4b5fd;animation-duration:1.8s;}
    @keyframes tkl-dash{to{stroke-dashoffset:-24;}}
    .tkl-pin{position:absolute;width:12px;height:12px;margin:-6px 0 0 -6px;border-radius:50%;background:#6366f1;box-shadow:0 0 0 4px rgba(99,102,241,.18);}
    .tkl-pin::after{content:'';position:absolute;inset:-4px;border-radius:50%;border:2px solid #818cf8;animation:tkl-ping 1.6s ease-out infinite;}
    .tkl-pin.p1{left:6%;top:78%;} .tkl-pin.p2{left:96%;top:23%;background:#8b5cf6;} .tkl-pin.p3{left:4%;top:27%;background:#0ea5e9;} .tkl-pin.p4{left:98%;top:70%;background:#10b981;}
    .tkl-pin.p2::after{animation-delay:.4s;} .tkl-pin.p3::after{animation-delay:.8s;} .tkl-pin.p4::after{animation-delay:1.2s;}
    @keyframes tkl-ping{from{transform:scale(.6);opacity:1;}to{transform:scale(2.6);opacity:0;}}
    .tkl-fly{position:absolute;left:-150px;display:flex;align-items:center;animation:tkl-cross linear infinite;}
    .tkl-fly svg{width:30px;height:30px;color:#4f46e5;transform:rotate(90deg);filter:drop-shadow(0 4px 6px rgba(79,70,229,.35));}
    .tkl-fly i{display:block;width:110px;height:2px;border-radius:2px;background:linear-gradient(90deg,transparent,rgba(99,102,241,.55));margin-right:-4px;}
    .tkl-fly.f1{top:20%;animation-duration:3.2s;}
    .tkl-fly.f2{top:58%;animation-duration:4.4s;animation-delay:-1.6s;} .tkl-fly.f2 svg{width:22px;height:22px;color:#7c3aed;}
    .tkl-fly.f3{top:86%;animation-duration:5.4s;animation-delay:-3s;} .tkl-fly.f3 svg{width:18px;height:18px;color:#0ea5e9;}
    @keyframes tkl-cross{0%{left:-150px;translate:0 0;}50%{translate:0 -18px;}100%{left:calc(100% + 40px);translate:0 0;}}
    .tkl-center{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);display:flex;flex-direction:column;align-items:center;gap:14px;}
    .tkl-stage{position:relative;width:170px;height:170px;}
    .tkl-glow{position:absolute;inset:18px;border-radius:50%;background:radial-gradient(circle,rgba(129,140,248,.35),transparent 70%);animation:tkl-pulse 1.6s ease-in-out infinite;}
    .tkl-ring{position:absolute;inset:8px;border-radius:50%;border:2px dashed #a5b4fc;animation:tkl-spin 12s linear infinite reverse;}
    .tkl-ring.r2{inset:-10px;border:1px dotted #c4b5fd;animation-duration:20s;animation-direction:normal;}
    .tkl-logo{position:absolute;inset:40px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;
        box-shadow:0 10px 30px rgba(79,70,229,.25),0 0 0 1px #e0e7ff;animation:tkl-pulse 1.6s ease-in-out infinite;}
    .tkl-logo img{width:78%;height:78%;object-fit:contain;}
    .tkl-logo b{font-size:10px;font-weight:800;letter-spacing:.06em;color:#4f46e5;text-align:center;line-height:1.2;padding:4px;}
    .tkl-orbit{position:absolute;inset:0;animation:tkl-spin 1.8s linear infinite;}
    .tkl-plane{position:absolute;top:-4px;left:50%;margin-left:-14px;width:28px;height:28px;color:#4f46e5;transform:rotate(90deg);filter:drop-shadow(0 3px 4px rgba(79,70,229,.35));}
    .tkl-text{font-size:13px;font-weight:700;letter-spacing:.04em;color:#4338ca;}
    .tkl-text span{animation:tkl-blink 1.2s infinite;} .tkl-text span:nth-child(2){animation-delay:.2s;} .tkl-text span:nth-child(3){animation-delay:.4s;}
    @keyframes tkl-blink{0%,100%{opacity:.15;}50%{opacity:1;}}
    .tkl-bar{width:200px;height:4px;border-radius:4px;background:rgba(99,102,241,.15);overflow:hidden;}
    .tkl-bar i{display:block;width:40%;height:100%;border-radius:4px;background:linear-gradient(90deg,#6366f1,#a855f7);animation:tkl-load 1.1s ease-in-out infinite;}
    @keyframes tkl-load{0%{transform:translateX(-100%);}100%{transform:translateX(260%);}}
    #tklMsg{font-weight:700;transition:opacity .25s;}
    @keyframes tkl-spin{to{transform:rotate(360deg);}}
    @keyframes tkl-pulse{50%{transform:scale(1.05);}}
    html.dark .tkl-sky{background:radial-gradient(1200px 600px at 50% 45%,rgba(30,41,59,.95),rgba(15,23,42,.96));}
    html.dark .tkl-cloud,html.dark .tkl-cloud::before,html.dark .tkl-cloud::after{background:#334155;}
    html.dark .tkl-logo{background:#1e293b;box-shadow:0 0 0 1px #334155;} html.dark .tkl-text{color:#c7d2fe;}
    @media (prefers-reduced-motion: reduce){.tkl *{animation-duration:8s!important;}}
</style>
<script>
(function () {
    const el = document.getElementById('tkLoader'); if (!el) return;
    let timer;
    // Cover only the page area (owner, 2026-10-08): below the header, right of the
    // sidebar — the menu and header stay usable and visible. Falls back to full screen.
    function fit() {
        const m = document.querySelector('main'), h = document.querySelector('header');
        if (!m) return;
        const r = m.getBoundingClientRect(), top = Math.max(0, h ? h.getBoundingClientRect().bottom : r.top);
        Object.assign(el.style, { left: Math.max(0, r.left) + 'px', top: top + 'px', width: r.width + 'px', bottom: '0', right: 'auto', height: 'auto' });
    }
    fit();
    window.addEventListener('resize', fit);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fit);
    const show = () => { fit(); el.classList.add('is-on'); clearTimeout(timer); timer = setTimeout(hide, 12000); };
    function hide() { el.classList.remove('is-on'); clearTimeout(timer); }
    timer = setTimeout(hide, 12000);
    // at least one lap of the plane, so a fast page still shows the loader instead of a flicker
    const t0 = performance.now(), done = () => setTimeout(hide, Math.max(0, 900 - (performance.now() - t0)));
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', done); else done();
    window.addEventListener('pageshow', e => { if (e.persisted) hide(); });   // back/forward cache only — it also fires on every normal load
    // a new status line every 1.4s while it is up
    const msgs = ['Preparing your journey', 'Checking the seats', 'Fuelling up', 'Clearing for take-off', 'Fetching your tickets', 'Almost there'];
    let mi = 0;
    setInterval(() => {
        if (!el.classList.contains('is-on')) return;
        const m = document.getElementById('tklMsg'); if (!m) return;
        m.style.opacity = 0;
        setTimeout(() => { mi = (mi + 1) % msgs.length; m.textContent = msgs[mi]; m.style.opacity = 1; }, 250);
    }, 1400);
    const skip = /export|download|pdf|excel|xlsx|csv|print/i;
    document.addEventListener('click', e => {
        const a = e.target.closest && e.target.closest('a[href]');
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const href = a.getAttribute('href') || '';
        if (!href || href[0] === '#' || /^(javascript|mailto|tel):/i.test(href) || a.target === '_blank' || a.hasAttribute('download') || skip.test(href)) return;
        const u = new URL(a.href, location.href);
        if (u.origin !== location.origin || (u.pathname === location.pathname && u.search === location.search && u.hash)) return;
        // a handler that cancels the click later (modal openers) must not leave it up
        setTimeout(() => { if (!e.defaultPrevented) show(); }, 0);
    });
    document.addEventListener('submit', e => {
        const f = e.target;
        setTimeout(() => {
            if (e.defaultPrevented || f.target === '_blank' || skip.test(f.action || '') || (f.method || 'get').toLowerCase() === 'get' && f.closest('[data-tfb-swap], .tfb')) return;
            show();
        }, 0);
    });
})();
</script>
@endonce
