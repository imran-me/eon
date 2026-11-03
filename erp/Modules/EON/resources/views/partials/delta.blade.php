@php
    /**
     * A percentage change, with its arrow. Null renders as a dash rather than
     * as 0% — "we cannot compare" and "no change" are different statements and
     * the second one is a claim.
     *
     * @var float|null $value    percent
     * @var bool       $goodUp   is a rise the good direction? (false for overdue)
     */
    /* Type-checked — see sparkline.blade.php. */
    $value  = is_numeric($value ?? null) ? (float) $value : null;
    $goodUp = isset($goodUp) ? (bool) $goodUp : true;

    if ($value === null) {
        $tone = 'flat';
    } elseif (abs($value) < 3) {
        $tone = 'flat';
    } elseif ($value > 0) {
        $tone = $goodUp ? 'up' : 'down';
    } else {
        $tone = $goodUp ? 'down' : 'up';
    }
@endphp

<span class="eon-delta {{ $tone }}">
    @if ($value === null)
        —
    @elseif (abs($value) < 3)
        <i class="fas fa-minus" style="font-size:.6rem"></i> level
    @else
        <i class="fas fa-arrow-{{ $value > 0 ? 'up' : 'down' }}" style="font-size:.6rem"></i>{{ round(abs($value)) }}%
    @endif
</span>
