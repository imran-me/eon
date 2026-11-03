<?php

namespace Modules\WoodArt\Modules\Accounts\Support;

use App\Http\Controllers\ExpenseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Files and approves ONE expense through the ERP's own ExpenseController —
 * store() then approve() — exactly as the Expense desk's form does, so
 * validation, cost lines, the double-entry journal and every guard behave
 * identically. Shared by the bulk-entry screen and the Munshi Villa history
 * seeder so neither can drift from what typing it by hand would do.
 *
 * Runs inside the CALLER's transaction and never opens one of its own: the
 * caller decides what "all or nothing" means (a whole batch, a whole import).
 */
class ExpensePoster
{
    /**
     * @param  array  $r  department_id, category_id, head_id, date (Y-m-d), person_id,
     *                    person_name, description, amount, float_id?, bank_id?, reference
     * @param  bool  $approve  true (default): file AND approve — the journal is posted.
     *                         false: file only — the expense waits as PENDING with NO journal,
     *                         for the accountant to check and confirm with the Expense desk's
     *                         own Approve button (history that must not hit the books unchecked).
     * @return int the new expense id
     *
     * @throws \RuntimeException with the desk's own sentence when it refuses
     */
    public static function post(array $r, string $role = 'super-admin', bool $approve = true): int
    {
        $input = [
            'company_id' => SiteCosts::company(),
            'expense_department_id' => $r['department_id'],
            'expense_category_id' => $r['category_id'],
            'expense_sub_category_id' => $r['head_id'],
            'expense_date' => $r['date'],
            'transaction_time' => '12:00',
            'reference_user_name' => $r['supplier_name'] ?? null,
            'reference' => $r['reference'],
            'description' => $r['description'],
            'payment_mode' => ! empty($r['bank_id']) ? ($r['payment_method'] ?? 'bank_transfer') : 'cash',
            'bank_charge_amount' => $r['bank_charge_amount'] ?? 0,
            'bank_charge_account_id' => $r['bank_charge_account_id'] ?? null,
            'status' => 1,
            'items' => [[
                'description' => $r['description'],
                'quantity' => $r['quantity'] ?? 1,
                'unit_price' => $r['unit_price'] ?? $r['amount'],
                'amount' => $r['amount'],
            ]],
        ];

        if (! empty($r['bank_id'])) {
            $input['bank_id'] = $r['bank_id'];
        } elseif (! empty($r['float_id'])) {
            $input['petty_cash_float_id'] = $r['float_id'];
        } else {
            $input['reimburse_to_user_id'] = $r['person_id'];
        }

        $ctrl = app(ExpenseController::class);

        $filed = self::call($ctrl, 'store', $input);
        if (! ($filed['success'] ?? false)) {
            throw new \RuntimeException($filed['message'] ?? 'refused');
        }

        $id = $filed['data']['id'] ?? null;
        if (! $id) {
            throw new \RuntimeException('the expense was filed but its id did not come back');
        }

        if ($approve) {
            $approved = self::call($ctrl, 'approve', [], [$role, $id]);
            if (! ($approved['success'] ?? false)) {
                throw new \RuntimeException($approved['message'] ?? 'approval refused');
            }
        }

        return (int) $id;
    }

    /**
     * Approve ONE pending expense through the desk's own approve(): the journal is posted
     * now. This is the "Confirm" button — the same code the Expense desk's Approve runs.
     *
     * @throws \RuntimeException with the desk's own sentence when it refuses (an overdrawn
     *                           source, an inactive expense, another company's expense …)
     */
    public static function approve(int $expenseId, string $role = 'super-admin'): void
    {
        $out = self::call(app(ExpenseController::class), 'approve', [], [$role, $expenseId]);

        if (! ($out['success'] ?? false)) {
            throw new \RuntimeException($out['message'] ?? 'approval refused');
        }
    }

    /**
     * Reverse an APPROVED expense through the desk's own reverse(): an opposite journal is
     * added and the expense goes back to pending — the original journal stays.
     */
    public static function reverse(int $expenseId, string $date, string $role = 'super-admin'): void
    {
        $req = Request::create('/', 'POST', ['reversal_date' => $date]);
        $req->headers->set('X-Requested-With', 'XMLHttpRequest');
        $req->headers->set('Accept', 'application/json');
        $req->setUserResolver(fn () => auth()->user());

        $res = app(ExpenseController::class)->reverse($req, $role, $expenseId);
        $out = $res instanceof JsonResponse ? ($res->getData(true) ?: []) : [];

        if (! ($out['success'] ?? false)) {
            throw new \RuntimeException($out['message'] ?? 'reversal refused');
        }
    }

    /** One ExpenseController action as the form calls it: an ajax POST that answers JSON. */
    private static function call(ExpenseController $ctrl, string $action, array $input, array $routeArgs = []): array
    {
        $req = Request::create('/', 'POST', $input);
        $req->attributes->set('woodart_project_expense', true);
        $req->headers->set('X-Requested-With', 'XMLHttpRequest');
        $req->headers->set('Accept', 'application/json');
        $req->setUserResolver(fn () => auth()->user());

        $res = $action === 'store' ? $ctrl->store($req) : $ctrl->approve($req, ...$routeArgs);

        return $res instanceof JsonResponse
            ? ($res->getData(true) ?: [])
            : ['success' => false, 'message' => 'unexpected response from the expense desk'];
    }
}
