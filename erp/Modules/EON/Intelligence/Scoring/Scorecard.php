<?php

namespace Modules\EON\Intelligence\Scoring;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Support\Stats;

/**
 * Turns five pillars into one number — carefully, because this is the number
 * that will end up beside somebody's name in a meeting.
 *
 * Three rules govern it, and each exists to stop a specific unfairness:
 *
 * 1. UNKNOWN IS NOT ZERO. A pillar with no evidence is dropped and the
 *    remaining weights re-normalise. Somebody whose tasks carry no due dates
 *    has not failed timeliness; nobody can tell. Scoring the unknown as zero
 *    would punish people for how their manager writes tasks.
 *
 * 2. ENGAGEMENT CANNOT SINK A GOOD WORKER. Where output, timeliness and
 *    quality are all strong, a poor engagement reading may cost at most five
 *    points. Activity-log density is a proxy for a proxy, and it must never be
 *    the reason a productive person grades badly.
 *
 * 3. THE SCORE CARRIES ITS OWN CONFIDENCE. Four tasks and one month produce a
 *    number, and the pages print it in grey with "low confidence" next to it.
 *    A number that does not say how much it knows is the thing that gets
 *    misused, so confidence travels with it everywhere — the dashboard row,
 *    the dossier header, the printed sheet and the stored snapshot.
 *
 * The bands are named rather than lettered on the pages ("Strong", "Watch")
 * because a letter grade invites a comparison between people that a 12-point
 * difference cannot actually support.
 */
final class Scorecard
{
    /** Where the other pillars have to sit before rule 2 applies. */
    private const STRONG_CORE = 70.0;

    public function apply(Assessment $a): void
    {
        $known = array_filter($a->pillars, fn (Pillar $p) => $p->known());

        if (!$known || !$a->hasEvidence()) {
            $a->score      = null;
            $a->grade      = '—';
            $a->band       = 'unrated';
            $a->confidence = 'none';

            $a->set('score_basis', $a->hasEvidence()
                ? 'None of the five dimensions had enough evidence to score.'
                : 'No tasks are assigned to this person.');

            return;
        }

        $total = $this->weighted($known);

        /* Rule 2 — engagement is not allowed to be the story. */
        $withoutEngagement = $this->weighted(array_filter(
            $known,
            fn (Pillar $p) => $p->key !== 'engagement'
        ));

        if ($withoutEngagement !== null && $this->coreIsStrong($known) && ($withoutEngagement - $total) > 5) {
            $total = $withoutEngagement - 5;
        }

        $a->score      = Stats::clamp((float) $total);
        $a->band       = $this->band($a->score);
        $a->grade      = $this->grade($a->score);
        $a->confidence = $this->confidence($a, count($known));

        $a->set('score_basis', $this->basis($known, $a));
    }

    /** @param Pillar[] $pillars */
    private function weighted(array $pillars): ?float
    {
        $sum    = 0.0;
        $weight = 0.0;

        foreach ($pillars as $p) {
            $sum    += $p->score * $p->weight;
            $weight += $p->weight;
        }

        return $weight > 0 ? $sum / $weight : null;
    }

    /** @param Pillar[] $pillars */
    private function coreIsStrong(array $pillars): bool
    {
        foreach (['output', 'timeliness', 'quality'] as $key) {
            $p = $pillars[$key] ?? null;

            if ($p === null || $p->score < self::STRONG_CORE) {
                return false;
            }
        }

        return true;
    }

    private function band(float $score): string
    {
        return match (true) {
            $score >= 85 => 'exemplary',
            $score >= 70 => 'strong',
            $score >= 55 => 'steady',
            $score >= 40 => 'watch',
            default      => 'critical',
        };
    }

    private function grade(float $score): string
    {
        return match (true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 55 => 'C',
            $score >= 40 => 'D',
            default      => 'E',
        };
    }

    /**
     * How much this score is worth. Sample size first, then breadth: a number
     * built on one dimension is not a picture of somebody's work no matter how
     * many tasks fed it.
     */
    private function confidence(Assessment $a, int $knownPillars): string
    {
        $wholeMonths = count(array_filter($a->wholeMonthsCompleted(), fn ($v) => $v > 0));
        $tasks       = (int) $a->standing->total;

        if ($wholeMonths >= 3 && $tasks >= 20 && $knownPillars >= 4) {
            return 'high';
        }

        if ($wholeMonths >= 1 && $tasks >= 8 && $knownPillars >= 3) {
            return 'medium';
        }

        return 'low';
    }

    /** @param Pillar[] $known */
    private function basis(array $known, Assessment $a): string
    {
        $parts = [];

        foreach ($known as $p) {
            $parts[] = $p->label . ' ' . $p->rounded();
        }

        $missing = array_diff(array_keys($a->pillars), array_keys($known));

        $text = implode(' · ', $parts) . ' — weighted to ' . round($a->score) . '.';

        if ($missing) {
            $labels = array_map(fn ($k) => $a->pillars[$k]->label, $missing);
            $text  .= ' ' . implode(' and ', $labels) . ' had no evidence and '
                . (count($labels) === 1 ? 'was' : 'were')
                . ' left out rather than scored zero.';
        }

        return $text;
    }
}
