<?php
/* ============================================================
   EON · SchemaSense — EON knows what the ERP's data IS.

   Reads server/kb/erp-schema.json (tools/schema-sense.php: every table,
   every column, the role each column plays, the status vocabularies the
   data really holds, row counts, date ranges, who references whom) and
   answers the questions an analyst asks about a database, in English
   and বাংলা:

     where      "where are ticket refunds stored"   "রিফান্ড কোন টেবিলে থাকে"
     values     "what statuses can a visa have"      "ভিসার স্ট্যাটাস কী কী"
     column     "what is rbd in ticket purchases"    "bearer কলাম মানে কী"
     links      "what links to passport holders"     "পাসপোর্ট হোল্ডারের সাথে কী কী যুক্ত"
     describe   "describe the contract files table"  "কন্ট্রাক্ট ফাইল টেবিলে কী আছে"
     count      "how many rows in ticket changes"    "ভিসা টেবিলে কয়টা রেকর্ড"

   It takes a question only when the question is about the data's shape
   — table, column, stored, statuses, রেকর্ড, টেবিল — so "how many tickets
   did we sell" stays with the figure answerers. Below a weak answer it is
   also a floor: a question nothing else could place may still name a
   table.
   ============================================================ */
final class SchemaSense
{
    private static ?array $S = null;

    /* বাংলা and Banglish nouns → the English the table names are written in */
    private const GLOSS = [
        'টিকিট' => 'ticket', 'টিকেট' => 'ticket', 'রিফান্ড' => 'refund', 'ফেরত' => 'refund', 'রিইস্যু' => 'reissue', 'ভয়েড' => 'void',
        'ভিসা' => 'visa', 'বেতন' => 'salary', 'স্যালারি' => 'salary', 'কর্মী' => 'user', 'কর্মচারী' => 'user', 'স্টাফ' => 'user', 'ইউজার' => 'user',
        'পার্টি' => 'party', 'এজেন্ট' => 'agent', 'ভেন্ডর' => 'vendor', 'ক্লায়েন্ট' => 'client', 'কাস্টমার' => 'customer',
        'ব্যাংক' => 'bank', 'খরচ' => 'expense', 'ব্যয়' => 'expense', 'জার্নাল' => 'journal', 'খতিয়ান' => 'journal', 'হাজিরা' => 'attendance', 'ছুটি' => 'leave',
        'পাসপোর্ট' => 'passport', 'যাত্রী' => 'passenger', 'ফ্লাইট' => 'flight', 'কন্ট্রাক্ট' => 'contract', 'ফাইল' => 'file', 'ইনভয়েস' => 'invoice',
        'পেমেন্ট' => 'payment', 'লোন' => 'loan', 'ঋণ' => 'loan', 'অগ্রিম' => 'advance', 'টাস্ক' => 'task', 'কাজ' => 'task', 'কোম্পানি' => 'company',
        'অ্যাকাউন্ট' => 'account', 'হিসাব' => 'account', 'লেজার' => 'ledger', 'বিক্রি' => 'sale', 'বিক্রয়' => 'sale', 'ক্রয়' => 'purchase', 'কেনা' => 'purchase',
        'নোটিশ' => 'notice', 'লিড' => 'lead', 'ডিসকাউন্ট' => 'discount', 'শিডিউল' => 'schedule', 'ক্যাশ' => 'cash', 'নগদ' => 'cash', 'হোল্ডার' => 'holder',
        'বুকিং' => 'booking', 'প্রজেক্ট' => 'project', 'বিভাগ' => 'department', 'ডিপার্টমেন্ট' => 'department', 'রুট' => 'route', 'এয়ারলাইন' => 'airline',
        'বেতনের' => 'salary', 'ভিসার' => 'visa', 'টিকিটের' => 'ticket', 'ফাইলের' => 'file', 'যাত্রীর' => 'passenger', 'পার্টির' => 'party',
        'betan' => 'salary', 'beton' => 'salary', 'khoroch' => 'expense', 'hajira' => 'attendance', 'chuti' => 'leave', 'jatri' => 'passenger',
    ];
    private const STOP = ['the', 'a', 'an', 'of', 'in', 'on', 'for', 'to', 'is', 'are', 'be', 'can', 'what', 'which', 'where', 'how', 'many', 'much', 'does',
        'do', 'we', 'our', 'erp', 'eon', 'table', 'tables', 'column', 'columns', 'field', 'fields', 'stored', 'store', 'stores', 'kept', 'keep', 'keeps',
        'saved', 'save', 'data', 'database', 'schema', 'record', 'records', 'row', 'rows', 'have', 'has', 'hold', 'holds', 'it', 'its', 'there', 'about',
        'tell', 'me', 'describe', 'explain', 'mean', 'means', 'meaning', 'possible', 'values', 'value', 'statuses', 'status', 'stages', 'stage', 'kinds',
        'kind', 'types', 'type', 'links', 'link', 'linked', 'refers', 'refer', 'references', 'connected', 'with', 'and', 'or', 'all', 'list', 'show',
        'kon', 'kothay', 'ki', 'koto', 'gula', 'gulo', 'thake', 'rakha', 'hoy', 'er', 'e', 'ar', 'kono',
        'কী', 'কি', 'কোন', 'কোনো', 'আছে', 'থাকে', 'হয়', 'হয়', 'মানে', 'কত', 'কয়টা', 'কয়টা', 'টেবিল', 'টেবিলে', 'টেবিলের', 'কলাম', 'কলামে', 'এর', 'ও', 'এবং',
        'সব', 'গুলো', 'রাখা', 'কোথায়', 'কোথায়', 'স্ট্যাটাস', 'অবস্থা', 'রেকর্ড', 'সাথে', 'যুক্ত', 'ডাটাবেস', 'ডেটাবেস', 'আর', 'কে', 'কার', 'বলো', 'দেখাও'];

