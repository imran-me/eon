<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BankOverdrawnException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JournalController extends Controller
{
    /**
     * Display a listing of journal entries with optional filters.
     */
    public function index(Request $request)
    {
        $query = JournalEntry::with(['items.account', 'createdBy']);            

        // Filter: date range
        if (Auth::user()->company_id) {
            $query->where('company_id', Auth::user()->company_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        // Filter: reference
        if ($request->filled('reference')) {
            $query->where('reference', 'like', '%' . $request->reference . '%');
        }

        // Filter: source
        if ($request->filled('source') && $request->source !== '') {
            $query->where('source', $request->source);
        }

        $journals    = $query->orderByDesc('date')->orderByDesc('id')->paginate(15)->withQueryString();
        $accounts    = Account::active()->orderBy('code')->get();
        $banks       = Bank::where('status', 1)->orderBy('name')->get();
        $sources     = ['manual', 'sale', 'purchase', 'expense', 'salary', 'loan', 'bank_transfer', 'ticket_sale', 'ticket_purchase'];

        return response()->json([
            'success' => true,
            'message' => 'Journal entries retrieved successfully.',
            'data' => [
                'journals' => $journals,
                'accounts' => $accounts,
                'sources' => $sources,
                'banks' => $banks,
            ]
        ]);
    }

    /**
     * Store a new journal entry with items.
     */
    public function store(Request $request)
    {
        // dd($request->all());
        $this->validateJournal($request);
        $this->assertBalanced($request->items);

        try {
            $entry = DB::transaction(function () use ($request) {
                $this->assertMoneyAccountReductions($request->items);

                $entry = JournalEntry::create([
                    'company_id'  => Auth::user()->company_id ?? 2,
                    'created_by'  => Auth::id(),
                    'date'        => $request->date,
                    'reference'   => $request->reference,
                    'source'      => $request->source,
                    'source_id'   => $request->source_id,
                    'description' => $request->description,
                ]);

                $this->syncItems($entry, $request->items);

                return $entry;
            });

            return response()->json([
                'success' => true,
                'message' => 'Journal entry created successfully.',
                'data'   => $entry->load('items.account', 'createdBy'),
            ]);
        } catch (BankOverdrawnException $e) {
            return $this->overdrawnResponse($e);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show(JournalEntry $journal)
    {
        return response()->json([
            'success' => true,
            'message' => 'Journal entry retrieved successfully.',
            'data' => $journal->load('items.account', 'createdBy'),
        ]);
    }

    /**
     * Update journal entry and replace items.
     */
    public function update(Request $request, JournalEntry $journal)
    {
        // dd($request->all());
        $this->validateJournal($request);
        $this->assertBalanced($request->items);

        try {
            $journal = DB::transaction(function () use ($request, $journal) {
                $journal = JournalEntry::query()
                    ->whereKey($journal->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($journal->source !== 'manual') {
                    abort(422, 'Only manual journal entries can be edited here.');
                }

                $oldItems = $journal->items()
                    ->get(['account_id', 'debit', 'credit'])
                    ->map(fn ($item) => $item->toArray())
                    ->all();

                // Check the net reduction versus the journal currently in the
                // ledger. Replacing an old cash debit can reduce cash just as a
                // new cash credit can, so both sides must be considered.
                $this->assertMoneyAccountReductions($request->items, $oldItems);

                $journal->update([
                    'date'        => $request->date,
                    'reference'   => $request->reference,
                    'source'      => $request->source,
                    'description' => $request->description,
                ]);

                $journal->items()->delete();
                $this->syncItems($journal, $request->items);

                return $journal;
            });

            return response()->json([
                'success' => true,
                'message' => 'Journal entry updated successfully.',
                'data'   => $journal->fresh('items.account', 'createdBy'),
            ]);
        } catch (BankOverdrawnException $e) {
            return $this->overdrawnResponse($e);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft-delete journal entry and cascade to items.
     */
    public function destroy(JournalEntry $journal)
    {
        try {
            DB::transaction(function () use ($journal) {
                $journal = JournalEntry::query()
                    ->whereKey($journal->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($journal->source !== 'manual') {
                    abort(422, 'Only manual journal entries can be deleted.');
                }

                $oldItems = $journal->items()
                    ->get(['account_id', 'debit', 'credit'])
                    ->map(fn ($item) => $item->toArray())
                    ->all();

                // Deleting a debit from a money account removes money from the
                // ledger. Refuse the delete if later spending already used it.
                $this->assertMoneyAccountReductions([], $oldItems);

                $journal->items()->delete();
                $journal->delete();
            });
        } catch (BankOverdrawnException $e) {
            return $this->overdrawnResponse($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Journal entry deleted successfully.',
        ]);
    }

    // ── Private Helpers ────────────────────────────────────

    private function validateJournal(Request $request): void
    {
        $request->validate([
            'date'                   => 'required|date',
            'reference'              => 'nullable|string|max:100',
            'source'                 => ['required', Rule::in(['manual', 'sale', 'purchase', 'expense', 'salary'])],
            'description'            => 'nullable|string|max:500',
            'items'                  => 'required|array|min:2',
            'items.*.account_id'     => 'required|exists:accounts,id',
            'items.*.debit'          => 'required|numeric|min:0',
            'items.*.credit'         => 'required|numeric|min:0',
            'items.*.note'           => 'nullable|string|max:255',
        ], [
            'items.required'         => 'At least 2 line items are required.',
            'items.min'              => 'At least 2 line items are required.',
            'items.*.account_id.required' => 'Account is required for every line item.',
            'items.*.account_id.exists'   => 'One or more selected accounts are invalid.',
        ]);

        // Each item must have debit OR credit (not both, not neither)
        foreach ($request->items as $i => $item) {
            $debit  = (float) ($item['debit']  ?? 0);
            $credit = (float) ($item['credit'] ?? 0);

            if ($debit > 0 && $credit > 0) {
                abort(422, "Line " . ($i + 1) . ": A line item cannot have both debit and credit.");
            }
            if ($debit == 0 && $credit == 0) {
                abort(422, "Line " . ($i + 1) . ": A line item must have either a debit or credit amount.");
            }
        }
    }

    private function assertBalanced(array $items): void
    {
        $totalDebit  = array_sum(array_column($items, 'debit'));
        $totalCredit = array_sum(array_column($items, 'credit'));

        if (abs($totalDebit - $totalCredit) >= 0.01) {
            abort(422, 'Journal entry is not balanced. Total debits (' . number_format($totalDebit, 2) .
                ') must equal total credits (' . number_format($totalCredit, 2) . ').');
        }
    }

    private function syncItems(JournalEntry $entry, array $items): void
    {
        
        foreach ($items as $item) {
            JournalItem::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $item['account_id'],
                'debit'            => (float)($item['debit']  ?? 0),
                'credit'           => (float)($item['credit'] ?? 0),
                'note'             => $item['note'] ?? null,
            ]);
        }
    }

    /**
     * Guard the amount by which a create/update/delete would reduce each cash,
     * bank or portal account. All checks happen inside the caller's transaction
     * and acquire account locks in a stable order.
     */
    private function assertMoneyAccountReductions(array $newItems, array $oldItems = []): void
    {
        $contributions = static function (array $items): array {
            $totals = [];

            foreach ($items as $item) {
                $accountId = (int) $item['account_id'];
                $totals[$accountId] = ($totals[$accountId] ?? 0)
                    + (float) ($item['debit'] ?? 0)
                    - (float) ($item['credit'] ?? 0);
            }

            return $totals;
        };

        $old = $contributions($oldItems);
        $new = $contributions($newItems);
        $accountIds = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($accountIds, SORT_NUMERIC);

        foreach ($accountIds as $accountId) {
            $reduction = round(($old[$accountId] ?? 0) - ($new[$accountId] ?? 0), 2);

            if ($reduction <= 0 || ! Bank::isMoneyHoldingAccount((int) $accountId)) {
                continue;
            }

            $accountName = Account::whereKey($accountId)->value('name') ?? 'Money account';
            Bank::assertCanCover((int) $accountId, $reduction, $accountName);
        }
    }

    private function overdrawnResponse(BankOverdrawnException $e)
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'errors' => ['amount' => [$e->getMessage()]],
        ], 422);
    }
}
