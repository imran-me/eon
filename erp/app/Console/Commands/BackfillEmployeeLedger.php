<?php

namespace App\Console\Commands;

use App\Models\EmployeeLedger;
use App\Models\EmployeeSalary;
use App\Models\Payment;
use App\Traits\PostsEmployeeLedger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillEmployeeLedger extends Command
{
    use PostsEmployeeLedger;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-employee-ledger {--dry-run : Preview counts without writing any rows}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill employee_ledger rows from existing employee_salaries + payments history';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        $userIds = EmployeeSalary::distinct()->pluck('user_id');
        $this->info("Found {$userIds->count()} employees with salary history.");

        $bar = $this->output->createProgressBar($userIds->count());
        $bar->start();

        $totalRows = 0;
        $skippedUsers = 0;

        foreach ($userIds as $userId) {
            // Don't double-post if this user already has ledger rows (e.g. re-running the command).
            if (EmployeeLedger::where('user_id', $userId)->exists()) {
                $skippedUsers++;
                $bar->advance();
                continue;
            }

            $events = collect();

            $salaries = EmployeeSalary::where('user_id', $userId)->orderBy('year')->orderBy('month')->get();

            foreach ($salaries as $salary) {
                $earnedDate = $salary->salary_generation_date ?? $salary->created_at;

                $events->push([
                    'date'       => Carbon::parse($earnedDate),
                    'seq'        => 0, // debit lands before any same-day credit
                    'type'       => 'salary_earned',
                    'entry_date' => $earnedDate,
                    'reference'  => Carbon::createFromDate((int) $salary->year, (int) $salary->month, 1)->format('F Y') . ' salary (net of deductions)',
                    'debit'      => $salary->net_salary,
                    'credit'     => 0,
                    'source'     => $salary,
                ]);

                $payments = Payment::where('employee_salary_id', $salary->id)->get();

                if ($payments->isNotEmpty()) {
                    foreach ($payments as $payment) {
                        $paidDate = $payment->payment_date ?? $earnedDate;
                        $events->push([
                            'date'       => Carbon::parse($paidDate),
                            'seq'        => 1,
                            'type'       => 'salary_paid',
                            'entry_date' => $paidDate,
                            'reference'  => 'Salary paid via ' . ($payment->payment_method ?? 'unknown'),
                            'debit'      => 0,
                            'credit'     => $payment->amount,
                            'source'     => $salary,
                        ]);
                    }
                } elseif (in_array(strtolower($salary->status), ['paid'])) {
                    // Marked Paid via the direct edit form (no Payment/PaymentSchedule row exists) —
                    // reconstruct a single full-amount credit on the best available date.
                    $paidDate = $salary->scheduled_date ?? $salary->salary_generation_date ?? $salary->updated_at;
                    $events->push([
                        'date'       => Carbon::parse($paidDate),
                        'seq'        => 1,
                        'type'       => 'salary_paid',
                        'entry_date' => $paidDate,
                        'reference'  => 'Salary paid (backfilled — no payment record on file)',
                        'debit'      => 0,
                        'credit'     => $salary->net_salary,
                        'source'     => $salary,
                    ]);
                }
            }

            $events = $events->sort(function ($a, $b) {
                return [$a['date']->timestamp, $a['seq']] <=> [$b['date']->timestamp, $b['seq']];
            })->values();

            if (! $dryRun) {
                DB::transaction(function () use ($events, $userId) {
                    foreach ($events as $event) {
                        $this->postEmployeeLedgerRow($userId, [
                            'type'       => $event['type'],
                            'entry_date' => $event['entry_date'],
                            'reference'  => $event['reference'],
                            'debit'      => $event['debit'],
                            'credit'     => $event['credit'],
                        ], $event['source']);
                    }
                });
            }

            $totalRows += $events->count();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(($dryRun ? '[DRY RUN] Would create' : 'Created') . " {$totalRows} ledger rows across " . ($userIds->count() - $skippedUsers) . ' employees.');
        if ($skippedUsers > 0) {
            $this->comment("Skipped {$skippedUsers} employee(s) who already had ledger rows.");
        }
    }
}
