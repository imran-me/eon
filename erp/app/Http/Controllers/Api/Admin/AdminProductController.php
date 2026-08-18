<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
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

        $datas = $query->orderBy('created_at', 'desc')->paginate(30);

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully.',
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
            'status'                    => 'required|string|in:not_started,in_progress,completed,on_hold,cancelled',
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
            'status' => $request->status,
            'budget' => $request->budget,
            'team_members' => $request->team_members,
            'description' => $request->description
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully.',
            'data' => $data
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $project = Project::with(['projectCategory', 'customer'])->find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.'
            ], 404);
        }

        // Check if employee is in team_members
        // if ('employee' == Str::slug(Auth::user()->getRoleNames()->first())) {
        //     $teamMembers = is_string($project->team_members) ? json_decode($project->team_members, true) : $project->team_members;
        //     if (empty($teamMembers) || !in_array((string) Auth::id(), (array)$teamMembers)) {
        //         return response()->json([
        //             'success' => false,
        //             'message' => 'Unauthorized to view this project.'
        //         ], 403);
        //     }
        // }

        return response()->json([
            'success' => true,
            'message' => 'Project details retrieved successfully.',
            'data' => $project
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
            'department_id'             => 'sometimes|required|integer|exists:departments,id',
            'customer_id'               => 'sometimes|required|integer|exists:customers,id',
            'project_name'              => 'sometimes|required|string',
            'status'                    => 'sometimes|required|string|in:not_started,in_progress,completed,on_hold,cancelled',
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

        try {
            $data->update($request->only([
                'project_category_id', 'company_id', 'department_id', 'customer_id',
                'project_name', 'start_date', 'end_date', 'status', 'budget',
                'team_members', 'description'
            ]));
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
            'data' => $data,
        ]);
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
