<?php
/* ============================================================
   EON · Ops — the questions a boss asks across the desk, answered
   straight from the books before the general answerer can mistake
   them for something else.

     "amar bkash e koto taka ache"      a named bank / wallet's balance
     "ei mase sobaike koto salary dite hobe"   this month's payroll still to pay
     "koyta flight baki ache"           flights not yet flown
     "koyta flight er taka due ache"    flights with money still to pay / collect

   Each handler fires only on its own subject AND its own question
   shape, so it never takes a sentence it does not fully understand.
   English, বাংলা and Banglish; a Banglish question is answered in
   Bangla, the way it was asked.
   ============================================================ */
declare(strict_types=1);

final class Ops
{
    private const BANGLISH = '/\b(ache|achhe|koto|kto|taka|dite|hobe|baki|koyta|koita|dewa|deya|hoyeche|hoise|hoyse|mase|mashe|kothay|dekhao|amar|amader|sobai|sobaike|shobai|ki|keno|kar|koro|niye|jao)\b/i';

    public static function answer(string $q, array $D, ?string $lang = null): ?array
    {
        $l = mb_strtolower(trim($q));
        // 'bn'/'en' exactly is an instruction (the Gemini planner re-asks in English for a Bangla speaker)
        $bn = $lang === 'bn' ? true : ($lang === 'en' ? false : ((bool) preg_match('/\p{Bengali}/u', $q) || preg_match_all(self::BANGLISH, $l) >= 2));
        foreach (['bank', 'personSalary', 'payrollDue', 'flightsDue', 'flightsPending'] as $h) {
            $r = self::$h($l, $D, $bn);
            if ($r !== null) return ['text' => $r, 'lang' => $bn ? 'bn' : 'en', 'intent' => 'ops_' . $h];
        }
        return null;
    }

    private static function m(float $v, bool $bn): string { return Phrase::money($v, $bn ? 'bn' : 'en'); }
    private static function n($v, bool $bn): string { return Phrase::n($v, $bn ? 'bn' : 'en'); }
    private static function asksAmount(string $l): bool
    {
        return (bool) preg_match('/\b(how much|balance|koto|kto|amount|left|ache|achhe|total)\b|কত|ব্যালেন্স|ব্যালান্স|আছে|মোট/u', $l);
    }

