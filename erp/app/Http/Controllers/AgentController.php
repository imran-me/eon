<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Services\PartyAssignmentService;
use App\Traits\ExportsPartyList;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class AgentController implements HasMiddleware
{
    use ExportsPartyList;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view agent|view all agent', only: ['index', 'print']),
            new Middleware('permission:create agent', only: ['create', 'store']),
            new Middleware('permission:edit agent', only: ['edit', 'update', 'toggleStatus']),
            new Middleware('permission:delete agent', only: ['destroy']),
        ];
    }

    protected function partyExportMeta(): array
    {
        return ['role' => 'agent', 'prefix' => 'EP-AG-', 'label' => 'Agent'];
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

            // Only agents (assuming you use Spatie Roles)
            $query->whereHas('roles', function ($q) {
                $q->where('name', 'agent');
            });

            $agents = $query->latest()->paginate(10)->withQueryString();

            $balances = $this->latestBalances($agents->pluck('id'));

            // Return the view with agents data
            return view('dashboard.agent.index', compact('agents', 'balances'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to fetch agents.']);
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

            return view('dashboard.agent.create', PartyAssignmentService::formData(null, 'agent'));
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
            $validator = Validator::make($request->all(), array_merge(
                $this->validationRules(),
                ['service_categories' => $this->travelServicesRule($request)],
                PartyAssignmentService::rules()
            ), $this->validationMessages());

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

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
            ]);

            // Assign role
            $user->assignRole('agent'); // ✅ Direct by name
            PartyAssignmentService::sync($user, 'agent', $request->company_ids, $request->project_ids ?? []);
            DB::commit();

            return redirect()->back()->with('success', 'User created successfully.');

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            // Handle any exceptions that may occur
            return redirect()->back()->with('error', 'Please fix the validation errors.')->withErrors(['error' => 'Failed to create agent.'])->withInput();
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
    public function edit($role, string $id)
    {
        try {
            // Fetch the agent by ID
            $agent = User::findOrFail($id);

            // Return the view for editing the agent
            return view('dashboard.agent.edit', compact('agent') + PartyAssignmentService::formData($agent, 'agent'));
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
            // Fetch the agent by ID
            $agent = User::findOrFail($id);

            // Validate the form data
            $validator = Validator::make($request->all(), array_merge(
                $this->validationRules($agent->id),
                ['service_categories' => $this->travelServicesRule($request)],
                PartyAssignmentService::rules()
            ), $this->validationMessages());
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            DB::beginTransaction();

            // Update fields
            $agent->name = $request->name;
            $agent->company_id = null;
            $agent->contact_person = $request->contact_person;
            $agent->email = $request->email;
            $agent->phone = $request->phone;
            $agent->address = $request->address;
            $agent->service_categories = $request->service_categories;

            $agent->save();
            PartyAssignmentService::sync($agent, 'agent', $request->company_ids, $request->project_ids ?? []);
            DB::commit();

            return redirect()->back()->with('success', 'Agent updated successfully.');
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
            // Fetch the agent by ID
            $agent = User::findOrFail($id);

            // Delete the agent
            $agent->delete();

            return redirect()->back()->with('success', 'Agent deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete agent.')->withErrors(['error' => 'Failed to delete agent.']);
        }
    }

    /**
     * Validation rules for agent forms.
     */
    /**
     * Agent types (2026-09-21). A Trainer or Consultant is the responsible
     * person for a contract flight's training / consulting services — we pay
     * them, they have a ledger — and stays an agent in every list. Carried
     * as an extra role beside 'agent'.
     */
    protected function validationRules($agentId = null)
    {
        return [
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email'.($agentId ? ','.$agentId : ''),
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
            'service_categories' => 'required|array|min:1',
            'service_categories.*' => 'required|in:ticket,visa,flight,file,instructor,others|distinct',
        ];
    }

    private function travelServicesRule(Request $request): string
    {
        return in_array(2, array_map('intval', $request->input('company_ids', [])), true)
            ? 'required|array|min:1'
            : 'nullable|array';
    }

    /**
     * Custom validation messages for agent forms.
     */
    protected function validationMessages()
    {
        return [
            'name.required' => 'The agent name is required.',
            'name.string' => 'The agent name must be a string.',
            'name.max' => 'The agent name may not be greater than :max characters.',
            'contact_person.string' => 'The contact person must be a string.',
            'contact_person.max' => 'The contact person may not be greater than :max characters.',
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'The email address is already taken.',
            'phone.required' => 'The phone number is required.',
            'phone.string' => 'The phone number must be a valid string.',
            'phone.max' => 'The phone number may not be greater than :max characters.',
            'address.string' => 'The address must be a string.',
            'address.max' => 'The address may not be greater than :max characters.',
        ];
    }

    public function toggleStatus(string $role, $id)
    {
        $vendor = User::findOrFail($id);
        $vendor->status = $vendor->status === 'active' ? 'inactive' : 'active';
        $vendor->save();

        return back()->with('success', 'Vendor status updated successfully.');
    }
}
