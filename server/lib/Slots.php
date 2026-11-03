<?php
/* ============================================================
   EON · Slots — the details inside a question, read the way the boss says
   them: WHO (an agent / client / vendor, matched by sound across Bangla and
   Latin spellings), WHEN (আজ, গতকাল, পরশু, এই হপ্তা, গত ৭ দিন, এই মাস,
   গত মাস, কোয়ার্টার, বছর, গত ৬ মাস, a named date, "১ তারিখ থেকে আজ"),
   WHICH SECTION (travel, IT, construction…), a route, an amount.
   ============================================================ */
declare(strict_types=1);

final class Slots
{
    private static function digits(string $s): string
    {
        return strtr($s, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    }

    /** [from, to, label_en, label_bn] — inclusive Y-m-d; null when the question names no period */
    public static function period(string $q): ?array
    {
        $l = mb_strtolower(self::digits($q));
        $t = new DateTimeImmutable('today'); $f = fn($d) => $d->format('Y-m-d');
        $mk = fn($a, $b, $en, $bn) => [$f($a), $f($b), $en, $bn];
        if (preg_match('/গত\s*পরশু|পরশু|day before yesterday|gotoporshu/u', $l)) return $mk($t->modify('-2 day'), $t->modify('-2 day'), 'the day before yesterday', 'গত পরশু');
        if (preg_match('/গতকাল|\byesterday\b|gotokal|kalke(?! theke)/u', $l)) return $mk($t->modify('-1 day'), $t->modify('-1 day'), 'yesterday', 'গতকাল');
        if (preg_match('/আগামীকাল|\btomorrow\b|agamikal/u', $l)) return $mk($t->modify('+1 day'), $t->modify('+1 day'), 'tomorrow', 'আগামীকাল');
        if (preg_match('/(গত|last|past)\s*(\d+)\s*(দিন|days?|din)/u', $l, $m)) return $mk($t->modify('-' . ((int) $m[2] - 1) . ' day'), $t, "the last {$m[2]} days", "গত {$m[2]} দিনে");
        if (preg_match('/(গত|last|past)\s*(\d+|তিন|ছয়|ছয়|৩|৬)\s*(মাস|months?|mash|mas)/u', $l, $m)) { $n = ['তিন' => 3, 'ছয়' => 6, 'ছয়' => 6][$m[2]] ?? (int) $m[2]; return $mk($t->modify('first day of this month')->modify('-' . ($n - 1) . ' month'), $t, "the last $n months", "গত $n মাসে"); }
        if (preg_match('/এই\s*(সপ্তাহ|হপ্তা|সপ্তা)|this week|ei soptah/u', $l)) return $mk($t->modify('-' . ((int) $t->format('w') + 1) % 7 . ' day'), $t, 'this week', 'এই সপ্তাহে');
        if (preg_match('/গত\s*(সপ্তাহ|হপ্তা)|last week/u', $l)) { $s = $t->modify('-' . ((int) $t->format('w') + 1) % 7 . ' day'); return $mk($s->modify('-7 day'), $s->modify('-1 day'), 'last week', 'গত সপ্তাহে'); }
        if (preg_match('/গত\s*মাস|last month|goto mash|gotomash/u', $l)) { $s = $t->modify('first day of last month'); return $mk($s, $s->modify('last day of this month'), 'last month', 'গত মাসে'); }
        if (preg_match('/এই\s*মাস|এ\s*মাস|this month|ei mash|ei mase/u', $l)) return $mk($t->modify('first day of this month'), $t, 'this month', 'এই মাসে');
        if (preg_match('/(এই|চলতি)\s*কোয়ার্টার|this quarter/u', $l)) { $qm = intdiv((int) $t->format('n') - 1, 3) * 3 + 1; return $mk($t->setDate((int) $t->format('Y'), $qm, 1), $t, 'this quarter', 'এই কোয়ার্টারে'); }
        if (preg_match('/গত\s*কোয়ার্টার|last quarter/u', $l)) { $qm = intdiv((int) $t->format('n') - 1, 3) * 3 + 1; $s = $t->setDate((int) $t->format('Y'), $qm, 1)->modify('-3 month'); return $mk($s, $s->modify('+3 month')->modify('-1 day'), 'last quarter', 'গত কোয়ার্টারে'); }
        if (preg_match('/এই\s*বছর|this year|ei bochor/u', $l)) return $mk($t->setDate((int) $t->format('Y'), 1, 1), $t, 'this year', 'এই বছরে');
        if (preg_match('/গত\s*বছর|last year/u', $l)) { $y = (int) $t->format('Y') - 1; return $mk($t->setDate($y, 1, 1), $t->setDate($y, 12, 31), 'last year', 'গত বছরে'); }
        if (preg_match('/১?\s*1?\s*তারিখ থেকে আজ|from the 1st|since the first/u', $l)) return $mk($t->modify('first day of this month'), $t, 'this month so far', 'মাসের শুরু থেকে আজ পর্যন্ত');
        if (preg_match('/\b(today|ajke|aj)\b|আজকে|আজকের|আজ(?![\p{Bengali}])/u', $l)) return $mk($t, $t, 'today', 'আজ');
        if (class_exists('Ops') && ($d = Ops::dateIn($l))) { $x = new DateTimeImmutable($d); return [$d, $d, $x->format('j F'), Phrase::day($d, 'bn')]; }
        return null;
    }

    /** a business section the question names → a company id (by its name), or null */
    private const SECTION = [['/ট্রাভেল|travel|টিকেটিং|ticketing|ভিসা|visa|কন্ট্রাক্ট|contract/u', 'travel'], ['/আইটি|\bit\b|সফটওয়্যার/u', 'it'], ['/কনস্ট্রাকশন|construction/u', 'construct'],
        ['/ইন্টেরিয়র|interior|উড আর্ট|wood/u', 'wood'], ['/শপ|shop|ই-কমার্স/u', 'shop'], ['/এক্সপোর্ট|import|export/u', 'export'], ['/প্রপার্টি|property|ভাড়া/u', 'propert']];
    public static function company(string $q, array $D): ?array
    {
        $l = mb_strtolower($q);
        foreach (self::SECTION as [$rx, $key]) if (preg_match($rx, $l))
            foreach ($D['companies'] ?? [] as $c) if (str_contains(mb_strtolower((string) $c['name']), $key)) return $c;
        return null;
    }

    /**
     * The party a question names — agent, client or vendor — matched by sound across scripts
     * ("রহমান এয়ার" ↔ RAHMAN AIR). Returns ['name','kind','score'] or ['none' => '<what was said>'] when
     * the question clearly names someone the ERP does not have, or null when it names no one.
     */
    public static function party(string $q, array $D, array $kinds = ['customer', 'supplier']): ?array
    {
        if (!class_exists('Grammar')) return null;
        $names = [];
        foreach ($D['party_transactions'] ?? [] as $p) if (in_array($p['party_type'], $kinds, true) && !preg_match('/super ?admin/i', (string) $p['party_name'])) $names[(string) $p['party_name']] = $p['party_type'];
        if (in_array('customer', $kinds, true)) foreach ($D['contract_flight_passengers'] ?? [] as $p) if ($p['agent']) $names[(string) $p['agent']] = $names[(string) $p['agent']] ?? 'customer';
        if (in_array('supplier', $kinds, true)) foreach ($D['ticket_purchases'] ?? [] as $p) if ($p['vendor']) $names[(string) $p['vendor']] = $names[(string) $p['vendor']] ?? 'supplier';
        $skel = fn($s) => Grammar::skeleton(preg_replace('/\b(mr|md|mrs|ms|mst|ltd|limited)\b|\(.*?\)|মোঃ|মো\./iu', ' ', (string) $s) ?? '');
        $stop = '/^(এর|কে|থেকে|কাছে|কত|কয়টা|লাভ|লস|প্রফিট|ডিউ|বাকি|টাকা|স্টেটমেন্ট|লেজার|গত|এই|আজ|আজকে|ফ্লাইট|টিকেট|ট্রাভেল|দাও|দেখাও|পাঠাও|বলো|শোনো|আচ্ছা|একটু|প্লিজ|ভাই)$/u';
        $words = array_values(array_filter(preg_split('/[\s,?।!]+/u', $q) ?: [], fn($w) => $w !== ''));
        $best = null;
        for ($n = 4; $n >= 1; $n--) for ($i = 0; $i + $n <= count($words); $i++) {
            $chunk = array_slice($words, $i, $n);
            if (preg_match($stop, $chunk[0]) || preg_match($stop, end($chunk))) continue;
            $phrase = preg_replace('/(এর|ের|কে|র|সের|দের|থেকে|-এর)$/u', '', implode(' ', $chunk)) ?? '';
            $k = $skel($phrase); if (mb_strlen($k) < 4) continue;
            foreach ($names as $name => $kind) {
                $nk = $skel($name); if ($nk === '') continue;
                $sim = 0.0; similar_text($k, $nk, $sim);
                if (str_starts_with($nk, $k) || str_starts_with($k, $nk)) $sim = max($sim, 90.0 * min(mb_strlen($k), mb_strlen($nk)) / max(mb_strlen($k), mb_strlen($nk)) + 10);
                if ($sim >= 80 && (!$best || $sim > $best['score'])) $best = ['name' => $name, 'kind' => $kind, 'score' => $sim, 'said' => $phrase];
            }
        }
        if ($best) return $best;
        // a business name the ERP does not have ("আল-মাদানী ট্রাভেলস"): say so rather than answer for everyone
        if (preg_match('/([\p{L}\p{M}\-]+\s+)?([\p{L}\p{M}\-]+)\s+(ট্রাভেলস|ট্রাভেল|ট্যুরস|এজেন্সি|এয়ার|এন্টারপ্রাইজ|travels|tours|agency|air|enterprise)/iu', $q, $m)) return ['none' => trim($m[0])];
        return null;
    }
}
