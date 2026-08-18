<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PettyCashFloat;
use App\Models\PettyCashTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moving cash between the drawer and a custodian's pocket, and reading back what
 * is in that pocket.
 *
 * The one rule everything here follows: issuing cash is NOT an expense. The money
 * is still the company's, it has only changed hands, so both sides of an issue are
 * assets — the float account goes up, the drawer or bank goes down. It becomes an
 * expense later, when a receipt says what it was spent on, and that happens
 * through the ordinary expense flow rather than here.
 *
 * See the 2026_08_10 petty cash migration for the three-event model.
 */
class PettyCashService
{
    /**
     * The asset account a float sits in.
     *
     * The float names its own account, so a company running a separate site fund
     * can point one somewhere else; config is only the default for new floats.
     */
    public function accountIdFor(PettyCashFloat $float): int
    {
        return (int) $float->account_id;
    }

    /**
     * The pot small spending actually comes out of.
     *
     * Everything that used to reach straight for Office Cash — a cash expense
     * with no bank named, a petty cash issue with no bank named — comes here
     * instead. 1013 holds the whole group's money; this is the small pot in
     * front of it, topped up by journal from 1013 when it runs low.
     *
     * FALLS BACK RATHER THAN THROWS. The configured account may not exist on
     * this server yet: the code deploys before anyone opens the chart of
     * accounts, and `petty_cash_pool` names an account somebody has to create or
     * activate. Throwing would mean no cash expense could be filed by any of the
     * twelve companies until that happened — working code taking the site down
     * because a manual step had not been done, which is the 2026-08-10 failure
     * exactly. So a missing or inactive pool silently means "carry on using
     * Office Cash", which is precisely the behaviour that existed before this
     * account was introduced.
     *
     * The one thing it will not do is invent an account. If office cash is
     * missing too, that is a broken chart and the caller must say so.
     */
    public function cashPotAccount(): Account
    {
        $poolCode = config('accounts.petty_cash_pool');

        if ($poolCode) {
            $pool = Account::where('code', $poolCode)->where('status', 1)->first();

            if ($pool) {
                return $pool;
            }
        }

        $cash = Account::where('code', config('accounts.office_cash'))->first();

        if (!$cash) {
            throw new \RuntimeException(
                'Neither the petty cash pool (' . ($poolCode ?: 'not configured') . ') nor office cash ('
                . config('accounts.office_cash') . ') exists in the chart of accounts, so a cash payment '
                . 'cannot be posted. Add one of them, or pay from a bank.'
            );
        }

        return $cash;
    }

    /**
     * What is left in that pot right now, read from the ledger.
     *
     * Group-wide by design: one pot serves every company, and which company
     * spent what is answered by journal_entries.company_id rather than by
     * splitting the pot. Shown on the Petty Cash desk so it is possible to see
     * that a top-up is due before an expense finds the pot empty.
     */
    public function cashPotBalance(): float
    {
        // Through accountBalance() rather than its own query: that one excludes
        // reversed entries via the journal's soft-delete scope, and a pot that
        // counted reversed cash would send someone to top up money already there
        // — or worse, not send them when it was.
        return $this->accountBalance($this->cashPotAccount()->id);
    }

