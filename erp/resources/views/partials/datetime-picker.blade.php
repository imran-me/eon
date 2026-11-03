{{--
    One field, date + optional time (owner, 2026-09-30).

    Clicking the box or its icon opens a calendar with a time row beneath it.
    Picking a day alone gives "2026-10-04"; touching the time gives
    "2026-10-04 14:30". Typing either form works too, as does 04/10/2026 or
    04/10/2026 14:30. The browser's own datetime-local box cannot do this: it
    throws the whole value away when the time is left blank.

    What it upgrades, and only on pages that include this partial:
      - every <input type="datetime-local">  (those columns already hold a time)
      - every <input data-dt>                (a date box whose column now does)
    Accounting dates (purchase / sale / payment / due) are deliberately NOT
    marked: they stay date-only so period reports cannot shift (owner's choice).

    Midnight is read as "no time given": a stored 00:00:00 shows as the date
    alone, so date-only rows migrated to DATETIME look exactly as before.

    Rollout is module by module — include this where a module's operational
    date boxes live. Safe to include more than once.
--}}
@once
<link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">
<style>
    .flatpickr-calendar{z-index:100000 !important;}
    /* Time panel BESIDE the calendar (owner, 2026-09-30): a big time you can
       type into, hour and minute as chips (no scrolling), 24h by default and
       AM/PM only in 12h. The box always stores 24h "Y-m-d H:i" — the mode
       only changes how the panel reads. */
    .flatpickr-calendar.epal-dt-cal{width:512px !important;padding-right:204px !important;box-sizing:border-box;border-radius:14px;border:1px solid #e2e8f0;box-shadow:0 20px 45px rgba(15,23,42,.16);}
    .flatpickr-calendar.epal-dt-cal .flatpickr-months,.flatpickr-calendar.epal-dt-cal .flatpickr-innerContainer{width:307.875px;}
    .flatpickr-calendar.epal-dt-cal .flatpickr-rContainer,.flatpickr-calendar.epal-dt-cal .flatpickr-days,.flatpickr-calendar.epal-dt-cal .dayContainer{width:307.875px;min-width:307.875px;max-width:307.875px;}
    .flatpickr-calendar.epal-dt-cal .flatpickr-next-month{right:204px !important;}
    .flatpickr-calendar.epal-dt-cal .flatpickr-prev-month:hover svg,.flatpickr-calendar.epal-dt-cal .flatpickr-next-month:hover svg{fill:#4f46e5;}
    .flatpickr-calendar.epal-dt-cal .flatpickr-current-month{font-size:14px;font-weight:700;color:#1e293b;}
    .flatpickr-calendar.epal-dt-cal .flatpickr-current-month .flatpickr-monthDropdown-months{font-weight:700;}
    .flatpickr-calendar.epal-dt-cal span.flatpickr-weekday{font-size:11px;font-weight:600;color:#94a3b8;}
    .flatpickr-calendar.epal-dt-cal .flatpickr-day{border-radius:10px;color:#334155;}
    .flatpickr-calendar.epal-dt-cal .flatpickr-day:hover{background:#eef2ff;border-color:#eef2ff;color:#3730a3;}
    .flatpickr-calendar.epal-dt-cal .flatpickr-day.today{border-color:#a5b4fc;}
    .flatpickr-calendar.epal-dt-cal .flatpickr-day.selected,.flatpickr-calendar.epal-dt-cal .flatpickr-day.selected:hover{background:#4f46e5;border-color:#4f46e5;color:#fff;box-shadow:0 4px 12px rgba(79,70,229,.35);}
    .flatpickr-calendar.epal-dt-cal .flatpickr-day.prevMonthDay,.flatpickr-calendar.epal-dt-cal .flatpickr-day.nextMonthDay{color:#cbd5e1;}
    .flatpickr-calendar.epal-dt-cal:before,.flatpickr-calendar.epal-dt-cal:after{display:none;}   /* the pointer would lie once it is moved */
    .epal-dt-side{position:absolute;top:0;right:0;bottom:0;width:204px;border-left:1px solid #eef2f7;background:linear-gradient(180deg,#f8faff 0%,#fff 38%);border-radius:0 14px 14px 0;display:flex;flex-direction:column;padding:11px;box-sizing:border-box;font-size:12px;color:#334155;}
    .epal-dt-side .top{display:flex;align-items:center;gap:8px;}
    .epal-dt-side .clock{flex:1;min-width:0;height:36px;display:flex;align-items:center;justify-content:center;gap:2px;padding:0 4px;box-sizing:border-box;background:#eef2ff;border:1px solid transparent;border-radius:10px;cursor:text;}
    .epal-dt-side .clock:focus-within{background:#fff;border-color:#818cf8;box-shadow:0 0 0 3px rgba(79,70,229,.14);}
    .epal-dt-side .clock.bad{border-color:#f87171;box-shadow:0 0 0 3px rgba(248,113,113,.18);}
    .epal-dt-side .clock .typ{flex:1;min-width:0;height:100%;background:transparent !important;border:0 !important;box-shadow:none !important;font-variant-numeric:tabular-nums;}
    .epal-dt-side .clock .apx{margin-left:4px;border:0;background:#4f46e5;color:#fff;border-radius:6px;font-size:10px;font-weight:800;padding:2px 5px;cursor:pointer;}
    .epal-dt-side .typ{flex:1;min-width:0;box-sizing:border-box;height:36px;border:1px solid transparent;border-radius:10px;background:#eef2ff;color:#3730a3;font-size:19px;font-weight:800;letter-spacing:.5px;text-align:center;padding:0 6px;}
    .epal-dt-side .typ::placeholder{color:#a5b4fc;font-weight:700;}
    .epal-dt-side .typ:focus{outline:none;border-color:#818cf8;background:#fff;box-shadow:0 0 0 3px rgba(79,70,229,.14);}
    .epal-dt-side .mode{display:flex;flex-direction:column;gap:2px;background:#f1f5f9;border-radius:8px;padding:2px;}
    .epal-dt-side .mode button{border:0;background:transparent;border-radius:6px;padding:2px 6px;font-size:10px;font-weight:700;color:#64748b;cursor:pointer;line-height:1.4;}
    .epal-dt-side .mode button.on{background:#fff;color:#4f46e5;box-shadow:0 1px 3px rgba(15,23,42,.12);}
    .epal-dt-side .lbl{display:flex;align-items:center;justify-content:space-between;margin:10px 0 4px;font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:#94a3b8;}
    .epal-dt-side .ampm{display:inline-flex;background:#f1f5f9;border-radius:7px;padding:2px;gap:2px;}
    .epal-dt-side .ampm button{border:0;background:transparent;border-radius:5px;padding:1px 7px;font-size:10px;font-weight:700;color:#64748b;cursor:pointer;letter-spacing:0;}
    .epal-dt-side .ampm button.on{background:#4f46e5;color:#fff;}
    .epal-dt-side .grid{display:grid;gap:3px;}
    .epal-dt-side .g-h24{grid-template-columns:repeat(6,1fr);}
    .epal-dt-side .g-h12{grid-template-columns:repeat(4,1fr);}
    .epal-dt-side .g-m{grid-template-columns:repeat(6,1fr);}
    .epal-dt-side .grid button{height:22px;border:0;border-radius:7px;background:#f8fafc;color:#475569;font-size:11.5px;font-weight:600;cursor:pointer;padding:0;transition:background .12s,color .12s;}
    .epal-dt-side .grid button:hover{background:#eef2ff;color:#4338ca;}
    .epal-dt-side .grid button.on{background:#4f46e5;color:#fff;box-shadow:0 3px 8px rgba(79,70,229,.3);}
    .epal-dt-side .foot{margin-top:auto;display:flex;justify-content:space-between;padding-top:6px;}
    .epal-dt-side .foot button{border:0;background:transparent;border-radius:7px;padding:4px 8px;font-size:11px;font-weight:700;cursor:pointer;}
    .epal-dt-side .foot .now{color:#4f46e5;}
    .epal-dt-side .foot .now:hover{background:#eef2ff;}
    .epal-dt-side .foot .clr{color:#94a3b8;}
    .epal-dt-side .foot .clr:hover{background:#f1f5f9;color:#475569;}
    input.epal-dt{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2'/%3E%3Cpath d='M16 2v4M8 2v4M3 10h18'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;padding-right:28px !important;cursor:pointer;}
</style>
<script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}"></script>
<script>
(function () {
    if (window.EpalDateTime || typeof window.flatpickr !== 'function') return;

    var SELECTOR = 'input[type="datetime-local"], input[data-dt]';
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };

    // Any accepted spelling → "Y-m-d" or "Y-m-d H:i" ("" when unreadable).
    function parse(raw) {
        var s = String(raw == null ? '' : raw).trim().replace('T', ' ');
        if (!s) return null;
        var m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?:\s+(\d{1,2}):(\d{2})(?::\d{2})?)?$/)
             || s.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})(?:\s+(\d{1,2}):(\d{2}))?$/);
        if (!m) return null;
        var y, mo, d;
        if (m[1].length === 4) { y = +m[1]; mo = +m[2]; d = +m[3]; } else { d = +m[1]; mo = +m[2]; y = +m[3]; }
        var hasTime = m[4] != null && !(+m[4] === 0 && +m[5] === 0);
        var date = new Date(y, mo - 1, d, hasTime ? +m[4] : 0, hasTime ? +m[5] : 0);
        if (isNaN(date.getTime()) || date.getDate() !== d) return null;
        return { date: date, hasTime: hasTime };
    }
    function format(date, hasTime) {
        var s = date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
        return hasTime ? s + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes()) : s;
    }
    function normalise(raw) { var p = parse(raw); return p ? format(p.date, p.hasTime) : String(raw || ''); }

    function upgrade(el) {
        if (el._flatpickr || el.dataset.dtReady) return;
        el.dataset.dtReady = '1';
        var min = parse(el.getAttribute('min')), max = parse(el.getAttribute('max'));
        el.removeAttribute('min'); el.removeAttribute('max');
        el.value = normalise(el.value || el.getAttribute('value'));
        el.type = 'text';
        el.autocomplete = 'off';
        el.classList.add('epal-dt');
        if (!el.placeholder) el.placeholder = 'YYYY-MM-DD hh:mm';

        var hasTime = /\d:\d\d$/.test(el.value);
        var h = 0, mi = 0;
        var cur = parse(el.value);
        if (cur && cur.hasTime) { h = cur.date.getHours(); mi = cur.date.getMinutes(); }
        var side = null;
        var mode = function () { var m = null; try { m = localStorage.getItem('epalDtMode'); } catch (e) {} return m === '12' ? 12 : 24; };
        var fp = window.flatpickr(el, {
            enableTime: false, allowInput: true, disableMobile: true, closeOnSelect: false,
            minDate: min ? format(min.date, false) : null,
            maxDate: max ? format(max.date, false) : null,
            dateFormat: 'Y-m-d H:i',
            parseDate: function (str) {
                var p = parse(str); if (!p) return undefined;
                hasTime = p.hasTime;
                if (p.hasTime) { h = p.date.getHours(); mi = p.date.getMinutes(); }
                return p.date;
            },
            formatDate: function (date) { return format(date, hasTime); },
            onOpen: function (_, __, inst) {
                // Other scripts set .value directly; start from what the box shows.
                var p = parse(el.value);
                if (p) { hasTime = p.hasTime; if (p.hasTime) { h = p.date.getHours(); mi = p.date.getMinutes(); } inst.setDate(p.date, false); }
                paint();
                // flatpickr has placed it; keep it inside the window.
                setTimeout(fit, 0);
            },
            onMonthChange: function () { setTimeout(fit, 0); },
            onYearChange: function () { setTimeout(fit, 0); },
            onReady: function (_, __, inst) {
                inst.calendarContainer.classList.add('epal-dt-cal');
                side = document.createElement('div');
                side.className = 'epal-dt-side';
                inst.calendarContainer.appendChild(side);
                side.addEventListener('mousedown', function (e) { if (e.target.tagName !== 'INPUT') e.preventDefault(); });
                side.addEventListener('click', function (e) {
                    var b = e.target.closest('button'); if (!b) return;
                    if (b.dataset.mode) { try { localStorage.setItem('epalDtMode', b.dataset.mode); } catch (x) {} paint(); return; }
                    if (b.dataset.flip) { apPm = !apPm; if (hasTime) { h = (h % 12) + (apPm ? 12 : 0); apply(); } else b.textContent = apPm ? 'PM' : 'AM'; return; }
                    if (b.dataset.clr) { hasTime = false; apply(); return; }
                    if (b.dataset.now) { var n = new Date(); h = n.getHours(); mi = n.getMinutes(); hasTime = true; fp.setDate(n, false); apply(); fp.close(); return; }
                    if (b.dataset.h != null) { if (!hasTime) mi = 0; h = +b.dataset.h; }
                    if (b.dataset.m != null) mi = +b.dataset.m;
                    if (b.dataset.ap) h = (h % 12) + (b.dataset.ap === 'pm' ? 12 : 0);
                    hasTime = true; apply();
                });
                // ONE field, colon always shown: "--:--" fills as you type
                // (1-:-- → 14:-- → 14:3- → 14:30). Backspace removes the last
                // digit, ":" after one digit pads it, A / P switch in 12h,
                // Enter commits + closes, a pasted "2:30 pm" is read whole.
                side.addEventListener('keydown', function (e) {
                    var t = e.target; if (!t.classList || !t.classList.contains('typ')) return;
                    var k = e.key, twelve = mode() === 12;
                    if (k === 'Enter') { e.preventDefault(); if (commit()) fp.close(); return; }
                    if (k === 'Tab' || k === 'Escape' || e.ctrlKey || e.metaKey || e.altKey) return;
                    e.preventDefault();
                    if (/^\d$/.test(k)) {
                        if (fresh) digits = '';   // typing over a shown time starts a new one
                        if (digits.length < 4) {
                            var d = +k;
                            if (digits.length === 0 && d > (twelve ? 1 : 2)) digits = '0' + k;
                            else if (digits.length === 2 && d > 5) digits += '0' + k;
                            else digits += k;
                        }
                    } else if (k === 'Backspace' || k === 'Delete') {
                        digits = digits.slice(0, -1);
                    } else if ((k === ':' || k === '.' || k === ' ') && digits.length === 1) {
                        digits = '0' + digits;
                    } else if (twelve && /^[aApP]$/.test(k)) {
                        apPm = k.toLowerCase() === 'p';
                        var ax = side.querySelector('.apx'); if (ax) ax.textContent = apPm ? 'PM' : 'AM';
                    }
                    fresh = false;
                    showMask(t);
                });
                // Phones / IME send input without a usable key: rebuild from the digits.
                side.addEventListener('input', function (e) {
                    var t = e.target; if (!t.classList || !t.classList.contains('typ')) return;
                    digits = t.value.replace(/\D/g, '').slice(0, 4);
                    showMask(t);
                });
                side.addEventListener('paste', function (e) {
                    var t = e.target; if (!t.classList || !t.classList.contains('typ')) return;
                    e.preventDefault();
                    var m = String((e.clipboardData || window.clipboardData).getData('text') || '').trim().toLowerCase()
                        .match(/^(\d{1,2})(?::?(\d{2}))?\s*(am|pm|a|p)?$/);
                    if (!m) return;
                    var hh = +m[1];
                    if (m[3]) { apPm = m[3].charAt(0) === 'p'; if (mode() === 24) hh = (hh % 12) + (apPm ? 12 : 0); }
                    digits = pad(hh) + (m[2] || '00');
                    showMask(t);
                });
                side.addEventListener('focusin', function (e) { if (e.target.classList && e.target.classList.contains('typ')) { fresh = !!digits; showMask(e.target); } });
                // Clicking away from the field keeps what was typed.
                side.addEventListener('focusout', function (e) {
                    if (!painting && e.target.classList && e.target.classList.contains('typ') && digits !== shownDigits) commit();
                });
                paint();
            },
            onChange: function (dates) { if (dates[0]) { el.value = format(withTime(dates[0]), hasTime); paint(); } },
        });
        // Never past the window's edge (owner, 2026-09-30): below the box if
        // it fits, else above it, else pinned 8px inside the window; the same
        // for left / right. Re-checked on resize while open.
        function fit() {
            if (!fp || !fp.isOpen) return;
            var cal = fp.calendarContainer, gap = 8;
            var vw = document.documentElement.clientWidth, vh = window.innerHeight;
            var box = el.getBoundingClientRect(), r = cal.getBoundingClientRect();
            var top = r.top, left = r.left;
            if (r.bottom > vh - gap) top = box.top - r.height - 6 >= gap ? box.top - r.height - 6 : Math.max(gap, vh - r.height - gap);
            if (r.top < gap) top = gap;
            if (r.right > vw - gap) left = vw - gap - r.width;
            if (left < gap) left = gap;
            // style.top/left are in the calendar's positioning context.
            var ctx = cal.offsetParent && cal.offsetParent !== document.body && cal.offsetParent !== document.documentElement
                ? cal.offsetParent.getBoundingClientRect() : { top: -window.pageYOffset, left: -window.pageXOffset };
            if (top !== r.top) cal.style.top = (top - ctx.top) + 'px';
            if (left !== r.left) { cal.style.right = 'auto'; cal.style.left = (left - ctx.left) + 'px'; }
        }
        window.addEventListener('resize', function () { setTimeout(fit, 0); });
        function withTime(d) { var x = new Date(d.getTime()); x.setHours(hasTime ? h : 0, hasTime ? mi : 0, 0, 0); return x; }
        function apply() {
            var p = parse(el.value);
            var base = fp.selectedDates[0] || (p && p.date) || new Date();
            var d = withTime(base);
            fp.setDate(d, false);
            el.value = format(d, hasTime);
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            paint();
        }
        // The typed digits -> the time. None = date only; "14" = 14:00;
        // "143" = 14:30. Out of range marks the box red and stays open.
        var digits = '', shownDigits = '', apPm = h >= 12, painting = false, fresh = false;
        function showMask(t) {
            var c = function (i) { return digits.charAt(i) || '-'; };
            t.value = digits ? c(0) + c(1) + ':' + c(2) + c(3) : '';
            var pos = [0, 1, 3, 4, 5][digits.length];
            try { t.setSelectionRange(pos, pos); } catch (x) {}
            var box = t.closest('.clock'); if (box) box.classList.remove('bad');
        }
        function commit() {
            if (!digits) { hasTime = false; apply(); return true; }
            var twelve = mode() === 12;
            var hh = digits.length === 1 ? +digits : +digits.slice(0, 2);
            var mm = digits.length > 2 ? +(digits.charAt(2) + (digits.charAt(3) || '0')) : 0;
            var ok = mm <= 59 && (twelve ? hh >= 1 && hh <= 12 : hh <= 23);
            if (!ok) { var box = side && side.querySelector('.clock'); if (box) box.classList.add('bad'); return false; }
            h = twelve ? (hh % 12) + (apPm ? 12 : 0) : hh;
            mi = mm; hasTime = true; apply();
            return true;
        }
        function paint() {
            if (!side) return;
            var twelve = mode() === 12;
            var pm = h >= 12, h12 = h % 12 === 0 ? 12 : h % 12;
            if (hasTime) apPm = pm;
            digits = shownDigits = hasTime ? pad(twelve ? h12 : h) + pad(mi) : '';
            var chip = function (attr, v, label, on) {
                return '<button type="button" ' + attr + '="' + v + '"' + (on ? ' class="on"' : '') + '>' + label + '</button>';
            };
            var hrs = twelve ? [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11] : Array.from({ length: 24 }, function (_, i) { return i; });
            var mins = []; for (var k = 0; k < 60; k += 5) mins.push(k);
            painting = true;
            side.innerHTML =
                '<div class="top">'
                + '<div class="clock"><input class="typ" type="text" inputmode="numeric" autocomplete="off" placeholder="--:--" value="'
                + (digits ? digits.slice(0, 2) + ':' + digits.slice(2) : '') + '" title="Type the time — the colon stays put. Enter to set.">'
                + (twelve ? '<button type="button" class="apx" data-flip="1" title="Switch AM / PM (or press A / P)">' + (apPm ? 'PM' : 'AM') + '</button>' : '')
                + '</div>'
                + '<span class="mode"><button type="button" data-mode="24"' + (twelve ? '' : ' class="on"') + '>24h</button>'
                + '<button type="button" data-mode="12"' + (twelve ? ' class="on"' : '') + '>12h</button></span>'
                + '</div>'
                + '<div class="lbl"><span>Hour</span>'
                + (twelve ? '<span class="ampm">' + chip('data-ap', 'am', 'AM', hasTime && !pm) + chip('data-ap', 'pm', 'PM', hasTime && pm) + '</span>' : '')
                + '</div>'
                + '<div class="grid ' + (twelve ? 'g-h12' : 'g-h24') + '">' + hrs.map(function (x) {
                    var v = twelve ? (x % 12) + (pm ? 12 : 0) : x;
                    return chip('data-h', v, twelve ? x : pad(x), hasTime && v === h);
                }).join('') + '</div>'
                + '<div class="lbl"><span>Minute</span></div>'
                + '<div class="grid g-m">' + mins.map(function (x) { return chip('data-m', x, ':' + pad(x), hasTime && x === mi); }).join('') + '</div>'
                + '<div class="foot"><button type="button" class="now" data-now="1">Now</button>'
                + '<button type="button" class="clr" data-clr="1">Date only</button></div>';
            painting = false;
        }
        // Clearing the box clears the time choice with it.
        el.addEventListener('input', function () { if (!el.value.trim()) { hasTime = false; paint(); } });
        return fp;
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        if (root.matches && root.matches(SELECTOR)) upgrade(root);
        root.querySelectorAll(SELECTOR).forEach(upgrade);
    }

    window.EpalDateTime = { upgrade: upgrade, scan: scan, normalise: normalise };

    var start = function () {
        scan(document);
        // Cards, legs and modals built by script after load get the same box.
        new MutationObserver(function (list) {
            list.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) scan(n); }); });
        }).observe(document.body, { childList: true, subtree: true });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
</script>
@endonce
