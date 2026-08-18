<?php

namespace App\Services;

use App\Models\CompanyDailyFund;
use App\Models\Expense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The daily cost fund — what a company MAY spend in cash on a day, against what
 * it actually did.
 *
 * Nothing here writes a journal entry, and that is the point. A ceiling is a
 * permission, not a transaction: it has no debit, no credit and no account. The
 * cash itself is already fully modelled elsewhere — 1013 Office Cash for the
 * drawer, 1015 Petty Cash Float for what a custodian is holding — and this
 * service never touches either. Compare it to PettyCashService, which moves real
 * money and therefore posts every time.
 *
 * WHAT COUNTS AS SPEND — cash only. The fund exists to govern the small recurring
 * costs that leave a drawer or a pocket; a bank transfer for a site payment is a
 * different decision made by different people, and counting it here would put
 * every company over its ceiling by mid-morning and make the whole figure
 * meaningless. `isCashSpend()` mirrors ExpenseController::settlementAccountId()
 * exactly, so "what the fund counts" and "what the ledger credits to cash" can
 * never drift apart.
 *
 * NOTHING RESETS — the ceiling stands until superseded; it is the SPEND that is
 * measured per day, so every morning starts at zero used without a nightly job.
 * Cash does not reset either, which is why the two are kept apart: yesterday's
 * unspent taka is still in the custodian's pocket, but yesterday's unspent
 * allowance is simply gone.
 */
class DailyFundService
{
    /**
     * Whether the fund table actually exists on THIS server.
     *
     * A deploy puts the code in place before anyone runs the migration, and both
     * the Petty Cash desk and the Expense list now read this service on every
     * page load. Without this check, the window between "code deployed" and
     * "migration run" is a 500 on two shared pages for all twelve companies —
     * the same shape of outage as 2026-08-10, where working code took the site
     * down because a server step had not happened yet.
     *
     * So a missing table degrades to "no ceiling configured anywhere": the panel
     * renders, every company reads "No daily fund set", the expense form shows
     * no meter, and nothing else on either page is affected. Setting a fund is
     * the one action that refuses, and it says why.
     *
     * Static so the check survives the service being resolved more than once in
     * a request; false is cached too, because a missing table will not appear
     * halfway through a page render.
     */
    private static ?bool $tableReady = null;

    private function tableReady(): bool
    {
        if (self::$tableReady !== null) {
            return self::$tableReady;
        }

        try {
            return self::$tableReady = Schema::hasTable('company_daily_funds');
        } catch (\Throwable $e) {
            // No database, no permission to read the schema — either way the
            // honest answer for a spending ceiling is "there isn't one".
            return self::$tableReady = false;
        }
    }

    /**
     * The fund in force on a date, keyed by company id.
     *
     * Companies with no fund set are absent rather than zero — "no ceiling
     * configured" and "a ceiling of nothing" are different answers, and the
     * screens say so differently.
     */
    public function fundsOn(string $date, ?array $companyIds = null): array
    {
        if (!$this->tableReady()) {
            return [];
        }

        return CompanyDailyFund::query()
            ->inForceOn($date)
            ->when($companyIds, fn ($q) => $q->whereIn('company_id', $companyIds))
            // Rows for one company never overlap, so this returns at most one
            // each. Ordered ascending anyway so that if a hand-edited row ever
            // did overlap, keyBy's last-wins picks the most recent instruction
            // instead of an arbitrary one.
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get(['company_id', 'amount'])
            ->keyBy('company_id')
            ->map(fn ($row) => (float) $row->amount)
            ->all();
    }

