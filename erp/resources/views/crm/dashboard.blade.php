@extends('layout.app')
@section('meta-information')
    <title>CRM Dashboard</title>
@endsection
@section('css')
<style>
/* ── Base Reset ── */
*, *::before, *::after { box-sizing: border-box; }

/* ── Page wrapper ── */
.crm-wrap { background: #f1f5f9; min-height: 100vh; padding: 24px 20px; }

/* ── Hero Header ── */
.crm-hero {
    background: linear-gradient(135deg, #1e40af 0%, #4f46e5 60%, #7c3aed 100%);
    border-radius: 18px;
    padding: 28px 32px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(79,70,229,.25);
}
.crm-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.crm-hero-title { font-size: 1.6rem; font-weight: 800; color: #fff; margin: 0; }
.crm-hero-sub   { font-size: .85rem; color: rgba(255,255,255,.7); margin-top: 4px; }
.crm-hero-btn {
    background: rgba(255,255,255,.18);
    border: 1.5px solid rgba(255,255,255,.35);
    color: #fff;
    padding: 9px 20px;
    border-radius: 10px;
    font-size: .85rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(4px);
    transition: background .2s, transform .15s;
}
.crm-hero-btn:hover { background: rgba(255,255,255,.28); transform: translateY(-1px); }

/* ── Stat Cards ── */
.stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 14px; margin-bottom: 24px; }
.stat-card {
    border-radius: 16px;
    padding: 18px 16px 14px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
    transition: transform .2s, box-shadow .2s;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.12); }
.stat-card-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: rgba(255,255,255,.22);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    margin-bottom: 12px;
    color: #fff;
}
.stat-card-num { font-size: 2rem; font-weight: 900; color: #fff; line-height: 1; }
.stat-card-label { font-size: .72rem; color: rgba(255,255,255,.8); margin-top: 5px; font-weight: 600; letter-spacing: .4px; text-transform: uppercase; }
.stat-card::after {
    content: '';
    position: absolute;
    right: -18px; bottom: -18px;
    width: 80px; height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,.08);
}

/* ── Section card ── */
.section-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e8ecf1;
    box-shadow: 0 1px 6px rgba(0,0,0,.05);
    overflow: hidden;
}
.section-header {
    padding: 16px 20px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
.section-title { font-size: .9rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.section-link  { font-size: .75rem; color: #6366f1; font-weight: 600; text-decoration: none; }
.section-link:hover { text-decoration: underline; }
.section-body  { padding: 0 20px 20px; }

/* ── Lead Type Pills ── */
.type-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 24px; }
@media (min-width: 768px) { .type-grid { grid-template-columns: repeat(4, 1fr); } }
.type-card {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1.5px solid;
    text-decoration: none;
    transition: transform .2s, box-shadow .2s;
    background: #fff;
}
.type-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.1); text-decoration: none; }
.type-icon { font-size: 1.6rem; line-height: 1; }
.type-num  { font-size: 1.4rem; font-weight: 900; line-height: 1; }
.type-lbl  { font-size: .72rem; font-weight: 600; margin-top: 2px; }

