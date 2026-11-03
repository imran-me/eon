<?php

namespace Modules\WoodArt\Modules\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Accounts\Support\ProjectContext;
use Modules\WoodArt\Modules\Accounts\Support\SiteCosts;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Wood Art · Accounts — everything this module owns lives in this folder.
 * Mirrors the reference's companies/woodart/modules/accounts/.
 *
 * ─── THIS IS NOT THE ERP'S ACCOUNTS ──────────────────────────────────────
 * The host ERP already has a live Accounts feature that every other company
 * uses: `Dashboard\AccountController`, routes `role.accounts.*`, URIs
 * `{role}/accounts` and `{role}/accounts/{account}`. This module is separate in
 * every dimension and must stay that way:
 *
 *     live ERP        {role}/accounts...          role.accounts.*
 *     Wood Art        {role}/woodart/accounts/... role.woodart.accounts
 *
 * Different URI, different route name, different controller namespace,
 * different view namespace (`woodart::accounts`). Nothing here reads, writes,
 * extends or overrides the live one. Owner's instruction, 2026-08-11: build the
 * Wood Art side only and leave the Laravel one exactly as it is.
 *
 * ─── DATABASE: READ-ONLY, THE ERP'S OWN EXPENSES ─────────────────────────
 * Still no models, no migrations and no tables of its own. The Expenses and
 * Project P&L tabs READ company 6's expenses from the ERP's Expense desk (see
 * Support\SiteCosts); the only writes are the ones ExpenseBulkController makes
 * by calling that desk's own store()/approve(). Every other tab still renders
 * the empty state, as the rest of the suite does.
 *
 * This matters more here than anywhere else in the suite. The reference's own
 * module.json carries an ownership note: this desk owns NO table — `acc_entries`
 * belongs to Master Accounts and is shared GROUP-WIDE, with Woodart merely one
 * company-scoped tenant of it. So whenever a data layer does arrive, writing it
 * naively would post Wood Art's money into books other companies read. That is
 * precisely the isolation rule in CLAUDE.md, and it is why there is no
 * persistence here at all yet.
 */
class AccountsController extends Controller
{
    /**
     * TAB labels — from module.json's menu, and identical to the reference's
     * own tab band (template.html:44-54) and to the sidebar's sub-items.
     */
    /**
     * The tabs a person SEES. The other seven sections (Income, Payroll,
     * Recurring, Banks, Manage Cash, Journals, Schedules) were empty shells that
     * would only ever duplicate the ERP's own Accounts (Manage Banks, Manage
     * Journals…), so they are not offered here — Wood Art has ONE set of books,
     * the ERP's, and this module is a window over it. Their URLs still work: see
     * LEGACY below.
     */
    public const TABS = [
        'overview' => 'Financial Overview',
        'projects' => 'All Projects',
        'expenses' => 'All Expenses',
        'receivables' => 'Client Receivables',
        'payables' => 'Supplier Payables',
    ];

    public static function tabs(): array
    {
        return ProjectContext::key() === 'construction' ? [
            'projects' => 'All Projects',
            'overview' => 'Overall Overview',
        ] : self::TABS;
    }

    /** Old section URLs (bookmarks, the old sidebar): sent to the Overview, never a 404. */
    private const LEGACY = ['income', 'payroll', 'recurring', 'banks', 'cash', 'schedules'];

    /**
     * Every section key the route accepts (its regex still lists all eleven).
     * Kept for TITLES/SUBTITLES and old links; the tab band comes from TABS.
     */
    public const SECTIONS = [
        'overview' => 'Overview',
        'projects' => 'All Projects',
        'receivables' => 'Client Receivables',
        'income' => 'Income',
        'expenses' => 'Expenses',
        'payables' => 'Payables',
        'pnl' => 'Project P&L',
        'parties' => 'Party Statement',
        'payroll' => 'Payroll',
        'recurring' => 'Recurring',
        'banks' => 'Banks',
        'cash' => 'Manage Cash',
        'journals' => 'Journals',
        'schedules' => 'Schedules',
    ];

