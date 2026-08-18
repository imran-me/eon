<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkspaceUser;
use Illuminate\Http\Request;

class WorkSpaceUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax() && $request->has('workspace_id')) {
            $company = Project::find($request->workspace_id);

            if ($company && !empty($company->team_members)) {
                // Query the User table using the IDs found in the team_members array
                $users = User::whereIn('id', $company->team_members)
                    ->get(['id', 'name']) // Optimization: only grab what you need
                    ->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'text' => $user->name,
                        ];
                    });

                if ($users->isNotEmpty()) {
                    return response()->json([
                        'success' => true,
                        'users' => $users
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'No users found for the selected workspace.'
            ]);
        }

        $query = WorkspaceUser::groupedByProject()->orderBy('created_at', 'desc');

        if ($request->filled('workspace_id')) {
            $query->where('workspace_id', $request->workspace_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $paginatedProjects = $query->paginate(30);

        $projectIds = $paginatedProjects->pluck('project_id');

        $workspaceUsers = WorkspaceUser::with(['workspace', 'project', 'user'])
            ->whereIn('project_id', $projectIds)
            ->get()
            ->groupBy('project_id');

        $paginatedProjects->setCollection(
            $paginatedProjects->getCollection()->map(function ($item) use ($workspaceUsers) {
                $users = $workspaceUsers->get($item->project_id, collect());
                $first = $users->first();

                return (object) [
                    'project' => $first?->project,
                    'workspace' => $first?->workspace,
                    'owners' => $users->where('role', 'owner')->pluck('user.name')->implode(', '),
                    'admins' => $users->where('role', 'admin')->pluck('user.name')->implode(', '),
                    'members' => $users->where('role', 'member')->pluck('user.name')->implode(', '),
                    'created_at' => $item->created_at,
                ];
            })
        );

        $datas = $paginatedProjects;

        // $companies = Company::where('status', 1)->orderBy('name', 'asc')->get();
        // $projects = Project::whereIn('status', ['in_progress', 'completed'])
        //     ->orderBy('id', 'desc')
        //     ->get();
        // $allUsers = User::where('status', 'active')->orderBy('name')->role('employee')->get();
        // $roles = [
        //     ['value' => 'owner', 'name' => 'Owner'],
        //     ['value' => 'admin', 'name' => 'Admin'],
        //     ['value' => 'member', 'name' => 'Member'],
        // ];

        return response()->json([
            'success' => true,
            'message' => 'Workspace users retrieved successfully.',
            'data' => $datas,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'workspace_id' => 'required|exists:projects,id',
            'assignments' => 'required|array|min:1',
            'assignments.*.user_id' => 'required|exists:users,id',
            'assignments.*.role' => 'required|in:owner,admin,member',
        ]);

        $totalAssigned = 0;
        $assignments = [];
        $project = Project::find($request->workspace_id);
        try {
            foreach ($request->assignments as $assignment) {
                $existing = WorkspaceUser::where([
                    'workspace_id' => $project->company_id,
                    'project_id' => $project->id,
                    'user_id' => $assignment['user_id'],
                    'role' => $assignment['role'],
                ])->first();

                if (!$existing) {
                    $assignments[] = [
                        'workspace_id' => $project->company_id,
                        'project_id' => $project->id,
                        'user_id' => $assignment['user_id'],
                        'role' => $assignment['role'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $totalAssigned++;
                }
            }

            if (!empty($assignments)) {
                WorkspaceUser::insert($assignments);
            }

            $message = $totalAssigned > 0
                ? "{$totalAssigned} user(s) assigned successfully"
                : "All selected users were already assigned";

            return response()->json([
                'success' => true,
                'message' => $message,
                'total_assigned' => $totalAssigned,
                'total_duplicates' => (count($request->assignments) - $totalAssigned)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create assignments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Check if this is a bulk update request
        if ($request->has('user_roles') && is_array($request->user_roles)) {
            return $this->bulkUpdateRoles($request);
        }

        // Single user update
        $request->validate([
            'role' => 'required|in:owner,admin,member',
        ]);

        try {
            $workspaceUser = WorkspaceUser::findOrFail($request->id);

            // Check if another record with same workspace, user, and new role already exists
            $duplicate = WorkspaceUser::where('workspace_id', $workspaceUser->workspace_id)
                ->where('user_id', $workspaceUser->user_id)
                ->where('role', $request->role)
                ->where('id', '!=', $request->id)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'This user already has the ' . ucfirst($request->role) . ' role for this workspace.'
                ]);
            }

            $workspaceUser->role = $request->role;
            $workspaceUser->save();

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully to ' . ucfirst($request->role)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle bulk role updates for multiple users
     */
    private function bulkUpdateRoles(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'user_roles' => 'required|array|min:1',
            'user_roles.*.id' => 'required|exists:workspace_users,id',
            'user_roles.*.role' => 'required|in:owner,admin,member',
        ]);

        try {
            $updatedCount = 0;
            foreach ($request->user_roles as $userRole) {
                $workspaceUser = WorkspaceUser::find($userRole['id']);
                if ($workspaceUser && $workspaceUser->role !== $userRole['role']) {
                    $workspaceUser->role = $userRole['role'];
                    $workspaceUser->save();
                    $updatedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => $updatedCount > 0 
                    ? "{$updatedCount} user role(s) updated successfully" 
                    : "No changes were made",
                'updated_count' => $updatedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all users and roles for a specific project
     */
    public function getProjectUsers(Request $request, $role, $project_id)
    {
        try {
            if (!$project_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project ID is required.'
                ], 400);
            }

            $workspaceUsers = WorkspaceUser::with('user')
                ->where('project_id', $project_id)
                ->get()
                ->map(function($wu) {
                    return [
                        'id' => $wu->id,
                        'user_id' => $wu->user_id,
                        'user_name' => $wu->user->name,
                        'role' => $wu->role,
                    ];
                });

            return response()->json([
                'success' => true,
                'users' => $workspaceUsers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load project users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $data = WorkspaceUser::find($id);
            if ($data) {
                $data->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Workspace User Info Not Found!'
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
            'message' => 'Workspace User deleted successfully.'
        ]);
    }
}
