<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Modules\WoodArt\Modules\Accounts\AccountsController;
use Modules\WoodArt\Modules\Accounts\ProjectSummaryController;
use Modules\WoodArt\Modules\Accounts\Support\ProjectContext;
use Modules\WoodArt\Modules\Accounts\Support\SiteCosts;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConstructionWorkspaceTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->beforeBootstrapping(BootProviders::class, function () {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
            DB::purge('sqlite');
            Schema::create('roles', function (Blueprint $t) {
                $t->id();
                $t->string('name');
            });
            DB::table('roles')->insert(['name' => 'superadmin']);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        ProjectContext::force(null);
        $this->request();
        auth()->setUser((new User)->forceFill(['id' => 1, 'company_id' => 3, 'name' => 'Construction Reader'])->setRelation('roles', collect()));
        Gate::swap(new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user()));
        Gate::before(fn () => false);
        Schema::create('expense_departments', function (Blueprint $t) {
            $t->id();
            $t->integer('company_id');
            $t->string('name');
            $t->boolean('status')->default(true);
            $t->string('description')->nullable();
            $t->softDeletes();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('project_terms', function (Blueprint $t) {
            $t->id();
            $t->integer('expense_department_id')->unique();
            $t->integer('client_party_id')->nullable();
            $t->integer('manager_user_id')->nullable();
            $t->decimal('contract_value', 14, 2)->nullable();
            $t->string('project_code')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->unsignedTinyInteger('progress_percent')->nullable();
            $t->string('construction_status')->nullable();
            $t->string('project_type')->nullable();
            $t->text('note')->nullable();
        });
        Schema::create('project_statement', function (Blueprint $t) {
            $t->id();
            $t->integer('expense_department_id');
            $t->string('kind');
            $t->decimal('amount', 14, 2);
            $t->string('party')->nullable();
            $t->integer('party_id')->nullable();
            $t->date('entry_date')->nullable();
            $t->integer('journal_entry_id')->nullable();
            $t->integer('reversal_journal_entry_id')->nullable();
            $t->softDeletes();
        });
        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('expense_sub_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->integer('company_id');
            $t->integer('expense_department_id');
            $t->integer('expense_category_id')->nullable();
            $t->integer('expense_sub_category_id')->nullable();
            $t->decimal('amount', 14, 2);
            $t->string('approval_status');
            $t->date('expense_date');
            $t->string('transaction_time')->nullable();
            foreach (['title', 'description', 'reference', 'reference_user_name', 'payment_mode'] as $field) {
                $t->string($field)->nullable();
            }
            foreach (['reimburse_to_user_id', 'petty_cash_float_id', 'bank_id'] as $field) {
                $t->integer($field)->nullable();
            }
            $t->softDeletes();
        });
        DB::table('expense_departments')->insert([
            ['id' => 1, 'company_id' => 3, 'name' => 'Villa', 'status' => 1, 'deleted_at' => null],
            ['id' => 2, 'company_id' => 3, 'name' => '=Formula project', 'status' => 0, 'deleted_at' => null],
            ['id' => 6, 'company_id' => 6, 'name' => 'Interiors private', 'status' => 1, 'deleted_at' => null],
            ['id' => 7, 'company_id' => 3, 'name' => 'Deleted project', 'status' => 1, 'deleted_at' => '2026-01-01'],
        ]);
        DB::table('users')->insert([['id' => 10, 'name' => 'Client A'], ['id' => 11, 'name' => 'Client B']]);
        DB::table('project_terms')->insert([
            ['expense_department_id' => 1, 'client_party_id' => 10, 'contract_value' => '1000.00'],
            ['expense_department_id' => 2, 'client_party_id' => 11, 'contract_value' => '2000.00'],
            ['expense_department_id' => 6, 'client_party_id' => 11, 'contract_value' => '99999.00'],
        ]);
        DB::table('expense_categories')->insert(['id' => 1, 'name' => 'Construction']);
        DB::table('expense_sub_categories')->insert([['id' => 1, 'name' => 'Transport'], ['id' => 2, 'name' => 'Transport']]);
        $this->expense(1, 3, 1, 1, '100.10', 'approved');
        $this->expense(2, 3, 1, 2, '200.20', 'approved');
        $this->expense(3, 3, 1, 1, '50.50', 'pending');
        $this->expense(4, 6, 6, 1, '9999.00', 'approved');
        $this->expense(5, 6, 1, 1, '8888.00', 'approved'); // A malformed foreign-company association must still be excluded.
        DB::table('project_statement')->insert([
            ['id' => 1, 'expense_department_id' => 1, 'kind' => 'supplier_balance', 'party' => 'Balance-only supplier', 'amount' => '400.40', 'journal_entry_id' => 1, 'reversal_journal_entry_id' => null],
            ['id' => 2, 'expense_department_id' => 1, 'kind' => 'supplier_payment', 'party' => 'Balance-only supplier', 'amount' => '100.10', 'journal_entry_id' => 2, 'reversal_journal_entry_id' => null],
            ['id' => 3, 'expense_department_id' => 1, 'kind' => 'supplier_bill', 'party' => 'Reversed supplier', 'amount' => '900.00', 'journal_entry_id' => 3, 'reversal_journal_entry_id' => 4],
            ['id' => 4, 'expense_department_id' => 1, 'kind' => 'supplier_bill', 'party' => 'Unposted supplier', 'amount' => '800.00', 'journal_entry_id' => null, 'reversal_journal_entry_id' => null],
            ['id' => 5, 'expense_department_id' => 6, 'kind' => 'supplier_bill', 'party' => 'Private supplier', 'amount' => '9999.00', 'journal_entry_id' => 5, 'reversal_journal_entry_id' => null],
        ]);
    }

    protected function tearDown(): void
    {
        ProjectContext::force(null);
        parent::tearDown();
    }

    private function request(array $query = [], string $path = '/superadmin/construction/accounts/projects'): Request
    {
        $request = Request::create($path, 'GET', $query);
        $route = new Route('GET', '{role}/construction/accounts/{section?}', []);
        $route->bind($request);
        $route->setParameter('role', 'superadmin');
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);

        return $request;
    }

    private function expense(int $id, int $company, int $project, ?int $head, string $amount, string $approval): void
    {
        DB::table('expenses')->insert(['id' => $id, 'company_id' => $company, 'expense_department_id' => $project,
            'expense_category_id' => 1, 'expense_sub_category_id' => $head, 'amount' => $amount, 'approval_status' => $approval,
            'expense_date' => '2026-10-20', 'title' => 'Site purchase', 'reference_user_name' => 'Recorded payer']);
    }

    public function test_portfolio_filters_pagination_and_export_share_company_scoped_totals(): void
    {
        $page = SiteCosts::projectsPage([], 1);
        $this->assertSame(2, $page->total());
        $this->assertCount(1, $page);
        $filtered = SiteCosts::projectsPage(['client' => 10, 'from' => '2026-10-01', 'status' => 'enabled']);
        $this->assertSame([1], $filtered->pluck('id')->all());
        $this->assertEqualsWithDelta(300.30, $filtered->first()->approved_cost, .001);
        $this->assertEqualsWithDelta(300.30, $filtered->first()->supplier_due, .001);
        $this->assertEqualsWithDelta(50.50, $filtered->first()->pending_cost, .001);
        $this->assertSame([1], SiteCosts::projectsPage(['client' => 10], 25, true)->pluck('id')->all());
        $this->assertSame([2], SiteCosts::projectsPage(['status' => 'disabled'])->pluck('id')->all());
        $this->assertSame(0, SiteCosts::projectsPage(['from' => '2026-10-21'])->total());
    }

    public function test_duplicate_head_names_drill_into_the_exact_classification_and_approval(): void
    {
        $sheet = SiteCosts::sheet(1);
        $this->assertCount(2, $sheet['byHead']);
        $drill = SiteCosts::sheet(1, null, null, '', 10, ['head' => 1, 'approval' => 'approved']);
        $this->assertSame([1], $drill['entries']->pluck('id')->all());
        $this->assertEqualsWithDelta(100.10, $drill['total'], .001);
        $this->assertEquals(0, $drill['pending']);
        $pending = SiteCosts::sheet(1, null, null, '', 10, ['approval' => 'pending']);
        $this->assertSame([3], $pending['entries']->pluck('id')->all());
    }

    public function test_missing_heads_and_person_filters_do_not_broaden_the_register(): void
    {
        $this->expense(8, 3, 1, null, '10.01', 'approved');
        DB::table('expenses')->where('id', 8)->update(['reimburse_to_user_id' => 10]);
        $sheet = SiteCosts::sheet(1, null, null, '', 10, ['without_head' => 1, 'person' => 10]);
        $this->assertSame([8], $sheet['entries']->pluck('id')->all());
        $this->assertSame(0, SiteCosts::sheet(1, null, null, '', 10, ['person' => 11])['entries']->total());
    }

    public function test_posted_supplier_balances_exclude_reversals_unposted_and_foreign_company_rows(): void
    {
        $rows = SiteCosts::postedSupplierBalances(1);
        $this->assertCount(1, $rows);
        $this->assertSame('Balance-only supplier', $rows->first()->party);
        $this->assertEqualsWithDelta(300.30, $rows->first()->outstanding, .001);
        $this->assertTrue(SiteCosts::postedSupplierBalances(6)->isEmpty());
        $this->assertNull(SiteCosts::department(6));
        $this->assertNull(SiteCosts::department(7));
    }

    public function test_foreign_company_project_is_rejected_before_loading_its_finances(): void
    {
        try {
            (new ProjectSummaryController)->summary($this->request(), 'superadmin', 6);
            $this->fail('Foreign project was accessible.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_other_company_member_cannot_export_construction(): void
    {
        auth()->user()->company_id = 2;
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('These project accounts belong to another company.');
        (new AccountsController)->show($this->request(['export' => 'csv']), 'superadmin', 'projects');
    }

    public function test_csv_exports_all_matching_rows_and_neutralizes_spreadsheet_formulas_without_writes(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = (new AccountsController)->show($this->request(['export' => 'csv', 'per_page' => 10]), 'superadmin', 'projects');
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString("'=Formula project", $csv);
        $this->assertStringContainsString('Villa', $csv);
        $this->assertStringNotContainsString('Interiors private', $csv);
        $this->assertStringContainsString('Lifetime balances', $csv);
        foreach (DB::getQueryLog() as $query) {
            $this->assertMatchesRegularExpression('/^(select|pragma)/i', $query['query']);
        }
    }

    public function test_switcher_is_bounded_and_excludes_foreign_and_deleted_projects(): void
    {
        $data = ['dept' => SiteCosts::department(1)];
        $html = view('woodart::construction.header', $data)->render();
        $this->assertStringContainsString('Villa', $html);
        $this->assertStringNotContainsString('Interiors private', $html);
        $this->assertStringNotContainsString('Deleted project', $html);
        $this->assertStringNotContainsString('New Expense', $html);
        $this->request(['switch_q' => 'No match']);
        $this->assertStringContainsString('No projects match your search.', view('woodart::construction.header', $data)->render());
    }

    public function test_construction_pages_render_with_existing_suite_and_exact_drill_links(): void
    {
        // Replace only the host shell; render the real suite and project templates.
        $stub = sys_get_temp_dir().'/epal-construction-view-'.bin2hex(random_bytes(6));
        mkdir($stub.'/layout', 0777, true);
        file_put_contents($stub.'/layout/app.blade.php', "<!doctype html><html><body>@yield('main-content')</body></html>");
        view()->getFinder()->prependLocation($stub);
        view()->share('errors', new ViewErrorBag);
        try {
            $list = (new AccountsController)->show($this->request(), 'superadmin', 'projects')->render();
            $this->assertStringContainsString('Construction Projects', $list);
            $this->assertStringContainsString('project/1/summary', $list);
            $this->assertStringNotContainsString('Interiors private', $list);
            $this->assertStringContainsString('<nav class="wap-tabs', $list);

            $companyOverview = (new AccountsController)->show($this->request([], '/superadmin/construction/accounts/overview'), 'superadmin', 'overview');
            $this->assertSame('woodart::construction.company-overview', $companyOverview->name());
            $companyHtml = $companyOverview->render();
            $this->assertStringContainsString('Portfolio Financial Progress', $companyHtml);
            $this->assertStringContainsString('Approved Cost Breakdown', $companyHtml);
            $this->assertStringContainsString('Open Overview', $companyHtml);
            $this->assertStringNotContainsString('Interiors private', $companyHtml);
            $this->assertStringContainsString('<nav class="wap-tabs', $companyHtml);

            $dept = SiteCosts::department(1);
            $common = ['waModule' => 'accounts', 'waRoute' => ProjectContext::routeName(), 'waCompanyName' => 'Epal Constructions',
                'waIcon' => 'buildings', 'section' => 'projects', 'sections' => AccountsController::TABS, 'title' => 'Project Overview',
                'subtitle' => 'Construction workspace', 'dept' => $dept, 'sheet' => SiteCosts::sheet(1, null, null, '', 5, [], true)];
            $statement = ['ready' => true, 'contract' => '1000.00', 'client' => null, 'received' => '250.00',
                'books' => ['received' => '100.00', 'due' => '900.00'], 'installments' => collect()];
            $overview = view('woodart::construction.overview', $common + ['st' => $statement,
                'postedSupplierBalances' => SiteCosts::postedSupplierBalances(1), 'team' => collect(), 'pendingN' => 1, 'unbooked' => 2])->render();
            $this->assertStringContainsString('Project Information', $overview);
            $this->assertStringContainsString('Client Collection Progress', $overview);
            $this->assertStringContainsString('Project Cost Summary', $overview);
            $this->assertStringContainsString('Assigned Team Members', $overview);
            $this->assertStringContainsString('Recent Supplier Payments', $overview);
            $this->assertStringNotContainsString('<nav class="wap-tabs', $overview);
            $this->assertStringContainsString('aria-label="Project workspace"', $overview);

            $analysis = view('woodart::construction.analysis', array_merge($common, ['sheet' => SiteCosts::sheet(1), 'workspace' => 'reports', 'constructionFilters' => [],
                'from' => null, 'to' => null, 'q' => '', 'statement' => $statement]))->render();
            $this->assertStringContainsString('head=1', $analysis);
            $this->assertStringContainsString('head=2', $analysis);
            $this->assertStringContainsString('Person-wise Own-pocket Expenses', $analysis);
            $this->assertStringNotContainsString('<nav class="wap-tabs', $analysis);

            $expenses = view('woodart::project-sheet', array_merge($common, ['sheet' => SiteCosts::sheet(1, null, null, '', 10),
                'workspace' => 'expenses', 'constructionFilters' => ['head' => 1], 'from' => null, 'to' => null, 'q' => '',
                'canEdit' => false, 'statement' => $statement + ['banks' => collect(), 'heads' => collect()]]))->render();
            $this->assertStringContainsString('name="head" value="1"', $expenses);
            $this->assertStringContainsString('Project Expense Register', $expenses);
            $this->assertStringContainsString('Payment source', $expenses);
            $this->assertStringNotContainsString('<nav class="wap-tabs', $expenses);

            $informationRequest = $this->request(['view' => 'information'], '/superadmin/construction/accounts/project/1');
            $information = (new AccountsController)->project($informationRequest, 'superadmin', 1)->render();
            $this->assertStringContainsString('Basic Information', $information);
            $this->assertStringContainsString('Client A', $information);
            $this->assertStringContainsString('৳ 1,000.00', $information);
            $this->assertStringNotContainsString('<nav class="wap-tabs', $information);

            DB::table('expenses')->where('id', 1)->update(['reimburse_to_user_id' => 10]);
            $teamRequest = $this->request(['view' => 'team'], '/superadmin/construction/accounts/project/1');
            $team = (new AccountsController)->project($teamRequest, 'superadmin', 1)->render();
            $this->assertStringContainsString('Assigned Project Members', $team);
            $this->assertStringContainsString('Project assignments are not recorded yet.', $team);
            $this->assertStringContainsString('Site Financial Participants', $team);
            $this->assertStringContainsString('Client A', $team);
            $this->assertStringNotContainsString('Interiors private', $team);
            $this->assertStringNotContainsString('<nav class="wap-tabs', $team);

            foreach ([
                'budget' => 'Approved Cost by Category',
                'timeline' => 'No project milestones are recorded.',
                'materials' => 'Project material quantities are not tracked.',
                'documents' => 'No project document register is connected.',
                'activity' => 'Financial Activity Log',
            ] as $view => $expected) {
                $operationalRequest = $this->request(['view' => $view], '/superadmin/construction/accounts/project/1');
                $operational = (new AccountsController)->project($operationalRequest, 'superadmin', 1)->render();
                $this->assertStringContainsString($expected, $operational);
                $this->assertStringNotContainsString('Interiors private', $operational);
            }
        } finally {
            unlink($stub.'/layout/app.blade.php');
            rmdir($stub.'/layout');
            rmdir($stub);
        }
    }

    public function test_overview_aggregates_every_expense_but_loads_only_recent_rows(): void
    {
        for ($id = 20; $id < 32; $id++) {
            $this->expense($id, 3, 1, 1, '1.01', 'approved');
        }
        $sheet = SiteCosts::sheet(1, null, null, '', 5, [], true);
        $this->assertCount(5, $sheet['entries']);
        $this->assertEqualsWithDelta(312.42, $sheet['total'], .001);
        $this->assertCount(2, $sheet['byHead']);
        $this->assertEqualsWithDelta(112.22, $sheet['byHead']->firstWhere('head_id', 1)['total'], .001);
        $this->assertSame(31, $sheet['entries']->first()->id);
    }

    public function test_woodart_navigation_and_named_head_grouping_remain_unchanged(): void
    {
        $this->request([], '/superadmin/woodart/accounts/projects');
        $html = view('woodart::project-nav', ['dept' => SiteCosts::department(6)])->render();
        $this->assertStringContainsString('wa-project-nav', $html);
        $this->assertStringNotContainsString('wa-cpm-header', $html);
        $this->assertSame(6, ProjectContext::id());
        $this->assertSame(AccountsController::TABS, AccountsController::tabs());
    }

    public function test_construction_entry_opens_projects_and_keeps_overall_overview_separate(): void
    {
        $request = $this->request([], '/superadmin/construction/accounts');
        $page = (new AccountsController)->show($request, 'superadmin');
        $this->assertSame('woodart::construction.projects', $page->name());
        $this->assertSame('projects', $page->getData()['section']);
        $this->assertSame('projects', array_key_first($page->getData()['sections']));
        $this->assertSame('Overall Overview', $page->getData()['sections']['overview']);
        $this->assertSame(['projects', 'overview'], array_keys($page->getData()['sections']));

        $overview = (new AccountsController)->show($request, 'superadmin', 'overview');
        $this->assertSame('woodart::construction.company-overview', $overview->name());
        $this->assertSame('Overall Overview', $overview->getData()['title']);
    }

    public function test_portfolio_accepts_a_to_date_without_requiring_a_from_date(): void
    {
        $view = (new AccountsController)->show($this->request(['to' => '2026-10-31']), 'superadmin', 'projects');
        $this->assertSame([1], $view->getData()['rows']->pluck('id')->all());
    }

    public function test_filtered_expense_csv_matches_the_register_and_excludes_pending_rows(): void
    {
        $request = $this->request(['head' => 1, 'approval' => 'approved', 'export' => 'csv'], '/superadmin/construction/accounts/project/1');
        $response = (new AccountsController)->project($request, 'superadmin', 1);
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('100.1', $csv);
        $this->assertStringNotContainsString('200.2', $csv);
        $this->assertStringNotContainsString('50.5', $csv);
        $this->assertStringNotContainsString('9999', $csv);
    }
}
