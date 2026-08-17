<?php
declare(strict_types=1);

/* ============================================================
   Tools — what the language model may call. Each tool has a
   definition (name, description, inputSchema — camelCase for the
   PHP SDK) and an executor over the Analytics/Dataset/ERP layers.
   Descriptions say WHEN to call the tool, not just what it does.
   ============================================================ */
final class Tools
{
    private Analytics $A; private array $D;

    public function __construct(array $D, ?int $company = null) { $this->D = $D; $this->A = new Analytics($D, $company); }

    private static function obj(array $props, array $required = []): array { return ['type' => 'object', 'properties' => $props ?: new stdClass(), 'required' => $required]; }

    public function definitions(): array
    {
        $t = [
            ['name' => 'get_brief', 'description' => 'Call this for "brief", "morning report", "how are things", "what should I focus on", or any broad status question. Returns the KPI tiles, the ranked decision list across finance/people/CRM/operations, the approvals queue and a spoken summary. Prefer it over calling many smaller tools when the question is broad.', 'inputSchema' => self::obj([])],
            ['name' => 'get_kpis', 'description' => 'Headline numbers only: cash, receivables/payables (+overdue), revenue MTD and trend, opex, net profit, headcount, attendance today, payroll, pipeline, overdue tasks, projects at risk. Call for quick "how much / how many" questions.', 'inputSchema' => self::obj([])],
            ['name' => 'get_cash_position', 'description' => 'Bank and cash balances by account and by company, low accounts. Call for questions about cash, bank balances, liquidity.', 'inputSchema' => self::obj([])],
            ['name' => 'get_receivables', 'description' => 'Open customer receivables: total, overdue total, aging buckets (current/1-30/31-60/61-90/90+), top debtors, oldest overdue items, due in 7 days. Call for who owes us, collections, debtors, AR, aging.', 'inputSchema' => self::obj([])],
            ['name' => 'get_payables', 'description' => 'Open payables to suppliers/vendors/employees: total, overdue, aging, top creditors, late salaries, due in 7 days. Call for what we owe, AP, suppliers dues, bills to pay.', 'inputSchema' => self::obj([])],
            ['name' => 'get_profit_and_loss', 'description' => 'Income, direct cost, opex, finance cost, gross and net profit, margin, top income and expense accounts for a period. Call for profit, revenue, sales, margin, income statement questions. Dates ISO YYYY-MM-DD; omit for month-to-date.', 'inputSchema' => self::obj(['from' => ['type' => 'string', 'description' => 'start date YYYY-MM-DD'], 'to' => ['type' => 'string', 'description' => 'end date YYYY-MM-DD']])],
            ['name' => 'get_trial_balance', 'description' => 'Trial balance for the whole period on record: every account with debit/credit totals and whether it balances. Call for trial balance or "do the books balance".', 'inputSchema' => self::obj([])],
            ['name' => 'get_balance_sheet', 'description' => 'Assets, liabilities, equity with the current period profit. Call for balance sheet, net worth, what we own/owe.', 'inputSchema' => self::obj([])],
            ['name' => 'get_account_ledger', 'description' => 'Postings and running balance for one GL account code (e.g. 1311 Customer Receivable, 2210 Salaries Payable, 6110 Salary Expense). Call when asked about a specific account or code.', 'inputSchema' => self::obj(['code' => ['type' => 'string', 'description' => '4-digit account code'], 'limit' => ['type' => 'integer']], ['code'])],
            ['name' => 'get_expenses_vs_budget', 'description' => 'Expenses by category for a month against budgets (pct, over-budget list, pending approvals). Call for expenses, spending, budget, overspend, cost breakdown. month = YYYY-MM (default current).', 'inputSchema' => self::obj(['month' => ['type' => 'string']])],
            ['name' => 'get_attendance_today', 'description' => 'Who is present, absent, late, on leave, not punched, online right now, with names. Call for attendance, who is absent/late/in today, presence.', 'inputSchema' => self::obj([])],
            ['name' => 'get_attendance_patterns', 'description' => 'Chronic lateness and absence over the last N days (default 30) with per-employee minutes. Call for habitual late-comers, punctuality, attendance patterns, warning letters.', 'inputSchema' => self::obj(['days' => ['type' => 'integer']])],
            ['name' => 'get_payroll', 'description' => 'Payroll run for a month (default: latest run): heads, gross, deductions (late, absence, loans), net, unpaid payslips, by company, vs previous month. Call for payroll, salaries, payslips, wage bill.', 'inputSchema' => self::obj(['month' => ['type' => 'string', 'description' => 'YYYY-MM']])],
            ['name' => 'find_employee', 'description' => 'Look up an employee by (partial) name and return their profile plus a 30-day evaluation (score, grade, attendance, punctuality, tasks, sales, strengths, concerns). Call whenever a person is named or asked about, or for "evaluate X".', 'inputSchema' => self::obj(['name' => ['type' => 'string']], ['name'])],
            ['name' => 'get_pipeline', 'description' => 'CRM pipeline: open leads and value by stage, won/lost and conversion, cold/stale leads with owners, follow-ups due today. Call for leads, pipeline, sales funnel, CRM, follow-ups.', 'inputSchema' => self::obj([])],
            ['name' => 'get_tasks', 'description' => 'Overdue tasks with owners and days late, open counts, closed this week, per-person load and who is overloaded. Call for tasks, overdue work, workload, delivery.', 'inputSchema' => self::obj([])],
            ['name' => 'get_projects', 'description' => 'Active projects with progress vs elapsed time, budget burn, days left and a risk rating; the at-risk list first. Call for projects, delivery risk, deadlines.', 'inputSchema' => self::obj([])],
            ['name' => 'get_approvals', 'description' => 'Everything waiting for the boss: payroll run, expenses, leaves, salary advances, employee requests. Call for approvals, pending sign-offs, what is waiting on me.', 'inputSchema' => self::obj([])],
            ['name' => 'get_decisions', 'description' => 'The ranked list of decisions EON proposes right now across all layers (severity, why, recommendation). Call for "what needs attention", risks, priorities, problems.', 'inputSchema' => self::obj([])],
            ['name' => 'search_records', 'description' => 'Full-text search across employees, customers, suppliers, leads, projects, tasks, expenses and payment schedules by a word or name. Call when a name or reference is mentioned that no other tool covers.', 'inputSchema' => self::obj(['query' => ['type' => 'string'], 'limit' => ['type' => 'integer']], ['query'])],
            ['name' => 'record_action', 'description' => 'Record an instruction the boss gives through EON — an approval decision, a reminder to set, a draft to send, a note. EON is advisory: this logs the intent for the ERP/staff to execute; say so in your reply. kind: approve | reject | remind | send_draft | note. payload: free JSON describing the target.', 'inputSchema' => self::obj(['kind' => ['type' => 'string', 'enum' => ['approve', 'reject', 'remind', 'send_draft', 'note']], 'payload' => ['type' => 'object', 'description' => 'what/who/amount/when'], 'summary' => ['type' => 'string']], ['kind', 'summary'])],
        ];
        if (Config::get('anthropic.allow_sql_tool') && Config::dbEnabled()) $t[] = ['name' => 'sql_readonly', 'description' => 'Run a single read-only SELECT against the live ERP MySQL database when the prepared tools cannot answer (a specific record, an unusual grouping, a report the tools do not cover). Tables follow the Epal ERP schema (journal_entries, journal_items, accounts, payment_schedules, expenses, users, employee_profiles, attendances, leaves, employee_salaries, loans, leads, deals, projects, tasks, sales, purchases, banks, companies…). Always LIMIT; never write. Prefer the prepared tools first.', 'inputSchema' => self::obj(['sql' => ['type' => 'string'], 'purpose' => ['type' => 'string']], ['sql'])];
        return $t;
    }

