<?php
declare(strict_types=1);

/* ============================================================
   scope-run.php — the two holes a judge finds first, measured.

   EON published both of these against itself, so they are worth
   testing the way they were admitted:

     1. a question that narrows ("in Chittagong", "the ticketing
        department") was answered about the whole business, with a
        real number and no hedge
     2. a question that names two periods ("this month vs last
        month") was answered about one of them

   Four things are asserted per case, and the third is the one
   that matters most:

     a. the answer is a real answer, not the fallback
     b. when the filter CAN be honoured, it is — the slice is
        named and the wide figure is gone
     c. when it CANNOT be honoured, the answer says so — silence
        is the defect, not the missing filter
     d. a question with NO filter in it gets NO caveat: crying
        "I could not narrow that" over a question nobody narrowed
        is the same lie facing the other way

     php tools/scope-run.php            all of it
     php tools/scope-run.php --fail     only what broke
     php tools/scope-run.php --show     print every answer
   ============================================================ */

$root = dirname(__DIR__);
define('EON_ROOT', $root . '/server');

$opt = getopt('', ['fail', 'show', 'only::']);
$onlyFail = isset($opt['fail']);
$show = isset($opt['show']);
$only = $opt['only'] ?? null;

foreach (['Config', 'Log', 'Db', 'Dataset', 'Erp', 'Memory', 'Analytics', 'ErpMap',
          'Scope', 'Grammar', 'Nlu', 'Phrase', 'Kb', 'Loc', 'Speech', 'Insight',
          'Reason', 'Tools', 'Answer'] as $c) {
    $f = EON_ROOT . '/lib/' . $c . '.php';
    if (is_file($f)) require_once $f;
}
foreach (['Scope', 'Nlu', 'Answer', 'Analytics', 'Tools'] as $need) {
    if (!class_exists($need)) { fwrite(STDERR, "missing class $need — cannot run\n"); exit(2); }
}

$cache = $root . '/tools/.qa-dataset.json';
if (!is_file($cache)) { fwrite(STDERR, "no dataset cache — run: php tools/qa-run.php --refresh\n"); exit(2); }
$D = json_decode((string) file_get_contents($cache), true);
if (!is_array($D)) { fwrite(STDERR, "dataset cache is not valid JSON\n"); exit(2); }

Nlu::useDataset($D);
$A = new Analytics($D);
$tools = new Tools($D);

/* ---------- what the answers must and must not contain ---------- */

/** the sentence EON says when it could not honour a filter */
const REFUSED_EN = 'could not narrow';
const REFUSED_BN = 'আলাদা করতে পারিনি';
/** the softer one, when the dimension exists but this answer does not slice by it */
const PARTIAL_EN = 'does not split by';
const PARTIAL_BN = 'ভাগ করে না';

function refuses(string $t): bool { return str_contains($t, REFUSED_EN) || str_contains($t, REFUSED_BN); }
function hedges(string $t): bool  { return refuses($t) || str_contains($t, PARTIAL_EN) || str_contains($t, PARTIAL_BN); }

