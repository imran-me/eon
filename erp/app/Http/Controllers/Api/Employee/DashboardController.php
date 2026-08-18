<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\OfficeTodoAssignee;
use App\Models\SupportTicket;
use App\Models\Task;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();
        $calendarStart = $startOfMonth->copy();
        $calendarEnd = $endOfMonth->copy();

        /*
        |--------------------------------------------------------------------------
        | Attendance records for current month
        |--------------------------------------------------------------------------
        */
        $attendanceRecords = Attendance::query()
            ->with(['shift', 'attendence_setting'])
            ->where('user_id', $userId)
            ->whereBetween('date', [$calendarStart->toDateString(), $today->toDateString()])
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->toDateString();
            });

        $presentDates = $attendanceRecords
            ->filter(function ($record) {
                return !empty($record->check_in)
                    || in_array($record->status, ['present', 'late', 'Present', 'Late']);
            })
            ->keys()
            ->flip();

        $currentMonthPresent = $presentDates->count();

        /*
        |--------------------------------------------------------------------------
        | Leaves and holidays
        |--------------------------------------------------------------------------
        */
        $monthLeaves = Leave::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $calendarEnd->toDateString())
            ->whereDate('end_date', '>=', $calendarStart->toDateString())
            ->get(['id', 'leave_type_id', 'start_date', 'end_date', 'reason', 'status']);

        $monthHolidays = Holiday::query()
            ->whereDate('start_date', '<=', $calendarEnd->toDateString())
            ->whereDate('end_date', '>=', $calendarStart->toDateString())
            ->get(['id', 'name', 'start_date', 'end_date']);

        $weekendDays = collect(optional($user->shift)->holidays ?? [])
            ->map(fn ($day) => strtolower((string) $day))
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Daily attendance for calendar
        |--------------------------------------------------------------------------
        */
        $currentMonthAbsent = 0;
        $calendar = collect();

        foreach (CarbonPeriod::create($calendarStart, $calendarEnd) as $date) {
            $dateStr = $date->toDateString();
            $dayName = strtolower($date->format('l'));

            $record = $attendanceRecords->get($dateStr);

            $holidayRecord = $monthHolidays->first(function ($holiday) use ($dateStr) {
                return $dateStr >= Carbon::parse($holiday->start_date)->toDateString()
                    && $dateStr <= Carbon::parse($holiday->end_date)->toDateString();
            });

            $leaveRecord = $monthLeaves->first(function ($leave) use ($dateStr) {
                return $dateStr >= Carbon::parse($leave->start_date)->toDateString()
                    && $dateStr <= Carbon::parse($leave->end_date)->toDateString();
            });

            $isWeekend = in_array($dayName, $weekendDays, true);

            $status = null;
            $note = null;
            $checkIn = null;
            $checkOut = null;
            $lateMinutes = 0;

            // Future dates
            if ($date->gt($today)) {
                $status = 'Upcoming';
                $note = 'Future date';
            }
            // Attendance record exists
            elseif ($record) {
                $status = 'Present';
                $note = $record->note ?? '-';
                $checkIn = $record->check_in;
                $checkOut = $record->check_out;

                if ($checkIn && $record->attendence_setting && $record->shift) {
                    $graceMinutes = (int) $record->attendence_setting->time_after_checkin;
                    $lateThreshold = Carbon::parse($dateStr . ' ' . $record->shift->start_time)
                        ->addMinutes($graceMinutes);
                    $checkInTime = Carbon::parse($dateStr . ' ' . $checkIn);

                    if ($checkInTime->gt($lateThreshold)) {
                        $status = 'Late';
                        $lateMinutes = $checkInTime->diffInMinutes($lateThreshold);
                    }
                } elseif (!empty($record->status)) {
                    $status = ucfirst(strtolower($record->status));
                }
            }
            // Leave
            elseif ($leaveRecord) {
                $status = 'Leave';
                $note = $leaveRecord->reason ?: 'Approved Leave';
            }
            // Holiday or weekend
            elseif ($holidayRecord || $isWeekend) {
                $status = 'Holiday';
                $note = $holidayRecord ? $holidayRecord->name : 'Weekend';
            }
            // Absent
            else {
                $status = 'Absent';
                $note = 'No record';
                $currentMonthAbsent++;
            }

            $calendar->push([
                'date' => $dateStr,
                'day' => $date->format('l'),
                'status' => $status,
                'note' => $note,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'late_minutes' => $lateMinutes,
                'is_today' => $dateStr === $today->toDateString(),
                'is_future' => $date->gt($today),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Tasks
        |--------------------------------------------------------------------------
        */
        $assignedTasks = Task::query()
            ->whereHas('users', function ($query) use ($userId) {
                $query->where('users.id', $userId);
            })
            ->with(['column:id,name', 'board:id,name'])
            ->get();

        $taskColumns = $assignedTasks
            ->groupBy(fn ($task) => $task->column_id ?: 'unassigned')
            ->map(function ($items, $columnId) {
                $first = $items->first();

                return [
                    'column_id' => is_numeric($columnId) ? (int) $columnId : null,
                    'column_name' => optional($first->column)->name ?? 'Unassigned',
                    'count' => $items->count(),
                    'tasks' => $items->map(function ($task) {
                        return [
                            'id' => $task->id,
                            'title' => $task->title ?? null,
                            'description' => $task->description ?? null,
                            'board_id' => $task->board_id,
                            'board_name' => optional($task->board)->name,
                            'column_id' => $task->column_id,
                            'status' => optional($task->column)->name,
                            'created_at' => optional($task->created_at)?->format('Y-m-d H:i:s'),
                            'updated_at' => optional($task->updated_at)?->format('Y-m-d H:i:s'),
                        ];
                    })->values(),
                ];
            })
            ->values();

        $taskSummary = $taskColumns->reduce(function ($carry, $column) {
            $normalized = strtolower((string) ($column['column_name'] ?? 'unassigned'));
            $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized);
            $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $normalized));

            $key = str_replace(' ', '_', $normalized);
            if ($key === '') {
                $key = 'unassigned';
            }

            $carry[$key] = ($carry[$key] ?? 0) + (int) ($column['count'] ?? 0);
            
            return $carry;
        }, []);

        $taskSummary['total_assigned'] = (int) $assignedTasks->count();

        /*
        |--------------------------------------------------------------------------
        | Support ticket count
        |--------------------------------------------------------------------------
        */
        $supportTicketCount = SupportTicket::query()
            ->where(function ($query) use ($userId) {
                $query->where('assigned_to', $userId)
                    ->orWhere('created_by', $userId);
            })
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Office Todos
        |--------------------------------------------------------------------------
        */
        $todoAssignees = OfficeTodoAssignee::where('user_id', $userId)->get();

        $officeTodoSummary = [
            'total'       => $todoAssignees->count(),
            'pending'     => $todoAssignees->where('status', 'pending')->count(),
            'in_progress' => $todoAssignees->where('status', 'in_progress')->count(),
            'completed'   => $todoAssignees->where('status', 'completed')->count(),
        ];

        $upcomingTodos = OfficeTodoAssignee::where('user_id', $userId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['todo:id,title,description,priority,due_date,status'])
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->todo?->id,
                'title'       => $a->todo?->title,
                'description' => $a->todo?->description,
                'priority'    => $a->todo?->priority,
                'due_date'    => $a->todo?->due_date,
                'my_status'   => $a->status,
            ])
            ->filter(fn ($item) => $item['id'] !== null)
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Employee dashboard data fetched successfully.',
            'data' => [
                'attendance' => [
                    'start_date'            => $calendarStart->toDateString(),
                    'end_date'              => $calendarEnd->toDateString(),
                    'current_month_present' => $currentMonthPresent,
                    'current_month_absent'  => $currentMonthAbsent,
                    'calendar'              => $calendar,
                ],
                'tasks' => [
                    'summary' => $taskSummary,
                    'columns' => $taskColumns,
                ],
                'support_tickets' => [
                    'total_count' => $supportTicketCount,
                ],
                'office_todos' => [
                    'summary'        => $officeTodoSummary,
                    'upcoming_todos' => $upcomingTodos,
                ],
            ],
        ]);
    }
}
