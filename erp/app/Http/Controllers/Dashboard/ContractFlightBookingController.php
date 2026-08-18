<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Bank;
use App\Models\Company;
use App\Models\ContractFlight;
use App\Models\ContractFlightBooking;
use App\Models\ContractFlightBookingItem;
use App\Models\FlightCategory;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsSaleJournal;
use App\Traits\ResolvesPartyType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContractFlightBookingController implements HasMiddleware
{
    use PostsPartyLedger, PostsSaleJournal, ResolvesPartyType;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract flight sale|view all contract flight sale', only: ['index', 'invoice']),
            new Middleware('permission:create contract flight sale', only: ['store']),
            new Middleware('permission:edit contract flight sale', only: ['update']),
            new Middleware('permission:delete contract flight sale', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = ContractFlightBooking::with([
            'items.contractFlight.flightCategory',
            'items.contractFlight.ticket.airline',
            'items.contractFlight.ticket.from_airport',
            'items.contractFlight.ticket.to_airport',
            'client',
        ])->latest('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('booking_number', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('items.contractFlight', fn ($flight) => $flight
                        ->where('flight_number', 'like', "%{$search}%")
                        ->orWhere('airline_flight_no', 'like', "%{$search}%"))
                    ->orWhereHas('items.contractFlight.ticket.airline', fn ($airline) => $airline
                        ->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('flight_category_id')) {
            $query->whereHas('items.contractFlight', fn ($flight) => $flight
                ->where('flight_category_id', $request->flight_category_id));
        }

        $datas = $query->paginate(20)->withQueryString();
        $mtd = ContractFlightBooking::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month);
        $revenueMtd = (clone $mtd)->sum('total_amount');
        $lastMonth = now()->subMonth();
        $prevRevenue = ContractFlightBooking::whereYear('created_at', $lastMonth->year)
            ->whereMonth('created_at', $lastMonth->month)
            ->sum('total_amount');
        $totalSeats = ContractFlight::sum('total_seats');
        $soldSeats = ContractFlightBookingItem::whereHas('booking')->sum('seats');

        $stats = [
            'bookings_mtd' => (clone $mtd)->count(),
            'seats_mtd' => (clone $mtd)->sum('seats'),
            'revenue_mtd' => $revenueMtd,
            'revenue_trend' => $prevRevenue > 0
                ? round((($revenueMtd - $prevRevenue) / $prevRevenue) * 100)
                : null,
            'due_amount' => ContractFlightBooking::whereIn('payment_status', ['partial', 'due'])
                ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as total_due')
                ->value('total_due'),
            'due_count' => ContractFlightBooking::whereIn('payment_status', ['partial', 'due'])->count(),
            'seat_fill_rate' => $totalSeats > 0 ? round(($soldSeats / $totalSeats) * 100) : 0,
        ];

        $flights = ContractFlight::with([
            'flightCategory',
            'ticket.airline',
            'ticket.from_airport',
            'ticket.to_airport',
        ])->orderByDesc('departure_at')->get();
        $categories = FlightCategory::orderBy('name')->get();
        $agents = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['vendor', 'agent']))
            ->orderBy('name')->get(['id', 'name', 'phone']);
        $customers = User::orderBy('name')->role('customer')->get(['id', 'name', 'phone']);
        $banks = Bank::where('status', 1)->get();

        return view('contract-flight-sales.index', compact(
            'datas',
            'stats',
            'flights',
            'categories',
            'agents',
            'customers',
            'banks'
        ));
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            DB::transaction(function () use ($request) {
                $items = $this->itemPayloads($request);
                $booking = ContractFlightBooking::create($this->bookingPayload(
                    $request,
                    $items,
                    ContractFlightBooking::nextBookingNumber()
                ));
                $this->syncItems($booking, $items);
                $this->syncFlightSeats(collect($items)->pluck('contract_flight_id'));
                $this->reconcileFlightLedger($booking, null);
                $this->syncFlightJournal($booking, 'Flight sale — ' . $booking->booking_number);
                $this->syncFlightBookingReceivableSchedule($booking);
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Multi-flight voucher created successfully.']);
    }

    public function update(Request $request, $role, string $id)
    {
        $booking = ContractFlightBooking::with('items')->find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.']);
        }

        $validator = $this->validator($request, $booking->id);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            DB::transaction(function () use ($booking, $request) {
                $oldFlightIds = $booking->items->pluck('contract_flight_id');
                $oldClientId  = $booking->client_id;
                $items = $this->itemPayloads($request);
                $booking->update($this->bookingPayload($request, $items));
                $this->syncItems($booking, $items);
                $this->syncFlightSeats($oldFlightIds->merge(
                    collect($items)->pluck('contract_flight_id')
                )->unique());
                $this->reconcileFlightLedger($booking, $oldClientId);
                $this->syncFlightJournal($booking, 'Flight sale (edited) — ' . $booking->booking_number);
                $this->syncFlightBookingReceivableSchedule($booking);
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Multi-flight voucher updated successfully.']);
    }

    public function destroy(Request $request, $role, string $id)
    {
        try {
            DB::transaction(function () use ($request, $id) {
                $booking = ContractFlightBooking::with('items')->find($request->item_id ?? $id);
                if (!$booking) {
                    throw new \RuntimeException('Booking not found.');
                }

                $this->reversePartyLedger('flight_sale', $booking->id);
                $this->deleteSaleJournal('flight_sale', $booking->id);
                PaymentSchedule::where('schedulable_type', ContractFlightBooking::class)
                    ->where('schedulable_id', $booking->id)
                    ->delete();

                $flightIds = $booking->items->pluck('contract_flight_id');
                $booking->items()->delete();
                $booking->delete();
                $this->syncFlightSeats($flightIds);
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Booking deleted successfully.']);
    }

    /**
     * Keep the booking's invoice/paid rows on the client's party-statement
     * ledger in sync. If the client changed since the last save, the old
     * client's rows are dropped and fresh ones posted against the new
     * client rather than cascade-repairing the old chain.
     */
    private function reconcileFlightLedger(ContractFlightBooking $booking, ?int $oldClientId): void
    {
        if ($oldClientId && $oldClientId != $booking->client_id) {
            $this->reversePartyLedger('flight_sale', $booking->id);
        }

        if (!$booking->client_id) {
            return;
        }

        $common = [
            'payment_date' => now()->toDateString(),
            'reference_no' => $booking->booking_number,
        ];

        $this->reconcilePartyBankBalance('flight_sale', $booking->id, $booking->bank_id, (float) $booking->paid_amount);

        $this->reconcilePartyLedgerRow(
            $booking->client_id, 'flight_sale', $booking->id, true, (float) $booking->total_amount,
            $common + ['remarks' => 'Flight sale invoice — ' . $booking->booking_number]
        );

        $this->reconcilePartyLedgerRow(
            $booking->client_id, 'flight_sale', $booking->id, false, (float) $booking->paid_amount,
            $common + [
                'remarks'        => 'Flight sale payment received — ' . $booking->booking_number,
                'account_id'     => $booking->bank_id,
                'payment_method' => $booking->payment_method,
            ]
        );
    }

    /**
     * Keep the booking's chart-of-accounts journal entry in sync (creates it
     * on first save, replaces its items on every later save).
     */
    private function syncFlightJournal(ContractFlightBooking $booking, string $description): void
    {
        $this->updateSaleJournal(
            'flight_sale',
            $booking->id,
            Auth::user()->company_id ?? 2,
            now(),
            $booking->booking_number,
            $description,
            (float) $booking->total_amount,
            (float) $booking->paid_amount,
            (float) $booking->due_amount,
            $booking->bank_id,
            config('accounts.flight_sales_revenue')
        );
    }

    /**
     * Keep the booking's receivable PaymentSchedule row in sync with its
     * current due amount, keyed to the real paying client.
     */
    private function syncFlightBookingReceivableSchedule(ContractFlightBooking $booking): void
    {
        PaymentSchedule::where('schedulable_type', ContractFlightBooking::class)
            ->where('schedulable_id', $booking->id)
            ->delete();

        if ((float) $booking->due_amount <= 0) {
            return;
        }

        $booking->loadMissing('client');

        PaymentSchedule::create([
            'company_id'       => Auth::user()->company_id ?? null,
            'schedulable_type' => ContractFlightBooking::class,
            'schedulable_id'   => $booking->id,
            'type'             => 'receive',
            'party_type'       => $this->resolvePartyType($booking->client),
            'party_id'         => $booking->client_id,
            'party_name'       => $booking->client?->name,
            'source_label'     => $booking->booking_number,
            'amount'           => (float) $booking->due_amount,
            'paid_amount'      => (float) $booking->paid_amount,
            'scheduled_date'   => $booking->receivable_date?->toDateString() ?? now()->toDateString(),
            'status'           => 'pending',
            'created_by'       => Auth::id(),
        ]);
    }

    public function invoice($role, ContractFlightBooking $contractFlightBooking)
    {
        $contractFlightBooking->load([
            'items.contractFlight.flightCategory',
            'items.contractFlight.ticket.airline',
            'items.contractFlight.ticket.from_airport',
            'items.contractFlight.ticket.to_airport',
            'client',
        ]);

        $company = Company::find(Auth::user()->company_id)
            ?? Company::find(2)
            ?? Company::first();

        return view('contract-flight-sales.invoice', [
            'booking' => $contractFlightBooking,
            'company' => $company,
        ]);
    }

    private function validator(Request $request, ?int $bookingId = null)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.contract_flight_id' => 'required|distinct|exists:contract_flights,id',
            'items.*.seats' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'receivable_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'bank_id' => 'nullable|exists:banks,id',
            'notes' => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request, $bookingId) {
            $items = collect($request->input('items', []));
            $total = $items->sum(fn ($item) =>
                ((int) ($item['seats'] ?? 0)) * ((float) ($item['unit_price'] ?? 0))
            );

            if ((float) $request->input('paid_amount', 0) > $total) {
                $validator->errors()->add('paid_amount', 'Paid amount cannot exceed voucher total.');
            }

            foreach ($items as $index => $item) {
                $flight = ContractFlight::find($item['contract_flight_id'] ?? null);
                if (!$flight || $flight->total_seats <= 0) {
                    continue;
                }

                $bookedSeats = ContractFlightBookingItem::where(
                    'contract_flight_id',
                    $flight->id
                )->whereHas('booking')->when($bookingId, fn ($query) => $query->where(
                    'contract_flight_booking_id',
                    '!=',
                    $bookingId
                ))->sum('seats');
                $availableSeats = max(0, $flight->total_seats - $bookedSeats);

                if ((int) ($item['seats'] ?? 0) > $availableSeats) {
                    $validator->errors()->add(
                        "items.{$index}.seats",
                        "{$flight->flight_number} has only {$availableSeats} available seats."
                    );
                }
            }
        });

        return $validator;
    }

    private function itemPayloads(Request $request): array
    {
        return collect($request->input('items', []))->map(function ($item) {
            $seats = (int) $item['seats'];
            $unitPrice = (float) $item['unit_price'];

            return [
                'contract_flight_id' => (int) $item['contract_flight_id'],
                'seats' => $seats,
                'unit_price' => $unitPrice,
                'total_amount' => $seats * $unitPrice,
            ];
        })->values()->all();
    }

    private function bookingPayload(
        Request $request,
        array $items,
        ?string $bookingNumber = null
    ): array {
        $total = collect($items)->sum('total_amount');
        $seats = collect($items)->sum('seats');
        $paid = min((float) $request->input('paid_amount', 0), $total);
        $payload = [
            'client_id' => $request->client_id,
            'seats' => $seats,
            'unit_price' => $seats > 0 ? $total / $seats : 0,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'due_amount' => max(0, $total - $paid),
            'receivable_date' => $request->receivable_date,
            'payment_status' => $paid >= $total && $total > 0
                ? 'paid'
                : ($paid > 0 ? 'partial' : 'due'),
            'payment_method' => $request->payment_method,
            'bank_id' => $request->bank_id,
            'notes' => $request->notes,
        ];

        if ($bookingNumber) {
            $payload['booking_number'] = $bookingNumber;
            $payload['created_by'] = Auth::id();
            $payload['company_id'] = Auth::user()->company_id ?? 2;
        }

        return $payload;
    }

    private function syncItems(ContractFlightBooking $booking, array $items): void
    {
        $booking->items()->delete();
        $booking->items()->createMany($items);
    }

    private function syncFlightSeats($flightIds): void
    {
        foreach (collect($flightIds)->filter()->unique() as $flightId) {
            $flight = ContractFlight::find($flightId);
            if (!$flight) {
                continue;
            }

            $flight->update([
                'seats_sold' => ContractFlightBookingItem::where(
                    'contract_flight_id',
                    $flightId
                )->whereHas('booking')->sum('seats'),
                'revenue' => ContractFlightBookingItem::where(
                    'contract_flight_id',
                    $flightId
                )->whereHas('booking')->sum('total_amount'),
            ]);
        }
    }
}
