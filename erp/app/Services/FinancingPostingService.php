<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bank;
use App\Models\FinancingCapital;
use App\Models\FinancingLoan;
use App\Models\FinancingTransaction;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use Illuminate\Support\Facades\Auth;

/**
 * Puts the loan book on the books.
 *
 * ── WHICH BOOKS POST, AND WHY NOT ALL OF THEM ─────────────────────────────
 * The desk carries three books and only two of them belong in the ledger:
 *
 *   BORROWED  — posts. A bank loan has no other record anywhere in the system:
 *               nothing else created it, so nothing else can be counting it. Left
 *               unposted the debt simply does not exist on the balance sheet
 *               while the bank still expects it back, and the monthly instalment
 *               has to be split by hand — which is how an entire EMI ends up in
 *               Interest Expense.
 *
 *   CAPITAL   — posts. Owner money in and out has no other record either.
 *
 *   LENT      — posts NOTHING, deliberately, and this is not a limitation to be
 *               fixed later. When a client takes a visa and pays monthly, the
 *               SALE already put the balance in 1311 Customer Receivable
 *               (App\Traits\PostsSaleJournal does it at the moment of sale).
 *               Booking the same balance again as a loan asset would show twice
 *               what the client owes, and there is no honest account for the
 *               other leg. The lent book is a follow-up register; its accounting
 *               lives in the sale.
 *
 * ── THE ONE QUESTION THAT DECIDES A BORROWING ─────────────────────────────
 * Whose name is the agreement in?
 *
 *   COMPANY'S name  — the company owes the bank. The debt is a liability, and
 *                     because there is a principal on the balance sheet the
 *                     interest against it is a real cost: split every instalment
 *                     into principal and interest.
 *
 *   A PERSON's name — the company owes nobody. No liability, and no interest may
 *                     be claimed: there is no principal for it to be interest ON,
 *                     which is the first thing an auditor tests for. If the
 *                     company pays an instalment anyway, the whole amount is
 *                     money taken out for personal use — one line to Drawings,
 *                     never split, and Interest Expense is never touched.
 *
 * assertNoInterestOnPersonalDebt() enforces the second case as a hard stop
 * rather than a convention, because the cost of getting it wrong is a disallowed
 * expense plus penalty, and a convention is only as good as the next edit.
 *
 * ── EVERY ENTRY IS TRACEABLE AND UNDOABLE ─────────────────────────────────
 * Each entry carries source/source_id back to the row that caused it, the same
 * convention bank transactions use. Corrections REVERSE (a mirrored entry linked
 * by reversed_journal_entry_id); nothing is ever hard-deleted, because a ledger
 * that can lose a line is not a ledger.
 */
