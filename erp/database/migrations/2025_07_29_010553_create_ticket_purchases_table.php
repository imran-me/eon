<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passport_holder_id')->nullable()->constrained('passport_holders')->onDelete('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('portal_id')->nullable()->constrained('portals')->onDelete('cascade');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->onDelete('cascade');
            $table->unsignedBigInteger('ticket_id');

            $table->enum('ticket_type', ['air', 'bus', 'train', 'other'])->default('air');
            $table->enum('trip_type', ['one-way', 'two-way'])->default('one-way');
            // $table->string('source')->nullable();
            // $table->string('from_location')->nullable();
            // $table->string('to_location')->nullable();
            // $table->date('travel_date')->nullable();
            // $table->string('airline_or_operator')->nullable();
            // $table->string('seat_number')->nullable();
            // $table->text('description')->nullable();

            $table->string('ticket_no')->unique();
            $table->date('purchase_date')->nullable();
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('currency', 5)->default('BDT');
            $table->enum('status', ['draft', 'pending', 'confirm', 'cancelled'])->default('pending');
            $table->string('attachment')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_purchases');
    }
};
