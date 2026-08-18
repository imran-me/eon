<?php

namespace App\Http\Controllers;

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
        $countries = Country::get();
        $activeStates = State::where('status', true)->count();
        $inactiveStates = State::where('status', false)->count();
        $countriesCount = State::distinct('country_id')->count('country_id');

        return view('states.index', compact(
            'states',
            'countries',
            'totalStates',
            'activeStates',
            'inactiveStates',
            'countriesCount'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('states.create-modal');
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
            'country_id' => 'required|exists:countries,id',
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

        // Create state
        $state = State::create([
            'name' => $request->name,
            'country_id' => $request->country_id,
            'status' => $request->boolean('state_status')
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'State created successfully.',
                'data' => $state
            ]);
        }

        return redirect()->route('role.states.index')->with('success', 'State created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('states.edit-modal', compact('id'));
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
        $state = State::findOrFail($id);
        if(empty($state)){
            return response()->json([
                'success' => false,
                'message' => 'State Info Not Found!'
            ]);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:states,name,' . $id,
            'country_id' => 'required|exists:countries,id'
        ]);

        $state->update([
            'name' => $validated['name'],
            'country_id' => $validated['country_id'],
            'status' => $request->boolean('state_status')
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'State updated successfully.',
                'data' => $state
            ]);
        }

        return redirect('/super-admin/states')->with('success', 'State updated successfully.');
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
            $state = State::find($request->state_id);
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

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'State deleted successfully.'
            ]);
        }

        return redirect('/super-admin/states')->with('success', 'State deleted successfully.');
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
