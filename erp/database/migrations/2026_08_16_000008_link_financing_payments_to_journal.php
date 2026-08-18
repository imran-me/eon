<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties a financing payment to the journal entry it posted.
 *
 * Repaying a BORROWING is now written to the books at the moment it is
 * recorded, rather than left for someone to enter again in Manage Banks. This
 * column is what makes that traceable: which entry a payment produced, so it
 * can be found, audited and reversed rather than merely deleted.
 *
 * nullable, because three kinds of payment legitimately post nothing:
 *   - a payment on a LENT loan whose money is already on the books as a
 *     receivable from the original sale — posting it again would count the
 *     same taka twice;
 *   - a PERSONAL borrowing, which is not the company's liability at all and
 *     has no company to post under;
 *   - a payment recorded with method 'adjustment', which by definition moves
 *     no money.
 *
 * nullOnDelete: if a journal entry is ever removed, the payment record must
 * survive with its own figures rather than disappear with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_transactions', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->nullable()->after('bank_id')
                  ->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financing_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_entry_id');
        });
    }
};
