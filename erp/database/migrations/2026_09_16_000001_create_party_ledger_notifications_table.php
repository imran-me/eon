<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per email/SMS bundle sent to a party about new entries on their
 * ledger. Doubles as the watermark: the highest `to_transaction_id` a party
 * has is the last entry they were told about, so a job only ever announces
 * what came after it — and a sale's two rows (charge + payment) posted
 * seconds apart go out as one message, not two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_ledger_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('party_type', 20)->nullable();           // agent | client | vendor
            $table->string('kind', 20)->default('activity');        // activity | statement | reminder
            $table->unsignedBigInteger('sent_by')->nullable();     // staff user for manual sends; null when automatic
            $table->unsignedBigInteger('from_transaction_id');
            $table->unsignedBigInteger('to_transaction_id')->index();
            $table->unsignedInteger('entry_count')->default(0);
            $table->decimal('closing_balance', 15, 2)->default(0);
            $table->string('email_to')->nullable();
            $table->string('email_status', 20)->nullable();         // sent | failed | skipped
            $table->text('email_error')->nullable();
            $table->string('sms_to', 30)->nullable();
            $table->string('sms_status', 20)->nullable();           // sent | failed | skipped
            $table->text('sms_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_ledger_notifications');
    }
};
