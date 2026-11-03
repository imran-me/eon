<?php

namespace Modules\EON\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\EonEngine;
use Modules\EON\Intelligence\People;
use Modules\EON\Models\EonReview;
use Modules\EON\Models\EonSnapshot;
use Throwable;

/**
 * EON's only door.
 *
 * Reads the task module, writes eon_reviews and eon_snapshots, and touches
 * nothing else in the ERP. Every action guards on super admin first — the same
 * standing as Employee Task Oversight, checked here rather than on the route,
 * because a route is a door and this is the lock.
 */
class EonController extends Controller
{
    /**
     * Super admin only, checked at every entry point.
     *
     * Evaluation data is the most sensitive thing EON touches: it is about
     * named people, it is inferential, and it will be wrong about somebody
     * eventually. Keeping the audience identical to the oversight screen means
     * no new permission rows and no chance of it widening by accident.
     */
    private function guard(): void
    {
        $user = Auth::user();

        abort_unless($user && ($user->is_super_admin || $user->hasRole('super admin')), 403);
    }

    /* ── The dashboard ──────────────────────────────────────────────────── */

    public function index(Request $request, $role)
    {
        $this->guard();

        $q      = trim((string) $request->get('q', ''));
        $sort   = (string) $request->get('sort', 'risk');
        $engine = new EonEngine();

        // Always evaluate everybody, then filter for display. The baseline has
        // to come from the whole cohort — a search for "imran" must not
        // redefine what an average month looks like.
        $everyone    = People::evaluable();
        $assessments = $engine->evaluate($everyone);

        $visible = $q === ''
            ? $everyone
            : People::evaluable($q);

        $rows = [];

        foreach ($visible as $person) {
            if (isset($assessments[(int) $person->id])) {
                $rows[] = $assessments[(int) $person->id];
            }
        }

        $rows = $this->sortRows($rows, $sort);

        return view('eon::dashboard', [
            'role'        => $role,
            'q'           => $q,
            'sort'        => $sort,
            'rows'        => $rows,
            'assessments' => $assessments,
            'rollup'      => $this->rollup($assessments),
            'orgSeries'   => $this->orgSeries($assessments),
            'watchlist'   => $this->watchlist($assessments),
            'narrator'    => $engine->narrator(),
        ]);
    }

    /* ── One person ─────────────────────────────────────────────────────── */

    public function employee(Request $request, $role, User $employee)
    {
        $this->guard();

        $engine     = new EonEngine();
        $cohort     = People::evaluable();
        $assessment = $engine->evaluateOne($employee, $cohort);
        $metrics    = $engine->metrics();

        $period = $assessment->mtdWindow['key'] ?? CarbonImmutable::now()->format('Y-m');

        return view('eon::employee', [
            'role'        => $role,
            'employee'    => $employee,
            'a'           => $assessment,
            'narrator'    => $engine->narrator(),
            'summary'     => $engine->narrator()->summary($assessment),
            'weekly'      => $metrics->weeklyCompletions($employee->id),
            'overdue'     => $metrics->overdueTasks($employee->id),
            'unmarked'    => $metrics->unmarkedDoneTasks($employee->id),
            'judged'      => $metrics->judgedTasks($employee->id),
            'ratingSeries' => $metrics->ratingSeries($employee->id),
            'activityMix' => $metrics->activityMix($employee->id),
            'reviews'     => $this->reviewsFor($employee->id),
            'review'      => $this->reviewFor($employee->id, $period),
            'period'      => $period,
            'reviewsInstalled' => EonReview::installed(),
        ]);
    }

    /**
     * The printable evaluation sheet — the same assessment, laid out for A4 and
     * a signature. Its own self-contained blade: the shared layout carries a
     * sidebar, a header and a live socket, none of which belong on paper.
     */
    public function printSheet(Request $request, $role, User $employee)
    {
        $this->guard();

        $engine     = new EonEngine();
        $assessment = $engine->evaluateOne($employee, People::evaluable());
        $period     = $assessment->mtdWindow['key'] ?? CarbonImmutable::now()->format('Y-m');

        return view('eon::print', [
            'role'     => $role,
            'employee' => $employee,
            'a'        => $assessment,
            'summary'  => $engine->narrator()->summary($assessment),
            'actions'  => $engine->narrator()->actions($assessment),
            'review'   => $this->reviewFor($employee->id, $period),
            'judged'   => $engine->metrics()->judgedTasks($employee->id, 15),
            'period'   => $period,
        ]);
    }

