# Epal ERP — domain map for EON

What EON must know about the system it sits on. Compiled from the ERP repository
(`Epal-It-Solutions/epal_erp_soft`, Laravel 12 / PHP 8.2 / MySQL, live at
`erp.epal.com.bd`, twelve companies in one database). EON never edits the ERP;
it reads it (database or API) and reasons over it.

---

## 1. Stack, routing, roles, companies

- **Laravel 12**, `spatie/laravel-permission` (roles + permissions), `laravel/sanctum`
  (API tokens), Pusher (chat + notifications), maatwebsite/excel, dompdf, 2FA.
- **Routing:** every web URL is `/{role-slug}/{resource}` (`/super-admin/journals`,
  `/accountant/report/trial-balance`). Route names `role.<resource>.<action>`.
- **Roles** (seeded): `super admin`, `admin`, `accountant`, `agent`, `vendor`,
  `customer`, `employee`. Permissions are `{view|create|edit|delete} {module}` plus
  workflow extras (`approve expense`, `approve leave`, `approve payment schedule`,
  `disburse employee request`, `view trial balance report`, …). Roles and permissions
  are developer-maintained only — never editable from a dashboard.
- **Multi-company:** no subdomains, no session company. `users.company_id` is the home
  company; transactional tables carry `company_id`. The active company on a page is
  route param → `?company=` → user's company. Per-company dashboard:
  `/{role}/company/{company}/dashboard`.
- **Companies confirmed in code:** `2` Epal Travels & Consultancy (revenue from
  ticket/visa/contract tables), `5` e-commerce company (extra e-commerce reports),
  `6` Wood Art Interiors (isolated `Modules/WoodArt`, `wa_*` tables). Others by
  name in the roadmap: Epal Constructions & Interiors, Epal Group, Epal It
  Solutions, Epal Manufacturing, Epal Online Shop, Epal Properties. **Ids beyond
  2/5/6 must be read from `companies` at runtime.**
- **Critical accounting rule:** shared posting accounts have `accounts.company_id = NULL`.
  Reports filter *accounts* by company but *journal items* by entry — a
  company-owned account posted to by another company shows a debit and hides the
  credit and silently breaks that company's trial balance.

## 2. Accounts / finance

### Chart of accounts — `accounts`
`code` (unique), `name`, `type ∈ {asset, liability, equity, income, expense}`,
`parent_id` (tree), `opening_balance`, `status`, `company_id` (NULL = shared).

Live numbering (renumbered 2026-08-12; the seeder is stale — trust `config/accounts.php` or the DB):

| Range | Meaning | Notable leaves |
| --- | --- | --- |
| 10xx | Cash | 1011 Petty Cash – Head Office (petty-cash pool), 1013 Office Cash, 1015 Petty Cash Float |
| 11xx | Bank accounts | one leaf per bank (`banks.account_id`) |
| 13xx | Receivables / advances | 1311 Customer Receivable, 1351 Director's Current Account, 1455–1457 Employee Loan |
| 1400 / 1470 | Inventory / Prepaid | |
| 21xx | Payables | 2111 Supplier Payable, 2210 Salaries Payable, 2240 Employee Expense Reimbursement Payable, 2270 Income Tax Payable, 2280 Withholding Tax (TDS/VDS) Payable |
| 24xx–25xx | Borrowings | 2410 Short-term Loan, 2440 Credit Card Payable, 2510 Bank Loan LT, 2520 Party Loan LT, 2560 Vehicle Loan |
| 3xxx | Equity | 3110 Owner Investment, 3210 Drawings, 3310 Retained Earnings, 3400 Opening Balance Equity |
| 4xxx | Income | 4110 Air Ticket Sales, 4120 Visa Service Income, 4150 Contract Flight & File Revenue, 4160 Travel Commission, 4610 Product Sales |
| 5xxx | Direct cost | 5110 Air Ticket Purchase Cost, 5120 Visa Processing Cost, 5150 Contract Flight & File Cost, 5610 Purchase / COGS |
| 6xxx–7xxx | Operating expenses | 6110 Salary Expense, 7400 Miscellaneous Expenses (fallback) |
| 8xxx | Finance | 8110 Interest Income (typed *expense* in the chart — known bug), 8520 Interest Expense, 8530 Loan Processing Fee |

### General ledger — `journal_entries` + `journal_items`
- Header: `company_id`, `created_by`, `date`, `reference`, `source` (`sale`, `purchase`,
  `expense`, `salary`, `employee_ledger`, `ticket_sale`, `visa_sale`, `financing`…),
  `source_id`, `description`, `reversed_journal_entry_id`.
