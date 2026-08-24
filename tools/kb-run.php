#!/usr/bin/env php
<?php
declare(strict_types=1);
/* ============================================================
   tools/kb-run.php — does the corpus answer its own question bank?

   Every question in eon_kb_questions carries the vertical, module
   and answer_facet the record answering it should have. This fires
   a stratified sample of them at KbCorpus and counts four things:

     answered   a record came back at all
     vertical   it came from the right trade
     module     it came from the right module
     facet      it was the right *kind* of record

   Facet is the strict one and the one worth reading: a question
   asking how a process works, answered with a field definition
   from the same module, is a miss even though every id lines up.

     php tools/kb-run.php                 1,000 questions, stratified
     php tools/kb-run.php --n 5000        a bigger sample
     php tools/kb-run.php --all           every one of the 188,885
     php tools/kb-run.php --lang bn       one language
     php tools/kb-run.php --vertical hospital
     php tools/kb-run.php --fail          print the misses
     php tools/kb-run.php --paraphrase    ask it a way the bank does not hold
     php tools/kb-run.php --json out.json

   --paraphrase is the number that means something. Asked verbatim,
   every question is already in eon_kb_questions and the exact-match
   tier finds it: that measures the index, not the retrieval, and it
   reads 100% because it is very nearly circular. --paraphrase
   perturbs each question first — drops a word, reorders, prefixes
   the filler people actually type — so nothing matches exactly and
   the boolean tiers have to earn it.

   Sample by strata, never by stride. The bank is written in
   per-vertical blocks, so a stride sharing a factor with the block
   size lands on the same offsets forever — the instance matrix
   learned that the expensive way and drew Banglish zero times
   while reporting 97.8%.
   ============================================================ */

require_once dirname(__DIR__) . '/server/bootstrap.php';

$opt = static function (array $argv, string $n, $d = null) {
    $i = array_search($n, $argv, true);
    return $i === false ? $d : ($argv[$i + 1] ?? true);
};
$has = static fn(array $a, string $n): bool => in_array($n, $a, true);

$N        = (int) $opt($argv, '--n', 1000);
$ALL      = $has($argv, '--all');
$FAIL     = $has($argv, '--fail');
$LANG     = $opt($argv, '--lang', null);
$VERT     = $opt($argv, '--vertical', null);
$JSON     = $opt($argv, '--json', null);
$PARA     = $has($argv, '--paraphrase');

/**
 * Ask it a way the bank does not hold.
 *
 * Deterministic per question id, so a re-run measures the same thing. Four
 * perturbations, all of them things people really do: drop a word, put the
 * subject first, add polite filler, contract the verb.
 */
function perturb(string $q, int $seed): string
{
    $w = preg_split('/\s+/u', trim($q)) ?: [$q];
    switch ($seed % 4) {
        case 0:
            if (count($w) > 2) array_splice($w, 0, 1);
            return implode(' ', $w);
        case 1:
            if (count($w) > 2) { $tail = array_splice($w, -2); $w = array_merge($tail, $w); }
            return implode(' ', $w);
        case 2:
            return (preg_match('/[\x{0980}-\x{09FF}]/u', $q) ? 'একটু বলো তো ' : 'please tell me ') . $q;
        default:
            return str_replace(
                ['what is the ', 'what is ', 'how do I ', 'show me the ', 'কীভাবে', 'দেখাও'],
                ['whats the ', 'whats ', 'how to ', 'show ', 'কিভাবে', 'দেখাও তো'],
                $q
            );
    }
}

if (!KbCorpus::available()) {
    fwrite(STDERR, "eon_kb is not loaded — run server/install/schema-kb.sql then tools/kb-import.php\n");
    exit(1);
}
$pdo = Db::eon();
if (!Db::tableExists($pdo, 'eon_kb_questions')) {
    fwrite(STDERR, "eon_kb_questions is not loaded — run tools/kb-import.php --only q\n");
    exit(1);
}

/* ---------------- draw the sample ---------------- */
$where = ['module IS NOT NULL'];
$args  = [];
if ($LANG !== null) { $where[] = 'lang = ?';     $args[] = (string) $LANG; }
if ($VERT !== null) { $where[] = 'vertical = ?'; $args[] = (string) $VERT; }
$w = 'WHERE ' . implode(' AND ', $where);

$total = (int) Db::one($pdo, "SELECT COUNT(*) n FROM eon_kb_questions {$w}", $args)['n'];
if ($total === 0) { fwrite(STDERR, "no questions match those filters\n"); exit(1); }

if ($ALL) {
    $rows = Db::rows($pdo, "SELECT * FROM eon_kb_questions {$w}", $args);
} else {
    /* One stratum per (vertical, lang, intent) so every corner is drawn — but
       in ONE query. Looping the strata meant up to 1,728 separate SELECTs,
       each with its own filesort: the first run of this file spent ten
       minutes drawing a 300-question sample and never reached the retrieval
       it was supposed to be measuring. MariaDB has had window functions
       since 10.2; the host runs 10.4. */
    $strataCount = (int) Db::one($pdo, "SELECT COUNT(*) n FROM (SELECT 1 FROM eon_kb_questions {$w} GROUP BY vertical, lang, intent) s", $args)['n'];
    $per = max(1, (int) ceil($N / max(1, $strataCount)));
    $rows = Db::rows($pdo,
        "SELECT * FROM (
           SELECT q.*, ROW_NUMBER() OVER (PARTITION BY vertical, lang, intent ORDER BY id) rn
             FROM eon_kb_questions q {$w}
         ) s WHERE rn <= {$per}",
        $args
    );
    if (count($rows) > $N) {
        /* trim evenly rather than from the tail, which would drop whole strata */
        $step = count($rows) / $N;
        $keep = [];
        for ($i = 0; $i < $N; $i++) $keep[] = $rows[(int) floor($i * $step)];
        $rows = $keep;
    }
}

