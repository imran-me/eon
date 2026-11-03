<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contract flight services are now four, each with a responsible person
 * (owner, 2026-09-21): Immigration Training, Boarding Training, BMET / Work
 * Permit Consulting, Others. Boarding used to be "always included"; it is
 * now ticked per passenger like the rest, so the passenger needs a flag.
 * Existing passengers default to true — they were all boarded by us.
 *
 * The responsible people are agents of a type — Trainer or Consultant —
 * carried as extra Spatie roles beside 'agent', so they stay in every agent
 * list and ledger. The roles are created here so `User::role('trainer')`
 * never throws RoleDoesNotExist on a server that has not opened the agent
 * form yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->boolean('boarding_training')->default(true)->after('has_manpower_card');
        });

        foreach (['trainer', 'consultant'] as $role) {
            $exists = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->exists();
            if (! $exists) {
                DB::table('roles')->insert([
                    'name' => $role, 'guard_name' => 'web',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->dropColumn('boarding_training');
        });
        // The roles are left in place: users may carry them by then.
    }
};
