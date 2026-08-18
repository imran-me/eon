<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories and sub-categories for the loan book.
 *
 * ONE self-parenting table rather than the two the expense module uses
 * (expense_categories + expense_sub_categories). A sub-category is a category
 * with a parent, which means one screen, one controller and one set of rules —
 * and if the owner later wants a third level it costs nothing. The chart of
 * accounts in this same app already self-parents the same way.
 *
 * `direction` scopes a category to the book it belongs to: "Car Loan" and
 * "Mortgage" only make sense against money we borrowed, "Service on
 * Instalments" only against money we lent. NULL means it applies to both.
 *
 * Shared across companies (no company_id) on purpose: a taxonomy that differs
 * per company cannot be reported on across the group, which is the whole point
 * of having one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_categories', function (Blueprint $table) {
            $table->id();

            // Self reference: null = a top-level category, set = a sub-category.
            // cascadeOnDelete so removing a category takes its children with it
            // rather than leaving them orphaned and unreachable.
            $table->foreignId('parent_id')->nullable()
                  ->constrained('financing_categories')->cascadeOnDelete();

            $table->string('name');
            $table->enum('direction', ['borrowed', 'lent'])->nullable()->index();
            $table->boolean('status')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'direction']);
        });

        Schema::table('financing_loans', function (Blueprint $table) {
            // Both levels stored, exactly as the expense module does, so a
            // report can group by category without walking the tree.
            $table->foreignId('category_id')->nullable()->after('kind')
                  ->constrained('financing_categories')->nullOnDelete();
            $table->foreignId('sub_category_id')->nullable()->after('category_id')
                  ->constrained('financing_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financing_loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_category_id');
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('financing_categories');
    }
};
