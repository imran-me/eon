<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ShiftController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view shift', only: ['index']),
            new Middleware('permission:create shift', only: ['create', 'store']),
            new Middleware('permission:edit shift', only: ['edit', 'update']),
            new Middleware('permission:delete shift', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Shift::query()->orderBy('id', 'asc'); 
        
        if ($request->has('name') && !empty($request->name)) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }
        if ($request->has('start_time') && !empty($request->start_time)) {
            $query->where('start_time', 'like', '%'.$request->start_time.'%');
        }
        if ($request->has('end_time') && !empty($request->end_time)) {
            $query->where('end_time', 'like', '%'.$request->end_time.'%');
        }
        
        $datas = $query->paginate(30);

        return view('shifts.index', compact(
            'datas'
        ));
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('shifts.create-modal');
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
            'name'  => 'required|string|max:255|unique:shifts,name',
            'start_time' => 'required',
            'end_time' => 'required'
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

        // Create
        $data = Shift::create([
            'name' => $request->name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'holidays' => $request->holidays
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.shifts.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('shifts.edit-modal', compact('id'));
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
        $data = Shift::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255|unique:shifts,name,' . $data->id,
            'start_time' => 'required',
            'end_time' => 'required'
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
                'name' => $request->name,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'holidays' => $request->holidays
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
            $data = Shift::find($request->item_id);
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
