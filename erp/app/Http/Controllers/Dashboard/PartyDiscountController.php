<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PartyDiscount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartyDiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PartyDiscountController extends Controller
{
    private function authorizeAction(Request $request): void
    {
        // Price concessions require explicit financial authority, not just ledger access.
        abort_unless($request->user()?->is_super_admin, 403);
        abort_unless(Schema::hasTable('party_discounts'), 422, 'Run the party discount migration first.');
    }

    public function index(Request $request, PartyDiscountService $service)
    {
        $this->authorizeAction($request);
        $data = $request->validate(['party_id' => 'required|integer|exists:users,id', 'company' => 'nullable|integer|exists:companies,id', 'project' => 'nullable|integer']);
        $party = User::findOrFail($data['party_id']);
        abort_unless($party->hasRole('agent') || $party->hasRole('customer'), 422);
        $charges = Transaction::where('user_id', $party->id)->whereIn('type', array_keys(PartyDiscountService::TYPES))->where('debit', '>', 0)
            ->when($request->integer('company'), fn ($q) => $q->where('company_id', $request->integer('company')))
            ->when($request->integer('project'), fn ($q) => $q->where('expense_department_id', $request->integer('project')))->get();
        $documents = [];
        foreach ($charges->unique(fn ($row) => $row->type.':'.$row->invoice_id) as $charge) {
            $class = PartyDiscountService::TYPES[$charge->type];
            $doc = $class::find($charge->invoice_id);
            if (! $doc || ($charge->type === 'ticket_sale' && $doc->status === 'cancelled')) {
                continue;
            }
            $owner = $charge->type === 'party_invoice' ? $doc->user_id : ($charge->type === 'ticket_sale' ? $doc->client_id : $doc->agent_id);
            if ((int) $owner !== (int) $party->id) {
                continue;
            }
            $due = $service->outstanding($charge->type, $doc);
            $kind = $charge->type === 'contract_flight_passenger_agent' ? 'Flight package' : ucwords(str_replace('_', ' ', $charge->type));
            $documents[] = ['type' => $charge->type, 'id' => $doc->id, 'label' => $charge->reference_no.' — '.$kind, 'due' => $due, 'date' => substr((string) $charge->payment_date, 0, 10), 'company' => $charge->company_id, '_id' => $charge->id];
        }
        // Oldest first — the order an overall discount is spread in.
        usort($documents, fn ($a, $b) => [$a['date'], $a['_id']] <=> [$b['date'], $b['_id']]);
        $documents = array_map(function ($d) {
            unset($d['_id']);

            return $d;
        }, $documents);
        $discounts = PartyDiscount::where('user_id', $party->id)
            ->when($request->integer('company'), fn ($q) => $q->where('company_id', $request->integer('company')))
            ->when($request->integer('project'), fn ($q) => $q->whereIn('charge_transaction_id', $charges->pluck('id')))
            ->latest('id')->get(array_merge(['id', 'document_type', 'document_id', 'amount', 'discount_date', 'reason', 'reversed_at'], Schema::hasColumn('party_discounts', 'batch_key') ? ['batch_key'] : []));

        return response()->json(compact('documents', 'discounts'));
    }

    public function store(Request $request, PartyDiscountService $service)
    {
        $this->authorizeAction($request);
        $data = $request->validate(['party_id' => 'required|integer|exists:users,id', 'document_type' => ['required', Rule::in(array_keys(PartyDiscountService::TYPES))], 'document_id' => 'required|integer|min:1', 'amount' => 'required|numeric|min:0.01|decimal:0,2', 'date' => 'required|date|before_or_equal:today', 'reason' => 'required|string|min:5|max:500', 'request_key' => 'required|uuid', 'company' => 'nullable|integer|exists:companies,id']);
        $discount = $service->post((int) $data['party_id'], $data['document_type'], (int) $data['document_id'], (float) $data['amount'], $data['date'], $data['reason'], $data['request_key'], $request->user(), $request->integer('company') ?: null);

        return response()->json(['success' => true, 'message' => 'Discount credit note DISC-'.$discount->id.' posted. No bank/cash movement.']);
    }

    public function storeOverall(Request $request, PartyDiscountService $service)
    {
        $this->authorizeAction($request);
        abort_unless(Schema::hasColumn('party_discounts', 'batch_key'), 422, 'Run the overall-discount migration first.');
        $data = $request->validate(['party_id' => 'required|integer|exists:users,id', 'amount' => 'required|numeric|min:0.01|decimal:0,2', 'date' => 'required|date|before_or_equal:today', 'reason' => 'required|string|min:5|max:500', 'request_key' => 'required|uuid', 'company' => 'nullable|integer|exists:companies,id']);
        $result = $service->postOverall((int) $data['party_id'], (float) $data['amount'], $data['date'], $data['reason'], $data['request_key'], $request->user(), $request->integer('company') ?: null);

        $lines = array_map(fn ($l) => $l['label'].' ৳'.number_format($l['amount'], 2), $result['split']);
        if ($result['unallocated'] > 0) {
            $lines[] = 'kept as party credit ৳'.number_format($result['unallocated'], 2);
        }

        return response()->json(['success' => true, 'message' => 'Overall discount of ৳'.number_format((float) $data['amount'], 2).' posted — '.implode(', ', $lines).'. No bank/cash movement.']);
    }

    public function reverseBatch(Request $request, string $role, string $batch, PartyDiscountService $service)
    {
        $this->authorizeAction($request);
        $data = $request->validate(['reason' => 'required|string|min:5|max:500', 'confirmation' => 'required|in:CONFIRM', 'company' => 'nullable|integer|exists:companies,id']);
        abort_unless(Str::isUuid($batch), 404);
        $count = $service->reverseBatch($batch, $data['reason'], $request->user(), $request->integer('company') ?: null);

        return response()->json(['success' => true, 'message' => 'Overall discount reversed ('.$count.' entries). Invoice dues restored; bank/cash unchanged.']);
    }

    public function reverse(Request $request, string $role, int $discount, PartyDiscountService $service)
    {
        $this->authorizeAction($request);
        $data = $request->validate(['reason' => 'required|string|min:5|max:500', 'confirmation' => 'required|in:CONFIRM', 'company' => 'nullable|integer|exists:companies,id']);
        $service->reverse($discount, $data['reason'], $request->user(), $request->integer('company') ?: null);

        return response()->json(['success' => true, 'message' => 'Discount reversed. Invoice due restored; bank/cash unchanged.']);
    }
}
