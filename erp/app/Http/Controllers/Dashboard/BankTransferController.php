<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankTransfer;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BankTransferController extends Controller
{
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
            $query->where('reference_no', 'like', '%' . $request->search . '%');
        }

        $transfers = $query->paginate(15)->withQueryString();
        $banks     = Bank::where('status', true)->orderBy('name')->get();

        $summary = [
            'total'     => BankTransfer::count(),
            'completed' => BankTransfer::completed()->count(),
            'pending'   => BankTransfer::pending()->count(),
            'amount'    => BankTransfer::completed()->sum('amount'),
        ];

        return view('bank_transfers.index', compact('transfers', 'banks', 'summary'));
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create()
    {
        $banks        = Bank::where('status', true)->orderBy('name')->get();
        $referenceNo  = BankTransfer::generateReferenceNo();

        return view('bank_transfers.create', compact('banks', 'referenceNo'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_bank_id'   => ['required', 'exists:banks,id', Rule::notIn([$request->to_bank_id])],
            'to_bank_id'     => ['required', 'exists:banks,id'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_date'   => ['required', 'date'],
            'reference_no'   => ['required', 'string', 'unique:bank_transfers,reference_no'],
            'payment_method' => ['required', 'string'],
            'status'         => ['required', Rule::in(['pending', 'completed', 'cancelled'])],
            'remarks'        => ['nullable', 'string', 'max:500'],
        ], [
            'from_bank_id.not_in' => 'Source and destination banks cannot be the same.',
        ]);

        $fromBank = Bank::findOrFail($validated['from_bank_id']);

        // Sufficient funds, when completing immediately.
        //
        // Reads the LEDGER, not the `balance` column this used to trust. The two
        // had drifted by ৳24,440 on OFFICE CASH, so a transfer of money that did
        // not exist would have passed this check and overdrawn the account. See
        // Bank::availableBalance().
        $available = $fromBank->availableBalance();

        if ($validated['status'] === 'completed' && $available < $validated['amount']) {
            return back()
                ->withInput()
                ->withErrors(['amount' => 'Insufficient balance in source bank. Available: ' . number_format($available, 2)]);
        }

        DB::transaction(function () use ($validated, $fromBank) {
            $transfer = BankTransfer::create([
                ...$validated,
                'created_by' => Auth::id(),
            ]);

            if ($validated['status'] === 'completed') {
                $this->processTransfer($transfer, $fromBank);
            }
        });

        return redirect()
            ->route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])
            ->with('success', 'Bank transfer created successfully.');
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show($role, BankTransfer $bankTransfer)
    {
        $bankTransfer->load(['fromBank', 'toBank', 'creator', 'transactions']);

        return view('bank_transfers.show', compact('bankTransfer'));
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit($role, BankTransfer $bankTransfer)
    {
        if ($bankTransfer->status === 'completed') {
            return redirect()
                ->route('role.bank_transfers.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $bankTransfer->id])
                ->with('error', 'Completed transfers cannot be edited.');
        }

        $banks = Bank::where('status', true)->orderBy('name')->get();

        return view('bank_transfers.edit', compact('bankTransfer', 'banks'));
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
            'from_bank_id'   => ['required', 'exists:banks,id', Rule::notIn([$request->to_bank_id])],
            'to_bank_id'     => ['required', 'exists:banks,id'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_date'   => ['required', 'date'],
            'reference_no'   => ['required', 'string', Rule::unique('bank_transfers', 'reference_no')->ignore($bankTransfer->id)],
            'payment_method' => ['required', 'string'],
            'status'         => ['required', Rule::in(['pending', 'completed', 'cancelled'])],
            'remarks'        => ['nullable', 'string', 'max:500'],
        ], [
            'from_bank_id.not_in' => 'Source and destination banks cannot be the same.',
        ]);

        $fromBank = Bank::findOrFail($validated['from_bank_id']);

        // Same ledger-backed check as store(). This path matters just as much:
        // a transfer saved as "pending" skips the check entirely, and flipping it
        // to "completed" here is what actually moves the money.
        $available = $fromBank->availableBalance();

        if ($validated['status'] === 'completed' && $available < $validated['amount']) {
            return back()
                ->withInput()
                ->withErrors(['amount' => 'Insufficient balance in source bank. Available: ' . number_format($available, 2)]);
        }

        DB::transaction(function () use ($validated, $fromBank, $bankTransfer) {
            $previousStatus = $bankTransfer->status;
            $bankTransfer->update($validated);

            // Newly marked as completed — process the transfer
            if ($previousStatus !== 'completed' && $validated['status'] === 'completed') {
                $this->processTransfer($bankTransfer, $fromBank);
            }
        });

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

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Create ledger transactions and update bank balances.
     */
    private function processTransfer(BankTransfer $transfer, Bank $fromBank): void
    {
        $toBank = Bank::findOrFail($transfer->to_bank_id);

        // ── Debit: source bank ──────────────────────────────────────────────
        Transaction::create([
            'user_id'        => Auth::id(),
            'type'           => 'bank_transfer',
            'user_type'      => 'supplier',
            'account_id'     => $transfer->from_bank_id,
            'payment_date'   => $transfer->payment_date,
            'reference_no'   => $transfer->reference_no,
            'payment_method' => $transfer->payment_method,
            'invoice_id'     => $transfer->id,
            'old_balance'    => $fromBank->balance,
            'debit'          => $transfer->amount,
            'credit'         => 0,
            'balance'        => $fromBank->balance - $transfer->amount,
            'remarks'        => 'Transfer to: ' . $toBank->name . ' | ' . $transfer->remarks,
        ]);

        $fromBank->decrement('balance', $transfer->amount);

        // ── Credit: destination bank ─────────────────────────────────────────
        $toBank->refresh();

        Transaction::create([
            'user_id'        => Auth::id(),
            'type'           => 'bank_transfer',
            'user_type'      => 'customer',
            'account_id'     => $transfer->to_bank_id,
            'payment_date'   => $transfer->payment_date,
            'reference_no'   => $transfer->reference_no,
            'payment_method' => $transfer->payment_method,
            'invoice_id'     => $transfer->id,
            'old_balance'    => $toBank->balance,
            'debit'          => 0,
            'credit'         => $transfer->amount,
            'balance'        => $toBank->balance + $transfer->amount,
            'remarks'        => 'Transfer from: ' . $fromBank->name . ' | ' . $transfer->remarks,
        ]);

        $toBank->increment('balance', $transfer->amount);

        // ── JOURNAL (contra entry) ────────────────────────────────
        $this->createTransferJournal($transfer, $fromBank, $toBank);
        // ── END JOURNAL ──────────────────────────────────────────
    }

    private function createTransferJournal(BankTransfer $transfer, Bank $fromBank, Bank $toBank): void
    {
        if (!$fromBank->account_id) {
            throw new \Exception("Source bank '{$fromBank->name}' is not linked to a chart-of-accounts account.");
        }
        if (!$toBank->account_id) {
            throw new \Exception("Destination bank '{$toBank->name}' is not linked to a chart-of-accounts account.");
        }

        $journal = JournalEntry::create([
            'company_id'  => $transfer->company_id ?? 2,
            'created_by'  => auth()->id(),
            'date'        => $transfer->payment_date,
            'reference'   => $transfer->reference_no,
            'source'      => 'bank_transfer',
            'source_id'   => $transfer->id,
            'description' => 'Bank transfer — ' . $fromBank->name . ' → ' . $toBank->name,
        ]);

        // Debit: Destination bank (money received)
        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $toBank->account_id,
            'debit'            => $transfer->amount,
            'credit'           => 0,
            'note'             => 'Transfer received from ' . $fromBank->name,
        ]);

        // Credit: Source bank (money sent)
        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $fromBank->account_id,
            'debit'            => 0,
            'credit'           => $transfer->amount,
            'note'             => 'Transfer sent to ' . $toBank->name,
        ]);
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
