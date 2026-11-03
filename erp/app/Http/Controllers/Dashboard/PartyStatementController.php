<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\BankOverdrawnException;
use App\Exports\PartyStatementExport;
use App\Models\Account;
use App\Models\Bank;
use App\Models\Company;
use App\Models\ContractFile;
use App\Models\ContractFileServiceCost;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use App\Models\Country;
use App\Models\FinancingCapital;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PartyDiscount;
use App\Models\PartyInvoice;
use App\Models\PartyLedgerNotification;
use App\Models\PartyStatementLink;
use App\Models\TicketChange;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CompanyFunds;
use App\Services\CompanyLedger;
use App\Services\CompanyPartyDirectory;
use App\Services\ContractFlightAgentReceipts;
use App\Services\DepartmentParties;
use App\Services\FinancingPostingService;
use App\Services\PartyInvoiceAllocation;
use App\Services\PartyInvoicePostingService;
use App\Services\PartyLedgerService;
use App\Services\PartyNotifier;
use App\Services\PaymentScheduleReconciler;
use App\Services\SalesInvoice;
use App\Services\TicketRefundStatementPresenter;
use App\Support\StatementRows;
use App\Traits\ResolvesPartyType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class PartyStatementController implements HasMiddleware
{
    use ResolvesPartyType;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view party statement|view all party statement', only: [
                'index', 'agents', 'clients', 'vendors', 'company', 'activity', 'printActivity',
                'search', 'load', 'unpaidInvoices', 'exportExcel', 'salesInvoice',
                'salesInvoiceLines', 'exportPdf', 'invoicePdf', 'printFeed', 'printParties',
            ]),
            new Middleware('permission:create party statement', only: [
                'companyFunds', 'recordVendorRefund', 'recordPayment', 'createInvoice', 'setOpeningBalance',
            ]),
            new Middleware('permission:edit party statement', only: [
                'updateTransactionDate', 'sendStatement', 'sendReminder', 'invoiceBranding',
            ]),
        ];
    }

    /**
     * The three kinds of party, keyed by the URL segment each ledger lives on.
     *
     *   role    the Spatie role that makes a user this kind of party — the same
     *           roster Party Management → Manage Vendor/Agent/Customer lists
     *   prefix  the party code shown everywhere (EP-AG-041); must match the
     *           codes the bank dashboard prints under a beneficiary
     *           (BankControllerV2::dashboardPayload) or a code seen on one
     *           screen will not be findable on the other
     *   side    which way the money runs — a LABEL, not a sign. On every party
     *           ledger in this system debit raises the outstanding balance and
     *           credit settles it, whoever the party is: a ticket sale posts a
     *           debit on the agent, a ticket purchase posts a debit on the
     *           vendor (TicketPurchaseController: "Ticket purchase cost" Dr,
     *           "due payment" Cr). So net = debit − credit > 0 is always "the
     *           outstanding amount"; for a receivable type that is what they
     *           owe us, for a payable type what we owe them. Do not flip the
     *           sign for vendors — PartyLedgerService's docblock says positive
     *           is "the party owes us", and that is only true for receivables.
     *
     * A user holds exactly one of these roles (checked: 0 of 69 parties carry
     * two), so a party belongs on exactly one ledger.
     */
    public const TYPES = [
        'agent' => ['role' => 'agent',    'label' => 'Agent',  'plural' => 'Agents',  'prefix' => 'EP-AG-', 'side' => 'receivable'],
        'vendor' => ['role' => 'vendor',   'label' => 'Vendor', 'plural' => 'Vendors', 'prefix' => 'EP-VN-', 'side' => 'payable'],
        'client' => ['role' => 'customer', 'label' => 'Customer', 'plural' => 'Customers', 'prefix' => 'EP-CU-', 'side' => 'receivable'],
    ];

    /** A balance with no movement for this long is flagged for follow-up. */
    public const STALE_DAYS = 60;

    /**
     * Canned messages for the printed statement's Note box — picked from the
     * Print chooser's dropdown, or typed over freely. 'general' is what a
     * statement carries when nobody picks a template at all (the quick "PDF"
     * button skips the chooser entirely), so the sheet is never sent with an
     * empty Note box.
     */
    public const NOTE_TEMPLATES = [
        'general' => 'Please review this statement carefully. If you notice any discrepancy or have a question about any entry, kindly contact us at your earliest convenience.',
        'due' => 'Kindly note the outstanding balance shown above. We request you to settle the due amount at your earliest convenience. For any query, please contact us.',
        'mismatch' => 'If any transaction in this statement does not match your own records, please inform us immediately so we can review and correct it together.',
    ];

    /**
     * The module's front door. With ?party_id= it forwards to the ledger that
     * party belongs on — the sidebar link and the bank dashboard's beneficiary
     * codes both arrive here, and neither knows the party's type. Without one
     * it is the dashboard.
     */
    public function index(Request $request)
    {
        if ($partyId = $request->integer('party_id')) {
            $party = User::find($partyId);
            $type = $party ? $this->typeOf($party) : null;

            if ($type) {
                return redirect()->route("role.party-statement.{$type}s", array_filter([
                    'role' => $request->route('role'),
                    'party_id' => $partyId,
                    'company' => $request->query('company'),
                    'project' => $request->query('project'),
                ]));
            }
            // Not a party (or no longer one): fall through to the dashboard
            // rather than 404 a link someone bookmarked.
        }

        return view('party-statement.dashboard', $this->dashboardPayload($request));
    }

    public function agents(Request $request)
    {
        return $this->ledger($request, 'agent');
    }

    public function clients(Request $request)
    {
        return $this->ledger($request, 'client');
    }

    public function vendors(Request $request)
    {
        return $this->ledger($request, 'vendor');
    }

    /**
     * The Company Ledger — what the company bears on contract flights against
     * what it recovers. Journal-backed and read-only; see CompanyLedger.
     */
    public function company(Request $request)
    {
        // The screen holds every row and filters in the browser, as the
        // dashboard feed does. ?print=1 is the Print chooser's sheet: ticked
        // ids win over dates, as on every other sheet here.
        if (! $request->boolean('print')) {
            return view('party-statement.company', CompanyLedger::entries() + [
                'funds' => CompanyFunds::summary(),
                'owners' => User::whereIn('name', self::CAPITAL_OWNERS)->orderBy('name')->get(['id', 'name']),
            ]);
        }

        $from = $request->filled('from') ? $request->date('from')?->toDateString() : null;
        $to = $request->filled('to') ? $request->date('to')?->toDateString() : null;
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $itemIds = $this->idList($request->input('items'));

        $data = CompanyLedger::entries($itemIds ? null : $from, $itemIds ? null : $to);
        $rows = $itemIds ? $data['rows']->whereIn('id', $itemIds)->values() : $data['rows'];

        return view('party-statement.print-company', [
            'company' => Company::find(2) ?? Company::first(),
            'rows' => $rows,
            'totals' => [
                'borne' => (float) $rows->sum('borne'),
                'recovered' => (float) $rows->sum('recovered'),
                'net' => (float) $rows->sum('borne') - (float) $rows->sum('recovered'),
            ],
            'dateFrom' => $itemIds ? null : $from,
            'dateTo' => $itemIds ? null : $to,
            'selection' => $itemIds ? $rows->count() : 0,
        ]);
    }

    /** Consolidated period activity across agents, vendors and customers. */
    public function activity(Request $request)
    {
        return view('party-statement.activity', $this->activityPayload($request));
    }

    public function printActivity(Request $request)
    {
        return view('party-statement.print-activity', $this->activityPayload($request));
    }

    private function activityPayload(Request $request): array
    {
        $companyId = $this->companyFilter($request);
        $partyType = array_key_exists((string) $request->input('party_type'), self::TYPES)
            ? (string) $request->input('party_type')
            : 'all';
        $preset = in_array($request->input('period'), ['today', 'yesterday', 'last7', 'month', 'last_month', 'year', 'custom', 'all'], true)
            ? $request->input('period')
            : 'last7';

        $today = today();
        [$from, $to] = match ($preset) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'yesterday' => [$today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
            'month' => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'year' => [$today->copy()->startOfYear()->toDateString(), $today->toDateString()],
            'all' => [null, null],
            'custom' => [
                Carbon::hasFormat((string) $request->input('from'), 'Y-m-d') ? $request->input('from') : null,
                Carbon::hasFormat((string) $request->input('to'), 'Y-m-d') ? $request->input('to') : null,
            ],
            default => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
        };
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $typeOfUser = $this->typeOfUsers();
        if ($partyType !== 'all') {
            $typeOfUser = array_filter($typeOfUser, fn ($type) => $type === $partyType);
        }
        $partyIds = array_keys($typeOfUser);
        $selectedPartyId = $request->integer('party_id');
        if ($selectedPartyId && in_array($selectedPartyId, $partyIds, true)) {
            $partyIds = [$selectedPartyId];
        }

        $base = Transaction::query()
            ->whereIn('user_id', $partyIds ?: [0])
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId));
        $periodQuery = (clone $base)
            ->when($from, fn ($query) => $query->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('payment_date', '<=', $to));

        $periodRows = (clone $periodQuery)
            ->selectRaw('user_id, COUNT(*) AS transaction_count, COALESCE(SUM(debit), 0) AS debit_total, COALESCE(SUM(credit), 0) AS credit_total, MAX(payment_date) AS last_transaction')
            ->groupBy('user_id')->get()->keyBy('user_id');
        $currentBalances = (clone $base)
            ->selectRaw('user_id, COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS current_balance')
            ->groupBy('user_id')->pluck('current_balance', 'user_id');
        $users = User::whereIn('id', $partyIds ?: [0])->get(['id', 'name', 'phone'])->keyBy('id');

        $rows = collect($partyIds)->map(function ($partyId) use ($users, $periodRows, $currentBalances, $typeOfUser) {
            $period = $periodRows->get($partyId);
            if (! $period) {
                return null;
            }
            $debit = (float) $period->debit_total;
            $credit = (float) $period->credit_total;

            return [
                'id' => $partyId,
                'code' => self::partyCode($partyId, $typeOfUser[$partyId]),
                'name' => optional($users->get($partyId))->name,
                'phone' => optional($users->get($partyId))->phone,
                'type' => $typeOfUser[$partyId],
                'count' => (int) $period->transaction_count,
                'debit' => $debit,
                'credit' => $credit,
                'net' => $debit - $credit,
                'current' => (float) ($currentBalances[$partyId] ?? 0),
                'last_at' => $period->last_transaction ? substr((string) $period->last_transaction, 0, 10) : null,
            ];
        })->filter()->sortByDesc('debit')->values();

        $companyRows = (clone $periodQuery)
            ->selectRaw('company_id, COUNT(*) AS transaction_count, COALESCE(SUM(debit), 0) AS debit_total, COALESCE(SUM(credit), 0) AS credit_total')
            ->groupBy('company_id')->get();
        $companyNames = Company::whereIn('id', $companyRows->pluck('company_id')->filter())->pluck('name', 'id');
        $companyBreakdown = $companyRows->map(fn ($row) => [
            'name' => $companyNames[$row->company_id] ?? 'Unassigned company',
            'count' => (int) $row->transaction_count,
            'debit' => (float) $row->debit_total,
            'credit' => (float) $row->credit_total,
            'net' => (float) $row->debit_total - (float) $row->credit_total,
        ])->sortByDesc('debit')->values();

        $summary = [
            'parties' => $rows->count(),
            'transactions' => $rows->sum('count'),
            'debit' => (float) $rows->sum('debit'),
            'credit' => (float) $rows->sum('credit'),
            'net' => (float) $rows->sum('net'),
            'current' => (float) $rows->sum('current'),
        ];
        $periodLabel = $from || $to
            ? (($from ? Carbon::parse($from)->format('d M Y') : 'Beginning').' – '.($to ? Carbon::parse($to)->format('d M Y') : 'Today'))
            : 'All Time';

        return compact('rows', 'summary', 'companyBreakdown', 'from', 'to', 'preset', 'periodLabel', 'partyType', 'companyId') + [
            'companies' => $this->filterCompanies(),
            'parties' => User::whereIn('id', array_keys($typeOfUser))->orderBy('name')->get(['id', 'name']),
            'selectedPartyId' => $selectedPartyId,
            'selectedCompany' => $companyId ? Company::find($companyId) : null,
        ];
    }

    /**
     * One ledger screen for one kind of party: every party of that type with
     * their live balance, and the statement panel that opens under the row you
     * pick. ?party_id= opens that party on arrival.
     */
    private function ledger(Request $request, string $type)
    {
        $meta = self::TYPES[$type];
        $companyId = $this->companyFilter($request);
        $projectId = $this->projectFilter($request, $companyId);
        $parties = $this->partiesWithBalances($type, $companyId, $projectId);
        // Opened as a department's Agent / Vendor Ledger tab (Visa, Ticketing, Contract
        // Flight accounts — owner, 2026-10-17): only the parties that department did
        // business with. This page on its own sets no department and lists everyone.
        $dept = $this->departmentScope($request->attributes->get('party_scope_dept'));
        if ($dept) {
            $parties = $parties->whereIn('id', $dept['ids'])->values();
        }

        $autoPartyId = $request->integer('party_id') ?: null;
        // A party_id that is not on THIS ledger is not opened: the deep link
        // would put a vendor's statement under the Agents tab.
        if ($autoPartyId && ! $parties->contains('id', $autoPartyId)) {
            $autoPartyId = null;
        }

        return view('party-statement.ledger', [
            'type' => $type,
            'meta' => $meta,
            'parties' => $parties->values(),
            'summary' => $this->summarise($parties, $meta['side']),
            'autoPartyId' => $autoPartyId,
            'banks' => Bank::with('company')->where(function ($query) {
                $query->where('status', 1)->orWhereNull('status');
            })->get(['id', 'name', 'account_number', 'type', 'company_id', 'balance']),
            'countries' => Country::orderBy('name')->get(['id', 'name']),
            'companies' => $this->filterCompanies(),
            'companyId' => $companyId,
            'projects' => $this->filterProjects($companyId),
            'projectId' => $projectId,
            'deptScope' => $dept['key'] ?? null,
        ]);
    }

    /**
     * A department scope ('visa' …) and its party ids, or null — no department, an
     * unknown one, or a lookup that failed (then the list is everyone's, as before).
     */
    private function departmentScope(?string $dept): ?array
    {
        if (! $dept || ! in_array($dept, DepartmentParties::DEPARTMENTS, true)) {
            return null;
        }
        try {
            return ['key' => $dept, 'ids' => DepartmentParties::ids($dept)];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Which ledger a user belongs on, or null if they are not a party. */
    private function typeOf(User $user): ?string
    {
        foreach (self::TYPES as $type => $meta) {
            if ($user->hasRole($meta['role'])) {
                return $type;
            }
        }

        return null;
    }

    /** EP-AG-041 — the code a party is known by on every screen. */
    public static function partyCode(int $id, string $type): string
    {
        return self::TYPES[$type]['prefix'].str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Every party of one type with their whole-ledger balance, in two queries:
     * the roster, then one GROUP BY over their transactions. Never one query
     * per party — the agent list is 41 rows today and only grows.
     *
     * `net` is debit − credit. `owed` is its positive part — the outstanding
     * amount, whichever way it runs (see TYPES on why the sign is the same for
     * both sides). `advance` is the negative part: a client who has overpaid,
     * a vendor we have paid ahead.
     */
    private function partiesWithBalances(string $type, ?int $companyId = null, ?int $projectId = null): Collection
    {
        $meta = self::TYPES[$type];
        $users = User::role($meta['role'])
            ->when($companyId, fn ($q) => $q->whereIn('id', DB::table('company_party')->where('company_id', $companyId)->where('party_type', $type)->select('user_id')))
            ->when($projectId, fn ($q) => $q->whereIn('id', DB::table('project_party')->where('expense_department_id', $projectId)->where('party_type', $type)->select('user_id')))
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email', 'contact_person', 'status', 'service_categories']);

        if ($users->isEmpty()) {
            return collect();
        }

        $agg = Transaction::whereIn('user_id', $users->pluck('id'))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($projectId, fn ($q) => $q->where('expense_department_id', $projectId))
            ->selectRaw('user_id, COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS net, COUNT(*) AS entries, MAX(payment_date) AS last_at')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $staleBefore = now()->subDays(self::STALE_DAYS)->toDateString();

        return $users->map(function (User $u) use ($agg, $type, $staleBefore) {
            $row = $agg->get($u->id);
            $net = $row ? (float) $row->net : 0.0;
            $owed = max($net, 0);
            $adv = max(-$net, 0);
            $last = $row && $row->last_at ? substr((string) $row->last_at, 0, 10) : null;

            return [
                'id' => $u->id,
                'code' => self::partyCode($u->id, $type),
                'name' => $u->name,
                'phone' => $u->phone,
                'email' => $u->email,
                'contact_person' => $u->contact_person,
                // what the party deals in — the services ticked on its profile
                'services' => collect($u->service_categories ?? [])
                    ->map(fn ($s) => self::SERVICE_LABELS[$s] ?? ucfirst((string) $s))->unique()->values()->all(),
                'kinds' => array_values(array_unique(array_map('strval', $u->service_categories ?? []))),
                'initials' => $this->initials($u->name),
                'net' => $net,
                'owed' => $owed,
                'advance' => $adv,
                'entries' => $row ? (int) $row->entries : 0,
                'last_at' => $last,
                // Money outstanding and nothing has moved in STALE_DAYS: the
                // ones a follow-up call is for.
                'stale' => $owed > 0 && $last !== null && $last < $staleBefore,
            ];
        });
    }

    /** The figures a ledger's header and a dashboard card both print. */
    private function summarise(Collection $parties, string $side): array
    {
        return [
            'side' => $side,
            'count' => $parties->count(),
            'active' => $parties->where('entries', '>', 0)->count(),
            'owing' => $parties->where('owed', '>', 0)->count(),
            'owed' => (float) $parties->sum('owed'),
            'advance' => (float) $parties->sum('advance'),
            'stale' => $parties->where('stale', true)->count(),
            'top' => $parties->where('owed', '>', 0)->sortByDesc('owed')->take(5)->values(),
        ];
    }

    /**
     * Everything the dashboard draws. Three rosters with balances, the month's
     * movement per type, and the latest entries across every party.
     *
     * "This month" is month-to-date. `up` is the month's debits — what was
     * billed to a receivable party or purchased from a payable one, either way
     * the side that raises what is owed. `down` is the credits that settled it.
     */
    private function dashboardPayload(Request $request): array
    {
        $companyId = $this->companyFilter($request);
        $projectId = $this->projectFilter($request, $companyId);
        $types = [];
        $typeOfUser = [];
        foreach (self::TYPES as $type => $meta) {
            $parties = $this->partiesWithBalances($type, $companyId, $projectId);
            $types[$type] = ['meta' => $meta] + $this->summarise($parties, $meta['side']);
            foreach ($parties as $p) {
                $typeOfUser[$p['id']] = $type;
            }
        }

        $ids = array_keys($typeOfUser);

        // Month-to-date, one query, bucketed by type in PHP.
        $month = ['agent' => ['up' => 0.0, 'down' => 0.0], 'vendor' => ['up' => 0.0, 'down' => 0.0], 'client' => ['up' => 0.0, 'down' => 0.0]];
        if ($ids) {
            $rows = Transaction::whereIn('user_id', $ids)
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->when($projectId, fn ($q) => $q->where('expense_department_id', $projectId))
                ->whereDate('payment_date', '>=', now()->startOfMonth()->toDateString())
                ->selectRaw('user_id, COALESCE(SUM(debit), 0) AS d, COALESCE(SUM(credit), 0) AS c')
                ->groupBy('user_id')
                ->get();
            foreach ($rows as $r) {
                $type = $typeOfUser[$r->user_id] ?? null;
                if (! $type) {
                    continue;
                }
                $month[$type]['up'] += (float) $r->d;
                $month[$type]['down'] += (float) $r->c;
            }
        }

        // Each row carries the party's balance AFTER it — summed in SQL over
        // that party's rows up to and including this one, in the statement's
        // own order (payment_date, then id), so it agrees with the running
        // balance the statement prints. Never the stored `balance` column: it
        // is a snapshot taken in insertion order and drifts the moment anything
        // is back-dated (see PartyLedgerService).
        // The last 14 days, as the bank feed holds — the table bar's
        // Today / Yesterday / Last Week / All 14 Days narrow within it in the
        // browser. Capped so a runaway import cannot ship the whole ledger;
        // the party pages hold the full history.
        $recent = $ids
            ? $this->feedQuery($ids, $companyId)
                ->when($projectId, fn ($q) => $q->where('transactions.expense_department_id', $projectId))
                ->whereDate('payment_date', '>=', now()->subDays(14)->toDateString())
                ->limit(500)
                ->get()
            : collect();
        $recent = $this->feedRows($recent, $typeOfUser);

        return [
            'types' => $types,
            'month' => $month,
            'recent' => $recent,
            'receivable' => $types['agent']['owed'] + $types['client']['owed'],
            'payable' => $types['vendor']['owed'],
            'staleDays' => self::STALE_DAYS,
            'companyLedger' => CompanyLedger::summary(),
            'feedWindow' => ['from' => now()->subDays(14)->toDateString(), 'to' => now()->toDateString()],
            'companies' => $this->filterCompanies(),
            'companyId' => $companyId,
            'projects' => $this->filterProjects($companyId),
            'projectId' => $projectId,
        ];
    }

    public function search(Request $request)
    {
        $q = $request->q;
        $type = $request->type ?? 'all';

        $query = User::query();
        $companyId = $this->companyFilter($request);

        match ($type) {
            'vendor' => $query->role('vendor'),
            'agent' => $query->role('agent'),
            'client' => $query->role('customer'),
            default => $query->role(['vendor', 'agent', 'customer']),
        };
        if ($companyId) {
            $query->whereIn('id', DB::table('company_party')->where('company_id', $companyId)->select('user_id'));
        }

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('id', $q)
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->limit(30)->get(['id', 'name', 'phone', 'email', 'contact_person'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'phone' => $p->phone,
                    'email' => $p->email,
                    'contact_person' => $p->contact_person,
                    'roles' => $p->getRoleNames(),
                    'initials' => $this->initials($p->name),
                ])
        );
    }

    public function load(Request $request)
    {
        $request->validate(['party_id' => 'required|exists:users,id']);

        $partyId = $request->party_id;
        $from = $request->from;
        $to = $request->to;
        $type = $request->type;
        $party = User::findOrFail($partyId);

        $companyId = $this->companyFilter($request);
        $projectId = $this->projectFilter($request, $companyId);
        $ledgerService = app(PartyLedgerService::class);
        [$transactions, $openingBalance] = $this->fetchTransactions($partyId, $from, $to, $type, $companyId, $projectId);

        // Which bank a payment went through, in one query for the whole page —
        // and the bank ledger entry behind it, where one can be found.
        $transactions->load('account:id,name,account_number');
        $rawTransactions = $ledgerService->markReversalPairs($transactions);
        $transactions = app(TicketRefundStatementPresenter::class)->group(
            $rawTransactions->filter(fn ($row) => ! $row->reversal_pair_id)->values(),
            (float) $openingBalance,
            foldSale: false
        );
        $bankEntries = $this->bankEntriesFor($transactions);
        [$reasons, $details, $facts, $entries] = $this->reasonsFor($transactions);
        $saleSplits = $this->ticketSaleSplits($transactions);
        $reversedRows = $rawTransactions->filter(fn ($row) => $row->reversal_pair_id)->values();
        [$rReasons, $rDetails, $rFacts] = $reversedRows->isEmpty() ? [[], [], []] : $this->reasonsFor($reversedRows);

        $totalDebit = (float) $transactions->sum('debit');
        $totalCredit = (float) $transactions->sum('credit');
        $closingBalance = $transactions->isNotEmpty()
                        ? (float) $transactions->last()->balance
                        : $openingBalance;

        // Do not describe an empty calendar period before this party's ledger
        // actually began. For example, "This Year" may request 1 January, but
        // if the first row is 19 September the statement and print chooser must
        // say 19 September. With no upper bound, the statement runs through
        // today; an explicitly selected upper bound remains unchanged.
        $ledgerStarted = $ledgerService->firstTransactionDate($partyId, $type, $companyId, $projectId);
        $effectiveFrom = $from && $ledgerStarted ? max($from, $ledgerStarted) : ($from ?: $ledgerStarted);
        $effectiveTo = $to ?: ($ledgerStarted ? today()->toDateString() : null);

        return response()->json([
            'party' => $this->partyData($party),
            'opening_balance' => $openingBalance,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => $closingBalance,
            // The whole-ledger figure, whatever window or type filter is on —
            // what the Reminder button and the WhatsApp summary quote.
            'current_balance' => app(PartyLedgerService::class)->currentBalance($partyId, $companyId),
            // A no-login short link to this statement as PDF (/s/kX9pQ2Ab),
            // for the WhatsApp message — wa.me can prefill text but never
            // attach a file. The short link expires after 30 days and covers
            // exactly the range on screen.
            'share_pdf_url' => $this->sharedPdfUrl($partyId, $from, $to),
            'date_from' => $effectiveFrom,
            'date_to' => $effectiveTo,
            'transactions' => $transactions->map(fn ($t) => [
                'id' => $t->id,
                'reversal_pair_id' => $t->reversal_pair_id,
                'bank_entry' => $bankEntries[$t->id] ?? null,
                // no "customer" on the ledger (owner, 2026-10-27) — StatementRows::plain, display only
                'reason' => array_map(fn ($l) => is_array($l) && isset($l['text']) ? ['text' => StatementRows::plain((string) $l['text'])] + $l : $l, $reasons[$t->id] ?? []),
                'detail' => $details[$t->id] ?? null,
                // who / what / how — the statement's own columns.
                'facts' => $facts[$t->id] ?? null,
                'payment_date' => $t->payment_date,
                'transaction_time' => $t->transaction_time
                    ? substr((string) $t->transaction_time, 0, 5)
                    : optional($t->created_at)->format('H:i'),
                // When the row was written — the small time under the date,
                // shown only when it was recorded on the day it is dated.
                'recorded_at' => optional($t->created_at)->format('Y-m-d H:i:s'),
                'reference_no' => $t->reference_no,
                'type' => $t->type,
                'user_type' => $t->user_type,
                'payment_method' => $t->payment_method,
                'bank' => optional($t->account)->name,
                'bank_no' => optional($t->account)->account_number,
                'invoice_id' => $t->invoice_id,
                'debit' => (float) $t->debit,
                'credit' => (float) $t->credit,
                // Both recomputed by PartyLedgerService in statement order.
                'old_balance' => (float) $t->old_balance,
                'balance' => (float) $t->balance,
                'remarks' => ($rm = $entries[$t->id] ?? $t->display_remarks) === null ? null : StatementRows::plain((string) $rm),
                'refund_summary' => $t->refund_summary ?? null,
                'split' => $saleSplits[$t->id] ?? null,
            ]),
            // The complete cancelled pairs, kept out of `transactions` (and its
            // totals / running balance) but listed here so the "Reversed
            // transactions" view can show them. Balances are the stored
            // snapshot; the pair nets to zero.
            'reversed_transactions' => $reversedRows->map(fn ($t) => [
                'id' => $t->id,
                'reversal_pair_id' => $t->reversal_pair_id,
                'bank_entry' => null,
                // the same Details as an active row (owner, 2026-10-26: reversed rows read blank)
                'reason' => $rReasons[$t->id] ?? [],
                'detail' => $rDetails[$t->id] ?? null,
                'facts' => $rFacts[$t->id] ?? null,
                'payment_date' => $t->payment_date,
                'transaction_time' => $t->transaction_time
                    ? substr((string) $t->transaction_time, 0, 5)
                    : optional($t->created_at)->format('H:i'),
                'recorded_at' => optional($t->created_at)->format('Y-m-d H:i:s'),
                'reference_no' => $t->reference_no,
                'type' => $t->type,
                'user_type' => $t->user_type,
                'payment_method' => $t->payment_method,
                'bank' => optional($t->account)->name,
                'bank_no' => optional($t->account)->account_number,
                'invoice_id' => $t->invoice_id,
                'debit' => (float) $t->debit,
                'credit' => (float) $t->credit,
                'old_balance' => (float) $t->old_balance,
                'balance' => (float) $t->balance,
                'remarks' => $t->display_remarks,
                'refund_summary' => null,
            ]),
            'audit_transactions' => $rawTransactions->map(fn ($t) => [
                'id' => $t->id,
                'reversal_pair_id' => $t->reversal_pair_id,
                'payment_date' => $t->payment_date,
                'reference_no' => $t->reference_no,
                'type' => $t->type,
                'invoice_id' => $t->invoice_id,
                'debit' => (float) $t->debit,
                'credit' => (float) $t->credit,
                'remarks' => $t->display_remarks,
            ]),
        ]);
    }

    /**
     * A ticket sale's tickets, one line each, so the screen draws one row per
     * passenger (owner, 2026-10-27: "each row with one passenger"). Display
     * only — the ledger row stays one. Sent only when the prices add up to the
     * row's debit; otherwise the row is drawn as before.
     */
    private function ticketSaleSplits($transactions): array
    {
        $sales = $transactions->filter(fn ($t) => $t->type === 'ticket_sale' && $t->invoice_id && (float) $t->debit > 0 && (float) $t->credit == 0);
        if ($sales->isEmpty()) {
            return [];
        }
        try {
            $items = DB::table('ticket_sale_items as i')
                ->join('ticket_purchases as p', 'p.id', '=', 'i.ticket_purchase_id')
                ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                ->leftJoin('tickets as t', 't.id', '=', 'p.ticket_id')
                ->leftJoin('airports as a', 'a.id', '=', 't.from_airport_id')
                ->leftJoin('airports as b', 'b.id', '=', 't.to_airport_id')
                ->whereIn('i.ticket_sale_id', $sales->pluck('invoice_id')->unique()->all())
                ->orderBy('i.id')
                ->get(['i.ticket_sale_id', 'i.price', 'p.ticket_no', 't.title as route_title', 'h.name as holder', 'a.code as from_code', 'b.code as to_code'])
                ->groupBy('ticket_sale_id');
        } catch (\Throwable $e) {
            \Log::warning('ticketSaleSplits: '.$e->getMessage());

            return [];
        }
        $out = [];
        foreach ($sales as $t) {
            $rows = $items->get($t->invoice_id, collect());
            if ($rows->count() < 2 || abs($rows->sum(fn ($r) => (float) $r->price) - (float) $t->debit) > 0.01) {
                continue;
            }
            $out[$t->id] = $rows->map(fn ($r) => [
                'holder' => $r->holder,
                'pnr' => $r->ticket_no,
                'route' => $r->route_title ?: trim(($r->from_code ?? '').' → '.($r->to_code ?? ''), ' →'),
                'price' => (float) $r->price,
            ])->values()->all();
        }

        return $out;
    }

    /** Correct a party-ledger row's posting order with a permanent audit trail. */
    public function updateTransactionDate(Request $request, $role, Transaction $transaction)
    {
        $companyId = $this->companyFilter($request);
        abort_if($companyId && (int) $transaction->company_id !== $companyId, 403);
        $isDiscount = $transaction->type === 'party_discount';
        abort_if($isDiscount && ! $request->user()?->is_super_admin, 403);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'note' => ['required', 'string', 'min:5', 'max:500'],
            'confirmation' => ['required', Rule::in(['CONFIRM'])],
        ]);

        $targetTransactions = collect([$transaction]);
        $linkedJournalIds = collect();
        $discounts = collect();
        if ($isDiscount && preg_match('/^DISC-(\d+)$/i', (string) $transaction->reference_no, $match)) {
            $discount = PartyDiscount::find((int) $match[1]);
            if ($discount) {
                $discounts = $discount->batch_key
                    ? PartyDiscount::where('batch_key', $discount->batch_key)->whereNull('reversed_at')->get()
                    : collect([$discount]);
                $linkedJournalIds = $discounts->pluck('journal_entry_id')->filter()->values();
                $targetTransactions = Transaction::where('user_id', $transaction->user_id)
                    ->where('company_id', $transaction->company_id)
                    ->where('type', 'party_discount')->whereIn('invoice_id', $linkedJournalIds)
                    ->orderBy('id')->get();
            }
        }
        abort_if($isDiscount && ($discounts->isEmpty() || $targetTransactions->isEmpty()), 422, 'The source discount could not be identified.');

        $oldDate = $transaction->payment_date ? Carbon::parse($transaction->payment_date)->toDateString() : null;
        $oldTime = $transaction->transaction_time
            ? substr((string) $transaction->transaction_time, 0, 5)
            : optional($transaction->created_at)->format('H:i');

        if ($oldDate === $validated['date'] && $oldTime === $validated['time']) {
            return response()->json(['success' => true, 'message' => 'Date and time are unchanged.']);
        }

        // Resolve the linked journal before changing the date: legacy links are
        // partly matched by their existing date and would disappear afterwards.
        if (! $isDiscount) {
            $linkedJournalId = $this->bankEntriesFor(collect([$transaction]))[$transaction->id]['entry_id'] ?? null;
            $linkedJournalIds = $linkedJournalId ? collect([$linkedJournalId]) : collect();
        }

        DB::transaction(function () use ($targetTransactions, $discounts, $validated, $linkedJournalIds) {
            foreach ($targetTransactions as $target) {
                $targetOldDate = $target->payment_date ? Carbon::parse($target->payment_date)->toDateString() : null;
                $targetOldTime = $target->transaction_time
                    ? substr((string) $target->transaction_time, 0, 5)
                    : optional($target->created_at)->format('H:i');
                DB::table('transaction_date_edits')->insert([
                    'transaction_id' => $target->id,
                    'old_date' => $targetOldDate,
                    'old_time' => $targetOldTime ? $targetOldTime.':00' : null,
                    'new_date' => $validated['date'],
                    'new_time' => $validated['time'].':00',
                    'note' => $validated['note'],
                    'edited_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $target->update([
                    'payment_date' => $validated['date'],
                    'transaction_time' => $validated['time'].':00',
                ]);
            }

            if ($discounts->isNotEmpty()) {
                PartyDiscount::whereIn('id', $discounts->pluck('id'))->update(['discount_date' => $validated['date']]);
            }

            // When this row is backed by a bank journal, keep both ledgers on
            // the same posting timestamp. The business document's own issue or
            // service date is intentionally not changed.
            foreach ($linkedJournalIds as $linkedJournalId) {
                $journal = JournalEntry::find($linkedJournalId);
                if ($journal) {
                    $journalDate = optional($journal->date)->format('Y-m-d');
                    $journalTime = $journal->entry_time ? substr((string) $journal->entry_time, 0, 5) : null;
                    if ($journalDate !== $validated['date']) {
                        DB::table('journal_entry_date_edits')->insert([
                            'journal_entry_id' => $journal->id,
                            'old_date' => $journalDate,
                            'new_date' => $validated['date'],
                            'reason' => $validated['note'],
                            'edited_by' => Auth::id(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    if ($journalTime !== $validated['time']) {
                        DB::table('journal_entry_detail_edits')->insert([
                            'journal_entry_id' => $journal->id,
                            'old_data' => json_encode(['entry_time' => $journalTime]),
                            'new_data' => json_encode(['entry_time' => $validated['time']]),
                            'reason' => $validated['note'],
                            'edited_by' => Auth::id(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $journal->update([
                        'date' => $validated['date'],
                        'entry_time' => $validated['time'].':00',
                    ]);
                }
            }
        });

        return response()->json(['success' => true, 'message' => $isDiscount
            ? 'Discount date and time updated across the party ledger, journal and discount record.'
            : 'Party ledger date and time updated.']);
    }

    /**
     * The owners a capital movement can name — twin of the list the Capital &
     * Financing desk enforces (FinancingController::storeCapital). Change both.
     */
    public const CAPITAL_OWNERS = ['Md Mohsin', 'Md Parvez Hossain', 'Nayeemur Rahman', 'Mohammad Fahim'];

    /**
     * Deposit / withdraw from the Company Ledger (owner, 2026-09-28). Travels
     * money only, from one Travels bank the person picks.
     *
     *   owner purposes (investment, profit withdrawal) → a FinancingCapital row
     *     posted by FinancingPostingService::postCapital() — the Capital &
     *     Financing desk's own code, so Accounts and Manage Bank read it as
     *     that desk's entry (Dr bank / Cr 3110, or Dr 3210 / Cr bank)
     *   vendor charge / expense → Dr 7400 / Cr bank, source company_ledger_cash
     *
     * Manage Bank is not touched: it reads the journal like it always has.
     */
    public function companyFunds(Request $request)
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(array_keys(CompanyFunds::PURPOSES))],
            'purpose' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'bank_id' => ['required', 'exists:banks,id'],
            'date' => ['required', 'date'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $purpose = CompanyFunds::PURPOSES[$data['direction']][$data['purpose']] ?? null;
        if (! $purpose) {
            return response()->json(['success' => false, 'message' => 'Choose what the money is for.'], 422);
        }
        $bank = Bank::find($data['bank_id']);
        if ((int) $bank->company_id !== CompanyFunds::COMPANY_ID) {
            return response()->json(['success' => false, 'message' => 'Choose a Travels bank or cash account.'], 422);
        }
        if (! $bank->account_id) {
            return response()->json(['success' => false, 'message' => "Bank '{$bank->name}' is not linked to a chart-of-accounts account."], 422);
        }

        try {
            if (! empty($purpose['capital'])) {
                $owner = User::find($data['owner_user_id'] ?? 0);
                if (! $owner || ! in_array($owner->name, self::CAPITAL_OWNERS, true)) {
                    return response()->json(['success' => false, 'message' => 'Choose whose money it is — '.implode(', ', self::CAPITAL_OWNERS).'.'], 422);
                }
                DB::transaction(function () use ($data, $purpose, $owner) {
                    $capital = FinancingCapital::create([
                        'company_id' => CompanyFunds::COMPANY_ID,
                        'created_by' => Auth::id(),
                        'kind' => $purpose['capital'],
                        'person_name' => $owner->name,
                        'party_type' => 'user',
                        'party_id' => $owner->id,
                        'reason' => $purpose['reason'],
                        'amount' => $data['amount'],
                        'date' => $data['date'],
                        'transaction_time' => now()->format('H:i'),
                        'method' => 'bank',
                        'bank_id' => $data['bank_id'],
                        'reference' => $data['reference'] ?? null,
                        'notes' => trim('From the Company Ledger. '.($data['notes'] ?? '')),
                    ]);
                    app(FinancingPostingService::class)->postCapital($capital);
                });
            } else {
                $expense = Account::where('code', config('accounts.'.$purpose['account']))->value('id');
                if (! $expense) {
                    return response()->json(['success' => false, 'message' => 'The account for this purpose is missing from the chart.'], 422);
                }
                DB::transaction(function () use ($data, $purpose, $bank, $expense) {
                    $amount = round((float) $data['amount'], 2);
                    Bank::assertCanCover((int) $bank->account_id, $amount, $bank->name);
                    $ref = ($data['reference'] ?? null) ?: 'CLC-'.date('Y').'-'.str_pad((string) (JournalEntry::where('source', CompanyFunds::SOURCE)->count() + 1), 4, '0', STR_PAD_LEFT);
                    $entry = JournalEntry::create([
                        'company_id' => CompanyFunds::COMPANY_ID,
                        'created_by' => Auth::id(),
                        'date' => $data['date'],
                        'entry_time' => now()->format('H:i:s'),
                        'reference' => $ref,
                        'source' => CompanyFunds::SOURCE,
                        'source_id' => 0,
                        'description' => $purpose['label'].(! empty($data['notes']) ? ' — '.$data['notes'] : ''),
                    ]);
                    JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $expense, 'debit' => $amount, 'credit' => 0, 'note' => $purpose['label']]);
                    JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $bank->account_id, 'debit' => 0, 'credit' => $amount, 'note' => $purpose['label'].' — paid from '.$bank->name]);
                });
            }
        } catch (\RuntimeException $e) {
            // The bank would be overdrawn, or an account is missing: refuse
            // with the reason, nothing written (the transaction rolled back).
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => $purpose['label'].' of ৳'.number_format((float) $data['amount'], 2).' recorded.']);
    }

    /**
     * Money a vendor pays BACK to us — an airline refund on a cancelled
     * ticket (2026-09-28). The cancel left the refund on the vendor as an
     * Advance (a credit balance); this is the cash arriving:
     *
     *   vendor ledger  Dr amount (type vendor_refund) — the Advance comes down
     *   journal        Dr Bank / Cr Accounts Payable (vendor)
     *   bank           balance + amount
     *
     * The mirror of recordPayment() for a vendor; nothing earlier is touched.
     */
    public function recordVendorRefund(Request $request)
    {
        $request->validate([
            'party_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,mobile_banking,online',
            'bank_id' => 'required|exists:banks,id',
            'payment_date' => 'required|date|before_or_equal:today',
            'transaction_time' => 'required|date_format:H:i',
            'payer_bank' => 'required_if:payment_method,bank_transfer,cheque,mobile_banking|nullable|string|max:150',
            'payer_reference' => 'required_if:payment_method,bank_transfer,cheque,mobile_banking|nullable|string|max:100',
        ]);

        $party = User::find($request->party_id);
        if (! $party || $this->typeOf($party) !== 'vendor') {
            return response()->json(['success' => false, 'message' => 'A refund can only be received from a vendor.']);
        }
        $bank = Bank::find($request->bank_id);
        if ($bank && ! in_array($bank->type, $this->partyPaymentAccountTypes($request->payment_method), true)) {
            return response()->json(['success' => false, 'message' => 'The selected company account does not match the payment method.'], 422);
        }
        if (! $bank?->account_id) {
            return response()->json([
                'success' => false,
                'message' => $bank ? "Bank '{$bank->name}' is not linked to a chart-of-accounts account." : 'Bank not found.',
            ]);
        }
        $payable = Account::where('code', config('accounts.accounts_payable'))->first();
        if (! $payable) {
            return response()->json(['success' => false, 'message' => 'The Accounts Payable account is missing from the chart.']);
        }

        DB::transaction(function () use ($request, $bank, $party, $payable) {
            $amount = (float) $request->amount;
            $lastTx = Transaction::where('user_id', $party->id)->orderByDesc('id')->lockForUpdate()->first();
            $oldBalance = $lastTx ? (float) $lastTx->balance : 0;
            $refNo = 'VRF-'.date('Y').'-'.str_pad(
                (Transaction::whereYear('created_at', date('Y'))->where('type', 'vendor_refund')->count() + 1),
                4, '0', STR_PAD_LEFT
            );

            Transaction::create([
                'user_id' => $party->id,
                'user_type' => $this->resolveUserType($party),
                'type' => 'vendor_refund',
                'account_id' => $bank->id,
                'payment_date' => $request->payment_date,
                'reference_no' => $refNo,
                'payment_method' => $request->payment_method,
                'old_balance' => $oldBalance,
                'debit' => $amount,
                'credit' => 0,
                'balance' => $oldBalance + $amount,
                'remarks' => $request->notes ?: ('Refund received from vendor — '.$request->payment_method),
            ]);

            $journal = JournalEntry::create([
                'company_id' => $this->companyFilter($request) ?? Auth::user()->company_id ?? 2,
                'created_by' => Auth::id(),
                'date' => $request->payment_date,
                'reference' => $refNo,
                'source' => 'vendor_refund',
                'source_id' => $party->id,
                'beneficiary' => $party->name,
                'beneficiary_party_id' => $party->id,
                'entry_time' => $request->transaction_time.':00',
                'beneficiary_method' => match ($request->payment_method) {
                    'cash' => 'cash', 'cheque' => 'cheque',
                    'bank_transfer', 'mobile_banking', 'online' => 'bank',
                    default => null,
                },
                'beneficiary_bank' => $request->payer_bank,
                'beneficiary_ref' => $request->payer_reference,
                'description' => 'Refund received from vendor — '.$refNo,
            ]);
            JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $bank->account_id,
                'debit' => $amount, 'credit' => 0, 'note' => 'Vendor refund received']);
            JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $payable->id,
                'party_type' => $this->resolvePartyType($party), 'party_id' => $party->id,
                'debit' => 0, 'credit' => $amount, 'note' => 'Vendor refund received']);

            $bank->update([
                'balance' => $bank->balance + $amount,
                'last_transaction_date' => $request->payment_date,
                'last_transaction_amount' => $amount,
                'last_transaction_type' => 'credit',
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Refund recorded — the vendor\'s advance is reduced and the bank credited.']);
    }

    public function recordPayment(Request $request)
    {
        $request->validate([
            'party_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,mobile_banking,online',
            'bank_id' => 'required|exists:banks,id',
            'payment_date' => 'required|date|before_or_equal:today',
            'transaction_time' => 'required|date_format:H:i',
            'payer_bank' => 'required_if:payment_method,bank_transfer,cheque,mobile_banking|nullable|string|max:150',
            'payer_reference' => 'required_if:payment_method,bank_transfer,cheque,mobile_banking|nullable|string|max:100',
            'allocation_mode' => 'nullable|in:unallocated,auto,manual',
            'invoice_allocations' => 'nullable|array|max:100',
            'invoice_allocations.*.key' => 'required|string|max:100',
            'invoice_allocations.*.amount' => 'required|numeric|min:0',
            'transaction_kind' => 'nullable|in:payment,advance_refund,service_refund',
        ]);

        $bank = Bank::find($request->bank_id);
        if ($bank && ! in_array($bank->type, $this->partyPaymentAccountTypes($request->payment_method), true)) {
            return response()->json(['success' => false, 'message' => 'The selected company account does not match the payment method.'], 422);
        }
        if (! $bank?->account_id) {
            return response()->json([
                'success' => false,
                'message' => $bank ? "Bank '{$bank->name}' is not linked to a chart-of-accounts account." : 'Bank not found.',
            ]);
        }

        $transactionKind = $request->input('transaction_kind', 'payment');
        $party = User::findOrFail($request->party_id);

        if (in_array($transactionKind, ['advance_refund', 'service_refund'], true) && $this->typeOf($party) === 'vendor') {
            return response()->json([
                'success' => false,
                'message' => 'Vendor money-back uses Receive Refund. Return Advance is only for an agent or customer advance held by the company.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($request, $bank, $transactionKind, $party) {
                $partyId = $request->party_id;
                $amount = (float) $request->amount;

                $lastTx = Transaction::where('user_id', $partyId)->orderByDesc('id')->lockForUpdate()->first();
                // Historical rows do not all carry a reliable cached `balance`.
                // The statement itself is authoritative and calculates debit minus
                // credit, so new running balances and refund limits must use the
                // exact same rule instead of trusting the latest cached value.
                $oldBalance = round((float) Transaction::where('user_id', $partyId)
                    ->selectRaw('COALESCE(SUM(debit - credit), 0) AS ledger_balance')
                    ->value('ledger_balance'), 2);

                // Return money that the company is genuinely holding as an agent/
                // customer advance. This is deliberately NOT a supplier advance:
                // the party statement needs a debit to bring its credit balance
                // back towards zero, while the customer-advance liability and the
                // selected bank/cash account both reduce in the journal.
                if (in_array($transactionKind, ['advance_refund', 'service_refund'], true)) {
                    $advanceHeld = max(0, round(-$oldBalance, 2));
                    if ($transactionKind === 'advance_refund' && ($advanceHeld <= 0 || round($amount, 2) > $advanceHeld)) {
                        throw new \DomainException('Return Advance cannot exceed the available party credit of ৳'.number_format($advanceHeld, 2).'. Use Service Refund for a service-related payout.');
                    }

                    $bank = Bank::query()->lockForUpdate()->findOrFail($bank->id);
                    Bank::assertCanCover((int) $bank->account_id, $amount, $bank->name);

                    $isServiceRefund = $transactionKind === 'service_refund';
                    $refundAccountCode = $isServiceRefund
                        ? config('accounts.accounts_receivable')
                        : config('accounts.customer_advance');
                    $refundAccount = Account::where('code', $refundAccountCode)->first();
                    if (! $refundAccount) {
                        throw new \DomainException('The configured refund control account is missing. Run php artisan accounts:check.');
                    }

                    $partyRowType = $isServiceRefund ? 'customer_service_refund' : 'customer_advance_refund';
                    $refPrefix = $isServiceRefund ? 'SRF-' : 'ARF-';
                    $refNo = $refPrefix.date('Y').'-'.str_pad(
                        (Transaction::whereYear('created_at', date('Y'))->where('type', $partyRowType)->count() + 1),
                        4, '0', STR_PAD_LEFT
                    );
                    $actionLabel = $isServiceRefund ? 'Customer service refund paid' : 'Agent/customer advance returned';
                    $note = trim((string) ($request->notes ?: $actionLabel));
                    $journal = JournalEntry::create([
                        'company_id' => Auth::user()->company_id ?? 2,
                        'created_by' => Auth::id(),
                        'date' => $request->payment_date,
                        'reference' => $refNo,
                        // Bank transaction keeps the standard bank correction and
                        // reversal workspace available, including party-ledger undo.
                        'source' => 'bank_transaction',
                        'source_id' => $bank->id,
                        'beneficiary' => $party->name,
                        'beneficiary_party_id' => $partyId,
                        'entry_time' => $request->transaction_time.':00',
                        'beneficiary_method' => match ($request->payment_method) {
                            'cash' => 'cash', 'cheque' => 'cheque',
                            'bank_transfer', 'mobile_banking', 'online' => 'bank',
                            default => null,
                        },
                        'beneficiary_bank' => $request->payer_bank,
                        'beneficiary_ref' => $request->payer_reference,
                        'description' => ($isServiceRefund ? 'Service refund paid to ' : 'Advance returned to ').$party->name.' — '.$note,
                    ]);

                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $refundAccount->id,
                        'party_type' => $this->resolvePartyType($party),
                        'party_id' => $partyId,
                        'debit' => $amount,
                        'credit' => 0,
                        'note' => $actionLabel,
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $bank->account_id,
                        'debit' => 0,
                        'credit' => $amount,
                        'note' => ($isServiceRefund ? 'Service refund' : 'Advance refund').' paid from '.$bank->name,
                    ]);

                    Transaction::create([
                        'user_id' => $partyId,
                        'user_type' => $this->resolveUserType($party),
                        'type' => $partyRowType,
                        'account_id' => $bank->id,
                        'payment_date' => $request->payment_date,
                        'reference_no' => $refNo,
                        'payment_method' => $request->payment_method,
                        'invoice_id' => $journal->id,
                        'old_balance' => $oldBalance,
                        'debit' => $amount,
                        'credit' => 0,
                        'balance' => $oldBalance + $amount,
                        'remarks' => $note,
                    ]);

                    $bank->update([
                        'balance' => $bank->balance - $amount,
                        'last_transaction_date' => $request->payment_date,
                        'last_transaction_amount' => $amount,
                        'last_transaction_type' => 'debit',
                    ]);

                    return;
                }

                $newBalance = $oldBalance - $amount;

                $refNo = 'RCT-'.date('Y').'-'.str_pad(
                    (Transaction::whereYear('created_at', date('Y'))->where('type', 'party_payment')->count() + 1),
                    4, '0', STR_PAD_LEFT
                );

                $userType = $this->resolveUserType($party);
                $allocationMode = $request->input('allocation_mode', 'unallocated');
                $allocations = app(PartyInvoiceAllocation::class)->allocateMany(
                    (int) $partyId, $amount, $allocationMode, $request->input('invoice_allocations', []),
                    $userType === 'customer' ? 'sale' : 'payable', (int) $bank->id, $request->payment_method,
                    $request->payment_date, $refNo
                );
                $allocationNote = $allocations ? collect($allocations)->map(fn ($a) => $a['service'].' '.$a['number'])->implode(', ') : null;

                $partyTransactionType = $allocationMode === 'unallocated'
                    ? ($userType === 'customer' ? 'customer_advance_received' : 'supplier_advance_paid')
                    : 'party_payment';

                // A customer/agent payment is cash received (clears our
                // receivable); a vendor payment is cash paid out (clears our
                // payable). resolveUserType() deliberately puts agents on the
                // customer side because an agent owes us for passenger charges.
                if ($bank) {
                    $isReceivable = $userType === 'customer';
                    $isAdvance = $allocationMode === 'unallocated';
                    $contraCode = $isAdvance
                        ? ($isReceivable ? config('accounts.customer_advance') : config('accounts.supplier_advance'))
                        : ($isReceivable ? config('accounts.accounts_receivable') : config('accounts.accounts_payable'));
                    $contraAccount = Account::where('code', $contraCode)->firstOrFail();

                    if (! $isReceivable) {
                        Bank::assertCanCover((int) $bank->account_id, $amount, $bank->name);
                    }

                    $journal = JournalEntry::create([
                        'company_id' => Auth::user()->company_id ?? 2,
                        'created_by' => Auth::id(),
                        'date' => $request->payment_date,
                        'reference' => $refNo,
                        'source' => $isAdvance ? 'bank_transaction' : 'party_payment',
                        'source_id' => $isAdvance ? $bank->id : $partyId,
                        'beneficiary' => $party?->name,
                        'beneficiary_party_id' => $partyId,
                        'entry_time' => $request->transaction_time.':00',
                        'beneficiary_method' => match ($request->payment_method) {
                            'cash' => 'cash', 'cheque' => 'cheque',
                            'bank_transfer', 'mobile_banking', 'online' => 'bank',
                            default => null,
                        },
                        'beneficiary_bank' => $request->payer_bank,
                        'beneficiary_ref' => $request->payer_reference,
                        'description' => 'Party statement payment — '.$refNo
                            .($allocationNote ? ' — '.$allocationNote : ' — Unallocated'),
                    ]);

                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $bank->account_id,
                        'debit' => $isReceivable ? $amount : 0,
                        'credit' => $isReceivable ? 0 : $amount,
                        'note' => $isAdvance ? 'Party advance payment' : 'Party statement payment',
                    ]);

                    if (! $allocations) {
                        Transaction::create([
                            'user_id' => $partyId,
                            'user_type' => $userType,
                            'type' => $partyTransactionType,
                            'account_id' => $request->bank_id ?: null,
                            'payment_date' => $request->payment_date,
                            'reference_no' => $refNo,
                            'payment_method' => $request->payment_method,
                            'invoice_id' => $journal->id,
                            'old_balance' => $oldBalance,
                            'debit' => 0,
                            'credit' => $amount,
                            'balance' => $newBalance,
                            'remarks' => trim(($request->notes ?: 'Payment received — '.$request->payment_method)
                                .($allocationNote ? ' — Allocated to '.$allocationNote : ' — Unallocated')),
                        ]);
                    }

                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $contraAccount->id,
                        'party_type' => $this->resolvePartyType($party),
                        'party_id' => $partyId,
                        'debit' => $isReceivable ? 0 : $amount,
                        'credit' => $isReceivable ? $amount : 0,
                        'note' => $isAdvance ? 'Party advance payment' : 'Party statement payment',
                    ]);

                    $bank->update([
                        'balance' => $bank->balance + ($isReceivable ? $amount : -$amount),
                        'last_transaction_date' => $request->payment_date,
                        'last_transaction_amount' => $amount,
                        'last_transaction_type' => $isReceivable ? 'credit' : 'debit',
                    ]);

                    // This receipt/payment is the same money a scheduled "receive"
                    // (agent/customer) or "pay" (vendor) row is waiting on — close
                    // whatever's open for this party, oldest first, so Payment
                    // Schedule's dashboard totals don't keep counting it as still
                    // due just because it wasn't settled through its own Mark Paid
                    // button. See PaymentScheduleReconciler.
                    if (! $allocations && ! $isAdvance) {
                        app(PaymentScheduleReconciler::class)->settleForParty(
                            $partyId,
                            $isReceivable ? 'receive' : 'pay',
                            $amount,
                            $request->payment_date,
                            $request->notes ?: null
                        );
                    }
                }
            });
        } catch (\DomainException|BankOverdrawnException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // A receipt from an agent settles their contract-flight passengers
        // oldest first, so Flights & Passengers and Flight Accounts read the
        // same "received" the statement does. No-op for any other party.
        if ($transactionKind === 'payment' && $request->input('allocation_mode', 'unallocated') !== 'unallocated') {
            ContractFlightAgentReceipts::sync((int) $request->party_id);
        }

        return response()->json([
            'success' => true,
            'message' => match ($transactionKind) {
                'advance_refund' => 'Advance returned successfully. Bank/cash, journal and party ledger were updated together.',
                'service_refund' => 'Service refund paid successfully. Bank/cash, journal and party ledger were updated together.',
                default => 'Payment recorded successfully.',
            },
        ]);
    }

    private function partyPaymentAccountTypes(string $method): array
    {
        return match ($method) {
            'cash' => ['cash'],
            'mobile_banking' => ['mobile_banking', 'digital_wallet'],
            'bank_transfer', 'cheque', 'online' => ['bank'],
            default => [],
        };
    }

    public function unpaidInvoices(Request $request)
    {
        $data = $request->validate(['party_id' => 'required|integer|exists:users,id']);

        return response()->json([
            'invoices' => app(PartyInvoiceAllocation::class)->unpaidForParty((int) $data['party_id']),
        ]);
    }

    // ── Send to the party ───────────────────────────────────────────────────
    // The Email / Reminder buttons under the statement. Both go through
    // PartyNotifier, the same path the automatic after-posting notice takes,
    // and both answer with what actually happened on each channel rather
    // than a bare "sent" — SMTP and the SMS gateway fail independently.

    /** Email the statement for the range shown (whole ledger when no dates). */
    public function sendStatement(Request $request, PartyNotifier $notifier)
    {
        $request->validate([
            'party_id' => 'required|exists:users,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $party = User::findOrFail($request->party_id);
        if (! $party->email) {
            return response()->json(['success' => false, 'message' => "{$party->name} has no email address on file."]);
        }

        try {
            $log = $notifier->sendStatement($party, $request->from ?: null, $request->to ?: null, Auth::id());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json($this->sendOutcome($log));
    }

    /** Send the loaded statement summary by SMS only. */
    public function sendReminder(Request $request, PartyNotifier $notifier)
    {
        $request->validate([
            'party_id' => 'required|exists:users,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $party = User::findOrFail($request->party_id);
        if (! $party->phone) {
            return response()->json(['success' => false, 'message' => "{$party->name} has no phone number on file."]);
        }

        try {
            $from = $request->from ?: null;
            $to = $request->to ?: null;
            $log = $notifier->sendStatementSms($party, $from, $to, Auth::id());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json($this->sendOutcome($log));
    }

    /**
     * The statement as PDF behind a short link — /s/kX9pQ2Ab — what the
     * WhatsApp message carries, since wa.me cannot attach a file. Public
     * route (no login: the party is not a user of the ERP); the random token
     * in party_statement_links is the whole access control, and the row says
     * which party and which range the link stands for. Rendered fresh on
     * every open, so the figures are the ledger as it is today. Links expire
     * after 30 days, matching the salary-payslip sharing timeline.
     */
    public function shortPdf(string $token, PartyNotifier $notifier)
    {
        $link = PartyStatementLink::where('token', $token)->first();
        $party = $link ? User::find($link->user_id) : null;
        abort_if(! $link || ! $party, 404);
        abort_if(! $link->expires_at || $link->expires_at->isPast(), 410, 'This statement link has expired. Please ask for a new one.');

        $from = $link->date_from?->format('Y-m-d');
        $to = $link->date_to?->format('Y-m-d');

        $pdf = $notifier->statementPdfFor($party, $from, $to);
        abort_if($pdf === null, 500, 'The statement could not be rendered.');

        $link->increment('opens', 1, ['last_opened_at' => now()]);

        $type = $notifier->typeOf($party);
        $name = 'Statement-'.($type ? self::partyCode($party->id, $type) : $party->id).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * The short link for this party and range (issued once, then reused).
     * Null — and the WhatsApp text falls back to "statement emailed" — if the
     * route is not registered or the table is not there yet, so a lagging
     * deploy costs the link, never the page.
     */
    private function sharedPdfUrl(int $partyId, ?string $from, ?string $to): ?string
    {
        if (! Route::has('party-statement.short-pdf')) {
            return null;
        }
        try {
            $link = PartyStatementLink::issue($partyId, $from, $to, Auth::id());

            return route('party-statement.short-pdf', ['token' => $link->token]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** One line per channel — "Email sent to x@y · SMS failed: …" — for the toast. */
    private function sendOutcome(PartyLedgerNotification $log): array
    {
        $lines = [];
        if ($log->email_status === 'sent') {
            $lines[] = "Email sent to {$log->email_to}";
        } elseif ($log->email_status === 'failed') {
            $lines[] = 'Email failed: '.Str::limit((string) $log->email_error, 120);
        } elseif ($log->email_status === 'skipped') {
            $lines[] = 'Email skipped — no valid address on file';
        }

        if ($log->sms_status === 'sent') {
            $lines[] = "SMS sent to {$log->sms_to}";
        } elseif ($log->sms_status === 'failed') {
            $lines[] = 'SMS failed: '.Str::limit((string) $log->sms_error, 120);
        } elseif ($log->sms_status === 'skipped') {
            $lines[] = 'SMS skipped — no phone number on file';
        }

        $anySent = $log->email_status === 'sent' || $log->sms_status === 'sent';

        return ['success' => $anySent, 'message' => implode("\n", $lines), 'log_id' => $log->id];
    }

    public function createInvoice(Request $request, PartyInvoicePostingService $posting)
    {
        $data = $request->validate([
            'party_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'service_description' => 'required|string',
            'due_date' => 'nullable|date',
            'invoice_time' => 'nullable|date_format:H:i',
            'revenue_account_code' => 'nullable|string|exists:accounts,code',
        ]);
        $party = User::findOrFail($data['party_id']);
        $directoryType = $party->hasRole('agent') ? 'agent' : ($party->hasRole('customer') ? 'client' : null);
        if (! $directoryType) {
            return response()->json([
                'success' => false,
                'message' => 'Only an agent or customer can receive a custom invoice.',
            ], 422);
        }

        $companyId = $this->companyFilter($request) ?? Auth::user()?->company_id;
        if ($request->filled('company') && ! $companyId) {
            return response()->json([
                'success' => false,
                'message' => 'The selected company is unavailable. Refresh the page and select a valid company.',
            ], 422);
        }

        if (! $companyId) {
            $companyIds = DB::table('company_party')
                ->where('user_id', $party->id)
                ->where('party_type', $directoryType)
                ->pluck('company_id')
                ->unique()
                ->values();

            if ($companyIds->count() === 1) {
                $companyId = (int) $companyIds->first();
            } elseif ($companyIds->count() > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'This party belongs to multiple companies. Select a company before creating the invoice.',
                ], 422);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'This party is not assigned to a company. Assign the party to a company before creating the invoice.',
                ], 422);
            }
        }

        $company = Company::find($companyId);
        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'The invoice company no longer exists. Select another company or repair the party assignment.',
            ], 422);
        }

        abort_unless($directoryType && CompanyPartyDirectory::belongs($party->id, $company->id, $directoryType), 422, 'Only an agent/customer belonging to this company can receive a custom invoice.');
        $invoice = $posting->post($company, $party, [
            'invoice_date' => now()->toDateString(),
            'invoice_time' => $data['invoice_time'] ?? now()->format('H:i'),
            'due_date' => $data['due_date'] ?? null,
            'notes' => $request->notes,
            'items' => [[
                'service_type' => 'custom', 'description' => $data['service_description'],
                'passenger_name' => null, 'quantity' => 1, 'unit_price' => $data['amount'],
                'revenue_account_code' => $data['revenue_account_code'] ?? config('accounts.service_charge_income'),
            ]],
        ], $request->user());

        // The new ledger row, so the Invoice window can tick it and show its invoice.
        return response()->json([
            'success' => true,
            'message' => 'Invoice created successfully.',
            'invoice_number' => $invoice->invoice_number,
            'transaction_id' => Transaction::where('type', 'party_invoice')->where('invoice_id', $invoice->id)->value('id'),
        ]);
    }

    /**
     * Seed a party's ledger with a single starting balance so historical activity
     * doesn't need to be re-entered transaction by transaction. Every later
     * Record Payment / Invoice / sale already chains off this party's last
     * transaction, so once this is set everything else nets against it automatically.
     */
    public function setOpeningBalance(Request $request)
    {
        $request->validate([
            'party_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'direction' => 'required|in:receivable,payable',
            'as_of_date' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $partyId = $request->party_id;
        $companyId = $this->companyFilter($request) ?? Auth::user()?->company_id ?? 2;

        if (Transaction::where('user_id', $partyId)->where('company_id', $companyId)->where('type', 'opening_balance')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'An opening balance has already been set for this party. Remove it first if you need to change it.',
            ]);
        }

        DB::transaction(function () use ($request, $partyId, $companyId) {
            $amount = (float) $request->amount;
            $isReceivable = $request->direction === 'receivable';
            $signedAmount = $isReceivable ? $amount : -$amount;

            $party = User::find($partyId);
            $userType = $this->resolveUserType($party);
            $reference = 'OB-'.now()->format('YmdHis');

            Transaction::create([
                'company_id' => $companyId,
                'user_id' => $partyId,
                'user_type' => $userType,
                'type' => 'opening_balance',
                'account_id' => null,
                'payment_date' => $request->as_of_date,
                'reference_no' => $reference,
                'payment_method' => null,
                'old_balance' => 0,
                'debit' => $isReceivable ? $amount : 0,
                'credit' => $isReceivable ? 0 : $amount,
                'balance' => $signedAmount,
                'remarks' => $request->note ?: 'Opening balance',
            ]);

            // Opening balances must enter the control account as well as the
            // party sub-ledger. Otherwise every later receipt/sale is correct
            // on its own but the party's statement can never reconcile to GL.
            $controlAccount = Account::where('code', $isReceivable
                ? config('accounts.accounts_receivable')
                : config('accounts.accounts_payable'))->firstOrFail();
            $openingEquity = Account::where('code', config('accounts.opening_balance_equity'))->firstOrFail();

            $journal = JournalEntry::create([
                'company_id' => $companyId,
                'created_by' => Auth::id(),
                'date' => $request->as_of_date,
                'reference' => $reference,
                'source' => 'opening_balance',
                'source_id' => $partyId,
                'beneficiary' => $party?->name,
                'beneficiary_party_id' => $partyId,
                'description' => 'Party opening balance — '.$reference,
            ]);

            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $isReceivable ? $controlAccount->id : $openingEquity->id,
                'party_type' => $isReceivable ? $this->resolvePartyType($party) : null,
                'party_id' => $isReceivable ? $partyId : null,
                'debit' => $amount,
                'credit' => 0,
                'note' => 'Party opening balance',
            ]);

            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $isReceivable ? $openingEquity->id : $controlAccount->id,
                'party_type' => $isReceivable ? null : $this->resolvePartyType($party),
                'party_id' => $isReceivable ? null : $partyId,
                'debit' => 0,
                'credit' => $amount,
                'note' => 'Party opening balance',
            ]);

            // Every transaction already on this party's ledger was computed
            // assuming a zero starting balance — shift them all by the same
            // amount now that we know the true starting point.
            Transaction::where('user_id', $partyId)
                ->where('company_id', $companyId)
                ->where('type', '!=', 'opening_balance')
                ->increment('old_balance', $signedAmount);
            Transaction::where('user_id', $partyId)
                ->where('company_id', $companyId)
                ->where('type', '!=', 'opening_balance')
                ->increment('balance', $signedAmount);
        });

        return response()->json(['success' => true, 'message' => 'Opening balance set successfully.']);
    }

    public function exportExcel(Request $request)
    {
        $request->validate(['party_id' => 'required|exists:users,id']);

        $partyId = $request->party_id;
        $party = User::findOrFail($partyId);

        $companyId = $this->companyFilter($request);
        [$transactions, $openingBalance] = $this->fetchTransactions($partyId, $request->from, $request->to, $request->type, $companyId, $this->projectFilter($request, $companyId));

        $totalDebit = (float) $transactions->sum('debit');
        $totalCredit = (float) $transactions->sum('credit');
        $closingBalance = $transactions->isNotEmpty() ? (float) $transactions->last()->balance : $openingBalance;

        $rows = $transactions->map(fn ($t) => [
            'payment_date' => $t->payment_date,
            'reference_no' => $t->reference_no,
            'remarks' => $t->display_remarks,
            'type' => $t->type,
            'debit' => (float) $t->debit,
            'credit' => (float) $t->credit,
            'balance' => (float) $t->balance,
        ])->toArray();

        $filename = 'statement-'.str($party->name)->slug().'-'.date('Ymd').'.xlsx';

        return Excel::download(
            new PartyStatementExport($rows, $party->name, $openingBalance, $totalDebit, $totalCredit, $closingBalance),
            $filename
        );
    }

    /**
     * The sales invoice (owner, 2026-10-14): the party's SALES — not the
     * ledger — as one PDF to give the agent. Picked like Print: ticked
     * entries (they win over dates), a date range, or what the table shows.
     * Payments and advances never appear; each line is numbered by its own
     * reference. The same sheet the after-posting email attaches
     * (App\Services\SalesInvoice).
     */
    public function salesInvoice(Request $request)
    {
        $request->validate(['party_id' => 'required|exists:users,id']);

        $party = User::findOrFail($request->party_id);
        $companyId = $this->companyFilter($request);
        $company = ($companyId ? Company::find($companyId) : null) ?? Company::find(2) ?? Company::first();

        $ids = $this->idList($request->input('entries'));
        [$rows] = $this->fetchTransactions($party->id, $ids ? null : $request->from, $ids ? null : $request->to, $request->type, $companyId, $this->projectFilter($request, $companyId));
        if ($ids) {
            $rows = $rows->whereIn('id', $ids)->values();
        }

        $data = SalesInvoice::data($party, $company, SalesInvoice::lines($rows));
        $pdf = SalesInvoice::pdf($data);
        abort_if($pdf === null, 500, 'The invoice could not be rendered.');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.SalesInvoice::fileName($data).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * The party's sales for the Invoice window's list (2026-10-14): every
     * line a sales invoice can carry in the period — payments and advances
     * never — in the words the PDF prints (SalesInvoice::summary).
     */
    public function salesInvoiceLines(Request $request)
    {
        $request->validate(['party_id' => 'required|exists:users,id', 'from' => 'nullable|date', 'to' => 'nullable|date']);

        $party = User::findOrFail($request->party_id);
        $companyId = $this->companyFilter($request);
        [$rows] = $this->fetchTransactions($party->id, $request->from, $request->to, null, $companyId, $this->projectFilter($request, $companyId));

        return response()->json([
            'lines' => SalesInvoice::lines($rows)->map(fn ($r) => SalesInvoice::summary($r))->values(),
        ]);
    }

    /**
     * The invoice's logo and signature (Invoice window → ⚙, owner 2026-10-14):
     * the company's own `logo` and `signature` columns, stored the way Company
     * Settings stores them (public/image/company/). Same permission as Company
     * Settings. The old file is kept on disk, so a wrong upload can be undone
     * from Company Settings; nothing else about the company changes.
     */
    public function invoiceBranding(Request $request)
    {
        $user = Auth::user();
        abort_unless($user && ($user->is_super_admin || $user->can('edit company setting')), 403, 'Only someone who can edit company settings can change the logo or the signature.');
        $request->validate([
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
            'signature' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
            'remove_signature' => 'nullable|boolean',
        ]);

        $company = Company::findOrFail($this->companyFilter($request) ?? $user->company_id ?? 2);
        $dir = 'image/company/';
        if (! is_dir(public_path($dir))) {
            mkdir(public_path($dir), 0755, true);
        }
        foreach (['logo' => '', 'signature' => 'signature_'] as $field => $prefix) {
            if (! $request->hasFile($field)) {
                continue;
            }
            $file = $request->file($field);
            $name = $prefix.uniqid().'.'.strtolower($file->getClientOriginalExtension());
            $file->move(public_path($dir), $name);
            $company->{$field} = $dir.$name;
        }
        if ($request->boolean('remove_signature') && ! $request->hasFile('signature')) {
            $company->signature = null;
        }
        $company->save();

        return response()->json([
            'success' => true,
            'message' => 'Saved — the next invoice uses it.',
            'logo' => $company->logo && is_file(public_path($company->logo)) ? asset($company->logo) : null,
            'signature' => $company->signature && is_file(public_path($company->signature)) ? asset($company->signature) : null,
        ]);
    }

    public function exportPdf(Request $request)
    {
        $request->validate(['party_id' => 'required|exists:users,id']);

        $partyId = $request->party_id;
        $party = User::findOrFail($partyId);
        $companyId = $this->companyFilter($request);
        $company = ($companyId ? Company::find($companyId) : null) ?? Company::find(2) ?? Company::first();

        $dateFrom = $request->from;
        $dateTo = $request->to;

        // Ticked rows (Print → "Selected transactions") win over the dates, as
        // on the bank statement: the sheet lists exactly those entries. The
        // balances were run over the whole ledger before the cut, so each
        // printed balance is still the true figure after that entry.
        $entryIds = $this->idList($request->input('entries'));
        if ($entryIds) {
            $dateFrom = $dateTo = null;
        }
        [$transactions, $openingBalance] = $this->fetchTransactions($partyId, $dateFrom, $dateTo, $request->type, $companyId, $this->projectFilter($request, $companyId));
        // Customer-facing print/PDF follows the shared statement policy:
        // omit complete cancelled pairs, retaining valid receipts and any
        // unmatched period-edge adjustment. Match before selecting rows so
        // ticking only one side cannot expose a cancelled transaction.
        $transactions = app(PartyLedgerService::class)->activeRows($transactions, (float) $openingBalance);
        $transactions = app(TicketRefundStatementPresenter::class)->group($transactions, (float) $openingBalance, foldSale: false);
        $selection = 0;
        if ($entryIds) {
            $transactions = $transactions->whereIn('id', $entryIds)->values();
            $selection = $transactions->count();
        }

        // Print designer → "Categories": only the ticked entry types. Like ticked
        // rows, the running balances are the whole ledger's, so each printed
        // balance is still the true figure after that entry.
        $categories = array_values(array_filter(
            explode(',', (string) $request->input('cats', '')),
            fn ($c) => preg_match('/^[a-z0-9_]+$/', $c)
        ));
        $catFiltered = false;
        if ($categories) {
            if (in_array('ticket_purchase_refund', $categories, true)) {
                $categories[] = 'ticket_refund_summary';
            }
            $kept = $transactions->whereIn('type', $categories)->values();
            $catFiltered = $kept->count() !== $transactions->count();
            $transactions = $kept;
        }

        $totalDebit = (float) $transactions->sum('debit');
        $totalCredit = (float) $transactions->sum('credit');
        $closingBalance = $transactions->isNotEmpty() ? (float) $transactions->last()->balance : $openingBalance;

        // Each ticket sale's tickets and each contract-flight charge's passenger,
        // loaded up front (StatementRows::related — the emailed statement uses
        // the same look-ups, so the two can never describe a row differently).
        [$flightChargePassengers, $ticketSaleItems] = StatementRows::related($transactions);

        // EP-AG-126, not the generic ID-0126 — the same code the ledger
        // screen, the bank dashboard's beneficiary and every other party
        // screen already print for this person. Falls back to ID-0126 only
        // for a user who isn't actually a party on any of the three ledgers
        // (shouldn't happen from this screen, but a statement must still
        // render rather than 500 if one is reached directly).
        $partyType = $this->typeOf($party);
        $partyCode = $partyType ? self::partyCode($party->id, $partyType) : 'ID-'.str_pad((string) $party->id, 4, '0', STR_PAD_LEFT);

        // The Note box always carries something — a template picked in the
        // Print chooser, free text typed over one, or (when the quick "PDF"
        // button skipped the chooser entirely) the 'general' template as the
        // default. Never stored; it only exists for this render.
        $note = trim((string) $request->input('note', ''));
        $note = $note !== '' ? Str::limit($note, 500) : self::NOTE_TEMPLATES['general'];

        return view('party-statement.print-statement', compact(
            'party', 'company', 'transactions',
            'openingBalance', 'totalDebit', 'totalCredit', 'closingBalance',
            'dateFrom', 'dateTo', 'ticketSaleItems', 'flightChargePassengers', 'selection',
            'note', 'partyCode', 'catFiltered'
        ) + ['sheet' => self::sheetSettings($request)]);
    }

    /** Print columns the designer can show, in sheet order, with their width weight. */
    public const PRINT_COLUMNS = [
        'sl' => ['#', 4, false], 'date' => ['Date', 11.5, true], 'entry' => ['Entry', 13.5, true],
        'particulars' => ['Particulars', 27, true], 'flight' => ['Flight / Bank', 13, true],
        'ref' => ['Ref / Voucher', 10, false], 'debit' => ['Debit (৳)', 11.5, true],
        'credit' => ['Credit (৳)', 11.5, true], 'balance' => ['Balance (৳)', 12, true],
    ];

    public const PRINT_SIGNATURES = [
        'prepared' => 'Prepared By', 'checked' => 'Checked By',
        'authorized' => 'Authorized Signatory', 'received' => 'Received By (Party)',
    ];

    /**
     * The Print designer's choices (party statement only). Every key is
     * optional: a bare ?party_id= link prints exactly the sheet it always did.
     */
    private static function sheetSettings(Request $request): array
    {
        $list = fn (string $key, array $allowed, array $default) => $request->filled($key)
            ? array_values(array_intersect(array_keys($allowed), explode(',', (string) $request->input($key))))
            : $default;
        $text = fn (string $key, int $max) => Str::limit(trim((string) $request->input($key, '')), $max, '');
        $flag = fn (string $key) => $request->input($key, '1') !== '0';

        $cols = $list('cols', self::PRINT_COLUMNS, array_keys(array_filter(self::PRINT_COLUMNS, fn ($c) => $c[2])));
        $stamps = ['paid' => 'PAID', 'received' => 'RECEIVED', 'due' => 'PAYMENT DUE', 'original' => 'ORIGINAL', 'copy' => 'COPY', 'verified' => 'VERIFIED'];
        $stamp = (string) $request->input('stamp', '');
        $stampText = $stamp === 'custom' ? mb_strtoupper($text('stamp_text', 24)) : ($stamps[$stamp] ?? '');
        $accent = (string) $request->input('accent', '');

        return [
            'title' => $text('title', 80),
            'cols' => $cols ?: ['date', 'particulars', 'balance'],
            'stamp' => $stampText,
            'sigs' => $list('sigs', self::PRINT_SIGNATURES, ['authorized']),
            'generator' => $request->has('gen') ? $text('gen', 60) : null,
            'summary' => $flag('summary'),
            'words' => $flag('words'),
            'logo' => $flag('logo'),
            'bank' => $flag('bank'),
            'watermark' => mb_strtoupper($text('watermark', 20)),
            'landscape' => $request->input('orient') === 'landscape',
            'accent' => preg_match('/^#[0-9a-f]{6}$/i', $accent) ? $accent : null,
        ];
    }

    public function invoicePdf(string $_role, PartyInvoice $partyInvoice)
    {
        $partyInvoice->load('user', 'country', 'createdBy');
        $company = Company::find(2) ?? Company::first();

        return view('party-statement.pdf-invoice', compact('partyInvoice', 'company'));
    }

    // ── Print sheets ────────────────────────────────────────────────────────
    // The dashboard feed and the party rosters print the way Manage Bank's
    // tables do: Print opens a chooser (today, a day, a range, the ticked
    // rows, or what the table shows) and the sheet is built here from the
    // whole ledger, so a day outside the feed's fourteen days still prints.
    // Ticked ids win over dates, as on the bank sheets.

    /** Latest entries across every party — ?date_from=&date_to=&items= */
    public function printFeed(string $_role, Request $request)
    {
        $companyId = $this->companyFilter($request);
        $typeOfUser = $this->typeOfUsers();
        $ids = array_keys($typeOfUser);

        $dateFrom = $request->input('date_from') ?: null;
        $dateTo = $request->input('date_to') ?: null;
        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }
        $itemIds = $this->idList($request->input('items'));

        $rows = $ids
            ? $this->feedQuery($ids, $companyId)
                ->when($itemIds, fn ($q) => $q->whereIn('transactions.id', $itemIds))
                ->when(! $itemIds && $dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $dateFrom))
                ->when(! $itemIds && $dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $dateTo))
                ->limit(2000)
                ->get()
            : collect();
        $rows = $this->feedRows($rows, $typeOfUser);

        return view('party-statement.print-feed', [
            'company' => ($companyId ? Company::find($companyId) : null) ?? Company::find(2) ?? Company::first(),
            'rows' => $rows,
            'totalDebit' => (float) $rows->sum('debit'),
            'totalCredit' => (float) $rows->sum('credit'),
            'dateFrom' => $itemIds ? null : $dateFrom,
            'dateTo' => $itemIds ? null : $dateTo,
            'selection' => $itemIds ? $rows->count() : 0,
            'counts' => $rows->countBy('type'),
        ]);
    }

    /** users.service_categories → the names the menus use */
    private const SERVICE_LABELS = [
        'ticket' => 'Ticket',
        'visa' => 'Visa',
        'file' => 'Contract File',
        'flight' => 'Contract Flight',
        'others' => 'Others',
    ];

    /** The Type filter on the party tables: [label, icon, colour] per kind */
    public const TYPE_FILTER = [
        'ticket' => ['Ticket', 'fa-ticket', '#2563eb'],
        'visa' => ['Visa', 'fa-passport', '#16a34a'],
        'file' => ['Contract File', 'fa-folder-open', '#d97706'],
        'flight' => ['Contract Flight', 'fa-plane', '#7c3aed'],
        'others' => ['Others', 'fa-ellipsis', '#64748b'],
        'multiple' => ['Multiple', 'fa-layer-group', '#db2777'],
        'none' => ['Not set', 'fa-circle-question', '#94a3b8'],
    ];

    /** Same rule as the table's Type dropdown: a kind, Multiple, or Not set */
    private static function kindMatches(array $kinds, string $kind): bool
    {
        return match ($kind) {
            'multiple' => count($kinds) > 1,
            'none' => $kinds === [],
            default => in_array($kind, $kinds, true),
        };
    }

    /** One ledger's roster with balances — ?type=agent&show=owing&ids= */
    public function printParties(string $_role, Request $request)
    {
        $type = $request->input('type');
        abort_unless(isset(self::TYPES[$type]), 404);
        $meta = self::TYPES[$type];
        $companyId = $this->companyFilter($request);
        $parties = $this->partiesWithBalances($type, $companyId, $this->projectFilter($request, $companyId));
        // printed from a department's ledger tab (?dept=visa): that department's parties only
        if ($dept = $this->departmentScope($request->input('dept'))) {
            $parties = $parties->whereIn('id', $dept['ids'])->values();
        }

        $ids = $this->idList($request->input('ids'));
        $show = $ids ? 'selected' : (string) $request->input('show', 'all');
        $parties = match ($show) {
            'selected' => $parties->whereIn('id', $ids),
            'owing' => $parties->where('owed', '>', 0),
            'stale' => $parties->where('stale', true),
            'advance' => $parties->where('advance', '>', 0),
            default => $parties,
        };
        // the Type filter the table was showing (?kind=flight)
        $kind = $ids ? null : $request->input('kind');
        if ($kind && isset(self::TYPE_FILTER[$kind])) {
            $parties = $parties->filter(fn ($p) => self::kindMatches($p['kinds'] ?? [], $kind));
        }
        // The screen's default order: largest outstanding first, then name.
        $parties = $parties->sortBy([['owed', 'desc'], ['name', 'asc']])->values();

        return view('party-statement.print-parties', [
            'company' => ($companyId ? Company::find($companyId) : null) ?? Company::find(2) ?? Company::first(),
            'type' => $type,
            'meta' => $meta,
            'parties' => $parties,
            'summary' => $this->summarise($parties, $meta['side']),
            'show' => $show,
            'selection' => $ids ? $parties->count() : 0,
            'staleDays' => self::STALE_DAYS,
        ]);
    }

    /**
     * "1,2,3" or [1,2,3] from the query string → distinct positive ints.
     * Anything else is dropped rather than passed to a whereIn.
     */
    private function idList($raw): array
    {
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(array_map('intval', $parts), fn ($n) => $n > 0)));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * The party's rows for the window, with running balances recomputed from
     * debit/credit in date order — see PartyLedgerService for why the stored
     * `balance` column can't be trusted here. Each row's `balance` is the
     * correct closing figure as of that line, so callers can keep reading
     * `->last()->balance` for the window's closing balance.
     */
    private function fetchTransactions(int $partyId, ?string $from, ?string $to, ?string $type = null, ?int $companyId = null, ?int $projectId = null): array
    {
        [$transactions, $openingBalance] = app(PartyLedgerService::class)
            ->statement($partyId, $from, $to, $type, $companyId, $projectId);

        return [$transactions, $openingBalance];
    }

    private function partyData(User $party): array
    {
        $type = $this->typeOf($party);

        return [
            'id' => $party->id,
            'type' => $type,
            // EP-AG-041 — the same code the bank dashboard prints under a
            // beneficiary, so the two screens name a party the same way.
            'code' => $type ? self::partyCode($party->id, $type) : 'ID-'.str_pad((string) $party->id, 4, '0', STR_PAD_LEFT),
            'name' => $party->name,
            'phone' => $party->phone,
            'email' => $party->email,
            'contact_person' => $party->contact_person,
            'roles' => $party->getRoleNames(),
            'initials' => $this->initials($party->name),
        ];
    }

    /**
     * The feed's query: every party's rows, newest first, each carrying the
     * party's balance AFTER it — summed in SQL over that party's rows up to
     * and including this one, in the statement's own order (payment_date,
     * then id), so it agrees with the running balance the statement prints.
     * Never the stored `balance` column: it is a snapshot taken in insertion
     * order and drifts the moment anything is back-dated (see
     * PartyLedgerService). The caller adds the window.
     */
    private function feedQuery(array $userIds, ?int $companyId = null)
    {
        return Transaction::with(['user:id,name', 'account:id,name,account_number'])
            ->whereIn('user_id', $userIds)
            ->when($companyId, fn ($q) => $q->where('transactions.company_id', $companyId))
            ->selectRaw('transactions.*, (
                    SELECT COALESCE(SUM(x.debit - x.credit), 0) FROM transactions x
                    WHERE x.user_id = transactions.user_id
                      AND x.company_id = transactions.company_id
                      AND (x.payment_date < transactions.payment_date
                           OR (x.payment_date = transactions.payment_date AND (
                               COALESCE(x.transaction_time, TIME(x.created_at)) < COALESCE(transactions.transaction_time, TIME(transactions.created_at))
                               OR (COALESCE(x.transaction_time, TIME(x.created_at)) = COALESCE(transactions.transaction_time, TIME(transactions.created_at)) AND x.id <= transactions.id)
                           )))
                ) AS balance_after')
            ->orderByDesc('payment_date')
            ->orderByRaw('COALESCE(transaction_time, TIME(created_at)) DESC')
            ->orderByDesc('id');
    }

    /** Super admins may view the group or a company; everyone else is locked to their company. */
    private function companyFilter(Request $request): ?int
    {
        $user = Auth::user();
        $canViewAll = $user?->is_super_admin || $user?->can('view all party statement');

        if (! $canViewAll) {
            return $user?->company_id ? (int) $user->company_id : null;
        }

        $id = $request->integer('company');

        return $id && Company::whereKey($id)->exists() ? $id : null;
    }

    private function filterCompanies(): Collection
    {
        $user = Auth::user();
        $canViewAll = $user?->is_super_admin || $user?->can('view all party statement');

        return Company::where('status', 1)
            ->when(! $canViewAll && $user?->company_id, fn ($q) => $q->whereKey($user->company_id))
            ->orderBy('name')->get(['id', 'name']);
    }

    private function projectFilter(Request $request, ?int $companyId): ?int
    {
        if (! $companyId) {
            return null;
        }
        $id = $request->integer('project');

        return $id && DB::table('expense_departments')->where('id', $id)->where('company_id', $companyId)->whereNull('deleted_at')->exists()
            ? $id : null;
    }

    private function filterProjects(?int $companyId): Collection
    {
        if (! $companyId) {
            return collect();
        }

        return DB::table('expense_departments')->where('company_id', $companyId)->where('status', 1)
            ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);
    }

    /** The feed's row shape — the dashboard table and the print sheet read it alike. */
    private function feedRows(Collection $rows, array $typeOfUser): Collection
    {
        $bankEntries = $this->bankEntriesFor($rows);
        [$reasons, $details, , $entries] = $this->reasonsFor($rows);

        return $rows->map(fn ($t) => [
            'id' => $t->id,
            'date' => substr((string) $t->payment_date, 0, 10),
            // What the row is FOR — passenger, route, visa, file — read from
            // the module that posted it. See reasonsFor().
            'reason' => $reasons[$t->id] ?? [],
            'detail' => $details[$t->id] ?? null,
            // The bank ledger entry behind a payment row, when one
            // can be found — see bankEntriesFor(). Null for charges.
            'bank_entry' => $bankEntries[$t->id] ?? null,
            'recorded_at' => optional($t->created_at)->format('Y-m-d H:i:s'),
            'party' => optional($t->user)->name,
            'user_id' => $t->user_id,
            'type' => $typeOfUser[$t->user_id] ?? null,
            'code' => isset($typeOfUser[$t->user_id]) ? self::partyCode($t->user_id, $typeOfUser[$t->user_id]) : null,
            'kind' => $t->type,
            'ref' => $t->reference_no,
            'remarks' => $entries[$t->id] ?? $t->display_remarks,
            'method' => $t->payment_method,
            'bank' => optional($t->account)->name,
            'bank_no' => optional($t->account)->account_number,
            'debit' => (float) $t->debit,
            'credit' => (float) $t->credit,
            'balance_after' => (float) $t->balance_after,
            'balance_before' => (float) $t->balance_after - ((float) $t->debit - (float) $t->credit),
        ]);
    }

    /** user_id => type for every party on the three ledgers. */
    private function typeOfUsers(): array
    {
        $map = [];
        foreach (self::TYPES as $type => $meta) {
            foreach (User::role($meta['role'])->pluck('id') as $id) {
                $map[$id] = $type;
            }
        }

        return $map;
    }

    /**
     * The bank ledger entry behind each party-ledger row, keyed by
     * transactions.id: ['bank_id' => banks.id, 'entry_id' => journal_entries.id].
     * Rows that resolve become links into Manage Bank; the rest stay text.
     *
     * A party row and a journal entry share no foreign key. What they share:
     *
     *   1. the reference — recordPayment() writes the same number on both
     *      (transactions.reference_no = journal_entries.reference), and so do
     *      the ticket/visa payment posters. Matched first, and only when that
     *      entry really has a leg on the row's bank, so a sale invoice number
     *      that happens to equal some unrelated reference cannot link.
     *   2. failing that, the bank's own ledger account on the same day for the
     *      same amount — the way the reconciliation screen pairs rows. Taken
     *      only when exactly ONE such leg exists; two candidates means a guess,
     *      and a wrong link is worse than none.
     *
     * Charges (sales, flight passengers, opening balances) never touch a bank
     * and never link. Everything is fetched in bulk; a failure here loses the
     * links, never the statement.
     */
    private function bankEntriesFor(Collection $rows): array
    {
        try {
            $rows = $rows->filter(fn ($t) => $t->account_id && ((float) $t->debit > 0 || (float) $t->credit > 0));
            if ($rows->isEmpty()) {
                return [];
            }

            // banks.id -> the ledger account that bank's entries are posted to.
            $bankAccounts = Bank::whereIn('id', $rows->pluck('account_id')->unique())
                ->whereNotNull('account_id')
                ->pluck('account_id', 'id');
            if ($bankAccounts->isEmpty()) {
                return [];
            }

            // 1. by reference, verified against the bank's account.
            $refs = $rows->pluck('reference_no')->filter()->unique()->values();
            $byRef = [];
            if ($refs->isNotEmpty()) {
                $legs = JournalItem::query()
                    ->join('journal_entries as e', 'e.id', '=', 'journal_items.journal_entry_id')
                    ->whereIn('e.reference', $refs)
                    ->whereIn('journal_items.account_id', $bankAccounts->values())
                    ->get(['e.id as entry_id', 'e.reference', 'journal_items.account_id']);
                foreach ($legs as $leg) {
                    $byRef[$leg->reference.'|'.$leg->account_id] = (int) $leg->entry_id;
                }
            }

            // 2. by bank + day + amount, for the rows still unresolved.
            $dates = $rows->map(fn ($t) => substr((string) $t->payment_date, 0, 10))->unique()->values();
            $legs = JournalItem::query()
                ->join('journal_entries as e', 'e.id', '=', 'journal_items.journal_entry_id')
                ->whereIn('journal_items.account_id', $bankAccounts->values())
                ->whereIn(DB::raw('DATE(e.date)'), $dates)
                ->get(['e.id as entry_id', 'e.date', 'journal_items.account_id', 'journal_items.debit', 'journal_items.credit']);
            $byAmount = [];
            foreach ($legs as $leg) {
                $amt = (float) $leg->debit > 0 ? (float) $leg->debit : (float) $leg->credit;
                $key = $leg->account_id.'|'.substr((string) $leg->date, 0, 10).'|'.number_format($amt, 2, '.', '');
                $byAmount[$key][] = (int) $leg->entry_id;
            }

            $out = [];
            foreach ($rows as $t) {
                $acct = $bankAccounts->get($t->account_id);
                if (! $acct) {
                    continue;
                }
                $entryId = $t->reference_no ? ($byRef[$t->reference_no.'|'.$acct] ?? null) : null;
                if (! $entryId) {
                    $amt = (float) $t->debit > 0 ? (float) $t->debit : (float) $t->credit;
                    $key = $acct.'|'.substr((string) $t->payment_date, 0, 10).'|'.number_format($amt, 2, '.', '');
                    $candidates = array_unique($byAmount[$key] ?? []);
                    $entryId = count($candidates) === 1 ? $candidates[0] : null;
                }
                if ($entryId) {
                    $out[$t->id] = ['bank_id' => (int) $t->account_id, 'entry_id' => $entryId];
                }
            }

            // The bank's own transaction number (DEP/WDR/TRF-00000959) for each
            // linked entry — printed as the voucher when the party row has none,
            // so there is always a number to click. Same rule as Manage Bank.
            if ($out) {
                $entries = JournalEntry::whereIn('id', array_column($out, 'entry_id'))->get()->keyBy('id');
                foreach ($out as $tid => $link) {
                    $entry = $entries->get($link['entry_id']);
                    $out[$tid]['txn_no'] = $entry ? BankControllerV2::txnNo($entry) : null;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            \Log::warning('Party statement bank-entry lookup failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * What each ledger row was FOR, keyed by transactions.id — the facts a
     * reader wants beside "Ticket purchase ৳54,000": whose ticket, which PNR,
     * which route; whose visa, for which country; which passenger on which
     * flight. Each is a list of ['icon' => fa-class, 'text' => string] lines.
     *
     * Every row type points at its source record through invoice_id, and the
     * source modules keep the detail: ticket_purchases → passport holder +
     * ticket → airports; contract_flight_passengers → holder + flight → ticket
     * → airports/airline + category; visa_processes → holder + country +
     * category; ticket_sales / visa_sales / contract_file_sales through their
     * item rows. One query per type, never per row. A failure in one type
     * loses that type's lines and nothing else.
     *
     * Public because the printed / emailed statement (App\Support\StatementRows)
     * shows the same who / what / how in its Particulars column. Reads only.
     */
    public function reasonsFor(Collection $rows): array
    {
        $out = [];
        // The full record behind each row — every column the summary lines
        // leave out — as ['columns' => [...], 'rows' => [[...], ...]], drawn
        // under the row when its arrow is opened. Same shape for every type so
        // one renderer draws them all.
        $detail = [];
        // The same facts split for a wider table: who (names), what (the
        // item — PNR · route, country · category), how (carrier · class,
        // travel date). The statement gives each its own column.
        $facts = [];
        // The Entry remark with what Details now says taken out, where they
        // overlap (ticket change rows) — id => text; other rows keep theirs.
        $entry = [];
        $byType = $rows->filter(fn ($t) => $t->invoice_id)->groupBy('type');
        $ids = fn (string $type) => $byType->get($type, collect())->pluck('invoice_id')->unique()->values()->all();
        $put = function ($t, array $lines, ?array $full = null, ?array $split = null) use (&$out, &$detail, &$facts) {
            if ($lines) {
                $out[$t->id] = $lines;
            }
            if ($full && $full['rows']) {
                $detail[$t->id] = $full;
            }
            if ($split) {
                $facts[$t->id] = $split;
            }
        };
        $money = fn ($v) => $v === null ? '' : '৳'.number_format((float) $v, 0);
        $dmy = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '';
        $route = fn ($from, $to) => ($from || $to) ? (($from ?: '?').' → '.($to ?: '?')) : null;
        $join = fn (array $parts, string $glue = ' · ') => implode($glue, array_values(array_filter($parts, fn ($v) => $v !== null && $v !== '')));
        // First line is the NAME alone, as the Party column leads with its name;
        // the passport number is not what identifies a transaction to a reader.
        $person = fn ($name, $passport) => $name ? ['icon' => 'fa-user', 'text' => $name] : null;

        // One passport holder's ticket, as a pair of lines. Shared by purchases,
        // sales (per item) and flight passengers.
        $ticketLines = function ($r) use ($join, $route, $person) {
            $lines = [];
            if ($p = $person($r->holder ?? null, $r->passport_no ?? null)) {
                $lines[] = $p;
            }
            // Then the ticket — PNR and route — and the carrier with class.
            $pnr = $join([$r->ticket_no ?? null, $route($r->from_code ?? null, $r->to_code ?? null)]);
            if ($pnr) {
                $lines[] = ['icon' => 'fa-plane', 'text' => $pnr];
            }
            $carrier = $join([$r->airline ?? $r->airline_or_operator ?? null, isset($r->trip_type) ? str_replace('-', ' ', $r->trip_type) : null, $r->ticket_class ?? null]);
            if ($carrier) {
                $lines[] = ['icon' => 'fa-tag', 'text' => $carrier];
            }

            return $lines;
        };

        // File applicant details for both receivable and payable rows.
        // Service invoice_id points to a service, not a file (IDs may overlap).
        try {
            $fileTypes = ['contract_file_client', 'contract_file', 'contract_file_client_adjustment', 'contract_file_vendor_adjustment'];
            $fileIds = collect($fileTypes)->flatMap(fn ($type) => $ids($type));
            $services = collect();
            if (($serviceIds = $ids('contract_file_service_cost')) && Schema::hasTable('contract_file_service_costs')) {
                $services = ContractFileServiceCost::with('vendor:id,name')->whereIn('id', $serviceIds)->get()->keyBy('id');
                $fileIds = $fileIds->merge($services->pluck('contract_file_id'));
            }
            if ($fileIds->isNotEmpty() && Schema::hasTable('contract_files')) {
                $files = ContractFile::with(['passportHolder', 'country', 'category', 'client', 'vendor'])->whereIn('id', $fileIds->unique())->get()->keyBy('id');
                foreach (array_merge($fileTypes, ['contract_file_service_cost']) as $type) {
                    foreach ($byType->get($type, collect()) as $t) {
                        $service = $type === 'contract_file_service_cost' ? $services->get($t->invoice_id) : null;
                        $file = $files->get($type === 'contract_file_service_cost' ? $service?->contract_file_id : $t->invoice_id);
                        if (! $file) {
                            continue;
                        }
                        $what = $join([$file->file_number, $file->country?->name]);
                        $how = $join([$file->category?->name, $file->status_label, $file->exception_flag ? ContractFile::EXCEPTION_FLAGS[$file->exception_flag] ?? $file->exception_flag : null]);
                        $lines = array_values(array_filter([
                            $person($file->applicant_name, $file->passport_no),
                            ['icon' => 'fa-folder-open', 'text' => $what],
                            ['icon' => 'fa-tag', 'text' => $how],
                            $service ? ['icon' => 'fa-receipt', 'text' => $service->title] : null,
                        ]));
                        $put($t, $lines, [
                            'columns' => ['Applicant', 'Passport', 'File', 'Country / Category', 'Submitted', 'Expected', 'Agent / Client', 'Provider / Service', 'Stage', 'Entry amount'],
                            'rows' => [[$file->applicant_name, $file->passport_no, $file->file_number, $join([$file->country?->name, $file->category?->name]), $dmy($file->submit_date), $dmy($file->expected_out_date), $file->client?->name, $join([$service ? $service->vendor?->name : $file->vendor?->name, $service?->title]), $file->status_label, $money(max((float) $t->debit, (float) $t->credit))]],
                        ], ['who' => array_values(array_filter([$file->applicant_name])), 'what' => [$what], 'how' => $how]);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('reasonsFor file process: '.$e->getMessage());
        }

        // ── ticket purchases ──────────────────────────────────────────────
        try {
            if ($pid = $ids('ticket_purchase')) {
                $found = DB::table('ticket_purchases as p')
                    ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                    ->leftJoin('tickets as t', 't.id', '=', 'p.ticket_id')
                    ->leftJoin('airports as a', 'a.id', '=', 't.from_airport_id')
                    ->leftJoin('airports as b', 'b.id', '=', 't.to_airport_id')
                    ->leftJoin('airlines as al', 'al.id', '=', 't.airline_id')
                    ->whereIn('p.id', $pid)
                    ->get(['p.id', 'p.ticket_no', 'p.airline_or_operator', 'p.ticket_class', 'p.trip_type', 'p.amount', 'p.purchase_date', 'p.due_date', 'p.status', 'p.payment_status',
                        'h.name as holder', 'h.passport_no', 'a.name as from_name', 'b.name as to_name', 'a.code as from_code', 'b.code as to_code', 'al.name as airline'])
                    ->keyBy('id');
                foreach ($byType->get('ticket_purchase', collect()) as $t) {
                    if (! ($r = $found->get($t->invoice_id))) {
                        continue;
                    }
                    $put($t, $ticketLines($r), [
                        'columns' => ['PNR / Passenger', 'Passport', 'Ticket', 'Route', 'Purchased', 'Status', 'Amount'],
                        'rows' => [[
                            $join([$r->ticket_no, $r->holder]), $r->passport_no,
                            $join([$r->airline ?: $r->airline_or_operator, $r->trip_type ? str_replace('-', ' ', $r->trip_type) : null, $r->ticket_class]),
                            $join([$r->from_name ?: $r->from_code, $r->to_name ?: $r->to_code], ' → '),
                            $dmy($r->purchase_date), $join([$r->status, $r->payment_status ? str_replace('_', ' ', $r->payment_status) : null]), $money($r->amount),
                        ]],
                    ], [
                        'who' => array_values(array_filter([$r->holder])),
                        'what' => array_values(array_filter([$join([$r->ticket_no, $route($r->from_code, $r->to_code)])])),
                        'how' => $join([$r->airline ?: $r->airline_or_operator, $r->trip_type ? str_replace('-', ' ', $r->trip_type) : null, $r->ticket_class]),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('reasonsFor ticket_purchase: '.$e->getMessage());
        }

        // ── ticket sales: every ticket on the invoice ──────────────────────
        try {
            if ($sid = $ids('ticket_sale')) {
                $items = DB::table('ticket_sale_items as i')
                    ->join('ticket_purchases as p', 'p.id', '=', 'i.ticket_purchase_id')
                    ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                    ->leftJoin('tickets as t', 't.id', '=', 'p.ticket_id')
                    ->leftJoin('airports as a', 'a.id', '=', 't.from_airport_id')
                    ->leftJoin('airports as b', 'b.id', '=', 't.to_airport_id')
                    ->leftJoin('airlines as al', 'al.id', '=', 't.airline_id')
                    ->whereIn('i.ticket_sale_id', $sid)
                    ->get(['i.ticket_sale_id', 'i.price', 'p.ticket_no', 'p.trip_type', 'p.ticket_class', 't.title as route_title',
                        'h.name as holder', 'h.passport_no', 'a.name as from_name', 'b.name as to_name', 'a.code as from_code', 'b.code as to_code', 'al.name as airline'])
                    ->groupBy('ticket_sale_id');
                foreach ($byType->get('ticket_sale', collect()) as $t) {
                    $rows = $items->get($t->invoice_id, collect());
                    if ($rows->isEmpty()) {
                        continue;
                    }
                    $full = ['columns' => ['PNR / Passenger', 'Passport', 'Ticket', 'Route', 'Price'], 'rows' => []];
                    $split = ['who' => [], 'what' => [], 'how' => ''];
                    foreach ($rows as $r) {
                        $currentRoute = $r->route_title ?: $route($r->from_code, $r->to_code);
                        if ($r->holder && ! in_array($r->holder, $split['who'], true)) {
                            $split['who'][] = $r->holder;
                        }
                        $split['what'][] = $join([$r->ticket_no, $currentRoute, $r->airline]);
                        $full['rows'][] = [$join([$r->ticket_no, $r->holder]), $r->passport_no,
                            $join([$r->airline, $r->trip_type ? str_replace('-', ' ', $r->trip_type) : null, $r->ticket_class]),
                            $currentRoute, $money($r->price)];
                    }
                    // every ticket of the invoice on its own line (owner, 2026-10-18): "+5 more passengers"
                    // hid NTXOCA, UFUMMB, AMMOQV … of INV000051, and they read as not posted to the agent
                    $lines = [];
                    foreach ($rows as $r) {
                        $lines[] = ['icon' => 'fa-user', 'text' => $join([$r->holder, $r->ticket_no, $r->route_title ?: $route($r->from_code, $r->to_code)])];
                    }
                    $put($t, $lines, $full, $split);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('reasonsFor ticket_sale: '.$e->getMessage());
        }

        // ── contract flight passengers ─────────────────────────────────────
        try {
            if ($cid = $ids(ContractFlightPassenger::AGENT_LEDGER_TYPE)) {
                // leftJoin: a passenger taken off his flight (2026-09-26) has no
                // flight, and an inner join dropped his row's details entirely.
                $found = DB::table('contract_flight_passengers as p')
                    ->leftJoin('contract_flights as f', 'f.id', '=', 'p.contract_flight_id')
                    ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                    ->leftJoin('tickets as t', 't.id', '=', 'f.ticket_id')
                    ->leftJoin('airports as a', 'a.id', '=', 't.from_airport_id')
                    ->leftJoin('airports as b', 'b.id', '=', 't.to_airport_id')
                    ->leftJoin('airlines as al', 'al.id', '=', 't.airline_id')
                    ->leftJoin('flight_categories as c', 'c.id', '=', 'p.flight_category_id')
                    ->whereIn('p.id', $cid)
                    ->get(['p.id', 'h.name as holder', 'h.passport_no', 'f.flight_number', 'f.airline_flight_no', 'f.departure_at', 'f.arrival_at',
                        'al.name as airline', 'a.name as from_name', 'b.name as to_name', 'a.code as from_code', 'b.code as to_code', 'c.name as category',
                        'p.ticket_class', 'p.handling_type', 'p.stage', 'p.exception_flag', 'p.agent_price', 'p.has_bmet', 'p.has_manpower_card'])
                    ->keyBy('id');
                // Why a charge was voided, for the red line under it: where he
                // was taken off, the reason and the day (guarded — the changes
                // table may not exist on a lagging server).
                $cancels = collect();
                try {
                    if (ContractFlightPassengerChange::installed()) {
                        $cancels = ContractFlightPassengerChange::with('fromFlight:id,flight_number')
                            ->whereIn('contract_flight_passenger_id', $cid)->where('kind', 'cancelled')
                            ->orderBy('id')->get()->keyBy('contract_flight_passenger_id');
                    }
                } catch (\Throwable) {
                    $cancels = collect();
                }
                foreach ($byType->get(ContractFlightPassenger::AGENT_LEDGER_TYPE, collect()) as $t) {
                    if (! ($r = $found->get($t->invoice_id))) {
                        continue;
                    }
                    $lines = [];
                    if ($p = $person($r->holder, $r->passport_no)) {
                        $lines[] = $p;
                    }
                    if ((float) $t->debit == 0.0 && in_array($r->exception_flag, ContractFlightPassenger::AGENT_CHARGE_VOIDING_FLAGS, true)) {
                        $c = $cancels->get($r->id);
                        $lines[] = ['icon' => 'fa-ban', 'tone' => 'danger', 'text' => $c
                         ? $join(['Cancelled from '.($c->fromFlight?->flight_number ?: 'his flight'), ContractFlightPassengerChange::CANCEL_REASONS[$c->reason] ?? null, $c->created_at?->format('d M Y')]).' — charge voided'
                         : (ContractFlightPassenger::EXCEPTION_FLAGS[$r->exception_flag] ?? 'Cancelled').' — charge voided'];
                    }
                    // Batch, carrier, route — not the airline's own flight code, the
                    // departure clock or the pipeline stage (owner, 2026-09-15: the
                    // column is for what was charged, not the flight's paperwork).
                    $lines[] = ['icon' => 'fa-plane', 'text' => $join([$r->flight_number, $route($r->from_code, $r->to_code)])];
                    $lines[] = ['icon' => 'fa-tag', 'text' => $join([$r->airline, $r->ticket_class, $r->exception_flag ? strtoupper($r->exception_flag) : null])];
                    $services = $join([$r->has_bmet ? 'BMET' : null, $r->has_manpower_card ? 'Manpower card' : null, 'Boarding']);
                    $put($t, $lines, [
                        'columns' => ['Passenger', 'Passport', 'Flight', 'Route', 'Departs', 'Category / Class', 'Handling', 'Services', 'Stage', 'Agent price'],
                        'rows' => [[
                            $r->holder, $r->passport_no, $join([$r->flight_number, $r->airline, $r->airline_flight_no]),
                            $join([$r->from_name ?: $r->from_code, $r->to_name ?: $r->to_code], ' → '),
                            $r->departure_at ? Carbon::parse($r->departure_at)->format('d/m/Y H:i') : '',
                            $join([$r->category, $r->ticket_class]), $r->handling_type ? str_replace('_', ' ', $r->handling_type) : '',
                            $services, $join([$r->stage ? str_replace('_', ' ', $r->stage) : null, $r->exception_flag ? strtoupper($r->exception_flag) : null]),
                            $money($r->agent_price),
                        ]],
                    ], [
                        'who' => array_values(array_filter([$r->holder])),
                        'what' => array_values(array_filter([$join([$r->flight_number, $route($r->from_code, $r->to_code)])])),
                        'how' => $join([$r->airline, $r->ticket_class, $r->exception_flag ? strtoupper($r->exception_flag) : null]),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('reasonsFor contract flight: '.$e->getMessage());
        }

        // ── contract flight change charges (cancellation / re-booking) ──────
        try {
            if (ContractFlightPassengerChange::installed() && ($chid = $ids(ContractFlightPassengerChange::LEDGER_TYPE))) {
                $changes = ContractFlightPassengerChange::with(['passenger.passportHolder:id,name,passport_no', 'fromFlight:id,flight_number', 'toFlight:id,flight_number'])
                    ->whereIn('id', $chid)->get()->keyBy('id');
                foreach ($byType->get(ContractFlightPassengerChange::LEDGER_TYPE, collect()) as $t) {
                    if (! ($c = $changes->get($t->invoice_id))) {
                        continue;
                    }
                    $lines = [];
                    if ($p = $person($c->passenger?->passportHolder?->name, null)) {
                        $lines[] = $p;
                    }
                    $what = match (true) {
                        $c->kind === 'cancelled' => 'Cancellation penalty — taken off '.($c->fromFlight?->flight_number ?: 'his flight'),
                        // Offload / Deported / refund rows have their own name (2026-10-13: they read "Reschedule").
                        $c->kind === 'flagged' => match ($c->reason) {
                            'deported' => 'Deportation charge',
                            'boarding_denied', 'immigration_offload', 'flight_missed' => 'Offload charge',
                            default => 'Cancellation penalty',
                        }.' — '.($c->fromFlight?->flight_number ?: 'his flight'),
                        $c->kind === 'ticket_refund' => 'Refund penalty'.(preg_match('/^Refund — ([^:]+):/u', (string) $c->note, $rm) ? ' — '.trim($rm[1]) : '')
                            .' (on '.($c->fromFlight?->flight_number ?: 'his flight').')',
                        in_array($c->kind, ['reissue', 'ticket_reissue'], true) => 'Reissue penalty — '.$join([$c->fromFlight?->flight_number, $c->toFlight?->flight_number], ' → '),
                        // Cancel return (2026-10-14): he stayed on; his unused return was cancelled
                        $c->kind === 'return_cancel' => 'Return cancellation penalty'.(preg_match('/^Return cancelled — ([^\s:(]+)/u', (string) $c->note, $rm) ? ' — '.trim($rm[1]) : '')
                            .' (on '.($c->fromFlight?->flight_number ?: 'his flight').')',
                        default => 'Reschedule penalty — '.$join([$c->fromFlight?->flight_number, $c->toFlight?->flight_number], ' → '),
                    };
                    // Same flight both sides = a charge on one ticket, not a move (2026-10-10:
                    // "Reschedule charge — CF-035 → CF-035" read as a flight change).
                    if (in_array($c->kind, ['reissue', 'reschedule', 'ticket_reissue'], true) && $c->from_flight_id && (int) $c->from_flight_id === (int) $c->toFlight?->id) {
                        $pnr = preg_match('/Charge per ticket[^:]*:\s*([^৳]+?)\s৳/u', (string) $c->note, $m) ? trim($m[1]) : null;
                        $what = ($c->reason === ContractFlightPassengerController::LANE_NEW_TICKET_REASON ? 'New ticket after refund' : 'Reissue penalty')
                            .($pnr ? ' — '.$pnr : '').' (on '.($c->fromFlight?->flight_number ?: 'his flight').')';
                    }
                    $lines[] = ['icon' => 'fa-ban', 'tone' => 'danger', 'text' => $what];
                    // The agent sees ONE figure (owner, 2026-10-13): the money breakdown the
                    // system writes into the note (penalty, our charge, refund, kept) never
                    // reaches his statement — only the parts without an amount (dates, notes).
                    $plain = implode(' · ', array_filter(array_map('trim', explode(' · ', (string) $c->note)), fn ($s) => $s !== '' && ! str_contains($s, '৳')));
                    if ($plain !== '') {
                        $lines[] = ['icon' => 'fa-note-sticky', 'text' => $plain];
                    }
                    $put($t, $lines, null, ['who' => array_values(array_filter([$c->passenger?->passportHolder?->name])), 'what' => [$what], 'how' => '']);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('reasonsFor contract flight change: '.$e->getMessage());
        }

        // ── contract flight suspend withdraw (2026-11-02): vendor bill + agent's ONE line ──
        try {
            $swTypes = [\App\Models\ContractFlightSuspendWithdrawal::VENDOR_TYPE, \App\Models\ContractFlightSuspendWithdrawal::AGENT_TYPE];
            $swIds = array_merge($ids($swTypes[0]), $ids($swTypes[1]));
            if ($swIds && \App\Models\ContractFlightSuspendWithdrawal::available()) {
                $sws = DB::table('contract_flight_suspend_withdrawals as w')
                    ->join('contract_flight_passengers as p', 'p.id', '=', 'w.contract_flight_passenger_id')
                    ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                    ->leftJoin('contract_flights as f', 'f.id', '=', 'w.contract_flight_id')
                    ->leftJoin('ticket_purchases as tp', 'tp.id', '=', 'w.ticket_purchase_id')
                    ->whereIn('w.id', $swIds)
                    ->get(['w.id', 'w.note', 'h.name as holder', 'f.flight_number', 'tp.ticket_no'])->keyBy('id');
                foreach ($swTypes as $type) {
                    foreach ($byType->get($type, collect()) as $t) {
                        if (! ($w = $sws->get($t->invoice_id))) {
                            continue;
                        }
                        $what = 'Suspend withdraw'.($w->ticket_no ? ' — '.$w->ticket_no : '').' (on '.($w->flight_number ?: 'his flight').')';
                        $lines = array_values(array_filter([
                            $person($w->holder, null),
                            ['icon' => 'fa-unlock', 'tone' => 'danger', 'text' => $what],
                            $w->note ? ['icon' => 'fa-note-sticky', 'text' => $w->note] : null,
                        ]));
                        $put($t, $lines, null, ['who' => array_values(array_filter([$w->holder])), 'what' => [$what], 'how' => '']);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('reasonsFor suspend withdraw: '.$e->getMessage());
        }

        // ── contract flight reversals, airline penalties, fare differences ──
        // (2026-09-28) Added rows, never edits: the package reversal is keyed
        // on the passenger, the penalty / fare rows on the change.
        try {
            $revType = ContractFlightPassenger::AGENT_REVERSAL_TYPE;
            if ($rid = $ids($revType)) {
                $holders = DB::table('contract_flight_passengers as p')
                    ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                    ->whereIn('p.id', $rid)->pluck('h.name', 'p.id');
                foreach ($byType->get($revType, collect()) as $t) {
                    $lines = [];
                    if ($p = $person($holders[$t->invoice_id] ?? null, null)) {
                        $lines[] = $p;
                    }
                    $lines[] = (float) $t->credit > 0
                        ? ['icon' => 'fa-rotate-left', 'tone' => 'danger', 'text' => (string) $t->display_remarks]
                        : ['icon' => 'fa-rotate-right', 'text' => (string) $t->display_remarks];
                    $put($t, $lines, null, ['who' => array_values(array_filter([$holders[$t->invoice_id] ?? null])), 'what' => [(string) $t->display_remarks], 'how' => '']);
                }
            }
            // A cancelled return's package revision (2026-10-14): keyed on the change.
            $prType = ContractFlightPassengerChange::PRICE_REVISION_TYPE;
            if (($prid = $ids($prType)) && ContractFlightPassengerChange::installed()) {
                $holders = DB::table('contract_flight_passenger_changes as c')
                    ->join('contract_flight_passengers as p', 'p.id', '=', 'c.contract_flight_passenger_id')
                    ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                    ->whereIn('c.id', $prid)->pluck('h.name', 'c.id');
                foreach ($byType->get($prType, collect()) as $t) {
                    $lines = [];
                    if ($p = $person($holders[$t->invoice_id] ?? null, null)) {
                        $lines[] = $p;
                    }
                    $lines[] = ['icon' => 'fa-rotate-left', 'tone' => 'danger', 'text' => (string) $t->display_remarks];
                    $put($t, $lines, null, ['who' => array_values(array_filter([$holders[$t->invoice_id] ?? null])), 'what' => [(string) $t->display_remarks], 'how' => '']);
                }
            }
            $penType = ContractFlightPassengerChange::PENALTY_LEDGER_TYPE;
            $fareType = ContractFlightPassengerChange::FARE_LEDGER_TYPE;
            $chIds = array_merge($ids($penType), $ids($fareType));
            if ($chIds && ContractFlightPassengerChange::installed()) {
                $changes = ContractFlightPassengerChange::with(['passenger.passportHolder:id,name,passport_no'])
                    ->whereIn('id', $chIds)->get()->keyBy('id');
                foreach ([$penType, $fareType] as $type) {
                    foreach ($byType->get($type, collect()) as $t) {
                        $c = $changes->get($t->invoice_id);
                        $lines = [];
                        if ($p = $person($c?->passenger?->passportHolder?->name, null)) {
                            $lines[] = $p;
                        }
                        $lines[] = ['icon' => 'fa-ban', 'tone' => 'danger', 'text' => (string) $t->display_remarks];
                        if ($c?->note) {
                            $lines[] = ['icon' => 'fa-note-sticky', 'text' => $c->note];
                        }
                        $put($t, $lines, null, ['who' => array_values(array_filter([$c?->passenger?->passportHolder?->name])), 'what' => [(string) $t->display_remarks], 'how' => '']);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('reasonsFor contract flight reversal: '.$e->getMessage());
        }

        // ── Ticket Orders → Change rows (re-issue / refund / void) ─────────
        // invoice_id = the ticket_changes id. Details carry whose ticket, the
        // PNR and route, and where a re-issue moved the travel date; Entry
        // keeps only what happened (owner, 2026-10-26: the PNR and name sat in
        // Entry and Details read "—").
        try {
            $tcTypes = [TicketChange::CHARGE_TYPE, TicketChange::SALE_REVERSAL_TYPE, TicketChange::FEE_TYPE, TicketChange::FARE_TYPE];
            $tcIds = collect($tcTypes)->flatMap(fn ($type) => $ids($type))->unique()->values()->all();
            if ($tcIds && Schema::hasTable('ticket_changes')) {
                $found = DB::table('ticket_changes as c')
                    ->join('ticket_purchases as p', 'p.id', '=', 'c.ticket_purchase_id')
                    ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                    ->leftJoin('tickets as t', 't.id', '=', 'p.ticket_id')
                    ->leftJoin('airports as a', 'a.id', '=', 't.from_airport_id')
                    ->leftJoin('airports as b', 'b.id', '=', 't.to_airport_id')
                    ->whereIn('c.id', $tcIds)
                    ->get(['c.id', 'c.kind', 'c.old_travel_at', 'c.new_travel_at', 'p.ticket_no', 'h.name as holder', 't.title as route_title', 'a.code as from_code', 'b.code as to_code'])
                    ->keyBy('id');
                // "Re-issue penalty — VD57QU · NAME" → "Re-issue penalty". A remark
                // that does not hold the text (PNR or name edited since) stays whole.
                $drop = function (string $s, array $needles): string {
                    foreach ($needles as $n) {
                        $q = preg_quote($n, '/');
                        $s = preg_replace('/—\s*'.$q.'(?:\s*·\s*|(?=\s|\(|$))/u', '— ', $s, 1, $hit);
                        if (! $hit) {
                            $s = preg_replace('/\s*·?\s*(?<![\w-])'.$q.'(?=\s|\(|·|$)/u', '', $s, 1);
                        }
                    }

                    return trim(preg_replace(['/\s*—\s*(?=\(|$)/u', '/\s{2,}/u'], ' ', $s));
                };
                foreach ($tcTypes as $type) {
                    foreach ($byType->get($type, collect()) as $t) {
                        if (! ($r = $found->get($t->invoice_id))) {
                            continue;
                        }
                        // A new ticket's charge is posted under the NEW PNR.
                        $pnr = $t->reference_no ?: $r->ticket_no;
                        $lines = [];
                        if ($p = $person($r->holder, null)) {
                            $lines[] = $p;
                        }
                        if ($what = $join([$pnr, $r->route_title ?: $route($r->from_code, $r->to_code)])) {
                            $lines[] = ['icon' => 'fa-plane', 'text' => $what];
                        }
                        // The seller's remark already spells the move out ("… → …").
                        $remark = (string) $t->display_remarks;
                        if ($r->new_travel_at && in_array($r->kind, ['reissue', 'reschedule'], true) && ! str_contains($remark, '→')) {
                            $new = Carbon::parse($r->new_travel_at);
                            $was = $r->old_travel_at ? Carbon::parse($r->old_travel_at) : null;
                            $lines[] = ['icon' => 'fa-calendar', 'text' => $join([$new->format('d M Y'), $was && ! $was->isSameDay($new) ? 'was '.$was->format('d M Y') : null])];
                        }
                        // who only — the same fact StatementRows::facts() looked up for
                        // the agent's rows, so the printed statement does not change.
                        $agentRow = in_array($type, [TicketChange::CHARGE_TYPE, TicketChange::SALE_REVERSAL_TYPE], true);
                        $put($t, $lines, null, $agentRow && $r->holder ? ['who' => [$r->holder], 'what' => [], 'how' => ''] : null);
                        $short = $drop($remark, array_values(array_unique(array_filter([$pnr, $r->ticket_no, $r->holder]))));
                        if ($short !== '' && $short !== $remark) {
                            $entry[$t->id] = $short;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('reasonsFor ticket change: '.$e->getMessage());
        }

        // ── visa processing (the vendor side) ──────────────────────────────
        $visaLines = function ($r) use ($join, $person) {
            $lines = [];
            if ($p = $person($r->holder, $r->passport_no)) {
                $lines[] = $p;
            }
            $travel = $r->travel_date ? 'travel '.Carbon::parse($r->travel_date)->format('d/m/Y') : null;
            $lines[] = ['icon' => 'fa-passport', 'text' => $join([$r->country, $r->category ?: $r->visa_type])];
            if ($travel) {
                $lines[] = ['icon' => 'fa-calendar', 'text' => $travel];
            }

            return $lines;
        };
        $visaQuery = fn () => DB::table('visa_processes as v')
            ->leftJoin('passport_holders as h', 'h.id', '=', 'v.passport_holder_id')
            ->leftJoin('countries as co', 'co.id', '=', 'v.country_id')
            ->leftJoin('visa_categories as vc', 'vc.id', '=', 'v.visa_category_id')
            ->select(['v.id', 'v.application_id', 'v.visa_type', 'v.duration', 'v.travel_date', 'v.costing_price', 'v.sale_price', 'v.stage', 'v.status', 'h.name as holder', 'h.passport_no', 'co.name as country', 'vc.name as category']);
        try {
            if ($vid = $ids('visa_process')) {
                $found = $visaQuery()->whereIn('v.id', $vid)->get()->keyBy('id');
                foreach ($byType->get('visa_process', collect()) as $t) {
                    if (! ($r = $found->get($t->invoice_id))) {
                        continue;
                    }
                    $put($t, $visaLines($r), [
                        'columns' => ['Applicant', 'Passport', 'Country', 'Category / Type', 'Duration', 'Travel', 'Application', 'Stage', 'Cost'],
                        'rows' => [[$r->holder, $r->passport_no, $r->country, $join([$r->category, $r->visa_type]), $r->duration, $dmy($r->travel_date),
                            $r->application_id, $join([$r->stage ? str_replace('_', ' ', $r->stage) : null, $r->status]), $money($r->costing_price)]],
                    ], [
                        'who' => array_values(array_filter([$r->holder])),
                        'what' => array_values(array_filter([$join([$r->country, $r->category ?: $r->visa_type])])),
                        'how' => $r->travel_date ? 'travel '.$dmy($r->travel_date) : '',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('reasonsFor visa_process: '.$e->getMessage());
        }

        // ── visa sales: every applicant on the invoice ─────────────────────
        try {
            if ($vsid = $ids('visa_sale')) {
                $items = DB::table('visa_sale_items')->whereIn('visa_sale_id', $vsid)->whereNotNull('visa_process_id')->get(['visa_sale_id', 'visa_process_id', 'sale_price'])->groupBy('visa_sale_id');
                $procs = $visaQuery()->whereIn('v.id', $items->flatten(1)->pluck('visa_process_id')->unique()->all())->get()->keyBy('id');
                foreach ($byType->get('visa_sale', collect()) as $t) {
                    $pairs = $items->get($t->invoice_id, collect())->map(fn ($i) => [$procs->get($i->visa_process_id), $i->sale_price])->filter(fn ($p) => $p[0]);
                    if ($pairs->isEmpty()) {
                        continue;
                    }
                    $rows = $pairs->map(fn ($p) => $p[0]);
                    $full = ['columns' => ['Applicant', 'Passport', 'Country', 'Category / Type', 'Travel', 'Sale price'], 'rows' => []];
                    $split = ['who' => [], 'what' => [], 'how' => ''];
                    foreach ($pairs as [$r, $price]) {
                        $full['rows'][] = [$r->holder, $r->passport_no, $r->country, $join([$r->category, $r->visa_type]), $dmy($r->travel_date), $money($price)];
                        if ($r->holder && ! in_array($r->holder, $split['who'], true)) {
                            $split['who'][] = $r->holder;
                        }
                        $w = $join([$r->country, $r->category ?: $r->visa_type]);
                        if ($w && ! in_array($w, $split['what'], true)) {
                            $split['what'][] = $w;
                        }
                    }
                    $lines = [];
                    foreach ($rows->take(2) as $r) {
                        $lines[] = ['icon' => 'fa-user', 'text' => $join([$r->holder, $r->country, $r->category ?: $r->visa_type])];
                    }
                    if ($rows->count() > 2) {
                        $lines[] = ['icon' => 'fa-users', 'text' => '+'.($rows->count() - 2).' more applicant'.($rows->count() - 2 === 1 ? '' : 's')];
                    }
                    $put($t, $lines, $full, $split);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('reasonsFor visa_sale: '.$e->getMessage());
        }

        // ── contract file sales ────────────────────────────────────────────
        try {
            if ($fid = $ids('file_sale')) {
                $found = DB::table('contract_file_sales as s')->leftJoin('countries as co', 'co.id', '=', 's.country_id')
                    ->whereIn('s.id', $fid)->get(['s.id', 's.invoice_number', 's.files_count', 's.sale_date', 's.total_amount', 's.payment_status', 'co.name as country'])->keyBy('id');
                foreach ($byType->get('file_sale', collect()) as $t) {
                    if (! ($r = $found->get($t->invoice_id))) {
                        continue;
                    }
                    $put($t, [['icon' => 'fa-folder-open', 'text' => $join([$r->invoice_number, $r->country, $r->files_count ? $r->files_count.' file'.($r->files_count == 1 ? '' : 's') : null])]], [
                        'columns' => ['Invoice', 'Country', 'Files', 'Sold', 'Status', 'Total'],
                        'rows' => [[$r->invoice_number, $r->country, $r->files_count, $dmy($r->sale_date), $r->payment_status ? str_replace('_', ' ', $r->payment_status) : '', $money($r->total_amount)]],
                    ], [
                        'who' => [],
                        'what' => array_values(array_filter([$join([$r->invoice_number, $r->country])])),
                        'how' => $r->files_count ? $r->files_count.' file'.($r->files_count == 1 ? '' : 's') : '',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('reasonsFor file_sale: '.$e->getMessage());
        }

        return [$out, $detail, $facts, $entry];
    }

    private function initials(string $name): string
    {
        $words = array_slice(explode(' ', $name), 0, 2);

        return strtoupper(implode('', array_map(fn ($w) => $w[0] ?? '', $words)));
    }

    private function resolveUserType(User $party): string
    {
        // An agent is charged for the passengers they bring — a receivable,
        // the same side of the ledger as a customer — never paid a commission,
        // so 'agent' does not belong in the supplier bucket.
        if ($party->hasRole('vendor')) {
            return 'supplier';
        }

        return 'customer';
    }
}
