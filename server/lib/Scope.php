<?php
declare(strict_types=1);

/* ============================================================
   Scope — the narrowing half of a question.

   "How many customers in Chittagong" is not the customers
   question. It is the customers question about one place, and
   until now EON answered the wide one and said nothing: 14
   customers, confidently, for a city the ERP has never heard of.
   Answering something slightly wider than what was asked is the
   same defect as answering about the wrong person — it is just
   harder to notice, because the number is real.

   So a narrowing phrase gets read, and then it gets *resolved
   against the data that actually exists*:

     resolved   → the answer is filtered, and says it was
     unresolved → the answer stays wide, and says so before the
                  boss can act on it, naming what it can filter by

   The dimensions are whatever the dataset carries: company,
   department (both the HR list and the free-text one the expense
   rows use, which is where places like "Bhairab, Kishoreganj"
   really do appear), and expense category. There is no city,
   district or branch column anywhere in the ERP, which is exactly
   why "in Chittagong" has to be refused rather than absorbed.
   ============================================================ */
final class Scope
{
    /** words that follow "in/at/for" without naming a unit of the business */
    private const STOP = [
        'the', 'this', 'that', 'last', 'next', 'total', 'general', 'all', 'every', 'each',
        'my', 'our', 'your', 'his', 'her', 'their', 'it', 'us', 'we', 'i',
        'cash', 'hand', 'bank', 'ledger', 'books', 'account', 'accounts', 'journal',
        'profit', 'loss', 'credit', 'debit', 'balance', 'advance', 'arrears', 'due', 'dues',
        'detail', 'details', 'short', 'brief', 'summary', 'full', 'number', 'numbers',
        'time', 'times', 'fact', 'order', 'place', 'front', 'terms', 'words', 'words',
        'erp', 'system', 'company', 'companies', 'department', 'departments', 'office',
        'branch', 'business', 'group', 'team', 'staff', 'people', 'employee', 'employees',
        'today', 'yesterday', 'tomorrow', 'week', 'month', 'year', 'quarter', 'period',
        'taka', 'tk', 'bdt', 'usd', 'lakh', 'crore', 'percent',
        'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august',
        'september', 'october', 'november', 'december',
        'jan', 'feb', 'mar', 'apr', 'jun', 'jul', 'aug', 'sep', 'sept', 'oct', 'nov', 'dec',
    ];

    /** an English noun that marks the word before it as a unit of the business */
    private const UNIT_EN = ['branch', 'office', 'outlet', 'region', 'zone', 'area', 'district',
        'division', 'unit', 'centre', 'center', 'depot', 'warehouse', 'site', 'store', 'showroom'];

    /** the same, in বাংলা */
    private const UNIT_BN = ['শাখা', 'অফিস', 'অঞ্চল', 'এলাকা', 'জেলা', 'ইউনিট', 'কেন্দ্র', 'গুদাম', 'দোকান'];

    /** words that mark the neighbouring word as a department rather than a place */
    private const DEPT_WORD_EN = ['department', 'dept', 'team', 'desk', 'section', 'wing', 'cell'];
    private const DEPT_WORD_BN = ['বিভাগ', 'ডিপার্টমেন্ট', 'টিম', 'শাখার', 'সেকশন'];

    private static ?array $vocab = null;
    private static ?string $vocabFor = null;

    /** forget the cached vocabulary — the dataset changed under us */
    public static function reset(): void { self::$vocab = null; self::$vocabFor = null; }

    /**
     * Read the narrowing phrase out of a question.
     *
     * @return array{text:string,dim:?string,key:?string,label:string,resolved:bool}|null
     */
    public static function read(string $raw, string $n, string $lang, ?array $D): ?array
    {
        $n = ' ' . trim($n) . ' ';

        // 1. does the sentence name something the data actually carries?
        $hit = self::matchVocab($n, $D);
        if ($hit !== null) return $hit;

        // 2. no — but is it *asking* for a narrowing we cannot give?
        return self::matchUnknown($raw, $n, $lang);
    }

    /* ---------------- resolvable: it is in the data ---------------- */

