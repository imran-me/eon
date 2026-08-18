<?php
declare(strict_types=1);

/* The one dataset EON reads — same shape as ai-companion/eon-brain/domains/erp/dataset.js.
   Built from the ERP database (Erp::build) or loaded from the demo JSON; cached on disk. */
final class Dataset
{
    public const TABLES = ['companies', 'accounts', 'journal_entries', 'banks', 'payment_schedules', 'expenses', 'expense_budgets', 'departments', 'designations', 'employees', 'attendances', 'leave_types', 'leaves', 'holidays', 'payroll', 'loans', 'advance_salaries', 'employee_requests', 'customers', 'suppliers', 'leads', 'deals', 'projects', 'tasks', 'office_todos', 'sales', 'purchases', 'notices',
        // the service business: what Epal actually sells, and what it owes for it
        'ticket_sales', 'visa_sales', 'contract_file_sales', 'contract_flight_bookings', 'ticket_purchases', 'visa_processes', 'other_visa_services', 'passport_holders',
        // money that moves outside the journal
        'payments', 'bank_transfers', 'petty_cash_floats', 'petty_cash_transactions', 'employee_ledger',
        // the rest of the people lifecycle, and the service desk
        'payslips', 'resignations', 'shifts', 'support_tickets', 'portals',
        // the party ledger, what is on each invoice, the audit trail, and Wood Art
        'party_transactions', 'ticket_sale_items', 'visa_sale_items', 'payment_schedule_logs', 'expense_items', 'salary_templates', 'device_users', 'wa_projects'];

    /** every section that carries an invoice-style due: [table, date column, label] — the real AR/AP */
    public const SALES_TABLES = [
        'ticket_sales' => 'air ticket',
        'visa_sales' => 'visa',
        'contract_file_sales' => 'contract file',
        'contract_flight_bookings' => 'contract flight',
    ];

    public static function empty(): array
    {
        $d = ['meta' => ['source' => 'empty', 'generated_at' => date('c'), 'currency' => 'BDT', 'today' => date('Y-m-d'), 'company_id' => null]];
        foreach (self::TABLES as $t) $d[$t] = [];
        return $d;
    }

    /** the current dataset: DB → cache → demo, in that order */
    public static function current(?int $company = null, bool $fresh = false): array
    {
        $key = 'dataset-' . ($company ?? 'all');
        if (!$fresh && ($c = self::cacheGet($key)) !== null) return $c;
        if (Config::dbEnabled() && Db::available()) {
            $d = Erp::build($company);
            self::cachePut($key, $d);
            return $d;
        }
        return self::demo();
    }

    public static function demo(): array
    {
        $file = Config::get('demo_dataset');
        if ($file && is_file($file)) {
            $d = json_decode((string) file_get_contents($file), true);
            if (is_array($d) && isset($d['meta'])) { $d['meta']['source'] = 'demo'; $d['meta']['today'] = $d['meta']['today'] ?? date('Y-m-d'); return self::normalise($d); }
        }
        $d = self::empty(); $d['meta']['source'] = 'empty'; return $d;
    }

    public static function source(): string
    {
        if (Config::dbEnabled() && Db::available()) return 'erp';
        $file = Config::get('demo_dataset');
        return ($file && is_file($file)) ? 'demo' : 'empty';
    }

    /** make sure every table exists and ids are ints where numeric */
    public static function normalise(array $d): array
    {
        foreach (self::TABLES as $t) if (!isset($d[$t]) || !is_array($d[$t])) $d[$t] = [];
        return $d;
    }

    // ---- disk cache ----
    private static function cacheFile(string $key): string { return EON_ROOT . '/storage/cache/' . preg_replace('/[^a-z0-9\-]/i', '_', $key) . '.json'; }
    public static function cacheGet(string $key): ?array
    {
        $f = self::cacheFile($key); $ttl = (int) Config::get('cache_ttl', 300);
        if (!is_file($f) || filemtime($f) < time() - $ttl) return null;
        $d = json_decode((string) file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }
    public static function cachePut(string $key, array $d): void
    {
        @file_put_contents(self::cacheFile($key), json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    public static function cacheClear(): void { foreach (glob(EON_ROOT . '/storage/cache/*.json') ?: [] as $f) @unlink($f); }
}
