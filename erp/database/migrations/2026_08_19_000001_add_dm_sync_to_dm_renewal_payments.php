<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records whether DM was told about a renewal, and what went wrong if it was not.
 *
 * ── WHY THIS IS A SEPARATE FACT FROM THE PAYMENT ───────────────────────────────
 * Paying a renewal and pushing its new date back into DM are two things that
 * happen in two different systems, and the second one can fail on its own — DM
 * may be down, the token may lack the renew ability, the network may time out.
 * The money still moved. So the expense and its journal must never be rolled back
 * because a second system was unreachable.
 *
 * That leaves a state the desk has to be able to show: *paid here, not yet
 * updated there*. Without somewhere to record it, the only options are to lie
 * (claim DM is in step when it is not) or to lose the expense (roll it back over
 * an unrelated outage). Both are worse than a column.
 *
 * ── THE COLUMNS ───────────────────────────────────────────────────────────────
 *   cycle          — the billing cycle DM reported for this commitment, captured
 *                    when the expense was filed. It is what the new expiry is
 *                    computed from, so it is stored rather than re-fetched: a
 *                    cycle that changed in DM afterwards must not silently change
 *                    what this payment settled. Also makes the row readable on
 *                    its own — "this paid a Yearly period" — for anyone auditing.
 *   dm_synced_at   — set once DM confirms the new date. Null means outstanding.
 *   dm_sync_error  — the last failure, in words, so the desk can say WHY rather
 *                    than just showing a warning triangle. Cleared on success.
 *
 * ── BLAST RADIUS ──────────────────────────────────────────────────────────────
 * Three nullable columns on a table only the Subscriptions desk reads. Nothing
 * else queries it, no existing column changes, and every existing row stays
 * valid — a row written before this migration simply reads as "never synced",
 * which is exactly what it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dm_renewal_payments', function (Blueprint $table) {
            $table->string('cycle', 20)->nullable()->after('currency');
            $table->timestamp('dm_synced_at')->nullable()->after('paid_at');
            $table->text('dm_sync_error')->nullable()->after('dm_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('dm_renewal_payments', function (Blueprint $table) {
            $table->dropColumn(['cycle', 'dm_synced_at', 'dm_sync_error']);
        });
    }
};
