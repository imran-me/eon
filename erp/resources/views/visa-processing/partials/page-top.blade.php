{{-- Visa Processing — one top for every Visa page, built to Ticket Orders'
     pattern (owner, 2026-10-12: match Ticketing / Contract File): one row of
     compact stat tiles, then a white header card with the page's action.

     $vxTiles   [[label, value, sub, icon, tone, href|null, active?], …]
                tone: indigo | green | teal | amber | orange | red | violet | slate | cyan | blue
     $vxIcon    header icon (fa-…)          $vxTitle  header title
     $vxSub     header sub-line (HTML-safe)  $vxAction slot: the header's buttons (raw HTML)
--}}
@php
    $vxTones = [
        'indigo' => ['#eef2ff', '#e0e7ff', '#4f46e5', '#312e81'],
        'blue'   => ['#eff6ff', '#dbeafe', '#2563eb', '#1e3a8a'],
        'cyan'   => ['#ecfeff', '#cffafe', '#0891b2', '#164e63'],
        'teal'   => ['#f0fdfa', '#ccfbf1', '#0d9488', '#134e4a'],
        'green'  => ['#ecfdf5', '#d1fae5', '#059669', '#064e3b'],
        'amber'  => ['#fffbeb', '#fef3c7', '#d97706', '#78350f'],
        'orange' => ['#fff7ed', '#ffedd5', '#ea580c', '#7c2d12'],
        'red'    => ['#fff1f2', '#ffe4e6', '#e11d48', '#881337'],
        'violet' => ['#f5f3ff', '#ede9fe', '#7c3aed', '#4c1d95'],
        'slate'  => ['#f8fafc', '#e2e8f0', '#475569', '#0f172a'],
    ];
    $vxTiles = $vxTiles ?? [];
@endphp
@once
<style>
    .vx-grid{display:grid;gap:10px;margin-bottom:14px}
    .vx-card{display:block;min-width:0;min-height:82px;padding:11px 13px;border-radius:12px;text-decoration:none;transition:.15s;box-shadow:0 1px 4px rgba(30,50,100,.08),0 1px 2px rgba(30,50,100,.04)}
    a.vx-card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(30,50,100,.1);text-decoration:none}
    .vx-card.active{outline:2px solid #4f46e5;outline-offset:-2px}
    .vx-top{display:flex;align-items:center;gap:9px}
    .vx-ic{width:32px;height:32px;flex:none;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px}
    .vx-num{font-size:22px;font-weight:900;line-height:1;font-variant-numeric:tabular-nums;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .vx-lbl{margin-top:7px;font-size:10.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .vx-sub{margin-top:2px;font-size:11px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    @media(max-width:1100px){.vx-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}}
    @media(max-width:640px){.vx-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
    .vx-btn{display:inline-flex;align-items:center;gap:6px;border-radius:8px;background:#4f46e5;padding:8px 16px;font-size:12px;font-weight:600;color:#fff;border:0;cursor:pointer;box-shadow:0 1px 2px rgba(15,23,42,.08);text-decoration:none}
    .vx-btn:hover{background:#4338ca;color:#fff;text-decoration:none}
    .vx-btn-ghost{background:#fff;color:#475569;border:1px solid #e2e8f0}
    .vx-btn-ghost:hover{background:#f8fafc;color:#1e293b}
    /* Filter row under the header: one line like Ticketing's bar — a picker
       keeps its own width instead of the full-row width Select2 gives it. */
    .vx-filter{flex-wrap:nowrap!important;overflow-x:auto}
    .vx-filter > *{flex:0 0 auto}
    .vx-filter > .relative, .vx-filter > .flex-1{flex:1 1 220px!important;min-width:180px}
    .vx-filter .select2-container{width:170px!important}
    .vx-filter > div[class*="w-"]{width:auto!important}
    .vx-filter select:not(.select2-hidden-accessible){width:auto;min-width:150px}
    .vx-filter input[type=month]{width:auto}
</style>
@endonce

@if($vxTiles)
<section class="vx-grid" style="grid-template-columns:repeat({{ count($vxTiles) }},minmax(0,1fr))">
    @foreach($vxTiles as $t)
        @php [$bg, $ibg, $fg, $num] = $vxTones[$t[4] ?? 'slate'] ?? $vxTones['slate']; $tag = ! empty($t[5]) ? 'a' : 'div'; @endphp
        <{{ $tag }} @if(! empty($t[5])) href="{{ $t[5] }}" @endif class="vx-card {{ ! empty($t[6]) ? 'active' : '' }}" style="background:{{ $bg }};border:1px solid {{ $ibg }}" title="{{ $t[0] }}{{ ! empty($t[2]) ? ' — ' . strip_tags($t[2]) : '' }}">
            <div class="vx-top"><span class="vx-ic" style="background:{{ $ibg }};color:{{ $fg }}"><i class="fas {{ $t[3] }}"></i></span><span class="vx-num" style="color:{{ $num }}">{{ $t[1] }}</span></div>
            <div class="vx-lbl" style="color:{{ $fg }}">{{ $t[0] }}</div>
            @if(! empty($t[2]))<div class="vx-sub">{{ $t[2] }}</div>@endif
        </{{ $tag }}>
    @endforeach
</section>
@endif

<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 flex-shrink-0"><i class="fas {{ $vxIcon }}"></i></span>
    <div class="flex-1 min-w-0">
        <h1 class="text-lg font-bold text-slate-900">{{ $vxTitle }}</h1>
        <p class="text-xs text-slate-500">{!! $vxSub ?? '' !!}</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap justify-end">{!! $vxAction ?? '' !!}</div>
</header>
