<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Display a listing of accounts with optional filters.
     */
    public function index(Request $request)
    {
        $role = Str::slug(Auth::user()->getRoleNames()->first());

        $query = Account::with('parent');

        // Filter: name (searches both name and code)
        if ($request->filled('name')) {
            $search = $request->name;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        // Filter: status
        if ($request->filled('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter: type
        if ($request->filled('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        $accounts     = $query->orderBy('code')->paginate(15)->withQueryString();
        $allAccounts  = Account::active()->orderBy('name')->get(); // for parent dropdown

        return response()->json([
            'success' => true,
            'message' => 'Accounts retrieved successfully.',
            'data' => [
                'accounts' => $accounts,
                'all_accounts' => $allAccounts,
                'role' => $role,
            ]
        ]);
    }

    /**
     * Store a newly created account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'            => 'required|string|max:50|unique:accounts,code',
            'name'            => 'required|string|max:255',
            'type'            => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'parent_id'       => 'nullable|exists:accounts,id',
            'opening_balance' => 'nullable|numeric|min:0',
            'status'          => 'nullable|boolean',
        ]);

        // Prevent an account from being its own parent (not applicable on create, but keep guard)
        $validated['opening_balance'] = $validated['opening_balance'] ?? 0;
        $validated['status']          = $request->boolean('status', true);

        $account = Account::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => $account->load('parent'),
        ]);
    }

    /**
     * Update the specified account.
     */
    public function update(Request $request, $role, Account $account)
    {
        // ── SYSTEM ACCOUNT GUARD ─────────────────────────────
        // if ($account->isSystemAccount()) {
        //     // Allow only name and status to be changed
        //     // Block code, type, parent_id changes
        //     if (
        //         $request->code !== $account->code ||
        //         $request->type !== $account->type ||
        //         $request->parent_id != $account->parent_id
        //     ) {
        //         return response()->json([
        //             'success' => false,
        //             'errors' => [
        //                 'code' => ['System accounts: only Name and Status can be changed. Code, Type and Parent are locked.']
        //             ]
        //         ], 422);
        //     }
        // }
        // ── END GUARD ─────────────────────────────────────────

        $validated = $request->validate([
            'code'            => ['required', 'string', 'max:50', Rule::unique('accounts', 'code')->ignore($account->id)],
            'name'            => 'required|string|max:255',
            'type'            => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'parent_id'       => [
                'nullable',
                'exists:accounts,id',
                // Prevent self-reference
                function ($attribute, $value, $fail) use ($account) {
                    if ($value && (int) $value === $account->id) {
                        $fail('An account cannot be its own parent.');
                    }
                },
                // Prevent circular: parent cannot be a child of this account
                function ($attribute, $value, $fail) use ($account) {
                    if ($value && $account->isAncestorOf((int) $value)) {
                        $fail('Circular parent reference detected.');
                    }
                },
            ],
            'opening_balance' => 'nullable|numeric|min:0',
            'status'          => 'nullable|boolean',
        ]);

        $validated['opening_balance'] = $validated['opening_balance'] ?? 0;
        $validated['status']          = $request->boolean('status', true);

        $account->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully.',
            'data' => $account->fresh('parent'),
        ]);
    }

    /**
     * Soft-delete the specified account.
     */
    public function destroy($role, Account $account)
    {
        // ── SYSTEM ACCOUNT GUARD ─────────────────────────────
        if ($account->isSystemAccount()) {
            return response()->json([
                'success' => false,
                'message' => 'System accounts cannot be deleted. They are required for journal entries.',
            ], 422);
        }
        // ── END GUARD ─────────────────────────────────────────
        
        // Prevent deletion if children exist
        if ($account->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an account that has child accounts. Reassign or delete the children first.',
            ], 422);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ]);
    }
}
