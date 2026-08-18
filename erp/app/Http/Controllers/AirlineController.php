<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use Illuminate\Http\Request;

class AirlineController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view airline|view all airline')->only('index');
        $this->middleware('permission:create airline')->only(['create', 'store']);
        $this->middleware('permission:edit airline')->only(['edit', 'update']);
        $this->middleware('permission:delete airline')->only('destroy');
    }

    public function index(Request $request)
    {
        $query = Airline::query()->orderBy('name');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $airlines = $query->paginate(20)->withQueryString();
        $role = request()->route('role');

        return view('airlines.index', compact('airlines', 'role'));
    }

    public function create()
    {
        $role = request()->route('role');

        return view('airlines.create', compact('role'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:airlines,name'],
            'status' => ['nullable', 'boolean'],
        ]);

        Airline::create([
            'name' => $validated['name'],
            'status' => $request->boolean('status'),
        ]);

        return redirect()
            ->route('role.airlines.index', ['role' => request()->route('role')])
            ->with('success', 'Airline created successfully.');
    }

    public function edit(string $role, Airline $airline)
    {
        return view('airlines.edit', compact('airline', 'role'));
    }

    public function update(Request $request, string $role, Airline $airline)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:airlines,name,' . $airline->id],
            'status' => ['nullable', 'boolean'],
        ]);

        $airline->update([
            'name' => $validated['name'],
            'status' => $request->boolean('status'),
        ]);

        return redirect()
            ->route('role.airlines.index', ['role' => $role])
            ->with('success', 'Airline updated successfully.');
    }

    public function destroy(string $role, Airline $airline)
    {
        $airline->delete();

        return redirect()
            ->route('role.airlines.index', ['role' => $role])
            ->with('success', 'Airline deleted successfully.');
    }
}
