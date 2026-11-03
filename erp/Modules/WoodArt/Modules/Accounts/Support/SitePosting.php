<?php

namespace Modules\WoodArt\Modules\Accounts\Support;

use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Transaction;
use App\Services\ExpenseClassificationService;
use Illuminate\Support\Facades\DB;

/**
 * Posts a Wood Art site-statement row to the REAL books.
 *
 *   client_bill        Dr 1315 Receivable - Interiors     Cr 4410 Contract Revenue - Interiors
 *   client_installment Dr Wood Art's bank                 Cr 1315
 *                      (or, when the money went into ANOTHER company's account:
 *                       Dr 1353 Inter-Company Receivable   Cr 1315)
 *   supplier_bill      Dr the cost head's account         Cr 2111 Supplier Payable / 2112 Subcontractor Payable
 *   supplier_payment   Dr 2111 / 2112                     Cr Wood Art's bank
 *
 * Owner's rule (2026-10-06): what a client pays is booked against RECEIVABLE
 * (1315), never as an advance (2310). And Wood Art's money moves only through
 * Wood Art's own bank: a bank of another company is refused here, whatever the
 * caller sends. (The one exception is a RECEIPT that already landed in another
 * company's account — history — which is booked to Inter-Company Receivable so
 * the books show who holds the money, instead of pretending Wood Art's bank got it.)
 *
 * ─── HOW IT BEHAVES ──────────────────────────────────────────────────────────
 *  · One journal per row, company 6, balanced by construction, written in a
 *    transaction together with the row's link to it.
 *  · A row is posted ONCE. Undoing is a REVERSAL journal (the original stays);
 *    a reversed row cannot be posted again — add a new row instead.
 *  · Money out of the bank goes through Bank::assertCanCover(), the same
 *    overdraw guard every other payment uses, so Wood Art's bank cannot be
 *    driven below what its ledger holds.
 *  · A supplier is paid only what the BOOKS say is owed (posted dues minus
 *    posted payments) — an unposted due is not yet a liability.
 *  · The party (which supplier / which project) lives on the statement row, not
 *    on the journal line: the shared journal screens never see a party type
 *    they do not know.
 */
class SitePosting
{
    public static function company(): int
    {
        return ProjectContext::id();
    }

