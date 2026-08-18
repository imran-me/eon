<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Sale;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InvoiceTemplate;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SaleController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view sales', only: ['index']),
            new Middleware('permission:create sales', only: ['create', 'store']),
            new Middleware('permission:edit sales', only: ['edit', 'update']),
            new Middleware('permission:delete sales', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $req_subdatas = [];
        $query = Sale::with(['customer', 'returns'])->select('sales.*')
            ->leftJoin('users', 'users.id', '=', 'sales.created_by')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->leftJoin('companies', 'companies.id', '=', 'sales.company_id')
            ->orderBy('sales.id', 'asc');

        if ($request->has('created_by') && !empty($request->created_by)) {
            $query->where('sales.created_by', $request->created_by);
        }

        if ($request->has('customer_id') && !empty($request->customer_id)) {
            $query->where('sales.customer_id', $request->customer_id);
        }

        if ($request->has('company_id') && !empty($request->company_id)) {
            $query->where('sales.company_id', $request->company_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sales.invoice_no', 'like', "%{$search}%");
            });
        }


        $datas = $query->paginate(20);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $customers = Customer::orderBy('name')->where('is_active', 1)->get();
        $companies = Company::orderBy('name')->where('status', 1)->get();

        return view('sales.index', compact(
            'datas',
            'users',
            'companies',
            'customers'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $lastInvoice = Sale::latest('id')->value('invoice_no');

        if ($lastInvoice) {
            $lastNumber = (int) str_replace('EPAL-', '', $lastInvoice);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        $nextInvoice = 'EPAL-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        $companyId = auth()->user()->company_id;
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $customers = Customer::orderBy('name')->where('is_active', 1)->get();
        $companies = Company::orderBy('name')->where('status', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();
        $banks = Bank::orderBy('name')->where('status', 1)->where('company_id', $companyId)->get();
        return view('sales.create', compact(
            'users',
            'products',
            'companies',
            'customers',
            'banks',
            'nextInvoice'
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sale_date' => 'required',
            'company_id' => 'nullable|exists:companies,id',
            'bank_id' => Rule::requiredIf(fn() => $request->paid_amount > 0),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        try {
            DB::transaction(function () use ($request) {

                // $paymentStatus = 'due';
                // if (!empty($request->paid_amount)) {                    
                //     $paymentStatus = ($request->total_amount == $request->paid_amount) ? 'paid' : 'partial';
                // }

                $lastInvoice = Sale::latest('id')->value('invoice_no');

                if ($lastInvoice) {
                    $lastNumber = (int) str_replace('INV-', '', $lastInvoice);
                    $nextNumber = $lastNumber + 1;
                } else {
                    $nextNumber = 1;
                }

                $invoiceNo = 'INV-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

                $data = Sale::create([
                    'customer_id' => $request->customer_id,
                    'company_id' => $request->company_id,
                    'bank_id' => $request->bank_id,
                    'invoice_no' => $invoiceNo,
                    'sale_date' => $request->sale_date ?? now(),
                    'total_amount' => $request->total_amount ?? 0,
                    'paid_amount' => $request->paid_amount ?? 0,
                    'due_amount' => $request->due_amount ?? 0,
                    'commission_amount' => $request->commission_amount ?? 0,
                    'payment_status' => $request->payment_status,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'created_by' => auth()->id()
                ]);

                // ── JOURNAL (auto) ───────────────────────────────────────
                $bank             = Bank::find($request->bank_id);
                $revenueAccount   = \App\Models\Account::where('code', config('accounts.sales_revenue'))->firstOrFail();
                $receivableAccount= \App\Models\Account::where('code', config('accounts.accounts_receivable'))->firstOrFail();

                $journal = \App\Models\JournalEntry::create([
                    'company_id'  => $request->company_id,
                    'created_by'  => auth()->id(),
                    'date'        => $data->sale_date,
                    'reference'   => $data->invoice_no,
                    'source'      => 'sale',
                    'source_id'   => $data->id,
                    'description' => 'Sale — ' . $data->invoice_no,
                ]);

                $journalItems = [];

                // Debit: Cash/Bank — only if payment received
                if ($data->paid_amount > 0 && $bank?->account_id) {
                    $journalItems[] = [
                        'account_id' => $bank->account_id,   // bank → linked account
                        'debit'      => $data->paid_amount,
                        'credit'     => 0,
                        'note'       => 'Cash received',
                    ];
                }

                // Debit: Accounts Receivable — only if due exists
                if ($data->due_amount > 0) {
                    $journalItems[] = [
                        'account_id' => $receivableAccount->id,
                        'debit'      => $data->due_amount,
                        'credit'     => 0,
                        'note'       => 'Amount due',
                    ];
                }

                // Credit: Sales Revenue — always, full total
                $journalItems[] = [
                    'account_id' => $revenueAccount->id,
                    'debit'      => 0,
                    'credit'     => $data->total_amount,
                    'note'       => 'Sales revenue',
                ];

                foreach ($journalItems as $item) {
                    \App\Models\JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
                }
                // ── END JOURNAL ───────────────────────────────────────────

                if ($request->bank_id && $request->paid_amount > 0) {
                    $acc = Bank::find($request->bank_id);
                    $opening_balance = $acc?->balance ?? 0;
                    Transaction::create([
                        'user_id' => $request->customer_id,
                        'user_type' => 'customer',
                        'type' => 'sale',
                        'account_id' => $request->bank_id,
                        'payment_date' => $request->sale_date ?? now(),
                        'reference_no' => $request->reference_no,
                        'payment_method' => $request->payment_method,
                        'invoice_id' => $data->id,
                        'old_balance' => $opening_balance,
                        'debit' => 0,
                        'credit' => $request->paid_amount ?? 0,
                        'balance' => $opening_balance + $request->paid_amount,
                        'note' => 'From sale creation',
                    ]);

                    if ($acc) {
                        $acc->update([
                            'balance' => $opening_balance + $request->paid_amount
                        ]);
                    }
                }

                if ($request->product_ids) {
                    foreach ($request->product_ids as $key => $product_id) {
                        $quantity = (int) ($request->quantity[$key] ?? 0);
                        $unit_price = (float) ($request->unit_price[$key] ?? 0);
                        $subtotal = (float) ($request->subtotal[$key] ?? 0);

                        SaleItem::updateOrCreate(
                            ['product_id' => $product_id, 'sale_id' => $data->id],
                            ['quantity' => $quantity, 'unit_price' => $unit_price, 'subtotal' => $subtotal]
                        );

                        $product = Product::with('stocks')->find($product_id);

                        $product->update([
                            'stock_qty' => $product->stock_qty - $quantity
                        ]);

                        $remainingQty = $quantity;
                        if ($request->branch_id) {
                            $branchStock = $product->stocks()->where('branch_id', $request->branch_id)->first();
                            if ($branchStock && $branchStock->available_qty > 0) {
                                $deduct = min($branchStock->available_qty, $remainingQty);
                                $branchStock->update(['available_qty' => $branchStock->available_qty - $deduct]);
                                $remainingQty -= $deduct;
                            }
                        }

                        if ($remainingQty > 0) {
                            $otherStocks = $product->stocks()
                                ->where('branch_id', '!=', $request->branch_id)
                                ->where('available_qty', '>', 0)
                                ->orderBy('available_qty', 'desc')
                                ->get();

                            foreach ($otherStocks as $stock) {
                                if ($remainingQty <= 0) break;

                                $deduct = min($stock->available_qty, $remainingQty);
                                $stock->update(['available_qty' => $stock->available_qty - $deduct]);
                                $remainingQty -= $deduct;
                            }
                        }

                        if ($remainingQty > 0) {
                            throw new \Exception("Insufficient stock for product SKU => {$product->sku}");
                        }
                    }
                }
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.'
        ]);
    }
    
    public function storeV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sale_date' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        try {
            DB::transaction(function () use($request) {

                // $paymentStatus = 'due';
                // if (!empty($request->paid_amount)) {
                //     $paymentStatus = ($request->total_amount == $request->paid_amount) ? 'paid' : 'partial';
                // }

                $lastInvoice = Sale::latest('id')->value('invoice_no');

                if ($lastInvoice) {
                    $lastNumber = (int) str_replace('EPAL-', '', $lastInvoice);
                    $nextNumber = $lastNumber + 1;
                } else {
                    $nextNumber = 1;
                }

                $invoiceNo = 'EPAL-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

                $data = Sale::create([
                    'customer_id' => $request->customer_id,
                    'company_id' => $request->company_id,
                    'bank_id' => $request->bank_id,
                    'invoice_no' => $invoiceNo,
                    'sale_date' => $request->sale_date ?? now(),
                    'total_amount' => $request->total_amount ?? 0,
                    'paid_amount' => $request->paid_amount ?? 0,
                    'due_amount' => $request->due_amount ?? 0,
                    'commission_amount' => $request->commission_amount ?? 0,
                    'payment_status' => $request->payment_status,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'created_by' => auth()->id()
                ]);

                if ($request->product_ids) {
                    foreach ($request->product_ids as $key => $product_id) {
                        $quantity = (int) ($request->quantity[$key] ?? 0);
                        $unit_price = (float) ($request->unit_price[$key] ?? 0);
                        $subtotal = (float) ($request->subtotal[$key] ?? 0);

                        SaleItem::updateOrCreate(
                            ['product_id' => $product_id, 'sale_id' => $data->id],
                            ['quantity' => $quantity, 'unit_price' => $unit_price, 'subtotal' => $subtotal]
                        );

                        $product = Product::with('stocks')->find($product_id);

                        $product->update([
                            'stock_qty' => $product->stock_qty - $quantity
                        ]);

                        $remainingQty = $quantity;
                        if ($request->branch_id) {
                            $branchStock = $product->stocks()->where('branch_id', $request->branch_id)->first();
                            if ($branchStock && $branchStock->available_qty > 0) {
                                $deduct = min($branchStock->available_qty, $remainingQty);
                                $branchStock->update(['available_qty' => $branchStock->available_qty - $deduct]);
                                $remainingQty -= $deduct;
                            }
                        }

                        if ($remainingQty > 0) {
                            $otherStocks = $product->stocks()
                                ->where('branch_id', '!=', $request->branch_id)
                                ->where('available_qty', '>', 0)
                                ->orderBy('id')
                                ->get();

                            foreach ($otherStocks as $stock) {
                                if ($remainingQty <= 0) break;

                                $deduct = min($stock->available_qty, $remainingQty);
                                $stock->update(['available_qty' => $stock->available_qty - $deduct]);
                                $remainingQty -= $deduct;
                            }
                        }

                        if ($remainingQty > 0) {
                            throw new \Exception("Insufficient stock for product SKU => {$product->sku}");
                        }
                    }
                }

            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.'
        ]);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($role,$id)
    {
        $sale = Sale::with('items', 'items.product')->find($id);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $customers = Customer::orderBy('name')->where('is_active', 1)->get();
        $companies = Company::orderBy('name')->where('status', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();
        $banks = Bank::orderBy('name')->where('status', 1)->get();

        return view('sales.edit', compact(
            'users',
            'products',
            'companies',
            'customers',
            'banks',
            'sale'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id = null)
    {
        $data = Sale::findOrFail($request->sale_id);
        if (!$data) {
            return request()->ajax()
                ? response()->json(['success' => false, 'message' => 'Data Info Not Found!'])
                : redirect()->back()->with('error', 'Data Info Not Found!');
        }
        if ($data->returns) {
            return request()->ajax()
                ? response()->json(['success' => false, 'message' => 'Sale can not edit anymore, return exists!'])
                : redirect()->back()->with('error', 'Sale can not edit anymore, return exists!');
        }
        if ($data->deleted_at) {
            return request()->ajax()
                ? response()->json(['success' => false, 'message' => 'Sale has been cancelled, cant edit anymore!'])
                : redirect()->back()->with('error', 'Sale has been cancelled, cant edit anymore!');
        }

        $validator = Validator::make($request->all(), [
            'sale_date' => 'required',
            'company_id' => 'nullable|exists:companies,id',
            'bank_id' => Rule::requiredIf(fn() => $request->paid_amount > 0),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        try {
            DB::transaction(function () use ($data, $request) {

                $newPaidTotal = $data->paid_amount + $request->paid_amount;
                $data->update([
                    'customer_id' => $request->customer_id,
                    'company_id' => $request->company_id,
                    'bank_id' => $request->bank_id,
                    'sale_date' => $request->sale_date,
                    'total_amount' => $request->total_amount ?? 0,
                    'paid_amount' => ($newPaidTotal > $request->total_amount) ? $request->total_amount : $newPaidTotal,
                    'due_amount' => $request->due_amount ?? 0,
                    'commission_amount' => $request->commission_amount ?? 0,
                    'payment_status' => $request->payment_status,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note
                ]);

                // ── JOURNAL UPDATE (auto) ─────────────────────────────────
                $revenueAccount    = \App\Models\Account::where('code', config('accounts.sales_revenue'))->firstOrFail();
                $receivableAccount = \App\Models\Account::where('code', config('accounts.accounts_receivable'))->firstOrFail();

                // Find existing journal entry for this sale
                $journal = \App\Models\JournalEntry::where('source', 'sale')
                                                ->where('source_id', $data->id)
                                                ->first();

                if ($journal) {
                    // Wipe old items and regenerate fresh
                    $journal->items()->delete();
                    $journal->update([
                        'date'        => $data->sale_date,
                        'description' => 'Sale (edited) — ' . $data->invoice_no,
                    ]);
                } else {
                    // Fallback: journal was missing, create it now
                    $journal = \App\Models\JournalEntry::create([
                        'company_id'  => $request->company_id,
                        'created_by'  => auth()->id(),
                        'date'        => $data->sale_date,
                        'reference'   => $data->invoice_no,
                        'source'      => 'sale',
                        'source_id'   => $data->id,
                        'description' => 'Sale (edited) — ' . $data->invoice_no,
                    ]);
                }

                $journalItems = [];

                // Debit: Cash/Bank — only if total paid_amount > 0
                if ($data->paid_amount > 0 && $data->bank_id) {
                    $bank = \App\Models\Bank::find($data->bank_id);
                    if (!$bank || !$bank->account_id) {
                        throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                    }
                    $journalItems[] = [
                        'account_id' => $bank->account_id,
                        'debit'      => $data->paid_amount,
                        'credit'     => 0,
                        'note'       => 'Cash received',
                    ];
                }

                // Debit: Accounts Receivable — only if due exists
                if ($data->due_amount > 0) {
                    $journalItems[] = [
                        'account_id' => $receivableAccount->id,
                        'debit'      => $data->due_amount,
                        'credit'     => 0,
                        'note'       => 'Amount due',
                    ];
                }

                // Credit: Sales Revenue — always, full total
                $journalItems[] = [
                    'account_id' => $revenueAccount->id,
                    'debit'      => 0,
                    'credit'     => $data->total_amount,
                    'note'       => 'Sales revenue',
                ];

                foreach ($journalItems as $item) {
                    \App\Models\JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
                }
                // ── END JOURNAL ───────────────────────────────────────────

                if ($request->bank_id && $request->paid_amount > 0) {
                    $acc = Bank::find($request->bank_id);
                    $opening_balance = $acc?->balance ?? 0;
                    Transaction::create([
                        'user_id' => $request->customer_id,
                        'user_type' => 'customer',
                        'type' => 'sale',
                        'account_id' => $request->bank_id,
                        'payment_date' => now(),
                        'reference_no' => $request->reference_no,
                        'payment_method' => $request->payment_method,
                        'invoice_id' => $data->id,
                        'old_balance' => $opening_balance,
                        'debit' => 0,
                        'credit' => $request->paid_amount ?? 0,
                        'balance' => $opening_balance + $request->paid_amount,
                        'note' => 'From sale edit',
                    ]);
                    if ($acc) {
                        $acc->update([
                            'balance' => $opening_balance + $request->paid_amount
                        ]);
                    }
                }

                $existingItems = $data->sale_items->keyBy('product_id');
                $incomingItemIds = $request->product_ids ?? [];

                // Handle removed items & restore stock
                $removedIds = array_diff($existingItems->keys()->toArray(), $incomingItemIds);
                foreach ($removedIds as $productId) {
                    $item = $existingItems[$productId];

                    // restore stock for product
                    $product = Product::with('stocks')->find($productId);
                    $product->increment('stock_qty', $item->quantity);

                    if ($data->branch_id) {
                        $branchStock = $product->stocks()->where('branch_id', $data->branch_id)->first();
                        if ($branchStock) {
                            $branchStock->increment('available_qty', $item->quantity);
                        }
                    }

                    // delete sale item
                    $item->delete();
                }

                // Update or create incoming items and adjust stock
                foreach ($incomingItemIds as $key => $productId) {
                    $quantity = (int) ($request->quantity[$key] ?? 0);
                    $unitPrice = (float) ($request->unit_price[$key] ?? 0);
                    $subtotal = (float) ($request->subtotal[$key] ?? 0);

                    $saleItem = SaleItem::updateOrCreate(
                        ['sale_id' => $data->id, 'product_id' => $productId],
                        ['quantity' => $quantity, 'unit_price' => $unitPrice, 'subtotal' => $subtotal]
                    );

                    $product = Product::with('stocks')->find($productId);

                    // Calculate old quantity for stock adjustment
                    $oldQuantity = $existingItems[$productId]->quantity ?? 0;
                    $qtyDiff = $quantity - $oldQuantity;

                    if ($qtyDiff != 0) {
                        // --- LOG: Start Tracking ---
                        // Log::info("Stock Adjustment Started", [
                        //     'sku' => $product->sku,
                        //     'qty_diff' => $qtyDiff,
                        //     'current_main_stock' => $product->stock_qty
                        // ]);

                        // 1. Update main stock
                        if (($product->stock_qty - $qtyDiff) < 0) {
                            // Log::warning("Stock Out: Main Inventory", [
                            //     'sku' => $product->sku,
                            //     'available' => $product->stock_qty,
                            //     'attempted_deduction' => $qtyDiff
                            // ]);
                            throw new \Exception("Insufficient stock for product SKU: {$product->sku} Shortfall: {$remainingQty}");
                        }

                        $product->stock_qty -= $qtyDiff;
                        $product->save();
   
                        $remainingQty = $qtyDiff;

                        // adjust branch stock
                        if ($request->branch_id) {
                            $branchStock = $product->stocks()->where('branch_id', $request->branch_id)->first();
                            if ($branchStock) {
                                $deduct = min($branchStock->available_qty, $remainingQty);
                                $branchStock->available_qty -= $deduct;
                                $branchStock->save();
                                $remainingQty -= $deduct;

                                // Log::info("Deducted from primary branch", [
                                //     'branch_id' => $request->branch_id,
                                //     'deducted' => $deduct,
                                //     'remaining_to_find' => $remainingQty
                                // ]);
                            }
                            // adjust other branches if needed
                            if ($remainingQty > 0) {
                                $otherStocks = $product->stocks()
                                    ->where('branch_id', '!=', $request->branch_id)
                                    ->where('available_qty', '>', 0)
                                    ->orderBy('available_qty', 'desc')
                                    ->get();

                                foreach ($otherStocks as $stock) {
                                    if ($remainingQty <= 0) break;
                                    $deduct = min($stock->available_qty, $remainingQty);
                                    $stock->available_qty -= $deduct;
                                    $stock->save();
                                    $remainingQty -= $deduct;

                                    // Log::info("Deducted from fallback branch", [
                                    //     'branch_id' => $stock->branch_id,
                                    //     'deducted' => $deduct,
                                    //     'remaining_to_find' => $remainingQty
                                    // ]);
                                }
                            }
                        }


                        if ($remainingQty > 0) {
                            // Log::error("Stock Out: Branch Inventory Exhausted", [
                            //     'sku' => $product->sku,
                            //     'unfulfilled_qty' => $remainingQty,
                            //     'total_requested_diff' => $qtyDiff
                            // ]);
                            throw new \Exception("Insufficient stock for product SKU => {$product->sku} Shortfall: {$remainingQty}");
                        }
                    }
                }
            });
        } catch (\Throwable $th) {
            return request()->ajax()
                ? response()->json(['success' => false, 'message' => $th->getMessage()])
                : redirect()->back()->with('error', $th->getMessage());
        }

        return request()->ajax()
            ? response()->json(['success' => true, 'message' => 'Data updated successfully.'])
            : redirect()->back()->with('success', 'Data updated successfully.');
    }


    public function updateV2(Request $request, $id=null)
    {
        $data = Sale::findOrFail($request->sale_id);
        if (!$data) {
            return request()->ajax()
                ? response()->json(['success' => false, 'message' => 'Data Info Not Found!'])
                : redirect()->back()->with('error', 'Data Info Not Found!');
        }

        $validated = $request->validate([
            'sale_date' => 'required',
        ]);

        try {
            DB::transaction(function () use ($data, $request) {

                // Update payment status
                // $paymentStatus = !empty($request->paid_amount)
                //     ? ($request->total_amount == $request->paid_amount ? 'paid' : 'partial')
                //     : 'due';

                $data->update([
                    'customer_id' => $request->customer_id,
                    'company_id' => $request->company_id,
                    'bank_id' => $request->bank_id,
                    'sale_date' => $request->sale_date,
                    'total_amount' => $request->total_amount ?? 0,
                    'paid_amount' => $request->paid_amount ?? 0,
                    'due_amount' => $request->due_amount ?? 0,
                    'commission_amount' => $request->commission_amount ?? 0,
                    'payment_status' => $request->payment_status,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note
                ]);

                $existingItems = $data->sale_items->keyBy('product_id');
                $incomingItemIds = $request->product_ids ?? [];

                // Handle removed items & restore stock
                $removedIds = array_diff($existingItems->keys()->toArray(), $incomingItemIds);
                foreach ($removedIds as $productId) {
                    $item = $existingItems[$productId];

                    // restore stock for product
                    $product = Product::with('stocks')->find($productId);
                    $product->increment('stock_qty', $item->quantity);

                    if ($data->branch_id) {
                        $branchStock = $product->stocks()->where('branch_id', $data->branch_id)->first();
                        if ($branchStock) {
                            $branchStock->increment('available_qty', $item->quantity);
                        }
                    }

                    // delete sale item
                    $item->delete();
                }

                // Update or create incoming items and adjust stock
                foreach ($incomingItemIds as $key => $productId) {
                    $quantity = (int) ($request->quantity[$key] ?? 0);
                    $unitPrice = (float) ($request->unit_price[$key] ?? 0);
                    $subtotal = (float) ($request->subtotal[$key] ?? 0);

                    $saleItem = SaleItem::updateOrCreate(
                        ['sale_id' => $data->id, 'product_id' => $productId],
                        ['quantity' => $quantity, 'unit_price' => $unitPrice, 'subtotal' => $subtotal]
                    );

                    $product = Product::with('stocks')->find($productId);

                    // Calculate old quantity for stock adjustment
                    $oldQuantity = $existingItems[$productId]->quantity ?? 0;
                    $qtyDiff = $quantity - $oldQuantity;

                    if ($qtyDiff != 0) {
                        // update main stock
                        $product->stock_qty -= $qtyDiff;
                        if ($product->stock_qty < 0) {
                            throw new \Exception("Insufficient stock for product SKU: {$product->sku}");
                        }
                        $product->save();

                        $remainingQty = $qtyDiff;

                        // adjust branch stock
                        if ($request->branch_id) {
                            $branchStock = $product->stocks()->where('branch_id', $request->branch_id)->first();
                            if ($branchStock) {
                                $deduct = min($branchStock->available_qty, $remainingQty);
                                $branchStock->available_qty -= $deduct;
                                $branchStock->save();
                                $remainingQty -= $deduct;
                            }
                            // adjust other branches if needed
                            if ($remainingQty > 0) {
                                $otherStocks = $product->stocks()
                                    ->where('branch_id', '!=', $request->branch_id)
                                    ->where('available_qty', '>', 0)
                                    ->orderBy('id')
                                    ->get();

                                foreach ($otherStocks as $stock) {
                                    if ($remainingQty <= 0) break;
                                    $deduct = min($stock->available_qty, $remainingQty);
                                    $stock->available_qty -= $deduct;
                                    $stock->save();
                                    $remainingQty -= $deduct;
                                }
                            }
                        }


                        if ($remainingQty <= 0) {
                            throw new \Exception("Insufficient stock for product SKU => {$product->sku}");
                        }
                    }
                }
            });
        } catch (\Throwable $th) {
            return request()->ajax()
                ? response()->json(['success' => false, 'message' => $th->getMessage()])
                : redirect()->back()->with('error', $th->getMessage());
        }

        return request()->ajax()
            ? response()->json(['success' => true, 'message' => 'Data updated successfully.'])
            : redirect()->back()->with('success', 'Data updated successfully.');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = Sale::with([
                'sale_items.product.stocks',
                'returns.items',
                'transactions'
            ])->find($request->item_id);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }
            if ($item->returns->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Return already exists for this sale. Cannot proceed!'
                ]);
            }

            DB::transaction(function () use ($item) {
                
                // remove transactions
                foreach ($item->transactions as $transaction) {
                    $bank = Bank::find($transaction->account_id);
                    if ($bank && $transaction->credit > 0) {
                        $bank->decrement('balance', (float) $transaction->credit);
                    }
                    $transaction->delete();
                }

                // ── JOURNAL CLEANUP ───────────────────────────────────────
                $journal = \App\Models\JournalEntry::where('source', 'sale')
                                                ->where('source_id', $item->id)
                                                ->first();
                if ($journal) {
                    $journal->items()->forceDelete();
                    $journal->forceDelete();
                }
                // ── END JOURNAL ───────────────────────────────────────────

                // Loop through sale items to restore stock
                foreach ($item->items as $saleItem) {
                    $product = $saleItem->product;

                    // Restore total product stock
                    $product->update([
                        'stock_qty' => $product->stock_qty + $saleItem->quantity
                    ]);

                    $remainingQty = $saleItem->quantity;

                    // Try to restore stock to the original branch first (if branch_id is stored in SaleItem)
                    if ($saleItem->branch_id) {
                        $branchStock = $product->stocks()->where('branch_id', $saleItem->branch_id)->first();
                        if ($branchStock) {
                            $restock = min($remainingQty, $saleItem->quantity); // can also check max capacity if needed
                            $branchStock->update([
                                'available_qty' => $branchStock->available_qty + $restock
                            ]);
                            $remainingQty -= $restock;
                        }
                    }

                    // If any remaining, restore sequentially to other branches
                    if ($remainingQty > 0) {
                        $otherStocks = $product->stocks()
                            ->where('branch_id', '!=', $saleItem->branch_id)
                            ->orderBy('available_qty', 'desc')
                            ->get();

                        foreach ($otherStocks as $stock) {
                            if ($remainingQty <= 0) break;

                            $restock = $remainingQty;
                            $stock->update([
                                'available_qty' => $stock->available_qty + $restock
                            ]);
                            $remainingQty -= $restock;
                        }
                    }
                }

                $item->sale_items()->delete();

                $item->forceDelete();
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sale permanently deleted.'
        ]);
    }

    public function destroyV2(Request $request, $id)
    {
        try {
            $item = Sale::with('items.product.stocks')->find($request->item_id);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }

            // Loop through sale items to restore stock
            foreach ($item->items as $saleItem) {
                $product = $saleItem->product;

                // Restore total product stock
                $product->update([
                    'stock_qty' => $product->stock_qty + $saleItem->quantity
                ]);

                $remainingQty = $saleItem->quantity;

                // Try to restore stock to the original branch first (if branch_id is stored in SaleItem)
                if ($saleItem->branch_id) {
                    $branchStock = $product->stocks()->where('branch_id', $saleItem->branch_id)->first();
                    if ($branchStock) {
                        $restock = min($remainingQty, $saleItem->quantity); // can also check max capacity if needed
                        $branchStock->update([
                            'available_qty' => $branchStock->available_qty + $restock
                        ]);
                        $remainingQty -= $restock;
                    }
                }

                // If any remaining, restore sequentially to other branches
                if ($remainingQty > 0) {
                    $otherStocks = $product->stocks()
                        ->where('branch_id', '!=', $saleItem->branch_id)
                        ->orderBy('id')
                        ->get();

                    foreach ($otherStocks as $stock) {
                        if ($remainingQty <= 0) break;

                        $restock = $remainingQty;
                        $stock->update([
                            'available_qty' => $stock->available_qty + $restock
                        ]);
                        $remainingQty -= $restock;
                    }
                }
            }

            // Finally, delete the sale
            $item->delete();

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
    }

    public function invoice($role, $id)
    {
        $sale = Sale::with('items', 'items.product', 'customer')->find($id);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $customers = Customer::orderBy('name')->where('is_active', 1)->get();
        $companies = Company::orderBy('name')->where('status', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();
        $banks = Bank::orderBy('name')->where('status', 1)->get();
        $setting = null;
        $data = clone $sale;
        $company = Company::findOrFail($data->company_id ?? 2);
        
        $invoiceTemplate = InvoiceTemplate::with('fields', 'style')->where('type', 'sale')->where('is_default', 1)->first();
        

        return view('sales.invoice', compact(
            'users',
            'products',
            'companies',
            'customers',
            'banks',
            'setting',
            'sale',
            'data',
            'company',
            'invoiceTemplate'
        ));
    }

    public function modal($role, $id)
    {
        try {

            $banks = Bank::orderBy('name')->where('status', 1)->get();
            $sale  = Sale::with('items', 'items.product')->find($id);
            $data['html']  = view('sales.payment-modal', compact('banks', 'sale'))->render();
        } catch (\Throwable $th) {
            return response([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response([
            'data' => $data,
            'success' => true,
            'message' => 'Data Found!',
        ]);
    }

    public function make_payment(Request $request, $role, $id)
    {
        try {
            // Validate incoming request
            $validated = $request->validate([
                'payment_method' => 'required|in:cash,card,advance,checque,bank_transfer,mobile_banking,other',
                'bank_id' => 'required|exists:banks,id',
                'payment_date' => 'required|date',
                'reference_no' => 'nullable|string|max:255',
                'payment_amount' => 'required|numeric|min:0.01',
                'schedule_id' => 'nullable|exists:payment_schedules,id',
            ]);

            $sale = Sale::with('items')->findOrFail($id);

            // Check if payment amount exceeds due amount
            if ($validated['payment_amount'] > $sale->due_amount) {
                return redirect()->back()->with('error', 'Payment amount cannot exceed due amount!');
            }

            DB::transaction(function () use ($validated, $sale) {

                // Get bank/account info
                $acc = Bank::find($validated['bank_id']);
                $opening_balance = $acc?->balance ?? 0;

                // Create Transaction record (like in update method)
                Transaction::create([
                    'user_id' => $sale->customer_id,
                    'user_type' => 'customer',
                    'type' => 'sale',
                    'account_id' => $validated['bank_id'],
                    'payment_date' => $validated['payment_date'],
                    'reference_no' => $validated['reference_no'],
                    'payment_method' => $validated['payment_method'],
                    'invoice_id' => $sale->id,
                    'old_balance' => $opening_balance,
                    'debit' => 0,
                    'credit' => $validated['payment_amount'],
                    'balance' => $opening_balance + $validated['payment_amount'],
                    'note' => 'Payment from Receive Payment.',
                ]);

                // Update bank balance
                if ($acc) {
                    $acc->update([
                        'balance' => $opening_balance + $validated['payment_amount']
                    ]);
                }

                // Update sale amounts            
                $newPaidTotal = min($sale->paid_amount + $validated['payment_amount'], $sale->total_amount);
                $newDueAmount = max($sale->due_amount - $validated['payment_amount'], 0);

                // Determine payment status
                $paymentStatus = $newDueAmount <= 0 ? 'paid' : 'partial';

                $sale->update([
                    'paid_amount' => $newPaidTotal,
                    'due_amount' => $newDueAmount,
                    'payment_status' => $paymentStatus,
                ]);

                // ── JOURNAL (payment received) ────────────────────────────
                $receivableAccount = \App\Models\Account::where('code', config('accounts.accounts_receivable'))->firstOrFail();

                $bank = \App\Models\Bank::find($validated['bank_id']);
                if (!$bank || !$bank->account_id) {
                    throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                }

                // Each payment gets its OWN journal entry (not replacing the sale journal)
                $journal = \App\Models\JournalEntry::create([
                    'company_id'  => auth()->user()->company_id,
                    'created_by'  => auth()->id(),
                    'date'        => $validated['payment_date'],
                    'reference'   => $validated['reference_no'] ?? $sale->invoice_no,
                    'source'      => 'sale',
                    'source_id'   => $sale->id,
                    'description' => 'Payment received — ' . $sale->invoice_no,
                ]);

                // Debit: Cash/Bank — money came in
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $bank->account_id,
                    'debit'            => $validated['payment_amount'],
                    'credit'           => 0,
                    'note'             => 'Payment received',
                ]);

                // Credit: Accounts Receivable — customer owes less now
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $receivableAccount->id,
                    'debit'            => 0,
                    'credit'           => $validated['payment_amount'],
                    'note'             => 'Receivable cleared — ' . $sale->invoice_no,
                ]);
                // ── END JOURNAL ───────────────────────────────────────────

                // Mark schedule paid if provided
                if (!empty($validated['schedule_id'])) {
                    \App\Models\PaymentSchedule::where('id', $validated['schedule_id'])
                        ->where('schedulable_type', \App\Models\Sale::class)
                        ->where('schedulable_id', $sale->id)
                        ->update([
                            'status'    => 'paid',
                            'paid_date' => $validated['payment_date'],
                        ]);
                }

            });
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->with('error', $th->getMessage());
        }
        return redirect()->back()->with('success', 'Payment of ৳' . number_format($validated['payment_amount'], 2) . ' added successfully!');
    }
}
