{{-- Payroll list filter bar — the Contract Flight → Manage Flights filter strip
     (labelled Search, labelled selects, "…from" / "…to", Filter + Reset),
     rebuilt for the payroll lists under the pfb- prefix.

     No Today / Yesterday / This week buttons, on purpose: payroll runs by the
     month, so day presets were noise here (owner, 2026-09-27). A page that is
     already scoped to one month (Salary Manage) passes :dates="false".

     Drop it INSIDE the page's own <form method="GET">: every control is a plain
     named field, so Filter submits whatever else the form carries (hidden
     company_id / date / type keys) and nothing already chosen is lost.

     Props:
       search       request key of the text search            (default 'search')
       searchLabel  label over the search box                  (default 'Search')
       placeholder  search box placeholder
       from / to    request keys of the date bounds            (default from_date / to_date)
       dateLabel    prefix of the date labels: 'Issued' → "Issued from" / "Issued to"
       reset        URL of the bare list (default: current URL without a query)
       keep         keys that survive Reset and do not count as a live filter
       pages        paginator keys ignored when deciding "is a filter live"
       total        row total, shown as "N rows" while a filter is live
       dates        false hides the from/to pair (page already scoped to a month)
       searchable   false hides the search box
       segments     button groups instead of a status dropdown:
                    [['name' => 'status', 'label' => 'Status', 'options' => ['paid' => 'Paid', …]], …]
                    an "All" button is added in front of the options
       $slot        extra labelled selects, each in a .pfb-field with a <label>
       $range       replaces the date inputs (month inputs on Reports)

     Empty fields are left out of the URL on submit. A lone date is read as THAT
     DAY. Values in the
     URL are never parsed with Carbon here: a malformed one is simply not echoed.
     Every selector is pfb- prefixed; styles and script render once per page. --}}
@props([
    'search'      => 'search',
    'searchLabel' => 'Search',
    'placeholder' => 'Search…',
    'from'        => 'from_date',
    'to'          => 'to_date',
    'dateLabel'   => 'Date',
    'reset'       => null,
    'keep'        => [],
    'pages'       => ['page'],
    'total'       => null,
    'dates'       => true,
    'searchable'  => true,
    'segments'    => [],
])
@php
    $pfbYmd   = function ($v) {
        if (! is_string($v) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) return '';
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $v : '';
    };
    $pfbFrom  = $pfbYmd(request($from));
    $pfbTo    = $pfbYmd(request($to));
    if ($pfbFrom === '' && $pfbTo !== '') $pfbFrom = $pfbTo;
    if ($pfbTo === '' && $pfbFrom !== '') $pfbTo = $pfbFrom;
    $pfbQ     = request($search);
    $pfbQ     = is_string($pfbQ) ? $pfbQ : '';
    $pfbKeep  = (array) $keep;
    // Read from the raw query string, not request()->query(): some controllers
    // merge() a forced user_id into the request for plain employees, and that
    // lock is not a filter the viewer set.
    parse_str((string) request()->server('QUERY_STRING', ''), $pfbSent);
    $pfbLive  = collect($pfbSent)
        ->except(array_merge($pfbKeep, (array) $pages))
        ->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])
        ->isNotEmpty();
    $pfbReset = $reset ?: url()->current();
    $pfbKept  = collect(request()->only($pfbKeep))->filter(fn ($v) => is_scalar($v) && $v !== '')->all();
    if ($pfbKept) {
        $pfbReset .= (str_contains($pfbReset, '?') ? '&' : '?') . http_build_query($pfbKept);
    }
    $pfbHasRange = isset($range) && trim((string) $range) !== '';