    /**
     * Cash spent on a date, keyed by company id.
     */
    public function cashSpentOn(string $date, ?array $companyIds = null): array
    {
        return $this->cashSpendQuery($date, $companyIds)
            ->groupBy('company_id')
            ->pluck(DB::raw('SUM(amount)'), 'company_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * One company's cash spend on a date.
     *
     * `$excludeExpenseId` is for the edit form: an expense being edited is
     * already counted in the day's total, so leaving it in would have the form
     * warn that raising ৳400 to ৳450 breaks a ceiling it is itself ৳400 of.
     */
    public function cashSpentByCompanyOn(int $companyId, string $date, ?int $excludeExpenseId = null): float
    {
        return (float) $this->cashSpendQuery($date, [$companyId])
            ->when($excludeExpenseId, fn ($q) => $q->where('id', '!=', $excludeExpenseId))
            ->sum('amount');
    }

    /**
     * The expenses that made up a day's cash spend for one company — the
     * drill-down behind a progress bar.
     */
    public function breakdownFor(int $companyId, string $date): Collection
    {
        return $this->cashSpendQuery($date, [$companyId])
            ->with([
                'expense_category:id,name',
                'expense_sub_category:id,name',
                'pettyCashFloat:id,custodian_id',
                'pettyCashFloat.custodian:id,name',
                'user:id,name',
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Expense $e) => [
                'id'       => $e->id,
                'title'    => $e->title,
                'source'   => $e->pettyCashFloat?->custodian?->name
                    ? $e->pettyCashFloat->custodian->name . "'s float"
                    : 'Office drawer',
                'category' => collect([
                    $e->expense_category?->name,
                    $e->expense_sub_category?->name,
                ])->filter()->implode(' › '),
                'by'     => $e->user?->name,
                'amount' => (float) $e->amount,
            ]);
    }

    /**
     * Fund, spend and the state of each, one row per company — what the Daily
     * Cost Fund panel and its progress bars are built from.
     */
    public function summaryOn(string $date, Collection $companies): array
    {
        $ids    = $companies->pluck('id')->all();
        $funds  = $this->fundsOn($date, $ids);
        $spends = $this->cashSpentOn($date, $ids);

        $rows = $companies->map(function ($company) use ($funds, $spends) {
            $hasFund = array_key_exists($company->id, $funds);
            $fund    = (float) ($funds[$company->id] ?? 0);
            $spent   = (float) ($spends[$company->id] ?? 0);

            // Usage is uncapped on purpose — the bar clamps itself at 100% for
            // drawing, but "142%" is the number worth reading.
            $usage = $fund > 0 ? (int) round(($spent / $fund) * 100) : ($spent > 0 ? 100 : 0);

            if (!$hasFund)          { $state = 'unset';  }
            elseif ($spent > $fund) { $state = 'over';   }
            elseif ($usage >= 80)   { $state = 'near';   }
            else                    { $state = 'ok';     }

            return [
                'company_id'   => $company->id,
                'company_name' => $company->short_name ?: $company->name,
                'has_fund'     => $hasFund,
                'fund'         => $fund,
                'spent'        => $spent,
                'remaining'    => $fund - $spent,
                'usage'        => $usage,
                'state'        => $state,
            ];
        })->values();

        return [
            'date'  => $date,
            'rows'  => $rows,
            // Totals cover only companies that actually have a ceiling set —
            // adding a company with no fund would inflate "spent" against a
            // total nobody budgeted, and the remaining figure would be a lie.
            'total_fund'      => $rows->where('has_fund', true)->sum('fund'),
            'total_spent'     => $rows->where('has_fund', true)->sum('spent'),
            'total_remaining' => $rows->where('has_fund', true)->sum('remaining'),
            'over_count'      => $rows->where('state', 'over')->count(),
            'unset_count'     => $rows->where('has_fund', false)->count(),
        ];
    }

    /**
     * Set (or clear) a company's ceiling from a date onward.
     *
     * The instruction being recorded is "from this date, the figure is X", so:
     *
     *   1. anything already starting on or after that date is superseded and goes
     *   2. the row spanning the date is truncated to end the day before
     *   3. the new row opens on the date and stays open
     *
     * which leaves exactly one row in force on every date, with no overlap and no
     * gap going forward — the invariant fundsOn() relies on to return one answer.
     * Passing a null amount performs 1 and 2 and stops, which is how a fund is
     * removed: back to "no ceiling configured" rather than a ceiling of zero.
     *
     * History is kept, so last month's report still shows last month's ceiling
     * rather than today's.
     */
    public function save(int $companyId, ?float $amount, string $date, ?string $note = null): ?CompanyDailyFund
    {
        // Reading degrades silently; writing must not. Someone typing a ceiling
        // in and being told "saved" when there is nowhere to save it is worse
        // than being told the truth.
        if (!$this->tableReady()) {
            throw new \RuntimeException(
                'The daily cost fund table has not been created on this server yet. '
                . 'Run `php artisan migrate` and try again.'
            );
        }

        return DB::transaction(function () use ($companyId, $amount, $date, $note) {
            $from = Carbon::parse($date)->startOfDay();

            CompanyDailyFund::where('company_id', $companyId)
                ->whereDate('effective_from', '>=', $from->toDateString())
                ->delete();

            CompanyDailyFund::where('company_id', $companyId)
                ->whereDate('effective_from', '<', $from->toDateString())
                ->where(fn ($q) => $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $from->toDateString()))
                ->update(['effective_to' => $from->copy()->subDay()->toDateString()]);

            if ($amount === null) {
                return null;
            }

            return CompanyDailyFund::create([
                'company_id'     => $companyId,
                'amount'         => round($amount, 2),
                'effective_from' => $from->toDateString(),
                'effective_to'   => null,
                'note'           => $note,
                'created_by'     => auth()->id(),
            ]);
        });
    }

    /**
     * The expenses a fund governs: approved, on the date, paid in cash.
     *
     * "Paid in cash" is `settled from a float` OR `no bank named`, which is
     * ExpenseController::settlementAccountId()'s rule read backwards — it checks
     * the float first and falls through to office cash when no bank is set. An
     * expense carrying a stale bank_id behind a float posts to the float, so it
     * has to count here too.
     */
    private function cashSpendQuery(string $date, ?array $companyIds = null)
    {
        return Expense::query()
            ->where('status', 1)
            ->whereDate('expense_date', $date)
            ->where(fn ($q) => $q->whereNotNull('petty_cash_float_id')->orWhereNull('bank_id'))
            ->when($companyIds, fn ($q) => $q->whereIn('company_id', $companyIds));
    }
}