/* ---------- the cases ----------
   kind:
     applied  — the filter is real; the answer must name the slice and must not hedge
     refused  — the filter is not in the data; the answer must say so out loud
     clean    — no filter was asked for; the answer must carry no scope hedge at all
     compare  — two periods; both must appear, and `dir` must be the direction said
     declined — a comparison EON cannot make honestly; it must say why
*/
$CASES = [
    // ---- a filter the data really carries, English ----
    ['q' => 'how many employees in the IT department', 'kind' => 'applied', 'want' => 'IT & AI Operations'],
    ['q' => 'how many people in the marketing department', 'kind' => 'applied', 'want' => 'Marketing & Branding'],
    ['q' => 'who is in the ticketing department', 'kind' => 'applied', 'want' => 'Ticketing & Tours'],
    ['q' => 'expenses in Bhairab', 'kind' => 'applied', 'want' => 'Bhairab'],
    ['q' => 'what did the group office spend', 'kind' => 'applied', 'want' => 'Group Office'],

    // ---- the same, in বাংলা, against Latin-spelled records ----
    ['q' => 'টিকিটিং বিভাগে কতজন আছে', 'kind' => 'applied', 'want' => 'Ticketing & Tours', 'lang' => 'bn'],
    ['q' => 'মার্কেটিং বিভাগে কতজন', 'kind' => 'applied', 'want' => 'Marketing & Branding', 'lang' => 'bn'],
    ['q' => 'অ্যাকাউন্টস বিভাগে কে কে আছে', 'kind' => 'applied', 'want' => 'Accounts & Finance', 'lang' => 'bn'],

    // ---- a filter the ERP has no column for: it must be refused, not absorbed ----
    ['q' => 'how many customers in Chittagong', 'kind' => 'refused', 'want' => 'Chittagong'],
    ['q' => 'expenses in Dhaka office', 'kind' => 'refused', 'want' => 'Dhaka'],
    ['q' => 'sales in Chittagong branch', 'kind' => 'refused', 'want' => 'Chittagong'],
    ['q' => 'how many staff in Sylhet', 'kind' => 'refused', 'want' => 'Sylhet'],
    ['q' => 'revenue from the Khulna region', 'kind' => 'refused', 'want' => 'Khulna'],

    /* ---- no filter was asked for, so no hedge may appear ----
       This half of the suite is the reason the other half is safe. A scope
       reader that fires on questions with no scope in them would hedge every
       answer EON gives, and a hedge on everything is worth exactly nothing. */
    ['q' => 'how much cash do we have', 'kind' => 'clean'],
    ['q' => 'how many people work here', 'kind' => 'clean'],
    ['q' => 'what did we spend this month', 'kind' => 'clean'],
    ['q' => 'who is late today', 'kind' => 'clean'],
    ['q' => 'show me the receivables', 'kind' => 'clean'],
    ['q' => 'what is the payroll this month', 'kind' => 'clean'],
    ['q' => 'how many tasks are overdue', 'kind' => 'clean'],
    ['q' => 'what is in the pipeline', 'kind' => 'clean'],
    ['q' => 'give me the brief', 'kind' => 'clean'],
    ['q' => 'কত টাকা আছে', 'kind' => 'clean', 'lang' => 'bn'],
    ['q' => 'আজ কে দেরিতে এসেছে', 'kind' => 'clean', 'lang' => 'bn'],
    ['q' => 'এই মাসে খরচ কত', 'kind' => 'clean', 'lang' => 'bn'],
    ['q' => 'কতজন কর্মী আছে', 'kind' => 'clean', 'lang' => 'bn'],
    ['q' => 'বকেয়া কত', 'kind' => 'clean', 'lang' => 'bn'],
    ['q' => 'where is the payslip screen', 'kind' => 'clean'],
    ['q' => 'why is profit down', 'kind' => 'clean'],
    ['q' => 'cash in hand', 'kind' => 'clean'],
    ['q' => 'what is the VAT calendar', 'kind' => 'clean'],

    // ---- two periods in one sentence ----
    ['q' => 'compare this month with last month', 'kind' => 'compare', 'both' => ['August', 'July']],
    ['q' => 'this month vs last month profit', 'kind' => 'compare', 'both' => ['August', 'July']],
    ['q' => 'compare this year with last year', 'kind' => 'compare', 'both' => ['2026', '2025']],
    ['q' => 'compare June with July', 'kind' => 'compare', 'both' => ['June', 'July']],
    ['q' => 'how does our spending compare with last month', 'kind' => 'compare', 'both' => ['August', 'July']],
    ['q' => 'গত বছরের সাথে তুলনা করো', 'kind' => 'compare', 'both' => ['২০২৬', '২০২৫'], 'lang' => 'bn'],
    ['q' => 'গত মাসের সাথে এই মাসের তুলনা করো', 'kind' => 'compare', 'both' => ['আগস্ট', 'জুলাই'], 'lang' => 'bn'],
    ['q' => 'গত মাসের চেয়ে খরচ কত বেড়েছে', 'kind' => 'compare', 'both' => ['আগস্ট', 'জুলাই'], 'lang' => 'bn'],

    // ---- a comparison that cannot be made honestly ----
    ['q' => 'compare cash with last month', 'kind' => 'declined'],
    ['q' => 'compare our headcount with last year', 'kind' => 'declined'],
];

