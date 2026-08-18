{{--
    Wood Art · Spaces & Phases — extends the suite shell.

    Four screens: Spaces is every room and how far its phases have got; Phase
    Board is those phases by state; Material Demand is what the planned work
    needs; Team Load is who is carrying it.

    ORPHANS ARE SHOWN, NEVER HIDDEN — a phase whose space was removed, or whose
    owner code does not resolve, appears flagged. A record you cannot see is a
    record you cannot fix.

    NOTHING HERE POSTS MONEY. A requirement is a plan, not a transaction.

    NO <script> ANYWHERE IN THIS FILE — dragging is served by woodart-board.js,
    loaded once by the suite shell, outside the swapped region.
--}}
@extends('woodart::layouts.suite')

@php
    use Modules\WoodArt\Modules\Projects\Models\Project;

    $waRole = request()->route('role');
    $waHere = route('role.woodart.scope', ['role' => $waRole, 'section' => $section]);

    $waPhaseClass = fn (string $s) => match ($s) {
        'Complete' => 'wap-badge-good',
        'Active'   => 'wap-badge-warn',
        default    => '',
    };
    $waColor = \Modules\WoodArt\Modules\Scope\Models\Phase::STATUS_COLORS;
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.projects', ['role' => $waRole, 'section' => 'active']) }}">
        <i class="bi bi-easel2-fill"></i> Projects</a>
    @if($section === 'phases')
    <a class="wap-btn wap-btn-primary" href="{{ route('role.woodart.scope.phases.create', ['role' => $waRole]) }}">
        <i class="bi bi-plus-lg"></i> New Phase</a>
    @else
    <a class="wap-btn wap-btn-primary" href="{{ route('role.woodart.scope.spaces.create', ['role' => $waRole]) }}">
        <i class="bi bi-plus-lg"></i> New Space</a>
    @endif
@endsection

