<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ExpenseDepartment;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubCategory;
use Illuminate\Support\Collection;

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
            ->get(['id', 'name', 'company_id', 'expense_category_id']);

        return [
            'categories' => $categories->map(fn ($c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'company_id' => $c->company_id,
                'account_id' => $c->account_id,
            ])->values(),

            'subCategories' => $subCategories->map(fn ($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'category_id' => $s->expense_category_id,
                'company_id'  => $s->company_id,
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
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name', 'company_id'])
            ->map(fn (ExpenseDepartment $d) => [
                'id'         => $d->id,
                'name'       => $d->name,
                'company_id' => (int) $d->company_id,
            ]);
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
        $mapped = $subCategoryId
            ? ExpenseSubCategory::whereKey($subCategoryId)->value('account_id')
            : null;

        $mapped = $mapped ?: ($categoryId
            ? ExpenseCategory::whereKey($categoryId)->value('account_id')
            : null);

        if ($mapped) {
            return (int) $mapped;
        }

        return Account::where('code', config('accounts.general_expense'))->value('id');
    }
}
