<?php
declare(strict_types=1);

/* ============================================================
   qa-run.php — fire the whole question bank at EON's offline
   brain and report what it actually answers.

   Coverage is not a claim you make, it is a number you print.

     php tools/qa-run.php                      (uses the cached dataset)
     php tools/qa-run.php --refresh            (pull a fresh one from live)
     php tools/qa-run.php --fail               (list only the failures)
     php tools/qa-run.php --section=Payroll    (one section)
     php tools/qa-run.php --json=out.json      (machine readable)

   A question passes when all three hold:
     1. it lands on the intent the corpus says it should
     2. the answer is a real answer, not the "I did not catch that" fallback
     3. the answer comes back in the language it was asked in
   ============================================================ */

$root = dirname(__DIR__);
define('EON_ROOT', $root . '/server');

$opt = getopt('', ['refresh', 'fail', 'section::', 'json::', 'lang::', 'limit::', 'show::']);
$onlyFail = isset($opt['fail']);
$section = $opt['section'] ?? null;
$langOnly = $opt['lang'] ?? null;
$limit = isset($opt['limit']) ? (int) $opt['limit'] : 0;
$show = isset($opt['show']) ? (int) $opt['show'] : 0;

/* ---------- load EON ---------- */
foreach (['Config', 'Log', 'Db', 'Dataset', 'Erp', 'Memory', 'Analytics',
          'ErpMap', 'Nlu', 'Phrase', 'Kb', 'Loc', 'Insight', 'Tools', 'Answer'] as $c) {
    $f = EON_ROOT . '/lib/' . $c . '.php';
    if (is_file($f)) require_once $f;
}
foreach (['Nlu', 'Answer', 'Analytics', 'Tools'] as $need) {
    if (!class_exists($need)) { fwrite(STDERR, "missing class $need — cannot run\n"); exit(2); }
}

/* ---------- the dataset ---------- */
$cache = $root . '/tools/.qa-dataset.json';
if (isset($opt['refresh']) || !is_file($cache)) {
    $cfg = @include EON_ROOT . '/config.local.php';
    $token = is_array($cfg) ? ($cfg['token'] ?? '') : '';
    $url = 'https://eon.gulfrabit.com/server/api/dataset.php?token=' . rawurlencode((string) $token);
    fwrite(STDERR, "pulling a fresh dataset from live…\n");
    $raw = @file_get_contents($url);
    if ($raw === false || strlen($raw) < 1000) { fwrite(STDERR, "could not fetch the dataset\n"); exit(2); }
    file_put_contents($cache, $raw);
}
$D = json_decode((string) file_get_contents($cache), true);
if (!is_array($D)) { fwrite(STDERR, "dataset cache is not valid JSON\n"); exit(2); }

/* ---------- the corpus ---------- */
$corpusFile = $root . '/tools/qa-corpus.json';
if (!is_file($corpusFile)) { fwrite(STDERR, "run: node tools/make-qa-corpus.mjs\n"); exit(2); }
$corpus = json_decode((string) file_get_contents($corpusFile), true);
$questions = $corpus['questions'] ?? [];

/* expand the person templates against real staff names */
$names = [];
foreach (($D['employees'] ?? []) as $e) {
    $n = trim((string) ($e['name'] ?? ''));
    if ($n !== '' && strtolower($n) !== 'super admin' && str_word_count($n) >= 2) $names[] = $n;
}
$names = array_slice(array_values(array_unique($names)), 0, 6);
$id = 100000;
foreach (($corpus['person_templates'] ?? []) as $tpl) {
    foreach ($names as $n) {
        $questions[] = ['id' => ++$id, 'q' => str_replace('{name}', $n, (string) $tpl['q']),
                        'intent' => $tpl['intent'], 'section' => 'Payroll & People',
                        'lang' => $tpl['lang'], 'expect' => $tpl['lang'] === 'en' ? 'en' : 'bn',
                        'kind' => 'person'];
    }
}

/* ---------- intents that may legitimately answer for one another ---------- */
$ALIAS = [
    'payroll'          => ['payroll', 'payroll_unpaid', 'evaluate_person'],
    'payroll_unpaid'   => ['payroll', 'payroll_unpaid'],
    'overdue_payments' => ['overdue_payments', 'payables'],
    'expenses'         => ['expenses', 'expense_by_category', 'budget'],
    'expense_by_category' => ['expense_by_category', 'expenses'],
    'revenue'          => ['revenue', 'profit_loss'],
    'brief'            => ['brief', 'focus'],
    'focus'            => ['focus', 'brief', 'risks'],
    'risks'            => ['risks', 'focus', 'anomalies'],
    'howto'            => ['howto', 'navigation', 'deduction_rules'],
    'navigation'       => ['navigation', 'howto'],
    'burn_runway'      => ['burn_runway', 'cash'],
    'attendance_today' => ['attendance_today', 'late_today'],
    'evaluate_person'  => ['evaluate_person', 'payroll'],
    'departments'      => ['departments', 'headcount'],
    'tasks'            => ['tasks', 'projects'],
];

/* the opening words of every fallback, in both languages */
$FALLBACK_MARK = ['I did not catch which report', 'কোন হিসাবটা চাইছেন সেটা ধরতে পারিনি'];

$A = new Analytics($D, null);
$T = new Tools($D, null);

$results = [];
$n = 0;
$t0 = microtime(true);

