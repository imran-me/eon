<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bank;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The lower half of the Company Ledger (owner, 2026-09-28): Travels' profit
 * per head, how much of it has actually come in, and the money around it —
 * owner withdrawals and deposits, and what the Travels banks hold in total.
 *
 * Read-only. Deposits / withdrawals are WRITTEN by
 * PartyStatementController::companyFunds() through the Capital & Financing
 * desk's own posting code, so Manage Bank and Accounts read them exactly as
 * they read that desk's entries. Nothing here touches Manage Bank.
 *
 * Every figure is the same one the rest of the ERP shows:
 *   revenue   package (unless voided) + what the agent is billed for his changes
 *   cost      his live tickets + service costs + what the airline kept
 *   received  agent_paid_amount — receipts spread oldest first
 *             (ContractFlightAgentReceipts), the same "received" Flights shows
 */
class CompanyFunds
{
    public const COMPANY_ID = 2; // Travels — the only company that runs contract flights

    /**
     * What a deposit / withdrawal from the Company Ledger is FOR. Each maps to
     * the account it posts against. Owner purposes go through the Financing
     * desk (FinancingCapital) so the capital ledger sees them too. More
     * purposes are added here (owner, 2026-09-28: "I will add those later").
     */
    public const PURPOSES = [
        'withdraw' => [
            'profit_withdrawal' => ['label' => 'Profit withdrawal (owner)', 'capital' => 'drawings', 'reason' => 'Profit withdrawal'],
            'vendor_expense' => ['label' => 'Vendor charge / expense',    'account' => 'general_expense'],
        ],
        'deposit' => [
            'investment' => ['label' => 'Investment (owner)',         'capital' => 'investment', 'reason' => 'Working capital'],
        ],
    ];

    /** Source tag on the journals this page writes itself (non-capital purposes). */
    public const SOURCE = 'company_ledger_cash';

    public static function summary(): array
    {
        try {
            $heads = self::perHead();
            $moves = self::movements();
            $banks = self::banks();
        } catch (\Throwable $e) {
            \Log::warning('CompanyFunds: '.$e->getMessage());

            return ['ok' => false];
        }
        // Department-wise (owner, 2026-10-12): Contract Flight makes its profit
        // per passport (perHead); Ticketing makes it on direct sales and passes
        // Contract Flight's tickets on at net (TicketingProfit); Visa on its
        // Manage Sales invoices (VisaProfit). Company = all three.
        $cfProfit = (float) $heads->sum('profit');
        $tk = TicketingProfit::summary();
        $tkProfit = ($tk['ok'] ?? false) ? (float) $tk['totals']['profit'] : 0.0;
        $va = VisaProfit::summary();
        $vaProfit = ($va['ok'] ?? false) ? (float) $va['totals']['profit'] : 0.0;
        $profit = $cfProfit + $tkProfit + $vaProfit;
        $withdrawn = (float) $moves->where('purpose', 'profit_withdrawal')->sum('amount');

        return [
            'ok' => true,
            'heads' => $heads,
            'moves' => $moves,
            'banks' => $banks,
            'ticketing' => $tk,
            'visa' => $va,
            'totals' => [
                'cf_revenue' => (float) $heads->sum('revenue'),
                'cf_cost' => (float) $heads->sum('cost'),
                'cf_profit' => $cfProfit,
                'tk_profit' => $tkProfit,
                'va_profit' => $vaProfit,
                'revenue' => (float) $heads->sum('revenue'),
                'cost' => (float) $heads->sum('cost'),
                'profit' => $profit,
                'received' => (float) $heads->sum('received'),
                'due' => (float) $heads->sum('due'),
                'withdrawn' => $withdrawn,
                'invested' => (float) $moves->where('purpose', 'investment')->sum('amount'),
                'expense' => (float) $moves->where('purpose', 'vendor_expense')->sum('amount'),
                'held' => $profit - $withdrawn,
                'bank' => (float) $banks->sum('balance'),
            ],
        ];
    }

    /** One row per contract-flight passenger: the whole trip's P&L. */
    public static function perHead(): Collection
    {
        $pax = ContractFlightPassenger::with(['passportHolder:id,name', 'agent:id,name', 'contractFlight:id,flight_number', 'ticketPurchases'])
            ->orderByDesc('id')->get();

        // Change money per passenger, from the change rows themselves.
        $changes = collect();
        if (ContractFlightPassengerChange::installed()) {
            $changes = ContractFlightPassengerChange::whereIn('contract_flight_passenger_id', $pax->pluck('id'))->get()
                ->groupBy('contract_flight_passenger_id');
        }

        return $pax->map(function (ContractFlightPassenger $p) use ($changes) {
            $voided = in_array($p->exception_flag, ContractFlightPassenger::AGENT_CHARGE_VOIDING_FLAGS, true);
            $p->setRelation('flightChanges', $changes->get($p->id, collect())->sortByDesc('id')->values());
            // less a cancelled return's price revision (2026-10-14) — package_charged's rule
            $package = $voided ? 0.0 : $p->chargedPackage();
            // Change money from the SAME reader the passenger card uses
            // (penaltyTotals), so the two can never disagree: billed = fees,
            // penalties and fare differences charged to the agent; borne =
            // what the airline kept + charges we bear + the reissue fees owed
            // to the ticket's vendor/portal (which the old sum here missed).
            $pen = $p->penaltyTotals();
            $revenue = $package + (float) $pen['billed'] + $p->baggageTotals()['charge'] + $p->suspendWithdrawTotals()['charge'];
            $cost = (float) $p->total_travel_cost + (float) $pen['borne'];
            $received = min((float) ($p->agent_paid_amount ?? 0), $revenue);

            return [
                'id' => $p->id,
                'name' => $p->passportHolder?->name ?: 'Passenger #'.$p->id,
                'flight' => $p->contractFlight?->flight_number ?: 'Off flight',
                'agent' => $p->agent?->name,
                'status' => $p->exception_flag ? (ContractFlightPassenger::EXCEPTION_FLAGS[$p->exception_flag] ?? $p->exception_flag) : null,
                'revenue' => round($revenue, 2),
                'cost' => round($cost, 2),
                'profit' => round($revenue - $cost, 2),
                'received' => round($received, 2),
                'due' => round(max(0, $revenue - $received), 2),
            ];
        })->filter(fn ($r) => $r['revenue'] != 0 || $r['cost'] != 0)->values();
    }

