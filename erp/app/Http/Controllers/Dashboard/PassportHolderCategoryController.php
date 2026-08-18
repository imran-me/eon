<?php

namespace App\Http\Controllers\Dashboard;

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
            return view('dashboard.passport_holders.category.index', compact('categories'));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to fetch categories: ' . $e->getMessage()]);
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
            return redirect()->back()->withErrors(['error' => 'Failed to load create form: ' . $e->getMessage()]);
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
            return redirect()->back()->with('success', 'Category created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create category: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $role, string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $role, string $id)
    {
        try {
            $category = PassportHolderCategory::findOrFail($id);
            return view('dashboard.passport_holders.category.edit', compact('category'));
        } catch (\Exception $e) {
            dd($e);
            return redirect()->back()->withErrors(['error' => 'Failed to load edit form: ' . $e->getMessage()]);
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
            return redirect()->route('role.passport-holder-category.index', ['role' => $role])
                ->with('success', 'Category updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update category: ' . $e->getMessage()]);
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
