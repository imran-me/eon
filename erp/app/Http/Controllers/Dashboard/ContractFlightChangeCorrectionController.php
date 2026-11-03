<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\ContractFlightPassengerChange;
use App\Services\ContractFlightChangeCorrection;
use App\Support\UserRoleSetting\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * Passenger edit modal → Flight changes → Correct (owner, 2026-10-19: "a way so I can edit those
 * if mistakes happen — amounts, which ledger to hit, who bears"). Super admin only — checked in
 * every action, not only by hiding the button. The money is ContractFlightChangeCorrection's:
 * nothing is edited or deleted, one row per difference is added beside the originals.
 */
class ContractFlightChangeCorrectionController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:edit contract flight')];
    }

    /** The entry as the dialog shows it. */
    public function show(string $role, ContractFlightPassengerChange $contractFlightPassengerChange): JsonResponse
    {
        if ($deny = $this->guard()) {
            return $deny;
        }

        return response()->json(['success' => true, 'entry' => (new ContractFlightChangeCorrection)->form($contractFlightPassengerChange)]);
    }

    /** What the correction would post — nothing written. */
    public function preview(Request $request, string $role, ContractFlightPassengerChange $contractFlightPassengerChange): JsonResponse
    {
        if ($deny = $this->guard()) {
            return $deny;
        }
        $in = $this->input($request, false);
        try {
            $plan = (new ContractFlightChangeCorrection)->plan($contractFlightPassengerChange, $in);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'changed' => $plan['changed'], 'preview' => $plan['preview'],
            'rows_change' => $plan['rows_change'], 'date' => $plan['date']]);
    }

    /** Post it: the rows shown in the preview, in one transaction. */
    public function apply(Request $request, string $role, ContractFlightPassengerChange $contractFlightPassengerChange): JsonResponse
    {
        if ($deny = $this->guard()) {
            return $deny;
        }
        $in = $this->input($request, true);
        $service = new ContractFlightChangeCorrection;
        try {
            $plan = $service->apply($contractFlightPassengerChange, $in);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $passenger = $contractFlightPassengerChange->fresh()->passenger;
        $passenger?->contractFlight?->syncMoneyFromPassengers();
        $count = count($plan['ops']);

        return response()->json([
            'success' => true,
            'message' => $count
                ? 'Correction posted — '.$count.' difference'.($count === 1 ? '' : 's').' added beside the original rows; nothing was deleted.'
                : 'The entry now reads correctly — no money needed to move.',
            'changes' => $passenger ? $passenger->flightChanges()->with(['fromFlight:id,flight_number', 'toFlight:id,flight_number', 'creator:id,name'])
                ->get()->map->toCard()->values() : [],
        ]);
    }

    private function guard(): ?JsonResponse
    {
        if (! Access::allows('travels.contract-flight', 'e')) {
            return response()->json(['success' => false, 'message' => 'Only a super admin can correct a posted entry.'], 403);
        }
        if (! ContractFlightChangeCorrection::available()) {
            return response()->json(['success' => false, 'message' => 'Correcting entries is not installed on this server yet.'], 422);
        }

        return null;
    }

    private function input(Request $request, bool $apply): array
    {
        $side = Rule::in(['agent', 'company']);

        return $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => [$apply ? 'required' : 'nullable', 'string', 'max:300'],
            'signature' => [$apply ? 'required' : 'nullable', 'string', 'max:64'],
            'agent_id' => ['nullable', 'integer'],
            'tickets' => ['nullable', 'array', 'max:20'],
            'tickets.*.ticket_id' => ['required', 'integer'],
            'tickets.*.bearer' => ['nullable', $side],
            'tickets.*.agent_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'tickets.*.parts' => ['nullable', 'array', 'max:4'],
            'tickets.*.parts.*.key' => ['required', 'string', 'max:40'],
            'tickets.*.parts.*.seller' => ['nullable', 'string', 'max:20'],
            'tickets.*.parts.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'ours' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'charge' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'charge_bearer' => ['nullable', $side],
            'fault' => ['nullable', $side],
            'refunds' => ['nullable', 'array', 'max:20'],
            'refunds.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fare' => ['nullable', 'numeric', 'min:-99999999', 'max:99999999'],
            'fare_bearer' => ['nullable', $side],
            'agent_fare_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
    }
}
