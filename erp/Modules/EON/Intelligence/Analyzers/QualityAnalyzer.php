<?php

namespace Modules\EON\Intelligence\Analyzers;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Baseline;
use Modules\EON\Intelligence\Scoring\Pillar;
use Modules\EON\Intelligence\Support\Signal;
use Modules\EON\Intelligence\Support\Stats;

/**
 * QUALITY — what the work was like, not how much of it there was.
 *
 * This is the one pillar EON does not really own. The authority's stars in
 * Employee Task Oversight are the actual judgement of quality in this system,
 * and they are manual on purpose. So when ratings exist, they lead: two thirds
 * of this pillar is the human verdict, and EON's own observations are the
 * remaining third.
 *
 * With no ratings on file, EON scores only what it can genuinely see — red
 * flags raised, work cancelled rather than finished — and SAYS SO on the
 * pillar, so nobody reads a machine-only number as though somebody had looked.
 *
 * The rule this class exists to enforce: EON never invents a quality opinion.
 * It can see that something was cancelled. It cannot see whether it was any
 * good. Where it does not know, it says it does not know.
 */
final class QualityAnalyzer implements Analyzer
{
    public function key(): string
    {
        return 'quality';
    }

    public function analyze(Assessment $a, Baseline $b): void
    {
        $v        = $a->verdict;
        $standing = $a->standing;

        $closed        = max(0, $standing->done + $standing->cancelled);
        $cancelRate    = $closed > 0 ? ($standing->cancelled / $closed) * 100 : null;
        $flagRate      = $standing->total > 0 ? ($v->flags / $standing->total) * 100 : null;

        $a->set('quality', [
            'rated'        => $v->rated,
            'avg_rating'   => $v->avg_rating,
            'low_ratings'  => $v->low_ratings,
            'high_ratings' => $v->high_ratings,
            'recent_avg'   => $v->recent_avg,
            'earlier_avg'  => $v->earlier_avg,
            'flags'        => $v->flags,
            'locks'        => $v->locks,
            'cancelled'    => $standing->cancelled,
            'cancel_rate'  => $cancelRate,
            'flag_rate'    => $flagRate,
            'last_rated_at' => $v->last_rated_at,
        ]);

        if (!$a->hasEvidence()) {
            $a->addPillar(new Pillar('quality', 'Quality', null, 20, 'Nothing assigned, so nothing to judge.'));

            return;
        }

        /* EON's own reading: cancellations and flags, both scored downward. */
        $machine = 100.0;
        $notes   = [];

        if ($cancelRate !== null && $closed >= 5) {
            // 0% cancelled is clean; 25% cancelled is a third of the pillar gone.
            $machine -= Stats::clamp($cancelRate * 2, 0, 40);
            $notes[]  = round($cancelRate) . '% of closed work was cancelled';
        }

        if ($v->flags > 0) {
            // A red flag is a deliberate act by a human. It bites hard.
            $machine -= Stats::clamp($v->flags * 12, 0, 45);
            $notes[]  = $v->flags . ' red ' . ($v->flags === 1 ? 'flag' : 'flags');
        }

        $machine = Stats::clamp($machine);

        /* NO RATINGS AND NOTHING VISIBLY WRONG IS "UNKNOWN", NOT 100.
           The first build scored exactly that case as a full 100 — no flags,
           no cancellations, therefore perfect — and since most people here have
           never been rated, a fifth of everybody's score was being handed out
           for the absence of evidence. Quality is the one dimension a machine
           genuinely cannot see, so where there is nothing to go on the pillar
           reports null and the Scorecard re-weights the rest around it.
           Negative evidence still scores: a red flag is somebody's judgement,
           and a cancellation is a fact. */
        if ($v->rated === 0 && $v->flags === 0 && !($cancelRate !== null && $closed >= 5 && $cancelRate > 0)) {
            $a->addPillar(new Pillar(
                key:    'quality',
                label:  'Quality',
                score:  null,
                weight: 20,
                basis:  'Nothing to go on — no manual ratings, no red flags, nothing cancelled. '
                    . 'EON does not award marks for the absence of evidence; rate a few of their tasks and this pillar starts working.',
                facts:  ['rated' => 0],
            ));

            if ($standing->done >= 20) {
                $a->addSignal(Signal::neutral(
                    'quality.unrated',
                    'Nobody has rated any of their work',
                    $standing->done . ' completed tasks and not one manual rating. EON can measure volume and timing on its own; quality needs a person. Open their board and rate a handful.',
                    'quality'
                ));
            }

            return;
        }

        if ($v->rated > 0 && $v->avg_rating !== null) {
            // The stars, on the same 0-100 scale: 1 star = 0, 5 stars = 100.
            $human = Stats::clamp((($v->avg_rating - 1) / 4) * 100);
            $score = ($human * 0.66) + ($machine * 0.34);
            $basis = 'Two thirds from the authority\'s ' . $this->stars($v->avg_rating)
                . ' average across ' . $v->rated . ' rated ' . ($v->rated === 1 ? 'task' : 'tasks')
                . '; one third from what EON can see — ' . ($notes ? implode(' and ', $notes) : 'no flags and a clean cancellation record') . '.';
        } else {
            $score = $machine;
            $basis = 'No manual ratings on record, so this is EON\'s reading alone — '
                . ($notes ? implode(' and ', $notes) : 'no red flags and a clean cancellation record')
                . '. A machine cannot see whether work was any good; rate a few tasks and this pillar starts meaning something.';
        }

        $a->addPillar(new Pillar(
            key:    'quality',
            label:  'Quality',
            score:  $score,
            weight: 20,
            basis:  $basis,
            facts:  [
                'avg_rating'  => $v->avg_rating,
                'rated'       => $v->rated,
                'flags'       => $v->flags,
                'cancel_rate' => $cancelRate === null ? null : round($cancelRate, 1),
                'machine'     => round($machine),
            ],
        ));

        /* ── Findings ──────────────────────────────────────────────────── */

        if ($v->rated === 0 && $standing->done >= 20) {
            $a->addSignal(Signal::neutral(
                'quality.unrated',
                'Nobody has rated any of their work',
                $standing->done . ' completed tasks and not one manual rating. EON can measure volume and timing on its own; quality needs a person. Open their board and rate a handful.',
                'quality'
            ));
        }

        if ($v->flags > 0) {
            $a->addSignal(Signal::critical(
                'quality.flags',
                $v->flags . ' red ' . ($v->flags === 1 ? 'flag' : 'flags') . ' raised on their tasks',
                'Red flags are raised by hand from the oversight board. They are listed in full below, with whoever raised them and why.',
                'quality',
                ['count' => $v->flags]
            ));
        }

        if ($v->locks > 0) {
            $a->addSignal(Signal::watch(
                'quality.locks',
                $v->locks . ' of their tasks are locked against editing',
                'A lock is an administrative decision, not a performance finding — it appears here so the picture is complete.',
                'quality',
                ['count' => $v->locks]
            ));
        }

        if ($v->recent_avg !== null && $v->earlier_avg !== null && $v->rated >= 4) {
            $shift = $v->recent_avg - $v->earlier_avg;

            if ($shift <= -0.75) {
                $a->addSignal(Signal::watch(
                    'quality.rating_falling',
                    'Manual ratings have fallen from ' . $this->stars($v->earlier_avg) . ' to ' . $this->stars($v->recent_avg),
                    'Comparing the last 90 days with everything before it.',
                    'quality'
                ));
            } elseif ($shift >= 0.75) {
                $a->addSignal(Signal::good(
                    'quality.rating_rising',
                    'Manual ratings have risen from ' . $this->stars($v->earlier_avg) . ' to ' . $this->stars($v->recent_avg),
                    'Comparing the last 90 days with everything before it.',
                    'quality'
                ));
            }
        }

        if ($v->avg_rating !== null && $v->avg_rating >= 4.3 && $v->rated >= 5) {
            $a->addSignal(Signal::good(
                'quality.rated_well',
                'Rated ' . $this->stars($v->avg_rating) . ' across ' . $v->rated . ' tasks',
                null,
                'quality'
            ));
        }

        if ($cancelRate !== null && $cancelRate >= 20 && $closed >= 10) {
            $a->addSignal(Signal::watch(
                'quality.cancellations',
                round($cancelRate) . '% of their closed work was cancelled rather than completed',
                $standing->cancelled . ' cancelled against ' . $standing->done . ' completed. Often this is planning upstream rather than the person — worth asking where the cancelled work came from.',
                'quality'
            ));
        }
    }

    /** "4.2★" — short enough to sit inside a sentence. */
    private function stars(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1), '0'), '.') . '★';
    }
}
