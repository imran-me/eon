<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectDepartment;
use App\Models\ProjectFieldDefinition;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class ProjectManageController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view lead project|view all lead project', only: ['index', 'show']),
            new Middleware('permission:create lead project', only: ['create', 'store']),
            new Middleware('permission:edit lead project', only: ['edit', 'update']),
            new Middleware('permission:delete lead project', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get the logged-in user's ID
        $userId = auth()->id();
        $query = Project::with('projectCategory', 'customer')
            ->where('type', 'lead_project')
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
        if('employee' == Str::slug(Auth::user()->getRoleNames()->first()) && !Auth::user()->hasPermissionTo('view all lead project')) {
            $query->whereJsonContains('team_members', (string) $userId);
        }

        $datas = $query->orderBy('created_at', 'desc')->paginate(30);

        $projectCategories = ProjectCategory::orderBy('name', 'asc')->get();
        $customers = Customer::orderBy('name', 'asc')->get();
        $companies = Company::orderBy('name', 'asc')->get();
        $employees = User::role('employee')->orderBy('name', 'asc')->where('status','active')->get();

        return view('projects.index', compact(
            'datas',
            'projectCategories',
            'customers',
            'employees',
            'companies'
        ));
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $role, string $id)
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

        return view('projects.show', compact(
            'project',
            'boards',
            'recentTasks',
            'pendingTasks',
            'progressPercentage',
            'boardMembers',
            'boardFilters'
        ));
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
            'type'                      => 'nullable|in:task_project,lead_project',
            'budget'                    => 'required|numeric',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = Project::updateOrCreate([
            'project_name' => $request->project_name
        ],[
            'project_category_id'   => $request->project_category_id,
            'company_id'            => $request->company_id,
            'customer_id'           => $request->customer_id,
            'start_date'            => $request->start_date,
            'end_date'              => $request->end_date,
            'color'                 => $request->color ?? 'blue',
            'status'                => $request->status,
            'type'                  => $request->type ?? 'task_project',
            'budget'                => $request->budget,
            'team_members'          => $request->team_members,
            'description'           => $request->description
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('dashboard.proposals.index')->with('success', 'Data created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = Project::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'project_category_id'       => 'required|integer|exists:project_categories,id',
            'company_id'                => 'required|integer|exists:companies,id',
            'customer_id'               => 'required|integer|exists:customers,id',
            'project_name'              => 'required|string',
            'color'                     => 'nullable|in:gray,blue,purple,green,yellow,red,indigo,pink,orange,teal',
            'status'                    => 'required|string|in:not_started,in_progress,completed,on_hold,cancelled',
            'type'                      => 'nullable|in:task_project,lead_project',
            'budget'                    => 'required|numeric',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }
        try {
            $data->update([
                'project_category_id'   => $request->project_category_id,
                'company_id'            => $request->company_id,
                'customer_id'           => $request->customer_id,
                'project_name'          => $request->project_name,
                'start_date'            => $request->start_date,
                'end_date'              => $request->end_date,
                'color'                 => $request->color ?? $data->color ?? 'blue',
                'status'                => $request->status,
                'type'                  => $request->type ?? $data->type ?? 'task_project',
                'budget'                => $request->budget,
                'team_members'          => $request->team_members,
                'description'           => $request->description
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
            'data' => $data,
        ]);
    }

    public function getProjectDepartments(Request $request)
    {
        $types = ProjectDepartment::where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'data' => $types]);
    }

    /**
     * Get custom field definitions by project_department_id (AJAX)
     */
    public function getFieldDefinitions(Request $request)
    {
        $fields = ProjectFieldDefinition::where('project_department_id', $request->project_department_id)
            ->where('status', true)
            ->orderBy('sort_order')
            ->get(['id', 'label', 'type', 'options', 'required']);

        return response()->json(['success' => true, 'data' => $fields]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $data = Project::find($request->item_id);
            if ($data) {
                $data->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
    }
}
