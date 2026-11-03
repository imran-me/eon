<?php

namespace Modules\WoodArt\Modules\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bank;
use App\Models\PettyCashFloat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Accounts\Support\ExpensePoster;
use Modules\WoodArt\Modules\Accounts\Support\ProjectContext;
use Modules\WoodArt\Modules\Accounts\Support\SiteCosts;

/**
 * Wood Art · Bulk expense entry — many site costs in one go.
 *
 * ─── NO SECOND SET OF BOOKS ──────────────────────────────────────────────────
 * This screen does not post anything itself. Every row is filed and approved by
 * the ERP's own ExpenseController::store() and ::approve(), called one after the
 * other, so validation, cost lines, titles, the double-entry journal and every
 * guard (overdrawn cash / bank / float, company ownership of department and
 * category) are exactly what typing the same expense into Accounts → Expenses
 * would do. The rows land in that list, company 6, as ordinary expenses.
 *
 * ─── WHO PAID ────────────────────────────────────────────────────────────────
 * Each row names the person whose hand the money went through:
 *   · Own pocket       → reimburse_to_user_id: the company now OWES them (2200
 *                         Employee Payables, party = that person). Needs no cash.
 *   · Company advance  → petty_cash_float_id of that person, when they hold one:
 *                         the float gives what it has, anything beyond is booked
 *                         as owed to them (ExpenseController::settlementLines).
 * Their running balance is the ERP's own Petty Cash / Reimbursement desks.
 *
 * ─── ALL OR NOTHING ──────────────────────────────────────────────────────────
 * The whole batch runs in ONE transaction. One refused row (say a float that
 * cannot cover it) rolls everything back and says which row — never a half-
 * posted batch to untangle.
 *
 * ─── REVIEW BEFORE POSTING ───────────────────────────────────────────────────
 * Nothing posts until "Confirm & post" on the review page. The rows travel
 * between the two pages encrypted (tamper-proof) with a one-time token, so a
 * double click cannot post the batch twice.
 */
class ExpenseBulkController extends Controller
{
    /** Expenses are filed under company 6's books, whoever opens this screen. */
    private static function company(): int
    {
        return ProjectContext::id();
    }

    private const BLANK_ROWS = 1;

    /** Words in Excel names that say nothing about who the person is. */
    private const NOISE = ['vai', 'bhai', 'boss', 'mia', 'miah', 'sir', 'and', 'a', 'c'];

    /** Filing AND approving needs both; the screen does both in one step. */
    private function guard(): void
    {
        $u = auth()->user();

        abort_unless(
            $u && $u->can('view all expense') && $u->can('approve expense'),
            403,
            'Bulk entry files and approves expenses, so it needs both “view all expense” and “approve expense”.'
        );
    }

    /* ── step 1: the entry grid ───────────────────────────────────────────── */

    public function form(Request $request, string $role): View
    {
        $this->guard();

        $rows = old('rows');
        if (! is_array($rows)) {
            $n = max(1, min(60, (int) $request->query('rows', self::BLANK_ROWS)));
            $rows = collect(range(1, $n))->map(fn () => $this->blankRow())->all();
        }

        return $this->page('woodart::expense-bulk', $role, 'expenses', [
            'rows' => $rows,
            'paste' => old('paste', ''),
            'projectId' => old('department_id', $request->query('project')),
            'rowErrors' => session('wa_bulk_errors', []),
        ] + $this->lookups() + $this->recentProjectExpenses((int) old('department_id', $request->query('project'))));
    }

    /* ── step 2: validate, then show what will be posted ──────────────────── */

