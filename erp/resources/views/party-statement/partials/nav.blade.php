{{-- The module's gradient header band: title, subtitle and up to four KPI
     tiles. The section tabs are NOT here — they are layout/party-tabs, the
     same band every other desk uses, included by the page above this.

     $subtitle the line under the title, per page
     $kpis     up to four [label, value, sub] tiles on the right --}}

<style>
    /* Scoped to this module — every selector carries ps- */
    .ps-band { background: linear-gradient(90deg, #1d4ed8 0%, #4338ca 55%, #6d28d9 100%); }
    .ps-kpi { background: rgba(255,255,255,.10); border-radius: 12px; padding: 8px 16px; min-width: 128px; text-align: right; }
    .ps-kpi-l { font-size: 10px; letter-spacing: .08em; text-transform: uppercase; font-weight: 700; color: #c7d2fe; }
    .ps-kpi-v { font-size: 18px; font-weight: 800; color: #fff; line-height: 1.25; margin-top: 2px; }
    .ps-kpi-s { font-size: 10px; color: #c7d2fe; margin-top: 1px; }
</style>

<div class="ps-band px-2.5 py-4 shadow-lg">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                <i class="fas fa-book-open text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white leading-tight">Party Statement</h1>
                <p class="text-xs text-blue-200 mt-0.5">{{ $subtitle ?? 'Agent · Vendor · Customer ledgers' }}</p>
            </div>
        </div>
        {{-- $kpis: up to four [label, value, sub] tiles on the band's right. --}}
        <div class="flex items-center gap-3 flex-wrap">
            @foreach(($kpis ?? []) as $k)
                <div class="ps-kpi">
                    <div class="ps-kpi-l">{{ $k['label'] }}</div>
                    <div class="ps-kpi-v">{{ $k['value'] }}</div>
                    @if(!empty($k['sub']))<div class="ps-kpi-s">{{ $k['sub'] }}</div>@endif
                </div>
            @endforeach
        </div>
    </div>

</div>
