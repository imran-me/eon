<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyPayslip;
use App\Models\EmployeeSalary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyPayroll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:generate-monthly {--month= : Y-m to generate for, defaults to last month} {--dry-run : List who would be queued without dispatching anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue salary + payslip generation for every eligible employee for the given (default: previous) month';

    public function handle(): void
    {
        $target = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))
            : now()->subMonthNoOverflow();

        $year = (int) $target->year;
        $month = (int) $target->month;
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $employees = User::role('employee')
            ->where('is_super_admin', 0)
            ->where('status', 'active')
            ->where('auto_payslip_enabled', true)
            ->whereHas('profile', fn ($q) => $q->where('salary', '>', 0))
            ->get();

        Log::info('Monthly payroll run started', [
            'month' => $target->format('Y-m'),
            'dry_run' => (bool) $this->option('dry-run'),
            'eligible_employees' => $employees->count(),
        ]);

        $this->info("Generating payroll for {$target->format('F Y')} — {$employees->count()} eligible employee(s) on file.");

        $queued = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $alreadyExists = EmployeeSalary::where('user_id', $employee->id)
                ->where('year', $year)
                ->where('month', $monthStr)
                ->exists();

            if ($alreadyExists) {
                $skipped++;
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  Would queue: {$employee->name} (#{$employee->id})");
                $queued++;
                continue;
            }

            GenerateMonthlyPayslip::dispatch($employee->id, $year, $month);
            $queued++;
        }

        Log::info('Monthly payroll run finished', [
            'month' => $target->format('Y-m'),
            'dry_run' => (bool) $this->option('dry-run'),
            'queued' => $queued,
            'skipped' => $skipped,
        ]);

        $this->info(($this->option('dry-run') ? '[DRY RUN] Would queue' : 'Queued') . " {$queued} employee(s); skipped {$skipped} (already had a salary record for {$target->format('F Y')}).");
    }
}
