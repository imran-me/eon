{{--
    Single-task report — rendered by dompdf from
    Task\TaskController@report (the drawer's "Task report" button).

    dompdf constraints this file has to respect:
      • no flexbox / grid — every layout here is a table,
      • no remote assets — the logo arrives as a filesystem path, and images
        inside the description survive only as inline data: URIs,
      • DejaVu Sans is the only font with full glyph coverage.
--}}
@php
    $statusName = $task->column?->name ?? '—';
    $doneCount  = $subtasks->whereNotNull('completed_at')->count();
    $totalCount = $subtasks->count();
    $percent    = $totalCount
        ? (int) round($doneCount / $totalCount * 100)
        : ($task->completed_at ? 100 : 0);

    $fmt       = fn ($d) => $d ? $d->format('d M Y, h:i A') : '—';
    $isOverdue = $task->due_date && ! $task->completed_at && $task->due_date->isPast();

    $prioClass = ['high' => 'chip-red', 'medium' => 'chip-amber', 'low' => 'chip-green'][$task->priority] ?? 'chip-gray';

    // Summernote HTML made safe for dompdf: script/style/iframe gone, and any
    // image that is not an inline data: URI dropped — remote fetching is off, so
    // one would print as a broken-image box.
    $description = trim((string) $task->description);
    if ($description !== '') {
        $description = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $description);
        $description = preg_replace('#<img\b(?![^>]*src\s*=\s*["\']data:)[^>]*>#i', '', $description);
        $description = strip_tags($description, '<p><br><b><strong><i><em><u><s><strike><del><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><a><table><thead><tbody><tr><th><td><span><div><code><pre><hr><img><font>');
        $description = trim($description);
    }
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>TASK-{{ $task->id }} — Task Report</title>
    <style>
        @page { margin: 32px 30px 46px 30px; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
        }

        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }

        /* A row split across two pages reads as two half-rows; a section heading
           stranded at the foot of a page reads as a mistake. */
        tr { page-break-inside: avoid; }
        h2.section { page-break-after: avoid; }

        /* Header */
        .head { border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 12px; }
        .head td { vertical-align: middle; }
        .head .logo { width: 46px; }
        .head .logo img { height: 34px; }
        .head .org { font-size: 13px; font-weight: bold; color: #111827; }
        .head .addr { font-size: 8.5px; color: #6b7280; margin-top: 2px; }
        .head .meta { text-align: right; font-size: 9px; color: #6b7280; }
        .head .meta strong { display: block; font-size: 12px; color: #4f46e5; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 3px; }

        /* Title block */
        .code { font-size: 9px; font-weight: bold; color: #6b7280; letter-spacing: .5px; }
        h1.title { font-size: 15px; margin: 3px 0 6px 0; color: #111827; line-height: 1.35; }

        .chip {
            display: inline-block; padding: 2px 7px; margin-right: 4px;
            border-radius: 8px; font-size: 8px; font-weight: bold;
            text-transform: uppercase; letter-spacing: .3px;
        }
        .chip-blue  { background: #e0e7ff; color: #3730a3; }
        .chip-red   { background: #fee2e2; color: #991b1b; }
        .chip-amber { background: #fef3c7; color: #92400e; }
        .chip-green { background: #d1fae5; color: #065f46; }
        .chip-gray  { background: #f3f4f6; color: #4b5563; }

        /* Section heading */
        h2.section {
            font-size: 10px; text-transform: uppercase; letter-spacing: .6px;
            color: #4f46e5; margin: 14px 0 6px 0; padding-bottom: 3px;
            border-bottom: 1px solid #e5e7eb;
        }

        /* Property grid */
        .props td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; font-size: 9.5px; }
        .props td.k { width: 15%; color: #6b7280; }
        .props td.v { width: 35%; color: #111827; }

        /* Progress bar */
        .bar { width: 90px; height: 6px; background: #e5e7eb; border-radius: 3px; }
        .bar div { height: 6px; background: #4f46e5; border-radius: 3px; }

        /* Data tables */
        .grid th {
            background: #f8fafc; border: 1px solid #e2e8f0; padding: 5px 6px;
            font-size: 8.5px; text-transform: uppercase; letter-spacing: .3px;
            color: #475569; text-align: left;
        }
        .grid td { border: 1px solid #e2e8f0; padding: 5px 6px; font-size: 9px; }
        .grid tr.total td { background: #f8fafc; font-weight: bold; }
        .num { text-align: right; }
        .muted { color: #9ca3af; }
        .empty { padding: 8px 2px; font-size: 9px; color: #9ca3af; font-style: italic; }

        /* Description */
        .desc { font-size: 9.5px; line-height: 1.5; }
        .desc p { margin: 0 0 6px 0; }
        .desc ul, .desc ol { margin: 0 0 6px 16px; padding: 0; }
        .desc img { max-width: 100%; }
        .desc table { margin-bottom: 6px; }
        .desc td, .desc th { border: 1px solid #e2e8f0; padding: 4px; }

        /* Activity */
        .act td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; font-size: 9px; }
        .act td.when { width: 22%; color: #6b7280; }
        .act td.who  { width: 20%; color: #111827; }

        /* Footer. Page numbers come from CSS counters rather than dompdf's
           inline-PHP callback, because enable_php is off by default and the
           callback would silently print nothing. */
        .pdf-foot {
            position: fixed; left: 0; right: 0; bottom: -26px;
            font-size: 7.5px; color: #9ca3af;
            border-top: 1px solid #e5e7eb; padding-top: 4px;
        }
        .pdf-foot .r { text-align: right; }
        .pageno:after { content: counter(page); }
        .pagesof:after { content: counter(pages); }
    </style>
</head>
<body>

    <div class="pdf-foot">
        <table>
            <tr>
                <td>TASK-{{ $task->id }} — {{ $company->name ?? config('app.name') }}</td>
                <td class="r">Page <span class="pageno"></span> of <span class="pagesof"></span></td>
            </tr>
        </table>
    </div>

    {{-- Header --}}
    <table class="head">
        <tr>
            @if($logoPath)
                <td class="logo"><img src="{{ $logoPath }}" alt=""></td>
            @endif
            <td>
                <div class="org">{{ $company->name ?? config('app.name') }}</div>
                @if($company?->address)
                    <div class="addr">{{ $company->address }}</div>
                @endif
            </td>
            <td class="meta">
                <strong>Task Report</strong>
                Generated {{ now()->format('d M Y, h:i A') }}<br>
                by {{ $generatedBy }}
            </td>
        </tr>
    </table>

    {{-- Title --}}
    <div class="code">@if($task->parent)TASK-{{ $task->parent->id }} / @endif TASK-{{ $task->id }}</div>
    <h1 class="title">{{ $task->title }}</h1>
    <div>
        <span class="chip chip-blue">{{ $statusName }}</span>
        <span class="chip {{ $prioClass }}">{{ $task->priority ? ucfirst($task->priority) . ' priority' : 'No priority' }}</span>
        @if($task->completed_at)
            <span class="chip chip-green">Completed</span>
        @elseif($isOverdue)
            <span class="chip chip-red">Overdue</span>
        @endif
    </div>

    {{-- Properties --}}
    <h2 class="section">Details</h2>
    <table class="props">
        <tr>
            <td class="k">Project</td>
            <td class="v">{{ $task->board?->project?->project_name ?? '—' }}</td>
            <td class="k">Board</td>
            <td class="v">{{ $task->board?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Status</td>
            <td class="v">{{ $statusName }}</td>
            <td class="k">Priority</td>
            <td class="v">{{ $task->priority ? ucfirst($task->priority) : '—' }}</td>
        </tr>
        <tr>
            <td class="k">Assigned to</td>
            <td class="v">{{ $task->users->pluck('name')->implode(', ') ?: '—' }}</td>
            <td class="k">Created by</td>
            <td class="v">{{ $task->creator?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Start date</td>
            <td class="v">{{ $fmt($task->start_date) }}</td>
            <td class="k">Due date</td>
            <td class="v">{{ $fmt($task->due_date) }}</td>
        </tr>
        <tr>
            <td class="k">Created on</td>
            <td class="v">{{ $fmt($task->created_at) }}</td>
            <td class="k">Completed on</td>
            <td class="v">{{ $fmt($task->completed_at) }}</td>
        </tr>
        <tr>
            <td class="k">Parent item</td>
            <td class="v">{{ $task->parent ? 'TASK-' . $task->parent->id . ' — ' . $task->parent->title : '—' }}</td>
            <td class="k">Labels</td>
            <td class="v">{{ $task->labels->pluck('name')->implode(', ') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">Progress</td>
            <td class="v">
                <table>
                    <tr>
                        <td style="width: 92px; padding: 0;">
                            <div class="bar"><div style="width: {{ $percent }}%"></div></div>
                        </td>
                        <td style="padding: 0 0 0 6px;">
                            {{ $percent }}%
                            @if($totalCount)<span class="muted">({{ $doneCount }}/{{ $totalCount }} sub-work items)</span>@endif
                        </td>
                    </tr>
                </table>
            </td>
            <td class="k">Time tracked</td>
            <td class="v">
                @if($totalCount)
                    {{ \App\Models\Task::humanDuration($trackedMs) }}
                    <span class="muted">across {{ $totalCount }} sub-work item{{ $totalCount > 1 ? 's' : '' }}</span>
                @elseif($task->timer_first_started_at)
                    {{ \App\Models\Task::humanDuration($ownMs) }}
                @else
                    —
                @endif
            </td>
        </tr>
        @if($task->parent && $task->timer_first_started_at)
            <tr>
                <td class="k">Time on this item</td>
                <td class="v">{{ \App\Models\Task::humanDuration($ownMs) }}</td>
                <td class="k">First started</td>
                <td class="v">{{ \App\Models\Task::stampLabel($task->timer_first_started_at) ?? '—' }}</td>
            </tr>
        @endif
    </table>

    {{-- Description --}}
    <h2 class="section">Description</h2>
    @if($description !== '')
        <div class="desc">{!! $description !!}</div>
    @else
        <div class="empty">No description.</div>
    @endif

    {{-- Sub-work items --}}
    <h2 class="section">Sub-work items ({{ $doneCount }}/{{ $totalCount }})</h2>
    @if($totalCount)
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 9%">Item</th>
                    <th style="width: 29%">Title</th>
                    <th style="width: 17%">Assigned to</th>
                    <th style="width: 11%">Status</th>
                    <th style="width: 12%">Started</th>
                    <th style="width: 12%">Finished</th>
                    <th style="width: 10%" class="num">Time spent</th>
                </tr>
            </thead>
            <tbody>
                @foreach($subtasks as $sub)
                    <tr>
                        <td>TASK-{{ $sub->id }}</td>
                        <td>{{ $sub->title }}</td>
                        <td>{{ $sub->users->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td>{{ $sub->completed_at ? 'Done' : ($sub->isTimerRunning() ? 'Running' : ($sub->column?->name ?? '—')) }}</td>
                        <td>{{ \App\Models\Task::stampLabel($sub->timer_first_started_at) ?? '—' }}</td>
                        <td>{{ \App\Models\Task::stampLabel($sub->completed_at) ?? '—' }}</td>
                        <td class="num">{{ $sub->elapsedMs() > 0 || $sub->timer_first_started_at ? \App\Models\Task::humanDuration($sub->elapsedMs()) : 'not started' }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="6">Total time tracked</td>
                    <td class="num">{{ \App\Models\Task::humanDuration($trackedMs) }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <div class="empty">No sub-work items.</div>
    @endif

    {{-- Links --}}
    <h2 class="section">Links ({{ $task->links->count() }})</h2>
    @if($task->links->count())
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 30%">Title</th>
                    <th style="width: 43%">URL</th>
                    <th style="width: 15%">Added by</th>
                    <th style="width: 12%">Added on</th>
                </tr>
            </thead>
            <tbody>
                @foreach($task->links as $link)
                    <tr>
                        <td>{{ $link->display_title ?: '—' }}</td>
                        <td>{{ $link->url }}</td>
                        <td>{{ $link->creator?->name ?? '—' }}</td>
                        <td>{{ $link->created_at?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No links.</div>
    @endif

    {{-- Attachments --}}
    <h2 class="section">Attachments ({{ $task->attachments->count() }})</h2>
    @if($task->attachments->count())
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 45%">File</th>
                    <th style="width: 15%">Type</th>
                    <th style="width: 12%" class="num">Size</th>
                    <th style="width: 16%">Uploaded by</th>
                    <th style="width: 12%">Uploaded on</th>
                </tr>
            </thead>
            <tbody>
                @foreach($task->attachments as $att)
                    <tr>
                        <td>{{ $att->file_name }}</td>
                        <td>{{ $att->file_type ?: '—' }}</td>
                        <td class="num">{{ $att->file_size ? number_format($att->file_size / 1024, 1) . ' KB' : '—' }}</td>
                        <td>{{ $att->uploader?->name ?? '—' }}</td>
                        <td>{{ $att->created_at?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No attachments.</div>
    @endif

    {{-- Activity --}}
    <h2 class="section">Activity &amp; comments ({{ $activities->count() }})</h2>
    @if($activities->count())
        <table class="act">
            @foreach($activities as $row)
                <tr>
                    <td class="when">{{ $row['at']?->format('d M Y, h:i A') ?? '—' }}</td>
                    <td class="who">{{ $row['user_name'] }}</td>
                    <td>
                        @if($row['type'] === 'comment')<span class="chip chip-gray">Comment</span>@endif
                        {{ \Illuminate\Support\Str::limit(trim(strip_tags($row['text'])), 400) }}
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <div class="empty">No activity recorded.</div>
    @endif

</body>
</html>
