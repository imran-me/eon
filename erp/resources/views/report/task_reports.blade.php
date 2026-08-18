@extends('layout.app')

@section('meta-information')
    <title>Task Report</title>
@endsection

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 40px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
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

        /* Filter Section */
        .task-report-container .filter-section {
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .task-report-container .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .task-report-container .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .task-report-container .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }

        .task-report-container .form-control {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

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

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-top: 15px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }

        @media print {
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
            .task-report-container .export-actions{
                display: none !important;
            }

            /* Reduce table spacing for print */
            .task-report-container .table th,
            .task-report-container .table td {
                padding: 6px 8px !important;
                font-size: 12px !important;
            }

            .task-report-container .user-header {
                padding: 10px 15px !important;
                font-size: 14px !important;
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
        <!-- <h2 style="margin-bottom: 20px; font-size: 24px; font-weight: 700; color: #1e293b;">
            <i class="fa fa-tasks"></i> Task Report
        </h2> -->

        <!-- Filter Section -->
        <div class="filter-section">
            <form class="filter-form" method="get">
                <div class="form-group">
                    <label>User / Employee</label>
                    <select name="user_id" class="form-control select2" style="width: 100%">
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

                <div style="display: flex; gap: 10px;">
                    <a href="{{ route('role.report.task.reports', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn btn-sm bg-secondary"
                        style="background: #e8e8e8; width: 100px; height: 40px; line-height: 40px; text-align: center; display: inline-block; border-radius: 4px; cursor: pointer;">
                        Reset
                    </a>
                    <button type="submit" class="btn btn-sm btn-filter"
                        style="width: 100px; height: 40px; line-height: 40px; text-align: center; border: none;">
                        Filter
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
        
        <!-- Summary Cards -->
        <!-- <div class="summary-grid">
            <div class="summary-card card-total">
                <span class="summary-value">{{ $summary['total_tasks'] }}</span>
                <span class="summary-label">Total Tasks</span>
            </div>
            <div class="summary-card card-high">
                <span class="summary-value">{{ $summary['by_priority']['high'] }}</span>
                <span class="summary-label">High Priority</span>
            </div>
            <div class="summary-card card-medium">
                <span class="summary-value">{{ $summary['by_priority']['medium'] }}</span>
                <span class="summary-label">Medium Priority</span>
            </div>
            <div class="summary-card card-low">
                <span class="summary-value">{{ $summary['by_priority']['low'] }}</span>
                <span class="summary-label">Low Priority</span>
            </div>
            <div class="summary-card card-overdue">
                <span class="summary-value">{{ $summary['overdue'] }}</span>
                <span class="summary-label">Overdue Tasks</span>
            </div>
        </div> -->
        
        
        <!-- Export Actions -->
        <div class="export-actions" style="margin-bottom: 20px;">
            <a href="{{ route('export.task.report.excel', request()->all()) }}" class="btn-export">
                <i class="fa-solid fa-file-excel" style="margin-right: 5px"></i> Export Excel
            </a>
            <a href="{{ route('export.task.report.pdf', request()->all()) }}" class="btn-export btn-pdf">
                <i class="fa-solid fa-file-pdf" style="margin-right: 5px"></i> Export PDF
            </a>
            <a href="javascript:void(0)" onclick="window.print()" class="btn-export btn-print">
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
                                    <th style="width: 5%">#</th>
                                    <th style="width: 25%">Task Title</th>
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
                                        $statusName = strtolower(trim($task->column->name ?? ''));
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
                                                        <span class="badge" style="background-color: {{ $label->color }}; opacity: 0.8; color: white; margin-right: 5px;">
                                                            {{ $label->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td>{{ $task->board->project->project_name ?? 'N/A' }}</td>
                                        <td>
                                            <span class="status-badge" style="background-color: {{ $task->column->color }}; opacity: 0.9; color: white;">
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
