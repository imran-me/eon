<?php

namespace Modules\EON\Intelligence\Analyzers;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Baseline;
use Modules\EON\Intelligence\Scoring\Pillar;
use Modules\EON\Intelligence\Support\Signal;
use Modules\EON\Intelligence\Support\Stats;

/**
 * ENGAGEMENT — are they working the board, or filing into it?
 *
 * Built from the activity log: on how many of the last thirty days they touched
 * anything, and how much of that was work on tasks rather than only filing them.
 *
 * WEIGHTED LOWEST, AT 10, ON PURPOSE. This is the pillar most easily gamed and
 * most easily misread. Somebody who does excellent work and updates the board
 * once a week is not disengaged; somebody who fires forty status changes a day
 * is not necessarily productive. It is here because a person who has not
 * touched the system in six weeks while holding open work is a real finding —
 * and that finding is worth more than the score is.
 *
 * It is never allowed to be the reason a score is low: with output, timeliness
 * and quality all healthy, ten points of engagement cannot drop somebody out of
 * their band.
 */
final class EngagementAnalyzer implements Analyzer
{
    public function key(): string
    {
        return 'engagement';
    }

    public function analyze(Assessment $a, Baseline $b): void
    {
        /* The rolling month, like every other scored figure. Measuring presence
           against the calendar month would score everybody at zero on the 1st
           and rebuild their reading over three weeks. */
        $activeDays  = (int) $a->rolling->active_days;
        $activity    = (int) $a->rolling->activity;
        $lastSeen    = $a->lastActivityAt;
        $silentDays  = $lastSeen ? (int) $lastSeen->diffInDays($a->mtdWindow['end']) : null;

        // Working days in those 30, Friday off — the Bangladesh working week
        // this ERP runs on. An approximation, and it only ever sets the
        // denominator for "how many days did they show up", never a pay or
        // attendance figure; attendance has its own module and this must not
        // pretend to be it.
        $workingDays = $this->workingDaysInWindow($a);

        $presence = $workingDays > 0 ? Stats::clamp(($activeDays / $workingDays) * 100) : null;
        $perTask  = $a->rolling->completed > 0 ? $activity / $a->rolling->completed : null;

        $a->set('engagement', [
            'active_days'  => $activeDays,
            'working_days' => $workingDays,
            'activity'     => $activity,
            'per_task'     => $perTask,
            'presence'     => $presence,
            'last_seen'    => $lastSeen,
            'silent_days'  => $silentDays,
            'peer_median'  => $b->reliable() ? $b->medianActivityMtd : null,
        ]);

        if (!$a->hasEvidence()) {
            $a->addPillar(new Pillar('engagement', 'Engagement', null, 10, 'No tasks assigned.'));

            return;
        }

        if ($activity === 0 && $a->standing->open === 0) {
            $a->addPillar(new Pillar(
                key:    'engagement',
                label:  'Engagement',
                score:  null,
                weight: 10,
                basis:  'Nothing open and nothing logged in the last 30 days — there is no engagement question to answer.',
            ));

            return;
        }

        // Showing up on 60% of working days is full marks. Higher is not better
        // and is not rewarded: this measures presence, not intensity.
        $score = $presence === null ? 50.0 : Stats::clamp(($presence / 60) * 100);

        $a->addPillar(new Pillar(
            key:    'engagement',
            label:  'Engagement',
            score:  $score,
            weight: 10,
            basis:  'Active on ' . $activeDays . ' of about ' . $workingDays
                . ' working days in the last 30, across ' . $activity . ' logged actions.',
            facts:  [
                'active_days'  => $activeDays,
                'working_days' => $workingDays,
                'activity'     => $activity,
                'per_task'     => $perTask === null ? null : round($perTask, 1),
            ],
        ));

        /* ── Findings ──────────────────────────────────────────────────── */

        if ($silentDays !== null && $silentDays >= 21 && $a->standing->open > 0) {
            $a->addSignal(Signal::critical(
                'engagement.silent',
                'Nothing touched in ' . $silentDays . ' days, with ' . $a->standing->open . ' items still open',
                'Last logged action ' . $lastSeen->format('j M Y') . '. Either the work moved off the board or it stopped.',
                'engagement'
            ));
        } elseif ($lastSeen === null && $a->standing->open > 0) {
            $a->addSignal(Signal::watch(
                'engagement.never',
                'No activity of theirs has ever been logged',
                $a->standing->open . ' items are open in their name. Somebody else may be moving these cards for them.',
                'engagement'
            ));
        }

        if ($activeDays >= 1 && $workingDays >= 10 && $presence !== null && $presence >= 70) {
            $a->addSignal(Signal::good(
                'engagement.present',
                'On the board almost every working day',
                $activeDays . ' active days out of about ' . $workingDays . ' in the last 30.',
                'engagement'
            ));
        }
    }

    /**
     * Working days in the rolling 30-day window, counting Friday as the
     * weekend — the pattern this install works to.
     *
     * Deliberately crude. It is a denominator for "did they show up", not an
     * attendance record: holidays and leave are somebody else's module, and if
     * EON started deducting for them it would be quietly duplicating payroll.
     */
    private function workingDaysInWindow(Assessment $a): int
    {
        $end   = $a->mtdWindow['end'] ?? null;
        $start = $end ? $end->subDays(30) : null;

        if (!$start || !$end) {
            return 0;
        }

        $days   = 0;
        $cursor = $start;

        while ($cursor <= $end) {
            if ($cursor->dayOfWeek !== 5) { // 5 = Friday
                $days++;
            }

            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
