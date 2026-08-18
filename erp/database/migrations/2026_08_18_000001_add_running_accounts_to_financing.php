<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two shapes of arrangement, and only one of them was ever modelled.
 *
 * ── TERM (everything the desk held until now) ─────────────────────────────
 * A bank sanctions a fixed sum for a fixed number of months. The whole amount
 * arrives once, the schedule is known on day one, and every instalment is
 * measured against it. `principal`, `tenure_months` and the generated schedule
 * all assume this shape.
 *
 * ── RUNNING (what the desk could not hold) ────────────────────────────────
 * The boss takes 2,000 today, 5,000 next week, 10,000 the week after, and pays
 * back whatever he likes whenever he likes. There is no principal — the balance
 * IS the sum of what has been taken less what has come back. There is no
 * tenure, no instalment, and a schedule would be a fiction.
 *
 * Forcing this into the term shape is what produces the errors it is worth
 * naming: a `principal` invented at creation is wrong the moment he takes the
 * next 2,000, and a schedule generated against it reports instalments nobody
 * ever agreed to as overdue.
 *
 * In accounting terms this is not a loan at all, it is a CURRENT ACCOUNT —
 * which is exactly what 1351 Director's Current Account is for. Debit while he
 * owes the company, and it clears when he settles.
 *
 * ── WHY AN ENUM AND NOT A NULLABLE tenure ─────────────────────────────────
 * "tenure_months is null, so treat it as running" would be an inference, and
 * every screen would have to make the same inference identically forever. The
 * shape is a fact about the arrangement, so it is stored as one. Defaulting to
 * 'term' means every row that exists today keeps behaving exactly as it does
 * now — this migration changes no existing loan's numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_loans', function (Blueprint $table) {
            $table->enum('loan_shape', ['term', 'running'])
                  ->default('term')
                  ->after('interest_method');
        });
    }

    public function down(): void
    {
        Schema::table('financing_loans', function (Blueprint $table) {
            $table->dropColumn('loan_shape');
        });
    }
};
