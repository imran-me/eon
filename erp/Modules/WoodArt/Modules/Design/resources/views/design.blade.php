{{--
    Wood Art · Design & 3D — extends the suite shell.

    Three screens: Drawing Register is every deliverable and where it stands;
    Approvals is what is sitting with the client; Design Load is who is
    carrying the work.

    THE PHASE GATE lives on the register screen: a project's design is complete
    only when it HAS deliverables and every one is Approved. A project with none
    reads "not started", never "complete" — see DesignController::projectStatus.

    NO <script> ANYWHERE IN THIS FILE.
--}}
@extends('woodart::layouts.suite')

@php
    $waRole = request()->route('role');
    $waHere = route('role.woodart.design', ['role' => $waRole, 'section' => $section]);

    $waStatusClass = fn (string $s) => match ($s) {
        'Approved'  => 'wap-badge-good',
        'Commented' => 'wap-badge-bad',
        'Issued'    => 'wap-badge-warn',
        default     => '',
    };
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.projects', ['role' => $waRole, 'section' => 'active']) }}">
        <i class="bi bi-easel2-fill"></i> Projects</a>
    <a class="wap-btn wap-btn-primary" href="{{ route('role.woodart.design.create', ['role' => $waRole]) }}">
        <i class="bi bi-plus-lg"></i> New Drawing</a>
@endsection

