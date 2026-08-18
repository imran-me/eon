<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProjectCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $companyId = auth()->user()->company_id ?? 1;
        $query = ProjectCategory::query()->where('company_id', $companyId)->orderBy('name', 'asc');
        if ($request->has('name') && !empty($request->name)) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }
        $datas = $query->paginate(30);

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully.',
            'data' => $datas,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $companyId = auth()->user()->company_id ?? 1;

        $request->validate([
            'name' => 'required|string|max:255|unique:project_categories,name,NULL,id,company_id,' . $companyId,
        ]);

        // Create
        $data = ProjectCategory::updateOrCreate([
            'company_id' => $companyId,
            'name'       => $request->name,
        ], [
            'is_active' => $request->is_active ? 1 : 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = ProjectCategory::findOrFail($id);
        $companyId = auth()->user()->company_id ?? 1;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:project_categories,name,' . $data->id . ',id,company_id,' . $companyId,
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
    public function destroy(Request $request, string $id)
    {
        try {
            $item = ProjectCategory::find($id);
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
