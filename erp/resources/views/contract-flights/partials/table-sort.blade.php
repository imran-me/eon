{{-- Column sorting for the Contract Flight tables.

     Included only by Contract Flight views; every selector is scoped to
     `table[data-cf-sort]`, so nothing here reaches another screen. Click a
     heading to sort ascending, again for descending; the arrow shows which.

     Opt a table in with `data-cf-sort` on the <table>. Every <th> becomes
     sortable unless it carries `data-nosort` (Actions, blank, checkbox).
     A cell may give an explicit value with `data-sort="…"`; otherwise its text
     is read — a date (Y-m-d, d M Y, d/m/Y), a money figure (৳ / BDT / commas
     stripped) or plain text, compared numerically where it can be.

     Rows:
       · `data-sort-attach` (or the ledger's .fdl-route-row / accounts' .cfa-jr)
         travel with the row above them — the journey strip never parts from
         its passenger.
       · `data-sort-section` / .grp rows are dividers; rows sort within their
         section, the divider stays put.
       · a single cell spanning the table (empty state) is pinned at the end.

     Tables the page re-renders (the Passenger Ledger) call
     `cfSortReapply(table)` after painting so the chosen order survives. --}}
@once
<style>
    table[data-cf-sort] th[data-cf-sortable] { cursor: pointer; user-select: none; }
    table[data-cf-sort] th[data-cf-sortable]:hover { filter: brightness(.96); }
    table[data-cf-sort] th[data-cf-sortable]:focus-visible { outline: 2px solid #6366f1; outline-offset: -2px; }
    table[data-cf-sort] th .cf-sort-ico { margin-left: 5px; opacity: .4; font-size: 10px; }
    table[data-cf-sort] th[aria-sort="ascending"] .cf-sort-ico,
    table[data-cf-sort] th[aria-sort="descending"] .cf-sort-ico { opacity: 1; }
</style>
<script>
(function () {
    const ATTACH  = 'tr[data-sort-attach], tr.fdl-route-row, tr.cfa-jr';
    const SECTION = 'tr[data-sort-section], tr.grp';

    function cellValue(td) {
        if (!td) return '';
        if (td.dataset.sort !== undefined) return parse(td.dataset.sort);
        return parse(td.textContent || '');
    }
    function parse(raw) {
        const s = raw.replace(/\s+/g, ' ').trim();
        if (s === '' || s === '—' || s === '-') return null;
        let m;
        if ((m = s.match(/^(\d{4})-(\d{2})-(\d{2})/))) return Date.UTC(+m[1], m[2] - 1, +m[3]);
        if ((m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/))) return Date.UTC(+m[3], m[2] - 1, +m[1]);
        if ((m = s.match(/^(\d{1,2}) ([A-Za-z]{3})[a-z]* (\d{4})/))) {
            const t = Date.parse(`${m[1]} ${m[2]} ${m[3]} UTC`);
            if (!isNaN(t)) return t;
        }
        // Money or count: "৳1,500", "BDT 2,000.50", "-300", "12". Only when the
        // text starts with the figure (or a currency mark), so "CF-003" and
        // "12 Sep" are not read as numbers.
        if ((m = s.match(/^(?:৳|BDT|Tk\.?|\$)?\s*(-?\d[\d,]*(?:\.\d+)?)/i))) {
            const n = Number(m[1].replace(/,/g, ''));
            if (Number.isFinite(n)) return n;
        }
        return s.toLowerCase();
    }
    function compare(a, b) {
        if (a === null || b === null) return a === null ? (b === null ? 0 : 1) : -1; // blanks last
        if (typeof a === 'number' && typeof b === 'number') return a - b;
        return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
    }

    // Group the body into movable units: [primary row, ...attached rows].
    function units(tbody, colCount) {
        const sections = [{ divider: null, groups: [], pinned: [] }];
        let cur = sections[0];
        for (const tr of Array.from(tbody.rows)) {
            if (tr.matches(SECTION)) { cur = { divider: tr, groups: [], pinned: [] }; sections.push(cur); continue; }
            if (tr.matches(ATTACH) && cur.groups.length) { cur.groups[cur.groups.length - 1].push(tr); continue; }
            const cells = tr.cells;
            if (cells.length === 1 && (cells[0].colSpan || 1) >= colCount) { cur.pinned.push(tr); continue; }
            cur.groups.push([tr]);
        }
        return sections;
    }

    function apply(table) {
        const key = table.dataset.cfSortKey, dir = table.dataset.cfSortDir;
        const tbody = table.tBodies[0];
        if (!tbody || key === undefined) return;
        const idx = Number(key), sign = dir === 'desc' ? -1 : 1;
        const ths = table.tHead ? table.tHead.rows[0].cells : [];
        for (const th of ths) {
            const active = th.cellIndex === idx;
            th.setAttribute('aria-sort', active ? (dir === 'desc' ? 'descending' : 'ascending') : 'none');
            const ico = th.querySelector('.cf-sort-ico');
            if (ico) ico.className = 'fas cf-sort-ico ' + (active ? (dir === 'desc' ? 'fa-sort-down' : 'fa-sort-up') : 'fa-sort');
            if (th.hasAttribute('data-cf-sortable')) th.title = 'Sort ' + (active && dir === 'asc' ? 'descending' : 'ascending');
        }
        const frag = document.createDocumentFragment();
        for (const sec of units(tbody, ths.length)) {
            if (sec.divider) frag.appendChild(sec.divider);
            sec.groups
                .map((g, i) => ({ g, i, v: cellValue(g[0].cells[idx]) }))
                .sort((a, b) => compare(a.v, b.v) * sign || a.i - b.i)   // stable: ties keep their order
                .forEach(({ g }) => g.forEach(tr => frag.appendChild(tr)));
            sec.pinned.forEach(tr => frag.appendChild(tr));
        }
        tbody.appendChild(frag);
    }

    function toggle(table, th) {
        const idx = String(th.cellIndex);
        table.dataset.cfSortDir = table.dataset.cfSortKey === idx && table.dataset.cfSortDir === 'asc' ? 'desc' : 'asc';
        table.dataset.cfSortKey = idx;
        apply(table);
    }

    function wire(table) {
        if (table.dataset.cfSortWired || !table.tHead) return;
        table.dataset.cfSortWired = '1';
        for (const th of table.tHead.rows[0].cells) {
            if (th.hasAttribute('data-nosort') || th.textContent.trim() === '' || th.querySelector('input')) continue;
            th.setAttribute('data-cf-sortable', '');
            th.setAttribute('aria-sort', 'none');
            th.setAttribute('tabindex', '0');
            th.title = 'Sort ascending';
            const ico = document.createElement('i');
            ico.className = 'fas fa-sort cf-sort-ico';
            ico.setAttribute('aria-hidden', 'true');
            th.appendChild(ico);
            th.addEventListener('click', () => toggle(table, th));
            th.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(table, th); } });
        }
    }

    window.cfSortReapply = table => { if (table) { wire(table); apply(table); } };
    const boot = () => document.querySelectorAll('table[data-cf-sort]').forEach(wire);
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
})();
</script>
@endonce
