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
    private Analytics $A; private array $D; private ?int $company;

    public function __construct(array $D, ?int $company = null) { $this->D = $D; $this->company = $company; $this->A = new Analytics($D, $company); }

    private static function obj(array $props, array $required = []): array { $o = ['type' => 'object']; if ($props) { $o['properties'] = $props; if ($required) $o['required'] = $required; } return $o; }

    /** plug-in tools: every server/lib/tools/*.php returns ['definitions' => [...], 'run' => fn(string $name, array $in, Tools $tools, array $D, ?int $company)] */
    private static ?array $plugins = null;
    public static function plugins(): array
    {
        if (self::$plugins !== null) return self::$plugins;
        self::$plugins = [];
        foreach (glob(EON_ROOT . '/lib/tools/*.php') ?: [] as $f) { try { $p = require $f; if (is_array($p) && isset($p['definitions'], $p['run'])) self::$plugins[basename($f, '.php')] = $p; } catch (Throwable $e) { Log::warn('tool plug-in failed: ' . $f, ['error' => $e->getMessage()]); } }
        return self::$plugins;
    }
    public function dataset(): array { return $this->D; }
    public function company(): ?int { return $this->company; }
    public function analytics(): Analytics { return $this->A; }

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
            ['name' => 'get_sales', 'description' => 'What Epal actually sold in a period, per service line — air tickets, visas, contract files, contract flights: invoiced, collected, still outstanding, invoice count, the biggest invoices and their clients. This is the real top line: the `sales` table is unused and the general ledger only carries what has been journalised, so ALWAYS call this (not just get_profit_and_loss) for revenue, sales, turnover or "how much business did we do" questions. Dates ISO; omit for month-to-date.', 'inputSchema' => self::obj(['from' => ['type' => 'string'], 'to' => ['type' => 'string']])],
            ['name' => 'get_service_operations', 'description' => 'The travel desk\'s work in hand: visa processing pipeline by stage with cost vs sale price and the officer assigned, ticket purchases and what is still owed to vendors and booking portals (BSP/IATA), portal balances, and other visa services. Call for visa processing, ticket purchase, vendor/portal dues, work in hand, or travel operations questions.', 'inputSchema' => self::obj([])],
            ['name' => 'explain_erp', 'description' => 'Ask the ERP about itself, from its own source: which screen does something and its address, what actions a module supports, which database table holds a kind of record and its columns, and the model behind it. Call for "where do I ...", "how do I ...", "which screen/table/report has ...", "what can I do on ...", or any question about how the ERP is built rather than what the numbers say.', 'inputSchema' => self::obj(['query' => ['type' => 'string', 'description' => 'a screen, module, record or concept — e.g. "payslip", "journal entry", "leads", "ticket sale"']], ['query'])],
            ['name' => 'get_party_ledger', 'description' => 'The running account of one customer or vendor, from the ERP transactions ledger: total invoiced (debit), total paid (credit), the balance the ERP itself carries, the number of movements and the recent ones with dates and references. Omit the name to get every party ranked by balance. Call for "what is the balance for X", "how much has X paid", "statement for X", or any party-account question. This is the ERP own view and can differ from the invoice dues in get_receivables; when the two disagree, say so.', 'inputSchema' => self::obj(['name' => ['type' => 'string', 'description' => 'party name, partial is fine'], 'limit' => ['type' => 'integer']])],
            ['name' => 'search_records', 'description' => 'Full-text search across employees, customers, suppliers, leads, projects, tasks, expenses and payment schedules by a word or name. Call when a name or reference is mentioned that no other tool covers.', 'inputSchema' => self::obj(['query' => ['type' => 'string'], 'limit' => ['type' => 'integer']], ['query'])],
            ['name' => 'record_action', 'description' => 'Record an instruction the boss gives through EON — an approval decision, a reminder to set, a draft to send, a note. EON is advisory: this logs the intent for the ERP/staff to execute; say so in your reply. kind: approve | reject | remind | send_draft | note. payload: free JSON describing the target.', 'inputSchema' => self::obj(['kind' => ['type' => 'string', 'enum' => ['approve', 'reject', 'remind', 'send_draft', 'note']], 'payload' => ['type' => 'object', 'description' => 'what/who/amount/when'], 'summary' => ['type' => 'string']], ['kind', 'summary'])],
        ];
        if (Py::available()) {
            $t[] = ['name' => 'forecast', 'description' => 'Python forecast of income, outflow, net and month-end cash for the next N months (default 3) from the ledger history, with an 80% band, growth per month and runway. Call for forecast, projection, next month/quarter, runway, will we have cash, trend questions.', 'inputSchema' => self::obj(['months' => ['type' => 'integer']])];
            $t[] = ['name' => 'spending_anomalies', 'description' => 'Python anomaly detection on expenses: categories running far above their usual level (like-for-like by day of month) and possible duplicate charges. Call for anomalies, unusual spending, leaks, duplicates, "anything suspicious".', 'inputSchema' => self::obj([])];
            $t[] = ['name' => 'evaluate_all_staff', 'description' => 'Python evaluation model over every active employee: score/grade, department averages, grade distribution, attrition-risk list, top and bottom performers. Call for rankings, best/worst performers, who is at risk of leaving, team quality by department.', 'inputSchema' => self::obj([])];
            $t[] = ['name' => 'export_report', 'description' => 'Generate a downloadable report file (xlsx if openpyxl is installed, else csv): kind = receivables | payables | payroll | pnl | attendance. Returns the file path/URL. Call when the boss asks to export, download, send as Excel, or "give me the sheet".', 'inputSchema' => self::obj(['kind' => ['type' => 'string', 'enum' => ['receivables', 'payables', 'payroll', 'pnl', 'attendance']]], ['kind'])];
        }
        foreach (self::plugins() as $p) foreach ($p['definitions'] as $d) $t[] = $d;
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
                'get_sales' => $this->A->salesBooked($in['from'] ?? null, $in['to'] ?? null) + ['top_invoices' => $this->topInvoices($in['from'] ?? null, $in['to'] ?? null)],
                'get_service_operations' => $this->serviceOps(),
                'explain_erp' => ErpMap::explain((string) ($in['query'] ?? '')),
                'get_party_ledger' => $this->A->partyLedger($in['name'] ?? null, (int) ($in['limit'] ?? 40)),
                'search_records' => $this->search((string) ($in['query'] ?? ''), (int) ($in['limit'] ?? 15)),
                'record_action' => Memory::logAction((string) ($in['kind'] ?? 'note'), ['summary' => $in['summary'] ?? '', 'detail' => $in['payload'] ?? [], 'via' => 'model'], 'proposed'),
                'forecast' => Py::run('forecast', $this->D, ['--company' => $this->company, '--months' => (int) ($in['months'] ?? 3)]),
                'spending_anomalies' => Py::run('anomalies', $this->D, ['--company' => $this->company]),
                'evaluate_all_staff' => Py::run('evaluate', $this->D, ['--company' => $this->company, '--all' => true]),
                'export_report' => (function () use ($in) { $kind = (string) ($in['kind'] ?? 'receivables'); $file = EON_ROOT . '/storage/data/report-' . preg_replace('/[^a-z]/', '', $kind) . '-' . date('Ymd-His') . '.xlsx'; $r = Py::run('report', $this->D, ['--company' => $this->company, '--kind' => $kind, '--out' => $file]); if (($r['ok'] ?? false) && !empty($r['file'])) $r['download'] = 'api/file.php?name=' . rawurlencode(basename($r['file'])); return $r; })(),
                'sql_readonly' => Erp::safeSelect((string) ($in['sql'] ?? ''), 200),
                default => $this->runPlugin($name, $in),
            };
        } catch (Throwable $e) { return ['error' => $e->getMessage()]; }
    }

    /** the biggest invoices in a period, whatever service line they came from */
    private function topInvoices(?string $from, ?string $to): array
    {
        $today = (string) ($this->D['meta']['today'] ?? date('Y-m-d'));
        $from = $from ?: substr($today, 0, 7) . '-01'; $to = $to ?: $today;
        $rows = [];
        foreach (Dataset::SALES_TABLES as $table => $label) {
            foreach ($this->D[$table] ?? [] as $r) {
                if ($this->company !== null && isset($r['company_id']) && (int) $r['company_id'] !== $this->company) continue;
                $d = substr((string) ($r['date'] ?? ''), 0, 10);
                if ($d === '' || $d < $from || $d > $to) continue;
                $rows[] = ['line' => $label, 'invoice' => $r['invoice'] ?? null, 'date' => $d, 'client' => $r['client'] ?? null,
                    'total' => (float) ($r['total'] ?? 0), 'paid' => (float) ($r['paid_amount'] ?? 0), 'due' => (float) ($r['due_amount'] ?? 0), 'status' => $r['payment_status'] ?? ($r['status'] ?? null)];
            }
        }
        usort($rows, fn($a, $b) => $b['total'] <=> $a['total']);
        return array_slice($rows, 0, 15);
    }

    /** the travel desk's work in hand and what it owes for it */
    private function serviceOps(): array
    {
        $co = fn(array $r) => $this->company === null || !isset($r['company_id']) || (int) $r['company_id'] === $this->company;
        $vp = array_values(array_filter($this->D['visa_processes'] ?? [], $co));
        $byStage = [];
        foreach ($vp as $p) {
            $k = (string) ($p['status'] ?? $p['stage'] ?? 'unknown');
            if (!isset($byStage[$k])) $byStage[$k] = ['stage' => $k, 'count' => 0, 'cost' => 0.0, 'sale' => 0.0];
            $byStage[$k]['count']++; $byStage[$k]['cost'] += (float) ($p['costing_price'] ?? 0); $byStage[$k]['sale'] += (float) ($p['sale_price'] ?? 0);
        }
        usort($byStage, fn($a, $b) => $b['count'] <=> $a['count']);
        $tp = array_values(array_filter($this->D['ticket_purchases'] ?? [], $co));
        $owedByParty = [];
        foreach ($tp as $p) {
            $due = (float) ($p['due_amount'] ?? 0);
            if (round($due) <= 0) continue;
            $name = (string) ($p['vendor'] ?? '') ?: (string) ($p['portal'] ?? '') ?: (string) ($p['airline'] ?? '') ?: 'Ticket vendor';
            $owedByParty[$name] = ($owedByParty[$name] ?? 0) + $due;
        }
        arsort($owedByParty);
        return [
            'visa_processes' => ['count' => count($vp), 'by_stage' => array_values($byStage),
                'cost_total' => round(array_sum(array_map(fn($p) => (float) ($p['costing_price'] ?? 0), $vp))),
                'sale_total' => round(array_sum(array_map(fn($p) => (float) ($p['sale_price'] ?? 0), $vp))),
                'unpaid_to_vendor' => round(array_sum(array_map(fn($p) => max(0, (float) ($p['costing_price'] ?? 0) - (float) ($p['cost_paid_amount'] ?? 0)), $vp)))],
            'ticket_purchases' => ['count' => count($tp), 'total' => round(array_sum(array_map(fn($p) => (float) ($p['total'] ?? 0), $tp))), 'due' => round(array_sum(array_map(fn($p) => (float) ($p['due_amount'] ?? 0), $tp))),
                'owed_to' => array_map(fn($k, $v) => ['party' => $k, 'due' => round($v)], array_keys($owedByParty), array_values($owedByParty))],
            'portals' => array_map(fn($p) => ['name' => $p['name'] ?? '', 'balance' => (float) ($p['balance'] ?? 0), 'next_payment_date' => $p['next_payment_date'] ?? null], $this->D['portals'] ?? []),
            'other_visa_services' => ['count' => count($this->D['other_visa_services'] ?? []), 'sale_total' => round(array_sum(array_map(fn($s) => (float) ($s['sale_price'] ?? 0), $this->D['other_visa_services'] ?? [])))],
            'passport_holders' => count($this->D['passport_holders'] ?? []),
        ];
    }

    private function runPlugin(string $name, array $in): array|string
    {
        foreach (self::plugins() as $p) foreach ($p['definitions'] as $d) if (($d['name'] ?? '') === $name) return ($p['run'])($name, $in, $this, $this->D, $this->company);
        return ['error' => "unknown tool $name"];
    }
    private function ledger(string $code, int $limit): array
    {
        $rows = []; $run = 0.0; $type = (int) substr($code, 0, 1); $isDr = in_array($type, [1, 5, 6, 7, 8], true);
        $je = array_values(array_filter($this->D['journal_entries'] ?? [], fn($e) => $this->company === null || (int) $e['company_id'] === $this->company)); usort($je, fn($a, $b) => strcmp($a['date'], $b['date']) ?: $a['id'] <=> $b['id']);
        foreach ($je as $e) foreach ($e['items'] as $it) { if ((string) $it['account_code'] !== $code) continue; $run += $isDr ? $it['debit'] - $it['credit'] : $it['credit'] - $it['debit']; $rows[] = ['date' => $e['date'], 'ref' => $e['reference'], 'description' => $e['description'], 'debit' => round((float) $it['debit']), 'credit' => round((float) $it['credit']), 'balance' => round($run)]; }
        return ['code' => $code, 'postings' => count($rows), 'closing_balance' => round($run), 'last' => array_slice($rows, -$limit)];
    }
    private function search(string $q, int $limit): array
    {
        $q = mb_strtolower(trim($q)); if ($q === '') return []; $hits = [];
        $tables = ['employees' => ['name', 'email', 'designation', 'department'], 'customers' => ['name', 'phone'], 'suppliers' => ['name'], 'leads' => ['name', 'phone', 'assigned_name'], 'projects' => ['project_name', 'customer'], 'tasks' => ['title', 'project'], 'expenses' => ['title', 'category', 'user_name'], 'payment_schedules' => ['party_name', 'source_label'],
            // the travel business: a name the boss types is usually a client on an invoice
            'ticket_sales' => ['invoice', 'client', 'client_phone'], 'visa_sales' => ['invoice', 'client'], 'contract_file_sales' => ['invoice', 'client'],
            'ticket_purchases' => ['ticket_no', 'vendor', 'portal', 'airline'], 'visa_processes' => ['application_id', 'applicant', 'country', 'officer'],
            'passport_holders' => ['name', 'passport_no', 'phone'], 'party_transactions' => ['party_name', 'reference_no'], 'support_tickets' => ['title', 'assignee']];
        foreach ($tables as $t => $cols) foreach ($this->D[$t] ?? [] as $r) { if ($this->company !== null && isset($r['company_id']) && (int) $r['company_id'] !== $this->company) continue; foreach ($cols as $c) { if (isset($r[$c]) && is_string($r[$c]) && str_contains(mb_strtolower($r[$c]), $q)) { $hits[] = ['table' => $t] + array_intersect_key($r, array_flip(array_merge(['id', 'company_id', 'status', 'amount', 'due_date', 'scheduled_date', 'salary'], $cols))); break; } } if (count($hits) >= $limit) break 2; }
        return $hits;
    }
}
