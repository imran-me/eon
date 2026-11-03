<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\StalePassengerException;
use App\Models\ContractFlight;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use App\Models\Ticket;
use App\Models\TicketPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ContractFlightCancellationUndo;
use App\Services\ContractFlightCostSplit;
use App\Services\ContractFlightMessenger;
use App\Services\ContractFlightUndoRefused;
use App\Support\TravelCompany;
use App\Support\UserRoleSetting\Access;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsSaleJournal;
use App\Traits\RebooksContractFlightPassengers;
use App\Traits\ReconcilesContractFlightAgent;
use App\Traits\ReconcilesContractFlightChangeCharge;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * One passenger off a flight and onto another (owner, 2026-09-26).
 *
 * When the whole flight moves, nothing here is involved — the date lives on
 * the flight and everyone moves with it. This is for ONE person whose
 * journey changes: documents not ready, not travelling that day. The flow:
 *
 *   cancel()   — off the flight, into Cancelled. contract_flight_id goes
 *                null, the flag is OFF_FLIGHT_FLAG, the agent's package
 *                charge is voided (posted as zero, reversal visible), and —
 *                unless unticked — his tickets are cancelled through Ticketing:
 *                still listed in Ticket Orders, amount 0, with a note.
 *   reassign() — onto another open flight as a RESCHEDULE (same ticket) or a
 *                REISSUE (new ticket; any old live one is cancelled at 0),
 *                with an optional charge billed to the agent or borne by us.
 *                The same move also happens from the new flight's passenger
 *                builder (RebooksContractFlightPassengers holds the shared part).
 *   restore()  — Undo: straight back onto the flight he left, no charge.
 *
 * Every move is a ContractFlightPassengerChange row, and every charge the
 * agent bears is its own Party Statement row keyed on that change.
 *
 * Separate from ContractFlightPassengerController so the pipeline editor
 * stays exactly as it was; nothing outside Contract Flight calls this.
 */
class ContractFlightRebookController implements HasMiddleware
{
    use PostsPartyLedger, PostsSaleJournal;
    use RebooksContractFlightPassengers, ReconcilesContractFlightAgent, ReconcilesContractFlightChangeCharge;

    /** Flights a passenger can be put on: still ahead of us. */
    private const OPEN_STATUSES = ['open', 'boarding'];

    public static function middleware(): array
    {
        return [new Middleware('permission:edit contract flight')];
    }

    /** Take one passenger off his flight and into Cancelled. */
    public function cancel(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        if (! ContractFlightPassengerChange::installed()) {
            return $this->notInstalled();
        }
        $validated = $request->validate([
            'reason' => ['required', Rule::in(array_keys(ContractFlightPassengerChange::CANCEL_REASONS))],
            'note' => ['nullable', 'string', 'max:2000'],
            // "the old ticket will still be shown in the ticket purchases, but
            // with amount zero and a note as cancelled" — on unless the person
            // wants him to keep it for a later reschedule on the same ticket.
            'cancel_ticket' => ['nullable', 'boolean'],
            // Optional part the agent still pays for cancelling (2026-09-27):
            // posted to his statement + journal like every other charge.
            'charge' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Whose side caused it (2026-09-28) — decides who bears what the
            // airline keeps. refunds[ticket id] = what the airline gives back
            // on that ticket; blank = all of it.
            'fault' => ['nullable', Rule::in(array_keys(ContractFlightPassengerChange::FAULTS))],
            'refunds' => ['nullable', 'array'],
            'refunds.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Whose side, per leg (2026-09-30): faults[ticket id] = agent|company.
            'faults' => ['nullable', 'array'],
            'faults.*' => ['nullable', Rule::in([...array_keys(ContractFlightPassengerChange::FAULTS), 'split'])],
            'penalty_agent_amounts' => ['nullable', 'array'],
            'penalty_agent_amounts.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Refund one leg or all (2026-09-30): comma-separated ticket ids;
            // absent = every live ticket, as before.
            'only_tickets' => ['nullable', 'string', 'max:500'],
        ]);

        $p = $contractFlightPassenger;
        $onlyIds = $this->onlyTicketIds($request);
        if (! $p->contract_flight_id) {
            return $this->fail('This passenger is already off his flight — find him under Cancelled.');
        }
        $charge = round((float) ($validated['charge'] ?? 0), 2);
        if ($charge > 0 && ! $p->agent_id) {
            return $this->fail('This passenger has no agent to bill a cancellation charge to.');
        }

        try {
            DB::transaction(function () use ($p, $validated, $charge, $onlyIds) {
                $this->lockPassenger($p);
                $p->loadMissing(['contractFlight', 'passportHolder', 'agent']);
                $flight = $p->contractFlight;
                $previousFlag = $p->exception_flag;
                $reason = ContractFlightPassengerChange::CANCEL_REASONS[$validated['reason']];

                // His tickets first, while he still belongs to the flight, so the
                // note names it and the flight's cost is resynced without them.
                $cancelled = ['ids' => [], 'labels' => []];
                // Per-leg sides, read while the tickets are still live.
                $legs = ! empty($validated['faults']) && ($validated['cancel_ticket'] ?? true)
                    ? $this->penaltyByLeg($p, (array) ($validated['refunds'] ?? []), (array) $validated['faults'], $onlyIds, (array) ($validated['penalty_agent_amounts'] ?? [])) : null;
                if ($validated['cancel_ticket'] ?? true) {
                    $cancelled = $this->cancelPassengerTickets($p,
                        ($p->passportHolder?->name ?: 'passenger').' taken off '.($flight?->flight_number ?: 'his flight').' ('.$reason.')',
                        $onlyIds, $validated['refunds'] ?? []);
                }
                $penalty = (float) ($cancelled['penalty'] ?? 0);
                $fault = $legs ? ($legs['agent'] > 0 ? 'agent' : 'company') : ($validated['fault'] ?? null);
                if ($fault === 'agent' && ! $p->agent_id) {
                    $fault = 'company';
                }

                // Voided while he still belongs to the flight, so the reversal on
                // the agent's statement names the flight he was taken off.
                $p->update(['exception_flag' => ContractFlightPassenger::OFF_FLIGHT_FLAG]);
                $this->reconcileAgentLedger($p);

                $change = ContractFlightPassengerChange::create([
                    'contract_flight_passenger_id' => $p->id,
                    'kind' => 'cancelled',
                    'from_flight_id' => $flight?->id,
                    'reason' => $validated['reason'],
                    'note' => $validated['note'] ?? null,
                    'charge' => $charge > 0 ? $charge : null,
                    'bearer' => $charge > 0 ? 'agent' : null,
                    'agent_id' => $p->agent_id,
                    'previous_ticket_purchase_ids' => $cancelled['ids'] ?: null,
                    'previous_flag' => $previousFlag,
                    'created_by' => Auth::id(),
                ] + (ContractFlightPassengerChange::hasFaultColumns() ? [
                    'fault' => $fault ?: ($penalty > 0 ? 'company' : null),
                    'airline_penalty' => $penalty > 0 ? $penalty : null,
                ] : []));
                // Posted while he still belongs to the flight, so the entry names it.
                $this->reconcileChangeCharge($change);
                $flight?->loadMissing('creator');
                $this->postChangeCosts($change, $p, $flight);
                if ($legs) {
                    $this->splitLegPenalty($change, $legs);
                }

                $p->update(['contract_flight_id' => null]);
                $p->unsetRelation('contractFlight');

                $this->logRebookEvent($p, 'exception', ContractFlightPassenger::OFF_FLIGHT_FLAG, 'Taken off flight', $this->sentence([
                    'From '.($flight?->flight_number ?: 'his flight'),
                    $reason,
                    $cancelled['labels'] ? 'Ticket cancelled: '.implode(', ', $cancelled['labels']) : null,
                    $penalty > 0 ? 'Airline penalty ৳'.number_format($penalty, 2).' — '.($change->fault === 'agent' ? 'billed to the agent' : 'borne by us') : null,
                    $charge > 0 ? 'Cancellation charge ৳'.number_format($charge, 2).' billed to the agent' : null,
                    $validated['note'] ?? null,
                ]));

                $flight?->syncMoneyFromPassengers();
            });
        } catch (StalePassengerException $e) {
            return $this->fail($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => ($p->passportHolder?->name ?: 'Passenger').' is off the flight and waiting under Cancelled.',
        ]);
    }