    /** Monthly series as JSON, for anything the page wants after first paint. */
    public function series(Request $request, $role, User $employee)
    {
        $this->guard();

        $engine     = new EonEngine();
        $assessment = $engine->evaluateOne($employee, People::evaluable());

        return response()->json([
            'success'  => true,
            'series'   => $assessment->series,
            'trend'    => $assessment->get('trend'),
            'drawdown' => $assessment->get('drawdown'),
            'score'    => $assessment->score,
        ]);
    }

    /* ── The written review ─────────────────────────────────────────────── */

    /**
     * Save one month's review, and freeze the engine's reading beside it.
     *
     * The snapshot is not optional and not a cache. A review read back next
     * year has to show what the numbers said on the day it was written, or the
     * judgement in it cannot be understood — the tasks behind it will have
     * moved on, been reassigned, or been deleted.
     */
    public function saveReview(Request $request, $role, User $employee)
    {
        $this->guard();

        if (!EonReview::installed()) {
            return back()->with('error', 'EON reviews are not installed on this server yet — the migration has not been run.');
        }

        $data = $request->validate([
            'period'         => 'required|string|max:7',
            'overall_rating' => 'nullable|integer|min:1|max:5',
            'verdict'        => 'nullable|string|in:' . implode(',', array_keys(EonReview::VERDICTS)),
            'summary'        => 'nullable|string|max:4000',
            'actions'        => 'nullable|string|max:4000',
        ]);

        $engine     = new EonEngine();
        $assessment = $engine->evaluateOne($employee, People::evaluable());

        $snapshotId = $this->freeze($assessment, $employee->id, $data['period']);

        EonReview::updateOrCreate(
            ['user_id' => $employee->id, 'period' => $data['period']],
            [
                'overall_rating'     => $data['overall_rating'] ?? null,
                'verdict'            => $data['verdict'] ?? null,
                'summary'            => $data['summary'] ?? null,
                'actions'            => $data['actions'] ?? null,
                'machine_score'      => $assessment->score === null ? null : round($assessment->score, 1),
                'machine_band'       => $assessment->band,
                'machine_confidence' => $assessment->confidence,
                'snapshot_id'        => $snapshotId,
                'reviewed_by'        => Auth::id(),
                'reviewed_at'        => now(),
            ]
        );

        return back()->with('success', 'Review saved for ' . $employee->name . '.');
    }

    public function deleteReview(Request $request, $role, $review)
    {
        $this->guard();

        if (!EonReview::installed()) {
            return back()->with('error', 'EON reviews are not installed on this server yet.');
        }

        $row = EonReview::find($review);

        if ($row) {
            $row->delete();
        }

        return back()->with('success', 'Review removed.');
    }

