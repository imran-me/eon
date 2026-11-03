@extends('layout.app')

@section('meta-information')
    <title>EON — Employee Intelligence</title>
@endsection

{{--
  EON — the org view.

  One question governs the layout: WHO DO I OPEN FIRST? That is why the table
  sorts by risk rather than by score by default, why the watchlist sits above
  the fold, and why the company's own trend is on the same screen — an
  individual's fall means something different when everybody's line is falling
  too, and a page that shows one without the other invites the wrong
  conversation.

  Every class here is prefixed eon-. The style block is included, not published:
  see partials/styles.blade.php for why.
--}}

@section('css')
    @include('eon::partials.styles')
@endsection

@section('main-content')
@php
    use Modules\EON\Intelligence\People;

    $hasBoardRoute = Route::has('role.task-oversight.employee');
    $sortLinks = [
        'risk'    => 'Needs attention',
        'score'   => 'EON score',
        'output'  => 'Output',
        'overdue' => 'Overdue',
        'flags'   => 'Red flags',
        'rating'  => 'Rating',
        'name'    => 'Name',
    ];
@endphp

<div class="eon-wrap">

    @if (session('success'))<div class="eon-flash ok">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="eon-flash err">{{ session('error') }}</div>@endif

    <div class="eon-head">
        <div>
            <div class="eon-title"><span class="eon-mark">EON</span> — Employee Intelligence</div>
            <div class="eon-sub">
                Evaluation, trends and risk across everyone who can hold a task board.
                Ratings and red flags stay manual — this reads what the work itself says and tells you where to look.
            </div>
        </div>

        <div class="eon-tools">
            <form method="GET" action="{{ route('role.eon.index', ['role' => $role]) }}" class="eon-tools">
                <div class="eon-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" value="{{ $q }}" placeholder="Name, ID, email, company…">
                </div>
                <input type="hidden" name="sort" value="{{ $sort }}">
                <button type="submit" class="eon-btn primary">Search</button>
                @if ($q !== '')
                    <a class="eon-btn" href="{{ route('role.eon.index', ['role' => $role]) }}">Clear</a>
                @endif
            </form>
        </div>
    </div>

    {{-- ── The company in one strip ─────────────────────────────────── --}}
    <div class="eon-pulse">
        <div class="eon-tile">
            <div class="eon-tile-l">Evaluated</div>
            <div class="eon-tile-v">{{ $rollup['active'] }}</div>
            <div class="eon-tile-n">of {{ $rollup['people'] }} with a board</div>
        </div>

        <div class="eon-tile">
            <div class="eon-tile-l">Completed, month to date</div>
            <div class="eon-tile-v">{{ number_format($rollup['completed_mtd']) }}</div>
            <div class="eon-tile-n">
                @include('eon::partials.delta', ['value' => $rollup['change'], 'goodUp' => true])
                vs same days last month
            </div>
        </div>

        <div class="eon-tile {{ ($rollup['on_time'] ?? 100) < 60 ? 'warn' : 'good' }}">
            <div class="eon-tile-l">On time</div>
            <div class="eon-tile-v">{{ $rollup['on_time'] === null ? '—' : round($rollup['on_time']) . '%' }}</div>
            <div class="eon-tile-n">of dated work closed this month</div>
        </div>

        <div class="eon-tile {{ $rollup['overdue'] ? 'bad' : '' }}">
            <div class="eon-tile-l">Open past due</div>
            <div class="eon-tile-v">{{ number_format($rollup['overdue']) }}</div>
            <div class="eon-tile-n">{{ number_format($rollup['overdue_older']) }} over a month old</div>
        </div>

        {{-- Called out on the strip rather than buried: across this install it
             is the largest single distortion in the task record, and every
             overdue figure on this page is cleaner for excluding it. --}}
        <div class="eon-tile {{ ($rollup['unmarked_done'] ?? 0) ? 'warn' : '' }}"
             title="Cards dragged to DONE whose completion was never recorded. EON counts them as neither completed nor overdue.">
            <div class="eon-tile-l">In DONE, unmarked</div>
            <div class="eon-tile-v">{{ number_format($rollup['unmarked_done'] ?? 0) }}</div>
            <div class="eon-tile-n">never stamped complete</div>
        </div>

        <div class="eon-tile {{ $rollup['flags'] ? 'bad' : '' }}">
            <div class="eon-tile-l">Red flags</div>
            <div class="eon-tile-v">{{ number_format($rollup['flags']) }}</div>
            <div class="eon-tile-n">{{ number_format($rollup['locks']) }} tasks locked</div>
        </div>

        <div class="eon-tile">
            <div class="eon-tile-l">Median score</div>
            <div class="eon-tile-v">{{ $rollup['median_score'] === null ? '—' : round($rollup['median_score']) }}</div>
            <div class="eon-tile-n">across everybody scored</div>
        </div>

        <div class="eon-tile {{ $rollup['unrated_people'] ? 'warn' : '' }}">
            <div class="eon-tile-l">Never rated</div>
            <div class="eon-tile-v">{{ $rollup['unrated_people'] }}</div>
            <div class="eon-tile-n">people with no manual rating</div>
        </div>
    </div>

    <div class="eon-grid-2">
        {{-- ── The company's own line ───────────────────────────────── --}}
        <div class="eon-card">
            <div class="eon-card-h">
                <div class="eon-card-t"><i class="fas fa-chart-column"></i> Completed work, company-wide</div>
                <div class="eon-card-n">Last {{ count($orgSeries) }} months</div>
            </div>
            <div class="eon-card-b">
                @include('eon::partials.bars', [
                    'series' => collect($orgSeries)->map(fn ($v) => ['completed' => $v, 'created' => 0])->all(),
                    'showCreated' => false,
                    'height' => 160,
                ])
                <div class="eon-note" style="margin-top:.6rem">
                    Read an individual's fall against this line before treating it as theirs. When the whole
                    company's output moves, a person tracking it is not the story.
                </div>
            </div>
        </div>

        {{-- ── Who to open first ────────────────────────────────────── --}}
        <div class="eon-card">
            <div class="eon-card-h">
                <div class="eon-card-t"><i class="fas fa-triangle-exclamation"></i> Needs attention</div>
                <div class="eon-card-n">{{ count($watchlist) }} flagged by EON</div>
            </div>
            <div class="eon-card-b">
                {{-- Named $wl, not $w. Blade's @include inherits this scope, and
                     a loop variable called $w silently became the sparkline
                     partial's width further down the page. --}}
                @forelse ($watchlist as $wl)
                    @php $risk = $wl->get('risk', []); @endphp
                    <a href="{{ route('role.eon.employee', ['role' => $role, 'employee' => $wl->userId]) }}"
                       style="display:block;text-decoration:none;color:inherit;padding:.5rem 0;border-bottom:1px solid #f6f7f8;">
                        <div style="display:flex;align-items:center;gap:.55rem;">
                            <div class="eon-avatar">
                                @if ($wl->user->image)
                                    <img src="{{ asset($wl->user->image) }}" alt="">
                                @else
                                    {{ People::initials($wl->user->name) }}
                                @endif
                            </div>
                            <div style="min-width:0;flex:1">
                                <div class="eon-person-n">{{ $wl->user->name }}</div>
                                <div class="eon-person-m">{{ implode(' · ', $risk['reasons'] ?? []) ?: 'flagged' }}</div>
                            </div>
                            <span class="eon-band {{ $wl->band }}">{{ $wl->band }}</span>
                        </div>
                    </a>
                @empty
                    <div class="eon-empty"><i class="fas fa-circle-check"></i>Nobody is above the attention threshold.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── The ranking ──────────────────────────────────────────────── --}}
    <div class="eon-card" style="margin-top:.9rem">
        <div class="eon-card-h">
            <div class="eon-card-t"><i class="fas fa-list-ol"></i> Everybody</div>
            <div class="eon-card-n">
                {{ count($rows) }} shown{{ $q !== '' ? ' for “' . $q . '”' : '' }} ·
                sorted by {{ strtolower($sortLinks[$sort] ?? 'needs attention') }}
            </div>
        </div>

        <div class="eon-tw">
            <table class="eon-table">
                <thead>
                    <tr>
                        <th class="{{ $sort === 'name' ? 'is-sorted' : '' }}">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'name']) }}">Person</a>
                        </th>
                        <th class="num {{ $sort === 'score' ? 'is-sorted' : '' }}">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'score']) }}">Score</a>
                        </th>
                        <th>Trend, 12 months</th>
                        <th class="num {{ $sort === 'output' ? 'is-sorted' : '' }}">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'output']) }}" title="Completed month to date, against the same days of last month">Output</a>
                        </th>
                        <th class="num">On time</th>
                        <th class="num {{ $sort === 'overdue' ? 'is-sorted' : '' }}">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'overdue']) }}">Overdue</a>
                        </th>
                        <th class="num {{ $sort === 'flags' ? 'is-sorted' : '' }}">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'flags']) }}">Flags</a>
                        </th>
                        <th class="num {{ $sort === 'rating' ? 'is-sorted' : '' }}">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'rating']) }}">Rated</a>
                        </th>
                        <th class="num {{ $sort === 'risk' ? 'is-sorted' : '' }}">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'risk']) }}">Attention</a>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $a)
                    @php
                        $risk    = $a->get('risk', []);
                        $output  = $a->get('output', []);
                        $onTime  = $a->onTimeRateOverall();
                        $trend   = $a->get('trend', []);
                    @endphp
                    <tr>
                        <td>
                            <div class="eon-person">
                                <div class="eon-avatar">
                                    @if ($a->user->image)
                                        <img src="{{ asset($a->user->image) }}" alt="">
                                    @else
                                        {{ People::initials($a->user->name) }}
                                    @endif
                                </div>
                                <a href="{{ route('role.eon.employee', ['role' => $role, 'employee' => $a->userId]) }}" style="min-width:0">
                                    <div class="eon-person-n">{{ $a->user->name }}</div>
                                    <div class="eon-person-m">
                                        {{ $a->user->company->short_name ?? $a->user->company->name ?? 'No company' }}
                                        @if ($a->user->profile?->designation?->name)
                                            · {{ $a->user->profile->designation->name }}
                                        @endif
                                    </div>
                                </a>
                            </div>
                        </td>

                        <td class="num">
                            <div style="display:flex;align-items:center;gap:.4rem;justify-content:flex-end;">
                                <span class="eon-score-v">{{ $a->score === null ? '—' : round($a->score) }}</span>
                                <span class="eon-band {{ $a->band }}">{{ $a->band }}</span>
                            </div>
                            @if ($a->score !== null && $a->confidence === 'low')
                                <div style="font-size:.6rem;color:#b45309;margin-top:.12rem;">low confidence</div>
                            @endif
                        </td>

                        <td>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                @include('eon::partials.sparkline', [
                                    'values' => array_values($a->completedSeries()),
                                    'stroke' => ($trend['direction'] ?? null) === 'falling' ? '#ef4444'
                                        : ((($trend['direction'] ?? null) === 'rising') ? '#10b981' : '#94a3b8'),
                                ])
                                <span style="font-size:.68rem;color:#9ca3af;white-space:nowrap;">{{ $trend['label'] ?? '' }}</span>
                            </div>
                        </td>

                        <td class="num">
                            <div style="font-weight:650">{{ (int) $a->mtd->completed }}</div>
                            <div style="font-size:.66rem;">
                                @include('eon::partials.delta', ['value' => $output['change'] ?? null, 'goodUp' => true])
                            </div>
                        </td>

                        <td class="num {{ $onTime !== null && $onTime < 60 ? 'warn' : '' }}">
                            {{ $onTime === null ? '—' : round($onTime) . '%' }}
                        </td>

                        <td class="num {{ $a->standing->overdue ? 'bad' : 'mute' }}">
                            {{ (int) $a->standing->overdue }}
                            @if ($a->standing->overdue_older)
                                <div style="font-size:.6rem;color:#b91c1c;">{{ $a->standing->overdue_older }} &gt; 30d</div>
                            @endif
                        </td>

                        <td class="num {{ $a->verdict->flags ? 'bad' : 'mute' }}">{{ (int) $a->verdict->flags }}</td>

                        <td class="num">
                            @if ($a->verdict->avg_rating !== null)
                                <span class="eon-stars">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="fas fa-star {{ $i <= round($a->verdict->avg_rating) ? '' : 'off' }}"></i>
                                    @endfor
                                </span>
                                <div style="font-size:.62rem;color:#9ca3af;">{{ $a->verdict->rated }} rated</div>
                            @else
                                <span style="color:#d1d5db;font-size:.72rem;">not rated</span>
                            @endif
                        </td>

                        <td class="num">
                            @php $rs = (int) ($risk['score'] ?? 0); @endphp
                            <span class="eon-chip {{ $rs >= 60 ? 'low' : ($rs >= 30 ? 'med' : '') }}"
                                  title="{{ implode(' · ', $risk['reasons'] ?? []) ?: 'nothing outstanding' }}">
                                {{ $rs }}
                            </span>
                        </td>

                        {{-- Icon buttons, not "Dossier" / "Board" in words: the
                             row is already at the width the viewport allows and
                             the labelled version was clipped off the right edge
                             at 1440px. The whole row links to the dossier
                             anyway, via the name. --}}
                        <td style="white-space:nowrap;text-align:right;">
                            <a class="eon-btn" style="padding:.28rem .45rem;font-size:.72rem"
                               title="Open EON dossier"
                               href="{{ route('role.eon.employee', ['role' => $role, 'employee' => $a->userId]) }}"><i class="fas fa-chart-simple"></i></a>
                            @if ($hasBoardRoute)
                                <a class="eon-btn" style="padding:.28rem .45rem;font-size:.72rem"
                                   title="Open their task board"
                                   href="{{ route('role.task-oversight.employee', ['role' => $role, 'employee' => $a->userId]) }}"><i class="fas fa-table-columns"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            <div class="eon-empty">
                                <i class="fas fa-user-slash"></i>
                                {{ $q !== '' ? 'Nobody matches “' . $q . '”.' : 'No evaluable people yet.' }}
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── How to read all this ─────────────────────────────────────── --}}
    <div class="eon-card" style="margin-top:.9rem">
        <div class="eon-card-h">
            <div class="eon-card-t"><i class="fas fa-circle-info"></i> How EON reaches a score</div>
        </div>
        <div class="eon-card-b eon-method">
            <p>
                Five dimensions, weighted: <b>Output 25</b>, <b>Timeliness 30</b>, <b>Quality 20</b>,
                <b>Consistency 15</b>, <b>Engagement 10</b>. A dimension with no evidence is
                <b>left out and the rest re-weighted</b> — never scored zero, because "nobody can tell"
                and "they failed" are different findings.
            </p>
            <ul>
                <li><b>Output</b> — the last 30 days against their own median month; the company median only for people too new to have one.</li>
                <li><b>Timeliness</b> — the share of dated work delivered by its due date, plus the age of what is still open past due. On-time is judged by the due <b>day</b>: due dates here carry a time of day that is rarely a hard deadline.</li>
                <li><b>Quality</b> — the authority's manual stars lead at two thirds. With no ratings on file, this reads "not known" rather than perfect.</li>
                <li><b>Consistency</b> — month-to-month variation, so a predictable worker is distinguishable from a spiky one at the same average.</li>
                <li><b>Engagement</b> — days present on the board out of working days. Capped so it can never sink somebody whose actual work is strong.</li>
            </ul>
            <p style="margin-top:.5rem">
                <b>Every score carries a confidence.</b> Four tasks and one month produce a number, and EON
                labels it low confidence — treat it as a prompt to look, never as a finding.
                EON does not rate anybody. It reads what the work records and says where to look; the
                judgement stays with you, and is written down in the review on each person's page.
            </p>
        </div>
    </div>
</div>
@endsection
