<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PassportHolder;
use App\Models\PassportHolderCategory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PassportHolderController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view passport holder', only: ['index']),
            new Middleware('permission:create passport holder', only: ['create', 'store']),
            new Middleware('permission:edit passport holder', only: ['edit', 'update']),
            new Middleware('permission:delete passport holder', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Fetch passport holders from the database
            $passportHolders = PassportHolder::latest()->paginate(10);;

            // Return the view with passport holders data
            return view('dashboard.passport_holders.index', compact('passportHolders'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to fetch passport holders.']);
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
            return view('dashboard.passport_holders.create', compact('categories'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to load create form.']);
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
                'date_of_birth'  => 'nullable|date',
                'issue_date'     => 'nullable|date',
                'expiry_date'    => 'nullable|date',
                'category_id'    => 'required|exists:passport_holder_categories,id',
            ]);

            PassportHolder::create($validatedData);

            return redirect()->back()->with('success', 'Passport holder created successfully.');
        } catch (\Exception $e) {

            return redirect()->back()->withErrors(['error' => 'Failed to create passport holder.'])->with('error', $e->getMessage())->withInput();
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
    public function edit($role,string $id)
    {
        try {
            // Find the passport holder by ID
            $passportHolder = PassportHolder::findOrFail($id);
            $categories = PassportHolderCategory::all();
            // Return the view for editing the passport holder
            return view('dashboard.passport_holders.edit', compact('passportHolder','categories'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to load edit form.']);
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
                'date_of_birth'  => 'nullable|date',
                'issue_date'     => 'nullable|date',
                'expiry_date'    => 'nullable|date',
                'category_id'    => 'required|exists:passport_holder_categories,id',
            ]);

            $passportHolder->update($validatedData);

            return redirect()->back()->with('success', 'Passport holder updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update passport holder.'])->with('error', $e->getMessage())->withInput();
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
            return redirect()->route('dashboard.passport-holder.index')->with('success', 'Passport holder deleted successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to delete passport holder.']);
        }
    }
}
