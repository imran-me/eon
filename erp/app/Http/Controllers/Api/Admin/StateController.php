<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Models\State;
use Illuminate\Support\Facades\Validator;

class StateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = State::select('states.*')
            ->join('countries', 'countries.id', '=', 'states.country_id')
            ->orderBy('countries.name', 'asc')
            ->orderBy('states.name', 'asc');

        // Apply filters if provided
        if ($request->has('country_id') && !empty($request->country_id)) {
            $query->where('states.country_id', $request->country_id);
        }

        if ($request->has('status') && $request->status !== null) {
            $query->where('states.status', $request->status);
        }

        if ($request->has('state_name') && !empty($request->state_name)) {
            $query->where('states.name', 'like', '%' . $request->state_name . '%');
        }

        $states = $query->paginate(30);

        $totalStates = State::count();
        // $countries = Country::get();
        // $activeStates = State::where('status', true)->count();
        // $inactiveStates = State::where('status', false)->count();
        // $countriesCount = State::distinct('country_id')->count('country_id');

        return response()->json([
            'success' => true,
            'message' => 'States retrieved successfully.',
            'data' => $states,
            'total_states' => $totalStates,
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
            'name' => 'required|string|max:255|unique:states,name',
            'country_id' => 'required',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create state
        $state = State::create([
            'name' => $request->name,
            'country_id' => $request->country_id,
            'status' => $request->has('status') ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'State created successfully.',
            'data' => $state
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
        $state = State::findOrFail($id);
        if(empty($state)){
            return response()->json([
                'success' => false,
                'message' => 'State Info Not Found!'
            ]);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:states,name,' . $id,
            'country_id' => 'required|string'
        ]);

        $state->update([
            'name' => $validated['name'],
            'country_id' => $validated['country_id'],
            'status' => $request->has('state_status') ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'State updated successfully.',
            'data' => $state
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
            $state = State::find($id);
            if ($state) {                
                $state->delete();   
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'State Info Not Found!'
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
            'message' => 'State deleted successfully.'
        ]);

    }

    
    public function getStatesByCountry(Request $request)
    {
        try {
            $data = State::where('country_id', $request->country_id)->orderBy('name', 'ASC')->get();
            
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => 'Data Found Successfully.'
        ]);        
    }
}
