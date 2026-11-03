<?php

use App\Models\ExpenseDepartment;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseGeneration;
use App\Models\User;
use App\Services\ExpenseClassificationService;
use App\Services\RecurringExpenseService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$actor = User::where('is_super_admin', 1)->firstOrFail();
$department = ExpenseDepartment::active()->firstOrFail();
$classification = app(ExpenseClassificationService::class)->payload((int) $department->company_id);
$category = collect($classification['categories'])->first(fn (array $item) => ! empty($item['account_id']));
if (! $category) {
    throw new RuntimeException('No operating expense category with a mapped account was found.');
}

$checks = 0;
$assert = function (bool $condition, string $message) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};

DB::beginTransaction();
try {
    $template = RecurringExpense::create([
        'company_id' => $department->company_id,
        'expense_department_id' => $department->id,
        'name' => 'Recurring integration check',
        'generation_day' => 1,
        'start_month' => Carbon::parse('2026-10-01'),
        'payment_mode' => 'cash',
        'is_active' => true,
        'created_by' => $actor->id,
    ]);
    $template->lines()->create([
        'expense_category_id' => $category['id'],
        'account_id' => $category['account_id'],
        'description' => 'Monthly fixed cost',
        'amount' => 1250.50,
        'sort_order' => 0,
    ]);

    $service = app(RecurringExpenseService::class);
    $first = $service->generate($template, Carbon::parse('2026-10-15'));
    $second = $service->generate($template, Carbon::parse('2026-10-20'));

    $assert($first !== null, 'The first monthly draft was not generated.');
    $assert($second === null, 'The same schedule generated twice for one month.');
    $assert($first->approval_status === 'pending', 'Generated expense bypassed approval.');
    $assert((float) $first->amount === 1250.50, 'Generated expense total is incorrect.');
    $assert($first->items()->count() === 1, 'Generated expense lines are incomplete.');
    $assert(RecurringExpenseGeneration::where('recurring_expense_id', $template->id)->count() === 1, 'Generation register is not idempotent.');

    echo "Recurring expense integration checks passed: {$checks}\n";
} finally {
    DB::rollBack();
}
