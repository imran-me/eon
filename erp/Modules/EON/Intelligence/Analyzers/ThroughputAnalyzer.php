<?php

namespace Modules\EON\Intelligence\Analyzers;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Baseline;
use Modules\EON\Intelligence\Scoring\Pillar;
use Modules\EON\Intelligence\Support\Signal;
use Modules\EON\Intelligence\Support\Stats;

/**
 * OUTPUT — how much work this person is actually finishing.
 *
 * Scored against two references, and the order matters:
 *
 *   1. THEIR OWN median month, when they have three or more whole months of
 *      history. This is the fair reading. A designer who closes five detailed
 *      items a month is doing their job; comparing them with a coordinator who
 *      closes fifty measures job description, not performance.
 *   2. The company median, only for people too new to have a history of their
 *      own, and only when the cohort is big enough to have one.
 *
 * The comparison is always month-to-date against the SAME days of last month.
 * Comparing 30 days of August with all of July is the single easiest way to
 * manufacture a collapse that is really just the calendar.
 *
 * Load is reported next to output, never subtracted from it: somebody closing
 * eight of the twelve things that landed on them is a different story from
 * somebody closing eight of eight, and only a human should decide which.
 */
final class ThroughputAnalyzer implements Analyzer
{
    public function key(): string
    {
        return 'throughput';
    }

