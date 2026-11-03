<?php

namespace Tests\Unit\Accounting;

use App\Services\ExpenseClassificationService;
use PHPUnit\Framework\TestCase;

class MainExpenseAccountPolicyTest extends TestCase
{
    public function test_main_expense_policy_contains_the_owner_named_heads_without_total_rows(): void
    {
        $heads = ExpenseClassificationService::MAIN_EXPENSE_ACCOUNT_NAMES;

        $this->assertContains('Consultancy Fees', $heads);
        $this->assertContains('Software renewal & Subscription', $heads);
        $this->assertContains('Corporate Office (Electricity)', $heads);
        $this->assertContains('VAT On Sales', $heads);
        $this->assertNotContains('Operating Expense', $heads);
        $this->assertEmpty(array_filter($heads, fn (string $head) => str_starts_with($head, 'Total for')));
        $this->assertSame($heads, array_values(array_unique($heads)));
    }

    public function test_main_expense_uses_only_overall_operating_cost_departments(): void
    {
        $this->assertSame('Overall Cost', ExpenseClassificationService::OPERATING_COST_DEPARTMENT_DESCRIPTION);
    }
}
