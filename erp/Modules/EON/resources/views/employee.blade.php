@extends('layout.app')

@section('meta-information')
    <title>EON — {{ $employee->name }}</title>
@endsection

{{--
  EON — one person's dossier.

  The order of this page is an argument, not a layout. It goes:
     what EON noticed  →  how it reached the score  →  the evidence  →
     the human record  →  your written judgement.

  The machine's reading and the authority's rating are kept visibly apart the
  whole way down. They are never merged into a single figure, because the
  interesting case is precisely when they disagree — and a page that averages
  them away destroys the only signal that would tell you the engine is wrong.
--}}

@section('css')
    @include('eon::partials.styles')
@endsection

@section('main-content')
@php
    use Modules\EON\Intelligence\People;
    use Modules\EON\Intelligence\Support\Period;
    use Modules\EON\Models\EonReview;

    $hasBoardRoute = Route::has('role.task-oversight.employee');

    $output      = $a->get('output', []);
    $timeliness  = $a->get('timeliness', []);
    $quality     = $a->get('quality', []);
    $engagement  = $a->get('engagement', []);
    $consistency = $a->get('consistency', []);
    $trend       = $a->get('trend', []);
    $dd          = $a->get('drawdown');
    $risk        = $a->get('risk', []);

    $mtdLabel  = $a->mtdWindow['label'] ?? 'This month';
    $prevLabel = $a->prevWindow['label'] ?? 'Last month';
    $mtdDays   = $a->mtdWindow['days'] ?? null;

    $confChip = ['high' => 'high', 'medium' => 'med', 'low' => 'low', 'none' => ''][$a->confidence] ?? '';
@endphp

