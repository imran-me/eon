<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadSourceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = LeadSource::query()->orderBy('name', 'asc');
        if ($request->has('name') && !empty($request->name)) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }        
        $datas = $query->paginate(30);

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully.',
            'data' => $datas
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255|unique:lead_sources,name'
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'error' => $validator->errors()
            ]);
        }

        // Create
        $data = LeadSource::updateOrCreate([
            'name' => $request->name
        ],[
            'is_active' => $request->is_active ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead source created successfully.',
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = LeadSource::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255|unique:lead_sources,name,' . $data->id
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'error' => $validator->errors()
            ]);
        }
        try {
    
            $data->update([
                'name'    => $request->name,
                'is_active' => isset($request->is_active) ? 1 : 0,
            ]);
            
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);    
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead source updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $data = LeadSource::find($id);
        if ($data) {
            $data->delete();
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Lead source not found!'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead source deleted successfully.'
        ]);
    }
}
