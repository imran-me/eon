<!DOCTYPE html>
<html>
<head>
    <title>Task Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #667eea;
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
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .summary-row {
            display: table-row;
        }

        .summary-card {
            display: table-cell;
            width: 20%;
            padding: 12px;
            text-align: center;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .summary-value {
            font-size: 20px;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }

        .summary-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
        }

        .user-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .user-header {
            background-color: #667eea;
            color: #ffffff;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 0;
            font-family: 'DejaVu Sans', sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            padding: 8px 6px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        td {
            padding: 7px 6px;
            border: 1px solid #e2e8f0;
            font-size: 10px;
        }

        .badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 8px;
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
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .status-summary h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #1e293b;
        }

        .status-item {
            display: inline-block;
            margin-right: 20px;
            margin-bottom: 5px;
        }

        .status-count {
            font-weight: bold;
            color: #667eea;
            font-size: 16px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
            font-size: 14px;
        }

        @page {
            margin: 15mm;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Task Report</h1>
        <p>Generated on {{ now()->format('F d, Y - h:i A') }}</p>
    </div>

    <!-- Summary Cards -->
    <!-- <div class="summary-grid">
        <div class="summary-row">
            <div class="summary-card">
                <span class="summary-value">{{ $summary['total_tasks'] }}</span>
                <span class="summary-label">Total Tasks</span>
            </div>
            <div class="summary-card">
                <span class="summary-value">{{ $summary['by_priority']['high'] }}</span>
                <span class="summary-label">High Priority</span>
            </div>
            <div class="summary-card">
                <span class="summary-value">{{ $summary['by_priority']['medium'] }}</span>
                <span class="summary-label">Medium Priority</span>
            </div>
            <div class="summary-card">
                <span class="summary-value">{{ $summary['by_priority']['low'] }}</span>
                <span class="summary-label">Low Priority</span>
            </div>
            <div class="summary-card">
                <span class="summary-value">{{ $summary['overdue'] }}</span>
                <span class="summary-label">Overdue Tasks</span>
            </div>
        </div>
    </div> -->

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
                            <th style="width: 5%">#</th>
                            <th style="width: 28%">Task Title</th>
                            <th style="width: 15%">Project</th>
                            <th style="width: 12%">Status</th>
                            <th style="width: 10%">Priority</th>
                            <th style="width: 10%">Start Date</th>
                            <th style="width: 10%">Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($userData['tasks'] as $index => $task)
                            @php
                                $dueDate = $task->due_date ? \Carbon\Carbon::parse($task->due_date) : null;
                                $startDate = $task->start_date ? \Carbon\Carbon::parse($task->start_date) : null;
                                $isOverdue = $dueDate && $dueDate->isPast();
                                $daysRemaining = $dueDate ? now()->diffInDays($dueDate, false) : null;
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
