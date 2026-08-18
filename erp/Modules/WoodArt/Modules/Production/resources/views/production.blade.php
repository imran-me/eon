{{--
    Wood Art · Workshop (production) — extends the suite shell.

    Transcribed from the reference module's authored markup
    (companies/woodart/modules/production/frontend/template.html, with the
    empty-state copy from view.js:356): three screens. Job Register is every
    fabrication job on the floor; Workshop Board is the four workshop states
    side by side; Station Load is where the workshop is busy.

    THE BOARD'S FOUR COLUMNS ARE FIXED MARKUP, not data — they are the
    workshop's four states, which is why the reference writes them out
    (decision W5). Only the cards inside are per-record, so an empty board is
    still a complete, truthful screen.

    ORPHANS: a job pointing at a project that no longer exists is shown and
    flagged, never hidden (decision W3). Losing real shop-floor history because
    a parent record vanished is worse than showing the problem.

    NO <script> ANYWHERE IN THIS FILE. woodart-nav.js replaces [data-wa-view]
    wholesale on navigation, so a script here would never re-run. Dragging is
    served by woodart-board.js, loaded once by the suite shell.
--}}
@extends('woodart::layouts.suite')

@php
    $waRole = request()->route('role');
    $waJobs = route('role.woodart.production', ['role' => $waRole, 'section' => 'jobs']);
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.projects', ['role' => $waRole, 'section' => 'active']) }}">
        <i class="bi bi-easel2-fill"></i> Projects</a>
    <a class="wap-btn wap-btn-primary" href="{{ route('role.woodart.production.create', ['role' => $waRole]) }}">
        <i class="bi bi-plus-lg"></i> New Job</a>
@endsection

