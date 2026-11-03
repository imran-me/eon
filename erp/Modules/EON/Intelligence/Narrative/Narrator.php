<?php

namespace Modules\EON\Intelligence\Narrative;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Support\Signal;

/**
 * The last step: EON in a paragraph.
 *
 * Every sentence here is assembled from signals the analyzers already raised —
 * this class invents nothing and computes nothing. That constraint is the
 * whole design. If the narrative could reason on its own, there would be two
 * places where EON forms an opinion and they would drift apart; as it stands,
 * anything the summary says can be traced to the analyzer that said it first.
 *
 * The register is deliberate too. This text goes into a review that a person
 * may end up reading about themselves, so it describes work and never
 * character: "twelve items are more than a month past due", not "careless".
 * EON reports; the authority judges.
 */
final class Narrator
{
    /** One line for the dossier header and the dashboard tooltip. */
    public function headline(Assessment $a): string
    {
        if (!$a->hasEvidence()) {
            return 'No tasks are assigned to this person, so there is nothing for EON to evaluate.';
        }

        if ($a->score === null) {
            return 'Not enough evidence yet to score this person\'s work.';
        }

        $name = $this->firstName($a);

        $band = match ($a->band) {
            'exemplary' => 'is performing at the top of the range EON can measure',
            'strong'    => 'is performing well against their own history and their colleagues',
            'steady'    => 'is holding a workable level with room to tighten',
            'watch'     => 'is below the level this evaluation would expect',
            default     => 'needs attention on more than one dimension',
        };

        $critical = $a->signalsOf(Signal::CRITICAL);

        if ($critical) {
            /* The first critical finding is quoted verbatim rather than folded
               into the sentence. Its titles start with figures — "17 items are
               more than a month past due" — and lower-casing one into a clause
               produced "starting with 17 items are more than…", which reads as
               a grammar fault rather than a quotation. */
            return $name . ' ' . $band . ', with ' . count($critical) . ' '
                . (count($critical) === 1 ? 'issue' : 'issues')
                . ' to answer first. ' . rtrim($critical[0]->title, '.') . '.';
        }

        $good = $a->signalsOf(Signal::GOOD);

        if ($good) {
            return $name . ' ' . $band . '. ' . rtrim($good[0]->title, '.') . '.';
        }

        return $name . ' ' . $band . '.';
    }

    /**
     * The three-or-four sentence summary the printed review sheet opens with.
     * Structured as: where they stand, what changed, what to do about it.
     */
    public function summary(Assessment $a): array
    {
        $paragraphs = [];

        $paragraphs[] = $this->headline($a);

        /* What changed — the month-on-month sentence, always like-for-like. */
        $mtdDays = $a->mtdWindow['days'] ?? null;
        $change  = $a->get('output')['change'] ?? null;

        if ($mtdDays && ($a->mtd->completed > 0 || $a->prev->completed > 0)) {
            $line = 'Through day ' . $mtdDays . ' of ' . ($a->mtdWindow['label'] ?? 'this month')
                . ' they completed ' . $a->mtd->completed . ' '
                . ($a->mtd->completed === 1 ? 'task' : 'tasks')
                . ', against ' . $a->prev->completed . ' over the same days of '
                . ($a->prevWindow['label'] ?? 'last month');

            if ($change !== null) {
                $line .= ' — ' . ($change >= 0 ? 'up ' : 'down ') . round(abs($change)) . '%';
            }

            $paragraphs[] = $line . '.';
        }

        /* Where they stand against themselves. */
        $dd = $a->get('drawdown');

        if ($dd && ($dd['from_peak_pct'] ?? null) !== null && $dd['peak'] >= 3) {
            /* Somebody sitting ON their peak used to be told they were "0%
               below" it, which reads as a fault report about an achievement. */
            $paragraphs[] = round($dd['from_peak_pct']) < 5
                ? 'Their best month on record is ' . round($dd['peak']) . ' completed in '
                    . $dd['peak_month'] . ', which is where they currently are.'
                : 'Their best month on record is ' . round($dd['peak']) . ' completed in '
                    . $dd['peak_month'] . '; they are currently '
                    . round($dd['from_peak_pct']) . '% below it'
                    . (($dd['months_below'] ?? 0) >= 2 ? ', and have been under it for ' . $dd['months_below'] . ' months' : '')
                    . '.';
        }

        /* The human verdicts — kept separate from EON's own reading. */
        $q = $a->get('quality', []);

        if (($q['rated'] ?? 0) > 0) {
            $paragraphs[] = 'The authority has rated ' . $q['rated'] . ' of their tasks, averaging '
                . rtrim(rtrim(number_format((float) $q['avg_rating'], 1), '0'), '.') . ' out of 5'
                . (($q['flags'] ?? 0) > 0 ? ', with ' . $q['flags'] . ' red-flagged' : '')
                . '. That rating is a person\'s judgement and is not part of EON\'s own observations.';
        } elseif (($a->standing->done ?? 0) >= 10) {
            $paragraphs[] = 'No manual rating has been recorded for any of their work, so the quality reading here is machine-only and should be treated as provisional.';
        }

        /* What to do about it. */
        $actions = $this->actions($a);

        if ($actions) {
            $paragraphs[] = 'Worth acting on: ' . $this->list($actions) . '.';
        }

        return $paragraphs;
    }

    /**
     * Concrete next steps, drawn only from findings that imply one. A review
     * that ends in a verdict and no action is a complaint.
     */
    public function actions(Assessment $a): array
    {
        $actions  = [];
        $standing = $a->standing;

        if ($standing->overdue_older > 0) {
            $actions[] = 'a decision on the ' . $standing->overdue_older
                . ' ' . ($standing->overdue_older === 1 ? 'item' : 'items')
                . ' more than a month past due — finish, re-date or close ' . ($standing->overdue_older === 1 ? 'it' : 'them');
        }

        if (($a->verdict->flags ?? 0) > 0) {
            $actions[] = 'reviewing the ' . $a->verdict->flags . ' red-flagged '
                . ($a->verdict->flags === 1 ? 'task' : 'tasks');
        }

        if (($a->verdict->rated ?? 0) === 0 && $standing->done >= 20) {
            $actions[] = 'rating a few completed tasks, so quality stops being guesswork';
        }

        $silent = $a->get('engagement')['silent_days'] ?? null;

        if ($silent !== null && $silent >= 21 && $standing->open > 0) {
            $actions[] = 'asking why the board has been untouched for ' . (int) $silent . ' days';
        }

        $timeliness = $a->get('timeliness', []);

        if (($timeliness['with_due'] ?? 0) === 0 && $standing->done >= 10) {
            $actions[] = 'setting due dates on their work — without them, nothing here can measure reliability';
        }

        return array_slice($actions, 0, 4);
    }

    private function list(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }

    private function firstName(Assessment $a): string
    {
        $name = trim((string) ($a->user->name ?? ''));

        if ($name === '') {
            return 'This person';
        }

        $first = explode(' ', $name)[0];

        // Titles are not names — "Mr." alone reads as a joke on a review sheet.
        return in_array(rtrim($first, '.'), ['Mr', 'Md', 'Mrs', 'Ms', 'Dr'], true) ? $name : $first;
    }
}
