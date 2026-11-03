<?php

namespace Modules\EON\Intelligence\Analyzers;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Baseline;
use Modules\EON\Intelligence\Support\Signal;
use Modules\EON\Intelligence\Support\Stats;

/**
 * RISK — what is likely to go wrong next, regardless of how the score reads.
 *
 * The last analyzer to run, and the only one that looks at the picture as a
 * whole rather than at one dimension. It exists because a score is a summary
 * and summaries hide combinations: a healthy 74 can be concealing a person who
 * finished a lot this month while nineteen items rot in their backlog.
 *
 * It also produces the number the dashboard sorts by. A leaderboard ordered by
 * score answers "who is best", which is rarely the question at 9am; ordered by
 * risk it answers "who do I open first", which is always the question.
 *
 * Risk is deliberately NOT the inverse of the score. High output with a rotting
 * backlog is riskier than low output with a clean one.
 */
final class RiskAnalyzer implements Analyzer
{
    public function key(): string
    {
        return 'risk';
    }

    public function analyze(Assessment $a, Baseline $b): void
    {
        $standing = $a->standing;
        $verdict  = $a->verdict;
        $trend    = $a->get('trend', []);
        $drawdown = $a->get('drawdown');

        $risk    = 0.0;
        $reasons = [];

        // Ageing overdue work — the strongest single predictor of a bad month.
        if ($standing->overdue_older > 0) {
            $risk += min(30, $standing->overdue_older * 8);
            $reasons[] = $standing->overdue_older . ' overdue over a month';
        }

        if ($standing->overdue_month > 0) {
            $risk += min(15, $standing->overdue_month * 3);
        }

        // Red flags are a human saying "look at this".
        if ($verdict->flags > 0) {
            $risk += min(20, $verdict->flags * 10);
            $reasons[] = $verdict->flags . ' red ' . ($verdict->flags === 1 ? 'flag' : 'flags');
        }

        // A sustained fall.
        if (($trend['direction'] ?? null) === 'falling') {
            $risk += min(15, abs((float) ($trend['percent'] ?? 0)));
            $reasons[] = 'output falling';
        }

        if ($drawdown && ($drawdown['from_peak_pct'] ?? 0) >= 50 && ($drawdown['months_below'] ?? 0) >= 2) {
            $risk += 12;
            $reasons[] = 'well below their own peak';
        }

        // Gone quiet while holding open work.
        $silent = $a->get('engagement')['silent_days'] ?? null;

        if ($silent !== null && $silent >= 14 && $standing->open > 0) {
            $risk += min(20, $silent / 2);
            $reasons[] = 'quiet for ' . (int) $silent . ' days';
        }

        // Low ratings from the authority.
        if ($verdict->rated >= 3 && $verdict->avg_rating !== null && $verdict->avg_rating <= 2.5) {
            $risk += 12;
            $reasons[] = 'rated ' . round($verdict->avg_rating, 1) . '★';
        }

        $risk = Stats::clamp($risk);

        $a->set('risk', [
            'score'   => round($risk),
            'reasons' => $reasons,
            'band'    => match (true) {
                $risk >= 60 => 'high',
                $risk >= 30 => 'medium',
                $risk > 0   => 'low',
                default     => 'none',
            },
        ]);

        /* The combination findings — things no single pillar can see. */

        /* Productive AND leaking — the combination a score hides.
           NOTE: $a->score is deliberately not consulted. Analyzers run BEFORE
           the scorecard, so it is null here for everybody; an earlier version
           tested it and the finding fired on every person with five overdue
           items, including ones who had completed nothing at all. The test is
           output against the company, which is what the sentence claims. */
        if ($standing->overdue >= 5 && $a->mtd->completed >= max(3, $b->medianCompletedMtd)) {
            $a->addSignal(Signal::watch(
                'risk.hidden_backlog',
                'Busy month, but ' . $standing->overdue . ' items are sitting past due',
                'Output alone would read as healthy here. The backlog is the part that will not fix itself.',
                null,
                ['overdue' => $standing->overdue]
            ));
        }

        if ($standing->open > 0 && $standing->open_age_days !== null && $standing->open_age_days >= 60) {
            $a->addSignal(Signal::watch(
                'risk.stale_open',
                'Open work averages ' . round($standing->open_age_days) . ' days old',
                $standing->open . ' items open, and the average one has been open two months. Some of these are probably finished in reality and never moved.',
                null
            ));
        }

        if ($standing->todo > 0 && $standing->in_progress === 0 && $standing->todo >= 5) {
            $a->addSignal(Signal::neutral(
                'risk.nothing_started',
                $standing->todo . ' items waiting and none marked in progress',
                'Either the board is not being kept current, or nothing has been picked up.',
                null
            ));
        }
    }
}
