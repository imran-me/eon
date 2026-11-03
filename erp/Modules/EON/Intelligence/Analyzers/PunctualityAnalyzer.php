<?php

namespace Modules\EON\Intelligence\Analyzers;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Baseline;
use Modules\EON\Intelligence\Scoring\Pillar;
use Modules\EON\Intelligence\Support\Signal;
use Modules\EON\Intelligence\Support\Stats;

/**
 * TIMELINESS — does the work land when it was promised?
 *
 * The heaviest pillar, at 30, because a deadline is the one expectation the
 * task itself records. Everything else EON measures is inferred; this is
 * written down.
 *
 * HOW "ON TIME" IS DEFINED, and why it is not the obvious thing:
 * due dates in this install carry a time of day, and 3,096 completed tasks
 * landed on the right DAY but after the stated hour. Judged by the timestamp,
 * four fifths of all work here is late — a number so large it stops
 * distinguishing anybody. So EON scores on the calendar day, and counts
 * "finished the right day but after the hour" separately as a softer reading
 * that a manager can look at if they want to run a tighter clock.
 *
 * Two things are scored, not one:
 *   • the hit rate on work already delivered, and
 *   • the state of what is still open and already past due.
 * A person can post a perfect on-time record by simply never closing the hard
 * items. Weighting the standing backlog at a third of the pillar is what stops
 * that from reading as excellence.
 */
final class PunctualityAnalyzer implements Analyzer
{
    public function key(): string
    {
        return 'punctuality';
    }

