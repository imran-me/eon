<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PortalManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Fetch portal management data from the database
            $portals = Portal::with('vendor')->latest()->paginate(10); // You can change 10 to any number per page

            $accounts = Account::active()->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Portal management data retrieved successfully.',
                'data' => [
                    'portals' => $portals,
                    'accounts' => $accounts
                ]
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch portal management data.',
                'data' => []
            ], 500);
        }
    }

    public function updateBalance(Request $request)
    {
        $request->validate([
            'portal_id' => 'required|exists:portals,id', 
            'amount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $acc = Portal::findOrFail($request->portal_id);
            $amount = (float) $request->amount;
            $opening_balance = (float) $acc->balance;
            $new_balance = $opening_balance + $amount;
    
            PortalBalance::create([
                'portal_id' => $request->portal_id,
                'type' => 'add_balance',
                'old_balance' => $opening_balance,
                'debit' => $amount,
                'credit' => 0,
                'current_balance' => $new_balance,
                'remarks' => 'Add balance from portal management.',
                'created_by' => Auth::id(),
            ]);
    
            if ($acc) {
                $acc->update([
                    'balance' => $new_balance
                ]);
            }
            DB::commit();     
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => "Something went wrong! " . $th->getMessage(),
                'data' => []
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Balance updated successfully!",
            'data' => []
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $accounts = Account::active()->get();
            $vendors = User::whereHas('roles', fn($q) => $q->where('name', 'vendor'))->get();
            return view('dashboard.portal_management.create', compact('vendors', 'accounts'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to load create form.']);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $request->validate([
                'name' => 'required|unique:portals,name',
                'type' => 'required|in:flight,bus,train',
                // 'vendor_id' => 'nullable|exists:users,id',
            ]);

            Portal::create([
                'name' => $request->name,
                'api_key' => $request->api_key,
                'api_secret' => $request->api_secret,
                'base_url' => $request->base_url,
                'type' => $request->type,
                'account_id' => $request->account_id,
                'status' => $request->status ?? 'active',
                'created_by' => Auth::id(),
            ]);
            // Redirect to the index with success message
            return redirect()->back()->with('success', 'Portal created successfully.');
        } catch (\Exception $e) {            
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to create portal.']);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($role, string $id)
    {
        try {
            $vendors = User::whereHas('roles', fn($q) => $q->where('name', 'vendor'))->get();
            $accounts = Account::active()->get();
            $portal = Portal::findOrFail($id);
            // Return the view for editing the portal
            return view('dashboard.portal_management.edit', compact('portal', 'vendors', 'accounts'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to load edit form.']);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, string $id)
    {
        try {
            // Validate the request data
            $request->validate([
                'name' => 'required|unique:portals,name,' . $id,
                'type' => 'required|in:flight,bus,train',
                // 'vendor_id' => 'nullable|exists:users,id',
            ]);

            $portal = Portal::findOrFail($id);
            
            $portal->update($request->only([
                'name',
                'api_key',
                'api_secret',
                'base_url',
                'account_id',
                'type',
                'status'
            ]));

            // Redirect to the index with success message
            return redirect()->back()->with('success', 'Portal updated successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to update portal.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Find the portal by ID and delete it
            $portal = \App\Models\Portal::findOrFail($id);
            $portal->delete();

            // Redirect to the index with success message
            return redirect()->route('dashboard.portal-management.index')->with('success', 'Portal deleted successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to delete portal.']);
        }
    }
}
