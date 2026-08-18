<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Project;
use App\Models\Task;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $query = Project::with(['projectCategory', 'customer']);

        if ($request->filled('project_name')) {
            $query->where('project_name', 'like', '%' . $request->project_name . '%');
        }
        if ($request->filled('project_category_id')) {
            $query->where('project_category_id', $request->project_category_id);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Automatically filter projects where the user is a team member
        if ('employee' == Str::slug(Auth::user()->getRoleNames()->first())) {
            $query->whereJsonContains('team_members', (string) $userId);
        }

        $datas = $query->where('type', 'task_project')->orderBy('created_at', 'desc')->paginate(30);

        $datas->getCollection()->transform(function (Project $project) {
            return $this->appendColorGradient($project);
        });

        return response()->json([
            'success' => true,
            'message' => 'Projects retrieved successfully.',
            'data' => $datas
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_category_id'       => 'required|integer|exists:project_categories,id',
            'company_id'                => 'required|integer|exists:companies,id',
            'customer_id'               => 'required|integer|exists:customers,id',
            'project_name'              => 'required|string',
            'color'                     => 'nullable|in:gray,blue,purple,green,yellow,red,indigo,pink,orange,teal',
            'status'                    => 'required|string|in:not_started,in_progress,completed,on_hold,cancelled',
            'type'                      => 'sometimes|required|string|in:task_project,lead_project',
            'budget'                    => 'required|numeric',
            'team_members'              => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = Project::create([
            'project_category_id' => $request->project_category_id,
            'company_id' => $request->company_id,
            'customer_id' => $request->customer_id,
            'project_name' => $request->project_name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'color' => $request->color ?? 'blue',
            'status' => $request->status,
            'type' => $request->type,
            'budget' => $request->budget,
            'team_members' => $request->team_members,
            'description' => $request->description
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully.',
            'data' => $this->appendColorGradient($data)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $project = Project::with(['projectCategory', 'customer'])
            ->withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => function ($taskQuery) {
                    $taskQuery->whereNotNull('completed_at');
                },
                'tasks as overdue_tasks' => function ($taskQuery) {
                    $taskQuery->whereNull('completed_at')->whereDate('due_date', '<', now()->toDateString());
                },
                'modules as total_modules'
            ])
            ->where('type', 'task_project')
            ->find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.'
            ], 404);
        }

        // Check if employee is in team_members
        if ('employee' == Str::slug(Auth::user()->getRoleNames()->first())) {
            $teamMembers = is_string($project->team_members) ? json_decode($project->team_members, true) : $project->team_members;
            if (empty($teamMembers) || !in_array((string) Auth::id(), (array)$teamMembers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this project.'
                ], 403);
            }
        }

        $boards = Board::where('project_id', $project->id)
            ->withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => function ($taskQuery) {
                    $taskQuery->whereNotNull('completed_at');
                },
                'tasks as overdue_tasks' => function ($taskQuery) {
                    $taskQuery->whereNull('completed_at')->whereDate('due_date', '<', now()->toDateString());
                },
                'tasks as blocked_tasks' => function ($taskQuery) {
                    $taskQuery->whereHas('column', function ($columnQuery) {
                        $columnQuery->whereRaw("LOWER(name) LIKE ?", ['%block%'])
                            ->orWhereRaw("LOWER(name) LIKE ?", ['%hold%']);
                    });
                },
                'tasks as unassigned_tasks' => function ($taskQuery) {
                    $taskQuery->whereNull('assigned_to')->doesntHave('users');
                }
            ])
            ->orderBy('name')
            ->get();

        $recentTasks = Task::with(['column:id,name', 'users:id,name'])
            ->where('project_id', $project->id)
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Project details retrieved successfully.',
            'data' => $this->formatProjectOverviewResponse($project, $boards, $recentTasks)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $data = Project::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.'
            ], 404);
        }

        // Validate that if they are employee, they have rights (optional based on your business logic)
        // Usually, employees might only view or update task status, but if you allow full edit:
        if ('employee' == Str::slug(Auth::user()->getRoleNames()->first())) {
            $teamMembers = is_string($data->team_members) ? json_decode($data->team_members, true) : $data->team_members;
            if (empty($teamMembers) || !in_array((string) Auth::id(), (array)$teamMembers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this project.'
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'project_category_id'       => 'sometimes|required|integer|exists:project_categories,id',
            'company_id'                => 'sometimes|required|integer|exists:companies,id',
            'customer_id'               => 'sometimes|required|integer|exists:customers,id',
            'project_name'              => 'sometimes|required|string',
            'color'                     => 'nullable|in:gray,blue,purple,green,yellow,red,indigo,pink,orange,teal',
            'status'                    => 'sometimes|required|string|in:not_started,in_progress,completed,on_hold,cancelled',
            'type'                      => 'sometimes|required|string|in:task_project,lead_project',
            'budget'                    => 'sometimes|required|numeric',
            'team_members'              => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = (string) ($data->status ?? '');

        try {
            $data->update($request->only([
                'project_category_id', 'company_id', 'customer_id',
                'project_name', 'start_date', 'end_date', 'color', 'status', 'type', 'budget',
                'team_members', 'description'
            ]));

            $newStatus = (string) ($data->status ?? '');
            if ($oldStatus !== '' && $newStatus !== '' && $oldStatus !== $newStatus && class_exists(NotificationService::class)) {
                try {
                    NotificationService::notifyProjectStatusChanged($data, $oldStatus, $newStatus);
                } catch (\Throwable $th) {
                }
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully.',
            'data' => $this->appendColorGradient($data),
        ]);
    }

    private function appendColorGradient(Project $project): Project
    {
        $project->setAttribute('color_gradient', data_get($project->color_details, 'gradient'));

        return $project;
    }

    private function formatProjectForResponse(Project $project): Project
    {
        $project = $this->appendColorGradient($project);

        // Keep original team_members IDs unchanged for backward compatibility.
        $teamMemberIds = is_string($project->team_members)
            ? json_decode($project->team_members, true)
            : ($project->team_members ?? []);

        if (!is_array($teamMemberIds) || empty($teamMemberIds)) {
            $project->setAttribute('team_member_users', []);

            return $project;
        }

        $teamMemberUsers = \App\Models\User::query()
            ->whereIn('id', array_map('intval', $teamMemberIds))
            ->select('name', 'image')
            ->get()
            ->map(function ($user) {
                $image = $user->image;

                if (!empty($image) && !\Illuminate\Support\Str::startsWith($image, ['http://', 'https://'])) {
                    $image = asset($image);
                }

                return [
                    'name' => $user->name,
                    'image' => $image,
                ];
            })
            ->values();

        $project->setAttribute('team_member_users', $teamMemberUsers);

        return $project;
    }

    private function formatProjectOverviewResponse(Project $project, $boards, $recentTasks): array
    {
        $teamMemberUsers = $this->formatProjectForResponse($project)->getAttribute('team_member_users') ?? [];

        $progressPercentage = $project->total_tasks > 0
            ? (int) round(($project->completed_tasks / $project->total_tasks) * 100)
            : 0;

        return [
            'id' => $project->id,
            'project_name' => $project->project_name,
            'description' => $project->description,
            'status' => $project->status,
            'color' => $project->color,
            'color_gradient' => data_get($project->color_details, 'gradient'),
            'start_date' => optional($project->start_date)->format('Y-m-d'),
            'end_date' => optional($project->end_date)->format('Y-m-d'),
            'category' => [
                'id' => optional($project->projectCategory)->id,
                'name' => optional($project->projectCategory)->name,
            ],
            'customer' => [
                'id' => optional($project->customer)->id,
                'name' => optional($project->customer)->name,
            ],
            'overview' => [
                'progress_percentage' => $progressPercentage,
                'total_tasks' => (int) $project->total_tasks,
                'completed_tasks' => (int) $project->completed_tasks,
                'pending_tasks' => (int) max($project->total_tasks - $project->completed_tasks, 0),
                'overdue_tasks' => (int) $project->overdue_tasks,
                'total_modules' => (int) $project->total_modules,
            ],
            'team_members' => $teamMemberUsers,
            'modules' => $boards->map(function ($board) {
                $progress = $board->total_tasks > 0
                    ? (int) round(($board->completed_tasks / $board->total_tasks) * 100)
                    : 0;

                return [
                    'id' => $board->id,
                    'name' => $board->name,
                    'description' => $board->description,
                    'status' => $board->status,
                    'progress_percentage' => $progress,
                    'total_tasks' => (int) $board->total_tasks,
                    'completed_tasks' => (int) $board->completed_tasks,
                    'overdue_tasks' => (int) $board->overdue_tasks,
                    'blocked_tasks' => (int) $board->blocked_tasks,
                    'unassigned_tasks' => (int) $board->unassigned_tasks,
                ];
            })->values(),
            'recent_tasks' => $recentTasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'column' => optional($task->column)->name,
                    'due_date' => $task->due_date ? date('Y-m-d', strtotime($task->due_date)) : null,
                    'state' => $task->completed_at ? 'done' : 'open',
                    'assignees' => $task->users->pluck('name')->values(),
                ];
            })->values(),
        ];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $data = Project::find($id);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project Not Found!'
                ], 404);
            }

            // Optional permissions check
            if ('employee' == Str::slug(Auth::user()->getRoleNames()->first())) {
                $teamMembers = is_string($data->team_members) ? json_decode($data->team_members, true) : $data->team_members;
                if (empty($teamMembers) || !in_array((string) Auth::id(), (array)$teamMembers)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to delete this project.'
                    ], 403);
                }
            }

            $data->delete();

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
