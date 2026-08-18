<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = LeaveType::query()->orderBy('id', 'asc'); 
        
        if ($request->has('name') && !empty($request->name)) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }
        $datas = $query->paginate(30);

        return response()->json([
            'success' => true,
            'message' => 'Leave Type data retrieved successfully.',
            'data' => $datas
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255|unique:leave_types,name'
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
        $data = LeaveType::create([
            'name' => $request->name,
            'max_leaves_count' => $request->max_leaves_count,
            'requires_time' => $request->boolean('requires_time'),
            'exempts_early_out_deduction' => $request->boolean('exempts_early_out_deduction'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.',
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $role)
    {
        $data = LeaveType::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255|unique:leave_types,name,' . $data->id
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
                'name' => $request->name,
                'max_leaves_count' => $request->max_leaves_count,
                'requires_time' => $request->boolean('requires_time'),
                'exempts_early_out_deduction' => $request->boolean('exempts_early_out_deduction'),
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
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $data = LeaveType::find($request->item_id);
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
