<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\TicketDepartment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\Middleware;

class TicketDepartmentController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view ticket department', only: ['index']),
            new Middleware('permission:create ticket department', only: ['store']),
            new Middleware('permission:edit ticket department', only: ['update']),
            new Middleware('permission:delete ticket department', only: ['destroy']),
        ];
    }

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

        return view('ticket-departments.index', compact(
            'datas'
        ));
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

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Data created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = TicketDepartment::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255|unique:ticket_departments,name,' . $data->id
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
            'message' => 'Data updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $item = TicketDepartment::find($request->item_id);
            if ($item) {
                $item->delete();
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
