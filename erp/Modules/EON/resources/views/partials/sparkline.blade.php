@php
    /**
     * A sparkline, drawn as inline SVG.
     *
     * No charting library, on purpose. Chart.js or its cousins would have to be
     * added to the ERP's shared asset loading to be available here, and CLAUDE.md
     * forbids changing how any shared dependency loads — those changes reach all
     * 576 views. An <svg> element needs nothing.
     *
     * @var array $values  numbers, oldest first
     * @var int   $w, $h   drawing box
     * @var string $stroke line colour
     */
    /* TYPE-CHECKED, NOT JUST DEFAULTED, and it is not paranoia: Blade's
       @include inherits every variable in the PARENT's scope, so a caller with
       its own $w — the dashboard's watchlist loop used `as $w` — silently
       shadows the width with whatever that is. It shipped as
       "Unsupported operand types: Assessment - int" from a file that never
       mentions Assessment. Anything not of the expected type falls back to the
       default. */
    $values = array_values(array_map('floatval', is_array($values ?? null) ? $values : []));
    $w      = is_numeric($w ?? null) ? (int) $w : 92;
    $h      = is_numeric($h ?? null) ? (int) $h : 22;
    $stroke = is_string($stroke ?? null) ? $stroke : '#2563eb';

    $max = $values ? max($values) : 0;
    $min = $values ? min($values) : 0;
    $span = max(0.0001, $max - $min);

    $points = [];

    foreach ($values as $i => $v) {
        $x = count($values) > 1 ? ($i / (count($values) - 1)) * ($w - 2) + 1 : $w / 2;
        // 2px of padding top and bottom so the extremes are not clipped by the box.
        $y = $h - 2 - (($v - $min) / $span) * ($h - 4);
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
@endphp

@if (count($values) < 2)
    <span style="font-size:.68rem;color:#d1d5db;">—</span>
@else
    <svg class="eon-spark" width="{{ $w }}" height="{{ $h }}" viewBox="0 0 {{ $w }} {{ $h }}"
         role="img" aria-label="Trend of {{ implode(', ', array_map('intval', $values)) }}">
        <polyline points="{{ implode(' ', $points) }}"
                  fill="none" stroke="{{ $stroke }}" stroke-width="1.5"
                  stroke-linejoin="round" stroke-linecap="round" />
        @php $lastX = explode(',', end($points))[0]; $lastY = explode(',', end($points))[1]; @endphp
        <circle cx="{{ $lastX }}" cy="{{ $lastY }}" r="2" fill="{{ $stroke }}" />
    </svg>
@endif
