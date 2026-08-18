<?php
declare(strict_types=1);

/* ============================================================
   Analytics — server-side port of the decision layers (finance,
   people, CRM, operations) over the dataset array. Used by the
   language-model tools, the brief endpoint and the cron watchers.
   Mirrors ai-companion/eon-brain/domains/erp/*.js so what the boss
   hears from the server equals what the screen shows.
   ============================================================ */
final class Analytics
{
    private array $D; private ?int $co; private string $today;

    public function __construct(array $D, ?int $company = null)
    {
        $this->D = $D; $this->co = $company; $this->today = $D['meta']['today'] ?? date('Y-m-d');
    }

    // ---------- helpers ----------
    private function inCo(array $r, bool $sharedOk = false): bool { if ($this->co === null) return true; $c = $r['company_id'] ?? null; return ($sharedOk && $c === null) || (int) $c === $this->co; }
    public function rows(string $t, bool $sharedOk = false): array
    {
        $viaEmp = in_array($t, ['advance_salaries', 'employee_requests', 'loans'], true);
        return array_values(array_filter($this->D[$t] ?? [], function ($r) use ($sharedOk, $viaEmp) {
            if ($this->co !== null && $viaEmp && !isset($r['company_id'])) { $e = $this->emp($r['user_id'] ?? null); return $e ? (int) $e['company_id'] === $this->co : $sharedOk; }
            return $this->inCo($r, $sharedOk);
        }));
    }
    private function emp(int|string|null $id): ?array { foreach ($this->D['employees'] ?? [] as $e) if ((int) $e['id'] === (int) $id) return $e; return null; }
    /** employees / attendances / tasks / leads under the current company scope */
    private function scoped(string $t): array { return $this->co === null ? ($this->D[$t] ?? []) : array_values(array_filter($this->D[$t] ?? [], fn($r) => $this->inCo($r))); }
    private static function sum(array $a, string $k): float { $s = 0.0; foreach ($a as $r) $s += (float) ($r[$k] ?? 0); return $s; }
    private function days(string $a, string $b): int { return (int) round((strtotime($b) - strtotime($a)) / 86400); }
    private function coName(int|string|null $id): string { foreach ($this->D['companies'] ?? [] as $c) if ((int) $c['id'] === (int) $id) return (string) ($c['short_name'] ?: $c['name']); return '#' . $id; }
    private function empName(int|string|null $id): string { foreach ($this->D['employees'] ?? [] as $e) if ((int) $e['id'] === (int) $id) return (string) $e['name']; return '#' . $id; }
    public static function bdt(float $n): string { $neg = $n < 0; $n = abs(round($n)); $s = (string) (int) $n; if (strlen($s) > 3) { $last3 = substr($s, -3); $rest = substr($s, 0, -3); $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest); $s = $rest . ',' . $last3; } return ($neg ? '−' : '') . '৳' . $s; }
    private static function trimZeros(string $s): string { return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s; }
    public static function bdtk(float $n): string { $a = abs($n); $s = $n < 0 ? '−' : ''; if ($a >= 1e7) return $s . '৳' . self::trimZeros(number_format($a / 1e7, $a >= 1e8 ? 0 : 1, '.', '')) . ' Cr'; if ($a >= 1e5) return $s . '৳' . self::trimZeros(number_format($a / 1e5, $a >= 1e6 ? 0 : 1, '.', '')) . ' L'; if ($a >= 1e3) return $s . '৳' . self::trimZeros(number_format($a / 1e3, 1, '.', '')) . 'k'; return self::bdt($n); }
    private static function acctType(string $code): string { $c = (int) substr($code, 0, 1); return match ($c) { 1 => 'asset', 2 => 'liability', 3 => 'equity', 4 => 'income', default => 'expense' }; }

    // ---------- finance ----------
    public function ledgerBalances(?string $from = null, ?string $to = null): array
    {
        $bal = [];
        foreach ($this->D['journal_entries'] ?? [] as $je) {
            if ($this->co !== null && (int) $je['company_id'] !== $this->co) continue;
            if ($from && $je['date'] < $from) continue; if ($to && $je['date'] > $to) continue;
            foreach ($je['items'] as $it) { $c = (string) $it['account_code']; if (!isset($bal[$c])) $bal[$c] = ['code' => $c, 'debit' => 0.0, 'credit' => 0.0]; $bal[$c]['debit'] += (float) $it['debit']; $bal[$c]['credit'] += (float) $it['credit']; }
        }
        $names = []; foreach ($this->D['accounts'] ?? [] as $a) $names[(string) $a['code']] = $a['name'];
        foreach ($bal as &$b) { $b['type'] = self::acctType($b['code']); $b['name'] = $names[$b['code']] ?? $b['code']; $b['balance'] = in_array($b['type'], ['asset', 'expense'], true) ? $b['debit'] - $b['credit'] : $b['credit'] - $b['debit']; } unset($b);
        ksort($bal); return $bal;
    }
    public function trialBalance(): array
    {
        $rows = []; $dr = 0; $cr = 0;
        foreach ($this->ledgerBalances() as $b) { $isDr = in_array($b['type'], ['asset', 'expense'], true); $d = max(0, $isDr ? $b['balance'] : -$b['balance']); $c = max(0, $isDr ? -$b['balance'] : $b['balance']); $rows[] = ['code' => $b['code'], 'name' => $b['name'], 'debit' => round($d), 'credit' => round($c)]; $dr += $d; $cr += $c; }
        return ['rows' => $rows, 'total_debit' => round($dr), 'total_credit' => round($cr), 'balanced' => abs($dr - $cr) < 2];
    }
    /* Sales actually invoiced in a period, per service line. The ledger only sees what
       was journalised, and Epal's ticket/visa desks invoice well ahead of posting — so a
       month can show ৳42k of Air Ticket Sales against ৳8.9 L of confirmed invoices. EON
       reports both and names the gap rather than quietly under-reporting the business. */
    public function salesBooked(?string $from = null, ?string $to = null): array
    {
        $from = $from ?: substr($this->today, 0, 7) . '-01'; $to = $to ?: $this->today;
        $lines = []; $total = 0.0; $collected = 0.0; $count = 0;
        foreach (Dataset::SALES_TABLES as $table => $label) {
            $amt = 0.0; $paid = 0.0; $n = 0;
            foreach ($this->rows($table) as $r) {
                $d = substr((string) ($r['date'] ?? ''), 0, 10);
                if ($d === '' || $d < $from || $d > $to) continue;
                $amt += (float) ($r['total'] ?? 0); $paid += (float) ($r['paid_amount'] ?? 0); $n++;
            }
            if ($n) { $lines[] = ['line' => $label, 'invoiced' => round($amt), 'collected' => round($paid), 'invoices' => $n]; $total += $amt; $collected += $paid; $count += $n; }
        }
        usort($lines, fn($a, $b) => $b['invoiced'] <=> $a['invoiced']);
        return ['from' => $from, 'to' => $to, 'invoiced' => round($total), 'collected' => round($collected), 'outstanding' => round($total - $collected), 'invoices' => $count, 'by_line' => $lines];
    }

    public function profitAndLoss(?string $from = null, ?string $to = null): array
    {
        $bal = $this->ledgerBalances($from, $to);
        $inc = 0; $dir = 0; $opex = 0; $fin = 0; $top = ['income' => [], 'opex' => []];
        foreach ($bal as $b) { if ($b['type'] === 'income') { $inc += $b['balance']; $top['income'][] = ['code' => $b['code'], 'name' => $b['name'], 'amount' => round($b['balance'])]; } elseif ($b['type'] === 'expense') { $p = $b['code'][0]; if ($p === '5') $dir += $b['balance']; elseif ($p === '8') $fin += $b['balance']; else { $opex += $b['balance']; $top['opex'][] = ['code' => $b['code'], 'name' => $b['name'], 'amount' => round($b['balance'])]; } } }
        usort($top['income'], fn($a, $b) => $b['amount'] <=> $a['amount']); usort($top['opex'], fn($a, $b) => $b['amount'] <=> $a['amount']);
        $net = $inc - $dir - $opex - $fin;
        $sb = $this->salesBooked($from, $to);
        return ['from' => $from, 'to' => $to, 'income' => round($inc), 'direct_cost' => round($dir), 'opex' => round($opex), 'finance_cost' => round($fin), 'gross_profit' => round($inc - $dir), 'net_profit' => round($net), 'margin_pct' => $inc ? (int) round($net / $inc * 100) : 0, 'top_income' => array_slice($top['income'], 0, 6), 'top_opex' => array_slice($top['opex'], 0, 8),
            // what the desks actually invoiced in the same period, and how much of it never reached the ledger
            'sales_invoiced' => $sb['invoiced'], 'sales_by_line' => $sb['by_line'], 'unposted_sales' => round($sb['invoiced'] - $inc), 'ledger_covers_pct' => $sb['invoiced'] > 0 ? (int) round($inc / $sb['invoiced'] * 100) : null];
    }
    public function balanceSheet(): array
    {
        $bal = $this->ledgerBalances(); $A = []; $L = []; $E = [];
        foreach ($bal as $b) { if (abs($b['balance']) < 1) continue; $row = ['code' => $b['code'], 'name' => $b['name'], 'amount' => round($b['balance'])]; if ($b['type'] === 'asset') $A[] = $row; elseif ($b['type'] === 'liability') $L[] = $row; elseif ($b['type'] === 'equity') $E[] = $row; }
        $pl = $this->profitAndLoss(); $tA = self::sum($A, 'amount'); $tL = self::sum($L, 'amount'); $tE = self::sum($E, 'amount') + $pl['net_profit'];
        return ['assets' => $A, 'liabilities' => $L, 'equity' => $E, 'current_period_profit' => $pl['net_profit'], 'total_assets' => round($tA), 'total_liabilities' => round($tL), 'total_equity' => round($tE), 'balanced' => abs($tA - ($tL + $tE)) < 2];
    }
    public function cash(): array
    {
        $banks = $this->rows('banks'); usort($banks, fn($a, $b) => (float) $b['balance'] <=> (float) $a['balance']);
        $out = array_map(fn($b) => ['id' => $b['id'], 'name' => $b['name'], 'type' => $b['type'], 'company' => $this->coName($b['company_id']), 'balance' => round((float) $b['balance'])], $banks);
        $byCo = []; foreach ($banks as $b) { $k = (int) $b['company_id']; $byCo[$k] = ($byCo[$k] ?? 0) + (float) $b['balance']; } arsort($byCo);
        return ['total' => round(self::sum($banks, 'balance')), 'accounts' => $out, 'by_company' => array_map(fn($k, $v) => ['company' => $this->coName($k), 'total' => round($v)], array_keys($byCo), $byCo), 'low' => array_values(array_filter($out, fn($b) => $b['balance'] < 50000 && $b['type'] !== 'cash'))];
    }
    /* ------------------------------------------------------------------
       The dues that never reach payment_schedules.
       Epal's money sits on invoices: ticket / visa / contract-file /
       contract-flight sales owe us, ticket purchases and visa costing
       owe the vendor. Shaped exactly like a payment_schedule row so
       aging, buckets, top parties and the overdue list all work on it.
       ------------------------------------------------------------------ */
    public function invoiceDues(string $type): array
    {
        $out = [];
        $add = function (array $r, string $party, string $label, string $dateCol, float $amount, float $paid) use (&$out, $type) {
            if (round($amount - $paid) <= 0) return;
            $when = (string) ($r[$dateCol] ?? '') ?: (string) ($r['date'] ?? $this->today);
            $out[] = [
                'id' => $label . '-' . ($r['id'] ?? 0), 'company_id' => $r['company_id'] ?? null, 'type' => $type,
                'party_type' => $party, 'party_id' => $r[$party === 'vendor' ? 'vendor_id' : 'client_id'] ?? null,
                'party_name' => (string) ($r[$party === 'vendor' ? 'vendor' : 'client'] ?? '') ?: 'Unnamed ' . $party,
                'source_label' => $label . ' ' . (string) ($r['invoice'] ?? $r['id'] ?? ''),
                'amount' => $amount, 'paid_amount' => $paid, 'scheduled_date' => substr($when, 0, 10),
                'status' => 'pending', 'priority' => null, 'paid_date' => null, 'from_invoice' => true,
            ];
        };
        if ($type === 'receive') {
            foreach (Dataset::SALES_TABLES as $table => $label) {
                foreach ($this->rows($table) as $r) {
                    $paid = (float) ($r['paid_amount'] ?? 0);
                    $total = (float) ($r['total'] ?? 0);
                    // trust the ERP's own due_amount when it carries one; else total − paid
                    $due = array_key_exists('due_amount', $r) ? (float) $r['due_amount'] : $total - $paid;
                    $add($r, 'client', $label, isset($r['due_date']) && $r['due_date'] ? 'due_date' : 'receivable_date', $paid + $due, $paid);
                }
            }
        } elseif ($type === 'pay') {
            foreach ($this->rows('ticket_purchases') as $r) {
                $paid = (float) ($r['paid_amount'] ?? 0);
                $due = array_key_exists('due_amount', $r) ? (float) $r['due_amount'] : (float) ($r['total'] ?? 0) - $paid;
                if (round($due) <= 0) continue;
                // bought on a portal (BSP/IATA, an airline portal) → the money is owed to the portal
                $name = (string) ($r['vendor'] ?? '') ?: (string) ($r['portal'] ?? '') ?: (string) ($r['airline'] ?? '') ?: 'Ticket vendor';
                $pid = $r['vendor_id'] ?? (isset($r['portal_id']) && $r['portal_id'] ? 'portal:' . $r['portal_id'] : null);
                $out[] = ['id' => 'ticket-purchase-' . ($r['id'] ?? 0), 'company_id' => $r['company_id'] ?? null, 'type' => 'pay', 'party_type' => 'vendor', 'party_id' => $pid,
                    'party_name' => $name, 'source_label' => 'ticket purchase ' . (string) ($r['ticket_no'] ?? $r['id'] ?? ''),
                    'amount' => $paid + $due, 'paid_amount' => $paid, 'scheduled_date' => substr((string) ((($r['due_date'] ?? '') ?: ($r['date'] ?? '')) ?: $this->today), 0, 10),
                    'status' => 'pending', 'priority' => null, 'paid_date' => null, 'from_invoice' => true];
            }
            foreach ($this->rows('visa_processes') as $r) {   // what the visa costs us, still unpaid to the vendor
                $paid = (float) ($r['cost_paid_amount'] ?? 0);
                $due = (float) ($r['costing_price'] ?? 0) - $paid;
                if (round($due) <= 0) continue;
                $out[] = ['id' => 'visa-cost-' . ($r['id'] ?? 0), 'company_id' => null, 'type' => 'pay', 'party_type' => 'vendor', 'party_id' => $r['vendor_id'] ?? null,
                    'party_name' => 'Visa vendor' . (isset($r['country']) && $r['country'] ? ' — ' . $r['country'] : ''), 'source_label' => 'visa ' . (string) ($r['application_id'] ?? $r['id'] ?? ''),
                    'amount' => $paid + $due, 'paid_amount' => $paid, 'scheduled_date' => substr((string) (($r['payable_date'] ?? '') ?: $this->today), 0, 10),
                    'status' => 'pending', 'priority' => null, 'paid_date' => null, 'from_invoice' => true];
            }
        }
        return $out;
    }

    public function schedules(string $type): array
    {
        $rows = array_merge($this->rows('payment_schedules'), $this->invoiceDues($type));
        $open = []; foreach ($rows as $p) { if ($p['type'] !== $type || !in_array($p['status'], ['pending', 'overdue'], true)) continue; $due = (float) $p['amount'] - (float) ($p['paid_amount'] ?? 0); if (round($due) <= 0) continue; $days = $this->days((string) $p['scheduled_date'], $this->today); $p['due'] = round($due); $p['days_overdue'] = max(0, $days); $p['bucket'] = $days <= 0 ? 'current' : ($days <= 30 ? '1-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+'))); $p['overdue'] = $days > 0; $open[] = $p; }
        $overdue = array_values(array_filter($open, fn($p) => $p['overdue'])); usort($overdue, fn($a, $b) => $b['days_overdue'] <=> $a['days_overdue'] ?: $b['due'] <=> $a['due']);
        $buckets = []; foreach (['current', '1-30', '31-60', '61-90', '90+'] as $bk) { $s = array_filter($open, fn($p) => $p['bucket'] === $bk); $buckets[] = ['bucket' => $bk, 'amount' => round(self::sum($s, 'due')), 'count' => count($s)]; }
        $byParty = []; foreach ($open as $p) { $k = $p['party_type'] . ':' . $p['party_id']; if (!isset($byParty[$k])) $byParty[$k] = ['party_type' => $p['party_type'], 'party_id' => $p['party_id'], 'party_name' => $p['party_name'], 'due' => 0, 'overdue' => 0, 'count' => 0, 'oldest' => 0]; $byParty[$k]['due'] += $p['due']; if ($p['overdue']) $byParty[$k]['overdue'] += $p['due']; $byParty[$k]['count']++; $byParty[$k]['oldest'] = max($byParty[$k]['oldest'], $p['days_overdue']); }
        $byParty = array_values($byParty); usort($byParty, fn($a, $b) => $b['overdue'] <=> $a['overdue'] ?: $b['due'] <=> $a['due']);
        $soon = array_values(array_filter($open, fn($p) => !$p['overdue'] && $this->days($this->today, (string) $p['scheduled_date']) <= 7));
        return ['type' => $type, 'total' => round(self::sum($open, 'due')), 'count' => count($open), 'overdue_total' => round(self::sum($overdue, 'due')), 'overdue_count' => count($overdue), 'buckets' => $buckets, 'by_party' => array_slice($byParty, 0, 15), 'overdue' => array_slice(array_map(fn($p) => ['party' => $p['party_name'], 'ref' => $p['source_label'], 'due' => $p['due'], 'days_overdue' => $p['days_overdue'], 'company' => $this->coName($p['company_id']), 'party_type' => $p['party_type']], $overdue), 0, 25), 'due_in_7_days' => round(self::sum($soon, 'due')), 'due_in_7_days_count' => count($soon)];
    }
    public function expensesVsBudget(?string $month = null): array
    {
        $mk = $month ?: substr($this->today, 0, 7); $cats = [];
        foreach ($this->rows('expenses') as $e) { if (substr((string) $e['expense_date'], 0, 7) !== $mk) continue; $c = (string) ($e['category'] ?? 'Uncategorised'); $cats[$c] ??= ['category' => $c, 'spent' => 0, 'pending' => 0, 'budget' => 0, 'count' => 0]; $cats[$c]['spent'] += (float) $e['amount']; $cats[$c]['count']++; if (($e['approval_status'] ?? 'approved') !== 'approved') $cats[$c]['pending'] += (float) $e['amount']; }
        foreach ($this->rows('expense_budgets') as $b) { $c = (string) ($b['category'] ?? ''); $cats[$c] ??= ['category' => $c, 'spent' => 0, 'pending' => 0, 'budget' => 0, 'count' => 0]; $mult = match (strtolower((string) ($b['period'] ?? 'monthly'))) { 'weekly' => 4.33, 'quarterly' => 1 / 3, 'yearly' => 1 / 12, default => 1 }; $cats[$c]['budget'] += (float) $b['amount'] * $mult; }
        $rows = array_values($cats); foreach ($rows as &$r) { $r['spent'] = round($r['spent']); $r['budget'] = round($r['budget']); $r['pct'] = $r['budget'] ? (int) round($r['spent'] / $r['budget'] * 100) : null; $r['over'] = $r['budget'] && $r['spent'] > $r['budget']; } unset($r);
        usort($rows, fn($a, $b) => ($b['pct'] ?? 0) <=> ($a['pct'] ?? 0));
        return ['month' => $mk, 'rows' => $rows, 'total_spent' => round(self::sum($rows, 'spent')), 'total_budget' => round(self::sum($rows, 'budget')), 'over' => array_values(array_filter($rows, fn($r) => $r['over']))];
    }
    public function pendingExpenses(): array
    {
        $rows = array_values(array_filter($this->rows('expenses'), fn($e) => ($e['approval_status'] ?? '') === 'pending')); usort($rows, fn($a, $b) => (float) $b['amount'] <=> (float) $a['amount']);
        return ['count' => count($rows), 'total' => round(self::sum($rows, 'amount')), 'rows' => array_slice(array_map(fn($e) => ['id' => $e['id'], 'title' => $e['title'], 'category' => $e['category'], 'amount' => round((float) $e['amount']), 'by' => $e['user_name'], 'company' => $this->coName($e['company_id']), 'date' => $e['expense_date']], $rows), 0, 20)];
    }
    public function revenueTrend(int $months = 6): array
    {
        $out = []; $t = strtotime($this->today);
        for ($i = $months - 1; $i >= 0; $i--) { $mk = date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01', $t)))); $pl = $this->profitAndLoss($mk . '-01', $mk . '-31'); $out[] = ['month' => $mk, 'income' => $pl['income'], 'direct' => $pl['direct_cost'], 'opex' => $pl['opex'], 'net' => $pl['net_profit']]; }
        $mtd = end($out); $prev = $out[count($out) - 2] ?? $mtd; $day = (int) date('j', $t); $dim = (int) date('t', $t); $run = $mtd['income'] * $dim / max(1, $day);
        return ['series' => $out, 'mtd' => $mtd, 'prev' => $prev, 'run_rate' => round($run), 'vs_prev_pct' => $prev['income'] ? (int) round(($run - $prev['income']) / $prev['income'] * 100) : 0];
    }

    // ---------- people ----------
    public function attendanceToday(): array
    {
        $emps = array_filter($this->rows('employees'), fn($e) => ($e['status'] ?? 'active') === 'active'); $A = []; $seen = []; $lastDate = '';
        foreach ($this->rows('attendances') as $a) {
            if ($a['date'] === $this->today) $A[(int) $a['user_id']] = $a;
            $seen[(int) $a['user_id']] = true;                                  // who is on the attendance system at all
            if ((string) $a['date'] > $lastDate) $lastDate = (string) $a['date'];
        }
        // Only part of the payroll punches in (device users / selfie check-in). Measuring
        // presence against every active employee reads "0 of 87 in" on a normal morning,
        // which is false: the honest denominator is the people the system actually tracks.
        $tracked = count(array_filter($emps, fn($e) => isset($seen[(int) $e['id']])));
        $present = []; $absent = []; $late = []; $leave = []; $notYet = [];
        foreach ($emps as $e) { $a = $A[(int) $e['id']] ?? null; $item = ['id' => $e['id'], 'name' => $e['name'], 'department' => $e['department'], 'company' => $this->coName($e['company_id']), 'check_in' => $a['check_in'] ?? null, 'late_minutes' => (int) ($a['late_minutes'] ?? 0)]; if (!$a) { $notYet[] = $item; continue; } if ($a['status'] === 'present') { $present[] = $item; if ($item['late_minutes'] > 0) $late[] = $item; } elseif ($a['status'] === 'absent') $absent[] = $item; elseif ($a['status'] === 'leave') $leave[] = $item; }
        usort($late, fn($a, $b) => $b['late_minutes'] <=> $a['late_minutes']);
        $online = array_values(array_map(fn($e) => $e['name'], array_filter($emps, fn($e) => !empty($e['last_seen_at']) && time() - strtotime((string) $e['last_seen_at']) < 300)));
        $w = (int) date('w', strtotime($this->today));
        $den = $tracked ?: count($emps);
        return ['date' => $this->today, 'weekend' => in_array($w, [5, 6], true), 'total' => count($emps), 'tracked' => $tracked, 'recorded_today' => count($A), 'no_data_yet' => count($A) === 0, 'last_recorded_date' => $lastDate,
            'present' => count($present), 'present_pct' => $den ? (int) round(count($present) / $den * 100) : 0, 'absent' => count($absent), 'late' => count($late), 'on_leave' => count($leave), 'not_punched' => count($notYet),
            'absent_list' => array_slice($absent, 0, 20), 'late_list' => array_slice($late, 0, 15), 'on_leave_list' => array_slice($leave, 0, 15), 'online' => array_slice($online, 0, 20), 'online_count' => count($online)];
    }
    public function latePatterns(int $days = 30): array
    {
        $from = date('Y-m-d', strtotime("-$days days", strtotime($this->today))); $by = [];
        foreach ($this->rows('attendances') as $a) { if ($a['date'] < $from || $a['date'] > $this->today) continue; $u = (int) $a['user_id']; $by[$u] ??= ['workdays' => 0, 'present' => 0, 'absent' => 0, 'late_days' => 0, 'late_minutes' => 0, 'overtime_minutes' => 0]; $by[$u]['workdays']++; if ($a['status'] === 'present') { $by[$u]['present']++; if ((int) $a['late_minutes'] > 0) { $by[$u]['late_days']++; $by[$u]['late_minutes'] += (int) $a['late_minutes']; } $by[$u]['overtime_minutes'] += (int) ($a['overtime_minutes'] ?? 0); } elseif ($a['status'] === 'absent') $by[$u]['absent']++; }
        $rows = []; foreach ($by as $u => $s) { $s['id'] = $u; $s['name'] = $this->empName($u); $s['attendance_pct'] = $s['workdays'] ? (int) round($s['present'] / $s['workdays'] * 100) : 0; $s['late_pct'] = $s['present'] ? (int) round($s['late_days'] / $s['present'] * 100) : 0; $rows[] = $s; }
        $chronicLate = array_values(array_filter($rows, fn($r) => $r['late_pct'] >= 30 && $r['late_days'] >= 4)); usort($chronicLate, fn($a, $b) => $b['late_pct'] <=> $a['late_pct']);
        $chronicAbsent = array_values(array_filter($rows, fn($r) => $r['absent'] >= 3 && $r['attendance_pct'] < 85)); usort($chronicAbsent, fn($a, $b) => $b['absent'] <=> $a['absent']);
        return ['days' => $days, 'chronic_late' => array_slice($chronicLate, 0, 15), 'chronic_absent' => array_slice($chronicAbsent, 0, 15), 'avg_attendance_pct' => $rows ? (int) round(self::sum($rows, 'attendance_pct') / count($rows)) : 0];
    }
    public function payroll(?string $month = null): array
    {
        $keys = array_values(array_unique(array_filter(array_map(fn($p) => $p['month_key'] ?? null, $this->rows('payroll'))))); sort($keys);
        $mk = $month ?: (end($keys) ?: substr($this->today, 0, 7));
        $rows = array_values(array_filter($this->rows('payroll'), fn($p) => ($p['month_key'] ?? '') === $mk));
        $prevKey = date('Y-m', strtotime('-1 month', strtotime($mk . '-01'))); $prev = array_filter($this->rows('payroll'), fn($p) => ($p['month_key'] ?? '') === $prevKey);
        $pending = array_values(array_filter($rows, fn($p) => strtolower((string) $p['status']) === 'pending'));
        $byCo = []; foreach ($rows as $p) { $k = (int) $p['company_id']; $byCo[$k] ??= ['company' => $this->coName($k), 'heads' => 0, 'net' => 0, 'pending' => 0]; $byCo[$k]['heads']++; $byCo[$k]['net'] += (float) $p['net_salary']; if (strtolower((string) $p['status']) === 'pending') $byCo[$k]['pending']++; }
        return ['month' => $mk, 'heads' => count($rows), 'gross' => round(self::sum($rows, 'gross_salary')), 'deductions' => round(self::sum($rows, 'total_deductions')), 'net' => round(self::sum($rows, 'net_salary')), 'late_deductions' => round(self::sum($rows, 'late_deduction')), 'absent_deductions' => round(self::sum($rows, 'absent_deduction')), 'loan_recoveries' => round(self::sum($rows, 'loan_deduction')), 'overtime_paid' => round(self::sum($rows, 'overtime_salary')), 'pending_count' => count($pending), 'pending_net' => round(self::sum($pending, 'net_salary')), 'prev_net' => round(self::sum($prev, 'net_salary')), 'by_company' => array_values($byCo)];
    }
    public function findEmployee(string $q): ?array
    {
        $q = strtolower(preg_replace('/[^a-z\s]/i', ' ', $q)); $toks = array_filter(explode(' ', $q), fn($t) => strlen($t) >= 3); $best = null; $bs = 0;
        foreach ($this->scoped('employees') as $e) { $n = strtolower((string) $e['name']); $s = 0; if ($n && str_contains($q, $n)) $s = 100; else foreach ($toks as $t) { if (in_array($t, explode(' ', $n), true)) $s += 10; elseif (str_contains($n, $t)) $s += 4; } if ($s > $bs) { $bs = $s; $best = $e; } }
        return $bs >= 8 ? $best : null;
    }
    public function evaluate(int $uid, int $days = 30): ?array
    {
        $e = null; foreach ($this->scoped('employees') as $x) if ((int) $x['id'] === $uid) { $e = $x; break; } if (!$e) return null;
        $from = date('Y-m-d', strtotime("-$days days", strtotime($this->today)));
        $att = array_filter($this->D['attendances'] ?? [], fn($a) => (int) $a['user_id'] === $uid && $a['date'] >= $from && $a['date'] <= $this->today);
        $present = array_filter($att, fn($a) => $a['status'] === 'present'); $attPct = count($att) ? count($present) / count($att) : 1; $lateDays = count(array_filter($present, fn($a) => (int) $a['late_minutes'] > 0)); $punct = count($present) ? 1 - $lateDays / count($present) : 1;
        $tasks = array_filter($this->D['tasks'] ?? [], fn($t) => in_array($uid, $t['assigned_to'] ?? [], true)); $recent = array_filter($tasks, fn($t) => ($t['due_date'] ?? '') >= $from || $t['status'] !== 'done'); $done = array_filter($recent, fn($t) => $t['status'] === 'done'); $onTime = array_filter($done, fn($t) => !$t['completed_at'] || $t['completed_at'] <= $t['due_date']); $overdue = array_filter($recent, fn($t) => $t['status'] !== 'done' && ($t['due_date'] ?? '9999') < $this->today);
        $delivery = count($done) ? count($onTime) / count($done) : (count($recent) ? 0.6 : null);
        $leads = array_filter($this->D['leads'] ?? [], fn($l) => (int) ($l['assigned_to'] ?? 0) === $uid); $won = count(array_filter($leads, fn($l) => $l['status'] === 'won')); $lost = count(array_filter($leads, fn($l) => $l['status'] === 'lost')); $salesRate = ($won + $lost) ? $won / ($won + $lost) : null;
        $isSales = stripos((string) $e['designation'], 'sales') !== false || stripos((string) $e['department'], 'sales') !== false;
        $perf = ($isSales && $salesRate !== null) ? $salesRate : ($delivery ?? 0.7);
        $score = (int) round($attPct * 30 + $punct * 20 + $perf * 35 + min(1, (count($done) + $won) / 6) * 15);
        $grade = $score >= 85 ? 'A' : ($score >= 70 ? 'B' : ($score >= 55 ? 'C' : 'D'));
        $strengths = []; $concerns = [];
        if ($attPct >= 0.95) $strengths[] = 'attendance ' . round($attPct * 100) . '%'; elseif ($attPct < 0.85) $concerns[] = 'attendance only ' . round($attPct * 100) . '%';
        if ($punct >= 0.9) $strengths[] = 'punctual'; elseif ($punct < 0.7) $concerns[] = "late on $lateDays of " . count($present) . ' days';
        if ($isSales && $salesRate !== null) { $m = 'closes ' . round($salesRate * 100) . "% of decided leads ({$won}W/{$lost}L)"; if ($salesRate >= 0.5) $strengths[] = $m; else $concerns[] = $m; }
        if ($delivery !== null && count($done)) { $m = count($onTime) . ' of ' . count($done) . ' tasks on time'; if ($delivery >= 0.75) $strengths[] = $m; else $concerns[] = $m; }
        if (count($overdue)) $concerns[] = count($overdue) . ' task(s) overdue now';
        return ['employee' => ['id' => $e['id'], 'name' => $e['name'], 'designation' => $e['designation'], 'department' => $e['department'], 'company' => $this->coName($e['company_id']), 'joined' => $e['joining_date'], 'salary' => (float) $e['salary']], 'days' => $days, 'score' => $score, 'grade' => $grade, 'attendance_pct' => (int) round($attPct * 100), 'punctuality_pct' => (int) round($punct * 100), 'late_days' => $lateDays, 'tasks_done' => count($done), 'tasks_on_time' => count($onTime), 'tasks_overdue' => count($overdue), 'open_tasks' => count($recent) - count($done), 'leads_won' => $won, 'leads_lost' => $lost, 'strengths' => $strengths, 'concerns' => $concerns, 'narrative' => sprintf('%s (%s, %s) scores %d/100 — grade %s. %s %s', $e['name'], $e['designation'], $this->coName($e['company_id']), $score, $grade, $strengths ? 'Strengths: ' . implode(', ', $strengths) . '.' : '', $concerns ? 'Watch: ' . implode('; ', $concerns) . '.' : "No concerns in the last $days days.")];
    }
    public function pendingLeaves(): array
    {
        $rows = array_values(array_filter($this->rows('leaves'), fn($l) => $l['status'] === 'pending'));
        return array_map(fn($l) => ['id' => $l['id'], 'name' => $this->empName($l['user_id']), 'type' => $l['leave_type'], 'days' => (int) $l['days'], 'from' => $l['start_date'], 'to' => $l['end_date'], 'reason' => $l['reason']], $rows);
    }

    // ---------- CRM ----------
    public function pipeline(): array
    {
        $OPEN = ['new', 'contacted', 'qualified', 'proposal_sent', 'negotiation']; $leads = $this->rows('leads'); $open = array_filter($leads, fn($l) => in_array($l['status'], $OPEN, true));
        $won = count(array_filter($leads, fn($l) => $l['status'] === 'won')); $lost = count(array_filter($leads, fn($l) => $l['status'] === 'lost'));
        $stages = []; foreach (array_merge($OPEN, ['won', 'lost']) as $s) { $a = array_filter($leads, fn($l) => $l['status'] === $s); $stages[] = ['stage' => $s, 'count' => count($a), 'value' => round(self::sum($a, 'value'))]; }
        $stale = []; foreach ($open as $l) { $last = $l['last_followup_at'] ?: $l['created_at']; $idle = $last ? $this->days((string) $last, $this->today) : 0; $od = ($l['next_followup_at'] && $l['next_followup_at'] < $this->today) ? $this->days((string) $l['next_followup_at'], $this->today) : 0; if ($idle >= 10 || $od > 0) $stale[] = ['id' => $l['id'], 'name' => $l['name'], 'type' => $l['lead_type'], 'owner' => $l['assigned_name'] ?? $this->empName($l['assigned_to']), 'idle_days' => $idle, 'followup_overdue_days' => $od, 'value' => (float) ($l['value'] ?? 0), 'company' => $this->coName($l['company_id'])]; }
        usort($stale, fn($a, $b) => ($b['idle_days'] + $b['followup_overdue_days']) <=> ($a['idle_days'] + $a['followup_overdue_days']));
        $today = array_values(array_filter($open, fn($l) => ($l['next_followup_at'] ?? '') === $this->today));
        return ['total' => count($leads), 'open' => count($open), 'open_value' => round(self::sum($open, 'value')), 'won' => $won, 'lost' => $lost, 'conversion_pct' => ($won + $lost) ? (int) round($won / ($won + $lost) * 100) : null, 'stages' => $stages, 'stale' => array_slice($stale, 0, 15), 'stale_count' => count($stale), 'followups_today' => count($today)];
    }

    // ---------- operations ----------
    public function tasks(): array
    {
        $all = $this->rows('tasks'); $open = array_filter($all, fn($t) => $t['status'] !== 'done');
        $overdue = array_values(array_filter($open, fn($t) => ($t['due_date'] ?? '9999') < $this->today)); foreach ($overdue as &$t) { $t['days_overdue'] = $this->days((string) $t['due_date'], $this->today); $t['assignees'] = implode(', ', array_map(fn($u) => $this->empName($u), $t['assigned_to'] ?? [])); } unset($t); usort($overdue, fn($a, $b) => $b['days_overdue'] <=> $a['days_overdue']);
        $load = []; foreach ($open as $t) foreach ($t['assigned_to'] ?? [] as $u) { $load[$u] ??= ['id' => $u, 'name' => $this->empName($u), 'open' => 0, 'overdue' => 0]; $load[$u]['open']++; if (($t['due_date'] ?? '9999') < $this->today) $load[$u]['overdue']++; } $load = array_values($load); usort($load, fn($a, $b) => $b['overdue'] <=> $a['overdue'] ?: $b['open'] <=> $a['open']);
        $done7 = count(array_filter($all, fn($t) => $t['status'] === 'done' && $t['completed_at'] && $this->days(substr((string) $t['completed_at'], 0, 10), $this->today) <= 7));
        return ['open' => count($open), 'overdue' => count($overdue), 'overdue_high' => count(array_filter($overdue, fn($t) => $t['priority'] === 'high')), 'closed_last_7_days' => $done7, 'overdue_list' => array_slice(array_map(fn($t) => ['title' => $t['title'], 'project' => $t['project'], 'assignees' => $t['assignees'], 'days_overdue' => $t['days_overdue'], 'priority' => $t['priority']], $overdue), 0, 20), 'load' => array_slice($load, 0, 10), 'overloaded' => array_values(array_filter($load, fn($r) => $r['open'] >= 6 || $r['overdue'] >= 3))];
    }
    public function projects(): array
    {
        $out = []; foreach ($this->rows('projects') as $p) { if (in_array($p['status'], ['completed', 'cancelled'], true)) continue; $total = max(1, $this->days((string) $p['start_date'], (string) $p['end_date'])); $el = min(1, max(0, $this->days((string) $p['start_date'], $this->today) / $total)); $gap = (int) round($el * 100) - (int) ($p['progress'] ?? 0); $bp = $p['budget'] ? (int) round(($p['spent'] ?? 0) / $p['budget'] * 100) : null; $late = ($p['end_date'] ?? '9999') < $this->today; $risk = ($late ? 3 : 0) + ($gap > 25 ? 2 : ($gap > 12 ? 1 : 0)) + ($bp !== null && $bp > 100 ? 2 : 0) + ($p['status'] === 'on_hold' ? 1 : 0); $out[] = ['id' => $p['id'], 'name' => $p['project_name'], 'company' => $this->coName($p['company_id']), 'status' => $p['status'], 'progress' => (int) ($p['progress'] ?? 0), 'elapsed_pct' => (int) round($el * 100), 'budget' => (float) $p['budget'], 'budget_pct' => $bp, 'end_date' => $p['end_date'], 'days_left' => $this->days($this->today, (string) $p['end_date']), 'late' => $late, 'risk' => $risk, 'risk_label' => $risk >= 4 ? 'critical' : ($risk >= 2 ? 'at risk' : 'on track'), 'manager' => $p['manager'] ?? null]; }
        $atRisk = array_values(array_filter($out, fn($p) => $p['risk'] >= 2)); usort($atRisk, fn($a, $b) => $b['risk'] <=> $a['risk']);
        return ['active' => count($out), 'at_risk' => $atRisk, 'all' => $out];
    }

    // ---------- the boss's views ----------
    public function kpis(): array
    {
        $mk = substr($this->today, 0, 7); $cash = $this->cash(); $ar = $this->schedules('receive'); $ap = $this->schedules('pay'); $pl = $this->profitAndLoss($mk . '-01', $this->today); $tr = $this->revenueTrend(); $lastMk = date('Y-m', strtotime('-1 month', strtotime($mk . '-01'))); $plLast = $this->profitAndLoss($lastMk . '-01', $lastMk . '-31'); $td = $this->attendanceToday(); $pr = $this->payroll(); $pipe = $this->pipeline(); $tk = $this->tasks(); $pj = $this->projects();
        $emps = array_filter($this->rows('employees'), fn($e) => ($e['status'] ?? 'active') === 'active');
        return ['cash' => $cash['total'], 'receivables' => $ar['total'], 'receivables_overdue' => $ar['overdue_total'], 'payables' => $ap['total'], 'payables_overdue' => $ap['overdue_total'], 'revenue_mtd' => $pl['income'], 'revenue_vs_prev_pct' => $tr['vs_prev_pct'], 'opex_mtd' => $pl['opex'], 'net_profit_mtd' => $pl['net_profit'], 'net_profit_last_month' => $plLast['net_profit'], 'last_month' => $lastMk, 'headcount' => count($emps), 'monthly_payroll' => round(self::sum($emps, 'salary')), 'present_pct' => $td['present_pct'], 'absent_today' => $td['absent'], 'late_today' => $td['late'], 'payroll_month' => $pr['month'], 'payroll_net' => $pr['net'], 'payroll_pending' => $pr['pending_count'], 'pipeline_open' => $pipe['open'], 'pipeline_value' => $pipe['open_value'], 'conversion_pct' => $pipe['conversion_pct'], 'tasks_overdue' => $tk['overdue'], 'tasks_open' => $tk['open'], 'projects_active' => $pj['active'], 'projects_at_risk' => count($pj['at_risk'])];
    }
    public function approvals(): array
    {
        $items = []; $pe = $this->pendingExpenses(); foreach ($pe['rows'] as $e) $items[] = ['kind' => 'expense', 'id' => $e['id'], 'title' => $e['title'] . ' — ' . $e['category'], 'who' => $e['by'], 'company' => $e['company'], 'amount' => $e['amount']];
        foreach ($this->pendingLeaves() as $l) $items[] = ['kind' => 'leave', 'id' => $l['id'], 'title' => "{$l['type']} leave · {$l['days']} day(s) from {$l['from']}", 'who' => $l['name'], 'company' => '', 'amount' => null];
        foreach ($this->rows('advance_salaries') as $a) if (strtolower((string) $a['status']) === 'pending') $items[] = ['kind' => 'advance', 'id' => $a['id'], 'title' => 'Salary advance for ' . $a['month'], 'who' => $this->empName($a['user_id']), 'company' => '', 'amount' => (float) $a['amount']];
        foreach ($this->rows('employee_requests') as $r) if (in_array($r['status'], ['pending', 'under_review'], true)) $items[] = ['kind' => 'request', 'id' => $r['id'], 'title' => $r['request_type'] . ' (' . $r['category'] . ')', 'who' => $this->empName($r['user_id']), 'company' => '', 'amount' => (float) $r['amount']];
        $pr = $this->payroll(); if ($pr['pending_count']) array_unshift($items, ['kind' => 'payroll', 'id' => $pr['month'], 'title' => "Payroll run {$pr['month']} — {$pr['pending_count']} payslips", 'who' => 'Accounts', 'company' => '', 'amount' => $pr['pending_net']]);
        return ['count' => count($items), 'amount' => round(self::sum($items, 'amount')), 'items' => $items];
    }
    /** ranked decisions across the four layers */
    public function decisions(): array
    {
        $out = []; $ar = $this->schedules('receive'); $ap = $this->schedules('pay'); $cash = $this->cash(); $bud = $this->expensesVsBudget(); $pe = $this->pendingExpenses(); $tr = $this->revenueTrend(); $td = $this->attendanceToday(); $pt = $this->latePatterns(); $lv = $this->pendingLeaves(); $pr = $this->payroll(); $pipe = $this->pipeline(); $tk = $this->tasks(); $pj = $this->projects();
        $push = function (string $layer, int $sev, string $title, array $why, string $rec, float $amount = 0) use (&$out) { $out[] = ['layer' => $layer, 'severity' => $sev, 'severity_label' => [5 => 'critical', 4 => 'high', 3 => 'medium', 2 => 'low', 1 => 'info'][$sev], 'title' => $title, 'why' => $why, 'recommend' => $rec, 'amount' => round($amount)]; };
        if ($ar['overdue_total'] > 0) { $top = array_slice(array_filter($ar['by_party'], fn($p) => $p['overdue'] > 0), 0, 3); $push('finance', $ar['overdue_total'] > $cash['total'] * 0.25 ? 5 : 4, self::bdtk($ar['overdue_total']) . " receivable is overdue across {$ar['overdue_count']} dues", array_map(fn($p) => "{$p['party_name']} — " . self::bdtk($p['overdue']) . " overdue, oldest {$p['oldest']}d", $top), 'Call ' . ($top[0]['party_name'] ?? 'the top debtor') . ' today; written reminders to the next two; the 30+ day buckets alone recover ' . self::bdtk($ar['buckets'][2]['amount'] + $ar['buckets'][3]['amount'] + $ar['buckets'][4]['amount']) . '.', $ar['overdue_total']); }
        if ($ap['overdue_total'] > 0) { $emp = array_filter($ap['overdue'], fn($p) => $p['party_type'] === 'employee'); $push('finance', $emp ? 5 : 4, self::bdtk($ap['overdue_total']) . " payable is past due ({$ap['overdue_count']} items)", [$emp ? count($emp) . ' salary payments are late' : 'no salary is late', 'cash available ' . self::bdtk($cash['total'])], $emp ? 'Release the late salaries first, then suppliers older than 30 days.' : 'Settle suppliers older than 30 days this week to protect credit terms.', $ap['overdue_total']); }
        if ($ap['due_in_7_days'] > $cash['total'] * 0.6 && $ap['due_in_7_days'] > 0) $push('finance', 4, 'Payables due in 7 days (' . self::bdtk($ap['due_in_7_days']) . ') take most of the cash', ['cash ' . self::bdtk($cash['total'])], 'Sequence payments: salaries and high-priority suppliers first; chase receivables due now.', $ap['due_in_7_days']);
        foreach ($cash['low'] as $b) $push('finance', $b['balance'] < 0 ? 5 : 2, "{$b['name']} ({$b['company']}) is " . ($b['balance'] < 0 ? 'overdrawn' : 'low') . ': ' . self::bdt($b['balance']), [], "Transfer a float into {$b['name']} before the next scheduled payment.", $b['balance']);
        foreach ($bud['over'] as $r) $push('finance', $r['pct'] >= 150 ? 4 : 3, "{$r['category']} is {$r['pct']}% of budget this month (" . self::bdtk($r['spent']) . ' of ' . self::bdtk($r['budget']) . ')', ["{$r['count']} expense lines"], $r['pending'] ? "Hold the pending {$r['category']} approvals until the overrun is justified." : "Freeze discretionary {$r['category']} spend for the rest of the month.", $r['spent'] - $r['budget']);
        if ($pe['count']) $push('finance', $pe['total'] > 200000 ? 3 : 2, "{$pe['count']} expenses (" . self::bdtk($pe['total']) . ') are waiting for approval', [], 'Approve the routine ones in one pass; question anything above budget.', $pe['total']);
        if ($tr['vs_prev_pct'] <= -15) $push('finance', 3, "Revenue is tracking " . abs($tr['vs_prev_pct']) . '% below last month', ['run-rate ' . self::bdtk($tr['run_rate']) . ' vs ' . self::bdtk($tr['prev']['income'])], 'Check the pipeline: which won deals have not been invoiced yet?', $tr['prev']['income'] - $tr['run_rate']);
        if (!$td['weekend'] && $td['absent']) $push('people', $td['absent'] >= 5 ? 3 : 2, "{$td['absent']} absent today without leave" . ($td['late'] ? ", {$td['late']} came late" : ''), ["present {$td['present']}/{$td['total']} ({$td['present_pct']}%)"], $td['absent'] >= 3 ? 'HR to call the absentees before noon.' : 'HR to log the reason for each.');
        if ($pt['chronic_late']) $push('people', 3, count($pt['chronic_late']) . " employees are late on 30%+ of days (last {$pt['days']} days)", array_map(fn($r) => "{$r['name']} — late {$r['late_days']}/{$r['present']} days, {$r['late_minutes']} min", array_slice($pt['chronic_late'], 0, 4)), 'Written warning to the top two; late deduction only bites at 120 min/month.');
        if ($pt['chronic_absent']) $push('people', 3, count($pt['chronic_absent']) . ' employees below 85% attendance with 3+ absences', array_map(fn($r) => "{$r['name']} — {$r['absent']} absent, {$r['attendance_pct']}%", array_slice($pt['chronic_absent'], 0, 4)), 'Line managers hold a one-to-one this week.');
        if ($lv) $push('people', 2, count($lv) . ' leave request(s) awaiting approval', array_map(fn($l) => "{$l['name']} — {$l['type']} {$l['days']}d from {$l['from']}", array_slice($lv, 0, 4)), 'Approve unless a project deadline collides.');
        if ($pr['pending_count']) $push('people', (int) date('j') >= 5 ? 4 : 2, "{$pr['pending_count']} payslips for {$pr['month']} are unpaid — " . self::bdtk($pr['pending_net']), ['net payroll ' . self::bdtk($pr['net']) . ' vs ' . self::bdtk($pr['prev_net']) . ' last month'], 'Release the payroll — late pay is the fastest way to lose good people.', $pr['pending_net']);
        if ($pipe['stale_count']) $push('crm', 3, "{$pipe['stale_count']} open leads have gone cold", array_map(fn($l) => "{$l['name']} ({$l['type']}) — {$l['idle_days']}d silent, {$l['owner']}", array_slice($pipe['stale'], 0, 4)), 'Give the owners 48 hours to touch each one, then move the rest to lost.', $pipe['open_value'] * 0.3);
        if ($pipe['followups_today']) $push('crm', 2, "{$pipe['followups_today']} follow-ups due today", [], 'Sales team clears them before lunch.');
        if ($pj['at_risk']) { $p = $pj['at_risk'][0]; $push('ops', $p['risk_label'] === 'critical' ? 4 : 3, count($pj['at_risk']) . " project(s) at risk — worst: {$p['name']}", array_map(fn($x) => "{$x['name']} ({$x['company']}) — {$x['progress']}% done at {$x['elapsed_pct']}% of time" . ($x['budget_pct'] !== null ? ", budget {$x['budget_pct']}%" : ''), array_slice($pj['at_risk'], 0, 4)), "Ask the manager for a recovery plan on {$p['name']} by tomorrow: re-baseline or add capacity, and stop new scope.", $p['budget']); }
        if ($tk['overdue']) $push('ops', $tk['overdue_high'] >= 3 ? 4 : ($tk['overdue'] >= 10 ? 3 : 2), "{$tk['overdue']} tasks are overdue ({$tk['overdue_high']} high priority)", array_map(fn($t) => "{$t['title']} — {$t['project']} · {$t['assignees']} · {$t['days_overdue']}d late", array_slice($tk['overdue_list'], 0, 4)), $tk['overloaded'] ? "{$tk['overloaded'][0]['name']} is overloaded — move two tasks to someone with capacity." : 'Ask each owner for a new date today.');
        foreach (self::decisionPlugins() as $fn) { try { foreach ((array) $fn($this->D, $this->co, $this) as $d) if (is_array($d) && isset($d['title'])) $out[] = $d + ['layer' => 'ops', 'severity' => 2, 'severity_label' => 'low', 'why' => [], 'recommend' => '', 'amount' => 0]; } catch (Throwable $e) { Log::warn('decision plug-in failed', ['error' => $e->getMessage()]); } }
        usort($out, fn($a, $b) => $b['severity'] <=> $a['severity'] ?: $b['amount'] <=> $a['amount']);
        return $out;
    }
    /** plug-in decision providers: every server/lib/decisions/*.php returns fn(array $D, ?int $company, Analytics $A): array of decisions */
    private static ?array $decPlugins = null;
    public static function decisionPlugins(): array
    {
        if (self::$decPlugins !== null) return self::$decPlugins;
        self::$decPlugins = [];
        foreach (glob(EON_ROOT . '/lib/decisions/*.php') ?: [] as $f) { try { $fn = require $f; if (is_callable($fn)) self::$decPlugins[basename($f, '.php')] = $fn; } catch (Throwable $e) { Log::warn('decision plug-in load failed: ' . $f, ['error' => $e->getMessage()]); } }
        return self::$decPlugins;
    }
    public function today(): string { return $this->today; }
    public function dataset(): array { return $this->D; }
    public function company(): ?int { return $this->co; }
    public function companyName(int|string|null $id): string { return $this->coName($id); }
    public function employeeName(int|string|null $id): string { return $this->empName($id); }
    public function brief(): array
    {
        $k = $this->kpis(); $dec = $this->decisions(); $ap = $this->approvals(); $td = $this->attendanceToday();
        $who = 'Boss'; foreach (preg_split('/\s+/', trim((string) Config::get('boss.name', 'Boss'))) as $part) { if ($part !== '' && !preg_match('/^(md\.?|mohammad|muhammad|mohammed|mst\.?|mrs?\.?|ms\.?|dr\.?)$/i', $part)) { $who = $part; break; } }
        $h = (int) date('G'); $greet = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
        $lines = [];
        $sb = $this->salesBooked(substr($this->today, 0, 7) . '-01', $this->today);
        $lines[] = sprintf('%s, %s. %s. Cash stands at %s; revenue this month %s, tracking %s %d%% on last month, which closed at a net %s of %s.', $greet, $who, date('l j F', strtotime($this->today)), self::bdtk($k['cash']), self::bdtk($k['revenue_mtd']), $k['revenue_vs_prev_pct'] >= 0 ? 'up' : 'down', abs($k['revenue_vs_prev_pct']), $k['net_profit_last_month'] >= 0 ? 'profit' : 'loss', self::bdtk(abs($k['net_profit_last_month'])));
        // the desks invoice ahead of the ledger — lead with the business, then the books
        if ($sb['invoiced'] > 0 && $sb['invoiced'] - $k['revenue_mtd'] > 1000) $lines[] = sprintf('The desks invoiced %s this month across %d invoices — %s of that is not journalised yet, so the books show only %s.', self::bdtk((float) $sb['invoiced']), (int) $sb['invoices'], self::bdtk((float) $sb['invoiced'] - (float) $k['revenue_mtd']), self::bdtk((float) $k['revenue_mtd']));
        if ($k['receivables_overdue'] || $k['payables_overdue']) $lines[] = sprintf('Overdue: %s to collect, %s to pay.', self::bdtk($k['receivables_overdue']), self::bdtk($k['payables_overdue']));
        if (!$td['weekend']) {
            $lines[] = !empty($td['no_data_yet'])
                ? sprintf('No attendance is in yet today (last recorded %s).', $td['last_recorded_date'])
                : sprintf('%d of %d tracked staff are in today, %d absent, %d late.', $td['present'], $td['tracked'] ?: $td['total'], $td['absent'], $td['late']);
        }
        $crit = array_values(array_filter($dec, fn($d) => $d['severity'] >= 4));
        if ($crit) $lines[] = count($crit) . ' thing' . (count($crit) > 1 ? 's' : '') . ' need' . (count($crit) > 1 ? '' : 's') . ' you today: ' . implode('; ', array_map(fn($d) => $d['title'], array_slice($crit, 0, 3))) . '.';
        else $lines[] = 'Nothing critical this morning.';
        if ($ap['count']) $lines[] = "{$ap['count']} approvals are waiting on you" . ($ap['amount'] ? ' — ' . self::bdtk($ap['amount']) . ' in total' : '') . '.';
        if ($dec) $lines[] = 'If you do one thing: ' . $dec[0]['recommend'];
        return ['date' => $this->today, 'speak' => implode(' ', $lines), 'lines' => $lines, 'kpis' => $k, 'decisions' => $dec, 'critical' => $crit, 'approvals' => $ap];
    }
}
