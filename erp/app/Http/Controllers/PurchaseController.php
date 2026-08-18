<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InvoiceTemplate;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class PurchaseController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view purchases', only: ['index']),
            new Middleware('permission:create purchases', only: ['create', 'store']),
            new Middleware('permission:edit purchase', only: ['edit', 'update']),
            new Middleware('permission:delete purchase', only: ['destroy']),
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
        $query = Purchase::select('purchases.*')
            ->leftJoin('users', 'users.id', '=', 'purchases.created_by')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->leftJoin('companies', 'companies.id', '=', 'purchases.company_id')
            ->orderBy('purchases.id', 'asc');

        if ($request->has('created_by') && !empty($request->created_by)) {
            $query->where('purchases.created_by', $request->created_by);
        }

        if ($request->has('supplier_id') && !empty($request->supplier_id)) {
            $query->where('purchases.supplier_id', $request->supplier_id);
        }

        if ($request->has('company_id') && !empty($request->company_id)) {
            $query->where('purchases.company_id', $request->company_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('purchases.purchase_no', 'like', "%{$search}%");
            });
        }


        $datas = $query->paginate(20);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $suppliers = Supplier::orderBy('name')->where('is_active', 1)->get();
        $companies = Company::orderBy('name')->where('status', 1)->get();

        return view('purchases.index', compact(
            'datas',
            'users',
            'companies',
            'suppliers'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $lastInvoice = Purchase::latest('id')->value('purchase_no');

        if ($lastInvoice) {
            $lastNumber = (int) str_replace('PUR-', '', $lastInvoice);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        $nextPurNo = 'PUR-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $suppliers = Supplier::orderBy('name')->where('is_active', 1)->get();
        $companies = Company::orderBy('name')->where('status', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();
        $banks = Bank::orderBy('name')->where('status', 1)->get();

        return view('purchases.create', compact(
            'users',
            'products',
            'companies',
            'suppliers',
            'banks',
            'nextPurNo'
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
            'purchase_date' => 'required',
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
            DB::transaction(function () use($request) {

                $lastInvoice = Purchase::latest('id')->value('purchase_no');

                if ($lastInvoice) {
                    $lastNumber = (int) str_replace('PUR-', '', $lastInvoice);
                    $nextNumber = $lastNumber + 1;
                } else {
                    $nextNumber = 1;
                }

                $invoiceNo = 'PUR-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

                $data = Purchase::create([
                    'supplier_id' => $request->supplier_id,
                    'company_id' => $request->company_id,
                    'bank_id' => $request->bank_id,
                    'purchase_no' => $invoiceNo,
                    'purchase_date' => $request->purchase_date ?? now(),
                    'total_amount' => $request->total_amount ?? 0,
                    'paid_amount' => $request->paid_amount ?? 0,
                    'due_amount' => $request->due_amount ?? 0,
                    'payment_status' => $request->payment_status,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'created_by' => auth()->id()
                ]);

                // ── JOURNAL (auto) ────────────────────────────────────────
                $inventoryAccount = \App\Models\Account::where('code', config('accounts.inventory'))->firstOrFail();
                $payableAccount   = \App\Models\Account::where('code', config('accounts.accounts_payable'))->firstOrFail();

                $journal = \App\Models\JournalEntry::create([
                    'company_id'  => $request->company_id ?? auth()->user()->company_id ?? 2,
                    'created_by'  => auth()->id(),
                    'date'        => $data->purchase_date,
                    'reference'   => $data->purchase_no,
                    'source'      => 'purchase',
                    'source_id'   => $data->id,
                    'description' => 'Purchase — ' . $data->purchase_no,
                ]);

                $journalItems = [];

                // Debit: Inventory — always, full total (goods received)
                $journalItems[] = [
                    'account_id' => $inventoryAccount->id,
                    'debit'      => $data->total_amount,
                    'credit'     => 0,
                    'note'       => 'Stock received — ' . $data->purchase_no,
                ];

                // Credit: Cash/Bank — only if payment was made
                if ($data->paid_amount > 0 && $data->bank_id) {
                    $bank = \App\Models\Bank::find($data->bank_id);
                    if (!$bank || !$bank->account_id) {
                        throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                    }
                    $journalItems[] = [
                        'account_id' => $bank->account_id,
                        'debit'      => 0,
                        'credit'     => $data->paid_amount,
                        'note'       => 'Cash paid to supplier',
                    ];
                }

                // Credit: Accounts Payable — only if due exists
                if ($data->due_amount > 0) {
                    $journalItems[] = [
                        'account_id' => $payableAccount->id,
                        'debit'      => 0,
                        'credit'     => $data->due_amount,
                        'note'       => 'Amount owed to supplier — ' . $data->purchase_no,
                    ];
                }

                foreach ($journalItems as $item) {
                    \App\Models\JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
                }
                // ── END JOURNAL ───────────────────────────────────────────

                // ── Record bank transaction if payment made ────────────────────
                if ($request->bank_id && $request->paid_amount > 0) {
                    $acc             = Bank::find($request->bank_id);
                    $opening_balance = $acc?->balance ?? 0;

                    Transaction::create([
                        'user_id'        => $request->supplier_id,
                        'user_type'      => 'supplier',
                        'type'           => 'purchase',
                        'account_id'     => $request->bank_id,
                        'payment_date'   => now(),
                        'reference_no'   => $request->reference_no,
                        'payment_method' => $request->payment_method,
                        'invoice_id'     => $data->id,
                        'old_balance'    => $opening_balance,
                        'debit'          => $request->paid_amount,   // money going OUT for purchase
                        'credit'         => 0,
                        'balance'        => $opening_balance - $request->paid_amount,
                        'note'           => 'From purchase create',
                    ]);

                    if ($acc) {
                        $acc->update([
                            'balance' => $opening_balance - $request->paid_amount
                        ]);
                    }
                }

                // $supplier = Supplier::find($request->supplier_id);
                // if ($supplier) {
                //     $supplier->increment('total_purchase',          (float) ($request->total_amount ?? 0));
                //     $supplier->increment('total_payment_paid',      (float) ($request->paid_amount  ?? 0));
                //     $supplier->increment('total_due',               (float) ($request->due_amount   ?? 0));
                // }

                if ($request->product_ids) {
                    foreach ($request->product_ids as $key => $product_id) {
                        $quantity = (int) ($request->quantity[$key] ?? 0);
                        $unit_price = (float) ($request->unit_price[$key] ?? 0);
                        $subtotal = (float) ($request->subtotal[$key] ?? 0);

                        PurchaseItem::updateOrCreate(
                            ['product_id' => $product_id, 'purchase_id' => $data->id],
                            ['quantity' => $quantity, 'unit_price' => $unit_price, 'subtotal' => $subtotal]
                        );

                        $product = Product::with('stocks')->find($product_id);

                        $product->update([
                            'stock_qty' => $product->stock_qty + $quantity
                        ]);

                        if ($request->branch_id) {
                            $branchStock = $product->stocks()->where('branch_id', $request->branch_id)->first();

                            if ($branchStock) {
                                $branchStock->update([
                                    'available_qty' => $branchStock->available_qty + $quantity
                                ]);
                            } else {
                                $product->stocks()->create([
                                    'branch_id' => $request->branch_id,
                                    'available_qty' => $quantity
                                ]);
                            }
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
        $purchase = Purchase::with('items', 'items.product')->find($id);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $suppliers = Supplier::orderBy('name')->where('is_active', 1)->get();
        $companies = Company::orderBy('name')->where('status', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();
        $banks = Bank::orderBy('name')->where('status', 1)->get();

        return view('purchases.edit', compact(
            'users',
            'products',
            'companies',
            'suppliers',
            'banks',
            'purchase'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id=null)
    {
        $data = Purchase::findOrFail($request->purchase_id);
        if (!$data) {
            return request()->ajax()
                ? response()->json(['success' => false, 'message' => 'Data Info Not Found!'])
                : redirect()->back()->with('error', 'Data Info Not Found!');
        }

        $validator = Validator::make($request->all(), [
            'purchase_date' => 'required',
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
                
                // ── 1. REVERSE OLD SUPPLIER BALANCES ──────────────────────────
                // $oldSupplier = Supplier::find($data->supplier_id);
                // if ($oldSupplier) {
                //     $oldSupplier->decrement('total_purchase',     (float) $data->total_amount);
                //     $oldSupplier->decrement('total_payment_paid', (float) $data->paid_amount);
                //     $oldSupplier->decrement('total_due',          (float) $data->due_amount);
                // }

                // ── 2. CALCULATE NEW PAID TOTAL ───────────────────────────────
                $newPaidTotal = $data->paid_amount + $request->paid_amount;
                $actualPaidAmount = ($newPaidTotal > $request->total_amount) ? $request->total_amount : $newPaidTotal;

                $data->update([
                    'supplier_id' => $request->supplier_id,
                    'bank_id' => $request->bank_id,
                    'company_id' => $request->company_id,
                    'purchase_date' => $request->purchase_date,
                    'total_amount' => $request->total_amount ?? 0,
                    'paid_amount' => $actualPaidAmount ?? 0,
                    'due_amount' => $request->due_amount ?? 0,
                    'payment_status' => $request->payment_status,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note
                ]);

                // ── JOURNAL UPDATE (auto) ─────────────────────────────────
                $inventoryAccount = \App\Models\Account::where('code', config('accounts.inventory'))->firstOrFail();
                $payableAccount   = \App\Models\Account::where('code', config('accounts.accounts_payable'))->firstOrFail();

                // Find existing journal entry for this purchase
                $journal = \App\Models\JournalEntry::where('source', 'purchase')
                                                ->where('source_id', $data->id)
                                                ->first();

                if ($journal) {
                    // Wipe old items and regenerate fresh
                    $journal->items()->delete();
                    $journal->update([
                        'date'        => $data->purchase_date,
                        'description' => 'Purchase (edited) — ' . $data->purchase_no,
                    ]);
                } else {
                    // Fallback: journal was missing, create it now
                    $journal = \App\Models\JournalEntry::create([
                        'company_id'  => $request->company_id ?? auth()->user()->company_id ?? 2,
                        'created_by'  => auth()->id(),
                        'date'        => $data->purchase_date,
                        'reference'   => $data->purchase_no,
                        'source'      => 'purchase',
                        'source_id'   => $data->id,
                        'description' => 'Purchase (edited) — ' . $data->purchase_no,
                    ]);
                }

                $journalItems = [];

                // Debit: Inventory — always, full total (goods received)
                $journalItems[] = [
                    'account_id' => $inventoryAccount->id,
                    'debit'      => $data->total_amount,
                    'credit'     => 0,
                    'note'       => 'Stock received — ' . $data->purchase_no,
                ];

                // Credit: Cash/Bank — only if total paid_amount > 0
                if ($data->paid_amount > 0 && $data->bank_id) {
                    $bank = \App\Models\Bank::find($data->bank_id);
                    if (!$bank || !$bank->account_id) {
                        throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                    }
                    $journalItems[] = [
                        'account_id' => $bank->account_id,
                        'debit'      => 0,
                        'credit'     => $data->paid_amount,
                        'note'       => 'Cash paid to supplier',
                    ];
                }

                // Credit: Accounts Payable — only if due exists
                if ($data->due_amount > 0) {
                    $journalItems[] = [
                        'account_id' => $payableAccount->id,
                        'debit'      => 0,
                        'credit'     => $data->due_amount,
                        'note'       => 'Amount owed to supplier — ' . $data->purchase_no,
                    ];
                }

                foreach ($journalItems as $item) {
                    \App\Models\JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
                }
                // ── END JOURNAL ───────────────────────────────────────────

                // ── 4. RECORD BANK TRANSACTION IF PAYMENT MADE ───────────────
                if ($request->bank_id && $request->paid_amount > 0) {
                    $acc             = Bank::find($request->bank_id);
                    $opening_balance = $acc?->balance ?? 0;

                    Transaction::create([
                        'user_id'        => $request->supplier_id,
                        'user_type'      => 'supplier',
                        'type'           => 'purchase',
                        'account_id'     => $request->bank_id,
                        'payment_date'   => now(),
                        'reference_no'   => $request->reference_no,
                        'payment_method' => $request->payment_method,
                        'invoice_id'     => $data->id,
                        'old_balance'    => $opening_balance,
                        'debit'          => $request->paid_amount,  // money going OUT
                        'credit'         => 0,
                        'balance'        => $opening_balance - $request->paid_amount,
                        'note'           => 'From purchase edit',
                    ]);

                    if ($acc) {
                        $acc->update([
                            'balance' => $opening_balance - $request->paid_amount
                        ]);
                    }
                }

                // ── 5. APPLY NEW VALUES TO THE (POTENTIALLY NEW) SUPPLIER ─────
                // $newSupplier = Supplier::find($request->supplier_id);
                // if ($newSupplier) {
                //     $newSupplier->increment('total_purchase',     (float) ($request->total_amount ?? 0));
                //     $newSupplier->increment('total_payment_paid', (float) $actualPaidAmount);
                //     $newSupplier->increment('total_due',          (float) ($request->due_amount  ?? 0));
                // }

                $existingItems = $data->purchase_items->keyBy('product_id');
                $incomingItemIds = $request->product_ids ?? [];

                // Handle removed items & restore stock
                $removedIds = array_diff($existingItems->keys()->toArray(), $incomingItemIds);
                foreach ($removedIds as $productId) {
                    $item = $existingItems[$productId];

                    $product = Product::with('stocks')->find($productId);

                    // Decrease total stock since we’re removing a purchase
                    $product->decrement('stock_qty', $item->quantity);

                    // Decrease branch stock
                    if ($data->branch_id) {
                        $branchStock = $product->stocks()->where('branch_id', $data->branch_id)->first();
                        if ($branchStock) {
                            $branchStock->decrement('available_qty', $item->quantity);
                        }
                    }

                    $item->delete();
                }


                // Update or create incoming items and adjust stock
                foreach ($incomingItemIds as $key => $productId) {
                    $quantity = (int) ($request->quantity[$key] ?? 0);
                    $unitPrice = (float) ($request->unit_price[$key] ?? 0);
                    $subtotal = (float) ($request->subtotal[$key] ?? 0);

                    $oldQuantity = $existingItems[$productId]->quantity ?? 0;
                    $qtyDiff = $quantity - $oldQuantity; // positive = added, negative = reduced

                    PurchaseItem::updateOrCreate(
                        ['purchase_id' => $data->id, 'product_id' => $productId],
                        ['quantity' => $quantity, 'unit_price' => $unitPrice, 'subtotal' => $subtotal]
                    );

                    // Adjust stock properly
                    if ($qtyDiff != 0) {
                        $product = Product::with('stocks')->find($productId);

                        // Increase or decrease main stock depending on diff
                        $newQty = $product->stock_qty + $qtyDiff;
                        if ($newQty < 0) {
                            throw new \Exception("Invalid stock adjustment for product ID: {$product->sku}");
                        }
                        $product->update(['stock_qty' => $newQty]);

                        // Adjust branch stock accordingly
                        if ($request->branch_id) {
                            $branchStock = $product->stocks()->where('branch_id', $request->branch_id)->first();

                            if ($branchStock) {
                                $newAvailable = $branchStock->available_qty + $qtyDiff;
                                if ($newAvailable < 0) {
                                    throw new \Exception("Invalid branch stock for product ID: {$product->sku}");
                                }
                                $branchStock->update(['available_qty' => $newAvailable]);
                            } else {
                                // Create branch stock if adding positive quantity
                                if ($qtyDiff > 0) {
                                    $product->stocks()->create([
                                        'branch_id' => $request->branch_id,
                                        'available_qty' => $qtyDiff
                                    ]);
                                }
                            }
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
            $purchase = Purchase::with('items.product.stocks')->find($request->item_id);

            if (!$purchase) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }

            DB::transaction(function () use ($purchase) {

                // ── 1. REVERSE SUPPLIER BALANCES ──────────────────────────────
                // $supplier = Supplier::find($purchase->supplier_id);
                // if ($supplier) {
                //     $supplier->decrement('total_purchase',     (float) $purchase->total_amount);
                //     $supplier->decrement('total_payment_paid', (float) $purchase->paid_amount);
                //     $supplier->decrement('total_due',          (float) $purchase->due_amount);
                // }

                // ── 2. DELETE TRANSACTIONS & RESTORE BANK BALANCE ────────────
                $transactions = Transaction::where('invoice_id', $purchase->id)->where('type', 'purchase')->get();

                foreach ($transactions as $transaction) {
                    $acc = Bank::find($transaction->account_id);
                    if ($acc) {
                        $acc->increment('balance', (float) $transaction->debit);
                    }
                    $transaction->delete();
                }

                // ── JOURNAL CLEANUP ───────────────────────────────────────────
                $journal = \App\Models\JournalEntry::where('source', 'purchase')
                    ->where('source_id', $purchase->id)
                    ->first();
                if ($journal) {
                    $journal->items()->forceDelete();
                    $journal->forceDelete();
                }
                // ── END JOURNAL ───────────────────────────────────────────────

                // Loop through purchase items to reverse stock
                foreach ($purchase->items as $purchaseItem) {
                    $product = $purchaseItem->product;

                    // Decrease total product stock
                    $newQty = $product->stock_qty - $purchaseItem->quantity;
                    if ($newQty < 0) {
                        throw new \Exception("Invalid stock after deleting purchase for product ID: {$product->sku}");
                    }
                    $product->update(['stock_qty' => $newQty]);

                    // Decrease branch stock
                    if ($purchaseItem->branch_id) {
                        $branchStock = $product->stocks()->where('branch_id', $purchaseItem->branch_id)->first();
                        if ($branchStock) {
                            $newAvailable = $branchStock->available_qty - $purchaseItem->quantity;
                            if ($newAvailable < 0) {
                                throw new \Exception("Invalid branch stock for product ID: {$product->id}");
                            }
                            $branchStock->update(['available_qty' => $newAvailable]);
                        }
                    }
                }

                // Delete purchase items first, then main record
                $purchase->items()->delete();
                $purchase->delete();

            });

            return response()->json([
                'success' => true,
                'message' => 'Purchase deleted successfully.'
            ]);
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
        $purchase = Purchase::with('items', 'items.product', 'supplier')->find($id);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $suppliers = Supplier::orderBy('name')->where('is_active', 1)->get();
        $companies = Company::orderBy('name')->where('status', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();
        $banks = Bank::orderBy('name')->where('status', 1)->get();
        $setting = null;

        $data = clone $purchase;
        $company = Company::findOrFail($data->company_id ?? 2);
        $invoiceTemplate = InvoiceTemplate::with('fields', 'style')->where('type', 'purchase')->where('is_default', 1)->first();

        return view('purchases.invoice', compact(
            'users',
            'products',
            'companies',
            'suppliers',
            'banks',
            'setting',
            'purchase',
            'data',
            'company',
            'invoiceTemplate'
        ));
    }

    public function make_payment(Request $request, $role, $id)
    {
        try {
            $validated = $request->validate([
                'payment_method' => 'required|in:cash,card,advance,checque,bank_transfer,mobile_banking,other',
                'bank_id'        => 'required|exists:banks,id',
                'payment_date'   => 'required|date',
                'reference_no'   => 'nullable|string|max:255',
                'payment_amount' => 'required|numeric|min:0.01',
                'schedule_id'    => 'nullable|exists:payment_schedules,id',
            ]);

            $purchase = Purchase::findOrFail($id);

            if ($validated['payment_amount'] > $purchase->due_amount) {
                return redirect()->back()->with('error', 'Payment amount cannot exceed due amount!');
            }

            DB::transaction(function () use ($validated, $purchase) {

                $acc = Bank::find($validated['bank_id']);
                $opening_balance = $acc?->balance ?? 0;

                Transaction::create([
                    'user_id'        => $purchase->supplier_id,
                    'user_type'      => 'supplier',
                    'type'           => 'purchase',
                    'account_id'     => $validated['bank_id'],
                    'payment_date'   => $validated['payment_date'],
                    'reference_no'   => $validated['reference_no'],
                    'payment_method' => $validated['payment_method'],
                    'invoice_id'     => $purchase->id,
                    'old_balance'    => $opening_balance,
                    'debit'          => $validated['payment_amount'],
                    'credit'         => 0,
                    'balance'        => $opening_balance - $validated['payment_amount'],
                    'note'           => 'Payment from Make Payment.',
                ]);

                if ($acc) {
                    $acc->update(['balance' => $opening_balance - $validated['payment_amount']]);
                }

                $newPaidTotal = min($purchase->paid_amount + $validated['payment_amount'], $purchase->total_amount);
                $newDueAmount = max($purchase->due_amount - $validated['payment_amount'], 0);

                $purchase->update([
                    'paid_amount'    => $newPaidTotal,
                    'due_amount'     => $newDueAmount,
                    'payment_status' => $newDueAmount <= 0 ? 'paid' : 'partial',
                ]);

                // ── JOURNAL: Debit AP 2110 / Credit Bank ─────────────────────
                $apAccount = \App\Models\Account::where('code', config('accounts.accounts_payable'))->firstOrFail();
                $bank = Bank::find($validated['bank_id']);
                if (!$bank || !$bank->account_id) {
                    throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                }

                $journal = \App\Models\JournalEntry::create([
                    'company_id'  => auth()->user()->company_id ?? 2,
                    'created_by'  => auth()->id(),
                    'date'        => $validated['payment_date'],
                    'reference'   => $validated['reference_no'] ?? $purchase->purchase_no,
                    'source'      => 'purchase',
                    'source_id'   => $purchase->id,
                    'description' => 'Payment made — ' . $purchase->purchase_no,
                ]);

                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $apAccount->id,
                    'debit'            => $validated['payment_amount'],
                    'credit'           => 0,
                    'note'             => 'Payable cleared — ' . $purchase->purchase_no,
                ]);

                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $bank->account_id,
                    'debit'            => 0,
                    'credit'           => $validated['payment_amount'],
                    'note'             => 'Payment sent',
                ]);
                // ── END JOURNAL ───────────────────────────────────────────────

                if (!empty($validated['schedule_id'])) {
                    \App\Models\PaymentSchedule::where('id', $validated['schedule_id'])
                        ->where('schedulable_type', \App\Models\Purchase::class)
                        ->where('schedulable_id', $purchase->id)
                        ->update(['status' => 'paid', 'paid_date' => $validated['payment_date']]);
                }
            });
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->with('error', $th->getMessage());
        }

        return redirect()->back()->with('success', 'Payment of ৳' . number_format($validated['payment_amount'], 2) . ' sent successfully!');
    }

}
