<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EON review — {{ $employee->name }} — {{ \Modules\EON\Intelligence\Support\Period::label($period) }}</title>

    {{--
      The printable sheet.

      SELF-CONTAINED ON PURPOSE — it does not extend layout.app. That layout
      carries a sidebar, a header, a live Pusher socket and the whole Vite
      bundle, none of which belong on a sheet of A4, and opening a second
      socket from a print window is a real cost for a page whose only job is to
      be put on a desk.

      No shared asset is loaded and no shared style is touched, so this file
      cannot affect any other page in the ERP even in principle.

      The sheet is deliberately a RECORD, not a verdict: EON's reading and the
      authority's written review are printed in separate blocks with their
      sources named, and there is a signature line, because what gets filed
      should show who decided it.
    --}}
    <style>
        @page { size: A4; margin: 14mm 12mm; }

        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, Arial, sans-serif;
            color: #111827; font-size: 11px; line-height: 1.5; background: #fff;
        }

        .sheet { max-width: 190mm; margin: 0 auto; padding: 6mm; }

        .row { display: flex; gap: 10px; }
        .col { flex: 1; }

        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 8px; }
        .brand { font-size: 17px; font-weight: 800; letter-spacing: -.02em; }
        .brand span { color: #2563eb; }
        .brand-n { font-size: 10px; color: #6b7280; margin-top: 2px; }
        .period { text-align: right; font-size: 10px; color: #6b7280; }
        .period b { display: block; font-size: 13px; color: #111827; }

        .who { display: flex; justify-content: space-between; align-items: center; margin: 12px 0 4px; }
        .who-n { font-size: 15px; font-weight: 700; }
        .who-m { font-size: 10px; color: #6b7280; }

        .score-box { text-align: center; border: 2px solid #111827; border-radius: 6px; padding: 6px 14px; min-width: 92px; }
        .score-v { font-size: 26px; font-weight: 800; line-height: 1; }
        .score-l { font-size: 8px; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; margin-top: 2px; }
        .score-b { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; margin-top: 3px; }

        h2 {
            font-size: 10px; text-transform: uppercase; letter-spacing: .07em; color: #6b7280;
            margin: 14px 0 5px; padding-bottom: 3px; border-bottom: 1px solid #e5e7eb; font-weight: 700;
        }

        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th { text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; padding: 3px 5px; border-bottom: 1px solid #e5e7eb; }
        td { padding: 3px 5px; border-bottom: 1px solid #f3f4f6; }
        td.num, th.num { text-align: right; }

        .pillar { display: flex; align-items: center; gap: 7px; margin-bottom: 4px; }
        .pillar-n { width: 82px; font-size: 10px; font-weight: 600; }
        .pillar-bar { flex: 1; height: 6px; background: #f1f3f5; border-radius: 3px; overflow: hidden; }
        .pillar-bar span { display: block; height: 100%; background: #2563eb; }
        .pillar-v { width: 30px; text-align: right; font-size: 10px; font-weight: 700; }

        ul.findings { margin: 0; padding-left: 14px; }
        ul.findings li { margin-bottom: 3px; }
        .sev { font-weight: 700; }
        .sev.critical { color: #b91c1c; }
        .sev.watch { color: #b45309; }
        .sev.good { color: #047857; }
        .sev.neutral { color: #6b7280; }

        .box { border: 1px solid #e5e7eb; border-radius: 5px; padding: 7px 9px; }
        .box + .box { margin-top: 6px; }
        .box-l { font-size: 8px; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; font-weight: 700; margin-bottom: 3px; }
        .written { min-height: 42px; white-space: pre-wrap; }
        .blank { color: #cbd5e1; font-style: italic; }

        .stars { color: #b45309; letter-spacing: 1px; }
        .stars i { font-style: normal; }
        .stars .off { color: #d1d5db; }

        .sig { display: flex; gap: 26px; margin-top: 20px; }
        .sig div { flex: 1; border-top: 1px solid #111827; padding-top: 4px; font-size: 9px; color: #6b7280; }

        .foot { margin-top: 14px; padding-top: 6px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; line-height: 1.5; }

        .noprint { margin-bottom: 10px; }
        .noprint button {
            font: inherit; font-size: 11px; padding: 6px 14px; border-radius: 6px;
            border: 1px solid #2563eb; background: #2563eb; color: #fff; cursor: pointer;
        }
        @media print { .noprint { display: none !important; } }
    </style>
</head>
<body>
@php
    use Modules\EON\Intelligence\Support\Period;

    $timeliness = $a->get('timeliness', []);
    $dd         = $a->get('drawdown');
    $trend      = $a->get('trend', []);
    $mtdLabel   = $a->mtdWindow['label'] ?? '';
    $prevLabel  = $a->prevWindow['label'] ?? '';
    $mtdDays    = $a->mtdWindow['days'] ?? null;
@endphp

<div class="sheet">

    <div class="noprint">
        <button type="button" onclick="window.print()">Print this sheet</button>
    </div>

    <div class="head">
        <div>
            <div class="brand"><span>EON</span> Employee Evaluation</div>
            <div class="brand-n">{{ config('app.name') }} · generated {{ now()->format('j M Y, H:i') }}</div>
        </div>
        <div class="period">
            Review period
            <b>{{ Period::label($period) }}</b>
            through day {{ $mtdDays }}
        </div>
    </div>

    <div class="who">
        <div>
            <div class="who-n">{{ $employee->name }}</div>
            <div class="who-m">
                {{ $employee->employee_id_no ? 'ID ' . $employee->employee_id_no . ' · ' : '' }}
                {{ $employee->company->name ?? 'No company' }}
                @if ($employee->profile?->designation?->name) · {{ $employee->profile->designation->name }} @endif
                @if ($employee->profile?->department?->name) · {{ $employee->profile->department->name }} @endif
            </div>
        </div>
        <div class="score-box">
            <div class="score-v">{{ $a->score === null ? '—' : round($a->score) }}</div>
            <div class="score-l">EON score</div>
            <div class="score-b">{{ $a->band }}</div>
        </div>
    </div>

    <div style="font-size:9px;color:#6b7280;margin-bottom:4px">
        Confidence: <b>{{ $a->confidence }}</b>
        @if ($a->confidence === 'low')
            — not enough history for this figure to carry weight; read it as a prompt, not a finding.
        @endif
    </div>

    {{-- ── The machine's reading ────────────────────────────────────── --}}
    <h2>What the record shows — computed by EON</h2>

    <div class="row">
        <div class="col">
            @foreach ($a->pillars as $p)
                <div class="pillar">
                    <div class="pillar-n">{{ $p->label }}</div>
                    <div class="pillar-bar">
                        <span style="width:{{ $p->known() ? $p->rounded() : 0 }}%"></span>
                    </div>
                    <div class="pillar-v">{{ $p->known() ? $p->rounded() : 'n/a' }}</div>
                </div>
            @endforeach
            <div style="font-size:8px;color:#9ca3af;margin-top:4px">
                Weights — Output 25, Timeliness 30, Quality 20, Consistency 15, Engagement 10.
                A dimension with no evidence is left out and the rest re-weighted, never scored zero.
            </div>
        </div>

        <div class="col">
            <table>
                <tr>
                    <th></th>
                    <th class="num">{{ $mtdLabel }}</th>
                    <th class="num">{{ $prevLabel }}</th>
                </tr>
                <tr>
                    <td>Completed</td>
                    <td class="num">{{ (int) $a->mtd->completed }}</td>
                    <td class="num">{{ (int) $a->prev->completed }}</td>
                </tr>
                <tr>
                    <td>New work arrived</td>
                    <td class="num">{{ (int) $a->mtd->created }}</td>
                    <td class="num">{{ (int) $a->prev->created }}</td>
                </tr>
                <tr>
                    <td>Delivered on time</td>
                    <td class="num">{{ $a->mtd->with_due ? round($a->onTimeRate($a->mtd)) . '%' : '—' }}</td>
                    <td class="num">{{ $a->prev->with_due ? round($a->onTimeRate($a->prev)) . '%' : '—' }}</td>
                </tr>
                <tr>
                    <td>Open past due</td>
                    <td class="num">{{ (int) $a->standing->overdue }}</td>
                    <td class="num">—</td>
                </tr>
                <tr>
                    <td>Over 30 days past due</td>
                    <td class="num">{{ (int) $a->standing->overdue_older }}</td>
                    <td class="num">—</td>
                </tr>
                {{-- On paper especially, the overdue figures above have to say
                     what they exclude — a filed sheet is read without anybody
                     around to explain it. --}}
                <tr>
                    <td>In DONE, never marked complete</td>
                    <td class="num">{{ (int) ($a->standing->unmarked_done ?? 0) }}</td>
                    <td class="num">—</td>
                </tr>
                <tr>
                    <td>Trend</td>
                    <td class="num" colspan="2">{{ $trend['label'] ?? '—' }}</td>
                </tr>
                @if ($dd && ($dd['from_peak_pct'] ?? null) !== null)
                    <tr>
                        <td>Against their peak month</td>
                        <td class="num" colspan="2">
                            {{ round($dd['from_peak_pct']) }}% below {{ round($dd['peak']) }} in {{ $dd['peak_month'] }}
                        </td>
                    </tr>
                @endif
            </table>
        </div>
    </div>

    <h2>Findings</h2>
    <ul class="findings">
        @forelse ($a->rankedSignals(8) as $s)
            <li>
                <span class="sev {{ $s->severity }}">{{ ucfirst($s->severity) }}:</span>
                {{ $s->title }}@if ($s->detail) — {{ $s->detail }}@endif
            </li>
        @empty
            <li>Nothing stood out.</li>
        @endforelse
    </ul>

    <h2>Summary EON offers</h2>
    <div style="font-size:10px">
        @foreach ($summary as $para)
            <p style="margin:0 0 4px">{{ $para }}</p>
        @endforeach
    </div>

    {{-- ── The human record ─────────────────────────────────────────── --}}
    <h2>The authority's record — set by hand, not by EON</h2>

    <div class="row">
        <div class="col">
            <table>
                <tr>
                    <td>Tasks rated</td>
                    <td class="num">{{ (int) $a->verdict->rated }}</td>
                </tr>
                <tr>
                    <td>Average rating</td>
                    <td class="num">
                        @if ($a->verdict->avg_rating !== null)
                            {{ number_format($a->verdict->avg_rating, 1) }} / 5
                        @else
                            not rated
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Red flags</td>
                    <td class="num">{{ (int) $a->verdict->flags }}</td>
                </tr>
                <tr>
                    <td>Tasks locked</td>
                    <td class="num">{{ (int) $a->verdict->locks }}</td>
                </tr>
            </table>
        </div>
        <div class="col">
            @if (count($judged))
                <table>
                    <tr><th>Judged task</th><th class="num">Rating</th></tr>
                    @foreach (array_slice($judged, 0, 6) as $j)
                        <tr>
                            <td>{{ Str::limit($j->title, 48) }}</td>
                            <td class="num">
                                @if ($j->rating)
                                    <span class="stars">
                                        @for ($i = 1; $i <= 5; $i++)<i class="{{ $i <= $j->rating ? '' : 'off' }}">★</i>@endfor
                                    </span>
                                @elseif ($j->red_flag)
                                    flagged
                                @elseif ($j->is_restricted)
                                    locked
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            @else
                <div style="font-size:10px;color:#9ca3af">No task on this person has been rated, flagged or locked.</div>
            @endif
        </div>
    </div>

    {{-- ── The written judgement ────────────────────────────────────── --}}
    <h2>Written review — {{ Period::label($period) }}</h2>

    <div class="box">
        <div class="box-l">Overall rating and verdict</div>
        <div>
            @if ($review?->overall_rating)
                <span class="stars">
                    @for ($i = 1; $i <= 5; $i++)<i class="{{ $i <= $review->overall_rating ? '' : 'off' }}">★</i>@endfor
                </span>
                {{ $review->overall_rating }} of 5
            @else
                <span class="blank">not yet rated</span>
            @endif
            @if ($review?->verdict)
                &nbsp;·&nbsp; {{ $review->verdictLabel() }}
            @endif
        </div>
    </div>

    <div class="box">
        <div class="box-l">What the reviewer saw</div>
        <div class="written">{!! $review?->summary ? e($review->summary) : '<span class="blank">—</span>' !!}</div>
    </div>

    <div class="box">
        <div class="box-l">What happens next</div>
        <div class="written">{!! $review?->actions ? e($review->actions) : '<span class="blank">—</span>' !!}</div>
    </div>

    @if (!$review && count($actions))
        <div class="box">
            <div class="box-l">EON's suggested actions, for the reviewer to accept or reject</div>
            <ul class="findings">
                @foreach ($actions as $action)
                    <li>{{ ucfirst($action) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="sig">
        <div>Reviewed by{{ $review?->reviewer?->name ? ' — ' . $review->reviewer->name : '' }}</div>
        <div>Employee acknowledgement</div>
        <div>Date</div>
    </div>

    <div class="foot">
        EON computes from the task record only: work assigned, completed, dated and logged. It does not
        observe effort, difficulty, collaboration or anything happening away from the board, and it does
        not rate anybody — every rating and red flag on this sheet was entered by a named person.
        The figures above were computed on {{ now()->format('j M Y') }} and will change as the work does;
        the written review, once saved, is stored with a frozen copy of them.
    </div>
</div>
</body>
</html>