    private static function matchVocab(string $n, ?array $D): ?array
    {
        $vocab = self::ordered(self::vocab($D), $n);

        foreach ($vocab as $v) {
            foreach ($v['cues'] as $cue) {
                if (!Nlu::mentions($n, $cue)) continue;
                // a one-word cue needs the sentence to be talking about a unit,
                // otherwise "marketing spend" reads as the marketing department
                // and "driving licence" reads as the Driving department
                $weak = $v['weak'] || mb_strlen($cue, 'UTF-8') <= 3;
                if ($weak && !self::narrowingNearby($n)) continue;
                return ['text' => $cue, 'dim' => $v['dim'], 'key' => $v['key'],
                        'label' => $v['label'], 'resolved' => true];
            }
        }
        return self::matchAcrossScript($n, $vocab);
    }

    /**
     * The sentence says which kind of thing it is narrowing by, and that has to
     * outrank the default order: "employees in the IT department" bound to EPAL
     * IT SOLUTIONS, because a company outranks a department everywhere else. The
     * word "department" in the question is not decoration — it is the answer to
     * which dimension was meant.
     */
    private static function ordered(array $vocab, string $n): array
    {
        $prefer = null;
        foreach (self::DEPT_WORD_EN as $w) {
            if (preg_match('/(?<![a-z])' . $w . '(?![a-z])/u', $n)) { $prefer = 'department'; break; }
        }
        if ($prefer === null) {
            foreach (self::DEPT_WORD_BN as $w) if (mb_strpos($n, $w) !== false) { $prefer = 'department'; break; }
        }
        if ($prefer === null && (preg_match('/(?<![a-z])(?:company|firm|concern)(?![a-z])/u', $n) || mb_strpos($n, 'কোম্পানি') !== false)) {
            $prefer = 'company';
        }
        if ($prefer === null) return $vocab;

        $first = $second = [];
        foreach ($vocab as $v) {
            $isDept = in_array($v['dim'], ['department', 'expense_department'], true);
            $wanted = $prefer === 'department' ? $isDept : $v['dim'] === 'company';
            if ($wanted) $first[] = $v; else $second[] = $v;
        }
        return array_merge($first, $second);
    }

    /**
     * A বাংলা question about a Latin-spelled unit. The ERP stores "Ticketing &
     * Tours"; the boss types টিকিটিং. Grammar already solved this for people —
     * a consonant skeleton with the vowels and the digraph h dropped — and a
     * department is no different from a person in that respect.
     */
    private static function matchAcrossScript(string $n, array $vocab): ?array
    {
        if (!preg_match('/[\x{0980}-\x{09FF}]/u', $n)) return null;
        if (!class_exists('Grammar') || !self::narrowingNearby($n)) return null;

        /* Only the বাংলা words of the sentence may bridge. This path exists to let
           টিকিটিং reach "Ticketing & Tours"; it is not a second, looser way to match
           Latin to Latin — the cue list already does that exactly. Left reading the
           whole sentence it took "OPERATIONS MANAGEMENT-এর বিভাগ", reduced it to the
           one surviving skeleton *prtns*, and bound it to the IT & AI **Operations**
           department: a confident filter built out of a word the two names merely
           share. Latin tokens are dropped before the skeleton is taken. */
        $bnOnly = [];
        foreach (preg_split('/\s+/u', trim($n)) ?: [] as $w) {
            if (preg_match('/[\x{0980}-\x{09FF}]/u', $w)) $bnOnly[] = $w;
        }
        if ($bnOnly === []) return null;
        $said = self::skel(implode(' ', $bnOnly));
        if ($said === []) return null;

        foreach ($vocab as $v) {
            foreach ($v['cues'] as $cue) {
                $want = self::skel($cue);
                // a skeleton under three letters is not enough to bet a filter on
                if ($want === [] || strlen($want[0]) < 3) continue;
                $ok = true;
                foreach ($want as $w) {
                    if (!in_array($w, $said, true)) { $ok = false; break; }
                }
                /* Nobody says the whole name. "Accounts & Finance" is asked for as
                   অ্যাকাউন্টস বিভাগে — the first word of the name and the marker — so
                   demanding every word of a two-word department is demanding a
                   sentence no boss speaks. The lead word carries it, and only here:
                   this path already requires বাংলা and an explicit narrowing marker. */
                if (!$ok && count($want) > 1 && strlen($want[0]) >= 4 && in_array($want[0], $said, true)) {
                    $ok = true;
                }
                if ($ok) {
                    return ['text' => $cue, 'dim' => $v['dim'], 'key' => $v['key'],
                            'label' => $v['label'], 'resolved' => true];
                }
            }
        }
        return null;
    }

