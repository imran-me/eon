<?php

namespace Modules\EON\Intelligence\Analyzers;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Baseline;
use Modules\EON\Intelligence\Scoring\Pillar;
use Modules\EON\Intelligence\Support\Signal;
use Modules\EON\Intelligence\Support\Stats;

/**
 * LOAD & SCOPE — how much this person carries, against everybody else
 * (owner, 2026-10-07: "Imran's task list is huge, multi department, even
 * handles Shafi" — yet he scored below someone finishing 15).
 *
 * Output measures a person against their OWN normal; this measures the size
 * and width of the load against the COMPANY, so carrying more counts:
 *   • volume  (75%) — last-30-days completed, as a percentile of everybody's
 *   • breadth (25%) — boards the finished work came from (1 → 25 … 4+ → 100)
 *   • delegation    — up to +10 for work they handed to others that got done
 *                     (a bonus only: nobody loses points for not managing)
 * Null when there is no company cohort to compare with (unknown is not zero).
 */
final class LoadAnalyzer implements Analyzer
{
    public function key(): string
    {
        return 'load';
    }

    public function analyze(Assessment $a, Baseline $b): void
    {
        $done      = (float) $a->rolling->completed;
        $boards    = (int) ($a->rolling->boards ?? 0);
        $companies = (int) ($a->rolling->companies ?? 0);
        $delegated = (int) ($a->rolling->delegated ?? 0);
        $pct       = $b->percentileOfRolling($done);

        $a->set('load', ['completed_30d' => $done, 'boards' => $boards, 'companies' => $companies, 'delegated' => $delegated, 'percentile' => $pct]);

        if (! $a->hasEvidence() || $pct === null) {
            $a->addPillar(new Pillar(
                key: 'load', label: 'Load & scope', score: null, weight: 20,
                basis: 'Not enough active people to compare how much each one carries.',
            ));
            return;
        }

        $breadth = [0 => 0, 1 => 25, 2 => 55, 3 => 80][min($boards, 4)] ?? 100;
        $bonus   = min(10, $delegated);
        $score   = Stats::clamp(0.75 * $pct + 0.25 * $breadth + $bonus);

        $a->addPillar(new Pillar(
            key:    'load',
            label:  'Load & scope',
            score:  $score,
            weight: 20,
            basis:  rtrim(rtrim(number_format($done, 1), '0'), '.') . ' completed in 30 days — more than ' . round($pct) . '% of people; work spread over '
                . $boards . ' board' . ($boards === 1 ? '' : 's')
                . ($companies > 1 ? ' in ' . $companies . ' companies' : '')
                . ($delegated ? '; ' . $delegated . ' task' . ($delegated === 1 ? '' : 's') . ' handed to others and finished' : '') . '.',
            facts:  ['completed_30d' => $done, 'percentile' => round($pct), 'boards' => $boards, 'companies' => $companies, 'delegated' => $delegated],
        ));

        if ($pct >= 85 && $boards >= 3) {
            $a->addSignal(Signal::good('load.wide', 'Carries one of the widest loads', 'Top ' . max(1, 100 - (int) round($pct)) . '% for volume, across ' . $boards . ' boards.', 'load'));
        }
    }
}