    public static function load(): array
    {
        if (self::$S !== null) return self::$S;
        $f = dirname(__DIR__) . '/kb/erp-schema.json';
        self::$S = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        return self::$S;
    }

    /** a question plainly about the data's shape */
    public static function strong(string $q, ?string $lang = null): ?array
    {
        if (!preg_match('/\b(table|tables|column|columns|field|fields|database|schema|stored|store[sd]?|kept|saved|records?|rows?|statuses|status values|possible status|kinds of|types of|stages of|what (statuses|stages|kinds|types|methods|modes|values)|links? to|refers? to|referenc|foreign key|data ?type)\b|টেবিল|কলাম|ডাটাবেস|ডেটাবেস|ফিল্ড|কোথায় রাখা|কোথায় থাকে|কোথায় সেভ|রেকর্ড|স্ট্যাটাস কী কী|স্ট্যাটাস কি কি|কী কী অবস্থা|ধরন|যুক্ত|সংযুক্ত/iu', $q)) return null;
        return self::answer($q, $lang, 1.2);
    }

    /** "what is rbd in ticket purchases": no schema word, but a column of a named table */
    public static function columnQuestion(string $q, ?string $lang = null): ?array
    {
        if (empty(self::load()['tables'])) return null;
        $words = self::words($q);
        [$table, $sc] = self::table($words);
        $dotted = preg_match('/\b[a-z_]+\.[a-z_]+\b/', $q);
        if (!$dotted && (!$table || $sc < 2)) return null;
        $col = self::column(mb_strtolower($q), $words, $dotted ? null : $table);
        if (!$col || (!preg_match('/\b(what|mean|means|meaning|is)\b|মানে|কী|কি/iu', $q) && !preg_match('/\b[a-z_]+\.[a-z_]+\b/', $q))) return null;
        $bn = $lang === 'bn' || ($lang === null && preg_match('/\p{Bengali}/u', $q) && self::bnShare($q));
        return self::describeColumn($col[0], $col[1], $bn);
    }

