<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use App\Models\FlightCategory;
use App\Models\FlightCategoryType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FlightCategoryController implements HasMiddleware
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

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = FlightCategory::with(['airlines', 'categoryType'])->orderBy('name', 'asc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('typical_routes', 'like', "%{$search}%")
                  ->orWhereHas('categoryType', fn($type) => $type->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('airlines', fn($a) => $a->where('name', 'like', "%{$search}%"));
            });
        }

        $datas = $query->paginate(30)->withQueryString();

        $stats = [
            'total_categories' => FlightCategory::where('status', 'active')->count(),
            'top_category'     => FlightCategory::orderByDesc('default_seats')->first(),
            'avg_seats'        => (int) round(FlightCategory::avg('default_seats') ?? 0),
            'airline_partners' => DB::table('flight_category_airline')->distinct('airline_id')->count('airline_id'),
            'partner_names'    => Airline::whereIn('id', DB::table('flight_category_airline')->distinct()->pluck('airline_id'))
                ->orderBy('name')->limit(3)->pluck('name'),
        ];

        $airlines = Airline::orderBy('name')->get();
        $categoryTypes = FlightCategoryType::where('status', 'active')->orderBy('name')->get();

        return view('flight-categories.index', compact('datas', 'stats', 'airlines', 'categoryTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255|unique:flight_categories,name',
            'flight_category_type_id' => 'nullable|exists:flight_category_types,id',
            'code'           => 'nullable|string|max:50|unique:flight_categories,code',
            'icon'           => 'nullable|string|max:50',
            'typical_routes' => 'nullable|string|max:255',
            'default_seats'  => 'nullable|integer|min:0',
            'base_fare'      => 'nullable|numeric|min:0',
            'status'         => 'required|in:active,seasonal,inactive',
            'airline_ids'    => 'nullable|array',
            'airline_ids.*'  => 'exists:airlines,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::transaction(function () use ($request) {
                $category = FlightCategory::create([
                    'name'           => $request->name,
                    'flight_category_type_id' => $request->flight_category_type_id,
                    'code'           => $request->code,
                    'icon'           => $request->icon,
                    'typical_routes' => $request->typical_routes,
                    'default_seats'  => $request->default_seats ?? 0,
                    'base_fare'      => $request->base_fare ?? 0,
                    'status'         => $request->status,
                ]);

                $category->airlines()->sync($request->airline_ids ?? []);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Flight category created successfully.',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, string $id)
    {
        $data = FlightCategory::find($id);
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255|unique:flight_categories,name,' . $data->id,
            'flight_category_type_id' => 'nullable|exists:flight_category_types,id',
            'code'           => 'nullable|string|max:50|unique:flight_categories,code,' . $data->id,
            'icon'           => 'nullable|string|max:50',
            'typical_routes' => 'nullable|string|max:255',
            'default_seats'  => 'nullable|integer|min:0',
            'base_fare'      => 'nullable|numeric|min:0',
            'status'         => 'required|in:active,seasonal,inactive',
            'airline_ids'    => 'nullable|array',
            'airline_ids.*'  => 'exists:airlines,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::transaction(function () use ($data, $request) {
                $data->update([
                    'name'           => $request->name,
                    'flight_category_type_id' => $request->flight_category_type_id,
                    'code'           => $request->code,
                    'icon'           => $request->icon,
                    'typical_routes' => $request->typical_routes,
                    'default_seats'  => $request->default_seats ?? 0,
                    'base_fare'      => $request->base_fare ?? 0,
                    'status'         => $request->status,
                ]);

                $data->airlines()->sync($request->airline_ids ?? []);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Flight category updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $role, string $id)
    {
        try {
            $item = FlightCategory::find($request->item_id ?? $id);
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!',
                ]);
            }

            $item->delete();
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Flight category deleted successfully.',
        ]);
    }
}