    public function review(Request $request, string $role)
    {
        $this->guard();

        $lookups = $this->lookups();
        $dept = $lookups['departments']->firstWhere('id', (int) $request->input('department_id'));

        // "Add 8 more rows": hand back the grid as typed, longer. Nothing is checked.
        if ($request->has('more')) {
            $typed = array_values(array_filter((array) $request->input('rows', []), 'is_array'));
            $blank = $this->blankRow();

            return redirect()
                ->route(ProjectContext::routeName('bulk'), ['role' => $role])
                ->withInput(['rows' => array_merge($typed, array_fill(0, 2, $blank)), 'department_id' => $request->input('department_id'), 'paste' => (string) $request->input('paste', '')]);
        }

        $incoming = collect($request->input('rows', []))
            ->filter(fn ($r) => is_array($r) && collect($r)->only(['description', 'amount', 'person_id', 'head_id'])->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty())
            ->values()->all();

        // Pasted rows are appended after the grid's own.
        $pasted = $this->parsePaste((string) $request->input('paste', ''), $lookups);
        $rows = array_merge($incoming, $pasted);

        $errors = [];
        if (! $dept) {
            $errors['_'][] = 'Choose the project these costs belong to.';
        }
        if (empty($rows)) {
            $errors['_'][] = 'Add at least one row, or paste rows from Excel.';
        }

        $clean = [];
        foreach ($rows as $i => $r) {
            [$row, $err] = $this->normaliseRow($r, $lookups);
            if ($err) {
                $errors[$i] = $err;
            }
            $rows[$i] = $r + ['source' => 'pocket'];
            if ($row['date']) {
                $rows[$i]['date'] = $row['date'];
            }
            $clean[] = $row;
        }

        if ($errors) {
            // Back to the grid with everything the person typed, plus the parsed
            // pasted rows now sitting in the grid where they can be corrected.
            return redirect()
                ->route(ProjectContext::routeName('bulk'), ['role' => $role])
                ->withInput(['rows' => array_values($rows), 'department_id' => $request->input('department_id'), 'paste' => ''])
                ->with('wa_bulk_errors', $errors);
        }

        $token = (string) Str::uuid();
        session(['wa_bulk_token' => $token]);

        $perPerson = collect($clean)->groupBy(fn ($row) => ! empty($row['bank_id']) ? 'bank-'.$row['bank_id'] : 'person-'.$row['person_id'])->map(fn ($g) => [
            'name' => $g->first()['person_name'], 'n' => $g->count(), 'total' => $g->sum(fn ($row) => $row['amount'] + $row['bank_charge_amount']),
        ])->values();

        $perHead = collect($clean)->groupBy('selection_id')->map(fn ($g) => [
            'name' => $g->first()['head_name'],
            'account' => $g->first()['account_label'],
            'n' => $g->count(),
            'total' => $g->sum('amount'),
        ])->sortByDesc('total')->values();

        $total = collect($clean)->sum('amount');
        $bankChargeTotal = collect($clean)->sum('bank_charge_amount');

        return $this->page('woodart::expense-bulk-review', $role, 'expenses', [
            'rows' => $clean,
            'dept' => $dept,
            'total' => $total,
            'bankChargeTotal' => $bankChargeTotal,
            'cashOutTotal' => $total + $bankChargeTotal,
            'perPerson' => $perPerson,
            'perHead' => $perHead,
            'payload' => Crypt::encryptString(json_encode(['dept' => (int) $dept->id, 'rows' => $clean])),
            'token' => $token,
        ]);
    }

    /** "Edit rows" on the review page: the same rows, back in the grid. */
    public function edit(Request $request, string $role)
    {
        $this->guard();

        try {
            $data = json_decode(Crypt::decryptString((string) $request->input('payload')), true);
        } catch (\Throwable $e) {
            return redirect()->route(ProjectContext::routeName('bulk'), ['role' => $role]);
        }

        $rows = collect($data['rows'] ?? [])->map(fn ($r) => [
            'date' => $r['date'] ? Carbon::parse($r['date'])->format('Y-m-d') : '',
            'person_id' => $r['person_id'],
            'bank_id' => $r['bank_id'] ?? '',
            'description' => $r['description'],
            'head_id' => $r['selection_id'] ?? $r['head_id'],
            'quantity' => $r['quantity'] ?? 1,
            'unit_price' => $r['unit_price'] ?? $r['amount'],
            'amount' => $r['amount'],
            'source' => ! empty($r['bank_id']) ? 'bank' : (! empty($r['float_id']) ? 'advance' : 'pocket'),
            'payment_method' => $r['payment_method'] ?? (! empty($r['bank_id']) ? 'bank_transfer' : 'cash'),
            'bank_charge_amount' => $r['bank_charge_amount'] ?? 0,
            'bank_charge_account_id' => $r['bank_charge_account_id'] ?? '',
            'supplier_key' => $r['supplier_key'] ?? '',
            'voucher_no' => $r['voucher_no'] ?? $this->nextVoucher(),
        ])->all();

        return redirect()
            ->route(ProjectContext::routeName('bulk'), ['role' => $role])
            ->withInput(['rows' => $rows, 'department_id' => $data['dept'] ?? '', 'paste' => '']);
    }

    /* ── step 3: post ─────────────────────────────────────────────────────── */

