<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Exceptions\BankOverdrawnException;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankTransfer;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Notice;
use App\Services\BankTransferService;
use App\Services\NotificationService;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminQuickActionController extends Controller
{
    /**
     * Add a new transaction and update the associated bank account balance.
     */
    public function addTransaction(Request $request)
    {
        try {
            $validated = $request->validate([
                "account_id" => "required|exists:banks,id",
                "type" => "required|string|in:income,expense,transfer",
                "amount" => "required|numeric|min:0.01",
                "payment_date" => "required|date",
                "payment_method" =>
                    "required|string|in:cash,bank_transfer,cheque,card,online",
                "reference_no" => "nullable|string|max:100",
                "remarks" => "nullable|string|max:500",
                "user_type" => "nullable|string",
                "contra_account_id" => "nullable|exists:accounts,id",
                "to_bank_id" => "required_if:type,transfer|nullable|exists:banks,id|different:account_id",
            ]);

            $amount = (float) $validated["amount"];
            $type = $validated["type"];

            $transaction = DB::transaction(function () use (
                $validated,
                $type,
                $amount,
            ) {
                $bank = Bank::query()
                    ->whereKey($validated["account_id"])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $bank->account_id) {
                    throw new \RuntimeException('Bank is not linked to a chart-of-accounts account.');
                }

                if ($type === 'transfer') {
                    $transfer = BankTransfer::create([
                        'from_bank_id' => $bank->id,
                        'to_bank_id' => $validated['to_bank_id'],
                        'amount' => $amount,
                        'payment_date' => $validated['payment_date'],
                        'reference_no' => ($validated['reference_no'] ?? null)
                            ?: 'QA-TRF-' . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(6)),
                        'payment_method' => $validated['payment_method'],
                        'status' => 'completed',
                        'remarks' => $validated['remarks'] ?? null,
                        'created_by' => Auth::id(),
                    ]);

                    app(BankTransferService::class)->post($transfer, $bank);

                    return $transfer->load(['fromBank', 'toBank']);
                }

                $isIncome = $type === 'income';
                $debit = $isIncome ? 0 : $amount;
                $credit = $isIncome ? $amount : 0;

                if (! $isIncome) {
                    Bank::assertCanCover((int) $bank->account_id, $amount, $bank->name);
                }

                $contraCode = $isIncome
                    ? config('accounts.sales_revenue')
                    : config('accounts.general_expense');
                $contraAccount = isset($validated['contra_account_id'])
                    ? Account::findOrFail($validated['contra_account_id'])
                    : Account::where('code', $contraCode)->firstOrFail();

                if ($isIncome && Bank::isMoneyHoldingAccount((int) $contraAccount->id)) {
                    Bank::assertCanCover(
                        (int) $contraAccount->id,
                        $amount,
                        $contraAccount->name,
                    );
                }

                $oldBalance = (float) $bank->balance;
                $newBalance = round($oldBalance + $credit - $debit, 2);

                $transaction = Transaction::create([
                    "user_id" => Auth::id(),
                    "type" => $type,
                    "user_type" => $validated["user_type"] ?? null,
                    "account_id" => $validated["account_id"],
                    "payment_date" => $validated["payment_date"],
                    "reference_no" => $validated["reference_no"] ?? null,
                    "payment_method" => $validated["payment_method"],
                    "invoice_id" => null,
                    "old_balance" => $oldBalance,
                    "debit" => $debit,
                    "credit" => $credit,
                    "balance" => $newBalance,
                    "remarks" => $validated["remarks"] ?? null,
                ]);

                $lastTransactionType = $type === "income" ? "credit" : "debit";

                $bank->update([
                    "balance" => $newBalance,
                    "last_transaction_date" => $validated["payment_date"],
                    "last_transaction_amount" => $amount,
                    "last_transaction_type" => $lastTransactionType,
                ]);

                $journal = JournalEntry::create([
                    'company_id' => Auth::user()?->company_id ?? $bank->company_id ?? 2,
                    'created_by' => Auth::id(),
                    'date' => $validated['payment_date'],
                    'reference' => $validated['reference_no'] ?? null,
                    'source' => 'quick_transaction',
                    'source_id' => $transaction->id,
                    'description' => 'Quick ' . $type . ' - ' . ($validated['remarks'] ?? $bank->name),
                ]);

                $lines = $isIncome
                    ? [
                        [$bank->account_id, $amount, 0],
                        [$contraAccount->id, 0, $amount],
                    ]
                    : [
                        [$contraAccount->id, $amount, 0],
                        [$bank->account_id, 0, $amount],
                    ];

                foreach ($lines as [$accountId, $lineDebit, $lineCredit]) {
                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $accountId,
                        'debit' => $lineDebit,
                        'credit' => $lineCredit,
                        'note' => $validated['remarks'] ?? ucfirst($type),
                    ]);
                }

                return $transaction;
            });

            if ($transaction instanceof Transaction) {
                $transaction->load("account");
            }

            return response()->json(
                [
                    "success" => true,
                    "message" => "Transaction added successfully.",
                    "transaction" => $transaction,
                ],
                201,
            );
        } catch (ValidationException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Validation failed.",
                    "errors" => $e->errors(),
                ],
                422,
            );
        } catch (BankOverdrawnException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['amount' => [$e->getMessage()]],
            ], 422);
        } catch (\Throwable $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "An error occurred while adding the transaction.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Add a new expense record.
     */
    public function addExpense(Request $request)
    {
        try {
            $validated = $request->validate([
                "title" => "required|string|max:255",
                "amount" => "required|numeric|min:0.01",
                "expense_date" => "required|date",
                "expense_category_id" =>
                    "required|exists:expense_categories,id",
                "expense_sub_category_id" =>
                    "nullable|exists:expense_sub_categories,id",
                "payment_mode" =>
                    "required|string|in:cash,mobile_banking,bank_transfer,digital_wallet,other",
                "bank_id" => "nullable|exists:banks,id",
                "description" => "nullable|string|max:1000",
                "reference" => "nullable|string|max:100",
                // "status" => "nullable|in:pending,approved,rejected",
                "status" => "nullable",
            ]);

            $user = Auth::user();
            $companyId = $user?->company_id ?? null;

            $expense = DB::transaction(fn () => Expense::create([
                "user_id" => Auth::id(),
                "company_id" => $companyId,
                "title" => $validated["title"],
                "amount" => $validated["amount"],
                "expense_date" => $validated["expense_date"],
                "expense_category_id" => $validated["expense_category_id"],
                "expense_sub_category_id" =>
                    $validated["expense_sub_category_id"] ?? null,
                "payment_mode" => $validated["payment_mode"],
                "bank_id" => $validated["bank_id"] ?? null,
                "description" => $validated["description"] ?? null,
                "reference" => $validated["reference"] ?? null,
                // This endpoint files an expense for approval; it does not move
                // money. Actual payment is posted by the approval/payment flow.
                "status" => true,
                "approval_status" => Expense::PENDING,
                "attachment" => null,
            ]));

            $expense->load("expense_category");

            return response()->json(
                [
                    "success" => true,
                    "message" => "Expense added successfully.",
                    "expense" => $expense,
                ],
                201,
            );
        } catch (ValidationException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Validation failed.",
                    "errors" => $e->errors(),
                ],
                422,
            );
        } catch (\Throwable $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "An error occurred while adding the expense.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Post a new notice.
     */
    public function postNotice(Request $request)
    {
        try {
            $validated = $request->validate([
                "title" => "required|string|max:255",
                "description" => "required|string",
                "publish_date" => "required|date",
                "expiry_date" => "nullable|date|after_or_equal:publish_date",
                "department_id" => "nullable|exists:departments,id",
                "status" => "nullable|in:draft,published",
            ]);

            $user = Auth::user();
            $companyId = $user?->company_id ?? null;

            $notice = Notice::create([
                "created_by" => Auth::id(),
                "company_id" => $companyId,
                "title" => $validated["title"],
                "description" => $validated["description"],
                "publish_date" => $validated["publish_date"],
                "expiry_date" => $validated["expiry_date"] ?? null,
                "department_id" => $validated["department_id"] ?? null,
                "status" => $validated["status"] ?? "active",
            ]);

            if (($notice->status ?? null) === 'published') {
                NotificationService::notifyNoticePublished($notice, 'published');
            }

            $notice->load("department");

            return response()->json(
                [
                    "success" => true,
                    "message" => "Notice posted successfully.",
                    "notice" => $notice,
                ],
                201,
            );
        } catch (ValidationException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Validation failed.",
                    "errors" => $e->errors(),
                ],
                422,
            );
        } catch (\Throwable $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "An error occurred while posting the notice.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
