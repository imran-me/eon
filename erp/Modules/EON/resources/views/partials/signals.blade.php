@php
    /**
     * What EON noticed, worst first.
     *
     * Every line here was raised by exactly one analyzer, and the wording is
     * the analyzer's own — this file adds no sentences of its own, so anything
     * on the page can be traced back to the class that concluded it.
     *
     * @var array    $signals  Signal[]
     * @var int|null $limit
     * @var string|null $empty
     */
    /* Type-checked — see sparkline.blade.php. */
    $signals = is_array($signals ?? null) ? $signals : [];
    $limit   = is_numeric($limit ?? null) ? (int) $limit : null;
    $shown   = $limit ? array_slice($signals, 0, $limit) : $signals;
@endphp

@forelse ($shown as $s)
    <div class="eon-signal {{ $s->severity }}">
        <i class="fas {{ $s->icon() }}"></i>
        <div style="min-width:0">
            <div class="eon-signal-t">{{ $s->title }}</div>
            @if ($s->detail)
                <div class="eon-signal-d">{{ $s->detail }}</div>
            @endif
        </div>
    </div>
@empty
    <div class="eon-empty">
        <i class="fas fa-circle-check"></i>
        {{ $empty ?? 'Nothing stood out — no findings on this person.' }}
    </div>
@endforelse

@if ($limit && count($signals) > $limit)
    <div style="margin-top:.5rem;font-size:.72rem;color:#9ca3af;">
        {{ count($signals) - $limit }} further {{ Str::plural('finding', count($signals) - $limit) }} not shown.
    </div>
@endif
