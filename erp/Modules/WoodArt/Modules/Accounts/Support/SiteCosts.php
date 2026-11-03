<?php

namespace Modules\WoodArt\Modules\Accounts\Support;

use App\Models\User;
use App\Services\EmployeeReimbursementService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wood Art site costs — a READ-ONLY view over the ERP's own Expense module.
 *
 * Nothing here writes. A "project" is an Expense Department of company 6 (that
 * is how the Expense screens already group a site's costs: Mr. Sogib Miah
 * Project, Mr. Mustaq Ahmed Project, …), so there is no second set of books to
 * keep in step — the figures are the ERP's own, filtered to company 6.
 *
 * Only APPROVED expenses count as cost: that is the moment an expense reaches
 * the accounts (ExpenseController::approve → postJournal). Filed-but-pending
 * ones are shown beside the total so nothing looks missing, never inside it.
 */
class SiteCosts
{
    /** Presentation helpers only; integer minor units avoid introducing float-based money arithmetic. */
    public static function constructionPresentation(int $departmentId): array
    {
        $minor = static function ($amount): int {
            $text = (string) ($amount ?? '0');
            $negative = str_starts_with($text, '-');
            [$whole, $fraction] = array_pad(explode('.', ltrim($text, '+-'), 2), 2, '');
            $value = (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

            return $negative ? -$value : $value;
        };
        $moneyMinor = static fn (int $n) => ($n < 0 ? '-৳ ' : '৳ ').number_format(intdiv(abs($n), 100)).'.'.str_pad((string) (abs($n) % 100), 2, '0', STR_PAD_LEFT);

        return [
            'minor' => $minor, 'moneyMinor' => $moneyMinor,
            'money' => static fn ($amount) => $moneyMinor($minor($amount)),
            'percent' => static fn (int $numerator, int $denominator) => $denominator > 0 ? round($numerator / $denominator * 100, 1) : null,
            'projectUrl' => static fn (array $params = []) => ProjectContext::route('project', ['department' => $departmentId] + $params),
            'headFilters' => static fn (array $head) => ['head' => $head['head_id'], 'category' => $head['category_id'],
                'without_head' => $head['head_id'] === null ? 1 : null, 'without_category' => $head['category_id'] === null ? 1 : null, 'approval' => 'approved'],
        ];
    }

    /** The company whose projects are shown — chosen from the URL (see ProjectContext). */
    public static function company(): int
    {
        return ProjectContext::id();
    }

    /** One line per project (department) with what it cost so far. */
    public static function projects(): Collection
    {
        $rows = DB::table('expense_departments as d')
            ->leftJoin('expenses as e', function ($j) {
                $j->on('e.expense_department_id', '=', 'd.id')
                    ->where('e.company_id', self::company())
                    ->whereNull('e.deleted_at');
            })
            ->where('d.company_id', self::company())
            ->whereNull('d.deleted_at')
            ->groupBy('d.id', 'd.name')
            ->orderBy('d.name')
            ->get([
                'd.id',
                'd.name',
                DB::raw("COALESCE(SUM(CASE WHEN e.approval_status = 'approved' THEN e.amount END), 0) AS total"),
                DB::raw("COUNT(CASE WHEN e.approval_status = 'approved' THEN 1 END) AS entries"),
                DB::raw("COALESCE(SUM(CASE WHEN e.approval_status <> 'approved' THEN e.amount END), 0) AS pending"),
                DB::raw("MIN(CASE WHEN e.approval_status = 'approved' THEN e.expense_date END) AS first_on"),
                DB::raw("MAX(CASE WHEN e.approval_status = 'approved' THEN e.expense_date END) AS last_on"),
            ]);

        $agg = self::statementTotals();

        return $rows->map(function ($r) use ($agg) {
            $r->total = (float) $r->total;
            $r->pending = (float) $r->pending;

            // Statement figures (client installments, supplier dues): null when
            // the project has no contract value on file, so "not set" is never
            // shown as a client who owes nothing.
            $a = $agg[$r->id] ?? null;
            $r->contract_value = $a['contract'] ?? null;
            $r->received = $a['received'] ?? 0.0;
            $r->client_due = $r->contract_value !== null ? $r->contract_value - $r->received : null;
            $r->supplier_due = $a['supplier_due'] ?? 0.0;

            return $r;
        });
    }

    /** Compact, server-paginated project portfolio for the accounts workspace. */
    public static function projectsPage(array $filters = [], int $perPage = 25, bool $export = false)
    {
        $co = self::company();
        $expense = DB::table('expenses')->where('company_id', $co)->whereNull('deleted_at')
            ->groupBy('expense_department_id')->select([
                'expense_department_id',
                DB::raw("COALESCE(SUM(CASE WHEN approval_status = 'approved' THEN amount END), 0) AS approved_cost"),
                DB::raw("COALESCE(SUM(CASE WHEN approval_status <> 'approved' THEN amount END), 0) AS pending_cost"),
                DB::raw("COUNT(CASE WHEN approval_status = 'approved' THEN 1 END) AS expense_count"),
                DB::raw('MAX(expense_date) AS last_expense_date'),
            ]);
        $statement = DB::table('project_statement')->whereNull('deleted_at')
            ->groupBy('expense_department_id')->select([
                'expense_department_id',
                DB::raw("COALESCE(SUM(CASE WHEN kind = 'client_installment' THEN amount ELSE 0 END), 0) AS received"),
                DB::raw("COALESCE(SUM(CASE WHEN kind IN ('supplier_bill','supplier_balance') AND journal_entry_id IS NOT NULL AND reversal_journal_entry_id IS NULL THEN amount WHEN kind = 'supplier_payment' AND journal_entry_id IS NOT NULL AND reversal_journal_entry_id IS NULL THEN -amount ELSE 0 END), 0) AS supplier_due"),
            ]);

        $query = DB::table('expense_departments as d')
            ->leftJoinSub($expense, 'ex', 'ex.expense_department_id', '=', 'd.id')
            ->leftJoin('project_terms as pt', 'pt.expense_department_id', '=', 'd.id')
            ->leftJoin('users as client', 'client.id', '=', 'pt.client_party_id')
            ->leftJoin('users as manager', 'manager.id', '=', 'pt.manager_user_id')
            ->leftJoinSub($statement, 'st', 'st.expense_department_id', '=', 'd.id')
            ->where('d.company_id', $co)->whereNull('d.deleted_at')
            ->when(ProjectContext::key() === 'construction', fn ($q) => $q->where('d.name', '<>', 'Others...'))
            ->when(($filters['q'] ?? '') !== '', function ($q) use ($filters) {
                $like = '%'.$filters['q'].'%';
                $q->where(fn ($w) => $w->where('d.name', 'like', $like)->orWhere('pt.project_code', 'like', $like)->orWhere('client.name', 'like', $like)->orWhere('manager.name', 'like', $like));
            });

        $status = $filters['status'] ?? '';
        if ($status === 'active') {
            $query->where(fn ($q) => $q->whereNotNull('ex.expense_department_id')->orWhereNotNull('pt.contract_value'));
        }
        if ($status === 'unconfigured') {
            $query->whereNull('pt.contract_value');
        }
        if ($status === 'settled') {
            $query->whereRaw('COALESCE(pt.contract_value, 0) > 0')->whereRaw('COALESCE(pt.contract_value, 0) - COALESCE(st.received, 0) <= 0.004')->whereRaw('COALESCE(st.supplier_due, 0) <= 0.004');
        }

        // Construction's record status is a boolean, not a construction progress state.
        if (ProjectContext::key() === 'construction') {
            $query->when($status === 'enabled', fn ($q) => $q->where('d.status', 1))
                ->when($status === 'disabled', fn ($q) => $q->where('d.status', 0))
                ->when(in_array($status, ['in_progress', 'on_hold', 'completed'], true), fn ($q) => $q->where('pt.construction_status', $status))
                ->when($filters['client'] ?? null, fn ($q, $id) => $q->where('pt.client_party_id', $id))
                ->when($filters['manager'] ?? null, fn ($q, $id) => $q->where('pt.manager_user_id', $id))
                ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('ex.last_expense_date', '>=', $date))
                ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('ex.last_expense_date', '<=', $date));
        }

