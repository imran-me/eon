<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How much of this expense turned into a debt to the person who filed it.
 *
 * Written at POSTING time, not entry time — it is a fact about what the journal
 * did, not something anyone types. Two cases fill it:
 *
 *   own pocket        the whole amount; no company cash moved at all
 *   float overspend   the part the float could not cover
 *
 * The second is why it exists. A custodian holding ৳1,000 who spends ৳1,500 has
 * covered ৳500 himself, and until now that expense was simply REFUSED — the
 * float cannot go negative, so the receipt had to be split into two entries by
 * hand. Now it posts as one expense against two credits, and this column is what
 * lets the list say "৳500 of this is owed back" without re-reading the ledger.
 *
 * Deliberately NOT paired with reimburse_to_user_id on the overspend path. Who is
 * owed there is the float's custodian and nobody else, so storing it again would
 * be a second copy of a fact that can drift. That column stays what the person
 * filing actually declared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('reimbursed_amount', 15, 2)
                ->nullable()
                ->after('reimburse_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('reimbursed_amount');
        });
    }
};
