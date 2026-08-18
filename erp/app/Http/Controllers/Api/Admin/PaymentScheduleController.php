<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bank;
use App\Models\Company;
use App\Models\EmployeeSalary;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLog;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentScheduleController extends Controller
{
    public function index(Request $request)
    {
        $schedules = PaymentSchedule::with(['schedulable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Sale::class           => ['customer'],
                    Purchase::class       => ['supplier'],
                    TicketSale::class     => ['client'],
                    TicketPurchase::class => ['vendor'],
                    EmployeeSalary::class => ['user'],
                ]);
            }])
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->when($request->party,     fn($q) => $q->where('party_type', $request->party))
            ->when($request->date_from, fn($q) => $q->where('scheduled_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('scheduled_date', '<=', $request->date_to))
            ->orderByRaw("FIELD(status,'overdue','pending','approved','paid','cancelled','rejected') ASC")
            ->orderBy('scheduled_date')
            ->paginate(20)
            ->withQueryString();

        $todayPayable    = PaymentSchedule::where('scheduled_date', today())->where('type', 'pay')->whereIn('status', ['pending','approved'])->sum('amount');
        $todayReceivable = PaymentSchedule::where('scheduled_date', today())->where('type', 'receive')->whereIn('status', ['pending','approved'])->sum('amount');
        $overdueAmt      = PaymentSchedule::where('status', 'overdue')->sum('amount');
        $upcomingCount   = PaymentSchedule::whereBetween('scheduled_date', [today()->addDay(), today()->addDays(7)])->where('status', 'pending')->count();

        $banks = Bank::where('status', 1)->orderBy('name')->get();
        $companies = Company::orderBy('name')->get();

       return response()->json([
            'success' => true,
            'message' => 'Payment schedules retrieved successfully.',
            'data' => [
                'schedules' => $schedules,
                'summary' => [
                    'today_payable_amount' => $todayPayable,
                    'today_receivable_amount' => $todayReceivable,
                    'overdue_amount' => $overdueAmt,
                    'upcoming_schedules_count' => $upcomingCount,
                ],
                'banks' => $banks,
                'companies' => $companies,
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id'              => 'nullable|exists:companies,id',
            'schedulable_type'         => 'required|in:sale,purchase,ticket_sale,ticket_purchase',
            'schedulable_id'           => 'required|integer',
            'schedules'                => 'required|array|min:1',
            'schedules.*.amount'       => 'required|numeric|min:0.01',
            'schedules.*.date'         => 'required|date',
            'schedules.*.note'         => 'nullable|string|max:500',
        ]);

        $modelMap = [
            'sale'             => \App\Models\Sale::class,
            'purchase'         => \App\Models\Purchase::class,
            'ticket_sale'      => \App\Models\TicketSale::class,
            'ticket_purchase'  => \App\Models\TicketPurchase::class,
        ];

        $modelClass = $modelMap[$request->schedulable_type];
        $model      = $modelClass::findOrFail($request->schedulable_id);

        // Total scheduled must not exceed remaining due_amount
        $alreadyScheduled = PaymentSchedule::where('schedulable_type', $modelClass)
            ->where('schedulable_id', $model->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('amount');

        $newTotal = collect($request->schedules)->sum('amount');

        $dueField = $request->schedulable_type === 'ticket_purchase' ? $model->due_amount ?? 0 : $model->due_amount;

        if (($alreadyScheduled + $newTotal) > $dueField) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule total exceeds due amount.',
                'errors' => ['amount' => 'Schedule total exceeds due amount: ' . $dueField],
            ], 422);
        }

        $type = in_array($request->schedulable_type, ['sale', 'ticket_sale']) ? 'receive' : 'pay';

        $partyMap = [
            'sale'            => ['type' => 'customer', 'id' => $model->customer_id,  'name' => $model->customer?->name],
            'purchase'        => ['type' => 'vendor',   'id' => $model->supplier_id,  'name' => $model->supplier?->name],
            'ticket_sale'     => ['type' => 'agent',    'id' => $model->client_id,    'name' => 'Client #' . $model->client_id],
            'ticket_purchase' => ['type' => 'vendor',   'id' => $model->vendor_id,    'name' => 'Vendor #' . $model->vendor_id],
        ];
        $party = $partyMap[$request->schedulable_type];

        $companyId = $model->company_id ?? null;

        foreach ($request->schedules as $sched) {
            PaymentSchedule::create([
                'company_id'       => $companyId,
                'schedulable_type' => $modelClass,
                'schedulable_id'   => $model->id,
                'type'             => $type,
                'party_type'       => $party['type'],
                'party_id'         => $party['id'],
                'party_name'       => $party['name'],
                'amount'           => $sched['amount'],
                'scheduled_date'   => $sched['date'],
                'status'           => 'pending',
                'note'             => $sched['note'] ?? null,
                'created_by'       => auth()->id(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($request->schedules) . ' schedule(s) created.'
        ], 201);
    }

    public function storeAdHoc(Request $request)
    {
        $request->validate([
            'type'           => 'required|in:pay,receive',
            'company_id'     => 'nullable|exists:companies,id',
            'party_name'     => 'required|string|max:200',
            'party_type'     => 'required|string|max:50',
            'source_label'   => 'required|string|max:200',
            'amount'         => 'required|numeric|min:0.01',
            'scheduled_date' => 'required|date',
            'note'           => 'nullable|string|max:500',
        ]);

        $userId    = Auth::id();
        $companyId = $request->company_id ?? Auth::user()?->company_id;

        DB::transaction(function () use ($request, $userId, $companyId) {
            $schedule = PaymentSchedule::create([
                'company_id'       => $companyId,
                'schedulable_type' => null,
                'schedulable_id'   => null,
                'type'             => $request->type,
                'party_type'       => $request->party_type,
                'party_id'         => null,
                'party_name'       => $request->party_name,
                'source_label'     => $request->source_label,
                'amount'           => $request->amount,
                'scheduled_date'   => $request->scheduled_date,
                'status'           => 'pending',
                'note'             => $request->note,
                'created_by'       => $userId,
            ]);

            // Journal entries for ad-hoc schedule (accrual basis)
            // pay     → DR General Expense (6000) | CR Accounts Payable (2110)
            // receive → DR Accounts Receivable (1130) | CR Sales Revenue (4100)
            if ($request->type === 'pay') {
                $debitAccount  = Account::where('code', config('accounts.general_expense'))->firstOrFail();
                $creditAccount = Account::where('code', config('accounts.accounts_payable'))->firstOrFail();
                $reference     = 'ADHOC-PAY-' . $schedule->id;
            } else {
                $debitAccount  = Account::where('code', config('accounts.accounts_receivable'))->firstOrFail();
                $creditAccount = Account::where('code', config('accounts.sales_revenue'))->firstOrFail();
                $reference     = 'ADHOC-RCV-' . $schedule->id;
            }

            $journal = JournalEntry::create([
                'company_id'  => $companyId,
                'created_by'  => $userId,
                'date'        => $request->scheduled_date,
                'reference'   => $reference,
                'source'      => 'payment_schedule',
                'source_id'   => $schedule->id,
                'description' => $request->source_label . ' — ' . $request->party_name,
            ]);

            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id'       => $debitAccount->id,
                'debit'            => $request->amount,
                'credit'           => 0,
                'note'             => $request->source_label,
                'party_type'       => $request->party_type,
                'party_id'         => null,
            ]);

            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id'       => $creditAccount->id,
                'debit'            => 0,
                'credit'           => $request->amount,
                'note'             => $request->source_label,
                'party_type'       => $request->party_type,
                'party_id'         => null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Ad-hoc schedule created successfully.'
        ], 201);
    }

    public function approve(PaymentSchedule $schedule, Request $request)
    {
        if (! in_array($schedule->status, ['pending', 'overdue'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending or overdue schedules can be approved.',
            ], 422);
        }

        $schedule->update([
            'status'        => 'approved',
            'approved_by'   => auth()->id(),
            'approved_at'   => now(),
            'approval_note' => $request->approval_note,
        ]);

        PaymentScheduleLog::create([
            'payment_schedule_id' => $schedule->id,
            'action'              => 'approved',
            'old_date'            => $schedule->scheduled_date,
            'new_date'            => $schedule->scheduled_date,
            'reason'              => $request->approval_note,
            'done_by'             => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment Schedule approved successfully.'
        ]);
    }

    public function reject(PaymentSchedule $schedule, Request $request)
    {
        $request->validate([
            'approval_note' => 'required|string|max:500',
        ]);

        if (! in_array($schedule->status, ['pending', 'overdue', 'approved'])) {
            return response()->json([
                'success' => false,
                'message' => 'This schedule cannot be rejected at its current status.'
            ], 422);
        }

        $schedule->update([
            'status'        => 'rejected',
            'approval_note' => $request->approval_note,
        ]);

        PaymentScheduleLog::create([
            'payment_schedule_id' => $schedule->id,
            'action'              => 'rejected',
            'old_date'            => $schedule->scheduled_date,
            'new_date'            => $schedule->scheduled_date,
            'reason'              => $request->approval_note,
            'done_by'             => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment Schedule rejected.'
        ]);
    }

    public function reschedule(PaymentSchedule $schedule, Request $request)
    {
        $request->validate([
            'new_date' => 'required|date',
            'reason'   => 'required|string|max:500',
        ]);

        if ($schedule->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reschedule a paid schedule.'
            ], 422);
        }

        $oldDate = $schedule->scheduled_date;

        $schedule->update([
            'scheduled_date'          => $request->new_date,
            'original_scheduled_date' => $schedule->original_scheduled_date ?? $oldDate,
            'reschedule_count'        => $schedule->reschedule_count + 1,
            'reschedule_reason'       => $request->reason,
            'status'                  => 'pending',   // reset overdue/rejected back to pending
        ]);

        PaymentScheduleLog::create([
            'payment_schedule_id' => $schedule->id,
            'action'              => 'rescheduled',
            'old_date'            => $oldDate,
            'new_date'            => $request->new_date,
            'reason'              => $request->reason,
            'done_by'             => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment Schedule moved to ' . \Carbon\Carbon::parse($request->new_date)->format('d M Y') . '.'
        ]);
    }

    public function markPaid(PaymentSchedule $schedule, Request $request)
    {
        $request->validate([
            // Same rule as the web markPaid() — see the note there. A payment
            // dated in the future desynchronises the bank dashboard card from
            // the account ledger.
            'payment_date'   => 'required|date|before_or_equal:today',
            'payment_method' => 'nullable|string',
            'bank_id'        => 'required|exists:banks,id',
            'note'           => 'nullable|string|max:500',
            'paid_amount'    => 'nullable|numeric|min:0.01',
            'remainder_date' => 'nullable|date',
        ]);

        if ($schedule->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'This schedule is not approved. Please approve the schedule first.',
            ], 422);
        }

        if ($schedule->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot mark a cancelled schedule as paid.',
            ], 422);
        }

        $paidAmt   = $request->filled('paid_amount')
            ? min((float) $request->paid_amount, (float) $schedule->amount)
            : (float) $schedule->amount;
        $isPartial = $paidAmt < ((float) $schedule->amount - 0.001);

        DB::transaction(function () use ($schedule, $request, $paidAmt, $isPartial) {

            $bank = Bank::findOrFail($request->bank_id);

            // ── Employee Salary ──────────────────────────────────────────
            if ($schedule->schedulable instanceof EmployeeSalary) {
                $salary = $schedule->schedulable;

                Payment::updateOrCreate(
                    [
                        'user_id'            => $salary->user_id,
                        'employee_salary_id' => $salary->id,
                    ],
                    [
                        'payment_date'   => $request->payment_date,
                        'bank_id'        => $bank->id,
                        'payment_method' => $request->payment_method,
                        'amount'         => $paidAmt,
                        'transaction_no' => 'SCH-' . $schedule->id,
                        'notes'          => $request->note,
                    ]
                );

                if (! $isPartial) {
                    $salary->update([
                        'status'         => 'Paid',
                        'payment_method' => $request->payment_method,
                        'scheduled_date' => $salary->scheduled_date ?? $schedule->scheduled_date,
                    ]);
                }

                $salaryPayableAccount = Account::where('code', config('accounts.salary_payable'))->firstOrFail();
                if (! $bank->account_id) {
                    throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                }

                $journal = JournalEntry::create([
                    'company_id'  => auth()->user()->company_id ?? $salary->user?->company_id ?? 2,
                    'created_by'  => auth()->id(),
                    'date'        => $request->payment_date,
                    'reference'   => 'SALPAY-SCH-' . $schedule->id,
                    'source'      => 'salary_payment',
                    'source_id'   => $schedule->id,
                    'description' => 'Salary payment - ' . ($salary->user->name ?? 'Employee'),
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $salaryPayableAccount->id,
                    'debit'            => $paidAmt,
                    'credit'           => 0,
                    'note'             => 'Salary payable settled - ' . ($salary->user->name ?? 'Employee'),
                    'party_type'       => 'employee',
                    'party_id'         => $salary->user_id,
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $bank->account_id,
                    'debit'            => 0,
                    'credit'           => $paidAmt,
                    'note'             => 'Salary paid via ' . $request->payment_method,
                    'party_type'       => 'employee',
                    'party_id'         => $salary->user_id,
                ]);

                $schedule->update([
                    'status'      => 'paid',
                    'paid_amount' => $paidAmt,
                    'paid_date'   => $request->payment_date,
                    'note'        => $request->note ?? $schedule->note,
                ]);

                PaymentScheduleLog::create([
                    'payment_schedule_id' => $schedule->id,
                    'action'              => $isPartial ? 'partial_paid' : 'paid',
                    'old_date'            => $schedule->scheduled_date,
                    'new_date'            => $request->payment_date,
                    'reason'              => ($isPartial ? 'Partial ৳' . number_format($paidAmt, 2) . ' of ৳' . number_format($schedule->amount, 2) . '. ' : '') . 'Salary paid via ' . $request->payment_method . ' - Bank: ' . $bank->name,
                    'done_by'             => auth()->id(),
                ]);

                if ($isPartial) {
                    $this->createRemainderSchedule($schedule, $paidAmt, $request->remainder_date);
                }

                return;
            }

            // ── Ad-hoc schedule ──────────────────────────────────────────
            if (is_null($schedule->schedulable_type)) {
                if (! $bank->account_id) {
                    throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                }

                if ($schedule->type === 'pay') {
                    $apAccount       = Account::where('code', config('accounts.accounts_payable'))->firstOrFail();
                    $debitAccountId  = $apAccount->id;
                    $creditAccountId = $bank->account_id;
                    $reference       = 'ADHOC-PAY-PAID-' . $schedule->id;
                } else {
                    $arAccount       = Account::where('code', config('accounts.accounts_receivable'))->firstOrFail();
                    $debitAccountId  = $bank->account_id;
                    $creditAccountId = $arAccount->id;
                    $reference       = 'ADHOC-RCV-PAID-' . $schedule->id;
                }

                $journal = JournalEntry::create([
                    'company_id'  => $schedule->company_id,
                    'created_by'  => auth()->id(),
                    'date'        => $request->payment_date,
                    'reference'   => $reference,
                    'source'      => 'schedule_payment_settlement',
                    'source_id'   => $schedule->id,
                    'description' => ($schedule->source_label ?? 'Ad-hoc') . ' — ' . $schedule->party_name,
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $debitAccountId,
                    'debit'            => $paidAmt,
                    'credit'           => 0,
                    'note'             => $schedule->source_label ?? $schedule->note,
                    'party_type'       => $schedule->party_type,
                    'party_id'         => null,
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $creditAccountId,
                    'debit'            => 0,
                    'credit'           => $paidAmt,
                    'note'             => $schedule->source_label ?? $schedule->note,
                    'party_type'       => $schedule->party_type,
                    'party_id'         => null,
                ]);

                $schedule->update([
                    'status'      => 'paid',
                    'paid_amount' => $paidAmt,
                    'paid_date'   => $request->payment_date,
                    'note'        => $request->note ?? $schedule->note,
                ]);

                PaymentScheduleLog::create([
                    'payment_schedule_id' => $schedule->id,
                    'action'              => $isPartial ? 'partial_paid' : 'paid',
                    'old_date'            => $schedule->scheduled_date,
                    'new_date'            => $request->payment_date,
                    'reason'              => ($isPartial ? 'Partial ৳' . number_format($paidAmt, 2) . ' of ৳' . number_format($schedule->amount, 2) . '. ' : '') . 'Ad-hoc settled — Bank: ' . $bank->name,
                    'done_by'             => auth()->id(),
                ]);

                if ($isPartial) {
                    $this->createRemainderSchedule($schedule, $paidAmt, $request->remainder_date);
                }

                return;
            }

            // ── Linked document (Sale / Purchase / Ticket) ───────────────
            $parent = $schedule->schedulable;
            if ($parent && isset($parent->due_amount)) {
                $newDue                 = max(0, $parent->due_amount - $paidAmt);
                $parent->due_amount     = $newDue;
                $parent->payment_status = $newDue <= 0 ? 'paid' : 'partial';
                $parent->save();
            }

            $schedule->update([
                'status'      => 'paid',
                'paid_amount' => $paidAmt,
                'paid_date'   => $request->payment_date,
                'note'        => $request->note ?? $schedule->note,
            ]);

            PaymentScheduleLog::create([
                'payment_schedule_id' => $schedule->id,
                'action'              => $isPartial ? 'partial_paid' : 'paid',
                'old_date'            => $schedule->scheduled_date,
                'new_date'            => $request->payment_date,
                'reason'              => ($isPartial ? 'Partial ৳' . number_format($paidAmt, 2) . ' of ৳' . number_format($schedule->amount, 2) . '. ' : '') . 'Paid via ' . ($request->payment_method ?? 'N/A') . ' — Bank: ' . $bank->name,
                'done_by'             => auth()->id(),
            ]);

            if ($isPartial) {
                $this->createRemainderSchedule($schedule, $paidAmt, $request->remainder_date);
            }
        });

        $msg = $isPartial
            ? 'Partial payment of ৳ ' . number_format($paidAmt, 2) . ' recorded. Remainder ৳ ' . number_format($schedule->amount - $paidAmt, 2) . ' scheduled as new pending.'
            : 'Payment of ৳ ' . number_format($schedule->amount, 2) . ' marked as paid successfully.';

        return response()->json(['success' => true, 'message' => $msg]);
    }

    private function createRemainderSchedule(PaymentSchedule $schedule, float $paidAmt, ?string $remainderDate): void
    {
        PaymentSchedule::create([
            'company_id'       => $schedule->company_id,
            'schedulable_type' => $schedule->schedulable_type,
            'schedulable_id'   => $schedule->schedulable_id,
            'type'             => $schedule->type,
            'party_type'       => $schedule->party_type,
            'party_id'         => $schedule->party_id,
            'party_name'       => $schedule->party_name,
            'source_label'     => $schedule->source_label,
            'amount'           => round($schedule->amount - $paidAmt, 2),
            'scheduled_date'   => $remainderDate ?? $schedule->scheduled_date,
            'status'           => 'pending',
            'priority'         => $schedule->priority,
            'note'             => 'Remainder from partial payment (SCH-' . $schedule->id . ')',
            'created_by'       => auth()->id(),
        ]);
    }

    public function cancel(PaymentSchedule $schedule)
    {
        if ($schedule->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a paid schedule.'
            ], 422);
        }

        $schedule->update(['status' => 'cancelled']);

        PaymentScheduleLog::create([
            'payment_schedule_id' => $schedule->id,
            'action'              => 'cancelled',
            'old_date'            => $schedule->scheduled_date,
            'done_by'             => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully Payment Schedule cancelled.'
        ]);
    }

    public function destroy(PaymentSchedule $paymentSchedule)
    {
        if ($paymentSchedule->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a paid schedule.'
            ], 422);
        }

        $paymentSchedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully Payment Schedule deleted.'
        ]);
    }
}
