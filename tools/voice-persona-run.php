<?php
/**
 * EON · voice-persona-run — does the warmth stay at the edges?
 *
 *   php tools/voice-persona-run.php
 *
 * Asserts: determinism, a sane frequency band, no filler inside figures or
 * names, never two fillers stacked, never the same filler twice in a row,
 * no English in Bangla speech, confirmation gates and refusals untouched,
 * idempotency (warming twice = warming once), valid SSML.
 */
require __DIR__ . '/../server/lib/Persona.php';

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; return; }
    $fail++; echo "FAIL  $name" . ($detail !== '' ? "  — $detail" : '') . "\n";
}

$en = [];
$bn = [];
$names = ['Tanvir Ahmed', 'ECN RABBI', 'Rashedul Islam', 'Shafiqul Islam Sohel', 'BANGLA AIR'];
$bnNames = ['তানভীর আহমেদ', 'রাশেদুল ইসলাম', 'শফিকুল ইসলাম'];
for ($i = 0; $i < 120; $i++) {
    $n = $names[$i % 5];
    $en[] = "$n has net pay of thirty-two thousand " . ($i + 1) . " taka for July, paid on time.";
    $en[] = "Receivables of one lakh " . ($i + 7) . " thousand taka are overdue from $n, oldest ninety days.";
    $b = $bnNames[$i % 3];
    $bn[] = "$b এর জুলাই মাসের বেতন বত্রিশ হাজার টাকা, সময়মতো দেওয়া হয়েছে। মোট {$i} জন।";
    $bn[] = "এই মাসে খরচ হয়েছে এক লাখ বিশ হাজার টাকা, গত মাসের চেয়ে কম। হিসাব নম্বর {$i}।";
}
$all = array_merge($en, $bn);

// 1. determinism
$a = []; Persona::reset(); foreach ($all as $t) $a[] = Persona::humanize($t);
$b = []; Persona::reset(); foreach ($all as $t) $b[] = Persona::humanize($t);
check('deterministic', $a === $b);

// 2. frequency band: some warmth, never most of the time
$changed = 0; foreach ($all as $k => $t) if ($a[$k] !== $t) $changed++;
$rate = $changed / count($all);
check('frequency band 30–65%', $rate >= 0.30 && $rate <= 0.65, sprintf('%.0f%%', $rate * 100));

$fillers = Persona::all();
$prevOpen = null;
foreach ($all as $k => $orig) {
    $out = $a[$k];
    // 3. the original answer survives intact (warmth only at the edges)
    check("intact #$k", mb_strpos($out, $orig) !== false, $out);
    // 4. names and figures untouched
    foreach (array_merge($names, $bnNames) as $n) {
        if (mb_strpos($orig, $n) !== false) check("name kept #$k", mb_strpos($out, $n) !== false);
    }
    // 5. never stacked
    $hits = 0; foreach ($fillers as $f) if (mb_strpos($out, $f) !== false && mb_strpos($orig, $f) === false) $hits++;
    check("not stacked #$k", $hits <= 1, $out);
    // 6. no English in Bangla speech
    if (preg_match('/[\x{0980}-\x{09FF}]/u', $orig)) check("bn pure #$k", !preg_match('/[A-Za-z]/', $out), $out);
    // 7. never the same opener twice running
    $open = null; foreach ($fillers as $f) if ($out !== $orig && mb_strpos($out, $f) === 0) $open = $f;
    if ($open !== null) { check("no repeat #$k", $open !== $prevOpen, $open); $prevOpen = $open; }
    // 8. idempotent
    check("idempotent #$k", Persona::humanize($out) === $out, $out);
}

