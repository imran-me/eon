<?php

namespace Modules\EON\Intelligence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\EON\Intelligence\Analyzers\Analyzer;
use Modules\EON\Intelligence\Analyzers\ConsistencyAnalyzer;
use Modules\EON\Intelligence\Analyzers\DrawdownAnalyzer;
use Modules\EON\Intelligence\Analyzers\EngagementAnalyzer;
use Modules\EON\Intelligence\Analyzers\LoadAnalyzer;
use Modules\EON\Intelligence\Analyzers\PunctualityAnalyzer;
use Modules\EON\Intelligence\Analyzers\QualityAnalyzer;
use Modules\EON\Intelligence\Analyzers\RiskAnalyzer;
use Modules\EON\Intelligence\Analyzers\ThroughputAnalyzer;
use Modules\EON\Intelligence\Analyzers\TrendAnalyzer;
use Modules\EON\Intelligence\Narrative\Narrator;
use Modules\EON\Intelligence\Scoring\Scorecard;
use Modules\EON\Intelligence\Support\Period;
use Throwable;

/**
 * EON, assembled.
 *
 * The engine does four things in a fixed order, and the order is the design:
 *
 *   1. READ  — one bulk pass through TaskMetrics for the whole cohort. Four or
 *              five queries answer for forty people; nothing below this line
 *              touches the database.
 *   2. LEVEL — build the Baseline from those readings, so "normal" is derived
 *              from the people being evaluated rather than assumed. This must
 *              happen before any analyzer runs, because several of them judge
 *              against it.
 *   3. ANALYZE — run every analyzer over every person. Each is isolated in its
 *              own try/catch: an analyzer that throws loses its own dimension
 *              and nothing else. A page that dies because one metric divided by
 *              zero is not an acceptable trade for a screen nobody is required
 *              to use.
 *   4. SCORE — total the pillars, attach confidence, write the headline.
 *
 * Everything the ERP proper sees of EON stops at this class's return value. No
 * shared model is written, no shared cache key is touched, no event is fired.
 */
final class EonEngine
{
    private TaskMetrics $metrics;
    private Scorecard $scorecard;
    private Narrator $narrator;

    public function __construct(?TaskMetrics $metrics = null, ?CarbonImmutable $now = null)
    {
        $this->metrics   = $metrics ?: new TaskMetrics($now);
        $this->scorecard = new Scorecard();
        $this->narrator  = new Narrator();
    }

    /**
     * The analyzer roster, in running order.
     *
     * THIS IS THE EXTENSION POINT. A new dimension of the evaluation is one
     * class implementing Analyzer plus one line here. Order matters only in
     * that RiskAnalyzer reads what the others produced, so it stays last.
     *
     * @return Analyzer[]
     */
    public function analyzers(): array
    {
        return [
            new ThroughputAnalyzer(),
            new PunctualityAnalyzer(),
            new QualityAnalyzer(),
            new ConsistencyAnalyzer(),
            new EngagementAnalyzer(),
            new LoadAnalyzer(),
            new TrendAnalyzer(),
            new DrawdownAnalyzer(),
            new RiskAnalyzer(),
        ];
    }

    public function metrics(): TaskMetrics
    {
        return $this->metrics;
    }

    public function narrator(): Narrator
    {
        return $this->narrator;
    }

