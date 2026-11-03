<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Company Ledger's chart heads (owner, 2026-09-28): what the company
 * itself absorbs when a contract flight goes wrong on OUR side, and what it
 * recovers from the agent when it went wrong on theirs.
 *
 *   5170 Contract Flight - Company Borne Losses   (expense group, under 5100)
 *     5171 Airline Cancellation Penalty (Company Borne)
 *     5172 Fare Difference Absorbed
 *     5173 Rebooking & Change Fees (Company Borne)
 *   4180 Contract Flight - Recoveries             (income group, under 4100)
 *     4181 Cancellation Penalty Recovered
 *     4182 Fare Difference Recovered
 *     4183 Rebooking & Change Charges Recovered
 *
 * Travels only (company_id 2) — only Travels runs contract flights, the same
 * reason 4150 / 5150 are company-owned. The Company Ledger screen reads every
 * leaf under the two groups, so a head the accountant adds later under either
 * one appears there without code.
 *
 * Idempotent: a code that already exists is left alone, never renamed.
 */
return new class extends Migration
{
    private const HEADS = [
        ['5170', 'Contract Flight - Company Borne Losses', 'expense', '5100'],
        ['5171', 'Airline Cancellation Penalty (Company Borne)', 'expense', '5170'],
        ['5172', 'Fare Difference Absorbed', 'expense', '5170'],
        ['5173', 'Rebooking & Change Fees (Company Borne)', 'expense', '5170'],
        ['4180', 'Contract Flight - Recoveries', 'income', '4100'],
        ['4181', 'Cancellation Penalty Recovered', 'income', '4180'],
        ['4182', 'Fare Difference Recovered', 'income', '4180'],
        ['4183', 'Rebooking & Change Charges Recovered', 'income', '4180'],
    ];

    public function up(): void
    {
        foreach (self::HEADS as [$code, $name, $type, $parentCode]) {
            if (DB::table('accounts')->where('code', $code)->whereNull('deleted_at')->exists()) {
                continue;
            }
            $parentId = DB::table('accounts')->where('code', $parentCode)->whereNull('deleted_at')->value('id');
            DB::table('accounts')->insert([
                'code'            => $code,
                'name'            => $name,
                'type'            => $type,
                'parent_id'       => $parentId,
                'company_id'      => 2,
                'opening_balance' => 0,
                'status'          => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Only heads nothing has posted to — a head with journal lines stays.
        foreach (array_reverse(self::HEADS) as [$code]) {
            $id = DB::table('accounts')->where('code', $code)->whereNull('deleted_at')->value('id');
            if ($id && ! DB::table('journal_items')->where('account_id', $id)->exists()
                && ! DB::table('accounts')->where('parent_id', $id)->whereNull('deleted_at')->exists()) {
                DB::table('accounts')->where('id', $id)->delete();
            }
        }
    }
};
