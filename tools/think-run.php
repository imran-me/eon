<?php
declare(strict_types=1);

/* ============================================================
   think-run.php — EON reasoning, measured.

   `Think` turns a question into an argument built from stored
   facts. Everything that went wrong while building it went wrong
   silently — the answer was fluent every time, and fluent is
   exactly what makes a wrong one dangerous. So this asserts the
   things that were actually broken:

     subject   the frame names what was ASKED. It named
               `student_billing` for an admissions question (the
               far end of an integration record), then
               `over admission` (a pitfall that merely shared a
               word), then `users and permissions` (a better
               fulltext score on the word *work*). Three separate
               ways to answer confidently about the wrong thing.
     chain     the moves are ordered and plural — a definition
               and nothing else is a lookup wearing a frame
     scope     every answer says it speaks for the trade and not
               for this company's books
     bangla    a বাংলা answer is বাংলা throughout, and where the
               corpus has no Bangla for a move it SAYS so rather
               than splicing English in
     repeat    no clause is said twice
     stable    the same question gives the same answer

     php tools/think-run.php           all of it
     php tools/think-run.php --fail    only what broke
     php tools/think-run.php --show    print the answers
   ============================================================ */

define('EON_ROOT', dirname(__DIR__) . '/server');
require_once EON_ROOT . '/bootstrap.php';

$opt = getopt('', ['fail', 'show']);
$onlyFail = isset($opt['fail']);
$show = isset($opt['show']);

if (!class_exists('Think') || !class_exists('KbCorpus') || !KbCorpus::available()) {
    fwrite(STDERR, "the corpus is not loaded — run: php tools/kb-import.php\n");
    exit(2);
}

/* q, the language it must answer in, and the word the frame must name */
$CASES = [
    ['q' => 'how does admissions work at a university',            'lang' => 'en', 'about' => 'admissions'],
    ['q' => 'what is bed occupancy in a hospital',                 'lang' => 'en', 'about' => 'bed occupancy'],
    ['q' => 'who can approve a purchase order in a factory',       'lang' => 'en', 'about' => 'purchas'],
    ['q' => 'what goes wrong with batch expiry in a pharmacy',     'lang' => 'en', 'about' => 'batch'],
    ['q' => 'how does payroll work in a garment factory',          'lang' => 'en', 'about' => 'payroll'],
    ['q' => 'what is fee collection rate at a school',             'lang' => 'en', 'about' => 'collection'],
    ['q' => 'how does the general ledger post in a travel agency', 'lang' => 'en', 'about' => 'ledger'],
    ['q' => 'what is bed occupancy',                               'lang' => 'en', 'about' => 'bed occupancy'],
    ['q' => 'বিশ্ববিদ্যালয়ে ভর্তি প্রক্রিয়া কীভাবে চলে',                'lang' => 'bn', 'about' => 'ভর্তি'],
    ['q' => 'হাসপাতালে বেড দখল মানে কী',                            'lang' => 'bn', 'about' => 'বেড'],
    ['q' => 'সাধারণ খতিয়ানে কী কী সমস্যা হয়',                        'lang' => 'bn', 'about' => 'খতিয়ান'],
];

/* questions the corpus has nothing for — it must decline, not improvise */
$SILENT = [
    'what is the capital of Bangladesh',
    'sing me a song',
    'zzzzz qqqqq wwwww',
];

$pass = 0; $fail = 0; $lines = [];
$say = function (bool $ok, string $what, string $why = '') use (&$pass, &$fail, &$lines, $onlyFail) {
    $ok ? $pass++ : $fail++;
    if (!$ok || !$onlyFail) $lines[] = sprintf('%s %-52s %s', $ok ? '  ok  ' : ' FAIL ', mb_strimwidth($what, 0, 52, '…'), $why);
};

$BN = '/[\x{0980}-\x{09FF}]/u';

foreach ($CASES as $c) {
    $t = Think::about($c['q'], $c['lang']);
    if ($t === null) { $say(false, $c['q'], 'no answer at all'); continue; }
    $text = (string) $t['text'];
    $moves = array_column($t['chain'], 'move');
    $why = [];

    // the frame must name what was asked
    $frame = mb_substr($text, 0, 160);
    if (mb_stripos($frame, $c['about']) === false) {
        $why[] = "the frame does not name '{$c['about']}' — it opened \"" . mb_strimwidth($frame, 0, 60, '…') . '"';
    }

    // an argument, not a lookup
    if (count($moves) < 4) $why[] = 'chain is ' . implode('>', $moves) . ' — too short to be reasoning';
    if ($moves[0] !== 'frame') $why[] = 'does not open with a frame';
    if (end($moves) !== 'close') $why[] = 'does not close';

    // it must not be read as a figure from this company's books
    $scoped = str_contains($text, 'not a reading of your books') || str_contains($text, 'not your company')
           || str_contains($text, 'আপনার নিজের হিসাবের সংখ্যা নয়') || str_contains($text, 'আপনার প্রতিষ্ঠানের সংখ্যা নয়');
    if (!$scoped) $why[] = 'never says it is speaking for the trade rather than this company';

    // language, all the way through
    if ($c['lang'] === 'bn') {
        $prose = preg_replace('/[“"][^”"]*[”"]/u', ' ', $text) ?? $text;   // quoted identifiers are the ERP's own
        if (preg_match('/[A-Za-z][A-Za-z\'-]*(?:\s+[A-Za-z][A-Za-z\'-]*){2,}/u', $prose, $m)) {
            $why[] = 'English clause in a বাংলা answer: "' . mb_strimwidth($m[0], 0, 40, '…') . '"';
        }
        if (!preg_match($BN, $text)) $why[] = 'a বাংলা question came back with no বাংলা in it';
    } elseif (preg_match($BN, $text)) {
        $why[] = 'বাংলা inside an English answer';
    }

    /* nothing said twice — the first build repeated one module's purpose clause
       three times in a single answer, which is what a machine does and a person
       does not */
    $sentences = preg_split('/(?<=[.।])\s+/u', $text) ?: [];
    $seen = [];
    foreach ($sentences as $s) {
        $k = mb_strtolower(trim(preg_replace('/[^\p{L}\p{M}\p{N}\s]+/u', '', $s) ?? $s));
        if (mb_strlen($k) < 30) continue;
        if (isset($seen[$k])) { $why[] = 'says the same sentence twice'; break; }
        $seen[$k] = true;
    }

    // same question, same answer
    $again = Think::about($c['q'], $c['lang']);
    if ($again === null || $again['text'] !== $text) $why[] = 'answered differently the second time';

    $say($why === [], $c['q'], $why === [] ? $t['kind'] . ': ' . implode('>', $moves) : implode('; ', $why));
    if ($show) $lines[] = '        ' . mb_strimwidth(str_replace("\n", ' ', $text), 0, 400, '…');
}

/* and the silence */
foreach ($SILENT as $q) {
    $t = Think::about($q, 'en');
    $say($t === null, "declines: \"$q\"", $t === null ? 'no answer, correctly' : 'improvised an answer about ' . ($t['focus']['label'] ?? '?'));
}

$total = $pass + $fail;
echo "EON — reasoning from the corpus, not reciting it\n";
echo str_repeat('=', 72) . "\n";
foreach ($lines as $l) echo $l . "\n";
echo str_repeat('=', 72) . "\n";
printf("TOTAL  %d/%d  %.1f%%\n", $pass, $total, $total ? $pass / $total * 100 : 0);
exit($fail > 0 ? 1 : 0);
