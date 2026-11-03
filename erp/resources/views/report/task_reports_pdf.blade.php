<!DOCTYPE html>
<html>
<head>
    <title>Task Report</title>
    <style>
        /* @page { size: A4 landscape; margin: 8mm 9mm; } */
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8px;
            color: #1e293b;
            margin: 0;
        }

        .masthead { width: 100%; margin-bottom: 2px; border-collapse: collapse; }
        .masthead td { border: 0; padding: 0; vertical-align: middle; }
        .logo { width:65px; max-width:65px; height:auto; max-height:26px; object-fit:contain; object-position:left center; }
        .brand { color: #3730a3; font-size: 13px; font-weight: 800; }
        .company { text-align: right; color: #64748b; font-size: 6.5px; line-height: 1.2; }
        .company strong { display:block; color:#1e293b; font-size:8px; }
        .title-bar { margin-bottom:3px; padding:3px; border-top:1.5px solid #3730a3; background:#f5f7ff; color:#3730a3; text-align:center; text-transform:uppercase; letter-spacing:1px; font-size:7px; font-weight:bold; }
        .report-meta { width:100%; margin-bottom:3px; border-collapse:collapse; }
        .report-meta td { border:0; padding:0; line-height:1.15; font-size:6.3px; }
        .report-meta .right { text-align:right; color:#64748b; }
        .employee-title { color:#3730a3; text-transform:uppercase; letter-spacing:.4px; font-size:5.8px; font-weight:bold; }
        .employee-name { color:#1e293b; font-size:7.5px; font-weight:bold; }

        .header {
            text-align: center;
            margin-bottom: 8px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 5px;
        }

        .header h1 {
            margin: 0;
            font-size: 12px;
            color: #3730a3;
            text-transform: uppercase;
        }

        .header p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #64748b;
        }

        .summary-grid {
            display: table;
            width: 100%;
            margin-bottom: 8px;
            border-collapse: collapse;
        }

        .summary-row {
            display: table-row;
        }

        .summary-card {
            display: table-cell;
            width: 20%;
            padding: 5px;
            text-align: center;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .summary-value {
            font-size: 10px;
            font-weight: bold;
            display: block;
            margin-bottom: 1px;
        }

        .summary-label {
            font-size: 6px;
            text-transform: uppercase;
            color: #64748b;
        }

        .user-section {
            margin-bottom: 2px;
            page-break-inside: auto;
        }

        .user-header {
            background-color: #eef2ff;
            color: #3730a3;
            padding: 2px 3px;
            font-size: 7px;
            font-weight: 700;
            margin-bottom: 0;
            font-family: 'DejaVu Sans', sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
            table-layout: fixed;
        }

        th {
            background-color: #564fc8;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 6.2px;
            padding: 1.3px 2.5px;
            border: 0;
            text-align: left;
        }

        td {
            padding: .5px 2.5px;
            border: 0;
            border-bottom: .5px solid #e2e8f0;
            font-size: 7px;
            line-height: 1.05;
        }

        .badge {
            padding: 0 2px;
            border-radius: 2px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }

        .badge-high {
            background: #fee2e2;
            color: #b91c1c;
        }

        .badge-medium {
            background: #fef9c3;
            color: #a16207;
        }

        .badge-low {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-overdue {
            background: #7f1d1d;
            color: #fecaca;
        }

        .status-summary {
            margin-top: 3px;
            padding: 3px 5px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 2px;
        }

        .status-summary h3 {
            margin: 0 0 2px 0;
            font-size: 6px;
            color: #1e293b;
        }

        .status-item {
            display: inline-block;
            margin-right: 8px;
            margin-bottom: 1px;
            font-size: 6.3px;
        }

        .status-count {
            font-weight: bold;
            color: #667eea;
            font-size: 7px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
            font-size: 14px;
        }

        tbody tr:nth-child(even) td { background:#f8f9ff; }
        th:nth-child(4), th:nth-child(5), td:nth-child(4), td:nth-child(5) { text-align:center; }
        th:nth-child(6), th:nth-child(7), td:nth-child(6), td:nth-child(7) { white-space:nowrap; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    @php
        $firstGroup = $tasksByUser->first();
        $reportUser = $firstGroup['user'] ?? null;
        $company = $reportUser?->company ?? Auth::user()?->company;
        $logoPath = $company?->logo && file_exists(public_path($company->logo)) ? public_path($company->logo) : null;
        $singleEmployeeReport = $tasksByUser->count() === 1;
        $employeeId = $reportUser?->employee_id_no ?: ($reportUser ? 'EMP-' . str_pad((string) $reportUser->id, 4, '0', STR_PAD_LEFT) : 'N/A');
        $period = !empty($filters['month'])
            ? \Carbon\Carbon::createFromFormat('Y-m', $filters['month'])->format('F Y')
            : (!empty($filters['start_date'])
                ? \Carbon\Carbon::parse($filters['start_date'])->format('F Y')
                : now()->format('F Y'));
    @endphp
    <table class="masthead">
        <tr>
            <td>
                @if($logoPath)<img class="logo" src="{{ $logoPath }}" alt="">@else<div class="brand">{{ $company?->name ?? config('app.name') }}</div>@endif
            </td>
            <td class="company">
                <strong>{{ $company?->name ?? config('app.name') }}</strong>
                @if($company?->address){{ $company->address }}<br>@endif
                {{ $company?->phone }}{{ $company?->phone && $company?->email ? ' | ' : '' }}{{ $company?->email }}
            </td>
        </tr>
    </table>
    <div class="title-bar">Employee Task Statement</div>
    <table class="report-meta">
        <tr>
            <td>
                <div class="employee-title">Employee Details</div>
                <div class="employee-name">{{ $singleEmployeeReport ? $reportUser?->name : 'All Employees' }}</div>
                @if($singleEmployeeReport){{ $reportUser?->email }}@endif
            </td>
            <td class="right">
                @if($singleEmployeeReport)<strong>Employee ID:</strong> {{ $employeeId }}<br>@endif
                <strong>Period:</strong> {{ $period }}<br>
                <strong>Generated:</strong> {{ now()->format('d M Y') }}<br>
                <strong>Printed By:</strong> {{ Auth::user()->name }}
            </td>
        </tr>
    </table>
    <!-- Task Report by User -->
    @if($tasksByUser->isEmpty())
        <div class="no-data">
            No tasks found for the selected filters.
        </div>
    @else
        @foreach ($tasksByUser as $userData)
            <div class="user-section">
                <div class="user-header">
                    @php
                        $userName = $userData['user']->name ?? 'Unknown User';
                        $taskCount = $userData['tasks']->count();
                    @endphp
                    {{ $userName }} - {{ $taskCount }} {{ $taskCount == 1 ? 'Task' : 'Tasks' }}
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 2%">#</th>
                            <th style="width: 57%">Task Title</th>
                            <th style="width: 13%">Project</th>
                            <th style="width: 6%">Status</th>
                            <th style="width: 6%">Priority</th>
                            <th style="width: 8%">Start Date</th>
                            <th style="width: 8%">Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($userData['tasks'] as $index => $task)
                            @php
                                $dueDate = $task->due_date ? \Carbon\Carbon::parse($task->due_date) : null;
                                $startDate = $task->start_date ? \Carbon\Carbon::parse($task->start_date) : null;
                                $overdueAt = isset($reportAsOf) ? \Carbon\Carbon::parse($reportAsOf) : now();
                                $doneStatuses = ['done', 'completed', 'complete', 'closed'];
                                $isDone = in_array(strtolower(trim($task->column->name ?? '')), $doneStatuses, true);
                                $isOverdue = $dueDate && !$isDone && $dueDate->endOfDay()->lt($overdueAt);
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td><strong>{{ $task->title }}</strong></td>
                                <td>{{ $task->board->project->project_name ?? 'N/A' }}</td>
                                <td>{{ $task->column->name }}</td>
                                <td>
                                    @if($task->priority)
                                        <span class="badge badge-{{ $task->priority }}">
                                            {{ ucfirst($task->priority) }}
                                        </span>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td>{{ $startDate ? $startDate->format('M d, Y') : '-' }}</td>
                                <td>
                                    {{ $dueDate ? $dueDate->format('M d, Y') : '-' }}
                                    @if($isOverdue)
                                        <br><span class="badge badge-overdue">OVERDUE</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

    <!-- Summary by Status -->
    @if($summary['by_status']->isNotEmpty())
        <div class="status-summary">
            <h3>Tasks by Status</h3>
            @foreach($summary['by_status'] as $status => $count)
                <div class="status-item">
                    <span class="status-count">{{ $count }}</span> {{ $status }}
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
