# Construction Project Accounts modernization

## Architecture assessment (2026-10-28)

- Runtime: Laravel 12.70.1, PHP 8.3. Existing Blade, Bootstrap Icons and suite layout are reused. No frontend framework or dependency changes.
- Construction is company 3, mounted at `/{role}/construction/accounts/*`. Wood Art uses the same controllers under company 6. `ProjectContext` derives the company from the URL, and `assertCanView()` permits company members or users with `view all expense`. Every project lookup additionally uses `SiteCosts::department()` to reject foreign-company and deleted departments. There is no narrower project-assignment policy in this module to reuse.
- A Project Accounts project is an `ExpenseDepartment`. `App\Models\Project` is a separate general project/CRM/task model with its own team, dates and budget. No verified foreign key joins the two. IDs or similar names cannot establish that relationship.
- `AccountsController`, `ProjectSummaryController`, `SiteCosts`, `SitePosting`, `ExpenseBulkController` and `SiteStatementController` remain the existing read/write boundaries. Bulk entry uses the original Expense desk's store/approve flow. Approvals, reversals, intercompany funding, supplier settlement and client posting are unchanged.
- `expenses.approval_status = approved` determines the existing Expense-desk cost measure. Deleted entries are excluded. Supplier settlement is not added to expenses. Posted supplier obligations include `supplier_balance`, and omit unposted/reversed/deleted statement rows, matching `SitePosting::supplierBooks()`.
- Statement tables are `project_terms` and `project_statement`, keyed by `expense_department_id`. Project terms have a unique project key; the statement has a project/kind index. Expenses already carry company/project/category keys. No new indexes, migrations or financial caching were justified for this presentation phase.
- Client/vendor identities use canonical users and company/project assignments. `reference_user_name` is the recorded payer text, `reimburse_to_user_id` is the own-pocket claimant, `user_id` is the creator, and `approved_by` is the approver. These responsibilities are not interchangeable.
- General `Project` team members and HR employee profiles exist, but are not assignments to Expense Departments. `SiteCosts::team()` is financial participation, not project membership.
- The existing expense attachment path is public file infrastructure; it is not a secure, categorized project document repository. Existing permission-protected expense detail/slip/edit workflows are retained instead of adding a second upload system.
- Expense budgets are company/category/period budgets, without a project allocation. They cannot support project category variance or forecasting. Material consumption, project material allocation and a reliable general Spent By identity are unavailable here.
- Existing journal screens provide the financial audit trail. Task activity logs refer to the separate task/project system and cannot be presented as this project's activity.

## Implemented safe phase

1. Construction-only compact portfolio with actual record-state KPIs, client filter, search, sorting, page sizes, last-expense date range, optional columns and all-matching-row CSV export.
2. Shared project header, bounded server-side switcher, browser-history/deep-link navigation, permission-aware contextual actions and horizontal tabs.
3. Overview with source-labelled financial KPIs, collection/cost percentages, approved cost chart, recent expenses/collections and posted supplier balances. The original accounting/site-team workspace remains accessible from More → Accounting & site team (`?mode=posting`).
4. Category cost report and exact classification drill-down (including duplicate head names and missing classifications), period controls, own-pocket claimant analysis, filtered expense register, CSV and existing A4 print/save-to-PDF flow.
5. Existing collection, supplier and approval/reversal entry points remain intact. Construction-only branches protect Wood Art and unrelated companies.

## Accounting issues for separate review

- Existing recorded installment totals include unposted and reversed statement entries. Posted client-book receipts exclude reversals. The UI explicitly distinguishes recorded installments from book receipts; this phase does not alter those definitions or historical records.
- Existing approved cost means Expense-desk entries, while additional supplier liabilities can post directly through statement journals. It is not a complete all-source project GL cost measure. Contract less this cost is neither recognized net profit nor a forecast. A broader cost measure requires accounting review to avoid counting historical paid costs/net-balance carries twice.
- Legacy read services use floating-point monetary calculations. New display differences/person totals use integer minor units, but this phase deliberately does not replace the established financial services or posting arithmetic.
- Original recorded supplier sheets and posted control-account balances can differ. The overview shows live posted balances, including suppliers represented only by a historical balance carry. The existing payable workspace still retains the original statement detail and posting state.

## Requires a separately approved data design

Lifecycle states (In Progress/On Hold/Completed), project codes, manager/member/role assignments, physical progress, dates/milestones, linking general tasks, project budget allocations/forecasts, material allocations/consumption, secure project documents and a nonfinancial project activity log. No values, links, assignments or financial records are fabricated for these features. CSV opens in spreadsheet applications; native XLSX and an application-generated analysis PDF are not implemented. The existing print flow can save its A4 transaction report as PDF.

## Verification and deployment scope

Run task-scoped Pint and Pint `--test`, the Construction workspace regression tests (isolated SQLite fixtures), existing unit tests, PHP syntax checks and Blade compilation. Browser acceptance with real company roles and production-like volumes remains necessary. No production financial data should be modified for verification.

This change adds no routes, classes, schema, assets or dependencies; no migration, autoload regeneration or asset publication is introduced. Follow the application's normal release procedure. If the server precompiles views in its release process, rebuild that cache as usual. No production-readiness claim is made for the deferred capabilities.

Verified locally: 14 Construction regression tests / 73 assertions; 19 existing unit tests / 42 assertions; task-scoped Pint and Pint `--test`; syntax checks on every changed/new PHP file; `php artisan view:cache`; `git diff --check`. SQLite extensions were enabled for the test commands only. Rendering tests use the real suite/project templates with a minimal host shell and disposable in-memory fixtures. Browser access was unavailable and native screenshot capture timed out, so responsive visual/browser acceptance remains outstanding. No financial posting, migration or production-data modification was used for verification.

Owner follow-up: the company landing route `/construction/accounts/overview` now also selects a Construction-only dashboard with compact KPI cards, portfolio financial progress, approved cost breakdown, recent expenses, supplier outstanding and project Overview links. A regression test renders this specific landing view to prevent leaving the visible entry page unchanged again.

Navigation decision: Project Accounts now opens All Projects first, including the sectionless Construction URL. The separate combined dashboard remains at `/construction/accounts/overview`, labelled Overall Overview and placed second in the company tab band. Project names continue to open the individual project's Overview. Wood Art's default and tab labels remain unchanged.

2026-10-29: Added dedicated Project Information and Team Information tabs through the existing project route (`?view=information` and `?view=team`). Information reads the authorized department, its terms and client. Team explicitly reports the missing member-assignment relationship and separately displays existing financial site participants using `SiteCosts::team()`; it does not infer assigned roles from claims or funding. Assignment management, managers and planned dates still need an approved data design. Regression rendering now includes both new tabs (14 Construction tests / 90 assertions).
