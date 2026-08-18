{{--
    Wood Art · Site & Install (installation) — extends the suite shell.

    Transcribed from the reference module's authored markup
    (companies/woodart/modules/installation/frontend/template.html, with the
    empty-state copy from view.js:364/405): three screens. Schedule is every
    site visit and who is going; Snag List is what is stopping handover; Teams
    is who is where and which crew carries the snags.

    THE SNAG COUNT shown everywhere is $i->open_snags, never $i->snags — the
    model reads whichever shape a record carries (a plain number, or an itemised
    list whose un-done items are counted). See Install for why both exist.

    NOTHING HERE BILLS. Handover billing belongs to Projects; a second posting
    path would double-bill every project (reference decision I4).

    NO <script> ANYWHERE IN THIS FILE — woodart-nav.js replaces [data-wa-view]
    wholesale on navigation, so a script here would never re-run.
--}}
@extends('woodart::layouts.suite')

@php
    $waRole = request()->route('role');
    $waSched = route('role.woodart.installation', ['role' => $waRole, 'section' => 'schedule']);

    $waStatusClass = fn (string $s) => match ($s) {
        'Handover'    => 'wap-badge-good',
        'Snagging'    => 'wap-badge-bad',
        'In Progress' => 'wap-badge-warn',
        default       => '',
    };
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.production', ['role' => $waRole, 'section' => 'jobs']) }}">
        <i class="bi bi-hammer"></i> Workshop</a>
    <a class="wap-btn wap-btn-primary" href="{{ route('role.woodart.installation.create', ['role' => $waRole]) }}">
        <i class="bi bi-plus-lg"></i> Schedule Install</a>
@endsection

