<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Unit;
use App\Models\SalaryTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Unit::select('units.*')->orderBy('units.name', 'asc');

        if ($request->filled('name')) {
            $query->where('units.name', $request->name);
        }

        if ($request->filled('is_active')) {
            $query->whereDate('units.is_active', $request->is_active);
        }

        $datas = $query->paginate(20);        

        return response()->json([
            "success" => true,
            "message" => "Unit data retrieved successfully.",
            "data" => $datas
        ], 200);
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
            'name'      => 'required|string|max:255',
            'symbol'    => 'required|string|max:50|unique:units,symbol',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }
  
        $data = Unit::create([              
            'name' => $request->name,    
            'symbol' => $request->symbol,
            'is_active' => $request->is_active ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Unit created successfully.',
            'data' => $data
        ], 201);
    }

    /**
     * Display the specified resource, including replies (chat).
     */
    public function show($id)
    {
        $unit = Unit::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Unit details retrieved successfully.',
            'data' => $unit
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = Unit::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Unit Info Not Found!'
            ]);
        }
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'symbol'    => 'required|string|max:50|unique:units,symbol',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422);
    }

        $data->update([
            'name' => $request->name,
            'symbol' => $request->symbol,
            'is_active' => $request->is_active ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Unit updated successfully.',
            'data' => $data
        ]);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $item = Unit::find($id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unit Info Not Found!'
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
            'message' => 'Unit deleted successfully.'
        ]);
    }

}
