{{-- Behaviour for partials/table-bar — a port of the bank feed's bar code
     (banks/dashboard.blade.php: initCompanyFilter, applyCompanyRange,
     parseLedgerDate, markLedgerDateBad, paintCompanyCount). Included inside
     a <script> block. Every name is ps-prefixed. --}}

function psYmd(d) {
    const pad = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
}
function psDaysBack(n) { const d = new Date(); d.setDate(d.getDate() - n); return psYmd(d); }
// Y-m-d -> dd/mm/yyyy, as text, so no browser locale can reorder it.
function psDMY(ymd) {
    const m = String(ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
}
// dd/mm/yyyy in, Y-m-d out. '' for an empty box, null for something unreadable —
// the caller must tell those apart: empty is a cleared bound, null is a typo.
function psParseDate(str) {
    const t = String(str || '').trim();
    if (!t) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(t)) return t;
    const m = t.match(/^(\d{1,2})\D(\d{1,2})\D(\d{4})$/) || t.match(/^(\d{2})(\d{2})(\d{4})$/);
    if (!m) return null;
    const d = m[1].padStart(2, '0'), mo = m[2].padStart(2, '0');
    if (+d < 1 || +d > 31 || +mo < 1 || +mo > 12) return null;
    return m[3] + '-' + mo + '-' + d;
}
function psMarkBad(text, bad) {
    text.classList.toggle('is-bad', bad);
    const wrap = text.closest('.pb-date');
    if (wrap) wrap.classList.toggle('is-bad', bad);
}
function psSetDateBoxes(prefix, from, to) {
    [[prefix + 'From', prefix + 'FromText', from], [prefix + 'To', prefix + 'ToText', to]].forEach(([nativeId, textId, ymd]) => {
        const native = document.getElementById(nativeId);
        const text   = document.getElementById(textId);
        if (native) native.value = ymd || '';
        if (text) { text.value = ymd ? psDMY(ymd) : ''; psMarkBad(text, false); }
    });
}
function psPaintSeg(prefix, key) {
    document.querySelectorAll('#' + prefix + 'Seg button').forEach(b => b.classList.toggle('is-on', b.dataset.range === key));
    const dates = document.getElementById(prefix + 'Dates');
    if (dates) dates.classList.toggle('is-open', key === 'custom');
}
// "12 of 87 rows" — only while a search is live; a count of everything is not
// information.
function psPaintCount(prefix, searching, shown, total) {
    const el = document.getElementById(prefix + 'Count');
    if (!el) return;
    el.style.display = searching ? '' : 'none';
    if (!searching) return;
    el.classList.toggle('is-none', shown === 0);
    el.textContent = shown === 0
        ? 'No match in ' + total + ' row' + (total === 1 ? '' : 's')
        : shown + ' of ' + total + ' row' + (total === 1 ? '' : 's');
}
// ── Print: the chooser and row picking ──────────────────────────────────────
// Manage Bank's print, table for table (banks/dashboard.blade.php:
// openPrintDialog, startPicking, paintSelectionStrip). Print opens a chooser —
// today, yesterday, a day, a range, the ticked rows, or what the table shows —
// and the sheet is built server-side from the whole ledger, so a day outside
// the feed's fourteen days still prints. Ticked ids win over dates on the
// server, so a selection prints the same whatever the period boxes say.
//
// A table opts in with psPrintSetup(prefix, cfg):
//   host        the element .is-picking goes on — the table or its wrapper;
//               every pickable row is a tr[data-pick-id][data-date] under it
//               with a <td class="ps-pick"><input class="ps-pick-box"> cell
//   dates       true when the table has a period (the day/range choices)
//   noun        what a row is — 'transactions', 'agents' — for the labels
//   period()    {from, to} the table shows now (Y-m-d or ''); a table with no
//               dates returns whatever its url() needs for "what is shown"
//   searching() true while a search narrows the rows: "what the table shows"
//               is then sent as the shown ids, not as the period
//   rows()      every row the table holds, as [id, date] — data, not screen;
//               only needed with dates (Select whole day)
//   url(q)      the sheet's URL for {from, to, ids} (or period()'s shape),
//               or null when nothing can print
//   ready()     optional; false while there is nothing loaded to print
//   onPicks()   optional; called after a change so a table that draws its own
//               rows can redraw them
const PS_PRINT = {}, PS_PICKS = {}, PS_PICKING = {};

