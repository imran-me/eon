<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\ExpenseDepartment;
use App\Models\Log\VendorLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartyAssignmentService;
use App\Services\VendorClassificationService;
use App\Traits\ExportsPartyList;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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
            new Middleware('permission:view vendor|view all vendor', only: ['index', 'print']),
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
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->search.'%')
                        ->orWhere('email', 'like', '%'.$request->search.'%')
                        ->orWhere('phone', 'like', '%'.$request->search.'%');
                });
            }

            if ($request->filled('service_category')) {
                $query->whereJsonContains('service_categories', $request->service_category);
            }

            if (VendorClassificationService::installed() && $request->filled('vendor_type_id')) {
                $query->whereHas('vendorProfile', fn ($q) => $q->where('vendor_type_id', $request->vendor_type_id));
            }
            if (VendorClassificationService::installed() && $request->filled('vendor_category_id')) {
                $query->whereHas('vendorCategories', fn ($q) => $q->whereKey($request->vendor_category_id));
            }

            // Only vendors (assuming you use Spatie Roles)
            $query->whereHas('roles', function ($q) {
                $q->where('name', 'vendor');
            });

            $query->when(VendorClassificationService::installed(), fn ($q) => $q->with(['vendorProfile.type', 'vendorCategories']));
            $vendors = $query->latest()->paginate(10)->withQueryString();

            $balances = $this->latestBalances($vendors->pluck('id'));

            // Return the view with vendors data
            return view('dashboard.vendors.index', compact('vendors', 'balances') + VendorClassificationService::formData());
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

            return view('dashboard.vendors.create', PartyAssignmentService::formData(null, 'vendor') + VendorClassificationService::formData() + [
                'returnContext' => $this->returnContext(),
            ]);
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
            $validator = Validator::make($request->all(), array_merge([
                'name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'service_categories' => in_array(2, array_map('intval', $request->input('company_ids', [])), true) ? 'required|array|min:1' : 'nullable|array',
                'service_categories.*' => 'required|in:ticket,visa,flight,file,instructor,others|distinct',
                'origin_company' => 'nullable|integer',
                'origin_project' => 'nullable|integer',
                // 'role' => 'required|exists:roles,name',
            ], PartyAssignmentService::rules(), VendorClassificationService::rules($request)));

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Please fix the validation errors.')->withErrors($validator)->withInput();
            }

            // Resolve before writing. A redirect-building failure must never be
            // reported as a failed save after the vendor was already committed.
            $returnContext = $this->returnContext($request);

            DB::beginTransaction();

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'company_id' => null,
                'contact_person' => $request->contact_person,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'service_categories' => $request->service_categories,
                'password' => Hash::make(Str::random(40)),
            ]);

            // Assign role
            $user->assignRole('vendor'); // ✅ Direct by name
            PartyAssignmentService::sync($user, 'vendor', $request->company_ids, $request->project_ids ?? []);
            VendorClassificationService::sync($user, $request->integer('vendor_type_id') ?: null, $request->input('vendor_category_ids', []));
            DB::commit();

            return redirect($returnContext['url'])->with('success', 'Vendor created and assigned successfully.');

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

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
     * A create form opened from Project Accounts returns to that exact project.
     * The destination is reconstructed from known company/project records rather
     * than accepting a free-form return URL (which would allow open redirects).
     */
    private function returnContext(?Request $request = null): array
    {
        $companyId = $request?->integer('origin_company') ?: request()->integer('company');
        $projectId = $request?->integer('origin_project') ?: request()->integer('project');
        $segment = [3 => 'construction', 6 => 'woodart'][$companyId] ?? null;

        $project = $segment && $projectId
            ? ExpenseDepartment::query()
                ->whereKey($projectId)
                ->where('company_id', $companyId)
                ->where('status', 1)
                ->first(['id', 'name'])
            : null;

        if ($project) {
            return [
                'url' => route("role.{$segment}.accounts.project.summary", [
                    'role' => request()->route('role'),
                    'department' => $project->id,
                ]),
                'label' => 'Back to '.$project->name,
                'company' => $companyId,
                'project' => $project->id,
            ];
        }

        return [
            'url' => route('role.vendor.index', ['role' => request()->route('role')]),
            'label' => 'Back to Vendor List',
            'company' => null,
            'project' => null,
        ];
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($_role, string $id)
    {
        try {
            // Find the vendor by ID
            $vendor = User::when(VendorClassificationService::installed(), fn ($q) => $q->with(['vendorProfile', 'vendorCategories']))->findOrFail($id);

            // Return the view for editing the vendor
            return view('dashboard.vendors.edit', compact('vendor') + PartyAssignmentService::formData($vendor, 'vendor') + VendorClassificationService::formData($vendor));
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
            $validator = Validator::make($request->all(), array_merge([
                'name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:users,email,'.$vendor->id,
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'service_categories' => in_array(2, array_map('intval', $request->input('company_ids', [])), true) ? 'required|array|min:1' : 'nullable|array',
                'service_categories.*' => 'required|in:ticket,visa,flight,file,instructor,others|distinct',
            ], PartyAssignmentService::rules(), VendorClassificationService::rules($request)));

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Please fix the validation errors.')->withErrors($validator)->withInput();
            }

            DB::beginTransaction();

            $before = $vendor->only(['name', 'email', 'phone', 'address']);

            // Update fields
            $vendor->name = $request->name;
            $vendor->company_id = null;
            $vendor->contact_person = $request->contact_person;
            $vendor->email = $request->email;
            $vendor->phone = $request->phone;
            $vendor->address = $request->address;
            $vendor->service_categories = $request->service_categories;

            $vendor->save();
            PartyAssignmentService::sync($vendor, 'vendor', $request->company_ids, $request->project_ids ?? []);
            VendorClassificationService::sync($vendor, $request->integer('vendor_type_id') ?: null, $request->input('vendor_category_ids', []));
            DB::commit();

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
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

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
