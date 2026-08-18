<?php
declare(strict_types=1);

/* ============================================================
   instance-matrix — the size of the command space, and how much
   of it answers.

   matrix-run walks subject × verb: the shape of a question. This
   walks the instances too — every employee, party, passenger,
   project, company and account actually in the ERP × every aspect
   that record supports × the phrasings a boss uses × three scripts.

   That product is the real number. "Imran's payroll" and "payroll
   of Rashedul" are not one test each: they are two points in a
   space this prints the size of, then samples to prove coverage.

     php tools/instance-matrix.php               size + a 400-point sample
     php tools/instance-matrix.php --sample 2000 a bigger sample
     php tools/instance-matrix.php --all         every point (slow)
     php tools/instance-matrix.php --fail        list what misses
     php tools/instance-matrix.php --json out    machine-readable
   ============================================================ */

require_once __DIR__ . '/../server/bootstrap.php';

$args = $argv ?? [];
$flag = fn(string $f) => in_array($f, $args, true);
$val  = function (string $f, $d) use ($args) { $i = array_search($f, $args, true); return $i === false ? $d : ($args[$i + 1] ?? $d); };
$sample = $flag('--all') ? PHP_INT_MAX : (int) $val('--sample', 400);
$onlyFail = $flag('--fail');
$jsonOut  = $flag('--json') ? (string) $val('--json', __DIR__ . '/instance-matrix.json') : null;

fwrite(STDERR, "loading dataset…\n");
$D = Dataset::current(null, false);
$T = new Tools($D, null);
Nlu::useDataset($D);

/* ---------- the phrasings a boss uses for (aspect, name) ---------- */

$PHRASINGS = [
    'en' => [
        '{a} of {n}', "{n}'s {a}", 'what is {n} {a}', 'give me {n} {a}', 'show me {n} {a}',
        'take me to {n} {a}', 'i want {n} {a}', 'tell me about {n} {a}',
    ],
    'bn' => [
        '{n}-এর {a}', '{n} এর {a} কত', '{n} এর {a} দেখাও', '{n} এর {a} দাও',
        '{n} এর {a} বলো', '{n} এর {a} নিয়ে চলো',
    ],
    'bl' => [
        '{n} er {a}', '{n} er {a} koto', '{n} er {a} dekhao', '{n} er {a} dao', '{n} er {a} bolo',
    ],
];

/* ---------- which aspects each kind of record supports ---------- */

$KIND_ASPECTS = [
    'employee'  => ['profile', 'payroll', 'payslip', 'attendance', 'lateness', 'leave', 'loan', 'advance', 'task', 'project', 'request', 'evaluation', 'ledger', 'contact', 'resignation', 'department'],
    'party'     => ['ledger', 'profile', 'contact'],
    'passenger' => ['profile', 'contact', 'ledger'],
    'project'   => ['profile', 'task', 'department'],
    'company'   => ['profile', 'payroll', 'ledger'],
    'account'   => ['ledger', 'profile'],
];

/* ---------- the instances the ERP actually holds ---------- */

$instances = [];
foreach ($D['employees'] ?? [] as $r) if (($r['status'] ?? '') === 'active') $instances[] = ['kind' => 'employee', 'id' => $r['id'], 'name' => (string) $r['name']];
foreach ($D['customers'] ?? [] as $r) $instances[] = ['kind' => 'party', 'id' => $r['id'], 'name' => (string) $r['name']];
foreach ($D['suppliers'] ?? [] as $r) $instances[] = ['kind' => 'party', 'id' => $r['id'], 'name' => (string) $r['name']];
foreach ($D['passport_holders'] ?? [] as $r) $instances[] = ['kind' => 'passenger', 'id' => $r['id'], 'name' => (string) $r['name']];
foreach ($D['projects'] ?? [] as $r) $instances[] = ['kind' => 'project', 'id' => $r['id'], 'name' => (string) $r['project_name']];
foreach ($D['companies'] ?? [] as $r) $instances[] = ['kind' => 'company', 'id' => $r['id'], 'name' => (string) $r['name']];
foreach ($D['accounts'] ?? [] as $r) $instances[] = ['kind' => 'account', 'id' => $r['code'], 'name' => (string) $r['name']];
$instances = array_values(array_filter($instances, fn($i) => mb_strlen(trim($i['name'])) >= 4));

/* ---------- the size of the space ---------- */

$points = [];
foreach ($instances as $inst) {
    foreach ($KIND_ASPECTS[$inst['kind']] ?? ['profile'] as $aspect) {
        foreach ($PHRASINGS as $lang => $forms) {
            foreach ($forms as $form) $points[] = ['inst' => $inst, 'aspect' => $aspect, 'lang' => $lang, 'form' => $form];
        }
    }
}
$size = count($points);

// the type-level space rides on top of it
$typeSize = count(Grammar::pairs()) * 3;

echo "\nEON command space\n" . str_repeat('=', 74) . "\n";
printf("  instances in the ERP        %6d  (%s)\n", count($instances),
    implode(', ', array_map(fn($k) => $k . ' ' . count(array_filter($instances, fn($i) => $i['kind'] === $k)), array_keys($KIND_ASPECTS))));
