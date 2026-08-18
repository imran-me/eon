<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\TicketDepartment;
use Illuminate\Support\Facades\Validator;

class TicketDepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = TicketDepartment::query()->orderBy('name', 'asc');
        if ($request->has('name') && !empty($request->name)) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }        
        $datas = $query->paginate(30);

        return response()->json([
            'success' => true,
            'message' => 'Tickets departments retrieved successfully.',
            'data' => $datas
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255|unique:ticket_departments,name'
        ]);

        // Create
        $data = TicketDepartment::updateOrCreate([
            'name' => $request->name
        ],[
            'is_active' => $request->is_active ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tickets department created successfully.',
            'data' => $data
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = TicketDepartment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255|unique:ticket_departments,name,' . $data->id
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data->update([
                'name'    => $request->name,
                'is_active' => $request->is_active ? 1 : 0,
            ]);
            
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);    
        }

        return response()->json([
            'success' => true,
            'message' => 'Tickets department updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $item = TicketDepartment::find($id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Tickets department not found!'
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
            'message' => 'Tickets department deleted successfully.'
        ]);
    }
}