@endphp
@once
<style>
    .pfb-bar {
        display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px;
        padding: 10px 12px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
    }
    .pfb-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 0 0 150px; }
    .pfb-field.pfb-search-field { flex: 1 1 220px; min-width: 200px; }
    .pfb-field.pfb-date-field { flex: 0 0 118px; }
    .pfb-field.pfb-month-field { flex: 0 0 140px; }
    .pfb-bar label { color: #475569; font-size: 11px; font-weight: 600; margin: 0; }
    .pfb-bar input, .pfb-bar select {
        width: 100%; height: 28px; min-width: 0; border: 1px solid #dce2eb; border-radius: 9px; background: #fff;
        color: #334155; font-size: 12px; padding: 0 7px; box-shadow: 0 1px 2px #0f172a03; box-sizing: border-box;
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .pfb-bar input[type="search"] { padding-left: 32px; }
    .pfb-search { position: relative; }
    .pfb-search > i { position: absolute; left: 11px; top: 8px; color: #94a3b8; font-size: 12px; pointer-events: none; }
    .pfb-bar input::placeholder { color: #8793a5; }
    .pfb-bar input:hover, .pfb-bar select:hover { border-color: #a5b4fc; }
    .pfb-bar input:focus, .pfb-bar select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px #6366f11a; }
    .pfb-actions button, .pfb-reset {
        height: 28px; padding: 0 8px; border: 1px solid #dce2eb; border-radius: 7px; background: #fff; color: #64748b;
        font-size: 10px; font-weight: 600; white-space: nowrap; cursor: pointer; box-sizing: border-box;
        display: inline-flex; align-items: center; text-decoration: none;
        transition: background .18s ease, border-color .18s ease, color .18s ease;
    }
    .pfb-reset:hover {
        background: #eef2ff; border-color: #c7d2fe; color: #4338ca; text-decoration: none;
    }
    .pfb-actions { display: flex; align-items: center; gap: 6px; }
    .pfb-actions button, .pfb-reset { font-size: 12px; padding: 0 14px; }
    .pfb-actions button i { margin-right: 6px; }
    .pfb-actions button.is-primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .pfb-actions button.is-primary:hover { background: #4338ca; }
    /* Segmented choice for a filter with only two or three values (Status):
       each option writes a hidden input and submits, so one click filters and
       whatever is typed in the search box goes along with it. */
    .pfb-field.pfb-seg-field { flex: 0 0 auto; }
    .pfb-seg { display: inline-flex; height: 28px; border: 1px solid #dce2eb; border-radius: 9px; background: #fff; overflow: hidden; box-sizing: border-box; }
    .pfb-seg button { height: 100%; padding: 0 12px; border: 0; background: transparent; color: #64748b; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: background .18s ease, color .18s ease; }
    .pfb-seg button + button { border-left: 1px solid #dce2eb; }
    .pfb-seg button:hover { background: #eef2ff; color: #4338ca; }
    .pfb-seg button.is-on { background: #4f46e5; color: #fff; }
    .pfb-count { align-self: center; font-size: 11px; font-weight: 600; color: #64748b; white-space: nowrap; }
    @media (max-width: 640px) {
        .pfb-field, .pfb-field.pfb-date-field, .pfb-field.pfb-month-field { flex: 1 1 calc(50% - 5px); }
        .pfb-field.pfb-search-field { flex: 1 1 100%; }
        .pfb-actions { flex: 1 1 100%; justify-content: flex-end; }
    }
    html.dark .pfb-bar { background: #1e293b; border-bottom-color: #334155; }
    html.dark .pfb-bar label { color: #94a3b8; }
    html.dark .pfb-bar input, html.dark .pfb-bar select { background: #0f172a; border-color: #475569; color: #e2e8f0; color-scheme: dark; }
    html.dark .pfb-bar input::placeholder { color: #64748b; }
    html.dark .pfb-bar input:hover, html.dark .pfb-bar select:hover { border-color: #64748b; }
    html.dark .pfb-bar input:focus, html.dark .pfb-bar select:focus { border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(96,165,250,.18); }
    html.dark .pfb-reset { background: #0f172a; border-color: #475569; color: #cbd5e1; }
    html.dark .pfb-reset:hover { background: rgba(59,130,246,.14); border-color: #60a5fa; color: #93c5fd; }
    html.dark .pfb-actions button.is-primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    html.dark .pfb-count { color: #94a3b8; }
    html.dark .pfb-seg { background: #0f172a; border-color: #475569; }
    html.dark .pfb-seg button { color: #cbd5e1; }
    html.dark .pfb-seg button + button { border-left-color: #475569; }
    html.dark .pfb-seg button:hover { background: rgba(59,130,246,.14); color: #93c5fd; }
    html.dark .pfb-seg button.is-on { background: #4f46e5; color: #fff; }
</style>
<script>
    // Everything below acts only on the form that contains a .pfb-bar.
    function pfbTidy(form) {
        var bar = form.querySelector('.pfb-bar');
        if (!bar) return;
        var f = bar.querySelector('[data-pfb-from]'), t = bar.querySelector('[data-pfb-to]');
        if (f && t) {
            if (f.value && !t.value) t.value = f.value;
            if (t.value && !f.value) f.value = t.value;
        }
        // Keep the URL to what was actually chosen: no search=&user_id=&… tail.
        Array.prototype.forEach.call(form.elements, function (el) {
            if (el.name && !el.disabled && el.value === '' && /^(INPUT|SELECT)$/.test(el.tagName)) el.disabled = true;
        });
    }
    // Segment button: write its value into the hidden input beside it, submit.
    // type="button" on purpose — a submit button would become the form's
    // default and pressing Enter in the search box would "click" All.
    function pfbSet(btn) {
        var field = btn.closest('.pfb-field'), input = field && field.querySelector('[data-pfb-seg]');
        if (!input) return;
        input.value = btn.getAttribute('data-value') || '';
        pfbGo(btn.closest('form'));
    }
    function pfbGo(form) {
        if (!form) return;
        pfbTidy(form);
        form.submit();
    }
    document.addEventListener('change', function (e) {
        var el = e.target;
        if (!el || el.tagName !== 'SELECT' || !el.closest) return;
        var bar = el.closest('.pfb-bar');
        if (bar) pfbGo(bar.closest('form'));
    });
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.querySelector || !form.querySelector('.pfb-bar')) return;
        pfbTidy(form);
    }, true);
</script>
@endonce
<div class="pfb-bar" role="search" aria-label="Filter list">
    @if($searchable)
    <div class="pfb-field pfb-search-field">
        <label>{{ $searchLabel }}</label>
        <div class="pfb-search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" name="{{ $search }}" value="{{ $pfbQ }}" placeholder="{{ $placeholder }}" aria-label="{{ $searchLabel }}"></div>
    </div>
    @endif
    {{ $slot }}
    @foreach((array) $segments as $pfbSeg)
        @php
            $pfbSegNow = request($pfbSeg['name']);
            $pfbSegNow = is_string($pfbSegNow) ? $pfbSegNow : '';
        @endphp
        <div class="pfb-field pfb-seg-field">
            <label>{{ $pfbSeg['label'] ?? 'Status' }}</label>
            <input type="hidden" name="{{ $pfbSeg['name'] }}" value="{{ $pfbSegNow }}" data-pfb-seg>
            <div class="pfb-seg" role="group" aria-label="{{ $pfbSeg['label'] ?? 'Status' }}">
                @foreach(['' => 'All'] + (array) $pfbSeg['options'] as $pfbVal => $pfbText)
                    <button type="button" data-value="{{ $pfbVal }}" onclick="pfbSet(this)" class="{{ (string) $pfbVal === $pfbSegNow ? 'is-on' : '' }}">{{ $pfbText }}</button>
                @endforeach
            </div>
        </div>
    @endforeach
    @if($pfbHasRange)
        {{ $range }}
    @elseif($dates)
        <div class="pfb-field pfb-date-field"><label>{{ $dateLabel }} from</label><input type="date" name="{{ $from }}" value="{{ $pfbFrom }}" data-pfb-from aria-label="{{ $dateLabel }} from"></div>
        <div class="pfb-field pfb-date-field"><label>{{ $dateLabel }} to</label><input type="date" name="{{ $to }}" value="{{ $pfbTo }}" data-pfb-to aria-label="{{ $dateLabel }} to"></div>
    @endif
    <div class="pfb-actions">
        <button type="submit" class="is-primary"><i class="fas fa-filter"></i>Filter</button>
        <a href="{{ $pfbReset }}" class="pfb-reset">Reset</a>
    </div>
    @if($pfbLive && $total !== null)
        <span class="pfb-count">{{ number_format((int) $total) }} {{ (int) $total === 1 ? 'row' : 'rows' }}</span>
    @endif
</div>