    public function run(string $name, array $in): array|string
    {
        try {
            return match ($name) {
                'get_brief' => $this->A->brief(),
                'get_kpis' => $this->A->kpis(),
                'get_cash_position' => $this->A->cash(),
                'get_receivables' => $this->A->schedules('receive'),
                'get_payables' => $this->A->schedules('pay'),
                'get_profit_and_loss' => $this->A->profitAndLoss($in['from'] ?? substr($this->D['meta']['today'] ?? date('Y-m-d'), 0, 7) . '-01', $in['to'] ?? ($this->D['meta']['today'] ?? date('Y-m-d'))),
                'get_trial_balance' => $this->A->trialBalance(),
                'get_balance_sheet' => $this->A->balanceSheet(),
                'get_account_ledger' => $this->ledger((string) ($in['code'] ?? ''), (int) ($in['limit'] ?? 30)),
                'get_expenses_vs_budget' => $this->A->expensesVsBudget($in['month'] ?? null),
                'get_attendance_today' => $this->A->attendanceToday(),
                'get_attendance_patterns' => $this->A->latePatterns((int) ($in['days'] ?? 30)),
                'get_payroll' => $this->A->payroll($in['month'] ?? null),
                'find_employee' => (function () use ($in) { $e = $this->A->findEmployee((string) ($in['name'] ?? '')); return $e ? ($this->A->evaluate((int) $e['id']) ?? ['error' => 'no data']) : ['error' => 'no employee matches "' . ($in['name'] ?? '') . '"']; })(),
                'get_pipeline' => $this->A->pipeline(),
                'get_tasks' => $this->A->tasks(),
                'get_projects' => $this->A->projects(),
                'get_approvals' => $this->A->approvals(),
                'get_decisions' => $this->A->decisions(),
                'search_records' => $this->search((string) ($in['query'] ?? ''), (int) ($in['limit'] ?? 15)),
                'record_action' => Memory::logAction((string) ($in['kind'] ?? 'note'), ['summary' => $in['summary'] ?? '', 'detail' => $in['payload'] ?? []], 'queued'),
                'sql_readonly' => Erp::safeSelect((string) ($in['sql'] ?? ''), 200),
                default => ['error' => "unknown tool $name"],
            };
        } catch (Throwable $e) { return ['error' => $e->getMessage()]; }
    }

