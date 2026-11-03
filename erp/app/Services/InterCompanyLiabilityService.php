<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Expense;
use App\Models\InterCompanyLiability;
use App\Models\InterCompanyLiabilityTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The Liability book: who owes whom when one company's expense is paid from
 * another company's bank account.
 *
 * ── WRITES NOTHING TO THE LEDGER ──────────────────────────────────────────
 * No journal entry, no balance movement, no account. Owner's instruction,
 * 2026-08-22. What that leaves unfixed is documented in full on the
 * create_inter_company_liability_tables migration — read it before assuming
 * this desk makes the books balance, because it does not.
 *
 * ── AND MUST NEVER BREAK AN EXPENSE ───────────────────────────────────────
 * raiseFor() is called from ExpenseController::approve(), a shared path that
 * all twelve companies use. A tracking record failing must never stop money
 * being posted, so every public method here is guarded twice:
 *
 *   1. Schema::hasTable() — the window between "code deployed" and "migration
 *      run" is otherwise a 500 on the expense desk for everyone. This is the
 *      shape of the 2026-08-10 outage.
 *   2. try/catch that logs and returns null — a bug in here degrades to "the
 *      liability was not recorded", never to "the expense could not be
 *      approved".
 *
 * Both are why raiseFor() is idempotent: it updateOrCreates on expense_id, so
 * a row missed during an outage can be recovered by simply approving again or
 * running the backfill, with no risk of booking the same debt twice.
 *
 * Pattern copied from App\Services\DailyFundService, which guards itself the
 * same way and for the same reason.
 */
class InterCompanyLiabilityService
{
    /** Cached per request — hasTable() is a schema query, not free in a loop. */
    private static ?bool $tableReady = null;

    /**
     * Are the tables actually there? False during the deploy window, and on any
     * install where the migration has not been run. Everything public checks it.
     */
    public function ready(): bool
    {
        if (self::$tableReady !== null) {
            return self::$tableReady;
        }

        try {
            return self::$tableReady = Schema::hasTable('inter_company_liabilities')
                && Schema::hasTable('inter_company_liability_transactions');
        } catch (\Throwable $e) {
            // A database that cannot answer is a database that cannot host the
            // desk either. Never let the question itself throw.
            return self::$tableReady = false;
        }
    }

    /**
     * Does this expense create a debt between two companies?
     *
     * True only when it was paid from a BANK account whose company differs from
     * the company the expense belongs to. Petty cash and own-pocket are excluded
     * deliberately (owner, 2026-08-22): a float is company-scoped and cannot
     * cross companies in the first place, and an own-pocket expense is already
     * a debt — to the person, tracked as 2240 Employee Reimbursement Payable.
     */
    public function isCrossCompany(Expense $expense): bool
    {
        if (! $expense->bank_id || ! $expense->company_id) {
            return false;
        }

        $bank = $expense->relationLoaded('bank') ? $expense->bank : Bank::find($expense->bank_id);

        if (! $bank || ! $bank->company_id) {
            return false;
        }

        return (int) $bank->company_id !== (int) $expense->company_id;
    }