<div class="eon-wrap">

    @if (session('success'))<div class="eon-flash ok">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="eon-flash err">{{ session('error') }}</div>@endif

    {{-- A rejected review redirects back; without this the form would appear to
         have saved and silently not have. --}}
    @if ($errors->any())
        <div class="eon-flash err">
            The review was not saved:
            {{ implode(' ', $errors->all()) }}
        </div>
    @endif

    <div style="margin-bottom:.7rem">
        <a href="{{ route('role.eon.index', ['role' => $role]) }}" class="eon-btn" style="padding:.35rem .6rem;font-size:.74rem">
            <i class="fas fa-arrow-left" style="font-size:.68rem"></i> All people
        </a>
    </div>

    {{-- ── Who, and where they stand ────────────────────────────────── --}}
    <div class="eon-card">
        <div class="eon-card-b" style="display:flex;gap:1.2rem;align-items:flex-start;flex-wrap:wrap;">
            @include('eon::partials.ring', ['score' => $a->score, 'band' => $a->band])

            <div style="flex:1;min-width:260px">
                <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
                    <div class="eon-avatar lg">
                        @if ($employee->image)
                            <img src="{{ asset($employee->image) }}" alt="">
                        @else
                            {{ People::initials($employee->name) }}
                        @endif
                    </div>
                    <div style="min-width:0">
                        <div style="font-size:1.15rem;font-weight:700;line-height:1.2">{{ $employee->name }}</div>
                        <div style="font-size:.76rem;color:#6b7280;margin-top:.15rem">
                            {{ $employee->employee_id_no ? $employee->employee_id_no . ' · ' : '' }}{{ $employee->email }}
                        </div>
                        <div style="font-size:.72rem;color:#9ca3af;margin-top:.1rem">
                            {{ $employee->company->name ?? 'No company' }}
                            @if ($employee->profile?->designation?->name) · {{ $employee->profile->designation->name }} @endif
                            @if ($employee->profile?->department?->name) · {{ $employee->profile->department->name }} @endif
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;margin-top:.7rem">
                    <span class="eon-band {{ $a->band }}">{{ $a->band }}</span>
                    <span class="eon-chip {{ $confChip }}">
                        <i class="fas fa-circle-half-stroke" style="font-size:.6rem"></i>
                        {{ $a->confidence }} confidence
                    </span>
                    @if ($a->get('rank'))
                        <span class="eon-chip">Rank {{ $a->get('rank') }} of {{ $a->get('rank_of') }}</span>
                    @endif
                    @if (($risk['score'] ?? 0) > 0)
                        <span class="eon-chip {{ ($risk['score'] ?? 0) >= 60 ? 'low' : 'med' }}">
                            Attention {{ $risk['score'] }}
                        </span>
                    @endif
                    <span class="eon-chip">{{ $trend['label'] ?? 'Trend unknown' }}</span>
                </div>

                <div style="font-size:.86rem;line-height:1.6;margin-top:.7rem;color:#374151;max-width:76ch">
                    {{ $a->get('headline') }}
                </div>

                @if ($a->confidence === 'low')
                    <div class="eon-warn" style="margin-top:.6rem">
                        <b>Low confidence.</b> There is not enough history here for the score to carry weight —
                        treat it as a prompt to look at the work, not as a finding about the person.
                    </div>
                @endif
            </div>

            <div style="display:flex;flex-direction:column;gap:.4rem;">
                @if ($hasBoardRoute)
                    <a class="eon-btn primary" href="{{ route('role.task-oversight.employee', ['role' => $role, 'employee' => $employee->id]) }}">
                        <i class="fas fa-columns"></i> Open their board
                    </a>
                @endif
                <a class="eon-btn" target="_blank" rel="noopener"
                   href="{{ route('role.eon.employee.print', ['role' => $role, 'employee' => $employee->id]) }}">
                    <i class="fas fa-print"></i> Print review sheet
                </a>
            </div>
        </div>
    </div>

    {{-- ── What EON noticed, and how it scored ──────────────────────── --}}
    <div class="eon-grid-2" style="margin-top:.9rem">
        <div class="eon-card">
            <div class="eon-card-h">
                <div class="eon-card-t"><i class="fas fa-magnifying-glass-chart"></i> What EON noticed</div>
                <div class="eon-card-n">{{ count($a->signals) }} {{ Str::plural('finding', count($a->signals)) }}</div>
            </div>
            <div class="eon-card-b">
                @include('eon::partials.signals', ['signals' => $a->rankedSignals()])
            </div>
        </div>

        <div class="eon-card">
            <div class="eon-card-h">
                <div class="eon-card-t"><i class="fas fa-scale-balanced"></i> How the score is built</div>
            </div>
            <div class="eon-card-b">
                @include('eon::partials.pillars', ['pillars' => $a->pillars])

                <div class="eon-note" style="margin-top:.9rem;padding-top:.7rem;border-top:1px solid #f1f3f5">
                    {{ $a->get('score_basis') }}
                </div>
            </div>
        </div>
    </div>

    {{-- ── This month against last ──────────────────────────────────── --}}
    <div class="eon-grid-2" style="margin-top:.9rem">
        <div class="eon-card">
            <div class="eon-card-h">
                <div class="eon-card-t"><i class="fas fa-right-left"></i> {{ $mtdLabel }} against {{ $prevLabel }}</div>
                <div class="eon-card-n">Both through day {{ $mtdDays }}</div>
            </div>
            <div class="eon-card-b tight">
                <div class="eon-tw">
                    <table class="eon-table eon-vs">
                        <thead>
                            <tr>
                                <th></th>
                                <th class="num">{{ $mtdLabel }}</th>
                                <th class="num">{{ $prevLabel }}</th>
                                <th class="num">Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                /* Each row carries its NUMBERS and its display
                                   text separately. The first version formatted
                                   the on-time row as "67%" and then tried to
                                   compute a delta from it — is_numeric('67%')
                                   is false, so that row's change silently
                                   rendered as a dash on every dossier. */
                                $onTimeNow  = $a->mtd->with_due  ? $a->onTimeRate($a->mtd)  : null;
                                $onTimeThen = $a->prev->with_due ? $a->onTimeRate($a->prev) : null;

                                $rows = [
                                    ['Completed', (int) $a->mtd->completed, (int) $a->prev->completed,
                                        (int) $a->mtd->completed, (int) $a->prev->completed, true],
                                    ['New work arrived', (int) $a->mtd->created, (int) $a->prev->created,
                                        null, null, true],
                                    ['Delivered on time',
                                        $onTimeNow  === null ? '—' : round($onTimeNow) . '%',
                                        $onTimeThen === null ? '—' : round($onTimeThen) . '%',
                                        $onTimeNow, $onTimeThen, true],
                                    ['Days active', (int) $a->mtd->active_days, (int) $a->prev->active_days,
                                        (int) $a->mtd->active_days, (int) $a->prev->active_days, true],
                                    ['Actions logged', (int) $a->mtd->activity, (int) $a->prev->activity,
                                        (int) $a->mtd->activity, (int) $a->prev->activity, true],
                                ];
                            @endphp

                            @foreach ($rows as [$label, $showNow, $showThen, $numNow, $numThen, $goodUp])
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="num">{{ $showNow }}</td>
                                    <td class="num" style="color:#9ca3af">{{ $showThen }}</td>
                                    <td class="num">
                                        @include('eon::partials.delta', [
                                            'value' => ($numNow !== null && $numThen !== null && $numThen > 0)
                                                ? (($numNow - $numThen) / $numThen) * 100 : null,
                                            'goodUp' => $goodUp,
                                        ])
                                    </td>
                                </tr>
                            @endforeach

                            <tr>
                                <td>Completed, last 30 days</td>
                                <td class="num">{{ (int) $a->rolling->completed }}</td>
                                <td class="num" style="color:#9ca3af">{{ (int) $a->rollingPrev->completed }}</td>
                                <td class="num">
                                    @include('eon::partials.delta', ['value' => $output['rolling_change'] ?? null, 'goodUp' => true])
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="eon-note" style="padding:.7rem 1rem 0">
                    Both columns cover the same number of days, so the comparison is like for like —
                    a part-month set against a whole one would show a collapse that is only the calendar.
                    The <b>last 30 days</b> row is what the Output score is actually built on.
                </div>
            </div>
        </div>

        {{-- ── Standing backlog ─────────────────────────────────────── --}}
        <div class="eon-card">
            <div class="eon-card-h">
                <div class="eon-card-t"><i class="fas fa-layer-group"></i> Where the work stands now</div>
            </div>
            <div class="eon-card-b">
                <div class="eon-dd">
                    <div class="eon-dd-i">
                        <div class="eon-dd-l">Assigned</div>
                        <div class="eon-dd-v">{{ (int) $a->standing->total }}</div>
                        <div class="eon-dd-n">{{ (int) $a->standing->done }} completed</div>
                    </div>
                    <div class="eon-dd-i">
                        <div class="eon-dd-l">Still open</div>
                        <div class="eon-dd-v">{{ (int) $a->standing->open }}</div>
                        <div class="eon-dd-n">
                            {{ (int) $a->standing->in_progress }} in progress · {{ (int) $a->standing->todo }} waiting
                        </div>
                    </div>
                    <div class="eon-dd-i">
                        <div class="eon-dd-l">Past due</div>
                        <div class="eon-dd-v" style="color:{{ $a->standing->overdue ? '#dc2626' : '#111827' }}">
                            {{ (int) $a->standing->overdue }}
                        </div>
                        <div class="eon-dd-n">
                            {{ (int) $a->standing->overdue_week }} this week ·
                            {{ (int) $a->standing->overdue_older }} over 30d
                        </div>
                    </div>
                    <div class="eon-dd-i">
                        <div class="eon-dd-l">Oldest overdue</div>
                        <div class="eon-dd-v">{{ $a->standing->oldest_overdue === null ? '—' : $a->standing->oldest_overdue . 'd' }}</div>
                        <div class="eon-dd-n">open work averages {{ $a->standing->open_age_days === null ? '—' : round($a->standing->open_age_days) . 'd' }} old</div>
                    </div>
                    {{-- The recording gap gets a tile of its own rather than
                         being folded into "open" or "done". It is neither. --}}
                    <div class="eon-dd-i">
                        <div class="eon-dd-l">In DONE, unmarked</div>
                        <div class="eon-dd-v" style="color:{{ ($a->standing->unmarked_done ?? 0) ? '#b45309' : '#111827' }}">
                            {{ (int) ($a->standing->unmarked_done ?? 0) }}
                        </div>
                        <div class="eon-dd-n">never stamped complete</div>
                    </div>
                    <div class="eon-dd-i">
                        <div class="eon-dd-l">Cancelled</div>
                        <div class="eon-dd-v">{{ (int) $a->standing->cancelled }}</div>
                        <div class="eon-dd-n">closed without completing</div>
                    </div>
                    <div class="eon-dd-i">
                        <div class="eon-dd-l">Last activity</div>
                        <div class="eon-dd-v" style="font-size:.86rem">
                            {{ $a->lastActivityAt ? $a->lastActivityAt->format('j M Y') : 'never' }}
                        </div>
                        <div class="eon-dd-n">
                            {{ ($engagement['silent_days'] ?? null) !== null ? $engagement['silent_days'] . ' days ago' : '' }}
                        </div>
                    </div>
                </div>

                {{-- Weekly cadence — a rhythm reads faster than a table. --}}
                @php $peakWeek = max(1, max(array_map(fn ($w2) => (int) $w2['count'], $weekly ?: [['count' => 0]]))); @endphp
                <div style="margin-top:1rem">
                    <div class="eon-label">Completions by week, last {{ count($weekly) }} weeks</div>
                    <div class="eon-cadence" style="height:46px">
                        @foreach ($weekly as $wk)
                            <div class="{{ $wk['count'] ? '' : 'zero' }}"
                                 style="height:{{ max(3, ($wk['count'] / $peakWeek) * 46) }}px"
                                 title="Week of {{ $wk['label'] }} — {{ $wk['count'] }} completed"></div>
                        @endforeach
                    </div>
                    <div class="eon-cadence-l">
                        {{-- $loop->index, not the array key: $weekly is keyed by
                             the week's Monday ('2026-06-01'), so a modulo on the
                             key is a non-numeric warning on every render. --}}
                        @foreach ($weekly as $wk)
                            <span>{{ $loop->index % 3 === 0 ? $wk['label'] : '' }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── The trend, and the drawdown ──────────────────────────────── --}}
    <div class="eon-grid-2" style="margin-top:.9rem">
        <div class="eon-card">
            <div class="eon-card-h">
                <div class="eon-card-t"><i class="fas fa-chart-column"></i> Twelve months of work</div>
                <div class="eon-card-n">{{ $trend['label'] ?? '' }}</div>
            </div>
            <div class="eon-card-b">
                @include('eon::partials.bars', ['series' => $a->series, 'showCreated' => true, 'height' => 170])
            </div>
        </div>

        <div class="eon-card">
            <div class="eon-card-h">
                <div class="eon-card-t"><i class="fas fa-arrow-trend-down"></i> Drawdown from their own peak</div>
            </div>
            <div class="eon-card-b">
                @if ($dd && ($dd['peak'] ?? 0) >= 1)
                    <div class="eon-dd">
                        <div class="eon-dd-i">
                            <div class="eon-dd-l">Peak month</div>
                            <div class="eon-dd-v">{{ round($dd['peak']) }}</div>
                            <div class="eon-dd-n">{{ $dd['peak_month'] }}</div>
                        </div>
                        <div class="eon-dd-i">
                            <div class="eon-dd-l">Last whole month</div>
                            <div class="eon-dd-v">{{ round($dd['current']) }}</div>
                            <div class="eon-dd-n">{{ $dd['current_month'] }}</div>
                        </div>
                        <div class="eon-dd-i">
                            <div class="eon-dd-l">Below peak</div>
                            <div class="eon-dd-v" style="color:{{ ($dd['from_peak_pct'] ?? 0) >= 40 ? '#dc2626' : '#111827' }}">
                                {{ $dd['from_peak_pct'] === null ? '—' : round($dd['from_peak_pct']) . '%' }}
                            </div>
                            <div class="eon-dd-n">
                                {{ ($dd['months_below'] ?? 0) ? $dd['months_below'] . ' months under it' : 'at or near peak' }}
                            </div>
                        </div>
                        <div class="eon-dd-i">
                            <div class="eon-dd-l">Deepest fall</div>
                            <div class="eon-dd-v">{{ $dd['max_pct'] === null ? '—' : round($dd['max_pct']) . '%' }}</div>
                            <div class="eon-dd-n">{{ $dd['max_label'] ? Period::label($dd['max_label']) : '' }}</div>
                        </div>
                    </div>

                    <div class="eon-note" style="margin-top:.8rem">
                        A drawdown measures the fall from a level this person has actually reached — not a target,
                        not the company average. It is the fairest version of the difficult question, because the
                        number being compared against is theirs.
                        @if ($dd['recovering'] ?? false)
                            <b>They are recovering:</b> the last whole month beat the one before it.
                        @endif
                    </div>
                @else
                    <div class="eon-empty">
                        <i class="fas fa-chart-line"></i>
                        Not enough whole months of history to measure a peak yet.
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── The human record ─────────────────────────────────────────── --}}
    <div class="eon-card" style="margin-top:.9rem">
        <div class="eon-card-h">
            <div class="eon-card-t"><i class="fas fa-user-check"></i> The authority's record — ratings, flags and locks</div>
            <div class="eon-card-n">Set by hand in Employee Task Oversight, never by EON</div>
        </div>
        <div class="eon-card-b">
            <div class="eon-dd" style="margin-bottom:.9rem">
                <div class="eon-dd-i">
                    <div class="eon-dd-l">Average rating</div>
                    <div class="eon-dd-v">
                        @if ($a->verdict->avg_rating !== null)
                            {{ number_format($a->verdict->avg_rating, 1) }}
                            <span class="eon-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <i class="fas fa-star {{ $i <= round($a->verdict->avg_rating) ? '' : 'off' }}"></i>
                                @endfor
                            </span>
                        @else
                            —
                        @endif
                    </div>
                    <div class="eon-dd-n">{{ (int) $a->verdict->rated }} tasks rated</div>
                </div>
                <div class="eon-dd-i">
                    <div class="eon-dd-l">Red flags</div>
                    <div class="eon-dd-v" style="color:{{ $a->verdict->flags ? '#dc2626' : '#111827' }}">{{ (int) $a->verdict->flags }}</div>
                    <div class="eon-dd-n">raised on their tasks</div>
                </div>
                <div class="eon-dd-i">
                    <div class="eon-dd-l">Restricted</div>
                    <div class="eon-dd-v">{{ (int) $a->verdict->locks }}</div>
                    <div class="eon-dd-n">tasks locked from editing</div>
                </div>
                <div class="eon-dd-i">
                    <div class="eon-dd-l">Rating direction</div>
                    <div class="eon-dd-v" style="font-size:.9rem">
                        @if ($a->verdict->recent_avg !== null && $a->verdict->earlier_avg !== null)
                            {{ number_format($a->verdict->earlier_avg, 1) }} → {{ number_format($a->verdict->recent_avg, 1) }}
                        @else
                            —
                        @endif
                    </div>
                    <div class="eon-dd-n">before / last 90 days</div>
                </div>
            </div>

            @if (count($judged))
                <div class="eon-tw">
                    <table class="eon-table">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th class="num">Rating</th>
                                <th>Marks</th>
                                <th>Note</th>
                                <th>By, when</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($judged as $j)
                            <tr>
                                <td style="max-width:280px">
                                    <div style="font-weight:600;font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                        {{ $j->title }}
                                    </div>
                                    <div style="font-size:.66rem;color:#9ca3af">
                                        {{ $j->completed_at ? 'completed ' . \Carbon\Carbon::parse($j->completed_at)->format('j M y') : 'open' }}
                                    </div>
                                </td>
                                <td class="num">
                                    @if ($j->rating)
                                        <span class="eon-stars">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <i class="fas fa-star {{ $i <= $j->rating ? '' : 'off' }}"></i>
                                            @endfor
                                        </span>
                                    @else
                                        <span style="color:#d1d5db">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($j->red_flag)<span class="eon-chip low"><i class="fas fa-flag"></i> flagged</span>@endif
                                    @if ($j->is_restricted)<span class="eon-chip med"><i class="fas fa-lock"></i> locked</span>@endif
                                    @if (!$j->red_flag && !$j->is_restricted)<span style="color:#d1d5db;font-size:.72rem">—</span>@endif
                                </td>
                                <td style="max-width:320px;font-size:.74rem;color:#6b7280">
                                    {{ $j->rating_note ?: $j->red_flag_note ?: $j->restriction_note ?: '—' }}
                                </td>
                                <td style="font-size:.7rem;color:#9ca3af;white-space:nowrap">
                                    {{ $j->rated_by_name ?: $j->flagged_by_name ?: $j->locked_by_name ?: '—' }}<br>
                                    {{ $j->rated_at ? \Carbon\Carbon::parse($j->rated_at)->format('j M y') : '' }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="eon-empty">
                    <i class="fas fa-star"></i>
                    Nothing on this person has been rated, flagged or locked yet.
                    @if ($hasBoardRoute)
                        <div style="margin-top:.6rem">
                            <a class="eon-btn" href="{{ route('role.task-oversight.employee', ['role' => $role, 'employee' => $employee->id]) }}">
                                Open their board to rate some work
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- ── The overdue list ─────────────────────────────────────────── --}}
    @if (count($overdue))
        <div class="eon-card" style="margin-top:.9rem">
            <div class="eon-card-h">
                <div class="eon-card-t"><i class="fas fa-clock"></i> Open and past due</div>
                <div class="eon-card-n">Oldest first · {{ (int) $a->standing->overdue }} in total</div>
            </div>
            <div class="eon-card-b tight">
                <div class="eon-tw">
                    <table class="eon-table">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Column</th>
                                <th>Priority</th>
                                <th class="num">Due</th>
                                <th class="num">Days late</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($overdue as $t)
                            <tr>
                                <td style="max-width:420px">
                                    <div style="font-weight:600;font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $t->title }}</div>
                                </td>
                                <td style="font-size:.72rem;color:#6b7280">{{ $t->column_name ?: '—' }}</td>
                                <td style="font-size:.72rem;color:#6b7280">{{ $t->priority ?: '—' }}</td>
                                <td class="num" style="font-size:.72rem;color:#6b7280">
                                    {{ \Carbon\Carbon::parse($t->due_date)->format('j M y') }}
                                </td>
                                <td class="num {{ $t->days_late > 30 ? 'bad' : 'warn' }}">{{ $t->days_late }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ── The recording gap ────────────────────────────────────────── --}}
    @if (count($unmarked))
        <div class="eon-card" style="margin-top:.9rem">
            <div class="eon-card-h">
                <div class="eon-card-t"><i class="fas fa-circle-question"></i> Sitting in DONE, never marked complete</div>
                <div class="eon-card-n">{{ (int) ($a->standing->unmarked_done ?? 0) }} in total</div>
            </div>
            <div class="eon-card-b tight">
                <div style="padding:0 1rem .6rem">
                    <div class="eon-note">
                        The card was dragged to DONE but the completion was never recorded, so EON counts these
                        as <b>neither completed nor overdue</b> — the record does not say which they are. They are
                        the cheapest thing on this page to fix, and every figure above gets sharper when they are.
                    </div>
                </div>
                <div class="eon-tw">
                    <table class="eon-table">
                        <thead>
                            <tr><th>Task</th><th class="num">Due</th><th class="num">Last touched</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($unmarked as $u)
                            <tr>
                                <td style="max-width:520px">
                                    <div style="font-weight:600;font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $u->title }}</div>
                                </td>
                                <td class="num" style="font-size:.72rem;color:#6b7280">
                                    {{ $u->due_date ? \Carbon\Carbon::parse($u->due_date)->format('j M y') : '—' }}
                                </td>
                                <td class="num" style="font-size:.72rem;color:#9ca3af">
                                    {{ $u->updated_at ? \Carbon\Carbon::parse($u->updated_at)->format('j M y') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Your judgement ───────────────────────────────────────────── --}}
    <div class="eon-card" style="margin-top:.9rem">
        <div class="eon-card-h">
            <div class="eon-card-t"><i class="fas fa-pen-to-square"></i> Written review — {{ Period::label($period) }}</div>
            <div class="eon-card-n">Yours, not EON's</div>
        </div>
        <div class="eon-card-b">
            @if (!$reviewsInstalled)
                <div class="eon-warn">
                    <b>Reviews are not installed on this server yet.</b>
                    Everything above works; saving a written review needs the EON migration to have been run.
                    Until then this panel is read-only.
                </div>
            @else
                <div class="eon-grid-2">
                    <form method="POST" action="{{ route('role.eon.review.save', ['role' => $role, 'employee' => $employee->id]) }}">
                        @csrf
                        <input type="hidden" name="period" value="{{ $period }}">

                        <div class="eon-field">
                            <label class="eon-label">Your overall rating for {{ Period::label($period) }}</label>
                            <div class="eon-starpick" id="eonStars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <input type="radio" name="overall_rating" id="eon-star-{{ $i }}" value="{{ $i }}"
                                           @checked(($review->overall_rating ?? null) == $i)>
                                    <label for="eon-star-{{ $i }}" data-star="{{ $i }}"
                                           class="{{ ($review->overall_rating ?? 0) >= $i ? 'on' : '' }}"
                                           title="{{ $i }} of 5">★</label>
                                @endfor
                                <span style="font-size:.72rem;color:#9ca3af;margin-left:.4rem;align-self:center">
                                    {{ $review?->overall_rating ? $review->overall_rating . ' of 5' : 'not set' }}
                                </span>
                            </div>
                        </div>

                        <div class="eon-field">
                            <label class="eon-label">Verdict</label>
                            <select name="verdict" class="eon-select">
                                <option value="">— choose —</option>
                                @foreach (EonReview::VERDICTS as $key => $label)
                                    <option value="{{ $key }}" @selected(($review->verdict ?? null) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="eon-field">
                            <label class="eon-label">What you saw</label>
                            <textarea name="summary" class="eon-textarea"
                                      placeholder="Your own reading of this person's month.">{{ $review->summary ?? '' }}</textarea>
                        </div>

                        <div class="eon-field">
                            <label class="eon-label">What happens next</label>
                            <textarea name="actions" class="eon-textarea"
                                      placeholder="Agreed actions, support, deadlines.">{{ $review->actions ?? '' }}</textarea>
                        </div>

                        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                            <button type="submit" class="eon-btn primary"><i class="fas fa-check"></i> Save review</button>
                            @if ($review)
                                <span class="eon-note">
                                    Last saved by {{ $review->reviewer->name ?? 'someone' }}
                                    on {{ $review->reviewed_at?->format('j M Y, H:i') }}
                                </span>
                            @endif
                        </div>

                        <div class="eon-note" style="margin-top:.7rem">
                            Saving also freezes EON's current reading beside your words, so this review still makes
                            sense next year when the tasks behind it have moved on.
                        </div>
                    </form>

                    <div>
                        <div class="eon-label">EON's suggested starting points</div>
                        <div style="font-size:.78rem;line-height:1.65;color:#374151">
                            @foreach ($summary as $para)
                                <p style="margin-bottom:.5rem">{{ $para }}</p>
                            @endforeach
                        </div>

                        @if (count($reviews))
                            <div class="eon-label" style="margin-top:1rem">Earlier reviews</div>
                            @foreach ($reviews as $r)
                                <div style="padding:.5rem 0;border-bottom:1px solid #f6f7f8">
                                    <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:center">
                                        <div style="font-size:.78rem;font-weight:650">{{ Period::label($r->period) }}</div>
                                        <div style="display:flex;gap:.35rem;align-items:center">
                                            @if ($r->overall_rating)
                                                <span class="eon-stars">
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <i class="fas fa-star {{ $i <= $r->overall_rating ? '' : 'off' }}"></i>
                                                    @endfor
                                                </span>
                                            @endif
                                            @if ($r->machine_score !== null)
                                                <span class="eon-chip" title="What EON was saying when this review was written">
                                                    EON {{ round($r->machine_score) }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div style="font-size:.7rem;color:#9ca3af">{{ $r->verdictLabel() }}</div>
                                    @if ($r->summary)
                                        <div style="font-size:.74rem;color:#6b7280;margin-top:.2rem">{{ Str::limit($r->summary, 180) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
/* EON — the star picker, and nothing else.
 *
 * Scoped hard on purpose: it looks up ONE element by its own id, exits if that
 * element is not on the page, and never touches anything outside it. No shared
 * helper is patched, no document-level listener is added, no selector here can
 * match markup belonging to another feature. This file is loaded only by EON
 * views, which are only reachable by a super admin on a /{role}/eon/* URL.
 */
(function () {
    var picker = document.getElementById('eonStars');
    if (!picker) { return; }

    var labels = picker.querySelectorAll('label[data-star]');

    function paint(upTo) {
        labels.forEach(function (label) {
            label.classList.toggle('on', parseInt(label.dataset.star, 10) <= upTo);
        });
    }

    function chosen() {
        var checked = picker.querySelector('input:checked');
        return checked ? parseInt(checked.value, 10) : 0;
    }

    labels.forEach(function (label) {
        label.addEventListener('mouseenter', function () { paint(parseInt(label.dataset.star, 10)); });
        label.addEventListener('click', function () { setTimeout(function () { paint(chosen()); }, 0); });
    });

    picker.addEventListener('mouseleave', function () { paint(chosen()); });
})();
</script>
@endsection
