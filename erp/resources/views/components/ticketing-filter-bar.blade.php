{{-- Ticketing's table heading (owner, 2026-10-06: "in ticketing, all tables
     heading should be same style like in the contract flight, with live
     search, no enter needed"). The Flights & Passengers filter bar
     (.mfp-filter) in Ticketing's own prefix: labelled fields on one row,
     28px controls, date presets, From / To, Filter, Reset.

     Live: typing (300 ms pause), a select, a preset or a date fetches this
     same page with the bar's values and swaps only the parts the page marks
     with data-tfb-swap (its table, its page links) and the count. The search
     box is never replaced, so focus and the caret stay put; the address bar
     follows, so a reload or a shared link shows the same list. A page without
     data-tfb-swap just submits as before.

     Drop-in for <x-list-filter-bar> — same props, same slot, used INSIDE the
     page's own <form method="GET">:
       search / placeholder / from / to / reset / total, $slot = extra selects.
     Each slotted <select> gets a label from its aria-label (or its first
     option, "All Routes" → "Routes"). After a swap the page hears
     `tfb:updated` on document to re-run row scripts.
     Every selector is tfb- prefixed. --}}
@props([
    'search'      => 'search',
    'placeholder' => 'Search…',
    'from'        => 'from_date',
    'to'          => 'to_date',
    'reset'       => null,
    'total'       => null,
])
@php
    $tfbFrom  = request($from);
    $tfbTo    = request($to);
    $tfbQ     = request($search);
    $tfbBase  = collect(request()->except(['page']))->filter(fn ($v) => $v !== null && $v !== '');
    // ?company= rides on every link but is not a filter the user set.
    $tfbLive  = $tfbBase->except(['company', 'tab', 'view'])->isNotEmpty();
    $tfbReset = $reset ?: url()->current();
    if (request()->filled('company')) {
        $tfbReset .= (str_contains($tfbReset, '?') ? '&' : '?') . http_build_query(['company' => request('company')]);
    }
    // Each slotted select in a labelled field, as Contract Flight's bar has.
    $tfbSlot = preg_replace_callback('~<select\b[^>]*>.*?</select>~s', function ($m) {
        $sel = $m[0];
        $label = preg_match('~aria-label="([^"]+)"~', $sel, $a) ? $a[1]
            : (preg_match('~<option[^>]*>\s*([^<]+?)\s*</option>~', $sel, $o) ? preg_replace('~^All\s+~i', '', $o[1]) : '');
        return '<div class="tfb-field tfb-field-select"><label>' . e(ucfirst(trim($label))) . '</label>' . $sel . '</div>';
    }, (string) $slot);
