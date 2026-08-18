<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Account;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PaymentSchedule;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JournalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view journal|view all journal', only: ['index']),
            new Middleware('permission:create journal', only: ['store']),
            new Middleware('permission:edit journal', only: ['update']),
            new Middleware('permission:delete journal', only: ['destroy']),
        ];
    }

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

        // Column sorting. The key comes from the URL and is checked against this
        // list before it is used, so nothing from the request reaches the query
        // as SQL. Anything unrecognised falls through to the original order.
        //
        // 'balanced' is deliberately absent: it is derived by comparing the two
        // totals, not stored, so it has no column to sort on.
        // is_string before any cast: ?sort[]=date arrives as an array, and
        // casting that to string raises a PHP warning which Laravel promotes to
        // an ErrorException — a 500 before the whitelist is ever consulted.
        $sortRaw = $request->query('sort');
        $dirRaw  = $request->query('dir');
        $sortKey = is_string($sortRaw) ? $sortRaw : '';
        $sortDir = (is_string($dirRaw) && strtolower($dirRaw) === 'asc') ? 'asc' : 'desc';

        if (in_array($sortKey, ['date', 'reference', 'source', 'description', 'debit', 'credit', 'created_by'], true)) {
            if ($sortKey === 'debit' || $sortKey === 'credit') {
                // These columns show the total of the entry's lines, so the sort
                // has to be on that sum — journal_entries has no such column.
                $query->withSum('items as sort_total', $sortKey)->orderBy('sort_total', $sortDir);
            } elseif ($sortKey === 'created_by') {
                // A subquery rather than a join: journal_entries and users both
                // have an id, and a bare leftJoin would let users.id overwrite
                // the entry id in the selected row.
                $query->orderBy(
                    User::select('name')->whereColumn('users.id', 'journal_entries.created_by'),
                    $sortDir
                );
            } else {
                $query->orderBy($sortKey, $sortDir);
            }
        }

        $companies      = Company::where('status', 1)->orderBy('name')->get();
        // orderByDesc('id') stays in every case: on its own it is the original
        // default, and behind a sort it is the tiebreak that keeps paging stable
        // when several entries share a value.
        $journals       = $query->orderByDesc('id')->paginate(15)->withQueryString();
        $creditAccounts = Account::active()->whereIn('type', ['income', 'liability', 'equity'])->orderBy('code')->get();
        $debitAccounts  = Account::active()->whereIn('type', ['expense', 'asset'])->orderBy('code')->get();
        $assetAccounts  = Account::active()->where('type', 'asset')->orderBy('code')->get();
        $banks          = Bank::where('status', 1)->orderBy('name')->get();
        $sources        = ['manual', 'sale', 'purchase', 'expense', 'salary', 'loan', 'bank_transfer', 'ticket_sale', 'ticket_purchase', 'opening_receivable', 'opening_payable', 'opening_asset'];

        // Parties for the Opening Balance modals
        $partyCustomers = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']);
        $partySuppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']);
        $partyAgents    = User::whereHas('roles', fn($q) => $q->where('name', 'agent'))
                              ->where('status', 'active')->orderBy('name')->get(['id', 'name', 'phone']);
        $partyVendors   = User::whereHas('roles', fn($q) => $q->where('name', 'vendor'))
                              ->where('status', 'active')->orderBy('name')->get(['id', 'name', 'phone']);

        return view('journals.index', compact(
            'journals', 'creditAccounts', 'debitAccounts', 'assetAccounts', 'sources', 'banks', 'companies',
            'partyCustomers', 'partySuppliers', 'partyAgents', 'partyVendors'
        ));
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
                'company_id'  => $request->company_id,
                'created_by'  => Auth::id(),
                'date'        => $request->date,
                'reference'   => $request->reference,
                'source'      => $request->source,
                'source_id'   => $request->source_id,
                'description' => $request->description,
            ]);

            $this->syncItems($entry, $request->items);

            // Auto-create PaymentSchedule for opening balance rows that have a due_date
            if (in_array($request->source, ['opening_receivable', 'opening_payable'])) {
                $schedType = $request->source === 'opening_receivable' ? 'receive' : 'pay';
                foreach ($request->items as $i => $item) {
                    if (empty($item['due_date']) || empty($item['party_type']) || empty($item['party_id'])) {
                        continue;
                    }
                    $amount = $schedType === 'receive'
                        ? (float)($item['debit']  ?? 0)
                        : (float)($item['credit'] ?? 0);
                    if ($amount <= 0) continue;

                    PaymentSchedule::create([
                        'company_id'      => $request->company_id,
                        'schedulable_type' => 'journal_entry',
                        'schedulable_id'   => $entry->id,
                        'type'             => $schedType,
                        'party_type'       => $item['party_type'],
                        'party_id'         => (int)$item['party_id'],
                        'party_name'       => $item['party_name'] ?? null,
                        'source_label'     => $item['reference'] ?? null,
                        'amount'           => $amount,
                        'scheduled_date'   => $item['due_date'],
                        'status'           => 'pending',
                        'created_by'       => Auth::id(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Journal entry created successfully.',
                'entry'   => $entry->load('items.account', 'createdBy'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return a printable voucher for a single journal entry.
     */
    public function voucher($role, JournalEntry $journal)
    {
        $journal->load(['items.account', 'items.party', 'items.partyUser', 'createdBy', 'company']);
        $company = $journal->company ?? \App\Models\Company::first();
        return view('journals.voucher', compact('journal', 'company'));
    }

    /**
     * Return a party-specific printable voucher (customer / supplier / agent / vendor).
     */
    public function partyVoucher($role, JournalEntry $journal)
    {
        $journal->load(['items.account', 'items.party', 'items.partyUser', 'createdBy', 'company']);
        $company = $journal->company ?? \App\Models\Company::first();

        // Resolve party from the first item that has a party set
        $partyItem = $journal->items->first(fn($i) => $i->party_type && $i->party_id);

        $partyType    = $partyItem?->party_type ?? 'others';
        $partyName    = '—';
        $partyPhone   = null;
        $partyEmail   = null;
        $partyAddress = null;

        if ($partyItem) {
            if (in_array($partyType, ['customer', 'supplier'])) {
                $model        = $partyItem->party;
                $partyName    = $model?->name    ?? '—';
                $partyPhone   = $model?->phone   ?? null;
                $partyEmail   = $model?->email   ?? null;
                $partyAddress = $model?->address ?? null;
            } else {
                $model        = $partyItem->partyUser;
                $partyName    = $model?->name    ?? '—';
                $partyPhone   = $model?->phone   ?? null;
                $partyEmail   = $model?->email   ?? null;
            }
        }

        $voucherTitle = match($partyType) {
            'customer' => 'Customer Voucher',
            'supplier' => 'Supplier Voucher',
            'agent'    => 'Agent Voucher',
            'vendor'   => 'Vendor Voucher',
            default    => 'Party Voucher',
        };

        return view('journals.party-voucher', compact(
            'journal', 'company',
            'partyType', 'partyName', 'partyPhone', 'partyEmail', 'partyAddress',
            'voucherTitle'
        ));
    }

    /**
     * Return journal entry data (with items) for edit modal.
     */
    public function edit($role, JournalEntry $journal)
    {
        return response()->json($journal->load('items.account'));
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
                'entry'   => $journal->fresh('items.account', 'createdBy'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
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
            'source'                 => ['required', Rule::in(['manual', 'sale', 'purchase', 'expense', 'salary', 'opening_receivable', 'opening_payable', 'opening_asset'])],
            'description'            => 'nullable|string|max:500',
            'items'                  => 'required|array|min:2',
            'items.*.account_id'     => 'required|exists:accounts,id',
            'items.*.debit'          => 'required|numeric|min:0',
            'items.*.credit'         => 'required|numeric|min:0',
            'items.*.note'           => 'nullable|string|max:500',
            'items.*.party_type'     => ['nullable', Rule::in(['customer', 'supplier', 'agent', 'vendor'])],
            'items.*.party_id'       => 'nullable|integer|min:1',
            'items.*.party_name'     => 'nullable|string|max:200',
            'items.*.due_date'       => 'nullable|date',
            'items.*.reference'      => 'nullable|string|max:100',
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
                'party_type'       => $item['party_type'] ?? null,
                'party_id'         => isset($item['party_id']) ? (int)$item['party_id'] : null,
            ]);
        }
    }
}
