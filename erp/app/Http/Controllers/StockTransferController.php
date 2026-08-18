<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\User;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockTransferController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view stock transfer', only: ['index']),
            new Middleware('permission:create stock transfer', only: ['create', 'store']),
            new Middleware('permission:edit stock transfer', only: ['edit', 'update']),
            new Middleware('permission:delete stock transfer', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = StockTransfer::select('stock_transfers.*')
            ->leftJoin('users', 'users.id', '=', 'stock_transfers.created_by')
            ->leftJoin('products', 'products.id', '=', 'stock_transfers.product_id')
            ->leftJoin('branches as from_branch', 'from_branch.id', '=', 'stock_transfers.from_branch_id')
            ->leftJoin('branches as to_branch', 'to_branch.id', '=', 'stock_transfers.to_branch_id')
            ->orderBy('stock_transfers.id', 'asc');

        if ($request->has('created_by') && !empty($request->created_by)) {
            $query->where('stock_transfers.created_by', $request->created_by);
        }

        if ($request->has('product_id') && !empty($request->product_id)) {
            $query->where('stock_transfers.product_id', $request->product_id);
        }

        if ($request->has('from_branch_id') && !empty($request->from_branch_id)) {
            $query->where('stock_transfers.from_branch_id', $request->from_branch_id);
        }

        if ($request->has('to_branch_id') && !empty($request->to_branch_id)) {
            $query->where('stock_transfers.to_branch_id', $request->to_branch_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('stock_transfers.transfer_no', 'like', "%{$search}%");
            });
        }

        $datas = $query->paginate(20);
        
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $branches = Branch::orderBy('name')->where('is_active', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();

        return view('stock-transfers.index', compact(
            'datas',
            'users',
            'branches',
            'products'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $lastInvoice = StockTransfer::latest('id')->value('transfer_no');

        if ($lastInvoice) {
            $lastNumber = (int) str_replace('TRNF-', '', $lastInvoice);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        $nextTransNo = 'TRNF-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $suppliers = Supplier::orderBy('name')->where('is_active', 1)->get();
        $branches = Branch::orderBy('name')->where('is_active', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();
        $banks = Bank::orderBy('name')->where('status', 1)->get();

        return view('stock-transfers.create', compact(
            'users',
            'products',
            'branches',
            'suppliers',
            'banks',
            'nextTransNo'
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
            'from_branch_id' => 'required',
            'to_branch_id' => 'required',
            'product_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);            
        }
        
        try { 
            DB::transaction(function () use ($request) {

                // Generate next transfer number
                $lastInvoice = StockTransfer::latest('id')->value('transfer_no');

                $nextNumber = $lastInvoice ? ((int) str_replace('TRNF-', '', $lastInvoice) + 1) : 1;
                $trsnfNo = 'TRNF-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

                // Check stock availability
                $stock = Stock::where('product_id', $request->product_id)
                            ->where('branch_id', $request->from_branch_id)
                            ->lockForUpdate() // Prevent race conditions
                            ->first();

                if (!$stock) {
                    throw new \Exception('Stock not found for this product in the selected branch.');
                }

                if ($stock->available_qty < $request->quantity) {
                    throw new \Exception('Insufficient stock available for transfer.');
                }

                // Create stock transfer
                $transfer = StockTransfer::create([
                    'transfer_no'    => $trsnfNo,
                    'product_id'     => $request->product_id,
                    'from_branch_id' => $request->from_branch_id,
                    'to_branch_id'   => $request->to_branch_id,
                    'quantity'       => $request->quantity,
                    'note'           => $request->note,
                    'created_by'     => auth()->id()
                ]);

                // Update stock quantities
                // Deduct from source branch
                $stock->available_qty -= $request->quantity;
                $stock->save();

                // Add to destination branch
                $destStock = Stock::firstOrCreate(
                    [
                        'product_id' => $request->product_id,
                        'branch_id' => $request->to_branch_id
                    ],
                    [
                        'available_qty' => 0
                    ]
                );

                $destStock->available_qty += $request->quantity;
                $destStock->save();
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
        $transfer = StockTransfer::find($id);        
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $suppliers = Supplier::orderBy('name')->where('is_active', 1)->get();
        $branches = Branch::orderBy('name')->where('is_active', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();
        $banks = Bank::orderBy('name')->where('status', 1)->get();

        return view('stock-transfers.edit', compact(
            'users',
            'products',
            'branches',
            'suppliers',
            'banks',
            'transfer'
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
        $request->validate([
            'product_id'     => 'required|exists:products,id',
            'from_branch_id' => 'required|exists:branches,id',
            'to_branch_id'   => 'required|exists:branches,id|different:from_branch_id',
            'quantity'       => 'required|integer|min:1',
            'note'           => 'nullable|string'
        ]);

        try {

            DB::transaction(function () use ($request) {

                $item = StockTransfer::find($request->purchase_id);
                if (!$item) {
                    throw new \Exception('Stock transfer not found!');
                }

                // Save old values to revert stock if needed
                $oldProductId     = $item->product_id;
                $oldFromBranchId  = $item->from_branch_id;
                $oldToBranchId    = $item->to_branch_id;
                $oldQuantity      = $item->quantity;

                $newProductId     = $request->product_id;
                $newFromBranchId  = $request->from_branch_id;
                $newToBranchId    = $request->to_branch_id;
                $newQuantity      = $request->quantity;

                // Revert old stock (undo old transfer)
                $oldSourceStock = Stock::firstOrCreate(
                    ['product_id' => $oldProductId, 'branch_id' => $oldFromBranchId],
                    ['available_qty' => 0]
                );
                $oldSourceStock->increment('available_qty', $oldQuantity);

                $oldDestStock = Stock::firstOrCreate(
                    ['product_id' => $oldProductId, 'branch_id' => $oldToBranchId],
                    ['available_qty' => 0]
                );
                $oldDestStock->decrement('available_qty', $oldQuantity);
                if ($oldDestStock->available_qty < 0) $oldDestStock->available_qty = 0;
                $oldDestStock->save();

                // Check new source stock availability
                $newSourceStock = Stock::where('product_id', $newProductId)
                    ->where('branch_id', $newFromBranchId)
                    ->lockForUpdate()
                    ->first();

                if (!$newSourceStock || $newSourceStock->available_qty < $newQuantity) {
                    throw new \Exception('Insufficient stock in source branch for the updated transfer!');
                }

                // Update stock for new transfer
                // Deduct from new source branch
                $newSourceStock->decrement('available_qty', $newQuantity);
                $newSourceStock->save();

                // Add to new destination branch
                $newDestStock = Stock::firstOrCreate(
                    ['product_id' => $newProductId, 'branch_id' => $newToBranchId],
                    ['available_qty' => 0]
                );
                $newDestStock->increment('available_qty', $newQuantity);

                // Update transfer record
                $item->update([
                    'product_id'     => $newProductId,
                    'from_branch_id' => $newFromBranchId,
                    'to_branch_id'   => $newToBranchId,
                    'quantity'       => $newQuantity,
                    'note'           => $request->note
                ]);
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
            DB::transaction(function () use ($request) {

                $item = StockTransfer::find($request->item_id);

                if (!$item) {
                    throw new \Exception('Data Info Not Found!');
                }

                $quantity = $item->quantity;
                $productId = $item->product_id;
                $fromBranchId = $item->from_branch_id;
                $toBranchId = $item->to_branch_id;

                // Revert source branch stock
                $sourceStock = Stock::where('product_id', $productId)
                                    ->where('branch_id', $fromBranchId)
                                    ->lockForUpdate()
                                    ->first();

                if (!$sourceStock) {
                    // If somehow missing, create with quantity
                    $sourceStock = Stock::create([
                        'product_id' => $productId,
                        'branch_id' => $fromBranchId,
                        'available_qty' => 0
                    ]);
                }

                $sourceStock->available_qty += $quantity;
                $sourceStock->save();

                // Revert destination branch stock
                $destStock = Stock::where('product_id', $productId)
                                ->where('branch_id', $toBranchId)
                                ->lockForUpdate()
                                ->first();

                if ($destStock) {
                    // Ensure quantity doesn’t go negative
                    $destStock->available_qty -= $quantity;
                    if ($destStock->available_qty < 0) {
                        $destStock->available_qty = 0;
                    }
                    $destStock->save();
                }

                // Delete the transfer
                $item->delete();
            });

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

}
