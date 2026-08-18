<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\ContractFileCategory;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ContractFileCategoryController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract file category|view all contract file category', only: ['index']),
            new Middleware('permission:create contract file category', only: ['store']),
            new Middleware('permission:edit contract file category', only: ['update']),
            new Middleware('permission:delete contract file category', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = ContractFileCategory::with('country')->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('required_documents', 'like', "%{$search}%")
                    ->orWhereHas('country', fn ($country) => $country->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        $datas = $query->paginate(30)->withQueryString();
        $activeCategories = ContractFileCategory::with('country')->where('status', 'active')->get();
        $topCategory = $activeCategories->sortByDesc('documents_count')->first();

        $stats = [
            'total_categories' => $activeCategories->count(),
            'top_category' => $topCategory,
            'avg_visa_rate' => (float) (ContractFileCategory::where('status', 'active')->avg('visa_rate') ?? 0),
            'countries_served' => ContractFileCategory::where('status', 'active')->whereNotNull('country_id')->distinct('country_id')->count('country_id'),
            'country_names' => Country::whereIn('id', ContractFileCategory::where('status', 'active')->whereNotNull('country_id')->distinct()->pluck('country_id'))
                ->orderBy('name')
                ->limit(3)
                ->pluck('name'),
        ];

        $countries = Country::orderBy('name')->get(['id', 'name']);

        return view('contract-file-categories.index', compact('datas', 'stats', 'countries'));
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            ContractFileCategory::create([
                'name' => $request->name,
                'code' => ContractFileCategory::makeCode($request->name),
                'country_id' => $request->country_id,
                'visa_rate' => $request->visa_rate ?? 0,
                'required_documents' => $request->required_documents,
                'status' => $request->status,
                'created_by' => Auth::id(),
            ]);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'File category created successfully.']);
    }

    public function update(Request $request, $role, string $id)
    {
        $category = ContractFileCategory::find($id);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Data Info Not Found!']);
        }

        $validator = $this->validator($request, $category->id);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            $category->update([
                'name' => $request->name,
                'country_id' => $request->country_id,
                'visa_rate' => $request->visa_rate ?? 0,
                'required_documents' => $request->required_documents,
                'status' => $request->status,
            ]);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'File category updated successfully.']);
    }

    public function destroy(Request $request, $role, string $id)
    {
        try {
            $category = ContractFileCategory::find($request->item_id ?? $id);
            if (!$category) {
                return response()->json(['success' => false, 'message' => 'Data Info Not Found!']);
            }

            $category->delete();
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'File category deleted successfully.']);
    }

    private function validator(Request $request, ?int $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'visa_rate' => 'required|numeric|min:0',
            'required_documents' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);
    }
}
