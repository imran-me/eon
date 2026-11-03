<?php

namespace App\Services;

use App\Models\EmployeeSalary;
use App\Models\Task;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class EmployeeTaskReportService
{
    private const DONE_COLUMNS = ['done', 'completed', 'complete', 'closed'];
    private const CANCELLED_COLUMNS = ['cancelled', 'canceled'];

    public function render(EmployeeSalary $salary): string
    {
        $monthStart = Carbon::create((int) $salary->year, (int) $salary->month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $tasks = Task::with(['users', 'column', 'board.project', 'creator', 'labels'])
            ->whereHas('users', fn ($query) => $query->where('users.id', $salary->user_id))
            ->where('created_at', '<=', $monthEnd)
            ->where(fn ($query) => $query->whereNull('completed_at')->orWhere('completed_at', '>=', $monthStart))
            ->orderByDesc('created_at')->get()
            ->filter(function (Task $task) use ($monthStart, $monthEnd) {
                if ($task->completed_at) {
                    return $task->completed_at->between($monthStart, $monthEnd) || $task->completed_at->gt($monthEnd);
                }
                $status = strtolower(trim((string) ($task->column->name ?? '')));
                return !in_array($status, self::DONE_COLUMNS, true)
                    && !in_array($status, self::CANCELLED_COLUMNS, true);
            })->values();

        $user = $salary->user()->firstOrFail();
        $tasksByUser = $tasks->isEmpty()
            ? collect()
            : collect([$user->id => ['user' => $user, 'tasks' => $tasks]]);
        $isDone = fn (Task $task) => in_array(strtolower(trim((string) ($task->column->name ?? ''))), self::DONE_COLUMNS, true);
        $summary = [
            'total_tasks' => $tasks->count(),
            'by_priority' => [
                'high' => $tasks->where('priority', 'high')->count(),
                'medium' => $tasks->where('priority', 'medium')->count(),
                'low' => $tasks->where('priority', 'low')->count(),
            ],
            'by_status' => $tasks->groupBy(fn (Task $task) => $task->column->name ?? 'Unassigned')->map->count(),
            'overdue' => $tasks->filter(fn (Task $task) =>
                $task->due_date && !$isDone($task) && $task->due_date->endOfDay()->lt($monthEnd)
            )->count(),
        ];

        return Pdf::loadView('report.task_reports_pdf', [
            'tasksByUser' => $tasksByUser,
            'summary' => $summary,
            'filters' => [
                'user_id' => $user->id,
                'start_date' => $monthStart->toDateString(),
                'end_date' => $monthEnd->toDateString(),
            ],
            'reportTitle' => 'Employee Task Report - ' . $monthStart->format('F Y'),
            'reportAsOf' => $monthEnd,
        ])->setPaper('a4', 'landscape')->output();
    }
}
