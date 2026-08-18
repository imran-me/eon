<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Log\CustomerLog;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\ExportsPartyList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CustomerRoleController implements HasMiddleware
{
    use ExportsPartyList;

    protected function partyExportMeta(): array
    {
        return ['role' => 'customer', 'prefix' => 'EP-CU-', 'label' => 'Customer'];
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view customer|view all customer', only: ['index', 'exportExcel', 'exportPdf']),
            new Middleware('permission:create customer', only: ['create', 'store']),
            new Middleware('permission:edit customer', only: ['edit', 'update']),
            new Middleware('permission:delete customer', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = User::query();

            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%');
                });
            }

            $query->whereHas('roles', function ($q) {
                $q->where('name', 'customer');
            });

            $customers = $query->latest()->paginate(10);

            $balances = $this->latestBalances($customers->pluck('id'));

            return view('dashboard.customer.index', compact('customers', 'balances'));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to fetch customers.']);
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

    public function create()
    {
        try {
            return view('dashboard.customer.create');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load create form.']);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'password' => 'required|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Please fix the validation errors.')->withErrors($validator)->withInput();
            }

            $user = User::create([
                'name' => $request->name,
                'company_id' => 2,
                'contact_person' => $request->contact_person,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'password' => Hash::make($request->password ?? '1234568'),
            ]);

            $user->assignRole('customer');

            return redirect()->back()->with('success', 'Customer created successfully.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Please fix the validation errors.')->withErrors(['error' => 'Failed to create customer.'])->withInput();
        }
    }

    public function show(string $id)
    {
        //
    }

    public function edit($_role, string $id)
    {
        try {
            $customer = User::findOrFail($id);
            return view('dashboard.customer.edit', compact('customer'));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load edit form.']);
        }
    }

    public function update(Request $request, string $role, User $customer)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'required|email|unique:users,email,' . $customer->id,
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'password' => 'nullable|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Please fix the validation errors.')->withErrors($validator)->withInput();
            }

            $before = $customer->only(['name', 'email', 'phone', 'address']);

            $customer->name = $request->name;
            $customer->company_id = 2;
            $customer->contact_person = $request->contact_person;
            $customer->email = $request->email;
            $customer->phone = $request->phone;
            $customer->address = $request->address;

            if ($request->filled('password')) {
                $customer->password = Hash::make($request->password);
            }

            $customer->save();

            $after = $customer->only(['name', 'email', 'phone', 'address']);

            CustomerLog::create([
                'customer_id' => $customer->id,
                'changed_by' => auth()->id(),
                'action' => 'updated',
                'before' => $before,
                'after' => $after,
            ]);

            return redirect()->back()->with('success', 'Customer updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->withErrors(['error' => 'Failed to update customer.'])->withInput();
        }
    }

    public function destroy($_role, string $id)
    {
        try {
            $customer = User::findOrFail($id);
            $customer->delete();
            return redirect()->back()->with('success', 'Customer deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete customer.');
        }
    }

    public function restore($_role, int $logId)
    {
        $log = CustomerLog::findOrFail($logId);
        $customer = User::findOrFail($log->customer_id);
        $customer->update($log->before);
        return back()->with('success', 'Customer restored to selected version.');
    }

    public function toggleStatus($_role, $id)
    {
        $customer = User::findOrFail($id);
        $customer->status = $customer->status === 'active' ? 'inactive' : 'active';
        $customer->save();
        return back()->with('success', 'Customer status updated successfully.');
    }
}
