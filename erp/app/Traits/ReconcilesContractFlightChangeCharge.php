<?php

namespace App\Traits;

use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;

/**
 * Posting a flight-change charge (reissue / reschedule onto another flight)
 * onto the agent's Party Statement, when the agent is the one who bears it.
 *
 * Third sibling of ReconcilesContractFlightAgent and ...Reissue, and kept
 * apart from both on purpose: the package price is what the agent was
 * quoted, the reissue field is the manual exception cost, and a change is a
 * separate event with a separate date. Each change row is its own ledger
 * row (invoice_id = the change's id), so a passenger moved twice is billed
 * twice and clearing one charge never touches the other.
 *
 * Like its siblings, a cleared charge posts ZERO rather than deleting the
 * row — the reversal stays visible on the statement.
 *
 * The host class must also use PostsPartyLedger and PostsSaleJournal.
 */
trait ReconcilesContractFlightChangeCharge
{
    /**
     * ONE line on the agent's statement per change (owner, 2026-09-28: "the
     * agent will not agree to pay two amounts for one cancellation"):
     *
     *   amount = service fee (charge, when billed to the agent)
     *          + airline penalty (when the fault is the agent's side)
     *
     * e.g. "Cancellation charge ৳8,500 (airline penalty ৳6,000 + service fee
     * ৳2,500)". Behind that one line the journal splits it:
     *
     *   Dr Accounts Receivable (agent)     8,500
     *      Cr 4181 Penalty Recovered              6,000   passed on, not profit
     *      Cr 4183 Change Charges (our fee)       2,500   our income
     *
     * The penalty's cost side (Dr 5171 / Cr 5110) is posted by
     * postChangeCosts(); the vendor never sees the fee.
     */
    protected function reconcileChangeCharge(ContractFlightPassengerChange $change): void
    {
        $fee = $change->isBilled() ? round((float) $change->charge, 2) : 0.0;
        $penalty = (ContractFlightPassengerChange::hasFaultColumns() && $change->fault === 'agent' && $change->agent_id)
            ? round((float) ($change->airline_penalty ?? 0), 2) : 0.0;
        $amount = round($fee + $penalty, 2);

        if (! $change->agent_id) {
            $this->deleteSaleJournal(ContractFlightPassengerChange::LEDGER_TYPE, $change->id);
            return;
        }

        $change->loadMissing(['passenger.passportHolder', 'passenger.contractFlight.creator', 'fromFlight', 'toFlight']);
        $name = $change->passenger?->passportHolder?->name ?: 'passenger';
        // A lane's "New ticket after refund" carries kind reissue; its fee is not a reissue (2026-10-10).
        $label = $change->reason === 'lane_new_ticket' ? 'New ticket charge' : match ($change->kind) {
            // Offload / Deported (2026-10-07) carry their event as the reason;
            // older flagged rows have none and read as before.
            'flagged' => match ($change->reason) {
                'deported' => 'Deportation charge',
                'boarding_denied', 'immigration_offload', 'flight_missed' => 'Offload charge',
                default => 'Cancellation penalty',
            },
            'cancelled' => 'Cancellation penalty',
            'reissue', 'ticket_reissue' => 'Reissue penalty',
            'ticket_refund' => 'Refund penalty',
            'return_cancel' => 'Return cancellation penalty',
            default => 'Reschedule penalty',
        };
        $move = $change->kind === 'flagged' && isset(ContractFlightPassengerChange::OFFLOAD_EVENTS[$change->reason])
            ? mb_strtolower(ContractFlightPassengerChange::OFFLOAD_EVENTS[$change->reason]) . ' on ' . ($change->fromFlight?->flight_number ?: 'his flight')
            : (in_array($change->kind, ['cancelled', 'flagged'], true)
            ? 'taken off ' . ($change->fromFlight?->flight_number ?: 'his flight')
            : (in_array($change->kind, ContractFlightPassengerChange::TICKET_ADJUSTMENT_KINDS, true)
                || ($change->from_flight_id && (int) $change->from_flight_id === (int) $change->to_flight_id)
                ? 'on ' . ($change->fromFlight?->flight_number ?: 'his flight')   // same flight, one ticket
                : ($change->fromFlight?->flight_number ?: 'off flight') . ' → ' . ($change->toFlight?->flight_number ?: 'contract flight')));
        $reference = ($change->toFlight?->flight_number ?: $change->fromFlight?->flight_number ?: 'CF') . '-' . $change->contract_flight_passenger_id . '-C' . $change->id;
        $what = $label;

        $this->reconcilePartyLedgerRow(
            (int) $change->agent_id,
            ContractFlightPassengerChange::LEDGER_TYPE,
            $change->id,
            true,
            $amount,
            [
                'account_id' => null,
                'payment_date' => $change->created_at ?? now(),
                'reference_no' => $reference,
                'remarks' => mb_substr($amount > 0
                    ? $what . ' — ' . $name . ' (' . $move . ')'
                    : $label . ' cleared — ' . $name . ' (' . $move . ')', 0, 250),
            ]
        );

        if ($amount <= 0) {
            $this->deleteSaleJournal(ContractFlightPassengerChange::LEDGER_TYPE, $change->id);
            return;
        }

        $ar = \App\Models\Account::where('code', config('accounts.accounts_receivable'))->value('id');
        // Our fee lands on the Company Ledger (4183); a server without that
        // head falls back to flight revenue, as the fee always posted before.
        $feeAcct = \App\Models\Account::where('code', config('accounts.cf_change_recovered'))->value('id')
            ?: \App\Models\Account::where('code', config('accounts.flight_sales_revenue'))->value('id');
        $penAcct = \App\Models\Account::where('code', config('accounts.cf_penalty_recovered'))->value('id') ?: $feeAcct;
        if (! $ar || ! $feeAcct) {
            return;
        }
        $journal = \App\Models\JournalEntry::where('source', ContractFlightPassengerChange::LEDGER_TYPE)
            ->where('source_id', $change->id)->orderBy('id')->first();
        $agentName = \App\Models\User::whereKey($change->agent_id)->value('name');
        $head = [
            'company_id' => $change->passenger?->contractFlight?->creator?->company_id ?? auth()->user()?->company_id ?? 2,
            'date' => ($change->created_at ?? now())->toDateString(),
            'reference' => $reference,
            'description' => mb_substr($what . ' — ' . $name . ' (' . $move . ')', 0, 250),
            'beneficiary' => $agentName,
            'beneficiary_party_id' => (int) $change->agent_id,
        ];
        if ($journal) {
            $journal->items()->delete();
            $journal->update($head);
        } else {
            $journal = \App\Models\JournalEntry::create($head + [
                'created_by' => auth()->id(),
                'source' => ContractFlightPassengerChange::LEDGER_TYPE,
                'source_id' => $change->id,
            ]);
        }
        \App\Models\JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $ar, 'debit' => $amount, 'credit' => 0,
            'note' => $what . ' — ' . $name, 'party_type' => 'agent', 'party_id' => (int) $change->agent_id]);
        if ($penalty > 0) {
            \App\Models\JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $penAcct, 'debit' => 0, 'credit' => $penalty,
                'note' => 'Airline penalty recovered from agent — ' . $name . ' (' . $move . ')']);
        }
        // A per-ticket charge (Re-assign / Re-Issue) carries the seller's
        // penalty passed on PLUS our own charge (owner, 2026-10-13): the
        // penalty part is RECOVERED (4181) — it cancels its Borne line — and
        // only "our charge ৳…" in the note is our profit (4183).
        $ours = $fee;
        if ($fee > 0 && str_contains((string) $change->note, 'Charge per ticket')) {
            $ours = preg_match('/our charge ৳([\d,]+(?:\.\d+)?)/u', (string) $change->note, $m)
                ? min($fee, round((float) str_replace(',', '', $m[1]), 2)) : 0.0;
            $passed = round($fee - $ours, 2);
            if ($passed > 0) {
                \App\Models\JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $penAcct, 'debit' => 0, 'credit' => $passed,
                    'note' => 'Reissue penalty recovered from agent — ' . $name . ' (' . $move . ')', 'party_type' => 'agent', 'party_id' => (int) $change->agent_id]);
            }
        }
        if ($ours > 0) {
            \App\Models\JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $feeAcct, 'debit' => 0, 'credit' => $ours,
                'note' => 'Our charge (profit) — ' . $name . ' (' . $move . ')', 'party_type' => 'agent', 'party_id' => (int) $change->agent_id]);
        }
    }

    /**
     * Correct a change's agent line BY ADDITION (owner, 2026-10-04: "yes —
     * a reversal line, not an edit"). The change row already carries its new
     * charge / bearer; this compares what his statement holds for it with
     * what it should hold now and adds ONE row for the difference — a debit
     * when it went up, a credit when it came down — with its own journal
     * (Dr AR / Cr 4183, or the reverse). The original row and its journal
     * are never touched. Returns the difference posted.
     */
    protected function correctChangeCharge(ContractFlightPassengerChange $change, string $why): float
    {
        $agentId = $change->agent_id ? (int) $change->agent_id : null;
        $fee = $change->isBilled() ? round((float) $change->charge, 2) : 0.0;
        $penalty = (ContractFlightPassengerChange::hasFaultColumns() && $change->fault === 'agent' && $agentId)
            ? round((float) ($change->airline_penalty ?? 0), 2) : 0.0;
        // Older changes billed the penalty on its own row — it stays there.
        $penalty -= (float) \App\Models\Transaction::where('type', ContractFlightPassengerChange::PENALTY_LEDGER_TYPE)
            ->where('invoice_id', $change->id)->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) n')->value('n');
        $should = round($fee + max(0.0, $penalty), 2);

        $posted = \App\Models\Transaction::where('type', ContractFlightPassengerChange::LEDGER_TYPE)->where('invoice_id', $change->id)
            ->selectRaw('user_id, COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) n')->groupBy('user_id')->pluck('n', 'user_id');
        $change->loadMissing(['passenger.passportHolder', 'passenger.contractFlight.creator', 'fromFlight', 'toFlight']);
        $name = $change->passenger?->passportHolder?->name ?: 'passenger';
        $reference = ($change->toFlight?->flight_number ?: $change->fromFlight?->flight_number ?: 'CF') . '-' . $change->contract_flight_passenger_id . '-C' . $change->id;
        $companyId = $change->passenger?->contractFlight?->creator?->company_id ?? auth()->user()?->company_id ?? 2;
        $total = 0.0;

        // A per-ticket charge (2026-10-13) was posted split — the penalty part to
        // 4181 Recovered, "our charge" to 4183. Its correction follows the same
        // split, worked out from what the journal actually holds for it, so the
        // Company Ledger columns stay true. One agent only; anything else below.
        if (str_contains((string) $change->note, 'Charge per ticket') && $agentId
            && $posted->keys()->every(fn ($uid) => (int) $uid === $agentId)) {
            $penAcct = \App\Models\Account::where('code', config('accounts.cf_penalty_recovered'))->value('id');
            $feeAcct = \App\Models\Account::where('code', config('accounts.cf_change_recovered'))->value('id');
            if ($penAcct && $feeAcct) {
                $ours = preg_match('/our charge ৳([\d,]+(?:\.\d+)?)/u', (string) $change->note, $m) ? round((float) str_replace(',', '', $m[1]), 2) : 0.0;
                $held = \App\Models\JournalItem::query()->join('journal_entries as e', 'e.id', '=', 'journal_items.journal_entry_id')
                    ->whereIn('e.source', [ContractFlightPassengerChange::LEDGER_TYPE, ContractFlightPassengerChange::FIX_SOURCE])
                    ->where('e.source_id', $change->id)->whereNull('e.deleted_at')->whereNull('journal_items.deleted_at')
                    ->whereIn('journal_items.account_id', [$penAcct, $feeAcct])
                    ->selectRaw('journal_items.account_id a, COALESCE(SUM(journal_items.credit),0) - COALESCE(SUM(journal_items.debit),0) n')
                    ->groupBy('journal_items.account_id')->pluck('n', 'a');
                $feeT = round(min($should, $ours), 2);
                $dFee = round($feeT - (float) ($held[$feeAcct] ?? 0), 2);
                $dPen = round(($should - $feeT) - (float) ($held[$penAcct] ?? 0), 2);
                return $this->postChangeFixSplit($change, $agentId, $dPen, $dFee, (int) $penAcct, (int) $feeAcct, $why, $name, $reference, $companyId);
            }
        }

        // Whatever sits on any OTHER agent for this change comes back to 0.
        foreach ($posted as $uid => $net) {
            $target = (int) $uid === $agentId ? $should : 0.0;
            $total += $this->postChangeFix($change, (int) $uid, round($target - (float) $net, 2), $why, $name, $reference, $companyId);
        }
        if ($agentId && ! $posted->has($agentId)) {
            $total += $this->postChangeFix($change, $agentId, $should, $why, $name, $reference, $companyId);
        }
        return round($total, 2);
    }

    /** postChangeFix() with the delta split across 4181 (penalty part) and 4183 (our charge). */
    private function postChangeFixSplit(ContractFlightPassengerChange $change, int $agentId, float $dPen, float $dFee, int $penAcct, int $feeAcct, string $why, string $name, string $reference, int $companyId): float
    {
        $delta = round($dPen + $dFee, 2);
        if (abs($dPen) < 0.005 && abs($dFee) < 0.005) {
            return 0.0;
        }
        $remarks = mb_substr('Correction — ' . $why . ' — ' . $name . ' (' . ($delta >= 0 ? 'charged ' : 'reduced ') . '৳' . number_format(abs($delta), 2) . ')', 0, 250);
        if (abs($delta) >= 0.005) {
            $this->postPartyLedgerRow($agentId, [
                'type' => ContractFlightPassengerChange::LEDGER_TYPE,
                'invoice_id' => $change->id,
                'account_id' => null,
                'payment_date' => now(),
                'reference_no' => $reference,
                'debit' => $delta > 0 ? $delta : 0,
                'credit' => $delta < 0 ? -$delta : 0,
                'remarks' => $remarks,
            ]);
        }
        $ar = \App\Models\Account::where('code', config('accounts.accounts_receivable'))->value('id');
        if ($ar) {
            $entry = \App\Models\JournalEntry::create([
                'company_id' => $companyId,
                'created_by' => auth()->id(),
                'date' => now()->toDateString(),
                'entry_time' => now()->format('H:i:s'),
                'reference' => mb_substr($reference, 0, 190),
                'source' => ContractFlightPassengerChange::FIX_SOURCE,
                'source_id' => $change->id,
                'description' => $remarks,
                'beneficiary' => \App\Models\User::whereKey($agentId)->value('name'),
                'beneficiary_party_id' => $agentId,
            ]);
            // Balanced by construction: AR moves by dPen + dFee, each head by its own part.
            foreach ([[$ar, $delta, true], [$penAcct, $dPen, false], [$feeAcct, $dFee, false]] as [$acct, $amt, $isAr]) {
                if (abs($amt) < 0.005) continue;
                $debit = $isAr ? max(0, $amt) : max(0, -$amt);
                $credit = $isAr ? max(0, -$amt) : max(0, $amt);
                \App\Models\JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $acct, 'debit' => round($debit, 2), 'credit' => round($credit, 2),
                    'note' => $remarks, 'party_type' => $isAr ? 'agent' : null, 'party_id' => $isAr ? $agentId : null]);
            }
        }
        return $delta;
    }

    private function postChangeFix(ContractFlightPassengerChange $change, int $agentId, float $delta, string $why, string $name, string $reference, int $companyId): float
    {
        if (abs($delta) < 0.005) {
            return 0.0;
        }
        $remarks = mb_substr('Correction — ' . $why . ' — ' . $name . ' (' . ($delta > 0 ? 'charged ' : 'reduced ') . '৳' . number_format(abs($delta), 2) . ')', 0, 250);
        $this->postPartyLedgerRow($agentId, [
            'type' => ContractFlightPassengerChange::LEDGER_TYPE,
            'invoice_id' => $change->id,
            'account_id' => null,
            'payment_date' => now(),
            'reference_no' => $reference,
            'debit' => $delta > 0 ? $delta : 0,
            'credit' => $delta < 0 ? -$delta : 0,
            'remarks' => $remarks,
        ]);
        $ar = \App\Models\Account::where('code', config('accounts.accounts_receivable'))->value('id');
        $fee = \App\Models\Account::where('code', config('accounts.cf_change_recovered'))->value('id')
            ?: \App\Models\Account::where('code', config('accounts.flight_sales_revenue'))->value('id');
        if ($ar && $fee) {
            $amount = abs($delta);
            $entry = \App\Models\JournalEntry::create([
                'company_id' => $companyId,
                'created_by' => auth()->id(),
                'date' => now()->toDateString(),
                'entry_time' => now()->format('H:i:s'),
                'reference' => mb_substr($reference, 0, 190),
                'source' => ContractFlightPassengerChange::FIX_SOURCE,
                'source_id' => $change->id,
                'description' => $remarks,
                'beneficiary' => \App\Models\User::whereKey($agentId)->value('name'),
                'beneficiary_party_id' => $agentId,
            ]);
            \App\Models\JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $delta > 0 ? $ar : $fee, 'debit' => $amount, 'credit' => 0,
                'note' => $remarks, 'party_type' => $delta > 0 ? 'agent' : null, 'party_id' => $delta > 0 ? $agentId : null]);
            \App\Models\JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $delta > 0 ? $fee : $ar, 'debit' => 0, 'credit' => $amount,
                'note' => $remarks, 'party_type' => $delta > 0 ? null : 'agent', 'party_id' => $delta > 0 ? null : $agentId]);
        }
        return $delta;
    }

    /**
     * Zero every billed change charge of a passenger about to be deleted —
     * twin of voidAgentLedger()/voidReissueLedger(), called before the
     * delete while the rows still exist (the change rows cascade away with
     * the passenger, and nothing would be left pointing at the debits).
     * Guarded on the table only: an install that has not run the changes
     * migration has no charges to void, and removing a passenger must not
     * fail over it. A real ledger failure is NOT swallowed — it rolls the
     * removal back, exactly as the sibling voids do.
     */
    protected function voidChangeCharges(ContractFlightPassenger $passenger): void
    {
        if (! ContractFlightPassengerChange::installed()) {
            return;
        }
        ContractFlightPassengerChange::where('contract_flight_passenger_id', $passenger->id)
            ->where('bearer', 'agent')->where('charge', '>', 0)->whereNotNull('agent_id')
            ->get()
            ->each(function (ContractFlightPassengerChange $change) {
                $change->charge = 0;
                $this->reconcileChangeCharge($change);
            });
    }
}