    /**
     * PAGE titles — TAB_COPY[key][0] (view.js:492-504). Unlike every other
     * module in the suite these do NOT all match the tab labels: the Overview
     * tab titles the page "Accounts", Payables becomes "Vendor Payables" and
     * Schedules becomes "Payment Schedules". Keep both maps.
     */
    private const TITLES = [
        'overview' => 'Financial Overview',
        'projects' => 'All Projects',
        'receivables' => 'Client Receivables',
        'income' => 'Income',
        'expenses' => 'Expenses',
        'payables' => 'Vendor Payables',
        'pnl' => 'Project Cost',
        'parties' => 'Party Statement',
        'payroll' => 'Payroll',
        'recurring' => 'Recurring',
        'banks' => 'Banks',
        'cash' => 'Manage Cash',
        'journals' => 'Journals',
        'schedules' => 'Payment Schedules',
    ];

    /** Verbatim from the module's TAB_COPY (view.js:492-504). */
    private const SUBTITLES = [
        'overview' => 'Contract, collections, approved costs, receivables and payables at a glance.',
        'projects' => 'A compact financial register of every project.',
        'receivables' => 'Contract value, client collections and outstanding balances by project.',
        'income' => 'Project billings, design fees and everything else the business earned.',
        'expenses' => 'Every cost, by project — the ERP Expense desk\'s own entries.',
        'payables' => 'What suppliers and contractors are still owed, project by project.',
        'pnl' => 'Cost, contract value, client due and supplier due for every project — click one for its ledger sheet.',
        'parties' => 'Company-filtered vendor, agent and client statements from the group ledger.',
        'payroll' => 'Salary run, payslips, loans & advances — posted to the ledger.',
        'recurring' => 'Standing monthly costs — rent, utilities, retainers.',
        'banks' => "Woodart's own accounts and what is in them.",
        'cash' => 'Cash book, petty cash and cheques.',
        'journals' => 'The double entry behind every screen in this module.',
        'schedules' => 'Money promised for a future date, and what is overdue.',
    ];

    /** See ProjectsController::show() for why $role must be declared. */
    public function show(Request $request, string $role, ?string $section = null)
    {
        ProjectContext::assertCanView();
        $section ??= ProjectContext::key() === 'construction' ? 'projects' : 'overview';
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        // Project Cost was a second project list showing the same source data.
        // Keep old bookmarks working while making Projects the single home.
        if ($section === 'pnl') {
            return redirect()->route(ProjectContext::routeName(''), ['role' => $role, 'section' => ProjectContext::key() === 'construction' ? 'projects' : 'overview']);
        }

        // The vendors & clients register has its own controller.
        if ($section === 'parties') {
            return redirect()->route('role.party-statement.vendors', ['role' => $role, 'company' => ProjectContext::id()]);
        }

        if ($section === 'journals') {
            return redirect()->route('role.journals.index', [
                'role' => $role,
                'company_id' => ProjectContext::id(),
            ]);
        }

        // An old link to a tab that no longer exists lands on the Overview.
        if (\in_array($section, self::LEGACY, true)) {
            return redirect()->route(ProjectContext::routeName(''), ['role' => $role, 'section' => ProjectContext::key() === 'construction' ? 'projects' : 'overview']);
        }

        if (ProjectContext::key() === 'construction' && $section === 'projects') {
            return $this->constructionProjects($request);
        }

        if (ProjectContext::key() === 'construction' && $section === 'overview') {
            $ready = SiteCosts::statementReady();

            return view('woodart::construction.company-overview', [
                'waModule' => 'accounts', 'waRoute' => ProjectContext::routeName(), 'waCompanyName' => ProjectContext::name(),
                'waIcon' => 'buildings', 'section' => 'overview', 'sections' => self::tabs(), 'tabsDense' => true,
                'title' => 'Overall Overview', 'subtitle' => 'Combined financial summary of all Construction projects.',
                'ready' => $ready, 'totals' => $ready ? SiteCosts::overviewTotals() : [],
                'projects' => $ready ? SiteCosts::projectsPage([], 8) : null,
                'recentExpenses' => SiteCosts::expensesPage([], 6),
                'suppliers' => $ready ? SiteCosts::payablesPage([], 5) : null,
                'costHeads' => DB::table('expenses as e')
                    ->leftJoin('expense_sub_categories as sc', 'sc.id', '=', 'e.expense_sub_category_id')
                    ->leftJoin('expense_categories as c', 'c.id', '=', 'e.expense_category_id')
                    ->where('e.company_id', ProjectContext::id())->whereNull('e.deleted_at')->where('e.approval_status', 'approved')
                    ->groupBy('e.expense_category_id', 'e.expense_sub_category_id', 'sc.name', 'c.name')
                    ->selectRaw("COALESCE(sc.name,c.name,'Uncategorised') AS name, SUM(e.amount) AS total")
                    ->orderByDesc('total')->get(),
            ]);
        }

        return view('woodart::accounts', $this->siteData($request, $section) + [
            'waModule' => 'accounts',
            'waRoute' => ProjectContext::routeName(),
            'waCompanyName' => ProjectContext::name(),
            'waIcon' => 'cash-stack',
            'section' => $section,
            'sections' => self::tabs(),
            'title' => self::TITLES[$section],
            'subtitle' => self::SUBTITLES[$section],
            // Eleven tabs: the reference gives this band .tabs-dense.
            'tabsDense' => true,
        ]);
    }