    public static function answer(string $q, ?string $lang = null, float $min = 2.0): ?array
    {
        $S = self::load();
        if (empty($S['tables'])) return null;
        $bn = $lang === 'bn' || ($lang === null && preg_match('/\p{Bengali}/u', $q) && self::bnShare($q));
        $l = mb_strtolower($q);
        $words = self::words($q);
        if (!$words) return null;

        // a column named outright: "rbd", "bearer column", "ticket_purchases.rbd"
        [$table, $tScore] = self::table($words);
        $col = self::column($l, $words, $table);

        if (preg_match('/\b(links?|linked|refers?|references|connected|points? to|depends?)\b|যুক্ত|সংযুক্ত|সম্পর্ক|লিংক/iu', $q) && $table) return self::links($table, $bn);
        if (preg_match('/\b(status|statuses|stages?|kinds?|types?|methods?|modes?|values?|options?|categories)\b|স্ট্যাটাস|অবস্থা|ধরন|প্রকার|স্টেজ|কী কী|কি কি/iu', $q) && $table && !$col) {
            $v = self::values($table, $l, $bn, $words);
            if ($v) return $v;
        }
        if ($col) return self::describeColumn($col[0], $col[1], $bn);
        /* "where are ticket refunds stored": no table is called refunds (or the one that
           is, is empty) — but a refund is a VALUE: ticket_changes.kind = refund. The data
           itself says where a thing lives. */
        $empty = $table && (self::load()['tables'][$table]['rows'] ?? 0) === 0;
        if (!$table || $tScore < $min || $empty) {
            $hit = self::byValue($words);
            if ($hit) return self::whereValue($hit, $bn);
        }
        if (!$table || $tScore < $min) return null;
        if (preg_match('/\b(how many|count|number of|rows?|records?)\b|কয়টা|কয়টা|কতগুলো|কত রেকর্ড|রেকর্ড/iu', $q)) return self::count($table, $bn);
        if (preg_match('/\b(where|which table|stored|kept|saved|keep|store|kothay)\b|কোথায়|কোথায়|কোন টেবিল/iu', $q)) return self::where($table, $words, $bn);
        return self::describe($table, $bn);
    }