    /**
     * Travels money in / out by purpose, wherever it was entered — this page,
     * the Capital & Financing desk, or a journal in Accounts. The PURPOSE is
     * read from the account the entry posted against (owner, 2026-09-28:
     * "purpose tag decides"): 3110 investment, 3210 drawings, and the page's
     * own vendor-charge journals.
     */
    public static function movements(): Collection
    {
        $codes = [
            'investment' => config('accounts.owners_equity'),
            'profit_withdrawal' => config('accounts.owner_drawings'),
        ];
        $ids = Account::whereIn('code', array_filter($codes))->pluck('id', 'code');
        $byAccount = [];
        foreach ($codes as $purpose => $code) {
            if (isset($ids[$code])) {
                $byAccount[$ids[$code]] = $purpose;
            }
        }

        $rows = collect();
        if ($byAccount) {
            $rows = DB::table('journal_items as i')
                ->join('journal_entries as e', 'e.id', '=', 'i.journal_entry_id')
                ->whereIn('i.account_id', array_keys($byAccount))
                ->where('e.company_id', self::COMPANY_ID)
                ->whereNull('i.deleted_at')->whereNull('e.deleted_at')
                ->orderByDesc('e.date')->orderByDesc('e.id')
                ->get(['i.account_id', 'i.debit', 'i.credit', 'e.id', 'e.date', 'e.reference', 'e.description', 'e.source', 'e.beneficiary'])
                ->map(fn ($r) => [
                    'date' => $r->date, 'reference' => $r->reference, 'details' => $r->description,
                    'purpose' => $byAccount[$r->account_id],
                    'direction' => $byAccount[$r->account_id] === 'investment' ? 'in' : 'out',
                    // Investment is a credit to equity; drawings a debit. A
                    // reversal comes through with the opposite sign.
                    'amount' => $byAccount[$r->account_id] === 'investment'
                        ? (float) $r->credit - (float) $r->debit
                        : (float) $r->debit - (float) $r->credit,
                    'who' => $r->beneficiary, 'source' => $r->source, 'entry_id' => $r->id,
                ]);
        }

        // This page's own non-capital purposes (vendor charge / expense).
        $own = DB::table('journal_entries as e')
            ->join('journal_items as i', 'i.journal_entry_id', '=', 'e.id')
            ->where('e.source', self::SOURCE)->where('e.company_id', self::COMPANY_ID)
            ->whereNull('e.deleted_at')->whereNull('i.deleted_at')->where('i.debit', '>', 0)
            ->orderByDesc('e.date')->orderByDesc('e.id')
            ->get(['e.id', 'e.date', 'e.reference', 'e.description', 'e.beneficiary', 'i.debit'])
            ->map(fn ($r) => [
                'date' => $r->date, 'reference' => $r->reference, 'details' => $r->description,
                'purpose' => 'vendor_expense', 'direction' => 'out', 'amount' => (float) $r->debit,
                'who' => $r->beneficiary, 'source' => self::SOURCE, 'entry_id' => $r->id,
            ]);

        return $rows->concat($own)->sortByDesc(fn ($r) => (string) $r['date'].str_pad((string) $r['entry_id'], 10, '0', STR_PAD_LEFT))->values();
    }

    /** Travels' bank and cash accounts with what each holds now (journal balance). */
    public static function banks(): Collection
    {
        return Bank::where('company_id', self::COMPANY_ID)
            ->where(fn ($q) => $q->where('status', 1)->orWhereNull('status'))
            ->orderBy('name')->get()
            ->map(fn (Bank $b) => [
                'id' => $b->id,
                'name' => trim($b->name.($b->account_number ? ' · '.$b->account_number : '')),
                'balance' => round((float) $b->availableBalance(), 2),
            ]);
    }

    public static function purposeLabel(string $purpose): string
    {
        foreach (self::PURPOSES as $list) {
            if (isset($list[$purpose])) {
                return $list[$purpose]['label'];
            }
        }

        return ucwords(str_replace('_', ' ', $purpose));
    }
}
