<?php

namespace Modules\EON\Intelligence;

use Modules\EON\Intelligence\Support\Stats;

/**
 * What "normal" looks like here, computed from the people being evaluated
 * rather than assumed.
 *
 * This is the single most important design decision in EON. A fixed target —
 * "twenty tasks a month is good" — is a number somebody invented, and it would
 * be wrong for every department at once: an accountant closing six careful
 * reconciliations is not underperforming a coordinator ticking sixty calls. So
 * EON compares people with the population it can actually see, using MEDIANS,
 * which one heroic month cannot drag.
 *
 * The honesty rule that comes with it: a baseline built from fewer than five
 * people with real work is not a baseline, it is an opinion. Below that,
 * cohort() reports itself as unreliable and the analyzers fall back to judging
 * a person against their OWN history instead of against their colleagues.
 */
final class Baseline
{
    private const MIN_PEERS = 5;

    public function __construct(
        public readonly int $people,
        public readonly int $activePeople,
        public readonly float $medianMonthly,
        public readonly float $medianCompletedMtd,
        public readonly ?float $medianOnTime,
        public readonly ?float $medianCycleDays,
        public readonly float $medianOverdue,
        public readonly float $medianActivityMtd,
        /** Every person's monthly-median, kept so a score can be turned into a rank. */
        public readonly array $monthlyValues = [],
        public readonly array $onTimeValues = [],
        public readonly array $scoreValues = [],
        // Each active person's last-30-days completed count (Load & scope, 2026-10-07)
        public readonly array $rollingValues = [],
    ) {
    }

    /** Enough peers with real work for "against the company" to mean anything. */
    public function reliable(): bool
    {
        return $this->activePeople >= self::MIN_PEERS;
    }

    public function percentileOfMonthly(float $value): ?float
    {
        return $this->reliable() ? Stats::percentileOf($value, $this->monthlyValues) : null;
    }

    /** Where a last-30-days count sits among everybody's — volume against the company. */
    public function percentileOfRolling(float $value): ?float
    {
        return $this->reliable() ? Stats::percentileOf($value, $this->rollingValues) : null;
    }

    public function percentileOfOnTime(float $value): ?float
    {
        return $this->reliable() ? Stats::percentileOf($value, $this->onTimeValues) : null;
    }

    /**
     * Build it from assessments already carrying their metrics.
     *
     * "Active" means somebody who finished at least one task in the window EON
     * holds. Dormant accounts are excluded from the medians deliberately: leave
     * them in and the company median drifts towards zero, which would flatter
     * everybody still working.
     *
     * @param Assessment[] $assessments
     */
    public static function fromAssessments(array $assessments): self
    {
        $monthly    = [];
        $mtd        = [];
        $onTime     = [];
        $cycle      = [];
        $overdue    = [];
        $activity   = [];
        $rolling    = [];

        foreach ($assessments as $a) {
            // Active months, so a joiner's empty calendar before they arrived
            // cannot pull the company median down towards zero.
            $completed = $a->activeMonthsCompleted();
            $worked    = array_sum($completed) > 0 || $a->mtd->completed > 0;

            if (!$worked) {
                continue;
            }

            $monthly[] = Stats::median($completed);
            $mtd[]     = (float) $a->mtd->completed;
            $rolling[] = (float) $a->rolling->completed;
            $overdue[] = (float) $a->standing->overdue;
            $activity[] = (float) $a->mtd->activity;

            $rate = $a->onTimeRateOverall();

            if ($rate !== null) {
                $onTime[] = $rate;
            }

            $cycles = array_values(array_filter(
                array_map(fn ($m) => $m['cycle_days'] ?? null, $a->series),
                fn ($v) => $v !== null
            ));

            if ($cycles) {
                $cycle[] = Stats::median($cycles);
            }
        }

        return new self(
            people:             count($assessments),
            activePeople:       count($monthly),
            medianMonthly:      Stats::median($monthly),
            medianCompletedMtd: Stats::median($mtd),
            medianOnTime:       $onTime ? Stats::median($onTime) : null,
            medianCycleDays:    $cycle ? Stats::median($cycle) : null,
            medianOverdue:      Stats::median($overdue),
            medianActivityMtd:  Stats::median($activity),
            monthlyValues:      $monthly,
            onTimeValues:       $onTime,
            rollingValues:      $rolling,
        );
    }
}
