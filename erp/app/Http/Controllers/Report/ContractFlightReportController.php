<?php

namespace App\Http\Controllers\Report;

use App\Models\Company;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use App\Models\ContractFlightSuspendWithdrawal;
use App\Models\TicketLeg;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;

class ContractFlightReportController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract flight|view all contract flight'),
        ];
    }

    /**
     * One row per agent: how much of their contract-flight commission has
     * been invoiced (posted), paid, and is still due — read straight off the
     * Party Statement ledger, the same figures that screen itself would show
     * for that agent, laid out like the existing Payroll "Overall" report.
     */
    public function agentOverall(Request $request)
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $invoiced = Transaction::query()
            // The package, less any reversal of it (a cancel adds a credit row
            // beside the charge, 2026-09-28), plus penalties / fare differences
            // billed for a change.
            // + change charges (and their corrections) and reissue billing.
            ->whereIn('type', [ContractFlightPassenger::AGENT_LEDGER_TYPE, ContractFlightPassenger::AGENT_REVERSAL_TYPE,
                ContractFlightPassengerChange::PENALTY_LEDGER_TYPE, ContractFlightPassengerChange::FARE_LEDGER_TYPE,
                ContractFlightPassengerChange::LEDGER_TYPE, ContractFlightPassenger::REISSUE_LEDGER_TYPE,
                ContractFlightPassengerChange::PRICE_REVISION_TYPE,
                ContractFlightSuspendWithdrawal::AGENT_TYPE])
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
            ->selectRaw('user_id, SUM(debit) - SUM(credit) as invoiced')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // A payment against an agent is recorded on the Party Statement screen
        // generically (type 'party_payment'), not under this feature's own
        // type — so "paid" reads every credit on that agent's ledger, not
        // just rows this feature itself created. Accurate as long as an
        // agent's ledger holds nothing but contract-flight commission, which
        // is the only thing that has ever posted a Transaction for an agent.
        $paid = Transaction::query()
            ->whereIn('user_id', $invoiced->keys())
            // A package reversal is a credit but not money received — it is
            // already netted out of "invoiced" above.
            ->whereNotIn('type', ContractFlightPassengerChange::NOT_RECEIPT_TYPES)
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
            ->selectRaw('user_id, SUM(credit) as paid')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $passengerCounts = ContractFlightPassenger::whereIn('agent_id', $invoiced->keys())
            ->selectRaw('agent_id, COUNT(*) as passenger_count')
            ->groupBy('agent_id')
            ->pluck('passenger_count', 'agent_id');

        $agents = User::whereIn('id', $invoiced->keys())->orderBy('name')->get(['id', 'name']);

        $rows = $agents->map(function (User $agent) use ($invoiced, $paid, $passengerCounts) {
            $invoicedAmount = (float) ($invoiced->get($agent->id)->invoiced ?? 0);
            $paidAmount = (float) ($paid->get($agent->id)->paid ?? 0);

            return (object) [
                'agent_name' => $agent->name,
                'passenger_count' => (int) ($passengerCounts->get($agent->id) ?? 0),
                'invoiced' => $invoicedAmount,
                'paid' => $paidAmount,
                'due' => max(0, $invoicedAmount - $paidAmount),
            ];
        })->sortByDesc('due')->values();

        return view('report.contract-flight.agent-overall', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'company' => Company::where('name', 'like', '%GROUP%')->first() ?? Company::first(),
            'generatedBy' => Auth::user()?->name,
        ]);
    }

    /**
     * Cost per route segment (leg), even though a multi-leg journey is sold
     * and ticketed as one combined ticket — answers "how much did DAC→IST
     * cost us across every ticket" separately from "IST→ECN".
     */
    public function legCost(Request $request)
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $rows = TicketLeg::query()
            ->whereNotNull('cost')
            ->when($from, fn ($q) => $q->whereDate('travel_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('travel_date', '<=', $to))
            ->selectRaw('from_location, to_location, COUNT(*) as leg_count, SUM(cost) as total_cost, AVG(cost) as avg_cost')
            ->groupBy('from_location', 'to_location')
            ->orderByDesc('total_cost')
            ->get();

        return view('report.contract-flight.leg-cost', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'company' => Company::where('name', 'like', '%GROUP%')->first() ?? Company::first(),
            'generatedBy' => Auth::user()?->name,
        ]);
    }
}
