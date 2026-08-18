<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\VisaCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VisaCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = VisaCategory::with('country')->orderBy('name', 'asc');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        $datas = $query->paginate(20);
        $countries = Country::orderBy('name')->get();

        return view('visa-category.index', compact('datas', 'countries'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('visa-category.create-modal');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'name'               => 'required|string|max:255',
            'country_id'         => 'nullable|exists:countries,id',
            'visa_type'          => 'nullable|string|max:100',
            'costing_price'       => 'required|numeric|min:0',
            'sale_price'          => 'required|numeric|min:0',
            'avg_processing_days'=> 'nullable|string|max:50',
            'description'        => 'nullable|string',
            'is_active'          => 'required|boolean',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = VisaCategory::create([
            'name'                => $request->name,
            'country_id'          => $request->country_id,
            'visa_type'           => $request->visa_type,
            'costing_price'        => $request->costing_price,
            'sale_price'           => $request->sale_price,
            'avg_processing_days' => $request->avg_processing_days,
            'description'         => $request->description,
            'is_active'           => $request->boolean('is_active'),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data,
            ]);
        }

        return redirect()->route('role.visa-category.index', ['role' => $role])
            ->with('success', 'Visa category created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return view('visa-category.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, string $id)
    {
        $visaCategory = VisaCategory::findOrFail($request->id ?? $id);

        $validator = Validator::make($request->all(), [
            'name'               => 'required|string|max:255',
            'country_id'         => 'nullable|exists:countries,id',
            'visa_type'          => 'nullable|string|max:100',
            'costing_price'       => 'required|numeric|min:0',
            'sale_price'          => 'required|numeric|min:0',
            'avg_processing_days'=> 'nullable|string|max:50',
            'description'        => 'nullable|string',
            'is_active'          => 'required|boolean',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $visaCategory->update([
            'name'                => $request->name,
            'country_id'          => $request->country_id,
            'visa_type'           => $request->visa_type,
            'costing_price'        => $request->costing_price,
            'sale_price'           => $request->sale_price,
            'avg_processing_days' => $request->avg_processing_days,
            'description'         => $request->description,
            'is_active'           => $request->boolean('is_active'),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $visaCategory,
            ]);
        }

        return redirect()->route('role.visa-category.index', ['role' => $role])
            ->with('success', 'Visa category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $role, string $id)
    {
        $visaCategory = VisaCategory::find($request->item_id ?? $id);
        if (!$visaCategory) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }

            return redirect()->route('role.visa-category.index', ['role' => $role])
                ->with('error', 'Data Info Not Found!');
        }

        $visaCategory->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data deleted successfully.',
            ]);
        }

        return redirect()->route('role.visa-category.index', ['role' => $role])
            ->with('success', 'Visa category deleted successfully.');
    }
}
