<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_terms', function (Blueprint $table) {
            $table->string('location')->nullable();
            $table->string('contract_type', 40)->nullable();
            $table->string('priority', 20)->nullable();
            $table->decimal('estimated_budget', 16, 2)->nullable();
            $table->string('currency', 10)->default('BDT');
            $table->string('billing_type', 40)->nullable();
            $table->unsignedTinyInteger('physical_progress_target')->nullable();
            $table->text('project_description')->nullable();
            $table->json('tags')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('settings')->nullable();
            $table->json('team_member_ids')->nullable();
            $table->json('document_paths')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_terms', function (Blueprint $table) {
            $table->dropColumn(['location', 'contract_type', 'priority', 'estimated_budget', 'currency', 'billing_type', 'physical_progress_target', 'project_description', 'tags', 'internal_notes', 'settings', 'team_member_ids', 'document_paths']);
        });
    }
};
