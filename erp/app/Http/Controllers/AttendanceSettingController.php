<?php

namespace App\Http\Controllers;

use App\Models\AttendenceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AttendanceSettingController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view attendence setting', only: ['index']),
            new Middleware('permission:create attendence setting', only: ['create', 'store']),
            new Middleware('permission:edit attendence setting', only: ['edit', 'update']),
            new Middleware('permission:delete attendence setting', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = AttendenceSetting::query()->orderBy('id', 'asc'); 
        
        if ($request->has('time_before_checkin') && !empty($request->time_before_checkin)) {
            $query->where('time_before_checkin', 'like', '%'.$request->time_before_checkin.'%');
        }
        if ($request->has('time_after_checkin') && !empty($request->time_after_checkin)) {
            $query->where('time_after_checkin', 'like', '%'.$request->time_after_checkin.'%');
        }
        if ($request->has('time_before_checkout') && !empty($request->time_before_checkout)) {
            $query->where('time_before_checkout', 'like', '%'.$request->time_before_checkout.'%');
        }
        if ($request->has('time_after_checkout') && !empty($request->time_after_checkout)) {
            $query->where('time_after_checkout', 'like', '%'.$request->time_after_checkout.'%');
        }
        
        $datas = $query->paginate(30);

        return view('attendence-settings.index', compact(
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
        return view('attendence-settings.create-modal');
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
            'time_before_checkin' => 'required',
            'time_after_checkin' => 'required',
            'time_before_checkout' => 'required',
            'time_after_checkout' => 'required'
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
        $data = AttendenceSetting::create([
            'time_before_checkin' => $request->time_before_checkin,
            'time_after_checkin' => $request->time_after_checkin,
            'time_before_checkout' => $request->time_before_checkout,
            'time_after_checkout' => $request->time_after_checkout
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.attendence-settings.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('attendence-settings.edit-modal', compact('id'));
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
        $data = AttendenceSetting::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'time_before_checkin' => 'required',
            'time_after_checkin' => 'required',
            'time_before_checkout' => 'required',
            'time_after_checkout' => 'required'
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
                'time_before_checkin' => $request->time_before_checkin,
                'time_after_checkin' => $request->time_after_checkin,
                'time_before_checkout' => $request->time_before_checkout,
                'time_after_checkout' => $request->time_after_checkout
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
            $data = AttendenceSetting::find($request->item_id);
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
