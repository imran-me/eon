<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the beneficiary paid or was paid, next to the name that
 * 2026_09_14_000001 added: cash, their bank (with the account number), or a
 * cheque (with the bank and cheque number). Shown under the name on the
 * Beneficiary column the way our own account number sits under the bank.
 *
 * Nullable, additive, no default — nothing existing changes. Deploy with
 * --path, same as its sibling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entries', 'beneficiary_method')) {
                $table->string('beneficiary_method', 10)->nullable()->after('beneficiary');   // cash | bank | cheque
            }
            if (! Schema::hasColumn('journal_entries', 'beneficiary_bank')) {
                $table->string('beneficiary_bank', 150)->nullable()->after('beneficiary_method');
            }
            if (! Schema::hasColumn('journal_entries', 'beneficiary_ref')) {
                $table->string('beneficiary_ref', 100)->nullable()->after('beneficiary_bank');  // account no. or cheque no.
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            foreach (['beneficiary_ref', 'beneficiary_bank', 'beneficiary_method'] as $col) {
                if (Schema::hasColumn('journal_entries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
