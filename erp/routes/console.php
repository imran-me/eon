<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// appendOutputTo() writes every run's output (including "did nothing, not
// due yet" silence and any errors) to one file — the fastest way to confirm
// the server's cron is actually invoking schedule:run at all, without
// waiting for a task's real due date.
$scheduleLog = storage_path('logs/schedule.log');

Schedule::command('schedules:mark-overdue')
    ->dailyAt('00:05')
    ->appendOutputTo($scheduleLog);

Schedule::command('payroll:generate-monthly')
    ->monthlyOn(1, '01:00')
    ->appendOutputTo($scheduleLog);

// Runs daily so a missed 1st-of-month cron catches up on the next successful
// scheduler tick. Each schedule + month is unique, so reruns cannot duplicate.
Schedule::command('expenses:generate-recurring')
    ->dailyAt('01:10')
    ->withoutOverlapping()
    ->appendOutputTo($scheduleLog);

// Drains the queue (payroll jobs, chat broadcasts, email campaigns, etc.)
// without needing a persistent `queue:work` daemon — the only option on
// shared hosting, where long-running background processes aren't allowed.
// withoutOverlapping() stops a slow run from stacking with the next minute's.
Schedule::command('queue:work --queue=payroll,default --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo($scheduleLog);

Schedule::command('tickets:process-booking-reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo($scheduleLog);

// Cancel return (2026-10-14): 3 days / 1 day / on the day before a family
// ticket's return window closes. Does nothing until its migration has run.
Schedule::command('tickets:return-cancel-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo($scheduleLog);
