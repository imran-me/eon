<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\PassportHolder;
use App\Models\Portal;
use App\Models\TicketLeg;
use App\Models\TicketPurchase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketPurchaseControllerOLD extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Fetch ticket purchases from the database
            $ticketPurchases = TicketPurchase::latest()->paginate(10); ;

            // Return the view with ticket purchases data
            return view('dashboard.ticket_purchases.index', compact('ticketPurchases'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to fetch ticket purchases.']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            // Fetch data for selects
            $passportHolders = PassportHolder::all();
            $vendors = User::role('vendor')->get(); // assuming Spatie roles
            $portals = Portal::all();
            $banks = Bank::all();

            // Optional: Generate default ticket_no
            $lastTicket = TicketPurchase::latest()->first();
            if ($lastTicket) {
                $lastNumber = (int) substr($lastTicket->ticket_no, 2); // remove 'PL'
                $ticketNo = 'PL' . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            } else {
                $ticketNo = 'PL00001';
            }

            return view('dashboard.ticket_purchases.create', compact(
                'passportHolders',
                'vendors',
                'portals',
                'banks',
                'ticketNo' // send to view if you want to prefill
            ));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load ticket purchase data.']);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $role)
    {
        $request->validate([
            'passport_holder_id' => 'required|exists:passport_holders,id',
            'vendor_id'          => 'nullable|exists:users,id',
            'portal_id'          => 'nullable|exists:portals,id',
            'bank_id'            => 'nullable|exists:banks,id',
            'ticket_type'        => 'required|in:air,bus,train,other',
            'trip_type'          => 'required|in:one-way,two-way',
            'purchase_date'      => 'required|date',
            'ticket_no'          => 'required|string|unique:ticket_purchases,ticket_no',
            'status'             => 'required|in:pending,booked,cancelled',
            'amount'             => 'nullable|numeric',
            'currency'           => 'nullable|string|max:5',
            'legs.*.from_location' => 'nullable|string',
            'legs.*.to_location'   => 'nullable|string',
            'legs.*.travel_date'   => 'nullable|date',
            'legs.*.seat_number'   => 'nullable|string',
            'legs.*.attachment'    => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'airline_or_operator'  => 'nullable|string|max:255',
            'source'               => 'nullable|string|max:255',
            'attachment'           => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        ]);

        try {
            // Prepare main ticket purchase data
            $ticketPurchaseData = $request->only([
                'passport_holder_id',
                'vendor_id',
                'portal_id',
                'bank_id',
                'ticket_type',
                'trip_type',
                'purchase_date',
                'ticket_no',
                'status',
                'amount',
                'currency',
                'airline_or_operator',
                'source',
            ]);
            $ticketPurchaseData['created_by'] = Auth::user()->id;

            // Handle main attachment if provided
            if ($request->hasFile('attachment')) {
                $ticketPurchaseData['attachment'] = $request->file('attachment')->store('ticket_attachments', 'public');
            }

            // Create TicketPurchase
            $ticketPurchase = TicketPurchase::create($ticketPurchaseData);

            // Handle legs
            foreach ($request->legs as $index => $leg) {
                if (isset($leg['from_location'], $leg['to_location'], $leg['travel_date'])) {
                    $legData = [
                        'ticket_purchase_id' => $ticketPurchase->id,
                        'from_location' => $leg['from_location'],
                        'to_location'   => $leg['to_location'],
                        'travel_date'   => $leg['travel_date'],
                        'seat_number'   => $leg['seat_number'] ?? null,
                    ];

                    // Handle leg attachment
                    if (isset($leg['attachment']) && $request->hasFile("legs.$index.attachment")) {
                        $legData['attachment'] = $request->file("legs.$index.attachment")->store('ticket_legs', 'public');
                    }

                    // Save leg in a separate table or json column
                    // If you have a `ticket_legs` table
                    TicketLeg::create($legData);

                    // If you want to store legs in JSON column in ticket_purchases table
                    // you can append legData to $legsArray and then update ticketPurchase->legs = json_encode($legsArray)
                }
            }

            return redirect()->back()->with('success', 'Ticket purchase created successfully!');

        } catch (\Exception $e) {
            // throw $e;
            // dd($e->getMessage());
            // Handle any exceptions that may occur
            // Log the error or return a user-friendly message
            return redirect()->back()->withErrors(['error' => 'Failed to create ticket purchase: ' . $e->getMessage()])
                                    ->withInput();
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
 public function edit($role, $id)
{
    try {
        // Fetch ticket with legs
        $ticketPurchase = TicketPurchase::with('legs')->findOrFail($id);

        // Preload dropdowns again
        $passportHolders = PassportHolder::all();
        $vendors = User::role('vendor')->get();
        $portals = Portal::all();
        $banks = Bank::all();

        return view('dashboard.ticket_purchases.edit', compact(
            'ticketPurchase',
            'passportHolders',
            'vendors',
            'portals',
            'banks'
        ));
    } catch (\Exception $e) {
        return redirect()->back()->withErrors(['error' => 'Failed to load ticket purchase data.']);
    }
}


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, $id)
{
    $request->validate([
        'passport_holder_id'   => 'required|exists:passport_holders,id',
        'vendor_id'            => 'nullable|exists:users,id',
        'portal_id'            => 'nullable|exists:portals,id',
        'bank_id'              => 'nullable|exists:banks,id',
        'ticket_type'          => 'required|in:air,bus,train,other',
        'trip_type'            => 'required|in:one-way,two-way',
        'purchase_date'        => 'required|date',
        'ticket_no'            => 'required|string|unique:ticket_purchases,ticket_no,' . $id,
        'status'               => 'required|in:pending,booked,cancelled',
        'amount'               => 'nullable|numeric',
        'currency'             => 'nullable|string|max:5',
        'legs.*.from_location' => 'nullable|string',
        'legs.*.to_location'   => 'nullable|string',
        'legs.*.travel_date'   => 'nullable|date',
        'legs.*.seat_number'   => 'nullable|string',
        'legs.*.attachment'    => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        'airline_or_operator'  => 'nullable|string|max:255',
        'source'               => 'nullable|string|max:255',
        'attachment'           => 'nullable|file|mimes:pdf,jpg,jpeg,png',
    ]);

    try {
        $ticket = TicketPurchase::findOrFail($id);

        // Prepare main ticket data
        $ticketData = $request->only([
            'passport_holder_id',
            'vendor_id',
            'portal_id',
            'bank_id',
            'ticket_type',
            'trip_type',
            'purchase_date',
            'ticket_no',
            'status',
            'amount',
            'currency',
            'airline_or_operator',
            'source',
        ]);

        // Update attachment if new one uploaded
        if ($request->hasFile('attachment')) {
            $ticketData['attachment'] = $request->file('attachment')->store('ticket_attachments', 'public');
        }

        $ticket->update($ticketData);

        // 🔄 Handle legs
        $ticket->legs()->delete(); // simple way: remove old legs first

        foreach ($request->legs as $index => $leg) {
            if (isset($leg['from_location'], $leg['to_location'], $leg['travel_date'])) {
                $legData = [
                    'ticket_purchase_id' => $ticket->id,
                    'from_location' => $leg['from_location'],
                    'to_location'   => $leg['to_location'],
                    'travel_date'   => $leg['travel_date'],
                    'seat_number'   => $leg['seat_number'] ?? null,
                ];

                if (isset($leg['attachment']) && $request->hasFile("legs.$index.attachment")) {
                    $legData['attachment'] = $request->file("legs.$index.attachment")->store('ticket_legs', 'public');
                }

                TicketLeg::create($legData);
            }
        }

        return redirect()->route('role.ticket-purchase.index', ['role' => $role])
                         ->with('success', 'Ticket updated successfully!');
    } catch (\Exception $e) {
        return redirect()->back()->withErrors(['error' => 'Failed to update ticket purchase: ' . $e->getMessage()])
                             ->withInput();
    }
}


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Find the ticket purchase by ID
            $ticketPurchase = \App\Models\TicketPurchase::findOrFail($id);

            // Delete the ticket purchase
            $ticketPurchase->delete();

            // Redirect to the index with success message
            return redirect()->route('dashboard.ticket-purchase.index')->with('success', 'Ticket purchase deleted successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to delete ticket purchase.']);
        }
    }
}