    /** @return array{0:string,1:string} [message, ...] thrown as \RuntimeException on refusal */
    public static function post(int $statementId, array $opt, int $userId): int
    {
        return DB::transaction(function () use ($statementId, $opt, $userId) {
            $row = DB::table('project_statement')->where('id', $statementId)->lockForUpdate()->first();
            if (! $row || $row->deleted_at) {
                throw new \RuntimeException('That statement entry no longer exists.');
            }
            if ($row->journal_entry_id) {
                throw new \RuntimeException('That entry is already in the books.');
            }
            if ($row->reversal_journal_entry_id) {
                throw new \RuntimeException('That entry was posted and then reversed — add a new entry instead of posting it again.');
            }
            if (! SiteCosts::department((int) $row->expense_department_id)) {
                throw new \RuntimeException('That project does not belong to ' . ProjectContext::name() . '.');
            }

            $amount = round((float) $row->amount, 2);
            if ($amount <= 0) {
                throw new \RuntimeException('The amount must be above zero.');
            }

            $dept = DB::table('expense_departments')->where('id', $row->expense_department_id)->value('name');
            $ref  = 'WA-ST-' . $row->id;

            [$lines, $extra, $desc] = match ($row->kind) {
                'client_bill'        => self::clientBill($row, $amount, $dept),
                'client_installment' => self::installment($row, $amount, $opt, $dept),
                'supplier_bill'      => self::supplierBill($row, $amount, $opt, $dept),
                'supplier_payment'   => self::supplierPayment($row, $amount, $opt, $dept),
                'person_fund'        => self::personFund($row, $amount, $dept),
                'supplier_balance'   => self::supplierBill($row, $amount, $opt, $dept),
                default              => throw new \RuntimeException('Unknown statement entry type.'),
            };

            $journal = JournalEntry::create([
                'company_id'  => self::company(),
                'created_by'  => $userId,
                'date'        => $row->entry_date ?: now()->toDateString(),
                'reference'   => $ref,
                'source'      => 'project_statement',
                'source_id'   => $row->id,
                'description' => $desc,
                'beneficiary' => $row->party ?: $dept,
            ]);

            $dr = $cr = 0.0;
            foreach ($lines as $l) {
                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $l['account_id'],
                    'debit'            => $l['debit'],
                    'credit'           => $l['credit'],
                    'note'             => $l['note'],
                    'party_type'       => $l['party_type'] ?? null,
                    'party_id'         => $l['party_id'] ?? null,
                ]);
                $dr += $l['debit'];
                $cr += $l['credit'];
            }
            if (abs($dr - $cr) > 0.004) {
                throw new \RuntimeException('The entry does not balance — nothing was posted.');
            }

            self::postPartyLedger($row, $amount, false);

            DB::table('project_statement')->where('id', $row->id)->update($extra + [
                'journal_entry_id' => $journal->id,
                'posted_at'        => now(),
                'updated_at'       => now(),
            ]);

            return (int) $journal->id;
        });
    }

    /** Undo a posting with an opposite journal. The original and the row both stay. */
    public static function reverse(int $statementId, string $date, int $userId): int
    {
        return DB::transaction(function () use ($statementId, $date, $userId) {
            $row = DB::table('project_statement')->where('id', $statementId)->lockForUpdate()->first();
            if (! $row || ! $row->journal_entry_id) {
                throw new \RuntimeException('That entry is not in the books, so there is nothing to reverse.');
            }
            if ($row->reversal_journal_entry_id) {
                throw new \RuntimeException('That entry has already been reversed.');
            }

            $orig = JournalEntry::with('items')->findOrFail($row->journal_entry_id);
            abort_unless((int) $orig->company_id === self::company(), 403);

            // Reversing a supplier due that has been paid, or a bill/installment, must not
            // leave the books saying someone was paid more than they were owed.
            if (in_array($row->kind, ['supplier_bill', 'supplier_balance'], true) && $row->party) {
                // supplierBooks() leaves THIS due out, so what it returns is the owed figure
                // without it: payments beyond the other dues would leave the supplier overpaid.
                $b = self::supplierBooks($row->expense_department_id, $row->party, $row->id);
                if ($b['owed'] < -0.004) {
                    throw new \RuntimeException('Payments have already been posted against this supplier — reverse those first.');
                }
            }

            $rev = JournalEntry::create([
                'company_id'                => self::company(),
                'created_by'                => $userId,
                'date'                      => $date,
                'reference'                 => 'REV-' . $orig->reference,
                'source'                    => 'project_statement',
                'source_id'                 => $row->id,
                'description'               => 'Reversal of: ' . $orig->description,
                'reversed_journal_entry_id' => $orig->id,
            ]);

            foreach ($orig->items as $it) {
                JournalItem::create([
                    'journal_entry_id' => $rev->id,
                    'account_id'       => $it->account_id,
                    'debit'            => $it->credit,
                    'credit'           => $it->debit,
                    'note'             => 'Reversal — ' . ($it->note ?: $orig->description),
                    'party_type'       => $it->party_type,
                    'party_id'         => $it->party_id,
                ]);
            }

            self::postPartyLedger($row, (float) $row->amount, true);

            DB::table('project_statement')->where('id', $row->id)->update([
                'reversal_journal_entry_id' => $rev->id, 'updated_at' => now(),
            ]);

            return (int) $rev->id;
        });
    }

    /** Mirror the posted accounting event into the canonical company party statement. */
    private static function postPartyLedger(object $row, float $amount, bool $reversal): void
    {
        if (in_array($row->kind, ['person_fund', 'supplier_balance'], true)) {
            return;   // a staff member's account lives on the employee payable (2240), not in a vendor/client party statement
        }

        $partyId = (int) ($row->party_id ?? 0);
        if (in_array($row->kind, ['client_bill', 'client_installment'], true)) {
            $partyId = (int) DB::table('project_terms')->where('expense_department_id', $row->expense_department_id)->value('client_party_id');
        }
        if (! $partyId) return;

        $debitKinds = ['client_bill', 'supplier_bill'];
        $debit = in_array($row->kind, $debitKinds, true) ? $amount : 0.0;
        $credit = $debit > 0 ? 0.0 : $amount;
        if ($reversal) [$debit, $credit] = [$credit, $debit];

        $last = Transaction::where('company_id', self::company())->where('user_id', $partyId)->lockForUpdate()->latest('id')->first();
        $old = (float) ($last?->balance ?? 0);
        Transaction::updateOrCreate([
            'company_id' => self::company(), 'expense_department_id' => $row->expense_department_id,
            'user_id' => $partyId, 'type' => $reversal ? 'project_statement_reversal' : 'project_statement',
            'invoice_id' => $row->id,
        ], [
            'company_id' => self::company(), 'user_id' => $partyId,
            'expense_department_id' => $row->expense_department_id,
            'user_type' => str_starts_with($row->kind, 'supplier_') ? 'supplier' : 'customer',
            'type' => $reversal ? 'project_statement_reversal' : 'project_statement',
            'invoice_id' => $row->id, 'payment_date' => $row->entry_date ?: now()->toDateString(),
            'reference_no' => ($reversal ? 'REV-' : '') . 'PRJ-' . $row->id,
            'old_balance' => $old, 'debit' => $debit, 'credit' => $credit,
            'balance' => $old + $debit - $credit,
            'remarks' => ($reversal ? 'Reversal — ' : '') . ($row->label ?: str_replace('_', ' ', $row->kind)),
        ]);
    }

    /* ── the four postings ───────────────────────────────────────────────── */

    private static function clientBill(object $row, float $amount, string $dept): array
    {
        $ar  = ProjectContext::account('receivable');
        $rev = ProjectContext::account('revenue');

        return [
            [
                ['account_id' => $ar->id,  'debit' => $amount, 'credit' => 0, 'note' => 'Billed to client — ' . $dept],
                ['account_id' => $rev->id, 'debit' => 0, 'credit' => $amount, 'note' => 'Contract revenue — ' . $dept],
            ],
            [],
            'Client billed — ' . $dept . ($row->label ? ' (' . $row->label . ')' : ''),
        ];
    }

    private static function installment(object $row, float $amount, array $opt, string $dept): array
    {
        $ar = ProjectContext::account('receivable');

        // Never more than the books say the client owes (billed − received so far).
        $owed = self::clientBooks((int) $row->expense_department_id, (int) $row->id);
        if ($amount - $owed['due'] > 0.004) {
            throw new \RuntimeException(
                'The books show the client owes ৳ ' . number_format($owed['due'], 2) . ' (billed ৳ ' . number_format($owed['billed'], 2)
                . ', received ৳ ' . number_format($owed['received'], 2) . '). Bill the client for the contract first, or record a smaller amount.'
            );
        }

        $counter = ($opt['counter'] ?? 'bank') === 'intercompany' ? 'intercompany' : 'bank';

        if ($counter === 'intercompany') {
            $ic = self::account('1353', false);
            $debit = ['account_id' => $ic->id, 'debit' => $amount, 'credit' => 0,
                      'note' => 'Received into another company\'s account — to be settled between the companies'];
            $extra = ['counter' => 'intercompany'];
        } else {
            $bank  = self::bank((int) ($opt['bank_id'] ?? 0));
            $debit = ['account_id' => (int) $bank->account_id, 'debit' => $amount, 'credit' => 0, 'note' => 'Received from client — ' . $dept];
            $extra = ['counter' => 'bank', 'bank_id' => $bank->id];
        }

        return [
            [
                $debit,
                ['account_id' => $ar->id, 'debit' => 0, 'credit' => $amount, 'note' => 'Client installment — ' . $dept],
            ],
            $extra,
            'Client installment — ' . $dept . ($row->label ? ' (' . $row->label . ')' : ''),
        ];
    }

    private static function supplierBill(object $row, float $amount, array $opt, string $dept): array
    {
        if (! $row->party) {
            throw new \RuntimeException('A supplier due needs the supplier\'s name.');
        }

        $subId = (int) ($opt['cost_sub_category_id'] ?? 0);
        $sub   = DB::table('expense_sub_categories')->where('id', $subId)->where('company_id', self::company())->whereNull('deleted_at')->first();
        if (! $sub) {
            throw new \RuntimeException('Choose which cost head this due belongs to.');
        }
        $costAccount = app(ExpenseClassificationService::class)->accountFor((int) $sub->expense_category_id, (int) $sub->id);
        if (! $costAccount) {
            throw new \RuntimeException('That cost head has no account to post to.');
        }

        $kind = ($opt['payable_kind'] ?? 'supplier') === 'contractor' ? 'contractor' : 'supplier';
        $ap   = self::payableAccount($kind);

        return [
            [
                ['account_id' => $costAccount, 'debit' => $amount, 'credit' => 0, 'note' => $sub->name . ' — ' . $row->party . ' — ' . $dept],
                ['account_id' => $ap->id, 'debit' => 0, 'credit' => $amount, 'note' => 'Owed to ' . $row->party . ' — ' . $dept],
            ],
            ['cost_sub_category_id' => $sub->id, 'payable_kind' => $kind],
            'Supplier due — ' . $row->party . ' — ' . $dept . ($row->label ? ' (' . $row->label . ')' : ''),
        ];
    }

    /**
     * Funds provided to a member of staff — sent to him, or paid straight to a supplier for him by
     * another Epal company. It settles what the company owes HIM (Dr 2240, tagged to him, so the
     * Reimbursement desk nets it) and records that the other company paid for this one (Cr 1353).
     * No bank is touched: Epal Constructions has none, and the money moved outside this company's books.
     */
    private static function personFund(object $row, float $amount, string $dept): array
    {
        if (! $row->party_id) {
            throw new \RuntimeException('A funds entry needs the person it was provided to.');
        }

        $payable = app(\App\Services\EmployeeReimbursementService::class)->payableAccount();
        $ic      = self::account('1353', false);

        return [
            [
                ['account_id' => $payable->id, 'debit' => $amount, 'credit' => 0, 'party_type' => 'employee', 'party_id' => (int) $row->party_id,
                 'note' => 'Funds provided to ' . $row->party . ' — ' . $dept],
                ['account_id' => $ic->id, 'debit' => 0, 'credit' => $amount,
                 'note' => 'Provided by another company' . ($row->label ? ' (' . $row->label . ')' : '') . ' — to be settled between the companies'],
            ],
            ['counter' => 'intercompany'],
            'Funds provided to ' . $row->party . ' — ' . $dept . ($row->label ? ' (' . $row->label . ')' : ''),
        ];
    }

    private static function supplierPayment(object $row, float $amount, array $opt, string $dept): array
    {
        if (! $row->party) {
            throw new \RuntimeException('A payment needs the supplier\'s name.');
        }

        $b = self::supplierBooks((int) $row->expense_department_id, (string) $row->party, (int) $row->id);
        if ($b['kind'] === null) {
            throw new \RuntimeException($row->party . ' has no due in the books yet — post the supplier\'s due first.');
        }
        if ($amount - $b['owed'] > 0.004) {
            throw new \RuntimeException(
                'The books show ' . $row->party . ' is owed ৳ ' . number_format($b['owed'], 2) . ', so ৳ ' . number_format($amount, 2) . ' cannot be paid.'
            );
        }

        $bank = self::bank((int) ($opt['bank_id'] ?? 0));
        Bank::assertCanCover((int) $bank->account_id, $amount, $bank->name);   // same overdraw guard as every payment

        $ap = self::payableAccount($b['kind']);

        return [
            [
                ['account_id' => $ap->id, 'debit' => $amount, 'credit' => 0, 'note' => 'Paid to ' . $row->party . ' — ' . $dept],
                ['account_id' => (int) $bank->account_id, 'debit' => 0, 'credit' => $amount, 'note' => 'Payment to ' . $row->party . ' — ' . $dept],
            ],
            ['counter' => 'bank', 'bank_id' => $bank->id, 'payable_kind' => $b['kind']],
            'Supplier payment — ' . $row->party . ' — ' . $dept . ($row->label ? ' (' . $row->label . ')' : ''),
        ];
    }

    /* ── what the books say (posted, not reversed) ───────────────────────── */

    private static function live()
    {
        return DB::table('project_statement')->whereNull('deleted_at')
            ->whereNotNull('journal_entry_id')->whereNull('reversal_journal_entry_id');
    }

    /** @return array{billed:float,received:float,due:float} */
    public static function clientBooks(int $departmentId, int $exceptId = 0): array
    {
        $q = fn (string $kind) => (float) self::live()->where('expense_department_id', $departmentId)
            ->where('kind', $kind)->when($exceptId, fn ($x) => $x->where('id', '<>', $exceptId))->sum('amount');

        $billed = $q('client_bill');
        $recv   = $q('client_installment');

        return ['billed' => $billed, 'received' => $recv, 'due' => $billed - $recv];
    }

    /** @return array{owed:float,kind:?string} what one supplier is owed per the books, and which payable account carries it */
    public static function supplierBooks(int $departmentId, string $party, int $exceptId = 0): array
    {
        $rows = self::live()->where('expense_department_id', $departmentId)->where('party', $party)
            ->whereIn('kind', ['supplier_bill', 'supplier_payment', 'supplier_balance'])
            ->when($exceptId, fn ($x) => $x->where('id', '<>', $exceptId))
            ->get(['kind', 'amount', 'payable_kind']);

        // a `supplier_balance` is the net still owed, carried into the books in one go (see ProjectSummaryController::postAll)
        $bills = $rows->whereIn('kind', ['supplier_bill', 'supplier_balance']);

        return [
            'owed' => (float) $bills->sum('amount') - (float) $rows->where('kind', 'supplier_payment')->sum('amount'),
            'kind' => $bills->isEmpty() ? null : ($bills->first()->payable_kind ?: 'supplier'),
        ];
    }

    /* ── lookups with Wood Art guards ────────────────────────────────────── */

    /** Wood Art's own account when it has one, else the shared one of that code. */
    private static function account(string $code, bool $companyOwned): Account
    {
        $q = Account::where('code', $code)->where('status', 1);
        $a = $companyOwned
            ? (clone $q)->where('company_id', self::company())->first()
            : (clone $q)->whereNull('company_id')->first();

        if (! $a) {
            throw new \RuntimeException("Account {$code} is missing from the chart of accounts, so this cannot be posted.");
        }

        return $a;
    }

    private static function payableAccount(string $kind): Account
    {
        return self::account($kind === 'contractor' ? '2112' : (string) config('accounts.accounts_payable'), false);
    }

    /** Wood Art's own bank, linked to a chart account. Another company's bank is refused. */
    private static function bank(int $id): Bank
    {
        $bank = Bank::whereKey($id)->where('status', 1)->first();

        if (! $bank || (int) $bank->company_id !== self::company()) {
            // A company with no bank of its own (Epal Constructions today) says so, rather than
            // pointing at a bank that is not there.
            if (self::banks()->isEmpty()) {
                throw new \RuntimeException(ProjectContext::name() . ' has no bank account of its own in the system yet, so a bank cannot be used. Receipts can be recorded as received into another company\'s account; add the company\'s bank to pay from it.');
            }

            throw new \RuntimeException('Choose a bank account owned by ' . ProjectContext::name() . ' — another company\'s account cannot be used.');
        }
        if (! $bank->account_id) {
            throw new \RuntimeException('That bank is not linked to a ledger account.');
        }

        return $bank;
    }

    /** Wood Art's banks, for the drop-downs. */
    public static function banks()
    {
        return Bank::where('company_id', self::company())->where('status', 1)->whereNotNull('account_id')
            ->orderBy('name')->get(['id', 'name', 'account_number']);
    }
}
