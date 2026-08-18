<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PassportHolderCategory;
use Illuminate\Http\Request;

class PassportHolderCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $categories = PassportHolderCategory::all();
            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved successfully.',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            return view('dashboard.passport_holders.category.create');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load create form: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            PassportHolderCategory::create($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Category created successfully.',
                'data' => []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $role, string $id)
    {
        try {
            $category = PassportHolderCategory::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Category retrieved successfully.',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch category: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $role, string $id)
    {
        try {
            $category = PassportHolderCategory::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Category retrieved successfully.',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load edit form: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(string $role, string $id, Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $category = PassportHolderCategory::findOrFail($id);
            $category->update($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully.',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $role, string $id)
    {
        // try {
        //     $category = PassportHolderCategory::findOrFail($id);
        //     $category->delete();
        //     return redirect()->route('role.passport-holder-category.index')->with('success', 'Category deleted successfully.');
        // } catch (\Exception $e) {
        //     return redirect()->back()->withErrors(['error' => 'Failed to delete category: ' . $e->getMessage()]);
        // }
    }
}
