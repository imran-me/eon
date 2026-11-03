{{-- The Recent Transactions filter bar from Manage Banks, rebuilt for a
     server-paginated list. Same shape — search, Today / Yesterday / Last Week
     segment, a dd/mm/yyyy + calendar pair for each bound, Apply — but every
     control re-asks the server, because the rows on screen are one page of
     many and narrowing them in the browser would only ever filter that page.

     Drop it INSIDE the page's own <form method="GET">: the search box and the
     two date inputs are plain named fields, so Apply submits whatever else the
     form carries (the collapsible selects below it, a hidden book= key) and
     nothing already chosen is lost. The quick-range buttons are links built
     from the current query minus `page`, for the same reason.

     Two things it says differently from the Manage Banks copy, on purpose:
     - "Last Month" is offered and the last segment is plain "All", because
       these lists have no 14-day ceiling — All is every row, not a window.
     - A lone date is read as THAT DAY (the second box is filled before the
       form goes), matching the Today / Yesterday buttons, so from=12/09 never
       silently means "12/09 onwards".

     Props:
       search      request key of the text search        (default 'search')
       placeholder search box placeholder
       from / to   request keys of the date bounds        (default from_date / to_date)
       reset       URL of the bare list; Reset shows only while a filter is live
       total       paginator total, shown as "N rows" while a filter is live
       $leading    optional controls rendered before search (bank-style state filter)
       $slot       extra controls (selects) rendered between search and the segment

     Every selector is lfb- prefixed; the style and script blocks render once
     per page. Nothing here reaches outside .lfb-bar. --}}
@props([
    'search'      => 'search',
    'placeholder' => 'Search…',
    'from'        => 'from_date',
    'to'          => 'to_date',
    'reset'       => null,
    'total'       => null,
])
@php
    $lfbToday     = now()->toDateString();
    $lfbYesterday = now()->subDay()->toDateString();
    $lfbWeekFrom  = now()->subDays(6)->toDateString();    // rolling 7 days, today included
    $lfbMonthFrom = now()->subDays(29)->toDateString();   // rolling 30 days — the ledger's reading
    $lfbFrom      = request($from);
    $lfbTo        = request($to);
    $lfbQ         = request($search);
    // `ledger_page` is used by the employee-ledger paginator. Quick ranges
    // must always restart at page one just like the standard `page` paginator.
    $lfbBase      = collect(request()->except(['page', 'ledger_page']))->filter(fn ($v) => $v !== null && $v !== '');
    $lfbQuick     = fn ($f, $t) => url()->current() . '?' . http_build_query(
        $lfbBase->except([$from, $to])->merge(array_filter([$from => $f, $to => $t]))->all()
    );
    $lfbOn        = fn ($f, $t) => $lfbFrom === $f && $lfbTo === $t;
    // ?company= is the shell's company switcher, stamped on links by the
    // layout — not a filter the user set. It rides along on every link this
    // bar builds, and Reset keeps it, but it must not count as "filtered".
    $lfbLive      = $lfbBase->except(['company', 'tab'])->isNotEmpty();
    $lfbReset     = $reset ?: url()->current();
    if (request()->filled('company')) {
        $lfbReset .= (str_contains($lfbReset, '?') ? '&' : '?') . http_build_query(['company' => request('company')]);
    }
    // "12/09/2026" for the typed half. String surgery, never Carbon::parse on a
    // value the user typed into the URL — a bad one should show as-is, not throw.
    $lfbDmy       = fn ($ymd) => preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $ymd, $m) ? "{$m[3]}/{$m[2]}/{$m[1]}" : (string) $ymd;
