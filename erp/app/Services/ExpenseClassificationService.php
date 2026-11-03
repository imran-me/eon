<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\ExpenseDepartment;
use App\Models\ExpenseSubCategory;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * What an expense is FOR — Company › Department › Category › Sub-category.
 *
 * The form lets you fill that chain starting at ANY level rather than forcing
 * you top-down, because the person spending the money usually knows the specific
 * thing ("office fuel") and not the group it files under. Picking the specific
 * thing should fill in the rest, not be blocked by it.
 *
 * Every level resolves upward as a FACT rather than a guess, because every level
 * carries its company as a column: a sub-category names its category, and both a
 * category and an expense department name their company.
 *
 * That is why the expense desk keeps its own departments instead of borrowing
 * HR's. HR departments are group-wide and company-less — with those, "pick the
 * department and the company fills in" could only ever be inferred from what had
 * been booked before, and had to stay silent wherever a department spanned
 * companies. See ExpenseDepartment.
 */
class ExpenseClassificationService
{
    /** Departments used for company-wide operating costs, not project costs. */
    public const OPERATING_COST_DEPARTMENT_DESCRIPTION = 'Overall Cost';

    /** @var Collection<int, int>|null */
    private ?Collection $mainExpenseAccountIds = null;

    /**
     * Expense heads that belong on ERP's operational Expense desk.
     *
     * Everything else is still a valid chart-of-accounts head, but is entered
     * from Accounting > Payment Entry. Keeping this list in one server-side
     * place makes the split a posting rule rather than a dropdown convention.
     */
    public const MAIN_EXPENSE_ACCOUNT_NAMES = [
        'Consultancy Fees',
        'Bank Fees and Charges',
        'Cleaning and Freshness',
        'Corporate Mobile Bill',
        'Cultural Program & Entertainment',
        'Management Remuneration',
        'Office Accessories',
        'Office Stationery & Printing',
        'Online Marchant banking Charge',
        'Product Sourcing Cost',
        'Repairs and Maintenance',
        'Salaries and Employee Wages',
        'Sales Commission',
        'Transportation',
        'Software renewal & Subscription',
        'CHAT-GPT',
        'Other Software Expense',
        'Website Maintenance',
        'ERP (Accounting Software)',
        'Electricity Expense',
        'Corporate Office (Electricity)',
        'Other Office Electricity',
        'Rent Expense',
        'Corporate Office Rent Expense',
        'Other Office Rent Expense',
        'Service Charge (Rent)',
        'Corporate Office Service Charge',
        'Other Office Service Charge',
        'Brand Development Expenses',
        'In-house Content Expenses',
        'Out Source Content Expenses',
        'Internet Expenses',
        'Corporate Office Internet Expense',
        'Others Internet Expense',
        'Employee Benefits',
        'Performance Bonus',
        'Training Allowances',
        'Food & Allowance',
        'Snacks and Entertainment',
        'VAT Expense',
        'VAT On Office Rent',
        'VAT On Sales',
    ];

