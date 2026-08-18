<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Bank;
use App\Models\Company;
use App\Models\ContractFile;
use App\Models\ContractFileSale;
use App\Models\ContractFileSaleItem;
use App\Models\Country;
use App\Models\InvoiceTemplate;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsSaleJournal;
use App\Traits\ResolvesPartyType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContractFileSaleController implements HasMiddleware
{
    use PostsPartyLedger, PostsSaleJournal, ResolvesPartyType;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract file sale|view all contract file sale', only: ['index', 'voucherPrint']),
            new Middleware('permission:create contract file sale', only: ['store']),
            new Middleware('permission:edit contract file sale', only: ['update']),
            new Middleware('permission:delete contract file sale', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = ContractFileSale::with(['client', 'country', 'items.file.country', 'items.file.category', 'items.file.passportHolder'])->latest('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('country', fn ($country) => $country->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items.file', function ($file) use ($search) {
                        $file->where('file_number', 'like', "%{$search}%")
                            ->orWhereHas('passportHolder', function ($holder) use ($search) {
                                $holder->where('name', 'like', "%{$search}%")
                                    ->orWhere('passport_no', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $datas = $query->paginate(20)->withQueryString();

        $mtd = ContractFileSale::whereYear('sale_date', now()->year)->whereMonth('sale_date', now()->month);
        $dueQuery = ContractFileSale::whereIn('payment_status', ['partial', 'due']);

        $stats = [
            'invoices_mtd' => (clone $mtd)->count(),
            'revenue' => ContractFileSale::sum('total_amount'),
            'due_amount' => $dueQuery->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as total_due')->value('total_due'),
            'due_count' => ContractFileSale::whereIn('payment_status', ['partial', 'due'])->count(),
            'net_profit' => ContractFileSale::selectRaw('COALESCE(SUM(total_amount - vendor_cost), 0) as net_profit')->value('net_profit'),
        ];

        $clients = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['vendor', 'agent']))
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);
        $customers = User::orderBy('name')->role('customer')->get(['id', 'name', 'phone']);
        $files = ContractFile::with(['country', 'category', 'vendor', 'passportHolder'])
            ->latest('id')
            ->get();
        $countries = Country::whereIn('id', $files->pluck('country_id')->filter()->unique())->orderBy('name')->get(['id', 'name']);

        $filesJson = $files->map(fn ($file) => [
            'id' => $file->id,
            'file_number' => $file->file_number,
            'applicant_name' => $file->applicant_name,
            'country_id' => $file->country_id,
            'country_name' => $file->country?->name,
            'category_name' => $file->category?->name,
            'visa_rate' => (float) $file->visa_rate,
            'vendor_id' => $file->vendor_id,
            'vendor_name' => $file->vendor?->name,
            'status' => $file->status_label,
        ]);

        $clientsJson = $clients->concat($customers)->map(fn ($client) => [
            'id' => $client->id,
            'name' => $client->name,
            'phone' => $client->phone,
        ]);

        $customersJson = $customers->map(fn ($customer) => [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ]);

        $salesJson = $datas->getCollection()->map(fn ($sale) => [
            'id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'client_id' => $sale->client_id,
            'client_name' => $sale->client?->name,
            'client_phone' => $sale->client?->phone,
            'country_id' => $sale->country_id,
            'country_name' => $sale->country?->name,
            'file_ids' => $sale->file_ids ?? [],
            'files_count' => $sale->files_count,
            'items' => $this->saleItemsForJson($sale),
            'sale_date' => optional($sale->sale_date)->toDateString(),
            'total_amount' => (float) $sale->total_amount,
            'paid_amount' => (float) $sale->paid_amount,
            'vendor_cost' => (float) $sale->vendor_cost,
            'due_amount' => (float) $sale->due_amount,
            'net_profit' => (float) $sale->net_profit,
            'payment_status' => $sale->payment_status,
            'notes' => $sale->notes,
        ]);

        $nextInvoiceNumber = ContractFileSale::nextInvoiceNumber();
        $banks = Bank::where('status', 1)->get();

        return view('contract-file-sales.index', compact('datas', 'stats', 'clients', 'clientsJson', 'customers', 'customersJson', 'files', 'filesJson', 'salesJson', 'countries', 'nextInvoiceNumber', 'banks'));
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            DB::transaction(function () use ($request) {
                $payload = $this->payload($request);
                $items = $payload['items'];
                unset($payload['items']);

                $sale = ContractFileSale::create($payload + [
                    'invoice_number' => $request->invoice_number ?: ContractFileSale::nextInvoiceNumber(),
                    'created_by' => Auth::id(),
                    'company_id' => Auth::user()->company_id ?? 2,
                ]);

                $this->syncItems($sale, $items);
                $this->reconcileFileSaleLedger($sale, null);
                $this->syncFileSaleJournal($sale, 'File sale — ' . $sale->invoice_number);
                $this->syncFileSaleReceivableSchedule($sale);
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Sale created successfully.']);
    }

    public function show($role, ContractFileSale $contractFileSale)
    {
        $contractFileSale->load(['client', 'country', 'items.file.country', 'items.file.category', 'items.file.passportHolder', 'items.file.vendor']);
        $files = $this->saleItemsForJson($contractFileSale);

        return response()->json([
            'id' => $contractFileSale->id,
            'invoice_number' => $contractFileSale->invoice_number,
            'client_name' => $contractFileSale->client?->name,
            'client_phone' => $contractFileSale->client?->phone,
            'country' => $contractFileSale->country?->name,
            'sale_date' => $contractFileSale->sale_date?->format('d M Y'),
            'total_amount' => $contractFileSale->total_amount,
            'paid_amount' => $contractFileSale->paid_amount,
            'due_amount' => $contractFileSale->due_amount,
            'vendor_cost' => $contractFileSale->vendor_cost,
            'net_profit' => $contractFileSale->net_profit,
            'payment_status' => $contractFileSale->payment_status,
            'notes' => $contractFileSale->notes,
            'files' => $files,
            'items' => $files,
        ]);
    }

    public function edit($role, ContractFileSale $contractFileSale)
    {
        $contractFileSale->load('items.file.passportHolder');

        return response()->json([
            'id' => $contractFileSale->id,
            'invoice_number' => $contractFileSale->invoice_number,
            'client_id' => $contractFileSale->client_id,
            'country_id' => $contractFileSale->country_id,
            'file_ids' => $contractFileSale->file_ids ?? [],
            'items' => $this->saleItemsForJson($contractFileSale),
            'sale_date' => $contractFileSale->sale_date?->format('Y-m-d'),
            'receivable_date' => $contractFileSale->receivable_date?->format('Y-m-d'),
            'total_amount' => $contractFileSale->total_amount,
            'paid_amount' => $contractFileSale->paid_amount,
            'vendor_cost' => $contractFileSale->vendor_cost,
            'payment_status' => $contractFileSale->payment_status,
            'payment_method' => $contractFileSale->payment_method,
            'bank_id' => $contractFileSale->bank_id,
            'notes' => $contractFileSale->notes,
        ]);
    }

    public function update(Request $request, $role, string $id)
    {
        $sale = ContractFileSale::find($id);
        if (!$sale) {
            return response()->json(['success' => false, 'message' => 'Data Info Not Found!']);
        }

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            DB::transaction(function () use ($request, $sale) {
                $oldClientId = $sale->client_id;
                $payload = $this->payload($request);
                $items = $payload['items'];
                unset($payload['items']);

                $sale->update($payload + ['invoice_number' => $request->invoice_number]);
                $this->syncItems($sale, $items);
                $this->reconcileFileSaleLedger($sale, $oldClientId);
                $this->syncFileSaleJournal($sale, 'File sale (edited) — ' . $sale->invoice_number);
                $this->syncFileSaleReceivableSchedule($sale);
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Sale updated successfully.']);
    }

    public function destroy(Request $request, $role, string $id)
    {
        try {
            DB::transaction(function () use ($request, $id) {
                $sale = ContractFileSale::find($request->item_id ?? $id);
                if (!$sale) {
                    throw new \RuntimeException('Data Info Not Found!');
                }
                $this->reversePartyLedger('file_sale', $sale->id);
                $this->deleteSaleJournal('file_sale', $sale->id);
                PaymentSchedule::where('schedulable_type', ContractFileSale::class)
                    ->where('schedulable_id', $sale->id)
                    ->delete();
                $sale->delete();
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Sale deleted successfully.']);
    }

    /**
     * Keep the sale's invoice/paid rows on the client's party-statement
     * ledger in sync. If the client changed since the last save, the old
     * client's rows are dropped and fresh ones posted against the new
     * client rather than cascade-repairing the old chain.
     */
    private function reconcileFileSaleLedger(ContractFileSale $sale, ?int $oldClientId): void
    {
        if ($oldClientId && $oldClientId != $sale->client_id) {
            $this->reversePartyLedger('file_sale', $sale->id);
        }

        if (!$sale->client_id) {
            return;
        }

        $common = [
            'payment_date' => now()->toDateString(),
            'reference_no' => $sale->invoice_number,
        ];

        $this->reconcilePartyBankBalance('file_sale', $sale->id, $sale->bank_id, (float) $sale->paid_amount);

        $this->reconcilePartyLedgerRow(
            $sale->client_id, 'file_sale', $sale->id, true, (float) $sale->total_amount,
            $common + ['remarks' => 'File sale invoice — ' . $sale->invoice_number]
        );

        $this->reconcilePartyLedgerRow(
            $sale->client_id, 'file_sale', $sale->id, false, (float) $sale->paid_amount,
            $common + [
                'remarks'        => 'File sale payment received — ' . $sale->invoice_number,
                'account_id'     => $sale->bank_id,
                'payment_method' => $sale->payment_method,
            ]
        );
    }

    /**
     * Keep the sale's chart-of-accounts journal entry in sync (creates it on
     * first save, replaces its items on every later save).
     */
    private function syncFileSaleJournal(ContractFileSale $sale, string $description): void
    {
        $this->updateSaleJournal(
            'file_sale',
            $sale->id,
            Auth::user()->company_id ?? 2,
            now(),
            $sale->invoice_number,
            $description,
            (float) $sale->total_amount,
            (float) $sale->paid_amount,
            (float) $sale->due_amount,
            $sale->bank_id,
            config('accounts.file_sales_revenue')
        );
    }

    /**
     * Keep the sale's receivable PaymentSchedule row in sync with its
     * current due amount, keyed to the real paying client.
     */
    private function syncFileSaleReceivableSchedule(ContractFileSale $sale): void
    {
        PaymentSchedule::where('schedulable_type', ContractFileSale::class)
            ->where('schedulable_id', $sale->id)
            ->delete();

        if ((float) $sale->due_amount <= 0) {
            return;
        }

        $sale->loadMissing('client');

        PaymentSchedule::create([
            'company_id'       => Auth::user()->company_id ?? null,
            'schedulable_type' => ContractFileSale::class,
            'schedulable_id'   => $sale->id,
            'type'             => 'receive',
            'party_type'       => $this->resolvePartyType($sale->client),
            'party_id'         => $sale->client_id,
            'party_name'       => $sale->client?->name,
            'source_label'     => $sale->invoice_number,
            'amount'           => (float) $sale->due_amount,
            'paid_amount'      => (float) $sale->paid_amount,
            'scheduled_date'   => $sale->receivable_date?->toDateString() ?? $sale->sale_date?->toDateString() ?? now()->toDateString(),
            'status'           => 'pending',
            'created_by'       => Auth::id(),
        ]);
    }

    public function voucherPrint($role, ContractFileSale $contractFileSale)
    {
        $contractFileSale->load(['client', 'country', 'items.file.country', 'items.file.category', 'items.file.passportHolder', 'items.file.vendor']);
        $items = $contractFileSale->items;

        $company = Company::find(Auth::user()->company_id)
            ?? Company::find(2)
            ?? Company::first();

        $invoiceTemplate = InvoiceTemplate::with('fields', 'style')
            ->where('type', 'contract_file_sale')->where('is_default', 1)->first()
            ?? InvoiceTemplate::with('fields', 'style')
                ->where('type', 'visa_sale')->where('is_default', 1)->first()
            ?? InvoiceTemplate::with('fields', 'style')->where('is_default', 1)->first();

        return view('contract-file-sales.voucher', [
            'sale' => $contractFileSale,
            'items' => $items,
            'files' => $items->pluck('file')->filter(),
            'company' => $company,
            'invoiceTemplate' => $invoiceTemplate,
        ]);
    }

    public function makePayment(Request $request, $role, ContractFileSale $contractFileSale)
    {
        $request->validate([
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:50',
            'bank_id'        => 'nullable|exists:banks,id',
        ]);

        $newPaid = min($contractFileSale->total_amount, $contractFileSale->paid_amount + (float) $request->payment_amount);
        $status = $newPaid >= $contractFileSale->total_amount ? 'paid' : 'partial';

        DB::transaction(function () use ($request, $contractFileSale, $newPaid, $status) {
            $contractFileSale->update([
                'paid_amount'    => $newPaid,
                'due_amount'     => max(0, (float) $contractFileSale->total_amount - $newPaid),
                'payment_status' => $status,
                'payment_method' => $request->payment_method ?? $contractFileSale->payment_method,
                'bank_id'        => $request->bank_id ?? $contractFileSale->bank_id,
            ]);
            $this->syncFileSaleReceivableSchedule($contractFileSale);

            if ($contractFileSale->client_id) {
                $this->reconcilePartyBankBalance('file_sale', $contractFileSale->id, $contractFileSale->bank_id, (float) $newPaid);

                $this->reconcilePartyLedgerRow(
                    $contractFileSale->client_id, 'file_sale', $contractFileSale->id, false, (float) $newPaid,
                    [
                        'payment_date'   => now()->toDateString(),
                        'reference_no'   => $contractFileSale->invoice_number,
                        'remarks'        => 'File sale payment received — ' . $contractFileSale->invoice_number,
                        'account_id'     => $contractFileSale->bank_id,
                        'payment_method' => $contractFileSale->payment_method,
                    ]
                );
            }

            if ($request->bank_id && $request->payment_amount > 0) {
                $this->postSaleJournalPayment(
                    'file_sale',
                    $contractFileSale->id,
                    Auth::user()->company_id ?? 2,
                    now(),
                    $contractFileSale->invoice_number,
                    'File sale payment received — ' . $contractFileSale->invoice_number,
                    (float) $request->payment_amount,
                    $request->bank_id
                );
            }
        });

        return back()->with('success', 'Payment recorded successfully.');
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'invoice_number' => 'nullable|string|max:50',
            'client_id' => 'required|exists:users,id',
            'country_id' => 'nullable|exists:countries,id',
            'file_ids' => 'required|array|min:1',
            'file_ids.*' => 'distinct|exists:contract_files,id',
            'item_sale_prices' => 'nullable|array',
            'item_sale_prices.*' => 'nullable|numeric|min:0',
            'item_vendor_costs' => 'nullable|array',
            'item_vendor_costs.*' => 'nullable|numeric|min:0',
            'sale_date' => 'required|date',
            'receivable_date' => 'nullable|date',
            'total_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'vendor_cost' => 'nullable|numeric|min:0',
            'payment_status' => 'required|in:paid,partial,due',
            'payment_method' => 'nullable|string|max:50',
            'bank_id' => 'nullable|exists:banks,id',
            'notes' => 'nullable|string',
        ]);
    }

    private function payload(Request $request): array
    {
        $fileIds = collect($request->file_ids ?? [])->filter()->unique()->values();
        $files = ContractFile::whereIn('id', $fileIds)->get()->keyBy('id');
        $items = $fileIds->map(function ($fileId) use ($request, $files) {
            $file = $files->get($fileId);
            $salePrice = data_get($request->input('item_sale_prices', []), $fileId, $file?->visa_rate ?? 0);
            $vendorCost = data_get($request->input('item_vendor_costs', []), $fileId, $file?->visa_rate ?? 0);

            return [
                'contract_file_id' => (int) $fileId,
                'sale_price' => (float) $salePrice,
                'vendor_cost' => (float) $vendorCost,
            ];
        })->values();

        $total = (float) $items->sum('sale_price');
        $vendorCost = (float) $items->sum('vendor_cost');
        $paid = min((float) ($request->paid_amount ?? 0), $total);
        $status = $paid >= $total && $total > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due');

        return [
            'client_id' => $request->client_id,
            'country_id' => $request->country_id ?: $files->pluck('country_id')->filter()->first(),
            'file_ids' => $fileIds->values()->all(),
            'files_count' => $fileIds->count(),
            'sale_date' => $request->sale_date,
            'receivable_date' => $request->receivable_date,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'due_amount' => max(0, $total - $paid),
            'vendor_cost' => $vendorCost,
            'payment_status' => $status,
            'payment_method' => $request->payment_method,
            'bank_id' => $request->bank_id,
            'notes' => $request->notes,
            'items' => $items->all(),
        ];
    }

    private function syncItems(ContractFileSale $sale, array $items): void
    {
        ContractFileSaleItem::where('contract_file_sale_id', $sale->id)->delete();

        foreach ($items as $item) {
            $sale->items()->create($item);
        }
    }

    private function saleItemsForJson(ContractFileSale $sale)
    {
        return $sale->items->map(function ($item) {
            $file = $item->file;

            return [
                'id' => $item->id,
                'contract_file_id' => $item->contract_file_id,
                'file_number' => $file?->file_number,
                'applicant' => $file?->applicant_name,
                'passport_no' => $file?->passport_no,
                'country' => $file?->country?->name,
                'category' => $file?->category?->name,
                'vendor' => $file?->vendor?->name,
                'sale_price' => (float) $item->sale_price,
                'vendor_cost' => (float) $item->vendor_cost,
                'status' => $file?->status_label,
            ];
        })->values();
    }
}
