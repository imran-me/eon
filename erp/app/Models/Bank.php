<?php

namespace App\Models;

use App\Exceptions\BankOverdrawnException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Bank extends Model
{
    use SoftDeletes;

    protected $table = 'banks';

    protected $fillable = [
        'name',
        'branch_name',
        'account_id',
        'company_id',
        'account_name',
        'account_type',
        'bank_type',
        'type',
        'routing_number',
        'account_number',
        'iban',
        'swift_code',
        'currency',
        'address',
        'status',
        'created_by',
        'balance',
        'last_transaction_date',
        'last_transaction_amount',
        'last_transaction_type',
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_by' => 'string',
        'account_type' => 'string',
        'bank_type' => 'string',
        'type' => 'string',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * What this account actually holds, read from the ledger.
     *
     * The single definition every "can this money leave?" check must use.
     *
     * NOT the `balance` column. That column is maintained by hand — incremented
     * and decremented by whichever flow happens to move money — and it drifts:
     * on 2026-08-13 it read 64,440.00 for OFFICE CASH while the journal said
     * 40,000.00, and 0.00 for BKASH (MARCHENT) while the journal said 5.00. A
     * transfer checked against the column would have waved through ৳24,440 that
     * does not exist. The journal is what the statement, the dashboard card and
     * the accounts all report, so it is what the guard reads too.
     *
     * Excludes soft-deleted items AND items whose entry was soft-deleted — a
     * reversed or removed entry is not money in hand. There are no such orphans
     * today, so this changes no displayed figure; it stops one appearing later.
     */
    public function availableBalance(): float
    {
        return $this->account_id ? static::ledgerBalanceForAccount((int) $this->account_id) : 0.0;
    }

    /**
     * The same figure for a bare account id.
     *
     * Needed because the account losing the money is not always the account you
     * are looking at: a "Cash In" on one card credits whatever contra account was
     * chosen, and that contra may be another money-holding account with a balance
     * of its own to protect.
     */
    public static function ledgerBalanceForAccount(int $accountId, bool $lockForUpdate = false, ?string $asOfDate = null): float
    {
        $query = JournalItem::query()
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                if ($asOfDate) {
                    // Reconciliation reads "what the ledger said through the end
                    // of this day" — every existing caller passes nothing and
                    // gets the exact same all-time query as before.
                    $q->whereDate('date', '<=', $asOfDate);
                }
            });

        // assertCanCover() requests a locking/current read. Under MySQL's
        // default REPEATABLE READ isolation, an ordinary aggregate could reuse
        // an older snapshot even after waiting for the account lock.
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return (float) $query
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as balance')
            ->value('balance');
    }

    /**
     * Does this account hold actual money that can run out?
     *
     * True for anything that appears as a card on the Manage Banks page, plus the
     * configured cash accounts even if no bank record points at them.
     *
     * Deliberately NOT "any asset account". Crediting Accounts Receivable means a
     * customer paid their bill, crediting Owner's Capital means the owner put money
     * in — both are normal and neither needs a prior balance. Only the cash and
     * bank accounts are pots that can be emptied.
     */
    public static function isMoneyHoldingAccount(int $accountId): bool
    {
        if (static::where('account_id', $accountId)->where('type', '!=', 'credit_card')->exists()) {
            return true;
        }

        // Portals (bKash/Nagad/etc.) are stored-value accounts too. Purchase
        // flows can pay through them without a Bank row, so they need the same
        // ledger-backed protection as cash and bank accounts.
        if (Portal::where('account_id', $accountId)->exists()) {
            return true;
        }

        $cashCodes = array_filter([
            config('accounts.office_cash'),
            config('accounts.petty_cash_pool'),
            config('accounts.cash'),
        ]);

        return $cashCodes
            && Account::whereKey($accountId)->whereIn('code', $cashCodes)->exists();
    }

    /**
     * Refuse to let an account pay out more than it holds.
     *
     * ── ONE RULE, EVERY MONEY-OUT PATH ────────────────────────────────────────
     * Twelve different screens can take money out of a bank or a cash pot —
     * expenses, salaries, supplier payments, loan disbursement, petty cash
     * issues, transfers, scheduled payments, financing repayments. Each of them
     * writing its own balance check is how the project ended up with three
     * different answers to "how much is in this account": two read the ledger,
     * one read the stale `balance` column, and the rest asked nothing at all.
     *
     * So the check lives here. Every caller gets the same definition of the
     * balance, the same refusal, and the same wording.
     *
     * ── WHY IT THROWS ─────────────────────────────────────────────────────────
     * Callers post inside a transaction. Throwing rolls the whole posting back,
     * which is the only safe outcome — a half-written journal is worse than a
     * refused one. BankOverdrawnException extends SettlementSourceOverdrawnException,
     * which the expense desk already catches and turns into a sentence.
     *
     * ── WHAT IT DELIBERATELY DOES NOT GUARD ───────────────────────────────────
     * Only accounts that hold actual money (isMoneyHoldingAccount). Crediting
     * Accounts Receivable means a customer paid a bill; crediting Owner's Capital
     * means money came in. Those are not pots and must never be blocked.
     *
     * @param  int|null  $accountId  chart account being credited
     * @param  float  $amount  what is being taken out
     * @param  string  $label  what to call it in the message
     * @param  float  $already  amount of this same payment already posted to
     *                          the account in this entry, when the caller
     *                          checks BEFORE writing its lines
     */
    public static function assertCanCover(?int $accountId, float $amount, string $label = 'That account', float $already = 0.0): void
    {
        if (! $accountId || $amount <= 0) {
            return;
        }

        if (DB::transactionLevel() === 0) {
            throw new \LogicException(
                'Bank::assertCanCover() must run inside DB::transaction() so a failed payment can roll back completely.'
            );
        }

        // Every money-out path locks the same account row before reading its
        // balance. This serializes concurrent payments from one account: the
        // second request sees the first request's committed journal instead of
        // both requests spending the same balance.
        Account::whereKey($accountId)->lockForUpdate()->firstOrFail();

        if (! static::isMoneyHoldingAccount($accountId)) {
            return;
        }

        // $already lets a caller run this check before OR after writing its own
        // credit line: pass 0 beforehand, or the amount just posted afterwards,
        // and either way the figure compared is what the account held before this
        // payment touched it.
        $available = round(static::ledgerBalanceForAccount($accountId, true) + $already, 2);

        if ($available >= $amount) {
            return;
        }

        throw new BankOverdrawnException(
            $label.' holds '.number_format(max(0, $available), 2)
            .', so a payment of '.number_format($amount, 2).' cannot be made from it'
            .' — that would leave the account '.number_format($amount - $available, 2).' overdrawn.'
            .' Record the deposit that funds it first, or choose the account the money actually left.'
        );
    }
}
