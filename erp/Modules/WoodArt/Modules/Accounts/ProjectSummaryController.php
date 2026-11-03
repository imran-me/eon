<?php

namespace Modules\WoodArt\Modules\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Accounts\Support\ExpensePoster;
use Modules\WoodArt\Modules\Accounts\Support\ProjectContext;
use Modules\WoodArt\Modules\Accounts\Support\SiteCosts;
use Modules\WoodArt\Modules\Accounts\Support\SitePosting;

/**
 * Project summary — the page the boss opens: the client's paid / due, what the suppliers are
 * owed, what the site team spent and was given, and what is waiting for the accountant, for ONE
 * project, on one screen. Plus each person's own account, and the form that records funds
 * provided to a person.
 *
 * Reading follows the rest of Project Accounts (ProjectContext::assertCanView()); writing needs
 * the same rights as the cost entry itself. A funds entry is a statement row (kind `person_fund`)
 * until it is posted; posting goes through SitePosting like every other statement row.
 */
class ProjectSummaryController extends Controller
{
    /**
     * The names the site sheets use for the site team (their REF. NAME), so pasted lines can say "Rony".
     * A deliberate twin of SeedMunshiVilla::PEOPLE — change one, change the other. Used only when the
     * person it points to is actually on this project's team.
     */
    private const ALIASES = [
        'boss' => 'Md Mohsin', 'mohsin' => 'Md Mohsin', 'rony' => 'Md Habibur Rahman', 'eman' => 'Eman Hossain',
        'nayeem' => 'Nayeemur Rahman', 'azizul' => 'Azizul Haque',
    ];

    private function canEdit(): bool
    {
        $u = auth()->user();

        return (bool) ($u && $u->can('view all expense') && $u->can('approve expense'));
    }

    private function guardWrite(int $department): object
    {
        ProjectContext::assertCanView();
        abort_unless($this->canEdit(), 403, 'Recording funds needs both “view all expense” and “approve expense”.');
        $dept = SiteCosts::department($department);
        abort_unless($dept, 404);
        abort_unless(SiteCosts::statementReady(), 503, 'The statement tables are not installed yet.');

        return $dept;
    }

    private function page(string $view, object $dept, array $data): View
    {
        return view($view, $data + [
            'waModule' => 'accounts',
            'waRoute' => ProjectContext::routeName(),
            'waCompanyName' => ProjectContext::name(),
            'waIcon' => 'cash-stack',
            'section' => 'projects',
            'sections' => AccountsController::tabs(),
            'tabsDense' => true,
            'dept' => $dept,
            'canEdit' => $this->canEdit(),
        ]);
    }

    /* ── the summary ──────────────────────────────────────────────────────── */

    public function summary(Request $request, string $role, int $department): View
    {
        ProjectContext::assertCanView();
        $dept = SiteCosts::department($department);
        abort_unless($dept, 404);

        $constructionOverview = ProjectContext::key() === 'construction' && $request->query('mode') !== 'posting';
        $sheet = $constructionOverview ? SiteCosts::sheet($department, null, null, '', 5, [], true) : SiteCosts::sheet($department);
        $st = SiteCosts::statementFor($department);
        $team = SiteCosts::team($department);

        $recorded = $sheet['total'] + $sheet['pending'];                     // every cost entry, confirmed or not
        $byPersons = (float) $team->sum('spent');
        $direct = max(0.0, $recorded - $byPersons);                        // paid by the company itself (bank / cash / float)
        $staffOwed = (float) $team->where('balance', '>', 0.004)->sum('balance');
        $staffHolds = (float) -$team->where('balance', '<', -0.004)->sum('balance');

        $pendingIds = SiteCosts::pendingIds($department);
        $unbookedRows = $st['ready'] ? $st['lines']->filter(fn ($l) => $l->kind !== 'person_fund'
            && ! ($l->covered_by_balance ?? false)
            && ! $l->journal_entry_id && ! $l->reversal_journal_entry_id)->count() : 0;
        $unfunded = $team->filter(fn ($p) => $p->spent > 0 && $p->provided <= 0.004);

        $view = $constructionOverview
            ? 'woodart::construction.overview' : 'woodart::project-summary';

        return $this->page($view, $dept, [
            'title' => $constructionOverview ? 'Project Overview' : $dept->name,
            'subtitle' => 'Client, suppliers and site team at a glance — one project, one screen.',
            'sheet' => $sheet,
            'postedSupplierBalances' => ProjectContext::key() === 'construction' ? SiteCosts::postedSupplierBalances($department) : collect(),
            'st' => $st,
            'team' => $team,
            'recorded' => $recorded,
            'direct' => $direct,
            'staffOwed' => $staffOwed,
            'staffHolds' => $staffHolds,
            'pendingN' => count($pendingIds),
            'unbooked' => $unbookedRows,
            'unfunded' => $unfunded,
            'plan' => $constructionOverview ? [] : $this->plan($department),
            'unpostedFunds' => $st['ready'] ? $st['lines']->where('kind', 'person_fund')->filter(fn ($l) => ! $l->journal_entry_id && ! $l->reversal_journal_entry_id)->count() : 0,
        ]);
    }