    /**
     * Store the assessment as it reads right now. Best effort: a review that
     * saved must not report failure because its evidence copy did not.
     */
    private function freeze(Assessment $assessment, int $userId, string $period): ?int
    {
        if (!EonSnapshot::installed()) {
            return null;
        }

        try {
            $snapshot = EonSnapshot::create([
                'user_id'    => $userId,
                'period'     => $period,
                'score'      => $assessment->score === null ? null : round($assessment->score, 1),
                'band'       => $assessment->band,
                'confidence' => $assessment->confidence,
                'payload'    => $assessment->toSnapshot(),
                'taken_by'   => Auth::id(),
                'taken_at'   => now(),
            ]);

            return $snapshot->id;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function reviewsFor(int $userId)
    {
        if (!EonReview::installed()) {
            return collect();
        }

        try {
            return EonReview::with('reviewer:id,name')
                ->where('user_id', $userId)
                ->orderByDesc('period')
                ->limit(24)
                ->get();
        } catch (Throwable $e) {
            return collect();
        }
    }

    private function reviewFor(int $userId, string $period): ?EonReview
    {
        if (!EonReview::installed()) {
            return null;
        }

        try {
            return EonReview::with('reviewer:id,name')
                ->where('user_id', $userId)
                ->where('period', $period)
                ->first();
        } catch (Throwable $e) {
            return null;
        }
    }

    /* ── Dashboard aggregates ───────────────────────────────────────────── */

    /**
     * The company in one strip. Sums where a sum is honest (tasks, flags) and
     * medians where an average would lie (scores, rates) — one dormant account
     * should not be able to drag the company's on-time rate.
     *
     * @param array<int, Assessment> $assessments
     */
    private function rollup(array $assessments): array
    {
        $completedMtd = 0;
        $completedPrev = 0;
        $createdMtd   = 0;
        $overdue      = 0;
        $overdueOld   = 0;
        $unmarked     = 0;
        $flags        = 0;
        $locks        = 0;
        $open         = 0;
        $onTimeNum    = 0;
        $onTimeDen    = 0;
        $scores       = [];
        $bands        = ['exemplary' => 0, 'strong' => 0, 'steady' => 0, 'watch' => 0, 'critical' => 0, 'unrated' => 0];
        $unrated      = 0;
        $active       = 0;
        $rated        = 0;

        foreach ($assessments as $a) {
            $completedMtd  += (int) $a->mtd->completed;
            $completedPrev += (int) $a->prev->completed;
            $createdMtd    += (int) $a->mtd->created;
            $overdue       += (int) $a->standing->overdue;
            $overdueOld    += (int) $a->standing->overdue_older;
            $unmarked      += (int) ($a->standing->unmarked_done ?? 0);
            $flags         += (int) $a->verdict->flags;
            $locks         += (int) $a->verdict->locks;
            $open          += (int) $a->standing->open;
            $onTimeNum     += (int) $a->mtd->on_time;
            $onTimeDen     += (int) $a->mtd->with_due;
            $rated         += (int) $a->verdict->rated;

            if ($a->score !== null) {
                $scores[] = $a->score;
            }

            $bands[$a->band] = ($bands[$a->band] ?? 0) + 1;

            if ($a->standing->total > 0) {
                $active++;

                if ($a->verdict->rated === 0) {
                    $unrated++;
                }
            }
        }

        sort($scores);
        $n = count($scores);

        return [
            'people'         => count($assessments),
            'active'         => $active,
            'completed_mtd'  => $completedMtd,
            'completed_prev' => $completedPrev,
            'change'         => $completedPrev > 0
                ? (($completedMtd - $completedPrev) / $completedPrev) * 100
                : null,
            'created_mtd'    => $createdMtd,
            'open'           => $open,
            'overdue'        => $overdue,
            'overdue_older'  => $overdueOld,
            'unmarked_done'  => $unmarked,
            'flags'          => $flags,
            'locks'          => $locks,
            'rated_tasks'    => $rated,
            'unrated_people' => $unrated,
            'on_time'        => $onTimeDen > 0 ? ($onTimeNum / $onTimeDen) * 100 : null,
            'median_score'   => $n ? ($n % 2 ? $scores[intdiv($n, 2)] : ($scores[intdiv($n, 2) - 1] + $scores[intdiv($n, 2)]) / 2) : null,
            'bands'          => $bands,
        ];
    }

    /**
     * Completed work across the whole company, month by month — the line that
     * says whether an individual's fall is theirs or everybody's.
     *
     * @param array<int, Assessment> $assessments
     */
    private function orgSeries(array $assessments): array
    {
        $totals = [];

        foreach ($assessments as $a) {
            foreach ($a->series as $key => $month) {
                $totals[$key] = ($totals[$key] ?? 0) + (int) ($month['completed'] ?? 0);
            }
        }

        ksort($totals);

        /* Drop the empty months before the company's first recorded task. EON
           holds twelve months and this install only has data since March, so
           half the chart was blank space that read as a collapse rather than as
           "we were not using the board yet". */
        while ($totals && (int) reset($totals) === 0) {
            array_shift($totals);
        }

        return $totals;
    }

    /**
     * Who to open first. Sorted by the risk reading, not by the score — a
     * leaderboard answers "who is best", which is rarely the morning question.
     *
     * @param array<int, Assessment> $assessments
     */
    private function watchlist(array $assessments, int $limit = 6): array
    {
        $rows = array_filter(
            $assessments,
            fn ($a) => ($a->get('risk')['score'] ?? 0) >= 30
        );

        usort($rows, fn ($x, $y) => ($y->get('risk')['score'] ?? 0) <=> ($x->get('risk')['score'] ?? 0));

        return array_slice($rows, 0, $limit);
    }

    /** @param Assessment[] $rows */
    private function sortRows(array $rows, string $sort): array
    {
        $get = [
            'risk'     => fn ($a) => -($a->get('risk')['score'] ?? 0),
            'score'    => fn ($a) => -($a->score ?? -1),
            'output'   => fn ($a) => -(int) $a->mtd->completed,
            'overdue'  => fn ($a) => -(int) $a->standing->overdue,
            'flags'    => fn ($a) => -(int) $a->verdict->flags,
            'rating'   => fn ($a) => -($a->verdict->avg_rating ?? -1),
            'name'     => fn ($a) => mb_strtolower((string) ($a->user->name ?? '')),
        ][$sort] ?? null;

        if (!$get) {
            $get = fn ($a) => -($a->get('risk')['score'] ?? 0);
        }

        usort($rows, function ($x, $y) use ($get) {
            $a = $get($x);
            $b = $get($y);

            if (is_string($a) || is_string($b)) {
                return strcmp((string) $a, (string) $b);
            }

            return $a <=> $b;
        });

        return $rows;
    }
}