    public function store(Request $request, string $role)
    {
        $this->guard();

        // One-time: a second submit of the same review page finds no token.
        $expected = session()->pull('wa_bulk_token');
        if (! $expected || ! hash_equals($expected, (string) $request->input('token'))) {
            return redirect()->route(ProjectContext::routeName('bulk'), ['role' => $role])
                ->with('error', 'That batch was already posted (or the page expired). Check the project sheet before entering it again.');
        }

        try {
            $data = json_decode(Crypt::decryptString((string) $request->input('payload')), true);
        } catch (\Throwable $e) {
            abort(422, 'The batch could not be read back. Please enter it again.');
        }

        $rows = $data['rows'] ?? [];
        $dept = (int) ($data['dept'] ?? 0);
        abort_if(empty($rows) || ! SiteCosts::department($dept), 422, 'Nothing to post.');

        $stamp = now()->format('YmdHis');

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $r) {
                $n = $i + 1;

                ExpensePoster::post([
                    'department_id' => $dept,
                    'category_id' => $r['category_id'],
                    'head_id' => $r['head_id'],
                    'date' => $r['date'],
                    'person_id' => $r['person_id'],
                    'person_name' => $r['person_name'],
                    'description' => $r['description'],
                    'quantity' => $r['quantity'],
                    'unit_price' => $r['unit_price'],
                    'amount' => $r['amount'],
                    'float_id' => $r['float_id'] ?? null,
                    'bank_id' => $r['bank_id'] ?? null,
                    'payment_method' => $r['payment_method'] ?? 'cash',
                    'bank_charge_amount' => $r['bank_charge_amount'] ?? 0,
                    'bank_charge_account_id' => $r['bank_charge_account_id'] ?? null,
                    'supplier_name' => $r['supplier_name'],
                    'reference' => $r['voucher_no'] ?: 'EXP-'.$stamp.'-'.$n,
                ], $role);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            // Put the review page's token back so "Back" and a corrected retry work.
            return redirect()->route(ProjectContext::routeName('bulk'), ['role' => $role])
                ->with('error', 'Nothing was posted — row '.($n ?? '?').' was refused: '.$e->getMessage());
        }

