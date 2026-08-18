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
use App\Models\Portal;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FlightScheduleController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view flight schedule|view all flight schedule', only: ['index']),
            new Middleware('permission:create flight schedule', only: ['store']),
            new Middleware('permission:edit flight schedule', only: ['update']),
            new Middleware('permission:delete flight schedule', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $today = now()->startOfDay();
        $yesterday = (clone $today)->subDay();
        $tomorrow = (clone $today)->addDay();

        $query = ContractFlight::with([
            'flightCategory', 'categoryType', 'ticket.airline', 'ticket.from_airport',
            'ticket.to_airport', 'agent', 'boardingOfficer.user',
            'immigrationOfficer.user', 'passengers.passportHolder',
        ])
            ->orderBy('departure_at')
            ->orderBy('flight_number');

        if ($request->filled('date')) {
            $query->whereDate('departure_at', $request->date);
        } else {
            $query->whereBetween('departure_at', [$yesterday, (clone $tomorrow)->endOfDay()]);
        }

        if ($request->filled('flight_category_id')) {
            $query->where('flight_category_id', $request->flight_category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('flight_number', 'like', "%{$search}%")
                    ->orWhere('airline_flight_no', 'like', "%{$search}%")
                    ->orWhereHas('flightCategory', fn ($cat) => $cat->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('ticket', fn ($ticket) => $ticket->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('ticket.airline', fn ($airline) => $airline->where('name', 'like', "%{$search}%"));
            });
        }

        $datas = $query->get();

        $stats = [
            'yesterday_count' => ContractFlight::whereDate('departure_at', $yesterday)->where('status', 'departed')->count(),
            'today_count' => ContractFlight::whereDate('departure_at', $today)->count(),
            'today_departed' => ContractFlight::whereDate('departure_at', $today)->where('status', 'departed')->count(),
            'today_upcoming' => ContractFlight::whereDate('departure_at', $today)->whereIn('status', ['open', 'boarding'])->count(),
            'tomorrow_count' => ContractFlight::whereDate('departure_at', $tomorrow)->count(),
            'week_count' => ContractFlight::whereBetween('departure_at', [(clone $today)->startOfWeek(), (clone $today)->endOfWeek()])->count(),
            'yesterday_label' => $yesterday->format('d M'),
            'today_label' => $today->format('d M'),
            'tomorrow_label' => $tomorrow->format('d M'),
        ];

        $reminderFlights = ContractFlight::with(['ticket.airline', 'ticket.from_airport', 'ticket.to_airport'])
            ->whereDate('departure_at', $tomorrow)
            ->whereIn('status', ['open', 'boarding'])
            ->orderBy('departure_at')
            ->get();

        $categories = FlightCategory::with('categoryType')->orderBy('name')->get();
        $categoryTypes = FlightCategoryType::where('status', 'active')->orderBy('name')->get();
        $agents = User::orderBy('name')->role('agent')->get(['id', 'name']);
        $tickets = Ticket::with('airline', 'from_airport', 'to_airport')->orderBy('title')->get();
        $airports = Airport::orderBy('name')->get();
        $airlines = Airline::orderBy('name')->where('status', 1)->get();
        $vendors = User::orderBy('name')->role('vendor')->get();
        $banks = Bank::where('status', 1)->get();
        $portals = Portal::orderBy('name')->where('status', 'active')->get();
        $officers = FlightOfficer::with(['user', 'airline'])->where('status', 'active')->orderBy('id')->get();
        $passportHolders = PassportHolder::with('category')->orderBy('name')->get();
        $passportHolderCategories = PassportHolderCategory::orderBy('name')->get();

        return view('flight-schedules.index', compact(
            'datas',
            'stats',
            'reminderFlights',
            'categories',
            'categoryTypes',
            'agents',
            'tickets',
            'airports',
            'airlines',
            'vendors',
            'banks',
            'portals',
            'officers',
            'passportHolders',
            'passportHolderCategories'
        ));
    }

    public function store(Request $request)
    {
        return app(ManageFlightController::class)->store($request);
    }

    public function update(Request $request, $role, string $id)
    {
        return app(ManageFlightController::class)->update($request, $role, $id);
    }

    public function destroy(Request $request, $role, string $id)
    {
        return app(ManageFlightController::class)->destroy($request, $role, $id);
    }
}
