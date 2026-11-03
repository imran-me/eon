<?php

namespace App\Console\Commands;

use App\Models\RecurringExpense;
use App\Models\RecurringExpenseGeneration;
use App\Services\RecurringExpenseService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:generate-recurring {--month= : Y-m period, defaults to current month} {--dry-run : Show what would be generated}';

    protected $description = 'Generate pending monthly expenses from active recurring-expense schedules';

    public function handle(RecurringExpenseService $service): int
    {
        if (! Schema::hasTable('recurring_expenses')) {
            $this->warn('Recurring expenses are not installed.');

            return self::SUCCESS;
        }

        $period = $this->option('month')
            ? Carbon::createFromFormat('Y-m', (string) $this->option('month'))->startOfMonth()
            : now()->startOfMonth();
        $today = now();
        $templates = RecurringExpense::query()
            ->with('lines')
            ->where('is_active', true)
            ->whereDate('start_month', '<=', $period->copy()->endOfMonth())
            ->where(fn ($query) => $query->whereNull('end_month')->orWhereDate('end_month', '>=', $period))
            ->when(! $this->option('month'), fn ($query) => $query->where('generation_day', '<=', $today->day))
            ->get();

        $generated = 0;
        $skipped = 0;
        foreach ($templates as $template) {
            $exists = RecurringExpenseGeneration::where('recurring_expense_id', $template->id)
                ->whereDate('period', $period)->exists();
            if ($exists || $template->lines->isEmpty()) {
                $skipped++;

                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("Would generate: {$template->name} — ".number_format((float) $template->lines->sum('amount'), 2));
                $generated++;

                continue;
            }
            if ($service->generate($template, $period)) {
                $generated++;
            }
        }

        $this->info(($this->option('dry-run') ? '[DRY RUN] Would generate ' : 'Generated ')."{$generated}; skipped {$skipped} for {$period->format('F Y')}.");

        return self::SUCCESS;
    }
}
