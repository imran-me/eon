/* ===========================================================================
   WOOD ART INTERIORS — ambient scene
   ---------------------------------------------------------------------------
   Builds the SVG room and drives one custom property, --p (0 → 1), from the
   scroll position of the host. The stylesheet does everything else, so the only
   per-frame work here is a single scrollTop read and one property write.

   Mounts into [data-wa-scene] and reads scroll from it. Touches nothing else in
   the document, and is only loaded by the interior views.
   ======================================================================== */
(function () {
    'use strict';

    var VB_W = 1600, VB_H = 900;

    /* One interior ELEVATION — the straight-on drawing an interior designer
       would actually present — rather than a forced-perspective room. Realism
       here is proportion, not element count: a long low credenza on tapered
       legs, framed art hung at eye height above it, a window that stops above
       a sill the way real windows do, curtains that run past it to the floor,
       one pendant. Everything sits on a single datum line (the floor, y=700)
       and casts a shadow there, which is what grounds it. */
    function roomSVG() {
        var i, boards = '', motes = '';

        /* Floorboards — a few faint horizontals under the floor line. */
        var ys = [724, 756, 796, 844];
        for (i = 0; i < ys.length; i++) {
            boards += '<line x1="0" y1="' + ys[i] + '" x2="' + VB_W + '" y2="' + ys[i] + '" />';
        }

        /* Dust motes, drifting up through the pendant's light. */
        var pts = [[872, 470], [918, 548], [858, 606], [936, 646], [896, 386]];
        for (i = 0; i < pts.length; i++) {
            motes += '<circle class="wa-mote" cx="' + pts[i][0] + '" cy="' + pts[i][1] + '" r="' + (2 + (i % 2)) + '" />';
        }

        return '' +
        '<svg class="wa-room" viewBox="0 0 ' + VB_W + ' ' + VB_H + '" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">' +

          /* Datum lines: ceiling, floor, baseboard, floorboards, and one run of
             wall panel moulding. Always present, never animated. */
          '<g class="wa-struct">' +
            '<line x1="100" y1="84" x2="1500" y2="84" />' +
            '<line x1="60" y1="700" x2="1540" y2="700" />' +
            '<line x1="60" y1="682" x2="1540" y2="682" />' +
            '<rect x="130" y="150" width="230" height="430" rx="3" />' +
          '</g>' +
          '<g class="wa-floor">' + boards + '</g>' +

          /* Grounding shadows — what makes furniture sit ON the floor instead
             of floating near it. Deepen as the room gets built. */
          '<ellipse class="wa-shadow" cx="530" cy="703" rx="240" ry="10" />' +
          '<ellipse class="wa-shadow" cx="192" cy="703" rx="58" ry="8" />' +

          /* Window — glazing stops at a sill, wall continues below, the way a
             real window meets a real wall. 2×2 mullions, nothing more. */
          '<g>' +
            '<rect class="wa-daylight" x="1090" y="150" width="330" height="430" />' +
            '<polygon class="wa-daypool" points="1090,584 1420,584 1290,796 910,796" />' +
            '<g class="wa-line d1" style="--len:3200">' +
              '<rect x="1090" y="150" width="330" height="430" />' +
              '<path d="M1255 150 V580 M1090 365 H1420" />' +
              '<path d="M1072 584 H1438" />' +
              '<path d="M1056 132 H1452" />' +
            '</g>' +
          '</g>' +

          /* Curtain — hung from the rod, falling past the sill to the floor. */
          '<g>' +
            '<path class="wa-fill-soft" d="M1044 140 h74 c6 180 -10 340 4 516 l-86 6 c12 -180 2 -342 8 -522 z" />' +
            '<g class="wa-line d2" style="--len:2200">' +
              '<path d="M1044 140 c10 176 -6 348 -8 522" />' +
              '<path d="M1118 140 c8 176 -8 344 4 516" />' +
              '<path d="M1080 142 c6 170 -4 336 -2 500" />' +
            '</g>' +
          '</g>' +

          /* Credenza — the joinery this company builds. Slab top, three bays,
             finger-pull seams, slim legs. */
          '<g>' +
            '<rect class="wa-fill" x="300" y="560" width="460" height="120" rx="5" />' +
            '<g class="wa-line d3" style="--len:2200">' +
              '<rect x="300" y="560" width="460" height="120" rx="5" />' +
              '<path d="M453 560 V680 M607 560 V680" />' +
              '<path d="M441 608 v22 M619 608 v22" />' +
              '<path d="M322 680 v22 M738 680 v22" />' +
            '</g>' +
          '</g>' +

          /* On the credenza: a vase with sprigs, two books. Enough to read as
             lived-in, few enough to stay a drawing. */
          '<g class="wa-line d4" style="--len:900">' +
            '<path d="M368 560 c-5 -26 3 -42 12 -48 c9 6 17 22 12 48 z" />' +
            '<path d="M380 512 v-36 M380 496 c-13 -9 -21 -22 -21 -36 M380 496 c13 -9 21 -22 21 -36" />' +
            '<rect x="636" y="548" width="84" height="12" rx="2" />' +
            '<rect x="646" y="536" width="64" height="12" rx="2" />' +
          '</g>' +

          /* Framed art at eye height: frame, mat, one gesture inside. */
          '<g class="wa-line d2" style="--len:1900">' +
            '<rect x="440" y="250" width="180" height="240" rx="2" />' +
            '<rect x="458" y="268" width="144" height="204" />' +
            '<path d="M480 438 C516 356 550 358 584 298" />' +
          '</g>' +

          /* Pendant — cable, dome, bulb; its light warms the room mid-scroll. */
          '<g>' +
            '<ellipse class="wa-lamp-pool" cx="880" cy="700" rx="160" ry="26" />' +
            '<polygon class="wa-lamp-cone" points="846,308 914,308 1005,700 755,700" />' +
            '<g class="wa-motes">' + motes + '</g>' +
            '<circle class="wa-bulb" cx="880" cy="314" r="8" />' +
            '<g class="wa-line d1" style="--len:700">' +
              '<path d="M880 84 V262" />' +
              '<path d="M842 300 a38 38 0 0 1 76 0 z" />' +
            '</g>' +
          '</g>' +

          /* Floor plant by the panelled wall. */
          '<g>' +
            '<path class="wa-fill" d="M150 612 h84 l-10 86 h-64 z" />' +
            '<g class="wa-line d5" style="--len:1100">' +
              '<path d="M150 612 h84 l-10 86 h-64 z" />' +
              '<path d="M192 612 v-92" />' +
              '<path d="M192 544 c-36 -8 -54 -36 -52 -68 c32 2 52 28 52 68 z" />' +
              '<path d="M192 524 c36 -10 54 -38 50 -70 c-32 4 -50 30 -50 70 z" />' +
            '</g>' +
          '</g>' +

        '</svg>';
    }

    function sceneHTML() {
        return '' +
        '<div class="wa-scene">' +
          '<div class="wa-wash"><span class="w1"></span><span class="w2"></span><span class="w3"></span></div>' +
          roomSVG() +
        '</div>';
    }

    function mount() {
        var host = document.querySelector('[data-wa-scene]');
        if (!host) return;
        if (host.querySelector('.wa-scene')) return;

        host.insertAdjacentHTML('afterbegin', sceneHTML());
        var scene = host.querySelector('.wa-scene');
        if (!scene) return;

        /* The scroller is the host itself when it scrolls internally, otherwise
           the page. Resolving it once keeps the scroll handler to one read. */
        var scroller = host.scrollHeight > host.clientHeight + 4 ? host : null;

        var max = 0;
        function measure() {
            max = scroller
                ? scroller.scrollHeight - scroller.clientHeight
                : (document.documentElement.scrollHeight - window.innerHeight);
        }
        function progress() {
            var top = scroller ? scroller.scrollTop : (window.scrollY || window.pageYOffset || 0);
            var p = max > 6 ? Math.min(1, Math.max(0, top / max)) : 0;
            scene.style.setProperty('--p', p.toFixed(3));
        }

        measure();
        progress();
        requestAnimationFrame(function () { scene.classList.add('on'); });

        (scroller || window).addEventListener('scroll', progress, { passive: true });
        window.addEventListener('resize', function () { measure(); progress(); }, { passive: true });
        /* Content height can settle a beat after first paint. */
        setTimeout(function () { measure(); progress(); }, 400);

        /* Stop burning frames on a tab nobody is looking at. */
        document.addEventListener('visibilitychange', function () {
            scene.classList.toggle('paused', document.hidden);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }
})();