function psPrintSetup(prefix, cfg) {
    PS_PRINT[prefix]   = cfg;
    PS_PICKS[prefix]   = PS_PICKS[prefix] || new Set();
    PS_PICKING[prefix] = false;
    const link = document.getElementById(prefix + 'Print');
    if (link) link.addEventListener('click', e => { e.preventDefault(); psPrintOpen(prefix); });
    const sel = document.getElementById(prefix + 'SelPrint');
    if (sel) sel.addEventListener('click', e => {
        e.preventDefault();
        const ids = Array.from(PS_PICKS[prefix]);
        const u = ids.length ? cfg.url({ from: '', to: '', ids }) : null;
        if (u) window.open(u, '_blank');
    });
    if (cfg.host) {
        // Capture phase: while picking, a click on the row body toggles its box
        // and the row's own action (opening a statement) never runs.
        cfg.host.addEventListener('click', e => psRowPickClick(prefix, e), true);
        cfg.host.addEventListener('change', e => {
            const box = e.target.closest ? e.target.closest('input.ps-pick-box') : null;
            if (!box) return;
            if (box.classList.contains('ps-pick-all')) { psPickAll(prefix, box.checked); return; }
            const tr = box.closest('tr[data-pick-id]');
            if (tr) psTogglePick(prefix, Number(tr.dataset.pickId), box.checked);
        });
    }
    const day = document.getElementById(prefix + 'SelDay');
    if (day && !cfg.dates) day.style.display = 'none';
    psPaintPicks(prefix);
}

function psRowPickClick(prefix, e) {
    if (!PS_PICKING[prefix]) return;
    const t  = e.target;
    const tr = t.closest ? t.closest('tr[data-pick-id]') : null;
    if (!tr) return;
    // The box handles itself (its change event); the row's own controls —
    // links, buttons — keep their meaning. Only a click on the row body toggles.
    if (t.closest('input.ps-pick-box')) { e.stopPropagation(); return; }
    if (t.closest('a, button, label')) return;
    e.stopPropagation(); e.preventDefault();
    const box = tr.querySelector('input.ps-pick-box');
    psTogglePick(prefix, Number(tr.dataset.pickId), box ? !box.checked : !PS_PICKS[prefix].has(Number(tr.dataset.pickId)));
}

function psTogglePick(prefix, id, on) {
    if (!(id > 0)) return;
    if (on) PS_PICKS[prefix].add(id); else PS_PICKS[prefix].delete(id);
    psSyncPicks(prefix);
}

// The rows on screen for a table, as [id, date] — read from the DOM so "what
// is shown" is literally what is shown, search and range included. A table
// that draws only a page of its rows supplies shown() itself.
function psShownRows(prefix) {
    const cfg = PS_PRINT[prefix];
    if (cfg.shown) return cfg.shown();
    if (!cfg.host) return [];
    return Array.from(cfg.host.querySelectorAll('tr[data-pick-id]'))
        .filter(tr => getComputedStyle(tr).display !== 'none')
        .map(tr => [Number(tr.dataset.pickId), tr.dataset.date || ''])
        .filter(([id]) => id > 0);
}

function psPickAll(prefix, on) {
    const set = PS_PICKS[prefix];
    psShownRows(prefix).forEach(([id]) => on ? set.add(id) : set.delete(id));
    psSyncPicks(prefix);
}

// Every row that shares a day with a ticked row. Reads the full list, not the
// screen: a search may be hiding the rest of that day, and "whole day" means
// the whole day.
function psPickWholeDay(prefix) {
    const cfg = PS_PRINT[prefix], set = PS_PICKS[prefix];
    if (!set.size || !cfg.rows) return;
    const all  = cfg.rows();
    const days = new Set(all.filter(([id]) => set.has(id)).map(([, d]) => d));
    all.forEach(([id, d]) => { if (days.has(d)) set.add(id); });
    psSyncPicks(prefix);
}

function psPickClear(prefix) { PS_PICKS[prefix].clear(); psSyncPicks(prefix); }

// The boxes on screen follow the Set, then the strip and the buttons are
// repainted. Tables that draw their own rows also get onPicks().
function psSyncPicks(prefix) {
    const cfg = PS_PRINT[prefix], set = PS_PICKS[prefix];
    if (cfg.host) cfg.host.querySelectorAll('tr[data-pick-id]').forEach(tr => {
        const on  = set.has(Number(tr.dataset.pickId));
        const box = tr.querySelector('input.ps-pick-box');
        if (box) box.checked = on;
        tr.classList.toggle('is-picked', on);
    });
    if (cfg.onPicks) cfg.onPicks();
    psPaintPicks(prefix);
}

