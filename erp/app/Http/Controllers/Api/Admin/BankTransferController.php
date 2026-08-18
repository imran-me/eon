<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankTransfer;
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

        return response()->json([
            'success' => true,
            'message' => 'Bank transfers retrieved successfully.',
            'data' => [
                'transfers' => $transfers,
                'banks' => $banks,
                'summary' => $summary,
            ],
        ]);
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

        // Check sufficient balance when completing immediately
        if ($validated['status'] === 'completed' && $fromBank->balance < $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance in source bank.',
                'errors' => ['amount' => 'Insufficient balance in source bank. Available: ' . number_format($fromBank->balance, 2)]
            ], 422);
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

        return response()->json([
            'success' => true,
            'message' => 'Bank transfer created successfully.'
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show($role, BankTransfer $bankTransfer)
    {
        $bankTransfer->load(['fromBank', 'toBank', 'creator', 'transactions']);

        return response()->json([
            'success' => true,
            'data' => $bankTransfer
        ]);
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit($role, BankTransfer $bankTransfer)
    {
        if ($bankTransfer->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Completed transfers cannot be edited.'
            ], 422);
        }

        $banks = Bank::where('status', true)->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'bankTransfer' => $bankTransfer,
                'banks' => $banks
            ]
        ]);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, $role, BankTransfer $bankTransfer)
    {
        if ($bankTransfer->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Completed transfers cannot be edited.'
            ], 422);
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

        if ($validated['status'] === 'completed' && $fromBank->balance < $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance in source bank.',
                'errors' => ['amount' => 'Insufficient balance in source bank. Available: ' . number_format($fromBank->balance, 2)]
            ], 422);
        }

        DB::transaction(function () use ($validated, $fromBank, $bankTransfer) {
            $previousStatus = $bankTransfer->status;
            $bankTransfer->update($validated);

            // Newly marked as completed — process the transfer
            if ($previousStatus !== 'completed' && $validated['status'] === 'completed') {
                $this->processTransfer($bankTransfer, $fromBank);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Bank transfer updated successfully.'
        ]);
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy($role, BankTransfer $bankTransfer)
    {
        if ($bankTransfer->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Completed transfers cannot be deleted. Cancel it first.'
            ], 422);
        }

        $bankTransfer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bank transfer deleted.'
        ]);
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
    }
}
