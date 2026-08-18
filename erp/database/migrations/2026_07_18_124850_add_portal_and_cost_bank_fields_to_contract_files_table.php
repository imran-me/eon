<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_files', function (Blueprint $table) {
            $table->foreignId('portal_id')->nullable()->after('vendor_id')->constrained('portals')->nullOnDelete();
            $table->foreignId('cost_bank_id')->nullable()->after('portal_id')->constrained('banks')->nullOnDelete();
            $table->decimal('cost_paid_amount', 15, 2)->nullable()->default(0)->after('visa_rate');
            $table->date('purchase_date')->nullable()->after('cost_paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('contract_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('portal_id');
            $table->dropConstrainedForeignId('cost_bank_id');
            $table->dropColumn(['cost_paid_amount', 'purchase_date']);
        });
    }
};