printf("  aspects per record          %6d  max\n", max(array_map('count', $KIND_ASPECTS)));
printf("  phrasings per aspect        %6d  (%s)\n", array_sum(array_map('count', $PHRASINGS)),
    implode(', ', array_map(fn($l) => $l . ' ' . count($PHRASINGS[$l]), array_keys($PHRASINGS))));
printf("  ------------------------------------\n");
printf("  instance-level sentences    %6d\n", $size);
printf("  type-level sentences        %6d  (subject × verb × script)\n", $typeSize);
printf("  TOTAL COMMAND SPACE         %6d\n", $size + $typeSize);
echo str_repeat('=', 74) . "\n";

/* ---------- sample it ---------- */

if ($sample < $size) {
    // deterministic spread: walk with a stride so every kind and aspect is hit
    $stride = max(1, (int) floor($size / $sample));
    $picked = [];
    for ($i = 0; $i < $size && count($picked) < $sample; $i += $stride) $picked[] = $points[$i];
    $points = $picked;
}
printf("\nprobing %d of %d instance-level sentences…\n\n", count($points), $size);

$FALLBACK = ['did not catch', 'did not match', 'ধরতে পারিনি', 'বুঝতে পারিনি', 'could not match'];
$aspectWord = function (string $aspect, string $lang): string {
    $w = Grammar::ASPECTS[$aspect][$lang][0] ?? (Grammar::ASPECTS[$aspect]['en'][0] ?? $aspect);
    return (string) $w;
};

$rows = [];
foreach ($points as $pt) {
    $q = str_replace(['{n}', '{a}'], [$pt['inst']['name'], $aspectWord($pt['aspect'], $pt['lang'])], $pt['form']);
    $got = null; $text = ''; $err = null;
    try {
        $r = Brain::askOffline($q, $D, null, $T, $pt['lang'] === 'en' ? 'en-US' : 'bn-BD');
        $got = $r['intent'] ?? null; $text = (string) ($r['text'] ?? '');
    } catch (Throwable $e) { $err = $e->getMessage(); }

    $isFallback = false;
    foreach ($FALLBACK as $f) if ($text !== '' && mb_stripos($text, $f) !== false) { $isFallback = true; break; }
    // did the answer actually name the record asked about?
    $named = $text !== '' && mb_stripos($text, mb_substr(trim($pt['inst']['name']), 0, 12)) !== false;
    $pass = $err === null && $text !== '' && !$isFallback && $named;

    $rows[] = ['kind' => $pt['inst']['kind'], 'aspect' => $pt['aspect'], 'lang' => $pt['lang'], 'q' => $q,
               'intent' => $got, 'pass' => $pass,
               'why' => $err ? 'threw' : ($text === '' ? 'empty' : ($isFallback ? 'fallback' : (!$named ? 'answered without naming the record' : ''))),
               'text' => mb_substr($text, 0, 120)];
}

$bar = fn(int $p, int $t) => str_repeat('█', $t ? (int) round($p / $t * 20) : 0) . str_repeat('░', 20 - ($t ? (int) round($p / $t * 20) : 0));
$group = function (string $key) use ($rows) {
    $g = [];
    foreach ($rows as $r) { $k = (string) $r[$key]; $g[$k]['pass'] = ($g[$k]['pass'] ?? 0) + ($r['pass'] ? 1 : 0); $g[$k]['total'] = ($g[$k]['total'] ?? 0) + 1; }
    ksort($g); return $g;
};

if (!$onlyFail) {
    foreach (['kind', 'aspect', 'lang'] as $dim) {
        echo strtoupper($dim) . "\n";
        foreach ($group($dim) as $k => $v) printf("  %-16s %s %3d/%-3d %5.1f%%\n", mb_substr($k, 0, 15), $bar($v['pass'], $v['total']), $v['pass'], $v['total'], $v['total'] ? $v['pass'] / $v['total'] * 100 : 0);
        echo "\n";
    }
}

$pass = count(array_filter($rows, fn($r) => $r['pass']));
$total = count($rows);
printf("SAMPLE %d/%d  %.1f%%   → the space answers about %s of %s sentences\n",
    $pass, $total, $total ? $pass / $total * 100 : 0,
    number_format((int) round($size * ($total ? $pass / $total : 0))), number_format($size));

$fails = array_values(array_filter($rows, fn($r) => !$r['pass']));
if ($fails) {
    echo "\nmisses (" . count($fails) . " of $total sampled):\n";
    foreach (array_slice($fails, 0, $onlyFail ? 400 : 25) as $f) {
        printf("  [%s] %-52s %-14s %s\n", $f['lang'], mb_substr($f['q'], 0, 51), $f['aspect'], $f['why']);
    }
}
if ($jsonOut) { @file_put_contents($jsonOut, json_encode(['size' => $size, 'type_size' => $typeSize, 'sampled' => $total, 'pass' => $pass, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); echo "\nwrote $jsonOut\n"; }
