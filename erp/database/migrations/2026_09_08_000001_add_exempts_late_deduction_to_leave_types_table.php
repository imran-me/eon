<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('exempts_late_deduction')
                ->default(false)
                ->after('exempts_early_out_deduction');
        });

        $lateInType = DB::table('leave_types')
            ->whereRaw('LOWER(name) = ?', ['late in'])
            ->first();

        if ($lateInType) {
            DB::table('leave_types')->where('id', $lateInType->id)->update([
                'requires_time' => true,
                'exempts_late_deduction' => true,
                'updated_at' => now(),
            ]);
        } else {
            $earlyLeaveLimit = DB::table('leave_types')
                ->whereRaw('LOWER(name) = ?', ['early leave'])
                ->value('max_leaves_count');

            DB::table('leave_types')->insert([
                'name' => 'Late In',
                'max_leaves_count' => $earlyLeaveLimit ?? 10,
                'requires_time' => true,
                'exempts_early_out_deduction' => false,
                'exempts_late_deduction' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('exempts_late_deduction');
        });
    }
};