/* ── Pipeline bars ── */
.pipe-row { margin-bottom: 12px; }
.pipe-meta { display: flex; justify-content: space-between; font-size: .76rem; margin-bottom: 4px; }
.pipe-track { background: #f1f5f9; border-radius: 99px; height: 8px; overflow: hidden; }
.pipe-fill  { height: 8px; border-radius: 99px; transition: width .8s cubic-bezier(.4,0,.2,1); }

/* ── Funnel bars ── */
.funnel-row { margin-bottom: 8px; }
.funnel-label { font-size: .74rem; font-weight: 700; color: #475569; margin-bottom: 3px; display: flex; justify-content: space-between; }
.funnel-track { background: #f1f5f9; border-radius: 99px; height: 20px; overflow: hidden; }
.funnel-fill {
    height: 20px;
    border-radius: 99px;
    transition: width .8s cubic-bezier(.4,0,.2,1);
    min-width: 4%;
}

/* ── Alert items ── */
.alert-item {
    border-left: 3px solid;
    padding: 10px 13px;
    border-radius: 10px;
    margin-bottom: 7px;
    transition: transform .15s;
}
.alert-item:hover { transform: translateX(3px); }
.alert-item.overdue  { border-color: #ef4444; background: #fff5f5; }
.alert-item.today    { border-color: #f59e0b; background: #fffbeb; }
.alert-item.tomorrow { border-color: #3b82f6; background: #eff6ff; }
.alert-badge { font-size: .66rem; font-weight: 800; padding: 1px 7px; border-radius: 99px; display: inline-block; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .5px; }

/* ── Live lead cards ── */
.live-card {
    border: 1px solid #e8ecf1;
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 8px;
    transition: background .15s, box-shadow .15s;
    background: #fff;
}
.live-card:hover { background: #f8f9ff; box-shadow: 0 2px 8px rgba(99,102,241,.08); }

/* ── Pills ── */
.pill {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 99px;
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .2px;
}

/* ── Chart card ── */
.chart-card { background: #fff; border-radius: 16px; border: 1px solid #e8ecf1; padding: 20px; box-shadow: 0 1px 6px rgba(0,0,0,.05); }

/* ── Layouts ── */
.three-col { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 24px; }
@media (min-width: 1024px) { .three-col { grid-template-columns: 1fr 1fr 1fr; } }
.two-col { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 24px; }
@media (min-width: 1024px) { .two-col { grid-template-columns: 1fr 1fr; } }

/* ── Gradient helpers ── */
.g-blue   { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
.g-indigo { background: linear-gradient(135deg, #4f46e5, #4338ca); }
.g-slate  { background: linear-gradient(135deg, #475569, #334155); }
.g-purple { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
.g-green  { background: linear-gradient(135deg, #059669, #047857); }
.g-red    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
.g-amber  { background: linear-gradient(135deg, #d97706, #b45309); }
.g-teal   { background: linear-gradient(135deg, #0f766e, #0d9488); }
.g-orange { background: linear-gradient(135deg, #ea580c, #c2410c); }
</style>
@endsection

@section('main-content')
@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
@endphp

<div class="crm-wrap" style="max-width:1440px;margin:0 auto;">

    {{-- ── HERO HEADER ── --}}
    <div class="crm-hero">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;position:relative;z-index:1;">
            <div>
                <div style="font-size:.78rem;color:rgba(255,255,255,.65);font-weight:600;letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px;">
                    <i class="fas fa-chart-network me-1"></i> Customer Relationship Management
                </div>
                <h1 class="crm-hero-title">CRM Dashboard</h1>
                <p class="crm-hero-sub"><i class="far fa-calendar-alt me-1"></i>{{ now()->format('l, d F Y') }}</p>
            </div>
            <a href="{{ route('role.lead-manager.index', $role) }}" class="crm-hero-btn">
                <i class="fas fa-plus"></i> New Lead
            </a>
        </div>
    </div>

    {{-- ── STAT CARDS ── --}}
    <div class="stat-grid">
        @php
            $cards = [
                ['Total Leads',      $summary['total'],                'g-blue',   'fas fa-users'],
                ["Today's New",      $summary['today_new'],            'g-indigo', 'fas fa-user-plus'],
                ['New',              $summary['new'],                  'g-slate',  'fas fa-circle-dot'],
                ['Quoted',           $summary['quoted'],               'g-purple', 'fas fa-file-invoice'],
                ['Won',              $summary['won'],                  'g-green',  'fas fa-check-circle'],
                ['Lost',             $summary['lost'],                 'g-red',    'fas fa-times-circle'],
                ['Conversion',       $summary['conversion_rate'].'%',  'g-amber',  'fas fa-chart-line'],
                ['→ Project Done',   $wonAndConverted,                 'g-teal',   'fas fa-rocket'],
                ['Pending Convert',  $wonNotConverted,                 'g-orange', 'fas fa-hourglass-half'],
            ];
        @endphp
        @foreach($cards as [$label, $val, $grad, $icon])
        <div class="stat-card {{ $grad }}">
            <div class="stat-card-icon"><i class="{{ $icon }}"></i></div>
            <div class="stat-card-num">{{ $val }}</div>
            <div class="stat-card-label">{{ $label }}</div>
        </div>
        @endforeach
    </div>

    {{-- ── LEAD TYPE BREAKDOWN ── --}}
    <div class="type-grid">
        @php
            $typeMap = [
                'air_ticket' => ['✈️', 'Air Ticket', '#2563eb', '#dbeafe', '#1d4ed8'],
                'visa'       => ['🛂', 'Visa',        '#7c3aed', '#ede9fe', '#6d28d9'],
                'software'   => ['💻', 'Software',    '#059669', '#d1fae5', '#047857'],
                'other'      => ['📋', 'Other',       '#64748b', '#f1f5f9', '#475569'],
            ];
        @endphp
        @foreach($typeMap as $type => [$emoji, $label, $numColor, $bg, $border])
        <a href="{{ route('role.lead-manager.index', $role) }}?lead_type={{ $type }}"
           class="type-card"
           style="background:{{ $bg }};border-color:{{ $border }}22;">
            <div class="type-icon">{{ $emoji }}</div>
            <div>
                <div class="type-num" style="color:{{ $numColor }}">{{ $byType[$type] ?? 0 }}</div>
                <div class="type-lbl" style="color:{{ $numColor }}99">{{ $label }} Leads</div>
            </div>
        </a>
        @endforeach
    </div>

    {{-- ── THREE COLUMNS: Alerts · Pipeline · Visa Funnel ── --}}
    <div class="three-col">

        {{-- Follow-Up Alerts --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title"><i class="fas fa-bell" style="color:#f59e0b"></i> Follow-Up Alerts</span>
            </div>
            <div class="section-body" style="max-height:380px;overflow-y:auto;">

                @if($overdueFollowups->count())
                <span class="alert-badge" style="background:#fee2e2;color:#b91c1c;">Overdue &bull; {{ $overdueFollowups->count() }}</span>
                @foreach($overdueFollowups as $f)
                <div class="alert-item overdue">
                    <div style="font-size:.82rem;font-weight:700;color:#1e293b;">{{ $f->lead->name ?? '—' }}</div>
                    <div style="font-size:.72rem;color:#64748b;margin-top:2px;">{{ $f->lead->phone ?? '' }} &bull; {{ $f->type }}</div>
                    <div style="font-size:.71rem;color:#dc2626;margin-top:2px;"><i class="far fa-clock me-1"></i>{{ \Carbon\Carbon::parse($f->followup_date)->format('d M, H:i') }}</div>
                </div>
                @endforeach
                @endif

                @if($todayFollowups->count())
                <span class="alert-badge mt-2" style="background:#fef3c7;color:#92400e;">Today &bull; {{ $todayFollowups->count() }}</span>
                @foreach($todayFollowups as $f)
                <div class="alert-item today">
                    <div style="font-size:.82rem;font-weight:700;color:#1e293b;">{{ $f->lead->name ?? '—' }}</div>
                    <div style="font-size:.72rem;color:#64748b;margin-top:2px;">{{ $f->lead->phone ?? '' }} &bull; {{ $f->type }}</div>
                    <div style="font-size:.71rem;color:#d97706;margin-top:2px;"><i class="far fa-clock me-1"></i>{{ \Carbon\Carbon::parse($f->followup_date)->format('H:i') }}</div>
                </div>
                @endforeach
                @endif

                @if($tomorrowFollowups->count())
                <span class="alert-badge mt-2" style="background:#dbeafe;color:#1e40af;">Tomorrow &bull; {{ $tomorrowFollowups->count() }}</span>
                @foreach($tomorrowFollowups as $f)
                <div class="alert-item tomorrow">
                    <div style="font-size:.82rem;font-weight:700;color:#1e293b;">{{ $f->lead->name ?? '—' }}</div>
                    <div style="font-size:.72rem;color:#64748b;margin-top:2px;">{{ $f->lead->phone ?? '' }}</div>
                </div>
                @endforeach
                @endif

                @if($overdueFollowups->isEmpty() && $todayFollowups->isEmpty() && $tomorrowFollowups->isEmpty())
                <div style="text-align:center;padding:32px 0;color:#94a3b8;">
                    <i class="fas fa-check-circle" style="font-size:2rem;color:#4ade80;margin-bottom:8px;display:block;"></i>
                    <span style="font-size:.83rem;">All caught up — no pending follow-ups</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Lead Pipeline --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title"><i class="fas fa-filter" style="color:#6366f1"></i> Lead Pipeline</span>
            </div>
            <div class="section-body">
                @php
                    $stages = [
                        'new'           => ['New',         '#94a3b8'],
                        'contacted'     => ['Contacted',   '#6366f1'],
                        'qualified'     => ['Qualified',   '#eab308'],
                        'proposal_sent' => ['Quoted',      '#a855f7'],
                        'negotiation'   => ['Negotiation', '#f97316'],
                        'won'           => ['Won ✓',       '#22c55e'],
                        'lost'          => ['Lost ✗',      '#ef4444'],
                    ];
                    $pipeTotal = max(array_sum($pipeline), 1);
                @endphp
                @foreach($stages as $key => [$label, $color])
                @php $pct = $pipeTotal ? round(($pipeline[$key] ?? 0) / $pipeTotal * 100) : 0; @endphp
                <div class="pipe-row">
                    <div class="pipe-meta">
                        <span style="font-weight:700;color:#334155;font-size:.78rem;">{{ $label }}</span>
                        <span style="font-weight:800;color:{{ $color }};font-size:.78rem;">{{ $pipeline[$key] ?? 0 }}
                            <span style="font-weight:400;color:#94a3b8;font-size:.68rem;">({{ $pct }}%)</span>
                        </span>
                    </div>
                    <div class="pipe-track">
                        <div class="pipe-fill" style="width:{{ $pct }}%;background:{{ $color }};"></div>
                    </div>
                </div>
                @endforeach

                {{-- Won → Project conversion mini-summary --}}
                @if(($pipeline['won'] ?? 0) > 0)
                <div style="margin-top:16px;padding:12px;background:#f0fdf4;border-radius:10px;border:1px solid #bbf7d0;">
                    <p style="font-size:.72rem;font-weight:800;color:#14532d;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px;">Won → Project Status</p>
                    <div style="display:flex;gap:8px;">
                        <div style="flex:1;background:#dcfce7;border-radius:8px;padding:8px 10px;text-align:center;">
                            <div style="font-size:1.1rem;font-weight:900;color:#15803d;">{{ $wonAndConverted }}</div>
                            <div style="font-size:.65rem;color:#166534;font-weight:600;">Converted</div>
                        </div>
                        <div style="flex:1;background:#fef9c3;border-radius:8px;padding:8px 10px;text-align:center;">
                            <div style="font-size:1.1rem;font-weight:900;color:#a16207;">{{ $wonNotConverted }}</div>
                            <div style="font-size:.65rem;color:#92400e;font-weight:600;">Pending</div>
                        </div>
                    </div>
                    @php
                        $convPct = ($pipeline['won'] ?? 0) > 0 ? round(($wonAndConverted / ($pipeline['won'] ?? 1)) * 100) : 0;
                    @endphp
                    <div style="margin-top:8px;">
                        <div style="display:flex;justify-content:space-between;font-size:.68rem;color:#64748b;margin-bottom:3px;">
                            <span>Project conversion rate</span><span style="font-weight:800;color:#15803d;">{{ $convPct }}%</span>
                        </div>
                        <div style="background:#d1fae5;border-radius:99px;height:6px;overflow:hidden;">
                            <div style="width:{{ $convPct }}%;background:#22c55e;height:100%;border-radius:99px;transition:.8s;"></div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Visa Funnel --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title"><i class="fas fa-passport" style="color:#7c3aed"></i> Visa Process Funnel</span>
            </div>
            <div class="section-body">
                @php
                    $funnelColors = [
                        'Applications'   => '#3b82f6',
                        'Docs Collected' => '#6366f1',
                        'Complete'       => '#8b5cf6',
                        'Submitted'      => '#f59e0b',
                        'Approved (Won)' => '#10b981',
                        'Rejected (Lost)'=> '#ef4444',
                    ];
                    $maxFunnel = max(max(array_values($visaFunnelData)), 1);
                @endphp
                @foreach($visaFunnelData as $label => $count)
                @php $fpct = max(round($count / $maxFunnel * 100), 4); $fclr = $funnelColors[$label] ?? '#94a3b8'; @endphp
                <div class="funnel-row">
                    <div class="funnel-label">
                        <span>{{ $label }}</span>
                        <span style="color:{{ $fclr }};font-weight:800;">{{ $count }}</span>
                    </div>
                    <div class="funnel-track">
                        <div class="funnel-fill" style="width:{{ $fpct }}%;background:{{ $fclr }};"></div>
                    </div>
                </div>
                @endforeach

                <div style="margin-top:18px;padding-top:14px;border-top:1px solid #f1f5f9;">
                    <p style="font-size:.73rem;font-weight:800;color:#475569;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Payment Status</p>
                    @foreach(['pending'=>['Pending','#f59e0b'],'partial'=>['Partial Paid','#3b82f6'],'paid'=>['Fully Paid','#22c55e']] as $key=>[$lbl,$clr])
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <span style="display:flex;align-items:center;gap:7px;font-size:.78rem;color:#475569;">
                            <span style="width:8px;height:8px;border-radius:50%;background:{{ $clr }};display:inline-block;"></span>
                            {{ $lbl }}
                        </span>
                        <span style="font-size:.82rem;font-weight:800;color:{{ $clr }};">{{ $visaPayment[$key] ?? 0 }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

    {{-- ── WON → PROJECT CONVERSION SECTION ── --}}
    <div class="two-col">

        {{-- Pending Conversion (Won but no project yet) --}}
        <div class="section-card" style="border-top:3px solid #f59e0b;">
            <div class="section-header">
                <span class="section-title">
                    <i class="fas fa-hourglass-half" style="color:#f59e0b;"></i>
                    Pending Conversion
                    @if($wonNotConverted > 0)
                    <span style="background:#fef3c7;color:#92400e;font-size:.68rem;font-weight:700;padding:1px 8px;border-radius:99px;margin-left:4px;">{{ $wonNotConverted }}</span>
                    @endif
                </span>
                <a href="{{ route('role.lead-manager.index', $role) }}?status=won" class="section-link" style="color:#d97706;">View All →</a>
            </div>
            <div class="section-body" style="max-height:380px;overflow-y:auto;">
                @forelse($pendingConversion as $lead)
                <div class="live-card" style="border-left:3px solid #fbbf24;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.85rem;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $lead->name }}</div>
                            <div style="font-size:.72rem;color:#64748b;margin-top:2px;">
                                <i class="fas fa-phone" style="font-size:.6rem;"></i> {{ $lead->phone }}
                                @if($lead->customer) &nbsp;· {{ $lead->customer->name }} @endif
                            </div>
                            <div style="font-size:.7rem;color:#64748b;margin-top:2px;">
                                <i class="fas fa-tag" style="font-size:.6rem;"></i> {{ $lead->lead_type_label }}
                            </div>
                        </div>
                        <div style="flex-shrink:0;">
                            <form method="POST"
                                  action="{{ route('role.lead-manager.convert-to-project', ['role' => $role, 'lead' => $lead->id]) }}"
                                  onsubmit="return confirm('{{ addslashes($lead->name) }} কে project এ convert করবেন?')">
                                @csrf
                                <input type="hidden" name="project_name" value="{{ $lead->name }}">
                                <button type="submit"
                                    style="background:linear-gradient(135deg,#0f766e,#14b8a6);color:white;border:none;padding:5px 12px;border-radius:8px;font-size:.72rem;font-weight:700;cursor:pointer;white-space:nowrap;">
                                    <i class="fas fa-bolt"></i> Convert
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <div style="text-align:center;padding:32px 0;color:#94a3b8;">
                    <i class="fas fa-check-circle" style="font-size:2rem;color:#4ade80;display:block;margin-bottom:8px;"></i>
                    <span style="font-size:.83rem;">সব Won lead convert হয়ে গেছে!</span>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Recently Converted (Won → Project) --}}
        <div class="section-card" style="border-top:3px solid #14b8a6;">
            <div class="section-header">
                <span class="section-title">
                    <i class="fas fa-rocket" style="color:#0d9488;"></i>
                    Converted Projects
                    @if($wonAndConverted > 0)
                    <span style="background:#ccfbf1;color:#134e4a;font-size:.68rem;font-weight:700;padding:1px 8px;border-radius:99px;margin-left:4px;">{{ $wonAndConverted }}</span>
                    @endif
                </span>
                <a href="{{ route('role.projects.index', $role) }}" class="section-link" style="color:#0d9488;">View All →</a>
            </div>
            <div class="section-body" style="max-height:380px;overflow-y:auto;">
                @forelse($recentConverted as $lead)
                @php $proj = $lead->project; @endphp
                <div class="live-card" style="border-left:3px solid #2dd4bf;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.85rem;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                {{ $proj->project_name ?? $lead->name }}
                            </div>
                            <div style="font-size:.72rem;color:#0f766e;margin-top:2px;font-weight:600;">
                                <i class="fas fa-link" style="font-size:.6rem;"></i> Lead: {{ $lead->name }}
                            </div>
                            @if($lead->customer)
                            <div style="font-size:.7rem;color:#64748b;margin-top:1px;">
                                <i class="fas fa-building" style="font-size:.6rem;"></i> {{ $lead->customer->name }}
                            </div>
                            @endif
                        </div>
                        <div style="flex-shrink:0;text-align:right;">
                            @if($proj)
                            <a href="{{ route('role.projects.show', ['role' => $role, 'project' => $proj->id]) }}"
                               style="background:#ccfbf1;color:#0f766e;font-size:.7rem;font-weight:700;padding:4px 10px;border-radius:8px;text-decoration:none;display:inline-block;white-space:nowrap;">
                                <i class="fas fa-external-link-alt" style="font-size:.6rem;"></i> Project দেখুন
                            </a>
                            @endif
                            <div style="margin-top:4px;">
                                <span style="background:#dcfce7;color:#15803d;font-size:.66rem;font-weight:700;padding:2px 7px;border-radius:99px;">
                                    Ongoing
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div style="text-align:center;padding:32px 0;color:#94a3b8;">
                    <i class="fas fa-folder-open" style="font-size:2rem;display:block;margin-bottom:8px;color:#cbd5e1;"></i>
                    <span style="font-size:.83rem;">এখনো কোনো project convert হয়নি</span>
                </div>
                @endforelse
            </div>
        </div>

    </div>

    {{-- ── LIVE LEADS ── --}}
    @php
        $statusColors = ['new'=>['#e2e8f0','#475569'],'contacted'=>['#e0e7ff','#4338ca'],'qualified'=>['#fef9c3','#a16207'],'proposal_sent'=>['#f3e8ff','#7e22ce'],'negotiation'=>['#ffedd5','#c2410c'],'won'=>['#dcfce7','#15803d'],'lost'=>['#fee2e2','#b91c1c']];
        $docColors    = ['pending'=>['#fef9c3','#a16207'],'collected'=>['#dbeafe','#1d4ed8'],'complete'=>['#e0e7ff','#4338ca'],'submitted'=>['#d1fae5','#065f46']];
    @endphp
    <div class="two-col">

        {{-- Air Ticket --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">✈️ Air Ticket Live Leads</span>
                <a href="{{ route('role.lead-manager.index', $role) }}?lead_type=air_ticket" class="section-link">View All <i class="fas fa-arrow-right" style="font-size:.65rem;"></i></a>
            </div>
            <div class="section-body" style="max-height:380px;overflow-y:auto;">
                @forelse($airTicketLeads as $lead)
                <div class="live-card">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.85rem;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $lead->name }}</div>
                            <div style="font-size:.73rem;color:#64748b;margin-top:2px;"><i class="fas fa-phone" style="font-size:.65rem;"></i> {{ $lead->phone }}</div>
                            @if($lead->airTicket)
                            <div style="font-size:.73rem;font-weight:700;color:#2563eb;margin-top:4px;">
                                {{ $lead->airTicket->route_display }}
                                @if($lead->airTicket->travel_date) &nbsp;· {{ $lead->airTicket->travel_date->format('d M') }} @endif
                                @if($lead->airTicket->pax_count) &nbsp;· {{ $lead->airTicket->pax_count }} Pax @endif
                            </div>
                            @endif
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            @php $sc = $statusColors[$lead->status] ?? ['#f1f5f9','#475569']; @endphp
                            <span class="pill" style="background:{{ $sc[0] }};color:{{ $sc[1] }};">
                                {{ ucfirst(str_replace('_', ' ', $lead->status)) }}
                            </span>
                            @if($lead->assignedEmployee)
                            <div style="font-size:.7rem;color:#94a3b8;margin-top:4px;">{{ $lead->assignedEmployee->name }}</div>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div style="text-align:center;padding:32px 0;color:#94a3b8;font-size:.83rem;">
                    <i class="fas fa-plane-slash" style="font-size:1.8rem;display:block;margin-bottom:8px;color:#cbd5e1;"></i>
                    No active air ticket leads
                </div>
                @endforelse
            </div>
        </div>

        {{-- Visa Applications --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">🛂 Visa Live Applications</span>
                <a href="{{ route('role.lead-manager.index', $role) }}?lead_type=visa" class="section-link" style="color:#7c3aed;">View All <i class="fas fa-arrow-right" style="font-size:.65rem;"></i></a>
            </div>
            <div class="section-body" style="max-height:380px;overflow-y:auto;">
                @forelse($visaLeads as $lead)
                <div class="live-card">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.85rem;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $lead->name }}</div>
                            <div style="font-size:.73rem;color:#64748b;margin-top:2px;"><i class="fas fa-phone" style="font-size:.65rem;"></i> {{ $lead->phone }}</div>
                            @if($lead->visa)
                            <div style="font-size:.73rem;font-weight:700;color:#7c3aed;margin-top:4px;">
                                🌍 {{ $lead->visa->country->name ?? '—' }} &nbsp;· {{ ucfirst($lead->visa->visa_type) }}
                            </div>
                            @php $dc = $docColors[$lead->visa->document_status] ?? ['#f1f5f9','#475569']; @endphp
                            <span class="pill mt-1" style="background:{{ $dc[0] }};color:{{ $dc[1] }};">
                                Docs: {{ ucfirst($lead->visa->document_status) }}
                            </span>
                            @endif
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            @php $sc = $statusColors[$lead->status] ?? ['#f1f5f9','#475569']; @endphp
                            <span class="pill" style="background:{{ $sc[0] }};color:{{ $sc[1] }};">
                                {{ ucfirst(str_replace('_', ' ', $lead->status)) }}
                            </span>
                            @if($lead->assignedEmployee)
                            <div style="font-size:.7rem;color:#94a3b8;margin-top:4px;">{{ $lead->assignedEmployee->name }}</div>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div style="text-align:center;padding:32px 0;color:#94a3b8;font-size:.83rem;">
                    <i class="fas fa-passport" style="font-size:1.8rem;display:block;margin-bottom:8px;color:#cbd5e1;"></i>
                    No active visa applications
                </div>
                @endforelse
            </div>
        </div>

    </div>

    {{-- ── MISSING DOCUMENT ALERT ── --}}
    @if($missingDocLeads->count() > 0)
    <div class="section-card" style="border-left:4px solid #ef4444;margin-bottom:20px;">
        <div class="section-header" style="background:linear-gradient(135deg,#fff5f5,#fff);">
            <span class="section-title" style="color:#b91c1c;">
                <i class="fas fa-exclamation-circle" style="color:#ef4444;"></i>
                Missing Mandatory Documents
                <span style="display:inline-flex;align-items:center;justify-content:center;background:#ef4444;color:#fff;font-size:.7rem;font-weight:700;border-radius:999px;padding:1px 7px;margin-left:6px;">{{ $missingDocLeads->count() }}</span>
            </span>
            <a href="{{ route('role.lead-manager.index', $role) }}?lead_type=visa" class="section-link" style="color:#b91c1c;">View All Visa Leads <i class="fas fa-arrow-right" style="font-size:.65rem;"></i></a>
        </div>
        <div class="section-body" style="max-height:320px;overflow-y:auto;">
            @foreach($missingDocLeads as $lead)
            <div class="live-card" style="border-left:3px solid #fca5a5;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:.85rem;font-weight:700;color:#1e293b;">{{ $lead->name }}</div>
                        <div style="font-size:.73rem;color:#64748b;"><i class="fas fa-phone" style="font-size:.65rem;"></i> {{ $lead->phone }}</div>
                        @if($lead->visa)
                        <div style="font-size:.73rem;font-weight:700;color:#7c3aed;margin-top:3px;">
                            🌍 {{ $lead->visa->country->name ?? '—' }} · {{ ucfirst($lead->visa->visa_type) }}
                        </div>
                        @endif
                        <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;">
                            @foreach($lead->visaDocuments as $doc)
                            <span style="font-size:.68rem;background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:20px;font-weight:600;">
                                <i class="fas fa-times-circle" style="font-size:.6rem;"></i> {{ $doc->document_name }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    <div style="flex-shrink:0;">
                        <span style="font-size:.7rem;background:#fee2e2;color:#b91c1c;padding:3px 8px;border-radius:20px;font-weight:700;">
                            {{ $lead->visaDocuments->count() }} missing
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── MONTHLY TREND CHART ── --}}
    <div class="chart-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <span style="font-size:.9rem;font-weight:800;color:#1e293b;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-chart-bar" style="color:#6366f1;"></i> Lead Trend — Last 6 Months
            </span>
            <span style="font-size:.73rem;color:#94a3b8;">Updated {{ now()->format('d M Y') }}</span>
        </div>
        <canvas id="trendChart" height="60"></canvas>
    </div>

</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trendLabels = @json(array_column($monthlyTrend, 'label'));
const trendTotal  = @json(array_column($monthlyTrend, 'total'));
const trendWon    = @json(array_column($monthlyTrend, 'won'));

new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: trendLabels,
        datasets: [
            {
                label: 'Total Leads',
                data: trendTotal,
                backgroundColor: 'rgba(99,102,241,.75)',
                borderRadius: 8,
                borderSkipped: false,
            },
            {
                label: 'Won',
                data: trendWon,
                backgroundColor: 'rgba(34,197,94,.75)',
                borderRadius: 8,
                borderSkipped: false,
            },
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
                labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, font: { size: 12, weight: '600' } }
            },
            tooltip: { cornerRadius: 8, padding: 10 }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 12 } } },
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, font: { size: 11 } },
                grid: { color: '#f1f5f9', lineWidth: 1 }
            }
        }
    }
});
</script>
@endsection