    /** is there anything in the sentence saying "of one part of the business"? */
    private static function narrowingNearby(string $n): bool
    {
        foreach (array_merge(self::DEPT_WORD_EN, self::UNIT_EN) as $w) {
            if (preg_match('/(?<![a-z])' . $w . '(?![a-z])/u', $n)) return true;
        }
        foreach (array_merge(self::DEPT_WORD_BN, self::UNIT_BN) as $w) {
            if (mb_strpos($n, $w) !== false) return true;
        }
        return (bool) preg_match('/(?<![a-z])(?:in|at|for|within|from|under)\s/u', $n);
    }

    /** every name the dataset gives us to filter by, with the cues that reach it */
    private static function vocab(?array $D): array
    {
        $stamp = $D === null ? 'none' : (string) (($D['meta']['generated_at'] ?? '') . count($D));
        if (self::$vocab !== null && self::$vocabFor === $stamp) return self::$vocab;

        $out = [];
        if (is_array($D)) {
            // companies — the most distinctive names in the file
            foreach (($D['companies'] ?? []) as $c) {
                $name = trim((string) ($c['name'] ?? ''));
                if ($name === '') continue;
                $out[] = ['dim' => 'company', 'key' => (string) ($c['id'] ?? ''), 'label' => $name,
                          'weak' => false, 'cues' => self::cuesFor($name, (string) ($c['short_name'] ?? ''))];
            }
            // departments — the HR list
            foreach (($D['departments'] ?? []) as $d) {
                $name = trim((string) ($d['name'] ?? ''));
                if ($name === '') continue;
                $out[] = ['dim' => 'department', 'key' => $name, 'label' => $name,
                          'weak' => false, 'cues' => self::cuesFor($name, '')];
            }
            // the free-text department the expense rows carry — this is where the
            // ERP's only real place names live ("Bhairab, Kishoreganj Residential")
            $seen = [];
            foreach (($D['expenses'] ?? []) as $e) {
                $name = trim((string) ($e['department'] ?? ''));
                if ($name === '' || isset($seen[$name])) continue;
                $seen[$name] = true;
                $out[] = ['dim' => 'expense_department', 'key' => $name, 'label' => $name,
                          'weak' => false, 'cues' => self::cuesFor($name, '')];
            }
            // expense categories
            $seen = [];
            foreach (($D['expenses'] ?? []) as $e) {
                $name = trim((string) ($e['category'] ?? ''));
                if ($name === '' || isset($seen[$name])) continue;
                $seen[$name] = true;
                $out[] = ['dim' => 'category', 'key' => $name, 'label' => $name,
                          'weak' => true, 'cues' => self::cuesFor($name, '')];
            }
        }
        /* Order decides ties, and there are real ties: "marketing" is both the
           Marketing & Branding department and the Marketing & Promotion expense
           category. A named unit of the business beats a bucket the accountant
           made up, so the dimension ranks first and cue length only breaks ties
           inside it — which is also what keeps "IT & AI Operations" from being
           beaten by a bare "IT". */
        $rank = ['company' => 0, 'department' => 1, 'expense_department' => 2, 'category' => 3];
        usort($out, function ($a, $b) use ($rank) {
            $r = ($rank[$a['dim']] ?? 9) <=> ($rank[$b['dim']] ?? 9);
            if ($r !== 0) return $r;
            return max(array_map('mb_strlen', $b['cues'])) <=> max(array_map('mb_strlen', $a['cues']));
        });

        self::$vocab = $out;
        self::$vocabFor = $stamp;
        return $out;
    }

    /**
     * The ways a person says a stored name. "EPAL TRAVELS & CONSULTANCY" is
     * asked for as "travels"; "Marketing & Branding" as "marketing". The house
     * word (EPAL) is never distinctive, and a fragment under four letters is
     * only kept when it is a real abbreviation people use.
     */
    private const KEEP_SHORT = ['it', 'hr', 'ui', 'ux', 'qa', 'ai'];
    private const HOUSE = ['epal', 'ltd', 'limited', 'company', 'co', 'and', '&', 'the', 'group'];

