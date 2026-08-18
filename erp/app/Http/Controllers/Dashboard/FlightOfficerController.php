<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Airline;
use App\Models\FlightOfficer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FlightOfficerController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract flight|view all contract flight', only: ['index']),
            new Middleware('permission:create contract flight', only: ['store']),
            new Middleware('permission:edit contract flight', only: ['update']),
            new Middleware('permission:delete contract flight', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = FlightOfficer::with(['user', 'airline'])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->whereHas('user', function ($user) use ($search) {
                    $user->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })->orWhereHas('airline', fn ($airline) => $airline->where('name', 'like', "%{$search}%"));
            });
        }

        $datas = $query->paginate(30)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name', 'phone']);
        $airlines = Airline::where('status', 1)->orderBy('name')->get();
        $stats = [
            'total' => FlightOfficer::count(),
            'boarding' => FlightOfficer::whereJsonContains('work_roles', 'boarding')->count(),
            'immigration' => FlightOfficer::whereJsonContains('work_roles', 'immigration')->count(),
            'active' => FlightOfficer::where('status', 'active')->count(),
        ];

        return view('flight-officers.index', compact('datas', 'users', 'airlines', 'stats'));
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $data = FlightOfficer::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Officer profile created successfully.',
            'data' => $data->load(['user', 'airline']),
        ]);
    }

    public function update(Request $request, $role, string $id)
    {
        $data = FlightOfficer::find($id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Officer profile not found.']);
        }

        $validator = $this->validator($request, $data->id);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $data->update($validator->validated());

        return response()->json(['success' => true, 'message' => 'Officer profile updated successfully.']);
    }

    public function destroy(Request $request, $role, string $id)
    {
        $data = FlightOfficer::find($request->item_id ?? $id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Officer profile not found.']);
        }

        $data->delete();

        return response()->json(['success' => true, 'message' => 'Officer profile deleted successfully.']);
    }

    private function validator(Request $request, ?int $id = null)
    {
        return Validator::make($request->all(), [
            'user_id' => [
                'required',
                'exists:users,id',
                Rule::unique('flight_officers', 'user_id')->ignore($id),
            ],
            'airline_id' => 'nullable|exists:airlines,id',
            'work_roles' => 'required|array|min:1',
            'work_roles.*' => 'in:boarding,immigration,offload',
            'contact' => 'nullable|string|max:50',
            'experience' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive',
        ]);
    }
}