@endphp
@once
<style>
    /* The font is pinned, not inherited: the Manage Banks bar renders in the
       shell's own stack, while several list pages set DM Sans on <body>. Pinned,
       the bar measures the same on every page it appears on. */
    .lfb-bar {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 11px 14px; background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
    }
    .lfb-bar input, .lfb-bar select, .lfb-bar button, .lfb-bar a { font-family: inherit; }
    /* Standalone use — a panel of its own when nothing joins onto it below
       (a card list rather than a table). Same edge as .ca-panel on Manage Banks. */
    .lfb-panel {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
        overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    }
    .lfb-panel .lfb-bar { border-bottom: 0; }
    /* A page action beside Reset (Print, Export) — the .lf-print shape. */
    .lfb-action {
        height: 34px; padding: 0 12px; border: 1px solid #e2e8f0; border-radius: 8px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none; cursor: pointer;
        background: #fff; color: #475569; font-size: 11.5px; font-weight: 700;
        transition: background .15s, color .15s, border-color .15s;
    }
    .lfb-action:hover { background: #f8fafc; color: #334155; border-color: #cbd5e1; text-decoration: none; }
    .lfb-action i { font-size: 11px; }
    .lfb-search { position: relative; flex: 1 1 260px; min-width: 200px; }
    .lfb-search > i {
        position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: 12px; pointer-events: none;
    }
    .lfb-search input {
        width: 100%; height: 34px; padding: 0 30px 0 30px; box-sizing: border-box;
        border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;
        font-size: 12.5px; color: #1e293b; outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .lfb-search input::placeholder { color: #a3adbb; }
    .lfb-search input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    /* Clear button: hidden until there is something to clear. */
    .lfb-clear {
        position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
        border: 0; background: none; padding: 4px; cursor: pointer;
        color: #cbd5e1; font-size: 12px; line-height: 1; display: none;
    }
    .lfb-clear:hover { color: #64748b; }
    .lfb-search.has-q .lfb-clear { display: block; }

    /* Extra selects a page slots in, sized to match the bar's controls. */
    .lfb-extra { display: flex; flex: 0 1 auto; align-items: center; gap: 8px; flex-wrap: wrap; }
    .lfb-extra select {
        height: 34px; max-width: 200px; padding: 0 28px 0 10px; box-sizing: border-box;
        border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;
        font-size: 12px; color: #334155; outline: none;
    }
    .lfb-extra select:focus { border-color: #6366f1; }
    /* A slotted select that a page upgrades to select2 — sized to the bar's
       34px so the pair reads as one row. Scoped under .lfb-extra, so the
       shared 42px rule in layout.table-design is untouched everywhere else. */
    .lfb-extra .select2-container { min-width: 160px; max-width: 220px; }
    .lfb-extra .select2-container .select2-selection--single {
        height: 34px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;
    }
    .lfb-extra .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 32px; font-size: 12px; color: #334155; padding-left: 10px;
    }
    .lfb-extra .select2-container--default .select2-selection--single .select2-selection__arrow { height: 32px; top: 0; }
    .lfb-extra .select2-container--default.select2-container--focus .select2-selection--single,
    .lfb-extra .select2-container--default.select2-container--open .select2-selection--single { border-color: #6366f1; }

    /* Segmented range links. flex:0 0 auto so the group never shrinks — with
       overflow:hidden, shrinking slices "Today" off rather than compressing it;
       the search field gives way and the bar wraps first. */
    .lfb-seg { display: flex; flex: 0 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff; }
    .lfb-seg a {
        display: inline-flex; align-items: center;
        border-left: 1px solid #e2e8f0; padding: 0 11px; height: 34px;
        font-size: 11.5px; font-weight: 600; color: #64748b; white-space: nowrap;
        text-decoration: none; transition: background .15s, color .15s;
    }
    .lfb-seg a:first-child { border-left: 0; }
    .lfb-seg a:hover { background: #f1f5f9; color: #334155; text-decoration: none; }
    .lfb-seg a.is-on { background: #eef2ff; color: #4338ca; font-weight: 700; }

    .lfb-dates { display: flex; flex: 0 0 auto; align-items: center; gap: 6px; }
    .lfb-dates > span { font-size: 11px; color: #94a3b8; }
    /* A dd/mm/yyyy box and the real calendar side by side in one border.
       <input type="date"> prints in the BROWSER's locale (mm/dd/yyyy on en-US
       Chrome) and nothing can change that, so the half you read and type is a
       text field, and the date input beside it shows ONLY its picker button —
       Chrome's own, so the calendar opens with no picker library. */
    .lfb-date {
        position: relative; display: inline-flex; align-items: center;
        height: 34px; padding-right: 5px; background: #fff;
        border: 1px solid #e2e8f0; border-radius: 8px;
        transition: border-color .15s, background .15s;
    }
    .lfb-date:focus-within { border-color: #6366f1; }
    .lfb-dates input.lfb-date-text {
        width: 88px; height: 32px; padding: 0 2px 0 9px; box-sizing: border-box;
        border: 0; background: none; outline: none;
        font-size: 12px; color: #334155;
    }
    .lfb-date.is-bad { border-color: #fca5a5; background: #fef2f2; }
    .lfb-dates input.lfb-date-text.is-bad { color: #b91c1c; }
    .lfb-dates input.lfb-date-native {
        width: 22px; height: 22px; flex: 0 0 auto;
        margin: 0; padding: 0; border: 0; background: none;
        color: transparent; overflow: hidden; cursor: pointer;
    }
    .lfb-date-native::-webkit-datetime-edit,
    .lfb-date-native::-webkit-inner-spin-button,
    .lfb-date-native::-webkit-clear-button { display: none; }
    .lfb-date-native::-webkit-calendar-picker-indicator {
        width: 22px; height: 22px; margin: 0; padding: 0;
        opacity: 0.5; cursor: pointer; transition: opacity .15s;
    }
    .lfb-date:hover .lfb-date-native::-webkit-calendar-picker-indicator,
    .lfb-date-native:focus::-webkit-calendar-picker-indicator { opacity: 0.9; }
    /* A custom range lights no segment; the boxes go indigo instead. */
    .lfb-dates.is-open .lfb-date { border-color: #c7d2fe; background: #f5f6ff; }
    .lfb-go {
        height: 34px; padding: 0 12px; border: 0; border-radius: 8px; cursor: pointer;
        background: #4f46e5; color: #fff; font-size: 11.5px; font-weight: 700;
        font-family: inherit;
    }
    .lfb-go:hover { background: #4338ca; }
    .lfb-reset {
        height: 34px; padding: 0 12px; border: 1px solid #e2e8f0; border-radius: 8px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        background: #fff; color: #475569; font-size: 11.5px; font-weight: 700;
        transition: background .15s, color .15s, border-color .15s;
    }
    .lfb-reset:hover { background: #f8fafc; color: #334155; border-color: #cbd5e1; text-decoration: none; }
    /* Says what the filter found. Only while one is live — a count of
       everything is not information. */
    .lfb-count {
        flex-shrink: 0; font-size: 11px; font-weight: 700; color: #4338ca;
        background: #eef2ff; border-radius: 20px; padding: 4px 10px; white-space: nowrap;
    }
    .lfb-count.is-none { color: #b91c1c; background: #fef2f2; }
</style>
@endonce

<div class="lfb-bar" data-lfb>
    @isset($leading)
        <div class="lfb-extra">{{ $leading }}</div>
    @endisset

    <div class="lfb-search {{ $lfbQ !== null && $lfbQ !== '' ? 'has-q' : '' }}">
        <i class="fas fa-magnifying-glass"></i>
        <input type="text" name="{{ $search }}" value="{{ $lfbQ }}" autocomplete="off"
               placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}">
        <button type="button" class="lfb-clear" title="Clear search"><i class="fas fa-xmark"></i></button>
    </div>

    @if ($slot->isNotEmpty())
        <div class="lfb-extra">{{ $slot }}</div>
    @endif

    <div class="lfb-seg">
        <a href="{{ $lfbQuick($lfbToday, $lfbToday) }}" class="{{ $lfbOn($lfbToday, $lfbToday) ? 'is-on' : '' }}">Today</a>
        <a href="{{ $lfbQuick($lfbYesterday, $lfbYesterday) }}" class="{{ $lfbOn($lfbYesterday, $lfbYesterday) ? 'is-on' : '' }}">Yesterday</a>
        <a href="{{ $lfbQuick($lfbWeekFrom, $lfbToday) }}" class="{{ $lfbOn($lfbWeekFrom, $lfbToday) ? 'is-on' : '' }}">Last Week</a>
        <a href="{{ $lfbQuick($lfbMonthFrom, $lfbToday) }}" class="{{ $lfbOn($lfbMonthFrom, $lfbToday) ? 'is-on' : '' }}">Last Month</a>
        <a href="{{ $lfbQuick(null, null) }}" class="{{ !$lfbFrom && !$lfbTo ? 'is-on' : '' }}">All</a>
    </div>

    @php
        // Lit as a custom range only when the dates on screen match none of the
        // segments — the tint says "the table was built from these two boxes".
        $lfbCustom = ($lfbFrom || $lfbTo)
            && !$lfbOn($lfbToday, $lfbToday) && !$lfbOn($lfbYesterday, $lfbYesterday)
            && !$lfbOn($lfbWeekFrom, $lfbToday) && !$lfbOn($lfbMonthFrom, $lfbToday);
    @endphp
    <div class="lfb-dates {{ $lfbCustom ? 'is-open' : '' }}">
        <span class="lfb-date">
            <input type="text" class="lfb-date-text" value="{{ $lfbDmy($lfbFrom) }}" placeholder="dd/mm/yyyy"
                   inputmode="numeric" maxlength="10" autocomplete="off" aria-label="From date">
            <input type="date" name="{{ $from }}" value="{{ $lfbFrom }}" class="lfb-date-native" aria-label="Pick the from date" title="Pick a date">
        </span>
        <span>to</span>
        <span class="lfb-date">
            <input type="text" class="lfb-date-text" value="{{ $lfbDmy($lfbTo) }}" placeholder="dd/mm/yyyy"
                   inputmode="numeric" maxlength="10" autocomplete="off" aria-label="To date">
            <input type="date" name="{{ $to }}" value="{{ $lfbTo }}" class="lfb-date-native" aria-label="Pick the to date" title="Pick a date">
        </span>
        <button type="submit" class="lfb-go">Apply</button>
    </div>

    {{-- Page-specific actions such as Print belong after Apply and before Reset. --}}
    @isset($actions)
        {{ $actions }}
    @endisset

    @if ($lfbLive)
        @if ($total !== null)
            <span class="lfb-count {{ (int) $total === 0 ? 'is-none' : '' }}">
                {{ (int) $total === 0 ? 'No match' : number_format((int) $total) . ' ' . Str::plural('row', (int) $total) }}
            </span>
        @endif
        <a href="{{ $lfbReset }}" class="lfb-reset" title="Clear every filter"><i class="fas fa-rotate-left"></i> Reset</a>
    @endif

    {{-- Page actions (Print, Export) via <x-slot:actions>; style each .lfb-action. --}}
</div>

@once
<script>
// Scoped to [data-lfb]: the bar owns its own inputs and touches nothing else on
// the page. It runs once per page however many bars there are.
(function () {
    // dd/mm/yyyy in, Y-m-d out — the form the date input and the server both
    // speak. Y-m-d is taken as-is (a paste from elsewhere in the ERP). '' for an
    // empty box, null for something unreadable; callers tell those apart.
    function parse(str) {
        var t = String(str || '').trim();
        if (!t) return '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(t)) return t;
        var m = t.match(/^(\d{1,2})\D(\d{1,2})\D(\d{4})$/) || t.match(/^(\d{2})(\d{2})(\d{4})$/);
        if (!m) return null;
        var d = m[1].padStart(2, '0'), mo = m[2].padStart(2, '0');
        if (+d < 1 || +d > 31 || +mo < 1 || +mo > 12) return null;
        return m[3] + '-' + mo + '-' + d;
    }
    function dmy(ymd) {
        var m = String(ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? m[3] + '/' + m[2] + '/' + m[1] : (ymd || '');
    }
    function markBad(text, bad) {
        text.classList.toggle('is-bad', bad);
        var wrap = text.closest('.lfb-date');
        if (wrap) wrap.classList.toggle('is-bad', bad);
    }

    document.querySelectorAll('[data-lfb]').forEach(function (bar) {
        var form    = bar.closest('form');
        var search  = bar.querySelector('.lfb-search input');
        var wrap    = bar.querySelector('.lfb-search');
        var natives = bar.querySelectorAll('.lfb-date-native');

        // Search: Escape and the × both clear; Enter submits the form it sits in.
        if (search) {
            search.addEventListener('input', function () { wrap.classList.toggle('has-q', search.value.trim() !== ''); });
            search.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { search.value = ''; wrap.classList.remove('has-q'); }
            });
            var clear = bar.querySelector('.lfb-clear');
            if (clear) clear.addEventListener('click', function () {
                search.value = '';
                wrap.classList.remove('has-q');
                // Clearing a search that was applied is itself a request.
                if (form && new URLSearchParams(location.search).get(search.name)) form.submit(); else search.focus();
            });
        }

        // A lone date means THAT DAY: fill the empty box from the other before
        // the form goes, and swap bounds that arrive backwards rather than
        // answering with an empty table. Runs on every submit path — Apply,
        // Enter in the search box, a select's onchange.
        function settle() {
            var from = natives[0], to = natives[1];
            if (!from || !to) return;
            if (from.value && !to.value) to.value = from.value;
            else if (to.value && !from.value) from.value = to.value;
            else if (from.value && to.value && from.value > to.value) { var t = from.value; from.value = to.value; to.value = t; }
        }
        if (form) form.addEventListener('submit', settle);

        // Picking from the calendar applies straight away, the way the Manage
        // Banks bar does. Both boxes empty is left alone — that is someone
        // mid-edit, not a request.
        natives.forEach(function (native) {
            native.addEventListener('change', function () {
                var text = native.parentElement.querySelector('.lfb-date-text');
                if (text) { text.value = dmy(native.value); markBad(text, false); }
                if (form && (natives[0].value || natives[1].value)) form.requestSubmit ? form.requestSubmit() : form.submit();
            });
        });

        // The typed half. change fires on blur, so tabbing out applies exactly
        // as Enter does; a date that will not parse holds on screen in red.
        bar.querySelectorAll('.lfb-date-text').forEach(function (text) {
            var native = text.parentElement.querySelector('.lfb-date-native');
            var commit = function (submit) {
                var ymd = parse(text.value);
                if (ymd === null) { markBad(text, true); return; }
                markBad(text, false);
                var changed = native.value !== ymd;
                native.value = ymd;
                text.value = ymd ? dmy(ymd) : '';
                if (submit && form && changed && (natives[0].value || natives[1].value)) {
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            };
            text.addEventListener('change', function () { commit(true); });
            text.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); commit(true); } });
        });

        // A click on the wrapper's own padding beside the calendar button opens
        // the picker too. Guarded: showPicker() throws without a user gesture.
        bar.querySelectorAll('.lfb-date').forEach(function (wrapEl) {
            wrapEl.addEventListener('click', function (e) {
                if (e.target !== wrapEl) return;
                var native = wrapEl.querySelector('.lfb-date-native');
                if (native && native.showPicker) { try { native.showPicker(); } catch (_) {} }
            });
        });
    });
})();
</script>
@endonce
