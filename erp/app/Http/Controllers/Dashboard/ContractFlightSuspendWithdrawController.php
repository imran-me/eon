<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\StalePassengerException;
use App\Models\Account;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightSuspendWithdrawal as Withdrawal;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\User;
use App\Services\ContractFlightCostSplit;
use App\Support\TravelCompany;
use App\Traits\PostsPartyLedger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Suspend withdraw (owner, 2026-11-02) — Re-assign dialog, its own card and its own postings.
 *
 *   Vendor ledger     the vendor who lifted the suspension: his bill, debit = cost
 *   We bear           Dr 5173 Change fees (company borne) / Cr 2111 Supplier Payable    (cost)
 *                     + our charge, if any: agent row Dr, Dr 1311 / Cr 4183
 *   Agent bears       agent's ONE row Dr = cost + our charge,
 *                     Dr 1311 total / Cr 2111 cost / Cr 4183 our charge
 *
 * The passenger stays on his flight and his ticket stays live; nothing is flagged or cancelled.
 * Every row is added — nothing is edited or removed.
 */
class ContractFlightSuspendWithdrawController implements HasMiddleware
{
    use PostsPartyLedger;

    public static function middleware(): array
    {
        return [new Middleware('permission:edit contract flight')];
    }

    public function store(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        if (! Withdrawal::available()) {
            return response()->json(['success' => false, 'message' => 'Suspend withdraw is not installed on this server yet.'], 422);
        }
        $v = $request->validate([
            'ticket_purchase_id' => ['nullable', 'integer'],
            'vendor_id' => ['required', 'integer', 'exists:users,id'],
            'cost' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'bearer' => ['required', Rule::in(array_keys(Withdrawal::BEARERS))],
            'agent_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'our_charge' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'vendor_id.required' => 'Choose the vendor who lifted the suspension.',
            'cost.required' => 'Enter the vendor\'s cost.',
            'cost.min' => 'Enter the vendor\'s cost.',
            'bearer.required' => 'Choose who bears the cost.',
        ]);

        $p = $contractFlightPassenger;
        $cost = round((float) $v['cost'], 2);
        $charge = round((float) ($v['our_charge'] ?? 0), 2);
        $agentCost = ContractFlightCostSplit::agentAmount($cost, $v['bearer'], $v['agent_amount'] ?? null, $p->agent_id);
        $ourCost = round($cost - $agentCost, 2);
        $agentBears = $ourCost <= 0;
        $agentTotal = round($agentCost + $charge, 2);

        if (! $p->contract_flight_id) {
            return $this->fail('Only a passenger on a flight can have a suspension withdrawn here.');
        }
        if ($agentTotal > 0 && ! $p->agent_id) {
            return $this->fail('This passenger has no agent to bill — choose "We bear" and no charge.');
        }
        if (! User::whereKey($v['vendor_id'])->whereHas('roles', fn ($r) => $r->where('name', 'vendor'))->exists()) {
            return $this->fail('The suspend-withdraw vendor must be a vendor.');
        }
        $ticket = null;
        if (! empty($v['ticket_purchase_id'])) {
            $ticket = $p->ticketPurchases()->where('ticket_purchases.id', $v['ticket_purchase_id'])->first();
            if (! $ticket) {
                return $this->fail('That ticket is not his.');
            }
        }

        try {
            $w = DB::transaction(function () use ($p, $v, $ticket, $cost, $charge, $agentTotal, $ourCost) {
                $locked = ContractFlightPassenger::whereKey($p->id)->lockForUpdate()->first(['id', 'updated_at', 'contract_flight_id']);
                if (! $locked || (int) $locked->contract_flight_id !== (int) $p->contract_flight_id) {
                    throw new StalePassengerException('This passenger was just changed by someone else. Reload the page and check before doing it again.');
                }
                $p->loadMissing(['contractFlight', 'passportHolder', 'agent']);
                $flight = $p->contractFlight;

                $w = Withdrawal::create([
                    'contract_flight_passenger_id' => $p->id,
                    'contract_flight_id' => $flight?->id,
                    'ticket_purchase_id' => $ticket?->id,
                    'vendor_id' => (int) $v['vendor_id'],
                    'cost' => $cost,
                    'bearer' => $v['bearer'],
                    'our_charge' => $charge,
                    'agent_id' => $p->agent_id,
                    'agent_total' => $agentTotal,
                    'note' => $v['note'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                $name = $p->passportHolder?->name ?: 'passenger';
                $flightNo = $flight?->flight_number ?: 'contract flight';
                $what = 'Suspend withdraw'
                    .($ticket?->ticket_no ? ' on '.$ticket->ticket_no : '').' — '.$name.' ('.$flightNo.')';
                $ref = $flightNo.'-'.$p->id.'-SW'.$w->id;
                $companyId = TravelCompany::ID;

                // 1. The vendor's bill (vendor convention: a bill is a debit on his statement).
                $this->postPartyLedgerRow((int) $v['vendor_id'], [
                    'company_id' => $companyId,
                    'type' => Withdrawal::VENDOR_TYPE,
                    'invoice_id' => $w->id,
                    'account_id' => null,
                    'payment_date' => now(),
                    'reference_no' => $ref,
                    'debit' => $cost,
                    'credit' => 0,
                    'remarks' => mb_substr($what, 0, 250),
                ]);

                // 2. The agent's ONE line: cost he bears + our charge.
                if ($agentTotal > 0) {
                    $this->postPartyLedgerRow((int) $p->agent_id, [
                        'company_id' => $companyId,
                        'type' => Withdrawal::AGENT_TYPE,
                        'invoice_id' => $w->id,
                        'account_id' => null,
                        'payment_date' => now(),
                        'reference_no' => $ref,
                        'debit' => $agentTotal,
                        'credit' => 0,
                        // ONE figure for the agent (owner, 2026-10-13) — no cost / charge breakdown on his line.
                        'remarks' => mb_substr($what, 0, 250),
                    ]);
                }

                // 3. One balanced journal for the whole event.
                $lines = [];
                if ($agentTotal > 0) {
                    $lines[] = [config('accounts.accounts_receivable'), $agentTotal, 0, 'agent', (int) $p->agent_id];
                }
                if ($ourCost > 0) {
                    $lines[] = [config('accounts.cf_change_company'), $ourCost, 0, null, null];
                }
                $lines[] = [config('accounts.accounts_payable'), 0, $cost, 'vendor', (int) $v['vendor_id']];
                if ($charge > 0) {
                    $lines[] = [config('accounts.cf_change_recovered'), 0, $charge, null, null];
                }
                $this->postJournal($w, $lines, $what, $ref, $companyId, $p->agent_id ? (int) $p->agent_id : null);
                // The flight's revenue / cost are summed from its passengers — this one just changed.
                $flight?->syncMoneyFromPassengers();

                return $w;
            });
        } catch (StalePassengerException $e) {
            return $this->fail($e->getMessage());
        }

        $name = $p->passportHolder?->name ?: 'Passenger';

        return response()->json([
            'success' => true,
            'message' => $name.' — suspend withdraw posted: ৳'.number_format($cost, 2).' to the vendor'
                .($agentBears ? '' : ', borne by us')
                .($w->agent_total > 0 ? '; ৳'.number_format($w->agent_total, 2).' billed to the agent' : '').'.',
        ]);
    }

    /** @param array<int, array{0:?string,1:float,2:float,3:?string,4:?int}> $lines code, debit, credit, party type, party id */
    private function postJournal(Withdrawal $w, array $lines, string $what, string $ref, int $companyId, ?int $agentId): void
    {
        $ids = Account::whereIn('code', array_filter(array_column($lines, 0)))->pluck('id', 'code');
        foreach ($lines as $l) {
            if (! $ids->get($l[0])) {
                return; // a head is not set up on this server — ledger rows still stand, as elsewhere
            }
        }
        $entry = JournalEntry::create([
            'company_id' => $companyId,
            'created_by' => Auth::id(),
            'date' => now()->toDateString(),
            'entry_time' => now()->format('H:i:s'),
            'reference' => mb_substr($ref, 0, 190),
            'source' => Withdrawal::VENDOR_TYPE,
            'source_id' => $w->id,
            'description' => mb_substr($what, 0, 250),
            'beneficiary' => $agentId ? User::whereKey($agentId)->value('name') : null,
            'beneficiary_party_id' => $agentId,
        ]);
        foreach ($lines as [$code, $dr, $cr, $ptype, $pid]) {
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $ids->get($code),
                'debit' => round($dr, 2), 'credit' => round($cr, 2), 'note' => mb_substr($what, 0, 250),
                'party_type' => $ptype, 'party_id' => $pid]);
        }
    }

    private function fail(string $message)
    {
        return response()->json(['success' => false, 'message' => $message], 422);
    }
}
