<?php

namespace Modules\WoodArt\Modules\Accounts;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CompanyPartyDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\WoodArt\Modules\Accounts\Support\ExpensePoster;
use Modules\WoodArt\Modules\Accounts\Support\ProjectContext;
use Modules\WoodArt\Modules\Accounts\Support\SiteCosts;
use Modules\WoodArt\Modules\Accounts\Support\SitePosting;

/**
 * Wood Art · site statement forms — the contract value, the client's
 * installments, and what suppliers are owed, on a project's sheet — and the
 * "Post to books" actions that turn a row into a real journal (SitePosting).
 *
 * A row can sit as a STATEMENT entry (the owner's own record) or be POSTED. Once
 * posted it is never edited or deleted: it is REVERSED by an opposite journal.
 * Removing an unposted row is a soft delete, so what was once said is never lost.
 *
 * Same door as the cost entry: whoever may file and approve expenses. Anything
 * that moves money through a bank also needs "pay expense from bank".
 */
class SiteStatementController extends Controller
{
    private function guard(int $department): void
    {
        $u = auth()->user();

        abort_unless(
            $u && $u->can('view all expense') && $u->can('approve expense'),
            403,
            'Statement entries need both “view all expense” and “approve expense”.'
        );
        abort_unless(SiteCosts::department($department), 404);
        abort_unless(SiteCosts::statementReady(), 503, 'The statement tables are not installed yet.');
    }

    /** The cost entries are Expense-desk entries: confirming one needs the same rights as the desk's Approve. */
    private function guardCosts(int $department): void
    {
        $u = auth()->user();

        abort_unless(
            $u && $u->can('view all expense') && $u->can('approve expense'),
            403,
            'Confirming entries needs both “view all expense” and “approve expense”.'
        );
        abort_unless(SiteCosts::department($department), 404);
    }

    /** Posting through a bank is money moving: it needs the same permission as a bank-paid expense. */
    private function guardBank(array $opt, string $kind): void
    {
        $usesBank = $kind === 'supplier_payment' || ($kind === 'client_installment' && ($opt['counter'] ?? 'bank') !== 'intercompany');

        abort_if(
            $usesBank && ! auth()->user()->can('pay expense from bank'),
            403,
            'Posting through a bank account needs the “pay expense from bank” permission.'
        );
    }

    private function back(string $role, int $department, string $msg, bool $ok = true)
    {
        // A button on the summary page asks to come back to it (a whitelist, never a free URL).
        $back = (string) request()->input('back');
        $to = $back === 'summary' ? 'project.summary' : (preg_match('/^person-\d+$/', $back) ? 'project.person' : 'project');
        $params = ['role' => $role, 'department' => $department] + ($to === 'project.person' ? ['user' => (int) substr($back, 7)] : []);
        if ($to === 'project' && request()->input('workspace') === 'collections') {
            $params['view'] = 'collections';
        }

        return redirect()->route(ProjectContext::routeName($to), $params)
            ->with($ok ? 'success' : 'error', $msg);
    }

    public function terms(Request $request, string $role, int $department)
    {
        $this->guard($department);

        $v = $request->validate([
            'contract_value' => 'nullable|numeric|min:0|max:99999999999',
            'note' => 'nullable|string|max:500',
        ]);

        DB::table('project_terms')->updateOrInsert(
            ['expense_department_id' => $department],
            ['contract_value' => $v['contract_value'] ?? null, 'note' => $v['note'] ?? null,
                'created_at' => now(), 'updated_at' => now()]
        );

        return $this->back($role, $department, 'Contract value saved.');
    }

