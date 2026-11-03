<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a bank deposit / withdrawal was for, as its own field.
 *
 * Until now the person's name lived inside the typed remark ("MR. SHIARAJ
 * VAI - TICKET PAYMENT") and the Beneficiary column on Manage Banks had to
 * be parsed back out of the description. That works only when the remark was
 * typed with a separator; "MR. SALEH TICKET PAYMENT" cannot be split. The
 * deposit/withdraw form now asks for the name and the purpose separately,
 * and the name is stored here. Description keeps only the purpose.
 *
 * Nullable, additive, no default — every existing row and every other writer
 * of journal_entries is untouched. Rows without it still fall back to the
 * description split and the party/counter-account on the entry.
 *
 * Deploy with --path so nothing else pending in this directory runs with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('journal_entries', 'beneficiary')) {
            return;
        }
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('beneficiary', 150)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('journal_entries', 'beneficiary')) {
            return;
        }
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('beneficiary');
        });
    }
};
