<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PartyType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PartyTypeController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view account|view all account', only: ['index']),
            new Middleware('permission:create account', only: ['store']),
            new Middleware('permission:edit account', only: ['update']),
            new Middleware('permission:delete account', only: ['destroy']),
        ];
    }

    private array $modelOptions = [
        ''                      => '— None (Free text / Others) —',
        'App\Models\Customer'   => 'Customer',
        'App\Models\Supplier'   => 'Supplier',
    ];

    public function index(Request $request)
    {
        $user        = Auth::user();
        $isSuperAdmin = $user?->is_super_admin;
        $userCompanyId = $user?->company_id;

        // Super admin: filter by selected company or show all
        // Regular user: always scoped to own company
        $filterCompanyId = $isSuperAdmin
            ? ($request->filled('company_id') ? $request->company_id : null)
            : $userCompanyId;

        $query = PartyType::with('company')
            ->when($filterCompanyId, fn($q) => $q->where('company_id', $filterCompanyId))
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('company_id')
            ->orderBy('id');

        $partyTypes   = $query->paginate(20)->withQueryString();
        $companies    = Company::orderBy('name')->get();
        $modelOptions = $this->modelOptions;

        return view('party-types.index', compact(
            'partyTypes', 'companies', 'modelOptions', 'isSuperAdmin', 'filterCompanyId'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'model_class' => 'nullable|string|max:200',
            'company_id'  => 'nullable|exists:companies,id',
        ]);

        $companyId = $request->company_id ?? Auth::user()?->company_id;
        $slug      = Str::slug($request->name, '_');

        $exists = PartyType::where('company_id', $companyId)->where('slug', $slug)->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'A party type with this name already exists.']);
        }

        $data = PartyType::create([
            'company_id'  => $companyId,
            'name'        => $request->name,
            'slug'        => $slug,
            'model_class' => $request->model_class ?: null,
        ]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Party type created successfully.', 'data' => $data]);
        }

        return back()->with('success', 'Party type created successfully.');
    }

    public function update(Request $request, $id)
    {
        $partyType = PartyType::findOrFail($request->id ?? $id);

        $request->validate([
            'name'        => 'required|string|max:100',
            'model_class' => 'nullable|string|max:200',
            'company_id'  => 'nullable|exists:companies,id',
        ]);

        $slug = Str::slug($request->name, '_');

        $exists = PartyType::where('company_id', $partyType->company_id)
            ->where('slug', $slug)
            ->where('id', '!=', $partyType->id)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'A party type with this name already exists.']);
        }

        $partyType->update([
            'name'        => $request->name,
            'slug'        => $slug,
            'model_class' => $request->model_class ?: null,
        ]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Party type updated successfully.', 'data' => $partyType]);
        }

        return back()->with('success', 'Party type updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        try {
            $partyType = PartyType::find($request->item_id ?? $id);

            if (!$partyType) {
                return response()->json(['success' => false, 'message' => 'Party type not found.']);
            }

            $partyType->delete();
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Party type deleted successfully.']);
    }
}
