<?php

namespace App\Http\Controllers\Dashboard;

use App\Exports\PartyStatementExport;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Country;
use App\Models\PartyInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartyLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PartyStatementController extends Controller
{
    public function index(Request $request)
    {
        $totalReceivable = Transaction::where('user_type', 'customer')->sum('debit')
                         - Transaction::where('user_type', 'customer')->sum('credit');

        $activeLedgers = User::whereHas('transactions')->count();
        $banks         = Bank::where('status', 1)->orWhereNull('status')->get(['id', 'name', 'account_number']);
        $countries     = Country::orderBy('name')->get(['id', 'name']);
        $autoPartyId   = $request->integer('party_id') ?: null;

        return view('party-statement.index', compact('totalReceivable', 'activeLedgers', 'banks', 'countries', 'autoPartyId'));
    }

    public function search(Request $request)
    {
        $q    = $request->q;
        $type = $request->type ?? 'all';

        $query = User::query();

        match ($type) {
            'vendor' => $query->role('vendor'),
            'agent'  => $query->role('agent'),
            'client' => $query->role('customer'),
            default  => $query->role(['vendor', 'agent', 'customer']),
        };

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('id', $q)
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->limit(30)->get(['id', 'name', 'phone', 'email', 'contact_person'])
                  ->map(fn($p) => [
                      'id'             => $p->id,
                      'name'           => $p->name,
                      'phone'          => $p->phone,
                      'email'          => $p->email,
                      'contact_person' => $p->contact_person,
                      'roles'          => $p->getRoleNames(),
                      'initials'       => $this->initials($p->name),
                  ])
        );
    }

    public function load(Request $request)
    {
        $request->validate(['party_id' => 'required|exists:users,id']);

        $partyId = $request->party_id;
        $from    = $request->from;
        $to      = $request->to;
        $type    = $request->type;
        $party   = User::findOrFail($partyId);

        [$transactions, $openingBalance] = $this->fetchTransactions($partyId, $from, $to, $type);

        $totalDebit     = (float) $transactions->sum('debit');
        $totalCredit    = (float) $transactions->sum('credit');
        $closingBalance = $transactions->isNotEmpty()
                        ? (float) $transactions->last()->balance
                        : $openingBalance;

        return response()->json([
            'party'           => $this->partyData($party),
            'opening_balance' => $openingBalance,
            'total_debit'     => $totalDebit,
            'total_credit'    => $totalCredit,
            'closing_balance' => $closingBalance,
            'date_from'       => $from,
            'date_to'         => $to,
            'transactions'    => $transactions->map(fn($t) => [
                'id'             => $t->id,
                'payment_date'   => $t->payment_date,
                'reference_no'   => $t->reference_no,
                'type'           => $t->type,
                'user_type'      => $t->user_type,
                'payment_method' => $t->payment_method,
                'invoice_id'     => $t->invoice_id,
                'debit'          => (float) $t->debit,
                'credit'         => (float) $t->credit,
                'balance'        => (float) $t->balance,
                'remarks'        => $t->remarks,
            ]),
        ]);
    }

    public function recordPayment(Request $request)
    {
        $request->validate([
            'party_id'       => 'required|exists:users,id',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_date'   => 'required|date',
        ]);

        $bank = $request->bank_id ? Bank::find($request->bank_id) : null;
        if ($request->bank_id && (!$bank || !$bank->account_id)) {
            return response()->json([
                'success' => false,
                'message' => $bank ? "Bank '{$bank->name}' is not linked to a chart-of-accounts account." : 'Bank not found.',
            ]);
        }

        DB::transaction(function () use ($request, $bank) {
            $partyId = $request->party_id;
            $amount  = (float) $request->amount;

            $lastTx      = Transaction::where('user_id', $partyId)->orderByDesc('id')->lockForUpdate()->first();
            $oldBalance  = $lastTx ? (float) $lastTx->balance : 0;
            $newBalance  = $oldBalance - $amount;

            $refNo = 'RCT-' . date('Y') . '-' . str_pad(
                (Transaction::whereYear('created_at', date('Y'))->where('type', 'party_payment')->count() + 1),
                4, '0', STR_PAD_LEFT
            );

            $party = User::find($partyId);
            $userType = $this->resolveUserType($party);

            Transaction::create([
                'user_id'        => $partyId,
                'user_type'      => $userType,
                'type'           => 'party_payment',
                'account_id'     => $request->bank_id ?: null,
                'payment_date'   => $request->payment_date,
                'reference_no'   => $refNo,
                'payment_method' => $request->payment_method,
                'old_balance'    => $oldBalance,
                'debit'          => 0,
                'credit'         => $amount,
                'balance'        => $newBalance,
                'remarks'        => $request->notes ?: ('Payment received — ' . $request->payment_method),
            ]);

            // A customer's payment is cash received (clears our receivable); a
            // supplier's "payment" here is cash paid out (clears our payable).
            if ($bank) {
                $isCustomer    = $userType === 'customer';
                $contraCode    = $isCustomer ? config('accounts.accounts_receivable') : config('accounts.accounts_payable');
                $contraAccount = \App\Models\Account::where('code', $contraCode)->firstOrFail();

                $journal = \App\Models\JournalEntry::create([
                    'company_id'  => Auth::user()->company_id ?? 2,
                    'created_by'  => Auth::id(),
                    'date'        => $request->payment_date,
                    'reference'   => $refNo,
                    'source'      => 'party_payment',
                    'source_id'   => $partyId,
                    'description' => 'Party statement payment — ' . $refNo,
                ]);

                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $bank->account_id,
                    'debit'            => $isCustomer ? $amount : 0,
                    'credit'           => $isCustomer ? 0 : $amount,
                    'note'             => 'Party statement payment',
                ]);

                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $contraAccount->id,
                    'debit'            => $isCustomer ? 0 : $amount,
                    'credit'           => $isCustomer ? $amount : 0,
                    'note'             => 'Party statement payment',
                ]);

                $bank->update([
                    'balance'                 => $bank->balance + ($isCustomer ? $amount : -$amount),
                    'last_transaction_date'   => $request->payment_date,
                    'last_transaction_amount' => $amount,
                    'last_transaction_type'   => $isCustomer ? 'credit' : 'debit',
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Payment recorded successfully.']);
    }

    public function createInvoice(Request $request)
    {
        $request->validate([
            'party_id'            => 'required|exists:users,id',
            'amount'              => 'required|numeric|min:0.01',
            'service_description' => 'required|string',
        ]);

        DB::transaction(function () use ($request) {
            $partyId = $request->party_id;
            $amount  = (float) $request->amount;

            $lastTx     = Transaction::where('user_id', $partyId)->orderByDesc('id')->lockForUpdate()->first();
            $oldBalance = $lastTx ? (float) $lastTx->balance : 0;
            $newBalance = $oldBalance + $amount;

            $invNo = 'INV-' . date('Y') . '-' . str_pad(
                (PartyInvoice::whereYear('created_at', date('Y'))->count() + 1),
                4, '0', STR_PAD_LEFT
            );

            $invoice = PartyInvoice::create([
                'user_id'             => $partyId,
                'invoice_number'      => $invNo,
                'service_description' => $request->service_description,
                'country_id'          => $request->country_id ?: null,
                'no_of_candidates'    => $request->no_of_candidates ?: null,
                'amount'              => $amount,
                'due_date'            => $request->due_date ?: null,
                'notes'               => $request->notes ?: null,
                'created_by'          => Auth::id(),
            ]);

            $party    = User::find($partyId);
            $userType = $this->resolveUserType($party);

            Transaction::create([
                'user_id'        => $partyId,
                'user_type'      => $userType,
                'type'           => 'party_invoice',
                'account_id'     => null,
                'payment_date'   => now()->toDateString(),
                'reference_no'   => $invNo,
                'payment_method' => null,
                'old_balance'    => $oldBalance,
                'debit'          => $amount,
                'credit'         => 0,
                'balance'        => $newBalance,
                'remarks'        => $request->service_description,
                'invoice_id'     => $invoice->id,
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Invoice created successfully.']);
    }

    /**
     * Seed a party's ledger with a single starting balance so historical activity
     * doesn't need to be re-entered transaction by transaction. Every later
     * Record Payment / Invoice / sale already chains off this party's last
     * transaction, so once this is set everything else nets against it automatically.
     */
    public function setOpeningBalance(Request $request)
    {
        $request->validate([
            'party_id'   => 'required|exists:users,id',
            'amount'     => 'required|numeric|min:0.01',
            'direction'  => 'required|in:receivable,payable',
            'as_of_date' => 'required|date',
            'note'       => 'nullable|string|max:255',
        ]);

        $partyId = $request->party_id;

        if (Transaction::where('user_id', $partyId)->where('type', 'opening_balance')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'An opening balance has already been set for this party. Remove it first if you need to change it.',
            ]);
        }

        DB::transaction(function () use ($request, $partyId) {
            $amount       = (float) $request->amount;
            $isReceivable = $request->direction === 'receivable';
            $signedAmount = $isReceivable ? $amount : -$amount;

            $party    = User::find($partyId);
            $userType = $this->resolveUserType($party);

            Transaction::create([
                'user_id'        => $partyId,
                'user_type'      => $userType,
                'type'           => 'opening_balance',
                'account_id'     => null,
                'payment_date'   => $request->as_of_date,
                'reference_no'   => 'OB-' . now()->format('YmdHis'),
                'payment_method' => null,
                'old_balance'    => 0,
                'debit'          => $isReceivable ? $amount : 0,
                'credit'         => $isReceivable ? 0 : $amount,
                'balance'        => $signedAmount,
                'remarks'        => $request->note ?: 'Opening balance',
            ]);

            // Every transaction already on this party's ledger was computed
            // assuming a zero starting balance — shift them all by the same
            // amount now that we know the true starting point.
            Transaction::where('user_id', $partyId)
                ->where('type', '!=', 'opening_balance')
                ->increment('old_balance', $signedAmount);
            Transaction::where('user_id', $partyId)
                ->where('type', '!=', 'opening_balance')
                ->increment('balance', $signedAmount);
        });

        return response()->json(['success' => true, 'message' => 'Opening balance set successfully.']);
    }

    public function exportExcel(Request $request)
    {
        $request->validate(['party_id' => 'required|exists:users,id']);

        $partyId = $request->party_id;
        $party   = User::findOrFail($partyId);

        [$transactions, $openingBalance] = $this->fetchTransactions($partyId, $request->from, $request->to, $request->type);

        $totalDebit     = (float) $transactions->sum('debit');
        $totalCredit    = (float) $transactions->sum('credit');
        $closingBalance = $transactions->isNotEmpty() ? (float) $transactions->last()->balance : $openingBalance;

        $rows = $transactions->map(fn($t) => [
            'payment_date' => $t->payment_date,
            'reference_no' => $t->reference_no,
            'remarks'      => $t->remarks,
            'type'         => $t->type,
            'debit'        => (float) $t->debit,
            'credit'       => (float) $t->credit,
            'balance'      => (float) $t->balance,
        ])->toArray();

        $filename = 'statement-' . str($party->name)->slug() . '-' . date('Ymd') . '.xlsx';

        return Excel::download(
            new PartyStatementExport($rows, $party->name, $openingBalance, $totalDebit, $totalCredit, $closingBalance),
            $filename
        );
    }

    public function exportPdf(Request $request)
    {
        $request->validate(['party_id' => 'required|exists:users,id']);

        $partyId = $request->party_id;
        $party   = User::findOrFail($partyId);
        $company = Company::first();

        $dateFrom = $request->from;
        $dateTo   = $request->to;

        [$transactions, $openingBalance] = $this->fetchTransactions($partyId, $dateFrom, $dateTo, $request->type);

        $totalDebit     = (float) $transactions->sum('debit');
        $totalCredit    = (float) $transactions->sum('credit');
        $closingBalance = $transactions->isNotEmpty() ? (float) $transactions->last()->balance : $openingBalance;

        // Ticket-sale ledger rows are one row per invoice (the total); pull each
        // invoice's passenger/route breakdown up front so the statement can list
        // a sub-row per passenger without querying inside the Blade loop.
        $ticketSaleIds = $transactions->where('type', 'ticket_sale')->pluck('invoice_id')->filter()->unique();
        $ticketSaleItems = $ticketSaleIds->isEmpty() ? collect() : \App\Models\TicketSale::with([
                'items.ticketPurchase.passportHolder',
                'items.ticketPurchase.ticket.from_airport',
                'items.ticketPurchase.ticket.to_airport',
            ])->whereIn('id', $ticketSaleIds)->get()->keyBy('id');

        return view('party-statement.print-statement', compact(
            'party', 'company', 'transactions',
            'openingBalance', 'totalDebit', 'totalCredit', 'closingBalance',
            'dateFrom', 'dateTo', 'ticketSaleItems'
        ));
    }

    public function invoicePdf(string $_role, PartyInvoice $partyInvoice)
    {
        $partyInvoice->load('user', 'country', 'createdBy');
        $company = Company::first();

        return view('party-statement.pdf-invoice', compact('partyInvoice', 'company'));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * The party's rows for the window, with running balances recomputed from
     * debit/credit in date order — see PartyLedgerService for why the stored
     * `balance` column can't be trusted here. Each row's `balance` is the
     * correct closing figure as of that line, so callers can keep reading
     * `->last()->balance` for the window's closing balance.
     */
    private function fetchTransactions(int $partyId, ?string $from, ?string $to, ?string $type = null): array
    {
        [$transactions, $openingBalance] = app(PartyLedgerService::class)
            ->statement($partyId, $from, $to, $type);

        return [$transactions, $openingBalance];
    }

    private function partyData(User $party): array
    {
        return [
            'id'             => $party->id,
            'name'           => $party->name,
            'phone'          => $party->phone,
            'email'          => $party->email,
            'contact_person' => $party->contact_person,
            'roles'          => $party->getRoleNames(),
            'initials'       => $this->initials($party->name),
        ];
    }

    private function initials(string $name): string
    {
        $words = array_slice(explode(' ', $name), 0, 2);
        return strtoupper(implode('', array_map(fn($w) => $w[0] ?? '', $words)));
    }

    private function resolveUserType(User $party): string
    {
        if ($party->hasRole('vendor')) return 'supplier';
        return 'customer';
    }
}
