<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProjectCategoryController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view lead project category|view all lead project category', only: ['index']),
            new Middleware('permission:create lead project category', only: ['store']),
            new Middleware('permission:edit lead project category', only: ['update']),
            new Middleware('permission:delete lead project category', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProjectCategory::with('company')->orderBy('name', 'asc');
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        $datas = $query->paginate(30);
        $companies = Company::orderBy('name')->get();

        return view('project-categories.index', compact('datas', 'companies'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $companyId = $request->company_id ?? auth()->user()->company_id ?? 1;

        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'       => 'required|string|max:255|unique:project_categories,name,NULL,id,company_id,' . $companyId,
        ]);

        ProjectCategory::create([
            'company_id' => $companyId,
            'name'       => $request->name,
            'is_active'  => $request->is_active ? 1 : 0,
        ]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Data created successfully.']);
        }

        return redirect()->back()->with('success', 'Data created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = ProjectCategory::findOrFail($request->id);
        $companyId = $request->company_id ?? $data->company_id;

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'name'       => 'required|string|max:255|unique:project_categories,name,' . $data->id . ',id,company_id,' . $companyId,
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            $data->update([
                'company_id' => $companyId,
                'name'       => $request->name,
                'is_active'  => $request->is_active ? 1 : 0,
            ]);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Data updated successfully.', 'data' => $data]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $item = ProjectCategory::find($request->item_id);
            if ($item) {
                $item->delete();
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
