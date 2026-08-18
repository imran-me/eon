<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\FlightCategoryType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FlightCategoryTypeController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view flight category|view all flight category', only: ['index']),
            new Middleware('permission:create flight category', only: ['store']),
            new Middleware('permission:edit flight category', only: ['update']),
            new Middleware('permission:delete flight category', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = FlightCategoryType::withCount('flightCategories')->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        $datas = $query->paginate(30)->withQueryString();
        $stats = [
            'total' => FlightCategoryType::count(),
            'active' => FlightCategoryType::where('status', 'active')->count(),
            'avg_fare' => (float) (FlightCategoryType::avg('base_fare') ?? 0),
            'used' => FlightCategoryType::has('flightCategories')->count(),
        ];

        return view('flight-category-types.index', compact('datas', 'stats'));
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $type = FlightCategoryType::create($validator->validated());

        return response()->json(['success' => true, 'message' => 'Category type created successfully.', 'data' => $type]);
    }

    public function update(Request $request, $role, string $id)
    {
        $type = FlightCategoryType::find($id);
        if (!$type) {
            return response()->json(['success' => false, 'message' => 'Category type not found.']);
        }

        $validator = $this->validator($request, $type->id);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $type->update($validator->validated());

        return response()->json(['success' => true, 'message' => 'Category type updated successfully.']);
    }

    public function destroy(Request $request, $role, string $id)
    {
        $type = FlightCategoryType::find($request->item_id ?? $id);
        if (!$type) {
            return response()->json(['success' => false, 'message' => 'Category type not found.']);
        }

        $type->delete();

        return response()->json(['success' => true, 'message' => 'Category type deleted successfully.']);
    }

    private function validator(Request $request, ?int $id = null)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('flight_category_types', 'name')->ignore($id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('flight_category_types', 'code')->ignore($id)],
            'base_fare' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);
    }
}
