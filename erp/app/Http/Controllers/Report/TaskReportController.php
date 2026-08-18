<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use App\Models\Column;
use App\Models\Board;
use App\Exports\TaskReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class TaskReportController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view task report', only: ['taskReport','exportExcel','exportPdf','getTaskDetails',]),
        ];
    }

    public function taskReport(Request $request)
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $roleSlug = Str::slug($authUser->getRoleNames()->first());
        $isAdminUser = in_array($roleSlug, ['super admin', 'super-admin', 'admin']);
        $selectedUserId = $isAdminUser ? $request->user_id : $authUser->id;

        $query = Task::with([
            'users',
            'column',
            'board',
            'board.project',
            'creator',
            'labels'
        ]);


        // Apply filters
        $query->whereHas('users', function ($q) {
            $q->role('employee');
        });

        if (!empty($selectedUserId)) {
            $query->whereHas('users', function($q) use ($selectedUserId) {
                $q->where('users.id', $selectedUserId);
            });
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('column_id')) {
            $query->where('column_id', $request->column_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', Carbon::parse($request->start_date)->startOfDay());
        }

        if ($request->filled('end_date')) {
            $query->where('due_date', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        // Get tasks
        $tasks = $query->orderBy('created_at', 'desc')->get();

        // Group tasks by user
        $tasksByUser = collect();
        foreach ($tasks as $task) {
            foreach ($task->users as $user) {
                if (!$user->hasRole('employee')) {
                    continue;
                }

                // If filtering by user, only show that specific user's section
                if (!empty($selectedUserId) && (int) $user->id !== (int) $selectedUserId) {
                    continue;
                }
                
                if (!$tasksByUser->has($user->id)) {
                    $tasksByUser->put($user->id, [
                        'user' => $user,
                        'tasks' => collect()
                    ]);
                }
                
                // Get the user data, add task, and update the collection
                $userData = $tasksByUser->get($user->id);
                $userData['tasks']->push($task);
                $tasksByUser->put($user->id, $userData);
            }
        }

        // Calculate summary statistics
        $now = Carbon::now();
        $summary = [
            'total_tasks' => $tasks->count(),
            'by_priority' => [
                'high' => $tasks->where('priority', 'high')->count(),
                'medium' => $tasks->where('priority', 'medium')->count(),
                'low' => $tasks->where('priority', 'low')->count(),
            ],
            'by_status' => $tasks->groupBy('column.name')->map->count(),
            'overdue' => $tasks->filter(function($task) use ($now) {
                if (empty($task->due_date)) {
                    return false;
                }

                $statusName = strtolower(trim($task->column->name ?? ''));
                if (in_array($statusName, ['done', 'completed', 'complete', 'closed'], true)) {
                    return false;
                }

                return Carbon::parse($task->due_date)->endOfDay()->lt($now);
            })->count(),
        ];

        $users = User::select('id', 'name')
            ->role('employee')
            ->where('status', 'active')
            ->when(!$isAdminUser, function ($query) use ($authUser) {
                $query->where('id', $authUser->id);
            })
            ->orderBy('name')
            ->get();
        $projects = Project::select('id', 'project_name')->orderBy('project_name')->get();
        $columns = Column::select('id', 'name', 'color')->orderBy('position')->get();

        return view('report.task_reports', compact(
            'tasks', 
            'tasksByUser', 
            'users', 
            'projects', 
            'columns', 
            'summary',
            'isAdminUser',
            'selectedUserId',
        ));
    }

    public function exportExcel(Request $request)
    {
        // Get the same filtered data
        $data = $this->getFilteredTaskData($request);
        
        $fileName = 'Task_Report_' . now()->format('Y-m-d_His') . '.xlsx';
        
        return Excel::download(
            new TaskReportExport($data['tasksByUser'], $data['summary']), 
            $fileName
        );
    }

    public function exportPdf(Request $request)
    {
        // Get the same filtered data
        $data = $this->getFilteredTaskData($request);
        
        $pdf = Pdf::loadView('report.task_reports_pdf', [
            'tasksByUser' => $data['tasksByUser'],
            'summary' => $data['summary'],
            'filters' => [
                'user_id' => $data['selected_user_id'],
                'project_id' => $request->project_id,
                'column_id' => $request->column_id,
                'priority' => $request->priority,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]
        ])->setPaper('a4', 'landscape');
        
        $fileName = 'Task_Report_' . now()->format('Y-m-d_His') . '.pdf';
        
        return $pdf->download($fileName);
    }

    private function getFilteredTaskData(Request $request)
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $roleSlug = Str::slug($authUser->getRoleNames()->first());
        $isAdminUser = in_array($roleSlug, ['super admin', 'super-admin', 'admin']);
        $selectedUserId = $isAdminUser ? $request->user_id : $authUser->id;

        $query = Task::with([
            'users',
            'column',
            'board',
            'board.project',
            'creator',
            'labels'
        ]);

        // Apply filters
        $query->whereHas('users', function ($q) {
            $q->role('employee');
        });

        if (!empty($selectedUserId)) {
            $query->whereHas('users', function($q) use ($selectedUserId) {
                $q->where('users.id', $selectedUserId);
            });
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('column_id')) {
            $query->where('column_id', $request->column_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', Carbon::parse($request->start_date)->startOfDay());
        }

        if ($request->filled('end_date')) {
            $query->where('due_date', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        // Get tasks
        $tasks = $query->orderBy('created_at', 'desc')->get();

        // Group tasks by user
        $tasksByUser = collect();
        foreach ($tasks as $task) {
            foreach ($task->users as $user) {
                if (!$user->hasRole('employee')) {
                    continue;
                }

                // If filtering by user, only show that specific user's section
                if (!empty($selectedUserId) && (int) $user->id !== (int) $selectedUserId) {
                    continue;
                }
                
                if (!$tasksByUser->has($user->id)) {
                    $tasksByUser->put($user->id, [
                        'user' => $user,
                        'tasks' => collect()
                    ]);
                }
                
                // Get the user data, add task, and update the collection
                $userData = $tasksByUser->get($user->id);
                $userData['tasks']->push($task);
                $tasksByUser->put($user->id, $userData);
            }
        }

        // Calculate summary statistics
        $now = Carbon::now();
        $summary = [
            'total_tasks' => $tasks->count(),
            'by_priority' => [
                'high' => $tasks->where('priority', 'high')->count(),
                'medium' => $tasks->where('priority', 'medium')->count(),
                'low' => $tasks->where('priority', 'low')->count(),
            ],
            'by_status' => $tasks->groupBy('column.name')->map->count(),
            'overdue' => $tasks->filter(function($task) use ($now) {
                if (empty($task->due_date)) {
                    return false;
                }

                $statusName = strtolower(trim($task->column->name ?? ''));
                if (in_array($statusName, ['done', 'completed', 'complete', 'closed'], true)) {
                    return false;
                }

                return Carbon::parse($task->due_date)->endOfDay()->lt($now);
            })->count(),
        ];

        return [
            'tasks' => $tasks,
            'tasksByUser' => $tasksByUser,
            'summary' => $summary,
            'selected_user_id' => $selectedUserId,
        ];
    }
}
