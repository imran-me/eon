<?php
declare(strict_types=1);

/* ============================================================
   KbCorpus — retrieval over the ERP knowledge corpus.

   `eon_kb` holds 101,483 records about what an ERP means in 24
   trades. This turns a question into one of them, or into nothing
   at all, which matters more: the whole point of the offline brain
   is that it says "I do not know" rather than inventing a
   paragraph, and a retrieval layer that always returns its
   best-scoring row would quietly destroy that.

   How it finds one, in order:

   1. **Route through the question bank.** `eon_kb_questions` is not
      test data — it is a labelled index into the corpus: 188,885
      questions, each carrying the vertical, module, subject and
      *kind of record* that answers it. Matching against that
      answers what searching cannot — not "which record mentions
      these words" but "what kind of answer is being asked for" —
      and it is the only path that reaches Banglish, which `eon_kb`
      does not contain in any script.

   2. **Then tags, not text.** The bank's answer is three indexed
      columns, so the slice it leaves is tens of rows.

   3. **A title LIKE inside the scoped slice, as a safety net.**

   Not used: a fulltext index on `eon_kb`. It was the obvious
   design and it is measurably wrong here — one term 0.69s, three
   terms 3.0s, twelve terms 15.2s on MariaDB 10.4, because ORDER BY
   relevance scores every matching row and "invoice" matches tens of
   thousands of them. The schema no longer creates it. The bank's
   own index is a different proposition (a 400-character question,
   not a document) and answers in 10–25ms.

   The rule none of it breaks: **answer in the language asked.** A
   বাংলা question is searched against বাংলা records only, and
   Banglish is answered in বাংলা, because the rest of this codebase
   is measured on that and a corpus is not a reason to stop.
   ============================================================ */

final class KbCorpus
{
    /** below this, a match is not worth showing */
    private const MIN_SCORE = 0.6;

    /** facets that answer a "what/how/why" question well, best first */
    private const FACET_RANK = [
        'module_overview' => 1.35, 'kpi_definition' => 1.30, 'process' => 1.30,
        'entity' => 1.25, 'scenario' => 1.25, 'posting_rule' => 1.20,
        'remedy' => 1.20, 'kpi_reading' => 1.15, 'permission' => 1.15,
        'report' => 1.10, 'pitfall' => 1.10, 'kpi_action' => 1.10,
        'entity_lifecycle' => 1.05, 'integration' => 1.05, 'glossary' => 1.05,
        'entity_fields' => 1.0, 'relationship' => 1.0, 'role_matrix' => 1.0,
        'compliance' => 1.0, 'vertical_profile' => 1.0, 'vertical_kpi' => 0.95,
        'process_step' => 0.85, 'field' => 0.80, 'comparison' => 0.80,
        'answering_note' => 0.60,
    ];

    private static ?array $vocab = null;

    /* ---------------------------------------------------------- vocabulary */

    /** the 24 trades and 86 modules, from the file make-kb.mjs writes */
    public static function vocab(): array
    {
        if (self::$vocab !== null) return self::$vocab;
        $f = __DIR__ . '/kb-verticals.json';
        if (!is_file($f)) return self::$vocab = ['verticals' => [], 'modules' => []];
        $j = json_decode((string) file_get_contents($f), true);
        return self::$vocab = is_array($j) ? $j : ['verticals' => [], 'modules' => []];
    }