    /* ---------- reading the question ---------- */
    private static function bnShare(string $q): bool
    {
        $w = preg_split('/\s+/u', trim($q)) ?: [];
        $b = count(array_filter($w, fn($x) => preg_match('/\p{Bengali}/u', $x)));
        $e = count(array_filter($w, fn($x) => preg_match('/[A-Za-z]{2,}/', $x) && !preg_match('/\p{Bengali}/u', $x)));
        return $b * 2 >= $e;
    }
    private static function words(string $q): array
    {
        $out = [];
        // keep vowel signs: \p{L} alone shatters বাংলা words (\p{M} is the trap this codebase has already paid for)
        foreach (preg_split('/[^\p{L}\p{M}\p{N}_.]+/u', mb_strtolower($q)) ?: [] as $w) {
            if ($w === '') continue;
            foreach (self::GLOSS as $bnw => $en) if ($w === $bnw || (mb_strlen($bnw) >= 3 && str_starts_with($w, $bnw))) { $w = $en; break; }
            if (in_array($w, self::STOP, true) || mb_strlen($w) < 2) continue;
            $out[] = $w;
        }
        return $out;
    }
    private static function stem(string $w): string
    {
        if (preg_match('/ies$/', $w)) return substr($w, 0, -3) . 'y';
        if (preg_match('/(ss|x|ch|sh|us)es$/', $w)) return substr($w, 0, -2);
        if (preg_match('/[^s]s$/', $w)) return substr($w, 0, -1);
        return $w;
    }
    /** the table a question is about: its name's words against the question's */
    private static function table(array $words): array
    {
        $S = self::load();
        $stems = array_map([self::class, 'stem'], $words);
        $best = null; $score = 0;
        foreach ($S['tables'] as $t => $e) {
            $parts = array_map([self::class, 'stem'], explode('_', $t));
            $hit = 0;
            foreach ($stems as $s) if (in_array($s, $parts, true)) $hit += 2;
            if ($hit === 0) continue;
            $miss = count($parts) - (int) ($hit / 2);
            // the name said in full beats a longer table that merely contains it; real data beats an empty table
            $sc = $hit - 0.6 * $miss + (($e['rows'] ?? 0) > 0 ? 0.3 + 0.1 * log10(1 + ($e['rows'] ?? 0)) : 0) + (in_array(implode('_', $stems), [implode('_', $parts)], true) ? 2 : 0);
            if ($sc > $score) { $score = $sc; $best = $t; }
        }
        return [$best, $score];
    }
    /** a column named in the question — exactly, or with its underscores spoken as spaces */
    private static function column(string $l, array $words, ?string $table): ?array
    {
        $S = self::load();
        if (preg_match('/\b([a-z_]+)\.([a-z_]+)\b/', $l, $m) && isset($S['tables'][$m[1]]['columns'][$m[2]])) return [$m[1], $m[2]];
        $cands = [];
        $look = $table ? [$table => $S['tables'][$table]] : $S['tables'];
        foreach ($look as $t => $e) foreach ($e['columns'] as $c => $col) {
            if (in_array($c, ['id', 'created_at', 'updated_at', 'deleted_at', 'status', 'type', 'name'], true) && !preg_match('/\b' . preg_quote($c, '/') . '\b.*\b(column|field)\b|\b(column|field)\b.*\b' . preg_quote($c, '/') . '\b/', $l)) continue;
            $spoken = str_replace('_', ' ', $c);
            if (strlen($c) >= 3 && (preg_match('/\b' . preg_quote($c, '/') . '\b/', $l) || (str_contains($c, '_') && str_contains($l, $spoken)))) $cands[] = [$t, $c, strlen($c)];
        }
        if (!$cands) return null;
        usort($cands, fn($a, $b) => $b[2] <=> $a[2]);
        // without a table named, a column found in many tables is not a column question
        if (!$table) { $same = array_filter($cands, fn($x) => $x[1] === $cands[0][1]); if (count($same) > 2) return null; }
        return [$cands[0][0], $cands[0][1]];
    }

    /* ---------- the answers ---------- */
    private const ROLE_BN = ['key' => 'মূল চাবি', 'reference' => 'অন্য টেবিলের সংযোগ', 'money' => 'টাকার অঙ্ক', 'number' => 'সংখ্যা', 'date' => 'তারিখ',
        'time' => 'সময়', 'status' => 'অবস্থা বা ধরন', 'flag' => 'হ্যাঁ/না চিহ্ন', 'label' => 'নাম', 'reference_no' => 'রেফারেন্স নম্বর', 'contact' => 'যোগাযোগ',
        'identity' => 'পরিচয়পত্র', 'text' => 'লেখা', 'json' => 'গঠিত তথ্য (JSON)', 'file' => 'ফাইল', 'secret' => 'গোপন — EON পড়ে না', 'value' => 'মান'];
    private const ROLE_EN = ['key' => 'primary key', 'reference' => 'link to another table', 'money' => 'money amount', 'number' => 'number', 'date' => 'date',
        'time' => 'time of day', 'status' => 'status / kind', 'flag' => 'yes/no flag', 'label' => 'name', 'reference_no' => 'reference number', 'contact' => 'contact detail',
        'identity' => 'identity document', 'text' => 'free text', 'json' => 'structured data (JSON)', 'file' => 'file', 'secret' => 'secret — EON never reads it', 'value' => 'value'];