    /**
     * Offload / Deported, from Re-assign (owner, 2026-10-07) — the third road
     * beside Reschedule and Refund. Nothing is edited or deleted; every line
     * is added, and the agent sees ONE line for the event.
     *
     * Immigration / boarding offload — item by item: every live ticket and
     * every paid service (training, consulting) with its cost, what the
     * airline or the responsible person refunds, the penalty left, who bears
     * it, and our charge on it.
     *   ticket refund   Ticketing's own cancel (vendor row + Dr AP / Cr 5110)
     *   service refund  provider row Cr + Dr AP / Cr 5150
     *   penalty         Dr 5171 / Cr 5110 (ticket) or 5150 (service)
     *   agent's line    his penalties + our charges: Dr AR / Cr 4181 / Cr 4183
     *
     * Deported — nothing is refunded (he flew). Instead the costs of sending
     * him home: the return ticket, charges at the airport he was deported
     * from, charges at ours — each Dr 5171 / Cr AP (vendor row when a payee
     * is named), each with its own bearer — and our deport charge (agent,
     * 4183). "Whose side" decides the package: ours = reversed.
     *
     * next (offload): "hold" = stays flagged on this flight; "close" = off the
     * flight, package reversed, Closed; "rebook" = the page follows up with
     * reassign(). A deportation stays on its flight: the case closes.
     */
    public function offload(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        if (! ContractFlightPassengerChange::installed()) {
            return $this->notInstalled();
        }
        $sides = [...array_keys(ContractFlightPassengerChange::FAULTS), 'split'];
        $validated = $request->validate([
            'event' => ['required', Rule::in(array_keys(ContractFlightPassengerChange::OFFLOAD_EVENTS))],
            'fault' => ['required', Rule::in($sides)],
            'next' => ['nullable', Rule::in(['hold', 'close', 'rebook'])],
            // Offload, per ticket: refund and side; per service: refund and side.
            'only_tickets' => ['nullable', 'string', 'max:500'],
            'refunds' => ['nullable', 'array'],
            'refunds.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'faults' => ['nullable', 'array'],
            'faults.*' => ['nullable', Rule::in($sides)],
            'penalty_agent_amounts' => ['nullable', 'array'],
            'penalty_agent_amounts.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'package_agent_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'services' => ['nullable', 'array', 'max:20'],
            'services.*.refund' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'services.*.side' => ['nullable', Rule::in($sides)],
            'services.*.agent_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Our charge per item: "t<ticket id>" / "s<cost id>".
            'charges' => ['nullable', 'array', 'max:30'],
            'charges.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Deported.
            'deport_from' => ['nullable', 'string', 'max:120'],
            'deport_costs' => ['nullable', 'array'],
            'deport_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'deport_costs.*.side' => ['nullable', Rule::in($sides)],
            'deport_costs.*.agent_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'deport_costs.*.vendor_id' => ['nullable', 'integer', 'exists:users,id'],
            'charge' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $p = $contractFlightPassenger;
        if (! $p->contract_flight_id) {
            return $this->fail('Only a passenger on a flight can be offloaded or deported.');
        }
        $event = $validated['event'];
        $deported = $event === 'deported';
        $next = $deported ? 'hold' : ($validated['next'] ?? 'hold');
        $fault = $validated['fault'];
        if ($fault === 'agent' && ! $p->agent_id) {
            return $this->fail('This passenger has no agent — choose "Our side".');
        }

        // Our charge: per item on an offload, one deport charge on a deportation.
        $itemCharges = $deported ? collect()
            : collect($validated['charges'] ?? [])->map(fn ($v) => round((float) $v, 2))->filter(fn ($v) => $v > 0);
        $charge = $deported ? round((float) ($validated['charge'] ?? 0), 2) : round((float) $itemCharges->sum(), 2);
        if ($charge > 0 && ! $p->agent_id) {
            return $this->fail('This passenger has no agent to bill our charge to.');
        }

        $deportCosts = [];
        if ($deported) {
            foreach (ContractFlightPassengerChange::DEPORT_COSTS as $key => $label) {
                $c = $validated['deport_costs'][$key] ?? [];
                $amount = round((float) ($c['amount'] ?? 0), 2);
                if ($amount > 0) {
                    // Owner, 2026-10-07: always a payee and a bearer — never guessed.
                    if (empty($c['vendor_id']) || empty($c['side'])) {
                        return $this->fail($label.': choose '.(empty($c['vendor_id']) ? 'who it is paid to' : 'who bears it').'.');
                    }
                    $deportCosts[] = ['key' => $key, 'label' => $label, 'amount' => $amount,
                        'side' => $c['side'], 'agent_amount' => ContractFlightCostSplit::agentAmount($amount, $c['side'], $c['agent_amount'] ?? null, $p->agent_id), 'vendor_id' => (int) $c['vendor_id']];
                }
            }
        }

        $onlyIds = $deported ? [] : $this->onlyTicketIds($request);
        $cancelTicket = ! $deported && $onlyIds !== null && $onlyIds;

        try {
            DB::transaction(function () use ($p, $validated, $event, $deported, $next, $fault, $charge, $onlyIds, $cancelTicket, $itemCharges, $deportCosts) {
                $this->lockPassenger($p);
                $p->loadMissing(['contractFlight.creator', 'passportHolder', 'agent']);
                $flight = $p->contractFlight;
                $previousFlag = $p->exception_flag;
                $label = ContractFlightPassengerChange::OFFLOAD_EVENTS[$event];
                $refundable = collect($this->refundableServices($p))->keyBy('id');

                // Tickets: sides per leg read while they are still live.
                $legs = ['agent' => 0.0, 'ours' => 0.0];
                $cancelled = ['ids' => [], 'labels' => [], 'penalty' => 0.0];
                if ($cancelTicket) {
                    $legs = $this->penaltyByLeg($p, (array) ($validated['refunds'] ?? []), (array) ($validated['faults'] ?? []), $onlyIds, (array) ($validated['penalty_agent_amounts'] ?? []));
                    $cancelled = $this->cancelPassengerTickets($p,
                        ($p->passportHolder?->name ?: 'passenger').' — '.$label.' on '.($flight?->flight_number ?: 'his flight'),
                        $onlyIds, $validated['refunds'] ?? []);
                }
                $ticketPenalty = (float) ($cancelled['penalty'] ?? 0);

                // Services: what the responsible person gives back; the rest is
                // the penalty, on the side chosen for it.
                $services = [];
                if (! $deported) {
                    foreach ((array) ($validated['services'] ?? []) as $costId => $in) {
                        $svc = $refundable->get((int) $costId);
                        if (! $svc) {
                            continue;
                        }
                        $refund = max(0.0, min(round((float) ($in['refund'] ?? 0), 2), $svc['left']));
                        $side = $in['side'] ?? $fault;
                        $pen = round($svc['left'] - $refund, 2);
                        $agentPart = ContractFlightCostSplit::agentAmount($pen, $side, $in['agent_amount'] ?? null, $p->agent_id);
                        $services[] = $svc + ['refund' => $refund, 'penalty' => $pen, 'side' => $side, 'agent_amount' => $agentPart];
                        $legs['agent'] += $agentPart;
                        $legs['ours'] += $pen - $agentPart;
                    }
                }
                foreach ($deportCosts as $c) {
                    $legs['agent'] += $c['agent_amount'];
                    $legs['ours'] += $c['amount'] - $c['agent_amount'];
                }
                $legs = ['agent' => round($legs['agent'], 2), 'ours' => round($legs['ours'], 2)];
                $penalty = round($legs['agent'] + $legs['ours'], 2);

                $flag = $deported ? ($fault === 'company' ? ContractFlightPassenger::DEPORTED_BORNE_FLAG : 'deported') : $event;
                $p->update(['exception_flag' => $flag]);
                $this->reconcileAgentLedger($p);

                $taka = fn ($v) => '৳'.number_format((float) $v, 2);
                $note = $this->sentence([
                    $deported && ! empty($validated['deport_from']) ? 'Deported from '.$validated['deport_from'] : null,
                    $deportCosts ? collect($deportCosts)->map(fn ($c) => $c['label'].' '.$taka($c['amount']).' ('.($c['side'] === 'split' ? 'agent '.$taka($c['agent_amount']).', company '.$taka($c['amount'] - $c['agent_amount']) : ($c['side'] === 'agent' ? 'agent' : 'we bear')).')')->implode('; ') : null,
                    $services ? 'Services: '.collect($services)->map(fn ($s) => $s['label'].' refund '.$taka($s['refund']).', penalty '.$taka($s['penalty']).' ('.($s['side'] === 'split' ? 'agent '.$taka($s['agent_amount']).', company '.$taka($s['penalty'] - $s['agent_amount']) : ($s['side'] === 'agent' ? 'agent' : 'we bear')).')')->implode('; ') : null,
                    $itemCharges->isNotEmpty() ? 'Our charge: '.$itemCharges->map(fn ($v, $k) => $this->itemLabel((string) $k, $refundable).' '.$taka($v))->implode(' + ') : null,
                    $deported && $charge > 0 ? 'Our deport charge '.$taka($charge) : null,
                    $validated['note'] ?? null,
                ]);

                $change = ContractFlightPassengerChange::create([
                    'contract_flight_passenger_id' => $p->id,
                    'kind' => 'flagged',
                    'from_flight_id' => $flight?->id,
                    'reason' => $event,
                    'note' => $note ?: null,
                    'charge' => $charge > 0 ? $charge : null,
                    'bearer' => $charge > 0 ? 'agent' : null,
                    'agent_id' => $p->agent_id,
                    'previous_ticket_purchase_ids' => $cancelled['ids'] ?: null,
                    'previous_flag' => $previousFlag,
                    'created_by' => Auth::id(),
                ] + (ContractFlightPassengerChange::hasFaultColumns() ? [
                    'fault' => $penalty > 0 ? ($legs['agent'] > 0 ? 'agent' : 'company') : ($fault === 'split' ? 'agent' : $fault),
                    'airline_penalty' => $penalty > 0 ? $penalty : null,
                ] : []));
                if ($deported && $fault === 'split') {
                    $package = $p->chargedPackage();
                    $agentPackage = ContractFlightCostSplit::agentAmount($package, 'split', $validated['package_agent_amount'] ?? null, $p->agent_id);
                    $reduction = round($package - $agentPackage, 2);
                    $change->update(['price_revision' => $reduction, 'note' => trim(($change->note ?? '').' | Package split: agent '.number_format($agentPackage, 2).', company '.number_format($reduction, 2))]);
                    if ($reduction > 0) {
                        $this->postAgentAdjustment((int) $p->agent_id, ContractFlightPassengerChange::PRICE_REVISION_TYPE, $change->id, -$reduction,
                            'Deportation package adjustment - '.($p->passportHolder?->name ?: 'passenger'),
                            ($flight?->flight_number ?: 'CF').'-'.$p->id.'-C'.$change->id, config('accounts.flight_sales_revenue'), TravelCompany::ID);
                        ContractFlightPassenger::forgetRevisions();
                        $p->unsetRelation('flightChanges');
                    }
                }
                // ONE agent line: what he bears + our charges (4183 = our profit).
                $this->reconcileChangeCharge($change);

                // Company Ledger, item by item.
                if ($ticketPenalty > 0 && ContractFlightPassengerChange::hasFaultColumns()) {
                    $change->airline_penalty = $ticketPenalty;
                    $this->postChangeCosts($change, $p, $flight, ['penalty']);
                    $change->airline_penalty = $penalty > 0 ? $penalty : null;
                }
                $this->postServiceRefunds($change, $p, $flight, $services);
                $this->postDeportCosts($change, $p, $flight, $deportCosts, $validated['deport_from'] ?? null);
                $this->splitLegPenalty($change, $legs);

                $this->logRebookEvent($p, 'exception', $flag, $label, $this->sentence([
                    'On '.($flight?->flight_number ?: 'his flight'),
                    ContractFlightPassengerChange::FAULTS[$fault] ?? 'Split between agent and company',
                    $cancelled['labels'] ? 'Ticket cancelled: '.implode(', ', $cancelled['labels']) : null,
                    $legs['agent'] > 0 ? $taka($legs['agent']).' billed to the agent' : null,
                    $legs['ours'] > 0 ? $taka($legs['ours']).' borne by us' : null,
                    $charge > 0 ? 'Our charge '.$taka($charge) : null,
                    $flag === ContractFlightPassenger::DEPORTED_BORNE_FLAG ? 'Package reversed — we bear it' : null,
                    $validated['note'] ?? null,
                ]));

                if ($next === 'close') {
                    // Will not fly: off the flight and closed, exactly as the
                    // Refund tab's "close the case" — the package is reversed.
                    $p->update(['exception_flag' => ContractFlightPassenger::OFF_FLIGHT_FLAG]);
                    $this->reconcileAgentLedger($p);
                    ContractFlightPassengerChange::create([
                        'contract_flight_passenger_id' => $p->id,
                        'kind' => 'cancelled',
                        'from_flight_id' => $flight?->id,
                        'reason' => 'will_not_fly',
                        'note' => $label.' — will not fly (same event as change #'.$change->id.')',
                        'agent_id' => $p->agent_id,
                        'previous_flag' => $flag,
                        'created_by' => Auth::id(),
                    ]);
                    $p->update(['contract_flight_id' => null]);
                    $p->unsetRelation('contractFlight');
                    $this->logRebookEvent($p, 'exception', ContractFlightPassenger::OFF_FLIGHT_FLAG, 'Taken off flight', $label.' — will not fly, case closed');
                } elseif ($cancelled['ids'] && ($back = $p->stepBackIfUnticketed())) {
                    $this->logRebookEvent($p, 'stage', $back, ContractFlightPassenger::stageLabel($back), 'Ticket cancelled — issue a new one');
                }

                $flight?->syncMoneyFromPassengers();
            });
        } catch (StalePassengerException $e) {
            return $this->fail($e->getMessage());
        }

        // Tell the agent, if the Messages rules say so. Never throws.
        ContractFlightMessenger::auto('offloaded', $p->fresh(), $validated['note'] ?? null);

        $name = $p->passportHolder?->name ?: 'Passenger';

        return response()->json([
            'success' => true,
            'message' => $name.' — '.ContractFlightPassengerChange::OFFLOAD_EVENTS[$event].' posted'
                .($next === 'close' ? '; off the flight, case closed.' : ($next === 'rebook' ? '.' : '; he stays on the flight, flagged.')),
        ]);
    }

    /** Provider ledger rows for a service refund; ticket-style, by addition. */
    private const SERVICE_REFUND_TYPE = 'contract_flight_service_refund';

    /** Payee rows for the costs of a deportation. */
    private const DEPORT_COST_TYPE = 'contract_flight_deport_cost';

    /**
     * His paid services that can still be refunded: amount − what earlier
     * offloads already got back. Never throws — the dialog calls it.
     */
    private function refundableServices(ContractFlightPassenger $p): array
    {
        try {
            return $p->serviceCosts()->with('vendor:id,name')->where('amount', '>', 0)->whereNotNull('vendor_id')->get()
                ->map(function ($c) {
                    $back = (float) Transaction::where('type', self::SERVICE_REFUND_TYPE)
                        ->where('invoice_id', $c->id)->where('user_id', $c->vendor_id)->sum('credit');

                    return ['id' => (int) $c->id, 'label' => $c->type_label, 'provider' => $c->vendor?->name,
                        'vendor_id' => (int) $c->vendor_id, 'amount' => (float) $c->amount,
                        'refunded' => round($back, 2), 'left' => round(max(0, (float) $c->amount - $back), 2)];
                })->filter(fn ($s) => $s['left'] > 0)->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Service refund: provider row Cr + Dr AP / Cr 5150; penalty Dr 5171 / Cr 5150. */
    private function postServiceRefunds(ContractFlightPassengerChange $change, ContractFlightPassenger $p, ?ContractFlight $flight, array $services): void
    {
        $name = $p->passportHolder?->name ?: 'passenger';
        $flightNo = $flight?->flight_number ?: 'contract flight';
        $ref = $flightNo.'-'.$p->id.'-C'.$change->id;
        $companyId = TravelCompany::ID;
        foreach ($services as $s) {
            if ($s['refund'] > 0) {
                $this->postPartyLedgerRow($s['vendor_id'], [
                    'type' => self::SERVICE_REFUND_TYPE,
                    'invoice_id' => $s['id'],
                    'account_id' => null,
                    'payment_date' => now(),
                    'reference_no' => $ref,
                    'debit' => 0,
                    'credit' => $s['refund'],
                    'remarks' => mb_substr($s['label'].' refund — '.$name.' (offloaded, '.$flightNo.')', 0, 250),
                ]);
                $this->postCompanyJournal(self::SERVICE_REFUND_TYPE, $s['id'], config('accounts.accounts_payable'), config('accounts.flight_purchase_cost'),
                    $s['refund'], $s['label'].' refund from '.($s['provider'] ?: 'provider').' — '.$name.' ('.$flightNo.')', $ref, $companyId);
            }
            if ($s['penalty'] > 0 && ContractFlightPassengerChange::hasFaultColumns()) {
                $this->postCompanyJournal(ContractFlightPassengerChange::PENALTY_COMPANY_SOURCE, $change->id,
                    config('accounts.cf_penalty_company'), config('accounts.flight_purchase_cost'), $s['penalty'],
                    $s['label'].' not refunded — '.$name.' ('.$flightNo.')', $ref, $companyId, $change->agent_id ? (int) $change->agent_id : null);
            }
        }
    }

    /** Deportation costs: Dr 5171 / Cr Supplier Payable, payee row when named. */
    private function postDeportCosts(ContractFlightPassengerChange $change, ContractFlightPassenger $p, ?ContractFlight $flight, array $costs, ?string $from): void
    {
        $name = $p->passportHolder?->name ?: 'passenger';
        $flightNo = $flight?->flight_number ?: 'contract flight';
        $ref = $flightNo.'-'.$p->id.'-C'.$change->id;
        $companyId = TravelCompany::ID;
        foreach ($costs as $c) {
            $what = $c['label'].' — '.$name.' deported'.($from ? ' from '.$from : '').' ('.$flightNo.')';
            if ($c['vendor_id']) {
                // Vendor convention: a bill is a debit on his statement.
                $this->postPartyLedgerRow($c['vendor_id'], [
                    'type' => self::DEPORT_COST_TYPE,
                    'invoice_id' => $change->id,
                    'account_id' => null,
                    'payment_date' => now(),
                    'reference_no' => $ref,
                    'debit' => $c['amount'],
                    'credit' => 0,
                    'remarks' => mb_substr($what, 0, 250),
                ]);
            }
            $this->postCompanyJournal(ContractFlightPassengerChange::PENALTY_COMPANY_SOURCE, $change->id,
                config('accounts.cf_penalty_company'), config('accounts.accounts_payable'), $c['amount'],
                $what, $ref, $companyId, $change->agent_id ? (int) $change->agent_id : null);
        }
    }

    /** Vendors a deportation cost can be owed to. Never throws. */
    private function deportPayees(): array
    {
        try {
            return User::whereHas('roles', fn ($r) => $r->where('name', 'vendor'))
                ->orderBy('name')->limit(400)->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function itemLabel(string $key, $services): string
    {
        $id = (int) substr($key, 1);
        if (str_starts_with($key, 's')) {
            return $services->get($id)['label'] ?? 'service';
        }
        $t = TicketPurchase::find($id);

        return 'ticket '.($t?->ticket_no ?: '#'.$id);
    }

    /** Put an off-flight passenger on another flight, as a reschedule or a reissue. */
    public function reassign(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        if (! ContractFlightPassengerChange::installed()) {
            return $this->notInstalled();
        }
        $validated = $request->validate([
            'kind' => ['required', Rule::in(ContractFlightPassengerChange::MOVE_KINDS)],
            // An existing flight, or "new" with new_flight[...] (2026-09-28:
            // "there should be new flight adding option too").
            'contract_flight_id' => ['required', 'string', 'max:20'],
            'new_flight' => ['nullable', 'array'],
            'new_flight.ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
            'new_flight.airline_flight_no' => ['nullable', 'string', 'max:50'],
            'new_flight.departure_at' => ['nullable', 'date'],
            'charge' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'bearer' => ['nullable', Rule::in(array_keys(ContractFlightPassengerChange::BEARERS))],
            // How much dearer the new ticket is than the old one, and who pays it.
            'fare_difference' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fare_bearer' => ['nullable', Rule::in(array_keys(ContractFlightPassengerChange::BEARERS))],
            'agent_fare_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Refund (Cancel) / New ticket (owner, 2026-09-30): what the airline
            // gives back on each old ticket (blank = everything), and whose
            // side caused the penalty it keeps.
            'refunds' => ['nullable', 'array'],
            'refunds.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fault' => ['nullable', Rule::in(array_keys(ContractFlightPassengerChange::FAULTS))],
            'faults' => ['nullable', 'array'],
            'faults.*' => ['nullable', Rule::in([...array_keys(ContractFlightPassengerChange::FAULTS), 'split'])],
            'penalty_agent_amounts' => ['nullable', 'array'],
            'penalty_agent_amounts.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'only_tickets' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
            // Only a later leg changes (owner, 2026-10-04): he stays on the
            // flight he is on (or was just refunded off) — the flight card
            // follows his FIRST leg.
            'stay_on_flight' => ['nullable', 'boolean'],
            // Reschedule / Reissue (owner, 2026-09-30): a charge per ticket,
            // each with its own bearer.
            'ticket_charges' => ['nullable', 'array', 'max:10'],
            'ticket_charges.*.ticket' => ['required', 'string', 'max:60'],
            'ticket_charges.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            // our own charge on that ticket (owner, 2026-10-13) — billed to the
            // agent with his penalty share, never owed to the seller
            'ticket_charges.*.fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'ticket_charges.*.bearer' => ['required', Rule::in([...array_keys(ContractFlightPassengerChange::BEARERS), 'split'])],
            'ticket_charges.*.agent_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'ticket_charges.*.ticket_id' => ['nullable', 'integer'],
            'ticket_charges.*.travel_date' => ['nullable', 'date'],
            'ticket_charges.*.ticket_number' => ['nullable', 'string', 'max:60'],
        ]);

        $p = $contractFlightPassenger;
        $charge = round((float) ($validated['charge'] ?? 0), 2);
        $bearer = $charge > 0 ? ($validated['bearer'] ?? null) : null;

        // Per-ticket charges: the agent's share rides on the move's own change
        // row (posted to his statement as before); our share, when both exist,
        // is recorded on a second row — company-borne charges post nothing.
        $split = null;
        $ticketCharges = collect($validated['ticket_charges'] ?? [])->filter(fn ($c) => (float) $c['amount'] > 0 || (float) ($c['fee'] ?? 0) > 0)->values();
        $feeSum = round((float) $ticketCharges->sum(fn ($c) => (float) ($c['fee'] ?? 0)), 2);
        if ($feeSum > 0 && ! $p->agent_id) {
            return $this->fail('This passenger has no agent to bill our charge to. Clear "Our charge", or set his agent first.');
        }
        if ($ticketCharges->isNotEmpty()) {
            $line = fn ($rows) => $rows->filter(fn ($c) => (float) $c['amount'] > 0)->map(fn ($c) => $c['ticket'].' ৳'.number_format((float) $c['amount'], 2))->implode(' + ');
            $allocations = $ticketCharges->flatMap(function ($c) use ($p) {
                $agent = ContractFlightCostSplit::agentAmount((float) $c['amount'], $c['bearer'], $c['agent_amount'] ?? null, $p->agent_id);

                return [array_merge($c, ['amount' => $agent, 'bearer' => 'agent']), array_merge($c, ['amount' => round((float) $c['amount'] - $agent, 2), 'bearer' => 'company'])];
            });
            $agentRows = $allocations->where('bearer', 'agent');
            $ourRows = $allocations->where('bearer', 'company');
            // Agent's ONE line = the penalties he bears + our charge on every ticket.
            $agentSum = round((float) $agentRows->sum(fn ($c) => (float) $c['amount']) + $feeSum, 2);
            $ourSum = round((float) $ourRows->sum(fn ($c) => (float) $c['amount']), 2);
            $charge = $agentSum > 0 ? $agentSum : $ourSum;
            $bearer = $agentSum > 0 ? 'agent' : 'company';
            $agentPen = $line($agentRows);
            $itemised = 'Charge per ticket — '.($agentSum > 0
                ? 'agent pays: '.trim($agentPen.($feeSum > 0 ? ($agentPen ? ' + ' : '').'our charge ৳'.number_format($feeSum, 2) : ''))
                : 'we bear: '.$line($ourRows));
            $validated['note'] = trim($itemised.(($validated['note'] ?? null) ? ' · '.$validated['note'] : ''));
            if ($agentSum > 0 && $ourSum > 0) {
                $split = ['charge' => $ourSum, 'note' => 'Charge per ticket — we bear: '.$line($ourRows)];
            }
        }

        // Re-assign also works straight from a flight (owner, 2026-09-30:
        // Flights & Passengers' Re-assign button, no cancel step first) — he
        // moves from the flight he is on, by the same completeRebooking().
        $onFlightId = $p->contract_flight_id ? (int) $p->contract_flight_id : null;
        if (! $onFlightId && ! $p->isOffFlight()) {
            return $this->fail('Only a passenger on a flight or under Cancelled can be re-assigned.');
        }
        if ($charge > 0 && ! $bearer) {
            return $this->fail('Say who bears the '.number_format($charge, 2).' charge — the agent or us.');
        }
        if ($bearer === 'agent' && ! $p->agent_id) {
            return $this->fail('This passenger has no agent to bill. Choose "We bear it", or set his agent first.');
        }
        $fare = round((float) ($validated['fare_difference'] ?? 0), 2);
        // Blank amount on a reissue = work it out from the new ticket later;
        // the bearer is kept either way.
        $fareBearer = $validated['fare_bearer'] ?? null;
        if ($fareBearer === 'agent' && ! $p->agent_id) {
            return $this->fail('This passenger has no agent to bill the fare difference to. Choose "We bear it".');
        }
        if (isset($validated['agent_fare_amount'])) {
            ContractFlightCostSplit::agentAmount($fare, 'split', $validated['agent_fare_amount'], $p->agent_id);
            if ($fareBearer !== 'agent' || ! ContractFlightPassengerChange::splitFareAvailable()) {
                return $this->fail('Choose the agent bearer for a split fare difference.');
            }
        }
        $isNew = $validated['contract_flight_id'] === 'new';
        // Staying: the target must be his own flight — the one he is on, or
        // the one the refund step just took him off. It may already have
        // departed (leg 2 changes while he is in transit), and it carries
        // his passport only because it is him, so the usual checks are skipped.
        $stay = (bool) ($validated['stay_on_flight'] ?? false);
        if ($stay) {
            $ownFlightId = $onFlightId ?: (int) $p->lastCancellation()->value('from_flight_id');
            if ($isNew || (int) $validated['contract_flight_id'] !== $ownFlightId || ! ContractFlight::find($ownFlightId)) {
                return $this->fail('He can only stay on the flight he is on.');
            }
        } elseif (! $isNew) {
            if ($error = $this->targetProblem($p, (int) $validated['contract_flight_id'])) {
                return $this->fail($error);
            }
        }

        $flight = null;
        // New travel date per ticket (reschedule only): only his own live
        // tickets, and only when the date actually changes.
        $dateMoves = collect($validated['kind'] === 'reschedule' ? ($validated['ticket_charges'] ?? []) : [])
            ->filter(fn ($c) => ! empty($c['ticket_id']) && ! empty($c['travel_date']))->values();

        $onlyTickets = $this->onlyTicketIds($request);
        try {
            DB::transaction(function () use ($p, &$flight, $isNew, $validated, $charge, $bearer, $fare, $fareBearer, $onFlightId, $split, $dateMoves, $ticketCharges, $onlyTickets, $stay) {
                $this->lockPassenger($p);
                // A new flight is created by the same rules Manage Flights uses
                // for its own: next GFnnn number, open, the passenger's agent as
                // the batch default. Its totals settle from its passengers.
                $flight = $isNew
                    ? $this->createFlightForRebook($p, $validated['new_flight'] ?? [])
                    : ContractFlight::with('ticket')->find((int) $validated['contract_flight_id']);
                $flight->loadMissing('ticket');
                $kind = $validated['kind'];
                $fromFlightId = $onFlightId ?: $p->lastCancellation()->value('from_flight_id');
                $liveBefore = $p->ticketPurchases()->where('ticket_purchases.status', '!=', 'cancelled')
                    ->pluck('ticket_purchases.id')->map(fn ($id) => (int) $id)->all();
                // A reissue cancels only the legs switched on; the rest stay live
                // and go with him. "only_tickets" sent empty = cancel nothing
                // (the refund already happened in step 1).
                if ($kind === 'reissue' && $onlyTickets !== null) {
                    $liveBefore = array_values(array_intersect($liveBefore, $onlyTickets));
                }

                $attributes = [
                    'contract_flight_id' => $flight->id,
                    // Rescheduled keeps the package charge standing (he still
                    // travels on our arrangement) and shows him on the
                    // Rescheduled / Transit card until someone clears it.
                    // Staying on his flight he is not rescheduled: he keeps the
                    // flag he had (the refund step's, if it took him off).
                    'exception_flag' => $stay
                        ? ($onFlightId ? $p->exception_flag : $p->lastCancellation()->value('previous_flag'))
                        : 'rescheduled',
                ];
                // tickets_required stays his own on a reissue too (owner,
                // 2026-10-13): the new flight's route does not decide it.
                $p->update($attributes);

                // Same ticket, new date: each ticket's leg takes its new travel date.
                $dateLog = [];
                foreach ($dateMoves as $m) {
                    if (! in_array((int) $m['ticket_id'], $liveBefore, true)) {
                        continue;
                    }
                    $tp = TicketPurchase::find((int) $m['ticket_id']);
                    // Time is optional (2026-09-30): "2026-10-04" or "2026-10-04 14:30".
                    // A date alone keeps the ticket's time of day; the leg and the
                    // ticket's departure_at move together (2026-10-06) so Ticket
                    // Orders shows the same day as the flight.
                    $new = trim(str_replace('T', ' ', (string) $m['travel_date']));
                    $newHasTime = (bool) preg_match('/\d:\d\d/', $new);
                    $new = substr($new, 0, $newHasTime ? 16 : 10);
                    $at = $tp?->travelAt();
                    $old = $at ? substr($at, 0, $newHasTime ? 16 : 10) : '';
                    if ($tp && $old !== $new && $tp->moveTravelDate($new)) {
                        $dateLog[] = $m['ticket'].' '.($old ?: '—').' → '.$new;
                    }
                }
                // Ticket number per leg: recorded on the move until the ticket
                // itself has a column for it.
                $numbers = collect($validated['ticket_charges'] ?? [])->filter(fn ($c) => ! empty($c['ticket_number']))
                    ->map(fn ($c) => $c['ticket'].': '.$c['ticket_number'])->values()->all();
                $note = trim(implode(' · ', array_filter([
                    $dateLog ? 'New dates: '.implode(', ', $dateLog) : null,
                    $numbers ? 'Ticket no: '.implode(', ', $numbers) : null,
                    $validated['note'] ?? null,
                ])));

                // Reissue: the old tickets are cancelled through Ticketing with the
                // airline's refund; the penalty it keeps goes to the Company Ledger
                // and, when it was his side, onto the agent's one change line.
                $fault = $kind === 'reissue' ? ($validated['fault'] ?? null) : null;
                if ($fault === 'agent' && ! $p->agent_id) {
                    $fault = 'company';
                }
                $refunds = $kind === 'reissue' ? array_map(fn ($v) => $v === null || $v === '' ? null : (float) $v, (array) ($validated['refunds'] ?? [])) : [];
                $legs = $kind === 'reissue' && ! empty($validated['faults']) ? $this->penaltyByLeg($p, $refunds, (array) $validated['faults'], $liveBefore, (array) ($validated['penalty_agent_amounts'] ?? [])) : null;
                if ($legs) {
                    $fault = $legs['agent'] > 0 ? 'agent' : 'company';
                }
                $change = $this->completeRebooking($p, $flight, $kind, $charge, $bearer, $note ?: null, $fromFlightId, $liveBefore, $fare, $fareBearer, $refunds, $fault, $stay, isset($validated['agent_fare_amount']) ? (float) $validated['agent_fare_amount'] : null);
                if ($legs) {
                    $this->splitLegPenalty($change, $legs);
                }

                // The vendor side of every per-ticket fee, whoever bears it.
                foreach ($ticketCharges as $tc) {
                    if (! empty($tc['ticket_id']) && in_array((int) $tc['ticket_id'], $liveBefore, true)) {
                        $this->postReissueFee($change, $p, $flight, (int) $tc['ticket_id'], (float) $tc['amount'], $tc['bearer']);
                    }
                }

                // Our share of a mixed per-ticket charge: recorded beside the move.
                if ($split) {
                    ContractFlightPassengerChange::create([
                        'contract_flight_passenger_id' => $p->id,
                        'kind' => $kind,
                        'from_flight_id' => $change->from_flight_id,
                        'to_flight_id' => $change->to_flight_id,
                        'note' => $split['note'].' (same move as change #'.$change->id.')',
                        'charge' => $split['charge'],
                        'bearer' => 'company',
                        'agent_id' => $p->agent_id,
                        'created_by' => Auth::id(),
                    ]);
                    $flight->syncMoneyFromPassengers();
                }

                // The flight he left loses his head count and money.
                if ($onFlightId) {
                    ContractFlight::find($onFlightId)?->syncMoneyFromPassengers();
                }
            });
        } catch (StalePassengerException $e) {
            return $this->fail($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => ($p->passportHolder?->name ?: 'Passenger').' is on '.$flight->flight_number.($isNew ? ' (new flight)' : '').'.'
                .($validated['kind'] === 'reissue' ? ' Issue his new ticket from that flight\'s passenger card.' : ''),
            // The page opens his card on this flight with Issue New Ticket ready.
            'flight_id' => $flight->id,
            'flight_number' => $flight->flight_number,
            'passenger_id' => $p->id,
        ]);
    }

    /** Undo a cancellation: straight back onto the flight he was taken off, no charge. */
    /** What undoing his cancellation would remove — shown before anything happens. Super admin only. */
    public function undoPreview(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        abort_unless(Access::allows('travels.contract-flight', 'e') && ContractFlightCancellationUndo::available(), 403);
        try {
            return response()->json(['success' => true] + app(ContractFlightCancellationUndo::class)->plan($contractFlightPassenger));
        } catch (ContractFlightUndoRefused $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function restore(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        if (! ContractFlightPassengerChange::installed()) {
            return $this->notInstalled();
        }
        $p = $contractFlightPassenger;
        // A wrong cancellation, undone completely (owner, 2026-10-26): its lines removed, his
        // tickets live again, back on his flight. Super admin only; others keep the undo below.
        if (ContractFlightCancellationUndo::available() && Access::allows('travels.contract-flight', 'e')) {
            try {
                $message = app(ContractFlightCancellationUndo::class)->run($p, $request->input('reason'));
            } catch (ContractFlightUndoRefused $e) {
                return $this->fail($e->getMessage());
            }

            return response()->json(['success' => true, 'message' => $message]);
        }
        if (! $p->isOffFlight()) {
            return $this->fail('This passenger is not under Cancelled.');
        }
        $last = $p->lastCancellation()->first();
        if (! $last?->from_flight_id) {
            return $this->fail('There is no record of the flight he left — use Re-assign.');
        }
        if ($error = $this->targetProblem($p, $last->from_flight_id, false)) {
            return $this->fail($error);
        }
        $flight = ContractFlight::find($last->from_flight_id);

        try {
            DB::transaction(function () use ($p, $flight, $last) {
                $this->lockPassenger($p);
                $p->update(['contract_flight_id' => $flight->id, 'exception_flag' => $last->previous_flag]);
                $p->setRelation('contractFlight', $flight);
                $this->reconcileAgentLedger($p);

                ContractFlightPassengerChange::create([
                    'contract_flight_passenger_id' => $p->id,
                    'kind' => 'restored',
                    'to_flight_id' => $flight->id,
                    'agent_id' => $p->agent_id,
                    'created_by' => Auth::id(),
                ]);
                $this->logRebookEvent($p, 'note', 'restored', 'Put back on flight', 'Back on '.$flight->flight_number.' — the cancellation was undone.');

                // His tickets may have been cancelled when he was taken off (they
                // stay cancelled). A stage at or past Ticket Issued would then
                // claim a ticket he no longer holds — step back to what his data
                // proves, as a re-booking does.
                if ($stageNow = $p->stepBackIfUnticketed()) {
                    $this->logRebookEvent($p, 'stage', $stageNow, ContractFlightPassenger::stageLabel($stageNow), 'Ticket cancelled when he was taken off — issue a new one');
                }

                $flight->syncMoneyFromPassengers();
            });
        } catch (StalePassengerException $e) {
            return $this->fail($e->getMessage());
        }

        return response()->json(['success' => true, 'message' => ($p->passportHolder?->name ?: 'Passenger').' is back on '.$flight->flight_number.'.']);
    }

    /**
     * Open flights this passenger can be put on, for the Re-assign picker —
     * soonest first, never one that already carries his passport.
     */
    public function flights(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        // Different Ticket rows can describe the same itinerary (for example,
        // DAC > IST > ECN with a different carrier on the second leg). Rebook
        // eligibility is based on the airport path, not the database row id.
        $routeKey = static function ($ticket): ?string {
            if (! $ticket) {
                return null;
            }
            $legs = $ticket->legsArray();
            if ($legs) {
                return implode('>', array_merge(
                    [(string) ($legs[0]['from_id'] ?? '')],
                    array_map(fn ($leg) => (string) ($leg['to_id'] ?? ''), $legs)
                ));
            }

            return implode('>', [(string) $ticket->from_airport_id, (string) $ticket->to_airport_id]);
        };

        $sourceTicketId = $request->integer('route_id') ?: $contractFlightPassenger->contractFlight?->ticket_id;
        $sourceRouteKey = $routeKey($sourceTicketId ? Ticket::find($sourceTicketId) : null);

        $taken = ContractFlightPassenger::where('passport_holder_id', $contractFlightPassenger->passport_holder_id)
            ->whereNotNull('contract_flight_id')->pluck('contract_flight_id');

        $flights = ContractFlight::with(['ticket.from_airport', 'ticket.to_airport', 'ticket.airline'])
            ->withCount('passengers')
            // Every flight, departed ones too (owner, 2026-10-08): a late or
            // mistaken entry must still be re-issued onto the flight he took.
            // Open flights first by date, then the rest newest first.
            ->where('status', '!=', 'cancelled')
            ->whereNotIn('id', $taken)
            ->orderByRaw("CASE WHEN status IN ('open','boarding') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status IN ('open','boarding') THEN departure_at IS NULL ELSE 0 END")
            ->orderByRaw("CASE WHEN status IN ('open','boarding') THEN departure_at END ASC")
            ->orderByDesc('departure_at')
            ->limit(2000)
            ->get()
            ->map(function (ContractFlight $f) use ($routeKey) {
                try {
                    $legs = (int) ($f->ticket?->legCount() ?: 1);
                } catch (\Throwable) {
                    $legs = 1;
                }

                return [
                    'id' => $f->id,
                    'flight_number' => $f->flight_number,
                    'airline' => trim(($f->airline?->name ?? '').' '.($f->airline_flight_no ?? '')) ?: null,
                    'route' => $f->route,
                    // Reschedule / Reissue keeps his route: the dialog lists only these.
                    'ticket_id' => $f->ticket_id,
                    'route_key' => $routeKey($f->ticket),
                    'departure_at' => $f->departure_at?->toIso8601String(),
                    'departure_label' => $f->departure_at?->format('d M Y, h:i A'),
                    'passengers' => (int) $f->passengers_count,
                    'legs' => max(1, $legs),
                    'status' => $f->status,
                ];
            });

        // Routes for "+ New flight" — Manage Tickets' routes, as the flight
        // form offers them.
        $routes = [];
        try {
            $routes = Ticket::with(['from_airport', 'to_airport', 'airline'])
                ->where('status', 1)->orderBy('id', 'desc')->limit(300)->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'label' => trim(($t->from_airport?->code ?? '?').' → '.($t->to_airport?->code ?? '?')
                        .($t->airline?->name ? ' · '.$t->airline->name : '')),
                ])->values();
        } catch (\Throwable) {
            $routes = [];
        }

        return response()->json([
            'success' => true,
            'flights' => $flights,
            'source_route_key' => $sourceRouteKey,
            'routes' => $routes,
            'old_ticket_cost' => (float) $contractFlightPassenger->ticketPurchases()->sum('ticket_purchases.amount'),
            'faults' => ContractFlightPassengerChange::hasFaultColumns(),
            // Offload / Deported (2026-10-07): his paid services, each
            // refundable once, and who a deportation cost can be paid to.
            'services' => $this->refundableServices($contractFlightPassenger),
            'payees' => $this->deportPayees(),
        ]);
    }

    /** A new open flight for a re-booking, from the Re-assign dialog's "+ New flight". */
    private function createFlightForRebook(ContractFlightPassenger $p, array $data): ContractFlight
    {
        return ContractFlight::create([
            'flight_number' => ContractFlight::nextFlightNumber(),
            'flight_category_id' => $p->flight_category_id,
            'handling_type' => 'manpower_wise',
            'ticket_class' => $p->ticket_class ?: 'economy',
            'ticket_id' => $data['ticket_id'] ?? null,
            'airline_flight_no' => $data['airline_flight_no'] ?? null,
            'departure_at' => $data['departure_at'] ?? null,
            'total_seats' => 0,
            'seats_sold' => 0,
            'cost_price' => 0,
            'revenue' => 0,
            'agent_id' => $p->agent_id,
            'ticket_cost_per_pax' => 0,
            'manpower_cost_per_pax' => 0,
            'boarding_cost_per_pax' => 0,
            'immigration_cost_per_pax' => 0,
            'sale_price_per_pax' => 0,
            'status' => 'open',
            'notes' => 'Created from Re-assign for '.($p->passportHolder?->name ?: 'a passenger').'.',
            'created_by' => Auth::id(),
        ]);
    }

    /** Correct a change's charge or who bears it — the only way to undo a wrong one. */
    public function updateCharge(Request $request, string $role, ContractFlightPassengerChange $contractFlightPassengerChange)
    {
        $change = $contractFlightPassengerChange;
        // Whose side the fare difference is (2026-09-30), from the flight
        // card: only while it is still pending — once worked out and posted,
        // it is history, like every other posting.
        if ($request->has('fare_bearer')) {
            $fareInput = $request->validate(['fare_bearer' => ['required', Rule::in(array_keys(ContractFlightPassengerChange::BEARERS))], 'agent_fare_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999']]);
            $fb = $fareInput['fare_bearer'];
            if ($change->kind !== 'reissue' || $change->fare_difference !== null) {
                return $this->fail('The fare difference is already worked out and posted.');
            }
            if ($fb === 'agent' && ! ($change->agent_id ?: $change->passenger?->agent_id)) {
                return $this->fail('This passenger has no agent to bill.');
            }
            if (isset($fareInput['agent_fare_amount']) && (! ContractFlightPassengerChange::splitFareAvailable() || $fb !== 'agent')) {
                return $this->fail('Choose the agent bearer for a split fare difference.');
            }
            DB::transaction(function () use ($change, $fb, $fareInput) {
                $locked = ContractFlightPassengerChange::whereKey($change->id)->lockForUpdate()->firstOrFail();
                if ($locked->fare_difference !== null) {
                    throw ValidationException::withMessages(['fare_bearer' => 'The fare difference was already posted. Reload the passenger.']);
                }
                $locked->update(['fare_bearer' => $fb] + (ContractFlightPassengerChange::splitFareAvailable() ? ['agent_fare_amount' => $fareInput['agent_fare_amount'] ?? null] : []));
            });

            return response()->json(['success' => true, 'message' => 'Fare difference: '.ContractFlightPassengerChange::BEARERS[$fb].'.']);
        }
        $validated = $request->validate([
            'charge' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'bearer' => ['nullable', Rule::in(array_keys(ContractFlightPassengerChange::BEARERS))],
        ]);
        if (! $change->isChargeable()) {
            return $this->fail('Only a move onto another flight carries a charge.');
        }
        $charge = round((float) ($validated['charge'] ?? 0), 2);
        $bearer = $charge > 0 ? ($validated['bearer'] ?? null) : null;
        if ($charge > 0 && ! $bearer) {
            return $this->fail('Say who bears the charge — the agent or us.');
        }
        $change->loadMissing('passenger');
        $agentId = $change->agent_id ?: $change->passenger?->agent_id;
        if ($bearer === 'agent' && ! $agentId) {
            return $this->fail('This passenger has no agent to bill.');
        }

        // A per-ticket reissue fee is also owed to the ticket's vendor, ticket
        // by ticket — its amount is corrected with a new Re-Issue, not here.
        // Who pays it can still be corrected.
        if (str_contains((string) $change->note, 'Charge per ticket') && abs($charge - (float) $change->charge) > 0.005) {
            return $this->fail('This is a per-ticket reissue fee, also owed to the ticket vendor. Only "who pays" can be corrected here — for a different amount, add a Re-Issue on that ticket.');
        }

        // Our charge is always the agent's (owner, 2026-10-13). On a per-ticket
        // charge that carries one, "we bear it" moves only the airline's part:
        // it goes to a we-bear row (the shape Re-assign records), this row keeps
        // our charge, billed as before. The correction then posts -penalty to 4181.
        $ours = str_contains((string) $change->note, 'Charge per ticket') && preg_match('/our charge ৳([\d,]+(?:\.\d+)?)/u', (string) $change->note, $om)
            ? min((float) $change->charge, round((float) str_replace(',', '', $om[1]), 2)) : 0.0;
        if ($ours > 0 && $bearer !== 'agent' && $change->bearer === 'agent' && $agentId) {
            $moved = round((float) $change->charge - $ours, 2);
            DB::transaction(function () use ($change, $ours, $agentId) {
                $before = (float) $change->charge;
                $passed = round($before - $ours, 2);
                $segs = explode(' · ', (string) $change->note);
                $items = '';
                foreach ($segs as $i => $seg) {
                    if (str_starts_with($seg, 'Charge per ticket')) {
                        $items = implode(' + ', array_filter(array_map('trim', explode(' + ', trim(substr($seg, strpos($seg, ':') + 1)))),
                            fn ($x) => $x !== '' && ! str_starts_with($x, 'our charge')));
                        $segs[$i] = 'Charge per ticket — agent pays: our charge ৳'.number_format($ours, 2);
                    }
                }
                if ($passed > 0) {
                    ContractFlightPassengerChange::create([
                        'contract_flight_passenger_id' => $change->contract_flight_passenger_id,
                        'kind' => $change->kind,
                        'from_flight_id' => $change->from_flight_id,
                        'to_flight_id' => $change->to_flight_id,
                        'note' => 'Charge per ticket — we bear: '.($items ?: '৳'.number_format($passed, 2)).' (same move as change #'.$change->id.')',
                        'charge' => $passed,
                        'bearer' => 'company',
                        'agent_id' => $agentId,
                        'created_by' => Auth::id(),
                    ]);
                }
                $change->update(['charge' => $ours, 'note' => implode(' · ', $segs), 'agent_id' => $agentId]);
                // Agent-facing words: no split (the agent sees one penalty figure).
                $this->correctChangeCharge($change->fresh(), 'penalty now borne by us');
                if ($change->passenger) {
                    $this->logRebookEvent($change->passenger, 'finance', 'change_charge', 'Change charge updated', $this->sentence([
                        ContractFlightPassengerChange::KINDS[$change->kind],
                        'Airline penalty ৳'.number_format($passed, 2).' — we bear it',
                        'our charge ৳'.number_format($ours, 2).' stays billed to the agent',
                    ]));
                }
            });

            return response()->json([
                'success' => true,
                'message' => $moved > 0
                    ? 'Airline penalty moved to us. Our charge ৳'.number_format($ours, 2).' stays billed to the agent.'
                    : 'Nothing moved — this charge is only our charge, which always stays billed to the agent.',
                'changes' => $change->passenger ? $change->passenger->flightChanges()->with(['fromFlight:id,flight_number', 'toFlight:id,flight_number', 'creator:id,name'])->get()->map->toCard()->values() : [],
            ]);
        }

        DB::transaction(function () use ($change, $charge, $bearer, $agentId) {
            $before = $change->isBilled() ? (float) $change->charge : 0.0;
            // A per-ticket note names who pays ("Charge per ticket — we bear: …"): it follows the flip,
            // or the Flight changes panel goes on saying "we bear" beside "Bill the agent" (2026-10-19).
            $note = $bearer ? preg_replace('/Charge per ticket — (?:we bear|agent pays):/u',
                'Charge per ticket — '.($bearer === 'agent' ? 'agent pays' : 'we bear').':', (string) $change->note) : $change->note;
            $change->update(['charge' => $charge > 0 ? $charge : null, 'bearer' => $bearer, 'agent_id' => $agentId, 'note' => $note]);
            // By addition (owner, 2026-10-04): the original line and its
            // journal stay; one row for the difference is added beside them.
            $this->correctChangeCharge($change->fresh(), 'charge corrected from ৳'.number_format($before, 2).' to '.($bearer === 'agent' ? '৳'.number_format($charge, 2) : 'we bear it'));
            if ($change->passenger) {
                $this->logRebookEvent($change->passenger, 'finance', 'change_charge', 'Change charge updated', $this->sentence([
                    ContractFlightPassengerChange::KINDS[$change->kind],
                    $charge > 0 ? '৳'.number_format($charge, 2).' — '.ContractFlightPassengerChange::BEARERS[$bearer] : 'No charge',
                    $before > 0 ? 'was ৳'.number_format($before, 2).' billed' : null,
                ]));
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Charge updated.',
            'changes' => $change->passenger ? $change->passenger->flightChanges()->with(['fromFlight:id,flight_number', 'toFlight:id,flight_number', 'creator:id,name'])->get()->map->toCard()->values() : [],
        ]);
    }

    /**
     * Why $flightId cannot take this passenger, or null when it can: it must
     * exist (not deleted), still be ahead of us, and not already carry his
     * passport — the table allows one row per holder per flight.
     */
    private function targetProblem(ContractFlightPassenger $p, $flightId, bool $mustBeOpen = true): ?string
    {
        $flight = ContractFlight::find($flightId);
        if (! $flight) {
            return 'That flight no longer exists.';
        }
        if ($mustBeOpen && $flight->status === 'cancelled') {
            return $flight->flight_number.' is '.$flight->status.' — choose another flight.';
        }
        if (ContractFlightPassenger::where('contract_flight_id', $flight->id)->where('passport_holder_id', $p->passport_holder_id)->exists()) {
            return 'This passport is already on '.$flight->flight_number.'.';
        }

        return null;
    }

    /** only_tickets = "63,64" → [63, 64]; sent but empty → []; absent → null (all). */
    private function onlyTicketIds(Request $request): ?array
    {
        if (! $request->has('only_tickets')) {
            return null;
        }

        return array_values(array_filter(array_map('intval', explode(',', (string) $request->input('only_tickets', '')))));
    }

    private function sentence(array $parts): string
    {
        return implode(' · ', array_filter($parts, fn ($v) => $v !== null && $v !== ''));
    }

    private function fail(string $message)
    {
        return response()->json(['success' => false, 'message' => $message], 422);
    }

    private function notInstalled()
    {
        return $this->fail('Cancel / re-assign is not installed on this server yet — the contract flight changes migration has not run.');
    }
}