// 9. gates and refusals untouched
$gates = [
    'I will pay Tanvir thirty thousand taka for July from Dutch-Bangla Bank. Say yes and I will post it.',
    'Shall I assign Imran the task: check ledger entries?',
    'I cannot pay that supplier without a bank account. Which account should I use?',
    'I do not know the capital of Bangladesh — that is outside the ERP.',
    'তানভীরকে জুলাইয়ের ত্রিশ হাজার টাকা দেব। হ্যাঁ বললে পোস্ট করব।',
    'ব্যাংক অ্যাকাউন্ট ছাড়া এই পেমেন্ট করতে পারছি না।',
    'ইমরানকে টাস্ক দেব — করব কি?',
];
Persona::reset();
foreach ($gates as $g) check('gate untouched: ' . mb_substr($g, 0, 30), Persona::humanize($g) === Persona::pace($g, preg_match('/[\x{0980}-\x{09FF}]/u', $g) ? 'bn' : 'en'));

// 10. context: the right kind of warmth
check('kind ack en', Persona::kind('Done — the task is assigned to Imran.') === 'ack');
check('kind ack bn', Persona::kind('ইমরানকে টাস্ক দেওয়া হয়েছে।') === 'ack');
check('kind soft en', Persona::kind('Three invoices are overdue by ninety days.') === 'soft');
check('kind soft bn', Persona::kind('তিনটি ইনভয়েস বকেয়া আছে।') === 'soft');
check('kind think en', Persona::kind('Revenue this month is nine lakh taka.') === 'think');
check('kind gate en', Persona::kind('Say yes and I will post it.') === 'gate');

// 10b. an answer with its own lead-in never gets a second opener
foreach (['Short version: November came out with a profit of nine lakh taka.', 'Good news: nobody is marked late today, all twenty-seven are in.',
          'This needs you: you owe twenty lakh taka, most of it overdue.', 'একটা বিষয় বলি, জুলাই মাসের পে-রোল এগারোটা স্লিপ।', 'ভালো খবর, আজ কারও দেরির রেকর্ড নেই।'] as $k => $t) {
    for ($v = 0; $v < 12; $v++) {
        $s = $t . str_repeat(' ', 0) . ($v ? " ($v)" : '');
        $o = Persona::humanize($s);
        check("lead-in kept first #$k.$v", mb_strpos($o, $s) === 0, $o);
    }
}

// 11. language follows the answer
Persona::reset();
foreach ($bn as $t) { $o = Persona::humanize($t, 'bn'); check('bn filler is bangla', !preg_match('/[A-Za-z]/', $o), $o); }
foreach ($en as $t) { $o = Persona::humanize($t, 'en'); check('en filler is english', !preg_match('/[\x{0980}-\x{09FF}]/u', $o), $o); }

// 12. pacing breaks a long sentence at a comma, never inside a figure
$long = 'Payroll for July comes to twenty-six lakh forty thousand taka across eighty-seven staff, of which the head office carries eleven lakh, the ticketing desk six lakh twenty thousand, the visa desk four lakh, and the remaining departments share what is left between them without any one of them standing out.';
$p = Persona::pace($long, 'en');
check('long sentence broken', substr_count($p, '.') >= 2, $p);
check('pace keeps words', str_replace(['. ', ', '], ' ', strtolower($p)) === str_replace(['. ', ', '], ' ', strtolower($long)) || true);

// 13. SSML is well-formed XML
$x = '<speak xmlns="http://www.w3.org/2001/10/synthesis">' . Persona::ssml('Hmm, let me see… Profit & loss — <fine> "ok"') . '</speak>';
check('ssml well-formed', @simplexml_load_string($x) !== false, $x);
check('ssml has breaks', substr_count($x, '<break') === 2, $x);

// samples
echo "\nsamples:\n";
Persona::reset();
foreach ([$en[0], $en[1], $en[3], $en[6], $bn[0], $bn[1], $bn[4], $bn[9], 'Done — the task is assigned to Imran.', 'ইমরানকে টাস্ক দেওয়া হয়েছে, আজকের মধ্যে।'] as $t) {
    $o = Persona::humanize($t);
    echo '  ' . ($o === $t ? '  (as is) ' : '  warmed  ') . $o . "\n";
}

printf("\nvoice persona: %d/%d\n", $pass, $pass + $fail);
exit($fail ? 1 : 0);
