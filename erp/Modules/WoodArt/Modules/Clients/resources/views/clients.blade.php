{{--
    Wood Art · Clients — extends the suite shell.

    Transcribed from the reference module's authored markup
    (companies/woodart/modules/clients/frontend/template.html, with the
    empty-state copy from view.js:341/385/432): three screens. The module's own
    framing: Directory is who Woodart builds for; Portfolio is what each client
    is actually worth once projects are counted; Segments is which kind of
    client the business runs on.

    ALL THREE READ REAL DATA. Directory comes from `wa_clients`; Portfolio and
    Segments roll `wa_projects` up per client.

    ⚠ THE ROLL-UP JOINS ON THE CLIENT'S NAME, not an id — the reference's own
    design (see the module's migration). A project only counts towards a client
    when the two strings match exactly, so the Portfolio screen also reports
    projects naming somebody who is not in the register, rather than quietly
    dropping their value out of the totals.

    One reference block stays absent: Portfolio's STATE B card (view.js:360),
    whose "Won / Open quotes" columns need estimate data this module does not
    hold. Its project columns are built here; the quote columns wait.
--}}
@extends('woodart::layouts.suite')

@php use Modules\WoodArt\Modules\Projects\Models\Project; @endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.projects', ['role' => request()->route('role'), 'section' => 'active']) }}">
        <i class="bi bi-easel2-fill"></i> Projects</a>
    <a class="wap-btn wap-btn-primary"
       href="{{ route('role.woodart.clients.create', ['role' => request()->route('role')]) }}">
        <i class="bi bi-plus-lg"></i> New Client</a>
@endsection

@section('wa-view')

    @if(session('wa_status'))
    <div class="wap-flash"><i class="bi bi-check-circle-fill"></i> {{ session('wa_status') }}</div>
    @endif

    @if($section === 'directory')
        {{-- SCREEN 1 · DIRECTORY — who Woodart builds for. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Clients</span><span class="wap-kpi-ico"><i class="bi bi-person-hearts"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['clients'] }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">With Live Work</span><span class="wap-kpi-ico"><i class="bi bi-hammer"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['live'] }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Portfolio Value</span><span class="wap-kpi-ico"><i class="bi bi-cash-coin"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['value']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Repeat Clients</span><span class="wap-kpi-ico"><i class="bi bi-arrow-repeat"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['repeat'] }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Segments</span><span class="wap-kpi-ico"><i class="bi bi-tags"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['segments'] }}</div>
            </div>
        </div>

        {{-- The reference shows this only when somebody has never been given
             work (view.js:317). Same condition here. --}}
        @if($stats['idle'] > 0)
        <div class="wap-banner">
            <i class="bi bi-info-circle"></i>
            <div><strong>{{ $stats['idle'] }}</strong>
                {{ \Illuminate\Support\Str::plural('client', $stats['idle']) }}
                {{ $stats['idle'] === 1 ? 'has' : 'have' }} no project on record &mdash;
                worth a follow-up before they go cold.
                <a href="{{ route('role.woodart.clients', ['role' => request()->route('role'), 'section' => 'portfolio']) }}">See the portfolio</a>.</div>
        </div>
        @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-person-lines-fill"></i> Client Directory</h3>
                <span class="wap-card-sub">Homeowners, developers and corporates Woodart works with</span>
            </div>
            <div class="wap-card-body">
                @if($clients->isEmpty())
                <div class="wap-empty">
                    <i class="bi bi-person-hearts"></i>
                    <div class="wap-empty-title">No clients yet</div>
                    <div class="wap-empty-sub">Add the first homeowner, developer or corporate.</div>
                </div>
                @else
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Code</th><th>Client</th><th>Segment</th><th>Contact</th>
                                <th>Phone</th><th>Area</th><th>Client Since</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($clients as $c)
                            <tr>
                                <td class="wap-t-strong">{{ $c->ext_id }}</td>
                                <td class="wap-t-strong">{{ $c->name }}</td>
                                <td><span class="wap-badge">{{ $c->type }}</span></td>
                                <td>{{ $c->contact ?: '—' }}</td>
                                <td>{{ $c->phone ?: '—' }}</td>
                                <td>{{ $c->area ?: '—' }}</td>
                                <td>{{ $c->since?->format('d M Y') ?: '—' }}</td>
                                <td>
                                    <span class="wap-t-acts">
                                        <a class="wap-proj-act" title="Edit {{ $c->ext_id }}" aria-label="Edit {{ $c->name }}"
                                           href="{{ route('role.woodart.clients.edit', ['role' => request()->route('role'), 'client' => $c]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <a class="wap-proj-act wap-proj-act-bad" title="Remove {{ $c->ext_id }}" aria-label="Remove {{ $c->name }}"
                                           href="{{ route('role.woodart.clients.delete', ['role' => request()->route('role'), 'client' => $c]) }}">
                                            <i class="bi bi-trash3"></i></a>
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

    @elseif($section === 'portfolio')
        {{-- SCREEN 2 · PORTFOLIO — what each client is actually worth. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Contract Value</span><span class="wap-kpi-ico"><i class="bi bi-cash-stack"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['value']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Committed Cost</span><span class="wap-kpi-ico"><i class="bi bi-wallet2"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($rollup->sum('cost')) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Portfolio Margin</span><span class="wap-kpi-ico"><i class="bi bi-graph-up-arrow"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($rollup->sum('margin')) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Top Client</span><span class="wap-kpi-ico"><i class="bi bi-trophy"></i></span></div>
                <div class="wap-kpi-value" style="font-size:18px">{{ $stats['top'] ?: '—' }}</div>
            </div>
        </div>

        @php $withWork = $rollup->filter(fn ($r) => $r['projects'] > 0); @endphp

        @if($withWork->isEmpty())
        {{-- STATE A · nothing recorded against anybody yet (view.js:359). --}}
        <div class="wap-empty">
            <i class="bi bi-easel2"></i>
            <div class="wap-empty-title">No client work on record</div>
            <div class="wap-empty-sub">Projects raised against a client will roll up here.</div>
        </div>
        @else
        <div class="wap-banner">
            <i class="bi bi-info-circle"></i>
            <div>Value is the sum of each client's projects. A project counts towards a client
                when its client name matches the register exactly &mdash; the reference joins these
                by name rather than by id.</div>
        </div>
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-bar-chart-steps"></i> Client Portfolio</h3>
                <span class="wap-card-sub">Highest contract value first</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Client</th><th>Segment</th>
                                <th class="wap-t-num">Projects</th><th class="wap-t-num">Live</th>
                                <th class="wap-t-num">Contract Value</th><th class="wap-t-num">Cost</th>
                                <th class="wap-t-num">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rollup->sortByDesc('value') as $r)
                            <tr>
                                <td class="wap-t-strong">{{ $r['client']->name }}</td>
                                <td><span class="wap-badge">{{ $r['client']->type }}</span></td>
                                <td class="wap-t-num">{{ $r['projects'] }}</td>
                                <td class="wap-t-num">{{ $r['live'] }}</td>
                                <td class="wap-t-num">{{ Project::money($r['value']) }}</td>
                                <td class="wap-t-num">{{ Project::money($r['cost']) }}</td>
                                <td class="wap-t-num {{ $r['margin'] < 0 ? 'wap-t-bad' : 'wap-t-good' }}">
                                    {{ Project::money($r['margin']) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

    @else
        {{-- SCREEN 3 · SEGMENTS — which kind of client the business runs on. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Segments</span><span class="wap-kpi-ico"><i class="bi bi-tags"></i></span></div>
                <div class="wap-kpi-value">{{ count($segments) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Largest Segment</span><span class="wap-kpi-ico"><i class="bi bi-trophy"></i></span></div>
                <div class="wap-kpi-value" style="font-size:18px">
                    {{ collect($segments)->sortByDesc('value')->first()['label'] ?? '—' }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Avg Client Value</span><span class="wap-kpi-ico"><i class="bi bi-calculator"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['avg']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Never Given Work</span><span class="wap-kpi-ico"><i class="bi bi-slash-circle"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['idle'] }}</div>
            </div>
        </div>

        @if(empty($segments))
        <div class="wap-empty">
            <i class="bi bi-tags"></i>
            <div class="wap-empty-title">No segments</div>
            <div class="wap-empty-sub">Add clients to see the mix.</div>
        </div>
        @else
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-bar-chart-fill"></i> Value by Segment</h3>
                <span class="wap-card-sub">Share of total contract value</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-dist">
                    @foreach(collect($segments)->sortByDesc('value') as $s)
                    <div class="wap-dist-row">
                        <div class="wap-dist-main">
                            <div class="wap-dist-label"><span class="wap-dist-dot"></span>{{ $s['label'] }}
                                <span style="font-weight:400;color:var(--wap-text-mute)">
                                    &middot; {{ $s['clients'] }} {{ \Illuminate\Support\Str::plural('client', $s['clients']) }}</span>
                            </div>
                            <div class="wap-dist-track"><div class="wap-dist-bar" style="width:{{ $s['share'] }}%"></div></div>
                        </div>
                        <div class="wap-dist-figs">
                            <div class="wap-dist-value">{{ Project::money($s['value']) }}</div>
                            <div class="wap-dist-share">{{ $s['share'] }}% of value</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-table"></i> Segment Breakdown</h3>
                <span class="wap-card-sub">Clients, projects and value per segment</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Segment</th>
                                <th class="wap-t-num">Clients</th><th class="wap-t-num">Projects</th>
                                <th class="wap-t-num">Contract Value</th><th class="wap-t-num">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(collect($segments)->sortByDesc('value') as $s)
                            <tr>
                                <td class="wap-t-strong">{{ $s['label'] }}</td>
                                <td class="wap-t-num">{{ $s['clients'] }}</td>
                                <td class="wap-t-num">{{ $s['projects'] }}</td>
                                <td class="wap-t-num">{{ Project::money($s['value']) }}</td>
                                <td class="wap-t-num {{ $s['margin'] < 0 ? 'wap-t-bad' : 'wap-t-good' }}">
                                    {{ Project::money($s['margin']) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    @endif

@endsection