// The strip, the head tick and the Print links for one table.
function psPaintPicks(prefix) {
    const cfg = PS_PRINT[prefix], set = PS_PICKS[prefix], n = set.size;
    const strip = document.getElementById(prefix + 'Sel');
    const count = document.getElementById(prefix + 'SelN');
    if (strip) strip.classList.toggle('is-on', !!PS_PICKING[prefix]);
    if (count) count.textContent = String(n);
    const all = cfg.host ? cfg.host.querySelector('input.ps-pick-all') : null;
    if (all) {
        const shown  = psShownRows(prefix);
        const picked = shown.filter(([id]) => set.has(id)).length;
        all.checked = shown.length > 0 && picked === shown.length;
        all.indeterminate = picked > 0 && picked < shown.length;
    }
    const link = document.getElementById(prefix + 'Print');
    if (link) {
        link.classList.toggle('has-sel', n > 0);
        link.innerHTML = `<i class="fas fa-print"></i> ${n ? `Print (${n} ticked)` : 'Print'}`;
    }
    const sel = document.getElementById(prefix + 'SelPrint');
    if (sel) { sel.style.opacity = n ? '' : '.5'; sel.style.pointerEvents = n ? '' : 'none'; }
}

// Picking mode: the tick boxes and the strip exist only while it is on. It is
// entered from Print → "Selected …" and left by Cancel.
function psStartPicking(prefix) {
    PS_PICKING[prefix] = true;
    const cfg = PS_PRINT[prefix];
    if (cfg.host) cfg.host.classList.add('is-picking');
    psSyncPicks(prefix);
    const strip = document.getElementById(prefix + 'Sel');
    if (strip && strip.scrollIntoView) strip.scrollIntoView({ block: 'center', behavior: 'smooth' });
}
function psStopPicking(prefix) {
    PS_PICKING[prefix] = false;
    PS_PICKS[prefix].clear();
    const cfg = PS_PRINT[prefix];
    if (cfg.host) cfg.host.classList.remove('is-picking');
    psSyncPicks(prefix);
}

