<?php

namespace Modules\EON\Intelligence\Analyzers;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Baseline;
use Modules\EON\Intelligence\Scoring\Pillar;
use Modules\EON\Intelligence\Support\Signal;
use Modules\EON\Intelligence\Support\Stats;

/**
 * CONSISTENCY — can you predict what next month looks like?
 *
 * Two people can average the same output and be worth entirely different things
 * to a schedule: one closes eleven or twelve every month, the other closes
 * thirty-one, then two, then nothing, then twelve. The second is far harder to
 * plan around, and no other pillar notices the difference.
 *
 * Measured with the coefficient of variation — the spread as a share of the
 * average — so it scales: 4±2 a month and 40±20 are read as equally erratic,
 * which is the point.
 *
 * The deliberate limitation: this pillar cannot tell an erratic worker from
 * somebody whose WORK is erratic by nature. A person handling escalations
 * should look spiky. It is weighted at 15 for exactly that reason, and the
 * basis line says what it measured so a manager can dismiss it knowingly.
 */
final class ConsistencyAnalyzer implements Analyzer
{
    public function key(): string
    {
        return 'consistency';
    }

    public function analyze(Assessment $a, Baseline $b): void
    {
        // Whole months only. The current month is part-finished and would read
        // as a crash every single time.
        $months = $a->activeMonthsCompleted();

        $active = count($months);

        $a->set('consistency', [
            'months'       => $active,
            'values'       => array_values($months),
            'cv'           => $active >= 3 ? Stats::coefficientOfVariation($months) : null,
            'silent_months' => count(array_filter($months, fn ($v) => (int) $v === 0)),
        ]);

        if ($active < 3) {
            $a->addPillar(new Pillar(
                key:    'consistency',
                label:  'Consistency',
                score:  null,
                weight: 15,
                basis:  'Only ' . $active . ' whole ' . ($active === 1 ? 'month' : 'months')
                    . ' of history — three are needed before a rhythm can be called one.',
            ));

            return;
        }

        $cv     = Stats::coefficientOfVariation($months);
        $silent = count(array_filter($months, fn ($v) => (int) $v === 0));

        // CV of 0.25 is metronomic; 1.0 is all over the place.
        $score = Stats::scoreInverse($cv, 0.25, 1.0);

        // Silent months are not spread, they are absence, and they cost extra.
        $score = Stats::clamp($score - ($silent * 18));

        $a->addPillar(new Pillar(
            key:    'consistency',
            label:  'Consistency',
            score:  $score,
            weight: 15,
            basis:  'Month-to-month variation across ' . $active . ' whole months ('
                . implode(', ', array_map('intval', array_values($months))) . ')'
                . ($silent ? ', including ' . $silent . ' with nothing completed' : '') . '.',
            facts:  [
                'cv'      => $cv === null ? null : round($cv, 2),
                'months'  => $active,
                'silent'  => $silent,
                'median'  => Stats::median($months),
            ],
        ));

        if ($silent >= 2) {
            $a->addSignal(Signal::watch(
                'consistency.silent',
                $silent . ' whole months with nothing completed',
                'Out of ' . $active . ' months on record. Worth checking whether the work moved elsewhere, or whether the board simply stopped being used.',
                'consistency'
            ));
        }

        if ($cv !== null && $cv >= 0.9) {
            $a->addSignal(Signal::watch(
                'consistency.erratic',
                'Output swings widely from month to month',
                'Monthly completions of ' . implode(', ', array_map('intval', array_values($months)))
                    . '. Some roles are like that by nature — escalations, seasonal work — so read this beside what they actually do.',
                'consistency'
            ));
        } elseif ($cv !== null && $cv <= 0.3 && Stats::median($months) >= 3) {
            $a->addSignal(Signal::good(
                'consistency.steady',
                'Steady month after month',
                'Around ' . round(Stats::median($months)) . ' completed a month with little variation — the kind of record a schedule can be built on.',
                'consistency'
            ));
        }
    }
}