    /** Construction-only presentation over the existing portfolio query; no financial writes. */
    private function constructionProjects(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:160', 'client' => 'nullable|integer|min:1', 'manager' => 'nullable|integer|min:1',
            'status' => 'nullable|in:enabled,disabled,unconfigured,settled,in_progress,on_hold,completed',
            'sort' => 'nullable|in:code,name,contract,received,cost,client_due,supplier_due',
            'direction' => 'nullable|in:asc,desc', 'per_page' => 'nullable|in:10,25,50,100',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : ''),
            'export' => 'nullable|in:csv',
        ]);
        $perPage = (int) ($filters['per_page'] ?? 25);
        $ready = SiteCosts::statementReady();
        if (($filters['export'] ?? '') === 'csv') {
            abort_unless($ready, 503, 'Project statements are not installed.');
            $rows = SiteCosts::projectsPage($filters, $perPage, true);

            return response()->streamDownload(function () use ($rows, $filters) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                $write = function (array $values) use ($out) {
                    fputcsv($out, array_map(fn ($v) => ! is_numeric($v) && preg_match('/^\s*[=+\-@]|^[\t\r\n]/u', (string) $v) ? "'".$v : $v, $values));
                };
                $write([ProjectContext::name(), 'Construction projects', 'Lifetime balances', now()->toDateTimeString()]);
                $write(['Applied filters', json_encode(Arr::except($filters, ['export']), JSON_UNESCAPED_UNICODE)]);
                $write(['Project ID', 'Project', 'Client', 'Record status', 'Contract', 'Recorded collections', 'Contract less recorded collections', 'Approved expense cost', 'Posted supplier payable', 'Last expense']);
                foreach ($rows as $row) {
                    $write([$row->id, $row->name, $row->client_name, $row->record_status ? 'Active' : 'Inactive', $row->contract_value,
                        $row->received, $row->contract_value === null ? '' : $row->client_due, $row->approved_cost, $row->supplier_due, $row->last_expense_date]);
                }
                fclose($out);
            }, 'construction-projects.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $departments = DB::table('expense_departments')->where('company_id', ProjectContext::id())->whereNull('deleted_at')->where('name', '<>', 'Others...');

        return view('woodart::construction.projects', [
            'waModule' => 'accounts', 'waRoute' => ProjectContext::routeName(), 'waCompanyName' => ProjectContext::name(),
            'waIcon' => 'buildings', 'section' => 'projects', 'sections' => self::tabs(), 'tabsDense' => true,
            'title' => 'Construction Projects', 'subtitle' => 'Project accounts, client collections and approved costs.',
            'filters' => $filters, 'ready' => $ready,
            'rows' => $ready ? SiteCosts::projectsPage($filters, $perPage) : null,
            'totals' => $ready ? SiteCosts::overviewTotals() : [],
            'counts' => ['all' => (clone $departments)->count(),
                'in_progress' => (clone $departments)->join('project_terms as progress_terms', 'progress_terms.expense_department_id', '=', 'expense_departments.id')->where('progress_terms.construction_status', 'in_progress')->count(),
                'on_hold' => (clone $departments)->join('project_terms as hold_terms', 'hold_terms.expense_department_id', '=', 'expense_departments.id')->where('hold_terms.construction_status', 'on_hold')->count(),
                'completed' => (clone $departments)->join('project_terms as completed_terms', 'completed_terms.expense_department_id', '=', 'expense_departments.id')->where('completed_terms.construction_status', 'completed')->count(),
                'unconfigured' => $ready ? (clone $departments)->leftJoin('project_terms as pt', 'pt.expense_department_id', '=', 'expense_departments.id')->whereNull('pt.contract_value')->count() : null],
            'clients' => $ready ? DB::table('project_terms as pt')->join('expense_departments as d', 'd.id', '=', 'pt.expense_department_id')
                ->join('users as u', 'u.id', '=', 'pt.client_party_id')->where('d.company_id', ProjectContext::id())->whereNull('d.deleted_at')
                ->distinct()->orderBy('u.name')->get(['u.id', 'u.name']) : collect(),
            'managers' => $ready ? DB::table('project_terms as pt')->join('expense_departments as d', 'd.id', '=', 'pt.expense_department_id')
                ->join('users as u', 'u.id', '=', 'pt.manager_user_id')->where('d.company_id', ProjectContext::id())->whereNull('d.deleted_at')
                ->distinct()->orderBy('u.name')->get(['u.id', 'u.name']) : collect(),
        ]);
    }

    /**
     * Data for the two tabs that read real figures. Wrapped so a missing table
     * or a lagging deploy degrades to the empty state instead of a 500 — the
     * tab simply shows "no projects yet".
     */
    private function siteData(Request $request, string $section): array
    {
        if (! \in_array($section, ['overview', 'projects', 'expenses', 'receivables', 'pnl', 'payables'], true)) {
            return [];
        }

        try {
            $filters = [
                'q' => trim((string) $request->query('q', '')),
                'project' => (int) $request->query('project') ?: null,
                'status' => trim((string) $request->query('status', '')),
                'category' => (int) $request->query('category') ?: null,
                'from' => $request->filled('from') ? (string) $request->query('from') : null,
                'to' => $request->filled('to') ? (string) $request->query('to') : null,
                'sort' => trim((string) $request->query('sort', '')),
                'direction' => $request->query('direction') === 'desc' ? 'desc' : 'asc',
            ];
            $projects = DB::table('expense_departments')->where('company_id', ProjectContext::id())
                ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);
            $categories = DB::table('expense_categories')->where(fn ($q) => $q->where('company_id', ProjectContext::id())->orWhereNull('company_id'))
                ->whereNull('deleted_at')->where('status', 1)->orderBy('name')->get(['id', 'name']);

            return match ($section) {
                'overview' => ['financialTotals' => SiteCosts::overviewTotals(), 'projectRows' => SiteCosts::projectsPage(['status' => 'active'], 8)],
                'projects', 'pnl' => ['projectRows' => SiteCosts::projectsPage($filters), 'filters' => $filters],
                'expenses' => ['expenseRows' => SiteCosts::expensesPage($filters), 'projectOptions' => $projects, 'categoryOptions' => $categories, 'filters' => $filters],
                'receivables' => ['receivableRows' => SiteCosts::receivablesPage($filters), 'filters' => $filters],
                'payables' => ['payableRows' => SiteCosts::payablesPage($filters), 'projectOptions' => $projects, 'filters' => $filters],
                default => [],
            };
        } catch (\Throwable $e) {
            report($e);

            return ['financialTotals' => [], 'projectRows' => null, 'expenseRows' => null, 'receivableRows' => null,
                'payableRows' => null, 'projectOptions' => collect(), 'categoryOptions' => collect(), 'filters' => []];
        }
    }

    /** One project's cost sheet — every entry, running total, by head / month / payer. */
    public function project(Request $request, string $role, int $department): View|StreamedResponse
    {
        ProjectContext::assertCanView();
        $dept = SiteCosts::department($department);
        abort_unless($dept, 404);

        $constructionFilters = ProjectContext::key() === 'construction' ? $request->validate([
            'head' => 'nullable|integer|min:1', 'category' => 'nullable|integer|min:1',
            'person' => 'nullable|integer|min:1', 'approval' => 'nullable|in:approved,pending',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : ''),
            'q' => 'nullable|string|max:160', 'per_page' => 'nullable|in:10,25,50,100',
            'without_head' => 'nullable|boolean', 'without_category' => 'nullable|boolean', 'export' => 'nullable|in:csv',
            'view' => 'nullable|in:expenses,collections,payables,reports,information,team,budget,timeline,materials,documents,activity',
        ]) : [];

        $from = $request->filled('from') ? (string) $request->query('from') : null;
        $to = $request->filled('to') ? (string) $request->query('to') : null;
        $q = trim((string) $request->query('q', ''));

        $workspace = (string) $request->query('view', 'expenses');
        $workspaces = ProjectContext::key() === 'construction'
            ? ['expenses', 'collections', 'payables', 'reports', 'information', 'team', 'budget', 'timeline', 'materials', 'documents', 'activity']
            : ['expenses', 'collections', 'payables', 'reports'];
        abort_unless(in_array($workspace, $workspaces, true), 404);

        if (in_array($workspace, ['information', 'team', 'budget', 'timeline', 'materials', 'documents', 'activity'], true)) {
            abort_if($request->boolean('print') || $request->filled('export'), 404);
            $terms = SiteCosts::statementReady() ? DB::table('project_terms')->where('expense_department_id', $department)->first() : null;
            $client = $terms?->client_party_id ? DB::table('users')->where('id', $terms->client_party_id)->first(['id', 'name']) : null;

            $workspaceTitles = [
                'information' => ['Project Information', 'info-circle'],
                'team' => ['Team & Tasks', 'people'],
                'budget' => ['Budget & Profitability', 'calculator'],
                'timeline' => ['Timeline & Milestones', 'signpost-split'],
                'materials' => ['Materials', 'boxes'],
                'documents' => ['Documents & Files', 'folder2-open'],
                'activity' => ['Activity Log', 'activity'],
            ];
            [$workspaceTitle, $workspaceIcon] = $workspaceTitles[$workspace];

            return view('woodart::construction.project-information', [
                'waModule' => 'accounts', 'waRoute' => ProjectContext::routeName(), 'waCompanyName' => ProjectContext::name(),
                'waIcon' => $workspaceIcon, 'section' => 'projects', 'sections' => self::tabs(),
                'title' => $workspaceTitle,
                'subtitle' => $dept->name, 'tabsDense' => true, 'dept' => $dept, 'workspace' => $workspace,
                'terms' => $terms, 'statement' => ['client' => $client],
                'sitePeople' => $workspace === 'team' ? SiteCosts::team($department) : collect(),
                'operationalSheet' => in_array($workspace, ['budget', 'activity'], true) ? SiteCosts::sheet($department) : null,
                'canEdit' => (bool) (auth()->user()?->can('view all expense') && auth()->user()?->can('approve expense')),
            ]);
        }

        if (ProjectContext::key() === 'construction' && ($constructionFilters['export'] ?? '') === 'csv') {
            abort_unless(in_array($workspace, ['expenses', 'reports'], true), 404);
            $sheet = SiteCosts::sheet($department, $from, $to, $q, null, $constructionFilters);

            return response()->streamDownload(function () use ($sheet, $dept, $constructionFilters) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                $write = function (array $values) use ($out) {
                    fputcsv($out, array_map(fn ($v) => ! is_numeric($v) && preg_match('/^\s*[=+\-@]|^[\t\r\n]/u', (string) $v) ? "'".$v : $v, $values));
                };
                $write([ProjectContext::name(), $dept->name, 'Expense transactions', now()->toDateTimeString()]);
                $write(['Applied filters', json_encode(Arr::except($constructionFilters, ['export']), JSON_UNESCAPED_UNICODE)]);
                $write(['Approved total', $sheet['total'], 'Pending total', $sheet['pending']]);
                $write(['Date', 'Head', 'Title', 'Description', 'Recorded paid by', 'Own-pocket claimant ID', 'Payment source', 'Reference', 'Amount', 'Approval']);
                foreach ($sheet['entries'] as $entry) {
                    $write([$entry->expense_date, $entry->head, $entry->title, $entry->description, $entry->reference_user_name,
                        $entry->reimburse_to_user_id, $entry->source, $entry->reference, $entry->amount, $entry->approval_status]);
                }
                fclose($out);
            }, 'project-'.$department.'-expenses.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        if ($request->boolean('print')) {
            $ids = SiteCosts::sheet($department, $from, $to, $q, null, $constructionFilters)['entries']->pluck('id');
            $datas = Expense::where('company_id', ProjectContext::id())->where('expense_department_id', $department)
                ->whereIn('id', $ids)->with(['user', 'company', 'bank', 'expense_category', 'expense_sub_category', 'expenseDepartment', 'pettyCashFloat.custodian', 'reimburseTo'])->orderBy('expense_date')->orderBy('id')->get();

            return view('expenses.print', ['datas' => $datas, 'company' => Company::find(ProjectContext::id()),
                'printDateLabel' => ($from || $to) ? ($from ?: 'Start').' to '.($to ?: 'Present') : 'All dates',
                'filterLabels' => ['department' => $dept->name, 'user' => null, 'company' => ProjectContext::name(), 'bank' => null, 'category' => null, 'subcategory' => null, 'source' => null]]);
        }

        return view(ProjectContext::key() === 'construction' && $workspace === 'reports' ? 'woodart::construction.analysis' : 'woodart::project-sheet', [
            'waModule' => 'accounts',
            'waRoute' => ProjectContext::routeName(),
            'waCompanyName' => ProjectContext::name(),
            'waIcon' => 'cash-stack',
            'section' => 'projects',
            'sections' => self::tabs(),
            'title' => ProjectContext::key() === 'construction' ? match ($workspace) {
                'reports' => 'Cost Analysis', 'expenses' => 'Expense Register', 'collections' => 'Client Collections', 'payables' => 'Supplier Payables',
            } : $dept->name,
            'subtitle' => ucfirst($workspace).' — '.$dept->name,
            'workspace' => $workspace,
            'tabsDense' => true,
            'dept' => $dept,
            'sheet' => SiteCosts::sheet($department, $from, $to, $q, $workspace === 'expenses' ? (int) ($constructionFilters['per_page'] ?? 25) : null, $constructionFilters),
            'constructionFilters' => $constructionFilters,
            'statement' => SiteCosts::statementFor($department),
            'canEdit' => (bool) (auth()->user()?->can('view all expense') && auth()->user()?->can('approve expense')),
            'from' => $from,
            'to' => $to,
            'q' => $q,
        ]);
    }
}
