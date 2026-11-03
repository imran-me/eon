<?php
/* ============================================================
   EON · Query — understanding by composition, the way a language model
   does it, for EON's two domains (Group accounts and Travel).

   A question is broken into parts and the parts are combined freely:
     MEASURE   profit · sales · expense · due · payable · collection · count · seats · balance
     OF WHAT   agent · vendor · ticket · flight · visa · file · bank · section · category · airline · month
     FILTERS   a named party · a period · a section · a status · an airline
     OPERATION total · list · rank (top/bottom N) · compare (two periods) · trend (by month)
   One engine computes every combination from the ERP dataset and phrases
   the answer — so a question nobody wrote a rule for still has an answer.

   The last frame is remembered per conversation, so a follow-up ("আর গত
   মাসে?", "শুধু টপ ৩", "এজেন্ট অনুযায়ী ভাগ করো") changes only what it says.
   ============================================================ */
declare(strict_types=1);

final class Query
{
    private static bool $bn = true;
    /** the conversation this question belongs to — set by Brain::ask, so a follow-up can find the last frame */
    public static ?string $conv = null;
    private static function t(string $en, string $bn): string { return self::$bn ? $bn : $en; }
    private static function m(float $v): string { return Phrase::money($v, self::$bn ? 'bn' : 'en'); }
    private static function n($v): string { return Phrase::n($v, self::$bn ? 'bn' : 'en'); }

    /* ---------------- the lexicon: what words mean ---------------- */
    private const MEASURE = [
        // a count first: "কয়টা টিকেট বিক্রি হইছে" asks how many, not how much
        'count'      => '/কয়টা|কয়টা|কয়টি|কতগুলো|কতজন|কয়জন|কয়জন|\bhow many|\bkoyta|\bkoita|\bnumber of/u',
        'profit'     =>'/লাভ|প্রফিট|মুনাফা|মার্জিন|\blav\b|\bprofit|\bmargin|লোকসান|লস|ক্ষতি|\bloss/u',
        'sales'      => '/বিক্রি|বেচা|(?<![\p{L}\p{M}])সেল(?!ারি)|রেভিনিউ|আয়|আয়|ইনকাম|\bsales?\b|\brevenue|\bincome|\bbikri/u',
        'expense'    => '/খরচ|ব্যয়|ব্যয়|এক্সপেন্স|\bkhoroch|\bexpense|\bspend|\bcost\b/u',
        'due'        => '/বকেয়া|বাকি|পাওনা|পাবো|পাবো|ডিউ|\bdue\b|\bbaki|\bowe[sd]? us|\breceivable|\boutstanding/u',
        'payable'    => '/দিতে হবে|দেনা|পেয়েবল|\bpayable|\bwe owe|\bowe (them|to)/u',
        'collection' => '/কালেকশন|আদায়|আদায়|পেমেন্ট পাইছি|টাকা আসছে|\bcollect/u',
        'seats'      => '/সিট|আসন|\bseats?\b/u',
        'balance'    => '/ব্যালেন্স|ব্যালান্স|কত টাকা আছে|\bbalance/u',
    ];
    private const ENTITY = [
        'agent'    => '/এজেন্ট|ক্লায়েন্ট|কাস্টমার|\bagents?\b|\bclients?\b|\bcustomers?\b/u',
        'vendor'   => '/ভেন্ডর|ভেন্ডার|সাপ্লায়ার|সাপ্লায়ার|\bvendors?\b|\bsuppliers?\b/u',
        'ticket'   => '/টিকেট|টিকিট|\btickets?\b|\bpnr/u',
        'flight'   => '/ফ্লাইট|\bflights?\b/u',
        'visa'     => '/ভিসা|\bvisas?\b/u',
        'file'     => '/কন্ট্রাক্ট ফাইল|ফাইল|\bfiles?\b/u',
        'bank'     => '/ব্যাংক|ব্যাঙ্ক|বিকাশ|নগদ|ক্যাশ|\bbanks?\b|\bbkash|\bcash\b|ওয়ালেট|\bwallet/u',
        'section'  => '/সেকশন|বিজনেস|কোম্পানি|ব্যবসা|\bsections?\b|\bbusiness|\bcompan(y|ies)/u',
        'category' => '/খাত|ক্যাটাগরি|\bcategor(y|ies)\b|\bhead\b/u',
        'airline'  => '/এয়ারলাইন|এয়ারলাইন্স|এয়ারলাইন|\bairlines?\b/u',
        'month'    => '/মাস অনুযায়ী|মাস অনুযায়ী|মাসে মাসে|প্রতি মাসে|\bmonthly\b|\bby month|\bmonth by month|\bper month/u',
    ];
    private const RANK_TOP = '/সবচেয়ে বেশি|সবচেয়ে বেশি|সর্বোচ্চ|টপ|সেরা|শীর্ষ|\btop\b|\bmost\b|\bhighest|\bbest\b|\bbiggest|\blargest|\bbeshi/u';
    private const RANK_LOW = '/সবচেয়ে কম|সবচেয়ে কম|সর্বনিম্ন|নিচের দিকে|খারাপ|\blowest|\bleast\b|\bworst|\bsmallest|\bbottom\b|\bkom\b/u';
    private const COMPARE = '/তুলনা|তুলনায়|তুলনায়|পাশাপাশি|বনাম|\bvs\.?\b|\bversus|\bcompare|\bcompared/u';
    private const TREND = '/ট্রেন্ড|মাস অনুযায়ী|মাস অনুযায়ী|মাসে মাসে|প্রতি মাসে|কেমন যাচ্ছে|বাড়ছে না কমছে|\btrend|\bmonth by month|\bmonthly/u';
    private const LIST = '/লিস্ট|তালিকা|কোন কোন|কারা|কোনগুলো|দেখাও|\blist\b|\bshow\b|\bwhich\b/u';
    private const GROUP = '/অনুযায়ী|অনুযায়ী|ভিত্তিক|ভাগ করে|আলাদা করে|\bby\s+(agent|vendor|section|category|airline|month)|\bper\s+(agent|vendor|section)|\bwise\b/u';