@section('wa-view')

    @if($section === 'jobs')
        {{-- SCREEN 1 · JOB REGISTER — every fabrication job, searchable. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Jobs</span><span class="wap-kpi-ico"><i class="bi bi-hammer"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['jobs']) }}</div>
                @if($stats['jobs'])<div class="wap-kpi-foot">{{ number_format($stats['open']) }} still open</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Running</span><span class="wap-kpi-ico"><i class="bi bi-gear-wide-connected"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['running']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Blocked</span><span class="wap-kpi-ico"><i class="bi bi-exclamation-octagon"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['blocked']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Overdue</span><span class="wap-kpi-ico"><i class="bi bi-alarm-fill"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['overdue']) }}</div>
                @if($stats['overdue'])<div class="wap-kpi-foot">Open work past its due date</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Completion</span><span class="wap-kpi-ico"><i class="bi bi-check2-circle"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['pct'] }}%</div>
                @if($stats['jobs'])<div class="wap-kpi-foot">{{ number_format($stats['done']) }} of {{ number_format($stats['jobs']) }} finished</div>@endif
            </div>
        </div>

        {{-- The reference prints this only when something actually needs
             attention (view.js:319) — blocked work plus open work past due. --}}
        @if($stats['attention'] > 0)
        <div class="wap-banner" style="background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
            <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
            <div><strong>{{ number_format($stats['attention']) }}</strong>
                {{ \Illuminate\Support\Str::plural('job', $stats['attention']) }} need attention &mdash;
                {{ number_format($stats['blocked']) }} blocked, {{ number_format($stats['overdue']) }} overdue.</div>
        </div>
        @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-list-check"></i> Fabrication Jobs</h3>
                <span class="wap-card-sub">Every job on the shop floor &mdash; station, owner, due date and status</span>
            </div>
            <div class="wap-card-body">

                @include('woodart::partials.wa-search', [
                    'action'      => $waJobs,
                    'q'           => $q,
                    'placeholder' => 'Search job, project, station or crew…',
                ])

                @if($jobs->isEmpty())
                <div class="wap-empty">
                    <i class="bi bi-{{ $q !== '' ? 'search' : 'hammer' }}"></i>
                    <div class="wap-empty-title">
                        {{ $q !== '' ? 'Nothing matches “' . $q . '”' : 'No jobs yet' }}
                    </div>
                    <div class="wap-empty-sub">
                        {{ $q !== ''
                            ? 'Searched job, code, project, station, crew and status.'
                            : 'Break a project into workshop jobs.' }}
                    </div>
                </div>
                @else
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Code</th><th>Job</th><th>Project</th><th>Station</th>
                                <th>Assigned</th><th>Due</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jobs as $j)
                            @php
                                // An orphan is a job whose project id no longer
                                // resolves. Blank is NOT an orphan — a job with
                                // no project is general workshop work.
                                $orphan = $j->project && ! \array_key_exists($j->project, $projectNames);
                                $days   = $j->days_left;
                            @endphp
                            <tr>
                                <td class="wap-t-strong">{{ $j->ext_id }}</td>
                                <td class="wap-t-strong">{{ $j->job }}</td>
                                <td>
                                    @if(! $j->project)
                                        <span class="wap-caption">General</span>
                                    @elseif($orphan)
                                        {{ $j->project }}
                                        <span class="wap-badge wap-badge-bad" style="margin-left:6px"
                                              title="This project no longer exists — the job is kept so the problem is visible">orphan</span>
                                    @else
                                        {{ $j->project }}
                                        <span class="wap-caption" style="display:block">{{ $projectNames[$j->project] }}</span>
                                    @endif
                                </td>
                                <td><span class="wap-badge">{{ $j->station }}</span></td>
                                <td>{{ $j->assigned_to ?: '—' }}</td>
                                <td class="{{ $j->is_overdue ? 'wap-t-bad' : '' }}">
                                    @if($j->due)
                                        {{ $j->due->format('d M Y') }}
                                        @if($j->is_overdue)
                                            <span class="wap-caption" style="display:block">{{ abs($days) }}
                                                {{ \Illuminate\Support\Str::plural('day', abs($days)) }} late</span>
                                        @elseif($j->is_open && $days !== null && $days <= 7)
                                            <span class="wap-caption" style="display:block">{{ $days === 0 ? 'due today' : 'in ' . $days . ' ' . \Illuminate\Support\Str::plural('day', $days) }}</span>
                                        @endif
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td>
                                    <span class="wap-badge {{ $j->status === 'Done' ? 'wap-badge-good' : ($j->status === 'Blocked' ? 'wap-badge-bad' : ($j->status === 'Running' ? 'wap-badge-warn' : '')) }}">
                                        {{ $j->status }}</span>
                                </td>
                                <td>
                                    <span class="wap-t-acts">
                                        <a class="wap-proj-act" title="Edit {{ $j->ext_id }}" aria-label="Edit {{ $j->job }}"
                                           href="{{ route('role.woodart.production.edit', ['role' => $waRole, 'job' => $j]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <a class="wap-proj-act wap-proj-act-bad" title="Remove {{ $j->ext_id }}" aria-label="Remove {{ $j->job }}"
                                           href="{{ route('role.woodart.production.delete', ['role' => $waRole, 'job' => $j]) }}">
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

    @elseif($section === 'board')
        {{-- SCREEN 2 · WORKSHOP BOARD — the four workshop states, side by side.
             The reference prints this banner unconditionally
             (template.html:110), unlike every other banner in the suite. --}}
        <div class="wap-banner">
            <i class="bi bi-info-circle"></i>
            <div>Every job on the floor, grouped by state. Drag a card to another column to move it &mdash;
                the arrows do the same thing without a mouse.</div>
        </div>

        @php
            // The authored column meta, keyed so an unknown status can fall
            // back without a second loop.
            $waMeta = [];
            foreach ($columns as [$cName, $cColor, $cIcon, $cEmpty]) {
                $waMeta[$cName] = ['color' => $cColor, 'icon' => $cIcon, 'empty' => $cEmpty];
            }
        @endphp

        {{-- data-wa-board is the ONLY thing woodart-board.js will act on; that
             script lives outside [data-wa-view] so it survives no-reload
             navigation. data-wa-field tells it to post `status` rather than
             Projects' `stage`. --}}
        <div class="wap-kanban" data-wa-board data-wa-field="status"
             data-wa-stage-url="{{ route('role.woodart.production.status', ['role' => $waRole, 'job' => '__ID__']) }}">
            @foreach($board as $status => $inStatus)
            @php
                $i     = array_search($status, $statuses, true);
                // array_search returns FALSE for a status no longer in the
                // vocabulary, and in PHP `false < 3` is TRUE — so the arrows
                // must be gated on this flag, never on $i alone.
                $known = $i !== false;
                $meta  = $waMeta[$status] ?? ['color' => '#c22945', 'icon' => 'inbox', 'empty' => 'Nothing here'];
            @endphp
            <div class="wap-kb-col" style="--wap-kb: {{ $meta['color'] }}">
                <div class="wap-kb-col-head">
                    <span class="wap-kb-col-dot"></span>
                    <span class="wap-kb-col-title">{{ $status }}</span>
                    @unless($known)
                    <span class="wap-badge wap-badge-bad" title="This status is no longer part of the workflow">retired</span>
                    @endunless
                    <span class="wap-kb-count" data-wa-count>{{ $inStatus->count() }}</span>
                </div>
                <div class="wap-kb-list" data-wa-drop="{{ $status }}">
                    @forelse($inStatus as $j)
                    {{-- Only cards on a live status are draggable; one on a
                         retired status must be moved with its recovery button
                         so the choice is deliberate. --}}
                    <div class="wap-kb-card" data-wa-card="{{ $j->ext_id }}" @if($known) draggable="true" @endif>
                        <div class="wap-kb-card-title">{{ $j->job }}</div>
                        <div class="wap-kb-card-sub">
                            {{ $j->ext_id }} &middot; {{ $j->station }}
                            @if($j->assigned_to) &middot; {{ $j->assigned_to }} @endif
                        </div>

                        <div class="wap-kb-card-foot">
                            <span class="{{ $j->is_overdue ? 'wap-t-bad' : '' }}">
                                @if($j->due)
                                    {{ $j->is_overdue ? 'Late — ' : '' }}{{ $j->due->format('d M') }}
                                @else
                                    No date
                                @endif
                            </span>
                            <span class="wap-kb-move">
                                @unless($known)
                                <form method="POST" action="{{ route('role.woodart.production.status', ['role' => $waRole, 'job' => $j]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $statuses[0] }}">
                                    <button type="submit" title="Put {{ $j->ext_id }} back on the workflow at {{ $statuses[0] }}"
                                            aria-label="Move {{ $j->ext_id }} to {{ $statuses[0] }}">
                                        <i class="bi bi-arrow-return-left"></i></button>
                                </form>
                                @endunless
                                @if($known && $i > 0)
                                <form method="POST" action="{{ route('role.woodart.production.status', ['role' => $waRole, 'job' => $j]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $statuses[$i - 1] }}">
                                    <button type="submit" title="Move back to {{ $statuses[$i - 1] }}"
                                            aria-label="Move {{ $j->ext_id }} back to {{ $statuses[$i - 1] }}">
                                        <i class="bi bi-chevron-left"></i></button>
                                </form>
                                @endif
                                @if($known && $i < count($statuses) - 1)
                                <form method="POST" action="{{ route('role.woodart.production.status', ['role' => $waRole, 'job' => $j]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $statuses[$i + 1] }}">
                                    <button type="submit" title="Advance to {{ $statuses[$i + 1] }}"
                                            aria-label="Advance {{ $j->ext_id }} to {{ $statuses[$i + 1] }}">
                                        <i class="bi bi-chevron-right"></i></button>
                                </form>
                                @endif
                            </span>
                        </div>
                    </div>
                    @empty
                    <div class="wap-empty">
                        <i class="bi bi-{{ $meta['icon'] }}"></i>
                        <div class="wap-empty-sub">{{ $meta['empty'] }}</div>
                    </div>
                    @endforelse
                </div>
            </div>
            @endforeach
        </div>

    @else
        {{-- SCREEN 3 · STATION LOAD — where the workshop is busy. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Stations in Use</span><span class="wap-kpi-ico"><i class="bi bi-diagram-3-fill"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['stations']) }}</div>
                <div class="wap-kpi-foot">of {{ count(\Modules\WoodArt\Modules\Production\Models\Job::STATIONS) }} on the floor</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Busiest Station</span><span class="wap-kpi-ico"><i class="bi bi-fire"></i></span></div>
                <div class="wap-kpi-value {{ $stats['top'] ? '' : 'wap-kpi-unknown' }}">{{ $stats['top'] ?? '—' }}</div>
                @unless($stats['top'])<div class="wap-kpi-foot">Nothing open anywhere</div>@endunless
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Open Jobs</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['open']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Crew Assigned</span><span class="wap-kpi-ico"><i class="bi bi-people-fill"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['crew']) }}</div>
            </div>
        </div>

        @if(empty($load))
            {{-- STATE A · no jobs at all. Bare, not in a card — the reference
                 authors it as a plain empty state. --}}
            <div class="wap-empty">
                <i class="bi bi-hammer"></i>
                <div class="wap-empty-title">No jobs on the floor</div>
                <div class="wap-empty-sub">Break a project into workshop jobs and the load analysis fills in.</div>
            </div>
        @else
            {{-- STATE B · the load analysis (view.js:413). Ranked by OPEN jobs,
                 not total: finished work is history and does not compete for a
                 machine (decision W4). --}}
            <div class="wap-card">
                <div class="wap-card-head">
                    <h3><i class="bi bi-bar-chart-line"></i> Open Work by Station</h3>
                    <span class="wap-card-sub">Busiest first &mdash; the bar is relative to the busiest station</span>
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
                    <h3><i class="bi bi-diagram-3"></i> Station Detail</h3>
                    <span class="wap-card-sub">Where work is running, blocked or late</span>
                </div>
                <div class="wap-card-body">
                    <div class="wap-table-wrap">
                        <table class="wap-table">
                            <thead>
                                <tr>
                                    <th>Station</th>
                                    <th class="wap-t-num">Open</th><th class="wap-t-num">Running</th>
                                    <th class="wap-t-num">Blocked</th><th class="wap-t-num">Overdue</th>
                                    <th class="wap-t-num">Done</th><th class="wap-t-num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($load as $row)
                                <tr>
                                    <td class="wap-t-strong">{{ $row['name'] }}</td>
                                    <td class="wap-t-num">{{ number_format($row['open']) }}</td>
                                    <td class="wap-t-num">{{ number_format($row['running']) }}</td>
                                    <td class="wap-t-num {{ $row['blocked'] ? 'wap-t-bad' : '' }}">{{ number_format($row['blocked']) }}</td>
                                    <td class="wap-t-num {{ $row['overdue'] ? 'wap-t-bad' : '' }}">{{ number_format($row['overdue']) }}</td>
                                    <td class="wap-t-num wap-t-good">{{ number_format($row['done']) }}</td>
                                    <td class="wap-t-num">{{ number_format($row['total']) }}</td>
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
