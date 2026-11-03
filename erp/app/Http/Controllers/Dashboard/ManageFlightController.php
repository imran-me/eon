<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bank;
use App\Models\Company;
use App\Models\ContractFlight;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use App\Models\ContractFlightPassengerServiceCost;
use App\Models\FlightCategory;
use App\Models\FlightCategoryType;
use App\Models\FlightOfficer;
use App\Models\PassportHolder;
use App\Models\PassportHolderCategory;
use App\Models\PaymentSchedule;
use App\Models\Portal;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CompanyFunds;
use App\Services\CompanyPartyDirectory;
use App\Services\ContractFlightMessenger;
use App\Services\TicketBaggage;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsPurchaseJournal;
use App\Traits\PostsSaleJournal;
use App\Traits\RebooksContractFlightPassengers;
use App\Traits\ReconcilesContractFlightAgent;
use App\Traits\ReconcilesContractFlightChangeCharge;
use App\Traits\ReconcilesContractFlightReissue;
use App\Traits\ReconcilesContractFlightServiceCosts;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManageFlightController implements HasMiddleware
{
    use PostsPartyLedger, PostsPurchaseJournal, PostsSaleJournal;
    use RebooksContractFlightPassengers, ReconcilesContractFlightChangeCharge;
    use RebooksContractFlightPassengers, ReconcilesContractFlightChangeCharge;
    use ReconcilesContractFlightAgent, ReconcilesContractFlightReissue, ReconcilesContractFlightServiceCosts;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract flight|view all contract flight', only: ['index', 'dashboard', 'invoice', 'printPreview']),
            new Middleware('permission:create contract flight', only: ['store']),
            new Middleware('permission:edit contract flight', only: ['update']),
            new Middleware('permission:delete contract flight', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $now = now();

        $query = ContractFlight::with([
            'flightCategory', 'categoryType', 'ticket.airline', 'ticket.from_airport.country',
            'ticket.to_airport.country', 'agent', 'boardingOfficer.user',
            'immigrationOfficer.user', 'passengers.passportHolder', 'passengers.agent',
            // The passenger-wise list prints each passenger's category type
            // and each PNR's route title, so both come along in the same
            // eager load rather than one query per row.
            'passengers.flightCategory.categoryType',
            // .ticketPurchases feeds the itinerary card's readiness badge
            // (ContractFlight::getReadinessSummaryAttribute()) — without it,
            // that would silently lazy-load per row.
            'passengers.ticketPurchases.ticket',
            // Each ticket's seller, named with its code under the agent (2026-10-03).
            'passengers.ticketPurchases.vendor:id,name', 'passengers.ticketPurchases.portal:id,name',
        ])
            // Penalties on the row (2026-09-27) — only once the table exists.
            ->when(ContractFlightPassengerChange::installed(), fn ($q) => $q->with('passengers.flightChanges'))
            ->nearestFirst();

        $passengerConstraints = [];
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('flight_number', 'like', "%{$search}%")
                    ->orWhere('airline_flight_no', 'like', "%{$search}%")
                    ->orWhereHas('ticket', fn ($t) => $t->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('ticket.airline', fn ($a) => $a->where('name', 'like', "%{$search}%"))
                  // The list is passenger-wise, so a passenger's name,
                  // passport, agent or PNR must find their flight too.
                    ->orWhereHas('passengers.agent', fn ($a) => $a->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('passengers.passportHolder', fn ($h) => $h->where('name', 'like', "%{$search}%")->orWhere('passport_no', 'like', "%{$search}%"))
                    ->orWhereHas('passengers.ticketPurchases', fn ($t) => $t->where('ticket_no', 'like', "%{$search}%"));
            });

            // Which passengers of those flights to show (owner, 2026-09-27:
            // searching a name listed his whole flight, him third). A search
            // that matched the PERSON — name, passport, agent, PNR — shows only
            // the matching people; one that matched the FLIGHT itself — number,
            // airline, route — still shows everybody on it.
            $passengerConstraints[] = function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->whereHas('passportHolder', fn ($h) => $h->where('name', 'like', "%{$search}%")->orWhere('passport_no', 'like', "%{$search}%"))
                        ->orWhereHas('agent', fn ($a) => $a->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('ticketPurchases', fn ($t) => $t->where('ticket_no', 'like', "%{$search}%"))
                        ->orWhereHas('contractFlight', fn ($f) => $f->where('flight_number', 'like', "%{$search}%")
                            ->orWhere('airline_flight_no', 'like', "%{$search}%")
                            ->orWhereHas('ticket', fn ($t) => $t->where('title', 'like', "%{$search}%"))
                            ->orWhereHas('ticket.airline', fn ($a) => $a->where('name', 'like', "%{$search}%")));
                });
            };
        }

        if ($request->filled('month')) {
            [$year, $month] = explode('-', $request->month);
            $query->whereYear('departure_at', $year)->whereMonth('departure_at', $month);
        }

        // The list bar's date range, on the departure day.
        if ($request->filled('from_date')) {
            $query->whereDate('departure_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('departure_at', '<=', $request->to_date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('flight_category_id')) {
            $query->where('flight_category_id', $request->flight_category_id);
        }

        if ($request->filled('agent_id')) {
            // Agent is now set per passenger, not on the flight batch itself —
            // "this agent's flights" means any flight carrying at least one of
            // their passengers.
            $query->whereHas('passengers', fn ($q) => $q->where('agent_id', $request->agent_id));
        }

        // ── Passenger KPI cards ──
        // Each card is a passenger-level condition; clicking one narrows the
        // list to flights carrying such a passenger AND to those passengers
        // only. The count is over the whole company, not the current page.
        $paxScopes = self::passengerKpiScopes();
        $paxFilter = $request->filled('pax') && isset($paxScopes[$request->pax]) ? $request->pax : null;
        if ($paxFilter) {
            $query->whereHas('passengers', $paxScopes[$paxFilter]['where']);
            $passengerConstraints[] = $paxScopes[$paxFilter]['where'];
        }
        // Offloaded / deported passengers show only under their own card (owner, 2026-10-10:
        // "offload will show only after clicking that"). A search still finds them.
        if (! $paxFilter && ! $request->filled('search')) {
            $offloadedFlags = array_merge(
                ContractFlightPassenger::OFFLOAD_KPI_FLAGS,
                ContractFlightPassenger::DEPORTED_KPI_FLAGS,
            );
            $passengerConstraints[] = fn ($q) => $q->where(fn ($w) => $w->whereNull('exception_flag')->orWhereNotIn('exception_flag', $offloadedFlags));
        }
        // Search and KPI card together narrow the rows shown under each flight.
        if ($passengerConstraints) {
            $query->with(['passengers' => function ($q) use ($passengerConstraints) {
                foreach ($passengerConstraints as $constraint) {
                    $q->where($constraint);
                }
            }]);
        }

        $datas = $query->paginate(15)->withQueryString();

        $paxKpis = [];
        foreach ($paxScopes as $key => $scope) {
            $paxKpis[$key] = $scope + ['count' => ContractFlightPassenger::where($scope['where'])->count()];
            unset($paxKpis[$key]['where']);
        }

        // ── MTD stats ──
        $mtd = ContractFlight::whereYear('departure_at', $now->year)->whereMonth('departure_at', $now->month);
        $lastMonth = (clone $now)->subMonth();
        $prevMtd = ContractFlight::whereYear('departure_at', $lastMonth->year)->whereMonth('departure_at', $lastMonth->month);

        $revenueMtd = (clone $mtd)->sum('revenue');
        $revenuePrev = (clone $prevMtd)->sum('revenue');
        $revenueTrend = $revenuePrev > 0 ? round((($revenueMtd - $revenuePrev) / $revenuePrev) * 100) : null;

        // Heads actually on this month's flights. seats_sold used to fill this
        // card, but it counts seat-block bookings — on an agent-wise batch it
        // is zero however many people are flying, which read as "nobody
        // travelled" on a month full of departures.
        $passengersMtd = ContractFlightPassenger::whereHas(
            'contractFlight',
            fn ($q) => $q->whereYear('departure_at', $now->year)->whereMonth('departure_at', $now->month)
        )->count();

        $stats = [
            'total_flights_mtd' => (clone $mtd)->count(),
            'revenue_mtd' => $revenueMtd,
            'revenue_trend' => $revenueTrend,
            'passengers_mtd' => $passengersMtd,
            'net_profit_mtd' => $revenueMtd - (clone $mtd)->sum('cost_price'),
            'month_label' => $now->format('F Y'),
        ];

        // Off-flight passengers belong to no flight, so the flight-wise list
        // above can never show them: the Cancelled card lists them first, on
        // their own rows, with Re-assign / Undo.
        $offFlight = $paxFilter === 'cancelled' ? $this->offFlightPassengers($request) : collect();

        return view('contract-flights.index', compact('datas', 'stats', 'paxKpis', 'paxFilter', 'offFlight') + $this->flightFormData());
    }

    /**
     * Passengers taken off their flight and waiting to be re-assigned,
     * latest first, narrowed by the list bar's search and agent. Empty (never
     * an error) until the changes migration has run and its routes exist.
     */
    private function offFlightPassengers(Request $request)
    {
        if (! ContractFlightPassengerChange::available()) {
            return collect();
        }
        try {
            return ContractFlightPassenger::query()
                ->whereNull('contract_flight_id')
                ->where('exception_flag', ContractFlightPassenger::OFF_FLIGHT_FLAG)
                ->with(['passportHolder', 'agent', 'flightCategory.categoryType', 'ticketPurchases',
                    'lastCancellation.fromFlight.ticket.from_airport', 'lastCancellation.fromFlight.ticket.to_airport', 'lastCancellation.creator:id,name'])
                ->when($request->filled('agent_id'), fn ($q) => $q->where('agent_id', $request->agent_id))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $s = $request->search;
                    $q->where(fn ($w) => $w->whereHas('passportHolder', fn ($h) => $h->where('name', 'like', "%{$s}%")->orWhere('passport_no', 'like', "%{$s}%"))
                        ->orWhereHas('agent', fn ($a) => $a->where('name', 'like', "%{$s}%"))
                        ->orWhereHas('ticketPurchases', fn ($t) => $t->where('ticket_no', 'like', "%{$s}%")));
                })
                ->orderByDesc('updated_at')
                ->limit(200)
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * The lists the Add / Edit Flight modals and their passenger builder are
     * built from. Flights & Passengers and the Flight Dashboard both host the
     * Add Flight modal (owner, 2026-09-21: "Add Flight" on the dashboard must
     * open the modal there, not jump to the other tab first).
     */
    private function flightFormData(): array
    {
        return [
            // Contract Flight is a Travels operation even when another group
            // company's user opens Flights & Passengers and its print sheet.
            'company' => Company::find(CompanyFunds::COMPANY_ID) ?? Company::first(),
            'categories' => FlightCategory::with('categoryType')->orderBy('name')->get(),
            'categoryTypes' => FlightCategoryType::where('status', 'active')->orderBy('name')->get(),
            'agents' => CompanyPartyDirectory::members(CompanyFunds::COMPANY_ID, 'agent')->get(['id', 'name']),
            'tickets' => Ticket::with('airline', 'from_airport', 'to_airport')->orderBy('title')->get(),
            // for the inline "+ New Route" quick-create form (reuses Ticket creation)
            'airports' => Airport::orderBy('name')->get(),
            'airlines' => Airline::orderBy('name')->where('status', 1)->get(),
            'vendors' => CompanyPartyDirectory::members(CompanyFunds::COMPANY_ID, 'vendor')->get(),
            'banks' => Bank::where('company_id', CompanyFunds::COMPANY_ID)->where('status', 1)->get(),
            'portals' => Portal::orderBy('name')->where('status', 'active')->get(),
            'officers' => FlightOfficer::with(['user', 'airline'])->where('status', 'active')->orderBy('id')->get(),
            'passportHolders' => PassportHolder::with('category')->orderBy('name')->get(),
            'passportHolderCategories' => PassportHolderCategory::orderBy('name')->get(),
            // the "Notify boss / user" list of a hold issued from a passenger card (same as Ticket Entry)
            'bookingRecipients' => $recipients = User::where('status', 'active')
                ->where('company_id', CompanyFunds::COMPANY_ID)->orderBy('name')->get(),
            'bookingBoss' => User::where('name', 'Md Mohsin')->where('status', 'active')->first(),
        ];
    }

    /**
     * The six passenger KPI cards on Flights & Passengers, each a condition
     * on contract_flight_passengers. Keyed by the ?pax= value the card links
     * to. "Pending" counts until a ticket is CONFIRMED — a linked but
     * unconfirmed PNR is still pending — and stops counting once the person
     * is cancelled, offloaded or the flight itself is no longer live.
     */
    public static function passengerKpiScopes(): array
    {
        // One status per passenger, read in this order (kpiKey() on the
        // model is the twin — change one, change the other):
        //   broken  = cancelled, offload or deported flag — out of every live card;
        //   flown   = own stage Departed or later, or the flight departed;
        //   live    = on an open/boarding flight, not broken, not flown.
        // So Active = Pending + Ticketed, and nobody is both Active and Departed.
        // The stage counts because it is moved per person, while the flight
        // status is changed by hand and often left at open.
        $flownStages = ContractFlightPassenger::FLOWN_STAGES;
        $offloadFlags = ContractFlightPassenger::OFFLOAD_KPI_FLAGS;
        $deportedFlags = ContractFlightPassenger::DEPORTED_KPI_FLAGS;
        $broken = array_merge($offloadFlags, $deportedFlags, ['flight_cancelled', ContractFlightPassenger::OFF_FLIGHT_FLAG]);
        $notBroken = fn ($q) => $q->where(fn ($w) => $w->whereNull('exception_flag')->orWhereNotIn('exception_flag', $broken));
        $live = fn ($q) => $notBroken($q)
            ->whereHas('contractFlight', fn ($f) => $f->whereIn('status', ['open', 'boarding']))
            ->where(fn ($w) => $w->whereNull('stage')->orWhereNotIn('stage', $flownStages));

        return [
            'active' => [
                'label' => 'Active Passengers', 'hint' => 'On open or boarding flights, not yet departed', 'icon' => 'fa-users', 'tone' => 'indigo',
                'where' => fn ($q) => $live($q),
            ],
            'pending' => [
                'label' => 'Pending Ticket', 'hint' => 'One or more legs not yet confirmed', 'icon' => 'fa-hourglass-half', 'tone' => 'amber',
                'where' => fn ($q) => $live($q)->whereRaw('NOT ('.ContractFlightPassenger::fullyTicketedSql().')'),
            ],
            'ticketed' => [
                'label' => 'Ticketed', 'hint' => 'Active, every leg has a confirmed PNR', 'icon' => 'fa-ticket', 'tone' => 'emerald',
                'where' => fn ($q) => $live($q)->whereRaw(ContractFlightPassenger::fullyTicketedSql()),
            ],
            // Money, across every status — except a passenger whose charge is
            // voided (cancelled, deported, off flight): he owes nothing.
            'due' => [
                'label' => 'Due Passengers', 'hint' => 'Agent charge not fully paid', 'icon' => 'fa-money-bill-wave', 'tone' => 'rose',
                'where' => fn ($q) => $q->whereNotNull('contract_flight_id')
                    ->where(fn ($w) => $w->whereNull('exception_flag')->orWhereNotIn('exception_flag', ContractFlightPassenger::AGENT_CHARGE_VOIDING_FLAGS))
                    ->whereRaw('COALESCE(agent_price, 0) - '.ContractFlightPassenger::priceRevisionSql().' + '.ContractFlightPassenger::billedChangesSql().' > COALESCE(agent_paid_amount, 0) + 0.005'),
            ],
            'cancelled' => [
                'label' => 'Cancelled', 'hint' => 'Taken off a flight, or the flight was cancelled', 'icon' => 'fa-ban', 'tone' => 'red',
                'where' => fn ($q) => $q->where(fn ($w) => $w->whereIn('exception_flag', ['flight_cancelled', ContractFlightPassenger::OFF_FLIGHT_FLAG])
                    ->orWhereHas('contractFlight', fn ($f) => $f->where('status', 'cancelled'))),
            ],
            'offloaded' => [
                'label' => 'Offload', 'hint' => 'Immigration or boarding offload in our country', 'icon' => 'fa-user-slash', 'tone' => 'slate',
                'where' => fn ($q) => $q->whereIn('exception_flag', $offloadFlags),
            ],
            'deported' => [
                'label' => 'Deported', 'hint' => 'Sent back from the destination country', 'icon' => 'fa-plane-departure', 'tone' => 'slate',
                'where' => fn ($q) => $q->whereIn('exception_flag', $deportedFlags),
            ],
            'departed' => [
                'label' => 'Departed', 'hint' => 'Stage Departed or later, or the flight departed', 'icon' => 'fa-plane-departure', 'tone' => 'sky',
                'where' => fn ($q) => $notBroken($q)->whereNotNull('contract_flight_id')
                    ->whereHas('contractFlight', fn ($f) => $f->where('status', '!=', 'cancelled'))
                    ->where(fn ($w) => $w->whereIn('stage', $flownStages)
                        ->orWhereHas('contractFlight', fn ($f) => $f->where('status', 'departed'))),
            ],
            'flagged' => [
                'label' => 'Rescheduled / Transit', 'hint' => 'Rescheduled, time changed or in transit', 'icon' => 'fa-shuffle', 'tone' => 'violet',
                'where' => fn ($q) => $q->whereIn('exception_flag', ['rescheduled', 'time_changed', 'transit']),
            ],
        ];
    }

    /**
     * Upcoming batches stay on the wall; past unfinished departures need an
     * update in Overdue. Physical progress and passenger exceptions determine
     * the remaining sections, independently of a stale open flight status.
     */
    public function dashboard()
    {
        $flights = ContractFlight::with([
            'ticket.airline', 'ticket.from_airport.country', 'ticket.to_airport.country',
            'agent', 'vendor', 'passengers.passportHolder', 'passengers.agent',
            'passengers.flightCategory', 'passengers.ticketPurchases.vendor:id,name',
        ])
            ->whereIn('status', ['open', 'boarding', 'departed'])
            ->orderByRaw('departure_at IS NULL, departure_at ASC')
            ->get();

        // A flight everyone was moved off (re-assigned / cancelled) is not a batch any more
        // (owner, 2026-11-01: Kuddush moved CF-056 → CF-057, yet the Gulf Air card still said
        // "2 flights · next 4 Nov" from the empty CF-056). Left off the dashboard; it stays in
        // Flights & Passengers to close or delete. A new flight still waiting for its first
        // passenger has no one who left it, so it stays on the wall.
        try {
            $empty = $flights->filter(fn (ContractFlight $f) => $f->passengers->isEmpty())->pluck('id');
            if ($empty->isNotEmpty() && ContractFlightPassengerChange::installed()) {
                $left = ContractFlightPassengerChange::whereIn('from_flight_id', $empty)
                    ->where(fn ($q) => $q->whereNull('to_flight_id')->orWhereColumn('to_flight_id', '!=', 'from_flight_id'))
                    ->pluck('from_flight_id')->map(fn ($id) => (int) $id)->unique()->all();
                $flights = $flights->reject(fn (ContractFlight $f) => in_array((int) $f->id, $left, true))->values();
            }
        } catch (\Throwable) {
            // no change history on this server — every flight shows as before
        }

        $now = now();
        $groups = collect(['active', 'overdue', 'offloaded', 'departed'])
            ->mapWithKeys(fn ($section) => [$section => $flights->filter(
                fn (ContractFlight $f) => in_array($section, $f->dashboardSections($now), true)
            )]);
        $flights = $groups['active'];
        $cards = $flights->map(fn (ContractFlight $f) => $this->dashboardCard($f))->values();
        $historyCards = $groups->except('active')->map(fn ($items) => $items
            ->sortByDesc(fn (ContractFlight $f) => $f->departure_at?->getTimestamp() ?? 0)
            ->map(fn (ContractFlight $f) => $this->dashboardCard($f))->values());
        $company = Company::find(CompanyFunds::COMPANY_ID) ?? Company::first();

        $kpis = [
            'flights' => $flights->count(),
            'passengers' => (int) $flights->sum(fn (ContractFlight $f) => $f->passengers->count()),
            'airlines' => $flights->map(fn (ContractFlight $f) => $f->ticket?->airline_id)->filter()->unique()->count(),
            'vendor_due' => (float) $flights->sum('due_amount'),
            'vendors' => $flights->pluck('vendor_id')->filter()->unique()->count(),
        ];

        return view('contract-flights.dashboard', compact('cards', 'historyCards', 'company', 'kpis') + $this->flightFormData());
    }

    /** A named page for the shared print designer, like Party Statement. */
    public function printPreview()
    {
        return view('contract-flights.print-preview');
    }

    /**
     * Every passenger on the batch has reached Case Closed. A batch with no
     * passengers is not closed — there is nothing on it to have finished.
     * 'case_closed' is a system stage, so its key cannot be renamed from the
     * stage editor.
     */
    private function isCaseClosed(ContractFlight $f): bool
    {
        return $f->passengers->isNotEmpty()
            && $f->passengers->every(fn ($p) => $p->stage === 'case_closed');
    }

    /**
     * Who brought this batch. The passengers' agents where any are set (the
     * same reading as ContractFlight::getAgentSummaryAttribute()), otherwise
     * the agent picked on the flight itself — a batch whose passengers have
     * not been entered yet still has an agent, and the card must not call it
     * "Direct" (CF-004, 2026-09-16). Null only when nobody is on record.
     */
    private function dashboardAgent(ContractFlight $f): ?string
    {
        $names = $f->passengers->map(fn ($p) => $p->agent?->name)->filter()->unique()->values();

        if ($names->isEmpty()) {
            return $f->agent?->name;
        }

        return $names->count() > 1
            ? $names->first().' +'.($names->count() - 1).' more'
            : $names->first();
    }

    /**
     * Every airline carrying this batch, in leg order — a via route is two
     * tickets, each on its own airline (owner, 2026-09-21: the card then
     * shows both marks, one on the other, and both names). Distinct, so two
     * legs on the same carrier still read as one airline. A direct route, or
     * one whose legs carry no airline yet, falls back to the route's own.
     * legsArray() is guarded, so a server without ticket_route_legs reads
     * every route as direct.
     */
    private function dashboardAirlines(ContractFlight $f): array
    {
        $names = collect($f->ticket?->legsArray() ?? [])->pluck('airline')->filter()->unique()->values();

        if ($names->isEmpty() && $f->ticket?->airline?->name) {
            $names = collect([$f->ticket->airline->name]);
        }

        return $names->all();
    }

    /**
     * One flight, flattened for the dashboard card. Everything the card prints
     * or sorts on is here so the view's script never reaches into a model, and
     * the airports carry their ISO-2 country code because that is the flag file
     * name (public/flags/bd.png). Reads only what dashboard() eager-loaded.
     */
    private function dashboardCard(ContractFlight $f): array
    {
        $airport = function ($a) {
            if (! $a) {
                return null;
            }

            return [
                'code' => $a->code,
                'name' => $a->name,
                'country' => strtolower((string) ($a->country?->code ?? '')),
                'country_name' => $a->country?->name,
            ];
        };

        $r = $f->readiness_summary;
        $agents = $f->passengers->pluck('agent')->filter()->unique('id')->values();
        if ($agents->isEmpty() && $f->agent) {
            $agents->push($f->agent);
        }

        return [
            'id' => $f->id,
            'flight_number' => $f->flight_number,
            'airline_flight_no' => $f->airline_flight_no,
            'status' => $f->status,
            'case_closed' => $this->isCaseClosed($f),
            'airline' => $f->ticket?->airline?->name,
            'airlines' => $this->dashboardAirlines($f),
            'from' => $airport($f->ticket?->from_airport),
            'to' => $airport($f->ticket?->to_airport),
            // The whole path when the route goes via somewhere — "DAC → IST →
            // ECN". From/to alone read as a direct flight and hide the change
            // of plane the batch is actually built around. Null on a direct
            // route, where the from → to line above already says everything.
            // legsArray() carries its own guard: a server without the legs
            // table reads every route as direct rather than erroring.
            // Route with every hop and its country, so the passenger sheet
            // can flag the VIA airport too — from/to alone leave the middle
            // of a via route unflagged. Both live on the model, because the
            // Flights & Passengers rows need the same answer.
            'route_path' => $f->routePath(),
            'route_hops' => $f->routeHops(),
            'departure_at' => $f->departure_at?->toIso8601String(),
            'arrival_at' => $f->arrival_at?->toIso8601String(),
            'ticket_class' => $f->ticket_class,
            'passengers' => $r['total'],
            'passenger_details' => $f->passengers->map(fn (ContractFlightPassenger $passenger) => [
                'id' => $passenger->id,
                'name' => $passenger->passportHolder?->name ?: 'Passenger not assigned',
                'passport_no' => $passenger->passportHolder?->passport_no,
                'agent' => $passenger->agent?->name ?: $f->agent?->name ?: 'Direct',
                'agent_display_id' => $passenger->agent
                    ? 'EP-AG-'.str_pad($passenger->agent->id, 3, '0', STR_PAD_LEFT)
                    : ($f->agent ? 'EP-AG-'.str_pad($f->agent->id, 3, '0', STR_PAD_LEFT) : null),
                'category' => $passenger->flightCategory?->name,
                'ticket_progress' => $passenger->confirmedTicketCount().'/'.$passenger->ticketsRequired().' ticketed',
                'pnrs' => $passenger->ticketPurchases->where('status', '!=', 'cancelled')
                    ->pluck('ticket_no')->filter()->values()->all(),
                'stage' => ContractFlightPassenger::stageLabel($passenger->stage),
                'exception' => ContractFlightPassenger::EXCEPTION_FLAGS[$passenger->exception_flag] ?? null,
            ])->values()->all(),
            'offloaded_count' => $f->passengers->whereIn('exception_flag', [
                'boarding_denied', 'immigration_offload', 'deported', ContractFlightPassenger::DEPORTED_BORNE_FLAG,
            ])->count(),
            'ticketed' => $r['ticketed'],
            'flagged' => $r['flagged'],
            'needs_immigration_officer' => $r['needs_immigration_officer'],
            'agent' => $this->dashboardAgent($f),
            'agents' => $agents->map(fn ($agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'display_id' => 'EP-AG-'.str_pad($agent->id, 3, '0', STR_PAD_LEFT),
            ])->values()->all(),
            'category' => $f->category_summary,
            // The batch vendor is who we pay the flight cost to. When none is
            // set, the tickets' vendors still tell the story — "Not assigned"
            // on a batch whose PNRs all name a vendor reads as a data gap.
            'vendor' => $f->vendor?->name,
            'vendor_display_id' => $f->vendor ? 'EP-VN-'.str_pad($f->vendor->id, 3, '0', STR_PAD_LEFT) : null,
            'vendor_id' => $f->vendor?->id,
            'ticket_vendors' => $f->passengers->flatMap->ticketPurchases->where('status', '!=', 'cancelled')
                ->pluck('vendor')->filter()->unique('id')->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->values()->all(),
            'revenue' => (float) $f->total_agent_charge,
            'cost' => (float) $f->total_travel_cost,
            'vendor_due' => (float) $f->due_amount,
            'payable_date' => $f->payable_date?->toDateString(),
            'total_seats' => (int) $f->total_seats,
        ];
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
                // Category, class and handling are per passenger now, but the
                // flight's own columns are NOT NULL and still feed the rate
                // presets and the list filter. Seed them from the first
                // passenger row; applyPassengerDerivedTotals() settles them
                // properly once the passengers exist.
                $firstRow = collect($request->input('passengers', []))->first() ?: [];

                $flight = ContractFlight::create([
                    'flight_number' => ContractFlight::nextFlightNumber(),
                    'flight_category_id' => $request->flight_category_id ?: ($firstRow['flight_category_id'] ?? null),
                    'flight_category_type_id' => $request->flight_category_type_id,
                    'handling_type' => $request->handling_type ?: ($firstRow['handling_type'] ?? 'manpower_wise'),
                    'ticket_class' => $request->ticket_class ?: ($firstRow['ticket_class'] ?? 'economy'),
                    'ticket_id' => $request->ticket_id,
                    'airline_flight_no' => $request->airline_flight_no,
                    'departure_at' => $request->departure_at,
                    'arrival_at' => $request->arrival_at,
                    // Seats and the per-pax money are settled by
                    // applyPassengerDerivedTotals() once the passengers exist —
                    // they cannot be counted before syncPassengers() has run.
                    'total_seats' => $request->total_seats ?? 0,
                    'seats_sold' => $request->seats_sold ?? 0,
                    'cost_price' => 0,
                    'revenue' => 0,
                    // A batch is booked agent-wise: one agent brings the whole
                    // group, so the agent is chosen once on the flight and
                    // stamped onto every passenger the builder submits. The
                    // per-passenger agent_id stays the billing key (the Party
                    // Statement and the agent report both read it there), so a
                    // mixed batch can still be corrected passenger by
                    // passenger in the passenger edit modal.
                    'agent_id' => $request->agent_id ?: null,
                    // The batch's ticket seller — one airline, one vendor or
                    // portal — collected on step 1 again since 2026-09-21 and
                    // offered as the default on every passenger's new ticket.
                    'vendor_id' => $request->vendor_id ?: null,
                    'portal_id' => $request->portal_id ?: null,
                    // cost_bank_id/cost_paid_amount/purchase_date/payable_date
                    // deliberately omitted — the form no longer collects them
                    // (cost tracking moved to the linked TicketPurchase and
                    // Party Statement). A new flight simply has none set.
                    'boarding_officer_id' => $request->boarding_officer_id ?: null,
                    // Kept whenever one is chosen: immigration support is now a
                    // per-passenger decision, so the batch default no longer
                    // says whether an officer is involved.
                    'immigration_officer_id' => $request->immigration_officer_id ?: null,
                    'ticket_cost_per_pax' => $request->ticket_cost_per_pax ?? 0,
                    'manpower_cost_per_pax' => $request->manpower_cost_per_pax ?? 0,
                    'boarding_cost_per_pax' => $request->boarding_cost_per_pax ?? 0,
                    'immigration_cost_per_pax' => $request->handling_type === 'immigration_wise' ? ($request->immigration_cost_per_pax ?? 0) : 0,
                    'sale_price_per_pax' => $request->sale_price_per_pax ?? 0,
                    'status' => $request->status,
                    'notes' => $request->notes,
                    'created_by' => Auth::id(),
                ]);
                $this->syncPassengers($flight, $request);
                $this->applyPassengerDerivedTotals($flight, $request);
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
        if (! $data) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!',
            ]);
        }

        $validator = $this->validator($request, (int) $data->id);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            $scheduleBefore = [$data->departure_at?->toDateTimeString(), $data->arrival_at?->toDateTimeString()];
            DB::transaction(function () use ($data, $request) {
                // Category, class and handling now live on each passenger and
                // the modal no longer posts the old flight-level controls.
                // The parent columns are still NOT NULL and are used as the
                // list/preset summary, so seed them exactly as store() does:
                // an explicitly submitted flight value wins, then the first
                // passenger, then the flight's current value for older callers.
                $firstRow = collect($request->input('passengers', []))->first() ?: [];
                $flightCategoryId = $request->input('flight_category_id')
                    ?: ($firstRow['flight_category_id'] ?? $data->flight_category_id);
                $handlingType = $request->input('handling_type')
                    ?: ($firstRow['handling_type'] ?? $data->handling_type ?? 'manpower_wise');
                $ticketClass = $request->input('ticket_class')
                    ?: ($firstRow['ticket_class'] ?? $data->ticket_class ?? 'economy');

                $data->update([
                    'flight_category_id' => $flightCategoryId,
                    'flight_category_type_id' => $request->flight_category_type_id,
                    'handling_type' => $handlingType,
                    'ticket_class' => $ticketClass,
                    'ticket_id' => $request->ticket_id,
                    'airline_flight_no' => $request->airline_flight_no,
                    'departure_at' => $request->departure_at,
                    'arrival_at' => $request->arrival_at,
                    // Only written when the caller actually sent them; the
                    // modal no longer does, and blanking a flight's seat block
                    // on an unrelated edit would break the sales screen's
                    // overbooking guard. Recomputed below from the passengers.
                    'total_seats' => $request->has('total_seats') ? $request->total_seats : $data->total_seats,
                    'seats_sold' => $request->has('seats_sold') ? ($request->seats_sold ?? 0) : $data->seats_sold,
                    // The flight-level agent IS collected again (see store()),
                    // so it is written — but only when the form actually sent
                    // the field. A caller that never rendered the agent
                    // dropdown must not blank an existing flight's agent.
                    'agent_id' => $request->has('agent_id') ? ($request->agent_id ?: null) : $data->agent_id,
                    // Same rule for the ticket seller: written only when the
                    // form carried the field (the modal always does).
                    'vendor_id' => $request->has('vendor_id') ? ($request->vendor_id ?: null) : $data->vendor_id,
                    'portal_id' => $request->has('portal_id') ? ($request->portal_id ?: null) : $data->portal_id,
                    // cost_bank_id/cost_paid_amount/
                    // purchase_date/payable_date deliberately NOT written here —
                    // the form no longer collects them, and a flight edited
                    // before this deploy may still carry real historical values
                    // on those columns. Including them here (even as $request->x,
                    // which is always null now) would silently null out that
                    // data on the very next edit. Leave whatever is already
                    // there alone.
                    'boarding_officer_id' => $request->boarding_officer_id ?: null,
                    // Kept whenever one is chosen: immigration support is now a
                    // per-passenger decision, so the batch default no longer
                    // says whether an officer is involved.
                    'immigration_officer_id' => $request->immigration_officer_id ?: null,
                    'ticket_cost_per_pax' => $request->ticket_cost_per_pax ?? 0,
                    'manpower_cost_per_pax' => $request->manpower_cost_per_pax ?? 0,
                    'boarding_cost_per_pax' => $request->boarding_cost_per_pax ?? 0,
                    'immigration_cost_per_pax' => $handlingType === 'immigration_wise' ? ($request->immigration_cost_per_pax ?? 0) : 0,
                    'sale_price_per_pax' => $request->sale_price_per_pax ?? 0,
                    'status' => $request->status,
                    'notes' => $request->notes,
                ]);
                $this->syncPassengers($data, $request);
                $this->applyPassengerDerivedTotals($data, $request);
                $this->syncPaymentSchedules($data);
            });

            // Departure or arrival moved: everyone on the batch hears about
            // it, if the Messages rule for "rescheduled" is on. After the
            // transaction, so a gateway hiccup can never roll the edit back.
            $fresh = $data->fresh();
            // A route chosen on a later edit confirms it for everyone aboard.
            if ($fresh) {
                foreach ($fresh->passengers()->with(['contractFlight', 'ticketPurchases'])->get() as $pax) {
                    $this->advanceDerivedStage($pax);
                }
            }
            if ($fresh && [$fresh->departure_at?->toDateTimeString(), $fresh->arrival_at?->toDateTimeString()] !== $scheduleBefore && $scheduleBefore[0] !== null) {
                ContractFlightMessenger::autoForFlight('rescheduled', $fresh, 'Previously '.\Illuminate\Support\Carbon::parse($scheduleBefore[0])->format('d M Y, h:i A').'.');
            }
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
            if (! $item) {
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
                // Clear what each passenger's agent was billed before the
                // flight goes. The flight soft-deletes, but its passengers are
                // hard-deleted by the cascade, so nothing would be left
                // pointing at those Party Statement debits — the agent would
                // stay invoiced for a flight that no longer exists.
                $this->removePassengers($item->passengers()->get());
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
            'bank_id' => 'required|exists:banks,id',
            'payment_date' => 'required|date',
            'reference_no' => 'nullable|string|max:255',
            'payment_amount' => 'required|numeric|min:0.01',
            'schedule_id' => 'nullable|exists:payment_schedules,id',
        ]);

        if ($validated['payment_amount'] > (float) $contractFlight->due_amount) {
            $message = 'Payment amount cannot exceed due amount!';

            return $request->ajax()
                ? response()->json(['success' => false, 'message' => $message])
                : redirect()->back()->with('error', $message);
        }

        DB::transaction(function () use ($validated, $contractFlight) {
            $bank = Bank::find($validated['bank_id']);
            if (! $bank || ! $bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }

            $newCostPaid = min((float) $contractFlight->cost_paid_amount + $validated['payment_amount'], (float) $contractFlight->cost_price);
            $newDue = max(0, (float) $contractFlight->cost_price - $newCostPaid);

            $this->reconcileCostPayment('contract_flight', $contractFlight->id, $bank->id, null, $newCostPaid, $contractFlight->vendor_id, $validated['payment_date']);

            $this->postPurchaseJournalPayment(
                'contract_flight',
                $contractFlight->id,
                CompanyFunds::COMPANY_ID,
                $validated['payment_date'],
                $validated['reference_no'] ?? $contractFlight->flight_number,
                'Flight vendor payment made — '.$contractFlight->flight_number,
                (float) $validated['payment_amount'],
                $bank->account_id
            );

            $contractFlight->forceFill(['due_amount' => $newDue, 'cost_paid_amount' => $newCostPaid])->saveQuietly();

            $scheduleQuery = PaymentSchedule::where('schedulable_type', ContractFlight::class)
                ->where('schedulable_id', $contractFlight->id)
                ->where('type', 'pay');

            if (! empty($validated['schedule_id'])) {
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
        $contractFlight->load(['flightCategory', 'categoryType', 'ticket.airline', 'ticket.from_airport', 'ticket.to_airport', 'agent', 'boardingOfficer.user', 'immigrationOfficer.user', 'passengers.passportHolder', 'passengers.agent', 'passengers.flightCategory', 'passengers.ticketPurchases']);
        $company = Company::find(CompanyFunds::COMPANY_ID) ?? Company::first();

        return view('contract-flights.invoice', [
            'flight' => $contractFlight,
            'company' => $company,
        ]);
    }

    private function validator(Request $request, ?int $flightId = null)
    {
        $validator = Validator::make($request->all(), [
            'flight_category_id' => 'nullable|exists:flight_categories,id',
            'flight_category_type_id' => 'nullable|exists:flight_category_types,id',
            'handling_type' => 'nullable|in:manpower_wise,immigration_wise',
            'ticket_class' => 'nullable|in:economy,business,first',
            // Nothing about the flight is needed up front — a batch is opened
            // the day the agent hands over passports, before the route, the
            // flight number or even the date are known (the date comes with
            // the ticket). They arrive later and the passenger pipeline moves
            // forward as they do (syncDerivedStage()).
            'ticket_id' => 'nullable|exists:tickets,id',
            'airline_flight_no' => 'nullable|string|max:50',
            'departure_at' => 'nullable|date',
            // Time is optional on both (2026-09-30): when either is a date
            // alone, only the day is compared — "arrives 4 Oct" is not before
            // "departs 4 Oct 10:00".
            'arrival_at' => ['nullable', 'date', function ($attribute, $value, $fail) use ($request) {
                $departure = $request->input('departure_at');
                if (! $departure || ! strtotime((string) $departure) || ! strtotime((string) $value)) {
                    return;
                }
                $hasTime = fn ($v) => (bool) preg_match('/\d:\d\d/', (string) $v);
                $arrives = Carbon::parse($value);
                $departs = Carbon::parse($departure);
                $before = ($hasTime($value) && $hasTime($departure))
                    ? $arrives->lt($departs)
                    : $arrives->toDateString() < $departs->toDateString();
                if ($before) {
                    $fail('The arrival must be on or after the departure.');
                }
            }],
            // Both are derived from the passenger list now, so the form no
            // longer sends them. Still accepted (and still range-checked) for
            // any caller that does supply them.
            'total_seats' => 'nullable|integer|min:0',
            'seats_sold' => 'nullable|integer|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'revenue' => 'nullable|numeric|min:0',
            'agent_id' => 'nullable|exists:users,id',
            'vendor_id' => 'nullable|exists:users,id',
            'portal_id' => 'nullable|exists:portals,id',
            'cost_bank_id' => 'nullable|exists:banks,id',
            'cost_paid_amount' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'payable_date' => 'nullable|date',
            'boarding_officer_id' => 'nullable|exists:flight_officers,id',
            // Not required_if on the flight's own handling_type any more —
            // that is only the batch default now. The real test is whether any
            // passenger actually takes immigration support (added below).
            'immigration_officer_id' => 'nullable|exists:flight_officers,id',
            'ticket_cost_per_pax' => 'nullable|numeric|min:0',
            'manpower_cost_per_pax' => 'nullable|numeric|min:0',
            'boarding_cost_per_pax' => 'nullable|numeric|min:0',
            'immigration_cost_per_pax' => 'nullable|numeric|min:0',
            'sale_price_per_pax' => 'nullable|numeric|min:0',
            // Legacy shape, still accepted: a bare list of passport holders.
            // Any caller that has not moved to the per-passenger builder
            // (and any saved-but-unmigrated form) keeps working unchanged.
            'passenger_ids' => 'nullable|array',
            'passenger_ids.*' => 'distinct|exists:passport_holders,id',

            // Current shape: one row per passenger, carrying that person's own
            // ticket links and costs.
            'passengers' => 'nullable|array',
            'passengers.*.passport_holder_id' => 'required|distinct|exists:passport_holders,id',
            'passengers.*.ticket_class' => 'nullable|in:economy,business,first',
            'passengers.*.handling_type' => 'nullable|in:manpower_wise,immigration_wise',
            'passengers.*.flight_category_id' => 'nullable|exists:flight_categories,id',
            'passengers.*.ticket_purchase_ids' => 'nullable|array',
            'passengers.*.ticket_purchase_ids.*' => 'exists:ticket_purchases,id',
            'passengers.*.ticket_baggage' => 'nullable|array',
            'passengers.*.ticket_baggage.*' => 'array',
            ...TicketBaggage::rules('passengers.*.ticket_baggage.*.'),
            // The two things that must be known to open a card: who is
            // travelling and which agent brought (and pays for) them.
            'passengers.*.agent_id' => 'required|exists:users,id',
            'passengers.*.agent_price' => 'nullable|numeric|min:0',
            // A card re-booking someone from Cancelled (rebookFromRow()).
            'passengers.*.rebook_passenger_id' => 'nullable|integer',
            'passengers.*.rebook_kind' => 'nullable|in:reschedule,reissue',
            'passengers.*.rebook_charge' => 'nullable|numeric|min:0',
            'passengers.*.rebook_bearer' => 'nullable|required_with:passengers.*.rebook_charge|in:agent,company',
            'passengers.*.rebook_fare_mode' => 'nullable|in:agent,company,split',
            'passengers.*.rebook_fare_bearer' => 'nullable|in:agent,company',
            'passengers.*.rebook_agent_fare_amount' => 'nullable|numeric|min:0|max:99999999',
            'passengers.*.rebook_note' => 'nullable|string|max:2000',
            'passengers.*.manpower_cost' => 'nullable|numeric|min:0',
            'passengers.*.boarding_cost' => 'nullable|numeric|min:0',
            'passengers.*.immigration_cost' => 'nullable|numeric|min:0',
            'passengers.*.other_title' => 'nullable|string|max:255',
            'passengers.*.other_cost' => 'nullable|numeric|min:0',
            'passengers.*.manpower_card_fee' => 'nullable|numeric|min:0',
            'passengers.*.reissue_cost' => 'nullable|numeric|min:0',
            'passengers.*.reissue_bearer' => 'nullable|in:company,agent',
            'passengers.*.has_bmet' => 'nullable|boolean',
            'passengers.*.has_manpower_card' => 'nullable|boolean',
            'passengers.*.boarding_training' => 'nullable|boolean',
            'passengers.*.contact_phone' => 'nullable|string|max:30',
            'passengers.*.tickets_required' => 'nullable|integer|min:1|max:5',
            'passengers.*.remarks' => 'nullable|string|max:2000',
            'passengers.*.service_costs' => 'nullable|array',
            'passengers.*.service_costs.*.vendor_id' => 'nullable|exists:users,id',
            'passengers.*.service_costs.*.payment_status' => 'nullable|in:due,partial,paid,unassigned',
            'passengers.*.service_costs.*.paid_amount' => 'nullable|numeric|min:0',
            'passengers.*.service_costs.*.bank_id' => 'nullable|exists:banks,id',
            'passengers.*.service_costs.*.incurred_on' => 'nullable|date',
            'passengers.*.service_costs.*.payment_date' => 'nullable|date',
            'passengers.*.service_costs.*.due_date' => 'nullable|date',
            'passengers.*.service_costs.*.reference_no' => 'nullable|string|max:255',

            'status' => 'required|in:open,boarding,departed,cancelled',
            'notes' => 'nullable|string',
        ], [
            'passengers.*.passport_holder_id.distinct' => 'The same passenger has been added twice to this flight.',
            'passengers.*.agent_id.required' => 'Every passenger must be billed to an agent.',
        ]);

        // An officer is needed the moment ONE passenger takes immigration
        // support, whatever the batch default says. Checked here rather than
        // with required_if because the condition lives in the passenger rows.
        $validator->after(function ($validator) use ($request, $flightId) {
            // One person, one upcoming flight: a passport already booked on
            // another open/boarding flight is moved with Re-assign, never
            // added twice (two package charges, two tickets for one seat).
            $holderIds = collect($request->input('passengers', []))->pluck('passport_holder_id')->filter()->map(fn ($v) => (int) $v)->unique();
            if ($holderIds->isNotEmpty()) {
                ContractFlightPassenger::with(['passportHolder:id,name', 'contractFlight:id,flight_number,status'])
                    ->whereIn('passport_holder_id', $holderIds)
                    ->whereNotNull('contract_flight_id')
                    ->when($flightId, fn ($q) => $q->where('contract_flight_id', '!=', $flightId))
                    ->whereHas('contractFlight', fn ($q) => $q->whereIn('status', ['open', 'boarding']))
                    ->get()
                    ->each(fn ($p) => $validator->errors()->add('passengers',
                        ($p->passportHolder?->name ?: 'A passenger').' is already on '.$p->contractFlight?->flight_number
                        .'. Use Re-assign on his row to move him instead of adding him again.'));
            }

            // (The flight-level immigration officer is no longer demanded: the
            // responsible person is chosen per passenger, and may come later.)

            foreach ($request->input('passengers', []) as $row => $passenger) {
                foreach (ContractFlightPassengerServiceCost::TYPES as $type => $definition) {
                    $amount = max(0, (float) ($passenger[$definition['field']] ?? 0));
                    // A cost only counts while its service is on — the same
                    // rule syncPassengerRows() applies when saving, so a stale
                    // figure on a switched-off service is ignored, not policed.
                    $serviceOff = ($type === 'boarding' && array_key_exists('boarding_training', $passenger) && ! $passenger['boarding_training'])
                        || ($type === 'immigration' && ($passenger['handling_type'] ?? null) !== 'immigration_wise')
                        || ($type === 'manpower' && ! empty($passenger['has_bmet']))
                        || ($type === 'manpower_card' && ! empty($passenger['has_manpower_card']));
                    if ($amount <= 0 || $serviceOff) {
                        continue;
                    }

                    $meta = $passenger['service_costs'][$type] ?? [];
                    $key = "passengers.{$row}.service_costs.{$type}";
                    $label = $definition['label'];
                    $paid = max(0, (float) ($meta['paid_amount'] ?? 0));
                    $status = $meta['payment_status'] ?? null;

                    // Training / consulting services are provided by a Trainer
                    // or Consultant (an agent of that type); anything else by
                    // a vendor. The check never throws if a role is missing.
                    // Nothing is mandatory (owner, 2026-09-21): no person or date yet
                    // means the row is saved "unassigned" and nothing is posted —
                    // ReconcilesContractFlightServiceCosts::serviceCostIsPostable().
                    // A person, when given, must be of the right kind.
                    $providerRoles = ContractFlightPassengerServiceCost::providerRoles($type);
                    if (! empty($meta['vendor_id']) && ! ContractFlightPassengerServiceCost::providerQuery($type)->whereKey($meta['vendor_id'])->exists()) {
                        $who = $providerRoles[0] === 'vendor' ? 'vendor/service provider' : 'responsible person (an instructor, trainer or consultant)';
                        $validator->errors()->add("{$key}.vendor_id", "The {$label} {$who} must be a ".implode(' or ', $providerRoles).'.');
                    }
                    if ($status !== null && $status !== '' && ! in_array($status, ['due', 'partial', 'paid', 'unassigned'], true)) {
                        $validator->errors()->add("{$key}.payment_status", "Select Paid, Partial or Due for {$label}.");
                    }
                    if ($paid > $amount) {
                        $validator->errors()->add("{$key}.paid_amount", "{$label} paid amount cannot exceed its cost.");
                    }
                    if ($status === 'due' && $paid > 0) {
                        $validator->errors()->add("{$key}.paid_amount", "{$label} is marked Due, so paid amount must be zero.");
                    }
                    if ($status === 'partial' && ($paid <= 0 || $paid >= $amount)) {
                        $validator->errors()->add("{$key}.paid_amount", "Enter a partial {$label} payment between zero and its total cost.");
                    }
                    if ($status === 'paid' && abs($paid - $amount) > 0.005) {
                        $validator->errors()->add("{$key}.paid_amount", "{$label} is marked Paid, so paid amount must equal its cost.");
                    }
                    if ($paid > 0) {
                        if (empty($meta['bank_id'])) {
                            $validator->errors()->add("{$key}.bank_id", "Select the bank/cash account used to pay {$label}.");
                        } elseif (! Bank::whereKey($meta['bank_id'])->whereNotNull('account_id')->exists()) {
                            $validator->errors()->add("{$key}.bank_id", "The selected {$label} bank is not linked to a chart account.");
                        }
                        if (empty($meta['payment_date'])) {
                            $validator->errors()->add("{$key}.payment_date", "Enter the payment date for {$label}.");
                        }
                    }
                    // due date: reminded on the Review step, never demanded
                }
            }
        });

        return $validator;
    }

    /**
     * Settle the figures that can only be known once the passengers exist.
     *
     * The Add/Edit Flight form no longer asks for seat counts: a contract
     * batch is exactly the people on it, so both are counted rather than
     * typed. total_seats is still written (rather than left at zero) because
     * the Manage Sales screen uses it as the overbooking cap — a flight with
     * zero there silently loses that guard.
     *
     * seats_sold is deliberately NOT overwritten here: it is owned by the
     * seat-block booking flow (ContractFlightBookingController::syncFlightSeats),
     * and stamping the passenger count over it would erase what clients have
     * actually bought.
     *
     * revenue and cost_price are authoritative passenger rollups. The real
     * money is per passenger (agent_price and total_travel_cost); the flight
     * columns are only stored summaries for lists, reports and MTD cards.
     */
    private function applyPassengerDerivedTotals(ContractFlight $flight, Request $request): void
    {
        $passengers = $flight->passengers()->get();
        $headcount = $passengers->count();

        if ($headcount === 0) {
            $flight->forceFill([
                'revenue' => 0,
                'cost_price' => 0,
            ])->saveQuietly();

            return;
        }

        $attributes = [
            // Passenger rows are the sole source of truth for flight money.
            // The old per-pax rate × seats figure could not survive rates
            // moving to the passenger card.
            // Same rule as ContractFlight::syncMoneyFromPassengers(): a voided package is not revenue.
            // package_charged also takes off a cancelled return's price revision (2026-10-14).
            'revenue' => (float) $passengers->sum(fn ($p) => $p->package_charged),
            'cost_price' => (float) $passengers->sum(fn ($p) => $p->total_travel_cost),

            // The flight's own copies are a summary of what is on board, kept
            // because the rate presets, the list filter and the invoice all
            // still read them. The passenger row is the authority.
            'flight_category_id' => $passengers->firstWhere('flight_category_id', '!=', null)?->flight_category_id
                ?? $flight->flight_category_id,
            'ticket_class' => $passengers->first()->ticket_class ?? $flight->ticket_class,
            'handling_type' => $passengers->contains('handling_type', 'immigration_wise')
                ? 'immigration_wise'
                : 'manpower_wise',
        ];

        // A caller that sent its own total_seats keeps it.
        if (! $request->has('total_seats')) {
            $attributes['total_seats'] = $headcount;
        }

        $flight->forceFill($attributes)->saveQuietly();
    }

    /**
     * Add/remove passengers to match what the form submitted, without
     * touching rows that stay — a full delete-and-recreate would wipe each
     * passenger's pipeline stage, exception flag and ticket links every time
     * the flight itself is merely edited (e.g. a departure-time change).
     *
     * Two shapes are accepted, and which one arrives decides how much is
     * written:
     *
     *  - `passengers[]` — the per-passenger builder in the Add/Edit Flight
     *    modal. Carries each person's own ticket links and costs, so those
     *    are written too.
     *  - `passenger_ids[]` — the older bare list of passport holders. Only
     *    membership changes; costs are seeded from the flight's Price Builder
     *    rates exactly as before.
     *
     * If NEITHER key is present the passenger list is left completely alone.
     * That is deliberate: a form that never rendered the builder must not be
     * read as "this flight now has no passengers" and silently delete a
     * whole batch's pipeline state.
     */
    private function syncPassengers(ContractFlight $flight, Request $request): void
    {
        if ($request->has('passengers')) {
            $this->syncPassengerRows($flight, $request);

            return;
        }

        if ($request->has('passenger_ids')) {
            $this->syncPassengerIds($flight, $request);
        }
    }

    /** The per-passenger builder: membership, ticket links and per-head costs. */
    private function syncPassengerRows(ContractFlight $flight, Request $request): void
    {
        $rows = collect($request->input('passengers', []))
            ->filter(fn ($row) => ! empty($row['passport_holder_id'] ?? null))
            ->keyBy(fn ($row) => (int) $row['passport_holder_id']);

        $existing = $flight->passengers()->get()->keyBy('passport_holder_id');

        $this->removePassengers($this->passengersNotIn($existing, $rows->keys()));

        foreach ($rows as $holderId => $row) {
            $passenger = $existing->get($holderId);
            // A card filled from someone waiting under Cancelled (the builder
            // looked his passport up) moves THAT passenger onto this flight —
            // his history, services and ledger rows come with him — instead
            // of creating a second one. Null for every ordinary card.
            if (($row['rebook_fare_mode'] ?? null) === 'split' && (! is_numeric($row['rebook_agent_fare_amount'] ?? null) || ($row['rebook_fare_bearer'] ?? null) !== 'agent')) {
                throw ValidationException::withMessages(['rebook_agent_fare_amount' => 'Enter the agent share for the split fare difference.']);
            }
            $rebook = $passenger ? null : $this->rebookFromRow($row, (int) $holderId);
            if ($rebook) {
                $passenger = $rebook['passenger'];
            }
            $previousAgentId = $passenger?->agent_id ? (int) $passenger->agent_id : null;

            $handling = $row['handling_type'] ?? $flight->handling_type ?? 'manpower_wise';

            $attributes = [
                // Class is the passenger's own — a batch routinely carries one
                // man in business and the rest in economy. The flight's own
                // ticket_class is only the default a new card starts at.
                'ticket_class' => $row['ticket_class'] ?? $flight->ticket_class ?? 'economy',
                // Same for handling: one agent's three passports can mix
                // boarding-only and full immigration support.
                'handling_type' => $handling,
                // And for the category — Work Permit, Student, Umrah are
                // reasons a person travels, not a property of the aircraft.
                'flight_category_id' => $this->nullableValue($row['flight_category_id'] ?? null)
                    ?? $flight->flight_category_id,
                // A blank cost box means "nothing recorded", not zero — the
                // costing screens read null as not-yet-known, and writing 0.00
                // would present a guess as a measured figure.
                'agent_id' => $this->nullableValue($row['agent_id'] ?? null),
                'agent_price' => $this->nullableValue($row['agent_price'] ?? null),
                // Boarding Training is a ticked service now (2026-09-21), so
                // its cost lives only while the service is on — same rule as
                // immigration below. A form that never sent the flag (the
                // compact edit modal) keeps what the passenger had.
                'boarding_training' => array_key_exists('boarding_training', $row)
                    ? (bool) $row['boarding_training']
                    : (bool) ($passenger?->boarding_training ?? true),
                'boarding_cost' => (array_key_exists('boarding_training', $row) ? (bool) $row['boarding_training'] : (bool) ($passenger?->boarding_training ?? true))
                    ? $this->nullableValue($row['boarding_cost'] ?? null)
                    : null,
                'other_title' => $this->nullableValue($row['other_title'] ?? null),
                'other_cost' => $this->nullableValue($row['other_cost'] ?? null),
                // Immigration cost belongs only to a passenger who actually
                // takes that service. Taking it off them clears the charge, so
                // a cost can never outlive the service it was for.
                'immigration_cost' => $handling === 'immigration_wise'
                    ? $this->nullableValue($row['immigration_cost'] ?? null)
                    : null,
                // Likewise the BMET/manpower service: a passenger who already
                // holds a work permit is not charged for arranging one.
                'manpower_cost' => ($row['has_bmet'] ?? false)
                    ? null
                    : $this->nullableValue($row['manpower_cost'] ?? null),
                'reissue_cost' => $this->nullableValue($row['reissue_cost'] ?? null),
                'reissue_bearer' => $this->nullableValue($row['reissue_bearer'] ?? null),
                'has_bmet' => (bool) ($row['has_bmet'] ?? false),
                // The manpower card is no longer a service the builder offers
                // (2026-09-21). A row that does not mention it keeps whatever
                // the passenger already had, so old batches are not rewritten.
                'has_manpower_card' => array_key_exists('has_manpower_card', $row)
                    ? (bool) $row['has_manpower_card']
                    : (bool) ($passenger?->has_manpower_card ?? false),
                'manpower_card_fee' => array_key_exists('has_manpower_card', $row)
                    ? (($row['has_manpower_card'] ?? false) ? null : $this->nullableValue($row['manpower_card_fee'] ?? null))
                    : ($passenger?->manpower_card_fee ?? null),
                'contact_phone' => $this->nullableValue($row['contact_phone'] ?? null),
                'remarks' => $this->nullableValue($row['remarks'] ?? null),
                // Per passenger, never from the flight's route (owner,
                // 2026-10-13: the route is only a label). The card sets it
                // from the route of HIS ticket; 1 is the fallback.
                'tickets_required' => max(1, (int) ($row['tickets_required'] ?? 1)),
            ];

            if ($passenger) {
                // A re-booked passenger joins this flight here, and his
                // off-flight flag becomes "rescheduled" — before the ledger
                // below, so his package charge posts again instead of zero.
                $passenger->update($attributes + ($rebook
                    ? ['contract_flight_id' => $flight->id, 'exception_flag' => 'rescheduled']
                    : []));
            } else {
                $passenger = $flight->passengers()->create($attributes + [
                    'passport_holder_id' => $holderId,
                    'stage' => 'passenger_received',
                    'document_statuses' => $this->blankDocumentStatuses(),
                ]);
                $passenger->events()->create([
                    'type' => 'stage', 'code' => 'passenger_received', 'label' => 'Passenger Received',
                    'occurred_at' => now(), 'created_by' => Auth::id(),
                ]);
            }

            if (! $rebook && array_key_exists('rebook_fare_bearer', $row)) {
                $pending = ContractFlightPassengerChange::where('contract_flight_passenger_id', $passenger->id)
                    ->where('kind', 'reissue')->whereNull('fare_difference')->whereNotNull('fare_bearer')->latest('id')->lockForUpdate()->first();
                if ($pending) {
                    if (! in_array($row['rebook_fare_bearer'], ['agent', 'company'], true)) {
                        throw ValidationException::withMessages(['rebook_fare_bearer' => 'Choose who bears the fare difference.']);
                    }
                    $pending->update(['fare_bearer' => $row['rebook_fare_bearer']] + (ContractFlightPassengerChange::splitFareAvailable() ? [
                        'agent_fare_amount' => $row['rebook_fare_bearer'] === 'agent' ? ($row['rebook_agent_fare_amount'] ?? null) : null,
                    ] : []));
                }
            }
            $this->syncPassengerTickets($passenger, $row['ticket_purchase_ids'] ?? []);
            app(TicketBaggage::class)->forPassenger($passenger, $row['ticket_baggage'] ?? []);
            $this->reconcileAgentLedger($passenger, $previousAgentId);
            $this->reconcileReissueLedger($passenger, $previousAgentId);
            $this->reconcilePassengerServiceCosts($passenger, $row['service_costs'] ?? []);
            if ($rebook) {
                // Old live ticket cancelled at 0 on a reissue, the charge for
                // cancelling earlier posted, stage restarted, history written.
                $this->completeRebooking($passenger->fresh(['passportHolder']), $flight, $rebook['kind'], $rebook['charge'],
                    $rebook['bearer'], $rebook['note'], $rebook['from_flight_id'], $rebook['live_before'], 0.0, $rebook['fare_bearer'], [], null, false, $rebook['agent_fare_amount']);
                // The new ticket was linked in this same save, before the move
                // existed — work the fare difference out now.
                $this->settlePendingFareDifference($passenger->fresh());
            }
            $this->advanceDerivedStage($passenger->fresh(['contractFlight', 'ticketPurchases']));
        }
    }

    /**
     * The re-booking a builder card carries, or null (owner, 2026-09-26). A
     * card is a re-booking only when rebook_passenger_id names a passenger
     * who is still off a flight AND holds the passport on this card — a
     * stale or edited id can never pull someone else onto the flight.
     */
    private function rebookFromRow(array $row, int $holderId): ?array
    {
        $id = (int) ($row['rebook_passenger_id'] ?? 0);
        if (! $id || ! ContractFlightPassengerChange::available()) {
            return null;
        }
        $p = ContractFlightPassenger::find($id);
        if (! $p || ! $p->isOffFlight() || (int) $p->passport_holder_id !== $holderId) {
            return null;
        }
        $kinds = ContractFlightPassengerChange::MOVE_KINDS;
        $bearers = array_keys(ContractFlightPassengerChange::BEARERS);
        $reason = $p->lastCancellation()->value('reason');
        $live = $reason === 'new_ticket' ? [] : $p->ticketPurchases()->where('ticket_purchases.status', '!=', 'cancelled')
            ->pluck('ticket_purchases.id')->map(fn ($v) => (int) $v)->all();

        return [
            'passenger' => $p,
            // No kind sent: the card's own rule (pbRebookBox) — still holding
            // a live ticket = same ticket; nothing live = new ticket. Never a
            // silent "reissue", which would cancel the ticket he holds.
            'kind' => in_array($row['rebook_kind'] ?? '', $kinds, true) ? $row['rebook_kind'] : ($live ? 'reschedule' : 'reissue'),
            'charge' => is_numeric($row['rebook_charge'] ?? null) ? max(0.0, round((float) $row['rebook_charge'], 2)) : 0.0,
            'bearer' => in_array($row['rebook_bearer'] ?? '', $bearers, true) ? $row['rebook_bearer'] : null,
            'note' => mb_substr(trim((string) ($row['rebook_note'] ?? '')), 0, 2000) ?: null,
            'fare_bearer' => in_array($row['rebook_fare_bearer'] ?? '', $bearers, true) ? $row['rebook_fare_bearer'] : null,
            'agent_fare_amount' => ContractFlightPassengerChange::splitFareAvailable() && isset($row['rebook_agent_fare_amount']) && $row['rebook_agent_fare_amount'] !== '' ? round((float) $row['rebook_agent_fare_amount'], 2) : null,
            'from_flight_id' => $p->lastCancellation()->value('from_flight_id'),
            // What he held before this save — a reissue cancels exactly these,
            // never the new ticket issued on this card.
            // Already refunded through Re-assign (reason new_ticket): what he
            // still holds are the legs he KEPT — cancel nothing more.
            'live_before' => $live,
        ];
    }

    /**
     * Let the data move the pipeline: whatever this save proved (category,
     * price, route, all legs ticketed) becomes the stage, with an event on
     * the passenger's history so the journey strip can date it.
     */
    private function advanceDerivedStage(ContractFlightPassenger $passenger): void
    {
        $moved = $passenger->syncDerivedStage();
        if ($moved) {
            $passenger->events()->create([
                'type' => 'stage', 'code' => $moved, 'label' => ContractFlightPassenger::stageLabel($moved),
                'note' => 'Reached automatically from the details entered', 'occurred_at' => now(), 'created_by' => Auth::id(),
            ]);
        }
    }

    /** The older bare passport-holder list — membership only. */
    private function syncPassengerIds(ContractFlight $flight, Request $request): void
    {
        $selected = array_unique($request->input('passenger_ids', []));
        $existing = $flight->passengers()->get()->keyBy('passport_holder_id');

        $this->removePassengers($this->passengersNotIn($existing, collect($selected)));

        foreach ($selected as $passportHolderId) {
            if ($existing->has($passportHolderId)) {
                continue;
            }
            $passenger = $flight->passengers()->create([
                'passport_holder_id' => $passportHolderId,
                'stage' => 'passenger_received',
                'ticket_class' => $flight->ticket_class ?? 'economy',
                'flight_category_id' => $flight->flight_category_id,
                // Seeded from the flight's own Price Builder rate so staff
                // aren't retyping the same figure for every passenger — free
                // to correct it per passenger afterward for the exceptions.
                'manpower_cost' => $flight->manpower_cost_per_pax ?: null,
                'boarding_cost' => $flight->boarding_cost_per_pax ?: null,
                'document_statuses' => $this->blankDocumentStatuses(),
            ]);
            $passenger->events()->create([
                'type' => 'stage', 'code' => 'passenger_received', 'label' => 'Passenger Received',
                'occurred_at' => now(), 'created_by' => Auth::id(),
            ]);
        }
    }

    /**
     * The passengers on this flight whose passport holder is NOT in $keep.
     *
     * Written out rather than using Collection::except(), which is a trap
     * here: on an Eloquent collection `except()` filters by each model's
     * PRIMARY KEY, not by the key the collection was keyed by. Passing
     * passport-holder ids to it therefore matched almost nothing and marked
     * the whole batch for deletion — which then failed loudly on a foreign
     * key only because a passenger happened to have a ticket linked.
     *
     * Comparison is on int, since a holder id arrives from the form as a
     * string and from the database as an int.
     */
    private function passengersNotIn($passengers, $keep)
    {
        $keepIds = $keep->map(fn ($id) => (int) $id)->all();

        return $passengers->filter(
            fn ($passenger) => ! in_array((int) $passenger->passport_holder_id, $keepIds, true)
        );
    }

    /**
     * Drop passengers taken off the flight, clearing what their agent was
     * billed on the way out. Deleting the row alone would leave the debit
     * standing on the agent's Party Statement with nothing left in the system
     * pointing at it — an invoice for a man who is no longer on the flight.
     */
    private function removePassengers($passengers): void
    {
        // Nothing is deleted once money has moved (owner, 2026-09-28). A
        // passenger holding a live ticket, or with any flight change on file
        // (cancel, re-assign, refund, re-issue), carries vendor, agent and
        // Company Ledger postings that a delete would orphan. Refuse, and
        // send staff to Cancel from flight, which reverses by addition.
        // Thrown inside the caller's transaction, so nothing is half-done.
        foreach ($passengers as $passenger) {
            $live = $passenger->ticketPurchases()->where('ticket_purchases.status', '!=', 'cancelled')->pluck('ticket_no')->filter();
            $changes = ContractFlightPassengerChange::installed()
                && ContractFlightPassengerChange::where('contract_flight_passenger_id', $passenger->id)->exists();
            if ($live->isNotEmpty() || $changes) {
                $name = $passenger->passportHolder?->name ?: 'This passenger';
                throw new \RuntimeException($name.' cannot be removed — '
                    .($live->isNotEmpty() ? 'he holds ticket '.$live->implode(', ') : 'he has flight changes and postings on file')
                    .'. Use "Cancel from flight" instead; it reverses his charges without deleting anything.');
            }
        }
        foreach ($passengers as $passenger) {
            $this->voidAgentLedger($passenger);
            $this->voidReissueLedger($passenger);
            // Charges for earlier moves between flights, billed to the agent.
            $this->voidChangeCharges($passenger);
            $this->removePassengerServiceCosts($passenger);
            $passenger->ticketPurchases()->detach();
            $passenger->delete();
        }
    }

    /**
     * Attach exactly the PNRs the builder submitted for this passenger.
     *
     * Existing links are kept rather than detached-and-recreated so the
     * linked_by/created_at audit on a PNR that was already there survives an
     * unrelated edit to the flight.
     */
    private function syncPassengerTickets($passenger, array $ticketPurchaseIds): void
    {
        $ids = array_values(array_unique(array_filter($ticketPurchaseIds)));

        // Same door check as linkTicket(), for PNRs this save adds: never a
        // cancelled one, one already on another passenger, or a second ticket
        // for a leg already covered. Links he already had are left alone.
        $already = $passenger->ticketPurchases()->pluck('ticket_purchases.id')->map(fn ($v) => (int) $v)->all();
        foreach (array_diff(array_map('intval', $ids), $already) as $newId) {
            $problem = ContractFlightPassengerController::ticketLinkProblem($passenger, (int) $newId, array_map('intval', $ids));
            if ($problem) {
                throw new \RuntimeException(($passenger->passportHolder?->name ?: 'Passenger').': '.$problem);
            }
        }

        $passenger->ticketPurchases()->sync(
            collect($ids)->mapWithKeys(fn ($id) => [$id => ['linked_by' => Auth::id()]])->all()
        );

        // Ticketing is done only when EVERY leg has a confirmed PNR — a
        // DAC → IST → ECN passenger with one of two tickets stays before
        // Ticket Issued (the tracker shows the leg that is done). advance-
        // StageTo() never moves a passenger backwards, so this is safe to
        // call on every save and cannot undo someone already at Airport.
        $passenger->unsetRelation('ticketPurchases');
        $passenger->load('ticketPurchases');
        if ($ids) {
            $this->settlePendingFareDifference($passenger);
            $passenger->unsetRelation('ticketPurchases');
            $passenger->load('ticketPurchases');
        }
        if ($ids && $passenger->isFullyTicketed() && $passenger->advanceStageTo('ticket_issued')) {
            $passenger->events()->create([
                'type' => 'stage', 'code' => 'ticket_issued', 'label' => 'Ticket Issued',
                'occurred_at' => now(), 'created_by' => Auth::id(),
            ]);
        }
    }

    /** '' and '0'-as-not-entered both mean "not recorded"; a real 0 stays 0. */
    private function nullableValue($value)
    {
        return ($value === null || $value === '') ? null : $value;
    }

    private function blankDocumentStatuses(): array
    {
        return ['ticket' => false, 'visa' => false, 'manpower' => false, 'other' => false];
    }

    /**
     * Passenger-derived cost_price/revenue are the authoritative flight
     * rollups — the flight's own Vendor/Cost fields were removed from the
     * form once the real liabilities had proper homes:
     * ticket cost lives on the linked TicketPurchase (its own vendor +
     * journal), agent charge on the passenger's Party Statement row. Posting
     * a *second* GL/AP entry here for a vendor the form no longer collects
     * would double the cost and never reconcile against any real party — two
     * flights (CF-004, CF-009) picked up exactly that phantom entry before
     * this was caught.
     *
     * store()/update() deliberately never write vendor_id/cost_bank_id/
     * portal_id/purchase_date/payable_date/cost_paid_amount anymore, so any
     * flight created after this change simply has none of them — the branch
     * below skips straight to the zeroed-out path for those. A flight
     * created *before* this change may still carry a real vendor_id (and a
     * real journal/payment-schedule from back then); this preserves the
     * original full posting logic for exactly that case, so editing an old
     * flight (e.g. fixing a typo) doesn't silently erase its real liability
     * the way a blanket zero-out would.
     */
    private function syncPaymentSchedules(ContractFlight $flight): void
    {
        if (! $flight->vendor_id) {
            $flight->forceFill(['due_amount' => 0, 'cost_paid_amount' => 0])->saveQuietly();

            return;
        }

        $companyId = CompanyFunds::COMPANY_ID;

        $flight->loadMissing('vendor');

        $costPrice = (float) $flight->cost_price;
        $costPaid = min((float) $flight->cost_paid_amount, $costPrice);
        $due = max(0, $costPrice - $costPaid);

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

        $this->reconcilePartyLedgerRow(
            $flight->vendor_id, 'contract_flight', $flight->id, true, $costPrice,
            [
                'account_id' => null,
                'payment_date' => $flight->purchase_date ?? now(),
                'reference_no' => $flight->flight_number,
                'remarks' => 'Flight cost — '.$flight->flight_number,
            ]
        );

        PaymentSchedule::where('schedulable_type', ContractFlight::class)
            ->where('schedulable_id', $flight->id)
            ->delete();

        if ($costPrice > 0) {
            $this->updatePurchaseJournal(
                'contract_flight',
                $flight->id,
                CompanyFunds::COMPANY_ID,
                $flight->purchase_date ?? $flight->payable_date ?? now(),
                $flight->flight_number,
                'Flight vendor cost — '.$flight->flight_number,
                $costPrice,
                $costPaid,
                $due,
                $paymentAccountId,
                config('accounts.flight_purchase_cost')
            );

            if ($due > 0 && ! $flight->portal_id) {
                PaymentSchedule::create([
                    'company_id' => $companyId,
                    'schedulable_type' => ContractFlight::class,
                    'schedulable_id' => $flight->id,
                    'type' => 'pay',
                    'party_type' => 'vendor',
                    'party_id' => $flight->vendor_id,
                    'party_name' => $flight->vendor?->name ?? 'Vendor (unassigned)',
                    'source_label' => $flight->flight_number,
                    'amount' => $due,
                    'paid_amount' => 0,
                    'scheduled_date' => $flight->payable_date?->toDateString() ?? now()->toDateString(),
                    'status' => 'pending',
                    'created_by' => Auth::id(),
                ]);
            }
        } else {
            $this->deletePurchaseJournal('contract_flight', $flight->id);
        }
    }
}
