<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Log\VendorLog;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\ExportsPartyList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class VendorController implements HasMiddleware
{
    use ExportsPartyList;

    protected function partyExportMeta(): array
    {
        return ['role' => 'vendor', 'prefix' => 'EP-VN-', 'label' => 'Vendor'];
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view vendor|view all vendor', only: ['index', 'exportExcel', 'exportPdf']),
            new Middleware('permission:create vendor', only: ['create', 'store']),
            new Middleware('permission:edit vendor', only: ['edit', 'update']),
            new Middleware('permission:delete vendor', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = User::query();

            // Filter by name, email, or phone
            if ($request->filled('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%');
                });
            }

            // Only vendors (assuming you use Spatie Roles)
            $query->whereHas('roles', function ($q) {
                $q->where('name', 'vendor');
            });

            $vendors = $query->latest()->paginate(10);

            $balances = $this->latestBalances($vendors->pluck('id'));

            // Return the view with vendors data
            return view('dashboard.vendors.index', compact('vendors', 'balances'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to fetch vendors.']);
        }
    }

    /**
     * Each party's current running balance is simply the balance on their
     * most recently inserted ledger transaction (chained, cumulative).
     */
    protected function latestBalances($userIds)
    {
        $latestIds = Transaction::whereIn('user_id', $userIds)
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_id')
            ->pluck('id');

        return Transaction::whereIn('id', $latestIds)->get()->keyBy('user_id');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            // Return the view for creating a new vendor

            return view('dashboard.vendors.create');
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
            // Validate the form data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'password' => 'required|string|min:6|confirmed',
                // 'role' => 'required|exists:roles,name',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Please fix the validation errors.')->withErrors($validator)->withInput();
            }

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'company_id' => 2,
                'contact_person' => $request->contact_person,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'password' => Hash::make($request->password??'1234568'),
            ]);

            // Assign role
           $user->assignRole('vendor'); // ✅ Direct by name

            return redirect()->back()->with('success', 'User created successfully.');

        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->with('error', 'Please fix the validation errors.')->withErrors(['error' => 'Failed to create vendor.'])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($_role, string $id)
    {
        try {
            // Find the vendor by ID
            $vendor = User::findOrFail($id);

            // Return the view for editing the vendor
            return view('dashboard.vendors.edit', compact('vendor'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to load edit form.']);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $role, User $vendor)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'required|email|unique:users,email,' . $vendor->id,
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'password' => 'nullable|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Please fix the validation errors.')->withErrors($validator)->withInput();
            }

            $before = $vendor->only(['name', 'email', 'phone', 'address']);

            // Update fields
            $vendor->name = $request->name;
                $vendor->company_id = 2;
            $vendor->contact_person = $request->contact_person;
            $vendor->email = $request->email;
            $vendor->phone = $request->phone;
            $vendor->address = $request->address;

            // If password is set, update it
            if ($request->filled('password')) {
                $vendor->password = Hash::make($request->password);
            }

            $vendor->save();

            $after = $vendor->only(['name', 'email', 'phone', 'address']);

            // Log the changes
            VendorLog::create([
                'vendor_id' => $vendor->id,
                'changed_by' => auth()->id(),
                'action' => 'updated',
                'before' => $before,
                'after' => $after,
            ]);

            return redirect()->back()->with('success', 'Vendor updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->withErrors(['error' => 'Failed to update vendor.'])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($_role, string $id)
    {
        try {
            $vendor = User::findOrFail($id);
            $vendor->delete();
            return redirect()->back()->with('success', 'Vendor deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete vendor.');
        }
    }

    public function restore($_role, int $logId)
    {
        $log = VendorLog::findOrFail($logId);
        $vendor = User::findOrFail($log->vendor_id);

        $vendor->update($log->before);

        return back()->with('success', 'Vendor restored to selected version.');
    }

    public function toggleStatus($_role, $id)
    {
        $vendor = User::findOrFail($id);

        $vendor->status = $vendor->status === 'active' ? 'inactive' : 'active';
        $vendor->save();

        return back()->with('success', 'Vendor status updated successfully.');
    }
}
