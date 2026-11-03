{{--
    Date cell: the record's own date on top, the time it was recorded beneath.

        @include('partials.date-time-cell', ['date' => $journal->date, 'recordedAt' => $journal->created_at])

    Most date columns in this system are DATE columns — they hold no clock time.
    created_at is when the row was written, which is the same moment only when
    the record was not backdated. The time is therefore shown ONLY when both
    fall on the same day, so a backdated row never displays a time belonging to
    a different date.

    Y-m-d above, H:i:s below by default. Pass `dtcFormat` for a table that shows
    its dates another way — the bank transfer list is d/m/Y, and a column that
    sits beside one should read the same:

        @include('partials.date-time-cell', [
            'date' => $expense->expense_date,
            'recordedAt' => $expense->created_at,
            'dtcFormat' => 'd/m/Y',
        ])

    The format changes only what is PRINTED. The same-day test that decides
    whether a time is shown at all still compares real dates, so it cannot be
    affected by how they are displayed.

    Renders in a table cell on many screens, so it must not be able to throw:
    an unparseable value degrades to a dash rather than taking the page down.

    Params:
      date       - Carbon|string|null  the record's own date
      recordedAt - Carbon|string|null  the row's created_at (optional)
      dtcFormat  - string              date format for the top line (default 'Y-m-d').
                   Prefixed like every other name in here on purpose: an
                   @include inherits the PARENT view's variables, and a page
                   that happened to have a variable of its own called $format
                   would otherwise silently reformat every date cell on it.
--}}
@php
    $dtcParse = function ($v) {
        if (empty($v)) {
            return null;
        }
        if ($v instanceof \Carbon\CarbonInterface) {
            return $v;
        }
        try {
            return \Illuminate\Support\Carbon::parse($v);
        } catch (\Throwable $e) {
            return null;
        }
    };

    $dtcDate = $dtcParse($date ?? null);
    $dtcRec  = $dtcParse($recordedAt ?? null);

    // Two separate things: the key the same-day test compares on, and the text
    // that is printed. Keeping the test on Y-m-d means a caller's format can
    // never change WHETHER a time is shown, only how the date beside it reads.
    $dtcKey   = $dtcDate ? $dtcDate->format('Y-m-d') : null;
    $dtcDay   = $dtcDate ? $dtcDate->format($dtcFormat ?? 'Y-m-d') : null;
    $dtcTime  = ($dtcRec && $dtcKey && $dtcRec->format('Y-m-d') === $dtcKey)
        ? $dtcRec->format('H:i:s')
        : null;
@endphp

@once
<style>
    .dtc-date { white-space: nowrap; font-weight: 600; }
    /* display:block explicitly — several lists switch their cells to flex at
       narrow widths for the stacked label layout, which would otherwise pull the
       time onto the same line as the date and flush it to the right. */
    .dtc-time { display: block; width: 100%; font-size: 0.7rem; color: #94a3b8; margin-top: 2px; white-space: nowrap; font-weight: 400; }
</style>
@endonce

<span class="dtc-date">{{ $dtcDay ?? '—' }}</span>
@if($dtcTime)
    <div class="dtc-time">{{ $dtcTime }}</div>
@endif
