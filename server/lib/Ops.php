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
    /** the options to offer as buttons when a question named more than one person */
    public static ?array $choices = null;
    private const BANGLISH = '/\b(ache|achhe|koto|kto|taka|dite|hobe|baki|koyta|koita|dewa|deya|hoyeche|hoise|hoyse|mase|mashe|kothay|dekhao|amar|amader|sobai|sobaike|shobai|ki|keno|kar|koro|niye|jao)\b/i';

    public static function answer(string $q, array $D, ?string $lang = null): ?array
    {
        $l = mb_strtolower(trim($q));
        // 'bn'/'en' exactly is an instruction (the Gemini planner re-asks in English for a Bangla speaker)
        $bn = $lang === 'bn' ? true : ($lang === 'en' ? false : ((bool) preg_match('/\p{Bengali}/u', $q) || preg_match_all(self::BANGLISH, $l) >= 2));
        self::$nav = null;
        foreach (['bank', 'personSalary', 'payrollDue', 'flightsOn', 'flightsDue', 'flightsPending'] as $h) {
            $r = self::$h($l, $D, $bn);
            if ($r !== null) return ['text' => $r, 'lang' => $bn ? 'bn' : 'en', 'intent' => 'ops_' . $h] + (self::$nav ? ['navigate_path' => self::$nav] : []);
        }
        return null;
    }
    /** a screen the browser should open with the answer ('/{role}/…'); set by a handler */
    private static ?string $nav = null;

    /* ---------- a date said the way people say it: "5 নভেম্বর", "৫ ডিসেম্বরের", "Dec 5", "today", "আগামীকাল" ---------- */
    private const MONTHS = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        'জানুয়ারি' => 1, 'ফেব্রুয়ারি' => 2, 'মার্চ' => 3, 'এপ্রিল' => 4, 'মে' => 5, 'জুন' => 6, 'জুলাই' => 7, 'আগস্ট' => 8, 'অগাস্ট' => 8, 'সেপ্টেম্বর' => 9, 'অক্টোবর' => 10, 'নভেম্বর' => 11, 'ডিসেম্বর' => 12];
    public static function dateIn(string $l): ?string
    {
        $l = strtr($l, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
        $today = new DateTimeImmutable('today');
        if (preg_match('/\b(today|ajke|aj)\b|আজকে|আজ(?![\p{Bengali}])/u', $l)) return $today->format('Y-m-d');
        if (preg_match('/\b(tomorrow|agamikal)\b|আগামীকাল/u', $l)) return $today->modify('+1 day')->format('Y-m-d');
        if (preg_match('/\b(yesterday|gotokal)\b|গতকাল/u', $l)) return $today->modify('-1 day')->format('Y-m-d');
        foreach (self::MONTHS as $name => $m) {
            $rx = preg_match('/^[a-z]/', $name) ? '\b' . $name . '[a-z]*' : $name . '\p{Bengali}*';
            if (preg_match('/(\d{1,2})\s*(?:st|nd|rd|th|ই|শে|তারিখ)?\s*' . $rx . '/u', $l, $x) || preg_match('/' . $rx . '\s*(\d{1,2})\b/u', $l, $x)) {
                $d = (int) $x[1]; if ($d < 1 || $d > 31) continue;
                $y = (int) $today->format('Y');
                $dt = DateTimeImmutable::createFromFormat('Y-n-j', "$y-$m-$d");
                if ($dt && $dt < $today->modify('-120 days')) $dt = $dt->modify('+1 year');   // "5 January" said in November is next January
                return $dt ? $dt->format('Y-m-d') : null;
            }
        }
        return null;
    }

    /* ---------- the flights of one day: count, list, or open ---------- */
    private static function flightsOn(string $l, array $D, bool $bn): ?string
    {
        if (!preg_match('/\b(flights?)\b|ফ্লাইট/u', $l)) return null;
        $day = self::dateIn($l); if ($day === null) return null;
        $F = array_values(array_filter(self::flights($D), fn($f) => substr((string) ($f['departure_at'] ?? ''), 0, 10) === $day));
        $d = Phrase::day($day, $bn ? 'bn' : 'en');
        if (!$F) return $bn ? "{$d} তারিখে কোনো contract flight নেই।" : "There is no contract flight on {$d}.";
        $one = fn($f) => $f['flight_number'] . ' · ' . substr((string) $f['departure_at'], 11, 5) . ' · ' . (int) $f['seats_sold'] . '/' . (int) $f['total_seats'] . ($bn ? ' seat' : ' seats');
        $list = implode('; ', array_map($one, $F));
        if (preg_match('/\b(open|show|kholo|dekhao)\b|ওপেন|খোলো|খুলুন|দেখাও|নিয়ে যাও/u', $l)) {
            self::$nav = '/{role}/contract-flights?search=' . rawurlencode((string) $F[0]['flight_number']);
            return $bn ? "{$d}-এর flight খুলছি — {$list}।" : "Opening the {$d} flight — {$list}.";
        }
        return $bn ? sprintf('%s তারিখে %sটা contract flight: %s।', $d, self::n(count($F), true), $list)
                   : sprintf('%d contract flight%s on %s: %s.', count($F), count($F) > 1 ? 's' : '', $d, $list);
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
        /* every name word said counts: "মিস্টার ইমরান হোসেন" is Mr. Imran Hossain, not every Imran;
           a tie goes to the name with the fewest words left unsaid (Imran Hossain over Imran Hossain Shafi) */
        $keys = [];
        foreach (preg_split('/[\s,?।!]+/u', $l) ?: [] as $w) {
            $w = preg_replace('/(দের|য়ের|এর|ের|কে|র|der|er|ke|r)$/u', '', $w) ?? $w;
            $k = preg_replace('/(.)\1+/', '$1', Grammar::skeleton($w));   // হোসেন (sn) = Hossain (ssn)
            if (mb_strlen($k) >= 2 && !preg_match("/^(mstr|mr|md|er)$/", $k)) $keys[$k] = true;
        }
        $score = [];
        foreach ($emps as $e) {
            $parts = array_filter(preg_split('/\s+/', (string) $e['name']) ?: [], fn($p) => !preg_match('/^(mr|mrs|ms|md|mohammad|mohammed)\.?$/i', $p));
            $n = 0; foreach ($parts as $p) if (isset($keys[preg_replace('/(.)\1+/', '$1', Grammar::skeleton($p))])) $n++;
            if ($n > 0) $score[$e['id']] = [$n, count($parts) - $n, $e];
        }
        if (!$score) return null;
        uasort($score, fn($a, $b) => ($b[0] <=> $a[0]) ?: ($a[1] <=> $b[1]));
        $top = array_values($score)[0];
        $hits = array_map(fn($x) => $x[2], array_filter($score, fn($x) => $x[0] === $top[0] && ($top[0] < 2 || $x[1] === $top[1])));   // one shared word ("Imran") always asks
        if (count($hits) > 1) {
            $names = array_map(fn($e) => (string) $e['name'], array_values($hits));
            self::$choices = array_map(fn($n) => $bn ? "{$n}-এর স্যালারি দেওয়া হয়েছে?" : "Has {$n} been paid salary?", $names);
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
