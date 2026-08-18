<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('accounts')->where('code', '5001')->doesntExist()) {
            DB::table('accounts')->insert([
                'code'            => '5001',
                'name'            => 'Ticket Purchase Cost',
                'type'            => 'expense',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '5001')->delete();
    }
};
