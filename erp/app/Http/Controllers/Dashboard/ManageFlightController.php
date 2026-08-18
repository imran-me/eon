<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bank;
use App\Models\ContractFlight;
use App\Models\FlightCategory;
use App\Models\FlightCategoryType;
use App\Models\FlightOfficer;
use App\Models\PassportHolder;
use App\Models\PassportHolderCategory;
use App\Models\PaymentSchedule;
use App\Models\Portal;
use App\Models\Ticket;
use App\Models\User;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsPurchaseJournal;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ManageFlightController implements HasMiddleware
{
    use PostsPartyLedger, PostsPurchaseJournal;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract flight|view all contract flight', only: ['index', 'invoice']),
            new Middleware('permission:create contract flight', only: ['store']),
            new Middleware('permission:edit contract flight', only: ['update']),
            new Middleware('permission:delete contract flight', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $now = now();

        $query = ContractFlight::with([
            'flightCategory', 'categoryType', 'ticket.airline', 'ticket.from_airport',
            'ticket.to_airport', 'agent', 'boardingOfficer.user',
            'immigrationOfficer.user', 'passengers.passportHolder',
        ])
            ->orderByDesc('departure_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('flight_number', 'like', "%{$search}%")
                  ->orWhere('airline_flight_no', 'like', "%{$search}%")
                  ->orWhereHas('ticket', fn($t) => $t->where('title', 'like', "%{$search}%"))
                  ->orWhereHas('ticket.airline', fn($a) => $a->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('month')) {
            [$year, $month] = explode('-', $request->month);
            $query->whereYear('departure_at', $year)->whereMonth('departure_at', $month);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('flight_category_id')) {
            $query->where('flight_category_id', $request->flight_category_id);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        $datas = $query->paginate(15)->withQueryString();

        // ── MTD stats ──
        $mtd = ContractFlight::whereYear('departure_at', $now->year)->whereMonth('departure_at', $now->month);
        $lastMonth = (clone $now)->subMonth();
        $prevMtd = ContractFlight::whereYear('departure_at', $lastMonth->year)->whereMonth('departure_at', $lastMonth->month);

        $revenueMtd = (clone $mtd)->sum('revenue');
        $revenuePrev = (clone $prevMtd)->sum('revenue');
        $revenueTrend = $revenuePrev > 0 ? round((($revenueMtd - $revenuePrev) / $revenuePrev) * 100) : null;

        $seatsSoldMtd = (clone $mtd)->sum('seats_sold');
        $seatsTotalMtd = (clone $mtd)->sum('total_seats');
        $seatsPct = $seatsTotalMtd > 0 ? round(($seatsSoldMtd / $seatsTotalMtd) * 100) : 0;

        $stats = [
            'total_flights_mtd' => (clone $mtd)->count(),
            'revenue_mtd'       => $revenueMtd,
            'revenue_trend'     => $revenueTrend,
            'seats_sold_mtd'    => $seatsSoldMtd,
            'seats_total_mtd'   => $seatsTotalMtd,
            'seats_pct'         => $seatsPct,
            'net_profit_mtd'    => $revenueMtd - (clone $mtd)->sum('cost_price'),
            'month_label'       => $now->format('F Y'),
        ];

        $categories = FlightCategory::with('categoryType')->orderBy('name')->get();
        $categoryTypes = FlightCategoryType::where('status', 'active')->orderBy('name')->get();
        $agents     = User::orderBy('name')->role('agent')->get(['id', 'name']);
        $tickets    = Ticket::with('airline', 'from_airport', 'to_airport')->orderBy('title')->get();

        // ── For the inline "+ New Route" quick-create form (reuses Ticket creation) ──
        $airports = Airport::orderBy('name')->get();
        $airlines = Airline::orderBy('name')->where('status', 1)->get();
        $vendors  = User::orderBy('name')->role('vendor')->get();
        $banks    = Bank::where('status', 1)->get();
        $portals  = Portal::orderBy('name')->where('status', 'active')->get();
        $officers = FlightOfficer::with(['user', 'airline'])->where('status', 'active')->orderBy('id')->get();
        $passportHolders = PassportHolder::with('category')->orderBy('name')->get();
        $passportHolderCategories = PassportHolderCategory::orderBy('name')->get();

        return view('contract-flights.index', compact(
            'datas', 'stats', 'categories', 'categoryTypes', 'agents', 'tickets',
            'airports', 'airlines', 'vendors', 'banks', 'portals', 'officers',
            'passportHolders', 'passportHolderCategories'
        ));
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::transaction(function () use ($request) {
                $flight = ContractFlight::create([
                    'flight_number'      => ContractFlight::nextFlightNumber(),
                    'flight_category_id' => $request->flight_category_id,
                    'flight_category_type_id' => $request->flight_category_type_id,
                    'handling_type' => $request->handling_type,
                    'ticket_class' => $request->ticket_class,
                    'ticket_id'          => $request->ticket_id,
                    'airline_flight_no'  => $request->airline_flight_no,
                    'departure_at'       => $request->departure_at,
                    'arrival_at'         => $request->arrival_at,
                    'total_seats'        => $request->total_seats,
                    'seats_sold'         => $request->seats_sold ?? 0,
                    'cost_price'         => $this->totalCost($request),
                    'revenue'            => $this->totalRevenue($request),
                    'agent_id'           => $request->agent_id,
                    'vendor_id'          => $request->vendor_id,
                    'portal_id'          => $request->portal_id,
                    'cost_bank_id'       => $request->cost_bank_id,
                    'cost_paid_amount'   => $request->cost_paid_amount ?? 0,
                    'purchase_date'      => $request->purchase_date,
                    'payable_date'       => $request->payable_date,
                    'boarding_officer_id' => $request->boarding_officer_id,
                    'immigration_officer_id' => $request->handling_type === 'immigration_wise' ? $request->immigration_officer_id : null,
                    'ticket_cost_per_pax' => $request->ticket_cost_per_pax ?? 0,
                    'manpower_cost_per_pax' => $request->manpower_cost_per_pax ?? 0,
                    'boarding_cost_per_pax' => $request->boarding_cost_per_pax ?? 0,
                    'immigration_cost_per_pax' => $request->handling_type === 'immigration_wise' ? ($request->immigration_cost_per_pax ?? 0) : 0,
                    'sale_price_per_pax' => $request->sale_price_per_pax ?? 0,
                    'status'             => $request->status,
                    'notes'              => $request->notes,
                    'created_by'         => Auth::id(),
                ]);
                $this->syncPassengers($flight, $request);
                $this->syncPaymentSchedules($flight);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contract flight created successfully.',
        ]);
    }

    public function update(Request $request, $role, string $id)
    {
        $data = ContractFlight::find($id);
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!',
            ]);
        }

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::transaction(function () use ($data, $request) {
                $data->update([
                    'flight_category_id' => $request->flight_category_id,
                    'flight_category_type_id' => $request->flight_category_type_id,
                    'handling_type' => $request->handling_type,
                    'ticket_class' => $request->ticket_class,
                    'ticket_id' => $request->ticket_id,
                    'airline_flight_no' => $request->airline_flight_no,
                    'departure_at' => $request->departure_at,
                    'arrival_at' => $request->arrival_at,
                    'total_seats' => $request->total_seats,
                    'seats_sold' => $request->seats_sold ?? 0,
                    'cost_price' => $this->totalCost($request),
                    'revenue' => $this->totalRevenue($request),
                    'agent_id' => $request->agent_id,
                    'vendor_id' => $request->vendor_id,
                    'portal_id' => $request->portal_id,
                    'cost_bank_id' => $request->cost_bank_id,
                    'cost_paid_amount' => $request->cost_paid_amount ?? 0,
                    'purchase_date' => $request->purchase_date,
                    'payable_date' => $request->payable_date,
                    'boarding_officer_id' => $request->boarding_officer_id,
                    'immigration_officer_id' => $request->handling_type === 'immigration_wise' ? $request->immigration_officer_id : null,
                    'ticket_cost_per_pax' => $request->ticket_cost_per_pax ?? 0,
                    'manpower_cost_per_pax' => $request->manpower_cost_per_pax ?? 0,
                    'boarding_cost_per_pax' => $request->boarding_cost_per_pax ?? 0,
                    'immigration_cost_per_pax' => $request->handling_type === 'immigration_wise' ? ($request->immigration_cost_per_pax ?? 0) : 0,
                    'sale_price_per_pax' => $request->sale_price_per_pax ?? 0,
                    'status' => $request->status,
                    'notes' => $request->notes,
                ]);
                $this->syncPassengers($data, $request);
                $this->syncPaymentSchedules($data);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contract flight updated successfully.',
        ]);
    }

    public function destroy(Request $request, $role, string $id)
    {
        try {
            $item = ContractFlight::find($request->item_id ?? $id);
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!',
                ]);
            }

            DB::transaction(function () use ($item) {
                PaymentSchedule::where('schedulable_type', ContractFlight::class)
                    ->where('schedulable_id', $item->id)
                    ->delete();
                $this->deletePurchaseJournal('contract_flight', $item->id);
                $this->reversePurchaseCostPayment('contract_flight', $item->id);
                $item->delete();
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contract flight deleted successfully.',
        ]);
    }

    /**
     * Settle a contract flight's payable to its vendor — Debit AP (2110) /
     * Credit Bank, and mark the linked PaymentSchedule row paid.
     */
    public function payVendor(Request $request, $role, ContractFlight $contractFlight)
    {
        $validated = $request->validate([
            'bank_id'        => 'required|exists:banks,id',
            'payment_date'   => 'required|date',
            'reference_no'   => 'nullable|string|max:255',
            'payment_amount' => 'required|numeric|min:0.01',
            'schedule_id'    => 'nullable|exists:payment_schedules,id',
        ]);

        if ($validated['payment_amount'] > (float) $contractFlight->due_amount) {
            $message = 'Payment amount cannot exceed due amount!';
            return $request->ajax()
                ? response()->json(['success' => false, 'message' => $message])
                : redirect()->back()->with('error', $message);
        }

        DB::transaction(function () use ($validated, $contractFlight) {
            $bank = Bank::find($validated['bank_id']);
            if (!$bank || !$bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }

            $newCostPaid = min((float) $contractFlight->cost_paid_amount + $validated['payment_amount'], (float) $contractFlight->cost_price);
            $newDue      = max(0, (float) $contractFlight->cost_price - $newCostPaid);

            $this->reconcileCostPayment('contract_flight', $contractFlight->id, $bank->id, null, $newCostPaid, $contractFlight->vendor_id, $validated['payment_date']);

            $this->postPurchaseJournalPayment(
                'contract_flight',
                $contractFlight->id,
                Auth::user()->company_id ?? 2,
                $validated['payment_date'],
                $validated['reference_no'] ?? $contractFlight->flight_number,
                'Flight vendor payment made — ' . $contractFlight->flight_number,
                (float) $validated['payment_amount'],
                $bank->account_id
            );

            $contractFlight->forceFill(['due_amount' => $newDue, 'cost_paid_amount' => $newCostPaid])->saveQuietly();

            $scheduleQuery = PaymentSchedule::where('schedulable_type', ContractFlight::class)
                ->where('schedulable_id', $contractFlight->id)
                ->where('type', 'pay');

            if (!empty($validated['schedule_id'])) {
                $scheduleQuery->where('id', $validated['schedule_id']);
            }

            if ($newDue <= 0) {
                $scheduleQuery->update(['status' => 'paid', 'paid_date' => $validated['payment_date']]);
            } else {
                $scheduleQuery->update(['paid_amount' => (float) $validated['payment_amount'], 'status' => 'pending']);
            }
        });

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Vendor payment recorded successfully.']);
        }

        return redirect()->back()->with('success', 'Vendor payment recorded successfully.');
    }

    /**
     * Printable invoice/manifest for a single contract flight.
     */
    public function invoice($role, ContractFlight $contractFlight)
    {
        $contractFlight->load(['flightCategory', 'categoryType', 'ticket.airline', 'ticket.from_airport', 'ticket.to_airport', 'agent', 'boardingOfficer.user', 'immigrationOfficer.user', 'passengers.passportHolder']);
        $company = \App\Models\Company::find(Auth::user()->company_id) ?? \App\Models\Company::find(2) ?? \App\Models\Company::first();

        return view('contract-flights.invoice', [
            'flight'  => $contractFlight,
            'company' => $company,
        ]);
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'flight_category_id' => 'required|exists:flight_categories,id',
            'flight_category_type_id' => 'nullable|exists:flight_category_types,id',
            'handling_type' => 'required|in:manpower_wise,immigration_wise',
            'ticket_class' => 'required|in:economy,business,first',
            'ticket_id' => 'required|exists:tickets,id',
            'airline_flight_no' => 'required|string|max:50',
            'departure_at' => 'required|date',
            'arrival_at' => 'nullable|date|after_or_equal:departure_at',
            'total_seats' => 'required|integer|min:0',
            'seats_sold' => 'nullable|integer|min:0|lte:total_seats',
            'cost_price' => 'nullable|numeric|min:0',
            'revenue' => 'nullable|numeric|min:0',
            'agent_id' => 'nullable|exists:users,id',
            'vendor_id' => 'nullable|exists:users,id',
            'portal_id' => 'nullable|exists:portals,id',
            'cost_bank_id' => 'nullable|exists:banks,id',
            'cost_paid_amount' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'payable_date' => 'nullable|date',
            'boarding_officer_id' => 'required|exists:flight_officers,id',
            'immigration_officer_id' => 'required_if:handling_type,immigration_wise|nullable|exists:flight_officers,id',
            'ticket_cost_per_pax' => 'nullable|numeric|min:0',
            'manpower_cost_per_pax' => 'nullable|numeric|min:0',
            'boarding_cost_per_pax' => 'nullable|numeric|min:0',
            'immigration_cost_per_pax' => 'nullable|numeric|min:0',
            'sale_price_per_pax' => 'required|numeric|min:0',
            'passenger_ids' => 'nullable|array',
            'passenger_ids.*' => 'distinct|exists:passport_holders,id',
            'status' => 'required|in:open,boarding,departed,cancelled',
            'notes' => 'nullable|string',
        ]);
    }

    private function totalCost(Request $request): float
    {
        $perPassenger = (float) $request->input('ticket_cost_per_pax', 0)
            + (float) $request->input('manpower_cost_per_pax', 0)
            + (float) $request->input('boarding_cost_per_pax', 0)
            + ($request->handling_type === 'immigration_wise' ? (float) $request->input('immigration_cost_per_pax', 0) : 0);

        return $perPassenger > 0
            ? $perPassenger * (int) $request->input('seats_sold', 0)
            : (float) $request->input('cost_price', 0);
    }

    private function totalRevenue(Request $request): float
    {
        $salePrice = (float) $request->input('sale_price_per_pax', 0);

        return $salePrice > 0
            ? $salePrice * (int) $request->input('seats_sold', 0)
            : (float) $request->input('revenue', 0);
    }

    private function syncPassengers(ContractFlight $flight, Request $request): void
    {
        $flight->passengers()->delete();
        foreach (array_unique($request->input('passenger_ids', [])) as $passportHolderId) {
            $flight->passengers()->create([
                'passport_holder_id' => $passportHolderId,
                'document_statuses' => [
                    'ticket' => false,
                    'visa' => false,
                    'manpower' => false,
                    'other' => false,
                ],
            ]);
        }
    }

    private function syncPaymentSchedules(ContractFlight $flight): void
    {
        $companyId = Auth::user()->company_id ?? null;

        $flight->loadMissing('vendor');

        $costPrice = (float) $flight->cost_price;
        $costPaid  = min((float) $flight->cost_paid_amount, $costPrice);
        $due       = max(0, $costPrice - $costPaid);

        $flight->forceFill(['due_amount' => $due])->saveQuietly();

        $paymentAccountId = null;
        if ($flight->cost_bank_id) {
            $paymentAccountId = Bank::find($flight->cost_bank_id)?->account_id;
        } elseif ($flight->portal_id) {
            $paymentAccountId = Portal::find($flight->portal_id)?->account_id;
        }

        $this->reconcileCostPayment(
            'contract_flight',
            $flight->id,
            $flight->cost_bank_id,
            $flight->portal_id,
            $costPaid,
            $flight->vendor_id,
            $flight->purchase_date ?? now()
        );

        // Liability recognition on the vendor's party statement — see
        // VisaProcessingController::syncPaymentSchedules() for rationale.
        if ($flight->vendor_id && $costPrice > 0) {
            $this->reconcilePartyLedgerRow(
                $flight->vendor_id, 'contract_flight', $flight->id, true, $costPrice,
                [
                    'account_id'   => null,
                    'payment_date' => $flight->purchase_date ?? now(),
                    'reference_no' => $flight->flight_number,
                    'remarks'      => 'Flight cost — ' . $flight->flight_number,
                ]
            );
        }

        PaymentSchedule::where('schedulable_type', ContractFlight::class)
            ->where('schedulable_id', $flight->id)
            ->delete();

        if ($costPrice > 0) {
            $this->updatePurchaseJournal(
                'contract_flight',
                $flight->id,
                Auth::user()->company_id ?? 2,
                $flight->purchase_date ?? $flight->payable_date ?? now(),
                $flight->flight_number,
                'Flight vendor cost — ' . $flight->flight_number,
                $costPrice,
                $costPaid,
                $due,
                $paymentAccountId,
                config('accounts.flight_purchase_cost')
            );

            if ($due > 0 && !$flight->portal_id) {
                PaymentSchedule::create([
                    'company_id'       => $companyId,
                    'schedulable_type' => ContractFlight::class,
                    'schedulable_id'   => $flight->id,
                    'type'             => 'pay',
                    'party_type'       => 'vendor',
                    'party_id'         => $flight->vendor_id,
                    'party_name'       => $flight->vendor?->name ?? 'Vendor (unassigned)',
                    'source_label'     => $flight->flight_number,
                    'amount'           => $due,
                    'paid_amount'      => 0,
                    'scheduled_date'   => $flight->payable_date?->toDateString() ?? now()->toDateString(),
                    'status'           => 'pending',
                    'created_by'       => Auth::id(),
                ]);
            }
        } else {
            $this->deletePurchaseJournal('contract_flight', $flight->id);
        }
    }
}