    /**
     * Every float's movement over one window, read from the ledger.
     *
     * Opening, issued, spent, returned and closing for each custodian, plus the
     * period totals. Built for the petty cash report the way balancesFor() was
     * built for the index: one pair of queries for the lot, never one per row.
     *
     * The ledger is the only source here — not petty_cash_transactions, not
     * expenses.amount. Those are what was ASKED for; the journal is what actually
     * happened, and after an expense splits across a float and a payable only the
     * journal knows how much of it the float really paid. Reading the requests
     * instead would put a figure on a printed report that the accounts disagree
     * with.
     *
     * @param  \Illuminate\Support\Collection<int, PettyCashFloat>  $floats
     * @return array<string, array>  keyed "accountId|partyId|companyId"
     */
    public function movementReport(Collection $floats, string $from, string $to): array
    {
        if ($floats->isEmpty()) {
            return [];
        }

        $accountIds   = $floats->pluck('account_id')->unique()->values();
        $custodianIds = $floats->pluck('custodian_id')->unique()->values();
        $companyIds   = $floats->pluck('company_id')->unique()->values();

        $base = fn () => DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->whereIn('ji.account_id', $accountIds)
            ->where('ji.party_type', 'employee')
            ->whereIn('ji.party_id', $custodianIds)
            ->whereIn('je.company_id', $companyIds)
            // Both sides soft-delete and this is a raw join, so neither global
            // scope applies — without these a reversed entry would still count.
            ->whereNull('ji.deleted_at')
            ->whereNull('je.deleted_at');

        // What each pocket held the moment the window opened.
        $opening = $base()
            ->where('je.date', '<', $from)
            ->groupBy('ji.account_id', 'ji.party_id', 'je.company_id')
            ->get([
                'ji.account_id', 'ji.party_id', 'je.company_id',
                DB::raw('COALESCE(SUM(ji.debit) - SUM(ji.credit), 0) as opening'),
            ])
            ->keyBy(fn ($r) => $r->account_id . '|' . $r->party_id . '|' . $r->company_id);

        // And what moved inside it, split by what caused the movement. `source`
        // is what tells cash handed over apart from cash spent: both are journal
        // lines on the same account for the same person.
        $window = $base()
            ->whereBetween('je.date', [$from, $to])
            ->groupBy('ji.account_id', 'ji.party_id', 'je.company_id', 'je.source')
            ->get([
                'ji.account_id', 'ji.party_id', 'je.company_id', 'je.source',
                DB::raw('COALESCE(SUM(ji.debit), 0) as dr'),
                DB::raw('COALESCE(SUM(ji.credit), 0) as cr'),
            ]);

        $rows = [];

        foreach ($floats as $float) {
            $key = $float->account_id . '|' . $float->custodian_id . '|' . $float->company_id;

            $rows[$key] = [
                'float'    => $float,
                'opening'  => (float) ($opening[$key]->opening ?? 0),
                'issued'   => 0.0,
                'returned' => 0.0,
                'spent'    => 0.0,
                'other'    => 0.0,
            ];
        }

        foreach ($window as $r) {
            $key = $r->account_id . '|' . $r->party_id . '|' . $r->company_id;

            if (!isset($rows[$key])) {
                continue;
            }

            if ($r->source === 'petty_cash') {
                // A debit put cash in the pocket, a credit took it back out.
                $rows[$key]['issued']   += (float) $r->dr;
                $rows[$key]['returned'] += (float) $r->cr;
            } elseif ($r->source === 'expense') {
                $rows[$key]['spent'] += (float) $r->cr;
                // A reversed expense debits the float back; net it off the spend
                // rather than inventing a column nobody asked about.
                $rows[$key]['spent'] -= (float) $r->dr;
            } else {
                // An opening balance entry, a correction posted by hand — real
                // movement that belongs in the closing figure but in none of the
                // three named columns, so it gets its own rather than being
                // silently dropped or quietly added to "issued".
                $rows[$key]['other'] += (float) $r->dr - (float) $r->cr;
            }
        }

        foreach ($rows as $key => $row) {
            $rows[$key]['closing'] = round(
                $row['opening'] + $row['issued'] - $row['returned'] - $row['spent'] + $row['other'],
                2
            );
        }

        return $rows;
    }

    /**
     * The default float account, for floats being created.
     */
    public function defaultAccount(): Account
    {
        $code = config('accounts.petty_cash_float');
        $account = Account::where('code', $code)->first();

        if (!$account) {
            throw new \RuntimeException(
                "Petty cash float account ({$code}) is missing from the chart of accounts. "
                . 'Add it under 1160 Advances & Loans, or point config/accounts.php at an account that exists.'
            );
        }

        return $account;
    }

