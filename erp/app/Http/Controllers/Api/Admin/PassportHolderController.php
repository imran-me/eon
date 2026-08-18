<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PassportHolder;
use App\Models\PassportHolderCategory;
use Illuminate\Http\Request;

class PassportHolderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Fetch passport holders from the database
            $passportHolders = PassportHolder::latest()->paginate(10);

            // Return the view with passport holders data
            return response()->json([
                'success' => true,
                'message' => 'Passport holders retrieved successfully.',
                'data' => $passportHolders
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch passport holders.',
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
             $categories = PassportHolderCategory::all();
            // Return the view for creating a new passport holder
            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved successfully.',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to load create form.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $role)
    {
       try {
            $validatedData = $request->validate([
                'name'           => 'required|string|max:255',
                'passport_no'    => 'required|string|max:50|unique:passport_holders,passport_no',
                'nationality'    => 'required|string|max:100',
                'phone'          => 'nullable|string|max:20',
                'date_of_birth'  => 'nullable|date',
                'issue_date'     => 'nullable|date',
                'expiry_date'    => 'nullable|date|after_or_equal:issue_date',
                'category_id'    => 'required|exists:passport_holder_categories,id',
            ]);

            PassportHolder::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Passport holder created successfully.',
                'data' => $validatedData
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to create passport holder.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $passportHolder = PassportHolder::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Passport holder retrieved successfully.',
                'data' => $passportHolder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch passport holder.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($role,string $id)
    {
        try {
            // Find the passport holder by ID
            $passportHolder = PassportHolder::findOrFail($id);
            $categories = PassportHolderCategory::all();
            // Return the view for editing the passport holder
            return response()->json([
                'success' => true,
                'message' => 'Passport holder and categories retrieved successfully.',
                'data' => [
                    'passportHolder' => $passportHolder,
                    'categories' => $categories
                ]
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to load edit form.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, PassportHolder $passportHolder)
    {
        try {
            $validatedData = $request->validate([
                'name'           => 'required|string|max:255',
                'passport_no'    => 'required|string|max:50|unique:passport_holders,passport_no,' . $passportHolder->id,
                'nationality'    => 'required|string|max:100',
                'phone'          => 'nullable|string|max:20',
                'date_of_birth'  => 'nullable|date',
                'issue_date'     => 'nullable|date',
                'expiry_date'    => 'nullable|date|after_or_equal:issue_date',
                'category_id'    => 'required|exists:passport_holder_categories,id',
            ]);

            $passportHolder->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Passport holder updated successfully.',
                'data' => $passportHolder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update passport holder.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Find the passport holder by ID
            $passportHolder = PassportHolder::findOrFail($id);

            // Delete the passport holder
            $passportHolder->delete();

            // Redirect to the index with success message
            return response()->json([
                'success' => true,
                'message' => 'Passport holder deleted successfully.',
                'data' => []
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete passport holder.',
                'data' => []
            ], 500);
        }
    }
}
