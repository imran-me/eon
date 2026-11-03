<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\ContractFlight;
use App\Models\Portal;
use App\Models\Ticket;
use App\Models\TicketBooking;
use App\Models\TicketRouteLeg;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class TicketController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view ticket|view all ticket', only: ['index']),
            new Middleware('permission:create ticket', only: ['create', 'store']),
            new Middleware('permission:edit ticket', only: ['edit', 'update']),
            new Middleware('permission:delete ticket', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $query = Ticket::select('tickets.*')
            ->leftJoin('users', 'users.id', '=', 'tickets.vendor_id')
            ->leftJoin('portals', 'portals.id', '=', 'tickets.portal_id')
            ->leftJoin('airports as from_airport', 'from_airport.id', '=', 'tickets.from_airport_id')
            ->leftJoin('airports as to_airport', 'to_airport.id', '=', 'tickets.to_airport_id')
            ->orderBy('tickets.title', 'asc');

        if ($request->has('vendor_id') && ! empty($request->vendor_id)) {
            $query->where('tickets.vendor_id', $request->vendor_id);
        }

        if ($request->has('portal_id') && ! empty($request->portal_id)) {
            $query->where('tickets.portal_id', $request->portal_id);
        }

        if ($request->has('from_airport_id') && ! empty($request->from_airport_id)) {
            $query->where('tickets.from_airport_id', $request->from_airport_id);
        }

        if ($request->has('to_airport_id') && ! empty($request->to_airport_id)) {
            $query->where('tickets.to_airport_id', $request->to_airport_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('tickets.title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->whereDate('tickets.status', $request->status);
        }

        // The list bar's date range. A ticket route has no date of its own, so
        // the range reads the day it was added.
        if ($request->filled('from_date')) {
            $query->whereDate('tickets.created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('tickets.created_at', '<=', $request->to_date);
        }

        $datas = $query->paginate(20);

        $airports = Airport::with('country')->orderBy('name')->get();
        $vendors = User::orderBy('name')->role('vendor')->get();
        $portals = Portal::orderBy('name')->where('status', 'active')->get();
        $allPortals = Portal::orderBy('name')->get();
        $airlines = Airline::orderBy('name')->where('status', 1)->get();

        return view('tickets.index', compact(
            'datas',
            'airports',
            'vendors',
            'portals',
            'allPortals',
            'airlines'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('tickets.create-modal');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $this->mergeViaDefaults($request);
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            // a route of several tickets (via airports, or a separate return) takes them from its tickets.
            // The airline is optional (owner, 2026-10-26): one route, e.g. DAC → KUL for Contract File, and
            // the airline chosen on each ticket ("Airline that issues" on Ticket Entry).
            'airline_id' => 'nullable|exists:airlines,id',
            'vendor_id' => 'required_without_all:portal_id,has_route_legs',
            'portal_id' => 'required_without_all:vendor_id,has_route_legs',
            'from_airport_id' => 'required',
            'to_airport_id' => 'required',
            'trip_type' => 'nullable|in:one-way,two-way',
            'return_mode' => 'nullable|in:same,separate',
            'return_stops' => 'nullable|array',
            'return_stops.*.airport_id' => 'nullable|exists:airports,id',
        ], [
            'vendor_id.required_without' => 'Please select at least a vendor or portal.',
            'portal_id.required_without' => 'Please select at least a vendor or portal.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            $data = DB::transaction(function () use ($request) {
                $ticket = Ticket::create([
                    'title' => $request->title,
                    'vendor_id' => $request->vendor_id,
                    'portal_id' => $request->portal_id,
                    'airline_id' => $request->airline_id,
                    'from_airport_id' => $request->from_airport_id,
                    'to_airport_id' => $request->to_airport_id,
                    'price' => $request->price,
                    'qty' => $request->qty,
                    'status' => $request->status ? 1 : 0,
                ] + self::tripAttributes($request));
                $this->syncRouteLegs($ticket, $request);

                return $ticket;
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.',
            'data' => $data->load('airline', 'from_airport', 'to_airport'),
        ]);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('tickets.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, string $role, string $id)
    {
        $data = Ticket::findOrFail($id);
        if (empty($data)) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!',
                ]);
            } else {
                return redirect()->back()->with('error', 'Data Info Not Found!');
            }
        }
        $this->mergeViaDefaults($request);
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'vendor_id' => 'required_without_all:portal_id,has_route_legs',
            'portal_id' => 'required_without_all:vendor_id,has_route_legs',
            'airline_id' => 'nullable|exists:airlines,id',   // optional — see store()
            'from_airport_id' => 'required',
            'to_airport_id' => 'required',
            'trip_type' => 'nullable|in:one-way,two-way',
            'return_mode' => 'nullable|in:same,separate',
            'return_stops' => 'nullable|array',
            'return_stops.*.airport_id' => 'nullable|exists:airports,id',
        ], [
            'vendor_id.required_without' => 'Please select at least a vendor or portal.',
            'portal_id.required_without' => 'Please select at least a vendor or portal.',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::transaction(function () use ($data, $request) {
                $data->update([
                    'title' => $request->title,
                    'vendor_id' => $request->vendor_id,
                    'portal_id' => $request->portal_id,
                    'airline_id' => $request->airline_id,
                    'from_airport_id' => $request->from_airport_id,
                    'to_airport_id' => $request->to_airport_id,
                    'price' => $request->price,
                    'qty' => $request->qty,
                    'status' => $request->status ? 1 : 0,
                ] + self::tripAttributes($request));
                $this->syncRouteLegs($data, $request);
            });
        } catch (\Throwable $th) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                ]);
            } else {
                return redirect()->back()->with('error', $th->getMessage());
            }
        }

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
            ]);
        } else {
            return redirect()->back()->with('success', 'Data updated successfully.');
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = Ticket::find($request->item_id);
            if ($item) {
                // Owner, 2026-09-21: a route with history is never blocked from
                // deletion. It is soft-deleted, renamed "… (Deleted)" and
                // disappears from every dropdown, while the purchases, sales,
                // bookings and flights that point at it keep resolving. A route
                // nothing refers to is removed outright.
                $inUse = $item->ticket_sales()->exists() || $item->ticket_purchases()->exists()
                    || Schema::hasTable('ticket_bookings') && TicketBooking::withTrashed()->where('ticket_id', $item->id)->exists()
                    || ContractFlight::where('ticket_id', $item->id)->exists();
                if ($inUse) {
                    if (! str_ends_with($item->title, ' (Deleted)')) {
                        $item->title = $item->title.' (Deleted)';
                    }
                    $item->status = 0;
                    $item->save();
                    $item->delete();
                } else {
                    $item->routeLegs()->delete();
                    $item->forceDelete();
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!',
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.',
        ]);
    }

    // public function getTicketEditModal(Request $request)
    // {
    //     try {
    //         $data = $request->item_id ? Ticket::find($request->item_id) : null;

    //         $airports = Airport::orderBy('name')->get();
    //         $vendors = User::orderBy('name')->role('vendor')->get();
    //         $portals = Portal::orderBy('name')->where('status', 'active')->get();
    //         $airlines = Airline::orderBy('name')->where('status', 1)->get();

    //         if (empty($data)) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No data found!'
    //             ]);
    //         }
    //         $data['modal_view'] = view('tickets.edit-modal', [
    //             'data' => $data,
    //             'airports' => $airports,
    //             'vendors' => $vendors,
    //             'portals' => $portals,
    //             'airlines' => $airlines
    //         ])->render();

    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $th->getMessage()
    //         ]);
    //     }

    //     return response()->json([
    //         'data' => $data,
    //         'success' => true,
    //         'message' => 'Data Found Successfully.'
    //     ]);
    // }

    /**
     * Replace the route's legs with the form's list (owner, 2026-09-21): a
     * route with stops posts legs[i][from_airport_id|to_airport_id|cost],
     * in order; a direct route posts none and loses any it had. Skipped
     * silently while the ticket_route_legs table is not migrated yet, so the
     * route still saves.
     */
    /**
     * A route with a via airport (owner, 2026-09-21) is two tickets: From →
     * via and via → To, each with its own airline, vendor/portal and price.
     * The form posts via_airport_id and legs[0..1][airline_id|vendor_id|
     * portal_id|cost]; the route's own airline / seller mirror leg 1 and its
     * price is the sum, so every screen that reads the route still works.
     * No via = direct: any legs it had are removed.
     */
    // public: Ticket Entry's "+" route quick-create cuts its route the same way (2026-10-17)
    public function syncRouteLegs(Ticket $ticket, Request $request): void
    {
        if (! Ticket::legsAvailable()) {
            return;
        }
        $via = (int) $request->input('via_airport_id');
        $legs = $request->input('legs', []);
        $ticket->routeLegs()->delete();

        // Several stops (owner, 2026-10-11): route_stops[i] = {airport_id, new_ticket}. The route is
        // cut into tickets at every stop marked "new ticket"; the other stops are transits inside
        // the ticket they fall in. DAC → DOH(transit) → ESB(new ticket) → ECN = ticket 1 DAC→ESB
        // via DOH, ticket 2 ESB→ECN. No new-ticket stop = one ticket with transits.
        $stops = self::stopList($request->input('route_stops', []));
        // Round trip with a separate return (owner, 2026-10-18): after the outbound tickets come the
        // return tickets, destination → origin, cut the same way by return_stops[] — DAC-ECN-DAC =
        // ticket 1 DAC → ECN, ticket 2 ECN → DAC. legs[] posts them in that order.
        $separate = self::separateReturn($request);
        if ($stops->isNotEmpty() || $separate) {
            $segments = self::cutRoute((int) $ticket->from_airport_id, (int) $ticket->to_airport_id, $stops);
            if ($separate) {
                $segments = array_merge($segments, self::cutRoute((int) $ticket->to_airport_id, (int) $ticket->from_airport_id,
                    self::stopList($request->input('return_stops', []))));
            }
            $total = 0;
            foreach ($segments as $i => [$from, $to, $through]) {
                $leg = is_array($legs[$i] ?? null) ? $legs[$i] : [];
                $cost = isset($leg['cost']) && $leg['cost'] !== '' ? (float) $leg['cost'] : 0;
                $total += $cost;
                $ticket->routeLegs()->create([
                    'sequence' => $i + 1,
                    'from_airport_id' => $from, 'to_airport_id' => $to,
                    'airline_id' => ($leg['airline_id'] ?? null) ?: null,
                    'vendor_id' => ($leg['vendor_id'] ?? null) ?: null,
                    'portal_id' => ($leg['portal_id'] ?? null) ?: null,
                    'cost' => $cost,
                ] + (TicketRouteLeg::hasStops() ? ['stops' => $through ?: null] : []));
            }
            $ticket->update(['price' => $total]);

            return;
        }

        if (! $via || ! is_array($legs) || count($legs) < 2) {
            return;
        }
        $points = [[$ticket->from_airport_id, $via], [$via, $ticket->to_airport_id]];
        $total = 0;
        foreach ($points as $i => [$from, $to]) {
            $leg = is_array($legs[$i] ?? null) ? $legs[$i] : [];
            $cost = isset($leg['cost']) && $leg['cost'] !== '' ? (float) $leg['cost'] : 0;
            $total += $cost;
            $ticket->routeLegs()->create([
                'sequence' => $i + 1,
                'from_airport_id' => $from, 'to_airport_id' => $to,
                'airline_id' => ($leg['airline_id'] ?? null) ?: null,
                'vendor_id' => ($leg['vendor_id'] ?? null) ?: null,
                'portal_id' => ($leg['portal_id'] ?? null) ?: null,
                'cost' => $cost,
            ]);
        }
        $ticket->update(['price' => $total]);
    }

    /** route_stops[] / return_stops[] → [{id, new}] in order, empty rows dropped. */
    private static function stopList($rows): Collection
    {
        return collect(is_array($rows) ? $rows : [])
            ->filter(fn ($s) => is_array($s) && (int) ($s['airport_id'] ?? 0) > 0)
            ->map(fn ($s) => ['id' => (int) $s['airport_id'], 'new' => filter_var($s['new_ticket'] ?? false, FILTER_VALIDATE_BOOLEAN)])
            ->values();
    }

    /** One journey cut into tickets at its "new ticket" stops: [[from, to, transits], …]. */
    private static function cutRoute(int $start, int $end, Collection $stops): array
    {
        $segments = [];
        $transits = [];
        foreach ($stops as $stop) {
            if ($stop['new']) {
                $segments[] = [$start, $stop['id'], $transits];
                $start = $stop['id'];
                $transits = [];
            } else {
                $transits[] = $stop['id'];
            }
        }
        $segments[] = [$start, $end, $transits];

        return $segments;
    }

    /** A round trip whose return is its own ticket(s) — only once the trip_type column exists. */
    public static function separateReturn(Request $request): bool
    {
        return Ticket::roundTripAvailable() && $request->input('trip_type') === 'two-way' && $request->input('return_mode') === 'separate';
    }

    /** trip_type for the route row — only when the form sent it and the column exists. */
    public static function tripAttributes(Request $request): array
    {
        if (! Ticket::roundTripAvailable() || ! $request->has('trip_type')) {
            return [];
        }

        return ['trip_type' => $request->input('trip_type') === 'two-way' ? 'two-way' : 'one-way'];
    }

    /**
     * With a via, the airline / seller boxes are per leg and the route's own
     * ones are hidden — fill them from leg 1 so the existing validation
     * (vendor or portal required; the airline is optional) still applies sensibly.
     */
    // public: reused by Ticket Entry's "+" route quick-create (2026-10-17)
    public function mergeViaDefaults(Request $request): void
    {
        // several tickets: via airports, or a round trip with a separate return (2026-10-18)
        if (! $request->filled('via_airport_id') && ! self::separateReturn($request)) {
            return;
        }
        $leg = $request->input('legs.0', []);
        $legs = $request->input('legs', []);
        $request->merge([
            'has_route_legs' => 1,
            'airline_id' => ($leg['airline_id'] ?? null) ?: $request->airline_id,
            'vendor_id' => ($leg['vendor_id'] ?? null) ?: null,
            'portal_id' => ($leg['portal_id'] ?? null) ?: null,
            'price' => collect(is_array($legs) ? $legs : [])->sum(fn ($l) => (float) (($l['cost'] ?? 0) ?: 0)),
        ]);
    }
}
