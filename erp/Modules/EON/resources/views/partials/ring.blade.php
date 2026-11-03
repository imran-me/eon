@php
    /**
     * The score, as a ring. The only place on an EON page where one number is
     * allowed to be big — and even here it is wrapped in its band name and its
     * confidence, because a bare "36" beside somebody's photograph is exactly
     * the kind of thing that gets quoted without its caveats.
     *
     * @var float|null $score
     * @var string     $band
     * @var string|null $confidence
     */
    /* Type-checked — see sparkline.blade.php on why @include's inherited scope
       makes a plain ?? default unsafe here. */
    $score = is_numeric($score ?? null) ? (float) $score : null;
    $band  = is_string($band ?? null) ? $band : 'unrated';

    $colours = [
        'exemplary' => '#10b981',
        'strong'    => '#3b82f6',
        'steady'    => '#8b5cf6',
        'watch'     => '#f59e0b',
        'critical'  => '#ef4444',
        'unrated'   => '#d1d5db',
    ];

    $colour = $colours[$band] ?? '#d1d5db';

    $r    = 46;
    $circ = 2 * M_PI * $r;
    $fill = $score === null ? 0 : ($score / 100) * $circ;
@endphp

<div class="eon-ring" title="{{ $score === null ? 'Not scored' : 'EON score ' . round($score) . ' of 100' }}">
    <svg width="108" height="108" viewBox="0 0 108 108">
        <circle cx="54" cy="54" r="{{ $r }}" fill="none" stroke="#f1f3f5" stroke-width="9" />
        @if ($score !== null)
            <circle cx="54" cy="54" r="{{ $r }}" fill="none" stroke="{{ $colour }}" stroke-width="9"
                    stroke-linecap="round"
                    stroke-dasharray="{{ round($fill, 2) }} {{ round($circ - $fill, 2) }}" />
        @endif
    </svg>
    <div class="eon-ring-c">
        <div class="eon-ring-v" style="color:{{ $colour }}">{{ $score === null ? '—' : round($score) }}</div>
        <div class="eon-ring-l">EON score</div>
    </div>
</div>