    public function analyze(Assessment $a, Baseline $b): void
    {
        $overall  = $a->onTimeRateOverall();
        $recent   = $a->onTimeRate($a->mtd);
        $previous = $a->onTimeRate($a->prev);

        $withDue  = 0;
        $lateHour = 0;
        $lateDays = [];

        foreach ($a->series as $m) {
            $withDue  += (int) ($m['with_due'] ?? 0);
            $lateHour += (int) ($m['late_by_hour'] ?? 0);

            if (($m['late_days'] ?? null) !== null) {
                $lateDays[] = (float) $m['late_days'];
            }
        }

        $avgLateDays = $lateDays ? Stats::median($lateDays) : null;
        $standing    = $a->standing;

        $a->set('timeliness', [
            'overall'       => $overall,
            'mtd'           => $recent,
            'prev'          => $previous,
            'with_due'      => $withDue,
            'late_by_hour'  => $lateHour,
            'avg_late_days' => $avgLateDays,
            'peer_median'   => $b->reliable() ? $b->medianOnTime : null,
            'percentile'    => $overall !== null ? $b->percentileOfOnTime($overall) : null,
            'unmarked_done'    => $standing->unmarked_done ?? 0,
            'unmarked_overdue' => $standing->unmarked_overdue ?? 0,
            'overdue'       => [
                'total' => $standing->overdue,
                'week'  => $standing->overdue_week,
                'month' => $standing->overdue_month,
                'older' => $standing->overdue_older,
                'oldest_days' => $standing->oldest_overdue,
            ],
        ]);

        if ($withDue < 3) {
            $a->addPillar(new Pillar(
                key:    'timeliness',
                label:  'Timeliness',
                score:  null,
                weight: 30,
                basis:  $withDue === 0
                    ? 'None of their completed work carried a due date, so there is no promise to measure against.'
                    : 'Only ' . $withDue . ' completed items carried a due date — too few to read as a rate.',
                facts:  ['with_due' => $withDue],
            ));

            if ($a->standing->done >= 10 && $withDue === 0) {
                $a->addSignal(Signal::neutral(
                    'timeliness.no_dates',
                    'Their work carries no due dates',
                    'Timeliness cannot be evaluated for someone whose tasks are created without deadlines. That is a process gap, not a performance one — and it is worth closing, because it is the only expectation the system records.',
                    'timeliness'
                ));
            }

            return;
        }

        /* Delivered on time: a straight rate, 90% and up is full marks, 40%
           and below is none. The floor is not zero because a person handed
           impossible dates still delivers work. */
        $deliveryScore = Stats::clamp((($overall ?? 0) - 40) / (90 - 40) * 100);

        /* The standing backlog, scored by AGE not count. One thing a week late
           is a slip; one thing forty days late is a decision to ignore it. */
        $backlogScore = $this->backlogScore($standing);

        $score = ($deliveryScore * 0.66) + ($backlogScore * 0.34);

        $a->addPillar(new Pillar(
            key:    'timeliness',
            label:  'Timeliness',
            score:  $score,
            weight: 30,
            basis:  'Two thirds from the ' . round($overall ?? 0) . '% of ' . $withDue
                . ' dated items delivered by their due date; one third from the age of the '
                . $standing->overdue . ' items still open past due.',
            facts:  [
                'on_time_rate'   => $overall === null ? null : round($overall, 1),
                'delivery_score' => round($deliveryScore),
                'backlog_score'  => round($backlogScore),
                'overdue'        => $standing->overdue,
            ],
        ));

        /* ── Findings ──────────────────────────────────────────────────── */

        if ($standing->overdue_older > 0) {
            $a->addSignal(Signal::critical(
                'timeliness.stale_overdue',
                $standing->overdue_older . ' ' . $this->plural($standing->overdue_older, 'item is', 'items are') . ' more than a month past due',
                $standing->oldest_overdue !== null
                    ? 'The oldest has been overdue for ' . $standing->oldest_overdue . ' days. Work this old is usually either blocked or abandoned, and both need a decision rather than another week.'
                    : null,
                'timeliness',
                ['count' => $standing->overdue_older]
            ));
        } elseif ($standing->overdue > 0 && $b->reliable() && $standing->overdue > max(3, $b->medianOverdue * 2)) {
            $a->addSignal(Signal::watch(
                'timeliness.overdue_high',
                $standing->overdue . ' items open past due, against a company median of ' . round($b->medianOverdue),
                'None older than a month, so this reads as a pile-up rather than neglect.',
                'timeliness'
            ));
        }

        if ($overall !== null && $b->reliable() && $b->medianOnTime !== null && $overall < $b->medianOnTime - 15) {
            $a->addSignal(Signal::watch(
                'timeliness.below_peers',
                'On-time rate of ' . round($overall) . '% against a company median of ' . round($b->medianOnTime) . '%',
                $avgLateDays !== null
                    ? 'When they are late, the typical overrun is about ' . $this->days($avgLateDays) . '.'
                    : null,
                'timeliness'
            ));
        }

        if ($overall !== null && $overall >= 90 && $withDue >= 10) {
            $a->addSignal(Signal::good(
                'timeliness.reliable',
                round($overall) . '% of dated work delivered on time',
                'Across ' . $withDue . ' items with a deadline. This is the kind of record that makes someone safe to give the dated work to.',
                'timeliness'
            ));
        }

        if ($recent !== null && $previous !== null && $previous - $recent >= 25 && $a->mtd->with_due >= 3) {
            $a->addSignal(Signal::watch(
                'timeliness.slipping',
                'On-time rate has slipped from ' . round($previous) . '% to ' . round($recent) . '% this month',
                'Same days of the month, so the comparison is like for like.',
                'timeliness'
            ));
        }

        /* The recording gap. Raised as NEUTRAL, not as a fault: a card sitting
           in DONE with no completion stamp is almost always finished work that
           nobody clicked, and treating it as lateness would mark most of this
           workforce down for a habit rather than for a performance. It is
           excluded from every overdue figure above — this is where it surfaces
           instead, because a number that quietly disappears is worse than one
           that is wrong. */
        if (($standing->unmarked_done ?? 0) > 0) {
            $a->addSignal(Signal::neutral(
                'timeliness.unmarked_done',
                $standing->unmarked_done . ' ' . $this->plural($standing->unmarked_done, 'task sits', 'tasks sit')
                    . ' in the DONE column without ever being marked complete',
                ($standing->unmarked_overdue ?? 0) > 0
                    ? $standing->unmarked_overdue . ' of them are past their due date. EON does not count these as overdue and does not count them as completed either — the record simply does not say. Clearing them is a click each, and it is the single cheapest way to make every number on this page sharper.'
                    : 'EON counts these as neither overdue nor completed, because the record does not say which they are.',
                'timeliness',
                ['count' => $standing->unmarked_done]
            ));
        }

        // The softer clock, reported so nobody has to wonder whether EON saw it.
        if ($lateHour > 0 && $withDue > 0) {
            $share = ($lateHour / $withDue) * 100;

            if ($share >= 25) {
                $a->addSignal(Signal::neutral(
                    'timeliness.after_hour',
                    round($share) . '% of their work landed on the due day but after the stated hour',
                    'EON counts these as on time, because due dates here are set with a time of day that is rarely meant as a hard deadline. Raised only so the choice is visible.',
                    'timeliness',
                    ['count' => $lateHour]
                ));
            }
        }
    }

    /**
     * 100 for a clean backlog, falling as the overdue work ages. Weighted by
     * age band, because a month-old overdue item is not three times worse than
     * a week-old one — it is a different kind of problem.
     */
    private function backlogScore(object $standing): float
    {
        $penalty = ($standing->overdue_week * 2)
            + ($standing->overdue_month * 6)
            + ($standing->overdue_older * 14);

        // 50 points of penalty is a backlog nobody could defend: four items a
        // month past due, or three older than that.
        return Stats::clamp(100 - ($penalty / 50 * 100));
    }

    private function plural(int $n, string $one, string $many): string
    {
        return $n === 1 ? $one : $many;
    }

    private function days(float $v): string
    {
        if ($v < 1) {
            return round($v * 24) . ' hours';
        }

        return round($v, 1) . ' days';
    }
}
