<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
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
    public static function ledgerBalanceForAccount(int $accountId): float
    {
        return (float) \App\Models\JournalItem::query()
            ->where('account_id', $accountId)
            ->whereHas('journalEntry')
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
        if (static::where('account_id', $accountId)->exists()) {
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
}