    /**
     * Evaluate a whole cohort.
     *
     * @param  Collection|array $people  User models, or anything with ->id and ->name
     * @return array<int, Assessment>    keyed by user id, in the order given
     */
    public function evaluate($people, int $months = 12): array
    {
        $people = $people instanceof Collection ? $people : collect($people);
        $ids    = $people->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        if (!$ids) {
            return [];
        }

        $now  = $this->metrics->now();
        $mtd  = Period::monthToDate($now);
        $prev = Period::previousMonthToDate($now);

        /* 1 — read everything, in bulk. */
        $series   = $this->metrics->monthlySeries($ids, $months);
        $standing = $this->metrics->standing($ids);
        $verdicts = $this->metrics->verdicts($ids);
        $mtdStats = $this->metrics->window($ids, $mtd['start'], $mtd['end']);
        $prvStats = $this->metrics->window($ids, $prev['start'], $prev['end']);
        $lastSeen = $this->metrics->lastSeenWorking($ids);

        // The scored window: a rolling month, so a score means the same thing
        // on the 1st as it does on the 28th. See Assessment::$rolling.
        $r30      = $this->metrics->window($ids, $now->subDays(30), $now);
        $r30Prev  = $this->metrics->window($ids, $now->subDays(60), $now->subDays(30));

        $assessments = [];

        foreach ($people as $person) {
            $id = (int) $person->id;

            $assessments[$id] = new Assessment(
                userId:         $id,
                user:           $person,
                standing:       $standing[$id],
                verdict:        $verdicts[$id],
                mtd:            $mtdStats[$id],
                prev:           $prvStats[$id],
                rolling:        $r30[$id],
                rollingPrev:    $r30Prev[$id],
                series:         $series[$id] ?? [],
                mtdWindow:      $mtd,
                prevWindow:     $prev,
                lastActivityAt: $lastSeen[$id] ?? null,
            );
        }

        /* 2 — what "normal" looks like, from these people. */
        $baseline = Baseline::fromAssessments($assessments);

        /* 3 and 4 — analyze, then score. */
        foreach ($assessments as $assessment) {
            $this->run($assessment, $baseline);
        }

        $this->rank($assessments);

        return $assessments;
    }

    /**
     * One person, evaluated against the same baseline everybody else is —
     * which means the cohort still has to be read. That is deliberate: a score
     * that means one thing on the dashboard and another on the dossier would be
     * worse than no score.
     *
     * @param Collection|array $cohort  the people the baseline is built from
     */
    public function evaluateOne($person, $cohort, int $months = 12): Assessment
    {
        $cohort = $cohort instanceof Collection ? $cohort : collect($cohort);

        if (!$cohort->contains(fn ($p) => (int) $p->id === (int) $person->id)) {
            $cohort = $cohort->push($person);
        }

        $all = $this->evaluate($cohort, $months);

        return $all[(int) $person->id];
    }

    /* ── internals ──────────────────────────────────────────────────────── */

    private function run(Assessment $assessment, Baseline $baseline): void
    {
        foreach ($this->analyzers() as $analyzer) {
            try {
                $analyzer->analyze($assessment, $baseline);
            } catch (Throwable $e) {
                /* One dimension lost, the evaluation survives. Recorded on the
                   assessment so a missing pillar is visible as a fault rather
                   than as a silent "no evidence" — the two mean very different
                   things and a page that confuses them is lying quietly. */
                $failed = $assessment->get('failed_analyzers', []);
                $failed[$analyzer->key()] = $e->getMessage();
                $assessment->set('failed_analyzers', $failed);
            }
        }

        try {
            $this->scorecard->apply($assessment);
            $assessment->set('headline', $this->narrator->headline($assessment));
        } catch (Throwable $e) {
            $assessment->score = null;
            $assessment->band  = 'unrated';
            $assessment->set('headline', 'EON could not score this person.');
        }
    }

    /**
     * Position within the cohort, for the dashboard's rank column. Computed
     * after scoring because it depends on everybody else's result.
     *
     * @param array<int, Assessment> $assessments
     */
    private function rank(array $assessments): void
    {
        $scored = array_filter($assessments, fn ($a) => $a->score !== null);

        uasort($scored, fn ($x, $y) => $y->score <=> $x->score);

        $position = 0;
        $total    = count($scored);

        foreach ($scored as $assessment) {
            $position++;
            $assessment->set('rank', $position);
            $assessment->set('rank_of', $total);
        }
    }
}