@section('wa-view')

    @if($section === 'register')
        {{-- SCREEN 1 · DRAWING REGISTER --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Deliverables</span><span class="wap-kpi-ico"><i class="bi bi-vector-pen"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['drawings']) }}</div>
                @if($stats['drawings'])<div class="wap-kpi-foot">{{ number_format($stats['open']) }} still open</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">With Client</span><span class="wap-kpi-ico"><i class="bi bi-send"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['issued']) }}</div>
                @if($stats['oldest'] !== null)<div class="wap-kpi-foot">longest wait {{ $stats['oldest'] }}d</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Commented</span><span class="wap-kpi-ico"><i class="bi bi-chat-left-text"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['commented']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Approved</span><span class="wap-kpi-ico"><i class="bi bi-check2-circle"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['pct'] }}%</div>
                @if($stats['drawings'])<div class="wap-kpi-foot">{{ number_format($stats['approved']) }} of {{ number_format($stats['drawings']) }}</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Revisions</span><span class="wap-kpi-ico"><i class="bi bi-arrow-repeat"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['revisions']) }}</div>
                <div class="wap-kpi-foot">Re-issues beyond the first draft</div>
            </div>
        </div>

        @if($stats['attention'] > 0)
        <div class="wap-banner" style="background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
            <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
            <div><strong>{{ number_format($stats['attention']) }}</strong>
                {{ \Illuminate\Support\Str::plural('deliverable', $stats['attention']) }} need someone to act &mdash;
                {{ number_format($stats['issued']) }} with the client, {{ number_format($stats['commented']) }} come back with comments.</div>
        </div>
        @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-files"></i> Drawing Register</h3>
                <span class="wap-card-sub">Most recently issued first &mdash; revision, status and who drew it</span>
            </div>
            <div class="wap-card-body">

                @include('woodart::partials.wa-search', [
                    'action'      => $waHere,
                    'q'           => $q,
                    'placeholder' => 'Search title, code, type, designer or project…',
                ])

                @if($drawings->isEmpty())
                <div class="wap-empty">
                    <i class="bi bi-{{ $q !== '' ? 'search' : 'vector-pen' }}"></i>
                    <div class="wap-empty-title">
                        {{ $q !== '' ? 'Nothing matches “' . $q . '”' : 'No drawings yet' }}
                    </div>
                    <div class="wap-empty-sub">
                        {{ $q !== ''
                            ? 'Searched title, code, type, designer, status and project.'
                            : 'Register a drawing or 3D deliverable and it appears here.' }}
                    </div>
                </div>
                @else
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Code</th><th>Title</th><th>Type</th><th>Project</th>
                                <th>Designer</th><th class="wap-t-num">Rev</th>
                                <th>Issued</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($drawings as $d)
                            @php $orphan = $d->project && ! \array_key_exists($d->project, $projectNames); @endphp
                            <tr>
                                <td class="wap-t-strong">{{ $d->ext_id }}</td>
                                <td class="wap-t-strong">{{ $d->title }}</td>
                                <td><span class="wap-badge">{{ $d->kind }}</span></td>
                                <td>
                                    @if(! $d->project)
                                        <span class="wap-caption">Not linked</span>
                                    @elseif($orphan)
                                        {{ $d->project }}
                                        <span class="wap-badge wap-badge-bad" style="margin-left:6px"
                                              title="This project no longer exists — the drawing is kept so the problem is visible">orphan</span>
                                    @else
                                        {{ $d->project }}
                                        <span class="wap-caption" style="display:block">{{ $projectNames[$d->project] }}</span>
                                    @endif
                                </td>
                                <td>{{ $d->designer ?: '—' }}</td>
                                <td class="wap-t-num">
                                    {{ $d->rev }}
                                    @if($d->rev_count > 0)
                                    <span class="wap-caption" style="display:block">{{ $d->rev_count }} rev</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $d->issued?->format('d M Y') ?: '—' }}
                                    @if($d->waiting_days !== null)
                                    <span class="wap-caption" style="display:block">{{ $d->waiting_days }}d with client</span>
                                    @endif
                                </td>
                                <td><span class="wap-badge {{ $waStatusClass($d->status) }}">{{ $d->status }}</span></td>
                                <td>
                                    <span class="wap-t-acts">
                                        <a class="wap-proj-act" title="Log a revision on {{ $d->ext_id }}" aria-label="Log a revision on {{ $d->title }}"
                                           href="{{ route('role.woodart.design.revision.create', ['role' => $waRole, 'drawing' => $d]) }}">
                                            <i class="bi bi-arrow-repeat"></i></a>
                                        <a class="wap-proj-act" title="Edit {{ $d->ext_id }}" aria-label="Edit {{ $d->title }}"
                                           href="{{ route('role.woodart.design.edit', ['role' => $waRole, 'drawing' => $d]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <a class="wap-proj-act wap-proj-act-bad" title="Remove {{ $d->ext_id }}" aria-label="Remove {{ $d->title }}"
                                           href="{{ route('role.woodart.design.delete', ['role' => $waRole, 'drawing' => $d]) }}">
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

        @if(! empty($projectRows))
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-flag"></i> Design Phase by Project</h3>
                <span class="wap-card-sub">Complete only when a project HAS deliverables and every one is approved</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Project</th><th class="wap-t-num">Deliverables</th>
                                <th class="wap-t-num">Open</th><th class="wap-t-num">With Client</th>
                                <th style="min-width:150px">Approved</th><th>Phase</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($projectRows as $p)
                            <tr>
                                <td class="wap-t-strong">
                                    {{ $p['name'] }}
                                    @if($p['project'])
                                    <span class="wap-caption" style="display:block">{{ $p['project'] }}</span>
                                    @endif
                                    @unless($p['known'])
                                    <span class="wap-badge wap-badge-bad" style="margin-left:6px">orphan</span>
                                    @endunless
                                </td>
                                <td class="wap-t-num">{{ number_format($p['total']) }}</td>
                                <td class="wap-t-num">{{ number_format($p['open']) }}</td>
                                <td class="wap-t-num">{{ number_format($p['waiting']) }}</td>
                                <td>
                                    <div class="wap-proj-prog" style="margin:0">
                                        <div class="wap-progress"><div class="wap-progress-bar" style="width:{{ $p['pct'] }}%"></div></div>
                                        <span class="wap-proj-pct">{{ $p['pct'] }}%</span>
                                    </div>
                                </td>
                                <td>
                                    @if($p['complete'])
                                    <span class="wap-badge wap-badge-good">complete</span>
                                    @else
                                    <span class="wap-badge">in progress</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="wap-banner" style="margin-top:16px">
                    <i class="bi bi-info-circle"></i>
                    <div>A project with <strong>no deliverables at all</strong> does not appear here, and is
                        <strong>not</strong> complete &mdash; it has not started design. Only projects that
                        have drawings can reach "complete".</div>
                </div>
            </div>
        </div>
        @endif

    @elseif($section === 'approvals')
        {{-- SCREEN 2 · APPROVALS — what is sitting with the client. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">With Client</span><span class="wap-kpi-ico"><i class="bi bi-send"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['waiting']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Longest Wait</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="wap-kpi-value {{ $stats['oldest'] === null ? 'wap-kpi-unknown' : '' }}">
                    {{ $stats['oldest'] !== null ? $stats['oldest'] . 'd' : '—' }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Commented</span><span class="wap-kpi-ico"><i class="bi bi-chat-left-text"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['commented']) }}</div>
                <div class="wap-kpi-foot">Back with us to act on</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Approved</span><span class="wap-kpi-ico"><i class="bi bi-check2-circle"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['approved']) }}</div>
            </div>
        </div>

        @if($queue->isEmpty())
        <div class="wap-empty">
            <i class="bi bi-check2-circle"></i>
            <div class="wap-empty-title">Nothing waiting on the client</div>
            <div class="wap-empty-sub">
                {{ $stats['drawings']
                    ? 'Every deliverable is either still with us or already approved.'
                    : 'Register a drawing and anything issued for approval appears here.' }}
            </div>
        </div>
        @else
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-hourglass-split"></i> Waiting on the Client</h3>
                <span class="wap-card-sub">Longest wait first &mdash; issued and not yet answered</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Drawing</th><th>Project</th><th>Designer</th>
                                <th class="wap-t-num">Rev</th><th>Issued</th>
                                <th class="wap-t-num">Waiting</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($queue as $d)
                            <tr>
                                <td class="wap-t-strong">{{ $d->title }}
                                    <span class="wap-caption" style="display:block">{{ $d->ext_id }} · {{ $d->kind }}</span></td>
                                <td>
                                    @if(! $d->project)
                                        <span class="wap-caption">Not linked</span>
                                    @elseif(! \array_key_exists($d->project, $projectNames))
                                        {{ $d->project }} <span class="wap-badge wap-badge-bad">orphan</span>
                                    @else
                                        {{ $projectNames[$d->project] }}
                                    @endif
                                </td>
                                <td>{{ $d->designer ?: '—' }}</td>
                                <td class="wap-t-num">{{ $d->rev }}</td>
                                <td>{{ $d->issued?->format('d M Y') ?: '—' }}</td>
                                <td class="wap-t-num {{ ($d->waiting_days ?? 0) >= 14 ? 'wap-t-bad' : '' }}">
                                    {{ $d->waiting_days !== null ? $d->waiting_days . 'd' : '—' }}</td>
                                <td>
                                    <span class="wap-t-acts">
                                        <a class="wap-proj-act" title="Log a revision on {{ $d->ext_id }}" aria-label="Log a revision on {{ $d->title }}"
                                           href="{{ route('role.woodart.design.revision.create', ['role' => $waRole, 'drawing' => $d]) }}">
                                            <i class="bi bi-arrow-repeat"></i></a>
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

    @else
        {{-- SCREEN 3 · DESIGN LOAD --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Designers</span><span class="wap-kpi-ico"><i class="bi bi-people-fill"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['designers']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Open Work</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['open']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Drafts</span><span class="wap-kpi-ico"><i class="bi bi-pencil-square"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['draft']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Revisions</span><span class="wap-kpi-ico"><i class="bi bi-arrow-repeat"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['revisions']) }}</div>
            </div>
        </div>

        @if(empty($load))
        <div class="wap-empty">
            <i class="bi bi-people"></i>
            <div class="wap-empty-title">No design work yet</div>
            <div class="wap-empty-sub">Register a drawing and the workload appears here.</div>
        </div>
        @else
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-bar-chart-line"></i> Open Work by Designer</h3>
                <span class="wap-card-sub">Busiest first &mdash; the bar is relative to the busiest designer</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-dist">
                    @foreach($load as $row)
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
                            <span class="wap-dist-share">{{ number_format($row['total']) }} total</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-people"></i> Designer Detail</h3>
                <span class="wap-card-sub">How much has come back for revision</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Designer</th><th class="wap-t-num">Open</th>
                                <th class="wap-t-num">With Client</th><th class="wap-t-num">Revisions</th>
                                <th class="wap-t-num">Approved</th><th class="wap-t-num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($load as $row)
                            <tr>
                                <td class="wap-t-strong">{{ $row['name'] }}</td>
                                <td class="wap-t-num">{{ number_format($row['open']) }}</td>
                                <td class="wap-t-num">{{ number_format($row['waiting']) }}</td>
                                <td class="wap-t-num">{{ number_format($row['revisions']) }}</td>
                                <td class="wap-t-num wap-t-good">{{ number_format($row['approved']) }}</td>
                                <td class="wap-t-num">{{ number_format($row['total']) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if(! empty($kinds))
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-pie-chart"></i> Deliverable Mix</h3>
                <span class="wap-card-sub">What kind of work the design phase produces</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-dist">
                    @foreach($kinds as $k)
                    <div class="wap-dist-row">
                        <div class="wap-dist-main">
                            <span class="wap-dist-dot"></span>
                            <span class="wap-dist-label">{{ $k['name'] }}</span>
                            <span class="wap-dist-value">{{ number_format($k['count']) }}</span>
                        </div>
                        <div class="wap-dist-track">
                            <div class="wap-dist-bar" style="width: {{ $k['share'] }}%"></div>
                        </div>
                        <div class="wap-dist-figs">
                            <span class="wap-dist-share">{{ $k['share'] }}%</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
        @endif
    @endif

@endsection
