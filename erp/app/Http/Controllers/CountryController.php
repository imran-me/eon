<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Country::query()->orderBy('name', 'asc');
    
        // Apply filters if provided
        if ($request->has('country_name') && !empty($request->country_name)) {
            $query->where('name', 'like', '%' . $request->country_name . '%');
        }
    
        // Filter by active / inactive / trashed using deleted_at
        if ($request->has('status') && $request->status !== '') {
            switch ($request->status) {
                case 'active':
                    $query->whereNull('deleted_at');
                    break;
                case 'inactive':
                    // Inactive = soft-deleted
                    $query->onlyTrashed();
                    break;
                case 'all':
                default:
                    $query->withTrashed();
                    break;
            }
        }
    
        $countries = $query->paginate(30);
    
        // Counts
        $totalCountries   = Country::withTrashed()->count();
        $activeCountries  = Country::whereNull('deleted_at')->count();
        $inactiveCountries = Country::onlyTrashed()->count();
        $trashedCountries = $inactiveCountries; // same as inactive in this context
    
        return view('country.index', compact(
            'countries',
            'totalCountries',
            'activeCountries',
            'inactiveCountries',
            'trashedCountries'
        ));
    }
    

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:countries,code'],
        ]);

        $country = Country::create([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
        ]);

        return response()->json(['success' => true, 'message' => 'Country created successfully.', 'data' => $country]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        $country = Country::findOrFail($id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('countries', 'code')->ignore($country->id)],
        ]);

        $country->update([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
        ]);

        return response()->json(['success' => true, 'message' => 'Country updated successfully.', 'data' => $country]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        Country::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Country deleted successfully.']);
    }

}