    private function ledger(string $code, int $limit): array
    {
        $rows = []; $run = 0.0; $type = (int) substr($code, 0, 1); $isDr = in_array($type, [1, 5, 6, 7, 8], true);
        $je = $this->D['journal_entries'] ?? []; usort($je, fn($a, $b) => strcmp($a['date'], $b['date']) ?: $a['id'] <=> $b['id']);
        foreach ($je as $e) foreach ($e['items'] as $it) { if ((string) $it['account_code'] !== $code) continue; $run += $isDr ? $it['debit'] - $it['credit'] : $it['credit'] - $it['debit']; $rows[] = ['date' => $e['date'], 'ref' => $e['reference'], 'description' => $e['description'], 'debit' => round((float) $it['debit']), 'credit' => round((float) $it['credit']), 'balance' => round($run)]; }
        return ['code' => $code, 'postings' => count($rows), 'closing_balance' => round($run), 'last' => array_slice($rows, -$limit)];
    }
    private function search(string $q, int $limit): array
    {
        $q = mb_strtolower(trim($q)); if ($q === '') return []; $hits = [];
        $tables = ['employees' => ['name', 'email', 'designation', 'department'], 'customers' => ['name', 'phone'], 'suppliers' => ['name'], 'leads' => ['name', 'phone', 'assigned_name'], 'projects' => ['project_name', 'customer'], 'tasks' => ['title', 'project'], 'expenses' => ['title', 'category', 'user_name'], 'payment_schedules' => ['party_name', 'source_label']];
        foreach ($tables as $t => $cols) foreach ($this->D[$t] ?? [] as $r) { foreach ($cols as $c) { if (isset($r[$c]) && is_string($r[$c]) && str_contains(mb_strtolower($r[$c]), $q)) { $hits[] = ['table' => $t] + array_intersect_key($r, array_flip(array_merge(['id', 'company_id', 'status', 'amount', 'due_date', 'scheduled_date', 'salary'], $cols))); break; } } if (count($hits) >= $limit) break 2; }
        return $hits;
    }
}
