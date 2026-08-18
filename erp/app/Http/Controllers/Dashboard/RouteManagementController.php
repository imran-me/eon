<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RouteManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            // Fetch routes from the database
            $routes = \App\Models\Route::latest()->get();

            // Return the view with routes data
            return view('dashboard.routes.index', compact('routes'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to fetch routes.']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            // Return the view for creating a new route
            return view('dashboard.routes.create');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to load create form.']);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                // Add other validation rules as needed
            ]);

            // Create a new route
            \App\Models\Route::create($request->all());

            // Redirect to the index with success message
            return redirect()->route('dashboard.route.index')->with('success', 'Route created successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to create route.']);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            // Find the route by ID
            $route = \App\Models\Route::findOrFail($id);

            // Return the view for editing the route
            return view('dashboard.routes.edit', compact('route'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to load edit form.']);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // Validate the request data
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                // Add other validation rules as needed
            ]);

            // Find the route by ID and update it
            $route = \App\Models\Route::findOrFail($id);
            $route->update($request->all());

            // Redirect to the index with success message
            return redirect()->route('dashboard.route.index')->with('success', 'Route updated successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to update route.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Find the route by ID and delete it
            $route = \App\Models\Route::findOrFail($id);
            $route->delete();

            // Redirect to the index with success message
            return redirect()->route('dashboard.route.index')->with('success', 'Route deleted successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to delete route.']);
        }
    }
}
