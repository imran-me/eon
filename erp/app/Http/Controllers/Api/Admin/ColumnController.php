<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Column;
use App\Models\WorkspaceUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ColumnController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Column::query();
        if ($request->filled('board_id')) {
            $query->where('board_id', $request->board_id);
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        
        $datas = $query->orderBy('position', 'asc')->paginate(30);

        // $boards = Board::orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Columns retrieved successfully.',
            'data' => $datas
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'color'         => 'nullable|in:gray,blue,purple,green,yellow,red,indigo,pink,orange,teal',
            'position'      => 'nullable|integer|min:0',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        // Auto-calculate position if not provided
        $position = $request->position;
        if (!$position) {
            $position = (Column::max('position') ?? 0) + 1;
        }

        // Create
        $data = Column::create([
            'name' => $request->name,
            'position' => $position,
            'color' => $request->color ?? 'blue'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Column created successfully.',
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $column = Column::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            // 'board_id' => 'required|integer|exists:boards,id',
            'name' => 'required|string|max:255',
            'color' => 'nullable|in:gray,blue,purple,green,yellow,red,indigo,pink,orange,teal',
            'position' => 'nullable|integer|min:0',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        // Auto-calculate position if not provided
        $position = $request->position;
        if (!$position) {
            $position = $column->position;
        }

        // Update column
        $column->update([
            'name' => $request->name,
            'position' => $position,
            'color' => $request->color ?? $column->color ?? 'blue'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Column updated successfully.',
            'data' => $column
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        try {
            $data = Column::find($request->item_id);
            if ($data) {
                $data->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Column Not Found!'
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
            'message' => 'Column deleted successfully.'
        ]);
    }
}