    public function analyze(Assessment $a, Baseline $b): void
    {
        /* Their own months, with the empty run before they arrived removed —
           see Assessment::activeMonthsCompleted() for why that trim is not
           cosmetic. */
        $history   = array_values($a->activeMonthsCompleted());
        $ownMedian = count($history) >= 3 ? Stats::median($history) : null;

        $mtd    = (float) $a->mtd->completed;
        $prev   = (float) $a->prev->completed;
        $days   = max(1, (int) ($a->mtdWindow['days'] ?? 1));

        // What the SCORE is built on: a rolling month, so the reading means the
        // same thing on the 1st as on the 28th. The calendar comparison below
        // is reported, not scored.
        $rolling     = (float) $a->rolling->completed;
        $rollingPrev = (float) $a->rollingPrev->completed;

        // Where the month is heading if the current pace holds. Reported, never
        // scored — a projection is a guess and must not become a verdict.
        $daysInMonth = (int) ($a->mtdWindow['days_in_month'] ?? 30);
        $projected   = $days > 0 ? ($mtd / $days) * $daysInMonth : null;

        $a->set('output', [
            'mtd'         => $mtd,
            'prev'        => $prev,
            'change'      => Stats::change($prev, $mtd),
            'rolling'     => $rolling,
            'rolling_prev' => $rollingPrev,
            'rolling_change' => Stats::change($rollingPrev, $rolling),
            'own_median'  => $ownMedian,
            'projected'   => $projected === null ? null : round($projected, 1),
            'peer_median' => $b->reliable() ? $b->medianMonthly : null,
            'percentile'  => $b->reliable() && $ownMedian !== null ? $b->percentileOfMonthly($ownMedian) : null,
            'created_mtd' => (int) $a->mtd->created,
        ]);

        if (!$a->hasEvidence()) {
            $a->addPillar(new Pillar(
                key:    'output',
                label:  'Output',
                score:  null,
                weight: 25,
                basis:  'No tasks are assigned to this person, so there is nothing to measure.',
            ));

            return;
        }

        /* The reference to score against, and the sentence explaining it. Both
           sides of the comparison are a month long — the last 30 days against
           their median month — so there is no pro-rating to get wrong. */
        if ($ownMedian !== null && $ownMedian > 0) {
            $target = $ownMedian;
            $basis  = $this->n($rolling) . ' completed in the last 30 days, against their own median month of '
                . $this->n($ownMedian) . '.';
        } elseif ($b->reliable() && $b->medianMonthly > 0) {
            $target = $b->medianMonthly;
            $basis  = 'Too little history of their own, so measured against the company\'s median month of '
                . $this->n($b->medianMonthly) . ' completed — ' . $this->n($rolling)
                . ' in their last 30 days.';
        } else {
            $a->addPillar(new Pillar(
                key:    'output',
                label:  'Output',
                score:  null,
                weight: 25,
                basis:  'Not enough history — neither this person nor the company has a settled monthly level to compare against yet.',
                facts:  ['completed_mtd' => $mtd],
            ));

            $a->addSignal(Signal::neutral(
                'output.no_baseline',
                'Not enough history to judge output yet',
                'EON needs three whole months, or a company median built from at least five active people, before it will score how much somebody is producing.',
                'output'
            ));

            return;
        }

        $score = Stats::scoreRatio($rolling, $target);

        $a->addPillar(new Pillar(
            key:    'output',
            label:  'Output',
            score:  $score,
            weight: 25,
            basis:  $basis,
            facts:  [
                'completed_30d' => $rolling,
                'completed_mtd' => $mtd,
                'target'        => round($target, 1),
                'own_median'    => $ownMedian,
            ],
        ));

        /* ── What is worth saying about it ─────────────────────────────── */

        $change = Stats::change($prev, $mtd);

        if ($change !== null && $change <= -35 && $prev >= 3) {
            /* A month-on-month fall is only CRITICAL when the rolling reading
               agrees. Otherwise it is a fall from an exceptional month, and
               calling it critical produced a page that said "output down 45%"
               in red directly above "output has been climbing 17% a month" and
               a green 'among the highest in the company' — three true
               statements arranged to look like a contradiction. Whoever read
               that would stop trusting the rest of the page, correctly. */
            $stillStrong = $ownMedian !== null && $rolling >= $ownMedian * 0.75;

            $detail = $this->n($mtd) . ' completed in the first ' . $days . ' days of '
                . ($a->mtdWindow['label'] ?? 'this month') . ', against ' . $this->n($prev)
                . ' over the same days of ' . ($a->prevWindow['label'] ?? 'last month') . '.';

            $a->addSignal($stillStrong
                ? Signal::watch(
                    'output.drop',
                    'Output down ' . $this->pct(abs($change)) . ' on the same days last month',
                    $detail . ' Their last 30 days are still around their normal level, so this is a fall from an exceptional month rather than a slowdown.',
                    'output',
                    ['change' => $change]
                )
                : Signal::critical(
                    'output.drop',
                    'Output down ' . $this->pct(abs($change)) . ' on the same days last month',
                    $detail,
                    'output',
                    ['change' => $change]
                ));
        } elseif ($change !== null && $change >= 35 && $mtd >= 3) {
            $a->addSignal(Signal::good(
                'output.rise',
                'Output up ' . $this->pct($change) . ' on the same days last month',
                $this->n($mtd) . ' completed against ' . $this->n($prev) . ' over the same days.',
                'output',
                ['change' => $change]
            ));
        }

        // Rolling, not calendar: "nothing completed this month" fired on every
        // person alive on the 1st and 2nd, which made it noise rather than a
        // finding. Thirty quiet days is a fact regardless of the date.
        if ($ownMedian !== null && $ownMedian >= 3 && $rolling === 0.0) {
            $a->addSignal(Signal::critical(
                'output.stalled',
                'Nothing completed in the last 30 days',
                'Their normal month closes about ' . $this->n($ownMedian) . ' items.',
                'output'
            ));
        }

        if ($b->reliable() && $ownMedian !== null) {
            $percentile = $b->percentileOfMonthly($ownMedian);

            if ($percentile !== null && $percentile >= 85) {
                $a->addSignal(Signal::good(
                    'output.top',
                    'Among the highest output in the company',
                    'Their median month sits above ' . $this->pct($percentile) . ' of the people EON evaluates.',
                    'output'
                ));
            }
        }

        // Arriving faster than it is leaving — a backlog forming in real time.
        if ($a->rolling->created >= 5 && $rolling > 0 && $a->rolling->created >= $rolling * 2) {
            $a->addSignal(Signal::watch(
                'output.inflow',
                'Work is arriving about twice as fast as it is being closed',
                (int) $a->rolling->created . ' new items in the last 30 days against ' . $this->n($rolling)
                    . ' completed. Read alongside the backlog below before treating it as a performance question.',
                'output'
            ));
        }
    }

    private function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1), '0'), '.');
    }

    private function pct(float $v): string
    {
        return round($v) . '%';
    }
}