class FinancingPostingService
{
    /**
     * The money arriving on a loan the company took.
     *
     *     Dr  bank                 what actually landed
     *     Dr  8530 Processing Fee  what the lender kept          (if any)
     *     Cr  liability            the FULL sanctioned amount
     *
     * The credit is the sanctioned figure, not the figure that arrived — that is
     * what has to be repaid. Netting the fee off the debt would show a smaller
     * loan than the bank is owed and hide the fee from the P&L entirely.
     *
     * Returns null when the loan is not one that posts, so the caller can record
     * every kind of arrangement through the same path.
     */
    public function postLoanSetup(FinancingLoan $loan): ?JournalEntry
    {
        // A running account opens empty. Nothing has been taken yet, so there is
        // no amount to post and an opening entry would be a fiction — its ledger
        // life begins with the first drawdown.
        if ($loan->is_running) {
            return null;
        }

        // Cash the company actually handed over on a loan it gave. Rare, and
        // deliberately narrow — see lentPosts().
        if ($loan->direction === 'lent') {
            return $this->postLentDisbursement($loan);
        }

        // Only a borrowing in the company's own name creates a liability. A
        // personal loan is not the company's debt — see the note at the top.
        if ($loan->direction !== 'borrowed' || $loan->taken_for !== 'company') {
            return null;
        }

        if (! $loan->disbursement_bank_id || ! $loan->gl_account_id) {
            return null;
        }

        if ($this->entryFor('financing_loan', $loan->id)) {
            return null;   // already posted; regenerating would double the debt
        }

        $bankAccountId = $this->bankAccountId($loan->disbursement_bank_id);
        $principal     = round((float) $loan->principal, 2);
        $fee           = round((float) $loan->processing_fee, 2);
        $net           = round($principal - $fee, 2);

        $lines = [
            ['account_id' => $bankAccountId, 'debit' => $net, 'credit' => 0,
             'note' => 'Loan received from ' . $loan->counterparty_name],
        ];

        if ($fee > 0) {
            $lines[] = ['account_id' => $this->accountId('loan_processing_fee'), 'debit' => $fee, 'credit' => 0,
                        'note' => 'Processing fee deducted at source'];
        }

        $lines[] = ['account_id' => (int) $loan->gl_account_id, 'debit' => 0, 'credit' => $principal,
                    'note' => 'Loan principal — ' . ($loan->account_no ?: $loan->counterparty_name),
                    'party_type' => $loan->party_type, 'party_id' => $loan->party_id];

        return $this->write(
            companyId:   (int) $loan->company_id,
            date:        $loan->start_date,
            reference:   'FIN-' . str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT),
            source:      'financing_loan',
            sourceId:    $loan->id,
            description: trim(($loan->kind ?: 'Loan') . ' received from ' . $loan->counterparty_name
                            . ($fee > 0 ? ', processing fee deducted at source' : '')),
            lines:       $lines,
        );
    }

    /**
     * Money moving on a loan — an instalment, an interest payment, a settlement.
     *
     * A borrowing in the company's name:
     *
     *     Dr  liability      principal recovered this month
     *     Dr  8520 Interest  interest for this month
     *     Dr  8530 Fee       the lender's closure charge, if any
     *     Cr  2280 TDS       tax withheld, if any
     *     Cr  bank           what actually left the account
     *
     * The split comes from the schedule, per instalment, because it moves every
     * month: early instalments are mostly interest and later ones mostly
     * principal. A fixed split overstates expenses and leaves the loan never
     * reducing.
     *
     * A loan in a person's own name, paid from a company account:
     *
     *     Dr  3210 Drawings  the WHOLE amount
     *     Cr  bank           the whole amount
     *
     * Nothing is split and Interest Expense is not touched. Paid from his own
     * pocket, nothing is posted at all — no company money moved.
     */
    public function postPayment(FinancingTransaction $txn): ?JournalEntry
    {
        $loan = $txn->loan;

        if (! $loan) {
            return null;
        }

        if ($this->entryFor('financing_txn', $txn->id)) {
            return null;
        }

        // Money coming back on a loan we GAVE. Most of those post nothing — see
        // lentPosts() — but a director settling his current account is real cash
        // returning to a real account, and nothing else records it.
        if ($loan->direction === 'lent') {
            return $this->postLentReceipt($loan, $txn);
        }

        if ($loan->direction !== 'borrowed') {
            return null;
        }

        return $loan->is_personal
            ? $this->postPersonalInstalment($loan, $txn)
            : $this->postCompanyInstalment($loan, $txn);
    }

    /**
     * Owner money in or out.
     *
     *     investment  Dr bank / Cr 3110 Owner Investment    — equity goes up
     *     drawings    Dr 3210 Drawings / Cr bank            — equity goes down
     *
     * Drawings are NOT an expense. They do not reduce profit; they reduce what
     * the owner has in the business. Booking them as a cost understates profit
     * and the expense is disallowed.
     */
    public function postCapital(FinancingCapital $capital): ?JournalEntry
    {
        if (! $capital->bank_id) {
            return null;   // no account named, so there is no second leg to write
        }

        if ($this->entryFor('financing_capital', $capital->id)) {
            return null;
        }

        $bankAccountId = $this->bankAccountId($capital->bank_id);
        $amount        = round((float) $capital->amount, 2);
        $isInvestment  = $capital->kind === 'investment';

        $equityAccountId = $this->accountId($isInvestment ? 'owners_equity' : 'owner_drawings');

        $lines = $isInvestment
            ? [
                ['account_id' => $bankAccountId,   'debit' => $amount, 'credit' => 0,
                 'note' => 'Owner investment received'],
                ['account_id' => $equityAccountId, 'debit' => 0, 'credit' => $amount,
                 'note' => 'Invested by ' . $capital->person_name],
            ]
            : [
                ['account_id' => $equityAccountId, 'debit' => $amount, 'credit' => 0,
                 'note' => 'Withdrawn by ' . $capital->person_name],
                ['account_id' => $bankAccountId,   'debit' => 0, 'credit' => $amount,
                 'note' => 'Personal withdrawal by ' . $capital->person_name],
            ];

        return $this->write(
            companyId:   (int) $capital->company_id,
            date:        $capital->date,
            reference:   'FINCAP-' . str_pad((string) $capital->id, 5, '0', STR_PAD_LEFT),
            source:      'financing_capital',
            sourceId:    $capital->id,
            description: ($isInvestment ? 'Owner investment by ' : 'Personal withdrawal by ')
                            . $capital->person_name
                            . ($capital->reason ? ' — ' . $capital->reason : ''),
            lines:       $lines,
        );
    }

    /**
     * Undo a posted entry by mirroring it, never by deleting it.
     *
     * A correction has to leave the original visible: a ledger whose lines can
     * disappear cannot be audited, and whoever asks "why did this figure move"
     * six months from now needs both halves to read.
     */
    public function reverse(JournalEntry $entry, ?string $reason = null): ?JournalEntry
    {
        $entry->loadMissing('items');

        // Reversing a reversal, or reversing twice, would swing the balance back
        // the wrong way and look like a fresh transaction.
        if ($entry->reversed_journal_entry_id
            || JournalEntry::where('reversed_journal_entry_id', $entry->id)->exists()
            || $entry->items->isEmpty()) {
            return null;
        }

        $lines = $entry->items->map(fn (JournalItem $item) => [
            'account_id' => $item->account_id,
            'debit'      => $item->credit,
            'credit'     => $item->debit,
            'note'       => 'Reversal — ' . ($item->note ?: $entry->description),
            'party_type' => $item->party_type,
            'party_id'   => $item->party_id,
        ])->all();

        return $this->write(
            companyId:   (int) $entry->company_id,
            date:        now()->toDateString(),
            reference:   'REV-' . $entry->reference,
            source:      $entry->source,
            sourceId:    $entry->source_id,
            description: 'Reversal of: ' . $entry->description . ($reason ? ' — ' . $reason : ''),
            lines:       $lines,
            reversalOf:  $entry->id,
        );
    }

    /** Every entry this service wrote for a row, newest last. */
    public function entriesFor(string $source, int $sourceId)
    {
        return JournalEntry::where('source', $source)->where('source_id', $sourceId)
            ->orderBy('id')->get();
    }

    /* ── The two borrowing cases ────────────────────────────────────────── */

    private function postCompanyInstalment(FinancingLoan $loan, FinancingTransaction $txn): ?JournalEntry
    {
        if (! $loan->gl_account_id || ! $txn->bank_id) {
            return null;
        }

        $bankAccountId = $this->bankAccountId($txn->bank_id);

        $principal = round((float) $txn->principal_part, 2);
        $interest  = round((float) $txn->interest_part, 2);
        $fee       = round((float) $txn->fee_part, 2);
        $tds       = round((float) $txn->tds_amount, 2);
        $fromBank  = round((float) $txn->amount - $tds, 2);

        $lines = [];

        if ($principal > 0) {
            $lines[] = ['account_id' => (int) $loan->gl_account_id, 'debit' => $principal, 'credit' => 0,
                        'note' => 'Principal repaid', 'party_type' => $loan->party_type, 'party_id' => $loan->party_id];
        }

        if ($interest > 0) {
            // The loan's own interest account when one was chosen at setup,
            // otherwise the chart's Interest Expense. Either way it is only
            // reachable from here because a principal exists on the balance sheet.
            $lines[] = ['account_id' => (int) ($loan->gl_interest_account_id ?: $this->accountId('loan_interest_expense')),
                        'debit' => $interest, 'credit' => 0, 'note' => 'Interest for the period'];
        }

        if ($fee > 0) {
            $lines[] = ['account_id' => $this->accountId('loan_processing_fee'), 'debit' => $fee, 'credit' => 0,
                        'note' => 'Lender charge'];
        }

        if ($tds > 0) {
            // Government money being held, not a cost. It sits in 2280 until it
            // is deposited with the NBR.
            $lines[] = ['account_id' => $this->accountId('withholding_tax_payable'), 'debit' => 0, 'credit' => $tds,
                        'note' => 'Tax deducted at source', 'party_type' => $loan->party_type, 'party_id' => $loan->party_id];
        }

        if ($fromBank > 0) {
            $lines[] = ['account_id' => $bankAccountId, 'debit' => 0, 'credit' => $fromBank,
                        'note' => 'Paid to ' . $loan->counterparty_name];
        }

        if (count($lines) < 2) {
            return null;
        }

        return $this->write(
            companyId:   (int) $loan->company_id,
            date:        $txn->date,
            reference:   'FINPAY-' . str_pad((string) $txn->id, 5, '0', STR_PAD_LEFT),
            source:      'financing_txn',
            sourceId:    $txn->id,
            description: $this->instalmentNarration($loan, $txn),
            lines:       $lines,
        );
    }

    /**
     * A loan in a person's own name whose instalment left a company account.
     *
     * One debit, to Drawings, for the whole amount. The loan is not the
     * company's, so none of this instalment is a company cost — splitting out
     * "interest" here would understate profit against a debt the company does
     * not even carry, and it is disallowed at assessment.
     */
    private function postPersonalInstalment(FinancingLoan $loan, FinancingTransaction $txn): ?JournalEntry
    {
        // He paid it himself, or nobody said who did. No company money moved, so
        // there is nothing to record. Unanswered deliberately falls here rather
        // than inventing a withdrawal — see the paid_by migration.
        if ($txn->paid_by !== 'company' || ! $txn->bank_id) {
            return null;
        }

        $bankAccountId = $this->bankAccountId($txn->bank_id);
        $amount        = round((float) $txn->amount, 2);

        if ($amount <= 0) {
            return null;
        }

        $lines = [
            ['account_id' => $this->accountId('owner_drawings'), 'debit' => $amount, 'credit' => 0,
             'note' => trim(($loan->personal_of ?: 'Director') . "'s personal loan instalment")],
            ['account_id' => $bankAccountId, 'debit' => 0, 'credit' => $amount,
             'note' => 'Paid from company account'],
        ];

        $this->assertNoInterestOnPersonalDebt($lines);

        // The loan belongs to no company, so the entry belongs to whichever
        // company's account actually paid — that is whose money left.
        $companyId = Bank::find($txn->bank_id)?->company_id;

        if (! $companyId) {
            throw new \RuntimeException(
                'The account paying this instalment is not attached to a company, so there is no set of books to post it to.'
            );
        }

        return $this->write(
            companyId:   (int) $companyId,
            date:        $txn->date,
            reference:   'FINPAY-' . str_pad((string) $txn->id, 5, '0', STR_PAD_LEFT),
            source:      'financing_txn',
            sourceId:    $txn->id,
            description: ($loan->personal_of ?: 'Director') . "'s personal "
                            . strtolower($loan->kind ?: 'loan') . ' instalment paid from company account',
            lines:       $lines,
        );
    }

    /* ── The lent side ──────────────────────────────────────────────────── */

    /**
     * Does a loan we GAVE belong in the ledger?
     *
     * Only when company cash genuinely left an account.
     *
     * A client instalment plan did NOT: the sale already booked the balance to
     * 1311 Customer Receivable (App\Traits\PostsSaleJournal) at the moment of
     * sale, so booking it again as a loan asset would show twice what the client
     * owes. Nothing was ever paid out to create it — which is exactly why it
     * names no disbursement bank, and that absence is what this test reads.
     *
     * A director's current account is the opposite case. Real money left a real
     * account, no sale produced it, and nothing else in the system is counting
     * it. Left unposted the cash goes missing from the books entirely while the
     * bank statement plainly shows it gone.
     *
     * A running account always qualifies: every taking on it moves cash by
     * definition, so the loan row itself carries no disbursement bank — each
     * drawdown names its own.
     */
    public function lentPosts(FinancingLoan $loan): bool
    {
        return $loan->direction === 'lent'
            && ! empty($loan->gl_account_id)
            && ($loan->is_running || ! empty($loan->disbursement_bank_id));
    }

    /**
     * A term loan the company paid out in one go.
     *
     *     Dr  receivable   what he now owes us   (the loan's gl_account_id)
     *         Cr  bank      what left the account
     *
     * No interest leg: interest is earned over the term, not on the day the
     * money goes out, so it appears on the receipts instead.
     */
    private function postLentDisbursement(FinancingLoan $loan): ?JournalEntry
    {
        if (! $this->lentPosts($loan) || ! $loan->disbursement_bank_id) {
            return null;
        }

        if ($this->entryFor('financing_loan', $loan->id)) {
            return null;   // already posted; regenerating would double the asset
        }

        $amount = round((float) $loan->principal, 2);

        if ($amount <= 0) {
            return null;
        }

        $bank = Bank::find($loan->disbursement_bank_id);

        return $this->write(
            companyId:   $this->lentCompanyId($loan, $loan->disbursement_bank_id),
            date:        $loan->start_date,
            reference:   'FINLENT-' . str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT),
            source:      'financing_loan',
            sourceId:    $loan->id,
            description: trim(($loan->kind ?: 'Loan') . ' given to ' . $loan->counterparty_name),
            lines:       [
                ['account_id' => (int) $loan->gl_account_id, 'debit' => $amount, 'credit' => 0,
                 'note' => 'Advanced to ' . $loan->counterparty_name,
                 'party_type' => $loan->party_type, 'party_id' => $loan->party_id],
                ['account_id' => $this->bankAccountId($loan->disbursement_bank_id), 'debit' => 0, 'credit' => $amount,
                 'note' => 'Paid from ' . ($bank->name ?? 'company account')],
            ],
        );
    }

    /**
     * A fresh taking on a running account — the boss drawing another 5,000.
     *
     *     Dr  1351 Director's Current Account   the amount taken
     *         Cr  bank                          the amount that left
     *
     * ONE entry per taking, never a rolled-up total. A balance is only worth
     * trusting when every movement that produced it can be pointed at, and the
     * entire reason this shape exists is that the takings are irregular.
     *
     * Nothing here is an expense. He owes it back, so the company is no poorer
     * for having advanced it — only less liquid. Booking it to a cost account
     * would understate profit and misstate what he owes in the same stroke.
     */
    public function postDrawdown(FinancingTransaction $txn): ?JournalEntry
    {
        $loan = $txn->loan;

        if (! $loan || $txn->type !== 'disburse') {
            return null;
        }

        if (! $this->lentPosts($loan) || ! $txn->bank_id) {
            return null;
        }

        if ($this->entryFor('financing_txn', $txn->id)) {
            return null;
        }

        $amount = round((float) $txn->amount, 2);

        if ($amount <= 0) {
            return null;
        }

        $bank = Bank::find($txn->bank_id);

        return $this->write(
            companyId:   $this->lentCompanyId($loan, $txn->bank_id),
            date:        $txn->date,
            reference:   'FINDRAW-' . str_pad((string) $txn->id, 5, '0', STR_PAD_LEFT),
            source:      'financing_txn',
            sourceId:    $txn->id,
            description: 'Taken by ' . $loan->counterparty_name
                            . ($txn->memo ? ' — ' . $txn->memo : ''),
            lines:       [
                ['account_id' => (int) $loan->gl_account_id, 'debit' => $amount, 'credit' => 0,
                 'note' => 'Advanced to ' . $loan->counterparty_name,
                 'party_type' => $loan->party_type, 'party_id' => $loan->party_id],
                ['account_id' => $this->bankAccountId($txn->bank_id), 'debit' => 0, 'credit' => $amount,
                 'note' => 'Paid from ' . ($bank->name ?? 'company account')],
            ],
        );
    }

    /**
     * Money coming back on a loan we gave.
     *
     *     Dr  bank             what arrived
     *         Cr  receivable   his debt to us comes down   (principal)
     *         Cr  8110         interest earned, if any
     *
     * Interest here is INCOME, the mirror of a borrowing's cost. Posting it to
     * 8520 because both are called "interest" would turn money earned into money
     * spent and move the profit twice in the wrong direction.
     */
    private function postLentReceipt(FinancingLoan $loan, FinancingTransaction $txn): ?JournalEntry
    {
        if (! $this->lentPosts($loan) || ! $txn->bank_id) {
            return null;
        }

        $principal = round((float) $txn->principal_part, 2);
        $interest  = round((float) $txn->interest_part, 2);
        $received  = round($principal + $interest, 2);

        if ($received <= 0) {
            return null;
        }

        $bank  = Bank::find($txn->bank_id);
        $lines = [
            ['account_id' => $this->bankAccountId($txn->bank_id), 'debit' => $received, 'credit' => 0,
             'note' => 'Received into ' . ($bank->name ?? 'company account')],
        ];

        if ($principal > 0) {
            $lines[] = ['account_id' => (int) $loan->gl_account_id, 'debit' => 0, 'credit' => $principal,
                        'note' => 'Repaid by ' . $loan->counterparty_name,
                        'party_type' => $loan->party_type, 'party_id' => $loan->party_id];
        }

        if ($interest > 0) {
            $lines[] = ['account_id' => $this->accountId('loan_interest_income'), 'debit' => 0, 'credit' => $interest,
                        'note' => 'Interest earned on ' . $loan->counterparty_name];
        }

        if (count($lines) < 2) {
            return null;
        }

        return $this->write(
            companyId:   $this->lentCompanyId($loan, $txn->bank_id),
            date:        $txn->date,
            reference:   'FINRECV-' . str_pad((string) $txn->id, 5, '0', STR_PAD_LEFT),
            source:      'financing_txn',
            sourceId:    $txn->id,
            description: 'Repayment from ' . $loan->counterparty_name
                            . ($txn->memo ? ' — ' . $txn->memo : ''),
            lines:       $lines,
        );
    }

    /**
     * Whose books a lent movement belongs in.
     *
     * The loan's own company when it has one. A running account opened against
     * no company falls back to whichever company's account the money actually
     * moved through — that is whose cash it was. With neither there is nothing
     * to post to, and guessing would file the entry in the wrong company's books.
     */
    private function lentCompanyId(FinancingLoan $loan, ?int $bankId): int
    {
        $companyId = $loan->company_id ?: ($bankId ? Bank::find($bankId)?->company_id : null);

        if (! $companyId) {
            throw new \RuntimeException(
                'Neither the loan nor the account it moved through is attached to a company, so there is no set of books to post it to.'
            );
        }

        return (int) $companyId;
    }

    /* ── Guards ─────────────────────────────────────────────────────────── */

    /**
     * A personal debt may never reach Interest Expense.
     *
     * The display rules already route it to Drawings, but a display rule is not a
     * failure guard: the cost of one wrong edit here is a disallowed expense, tax
     * payable and a penalty, so it is checked against the lines actually about to
     * be written rather than trusted.
     */
    private function assertNoInterestOnPersonalDebt(array $lines): void
    {
        $interestAccountId = $this->accountId('loan_interest_expense');

        foreach ($lines as $line) {
            if ((int) $line['account_id'] === $interestAccountId) {
                throw new \RuntimeException(
                    'Interest cannot be claimed on a loan that is not the company\'s. '
                    . 'There is no principal on the balance sheet for it to be interest on.'
                );
            }
        }
    }

    /* ── Writing ────────────────────────────────────────────────────────── */

    /**
     * Write one balanced entry, or write nothing.
     *
     * The balance check is here rather than at the call sites so no future entry
     * shape can skip it. An unbalanced entry does not fail loudly — it silently
     * stops the trial balance from balancing, and the company that notices is
     * rarely the one that caused it.
     */
    private function write(
        int $companyId,
        $date,
        string $reference,
        string $source,
        int $sourceId,
        string $description,
        array $lines,
        ?int $reversalOf = null,
    ): JournalEntry {
        $debit  = round(array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines)), 2);
        $credit = round(array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines)), 2);

        if (abs($debit - $credit) > 0.01) {
            throw new \RuntimeException(
                "Refusing to post {$reference}: debits ({$debit}) and credits ({$credit}) do not agree."
            );
        }

        $entry = JournalEntry::create([
            'company_id'                => $companyId,
            'created_by'                => Auth::id(),
            'date'                      => $date,
            'reference'                 => $reference,
            'source'                    => $source,
            'source_id'                 => $sourceId,
            'description'               => $description,
            'reversed_journal_entry_id' => $reversalOf,
        ]);

        foreach ($lines as $line) {
            JournalItem::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $line['account_id'],
                'debit'            => $line['debit'] ?? 0,
                'credit'           => $line['credit'] ?? 0,
                'note'             => $line['note'] ?? null,
                'party_type'       => $line['party_type'] ?? null,
                'party_id'         => $line['party_id'] ?? null,
            ]);
        }

        return $entry;
    }

    /**
     * "Term loan instalment 15 of 36" — the narration an auditor can follow back
     * to a line on the amortisation schedule without asking anyone.
     */
    private function instalmentNarration(FinancingLoan $loan, FinancingTransaction $txn): string
    {
        $what = $loan->kind ?: 'Loan';

        if ($txn->interest_part > 0 && $txn->principal_part <= 0) {
            return 'Interest paid to ' . $loan->counterparty_name
                . ($txn->tds_amount > 0 ? ', tax deducted at source' : '');
        }

        if ($txn->schedule && $loan->tenure_months) {
            return $what . ' instalment ' . $txn->schedule->instalment_no . ' of ' . $loan->tenure_months;
        }

        return $what . ' repayment to ' . $loan->counterparty_name
            . ($txn->fee_part > 0 ? ' including closure charge' : '');
    }

    /** Has this row already been posted? Guards every entry against doubling. */
    private function entryFor(string $source, int $sourceId): ?JournalEntry
    {
        return JournalEntry::where('source', $source)->where('source_id', $sourceId)
            ->whereNull('reversed_journal_entry_id')
            ->first();
    }

    /**
     * A posting account by its config name, resolved strictly.
     *
     * Nothing validates config/accounts.php at boot, so a code pointing at a
     * missing account fails only when the one transaction that needs it runs.
     * This makes that failure loud and legible at the single place it matters,
     * instead of posting to null.
     */
    private function accountId(string $configKey): int
    {
        $code = config('accounts.' . $configKey);

        $account = Account::where('code', $code)->first();

        if (! $account) {
            throw new \RuntimeException(
                "Chart of accounts is missing {$code} (accounts.{$configKey}), which this entry needs."
            );
        }

        return (int) $account->id;
    }

    /** A bank's ledger account, which is what an entry can actually name. */
    private function bankAccountId(int $bankId): int
    {
        $bank = Bank::find($bankId);

        if (! $bank || ! $bank->account_id) {
            throw new \RuntimeException(
                'Bank "' . ($bank->name ?? '#' . $bankId) . '" is not linked to a chart-of-accounts account.'
            );
        }

        return (int) $bank->account_id;
    }
}
