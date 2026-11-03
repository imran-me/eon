<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a document renewal identifiable, and lets the desk say what term it buys.
 *
 * ── THE BUG THIS FIXES ────────────────────────────────────────────────────────
 * dm_group_id was set to DM's `document_id` on the assumption that it meant "this
 * same paper, renewed each year". It does not. In the live register 388 rows sit
 * under just 29 document_ids, because a document_id is a FOLDER — "MD MOHSIN
 * DOCUMNENTS" holds eighty different papers, and document_id 46 alone contains a
 * tax return, a licence, a certificate and more.
 *
 * What actually identifies one paper is the folder PLUS its type and category:
 * (document_id, document_type_id, document_category_id). Those three together
 * pick out a real renewal chain — e.g. 46/2/12 runs 2021→2022→2023→2024→2025→2026,
 * one row per year.
 *
 * Left as it was, the "last paid" figure carried forward onto a licence would be
 * whatever unrelated paper in the same folder was paid most recently. Wrong money
 * against the wrong document, with nothing on screen to show it.
 *
 * Added as two columns rather than by changing dm_group_id's type: dm_group_id
 * still holds the folder, and the pair narrows it. Purely additive, so existing
 * rows stay valid — a row written before this reads as "folder known, paper not",
 * which is exactly what it was.
 *
 * ── next_expiry ───────────────────────────────────────────────────────────────
 * A subscription's next expiry can be worked out: DM states the cycle and the
 * ERP adds one. A DOCUMENT cannot. DM records no cycle for them, and the dates in
 * that table cannot be used to infer one — the same folder holds rows where
 * created_date equals renewal_date, a term ending in 2050, and one beginning in
 * the year 0025.
 *
 * So the person filing the expense states the new expiry and it is stored here.
 * The desk proposes a year, but a proposal that can be corrected is not the same
 * as a guess written into the register: a wrong date pushed into DM raises no
 * error anywhere, it silently stops a licence reminder arriving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dm_renewal_payments', function (Blueprint $table) {
            // Null for subscriptions, which need no narrowing — one DM row is one
            // commitment there.
            $table->unsignedBigInteger('dm_type_id')->nullable()->after('dm_group_id');
            $table->unsignedBigInteger('dm_category_id')->nullable()->after('dm_type_id');

            // What the renewal buys. Set for documents from the form; for
            // subscriptions it is derived from the cycle and recorded for the
            // audit trail.
            $table->date('next_expiry')->nullable()->after('due_date');

            $table->index(['source_type', 'dm_group_id', 'dm_type_id', 'dm_category_id'], 'dm_renewal_paper_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dm_renewal_payments', function (Blueprint $table) {
            $table->dropIndex('dm_renewal_paper_idx');
            $table->dropColumn(['dm_type_id', 'dm_category_id', 'next_expiry']);
        });
    }
};
