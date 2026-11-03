<?php

namespace Modules\EON\Intelligence\Analyzers;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Baseline;
use Modules\EON\Intelligence\Support\Period;
use Modules\EON\Intelligence\Support\Signal;
use Modules\EON\Intelligence\Support\Stats;

/**
 * DRAWDOWN — how far below their own best are they, and for how long?
 *
 * The owner asked for this by name, in the language of finance, and it is the
 * right instrument: a drawdown measures the fall from a level the person has
 * ACTUALLY REACHED. Not a target somebody set, not the company average, not
 * what their job description implies. They did 31 in May; they are doing 9 now;
 * that is a 71% drawdown and it is unanswerable, because the 31 is theirs.
 *
 * It is also the fairest possible framing for the difficult conversation.
 * "You are below average" invites an argument about the average. "You are 71%
 * below your own best month, and you have been under it for three months"
 * invites the real question, which is what changed.
 *
 * Adds no pillar. Output already scores the level; this describes the shape of
 * the fall, which is a different thing and belongs in words.
 */
final class DrawdownAnalyzer implements Analyzer
{
    public function key(): string
    {
        return 'drawdown';
    }

    public function analyze(Assessment $a, Baseline $b): void
    {
        // Whole, active months only — the current one is part-finished and
        // would show as a fresh crash on the last day of every month, and the
        // empty calendar before somebody joined is not a peak they fell from.
        $series = $a->activeMonthsCompleted();

        if (count($series) < 3) {
            $a->set('drawdown', null);

            return;
        }

        $dd = Stats::drawdown($series);

        // Months spent below the peak, counting back from the most recent.
        $below = 0;

        foreach (array_reverse($series) as $value) {
            if ($dd['peak'] !== null && $value < $dd['peak'] * 0.9) {
                $below++;
                continue;
            }

            break;
        }

        $dd['months_below'] = $below;
        $dd['peak_month']   = Period::label($dd['peak_label']);
        $dd['current_month'] = Period::label($dd['current_label']);
        $dd['series']       = $series;

        $a->set('drawdown', $dd);

        $fall = $dd['from_peak_pct'];

        if ($fall === null || $dd['peak'] < 3) {
            // A "peak" of two tasks makes every percentage meaningless.
            return;
        }

        if ($fall >= 60 && $below >= 2) {
            $a->addSignal(Signal::critical(
                'drawdown.deep',
                round($fall) . '% below their own best month, and under it for ' . $below . ' months',
                'Peak of ' . round($dd['peak']) . ' completed in ' . $dd['peak_month']
                    . '; ' . round($dd['current']) . ' in ' . $dd['current_month']
                    . '. A fall this long is rarely about one month\'s effort — look for what changed around '
                    . $dd['peak_month'] . '.',
                'output',
                ['from_peak' => $fall, 'months_below' => $below]
            ));
        } elseif ($fall >= 40) {
            $a->addSignal(Signal::watch(
                'drawdown.moderate',
                round($fall) . '% below their peak month of ' . round($dd['peak']) . ' in ' . $dd['peak_month'],
                $dd['recovering']
                    ? 'The last month was better than the one before it, so this is a recovery in progress.'
                    : 'Not yet recovering.',
                'output',
                ['from_peak' => $fall]
            ));
        }

        if ($dd['recovering'] && $fall < 25 && $dd['max_pct'] !== null && $dd['max_pct'] >= 50) {
            $a->addSignal(Signal::good(
                'drawdown.recovered',
                'Back near their best after a ' . round($dd['max_pct']) . '% dip',
                'The deepest point was ' . Period::label($dd['max_label']) . '; they are now within '
                    . round($fall) . '% of their peak.',
                'output'
            ));
        }
    }
}
