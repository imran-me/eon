<?php

namespace App\Services\TicketTrail;

use App\Models\TicketPurchase;
use App\Models\TicketTrailLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every edit of a ticket, from whichever screen saved it (owner, 2026-10-26:
 * "shows every edits history"). Called from TicketPurchase's `updated` hook:
 * one ticket_trail_logs row per request — the Edit page saves a ticket in
 * several steps, they become one line of old → new.
 *
 * Quiet where the history is already kept: Change and the contract flight
 * write their own register, and the Trail logs its own correction. Never
 * throws — a ticket save must not fail because its history could not be
 * written.
 */
class EditLog
{
    /** The Trail is rewriting: it logs the correction itself. */
    public static bool $paused = false;

    /** "purchase id|request" => log id, so one request is one history line */
    private static array $open = [];

    private static ?bool $ready = null;

    public const FIELDS = [
        'passport_holder_id' => 'Passenger', 'ticket_id' => 'Route', 'ticket_no' => 'PNR', 'name_ticket_no' => 'E-ticket',
        'vendor_id' => 'Bought from', 'portal_id' => 'Portal', 'amount' => 'Cost', 'status' => 'Status',
        'ticket_class' => 'Class', 'trip_type' => 'Trip', 'purchase_date' => 'Purchase date', 'departure_at' => 'Travel date',
        'free_baggage_kg' => 'Free baggage KG', 'extra_baggage_kg' => 'Extra baggage KG',
        'extra_baggage_cost' => 'Baggage cost', 'extra_baggage_agent_charge' => 'Baggage agent charge',
        'extra_baggage_bearer' => 'Baggage paid by', 'baggage_conditions' => 'Baggage conditions',
        'base_fare' => 'Base fare', 'tax_amount' => 'Tax', 'commission_amount' => 'Commission', 'flight_no' => 'Flight',
    ];

    public static function record(TicketPurchase $t): void
    {
        try {
            if (self::$paused) {
                return;
            }
            $route = (string) request()->route()?->getName();
            if (str_contains($route, 'ticket-changes') || str_contains($route, 'contract-flight') || str_contains($route, 'ticket-trail')) {
                return;
            }
            self::$ready ??= Schema::hasTable('ticket_trail_logs');
            if (! self::$ready) {
                return;
            }
            $was = [];
            $now = [];
            foreach (array_keys(self::FIELDS) as $f) {
                if (! $t->wasChanged($f)) {
                    continue;
                }
                $old = $t->getOriginal($f);
                $new = $t->getAttribute($f);
                if (is_numeric($old) && is_numeric($new) ? abs((float) $old - (float) $new) < 0.005 : (string) $old === (string) $new) {
                    continue;
                }
                $was[$f] = $old instanceof \DateTimeInterface ? $old->format('Y-m-d H:i:s') : $old;
                $now[$f] = $new instanceof \DateTimeInterface ? $new->format('Y-m-d H:i:s') : $new;
            }
            if (! $now) {
                return;
            }
            $key = $t->id.'|'.(request()->server('REQUEST_TIME_FLOAT') ?? '');
            $log = isset(self::$open[$key]) ? TicketTrailLog::find(self::$open[$key]) : null;
            if ($log) {
                $was = ($log->before ?? []) + $was;   // the first old value stays
                $now = array_merge($log->after ?? [], $now);
            }
            $attrs = ['before' => $was, 'after' => $now, 'summary' => implode("\n", self::lines($was, $now))];
            if ($log) {
                $log->update($attrs);
            } else {
                self::$open[$key] = TicketTrailLog::create($attrs + [
                    'company_id' => $t->company_id, 'ticket_purchase_id' => $t->id, 'action' => 'edit',
                    'reason' => self::where($route), 'created_by' => Auth::id(),
                ])->id;
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** "Cost: ৳12,720.00 → ৳13,000.00" — names, not ids. */
    public static function lines(array $was, array $now): array
    {
        $out = [];
        foreach ($now as $f => $new) {
            $old = $was[$f] ?? null;
            if (is_numeric($old) && is_numeric($new) && abs((float) $old - (float) $new) < 0.005) {
                continue;
            }
            $out[] = (self::FIELDS[$f] ?? $f).': '.self::show($f, $old).' → '.self::show($f, $new);
        }

        return $out;
    }

    private static function show(string $f, $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }

        return match ($f) {
            'passport_holder_id' => (string) DB::table('passport_holders')->where('id', $v)->value('name'),
            'ticket_id' => (string) DB::table('tickets')->where('id', $v)->value('title'),
            'vendor_id' => (string) DB::table('users')->where('id', $v)->value('name'),
            'portal_id' => (string) DB::table('portals')->where('id', $v)->value('name'),
            'amount', 'base_fare', 'tax_amount', 'commission_amount' => '৳'.number_format((float) $v, 2),
            'status' => ['confirm' => 'Purchased', 'cancelled' => 'Cancelled'][$v] ?? ucfirst((string) $v),
            default => (string) $v,
        };
    }

    private static function where(string $route): string
    {
        return match (true) {
            str_contains($route, 'ticket-direct-sale.update'), str_contains($route, 'ticket-purchase.update') => 'Edit page',
            str_contains($route, 'ticket-direct-sale.store'), str_contains($route, 'ticket-purchase.store') => 'Ticket Entry',
            str_contains($route, 'make.payment') => 'Payment to seller',
            str_contains($route, 'payment-schedule') => 'Payment schedule',
            str_contains($route, 'ticket-sale') => 'Manage Sales',
            str_contains($route, 'import') => 'Ticket Import',
            $route === '' => 'System',
            default => $route,
        };
    }
}
