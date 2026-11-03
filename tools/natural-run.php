<?php
/* ============================================================
   EON · natural-run — the model on questions written the way people talk,
   not from the owner's templates (tools/natural-intents.json). Reports
   top-1, how many it was sure of, and how many it was sure AND right —
   the number that matters, since a sure answer goes out without Gemini.

     php tools/natural-run.php [--model path/to/intent-model.json]
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/../server/lib/IntentModel.php'; require __DIR__ . '/../server/lib/Analyst.php';
$opt = getopt('', ['model:']);
if (!empty($opt['model'])) { $r = new ReflectionProperty('IntentModel', 'm'); $r->setAccessible(true); $r->setValue(null, json_decode((string) file_get_contents($opt['model']), true)); }
$cases = json_decode((string) file_get_contents(__DIR__ . '/natural-intents.json'), true);
$m = IntentModel::confidentMargin(); $ok = 0; $sure = 0; $sureOk = 0;
foreach ($cases as [$q, $want]) {
    $sh = Analyst::shape($q); $top = $sh ? ['intent' => $sh, 'margin' => INF] : IntentModel::rank($q, 2)[0];
    $hit = $top['intent'] === $want || (Analyst::handlerOf($top['intent']) !== null && Analyst::handlerOf($top['intent']) === Analyst::handlerOf($want));   // twin intents, one handler
    $s = $top['margin'] >= $m;
    $ok += $hit ? 1 : 0; $sure += $s ? 1 : 0; $sureOk += ($s && $hit) ? 1 : 0;
    if (!$hit || !$s) printf("%s %-46s → %s (%.1f)%s\n", $hit ? '  ' : '✗ ', mb_substr($q, 0, 46), $top['intent'], $top['margin'], $hit ? '' : "   want: $want");
}
$n = count($cases);
printf("top-1 %d/%d · sure %d · sure AND right %d (%.0f%%) · sure but WRONG %d\n", $ok, $n, $sure, $sureOk, 100 * $sureOk / $n, $sure - $sureOk);
