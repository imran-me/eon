<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('party_discounts') || Schema::hasColumn('party_discounts', 'batch_key')) {
            return;
        }
        Schema::table('party_discounts', function (Blueprint $table) {
            // One "overall" discount is split over several invoices; every
            // row of the split shares this key so it can be shown and
            // reversed as a single act. NULL = an ordinary one-invoice discount.
            $table->uuid('batch_key')->nullable()->after('request_key')->index();
        });
    }

    public function down(): void
    {
        // Posted credit notes are an audit trail; leave the column in place.
    }
};
