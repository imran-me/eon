<?php

namespace App\Console\Commands;

use App\Models\PaymentSchedule;
use Illuminate\Console\Command;

class MarkOverdueSchedules extends Command
{
    protected $signature   = 'schedules:mark-overdue';
    protected $description = 'Mark pending payment schedules as overdue if their date has passed';

    public function handle(): void
    {
        $count = PaymentSchedule::where('status', 'pending')
            ->where('scheduled_date', '<', today())
            ->update(['status' => 'overdue']);

        $this->info("Marked {$count} schedule(s) as overdue.");
    }
}
