<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ContractFlight;
use App\Models\ContractFlightBooking;
use App\Models\ContractFileSale;
use App\Models\TicketSale;
use App\Models\VisaSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    private array $map = [
        'ticket_sale'             => TicketSale::class,
        'visa_sale'               => VisaSale::class,
        'contract_flight'         => ContractFlight::class,
        'contract_file_sale'      => ContractFileSale::class,
        'contract_flight_booking' => ContractFlightBooking::class,
    ];

    public function list(Request $request)
    {
        $type = $request->input('type');
        $id   = $request->input('id');

        if (!array_key_exists($type, $this->map)) {
            return response()->json([]);
        }

        $parent = $this->map[$type]::findOrFail($id);

        $comments = $parent->comments()->with('user')->latest()->get()
            ->map(fn ($c) => [
                'id'         => $c->id,
                'body'       => $c->body,
                'user'       => $c->user->name,
                'is_mine'    => $c->user_id === Auth::id(),
                'created_at' => $c->created_at->diffForHumans(),
            ]);

        return response()->json($comments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'body'  => 'required|string|max:2000',
            'type'  => 'required|string|in:ticket_sale,visa_sale,contract_flight,contract_file_sale,contract_flight_booking',
            'id'    => 'required|integer',
        ]);

        $parent  = $this->map[$request->type]::findOrFail($request->id);
        $comment = $parent->comments()->create([
            'user_id' => Auth::id(),
            'body'    => $request->body,
        ]);

        $comment->load('user');

        return response()->json([
            'id'         => $comment->id,
            'body'       => $comment->body,
            'user'       => $comment->user->name,
            'is_mine'    => true,
            'created_at' => $comment->created_at->diffForHumans(),
        ]);
    }

    public function destroy(string $_role, Comment $comment)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        abort_unless(
            $comment->user_id === Auth::id() || $user->hasRole('super-admin'),
            403,
            'You can only delete your own comments.'
        );

        $comment->delete();

        return response()->json(['success' => true]);
    }
}
