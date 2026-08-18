<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BankController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
         try {
            // Fetch banks from the database
            $banks = Bank::latest()->paginate(10);;

            // Return the view with banks data
            return view('dashboard.banks.index', compact('banks'));
        } catch (\Exception $e) {
            dd($e);
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to fetch banks.']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            return view('dashboard.banks.create');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load create form: ' . $e->getMessage()]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request,$role)
    {
        // Validate the request data
        $request->validate([
            'name' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_type' => 'required|in:savings,current,fixed',
            'bank_type' => 'required|in:national,international',
            'type' => 'required|in:bank,mobile_banking,digital_wallet',
            'routing_number' => 'nullable|string|max:20',
            'account_number' => 'required|string|max:20|unique:banks,account_number',
            'iban' => 'nullable|string|max:34',
            'swift_code' => 'nullable|string|max:11',
            'address' => 'nullable|string|max:255',
            'balance' => 'required|numeric|min:0',
        ]);

        try {
            $request->merge(['currency' => 'BDT']); // Set default currency to BDT
            // Create a new bank record
            $request->merge(['created_by' => Auth::user()->id]);

            Bank::create($request->all());
            return redirect()->back()->with('success', 'Bank created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create category: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($role, string $id)
    {
        // This method is not implemented in the original code, but you can add logic to display a specific bank's details if needed.
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($role, string $id)
    {
        try {
            $bank = Bank::findOrFail($id);
            return view('dashboard.banks.edit', compact('bank'));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load edit form: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, Bank $bank)
    {
        // Validate the request data
        $request->validate([
            'name' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_type' => 'required|in:savings,current,fixed',
            'bank_type' => 'required|in:national,international',
            'type' => 'required|in:bank,mobile_banking,digital_wallet',
            'routing_number' => 'nullable|string|max:20',
            // Ignore the current bank's account_number in unique validation
            'account_number' => 'required|string|max:20|unique:banks,account_number,' . $bank->id,
            'iban' => 'nullable|string|max:34',
            'swift_code' => 'nullable|string|max:11',
            'currency' => 'required|string|max:10',
            'address' => 'nullable|string|max:255',
            'balance' => 'required|numeric|min:0',
        ]);

        try {
            // Add updated_by or keep created_by if needed
            $data = $request->all();
            $data['updated_by'] = Auth::user()->id;

            $bank->update($data);

            return redirect()->route('role.banks.index', ['role' => $role])
                            ->with('success', 'Bank updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                            ->withErrors(['error' => 'Failed to update bank: ' . $e->getMessage()])
                            ->withInput();
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
