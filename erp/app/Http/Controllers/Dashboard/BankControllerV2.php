<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\BankOverdrawnException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankReconciliation;
use App\Models\BankTransfer;
use App\Models\Company;
use App\Models\Customer;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\ExpenseDepartment;
use App\Models\FinancingCapital;
use App\Models\JournalEntry;
use App\Models\JournalEntryDateEdit;
use App\Models\JournalItem;
use App\Models\PaymentSchedule;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankScheduleCorrection;
use App\Services\BankStatementComparer;
use App\Services\BankStatementParser;
use App\Services\BankTransferService;
use App\Services\PartyInvoiceAllocation;
use App\Services\PaymentScheduleReconciler;
use App\Traits\PostsPartyLedger;
use App\Traits\ResolvesPartyType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BankControllerV2 extends Controller
{
    use PostsPartyLedger;
    use ResolvesPartyType;

    public function __construct()
    {
        $this->middleware('permission:view bank account|view all bank account')
            ->only(['index', 'show', 'statement', 'printStatement', 'reconciliation']);
        $this->middleware('permission:create bank account')->only(['create', 'store']);
        $this->middleware('permission:edit bank account')->only(['edit', 'update', 'storeReconciliation', 'compareStatement']);
        $this->middleware('permission:delete bank account')->only(['destroy', 'destroyReconciliation']);
    }

    /**
     * Sources updateJournalDate() is allowed to touch.
     *
     * A journal entry's date is copied in from wherever it was posted —
     * 'bank_transaction' entries (this controller's own deposit/withdraw) have
     * no other record of their date, but the other sources each mirror a date
     * column on a record another screen also reads: 'financing_capital' from
     * financing_capital_movements.date (Capital & Financing screen), 'expense'
     * from expenses.expense_date (Expense screen), 'bank_transfer' from
     * bank_transfers.payment_date (Bank Transfers screen), and
     * 'schedule_payment_settlement' from payment_schedules.paid_date (Payment
     * Schedule screen) plus, when the schedule is linked to a Sale/Purchase/
     * TicketSale/TicketPurchase rather than an ad-hoc party, the matching
     * `transactions` row (found by its 'SCH-{schedule_id}' reference_no, the
     * same string PaymentScheduleController::pay() stamps on it) that the
     * Party Statement reads independently. Editing only the journal side for
     * one of those would leave the two screens disagreeing about when the
     * same real transaction happened — worse than the mismatch this feature
     * exists to fix. Each source here has a matching sync step in
     * updateJournalDate(). Employee payments mirror payment_date and
     * transaction_time on employee_payments. A source NOT listed here (sale,
     * purchase, salary generation,
     * ...) has its own date column elsewhere that has not been verified and
     * wired up the same way yet, so it stays refused.
     */
    private const DATE_EDITABLE_SOURCES = ['bank_transaction', 'financing_capital', 'expense', 'bank_transfer', 'schedule_payment_settlement', 'employee_payment'];

    /**
     * Ids of entries that have been reversed, memoised for the request.
     *
     * The recent-activity feed is rebuilt once per company, so without this the
     * same lookup would run a dozen times to answer one question: may this row's
     * text still be edited?
     *
     * @var Collection|null
     */
    private $reversedEntryIds = null;

    private function reversedEntryIds()
    {
        if ($this->reversedEntryIds === null) {
            $this->reversedEntryIds = JournalEntry::whereNotNull('reversed_journal_entry_id')
                ->pluck('reversed_journal_entry_id')
                ->flip();
        }

        return $this->reversedEntryIds;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        // Users without "view all bank" (e.g. a single-company manager/accountant)
        // are locked to their own company's banks, regardless of what's requested.
        $userCompanyId = auth()->user()->company_id;
        $canViewAllBanks = auth()->user()->can('view all bank');

        $query = Bank::select('banks.*')
            ->selectSub(function ($q) {
                $q->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0)')
                    ->from('journal_items')
                    ->whereColumn('journal_items.account_id', 'banks.account_id')
                    ->whereNull('journal_items.deleted_at');
            }, 'journal_balance')
            ->orderBy('banks.name', 'asc');

        if (! $canViewAllBanks && ! empty($userCompanyId)) {
            $query->where(fn ($scope) => $scope
                ->where('banks.company_id', $userCompanyId)
                ->orWhereNull('banks.company_id'));
        }

        if ($request->filled('name')) {
            $query->where('banks.name', $request->name);
        }

        if ($request->filled('status')) {
            $query->whereDate('banks.status', $request->status);
        }

        $datas = $query->paginate(20);

        // The edit form must show the opening JOURNAL, never banks.balance
        // (which is only a current-balance cache). Otherwise saving an old bank
        // would turn today's closing balance into a second opening balance.
        $openingEntries = JournalEntry::with('items')
            ->where('source', 'bank')
            ->whereIn('source_id', $datas->getCollection()->pluck('id'))
            ->where('reference', 'like', 'OPENING-BANK-%')
            ->get()
            ->keyBy('source_id');

        $datas->getCollection()->each(function (Bank $bank) use ($openingEntries) {
            $opening = $openingEntries->get($bank->id);
            $line = $opening?->items->firstWhere('account_id', $bank->account_id);
            $bank->setAttribute('opening_balance_amount', $line
                ? abs((float) $line->debit - (float) $line->credit)
                : 0);
            $bank->setAttribute('opening_balance_date', $opening?->date?->format('Y-m-d'));
        });

        $accounts = Account::active()->whereIn('type', ['asset', 'liability'])->get();
        $companies = (! $canViewAllBanks && ! empty($userCompanyId))
            ? Company::where('id', $userCompanyId)->get()
            : Company::all();

        return view('banks.index', compact(
            'datas',
            'accounts',
            'companies',
            'canViewAllBanks'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('banks.create-modal');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $sharedRequested = $request->input('company_id') === 'shared';
        if ($sharedRequested) {
            $request->merge(['company_id' => null]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'branch_name' => 'nullable|string|max:255',
            'account_id' => 'required',
            'account_name' => 'required|string|max:255',
            'account_type' => 'nullable|in:savings,current,fixed,credit_card,personal',
            'bank_type' => 'nullable|in:national,international',
            'type' => 'required|in:bank,cash,mobile_banking,digital_wallet,credit_card',
            'routing_number' => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:20|unique:banks,account_number',
            'iban' => 'nullable|string|max:34',
            'swift_code' => 'nullable|string|max:11',
            // 'currency' => 'required|string|max:10',
            'address' => 'nullable|string|max:255',
            'balance' => 'required|numeric|min:0',
            'opening_balance_date' => 'nullable|date|before_or_equal:today',
        ]);

        $validator->after(function ($validator) use ($request, $sharedRequested) {
            if (! $sharedRequested && ! $request->filled('company_id')) {
                $validator->errors()->add('company_id', 'Please select a company or Group / Shared.');
            }
            if ($sharedRequested && ! auth()->user()->can('view all bank')) {
                $validator->errors()->add('company_id', 'You are not allowed to create a group/shared account.');
            }
            $ledgerType = optional(Account::find($request->account_id))->type;
            $requiredType = $request->type === 'credit_card' ? 'liability' : 'asset';
            if ($ledgerType && $ledgerType !== $requiredType) {
                $validator->errors()->add('account_id', 'Credit cards require a liability ledger; all other payment accounts require an asset ledger.');
            }
            if ((float) $request->balance > 0 && ! $request->filled('opening_balance_date')) {
                $validator->errors()->add('opening_balance_date', 'Please select the opening balance date.');
            }
        });

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $data = Bank::create([
                'name' => $request->name,
                'company_id' => $request->company_id,
                'branch_name' => $request->branch_name,
                'account_id' => $request->account_id,
                'account_name' => $request->account_name,
                'account_type' => $request->type === 'credit_card' ? 'credit_card' : ($request->account_type ?: 'savings'),
                'bank_type' => $request->bank_type ?: 'national',
                'type' => $request->type,
                'routing_number' => $request->routing_number,
                'account_number' => $request->account_number,
                'iban' => $request->iban,
                'swift_code' => $request->swift_code,
                'currency' => 'BDT', // Set default currency to BDT
                'address' => $request->address,
                'balance' => $request->balance,
                'last_transaction_date' => $request->last_transaction_date,
                'last_transaction_amount' => $request->last_transaction_amount,
                'last_transaction_type' => $request->last_transaction_type,
                'created_by' => auth()->id(),
                'status' => $request->status ? 1 : 0,
            ]);

            // Use the picked ledger account as-is when that is safe, which is what
            // update() has always done. Auto-creating a child unconditionally is
            // what produced the duplicate pair "1113 Office Cash" (real, posted to)
            // and "ACC-36 OFFICE CASH" (empty), indistinguishable in the dropdown.
            //
            // Two cases are still not safe to link directly, and there we keep
            // nesting a bank-specific child underneath the picked account:
            //   - a group/parent account: posting straight to it double-counts
            //     against its own children in any rollup (this is exactly how
            //     "Cash In Hand" ended up holding transactions of its own);
            //   - an account already backing another bank: buildBankStatementRows()
            //     keys purely on account_id, so the two banks would render each
            //     other's transactions and balances.
            $picked = Account::find($request->account_id);
            $pickedIsParent = $picked && Account::where('parent_id', $picked->id)->exists();
            $pickedInUse = Bank::where('account_id', $request->account_id)
                ->where('id', '!=', $data->id)
                ->exists();

            if ($picked && ! $pickedIsParent && ! $pickedInUse) {
                $data->account_id = $picked->id;
            } else {
                $account = new Account;
                $account->name = $request->name;
                // Cash/mobile-banking accounts often have no account number — fall
                // back to a code derived from the bank's own id, which is always
                // unique, instead of an empty string colliding on the second one.
                $account->code = $request->account_number ? substr($request->account_number, -4) : ('ACC-'.$data->id);
                $account->type = $request->type === 'credit_card' ? 'liability' : 'asset';
                $account->parent_id = $request->account_id;
                // Bank sub-accounts belong to one specific company (unlike the
                // shared/global chart-of-accounts entries), matching the bank
                // record itself — otherwise this account would default to
                // "shared" and leak into every other company's reports.
                $account->company_id = $data->company_id;
                $account->save();

                $data->account_id = $account->id;
            }

            $data->save();

            if ($data && $request->balance > 0) {
                $equityAccount = Account::where('code', config('accounts.opening_balance_equity'))->first();

                if (! $equityAccount) {
                    throw new \Exception('Equity account not found for journal offset.');
                }
                $journal = JournalEntry::create([
                    'company_id' => $data->company_id,
                    'created_by' => auth()->id(),
                    'date' => $request->opening_balance_date,
                    'entry_time' => '00:00:00',
                    'reference' => 'OPENING-BANK-'.$data->name,
                    'source' => 'bank',
                    'source_id' => $data->id,
                    'description' => 'Bank Opening Balance',
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $data->account_id,
                    'debit' => $request->type === 'credit_card' ? 0 : $request->balance,
                    'credit' => $request->type === 'credit_card' ? $request->balance : 0,
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $equityAccount->id,
                    'debit' => $request->type === 'credit_card' ? $request->balance : 0,
                    'credit' => $request->type === 'credit_card' ? 0 : $request->balance,
                ]);

                // A bank opening is represented by the balanced journal above.
                // Leaving the same value on accounts.opening_balance would make
                // financial reports count it a second time.
                Account::whereKey($data->account_id)->update(['opening_balance' => 0]);
            }

            $data->update(['balance' => Bank::ledgerBalanceForAccount((int) $data->account_id)]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                ]);
            }

            return redirect()->back()->with('error', $th->getMessage())->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data,
            ]);
        }

        return redirect()->route('role.banks.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('banks.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $id = $request->id;
        $data = Bank::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!',
            ]);
        }
        $sharedRequested = $request->input('company_id') === 'shared';
        if ($sharedRequested) {
            $request->merge(['company_id' => null]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'branch_name' => 'nullable|string|max:255',
            'account_id' => ['required', 'exists:accounts,id', Rule::unique('banks', 'account_id')->ignore($data->id)],
            'account_name' => 'required|string|max:255',
            'account_type' => 'nullable|in:savings,current,fixed,credit_card,personal',
            'bank_type' => 'nullable|in:national,international',
            'type' => 'required|in:bank,cash,mobile_banking,digital_wallet,credit_card',
            'routing_number' => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:20|unique:banks,account_number,'.$data->id,
            'iban' => 'nullable|string|max:34',
            'swift_code' => 'nullable|string|max:11',
            'currency' => 'required|string|max:10',
            'address' => 'nullable|string|max:255',
            'balance' => 'required|numeric|min:0',
            'opening_balance_date' => 'nullable|date|before_or_equal:today',
        ]);

        $validator->after(function ($validator) use ($request, $sharedRequested, $data) {
            if (! $sharedRequested && ! $request->filled('company_id')) {
                $validator->errors()->add('company_id', 'Please select a company or Group / Shared.');
            }
            if ($sharedRequested && ! auth()->user()->can('view all bank')) {
                $validator->errors()->add('company_id', 'You are not allowed to assign a group/shared account.');
            }
            $ledgerType = optional(Account::find($request->account_id))->type;
            $requiredType = $request->type === 'credit_card' ? 'liability' : 'asset';
            if ($ledgerType && $ledgerType !== $requiredType) {
                $validator->errors()->add('account_id', 'Credit cards require a liability ledger; all other payment accounts require an asset ledger.');
            }
            if ((float) $request->balance > 0 && ! $request->filled('opening_balance_date')) {
                $validator->errors()->add('opening_balance_date', 'Please select the opening balance date.');
            }
            if ($request->filled('opening_balance_date')) {
                $firstTransactionDate = JournalEntry::query()
                    ->whereIn('id', JournalItem::query()
                        ->where('account_id', $data->account_id)
                        ->whereNull('deleted_at')
                        ->select('journal_entry_id'))
                    ->where(fn ($q) => $q
                        ->where('source', '!=', 'bank')
                        ->orWhere('source_id', '!=', $data->id)
                        ->orWhere('reference', 'not like', 'OPENING-BANK-%'))
                    ->whereNull('deleted_at')
                    ->min('date');

                if ($firstTransactionDate && $request->opening_balance_date > $firstTransactionDate) {
                    $validator->errors()->add(
                        'opening_balance_date',
                        'Opening balance date must be on or before the first transaction date ('.$firstTransactionDate.').'
                    );
                }
            }
        });

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $oldAccountId = $data->account_id;
            $oldType = $data->type;

            $data->update([
                'name' => $request->name,
                'company_id' => $request->company_id,
                'branch_name' => $request->branch_name,
                'account_id' => $request->account_id,
                'account_name' => $request->account_name,
                'account_type' => $request->type === 'credit_card' ? 'credit_card' : ($request->account_type ?: $data->account_type),
                'bank_type' => $request->bank_type ?: $data->bank_type,
                'type' => $request->type,
                'routing_number' => $request->routing_number,
                'account_number' => $request->account_number,
                'iban' => $request->iban,
                'swift_code' => $request->swift_code,
                'currency' => $request->currency,
                'address' => $request->address,
                'last_transaction_date' => $request->last_transaction_date,
                'last_transaction_amount' => $request->last_transaction_amount,
                'last_transaction_type' => $request->last_transaction_type,
                'updated_by' => auth()->id(),
                'status' => $request->status ? 1 : 0,
            ]);

            // The Ledger Account was changed to a different account (e.g. to
            // fix a broken link to a soft-deleted account) — each linked
            // account belongs to exactly one bank, so move this bank's whole
            // journal history over instead of leaving it split across two
            // accounts or orphaned on the old one.
            //
            // That "whole history" is a bulk move keyed on account_id alone,
            // because a journal item records the ACCOUNT it hit, never the bank.
            // On a dedicated bank account the two mean the same thing. On a
            // SHARED one they do not: 1013 Office Cash carries every company's
            // cash expense and every petty cash movement as well as one company's
            // cash-drawer bank, and there is nothing in the data saying which of
            // those rows "belongs to" the bank. Moving them all would hand seven
            // other companies' cash history to whatever account was picked.
            //
            // So refuse rather than guess. Failing here rolls the whole update
            // back and tells the user why; the alternative is a silent, correct-
            // looking save that quietly rewrites other companies' books.
            if ($oldAccountId && $oldAccountId != $data->account_id) {
                $otherCompanies = JournalEntry::query()
                    ->whereIn('id', JournalItem::query()
                        ->where('account_id', $oldAccountId)
                        ->whereNull('deleted_at')
                        ->select('journal_entry_id'))
                    ->where('company_id', '!=', $data->company_id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($otherCompanies) {
                    $old = Account::find($oldAccountId);

                    throw new \RuntimeException(
                        'Cannot move the history off ['.($old->code ?? $oldAccountId).'] '.($old->name ?? 'that account')
                        .' — other companies have posted to it too, and a journal item records the account it hit, not the bank. '
                        .'Moving them would take their entries with it. Point this bank at a different account without relinking '
                        .'the history, or have the entries reassigned individually.'
                    );
                }

                JournalItem::where('account_id', $oldAccountId)->update(['account_id' => $data->account_id]);
            }

            // Keep the linked account's company in sync with the bank's own
            // company, whether the link just changed or the bank's company
            // itself was edited — otherwise the account could stay "shared"
            // or point at the wrong company and leak into other reports.
            //
            // NEVER when another company has already posted to that account.
            // Some accounts are shared on purpose and a bank happens to point at
            // one: 1013 Office Cash is the credit side of EVERY company's cash
            // expense and every petty cash issue/return, and it is also the
            // ledger account behind one company's "OFFICE CASH" bank record.
            // Stamping it would hand a group-wide account to that single company,
            // and from that moment every OTHER company's trial balance silently
            // stops balancing — trialBalanceV1() filters ACCOUNTS by company but
            // JOURNAL ITEMS by entry, so their debits still render while their
            // credits disappear with the account. Nothing errors; the books just
            // stop adding up. That is exactly what the 1113 → 1013 renumber did,
            // and re-doing it is one save of the Bank edit form away.
            //
            // Asking the ledger rather than the chart on purpose: "is this
            // account shared?" is a guess, "has another company posted here?" is
            // the fact that decides whether claiming it breaks anything.
            if ($data->account_id) {
                $postedByAnotherCompany = JournalItem::query()
                    ->where('journal_items.account_id', $data->account_id)
                    ->whereNull('journal_items.deleted_at')
                    ->whereHas('journalEntry', fn ($q) => $q
                        ->where('company_id', '!=', $data->company_id)
                        ->whereNull('deleted_at'))
                    ->exists();

                if (! $postedByAnotherCompany) {
                    Account::where('id', $data->account_id)->update(['company_id' => $data->company_id]);
                }
            }

            // Create or update the opening journal independently of the bank's
            // current-balance cache. This remains available even after normal
            // transactions have started.
            $journal = JournalEntry::where('source', 'bank')
                ->where('source_id', $data->id)
                ->where('reference', 'like', 'OPENING-BANK-%')
                ->first();

            if ($journal) {
                $journal->update([
                    'company_id' => $data->company_id,
                    'date' => $request->opening_balance_date ?: $journal->date,
                    'entry_time' => '00:00:00',
                    'reference' => 'OPENING-BANK-'.$data->name,
                ]);

                // Update existing opening balance journal items
                JournalItem::where('journal_entry_id', $journal->id)
                    ->where('account_id', $data->account_id)
                    ->update([
                        'debit' => $data->type === 'credit_card' ? 0 : $request->balance,
                        'credit' => $data->type === 'credit_card' ? $request->balance : 0,
                    ]);

                $equityAccount = Account::where('code', config('accounts.opening_balance_equity'))->first();
                if ($equityAccount) {
                    JournalItem::where('journal_entry_id', $journal->id)
                        ->where('account_id', $equityAccount->id)
                        ->update([
                            'debit' => $data->type === 'credit_card' ? $request->balance : 0,
                            'credit' => $data->type === 'credit_card' ? 0 : $request->balance,
                        ]);
                }
            } elseif ((float) $request->balance > 0) {
                // No existing journal — create opening entry now
                $equityAccount = Account::where('code', config('accounts.opening_balance_equity'))->first();
                if (! $equityAccount) {
                    throw new \Exception('Equity account (3140) not found for journal offset.');
                }
                $journal = JournalEntry::create([
                    'company_id' => $data->company_id,
                    'created_by' => auth()->id(),
                    'date' => $request->opening_balance_date,
                    'entry_time' => '00:00:00',
                    'reference' => 'OPENING-BANK-'.$data->name,
                    'source' => 'bank',
                    'source_id' => $data->id,
                    'description' => 'Bank Opening Balance',
                ]);
                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $data->account_id,
                    'debit' => $data->type === 'credit_card' ? 0 : $request->balance,
                    'credit' => $data->type === 'credit_card' ? $request->balance : 0,
                ]);
                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $equityAccount->id,
                    'debit' => $data->type === 'credit_card' ? $request->balance : 0,
                    'credit' => $data->type === 'credit_card' ? 0 : $request->balance,
                ]);
            }

            if ((float) $request->balance > 0 || $journal) {
                Account::whereKey($data->account_id)->update(['opening_balance' => 0]);
            }

            // Rebuild the cache from the journal after changing the opening.
            $data->update(['balance' => Bank::ledgerBalanceForAccount((int) $data->account_id)]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                ]);
            }

            return redirect()->back()->with('error', $th->getMessage())->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $data,
            ]);
        }

        return redirect()->back()->with('success', 'Data updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = Bank::find($request->item_id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!',
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.',
        ]);
    }

    public function getEmployeeSalary(Request $request)
    {
        try {
            $data = Bank::where('user_id', $request->user_id)->orderBy('id', 'ASC')->get();
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => 'Data Found Successfully.',
        ]);
    }

    /**
     * Manage Bank Accounts dashboard — cards per account + combined recent transactions.
     */
    public function dashboard(Request $request)
    {
        return view('banks.dashboard', $this->dashboardPayload());
    }

    /**
     * One accounting desk for money entering or leaving any bank/card account.
     * Posting deliberately stays in recordTransaction(), the same engine used
     * by each individual account card, so both doors produce identical ledgers.
     */
    public function paymentEntry(Request $request)
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAllBanks = auth()->user()->can('view all bank');

        $banks = Bank::query()
            ->where('status', true)
            ->whereNotNull('account_id')
            ->when(! $canViewAllBanks && ! empty($userCompanyId), fn ($query) => $query->where('company_id', $userCompanyId))
            ->select('banks.*')
            ->selectSub(function ($query) {
                $query->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0)')
                    ->from('journal_items')
                    ->whereColumn('journal_items.account_id', 'banks.account_id')
                    ->whereNull('journal_items.deleted_at');
            }, 'journal_balance')
            ->orderBy('name')
            ->get();

        $accounts = Account::active()
            // Moving money between two managed money accounts is a Bank
            // Transfer, not a payment entry. Excluding those contra heads keeps
            // both account caches and the transfer audit record in sync.
            ->whereNotIn('id', Bank::query()->whereNotNull('account_id')->select('account_id'))
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'company_id']);

        $companyIds = $banks->pluck('company_id')->filter()->unique()->values();
        $companiesById = Company::whereIn('id', $companyIds)->get(['id', 'name', 'short_name'])->keyBy('id');
        $departments = ExpenseDepartment::active()
            ->whereIn('company_id', $companyIds)
            ->orderBy('name')
            ->get(['id', 'company_id', 'name']);

        $beneficiaryParties = User::role(['vendor', 'agent', 'customer'])
            ->select('id', 'name')
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (User $party) {
                $role = strtolower($party->roles->first(fn ($role) => in_array(strtolower($role->name), ['vendor', 'agent', 'customer']))->name);
                $prefix = ['vendor' => 'EP-VN-', 'agent' => 'EP-AG-', 'customer' => 'EP-CU-'][$role];

                return [
                    'id' => $party->id,
                    'name' => $party->name,
                    'kind' => $role === 'customer' ? 'Customer' : ucfirst($role),
                    'display_id' => $prefix.str_pad((string) $party->id, 3, '0', STR_PAD_LEFT),
                ];
            });

        return view('banks.payment-entry', compact('banks', 'accounts', 'companiesById', 'departments', 'beneficiaryParties'));
    }

    /**
     * The dashboard's own figures, as JSON.
     *
     * Exists so recording a Cash In / Cash Out / reversal can refresh the page
     * in place instead of reloading it. The company banner, the bank cards and
     * the recent-activity feed are all computed here and nowhere else, which is
     * why the old code simply called window.location.reload() — and why filing
     * five cash entries meant five full page loads, each one dropping the user
     * back at the top with the ledger closed.
     *
     * Deliberately the SAME payload the page is first rendered from, not a
     * hand-rolled subset: a client that patches balances arithmetically drifts
     * from what a reload would have shown, and the 14-day net-change figure
     * cannot be derived on the client at all.
     */
    public function dashboardData(Request $request)
    {
        return response()->json([
            'companies' => $this->dashboardPayload()['companiesData'],
        ]);
    }

    /** Everything banks/dashboard.blade.php renders from. */
    private function dashboardPayload(): array
    {
        // Same company-lock as index(): without "view all bank", a user only
        // ever sees their own company's banks — no cross-company overview and
        // no "Epal Group" umbrella, since both would leak other companies' data.
        $userCompanyId = auth()->user()->company_id;
        $canViewAllBanks = auth()->user()->can('view all bank');

        $banksQuery = Bank::where('status', true)
            ->with('company')
            ->select('banks.*')
            ->selectSub(function ($q) {
                $q->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0)')
                    ->from('journal_items')
                    ->whereColumn('journal_items.account_id', 'banks.account_id')
                    ->whereNull('journal_items.deleted_at');
            }, 'journal_balance')
            ->orderBy('name');

        if (! $canViewAllBanks && ! empty($userCompanyId)) {
            $banksQuery->where('company_id', $userCompanyId);
        }

        $banks = $banksQuery->get();

        $totalBalance = $banks->where('type', '!=', 'credit_card')->sum('journal_balance');
        $accountIds = $banks->pluck('account_id')->filter()->values();

        $bankGroups = $banks->groupBy(fn ($bank) => $bank->company->name ?? 'Unassigned')->sortKeys();

        $companies = Company::whereIn('id', $banks->pluck('company_id')->filter()->unique())
            ->orderBy('name')
            ->get(['id', 'name']);

        $accounts = Account::active()->orderBy('name')->get(['id', 'name', 'code']);

        // Running balance per account, computed chronologically across each account's
        // full history so "balance after" is correct even though the recent-activity
        // feed below only displays the last 14 days.
        $chronological = JournalItem::with('journalEntry')
            ->whereIn('account_id', $accountIds)
            ->get()
            ->sortBy(fn ($item) => $item->journalEntry->date.'-'.str_pad($item->id, 10, '0', STR_PAD_LEFT))
            ->values();

        $runningBalance = [];
        foreach ($chronological as $item) {
            $runningBalance[$item->account_id] = ($runningBalance[$item->account_id] ?? 0) + $item->debit - $item->credit;
            $item->balance_after = $runningBalance[$item->account_id];
        }

        $recentTransactions = $chronological
            ->filter(fn ($item) => $item->journalEntry->date >= now()->subDays(14)->toDateString())
            ->filter(fn ($item) => $this->isMainBankActivity($item))
            ->sortByDesc(fn ($item) => $item->journalEntry->date.'-'.str_pad($item->id, 10, '0', STR_PAD_LEFT))
            ->take(50)
            ->values();

        // Per-account movement over the same fortnight the feed covers, so a bank
        // card can say whether the money sitting there is moving at all.
        //
        // Computed from $chronological and NOT from $recentTransactions: that one
        // is capped at ->take(50), so per-bank totals derived from it would
        // quietly under-report every account the moment the group files more than
        // fifty entries in a fortnight — and under-report them by a different
        // amount each time. Same cutoff date as the feed, so a card and the rows
        // beneath it can never disagree about what "last 14 days" means.
        // 'last' rides along in the same pass and is deliberately NOT the
        // banks.last_transaction_date column: that column is only written when a
        // transaction is filed through the bank forms, so an account touched by a
        // journal, a transfer or an import keeps whatever date it had. Islami Bank
        // (Emi Multi) showed "never used" on 2026-08-18 next to fourteen days of
        // real movement. $chronological is sorted oldest-first, so the last write
        // per account IS its most recent entry.
        // 'total' and today's in/out ride along in the same pass for the card's
        // footer strip. 'total' is the account's LIFETIME entry count, not the
        // fortnight's — the strip answers "how much traffic does this account
        // see at all", which a 14-day figure would under-state on an account
        // that is busy monthly. Today's two figures are the day's debits and
        // credits, so a card can say whether money moved this morning without
        // opening the ledger.
        $flowCutoff = now()->subDays(14)->toDateString();
        $today = now()->toDateString();
        $bankFlow = [];
        foreach ($chronological as $item) {
            $accountId = $item->account_id;
            $entryDate = $item->journalEntry->date->format('Y-m-d');

            if (! isset($bankFlow[$accountId])) {
                $bankFlow[$accountId] = [
                    'in' => 0.0, 'out' => 0.0, 'count' => 0, 'last' => null,
                    'total' => 0, 'today_in' => 0.0, 'today_out' => 0.0,
                ];
            }
            $bankFlow[$accountId]['last'] = $entryDate;
            $bankFlow[$accountId]['total']++;

            if ($entryDate === $today) {
                $bankFlow[$accountId]['today_in'] += (float) $item->debit;
                $bankFlow[$accountId]['today_out'] += (float) $item->credit;
            }

            if ($entryDate < $flowCutoff) {
                continue;
            }
            $bankFlow[$accountId]['in'] += (float) $item->debit;
            $bankFlow[$accountId]['out'] += (float) $item->credit;
            $bankFlow[$accountId]['count']++;
        }

        // Who the money went to / came from, per feed row. Resolved once for the
        // whole fortnight — every company slice below reads the same map.
        $beneficiaries = $this->beneficiariesFor($recentTransactions);

        // Per-company slice of the same recent-activity feed, plus each company's
        // own totals, so the dashboard can switch "Recent Bank Transactions" and
        // the banner stats to a single company without another round trip.
        $companiesData = $bankGroups->map(
            fn ($companyBanks, $companyName) => $this->buildCompanyDashboardData($companyName, $companyBanks, $chronological, $recentTransactions, null, $bankFlow, $beneficiaries)
        );

        // "Epal Group" doubles as an umbrella view of every company's banks
        // grouped underneath it — display-only, there's no real parent/child
        // relationship between companies in the data. Every other company card
        // still shows just its own accounts, unchanged. Matched case-insensitively
        // since the real company name's casing varies across environments (e.g.
        // "EPAL GROUP" in production vs "Epal Group" in seeded/dev data).
        $epalGroupKey = $companiesData->keys()->first(fn ($name) => strcasecmp($name, 'Epal Group') === 0);
        if ($epalGroupKey !== null) {
            $epalGroupId = $companiesData[$epalGroupKey]['company_id'];
            $umbrella = $this->buildCompanyDashboardData($epalGroupKey, $banks, $chronological, $recentTransactions, $bankGroups, $bankFlow, $beneficiaries);
            $umbrella['company_id'] = $epalGroupId;
            $companiesData[$epalGroupKey] = $umbrella;
        }

        $companiesData = $companiesData->values();

        // Rank for the account cards' "sort by company" toggle. companies.order
        // is the ERP's own configured company sequence — the same one the sidebar
        // rail reads — so reordering companies in admin reorders these cards with
        // no change here. Name is the tie-break for anything left unordered.
        $companyOrder = Company::orderByRaw('COALESCE(`order`, 9999)')
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->flip()
            ->all();

        $beneficiaryParties = User::role(['vendor', 'agent', 'customer'])
            ->select('id', 'name')
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(function ($party) {
                $role = strtolower($party->roles->first(fn ($role) => in_array(strtolower($role->name), ['vendor', 'agent', 'customer']))->name);
                $prefix = ['vendor' => 'EP-VN-', 'agent' => 'EP-AG-', 'customer' => 'EP-CU-'][$role];

                return ['id' => $party->id, 'name' => $party->name, 'kind' => $role === 'customer' ? 'Customer' : ucfirst($role),
                    'display_id' => $prefix.str_pad($party->id, 3, '0', STR_PAD_LEFT)];
            });

        $beneficiaryOwners = User::withTrashed()
            ->whereIn('id', FinancingCapital::query()->whereNotNull('party_id')->select('party_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id_no'])
            ->map(fn ($owner) => [
                'id' => $owner->id,
                'name' => $owner->name,
                'kind' => 'Owner',
                'display_id' => $owner->employee_id_no ?: 'USER-'.str_pad((string) $owner->id, 3, '0', STR_PAD_LEFT),
            ]);

        // Employee payments carry party_type=employee on their journal legs.
        // Keep this list separate from customer/vendor/agent parties so the
        // bank beneficiary link opens the employee Accounts ledger, not Party
        // Statement or an owner's capital sheet.
        $beneficiaryEmployees = User::withTrashed()
            ->whereIn('id', JournalItem::query()
                ->where('party_type', 'employee')
                ->whereNotNull('party_id')
                ->select('party_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id_no'])
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'kind' => 'Employee',
                'display_id' => $employee->employee_id_no ?: 'EMP-'.str_pad((string) $employee->id, 3, '0', STR_PAD_LEFT),
            ]);

        return compact('banks', 'bankGroups', 'companies', 'accounts', 'totalBalance', 'recentTransactions', 'companiesData', 'companyOrder', 'beneficiaryParties', 'beneficiaryOwners', 'beneficiaryEmployees');
    }

    /**
     * Everything the dashboard needs to render one company's card, bank grid,
     * and recent-activity panel. Reused as-is for a single company, and again
     * (scoped to every bank in the system) to build the "Epal Group" umbrella.
     *
     * @param  Collection  $scopeBanks  banks to include in this view
     * @param  Collection  $chronological  ALL journal items, oldest first, with ->balance_after pre-computed per account
     * @param  Collection  $recentTransactions  last-14-days slice of $chronological
     * @param  Collection|null  $sections  when set, tags each bank card with which real company it belongs to (used for the umbrella view's grouped grid)
     * @param  array  $bankFlow  account_id => [in, out, count] over the last 14 days, prepared once by dashboardPayload()
     * @param  array  $beneficiaries  journal_item id => ['name' => ..., 'kind' => ...] for the feed rows, prepared once by beneficiariesFor()
     */
    private function buildCompanyDashboardData(
        string $displayName,
        Collection $scopeBanks,
        Collection $chronological,
        Collection $recentTransactions,
        ?Collection $sections = null,
        array $bankFlow = [],
        array $beneficiaries = []
    ): array {
        $accountIds = $scopeBanks->pluck('account_id')->filter()->values();

        // A single running balance across every account in scope combined (not
        // per individual bank) — walked chronologically over the full history
        // so it lands on the true current total, matching the banner stat.
        $scopeRunningBalance = 0;
        $scopeBalanceByItemId = [];
        foreach ($chronological as $item) {
            if (! $accountIds->contains($item->account_id)) {
                continue;
            }
            $scopeRunningBalance += $item->debit - $item->credit;
            $scopeBalanceByItemId[$item->id] = $scopeRunningBalance;
        }

        // dashboardPayload() already supplies operational rows, while the
        // printable report may supply any historical slice. Apply the same
        // rule here so screen and print never disagree.
        $inScope = $recentTransactions
            ->filter(fn ($item) => $accountIds->contains($item->account_id))
            ->filter(fn ($item) => $this->isMainBankActivity($item));

        // The petty cash pot has its own desk now — issues, spending, statements,
        // per-custodian balances — so repeating its movements on a page about banks
        // was noise. Its card and balance stay; only the feed rows go.
        //
        // Resolved from config/accounts.php rather than matched on the bank's name,
        // so pointing the pool at another account moves this with it. Resolving to
        // nothing leaves the feed exactly as it was.
        $pettyPotAccountId = $this->pettyCashPotAccountId();

        $mainActivity = $inScope
            ->when(
                $pettyPotAccountId,
                fn ($items) => $items->reject(fn ($item) => (int) $item->account_id === $pettyPotAccountId)
            );

        // Use exactly the rows the main table exposes, so its count, flow
        // totals and net change all describe the same operational activity.
        $netChange = (float) ($mainActivity->sum('debit') - $mainActivity->sum('credit'));

        $transactions = $mainActivity
            ->map(function ($item) use ($scopeBanks, $scopeBalanceByItemId, $beneficiaries) {
                $bank = $scopeBanks->firstWhere('account_id', $item->account_id);
                // balance_after is that bank's own running balance, already computed
                // chronologically above (per-account, not scope-wide).
                $bankBalanceAfter = (float) $item->balance_after;
                $bankBalanceBefore = $bankBalanceAfter - (float) $item->debit + (float) $item->credit;

                $entry = $item->journalEntry;

                return [
                    'date' => $entry->date->format('Y-m-d'),
                    'entry_time' => $entry->entry_time ? substr($entry->entry_time, 0, 5) : null,
                    'recorded_at' => optional($entry->created_at)->format('Y-m-d H:i:s'),
                    'description' => $entry->description,
                    // entry_id and note drive the description pencil. edit_scope
                    // mirrors what updateRemarks() will actually do, so the
                    // dialog can say which of the two lines it is about to
                    // change. Every row is editable; only the scope differs.
                    'entry_id' => $entry->id,
                    'source' => $entry->source,
                    'company_id' => $entry->company_id,
                    // The number a person quotes for this transaction — see txnNo().
                    'txn_no' => self::txnNo($entry),
                    // The leg itself. A transfer between two of the group's
                    // banks is ONE entry showing as TWO rows here, so a row
                    // picked for printing is identified by its leg, not its
                    // entry — see printRecentTransactions().
                    'item_id' => $item->id,
                    'note' => $item->note,
                    'edit_scope' => ($entry->source === 'bank_transaction'
                                                && ! $entry->reversed_journal_entry_id
                                                && ! $this->reversedEntryIds()->has($entry->id))
                                                    ? 'description' : 'note',
                    'debit' => (float) $item->debit,
                    'credit' => (float) $item->credit,
                    // The other side of the entry — a named party when one was
                    // recorded on any leg, otherwise the counter-account(s).
                    'beneficiary' => $beneficiaries[$item->id]['name'] ?? null,
                    'beneficiary_kind' => $beneficiaries[$item->id]['kind'] ?? null,
                    // The name typed on the deposit/withdraw form. Wins over
                    // both of the above when present.
                    'beneficiary_person' => $entry->beneficiary ?? null,
                    'beneficiary_party_id' => $entry->beneficiary_party_id ?? ($beneficiaries[$item->id]['id'] ?? null),
                    'beneficiary_method' => $entry->beneficiary_method ?? null,
                    'beneficiary_bank' => $entry->beneficiary_bank ?? null,
                    'beneficiary_ref' => $entry->beneficiary_ref ?? null,
                    'beneficiary_via' => self::paidViaLine($entry),
                    'company_balance_after' => (float) ($scopeBalanceByItemId[$item->id] ?? 0),
                    'bank_balance_before' => $bankBalanceBefore,
                    'bank_balance_after' => $bankBalanceAfter,
                    'bank' => $bank ? $this->bankCardJson($bank) : null,
                ];
            })
            ->values();

        $bankCards = $scopeBanks->map(function ($bank) use ($sections, $bankFlow) {
            // Defaulted rather than skipped: an account with nothing booked in
            // fourteen days is a fact the card shows ("quiet"), not a gap.
            $flow = $bankFlow[$bank->account_id] ?? [
                'in' => 0.0, 'out' => 0.0, 'count' => 0, 'last' => null,
                'total' => 0, 'today_in' => 0.0, 'today_out' => 0.0,
            ];
            $card = $this->bankCardJson($bank) + [
                'branch_name' => $bank->branch_name,
                'account_type' => $bank->account_type,
                'status' => (bool) $bank->status,
                'last_transaction_date' => $bank->last_transaction_date,
                'flow_in' => (float) $flow['in'],
                'flow_out' => (float) $flow['out'],
                'flow_count' => (int) $flow['count'],
                'txn_count' => (int) $flow['total'],
                'today_in' => (float) $flow['today_in'],
                'today_out' => (float) $flow['today_out'],
                'last_movement' => $flow['last'],
            ] + $this->companyRail($bank);
            if ($sections) {
                $card['section'] = optional($bank->company)->name ?? 'Unassigned';
            }

            return $card;
        })->values();

        return [
            'company_id' => optional($scopeBanks->first())->company_id,
            'name' => $displayName,
            'total_balance' => (float) $scopeBanks->where('type', '!=', 'credit_card')->sum('journal_balance'),
            'account_count' => $scopeBanks->count(),
            'net_change' => $netChange,
            'transactions' => $transactions,
            'banks' => $bankCards,
        ];
    }

    /**
     * Decide whether a journal leg belongs in the group-wide operational feed.
     *
     * Internal transfers and both halves of a reversal stay in each bank's
     * full ledger and in the GL closing balance. Hiding them only here keeps
     * the main received/paid totals from counting money that merely moved
     * between our own accounts or was subsequently undone.
     */
    private function isMainBankActivity(JournalItem $item): bool
    {
        $entry = $item->journalEntry;

        if (! $entry || $entry->source === 'bank_transfer') {
            return false;
        }

        if ($entry->reversed_journal_entry_id) {
            return false;
        }

        return ! $this->reversedEntryIds()->has($entry->id);
    }

    /**
     * The chart account the petty cash pot posts to, or null if it is not set up.
     *
     * Memoised because buildCompanyDashboardData() runs once per company and this
     * would otherwise be the same lookup thirteen times on one page load. Null on
     * any failure, which leaves the transaction feed untouched — a missing account
     * must not be the reason the bank dashboard changes what it shows.
     */
    private ?int $pettyCashPotAccountIdCache = null;

    private bool $pettyCashPotAccountResolved = false;

    /**
     * The colour a bank card wears down its left edge: its OWNING COMPANY's
     * accent, not the bank's own brand.
     *
     * Two accounts at the same bank belong to different concerns and read as
     * one another on a wall of cards; two accounts of the same concern at
     * different banks belong together and did not. Colouring by company is what
     * makes "all of Wood Art" a thing the eye finds without reading a word —
     * green for the interiors house, blue for Travels, violet for IT.
     *
     * Deliberately company_icon()'s accent and not a table of my own: that
     * helper already assigns each concern its colour (and the sidebar rail
     * already paints it), so a bank card and the sidebar can never disagree
     * about what colour Wood Art is, and a company added tomorrow gets a rail
     * with nothing to configure. The reference build keys the same rail off a
     * hash of the BANK's name (ui.colorFor) — this is the one place the ERP
     * departs from it, on the owner's instruction (2026-08-19).
     *
     * The accent is used AT FULL STRENGTH, and that is a deliberate departure
     * from the reference (2026-08-19). The reference mutes its rail 64% toward
     * #7c828c because its colour is a hash of the bank's NAME — arbitrary, so
     * it has to be tamed before it can be trusted on screen. Ours is a curated
     * brand accent that is already print-safe, so the same muting bought
     * nothing and cost the one thing the rail exists for: three of the concerns
     * banking here (Group #1a43bf, Travels #2f6bff, IT #7b5cff) are blues, and
     * pulling them all a third of the way to grey made them indistinguishable
     * at 5px. Muted, the owner's report was "I see all color same". Unmuted,
     * they read as navy / azure / violet.
     *
     * Handed over as "r, g, b" as well as hex so the sheet can build every tint
     * of it with plain rgba() and need no color-mix() support.
     *
     * Never throws: a bank with no company, or a helper that is somehow not
     * loaded, degrades to the neutral slate every unmatched company already
     * gets. A missing company must not be the reason this page 500s.
     *
     * `company_short` rides along because it is the same lookup: the card prints
     * the concern's name at 11.5px in a 125px column, where "EPAL TRAVELS &
     * CONSULTANCY" is clipped mid-word but its own short_name, "EPAL TRAVELS",
     * is not. Null where no short name is set, and the card falls back to the
     * full one.
     *
     * @return array{company_ink:string, company_ink_rgb:string, company_short:?string, company_glyph:string}
     */
    private function companyRail(Bank $bank): array
    {
        $accent = '#64748b';
        $short = null;
        // The neutral is a plain building, so a bank whose company row has gone
        // still gets a badge rather than an empty box where one should be.
        $glyph = 'bi bi-building';

        try {
            if ($bank->company) {
                $short = trim((string) ($bank->company->short_name ?? '')) ?: null;

                if (function_exists('company_icon')) {
                    $ico = company_icon($bank->company);
                    $accent = $ico['accent'] ?? $accent;
                    $glyph = $ico['class'] ?? $glyph;
                }
            }
        } catch (\Throwable $e) {
            // keep the neutral
        }

        $rgb = sscanf(ltrim($accent, '#'), '%2x%2x%2x');
        if (! is_array($rgb) || count(array_filter($rgb, 'is_int')) !== 3) {
            $rgb = [100, 116, 139];   // #64748b
        }

        return [
            'company_ink' => sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]),
            'company_ink_rgb' => implode(', ', $rgb),
            'company_short' => $short,
            'company_glyph' => $glyph,
        ];
    }

    /**
     * Who each feed row's money went to or came from, keyed by journal_item id.
     *
     * A bank leg on its own says nothing about the other party; that lives on
     * the entry's other legs. Two sources, in order:
     *
     *   1. A party recorded on any leg of the entry (party_type + party_id) —
     *      the bank leg's own first, then the others. Names come from the same
     *      tables the party voucher reads: customer → customers, supplier →
     *      suppliers, everything else (agent, vendor, employee, others) → users.
     *      A 'customer' id that has no customers row is retried against users,
     *      because the sale modules write a User id under that type.
     *   2. Otherwise the account(s) on the opposite side of the entry — "OFFICE
     *      CASH" for a transfer, "Salary Expense" for a payroll run.
     *
     * Bulk: one query for the legs, at most three for the names. Wrapped so a
     * missing table or a bad id costs the column, never the dashboard.
     *
     * @return array<int, array{name: string, kind: string, id?: int}>
     */
    private function beneficiariesFor(Collection $recentTransactions): array
    {
        try {
            $entryIds = $recentTransactions->pluck('journal_entry_id')->unique()->values();
            if ($entryIds->isEmpty()) {
                return [];
            }

            $legsByEntry = JournalItem::with('account:id,name')
                ->whereIn('journal_entry_id', $entryIds)
                ->get()
                ->groupBy('journal_entry_id');

            // Collect every party reference first so the names load in bulk.
            $wanted = ['customer' => [], 'supplier' => [], 'user' => []];
            foreach ($legsByEntry as $legs) {
                foreach ($legs as $leg) {
                    if (! $leg->party_type || ! $leg->party_id) {
                        continue;
                    }
                    $bucket = in_array($leg->party_type, ['customer', 'supplier'], true) ? $leg->party_type : 'user';
                    $wanted[$bucket][] = (int) $leg->party_id;
                    if ($bucket === 'customer') {
                        $wanted['user'][] = (int) $leg->party_id; // fallback, see docblock
                    }
                }
            }

            $names = [
                'customer' => $wanted['customer'] ? Customer::whereIn('id', array_unique($wanted['customer']))->pluck('name', 'id')->all() : [],
                'supplier' => $wanted['supplier'] ? Supplier::whereIn('id', array_unique($wanted['supplier']))->pluck('name', 'id')->all() : [],
                'user' => $wanted['user'] ? User::whereIn('id', array_unique($wanted['user']))->pluck('name', 'id')->all() : [],
            ];

            $partyName = function ($leg) use ($names): ?array {
                if (! $leg->party_type || ! $leg->party_id) {
                    return null;
                }
                $type = $leg->party_type;
                $id = (int) $leg->party_id;
                $name = match ($type) {
                    'customer' => $names['customer'][$id] ?? $names['user'][$id] ?? null,
                    'supplier' => $names['supplier'][$id] ?? null,
                    default => $names['user'][$id] ?? null,
                };

                return $name ? ['name' => $name, 'kind' => $type, 'id' => $id] : null;
            };

            $out = [];
            foreach ($recentTransactions as $item) {
                $legs = $legsByEntry[$item->journal_entry_id] ?? collect();

                // 1. A recorded party — this leg first, then any other leg.
                $found = $partyName($item);
                if (! $found) {
                    foreach ($legs as $leg) {
                        if ($leg->id !== $item->id && ($found = $partyName($leg))) {
                            break;
                        }
                    }
                }

                // 2. The counter-account(s): legs on the opposite side.
                if (! $found) {
                    $bankIsDebit = (float) $item->debit > 0;
                    $counter = $legs
                        ->filter(fn ($leg) => $leg->id !== $item->id
                            && ($bankIsDebit ? (float) $leg->credit > 0 : (float) $leg->debit > 0))
                        ->map(fn ($leg) => optional($leg->account)->name)
                        ->filter()
                        ->unique()
                        ->values();
                    if ($counter->isNotEmpty()) {
                        $found = ['name' => $counter->implode(', '), 'kind' => 'account'];
                    }
                }

                if ($found) {
                    $out[$item->id] = $found;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            \Log::warning('Bank dashboard beneficiary lookup failed: '.$e->getMessage());

            return [];
        }
    }

    private function pettyCashPotAccountId(): ?int
    {
        if ($this->pettyCashPotAccountResolved) {
            return $this->pettyCashPotAccountIdCache;
        }

        $this->pettyCashPotAccountResolved = true;

        try {
            $code = config('accounts.petty_cash_pool');

            $this->pettyCashPotAccountIdCache = $code
                ? Account::where('code', $code)->value('id')
                : null;
        } catch (\Throwable $e) {
            $this->pettyCashPotAccountIdCache = null;
        }

        return $this->pettyCashPotAccountIdCache;
    }

    /**
     * Live search box behind the dashboard's bank picker — mirrors
     * PartyStatementController::search().
     */
    public function search(Request $request)
    {
        $q = $request->q;

        $query = Bank::where('status', true)
            ->with('company')
            ->select('banks.*')
            ->selectSub(function ($sub) {
                $sub->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0)')
                    ->from('journal_items')
                    ->whereColumn('journal_items.account_id', 'banks.account_id')
                    ->whereNull('journal_items.deleted_at');
            }, 'journal_balance');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('account_name', 'like', "%{$q}%")
                    ->orWhere('account_number', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->limit(30)->get()->map(fn ($bank) => $this->bankCardJson($bank))
        );
    }

    /**
     * JSON ledger load behind the dashboard's inline statement panel — mirrors
     * PartyStatementController::load(), built on the same row math as statement().
     */
    public function load(Request $request)
    {
        $request->validate(['bank_id' => 'required|exists:banks,id']);

        $bank = Bank::with('company')->findOrFail($request->bank_id);

        [$rows, $openingBalance, $totalDebit, $totalCredit, $closingBalance] = $this->buildBankStatementRows(
            $bank, $request->input('from'), $request->input('to')
        );

        [$balance, $todayIn, $todayOut, $txnTotal, $lastTxn] = $this->bankHeaderFigures($bank);

        // The company rail's icon rules, reused verbatim, so the badge on this
        // card is the same glyph and accent the sidebar already shows for that
        // company. company_icon() never throws and always returns a class.
        $companyIcon = $bank->company ? company_icon($bank->company) : null;

        return response()->json([
            'bank' => [
                'id' => $bank->id,
                'account_id' => $bank->account_id,
                'name' => $bank->name,
                'account_name' => $bank->account_name,
                'account_number' => $bank->account_number,
                'branch_name' => $bank->branch_name,
                'type' => $bank->type,
                'account_type' => $bank->account_type,
                'company_name' => optional($bank->company)->name,
                'company_icon' => $companyIcon['class'] ?? null,
                'company_accent' => $companyIcon['accent'] ?? null,
                'balance' => $balance,
                'initials' => $this->initials($bank->name),
            ],
            'today_in' => $todayIn,
            'today_out' => $todayOut,
            'txn_total' => $txnTotal,
            'last_txn' => $lastTxn,
            'opening_balance' => (float) $openingBalance,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => (float) $closingBalance,
            'date_from' => $request->input('from'),
            'date_to' => $request->input('to'),
            'transactions' => $rows->values(),
        ]);
    }

    /**
     * The five figures the ledger header prints that the period statement cannot
     * supply, because none of them are period-scoped:
     *
     *   balance    the account's live balance, all time
     *   todayIn    what arrived today, whatever period is on screen
     *   todayOut   what left today, likewise
     *   txnTotal   entries on this account, all time
     *   lastTxn    the most recent entry — direction, amount, purpose, when
     *
     * "Today In / Today Out" must mean today even when the user is looking at
     * January, so they are counted here rather than summed off the visible rows.
     *
     * @return array{0:float,1:float,2:float,3:int,4:?array}
     */
    private function bankHeaderFigures(Bank $bank): array
    {
        if (! $bank->account_id) {
            return [0.0, 0.0, 0.0, 0, null];
        }

        $agg = JournalItem::where('account_id', $bank->account_id)
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c, COUNT(*) as n')
            ->first();

        $balance = (float) ($agg->d ?? 0) - (float) ($agg->c ?? 0);
        $txnTotal = (int) ($agg->n ?? 0);

        $todayAgg = JournalItem::where('account_id', $bank->account_id)
            ->whereHas('journalEntry', fn ($q) => $q->whereDate('date', now()->toDateString()))
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        // Ordered by the entry's DATE first and its id second — the same order
        // the statement rows use, so "last" here is the row that prints last
        // there rather than whichever was typed in most recently.
        $last = JournalItem::with('journalEntry')
            ->where('account_id', $bank->account_id)
            ->orderByDesc(
                JournalEntry::select('date')
                    ->whereColumn('journal_entries.id', 'journal_items.journal_entry_id')
                    ->limit(1)
            )
            ->orderByDesc('id')
            ->first();

        $lastTxn = null;
        if ($last && $last->journalEntry) {
            $entry = $last->journalEntry;
            $isIn = (float) $last->debit > 0;

            $lastTxn = [
                'direction' => $isIn ? 'in' : 'out',
                'amount' => $isIn ? (float) $last->debit : (float) $last->credit,
                // The note is the hand-written half and wins when it is there;
                // the description is the generated sentence behind it.
                'purpose' => $last->note ?: ($entry->description ?: ($entry->reference ?: null)),
                'date' => optional($entry->date)->format('Y-m-d'),
                'time' => optional($entry->created_at)->format('Y-m-d H:i:s'),
            ];
        }

        return [
            $balance,
            (float) ($todayAgg->d ?? 0),
            (float) ($todayAgg->c ?? 0),
            $txnTotal,
            $lastTxn,
        ];
    }

    private function initials(string $name): string
    {
        $words = array_slice(explode(' ', $name), 0, 2);

        return strtoupper(implode('', array_map(fn ($w) => $w[0] ?? '', $words)));
    }

    /**
     * The JSON shape used everywhere a bank gets passed into JS — the search
     * dropdown, "open this bank's ledger" row/card clicks, etc.
     */
    private function bankCardJson(Bank $bank): array
    {
        return [
            'id' => $bank->id,
            'account_id' => $bank->account_id,
            'name' => $bank->name,
            'account_name' => $bank->account_name,
            'account_number' => $bank->account_number,
            'type' => $bank->type,
            'company_name' => optional($bank->company)->name,
            // Liability ledgers naturally close credit (negative under the
            // dashboard's debit-minus-credit convention). On a credit-card
            // card, present that as a positive outstanding amount.
            'balance' => $bank->type === 'credit_card'
                ? abs((float) $bank->journal_balance)
                : (float) $bank->journal_balance,
            'initials' => $this->initials($bank->name),
        ];
    }

    /**
     * Running-balance statement for a single bank account (same math as
     * ReportController::accountLedgerV1, scoped to this bank's linked account).
     */
    public function statement($role, Bank $bank, Request $request)
    {
        [$rows, $openingBalance, $totalDebit, $totalCredit, $closingBalance] = $this->buildBankStatementRows(
            $bank,
            $request->input('date_from'),
            $request->input('date_to')
        );

        return view('banks.statement', compact(
            'bank',
            'rows',
            'openingBalance',
            'closingBalance',
            'totalDebit',
            'totalCredit'
        ));
    }

    /**
     * Printable, letterhead-styled version of the statement — same layout family
     * as party-statement.print-statement / visa-sales.voucher.
     */
    public function printStatement($role, Bank $bank, Request $request)
    {
        [$rows, $openingBalance, $totalDebit, $totalCredit, $closingBalance] = $this->buildBankStatementRows(
            $bank,
            $request->input('date_from'),
            $request->input('date_to')
        );

        $company = Company::find($bank->company_id) ?? Company::first();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // `entries` = the rows ticked on screen, by journal entry id. The rows
        // are cut down AFTER the running balance is computed over the whole
        // period, so each printed balance is still the account's real balance
        // at that point. The period's opening/closing figures describe the
        // whole period, not the picked rows, so the sheet drops them and the
        // totals become the sum of what is printed.
        $selected = $this->idList($request->input('entries'));
        if ($selected) {
            $rows = $rows->filter(fn ($r) => in_array((int) $r['entry_id'], $selected, true))->values();
            $totalDebit = (float) $rows->sum('debit');
            $totalCredit = (float) $rows->sum('credit');
        }
        $selection = $selected ? $rows->count() : 0;

        return view('banks.print-statement', compact(
            'bank',
            'company',
            'rows',
            'openingBalance',
            'closingBalance',
            'totalDebit',
            'totalCredit',
            'dateFrom',
            'dateTo',
            'selection'
        ));
    }

    /**
     * Printable sheet for the dashboard's Recent Transactions table — every
     * account of one company (or the whole group) on one page, with the same
     * columns the screen shows: bank, description, beneficiary, in, out and the
     * company-wide balance after each row.
     *
     * Three ways in, mirroring the screen: a period (`date_from`/`date_to`),
     * a single day (both the same), or the ticked rows (`items`, journal_item
     * ids). The period is not capped at the feed's fourteen days — it is a
     * request for a printout, and the screen's window is a display choice.
     *
     * `banks` is the list of bank ids the active company card shows, so the
     * sheet covers exactly what the user was looking at; it is intersected
     * with the accounts this user may see, never trusted on its own.
     */
    public function printRecentTransactions($role, Request $request)
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAllBanks = auth()->user()->can('view all bank');

        $banksQuery = Bank::where('status', true)
            ->with('company')
            ->select('banks.*')
            ->selectSub(function ($q) {
                $q->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0)')
                    ->from('journal_items')
                    ->whereColumn('journal_items.account_id', 'banks.account_id')
                    ->whereNull('journal_items.deleted_at');
            }, 'journal_balance')
            ->orderBy('name');
        if (! $canViewAllBanks && ! empty($userCompanyId)) {
            $banksQuery->where('company_id', $userCompanyId);
        }
        if ($bankIds = $this->idList($request->input('banks'))) {
            $banksQuery->whereIn('id', $bankIds);
        }
        $banks = $banksQuery->get();
        $accountIds = $banks->pluck('account_id')->filter()->values();

        $dateFrom = $request->input('date_from') ?: null;
        $dateTo = $request->input('date_to') ?: null;
        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }
        $itemIds = $this->idList($request->input('items'));

        // Same walk as dashboardPayload(): the whole history, oldest first, so
        // every printed balance is the true running figure — not one that
        // starts from zero at the top of the period.
        $chronological = JournalItem::with('journalEntry')
            ->whereIn('account_id', $accountIds)
            ->get()
            ->filter(fn ($item) => $item->journalEntry)
            ->sortBy(fn ($item) => $item->journalEntry->date.'-'.str_pad($item->id, 10, '0', STR_PAD_LEFT))
            ->values();

        $runningBalance = [];
        foreach ($chronological as $item) {
            $runningBalance[$item->account_id] = ($runningBalance[$item->account_id] ?? 0) + $item->debit - $item->credit;
            $item->balance_after = $runningBalance[$item->account_id];
        }

        $slice = $chronological
            ->filter(function ($item) use ($dateFrom, $dateTo, $itemIds) {
                if ($itemIds) {
                    return in_array((int) $item->id, $itemIds, true);
                }
                $d = $item->journalEntry->date->format('Y-m-d');

                return (! $dateFrom || $d >= $dateFrom) && (! $dateTo || $d <= $dateTo);
            })
            ->sortByDesc(fn ($item) => $item->journalEntry->date.'-'.str_pad($item->id, 10, '0', STR_PAD_LEFT))
            ->values();

        $companyName = trim((string) $request->input('company')) ?: (optional($banks->first()->company ?? null)->name ?? 'All Companies');
        $data = $this->buildCompanyDashboardData(
            $companyName, $banks, $chronological, $slice, null, [], $this->beneficiariesFor($slice)
        );

        // Split each description the way the screen does: the tail that names
        // the beneficiary moves to its own column and leaves the description.
        $rows = collect($data['transactions'])->map(fn ($t) => array_merge($t, $this->beneficiaryFields(
            $t['description'] ?? '',
            isset($t['beneficiary']) ? ['name' => $t['beneficiary'], 'kind' => $t['beneficiary_kind']] : null,
            $t['beneficiary_person'] ?? null,
            $t['bank']['name'] ?? null
        )));

        // The heading's company: the card the user printed from, if it is a
        // real company; the umbrella prints the group's first company for the
        // letterhead, the same one the statement sheet would fall back to.
        $company = ($cid = (int) $request->input('company_id'))
            ? Company::find($cid)
            : null;
        $company = $company ?? Company::first();

        return view('banks.print-recent', [
            'company' => $company,
            'companyName' => $companyName,
            'banks' => $banks,
            'rows' => $rows,
            'totalDebit' => (float) $rows->sum('debit'),
            'totalCredit' => (float) $rows->sum('credit'),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'selection' => $itemIds ? $rows->count() : 0,
        ]);
    }

    /**
     * "1,2,3" or [1,2,3] from the query string → distinct positive ints.
     * Anything else is dropped rather than passed to a whereIn.
     */
    /**
     * The transaction number shown under every description and searchable
     * on every table. Always the same shape: three letters for the kind of
     * movement, a dash, eight digits — "DEP-00000972".
     *
     * The digits are the journal entry id: the one identifier every movement
     * already has, never reused, and the same on both rows of a transfer —
     * one transaction, one number. `reference` is not used for this: it is
     * typed by the user on some entries (a name, a cheque number) and
     * generated on others, so it is neither unique nor always present.
     *
     * The letters come from the entry's source; a deposit and a withdrawal
     * are told apart by the description our own form writes, and a reversal
     * is REV whatever it reverses. Unknown sources read JRN.
     */
    public static function txnNo(JournalEntry $entry): string
    {
        return self::txnType($entry).'-'.sprintf('%08d', $entry->id);
    }

    private const TXN_TYPES = [
        'bank_transfer' => 'TRF',
        'bank' => 'OPN',   // opening balance
        'expense' => 'EXP',
        'expense_reimbursement' => 'EXP',
        'salary' => 'SLR',
        'salary_payment' => 'SLR',
        'employee_due_settlement' => 'SLR',
        'employee_ledger' => 'SLR',
        'sale' => 'SLE',
        'ticket_sale' => 'SLE',
        'direct_sale' => 'SLE',
        'purchase' => 'PUR',
        'ticket_purchase' => 'PUR',
        'ticket_refund' => 'RFD',
        'payment_schedule' => 'PAY',
        'schedule_payment_settlement' => 'PAY',
        'party_payment' => 'PAY',
        'financing_loan' => 'LON',
        'loan' => 'LON',
        'loan_repayment' => 'LON',
        'petty_cash' => 'PTY',
    ];

    private static function txnType(JournalEntry $entry): string
    {
        if ($entry->reversed_journal_entry_id) {
            return 'REV';
        }
        if ($entry->source === 'bank_transaction') {
            $d = (string) $entry->description;
            if (str_starts_with($d, 'Deposit to')) {
                return 'DEP';
            }
            if (str_starts_with($d, 'Withdrawal from')) {
                return 'WDR';
            }

            return 'BNK';
        }

        return self::TXN_TYPES[$entry->source] ?? 'JRN';
    }

    private function idList($raw): array
    {
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(array_map('intval', $parts), fn ($n) => $n > 0)));
    }

    /**
     * The three Beneficiary-column fields for a row, from its description and
     * the resolved party/counter-account (beneficiariesFor()). The description
     * itself is left whole on the row — search, the pencil dialog and the
     * ledger still see the full text; only the display splits it.
     *
     * Order of authority: the name typed on the form (journal_entries.
     * beneficiary), then a name split off the description's tail, then the
     * party or counter-account resolved from the entry's legs.
     *
     * @return array{description_head: string, beneficiary: ?string, beneficiary_kind: ?string, beneficiary_name: string, beneficiary_person: ?string}
     */
    private function beneficiaryFields(?string $description, ?array $resolved, ?string $person = null, ?string $bankName = null): array
    {
        $person = trim((string) $person);
        if ($person !== '') {
            $brief = $this->briefDescription((string) $description, $bankName);

            return [
                'description_head' => (string) $description,
                'description_brief' => $brief[0],
                'description_muted' => $brief[1],
                'beneficiary' => $resolved['name'] ?? null,
                'beneficiary_kind' => $resolved['kind'] ?? null,
                'beneficiary_name' => $person,
                'beneficiary_person' => $person,
            ];
        }
        [$head, $tail] = $this->splitBeneficiary((string) $description);
        $brief = $this->briefDescription($head, $bankName);

        return [
            'description_head' => $head,
            'description_brief' => $brief[0],
            'description_muted' => $brief[1],
            'beneficiary' => $resolved['name'] ?? null,
            'beneficiary_kind' => $tail !== '' ? null : ($resolved['kind'] ?? null),
            'beneficiary_name' => $tail !== '' ? $tail : (string) ($resolved['name'] ?? ''),
            'beneficiary_person' => null,
        ];
    }

    /**
     * PHP twin of briefDescription() in banks/dashboard.blade.php — keep in
     * step. The row's own bank is taken out of the text (the Bank column
     * already names it), leaving the purpose, or just the movement type when
     * none was typed. Returns [text, muted]; muted marks a bare type word.
     */
    private function briefDescription(string $head, ?string $bankName): array
    {
        if (! preg_match('/^(Deposit to|Withdrawal from|Bank transfer —)\s+(.*)$/u', $head, $m)) {
            return [$head, false];
        }
        $type = $m[1] === 'Bank transfer —' ? 'Bank transfer' : explode(' ', $m[1])[0];
        $rest = trim($m[2]);
        if ($bankName !== null && $bankName !== '') {
            if (str_starts_with($rest, $bankName)) {
                $rest = trim(mb_substr($rest, mb_strlen($bankName)));
            } else {
                return [$head, false];
            }
        }
        if (str_starts_with($rest, '—')) {
            $purpose = trim(mb_substr($rest, 1));

            return [$purpose !== '' ? $purpose : $type, $purpose === ''];
        }

        return [$rest !== '' ? $type.' '.$rest : $type, $rest === ''];
    }

    /**
     * The three pay-detail columns from a request, normalised: a method of
     * cash carries no bank or number, and blank strings are stored as null.
     */
    private function payDetailsFrom(Request $request): array
    {
        $method = $request->input('pay_method') ?: null;
        $bank = trim((string) $request->input('pay_bank'));
        $ref = trim((string) $request->input('pay_ref'));
        if ($method === 'cash' || $method === null) {
            $bank = '';
            $ref = '';
        }

        return [
            'beneficiary_method' => $method,
            'beneficiary_bank' => $bank !== '' ? $bank : null,
            'beneficiary_ref' => $ref !== '' ? $ref : null,
        ];
    }

    /**
     * The line under the beneficiary's name: "Cash", "Bank · DUTCH BANGLA ·
     * 1234567" or "Cheque · CITY BANK · No. 00123". Null when nothing was
     * recorded, so the tables fall back to the party kind.
     */
    public static function paidViaLine(JournalEntry $entry): ?string
    {
        $method = $entry->beneficiary_method ?? null;
        if (! $method) {
            return null;
        }
        $parts = [match ($method) {
            'cash' => 'Cash', 'bank' => 'Bank', 'cheque' => 'Cheque', default => ucfirst($method)
        }];
        if ($entry->beneficiary_bank) {
            $parts[] = $entry->beneficiary_bank;
        }
        if ($entry->beneficiary_ref) {
            $parts[] = ($method === 'cheque' ? 'No. ' : '').$entry->beneficiary_ref;
        }

        return implode(' · ', $parts);
    }

    /** Whether the 2026_09_14_000002 columns exist on THIS server — same discipline as journalHasBeneficiaryColumn(). */
    private function journalHasPayDetailColumns(): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $has = Schema::hasColumn('journal_entries', 'beneficiary_method');
            } catch (\Throwable $e) {
                $has = false;
            }
        }

        return $has;
    }

    /**
     * Whether journal_entries.beneficiary exists on THIS server. The column
     * arrives by migration and deploys lag; until it is there the form still
     * files, with the name folded into the remark, and nothing here throws.
     */
    private function journalHasBeneficiaryColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $has = Schema::hasColumn('journal_entries', 'beneficiary');
            } catch (\Throwable $e) {
                $has = false;
            }
        }

        return $has;
    }

    /**
     * PHP twin of beneficiaryOf() in banks/dashboard.blade.php — keep the two
     * in step. Returns [head, tail]: the tail is the text after the last "→"
     * (a transfer's receiving account) or the last " — " (the typed remark);
     * bookkeeping suffixes the forms append stay on the head.
     */
    private function splitBeneficiary(string $full): array
    {
        $suffix = '';
        $d = preg_replace_callback('/\s*(\(incl\.[^)]*\)|including charge\/VAT)\s*$/iu', function ($m) use (&$suffix) {
            $suffix = $m[0];

            return '';
        }, $full);

        $cut = false;
        $sepLen = 0;
        if (($p = mb_strrpos($d, '→')) !== false) {
            $cut = $p;
            $sepLen = 1;
        } elseif (($p = mb_strrpos($d, ' — ')) !== false) {
            $cut = $p;
            $sepLen = 3;
        }
        if ($cut === false) {
            return [$full, ''];
        }
        $tail = trim(mb_substr($d, $cut + $sepLen));
        if ($tail === '') {
            return [$full, ''];
        }
        $head = trim(mb_substr($d, 0, $cut));
        // "NAME - purpose" (also – / or :): the name is the beneficiary, the
        // purpose goes back on the description. A colon splits anywhere;
        // dash, en dash and slash need a space on at least one side, so
        // "Abdul-Karim" stays one name. Transfer tails (→) are account
        // names and are never split.
        if ($sepLen === 3 && (preg_match('/^(.+?)\s*:\s*(.+)$/u', $tail, $m)
                             || preg_match('/^(.+?)(?:\s+[-–\/]\s*|\s*[-–\/]\s+)(.+)$/u', $tail, $m))) {
            $tail = trim($m[1]);
            $head .= ' — '.trim($m[2]);
        }

        return [trim($head.$suffix), $tail];
    }

    /**
     * Running-balance rows for a bank's statement — shared by the interactive
     * statement() view and the printable printStatement() view.
     *
     * @return array{0: Collection, 1: float, 2: float, 3: float, 4: float}
     */
    private function buildBankStatementRows(Bank $bank, ?string $dateFrom, ?string $dateTo): array
    {
        $openingBalance = 0;
        $rows = collect();

        if ($bank->account_id) {
            if ($dateFrom) {
                $beforeItems = JournalItem::where('account_id', $bank->account_id)
                    ->whereHas('journalEntry', fn ($q) => $q->whereDate('date', '<', $dateFrom))
                    ->get();

                $openingBalance = $beforeItems->sum('debit') - $beforeItems->sum('credit');
            }

            $items = JournalItem::with('journalEntry.dateEdits')
                ->where('account_id', $bank->account_id)
                ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                    if ($dateFrom) {
                        $q->whereDate('date', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $q->whereDate('date', '<=', $dateTo);
                    }
                })
                ->orderBy(
                    JournalEntry::select('date')
                        ->whereColumn('journal_entries.id', 'journal_items.journal_entry_id')
                        ->limit(1)
                )
                // Same-day entries with no explicit entry_time fall back to
                // created_at — when they were actually typed in, which is
                // "good enough" order for entries that were entered close to
                // when they happened. entry_time (see updateJournalDate())
                // overrides that for the specific entries someone has
                // corrected to match the real statement's order. The frontend
                // ledger's txnInstant() sorts by this exact same rule, so a
                // row's position here always agrees with the running balance
                // printed against it.
                ->orderBy(
                    JournalEntry::selectRaw("COALESCE(CONCAT(date, ' ', entry_time), created_at)")
                        ->whereColumn('journal_entries.id', 'journal_items.journal_entry_id')
                        ->limit(1)
                )
                ->orderBy('id')
                ->get();

            $entryIds = $items->pluck('journal_entry_id')->unique()->values();

            // Entries that already have a reversal posted against them.
            $reversedEntryIds = JournalEntry::whereIn('reversed_journal_entry_id', $entryIds)
                ->pluck('reversed_journal_entry_id')
                ->flip();

            // Who the money went to / came from, for the Beneficiary column —
            // same resolver and same description split as the dashboard feed.
            $beneficiaries = $this->beneficiariesFor($items);

            $runningBalance = $openingBalance;
            $rows = $items->map(function ($item) use (&$runningBalance, $reversedEntryIds, $beneficiaries, $bank) {
                $runningBalance += $item->debit - $item->credit;
                $entry = $item->journalEntry;
                $isReversal = (bool) $entry->reversed_journal_entry_id;
                $isReversed = $reversedEntryIds->has($entry->id);

                return [
                    'entry_id' => $entry->id,
                    'txn_no' => self::txnNo($entry),
                    // Y-m-d string rather than the Carbon cast: the ledger table
                    // prints it verbatim, so no browser timezone parsing can
                    // shift the day. recorded_at is when the entry was written —
                    // journal_entries.date is a DATE column with no clock time.
                    'date' => optional($entry->date)->format('Y-m-d'),
                    // Set only when someone deliberately timed this entry to
                    // position it against same-day neighbours — see the ordering
                    // above and updateJournalDate(). Null for almost every row.
                    'entry_time' => $entry->entry_time ? substr($entry->entry_time, 0, 5) : null,
                    'recorded_at' => optional($entry->created_at)->format('Y-m-d H:i:s'),
                    'reference' => $entry->reference,
                    'source' => $entry->source,
                    'description' => $entry->description,
                    'note' => $item->note,
                    'debit' => $item->debit,
                    'credit' => $item->credit,
                    'balance' => $runningBalance,
                    'is_reversal' => $isReversal,
                    'is_reversed' => $isReversed,
                    // The entry this one reverses, so a statement comparison
                    // can pair the two and treat them as cancelled — see
                    // BankStatementComparer.
                    'reversed_entry_id' => $entry->reversed_journal_entry_id,
                    // Which line the pencil edits — see updateRemarks().
                    'edit_scope' => (! $isReversal && ! $isReversed && $entry->source === 'bank_transaction')
                                        ? 'description' : 'note',
                    'can_reverse' => in_array($entry->source, ['bank_transaction', 'bank_transfer'], true)
                                        && ! $isReversal && ! $isReversed,
                    // Orthogonal to reversal status — a wrong date can happen on
                    // any entry regardless, reversed or not. Limited to the
                    // sources updateJournalDate() knows how to keep in sync — see
                    // DATE_EDITABLE_SOURCES.
                    'can_edit_date' => in_array($entry->source, self::DATE_EDITABLE_SOURCES, true),
                    'date_edited' => $entry->dateEdits->isNotEmpty(),
                ] + $this->beneficiaryFields($entry->description, $beneficiaries[$item->id] ?? null, $entry->beneficiary ?? null, $bank->name)
                  + [
                      // The party this entry was billed to or paid, when one was
                      // picked on the form. The dashboard feed already carried it
                      // (see buildCompanyDashboardData); the statement needs it for
                      // the same reason — it is what makes the Beneficiary cell's
                      // name and code a link into that party's own ledger.
                      'beneficiary_party_id' => $entry->beneficiary_party_id ?? ($beneficiaries[$item->id]['id'] ?? null),
                      'source' => $entry->source,
                      'beneficiary_method' => $entry->beneficiary_method ?? null,
                      'beneficiary_bank' => $entry->beneficiary_bank ?? null,
                      'beneficiary_ref' => $entry->beneficiary_ref ?? null,
                      'beneficiary_via' => self::paidViaLine($entry),
                  ];
            });
        }

        $totalDebit = (float) $rows->sum('debit');
        $totalCredit = (float) $rows->sum('credit');
        $closingBalance = $rows->isNotEmpty() ? $rows->last()['balance'] : $openingBalance;

        return [$rows, $openingBalance, $totalDebit, $totalCredit, $closingBalance];
    }

    /**
     * Record a manual deposit/withdrawal against a bank account — journal-integrated,
     * mirrors PortalManagementController::updateBalance()'s shape.
     */
    public function recordTransaction(Request $request, $role, Bank $bank)
    {
        $userCompanyId = auth()->user()->company_id;
        abort_if(
            ! auth()->user()->can('view all bank')
                && ! empty($userCompanyId)
                && (int) $bank->company_id !== (int) $userCompanyId,
            403,
            'That account belongs to a different company.'
        );

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:deposit,withdraw',
            'entry_context' => 'nullable|in:payment_entry',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'contra_account_id' => 'required|exists:accounts,id',
            'expense_department_id' => 'nullable|exists:expense_departments,id',
            'reference' => 'nullable|string|max:100',
            // Who the money was for, and what for. `remarks` is the older
            // single box and still accepted: it is read as the purpose.
            'beneficiary' => 'nullable|string|max:150',
            'beneficiary_party_id' => 'nullable|integer|exists:users,id',
            'purpose' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:255',
            // How the other side paid or was paid: cash, their bank account,
            // or a cheque. Bank name and the account / cheque number ride
            // along for the last two.
            'pay_method' => 'nullable|in:cash,bank,cheque',
            'pay_bank' => 'nullable|string|max:150',
            'pay_ref' => 'nullable|string|max:100',
            // The bank's own cut. Both optional and both default to zero, so a
            // form that never touches them posts exactly the two-leg entry this
            // endpoint has always posted.
            'charge_amount' => 'nullable|numeric|min:0',
            'charge_account_id' => 'nullable|exists:accounts,id',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_account_id' => 'nullable|exists:accounts,id',
            'allocation_mode' => 'nullable|in:unallocated,auto,manual',
            'invoice_allocations' => 'nullable|array|max:100',
            'invoice_allocations.*.key' => 'required|string|max:100',
            'invoice_allocations.*.amount' => 'required|numeric|min:0',
            'transaction_kind' => 'nullable|in:payment,advance_refund,service_refund,vendor_refund',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        if ((int) $request->contra_account_id === (int) $bank->account_id) {
            return response()->json([
                'success' => false,
                'message' => 'Contra account cannot be this bank\'s own account — the two ledger legs would cancel out and no real transfer would be recorded.',
            ]);
        }

        if (! $bank->account_id) {
            return response()->json([
                'success' => false,
                'message' => "Bank '{$bank->name}' is not linked to a chart-of-accounts account.",
            ]);
        }

        // A charge with nowhere to post is a silently unbalanced entry, so the
        // account is demanded as soon as the figure is non-zero — and refused
        // against the bank's own account for the same reason the contra is: the
        // charge would debit and credit the same place and vanish.
        $chargeAmount = round((float) $request->input('charge_amount', 0), 2);
        $vatAmount = round((float) $request->input('vat_amount', 0), 2);

        foreach ([
            ['label' => 'Bank charge', 'amount' => $chargeAmount, 'account' => $request->charge_account_id],
            ['label' => 'VAT',         'amount' => $vatAmount,    'account' => $request->vat_account_id],
        ] as $extra) {
            if ($extra['amount'] <= 0) {
                continue;
            }
            if (! $extra['account']) {
                return response()->json([
                    'success' => false,
                    'message' => $extra['label'].' needs an account to post to.',
                ]);
            }
            if ((int) $extra['account'] === (int) $bank->account_id) {
                return response()->json([
                    'success' => false,
                    'message' => $extra['label'].' cannot post to the account this bank itself uses — it would cancel itself out.',
                ]);
            }
        }

        $isDeposit = $request->type === 'deposit';

        // Money may not leave an account that does not have it — and BOTH legs
        // have to be checked, because either one can be the side losing money:
        //
        //   Withdraw : Cr this bank      -> this bank is the source
        //   Cash In  : Cr the contra     -> the CONTRA is the source
        //
        // Guarding only the first is what let Office Cash go to -100.00 on
        // 2026-08-13: a ৳35,100 "Cash In" was recorded on the PETTY CASH (GROUP)
        // card with Office Cash as the contra, and Office Cash held ৳35,000. The
        // card being topped up was never short, so nothing objected.
        $amount = (float) $request->amount;

        // Charge and VAT always come OUT of the bank, whichever way the money
        // itself is going: a deposit that costs a fee nets to amount - fee, a
        // withdrawal to amount + fee.
        $bankCut = round($chargeAmount + $vatAmount, 2);
        $bankDelta = round($isDeposit ? ($amount - $bankCut) : -($amount + $bankCut), 2);

        $contraAccount = Account::find($request->contra_account_id);
        $departmentId = null;
        $departmentColumnAvailable = Schema::hasColumn('journal_items', 'expense_department_id');
        $isCentralPaymentEntry = $request->input('entry_context') === 'payment_entry';
        if ($isCentralPaymentEntry && ! $isDeposit && $contraAccount?->type === 'expense') {
            if (! $departmentColumnAvailable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment Entry department posting is not installed yet. Run the required journal-items migration.',
                ], 503);
            }
            if (! $request->filled('expense_department_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Choose the department responsible for this expense payment.',
                ], 422);
            }

            $department = ExpenseDepartment::active()->find($request->expense_department_id);
            if (! $department || (int) $department->company_id !== (int) $bank->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected department must belong to the payment account company.',
                ], 422);
            }
            $departmentId = (int) $department->id;
        }
        $source = $isDeposit
            ? ['id' => (int) $request->contra_account_id, 'name' => $contraAccount?->name ?: 'the contra account']
            : ['id' => (int) $bank->account_id,          'name' => $bank->name];

        // The purpose is the remark the description is built around; the
        // beneficiary is its own column. Before that column exists on the
        // server (migration not yet run) the name is folded into the remark
        // as "NAME - purpose", which the tables know how to split.
        $party = $request->filled('beneficiary_party_id')
            ? User::role(['vendor', 'agent', 'customer'])->find($request->beneficiary_party_id) : null;
        if ($request->filled('beneficiary_party_id') && ! $party) {
            return response()->json(['success' => false, 'message' => 'Please select an agent, vendor, or customer from the list.']);
        }
        $transactionKind = $request->input('transaction_kind', 'payment');
        $partyRole = $party ? $this->resolvePartyType($party) : null;
        $customerPayout = $party && $partyRole !== 'vendor'
            && ! $isDeposit && in_array($transactionKind, ['advance_refund', 'service_refund'], true);
        $vendorRefund = $party && $partyRole === 'vendor'
            && $isDeposit && $transactionKind === 'vendor_refund';
        $specialPartyMovement = $customerPayout || $vendorRefund;

        if (in_array($transactionKind, ['advance_refund', 'service_refund'], true) && ! $customerPayout) {
            return response()->json(['success' => false, 'message' => 'Advance/service refunds require Cash Out to an agent or customer.'], 422);
        }
        if ($transactionKind === 'vendor_refund' && ! $vendorRefund) {
            return response()->json(['success' => false, 'message' => 'Vendor refund requires Cash In from a vendor.'], 422);
        }
        $partySettlement = $party && (
            ($isDeposit && (string) $contraAccount?->code === (string) config('accounts.accounts_receivable'))
            || (! $isDeposit && (string) $contraAccount?->code === (string) config('accounts.accounts_payable'))
        );
        $allocationMode = $request->input('allocation_mode', 'unallocated');
        $advanceCode = $isDeposit
            ? (string) config('accounts.customer_advance')
            : (string) config('accounts.supplier_advance');
        $partyAdvance = $party && $allocationMode === 'unallocated'
            && (string) $contraAccount?->code === $advanceCode;

        if ($allocationMode !== 'unallocated' && (! $partySettlement || $specialPartyMovement)) {
            return response()->json(['success' => false, 'message' => 'Invoice/bill allocation requires the matching party control account and transaction direction.'], 422);
        }
        if ($party && $allocationMode === 'unallocated' && ! $partyAdvance && ! $specialPartyMovement
            && ! $isCentralPaymentEntry) {
            $advanceAccount = Account::where('code', $advanceCode)->first();
            if (! $advanceAccount) {
                return response()->json(['success' => false, 'message' => 'The configured party advance account ('.$advanceCode.') is missing. Run php artisan accounts:check.'], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Advance / Unallocated payments must use '.$advanceAccount->name.' ('.$advanceAccount->code.').',
            ], 422);
        }
        if ($specialPartyMovement) {
            $expectedCode = $vendorRefund
                ? (string) config('accounts.accounts_payable')
                : ($transactionKind === 'service_refund'
                    ? (string) config('accounts.accounts_receivable')
                    : (string) config('accounts.customer_advance'));
            if ((string) $contraAccount?->code !== $expectedCode) {
                $expected = Account::where('code', $expectedCode)->first();

                return response()->json([
                    'success' => false,
                    'message' => 'This party transaction must use '.($expected?->name ?: 'the configured control account').' ('.$expectedCode.').',
                ], 422);
            }
        }
        $beneficiary = $party ? $party->name : trim((string) $request->input('beneficiary'));
        $purpose = trim((string) ($request->input('purpose') ?? $request->input('remarks')));
        $hasColumn = $this->journalHasBeneficiaryColumn();
        $remarks = (! $hasColumn && $beneficiary !== '')
            ? trim($beneficiary.($purpose !== '' ? ' - '.$purpose : ''))
            : $purpose;
        $payDetails = $this->payDetailsFrom($request);
        $journalReference = $request->reference ?: ('BANK-'.strtoupper($request->type).'-'.now()->format('YmdHis'));

        try {
            DB::transaction(function () use ($request, $bank, $isDeposit, $amount, $chargeAmount, $vatAmount, $bankCut, $bankDelta, $source, $beneficiary, $party, $partySettlement, $partyAdvance, $specialPartyMovement, $customerPayout, $vendorRefund, $transactionKind, $remarks, $hasColumn, $payDetails, $journalReference, $departmentId, $departmentColumnAvailable) {
                // The import screen can post several rows to the same bank in
                // one outer transaction. Each row may have resolved the bank
                // before the first row posted, so its model-level balance can
                // be stale. Lock and reload here: every posting now builds on
                // the latest cached value and concurrent writes serialize.
                $bank = Bank::query()->lockForUpdate()->findOrFail($bank->id);

                // Return Advance can only release money the company is actually
                // holding for the party. A Service Refund is different: it is a
                // new receivable-side payout and may legitimately increase a
                // customer's debit balance.
                if ($customerPayout && $transactionKind === 'advance_refund') {
                    Transaction::where('user_id', $party->id)->orderByDesc('id')->lockForUpdate()->first();
                    $partyBalance = round((float) Transaction::where('user_id', $party->id)
                        ->selectRaw('COALESCE(SUM(debit - credit), 0) AS ledger_balance')
                        ->value('ledger_balance'), 2);
                    $availableCredit = max(0, -$partyBalance);
                    if ($availableCredit <= 0 || round($amount, 2) > $availableCredit) {
                        throw new \DomainException('Return Advance cannot exceed the available party credit of ৳'.number_format($availableCredit, 2).'. Use Service Refund for a service-related payout.');
                    }
                }

                // Use the same model-level rule as employee payments, expenses,
                // scheduled payments and transfers. Keeping the check in this
                // transaction means a refusal rolls the entire posting back.
                //
                // A withdrawal pays the amount plus charge/VAT. For a deposit,
                // the contra supplies the gross amount; the bank needs its own
                // check only when the charge/VAT exceeds the deposit and turns
                // the bank's net movement into an outflow.
                $needed = $isDeposit ? $amount : round($amount + $bankCut, 2);
                Bank::assertCanCover($source['id'], $needed, $source['name']);

                if ($isDeposit && $bankDelta < 0) {
                    Bank::assertCanCover(
                        (int) $bank->account_id,
                        abs($bankDelta),
                        $bank->name
                    );
                }

                $allocation = ($party && ! $specialPartyMovement && $request->input('allocation_mode', 'unallocated') !== 'unallocated')
                    ? app(PartyInvoiceAllocation::class)->allocateMany(
                        (int) $party?->id, $amount, $request->input('allocation_mode', 'unallocated'),
                        $request->input('invoice_allocations', []), $isDeposit ? 'sale' : 'payable', (int) $bank->id,
                        $request->input('pay_method', 'bank'), $request->date, $journalReference
                    ) : [];
                $movementLabel = $customerPayout
                    ? ($transactionKind === 'service_refund' ? 'Service refund paid' : 'Customer advance returned')
                    : ($vendorRefund ? 'Vendor refund received' : null);
                $entryRemarks = trim($remarks.($allocation
                    ? ($remarks !== '' ? ' — ' : '').'Allocated to '.collect($allocation)->map(fn ($a) => $a['service'].' '.$a['number'])->implode(', ')
                    : ($movementLabel
                        ? ($remarks !== '' ? ' — ' : '').$movementLabel
                        : ($partyAdvance
                            ? ($remarks !== '' ? ' — ' : '').($isDeposit ? 'Customer advance received' : 'Supplier advance paid')
                            : ($partySettlement ? ($remarks !== '' ? ' — ' : '').'Unallocated party payment' : '')))));

                // The bank's cut is spelled out in the description rather than left
                // to be inferred from the figures: the bank leg below is a NET
                // movement, so a statement row reading 10,115 against a withdrawal
                // someone remembers as 10,000 has to say why on its own face.
                $cutNote = [];
                if ($chargeAmount > 0) {
                    $cutNote[] = 'charge '.number_format($chargeAmount, 2);
                }
                if ($vatAmount > 0) {
                    $cutNote[] = 'VAT '.number_format($vatAmount, 2);
                }

                $journal = JournalEntry::create([
                    'company_id' => $bank->company_id ?? 2,
                    'created_by' => Auth::id(),
                    'date' => $request->date,
                    'entry_time' => $request->time ? $request->time.':00' : null,
                    'reference' => $journalReference,
                    // Allocated payments deliberately use a different source so
                    // the generic bank-only reversal cannot undo the journal
                    // while leaving the linked service invoice marked paid.
                    'source' => $allocation ? 'bank_invoice_payment' : 'bank_transaction',
                    'source_id' => $allocation ? $allocation[0]['id'] : $bank->id,
                    'description' => ($isDeposit ? 'Deposit to ' : 'Withdrawal from ').$bank->name
                        .($entryRemarks !== '' ? ' — '.$entryRemarks : '')
                        .($cutNote ? ' (incl. '.implode(', ', $cutNote).')' : ''),
                ] + ($hasColumn ? ['beneficiary' => $beneficiary !== '' ? $beneficiary : null] : [])
                  + ($party ? ['beneficiary_party_id' => $party->id] : [])
                  + ($this->journalHasPayDetailColumns() ? $payDetails : []));

                // ONE bank leg, carrying the net movement, because one action by the
                // user should read as one line on the bank's statement and one entry
                // in the activity feed. Splitting the cut into its own bank leg would
                // make every charged withdrawal look like two or three transactions.
                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $bank->account_id,
                    'debit' => $bankDelta >= 0 ? $bankDelta : 0,
                    'credit' => $bankDelta < 0 ? abs($bankDelta) : 0,
                    'note' => $entryRemarks !== '' ? $entryRemarks : null,
                ]);

                // The contra takes the amount ALONE. The charge is the bank's fee,
                // not part of what was moved to or from the other account.
                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $request->contra_account_id,
                    'debit' => $isDeposit ? 0 : $amount,
                    'credit' => $isDeposit ? $amount : 0,
                    'note' => $entryRemarks !== '' ? $entryRemarks : null,
                    'party_type' => ($partySettlement || $partyAdvance || $specialPartyMovement) ? $this->resolvePartyType($party) : null,
                    'party_id' => ($partySettlement || $partyAdvance || $specialPartyMovement) ? $party->id : null,
                ] + ($departmentColumnAvailable ? ['expense_department_id' => $departmentId] : []));

                // A selected party makes this bank movement a real settlement:
                // deposit = money received from an agent/customer, withdrawal =
                // money paid to a vendor. Party statements represent settlement
                // payments as credits in both receivable and payable ledgers.
                if ($partySettlement || $partyAdvance || $specialPartyMovement) {
                    if (! $allocation) {
                        $partyDebit = $specialPartyMovement ? $amount : 0;
                        $partyCredit = $specialPartyMovement ? 0 : $amount;
                        $this->postPartyLedgerRow($party->id, [
                            'company_id' => $bank->company_id ?? 2,
                            'type' => $customerPayout
                                ? ($transactionKind === 'service_refund' ? 'customer_service_refund' : 'customer_advance_refund')
                                : ($vendorRefund ? 'vendor_refund'
                                    : ($partyAdvance
                                        ? ($isDeposit ? 'customer_advance_received' : 'supplier_advance_paid')
                                        : 'party_payment')),
                            'account_id' => $bank->id,
                            'payment_date' => $request->date,
                            'reference_no' => $journal->reference,
                            'payment_method' => $request->input('pay_method', 'bank'),
                            'invoice_id' => $journal->id,
                            'debit' => $partyDebit,
                            'credit' => $partyCredit,
                            'remarks' => $entryRemarks !== ''
                                ? $entryRemarks
                                : ($isDeposit ? 'Bank deposit received' : 'Bank withdrawal payment'),
                        ]);
                    }

                    // A withdrawal settling a vendor here is the same money a
                    // scheduled "pay" row for that vendor is waiting on; a deposit
                    // settling an agent/customer is the same money a scheduled
                    // "receive" row is waiting on. Close whatever's open for them,
                    // oldest first, so Payment Schedule's dashboard doesn't keep
                    // counting this as still due just because it wasn't paid
                    // through its own Mark Paid button.
                    if (! $allocation && ! $partyAdvance && ! $specialPartyMovement) {
                        app(PaymentScheduleReconciler::class)->settleForParty(
                            $party->id,
                            $isDeposit ? 'receive' : 'pay',
                            $amount,
                            $request->date,
                            $entryRemarks !== '' ? $entryRemarks : null
                        );
                    }
                }

                // Charge and VAT are costs however the money was moving, so both are
                // debits in either direction. The entry balances either way:
                //   withdraw  Cr bank (amount+cut) = Dr contra (amount) + Dr cut
                //   deposit   Dr bank (amount-cut) + Dr cut = Cr contra (amount)
                foreach ([
                    ['amount' => $chargeAmount, 'account' => $request->charge_account_id, 'label' => 'Bank charge'],
                    ['amount' => $vatAmount,    'account' => $request->vat_account_id,    'label' => 'VAT'],
                ] as $extra) {
                    if ($extra['amount'] <= 0) {
                        continue;
                    }

                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $extra['account'],
                        'debit' => $extra['amount'],
                        'credit' => 0,
                        'note' => $extra['label'].' — '.($isDeposit ? 'deposit to ' : 'withdrawal from ').$bank->name,
                    ]);
                }

                $bank->update([
                    'balance' => $bank->balance + $bankDelta,
                    'last_transaction_date' => $request->date,
                    'last_transaction_amount' => abs($bankDelta),
                    'last_transaction_type' => $bankDelta >= 0 ? 'credit' : 'debit',
                ]);
            });
        } catch (BankOverdrawnException|\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => ucfirst($request->type).' recorded successfully.'
                .(($partySettlement || $partyAdvance || $specialPartyMovement) ? ' Party ledger updated.' : '')
                .($partyAdvance ? ' Recorded as an advance; no invoice or payment schedule was settled.' : '')
                .($party && ! $partySettlement && ! $partyAdvance && ! $specialPartyMovement
                    ? ' Beneficiary saved as a reference only; the selected control account/direction does not represent a party payment.'
                    : '')
                .($bankCut > 0 ? ' Charge/VAT of '.number_format($bankCut, 2).' posted with it.' : ''),
        ]);
    }

    /**
     * Post an equal-and-opposite journal entry against a mistaken bank
     * transaction, instead of deleting it — keeps the mistake and its
     * correction both visible in the audit trail. Only entries created
     * through the Deposit/Withdraw flow (source = bank_transaction) are
     * eligible: reversing a sale/payment-sourced entry here would leave the
     * originating invoice/sale record out of sync with the ledger.
     */
    public function reverseTransaction(Request $request, $role, JournalEntry $journalEntry)
    {
        $journalEntry->load('items');
        $reason = trim((string) $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ])['reason']);

        // Same mirror-and-post mechanic either way; a transfer's charge/VAT
        // lines are just extra items in the loop below, not a bank account,
        // so the cache sync per item is a no-op for them and correct for the
        // two bank legs. Kept to these two sources deliberately — anything
        // else (capital, expense, ...) has its own reverse() entry point and
        // its own business rules around what a reversal should update besides
        // the ledger, which this generic path does not know about.
        if (! in_array($journalEntry->source, ['bank_transaction', 'bank_transfer'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only deposit/withdraw transactions and bank transfers can be reversed here.',
            ]);
        }

        if ($journalEntry->reversed_journal_entry_id) {
            return response()->json([
                'success' => false,
                'message' => 'This entry is itself a reversal — reverse the original transaction instead.',
            ]);
        }

        if (JournalEntry::where('reversed_journal_entry_id', $journalEntry->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This transaction has already been reversed.',
            ]);
        }

        if ($journalEntry->items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'This entry has no line items to reverse.']);
        }

        // Posted on the entry's own date, not today's — a reversal dated
        // "today" would leave every day between an old mistake and today
        // double-counting it. The dashboard always sends the original entry's
        // date; the fallback only covers a bare API call that omits it.
        $reversalDate = $request->filled('date') && strtotime($request->input('date'))
            ? $request->input('date')
            : $journalEntry->date->format('Y-m-d');

        try {
            DB::transaction(function () use ($journalEntry, $reversalDate, $reason) {
                // Serialize reversals of the same source row. The checks above
                // give the user an early answer; this locked check prevents two
                // simultaneous requests from both posting a reversal.
                $journalEntry = JournalEntry::query()
                    ->whereKey($journalEntry->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($journalEntry->reversedBy()->exists()) {
                    throw new \DomainException('This transaction has already been reversed.');
                }

                $journalEntry->load('items');

                $journalEntry->items
                    ->filter(fn ($item) => (float) $item->debit > 0)
                    ->groupBy('account_id')
                    ->each(function ($items, $accountId) {
                        $account = Account::findOrFail($accountId);
                        Bank::assertCanCover(
                            (int) $accountId,
                            (float) $items->sum('debit'),
                            $account->name
                        );
                    });

                $reversal = JournalEntry::create([
                    'company_id' => $journalEntry->company_id,
                    'created_by' => Auth::id(),
                    'date' => $reversalDate,
                    // A reversal belongs beside the mistake it cancels. Legacy
                    // journals may not have entry_time, so use the original row's
                    // creation clock as the same-day fallback. Never use the new
                    // reversal's created_at, otherwise it jumps to the top of an
                    // old statement day instead of sitting beside its original.
                    'entry_time' => $journalEntry->entry_time
                        ?: optional($journalEntry->created_at)->format('H:i:s'),
                    'reference' => 'REV-'.$journalEntry->reference,
                    'source' => $journalEntry->source,
                    'source_id' => $journalEntry->source_id,
                    'description' => 'Reversal of: '.$journalEntry->description.' | Reason: '.$reason,
                    'reversed_journal_entry_id' => $journalEntry->id,
                ] + ($this->journalHasBeneficiaryColumn() ? ['beneficiary' => $journalEntry->beneficiary] : [])
                  + ($journalEntry->beneficiary_party_id ? ['beneficiary_party_id' => $journalEntry->beneficiary_party_id] : [])
                  + ($this->journalHasPayDetailColumns() ? [
                      'beneficiary_method' => $journalEntry->beneficiary_method,
                      'beneficiary_bank' => $journalEntry->beneficiary_bank,
                      'beneficiary_ref' => $journalEntry->beneficiary_ref,
                  ] : []));

                foreach ($journalEntry->items as $item) {
                    JournalItem::create([
                        'journal_entry_id' => $reversal->id,
                        'account_id' => $item->account_id,
                        'debit' => $item->credit,
                        'credit' => $item->debit,
                        'note' => 'Reversal — '.($item->note ?: $journalEntry->description),
                        'party_type' => $item->party_type,
                        'party_id' => $item->party_id,
                    ]);

                    // Keep the denormalized bank.balance cache in sync for any bank
                    // account touched by this entry.
                    $bank = Bank::where('account_id', $item->account_id)->first();
                    if ($bank) {
                        $netEffect = $item->credit - $item->debit;
                        $bank->update([
                            'balance' => $bank->balance + $netEffect,
                            'last_transaction_date' => now()->toDateString(),
                            'last_transaction_amount' => abs($netEffect),
                            'last_transaction_type' => $netEffect >= 0 ? 'credit' : 'debit',
                        ]);
                    }
                }

                // A bank deposit/withdrawal may also have posted a credit to the
                // selected agent/vendor/customer statement. Keep that original
                // row immutable and append its exact opposite, tied to this
                // reversal journal. Re-entering the corrected bank transaction
                // will then append only its own new party row: original + reversal
                // net to zero, while the audit trail remains complete.
                Transaction::query()
                    ->where('invoice_id', $journalEntry->id)
                    // Legacy party rows may belong to the travel company even
                    // when the paying bank belongs to another company. The
                    // exact journal ID + reference identify this posting.
                    ->where('reference_no', $journalEntry->reference)
                    ->when($journalEntry->beneficiary_party_id, fn ($query, $partyId) => $query->where('user_id', $partyId))
                    ->whereIn('type', [
                        'party_payment',
                        'customer_advance_received',
                        'supplier_advance_paid',
                        'customer_advance_refund',
                        'customer_service_refund',
                        'vendor_refund',
                    ])
                    ->orderBy('id')
                    ->get()
                    ->each(function (Transaction $partyRow) use ($journalEntry, $reversal, $reversalDate, $reason) {
                        $this->postPartyLedgerRow((int) $partyRow->user_id, [
                            'company_id' => $partyRow->company_id,
                            'type' => $partyRow->type.'_reversal',
                            'account_id' => $partyRow->account_id,
                            'payment_date' => $reversalDate,
                            'reference_no' => $reversal->reference,
                            'payment_method' => $partyRow->payment_method,
                            'invoice_id' => $reversal->id,
                            'debit' => $partyRow->credit,
                            'credit' => $partyRow->debit,
                            'remarks' => 'Reversal of: '.($partyRow->remarks ?: $journalEntry->description).' | Reason: '.$reason,
                        ]);
                    });
            });
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction reversed successfully. Any linked party ledger entry was reversed too.',
        ]);
    }

    /**
     * Edit the human part of a bank transaction's text.
     *
     * Only the remark is editable. The description is REBUILT here from the bank
     * and the direction using the same expression recordTransaction() uses, so
     * the generated half ("Deposit to X") can never drift from what the entry
     * actually does — the caller can only change what follows the em-dash.
     *
     * Nothing numeric is touched. Amounts are double-entry values that every
     * running balance is derived from, and the audit trail for a wrong figure is
     * a reversal, not a silent edit.
     *
     * Gated exactly like the Reverse button: an entry that has been reversed is
     * refused, because the reversal's own description embeds a copy of this text
     * and editing it afterwards would leave the pair contradicting each other.
     */
    public function updateRemarks(Request $request, $role, JournalEntry $journalEntry)
    {
        $journalEntry->load('items');

        $validated = $request->validate([
            'remarks' => 'nullable|string|max:255',
            'beneficiary' => 'nullable|string|max:150',
            'beneficiary_party_id' => 'nullable|integer|exists:users,id',
            'pay_method' => 'nullable|in:cash,bank,cheque',
            'pay_bank' => 'nullable|string|max:150',
            'pay_ref' => 'nullable|string|max:100',
            'correction_reason' => 'required|string|min:5|max:500',
        ]);
        if ($journalEntry->source === 'schedule_payment_settlement' && $request->has('beneficiary_party_id')) {
            $entry = app(BankScheduleCorrection::class)->details($request, $journalEntry, $validated);

            return response()->json(['success' => true, 'message' => 'Schedule party and details corrected; party ledger synchronized.',
                'description' => $entry->description, 'note' => $validated['remarks'] ?? null,
                'beneficiary' => $entry->beneficiary, 'beneficiary_party_id' => $entry->beneficiary_party_id]);
        }
        $beforeCorrection = $journalEntry->only(['description', 'beneficiary', 'beneficiary_party_id', 'beneficiary_method', 'beneficiary_bank', 'beneficiary_ref']);
        $beforeCorrection['note'] = $journalEntry->items->first()?->note;
        // Same rule as the name below: only written when the dialog sent it.
        $payDetails = $request->has('pay_method') ? $this->payDetailsFrom($request) : null;
        $remarks = trim((string) ($validated['remarks'] ?? ''));
        // Only written when the request carries the key: the note-only
        // dialog never sends it, and must not blank a stored name.
        $beneficiary = $request->has('beneficiary') ? trim((string) $validated['beneficiary']) : null;
        $hasColumn = $this->journalHasBeneficiaryColumn();

        $party = null;
        if ($request->filled('beneficiary_party_id')) {
            $party = User::role(['vendor', 'agent', 'customer'])
                ->find($validated['beneficiary_party_id']);
            if (! $party) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select an agent, vendor, or customer from the list.',
                ], 422);
            }
            $beneficiary = $party->name;
        }

        // Two scopes, decided here rather than trusted from the client.
        //
        // 'description' — our own deposit/withdraw entry, not reversed. We know
        // the formula that produced its text, so the remark can be swapped and
        // the description rebuilt around it.
        //
        // 'note' — everything else: entries owned by another module (party
        // payments, transfers), reversals whose text quotes the original, and
        // opening balances. Their descriptions are derived from records this
        // endpoint does not own, so only the note is written. Nothing computes
        // from a note, and no module regenerates it.
        $canRecompose = $journalEntry->source === 'bank_transaction'
            && ! $journalEntry->reversed_journal_entry_id
            && ! JournalEntry::where('reversed_journal_entry_id', $journalEntry->id)->exists();

        $bank = $canRecompose ? Bank::find($journalEntry->source_id) : null;
        $bankItem = $bank ? $journalEntry->items->firstWhere('account_id', $bank->account_id) : null;
        // Without the bank or its line we cannot know the direction, so fall
        // back to writing the note rather than guessing at the description.
        $canRecompose = $canRecompose && $bank && $bankItem;

        $existingPartyId = $journalEntry->beneficiary_party_id
            ?: $journalEntry->items->first(fn ($item) => $item->party_id !== null)?->party_id;
        if ($party && $existingPartyId && (int) $existingPartyId !== (int) $party->id) {
            return response()->json([
                'success' => false,
                'message' => 'This transaction is already posted to a party ledger. Reverse it to move the amount to another party.',
            ], 422);
        }
        if (! $party && $existingPartyId && $request->has('beneficiary_party_id')) {
            return response()->json([
                'success' => false,
                'message' => 'This transaction is already posted to a party ledger. Reverse it instead of changing the beneficiary back to Unknown.',
            ], 422);
        }

        $partyLedgerPosted = false;
        DB::transaction(function () use ($request, $journalEntry, $bank, $bankItem, $canRecompose, $remarks, $beneficiary, $hasColumn, $payDetails, $party, $beforeCorrection, $validated, &$partyLedgerPosted) {
            // Serialise confirmation attempts for this journal so a double
            // click or two open browser tabs cannot create two sub-ledger rows.
            JournalEntry::whereKey($journalEntry->id)->lockForUpdate()->firstOrFail();

            if ($canRecompose) {
                // Direction from the bank's own line — a debit is money in —
                // rather than from parsing the existing description.
                $isDeposit = (float) $bankItem->debit > 0;

                $journalEntry->update([
                    'description' => ($isDeposit ? 'Deposit to ' : 'Withdrawal from ')
                        .$bank->name
                        .($remarks !== '' ? ' — '.$remarks : ''),
                ] + ($hasColumn && $beneficiary !== null ? ['beneficiary' => $beneficiary !== '' ? $beneficiary : null] : [])
                  + ($party ? ['beneficiary_party_id' => $party->id] : [])
                  + (! $party && $beneficiary !== null && $beneficiary !== (string) $journalEntry->beneficiary ? ['beneficiary_party_id' => null] : [])
                  + ($payDetails !== null && $this->journalHasPayDetailColumns() ? $payDetails : []));

                // Confirming an initially unknown bank movement must classify
                // the existing contra leg, not create another GL entry. The
                // bank/contra amounts therefore remain untouched. A party
                // ledger row is added once, keyed by this journal id.
                if ($party) {
                    $isDeposit = (float) $bankItem->debit > 0;
                    $contraItem = $journalEntry->items
                        ->filter(fn ($item) => (int) $item->account_id !== (int) $bank->account_id)
                        ->filter(fn ($item) => $isDeposit ? (float) $item->credit > 0 : (float) $item->debit > 0)
                        ->sortByDesc(fn ($item) => $isDeposit ? (float) $item->credit : (float) $item->debit)
                        ->first();
                    $contraAccount = $contraItem ? Account::find($contraItem->account_id) : null;
                    $isPartyControl = $contraAccount && (
                        ($isDeposit && (string) $contraAccount->code === (string) config('accounts.accounts_receivable'))
                        || (! $isDeposit && (string) $contraAccount->code === (string) config('accounts.accounts_payable'))
                    );

                    if ($contraItem) {
                        $amount = $isDeposit ? (float) $contraItem->credit : (float) $contraItem->debit;
                        // The same bank movement can already be an advance.
                        // Editing its beneficiary/remarks must not reclassify
                        // it and post a second receipt on the party statement.
                        $ledgerRow = Transaction::whereIn('type', ['party_payment', 'customer_advance_received', 'supplier_advance_paid'])
                            ->where('invoice_id', $journalEntry->id)
                            ->where('account_id', $bank->id)
                            ->where('reference_no', $journalEntry->reference)
                            ->lockForUpdate()
                            ->first();
                        if ($ledgerRow && (int) $ledgerRow->user_id !== (int) $party->id) {
                            throw new \DomainException('This transaction already belongs to another party. Reverse it before changing the party.');
                        }
                        if ($isPartyControl) {
                            $contraItem->update([
                                'party_type' => $this->resolvePartyType($party),
                                'party_id' => $party->id,
                            ]);
                        } elseif (! $ledgerRow && ! JournalEntry::where('source', 'bank_transaction_classification')
                            ->where('source_id', $journalEntry->id)->exists()) {
                            // Unknown receipts/payments are commonly parked in
                            // a suspense or generic contra account. Do not
                            // rewrite that historical journal line. Reclassify
                            // it with a new balanced entry when the party is
                            // confirmed, preserving a visible audit trail.
                            $controlAccount = Account::where(
                                'code',
                                config($isDeposit ? 'accounts.accounts_receivable' : 'accounts.accounts_payable')
                            )->firstOrFail();
                            $classification = JournalEntry::create([
                                'company_id' => $journalEntry->company_id,
                                'created_by' => Auth::id(),
                                'date' => now()->toDateString(),
                                'entry_time' => now()->format('H:i:s'),
                                'reference' => substr('CLS-'.$journalEntry->reference, 0, 100),
                                'source' => 'bank_transaction_classification',
                                'source_id' => $journalEntry->id,
                                'description' => 'Classified previously unknown transaction — '.$party->name,
                            ] + ($hasColumn ? ['beneficiary' => $party->name, 'beneficiary_party_id' => $party->id] : []));

                            JournalItem::create([
                                'journal_entry_id' => $classification->id,
                                'account_id' => $contraItem->account_id,
                                'debit' => $isDeposit ? $amount : 0,
                                'credit' => $isDeposit ? 0 : $amount,
                                'note' => 'Clear unknown transaction '.$journalEntry->reference,
                            ]);
                            JournalItem::create([
                                'journal_entry_id' => $classification->id,
                                'account_id' => $controlAccount->id,
                                'debit' => $isDeposit ? 0 : $amount,
                                'credit' => $isDeposit ? $amount : 0,
                                'note' => $remarks !== '' ? $remarks : 'Party confirmed',
                                'party_type' => $this->resolvePartyType($party),
                                'party_id' => $party->id,
                            ]);
                        }

                        if (! $ledgerRow) {
                            $this->postPartyLedgerRow($party->id, [
                                'company_id' => $journalEntry->company_id,
                                'type' => 'party_payment',
                                'account_id' => $bank->id,
                                'payment_date' => $journalEntry->date,
                                'reference_no' => $journalEntry->reference,
                                'payment_method' => $request->input('pay_method', $journalEntry->beneficiary_method ?: 'bank'),
                                'invoice_id' => $journalEntry->id,
                                'debit' => 0,
                                'credit' => $amount,
                                'remarks' => $remarks !== '' ? $remarks : 'Previously unknown bank transaction confirmed',
                            ]);
                            $partyLedgerPosted = true;

                            app(PaymentScheduleReconciler::class)->settleForParty(
                                $party->id,
                                $isDeposit ? 'receive' : 'pay',
                                $amount,
                                $journalEntry->date->format('Y-m-d'),
                                $remarks !== '' ? $remarks : null
                            );
                        }
                    }
                }
            }

            // Both lines carry the note, exactly as recordTransaction() writes them.
            JournalItem::where('journal_entry_id', $journalEntry->id)
                ->update(['note' => $remarks !== '' ? $remarks : null]);

            $afterEntry = $journalEntry->fresh();
            $afterCorrection = $afterEntry->only(['description', 'beneficiary', 'beneficiary_party_id', 'beneficiary_method', 'beneficiary_bank', 'beneficiary_ref']);
            $afterCorrection['note'] = $remarks !== '' ? $remarks : null;
            DB::table('journal_entry_detail_edits')->insert([
                'journal_entry_id' => $journalEntry->id,
                'old_data' => json_encode($beforeCorrection),
                'new_data' => json_encode($afterCorrection),
                'reason' => $validated['correction_reason'] ?? null,
                'edited_by' => Auth::id(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => $partyLedgerPosted
                ? 'Transaction confirmed and posted to the party ledger.'
                : ($canRecompose ? 'Description updated.' : 'Note updated.'),
            'description' => $journalEntry->fresh()->description,
            'note' => $remarks !== '' ? $remarks : null,
            'beneficiary' => $hasColumn ? $journalEntry->fresh()->beneficiary : null,
            'beneficiary_party_id' => $journalEntry->fresh()->beneficiary_party_id,
            'pay_method' => $journalEntry->fresh()->beneficiary_method ?? null,
            'pay_bank' => $journalEntry->fresh()->beneficiary_bank ?? null,
            'pay_ref' => $journalEntry->fresh()->beneficiary_ref ?? null,
        ]);
    }

    public function transactionAmountDetails(Request $request, $role, JournalEntry $journalEntry)
    {
        $parts = $this->editableBankTransactionParts($request, $journalEntry);
        $journalEntry->loadMissing(['items.account', 'company']);
        $schedule = $parts['schedule'] ?? null;
        $postingItems = $journalEntry->items;
        if ($schedule) {
            $corrections = JournalEntry::where('source', 'schedule_payment_correction')
                ->where('source_id', $journalEntry->id)->where('company_id', $journalEntry->company_id)->select('id');
            $postingItems = $postingItems->concat(JournalItem::whereIn('journal_entry_id', $corrections)->get()->loadMissing('account'));
            // Show the effective posting; individual adjustment entries remain
            // in audit history rather than inflating the overview's totals.
            $postingItems = $postingItems->groupBy(fn ($item) => $item->account_id.':'.$item->party_type.':'.$item->party_id)
                ->map(function ($items) {
                    $item = clone $items->first();
                    $net = round((float) $items->sum('debit') - (float) $items->sum('credit'), 2);
                    $item->debit = max(0, $net);
                    $item->credit = max(0, -$net);

                    return $item;
                })->filter(fn ($item) => (float) $item->debit > 0 || (float) $item->credit > 0)->values();
        }
        $accounts = Account::active()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $journalEntry->company_id))
            ->whereKeyNot($parts['bank']->account_id)
            ->whereIn('type', ['asset', 'expense'])
            ->whereDoesntHave('children')
            ->orderBy('code')->get(['id', 'code', 'name', 'type']);
        $history = DB::table('bank_transaction_amount_edits as edit')
            ->leftJoin('users as editor', 'editor.id', '=', 'edit.edited_by')
            ->leftJoin('accounts as old_charge_account', 'old_charge_account.id', '=', 'edit.old_charge_account_id')
            ->leftJoin('accounts as new_charge_account', 'new_charge_account.id', '=', 'edit.new_charge_account_id')
            ->leftJoin('accounts as old_vat_account', 'old_vat_account.id', '=', 'edit.old_vat_account_id')
            ->leftJoin('accounts as new_vat_account', 'new_vat_account.id', '=', 'edit.new_vat_account_id')
            ->where('edit.journal_entry_id', $journalEntry->id)
            ->orderByDesc('edit.id')
            ->get([
                'edit.id', 'edit.old_amount', 'edit.new_amount', 'edit.old_charge_amount', 'edit.new_charge_amount',
                'edit.old_vat_amount', 'edit.new_vat_amount', 'edit.reason', 'edit.created_at',
                'editor.name as editor_name', 'old_charge_account.name as old_charge_account',
                'new_charge_account.name as new_charge_account', 'old_vat_account.name as old_vat_account',
                'new_vat_account.name as new_vat_account',
            ]);
        $linkedLedger = Transaction::where('company_id', $journalEntry->company_id)
            ->where('invoice_id', $journalEntry->id)->first();

        return response()->json(['success' => true] + collect($parts)->except(['bank', 'schedule', 'bank_item', 'principal_item', 'charge_item', 'vat_item'])->all() + [
            'is_schedule' => (bool) $schedule,
            'charge_account_id' => $parts['charge_item']?->account_id,
            'vat_account_id' => $parts['vat_item']?->account_id,
            'accounts' => $accounts,
            'transaction' => [
                'reference' => $journalEntry->reference,
                'raw_date' => optional($journalEntry->date)->format('Y-m-d'),
                'date' => optional($journalEntry->date)->format('d M Y'),
                'time' => $journalEntry->entry_time ? date('h:i A', strtotime($journalEntry->entry_time)) : null,
                'entry_time' => $journalEntry->entry_time ? substr($journalEntry->entry_time, 0, 5) : null,
                'recorded_at' => optional($journalEntry->created_at)->format('d M Y, h:i:s A'),
                'description' => $journalEntry->description,
                'note' => $schedule ? $schedule->note : $parts['principal_item']->note,
                'beneficiary' => $journalEntry->beneficiary ?: ($schedule?->party_name ?: 'Not recorded'),
                'beneficiary_party_id' => $journalEntry->beneficiary_party_id ?: $schedule?->party_id,
                'beneficiary_method' => $journalEntry->beneficiary_method,
                'beneficiary_bank' => $journalEntry->beneficiary_bank,
                'beneficiary_ref' => $journalEntry->beneficiary_ref,
                'bank' => $parts['bank']->name,
                'company' => $journalEntry->company?->name,
                'linked_ledger' => $linkedLedger?->type,
                'total_debit' => round((float) $postingItems->sum('debit'), 2),
                'total_credit' => round((float) $postingItems->sum('credit'), 2),
            ],
            'journal_items' => $postingItems->map(fn ($item) => [
                'account' => trim(($item->account?->code ? $item->account->code.' - ' : '').($item->account?->name ?? 'Unknown account')),
                'note' => $item->note,
                'debit' => (float) $item->debit,
                'credit' => (float) $item->credit,
            ])->values(),
            'history' => $history,
            'audit_history' => $this->journalCorrectionHistory($journalEntry->id),
        ]);
    }

    public function transactionCorrectionDetails(Request $request, $role, JournalEntry $journalEntry)
    {
        $actor = $request->user() ?? Auth::user();
        abort_unless($actor && ($actor->can('view all bank') || (int) $actor->company_id === (int) $journalEntry->company_id), 403);
        $journalEntry->loadMissing(['items.account', 'company']);
        $transfer = $journalEntry->source === 'bank_transfer'
            ? BankTransfer::with(['fromBank', 'toBank'])->find($journalEntry->source_id)
            : null;
        $salaryPayment = $journalEntry->source === 'employee_payment'
            ? EmployeePayment::with(['employee', 'bank'])->find($journalEntry->source_id)
            : null;
        $ownerDrawing = $journalEntry->source === 'financing_capital'
            ? FinancingCapital::with('bank')->where('kind', 'drawings')->find($journalEntry->source_id)
            : null;
        $accounts = ($transfer || $salaryPayment || $ownerDrawing) ? Account::active()
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $journalEntry->company_id))
            ->when($salaryPayment || $ownerDrawing, fn ($query) => $query->where('type', 'expense'))
            ->when($transfer, fn ($query) => $query->whereIn('type', ['asset', 'expense']))
            ->whereDoesntHave('children')->orderBy('code')
            ->get(['id', 'code', 'name']) : collect();

        return response()->json([
            'success' => true,
            'transaction' => [
                'reference' => $journalEntry->reference,
                'source' => $journalEntry->source,
                'date' => optional($journalEntry->date)->format('Y-m-d'),
                'entry_time' => $journalEntry->entry_time ? substr($journalEntry->entry_time, 0, 5) : null,
                'recorded_at' => optional($journalEntry->created_at)->format('d M Y, h:i:s A'),
                'description' => $journalEntry->description,
                'company' => $journalEntry->company?->name,
                'total_debit' => round((float) $journalEntry->items->sum('debit'), 2),
                'total_credit' => round((float) $journalEntry->items->sum('credit'), 2),
            ],
            'journal_items' => $journalEntry->items->map(fn ($item) => [
                'account' => trim(($item->account?->code ? $item->account->code.' - ' : '').($item->account?->name ?? 'Unknown account')),
                'note' => $item->note,
                'debit' => (float) $item->debit,
                'credit' => (float) $item->credit,
            ])->values(),
            'audit_history' => $this->journalCorrectionHistory($journalEntry->id),
            'accounts' => $accounts,
            'transfer' => $transfer ? [
                'id' => $transfer->id,
                'from_bank' => $transfer->fromBank?->name,
                'to_bank' => $transfer->toBank?->name,
                'amount' => (float) $transfer->amount,
                'charge_amount' => (float) $transfer->charge_amount,
                'vat_amount' => (float) $transfer->vat_amount,
                'charge_account_id' => $transfer->charge_account_id,
                'vat_account_id' => $transfer->vat_account_id,
                'remarks' => $transfer->remarks,
                'url' => route('role.bank_transfers.show', ['role' => $role, 'bank_transfer' => $transfer->id]),
                'create_url' => route('role.bank_transfers.create', ['role' => $role]),
            ] : null,
            'salary_payment' => $salaryPayment ? [
                'id' => $salaryPayment->id,
                'employee' => $salaryPayment->employee?->name,
                'bank' => $salaryPayment->bank?->name,
                'amount' => (float) $salaryPayment->amount,
                'charge_amount' => (float) $salaryPayment->bank_charge_amount,
                'charge_account_id' => $salaryPayment->bank_charge_account_id,
            ] : null,
            'owner_drawing' => $ownerDrawing ? [
                'id' => $ownerDrawing->id,
                'owner' => $ownerDrawing->person_name,
                'bank' => $ownerDrawing->bank?->name,
                'method' => $ownerDrawing->method,
                'amount' => (float) $ownerDrawing->amount,
                'charge_amount' => (float) $ownerDrawing->bank_charge_amount,
                'charge_account_id' => $ownerDrawing->bank_charge_account_id,
            ] : null,
        ]);
    }

    public function correctBankTransfer(Request $request, $role, JournalEntry $journalEntry, BankTransferService $service)
    {
        $actor = $request->user() ?? Auth::user();
        abort_unless($actor && ($actor->can('view all bank') || (int) $actor->company_id === (int) $journalEntry->company_id), 403);
        abort_unless($journalEntry->source === 'bank_transfer' && ! $journalEntry->reversed_journal_entry_id, 422, 'Only an active bank transfer can be corrected here.');

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01', 'charge_amount' => 'nullable|numeric|min:0',
            'charge_account_id' => 'nullable|integer|exists:accounts,id', 'vat_amount' => 'nullable|numeric|min:0',
            'vat_account_id' => 'nullable|integer|exists:accounts,id', 'reason' => 'required|string|min:5|max:500',
            'confirmation' => 'required|in:CONFIRM',
        ]);
        $data['charge_amount'] = round((float) ($data['charge_amount'] ?? 0), 2);
        $data['vat_amount'] = round((float) ($data['vat_amount'] ?? 0), 2);
        foreach ([['Bank charge', $data['charge_amount'], $data['charge_account_id'] ?? null], ['VAT', $data['vat_amount'], $data['vat_account_id'] ?? null]] as [$label, $amount, $accountId]) {
            if ($amount > 0 && ! $accountId) {
                return response()->json(['success' => false, 'message' => "$label account is required."], 422);
            }
        }

        try {
            $replacement = DB::transaction(function () use ($request, $role, $journalEntry, $service, $data) {
                $entry = JournalEntry::whereKey($journalEntry->id)->lockForUpdate()->firstOrFail();
                if ($entry->reversedBy()->exists()) {
                    throw new \DomainException('This transfer has already been reversed.');
                }
                $original = BankTransfer::with(['fromBank', 'toBank'])->lockForUpdate()->findOrFail($entry->source_id);
                foreach ([$data['charge_account_id'] ?? null, $data['vat_account_id'] ?? null] as $accountId) {
                    if ($accountId && ! Account::active()->whereKey($accountId)
                        ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $entry->company_id))
                        ->whereNotIn('id', array_filter([$original->fromBank?->account_id, $original->toBank?->account_id]))
                        ->exists()) {
                        throw new \DomainException('Charge and VAT must post to an active non-bank account for this company.');
                    }
                }
                $reference = BankTransfer::generateReferenceNo();
                $request->merge(['date' => optional($entry->date)->format('Y-m-d'), 'reason' => $data['reason'].' | Corrected by '.$reference]);
                $reverseResponse = $this->reverseTransaction($request, $role, $entry);
                $reverseData = $reverseResponse->getData(true);
                if (! ($reverseData['success'] ?? false)) {
                    throw new \DomainException($reverseData['message'] ?? 'Could not reverse the original transfer.');
                }

                $replacement = BankTransfer::create([
                    'from_bank_id' => $original->from_bank_id, 'to_bank_id' => $original->to_bank_id,
                    'amount' => round((float) $data['amount'], 2), 'payment_date' => $original->payment_date,
                    'entry_time' => $original->entry_time
                        ?: $entry->entry_time
                        ?: optional($original->created_at)->format('H:i:s'),
                    'reference_no' => $reference,
                    'payment_method' => 'bank_transfer', 'status' => 'completed',
                    'remarks' => trim('Correction of '.$original->reference_no.' — '.$data['reason']),
                    'charge_amount' => $data['charge_amount'], 'charge_account_id' => $data['charge_account_id'] ?? null,
                    'vat_amount' => $data['vat_amount'], 'vat_account_id' => $data['vat_account_id'] ?? null,
                    'created_by' => Auth::id(),
                ]);
                $service->post($replacement, $original->fromBank);

                return $replacement;
            });
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Original transfer reversed and corrected transfer posted.', 'reference' => $replacement->reference_no]);
    }

    public function correctSalaryBankCharge(Request $request, $role, JournalEntry $journalEntry)
    {
        $actor = $request->user() ?? Auth::user();
        abort_unless($actor && ($actor->can('view all bank') || (int) $actor->company_id === (int) $journalEntry->company_id), 403);
        abort_unless($journalEntry->source === 'employee_payment' && ! $journalEntry->reversed_journal_entry_id, 422, 'Only an active salary payment can be corrected here.');

        $data = $request->validate([
            'charge_amount' => 'required|numeric|min:0',
            'charge_account_id' => 'nullable|integer|exists:accounts,id',
            'reason' => 'required|string|min:5|max:500',
            'confirmation' => 'required|in:CONFIRM',
        ]);
        $newCharge = round((float) $data['charge_amount'], 2);
        $newAccountId = $newCharge > 0 ? (int) ($data['charge_account_id'] ?? 0) : null;
        if ($newCharge > 0 && ! $newAccountId) {
            return response()->json(['success' => false, 'message' => 'Select the account where the bank charge will be posted.'], 422);
        }

        try {
            DB::transaction(function () use ($request, $journalEntry, $data, $newCharge, $newAccountId) {
                $entry = JournalEntry::whereKey($journalEntry->id)->lockForUpdate()->firstOrFail();
                if ($entry->reversedBy()->exists()) {
                    throw new \DomainException('A reversed salary payment cannot be corrected.');
                }
                $payment = EmployeePayment::with('bank')->lockForUpdate()->findOrFail($entry->source_id);
                if ($payment->status !== 'posted' || ! $payment->bank?->account_id) {
                    throw new \DomainException('The salary payment or its bank account is not active.');
                }
                if ($newAccountId && ! Account::active()->whereKey($newAccountId)
                    ->where('type', 'expense')
                    ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $entry->company_id))
                    ->whereDoesntHave('children')->exists()) {
                    throw new \DomainException('Bank charge must use an active expense posting account for this company.');
                }

                $entry->load('items');
                $bankItem = $entry->items->firstWhere('account_id', $payment->bank->account_id);
                $chargeItem = $entry->items->first(fn ($item) => str_starts_with((string) $item->note, 'Bank charge'));
                if (! $bankItem || (float) $bankItem->credit <= 0) {
                    throw new \DomainException('This salary journal does not have the expected bank payment structure.');
                }
                $oldCharge = (float) ($chargeItem?->debit ?? $payment->bank_charge_amount ?? 0);
                $oldAccountId = $chargeItem?->account_id ?: $payment->bank_charge_account_id;

                $bankItem->update(['credit' => round((float) $payment->amount + $newCharge, 2)]);
                if ($newCharge > 0) {
                    if ($chargeItem) {
                        $chargeItem->update(['account_id' => $newAccountId, 'debit' => $newCharge, 'credit' => 0, 'note' => 'Bank charge — salary payment']);
                    } else {
                        JournalItem::create([
                            'journal_entry_id' => $entry->id, 'account_id' => $newAccountId,
                            'debit' => $newCharge, 'credit' => 0, 'note' => 'Bank charge — salary payment',
                            'party_type' => 'employee', 'party_id' => $payment->user_id,
                        ]);
                    }
                } else {
                    $chargeItem?->delete();
                }

                DB::table('employee_payments')->where('id', $payment->id)->update([
                    'bank_charge_amount' => $newCharge,
                    'bank_charge_account_id' => $newAccountId,
                    'updated_at' => now(),
                ]);
                DB::table('journal_entry_detail_edits')->insert([
                    'journal_entry_id' => $entry->id,
                    'old_data' => json_encode(['bank_charge_amount' => $oldCharge, 'bank_charge_account_id' => $oldAccountId]),
                    'new_data' => json_encode(['bank_charge_amount' => $newCharge, 'bank_charge_account_id' => $newAccountId]),
                    'reason' => $data['reason'], 'edited_by' => $request->user()?->id ?? Auth::id(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Salary bank charge updated; journal and bank balance remain balanced.']);
    }

    public function correctOwnerDrawing(Request $request, $role, JournalEntry $journalEntry)
    {
        $actor = $request->user() ?? Auth::user();
        abort_unless($actor && ($actor->can('view all bank') || (int) $actor->company_id === (int) $journalEntry->company_id), 403);
        abort_unless($journalEntry->source === 'financing_capital' && ! $journalEntry->reversed_journal_entry_id, 422, 'Only an active owner drawing can be corrected here.');

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'charge_amount' => 'nullable|numeric|min:0',
            'charge_account_id' => 'nullable|integer|exists:accounts,id',
            'reason' => 'required|string|min:5|max:500',
            'confirmation' => 'required|in:CONFIRM',
        ]);
        $newAmount = round((float) $data['amount'], 2);
        $newCharge = round((float) ($data['charge_amount'] ?? 0), 2);
        $newAccountId = $newCharge > 0 ? (int) ($data['charge_account_id'] ?? 0) : null;
        if ($newCharge > 0 && ! $newAccountId) {
            return response()->json(['success' => false, 'message' => 'Select the account where the bank charge will be posted.'], 422);
        }

        try {
            DB::transaction(function () use ($request, $journalEntry, $data, $newAmount, $newCharge, $newAccountId) {
                $entry = JournalEntry::whereKey($journalEntry->id)->lockForUpdate()->firstOrFail();
                if ($entry->reversedBy()->exists()) {
                    throw new \DomainException('A reversed owner drawing cannot be corrected.');
                }
                $drawing = FinancingCapital::with('bank')->whereKey($entry->source_id)->lockForUpdate()->firstOrFail();
                if ($drawing->kind !== 'drawings' || $drawing->method !== 'bank' || ! $drawing->bank?->account_id) {
                    throw new \DomainException('Amount and bank charge correction is available only for an active bank-funded owner drawing.');
                }
                if ($newAccountId && ! Account::active()->whereKey($newAccountId)
                    ->where('type', 'expense')
                    ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $entry->company_id))
                    ->whereDoesntHave('children')->exists()) {
                    throw new \DomainException('Bank charge must use an active expense posting account for this company.');
                }

                $entry->load('items');
                $bankItem = $entry->items->firstWhere('account_id', $drawing->bank->account_id);
                $drawingItem = $entry->items->first(fn ($item) => (int) $item->account_id !== (int) $drawing->bank->account_id
                    && ! str_starts_with((string) $item->note, 'Bank charge'));
                $chargeItem = $entry->items->first(fn ($item) => str_starts_with((string) $item->note, 'Bank charge'));
                if (! $bankItem || ! $drawingItem || (float) $bankItem->credit <= 0) {
                    throw new \DomainException('This owner drawing journal does not have the expected posting structure.');
                }

                $oldAmount = (float) $drawing->amount;
                $oldCharge = (float) ($chargeItem?->debit ?? $drawing->bank_charge_amount ?? 0);
                $oldAccountId = $chargeItem?->account_id ?: $drawing->bank_charge_account_id;
                $oldTotal = round($oldAmount + $oldCharge, 2);
                $newTotal = round($newAmount + $newCharge, 2);
                if ($newTotal > $oldTotal) {
                    Bank::assertCanCover((int) $drawing->bank->account_id, round($newTotal - $oldTotal, 2), $drawing->bank->name);
                }

                $drawingItem->update(['debit' => $newAmount, 'credit' => 0]);
                $bankItem->update(['debit' => 0, 'credit' => $newTotal]);
                if ($newCharge > 0) {
                    if ($chargeItem) {
                        $chargeItem->update(['account_id' => $newAccountId, 'debit' => $newCharge, 'credit' => 0, 'note' => 'Bank charge — owner drawing']);
                    } else {
                        JournalItem::create([
                            'journal_entry_id' => $entry->id, 'account_id' => $newAccountId,
                            'debit' => $newCharge, 'credit' => 0, 'note' => 'Bank charge — owner drawing',
                            'party_type' => $drawing->party_type, 'party_id' => $drawing->party_id,
                        ]);
                    }
                } else {
                    $chargeItem?->delete();
                }

                $drawing->update([
                    'amount' => $newAmount, 'bank_charge_amount' => $newCharge,
                    'bank_charge_account_id' => $newAccountId,
                ]);
                DB::table('journal_entry_detail_edits')->insert([
                    'journal_entry_id' => $entry->id,
                    'old_data' => json_encode(['amount' => $oldAmount, 'bank_charge_amount' => $oldCharge, 'bank_charge_account_id' => $oldAccountId]),
                    'new_data' => json_encode(['amount' => $newAmount, 'bank_charge_amount' => $newCharge, 'bank_charge_account_id' => $newAccountId]),
                    'reason' => $data['reason'], 'edited_by' => $request->user()?->id ?? Auth::id(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Owner drawing amount and bank charge updated; journal remains balanced.']);
    }

    private function journalCorrectionHistory(int $journalEntryId): array
    {
        $history = collect();
        DB::table('bank_transaction_amount_edits as edit')->leftJoin('users as user', 'user.id', '=', 'edit.edited_by')
            ->where('edit.journal_entry_id', $journalEntryId)->get(['edit.*', 'user.name as editor_name'])
            ->each(fn ($row) => $history->push([
                'type' => 'Amount & posting', 'editor' => $row->editor_name, 'at' => $row->created_at, 'reason' => $row->reason,
                'summary' => 'Amount '.number_format($row->old_amount, 2).' → '.number_format($row->new_amount, 2)
                    .'; charge '.number_format($row->old_charge_amount, 2).' → '.number_format($row->new_charge_amount, 2)
                    .'; VAT '.number_format($row->old_vat_amount, 2).' → '.number_format($row->new_vat_amount, 2),
            ]));
        DB::table('journal_entry_date_edits as edit')->leftJoin('users as user', 'user.id', '=', 'edit.edited_by')
            ->where('edit.journal_entry_id', $journalEntryId)->get(['edit.*', 'user.name as editor_name'])
            ->each(fn ($row) => $history->push([
                'type' => 'Date', 'editor' => $row->editor_name, 'at' => $row->created_at, 'reason' => $row->reason,
                'summary' => $row->old_date.' → '.$row->new_date,
            ]));
        DB::table('journal_entry_detail_edits as edit')->leftJoin('users as user', 'user.id', '=', 'edit.edited_by')
            ->where('edit.journal_entry_id', $journalEntryId)->get(['edit.*', 'user.name as editor_name'])
            ->each(function ($row) use ($history) {
                $old = json_decode($row->old_data, true) ?: [];
                $new = json_decode($row->new_data, true) ?: [];
                $changed = collect($new)->filter(fn ($value, $key) => ($old[$key] ?? null) != $value)->keys()->map(fn ($key) => str_replace('_', ' ', $key))->join(', ');
                $history->push(['type' => 'Details', 'editor' => $row->editor_name, 'at' => $row->created_at, 'reason' => $row->reason,
                    'summary' => $changed ? 'Changed: '.$changed : 'Details revalidated']);
            });
        JournalEntry::query()->leftJoin('users as user', 'user.id', '=', 'journal_entries.created_by')
            ->where('journal_entries.reversed_journal_entry_id', $journalEntryId)
            ->get(['journal_entries.reference', 'journal_entries.description', 'journal_entries.created_at', 'user.name as editor_name'])
            ->each(fn ($row) => $history->push([
                'type' => 'Reversal', 'editor' => $row->editor_name, 'at' => $row->created_at, 'reason' => $row->description,
                'summary' => 'Posted '.$row->reference,
            ]));

        return $history->sortByDesc('at')->values()->all();
    }

    public function updateTransactionAmount(Request $request, $role, JournalEntry $journalEntry)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'charge_amount' => 'nullable|numeric|min:0',
            'charge_account_id' => 'nullable|integer|exists:accounts,id',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_account_id' => 'nullable|integer|exists:accounts,id',
            'reason' => 'required|string|min:5|max:500',
            'confirmation' => 'required|in:CONFIRM',
        ]);
        $parts = $this->editableBankTransactionParts($request, $journalEntry);
        $newAmount = round((float) $data['amount'], 2);
        $newCharge = round((float) ($data['charge_amount'] ?? 0), 2);
        $newVat = round((float) ($data['vat_amount'] ?? 0), 2);
        $newChargeAccountId = $newCharge > 0 ? (int) ($data['charge_account_id'] ?? 0) : null;
        $newVatAccountId = $newVat > 0 ? (int) ($data['vat_account_id'] ?? 0) : null;
        foreach ([['Bank charge', $newCharge, $data['charge_account_id'] ?? null], ['VAT', $newVat, $data['vat_account_id'] ?? null]] as [$label, $amount, $accountId]) {
            if ($amount > 0 && ! $accountId) {
                return response()->json(['success' => false, 'message' => "{$label} needs a posting account."], 422);
            }
            if ($accountId && (int) $accountId === (int) $parts['bank']->account_id) {
                return response()->json(['success' => false, 'message' => "{$label} cannot use this bank's own account."], 422);
            }
            if ($accountId && ! Account::active()
                ->whereKey($accountId)
                ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $journalEntry->company_id))
                ->whereIn('type', ['asset', 'expense'])
                ->whereDoesntHave('children')->exists()) {
                return response()->json(['success' => false, 'message' => "{$label} must use an active posting account for this company."], 422);
            }
        }

        if ($journalEntry->source === 'schedule_payment_settlement') {
            try {
                app(BankScheduleCorrection::class)->amount($request, $journalEntry, $data);
            } catch (BankOverdrawnException $exception) {
                return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
            }

            return response()->json(['success' => true, 'message' => 'Schedule amount and charges corrected; bank and party ledger synchronized.']);
        }

        try {
            DB::transaction(function () use ($request, $journalEntry, $data, $newAmount, $newCharge, $newVat, $newChargeAccountId, $newVatAccountId) {
                $entry = JournalEntry::lockForUpdate()->findOrFail($journalEntry->id);
                if (JournalEntry::where('reversed_journal_entry_id', $entry->id)->exists()) {
                    throw new \RuntimeException('A reversed transaction cannot be edited.');
                }
                $parts = $this->editableBankTransactionParts($request, $entry);
                $bank = Bank::lockForUpdate()->findOrFail($parts['bank']->id);
                $oldCut = $parts['charge_amount'] + $parts['vat_amount'];
                $newCut = $newCharge + $newVat;
                $oldBankDelta = $parts['is_deposit'] ? $parts['amount'] - $oldCut : -($parts['amount'] + $oldCut);
                $newBankDelta = $parts['is_deposit'] ? $newAmount - $newCut : -($newAmount + $newCut);
                $cacheDelta = round($newBankDelta - $oldBankDelta, 2);
                if ($cacheDelta < 0) {
                    Bank::assertCanCover((int) $bank->account_id, abs($cacheDelta), $bank->name);
                }

                $parts['bank_item']->update(['debit' => $newBankDelta >= 0 ? $newBankDelta : 0, 'credit' => $newBankDelta < 0 ? abs($newBankDelta) : 0]);
                $parts['principal_item']->update(['debit' => $parts['is_deposit'] ? 0 : $newAmount, 'credit' => $parts['is_deposit'] ? $newAmount : 0]);
                $this->syncBankEditExtra($entry, $parts['charge_item'], $newCharge, $newChargeAccountId, 'Bank charge', $bank, $parts['is_deposit']);
                $this->syncBankEditExtra($entry, $parts['vat_item'], $newVat, $newVatAccountId, 'VAT', $bank, $parts['is_deposit']);

                $baseDescription = preg_replace('/\s+\(incl\.\s+.*\)$/u', '', (string) $entry->description);
                $cuts = [];
                if ($newCharge > 0) {
                    $cuts[] = 'charge '.number_format($newCharge, 2);
                }
                if ($newVat > 0) {
                    $cuts[] = 'VAT '.number_format($newVat, 2);
                }
                $entry->update(['description' => $baseDescription.($cuts ? ' (incl. '.implode(', ', $cuts).')' : '')]);

                $ledger = Transaction::where('company_id', $entry->company_id)->where('invoice_id', $entry->id)
                    ->whereIn('type', ['party_payment', 'supplier_advance_paid', 'customer_advance_received'])->lockForUpdate()->first();
                if ($ledger) {
                    // A party_payment linked by invoice_id to this manual bank
                    // journal is a direct beneficiary classification, not an
                    // invoice allocation. Keep its principal synchronized with
                    // the corrected bank transaction just like an advance.
                    $column = $parts['is_deposit'] ? 'credit' : 'debit';
                    $partyDelta = round($newAmount - (float) $ledger->{$column}, 2);
                    $balanceDelta = $parts['is_deposit'] ? -$partyDelta : $partyDelta;
                    $ledger->update([
                        $column => $newAmount,
                        'balance' => round((float) $ledger->balance + $balanceDelta, 2),
                    ]);
                    if ($partyDelta != 0.0) {
                        Transaction::where('company_id', $entry->company_id)->where('user_id', $ledger->user_id)->where('id', '>', $ledger->id)->increment('old_balance', $balanceDelta);
                        Transaction::where('company_id', $entry->company_id)->where('user_id', $ledger->user_id)->where('id', '>', $ledger->id)->increment('balance', $balanceDelta);
                    }
                }

                DB::table('bank_transaction_amount_edits')->insert([
                    'journal_entry_id' => $entry->id, 'old_amount' => $parts['amount'], 'new_amount' => $newAmount,
                    'old_charge_amount' => $parts['charge_amount'], 'new_charge_amount' => $newCharge,
                    'old_vat_amount' => $parts['vat_amount'], 'new_vat_amount' => $newVat,
                    'old_charge_account_id' => $parts['charge_item']?->account_id, 'new_charge_account_id' => $newChargeAccountId,
                    'old_vat_account_id' => $parts['vat_item']?->account_id, 'new_vat_account_id' => $newVatAccountId,
                    'reason' => $data['reason'], 'edited_by' => $request->user()?->id ?? Auth::id(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $bank->update(['balance' => round((float) $bank->balance + $cacheDelta, 2)]);
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Amount and bank charges updated; journal, bank balance and party ledger were synchronized.']);
    }

    private function editableBankTransactionParts(Request $request, JournalEntry $entry): array
    {
        if ($entry->source === 'schedule_payment_settlement') {
            return app(BankScheduleCorrection::class)->parts($request, $entry);
        }
        abort_unless($entry->source === 'bank_transaction' && ! $entry->reversed_journal_entry_id && ! JournalEntry::where('reversed_journal_entry_id', $entry->id)->exists(), 422, 'Only an active manual bank transaction can be edited.');
        $bank = Bank::whereKey($entry->source_id)->firstOrFail();
        $actor = $request->user() ?? Auth::user();
        abort_unless($actor && ($actor->can('view all bank') || (int) $actor->company_id === (int) $entry->company_id), 403);
        $entry->load('items');
        $bankItem = $entry->items->firstWhere('account_id', $bank->account_id);
        $chargeItem = $entry->items->first(fn ($item) => str_starts_with((string) $item->note, 'Bank charge'));
        $vatItem = $entry->items->first(fn ($item) => str_starts_with((string) $item->note, 'VAT'));
        $principal = $entry->items->first(fn ($item) => (int) $item->account_id !== (int) $bank->account_id && ! in_array($item->id, array_filter([$chargeItem?->id, $vatItem?->id]), true));
        abort_unless($bankItem && $principal, 422, 'This journal does not have the expected bank transaction structure.');
        $isDeposit = (float) $bankItem->debit > 0;

        return ['bank' => $bank, 'bank_item' => $bankItem, 'principal_item' => $principal, 'charge_item' => $chargeItem, 'vat_item' => $vatItem,
            'is_deposit' => $isDeposit, 'type' => $isDeposit ? 'deposit' : 'withdraw',
            'amount' => (float) ($isDeposit ? $principal->credit : $principal->debit),
            'charge_amount' => (float) ($chargeItem?->debit ?? 0), 'vat_amount' => (float) ($vatItem?->debit ?? 0)];
    }

    private function syncBankEditExtra(JournalEntry $entry, ?JournalItem $item, float $amount, ?int $accountId, string $label, Bank $bank, bool $isDeposit): void
    {
        if ($amount <= 0) {
            $item?->delete();

            return;
        }
        $values = ['account_id' => $accountId, 'debit' => $amount, 'credit' => 0, 'note' => $label.' — '.($isDeposit ? 'deposit to ' : 'withdrawal from ').$bank->name];
        $item ? $item->update($values) : JournalItem::create(['journal_entry_id' => $entry->id] + $values);
    }

    /**
     * Correct a transaction's date — the one journal-entry edit that keeps a
     * history trail (journal_entry_date_edits), unlike updateRemarks()'s
     * silent overwrite. A date decides which day the entry counts against
     * reconciliation, so moving it is logged: old date, new date, who, when,
     * and an optional reason.
     *
     * Not blocked for reversed/reversal entries — a wrong date is orthogonal
     * to reversal status. The dashboard shows an informational warning for
     * that case instead; nothing here needs to refuse it.
     *
     * Balances are computed live from journal_items wherever they're shown
     * (bankHeaderFigures(), buildBankStatementRows(), the dashboard card
     * grid's journal_balance subquery), never from banks.balance /
     * last_transaction_*, so moving a date fixes every affected day's balance
     * automatically. Those cache columns are deliberately left untouched
     * here — they are already documented (see Bank::availableBalance()) as
     * hand-maintained and drifting, and nothing live reads them.
     */
    public function updateJournalDate(Request $request, $role, JournalEntry $journalEntry)
    {
        if (! in_array($journalEntry->source, self::DATE_EDITABLE_SOURCES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'This entry\'s date comes from another module and cannot be corrected here yet.',
            ]);
        }

        $validated = $request->validate([
            'new_date' => 'required|date',
            'time' => [Rule::requiredIf(in_array($journalEntry->source, ['bank_transaction', 'employee_payment'], true)), 'nullable', 'date_format:H:i'],
            'reason' => 'required|string|min:5|max:500',
        ]);

        $oldDate = optional($journalEntry->date)->format('Y-m-d');
        $oldTime = $journalEntry->entry_time ? substr($journalEntry->entry_time, 0, 5) : null;
        $newDate = $validated['new_date'];
        $newTime = $validated['time'] ?? null;
        $dateUnchanged = $oldDate === $newDate;
        $timeUnchanged = $oldTime === $newTime;

        if ($dateUnchanged && $timeUnchanged) {
            return response()->json(['success' => true, 'message' => 'Unchanged.', 'date' => $newDate]);
        }

        // A reversal shares source_id with the entry it reverses (see
        // reverseTransaction() / FinancingPostingService::reverse() /
        // ExpenseController's own reversal — all copy sourceId straight
        // across). Syncing the source record's date from a REVERSAL would
        // silently overwrite the ORIGINAL transaction's date with the
        // reversal's — corrupting the one thing this feature exists to
        // protect. Only an original entry (not itself a reversal) may push
        // its date down to the source record; a reversal's date lives in
        // journal_entries only, same as bank_transaction always has.
        $canSyncSource = ! $journalEntry->reversed_journal_entry_id && $journalEntry->source_id;

        DB::transaction(function () use ($journalEntry, $oldDate, $oldTime, $newDate, $newTime, $dateUnchanged, $timeUnchanged, $canSyncSource, $validated) {
            if (! $dateUnchanged) {
                JournalEntryDateEdit::create([
                    'journal_entry_id' => $journalEntry->id,
                    'old_date' => $oldDate,
                    'new_date' => $newDate,
                    'reason' => $validated['reason'] ?? null,
                    'edited_by' => Auth::id(),
                ]);
            }
            if (! $timeUnchanged) {
                DB::table('journal_entry_detail_edits')->insert([
                    'journal_entry_id' => $journalEntry->id,
                    'old_data' => json_encode(['entry_time' => $oldTime]),
                    'new_data' => json_encode(['entry_time' => $newTime]),
                    'reason' => $validated['reason'] ?? null,
                    'edited_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $journalEntry->update([
                'date' => $newDate,
                'entry_time' => $newTime ? $newTime.':00' : null,
            ]);
            if ($canSyncSource && $journalEntry->source === 'schedule_payment_settlement') {
                JournalEntry::where('source', 'schedule_payment_correction')->where('source_id', $journalEntry->id)
                    ->where('company_id', $journalEntry->company_id)
                    ->update(['date' => $newDate, 'entry_time' => $newTime ? $newTime.':00' : null]);
                Transaction::where('company_id', $journalEntry->company_id)->where('invoice_id', $journalEntry->id)
                    ->where('type', 'schedule_payment_correction')
                    ->update(['payment_date' => $newDate, 'transaction_time' => $newTime ? $newTime.':00' : null]);
            }

            if (! $canSyncSource) {
                return;
            }

            if ($journalEntry->source === 'employee_payment') {
                $sourceChanges = [];
                if (! $dateUnchanged) {
                    $sourceChanges['payment_date'] = $newDate;
                }
                if (! $timeUnchanged) {
                    $sourceChanges['transaction_time'] = $newTime.':00';
                }
                if ($sourceChanges) {
                    EmployeePayment::whereKey($journalEntry->source_id)->update($sourceChanges);
                }

                return;
            }

            if ($journalEntry->source === 'bank_transfer') {
                $sourceChanges = [];
                if (! $dateUnchanged) {
                    $sourceChanges['payment_date'] = $newDate;
                }
                if (! $timeUnchanged) {
                    $sourceChanges['entry_time'] = $newTime ? $newTime.':00' : null;
                }
                if ($sourceChanges) {
                    BankTransfer::whereKey($journalEntry->source_id)->update($sourceChanges);
                }

                return;
            }

            if ($dateUnchanged) {
                return;
            }

            match ($journalEntry->source) {
                'financing_capital' => FinancingCapital::whereKey($journalEntry->source_id)->update(['date' => $newDate]),
                'expense' => Expense::whereKey($journalEntry->source_id)->update(['expense_date' => $newDate]),
                // source_id is the payment_schedules row itself (both the
                // ad-hoc and the linked-document settlement paths in
                // PaymentScheduleController::pay() set it that way). The
                // linked-document path additionally writes a `transactions`
                // row stamped 'SCH-{schedule_id}' that the Party Statement
                // reads independently of this journal — sync it too, or
                // that screen would keep showing the old date.
                'schedule_payment_settlement' => (function () use ($journalEntry, $newDate) {
                    PaymentSchedule::whereKey($journalEntry->source_id)->update(['paid_date' => $newDate]);
                    Transaction::where('reference_no', 'SCH-'.$journalEntry->source_id)->update(['payment_date' => $newDate]);
                })(),
                default => null,
            };
        });

        $message = ! $dateUnchanged && ! $timeUnchanged
            ? 'Date and time updated.'
            : ($dateUnchanged ? 'Time updated.' : 'Date updated.');

        return response()->json(['success' => true, 'message' => $message, 'date' => $newDate]);
    }

    /**
     * Daily reconciliation checkpoints for a bank — the real statement's
     * closing balance for each recorded date, compared against the live
     * ledger balance as of that date. The comparison is computed here, never
     * stored, so correcting a mis-dated entry later makes a mismatch
     * disappear with no recompute step.
     */
    public function reconciliation($role, Bank $bank)
    {
        $rows = BankReconciliation::where('bank_id', $bank->id)
            ->orderByDesc('date')
            ->get()
            ->map(function ($row) use ($bank) {
                $system = $bank->account_id
                    ? Bank::ledgerBalanceForAccount((int) $bank->account_id, false, $row->date->format('Y-m-d'))
                    : 0.0;

                return [
                    'id' => $row->id,
                    'date' => $row->date->format('Y-m-d'),
                    'actual_closing_balance' => (float) $row->actual_closing_balance,
                    'system_closing_balance' => $system,
                    'difference' => round((float) $row->actual_closing_balance - $system, 2),
                    'note' => $row->note,
                    'created_by' => optional($row->creator)->name,
                ];
            });

        return view('banks.reconciliation', compact('bank', 'rows'));
    }

    /**
     * Record (or correct) one day's real closing balance for a bank.
     * Upserts by (bank_id, date) via withTrashed()->firstOrNew() + restore()
     * rather than relying on the unique index to tell active rows apart from
     * soft-deleted ones — MySQL's unique index never treats two NULL
     * deleted_at values as distinct, so a delete-then-recreate on the same
     * day must reuse the row, not rely on the index shape.
     */
    public function storeReconciliation(Request $request, $role, Bank $bank)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'actual_closing_balance' => 'required|numeric',
            'note' => 'nullable|string|max:500',
        ]);

        $reconciliation = BankReconciliation::withTrashed()
            ->firstOrNew(['bank_id' => $bank->id, 'date' => $validated['date']]);

        if ($reconciliation->trashed()) {
            $reconciliation->restore();
        }

        $reconciliation->fill([
            'company_id' => $bank->company_id,
            'actual_closing_balance' => $validated['actual_closing_balance'],
            'note' => $validated['note'] ?? null,
            'created_by' => Auth::id(),
        ])->save();

        return redirect()->back()->with('success', 'Reconciliation checkpoint saved.');
    }

    public function destroyReconciliation($role, Bank $bank, BankReconciliation $reconciliation)
    {
        if ($reconciliation->bank_id !== $bank->id) {
            abort(404);
        }

        $reconciliation->delete();

        return redirect()->back()->with('success', 'Checkpoint removed.');
    }

    /**
     * Compare the bank's own PDF statement with the ledger, side by side.
     *
     * The PDF never reaches the server: the reconciliation page reads it in
     * the browser (pdf.js) and posts the text lines it found, so there is no
     * upload to store, no PDF library to install and nothing written to the
     * database. BankStatementParser turns the lines into dated rows and
     * BankStatementComparer lines them up against the same ledger rows the
     * statement screen shows, for the days the PDF covers. A statement that
     * yields no rows is shown with its extracted text so the layout can be
     * read by eye — that text is the evidence needed to teach the parser a
     * new bank's format.
     */
    public function compareStatement(Request $request, $role, Bank $bank)
    {
        $validated = $request->validate([
            'statement_text' => 'required|string|max:4000000',
            // JSON: one [page, y] per line of statement_text — see the reader
            // script on the reconciliation page. Optional; without it wrapped
            // descriptions attach to the row above.
            'statement_layout' => 'nullable|string|max:4000000',
            'file_name' => 'nullable|string|max:255',
            'pages' => 'nullable|integer|min:0',
        ]);

        $layout = null;
        if (! empty($validated['statement_layout'])) {
            $decoded = json_decode($validated['statement_layout'], true);
            $layout = is_array($decoded) ? $decoded : null;
        }

        $parsed = (new BankStatementParser)->parse($validated['statement_text'], $layout);
        $comparison = null;

        if ($parsed['rows']) {
            [$ledgerRows, $ledgerOpening] = $this->buildBankStatementRows($bank, $parsed['date_from'], $parsed['date_to']);
            $comparison = (new BankStatementComparer)->compare($parsed, $ledgerRows, (float) $ledgerOpening);
        }

        // The statement names its account; so does the bank record. Two
        // different numbers means someone uploaded another account's
        // statement — every row would then read as a difference.
        $bankAccountNo = preg_replace('/\D/', '', (string) $bank->account_number);
        $accountMismatch = $parsed['account_no'] && strlen($bankAccountNo) >= 8
            && $parsed['account_no'] !== $bankAccountNo;

        return view('banks.reconciliation-compare', [
            'bank' => $bank,
            'parsed' => $parsed,
            'comparison' => $comparison,
            'accountMismatch' => $accountMismatch,
            'fileName' => $validated['file_name'] ?? null,
            'pages' => (int) ($validated['pages'] ?? 0),
            'extracted' => $validated['statement_text'],
        ]);
    }
}
