<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deported now depends on whose side it was (owner, 2026-09-28):
 *   'deported'          agent side — he repays, the package charge stands
 *   'deported_we_bear'  our side   — we bear it, the package is reversed
 *
 * Until now every deportation reversed the package. Passengers already
 * flagged 'deported' keep that meaning by moving to 'deported_we_bear', so
 * no statement changes for anyone. Data only; varchar(20) already fits.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('contract_flight_passengers', 'exception_flag')) {
            DB::table('contract_flight_passengers')->where('exception_flag', 'deported')
                ->update(['exception_flag' => 'deported_we_bear']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_flight_passengers', 'exception_flag')) {
            DB::table('contract_flight_passengers')->where('exception_flag', 'deported_we_bear')
                ->update(['exception_flag' => 'deported']);
        }
    }
};
