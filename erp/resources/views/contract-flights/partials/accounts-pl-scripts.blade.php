{{-- Profit & Loss tab of Flight / Ticket / Visa Accounts. One filter (search,
     segment, dates) decides which rows count; the statement, by-agent table,
     losses, footers and months are all summed from window.PL_PAX for those
     rows, so every number on the tab agrees. Filter state lives in the URL
     query, so a reload or a shared link keeps it. Included inside a <script>
     after party-statement.partials.bar-scripts (psParseDate, psDMY,
     psMarkBad, psSetDateBoxes).

     Period (owner, 2026-10-15): the tab opens on the current month (#plTable
     data-now, the server's month). Every other month is a row of Past months
     (#plMonths); opening one copies the statement and the by-agent / losses
     cards into its .cfa-pl-mscope and paints them with that month alone. --}}
(function () {
    const table = document.getElementById('plTable');
    if (!table || !window.PL_PAX) return;
    const panel = document.querySelector('[data-panel="pl"]');
    const mainRows = [...table.tBodies[0].querySelectorAll(':scope > tr.cfa-pl-row')];
    // What a row and a unit are called on this page (Flight Accounts: flight / pax).
    const ROW = table.dataset.row || 'flight', UNIT = table.dataset.unit || 'pax';
    const plural = (n, w) => n + ' ' + w + (n === 1 || w === 'pax' ? '' : 's');
    const money = v => (v < 0 ? '−' : '') + '৳' + Math.round(Math.abs(v)).toLocaleString('en-US');
    const pct = (a, b) => b > 0 ? (a / b * 100).toFixed(1) + '%' : '—';
    const tone = v => v < 0 ? 'cfa-bad' : 'cfa-good';
    const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    const KEYS = ['package', 'billed', 'transfer', 'penalty', 'revenue', 'ticket_cost', 'service_cost', 'change_cost', 'cost', 'profit', 'agent_paid', 'agent_due', 'vendor_paid'];
    const sum = list => {
        const t = { pax: list.reduce((a, p) => a + (p.n || 1), 0), flights: new Set(list.map(p => p.fk)).size };
        KEYS.forEach(k => t[k] = list.reduce((a, p) => a + (+p[k] || 0), 0));
        return t;
    };

    // ── The period: this month unless the URL asks for another ──
    const NOW = table.dataset.now || new Date().toISOString().slice(0, 7);
    const lastDay = ym => { const [y, m] = ym.split('-').map(Number); return ym + '-' + String(new Date(y, m, 0).getDate()).padStart(2, '0'); };
    const monthName = ym => { const [y, m] = ym.split('-').map(Number); return new Date(y, m - 1, 1).toLocaleString('en-GB', { month: 'long', year: 'numeric' }); };
    const MONTH_FROM = NOW + '-01', MONTH_TO = lastDay(NOW);

    // Any row (main table or inside a month) opens its breakdown.
    document.querySelectorAll('tr.cfa-pl-row').forEach(r => {
        const toggle = () => {
            const d = r.nextElementSibling;
            if (!d || !d.classList.contains('cfa-pl-detail')) return;
            d.hidden = !d.hidden; r.classList.toggle('is-open', !d.hidden);
        };
        r.addEventListener('click', e => { if (e.target.closest('a, button')) return; toggle(); });
        r.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
    });
    // A past month opens its whole P&L beneath it, and the page scrolls there.
    document.querySelectorAll('tr.cfa-pl-month').forEach(r => {
        const toggle = () => {
            const d = r.nextElementSibling;
            d.hidden = !d.hidden; r.classList.toggle('is-open', !d.hidden);
            if (!d.hidden) { paintMonth(r); r.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        };
        r.addEventListener('click', e => { if (e.target.closest('a, button')) return; toggle(); });
        r.addEventListener('keydown', e => { if (e.target !== r) return; if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
    });

    // ── State, kept in the URL (no pl_from / pl_to = this month, pl_all = every month) ──
    let lastList = [], baseList = [];
    const qs = new URLSearchParams(location.search);
    const st = { q: qs.get('q') || '', seg: qs.get('seg') || 'all', from: qs.get('pl_from') || '', to: qs.get('pl_to') || '' };
    if (!st.from && !st.to && !qs.has('pl_all')) { st.from = MONTH_FROM; st.to = MONTH_TO; }
    const isThisMonth = () => st.from === MONTH_FROM && st.to === MONTH_TO;
    function saveState() {
        const p = new URLSearchParams(location.search);
        ['pl_from', 'pl_to', 'pl_all'].forEach(k => p.delete(k));
        [['q', st.q], ['seg', st.seg === 'all' ? '' : st.seg]].forEach(([k, v]) => v ? p.set(k, v) : p.delete(k));
        if (!st.from && !st.to) p.set('pl_all', '1');
        else if (!isThisMonth()) { if (st.from) p.set('pl_from', st.from); if (st.to) p.set('pl_to', st.to); }
        const q = p.toString();
        history.replaceState(null, '', location.pathname + (q ? '?' + q : '') + location.hash);
    }
    function periodLabel() {
        if (!st.from && !st.to) return 'All months';
        const m = st.from.slice(0, 7);
        if (st.from === m + '-01' && st.to === lastDay(m)) return monthName(m);
        return (st.from ? psDMY(st.from) : 'Start') + ' – ' + (st.to ? psDMY(st.to) : 'today');
    }
    const R = ROW.charAt(0).toUpperCase() + ROW.slice(1) + 's';
    const segLabel = { earned: table.dataset.earned || 'Flown (earned)', booked: table.dataset.booked || 'Not flown (booked)', profit: 'Profitable ' + ROW + 's', loss: 'Loss-making ' + ROW + 's', due: R + ' with agent due', incomplete: R + ' with cost incomplete' };
    const filterParts = () => [segLabel[st.seg], st.q ? 'matching “' + st.q + '”' : ''].filter(Boolean);

    // Search and segment narrow every period, the months included; the dates
    // only decide what the top of the tab shows.
    function matchBase(r) {
        if (st.q && !(r.dataset.search || '').includes(st.q)) return false;
        switch (st.seg) {
            case 'earned': if (r.dataset.earned !== '1') return false; break;
            case 'booked': if (r.dataset.earned === '1') return false; break;
            case 'profit': if (!(+r.dataset.profit > 0)) return false; break;
            case 'loss': if (!(+r.dataset.profit < 0)) return false; break;
            case 'due': if (!(+r.dataset.due > 0)) return false; break;
            case 'incomplete': if (!(+r.dataset.incomplete > 0)) return false; break;
        }
        return true;
    }
    function inPeriod(r) {
        const d = r.dataset.date || '';
        if (st.from && (d === '' || d < st.from)) return false;
        if (st.to && (d === '' || d > st.to)) return false;
        return true;
    }

    // The tab's own figures, never the copies inside an opened month.
    const own = (scope, el) => scope !== panel || !el.closest('.cfa-pl-mscope');
    const pick = (scope, sel) => [...scope.querySelectorAll(sel)].find(el => own(scope, el));
    function setAll(scope, key, text, tone) {
        scope.querySelectorAll('[data-pl="' + key + '"]').forEach(el => {
            if (!own(scope, el)) return;
            el.textContent = text;
            if (tone !== undefined) { el.classList.remove('cfa-good', 'cfa-bad'); if (tone) el.classList.add(tone); }
        });
    }

    function agentsHtml(list) {
        const byAgent = {};
        list.forEach(p => (byAgent[p.agent] = byAgent[p.agent] || []).push(p));
        const rows = Object.entries(byAgent).map(([name, ps]) => [name, sum(ps)]).sort((a, b) => b[1].profit - a[1].profit);
        return rows.length ? rows.map(([name, a]) =>
            `<tr class="cfa-dd-go" data-agent="${esc(name)}" title="Show what makes up this agent's figures"><td class="b">${esc(name)}</td><td class="num">${a.pax}</td><td class="num cfa-in">${money(a.revenue)}</td><td class="num cfa-out">${money(a.cost)}</td>`
            + `<td class="num ${tone(a.profit)}"><b>${money(a.profit)}</b></td><td class="num ${a.revenue > 0 ? tone(a.profit) : ''}">${pct(a.profit, a.revenue)}</td>`
            + `<td class="num ${a.agent_due > 0 ? 'cfa-due' : ''}">${money(a.agent_due)}</td></tr>`).join('')
            : '<tr><td colspan="7" class="cfa-empty">Nothing in this view.</td></tr>';
    }
    // Loss-making rows, worst first.
    function lossesHtml(list, rows) {
        const losses = rows.filter(r => +r.dataset.profit < 0).map(r => {
            const f = sum(list.filter(p => p.fk === r.dataset.fk));
            return [r.dataset.label || r.querySelector('td:nth-child(2) a')?.textContent || 'Off flight', f, r.dataset.fk];
        }).sort((a, b) => a[1].profit - b[1].profit);
        return losses.length ? losses.map(([n, f, fk]) =>
            `<tr class="cfa-dd-go" data-fk="${esc(fk)}" title="Open this row below"><td class="b">${esc(n)}</td><td class="num cfa-in">${money(f.revenue)}</td><td class="num cfa-out">${money(f.change_cost)}</td><td class="num cfa-bad"><b>${money(f.profit)}</b></td></tr>`).join('')
            : '<tr><td colspan="4" class="cfa-empty"><i class="fas fa-circle-check"></i>No loss-making ' + ROW + ' in this view.</td></tr>';
    }

    // One period's statement, cash strip, warning, by-agent and losses —
    // the tab's own (scope = panel) or an opened month's copy.
    function paint(scope, list, rows) {
        const t = sum(list);
        KEYS.forEach(k => setAll(scope, k, money(t[k]), k === 'profit' ? tone(t.profit) : undefined));
        setAll(scope, 'flights', t.flights); setAll(scope, 'pax', t.pax);
        setAll(scope, 'margin', pct(t.profit, t.revenue), t.revenue > 0 ? tone(t.profit) : '');
        const earned = sum(list.filter(p => p.earned)), booked = sum(list.filter(p => !p.earned));
        setAll(scope, 'earned_profit', money(earned.profit) + ' · ' + plural(earned.pax, UNIT), tone(earned.profit));
        setAll(scope, 'booked_profit', money(booked.profit) + ' · ' + plural(booked.pax, UNIT), tone(booked.profit));
        const cash = t.agent_paid - t.vendor_paid;
        setAll(scope, 'cash', money(cash), tone(cash));
        setAll(scope, 'vendor_due', money(list.reduce((a, p) => a + Math.max(0, p.ticket_cost + p.service_cost - p.vendor_paid), 0)));
        setAll(scope, 'net_changes', money(t.change_cost - t.billed), t.change_cost - t.billed > 0 ? 'cfa-bad' : 'cfa-good');

        // Cost incomplete: revenue in, ticket cost not — say so, and give the
        // margin on the rows whose costs are complete.
        const inc = list.filter(p => p.inc), clean = sum(list.filter(p => !p.inc));
        const warn = pick(scope, '.cfa-pls-warn');
        if (warn) warn.hidden = inc.length === 0;
        setAll(scope, 'inc_pax', inc.length);
        setAll(scope, 'inc_revenue', money(inc.reduce((a, p) => a + p.revenue, 0)));
        setAll(scope, 'clean_margin', pct(clean.profit, clean.revenue));

        const ab = pick(scope, '.cfa-pl-agents tbody'); if (ab) ab.innerHTML = agentsHtml(list);
        const lb = pick(scope, '.cfa-pl-losses tbody'); if (lb) lb.innerHTML = lossesHtml(list, rows);
        return t;
    }

    // An opened month: the tab's statement and side cards, copied once, then
    // painted with that month's rows (search and segment still apply).
    const monthList = month => baseList.filter(p => p.month === month);
    function paintMonth(mr) {
        const det = mr.nextElementSibling;
        const scope = det && det.querySelector('.cfa-pl-mscope');
        if (!scope) return;
        if (!scope.firstChild) {
            scope.dataset.label = mr.dataset.label || mr.dataset.month;
            [panel.querySelector('.cfa-pl-stmt'), panel.querySelector('.cfa-pl-side')].forEach(src => {
                if (!src) return;
                const c = src.cloneNode(true);
                c.removeAttribute('id');
                c.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));
                // all-months figures (books check, stock, not invoiced) stay on the tab
                c.querySelectorAll('.cfa-pls-gl, .cfa-pls-out').forEach(el => el.remove());
                scope.appendChild(c);
            });
            const h3 = scope.querySelector('.cfa-pl-stmt .cfa-card-h h3');
            if (h3) h3.textContent = 'Profit & loss · ' + scope.dataset.label;
        }
        const t = paint(scope, monthList(mr.dataset.month), [...det.querySelectorAll('tr.cfa-pl-row')].filter(r => !r.hidden));
        const sub = scope.querySelector('.cfa-pl-stmt .cfa-card-h small');
        if (sub) sub.textContent = [...filterParts(), plural(t.flights, ROW)].join(' · ');
    }

    let thisMonthBtn = null;
    function apply() {
        const shown = new Set(), base = new Set();
        mainRows.forEach(r => {
            const b = matchBase(r), ok = b && inPeriod(r);
            r.hidden = !ok;
            const d = r.nextElementSibling;
            if (!ok && d && d.classList.contains('cfa-pl-detail')) { d.hidden = true; r.classList.remove('is-open'); }
            if (ok) shown.add(r.dataset.fk);
            if (b) base.add(r.dataset.fk);
        });
        lastList = PL_PAX.filter(p => shown.has(p.fk));
        baseList = PL_PAX.filter(p => base.has(p.fk));
        const t = paint(panel, lastList, mainRows.filter(r => !r.hidden));

        // Past months: each summed from its own rows; a month with none is
        // hidden, an opened one is repainted, and inside a month only the
        // rows the search / segment keep are listed.
        const listed = new Set();
        document.querySelectorAll('#plMonths tr.cfa-pl-month').forEach(mr => {
            const m = sum(monthList(mr.dataset.month));
            const det = mr.nextElementSibling;
            mr.hidden = m.pax === 0;
            if (mr.hidden) { det.hidden = true; mr.classList.remove('is-open'); } else listed.add(mr.dataset.month);
            const c = k => mr.querySelector('[data-mc="' + k + '"]');
            c('flights').textContent = m.flights; c('pax').textContent = m.pax;
            c('revenue').textContent = money(m.revenue); c('cost').textContent = money(m.cost);
            c('profit').textContent = money(m.profit); c('profit').className = 'num ' + tone(m.profit);
            c('margin').textContent = pct(m.profit, m.revenue); c('margin').className = 'num ' + (m.revenue > 0 ? tone(m.profit) : '');
            c('agent_due').textContent = money(m.agent_due); c('agent_due').className = 'num ' + (m.agent_due > 0 ? 'cfa-due' : '');
            det.querySelectorAll('tr.cfa-pl-row').forEach(fr => {
                fr.hidden = !base.has(fr.dataset.fk);
                if (fr.hidden) { fr.nextElementSibling.hidden = true; fr.classList.remove('is-open'); }
            });
            if (!det.hidden) paintMonth(mr);
        });
        const mt = sum(baseList.filter(p => listed.has(p.month)));
        const mc = k => document.querySelector('#plMonths tfoot [data-mt="' + k + '"]');
        if (mc('months')) {
            mc('months').textContent = plural(listed.size, 'month');
            mc('flights').textContent = mt.flights; mc('pax').textContent = mt.pax;
            mc('revenue').textContent = money(mt.revenue); mc('cost').textContent = money(mt.cost);
            mc('profit').textContent = money(mt.profit); mc('margin').textContent = pct(mt.profit, mt.revenue);
            mc('agent_due').textContent = money(mt.agent_due);
        }

        const none = document.getElementById('plNone');
        none.hidden = t.flights > 0 || mainRows.length === 0;
        if (!none.hidden) {
            const msg = st.q || st.seg !== 'all' ? 'Nothing matches in ' + periodLabel() + '.'
                : 'Nothing in ' + periodLabel() + (listed.size ? ' — other months are under Past months below.' : '.');
            none.firstElementChild.innerHTML = '<i class="fas fa-filter"></i>' + esc(msg);
        }
        document.getElementById('plScope').textContent = [periodLabel(), ...filterParts(), plural(t.flights, ROW)].join(' · ');
        if (thisMonthBtn) thisMonthBtn.hidden = isThisMonth();
        saveState();
    }

    // ── Bar wiring ──
    const search = document.getElementById('plSearch'), clear = document.getElementById('plClear');
    search.value = st.q; clear.style.display = st.q ? 'block' : 'none';
    search.addEventListener('input', () => { st.q = search.value.trim().toLowerCase(); clear.style.display = st.q ? 'block' : 'none'; apply(); });
    clear.addEventListener('click', () => { search.value = ''; st.q = ''; clear.style.display = 'none'; apply(); });

    const seg = document.getElementById('plSeg');
    const paintSeg = () => seg.querySelectorAll('button').forEach(b => b.classList.toggle('is-on', b.dataset.range === st.seg));
    seg.querySelectorAll('button').forEach(b => b.addEventListener('click', () => { st.seg = b.dataset.range; paintSeg(); apply(); }));
    paintSeg();

    const ft = document.getElementById('plFromText'), tt = document.getElementById('plToText');
    psSetDateBoxes('pl', st.from, st.to);
    [['From', ft], ['To', tt]].forEach(([n, txt]) => {
        const nat = document.getElementById('pl' + n);
        nat.addEventListener('change', () => { txt.value = psDMY(nat.value); psMarkBad(txt, false); });
    });
    document.getElementById('plApply').addEventListener('click', () => {
        const f = psParseDate(ft.value), t = psParseDate(tt.value);
        psMarkBad(ft, f === null); psMarkBad(tt, t === null);
        if (f === null || t === null) return;
        [st.from, st.to] = f && t && f > t ? [t, f] : [f, t];
        apply();
    });
    // Back to the current month after a custom range (empty boxes + Apply = every month).
    const dates = document.getElementById('plDates');
    if (dates) {
        thisMonthBtn = document.createElement('button');
        thisMonthBtn.type = 'button'; thisMonthBtn.className = 'pb-go'; thisMonthBtn.textContent = 'This month';
        thisMonthBtn.title = monthName(NOW);
        thisMonthBtn.addEventListener('click', () => { st.from = MONTH_FROM; st.to = MONTH_TO; psSetDateBoxes('pl', st.from, st.to); apply(); });
        dates.appendChild(thisMonthBtn);
    }

    // Print: what is on screen — statement, analysis and the filtered rows.
    const print = document.getElementById('plPrint');
    if (print) print.addEventListener('click', e => { e.preventDefault(); window.print(); });

    // ── Every figure opens what is behind it (owner, 2026-10-12: "there
    //    should be no end point"). A figure lists the rows that make it up;
    //    a row jumps to itself in the table below and opens. Inside an
    //    opened month the same, for that month's rows. ──
    const labelOf = fk => {
        const r = mainRows.find(x => x.dataset.fk === fk);
        return r ? (r.dataset.label || r.querySelector('td:nth-child(2) a, td:nth-child(2) .b')?.textContent || fk) : fk;
    };
    const METRIC = {
        package: ['Package / direct sales', p => p.package], billed: ['Change charges billed', p => p.billed],
        transfer: ['To Contract Flight at net', p => p.transfer || 0], penalty: ['Own losses', p => p.penalty || 0],
        revenue: ['Total revenue', p => p.revenue], ticket_cost: ['Ticket cost', p => p.ticket_cost],
        service_cost: ['Services', p => p.service_cost], change_cost: ['Change cost we bear', p => p.change_cost],
        cost: ['Total cost', p => p.cost], profit: ['Gross profit', p => p.profit],
        earned_profit: ['Earned profit (travelled)', p => p.earned ? p.profit : 0], booked_profit: ['Booked profit (not travelled)', p => p.earned ? 0 : p.profit],
        agent_paid: ['Received from agents', p => p.agent_paid], agent_due: ['Still due from agents', p => p.agent_due],
        vendor_paid: ['Paid to vendors / sellers', p => p.vendor_paid], vendor_due: ['Still owed to vendors / sellers', p => Math.max(0, p.ticket_cost + p.service_cost - p.vendor_paid)],
        cash: ['Cash position', p => p.agent_paid - p.vendor_paid], net_changes: ['Net cost of changes', p => p.change_cost - p.billed],
        inc_revenue: ['Not fully ticketed — revenue', p => p.inc ? p.revenue : 0], inc_pax: ['Not fully ticketed — revenue', p => p.inc ? p.revenue : 0],
    };

    let dd, ddRoot = null;
    function ddOpen(title, sub, head, rows, total, root) {
        if (!dd) {
            dd = document.createElement('div');
            dd.className = 'cfa-dd';
            dd.innerHTML = '<div class="cfa-dd-box" role="dialog" aria-modal="true"><div class="cfa-dd-h"><div><h3></h3><small></small></div><button type="button" class="cfa-dd-x" title="Close">&times;</button></div><div class="cfa-dd-b"></div></div>';
            document.body.appendChild(dd);
            dd.querySelector('.cfa-dd-x').addEventListener('click', ddClose);
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && dd.classList.contains('is-open')) ddClose(); });
            dd.querySelector('.cfa-dd-b').addEventListener('click', e => { const tr = e.target.closest('tr[data-fk]'); if (tr) { ddClose(); jumpTo(tr.dataset.fk, ddRoot); } });
        }
        ddRoot = root || null;
        dd.querySelector('h3').textContent = title;
        dd.querySelector('small').textContent = sub;
        dd.querySelector('.cfa-dd-b').innerHTML = rows.length
            ? `<table class="cfa-table"><thead><tr>${head.map((h, i) => `<th class="${i ? 'num' : ''}">${h}</th>`).join('')}</tr></thead><tbody>${rows.join('')}</tbody>${total ? `<tfoot><tr>${total}</tr></tfoot>` : ''}</table>`
            : '<div class="cfa-empty">Nothing behind this figure in the current view.</div>';
        dd.classList.add('is-open');
    }
    function ddClose() { dd && dd.classList.remove('is-open'); }

    // root = an opened month's cell (its rows), or null for the tab's table.
    function jumpTo(fk, root) {
        const sel = 'tr.cfa-pl-row[data-fk="' + CSS.escape(fk) + '"]';
        let r = root ? root.querySelector(sel) : mainRows.find(x => x.dataset.fk === fk);
        if (!r) return;
        if (!root && r.hidden) {
            // Outside the period on screen: open it under its month instead.
            const p = PL_PAX.find(x => x.fk === fk);
            const mr = p && [...document.querySelectorAll('#plMonths tr.cfa-pl-month')].find(x => x.dataset.month === p.month && !x.hidden);
            if (mr) {
                if (mr.nextElementSibling.hidden) { mr.nextElementSibling.hidden = false; mr.classList.add('is-open'); paintMonth(mr); }
                r = mr.nextElementSibling.querySelector(sel);
            } else {
                st.q = ''; st.seg = 'all'; st.from = st.to = ''; search.value = ''; psSetDateBoxes('pl', '', ''); paintSeg(); apply();
            }
            if (!r || r.hidden) return;
        }
        const d = r.nextElementSibling;
        if (d && d.classList.contains('cfa-pl-detail') && d.hidden) { d.hidden = false; r.classList.add('is-open'); }
        r.scrollIntoView({ behavior: 'smooth', block: 'center' });
        r.classList.add('cfa-flash'); setTimeout(() => r.classList.remove('cfa-flash'), 1600);
    }

    function byRow(list, fn) {
        const out = {};
        list.forEach(p => { out[p.fk] = (out[p.fk] || 0) + fn(p); });
        return Object.entries(out).filter(([, v]) => Math.abs(v) >= 0.5).sort((a, b) => Math.abs(b[1]) - Math.abs(a[1]));
    }

    function openMetric(key, ctx) {
        const m = METRIC[key];
        if (!m) return;
        const rows = byRow(ctx.list, m[1]);
        const total = rows.reduce((a, [, v]) => a + v, 0);
        ddOpen(m[0] + (ctx.label ? ' · ' + ctx.label : ''), rows.length + ' row' + (rows.length === 1 ? '' : 's') + ' in ' + (ctx.label || 'the current view') + ' · click one to open it', ['Row', 'Amount'],
            rows.map(([fk, v]) => `<tr class="cfa-dd-go" data-fk="${esc(fk)}"><td class="b">${esc(labelOf(fk))}</td><td class="num ${v < 0 ? 'cfa-bad' : ''}">${money(v)}</td></tr>`),
            `<td>Total</td><td class="num">${money(total)}</td>`, ctx.root);
    }

    function openAgent(name, ctx) {
        const mine = ctx.list.filter(p => p.agent === name);
        const g = {};
        mine.forEach(p => { (g[p.fk] = g[p.fk] || []).push(p); });
        const rows = Object.entries(g).map(([fk, ps]) => [fk, sum(ps)]).sort((a, b) => b[1].profit - a[1].profit);
        const t = sum(mine);
        ddOpen(name + (ctx.label ? ' · ' + ctx.label : ''), rows.length + ' row' + (rows.length === 1 ? '' : 's') + ' · click one to open it', ['Row', 'Revenue', 'Cost', 'Profit', 'Due'],
            rows.map(([fk, a]) => `<tr class="cfa-dd-go" data-fk="${esc(fk)}"><td class="b">${esc(labelOf(fk))}</td><td class="num">${money(a.revenue)}</td><td class="num">${money(a.cost)}</td><td class="num ${a.profit < 0 ? 'cfa-bad' : 'cfa-good'}">${money(a.profit)}</td><td class="num ${a.agent_due > 0 ? 'cfa-due' : ''}">${money(a.agent_due)}</td></tr>`),
            `<td>Total</td><td class="num">${money(t.revenue)}</td><td class="num">${money(t.cost)}</td><td class="num">${money(t.profit)}</td><td class="num">${money(t.agent_due)}</td>`, ctx.root);
    }

    // Statement, cash strip, warning and leak figures are the clickable ones —
    // table footers and month cells already have their own drill-down. Marked
    // before any month is opened, so a month's copy carries the marks too.
    panel.querySelectorAll('.cfa-pls [data-pl], .cfa-pls-cash [data-pl], .cfa-pls-warn [data-pl], .cfa-pl-leak [data-pl]').forEach(el => {
        if (!METRIC[el.dataset.pl]) return;
        el.classList.add('cfa-dd-link');
        el.title = 'Show what makes up this figure';
    });
    panel.addEventListener('click', e => {
        const ms = e.target.closest('.cfa-pl-mscope');
        const ctx = ms ? { list: monthList(ms.dataset.month), root: ms.closest('td'), label: ms.dataset.label } : { list: lastList, root: null, label: '' };
        const fig = e.target.closest('.cfa-dd-link[data-pl]');
        if (fig) { openMetric(fig.dataset.pl, ctx); return; }
        const ag = e.target.closest('.cfa-pl-agents tr[data-agent]');
        if (ag) { openAgent(ag.dataset.agent, ctx); return; }
        const ls = e.target.closest('.cfa-pl-losses tr[data-fk]');
        if (ls) { jumpTo(ls.dataset.fk, ctx.root); return; }
        const gl = e.target.closest('.cfa-pls-gl');
        if (gl) { document.querySelector('button.cfa-tab[data-tab="journal"]')?.click(); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
        const stock = e.target.closest('[data-dd-stock]');
        if (stock && window.PL_STOCK) {
            ddOpen(stock.dataset.ddTitle || 'Unsold tickets (stock)', stock.dataset.ddSub || 'Bought, not sold and not on a contract flight', (stock.dataset.ddHeads || 'PNR|Bought|Cost').split('|'),
                PL_STOCK.map(l => `<tr><td class="b">${esc(l.pnr || '—')}</td><td class="num">${l.date ? psDMY(String(l.date).slice(0, 10)) : '—'}</td><td class="num">${money(l.amount)}</td></tr>`),
                `<td>Total</td><td></td><td class="num">${money(PL_STOCK.reduce((a, l) => a + l.amount, 0))}</td>`);
        }
    });

    apply();
})();
