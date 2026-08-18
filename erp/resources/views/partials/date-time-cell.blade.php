{{--
    Date cell: the record's own date on top, the time it was recorded beneath.

        @include('partials.date-time-cell', ['date' => $journal->date, 'recordedAt' => $journal->created_at])

    Most date columns in this system are DATE columns — they hold no clock time.
    created_at is when the row was written, which is the same moment only when
    the record was not backdated. The time is therefore shown ONLY when both
    fall on the same day, so a backdated row never displays a time belonging to
    a different date.

    Formats the same way as the bank tables: Y-m-d above, H:i:s below.

    Renders in a table cell on many screens, so it must not be able to throw:
    an unparseable value degrades to a dash rather than taking the page down.

    Params:
      date       - Carbon|string|null  the record's own date
      recordedAt - Carbon|string|null  the row's created_at (optional)
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

    $dtcDay  = $dtcDate ? $dtcDate->format('Y-m-d') : null;
    $dtcTime = ($dtcRec && $dtcDay && $dtcRec->format('Y-m-d') === $dtcDay)
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