@section('wa-view')

    @if($section === 'spaces')
        {{-- SCREEN 1 · SPACES — every room, and how far its phases have got. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Spaces</span><span class="wap-kpi-ico"><i class="bi bi-door-open"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['spaces']) }}</div>
                @if($stats['projects'])<div class="wap-kpi-foot">across {{ number_format($stats['projects']) }} {{ \Illuminate\Support\Str::plural('project', $stats['projects']) }}</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Total Area</span><span class="wap-kpi-ico"><i class="bi bi-rulers"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['area']) }}</div>
                <div class="wap-kpi-foot">square feet</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Phases</span><span class="wap-kpi-ico"><i class="bi bi-diagram-3"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['phases']) }}</div>
                @if($stats['phases'])<div class="wap-kpi-foot">{{ number_format($stats['open']) }} still open</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Complete</span><span class="wap-kpi-ico"><i class="bi bi-check2-circle"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['pct'] }}%</div>
                @if($stats['phases'])<div class="wap-kpi-foot">{{ number_format($stats['done']) }} of {{ number_format($stats['phases']) }} phases</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Overdue</span><span class="wap-kpi-ico"><i class="bi bi-alarm-fill"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['overdue']) }}</div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-door-open"></i> Spaces</h3>
                <span class="wap-card-sub">Every room of every project, in running order</span>
            </div>
            <div class="wap-card-body">

                @include('woodart::partials.wa-search', [
                    'action'      => $waHere,
                    'q'           => $q,
                    'placeholder' => 'Search room, type or project…',
                ])

                @if(empty($spaces))
                <div class="wap-empty">
                    <i class="bi bi-{{ $q !== '' ? 'search' : 'door-open' }}"></i>
                    <div class="wap-empty-title">
                        {{ $q !== '' ? 'Nothing matches “' . $q . '”' : 'No spaces yet' }}
                    </div>
                    <div class="wap-empty-sub">
                        {{ $q !== ''
                            ? 'Searched room name, code, type and project.'
                            : 'Divide a project into rooms and each one appears here.' }}
                    </div>
                </div>
                @else
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Code</th><th>Room</th><th>Type</th><th>Project</th>
                                <th class="wap-t-num">Area</th><th class="wap-t-num">Phases</th>
                                <th style="min-width:150px">Progress</th><th class="wap-t-num">Overdue</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($spaces as $row)
                            @php $s = $row['space']; @endphp
                            <tr>
                                <td class="wap-t-strong">{{ $s->ext_id }}</td>
                                <td class="wap-t-strong">{{ $s->name }}</td>
                                <td><span class="wap-badge">{{ $s->kind }}</span></td>
                                <td>
                                    {{ $s->project }}
                                    @if(\array_key_exists($s->project, $projectNames))
                                    <span class="wap-caption" style="display:block">{{ $projectNames[$s->project] }}</span>
                                    @else
                                    <span class="wap-badge wap-badge-bad" style="margin-left:6px">orphan</span>
                                    @endif
                                </td>
                                <td class="wap-t-num">{{ $s->area ? number_format($s->area) . ' sft' : '—' }}</td>
                                <td class="wap-t-num">{{ number_format($row['phases']) }}</td>
                                <td>
                                    <div class="wap-proj-prog" style="margin:0">
                                        <div class="wap-progress"><div class="wap-progress-bar" style="width:{{ $row['pct'] }}%"></div></div>
                                        <span class="wap-proj-pct">{{ $row['pct'] }}%</span>
                                    </div>
                                </td>
                                <td class="wap-t-num {{ $row['overdue'] ? 'wap-t-bad' : '' }}">{{ $row['overdue'] ?: '—' }}</td>
                                <td>
                                    <span class="wap-t-acts">
                                        <a class="wap-proj-act" title="Edit {{ $s->ext_id }}" aria-label="Edit {{ $s->name }}"
                                           href="{{ route('role.woodart.scope.spaces.edit', ['role' => $waRole, 'space' => $s]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <a class="wap-proj-act wap-proj-act-bad" title="Remove {{ $s->ext_id }}" aria-label="Remove {{ $s->name }}"
                                           href="{{ route('role.woodart.scope.spaces.delete', ['role' => $waRole, 'space' => $s]) }}">
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

    @elseif($section === 'phases')
        {{-- SCREEN 2 · PHASE BOARD — every phase by state, draggable. --}}
        <div class="wap-banner">
            <i class="bi bi-info-circle"></i>
            <div>Every phase of every space, grouped by state. Drag a card to another column to move it
                &mdash; the arrows do the same thing without a mouse. Moving a phase to Complete does
                <strong>not</strong> change the project's progress; that stays the project's own number.</div>
        </div>

        <div class="wap-kanban" data-wa-board data-wa-field="status"
             data-wa-stage-url="{{ route('role.woodart.scope.phases.status', ['role' => $waRole, 'phase' => '__ID__']) }}">
            @foreach($board as $status => $inStatus)
            @php
                $i     = array_search($status, $statuses, true);
                // array_search returns FALSE for a retired status, and in PHP
                // `false < 2` is TRUE — so gate the arrows on this flag.
                $known = $i !== false;
            @endphp
            <div class="wap-kb-col" style="--wap-kb: {{ $waColor[$status] ?? '#c22945' }}">
                <div class="wap-kb-col-head">
                    <span class="wap-kb-col-dot"></span>
                    <span class="wap-kb-col-title">{{ $status }}</span>
                    @unless($known)
                    <span class="wap-badge wap-badge-bad" title="This status is no longer part of the workflow">retired</span>
                    @endunless
                    <span class="wap-kb-count" data-wa-count>{{ $inStatus->count() }}</span>
                </div>
                <div class="wap-kb-list" data-wa-drop="{{ $status }}">
                    @forelse($inStatus as $p)
                    @php $orphanSpace = $p->space && ! \array_key_exists($p->space, $spaceNames); @endphp
                    <div class="wap-kb-card" data-wa-card="{{ $p->ext_id }}" @if($known) draggable="true" @endif>
                        <div class="wap-kb-card-title">{{ $p->name }}</div>
                        <div class="wap-kb-card-sub">
                            @if(! $p->space)
                                {{ $p->ext_id }} &middot; no space
                            @elseif($orphanSpace)
                                {{ $p->ext_id }} &middot; {{ $p->space }} (removed)
                            @else
                                {{ $p->ext_id }} &middot; {{ $spaceNames[$p->space] }}
                            @endif
                            @if($p->code) &middot; {{ $p->code }} @endif
                        </div>

                        <div class="wap-kb-card-foot">
                            <span class="{{ $p->is_overdue ? 'wap-t-bad' : '' }}">
                                @if($p->owner_id)
                                    {{ $people[$p->owner_id] ?? $p->owner_id }}
                                @else
                                    Unassigned
                                @endif
                                @if($p->is_overdue) &middot; late @endif
                            </span>
                            <span class="wap-kb-move">
                                @unless($known)
                                <form method="POST" action="{{ route('role.woodart.scope.phases.status', ['role' => $waRole, 'phase' => $p]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $statuses[0] }}">
                                    <button type="submit" title="Put {{ $p->ext_id }} back on the workflow at {{ $statuses[0] }}"
                                            aria-label="Move {{ $p->ext_id }} to {{ $statuses[0] }}">
                                        <i class="bi bi-arrow-return-left"></i></button>
                                </form>
                                @endunless
                                @if($known && $i > 0)
                                <form method="POST" action="{{ route('role.woodart.scope.phases.status', ['role' => $waRole, 'phase' => $p]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $statuses[$i - 1] }}">
                                    <button type="submit" title="Move back to {{ $statuses[$i - 1] }}"
                                            aria-label="Move {{ $p->ext_id }} back to {{ $statuses[$i - 1] }}">
                                        <i class="bi bi-chevron-left"></i></button>
                                </form>
                                @endif
                                @if($known && $i < count($statuses) - 1)
                                <form method="POST" action="{{ route('role.woodart.scope.phases.status', ['role' => $waRole, 'phase' => $p]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $statuses[$i + 1] }}">
                                    <button type="submit" title="Advance to {{ $statuses[$i + 1] }}"
                                            aria-label="Advance {{ $p->ext_id }} to {{ $statuses[$i + 1] }}">
                                        <i class="bi bi-chevron-right"></i></button>
                                </form>
                                @endif
                            </span>
                        </div>
                    </div>
                    @empty
                    <div class="wap-empty">
                        <i class="bi bi-inbox"></i>
                        <div class="wap-empty-sub">Nothing {{ strtolower($status) }}</div>
                    </div>
                    @endforelse
                </div>
            </div>
            @endforeach
        </div>

    @elseif($section === 'materials')
        {{-- SCREEN 3 · MATERIAL DEMAND — what the planned work needs. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Requirements</span><span class="wap-kpi-ico"><i class="bi bi-list-check"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['needs']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Planned Cost</span><span class="wap-kpi-ico"><i class="bi bi-wallet2"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['cost']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Planned Sale</span><span class="wap-kpi-ico"><i class="bi bi-cash-stack"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['sale']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Margin</span><span class="wap-kpi-ico"><i class="bi bi-graph-up"></i></span></div>
                <div class="wap-kpi-value {{ $stats['sale'] - $stats['cost'] < 0 ? 'wap-t-bad' : '' }}">
                    {{ Project::money($stats['sale'] - $stats['cost']) }}</div>
                @if($stats['sale'])<div class="wap-kpi-foot">{{ (int) round(($stats['sale'] - $stats['cost']) / $stats['sale'] * 100) }}% of sale</div>@endif
            </div>
        </div>

        @if(empty($demand))
        <div class="wap-empty">
            <i class="bi bi-list-check"></i>
            <div class="wap-empty-title">Nothing planned yet</div>
            <div class="wap-empty-sub">Requirements are what each phase needs &mdash; they appear here once recorded.</div>
        </div>
        @else
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-basket"></i> What the Plan Needs</h3>
                <span class="wap-card-sub">Dearest first &mdash; materials and contracted work</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Item</th><th>Kind</th>
                                <th class="wap-t-num">Quantity</th><th class="wap-t-num">Cost</th>
                                <th class="wap-t-num">Sale</th><th class="wap-t-num">Margin</th>
                                <th class="wap-t-num">Phases</th><th>State</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($demand as $d)
                            <tr>
                                <td class="wap-t-strong">{{ $d['item'] }}</td>
                                <td><span class="wap-badge">{{ $d['kind'] }}</span></td>
                                <td class="wap-t-num">{{ rtrim(rtrim(number_format($d['qty'], 2), '0'), '.') }} {{ $d['unit'] }}</td>
                                <td class="wap-t-num">{{ Project::money($d['cost']) }}</td>
                                <td class="wap-t-num">{{ Project::money($d['sale']) }}</td>
                                <td class="wap-t-num {{ $d['margin'] < 0 ? 'wap-t-bad' : 'wap-t-good' }}">{{ Project::money($d['margin']) }}</td>
                                <td class="wap-t-num">{{ number_format($d['phases']) }}</td>
                                <td>
                                    @foreach($d['statuses'] as $st)
                                    <span class="wap-badge {{ $st === 'Issued' ? 'wap-badge-good' : ($st === 'Ordered' ? 'wap-badge-warn' : '') }}"
                                          style="margin-right:4px">{{ $st }}</span>
                                    @endforeach
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="wap-banner" style="margin-top:16px">
                    <i class="bi bi-info-circle"></i>
                    <div>These are <strong>planned</strong> figures, not spend. Nothing here means money moved,
                        stock left the store or a purchase order exists &mdash; ordering is Procurement's job
                        and this screen never posts to it.</div>
                </div>
            </div>
        </div>
        @endif

    @else
        {{-- SCREEN 4 · TEAM LOAD — who is carrying which phases. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">People</span><span class="wap-kpi-ico"><i class="bi bi-people-fill"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['owners']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Open Phases</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['open']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Active Now</span><span class="wap-kpi-ico"><i class="bi bi-gear-wide-connected"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['active']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Overdue</span><span class="wap-kpi-ico"><i class="bi bi-alarm-fill"></i></span></div>
                <div class="wap-kpi-value {{ $stats['overdue'] ? 'wap-t-bad' : '' }}">{{ number_format($stats['overdue']) }}</div>
            </div>
        </div>

        @if(empty($load))
        <div class="wap-empty">
            <i class="bi bi-people"></i>
            <div class="wap-empty-title">No phases yet</div>
            <div class="wap-empty-sub">Add phases to a space and the workload appears here.</div>
        </div>
        @else
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-bar-chart-line"></i> Open Phases by Person</h3>
                <span class="wap-card-sub">Busiest first &mdash; the bar is relative to the busiest person</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-dist">
                    @foreach($load as $row)
                    <div class="wap-dist-row">
                        <div class="wap-dist-main">
                            <span class="wap-dist-dot"></span>
                            <span class="wap-dist-label">
                                {{ $row['name'] }}
                                @unless($row['known'])
                                <span class="wap-badge wap-badge-bad" style="margin-left:6px"
                                      title="This employee code does not match anyone in the register">unknown code</span>
                                @endunless
                            </span>
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
                <h3><i class="bi bi-people"></i> Detail</h3>
                <span class="wap-card-sub">What each person is running and what has slipped</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Person</th><th class="wap-t-num">Open</th><th class="wap-t-num">Active</th>
                                <th class="wap-t-num">Overdue</th><th class="wap-t-num">Complete</th><th class="wap-t-num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($load as $row)
                            <tr>
                                <td class="wap-t-strong">{{ $row['name'] }}
                                    @if($row['code'] && $row['name'] !== $row['code'])
                                    <span class="wap-caption" style="display:block">{{ $row['code'] }}</span>
                                    @endif
                                </td>
                                <td class="wap-t-num">{{ number_format($row['open']) }}</td>
                                <td class="wap-t-num">{{ number_format($row['active']) }}</td>
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