        $sorts = [
            'code' => 'pt.project_code', 'name' => 'd.name', 'contract' => 'pt.contract_value', 'received' => 'st.received',
            'cost' => 'ex.approved_cost', 'client_due' => DB::raw('COALESCE(pt.contract_value, 0) - COALESCE(st.received, 0)'),
            'supplier_due' => 'st.supplier_due',
        ];
        $sort = $sorts[$filters['sort'] ?? (ProjectContext::key() === 'construction' ? 'code' : 'name')] ?? 'd.name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sort, $direction)->orderBy('d.id')
            ->select([
                'd.id', 'd.name', 'client.name as client_name', 'manager.name as manager_name', 'pt.contract_value',
                'pt.project_code', 'pt.start_date', 'pt.end_date', 'pt.progress_percent', 'pt.construction_status', 'pt.project_type',
                DB::raw('COALESCE(st.received, 0) AS received'),
                DB::raw('COALESCE(pt.contract_value, 0) - COALESCE(st.received, 0) AS client_due'),
                DB::raw('COALESCE(ex.approved_cost, 0) AS approved_cost'),
                DB::raw('COALESCE(ex.pending_cost, 0) AS pending_cost'),
                DB::raw('COALESCE(ex.expense_count, 0) AS expense_count'),
                DB::raw('COALESCE(st.supplier_due, 0) AS supplier_due'), 'ex.last_expense_date',
            ]);

        if (ProjectContext::key() === 'construction') {
            $query->addSelect('d.status as record_status');
        }

