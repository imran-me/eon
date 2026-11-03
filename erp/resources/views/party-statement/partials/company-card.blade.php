{{-- Company Ledger card on the Party Statement dashboard, stacked under
     Customers. $cl = CompanyLedger::summary(), $fmt = the dashboard's formatter.
     Uses the dashboard's ps-card styles; the accent is its own. --}}
@php
    $clUrl = null;
    if (\Illuminate\Support\Facades\Route::has('role.party-statement.company')) {
        $clParams = ['role' => request()->route('role') ?: \Illuminate\Support\Str::slug(auth()->user()->getRoleNames()->first() ?? '')];
        if (request()->query('company')) $clParams['company'] = request()->query('company');
        $clUrl = route('role.party-statement.company', $clParams);
    }
    $clOk = ! empty($cl['installed']);
    $clNet = $clOk ? (float) $cl['net'] : 0.0;
@endphp
<div class="ps-card" style="flex:1;--ps-fg:#0f766e;--ps-soft:#f0fdfa;--ps-bar:#14b8a6;--ps-grad:linear-gradient(135deg,#14b8a6,#0f766e)">
    <div class="ps-card-h">
        <div class="ps-card-t">
            <span class="ps-ic"><i class="fas fa-building"></i></span>
            <div style="min-width:0;">
                <div class="ps-card-name">Company Ledger @if($clOk)<span class="ps-pill">{{ $cl['count'] }}</span>@endif</div>
                <div class="ps-card-meta">what we bear &middot; what we recover</div>
            </div>
        </div>
        @if($clUrl)
            <a href="{{ $clUrl }}" class="ps-open" data-no-prefetch><span>Open ledger</span> <i class="fas fa-arrow-right"></i></a>
        @endif
    </div>

    @if(! $clOk)
        <div class="ps-empty"><i class="fas fa-circle-info" style="color:#94a3b8"></i>Not set up on this server yet.</div>
    @else
        <div class="ps-month">
            <div class="ps-month-h">
                <div class="ps-fig-l"><i class="far fa-calendar" style="font-size:10px;"></i> This month</div>
                <span class="ps-tag">Net {{ $clNet < 0 ? '−' : '' }}{{ $fmt(abs($clNet)) }} all time</span>
            </div>
            <div class="ps-month-g">
                <div>
                    <div class="ps-k">Borne</div>
                    <div class="ps-v" style="color:#dc2626">{{ $fmt($cl['month']['borne']) }}</div>
                </div>
                <div>
                    <div class="ps-k">Recovered</div>
                    <div class="ps-v is-in">{{ $fmt($cl['month']['recovered']) }}</div>
                </div>
            </div>
        </div>
        @if($cl['recent']->isEmpty())
            <div class="ps-empty"><i class="fas fa-circle-check"></i>Nothing borne yet.</div>
        @else
            <div class="ps-top-h"><span class="ps-fig-l">Latest</span></div>
            <ul class="ps-top">
                @foreach($cl['recent'] as $r)
                    <li @if($clUrl) onclick="location.href='{{ $clUrl }}'" @endif title="{{ $r['head'] }}">
                        <span class="ps-who">
                            <span class="ps-nm"><span>{{ $r['details'] ?: $r['head'] }}</span></span>
                            <span class="ps-line"><span class="ps-code">{{ \Carbon\Carbon::parse($r['date'])->format('d/m/Y') }}{{ $r['party'] ? ' · ' . $r['party'] : '' }}</span></span>
                        </span>
                        <span>
                            <div class="ps-amt" style="color:{{ $r['side'] === 'borne' ? '#dc2626' : '#059669' }}">{{ $fmt(abs($r['borne'] ?: $r['recovered'])) }}</div>
                            <div class="ps-share">{{ $r['side'] === 'borne' ? 'borne' : 'recovered' }}</div>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
