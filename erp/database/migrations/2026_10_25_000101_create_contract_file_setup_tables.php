<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Contract File → Setup (owner, 2026-10-25): "rename it to Setup. here we will do setup of visa type wise
 * requirement, country wise requirements … requirement builder, so that while entering the files, if we
 * select any visa types or country the requirements add up automatically, also cost prices".
 *
 *   contract_file_visa_types      the file module's own visa types (Work Permit, Student, Family …)
 *   contract_file_requirements    a document list for a scope: country / visa type / job category, any mix
 *   contract_file_price_presets   processing cost, training fee and expected days for a scope
 *   contract_files.contract_file_visa_type_id
 *
 * Additive only: nothing existing is altered or dropped. Run with --path; each step is skipped if it is
 * already there, so a re-run is harmless.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('contract_file_visa_types')) {
            Schema::create('contract_file_visa_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 40)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        foreach (['contract_file_requirements', 'contract_file_price_presets'] as $name) {
            if (Schema::hasTable($name)) {
                continue;
            }
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                // The scope: any of the three may be empty ("any"); at least one is set (validated).
                $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
                $table->foreignId('contract_file_visa_type_id')->nullable()->constrained('contract_file_visa_types')->nullOnDelete();
                $table->foreignId('contract_file_category_id')->nullable()->constrained('contract_file_categories')->nullOnDelete();
                if ($name === 'contract_file_requirements') {
                    $table->text('documents');
                    $table->text('notes')->nullable();
                } else {
                    $table->decimal('processing_cost', 14, 2)->nullable();
                    $table->decimal('training_fee', 14, 2)->nullable();
                    $table->unsignedSmallInteger('expected_days')->nullable();
                }
                $table->string('status', 20)->default('active');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['status', 'country_id', 'contract_file_visa_type_id'], $name.'_scope_idx');
            });
        }

        if (Schema::hasTable('contract_files') && ! Schema::hasColumn('contract_files', 'contract_file_visa_type_id')) {
            Schema::table('contract_files', function (Blueprint $table) {
                $table->foreignId('contract_file_visa_type_id')->nullable()->after('contract_file_category_id')
                    ->constrained('contract_file_visa_types')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_files', 'contract_file_visa_type_id')) {
            Schema::table('contract_files', function (Blueprint $table) {
                $table->dropConstrainedForeignId('contract_file_visa_type_id');
            });
        }
        Schema::dropIfExists('contract_file_price_presets');
        Schema::dropIfExists('contract_file_requirements');
        Schema::dropIfExists('contract_file_visa_types');
    }
};