/* ---------- run ---------- */

$pass = 0; $fail = 0; $lines = [];
foreach ($CASES as $i => $c) {
    if ($only !== null && !str_contains($c['q'], (string) $only)) continue;
    $q = $c['q'];
    $parse = Nlu::parse($q, $c['lang'] ?? null);
    $res = Answer::compose($q, $parse, $A, $tools);
    $text = (string) $res['text'];
    $why = [];

    // (a) it answered at all
    if ($text === '' || str_contains($text, 'did not catch') || str_contains($text, 'ধরতে পারিনি')) {
        $why[] = 'fell through to the fallback';
    }
    // language honesty, the same criterion the question bank uses
    if (($c['lang'] ?? 'en') === 'bn' && Nlu::banglaRatio($text) < 0.3) {
        $why[] = 'a বাংলা question came back in English';
    }

    switch ($c['kind']) {
        case 'applied':
            if (!str_contains($text, (string) $c['want'])) $why[] = "the slice '{$c['want']}' is not named in the answer";
            if (hedges($text)) $why[] = 'the filter was honoured but the answer still hedged';
            if (($res['scope_applied'] ?? false) !== true) $why[] = 'scope_applied is false on an applied filter';
            break;

        case 'refused':
            if (!refuses($text)) $why[] = 'a filter it cannot honour passed without a word';
            if (!str_contains($text, (string) $c['want'])) $why[] = "the refusal does not name '{$c['want']}'";
            break;

        case 'clean':
            if (hedges($text)) $why[] = 'hedged about a filter the question never asked for';
            if (($parse['slots']['scope'] ?? null) !== null) $why[] = 'read a scope out of a question with none in it';
            break;

        case 'compare':
            foreach ((array) $c['both'] as $side) {
                if (!str_contains($text, $side)) $why[] = "'$side' is missing — only one period was answered";
            }
            if (($res['intent'] ?? '') !== 'compare') $why[] = 'a two-period question did not route to the comparison';
            break;

        case 'declined':
            $said = str_contains($text, 'cannot set') || str_contains($text, 'মেলাতে পারব না');
            if (!$said) $why[] = 'compared a position across periods instead of saying it cannot';
            break;
    }

    $ok = $why === [];
    $ok ? $pass++ : $fail++;
    if (!$ok || $show || !$onlyFail) {
        $mark = $ok ? '  ok  ' : ' FAIL ';
        $lines[] = sprintf('%s %-52s %s', $mark, mb_strimwidth($q, 0, 52, '…'),
            $ok ? ($c['kind']) : implode('; ', $why));
        if ($show || !$ok) $lines[] = '        ' . mb_strimwidth(str_replace("\n", ' ', $text), 0, 220, '…');
    }
}

$total = $pass + $fail;
echo "EON — the narrowing half of a question, and the second period\n";
echo str_repeat('=', 64) . "\n";
foreach ($lines as $l) {
    if ($onlyFail && str_starts_with($l, '  ok  ')) continue;
    echo $l . "\n";
}
echo str_repeat('=', 64) . "\n";
printf("TOTAL  %d/%d  %.1f%%\n", $pass, $total, $total ? $pass / $total * 100 : 0);
exit($fail > 0 ? 1 : 0);