    public function setClient(Request $request, string $role, int $department)
    {
        $this->guard($department);
        $v = $request->validate(['client_id' => 'nullable|integer', 'client_name' => 'nullable|string|max:160', 'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:500',
            'contract_value' => 'sometimes|nullable|numeric|min:0|max:99999999999']);
        $client = DB::transaction(function () use ($v, $department, $request) {
            $client = null;
            if (! empty($v['client_id'])) {
                $client = User::role('customer')->find((int) $v['client_id']);
                abort_unless($client, 422, 'Choose a valid client.');
                DB::table('company_party')->updateOrInsert(
                    ['company_id' => ProjectContext::id(), 'user_id' => $client->id, 'party_type' => 'client'],
                    ['created_at' => now(), 'updated_at' => now()]
                );
                DB::table('project_party')->updateOrInsert(
                    ['expense_department_id' => $department, 'user_id' => $client->id, 'party_type' => 'client'],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            } elseif (trim((string) ($v['client_name'] ?? '')) !== '') {
                $client = CompanyPartyDirectory::resolve($v['client_name'], 'client', ProjectContext::id(), [
                    'phone' => $v['phone'] ?? null, 'email' => $v['email'] ?? null, 'address' => $v['address'] ?? null,
                ], $department);
            }
            $terms = ['client_party_id' => $client?->id, 'updated_at' => now()];
            if ($request->has('contract_value')) {
                $terms['contract_value'] = $v['contract_value'] ?? null;
            }
            DB::table('project_terms')->updateOrInsert(['expense_department_id' => $department], $terms);

            return $client;
        });

        return $this->back($role, $department, $client ? 'Client set: '.$client->name.'.' : 'Client cleared.');
    }

    public function store(Request $request, string $role, int $department)
    {
        $this->guard($department);

        $v = $request->validate([
            'kind' => 'required|in:client_installment,supplier_bill,supplier_payment',
            'party' => 'nullable|string|max:160|required_unless:kind,client_installment',
            'label' => 'nullable|string|max:160',
            'entry_date' => 'required|date',
            'amount' => 'required|numeric|gt:0|max:99999999999',
            'note' => 'nullable|string|max:500',
            'post_now' => 'nullable|boolean',
            'bank_id' => 'nullable|integer',
            'counter' => 'nullable|in:bank,intercompany',
            'cost_sub_category_id' => 'nullable|integer',
            'payable_kind' => 'nullable|in:supplier,contractor',
        ]);

        $opt = $request->only(['bank_id', 'counter', 'cost_sub_category_id', 'payable_kind']);
        $post = $request->boolean('post_now');
        if ($post) {
            $this->guardBank($opt, $v['kind']);
        }

        try {
            // The row and its posting stand or fall together: a refused posting leaves no row behind.
            DB::transaction(function () use ($v, $department, $post, $opt) {
                // A supplier typed on the form is the company's vendor of that name (found, or added) —
                // "Haji Enterprise." and "haji enterprise" are one vendor, not two.
                $vendor = $v['kind'] === 'client_installment' ? null
                    : CompanyPartyDirectory::resolve((string) $v['party'], 'vendor', ProjectContext::id(), [], $department);

                $id = DB::table('project_statement')->insertGetId([
                    'expense_department_id' => $department,
                    'kind' => $v['kind'],
                    'party' => $v['kind'] === 'client_installment' ? null : ($vendor->name ?? trim((string) $v['party'])),
                    'party_id' => $vendor->id ?? null,
                    'label' => $v['label'] ?? null,
                    'entry_date' => $v['entry_date'],
                    'amount' => round((float) $v['amount'], 2),
                    'note' => $v['note'] ?? null,
                    'created_by' => auth()->id(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                if ($post) {
                    SitePosting::post($id, $opt, (int) auth()->id());
                }
            });
        } catch (\RuntimeException $e) {
            return $this->back($role, $department, $e->getMessage(), false);
        }

        $what = match ($v['kind']) {
            'client_installment' => 'Installment',
            'supplier_bill' => 'Supplier due',
            default => 'Payment to supplier',
        };

        return $this->back($role, $department, $what.' recorded'.($post ? ' and posted to the books.' : ' in the statement.'));
    }

    /**
     * CONFIRM pending cost entries: each one goes through the Expense desk's own Approve, so
     * its journal is posted exactly as if the accountant had pressed Approve there.
     *
     *   only=ID       one entry (the button on its row)
     *   all=1         every pending entry of this project that the sheet's filters show
     *   ids[]         the ticked rows
     *
     * Each entry stands alone: one the desk refuses (an overdrawn account, say) is reported by
     * name and the rest still go through — an accountant confirming sixty entries should not
     * lose fifty-nine to one.
     */
    public function confirm(Request $request, string $role, int $department)
    {
        $this->guardCosts($department);

        if ($request->filled('only')) {
            $ids = [(int) $request->input('only')];
        } elseif ($request->boolean('all')) {
            $ids = SiteCosts::pendingIds(
                $department,
                $request->filled('from') ? (string) $request->input('from') : null,
                $request->filled('to') ? (string) $request->input('to') : null,
                trim((string) $request->input('q', ''))
            );
        } else {
            $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        }

        if (! $ids) {
            return $this->back($role, $department, 'Nothing to confirm — tick the entries, or use Confirm all pending.', false);
        }

        // Only THIS project's, THIS company's, still-pending entries can be confirmed from here.
        $valid = DB::table('expenses')->whereIn('id', $ids)
            ->where('company_id', SiteCosts::company())->where('expense_department_id', $department)
            ->where('approval_status', '<>', 'approved')->whereNull('deleted_at')
            ->orderBy('expense_date')->orderBy('id')->get(['id', 'reference', 'title']);

        $done = 0;
        $failed = [];
        foreach ($valid as $e) {
            try {
                ExpensePoster::approve((int) $e->id, $role);
                $done++;
            } catch (\Throwable $ex) {
                $failed[] = ($e->reference ?: ('#'.$e->id)).' — '.$ex->getMessage();
            }
        }

        $skipped = count($ids) - $valid->count();
        $msg = $done.' '.($done === 1 ? 'entry' : 'entries').' confirmed and posted to the books.'
            .($skipped > 0 ? " {$skipped} skipped (already confirmed, or not part of this project)." : '');

        if ($failed) {
            $msg .= ' '.count($failed).' refused: '.implode(' | ', array_slice($failed, 0, 4)).(count($failed) > 4 ? ' …' : '');
        }

        return $this->back($role, $department, $msg, $done > 0 || ! $failed);
    }

    /** Bill the client for the contract: Dr Receivable 1315 / Cr Contract Revenue 4410. */
    public function bill(Request $request, string $role, int $department)
    {
        $this->guard($department);

        $v = $request->validate([
            'amount' => 'nullable|numeric|gt:0|max:99999999999',
            'entry_date' => 'required|date',
            'label' => 'nullable|string|max:160',
        ]);

        $contract = (float) DB::table('project_terms')->where('expense_department_id', $department)->value('contract_value');
        $billed = SitePosting::clientBooks($department)['billed'];
        $amount = isset($v['amount']) ? (float) $v['amount'] : $contract - $billed;

        if ($contract <= 0) {
            return $this->back($role, $department, 'Set the contract value first.', false);
        }
        if ($amount <= 0 || $billed + $amount - $contract > 0.004) {
            return $this->back($role, $department, 'Already billed ৳ '.number_format($billed, 2).' of the ৳ '.number_format($contract, 2).' contract — nothing more to bill.', false);
        }

        try {
            DB::transaction(function () use ($v, $department, $amount) {
                $id = DB::table('project_statement')->insertGetId([
                    'expense_department_id' => $department, 'kind' => 'client_bill', 'party' => null,
                    'label' => $v['label'] ?? 'Contract billed', 'entry_date' => $v['entry_date'],
                    'amount' => round($amount, 2), 'created_by' => auth()->id(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                SitePosting::post($id, [], (int) auth()->id());
            });
        } catch (\RuntimeException $e) {
            return $this->back($role, $department, $e->getMessage(), false);
        }

        return $this->back($role, $department, 'Client billed ৳ '.number_format($amount, 2).' — posted to Receivable.');
    }

    /** Post an existing statement row to the books. */
    public function post(Request $request, string $role, int $department, int $line)
    {
        $this->guard($department);

        $row = DB::table('project_statement')->where('id', $line)->where('expense_department_id', $department)->whereNull('deleted_at')->first();
        abort_unless($row, 404);

        if (in_array($row->kind, ['supplier_bill', 'supplier_payment'], true) && str_starts_with((string) $row->ext_key, 'MV-L-')) {
            return $this->back($role, $department,
                'This is history from the supplier\'s own sheet. The books carry only his net balance — use "Post to the books" on the project summary page.', false);
        }

        if (in_array($row->kind, ['supplier_bill', 'supplier_payment'], true) && $row->party_id) {
            $coveringBalance = DB::table('project_statement')
                ->where('expense_department_id', $department)
                ->where('party_id', $row->party_id)
                ->where('kind', 'supplier_balance')
                ->whereNotNull('journal_entry_id')
                ->whereNull('reversal_journal_entry_id')
                ->whereNull('deleted_at')
                ->where('id', '>', $row->id)
                ->exists();

            if ($coveringBalance) {
                return $this->back($role, $department,
                    'This historical row is already included in the supplier net-balance journal. Posting it again would duplicate the books.', false);
            }
        }

        $request->validate([
            'bank_id' => 'nullable|integer',
            'counter' => 'nullable|in:bank,intercompany',
            'cost_sub_category_id' => 'nullable|integer',
            'payable_kind' => 'nullable|in:supplier,contractor',
        ]);
        $opt = $request->only(['bank_id', 'counter', 'cost_sub_category_id', 'payable_kind']);
        $this->guardBank($opt, $row->kind);

        try {
            SitePosting::post($line, $opt, (int) auth()->id());
        } catch (\RuntimeException $e) {
            return $this->back($role, $department, $e->getMessage(), false);
        }

        return $this->back($role, $department, 'Posted to the books.');
    }

    /** Undo a posting with an opposite journal; the original and the row stay. */
    public function reverse(Request $request, string $role, int $department, int $line)
    {
        $this->guard($department);

        $row = DB::table('project_statement')->where('id', $line)->where('expense_department_id', $department)->whereNull('deleted_at')->first();
        abort_unless($row, 404);

        $v = $request->validate(['reversal_date' => 'nullable|date']);

        try {
            SitePosting::reverse($line, $v['reversal_date'] ?? now()->toDateString(), (int) auth()->id());
        } catch (\RuntimeException $e) {
            return $this->back($role, $department, $e->getMessage(), false);
        }

        return $this->back($role, $department, 'Reversed. The books now carry both the original entry and its reversal.');
    }

    public function void(Request $request, string $role, int $department, int $line)
    {
        $this->guard($department);

        $row = DB::table('project_statement')
            ->where('id', $line)->where('expense_department_id', $department)->whereNull('deleted_at')->first();
        abort_unless($row, 404);

        // A posted entry is part of the books: reverse it, never hide it.
        if ($row->journal_entry_id && ! $row->reversal_journal_entry_id) {
            return $this->back($role, $department, 'That entry is in the books — reverse it instead of removing it.', false);
        }

        // Soft delete: the row and who made it stay on the table.
        DB::table('project_statement')->where('id', $line)->update(['deleted_at' => now(), 'updated_at' => now()]);

        return $this->back($role, $department, 'Entry removed from the statement.');
    }
}