// The chooser. Print asks what to print rather than guessing: today, yesterday,
// one day, a date range, the ticked rows, or exactly what the table is showing.
// A table without dates offers the last two.
function psPrintOpen(prefix) {
    const cfg = PS_PRINT[prefix];
    if (!cfg) return;
    if (cfg.ready && !cfg.ready()) {
        if (window.Swal) Swal.fire({ icon: 'info', title: 'Nothing to print yet', text: cfg.readyHint || 'Load the table first.' });
        return;
    }
    if (!window.Swal) { const u = cfg.url(cfg.period()); if (u) window.open(u, '_blank'); return; }
    // A page may bring its own, bigger chooser (Party Statement's print designer).
    if (cfg.studio) { cfg.studio(prefix); return; }

    const picks   = PS_PICKS[prefix];
    const n       = picks.size;
    const noun    = cfg.noun || 'rows';
    const period  = cfg.period() || {};
    const today   = psYmd(new Date());
    const yday    = psDaysBack(1);
    const shownLabel = cfg.shownLabel ? cfg.shownLabel() : (period.from && period.to
        ? (period.from === period.to ? psDMY(period.from) : `${psDMY(period.from)} – ${psDMY(period.to)}`)
        : (period.from ? `from ${psDMY(period.from)}` : period.to ? `up to ${psDMY(period.to)}` : 'all time'));
    const initial = n ? 'selected' : 'shown';

    const opt = (value, label, hint, extra = '') => `
        <label class="pd-opt">
            <input type="radio" name="pdMode" value="${value}" ${value === initial ? 'checked' : ''}>
            <span class="pd-body">
                <span class="pd-label">${label}</span>
                ${hint ? `<span class="pd-hint">${hint}</span>` : ''}
                ${extra}
            </span>
        </label>`;
    const dateBox = (id, value) => `<input type="date" id="${id}" value="${value}" class="pd-date" onfocus="this.closest('.pd-opt').querySelector('input[type=radio]').checked = true">`;

    Swal.fire({
        title: cfg.title || 'Print ' + noun,
        html: `
            <style>
                .pd-list { display:flex; flex-direction:column; gap:6px; text-align:left; }
                .pd-opt { display:flex; gap:10px; align-items:flex-start; padding:9px 11px; border:1px solid #e2e8f0; border-radius:8px; cursor:pointer; }
                .pd-opt:has(input:checked) { border-color:#6366f1; background:#f5f6ff; }
                .pd-opt input[type=radio] { margin-top:3px; accent-color:#4f46e5; }
                .pd-body { display:flex; flex-direction:column; gap:3px; flex:1; }
                .pd-label { font-size:13px; font-weight:700; color:#1e293b; }
                .pd-hint { font-size:11px; color:#64748b; }
                .pd-dates { display:flex; gap:8px; align-items:center; margin-top:4px; font-size:11px; color:#64748b; }
                .pd-date { padding:5px 8px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px; color:#1e293b; }
                .pd-note { margin-top:2px; text-align:left; }
                .pd-note-label { display:block; font-size:11px; font-weight:600; color:#475569; margin-bottom:4px; }
                .pd-note-box { width:100%; min-height:56px; padding:8px 10px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px; font-family:inherit; color:#1e293b; resize:vertical; box-sizing:border-box; }
                .pd-note-select { width:100%; padding:8px 10px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px; font-family:inherit; color:#1e293b; box-sizing:border-box; margin-bottom:6px; background:#fff; }
            </style>
            <div class="pd-list">
                ${cfg.dates ? opt('range', 'A date range', '', `<div class="pd-dates">${dateBox('pdFrom', period.from || today)} <span>to</span> ${dateBox('pdTo', period.to || today)}</div>`) : ''}
                ${opt('selected', n ? `Selected ${noun} (${n})` : `Selected ${noun}`,
                      n ? 'Only the rows you ticked in the table' : 'Tick the rows you want in the table')}
                ${opt('shown', 'What the table shows now', shownLabel)}
            </div>
            ${cfg.note ? `<div class="pd-note">
                <label class="pd-note-label" for="pdNote">Note on the printed statement</label>
                ${cfg.noteTemplates ? `<select id="pdNoteTemplate" class="pd-note-select">
                    ${Object.keys(cfg.noteTemplates).map(key => `<option value="${key}">${cfg.noteTemplateLabels[key]}</option>`).join('')}
                    <option value="custom">Custom — write your own</option>
                </select>` : ''}
                <textarea id="pdNote" class="pd-note-box" placeholder="e.g. Please settle the balance by 30 Sep.">${cfg.noteTemplates ? cfg.noteTemplates[Object.keys(cfg.noteTemplates)[0]] : ''}</textarea>
            </div>` : ''}`,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-print"></i> Print',
        confirmButtonColor: '#4f46e5',
        focusConfirm: false,
        width: 560,
        // With nothing ticked yet, choosing "Selected …" IS the action: the
        // chooser closes on the click and the table switches to picking mode.
        didOpen: () => {
            const radio = document.querySelector('input[name=pdMode][value=selected]');
            if (radio && !n) radio.addEventListener('change', () => { if (radio.checked) { Swal.close(); psStartPicking(prefix); } });

            // Picking a template fills the textarea with it — still a normal
            // editable box after that, so a template is a starting point, not
            // a locked choice. "Custom" just clears it for a blank slate.
            const templateSelect = document.getElementById('pdNoteTemplate');
            const noteBox = document.getElementById('pdNote');
            if (templateSelect && noteBox && cfg.noteTemplates) {
                templateSelect.addEventListener('change', () => {
                    const key = templateSelect.value;
                    noteBox.value = key === 'custom' ? '' : (cfg.noteTemplates[key] || '');
                    if (key === 'custom') noteBox.focus();
                });
            }
        },
        preConfirm: () => {
            const mode = (document.querySelector('input[name=pdMode]:checked') || {}).value;
            let from = '', to = '', ids = [];
            if      (mode === 'today')     { from = to = today; }
            else if (mode === 'yesterday') { from = to = yday; }
            else if (mode === 'day') {
                from = to = document.getElementById('pdDay').value;
                if (!from) { Swal.showValidationMessage('Pick the day.'); return false; }
            }
            else if (mode === 'range') {
                from = document.getElementById('pdFrom').value;
                to   = document.getElementById('pdTo').value;
                if (!from && !to) { Swal.showValidationMessage('Pick a start or an end date.'); return false; }
                if (from && to && from > to) { const t = from; from = to; to = t; }
            }
            else if (mode === 'selected') { ids = Array.from(picks); }
            const note = cfg.note ? (document.getElementById('pdNote').value || '').trim() : '';
            return { mode, from, to, ids, note };
        },
    }).then(res => {
        if (!res.isConfirmed) return;
        const v = res.value;
        if (v.mode === 'selected' && !v.ids.length) { psStartPicking(prefix); return; }
        let q;
        if (v.mode === 'shown') {
            // A live search narrows the rows; the sheet then lists exactly
            // those, by id. Otherwise the period (or the segment) the table
            // shows, as the server understands it.
            q = (cfg.searching && cfg.searching())
                ? { from: '', to: '', ids: psShownRows(prefix).map(([id]) => id) }
                : Object.assign({ from: '', to: '', ids: [] }, period);
        } else {
            q = { from: v.from, to: v.to, ids: v.ids };
        }
        q.note = v.note;
        const url = cfg.url(q);
        if (url) window.open(url, '_blank');
    });
}

