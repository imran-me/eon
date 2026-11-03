<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseGeneration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringExpenseService
{
    public function generate(RecurringExpense $template, Carbon $period): ?Expense
    {
        $period = $period->copy()->startOfMonth();
        if (! $template->is_active
            || $period->lt($template->start_month->copy()->startOfMonth())
            || ($template->end_month && $period->gt($template->end_month->copy()->startOfMonth()))) {
            return null;
        }

        return DB::transaction(function () use ($template, $period) {
            $template = RecurringExpense::query()->with('lines')->lockForUpdate()->findOrFail($template->id);
            $existing = RecurringExpenseGeneration::where('recurring_expense_id', $template->id)
                ->whereDate('period', $period->toDateString())
                ->first();
            if ($existing) {
                return null;
            }

            $lines = $template->lines;
            if ($lines->isEmpty()) {
                return null;
            }

            $first = $lines->first();
            $total = round((float) $lines->sum('amount'), 2);
            $expense = Expense::create([
                'user_id' => $template->created_by,
                'company_id' => $template->company_id,
                'expense_department_id' => $template->expense_department_id,
                'account_id' => $first->account_id,
                'expense_category_id' => $first->expense_category_id,
                'expense_sub_category_id' => $first->expense_sub_category_id,
                'title' => $template->name,
                'description' => trim('Recurring expense for '.$period->format('F Y').($template->description ? ' — '.$template->description : '')),
                'amount' => $total,
                'payment_mode' => $template->payment_mode,
                'bank_id' => $template->bank_id,
                'reference' => 'REC-'.$template->id.'-'.$period->format('Ym'),
                'expense_date' => $period->toDateString(),
                'transaction_time' => '01:10:00',
                'status' => true,
                'approval_status' => Expense::PENDING,
            ]);

            foreach ($lines as $line) {
                $expense->items()->create([
                    'account_id' => $line->account_id,
                    'expense_category_id' => $line->expense_category_id,
                    'expense_sub_category_id' => $line->expense_sub_category_id,
                    'description' => $line->description,
                    'quantity' => 1,
                    'unit_price' => $line->amount,
                    'amount' => $line->amount,
                ]);
            }

            RecurringExpenseGeneration::create([
                'recurring_expense_id' => $template->id,
                'period' => $period->toDateString(),
                'expense_id' => $expense->id,
                'generated_at' => now(),
            ]);

            return $expense;
        });
    }
}
