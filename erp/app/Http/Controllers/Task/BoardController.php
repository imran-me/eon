<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Column;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BoardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view board')->only('index');
        $this->middleware('permission:create board')->only('store');
        $this->middleware('permission:edit board')->only('update');
        $this->middleware('permission:delete board')->only('destroy');
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $user = Auth::user();
        $query = Board::with('workspace', 'project', 'user', 'columns');

        // Only show boards for projects where the authenticated user is a project team member.
        // For admin / superuser, or users with 'view all tasks' permission, show all boards; adjust role names accordingly.
        if ($user && !in_array($user->getRoleNames()[0], ['admin', 'super admin']) && !$user->hasPermissionTo('view all board')) {
            $userId = (string)$user->id;
            $query->whereHas('project', function ($q) use ($userId) {
                $q->whereJsonContains('team_members', $userId);
            });
        }
        // --- Bakki Filter-gula ager motoi thakbe ---
        if ($request->filled('workspace_id')) {
            $query->where('workspace_id', $request->workspace_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $datas = $query->orderBy('id', 'desc')->paginate(20);

        $workspaces = Company::orderBy('name', 'asc')->get();
        $projects = Project::orderBy('project_name', 'asc')->get();
        $columns = Column::orderBy('name', 'asc')->get();
        return view('task-module.boards.index', compact(
            'datas',
            'projects',
            'workspaces',
            'columns'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'workspace_id'  => 'required|integer|exists:companies,id',
            'project_id'    => 'required|integer|exists:projects,id',
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'columns'       => 'nullable|array',
            'columns.*'     => 'integer|exists:columns,id',
        ]);

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }
        $project = Project::find($request->project_id);
        $data = Board::updateOrCreate([
            'name' => $request->name
        ], [
            'workspace_id' => $project->company_id,
            'project_id' => $request->project_id,
            'description' => $request->description,
            'created_by' => Auth::id(),
        ]);
        // position is array positions
        $positions = $request->positions ?? [];

        // Clear previous columns
        BoardColumn::where('board_id', $data->id)->delete();

        $boardColumns = [];

        foreach ($request->columns ?? [] as $key => $columnId) {

            $boardColumns[] = [
                'board_id' => $data->id,
                'column_id' => $columnId,
                'position' => $positions[$key] ?? 0,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        if (count($boardColumns)) {
            BoardColumn::insert($boardColumns);
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Board created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('task.boards.index')->with('success', 'Board created successfully.');
    }

    public function edit(Request $request, $role, string $id)
    {
        $data = Board::with('columns')->findOrFail($id);
        $projects = Project::orderBy('project_name', 'asc')->get();
        $columns = Column::orderBy('name', 'asc')->get();
        return view('task-module.boards.edit', compact('data', 'projects', 'columns'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, Board $board)
    {
        $data = Board::findOrFail($board->id);
        $validator = Validator::make($request->all(), [
            // 'workspace_id'          => 'required|integer|exists:companies,id',
            'project_id'            => 'required|integer|exists:projects,id',
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string|max:1000',
        ]);

        // borad column update
        BoardColumn::where('board_id', $board->id)->delete();

        foreach ($request->columns as $columnId) {

            BoardColumn::create([
                'board_id' => $board->id,
                'column_id' => $columnId,
                'position' => $request->positions[$columnId] ?? 1
            ]);
        }
        // If validation fails
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
            $project = Project::find($request->project_id);
            $data->update([
                'name' => $request->name,
                'workspace_id' => $project->company_id,
                'project_id' => $request->project_id,
                'description' => $request->description,
                'created_by' => Auth::id(),
            ]);
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }

        return redirect()->back()->with('success', 'Board updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $data = Board::find($request->item_id);
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
    public function updateStatus(Request $request, $role, $id)
    {
        try {
            $board = Board::findOrFail($id);
            $board->status = $request->status;
            $board->save();

            return response()->json([
                'success' => true,
                'message' => 'Board status updated successfully.',
                'data' => $board
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }
    }
}