@endphp
@once
<style>
    .tfb-bar {
        display: flex; flex-wrap: wrap; align-items: flex-end; gap: 8px;
        padding: 10px 12px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
    }
    .tfb-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
    .tfb-field-search { flex: 1 1 170px; min-width: 160px; }
    .tfb-field-select { flex: 1 1 120px; min-width: 110px; max-width: 200px; }
    .tfb-field-date { flex: 0 0 116px; }
    .tfb-bar label { color: #475569; font-size: 11px; font-weight: 600; margin: 0; }
    .tfb-bar input, .tfb-bar select {
        width: 100%; height: 28px; min-width: 0; border: 1px solid #dce2eb; border-radius: 9px; background: #fff;
        color: #334155; font-size: 12px; padding: 0 7px; box-shadow: 0 1px 2px #0f172a03; box-sizing: border-box;
        transition: border-color .18s ease, box-shadow .18s ease; font-family: inherit;
    }
    /* Plain selects dressed as Contract Flight's select2 boxes: white, 9px
       corners, a small dark caret on the right. */
    .tfb-bar select { -webkit-appearance: none; appearance: none; padding: 0 26px 0 8px; cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='5' viewBox='0 0 8 5'%3E%3Cpath d='M0 0l4 5 4-5z' fill='%23475569'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 9px center; background-size: 8px 5px; }
    .tfb-search { position: relative; }
    .tfb-search > i { position: absolute; left: 11px; top: 8px; color: #94a3b8; font-size: 12px; pointer-events: none; }
    .tfb-bar .tfb-search input { padding-left: 32px; padding-right: 26px; }
    .tfb-search .tfb-clear { position: absolute; right: 6px; top: 5px; border: 0; background: none; padding: 2px 4px; color: #cbd5e1; cursor: pointer; display: none; font-size: 12px; }
    .tfb-search.has-q .tfb-clear { display: block; }
    .tfb-search .tfb-clear:hover { color: #64748b; }
    .tfb-bar input::placeholder { color: #8793a5; }
    .tfb-bar input:hover, .tfb-bar select:hover { border-color: #a5b4fc; }
    .tfb-bar input:focus, .tfb-bar select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px #6366f11a; }
    .tfb-bar .select2-container { width: 100% !important; }
    .tfb-bar .select2-container .select2-selection--single { height: 28px !important; border: 1px solid #dce2eb !important; border-radius: 9px !important; background: #fff; box-shadow: 0 1px 2px #0f172a03; }
    .tfb-bar .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 26px !important; padding-left: 7px; padding-right: 28px; font-size: 12px; color: #334155; }
    .tfb-bar .select2-container--default .select2-selection--single .select2-selection__arrow { height: 26px !important; right: 4px; top: 0 !important; }
    .tfb-bar .select2-container--default.select2-container--open .select2-selection--single,
    .tfb-bar .select2-container--default.select2-container--focus .select2-selection--single { border-color: #6366f1 !important; box-shadow: 0 0 0 3px #6366f11a; }
    .tfb-presets { display: flex; align-items: center; gap: 4px; flex-wrap: nowrap; }
    .tfb-presets button, .tfb-actions button, .tfb-reset {
        height: 28px; padding: 0 7px; border: 1px solid #dce2eb; border-radius: 7px; background: #fff; color: #64748b;
        font-size: 10px; font-weight: 600; white-space: nowrap; cursor: pointer; font-family: inherit;
        transition: background .18s ease, border-color .18s ease, color .18s ease;
    }
    .tfb-presets button:hover, .tfb-presets button.is-active, .tfb-reset:hover { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
    .tfb-actions { display: flex; align-items: center; gap: 6px; }
    .tfb-actions button, .tfb-reset { font-size: 12px; padding: 0 14px; display: inline-flex; align-items: center; text-decoration: none; }
    .tfb-actions button i, .tfb-reset i { margin-right: 6px; }
    .tfb-actions button.is-primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .tfb-actions button.is-primary:hover { background: #4338ca; }
    .tfb-count { height: 28px; display: inline-flex; align-items: center; font-size: 11px; font-weight: 700; color: #4338ca; background: #eef2ff; border-radius: 20px; padding: 0 10px; white-space: nowrap; }
    .tfb-count.is-none { color: #b91c1c; background: #fef2f2; }
    .tfb-meta { display: contents; }
    [data-tfb-swap].tfb-loading { opacity: .55; transition: opacity .15s; }
    @media (max-width: 640px) { .tfb-field-search, .tfb-field-select { flex-basis: 100%; } }
</style>
@endonce

<div class="tfb-bar" data-tfb>
    <div class="tfb-field tfb-field-search">
        <label>Search</label>
        <div class="tfb-search {{ $tfbQ !== null && $tfbQ !== '' ? 'has-q' : '' }}">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="search" name="{{ $search }}" value="{{ $tfbQ }}" autocomplete="off" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" data-tfb-q>
            <button type="button" class="tfb-clear" title="Clear search"><i class="fas fa-xmark"></i></button>
        </div>
    </div>
    {!! $tfbSlot !!}
    <div class="tfb-presets" aria-label="Quick date filters">
        <button type="button" data-tfb-preset="today">Today</button>
        <button type="button" data-tfb-preset="yesterday">Yesterday</button>
        <button type="button" data-tfb-preset="week" title="Last 7 days">7 days</button>
        <button type="button" data-tfb-preset="month" title="Last 30 days">30 days</button>
        <button type="button" data-tfb-preset="clear">All</button>
    </div>
    <div class="tfb-field tfb-field-date"><label>From</label><input type="date" name="{{ $from }}" value="{{ $tfbFrom }}" data-tfb-from></div>
    <div class="tfb-field tfb-field-date"><label>To</label><input type="date" name="{{ $to }}" value="{{ $tfbTo }}" data-tfb-to></div>
    {{-- Everything applies as it changes, so no Filter button; Reset only
         while a filter is on ($total is accepted for <x-list-filter-bar> parity). --}}
    <div class="tfb-actions">
        <button type="submit" class="is-primary"><i class="fas fa-filter"></i>Filter</button>
        <span class="tfb-meta" data-tfb-meta>
        @if ($tfbLive)
            <a href="{{ $tfbReset }}" class="tfb-reset" title="Clear every filter"><i class="fas fa-rotate-left"></i>Reset</a>
        @endif
        </span>
    </div>
    @isset($actions){{ $actions }}@endisset
</div>

@once
<script>
// Scoped to [data-tfb] and the [data-tfb-swap] parts its own page marks.
(function () {
    const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    function range(p) {
        const t = new Date(); t.setHours(0, 0, 0, 0);
        const back = n => { const d = new Date(t); d.setDate(t.getDate() - n); return ymd(d); };
        return { today: [ymd(t), ymd(t)], yesterday: [back(1), back(1)], week: [back(6), ymd(t)], month: [back(29), ymd(t)], clear: ['', ''] }[p];
    }
    document.querySelectorAll('[data-tfb]').forEach(function (bar) {
        const form = bar.closest('form');
        if (!form) return;
        const q = bar.querySelector('[data-tfb-q]'), qWrap = bar.querySelector('.tfb-search');
        const from = bar.querySelector('[data-tfb-from]'), to = bar.querySelector('[data-tfb-to]');
        // Asked at each run, not here: this script sits above the table it swaps.
        const canLive = () => !!(window.fetch && window.DOMParser && document.querySelector('[data-tfb-swap]'));
        let ctrl = null, timer = null, last = null;

        function markPresets() {
            bar.querySelectorAll('[data-tfb-preset]').forEach(b => {
                const [f, t] = range(b.dataset.tfbPreset);
                b.classList.toggle('is-active', b.dataset.tfbPreset === 'clear' ? (!from.value && !to.value) : (from.value === f && to.value === t));
            });
        }
        function url() {
            const params = new URLSearchParams();
            for (const [k, v] of new FormData(form)) if (v !== '' && k !== 'page') params.append(k, v);
            const base = form.getAttribute('action') || location.pathname;
            return (base || location.pathname) + (params.toString() ? '?' + params : '');
        }
        // A lone date means THAT day; bounds backwards are swapped.
        function settle() {
            if (from.value && !to.value) to.value = from.value;
            else if (to.value && !from.value) from.value = to.value;
            else if (from.value && to.value && from.value > to.value) { const x = from.value; from.value = to.value; to.value = x; }
        }
        async function run() {
            settle(); markPresets();
            const target = url();
            if (target === last) return;
            last = target;
            if (!canLive()) { location.href = target; return; }
            ctrl?.abort(); ctrl = new AbortController();
            const parts = document.querySelectorAll('[data-tfb-swap]');
            parts.forEach(p => p.classList.add('tfb-loading'));
            try {
                const res = await fetch(target, { headers: { Accept: 'text/html' }, credentials: 'same-origin', signal: ctrl.signal });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                const fresh = doc.querySelectorAll('[data-tfb-swap]');
                document.querySelectorAll('[data-tfb-swap]').forEach((p, i) => {
                    if (!fresh[i]) return;
                    // A sorted column stays sorted on the new rows.
                    const was = p.matches('table[data-cf-sort]') ? p : p.querySelector('table[data-cf-sort]');
                    const now = fresh[i].matches('table[data-cf-sort]') ? fresh[i] : fresh[i].querySelector('table[data-cf-sort]');
                    p.replaceWith(fresh[i]);
                    if (now && window.cfSortReapply) {
                        if (was && was.dataset.cfSortKey !== undefined) { now.dataset.cfSortKey = was.dataset.cfSortKey; now.dataset.cfSortDir = was.dataset.cfSortDir; }
                        window.cfSortReapply(now);
                    }
                });
                const meta = doc.querySelector('[data-tfb-meta]');
                if (meta) bar.querySelector('[data-tfb-meta]').innerHTML = meta.innerHTML;
                parts.forEach(p => p.classList.remove('tfb-loading'));
                history.replaceState(null, '', target + location.hash);
                document.dispatchEvent(new CustomEvent('tfb:updated'));
            } catch (e) {
                if (e.name !== 'AbortError') { last = null; parts.forEach(p => p.classList.remove('tfb-loading')); }
            }
        }
        const soon = () => { clearTimeout(timer); timer = setTimeout(run, 300); };
        const now = () => { clearTimeout(timer); run(); };
        last = url();
        markPresets();

        // Every way the form is sent — Filter, Enter, a select's own
        // onchange="this.form.requestSubmit()" — goes live instead of reloading.
        form.addEventListener('submit', e => { e.preventDefault(); now(); });
        q.addEventListener('input', () => { qWrap.classList.toggle('has-q', q.value.trim() !== ''); soon(); });
        q.addEventListener('keydown', e => { if (e.key === 'Escape') { q.value = ''; qWrap.classList.remove('has-q'); now(); } });
        bar.querySelector('.tfb-clear').addEventListener('click', () => { q.value = ''; qWrap.classList.remove('has-q'); q.focus(); now(); });
        bar.querySelectorAll('[data-tfb-preset]').forEach(b => b.addEventListener('click', () => {
            const [f, t] = range(b.dataset.tfbPreset); from.value = f; to.value = t; now();
        }));
        [from, to].forEach(d => d.addEventListener('change', () => { if (from.value || to.value || d.value === '') now(); }));
        // Selects: native change, and select2's (jQuery) change.
        bar.querySelectorAll('select').forEach(s => s.addEventListener('change', soon));
        if (window.jQuery) window.jQuery(bar).on('change', 'select', soon);
        // The collapsible extra filters some pages keep in the same form.
        form.querySelectorAll('select').forEach(s => { if (!bar.contains(s)) { s.addEventListener('change', soon); if (window.jQuery) window.jQuery(s).on('change', soon); } });
    });
})();
</script>
@endonce
{{-- Column sorting: Contract Flight's own script (opt in with data-cf-sort on the <table>). --}}
@include('contract-flights.partials.table-sort')
