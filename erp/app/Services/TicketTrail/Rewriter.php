<?php

namespace App\Services\TicketTrail;

use App\Exceptions\TicketChangeRefused;
use App\Http\Controllers\Dashboard\TicketChangeController;
use App\Http\Controllers\Dashboard\TicketPurchaseController;
use App\Http\Controllers\Dashboard\TicketSaleController;
use App\Models\Bank;
use App\Models\PartyLedgerNotification;
use App\Models\Portal;
use App\Models\Ticket;
use App\Models\TicketChange;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\TicketSaleItem;
use App\Models\TicketTrailLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TicketBaggage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ticket Trail (owner, 2026-10-26): correct anything about a Ticketing ticket —
 * its seller and cost, the payments made to the seller, who it is sold to and
 * at what price, and every change on it (kind, amounts, who bears, the day) —
 * and REPLACE what was posted ("wrong entry get vanished and the edited take
 * their places"), never add reversal rows.
 *
 * How, in one transaction:
 *   1. rewind   every posting the ticket's changes made is removed, and the
 *               ticket is put back to how it was bought (live, its first date)
 *   2. ticket   field edits go through the Edit page's own update()
 *   3. payments seller payments removed and posted again, as made
 *   4. sale     this ticket's line on its invoice re-priced / moved
 *   5. replay   each change posted again through Change's own code
 *               (TicketChangeController::replay) on its own day; a change
 *               left as it was keeps every row's original date and time
 * A preview is the same run rolled back, so what it shows is exactly what
 * Confirm will post. A copy of every removed row goes into ticket_trail_logs.
 */
class Rewriter
{
    /** party id => lowest ledger row id touched, to re-walk the running balance from */
    private array $parties = [];

    /** portal id => lowest wallet row id touched */
    private array $portals = [];

    /** verbatim copies of everything removed, for the log */
    private array $replaced = ['transactions' => [], 'journals' => [], 'portal_balances' => [], 'ticket_changes' => [], 'sale_items' => []];

    // ── what the Trail edits ─────────────────────────────────────────────

    /** Why this ticket's trail cannot be edited here, or null. */
    public function blocked(TicketPurchase $t): ?string
    {
        if (! in_array($t->status, ['confirm', 'cancelled'], true)) {
            return 'Only a purchased or cancelled ticket can be corrected here — this one is '.$t->status.'.';
        }
        if ($this->isContractFlight($t)) {
            // A live ticket of a passenger the flight has not changed: only the ticket's own
            // purchase is the Trail's (cost, seller, PNR, dates, payments) — update() keeps the
            // flight's totals in step, exactly as the Edit page does. Everything else the flight posted.
            // His flight changes (re-issue, refund, re-assign) are the flight's and are edited on the
            // Contract Flight Trail (owner, 2026-11-01: "mirror what it is connected, the rest are in the
            // contract flight"); the ticket's own side stays editable here whatever the flight did —
            // normalise() never replays a contract-flight ticket's changes, run() never rewinds them.
            $cf = $this->flightOf($t);
            if (! $cf) {
                return 'This ticket was a contract-flight passenger\'s and was taken off his flight. Its trail is shown; what it posted belongs to the flight — edit it on his flight trail (Flights & Passengers → Re-assign → All Trails and Edit).';
            }
            if ($t->status !== 'confirm') {
                return 'This contract-flight ticket is cancelled — the flight posted that. Edit its refund on his flight trail (Flights & Passengers → Re-assign → All Trails and Edit).';
            }
        }

        return null;
    }

    /** The contract-flight passenger this ticket is linked to now: flight, agent, package, changes. */
    private function flightOf(TicketPurchase $t): ?array
    {
        try {
            $r = DB::table('contract_flight_passenger_tickets as l')->join('contract_flight_passengers as p', 'p.id', '=', 'l.contract_flight_passenger_id')
                ->leftJoin('contract_flights as f', 'f.id', '=', 'p.contract_flight_id')->leftJoin('users as u', 'u.id', '=', 'p.agent_id')
                ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                ->where('l.ticket_purchase_id', $t->id)->first(['p.id', 'p.agent_price', 'f.flight_number', 'u.name as agent', 'h.name as passenger']);
            if (! $r) {
                return null;
            }

            return ['passenger_id' => (int) $r->id, 'passenger' => $r->passenger ?: 'This passenger', 'flight' => $r->flight_number, 'agent' => $r->agent,
                'package' => round((float) $r->agent_price, 2),
                'changes' => (int) DB::table('contract_flight_passenger_changes')->where('contract_flight_passenger_id', $r->id)->count()];
        } catch (\Throwable) {
            return null;
        }
    }

    /** The ticket as the Trail shows and edits it. */
    public function state(TicketPurchase $t): array
    {
        $t->loadMissing(['passportHolder:id,name,passport_no', 'ticket:id,title', 'vendor:id,name', 'portal:id,name']);
        $changes = Schema::hasTable('ticket_changes') ? TicketChange::with('creator:id,name')->where('ticket_purchase_id', $t->id)->orderBy('id')->get() : collect();
        $moved = $changes->first(fn ($c) => $c->old_travel_at);
        $sale = $this->saleOf($t);

        return [
            'ticket' => [
                'passport_holder_id' => (int) $t->passport_holder_id,
                'passenger' => $t->passportHolder?->name,
                'passport' => $t->passportHolder?->passport_no,
                'ticket_id' => (int) $t->ticket_id,
                'route' => $t->ticket?->title,
                'ticket_no' => (string) $t->ticket_no,
                'ticket_class' => $t->ticket_class,
                'trip_type' => $t->trip_type,
                'purchase_date' => $t->purchase_date ? Carbon::parse($t->purchase_date)->toDateString() : null,
                // the date it was BOUGHT for; a re-issue's new date sits on that change
                'travel_at' => $moved ? Carbon::parse($moved->old_travel_at)->format('Y-m-d H:i') : ($t->travelAt() ? substr($t->travelAt(), 0, 16) : null),
                'seller' => $t->vendor_id ? 'vendor:'.$t->vendor_id : ($t->portal_id ? 'portal:'.$t->portal_id : null),
                'seller_name' => $t->vendor?->name ?: ($t->portal?->name ? $t->portal->name.' (portal)' : null),
                'amount' => round((float) $t->amount, 2),
                'status' => $t->status,
            ],
            'payments' => $this->paymentsOf($t),
            'sale' => $sale ? [
                'sale_id' => (int) $sale->id, 'invoice' => $sale->invoice_no, 'client_id' => (int) $sale->client_id, 'client' => $sale->client,
                'price' => round((float) $sale->price, 2), 'tickets' => (int) $sale->tickets, 'paid' => round((float) $sale->paid_amount, 2),
                'date' => $sale->sale_date ? substr((string) $sale->sale_date, 0, 10) : null,
            ] : null,
            'events' => $this->eventsOf($t, $changes),
            // on a contract flight: billed per head there, never sold here
            'cf' => $this->flightOf($t),
        ];
    }

    // ── preview / confirm ─────────────────────────────────────────────────

    /**
     * Run the correction. $commit false = preview: everything is posted, read
     * back, and rolled back. Throws TrailRefused with a message for the user.
     */
    public function run(TicketPurchase $t, array $edit, bool $commit, string $reason = '', bool $notify = false, bool $force = false): array
    {
        if ($why = $this->blocked($t)) {
            throw new TrailRefused($why);
        }
        $this->parties = $this->portals = [];
        $this->replaced = ['transactions' => [], 'journals' => [], 'portal_balances' => [], 'ticket_changes' => [], 'sale_items' => []];
        // Corrections are not news to the agent / vendor unless asked: no email / SMS.
        request()->merge(['suppress_party_notifications' => ! $notify]);
        EditLog::$paused = true;

        DB::beginTransaction();
        try {
            $p = TicketPurchase::whereKey($t->id)->lockForUpdate()->firstOrFail();
            $before = Footprint::of($p);
            $state = $this->state($p);
            $edit = $this->normalise($edit, $state);
            $summary = $this->describe($state, $edit);
            if (! $summary && ! $force) {
                DB::rollBack();

                return ['changed' => false, 'summary' => [], 'before' => $before, 'after' => $before, 'diff' => Diff::of($before, $before), 'state' => $state];
            }
            $marks = [
                'tx' => (int) DB::table('transactions')->max('id'),
                'je' => (int) DB::table('journal_entries')->max('id'),
                'pb' => Schema::hasTable('portal_balances') ? (int) DB::table('portal_balances')->max('id') : 0,
            ];

            // a contract-flight ticket has no Ticketing changes to take back — the flight's are its own
            if (empty($state['cf'])) {
                $this->rewind($p, $state);
            }
            $this->applyTicket($p, $state, $edit);
            $this->applyPayments($p, $state, $edit);
            $this->applySale($p, $state, $edit);
            app(TicketBaggage::class)->sync($p);
            $map = $this->replay($p, $state, $edit);
            $this->restoreDates($p, $before, $state, $edit, $map, $marks);
            app(TicketPurchaseController::class)->trailResync($p);
            $this->rewalk($marks);

            $p->refresh();
            $after = Footprint::of($p, $state['sale'] ? [$state['sale']['sale_id']] : []);
            $this->assertBalanced($after, $marks['je']);
            $result = ['changed' => true, 'summary' => $summary, 'before' => $before, 'after' => $after, 'diff' => Diff::of($before, $after), 'state' => $state, 'edited' => $this->state($p)];

            if (! $commit) {
                DB::rollBack();

                return $result;
            }
            $log = TicketTrailLog::create([
                'company_id' => $p->company_id, 'ticket_purchase_id' => $p->id, 'action' => 'correction',
                'reason' => mb_substr($reason, 0, 2000), 'summary' => implode("\n", $summary),
                'before' => $state, 'after' => $result['edited'], 'replaced' => $this->replaced,
                'added' => [
                    'transactions' => DB::table('transactions')->where('id', '>', $marks['tx'])->pluck('id')->all(),
                    'journals' => DB::table('journal_entries')->where('id', '>', $marks['je'])->pluck('id')->all(),
                ],
                'created_by' => Auth::id(),
            ]);
            if (! $notify) {
                $this->quiet($marks['tx']);
            }
            DB::commit();

            return $result + ['log_id' => $log->id];
        } catch (TrailRefused $e) {
            DB::rollBack();
            throw $e;
        } catch (TicketChangeRefused $e) {
            DB::rollBack();
            throw new TrailRefused($e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            throw new TrailRefused('Nothing was posted: '.$e->getMessage());
        } finally {
            EditLog::$paused = false;
        }
    }

    // ── 1. rewind ─────────────────────────────────────────────────────────

    private function rewind(TicketPurchase $p, array $state): void
    {
        $changes = TicketChange::where('ticket_purchase_id', $p->id)->orderBy('id')->get();
        $ids = $changes->pluck('id')->all();

        $tx = Transaction::where(fn ($q) => $q->where('type', 'ticket_purchase_refund')->where('invoice_id', $p->id))
            ->when($ids, fn ($q) => $q->orWhere(fn ($w) => $w->whereIn('type', Footprint::CHANGE_TX_TYPES)->whereIn('invoice_id', $ids)))
            ->get();
        $this->dropTransactions($tx);

        $je = DB::table('journal_entries')->where(fn ($q) => $q->where('source', 'ticket_purchase_cancel')->where('source_id', $p->id))
            ->when($ids, fn ($q) => $q->orWhere(fn ($w) => $w->whereIn('source', Footprint::CHANGE_JE_SOURCES)->whereIn('source_id', $ids)))
            ->pluck('id');
        $this->dropJournals($je);

        if (Schema::hasTable('portal_balances')) {
            $pb = DB::table('portal_balances')->where(fn ($q) => $q->where('type', 'ticket_purchase_refund')->where('invoice_id', $p->id))
                ->when($ids, fn ($q) => $q->orWhere(fn ($w) => $w->whereIn('type', [TicketChange::FEE_TYPE, TicketChange::FARE_TYPE])->whereIn('invoice_id', $ids)))
                ->get();
            $this->dropWallet($pb);
        }

        // The ticket as bought: live, its first date, no refund.
        $wasCancelled = $p->status === 'cancelled';
        $reset = ['status' => 'confirm', 'cancel_note' => null];
        foreach (['cancelled_at', 'refund_amount', 'return_cancelled_at', 'return_refund_amount', 'return_penalty'] as $col) {
            if (Schema::hasColumn('ticket_purchases', $col)) {
                $reset[$col] = null;
            }
        }
        $p->forceFill($reset)->save();
        // a cancel took its seat back off the route's stock
        if ($wasCancelled && $p->ticket_id) {
            Ticket::whereKey($p->ticket_id)->increment('qty');
        }
        if ($first = $changes->first(fn ($c) => $c->old_travel_at)) {
            $p->moveTravelDate(Carbon::parse($first->old_travel_at)->format('Y-m-d H:i'));
        }

        foreach ($changes as $c) {
            $this->replaced['ticket_changes'][] = $c->getAttributes();
        }
        if ($ids) {
            TicketChange::whereIn('id', $ids)->delete();
        }
    }

    // ── 2. the ticket itself ──────────────────────────────────────────────

    private function applyTicket(TicketPurchase $p, array $state, array $edit): void
    {
        $was = $state['ticket'];
        $now = $edit['ticket'];
        $fields = ['passport_holder_id', 'ticket_id', 'ticket_no', 'ticket_class', 'trip_type', 'purchase_date', 'travel_at', 'seller', 'amount'];
        if (collect($fields)->every(fn ($f) => (string) ($was[$f] ?? '') === (string) ($now[$f] ?? ''))) {
            return;
        }
        [$sellerKind, $sellerId] = array_pad(explode(':', (string) $now['seller']), 2, null);
        $legs = $p->legs()->orderBy('id')->get()->map(fn ($l) => array_filter([
            'from_location' => $l->from_location, 'to_location' => $l->to_location, 'travel_date' => $l->travel_date,
            'seat_number' => $l->seat_number ?? null, 'cost' => $l->cost,
        ] + (isset($l->airline_id) ? ['airline_id' => $l->airline_id] : []), fn ($v) => $v !== null))->values()->all();
        if ((string) $was['travel_at'] !== (string) $now['travel_at'] && $now['travel_at'] && $legs) {
            $legs[0]['travel_date'] = Carbon::parse($now['travel_at'])->format('Y-m-d H:i:s');
        }
        $class = strtolower((string) $now['ticket_class']);
        $payload = array_filter([
            'passport_holder_id' => $now['passport_holder_id'],
            'ticket_id' => $now['ticket_id'],
            'bank_id' => $p->bank_id,
            'ticket_type' => in_array($p->ticket_type, ['air', 'bus', 'train', 'other'], true) ? $p->ticket_type : 'air',
            'trip_type' => in_array($now['trip_type'], ['one-way', 'two-way', 'multi-city'], true) ? $now['trip_type'] : 'one-way',
            'ticket_class' => in_array($class, ['economy', 'business', 'first'], true) ? $class : null,
            'purchase_date' => $now['purchase_date'],
            'ticket_no' => $now['ticket_no'],
            'name_ticket_no' => $p->name_ticket_no,
            'status' => 'confirm',
            'amount' => $now['amount'],
            'currency' => $p->currency ?: 'BDT',
            'legs' => $legs,
            'departure_at' => $now['travel_at'] ? Carbon::parse($now['travel_at'])->format('Y-m-d H:i:s') : null,
            'paid_amount' => 0,
        ], fn ($v) => $v !== null) + [
            // both, always: update() fills with $request->only(), which skips an absent key — the
            // seller not chosen must arrive as null (as the Edit page's empty field does) or it stays
            'vendor_id' => $sellerKind === 'vendor' ? (int) $sellerId : null,
            'portal_id' => $sellerKind === 'portal' ? (int) $sellerId : null,
        ];
        $req = Request::create(url('/'), 'PUT', $payload);
        $req->setUserResolver(fn () => Auth::user());
        $res = app(TicketPurchaseController::class)->update($req, $this->role(), $p->id)->getData(true);
        if (empty($res['success'])) {
            throw new TrailRefused('The ticket was not saved: '.($res['message'] ?? 'refused'));
        }
        $p->refresh();
    }

    // ── 3. payments to the seller ─────────────────────────────────────────

    private function applyPayments(TicketPurchase $p, array $state, array $edit): void
    {
        $sig = fn (array $list) => json_encode(array_map(fn ($x) => [$x['kind'], (int) ($x['bank_id'] ?? 0), round((float) $x['amount'], 2), $x['date'] ?? null, $x['method'] ?? null, $x['ref'] ?? null], $list));
        $sellerChanged = $state['ticket']['seller'] !== $edit['ticket']['seller'];
        if ($sig($state['payments']) === $sig($edit['payments']) && ! $sellerChanged) {
            return;
        }
        $p->refresh();
        $paid = round(array_sum(array_column($edit['payments'], 'amount')), 2);
        if ($paid - (float) $p->amount > 0.005) {
            throw new TrailRefused('Payments to the seller (৳'.number_format($paid, 2).') are more than the ticket\'s cost (৳'.number_format((float) $p->amount, 2).').');
        }

        // out: every payment row, its bank back up, the settlement journals, the wallet row
        $rows = Transaction::where('invoice_id', $p->id)->where('type', 'ticket_purchase')->whereNotNull('account_id')->where('credit', '>', 0)->get();
        foreach ($rows as $r) {
            Bank::whereKey($r->account_id)->increment('balance', (float) $r->credit);
        }
        $this->dropTransactions($rows);
        $first = DB::table('journal_entries')->where('source', 'ticket_purchase')->where('source_id', $p->id)->min('id');
        $this->dropJournals(DB::table('journal_entries')->where('source', 'ticket_purchase')->where('source_id', $p->id)->where('id', '>', (int) $first)->pluck('id'));
        if (Schema::hasTable('portal_balances')) {
            $pb = DB::table('portal_balances')->where('invoice_id', $p->id)->where('type', 'ticket_purchase')->get();
            foreach ($pb as $r) {
                Portal::whereKey($r->portal_id)->increment('balance', (float) $r->credit - (float) $r->debit);
            }
            $this->dropWallet($pb, false);
        }

        // in: what was paid with the purchase rides on its own journal (as Ticket Entry
        // posts it); every later payment is a settlement, as make_payment posts it.
        $tpc = app(TicketPurchaseController::class);
        $all = collect($edit['payments']);
        $atBank = $all->first(fn ($x) => $x['kind'] === 'bank' && ! empty($x['at_purchase']))['bank_id'] ?? null;
        $isAt = fn ($x) => $x['kind'] === 'portal' || (! empty($x['at_purchase']) && (int) $x['bank_id'] === (int) $atBank);
        $atPurchase = $all->filter($isAt);
        $later = $all->reject($isAt);
        foreach ($atPurchase as $x) {
            $req = new Request(['vendor_id' => $p->vendor_id, 'bank_id' => $x['bank_id'] ?? null, 'portal_id' => $p->portal_id,
                'purchase_date' => $x['date'] ?: $p->purchase_date, 'reference_no' => $x['ref'] ?? null, 'payment_method' => $x['method'] ?? null]);
            $x['kind'] === 'portal' && $p->portal_id
                ? $tpc->managePortalPayment($req, $p->id, (float) $x['amount'])
                : $tpc->manageBankPayment($req, $p->id, (float) $x['amount']);
        }
        $atSum = round((float) $atPurchase->sum('amount'), 2);
        $p->forceFill(['paid_amount' => $atSum, 'due_amount' => max(0, round((float) $p->amount - $atSum, 2))] + ($atBank ? ['bank_id' => $atBank] : []))->save();
        $tpc->trailSyncPurchaseJournal($p, $atBank ? (int) $atBank : null);
        foreach ($later as $x) {
            $tpc->postSellerBankPayment($p->refresh(), (int) $x['bank_id'], (float) $x['amount'], $x['date'] ?: now()->toDateString(),
                $x['method'] ?? null, $x['ref'] ?? null, 'Ticket purchase due payment (make_payment).');
        }
        $due = max(0, round((float) $p->amount - $paid, 2));
        $p->forceFill(['paid_amount' => $paid, 'due_amount' => $due, 'payment_status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due')])->save();
    }

    // ── 4. the sale ───────────────────────────────────────────────────────

    private function applySale(TicketPurchase $p, array $state, array $edit): void
    {
        $was = $state['sale'];
        $now = $edit['sale'];
        if (! $was && ! $now) {
            return;
        }
        if ($was && $now && (int) $was['client_id'] === (int) $now['client_id'] && abs($was['price'] - $now['price']) < 0.005) {
            return;
        }
        $sc = app(TicketSaleController::class);
        $item = $was ? TicketSaleItem::where('ticket_sale_id', $was['sale_id'])->where('ticket_purchase_id', $p->id)->first() : null;
        $sale = $was ? TicketSale::find($was['sale_id']) : null;

        // The route's sale stock, as Manage Sales keeps it (TicketSaleController::update):
        // a ticket off an invoice gives its seat back, a price change moves the sold value.
        // It was counted on the route the ticket had when it was sold.
        $stock = function (int $seats, float $amount) use ($state, $sale) {
            if ($sale?->status === 'confirm' && $state['ticket']['ticket_id'] && ($seats || abs($amount) >= 0.005)) {
                DB::table('tickets')->where('id', $state['ticket']['ticket_id'])->update([
                    'qty' => DB::raw('qty + '.$seats),
                    'total_sale_qty' => DB::raw('total_sale_qty - '.$seats),
                    'total_sale_amount' => DB::raw('total_sale_amount + '.round($amount, 2)),
                ]);
            }
        };

        // same buyer, or the invoice is this ticket alone: re-price / move it in place
        if ($was && $now && $item && $sale && ((int) $was['client_id'] === (int) $now['client_id'] || $was['tickets'] <= 1)) {
            $this->replaced['sale_items'][] = $item->getAttributes();
            $oldPrice = (float) $item->price;
            $item->update(['price' => $now['price']]);
            $stock(0, $now['price'] - $oldPrice);
            $sc->resyncFromItems($sale, (int) $now['client_id'] !== (int) $was['client_id'] ? (int) $now['client_id'] : null);

            return;
        }
        // off the old invoice
        if ($was && $item && $sale) {
            $this->replaced['sale_items'][] = $item->getAttributes();
            $stock(1, -(float) $item->price);
            $item->delete();
            if (! TicketSaleItem::where('ticket_sale_id', $sale->id)->exists()) {
                $this->dropInvoice($sale);
            } else {
                $sc->resyncFromItems($sale);
            }
        }
        // onto a new invoice for the new buyer, as Manage Sales would make it
        if ($now) {
            $req = Request::create(url('/'), 'POST', [
                'client_id' => $now['client_id'], 'status' => 'confirm', 'currency' => $p->currency ?: 'BDT',
                'sale_date' => $was['date'] ?? ($p->purchase_date ? Carbon::parse($p->purchase_date)->toDateString() : now()->toDateString()),
                'ticket_purchase_ids' => [$p->id], 'sale_prices' => [$p->id => $now['price']],
                'paid_amount' => 0, 'payment_method' => 'cash', 'payment_status' => 'due',
            ]);
            $req->setUserResolver(fn () => Auth::user());
            $res = $sc->store($req, $this->role())->getData(true);
            if (empty($res['success'])) {
                throw new TrailRefused('The sale was not posted: '.($res['message'] ?? 'refused'));
            }
        }
    }

    /** An invoice whose last ticket left it: refused when the buyer paid on it. */
    private function dropInvoice(TicketSale $sale): void
    {
        if ((float) $sale->paid_amount > 0.005) {
            throw new TrailRefused('Invoice '.$sale->invoice_no.' has a receipt on it — move the receipt before taking its last ticket off.');
        }
        $this->dropTransactions(Transaction::where('type', 'ticket_sale')->where('invoice_id', $sale->id)->get());
        $this->dropJournals(DB::table('journal_entries')->where('source', 'ticket_sale')->where('source_id', $sale->id)->pluck('id'));
        DB::table('payment_schedules')->where('schedulable_type', TicketSale::class)->where('schedulable_id', $sale->id)->delete();
        $sale->delete();
    }

    // ── 5. the changes, posted again ──────────────────────────────────────

    /** Returns new change id => the original change it replaces (null for a new one). */
    private function replay(TicketPurchase $p, array $state, array $edit): array
    {
        $tc = app(TicketChangeController::class);
        $tpc = app(TicketPurchaseController::class);
        $orig = collect($state['events'])->keyBy('key');
        $map = [];
        foreach ($edit['events'] as $e) {
            if (! empty($e['remove'])) {
                continue;
            }
            $o = $orig->get($e['key']);
            $at = Carbon::parse(($e['date'] ?: now()->toDateString()).' '.($o['time'] ?? now()->format('H:i')));
            $p->refresh();
            if ($e['kind'] === 'cancel') {
                $tpc->cancelFromContractFlight($p, $e['note'] ?: 'Cancelled', (float) ($e['refund'] ?? $p->amount), $at);

                continue;
            }
            if ($e['kind'] === 'newticket') {
                if ($p->status !== 'cancelled') {
                    throw new TrailRefused('"New ticket" is made after a refund or void — keep the refund before it, or remove the new ticket change too.');
                }
                $new = TicketPurchase::find($e['new_ticket_id'] ?? 0);
                if (! $new) {
                    throw new TrailRefused('The new ticket made after this refund was not found — it cannot be posted again.');
                }
                $buyer = $this->saleOf($p)?->client_id ?? ($e['client_id'] ?? null);
                $c = $tc->replayNewTicket($p, $new, $e['fare_bearer'] ?: null, (float) $e['service_fee'], $buyer ? (int) $buyer : null, $this->token(), $e['note'] ?? null, $at);
            } else {
                $c = $tc->replay($p, array_filter([
                    'kind' => $e['kind'], 'token' => $this->token(), 'note' => $e['note'] ?? null,
                    'service_fee' => $e['service_fee'], 'penalty' => $e['penalty'], 'fare_difference' => $e['fare_difference'],
                    'refund' => in_array($e['kind'], ['refund', 'void', 'return_cancel'], true) ? $e['refund'] : null,
                    'agent_reversal' => in_array($e['kind'], ['refund', 'void'], true) ? $e['agent_reversal'] : null,
                    'new_travel_at' => in_array($e['kind'], ['reissue', 'reschedule'], true) ? $e['new_travel_at'] : null,
                    'penalty_bearer' => $e['penalty_bearer'] ?: null, 'fare_bearer' => $e['fare_bearer'] ?: null,
                    'penalty_billed' => $e['penalty_billed'],
                    'agent_mode' => $e['kind'] === 'return_cancel' && (float) $e['agent_reversal'] > 0 ? 'giveback' : null,
                    'agent_value' => $e['kind'] === 'return_cancel' && (float) $e['agent_reversal'] > 0 ? $e['agent_reversal'] : null,
                ], fn ($v) => $v !== null), $at);
            }
            // a change left as it was keeps its note, its author and its moment
            $same = $o && $this->sameEvent($o, $e);
            $c->forceFill(array_filter([
                'created_at' => $o['at'] ?? null,
                'created_by' => $o['by_id'] ?? null,
                'note' => $same ? ($o['note'] ?? null) : null,
            ]))->save();
            $map[$c->id] = $o['change_id'] ?? null;
        }

        return $map;
    }

    /**
     * Rows of a change that came back unchanged take their places exactly:
     * the date and time each original row carried (staff may have moved them
     * since — Party Statement's date correction), matched by type, party and
     * side. A change whose day was edited keeps the new day on every row.
     */
    private function restoreDates(TicketPurchase $p, array $before, array $state, array $edit, array $map, array $marks): void
    {
        $orig = collect($state['events'])->keyBy('change_id');
        $edits = collect($edit['events'])->keyBy('key');
        $newTx = Transaction::where('id', '>', $marks['tx'])->get();
        $newJe = DB::table('journal_entries')->where('id', '>', $marks['je'])->get();
        $refundIds = TicketChange::where('ticket_purchase_id', $p->id)->whereIn('kind', ['refund', 'void', 'return_cancel'])->pluck('id');
        $lastRefund = $refundIds->max();

        foreach ($map as $newId => $oldId) {
            $o = $oldId ? $orig->get($oldId) : null;
            $e = $o ? $edits->get($o['key']) : null;
            if (! $o || ! $e || $e['date'] !== $o['date']) {
                continue;
            }
            $belongs = fn ($r) => in_array($r->type, Footprint::CHANGE_TX_TYPES, true) ? (int) $r->invoice_id === (int) $newId
                : ($r->type === 'ticket_purchase_refund' && (int) $r->invoice_id === (int) $p->id && (int) $newId === (int) $lastRefund);
            $olds = collect($before['rows'])->where('src', 'tx')->where('change_id', $oldId)->values();
            $used = [];
            foreach ($newTx->filter($belongs) as $r) {
                $side = (float) $r->debit > 0 ? 'debit' : 'credit';
                // same party first; a row whose party was changed (another seller / buyer) still takes its place
                $m = $olds->first(fn ($x, $i) => ! isset($used[$i]) && $x['type'] === $r->type && (int) $x['party_id'] === (int) $r->user_id && $x[$side] > 0)
                    ?? $olds->first(fn ($x, $i) => ! isset($used[$i]) && $x['type'] === $r->type && $x[$side] > 0);
                if (! $m) {
                    continue;
                }
                $used[$olds->search($m)] = true;
                $copy = collect($this->replaced['transactions'])->firstWhere('id', $m['id']);
                $r->forceFill([
                    'payment_date' => $copy['payment_date'] ?? $m['date'],
                    'transaction_time' => $copy['transaction_time'] ?? null,
                    'created_at' => $copy['created_at'] ?? $r->created_at,
                ])->saveQuietly();
            }
            $oldJ = collect($before['journals'])->where('change_id', $oldId)->values();
            $usedJ = [];
            foreach ($newJe as $j) {
                $mine = in_array($j->source, Footprint::CHANGE_JE_SOURCES, true) ? (int) $j->source_id === (int) $newId
                    : ($j->source === 'ticket_purchase_cancel' && (int) $j->source_id === (int) $p->id && (int) $newId === (int) $lastRefund);
                if (! $mine) {
                    continue;
                }
                $m = $oldJ->first(fn ($x, $i) => ! isset($usedJ[$i]) && $x['source'] === $j->source);
                if (! $m) {
                    continue;
                }
                $usedJ[$oldJ->search($m)] = true;
                $copy = collect($this->replaced['journals'])->firstWhere('entry.id', $m['id'])['entry'] ?? null;
                DB::table('journal_entries')->where('id', $j->id)->update(array_filter([
                    'date' => $copy['date'] ?? $m['date'], 'entry_time' => $copy['entry_time'] ?? null, 'created_at' => $copy['created_at'] ?? null,
                ]));
            }
        }
    }

    // ── helpers: removing rows ────────────────────────────────────────────

    private function dropTransactions(Collection $rows): void
    {
        foreach ($rows as $r) {
            $a = $r instanceof Transaction ? $r->getAttributes() : (array) $r;
            $this->replaced['transactions'][] = $a;
            if ($a['user_id'] ?? null) {
                $this->parties[$a['user_id']] = min($this->parties[$a['user_id']] ?? PHP_INT_MAX, (int) $a['id']);
            }
        }
        if ($rows->isNotEmpty()) {
            DB::table('transactions')->whereIn('id', $rows->pluck('id'))->delete();
        }
    }

    private function dropJournals(Collection $ids): void
    {
        if ($ids->isEmpty()) {
            return;
        }
        foreach (DB::table('journal_entries')->whereIn('id', $ids)->get() as $j) {
            $this->replaced['journals'][] = ['entry' => (array) $j, 'items' => DB::table('journal_items')->where('journal_entry_id', $j->id)->get()->map(fn ($i) => (array) $i)->all()];
        }
        DB::table('journal_items')->whereIn('journal_entry_id', $ids)->delete();
        DB::table('journal_entries')->whereIn('id', $ids)->delete();
    }

    /** Wallet rows out; with $restore the wallet's balance goes back by what each row moved. */
    private function dropWallet(Collection $rows, bool $restore = true): void
    {
        foreach ($rows as $r) {
            $this->replaced['portal_balances'][] = (array) $r;
            $this->portals[$r->portal_id] = min($this->portals[$r->portal_id] ?? PHP_INT_MAX, (int) $r->id);
            if ($restore) {
                Portal::whereKey($r->portal_id)->decrement('balance', (float) $r->debit - (float) $r->credit);
            }
        }
        if ($rows->isNotEmpty()) {
            DB::table('portal_balances')->whereIn('id', $rows->pluck('id'))->delete();
        }
    }

    /** Running balances re-walked for every party and wallet a row left or joined. */
    private function rewalk(array $marks): void
    {
        foreach (DB::table('transactions')->where('id', '>', $marks['tx'])->whereNotNull('user_id')->groupBy('user_id')->selectRaw('user_id, MIN(id) m')->get() as $n) {
            $this->parties[$n->user_id] = min($this->parties[$n->user_id] ?? PHP_INT_MAX, (int) $n->m);
        }
        foreach ($this->parties as $party => $from) {
            $this->rewalkParty((int) $party, (int) $from);
        }
        if (! Schema::hasTable('portal_balances')) {
            return;
        }
        foreach (DB::table('portal_balances')->where('id', '>', $marks['pb'])->groupBy('portal_id')->selectRaw('portal_id, MIN(id) m')->get() as $n) {
            $this->portals[$n->portal_id] = min($this->portals[$n->portal_id] ?? PHP_INT_MAX, (int) $n->m);
        }
        foreach ($this->portals as $portal => $from) {
            $running = (float) (DB::table('portal_balances')->where('portal_id', $portal)->where('id', '<', $from)->orderByDesc('id')->value('current_balance') ?? 0);
            foreach (DB::table('portal_balances')->where('portal_id', $portal)->where('id', '>=', $from)->orderBy('id')->get() as $r) {
                $old = $running;
                $running = round($running + (float) $r->debit - (float) $r->credit, 2);
                if (abs((float) $r->old_balance - $old) > 0.005 || abs((float) $r->current_balance - $running) > 0.005) {
                    DB::table('portal_balances')->where('id', $r->id)->update(['old_balance' => $old, 'current_balance' => $running]);
                }
            }
        }
    }

    /**
     * One party's stored running balance re-walked from a row on. An opening
     * balance row is an anchor — the statement restarts there (27 of 30 restart
     * at 0) — so it is never rewritten and the walk carries on from it.
     */
    private function rewalkParty(int $party, int $from): void
    {
        $running = (float) (DB::table('transactions')->where('user_id', $party)->where('id', '<', $from)->orderByDesc('id')->value('balance') ?? 0);
        foreach (DB::table('transactions')->where('user_id', $party)->where('id', '>=', $from)->orderBy('id')->get(['id', 'type', 'old_balance', 'debit', 'credit', 'balance']) as $r) {
            if ($r->type === 'opening_balance') {
                $running = (float) $r->balance;

                continue;
            }
            $old = $running;
            $running = round($running + (float) $r->debit - (float) $r->credit, 2);
            if (abs((float) $r->old_balance - $old) > 0.005 || abs((float) $r->balance - $running) > 0.005) {
                DB::table('transactions')->where('id', $r->id)->update(['old_balance' => $old, 'balance' => $running]);
            }
        }
    }

    /** Every journal this correction posted or rewrote must balance — or nothing is posted. */
    private function assertBalanced(array $fp, int $jeMark): void
    {
        foreach ($fp['journals'] as $j) {
            if ($j['id'] <= $jeMark && ! in_array($j['source'], ['ticket_purchase', 'ticket_sale'], true)) {
                continue;   // not touched by this correction (a flight's own entries, say)
            }
            $dr = round(array_sum(array_column($j['lines'], 'debit')), 2);
            $cr = round(array_sum(array_column($j['lines'], 'credit')), 2);
            if (abs($dr - $cr) > 0.01) {
                throw new TrailRefused('Journal '.$j['source'].' #'.$j['id'].' would not balance (Dr ৳'.number_format($dr, 2).' / Cr ৳'.number_format($cr, 2).') — nothing was posted.');
            }
        }
    }

    /** The corrected rows are marked as already told, so the next notice does not list them as new. */
    private function quiet(int $txMark): void
    {
        if (! Schema::hasTable('party_ledger_notifications')) {
            return;
        }
        $rows = DB::table('transactions')->where('id', '>', $txMark)->whereNotNull('user_id')->groupBy('user_id')
            ->selectRaw('user_id, MIN(id) a, MAX(id) b, COUNT(*) n')->get();
        foreach ($rows as $r) {
            PartyLedgerNotification::create([
                'user_id' => $r->user_id, 'kind' => 'trail', 'sent_by' => Auth::id(),
                'from_transaction_id' => $r->a, 'to_transaction_id' => $r->b, 'entry_count' => $r->n,
                'email_status' => 'skipped', 'sms_status' => 'skipped',
            ]);
        }
    }

    // ── reading the ticket ────────────────────────────────────────────────

    private function paymentsOf(TicketPurchase $t): array
    {
        $out = [];
        foreach (Transaction::where('invoice_id', $t->id)->where('type', 'ticket_purchase')->whereNotNull('account_id')->where('credit', '>', 0)->orderBy('id')->get() as $r) {
            $out[] = ['id' => (int) $r->id, 'kind' => 'bank', 'bank_id' => (int) $r->account_id,
                'bank' => DB::table('banks')->where('id', $r->account_id)->value('name'),
                'amount' => round((float) $r->credit, 2), 'date' => substr((string) $r->payment_date, 0, 10),
                'method' => $r->payment_method, 'ref' => $r->reference_no,
                // paid with the purchase (Ticket Entry / the Edit page) rides on the purchase's own journal
                'at_purchase' => str_starts_with((string) $r->remarks, 'Ticket purchase with bank from purchase')];
        }
        if (Schema::hasTable('portal_balances')) {
            foreach (DB::table('portal_balances')->where('invoice_id', $t->id)->where('type', 'ticket_purchase')->orderBy('id')->get() as $r) {
                $out[] = ['id' => (int) $r->id, 'kind' => 'portal', 'bank_id' => null, 'bank' => DB::table('portals')->where('id', $r->portal_id)->value('name').' (wallet)',
                    'amount' => round((float) $r->credit - (float) $r->debit, 2), 'date' => substr((string) ($r->date ?: $r->created_at), 0, 10),
                    'method' => $r->payment_method, 'ref' => $r->reference, 'at_purchase' => true];
            }
        }

        return $out;
    }

    private function eventsOf(TicketPurchase $t, Collection $changes): array
    {
        $out = [];
        $refundish = $changes->whereIn('kind', ['refund', 'void', 'return_cancel']);
        $lastRefund = $refundish->last()?->id;
        foreach ($changes->values() as $i => $c) {
            if ($c->kind === 'refund_correction') {
                continue;   // folded: correctRefund rewrote its refund's own figures
            }
            $firstRow = DB::table('transactions')->where('invoice_id', $c->id)->whereIn('type', Footprint::CHANGE_TX_TYPES)->min('payment_date');
            if (! $firstRow && in_array($c->kind, ['refund', 'void'], true) && $c->id === $lastRefund) {
                $firstRow = DB::table('transactions')->where('invoice_id', $t->id)->where('type', 'ticket_purchase_refund')->min('payment_date');
            }
            $date = $firstRow ? substr((string) $firstRow, 0, 10) : $c->created_at?->toDateString();
            $e = [
                'key' => 'c'.$c->id, 'change_id' => (int) $c->id, 'kind' => $c->kind, 'label' => TicketChange::KINDS[$c->kind] ?? $c->kind,
                'date' => $date, 'time' => $c->created_at?->format('H:i'), 'at' => $c->created_at?->format('Y-m-d H:i:s'),
                'by' => $c->creator?->name, 'by_id' => $c->created_by ? (int) $c->created_by : null, 'note' => $c->note,
                'penalty' => 0.0, 'penalty_bearer' => null, 'fare_difference' => 0.0, 'fare_bearer' => null,
                'service_fee' => round((float) $c->service_fee, 2), 'refund' => null, 'agent_reversal' => null,
                'new_travel_at' => null, 'old_travel_at' => null, 'penalty_billed' => true, 'new_ticket_id' => null, 'client_id' => $c->client_id,
            ];
            if (in_array($c->kind, ['reissue', 'reschedule'], true)) {
                [$penBy, $fareBy] = $this->bearers($c);
                $e = array_merge($e, ['penalty' => round((float) $c->penalty, 2), 'fare_difference' => round((float) $c->fare_difference, 2),
                    'penalty_bearer' => $penBy, 'fare_bearer' => $fareBy,
                    'new_travel_at' => $c->new_travel_at?->format('Y-m-d H:i'), 'old_travel_at' => $c->old_travel_at?->format('Y-m-d H:i')]);
            } elseif (in_array($c->kind, ['refund', 'void'], true)) {
                $refund = $c->id === $lastRefund && $t->refund_amount !== null ? (float) $t->refund_amount : (float) $c->refund_amount;
                $kept = round(max(0, (float) $t->amount - $refund), 2);
                $e = array_merge($e, ['refund' => round($refund, 2), 'penalty' => $kept, 'penalty_bearer' => $kept > 0 ? $c->bearer : null,
                    'agent_reversal' => round((float) $c->sale_reversed, 2),
                    // a corrected refund kept the penalty inside his reduced refund — not billed again
                    'penalty_billed' => ! ($c->bearer === 'customer' && $kept > 0 && (float) $c->charged < $kept + (float) $c->service_fee - 0.005)]);
            } elseif ($c->kind === 'return_cancel') {
                $e = array_merge($e, ['refund' => round((float) $c->refund_amount, 2), 'penalty' => round((float) $c->penalty, 2),
                    'penalty_bearer' => $c->bearer, 'agent_reversal' => round((float) $c->sale_reversed, 2)]);
            } elseif ($c->kind === 'newticket') {
                $pnr = preg_match('/^New ticket (.+?) ·/u', (string) $c->note, $m) ? trim($m[1]) : null;
                $new = $pnr ? TicketPurchase::where('passport_holder_id', $t->passport_holder_id)->where('ticket_no', $pnr)->where('id', '!=', $t->id)->latest('id')->first() : null;
                $e = array_merge($e, ['fare_difference' => round((float) $c->fare_difference, 2), 'fare_bearer' => $c->bearer, 'new_ticket_id' => $new?->id, 'new_ticket' => $new?->ticket_no ?: $pnr]);
            }
            $out[] = $e;
        }
        // cancelled from the Edit page: no change row, the refund sits on the purchase
        if ($t->status === 'cancelled' && $refundish->isEmpty()) {
            $at = $t->cancelled_at ? Carbon::parse($t->cancelled_at) : $t->updated_at;
            $out[] = ['key' => 'cancel', 'change_id' => null, 'kind' => 'cancel', 'label' => 'Cancelled', 'date' => $at?->toDateString(),
                'time' => $at?->format('H:i'), 'at' => $at?->format('Y-m-d H:i:s'), 'by' => null, 'by_id' => null, 'note' => $t->cancel_note,
                'penalty' => round(max(0, (float) $t->amount - (float) $t->refund_amount), 2), 'penalty_bearer' => null, 'fare_difference' => 0.0, 'fare_bearer' => null,
                'service_fee' => 0.0, 'refund' => $t->refund_amount !== null ? round((float) $t->refund_amount, 2) : round((float) $t->amount, 2),
                'agent_reversal' => null, 'new_travel_at' => null, 'old_travel_at' => null, 'penalty_billed' => true, 'new_ticket_id' => null, 'client_id' => null];
        }

        return $out;
    }

    /** Who bore the penalty and the fare difference of a re-issue — the agent's own line says when it was split. */
    private function bearers(TicketChange $c): array
    {
        if ($c->bearer !== 'mixed') {
            return [(float) $c->penalty > 0 ? $c->bearer : null, (float) $c->fare_difference > 0 ? $c->bearer : null];
        }
        $lines = DB::table('journal_items as ji')->join('journal_entries as j', 'j.id', '=', 'ji.journal_entry_id')->join('accounts as a', 'a.id', '=', 'ji.account_id')
            ->where('j.source', TicketChange::CHARGE_TYPE)->where('j.source_id', $c->id)->pluck('ji.credit', 'a.code');

        return [(float) $c->penalty > 0 ? (((float) ($lines[config('accounts.cf_penalty_recovered')] ?? 0)) > 0 ? 'customer' : 'company') : null,
            (float) $c->fare_difference > 0 ? (((float) ($lines[config('accounts.cf_fare_diff_recovered')] ?? 0)) > 0 ? 'customer' : 'company') : null];
    }

    private function saleOf(TicketPurchase $t): ?object
    {
        $s = DB::table('ticket_sale_items as i')->join('ticket_sales as s', 's.id', '=', 'i.ticket_sale_id')->leftJoin('users as u', 'u.id', '=', 's.client_id')
            ->where('i.ticket_purchase_id', $t->id)->whereNull('s.deleted_at')->where('s.status', '!=', 'cancelled')->whereNotNull('s.client_id')
            ->orderByDesc('i.id')->first(['s.id', 's.invoice_no', 's.client_id', 'u.name as client', 'i.price', 's.paid_amount', 's.sale_date']);
        if ($s) {
            $s->tickets = DB::table('ticket_sale_items')->where('ticket_sale_id', $s->id)->count();
        }

        return $s;
    }

    // ── edits in, words out ───────────────────────────────────────────────

    /** The edit, completed from the state and checked. */
    private function normalise(array $edit, array $state): array
    {
        $money = fn ($v, $d = 0.0) => $v === null || $v === '' ? $d : round(max(0, (float) $v), 2);
        $t = array_merge($state['ticket'], array_intersect_key($edit['ticket'] ?? [], $state['ticket']));
        $t['amount'] = $money($t['amount']);
        $t['passport_holder_id'] = (int) $t['passport_holder_id'];
        $t['ticket_id'] = (int) $t['ticket_id'];
        if ($t['travel_at']) {
            $t['travel_at'] = Carbon::parse($t['travel_at'])->format('Y-m-d H:i');
        }
        if (! preg_match('/^(vendor|portal):(\d+)$/', (string) $t['seller'], $sm)) {
            throw new TrailRefused('Choose who the ticket was bought from.');
        }
        // ids come from the browser: each must be what its box says it is
        if ($sm[1] === 'vendor' ? ! User::role('vendor')->whereKey((int) $sm[2])->exists() : ! DB::table('portals')->where('id', (int) $sm[2])->exists()) {
            throw new TrailRefused('That seller is not a vendor or portal.');
        }
        if (! DB::table('passport_holders')->where('id', $t['passport_holder_id'])->exists() || ! DB::table('tickets')->where('id', $t['ticket_id'])->exists()) {
            throw new TrailRefused('Choose the passenger and the route from the lists.');
        }

        // A contract-flight ticket: the passport is the flight passenger's and the agent is
        // billed per head on the flight — neither is changed from a ticket.
        if (! empty($state['cf'])) {
            if ($t['passport_holder_id'] !== (int) $state['ticket']['passport_holder_id']) {
                throw new TrailRefused('This ticket is '.$state['cf']['passenger'].'\'s on '.($state['cf']['flight'] ?: 'his flight').' — the passenger is changed on the flight, not here.');
            }
            if (! empty($edit['sale']['client_id'])) {
                throw new TrailRefused('A contract-flight ticket is billed per head on the flight (agent '.($state['cf']['agent'] ?: '—').') — it is never sold on its own.');
            }
            $edit['sale'] = null;
            $edit['events'] = [];
        }

        $payments = [];
        foreach ((array) ($edit['payments'] ?? $state['payments']) as $x) {
            if (! empty($x['remove']) || $money($x['amount'] ?? 0) <= 0) {
                continue;
            }
            $kind = ($x['kind'] ?? 'bank') === 'portal' ? 'portal' : 'bank';
            if ($kind === 'bank' && (empty($x['bank_id']) || ! DB::table('banks')->where('id', (int) $x['bank_id'])->exists())) {
                throw new TrailRefused('Choose the bank for each payment to the seller.');
            }
            $payments[] = ['id' => isset($x['id']) ? (int) $x['id'] : null, 'kind' => $kind, 'bank_id' => $kind === 'bank' ? (int) $x['bank_id'] : null,
                'amount' => $money($x['amount']), 'date' => ! empty($x['date']) ? Carbon::parse($x['date'])->toDateString() : null,
                'method' => $x['method'] ?? null, 'ref' => $x['ref'] ?? null, 'at_purchase' => ! empty($x['at_purchase'])];
        }

        $sale = array_key_exists('sale', $edit) ? $edit['sale'] : $state['sale'];
        if ($sale && (empty($sale['client_id']) || ($sale['price'] ?? '') === '')) {
            $sale = null;
        }
        if ($sale) {
            $sale = ['client_id' => (int) $sale['client_id'], 'price' => $money($sale['price'])];
            if ($sale['client_id'] !== (int) ($state['sale']['client_id'] ?? 0) && ! User::role(['agent', 'customer'])->whereKey($sale['client_id'])->exists()) {
                throw new TrailRefused('A ticket is sold to an agent or a customer — that party is neither.');
            }
        }

        $orig = collect($state['events'])->keyBy('key');
        $events = [];
        foreach ((array) ($edit['events'] ?? $state['events']) as $e) {
            $o = $orig->get($e['key'] ?? '') ?? [];
            $e = array_merge($o, $e);
            $kind = $e['kind'] ?? null;
            $allowed = match ($o['kind'] ?? $kind) {
                'newticket' => ['newticket'],
                'return_cancel' => ['return_cancel'],
                default => ['reissue', 'reschedule', 'refund', 'void', 'cancel'],
            };
            if (! in_array($kind, $allowed, true)) {
                throw new TrailRefused('A '.($o['label'] ?? 'change').' can only become: '.implode(', ', array_map(fn ($k) => TicketChange::KINDS[$k] ?? ucfirst($k), $allowed)).'.');
            }
            foreach (['penalty_bearer', 'fare_bearer'] as $b) {
                if (! empty($e[$b]) && ! array_key_exists($e[$b], TicketChange::BEARERS)) {
                    $e[$b] = null;
                }
            }
            $events[] = [
                'key' => $e['key'] ?? 'new'.count($events), 'change_id' => $o['change_id'] ?? null, 'kind' => $kind, 'remove' => ! empty($e['remove']),
                'date' => ! empty($e['date']) ? Carbon::parse($e['date'])->toDateString() : ($o['date'] ?? now()->toDateString()),
                'penalty' => $money($e['penalty'] ?? 0), 'fare_difference' => $money($e['fare_difference'] ?? 0), 'service_fee' => $money($e['service_fee'] ?? 0),
                'refund' => ($e['refund'] ?? '') === '' || $e['refund'] === null ? null : $money($e['refund']),
                'agent_reversal' => ($e['agent_reversal'] ?? '') === '' || $e['agent_reversal'] === null ? null : $money($e['agent_reversal']),
                'penalty_bearer' => $e['penalty_bearer'] ?? null, 'fare_bearer' => $e['fare_bearer'] ?? null,
                'new_travel_at' => ! empty($e['new_travel_at']) ? Carbon::parse($e['new_travel_at'])->format('Y-m-d H:i') : null,
                'penalty_billed' => (bool) ($e['penalty_billed'] ?? true), 'note' => $e['user_note'] ?? null,
                'new_ticket_id' => $e['new_ticket_id'] ?? null, 'client_id' => $e['client_id'] ?? null,
            ];
        }

        return ['ticket' => $t, 'payments' => $payments, 'sale' => $sale, 'events' => $events];
    }

    /** One line per thing changed — the confirm sheet and the log. */
    private function describe(array $state, array $edit): array
    {
        $out = [];
        $m = fn ($v) => '৳'.number_format((float) $v, 2);
        $names = fn (string $table, $id) => $id ? DB::table($table)->where('id', $id)->value($table === 'tickets' ? 'title' : 'name') : '—';
        $seller = function ($key) {
            [$k, $id] = array_pad(explode(':', (string) $key), 2, null);

            return $k === 'portal' ? DB::table('portals')->where('id', $id)->value('name').' (portal)' : DB::table('users')->where('id', $id)->value('name');
        };
        $a = $state['ticket'];
        $b = $edit['ticket'];
        $labels = ['passport_holder_id' => 'Passenger', 'ticket_id' => 'Route', 'ticket_no' => 'PNR', 'ticket_class' => 'Class', 'trip_type' => 'Trip',
            'purchase_date' => 'Purchase date', 'travel_at' => 'Travel date (as bought)', 'seller' => 'Bought from', 'amount' => 'Cost'];
        foreach ($labels as $f => $label) {
            if ((string) ($a[$f] ?? '') === (string) ($b[$f] ?? '')) {
                continue;
            }
            $out[] = $label.': '.match ($f) {
                'passport_holder_id' => $names('passport_holders', $a[$f]).' → '.$names('passport_holders', $b[$f]),
                'ticket_id' => $names('tickets', $a[$f]).' → '.$names('tickets', $b[$f]),
                'seller' => $seller($a[$f]).' → '.$seller($b[$f]),
                'amount' => $m($a[$f]).' → '.$m($b[$f]),
                default => ($a[$f] ?: '—').' → '.($b[$f] ?: '—'),
            };
        }

        $pays = fn (array $l) => collect($l)->map(fn ($x) => ($x['kind'] === 'portal' ? 'wallet' : DB::table('banks')->where('id', $x['bank_id'])->value('name')).' '.$m($x['amount']).($x['date'] ? ' on '.$x['date'] : ''))->implode(', ');
        $pa = $pays($state['payments']);
        $pb = $pays($edit['payments']);
        if ($pa !== $pb) {
            $out[] = 'Paid to the seller: '.($pa ?: 'nothing').' → '.($pb ?: 'nothing');
        }

        $sa = $state['sale'];
        $sb = $edit['sale'];
        if (($sa['client_id'] ?? null) !== ($sb['client_id'] ?? null)) {
            $out[] = 'Sold to: '.($sa ? $names('users', $sa['client_id']) : 'not sold').' → '.($sb ? $names('users', $sb['client_id']) : 'not sold');
        }
        if ($sa && $sb && abs($sa['price'] - $sb['price']) >= 0.005 || (! $sa && $sb)) {
            $out[] = 'Agent price: '.($sa ? $m($sa['price']) : '—').' → '.$m($sb['price']);
        }

        $orig = collect($state['events'])->keyBy('key');
        foreach ($edit['events'] as $i => $e) {
            $o = $orig->get($e['key']);
            $name = 'Change '.($i + 1).' ('.($o['label'] ?? TicketChange::KINDS[$e['kind']] ?? $e['kind']).')';
            if (! $o) {
                $out[] = $name.': added';

                continue;
            }
            if ($e['remove']) {
                $out[] = $name.': removed';

                continue;
            }
            if (! $this->sameEvent($o, $e)) {
                $diffs = [];
                if ($o['kind'] !== $e['kind']) {
                    $diffs[] = ($o['label']).' → '.(TicketChange::KINDS[$e['kind']] ?? ucfirst($e['kind']));
                }
                foreach (['date' => 'day', 'penalty' => 'penalty', 'fare_difference' => 'fare difference', 'service_fee' => 'our charge', 'refund' => 'airline refund',
                    'agent_reversal' => 'agent refund', 'penalty_bearer' => 'penalty borne by', 'fare_bearer' => 'fare borne by', 'new_travel_at' => 'new date'] as $f => $l) {
                    if ((string) ($o[$f] ?? '') !== (string) ($e[$f] ?? '')) {
                        $fmt = fn ($v) => in_array($f, ['penalty', 'fare_difference', 'service_fee', 'refund', 'agent_reversal'], true) && $v !== null ? $m($v) : (TicketChange::BEARERS[$v] ?? ($v ?: '—'));
                        $diffs[] = $l.' '.$fmt($o[$f] ?? null).' → '.$fmt($e[$f] ?? null);
                    }
                }
                $out[] = $name.': '.implode('; ', $diffs);
            }
        }

        return $out;
    }

    private function sameEvent(array $o, array $e): bool
    {
        foreach (['kind', 'date', 'penalty', 'fare_difference', 'service_fee', 'refund', 'agent_reversal', 'penalty_bearer', 'fare_bearer', 'new_travel_at', 'penalty_billed'] as $f) {
            $x = $o[$f] ?? null;
            $y = $e[$f] ?? null;
            if (is_numeric($x) || is_numeric($y)) {
                if (abs((float) $x - (float) $y) >= 0.005 || ($x === null) !== ($y === null)) {
                    return false;
                }
            } elseif ((string) $x !== (string) $y) {
                return false;
            }
        }

        return empty($e['remove']);
    }

    private function isContractFlight(TicketPurchase $t): bool
    {
        try {
            if (DB::table('contract_flight_passenger_tickets')->where('ticket_purchase_id', $t->id)->exists()) {
                return true;
            }
            if (Schema::hasTable('ticket_changes') && DB::table('ticket_changes')->where('ticket_purchase_id', $t->id)
                ->where(fn ($q) => $q->where('source', '!=', 'ticketing')->orWhereNotNull('contract_flight_passenger_id'))->exists()) {
                return true;
            }

            return Schema::hasColumn('contract_flight_passenger_changes', 'previous_ticket_purchase_ids')
                && DB::table('contract_flight_passenger_changes')->whereJsonContains('previous_ticket_purchase_ids', (int) $t->id)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function token(): string
    {
        return 'TR'.now()->format('ymdHis').Str::lower(Str::random(10));
    }

    private function role(): string
    {
        return Str::slug((string) Auth::user()?->getRoleNames()->first()) ?: 'super-admin';
    }
}
