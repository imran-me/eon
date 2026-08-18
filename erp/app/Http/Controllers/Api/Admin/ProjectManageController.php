<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class ProjectManageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get the logged-in user's ID
        $userId = auth()->id();
        $query = Project::with('projectCategory', 'customer')
            ->withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => function ($taskQuery) {
                    $taskQuery->whereNotNull('completed_at');
                }
            ]);
        if ($request->has('project_name') && !empty($request->project_name)) {
            $query->where('project_name', 'like', '%'.$request->project_name.'%');
        }
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
        //if project team member has authenticated user id then show that project
        // Automatically filter projects where the user is a team member
        if('employee' == Str::slug(Auth::user()->getRoleNames()->first()) && !Auth::user()->hasPermissionTo('view all project')) {
            $query->whereJsonContains('team_members', (string) $userId);
        }

        $datas = $query->orderBy('created_at', 'desc')->paginate(30);
        
        // $projectCategories = ProjectCategory::orderBy('name', 'asc')->get();
        // $departments = Department::orderBy('name', 'asc')->get();
        // $customers = Customer::orderBy('name', 'asc')->get();
        // $companies = Company::orderBy('name', 'asc')->get();

        // $employees = User::role('employee')->orderBy('name', 'asc')->where('status','active')->get();

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully.',
            'data' => $datas,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
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
            ->findOrFail($id);

        $boardsQuery = Board::where('project_id', $project->id)
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
            ]);

        if ($request->filled('board_status')) {
            $boardsQuery->where('status', $request->board_status);
        }

        if ($request->boolean('overdue_only')) {
            $boardsQuery->whereHas('tasks', function ($taskQuery) {
                $taskQuery->whereNull('completed_at')->whereDate('due_date', '<', now()->toDateString());
            });
        }

        if ($request->filled('member_id')) {
            $memberId = (int) $request->member_id;
            $boardsQuery->whereHas('tasks', function ($taskQuery) use ($memberId) {
                $taskQuery->where('assigned_to', $memberId)
                    ->orWhereHas('users', function ($userQuery) use ($memberId) {
                        $userQuery->where('users.id', $memberId);
                    });
            });
        }

        $boards = $boardsQuery
            ->orderBy('name')
            ->paginate(4, ['*'], 'board_page');

        $recentTasks = Task::with(['column:id,name', 'users:id,name'])
            ->where('project_id', $project->id)
            ->latest()
            ->paginate(10, ['*'], 'task_page');

        $boardMembers = $project->teamMembers()->sortBy('name')->values();
        $boardFilters = [
            'board_status' => $request->board_status,
            'overdue_only' => $request->boolean('overdue_only'),
            'member_id' => $request->member_id,
        ];

        $pendingTasks = max($project->total_tasks - $project->completed_tasks, 0);
        $progressPercentage = $project->total_tasks > 0
            ? (int) round(($project->completed_tasks / $project->total_tasks) * 100)
            : 0;
        
        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully.',
            'data' => [
                'project' => $project,
                'boards' => $boards,
                'recent_tasks' => $recentTasks,
                'pending_tasks' => $pendingTasks,
                'progress_percentage' => $progressPercentage,
                'board_members' => $boardMembers,
                'board_filters' => $boardFilters,
            ],
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
            'budget'                    => 'required|numeric',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        // Create
        $data = Project::updateOrCreate([
            'project_name' => $request->project_name
        ],[
            'project_category_id' => $request->project_category_id,
            'company_id' => $request->company_id,
            'customer_id' => $request->customer_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'color' => $request->color ?? 'blue',
            'status' => $request->status,
            'budget' => $request->budget,
            'team_members' => $request->team_members,
            'description' => $request->description
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.',
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = Project::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'project_category_id'           => 'required|integer|exists:project_categories,id',
            'company_id'                    => 'required|integer|exists:companies,id',
            'customer_id'           => 'required|integer|exists:customers,id',
            'project_name'       => 'required|string',
            'color'              => 'nullable|in:gray,blue,purple,green,yellow,red,indigo,pink,orange,teal',
            'status'            => 'required|string|in:not_started,in_progress,completed,on_hold,cancelled',
            'budget'            => 'required|numeric',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }
        try {
            $data->update([
                'project_category_id' => $request->project_category_id,
                'company_id' => $request->company_id,
                'customer_id' => $request->customer_id,
                'project_name' => $request->project_name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'color' => $request->color ?? $data->color ?? 'blue',
                'status' => $request->status,
                'budget' => $request->budget,
                'team_members' => $request->team_members,
                'description' => $request->description
            ]);
            
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'errors' => $th->getTrace()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $data = Project::find($id);
        if ($data) {
            $data->delete();
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
    }
}
