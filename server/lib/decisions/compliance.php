<?php
/* ============================================================
   EON · decision plug-in — the Bangladeshi statutory calendar.

   The browser has the same calendar (domains/erp/plugins/compliance.js);
   this is the server half, so the morning brief and api/brief.php raise
   the same deadlines even when nobody has the page open.

   Dates below are the ordinary rules for a company group in Bangladesh.
   Where a rule varies by turnover, city corporation or the company's own
   year end, the item says so — EON flags the date, the accountant confirms it.

     VAT return (Mushak 9.1)     by the 15th of the following month      (VAT & SD Act 2012)
     VAT/TDS deposit             with the return, treasury challan
     TDS on salaries             deposited monthly                        (Income Tax Act 2023)
     Wages                       within 7 working days of the wage period (Labour Act 2006, s.123)
     Advance income tax          15 Sep · 15 Dec · 15 Mar · 15 Jun        (quarterly instalments)
     Company income tax return   "Tax Day" — 15th day of the 7th month after year end
     Trade licence renewal       by 30 June each fiscal year              (city corporation)
     Fire licence renewal        annually
     RJSC annual return          within 21 days of the AGM                (Companies Act 1994)

   Returns decisions in the same shape as Analytics::decisions().
   ============================================================ */
declare(strict_types=1);

return function (array $D, ?int $company, Analytics $A): array {
    $today = $A->today();
    $t = strtotime($today . ' 00:00:00');
    if ($t === false) return [];
    $y = (int) date('Y', $t);
    $m = (int) date('n', $t);

    $mk = fn(int $yy, int $mm, int $dd) => date('Y-m-d', mktime(0, 0, 0, $mm, $dd, $yy));
    $prevMonthName = date('F', mktime(0, 0, 0, $m - 1, 1, $y));

    /* the fixed calendar for this month and the next 45 days */
    $items = [
        ['name' => "VAT return — Mushak 9.1 for $prevMonthName", 'due' => $mk($y, $m, 15), 'basis' => 'VAT & SD Act 2012 — filed and paid by the 15th of the following month', 'tag' => 'vat'],
        ['name' => "VAT return — Mushak 9.1 for " . date('F', mktime(0, 0, 0, $m, 1, $y)), 'due' => $mk($y, $m + 1, 15), 'basis' => 'VAT & SD Act 2012 — filed and paid by the 15th of the following month', 'tag' => 'vat'],
        ['name' => "TDS on salaries — $prevMonthName deposit", 'due' => $mk($y, $m, 15), 'basis' => 'Income Tax Act 2023 — tax deducted at source is deposited monthly by treasury challan', 'tag' => 'tds'],
        ['name' => "Wages for $prevMonthName paid to all workers", 'due' => $mk($y, $m, 7), 'basis' => 'Bangladesh Labour Act 2006, s.123 — wages within 7 working days of the wage period ending', 'tag' => 'payroll'],
    ];
    foreach ([[9, 15], [12, 15], [3, 15], [6, 15]] as [$qm, $qd]) {
        $due = $mk($y, $qm, $qd);
        if ($due < $today) $due = $mk($y + 1, $qm, $qd);
        $items[] = ['name' => 'Advance income tax instalment', 'due' => $due, 'basis' => 'Quarterly instalments on 15 September, 15 December, 15 March and 15 June', 'tag' => 'tax'];
    }
    $tradeDue = $mk($y, 6, 30); if ($tradeDue < $today) $tradeDue = $mk($y + 1, 6, 30);
    $items[] = ['name' => 'Trade licence renewal', 'due' => $tradeDue, 'basis' => 'City corporation — renewed each fiscal year by 30 June', 'tag' => 'licence'];
    $items[] = ['name' => 'Company income tax return (Tax Day)', 'due' => $mk($y + ($m > 12 ? 1 : 0), 12, 15), 'basis' => 'Income Tax Act 2023 — the 15th day of the seventh month after the income year ends; confirm the date against your own year end', 'tag' => 'tax'];

    /* what the ERP itself already knows about — office to-dos that look statutory */
    $todos = [];
    foreach (($D['office_todos'] ?? []) as $r) {
        if ($company !== null && isset($r['company_id']) && (int) $r['company_id'] !== $company) continue;
        if (($r['status'] ?? '') === 'done') continue;
        $title = (string) ($r['title'] ?? '');
        if (!preg_match('/vat|mushak|tax|licen[cs]e|insurance|rjsc|return|audit|fire|trade/i', $title)) continue;
        $todos[strtolower($title)] = $r['due_date'] ?? null;
    }

    $out = [];
    foreach ($items as $it) {
        $days = (int) floor((strtotime($it['due']) - $t) / 86400);
        if ($days > 45) continue;                      // only what is actually near
        $overdue = $days < 0;
        if ($days > 7 && !$overdue) continue;          // "coming up" is the panel's job, not a decision

        // the ERP may already track it — say so rather than nagging twice
        $tracked = null;
        foreach ($todos as $title => $due) {
            $words = preg_split('/[^a-z0-9]+/i', strtolower($it['name'])) ?: [];
            foreach ($words as $w) { if (strlen($w) >= 4 && str_contains($title, $w)) { $tracked = $title; break 2; } }
        }

        $out[] = [
            'layer' => 'ops',
            'severity' => $overdue ? 4 : 3,
            'severity_label' => $overdue ? 'high' : 'medium',
            'title' => $overdue
                ? sprintf('Compliance overdue: %s (was due %s, %dd ago)', $it['name'], $it['due'], abs($days))
                : sprintf('Compliance due in %d day%s: %s (%s)', $days, $days === 1 ? '' : 's', $it['name'], $it['due']),
            'why' => array_values(array_filter([
                $it['basis'],
                $tracked ? 'The ERP has a to-do for this: "' . $tracked . '"' : 'No office to-do covers this in the ERP.',
                'Late statutory filings carry interest and penalties, and a lapsed licence stops the trade.',
            ])),
            'recommend' => $overdue
                ? sprintf('File and pay %s today, then log the challan number against the entry.', $it['name'])
                : sprintf('Have accounts prepare %s before %s.', $it['name'], $it['due']),
            'amount' => 0,
            'tag' => 'compliance',
        ];
    }
    return $out;
};
