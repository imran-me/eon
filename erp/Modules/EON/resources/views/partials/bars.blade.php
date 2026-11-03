@php
    use Modules\EON\Intelligence\Support\Period;

    /**
     * The month-by-month column chart: work that ARRIVED behind work that was
     * FINISHED, so the two are read together. A completions chart on its own
     * cannot tell a quiet month from an empty one.
     *
     * Inline SVG for the same reason the sparkline is — see that file.
     *
     * @var array  $series   keyed 'YYYY-MM' => ['created' => int, 'completed' => int]
     * @var int    $height
     * @var bool   $showCreated
     * @var string $emptyText
     */
    /* Type-checked rather than defaulted — see the note in sparkline.blade.php.
       @include inherits the caller's whole scope, so a stray $height in a
       parent view would otherwise become this chart's height. */
    $series      = is_array($series ?? null) ? $series : [];
    $height      = is_numeric($height ?? null) ? (int) $height : 150;
    $showCreated = isset($showCreated) ? (bool) $showCreated : true;

    $keys  = array_keys($series);
    $count = max(1, count($keys));

    $peak = 1;
    foreach ($series as $m) {
        $peak = max($peak, (int) ($m['completed'] ?? 0), $showCreated ? (int) ($m['created'] ?? 0) : 0);
    }

    /* The viewBox is a fixed 1000 units wide and scaled by CSS, so the chart is
     * responsive with no JavaScript measuring anything.
     *
     * THE CATCH, learned from the first screenshot: everything inside scales by
     * (rendered width / 1000). These charts sit in half-width cards about 460px
     * across, so a 9px axis label rendered at 4px — an illegible smear — and a
     * 160-unit-tall box rendered 74px high, which read as squashed. So the
     * geometry is expressed in viewBox units sized for that ~0.46 scale: the
     * caller's $height is treated as the RENDERED height in pixels and the
     * viewBox is built from it, with the font scaled to match.
     */
    $vw       = 1000;
    $scale    = 2.2;                        // viewBox units per rendered pixel
    $vh       = $height * $scale;
    $axisFont = round(9 * $scale);          // ~9px once the browser scales it down
    $plot     = $vh - ($axisFont * 2.2);    // room for the month labels
    $slot     = $vw / $count;
    $barW     = min(38 * $scale, $slot * 0.34);
    $gap      = 3 * $scale;
    $lastKey  = end($keys) ?: null;
@endphp

@if (empty($series))
    <div class="eon-empty"><i class="fas fa-chart-column"></i>Nothing to chart yet.</div>
@else
    {{-- No preserveAspectRatio="none": it stretches the month labels into
         unreadable smears at wide viewports. The viewBox scales proportionally
         with width:100%;height:auto instead. --}}
    <svg class="eon-chart" viewBox="0 0 {{ $vw }} {{ $vh }}"
         role="img" aria-label="Monthly completed work">
        {{-- Baseline --}}
        <line x1="0" y1="{{ $plot }}" x2="{{ $vw }}" y2="{{ $plot }}" stroke="#f1f3f5" stroke-width="{{ round($scale, 1) }}" />

        @foreach ($series as $key => $month)
            @php
                $i         = array_search($key, $keys, true);
                $centre    = ($i * $slot) + ($slot / 2);
                $completed = (int) ($month['completed'] ?? 0);
                $created   = (int) ($month['created'] ?? 0);

                $ch = ($completed / $peak) * ($plot - (6 * $scale));
                $rh = ($created   / $peak) * ($plot - (6 * $scale));

                $partial = $key === $lastKey;
            @endphp

            @if ($showCreated && $created > 0)
                <rect x="{{ round($centre - $barW - ($gap / 2), 1) }}" y="{{ round($plot - $rh, 1) }}"
                      width="{{ round($barW, 1) }}" height="{{ round(max($rh, 1), 1) }}"
                      rx="{{ round(2 * $scale, 1) }}" fill="#e5e7eb">
                    <title>{{ Period::shortLabel($key) }} — {{ $created }} arrived</title>
                </rect>
            @endif

            <rect x="{{ round($showCreated ? $centre + ($gap / 2) : $centre - ($barW / 2), 1) }}"
                  y="{{ round($plot - $ch, 1) }}"
                  width="{{ round($barW, 1) }}" height="{{ round(max($ch, 1), 1) }}"
                  rx="{{ round(2 * $scale, 1) }}" fill="{{ $partial ? '#93c5fd' : '#2563eb' }}">
                <title>{{ Period::shortLabel($key) }} — {{ $completed }} completed{{ $partial ? ' (month still running)' : '' }}</title>
            </rect>

            {{-- Every month is labelled when there are twelve or fewer; beyond
                 that they would collide, so every other one is. --}}
            @if ($count <= 12 || $i % 2 === 0)
                <text class="eon-axis" font-size="{{ $axisFont }}" x="{{ round($centre, 1) }}" y="{{ round($vh - ($axisFont * 0.5), 1) }}"
                      text-anchor="middle">{{ Period::shortLabel($key) }}</text>
            @endif
        @endforeach
    </svg>

    <div class="eon-legend">
        <span><i style="background:#2563eb"></i> Completed</span>
        @if ($showCreated)<span><i style="background:#e5e7eb"></i> Arrived</span>@endif
        <span><i style="background:#93c5fd"></i> Current month, still running</span>
    </div>
@endif
