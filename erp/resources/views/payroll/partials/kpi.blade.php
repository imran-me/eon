{{--
    One summary card, built to the same shape Salary Manage uses: icon tile on the
    left, then label · value · what it is spread across.

    $label    — what the number is
    $value    — the number, already formatted
    $icon     — Font Awesome class
    $iconBg   — tile background / $iconText — tile icon colour
    $valueTone— Tailwind text colour for the figure, or null for the default ink
    $foot     — what it is spread across
    $series   — month-end history behind the figure; only used to quote the
                month-on-month move, and omitted when there is no honest one
    $goodDown — for money owed or still to collect, falling is the good news, so
                the arrow keeps pointing down while the wording goes green
--}}
@php
    // @include shares the PARENT's variables, so a tile that passes no `series`
    // still sees whatever `$series` the page happens to hold — and the payroll
    // pages hold a KEYED array of several series. Indexing that numerically is
    // how a tile with no history of its own started reading $series[2]. Only a
    // plain list of numbers is treated as this tile's own series.
    $series = (isset($series) && is_array($series) && array_is_list($series)
        && ! array_filter($series, fn ($v) => ! is_numeric($v)))
        ? $series
        : [];

    $goodDown = $goodDown ?? false;
    $valueTone = $valueTone ?? 'text-gray-900';

    // Month on month, and only where there is an honest percentage to quote:
    // everything is an infinite rise from a zero base.
    $trend = null;
    $n = count($series);

    if ($n >= 2 && abs((float) $series[$n - 2]) > 0.009) {
        $pct = (($series[$n - 1] - $series[$n - 2]) / abs($series[$n - 2])) * 100;

        if (abs($pct) > 0.5) {
            $rising = $pct > 0;
            $good = $goodDown ? ! $rising : $rising;

            $trend = [
                'icon' => $rising ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down',
                'text' => ($rising ? '+' : '') . number_format($pct, 1) . '% vs last month',
                'tone' => $good ? 'text-green-600' : 'text-red-600',
            ];
        }
    }
@endphp

<div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
         style="background:{{ $iconBg }};color:{{ $iconText }};">
        <i class="fas {{ $icon }}"></i>
    </div>
    <div class="min-w-0">
        <p class="text-xs text-gray-500 font-medium">{{ $label }}</p>
        <p class="text-xl font-extrabold {{ $valueTone }} mt-0.5">{{ $value }}</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ $foot }}</p>
        @if($trend)
            <p class="text-[11px] font-semibold mt-0.5 {{ $trend['tone'] }}">
                <i class="fas {{ $trend['icon'] }}"></i> {{ $trend['text'] }}
            </p>
        @endif
    </div>
</div>
