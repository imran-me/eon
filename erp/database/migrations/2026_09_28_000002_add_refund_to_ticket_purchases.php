<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cancelled ticket keeps its original amount and every posting it made
 * (owner, 2026-09-28: "no data should be deleted at all, upon cancellation"),
 * and records what the airline gives back:
 *
 *   refund_amount   what comes back — to the vendor's balance or the portal
 *                   wallet. amount − refund_amount is the airline's penalty.
 *   cancelled_at    when the reversal was posted.
 *
 * Both NULL on every ticket cancelled before this, which were settled the
 * old way (rows deleted); the code tells the two apart by cancelled_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_purchases', 'refund_amount')) {
                $table->decimal('refund_amount', 15, 2)->nullable()->after('due_amount');
            }
            if (! Schema::hasColumn('ticket_purchases', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('refund_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_purchases', function (Blueprint $table) {
            foreach (['cancelled_at', 'refund_amount'] as $col) {
                if (Schema::hasColumn('ticket_purchases', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