    /** is the corpus actually loaded? cheap enough to call per request */
    public static function available(): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try {
            $pdo = Db::eon();
            if (!Db::tableExists($pdo, 'eon_kb')) return $ok = false;
            return $ok = ((int) $pdo->query('SELECT 1 FROM eon_kb LIMIT 1')->fetchColumn() === 1);
        } catch (Throwable $e) {
            return $ok = false;
        }
    }

    /** which trade EON answers as when the question does not say */
    public static function defaultVertical(): string
    {
        $c = Config::get('kb');
        $v = is_array($c) ? (string) ($c['vertical'] ?? '') : '';
        return $v !== '' ? $v : 'travel';
    }

    /**
     * The trade this question is about.
     *
     * A question mentioning a trade wins over the configured default: the
     * same sentence has 24 answers, and "how does admissions work at a
     * university" must not be answered as a travel agency because that is
     * what the config says.
     */
    public static function detectVertical(string $q): ?string
    {
        $s = ' ' . mb_strtolower(trim($q)) . ' ';
        $best = null; $bestLen = 0;
        foreach (self::vocab()['verticals'] as $v) {
            /* The head word of the trade's own name, too. Nobody writes "a
               pharmacy chain" — they write "a pharmacy", which matched neither
               the name nor any alias, so the question was answered as a travel
               agency because that is the configured default. The full name still
               wins on length, so "medical college hospital" is unaffected. */
            $head = preg_split('/\s+/u', trim((string) $v['en']))[0] ?? '';
            $names = array_merge([$v['en'], $v['bn']], $v['aliases'] ?? []);
            if (mb_strlen($head) >= 5) $names[] = $head;
            foreach ($names as $name) {
                $n = mb_strtolower((string) $name);
                if ($n === '' || mb_strlen($n) < 3) continue;
                if (mb_strpos($s, $n) === false) continue;
                /* longest name wins — "medical college hospital" beats "hospital" */
                if (mb_strlen($n) > $bestLen) { $best = $v['id']; $bestLen = mb_strlen($n); }
            }
        }
        return $best;
    }

    /** the module a question is about, if it names one */
    public static function detectModule(string $q, ?string $vertical = null): ?string
    {
        $s = ' ' . mb_strtolower(trim($q)) . ' ';
        $allowed = null;
        if ($vertical !== null) {
            foreach (self::vocab()['verticals'] as $v) if ($v['id'] === $vertical) $allowed = $v['modules'] ?? null;
        }
        $best = null; $bestLen = 0;
        foreach (self::vocab()['modules'] as $id => $m) {
            if ($allowed !== null && !in_array($id, $allowed, true)) continue;
            foreach (array_merge([$m['en'], $m['bn']], $m['aliases'] ?? []) as $name) {
                $n = mb_strtolower((string) $name);
                if ($n === '' || mb_strlen($n) < 3) continue;
                if (mb_strpos($s, $n) === false) continue;
                if (mb_strlen($n) > $bestLen) { $best = $id; $bestLen = mb_strlen($n); }
            }
        }
        return $best;
    }

    /** bn for anything carrying Bengali script, else en */
    public static function langOf(string $q): string
    {
        return preg_match('/[\x{0980}-\x{09FF}]/u', $q) ? 'bn' : 'en';
    }

    /* ------------------------------------------------------------- search */

    /**
     * Ranked records for a question.
     *
     * @param array{vertical?:?string,module?:?string,lang?:?string,limit?:int,facet?:?string} $opts
     * @return array<int,array<string,mixed>>
     */
    public static function search(string $q, array $opts = []): array
    {
        if (!self::available()) return [];

        $lang     = $opts['lang']     ?? self::langOf($q);
        $vertical = array_key_exists('vertical', $opts) ? $opts['vertical'] : (self::detectVertical($q) ?? self::defaultVertical());
        $module   = array_key_exists('module', $opts)   ? $opts['module']   : self::detectModule($q, $vertical);
        $limit    = max(1, (int) ($opts['limit'] ?? 6));

        $terms = self::terms($q);
        if (!$terms) return [];

        try { $pdo = Db::eon(); } catch (Throwable $e) { return []; }

        /* Scope first. NULL vertical rows are the cross-trade ones and always
           belong in the slice. */
        $where = ['lang = ?'];
        $args  = [$lang];
        if ($vertical !== null) { $where[] = '(vertical = ? OR vertical IS NULL)'; $args[] = $vertical; }
        if (!empty($opts['facet'])) { $where[] = 'facet = ?'; $args[] = (string) $opts['facet']; }

        $rows = self::likeQuery($pdo, $where, $args, $terms, $limit * 8);
        if (!$rows) return [];

        /* Rank in PHP: SQL gave relevance, but whether a record answers the
           *kind* of question asked is not something MATCH can know. */
        $maxRel = 0.0;
        foreach ($rows as $r) $maxRel = max($maxRel, (float) ($r['rel'] ?? 0));
        $out = [];
        foreach ($rows as $r) {
            $rel   = $maxRel > 0 ? ((float) ($r['rel'] ?? 0)) / $maxRel : 0.0;
            $score = $rel * (self::FACET_RANK[$r['facet']] ?? 1.0);
            if ($module !== null && $r['module'] === $module) $score *= 1.4;
            if ($vertical !== null && $r['vertical'] === $vertical) $score *= 1.15;
            $score *= self::coverage($terms, ($r['title'] ?? '') . ' ' . ($r['body'] ?? ''));
            $r['score'] = round($score, 4);
            $out[] = $r;
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($out, 0, $limit);
    }

    /* The fulltext index on eon_kb is deliberately not used, and the schema no
       longer creates it. MEASURED on this corpus, MariaDB 10.4: a one-term
       BOOLEAN query takes 0.69s, three terms 3.0s and twelve terms 15.2s —
       ORDER BY relevance makes it score every matching row, and in a corpus
       where "invoice" appears in tens of thousands of records that is most of
       the table. The question bank's index is a different proposition (its
       column is a 400-character question, not a document) and stays.

       So retrieval is: route through the bank, and fall back to a title LIKE
       inside an already-narrow slice. */

    /**
     * The fallback the whole design depends on.
     *
     * Every token under innodb_ft_min_token_size is invisible to MATCH — which
     * in an ERP means VAT, GL, PAR, TDS, ADR and every two-letter বাংলা word.
     * The slice is a few thousand rows, so scanning it with LIKE is cheap and
     * is the difference between the corpus answering and the corpus sulking.
     */
    private static function likeQuery(PDO $pdo, array $where, array $args, array $terms, int $limit): array
    {
        $use = array_slice($terms, 0, 3);
        if (!$use) return [];

        /* Title only. A leading-wildcard LIKE across the MEDIUMTEXT body costs
           0.48s a call against this table — measured — and with one clause per
           term it was the whole of a 2.7-second answer. The body is already
           covered by the fulltext index; this fallback exists for the tokens
           that index cannot hold, and those are subject words, which live in
           the title. Ranking happens in PHP over the rows that come back. */
        $ors = [];
        foreach ($use as $t) { $ors[] = 'title LIKE ?'; $args[] = '%' . $t . '%'; }
        $where[] = '(' . implode(' OR ', $ors) . ')';

        $sql = 'SELECT id, facet, lang, vertical, module, subject, title, body, meta, 1 AS rel'
             . ' FROM eon_kb WHERE ' . implode(' AND ', $where)
             . ' LIMIT ' . (int) $limit;
        try {
            $st = $pdo->prepare($sql);
            $st->execute($args);
            return $st->fetchAll() ?: [];
        } catch (Throwable $e) {
            Log::warn('kb LIKE search failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /* ------------------------------------------------------------ answering */

    /**
     * Route through the question bank.
     *
     * `eon_kb_questions` is not test data — it is a labelled index into the
     * corpus. 188,885 questions, each carrying the vertical, module, subject
     * and *kind of record* that answers it. Matching an incoming question
     * against that is far better than searching the corpus directly, because
     * it answers the question searching cannot: not "which record mentions
     * these words" but "what kind of answer is being asked for".
     *
     * It is also the only thing that reaches Banglish. `eon_kb` holds English
     * and বাংলা; a romanised-Bangla question matches nothing in either. The
     * bank holds 30,316 Banglish questions, so routing through it turns a
     * script the corpus does not contain into a Bangla answer.
     *
     * @return array{vertical:?string,module:?string,facet:string,subject:?string,lang:string,q:string}|null
     */
    public static function route(string $q, ?string $lang = null): ?array
    {
        $lang = $lang ?: self::langOf($q);
        try { $pdo = Db::eon(); } catch (Throwable $e) { return null; }
        if (!Db::tableExists($pdo, 'eon_kb_questions')) return null;

        $terms = self::terms($q);
        if (!$terms) return null;

        /* Three tiers, narrowest first, and never ORDER BY relevance.
           MEASURED: the same query with OR semantics and ORDER BY rel takes
           0.70s, because MariaDB scores every row matching any term — and
           "invoice" matches thousands. Requiring the two rarest terms takes
           0.03s and returns four rows. Ranking then happens in PHP over a
           handful of candidates, which is where it belonged anyway.

           `terms()` returns longest-first, and length is a decent proxy for
           rarity: "reconciliation" narrows, "how" does not. */
        $rare = array_slice($terms, 0, 3);
        $tiers = [];
        if (count($rare) >= 2) $tiers[] = '+' . $rare[0] . '* +' . $rare[1] . '*';
        $tiers[] = '+' . $rare[0] . '*';
        $tiers[] = implode(' ', array_map(fn($t) => $t . '*', array_slice($terms, 0, 6)));

        /* Banglish is asked in Latin and answered in বাংলা, so the bank is
           searched in the script it was typed in, not the script of the answer. */
        $cols = 'SELECT vertical, module, answer_facet, subject, expect_lang, q FROM eon_kb_questions WHERE lang = ?';
        $rows = [];
        try {
            /* Exact first. "show me the trial balance" and "what is our trial
               balance gap" share both of their rare terms, so the boolean tier
               returns them together — and with no ORDER BY, LIMIT decides
               arbitrarily which survives. That is how a request for the trial
               balance REPORT was answered with the definition of the trial
               balance GAP: not a ranking failure, a candidate-selection one. */
            $ex = $pdo->prepare($cols . ' AND q = ? LIMIT 3');
            $ex->execute([$lang, trim($q)]);
            $rows = $ex->fetchAll() ?: [];

            if (!$rows) {
                /* 200, not 40. The candidates are 60-byte rows and ranking them
                   in PHP costs nothing next to fetching them; a tight LIMIT
                   here throws away the right answer before anything can score it. */
                $st = $pdo->prepare($cols . ' AND MATCH(q) AGAINST (? IN BOOLEAN MODE) LIMIT 200');
                foreach ($tiers as $expr) {
                    $st->execute([$lang, $expr]);
                    $rows = $st->fetchAll() ?: [];
                    if ($rows) break;
                }
            }
        } catch (Throwable $e) {
            Log::warn('kb bank routing failed', ['error' => $e->getMessage()]);
            return null;
        }
        if (!$rows) return null;

        /* Prefer the candidate whose words the question actually covers —
           fulltext relevance alone will happily rank a long question that
           shares two common words above the short one that shares four. */
        $best = null; $bestScore = 0.0;
        foreach ($rows as $r) {
            $s = self::coverage($terms, (string) $r['q']) * self::overlap($q, (string) $r['q']);
            if ($s > $bestScore) { $bestScore = $s; $best = $r; }
        }
        if ($best === null || $bestScore < 0.45) return null;

        return [
            'vertical' => $best['vertical'], 'module' => $best['module'],
            'facet'    => (string) $best['answer_facet'], 'subject' => $best['subject'],
            'lang'     => (string) $best['expect_lang'], 'q' => (string) $best['q'],
        ];
    }

    /** how much two questions share, as a fraction of the shorter one */
    private static function overlap(string $a, string $b): float
    {
        $ta = self::terms($a); $tb = self::terms($b);
        if (!$ta || !$tb) return 0.0;
        $common = count(array_intersect($ta, $tb));
        return $common / max(1, min(count($ta), count($tb)));
    }

    /**
     * One answer, or null.
     *
     * Null is a real outcome and the caller must be able to act on it: EON's
     * honesty about not knowing is a feature the summit script leans on.
     */
    public static function answer(string $q, ?string $lang = null, array $opts = []): ?array
    {
        $lang = $lang ?: self::langOf($q);

        /* the bank first: it knows what kind of answer is wanted */
        $r = self::route($q, $lang);
        if ($r !== null) {
            $vertical = array_key_exists('vertical', $opts) && $opts['vertical'] !== null ? $opts['vertical'] : $r['vertical'];
            $hit = self::byTags($vertical, $r['module'], $r['facet'], $r['subject'], $r['lang'], $q);
            if ($hit !== null) {
                return [
                    'text' => self::render($hit, $r['lang']), 'lang' => $hit['lang'], 'id' => $hit['id'],
                    'facet' => $hit['facet'], 'vertical' => $hit['vertical'], 'module' => $hit['module'],
                    'score' => 1.0, 'via' => 'bank', 'also' => [],
                ];
            }
        }

        /* nothing in the bank looked like this question — search the corpus */
        $hits = self::search($q, $opts + ['lang' => $lang, 'limit' => 3]);
        if (!$hits) return null;
        $top = $hits[0];
        if ((float) $top['score'] < self::MIN_SCORE) return null;
        $top['via'] = 'search';

        return [
            'text'     => self::render($top, $lang),
            'lang'     => $top['lang'],
            'id'       => $top['id'],
            'facet'    => $top['facet'],
            'vertical' => $top['vertical'],
            'module'   => $top['module'],
            'score'    => $top['score'],
            'via'      => 'search',
            'also'     => array_map(fn($h) => ['id' => $h['id'], 'title' => $h['title'], 'score' => $h['score']], array_slice($hits, 1)),
        ];
    }

    /**
     * The record the bank pointed at.
     *
     * Tags first — vertical, module, facet — because those are indexed and
     * the slice they leave is tens of rows, not tens of thousands. The
     * subject narrows it further where the bank recorded one, and the
     * question's own words only break the remaining tie.
     */
    private static function byTags(?string $vertical, ?string $module, string $facet, ?string $subject, string $lang, string $q): ?array
    {
        try { $pdo = Db::eon(); } catch (Throwable $e) { return null; }

        $where = ['facet = ?', 'lang = ?'];
        $args  = [$facet, $lang];
        if ($vertical !== null) { $where[] = '(vertical = ? OR vertical IS NULL)'; $args[] = $vertical; }
        if ($module !== null)   { $where[] = 'module = ?';   $args[] = $module; }

        $sql = 'SELECT id, facet, lang, vertical, module, subject, title, body, meta FROM eon_kb WHERE '
             . implode(' AND ', $where) . ' LIMIT 200';
        try {
            $st = $pdo->prepare($sql);
            $st->execute($args);
            $rows = $st->fetchAll() ?: [];
        } catch (Throwable $e) {
            Log::warn('kb tag lookup failed', ['error' => $e->getMessage()]);
            return null;
        }
        if (!$rows) return null;
        if (count($rows) === 1) return $rows[0];

        $terms = self::terms($q);
        $best = null; $bestScore = -1.0;
        foreach ($rows as $r) {
            $s = self::coverage($terms, ($r['title'] ?? '') . ' ' . ($r['body'] ?? ''));
            /* the bank's subject is the strongest signal there is */
            if ($subject !== null && $subject !== '' && (string) $r['subject'] === $subject) $s *= 2.0;
            if ($vertical !== null && $r['vertical'] === $vertical) $s *= 1.2;
            if ($s > $bestScore) { $bestScore = $s; $best = $r; }
        }
        return $best;
    }

    /** the record as a sentence, with the scope named — never a bare paragraph */
    private static function render(array $r, string $lang): string
    {
        $body = trim((string) $r['body']);
        $vocab = self::vocab();
        $vName = null;
        foreach ($vocab['verticals'] as $v) if ($v['id'] === $r['vertical']) $vName = $lang === 'bn' ? $v['bn'] : $v['en'];

        /* Scope on the answer, every time. The oldest rule in this codebase:
           a figure or a claim with no scope on it will be read as universal. */
        if ($vName === null) return $body;
        return $lang === 'bn'
            ? $body . "\n\n(উত্তরটি " . $vName . '-এর প্রেক্ষিতে।)'
            : $body . "\n\n(Answered for a " . $vName . '.)';
    }

    /* -------------------------------------------------------------- helpers */

    /** the words worth searching on */
    private static function terms(string $q): array
    {
        $s = mb_strtolower(trim($q));
        $s = preg_replace('/[^\p{L}\p{M}\p{N}\s]+/u', ' ', $s) ?? $s;
        $stop = ['the','a','an','is','are','was','were','of','for','to','in','on','at','my','our','me','i','we','you',
                 'what','which','how','why','who','when','do','does','did','can','should','would','will','be','been',
                 'and','or','it','its','this','that','there','here','show','tell','give','want','need','see','get',
                 'কি','কী','কত','কে','কেন','আর','ও','এই','সেই','তা','যে','হয়','করা','করব','দাও','বলো','দেখাও','আমার','আমাদের'];
        $out = [];
        foreach (preg_split('/\s+/u', $s) ?: [] as $w) {
            if ($w === '' || in_array($w, $stop, true)) continue;
            if (mb_strlen($w) < 2) continue;
            $out[] = $w;
        }
        /* Keep the rarest-looking words: longer tokens carry more signal than
           short ones, and the search only uses the first handful. */
        usort($out, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        return array_values(array_unique($out));
    }

    /** how much of the question the record actually covers, 0.5–1.0 */
    private static function coverage(array $terms, string $text): float
    {
        $t = mb_strtolower($text);
        $use = array_slice($terms, 0, 6);
        if (!$use) return 1.0;
        $hit = 0;
        foreach ($use as $w) if (mb_strpos($t, $w) !== false) $hit++;
        return 0.5 + 0.5 * ($hit / count($use));
    }

    /** what is loaded, for health.php and the corpus runner */
    public static function stats(): array
    {
        if (!self::available()) return ['loaded' => false];
        try {
            $pdo = Db::eon();
            $n  = (int) $pdo->query('SELECT COUNT(*) FROM eon_kb')->fetchColumn();
            $nq = Db::tableExists($pdo, 'eon_kb_questions')
                ? (int) $pdo->query('SELECT COUNT(*) FROM eon_kb_questions')->fetchColumn() : 0;
            $langs = $pdo->query('SELECT lang, COUNT(*) n FROM eon_kb GROUP BY lang')->fetchAll();
            return [
                'loaded' => true, 'records' => $n, 'questions' => $nq,
                'languages' => array_column($langs, 'n', 'lang'),
                'verticals' => count(self::vocab()['verticals']),
                'default_vertical' => self::defaultVertical(),
            ];
        } catch (Throwable $e) {
            return ['loaded' => false, 'error' => $e->getMessage()];
        }
    }
}
