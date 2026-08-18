<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give every loan that already existed its disbursement row.
 *
 * loan_transactions arrived after the loans it has to describe, so the loans on
 * file have no movement behind them at all. The register and the transaction
 * trail both read those rows, and without this they would open on a book whose
 * loans were apparently never paid out — nothing lent, everything owed.
 *
 * Only the money going OUT is reconstructed. Repayments are deliberately left
 * alone: what came back before this table existed is already carried by
 * loans.opening_paid_amount, and inventing dated rows for it would be putting
 * payments in the book on days nobody can vouch for.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('loan_transactions')
            ->where('type', 'disburse')
            ->pluck('loan_id')
            ->all();

        // withTrashed by design — a soft-deleted loan keeps its history so the
        // trash screen and any restore still read a complete book.
        $loans = DB::table('loans')
            ->when($existing, fn ($q) => $q->whereNotIn('id', $existing))
            ->get();

        if ($loans->isEmpty()) {
            return;
        }

        $banks = DB::table('banks')->pluck('account_id', 'id');

        // The disbursement journal LoanController has been posting all along —
        // matched back up so the row points at its own posting rather than
        // looking like an unposted movement.
        $journals = DB::table('journal_entries')
            ->where('source', 'loan')
            ->pluck('id', 'source_id');

        $now = now();
        $rows = [];

        foreach ($loans as $loan) {
            $rows[] = [
                'loan_id'            => $loan->id,
                'user_id'            => $loan->user_id,
                'type'               => 'disburse',
                'method'             => $loan->bank_id ? 'bank' : 'cash',
                'amount'             => $loan->amount,
                'date'               => $loan->start_date ?: $loan->created_at,
                'account_id'         => $loan->bank_id ? ($banks[$loan->bank_id] ?? null) : null,
                'bank_id'            => $loan->bank_id,
                'employee_salary_id' => null,
                'journal_entry_id'   => $journals[$loan->id] ?? null,
                'note'               => 'Staff loan disbursed',
                'created_by'         => null,
                'created_at'         => $now,
                'updated_at'         => $now,
                'deleted_at'         => $loan->deleted_at,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('loan_transactions')->insert($chunk);
        }
    }

    public function down(): void
    {
        // Only the reconstructed rows go — a disbursement recorded by the app
        // since carries a created_by, and is real history.
        DB::table('loan_transactions')
            ->where('type', 'disburse')
            ->whereNull('created_by')
            ->delete();
    }
};
