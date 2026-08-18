<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expense departments — the expense desk's own, not HR's.
 *
 * The first cut hung expenses off `departments`, the HR table that employee
 * profiles use. That was wrong on two counts:
 *
 *  · They are not the same list. HR's departments exist to place PEOPLE
 *    ("Software Development", "Operations"); an expense desk wants the units it
 *    actually budgets and reports spending against, and renaming or retiring one
 *    should not move an employee's record.
 *
 *  · HR departments are group-wide with no company, so "pick the department and
 *    the company fills in" could only ever be a guess read back from history.
 *    An expense department belongs to ONE company, which turns that guess into a
 *    hard link — the behaviour that was asked for in the first place.
 *
 * expenses.department_id is repointed here. It was added days ago and nothing
 * has been filed against it yet, so there is no history to migrate — the column
 * is dropped and re-added against the new table rather than carrying an FK that
 * would now mean something different.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_departments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Not nullable: a department that belongs to no company cannot fill
            // the company in, which is the whole reason this table exists.
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('status')->default(1);

            $table->timestamps();
            $table->softDeletes();

            // One name per company — "Site" under Construction and "Site" under
            // Woodart are different departments; two "Site" rows under one
            // company are a mistake.
            $table->unique(['company_id', 'name']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'department_id']);
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('expense_department_id')->nullable()->after('company_id')
                ->constrained('expense_departments')->nullOnDelete();

            $table->index(['company_id', 'expense_department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'expense_department_id']);
            $table->dropConstrainedForeignId('expense_department_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('company_id')
                ->constrained('departments')->nullOnDelete();

            $table->index(['company_id', 'department_id']);
        });

        Schema::dropIfExists('expense_departments');
    }
};
