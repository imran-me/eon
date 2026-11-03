<?php

namespace App\Traits;

use App\Exceptions\StalePassengerException;
use App\Http\Controllers\Dashboard\TicketPurchaseController;
use App\Models\Account;
use App\Models\ContractFlight;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\TicketPurchase;
use App\Models\User;
use App\Services\ContractFlightMessenger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Putting an off-flight passenger on a new flight (owner, 2026-09-26), in
 * one place for both doors into it:
 *  - Re-assign on the Cancelled list (ContractFlightRebookController), and
 *  - picking his passport on the new flight's own passenger builder
 *    (ManageFlightController::syncPassengerRows) — "just search his name and
 *    all info will be filled, just the new ticket has to be done again, with
 *    an additional charge".
 *
 * Tickets are always cancelled through Ticketing's own code
 * (TicketPurchaseController::cancelFromContractFlight): the old ticket stays
 * in Ticket Orders, cancelled, at its full amount with the airline's refund
 * posted beside it and a note — never deleted, never a parallel record
 * (2026-09-28). What the airline kept (the penalty) is then placed by
 * postChangeCosts(): on the Company Ledger, or billed to the agent.
 *
 * The host class must also use ReconcilesContractFlightAgent,
 * ReconcilesContractFlightChangeCharge, PostsPartyLedger and PostsSaleJournal.
 */
trait RebooksContractFlightPassengers
{
    /**
     * Cancel a passenger's live tickets (all, or only $onlyIds) at 0 with a
     * note saying why. Returns what was cancelled: ['ids' => [...], 'labels' => [...]].
     */
    protected function cancelPassengerTickets(ContractFlightPassenger $p, string $why, ?array $onlyIds = null, array $refunds = []): array
    {
        $ticketing = app(TicketPurchaseController::class);
        $ids = [];
        $labels = [];
        $cost = 0.0;
        $penalty = 0.0;
        $p->ticketPurchases()->get()
            ->filter(fn (TicketPurchase $t) => $t->status !== 'cancelled'
                && ($onlyIds === null || in_array((int) $t->id, array_map('intval', $onlyIds), true)))
            ->each(function (TicketPurchase $t) use ($ticketing, $why, $refunds, &$ids, &$labels, &$cost, &$penalty) {
                $was = (float) $t->amount;
                // What the airline gives back; blank = everything.
                $refund = array_key_exists($t->id, $refunds) && $refunds[$t->id] !== null && $refunds[$t->id] !== ''
                    ? max(0.0, min((float) $refunds[$t->id], $was)) : $was;
                $ticketing->cancelFromContractFlight($t, 'Cancelled — '.$why.' · '.now()->format('d M Y'), $refund);
                $ids[] = (int) $t->id;
                $labels[] = $t->ticket_no ?: ('#'.$t->id);
                $cost += $was;
                $penalty += $was - $refund;
            });
        // A cancelled ticket is unlinked from him (owner, 2026-09-30). It is
        // not deleted: it stays in Ticket Orders at its amount with its refund
        // and note, and every change row keeps its id — the fare difference,
        // the "was …" dates and the Company Ledger all read from there.
        if ($ids) {
            $p->ticketPurchases()->detach($ids);
        }
        $p->unsetRelation('ticketPurchases');

        return ['ids' => $ids, 'labels' => $labels, 'cost' => round($cost, 2), 'penalty' => round($penalty, 2)];
    }

