<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The missing link between a DM renewal and the expense that paid it.
 *
 * ── WHAT THIS IS NOT ──────────────────────────────────────────────────────────
 * It is NOT a subscription register. DM (dm.epal.com.bd) already is one, and
 * DmApiService is read-only — two `fetch` methods and no write — so the ERP
 * cannot create, edit or close a subscription there. Keeping a second register
 * here would mean two lists of the same commitments drifting apart, with no
 * way to say which one is right.
 *
 * So this table stores ONE fact and nothing else: *this DM row, for this billing
 * period, was paid by this expense*. Everything describing the commitment stays
 * in DM and is fetched live.
 *
 * ── WHY A ROW PER PERIOD ──────────────────────────────────────────────────────
 * `due_date` is part of the identity, not a detail. A subscription in DM is one
 * row whose `expired_date` is pushed forward each cycle, so without the date in
 * the key there would be one payment row per subscription forever and last
 * year's payment would look like this year's. With it, each cycle is its own
 * settled/unsettled fact and the desk can show a real history.
 *
 * ── THE UNIQUE INDEX IS THE POINT ─────────────────────────────────────────────
 * (source_type, dm_id, due_date) is unique, and that is what makes paying the
 * same renewal twice fail loudly instead of quietly producing two expenses that
 * nobody reconciles. Double payment is the failure this desk exists to prevent;
 * a validation message would only catch the honest case, so it is enforced by
 * the database.
 *
 * ── WHY THE SNAPSHOT COLUMNS ──────────────────────────────────────────────────
 * `title`, `amount` and `currency` duplicate what DM holds, deliberately:
 *
 *   · title    — DM rows get deleted. A payment whose subject cannot be named is
 *                useless as an audit record, so the name is captured at payment.
 *   · amount   — for DOCUMENT renewals DM sends no amount at all (see
 *                app/Services/expired-documents.json: no amount, no currency).
 *                What was actually paid is only knowable here, and it is what
 *                pre-fills next year's renewal.
 *   · currency — what the amount above is denominated in. Never assume BDT: a
 *                subscription billed at USD 65.00 is a different number.
 *
 * These record the PAYMENT, not the commitment — what was paid on the day, which
 * is a fact of its own and cannot go stale.
 *
 * ── dm_group_id ───────────────────────────────────────────────────────────────
 * A document renewal in DM is a new row each cycle (`id` changes, `document_id`
 * stays), whereas a subscription is one row reused. So `dm_id` cannot carry
 * anything forward for documents. `dm_group_id` is what does: `document_id` for
 * a document, and the same value as `dm_id` for a subscription. It is what the
 * desk looks up to answer "what did we pay for this last time?".
 *
 * ── BLAST RADIUS ──────────────────────────────────────────────────────────────
 * A new table nothing else reads. No existing table is altered, no column is
 * dropped, no index on a live table is touched. If this migration is not run,
 * only the Subscriptions tab breaks — every other expense screen queries none
 * of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dm_renewal_payments', function (Blueprint $table) {
            $table->id();

            // Which DM endpoint the row came from — company-access or
            // expired-documents. Part of the key because the two have their own
            // id sequences and would otherwise collide on small ids.
            $table->enum('source_type', ['subscription', 'document']);

            $table->unsignedBigInteger('dm_id');

            // Stable across cycles. See the note above — this is what pre-fills
            // a document renewal whose amount DM does not carry.
            $table->unsignedBigInteger('dm_group_id')->nullable();

            // The billing period this settles, not the day it was paid.
            $table->date('due_date');

            $table->string('title');
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 8)->nullable();

            // nullOnDelete, not cascade: an expense that is removed must not take
            // the record of the payment with it. The row survives with a null
            // expense_id, which reads as "this was marked paid and the expense is
            // gone" — a discrepancy worth seeing, not one worth hiding.
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'dm_id', 'due_date'], 'dm_renewal_period_unique');
            $table->index(['source_type', 'dm_group_id'], 'dm_renewal_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dm_renewal_payments');
    }
};