        return $export ? $query->cursor() : $query->paginate($perPage)->withQueryString();
    }

    /** Company-level financial totals, calculated by aggregate queries rather than loading projects. */
    public static function overviewTotals(): array
    {
        $co = self::company();
        $ids = DB::table('expense_departments')->where('company_id', $co)->whereNull('deleted_at')->select('id');
        $contract = (float) DB::table('project_terms')->whereIn('expense_department_id', clone $ids)->sum('contract_value');
        $received = self::statementReady() ? (float) DB::table('project_statement')->whereIn('expense_department_id', clone $ids)->whereNull('deleted_at')->where('kind', 'client_installment')->sum('amount') : 0.0;
        $cost = (float) DB::table('expenses')->where('company_id', $co)->whereNull('deleted_at')->where('approval_status', 'approved')->sum('amount');
        $pending = (float) DB::table('expenses')->where('company_id', $co)->whereNull('deleted_at')->where('approval_status', '<>', 'approved')->sum('amount');
        $liveSupplierRows = fn () => DB::table('project_statement')->whereIn('expense_department_id', clone $ids)->whereNull('deleted_at')
            ->whereNotNull('journal_entry_id')->whereNull('reversal_journal_entry_id');
        $supplierBills = self::statementReady() ? (float) $liveSupplierRows()->whereIn('kind', ['supplier_bill', 'supplier_balance'])->sum('amount') : 0.0;
        $supplierPaid = self::statementReady() ? (float) $liveSupplierRows()->where('kind', 'supplier_payment')->sum('amount') : 0.0;

        return ['contract' => $contract, 'received' => $received, 'cost' => $cost, 'client_due' => $contract - $received,
            'supplier_due' => $supplierBills - $supplierPaid, 'contract_less_cost' => $contract - $cost, 'pending' => $pending];
    }

    /** Server-paginated expense register with filters that stay inside this company. */
    public static function expensesPage(array $filters = [], int $perPage = 40)
    {
        return DB::table('expenses as e')->leftJoin('expense_departments as d', 'd.id', '=', 'e.expense_department_id')
            ->leftJoin('expense_sub_categories as sc', 'sc.id', '=', 'e.expense_sub_category_id')
            ->leftJoin('expense_categories as c', 'c.id', '=', 'e.expense_category_id')
            ->where('e.company_id', self::company())->whereNull('e.deleted_at')
            ->when($filters['project'] ?? null, fn ($q, $id) => $q->where('e.expense_department_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('e.expense_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('e.expense_date', '<=', $date))
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('e.expense_category_id', $id))
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('e.approval_status', $filters['status']))
            ->when(($filters['q'] ?? '') !== '', function ($query) use ($filters) {
                $like = '%'.$filters['q'].'%';
                $query->where(fn ($q) => $q->where('e.title', 'like', $like)->orWhere('e.description', 'like', $like)
                    ->orWhere('e.reference_user_name', 'like', $like)->orWhere('d.name', 'like', $like)->orWhere('sc.name', 'like', $like));
            })->orderByDesc('e.expense_date')->orderByDesc('e.id')
            ->paginate($perPage, ['e.id', 'e.expense_date', 'e.title', 'e.description', 'e.amount', 'e.approval_status', 'e.reference_user_name',
                'e.reimburse_to_user_id', 'e.petty_cash_float_id', 'e.bank_id', 'e.payment_mode', 'd.name as project', 'd.id as project_id',
                DB::raw('COALESCE(sc.name, c.name) AS head')])->withQueryString();
    }

    public static function receivablesPage(array $filters = [], int $perPage = 30)
    {
        $statement = DB::table('project_statement')->whereNull('deleted_at')->groupBy('expense_department_id')->select([
            'expense_department_id',
            DB::raw("COALESCE(SUM(CASE WHEN kind = 'client_bill' THEN amount ELSE 0 END), 0) AS billed"),
            DB::raw("COALESCE(SUM(CASE WHEN kind = 'client_installment' THEN amount ELSE 0 END), 0) AS received"),
            DB::raw("MAX(CASE WHEN kind = 'client_installment' THEN entry_date END) AS last_received_on"),
        ]);

        return DB::table('expense_departments as d')->join('project_terms as pt', 'pt.expense_department_id', '=', 'd.id')
            ->leftJoin('users as client', 'client.id', '=', 'pt.client_party_id')->leftJoinSub($statement, 'st', 'st.expense_department_id', '=', 'd.id')
            ->where('d.company_id', self::company())->whereNull('d.deleted_at')
            ->when(($filters['q'] ?? '') !== '', function ($q) use ($filters) {
                $like = '%'.$filters['q'].'%';
                $q->where(fn ($w) => $w->where('d.name', 'like', $like)->orWhere('client.name', 'like', $like));
            })
            ->when(($filters['status'] ?? '') === 'due', fn ($q) => $q->whereRaw('COALESCE(pt.contract_value, 0) - COALESCE(st.received, 0) > 0.004'))
            ->when(($filters['status'] ?? '') === 'paid', fn ($q) => $q->whereRaw('COALESCE(pt.contract_value, 0) - COALESCE(st.received, 0) <= 0.004'))
            ->orderByDesc(DB::raw('COALESCE(pt.contract_value, 0) - COALESCE(st.received, 0)'))
            ->paginate($perPage, ['d.id as project_id', 'd.name as project', 'client.id as client_id', 'client.name as client', 'pt.contract_value',
                DB::raw('COALESCE(st.billed, 0) AS billed'), DB::raw('COALESCE(st.received, 0) AS received'),
                DB::raw('COALESCE(pt.contract_value, 0) - COALESCE(st.received, 0) AS outstanding'), 'st.last_received_on'])->withQueryString();
    }

    public static function payablesPage(array $filters = [], int $perPage = 30)
    {
        $balances = DB::table('project_statement as ps')->join('expense_departments as d', 'd.id', '=', 'ps.expense_department_id')
            ->where('d.company_id', self::company())->whereNull('d.deleted_at')->whereNull('ps.deleted_at')
            ->whereNotNull('ps.journal_entry_id')->whereNull('ps.reversal_journal_entry_id')
            ->whereIn('ps.kind', ['supplier_bill', 'supplier_payment', 'supplier_balance'])
            ->when($filters['project'] ?? null, fn ($q, $id) => $q->where('d.id', $id))
            ->when(($filters['q'] ?? '') !== '', function ($q) use ($filters) {
                $like = '%'.$filters['q'].'%';
                $q->where(fn ($w) => $w->where('d.name', 'like', $like)->orWhere('ps.party', 'like', $like));
            })
            ->groupBy('d.id', 'd.name', 'ps.party_id', 'ps.party')
            ->select(['d.id as project_id', 'd.name as project', 'ps.party_id', 'ps.party',
                DB::raw("SUM(CASE WHEN ps.kind IN ('supplier_bill','supplier_balance') THEN ps.amount ELSE 0 END) AS obligations"),
                DB::raw("SUM(CASE WHEN ps.kind = 'supplier_payment' THEN ps.amount ELSE 0 END) AS paid"),
                DB::raw("SUM(CASE WHEN ps.kind IN ('supplier_bill','supplier_balance') THEN ps.amount ELSE -ps.amount END) AS outstanding"),
                DB::raw('MAX(ps.entry_date) AS last_transaction_date')]);

        return DB::query()->fromSub($balances, 'payables')->where('outstanding', '>', 0.004)
            ->orderByDesc('outstanding')->paginate($perPage)->withQueryString();
    }

    /* ── statement (client installments, supplier dues) ───────────────────────
       Wood Art's own tables, written by woodart:seed-munshi-villa and the forms
       on the project sheet. Statement only — see the migration. Every reader
       checks the tables exist first: a lagging server shows "not set up yet",
       never an error. */

    public static function statementReady(): bool
    {
        try {
            return Schema::hasTable('project_terms') && Schema::hasTable('project_statement');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Per project: contract value, what the client has paid, what suppliers are still owed. */
    public static function statementTotals(): array
    {
        if (! self::statementReady()) {
            return [];
        }

        $out = [];

        foreach (DB::table('project_terms')->get(['expense_department_id', 'contract_value']) as $t) {
            $out[$t->expense_department_id]['contract'] = $t->contract_value === null ? null : (float) $t->contract_value;
        }

        $rows = DB::table('project_statement')->whereNull('deleted_at')
            ->groupBy('expense_department_id', 'kind')
            ->get(['expense_department_id as d', 'kind', DB::raw('SUM(amount) as t')]);

        foreach ($rows as $r) {
            $a = &$out[$r->d];
            $a['received'] = ($a['received'] ?? 0.0) + ($r->kind === 'client_installment' ? (float) $r->t : 0.0);
            $a['supplier_due'] = ($a['supplier_due'] ?? 0.0)
                + ($r->kind === 'supplier_bill' ? (float) $r->t : 0.0)
                - ($r->kind === 'supplier_payment' ? (float) $r->t : 0.0);
            unset($a);
        }

        return $out;
    }

    /** One project's statement: terms, installments with a running due, supplier balances. */
    public static function statementFor(int $departmentId): array
    {
        $empty = ['client' => null, 'clients' => collect(), 'ready' => false, 'books' => ['billed' => 0.0, 'received' => 0.0, 'due' => 0.0], 'supplierBooks' => collect(), 'banks' => collect(), 'heads' => collect(), 'bills' => collect(), 'contract' => null, 'note' => null, 'installments' => collect(), 'received' => 0.0,
            'clientDue' => null, 'suppliers' => collect(), 'supplierDue' => 0.0, 'lines' => collect()];

        if (! self::statementReady()) {
            return $empty;
        }

        $terms = DB::table('project_terms')->where('expense_department_id', $departmentId)->first();
        $contract = $terms && $terms->contract_value !== null ? (float) $terms->contract_value : null;

        $lines = DB::table('project_statement')->where('expense_department_id', $departmentId)->whereNull('deleted_at')
            ->orderBy('entry_date')->orderBy('id')->get();

        // A supplier_balance journal carries one supplier's historical net due
        // into the books. The earlier imported bill/payment rows remain useful
        // statement detail, but posting them again would duplicate that journal.
        // Rows entered after the balance carry remain independently postable.
        $balanceCarryByParty = $lines->where('kind', 'supplier_balance')
            ->filter(fn ($line) => $line->journal_entry_id && ! $line->reversal_journal_entry_id && $line->party_id)
            ->groupBy(fn ($line) => (int) $line->party_id)
            ->map(fn ($rows) => (int) $rows->max('id'));

        // Rows imported from a supplier's / contractor's own sheet (ext_key MV-L-…) are that party's HISTORY:
        // the books carry only his net balance (one `supplier_balance` row, "Post to the books" on the summary
        // page), because the paid part is already a cost entry. They are never posted one by one.
        $isHistory = fn ($l) => in_array($l->kind, ['supplier_bill', 'supplier_payment'], true) && str_starts_with((string) $l->ext_key, 'MV-L-');
        $histNet = $lines->filter($isHistory)->groupBy(fn ($l) => (int) $l->party_id)
            ->map(fn ($g) => round((float) $g->where('kind', 'supplier_bill')->sum('amount') - (float) $g->where('kind', 'supplier_payment')->sum('amount'), 2));

        $lines->each(function ($line) use ($balanceCarryByParty, $isHistory, $histNet) {
            $carryId = $line->party_id ? $balanceCarryByParty->get((int) $line->party_id) : null;
            $open = ! $line->journal_entry_id && ! $line->reversal_journal_entry_id;
            $covered = in_array($line->kind, ['supplier_bill', 'supplier_payment'], true) && $open && $carryId && (int) $line->id < (int) $carryId;
            $history = $open && $isHistory($line);

            $line->covered_by_balance = $covered || $history;
            $line->history_label = ! ($covered || $history) ? null
                : ($covered ? 'Included in net balance'
                : (abs((float) ($histNet[(int) $line->party_id] ?? 0)) < 0.005 ? 'Settled — history only' : 'History — posts with the net balance'));
        });

        $running = $contract;
        $installments = $lines->where('kind', 'client_installment')->values()->map(function ($l) use (&$running) {
            $l->amount = (float) $l->amount;
            if ($running !== null) {
                $running -= $l->amount;
            }
            $l->due_after = $running;

            return $l;
        });
        $received = (float) $installments->sum('amount');

        $suppliers = $lines->whereIn('kind', ['supplier_bill', 'supplier_payment'])
            ->groupBy(fn ($l) => $l->party ?: '—')
            ->map(function ($g, $party) {
                $bill = (float) $g->where('kind', 'supplier_bill')->sum('amount');
                $paid = (float) $g->where('kind', 'supplier_payment')->sum('amount');

                return ['party' => $party, 'party_id' => $g->whereNotNull('party_id')->first()->party_id ?? null, 'bill' => $bill, 'paid' => $paid, 'due' => $bill - $paid,
                    'last' => $g->max('entry_date')];
            })->sortByDesc('due')->values();

        $books = SitePosting::clientBooks($departmentId);

        // Per supplier: what the BOOKS say is owed (posted dues minus posted payments).
        $supplierBooks = $suppliers->mapWithKeys(fn ($sp) => [$sp['party'] => SitePosting::supplierBooks($departmentId, $sp['party'])['owed']]);

        // The project's client, and the clients it can be set to (this company's own).
        $client = null;
        $clients = collect();
        try {
            if (Schema::hasTable('company_party')) {
                $client = ($terms && $terms->client_party_id) ? User::find((int) $terms->client_party_id) : null;
                $clients = User::query()->whereIn('id', DB::table('company_party')->where('company_id', self::company())
                    ->where('party_type', 'client')->select('user_id'))->orderBy('name')->get(['id', 'name', 'phone']);
            }
        } catch (\Throwable $e) {
            // the register is an addition; the sheet stands without it
        }

        return [
            'client' => $client,
            'clients' => $clients,
            'ready' => true,
            'books' => $books,
            'supplierBooks' => $supplierBooks,
            'banks' => SitePosting::banks(),
            'heads' => DB::table('expense_sub_categories')->where('company_id', self::company())->whereNull('deleted_at')->where('status', 1)->orderBy('name')->get(['id', 'name']),
            'bills' => $lines->where('kind', 'client_bill')->values(),
            'contract' => $contract,
            'note' => $terms->note ?? null,
            'installments' => $installments,
            'received' => $received,
            'clientDue' => $contract !== null ? $contract - $received : null,
            'suppliers' => $suppliers,
            'supplierDue' => (float) $suppliers->sum('due'),
            'lines' => $lines,
        ];
    }

    /* ── the site team: what each person spent, and the funds provided to him ───────────
       A person's account on one project. Spent = the cost entries filed in his name (own pocket);
       Provided = the `person_fund` statement rows (sent to him, or paid straight to a supplier for
       him by another Epal company). Balance = spent − provided: above zero the company owes him,
       below zero he is holding company money. Read-only; the books are the journals. */

    /** One row per person who spent on, or was given funds for, this project. */
    public static function team(int $departmentId): Collection
    {
        $co = self::company();

        $spent = DB::table('expenses')->where('company_id', $co)->where('expense_department_id', $departmentId)
            ->whereNull('deleted_at')->whereNotNull('reimburse_to_user_id')
            ->groupBy('reimburse_to_user_id')
            ->get([
                'reimburse_to_user_id as user_id',
                DB::raw("COALESCE(SUM(CASE WHEN approval_status = 'approved' THEN amount END), 0) AS posted"),
                DB::raw("COALESCE(SUM(CASE WHEN approval_status <> 'approved' THEN amount END), 0) AS pending"),
                DB::raw('COUNT(*) AS n'),
                DB::raw('MAX(expense_date) AS last_on'),
            ])->keyBy('user_id');

        $funds = collect();
        if (self::statementReady()) {
            $funds = DB::table('project_statement')->where('expense_department_id', $departmentId)->where('kind', 'person_fund')
                ->whereNull('deleted_at')->whereNotNull('party_id')->groupBy('party_id')
                ->get([
                    'party_id as user_id',
                    DB::raw('COALESCE(SUM(amount), 0) AS provided'),
                    DB::raw('COALESCE(SUM(CASE WHEN journal_entry_id IS NOT NULL AND reversal_journal_entry_id IS NULL THEN amount END), 0) AS booked'),
                    DB::raw('COUNT(*) AS fn'),
                    DB::raw('MAX(entry_date) AS fund_last'),
                ])->keyBy('user_id');
        }

        $ids = $spent->keys()->merge($funds->keys())->unique()->values();
        $names = DB::table('users')->whereIn('id', $ids)->pluck('name', 'id');

        return $ids->map(function ($id) use ($spent, $funds, $names) {
            $s = $spent[$id] ?? null;
            $f = $funds[$id] ?? null;
            $posted = (float) ($s->posted ?? 0);
            $pending = (float) ($s->pending ?? 0);
            $provided = (float) ($f->provided ?? 0);
            $booked = (float) ($f->booked ?? 0);

            return (object) [
                'user_id' => (int) $id, 'name' => $names[$id] ?? ('#'.$id),
                'n' => (int) ($s->n ?? 0), 'posted' => $posted, 'pending' => $pending, 'spent' => $posted + $pending,
                'provided' => $provided, 'booked' => $booked, 'funds' => (int) ($f->fn ?? 0),
                'balance' => $posted + $pending - $provided,        // > 0: the company owes him
                'in_books' => $posted - $booked,                    // the same, on posted entries only
                'last' => max((string) ($s->last_on ?? ''), (string) ($f->fund_last ?? '')) ?: null,
            ];
        })->sortByDesc('spent')->values();
    }

    /** One person's account on one project: funds provided (Debit) and what he spent (Credit), oldest first. */
    public static function personLedger(int $departmentId, int $userId): array
    {
        $co = self::company();
        $rows = collect();

        foreach (DB::table('expenses as e')->leftJoin('expense_sub_categories as sc', 'sc.id', '=', 'e.expense_sub_category_id')
            ->where('e.company_id', $co)->where('e.expense_department_id', $departmentId)->where('e.reimburse_to_user_id', $userId)
            ->whereNull('e.deleted_at')
            ->get(['e.id', 'e.expense_date', 'e.title', 'e.description', 'e.amount', 'e.reference', 'e.approval_status', 'sc.name as head']) as $e) {
            $rows->push((object) [
                'date' => substr((string) $e->expense_date, 0, 10), 'sort' => 1000000 + $e->id, 'kind' => 'spent',
                'particulars' => mb_substr((string) ($e->title ?: $e->description), 0, 110), 'sub' => $e->head, 'ref' => $e->reference,
                'dr' => 0.0, 'cr' => (float) $e->amount, 'status' => $e->approval_status === 'approved' ? 'in books' : 'awaiting confirm', 'line' => null,
            ]);
        }

        if (self::statementReady()) {
            foreach (DB::table('project_statement')->where('expense_department_id', $departmentId)->where('kind', 'person_fund')
                ->where('party_id', $userId)->whereNull('deleted_at')->get() as $f) {
                $rows->push((object) [
                    'date' => substr((string) $f->entry_date, 0, 10), 'sort' => (int) $f->id, 'kind' => 'fund',
                    'particulars' => 'Funds provided'.($f->label ? ' — '.$f->label : ''), 'sub' => $f->note, 'ref' => null,
                    'dr' => (float) $f->amount, 'cr' => 0.0,
                    'status' => $f->reversal_journal_entry_id ? 'reversed' : ($f->journal_entry_id ? 'in books' : 'not in books'), 'line' => $f,
                ]);
            }
        }

        $rows = $rows->sortBy([fn ($a, $b) => strcmp($a->date, $b->date), fn ($a, $b) => $a->sort <=> $b->sort])->values();
        $bal = 0.0;
        $rows->each(function ($r) use (&$bal) {
            $bal += $r->cr - $r->dr;
            $r->balance = $bal;                 // > 0: the company owes him
        });

        return ['rows' => $rows, 'spent' => (float) $rows->sum('cr'), 'provided' => (float) $rows->sum('dr'), 'balance' => $bal];
    }

    public static function department(int $id): ?object
    {
        return DB::table('expense_departments')
            ->where('id', $id)
            ->where('company_id', self::company())
            ->whereNull('deleted_at')
            ->first();
    }

    /** Same live-row rules as SitePosting::supplierBooks, including balance-only suppliers. */
    public static function postedSupplierBalances(int $departmentId): Collection
    {
        if (! self::statementReady()) {
            return collect();
        }

        return DB::table('project_statement as ps')
            ->join('expense_departments as d', 'd.id', '=', 'ps.expense_department_id')
            ->where('d.company_id', self::company())->whereNull('d.deleted_at')
            ->where('d.id', $departmentId)->whereNull('ps.deleted_at')
            ->whereNotNull('ps.journal_entry_id')->whereNull('ps.reversal_journal_entry_id')
            ->whereIn('ps.kind', ['supplier_bill', 'supplier_balance', 'supplier_payment'])
            ->groupBy('ps.party')->select('ps.party')
            ->selectRaw("SUM(CASE WHEN ps.kind = 'supplier_payment' THEN -ps.amount ELSE ps.amount END) AS outstanding")
            ->orderByDesc('outstanding')->get();
    }

    /**
     * The whole sheet for one project: every entry with a running total, and the
     * same money cut by head, by month and by who paid.
     */
    public static function sheet(int $departmentId, ?string $from = null, ?string $to = null, string $q = '', ?int $perPage = null, array $filters = [], bool $overview = false): array
    {
        $base = fn () => DB::table('expenses as e')
            ->leftJoin('expense_sub_categories as sc', 'sc.id', '=', 'e.expense_sub_category_id')
            ->leftJoin('expense_categories as c', 'c.id', '=', 'e.expense_category_id')
            ->where('e.company_id', self::company())
            ->where('e.expense_department_id', $departmentId)
            ->whereNull('e.deleted_at')
            ->when($filters['head'] ?? null, fn ($qq, $id) => $qq->where('e.expense_sub_category_id', $id))
            ->when($filters['category'] ?? null, fn ($qq, $id) => $qq->where('e.expense_category_id', $id))
            ->when($filters['person'] ?? null, fn ($qq, $id) => $qq->where('e.reimburse_to_user_id', $id))
            ->when($filters['approval'] ?? null, fn ($qq, $status) => $qq->where('e.approval_status', $status))
            ->when($filters['without_head'] ?? false, fn ($qq) => $qq->whereNull('e.expense_sub_category_id'))
            ->when($filters['without_category'] ?? false, fn ($qq) => $qq->whereNull('e.expense_category_id'))
            ->when($from, fn ($qq) => $qq->whereDate('e.expense_date', '>=', $from))
            ->when($to, fn ($qq) => $qq->whereDate('e.expense_date', '<=', $to))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%'.$q.'%';
                $qq->where(fn ($w) => $w->where('e.title', 'like', $like)
                    ->orWhere('e.description', 'like', $like)
                    ->orWhere('e.reference_user_name', 'like', $like)
                    ->orWhere('sc.name', 'like', $like));
            });

        if ($perPage !== null) {
            $totals = $base()->selectRaw("COALESCE(SUM(CASE WHEN e.approval_status = 'approved' THEN e.amount ELSE 0 END),0) AS approved, COALESCE(SUM(CASE WHEN e.approval_status <> 'approved' THEN e.amount ELSE 0 END),0) AS pending")->first();
            $ordered = fn () => $base()->orderBy('e.expense_date', $overview ? 'desc' : 'asc')->orderBy('e.transaction_time', $overview ? 'desc' : 'asc')->orderBy('e.id', $overview ? 'desc' : 'asc');
            $entries = $ordered()->select('e.*')->selectRaw('COALESCE(sc.name,c.name) AS head')->paginate($perPage, ['*'], 'page', $overview ? 1 : null)->withQueryString();
            $offset = ($entries->currentPage() - 1) * $perPage;
            $running = $offset > 0 ? (float) DB::query()->fromSub($ordered()->select('e.amount', 'e.approval_status')->limit($offset), 'previous_expenses')
                ->where('approval_status', 'approved')->sum('amount') : 0.0;
            $entries->getCollection()->transform(function ($entry) use (&$running) {
                $entry->posted = $entry->approval_status === 'approved';
                if ($entry->posted) {
                    $running += (float) $entry->amount;
                }
                $entry->running = $running;
                $entry->source = $entry->reimburse_to_user_id ? 'Own pocket' : ($entry->petty_cash_float_id ? 'Company advance' : ($entry->bank_id ? 'Bank' : 'Cash'));

                return $entry;
            });

            $byHead = $overview ? $base()->where('e.approval_status', 'approved')
                ->groupBy('e.expense_sub_category_id', 'e.expense_category_id', 'sc.name', 'c.name')
                ->selectRaw("COALESCE(sc.name, c.name, 'Uncategorised') AS name, COUNT(*) AS n, SUM(e.amount) AS total")
                ->addSelect('e.expense_sub_category_id as head_id', 'e.expense_category_id as category_id')
                ->orderByDesc('total')->get()->map(fn ($row) => (array) $row) : collect();

            return ['entries' => $entries, 'total' => $totals->approved, 'pending' => $totals->pending,
                'byHead' => $byHead, 'byMonth' => collect(), 'byPayer' => collect(), 'owed' => collect()];
        }

        $entries = $base()
            ->orderBy('e.expense_date')->orderBy('e.transaction_time')->orderBy('e.id')
            ->get([
                'e.id', 'e.expense_date', 'e.title', 'e.description', 'e.amount', 'e.reference',
                'e.approval_status', 'e.reference_user_name', 'e.reimburse_to_user_id',
                'e.petty_cash_float_id', 'e.bank_id', 'e.payment_mode', 'e.expense_sub_category_id', 'e.expense_category_id',
                DB::raw('COALESCE(sc.name, c.name) AS head'),
            ]);

        $running = 0.0;
        foreach ($entries as $e) {
            $e->amount = (float) $e->amount;
            $e->posted = $e->approval_status === 'approved';
            if ($e->posted) {
                $running += $e->amount;
            }
            $e->running = $running;
            $e->source = $e->reimburse_to_user_id ? 'Own pocket'
                : ($e->petty_cash_float_id ? 'Company advance'
                : ($e->bank_id ? 'Bank' : 'Cash'));
        }

        $posted = $entries->where('posted', true);

        $byHead = $posted->groupBy(fn ($entry) => ProjectContext::key() === 'construction'
            ? $entry->expense_category_id.':'.$entry->expense_sub_category_id : $entry->head)->map(fn ($g) => [
                'name' => $g->first()->head ?: 'Uncategorised', 'n' => $g->count(), 'total' => $g->sum('amount'),
                'head_id' => $g->first()->expense_sub_category_id, 'category_id' => $g->first()->expense_category_id,
            ])->sortByDesc('total')->values();

        $byMonth = $posted->groupBy(fn ($e) => substr((string) $e->expense_date, 0, 7))
            ->map(fn ($g, $k) => ['month' => $k, 'n' => $g->count(), 'total' => $g->sum('amount')])
            ->sortKeys()->values();

        $byPayer = $posted->groupBy(fn ($e) => $e->reference_user_name ?: '—')
            ->map(fn ($g, $k) => [
                'name' => $k,
                'n' => $g->count(),
                'total' => $g->sum('amount'),
                'pocket' => $g->whereNotNull('reimburse_to_user_id')->sum('amount'),
            ])->sortByDesc('total')->values();

        // What the company still owes each person who spent their own money.
        // Company-wide, not per project: a person's balance does not split by
        // site, and the ledger it is read from is the ERP's (journal_items).
        $owed = collect();
        try {
            $svc = app(EmployeeReimbursementService::class);
            $ids = $posted->pluck('reimburse_to_user_id')->filter()->unique();
            $names = DB::table('users')->whereIn('id', $ids)->pluck('name', 'id');
            $owed = $ids->map(fn ($id) => [
                'user_id' => $id,
                'name' => $names[$id] ?? ('#'.$id),
                'owed' => $svc->owedTo((int) $id, self::company()),
            ])->values();
        } catch (\Throwable $e) {
            // The reimbursement ledger is a nicety here; the cost sheet stands without it.
        }

        return [
            'entries' => $entries,
            'total' => (float) $posted->sum('amount'),
            'pending' => (float) $entries->where('posted', false)->sum('amount'),
            'byHead' => $byHead,
            'byMonth' => $byMonth,
            'byPayer' => $byPayer,
            'owed' => $owed,
        ];
    }

    /**
     * The ids of a project's PENDING expenses (filed, no journal yet), with the same
     * search / date filters the ledger sheet is showing — what "Confirm all pending" acts on.
     */
    public static function pendingIds(int $departmentId, ?string $from = null, ?string $to = null, string $q = ''): array
    {
        return DB::table('expenses as e')
            ->leftJoin('expense_sub_categories as sc', 'sc.id', '=', 'e.expense_sub_category_id')
            ->where('e.company_id', self::company())
            ->where('e.expense_department_id', $departmentId)
            ->where('e.approval_status', '<>', 'approved')
            ->whereNull('e.deleted_at')
            ->when($from, fn ($qq) => $qq->whereDate('e.expense_date', '>=', $from))
            ->when($to, fn ($qq) => $qq->whereDate('e.expense_date', '<=', $to))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%'.$q.'%';
                $qq->where(fn ($w) => $w->where('e.title', 'like', $like)
                    ->orWhere('e.description', 'like', $like)
                    ->orWhere('e.reference_user_name', 'like', $like)
                    ->orWhere('sc.name', 'like', $like));
            })
            ->orderBy('e.expense_date')->orderBy('e.id')
            ->pluck('e.id')->map(fn ($i) => (int) $i)->all();
    }

    /** Company-6 expenses newest first, for the Accounts → Expenses tab. */
    public static function recent(string $q = '', ?int $departmentId = null, int $limit = 200): Collection
    {
        return DB::table('expenses as e')
            ->leftJoin('expense_departments as d', 'd.id', '=', 'e.expense_department_id')
            ->leftJoin('expense_sub_categories as sc', 'sc.id', '=', 'e.expense_sub_category_id')
            ->where('e.company_id', self::company())
            ->whereNull('e.deleted_at')
            ->when($departmentId, fn ($qq) => $qq->where('e.expense_department_id', $departmentId))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%'.$q.'%';
                $qq->where(fn ($w) => $w->where('e.title', 'like', $like)
                    ->orWhere('e.reference_user_name', 'like', $like)
                    ->orWhere('d.name', 'like', $like));
            })
            ->orderByDesc('e.expense_date')->orderByDesc('e.id')
            ->limit($limit)
            ->get([
                'e.id', 'e.expense_date', 'e.title', 'e.amount', 'e.approval_status',
                'e.reference_user_name', 'd.name as project', 'sc.name as head',
            ]);
    }
}
