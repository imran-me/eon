<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Account;
use App\Models\Bank;
use App\Models\BankTransfer;
use App\Models\Company;
use App\Models\Department;
use App\Models\FinancingCapital;
use App\Models\FinancingCategory;
use App\Models\FinancingLoan;
use App\Models\FinancingSchedule;
use App\Models\FinancingTransaction;
use App\Models\InterCompanyLiability;
use App\Models\InterCompanyLiabilityTransaction;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\User;
use App\Services\FinancingPostingService;
use App\Services\FinancingScheduleService;
use App\Services\InterCompanyLiabilityService;
use App\Services\LoanBookService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Capital & Financing — the loan book.
 *
 * Three books on one desk: money LENT out, money BORROWED, and the EMPLOYEE
 * book mirrored read-only from Payroll (App\Models\Loan). Nothing here writes
 * to the employee book.
 *
 * ── WHAT REACHES THE LEDGER ───────────────────────────────────────────────
 * BORROWED loans and CAPITAL movements post, through
 * App\Services\FinancingPostingService. Neither has any other record in the
 * system, so nothing else could be counting them, and left unposted a bank loan
 * simply does not appear as a debt while the bank still expects it back.
 *
 * The LENT book still posts nothing, and that is not an omission: a client
 * instalment plan was already booked to 1311 Customer Receivable by the sale
 * itself (App\Traits\PostsSaleJournal), so recording it again as a loan asset
 * would show twice what the client owes. The original rule and its reasoning are
 * on the create_financing_tables migration; this is the half of it that survives
 * scrutiny, and the posting service documents which entries it writes and why.
 *
 * ── THIS CONTROLLER ASSEMBLES NO ENTRIES ──────────────────────────────────
 * Every line, every balance check and every idempotency guard lives in the
 * service. What the controller adds is the refusal: a payment that OUGHT to
 * reach the books but cannot — no posting account on the loan, no account
 * named, a bank with no ledger account behind it — is rejected before anything
 * is written, naming the thing to fix. Saving it quietly would leave the desk
 * and the ledger disagreeing, and nobody finds that until the month is closed.
 */