    /**
     * What the custodian is holding right now.
     *
     * Derived from the ledger rather than stored, so it cannot drift: every issue,
     * every return and every expense settled from this float already writes to the
     * same account with the same party, and this is simply their net. A cached
     * column would be one more thing to disagree with the accounts, and the
     * accounts would still be right.
     */
    public function balanceOf(PettyCashFloat $float): float
    {
        return (float) JournalItem::query()
            ->where('account_id', $float->account_id)
            ->where('party_type', 'employee')
            ->where('party_id', $float->custodian_id)
            ->whereHas('journalEntry', fn ($q) => $q->where('company_id', $float->company_id))
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as balance')
            ->value('balance');
    }

    /**
     * Balances for many floats at once, keyed by float id.
     *
     * The index page would otherwise run one aggregate per row.
     */
    public function balancesFor(Collection $floats): array
    {
        if ($floats->isEmpty()) {
            return [];
        }

        $rows = JournalItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->whereIn('journal_items.account_id', $floats->pluck('account_id')->unique())
            ->where('journal_items.party_type', 'employee')
            ->whereIn('journal_items.party_id', $floats->pluck('custodian_id')->unique())
            ->whereIn('journal_entries.company_id', $floats->pluck('company_id')->unique())
            // Both sides soft-delete, and this is a raw join so neither global
            // scope applies. Without these two the batch figures would count
            // reversed entries that balanceOf() correctly ignores, and the index
            // page would disagree with the float's own screen.
            ->whereNull('journal_items.deleted_at')
            ->whereNull('journal_entries.deleted_at')
            ->groupBy('journal_items.account_id', 'journal_items.party_id', 'journal_entries.company_id')
            ->get([
                'journal_items.account_id',
                'journal_items.party_id',
                'journal_entries.company_id',
                DB::raw('SUM(journal_items.debit) - SUM(journal_items.credit) as balance'),
            ]);

        $keyed = $rows->keyBy(fn ($r) => $r->account_id . ':' . $r->party_id . ':' . $r->company_id);

        return $floats->mapWithKeys(fn ($f) => [
            $f->id => (float) ($keyed[$f->account_id . ':' . $f->custodian_id . ':' . $f->company_id]->balance ?? 0),
        ])->all();
    }

    /**
     * Hand cash over: the drawer (or a bank) goes down, the pocket goes up.
     *
     *     Dr  Petty Cash Float   (party: custodian)
     *     Cr  Office Cash / Bank
     *
     * No expense account is touched, which is the whole point.
     */
    public function issue(PettyCashFloat $float, array $data): PettyCashTransaction
    {
        return $this->record($float, PettyCashTransaction::TYPE_ISSUE, $data);
    }

    /**
     * Take cash back: the pocket goes down, the drawer goes up. The mirror of an
     * issue, and the entry that finally accounts for the change nobody used to
     * write down.
     */
    public function receive(PettyCashFloat $float, array $data): PettyCashTransaction
    {
        return $this->record($float, PettyCashTransaction::TYPE_RETURN, $data);
    }

    /**
     * Both directions, since only the sides of the entry differ.
     */
    private function record(PettyCashFloat $float, string $type, array $data): PettyCashTransaction
    {
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be more than zero.');
        }

        // Resolved before the transaction because the funds check below reads it.
        $counterAccountId = $this->counterAccountId($data['bank_id'] ?? null);

        // You cannot take back more than the custodian is holding. Allowing it
        // would push the float negative, which reads as the company owing its own
        // cash to a member of staff.
        if ($type === PettyCashTransaction::TYPE_RETURN) {
            $held = $this->balanceOf($float);

            if ($amount > $held + 0.001) {
                throw new \InvalidArgumentException(
                    'Only ' . number_format($held, 2) . ' is held on this float, so '
                    . number_format($amount, 2) . ' cannot be returned.'
                );
            }
        }

        // And you cannot hand out cash the drawer does not have. The mirror of
        // the rule above, and it was missing: on 2026-08-13 a ৳2,000 float was
        // issued from an Office Cash drawer standing at exactly 0.00, leaving
        // the account at 2,000.00 Cr — a credit balance on a cash asset, which
        // says the drawer is holding minus two thousand taka. A float account
        // going negative is an accounting error; the DRAWER going negative is a
        // physical impossibility, so this side needed the stricter guard, not
        // the looser one.
        if ($type === PettyCashTransaction::TYPE_ISSUE) {
            $available = $this->accountBalance($counterAccountId);

            if ($amount > $available + 0.001) {
                $source = Account::find($counterAccountId)?->name ?? 'That account';

                throw new \InvalidArgumentException(
                    $source . ' holds ' . number_format($available, 2) . ', so '
                    . number_format($amount, 2) . ' cannot be issued from it. '
                    . 'Record the money going into it first.'
                );
            }
        }

