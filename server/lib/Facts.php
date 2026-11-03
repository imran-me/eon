<?php
/* ============================================================
   EON · Facts — the ERP, boiled down to a sheet Gemini can reason over.

   Gemini is allowed to think, compare and explain, but only with numbers
   from here. Every figure is computed from the dataset the offline brain
   already reads (journals, expenses, party transactions, sales, purchases,
   flights, visas, payroll, banks, schedules), rounded to whole taka, and
   labelled. If a question needs a number that is not on the sheet, Gemini
   must say so rather than estimate.
   ============================================================ */
declare(strict_types=1);

final class Facts
{
    public static function sheet(array $D): array
    {
        $co = []; foreach ($D['companies'] ?? [] as $c) $co[$c['id']] = (string) ($c['short_name'] ?: $c['name']);
        $cname = fn($id) => $co[$id] ?? 'Group';
        $r = fn($v) => (int) round((float) $v);
        $ym = fn($d) => substr((string) $d, 0, 7);
        $months = []; $t = new DateTimeImmutable('first day of this month');
        for ($i = 0; $i < 6; $i++) $months[] = $t->modify("-$i month")->format('Y-m');
        $inM = fn($d) => in_array($ym($d), $months, true);
        $S = ['today' => date('Y-m-d'), 'months_covered' => $months, 'companies' => array_values($co)];

        // banks and wallets
        foreach ($D['banks'] ?? [] as $b) $S['bank_balances'][] = [$b['name'], $cname($b['company_id'] ?? null), $r($b['balance'])];

        // P&L by company and month from the journal: income and expense accounts by their type
        $type = []; foreach ($D['accounts'] ?? [] as $a) $type[$a['id']] = strtolower((string) $a['type']); $codeType = [];
        foreach ($D['accounts'] ?? [] as $a) $codeType[(string) $a['code']] = strtolower((string) $a['type']);
        $pl = [];
        foreach ($D['journal_entries'] ?? [] as $j) {
            if (!$inM($j['date'])) continue;
            $k = $cname($j['company_id'] ?? null) . ' ' . $ym($j['date']);
            foreach ((array) ($j['items'] ?? []) as $it) {
                $ty = $type[$it['account_id'] ?? 0] ?? ($codeType[(string) ($it['account_code'] ?? '')] ?? '');
                $dr = (float) ($it['debit'] ?? 0); $cr = (float) ($it['credit'] ?? 0);
                if (str_contains($ty, 'income') || str_contains($ty, 'revenue')) $pl[$k]['income'] = ($pl[$k]['income'] ?? 0) + $cr - $dr;
                elseif (str_contains($ty, 'expense') || str_contains($ty, 'cost')) $pl[$k]['expense'] = ($pl[$k]['expense'] ?? 0) + $dr - $cr;
            }
        }
        foreach ($pl as $k => $v) $S['profit_loss_by_company_month'][$k] = ['income' => $r($v['income'] ?? 0), 'expense' => $r($v['expense'] ?? 0), 'profit' => $r(($v['income'] ?? 0) - ($v['expense'] ?? 0))];

        // expenses: by category and month, pending approvals
        $ex = []; $pend = [0, 0];
        foreach ($D['expenses'] ?? [] as $e) {
            if (strtolower((string) $e['approval_status']) === 'pending') { $pend[0]++; $pend[1] += (float) $e['amount']; }
            if (!$inM($e['expense_date'])) continue;
            $ex[$ym($e['expense_date'])][(string) ($e['category'] ?: 'Other')] = ($ex[$ym($e['expense_date'])][(string) ($e['category'] ?: 'Other')] ?? 0) + (float) $e['amount'];
        }
        foreach ($ex as $m => $cats) { arsort($cats); $S['expenses_by_month_category'][$m] = array_map($r, array_slice($cats, 0, 12, true)) + ['TOTAL' => $r(array_sum($cats))]; }
        $S['expenses_pending_approval'] = ['count' => $pend[0], 'amount' => $r($pend[1])];

        // agents and clients (the ERP's "customer" parties): debit = what we billed, credit = what they paid.
        // Suppliers are left to the purchase dues below — their party ledger signs do not match the purchases.
        $cb = [];
        foreach ($D['party_transactions'] ?? [] as $p) {
            if ($p['party_type'] !== 'customer' || preg_match('/super ?admin|^admin$/i', (string) $p['party_name'])) continue;   // a system account, not a party
            $k = (string) $p['party_name']; $cb[$k] = ($cb[$k] ?? 0) + (float) $p['debit'] - (float) $p['credit'];
        }
        $owe = array_filter($cb, fn($v) => $v > 0.5); arsort($owe);
        $adv = array_filter($cb, fn($v) => $v < -0.5); asort($adv);
        $S['agents_clients_who_owe_us'] = array_map($r, array_slice($owe, 0, 30, true));
        $S['agents_clients_who_paid_in_advance'] = array_map(fn($v) => $r(-$v), array_slice($adv, 0, 15, true));
        $S['agent_note'] = 'agents/clients are the ERP customer parties (CYP, KUL… tags are agents). Vendors/suppliers and what we owe them are in ticket_vendors and contract_flights (vendor due).';

        // ticketing: sales by month, by client/agent; profit per client from sale vs purchase cost
        $cost = []; foreach ($D['ticket_purchases'] ?? [] as $p) $cost[$p['id']] = (float) ($p['total'] ?? 0);
        $byM = []; $byC = [];
        foreach ($D['ticket_sales'] ?? [] as $s) { $m = $ym($s['date']); if (!$inM($s['date'])) continue;
            $byM[$m]['invoices'] = ($byM[$m]['invoices'] ?? 0) + 1; $byM[$m]['sales'] = ($byM[$m]['sales'] ?? 0) + (float) $s['total']; $byM[$m]['due'] = ($byM[$m]['due'] ?? 0) + (float) $s['due_amount']; }
        foreach ($D['ticket_index'] ?? [] as $t2) { if (!$t2['client'] || !$inM($t2['date'])) continue;
            $c = (string) $t2['client']; $byC[$c]['tickets'] = ($byC[$c]['tickets'] ?? 0) + 1; $byC[$c]['sales'] = ($byC[$c]['sales'] ?? 0) + (float) $t2['sale_price'];
            $byC[$c]['profit'] = ($byC[$c]['profit'] ?? 0) + (float) $t2['sale_price'] - ($cost[$t2['purchase_id']] ?? 0); }
        foreach ($byM as $m => $v) $S['ticket_sales_by_month'][$m] = array_map($r, $v);
        uasort($byC, fn($a, $b) => $b['sales'] <=> $a['sales']);
        $S['ticket_by_client_agent'] = array_map(fn($v) => array_map($r, $v), array_slice($byC, 0, 25, true));
        $st = []; foreach ($D['ticket_index'] ?? [] as $t2) if ($inM($t2['date'])) { $k = $ym($t2['date']) . ' ' . ($t2['status'] ?: 'unknown'); $st[$k] = ($st[$k] ?? 0) + 1; }
        ksort($st); $S['tickets_by_month_status'] = $st;
        $vd = []; foreach ($D['ticket_purchases'] ?? [] as $p) { $v = (string) ($p['vendor'] ?: '—'); $vd[$v]['bought'] = ($vd[$v]['bought'] ?? 0) + (float) $p['total']; $vd[$v]['due'] = ($vd[$v]['due'] ?? 0) + (float) ($p['due_amount'] ?? 0); }
        uasort($vd, fn($a, $b) => $b['due'] <=> $a['due']);
        $S['ticket_vendors'] = array_map(fn($v) => array_map($r, $v), array_slice($vd, 0, 15, true));

        // contract flights and their passengers
        foreach ($D['contract_flights'] ?? [] as $f) $S['contract_flights'][] = [$f['flight_number'], substr((string) $f['departure_at'], 0, 16), $f['status'], 'seats ' . (int) $f['seats_sold'] . '/' . (int) $f['total_seats'], 'revenue ' . $r($f['revenue']), 'cost ' . $r($f['cost_price']), 'vendor due ' . $r($f['due_amount']), (string) ($f['vendor'] ?? '')];
        $ag = []; foreach ($D['contract_flight_passengers'] ?? [] as $p) { $a = (string) ($p['agent'] ?: '—');
            $ag[$a]['passengers'] = ($ag[$a]['passengers'] ?? 0) + 1; $ag[$a]['charged'] = ($ag[$a]['charged'] ?? 0) + (float) $p['agent_price']; $ag[$a]['due'] = ($ag[$a]['due'] ?? 0) + (float) $p['agent_price'] - (float) ($p['agent_paid_amount'] ?? 0);
            $ag[$a]['stages'][$p['stage']] = ($ag[$a]['stages'][$p['stage']] ?? 0) + 1; }
        foreach ($ag as $a => $v) { $v['charged'] = $r($v['charged']); $v['due'] = $r($v['due']); $S['contract_flight_agents'][$a] = $v; }

        // money in: what agents and clients paid us, by month (their credits)
        $col = []; foreach ($D['party_transactions'] ?? [] as $p) if ($p['party_type'] === 'customer' && (float) $p['credit'] > 0 && $inM($p['date']) && !preg_match('/super ?admin/i', (string) $p['party_name'])) $col[$ym($p['date'])] = ($col[$ym($p['date'])] ?? 0) + (float) $p['credit'];
        ksort($col); $S['collections_by_month'] = array_map($r, $col);
        // tickets by airline and month; visa sales by month
        $al = []; foreach ($D['ticket_purchases'] ?? [] as $p) if ($inM($p['date'])) { $k = $ym($p['date']) . ' ' . ($p['airline'] ?: '—'); $al[$k] = ($al[$k] ?? 0) + 1; }
        arsort($al); $S['tickets_by_month_airline'] = array_slice($al, 0, 40, true);
        $vsm = []; foreach ($D['visa_sales'] ?? [] as $v) if ($inM($v['date'])) { $m = $ym($v['date']); $vsm[$m]['invoices'] = ($vsm[$m]['invoices'] ?? 0) + 1; $vsm[$m]['sales'] = ($vsm[$m]['sales'] ?? 0) + (float) $v['total']; $vsm[$m]['due'] = ($vsm[$m]['due'] ?? 0) + (float) $v['due_amount']; }
        foreach ($vsm as $m => $v) $S['visa_sales_by_month'][$m] = array_map($r, $v);
        // contract files opened, by month and in the last 7 days
        $cfm = []; $cf7 = 0; $d7 = date('Y-m-d', strtotime('-7 days'));
        foreach ($D['contract_files'] ?? [] as $f) { $d0 = (string) ($f['submit_date'] ?? ''); if ($d0 === '') continue; if ($inM($d0)) $cfm[$ym($d0)] = ($cfm[$ym($d0)] ?? 0) + 1; if ($d0 >= $d7) $cf7++; }
        ksort($cfm); $S['contract_files_opened_by_month'] = $cfm; $S['contract_files_opened_last_7_days'] = $cf7;

        // visas and contract files
        $vs = []; foreach ($D['visa_processes'] ?? [] as $v) { $k = ($v['country'] ?: '—') . ' / ' . ($v['status'] ?: '—'); $vs[$k] = ($vs[$k] ?? 0) + 1; }
        $S['visa_processes_by_country_status'] = $vs;
        $cf = []; foreach ($D['contract_files'] ?? [] as $f) { $cf[(string) $f['status']] = ($cf[(string) $f['status']] ?? 0) + 1; } $S['contract_files_by_stage'] = $cf;

        // payroll and people
        $pr = []; foreach ($D['payroll'] ?? [] as $p) { $m = (string) $p['month_key']; if (!in_array($m, $months, true)) continue;
            $pr[$m]['slips'] = ($pr[$m]['slips'] ?? 0) + 1; $pr[$m]['gross'] = ($pr[$m]['gross'] ?? 0) + (float) $p['gross_salary']; $pr[$m]['net'] = ($pr[$m]['net'] ?? 0) + (float) $p['net_salary'];
            if (strtolower((string) $p['status']) !== 'paid') $pr[$m]['unpaid_net'] = ($pr[$m]['unpaid_net'] ?? 0) + (float) $p['net_salary']; }
        foreach ($pr as $m => $v) $S['payroll_by_month'][$m] = array_map($r, $v);
        $dep = []; foreach ($D['employees'] ?? [] as $e) if (($e['status'] ?? '') === 'active') $dep[(string) ($e['department'] ?: '—')] = ($dep[(string) ($e['department'] ?: '—')] ?? 0) + 1;
        $S['active_staff_by_department'] = $dep;

        // money due in and out on the payment calendar
        $now = date('Y-m-d'); $wk = date('Y-m-d', strtotime('+7 days')); $sc = ['pay_overdue' => 0, 'pay_next_7_days' => 0, 'receive_overdue' => 0, 'receive_next_7_days' => 0];
        foreach ($D['payment_schedules'] ?? [] as $p) { if (in_array(strtolower((string) $p['status']), ['paid', 'cancelled'], true)) continue;
            $left = (float) $p['amount'] - (float) $p['paid_amount']; $dir = $p['type'] === 'pay' ? 'pay' : 'receive';
            if ($p['scheduled_date'] < $now) $sc[$dir . '_overdue'] += $left; elseif ($p['scheduled_date'] <= $wk) $sc[$dir . '_next_7_days'] += $left; }
        $S['payment_calendar'] = array_map($r, $sc);
        return $S;
    }
}
