<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Brand;
use App\Models\SalaryTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Brand::select('brands.*')->orderBy('brands.name', 'asc');

        if ($request->filled('name')) {
            $query->where('brands.name', $request->name);
        }

        if ($request->filled('is_active')) {
            $query->whereDate('brands.is_active', $request->is_active);
        }

        $datas = $query->paginate(20);        

        return response()->json([
            "success" => true,
            "message" => "Brand data retrieved successfully.",
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
            'name' => 'required',             
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }
  
        $data = Brand::create([              
            'name' => $request->name,    
            'is_active' => $request->is_active ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully.',
            'data' => $data
        ], 201);
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
        $data = Brand::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
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
            'is_active' => $request->is_active ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully.',
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
            $item = Brand::find($id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Brand Info Not Found!'
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
            'message' => 'Brand deleted successfully.'
        ]);
    }

}