    /** @return Collection<int, int> */
    public function mainExpenseAccountIds(): Collection
    {
        if ($this->mainExpenseAccountIds !== null) {
            return $this->mainExpenseAccountIds;
        }

        $wanted = collect(self::MAIN_EXPENSE_ACCOUNT_NAMES)
            ->map(fn (string $name) => $this->normaliseAccountName($name))
            ->flip();

        $accounts = Account::query()
            ->where('type', 'expense')
            ->where('status', 1)
            ->get(['id', 'name', 'parent_id']);

        // Charts created by the newer setup use a 6000 OPERATING EXPENSES
        // parent and more formal leaf names (for example "Office Supplies &
        // Stationery"). They are the same operating-expense family, so accept
        // that tree as well as the owner's legacy names. Do not include the
        // summary parent itself: payment lines belong on a posting head.
        $operatingRoots = $accounts
            ->filter(fn (Account $account) => in_array($this->normaliseAccountName($account->name), [
                'operating expense',
                'operating expenses',
            ], true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $allowedIds = $accounts
            ->filter(fn (Account $account) => $wanted->has($this->normaliseAccountName($account->name)))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
        $frontier = $operatingRoots;

        while ($frontier->isNotEmpty()) {
            $children = $accounts
                ->filter(fn (Account $account) => $frontier->contains((int) $account->parent_id))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();
            $allowedIds = $allowedIds->merge($children);
            $frontier = $children;
        }

        return $this->mainExpenseAccountIds = $allowedIds->unique()->values();
    }

    /**
     * Everything the picker needs, in one payload.
     *
     * Sent whole rather than fetched per keystroke: nine categories and 31
     * sub-categories is a few kilobytes, and a round trip per character would be
     * slower and worse offline than filtering in the browser.
     *
     * A category with no company is SHARED and offered to everyone; one with a
     * company belongs to that company alone. So narrowing to a company keeps the
     * shared vocabulary and adds only that company's own — it never strands
     * someone with an empty list. Categories used to be copied once per company
     * instead, which put four rows named "Communication" in one dropdown with
     * nothing to tell them apart. See the 2026_08_10 merge migration.
     */
    public function payload(?int $companyId = null): array
    {
        $allowedAccountIds = $this->mainExpenseAccountIds();

        $categories = ExpenseCategory::query()
            ->where('status', 1)
            ->when($companyId, fn ($q) => $q->where(
                fn ($w) => $w->whereNull('company_id')->orWhere('company_id', $companyId)
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'company_id', 'account_id']);

        $subCategories = ExpenseSubCategory::query()
            ->where('status', 1)
            ->whereIn('expense_category_id', $categories->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'company_id', 'expense_category_id', 'account_id']);

        $subCategories = $subCategories
            ->filter(fn (ExpenseSubCategory $subCategory) => $allowedAccountIds->contains((int) $subCategory->account_id))
            ->values();

        $categoryIdsWithAllowedChildren = $subCategories->pluck('expense_category_id')->map(fn ($id) => (int) $id);
        $categories = $categories
            ->filter(fn (ExpenseCategory $category) => $allowedAccountIds->contains((int) $category->account_id)
                || $categoryIdsWithAllowedChildren->contains((int) $category->id))
            ->values();

        return [
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'company_id' => $c->company_id,
                'account_id' => $c->account_id,
            ])->values(),

            'subCategories' => $subCategories->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'category_id' => $s->expense_category_id,
                'account_id' => $s->account_id,
                'company_id' => $s->company_id,
            ])->values(),

            'departments' => $this->departments($companyId)->values(),
        ];
    }

    /**
     * The expense desk's departments, each naming the company it belongs to.
     *
     * A HARD link, not a guess. This used to read HR's `departments`, which is
     * group-wide with no company of its own, so the only way to offer a company
     * was to infer one from what had previously been booked — and stay silent
     * wherever a department spanned companies. ExpenseDepartment carries its
     * company as a column, so every row resolves and the form fills the company
     * in the moment a department is chosen.
     */
    public function departments(?int $companyId = null): Collection
    {
        return ExpenseDepartment::query()
            ->where('status', 1)
            ->where('description', self::OPERATING_COST_DEPARTMENT_DESCRIPTION)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name', 'company_id'])
            ->map(fn (ExpenseDepartment $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'company_id' => (int) $d->company_id,
            ]);
    }

    public function isOperatingCostDepartment(ExpenseDepartment $department): bool
    {
        return $department->status
            && strcasecmp(trim((string) $department->description), self::OPERATING_COST_DEPARTMENT_DESCRIPTION) === 0;
    }

    /**
     * Which chart-of-accounts account an expense should be debited to.
     *
     * The taxonomy knows; the user should not have to. Resolved narrowest-first,
     * because the chart is finer than the category: Communication spans 6309
     * Internet & Broadband and 6310 Telephone & Mobile, and Food & Beverage has a
     * leaf per sub-category under 8100. Asking the category alone would post a
     * broadband bill and a phone bill to the same line and lose the split the
     * chart was built to carry.
     *
     *     sub-category account → category account → general expense
     *
     * Each step is a fallback, not a requirement, so a sub-category the chart has
     * no line for yet — Postage & Courier, Newspaper & Magazine — still posts,
     * just at its category's level. Posting never fails for want of a code, and
     * this returns null only if the chart itself is missing the general expense
     * account, which the caller reports rather than swallows.
     */
    public function accountFor(?int $categoryId, ?int $subCategoryId = null): ?int
    {
        $subMapped = $subCategoryId
            ? ExpenseSubCategory::whereKey($subCategoryId)->value('account_id')
            : null;

        if ($this->isPostableExpenseAccount($subMapped)) {
            return (int) $subMapped;
        }

        $categoryMapped = $categoryId
            ? ExpenseCategory::whereKey($categoryId)->value('account_id')
            : null;

        if ($this->isPostableExpenseAccount($categoryMapped)) {
            return (int) $categoryMapped;
        }

        return Account::query()
            ->where('code', config('accounts.general_expense'))
            ->where('type', 'expense')
            ->where('status', 1)
            ->value('id');
    }

    /** Persist each line's classification and resolved head, not just a header label. */
    public function resolveLine(array $line, int $companyId, ?int $defaultCategory, ?int $defaultSub): array
    {
        $categoryId = ! empty($line['expense_category_id']) ? (int) $line['expense_category_id'] : $defaultCategory;
        $subId = ! empty($line['expense_sub_category_id']) ? (int) $line['expense_sub_category_id']
            : (empty($line['expense_category_id']) ? $defaultSub : null);
        $category = $categoryId ? ExpenseCategory::find($categoryId) : null;
        $sub = $subId ? ExpenseSubCategory::find($subId) : null;
        if (! $category || ! $category->status || ($category->company_id && (int) $category->company_id !== $companyId)) {
            throw ValidationException::withMessages(['items' => 'Choose an active line category available to this company.']);
        }
        if ($subId && (! $sub || ! $sub->status || (int) $sub->expense_category_id !== $categoryId
            || ($sub->company_id && (int) $sub->company_id !== $companyId))) {
            throw ValidationException::withMessages(['items' => 'The line sub-category must belong to its category and company.']);
        }
        $accountId = ! empty($line['account_id']) ? (int) $line['account_id'] : $this->accountFor($categoryId, $subId);
        if (! $this->isPostableExpenseAccount($accountId)) {
            throw ValidationException::withMessages(['items' => 'Choose an active expense head for every line.']);
        }

        return ['expense_category_id' => $categoryId, 'expense_sub_category_id' => $subId, 'account_id' => $accountId];
    }

    private function isPostableExpenseAccount($accountId): bool
    {
        return $accountId && $this->mainExpenseAccountIds()->contains((int) $accountId);
    }

    private function normaliseAccountName(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }
}
