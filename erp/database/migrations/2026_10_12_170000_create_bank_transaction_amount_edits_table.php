<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transaction_amount_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries');
            $table->decimal('old_amount', 15, 2);
            $table->decimal('new_amount', 15, 2);
            $table->decimal('old_charge_amount', 15, 2)->default(0);
            $table->decimal('new_charge_amount', 15, 2)->default(0);
            $table->decimal('old_vat_amount', 15, 2)->default(0);
            $table->decimal('new_vat_amount', 15, 2)->default(0);
            $table->foreignId('old_charge_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('new_charge_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('old_vat_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('new_vat_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->text('reason');
            $table->foreignId('edited_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_amount_edits');
    }
};