        return redirect()->route(ProjectContext::routeName('project'), ['role' => $role, 'department' => $dept])
            ->with('success', count($rows).' expenses posted to the ledger.');
    }

    private function blankRow(): array
    {
        return [
            'date' => now()->toDateString(),
            'person_id' => '',
            'bank_id' => '',
            'description' => '',
            'head_id' => '',
            'quantity' => 1,
            'unit_price' => '',
            'amount' => '',
            'source' => 'pocket',
            'payment_method' => 'cash',
            'bank_charge_amount' => 0,
            'bank_charge_account_id' => '',
            'supplier_key' => '',
            'voucher_no' => $this->nextVoucher(),
        ];
    }

    private function nextVoucher(): string
    {
        $next = Schema::hasTable('expenses') ? ((int) DB::table('expenses')->max('id') + 1) : 1;

        return 'EXP-'.now()->format('Y').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /* ── helpers ──────────────────────────────────────────────────────────── */

    /** One grid/pasted row → ledger-ready row, or a list of what is wrong. */
    private function normaliseRow(array $r, array $lookups): array
    {
        $err = [];

        $date = $this->parseDate((string) ($r['date'] ?? ''));
        if (! $date) {
            $err[] = 'date not understood (use dd.mm.yyyy)';
        }

        $source = (string) ($r['source'] ?? 'pocket');
        $person = $lookups['people']->firstWhere('id', (int) ($r['person_id'] ?? 0));
        if ($source !== 'bank' && ! $person) {
            $err[] = 'choose who paid';
        }

        $bank = $lookups['banks']->firstWhere('id', (int) ($r['bank_id'] ?? 0));
        $paymentMethod = $source === 'bank' ? (string) ($r['payment_method'] ?? '') : 'cash';
        $allowedMethods = ['bank_transfer', 'mobile_banking', 'digital_wallet', 'other'];
        if ($source === 'bank' && ! auth()->user()->can('pay expense from bank')) {
            $err[] = 'you do not have permission to pay expenses from a bank';
        } elseif ($source === 'bank' && ! $bank) {
            $err[] = 'choose the company bank/cash account used to pay';
        } elseif ($source === 'bank' && ! in_array($paymentMethod, $allowedMethods, true)) {
            $err[] = 'choose a valid bank payment method';
        } elseif ($source === 'bank' && $paymentMethod !== 'other') {
            $requiredType = match ($paymentMethod) {
                'bank_transfer' => 'bank',
                'mobile_banking' => 'mobile_banking',
                'digital_wallet' => 'digital_wallet',
            };
            if ($bank && $bank->type !== $requiredType) {
                $err[] = 'the selected account does not match the payment method';
            }
        }

        $bankCharge = $source === 'bank'
            ? round((float) str_replace([',', '৳', ' '], '', (string) ($r['bank_charge_amount'] ?? '0')), 2)
            : 0.0;
        $bankChargeAccount = $lookups['bankChargeAccounts']->firstWhere('id', (int) ($r['bank_charge_account_id'] ?? 0));
        if ($bankCharge < 0) {
            $err[] = 'bank charge cannot be negative';
        } elseif ($bankCharge > 0 && ! $bankChargeAccount) {
            $err[] = 'choose the journal account where the bank charge posts';
        } elseif ($bankCharge > 0 && $bank && (int) $bank->account_id === (int) $bankChargeAccount?->id) {
            $err[] = 'bank charge cannot post to the selected bank account itself';
        }

        $head = $lookups['heads']->firstWhere('id', (int) ($r['head_id'] ?? 0));
        if (! $head) {
            $err[] = 'choose the cost head';
        }

        $amount = round((float) str_replace([',', '৳', ' '], '', (string) ($r['amount'] ?? '0')), 2);
        $quantity = round((float) str_replace(',', '', (string) ($r['quantity'] ?? '1')), 2);
        $unitPrice = round((float) str_replace(',', '', (string) ($r['unit_price'] ?? '0')), 2);
        if ($quantity <= 0) {
            $err[] = 'quantity must be above zero';
        }
        if ($unitPrice > 0) {
            $amount = round($quantity * $unitPrice, 2);
        }
        if ($amount <= 0) {
            $err[] = 'amount must be above zero';
        }

        $desc = trim((string) ($r['description'] ?? ''));
        if ($desc === '') {
            $err[] = 'description is empty';
        }

        $supplier = $lookups['suppliers']->firstWhere('key', (string) ($r['supplier_key'] ?? ''));
        if (! $supplier) {
            $err[] = 'choose the supplier / vendor';
        }

        $voucherNo = trim((string) ($r['voucher_no'] ?? ''));
        if ($voucherNo === '') {
            $voucherNo = $this->nextVoucher();
        }

        $floatId = null;
        if ($person && $source === 'advance') {
            $floatId = $lookups['floats'][$person->id] ?? null;
            if (! $floatId) {
                $err[] = $person->name.' holds no company advance (petty cash float) — pick “Own pocket”';
            }
        }

        $row = [
            'date' => $date?->toDateString(),
            'person_id' => $source === 'bank' ? null : $person?->id,
            'person_name' => $source === 'bank'
                ? trim(($bank?->name ?? '').' — '.($bank?->company?->short_name ?: ($bank?->company?->name ?? 'Group / Shared')))
                : $person?->name,
            'selection_id' => $head?->id,
            'head_id' => $head?->kind === 'category' ? null : $head?->id,
            'head_name' => $head?->name,
            'category_name' => $head?->category_name,
            'account_code' => $head?->account_code,
            'account_name' => $head?->account_name,
            'account_label' => $head ? '['.$head->account_code.'] '.$head->account_name : null,
            'category_id' => $head?->kind === 'category' ? $head?->category_id : $head?->expense_category_id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice > 0 ? $unitPrice : $amount,
            'amount' => $amount,
            'description' => $desc,
            'supplier_key' => $supplier?->key,
            'supplier_name' => $supplier?->name,
            'voucher_no' => Str::limit($voucherNo, 100, ''),
            'float_id' => $floatId,
            'bank_id' => $source === 'bank' ? $bank?->id : null,
            'payment_method' => $paymentMethod,
            'bank_charge_amount' => $bankCharge,
            'bank_charge_account_id' => $bankCharge > 0 ? $bankChargeAccount?->id : null,
            'bank_charge_account_label' => $bankCharge > 0 ? '['.$bankChargeAccount?->code.'] '.$bankChargeAccount?->name : null,
            'source' => $source === 'bank' ? 'Company bank · '.$bank?->name : ($floatId ? 'Company advance' : 'Own pocket'),
        ];

        return [$row, $err];
    }

    private function parseDate(string $s): ?Carbon
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }

        foreach (['d.m.Y', 'd-m-Y', 'd/m/Y', 'Y-m-d', 'd.m.y', 'd-m-y', 'd/m/y'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $s);
                if ($d && $d->format($fmt) === $s && $d->year >= 2020 && $d->year <= 2100) {
                    return $d->startOfDay();
                }
            } catch (\Throwable $e) {
                // try the next format
            }
        }

        return null;
    }

    /**
     * Pasted rows: one per line, tab-separated, in this column order —
     *   Date · Paid by · Description · Head · Amount
     * (what Excel puts on the clipboard). A header line is skipped. Names are
     * matched as far as they can be; anything unmatched is left blank for the
     * person to choose on the grid — never guessed.
     */
    private function parsePaste(string $text, array $lookups): array
    {
        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $c = array_map('trim', explode("\t", $line));
            if (count($c) < 5) {
                $c = array_pad($c, 5, '');
            }

            [$date, $who, $desc, $head, $amt] = $c;

            // Header row: the amount cell is not a number.
            if (! preg_match('/\d/', $amt) && $out === []) {
                continue;
            }

            $out[] = [
                'date' => $date,
                'person_id' => $this->matchPerson($who, $lookups['people']),
                'description' => $desc,
                'head_id' => $this->matchHead($head, $lookups['heads']),
                'amount' => $amt,
                'source' => 'pocket',
            ];
        }

        return $out;
    }

    private function words(string $s): array
    {
        $s = Str::lower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s));

        return array_values(array_filter(
            preg_split('/\s+/', $s),
            fn ($w) => $w !== '' && ! in_array($w, self::NOISE, true) && mb_strlen($w) >= 3
        ));
    }

    /** First significant word of the pasted name, if exactly one person has it. */
    private function matchPerson(string $name, $people): string
    {
        $w = $this->words($name);
        if (! $w) {
            return '';
        }

        $hits = $people->filter(fn ($p) => str_contains(Str::lower($p->name), $w[0]));

        return $hits->count() === 1 ? (string) $hits->first()->id : '';
    }

    private function matchHead(string $name, $heads): string
    {
        $n = Str::lower(preg_replace('/[^\p{L}\p{N}]+/u', '', $name));
        if ($n === '') {
            return '';
        }

        $exact = $heads->first(fn ($h) => Str::lower(preg_replace('/[^\p{L}\p{N}]+/u', '', $h->name)) === $n);
        if ($exact) {
            return (string) $exact->id;
        }

        $near = $heads->filter(function ($h) use ($n) {
            $hn = Str::lower(preg_replace('/[^\p{L}\p{N}]+/u', '', $h->name));

            return str_contains($hn, $n) || str_contains($n, $hn);
        });

        return $near->count() === 1 ? (string) $near->first()->id : '';
    }

    /** Everything the grid's drop-downs offer. */
    private function lookups(): array
    {
        $departments = DB::table('expense_departments')
            ->where('company_id', self::company())->whereNull('deleted_at')->where('status', 1)
            ->orderBy('name')->get(['id', 'name']);

        // Company 6's own heads, plus any group-wide one — the same set the
        // Expense form offers it.
        $heads = DB::table('expense_sub_categories as sub')
            ->join('expense_categories as cat', 'cat.id', '=', 'sub.expense_category_id')
            ->leftJoin('accounts as sub_account', 'sub_account.id', '=', 'sub.account_id')
            ->leftJoin('accounts as cat_account', 'cat_account.id', '=', 'cat.account_id')
            ->whereNull('sub.deleted_at')->where('sub.status', 1)
            ->whereNull('cat.deleted_at')->where('cat.status', 1)
            ->where(fn ($q) => $q->where('sub.company_id', self::company())->orWhereNull('sub.company_id'))
            ->where(fn ($q) => $q->where('cat.company_id', self::company())->orWhereNull('cat.company_id'))
            ->where(function ($q) {
                $q->where(function ($own) {
                    $own->whereNotNull('sub.account_id')
                        ->where('sub_account.type', 'expense')
                        ->where('sub_account.status', 1)
                        ->whereNull('sub_account.deleted_at');
                })->orWhere(function ($inherited) {
                    $inherited->whereNull('sub.account_id')
                        ->whereNotNull('cat.account_id')
                        ->where('cat_account.type', 'expense')
                        ->where('cat_account.status', 1)
                        ->whereNull('cat_account.deleted_at');
                });
            })
            ->orderBy('cat.name')->orderBy('sub.name')
            ->get([
                'sub.id', 'sub.name', 'sub.expense_category_id',
                'cat.name as category_name',
                DB::raw('COALESCE(sub_account.code, cat_account.code) as account_code'),
                DB::raw('COALESCE(sub_account.name, cat_account.name) as account_name'),
            ])->each(fn ($head) => $head->kind = 'sub_category');

        // A category is itself a valid cost head. Previously only its children
        // were offered, so a newly mapped category with no sub-category appeared
        // to have saved successfully but the expense dropdown stayed empty.
        $categoryHeads = DB::table('expense_categories as cat')
            ->join('accounts as account', 'account.id', '=', 'cat.account_id')
            ->whereNull('cat.deleted_at')->where('cat.status', 1)
            ->where(fn ($q) => $q->where('cat.company_id', self::company())->orWhereNull('cat.company_id'))
            ->where('account.type', 'expense')->where('account.status', 1)->whereNull('account.deleted_at')
            ->orderBy('cat.name')
            ->get([
                DB::raw('-cat.id as id'),
                'cat.id as category_id',
                'cat.name',
                'cat.name as category_name',
                'account.code as account_code',
                'account.name as account_name',
            ])->each(function ($head) {
                $head->kind = 'category';
                $head->expense_category_id = $head->category_id;
            });

        $heads = $categoryHeads->concat($heads)
            ->sortBy(fn ($head) => $head->category_name.'|'.($head->kind === 'category' ? '0' : '1').'|'.$head->name)
            ->values();

        // Staff, not parties: the people whose hands site money goes through.
        $people = User::query()
            ->where('status', 'active')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['agent', 'vendor', 'customer', 'trainer', 'consultant']))
            ->orderBy('name')->get(['id', 'name']);

        $floats = PettyCashFloat::query()
            ->where('company_id', self::company())->where('status', 1)
            ->pluck('id', 'custodian_id')->all();

        $banks = Bank::query()
            ->with('company:id,name,short_name')
            ->where('status', 1)
            ->whereNotNull('account_id')
            ->orderBy('name')
            ->get(['id', 'name', 'company_id', 'type', 'account_id', 'account_number']);

        $bankChargeAccounts = Account::query()
            ->where('type', 'expense')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderByRaw("CASE WHEN code = '8510' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $defaultBankChargeAccountId = optional($bankChargeAccounts->firstWhere('code', '8510'))->id;

        $suppliers = collect();
        if (Schema::hasTable('suppliers')) {
            $suppliers = DB::table('suppliers')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($supplier) => (object) [
                    'key' => 'supplier:'.$supplier->id,
                    'name' => $supplier->name,
                    'source' => 'Supplier',
                ]);
        }

        $vendors = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['vendor', 'supplier']))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($vendor) => (object) [
                'key' => 'vendor:'.$vendor->id,
                'name' => $vendor->name,
                'source' => 'Vendor',
            ]);

        $suppliers = $suppliers->concat($vendors)->unique(fn ($party) => Str::lower($party->name))->sortBy('name')->values();

        return compact('departments', 'heads', 'people', 'floats', 'banks', 'bankChargeAccounts', 'defaultBankChargeAccountId', 'suppliers');
    }

    /** Recent real entries for the selected project; context only, never copied into the form. */
    private function recentProjectExpenses(int $department): array
    {
        $project = $department > 0 ? SiteCosts::department($department) : null;
        if (! $project || ! Schema::hasTable('expenses')) {
            return ['entryProject' => $project, 'recentExpenses' => collect()];
        }

        $recentExpenses = DB::table('expenses')
            ->where('company_id', self::company())
            ->where('expense_department_id', $project->id)
            ->whereNull('deleted_at')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(4)
            ->get(['id', 'title', 'reference_user_name', 'amount', 'approval_status', 'expense_date']);

        return compact('recentExpenses') + ['entryProject' => $project];
    }

    private function page(string $view, string $role, string $section, array $data): View
    {
        return view($view, $data + [
            'dept' => $data['entryProject'] ?? null,
            'waModule' => 'accounts',
            'waRoute' => ProjectContext::routeName(),
            'waCompanyName' => ProjectContext::name(),
            'waIcon' => 'cash-stack',
            'section' => $section,
            'sections' => AccountsController::tabs(),
            'title' => 'Expense Entry (New)',
            'subtitle' => 'Project expense details, payment source and approval in one workspace.',
            'tabsDense' => true,
        ]);
    }
}