// Implements HasMiddleware WITHOUT extending the base Controller, exactly as
// AccountController and JournalController do: the framework's Controller has a
// non-static middleware(), which cannot coexist with the static one this
// interface requires.
class FinancingController implements HasMiddleware
{
    public function __construct(private FinancingPostingService $posting)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view financing|view all financing', only: ['index', 'show', 'printCapital', 'sendCapitalStatement', 'printLiability']),
            new Middleware('permission:create financing', only: ['store', 'recordPayment', 'recordDrawdown', 'recordOpeningBalance', 'storeCapital', 'storeCategory', 'settleLiability']),
            new Middleware('permission:edit financing', only: ['update', 'regenerate', 'updateCategory']),
            new Middleware('permission:delete financing', only: ['destroy', 'destroyCapital', 'destroyCategory']),
        ];
    }

    /**
     * The book being viewed.
     *   borrowed / lent — financing_loans, with a schedule
     *   employee        — the read-only payroll mirror
     *   capital         — owner money in and out, no schedule
     *   liability       — what one company owes another because its expense was
     *                     paid from that company's bank account. Tracking only:
     *                     it posts nothing, exactly like the lent book.
     */
    private const BOOKS = ['lent', 'borrowed', 'employee', 'capital', 'categories', 'liability'];

    public function index(Request $request, LoanBookService $loanBook)
    {
        $book = $request->query('book');
        $book = is_string($book) && in_array($book, self::BOOKS, true) ? $book : 'borrowed';

        $user          = Auth::user();
        $canViewAll    = $user->can('view all financing');
        $userCompanyId = $user->company_id;

        // Company scoping, same convention as the account and expense desks:
        // without "view all", a user sees only their own company.
        $companyFilter = function ($query) use ($canViewAll, $userCompanyId, $request) {
            if (! $canViewAll && ! empty($userCompanyId)) {
                return $query->where('company_id', $userCompanyId);
            }

            $requested = $request->query('company_id');
            if (is_string($requested) && $requested !== '') {
                return $query->where('company_id', $requested);
            }

            return $query;
        };

        $loans = collect();
        $activeLoans = collect();
        $loanTxns = collect();
        $bookTotals = $this->bookTotals(collect());
        $staffLoans = collect();
        $staffFold = collect();
        $staffTxns = collect();
        $staffBalances = [];
        $showCompany = false;
        $capital = collect();
        $capitalPosting = collect();
        $capitalPeople = collect();
        $capitalPerson = null;
        $capitalOwner = null;
        $capitalOwnerId = null;
        $capitalLatest = null;
        $capitalBalanceById = [];
        $capitalOwnerUsers = collect();
        $capitalTotals = ['investment' => 0, 'drawings' => 0, 'net' => 0, 'count' => 0];
        $categoryTree = collect();
        $liabilities = collect();
        $settledLiabilities = collect();
        $liabilityTxns = collect();
        $liabilityTotals = ['count' => 0, 'active' => 0, 'raised' => 0, 'settled' => 0, 'outstanding' => 0, 'pairs' => 0];
        $liabilityPairs = [];
        $liabilityReady = false;
        // Defaulted with the rest, not inside the ready() branch: compact()
        // silently omits an unset variable, and the view would then throw
        // "Undefined variable $bankTransfers" on exactly the server where the
        // migration has not run — the one case the ready() guard exists to
        // render safely.
        $bankTransfers = collect();

        if ($book === 'categories') {
            // The whole taxonomy in one query, children eager loaded, so the
            // setup screen renders the tree without an N+1.
            $categoryTree = FinancingCategory::topLevel()
                ->with(['children' => fn ($q) => $q->withCount('loans')])
                ->withCount('loans')
                ->orderBy('sort_order')->orderBy('name')
                ->get();
        } elseif ($book === 'capital') {
            // Owner money in and out. No schedule, no interest — a plain
            // register, newest first.
            $query = $companyFilter(FinancingCapital::query()->with(['company:id,name', 'bank:id,name', 'owner:id,name,employee_id_no']));

            $capitalOwnerUsers = User::query()
                ->select('id', 'name', 'employee_id_no', 'phone', 'email', 'company_id')
                ->whereIn('name', ['Md Mohsin', 'Md Parvez Hossain', 'Nayeemur Rahman', 'Mohammad Fahim'])
                ->where(function ($q) {
                    $q->where('is_super_admin', 0)->orWhereNull('is_super_admin');
                })
                ->where(function ($q) {
                    $q->where('status', 'active')->orWhereNull('status');
                })
                ->orderBy('name')
                ->get();

            if (is_string($search = $request->query('q')) && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('person_name', 'like', $term)
                      ->orWhere('reason', 'like', $term)
                      ->orWhere('reference', 'like', $term);
                });
            }

            if ($request->filled('date_from')) {
                $query->whereDate('date', '>=', $request->query('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('date', '<=', $request->query('date_to'));
            }

            $reversedCapitalIds = JournalEntry::query()
                ->where('source', 'financing_capital')
                ->whereNull('reversed_journal_entry_id')
                ->whereHas('reversedBy')
                ->pluck('source_id')
                ->filter()
                ->all();

            $activeCapitalQuery = clone $query;

            if ($reversedCapitalIds) {
                $activeCapitalQuery->whereNotIn('id', $reversedCapitalIds);
            }

            $capitalPeople = (clone $activeCapitalQuery)
                ->select('person_name')
                ->selectRaw('MAX(party_id) as party_id')
                ->selectRaw("SUM(CASE WHEN kind = 'investment' THEN amount ELSE 0 END) as investment_total")
                ->selectRaw("SUM(CASE WHEN kind = 'drawings' THEN amount ELSE 0 END) as drawings_total")
                ->selectRaw('COUNT(*) as movement_count')
                ->selectRaw('MAX(date) as last_movement_at')
                ->groupBy('person_name')
                ->orderByRaw("SUM(CASE WHEN kind = 'investment' THEN amount ELSE -amount END) DESC")
                ->get();

            if (is_string($kind = $request->query('kind')) && in_array($kind, ['investment', 'drawings'], true)) {
                $query->where('kind', $kind);
                $activeCapitalQuery->where('kind', $kind);
            }

            if ($request->filled('owner_id')) {
                $capitalOwnerId = (int) $request->query('owner_id');
                $capitalOwner = $capitalOwnerUsers->firstWhere('id', $capitalOwnerId);
                abort_unless($capitalOwner, 404);
                $capitalPerson = $capitalOwner->name;
                $ownerScope = function ($ownerQuery) use ($capitalOwnerId, $capitalPerson) {
                    $ownerQuery->where('party_id', $capitalOwnerId)
                        ->orWhere(function ($legacy) use ($capitalPerson) {
                            $legacy->whereNull('party_id')->where('person_name', $capitalPerson);
                        });
                };
                $query->where($ownerScope);
                $activeCapitalQuery->where($ownerScope);
            } elseif (is_string($person = $request->query('person')) && trim($person) !== '') {
                $capitalPerson = trim($person);
                $query->where('person_name', $capitalPerson);
                $activeCapitalQuery->where('person_name', $capitalPerson);
            }

            $capitalTotals = [
                'investment' => (float) (clone $activeCapitalQuery)->where('kind', 'investment')->sum('amount'),
                'drawings' => (float) (clone $activeCapitalQuery)->where('kind', 'drawings')->sum('amount'),
                'count' => (int) (clone $activeCapitalQuery)->count(),
            ];
            $capitalTotals['net'] = $capitalTotals['investment'] - $capitalTotals['drawings'];
            $capitalLatest = (clone $activeCapitalQuery)
                ->orderByDesc('date')->orderByDesc('transaction_time')->orderByDesc('id')
                ->first();
            $runningCapital = (float) $capitalTotals['net'];
            foreach ((clone $query)->orderByDesc('date')->orderByDesc('transaction_time')->orderByDesc('id')->get(['id', 'kind', 'amount']) as $movement) {
                $capitalBalanceById[$movement->id] = ['closing' => $runningCapital];
                if (! in_array($movement->id, $reversedCapitalIds, true)) {
                    $runningCapital -= $movement->kind === 'investment' ? (float) $movement->amount : -(float) $movement->amount;
                }
                $capitalBalanceById[$movement->id]['opening'] = $runningCapital;
            }
            $capital = $query->orderBy('date', 'desc')->orderBy('id', 'desc')
                             ->paginate(20)->withQueryString();
            $capitalPosting = JournalEntry::query()
                ->with('reversedBy:id,reversed_journal_entry_id')
                ->where('source', 'financing_capital')
                ->whereIn('source_id', $capital->getCollection()->pluck('id'))
                ->whereNull('reversed_journal_entry_id')
                ->get()->keyBy('source_id');
        } elseif ($book === 'employee') {
            // Read-only mirror of the payroll desk, in the same three tables it
            // uses: the fold by person, the full register, and the movement
            // trail. Deliberately separate queries against payroll's own tables
            // — this screen never writes them.
            //
            // Three paginators on one page, so each needs its own page name or
            // clicking page 2 on one would move all three.
            // user.company is loaded because all three tables carry a Company
            // column when the page spans more than one — company_id must be in
            // the user select or the relation cannot resolve.
            $staffLoans = $companyFilter(
                Loan::query()->with(['user:id,name,company_id', 'user.company:id,name', 'transactions'])
            )->latest('id')->paginate(15, ['*'], 'reg')->withQueryString();

            // The fold and the running balances come from payroll's OWN service,
            // not a second implementation here. byEmployee() already folds the
            // book per person with the salary-vs-cash split, and
            // runningBalances() answers "what was left after that movement?",
            // which the amount column alone can never reach. Reusing them is
            // what keeps this mirror from disagreeing with the desk it mirrors.
            //
            // Both read the WHOLE book, not one page: a per-person total that
            // counted only the rows on screen would be wrong.
            $wholeBook = $companyFilter(
                Loan::query()->with(['user:id,name,company_id', 'user.company:id,name', 'transactions.bank:id,name'])
            )->get();

            $staffFold     = $loanBook->byEmployee($wholeBook);
            $staffBalances = $loanBook->runningBalances($wholeBook);

            // The Company column earns its place only when the page actually
            // spans more than one — otherwise every row repeats the same chip
            // and costs a column doing it. Same test payroll applies.
            $showCompany = $wholeBook->pluck('user.company_id')->filter()->unique()->count() > 1;

            $staffTxns = LoanTransaction::query()
                ->with(['user:id,name,company_id', 'user.company:id,name', 'loan:id,amount,start_date', 'bank:id,name'])
                ->when(! $canViewAll && ! empty($userCompanyId), fn ($q) => $q->whereHas(
                    'user', fn ($u) => $u->where('company_id', $userCompanyId)
                ))
                ->latest('date')->latest('id')
                ->paginate(15, ['*'], 'txn')->withQueryString();
        } elseif ($book === 'liability') {
            /*
             * What one company owes another because its expense was settled from
             * that company's bank account. Same three tables as the loan books —
             * active, register, movements — because it is the same question.
             *
             * Nothing here posts. See the create_inter_company_liability_tables
             * migration for the rule and, more importantly, for what it does not
             * fix: the ledger still credits the paying company's bank inside an
             * entry stamped with the spending company's id.
             *
             * The service answers ready() false until the migration has run, and
             * the branch simply renders an empty desk rather than 500ing — the
             * deploy window must not take Capital & Financing down for the other
             * eleven companies.
             */
            $liabilityService = app(InterCompanyLiabilityService::class);
            $liabilityReady = $liabilityService->ready();

            if ($liabilityReady) {
                // Scoped on BOTH sides: a company-locked user is party to a debt
                // whether they owe it or are owed it, and seeing only one half
                // would make the desk lie about their position.
                $scope = function ($query) use ($canViewAll, $userCompanyId, $request) {
                    if (! $canViewAll && ! empty($userCompanyId)) {
                        return $query->forCompany($userCompanyId);
                    }

                    $requested = $request->query('company_id');
                    if (is_string($requested) && $requested !== '') {
                        return $query->forCompany($requested);
                    }

                    return $query;
                };

                $filtered = function () use ($scope, $request) {
                    $query = $scope(
                        InterCompanyLiability::query()
                            ->with(['debtor:id,name,short_name', 'creditor:id,name,short_name', 'bank:id,name', 'expense:id,title'])
                    );

                    if (is_string($search = $request->query('q')) && trim($search) !== '') {
                        $term = '%' . trim($search) . '%';
                        $query->where(function ($q) use ($term) {
                            $q->where('title', 'like', $term)
                              ->orWhere('reference', 'like', $term)
                              ->orWhere('notes', 'like', $term);
                        });
                    }

                    if ($request->filled('date_from')) {
                        $query->whereDate('incurred_on', '>=', $request->query('date_from'));
                    }

                    if ($request->filled('date_to')) {
                        $query->whereDate('incurred_on', '<=', $request->query('date_to'));
                    }

                    return $query;
                };

                // 1 · Active — still owed, in whole or in part.
                $liabilities = $filtered()->active()
                    ->orderByDesc('incurred_on')->orderByDesc('id')
                    ->paginate(10, ['*'], 'act')->withQueryString();

                // 2 · Register — settled, written off or cancelled.
                $settledLiabilities = $filtered()->closed()
                    ->orderByDesc('incurred_on')->orderByDesc('id')
                    ->paginate(15, ['*'], 'reg')->withQueryString();

                // 3 · Every movement, newest first.
                $liabilityTxns = InterCompanyLiabilityTransaction::query()
                    ->with([
                        'liability:id,debtor_company_id,creditor_company_id,title,amount',
                        'liability.debtor:id,name,short_name',
                        'liability.creditor:id,name,short_name',
                        'bankTransfer:id,reference_no,amount',
                    ])
                    ->whereIn('inter_company_liability_id', $filtered()->select('inter_company_liabilities.id'))
                    ->orderByDesc('date')->orderByDesc('id')
                    ->paginate(15, ['*'], 'txn')->withQueryString();

                // Totals and the pair fold read the WHOLE book, never one page —
                // the same rule the loan books' position strip follows.
                $wholeBook = $filtered()->get();
                $liabilityTotals = $liabilityService->totals($wholeBook);
                $liabilityPairs  = $liabilityService->pairPositions($wholeBook);

                // Offered on the settle form so a payment can point at the
                // transfer that actually moved the money. Recent ones only —
                // a settlement is recorded within days of the transfer, and a
                // picker holding every transfer ever made is not a picker.
                $bankTransfers = BankTransfer::query()
                    ->whereNull('deleted_at')
                    ->orderByDesc('payment_date')->orderByDesc('id')
                    ->limit(100)
                    ->get(['id', 'reference_no', 'amount', 'payment_date']);
            }
        } else {
            /*
             * Three tables on one page, the same shape the employee book uses:
             *
             *   1 · Active     — what still needs attention. Doubles as the EMI
             *                    view: next due, paid so far, left to pay, Repay.
             *   2 · Register   — what is FINISHED. A loan leaves the first table
             *                    the moment it settles and lands here, so the two
             *                    never show the same row and neither has to be
             *                    read past. Written-off and cancelled belong here
             *                    too: they are equally done with, and without a
             *                    home they would vanish from the desk entirely.
             *   3 · Movements  — every payment and taking across the whole book,
             *                    so "what happened last week" is one look rather
             *                    than a walk through every loan.
             *
             * Each is built from the SAME filters, so a search or a company
             * switch moves all three together and they can never disagree about
             * which book is on screen. Each also needs its own page name, or
             * clicking page 2 on one would page all three.
             */
            $filtered = function () use ($book, $request, $companyFilter) {
                $query = $companyFilter(
                    FinancingLoan::query()
                        ->with(['company:id,name', 'department:id,name', 'schedules', 'transactions'])
                        ->where('direction', $book)
                );

                if (is_string($status = $request->query('status')) && $status !== '') {
                    $query->where('status', $status);
                }

                // Whose debt — only offered on the borrowed book.
                if ($book === 'borrowed'
                    && is_string($for = $request->query('for'))
                    && in_array($for, ['company', 'personal'], true)) {
                    $query->where('taken_for', $for);
                }

                // The shape of the arrangement: a fixed loan or a running
                // account. Two genuinely different things sharing one table.
                if (is_string($shape = $request->query('shape'))
                    && in_array($shape, ['term', 'running'], true)) {
                    $query->where('loan_shape', $shape);
                }

                if (is_string($search = $request->query('q')) && trim($search) !== '') {
                    $term = '%' . trim($search) . '%';
                    $query->where(function ($q) use ($term) {
                        $q->where('counterparty_name', 'like', $term)
                          ->orWhere('kind', 'like', $term)
                          ->orWhere('account_no', 'like', $term)
                          ->orWhere('purpose', 'like', $term);
                    });
                }

                return $query;
            };

            // 1 · Active. Sorted by the caller's choice like the register, so the
            // two tables stay comparable when someone sorts one of them.
            $activeQuery = $filtered()->where('status', 'active');
            $this->applySort($activeQuery, $request);
            $activeLoans = $activeQuery->paginate(10, ['*'], 'act')->withQueryString();

            // 2 · Register — the finished ones only. `!= active` rather than
            // `= closed` so a written-off or cancelled arrangement still has
            // somewhere to be; excluded from both tables it would disappear from
            // the desk while remaining perfectly real in the database.
            $registerQuery = $filtered()->where('status', '!=', 'active');
            $this->applySort($registerQuery, $request);
            $loans = $registerQuery->paginate(15, ['*'], 'reg')->withQueryString();

            // 3 · Movements across the whole book, newest first.
            $loanTxns = FinancingTransaction::query()
                ->with(['loan:id,counterparty_name,direction,taken_for,loan_shape,company_id', 'loan.company:id,name', 'bank:id,name'])
                ->whereIn('financing_loan_id', $filtered()->select('id'))
                ->orderByDesc('date')->orderByDesc('id')
                ->paginate(15, ['*'], 'txn')->withQueryString();

            // The position strip reads the WHOLE book, never one page: a total
            // that counted only the rows on screen would be wrong in the one way
            // that matters, quietly.
            $bookTotals = $this->bookTotals($filtered()->get());
        }

        $companies = $canViewAll
            ? Company::orderBy('name')->get(['id', 'name'])
            : Company::where('id', $userCompanyId)->get(['id', 'name']);

        // company_id and account_number ride along for the Liability book's settle
        // form, which offers the debtor's accounts to pay FROM and the creditor's to
        // pay INTO — it cannot split the list without knowing whose each one is.
        $banks = Bank::where('status', 1)
            ->with('company:id,name,short_name')
            ->orderBy('company_id')
            ->orderBy('name')
            ->get(['id', 'name', 'company_id', 'account_number', 'type', 'balance']);
        $departments = Department::orderBy('name')->get(['id', 'name']);

        // Chart of accounts for the posting hints. Shared accounts only
        // (company_id null) plus the user's own — a shared posting account
        // written by another company breaks that company's trial balance, so
        // the picker must not offer one.
        $glAccounts = Account::where('status', 1)
            ->where(function ($q) use ($userCompanyId) {
                $q->whereNull('company_id');
                if (! empty($userCompanyId)) {
                    $q->orWhere('company_id', $userCompanyId);
                }
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->groupBy('type');

        // Offered on the create form, scoped to the book being viewed.
        $categories = in_array($book, ['borrowed', 'lent'], true)
            ? FinancingCategory::topLevel()->active()->forBook($book)
                ->with(['children' => fn ($q) => $q->active()])
                ->orderBy('sort_order')->orderBy('name')->get()
            : collect();

        return view('financing.index', compact(
            'book', 'loans', 'activeLoans', 'loanTxns', 'bookTotals',
            'staffLoans', 'staffFold', 'staffTxns', 'staffBalances', 'showCompany',
            'capital', 'capitalTotals', 'capitalPosting', 'capitalPeople', 'capitalPerson', 'capitalOwner', 'capitalOwnerId', 'capitalLatest', 'capitalBalanceById', 'capitalOwnerUsers', 'categoryTree',
            'liabilities', 'settledLiabilities', 'liabilityTxns', 'bankTransfers',
            'liabilityTotals', 'liabilityPairs', 'liabilityReady',
            'companies', 'banks', 'departments', 'categories', 'glAccounts'
        ));
    }

    /**
     * The position of a whole book, in the figures people actually ask for.
     *
     * Read from the full book, never the page on screen. A total that quietly
     * meant "this page only" is the kind of wrong that gets believed, because it
     * looks like an answer and nothing on the page says otherwise.
     *
     * `outstanding` counts ACTIVE arrangements alone — a settled loan is worth
     * nothing outstanding by definition, and including closed rows would keep
     * reporting debt that was paid off years ago.
     */
    private function bookTotals($loans): array
    {
        $active = $loans->where('status', 'active');

        // Instalment arrears and what falls due soon, walked once. Both come
        // from the schedules the register already eager-loads, so this costs no
        // extra query — and both are read from ACTIVE loans only, since a
        // settled loan cannot be late for anything.
        $horizon    = now()->addDays(30);
        $overdueAmt = 0.0;
        $due30      = 0.0;

        foreach ($active as $l) {
            foreach ($l->schedules as $s) {
                if (! in_array($s->status, ['due', 'partial'], true) || ! $s->due_date) {
                    continue;
                }

                if ($s->due_date->isPast()) {
                    $overdueAmt += (float) $s->balance;
                } elseif ($s->due_date->lte($horizon)) {
                    $due30 += (float) $s->balance;
                }
            }
        }

        // What the borrowing COSTS over its whole life, which principal alone
        // never shows. Weighted by principal, not averaged flat: a ৳50m loan at
        // 9% and a ৳50k loan at 20% do not average to 14.5% in any useful sense.
        $principalSum = (float) $loans->sum(fn ($l) => (float) $l->principal);

        return [
            'interest'     => round($loans->sum(fn ($l) => (float) $l->schedules->sum('interest_component')), 2),
            'rateWeighted' => $principalSum > 0
                ? round($loans->sum(fn ($l) => (float) $l->principal * (float) $l->interest_rate) / $principalSum, 2)
                : 0.0,
            'overdueAmount' => round($overdueAmt, 2),
            'due30'         => round($due30, 2),
            'count'       => $loans->count(),
            'active'      => $active->count(),
            'running'     => $loans->where('loan_shape', 'running')->count(),
            // What the whole book was ever worth: agreed sums, plus everything
            // taken on the running accounts, which have no agreed sum at all.
            'exposure'    => round($loans->sum(fn ($l) => $l->exposure), 2),
            'settled'     => round($loans->sum(fn ($l) => $l->settled_amount), 2),
            'outstanding' => round($active->sum(fn ($l) => $l->outstanding), 2),
            // Split out because it is NOT the company's money either way — it
            // must never be added into what the company owes.
            'personal'    => round($active->filter(fn ($l) => $l->is_personal)->sum(fn ($l) => $l->outstanding), 2),
            'emi'         => round($active->sum(fn ($l) => (float) $l->instalment_amount), 2),
            'overdue'     => (int) $active->sum(fn ($l) => $l->overdue_count),
            'nextDue'     => $active->map(fn ($l) => $l->next_due?->due_date)->filter()->sort()->first(),
        ];
    }

    /**
     * Sorting, whitelisted. The key is matched against this list before use, so
     * nothing from the URL reaches the query as SQL, and a non-string (an array
     * from ?sort[]=) is treated as no sort rather than raising a PHP warning
     * that would surface as a 500.
     */
    private function applySort($query, Request $request): void
    {
        $sortRaw = $request->query('sort');
        $dirRaw  = $request->query('dir');
        $sortKey = is_string($sortRaw) ? $sortRaw : '';
        $sortDir = (is_string($dirRaw) && strtolower($dirRaw) === 'asc') ? 'asc' : 'desc';

        $sortable = ['counterparty_name', 'kind', 'principal', 'interest_rate', 'start_date', 'status'];

        if (in_array($sortKey, $sortable, true)) {
            $query->orderBy($sortKey, $sortDir);
        } else {
            $query->orderBy('start_date', 'desc');
        }

        // Unique tiebreak, so rows cannot repeat or vanish between pages when
        // the sorted value is shared.
        $query->orderBy('id', 'desc');
    }

    public function show(Request $request, $role, FinancingLoan $financing)
    {
        $this->authorizeCompany($financing);

        $financing->load(['company:id,name', 'department:id,name', 'creator:id,name', 'glAccount:id,code,name', 'glInterestAccount:id,code,name', 'schedules', 'transactions.bank:id,name']);

        // Where a repayment can be paid from. Scoped to the loan's own company
        // so a repayment cannot be drawn from another company's account, which
        // would post a credit into books the debit never reaches. A personal
        // loan has no company of its own, so it is offered every account.
        //
        // company_id, account_number and type are selected because
        // partials/bank-options reads them to build the label every other money
        // form in the ERP shows — "1265 · THE CITY BANK- Travels — EPAL GROUP".
        // Selecting only id and name did not break the picker, it silently
        // degraded it: with no company_id the company relation resolved to null
        // and EVERY row read "— No company", and with no account_number the
        // last four digits vanished — the one thing that tells two accounts at
        // the same bank apart. The company is eager-loaded for the same reason:
        // without it the partial falls back to a query per row.
        $banks = Bank::where('status', 1)
            ->when($financing->company_id, fn ($q) => $q->where('company_id', $financing->company_id))
            ->with('company:id,name,short_name')
            ->orderBy('name')
            ->get(['id', 'name', 'company_id', 'account_number', 'type']);

        // Whether saving a payment will write a journal entry — the same test
        // the posting service applies, so the form cannot promise what the
        // server will not do.
        //
        // `! is_personal` rather than `taken_for === 'company'` deliberately:
        // the service routes on is_personal, so a borrowing with taken_for left
        // null posts as a company liability. Testing for 'company' here would
        // have shown "desk only" on a payment that posted — the exact mismatch
        // this flag exists to prevent.
        //
        // The lent half is lentPosts(), the SAME call recordPayment() makes to
        // decide whether bank_id is required. It was missing, and the flag read
        // false on every loan given: the account field lost its asterisk while
        // the server still refused a blank one, and the form printed "recorded
        // on the desk only — posting again would count it twice" over a payment
        // that was posting. Loan #8's two collections sit in the ledger as
        // FINRECV-00016 and FINRECV-00020 with the page denying both.
        $postsToBooks = ($financing->direction === 'borrowed'
                && ! $financing->is_personal
                && ! empty($financing->company_id))
            || $this->posting->lentPosts($financing);

        return view('financing.show', [
            'loan'         => $financing,
            'banks'        => $banks,
            'postsToBooks' => $postsToBooks,
        ]);
    }

    public function store(Request $request, FinancingScheduleService $scheduler)
    {
        /*
         * The borrowed form asks one question with four answers — company or
         * personal, fixed or running — because the two are always decided
         * together at the counter. They arrive joined as "personal:running" and
         * are split back here, BEFORE validation, so every rule and every line
         * below sees the two independent fields it was written against and
         * nothing downstream has to know they travelled as one.
         *
         * Split defensively: a value that is not one of the four is dropped
         * rather than trusted, and the validator then rejects the request for
         * the missing field. A hand-crafted "taken_for" of anything else must
         * not survive by slipping past explode().
         */
        if (is_string($arrangement = $request->input('arrangement'))
            && substr_count($arrangement, ':') === 1) {
            [$for, $shape] = explode(':', $arrangement, 2);

            if (in_array($for, ['company', 'personal'], true) && in_array($shape, ['term', 'running'], true)) {
                $request->merge(['taken_for' => $for, 'loan_shape' => $shape]);
            }
        }

        // Whose debt it is, read first: it decides which of the fields below are
        // required at all. Only the borrowed book asks — a loan we gave has no
        // such distinction.
        $isPersonal = $request->input('direction') === 'borrowed'
            && $request->input('taken_for') === 'personal';

        /*
         * A running account has no agreed sum and no end date — the balance is
         * whatever has been taken less whatever has come back. So the three
         * fields a term loan cannot exist without (principal, tenure, interest
         * method) are not asked for here, and are not merely optional: asking
         * would invite someone to type a number that every later screen would
         * then report as the debt.
         */
        $isRunning = $request->input('loan_shape') === 'running';

        $data = $request->validate([
            // Which of the two shapes this is. Required, because inferring it
            // later from "tenure is empty" would make every screen repeat the
            // same guess and eventually one of them would guess differently.
            'loan_shape'        => ['required', Rule::in(['term', 'running'])],
            'direction'         => ['required', Rule::in(['lent', 'borrowed'])],
            // A personal loan belongs to no company: it is not the group's
            // liability and must not sit on any company's books. Null here is
            // also what keeps it out of a company-locked user's register.
            'company_id'        => [$isPersonal ? 'nullable' : 'required', 'exists:companies,id'],
            'taken_for'         => ['nullable', Rule::in(['company', 'personal'])],
            'personal_of'       => [$isPersonal ? 'required' : 'nullable', 'string', 'max:191'],
            'department_id'     => ['nullable', 'exists:departments,id'],
            'counterparty_name' => ['required', 'string', 'max:191'],
            'kind'              => ['nullable', 'string', 'max:100'],
            'category_id'       => ['nullable', 'exists:financing_categories,id'],
            'sub_category_id'   => ['nullable', 'exists:financing_categories,id'],
            'account_no'        => ['nullable', 'string', 'max:100'],
            // What the client settled up front, before the balance was financed.
            'down_payment'      => ['nullable', 'numeric', 'min:0'],
            'linked_label'      => ['nullable', 'string', 'max:191'],
            'principal'         => [$isRunning ? 'nullable' : 'required', 'numeric', 'min:0.01'],
            // Deducted at source by the lender, so it never reaches the account —
            // but the full principal is still owed. `lt:principal` because a fee
            // that swallows the whole loan means one of the two figures is wrong.
            // Skipped entirely on a running account: there is no principal for it
            // to be less than, and the rule would fail on its own missing input.
            'processing_fee'    => $isRunning
                ? ['nullable', 'numeric', 'min:0']
                : ['nullable', 'numeric', 'min:0', 'lt:principal'],
            // Where the money landed. Only asked on a borrowing in the company's
            // own name, which is the only case that puts a debt on the books and
            // therefore the only case with a cash leg to write.
            'disbursement_bank_id' => ['nullable', 'exists:banks,id'],
            'interest_rate'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'interest_method'   => [$isRunning ? 'nullable' : 'required', Rule::in(['none', 'flat', 'reducing'])],
            'tenure_months'     => [$isRunning ? 'nullable' : 'required', 'integer', 'min:1', 'max:600'],
            'start_date'        => ['required', 'date'],
            'purpose'           => ['nullable', 'string'],
            'security'          => ['nullable', 'string'],
            'gl_account_id'         => ['nullable', 'exists:accounts,id'],
            'gl_interest_account_id'=> ['nullable', 'exists:accounts,id'],
            'notes'             => ['nullable', 'string'],
        ]);

        // Blank out what the other branch's fields would otherwise carry over,
        // so a form filled in one mode and switched cannot leave a department on
        // a personal loan, or a company on a loan that belongs to nobody.
        if ($isPersonal) {
            $data['company_id'] = null;
            $data['department_id'] = null;
            // Enforced here and not only in the form: the money went into his own
            // account, so there is no company cash leg. A value surviving from a
            // switched form would post a liability for a debt the company does not
            // carry — the exact error the personal/company split exists to stop.
            $data['disbursement_bank_id'] = null;
        } else {
            $data['personal_of'] = null;
        }

        // A fee deducted at source is something a LENDER does to us. On the lent
        // book the same field would mean a charge we levied on a borrower, which
        // is income and a different account entirely — so it is not carried over
        // from a form that was filled in one mode and switched to the other.
        if ($request->input('direction') !== 'borrowed') {
            $data['processing_fee'] = 0;
        }

        // Assigned, not merged with `+`. All three columns are NOT NULL with a
        // default of 0, and an empty number input arrives as null (the framework
        // converts empty strings), which `nullable` lets through. `$data + [...]`
        // gives the LEFT side precedence, so a present-but-null key survived the
        // fallback and reached the insert as null — MySQL runs in strict mode
        // here, so leaving "Paid up front" blank on a lent loan failed outright
        // with "Column 'down_payment' cannot be null".
        $data['interest_rate']  = $data['interest_rate'] ?? 0;
        $data['down_payment']   = $data['down_payment'] ?? 0;
        $data['processing_fee'] = $data['processing_fee'] ?? 0;

        if ($isRunning) {
            /*
             * Forced to zero and null rather than left to whatever the form sent.
             *
             * `principal` is NOT NULL in the table, so it has to hold something —
             * and 0 is the only honest something. The balance on a running
             * account is derived from its movements (FinancingLoan::$exposure),
             * so a non-zero principal here would be counted on TOP of everything
             * taken, and the account would open already owing money nobody lent.
             *
             * The rest are cleared for the same reason: a tenure or an instalment
             * carried over from a form filled in term mode would put due dates on
             * an arrangement that has none, and the register would start calling
             * them overdue.
             */
            $data['principal']            = 0;
            $data['tenure_months']        = null;
            $data['instalment_amount']    = null;
            $data['interest_method']      = 'none';
            $data['interest_rate']        = 0;
            $data['processing_fee']       = 0;
            $data['down_payment']         = 0;
            // Each taking names the account it came from; the loan itself never
            // sees a single disbursement, so it must not claim one.
            $data['disbursement_bank_id'] = null;
        }

        try {
            [$loan, $posted] = DB::transaction(function () use ($data, $scheduler, $isRunning) {
                $loan = FinancingLoan::create($data + [
                    'created_by' => Auth::id(),
                    'status'     => 'active',
                ]);

                // No schedule on a running account — there is nothing to
                // schedule. Generating one would invent instalments and then
                // report them as missed.
                if (! $isRunning) {
                    $scheduler->generate($loan);
                }

                // Inside the same transaction on purpose: a loan on the desk whose
                // liability never reached the ledger is worse than no loan at all,
                // because every later instalment would pay down a debt that is not
                // there. Either both exist or neither does.
                $entry = $this->posting->postLoanSetup($loan);

                return [$loan, $entry];
            });
        } catch (\RuntimeException $e) {
            // The service refuses rather than write a wrong entry — a chart
            // account that is missing, a bank with no ledger account, an entry
            // that does not balance. The transaction is already rolled back, so
            // nothing was saved and the reason can be shown as it was given
            // instead of reaching the user as a 500.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('role.financing.show', [
                'role'      => $request->route('role'),
                'financing' => $loan->id,
            ])
            ->with('success', match (true) {
                $isRunning => 'Running account opened with a nil balance. Use "Record a taking" each time money goes out — every one is posted on its own.',
                (bool) $posted => 'Loan recorded, schedule generated, and posted to the ledger as ' . $posted->reference . '.',
                default => 'Loan recorded and its schedule generated.',
            });
    }

    public function update(Request $request, $role, FinancingLoan $financing)
    {
        $this->authorizeCompany($financing);

        $data = $request->validate([
            'counterparty_name' => ['required', 'string', 'max:191'],
            'kind'              => ['nullable', 'string', 'max:100'],
            'account_no'        => ['nullable', 'string', 'max:100'],
            'purpose'           => ['nullable', 'string'],
            'security'          => ['nullable', 'string'],
            'notes'             => ['nullable', 'string'],
            'status'            => ['required', Rule::in(['active', 'closed', 'written_off', 'cancelled'])],
        ]);

        // Terms are not editable here on purpose: changing principal, rate or
        // tenure changes every instalment, so that goes through regenerate(),
        // which refuses once money has been applied.
        $financing->update($data);

        return back()->with('success', 'Loan updated.');
    }

    /** Rebuild the schedule after a terms change. Refuses if anything is paid. */
    public function regenerate(Request $request, $role, FinancingLoan $financing, FinancingScheduleService $scheduler)
    {
        $this->authorizeCompany($financing);

        $data = $request->validate([
            'principal'       => ['required', 'numeric', 'min:0.01'],
            'interest_rate'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'interest_method' => ['required', Rule::in(['none', 'flat', 'reducing'])],
            'tenure_months'   => ['required', 'integer', 'min:1', 'max:600'],
            'start_date'      => ['required', 'date'],
        ]);

        try {
            $financing->update($data + ['interest_rate' => $data['interest_rate'] ?? 0]);
            $count = $scheduler->generate($financing);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Schedule rebuilt — {$count} instalments.");
    }

    /**
     * Record a fresh taking on a running account.
     *
     * The counterpart to recordPayment(): that one brings money back, this one
     * sends it out. Each taking is its own row and its own journal entry — never
     * an edit to a stored total — because the balance is only defensible if
     * every movement behind it can be pointed at, and the whole reason a running
     * account exists is that the takings are irregular and unplanned.
     *
     * Nothing here happens on its own. The amount, the date and the account are
     * all typed by a person, and nothing is written until they press the button.
     */
    public function recordDrawdown(Request $request, $role, FinancingLoan $financing)
    {
        $this->authorizeCompany($financing);

        // A term loan hands over its principal once, at setup. Letting a second
        // disbursement onto it would raise a debt above what was agreed while
        // the schedule went on measuring against the original figure.
        if (! $financing->is_running) {
            return back()->with('error', 'This is a fixed loan, so money can only go out once, at setup. Only a running account can take more.');
        }

        if ($financing->status !== 'active') {
            return back()->with('error', 'This account is closed. Reopen it before recording anything against it.');
        }

        $data = $request->validate([
            'amount'  => ['required', 'numeric', 'min:0.01'],
            'date'    => ['required', 'date'],
            // Which account the money left. Required, not optional: a taking
            // with no account named cannot be posted, and an unposted taking is
            // cash gone from the bank that the books never heard about.
            'bank_id' => ['required', 'exists:banks,id'],
            'memo'    => ['nullable', 'string', 'max:191'],
        ]);

        $bank = Bank::find($data['bank_id']);

        if (! $bank || empty($bank->account_id)) {
            return back()->withInput()->with('error', 'That account is not linked to a ledger account, so nothing can be posted against it.');
        }

        if (empty($financing->gl_account_id)) {
            return back()->withInput()->with('error',
                'This account has no posting account set, so the taking cannot reach the books. Set "Posts against — principal" on it first — for the boss\'s own account that is ' . config('accounts.director_current_account') . ' Director\'s Current Account.');
        }

        try {
            $posted = DB::transaction(function () use ($data, $financing) {
                $txn = FinancingTransaction::create([
                    'financing_loan_id' => $financing->id,
                    // `disburse` is money going OUT on this arrangement. The
                    // balance accessors read this type and no other, so a taking
                    // filed under any other type would vanish from the balance.
                    'type'              => 'disburse',
                    'date'              => $data['date'],
                    'amount'            => $data['amount'],
                    // A taking settles nothing, so none of the settlement columns
                    // may carry a figure — they are what `settled_amount` sums.
                    'principal_part'    => 0,
                    'interest_part'     => 0,
                    'fee_part'          => 0,
                    'tds_amount'        => 0,
                    'method'            => 'bank',
                    'bank_id'           => $data['bank_id'],
                    'memo'              => $data['memo'] ?? null,
                    'created_by'        => Auth::id(),
                ]);

                $entry = $this->posting->postDrawdown($txn);

                if ($entry) {
                    $txn->update(['journal_entry_id' => $entry->id]);
                }

                return $entry;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $posted
            ? 'Taking recorded and posted to the ledger as ' . $posted->reference . '.'
            : 'Taking recorded on the desk only — nothing was posted.');
    }

    /**
     * Bring forward the balance of a running loan that predates this ERP.
     * No cash moves today; the posting is Opening Balance Equity to liability.
     */
    public function recordOpeningBalance(Request $request, $role, FinancingLoan $financing)
    {
        $this->authorizeCompany($financing);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date'   => ['required', 'date'],
            'memo'   => ['nullable', 'string', 'max:191'],
        ]);

        if ($financing->direction !== 'borrowed'
            || ! $financing->is_running
            || $financing->is_personal
            || empty($financing->company_id)) {
            return back()->withInput()->with('error',
                'Opening liability is available only for a company running loan.');
        }

        if ($financing->status !== 'active') {
            return back()->withInput()->with('error',
                'This account is closed. Reopen it before recording an opening balance.');
        }

        if (empty($financing->gl_account_id)) {
            return back()->withInput()->with('error',
                'Choose the loan liability under "Posts against - principal" before recording an opening balance.');
        }

        try {
            $posted = DB::transaction(function () use ($data, $financing) {
                $lockedLoan = FinancingLoan::query()->lockForUpdate()->findOrFail($financing->id);

                // An opening balance is a starting point, never a later movement.
                // Locking the loan makes the check safe against simultaneous submits.
                if ($lockedLoan->transactions()->exists()) {
                    throw new \RuntimeException(
                        'Opening balance must be the first movement on this loan. This loan already has a movement.'
                    );
                }

                $txn = FinancingTransaction::create([
                    'financing_loan_id' => $lockedLoan->id,
                    'type'              => 'disburse',
                    'date'              => $data['date'],
                    'amount'            => $data['amount'],
                    'principal_part'    => 0,
                    'interest_part'     => 0,
                    'fee_part'          => 0,
                    'tds_amount'        => 0,
                    'method'            => 'adjustment',
                    'bank_id'           => null,
                    'memo'              => $data['memo'] ?? 'Opening liability brought forward',
                    'created_by'        => Auth::id(),
                ]);

                $entry = $this->posting->postOpeningBalance($txn);
                $txn->update(['journal_entry_id' => $entry->id]);

                return $entry;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success',
            'Opening liability recorded without changing any bank balance. Journal: ' . $posted->reference . '.');
    }

    /** Record money moving on a loan, apply it to an instalment, and post it. */
    public function recordPayment(Request $request, $role, FinancingLoan $financing)
    {
        $this->authorizeCompany($financing);

        $data = $request->validate([
            // Required on a term loan, because a payment there always settles
            // an instalment — and left blank it posted to the ledger while every
            // row stayed "Due" and the split into principal and interest never
            // happened. A running account has no schedule, so it stays optional.
            'financing_schedule_id' => [
                $financing->is_running ? 'nullable' : 'required',
                Rule::exists('financing_schedules', 'id')->where('financing_loan_id', $financing->id),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date'   => ['required', 'date'],
            'method' => ['required', Rule::in(['cash', 'bank', 'adjustment'])],
            'bank_id' => ['nullable', 'exists:banks,id'],
            // Whose money left. Asked only on a loan in a person's own name,
            // where it is the difference between "his affair, nothing to record"
            // and "company money went out for his personal benefit". Required
            // there precisely because it cannot be reconstructed later, and left
            // null elsewhere because a company loan is always paid by the company.
            'paid_by' => [Rule::requiredIf($financing->is_personal), 'nullable', Rule::in(['company', 'personal'])],
            // The lender's own charge riding along with the payment — a closure
            // fee on an early settlement, typically. Taken off the top before the
            // rest is applied, so it never looks like principal that was repaid.
            'fee_part' => ['nullable', 'numeric', 'min:0', 'lt:amount'],
            // Tax withheld from interest paid to a person — a director who lent
            // the company money. Withheld FROM the payment, so what leaves the
            // bank is amount minus this; the difference is held for the NBR.
            'tds_amount' => ['nullable', 'numeric', 'min:0', 'lt:amount'],
            'memo'   => ['nullable', 'string', 'max:191'],
        ], [
            'financing_schedule_id.required' => 'Choose which instalment this payment settles — on a fixed loan every payment settles one, and that is also what splits it into principal and interest.',
        ]);

        /*
         * Will this payment reach the ledger, and if it should, can it?
         *
         * Three outcomes, and the difference between them has to be settled
         * here rather than discovered inside the service:
         *
         *   LIABILITY  a borrowing in the company's name. The debt comes down
         *              and the interest is a real cost.
         *   DRAWING    a loan in a person's name that a COMPANY account paid.
         *              Money left the business for someone's private benefit —
         *              one line to Drawings, never split, never an expense.
         *   NOTHING    lent money (already on the books from the sale), an
         *              instalment the person paid himself, or an adjustment.
         *
         * An adjustment moves no money by definition, so it can never post
         * whatever else is true — which is also why it is exempt from every
         * guard below.
         */
        $isAdjustment = $data['method'] === 'adjustment';

        $postsAsLiability = ! $isAdjustment
            && $financing->direction === 'borrowed'
            && ! $financing->is_personal
            && ! empty($financing->company_id);

        $postsAsDrawing = ! $isAdjustment
            && $financing->is_personal
            && ($data['paid_by'] ?? null) === 'company';

        // Money coming back on a loan we gave, where real cash went out to
        // create it — the boss settling his current account. The service decides
        // which lent loans those are; the guard only has to match it.
        $postsAsReceipt = ! $isAdjustment && $this->posting->lentPosts($financing);

        if ($postsAsLiability && empty($financing->gl_account_id)) {
            return back()->withInput()->with('error',
                'This loan has no posting account set, so the repayment cannot reach the books. Set "Posts against — principal" on the loan first.');
        }

        if ($postsAsLiability || $postsAsDrawing || $postsAsReceipt) {
            if (empty($data['bank_id'])) {
                return back()->withInput()->with('error', match (true) {
                    $postsAsDrawing => 'Company money paid this instalment, so the account it left has to be named.',
                    $postsAsReceipt => 'Name the account the money came back into, or the repayment cannot reach the books.',
                    default         => 'Choose which bank or cash account the money came from.',
                });
            }

            $bank = Bank::find($data['bank_id']);

            if (! $bank || empty($bank->account_id)) {
                return back()->withInput()->with('error',
                    'That account is not linked to a ledger account, so nothing can be posted against it.');
            }

            // Both legs must land in the same set of books. On a company loan
            // that is the loan's own company; paying it from another company's
            // account would post a credit into books the debit never reaches.
            if ($postsAsLiability && $bank->company_id && (int) $bank->company_id !== (int) $financing->company_id) {
                return back()->withInput()->with('error',
                    'That account belongs to another company. A repayment has to be paid from the company the loan sits under.');
            }

            // A personal loan has no company of its own, so the entry belongs to
            // whichever company's money actually left. Without one there is no
            // set of books to put the withdrawal in — the service throws on this,
            // and a thrown exception is a 500 the user cannot act on.
            if ($postsAsDrawing && empty($bank->company_id)) {
                return back()->withInput()->with('error',
                    'That account is not attached to a company, so there is no set of books to record the withdrawal in.');
            }
        }

        try {
            $posted = DB::transaction(function () use ($data, $financing, $isAdjustment) {
                $schedule = ! empty($data['financing_schedule_id'])
                    ? FinancingSchedule::lockForUpdate()->find($data['financing_schedule_id'])
                    : null;

                // The lender's charge comes off the top first: it settles nothing, so
                // letting it into the split would report principal as repaid that the
                // loan never received — and 2510 would finish short of zero, reading
                // exactly like a mis-split instalment.
                $feePart = round((float) ($data['fee_part'] ?? 0), 2);
                $applied = round((float) $data['amount'] - $feePart, 2);

                /*
                 * A running account takes whatever arrives: there is no schedule
                 * and no agreed sum, so nothing can be "too much" — paying back
                 * more than was taken simply turns the balance the other way and
                 * the company owes him instead. Everything below is about the
                 * fixed-term case, where both of those things exist.
                 */
                $principalPart = $applied;
                $interestPart  = 0.0;
                $plan          = [];

                if ($schedule) {
                    /*
                     * ── ONE PAYMENT, SETTLED IN ORDER ─────────────────────────
                     * The chosen instalment is where the money starts, not where
                     * it stops. Anything left over runs on to the next open one,
                     * and the one after that, which is what the form has always
                     * promised ("Settles in order") and what paying two months at
                     * a counter actually does.
                     *
                     * Before this, the whole payment was dropped on the single
                     * chosen row with nothing compared against it: 25,000 against
                     * a 10,000 instalment left paid_amount at 25,000 on a
                     * total_amount of 10,000, the next row untouched at "Due",
                     * and the loan unable to close because closing reads statuses
                     * while the balance reads money.
                     *
                     * The split is the worse half. It was one ratio taken off the
                     * WHOLE payment, so a 50,000 payment on an instalment
                     * carrying 833.33 of interest booked 4,545.45 of interest
                     * income — five times what the loan had earned, invented by
                     * arithmetic. Here each instalment is split by what IT
                     * carries, so the interest booked is the interest those
                     * instalments were built with and nothing else.
                     */
                    $open = $financing->schedules()
                        ->whereIn('status', ['due', 'partial'])
                        ->orderBy('instalment_no')
                        ->lockForUpdate()
                        ->get();

                    // Instalments BEFORE the chosen one are deliberately left
                    // alone. Picking #3 while #2 is open is a decision, not a
                    // mistake — the desk sometimes settles out of order — and
                    // silently reaching backwards would overrule it.
                    $queue = $open->where('instalment_no', '>=', $schedule->instalment_no)->values();

                    if ($queue->isEmpty()) {
                        throw new \RuntimeException(
                            'Instalment #' . $schedule->instalment_no . ' is already settled, and there is'
                            . ' nothing after it left to pay. Choose an open instalment, or record this as'
                            . ' an adjustment if it is a correction.'
                        );
                    }

                    $owedFromHere = round($queue->sum(fn ($s) => max(0, (float) $s->total_amount - (float) $s->paid_amount)), 2);

                    // Refused rather than absorbed. Money with nothing left to
                    // settle is not a payment on this loan — it is a wrong figure,
                    // a wrong loan, or a refund, and each of those is somebody's
                    // decision to make rather than something to round away here.
                    if ($applied > $owedFromHere + 0.004) {
                        throw new \RuntimeException(
                            'Only ' . number_format($owedFromHere, 2) . ' is still owed from instalment #'
                            . $queue->first()->instalment_no . ' onward, so '
                            . number_format($applied, 2)
                            . ($feePart > 0 ? ' (what is left after the ' . number_format($feePart, 2) . ' fee)' : '')
                            . ' leaves ' . number_format($applied - $owedFromHere, 2)
                            . ' with nothing to settle. Reduce the amount, or start from an earlier instalment.'
                        );
                    }

                    // Worked out in full before a single row is written, so the
                    // transaction below can be created with the totals its journal
                    // entry needs — the entry is built from principal_part and
                    // interest_part, not from the schedule.
                    $left = $applied;

                    foreach ($queue as $s) {
                        if ($left <= 0) {
                            break;
                        }

                        $owed = round((float) $s->total_amount - (float) $s->paid_amount, 2);

                        if ($owed <= 0) {
                            continue;
                        }

                        $take     = min($left, $owed);
                        $ratio    = (float) $s->total_amount > 0
                            ? (float) $s->interest_component / (float) $s->total_amount
                            : 0.0;
                        $interest = round($take * $ratio, 2);

                        $plan[] = ['schedule' => $s, 'take' => $take, 'interest' => $interest];

                        $interestPart += $interest;
                        $left = round($left - $take, 2);
                    }

                    // Principal is the residual, not a second accumulation: the
                    // two parts are what the bank leg is built from, so they have
                    // to add back to the payment exactly. Summing both separately
                    // lets a paisa of per-instalment rounding drift between them
                    // and leaves the entry a paisa out of balance.
                    $interestPart  = round($interestPart, 2);
                    $principalPart = round($applied - $interestPart, 2);
                }

                $txn = FinancingTransaction::create([
                    'financing_loan_id'     => $financing->id,
                    'financing_schedule_id' => $schedule?->id,
                    'type'                  => $financing->direction === 'lent' ? 'receipt' : 'repayment',
                    'date'                  => $data['date'],
                    'amount'                => $data['amount'],
                    'principal_part'        => $principalPart,
                    'interest_part'         => $interestPart,
                    'fee_part'              => $feePart,
                    // Withheld, not added: it comes out of what the payee receives,
                    // so it never changes what the payment settled.
                    'tds_amount'            => round((float) ($data['tds_amount'] ?? 0), 2),
                    'method'                => $data['method'],
                    'paid_by'               => $financing->is_personal ? ($data['paid_by'] ?? null) : null,
                    'bank_id'               => $data['bank_id'] ?? null,
                    'memo'                  => $data['memo'] ?? null,
                    'created_by'            => Auth::id(),
                ]);

                // Credited with what actually settled each instalment, not the
                // gross payment — a closure fee does not pay down what is owed,
                // and neither does the part of the payment that ran on to the
                // next row.
                foreach ($plan as $step) {
                    $s    = $step['schedule'];
                    $paid = round((float) $s->paid_amount + $step['take'], 2);

                    $s->update([
                        'paid_amount' => $paid,
                        'paid_date'   => $paid >= (float) $s->total_amount ? $data['date'] : $s->paid_date,
                        'status'      => $paid >= (float) $s->total_amount ? 'paid' : 'partial',
                    ]);
                }

                // Close the loan once every instalment is settled, so the register
                // does not need to work it out per row.
                $financing->refresh()->load('schedules');
                if ($financing->schedules->count() && $financing->schedules->every(fn ($s) => in_array($s->status, ['paid', 'waived'], true))) {
                    $financing->update(['status' => 'closed']);
                }

                // Same transaction as the movement it describes: a payment on the
                // desk with no matching entry would leave the liability standing at
                // its old figure, which is exactly the mismatch the monthly check
                // hunts for. Either both exist or neither does.
                $entry = $isAdjustment ? null : $this->posting->postPayment($txn);

                if ($entry) {
                    // Copied from the entry the service returned, never assembled
                    // here. source/source_id on the entry stays the canonical link;
                    // this is the copy the register reads once per row instead of
                    // running a lookup for every line it prints.
                    $txn->update(['journal_entry_id' => $entry->id]);
                }

                return $entry;
            });
        } catch (\RuntimeException $e) {
            // The service refuses rather than write a wrong entry — a missing
            // chart account, a bank with no ledger account, an entry that does not
            // balance. The transaction is already rolled back, so the payment was
            // NOT saved, and the reason is shown as given rather than reaching the
            // user as a 500 they cannot act on.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $posted
            ? 'Payment recorded and posted to the ledger as ' . $posted->reference . '.'
            : 'Payment recorded on the desk only — ' . $this->deskOnlyReason($financing, $isAdjustment, $data['paid_by'] ?? null) . '.');
    }

    /**
     * Why a payment stayed off the ledger, in the words the desk would use.
     *
     * Said out loud rather than left as silence. A payment that posted nothing
     * for a good reason and one that posted nothing because something is
     * misconfigured look identical on screen otherwise — and only one of the two
     * is somebody's problem to fix.
     */
    private function deskOnlyReason(FinancingLoan $financing, bool $isAdjustment, ?string $paidBy): string
    {
        if ($isAdjustment) {
            return 'an adjustment moves no money, so there is nothing to post';
        }

        if ($financing->direction === 'lent') {
            return 'money lent is already on the books through the sale it came from, so posting it again would count it twice';
        }

        if ($financing->is_personal) {
            return $paidBy === 'personal'
                ? ($financing->personal_of ?: 'He') . ' paid it himself, so no company money moved'
                : 'nobody said whose money paid it, so nothing was assumed';
        }

        return 'this loan is not set up to post — record the bank movement in Manage Banks';
    }

    /**
     * Record owner money in or out.
     *
     * Posts when a bank is named, because owner money has no other record in the
     * system: an investment nobody booked leaves the cash unexplained, and a
     * withdrawal nobody booked overstates what the owner still has in the
     * business. Investment credits equity; drawings debit it — never an expense.
     */
    public function printCapital(Request $request)
    {
        $user = Auth::user();
        $query = FinancingCapital::query()->with(['company:id,name', 'bank:id,name']);

        if (! $user->can('view all financing') && ! empty($user->company_id)) {
            $query->where('company_id', $user->company_id);
        } elseif ($request->filled('company_id')) {
            $query->where('company_id', $request->query('company_id'));
        }

        if ($request->filled('ids')) {
            $ids = collect(explode(',', (string) $request->query('ids')))
                ->filter(fn ($id) => ctype_digit(trim($id)))
                ->map(fn ($id) => (int) trim($id))->unique()->values();
            abort_if($ids->isEmpty(), 422, 'No valid transactions were selected.');
            $query->whereIn('id', $ids);
        }

        if (is_string($search = $request->query('q')) && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('person_name', 'like', $term)
                  ->orWhere('reason', 'like', $term)
                  ->orWhere('reference', 'like', $term);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->query('date_to'));
        }

        $reversedCapitalIds = JournalEntry::query()
            ->where('source', 'financing_capital')
            ->whereNull('reversed_journal_entry_id')
            ->whereHas('reversedBy')
            ->pluck('source_id')
            ->filter()
            ->all();

        $activeCapitalQuery = clone $query;

        if ($reversedCapitalIds) {
            $activeCapitalQuery->whereNotIn('id', $reversedCapitalIds);
        }

        $peopleQuery = clone $activeCapitalQuery;

        if (is_string($kind = $request->query('kind')) && in_array($kind, ['investment', 'drawings'], true)) {
            $query->where('kind', $kind);
            $activeCapitalQuery->where('kind', $kind);
        }

        if ($request->filled('owner_id')) {
            $owner = User::withTrashed()->findOrFail((int) $request->query('owner_id'));
            $ownerScope = function ($ownerQuery) use ($owner) {
                $ownerQuery->where('party_id', $owner->id)
                    ->orWhere(function ($legacy) use ($owner) {
                        $legacy->whereNull('party_id')->where('person_name', $owner->name);
                    });
            };
            $query->where($ownerScope);
            $activeCapitalQuery->where($ownerScope);
            $peopleQuery->where($ownerScope);
        } elseif (is_string($person = $request->query('person')) && trim($person) !== '') {
            $query->where('person_name', trim($person));
            $activeCapitalQuery->where('person_name', trim($person));
            $peopleQuery->where('person_name', trim($person));
        }

        $capitalTotals = [
            'investment' => (float) (clone $activeCapitalQuery)->where('kind', 'investment')->sum('amount'),
            'drawings'   => (float) (clone $activeCapitalQuery)->where('kind', 'drawings')->sum('amount'),
            'count'      => (int) (clone $activeCapitalQuery)->count(),
        ];
        $capitalTotals['net'] = $capitalTotals['investment'] - $capitalTotals['drawings'];

        $capitalPeople = $peopleQuery
            ->select('person_name')
            ->selectRaw('MAX(party_id) as party_id')
            ->selectRaw("SUM(CASE WHEN kind = 'investment' THEN amount ELSE 0 END) as investment_total")
            ->selectRaw("SUM(CASE WHEN kind = 'drawings' THEN amount ELSE 0 END) as drawings_total")
            ->selectRaw('COUNT(*) as movement_count')
            ->selectRaw('MAX(date) as last_movement_at')
            ->groupBy('person_name')
            ->orderByRaw("SUM(CASE WHEN kind = 'investment' THEN amount ELSE -amount END) DESC")
            ->get();

        $capital = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get();

        $capitalPosting = JournalEntry::query()
            ->with('reversedBy:id,reversed_journal_entry_id')
            ->where('source', 'financing_capital')
            ->whereIn('source_id', $capital->pluck('id'))
            ->whereNull('reversed_journal_entry_id')
            ->get()
            ->keyBy('source_id');

        $company = $request->filled('company_id')
            ? Company::find($request->query('company_id'))
            : ($user->company_id ? Company::find($user->company_id) : Company::first());

        return view('financing.print-capital', [
            'capital'        => $capital,
            'capitalTotals'  => $capitalTotals,
            'capitalPeople'  => $capitalPeople,
            'capitalPosting' => $capitalPosting,
            'company'        => $company,
            'dateFrom'       => $request->query('date_from'),
            'dateTo'         => $request->query('date_to'),
            'kind'           => $request->query('kind'),
            'person'         => $request->query('person'),
            'search'         => $request->query('q'),
        ]);
    }

    public function printLiability(Request $request)
    {
        $service = app(InterCompanyLiabilityService::class);
        abort_unless($service->ready(), 404);

        $user = Auth::user();
        $scope = function ($query) use ($user, $request) {
            if (! $user->can('view all financing') && ! empty($user->company_id)) {
                return $query->forCompany($user->company_id);
            }

            if ($request->filled('company_id')) {
                return $query->forCompany($request->query('company_id'));
            }

            return $query;
        };

        $filtered = function () use ($scope, $request) {
            $query = $scope(
                InterCompanyLiability::query()
                    ->with(['debtor:id,name,short_name', 'creditor:id,name,short_name', 'bank:id,name', 'expense:id,title'])
            );

            if (is_string($search = $request->query('q')) && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('title', 'like', $term)
                      ->orWhere('reference', 'like', $term)
                      ->orWhere('notes', 'like', $term);
                });
            }

            if ($request->filled('date_from')) {
                $query->whereDate('incurred_on', '>=', $request->query('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('incurred_on', '<=', $request->query('date_to'));
            }

            return $query;
        };

        $activeLiabilities = $filtered()->active()->orderByDesc('incurred_on')->orderByDesc('id')->get();
        $settledLiabilities = $filtered()->closed()->orderByDesc('incurred_on')->orderByDesc('id')->get();
        $wholeBook = $filtered()->get();
        $liabilityTotals = $service->totals($wholeBook);
        $liabilityPairs = $service->pairPositions($wholeBook);
        $liabilityTxns = InterCompanyLiabilityTransaction::query()
            ->with([
                'liability:id,debtor_company_id,creditor_company_id,title,amount',
                'liability.debtor:id,name,short_name',
                'liability.creditor:id,name,short_name',
                'bankTransfer:id,reference_no,amount',
            ])
            ->whereIn('inter_company_liability_id', $filtered()->select('inter_company_liabilities.id'))
            ->orderByDesc('date')->orderByDesc('id')
            ->get();

        $company = $request->filled('company_id')
            ? Company::find($request->query('company_id'))
            : ($user->company_id ? Company::find($user->company_id) : Company::first());

        return view('financing.print-liability', [
            'activeLiabilities'  => $activeLiabilities,
            'settledLiabilities' => $settledLiabilities,
            'liabilityTxns'      => $liabilityTxns,
            'liabilityTotals'    => $liabilityTotals,
            'liabilityPairs'     => $liabilityPairs,
            'company'            => $company,
            'dateFrom'           => $request->query('date_from'),
            'dateTo'             => $request->query('date_to'),
            'search'             => $request->query('q'),
        ]);
    }

    public function storeCapital(Request $request)
    {
        $data = $request->validate([
            'company_id'  => ['required', 'exists:companies,id'],
            'kind'        => ['required', Rule::in(['investment', 'drawings'])],
            'owner_user_id' => ['required', 'exists:users,id'],
            'reason'      => ['required', 'string', 'max:191'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'date'        => ['required', 'date'],
            'transaction_time' => ['required', 'date_format:H:i'],
            'method'      => ['required', Rule::in(['cash', 'bank', 'adjustment'])],
            'bank_id'     => ['nullable', 'required_if:method,bank', 'exists:banks,id'],
            'counterparty_method' => [Rule::requiredIf($request->method !== 'adjustment'), 'nullable', Rule::in(['cash', 'bank', 'cheque'])],
            'counterparty_bank' => [Rule::requiredIf(in_array($request->counterparty_method, ['bank', 'cheque'], true)), 'nullable', 'string', 'max:150'],
            'counterparty_ref' => [Rule::requiredIf(in_array($request->counterparty_method, ['bank', 'cheque'], true)), 'nullable', 'string', 'max:100'],
            'gl_account_id' => ['nullable', 'exists:accounts,id'],
            'reference'   => ['nullable', 'string', 'max:191'],
            'notes'       => ['nullable', 'string'],
        ]);

        if ($data['method'] !== 'bank') $data['bank_id'] = null;
        if (($data['counterparty_method'] ?? null) === 'cash' || $data['method'] === 'adjustment') {
            $data['counterparty_bank'] = null;
            $data['counterparty_ref'] = null;
        }
        try {
            $owner = User::findOrFail($data['owner_user_id']);
            if (! in_array($owner->name, ['Md Mohsin', 'Md Parvez Hossain', 'Nayeemur Rahman', 'Mohammad Fahim'], true)) throw new \RuntimeException('Please choose Md Mohsin, Parvez, Nayeem, or Mohammad Fahim as the capital person.');
            $data['person_name'] = $owner->name;
            $data['party_type'] = 'user';
            $data['party_id'] = $owner->id;
            unset($data['owner_user_id']);
            if ($data['method'] === 'bank' && (int) Bank::findOrFail($data['bank_id'])->company_id !== (int) $data['company_id']) throw new \RuntimeException('The selected account belongs to another company.');
            $posted = DB::transaction(function () use ($data) {
                $capital = FinancingCapital::create($data + ['created_by' => Auth::id()]);

                return $this->posting->postCapital($capital);
            });
        } catch (\RuntimeException $e) {
            // Same contract as a loan payment: the service refuses rather than
            // write a wrong entry, the transaction rolls back, and the reason is
            // shown instead of a 500.
            return back()->withInput()->with('error', $e->getMessage());
        }

        $what = $data['kind'] === 'investment' ? 'Investment' : 'Drawing';

        // A saved owner movement is a money transaction, so notify the owner
        // through the same Brevo/SMS helpers used elsewhere. Delivery is
        // intentionally best-effort: a gateway outage must never roll back a
        // correctly posted journal and bank movement.
        $direction = $data['kind'] === 'investment' ? 'investment received' : 'drawing recorded';
        $reference = $posted?->reference ?: ($data['reference'] ?? 'Capital ledger');
        $notice = "Dear {$owner->name}, BDT " . number_format((float) $data['amount'], 2, '.', ',')
            . " {$direction} on " . \Carbon\Carbon::parse($data['date'])->format('d/m/Y')
            . ". Ref {$reference}.";
        if ($owner->email) {
            try {
                $response = sendBrevoMail($owner->email, $owner->name, "Owner {$what} - {$reference}",
                    '<p>' . e($notice) . '</p><p>Purpose: <strong>' . e($data['reason']) . '</strong></p>');
                if (! $response->successful()) {
                    \Illuminate\Support\Facades\Log::warning('Owner capital email failed.', ['owner_id' => $owner->id, 'status' => $response->status(), 'body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Owner capital email failed.', ['owner_id' => $owner->id, 'error' => $e->getMessage()]);
            }
        }
        if ($owner->phone) {
            try {
                if (sendSms($owner->phone, $notice) === false) {
                    \Illuminate\Support\Facades\Log::warning('Owner capital SMS failed.', ['owner_id' => $owner->id, 'error' => app()->bound('sms.last_error') ? app('sms.last_error') : null]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Owner capital SMS failed.', ['owner_id' => $owner->id, 'error' => $e->getMessage()]);
            }
        }

        return redirect()
            ->route('role.financing.index', ['role' => $request->route('role'), 'book' => 'capital'])
            ->with('success', $posted
                ? $what . ' recorded and posted as ' . $posted->reference . '.'
                : $what . ' recorded as a non-cash adjustment; no cash or bank ledger was changed.');
    }

    public function destroyCapital(Request $request, $role, FinancingCapital $capital)
    {
        $user = Auth::user();

        if (! $user->can('view all financing')) {
            abort_unless(
                empty($user->company_id) || (int) $capital->company_id === (int) $user->company_id,
                404
            );
        }

        $message = 'Entry removed.';

        try { DB::transaction(function () use ($capital, &$message) {
            $postedEntry = $this->posting
                ->entriesFor('financing_capital', $capital->id)
                ->first(fn ($j) => ! $j->reversed_journal_entry_id);

            if ($postedEntry && $postedEntry->reversedBy()->exists()) {
                $message = 'Entry was already reversed; no further action was taken.';
                return;
            }

            if ($postedEntry) {
                if (! $this->posting->reverse($postedEntry, 'Capital movement reversed')) {
                    throw new \RuntimeException('The linked ledger posting could not be reversed.');
                }

                $message = 'Entry reversed. The original row stays visible for audit, and totals now exclude it.';
                return;
            }

            $capital->delete();
            $message = 'Non-posted entry removed.';
        }); } catch (\RuntimeException $e) { return back()->with('error', $e->getMessage()); }

        return redirect()
            ->route('role.financing.index', ['role' => $request->route('role'), 'book' => 'capital'])
            ->with('success', $message);
    }

    public function sendCapitalStatement(Request $request)
    {
        $data = $request->validate([
            'owner_id' => ['required', 'exists:users,id'],
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $owner = User::findOrFail($data['owner_id']);
        $query = FinancingCapital::query()->where(function ($q) use ($owner) {
            $q->where('party_id', $owner->id)
                ->orWhere(fn ($legacy) => $legacy->whereNull('party_id')->where('person_name', $owner->name));
        });
        if (! Auth::user()->can('view all financing') && Auth::user()->company_id) {
            $query->where('company_id', Auth::user()->company_id);
        }
        if (! empty($data['date_from'])) $query->whereDate('date', '>=', $data['date_from']);
        if (! empty($data['date_to'])) $query->whereDate('date', '<=', $data['date_to']);
        $invested = (float) (clone $query)->where('kind', 'investment')->sum('amount');
        $drawn = (float) (clone $query)->where('kind', 'drawings')->sum('amount');
        $net = $invested - $drawn;
        $code = $owner->employee_id_no ?: 'USER-' . str_pad((string) $owner->id, 3, '0', STR_PAD_LEFT);
        $asOf = ! empty($data['date_to']) ? \Carbon\Carbon::parse($data['date_to'])->format('d/m/Y') : now()->format('d/m/Y');
        $text = "Dear {$owner->name}, owner capital statement as of {$asOf}: invested BDT "
            . number_format($invested, 2, '.', ',') . ', drawn BDT ' . number_format($drawn, 2, '.', ',')
            . ', net BDT ' . number_format($net, 2, '.', ',') . ". Ref {$code}.";

        if ($data['channel'] === 'email') {
            if (! $owner->email) return response()->json(['success' => false, 'message' => 'This owner has no email address.'], 422);
            try {
                $response = sendBrevoMail($owner->email, $owner->name, "Owner Capital Statement - {$code}",
                    '<h2>Owner Capital Statement</h2><p>' . e($text) . '</p>');
                return response()->json(['success' => $response->successful(), 'message' => $response->successful() ? "Email sent to {$owner->email}." : 'Brevo email failed: HTTP ' . $response->status()]);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        if (! $owner->phone) return response()->json(['success' => false, 'message' => 'This owner has no phone number.'], 422);
        try {
            $sent = sendSms($owner->phone, $text) !== false;
            return response()->json(['success' => $sent, 'message' => $sent ? "SMS sent to {$owner->phone}." : (app()->bound('sms.last_error') ? app('sms.last_error') : 'SMS failed.')]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* ── Categories and sub-categories ──────────────────────────────────
       One self-parenting table, so a sub-category is just a category with a
       parent and these three methods cover both levels. */

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'parent_id'  => ['nullable', 'exists:financing_categories,id'],
            'direction'  => ['nullable', Rule::in(['borrowed', 'lent'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        // A sub-category inherits its parent's book — letting the two disagree
        // would put a category on a screen its parent is not even on.
        if (! empty($data['parent_id'])) {
            $parent = FinancingCategory::find($data['parent_id']);
            $data['direction'] = $parent?->direction;

            // One level only: a sub-category cannot itself have a parent.
            if ($parent && $parent->parent_id) {
                return back()->with('error', 'Sub-categories cannot be nested further.');
            }
        }

        FinancingCategory::create($data + ['status' => true, 'sort_order' => $data['sort_order'] ?? 0]);

        return back()->with('success', empty($data['parent_id']) ? 'Category added.' : 'Sub-category added.');
    }

    public function updateCategory(Request $request, $role, FinancingCategory $category)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'direction'  => ['nullable', Rule::in(['borrowed', 'lent'])],
            'status'     => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        // Only a top-level category owns its direction; a child follows its parent.
        if ($category->parent_id) {
            unset($data['direction']);
        }

        $category->update($data + ['status' => (bool) $request->boolean('status')]);

        // Keep children in step when a parent moves book, so the tree can never
        // hold a child pointing at a book its parent has left.
        if (! $category->parent_id) {
            $category->children()->update(['direction' => $category->direction]);
        }

        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(Request $request, $role, FinancingCategory $category)
    {
        // Refuse while anything still points at it. Deleting would null the
        // reference on those loans and quietly lose how they were classified.
        $inUse = FinancingLoan::where('category_id', $category->id)
            ->orWhere('sub_category_id', $category->id)
            ->count();

        if ($inUse > 0) {
            return back()->with('error',
                "'{$category->name}' is used by {$inUse} loan" . ($inUse === 1 ? '' : 's') .
                ". Reassign those first, or switch it off instead of deleting it.");
        }

        if ($category->children()->exists()) {
            return back()->with('error',
                "'{$category->name}' still has sub-categories. Remove those first.");
        }

        $category->delete();

        return back()->with('success', 'Category removed.');
    }

    public function destroy(Request $request, $role, FinancingLoan $financing)
    {
        $this->authorizeCompany($financing);

        $financing->delete();

        return redirect()
            ->route('role.financing.index', ['role' => $request->route('role'), 'book' => $financing->direction])
            ->with('success', 'Loan removed.');
    }

    /**
     * Settle some or all of an inter-company liability.
     *
     * Records the payment on the Liability book and nothing else — no journal,
     * no balance moved. If real money changed hands it went through Manage
     * Banks as a transfer, and the optional link below points at it rather than
     * describing it a second time here.
     */
    public function settleLiability(Request $request, $role, InterCompanyLiability $liability)
    {
        $service = app(InterCompanyLiabilityService::class);

        if (! $service->ready()) {
            return back()->with('error', 'The Liability book is not installed on this server yet.');
        }

        $this->authorizeLiability($liability);

        $data = $request->validate([
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'date'             => ['required', 'date'],
            'bank_transfer_id' => ['nullable', 'exists:bank_transfers,id'],
            'memo'             => ['nullable', 'string', 'max:255'],
            'type'             => ['nullable', Rule::in(['settlement', 'write_off', 'adjustment'])],
            // 'record' just notes that it was paid. 'bank' actually pays it:
            // it raises a real transfer from the debtor's account to the
            // creditor's and links it, so nobody has to go to Manage Banks,
            // make the transfer and come back to point at it.
            'settle_via'       => ['nullable', Rule::in(['record', 'bank'])],
            'from_bank_id'     => ['nullable', 'exists:banks,id'],
            'to_bank_id'       => ['nullable', 'exists:banks,id', 'different:from_bank_id'],
        ], [
            'to_bank_id.different' => 'The money has to go from one account to a different one.',
        ]);

        $payByBank = ($data['settle_via'] ?? 'record') === 'bank';

        // Paying it off is a settlement by definition — a write-off is money
        // forgiven, not money sent, so the two cannot be combined.
        if ($payByBank && ($data['type'] ?? 'settlement') !== 'settlement') {
            return back()->withInput()->with('error', 'A write-off or adjustment is not a payment — it cannot be sent by bank transfer.');
        }

        if ($payByBank && (empty($data['from_bank_id']) || empty($data['to_bank_id']))) {
            return back()->withInput()->with('error', 'Choose which account pays and which account receives.');
        }

        try {
            // One transaction around both halves. A transfer that posts while
            // the settlement it exists for fails would move real money and
            // leave the debt still standing — the worst of the two outcomes.
            DB::transaction(function () use ($service, $liability, $data, $payByBank) {
                $transferId = $data['bank_transfer_id'] ?? null;

                if ($payByBank) {
                    $transferId = $this->raiseSettlementTransfer($liability, $data);
                }

                $service->settle(
                    $liability,
                    (float) $data['amount'],
                    $data['date'],
                    $transferId,
                    $data['memo'] ?? null,
                    $data['type'] ?? 'settlement'
                );
            });
        } catch (\Throwable $e) {
            // The service refuses rather than write a wrong figure — an
            // over-settlement is an answer, not a 500. Same for a source
            // account that cannot cover the payment.
            return back()->withInput()->with('error', $e->getMessage());
        }

        $fresh = $liability->fresh();

        return redirect()
            ->route('role.financing.index', ['role' => $role, 'book' => 'liability'])
            ->with('success', $fresh->outstanding > 0
                ? 'Recorded. ৳' . number_format($fresh->outstanding, 2) . ' is still owed.'
                : 'Settled in full — moved to the register.');
    }

    /**
     * Pay a liability off with a real bank transfer, raised from this desk.
     *
     * The transfer is a genuine movement — it debits, credits and moves both
     * bank balances, exactly as one raised from Manage Banks does, because it
     * runs that desk's own code (App\Services\BankTransferService). The
     * liability row still posts nothing; the transfer alongside it is what
     * touches the ledger, and it would have touched it either way.
     *
     * Returns the new transfer's id so the settlement can point at it.
     *
     * @throws \RuntimeException when the paying account cannot cover it
     */
    private function raiseSettlementTransfer(InterCompanyLiability $liability, array $data): int
    {
        $from = Bank::findOrFail($data['from_bank_id']);
        $to   = Bank::findOrFail($data['to_bank_id']);
        $amount = round((float) $data['amount'], 2);

        // The same ledger-derived figure the bank desk checks against — never
        // the `balance` column, which has drifted from the ledger before.
        $available = $from->availableBalance();

        if ($available < $amount) {
            throw new \RuntimeException(
                $from->name . ' does not hold enough to send this. Available: ৳'
                . number_format($available, 2) . ', needed: ৳' . number_format($amount, 2) . '.'
            );
        }

        $transferService = app(\App\Services\BankTransferService::class);

        $transfer = BankTransfer::create([
            'from_bank_id'   => $from->id,
            'to_bank_id'     => $to->id,
            'amount'         => $amount,
            'payment_date'   => $data['date'],
            'reference_no'   => $transferService->settlementReference($liability->id),
            'payment_method' => 'bank_transfer',
            'status'         => 'completed',
            'remarks'        => trim('Inter-company settlement — '
                . ($liability->debtor?->short_name ?: $liability->debtor?->name) . ' to '
                . ($liability->creditor?->short_name ?: $liability->creditor?->name)
                . ($data['memo'] ?? '' ? ' · ' . $data['memo'] : '')),
            'created_by'     => Auth::id(),
        ]);

        $transferService->post($transfer, $from);

        return $transfer->id;
    }

    /**
     * A company-locked user may open a liability only if their company is one of
     * the two parties to it. Either side counts: being owed is as much your
     * business as owing.
     */
    private function authorizeLiability(InterCompanyLiability $liability): void
    {
        $user = Auth::user();

        if ($user->can('view all financing') || empty($user->company_id)) {
            return;
        }

        abort_unless(
            (int) $liability->debtor_company_id === (int) $user->company_id
                || (int) $liability->creditor_company_id === (int) $user->company_id,
            404
        );
    }

    /**
     * A company-locked user must not reach another company's loan by guessing
     * an id — the index filter alone would not stop that.
     */
    private function authorizeCompany(FinancingLoan $loan): void
    {
        $user = Auth::user();

        if ($user->can('view all financing')) {
            return;
        }

        abort_unless(
            empty($user->company_id) || (int) $loan->company_id === (int) $user->company_id,
            404
        );
    }
}
