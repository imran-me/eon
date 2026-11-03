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

        // The list bar's date range: the day the airline was added.
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
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
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $airline = Airline::create([
            'name' => $validated['name'],
            'status' => $request->boolean('status'),
        ]);
        // Ticket Entry's import modal adds a missing airline in place (owner, 2026-10-07);
        // the Airlines screen itself still posts a form and gets the redirect below
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'airline' => ['id' => $airline->id, 'name' => $airline->name]]);
        }
        if ($request->hasFile('logo') && ! $airline->storeLogo($request->file('logo'))) {
            return redirect()->route('role.airlines.edit', ['role' => request()->route('role'), 'airline' => $airline->id])
                ->with('error', 'Airline saved, but the logo could not be read — try a PNG or JPG.');
        }

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
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $airline->update([
            'name' => $validated['name'],
            'status' => $request->boolean('status'),
        ]);
        if ($request->hasFile('logo')) {
            if (! $airline->storeLogo($request->file('logo'))) {
                return back()->with('error', 'The logo could not be read — try a PNG or JPG.');
            }
        } elseif ($request->boolean('remove_logo')) {
            $airline->removeLogo();
        }

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
