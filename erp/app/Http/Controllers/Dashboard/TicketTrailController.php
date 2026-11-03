<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\PassportHolder;
use App\Models\Portal;
use App\Models\Ticket;
use App\Models\TicketPurchase;
use App\Models\TicketTrailLog;
use App\Models\User;
use App\Services\TicketTrail\Footprint;
use App\Services\TicketTrail\Rewriter;
use App\Services\TicketTrail\Timeline;
use App\Services\TicketTrail\TrailRefused;
use App\Support\UserRoleSetting\Access;
use Illuminate\Http\Request;

/**
 * Ticket Orders → Change → "All Trails and Edit" (owner, 2026-10-26): one
 * ticket's whole story — history, every posting with its ledger and amount —
 * and the editor that corrects any of it, previewed side by side before
 * anything is posted. Super admin only, checked at every entry point.
 */
class TicketTrailController extends Controller
{
    private function guard(): void
    {
        abort_unless(Access::allows('travels.ticketing', 'e'), 403, 'The ticket trail needs Edit on Ticketing.');
        abort_unless(TicketTrailLog::available(), 404, 'The ticket trail is not installed on this server yet.');
    }

    /** Everything the Trail window shows. */
    public function show(string $role, TicketPurchase $ticketPurchase)
    {
        $this->guard();
        $rw = new Rewriter;
        $fp = Footprint::of($ticketPurchase);

        return response()->json([
            'success' => true,
            'blocked' => $rw->blocked($ticketPurchase),
            'state' => $rw->state($ticketPurchase),
            'footprint' => $fp,
            'totals' => Footprint::totals($fp),
            'timeline' => Timeline::of($ticketPurchase),
            'options' => [
                'passengers' => PassportHolder::orderBy('name')->get(['id', 'name', 'passport_no'])->map(fn ($h) => ['id' => $h->id, 'text' => $h->name.($h->passport_no ? ' · '.$h->passport_no : '')]),
                'routes' => Ticket::where('status', 1)->orWhere('id', $ticketPurchase->ticket_id)->orderBy('title')->get(['id', 'title'])->map(fn ($r) => ['id' => $r->id, 'text' => $r->title]),
                'sellers' => User::role('vendor')->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => 'vendor:'.$u->id, 'text' => $u->name])
                    ->concat(Portal::orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['id' => 'portal:'.$p->id, 'text' => $p->name.' (portal)'])),
                'banks' => Bank::where('status', 1)->orderBy('name')->get(['id', 'name'])->map(fn ($b) => ['id' => $b->id, 'text' => $b->name]),
                'buyers' => User::role(['agent', 'customer'])->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'text' => $u->name]),
            ],
        ]);
    }

    /** The correction run and rolled back: what would change, where it would hit. */
    public function preview(Request $request, string $role, TicketPurchase $ticketPurchase)
    {
        $this->guard();
        $request->validate(['edit' => ['required', 'array']]);
        try {
            $r = (new Rewriter)->run($ticketPurchase, $request->input('edit'), false);
        } catch (TrailRefused $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'changed' => $r['changed'], 'summary' => $r['summary'], 'diff' => $r['diff'],
            'after' => ['totals' => Footprint::totals($r['after'])]]);
    }

    /** Confirm & post: the same run, kept. Reason and CONFIRM are required here, not only in the browser. */
    public function apply(Request $request, string $role, TicketPurchase $ticketPurchase)
    {
        $this->guard();
        $v = $request->validate([
            'edit' => ['required', 'array'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'confirm' => ['required', 'in:CONFIRM'],
            'notify' => ['nullable', 'boolean'],
        ]);
        try {
            $r = (new Rewriter)->run($ticketPurchase, $v['edit'], true, $v['reason'], (bool) ($v['notify'] ?? false));
        } catch (TrailRefused $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        if (! $r['changed']) {
            return response()->json(['success' => false, 'message' => 'Nothing was changed, so nothing was posted.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Corrected and posted — '.count($r['summary']).' change'.(count($r['summary']) === 1 ? '' : 's').'. The old entries were replaced; a copy is kept in the history.',
            'summary' => $r['summary'], 'log_id' => $r['log_id'] ?? null]);
    }
}