    /* ── this project's journals, without leaving Project Accounts ────────── */

    /**
     * The posted double entry behind THIS project: its cost entries (once confirmed) and its statement
     * rows (client bill and installments, funds provided, supplier balances). Read-only. It selects the
     * same journals the ERP's Manage Journals shows for the project filter, so the two always agree;
     * this page just keeps the person inside the project (its sheet, summary and team are one click away).
     */
    public function journals(Request $request, string $role, int $department): View
    {
        ProjectContext::assertCanView();
        $dept = SiteCosts::department($department);
        abort_unless($dept, 404);

        $co = ProjectContext::id();
        $from = $request->filled('date_from') ? (string) $request->query('date_from') : null;
        $to = $request->filled('date_to') ? (string) $request->query('date_to') : null;
        $q = trim((string) $request->query('q', ''));

        $base = fn () => JournalEntry::query()->where('company_id', $co)->where(function ($w) use ($department) {
            $w->where(fn ($x) => $x->where('source', 'project_statement')->whereIn('source_id', DB::table('project_statement')->where('expense_department_id', $department)->select('id')))
                ->orWhere(fn ($x) => $x->where('source', 'expense')->whereIn('source_id', DB::table('expenses')->where('expense_department_id', $department)->select('id')));
        });

        $entries = $base()->with('items.account')
            ->when($from, fn ($x) => $x->whereDate('date', '>=', $from))
            ->when($to, fn ($x) => $x->whereDate('date', '<=', $to))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('reference', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")->orWhere('beneficiary', 'like', "%{$q}%")))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(40)->withQueryString();

        $bankAccounts = DB::table('banks')->whereNotNull('account_id')->pluck('account_id')->flip();
        $all = $base()->with('items')->get();
        $touchesBank = $all->filter(fn ($j) => $j->items->contains(fn ($i) => isset($bankAccounts[$i->account_id])))->count();

        return $this->page('woodart::project-journals', $dept, [
            'title' => $dept->name,
            'subtitle' => 'Journals — the double entry behind this project.',
            'entries' => $entries,
            'totalCount' => $all->count(),
            'totalDebit' => (float) $all->sum(fn ($j) => $j->items->sum('debit')),
            'touchesBank' => $touchesBank,
            'from' => $from, 'to' => $to, 'q' => $q,
        ]);
    }

    /* ── one person's account ─────────────────────────────────────────────── */

    public function person(Request $request, string $role, int $department, int $user): View
    {
        ProjectContext::assertCanView();
        $dept = SiteCosts::department($department);
        abort_unless($dept, 404);

        $person = DB::table('users')->where('id', $user)->first(['id', 'name', 'phone']);
        $ledger = SiteCosts::personLedger($department, $user);
        abort_unless($person && $ledger['rows']->isNotEmpty(), 404);

        return $this->page('woodart::project-person', $dept, [
            'title' => $person->name,
            'subtitle' => 'Site team account on '.$dept->name.' — what he spent, the funds provided to him, and what is left.',
            'person' => $person,
            'ledger' => $ledger,
        ]);
    }

    /** Standalone A4 statement; never print the application shell/sidebar. */
    public function personPrint(Request $request, string $role, int $department, int $user): View
    {
        ProjectContext::assertCanView();
        $dept = SiteCosts::department($department);
        abort_unless($dept, 404);

        $person = DB::table('users')->where('id', $user)->first(['id', 'name', 'phone', 'email', 'address']);
        $ledger = SiteCosts::personLedger($department, $user);
        abort_unless($person && $ledger['rows']->isNotEmpty(), 404);

        return view('woodart::project-person-print', [
            'company' => Company::find(ProjectContext::id()),
            'dept' => $dept,
            'person' => $person,
            'ledger' => $ledger,
            'role' => $role,
        ]);
    }

    /* ── funds provided to a person ───────────────────────────────────────── */

    /** One entry from the form, or many pasted from Excel (date · person · amount · from · note). */
    public function fund(Request $request, string $role, int $department)
    {
        $dept = $this->guardWrite($department);
        $people = SiteCosts::team($department);

        $made = 0;
        $problems = [];

        if (trim((string) $request->input('bulk', '')) !== '') {
            foreach (preg_split('/\r\n|\r|\n/', (string) $request->input('bulk')) as $n => $line) {
                if (trim($line) === '') {
                    continue;
                }
                $parts = preg_split('/\t|\||;/', $line);
                $parts = array_map('trim', $parts);
                if (count($parts) < 3) {
                    $problems[] = 'Line '.($n + 1).': needs date, person and amount (separate with a tab, | or ;).';

                    continue;
                }
                [$date, $who, $amount] = $parts;
                $from = $parts[3] ?? null;
                $note = $parts[4] ?? null;

                try {
                    $this->insertFund($department, $people, $who, $date, $amount, $from, $note);
                    $made++;
                } catch (\InvalidArgumentException $e) {
                    $problems[] = 'Line '.($n + 1).': '.$e->getMessage();
                }
            }
        } else {
            $v = $request->validate([
                'person_id' => 'required|integer',
                'entry_date' => 'required|date',
                'amount' => 'required|numeric|gt:0|max:99999999999',
                'label' => 'nullable|string|max:160',
                'note' => 'nullable|string|max:500',
            ]);
            $name = (string) optional($people->firstWhere('user_id', (int) $v['person_id']))->name;
            try {
                $this->insertFund($department, $people, $name, $v['entry_date'], (string) $v['amount'], $v['label'] ?? null, $v['note'] ?? null);
                $made++;
            } catch (\InvalidArgumentException $e) {
                $problems[] = $e->getMessage();
            }
        }

        $msg = $made.' funds '.($made === 1 ? 'entry' : 'entries').' recorded (not in the books until posted).';

        return $this->backTo($role, $department, $request, $problems ? $msg.' '.count($problems).' not recorded: '.implode(' ', array_slice($problems, 0, 4)) : $msg, $problems === [] || $made > 0 ? ($problems ? 'error' : 'success') : 'error');
    }

    private function insertFund(int $department, $people, string $who, string $date, string $amount, ?string $from, ?string $note): void
    {
        $key = preg_replace('/\s+(vai|vi|bhai|boss)$/i', '', mb_strtolower(trim($who)));            // "Rony vai" → "rony"
        $who = self::ALIASES[$key] ?? $who;
        $person = $people->first(fn ($p) => mb_strtolower($p->name) === mb_strtolower($who));
        if (! $person && $who !== '') {
            $like = $people->filter(fn ($p) => str_contains(mb_strtolower($p->name), mb_strtolower($who)));
            $person = $like->count() === 1 ? $like->first() : null;      // a part of a name is enough only when it is unambiguous
        }
        if (! $person) {
            throw new \InvalidArgumentException('"'.$who.'" is not one of this project\'s site team ('.$people->pluck('name')->implode(', ').').');
        }

        $d = $this->parseDate($date);
        $a = round((float) str_replace(',', '', $amount), 2);
        if (! $d) {
            throw new \InvalidArgumentException('date "'.$date.'" is not valid (use 31.05.2026 or 2026-05-31).');
        }
        if ($a <= 0) {
            throw new \InvalidArgumentException('amount "'.$amount.'" must be above zero.');
        }

        DB::table('project_statement')->insert([
            'expense_department_id' => $department, 'kind' => 'person_fund',
            'party' => $person->name, 'party_id' => $person->user_id,
            'label' => $from ? mb_substr($from, 0, 160) : null, 'entry_date' => $d, 'amount' => $a,
            'note' => $note ? mb_substr($note, 0, 500) : null, 'counter' => 'intercompany',
            'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function parseDate(string $s): ?string
    {
        $s = trim($s);
        foreach (['d.m.Y', 'd/m/Y', 'd-m-Y', 'Y-m-d'] as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, $s);
                if ($c && $c->year >= 2000 && $c->year <= 2100) {
                    return $c->toDateString();
                }
            } catch (\Throwable $e) {
                // try the next format
            }
        }

        return null;
    }

    /** Post every funds entry of this project that is not in the books yet; each stands alone. */
    public function postFunds(Request $request, string $role, int $department)
    {
        $this->guardWrite($department);

        $ok = 0;
        $fail = [];
        foreach (DB::table('project_statement')->where('expense_department_id', $department)->where('kind', 'person_fund')
            ->whereNull('deleted_at')->whereNull('journal_entry_id')->whereNull('reversal_journal_entry_id')
            ->orderBy('entry_date')->orderBy('id')->get(['id', 'party', 'amount']) as $row) {
            try {
                SitePosting::post((int) $row->id, [], (int) auth()->id());
                $ok++;
            } catch (\RuntimeException $e) {
                $fail[] = $row->party.' ৳ '.number_format($row->amount).': '.$e->getMessage();
            }
        }

        return $this->backTo($role, $department, $request,
            $ok.' funds '.($ok === 1 ? 'entry' : 'entries').' posted to the books.'.($fail ? ' Not posted — '.implode(' · ', array_slice($fail, 0, 3)) : ''),
            $fail ? 'error' : 'success');
    }

    /**
     * "The company gave them the money, so they spent it": one funds entry per person for what he
     * spent and was not yet shown as given. The shortcut for the common case — it assumes the amounts
     * given equal the amounts spent; a person who held more, or was owed more, is corrected later
     * with an ordinary funds entry (or a reversal). Statement rows only; nothing is posted here.
     */
    public function coverSpend(Request $request, string $role, int $department)
    {
        $this->guardWrite($department);

        $made = 0;
        $sum = 0.0;
        foreach (SiteCosts::team($department)->where('balance', '>', 0.004) as $p) {
            DB::table('project_statement')->insert([
                'expense_department_id' => $department, 'kind' => 'person_fund',
                'party' => $p->name, 'party_id' => $p->user_id,
                'label' => 'Provided by the company — covers what he spent', 'entry_date' => $p->last ?: now()->toDateString(),
                'amount' => round($p->balance, 2),
                'note' => 'one entry from "company gave the money"; correct with a funds entry if he held more or was owed more',
                'counter' => 'intercompany', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $made++;
            $sum += $p->balance;
        }

        return $this->backTo($role, $department, $request,
            $made ? $made.' funds '.($made === 1 ? 'entry' : 'entries').' recorded (৳ '.number_format($sum).') — each person is now shown as fully funded. Post them with the button below.' : 'Nobody has an unfunded balance.',
            $made ? 'success' : 'error');
    }

    /* ── post everything to the books, in one go ──────────────────────────── */

    /** The cost head each supplier's balance is booked to (a supplier buys one kind of thing). */
    private const SUPPLIER_HEADS = [
        'asia bricks' => 'Bricks & Breaking', 'haji enterprise' => 'Rod', 'helal enterprise' => 'Sand (Bali)',
    ];

    /**
     * What "Post to the books" would do for this project, without doing it. Only the pieces that are
     * correct to post in bulk: the contract bill, the client's installments (received into another
     * company's account — never a bank), funds provided to the team, each supplier's NET balance (the
     * paid part is already a cost entry, so booking every bill would count it twice), and the pending
     * cost entries (the Expense desk's own Approve).
     */
    private function plan(int $department): array
    {
        $st = SiteCosts::statementFor($department);
        $unposted = fn (string $kind) => $st['lines']->where('kind', $kind)->filter(fn ($l) => ! $l->journal_entry_id && ! $l->reversal_journal_entry_id);

        $contract = (float) ($st['contract'] ?? 0);
        $billDue = max(0.0, round($contract - (float) $st['books']['billed'], 2));
        $inst = $unposted('client_installment');
        $funds = $unposted('person_fund');
        $noBank = SitePosting::banks()->isEmpty();

        $heads = DB::table('expense_sub_categories')->where('company_id', SiteCosts::company())->whereNull('deleted_at')->where('status', 1)->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => (int) $id]);

        $suppliers = collect();
        foreach ($st['suppliers'] as $sp) {
            $toBook = round((float) $sp['due'] - (float) ($st['supplierBooks'][$sp['party']] ?? 0), 2);
            if ($toBook <= 0.004) {
                continue;
            }
            $headName = self::SUPPLIER_HEADS[mb_strtolower($sp['party'])] ?? null;
            $suppliers->push((object) [
                'party' => $sp['party'], 'party_id' => $sp['party_id'], 'amount' => $toBook,
                'head' => $headName, 'head_id' => $headName ? ($heads[mb_strtolower($headName)] ?? null) : null,
            ]);
        }

        $pendingIds = SiteCosts::pendingIds($department);
        $pendingSum = $pendingIds ? (float) DB::table('expenses')->whereIn('id', $pendingIds)->sum('amount') : 0.0;

        return [
            'billDue' => $billDue, 'inst' => $inst, 'instSum' => (float) $inst->sum('amount'), 'noBank' => $noBank,
            'funds' => $funds, 'fundsSum' => (float) $funds->sum('amount'),
            'suppliers' => $suppliers, 'suppliersSum' => (float) $suppliers->filter(fn ($x) => $x->head_id)->sum('amount'),
            'pendingIds' => $pendingIds, 'pendingSum' => $pendingSum,
        ];
    }

    /** Every bank account's balance, from the journals — the figure that must not move. */
    private function bankBalance(): float
    {
        $accounts = DB::table('banks')->whereNotNull('account_id')->pluck('account_id');

        return $accounts->isEmpty() ? 0.0 : (float) DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->whereIn('ji.account_id', $accounts)->whereNull('ji.deleted_at')->whereNull('je.deleted_at')
            ->selectRaw('COALESCE(SUM(ji.debit) - SUM(ji.credit), 0) as b')->value('b');
    }

    public function postAll(Request $request, string $role, int $department)
    {
        $this->guardWrite($department);
        $take = fn (string $k) => $request->boolean($k);
        $log = [];
        $fail = [];

        try {
            DB::transaction(function () use ($department, $role, $take, &$log, &$fail) {
                $bankBefore = $this->bankBalance();
                $plan = $this->plan($department);
                $uid = (int) auth()->id();

                // 1 · the contract bill (Dr Receivable / Cr Contract revenue)
                if ($take('do_bill') && $plan['billDue'] > 0.004) {
                    try {
                        $first = DB::table('project_statement')->where('expense_department_id', $department)->where('kind', 'client_installment')->whereNull('deleted_at')->min('entry_date');
                        $id = DB::table('project_statement')->insertGetId([
                            'expense_department_id' => $department, 'kind' => 'client_bill', 'label' => 'Contract billed',
                            'entry_date' => $first ?: now()->toDateString(), 'amount' => $plan['billDue'],
                            'created_by' => $uid, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                        SitePosting::post($id, [], $uid);
                        $log[] = 'contract billed '.number_format($plan['billDue']);
                    } catch (\RuntimeException $e) {
                        $fail[] = 'contract bill: '.$e->getMessage();
                    }
                }

                // 2 · the client's installments, received into another company's account (never a bank)
                if ($take('do_inst') && $plan['inst']->isNotEmpty()) {
                    if (! $plan['noBank']) {
                        $fail[] = 'installments: '.ProjectContext::name().' has a bank of its own, so each installment needs its bank chosen on Entries & posting.';
                    } else {
                        $n = 0;
                        foreach ($plan['inst']->sortBy([['entry_date', 'asc'], ['id', 'asc']]) as $l) {
                            try {
                                SitePosting::post((int) $l->id, ['counter' => 'intercompany'], $uid);
                                $n++;
                            } catch (\RuntimeException $e) {
                                $fail[] = 'installment '.number_format($l->amount).': '.$e->getMessage();
                            }
                        }
                        $log[] = $n.' installment'.($n === 1 ? '' : 's').' (inter-company)';
                    }
                }

                // 3 · funds provided to the team
                if ($take('do_funds') && $plan['funds']->isNotEmpty()) {
                    $n = 0;
                    foreach ($plan['funds'] as $l) {
                        try {
                            SitePosting::post((int) $l->id, [], $uid);
                            $n++;
                        } catch (\RuntimeException $e) {
                            $fail[] = 'funds '.$l->party.' '.number_format($l->amount).': '.$e->getMessage();
                        }
                    }
                    $log[] = $n.' funds '.($n === 1 ? 'entry' : 'entries');
                }

                // 4 · each supplier's net balance, as one row posted at once
                if ($take('do_suppliers')) {
                    $n = 0;
                    foreach ($plan['suppliers'] as $sp) {
                        if (! $sp->head_id) {
                            $fail[] = $sp->party.' ৳ '.number_format($sp->amount).': no cost head is set for this supplier — post it from Entries & posting.';

                            continue;
                        }
                        try {
                            $id = DB::table('project_statement')->insertGetId([
                                'expense_department_id' => $department, 'kind' => 'supplier_balance', 'party' => $sp->party, 'party_id' => $sp->party_id,
                                'label' => 'Balance still owed (net of payments)', 'entry_date' => now()->toDateString(), 'amount' => $sp->amount,
                                'note' => 'booked from the supplier statement in one go; the paid part is already a cost entry',
                                'created_by' => $uid, 'created_at' => now(), 'updated_at' => now(),
                            ]);
                            SitePosting::post($id, ['cost_sub_category_id' => $sp->head_id, 'payable_kind' => 'supplier'], $uid);
                            $n++;
                        } catch (\RuntimeException $e) {
                            $fail[] = $sp->party.': '.$e->getMessage();
                        }
                    }
                    if ($n) {
                        $log[] = $n.' supplier balance'.($n === 1 ? '' : 's');
                    }
                }

                // 5 · the pending cost entries, through the Expense desk's own Approve
                if ($take('do_costs') && $plan['pendingIds']) {
                    $n = 0;
                    foreach ($plan['pendingIds'] as $id) {
                        try {
                            ExpensePoster::approve((int) $id, $role);
                            $n++;
                        } catch (\Throwable $e) {
                            $ref = DB::table('expenses')->where('id', $id)->value('reference');
                            $fail[] = ($ref ?: '#'.$id).': '.$e->getMessage();
                        }
                    }
                    $log[] = $n.' cost '.($n === 1 ? 'entry' : 'entries').' confirmed';
                }

                // The promise: no bank balance moves. If anything above touched one, undo the whole run.
                $moved = round($this->bankBalance() - $bankBefore, 2);
                if (abs($moved) > 0.004) {
                    throw new \RuntimeException('A bank balance would have changed by ৳ '.number_format($moved, 2).' — nothing was posted.');
                }
            });
        } catch (\RuntimeException $e) {
            return $this->backTo($role, $department, $request, $e->getMessage(), 'error');
        }

        $msg = $log ? 'Posted to the books: '.implode(' · ', $log).'. No bank balance changed.' : 'Nothing was posted.';
        if ($fail) {
            $msg .= ' Not posted — '.implode(' | ', array_slice($fail, 0, 4)).(count($fail) > 4 ? ' …' : '');
        }

        return $this->backTo($role, $department, $request, $msg, $log ? ($fail ? 'error' : 'success') : 'error');
    }

    private function backTo(string $role, int $department, Request $request, string $msg, string $flash)
    {
        $person = (int) $request->input('person');
        $route = $person ? 'project.person' : 'project.summary';
        $params = ['role' => $role, 'department' => $department] + ($person ? ['user' => $person] : []);

        return redirect()->route(ProjectContext::routeName($route), $params)->with($flash, $msg);
    }
}
