<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\VisaCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VisaCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = VisaCategory::query()->orderBy('name', 'asc');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $datas = $query->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Visa categories retrieved successfully.',
            'data' => $datas
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:visa_categories,name',
            'base_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = VisaCategory::create([
            'name' => $request->name,
            'base_fee' => $request->base_fee ?? 0,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visa category created successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, string $id)
    {
        $visaCategory = VisaCategory::findOrFail($request->id ?? $id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:visa_categories,name,' . $visaCategory->id,
            'base_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $visaCategory->update([
            'name' => $request->name,
            'base_fee' => $request->base_fee ?? 0,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visa category updated successfully.',
            'data' => $visaCategory,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $role, string $id)
    {
        $visaCategory = VisaCategory::find($request->item_id ?? $id);
        if (!$visaCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Visa category not found!'
            ]);
        }

        $visaCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Visa category deleted successfully.',
        ]);
    }
}