foreach ($questions as $row) {
    if ($section !== null && stripos((string) $row['section'], (string) $section) === false) continue;
    if ($langOnly !== null && $row['lang'] !== $langOnly) continue;
    if ($limit > 0 && $n >= $limit) break;
    $n++;

    $q = (string) $row['q'];
    $want = (string) $row['intent'];
    $ok = true;
    $why = [];
    $text = '';
    $got = null;

    try {
        $parse = Nlu::parse($q);
        $got = $parse['intent'];
        $out = Answer::compose($q, $parse, $A, $T);
        $text = (string) $out['text'];

        // 1. intent
        $accept = $ALIAS[$want] ?? [$want];
        if (!in_array((string) $got, $accept, true)) { $ok = false; $why[] = 'intent=' . ($got ?? 'none'); }

        // 2. a real answer
        foreach ($FALLBACK_MARK as $mark) {
            if (mb_strpos($text, $mark) !== false) { $ok = false; $why[] = 'fallback'; break; }
        }
        if (trim($text) === '') { $ok = false; $why[] = 'empty'; }

        // 3. right language
        $ratio = Nlu::banglaRatio($text);
        $expect = (string) $row['expect'];
        if ($expect === 'bn' && $ratio < 0.2) { $ok = false; $why[] = 'answered in english'; }
        if ($expect === 'en' && $ratio > 0.5) { $ok = false; $why[] = 'answered in bangla'; }
    } catch (Throwable $e) {
        $ok = false;
        $why[] = 'threw: ' . $e->getMessage();
    }

    $results[] = ['id' => $row['id'], 'q' => $q, 'section' => $row['section'], 'lang' => $row['lang'],
                  'want' => $want, 'got' => $got, 'ok' => $ok, 'why' => implode(', ', $why),
                  'text' => $text];
}

$ms = (int) round((microtime(true) - $t0) * 1000);

/* ---------- report ---------- */
$pass = count(array_filter($results, fn($r) => $r['ok']));
$total = count($results);

$bySection = [];
$byLang = [];
foreach ($results as $r) {
    $s = (string) $r['section'];
    $bySection[$s] = $bySection[$s] ?? ['pass' => 0, 'total' => 0];
    $bySection[$s]['total']++;
    if ($r['ok']) $bySection[$s]['pass']++;

    $l = (string) $r['lang'];
    $byLang[$l] = $byLang[$l] ?? ['pass' => 0, 'total' => 0];
    $byLang[$l]['total']++;
    if ($r['ok']) $byLang[$l]['pass']++;
}

$bar = function (int $p, int $t): string {
    $pct = $t ? (int) round($p / $t * 20) : 0;
    return str_repeat('█', $pct) . str_repeat('·', 20 - $pct);
};

if (!$onlyFail) {
    echo "\n";
    echo "EON offline brain — question bank\n";
    echo str_repeat('=', 64), "\n";
    printf("%-24s %s %5s/%-5s %s\n", 'SECTION', str_repeat(' ', 20), 'PASS', 'TOTAL', '');
    foreach ($bySection as $s => $v) {
        printf("%-24s %s %5d/%-5d %5.1f%%\n", mb_substr($s, 0, 24), $bar($v['pass'], $v['total']),
            $v['pass'], $v['total'], $v['total'] ? $v['pass'] / $v['total'] * 100 : 0);
    }
    echo str_repeat('-', 64), "\n";
    foreach ($byLang as $l => $v) {
        $label = ['en' => 'English', 'bn' => 'বাংলা', 'bl' => 'Banglish'][$l] ?? $l;
        printf("%-24s %s %5d/%-5d %5.1f%%\n", $label, $bar($v['pass'], $v['total']),
            $v['pass'], $v['total'], $v['total'] ? $v['pass'] / $v['total'] * 100 : 0);
    }
    echo str_repeat('=', 64), "\n";
    printf("%-24s %s %5d/%-5d %5.1f%%   (%d ms)\n", 'TOTAL', $bar($pass, $total), $pass, $total,
        $total ? $pass / $total * 100 : 0, $ms);
    echo "\n";
}

$fails = array_values(array_filter($results, fn($r) => !$r['ok']));
if ($fails) {
    echo "failures (", count($fails), "):\n";
    foreach (array_slice($fails, 0, $onlyFail ? 200 : 25) as $f) {
        printf("  [%s] %-46s want=%-20s %s\n", $f['lang'], mb_substr($f['q'], 0, 46), $f['want'], $f['why']);
    }
    if (count($fails) > ($onlyFail ? 200 : 25)) echo '  … and ', count($fails) - ($onlyFail ? 200 : 25), " more\n";
    echo "\n";
}

if ($show > 0) {
    echo "sample answers:\n";
    foreach (array_slice(array_filter($results, fn($r) => $r['ok']), 0, $show) as $r) {
        echo "  Q [", $r['lang'], "] ", $r['q'], "\n  A ", mb_substr($r['text'], 0, 200), "\n\n";
    }
}

if (isset($opt['json'])) {
    $file = $opt['json'] !== false && $opt['json'] !== '' ? (string) $opt['json'] : $root . '/tools/qa-report.json';
    file_put_contents($file, json_encode([
        'ran_at' => date('c'), 'ms' => $ms, 'pass' => $pass, 'total' => $total,
        'pct' => $total ? round($pass / $total * 100, 1) : 0,
        'by_section' => $bySection, 'by_language' => $byLang,
        'failures' => array_map(fn($f) => ['q' => $f['q'], 'lang' => $f['lang'], 'want' => $f['want'], 'got' => $f['got'], 'why' => $f['why']], $fails),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "wrote $file\n";
}

exit($pass === $total ? 0 : 1);
