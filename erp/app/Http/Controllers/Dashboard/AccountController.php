<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController implements HasMiddleware
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

    /**
     * Display a listing of accounts with optional filters.
     */
    public function index(Request $request)
    {
        $role = Str::slug(Auth::user()->getRoleNames()->first());

        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all account');

        $query = Account::with(['parent', 'company']);

        // Users without "view all account" only see accounts that are
        // either shared across every company (company_id NULL) or belong
        // to their own company — never another company's bank sub-account.
        if (!$canViewAll && !empty($userCompanyId)) {
            $query->where(function ($q) use ($userCompanyId) {
                $q->whereNull('company_id')->orWhere('company_id', $userCompanyId);
            });
        }

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

        // Filter: company
        if ($request->filled('company_id')) {
            if ($request->company_id === 'shared') {
                $query->whereNull('company_id');
            } else {
                $query->where('company_id', $request->company_id);
            }
        }

        // Column sorting. The key comes from the URL and is only ever matched
        // against this list before use, so nothing from the request reaches the
        // query as SQL. Anything unrecognised falls through to the code order.
        // is_string before any cast: ?sort[]=code arrives as an array, and
        // casting that to string raises a PHP warning which Laravel promotes to
        // an ErrorException — a 500 before the whitelist is ever consulted.
        $sortRaw = $request->query('sort');
        $dirRaw  = $request->query('dir');
        $sortKey = is_string($sortRaw) ? $sortRaw : '';
        $sortDir = (is_string($dirRaw) && strtolower($dirRaw) === 'asc') ? 'asc' : 'desc';

        if (in_array($sortKey, ['code', 'name', 'type', 'parent', 'company', 'opening_balance', 'status'], true)) {
            if ($sortKey === 'parent') {
                // Self-referencing: accounts ordered by their parent's name. A
                // subquery keeps the two apart — a join would need aliasing and
                // would risk the parent's columns overwriting the row's own.
                //
                // DB::table, not Account::, on purpose. Account is soft-deleting,
                // and Eloquent would append its scope as `accounts.deleted_at is
                // null` — bound to the OUTER accounts table once the inner one is
                // aliased, which both duplicates the outer scope and fails to
                // exclude trashed parents. Written out here so the alias is the
                // thing being filtered.
                $query->orderBy(
                    DB::table('accounts as parent_acc')
                        ->select('parent_acc.name')
                        ->whereColumn('parent_acc.id', 'accounts.parent_id')
                        ->whereNull('parent_acc.deleted_at'),
                    $sortDir
                );
            } elseif ($sortKey === 'company') {
                // Nullable — "Shared" accounts have no company. Those sort
                // together at one end, which is the sensible grouping anyway.
                $query->orderBy(
                    Company::select('name')->whereColumn('companies.id', 'accounts.company_id'),
                    $sortDir
                );
            } else {
                $query->orderBy('accounts.' . $sortKey, $sortDir);
            }

            // code is unique, so it is a total tiebreak: rows can never shuffle
            // between pages when the sorted value repeats.
            $query->orderBy('accounts.code');
        } else {
            // Untouched default: the code order this list has always used.
            $query->orderBy('code');
        }

        $accounts     = $query->paginate(15)->withQueryString();
        $allAccounts  = Account::active()->orderBy('name')->get(); // for parent dropdown

        // Companies selectable in the create/edit form: company-locked users
        // may only pick "Shared" or their own company; unrestricted users
        // may pick any company.
        $companies = (!$canViewAll && !empty($userCompanyId))
            ? Company::where('id', $userCompanyId)->get()
            : Company::orderBy('name')->get();

        return view('accounts.index', compact('accounts', 'allAccounts', 'role', 'companies'));
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
            'company_id'      => 'nullable|exists:companies,id',
            'opening_balance' => 'nullable|numeric|min:0',
            'status'          => 'nullable|boolean',
        ]);

        // Company-locked users may leave this shared (blank) or assign their
        // own company, but never another company's id.
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all account');
        if (!$canViewAll && !empty($userCompanyId) && $request->filled('company_id')) {
            abort_if((int) $request->company_id !== (int) $userCompanyId, 403, 'You can only assign this account to your own company or leave it shared.');
        }

        // Prevent an account from being its own parent (not applicable on create, but keep guard)
        $validated['opening_balance'] = $validated['opening_balance'] ?? 0;
        $validated['status']          = $request->boolean('status', true);

        $account = Account::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'account' => $account->load(['parent', 'company']),
        ]);
    }

    /**
     * Return account data for edit modal.
     */
    public function edit($role, Account $account)
    {
        $userCompanyId = auth()->user()->company_id;
        abort_if(!auth()->user()->can('view all account') && !empty($userCompanyId) && !empty($account->company_id) && (int) $account->company_id !== (int) $userCompanyId, 403, 'This account belongs to a different company.');

        return response()->json($account->load(['parent', 'company']));
    }

    /**
     * Update the specified account.
     */
    public function update(Request $request, $role, Account $account)
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all account');
        abort_if(!$canViewAll && !empty($userCompanyId) && !empty($account->company_id) && (int) $account->company_id !== (int) $userCompanyId, 403, 'This account belongs to a different company.');

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
            'company_id'      => 'nullable|exists:companies,id',
            'opening_balance' => 'nullable|numeric|min:0',
            'status'          => 'nullable|boolean',
        ]);

        // Company-locked users may leave this shared (blank) or assign their
        // own company, but never another company's id.
        if (!$canViewAll && !empty($userCompanyId) && $request->filled('company_id')) {
            abort_if((int) $request->company_id !== (int) $userCompanyId, 403, 'You can only assign this account to your own company or leave it shared.');
        }

        $validated['opening_balance'] = $validated['opening_balance'] ?? 0;
        $validated['status']          = $request->boolean('status', true);

        $account->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully.',
            'account' => $account->fresh(['parent', 'company']),
        ]);
    }

    /**
     * Soft-delete the specified account.
     */
    public function destroy($role, Account $account)
    {
        $userCompanyId = auth()->user()->company_id;
        if (!auth()->user()->can('view all account') && !empty($userCompanyId) && !empty($account->company_id) && (int) $account->company_id !== (int) $userCompanyId) {
            return response()->json([
                'success' => false,
                'message' => 'This account belongs to a different company.',
            ], 403);
        }

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