    /* ---------------- parsing: the question → a frame ---------------- */
    public static function parse(string $q, array $D): array
    {
        $l = mb_strtolower($q);
        $f = ['measure' => null, 'entity' => null, 'by' => null, 'op' => 'total', 'n' => null, 'period' => Slots::period($q), 'period2' => null, 'company' => Slots::company($q, $D), 'party' => null, 'status' => null];
        foreach (self::MEASURE as $k => $rx) if (preg_match($rx, $l)) { $f['measure'] = $k; break; }
        if ($f['measure'] === 'balance' && !preg_match(self::ENTITY['bank'], $l)) $f['measure'] = 'due';
        foreach (self::ENTITY as $k => $rx) if (preg_match($rx, $l)) { $f['entity'] = $f['entity'] ?? $k; }
        // "X অনুযায়ী / by X": the grouping is the entity named next to it
        if (preg_match(self::GROUP, $l)) foreach (self::ENTITY as $k => $rx) if (preg_match($rx, $l) && $k !== 'ticket') { $f['by'] = $k; break; }
        if (preg_match(self::TREND, $l)) { $f['op'] = 'trend'; $f['by'] = 'month'; }
        elseif (preg_match(self::COMPARE, $l)) $f['op'] = 'compare';
        elseif (preg_match(self::RANK_LOW, $l)) $f['op'] = 'bottom';
        elseif (preg_match(self::RANK_TOP, $l)) $f['op'] = 'top';
        elseif (preg_match(self::LIST, $l) && !$f['measure']) $f['op'] = 'list';
        if (preg_match('/(?:টপ|top|সেরা|শীর্ষ|প্রথম|first)\s*(\d+|[০-৯]+)/u', $l, $m) || preg_match('/(\d+|[০-৯]+)\s*(?:টা|জন|টি)?\s*(?:এজেন্ট|ভেন্ডর|agent|vendor)/u', $l, $m))
            $f['n'] = (int) strtr($m[1], ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
        if (in_array($f['op'], ['top', 'bottom'], true) && !$f['by']) $f['by'] = in_array($f['entity'], ['agent', 'vendor', 'section', 'category', 'airline', 'bank'], true) ? $f['entity'] : null;
        if (preg_match('/ক্যান্সেল|ক্যানসেল|বাতিল|cancel|void|ভয়েড/u', $l)) $f['status'] = 'cancelled';
        elseif (preg_match('/পেন্ডিং|pending|অপেক্ষা/u', $l)) $f['status'] = 'pending';
        // "এই মাসে আর গত মাসে … তুলনা": this month is the subject, last month the baseline
        if ($f['op'] === 'compare' && preg_match('/এই মাস|এ মাস|this month|ei mas/u', $l)) $f['period'] = Slots::period('এই মাসে');
        if ($f['op'] === 'compare') $f['period2'] = self::previous($f['period'] ?? Slots::period('এই মাসে'));
        if ($f['op'] === 'compare' && !$f['period']) $f['period'] = Slots::period('এই মাসে');
        $p = Slots::party($q, $D, ['customer', 'supplier']);
        if ($p && !isset($p['none'])) { $f['party'] = $p; $f['entity'] = $f['entity'] ?? ($p['kind'] === 'supplier' ? 'vendor' : 'agent'); }
        // what a measure is OF when the entity is only implied
        if (!$f['entity'] && in_array($f['measure'], ['sales', 'profit'], true) && preg_match('/টিকেট|ticket/u', $l)) $f['entity'] = 'ticket';
        if ($f['measure'] === 'seats') $f['entity'] = 'flight';
        // "কন্ট্রাক্ট ফ্লাইট", "টিকেট" name the travel business already — a section filter only belongs to company-level measures
        if (in_array($f['entity'], ['flight', 'ticket', 'visa', 'file', 'agent', 'vendor'], true) && $f['company'] && str_contains(strtoupper((string) $f['company']['name']), 'TRAVEL')) $f['company'] = null;
        return $f;
    }

    /** the period before, the same length — "this month vs last month" */
    private static function previous(?array $p): ?array
    {
        if (!$p) return null;
        $a = new DateTimeImmutable($p[0]); $b = new DateTimeImmutable($p[1]); $days = (int) $a->diff($b)->days + 1;
        if ($a->format('d') === '01') { $pa = $a->modify('first day of last month'); $pb = $pa->modify('+' . ((int) $b->format('j') - 1) . ' days'); if ($pb->format('m') !== $pa->format('m')) $pb = $pa->modify('last day of this month');
            return [$pa->format('Y-m-d'), $pb->format('Y-m-d'), 'the same days last month', 'আগের মাসের একই সময়ে']; }
        return [$a->modify("-$days days")->format('Y-m-d'), $a->modify('-1 day')->format('Y-m-d'), 'the period before', 'তার আগের সমান সময়ে'];
    }

    /** a frame complete enough to answer: something to measure, or a count/list of something */
    public static function answerable(array $f): bool
    {
        if ($f['measure'] === null && $f['op'] !== 'list') return false;
        if ($f['measure'] === 'count' || $f['op'] === 'list') return (bool) $f['entity'];
        return true;
    }

    /* ---------------- the facts: one row per event, with what can be measured on it ---------------- */
    private static function rows(string $measure, ?string $entity, array $D): array
    {
        $co = []; foreach ($D['companies'] ?? [] as $c) $co[$c['id']] = (string) $c['name'];
        $out = [];
        $cost = []; $air = []; foreach ($D['ticket_purchases'] ?? [] as $p) { $cost[$p['id']] = (float) ($p['total'] ?? 0); $air[$p['id']] = (string) ($p['airline'] ?? ''); }
        switch ($measure) {
            case 'profit': case 'sales':
                if ($entity === 'flight') { foreach ($D['contract_flights'] ?? [] as $f) $out[] = ['date' => substr((string) $f['departure_at'], 0, 10), 'flight' => $f['flight_number'], 'vendor' => $f['vendor'] ?? '', 'section' => 'EPAL TRAVELS & CONSULTANCY', 'v' => $measure === 'profit' ? (float) $f['revenue'] - (float) $f['cost_price'] : (float) $f['revenue'], 'status' => $f['status']]; break; }
                if ($entity === 'section' || ($entity === null && $measure === 'profit' && !isset($D['__ticketonly']))) {
                    $type = []; foreach ($D['accounts'] ?? [] as $a) $type[$a['id']] = strtolower((string) $a['type']);
                    foreach ($D['journal_entries'] ?? [] as $j) foreach ((array) ($j['items'] ?? []) as $it) { $ty = $type[$it['account_id'] ?? 0] ?? '';
                        $inc = str_contains($ty, 'income') || str_contains($ty, 'revenue'); $exp = str_contains($ty, 'expense') || str_contains($ty, 'cost');
                        if (!$inc && !$exp) continue; if ($measure === 'sales' && !$inc) continue;
                        $out[] = ['date' => (string) $j['date'], 'section' => $co[$j['company_id']] ?? 'Group', 'v' => $inc ? (float) $it['credit'] - (float) $it['debit'] : -((float) $it['debit'] - (float) $it['credit'])]; }
                    break;
                }
                foreach ($D['ticket_index'] ?? [] as $t) { if ((float) $t['sale_price'] <= 0) continue;
                    $out[] = ['date' => (string) $t['date'], 'agent' => (string) $t['client'], 'vendor' => (string) $t['vendor'], 'airline' => $air[$t['purchase_id']] ?? '', 'section' => 'EPAL TRAVELS & CONSULTANCY', 'status' => (string) $t['status'],
                        'v' => $measure === 'profit' ? (float) $t['sale_price'] - ($cost[$t['purchase_id']] ?? 0) : (float) $t['sale_price']]; }
                foreach ($D['contract_flight_passengers'] ?? [] as $p) if ($measure === 'sales' && $entity === 'agent') $out[] = ['date' => substr((string) $p['departure_at'], 0, 10), 'agent' => (string) $p['agent'], 'section' => 'EPAL TRAVELS & CONSULTANCY', 'v' => (float) $p['agent_price']];
                break;
            case 'expense':
                foreach ($D['expenses'] ?? [] as $e) $out[] = ['date' => (string) $e['expense_date'], 'category' => (string) ($e['category'] ?: '—'), 'section' => $co[$e['company_id']] ?? 'Group', 'status' => (string) $e['approval_status'], 'v' => (float) $e['amount']];
                break;
            case 'due':
                foreach ($D['party_transactions'] ?? [] as $p) if ($p['party_type'] === 'customer' && !preg_match('/super ?admin/i', (string) $p['party_name'])) $out[] = ['date' => (string) $p['date'], 'agent' => (string) $p['party_name'], 'v' => (float) $p['debit'] - (float) $p['credit'], 'balance' => true];
                break;
            case 'payable':
                foreach ($D['ticket_purchases'] ?? [] as $p) $out[] = ['date' => (string) $p['date'], 'vendor' => (string) ($p['vendor'] ?: '—'), 'airline' => (string) $p['airline'], 'v' => (float) ($p['due_amount'] ?? 0)];
                foreach ($D['contract_flights'] ?? [] as $f) if ((float) $f['due_amount'] > 0) $out[] = ['date' => substr((string) $f['departure_at'], 0, 10), 'vendor' => (string) ($f['vendor'] ?? '—'), 'flight' => $f['flight_number'], 'v' => (float) $f['due_amount']];
                break;
            case 'collection':
                foreach ($D['party_transactions'] ?? [] as $p) if ($p['party_type'] === 'customer' && (float) $p['credit'] > 0 && !preg_match('/super ?admin/i', (string) $p['party_name'])) $out[] = ['date' => (string) $p['date'], 'agent' => (string) $p['party_name'], 'v' => (float) $p['credit']];
                break;
            case 'balance':
                foreach ($D['banks'] ?? [] as $b) $out[] = ['date' => null, 'bank' => (string) $b['name'], 'section' => $co[$b['company_id'] ?? 0] ?? 'Group', 'v' => (float) $b['balance'], 'balance' => true];
                break;
            case 'seats':
                foreach ($D['contract_flights'] ?? [] as $f) $out[] = ['date' => substr((string) $f['departure_at'], 0, 10), 'flight' => $f['flight_number'], 'v' => (float) $f['total_seats'] - (float) $f['seats_sold'], 'sold' => (float) $f['seats_sold'], 'total' => (float) $f['total_seats']];
                break;
            case 'count':
                $src = ['ticket' => fn() => array_map(fn($t) => ['date' => (string) $t['date'], 'agent' => (string) $t['client'], 'vendor' => (string) $t['vendor'], 'airline' => $air[$t['purchase_id']] ?? '', 'status' => (string) $t['status'], 'v' => 1], $D['ticket_index'] ?? []),
                    'flight' => fn() => array_map(fn($f) => ['date' => substr((string) $f['departure_at'], 0, 10), 'vendor' => (string) ($f['vendor'] ?? ''), 'status' => (string) $f['status'], 'v' => 1], $D['contract_flights'] ?? []),
                    'agent' => fn() => array_map(fn($p) => ['date' => substr((string) $p['departure_at'], 0, 10), 'agent' => (string) $p['agent'], 'flight' => $p['flight_number'], 'v' => 1], $D['contract_flight_passengers'] ?? []),
                    'visa' => fn() => array_map(fn($v) => ['date' => (string) ($v['travel_date'] ?? ''), 'status' => (string) $v['status'], 'category' => (string) $v['country'], 'v' => 1], $D['visa_processes'] ?? []),
                    'file' => fn() => array_map(fn($x) => ['date' => (string) ($x['submit_date'] ?? ''), 'status' => (string) $x['status'], 'agent' => (string) ($x['client'] ?? ''), 'v' => 1], $D['contract_files'] ?? [])];
                $out = isset($src[$entity]) ? $src[$entity]() : [];
                break;
        }
        return $out;
    }

    /* ---------------- answering a frame ---------------- */
    public static function answer(array $f, array $D, bool $bn): ?string
    {
        self::$bn = $bn;
        if (!self::answerable($f)) return null;
        $measure = $f['measure'] ?? 'count';
        $rows = self::rows($measure, $f['entity'], $D);
        if (!$rows && $measure !== 'balance') return null;
        $keep = function (array $r, ?array $per) use ($f) {
            if ($per && isset($r['date']) && $r['date'] !== null && !(substr((string) $r['date'], 0, 10) >= $per[0] && substr((string) $r['date'], 0, 10) <= $per[1])) return false;
            if ($f['party'] && (($r['agent'] ?? null) !== $f['party']['name'] && ($r['vendor'] ?? null) !== $f['party']['name'])) return false;
            if ($f['company'] && isset($r['section']) && $r['section'] !== $f['company']['name']) return false;
            if ($f['status'] === 'cancelled' && isset($r['status']) && !preg_match('/cancel|void/i', $r['status'])) return false;
            if ($f['status'] === 'pending' && isset($r['status']) && !preg_match('/pending|processing|open/i', $r['status'])) return false;
            if (!$f['status'] && $f['measure'] !== 'count' && isset($r['status']) && preg_match('/cancel|void/i', $r['status'])) return false;
            return true;
        };
        $per = (!empty($rows[0]['balance'])) ? null : $f['period'];   // a balance is as of now, whatever period was said
        $A = array_values(array_filter($rows, fn($r) => $keep($r, $per)));
        $label = self::label($f);
        $when = $per ? (self::$bn ? Phrase::bnDigits($per[3]) : $per[2]) : self::t('on record', 'রেকর্ডে মোট');
        $sum = fn($xs) => array_sum(array_map(fn($r) => $r['v'], $xs));
        $fmt = fn($v) => $measure === 'count' ? self::n($v) . self::t('', 'টা') : ($measure === 'seats' ? self::n($v) : self::m($v));

        if ($f['op'] === 'compare' && $f['period2']) {
            $B = array_values(array_filter($rows, fn($r) => $keep($r, $f['period2'])));
            $a = $sum($A); $b = $sum($B); $d = $a - $b; $pc = $b != 0 ? round(100 * $d / abs($b), 1) : null;
            $w2 = self::$bn ? $f['period2'][3] : $f['period2'][2];
            return self::t(sprintf('%s: %s %s against %s %s — %s %s%s.', ucfirst($label), $fmt($a), $when, $fmt($b), $w2, $d >= 0 ? 'up' : 'down', $fmt(abs($d)), $pc !== null ? " ({$pc}%)" : ''),
                sprintf('%s %s %s, %s %s — %s %s%s।', $when, $label, $fmt($a), $w2, $fmt($b), $d >= 0 ? 'বেড়েছে' : 'কমেছে', $fmt(abs($d)), $pc !== null ? ' (' . self::n(abs($pc)) . '%)' : ''));
        }
        if ($f['op'] === 'trend') {
            $g = []; foreach ($rows as $r) { if (!$keep($r, null) || empty($r['date'])) continue; $g[substr((string) $r['date'], 0, 7)] = ($g[substr((string) $r['date'], 0, 7)] ?? 0) + $r['v']; }
            ksort($g); $g = array_slice($g, -6, null, true); if (!$g) return null;
            $parts = array_map(fn($k, $v) => Phrase::monthName($k, self::$bn ? 'bn' : 'en') . ' ' . $fmt($v), array_keys($g), $g);
            $vals = array_values($g); $dir = count($vals) > 1 ? (end($vals) >= $vals[count($vals) - 2] ? self::t('rising', 'বাড়ছে') : self::t('falling', 'কমছে')) : '';
            return self::t(ucfirst($label) . ' month by month: ' . implode('; ', $parts) . ($dir ? " — {$dir} lately." : '.'), "মাস অনুযায়ী {$label}: " . implode('; ', $parts) . ($dir ? " — সম্প্রতি {$dir}।" : '।'));
        }
        $by = $f['by'];
        if ($by) {
            $key = ['agent' => 'agent', 'vendor' => 'vendor', 'section' => 'section', 'category' => 'category', 'airline' => 'airline', 'bank' => 'bank', 'month' => 'date', 'flight' => 'flight'][$by] ?? null;
            if (!$key) return null;
            $g = []; foreach ($A as $r) { $k = $key === 'date' ? substr((string) ($r['date'] ?? ''), 0, 7) : trim((string) ($r[$key] ?? '')); if ($k === '' || $k === '—') continue; /* an unnamed row is not a party to rank */ $g[$k] = ($g[$k] ?? 0) + $r['v']; }
            if (!empty($rows[0]['balance'])) $g = array_filter($g, fn($v) => $measure === 'due' ? $v > 0.5 : true);
            if (!$g) return self::t("Nothing to show for $label $when.", "$when $label-এর কিছু নেই।");
            $f['op'] === 'bottom' ? asort($g) : arsort($g);
            $n = $f['n'] ?? ($f['op'] === 'total' ? 8 : 5);
            if (in_array($by, ['section'], true) && $f['op'] === 'total') uksort($g, fn($x, $y) => [str_contains(strtoupper($x), 'GROUP') ? 0 : (str_contains(strtoupper($x), 'TRAVEL') ? 1 : 2), -$g[$x]] <=> [str_contains(strtoupper($y), 'GROUP') ? 0 : (str_contains(strtoupper($y), 'TRAVEL') ? 1 : 2), -$g[$y]]);
            $top = array_slice($g, 0, $n, true);
            $parts = array_map(fn($k, $v) => "$k " . $fmt($v), array_keys($top), $top);
            $byw = self::t(['agent' => 'agent', 'vendor' => 'vendor', 'section' => 'section', 'category' => 'category', 'airline' => 'airline', 'bank' => 'account', 'month' => 'month', 'flight' => 'flight'][$by] ?? $by,
                ['agent' => 'এজেন্ট', 'vendor' => 'ভেন্ডর', 'section' => 'সেকশন', 'category' => 'খাত', 'airline' => 'এয়ারলাইন', 'bank' => 'account', 'month' => 'মাস', 'flight' => 'ফ্লাইট'][$by] ?? $by);
            $head = $f['op'] === 'top' ? self::t("Highest $label by $byw", "সবচেয়ে বেশি $label — $byw অনুযায়ী") : ($f['op'] === 'bottom' ? self::t("Lowest $label by $byw", "সবচেয়ে কম $label — $byw অনুযায়ী") : self::t(ucfirst($label) . " by $byw", "$byw অনুযায়ী $label"));
            return self::t("$head, $when: " . implode('; ', $parts) . '.', "$when $head: " . implode('; ', $parts) . '।');
        }
        $total = $sum($A);
        // what agents owe is each agent's own balance, and only the ones in debt — an advance is not a negative due
        if ($measure === 'due' && !$f['party']) { $g = []; foreach ($A as $r) $g[$r['agent']] = ($g[$r['agent']] ?? 0) + $r['v']; $total = array_sum(array_filter($g, fn($v) => $v > 0.5)); }
        $who = $f['party'] ? $f['party']['name'] . ' ' : ($f['company'] ? $f['company']['name'] . ' ' : '');
        if ($measure === 'seats') { $sold = array_sum(array_map(fn($r) => $r['sold'], $A)); $all = array_sum(array_map(fn($r) => $r['total'], $A));
            return self::t(sprintf('%s: %d of %d contract-flight seats sold, %d unsold across %d flights.', ucfirst($when), $sold, $all, $total, count($A)), sprintf('%s %sটা flight-এ %sটা seat-এর মধ্যে %sটা বিক্রি, %sটা খালি।', $when, self::n(count($A)), self::n($all), self::n($sold), self::n($total))); }
        if ($measure === 'count' && $A && isset($A[0]['status'])) {
            $st = []; foreach ($A as $r) $st[$r['status'] ?: '—'] = ($st[$r['status'] ?: '—'] ?? 0) + 1; arsort($st);
            $sp = implode(', ', array_map(fn($k, $v) => "$k " . self::n($v), array_keys($st), $st));
            return self::t(sprintf('%s%s %s: %s (%s).', $who, ucfirst($label), $when, $fmt($total), $sp), sprintf('%s %s%s %s (%s)।', $when, $who, $label, $fmt($total), $sp));
        }
        return self::t(sprintf('%s%s %s: %s.', $who ? $who . '— ' : '', ucfirst($label), $when, $fmt($total)), sprintf('%s %s%s %s।', $when, $who ? $who . '— ' : '', $label, $fmt($total)));
    }

    private static function label(array $f): string
    {
        $m = $f['measure'] ?? 'count'; $e = $f['entity'];
        $en = ['profit' => 'profit', 'sales' => 'sales', 'expense' => 'spending', 'due' => 'agent dues', 'payable' => 'vendor dues', 'collection' => 'collection', 'seats' => 'seats', 'balance' => 'balance', 'count' => ($e ? $e . 's' : 'items')][$m];
        $bn = ['profit' => 'লাভ', 'sales' => 'বিক্রি', 'expense' => 'খরচ', 'due' => 'পাওনা', 'payable' => 'vendor-দের due', 'collection' => 'কালেকশন', 'seats' => 'seat', 'balance' => 'ব্যালেন্স',
            'count' => ['ticket' => 'ticket', 'flight' => 'contract flight', 'agent' => 'passenger', 'visa' => 'visa', 'file' => 'contract file'][$e] ?? 'টা'][$m];
        if (in_array($m, ['profit', 'sales'], true) && $e === 'flight') { $en = "contract-flight $en"; $bn = "contract flight-এর $bn"; }
        elseif (in_array($m, ['profit', 'sales'], true) && $e === 'ticket') { $en = "ticket $en"; $bn = "ticket-এ $bn"; }
        if ($f['status'] === 'cancelled') { $en = "cancelled $en"; $bn = "cancel হওয়া $bn"; }
        return self::t($en, $bn);
    }

    /* ---------------- follow-ups: the last frame, changed only where the boss changed it ---------------- */
    public static function followUp(string $q, array $last, array $D): ?array
    {
        $l = mb_strtolower(trim($q));
        if (mb_strlen($l) > 60) return null;
        $new = self::parse($q, $D);
        // a follow-up says little of its own — no measure, or it opens with "আর / and / what about" — and
        // it must change SOMETHING the last answer had (a period, a grouping, a number, a section, a name, a status)
        $opens = (bool) preg_match('/^(আর|আচ্ছা|তাহলে|and|what about|how about|ar|tahole)(?![\p{L}\p{M}])/u', $l);
        $short = $opens || (!$new['measure'] && count(preg_split('/\s+/u', $l) ?: []) <= 3);
        $says = $new['period'] || $new['by'] || $new['n'] || $new['company'] || $new['party'] || $new['status'] || !in_array($new['op'], ['total', 'list'], true);
        if (!$short || !$says) return null;
        $f = $last;
        if ($new['period']) { $f['period'] = $new['period']; if ($f['op'] === 'compare') $f['period2'] = self::previous($new['period']); }
        if ($new['by']) { $f['by'] = $new['by']; if ($f['op'] === 'total') $f['op'] = 'total'; }
        if ($new['n']) { $f['n'] = $new['n']; if ($f['op'] === 'total') $f['op'] = 'top'; }
        if ($new['op'] !== 'total' && $new['op'] !== 'list') { $f['op'] = $new['op']; if ($new['op'] === 'compare') $f['period2'] = self::previous($f['period'] ?? Slots::period('এই মাসে')); }
        if ($new['company']) $f['company'] = $new['company'];
        if ($new['party']) $f['party'] = $new['party'];
        if ($new['status']) $f['status'] = $new['status'];
        if (preg_match('/শুধু|only|just/u', $l) && !$new['company'] && !$new['party'] && !$new['n'] && !$new['by']) return null;
        return $f == $last ? null : $f;
    }

    /* the last frame per conversation, kept a short while */
    public static function remember(?string $conv, array $f): void
    {
        if (!$conv) return; $dir = dirname(__DIR__) . '/storage/cache'; if (!is_dir($dir)) return;
        @file_put_contents($dir . '/frame-' . preg_replace('/[^a-z0-9_-]/i', '', $conv) . '.json', json_encode(['at' => time(), 'f' => $f], JSON_UNESCAPED_UNICODE));
    }
    public static function recall(?string $conv): ?array
    {
        if (!$conv) return null; $file = dirname(__DIR__) . '/storage/cache/frame-' . preg_replace('/[^a-z0-9_-]/i', '', $conv) . '.json';
        $j = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return ($j && time() - (int) $j['at'] < 900) ? $j['f'] : null;
    }
}