    private static function n($x, bool $bn): string
    {
        $s = number_format((float) $x, 0, '.', ',');
        return $bn ? strtr($s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']) : $s;
    }
    private static function d(string $x, bool $bn): string
    {
        return $bn ? strtr($x, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']) : $x;
    }
    private static function out(string $text, bool $bn, string $intent): array
    {
        return ['text' => $text, 'lang' => $bn ? 'bn' : 'en', 'intent' => 'schema_' . $intent];
    }
    private static function rowsOf(array $e, bool $bn): string
    {
        $live = $e['live_rows'] ?? null;
        if ($bn) return self::n($e['rows'], true) . 'টি রেকর্ড' . ($live !== null && $live !== $e['rows'] ? ' (চালু ' . self::n($live, true) . 'টি, বাকিগুলো মুছে ফেলা)' : '');
        return self::n($e['rows'], false) . ' rows' . ($live !== null && $live !== $e['rows'] ? ' (' . self::n($live, false) . ' live, the rest deleted)' : '');
    }

    private static function describe(string $t, bool $bn): array
    {
        $e = self::load()['tables'][$t];
        $by = [];
        foreach ($e['columns'] as $c => $col) $by[$col['role']][] = $c;
        $range = isset($e['range']) ? ($bn ? '; সময়কাল ' . self::d($e['range'][0], true) . ' থেকে ' . self::d($e['range'][1], true) : '; dated ' . $e['range'][0] . ' to ' . $e['range'][1]) : '';
        $money = array_slice($by['money'] ?? [], 0, 6);
        $status = array_slice($by['status'] ?? [], 0, 5);
        $refs = array_slice(array_map(fn($c) => $c . '→' . ($e['columns'][$c]['to'] ?? '?'), $by['reference'] ?? []), 0, 6);
        if ($bn) {
            $t2 = "{$t} টেবিলে " . self::rowsOf($e, true) . $range . '। ' . self::n(count($e['columns']), true) . 'টি কলাম।';
            if ($money) $t2 .= ' টাকার কলাম: ' . implode(', ', $money) . '।';
            if ($status) $t2 .= ' অবস্থা/ধরন: ' . implode(', ', $status) . '।';
            if ($refs) $t2 .= ' সংযোগ: ' . implode(', ', $refs) . '।';
            return self::out($t2, true, 'describe');
        }
        $t2 = "{$t} holds " . self::rowsOf($e, false) . $range . ', in ' . count($e['columns']) . ' columns.';
        if ($money) $t2 .= ' Money: ' . implode(', ', $money) . '.';
        if ($status) $t2 .= ' Status/kind: ' . implode(', ', $status) . '.';
        if ($refs) $t2 .= ' Links: ' . implode(', ', $refs) . '.';
        return self::out($t2, false, 'describe');
    }

    private static function where(string $t, array $words, bool $bn): array
    {
        $e = self::load()['tables'][$t];
        $parts = array_map([self::class, 'stem'], explode('_', $t));
        $extra = array_values(array_filter(array_map([self::class, 'stem'], $words), fn($w) => !in_array($w, $parts, true)));
        $cols = [];
        foreach ($e['columns'] as $c => $col) foreach ($extra as $w) if (str_contains($c, $w)) { $cols[] = $c; break; }
        $also = [];
        foreach (self::load()['tables'] as $o => $oe) {
            if ($o === $t) continue;
            foreach ($oe['columns'] as $c => $col) if ($parts && str_contains($c, implode('_', $parts)) && in_array($col['role'], ['money', 'date', 'status', 'flag'], true)) { $also[] = "$o.$c"; break; }
            if (count($also) >= 3) break;
        }
        if ($bn) return self::out("এটা থাকে {$t} টেবিলে (" . self::rowsOf($e, true) . ')' . ($cols ? '; বিশেষ করে ' . implode(', ', array_slice($cols, 0, 4)) . ' কলামে' : '') . '।' . ($also ? ' সংশ্লিষ্ট: ' . implode(', ', $also) . '।' : ''), true, 'where');
        return self::out("That is kept in {$t} (" . self::rowsOf($e, false) . ')' . ($cols ? ', in ' . implode(', ', array_slice($cols, 0, 4)) : '') . '.' . ($also ? ' Related: ' . implode(', ', $also) . '.' : ''), false, 'where');
    }

    private static function count(string $t, bool $bn): array
    {
        $e = self::load()['tables'][$t];
        $when = self::load()['generated'] ?? '';
        $asof = $when ? substr($when, 0, 10) : '';
        if ($bn) return self::out("{$t} টেবিলে " . self::rowsOf($e, true) . ($asof ? ' (' . self::d($asof, true) . ' তারিখের হিসাবে)' : '') . '।', true, 'count');
        return self::out("{$t} has " . self::rowsOf($e, false) . ($asof ? " as of {$asof}" : '') . '.', false, 'count');
    }

    private static function values(string $t, string $l, bool $bn, array $words = []): ?array
    {
        /* which vocabulary was asked for: "statuses" → status, "স্টেজ" → stage,
           "kinds"/"ধরন" → kind or type, "methods" → method */
        $asked = preg_match('/\bstatus(es)?\b|স্ট্যাটাস|অবস্থা/u', $l) ? ['status']
            : (preg_match('/\bstages?\b|স্টেজ|পর্যায়/u', $l) ? ['stage', 'status']
            : (preg_match('/\b(kinds?|types?)\b|ধরন|প্রকার/u', $l) ? ['kind', 'type']
            : (preg_match('/\b(methods?|modes?)\b/', $l) ? ['method', 'mode'] : [])));
        $find = function (string $table) use ($l, $asked): ?string {
            $e = self::load()['tables'][$table];
            $pick = null; $byAsk = null;
            foreach ($e['columns'] as $c => $col) {
                if (($col['role'] ?? '') !== 'status' || empty($col['values'])) continue;
                if (preg_match('/\b' . preg_quote(str_replace('_', ' ', $c), '/') . '(es|s)?\b|\b' . preg_quote($c, '/') . '\b/', $l)) return $c;
                // "status" itself beats payment_status; a *_status column only when there is no plain one
                foreach ($asked as $a) {
                    if ($c === $a && ($byAsk === null || $byAsk !== $a)) $byAsk = $c;
                    elseif ($byAsk === null && str_ends_with($c, '_' . $a)) $byAsk = $c;
                }
                $pick = $pick ?? $c;
            }
            return $byAsk ?? ($asked ? null : $pick);
        };
        $pick = $find($t);
        /* the table named first may not carry that vocabulary — "ভিসার স্ট্যাটাস" is the
           visa PROCESS's status, not the visa category's — so look among the tables
           the subject names for the one that does, the busiest first */
        if (!$pick && $words) {
            $stems = array_map([self::class, 'stem'], $words);
            $alts = [];
            foreach (self::load()['tables'] as $o => $oe) {
                $parts = array_map([self::class, 'stem'], explode('_', $o));
                if (!array_intersect($stems, $parts)) continue;
                $c = $find($o);
                if ($c) $alts[] = [$o, $c, $oe['rows'] ?? 0];
            }
            usort($alts, fn($a, $b) => $b[2] <=> $a[2]);
            if ($alts) { [$t, $pick] = [$alts[0][0], $alts[0][1]]; }
        }
        if (!$pick) return null;
        $e = self::load()['tables'][$t];
        $vals = $e['columns'][$pick]['values'];
        $list = implode(', ', array_map(fn($v) => $v[0] . ' (' . self::n($v[1], $bn) . ')', $vals));
        if ($bn) return self::out("{$t}.{$pick}-এ ডেটায় যে মানগুলো আছে: {$list}।", true, 'values');
        return self::out("{$t}.{$pick} holds these values in the data: {$list}.", false, 'values');
    }

    /** a word of the question that is a VALUE somewhere: "refund" in ticket_changes.kind */
    private static function byValue(array $words): ?array
    {
        $stems = array_map([self::class, 'stem'], $words);
        $best = null;
        foreach (self::load()['tables'] as $t => $e) {
            if (($e['rows'] ?? 0) === 0) continue;
            $tparts = array_map([self::class, 'stem'], explode('_', $t));
            foreach ($e['columns'] as $c => $col) {
                if (($col['role'] ?? '') !== 'status' || empty($col['values'])) continue;
                foreach ($col['values'] as [$v, $n]) {
                    if (!in_array(self::stem(mb_strtolower((string) $v)), $stems, true)) continue;
                    // the rest of the question naming the table ("ticket" refunds) decides between several
                    $sc = $n + 1000 * count(array_intersect($stems, $tparts));
                    if (!$best || $sc > $best[4]) $best = [$t, $c, (string) $v, $n, $sc];
                }
            }
        }
        return $best;
    }
    private static function whereValue(array $h, bool $bn): array
    {
        [$t, $c, $v, $n] = $h;
        if ($bn) return self::out("এগুলো আলাদা টেবিলে নয় — {$t} টেবিলে থাকে, যেখানে {$c} = {$v} (" . self::n($n, true) . 'টি রেকর্ড)।', true, 'where');
        return self::out("Those are not a table of their own — they are rows of {$t} where {$c} = {$v} (" . self::n($n, false) . ' rows).', false, 'where');
    }

    private static function describeColumn(string $t, string $c, bool $bn): array
    {
        $col = self::load()['tables'][$t]['columns'][$c];
        $role = $col['role'];
        $bits = [];
        if ($bn) {
            $bits[] = "{$t}.{$c} — " . (self::ROLE_BN[$role] ?? $role) . " ({$col['type']})";
            if (isset($col['to'])) $bits[] = "{$col['to']} টেবিলের দিকে নির্দেশ করে";
            if (isset($col['polymorphic'])) $bits[] = "কোন টেবিল তা {$col['polymorphic']} কলাম বলে দেয়";
            if (isset($col['filled'])) $bits[] = self::n(round($col['filled'] * 100), true) . '% সারিতে পূরণ করা';
            if (isset($col['values'])) $bits[] = 'মান: ' . implode(', ', array_map(fn($v) => $v[0], array_slice($col['values'], 0, 10)));
            if (isset($col['range'])) $bits[] = self::d($col['range'][0], true) . ' থেকে ' . self::d($col['range'][1], true);
            if (isset($col['sum'])) $bits[] = 'মোট ৳' . self::n($col['sum'], true);
            return self::out(implode('; ', $bits) . '।', true, 'column');
        }
        $bits[] = "{$t}.{$c} is a " . (self::ROLE_EN[$role] ?? $role) . " ({$col['type']})";
        if (isset($col['to'])) $bits[] = "pointing at {$col['to']}";
        if (isset($col['polymorphic'])) $bits[] = "the table it points at is named in {$col['polymorphic']}";
        if (isset($col['filled'])) $bits[] = 'filled on ' . round($col['filled'] * 100) . '% of rows';
        if (isset($col['values'])) $bits[] = 'values: ' . implode(', ', array_map(fn($v) => $v[0], array_slice($col['values'], 0, 10)));
        if (isset($col['range'])) $bits[] = 'from ' . $col['range'][0] . ' to ' . $col['range'][1];
        if (isset($col['sum'])) $bits[] = 'totalling ৳' . self::n($col['sum'], false);
        return self::out(implode('; ', $bits) . '.', false, 'column');
    }

    private static function links(string $t, bool $bn): array
    {
        $e = self::load()['tables'][$t];
        $in = $e['referenced_by'] ?? [];
        $out = [];
        foreach ($e['columns'] as $c => $col) if (($col['role'] ?? '') === 'reference' && !empty($col['to'])) $out[] = "{$c}→{$col['to']}";
        $inTables = array_values(array_unique(array_map(fn($x) => explode('.', $x)[0], $in)));
        if ($bn) return self::out("{$t}-কে " . self::n(count($inTables), true) . 'টি টেবিল নির্দেশ করে' . ($inTables ? ': ' . implode(', ', array_slice($inTables, 0, 12)) . (count($inTables) > 12 ? ' …' : '') : '') . '।' . ($out ? " আর {$t} নিজে যুক্ত: " . implode(', ', $out) . '।' : ''), true, 'links');
        return self::out(count($inTables) . " tables point at {$t}" . ($inTables ? ': ' . implode(', ', array_slice($inTables, 0, 12)) . (count($inTables) > 12 ? ' …' : '') : '') . '.' . ($out ? " {$t} itself links to " . implode(', ', $out) . '.' : ''), false, 'links');
    }
}