    /* ---------- a named bank or wallet ---------- */
    private const WALLETS = ['bkash' => '/\bb ?kash\b|বিকাশ|বিকেশ/u', 'nagad' => '/\bnagad\b|নগদ অ্যাকাউন্ট|নগদ একাউন্ট/u', 'rocket' => '/\brocket\b|রকেট/u',
        'petty' => '/\bpetty\b|পেটি/u', 'office cash' => '/\boffice cash\b|অফিস ক্যাশ/u', 'city' => '/\bcity bank\b|সিটি ব্যাংক/u', 'brac' => '/\bbrac\b|ব্র্যাক|ব্রাক/u',
        'dutch' => '/\bdutch\b|\bdbbl\b|ডাচ/u', 'islami' => '/\bislami\b|ইসলামী|ইসলামি/u', 'dhaka bank' => '/\bdhaka bank\b|ঢাকা ব্যাংক/u'];
    private const LABEL = ['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'petty' => 'Petty cash', 'office cash' => 'Office cash', 'city' => 'City Bank', 'brac' => 'BRAC Bank', 'dutch' => 'Dutch-Bangla', 'islami' => 'Islami Bank', 'dhaka bank' => 'Dhaka Bank'];
    private static function bank(string $l, array $D, bool $bn): ?string
    {
        if (!self::asksAmount($l)) return null;
        $want = null;
        foreach (self::WALLETS as $k => $rx) if (preg_match($rx, $l)) { $want = $k; break; }
        if ($want === null) return null;
        $key = ['city' => 'city', 'brac' => 'brac', 'dutch' => 'dutch', 'islami' => 'islami', 'dhaka bank' => 'dhaka', 'office cash' => 'office cash'][$want] ?? $want;
        $rows = array_values(array_filter($D['banks'] ?? [], fn($b) => str_contains(mb_strtolower((string) ($b['name'] ?? '')), $key)
            || ($key === 'brac' && str_contains(mb_strtolower((string) $b['name']), 'brack'))));
        if (!$rows) return $bn ? 'ERP-তে "' . $want . '" নামে কোনো account পাইনি।' : 'There is no "' . $want . '" account in the ERP.';
        $total = array_sum(array_map(fn($b) => (float) ($b['balance'] ?? 0), $rows));
        $name = self::companies($D);
        $parts = array_map(fn($b) => (string) $b['name'] . (isset($name[$b['company_id'] ?? null]) ? ' (' . $name[$b['company_id']] . ')' : '') . ' — ' . self::m((float) $b['balance'], $bn), $rows);
        if (count($rows) === 1) return $bn ? "{$parts[0]} আছে।" : "{$parts[0]}.";
        return $bn
            ? sprintf('%s মিলিয়ে মোট %s — %sটা account-এ: %s।', (self::LABEL[$want] ?? ucfirst($want)), self::m($total, true), self::n(count($rows), true), implode('; ', $parts))
            : sprintf('%s comes to %s across %d accounts: %s.', (self::LABEL[$want] ?? ucfirst($want)), self::m($total, false), count($rows), implode('; ', $parts));
    }
    private static function companies(array $D): array
    {
        $o = []; foreach ($D['companies'] ?? [] as $c) $o[$c['id']] = (string) ($c['short_name'] ?? $c['name'] ?? ''); return $o;
    }

    /* ---------- one person's salary: paid or not ---------- */
    private static function personSalary(string $l, array $D, bool $bn): ?string
    {
        if (!preg_match('/\b(salary|beton|betan|payslip)\b|বেতন|স্যালারি|সেলারি|পে-স্লিপ/u', $l)) return null;
        if (!preg_match('/\b(paid|pay|dewa|deya|diyeche|dise|hoyeche|hoise|hoyse|peyeche|got|received)\b|দেওয়া|দেয়া|দিয়েছ|দিয়েছ|পেয়েছ|পেয়েছ|হয়েছে|হয়েছে|হয়েছে|পরিশোধ/u', $l)) return null;
        if (!class_exists('Grammar')) return null;
        $emps = array_values(array_filter($D['employees'] ?? [], fn($e) => ($e['status'] ?? 'active') === 'active' && (float) ($e['salary'] ?? 0) > 0));
        $hits = [];
        foreach (preg_split('/[\s,?।!]+/u', $l) ?: [] as $w) {
            $w = preg_replace('/(দের|য়ের|এর|ের|কে|র|der|er|ke|r)$/u', '', $w) ?? $w;
            $k = Grammar::skeleton($w);
            if (mb_strlen($k) < 3) continue;
            foreach ($emps as $e) foreach (preg_split('/\s+/', (string) $e['name']) ?: [] as $part)
                if (Grammar::skeleton($part) === $k) { $hits[$e['id']] = $e; break; }
            if ($hits) break;
        }
        if (!$hits) return null;
        if (count($hits) > 1) {
            $names = array_map(fn($e) => (string) $e['name'], array_values($hits));
            return $bn ? 'কার কথা বলছেন — ' . implode(', নাকি ', $names) . '?' : 'Which one — ' . implode(', or ', $names) . '?';
        }
        $e = array_values($hits)[0];
        $rows = array_values(array_filter($D['payroll'] ?? [], fn($r) => (int) $r['user_id'] === (int) $e['id']));
        usort($rows, fn($a, $b) => strcmp((string) $b['month_key'], (string) $a['month_key']));
        $all = array_unique(array_map(fn($r) => (string) $r['month_key'], $D['payroll'] ?? [])); rsort($all);
        if (!$rows) return $bn ? "{$e['name']}-এর কোনো payslip ERP-তে নেই।" : "{$e['name']} has no payslip in the ERP.";
        $r = $rows[0]; $paid = strtolower((string) $r['status']) === 'paid';
        $mon = Phrase::monthName((string) $r['month_key'], $bn ? 'bn' : 'en');
        $latest = ($all[0] ?? '') === $r['month_key'];
        $miss = !$latest && isset($all[0]) ? ($bn ? ' ' . Phrase::monthName($all[0], 'bn') . '-এর payroll-এ উনার নাম নেই।' : ' They are not on the ' . Phrase::monthName($all[0], 'en') . ' payroll.') : '';
        if ($paid) return ($bn
            ? sprintf('জি, %s-এর %s-এর বেতন দেওয়া হয়েছে — net %s%s।', $e['name'], $mon, self::m((float) $r['net_salary'], true), $r['payment_date'] ? ', ' . Phrase::day((string) $r['payment_date'], 'bn') . ' তারিখে' : '')
            : sprintf('Yes — %s was paid for %s: %s net%s.', $e['name'], $mon, self::m((float) $r['net_salary'], false), $r['payment_date'] ? ' on ' . Phrase::day((string) $r['payment_date'], 'en') : '')) . $miss;
        return ($bn
            ? sprintf('না, %s-এর %s-এর বেতন এখনো দেওয়া হয়নি — net %s বাকি।', $e['name'], $mon, self::m((float) $r['net_salary'], true))
            : sprintf('No — %s\'s %s salary is not paid yet: %s net outstanding.', $e['name'], $mon, self::m((float) $r['net_salary'], false))) . $miss;
    }

    /* ---------- this month's payroll still to pay ---------- */
    private static function payrollDue(string $l, array $D, bool $bn): ?string
    {
        if (!preg_match('/\b(salary|salaries|payroll|beton|betan|bet[oa]n)\b|বেতন|স্যালারি|সেলারি|পেরোল/u', $l)) return null;
        if (!preg_match('/\b(dite hobe|dite hbe|to pay|due|unpaid|pending|baki|left|owe)\b|দিতে হবে|বাকি|বকেয়া|দেওয়া বাকি|দেয়া বাকি/u', $l)) return null;
        if (!preg_match('/\b(sobai|sobaike|shobai|everyone|all|total|staff|team|this month|ei mase|ei mashe|koto)\b|সবাই|সবাইকে|সবার|মোট|এই মাসে|এ মাসে|কত/u', $l)) return null;
        $rows = $D['payroll'] ?? [];
        if (!$rows) return null;
        $months = array_unique(array_map(fn($r) => (string) $r['month_key'], $rows)); rsort($months);
        $mk = $months[0];
        $cur = array_values(array_filter($rows, fn($r) => $r['month_key'] === $mk));
        $unpaid = array_values(array_filter($cur, fn($r) => strtolower((string) $r['status']) !== 'paid'));
        $net = array_sum(array_map(fn($r) => (float) $r['net_salary'], $unpaid));
        $all = array_sum(array_map(fn($r) => (float) $r['net_salary'], $cur));
        $mon = Phrase::monthName($mk, $bn ? 'bn' : 'en');
        if (!$unpaid) return $bn ? "{$mon}-এর বেতন সবাইকে দেওয়া হয়ে গেছে — " . self::n(count($cur), true) . 'টা payslip, মোট ' . self::m($all, true) . '।'
                                 : "{$mon} payroll is fully paid — " . count($cur) . ' payslips, ' . self::m($all, false) . '.';
        return $bn
            ? sprintf('%s-এর বেতন বাবদ এখনো দিতে হবে %s — %sজনের, %sটা payslip-এর মধ্যে। পুরো মাসের net payroll %s।', $mon, self::m($net, true), self::n(count($unpaid), true), self::n(count($cur), true), self::m($all, true))
            : sprintf('%s salaries still to pay: %s to %d people, of %d payslips. The month\'s net payroll is %s.', $mon, self::m($net, false), count($unpaid), count($cur), self::m($all, false));
    }

    /* ---------- flights ---------- */
    private static function flights(array $D): array { return $D['contract_flights'] ?? []; }
    private static function flightsPending(string $l, array $D, bool $bn): ?string
    {
        if (!preg_match('/\b(flights?)\b|ফ্লাইট/u', $l)) return null;
        if (!preg_match('/\b(baki|pending|upcoming|left|remaining|koyta|koita|how many|next|ache)\b|বাকি|কয়টা|কয়টি|কতগুলো|সামনে|আসছে|আছে/u', $l)) return null;
        $F = self::flights($D); if (!$F) return null;
        $now = date('Y-m-d H:i:s');
        $up = array_values(array_filter($F, fn($f) => (string) ($f['departure_at'] ?? '') > $now && !in_array(strtolower((string) ($f['status'] ?? '')), ['cancelled', 'canceled'], true)));
        if (!$up) return $bn ? 'সামনে কোনো contract flight নেই — সবগুলো উড়ে গেছে।' : 'No contract flights are still to fly — all have departed.';
        $next = $up[0];
        $seats = array_sum(array_map(fn($f) => (int) $f['total_seats'], $up)); $sold = array_sum(array_map(fn($f) => (int) $f['seats_sold'], $up));
        $d = Phrase::day(substr((string) $next['departure_at'], 0, 10), $bn ? 'bn' : 'en');
        return $bn
            ? sprintf('সামনে %sটা flight বাকি আছে। সবচেয়ে কাছেরটা %s, %s। এই flight-গুলোতে %sটা seat-এর মধ্যে %sটা বিক্রি হয়েছে।', self::n(count($up), true), (string) $next['flight_number'], $d, self::n($seats, true), self::n($sold, true))
            : sprintf('%d flights are still to fly. The next is %s on %s. Across them, %d of %d seats are sold.', count($up), (string) $next['flight_number'], $d, $sold, $seats);
    }
    private static function flightsDue(string $l, array $D, bool $bn): ?string
    {
        if (!preg_match('/\b(flights?)\b|ফ্লাইট/u', $l)) return null;
        if (!preg_match('/\b(taka|money|due|payment|paid|unpaid|payable|owe|dena|pawna)\b|টাকা|ডিউ|বাকি টাকা|পেমেন্ট|পাওনা|দেনা|বকেয়া/u', $l)) return null;
        $F = self::flights($D); if (!$F) return null;
        $due = array_values(array_filter($F, fn($f) => (float) ($f['due_amount'] ?? 0) > 0.5));
        $dueSum = array_sum(array_map(fn($f) => (float) $f['due_amount'], $due));
        $P = $D['contract_flight_passengers'] ?? [];
        $ar = array_values(array_filter($P, fn($p) => (float) ($p['agent_price'] ?? 0) - (float) ($p['agent_paid_amount'] ?? 0) > 0.5));
        $arSum = array_sum(array_map(fn($p) => (float) $p['agent_price'] - (float) ($p['agent_paid_amount'] ?? 0), $ar));
        $arFlights = count(array_unique(array_map(fn($p) => $p['flight_number'], $ar)));
        return $bn
            ? sprintf('%sটা flight-এর vendor-কে এখনো দিতে হবে %s। আর agent-দের কাছ থেকে পাওনা %s — %sটা flight-এর %sজন passenger-এর।', self::n(count($due), true), self::m($dueSum, true), self::m($arSum, true), self::n($arFlights, true), self::n(count($ar), true))
            : sprintf('%d flights still owe their vendor %s. Agents still owe us %s for %d passengers across %d flights.', count($due), self::m($dueSum, false), self::m($arSum, false), count($ar), $arFlights);
    }
}