- Lines: `account_id`, **`debit`, `credit`** (true double entry), `note`,
  `party_type ∈ {customer, supplier, agent, vendor, employee}`, `party_id`
  (polymorphic: customers / suppliers / users — no FK).
- Corrections are **reversals**, never edits.
- Vouchers are print views over journal entries (`journals.voucher`, `journals.party-voucher`).

### Other finance tables
- `banks` — name, type ∈ {bank, mobile_banking, digital_wallet, cash}, `balance`,
  `company_id`, **`account_id` → GL leaf**. `bank_transfers` (pending/completed/cancelled).
- `payment_schedules` — the AR/AP diary: `type ∈ {receive, pay}`, `party_type`,
  `party_id`, `amount`, `paid_amount`, `scheduled_date`, `reschedule_count`,
  `status ∈ {pending, paid, overdue, cancelled}`, `priority ∈ {high, medium, low}`,
  approvals; `payment_schedule_logs` (rescheduled/approved/rejected/paid/cancelled).
  Daily job `schedules:mark-overdue` at 00:05.
- `transactions` — older customer/supplier running-balance ledger (`old_balance`,
  `debit` = out, `credit` = in, `balance`). Not the GL.
- `petty_cash_floats` (custodian, `float_limit`) + `petty_cash_transactions` (issue/return).
- `company_daily_funds` — a cash spend ceiling per company per date range.
- `expense_reimbursements` — paying staff back.
- **Financing (loan book):** `financing_loans` (`direction ∈ {lent, borrowed}`,
  principal, `interest_method ∈ {none, flat, reducing}`, tenure, EMI, status),
  `financing_schedules` (instalments due/partial/paid/waived), `financing_transactions`
  (disburse/receipt/repayment/write_off/adjustment, principal/interest/fee/TDS parts),
  `financing_capital_movements` (investment/drawings).
- No fiscal-year, cost-centre, VAT/TDS tables yet — roadmap only.

## 3. Expenses
`expense_categories` (`company_id` NULL = shared, **`account_id`** = GL mapping),
`expense_sub_categories`, `expense_departments`, `expenses` (`amount`,
`payment_mode ∈ {cash, mobile_banking, bank_transfer, digital_wallet, other}`,
`bank_id`, `petty_cash_float_id`, `reimburse_to_user_id`, `expense_date`,
**`approval_status ∈ {pending, approved}`**, `approved_by/at`), `expense_items`,
`expense_budgets` (`period ∈ {Weekly, Monthly, Quarterly, Yearly}`, `amount`,
`threshold` % default 80).

**Posting rule (on approval):** Dr `expenses.account_id` (from the category, fallback
7400). Cr resolved in order: reimburse-to-user → 2240 liability; petty-cash float →
float account (split over-float to 2240 with the custodian as party); bank → the
bank's GL leaf; else the petty-cash pool 1011 (fallback 1013).

## 4. HR / payroll
- `users` (+ `employee_profiles`: `joining_date`, **`salary`** — the figure payroll uses,
  `department_id`, `designation_id`, `employment_type`), `departments`, `designations`,
  `employee_documents`, `employee_promotions`, `employee_resignations`.
- `shifts` (`start_time`, `end_time`, `holidays` JSON weekdays), `attendence_settings`
  (grace minutes), `attendances` (`status ∈ {present, absent, leave, holiday}`,
  `source ∈ {machine, manual}`, selfie), `attendance_logs` (raw punches), devices,
  `holidays`, `leave_types` (`max_leaves_count`), `leaves` (pending/approved/rejected).
- Payroll: `salary_templates`, **`employee_salaries`** (`month`, `year`, `gross_salary`,
  `loan_deduction`, `advance_salary_deduction`, `absent_deduction`, `leave_deduction`,
  `late_deduction`, `early_leave_deduction`, `salary_adjustment`, `total_deductions`,
  `net_salary`, `overtime_salary`, `status ∈ {Pending, Paid}`), `payslips`, `payments`,
  `advance_salaries`, `loans` (+ `loan_transactions`), `employee_ledger` (running
  balance), `salary_reconciliations`, `commissions`.
- Requests: `employee_requests` (`status ∈ {pending, under_review, approved, rejected,
  fulfilled, closed}`), `require_assignments`, disbursements, recoveries.
- **No PF, gratuity, appraisal or income-tax tables.**

