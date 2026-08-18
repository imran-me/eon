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
        if (!Schema::hasTable('sms_templates')) {            
            Schema::create('sms_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();     // payment_received, salary_paid, etc.
                $table->string('title');              // Payment Received, Salary Paid
                $table->text('template');             // "Dear {{name}}, we received {{amount}}."
                $table->json('variables')->nullable(); // ["name", "amount", "invoice_no"]
                $table->boolean('status')->default(true);
                $table->unsignedBigInteger('created_by')->nullable()->comment('The user who created the template');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->unsignedBigInteger('company_id')->nullable();
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};