    /* The head noun of a stored name is not a name. "Utility Expenses" offered
       "expenses" as a cue, so the word that says what the question is *about*
       was read as the slice it was about — and "expenses in the Dhaka office"
       came back as the utility bill, filtered, confidently, by the subject of
       the sentence. A single word only narrows when it could not also be the
       subject. */
    private const GENERIC = [
        'expense', 'expenses', 'expenditure', 'cost', 'costs', 'spend', 'spending',
        'sale', 'sales', 'revenue', 'income', 'turnover', 'profit', 'loss',
        'salary', 'salaries', 'payroll', 'wage', 'wages', 'purchase', 'purchases',
        'payment', 'payments', 'account', 'accounts', 'balance', 'total', 'others',
        'general', 'miscellaneous', 'misc', 'service', 'services', 'operations',
        'management', 'solutions', 'consultancy', 'interiors', 'production',
    ];

    private static function cuesFor(string $name, string $short): array
    {
        $cues = [mb_strtolower($name, 'UTF-8')];
        if ($short !== '' && mb_strtolower($short, 'UTF-8') !== $cues[0]) $cues[] = mb_strtolower($short, 'UTF-8');

        // the name with the house word taken off — "epal travels & consultancy" → "travels & consultancy"
        $words = preg_split('/[\s&,\/]+/u', mb_strtolower($name, 'UTF-8')) ?: [];
        $keep = [];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '' || in_array($w, self::HOUSE, true)) continue;
            $keep[] = $w;
        }
        // "Group Office" loses its house word and what is left is the marker word
        // "office" — which is how "expenses in the Dhaka office" bound to the Group
        // Office. A fragment made only of markers names nothing.
        $markers = array_merge(self::UNIT_EN, self::DEPT_WORD_EN, self::UNIT_BN, self::DEPT_WORD_BN, self::GENERIC);
        $solid = array_values(array_filter($keep, fn($w) => !in_array($w, $markers, true)));
        if ($keep !== [] && $solid !== [] && count($keep) < count($words)) $cues[] = implode(' ', $keep);

        /* And each distinctive single word on its own — except the words that mark
           a unit rather than name one. "Office Management" offered "office" as a cue,
           so "expenses in the Dhaka office" bound to that department and EON answered
           about the whole company while claiming a department filter. A marker word
           belongs to the phrase around it, never to a name that happens to contain it. */
        $markers = array_merge(self::UNIT_EN, self::DEPT_WORD_EN, self::UNIT_BN, self::DEPT_WORD_BN, self::GENERIC);
        foreach ($keep as $w) {
            if (in_array($w, $markers, true)) continue;
            if (mb_strlen($w, 'UTF-8') >= 4 || in_array($w, self::KEEP_SHORT, true)) $cues[] = $w;
        }
        return array_values(array_unique(array_filter($cues, fn($c) => $c !== '')));
    }

    /* ---------------- unresolvable: it is not in the data ---------------- */

    private static function matchUnknown(string $raw, string $n, string $lang): ?array
    {
        // "<Word> branch", "<Word> office", "<Word> district"
        $units = implode('|', self::UNIT_EN);
        if (preg_match('/(?<![a-z])([a-z][a-z\'-]{2,})\s+(?:' . $units . ')(?![a-z])/u', $n, $m)
            && !self::isStop($m[1])) {
            return self::unknown($m[1], $raw);
        }
        // বাংলা marks the unit the same way — "চট্টগ্রাম শাখায়"
        foreach (self::UNIT_BN as $u) {
            if (preg_match('/([\x{0980}-\x{09FF}]{3,})\s*' . $u . '/u', $n, $m)) {
                return self::unknown($m[1], $raw);
            }
        }
        // "in Chittagong" — English marks a place by capitalising it, and the
        // preposition is what makes it a filter rather than a subject
        if (preg_match('/(?<![A-Za-z])(?:in|at|for|from|within|across)\s+([A-Z][a-zA-Z\'-]{2,}(?:\s+[A-Z][a-zA-Z\'-]{2,})?)/u', $raw, $m)) {
            $cand = trim($m[1]);
            $first = mb_strtolower(explode(' ', $cand)[0], 'UTF-8');
            if (!self::isStop($first)) return self::unknown($cand, $raw);
        }
        return null;
    }

    /**
     * A consonant skeleton that survives English spelling as well as script.
     *
     * Grammar's skeleton is built for names and stops at the vowels and the
     * digraph h. That is not enough here: বাংলা টিকিটিং romanises to "tikiting"
     * → tktng, while English "ticketing" keeps its silent c → tcktng, and the
     * two never meet. English spells one sound several ways — ck, ph, q, x, and
     * any doubled letter — so those are folded before the vowels come out.
     */
    private static function skel(string $s): array
    {
        /* বাংলা writes the English "a" of *account* as অ্যা, which romanises letter
           by letter to "oja" and takes the word to jknts where accounts is cnts.
           The cluster is a spelling of one borrowed vowel, so it is folded before
           the letters are read individually. */
        $s = strtr($s, ["\u{0985}\u{09CD}\u{09AF}\u{09BE}" => 'a', "\u{0985}\u{09CD}\u{09AF}" => 'a']);
        $s = class_exists('Grammar') ? Grammar::romanise($s) : mb_strtolower($s, 'UTF-8');
        /* c is /k/ in every word this ERP stores — accounts, ticketing, construction —
           and বাংলা writes that sound ক, which romanises to k. Left apart, অ্যাকাউন্টস
           reduced to knts and "Accounts" to cnts, and the two never met. */
        $s = strtr($s, ['ck' => 'k', 'c' => 'k', 'ph' => 'f', 'gh' => 'g', 'q' => 'k', 'x' => 'ks', 'z' => 'j']);
        $s = preg_replace('/(.)\1+/', '$1', $s) ?? $s;          // "tt" and "ss" are one sound
        $out = [];
        foreach (preg_split('/\s+/', trim($s)) ?: [] as $w) {
            $k = preg_replace('/[aeiouhwy]+/', '', $w) ?? $w;
            if (strlen($k) >= 2) $out[] = $k;
        }
        return $out;
    }

    private static function isStop(string $w): bool
    {
        return in_array(mb_strtolower(trim($w), 'UTF-8'), self::STOP, true);
    }

    private static function unknown(string $text, string $raw): array
    {
        // give it back the way the boss wrote it, capitals and all
        $label = $text;
        if (preg_match('/(?<![A-Za-z])(' . preg_quote($text, '/') . ')(?![A-Za-z])/iu', $raw, $m)) $label = $m[1];
        return ['text' => $text, 'dim' => null, 'key' => null, 'label' => $label, 'resolved' => false];
    }

    /* ---------------- applying a resolved scope ---------------- */

    /** does this row belong to the scope? */
    public static function keeps(array $scope, array $row): bool
    {
        if (!($scope['resolved'] ?? false)) return true;
        $key = (string) $scope['key'];
        return match ($scope['dim']) {
            'company'            => (string) ($row['company_id'] ?? '') === $key,
            'department'         => self::same((string) ($row['department'] ?? ''), $key),
            'expense_department' => self::same((string) ($row['department'] ?? ''), $key),
            'category'           => self::same((string) ($row['category'] ?? ''), $key),
            default              => true,
        };
    }

    private static function same(string $a, string $b): bool
    {
        return mb_strtolower(trim($a), 'UTF-8') === mb_strtolower(trim($b), 'UTF-8');
    }

    /** filter a list of rows to the scope */
    public static function rows(array $scope, array $rows): array
    {
        if (!($scope['resolved'] ?? false)) return $rows;
        return array_values(array_filter($rows, fn($r) => is_array($r) && self::keeps($scope, $r)));
    }

    /** which dimensions can this dataset actually be narrowed by? for the honest refusal */
    public static function dimensions(?array $D, string $lang): string
    {
        $bn = $lang === 'bn';
        $has = [];
        if (!empty($D['companies']))   $has[] = $bn ? 'কোম্পানি' : 'company';
        if (!empty($D['departments'])) $has[] = $bn ? 'বিভাগ' : 'department';
        if (!empty($D['expenses']))    $has[] = $bn ? 'খরচের খাত' : 'expense category';
        if ($has === []) return $bn ? 'কোনোটাই নয়' : 'nothing';
        return Phrase::join($has, $lang);
    }
}