printf("firing %s of %s questions%s%s%s\n\n",
    number_format(count($rows)), number_format($total),
    $LANG !== null ? " · lang {$LANG}" : '', $VERT !== null ? " · {$VERT}" : '',
    $PARA ? ' · paraphrased (held out from the bank)' : ' · verbatim');

/* ---------------- fire ---------------- */
$stat = ['answered' => 0, 'vertical' => 0, 'module' => 0, 'facet' => 0, 'lang' => 0];
$byIntent = []; $byLang = []; $misses = []; $t0 = microtime(true);

foreach ($rows as $r) {
    /* The runner tells KbCorpus which trade to answer as. In the app that
       comes from config or from the question naming one; here it is given,
       because what is being measured is retrieval, not trade detection. */
    $asked = $PARA ? perturb((string) $r['q'], (int) preg_replace('/\D/', '', (string) $r['id'])) : (string) $r['q'];
    $hit = KbCorpus::answer($asked, (string) $r['lang'], ['vertical' => $r['vertical']]);

    $k = $r['intent'];
    $byIntent[$k] = $byIntent[$k] ?? ['n' => 0, 'answered' => 0, 'facet' => 0];
    $byLang[$r['lang']] = $byLang[$r['lang']] ?? ['n' => 0, 'answered' => 0, 'facet' => 0];
    $byIntent[$k]['n']++; $byLang[$r['lang']]['n']++;

    if ($hit === null) {
        if ($FAIL) $misses[] = ['q' => $asked, 'want' => $r['answer_facet'], 'got' => null, 'lang' => $r['lang'], 'vertical' => $r['vertical']];
        continue;
    }
    $stat['answered']++; $byIntent[$k]['answered']++; $byLang[$r['lang']]['answered']++;
    /* against expect_lang, not lang: a Banglish question is asked in Latin
       and must be ANSWERED in বাংলা, so comparing the two scripts scored
       120 correct answers as failures and read as an 80% language rate. */
    if ($hit['lang'] === $r['expect_lang']) $stat['lang']++;
    if ($hit['vertical'] === $r['vertical']) $stat['vertical']++;
    if ($hit['module'] === $r['module']) $stat['module']++;
    if ($hit['facet'] === $r['answer_facet']) {
        $stat['facet']++; $byIntent[$k]['facet']++; $byLang[$r['lang']]['facet']++;
    } elseif ($FAIL) {
        $misses[] = ['q' => $asked, 'want' => $r['answer_facet'], 'got' => $hit['facet'],
                     'lang' => $r['lang'], 'vertical' => $r['vertical'], 'id' => $hit['id'], 'score' => $hit['score']];
    }
}

/* ---------------- report ---------------- */
$n = max(1, count($rows));
$pc = static fn(int $x): string => sprintf('%5.1f%%', 100 * $x / max(1, $GLOBALS['__n']));
$GLOBALS['__n'] = $n;

echo "                     count        of sample\n";
foreach (['answered' => 'answered at all', 'lang' => 'answered in the language expected',
          'vertical' => 'right trade', 'module' => 'right module', 'facet' => 'right kind of record'] as $k => $label) {
    printf("  %-30s %7s   %s\n", $label, number_format($stat[$k]), $pc($stat[$k]));
}

echo "\nby language\n";
foreach ($byLang as $l => $v) {
    printf("  %-6s n=%-6s answered %5.1f%%   right kind %5.1f%%\n",
        $l, number_format($v['n']), 100 * $v['answered'] / max(1, $v['n']), 100 * $v['facet'] / max(1, $v['n']));
}

echo "\nby intent (worst first)\n";
uasort($byIntent, fn($a, $b) => ($a['facet'] / max(1, $a['n'])) <=> ($b['facet'] / max(1, $b['n'])));
foreach ($byIntent as $k => $v) {
    printf("  %-16s n=%-6s answered %5.1f%%   right kind %5.1f%%\n",
        $k, number_format($v['n']), 100 * $v['answered'] / max(1, $v['n']), 100 * $v['facet'] / max(1, $v['n']));
}

printf("\n%s questions in %.1fs (%.0f/s)\n", number_format($n), microtime(true) - $t0, $n / max(0.001, microtime(true) - $t0));

if ($FAIL && $misses) {
    echo "\nmisses (first 40)\n";
    foreach (array_slice($misses, 0, 40) as $m) {
        printf("  [%s/%s] %-52s want %-18s got %s\n", $m['lang'], $m['vertical'],
            mb_substr($m['q'], 0, 52), $m['want'], $m['got'] ?? '—');
    }
    printf("  … %s misses in total\n", number_format(count($misses)));
}

if ($JSON) {
    file_put_contents((string) $JSON, json_encode([
        'sample' => $n, 'total' => $total, 'stats' => $stat,
        'by_intent' => $byIntent, 'by_lang' => $byLang,
        'misses' => array_slice($misses, 0, 500),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "wrote {$JSON}\n";
}
