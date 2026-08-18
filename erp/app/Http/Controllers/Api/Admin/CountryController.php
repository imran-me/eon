<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;

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
        // $totalCountries   = Country::withTrashed()->count();
        // $activeCountries  = Country::whereNull('deleted_at')->count();
        // $inactiveCountries = Country::onlyTrashed()->count();
        // $trashedCountries = $inactiveCountries; // same as inactive in this context
    
        return response()->json([
            'success' => true,
            'message' => 'Countries retrieved successfully.',
            'data' => $countries
        ]);
    }

}
