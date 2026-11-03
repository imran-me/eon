<?php

namespace Modules\EON\Intelligence\Analyzers;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Baseline;
use Modules\EON\Intelligence\Support\Signal;
use Modules\EON\Intelligence\Support\Stats;

/**
 * TREND — which way is this going?
 *
 * Adds no pillar and no points. A trend is a direction, and direction is not
 * performance: somebody excellent can be gently declining and somebody weak can
 * be climbing hard. Scoring it would double-count the level, which Output has
 * already scored. So this analyzer only ever produces a direction, a rate, and
 * something to say about it.
 *
 * Fitted by least squares over whole months only, and never on fewer than four
 * points. Three months of 5, 3, 5 is not "falling then recovering", it is
 * noise, and a trend arrow drawn on noise is worse than no arrow because
 * somebody will act on it.
 */
final class TrendAnalyzer implements Analyzer
{
    private const RISING  = 'rising';
    private const STEADY  = 'steady';
    private const FALLING = 'falling';

    public function key(): string
    {
        return 'trend';
    }

    public function analyze(Assessment $a, Baseline $b): void
    {
        $months = $a->activeMonthsCompleted();

        // The last six whole months: far enough back to see a direction, near
        // enough that a bad spring does not haunt somebody all year.
        $window = array_slice($months, -6);

        if (count($window) < 4) {
            $a->set('trend', [
                'direction' => null,
                'percent'   => null,
                'slope'     => null,
                'months'    => count($window),
                'label'     => 'Not enough history',
            ]);

            return;
        }

        $slope   = Stats::slope($window);
        $percent = Stats::trendPercent($window);

        // Under 8% a month either way is a flat line with weather on it.
        $direction = match (true) {
            $percent === null    => self::STEADY,
            $percent >= 8.0      => self::RISING,
            $percent <= -8.0     => self::FALLING,
            default              => self::STEADY,
        };

        /* Past about 60% the percentage stops informing and starts looking
           broken — the fitted slope is relative to the mean, so a series like
           25 → 3 legitimately produces "falling 120% a month", which reads as a
           bug to anybody who has not read this file. Beyond that point the
           direction is the whole message. */
        $steep = abs($percent) >= 60;

        $label = match ($direction) {
            self::RISING  => $steep ? 'Rising sharply' : 'Rising ' . round(abs($percent)) . '% a month',
            self::FALLING => $steep ? 'Falling sharply' : 'Falling ' . round(abs($percent)) . '% a month',
            default       => 'Holding steady',
        };

        $a->set('trend', [
            'direction' => $direction,
            'percent'   => $percent,
            'slope'     => $slope,
            'months'    => count($window),
            'values'    => array_values($window),
            'label'     => $label,
        ]);

        if ($direction === self::FALLING && abs($percent) >= 15) {
            $a->addSignal(Signal::critical(
                'trend.falling',
                'Output has been falling for months, about ' . round(abs($percent)) . '% each month',
                'Fitted across ' . count($window) . ' whole months: '
                    . implode(' → ', array_map('intval', array_values($window)))
                    . '. This is a direction, not a single bad month.',
                'output',
                ['percent' => $percent]
            ));
        } elseif ($direction === self::FALLING) {
            $a->addSignal(Signal::watch(
                'trend.easing',
                'Output is drifting downward, about ' . round(abs($percent)) . '% a month',
                implode(' → ', array_map('intval', array_values($window))) . ' over the last ' . count($window) . ' whole months.',
                'output',
                ['percent' => $percent]
            ));
        } elseif ($direction === self::RISING && $percent >= 15) {
            $a->addSignal(Signal::good(
                'trend.rising',
                'Output has been climbing, about ' . round($percent) . '% a month',
                implode(' → ', array_map('intval', array_values($window))) . ' over the last ' . count($window) . ' whole months.',
                'output',
                ['percent' => $percent]
            ));
        }
    }
}