        return DB::transaction(function () use ($float, $type, $amount, $data, $counterAccountId) {
            $isIssue = $type === PettyCashTransaction::TYPE_ISSUE;
            $custodian = $float->custodian->name ?? 'custodian';

            $transaction = PettyCashTransaction::create([
                'petty_cash_float_id' => $float->id,
                'type'                => $type,
                'amount'              => $amount,
                'date'                => $data['date'],
                'bank_id'             => $data['bank_id'] ?? null,
                'attachment'          => $data['attachment'] ?? null,
                'note'                => $data['note'] ?? null,
                'created_by'          => auth()->id(),
            ]);

            $journal = JournalEntry::create([
                'company_id'  => $float->company_id,
                'created_by'  => auth()->id(),
                'date'        => $data['date'],
                'reference'   => 'PC-' . strtoupper(substr($type, 0, 3)) . '-' . str_pad($transaction->id, 5, '0', STR_PAD_LEFT),
                'source'      => 'petty_cash',
                'source_id'   => $transaction->id,
                'description' => $isIssue
                    ? 'Petty cash issued to ' . $custodian
                    : 'Petty cash returned by ' . $custodian,
            ]);

            // The float side always carries the party, because that is the only
            // thing separating one custodian's money from another's.
            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id'       => $float->account_id,
                'debit'            => $isIssue ? $amount : 0,
                'credit'           => $isIssue ? 0 : $amount,
                'note'             => $isIssue ? 'Cash held by ' . $custodian : 'Cash returned by ' . $custodian,
                'party_type'       => 'employee',
                'party_id'         => $float->custodian_id,
            ]);

            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id'       => $counterAccountId,
                'debit'            => $isIssue ? 0 : $amount,
                'credit'           => $isIssue ? $amount : 0,
                'note'             => $isIssue ? 'Petty cash issued' : 'Petty cash returned',
            ]);

            $transaction->update(['journal_entry_id' => $journal->id]);

            return $transaction;
        });
    }

    /**
     * What the source of the cash is actually holding right now.
     *
     * Deliberately NOT scoped by company. Office Cash (1013) is one shared
     * account — `company_id` is NULL on it on purpose, see config/accounts.php —
     * and the entries that move it carry whichever company did the spending. The
     * drawer that ran dry on 2026-08-12 was drained by company 2's withdrawals
     * and then issued from under company 6; scoping this to the float's own
     * company would report a drawer that does not exist and wave the same
     * overdraft straight through.
     *
     * whereHas('journalEntry') rather than a raw join so the entry's soft-delete
     * scope applies — a reversed entry must not still count as cash in hand.
     */
    private function accountBalance(int $accountId): float
    {
        return (float) JournalItem::query()
            ->where('account_id', $accountId)
            ->whereHas('journalEntry')
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as balance')
            ->value('balance');
    }

    /**
     * Where the cash comes from, or goes back to.
     *
     * A bank was named, so it is that bank; otherwise the cash pot — the same
     * rule ExpenseController::settlementAccountId() applies, and both now resolve
     * it through cashPotAccount() so the two cannot disagree about where cash
     * lives even while the pool is being introduced.
     */
    private function counterAccountId(?int $bankId): int
    {
        if ($bankId) {
            $bank = Bank::find($bankId);

            if (!$bank || !$bank->account_id) {
                throw new \RuntimeException('That bank is not linked to a chart-of-accounts account.');
            }

            return (int) $bank->account_id;
        }

        // No bank named, so the cash came out of the pot — the small petty cash
        // pool if one is configured and exists, otherwise Office Cash, which is
        // what this line did before the pool existed.
        return (int) $this->cashPotAccount()->id;
    }
}
