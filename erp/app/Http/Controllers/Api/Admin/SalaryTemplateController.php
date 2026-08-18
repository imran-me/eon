<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalaryTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SalaryTemplateController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        
        $query = SalaryTemplate::query()->orderBy('name', 'asc');
        if ($request->has('name') && !empty($request->name)) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }        
        $datas = $query->paginate(30);

        return response()->json([
            'success' => true,
            'message' => 'Salary templates retrieved successfully.',
            'data' => $datas
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
            'name'  => 'required|string|max:255|unique:salary_templates,name'
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ]);
        }

        // Create
        $data = SalaryTemplate::updateOrCreate([
            'name' => $request->name
        ],[
            'basic_salary' => $request->basic_salary,
            'house_rent' => $request->house_rent ?? 0,
            'medical_allowance' => $request->medical_allowance ?? 0,
            'conveyance_allowance' => $request->conveyance_allowance ?? 0,
            'other_allowance' => $request->other_allowance ?? 0,
            'bonus' => $request->bonus ?? 0,
            'total_salary' => $request->total_salary
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Salary template created successfully.',
                'data' => $data
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Salary template created successfully.',
            'data' => $data
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
        $data = SalaryTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255|unique:salary_templates,name,' . $data->id
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }
        try {
    
            $data->update([
                'name'    => $request->name,
                'basic_salary' => $request->basic_salary,
                'house_rent' => $request->house_rent,
                'medical_allowance' => $request->medical_allowance,
                'conveyance_allowance' => $request->conveyance_allowance,
                'other_allowance' => $request->other_allowance,
                'bonus' => $request->bonus,
                'total_salary' => $request->total_salary
            ]);
            
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);    
        }

        return response()->json([
            'success' => true,
            'message' => 'Salary template updated successfully.',
            'data' => $data,
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
            $data = SalaryTemplate::find($id);
            if ($data) {
                $data->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Salary template not found!'
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
            'message' => 'Salary template deleted successfully.'
        ]);        
    }
    public function getSalary($role, $id)
    {
        $data = SalaryTemplate::find($id);
        if ($data) {
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Salary template not found!'
            ]);
        }
    }
}