    /**
     * Where the costs of a change land (owner, 2026-09-28). Both are already
     * inside ticket cost (5110) — the penalty because the airline kept it,
     * the fare difference because the new ticket was bought dearer — so the
     * company side is a reclass out of 5110 onto the Company Ledger:
     *
     *   penalty          Dr 5171 / Cr 5110                 (Borne)
     *     agent at fault + agent row "Airline penalty"     Dr AR / Cr 4181 (Recovered)
     *   fare difference  Dr 5172 / Cr 5110                 (Borne)
     *     agent bears    + agent row "Fare difference"     Dr AR / Cr 4182 (Recovered)
     *
     * So the Company Ledger shows every cost the change caused, and whether
     * the agent paid it back. Each posting is added, never edited.
     */
    protected function postChangeCosts(ContractFlightPassengerChange $change, ContractFlightPassenger $p, ?ContractFlight $flight, array $parts = ['penalty', 'fare']): void
    {
        if (! ContractFlightPassengerChange::hasFaultColumns()) {
            return;
        }
        $name = $p->passportHolder?->name ?: 'passenger';
        $flightNo = $flight?->flight_number ?: 'contract flight';
        $ref = $flightNo.'-'.$p->id.'-C'.$change->id;
        $companyId = $flight?->creator?->company_id ?? Auth::user()?->company_id ?? 2;
        $agentId = $change->agent_id ? (int) $change->agent_id : null;

        $penalty = in_array('penalty', $parts, true) ? (float) ($change->airline_penalty ?? 0) : 0.0;
        if ($penalty > 0) {
            $this->postCompanyJournal(ContractFlightPassengerChange::PENALTY_COMPANY_SOURCE, $change->id,
                config('accounts.cf_penalty_company'), config('accounts.ticket_purchase_cost'), $penalty,
                'Airline cancellation penalty — '.$name.' ('.$flightNo.')', $ref, $companyId, $agentId);
            // Billed to the agent inside the change's single "Cancellation
            // charge" line (reconcileChangeCharge), not as a second row.
        }

        $fare = in_array('fare', $parts, true) ? (float) ($change->fare_difference ?? 0) : 0.0;
        if ($fare > 0) {
            $this->postCompanyJournal(ContractFlightPassengerChange::FARE_COMPANY_SOURCE, $change->id,
                config('accounts.cf_fare_diff_company'), config('accounts.ticket_purchase_cost'), $fare,
                'Fare difference on new ticket — '.$name.' ('.$flightNo.')', $ref, $companyId, $agentId);
            if ($change->agentFareAmount() > 0 && $agentId) {
                $this->postAgentAdjustment($agentId, ContractFlightPassengerChange::FARE_LEDGER_TYPE, $change->id, $change->agentFareAmount(),
                    'Fare difference billed to agent — '.$name.' ('.$flightNo.')', $ref,
                    config('accounts.cf_fare_diff_recovered'), $companyId);
            }
        }
    }

