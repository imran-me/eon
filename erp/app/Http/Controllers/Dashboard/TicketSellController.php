<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TicketSellController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Fetch ticket sales from the database
            $ticketSales = \App\Models\TicketSale::latest()->get();

            // Return the view with ticket sales data
            return view('dashboard.ticket-sell.index', compact('ticketSales'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to fetch ticket sales.']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            // Return the view for creating a new ticket sale
            return view('dashboard.ticket-sell.create');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to load create form.']);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $request->validate([
                'ticket_id' => 'required|exists:tickets,id',
                'user_id' => 'required|exists:users,id',
                'quantity' => 'required|integer|min:1',
                // Add other validation rules as needed
            ]);

            // Create a new ticket sale
            \App\Models\TicketSale::create($request->all());

            // Redirect to the index with success message
            return redirect()->route('dashboard.ticket-sell.index')->with('success', 'Ticket sale created successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to create ticket sale.']);
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
    public function edit(string $id)
    {
        try {
            // Find the ticket sale by ID
            $ticketSale = \App\Models\TicketSale::findOrFail($id);

            // Return the view for editing the ticket sale
            return view('dashboard.ticket-sell.edit', compact('ticketSale'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to load edit form.']);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // Validate the request data
            $request->validate([
                'ticket_id' => 'required|exists:tickets,id',
                'user_id' => 'required|exists:users,id',
                'quantity' => 'required|integer|min:1',
                // Add other validation rules as needed
            ]);

            // Find the ticket sale by ID and update it
            $ticketSale = \App\Models\TicketSale::findOrFail($id);
            $ticketSale->update($request->all());

            // Redirect to the index with success message
            return redirect()->route('dashboard.ticket-sell.index')->with('success', 'Ticket sale updated successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to update ticket sale.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Find the ticket sale by ID and delete it
            $ticketSale = \App\Models\TicketSale::findOrFail($id);
            $ticketSale->delete();

            // Redirect to the index with success message
            return redirect()->route('dashboard.ticket-sell.index')->with('success', 'Ticket sale deleted successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to delete ticket sale.']);
        }
    }
}
