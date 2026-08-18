@extends('layout.app')

@section('meta-information')
    <title>Project Overview</title>
@endsection

@section('css')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');

        :root {
            --overview-bg-start: #f2f7fb;
            --overview-bg-end: #edf4ef;
            --overview-primary: #0f766e;
            --overview-primary-dark: #115e59;
            --overview-soft: #ccfbf1;
            --overview-accent: #f59e0b;
            --overview-danger: #dc2626;
            --overview-card: #ffffff;
            --overview-text: #0f172a;
            --overview-muted: #64748b;
            --overview-border: #dbe7ef;
        }

        .project-overview-wrap {
            font-family: 'Manrope', sans-serif;
            background: radial-gradient(circle at 10% 10%, rgba(15, 118, 110, 0.1), transparent 35%),
                        radial-gradient(circle at 85% 20%, rgba(245, 158, 11, 0.1), transparent 30%),
                        linear-gradient(140deg, var(--overview-bg-start), var(--overview-bg-end));
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 14px 30px rgba(2, 6, 23, 0.06);
        }

        .overview-hero {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            background: linear-gradient(120deg, #134e4a, #0f766e);
            color: #fff;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 18px;
        }

        .overview-hero h1 {
            font-weight: 800;
            font-size: 1.55rem;
            margin-bottom: 8px;
        }

        .overview-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }

        .meta-pill {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .kpi-card {
            background: var(--overview-card);
            border: 1px solid var(--overview-border);
            border-radius: 14px;
            padding: 16px;
        }

        .kpi-label {
            color: var(--overview-muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 6px;
        }

        .kpi-value {
            color: var(--overview-text);
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
        }

        .overview-card {
            background: var(--overview-card);
            border: 1px solid var(--overview-border);
            border-radius: 14px;
            padding: 18px;
        }

        .overview-card h3 {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--overview-text);
            margin-bottom: 12px;
        }

        .progress-track {
            height: 12px;
            background: #d1fae5;
            border-radius: 999px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #059669, #10b981);
            border-radius: 999px;
            transition: width .35s ease;
        }

        .team-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .team-chip {
            background: var(--overview-soft);
            color: var(--overview-primary-dark);
            border: 1px solid rgba(15, 118, 110, 0.2);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 700;
        }

        .task-table-wrap {
            overflow-x: auto;
        }

        .task-table {
            width: 100%;
            border-collapse: collapse;
        }

        .board-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .board-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            background: #f8fafc;
        }

        .board-card-title {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .board-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 12px;
            color: #475569;
        }

        .board-progress-track {
            height: 8px;
            background: #dbeafe;
            border-radius: 999px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .board-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0284c7, #0ea5e9);
            border-radius: 999px;
        }

        .board-filters {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 14px;
        }

        .board-filters-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto auto;
            gap: 8px;
            align-items: center;
        }

        .board-filter-control {
            width: 100%;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 7px 10px;
            font-size: 13px;
            background: #fff;
        }

        .board-health {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }

        .health-chip {
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 700;
        }

        .health-overdue {
            background: #fee2e2;
            color: #b91c1c;
        }

        .health-blocked {
            background: #ede9fe;
            color: #5b21b6;
        }

        .health-unassigned {
            background: #fef3c7;
            color: #92400e;
        }

        .task-table th,
        .task-table td {
            border-bottom: 1px solid #e6edf3;
            text-align: left;
            padding: 10px 8px;
            font-size: 13px;
            color: var(--overview-text);
        }

        .task-table th {
            font-size: 12px;
            color: var(--overview-muted);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .badge-status {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-done {
            background: #dcfce7;
            color: #166534;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .muted {
            color: var(--overview-muted);
        }

        @media (max-width: 992px) {
            .kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .overview-grid {
                grid-template-columns: 1fr;
            }

            .board-grid {
                grid-template-columns: 1fr;
            }

            .board-filters-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .project-overview-wrap {
                padding: 14px;
            }

            .kpi-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@section('main-content')
    @php
        $roleSlug = \Illuminate\Support\Str::slug(auth()->user()->getRoleNames()->first());
        $teamMembers = $project->teamMembers();
    @endphp

    <div class="project-overview-wrap">
        <div class="overview-hero">
            <div>
                <h1>{{ $project->project_name }}</h1>
                <p class="mb-0" style="opacity: .9; max-width: 760px;">
                    {{ $project->description ?: 'No project description added yet.' }}
                </p>
                <div class="overview-meta">
                    <span class="meta-pill">Category: {{ $project->projectCategory->name ?? 'N/A' }}</span>
                    <span class="meta-pill">Department: {{ $project->department->name ?? 'N/A' }}</span>
                    <span class="meta-pill">Customer: {{ $project->customer->name ?? 'N/A' }}</span>
                </div>
            </div>
            <div class="d-flex align-items-start">
                <a href="{{ route('role.projects.index', ['role' => $roleSlug]) }}" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Projects
                </a>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Task Progress</div>
                <div class="kpi-value">{{ $progressPercentage }}%</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Tasks</div>
                <div class="kpi-value">{{ $project->total_tasks }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Completed</div>
                <div class="kpi-value">{{ $project->completed_tasks }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Overdue</div>
                <div class="kpi-value {{ $project->overdue_tasks > 0 ? 'text-red-600' : '' }}">
                    {{ $project->overdue_tasks }}
                </div>
            </div>
        </div>

        <div class="overview-grid">
            <div class="overview-card">
                <h3>Boards In This Project</h3>
                <div class="board-filters">
                    <form method="GET" class="board-filters-form">
                        <select name="board_status" class="board-filter-control">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ ($boardFilters['board_status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="in_progress" {{ ($boardFilters['board_status'] ?? '') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="completed" {{ ($boardFilters['board_status'] ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                        </select>

                        <select name="member_id" class="board-filter-control">
                            <option value="">All Members</option>
                            @foreach($boardMembers as $member)
                                <option value="{{ $member->id }}" {{ (string) ($boardFilters['member_id'] ?? '') === (string) $member->id ? 'selected' : '' }}>
                                    {{ $member->name }}
                                </option>
                            @endforeach
                        </select>

                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="overdue_only" value="1" {{ !empty($boardFilters['overdue_only']) ? 'checked' : '' }}>
                            Overdue Only
                        </label>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <a href="{{ route('role.projects.show', ['role' => $roleSlug, 'project' => $project->id]) }}" class="btn btn-sm btn-light">Reset</a>
                        </div>
                    </form>
                </div>
                <div class="board-grid mb-3">
                    @forelse($boards as $board)
                        @php
                            $boardProgress = $board->total_tasks > 0 ? (int) round(($board->completed_tasks / $board->total_tasks) * 100) : 0;
                        @endphp
                        <div class="board-card">
                            <div class="board-card-title">{{ $board->name }}</div>
                            <div class="board-meta">
                                <span>{{ ucfirst(str_replace('_', ' ', $board->status ?? 'pending')) }}</span>
                                <span>{{ $board->completed_tasks }}/{{ $board->total_tasks }} tasks</span>
                            </div>
                            <div class="board-health">
                                <span class="health-chip health-overdue">Overdue: {{ $board->overdue_tasks }}</span>
                                <span class="health-chip health-blocked">Blocked: {{ $board->blocked_tasks }}</span>
                                <span class="health-chip health-unassigned">Unassigned: {{ $board->unassigned_tasks }}</span>
                            </div>
                            <div class="board-progress-track">
                                <div class="board-progress-fill board-progress-fill-js" data-progress="{{ $boardProgress }}"></div>
                            </div>
                            <a href="{{ route('role.tasks.board', ['role' => $roleSlug, 'board' => $board->id]) }}" class="btn btn-sm btn-outline-primary">
                                Board View
                            </a>
                        </div>
                    @empty
                        <div class="muted">No boards found for this project with the selected filters.</div>
                    @endforelse
                </div>
                <div class="mb-4">
                    {{ $boards->appends(request()->except('board_page'))->links() }}
                </div>

                <h3>Recent Task Activity</h3>
                <div class="task-table-wrap">
                    <table class="task-table">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Stage</th>
                                <th>Assignees</th>
                                <th>Due Date</th>
                                <th>State</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTasks as $task)
                                <tr>
                                    <td>{{ $task->title }}</td>
                                    <td>{{ $task->column->name ?? 'N/A' }}</td>
                                    <td>{{ $task->users->pluck('name')->join(', ') ?: 'Unassigned' }}</td>
                                    <td>{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('M d, Y') : 'N/A' }}</td>
                                    <td>
                                        @if($task->completed_at)
                                            <span class="badge-status badge-done">Done</span>
                                        @else
                                            <span class="badge-status badge-pending">Open</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="muted">No tasks found for this project yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $recentTasks->appends(request()->except('task_page'))->links() }}
                </div>
            </div>

            <div class="overview-card">
                <h3>Project Snapshot</h3>
                <div class="progress-track">
                    <div class="progress-fill project-overview-progress" data-progress="{{ $progressPercentage }}"></div>
                </div>
                <p class="muted mb-3">{{ $project->completed_tasks }} of {{ $project->total_tasks }} tasks completed</p>

                <div class="mb-3">
                    <div class="kpi-label">Start Date</div>
                    <div>{{ $project->start_date ? $project->start_date->format('M d, Y') : 'N/A' }}</div>
                </div>
                <div class="mb-3">
                    <div class="kpi-label">End Date</div>
                    <div>{{ $project->end_date ? $project->end_date->format('M d, Y') : 'N/A' }}</div>
                </div>
                <div class="mb-3">
                    <div class="kpi-label">Budget</div>
                    <div>{{ number_format((float) $project->budget, 2) }}</div>
                </div>
                <div class="mb-3">
                    <div class="kpi-label">Modules / Boards</div>
                    <div>{{ $project->total_modules }}</div>
                </div>
                <div>
                    <div class="kpi-label">Team Members</div>
                    <div class="team-list">
                        @forelse($teamMembers as $member)
                            <span class="team-chip">{{ $member->name }}</span>
                        @empty
                            <span class="muted">No team members assigned.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const progressBar = document.querySelector('.project-overview-progress');
            const boardProgressBars = document.querySelectorAll('.board-progress-fill-js');

            if (progressBar) {
                const progress = parseInt(progressBar.getAttribute('data-progress') || '0', 10);
                progressBar.style.width = `${Math.min(Math.max(progress, 0), 100)}%`;
            }

            boardProgressBars.forEach((bar) => {
                const progress = parseInt(bar.getAttribute('data-progress') || '0', 10);
                bar.style.width = `${Math.min(Math.max(progress, 0), 100)}%`;
            });
        });
    </script>
@endsection