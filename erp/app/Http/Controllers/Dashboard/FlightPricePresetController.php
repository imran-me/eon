<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Airline;
use App\Models\FlightCategory;
use App\Models\FlightCategoryType;
use App\Models\FlightPricePreset;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;

class FlightPricePresetController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view flight category|view all flight category', only: ['index']),
            new Middleware('permission:view flight category|view all flight category|create contract flight|create flight schedule', only: ['match']),
            new Middleware('permission:create flight category', only: ['store']),
            new Middleware('permission:edit flight category', only: ['update']),
            new Middleware('permission:delete flight category', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = FlightPricePreset::with(['airline', 'flightCategory', 'categoryType'])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->whereHas('airline', fn ($airline) => $airline->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('flightCategory', fn ($category) => $category->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('categoryType', fn ($type) => $type->where('name', 'like', "%{$search}%"));
            });
        }

        $datas = $query->paginate(30)->withQueryString();
        $airlines = Airline::where('status', 1)->orderBy('name')->get();
        $categories = FlightCategory::orderBy('name')->get();
        $types = FlightCategoryType::where('status', 'active')->orderBy('name')->get();

        return view('flight-price-presets.index', compact('datas', 'airlines', 'categories', 'types'));
    }

    public function match(Request $request)
    {
        $request->validate([
            'airline_id' => 'required|exists:airlines,id',
            'flight_category_id' => 'nullable|exists:flight_categories,id',
            'flight_category_type_id' => 'nullable|exists:flight_category_types,id',
            'ticket_class' => 'required|in:economy,business,first',
            'handling_type' => 'required|in:manpower_wise,immigration_wise',
        ]);

        $query = FlightPricePreset::where('status', 'active')
            ->where('airline_id', $request->airline_id)
            ->where('ticket_class', $request->ticket_class)
            ->where('handling_type', $request->handling_type)
            ->where(function ($builder) use ($request) {
                $builder->whereNull('flight_category_id');
                if ($request->flight_category_id) {
                    $builder->orWhere('flight_category_id', $request->flight_category_id);
                }
            })
            ->where(function ($builder) use ($request) {
                $builder->whereNull('flight_category_type_id');
                if ($request->flight_category_type_id) {
                    $builder->orWhere('flight_category_type_id', $request->flight_category_type_id);
                }
            });

        $preset = $query
            ->orderByRaw('flight_category_type_id = ? desc', [$request->flight_category_type_id])
            ->orderByRaw('flight_category_id = ? desc', [$request->flight_category_id])
            ->first();

        return response()->json([
            'success' => (bool) $preset,
            'data' => $preset,
            'message' => $preset ? 'Pricing preset loaded.' : 'No active pricing preset matched.',
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $data = FlightPricePreset::create($validator->validated());

        return response()->json(['success' => true, 'message' => 'Price preset created successfully.', 'data' => $data]);
    }

    public function update(Request $request, $role, string $id)
    {
        $data = FlightPricePreset::find($id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Price preset not found.']);
        }

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $data->update($validator->validated());

        return response()->json(['success' => true, 'message' => 'Price preset updated successfully.']);
    }

    public function destroy(Request $request, $role, string $id)
    {
        $data = FlightPricePreset::find($request->item_id ?? $id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Price preset not found.']);
        }

        $data->delete();

        return response()->json(['success' => true, 'message' => 'Price preset deleted successfully.']);
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'airline_id' => 'required|exists:airlines,id',
            'flight_category_id' => 'nullable|exists:flight_categories,id',
            'flight_category_type_id' => 'nullable|exists:flight_category_types,id',
            'ticket_class' => 'required|in:economy,business,first',
            'handling_type' => 'required|in:manpower_wise,immigration_wise',
            'ticket_cost' => 'required|numeric|min:0',
            'manpower_cost' => 'required|numeric|min:0',
            'boarding_cost' => 'required|numeric|min:0',
            'immigration_cost' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);
    }
}
