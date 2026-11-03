@extends('layout.app')

@section('meta-information')
    <title>Task Report</title>
@endsection

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .task-report-container .select2-container .select2-selection--single {
            height: 34px;
        }

        .task-report-container .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 32px;
            padding-left: 10px;
            font-size: 12px;
        }

        .task-report-container .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 32px;
            position: absolute;
            top: 1px;
            right: 3px;
            width: 20px;
        }

        /* Scoping all styles to the parent class */
        .task-report-container {
            padding: 25px;
            background-color: #f4f7f9;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        .task-report-container .print-statement-head { display: none; }

        .task-report-container .report-hero {
            display: flex; justify-content: space-between; align-items: center; gap: 16px;
            margin-bottom: 18px; padding: 20px 22px; border-radius: 14px;
            background: linear-gradient(135deg, #1e3a8a, #4f46e5); color: #fff;
            box-shadow: 0 10px 24px rgba(49,46,129,.18);
        }
        .report-hero h1 { margin: 0; font-size: 22px; font-weight: 800; }
        .report-hero p { margin: 4px 0 0; color: #c7d2fe; font-size: 12px; }
        .report-period { flex: none; padding: 7px 12px; border: 1px solid rgba(255,255,255,.25); border-radius: 999px; background: rgba(255,255,255,.12); font-size: 12px; font-weight: 700; }

        /* Filter Section */
        .task-report-container .filter-section {
            background: #ffffff;
            padding: 10px 12px;
            border: 1px solid #dbe4f0;
            border-radius: 9px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 14px;
        }

        .task-report-container .filter-form {
            display: grid;
            grid-template-columns: 135px minmax(175px, 1.25fr) minmax(165px, 1.15fr) minmax(125px, .8fr) 115px 135px 135px auto;
            gap: 8px;
            align-items: end;
        }

        .task-report-container .form-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .task-report-container .form-group label {
            font-size: 10px;
            line-height: 1.1;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .035em;
        }

        .task-report-container .form-control {
            height: 34px;
            padding: 5px 9px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            font-size: 12px;
        }

        .task-report-container .btn-filter {
            background-color: #4f46e5;
            color: white;
            padding: 9px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        .task-report-container .filter-actions { display:flex; gap:8px; align-items:center; }
        .task-report-container .filter-reset,
        .task-report-container .filter-submit {
            height: 34px; min-width: 72px; display:inline-flex !important; align-items:center; justify-content:center;
            gap:5px; border-radius:5px; padding:0 10px !important; line-height:1 !important;
            font-size:11px !important; font-weight:700 !important; text-decoration:none !important; white-space:nowrap;
        }
        .task-report-container .filter-reset { border:1px solid #cbd5e1; background:#fff; color:#475569 !important; }
        .task-report-container .filter-submit { border:1px solid #4f46e5; background:#4f46e5 !important; color:#fff !important; }
        .task-report-container .filter-submit i { color:#fff !important; }

        .task-report-container .report-kpis { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
        .task-report-container .report-kpi { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; box-shadow:0 2px 8px rgba(15,23,42,.04); }
        .report-kpi .kpi-label { display:block; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
        .report-kpi .kpi-value { display:block; margin-top:4px; color:#0f172a; font-size:22px; font-weight:800; }
        .report-kpi.high .kpi-value,.report-kpi.overdue .kpi-value { color:#dc2626; }
        .report-kpi.medium .kpi-value { color:#d97706; }
        .report-kpi.low .kpi-value { color:#059669; }

        .task-report-container .btn-filter:hover {
            background-color: #4338ca;
        }

        /* Summary Cards */
        .task-report-container .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .task-report-container .summary-card {
            padding: 20px;
            border-radius: 10px;
            color: white;
            text-align: center;
        }

        .task-report-container .card-total {
            background: #64748b;
        }

        .task-report-container .card-high {
            background: #ef4444;
        }

        .task-report-container .card-medium {
            background: #f59e0b;
        }

        .task-report-container .card-low {
            background: #10b981;
        }

        .task-report-container .card-overdue {
            background: #dc2626;
        }

        .task-report-container .summary-value {
            font-size: 24px;
            font-weight: 800;
            display: block;
        }

        .task-report-container .summary-label {
            font-size: 12px;
            text-transform: uppercase;
            opacity: 0.9;
        }

        /* User Section */
        .task-report-container .user-section {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .task-report-container .user-header {
            background: linear-gradient(135deg, #3157c8 0%, #6d4bb0 100%);
            color: white;
            padding: 15px 20px;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .task-report-container .no-data { padding:52px 20px; text-align:center; color:#64748b; }
        @media (max-width: 1400px) { .task-report-container .filter-form { grid-template-columns:repeat(4,minmax(150px,1fr)); } }
        @media (max-width: 900px) { .task-report-container .filter-form { grid-template-columns:repeat(2,minmax(140px,1fr)); } .task-report-container .report-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width: 640px) { .task-report-container { padding:12px; } .task-report-container .report-hero { align-items:flex-start; flex-direction:column; } .task-report-container .filter-form { grid-template-columns:1fr; } .task-report-container .filter-actions { justify-content:flex-end; } .task-report-container .report-kpis { grid-template-columns:1fr; } }

        .task-report-container .user-task-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        /* Table Section */
        .task-report-container .report-table-wrapper {
            background: #ffffff;
            overflow: hidden;
        }

        .task-report-container .table {
            width: 100%;
            border-collapse: collapse;
        }

        .task-report-container .table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 15px;
            text-align: left;
            color: #1e293b;
            font-size: 13px;
            font-weight: 600;
        }

        .task-report-container .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        /* Badge Styles */
        .task-report-container .badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }

        .task-report-container .badge-high {
            background: #fee2e2;
            color: #b91c1c;
        }

        .task-report-container .badge-medium {
            background: #fef9c3;
            color: #a16207;
        }

        .task-report-container .badge-low {
            background: #dcfce7;
            color: #15803d;
        }

        .task-report-container .badge-overdue {
            background: #7f1d1d;
            color: #fecaca;
        }

        .task-report-container .status-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .task-report-container .btn-export {
            background-color: #10b981;
            color: white;
            padding: 9px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: background 0.2s;
            font-size: 14px;
            margin-right: 10px;
        }

        .task-report-container .btn-export:hover {
            background-color: #059669;
        }

        .task-report-container .btn-export.btn-pdf {
            background-color: #ef4444;
        }

        .task-report-container .btn-export.btn-pdf:hover {
            background-color: #dc2626;
        }

        .task-report-container .btn-export.btn-print {
            background-color: #3b82f6;
        }

        .task-report-container .btn-export.btn-print:hover {
            background-color: #2563eb;
        }

        .report-table-wrapper {
            border-bottom: 2px solid #e2e8f0
        }

        /* Scoped 2026-08-22. This was a bare `.header`, and `.header` is also the
           class on the app's own top bar (layout/header.blade.php), so every page
           loading this stylesheet had 25px of report padding and a 2px dark rule
           added to the shell header — which is why the header stood taller here
           than on pages that do not load it. It only ever meant the printed
           report's heading. */
        .header.show-print {
            text-align: center;
            margin-bottom: 30px;
            padding-top: 15px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }

        @media print {
            @page { size: A4 landscape; margin: 8mm 9mm; }
            body * {
                visibility: hidden;
            }

            .show-print {
                display: block !important;
            }

            .summary-grid {
                display: none !important;
            }

            .task-report-container,
            .task-report-container * {
                visibility: visible !important;
            }

            .task-report-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
                margin: 0;
            }

            .task-report-container .filter-section,
            .task-report-container .export-actions,
            .task-report-container .report-hero,
            .task-report-container .report-kpis,
            .task-report-container > .header {
                display: none !important;
            }

            .task-report-container .print-statement-head {
                display: block !important;
                visibility: visible !important;
                margin-bottom: 8px;
            }
            .print-statement-head .print-brand { display:flex; justify-content:space-between; align-items:center; margin-bottom:5px; }
            .print-statement-head .print-logo { display:block; width:125px; max-width:125px; height:auto; max-height:42px; object-fit:contain; object-position:left center; }
            .print-statement-head .print-company { color:#3730a3; font-size:18px; font-weight:800; }
            .print-statement-head .print-meta { color:#64748b; font-size:8px; text-align:right; }
            .print-statement-head .print-title { padding:6px; border-top:2px solid #3730a3; background:#f5f7ff !important; color:#3730a3; text-align:center; text-transform:uppercase; letter-spacing:1.3px; font-size:9px; font-weight:800; }
            .print-statement-head .print-period { display:flex; justify-content:space-between; padding:5px 0; color:#475569; font-size:8px; }
            .print-statement-head .print-details { display:grid; grid-template-columns:1fr auto; gap:24px; padding:5px 0 7px; font-size:7px; color:#475569; }
            .print-statement-head .print-details strong { color:#1e293b; }
            .print-statement-head .print-details-title { margin-bottom:2px; color:#3730a3; text-transform:uppercase; letter-spacing:.4px; font-size:6px; font-weight:800; }

            /* Reduce table spacing for print */
            .task-report-container .table th,
            .task-report-container .table td {
                padding: 1.5px 3px !important;
                font-size: 6.3px !important;
                line-height: 1.05 !important;
            }

            .task-report-container .table th { padding-top: 2.5px !important; padding-bottom: 2.5px !important; }
            .task-report-container .table th:nth-child(1) { width: 2% !important; }
            .task-report-container .table th:nth-child(2) { width: 57% !important; }
            .task-report-container .table th:nth-child(3) { width: 13% !important; }
            .task-report-container .table th:nth-child(4) { width: 6% !important; }
            .task-report-container .table th:nth-child(5) { width: 6% !important; }
            .task-report-container .table th:nth-child(6),
            .task-report-container .table th:nth-child(7) { width: 8% !important; }
            .task-report-container .user-section { margin-bottom: 6px !important; border-radius: 0 !important; box-shadow: none !important; page-break-inside: auto; }
            .task-report-container .report-table-wrapper { overflow: visible !important; }
            .task-report-container .badge,
            .task-report-container .status-badge { padding: 1px 3px !important; font-size: 5.8px !important; line-height: 1 !important; }

            .task-report-container .user-header {
                padding: 5px 7px !important;
                font-size: 8px !important;
                background: #eef2ff !important;
                color: #3730a3 !important;
            }

            .task-report-container .badge,
            .task-report-container .status-badge {
                padding: 2px 6px !important;
                font-size: 10px !important;
            }
        }
    </style>
@endsection

@section('main-content')
    <div class="task-report-container">
        @php
            $firstPrintGroup = $tasksByUser->first();
            $printUser = $firstPrintGroup['user'] ?? Auth::user();
            $printCompany = $printUser?->company ?? Auth::user()->company;
            $singleEmployeeReport = $tasksByUser->count() === 1;
            $printEmployeeId = $printUser?->employee_id_no ?: ($printUser ? 'EMP-' . str_pad((string) $printUser->id, 4, '0', STR_PAD_LEFT) : 'N/A');
        @endphp
        <div class="print-statement-head">
            <div class="print-brand">
                <div>
                    @if($printCompany?->logo)
                        <img class="print-logo" src="{{ asset($printCompany->logo) }}" alt="{{ $printCompany->name }}">
                    @else
                        <div class="print-company">{{ $printCompany?->name ?? config('app.name') }}</div>
                    @endif
                </div>
                <div class="print-meta">
                    <strong>{{ $printCompany?->name ?? config('app.name') }}</strong><br>
                    @if($printCompany?->address){{ $printCompany->address }}<br>@endif
                    {{ $printCompany?->phone }}{{ $printCompany?->phone && $printCompany?->email ? ' | ' : '' }}{{ $printCompany?->email }}
                </div>
            </div>
            <div class="print-title">Employee Task Statement</div>
            <div class="print-details">
                <div>
                    <div class="print-details-title">Employee Details</div>
                    <strong>{{ $singleEmployeeReport ? $printUser?->name : 'All Employees' }}</strong><br>
                    @if($singleEmployeeReport){{ $printUser?->email }}@endif
                </div>
                <div>
                    @if($singleEmployeeReport)<strong>Employee ID:</strong> {{ $printEmployeeId }}<br>@endif
                    <strong>Period:</strong> {{ \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth)->format('F Y') }}<br>
                    <strong>Generated:</strong> {{ now()->format('d M Y') }}<br>
                    <strong>Printed by:</strong> {{ Auth::user()->name }}
                </div>
            </div>
        </div>
        <div class="report-hero">
            <div>
                <h1><i class="fa fa-list-check"></i> Employee Task Report</h1>
                <p>Review employee workload, progress and deadlines for a selected month.</p>
            </div>
            <div class="report-period">
                <i class="fa-regular fa-calendar"></i>
                {{ \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth)->format('F Y') }}
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form id="taskReportFilterForm" class="filter-form" method="get">
                <div class="form-group">
                    <label>Report Month</label>
                    <input type="month" name="month" class="form-control" value="{{ $selectedMonth }}">
                </div>

                <div class="form-group">
                    <label>User / Employee</label>
                    <select id="taskReportUserFilter" name="user_id" class="form-control select2" style="width: 100%">
                        @if($isAdminUser)
                            <option value="">All Users</option>
                        @endif
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ (int) ($selectedUserId ?? request('user_id')) === (int) $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                    @if(!$isAdminUser)
                        <input type="hidden" name="user_id" value="{{ $selectedUserId }}">
                    @endif
                </div>

                <div class="form-group">
                    <label>Project</label>
                    <select name="project_id" class="form-control select2" style="width: 100%">
                        <option value="">All Projects</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                {{ $project->project_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="column_id" class="form-control select2" style="width: 100%">
                        <option value="">All Status</option>
                        @foreach ($columns as $column)
                            <option value="{{ $column->id }}" {{ request('column_id') == $column->id ? 'selected' : '' }}>
                                {{ $column->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" class="form-control select2" style="width: 100%">
                        <option value="">All Priorities</option>
                        <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
                        <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                </div>

                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                </div>

                <div class="filter-actions">
                    <a href="{{ route('role.report.task.reports', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="filter-reset">
                        <i class="fa fa-rotate-left"></i> Reset
                    </a>
                    <button type="submit" class="filter-submit">
                        <i class="fa fa-filter"></i> Apply
                    </button>
                </div>
            </form>
        </div>
        
        <div class="header show-print" style="display: none; visibility: hidden;">
            <h1>Task Report</h1>
            <p>Generated on {{ now()->format('F d, Y - h:i A') }}</p>
        </div>

        <!-- <div class="user-info show-print" style="display: none; visibility: hidden;">
            <strong>Employee:</strong> test<br>
            <strong>Email:</strong> test@gmail.com<br>
            <strong>Phone:</strong> 01785451241
        </div> -->
        
        <div class="report-kpis">
            <div class="report-kpi">
                <span class="kpi-label">Total Tasks</span>
                <span class="kpi-value">{{ number_format($summary['total_tasks']) }}</span>
            </div>
            <div class="report-kpi high">
                <span class="kpi-label">High Priority</span>
                <span class="kpi-value">{{ number_format($summary['by_priority']['high']) }}</span>
            </div>
            <div class="report-kpi medium">
                <span class="kpi-label">Medium Priority</span>
                <span class="kpi-value">{{ number_format($summary['by_priority']['medium']) }}</span>
            </div>
            <div class="report-kpi low">
                <span class="kpi-label">Low Priority</span>
                <span class="kpi-value">{{ number_format($summary['by_priority']['low']) }}</span>
            </div>
            <div class="report-kpi overdue">
                <span class="kpi-label">Overdue</span>
                <span class="kpi-value">{{ number_format($summary['overdue']) }}</span>
            </div>
        </div>
        
        
        <!-- Export Actions -->
        <div class="export-actions" style="margin-bottom: 20px;">
            <a href="{{ route('export.task.report.excel', array_merge(request()->all(), ['month' => $selectedMonth])) }}" class="btn-export">
                <i class="fa-solid fa-file-excel" style="margin-right: 5px"></i> Export Excel
            </a>
            <a href="{{ route('export.task.report.pdf', array_merge(request()->all(), ['month' => $selectedMonth])) }}" class="btn-export btn-pdf">
                <i class="fa-solid fa-file-pdf" style="margin-right: 5px"></i> Export PDF
            </a>
            <a href="{{ route('export.task.report.pdf', array_merge(request()->all(), ['month' => $selectedMonth, 'preview' => 1])) }}" target="_blank" class="btn-export btn-print">
                <i class="fa-solid fa-print" style="margin-right: 5px"></i> Print
            </a>
        </div>

        <!-- Task Report by User -->
        @if($tasksByUser->isEmpty())
            <div class="user-section">
                <div class="no-data">
                    <i class="fa fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                    No tasks found for the selected filters.
                </div>
            </div>
        @else
            @foreach ($tasksByUser as $userData)
                <div class="user-section">
                    <div class="user-header">
                        <span>
                            <i class="fa fa-user"></i> {{ $userData['user']->name }}
                        </span>
                        <span class="user-task-count">
                            {{ $userData['tasks']->count() }} {{ Str::plural('Task', $userData['tasks']->count()) }}
                        </span>
                    </div>
                    <div class="report-table-wrapper">
                        <table class="table">
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
                                        $statusName = strtolower(trim($task->column->name ?? ''));
                                        $statusColor = $task->column->color ?? '#64748b';
                                        $statusHex = ltrim($statusColor, '#');
                                        if (strlen($statusHex) === 3) {
                                            $statusHex = implode('', array_map(fn ($char) => $char . $char, str_split($statusHex)));
                                        }
                                        $statusTextColor = '#ffffff';
                                        if (preg_match('/^[0-9a-fA-F]{6}$/', $statusHex)) {
                                            $red = hexdec(substr($statusHex, 0, 2));
                                            $green = hexdec(substr($statusHex, 2, 2));
                                            $blue = hexdec(substr($statusHex, 4, 2));
                                            $brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;
                                            $statusTextColor = $brightness > 155 ? '#3f3f1f' : '#ffffff';
                                        }
                                        if (in_array($statusName, ['progress', 'pending', 'in progress', 'on hold'], true)) {
                                            $statusTextColor = '#3f3f1f';
                                        }
                                        $isDoneStatus = in_array($statusName, ['done', 'completed', 'complete', 'closed'], true);
                                        $isOverdue = $dueDate && !$isDoneStatus && $dueDate->copy()->endOfDay()->lt(now());
                                        $daysRemaining = $dueDate ? now()->diffInDays($dueDate, false) : null;
                                    @endphp
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <strong>{{ $task->title }}</strong>
                                            @if($task->labels->isNotEmpty())
                                                <div style="margin-top: 5px;">
                                                    @foreach($task->labels as $label)
                                                        @php
                                                            $labelName = strtolower(trim($label->name ?? ''));
                                                            $labelColor = $label->color ?? '#64748b';
                                                            $labelHex = ltrim($labelColor, '#');
                                                            $labelTextColor = in_array($labelName, ['editing', 'shooting', 'progress', 'pending', 'in progress'], true)
                                                                ? '#3f3f1f'
                                                                : '#ffffff';
                                                            if (strlen($labelHex) === 3) {
                                                                $labelHex = implode('', array_map(fn ($char) => $char . $char, str_split($labelHex)));
                                                            }
                                                            if (preg_match('/^[0-9a-fA-F]{6}$/', $labelHex)) {
                                                                $labelBrightness = ((hexdec(substr($labelHex, 0, 2)) * 299)
                                                                    + (hexdec(substr($labelHex, 2, 2)) * 587)
                                                                    + (hexdec(substr($labelHex, 4, 2)) * 114)) / 1000;
                                                                $labelTextColor = $labelBrightness > 155 ? '#3f3f1f' : '#ffffff';
                                                            }
                                                        @endphp
                                                        <span class="badge" style="background-color: {{ $labelColor }}; color: {{ $labelTextColor }} !important; margin-right: 5px;">
                                                            {{ $label->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td>{{ $task->board->project->project_name ?? 'N/A' }}</td>
                                        <td>
                                            <span class="status-badge" style="background-color: {{ $statusColor }}; color: {{ $statusTextColor }} !important;">
                                                {{ $task->column->name }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($task->priority)
                                                <span class="badge badge-{{ $task->priority }}">
                                                    {{ ucfirst($task->priority) }}
                                                </span>
                                            @else
                                                <span class="badge" style="background: #e5e7eb; color: #6b7280;">N/A</span>
                                            @endif
                                        </td>
                                        <td>{{ $startDate ? $startDate->format('M d, Y : h:i A') : '-' }}</td>
                                        <td>
                                            {{ $dueDate ? $dueDate->format('M d, Y : h:i A') : '-' }}
                                            @if($isOverdue)
                                                <span class="badge badge-overdue" style="display: block; margin-top: 3px;">OVERDUE</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif

        <!-- Summary by Status -->
        @if($summary['by_status']->isNotEmpty())
            <div class="status-summary-section" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05); margin-top: 20px;">
                <h4 style="margin-bottom: 10px; font-size: 18px; font-weight: bolder">Tasks by Status</h4>
                <ul class="summary-list" style="list-style: none; padding: 15px; margin: 10px 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; flex-wrap: wrap; gap: 20px;">
                    @foreach($summary['by_status'] as $status => $count)
                        <li style="font-size: 14px; color: #334155;">{{ $status }}: <span style="font-weight: 600;">{{ $count }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection

@section('raw-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2();

            $('#taskReportUserFilter').on('change.taskReportAutoLoad', function() {
                const form = document.getElementById('taskReportFilterForm');
                const applyButton = form?.querySelector('.filter-submit');

                if (applyButton) {
                    applyButton.disabled = true;
                    applyButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Loading';
                }

                if (form) {
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            });
        });

        function viewTaskDetails(taskId) {
            $('#taskDetailsModal').modal('show');
            $('#taskDetailsContent').html(`
                <div class="text-center">
                    <i class="fa fa-spinner fa-spin fa-3x"></i>
                    <p>Loading task details...</p>
                </div>
            `);
        }
    </script>
@endsection
