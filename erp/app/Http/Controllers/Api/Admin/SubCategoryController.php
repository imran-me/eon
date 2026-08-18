<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = SubCategory::select('sub_categories.*')
            ->leftJoin('categories', 'categories.id', '=', 'sub_categories.category_id')
            ->orderBy('categories.name', 'asc')
            ->orderBy('sub_categories.name', 'asc');

        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('sub_categories.category_id', $request->category_id);
        }

        if ($request->filled('name')) {
            $query->where('sub_categories.name', $request->name);
        }

        if ($request->filled('is_active')) {
            $query->whereDate('sub_categories.is_active', $request->is_active);
        }

        $datas = $query->paginate(20);    

        return response()->json([
            "success" => true,
            "message" => "Sub Category data retrieved successfully.",
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
            'name' => 'required|string|max:255|unique:sub_categories,name',
            'category_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }
  
        $data = SubCategory::create([              
            'name' => $request->name,    
            'category_id' => $request->category_id,    
            'is_active' => $request->is_active ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sub Category created successfully.',
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
    public function update(Request $request, $id)
    {
        $data = SubCategory::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:sub_categories,name,' . $data->id,
            'category_id' => 'required',
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
            'category_id' => $request->category_id,
            'is_active' => $request->is_active ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sub Category updated successfully.',
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
            $item = SubCategory::find($id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Sub Category Not Found!'
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
            'message' => 'Sub Category deleted successfully.'
        ]);
    }

    public function getSubCategory(Request $request)
    {
        try {
            $data = SubCategory::where('category_id', $request->category_id)->orderBy('id', 'ASC')->get();
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => 'Sub Category Data Found Successfully.'
        ]);
    }
}
