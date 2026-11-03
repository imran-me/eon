<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who did business with a department (owner, 2026-10-17): Visa, Ticketing,
 * Contract Flight and Contract File accounts list only their own agents and
 * vendors — "a ticket's agent / vendor won't be in the visa accounts" — and a
 * party with cross sales or services appears in every department he used.
 *
 * A party belongs to a department when one of its records names him (a sale,
 * a purchase, a passenger, a service, a file) or the department posted to his
 * ledger (its own transactions.type prefix). Payments, advances, opening
 * balances and bank transfers name no department, so they make nobody belong.
 * Read-only.
 */
class DepartmentParties
{
    public const DEPARTMENTS = ['visa', 'ticketing', 'contract_flight', 'contract_file'];

    /** The transactions.type prefixes each department posts with (LIKE patterns). */
    private const TYPES = [
        'visa' => ['visa\_%', 'other\_visa%'],
        'ticketing' => ['ticket\_%'],
        'contract_flight' => ['contract\_flight%'],
        'contract_file' => ['contract\_file%'],
    ];

    /** [table, column] — the records that name a party, per department. */
    private const LINKS = [
        'visa' => [['visa_sales', 'client_id'], ['visa_processes', 'vendor_id']],
        'ticketing' => [['ticket_sales', 'client_id'], ['ticket_purchases', 'vendor_id'], ['ticket_changes', 'client_id'],
            ['ticket_refunds', 'agent_id'], ['ticket_refunds', 'vendor_id'], ['ticket_reissues', 'agent_id'],
            ['ticket_reissues', 'new_vendor_id'], ['ticket_bookings', 'vendor_id']],
        'contract_flight' => [['contract_flights', 'agent_id'], ['contract_flights', 'vendor_id'], ['contract_flight_passengers', 'agent_id'],
            ['contract_flight_passenger_service_costs', 'vendor_id'], ['contract_flight_passenger_changes', 'agent_id']],
        'contract_file' => [['contract_files', 'client_id'], ['contract_files', 'vendor_id'], ['contract_file_service_costs', 'vendor_id'],
            ['contract_file_sales', 'client_id']],
    ];

    /** @return int[] the user ids of the department's parties (every role — the ledger keeps its own). */
    public static function ids(string $dept): array
    {
        if (! in_array($dept, self::DEPARTMENTS, true)) {
            return [];
        }
        $ids = [];
        foreach (self::LINKS[$dept] as [$table, $col]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $col)) {
                continue;
            }
            $q = DB::table($table)->whereNotNull($col);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $ids = array_merge($ids, $q->distinct()->pluck($col)->all());
        }
        // Contract Flight: the sellers of the tickets bought for its passengers.
        if ($dept === 'contract_flight' && Schema::hasTable('contract_flight_passenger_tickets')) {
            $ids = array_merge($ids, DB::table('contract_flight_passenger_tickets as l')
                ->join('ticket_purchases as p', 'p.id', '=', 'l.ticket_purchase_id')
                ->whereNull('p.deleted_at')->whereNotNull('p.vendor_id')->distinct()->pluck('p.vendor_id')->all());
        }
        $tx = DB::table('transactions')->whereNotNull('user_id')
            ->where(function ($q) use ($dept) {
                foreach (self::TYPES[$dept] as $t) {
                    $q->orWhere('type', 'like', $t);
                }
            });
        if (Schema::hasColumn('transactions', 'deleted_at')) {
            $tx->whereNull('deleted_at');
        }
        $ids = array_merge($ids, $tx->distinct()->pluck('user_id')->all());

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** "Visa" — the department's name on a screen. */
    public static function label(string $dept): string
    {
        return ['visa' => 'Visa', 'ticketing' => 'Ticketing', 'contract_flight' => 'Contract Flight', 'contract_file' => 'Contract File'][$dept] ?? $dept;
    }
}
