<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'reopened' to payment_schedule_logs.action.
 *
 * PaymentScheduleController::reopen() puts a cancelled schedule back in the
 * queue and logs the fact. The column is an ENUM and the database runs with
 * STRICT_TRANS_TABLES, so without this the log insert throws and the whole
 * reopen fails — additive only, every existing value is kept.
 */
return new class extends Migration
{
    private const ACTIONS = [
        'rescheduled', 'approved', 'rejected', 'paid', 'cancelled', 'partial_paid',
    ];

    public function up(): void
    {
        $this->setActions(array_merge(self::ACTIONS, ['reopened']));
    }

    public function down(): void
    {
        // Rows written by reopen() have no equivalent in the old set; leaving
        // them in place would make the ALTER fail (or silently blank them
        // under a laxer sql_mode), so they are folded into the nearest
        // surviving action first. Their `reason` text still says what
        // happened.
        DB::table('payment_schedule_logs')
            ->where('action', 'reopened')
            ->update(['action' => 'rescheduled']);

        $this->setActions(self::ACTIONS);
    }

    private function setActions(array $actions): void
    {
        $list = implode(',', array_map(fn ($a) => "'" . $a . "'", $actions));

        DB::statement("ALTER TABLE payment_schedule_logs MODIFY action ENUM({$list}) NULL");
    }
};
