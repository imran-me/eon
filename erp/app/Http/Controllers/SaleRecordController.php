<?php

namespace App\Http\Controllers;

use App\Models\SalaryTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaleRecordController extends Controller
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

        return view('salary-templates.index', compact(
            'datas'
        ));
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('salary-templates.create-modal');
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
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
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
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.salary-templates.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('salary-templates.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $role)
    {
        $data = SalaryTemplate::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255|unique:salary_templates,name,' . $data->id
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
            'message' => 'Data updated successfully.',
            'data' => $data,
        ]);
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
            $data = SalaryTemplate::find($request->item_id);
            if ($data) {
                $data->delete();
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

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);        
    }
}
