<?php
/* ============================================================
   EON · decision plug-in — a company whose health score has slipped.

   Same model as the browser panel (lib/health-model.php), so the
   morning brief raises the same companies the leaderboard shows.

     score < 40  → severity 4 (high)
     score < 55  → severity 3 (medium)

   Only raised for the whole group view: when the boss is already
   scoped to one company, that company's own layers say the same
   things in more detail and this would only repeat them.
   ============================================================ */
declare(strict_types=1);

require_once dirname(__DIR__) . '/health-model.php';

return function (array $D, ?int $company, Analytics $A): array {
    $today = $A->today();
    $out = [];

    if ($company !== null) {                      // scoped view: one line, only when it is bad
        $r = eon_health_score($D, $company, $today);
        if (!empty($r['insufficient_data']) || $r['score'] >= 55) return [];
        return [[
            'layer' => 'finance',
            'severity' => $r['score'] < 40 ? 4 : 3,
            'severity_label' => $r['score'] < 40 ? 'high' : 'medium',
            'title' => sprintf('%s health score %d (%s) — %s', $r['company'], $r['score'], $r['grade'], $r['top_driver']),
            'why' => array_merge(
                array_map(fn($d) => $d['text'], $r['drivers']),
                [sprintf('finance %s · people %s · sales %s · operations %s', $r['sub']['finance'], $r['sub']['people'], $r['sub']['sales'], $r['sub']['ops'])]
            ),
            'recommend' => sprintf('Start with %s — it costs the score the most.', strtolower($r['drivers'][0]['text'] ?? 'the weakest part')),
            'amount' => 0,
            'tag' => 'health',
        ]];
    }

    $all = eon_health_all($D, $today);
    foreach ($all['companies'] as $r) {
        if (!empty($r['insufficient_data']) || $r['score'] >= 55) continue;
        $out[] = [
            'layer' => 'finance',
            'severity' => $r['score'] < 40 ? 4 : 3,
            'severity_label' => $r['score'] < 40 ? 'high' : 'medium',
            'title' => sprintf('%s health score %d (%s) — %s', $r['company'], $r['score'], $r['grade'], $r['top_driver']),
            'why' => array_merge(
                array_map(fn($d) => $d['text'], $r['drivers']),
                [sprintf('finance %s · people %s · sales %s · operations %s', $r['sub']['finance'], $r['sub']['people'], $r['sub']['sales'], $r['sub']['ops'])]
            ),
            'recommend' => sprintf('Start with %s — it costs the score the most.', strtolower($r['drivers'][0]['text'] ?? 'the weakest part')),
            'amount' => 0,
            'tag' => 'health',
        ];
    }
    // one summary line when several are weak at once
    $weak = array_values(array_filter($all['companies'], fn($r) => empty($r['insufficient_data']) && $r['score'] < 55));
    if (count($weak) >= 3) {
        array_unshift($out, [
            'layer' => 'finance',
            'severity' => 3,
            'severity_label' => 'medium',
            'title' => sprintf('%d of %d companies score below 55 — group health %d (%s)', count($weak), count($all['companies']), $all['group']['score'], $all['group']['grade']),
            'why' => array_map(fn($r) => sprintf('%s %d (%s)', $r['short_name'], $r['score'], $r['grade']), array_slice($all['companies'], 0, 5)),
            'recommend' => sprintf('%s is weakest — %s', $weak[0]['company'], $weak[0]['top_driver']),
            'amount' => 0,
            'tag' => 'health',
        ]);
    }
    return $out;
};