    /**
     * Record that the expense's company now owes the paying bank's company.
     *
     * Called from approve(), never from store(): filing an expense is a claim,
     * and a claim that is later rejected must not leave a debt on the paying
     * company's book.
     *
     * Returns null when there is nothing to record, when the tables are absent,
     * or when anything at all goes wrong — see the class note. It never throws.
     */
    public function raiseFor(Expense $expense): ?InterCompanyLiability
    {
        if (! $this->ready() || ! $this->isCrossCompany($expense)) {
            return null;
        }

        try {
            $bank = $expense->relationLoaded('bank') ? $expense->bank : Bank::find($expense->bank_id);

            return DB::transaction(function () use ($expense, $bank) {
                $liabilityAmount = round(
                    (float) $expense->amount + (float) ($expense->bank_charge_amount ?? 0),
                    2
                );

                // Keyed on the expense so a re-approval after a reversal reuses
                // the same row rather than raising the debt a second time. The
                // unique index on expense_id backs this up at the DB level.
                $liability = InterCompanyLiability::withTrashed()
                    ->firstOrNew(['expense_id' => $expense->id]);

                // A row that was soft-deleted with its expense comes back when
                // the expense does — the money still left the other company.
                if ($liability->trashed()) {
                    $liability->restore();
                }

                $alreadySettled = (float) ($liability->settled_amount ?? 0);

                $liability->fill([
                    'debtor_company_id' => (int) $expense->company_id,
                    'creditor_company_id' => (int) $bank->company_id,
                    'bank_id' => (int) $expense->bank_id,
                    'amount' => $liabilityAmount,
                    'incurred_on' => $expense->expense_date,
                    'title' => $expense->title,
                    'reference' => $expense->reference,
                    // A reversed-and-re-approved expense returns to active, but
                    // only if it is not already fully covered by settlements
                    // made in the meantime.
                    'status' => $alreadySettled >= $liabilityAmount
                        ? InterCompanyLiability::SETTLED
                        : InterCompanyLiability::ACTIVE,
                ]);

                if (! $liability->exists) {
                    $liability->created_by = auth()->id();
                }

                $liability->save();

                // One 'raise' row per liability, rewritten rather than appended
                // so re-approving an edited expense does not stack duplicate
                // openings on the transaction list.
                InterCompanyLiabilityTransaction::updateOrCreate(
                    [
                        'inter_company_liability_id' => $liability->id,
                        'type' => InterCompanyLiabilityTransaction::RAISE,
                    ],
                    [
                        'date' => $expense->expense_date,
                        'amount' => $liabilityAmount,
                        'memo' => 'Paid from '.($bank->name ?? 'another company\'s account')
                                        .' on behalf of '.($expense->company->name ?? 'the company'),
                        'created_by' => auth()->id(),
                    ]
                );

                return $liability;
            });
        } catch (\Throwable $e) {
            // Logged, not raised. The expense is approved and the money is on
            // the books; a tracking row is not worth undoing that for.
            Log::warning('Inter-company liability could not be recorded.', [
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The expense was reversed — it is a claim again, so the debt it raised is
     * no longer owed. Cancelled rather than deleted: the register should be able
     * to show that this happened.
     *
     * A liability that has already been settled in part is left alone and only
     * flagged in its notes. Money genuinely moved between the two companies;
     * cancelling the record of it would lose that.
     */
    public function cancelFor(Expense $expense): void
    {
        if (! $this->ready()) {
            return;
        }

        try {
            $liability = InterCompanyLiability::where('expense_id', $expense->id)->first();

            if (! $liability) {
                return;
            }

            if ((float) $liability->settled_amount > 0) {
                $liability->update([
                    'notes' => trim(($liability->notes ? $liability->notes."\n" : '')
                        .'Source expense was reversed on '.now()->toDateString()
                        .', but ৳'.number_format((float) $liability->settled_amount, 2)
                        .' had already been settled — left standing for review.'),
                ]);

                return;
            }

            $liability->update(['status' => InterCompanyLiability::CANCELLED]);
        } catch (\Throwable $e) {
            Log::warning('Inter-company liability could not be cancelled.', [
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Settle some or all of a liability.
     *
     * Unlike raiseFor() this one is allowed to fail loudly: it runs from a
     * button the user pressed on the Liability desk, so a refusal is an answer
     * ("that is more than is outstanding"), not a broken page. Nothing shared
     * calls it.
     *
     * @throws \RuntimeException when the amount is not settleable
     */
    public function settle(
        InterCompanyLiability $liability,
        float $amount,
        string $date,
        ?int $bankTransferId = null,
        ?string $memo = null,
        string $type = InterCompanyLiabilityTransaction::SETTLEMENT
    ): InterCompanyLiability {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new \RuntimeException('Enter an amount greater than zero.');
        }

        if ($liability->status === InterCompanyLiability::CANCELLED) {
            throw new \RuntimeException('That liability was cancelled — its expense is no longer approved.');
        }

        $outstanding = $liability->outstanding;

        if ($amount > $outstanding) {
            throw new \RuntimeException(
                'That is more than is outstanding. ৳'.number_format($outstanding, 2).' is still owed.'
            );
        }

        return DB::transaction(function () use ($liability, $amount, $date, $bankTransferId, $memo, $type) {
            InterCompanyLiabilityTransaction::create([
                'inter_company_liability_id' => $liability->id,
                'type' => $type,
                'date' => $date,
                'amount' => $amount,
                'bank_transfer_id' => $bankTransferId,
                'memo' => $memo,
                'created_by' => auth()->id(),
            ]);

            $settled = round((float) $liability->settled_amount + $amount, 2);

            $liability->update([
                'settled_amount' => $settled,
                // Closed the moment nothing is left, so it leaves the Active
                // table and joins the Register without anyone marking it.
                'status' => $settled >= (float) $liability->amount
                    ? ($type === InterCompanyLiabilityTransaction::WRITE_OFF
                        ? InterCompanyLiability::WRITTEN_OFF
                        : InterCompanyLiability::SETTLED)
                    : InterCompanyLiability::ACTIVE,
            ]);

            return $liability->fresh();
        });
    }

    /**
     * Where the book stands, for the strip above the tables. Read over the whole
     * book rather than the page on screen — a total that quietly meant "this
     * page" is the kind of wrong that gets believed.
     */
    public function totals($liabilities): array
    {
        $active = $liabilities->where('status', InterCompanyLiability::ACTIVE);

        return [
            'count' => $liabilities->count(),
            'active' => $active->count(),
            'raised' => round((float) $liabilities->sum('amount'), 2),
            'settled' => round((float) $liabilities->sum('settled_amount'), 2),
            'outstanding' => round((float) $active->sum(fn ($l) => $l->outstanding), 2),
            'pairs' => $active->groupBy(fn ($l) => $l->debtor_company_id.':'.$l->creditor_company_id)->count(),
        ];
    }

    /**
     * Net position per company pair — "Construction owes Travels ৳45,000", one
     * line per pair rather than one per expense. The per-expense rows stay in
     * the table below; this is the answer to "so what is the position between
     * these two companies", which is what actually gets asked.
     */
    public function pairPositions($liabilities): array
    {
        return $liabilities
            ->where('status', InterCompanyLiability::ACTIVE)
            ->groupBy(fn ($l) => $l->debtor_company_id.':'.$l->creditor_company_id)
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'debtor' => $first->debtor?->short_name ?: $first->debtor?->name,
                    'creditor' => $first->creditor?->short_name ?: $first->creditor?->name,
                    'outstanding' => round((float) $rows->sum(fn ($l) => $l->outstanding), 2),
                    'items' => $rows->count(),
                ];
            })
            ->sortByDesc('outstanding')
            ->values()
            ->all();
    }
}
