<?php
declare(strict_types=1);

/* ============================================================
   Erp — reads the live Epal ERP database (read-only) and shapes it
   into the EON dataset. Column names follow the ERP migrations
   (docs/erp-domain-map.md). Every section is defensive: a missing
   table or column skips that section instead of failing the whole
   dataset, because the ERP schema keeps moving.
   ============================================================ */
final class Erp
{
    private static PDO $pdo;
    private static array $warnings = [];

    public static function build(?int $company = null, int $monthsBack = 6): array
    {
        self::$pdo = Db::erp();
        self::$warnings = [];
        $D = Dataset::empty();
        $D['meta']['source'] = 'erp';
        $D['meta']['company_id'] = $company;
        $D['meta']['boss'] = Config::get('boss');
        $from = date('Y-m-01', strtotime("-{$monthsBack} months"));
        $cw = $company ? ' AND company_id = ' . (int) $company : '';   // for tables that carry company_id

        self::section($D, 'companies', "SELECT id, name, short_name, status FROM companies ORDER BY `order`, id");
        self::section($D, 'accounts', "SELECT id, code, name, type, parent_id, opening_balance, company_id FROM accounts ORDER BY code");
        self::section($D, 'departments', "SELECT id, name FROM departments WHERE deleted_at IS NULL");
        self::section($D, 'designations', "SELECT id, name FROM designations WHERE deleted_at IS NULL");
        self::section($D, 'leave_types', "SELECT id, name, max_leaves_count FROM leave_types");
        self::section($D, 'holidays', "SELECT id, name, start_date, end_date FROM holidays WHERE end_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)");

        // ---- journal entries with items ----
        try {
            $rows = Db::rows(self::$pdo, "SELECT je.id, je.company_id, je.date, je.reference, je.source, je.source_id, je.description, ji.account_id, a.code AS account_code, ji.debit, ji.credit, ji.party_type, ji.party_id, ji.note
                FROM journal_entries je JOIN journal_items ji ON ji.journal_entry_id = je.id LEFT JOIN accounts a ON a.id = ji.account_id
                WHERE je.date >= ? {$cwje} ORDER BY je.date, je.id", [$from]);
        } catch (Throwable $e) {
            // some builds prefix company filter differently — retry without the alias filter
            try { $rows = Db::rows(self::$pdo, "SELECT je.id, je.company_id, je.date, je.reference, je.source, je.source_id, je.description, ji.account_id, a.code AS account_code, ji.debit, ji.credit, ji.party_type, ji.party_id, ji.note FROM journal_entries je JOIN journal_items ji ON ji.journal_entry_id = je.id LEFT JOIN accounts a ON a.id = ji.account_id WHERE je.date >= ? ORDER BY je.date, je.id", [$from]); }
            catch (Throwable $e2) { self::$warnings[] = 'journal: ' . $e2->getMessage(); $rows = []; }
        }
        $je = [];
        foreach ($rows as $r) {
            if ($company && (int) $r['company_id'] !== $company) continue;
            $id = (int) $r['id'];
            if (!isset($je[$id])) $je[$id] = ['id' => $id, 'company_id' => (int) $r['company_id'], 'date' => substr((string) $r['date'], 0, 10), 'reference' => $r['reference'], 'source' => $r['source'], 'source_id' => $r['source_id'], 'description' => $r['description'], 'items' => []];
            $je[$id]['items'][] = ['account_id' => (int) $r['account_id'], 'account_code' => (string) $r['account_code'], 'debit' => (float) $r['debit'], 'credit' => (float) $r['credit'], 'party_type' => $r['party_type'], 'party_id' => $r['party_id'] !== null ? (int) $r['party_id'] : null, 'note' => $r['note']];
        }
        $D['journal_entries'] = array_values($je);

        // ---- banks (balance from ledger like the ERP dashboard: opening + Σdebit − Σcredit) ----
        self::section($D, 'banks', "SELECT b.id, b.company_id, b.name, b.type, b.account_id, a.code AS account_code, b.balance FROM banks b LEFT JOIN accounts a ON a.id = b.account_id WHERE 1=1 {$cwb}", [], ['{$cwb}' => str_replace('company_id', 'b.company_id', $cw)]);
        try {
            $bal = Db::rows(self::$pdo, "SELECT ji.account_id, COALESCE(a.opening_balance,0) + SUM(ji.debit) - SUM(ji.credit) AS bal FROM journal_items ji JOIN accounts a ON a.id = ji.account_id GROUP BY ji.account_id, a.opening_balance");
            $map = []; foreach ($bal as $b) $map[(int) $b['account_id']] = (float) $b['bal'];
            foreach ($D['banks'] as &$b) { if (isset($map[(int) ($b['account_id'] ?? 0)])) $b['balance'] = $map[(int) $b['account_id']]; $b['balance'] = (float) ($b['balance'] ?? 0); } unset($b);
        } catch (Throwable $e) { self::$warnings[] = 'bank balances: ' . $e->getMessage(); }

        self::section($D, 'payment_schedules', "SELECT id, company_id, type, party_type, party_id, party_name, source_label, amount, paid_amount, scheduled_date, original_scheduled_date, reschedule_count, status, priority, paid_date FROM payment_schedules WHERE (status IN ('pending','overdue') OR scheduled_date >= ?) {$cw}", [$from]);
        self::section($D, 'expenses', "SELECT e.id, e.company_id, e.title, e.amount, e.expense_date, c.name AS category, e.expense_category_id AS category_id, a.code AS account_code, d.name AS department, e.payment_mode, e.approval_status, e.user_id, u.name AS user_name, e.bank_id
            FROM expenses e LEFT JOIN expense_categories c ON c.id = e.expense_category_id LEFT JOIN accounts a ON a.id = e.account_id LEFT JOIN expense_departments d ON d.id = e.department_id LEFT JOIN users u ON u.id = e.user_id
            WHERE e.expense_date >= ? {$cwe}", [$from], ['{$cwe}' => str_replace('company_id', 'e.company_id', $cw)]);
        self::section($D, 'expense_budgets', "SELECT b.id, b.company_id, c.name AS category, b.expense_category_id AS category_id, b.period, b.amount, b.threshold FROM expense_budgets b LEFT JOIN expense_categories c ON c.id = b.expense_category_id WHERE 1=1 {$cwb}", [], ['{$cwb}' => str_replace('company_id', 'b.company_id', $cw)]);

        // ---- people ----
        self::section($D, 'employees', "SELECT u.id, u.name, u.email, u.phone, u.company_id, p.department_id, d.name AS department, g.name AS designation, p.joining_date, p.salary, p.employment_type, u.status, s.start_time AS shift_start, s.end_time AS shift_end, u.overtime_eligible, u.last_seen_at
            FROM users u LEFT JOIN employee_profiles p ON p.user_id = u.id LEFT JOIN departments d ON d.id = p.department_id LEFT JOIN designations g ON g.id = p.designation_id LEFT JOIN shifts s ON s.id = u.shift_id
            WHERE u.deleted_at IS NULL {$cwu}", [], ['{$cwu}' => str_replace('company_id', 'u.company_id', $cw)]);
        foreach ($D['employees'] as &$e) { $e['status'] = (in_array(strtolower((string) ($e['status'] ?? 'active')), ['active', '1', 'yes'], true)) ? 'active' : 'inactive'; $e['salary'] = (float) ($e['salary'] ?? 0); $e['shift_start'] = substr((string) ($e['shift_start'] ?? '09:00'), 0, 5); $e['shift_end'] = substr((string) ($e['shift_end'] ?? '18:00'), 0, 5); $e['overtime_eligible'] = (bool) ($e['overtime_eligible'] ?? false); } unset($e);
        try { $roles = Db::rows(self::$pdo, "SELECT mhr.model_id AS uid, r.name FROM model_has_roles mhr JOIN roles r ON r.id = mhr.role_id"); $rm = []; foreach ($roles as $r) $rm[(int) $r['uid']] = $r['name']; foreach ($D['employees'] as &$e) $e['role'] = $rm[(int) $e['id']] ?? 'employee'; unset($e); } catch (Throwable $e) {}

        // attendance last 75 days, with late/early/overtime minutes derived from the shift (ERP rules: grace handled by payroll)
        self::section($D, 'attendances', "SELECT a.id, a.user_id, a.company_id, a.date, a.check_in, a.check_out, a.status, a.source, s.start_time, s.end_time
            FROM attendances a LEFT JOIN shifts s ON s.id = a.shift_id WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 75 DAY) {$cwa}", [], ['{$cwa}' => str_replace('company_id', 'a.company_id', $cw)]);
        foreach ($D['attendances'] as &$a) {
            $a['date'] = substr((string) $a['date'], 0, 10);
            $ss = self::mins($a['start_time'] ?? '09:00'); $se = self::mins($a['end_time'] ?? '18:00');
            $ci = $a['check_in'] ? self::mins($a['check_in']) : null; $co = $a['check_out'] ? self::mins($a['check_out']) : null;
            $a['late_minutes'] = ($ci !== null && $ci > $ss + 5) ? $ci - $ss : 0;
            $a['early_minutes'] = ($co !== null && $co < $se - 10) ? $se - $co : 0;
            $a['overtime_minutes'] = ($co !== null && $co >= $se + 60) ? $co - $se : 0;
            $a['check_in'] = $ci !== null ? substr((string) $a['check_in'], 0, 5) : null; $a['check_out'] = $co !== null ? substr((string) $a['check_out'], 0, 5) : null;
            unset($a['start_time'], $a['end_time']);
        } unset($a);

        self::section($D, 'leaves', "SELECT l.id, l.user_id, l.company_id, t.name AS leave_type, l.start_date, l.end_date, DATEDIFF(l.end_date, l.start_date) + 1 AS days, l.status, l.reason, DATE(l.created_at) AS applied_at FROM leaves l LEFT JOIN leave_types t ON t.id = l.leave_type_id WHERE l.end_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) {$cwl}", [], ['{$cwl}' => str_replace('company_id', 'l.company_id', $cw)]);
        self::section($D, 'payroll', "SELECT s.id, s.user_id, u.company_id, s.month, s.year, s.gross_salary, s.absent_deduction, s.leave_deduction, s.late_deduction, s.early_leave_deduction, s.loan_deduction, s.advance_salary_deduction, s.overtime_salary, s.total_deductions, s.net_salary, s.status, s.salary_generation_date AS payment_date
            FROM employee_salaries s LEFT JOIN users u ON u.id = s.user_id WHERE s.created_at >= ? {$cwu}", [$from], ['{$cwu}' => str_replace('company_id', 'u.company_id', $cw)]);
        foreach ($D['payroll'] as &$p) { $mi = array_search(ucfirst(strtolower((string) $p['month'])), ['January','February','March','April','May','June','July','August','September','October','November','December'], true); $p['month_key'] = $mi === false ? null : sprintf('%04d-%02d', (int) $p['year'], $mi + 1); } unset($p);
        self::section($D, 'loans', "SELECT id, user_id, amount, remaining_amount, monthly_deduction, status, start_date, end_date FROM loans");
        self::section($D, 'advance_salaries', "SELECT id, user_id, amount, month, status, payment_status FROM advance_salaries WHERE created_at >= ?", [$from]);
        self::section($D, 'employee_requests', "SELECT id, user_id, category, request_type, amount, status, deadline, DATE(created_at) AS created_at FROM employee_requests WHERE status NOT IN ('closed','rejected') OR created_at >= ?", [$from]);

        // ---- parties, CRM, projects, tasks ----
        self::section($D, 'customers', "SELECT id, company_id, name, phone, 'customer' AS type FROM customers WHERE deleted_at IS NULL {$cw}");
        self::section($D, 'suppliers', "SELECT id, company_id, name, phone FROM suppliers WHERE deleted_at IS NULL {$cw}");
        self::section($D, 'leads', "SELECT l.id, l.company_id, l.name, l.phone, l.lead_type, s.name AS source, l.status, l.assigned_to, u.name AS assigned_name, NULL AS value, DATE(l.created_at) AS created_at,
            (SELECT MAX(followup_date) FROM lead_followups f WHERE f.lead_id = l.id) AS last_followup_at,
            (SELECT MIN(due_date) FROM lead_reminders r WHERE r.lead_id = l.id AND r.status = 'pending') AS next_followup_at
            FROM leads l LEFT JOIN lead_sources s ON s.id = l.lead_source_id LEFT JOIN users u ON u.id = l.assigned_to WHERE l.deleted_at IS NULL {$cwl}", [], ['{$cwl}' => str_replace('company_id', 'l.company_id', $cw)]);
        self::section($D, 'deals', "SELECT d.id, l.company_id, d.lead_id, CONCAT(COALESCE(l.name,'Deal'), ' — ', COALESCE(d.pipeline,'')) AS title, d.stage, d.amount, d.closing_date, d.status, d.deal_agent AS agent_id FROM deals d LEFT JOIN leads l ON l.id = d.lead_id");
        self::section($D, 'projects', "SELECT p.id, p.company_id, p.project_name, p.customer_id, c.name AS customer, p.status, p.start_date, p.end_date, p.budget, NULL AS spent, NULL AS progress, NULL AS manager_id, p.team_members AS team FROM projects p LEFT JOIN customers c ON c.id = p.customer_id WHERE p.deleted_at IS NULL {$cwp}", [], ['{$cwp}' => str_replace('company_id', 'p.company_id', $cw)]);
        foreach ($D['projects'] as &$p) { $t = is_string($p['team']) ? json_decode($p['team'], true) : $p['team']; $p['team'] = is_array($t) ? array_map('intval', $t) : []; } unset($p);
        self::section($D, 'tasks', "SELECT t.id, t.workspace_id AS company_id, t.project_id, p.project_name AS project, t.title, t.priority, LOWER(REPLACE(COALESCE(c.name,'todo'),' ','_')) AS status, t.created_by, t.start_date, t.due_date, t.completed_at, t.label FROM tasks t LEFT JOIN columns c ON c.id = t.column_id LEFT JOIN projects p ON p.id = t.project_id WHERE t.deleted_at IS NULL AND (t.completed_at IS NULL OR t.completed_at >= ?)", [$from]);
        try { $tu = Db::rows(self::$pdo, "SELECT task_id, user_id FROM task_user"); $m = []; foreach ($tu as $r) $m[(int) $r['task_id']][] = (int) $r['user_id']; foreach ($D['tasks'] as &$t) { $t['assigned_to'] = $m[(int) $t['id']] ?? []; if (in_array($t['status'], ['done', 'completed', 'complete'], true) || $t['completed_at']) $t['status'] = 'done'; elseif (str_contains((string) $t['status'], 'progress') || str_contains((string) $t['status'], 'doing')) $t['status'] = 'in_progress'; elseif (str_contains((string) $t['status'], 'review')) $t['status'] = 'review'; else $t['status'] = 'todo'; } unset($t); } catch (Throwable $e) {}
        // project progress from task completion
        foreach ($D['projects'] as &$p) { $tk = array_filter($D['tasks'], fn($t) => (int) $t['project_id'] === (int) $p['id']); $n = count($tk); $p['progress'] = $n ? (int) round(count(array_filter($tk, fn($t) => $t['status'] === 'done')) / $n * 100) : ($p['status'] === 'completed' ? 100 : 0); $p['spent'] = (float) ($p['spent'] ?? 0); $p['budget'] = (float) ($p['budget'] ?? 0); } unset($p);
        self::section($D, 'office_todos', "SELECT t.id, t.company_id, t.title, d.name AS department, t.priority, t.status, t.due_date FROM office_todos t LEFT JOIN departments d ON d.id = t.department_id WHERE t.status <> 'completed' OR t.updated_at >= ?", [$from]);
        try { $ta = Db::rows(self::$pdo, "SELECT oa.office_todo_id AS tid, oa.user_id, u.name FROM office_todo_assignees oa LEFT JOIN users u ON u.id = oa.user_id"); $m = []; foreach ($ta as $r) { $m[(int) $r['tid']]['ids'][] = (int) $r['user_id']; $m[(int) $r['tid']]['names'][] = $r['name']; } foreach ($D['office_todos'] as &$t) { $t['assignees'] = $m[(int) $t['id']]['ids'] ?? []; $t['assignee_names'] = $m[(int) $t['id']]['names'] ?? []; } unset($t); } catch (Throwable $e) {}
        self::section($D, 'sales', "SELECT s.id, s.company_id, s.invoice_no, s.customer_id, c.name AS customer, DATE(s.created_at) AS date, s.total, s.paid_amount, s.due_amount, s.payment_status, s.due_date FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE s.created_at >= ? {$cws}", [$from], ['{$cws}' => str_replace('company_id', 's.company_id', $cw)]);
        self::section($D, 'purchases', "SELECT p.id, p.company_id, p.supplier_id, sp.name AS supplier, DATE(p.created_at) AS date, p.total, p.paid_amount, p.due_amount, p.payment_status, p.due_date FROM purchases p LEFT JOIN suppliers sp ON sp.id = p.supplier_id WHERE p.created_at >= ? {$cwp}", [$from], ['{$cwp}' => str_replace('company_id', 'p.company_id', $cw)]);
        self::section($D, 'notices', "SELECT id, company_id, title, DATE(created_at) AS published_at, expiry_date AS expires_at FROM notices ORDER BY created_at DESC LIMIT 20");

        // numeric coercion for money fields (PDO returns strings)
        foreach (['payment_schedules' => ['amount', 'paid_amount'], 'expenses' => ['amount'], 'expense_budgets' => ['amount'], 'payroll' => ['gross_salary', 'absent_deduction', 'leave_deduction', 'late_deduction', 'early_leave_deduction', 'loan_deduction', 'advance_salary_deduction', 'overtime_salary', 'total_deductions', 'net_salary'], 'loans' => ['amount', 'remaining_amount', 'monthly_deduction'], 'advance_salaries' => ['amount'], 'employee_requests' => ['amount'], 'deals' => ['amount'], 'sales' => ['total', 'paid_amount', 'due_amount'], 'purchases' => ['total', 'paid_amount', 'due_amount'], 'accounts' => ['opening_balance']] as $t => $cols) {
            foreach ($D[$t] as &$r) foreach ($cols as $c) $r[$c] = (float) ($r[$c] ?? 0); unset($r);
        }
        foreach (Dataset::TABLES as $t) foreach ($D[$t] as &$r) { foreach (['id', 'company_id', 'user_id', 'customer_id', 'supplier_id', 'lead_id', 'project_id', 'assigned_to', 'agent_id', 'account_id', 'bank_id', 'party_id', 'category_id', 'department_id'] as $k) if (array_key_exists($k, $r) && $r[$k] !== null && !is_array($r[$k])) $r[$k] = (int) $r[$k]; } unset($r);

        $D['meta']['warnings'] = self::$warnings;
        $D['meta']['generated_at'] = date('c');
        return $D;
    }

    /** run one section; on failure record a warning and leave the table empty */
    private static function section(array &$D, string $table, string $sql, array $params = [], array $subs = []): void
    {
        foreach ($subs as $k => $v) $sql = str_replace($k, $v, $sql);
        $sql = str_replace(['{$cwje}', '{$cwb}', '{$cwe}', '{$cwu}', '{$cwa}', '{$cwl}', '{$cwp}', '{$cws}'], '', $sql);
        try { $D[$table] = Db::rows(self::$pdo, $sql, $params); }
        catch (Throwable $e) { self::$warnings[] = "$table: " . $e->getMessage(); $D[$table] = []; }
    }

    private static function mins(string $t): int { $p = explode(':', $t); return (int) ($p[0] ?? 0) * 60 + (int) ($p[1] ?? 0); }

    /** guarded read-only SQL for the language model's sql tool */
    public static function safeSelect(string $sql, int $limit = 200): array
    {
        $s = trim($sql);
        if (!preg_match('/^\s*(select|with|show|describe|explain)\b/i', $s)) throw new InvalidArgumentException('only SELECT / SHOW / DESCRIBE statements are allowed');
        if (preg_match('/;\s*\S/', $s)) throw new InvalidArgumentException('one statement at a time');
        if (preg_match('/\b(insert|update|delete|drop|alter|create|truncate|replace|grant|revoke|lock|call|load|outfile|into\s+dumpfile)\b/i', $s)) throw new InvalidArgumentException('write statements are not allowed');
        $s = rtrim($s, "; \t\n");
        if (preg_match('/^\s*select\b/i', $s) && !preg_match('/\blimit\s+\d+/i', $s)) $s .= ' LIMIT ' . $limit;
        $pdo = Db::erp();
        try { $pdo->exec('SET SESSION MAX_EXECUTION_TIME=8000'); } catch (Throwable $e) {}
        $st = $pdo->query($s);
        $rows = $st->fetchAll();
        return array_slice($rows, 0, $limit);
    }
}
