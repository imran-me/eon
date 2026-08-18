<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Models\State;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
            $query->where(function ($search) use ($request) {
                $search->where('airports.name', 'like', '%' . $request->name_code . '%')
                    ->orWhere('airports.code', 'like', '%' . $request->name_code . '%');
            });
        }

        // Paginate
        $datas = $query->paginate(30);

        // Stats
        $totalAirports   = Airport::count();
        $countriesCount  = Airport::distinct('country_id')->count('country_id');
        $statesCount     = Airport::distinct('state_id')->count('state_id');

        // For dropdown filters
        $countries = Country::orderBy('name')->get();
        $states    = State::orderBy('name')->get();

        return view('airport.index', compact(
            'datas',
            'request_states',
            'countries',
            'states',
            'totalAirports',
            'countriesCount',
            'statesCount'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('airport.create-modal');
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
            'country_id' => ['required', 'exists:countries,id'],
            'state_id' => ['required', Rule::exists('states', 'id')->where('country_id', $request->country_id)]
        ]);

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {                
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Create airport
        $airport = Airport::create([
            'country_id' => $request->country_id,
            'state_id' => $request->state_id,
            'name' => $request->name,
            'code' => strtoupper($request->code),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $airport
            ]);
        }

        return redirect()->route('role.airport.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('airport.edit-modal', compact('id'));
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
        $id = $request->id;
        $airport = Airport::findOrFail($id);
        if(empty($airport)){
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:airports,name,' . $id,
            'code' => 'required|string|max:255|unique:airports,code,' . $id,
            'country_id' => ['required', 'exists:countries,id'],
            'state_id' => ['required', Rule::exists('states', 'id')->where('country_id', $request->country_id)]
        ]);

        $airport->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'state_id' => $validated['state_id'],
            'country_id' => $validated['country_id']
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $airport
            ]);
        }

        return redirect('/super-admin/airport')->with('success', 'Data updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {        
        try {     
            $airport = Airport::find($request->airport_id);
            if ($airport) {                
                $airport->delete();   
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

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data deleted successfully.'
            ]);
        }

        return redirect('/super-admin/airport')->with('success', 'Data deleted successfully.');
    }

}
