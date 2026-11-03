<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\BankOverdrawnException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankTransfer;
use App\Models\JournalEntry;
use App\Services\BankTransferService;
use App\Services\FinancingPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BankTransferController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view bank transfer|view all bank transfer')->only(['index', 'show']);
        $this->middleware('permission:create bank transfer')->only(['create', 'store']);
        $this->middleware('permission:edit bank transfer')->only(['edit', 'update', 'reverse']);
        $this->middleware('permission:delete bank transfer')->only('destroy');
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = BankTransfer::with(['fromBank', 'toBank', 'creator'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        // Filters
        if ($request->filled('from_bank')) {
            $query->where('from_bank_id', $request->from_bank);
        }
        if ($request->filled('to_bank')) {
            $query->where('to_bank_id', $request->to_bank);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $query->where('reference_no', 'like', '%'.$request->search.'%');
        }

        $transfers = $query->paginate(15)->withQueryString();
        $banks = $this->banksForPicker();
        $accounts = Account::active()->orderBy('name')->get(['id', 'name', 'code']);
        $referenceNo = BankTransfer::generateReferenceNo();

        $summary = [
            'total' => BankTransfer::count(),
            'completed' => BankTransfer::completed()->count(),
            'pending' => BankTransfer::pending()->count(),
            'amount' => BankTransfer::completed()->sum('amount'),
        ];

        // Which of this page's completed transfers already have a reversal
        // posted, so the row can show "Reversed" instead of a live button —
        // same shape as FinancingController's $capitalPosting lookup.
        $reversedTransferIds = JournalEntry::query()
            ->where('source', 'bank_transfer')
            ->whereNull('reversed_journal_entry_id')
            ->whereHas('reversedBy')
            ->whereIn('source_id', $transfers->getCollection()->pluck('id'))
            ->pluck('source_id')
            ->all();

        return view('bank_transfers.index', compact(
            'transfers',
            'banks',
            'accounts',
            'referenceNo',
            'summary',
            'reversedTransferIds'
        ));
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create()
    {
        $banks = $this->banksForPicker();
        $referenceNo = BankTransfer::generateReferenceNo();
        $accounts = Account::active()->orderBy('name')->get(['id', 'name', 'code']);

        return view('bank_transfers.create', compact('banks', 'referenceNo', 'accounts'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_bank_id' => ['required', 'exists:banks,id', Rule::notIn([$request->to_bank_id])],
            'to_bank_id' => ['required', 'exists:banks,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'reference_no' => ['required', 'string', 'unique:bank_transfers,reference_no'],
            'payment_method' => ['required', 'string'],
            'status' => ['required', Rule::in(['pending', 'completed', 'cancelled'])],
            'remarks' => ['nullable', 'string', 'max:500'],
            'charge_amount' => ['nullable', 'numeric', 'min:0'],
            'charge_account_id' => ['nullable', 'exists:accounts,id'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'vat_account_id' => ['nullable', 'exists:accounts,id'],
        ], [
            'from_bank_id.not_in' => 'Source and destination banks cannot be the same.',
        ]);

        // Transfers created from this desk are posted immediately and always use
        // the bank-transfer channel; these values are intentionally not choices.
        $validated['status'] = 'completed';
        $validated['payment_method'] = 'bank_transfer';

        $fromBank = Bank::findOrFail($validated['from_bank_id']);
        $this->validateTransferCharges($validated, $fromBank);

        // charge_amount/vat_amount are NOT NULL (DB default 0.00) — but an
        // empty form field validates to an explicit null, and Eloquent inserts
        // that null verbatim rather than falling back to the column default.
        $validated['charge_amount'] = $validated['charge_amount'] ?? 0;
        $validated['vat_amount'] = $validated['vat_amount'] ?? 0;

        // 'time' isn't a bank_transfers column — same "hh:mm" the bank
        // ledger's Cash In/Out picker sends, stored as entry_time so a
        // transfer saved 'pending' still carries it to its JournalEntry
        // once it's later marked completed (see BankTransferService).
        $validated['entry_time'] = $validated['time'].':00';
        unset($validated['time']);

        try {
            DB::transaction(function () use ($validated, $fromBank) {
                $transfer = BankTransfer::create([
                    ...$validated,
                    'created_by' => Auth::id(),
                ]);

                if ($validated['status'] === 'completed') {
                    $this->processTransfer($transfer, $fromBank);
                }
            });
        } catch (BankOverdrawnException $e) {
            return back()
                ->withInput()
                ->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])
            ->with('success', 'Bank transfer created successfully.');
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show($role, BankTransfer $bankTransfer)
    {
        $bankTransfer->load(['fromBank', 'toBank', 'creator', 'transactions']);

        // The posted leg this transfer wrote, if any — used to show the
        // reversal state and gate the Reverse button the same way the
        // Capital & Financing screen does for its own postings.
        $postedEntry = JournalEntry::with('reversedBy')
            ->where('source', 'bank_transfer')
            ->where('source_id', $bankTransfer->id)
            ->whereNull('reversed_journal_entry_id')
            ->first();
        $isReversed = (bool) $postedEntry?->reversedBy;

        return view('bank_transfers.show', compact('bankTransfer', 'postedEntry', 'isReversed'));
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit($role, BankTransfer $bankTransfer)
    {
        if ($bankTransfer->status === 'completed') {
            return redirect()
                ->route('role.bank_transfers.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $bankTransfer->id])
                ->with('error', 'Completed transfers cannot be edited.');
        }

        $banks = $this->banksForPicker();
        $accounts = Account::active()->orderBy('name')->get(['id', 'name', 'code']);

        $fromBalance = $bankTransfer->fromBank?->availableBalance() ?? 0;
        $toBalance = $bankTransfer->toBank?->availableBalance() ?? 0;

        return view('bank_transfers.edit', compact('bankTransfer', 'banks', 'accounts', 'fromBalance', 'toBalance'));
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, $role, BankTransfer $bankTransfer)
    {
        if ($bankTransfer->status === 'completed') {
            return redirect()
                ->route('role.bank_transfers.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $bankTransfer->id])
                ->with('error', 'Completed transfers cannot be edited.');
        }

        $validated = $request->validate([
            'from_bank_id' => ['required', 'exists:banks,id', Rule::notIn([$request->to_bank_id])],
            'to_bank_id' => ['required', 'exists:banks,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'reference_no' => ['required', 'string', Rule::unique('bank_transfers', 'reference_no')->ignore($bankTransfer->id)],
            'payment_method' => ['required', 'string'],
            'status' => ['required', Rule::in(['pending', 'completed', 'cancelled'])],
            'remarks' => ['nullable', 'string', 'max:500'],
            'charge_amount' => ['nullable', 'numeric', 'min:0'],
            'charge_account_id' => ['nullable', 'exists:accounts,id'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'vat_account_id' => ['nullable', 'exists:accounts,id'],
        ], [
            'from_bank_id.not_in' => 'Source and destination banks cannot be the same.',
        ]);

        $fromBank = Bank::findOrFail($validated['from_bank_id']);
        $this->validateTransferCharges($validated, $fromBank);

        // See store() — charge_amount/vat_amount are NOT NULL, an empty field
        // validates to null, and Eloquent inserts that null as-is.
        $validated['charge_amount'] = $validated['charge_amount'] ?? 0;
        $validated['vat_amount'] = $validated['vat_amount'] ?? 0;

        $validated['entry_time'] = $validated['time'].':00';
        unset($validated['time']);

        try {
            DB::transaction(function () use ($validated, $fromBank, $bankTransfer) {
                $bankTransfer = BankTransfer::whereKey($bankTransfer->id)->lockForUpdate()->firstOrFail();
                $previousStatus = $bankTransfer->status;
                $bankTransfer->update($validated);

                // Newly marked as completed — process the transfer
                if ($previousStatus !== 'completed' && $validated['status'] === 'completed') {
                    $this->processTransfer($bankTransfer, $fromBank);
                }
            });
        } catch (BankOverdrawnException $e) {
            return back()
                ->withInput()
                ->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('role.bank_transfers.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $bankTransfer->id])
            ->with('success', 'Bank transfer updated successfully.');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy($role, BankTransfer $bankTransfer)
    {
        if ($bankTransfer->status === 'completed') {
            return back()->with('error', 'Completed transfers cannot be deleted. Cancel it first.');
        }

        $bankTransfer->delete();

        return redirect()
            ->route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])
            ->with('success', 'Bank transfer deleted.');
    }

    // ─── Reverse ──────────────────────────────────────────────────────────────

    /**
     * Undo a completed transfer by mirroring its journal entry, never by
     * deleting or editing it — destroy()/update() already refuse a completed
     * transfer outright, and until now that left completed transfers with no
     * way back at all.
     *
     * Reuses FinancingPostingService::reverse() rather than a second copy of
     * "mirror a JournalEntry's lines": it is source-agnostic already (it
     * mirrors whatever entry it is given), and BankTransferService's own
     * docblock is explicit that two implementations of moving money is how the
     * ledger and the register start disagreeing.
     */
    public function reverse(Request $request, $role, BankTransfer $bankTransfer)
    {
        if ($bankTransfer->status !== 'completed') {
            return back()->with('error', 'Only a completed transfer can be reversed.');
        }

        $message = 'Transfer reversed. The original row stays visible for audit, and both banks\' balances now exclude it.';

        try {
            DB::transaction(function () use ($bankTransfer, &$message) {
                $entry = JournalEntry::where('source', 'bank_transfer')
                    ->where('source_id', $bankTransfer->id)
                    ->whereNull('reversed_journal_entry_id')
                    ->first();

                if (! $entry) {
                    throw new \RuntimeException('No posted ledger entry was found for this transfer.');
                }

                if ($entry->reversedBy()->exists()) {
                    $message = 'This transfer was already reversed; no further action was taken.';

                    return;
                }

                if (! app(FinancingPostingService::class)->reverse($entry, 'Bank transfer reversed')) {
                    throw new \RuntimeException('The linked ledger posting could not be reversed.');
                }

                // processTransfer() (BankTransferService::post) moved the
                // denormalized banks.balance cache when this transfer posted —
                // decrement on the source, increment on the destination. Undo the
                // same two numbers here so that cache does not go on reading as if
                // the reversal never happened; availableBalance() is ledger-derived
                // and is already correct the moment the entry above is written.
                $lockedBanks = Bank::query()
                    ->whereIn('id', [$bankTransfer->from_bank_id, $bankTransfer->to_bank_id])
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $sourceOutflow = round(
                    (float) $bankTransfer->amount
                    + (float) $bankTransfer->charge_amount
                    + (float) $bankTransfer->vat_amount,
                    2
                );
                $lockedBanks->get($bankTransfer->from_bank_id)?->increment('balance', $sourceOutflow);
                $lockedBanks->get($bankTransfer->to_bank_id)?->decrement('balance', $bankTransfer->amount);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('role.bank_transfers.show', ['role' => $role, 'bank_transfer' => $bankTransfer->id])
            ->with('success', $message);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Create ledger transactions and update bank balances.
     */
    /**
     * The body of this moved to App\Services\BankTransferService when the
     * Liability desk gained "settle by bank transfer" — settling an
     * inter-company debt is a transfer, and it must run the same code this
     * desk runs rather than a second copy of it.
     *
     * Kept as a one-line delegate rather than replacing the two call sites
     * above, so this desk's flow reads exactly as it did before.
     */
    private function processTransfer(BankTransfer $transfer, Bank $fromBank): void
    {
        app(BankTransferService::class)->post($transfer, $fromBank);
    }

    private function banksForPicker()
    {
        // company eager-loaded and ordered first: the From/To pickers group accounts
        // by company (bank_transfers/partials/bank-options), and the label names it.
        //
        // The shared bank-options partial exposes `data-balance` from the model's
        // balance attribute. For transfers that number must match the bank cards
        // and the server-side guard, both of which read the journal ledger. Set it
        // on the in-memory models only; do not persist the old drift-prone column.
        return Bank::with('company:id,name,short_name')->where('status', true)
            ->orderBy('company_id')->orderBy('name')->get()
            ->each(function (Bank $bank) {
                $bank->setAttribute('balance', $bank->availableBalance());
            });
    }

    private function transferSourceOutflow(array $data): float
    {
        return round(
            (float) $data['amount']
            + (float) ($data['charge_amount'] ?? 0)
            + (float) ($data['vat_amount'] ?? 0),
            2
        );
    }

    private function validateTransferCharges(array $data, Bank $fromBank): void
    {
        foreach ([
            ['label' => 'Bank charge', 'amount' => (float) ($data['charge_amount'] ?? 0), 'account' => $data['charge_account_id'] ?? null],
            ['label' => 'VAT', 'amount' => (float) ($data['vat_amount'] ?? 0), 'account' => $data['vat_account_id'] ?? null],
        ] as $extra) {
            if ($extra['amount'] <= 0) {
                continue;
            }

            if (! $extra['account']) {
                throw ValidationException::withMessages([
                    'amount' => $extra['label'].' needs an account to post to.',
                ]);
            }

            if ((int) $extra['account'] === (int) $fromBank->account_id) {
                throw ValidationException::withMessages([
                    'amount' => $extra['label'].' cannot post to the source bank account.',
                ]);
            }
        }
    }

    private function deleteTransferJournal(int $transferId): void
    {
        $journal = JournalEntry::where('source', 'bank_transfer')
            ->where('source_id', $transferId)
            ->first();
        if ($journal) {
            $journal->items()->forceDelete();
            $journal->forceDelete();
        }
    }
}