// Wire one bar.
//   onSearch(term)          the lower-cased search text changed
//   onRange(key, from, to)  a segment was clicked or dates applied; key is the
//                           segment's data-range or 'custom'; from/to Y-m-d or ''
//   ranges                  {key: () => [from, to]} — what each segment means
//   window                  {from, to} — the hard bounds custom dates are pulled
//                           back to, or null for unbounded
function psInitBar(prefix, { onSearch, onRange, ranges = {}, window: win = null, initial = null } = {}) {
    const box  = document.getElementById(prefix + 'Search');
    const wrap = document.getElementById(prefix + 'SearchWrap');
    if (box && onSearch) {
        let t = null;
        const run = () => { wrap.classList.toggle('has-q', box.value.trim() !== ''); onSearch(box.value.trim().toLowerCase()); };
        box.addEventListener('input', () => { clearTimeout(t); t = setTimeout(run, 120); });
        box.addEventListener('keydown', e => { if (e.key === 'Escape') { box.value = ''; clearTimeout(t); run(); } });
        const clear = document.getElementById(prefix + 'Clear');
        if (clear) clear.addEventListener('click', () => { box.value = ''; clearTimeout(t); run(); box.focus(); });
    }

    const fromEl = document.getElementById(prefix + 'From');
    const toEl   = document.getElementById(prefix + 'To');
    if (win && fromEl && toEl) { fromEl.min = toEl.min = win.from; fromEl.max = toEl.max = win.to; }

    const apply = key => {
        let from = '', to = '', clamped = false;
        if (key === 'custom') {
            from = fromEl ? fromEl.value : '';
            to   = toEl ? toEl.value : '';
            if (!from && !to) {
                // Clearing a previously applied custom range and pressing
                // Apply is a reset. This avoids requiring a page refresh.
                key = 'all';
                psSetDateBoxes(prefix, '', '');
            }
            if (key === 'custom') {
                if      (from && !to) to   = from;
                else if (to && !from) from = to;
                else if (from > to)   { const x = from; from = to; to = x; }
                if (win) {
                    if (from < win.from) { from = win.from; clamped = true; }
                    if (to   > win.to)   { to   = win.to;   clamped = true; }
                }
                psSetDateBoxes(prefix, from, to);
            }
        } else if (ranges[key]) {
            [from, to] = ranges[key]();
            psSetDateBoxes(prefix, '', '');
        }
        psPaintSeg(prefix, key);
        if (onRange) onRange(key, from, to, clamped);
    };

    document.querySelectorAll('#' + prefix + 'Seg button').forEach(b => b.addEventListener('click', () => apply(b.dataset.range)));
    const go = document.getElementById(prefix + 'Apply');
    if (go) go.addEventListener('click', () => apply('custom'));
    const reset = document.getElementById(prefix + 'DateReset');
    if (reset) reset.addEventListener('click', () => apply('all'));

    const applyIfAnyDate = () => { if ((fromEl && fromEl.value) || (toEl && toEl.value)) apply('custom'); };
    [fromEl, toEl].forEach(el => el && el.addEventListener('change', applyIfAnyDate));
    document.querySelectorAll('#' + prefix + 'Dates .pb-date').forEach(wrapEl => {
        wrapEl.addEventListener('click', e => {
            if (e.target !== wrapEl) return;
            const native = wrapEl.querySelector('.pb-date-native');
            if (native && native.showPicker) { try { native.showPicker(); } catch (_) {} }
        });
    });
    [[prefix + 'FromText', fromEl], [prefix + 'ToText', toEl]].forEach(([textId, native]) => {
        const text = document.getElementById(textId);
        if (!text || !native) return;
        const commit = () => {
            const ymd = psParseDate(text.value);
            if (ymd === null) { psMarkBad(text, true); return; }
            psMarkBad(text, false);
            native.value = ymd;
            text.value = ymd ? psDMY(ymd) : '';
            applyIfAnyDate();
        };
        text.addEventListener('change', commit);
        text.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); commit(); } });
    });

    if (initial) psPaintSeg(prefix, initial);
    return { apply };
}
