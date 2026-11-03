<?php
/* ============================================================
   EON · own-brain-run — how much of the owner's bank does EON's OWN brain
   carry, with no Gemini at all? Over the held-out tenth of
   tools/owner-100k.json (never trained on), per intent:
     answered   the model was sure AND a handler gave an answer (or an action)
     unsure     the model was not sure — Gemini / the rules would take it
     no-handler the model was sure but no exact handler exists yet
     wrong      the model was sure of a DIFFERENT intent than the owner's label
   The dataset is a snapshot of the live one (pass --data <dataset.json>).

     php tools/own-brain-run.php --data <dataset.json> [--n 2000]
   ============================================================ */
declare(strict_types=1);
foreach (['Phrase', 'Grammar', 'Ops', 'Slots', 'Analyst', 'IntentModel'] as $c) require __DIR__ . "/../server/lib/$c.php";
$opt = getopt('', ['data:', 'n:']);
$d = json_decode((string) file_get_contents($opt['data'] ?? ''), true); $D = $d['data'] ?? $d;
$rows = json_decode((string) file_get_contents(__DIR__ . '/owner-100k.json'), true);
$test = []; foreach ($rows as $i => $r) if ($i % 10 === 0) $test[] = $r;
$n = (int) ($opt['n'] ?? 2000); $step = max(1, intdiv(count($test), $n)); $sample = [];
for ($i = 0; $i < count($test) && count($sample) < $n; $i += $step) $sample[] = $test[$i];
$m = IntentModel::confidentMargin(); $by = []; $tot = ['answered' => 0, 'unsure' => 0, 'no-handler' => 0, 'wrong' => 0];
foreach ($sample as $r) {
    $sh = Analyst::shape($r['q']); $top = $sh ? ['intent' => $sh, 'margin' => INF] : IntentModel::rank($r['q'], 2)[0];
    if ($top['margin'] < $m) $k = 'unsure';
    elseif ($top['intent'] !== $r['intent'] && !$sh) $k = 'wrong';
    else { $a = Analyst::handle($top['intent'], $r['q'], $D, true); $k = $a ? 'answered' : 'no-handler'; }
    $tot[$k]++; $by[$r['intent']][$k] = ($by[$r['intent']][$k] ?? 0) + 1;
}
uasort($by, fn($a, $b) => ($b['no-handler'] ?? 0) + ($b['unsure'] ?? 0) <=> ($a['no-handler'] ?? 0) + ($a['unsure'] ?? 0));
foreach (array_slice($by, 0, 25, true) as $k => $v) printf("%4d ✓ %4d ? %4d · %4d ✗  %s\n", $v['answered'] ?? 0, $v['unsure'] ?? 0, $v['no-handler'] ?? 0, $v['wrong'] ?? 0, $k);
$N = count($sample);
printf("TOTAL %d: answered by EON alone %.1f%% · unsure→Gemini/rules %.1f%% · no handler yet %.1f%% · wrong intent %.1f%%\n", $N, 100 * $tot['answered'] / $N, 100 * $tot['unsure'] / $N, 100 * $tot['no-handler'] / $N, 100 * $tot['wrong'] / $N);
