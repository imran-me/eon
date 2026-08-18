<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bank;
use App\Models\Company;
use App\Models\ContractFile;
use App\Models\ContractFileSale;
use App\Models\ContractFlight;
use App\Models\ContractFlightBooking;
use App\Models\EmployeeLedger;
use App\Models\EmployeeSalary;
use App\Models\PartyType;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLog;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SalaryReconciliation;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VisaProcess;
use App\Models\VisaSale;
use App\Services\NotificationService;
use App\Traits\PostsBalancedJournal;
use App\Traits\PostsEmployeeLedger;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsPurchaseJournal;
use App\Traits\PostsSaleJournal;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentScheduleController extends Controller
{
    use PostsBalancedJournal, PostsEmployeeLedger, PostsPartyLedger, PostsPurchaseJournal, PostsSaleJournal;

    public function __construct()
    {
        $this->middleware('can:view payment schedule')->only(['index', 'headlineNotices', 'voucher']);
        $this->middleware('can:create payment schedule')->only(['store', 'storeAdHoc', 'partiesByType', 'partyTypesByCompany', 'projectCategoriesByCompany']);
        $this->middleware('can:edit payment schedule')->only(['cancel', 'reschedule', 'markPaid', 'setPriority', 'payDueAmount']);
        $this->middleware('can:delete payment schedule')->only(['destroy']);
        $this->middleware('can:approve payment schedule')->only(['approve', 'reject']);
    }

    public function index(Request $request)
    {
        $schedules = PaymentSchedule::with(['schedulable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Sale::class           => ['customer'],
                    Purchase::class       => ['supplier'],
                    TicketSale::class     => ['client'],
                    TicketPurchase::class => ['vendor'],
                    VisaProcess::class    => ['passportHolder'],
                    EmployeeSalary::class => ['user'],
                ]);
            }])
            ->when($request->schedule_id, fn($q) => $q->where('id', $request->schedule_id))
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->when($request->party,     fn($q) => $q->where('party_type', $request->party))
            ->when($request->priority,  fn($q) => $q->where('priority', $request->priority))
            ->when($request->date_from, fn($q) => $q->where('scheduled_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('scheduled_date', '<=', $request->date_to))
            ->tap(function ($q) use ($request) {
                // Column sorting. The key is matched against this list before any
                // use, so nothing from the URL reaches the query as SQL. $dir is
                // normalised to asc/desc, which is what makes it safe to place
                // inside the two orderByRaw expressions below.
                // is_string before any cast: ?sort[]=amount arrives as an array,
                // and casting that to string raises a PHP warning which Laravel
                // promotes to an ErrorException — a 500 before the whitelist is
                // ever consulted.
                $sortRaw = $request->query('sort');
                $dirRaw  = $request->query('dir');
                $sortKey = is_string($sortRaw) ? $sortRaw : '';
                $sortDir = (is_string($dirRaw) && strtolower($dirRaw) === 'asc') ? 'asc' : 'desc';

                // Party and Reference are absent on purpose: both are resolved in
                // the view from a morph relation (customer / supplier / ticket /
                // passport holder), so there is no column to order on.
                if (! in_array($sortKey, ['scheduled_date', 'note', 'priority', 'status', 'amount'], true)) {
                    // Untouched default: urgency first, then due date — the order
                    // this desk has always used.
                    $q->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 ELSE 3 END")
                      ->orderByRaw("FIELD(status,'overdue','pending','approved','paid','cancelled','rejected') ASC")
                      ->orderBy('scheduled_date')
                      // Tiebreak: none of the three above is unique, so schedules
                      // sharing a priority, status and date had no defined order
                      // and could repeat or vanish across page boundaries.
                      ->orderBy('id', 'desc');
                    return;
                }

                if ($sortKey === 'priority') {
                    // By urgency, not alphabetically — plain A-Z would read
                    // high, low, medium, which is meaningless here.
                    $q->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 ELSE 3 END " . $sortDir);
                } elseif ($sortKey === 'status') {
                    // Same reasoning: overdue first, then the natural lifecycle.
                    $q->orderByRaw("FIELD(status,'overdue','pending','approved','paid','cancelled','rejected') " . $sortDir);
                } else {
                    $q->orderBy($sortKey, $sortDir);
                }

                // Stable paging when the sorted value repeats.
                $q->orderBy('id', 'desc');
            })
            ->paginate(20)
            ->withQueryString();

        $todayPayable    = PaymentSchedule::where('scheduled_date', today())->where('type', 'pay')->whereIn('status', ['pending','approved'])->sum('amount');
        $todayReceivable = PaymentSchedule::where('scheduled_date', today())->where('type', 'receive')->whereIn('status', ['pending','approved'])->sum('amount');
        $overdueAmt      = PaymentSchedule::where('status', 'overdue')->sum('amount');
        $upcomingCount   = PaymentSchedule::whereBetween('scheduled_date', [today()->addDay(), today()->addDays(7)])->where('status', 'pending')->count();

        $banks = Bank::where('status', 1)->orderBy('name')->get();
        $companies = Company::orderBy('name')->get();

        $companyId = Auth::user()?->company_id
            ?? Company::orderBy('id')->value('id');
        $partyTypes = PartyType::where('company_id', $companyId)
            ->orderBy('id')
            ->get();

        return view('payment-schedules.index', compact(
            'schedules', 'banks',
            'todayPayable', 'todayReceivable',
            'overdueAmt', 'upcomingCount', 'companies', 'partyTypes'
        ));
    }

    public function headlineNotices(string $role)
    {
        $schedules = PaymentSchedule::with(['company'])
            ->whereIn('status', ['pending', 'overdue', 'approved'])
            ->where(function ($query) {
                $query->where('scheduled_date', '<=', today()->addDays(7))
                    ->orWhere('priority', 'high');
            })
            ->orderByRaw(
                "CASE WHEN status = 'overdue' THEN 0 WHEN scheduled_date < ? THEN 1 WHEN scheduled_date = ? THEN 2 WHEN priority = 'high' THEN 3 ELSE 4 END",
                [today()->toDateString(), today()->toDateString()]
            )
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 ELSE 3 END")
            ->orderBy('scheduled_date')
            ->limit(25)
            ->get();

        return response()->json([
            'items' => $schedules->map(fn (PaymentSchedule $schedule) => $this->headlineNoticeItem($schedule, $role))->values(),
        ]);
    }

    private function headlineNoticeItem(PaymentSchedule $schedule, string $role): array
    {
        $isPayable = $schedule->type === 'pay';
        $isOverdue = $schedule->status === 'overdue'
            || ($schedule->scheduled_date && $schedule->scheduled_date->isPast() && ! $schedule->scheduled_date->isToday());
        $due = $this->headlineDueLabel($schedule);
        $party = $schedule->party_name ?: ucfirst((string) $schedule->party_type);
        $reference = $schedule->source_label ?: ('Schedule #' . $schedule->id);
        $amount = number_format((float) $schedule->amount, 2);
        $label = $isPayable ? 'Payable' : 'Receivable';
        $action = $isPayable ? 'pay' : 'collect';
        $priority = $isOverdue ? 'critical' : ($schedule->priority ?: 'medium');

        return [
            'id' => 'payment-schedule-' . $schedule->id,
            'type' => $isOverdue ? 'action' : 'reminder',
            'priority' => $priority,
            'overdue' => $isOverdue,
            'icon' => $isPayable ? '↑' : '↓',
            'text' => "{$label} ৳{$amount} {$due} — {$party} · {$reference} — {$action}",
            'due' => $due,
            'target_url' => route('role.payment-schedules.index', [
                'role' => $role,
                'schedule_id' => $schedule->id,
                'headline' => 'payment-schedule-' . $schedule->id,
            ]),
            'target_selector' => '#payment-schedule-' . $schedule->id,
            'schedule_id' => $schedule->id,
            'payment_type' => $schedule->type,
        ];
    }

    private function headlineDueLabel(PaymentSchedule $schedule): string
    {
        if (! $schedule->scheduled_date) {
            return 'scheduled';
        }

        if ($schedule->scheduled_date->isToday()) {
            return 'today';
        }

        if ($schedule->scheduled_date->isTomorrow()) {
            return 'tomorrow';
        }

        if ($schedule->scheduled_date->isPast()) {
            return 'overdue ' . $schedule->scheduled_date->diffInDays(today()) . 'd';
        }

        $days = today()->diffInDays($schedule->scheduled_date);

        return $days <= 7 ? "in {$days} days" : $schedule->scheduled_date->format('d M');
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
            return back()->withErrors(['amount' => 'Schedule total exceeds due amount: ' . $dueField]);
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

        return back()->with('success', count($request->schedules) . ' schedule(s) created.');
    }

    public function partyTypesByCompany(Request $request)
    {
        $request->validate(['company_id' => 'required|integer']);

        $types = PartyType::where('company_id', $request->company_id)
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'model_class']);

        return response()->json($types->map(fn($pt) => [
            'id'        => $pt->id,
            'name'      => $pt->name,
            'has_model' => $pt->model_class ? 1 : 0,
        ]));
    }

    public function projectCategoriesByCompany(Request $request)
    {
        $request->validate(['company_id' => 'required|integer']);

        $categories = \App\Models\ProjectCategory::where('company_id', $request->company_id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($categories);
    }

    public function partiesByType(Request $request)
    {
        $request->validate(['type_id' => 'required|integer']);

        $partyType = PartyType::findOrFail($request->type_id);
        $companyId = $request->company_id ?? Auth::user()?->company_id;

        return response()->json($partyType->getParties($companyId));
    }

    public function storeAdHoc(Request $request)
    {
        $request->validate([
            'type'                => 'required|in:pay,receive',
            'company_id'          => 'nullable|exists:companies,id',
            'project_category_id' => 'nullable|exists:project_categories,id',
            'party_type_id'       => 'required|exists:party_types,id',
            'party_id'        => 'nullable|integer',
            'party_name'      => 'nullable|string|max:200',
            'source_label'    => 'required|string|max:200',
            'amount'          => 'required|numeric|min:0.01',
            'scheduled_date'  => 'required|date',
            'note'            => 'nullable|string|max:500',
            'priority'        => 'nullable|in:high,medium,low',
        ]);

        $partyTypeModel = PartyType::findOrFail($request->party_type_id);
        $partySlug      = $partyTypeModel->slug ?? strtolower($partyTypeModel->name);
        $partyId        = null;
        $partyName      = $request->party_name;

        if ($partyTypeModel->hasModel() && $request->party_id) {
            $partyId   = (int) $request->party_id;
            $found     = ($partyTypeModel->model_class)::find($partyId);
            $partyName = $found?->name ?? $partyName;
        }

        if (empty($partyName)) {
            return back()->withErrors(['party_name' => 'Party name is required.'])->withInput();
        }

        $userId    = Auth::id();
        $companyId = $request->company_id ?? Auth::user()?->company_id;

        DB::transaction(function () use ($request, $userId, $companyId, $partySlug, $partyId, $partyName) {
            $schedule = PaymentSchedule::create([
                'company_id'          => $companyId,
                'project_category_id' => $request->project_category_id ?: null,
                'schedulable_type'    => null,
                'schedulable_id'      => null,
                'type'                => $request->type,
                'party_type'       => $partySlug,
                'party_id'         => $partyId,
                'party_name'       => $partyName,
                'source_label'     => $request->source_label,
                'amount'           => $request->amount,
                'scheduled_date'   => $request->scheduled_date,
                'status'           => 'pending',
                'priority'         => $request->priority ?? 'medium',
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

            $this->postBalancedJournal(
                'payment_schedule',
                $schedule->id,
                $companyId,
                $request->scheduled_date,
                $reference,
                $request->source_label . ' — ' . $partyName,
                $debitAccount->id,
                $creditAccount->id,
                (float) $request->amount,
                $request->source_label,
                $request->source_label,
                $partySlug,
                $partyId
            );
        });

        return back()->with('success', 'Ad-hoc schedule created successfully.');
    }

    public function approve(string $_role, PaymentSchedule $schedule, Request $request)
    {
        if (! in_array($schedule->status, ['pending', 'overdue'])) {
            return back()->with('error', 'Only pending or overdue schedules can be approved.');
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

        return back()->with('success', 'Schedule approved successfully.');
    }

    public function reject(string $_role, PaymentSchedule $schedule, Request $request)
    {
        $request->validate([
            'approval_note' => 'required|string|max:500',
        ]);

        if (! in_array($schedule->status, ['pending', 'overdue', 'approved'])) {
            return back()->with('error', 'This schedule cannot be rejected at its current status.');
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

        return back()->with('success', 'Schedule rejected.');
    }

    public function reschedule(string $_role, PaymentSchedule $schedule, Request $request)
    {
        $request->validate([
            'new_date' => 'required|date',
            'reason'   => 'required|string|max:500',
        ]);

        if ($schedule->status === 'paid') {
            return back()->with('error', 'Cannot reschedule a paid schedule.');
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

        return back()->with('success', 'Schedule moved to ' . \Carbon\Carbon::parse($request->new_date)->format('d M Y') . '.');
    }

    public function markPaid(string $_role, PaymentSchedule $schedule, Request $request)
    {
        $request->validate([
            // Money cannot leave an account on a date that has not happened yet.
            // A future payment_date posts a journal entry the bank statement's
            // date filter then hides, so the dashboard card (all-time) and the
            // account ledger (date-ranged) disagree — 2026-08-15, SALPAY-SCH-356
            // was dated 30 Aug and left the bKash card reading -7,810 against a
            // ledger closing balance of 2,190. A payment not yet made is a
            // schedule, not a payment: leave it pending with a future
            // scheduled_date instead.
            'payment_date'   => 'required|date|before_or_equal:today',
            'payment_method' => 'nullable|string',
            'bank_id'        => 'required|exists:banks,id',
            'note'           => 'nullable|string|max:500',
            'paid_amount'    => 'nullable|numeric|min:0.01',
            'remainder_date' => 'nullable|date',
        ]);

        if ($schedule->status === 'paid') {
            return back()->with('error', 'This schedule is already marked as paid.');
        }

        if ($schedule->status === 'cancelled') {
            return back()->with('error', 'Cannot mark a cancelled schedule as paid.');
        }

        $paidAmt   = $request->filled('paid_amount')
            ? min((float) $request->paid_amount, (float) $schedule->amount)
            : (float) $schedule->amount;
        $isPartial = $paidAmt < ((float) $schedule->amount - 0.001);

        DB::transaction(function () use ($schedule, $request, $paidAmt, $isPartial) {

            $bank = Bank::findOrFail($request->bank_id);

            // Checked on schedulable_type (the stored string), not on the
            // resolved ->schedulable relation — a schedule whose underlying
            // record was since (soft-)deleted still has the type string set,
            // and must still route in here so settleEmployeeSchedule()'s
            // orphan guard can raise a clear error, instead of silently
            // falling through to an unrelated branch below.
            if (in_array($schedule->schedulable_type, [EmployeeSalary::class, SalaryReconciliation::class])) {
                $this->settleEmployeeSchedule($schedule, $bank, $request, $paidAmt, $isPartial);
                return;
            }

            // ── Ad-hoc schedule ──────────────────────────────────────────
            // pay:     DR Expense / CR AP  →  settlement: DR AP (2110) / CR Bank
            // receive: DR AR / CR Revenue  →  settlement: DR Bank / CR AR (1130)
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

                $this->postBalancedJournal(
                    'schedule_payment_settlement',
                    $schedule->id,
                    $schedule->company_id,
                    $request->payment_date,
                    $reference,
                    ($schedule->source_label ?? 'Ad-hoc') . ' — ' . $schedule->party_name,
                    $debitAccountId,
                    $creditAccountId,
                    $paidAmt,
                    $schedule->source_label ?? $schedule->note,
                    $schedule->source_label ?? $schedule->note,
                    $schedule->party_type,
                    null
                );

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

            // ── Visa Sale — keep paid/due/status in sync and clear AR ────
            if ($schedule->schedulable instanceof VisaSale) {
                $sale = $schedule->schedulable;

                $newPaid = min((float) $sale->paid_amount + $paidAmt, (float) $sale->total_amount);
                $newDue  = max(0, (float) $sale->total_amount - $newPaid);

                $sale->update([
                    'paid_amount' => $newPaid,
                    'due_amount'  => $newDue,
                    'status'      => $newDue <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'pending'),
                ]);

                if ($sale->client_id) {
                    $this->reconcilePartyBankBalance('visa_sale', $sale->id, $bank->id, $newPaid);
                    $this->reconcilePartyLedgerRow(
                        $sale->client_id, 'visa_sale', $sale->id, false, $newPaid,
                        [
                            'payment_date'   => $request->payment_date,
                            'reference_no'   => $request->reference_no ?? $sale->invoice_number,
                            'remarks'        => 'Visa sale payment received (schedule) — ' . $sale->invoice_number,
                            'account_id'     => $bank->id,
                            'payment_method' => $request->payment_method,
                        ]
                    );
                } else {
                    $bank->increment('balance', $paidAmt);
                }

                $this->postSaleJournalPayment(
                    'visa_sale',
                    $sale->id,
                    $sale->company_id ?? auth()->user()->company_id ?? 2,
                    $request->payment_date,
                    $request->reference_no ?? $sale->invoice_number,
                    'Visa sale payment received (schedule) — ' . $sale->invoice_number,
                    $paidAmt,
                    $bank->id
                );
            }

            // ── Contract Flight Booking / Contract File Sale — keep
            // paid/due/status in sync and clear AR, same shape as VisaSale ──
            if ($schedule->schedulable instanceof ContractFlightBooking || $schedule->schedulable instanceof ContractFileSale) {
                $sale = $schedule->schedulable;
                $source = $sale instanceof ContractFlightBooking ? 'flight_sale' : 'file_sale';
                $reference = $sale instanceof ContractFlightBooking ? $sale->booking_number : $sale->invoice_number;

                $newPaid = min((float) $sale->paid_amount + $paidAmt, (float) $sale->total_amount);
                $newDue  = max(0, (float) $sale->total_amount - $newPaid);

                $sale->update([
                    'paid_amount'    => $newPaid,
                    'due_amount'     => $newDue,
                    'payment_status' => $newDue <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'due'),
                ]);

                if ($sale->client_id) {
                    $this->reconcilePartyBankBalance($source, $sale->id, $bank->id, $newPaid);
                    $this->reconcilePartyLedgerRow(
                        $sale->client_id, $source, $sale->id, false, $newPaid,
                        [
                            'payment_date'   => $request->payment_date,
                            'reference_no'   => $request->reference_no ?? $reference,
                            'remarks'        => ucfirst(str_replace('_', ' ', $source)) . ' payment received (schedule) — ' . $reference,
                            'account_id'     => $bank->id,
                            'payment_method' => $request->payment_method,
                        ]
                    );
                } else {
                    $bank->increment('balance', $paidAmt);
                }

                $this->postSaleJournalPayment(
                    $source,
                    $sale->id,
                    auth()->user()->company_id ?? 2,
                    $request->payment_date,
                    $request->reference_no ?? $reference,
                    ucfirst(str_replace('_', ' ', $source)) . ' payment received (schedule) — ' . $reference,
                    $paidAmt,
                    $bank->id
                );
            }

            // ── Visa Process / Contract Flight / Contract File — vendor
            // payable settlement. These have no payment_status column (it's
            // not a meaningful field for a cost/AP-side record — the formal
            // journal below is the source of truth), so they get their own
            // branch instead of falling into the generic one, same shape as
            // the dedicated payVendor() actions on each controller ─────────
            if ($schedule->schedulable instanceof VisaProcess
                || $schedule->schedulable instanceof ContractFlight
                || $schedule->schedulable instanceof ContractFile) {
                $model = $schedule->schedulable;

                $source = match (true) {
                    $model instanceof VisaProcess    => 'visa_process',
                    $model instanceof ContractFlight => 'contract_flight',
                    default                           => 'contract_file',
                };
                $costAmount = match (true) {
                    $model instanceof VisaProcess    => (float) $model->costing_price,
                    $model instanceof ContractFlight => (float) $model->cost_price,
                    default                           => (float) $model->visa_rate,
                };
                $reference = match (true) {
                    $model instanceof VisaProcess    => $model->application_id,
                    $model instanceof ContractFlight => $model->flight_number,
                    default                           => $model->file_number,
                };

                if (! $bank->account_id) {
                    throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                }

                $newCostPaid = min((float) $model->cost_paid_amount + $paidAmt, $costAmount);
                $newDue      = max(0, $costAmount - $newCostPaid);

                $this->reconcileCostPayment($source, $model->id, $bank->id, null, $newCostPaid, $model->vendor_id, $request->payment_date);

                $this->postPurchaseJournalPayment(
                    $source,
                    $model->id,
                    auth()->user()->company_id ?? 2,
                    $request->payment_date,
                    $request->reference_no ?? $reference,
                    ucfirst(str_replace('_', ' ', $source)) . ' vendor payment received (schedule) — ' . $reference,
                    $paidAmt,
                    $bank->account_id
                );

                $model->forceFill(['due_amount' => $newDue, 'cost_paid_amount' => $newCostPaid])->saveQuietly();
            }

            // ── Ticket Purchase — vendor payable settlement. Its own
            // dedicated make_payment() action already does this correctly
            // (additive Transaction row per payment, not a cumulative
            // reverse-and-reapply row like the trait's reconcileCostPayment),
            // so mirror that exact shape here instead of falling into the
            // generic branch below, which never touched the bank or the
            // party ledger at all ─────────────────────────────────────────
            if ($schedule->schedulable instanceof TicketPurchase) {
                $tp = $schedule->schedulable;

                if (! $bank->account_id) {
                    throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                }

                // `old_balance`/`balance` are the vendor's own running
                // party-ledger balance, not the bank's — bank balance is
                // tracked separately below.
                $partyOldBalance = $tp->vendor_id
                    ? (float) (Transaction::where('user_id', $tp->vendor_id)->orderByDesc('id')->value('balance') ?? 0)
                    : 0.0;
                $partyNewBalance = $partyOldBalance - $paidAmt;

                Transaction::create([
                    'user_id'        => $tp->vendor_id,
                    'user_type'      => 'supplier',
                    'type'           => 'ticket_purchase',
                    'account_id'     => $bank->id,
                    'payment_date'   => $request->payment_date,
                    'reference_no'   => $request->reference_no,
                    'payment_method' => $request->payment_method,
                    'invoice_id'     => $tp->id,
                    'old_balance'    => $partyOldBalance,
                    'debit'          => 0,
                    'credit'         => $paidAmt,
                    'balance'        => $partyNewBalance,
                    'remarks'        => 'Ticket purchase due payment (schedule).',
                ]);

                $bank->update(['balance' => (float) $bank->balance - $paidAmt]);

                $newPaid = min((float) $tp->paid_amount + $paidAmt, (float) $tp->amount);
                $newDue  = max(0, (float) $tp->due_amount - $paidAmt);

                $tp->update([
                    'paid_amount'    => $newPaid,
                    'due_amount'     => $newDue,
                    'payment_status' => $newDue <= 0 ? 'paid' : 'partial',
                ]);

                $this->postPurchaseJournalPayment(
                    'ticket_purchase',
                    $tp->id,
                    auth()->user()->company_id ?? 2,
                    $request->payment_date,
                    $request->reference_no ?? $tp->ticket_no,
                    'Ticket purchase payment made (schedule) — ' . $tp->ticket_no,
                    $paidAmt,
                    $bank->account_id
                );
            }

            // ── Ticket Sale — receivable settlement. Its own dedicated
            // make_payment() action already does this correctly (additive
            // Transaction row per payment via a locally-defined
            // postPartyLedger() helper), so mirror that exact shape here
            // instead of falling into the generic branch below, which never
            // touched the bank, journal, or party ledger at all ───────────
            if ($schedule->schedulable instanceof TicketSale) {
                $ts = $schedule->schedulable;

                if (! $bank->account_id) {
                    throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                }

                $partyOldBalance = $ts->client_id
                    ? (float) (Transaction::where('user_id', $ts->client_id)->orderByDesc('id')->value('balance') ?? 0)
                    : 0.0;
                $partyNewBalance = $partyOldBalance - $paidAmt;

                Transaction::create([
                    'user_id'        => $ts->client_id,
                    'user_type'      => 'customer',
                    'type'           => 'ticket_sale',
                    'account_id'     => $bank->id,
                    'payment_date'   => $request->payment_date,
                    'reference_no'   => $request->reference_no,
                    'payment_method' => $request->payment_method,
                    'invoice_id'     => $ts->id,
                    'old_balance'    => $partyOldBalance,
                    'debit'          => 0,
                    'credit'         => $paidAmt,
                    'balance'        => $partyNewBalance,
                    'remarks'        => 'Ticket sale due receipt (schedule).',
                ]);

                $bank->increment('balance', $paidAmt);

                $newPaid = min((float) $ts->paid_amount + $paidAmt, (float) $ts->total_amount);
                $newDue  = max(0, (float) $ts->due_amount - $paidAmt);

                $ts->update([
                    'paid_amount'    => $newPaid,
                    'due_amount'     => $newDue,
                    'payment_status' => $newDue <= 0 ? 'paid' : 'partial',
                ]);

                $this->postSaleJournalPayment(
                    'ticket_sale',
                    $ts->id,
                    auth()->user()->company_id ?? 2,
                    $request->payment_date,
                    $request->reference_no ?? $ts->invoice_no,
                    'Ticket sale payment received (schedule) — ' . $ts->invoice_no,
                    $paidAmt,
                    $bank->id
                );
            }

            // ── Linked document (Sale / Purchase / Ticket) ───────────────
            $parent = $schedule->schedulable;
            if ($parent
                && !($parent instanceof VisaSale)
                && !($parent instanceof ContractFlightBooking)
                && !($parent instanceof ContractFileSale)
                && !($parent instanceof VisaProcess)
                && !($parent instanceof ContractFlight)
                && !($parent instanceof ContractFile)
                && !($parent instanceof TicketPurchase)
                && !($parent instanceof TicketSale)
                && isset($parent->due_amount)) {
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

        return back()->with('success', $msg);
    }

    /**
     * Settles $paidAmt against a schedule backed by an EmployeeSalary or
     * SalaryReconciliation record — the two employee-facing schedulable
     * types. Extracted out of markPaid() so it can also be called in a loop
     * by payDueAmount() (one lump-sum payment applied across several
     * pending schedules) without the two ever drifting apart.
     */
    private function settleEmployeeSchedule(PaymentSchedule $schedule, Bank $bank, Request $request, float $paidAmt, bool $isPartial): void
    {
        // Guards against a schedule left orphaned by its underlying
        // EmployeeSalary/SalaryReconciliation row being (soft-)deleted after
        // the schedule was created — the instanceof checks below would
        // otherwise silently match nothing and this method would return
        // having done nothing, letting the caller believe $paidAmt was
        // applied when it actually wasn't (misallocating a lump-sum payment
        // to the next schedule in line instead). Fail loudly so the whole
        // payment transaction rolls back instead.
        if (!$schedule->schedulable) {
            throw new \Exception("Payment schedule #{$schedule->id} points to a deleted {$schedule->schedulable_type} record (#{$schedule->schedulable_id}) — cannot settle automatically, needs manual review.");
        }

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

            $this->postBalancedJournal(
                'salary_payment',
                $schedule->id,
                auth()->user()->company_id ?? $salary->user?->company_id ?? 2,
                $request->payment_date,
                'SALPAY-SCH-' . $schedule->id,
                'Salary payment - ' . ($salary->user->name ?? 'Employee'),
                $salaryPayableAccount->id,
                $bank->account_id,
                $paidAmt,
                'Salary payable settled - ' . ($salary->user->name ?? 'Employee'),
                'Salary paid via ' . $request->payment_method,
                'employee',
                $salary->user_id
            );

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

            // ── EMPLOYEE LEDGER (auto) ─────────────────────────────────
            $this->postEmployeeLedgerRow($salary->user_id, [
                'type'       => 'salary_paid',
                'entry_date' => $request->payment_date,
                'reference'  => ($isPartial ? 'Partial payment — ' : '') . 'Salary paid via ' . $request->payment_method,
                'debit'      => 0,
                'credit'     => $paidAmt,
            ], $salary);
            // ── END EMPLOYEE LEDGER ───────────────────────────────────

            $this->notifySalaryPayment($salary, $paidAmt, $isPartial, $request);

            return;
        }

        // ── Salary Reconciliation (annual leave encashment) ──────────
        if ($schedule->schedulable instanceof SalaryReconciliation) {
            $reconciliation = $schedule->schedulable;

            if (! $isPartial) {
                $reconciliation->update(['status' => 'Paid']);
            }

            $salaryPayableAccount = Account::where('code', config('accounts.salary_payable'))->firstOrFail();
            if (! $bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }

            $this->postBalancedJournal(
                'salary_reconciliation_payment',
                $schedule->id,
                auth()->user()->company_id ?? $reconciliation->user?->company_id ?? 2,
                $request->payment_date,
                'RECONPAY-SCH-' . $schedule->id,
                'Leave encashment payment - ' . ($reconciliation->user->name ?? 'Employee'),
                $salaryPayableAccount->id,
                $bank->account_id,
                $paidAmt,
                'Leave encashment payable settled - ' . ($reconciliation->user->name ?? 'Employee'),
                'Leave encashment paid via ' . $request->payment_method,
                'employee',
                $reconciliation->user_id
            );

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
                'reason'              => ($isPartial ? 'Partial ৳' . number_format($paidAmt, 2) . ' of ৳' . number_format($schedule->amount, 2) . '. ' : '') . 'Leave encashment paid via ' . $request->payment_method . ' - Bank: ' . $bank->name,
                'done_by'             => auth()->id(),
            ]);

            if ($isPartial) {
                $this->createRemainderSchedule($schedule, $paidAmt, $request->remainder_date);
            }

            // ── EMPLOYEE LEDGER (auto) ─────────────────────────────────
            $this->postEmployeeLedgerRow($reconciliation->user_id, [
                'type'       => 'salary_reconciliation_paid',
                'entry_date' => $request->payment_date,
                'reference'  => ($isPartial ? 'Partial payment — ' : '') . 'Leave encashment paid via ' . $request->payment_method,
                'debit'      => 0,
                'credit'     => $paidAmt,
            ], $reconciliation);
            // ── END EMPLOYEE LEDGER ───────────────────────────────────

            return;
        }
    }

    /**
     * Pays down an employee's overall ledger due balance in one action from
     * the Account Summary page, without needing HR to open the Payment
     * Schedule module and mark each month's salary/encashment paid one at a
     * time. Walks that employee's pending "pay" schedules oldest-first and
     * settles each in full (via settleEmployeeSchedule() — identical logic
     * to a normal Mark Paid) until the entered amount runs out; the last
     * schedule touched becomes a partial payment (with its own remainder
     * schedule) if the amount doesn't fully cover it. Any leftover beyond
     * every pending schedule (e.g. covering a Bonus debit, which has no
     * schedule of its own) is settled as a plain ledger credit + journal
     * entry not tied to any specific schedule.
     */
    public function payDueAmount(Request $request, string $role, User $user)
    {
        $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            // Same rule as markPaid() above — see the note there.
            'payment_date'   => 'required|date|before_or_equal:today',
            'payment_method' => 'nullable|string',
            'bank_id'        => 'required|exists:banks,id',
            'note'           => 'nullable|string|max:500',
            'remainder_date' => 'nullable|date',
        ]);

        $ledgerBalance = (float) (EmployeeLedger::where('user_id', $user->id)->orderByDesc('id')->value('balance') ?? 0);

        if ($ledgerBalance <= 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'This employee has no outstanding due balance.',
            ]);
        }

        if ((float) $request->amount > $ledgerBalance + 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Amount cannot exceed the current due balance of ৳ ' . number_format($ledgerBalance, 2) . '.',
            ]);
        }

        $amount = (float) $request->amount;

        try {
            DB::transaction(function () use ($user, $request, $amount) {
                $bank = Bank::findOrFail($request->bank_id);
                $remaining = $amount;

                $schedules = PaymentSchedule::where('party_type', 'employee')
                    ->where('party_id', $user->id)
                    ->where('type', 'pay')
                    ->where('status', 'pending')
                    ->whereIn('schedulable_type', [EmployeeSalary::class, SalaryReconciliation::class])
                    ->orderBy('scheduled_date')
                    ->orderBy('id')
                    ->get();

                foreach ($schedules as $schedule) {
                    if ($remaining <= 0.01) {
                        break;
                    }

                    $outstanding = (float) $schedule->amount;
                    if ($outstanding <= 0.01) {
                        continue;
                    }

                    $payAmt = min($remaining, $outstanding);
                    $isPartial = $payAmt < ($outstanding - 0.001);

                    $this->settleEmployeeSchedule($schedule, $bank, $request, $payAmt, $isPartial);

                    $remaining = round($remaining - $payAmt, 2);
                }

                // Leftover beyond all pending schedules (e.g. a schedule-less
                // Bonus debit) — settle directly against the ledger.
                if ($remaining > 0.01) {
                    $salaryPayableAccount = Account::where('code', config('accounts.salary_payable'))->firstOrFail();
                    if (! $bank->account_id) {
                        throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                    }

                    $this->postBalancedJournal(
                        'employee_due_settlement',
                        $user->id,
                        auth()->user()->company_id ?? $user->company_id ?? 2,
                        $request->payment_date,
                        'DUEPAY-' . $user->id . '-' . now()->format('YmdHis'),
                        'Due balance settlement - ' . $user->name,
                        $salaryPayableAccount->id,
                        $bank->account_id,
                        $remaining,
                        'Due balance settled - ' . $user->name,
                        'Due balance paid via ' . $request->payment_method,
                        'employee',
                        $user->id
                    );

                    $this->postEmployeeLedgerRow($user->id, [
                        'type'       => 'due_settlement',
                        'entry_date' => $request->payment_date,
                        'reference'  => 'Due balance paid via ' . $request->payment_method,
                        'debit'      => 0,
                        'credit'     => $remaining,
                    ]);
                }
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment of ৳ ' . number_format($amount, 2) . ' recorded successfully.',
        ]);
    }

    /**
     * Salary-payment notifications — same channels/style as payslip
     * generation (see GenerateMonthlyPayslip::handle()): in-app + push via
     * NotificationService, a Brevo email, and an SMS. Each channel is
     * independently try/caught so a notification failure can never roll
     * back the DB::transaction() this runs inside (the ledger/journal
     * postings already succeeded by this point and must not be undone
     * just because, say, the SMS gateway timed out).
     */
    private function notifySalaryPayment(EmployeeSalary $salary, float $paidAmt, bool $isPartial, Request $request): void
    {
        $user = $salary->user;
        if (!$user) {
            return;
        }

        $remainingDue = max(0, (float) $salary->net_salary - (float) PaymentSchedule::where('schedulable_type', EmployeeSalary::class)
            ->where('schedulable_id', $salary->id)
            ->settled()
            ->sum('paid_amount'));

        try {
            NotificationService::notifySalaryPaid($salary, $paidAmt, $isPartial, $remainingDue);
        } catch (\Throwable $e) {
            Log::warning('Salary payment in-app/push notification failed.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        if ($user->email) {
            try {
                $this->sendSalaryPaymentEmail($user, $salary, $paidAmt, $isPartial, $remainingDue, (string) $request->payment_method, (string) $request->payment_date);
            } catch (\Throwable $e) {
                Log::warning('Salary payment email failed to send.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        if (!empty($user->phone)) {
            try {
                // previousDue = what was owed right before this specific
                // installment — $remainingDue already nets out this payment,
                // so adding it back gives the pre-payment balance without an
                // extra query.
                $previousDue = $remainingDue + $paidAmt;

                $smsMessage = "Dear {$user->name}, your payment summary: "
                    . 'Previous Due: ' . number_format($previousDue, 0)
                    . ' Paid: ' . number_format($paidAmt, 0)
                    . ' Current Due: ' . number_format($remainingDue, 0);

                $loanTaken = (float) ($salary->loan_deduction ?? 0);
                $advanceTaken = (float) ($salary->advance_salary_deduction ?? 0);
                if ($loanTaken > 0) {
                    $smsMessage .= ' Loan Taken: ' . number_format($loanTaken, 0);
                }
                if ($advanceTaken > 0) {
                    $smsMessage .= ' Advance Taken: ' . number_format($advanceTaken, 0);
                }

                $smsMessage .= ' Date: ' . \Carbon\Carbon::parse($request->payment_date)->format('d-m-Y') . ' Thank you';

                sendSms($user->phone, $smsMessage);
            } catch (\Throwable $e) {
                Log::warning('Salary payment SMS failed to send.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Mirrors GenerateMonthlyPayslip::sendPayslipEmail() — same Brevo
     * helper, same non-fatal-failure handling.
     */
    private function sendSalaryPaymentEmail(User $user, EmployeeSalary $salary, float $paidAmt, bool $isPartial, float $remainingDue, string $paymentMethod, string $paymentDate): void
    {
        $subject = ($isPartial ? 'আংশিক বেতন পরিশোধ' : 'বেতন পরিশোধ') . ' (Salary Payment) - ' . $salary->month . '/' . $salary->year;
        $htmlContent = view('emails.salary-payment-notice', [
            'user'         => $user,
            'salary'       => $salary,
            'paidAmt'      => $paidAmt,
            'isPartial'    => $isPartial,
            'remainingDue' => $remainingDue,
            'paymentMethod' => $paymentMethod,
            'paymentDate'  => $paymentDate,
        ])->render();

        $response = sendBrevoMail($user->email, $user->name, $subject, $htmlContent);

        if (!$response->successful()) {
            Log::warning('Brevo salary payment email API call failed.', [
                'user_id' => $user->id,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
        }
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

    public function cancel(string $_role, PaymentSchedule $schedule)
    {
        if ($schedule->status === 'paid') {
            return back()->with('error', 'Cannot cancel a paid schedule.');
        }

        $schedule->update(['status' => 'cancelled']);

        PaymentScheduleLog::create([
            'payment_schedule_id' => $schedule->id,
            'action'              => 'cancelled',
            'old_date'            => $schedule->scheduled_date,
            'done_by'             => auth()->id(),
        ]);

        return back()->with('success', 'Schedule cancelled.');
    }

    public function setPriority(string $_role, PaymentSchedule $schedule, Request $request)
    {
        $request->validate(['priority' => 'required|in:high,medium,low']);

        $schedule->update(['priority' => $request->priority]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'priority' => $schedule->priority]);
        }

        return back()->with('success', 'Priority updated to ' . ucfirst($request->priority) . '.');
    }

    public function destroy(string $_role, PaymentSchedule $paymentSchedule)
    {
        if ($paymentSchedule->status === 'paid') {
            return back()->with('error', 'Cannot delete a paid schedule.');
        }

        $paymentSchedule->delete();

        return back()->with('success', 'Schedule deleted.');
    }

    public function voucher(string $_role, PaymentSchedule $schedule)
    {
        $schedule->load([
            'company',
            'createdBy',
            'approvedBy',
            'logs.doneBy',
            'schedulable',
        ]);

        $company = $schedule->company ?? Company::orderBy('id')->first();

        return view('payment-schedules.voucher', compact('schedule', 'company'));
    }
}