**Payroll formulas (`PayrollService`, shared by the form and `payroll:generate-monthly` on the 1st at 01:00):**
- `daily = salary / days_in_month`, `hourly = daily / 9`, `minute = hourly / 60`
- `absent_deduction = daily × absent_days`; `leave_deduction = daily × leave_days`
- `late_deduction = late_minutes × minute` **only when late_minutes ≥ 120 in the month**
- `early_out_deduction = early_minutes × minute` (early = >10 min before shift end; waived if approved leave covers the day)
- overtime counts from 60 min past shift end and pays only if `users.overtime_eligible`
- minus running-loan EMI and any approved advance for the month
- side effects: employee-ledger row, loan repayment, salary journal
  (**Dr 6110 Salary Expense; Cr bank leaf if paid, else Cr 2210 Salaries Payable**),
  and a `payment_schedules` row (`type = pay`, `party_type = employee`) if unpaid.
- Leave balance = `max_leaves_count` − approved days this year.

## 5. CRM & projects
`leads` (`status ∈ {new, contacted, qualified, proposal_sent, negotiation, won, lost}`,
`lead_type ∈ {air_ticket, visa, software, interior, other}`, `assigned_to`),
`lead_sources`, `lead_followups`, `lead_reminders`, `lead_status_histories`, per-type
detail tables (`lead_air_tickets`, `lead_visas`, `lead_interiors`), `deals`
(`stage`, `amount`, `status ∈ {open, won, lost}`), `proposals`, `estimates`,
`contracts` (`contract_value`, `status ∈ {draft, signed, expired}`), `customers`,
`suppliers`, `projects` (`status ∈ {not_started, in_progress, completed, on_hold,
cancelled}`, `budget`, `team_members`).

## 6. Tasks & monitoring
Workspace == company. `boards`, `columns`, `tasks` (`project_id`, `board_id`,
`column_id`, `parent_id`, `priority ∈ {low, medium, high}`, `start_date`,
`due_date`, `completed_at`, `assigned_to`, `task_user` many-to-many),
`task_comments`, `task_attachments`, `task_activity_logs`, `labels`;
`office_todos` (+ assignees with per-assignee status, checklists).
Monitoring is attendance-driven: device punches, manual selfie check-in
(`users.allow_manual_attendance`), presence via `users.last_seen_at`
(online = seen within 5 min).

## 7. Inventory / sales / purchase / travel
Products, stocks, stock movements, purchases, sales (with `paid_amount`,
`due_amount`, `payment_status`, `due_date`), returns. Travel (company 2):
tickets, ticket sales/purchases/refunds/reissues, visa categories/processes/sales,
contract flights & files. Wood Art (company 6): `wa_*` tables keyed by string
`ext_id` — deliberately not FK-joined.

## 8. Dashboards & reports the boss already sees
- Company dashboard (`?period=YYYY-MM`): total sales, purchases, expenses (rent
  broken out), salary paid, bank balance (opening + Σdebit − Σcredit), headcount,
  `netProfit = sales − purchase − rent − otherExpenses − salaryPaid`, yesterday snapshot.
- Global dashboard: today's present/absent/on-leave, tasks, todos, leads, notices,
  tickets, payment-schedule summary.
- Reports: General Ledger, Trial Balance, Profit & Loss, Balance Sheet, Account Ledger,
  Account Statement, Journal Entries, Account Balances, Monthly Attendance, Task
  Report, Payroll Overview, Monthly Profit, Petty Cash, Expense, Party Statement,
  Bank Statement, Loan Statement, Payslip Statement.
- **Missing** (roadmap): AR/AP aging, cash-flow statement, opening-balance UI,
  Bangladesh VAT/TDS module, unified CRM analytics.

## 9. What EON can call
- Sanctum JSON API under `/api/admin/*` (login → token): `dashboard`, `attendance`,
  `tasks`, `projects`, `expense/*`, `banks`, `accounts`, `journals`,
  `payment-schedules`, `hrm/*`, `crm/*`, `payroll/*`,
  `report/{trial-balance, profit-loss, balance-sheet, general-ledger, …}`.
- Or a **read-only MySQL user** on the same database (what `server/` prefers on
  Hostinger — no ERP code changes, no token lifecycle).
- Realtime: Pusher private channels `notifications.{userId}`, `chat.{userId}`.
- Scheduler: `schedules:mark-overdue` daily, `payroll:generate-monthly` monthly.

## 10. Cautions
1. `AccountSeeder.php` shows the old numbering; trust `config/accounts.php` / DB.
2. Cross-company GL queries must respect accounts-by-company vs items-by-entry.
3. `journal_items.party_id` is polymorphic on `party_type`.
4. Two side ledgers (`transactions`, `employee_ledger`) are not the GL.
5. Wood Art is isolated by design; don't join `wa_*` to host tables.
