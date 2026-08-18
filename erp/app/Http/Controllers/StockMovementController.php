<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\User;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockMovementController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view stock movement', only: ['index']),
            new Middleware('permission:create stock movement', only: ['create', 'store']),
            new Middleware('permission:edit stock movement', only: ['edit', 'update']),
            new Middleware('permission:delete stock movement', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = StockMovement::select('stock_movements.*')
            ->leftJoin('products', 'products.id', '=', 'stock_movements.product_id')
            ->leftJoin('branches', 'branches.id', '=', 'stock_movements.branch_id')
            ->orderBy('stock_movements.id', 'asc');

        if ($request->has('product_id') && !empty($request->product_id)) {
            $query->where('stock_movements.product_id', $request->product_id);
        }

        if ($request->has('branch_id') && !empty($request->branch_id)) {
            $query->where('stock_movements.branch_id', $request->branch_id);
        }

        if ($request->has('type') && !empty($request->type)) {
            $query->where('stock_movements.type', $request->type);
        }

        if ($request->has('movement') && !empty($request->movement)) {
            $query->where('stock_movements.movement', $request->movement);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('stock_movements.reference_no', 'like', "%{$search}%");
            });
        }

        $datas = $query->paginate(20);
        
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $branches = Branch::orderBy('name')->where('is_active', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();

        return view('stock-movements.index', compact(
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
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $branches = Branch::orderBy('name')->where('is_active', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();

        return view('stock-movements.create', compact(
            'users',
            'products',
            'branches'
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
            'product_id' => 'required',
            'quantity' => 'required',
            'type' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);            
        }
        
        try {
            DB::transaction(function () use ($request) {

                // Create the stock movement entry
                $movement = StockMovement::create([
                    'product_id' => $request->product_id,
                    'branch_id' => $request->branch_id,
                    'movement' => $request->movement, 
                    'quantity' => $request->quantity,
                    'type' => $request->type, 
                    'note' => $request->note
                ]);

                // --- Manage stock record ---
                $stock = Stock::firstOrCreate([
                    'product_id' => $request->product_id,
                    'branch_id' => $request->branch_id
                ],[
                    'available_qty' => 0,
                    'reserved_qty' => 0,
                    'damaged_qty' => 0
                ]);

                // --- Update stock quantity based on movement movement ---
                if ($request->movement === 'in') {
                    // Increase available quantity
                    $stock->available_qty += $request->quantity;
                } elseif ($request->movement === 'out') {

                    // Check if sufficient stock is available
                    if ($stock->available_qty < $request->quantity) {
                        throw new \Exception('Not enough stock available for stock out operation.');
                    }

                    // Decrease available quantity
                    $stock->available_qty -= $request->quantity;
                }

                $stock->save();

                // --- Optionally update Product summary stock ---
                $totalStock = Stock::where('product_id', $request->product_id)
                    ->selectRaw('
                        COALESCE(SUM(available_qty), 0) +
                        COALESCE(SUM(reserved_qty), 0) +
                        COALESCE(SUM(damaged_qty), 0) as total_qty
                    ')
                    ->value('total_qty');

                Product::where('id', $request->product_id)->update([
                    'stock_qty' => $totalStock
                ]);
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
        $movement = StockMovement::find($id);        
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();        
        $branches = Branch::orderBy('name')->where('is_active', 1)->get();
        $products = Product::orderBy('name')->where('is_active', 1)->get();

        return view('stock-movements.edit', compact(
            'users',
            'products',
            'branches',
            'movement'
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
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|exists:branches,id',
            'movement' => 'required|in:in,out',
            'quantity' => 'required|integer|min:1',
            'type' => 'required|string',
            'note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {

            $movement = StockMovement::find($request->movement_id);
            if (!$movement) {
                throw new \Exception('Stock movement info not found.');
            }

            $oldProductId = $movement->product_id;
            $oldBranchId = $movement->branch_id;
            $oldQtyAction = $movement->movement;
            $oldQuantity = $movement->quantity;

            $newProductId = $request->product_id;
            $newBranchId = $request->branch_id;
            $newQtyAction = $request->movement;
            $newQuantity = $request->quantity;

            // --- Revert old movement ---
            $oldStock = Stock::firstOrCreate([
                'product_id' => $oldProductId,
                'branch_id' => $oldBranchId
            ], [
                'available_qty' => 0,
                'reserved_qty' => 0,
                'damaged_qty' => 0
            ]);

            if ($oldQtyAction === 'in') {
                // Undo stock_in → decrease available
                $oldStock->available_qty -= $oldQuantity;
                if ($oldStock->available_qty < 0) $oldStock->available_qty = 0;
            } elseif ($oldQtyAction === 'out') {
                // Undo stock_out → increase available
                $oldStock->available_qty += $oldQuantity;
            }
            $oldStock->save();

            // --- Apply new movement ---
            $newStock = Stock::firstOrCreate([
                'product_id' => $newProductId,
                'branch_id' => $newBranchId
            ], [
                'available_qty' => 0,
                'reserved_qty' => 0,
                'damaged_qty' => 0
            ]);

            if ($newQtyAction === 'in') {
                $newStock->available_qty += $newQuantity;
            } elseif ($newQtyAction === 'out') {
                if ($newStock->available_qty < $newQuantity) {
                    throw new \Exception('Not enough stock available for stock out operation.');
                }
                $newStock->available_qty -= $newQuantity;
            }
            $newStock->save();

            // --- Update movement record ---
            $movement->update([
                'product_id' => $newProductId,
                'branch_id' => $newBranchId,
                'movement' => $newQtyAction,
                'quantity' => $newQuantity,
                'type' => $request->type,
                'note' => $request->note
            ]);

            // --- Update Product summary stock ---
            $totalStock = Stock::where('product_id', $newProductId)
                ->selectRaw('
                    COALESCE(SUM(available_qty),0) +
                    COALESCE(SUM(reserved_qty),0) +
                    COALESCE(SUM(damaged_qty),0) as total_qty
                ')
                ->value('total_qty');

            Product::where('id', $newProductId)->update([
                'stock_qty' => $totalStock
            ]);

            // Optional: if product changed, update old product total stock too
            if ($oldProductId != $newProductId) {
                $oldTotal = Stock::where('product_id', $oldProductId)
                    ->selectRaw('
                        COALESCE(SUM(available_qty),0) +
                        COALESCE(SUM(reserved_qty),0) +
                        COALESCE(SUM(damaged_qty),0) as total_qty
                    ')
                    ->value('total_qty');

                Product::where('id', $oldProductId)->update([
                    'stock_qty' => $oldTotal
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock movement updated successfully.'
        ]);
    }



    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id=null)
    {
        DB::transaction(function () use ($request) {

            $movement = StockMovement::find($request->item_id);

            if (!$movement) {
                throw new \Exception('Stock movement not found.');
            }

            $productId = $movement->product_id;
            $branchId = $movement->branch_id;
            $qtyAction = $movement->movement;
            $quantity = $movement->quantity;

            // --- Revert the stock movement ---
            $stock = Stock::firstOrCreate([
                'product_id' => $productId,
                'branch_id' => $branchId
            ], [
                'available_qty' => 0,
                'reserved_qty' => 0,
                'damaged_qty' => 0
            ]);

            if ($qtyAction === 'in') {
                // Undo stock_in → decrease available
                $stock->available_qty -= $quantity;
                if ($stock->available_qty < 0) $stock->available_qty = 0;
            } elseif ($qtyAction === 'out') {
                // Undo stock_out → increase available
                $stock->available_qty += $quantity;
            }

            $stock->save();

            // --- Delete the movement ---
            $movement->delete();

            // --- Update Product summary stock ---
            $totalStock = Stock::where('product_id', $productId)
                ->selectRaw('
                    COALESCE(SUM(available_qty),0) +
                    COALESCE(SUM(reserved_qty),0) +
                    COALESCE(SUM(damaged_qty),0) as total_qty
                ')
                ->value('total_qty');

            Product::where('id', $productId)->update([
                'stock_qty' => $totalStock
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock movement deleted and stock reverted successfully.'
        ]);
    }
}
