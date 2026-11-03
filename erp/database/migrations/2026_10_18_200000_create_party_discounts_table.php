<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $account = DB::table('accounts')->where('code', '4195')->first();
            if ($account && ($account->name !== 'Sales Discounts & Allowances' || $account->type !== 'income' || $account->company_id !== null || $account->deleted_at !== null)) {
                throw new RuntimeException('Account 4195 is occupied. Resolve the discount account before migrating.');
            }
            if (! $account) {
                DB::table('accounts')->insert(['code' => '4195', 'name' => 'Sales Discounts & Allowances', 'type' => 'income', 'company_id' => null, 'parent_id' => null, 'opening_balance' => 0, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        Schema::create('party_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('document_type', 60);
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('charge_transaction_id');
            $table->decimal('amount', 15, 2);
            $table->date('discount_date');
            $table->string('reason', 500);
            $table->uuid('request_key')->unique();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['document_type', 'document_id', 'reversed_at']);
            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        // Posted credit notes are an audit trail; do not silently delete them.
        if (DB::table('party_discounts')->exists()) {
            throw new RuntimeException('Cannot remove posted discount history.');
        }
        Schema::dropIfExists('party_discounts');
    }
};
