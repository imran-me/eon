<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time data fix (owner, 2026-10-18): an order (order_group) is ONE passenger on ONE route.
 * A ticket entered on another passenger's card and then corrected on the Edit page kept that
 * passenger's order — live: UFUMMB (MD RIDOY MIA) sat in SALA UDDIN's order, and Ticket Orders
 * showed it as "same passenger" under him. The code no longer lets it happen
 * (TicketPurchaseController::update, the import's holder creation); this fixes the rows already
 * saved that way.
 *
 * Each mixed order is split: its first passenger + route (earliest row) keeps the order; every
 * other passenger + route gets an order of its own, purchases and holds together. Only
 * order_group changes — no amount, ledger row or journal entry. Running it again finds nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = array_values(array_filter(['ticket_purchases', 'ticket_bookings'],
            fn ($t) => Schema::hasTable($t) && Schema::hasColumn($t, 'order_group')));
        if (! $tables) {
            return;
        }

        $rows = collect();
        foreach ($tables as $table) {
            $rows = $rows->concat(DB::table($table)->whereNull('deleted_at')->whereNotNull('order_group')->where('order_group', '!=', '')
                ->get(['id', 'order_group', 'passport_holder_id', 'ticket_id', 'created_at'])
                ->map(fn ($r) => ['table' => $table, 'id' => $r->id, 'group' => $r->order_group,
                    'who' => $r->passport_holder_id.'|'.$r->ticket_id, 'at' => (string) $r->created_at]));
        }

        foreach ($rows->groupBy('group') as $group => $members) {
            $owners = $members->sortBy(fn ($m) => [$m['at'], $m['table'] === 'ticket_purchases' ? 0 : 1, $m['id']])
                ->pluck('who')->unique()->values();
            if ($owners->count() < 2) {
                continue;
            }
            foreach ($owners->slice(1)->values() as $n => $who) {
                $new = substr($group, 0, 34).'s'.($n + 2);
                foreach ($members->where('who', $who) as $m) {
                    DB::table($m['table'])->where('id', $m['id'])->update(['order_group' => $new]);
                }
            }
        }
    }

    public function down(): void
    {
        // Nothing to restore: a mixed order was never right.
    }
};
