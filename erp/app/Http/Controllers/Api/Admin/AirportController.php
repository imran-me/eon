<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Models\State;
use Illuminate\Support\Facades\Validator;

class AirportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Airport::select('airports.*')
            ->join('countries', 'countries.id', '=', 'airports.country_id')
            ->join('states', 'states.id', '=', 'airports.state_id')
            ->orderBy('countries.name', 'asc')
            ->orderBy('states.name', 'asc')
            ->orderBy('airports.name', 'asc');
        $request_states = null;
        // Apply filters
        if ($request->has('country_id') && !empty($request->country_id)) {
            $query->where('airports.country_id', $request->country_id);
            $request_states = State::where('country_id', $request->country_id)->orderBy('name', 'ASC')->get();
        }

        if ($request->has('state_id') && !empty($request->state_id)) {
            $query->where('airports.state_id', $request->state_id);
        }

        if ($request->has('name_code') && !empty($request->name_code)) {
            $query->where('airports.name', 'like', '%' . $request->name_code . '%')
            ->orWhere('airports.code', 'like', '%' . $request->name_code . '%');
        }

        // Paginate
        $datas = $query->paginate(30);

        // Stats
        // $totalAirports   = Airport::count();
        // $countriesCount  = Airport::distinct('country_id')->count('country_id');
        // $statesCount     = Airport::distinct('state_id')->count('state_id');

        // // For dropdown filters
        // $countries = Country::orderBy('name')->get();
        // $states    = State::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Airport data retrieved successfully.',
            'data' => $datas,
        ]);
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
            'name' => 'required|string|max:255|unique:airports,name',
            'code' => 'required|string|max:255|unique:airports,code',
            'state_id' => 'required',
            'country_id' => 'required'
        ]);

        // If validation fails
        if ($validator->fails()) {             
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        // Create airport
        $airport = Airport::updateOrCreate(
            [
                'country_id' => $request->country_id,
                'state_id'   => $request->state_id,
            ],
            [
                'name' => $request->name,
                'code' => $request->code,
            ]
        );

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Airport Data created successfully.',
                'data' => $airport
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Airport Data created successfully.',
            'data' => $airport
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
        $airport = Airport::findOrFail($id);
        if(empty($airport)){
            return response()->json([
                'success' => false,
                'message' => 'Airport Data Info Not Found!'
            ]);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:airports,name,' . $id,
            'code' => 'required|string|max:255|unique:airports,code,' . $id,
            'state_id' => 'required|string',
            'country_id' => 'required|string'
        ]);

        $airport->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'state_id' => $validated['state_id'],
            'country_id' => $validated['country_id']
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Airport Data updated successfully.',
                'data' => $airport
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Airport Data updated successfully.',
            'data' => $airport
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
            $airport = Airport::find($id);
            if ($airport) {                
                $airport->delete();   
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Airport Data Info Not Found!'
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
            'message' => 'Airport Data deleted successfully.'
        ]);
    }

}
