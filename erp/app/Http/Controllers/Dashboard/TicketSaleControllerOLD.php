<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\TicketSaleItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketSaleControllerOLD extends Controller
{
    public function index()
    {
        // Get sales with agent + items
        $ticketSales = TicketSale::with(['agent', 'items.ticketPurchase.passportHolder'])
            ->latest()
            ->paginate(10);

        return view('dashboard.ticket-sell.index', compact('ticketSales'));
    }

    public function create(Request $request, $role)
    {
        // Agents: users with role 'agent' (adjust if you keep agents elsewhere)
        $agents = User::role('agent')->get();

        // Tickets not yet sold (and ideally already 'booked')
        $tickets = TicketPurchase::whereNotIn('id', function ($q) {
                $q->select('ticket_purchase_id')->from('ticket_sale_items');
            })
            ->where('status', 'booked')
            ->with(['passportHolder']) // if relation exists
            ->latest()->get();

        // Generate next invoice number like INV000001
        $last = TicketSale::latest('id')->first();
        $next = $last ? $last->id + 1 : 1;
        $invoiceNo = 'INV' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);

        return view('dashboard.ticket-sell.create', compact('agents', 'tickets', 'invoiceNo'));
    }

    public function store(Request $request, $role)
    {
        $request->validate([
            'agent_id'                => 'required|exists:users,id',
            'sale_date'               => 'required|date',
            'currency'                => 'required|string|max:5',
            'status'                  => 'required|in:pending,paid,cancelled',
            'invoice_no'              => 'required|string|unique:ticket_sales,invoice_no',
            'tickets'                 => 'required|array|min:1',
            'tickets.*.id'            => 'required|exists:ticket_purchases,id',
            'tickets.*.price'         => 'required|numeric|min:0',
        ]);

        // Extract selected ticket_purchase_ids
        $selectedIds = collect($request->input('tickets'))
            ->pluck('id')
            ->map('intval')
            ->values()
            ->all();

        // Check already sold ones
        $alreadySold = TicketSaleItem::whereIn('ticket_purchase_id', $selectedIds)
            ->pluck('ticket_purchase_id')
            ->toArray();

        if (!empty($alreadySold)) {
            return back()
                ->withErrors(['tickets' => 'Some tickets are already sold: '.implode(', ', $alreadySold)])
                ->withInput();
        }

        // Optional: only allow selling booked tickets
        $notBooked = TicketPurchase::whereIn('id', $selectedIds)
            ->where('status', '!=', 'booked')
            ->pluck('ticket_no')
            ->toArray();

        if (!empty($notBooked)) {
            return back()
                ->withErrors(['tickets' => 'Only booked tickets can be sold. Not booked: '.implode(', ', $notBooked)])
                ->withInput();
        }

        DB::transaction(function () use ($request) {
            $items = collect($request->input('tickets'));
            $total = $items->sum(function ($row) {
                return (float)($row['price'] ?? 0);
            });

            $sale = TicketSale::create([
                'invoice_no'  => $request->invoice_no,
                'agent_id'    => $request->agent_id,
                'sale_date'   => $request->sale_date,
                'total_amount'=> $total,
                'currency'    => $request->currency,
                'status'      => $request->status,
                'created_by'  => Auth::id(),
            ]);

            $payload = $items->map(function ($row) use ($sale) {
                return [
                    'ticket_sale_id'     => $sale->id,
                    'ticket_purchase_id' => (int)$row['id'],
                    'price'              => (float)$row['price'],
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            })->all();

            TicketSaleItem::insert($payload);
        });

        return redirect()->back()->with('success', 'Ticket sale created successfully!');
    }

     public function edit($role, TicketSale $ticket_sale)
    {
        // all agents
        $agents = User::role('agent')->get();

        // available tickets: booked + not already sold (same logic as create)
        $tickets = TicketPurchase::whereNotIn('id', function ($q) {
                $q->select('ticket_purchase_id')->from('ticket_sale_items');
            })
            ->where('status', 'booked')
            ->with(['passportHolder'])
            ->get();

        // but also include tickets already linked to this sale
        $tickets = $tickets->merge(
            $ticket_sale->items->map->ticketPurchase
        );
        return view('dashboard.ticket-sell.edit', compact('ticket_sale', 'agents', 'tickets'));
    }

    public function update(Request $request, $role, TicketSale $ticket_sale)
    {
        $request->validate([
            'agent_id' => 'required|exists:users,id',
            'sale_date' => 'required|date',
            'tickets'   => 'required|array|min:1',
            'currency'  => 'required|string|max:5',
            'status'    => 'required|in:pending,paid,cancelled',
        ]);

        DB::transaction(function () use ($request, $ticket_sale) {
            // update sale
            $ticket_sale->update([
                'agent_id' => $request->agent_id,
                'sale_date' => $request->sale_date,
                'currency' => $request->currency,
                'status' => $request->status,
            ]);

            // remove old items
            $ticket_sale->items()->delete();

            $total = 0;

            foreach ($request->tickets as $ticket) {
                if (!isset($ticket['id']) || !isset($ticket['price'])) {
                    continue;
                }

                $ticket_sale->items()->create([
                    'ticket_purchase_id' => $ticket['id'],
                    'price' => $ticket['price'],
                ]);

                $total += (float)$ticket['price'];
            }

            // update total
            $ticket_sale->update([
                'total_amount' => $total,
            ]);
        });

        return redirect()
            ->route('role.ticket-sales.index', ['role' => $role])
            ->with('success', 'Ticket Sale updated successfully.');
    }
}