@section('wa-view')

    @if($section === 'schedule')
        {{-- SCREEN 1 · SCHEDULE — every site visit, when it is and who is going. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Installs</span><span class="wap-kpi-ico"><i class="bi bi-truck"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['installs']) }}</div>
                @if($stats['installs'])<div class="wap-kpi-foot">{{ number_format($stats['open']) }} still open</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">On Site Now</span><span class="wap-kpi-ico"><i class="bi bi-tools"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['active']) }}</div>
                @if($stats['active'])<div class="wap-kpi-foot">In progress or snagging</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Open Snags</span><span class="wap-kpi-ico"><i class="bi bi-exclamation-diamond"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['snags']) }}</div>
                @if($stats['sites'])<div class="wap-kpi-foot">across {{ number_format($stats['sites']) }} {{ \Illuminate\Support\Str::plural('site', $stats['sites']) }}</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Handed Over</span><span class="wap-kpi-ico"><i class="bi bi-flag-fill"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['handover']) }}</div>
                @if($stats['installs'])<div class="wap-kpi-foot">{{ $stats['rate'] }}% of all visits</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Teams Out</span><span class="wap-kpi-ico"><i class="bi bi-people-fill"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['teams']) }}</div>
            </div>
        </div>

        {{-- The reference prints this only when something needs attention
             (view.js:333): a site snagging, or open work past its date. --}}
        @if($stats['attention'] > 0)
        <div class="wap-banner" style="background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
            <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
            <div><strong>{{ number_format($stats['attention']) }}</strong>
                {{ \Illuminate\Support\Str::plural('site', $stats['attention']) }} need attention &mdash;
                snagging or past the visit date.</div>
        </div>
        @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-calendar2-week"></i> Install Schedule</h3>
                <span class="wap-card-sub">Every site visit &mdash; team, date, status and open snags</span>
            </div>
            <div class="wap-card-body">

                @include('woodart::partials.wa-search', [
                    'action'      => $waSched,
                    'q'           => $q,
                    'placeholder' => 'Search site, project, team or status…',
                ])

                @if($installs->isEmpty())
                <div class="wap-empty">
                    <i class="bi bi-{{ $q !== '' ? 'search' : 'truck' }}"></i>
                    <div class="wap-empty-title">
                        {{ $q !== '' ? 'Nothing matches “' . $q . '”' : 'No installs scheduled' }}
                    </div>
                    <div class="wap-empty-sub">
                        {{ $q !== ''
                            ? 'Searched site, code, project, team and status.'
                            : 'Schedule the first site visit.' }}
                    </div>
                </div>
                @else
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Code</th><th>Site</th><th>Project</th><th>Team</th>
                                <th>Date</th><th class="wap-t-num">Snags</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($installs as $i)
                            @php
                                // An orphan is an install whose project id no
                                // longer resolves. Blank is NOT an orphan.
                                $orphan = $i->project && ! \array_key_exists($i->project, $projectNames);
                                $days   = $i->days_left;
                            @endphp
                            <tr>
                                <td class="wap-t-strong">{{ $i->ext_id }}</td>
                                <td class="wap-t-strong">{{ $i->site }}</td>
                                <td>
                                    @if(! $i->project)
                                        <span class="wap-caption">Not linked</span>
                                    @elseif($orphan)
                                        {{ $i->project }}
                                        <span class="wap-badge wap-badge-bad" style="margin-left:6px"
                                              title="This project no longer exists — the visit is kept so the problem is visible">orphan</span>
                                    @else
                                        {{ $i->project }}
                                        <span class="wap-caption" style="display:block">{{ $projectNames[$i->project] }}</span>
                                    @endif
                                </td>
                                <td>{{ $i->team ?: '—' }}</td>
                                <td class="{{ $i->is_overdue ? 'wap-t-bad' : '' }}">
                                    @if($i->date)
                                        {{ $i->date->format('d M Y') }}
                                        @if($i->is_overdue)
                                            <span class="wap-caption" style="display:block">{{ abs($days) }}
                                                {{ \Illuminate\Support\Str::plural('day', abs($days)) }} late</span>
                                        @elseif($i->is_open && $days !== null && $days <= 7)
                                            <span class="wap-caption" style="display:block">{{ $days === 0 ? 'today' : 'in ' . $days . ' ' . \Illuminate\Support\Str::plural('day', $days) }}</span>
                                        @endif
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="wap-t-num {{ $i->open_snags ? 'wap-t-bad' : '' }}">
                                    {{ $i->open_snags ?: '—' }}
                                </td>
                                <td>
                                    <span class="wap-badge {{ $waStatusClass($i->status) }}">{{ $i->status }}</span>
                                    {{-- Handed over with snags still open is a real
                                         state the business wants to SEE, not hide. --}}
                                    @if(! $i->is_open && $i->open_snags > 0)
                                    <span class="wap-badge wap-badge-warn" style="margin-left:6px"
                                          title="Handed over with snags still open">unclean</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="wap-t-acts">
                                        <a class="wap-proj-act" title="Edit {{ $i->ext_id }}" aria-label="Edit {{ $i->site }}"
                                           href="{{ route('role.woodart.installation.edit', ['role' => $waRole, 'install' => $i]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <a class="wap-proj-act wap-proj-act-bad" title="Remove {{ $i->ext_id }}" aria-label="Remove {{ $i->site }}"
                                           href="{{ route('role.woodart.installation.delete', ['role' => $waRole, 'install' => $i]) }}">
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

    @elseif($section === 'snags')
        {{-- SCREEN 2 · SNAG LIST — what is stopping handover, worst site first. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Open Snags</span><span class="wap-kpi-ico"><i class="bi bi-exclamation-diamond"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['snags']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Sites Snagging</span><span class="wap-kpi-ico"><i class="bi bi-geo-alt-fill"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['sites']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Worst Site</span><span class="wap-kpi-ico"><i class="bi bi-fire"></i></span></div>
                <div class="wap-kpi-value {{ $stats['worst'] ? '' : 'wap-kpi-unknown' }}">{{ $stats['worst'] ?? '—' }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Clean Handovers</span><span class="wap-kpi-ico"><i class="bi bi-check2-circle"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['clean']) }}</div>
                @if($stats['handover'])<div class="wap-kpi-foot">of {{ number_format($stats['handover']) }} handed over</div>@endif
            </div>
        </div>

        @if($queue->isEmpty())
            {{-- STATE A · nothing outstanding. Bare, not in a card — the
                 reference authors it as a plain empty state. --}}
            <div class="wap-empty">
                <i class="bi bi-check2-circle"></i>
                <div class="wap-empty-title">No open snags</div>
                <div class="wap-empty-sub">
                    {{ $stats['installs']
                        ? 'Every site is either clean or already handed over. Nothing is blocking a client sign-off.'
                        : 'Schedule a site visit and anything outstanding appears here.' }}
                </div>
            </div>
        @else
            {{-- STATE B · the handover queue (view.js:382). --}}
            <div class="wap-card">
                <div class="wap-card-head">
                    <h3><i class="bi bi-list-ol"></i> Handover Queue</h3>
                    <span class="wap-card-sub">Worst site first &mdash; what is stopping sign-off</span>
                </div>
                <div class="wap-card-body">
                    <div class="wap-table-wrap">
                        <table class="wap-table">
                            <thead>
                                <tr>
                                    <th>Site</th><th>Project</th><th>Team</th>
                                    <th class="wap-t-num">Open Snags</th><th>Status</th><th>Date</th><th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($queue as $i)
                                <tr>
                                    <td class="wap-t-strong">{{ $i->site }}
                                        <span class="wap-caption" style="display:block">{{ $i->ext_id }}</span></td>
                                    <td>
                                        @if(! $i->project)
                                            <span class="wap-caption">Not linked</span>
                                        @elseif(! \array_key_exists($i->project, $projectNames))
                                            {{ $i->project }}
                                            <span class="wap-badge wap-badge-bad" style="margin-left:6px">orphan</span>
                                        @else
                                            {{ $projectNames[$i->project] }}
                                        @endif
                                    </td>
                                    <td>{{ $i->team ?: '—' }}</td>
                                    <td class="wap-t-num wap-t-bad">{{ number_format($i->open_snags) }}</td>
                                    <td>
                                        <span class="wap-badge {{ $waStatusClass($i->status) }}">{{ $i->status }}</span>
                                        @if(! $i->is_open)
                                        <span class="wap-badge wap-badge-warn" style="margin-left:6px"
                                              title="Handed over with snags still open">unclean</span>
                                        @endif
                                    </td>
                                    <td class="{{ $i->is_overdue ? 'wap-t-bad' : '' }}">{{ $i->date?->format('d M Y') ?: '—' }}</td>
                                    <td>
                                        <span class="wap-t-acts">
                                            <a class="wap-proj-act" title="Edit {{ $i->ext_id }}" aria-label="Edit {{ $i->site }}"
                                               href="{{ route('role.woodart.installation.edit', ['role' => $waRole, 'install' => $i]) }}">
                                                <i class="bi bi-pencil"></i></a>
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="wap-banner" style="margin-top:16px">
                        <i class="bi bi-info-circle"></i>
                        <div>This screen counts and ranks snags; it does not edit them item by item.
                            The count comes from the visit record &mdash; change it on the visit.</div>
                    </div>
                </div>
            </div>
        @endif

    @else
        {{-- SCREEN 3 · TEAMS — who is where, and who is carrying the snags. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Teams</span><span class="wap-kpi-ico"><i class="bi bi-people-fill"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['allTeams']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Busiest Team</span><span class="wap-kpi-ico"><i class="bi bi-fire"></i></span></div>
                <div class="wap-kpi-value {{ $stats['top'] ? '' : 'wap-kpi-unknown' }}">{{ $stats['top'] ?? '—' }}</div>
                @unless($stats['top'])<div class="wap-kpi-foot">Nothing open anywhere</div>@endunless
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Open Sites</span><span class="wap-kpi-ico"><i class="bi bi-geo-alt-fill"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['open']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Handover Rate</span><span class="wap-kpi-ico"><i class="bi bi-percent"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['rate'] }}%</div>
            </div>
        </div>

        @if(empty($teams))
            {{-- STATE A · nothing scheduled. --}}
            <div class="wap-empty">
                <i class="bi bi-people"></i>
                <div class="wap-empty-title">No installs scheduled</div>
                <div class="wap-empty-sub">Schedule a site visit and the team workload fills in.</div>
            </div>
        @else
            {{-- STATE B · the crew load (view.js:424). Ranked by OPEN sites —
                 a handed-over site is history and does not occupy a crew. --}}
            <div class="wap-card">
                <div class="wap-card-head">
                    <h3><i class="bi bi-bar-chart-line"></i> Open Sites by Team</h3>
                    <span class="wap-card-sub">Busiest first &mdash; the bar is relative to the busiest crew</span>
                </div>
                <div class="wap-card-body">
                    <div class="wap-dist">
                        @foreach($teams as $row)
                        <div class="wap-dist-row">
                            <div class="wap-dist-main">
                                <span class="wap-dist-dot"></span>
                                <span class="wap-dist-label">{{ $row['name'] }}</span>
                                <span class="wap-dist-value">{{ number_format($row['open']) }} open</span>
                            </div>
                            <div class="wap-dist-track">
                                <div class="wap-dist-bar" style="width: {{ $row['share'] }}%"></div>
                            </div>
                            <div class="wap-dist-figs">
                                <span class="wap-dist-share">{{ number_format($row['sites']) }} total</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="wap-card">
                <div class="wap-card-head">
                    <h3><i class="bi bi-people"></i> Team Detail</h3>
                    <span class="wap-card-sub">Who is carrying the snags and the late work</span>
                </div>
                <div class="wap-card-body">
                    <div class="wap-table-wrap">
                        <table class="wap-table">
                            <thead>
                                <tr>
                                    <th>Team</th>
                                    <th class="wap-t-num">Open</th><th class="wap-t-num">Snags</th>
                                    <th class="wap-t-num">Overdue</th><th class="wap-t-num">Handed Over</th>
                                    <th class="wap-t-num">Sites</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($teams as $row)
                                <tr>
                                    <td class="wap-t-strong">{{ $row['name'] }}</td>
                                    <td class="wap-t-num">{{ number_format($row['open']) }}</td>
                                    <td class="wap-t-num {{ $row['snags'] ? 'wap-t-bad' : '' }}">{{ number_format($row['snags']) }}</td>
                                    <td class="wap-t-num {{ $row['overdue'] ? 'wap-t-bad' : '' }}">{{ number_format($row['overdue']) }}</td>
                                    <td class="wap-t-num wap-t-good">{{ number_format($row['handover']) }}</td>
                                    <td class="wap-t-num">{{ number_format($row['sites']) }}</td>
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