    /**
     * Everything after an off-flight passenger is on his new flight. The
     * caller has already set contract_flight_id (and, from the builder, the
     * card's own fields and ticket links). $ticketIdsBefore = the live PNRs
     * he held before this move: a reissue cancels exactly those, never a
     * ticket issued for the new flight in the same save.
     */
    protected function completeRebooking(
        ContractFlightPassenger $p,
        ContractFlight $flight,
        string $kind,
        float $charge,
        ?string $bearer,
        ?string $note,
        ?int $fromFlightId,
        array $ticketIdsBefore,
        float $fareDifference = 0.0,
        ?string $fareBearer = null,
        array $refunds = [],
        ?string $fault = null,
        bool $sameFlight = false,
        ?float $agentFareAmount = null
    ): ContractFlightPassengerChange {
        if ($agentFareAmount !== null && (! ContractFlightPassengerChange::splitFareAvailable() || ! $p->agent_id || $fareBearer !== 'agent' || $agentFareAmount < 0)) {
            throw ValidationException::withMessages(['agent_fare_amount' => 'Choose an agent and enter a valid split fare amount.']);
        }
        $p->loadMissing('passportHolder');
        $fromFlight = $fromFlightId ? ContractFlight::withTrashed()->find($fromFlightId) : null;
        $name = $p->passportHolder?->name ?: 'Passenger';
        $charge = round(max(0.0, $charge), 2);
        $bearer = $charge > 0 && in_array($bearer, array_keys(ContractFlightPassengerChange::BEARERS), true) ? $bearer : null;

        // Reissue: his old live tickets are cancelled at 0 and stay linked
        // as his history; reschedule: the same ticket carries on.
        $cancelled = ['ids' => [], 'labels' => []];
        if ($kind === 'reissue' && $ticketIdsBefore) {
            $cancelled = $this->cancelPassengerTickets($p,
                $name.' reissued onto '.$flight->flight_number.($fromFlight ? ' from '.$fromFlight->flight_number : ''),
                $ticketIdsBefore, $refunds);
        }

        // The package charge comes back (he still travels on our
        // arrangement), now naming the new flight.
        $p->setRelation('contractFlight', $flight);
        $this->reconcileAgentLedger($p);

        $fareDifference = round(max(0.0, $fareDifference), 2);
        $fareBearer = in_array($fareBearer, array_keys(ContractFlightPassengerChange::BEARERS), true) ? $fareBearer : null;
        $penalty = (float) ($cancelled['penalty'] ?? 0);
        $change = ContractFlightPassengerChange::create(array_merge([
            'contract_flight_passenger_id' => $p->id,
            'kind' => $kind,
            'from_flight_id' => $fromFlight?->id,
            'to_flight_id' => $flight->id,
            'note' => $note,
            'charge' => $charge > 0 ? $charge : null,
            'bearer' => $bearer,
            'agent_id' => $p->agent_id,
            'previous_ticket_purchase_ids' => $ticketIdsBefore ?: null,
            'created_by' => Auth::id(),
        ], ContractFlightPassengerChange::hasFaultColumns() ? [
            'fault' => $penalty > 0 ? ($fault ?: 'company') : null,
            'airline_penalty' => $penalty > 0 ? $penalty : null,
            'fare_difference' => $fareDifference > 0 ? $fareDifference : null,
            // A reissue with the amount left blank but a bearer chosen is
            // PENDING: settlePendingFareDifference() works it out from the
            // new ticket once he is fully ticketed.
            'fare_bearer' => $fareDifference > 0 || $kind === 'reissue' ? ($fareBearer ?: 'company') : null,
        ] : []));
        if ($agentFareAmount !== null) {
            $change->update(['agent_fare_amount' => $agentFareAmount]);
        }
        $this->reconcileChangeCharge($change);
        $flight->loadMissing('creator');
        $this->postChangeCosts($change, $p, $flight);

        // A new flight is a new journey: the stage restarts from what his
        // data proves on it — the one sanctioned step backwards, because the
        // physical steps he had reached belonged to the old departure.
        // Staying on the same flight (only a later leg changed) is the same
        // departure, so the stage he reached stands.
        $p->unsetRelation('ticketPurchases');
        $stageBefore = $p->stage;
        $stageNow = $sameFlight ? ($p->stepBackIfUnticketed() ?? $stageBefore) : $p->derivedStage();
        if ($stageNow !== $stageBefore) {
            $p->update(['stage' => $stageNow]);
            $this->logRebookEvent($p, 'stage', $stageNow, ContractFlightPassenger::stageLabel($stageNow), 'Restarted on '.$flight->flight_number);
        }

        $this->logRebookEvent($p, 'exception', $kind === 'reissue' ? 'reissue' : 'rescheduled',
            $sameFlight ? ($kind === 'reissue' ? 'Later leg refunded — new ticket, same flight' : 'Later leg reissued — same flight')
                : ($kind === 'reissue' ? 'Reissued onto another flight' : 'Rescheduled onto another flight'),
            implode(' · ', array_filter([
                ($fromFlight?->flight_number ?: 'Off flight').' → '.$flight->flight_number,
                $flight->departure_at ? $flight->departure_at->format('d M Y, h:i A') : null,
                $charge > 0 ? 'Charge ৳'.number_format($charge, 2).' — '.ContractFlightPassengerChange::BEARERS[$bearer] : 'No charge',
                $cancelled['labels'] ? 'Old ticket cancelled: '.implode(', ', $cancelled['labels']).($penalty > 0 ? ' (airline penalty ৳'.number_format($penalty, 2).')' : '') : null,
                $fareDifference > 0 ? 'Fare difference ৳'.number_format($fareDifference, 2).' — '.(ContractFlightPassengerChange::BEARERS[$change->fare_bearer ?? 'company'] ?? '') : null,
                $note,
            ])));

        $flight->syncMoneyFromPassengers();

        // After the commit, so a gateway hiccup can never roll the move
        // back. Only sends if the Messages rule for "rescheduled" is on.
        DB::afterCommit(fn () => ContractFlightMessenger::auto('rescheduled',
            $p->fresh(['contractFlight', 'passportHolder', 'agent', 'ticketPurchases']), $note));

        return $change;
    }

