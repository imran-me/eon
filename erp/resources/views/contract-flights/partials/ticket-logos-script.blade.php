{{-- Airline logos in the via-route ticket pills (.mfp-tk-air[data-airline]).
     JS only, no <script> tag: included inside a script that already has
     FD_LOGO_BASE and airlineBrand() (airline-brands-script) in scope —
     passenger-sheet-list (Flights & Passengers) and the Flight Dashboard. --}}
// A small airline logo in each ticket's pill on the table (owner, 2026-10-03:
// "add logos like small flag"). Same airlineBrand() match as the sheet. The
// mark files carry wide empty margins, so each is trimmed to its ink once
// (canvas, same-origin) and the trimmed copy reused; any failure keeps the
// untrimmed file, and a missing file leaves the code alone.
const TK_INK = new Map(), TK_DONE = new Map();
function tkTrim(src) {
    if (TK_INK.has(src)) return TK_INK.get(src);
    const job = new Promise(resolve => {
        const im = new Image();
        im.onload = () => {
            try {
                const w = im.naturalWidth, h = im.naturalHeight, c = document.createElement('canvas');
                c.width = w; c.height = h;
                const x = c.getContext('2d'); x.drawImage(im, 0, 0);
                const d = x.getImageData(0, 0, w, h).data;
                let l = w, t = h, r = -1, b = -1;
                for (let y = 0; y < h; y++) for (let i = 0; i < w; i++) {
                    const k = (y * w + i) * 4;
                    if (d[k + 3] > 24 && !(d[k] > 240 && d[k + 1] > 240 && d[k + 2] > 240)) {
                        if (i < l) l = i; if (i > r) r = i; if (y < t) t = y; if (y > b) b = y;
                    }
                }
                if (r < 0) return resolve(src);
                const s = Math.max(r - l, b - t) + 1, o = document.createElement('canvas');
                o.width = o.height = Math.min(s, 64);
                o.getContext('2d').drawImage(c, l - (s - (r - l + 1)) / 2, t - (s - (b - t + 1)) / 2, s, s, 0, 0, o.width, o.height);
                resolve(o.toDataURL('image/png'));
            } catch (_) { resolve(src); }
        };
        im.onerror = () => resolve(null);
        im.src = src;
    });
    TK_INK.set(src, job);
    return job;
}
window.mfpTicketLogos = function (root) {
    if (typeof airlineBrand !== 'function') return;
    (root || document).querySelectorAll('.mfp-tk-air[data-airline]:not([data-logo])').forEach(el => {
        el.dataset.logo = '1';
        const brand = airlineBrand(el.dataset.airline);
        if (!brand.slug) return;
        tkTrim(`${FD_LOGO_BASE}/${brand.slug}.png`).then(url => {
            if (!url) return;
            const img = document.createElement('img');
            img.src = url; img.alt = '';
            el.prepend(img);
        });
    });
};
