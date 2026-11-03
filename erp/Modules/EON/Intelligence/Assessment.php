<?php

namespace Modules\EON\Intelligence;

use Carbon\CarbonImmutable;
use Modules\EON\Intelligence\Scoring\Pillar;
use Modules\EON\Intelligence\Support\Signal;

/**
 * Everything EON has concluded about one person, in one object.
 *
 * The analyzers fill it, the scorecard totals it, and the views read it. No
 * view ever queries anything: if a number is on an EON page, it came through
 * here, which is why the dashboard row and the dossier header can never
 * disagree.
 *
 * It carries the human verdicts (stars, flags, locks) alongside the machine
 * ones, but never merges them — the machine score and the authority's rating
 * are two separate readings and the whole point is being able to see where they
 * disagree.
 */
final class Assessment
{
    /** @var Pillar[] keyed by pillar key */
    public array $pillars = [];

    /** @var Signal[] */
    public array $signals = [];

    /** Analyzer output that is not a score — trend, drawdown, aging, mix. */
    public array $data = [];

    public ?float $score = null;
    public string $grade = '—';
    public string $band  = 'unrated';

    /**
     * How much the score is worth. A person with four tasks gets a number, but
     * it is decoration and the pages say so — an honest "not enough to judge"
     * beats a confident figure built on nothing.
     */
    public string $confidence = 'low';

    public function __construct(
        public readonly int $userId,
        public readonly ?object $user,
        /** Point-in-time standing: backlog, overdue, ages. */
        public readonly object $standing,
        /** The human verdicts already recorded in Employee Task Oversight. */
        public readonly object $verdict,
        /** This month so far — what the month-on-month panel reports. */
        public readonly object $mtd,
        /** The SAME days of last month — never the whole month. */
        public readonly object $prev,
        /**
         * The last 30 days, and the 30 before those. THIS is what the score is
         * built on, and the distinction is not academic: scoring on the
         * calendar month means every person in the company reads as having
         * collapsed on the 1st, recovers over three weeks, and resets. A
         * rolling window measures the same amount of work on every day of the
         * year. The calendar comparison stays because "this month against
         * last" is how people think — it is reported, not scored.
         */
        public readonly object $rolling,
        public readonly object $rollingPrev,
        /** Month-by-month history, oldest first, keyed YYYY-MM. */
        public readonly array $series,
        public readonly array $mtdWindow,
        public readonly array $prevWindow,
        public readonly ?CarbonImmutable $lastActivityAt = null,
    ) {
    }

    public function addPillar(Pillar $pillar): void
    {
        $this->pillars[$pillar->key] = $pillar;
    }

    public function addSignal(Signal $signal): void
    {
        $this->signals[] = $signal;
    }

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /** Monthly completed counts, oldest first, keyed YYYY-MM. */
    public function completedSeries(): array
    {
        return array_map(fn ($m) => (int) ($m['completed'] ?? 0), $this->series);
    }

    /** Monthly counts of work that ARRIVED — the load, against the output. */
    public function createdSeries(): array
    {
        return array_map(fn ($m) => (int) ($m['created'] ?? 0), $this->series);
    }

    /** Months excluding the current, partial one — the only whole readings. */
    public function wholeMonthsCompleted(): array
    {
        $series = $this->completedSeries();
        array_pop($series);

        return $series;
    }

    /**
     * The same whole months with the empty run at the FRONT removed — their
     * history, not the calendar's.
     *
     * Every analyzer that judges somebody against their own past uses this, and
     * the reason is a bug that shipped in the first build: EON holds twelve
     * months, this install only has data since March, so a person's series
     * begins with six zeroes. Their median month came out as 0, "0 > 0" failed,
     * and a person with eighty completed tasks was silently scored against the
     * company median instead of their own record — then graded 0 for output.
     * A joiner's empty months before they arrived are not a performance
     * history, and nothing here may treat them as one.
     */
    public function activeMonthsCompleted(): array
    {
        $months = $this->wholeMonthsCompleted();

        while ($months && (int) reset($months) === 0) {
            array_shift($months);
        }

        return $months;
    }

    /** On-time share of completed work that had a due date, 0-100. */
    public function onTimeRate(?object $window = null): ?float
    {
        $w = $window ?: $this->mtd;

        return $w->with_due > 0 ? ($w->on_time / $w->with_due) * 100 : null;
    }

    /** Lifetime on-time share across the whole window EON holds. */
    public function onTimeRateOverall(): ?float
    {
        $onTime  = 0;
        $withDue = 0;

        foreach ($this->series as $m) {
            $onTime  += (int) ($m['on_time'] ?? 0);
            $withDue += (int) ($m['with_due'] ?? 0);
        }

        return $withDue > 0 ? ($onTime / $withDue) * 100 : null;
    }

    /** Signals worst-first, then in the order the analyzers raised them. */
    public function rankedSignals(?int $limit = null): array
    {
        $signals = $this->signals;

        usort($signals, fn ($a, $b) => $a->rank() <=> $b->rank());

        return $limit ? array_slice($signals, 0, $limit) : $signals;
    }

    public function signalsOf(string $severity): array
    {
        return array_values(array_filter($this->signals, fn ($s) => $s->severity === $severity));
    }

    public function hasEvidence(): bool
    {
        return $this->standing->total > 0;
    }

    /**
     * The frozen form. eon_snapshots stores this so a review written today
     * still reads correctly in a year, when the tasks behind it have moved on
     * and the live numbers no longer resemble what was judged.
     */
    public function toSnapshot(): array
    {
        return [
            'score'      => $this->score === null ? null : round($this->score, 1),
            'grade'      => $this->grade,
            'band'       => $this->band,
            'confidence' => $this->confidence,
            'pillars'    => array_map(fn ($p) => $p->toArray(), array_values($this->pillars)),
            'signals'    => array_map(fn ($s) => $s->toArray(), $this->rankedSignals()),
            'standing'   => (array) $this->standing,
            'verdict'    => array_map(
                fn ($v) => $v instanceof CarbonImmutable ? $v->toDateTimeString() : $v,
                (array) $this->verdict
            ),
            'mtd'        => (array) $this->mtd,
            'prev'       => (array) $this->prev,
            // The rolling window too — it is what the score was actually built
            // on, and a frozen reading that omitted it could not be re-derived.
            'rolling'      => (array) $this->rolling,
            'rolling_prev' => (array) $this->rollingPrev,
            'series'     => $this->series,
            'trend'      => $this->get('trend'),
            'drawdown'   => $this->get('drawdown'),
            'window'     => [
                'mtd'  => [
                    'label' => $this->mtdWindow['label'] ?? null,
                    'days'  => $this->mtdWindow['days'] ?? null,
                ],
                'prev' => [
                    'label' => $this->prevWindow['label'] ?? null,
                    'days'  => $this->prevWindow['days'] ?? null,
                ],
            ],
            'taken_at'   => now()->toDateTimeString(),
        ];
    }
}
