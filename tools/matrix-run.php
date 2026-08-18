<?php
declare(strict_types=1);

/* ============================================================
   matrix-run — how much of the command space actually answers.

   qa-run.php fires 1,131 questions somebody wrote down. This walks
   the cross-product instead: every SUBJECT the ERP holds × every
   VERB that makes sense on it × three scripts, generated from
   Grammar, so the number moves when the vocabulary moves rather
   than when someone remembers to add a test.

     php tools/matrix-run.php              coverage per subject
     php tools/matrix-run.php --verbs      coverage per verb
     php tools/matrix-run.php --fail       only what does not answer
     php tools/matrix-run.php --json out   machine-readable

   A cell passes when the sentence reaches the intent Grammar routes
   it to AND the answer is not the fallback AND it comes back in the
   language it was asked in. Anything else is a hole in the space.
   ============================================================ */

require_once __DIR__ . '/../server/bootstrap.php';

$args = $argv ?? [];
$only  = in_array('--fail', $args, true);
$byVerb = in_array('--verbs', $args, true);
$jsonAt = array_search('--json', $args, true);
$jsonOut = $jsonAt !== false ? ($args[$jsonAt + 1] ?? __DIR__ . '/matrix-report.json') : null;

fwrite(STDERR, "loading dataset…\n");
$D = Dataset::current(null, false);
$T = new Tools($D, null);
$A = new Analytics($D, null);

/** the fallback wordings each language uses when nothing matched */
$FALLBACK = ['did not catch', 'did not match', 'ধরতে পারিনি', 'বুঝতে পারিনি', 'মিলছে না'];

$rows = [];
$langs = ['en', 'bn', 'bl'];
foreach (Grammar::pairs() as $pair) {
    foreach ($langs as $lang) {
        $q = Grammar::phrase($pair['verb'], $pair['subject'], $lang);
        if ($q === null) continue;
        $want = $pair['intent'];
        $got = null; $text = ''; $err = null;
        try {
            $r = Brain::askOffline($q, $D, null, $T, $lang === 'en' ? 'en-US' : 'bn-BD');
            $got = $r['intent'] ?? null;
            $text = (string) ($r['text'] ?? '');
        } catch (Throwable $e) { $err = $e->getMessage(); }

        $isFallback = false;
        foreach ($FALLBACK as $f) if ($text !== '' && mb_stripos($text, $f) !== false) { $isFallback = true; break; }
        // did it answer in the script it was asked in?
        // ৳ (U+09F3) and Bengali digits sit in the Bengali block but appear in English
        // answers too — test for Bengali LETTERS, or the whole matrix reads "wrong language"
        $bnOut = (bool) preg_match('/[\x{0985}-\x{09B9}\x{09BE}-\x{09CC}]/u', $text);
        $langOk = $lang === 'en' ? !$bnOut : $bnOut;

        $ok = Grammar::accepts($pair['verb'], $pair['subject']);
        $pass = $err === null && $text !== '' && !$isFallback && $langOk && ($want === null || in_array($got, $ok, true));
        $rows[] = ['subject' => $pair['subject'], 'verb' => $pair['verb'], 'lang' => $lang, 'q' => $q,
                   'want' => $want, 'got' => $got, 'pass' => $pass,
                   'why' => $err ? 'threw: ' . $err : ($text === '' ? 'empty' : ($isFallback ? 'fallback' : (!$langOk ? 'wrong language' : (!in_array($got, $ok, true) ? "went to " . ($got ?? 'nothing') . " (expected one of " . implode('/', $ok) . ")" : '')))),
                   'text' => mb_substr($text, 0, 160)];
    }
}

/* ---------------- report ---------------- */

$bar = function (int $p, int $t): string {
    $n = $t ? (int) round($p / $t * 20) : 0;
    return str_repeat('█', $n) . str_repeat('░', 20 - $n);
};
$group = function (string $key) use ($rows): array {
    $g = [];
    foreach ($rows as $r) { $k = $r[$key]; $g[$k]['pass'] = ($g[$k]['pass'] ?? 0) + ($r['pass'] ? 1 : 0); $g[$k]['total'] = ($g[$k]['total'] ?? 0) + 1; }
    return $g;
};

$pass = count(array_filter($rows, fn($r) => $r['pass']));
$total = count($rows);

echo "\nEON command matrix — every subject × every verb it supports × three scripts\n";
echo str_repeat('=', 74) . "\n";

if (!$only) {
    $g = $group($byVerb ? 'verb' : 'subject');
    ksort($g);
    printf("%-22s %-22s %s\n", $byVerb ? 'VERB' : 'SUBJECT', '', 'PASS/TOTAL');
    foreach ($g as $k => $v) {
        printf("%-22s %s %3d/%-3d %5.1f%%\n", mb_substr((string) $k, 0, 21), $bar($v['pass'], $v['total']), $v['pass'], $v['total'], $v['total'] ? $v['pass'] / $v['total'] * 100 : 0);
    }
    echo str_repeat('-', 74) . "\n";
    foreach ($group('lang') as $k => $v) {
        printf("%-22s %s %3d/%-3d %5.1f%%\n", $k, $bar($v['pass'], $v['total']), $v['pass'], $v['total'], $v['total'] ? $v['pass'] / $v['total'] * 100 : 0);
    }
    echo str_repeat('=', 74) . "\n";
}

printf("TOTAL %66s\n", sprintf('%d/%d  %.1f%%', $pass, $total, $total ? $pass / $total * 100 : 0));

$fails = array_values(array_filter($rows, fn($r) => !$r['pass']));
if ($fails) {
    echo "\nholes (" . count($fails) . "):\n";
    foreach (array_slice($fails, 0, $only ? 500 : 40) as $f) {
        printf("  [%s] %-46s %-16s %s\n", $f['lang'], mb_substr($f['q'], 0, 45), $f['subject'] . '/' . $f['verb'], $f['why']);
    }
    if (!$only && count($fails) > 40) echo "  … " . (count($fails) - 40) . " more (--fail to list)\n";
}

if ($jsonOut) {
    @file_put_contents($jsonOut, json_encode(['generated_at' => date('c'), 'pass' => $pass, 'total' => $total, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\nwrote $jsonOut\n";
}
exit($pass === $total ? 0 : 1);
