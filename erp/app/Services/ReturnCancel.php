<?php

namespace App\Services;

use App\Models\ContractFlightPassenger;
use App\Models\TicketPurchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cancel return (owner, 2026-10-14). A family passenger flies DAC → ECN and
 * stays on; the unused return (ECN → DAC) is cancelled within the airline's
 * window — with the airline's penalty (and who bears it), our charge, and the
 * agent's total either kept or revised.
 *
 * This class only answers WHO may (family), WHAT is cancelled (shape) and
 * WHEN (the window); the postings are Ticketing's (TicketChangeController) or
 * the contract flight's (ContractFlightPassengerController::cancelReturn).
 *
 *   family   the ticket is ticked Family, or the passenger's contract-flight
 *            category is a family one (Cyprus Family), or he holds a Family /
 *            Spouse visa application — "family categories only" (owner).
 *   shape    round_trip      a two-way ticket: only its return half goes
 *            return_ticket   a separate one-way return: the whole ticket goes
 */
class ReturnCancel
{
    private static ?bool $ready = null;

    /** The 2026-10-14 columns exist — before the migration, nothing of this shows. */
    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasColumn('ticket_purchases', 'return_cancel_by')
            && Schema::hasColumn('contract_flight_passenger_changes', 'price_revision');
    }

    public static function isFamilyPassenger(?ContractFlightPassenger $p): bool
    {
        return (bool) ($p && preg_match('/famil/i', (string) $p->flightCategory?->name));
    }

    public static function hasFamilyVisa(?int $holderId): bool
    {
        if (! $holderId) return false;
        try {
            return DB::table('visa_processes as v')->join('visa_categories as c', 'c.id', '=', 'v.visa_category_id')
                ->where('v.passport_holder_id', $holderId)->where('c.name', 'like', '%famil%')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * A contract-flight passenger is family by HIS category (or a family visa) —
     * a Family tick on the ticket cannot make a Student passenger family. A
     * Ticketing-only ticket has no category: its Family tick (or a family visa) says it.
     */
    public static function isFamily(TicketPurchase $t, ?ContractFlightPassenger $p = null): bool
    {
        if ($p) {
            return self::isFamilyPassenger($p) || self::hasFamilyVisa($p->passport_holder_id ?: $t->passport_holder_id);
        }
        return (bool) $t->family_return || self::hasFamilyVisa($t->passport_holder_id);
    }

    /** round_trip | return_ticket | null — what "Cancel return" would cancel on this ticket. */
    public static function shape(TicketPurchase $t, ?ContractFlightPassenger $p = null): ?string
    {
        if ($t->trip_type === 'two-way') return 'round_trip';
        if ($t->family_return) return 'return_ticket';
        // A contract-flight passenger's ticket that goes back to where his trip began.
        if ($p) {
            $ends = function (?TicketPurchase $x): array {
                if (! $x) return [null, null];
                $x->loadMissing('legs', 'ticket.from_airport', 'ticket.to_airport');
                $from = $x->legs->first()?->from_location ?: $x->ticket?->from_airport?->code;
                $to = $x->legs->last()?->to_location ?: $x->ticket?->to_airport?->code;
                return [$from ? strtoupper(trim($from)) : null, $to ? strtoupper(trim($to)) : null];
            };
            $first = $p->ticketPurchases->where('status', '!=', 'cancelled')->where('id', '!=', $t->id)->sortBy('id')->first();
            $origin = $p->contractFlight?->ticket?->from_airport?->code ?: $ends($first)[0];
            $origin = $origin ? strtoupper(trim($origin)) : null;
            [$from, $to] = $ends($t);
            if ($origin && $to && $to === $origin && $from !== $origin) return 'return_ticket';
        }
        return null;
    }

    /**
     * Everything a dialog / lane / row needs, or null before the migration.
     * tone: ok (more than 3 days), soon (3 days or less), late (past), none (no window set).
     */
    public static function state(TicketPurchase $t, ?ContractFlightPassenger $p = null): ?array
    {
        if (! self::ready()) return null;
        $shape = self::shape($t, $p);
        $family = self::isFamily($t, $p);
        $done = (bool) $t->return_cancelled_at;
        $by = $t->return_cancel_by;
        $end = self::deadline($t);
        $dateOnly = $by && $by->format('H:i:s') === '00:00:00';
        // whole days left: today = 0 ("last day"), tomorrow = 1 …
        $left = $end ? (int) now()->startOfDay()->diffInDays($end->copy()->startOfDay(), false) : null;
        return [
            'family'       => $family,
            'shape'        => $shape,
            'eligible'     => $family && $shape && ! $done && $t->status === 'confirm',
            'done'         => $done,
            'cancel_by'    => $by ? ($dateOnly ? $by->format('Y-m-d') : $by->format('Y-m-d\TH:i')) : null,
            'cancel_by_label' => $by ? ($dateOnly ? $by->format('d M Y') : $by->format('d M Y, h:i A')) : null,
            'days_left'    => $left,
            'tone'         => ! $end ? 'none' : ($end->isPast() ? 'late' : ($left <= 3 ? 'soon' : 'ok')),
            'cancelled_at' => $t->return_cancelled_at?->format('d M Y'),
            'refund'       => $shape === 'round_trip' ? ($t->return_refund_amount !== null ? (float) $t->return_refund_amount : null)
                                                      : ($t->refund_amount !== null ? (float) $t->refund_amount : null),
            'amount'       => (float) $t->amount,
            'flagged'      => (bool) $t->family_return,
        ];
    }

    /**
     * When the window really closes: the time entered, or — a date alone ("no time",
     * stored 00:00 as every date+time box here does) — the end of that day.
     */
    public static function deadline(TicketPurchase $t): ?Carbon
    {
        $by = $t->return_cancel_by;
        if (! $by) return null;
        $by = Carbon::parse($by);
        return $by->format('H:i:s') === '00:00:00' ? $by->endOfDay() : $by;
    }

    /** The window (and the Family tick). A new deadline starts its reminders afresh. */
    public static function setWindow(TicketPurchase $t, ?string $cancelBy, ?bool $family = null): void
    {
        $by = $cancelBy ? Carbon::parse($cancelBy) : null;
        $changed = (string) $t->return_cancel_by?->format('Y-m-d H:i') !== (string) $by?->format('Y-m-d H:i');
        $t->forceFill(array_filter([
            'return_cancel_by' => $by,
            'family_return'    => $family,
            'return_reminders' => $changed ? null : $t->return_reminders,
        ], fn ($v, $k) => $k === 'return_cancel_by' || $k === 'return_reminders' || $v !== null, ARRAY_FILTER_USE_BOTH))->save();
    }

    /**
     * The Ticket Orders row's line, from the ticket alone (no passenger, no visa
     * lookup): a window someone entered, or a return already cancelled.
     * ['text' => …, 'tone' => ok | soon | late | done] or null.
     */
    public static function rowBadge(TicketPurchase $t): ?array
    {
        if ($t->return_cancelled_at) {
            $on = Carbon::parse($t->return_cancelled_at)->format('d M');
            return ['text' => 'cancelled ' . $on, 'title' => 'Return cancelled ' . $on, 'tone' => 'done'];
        }
        $end = self::deadline($t);
        if (! $end || $t->status !== 'confirm') return null;
        $left = (int) now()->startOfDay()->diffInDays($end->copy()->startOfDay(), false);
        // Yellow always; the last 5 hours pulse and say the hours left (owner, 2026-10-15).
        // Short enough for the Status cell (measured): the return icon and the tooltip
        // name it, as every cell here does — no "Return cancel by" label to be cut.
        $mins = (int) floor(now()->diffInMinutes($end, false));
        $tone = $end->isPast() ? 'late' : ($mins <= 300 ? 'urgent' : 'soon');
        $when = match (true) {
            $tone === 'late' => 'window passed',
            $tone === 'urgent' => ($mins >= 60 ? intdiv($mins, 60) . 'h ' : '') . ($mins % 60) . 'm left',
            $left === 0 => $end->format('d M') . ' · last day',
            default => $end->format('d M') . ' · ' . $left . 'd left',
        };
        $full = $end->format('H:i:s') === '23:59:59' ? $end->format('d M Y') : $end->format('d M Y, h:i A');
        $tail = match (true) {
            $tone === 'late' => 'the window has passed',
            $tone === 'urgent' => $when,
            $left === 0 => 'last day',
            default => $left . ' day' . ($left === 1 ? '' : 's') . ' left',
        };
        return ['text' => $when, 'title' => 'Cancel the return by ' . $full . ' — ' . $tail, 'tone' => $tone];
    }

    /** "Return cancel by 20 Nov · 5 days left" — the line the rows and lanes show. */
    public static function badge(?array $s): ?string
    {
        if (! $s || ! $s['family'] || ! $s['shape']) return null;
        if ($s['done']) return 'Return cancelled ' . $s['cancelled_at'];
        if (! $s['cancel_by']) return null;
        return 'Return cancel by ' . Carbon::parse($s['cancel_by'])->format('d M')
            . ' · ' . ($s['tone'] === 'late' ? 'window passed' : ($s['days_left'] === 0 ? 'last day' : $s['days_left'] . ' day' . ($s['days_left'] === 1 ? '' : 's') . ' left'));
    }
}