    /**
     * A reissue whose fare difference was left blank (2026-09-28): once the
     * passenger is fully ticketed again, difference = his new live tickets'
     * cost − the tickets he lost (this change's own, or those the cancel
     * before it took). Dearer → posted to whoever was chosen; cheaper or
     * equal → recorded as 0. Runs on every ticket link; settles once.
     */
    protected function settlePendingFareDifference(ContractFlightPassenger $p): void
    {
        try {
            if (! ContractFlightPassengerChange::hasFaultColumns()) {
                return;
            }
            $change = ContractFlightPassengerChange::where('contract_flight_passenger_id', $p->id)
                ->where('kind', 'reissue')->whereNull('fare_difference')->whereNotNull('fare_bearer')
                ->latest('id')->first();
            if (! $change) {
                return;
            }
            $p->unsetRelation('ticketPurchases');
            if (! $p->isFullyTicketed()) {
                return;
            }
            $oldIds = collect($change->previous_ticket_purchase_ids ?: []);
            if ($oldIds->isEmpty()) {
                // The nearest earlier change that actually cancelled tickets —
                // not just the latest one: "Flight Missed (tickets refunded) →
                // Re-assign" writes an empty cancel row in between, and two
                // reissues before a new ticket leave the second one empty.
                $prior = ContractFlightPassengerChange::where('contract_flight_passenger_id', $p->id)
                    ->where('id', '<', $change->id)->whereIn('kind', ['cancelled', 'flagged', 'reissue', 'ticket_refund'])
                    ->whereNotNull('previous_ticket_purchase_ids')
                    ->orderByDesc('id')->get()
                    ->first(fn ($c) => ! empty($c->previous_ticket_purchase_ids));
                $oldIds = collect($prior?->previous_ticket_purchase_ids ?: []);
            }
            $oldIds = $oldIds->map(fn ($id) => (int) $id);
            // New = live tickets on the SAME sectors as the refunded ones
            // (2026-09-30): a leg he kept flies on its old ticket and is not
            // part of the difference.
            $sectors = TicketPurchase::with('legs')->whereIn('id', $oldIds)->get()
                ->map(fn ($t) => ContractFlightPassenger::ticketSector($t))->filter()->unique();
            $live = $p->ticketPurchases()->with('legs')->where('ticket_purchases.status', '!=', 'cancelled')->get()
                ->reject(fn ($t) => $oldIds->contains((int) $t->id))
                ->filter(fn ($t) => $sectors->isEmpty() || $sectors->contains(ContractFlightPassenger::ticketSector($t)));
            if ($live->isEmpty() || $oldIds->isEmpty()) {
                return;
            }
            $old = (float) TicketPurchase::whereIn('id', $oldIds)->sum('amount');
            $new = (float) $live->sum('amount');
            $diff = round(max(0.0, $new - $old), 2);
            if ($change->agent_fare_amount !== null && ((float) $change->agent_fare_amount < 0 || (float) $change->agent_fare_amount > $diff)) {
                throw ValidationException::withMessages(['agent_fare_amount' => 'The agent share exceeds the actual fare difference. Correct the split before saving.']);
            }
            $p->loadMissing(['contractFlight.creator', 'passportHolder']);
            DB::transaction(function () use ($change, $p, $diff) {
                $change = ContractFlightPassengerChange::whereKey($change->id)->lockForUpdate()->firstOrFail();
                if ($change->fare_difference !== null) {
                    return;
                }
                if ($change->agent_fare_amount !== null && (float) $change->agent_fare_amount > $diff) {
                    throw ValidationException::withMessages(['agent_fare_amount' => 'The agent share exceeds the actual fare difference. Correct the split before saving.']);
                }
                $change->update(['fare_difference' => $diff]);
                if ($diff > 0) {
                    $this->postChangeCosts($change, $p, $p->contractFlight, ['fare']);
                }
            });
            $this->logRebookEvent($p, 'finance', 'fare_difference', 'Fare difference worked out',
                'New ticket ৳'.number_format($new, 2).' − old ৳'.number_format($old, 2).' = ৳'.number_format($diff, 2)
                .($diff > 0 ? ' — '.(ContractFlightPassengerChange::BEARERS[$change->fare_bearer] ?? '') : ' — nothing to bear'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Never block a ticket link over this; it retries on the next one.
            \Log::warning('settlePendingFareDifference: '.$e->getMessage());
        }
    }

    /**
     * A reissue fee the ticket's vendor charges us (owner, 2026-09-30) —
     * shared by Re-assign and the per-ticket Re-Issue button:
     * vendor ledger row (Bill — we owe it) + journal Dr 5173 Rebooking &
     * Change Fees (Company Borne) / Cr 2111 Supplier Payable. It shows in the
     * Company Ledger as Borne; when the agent pays, his change-charge line
     * (Cr 4183) is the Recovered side that cancels it. A ticket with no vendor
     * (portal) still posts the journal, with no party row. Skips quietly when
     * the account heads are missing — the move itself still saves.
     */
    protected function postReissueFee(ContractFlightPassengerChange $change, ContractFlightPassenger $p, ContractFlight $flight, int $purchaseId, float $amount, string $bearer, array $parts = []): void
    {
        $amount = round($amount, 2);
        $t = TicketPurchase::find($purchaseId);
        if (! $t || $amount < 0.005) {
            return;
        }
        $cr = Account::where('code', config('accounts.accounts_payable'))->value('id');
        // One seller row + one journal PER PART (owner, 2026-10-06: "two entry
        // supposed to happen, one for fare difference, another the re-issue
        // charge"). [[label, amount, row type, Dr account code], ...]; no parts
        // = one "Reissue fee" row on 5173 as before (Re-assign). Every journal
        // keeps the source REISSUE_FEE_TYPE, so the readers that sum them
        // (penaltyTotals, Company Ledger) see the same total.
        $parts = array_values(array_filter($parts ?: [['Reissue fee', $amount, ContractFlightPassengerChange::REISSUE_FEE_TYPE, config('accounts.cf_change_company')]],
            fn ($x) => round((float) $x[1], 2) >= 0.005));
        if (! $cr || ! $parts) {
            return;
        }
        $name = $p->passportHolder?->name ?: 'passenger';
        $pnr = (string) ($t->ticket_no ?: ('#'.$t->id));
        $vendorId = $t->vendor_id ? (int) $t->vendor_id : null;
        // Name who is owed and who pays, so the Company Ledger row pairs with
        // the agent's "recovered" line at a glance.
        $owedTo = $vendorId ? User::whereKey($vendorId)->value('name') : ($t->portal_id ? Portal::whereKey($t->portal_id)->value('name') : null);
        $agentName = $p->agent_id ? User::whereKey($p->agent_id)->value('name') : null;
        $fromNo = $change->fromFlight?->flight_number;
        $where = $fromNo && $fromNo !== $flight->flight_number ? $fromNo.' → '.$flight->flight_number : 'on '.$flight->flight_number;
        // Bought through a portal: the wallet pays the fee, as it paid the
        // ticket — PortalBalance row + Cr the portal's account.
        $portal = ! $vendorId && $t->portal_id ? Portal::find($t->portal_id) : null;
        $crAcc = $portal && $portal->account_id ? $portal->account_id : $cr;
        // Agent pays: the cost line names the agent, who settles it; we bear:
        // only the vendor, on the payable line.
        $agentPays = $bearer === 'agent' && $p->agent_id;

        foreach ($parts as [$label, $part, $rowType, $drCode]) {
            $part = round((float) $part, 2);
            $dr = Account::where('code', $drCode)->value('id')
                ?: Account::where('code', config('accounts.cf_change_company'))->value('id');
            if (! $dr) {
                continue;
            }
            $why = $label.' — '.$pnr.' · '.$name.' · '.$where
                .($owedTo ? ' · owed to '.$owedTo : '')
                .' · '.($bearer === 'agent' ? 'agent pays ('.($agentName ?: 'agent').') — recovered' : 'we bear');

            if ($vendorId) {
                $this->postPartyLedgerRow($vendorId, [
                    'type' => $rowType,
                    'invoice_id' => $t->id,
                    'account_id' => null,
                    'payment_date' => now(),
                    'reference_no' => $pnr,
                    'debit' => $part,
                    'credit' => 0,
                    'remarks' => mb_substr($why, 0, 250),
                ]);
            }
            if ($portal && $portal->account_id) {
                $portal->refresh();
                $opening = (float) $portal->balance;
                PortalBalance::create([
                    'invoice_id' => $t->id,
                    'portal_id' => $portal->id,
                    'type' => $rowType,
                    'old_balance' => $opening,
                    'debit' => 0,
                    'credit' => $part,
                    'current_balance' => $opening - $part,
                    'remarks' => mb_substr($why, 0, 250),
                    'created_by' => Auth::id(),
                ]);
                $portal->update(['balance' => $opening - $part]);
            }

            $entry = JournalEntry::create([
                'company_id' => $flight->creator?->company_id ?? $t->company_id ?? Auth::user()?->company_id ?? 2,
                'created_by' => Auth::id(),
                'date' => now()->toDateString(),
                'entry_time' => now()->format('H:i:s'),
                'reference' => mb_substr($pnr.'-C'.$change->id, 0, 190),
                'source' => ContractFlightPassengerChange::REISSUE_FEE_TYPE,
                'source_id' => $change->id,
                'description' => mb_substr($why, 0, 250),
                'beneficiary' => $vendorId ? User::whereKey($vendorId)->value('name') : null,
                'beneficiary_party_id' => $vendorId,
            ]);
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $dr, 'debit' => $part, 'credit' => 0,
                'note' => mb_substr($why, 0, 250), 'party_type' => $agentPays ? 'agent' : null, 'party_id' => $agentPays ? (int) $p->agent_id : null]);
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $crAcc, 'debit' => 0, 'credit' => $part,
                'note' => mb_substr($why, 0, 250), 'party_type' => $vendorId ? 'vendor' : null, 'party_id' => $vendorId]);
        }
    }

    /**
     * Whose side, leg by leg (owner, 2026-09-30): what the airline keeps on
     * each live ticket (its cost − its refund, blank = full refund), split by
     * the side chosen for THAT leg. Read before the tickets are cancelled.
     *
     * @return array{agent: float, ours: float}
     */
    protected function penaltyByLeg(ContractFlightPassenger $p, array $refunds, array $faults, ?array $onlyIds = null, array $agentShares = []): array
    {
        $agent = 0.0;
        $ours = 0.0;
        foreach ($p->ticketPurchases()->get() as $t) {
            if ($t->status === 'cancelled' || ($onlyIds !== null && ! in_array((int) $t->id, array_map('intval', $onlyIds), true))) {
                continue;
            }
            $was = (float) $t->amount;
            $r = array_key_exists($t->id, $refunds) && $refunds[$t->id] !== null && $refunds[$t->id] !== ''
                ? max(0.0, min((float) $refunds[$t->id], $was)) : $was;
            $pen = $was - $r;
            if ($pen <= 0) {
                continue;
            }
            if (($faults[$t->id] ?? null) === 'split') {
                $share = $agentShares[$t->id] ?? null;
                if (! $p->agent_id || $share === null || ! is_numeric($share) || (float) $share < 0 || round((float) $share, 2) > round($pen, 2)) {
                    throw ValidationException::withMessages(['penalty_agent_amounts' => 'Enter the agent share between zero and the penalty for each split ticket.']);
                }
                $agent += round((float) $share, 2);
                $ours += round($pen - (float) $share, 2);
            } elseif (($faults[$t->id] ?? null) === 'agent' && $p->agent_id) {
                $agent += $pen;
            } else {
                $ours += $pen;
            }
        }

        return ['agent' => round($agent, 2), 'ours' => round($ours, 2)];
    }

    /**
     * Mixed legs: the change was posted with the WHOLE penalty and fault
     * "agent" (Company Ledger reclass Dr 5171 / Cr 5110 = the full amount,
     * which is right: all of it was kept by the airline). Now trim the
     * agent's single line to HIS legs only, and record our legs' share
     * beside it — nothing to post for that part, the reclass already holds
     * it. Every figure reads from the two rows: agent part fault=agent,
     * ours fault=company.
     */
    protected function splitLegPenalty(ContractFlightPassengerChange $change, array $legs): void
    {
        if ($legs['agent'] <= 0 || $legs['ours'] <= 0 || ! ContractFlightPassengerChange::hasFaultColumns()) {
            return;
        }
        $change->update(['airline_penalty' => $legs['agent'], 'fault' => 'agent']);
        $this->reconcileChangeCharge($change->fresh());
        ContractFlightPassengerChange::create([
            'contract_flight_passenger_id' => $change->contract_flight_passenger_id,
            'kind' => $change->kind,
            'from_flight_id' => $change->from_flight_id,
            'to_flight_id' => $change->to_flight_id,
            'note' => 'Airline penalty on our-side legs ৳'.number_format($legs['ours'], 2).' — we bear it (same move as change #'.$change->id.')',
            'agent_id' => $change->agent_id,
            'fault' => 'company',
            'airline_penalty' => $legs['ours'],
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * First thing inside every money transaction on one passenger: lock his
     * row and make sure nobody changed him since this request loaded him
     * (a second staff member, or a double click on Confirm). Without it two
     * requests could both cancel the same tickets and both post the charge.
     * The lock is held to the end of the transaction; touching him makes the
     * NEXT request see that this one happened.
     */
    protected function lockPassenger(ContractFlightPassenger $p): void
    {
        $row = ContractFlightPassenger::whereKey($p->id)->lockForUpdate()->first(['id', 'updated_at', 'contract_flight_id', 'exception_flag']);
        // Timestamps are whole seconds, so also compare what every move
        // changes: his flight and his flag.
        if (! $row || ($p->updated_at && $row->updated_at && ! $row->updated_at->eq($p->updated_at))
            || (int) $row->contract_flight_id !== (int) $p->contract_flight_id
            || (string) $row->exception_flag !== (string) $p->exception_flag) {
            throw new StalePassengerException(
                ($p->passportHolder?->name ?: 'This passenger').' was just changed by someone else. Reload the page and check before doing it again.');
        }
        $p->touch();
    }

    protected function logRebookEvent(ContractFlightPassenger $p, string $type, ?string $code, string $label, ?string $note = null): void
    {
        $p->events()->create([
            'type' => $type, 'code' => $code, 'label' => $label, 'note' => $note,
            'occurred_at' => now(), 'created_by' => Auth::id(),
        ]);
    }
}
