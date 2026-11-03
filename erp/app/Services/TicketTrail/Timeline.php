<?php

namespace App\Services\TicketTrail;

use App\Models\TicketChange;
use App\Models\TicketPurchase;
use App\Services\TicketBaggage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everything that happened to one ticket, oldest first (owner, 2026-10-26:
 * "all history" in the Change dialog, "all trails, times" in the Trail):
 * booked, entered, sold, paid, received, each change, each edit (from the
 * day EditLog exists) and each Trail correction — with who and when.
 * Read-only; every part is guarded so a missing table leaves it out.
 */
class Timeline
{
    public static function of(TicketPurchase $t): array
    {
        $items = [];
        $money = fn ($v) => '৳'.number_format((float) $v, 2);
        $names = [];
        $who = function ($id) use (&$names) {
            if (! $id) {
                return null;
            }

            return $names[$id] ??= DB::table('users')->where('id', $id)->value('name');
        };
        $add = function (?string $at, string $kind, string $icon, string $title, array $lines = [], $by = null, string $tone = 'plain') use (&$items, $who) {
            if (! $at) {
                return;
            }
            $items[] = ['at' => Carbon::parse($at)->format('Y-m-d H:i:s'), 'kind' => $kind, 'icon' => $icon, 'tone' => $tone, 'title' => $title,
                'lines' => array_values(array_filter($lines, fn ($l) => $l !== null && $l !== '')), 'by' => is_numeric($by) ? $who($by) : $by];
        };
        $t->loadMissing(['passportHolder:id,name', 'ticket:id,title', 'vendor:id,name', 'portal:id,name']);
        $pnr = $t->ticket_no ?: '#'.$t->id;

        try {
            if (Schema::hasColumn('ticket_bookings', 'confirmed_purchase_id')) {
                foreach (DB::table('ticket_bookings')->where('confirmed_purchase_id', $t->id)->get() as $b) {
                    $add($b->created_at, 'booked', 'fa-clock', 'Booked (on hold)', [$b->expires_at ?? null ? 'hold until '.Carbon::parse($b->expires_at)->format('d M Y, h:i A') : null], $b->created_by ?? null, 'info');
                }
            }
        } catch (\Throwable) {
        }

        $add($t->created_at?->format('Y-m-d H:i:s'), 'entered', 'fa-ticket', 'Entered — '.$pnr, [
            'Cost '.$money($t->amount).' · bought from '.($t->vendor?->name ?: ($t->portal?->name ? $t->portal->name.' (portal)' : '—')),
            implode(' · ', array_filter([$t->passportHolder?->name, $t->ticket?->title, $t->purchase_date ? 'purchased '.Carbon::parse($t->purchase_date)->format('d M Y') : null])),
        ], $t->created_by, 'ok');

        // sold
        foreach (DB::table('ticket_sale_items as i')->join('ticket_sales as s', 's.id', '=', 'i.ticket_sale_id')->leftJoin('users as u', 'u.id', '=', 's.client_id')
            ->where('i.ticket_purchase_id', $t->id)->get(['i.created_at', 'i.price', 's.invoice_no', 's.created_by', 's.deleted_at', 'u.name as client']) as $s) {
            $add($s->created_at, 'sold', 'fa-user-tie', 'Sold to '.($s->client ?: '—'), [$s->invoice_no.' · agent price '.$money($s->price), $s->deleted_at ? 'invoice deleted since' : null], $s->created_by, 'ok');
        }

        // paid to the seller (per-ticket payments and the wallet)
        foreach (DB::table('transactions')->where('invoice_id', $t->id)->where('type', 'ticket_purchase')->whereNotNull('account_id')->where('credit', '>', 0)->get() as $r) {
            $add(substr((string) $r->payment_date, 0, 10).' '.substr((string) $r->created_at, 11, 8), 'paid', 'fa-building-columns', 'Paid to the seller '.$money($r->credit),
                [DB::table('banks')->where('id', $r->account_id)->value('name'), $r->reference_no], null, 'ok');
        }
        if (Schema::hasTable('portal_balances')) {
            foreach (DB::table('portal_balances')->where('invoice_id', $t->id)->where('type', 'ticket_purchase')->get() as $r) {
                $add($r->created_at, 'paid', 'fa-wallet', 'Paid from the wallet '.$money((float) $r->credit - (float) $r->debit), [DB::table('portals')->where('id', $r->portal_id)->value('name')], $r->created_by ?? null, 'ok');
            }
        }

        // receipts on its invoice (the whole invoice's money)
        $saleIds = DB::table('ticket_sale_items')->where('ticket_purchase_id', $t->id)->pluck('ticket_sale_id');
        if ($saleIds->isNotEmpty()) {
            foreach (DB::table('transactions')->where('type', 'ticket_sale')->whereIn('invoice_id', $saleIds)->whereNotNull('account_id')->where('credit', '>', 0)->get() as $r) {
                $add(substr((string) $r->payment_date, 0, 10).' '.substr((string) $r->created_at, 11, 8), 'received', 'fa-hand-holding-dollar', 'Received '.$money($r->credit).' on '.$r->reference_no,
                    ['whole invoice', DB::table('banks')->where('id', $r->account_id)->value('name')], null, 'ok');
            }
        }

        foreach (DB::table('journal_entries')->whereNull('deleted_at')->where('source_id', $t->id)
            ->whereIn('source', [TicketBaggage::COST, TicketBaggage::CHARGE])->orderBy('id')->get() as $entry) {
            $add($entry->created_at, 'baggage', 'fa-suitcase', $entry->description, [$entry->beneficiary], $entry->created_by, 'info');
        }

        // changes
        if (Schema::hasTable('ticket_changes')) {
            foreach (TicketChange::where('ticket_purchase_id', $t->id)->orderBy('id')->get() as $c) {
                $tone = in_array($c->kind, ['refund', 'void', 'return_cancel'], true) ? 'bad' : ($c->kind === 'refund_correction' ? 'warn' : 'info');
                $add($c->created_at?->format('Y-m-d H:i:s'), 'change', match ($c->kind) {
                    'refund' => 'fa-rotate-left', 'void' => 'fa-ban', 'reissue', 'reschedule' => 'fa-arrows-rotate',
                    'newticket' => 'fa-ticket', 'return_cancel' => 'fa-plane-arrival', default => 'fa-pen',
                }, TicketChange::KINDS[$c->kind] ?? ucfirst($c->kind), [$c->note], $c->created_by, $tone);
            }
            // made from another ticket's refund
            foreach (TicketChange::where('kind', 'newticket')->where('ticket_purchase_id', '!=', $t->id)->where('note', 'like', 'New ticket '.$t->ticket_no.' ·%')->get() as $c) {
                $old = DB::table('ticket_purchases')->where('id', $c->ticket_purchase_id)->value('ticket_no');
                $add($c->created_at?->format('Y-m-d H:i:s'), 'origin', 'fa-link', 'Made after the refund of '.$old, [$c->note], $c->created_by, 'info');
            }
        }

        // on a contract flight: when he was put on it, and every change the flight made to him
        try {
            $cf = Footprint::flightKeys($t);
            if ($cf['pax']) {
                foreach (DB::table('contract_flight_passengers as p')->leftJoin('contract_flights as f', 'f.id', '=', 'p.contract_flight_id')->leftJoin('users as u', 'u.id', '=', 'p.agent_id')
                    ->whereIn('p.id', $cf['pax'])->get(['p.created_at', 'p.agent_price', 'f.flight_number', 'u.name as agent']) as $p) {
                    // contract_flight_passengers keeps no created_by — who put him on is not recorded
                    $add($p->created_at, 'flight', 'fa-plane-departure', 'On contract flight '.($p->flight_number ?: '—'), [
                        'agent '.($p->agent ?: '—').' · package '.$money($p->agent_price).' (billed per head on the flight)',
                    ], null, 'info');
                }
                foreach (DB::table('contract_flight_passenger_changes')->whereIn('contract_flight_passenger_id', $cf['pax'])->orderBy('id')->get() as $c) {
                    $add($c->created_at, 'flight', 'fa-plane', 'Flight change — '.str_replace('_', ' ', (string) $c->kind), [$c->note], $c->created_by, in_array($c->kind, ['cancelled', 'ticket_refund', 'flagged'], true) ? 'bad' : 'info');
                }
            }
        } catch (\Throwable) {
        }

        // cancelled from the Edit page (no change row)
        if ($t->status === 'cancelled' && $t->cancelled_at && ! (Schema::hasTable('ticket_changes') && TicketChange::where('ticket_purchase_id', $t->id)->whereIn('kind', ['refund', 'void', 'return_cancel'])->exists())) {
            $add(Carbon::parse($t->cancelled_at)->format('Y-m-d H:i:s'), 'change', 'fa-ban', 'Cancelled', [$t->cancel_note, $t->refund_amount !== null ? 'airline refund '.$money($t->refund_amount) : null], null, 'bad');
        }

        // edits and Trail corrections
        if (Schema::hasTable('ticket_trail_logs')) {
            foreach (DB::table('ticket_trail_logs')->where('ticket_purchase_id', $t->id)->orderBy('id')->get() as $l) {
                $lines = array_filter(explode("\n", (string) $l->summary));
                if ($l->action === 'correction') {
                    $add($l->created_at, 'corrected', 'fa-wand-magic-sparkles', 'Corrected on the Trail', array_merge($lines, [$l->reason ? 'Reason: '.$l->reason : null]), $l->created_by, 'warn');
                } else {
                    $add($l->created_at, 'edited', 'fa-pencil', 'Edited'.($l->reason ? ' — '.$l->reason : ''), $lines, $l->created_by, 'plain');
                }
            }
        }
        // before edits were recorded: at least the last one — unless that save WAS one of the events above
        $lastSave = $t->updated_at?->getTimestamp();
        $explained = $lastSave && collect($items)->contains(fn ($i) => abs(strtotime($i['at']) - $lastSave) <= 60);
        if ($t->updated_by && $lastSave && ! $explained
            && ! (Schema::hasTable('ticket_trail_logs') && DB::table('ticket_trail_logs')->where('ticket_purchase_id', $t->id)->exists())) {
            $add($t->updated_at->format('Y-m-d H:i:s'), 'edited', 'fa-pencil', 'Last saved', ['what changed was not recorded before 26 Oct 2026'], $t->updated_by, 'plain');
        }

        usort($items, fn ($a, $b) => strcmp($a['at'], $b['at']));

        return $items;
    }
}
