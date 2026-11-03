<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\ContractFlightPassenger;
use App\Services\ContractFlightTrail;
use App\Services\TicketTrail\TrailRefused;
use App\Support\UserRoleSetting\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * Re-assign → "All Trails and Edit" (owner, 2026-11-01): one passenger's whole contract-flight
 * trail, every entry editable or deletable, wrong rows REPLACED (ContractFlightTrail). Super admin
 * only — checked in every action, not only by hiding the button.
 */
class ContractFlightTrailController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:edit contract flight')];
    }

    public function show(string $role, ContractFlightPassenger $contractFlightPassenger): JsonResponse
    {
        if ($deny = $this->guard()) {
            return $deny;
        }

        return response()->json(['success' => true, 'trail' => (new ContractFlightTrail)->state($contractFlightPassenger)]);
    }

    /** The same run as Confirm, rolled back. */
    public function preview(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger): JsonResponse
    {
        if ($deny = $this->guard()) {
            return $deny;
        }
        try {
            $r = (new ContractFlightTrail)->run($contractFlightPassenger, $this->edit($request), false);
        } catch (TrailRefused $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true] + $r);
    }

    public function apply(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger): JsonResponse
    {
        if ($deny = $this->guard()) {
            return $deny;
        }
        $request->validate(['reason' => ['required', 'string', 'max:500'], 'signature' => ['required', 'string', 'max:64']]);
        $service = new ContractFlightTrail;
        $edit = $this->edit($request);
        // deleting an entry follows the module's Delete, editing its Edit (User Role Setting, 2026-11-01)
        if ($edit['action'] === 'delete' && ! Access::allows('travels.contract-flight', 'd')) {
            return response()->json(['success' => false, 'message' => 'Deleting an entry needs Delete on Contract Flight.'], 403);
        }
        try {
            $r = $service->run($contractFlightPassenger, $edit, true, trim((string) $request->input('reason')), (string) $request->input('signature'));
        } catch (TrailRefused $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        if (! $r['changed']) {
            return response()->json(['success' => false, 'message' => 'Nothing to change — the entry already says this.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Replaced — '.implode(' · ', $r['summary']),
            'trail' => $service->state($contractFlightPassenger->fresh()),
        ]);
    }

    private function guard(): ?JsonResponse
    {
        if (! Access::allows('travels.contract-flight', 'e')) {
            return response()->json(['success' => false, 'message' => 'Only a super admin can edit the trail.'], 403);
        }
        if (! ContractFlightTrail::available()) {
            return response()->json(['success' => false, 'message' => 'The Contract Flight Trail is not installed on this server yet.'], 422);
        }

        return null;
    }

    private function edit(Request $request): array
    {
        $side = Rule::in(['agent', 'company']);
        $v = $request->validate([
            'entry' => ['required', 'string', 'max:20'],
            'action' => ['required', Rule::in(['edit', 'delete'])],
            'at' => ['nullable', 'date'],
            'input' => ['nullable', 'array'],
            'input.agent_id' => ['nullable', 'integer'],
            'input.agent_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'input.tickets' => ['nullable', 'array', 'max:20'],
            'input.tickets.*.ticket_id' => ['required', 'integer'],
            'input.tickets.*.bearer' => ['nullable', $side],
            'input.tickets.*.agent_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'input.tickets.*.parts' => ['nullable', 'array', 'max:4'],
            'input.tickets.*.parts.*.key' => ['required', 'string', 'max:40'],
            'input.tickets.*.parts.*.seller' => ['nullable', 'string', 'max:20'],
            'input.tickets.*.parts.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'input.ours' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'input.charge' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'input.charge_bearer' => ['nullable', $side],
            'input.fault' => ['nullable', $side],
            'input.refunds' => ['nullable', 'array', 'max:20'],
            'input.refunds.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'input.fare' => ['nullable', 'numeric', 'min:-99999999', 'max:99999999'],
            'input.fare_bearer' => ['nullable', $side],
            'input.agent_fare_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // a ticket (the Ticketing Trail's own boxes; the Rewriter checks every id again)
            'input.ticket' => ['nullable', 'array'],
            'input.ticket.ticket_id' => ['nullable', 'integer'],
            'input.ticket.ticket_no' => ['nullable', 'string', 'max:60'],
            'input.ticket.ticket_class' => ['nullable', 'string', 'max:20'],
            'input.ticket.trip_type' => ['nullable', 'string', 'max:20'],
            'input.ticket.purchase_date' => ['nullable', 'date'],
            'input.ticket.travel_at' => ['nullable', 'date'],
            'input.ticket.seller' => ['nullable', 'string', 'max:30'],
            'input.ticket.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'input.airline_id' => ['nullable', 'integer'],
            'input.payments' => ['nullable', 'array', 'max:30'],
            'input.payments.*.id' => ['nullable', 'integer'],
            'input.payments.*.kind' => ['nullable', Rule::in(['bank', 'portal'])],
            'input.payments.*.bank_id' => ['nullable', 'integer'],
            'input.payments.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'input.payments.*.date' => ['nullable', 'date'],
            'input.payments.*.method' => ['nullable', 'string', 'max:30'],
            'input.payments.*.ref' => ['nullable', 'string', 'max:100'],
            'input.payments.*.at_purchase' => ['nullable', 'boolean'],
            'input.payments.*.remove' => ['nullable', 'boolean'],
            // a service: who is paid, how much
            'input.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'input.vendor_id' => ['nullable', 'integer'],
        ]);

        return ['entry' => $v['entry'], 'action' => $v['action'], 'at' => $v['at'] ?? null, 'input' => $v['input'] ?? []];
    }
}
