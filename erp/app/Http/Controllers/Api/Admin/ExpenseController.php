<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // GET /api/expense/list had NO scoping of any kind — not the three tiers
        // the web list applies, not even the older "view all expense" check. Any
        // holder of a sanctum token read every expense of all twelve companies,
        // twenty rows at a time. The mobile app is not built yet, but the route
        // has been live and reachable, so this closes it rather than waiting for
        // a client that would only have inherited the hole.
        //
        // Same three tiers, same order, as ExpenseController::applyExpenseVisibility().
        // Kept in step by hand because this controller shares no code with it —
        // if the tiers ever change, they change in both places.
        $user = auth()->user();

        abort_if(!$user || !$user->can('view expense'), 403, 'You are not allowed to view expenses.');

        $req_subdatas = [];
        $query = Expense::select('expenses.*')
            ->when(
                !$user->can('view all expense') && $user->can('view company expense') && !empty($user->company_id),
                fn ($q) => $q->where('expenses.company_id', $user->company_id)
            )
            ->when(
                !$user->can('view all expense') && !$user->can('view company expense'),
                fn ($q) => $q->where('expenses.user_id', $user->id)
            )
            ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
            ->leftJoin('companies', 'companies.id', '=', 'expenses.company_id')
            ->leftJoin('banks', 'banks.id', '=', 'expenses.bank_id')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->leftJoin('expense_sub_categories', 'expense_sub_categories.id', '=', 'expenses.expense_sub_category_id')
            ->orderBy('users.name', 'asc')
            ->orderBy('expense_categories.name', 'asc')
            ->orderBy('expenses.title', 'asc');

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('expenses.user_id', $request->user_id);
        }

        if ($request->has('company_id') && !empty($request->company_id)) {
            $query->where('expenses.company_id', $request->company_id);
        }

        if ($request->has('bank_id') && !empty($request->bank_id)) {
            $query->where('expenses.bank_id', $request->bank_id);
        }

        if ($request->has('expense_category_id') && !empty($request->expense_category_id)) {
            $query->where('expenses.expense_category_id', $request->expense_category_id);
            $req_subdatas = ExpenseSubCategory::where('expense_category_id', $request->expense_category_id)->get();
        }

        if ($request->has('expense_sub_category_id') && !empty($request->expense_sub_category_id)) {
            $query->where('expenses.expense_sub_category_id', $request->expense_sub_category_id);
        }

        if ($request->filled('title')) {
            $query->where('expenses.title', $request->title);
        }

        if ($request->filled('status')) {
            $query->whereDate('expenses.status', $request->status);
        }

        $datas = $query->paginate(20);                
        // $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        // $companies = Company::orderBy('name')->get();
        // $expense_categories = ExpenseCategory::orderBy('name')->where('status', 1)->get();
        // $banks = Bank::orderBy('name')->where('status', 1)->get();

        return response()->json([
            'success' => true,
            'message' => 'Expenses retrieved successfully',
            'data' => $datas,
            // 'subdatas' => $req_subdatas
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'amount' => 'required',
            'expense_date' => 'required',
            'expense_category_id' => 'required'          
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        $data = null;

        DB::transaction(function () use ($request, &$data) {
                        
            $data = Expense::create([
                'user_id' => auth()->id(),
                'company_id' => $request->company_id,
                'expense_category_id' => $request->expense_category_id,
                'expense_sub_category_id' => $request->expense_sub_category_id,
                'title' => $request->title,
                'description' => $request->description,
                'amount' => $request->amount,
                'payment_mode' => $request->payment_mode,
                'bank_id' => $request->bank_id,
                'reference' => $request->reference,
                'expense_date' => $request->expense_date,
                'status' => $request->status ? 1 : 0
            ]);
    
    
            $attachment = $request->file('attachment');
            if ($attachment) {
                $attachment_name = uniqid() . '.' . strtolower($attachment->getClientOriginalExtension());
                $upload_path = 'image/expense/';
                if (!file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }
                $success = $attachment->move(public_path($upload_path), $attachment_name);
                if ($success) {
                    if (!empty($data->attachment) && file_exists(public_path($data->attachment))) {
                        unlink(public_path($data->attachment));
                    }
                    $data->attachment = $upload_path . $attachment_name;
                }
            }

            $data->save(); 

            // ── JOURNAL (auto) ────────────────────────────────────────
            if ($data->status == 1 && $data->bank_id) {
                // Resolved from the taxonomy, the same way the web form does it —
                // sub-category account, then category, then general expense. This
                // used to post every expense straight to general expense whatever
                // it was for, so an expense entered on the phone landed in one
                // bucket while the identical expense entered on the web went to
                // its proper line, and the two disagreed in the ledger.
                $expenseAccountId = app(\App\Services\ExpenseClassificationService::class)
                    ->accountFor($data->expense_category_id, $data->expense_sub_category_id);

                $expenseAccount = \App\Models\Account::findOrFail($expenseAccountId);

                $bank = \App\Models\Bank::find($data->bank_id);
                if (!$bank || !$bank->account_id) {
                    throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                }

                $journal = \App\Models\JournalEntry::create([
                    'company_id'  => auth()->user()->company_id ?? 2,
                    'created_by'  => auth()->id(),
                    'date'        => $data->expense_date,
                    'reference'   => $data->reference,
                    'source'      => 'expense',
                    'source_id'   => $data->id,
                    'description' => 'Expense — ' . $data->title,
                ]);

                // Debit: Expense account — money spent
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $expenseAccount->id,
                    'debit'            => $data->amount,
                    'credit'           => 0,
                    'note'             => $data->title,
                ]);

                // Credit: Cash/Bank — money went out
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $bank->account_id,
                    'debit'            => 0,
                    'credit'           => $data->amount,
                    'note'             => 'Expense paid — ' . $data->title,
                ]);
            }
            // ── END JOURNAL ───────────────────────────────────────────
        });

        return response()->json([
            'success' => true,
            'message' => 'Expense created successfully.',
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $id = $request->id;
        $data = Expense::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }
        $validated = $request->validate([
            'title' => 'required',
            'amount' => 'required',
            'expense_date' => 'required',
            'expense_category_id' => 'required'
        ]);

        DB::transaction(function () use ($request, $data) {

            // Handle attachment upload
            if ($request->hasFile('attachment')) {
                $upload_path = 'image/expense/';
                if (!file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }

                // Delete old attachment
                if (!empty($data->attachment) && file_exists(public_path($data->attachment))) {
                    unlink(public_path($data->attachment));
                }

                $file = $request->file('attachment');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($upload_path), $filename);
                $data->attachment = $upload_path . $filename;
            }

            $data->update([
                // 'user_id' => $request->user_id,
                'company_id' => $request->company_id,
                'expense_category_id' => $request->expense_category_id,
                'expense_sub_category_id' => $request->expense_sub_category_id,
                'title' => $request->title,
                'description' => $request->description,
                'amount' => $request->amount,
                'payment_mode' => $request->payment_mode,
                'bank_id' => $request->bank_id,
                'reference' => $request->reference,
                'expense_date' => $request->expense_date,
                'status' => $request->status ? 1 : 0
            ]);

            // ── JOURNAL UPDATE (auto) ─────────────────────────────────
            $journal = \App\Models\JournalEntry::where('source', 'expense')
                                            ->where('source_id', $data->id)
                                            ->first();

            if ($data->status == 1 && $data->bank_id) {
                // Resolved from the taxonomy, the same way the web form does it —
                // sub-category account, then category, then general expense. This
                // used to post every expense straight to general expense whatever
                // it was for, so an expense entered on the phone landed in one
                // bucket while the identical expense entered on the web went to
                // its proper line, and the two disagreed in the ledger.
                $expenseAccountId = app(\App\Services\ExpenseClassificationService::class)
                    ->accountFor($data->expense_category_id, $data->expense_sub_category_id);

                $expenseAccount = \App\Models\Account::findOrFail($expenseAccountId);

                $bank = \App\Models\Bank::find($data->bank_id);
                if (!$bank || !$bank->account_id) {
                    throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                }

                if ($journal) {
                    // Wipe old items and regenerate fresh
                    $journal->items()->delete();
                    $journal->update([
                        'date'        => $data->expense_date,
                        'reference'   => $data->reference,
                        'description' => 'Expense (edited) — ' . $data->title,
                    ]);
                } else {
                    // Fallback: status may have just changed to active, create now
                    $journal = \App\Models\JournalEntry::create([
                        'company_id'  => auth()->user()->company_id ?? 2,
                        'created_by'  => auth()->id(),
                        'date'        => $data->expense_date,
                        'reference'   => $data->reference,
                        'source'      => 'expense',
                        'source_id'   => $data->id,
                        'description' => 'Expense (edited) — ' . $data->title,
                    ]);
                }

                // Debit: Expense account — money spent
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $expenseAccount->id,
                    'debit'            => $data->amount,
                    'credit'           => 0,
                    'note'             => $data->title,
                ]);

                // Credit: Cash/Bank — money went out
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $bank->account_id,
                    'debit'            => 0,
                    'credit'           => $data->amount,
                    'note'             => 'Expense paid — ' . $data->title,
                ]);

            } else {
                // Status changed to inactive — remove journal entirely
                if ($journal) {
                    $journal->items()->delete();
                    $journal->delete();
                }
            }
            // ── END JOURNAL ───────────────────────────────────────────
        });

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully.',
            'data' => $data
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = Expense::find($request->item_id);
            if ($item) {
                DB::transaction(function () use ($request, $item) {

                    // ── JOURNAL CLEANUP ───────────────────────────────────
                    $journal = \App\Models\JournalEntry::where('source', 'expense')
                        ->where('source_id', $item->id)
                        ->first();
                    if ($journal) {
                        $journal->items()->forceDelete();
                        $journal->forceDelete();
                    }
                    // ── END JOURNAL ───────────────────────────────────────

                    $item->delete();
                });
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense Info Not Found!'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully.'
        ]);
    }

}
