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
        $al = fn(string $alias): string => $company ? " AND {$alias}.company_id = " . (int) $company : '';
        $cwje = $al('je'); $cwb = $al('b'); $cwe = $al('e'); $cwu = $al('u'); $cwa = $al('a'); $cwl = $al('l'); $cwp = $al('p'); $cws = $al('s');
        // schema drift guard: only reference columns that exist on this ERP build
        $has = fn(string $table, string $col): bool => self::hasColumn($table, $col);
        $sd = fn(string $table, string $alias = ''): string => $has($table, 'deleted_at') ? ' AND ' . ($alias ? $alias . '.' : '') . 'deleted_at IS NULL' : '';

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
                WHERE je.date >= ? {$cwje}{$sd('journal_entries', 'je')}{$sd('journal_items', 'ji')} ORDER BY je.date, je.id", [$from]);
        } catch (Throwable $e) { self::$warnings[] = 'journal: ' . $e->getMessage(); $rows = []; }
        $je = [];
        foreach ($rows as $r) {
            if ($company && (int) $r['company_id'] !== $company) continue;
            $id = (int) $r['id'];
            if (!isset($je[$id])) $je[$id] = ['id' => $id, 'company_id' => (int) $r['company_id'], 'date' => substr((string) $r['date'], 0, 10), 'reference' => $r['reference'], 'source' => $r['source'], 'source_id' => $r['source_id'], 'description' => $r['description'], 'items' => []];
            $je[$id]['items'][] = ['account_id' => (int) $r['account_id'], 'account_code' => (string) $r['account_code'], 'debit' => (float) $r['debit'], 'credit' => (float) $r['credit'], 'party_type' => $r['party_type'], 'party_id' => $r['party_id'] !== null ? (int) $r['party_id'] : null, 'note' => $r['note']];
        }
        $D['journal_entries'] = array_values($je);

        // ---- banks (balance from ledger like the ERP dashboard: opening + Σdebit − Σcredit) ----
        self::section($D, 'banks', "SELECT b.id, b.company_id, b.name, b.type, b.account_id, a.code AS account_code, b.balance FROM banks b LEFT JOIN accounts a ON a.id = b.account_id WHERE 1=1 {$cwb}{$sd('banks', 'b')}");
        try {
            $bal = Db::rows(self::$pdo, "SELECT ji.account_id, COALESCE(a.opening_balance,0) + SUM(ji.debit) - SUM(ji.credit) AS bal FROM journal_items ji JOIN accounts a ON a.id = ji.account_id GROUP BY ji.account_id, a.opening_balance");
            $map = []; foreach ($bal as $b) $map[(int) $b['account_id']] = (float) $b['bal'];
            foreach ($D['banks'] as &$b) { if (isset($map[(int) ($b['account_id'] ?? 0)])) $b['balance'] = $map[(int) $b['account_id']]; $b['balance'] = (float) ($b['balance'] ?? 0); } unset($b);
        } catch (Throwable $e) { self::$warnings[] = 'bank balances: ' . $e->getMessage(); }

        self::section($D, 'payment_schedules', "SELECT id, company_id, type, party_type, party_id, party_name, source_label, amount, paid_amount, scheduled_date, original_scheduled_date, reschedule_count, status, priority, paid_date FROM payment_schedules WHERE (status IN ('pending','overdue') OR scheduled_date >= ?) {$cw}{$sd('payment_schedules')}", [$from]);
        $deptCol = $has('expenses', 'expense_department_id') ? 'expense_department_id' : 'department_id';
        $apprCol = $has('expenses', 'approval_status') ? 'e.approval_status' : "'approved' AS approval_status";
        self::section($D, 'expenses', "SELECT e.id, e.company_id, e.title, e.amount, e.expense_date, c.name AS category, e.expense_category_id AS category_id, a.code AS account_code, d.name AS department, e.payment_mode, {$apprCol}, e.user_id, u.name AS user_name, e.bank_id
            FROM expenses e LEFT JOIN expense_categories c ON c.id = e.expense_category_id LEFT JOIN accounts a ON a.id = e.account_id LEFT JOIN expense_departments d ON d.id = e.{$deptCol} LEFT JOIN users u ON u.id = e.user_id
            WHERE e.expense_date >= ? {$cwe}{$sd('expenses', 'e')}", [$from]);
        self::section($D, 'expense_budgets', "SELECT b.id, b.company_id, c.name AS category, b.expense_category_id AS category_id, b.period, b.amount, b.threshold FROM expense_budgets b LEFT JOIN expense_categories c ON c.id = b.expense_category_id WHERE 1=1 {$cwb}{$sd('expense_budgets', 'b')}");

        // ---- people ----
        $otCol = $has('users', 'overtime_eligible') ? 'u.overtime_eligible' : '0 AS overtime_eligible'; $lsCol = $has('users', 'last_seen_at') ? 'u.last_seen_at' : 'NULL AS last_seen_at';
        self::section($D, 'employees', "SELECT u.id, u.name, u.email, u.phone, u.company_id, p.department_id, d.name AS department, g.name AS designation, p.joining_date, p.salary, p.employment_type, u.status, s.start_time AS shift_start, s.end_time AS shift_end, {$otCol}, {$lsCol}
            FROM users u LEFT JOIN employee_profiles p ON p.user_id = u.id LEFT JOIN departments d ON d.id = p.department_id LEFT JOIN designations g ON g.id = p.designation_id LEFT JOIN shifts s ON s.id = u.shift_id
            WHERE 1=1 {$sd('users', 'u')} {$cwu}");
        foreach ($D['employees'] as &$e) { $e['status'] = (in_array(strtolower((string) ($e['status'] ?? 'active')), ['active', '1', 'yes'], true)) ? 'active' : 'inactive'; $e['salary'] = (float) ($e['salary'] ?? 0); $e['shift_start'] = substr((string) ($e['shift_start'] ?? '09:00'), 0, 5); $e['shift_end'] = substr((string) ($e['shift_end'] ?? '18:00'), 0, 5); $e['overtime_eligible'] = (bool) ($e['overtime_eligible'] ?? false); } unset($e);
        try { $roles = Db::rows(self::$pdo, "SELECT mhr.model_id AS uid, r.name FROM model_has_roles mhr JOIN roles r ON r.id = mhr.role_id"); $rm = []; foreach ($roles as $r) $rm[(int) $r['uid']] = $r['name']; foreach ($D['employees'] as &$e) $e['role'] = $rm[(int) $e['id']] ?? 'employee'; unset($e); } catch (Throwable $e) {}

        // attendance last 75 days, with late/early/overtime minutes derived from the shift (ERP rules: grace handled by payroll)
        $srcCol = $has('attendances', 'source') ? 'a.source' : 'NULL AS source';
        self::section($D, 'attendances', "SELECT a.id, a.user_id, a.company_id, a.date, a.check_in, a.check_out, a.status, {$srcCol}, s.start_time, s.end_time
            FROM attendances a LEFT JOIN shifts s ON s.id = a.shift_id WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 75 DAY) {$cwa}{$sd('attendances', 'a')}");
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

        self::section($D, 'leaves', "SELECT l.id, l.user_id, l.company_id, t.name AS leave_type, l.start_date, l.end_date, DATEDIFF(l.end_date, l.start_date) + 1 AS days, l.status, l.reason, DATE(l.created_at) AS applied_at FROM leaves l LEFT JOIN leave_types t ON t.id = l.leave_type_id WHERE l.end_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) {$cwl}{$sd('leaves', 'l')}");
        self::section($D, 'payroll', "SELECT s.id, s.user_id, u.company_id, s.month, s.year, s.gross_salary, s.absent_deduction, s.leave_deduction, s.late_deduction, s.early_leave_deduction, s.loan_deduction, s.advance_salary_deduction, s.overtime_salary, s.total_deductions, s.net_salary, s.status, s.salary_generation_date AS payment_date
            FROM employee_salaries s LEFT JOIN users u ON u.id = s.user_id WHERE s.created_at >= ? {$cwu}{$sd('employee_salaries', 's')}", [$from]);
        $names = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        foreach ($D['payroll'] as &$p) {
            $m = trim((string) ($p['month'] ?? '')); $mi = false;
            if (ctype_digit($m)) $mi = (int) $m - 1;                                                     // '8' / '08' (PayrollService stores the number)
            elseif (preg_match('/^(\d{4})-(\d{1,2})$/', $m, $mm)) { $mi = (int) $mm[2] - 1; $p['year'] = (int) $mm[1]; }
            else { $mi = array_search(ucfirst(strtolower($m)), $names, true); if ($mi === false) $mi = array_search(ucfirst(strtolower(substr($m, 0, 3))), array_map(fn($n) => substr($n, 0, 3), $names), true); }
            $p['month_key'] = ($mi === false || $mi < 0 || $mi > 11) ? null : sprintf('%04d-%02d', (int) $p['year'], $mi + 1);
            if ($mi !== false && $mi >= 0 && $mi <= 11) $p['month'] = $names[$mi];
        } unset($p);
        self::section($D, 'loans', "SELECT id, user_id, amount, remaining_amount, monthly_deduction, status, start_date, end_date FROM loans WHERE 1=1 {$sd('loans')}");
        self::section($D, 'advance_salaries', "SELECT id, user_id, amount, month, status, payment_status FROM advance_salaries WHERE created_at >= ?", [$from]);
        $reqUser = $has('employee_requests', 'employee_id') ? 'employee_id AS user_id' : 'user_id';
        self::section($D, 'employee_requests', "SELECT id, {$reqUser}, category, request_type, amount, status, deadline, DATE(created_at) AS created_at FROM employee_requests WHERE (status NOT IN ('closed','rejected') OR created_at >= ?) {$sd('employee_requests')}", [$from]);

        // ---- parties, CRM, projects, tasks ----
        $custCo = $has('customers', 'company_id') ? 'company_id' : 'NULL AS company_id'; $custAct = $has('customers', 'is_active') ? ' AND is_active = 1' : '';
        self::section($D, 'customers', "SELECT id, {$custCo}, name, phone, 'customer' AS type FROM customers WHERE 1=1 {$custAct}{$sd('customers')} " . ($has('customers', 'company_id') ? $cw : ''));
        $supCo = $has('suppliers', 'company_id') ? 'company_id' : 'NULL AS company_id'; $supAct = $has('suppliers', 'is_active') ? ' AND is_active = 1' : '';
        self::section($D, 'suppliers', "SELECT id, {$supCo}, name, phone FROM suppliers WHERE 1=1 {$supAct}{$sd('suppliers')} " . ($has('suppliers', 'company_id') ? $cw : ''));
        $leadCo = $has('leads', 'company_id') ? 'l.company_id' : 'u.company_id';   // leads carry no company on the current ERP build → the assigned user's company
        self::section($D, 'leads', "SELECT l.id, {$leadCo} AS company_id, l.name, l.phone, l.lead_type, s.name AS source, l.status, l.assigned_to, u.name AS assigned_name, NULL AS value, DATE(l.created_at) AS created_at,
            (SELECT MAX(followup_date) FROM lead_followups f WHERE f.lead_id = l.id) AS last_followup_at,
            (SELECT MIN(due_date) FROM lead_reminders r WHERE r.lead_id = l.id AND r.status = 'pending') AS next_followup_at
            FROM leads l LEFT JOIN lead_sources s ON s.id = l.lead_source_id LEFT JOIN users u ON u.id = l.assigned_to WHERE 1=1 {$sd('leads', 'l')} " . ($company ? " AND {$leadCo} = " . (int) $company : ''));
        $dealTitle = $has('deals', 'deal_name') ? "COALESCE(d.deal_name, l.name, 'Deal')" : "CONCAT(COALESCE(l.name,'Deal'), ' — ', COALESCE(d.pipeline,''))";
        self::section($D, 'deals', "SELECT d.id, {$leadCo} AS company_id, d.lead_id, {$dealTitle} AS title, d.stage, d.amount, d.closing_date, d.status, d.deal_agent AS agent_id FROM deals d LEFT JOIN leads l ON l.id = d.lead_id LEFT JOIN users u ON u.id = l.assigned_to WHERE 1=1 {$sd('deals', 'd')} " . ($company ? " AND {$leadCo} = " . (int) $company : ''));
        self::section($D, 'projects', "SELECT p.id, p.company_id, p.project_name, p.customer_id, c.name AS customer, p.status, p.start_date, p.end_date, p.budget, NULL AS spent, NULL AS progress, NULL AS manager_id, p.team_members AS team FROM projects p LEFT JOIN customers c ON c.id = p.customer_id WHERE 1=1 {$sd('projects', 'p')} {$cwp}");
        foreach ($D['projects'] as &$p) { $t = is_string($p['team']) ? json_decode($p['team'], true) : $p['team']; $p['team'] = is_array($t) ? array_map('intval', $t) : []; } unset($p);
        self::section($D, 'tasks', "SELECT t.id, COALESCE(t.company_id, t.workspace_id) AS company_id, t.project_id, p.project_name AS project, t.title, t.priority, LOWER(REPLACE(COALESCE(c.name,'todo'),' ','_')) AS status, t.created_by, t.start_date, t.due_date, t.completed_at FROM tasks t LEFT JOIN columns c ON c.id = t.column_id LEFT JOIN projects p ON p.id = t.project_id WHERE (t.completed_at IS NULL OR t.completed_at >= ?) {$sd('tasks', 't')}", [$from]);
        try { $tu = Db::rows(self::$pdo, "SELECT task_id, user_id FROM task_user"); $m = []; foreach ($tu as $r) $m[(int) $r['task_id']][] = (int) $r['user_id']; foreach ($D['tasks'] as &$t) { $t['assigned_to'] = $m[(int) $t['id']] ?? []; if (in_array($t['status'], ['done', 'completed', 'complete'], true) || $t['completed_at']) $t['status'] = 'done'; elseif (str_contains((string) $t['status'], 'progress') || str_contains((string) $t['status'], 'doing')) $t['status'] = 'in_progress'; elseif (str_contains((string) $t['status'], 'review')) $t['status'] = 'review'; else $t['status'] = 'todo'; } unset($t); } catch (Throwable $e) {}
        // project progress from task completion
        foreach ($D['projects'] as &$p) { $tk = array_filter($D['tasks'], fn($t) => (int) $t['project_id'] === (int) $p['id']); $n = count($tk); $p['progress'] = $n ? (int) round(count(array_filter($tk, fn($t) => $t['status'] === 'done')) / $n * 100) : ($p['status'] === 'completed' ? 100 : 0); $p['spent'] = (float) ($p['spent'] ?? 0); $p['budget'] = (float) ($p['budget'] ?? 0); } unset($p);
        self::section($D, 'office_todos', "SELECT t.id, t.company_id, t.title, d.name AS department, t.priority, t.status, t.due_date FROM office_todos t LEFT JOIN departments d ON d.id = t.department_id WHERE t.status <> 'completed' OR t.updated_at >= ?", [$from]);
        try { $ta = Db::rows(self::$pdo, "SELECT oa.office_todo_id AS tid, oa.user_id, u.name FROM office_todo_assignees oa LEFT JOIN users u ON u.id = oa.user_id"); $m = []; foreach ($ta as $r) { $m[(int) $r['tid']]['ids'][] = (int) $r['user_id']; $m[(int) $r['tid']]['names'][] = $r['name']; } foreach ($D['office_todos'] as &$t) { $t['assignees'] = $m[(int) $t['id']]['ids'] ?? []; $t['assignee_names'] = $m[(int) $t['id']]['names'] ?? []; } unset($t); } catch (Throwable $e) {}
        $saleTot = $has('sales', 'total_amount') ? 's.total_amount' : 's.total'; $saleDate = $has('sales', 'sale_date') ? 's.sale_date' : 'DATE(s.created_at)';
        self::section($D, 'sales', "SELECT s.id, s.company_id, s.invoice_no, s.customer_id, c.name AS customer, {$saleDate} AS date, {$saleTot} AS total, s.paid_amount, s.due_amount, s.payment_status, s.due_date FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE {$saleDate} >= ? {$cws}{$sd('sales', 's')}", [$from]);
        $purTot = $has('purchases', 'total_amount') ? 'p.total_amount' : 'p.total'; $purDate = $has('purchases', 'purchase_date') ? 'p.purchase_date' : 'DATE(p.created_at)';
        self::section($D, 'purchases', "SELECT p.id, p.company_id, p.supplier_id, sp.name AS supplier, {$purDate} AS date, {$purTot} AS total, p.paid_amount, p.due_amount, p.payment_status, p.due_date FROM purchases p LEFT JOIN suppliers sp ON sp.id = p.supplier_id WHERE {$purDate} >= ? {$cwp}{$sd('purchases', 'p')}", [$from]);
        $expCol = $has('notices', 'expiry_date') ? 'expiry_date' : ($has('notices', 'expires_at') ? 'expires_at' : 'NULL'); $pubCol = $has('notices', 'publish_date') ? 'publish_date' : 'DATE(created_at)';
        self::section($D, 'notices', "SELECT id, company_id, title, {$pubCol} AS published_at, {$expCol} AS expires_at FROM notices WHERE 1=1 {$sd('notices')} ORDER BY created_at DESC LIMIT 20");

        // ============================================================
        //  The service business the general ledger does not capture.
        //  Epal sells air tickets, visas, contract files and contract
        //  flights — `sales`/`purchases` are unused, so revenue and the
        //  real AR/AP live in these invoice tables. Reading only the
        //  ledger understates the business (a month can post ৳42k of
        //  4110 against ৳8.9 L of confirmed ticket sales), and reading
        //  only payment_schedules reports receivables of zero while
        //  lakhs sit unpaid on invoices. EON reads both.
        // ============================================================
        $co = fn(string $t, string $a) => $has($t, 'company_id') ? ($company ? " AND {$a}.company_id = " . (int) $company : '') : '';

        // --- air ticket sales (client_id → users) ---
        self::section($D, 'ticket_sales', "SELECT s.id, s.company_id, s.invoice_no AS invoice, s.client_id, u.name AS client, u.phone AS client_phone, s.sale_date AS date, s.due_date, s.total_amount AS total, s.paid_amount, s.due_amount, s.payment_status, s.status, s.bank_id
            FROM ticket_sales s LEFT JOIN users u ON u.id = s.client_id WHERE s.sale_date >= ? {$co('ticket_sales', 's')}{$sd('ticket_sales', 's')} ORDER BY s.sale_date DESC", [$from]);
        // --- visa sales ---
        self::section($D, 'visa_sales', "SELECT s.id, s.company_id, s.invoice_number AS invoice, s.client_id, u.name AS client, s.voucher_date AS date, s.receivable_date, s.total_amount AS total, s.paid_amount, s.due_amount, s.payment_method, s.status, s.bank_id
            FROM visa_sales s LEFT JOIN users u ON u.id = s.client_id WHERE s.voucher_date >= ? {$co('visa_sales', 's')}{$sd('visa_sales', 's')} ORDER BY s.voucher_date DESC", [$from]);
        // --- contract file sales ---
        self::section($D, 'contract_file_sales', "SELECT s.id, s.company_id, s.invoice_number AS invoice, s.client_id, u.name AS client, s.sale_date AS date, s.receivable_date, s.files_count, s.total_amount AS total, s.paid_amount, s.due_amount, s.vendor_cost, s.payment_status, s.payment_method, s.bank_id
            FROM contract_file_sales s LEFT JOIN users u ON u.id = s.client_id WHERE s.sale_date >= ? {$co('contract_file_sales', 's')}{$sd('contract_file_sales', 's')} ORDER BY s.sale_date DESC", [$from]);
        // --- contract flight (group seat) bookings ---
        self::section($D, 'contract_flight_bookings', "SELECT b.id, b.company_id, b.booking_number AS invoice, b.client_id, u.name AS client, DATE(b.created_at) AS date, b.receivable_date, b.seats, b.unit_price, b.total_amount AS total, b.paid_amount, b.due_amount, b.payment_status, b.bank_id
            FROM contract_flight_bookings b LEFT JOIN users u ON u.id = b.client_id WHERE b.created_at >= ? {$co('contract_flight_bookings', 'b')}{$sd('contract_flight_bookings', 'b')} ORDER BY b.created_at DESC", [$from]);
        // --- ticket purchases: the vendor payable side of a ticket sale ---
        // a ticket bought through a booking portal (BSP/IATA, an airline portal) is owed to the PORTAL, not to a vendor
        self::section($D, 'ticket_purchases', "SELECT p.id, p.company_id, p.vendor_id, v.name AS vendor, p.portal_id, o.name AS portal, p.ticket_no, p.airline_or_operator AS airline, p.ticket_type, p.trip_type, p.source, p.purchase_date AS date, p.due_date, p.amount AS total, p.paid_amount, p.due_amount, p.payment_status, p.status, p.bank_id
            FROM ticket_purchases p LEFT JOIN users v ON v.id = p.vendor_id LEFT JOIN portals o ON o.id = p.portal_id WHERE p.purchase_date >= ? {$co('ticket_purchases', 'p')}{$sd('ticket_purchases', 'p')} ORDER BY p.purchase_date DESC", [$from]);
        self::section($D, 'portals', "SELECT id, name, type, balance, next_payment_date, next_payment_amount, account_id, status FROM portals WHERE 1=1 {$sd('portals')}");
        // --- visa processing pipeline: work in hand, its cost and its sale price ---
        self::section($D, 'visa_processes', "SELECT p.id, p.application_id, p.passport_holder_id, h.name AS applicant, p.vendor_id, c.name AS country, t.name AS visa_category, p.visa_type, p.travel_date, p.embassy_fee, p.vfs_fee, p.our_service_fee, p.costing_price, p.cost_paid_amount, p.due_amount, p.sale_price, p.advance_received, p.payable_date, p.receivable_date, p.payment_status, p.status, p.stage, p.assigned_officer_id, o.name AS officer
            FROM visa_processes p LEFT JOIN passport_holders h ON h.id = p.passport_holder_id LEFT JOIN countries c ON c.id = p.country_id LEFT JOIN visa_categories t ON t.id = p.visa_category_id LEFT JOIN users o ON o.id = p.assigned_officer_id WHERE 1=1 {$sd('visa_processes', 'p')}");
        self::section($D, 'other_visa_services', "SELECT s.id, s.service_code, s.passport_holder_id, h.name AS applicant, t.name AS service_type, s.cost_price, s.sale_price, s.deadline, s.status, s.is_billable, s.assigned_officer_id
            FROM other_visa_services s LEFT JOIN passport_holders h ON h.id = s.passport_holder_id LEFT JOIN other_service_types t ON t.id = s.other_service_type_id WHERE 1=1 {$sd('other_visa_services', 's')}");
        self::section($D, 'passport_holders', "SELECT h.id, h.name, h.passport_no, h.nationality, h.phone, h.expiry_date, h.type, h.status, c.name AS category FROM passport_holders h LEFT JOIN passport_holder_categories c ON c.id = h.category_id WHERE 1=1 {$sd('passport_holders', 'h')}");

        // --- money movement outside the journal ---
        self::section($D, 'payments', "SELECT p.id, p.user_id, u.name AS person, p.employee_salary_id, p.payment_date AS date, p.bank_id, p.payment_method, p.transaction_no, p.amount, p.notes FROM payments p LEFT JOIN users u ON u.id = p.user_id WHERE p.payment_date >= ? ORDER BY p.payment_date DESC", [$from]);
        self::section($D, 'bank_transfers', "SELECT t.id, t.from_bank_id, f.name AS from_bank, t.to_bank_id, b.name AS to_bank, t.amount, t.payment_date AS date, t.reference_no, t.payment_method, t.status, t.remarks FROM bank_transfers t LEFT JOIN banks f ON f.id = t.from_bank_id LEFT JOIN banks b ON b.id = t.to_bank_id WHERE t.payment_date >= ? {$sd('bank_transfers', 't')} ORDER BY t.payment_date DESC", [$from]);
        self::section($D, 'petty_cash_floats', "SELECT f.id, f.company_id, f.custodian_id, u.name AS custodian, f.account_id, f.float_limit, f.status FROM petty_cash_floats f LEFT JOIN users u ON u.id = f.custodian_id WHERE 1=1 {$sd('petty_cash_floats', 'f')}");
        self::section($D, 'petty_cash_transactions', "SELECT t.id, t.petty_cash_float_id, t.type, t.amount, t.date, t.bank_id, t.note FROM petty_cash_transactions t WHERE t.date >= ? {$sd('petty_cash_transactions', 't')} ORDER BY t.date DESC", [$from]);
        self::section($D, 'employee_ledger', "SELECT l.id, l.user_id, u.name AS person, l.type, l.source_type, l.entry_date AS date, l.reference, l.debit, l.credit, l.balance, l.note FROM employee_ledger l LEFT JOIN users u ON u.id = l.user_id WHERE l.entry_date >= ? {$sd('employee_ledger', 'l')} ORDER BY l.entry_date DESC", [$from]);

        // --- people, the rest of the lifecycle ---
        self::section($D, 'payslips', "SELECT p.id, p.user_id, u.name AS person, p.employee_salary_id, p.payslip_number, p.issue_date, p.payment_status, p.bank_id FROM payslips p LEFT JOIN users u ON u.id = p.user_id WHERE p.issue_date >= ? ORDER BY p.issue_date DESC", [$from]);
        self::section($D, 'resignations', "SELECT r.id, r.employee_id AS user_id, u.name AS person, r.resign_date, r.last_working_day, r.resign_type, r.notice_period_days, r.status, r.reason FROM employee_resignations r LEFT JOIN users u ON u.id = r.employee_id WHERE 1=1 {$sd('employee_resignations', 'r')} ORDER BY r.resign_date DESC");
        self::section($D, 'shifts', "SELECT id, name, start_time, end_time FROM shifts WHERE 1=1 {$sd('shifts')}");

        // --- service desk ---
        self::section($D, 'support_tickets', "SELECT t.id, t.company_id, t.title, d.name AS department, t.priority, t.status, t.assigned_to, u.name AS assignee, t.customer_id, DATE(t.created_at) AS created_at FROM support_tickets t LEFT JOIN ticket_departments d ON d.id = t.ticket_department_id LEFT JOIN users u ON u.id = t.assigned_to WHERE 1=1 {$co('support_tickets', 't')}{$sd('support_tickets', 't')} ORDER BY t.created_at DESC");

        // numeric coercion for money fields (PDO returns strings)
        foreach (['payment_schedules' => ['amount', 'paid_amount'], 'expenses' => ['amount'], 'expense_budgets' => ['amount'], 'payroll' => ['gross_salary', 'absent_deduction', 'leave_deduction', 'late_deduction', 'early_leave_deduction', 'loan_deduction', 'advance_salary_deduction', 'overtime_salary', 'total_deductions', 'net_salary'], 'loans' => ['amount', 'remaining_amount', 'monthly_deduction'], 'advance_salaries' => ['amount'], 'employee_requests' => ['amount'], 'deals' => ['amount'], 'sales' => ['total', 'paid_amount', 'due_amount'], 'purchases' => ['total', 'paid_amount', 'due_amount'], 'accounts' => ['opening_balance'],
            'ticket_sales' => ['total', 'paid_amount', 'due_amount'], 'visa_sales' => ['total', 'paid_amount', 'due_amount'], 'contract_file_sales' => ['total', 'paid_amount', 'due_amount', 'vendor_cost'], 'contract_flight_bookings' => ['total', 'paid_amount', 'due_amount', 'unit_price'],
            'ticket_purchases' => ['total', 'paid_amount', 'due_amount'], 'visa_processes' => ['embassy_fee', 'vfs_fee', 'our_service_fee', 'costing_price', 'cost_paid_amount', 'due_amount', 'sale_price', 'advance_received'], 'other_visa_services' => ['cost_price', 'sale_price'],
            'payments' => ['amount'], 'bank_transfers' => ['amount'], 'petty_cash_floats' => ['float_limit'], 'petty_cash_transactions' => ['amount'], 'employee_ledger' => ['debit', 'credit', 'balance']] as $t => $cols) {
            foreach ($D[$t] as &$r) foreach ($cols as $c) $r[$c] = (float) ($r[$c] ?? 0); unset($r);
        }
        foreach (Dataset::TABLES as $t) foreach ($D[$t] as &$r) { foreach (['id', 'company_id', 'user_id', 'customer_id', 'supplier_id', 'lead_id', 'project_id', 'assigned_to', 'agent_id', 'account_id', 'bank_id', 'party_id', 'category_id', 'department_id'] as $k) if (array_key_exists($k, $r) && $r[$k] !== null && !is_array($r[$k])) $r[$k] = (int) $r[$k]; } unset($r);

        $D['meta']['warnings'] = self::$warnings;
        $D['meta']['generated_at'] = date('c');
        return $D;
    }

    /** run one section; on failure record a warning and leave the table empty */
    private static function section(array &$D, string $table, string $sql, array $params = []): void
    {
        try { $D[$table] = Db::rows(self::$pdo, $sql, $params); }
        catch (Throwable $e) { self::$warnings[] = "$table: " . $e->getMessage(); Log::warn("erp section $table failed", ['error' => $e->getMessage()]); $D[$table] = []; }
    }

    private static array $colCache = [];
    /** does this column exist on this ERP build? (cached per request) */
    public static function hasColumn(string $table, string $col): bool
    {
        if (!isset(self::$colCache[$table])) self::$colCache[$table] = array_map('strtolower', Db::columns(self::$pdo, $table));
        return in_array(strtolower($col), self::$colCache[$table], true);
    }

    private static function mins(string $t): int { $p = explode(':', $t); return (int) ($p[0] ?? 0) * 60 + (int) ($p[1] ?? 0); }

    /** guarded read-only SQL for the language model's sql tool */
    public static function safeSelect(string $sql, int $limit = 200): array
    {
        $s = trim($sql);
        if (!preg_match('/^\s*(select|with|show|describe|explain)\b/i', $s)) throw new InvalidArgumentException('only SELECT / SHOW / DESCRIBE statements are allowed');
        if (preg_match('/;\s*\S/', $s)) throw new InvalidArgumentException('one statement at a time');
        if (preg_match('/\b(insert|update|delete|drop|alter|create|truncate|replace|grant|revoke|lock|call|load|outfile|into\s+dumpfile|handler|prepare|execute|deallocate|set\b|use\b|kill\b|shutdown|flush|reset|purge|analyze|optimize|repair|for\s+update|lock\s+in\s+share)\b/i', $s)) throw new InvalidArgumentException('write / control statements are not allowed');
        if (preg_match('/\b(sleep|benchmark|get_lock|release_lock|load_file|master_pos_wait|wait_for_executed_gtid_set|sys_eval|sys_exec)\s*\(/i', $s)) throw new InvalidArgumentException('blocking or expensive functions are not allowed');
        if (preg_match('/\b(sessions|personal_access_tokens|password_reset_tokens|password_resets|jobs|failed_jobs|job_batches|cache|cache_locks|migrations|oauth_\w+|eon_\w+|mysql\.\w+|information_schema\.\w+|performance_schema\.\w+)\b/i', $s)) throw new InvalidArgumentException('that table is not readable through EON');
        if (preg_match('/\b(password|remember_token|two_factor_secret|two_factor_recovery_codes|api_token|secret|token|device_token)\b/i', $s)) throw new InvalidArgumentException('credential columns are not readable through EON');
        if (preg_match('/\/\*|--|#/', $s)) throw new InvalidArgumentException('comments are not allowed');
        $s = rtrim($s, "; \t\n");
        if (preg_match('/^\s*(select|with)\b/i', $s) && !preg_match('/\blimit\s+\d+/i', $s)) $s .= ' LIMIT ' . $limit;
        Log::info('sql_readonly', ['sql' => $s]);
        $pdo = Db::erp();
        try { $pdo->exec('SET SESSION MAX_EXECUTION_TIME=8000'); } catch (Throwable $e) {}   // MySQL ≥ 5.7.8 (ms)
        try { $pdo->exec('SET SESSION max_statement_time=8'); } catch (Throwable $e) {}      // MariaDB (s)
        $st = $pdo->query($s);
        $rows = $st->fetchAll();
        $deny = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'api_token', 'token', 'secret', 'device_token'];
        foreach ($rows as &$r) foreach ($deny as $c) unset($r[$c]); unset($r);
        return array_slice($rows, 0, $limit);
    }
}
