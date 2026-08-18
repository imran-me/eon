<?php

namespace App\Http\Controllers\Api\Admin;

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

        DB::beginTransaction();
        try {
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

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Journal entry created successfully.',
                'data'   => $entry->load('items.account', 'createdBy'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update journal entry and replace items.
     */
    public function update(Request $request, $role, JournalEntry $journal)
    {
        // dd($request->all());
        $this->validateJournal($request);
        $this->assertBalanced($request->items);

        // Prevent editing auto-generated entries (non-manual sources)
        if ($journal->source !== 'manual') {
            return response()->json([
                'success' => false,
                'message' => 'Only manual journal entries can be edited here.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $journal->update([
                'date'        => $request->date,
                'reference'   => $request->reference,
                'source'      => $request->source,
                'description' => $request->description,
            ]);

            // Delete old items and recreate
            $journal->items()->delete();
            $this->syncItems($journal, $request->items);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Journal entry updated successfully.',
                'data'   => $journal->fresh('items.account', 'createdBy'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft-delete journal entry and cascade to items.
     */
    public function destroy($role, JournalEntry $journal)
    {
        if ($journal->source !== 'manual') {
            return response()->json([
                'success' => false,
                'message' => 'Only manual journal entries can be deleted.',
            ], 422);
        }

        $journal->items()->delete();
        $journal->delete();

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
}
