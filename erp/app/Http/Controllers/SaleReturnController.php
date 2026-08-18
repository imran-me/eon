<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Sale;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnRef;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleReturnController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $req_subdatas = [];
        $query = ReturnRef::select('return_refs.*')
            ->leftJoin('users', 'users.id', '=', 'return_refs.created_by')            
            ->leftJoin('branches', 'branches.id', '=', 'return_refs.branch_id')
            ->orderBy('return_refs.id', 'asc');

        if ($request->has('created_by') && !empty($request->created_by)) {
            $query->where('return_refs.created_by', $request->created_by);
        }

        if ($request->has('customer_id') && !empty($request->customer_id)) {
            $query->where('return_refs.customer_id', $request->customer_id);
        }

        if ($request->has('branch_id') && !empty($request->branch_id)) {
            $query->where('return_refs.branch_id', $request->branch_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('return_refs.return_no', 'like', "%{$search}%")
                    ->orWhere('return_refs.reference_id', 'like', "%{$search}%");
            });
        }


        $datas = $query->paginate(20);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $customers = Customer::orderBy('name')->where('is_active', 1)->get();
        $branches = Branch::orderBy('name')->where('is_active', 1)->get();

        return view('return-refs.index', compact(
            'datas',
            'users',
            'branches',
            'customers'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $role = Str::slug(Auth::user()->getRoleNames()->first());
        if (empty($request->sale)) {            
            return redirect()->route('role.sales.index', ['role' => $role])->with('error', 'Missing Required Parameter!');            
        }

        $sale = Sale::with('items', 'items.product')->find($request->sale);
        if (empty($sale)) {            
            return redirect()->route('role.sales.index', ['role' => $role])->with('error', 'Sale Info Mismatched!');
        }

        $isReturned = ReturnRef::where('reference_id', $sale->id)->first();
        if (!empty($isReturned)) {            
            return redirect()->route('role.sales.index', ['role' => $role])->with('error', 'Sale can not return anymore!');
        }

        $lastreturn = ReturnRef::latest('id')->value('return_no');
        if ($lastreturn) {
            $lastNumber = (int) str_replace('SRN-', '', $lastreturn);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        $nextreturn = 'SRN-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        $branches = Branch::orderBy('name')->where('is_active', 1)->get();

        return view('return-refs.create', compact(
            'branches',
            'nextreturn',
            'sale'
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
            'return_type' => 'required',
            'product_ids' => 'required',
            'total_amount' => 'required',
            'return_quantity' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);            
        }
        
        try {
            DB::transaction(function () use ($request) {

                // Generate return number
                $lastreturn = ReturnRef::latest('id')->value('return_no');
                $nextNumber = $lastreturn ? ((int) str_replace('SRN-', '', $lastreturn)) + 1 : 1;
                $returnNo = 'SRN-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

                // Create ReturnRef
                $return = ReturnRef::create([
                    'reference_id' => $request->sale_id,
                    'branch_id' => $request->branch_id,
                    'return_no' => $returnNo,
                    'return_date' => $request->return_date ?? now(),
                    'total_amount' => $request->total_amount ?? 0,
                    'return_type' => $request->return_type,
                    'note' => $request->note,
                    'created_by' => auth()->id()
                ]);

                if ($request->product_ids) {
                    foreach ($request->product_ids as $key => $product_id) {

                        $quantity = (int) ($request->return_quantity[$key] ?? 0);
                        $unit_price = (float) ($request->unit_price[$key] ?? 0);
                        $subtotal = (float) ($request->subtotal[$key] ?? 0);

                        // Skip products with 0 return quantity
                        if ($quantity <= 0) continue;

                        // Create or update ReturnItem
                        ReturnItem::updateOrCreate(
                            ['product_id' => $product_id, 'return_id' => $return->id],
                            ['quantity' => $quantity, 'unit_price' => $unit_price, 'subtotal' => $subtotal]
                        );

                        // Update product stock
                        $product = Product::with('stocks')->find($product_id);
                        $product->increment('stock_qty', $quantity);

                        // Update branch stock if branch_id exists
                        if ($request->branch_id) {
                            $branchStock = $product->stocks()->firstOrCreate(
                                ['branch_id' => $request->branch_id],
                                ['available_qty' => 0]
                            );
                            $branchStock->increment('available_qty', $quantity);
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
    public function edit(Request $request, $role, $id)
    {        
        $item = ReturnRef::with('items', 'reference', 'reference.items')->find($id);        
        $branches = Branch::orderBy('name')->where('is_active', 1)->get();

        return view('return-refs.edit', compact(
            'branches',            
            'item'
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
        $return = ReturnRef::findOrFail($request->return_id);
        if (!$return) {
            return request()->ajax()
                ? response()->json(['success' => false, 'message' => 'Data Info Not Found!'])
                : redirect()->back()->with('error', 'Data Info Not Found!');
        }

        $validator = Validator::make($request->all(), [
            'return_type' => 'required',
            'product_ids' => 'required',
            'total_amount' => 'required',
            'return_quantity' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        try {
            DB::transaction(function () use ($request, $return) {
                
                // Update ReturnRef basic info
                $return->update([
                    'branch_id' => $request->branch_id,
                    'return_date' => $request->return_date ?? now(),
                    'total_amount' => $request->total_amount ?? 0,
                    'return_type' => $request->return_type,
                    'note' => $request->note,
                    'updated_by' => auth()->id()
                ]);

                $submittedProductIds = $request->product_ids ?? [];

                // Handle existing return items
                $existingItems = ReturnItem::where('return_id', $return->id)->get();

                foreach ($existingItems as $item) {

                    $key = array_search($item->product_id, $submittedProductIds);

                    if ($key === false) {
                        // Product removed from request -> subtract old quantity from stock
                        $product = Product::with('stocks')->find($item->product_id);
                        $product->decrement('stock_qty', $item->quantity);

                        if ($return->branch_id) {
                            $branchStock = $product->stocks()->where('branch_id', $return->branch_id)->first();
                            if ($branchStock) {
                                $branchStock->decrement('available_qty', $item->quantity);
                            }
                        }

                        $item->delete();
                        continue;
                    }

                    // Product exists in request -> update quantity & subtotal
                    $newQuantity = (int) ($request->return_quantity[$key] ?? 0);
                    $unit_price = (float) ($request->unit_price[$key] ?? 0);
                    $subtotal = (float) ($request->subtotal[$key] ?? 0);
                    $oldQuantity = $item->quantity;

                    $diffQuantity = $newQuantity - $oldQuantity;

                    $item->update([
                        'quantity' => $newQuantity,
                        'unit_price' => $unit_price,
                        'subtotal' => $subtotal
                    ]);

                    // Update product stock
                    $product = Product::with('stocks')->find($item->product_id);
                    $product->increment('stock_qty', $diffQuantity);

                    // Update branch stock
                    if ($return->branch_id) {
                        $branchStock = $product->stocks()->firstOrCreate(
                            ['branch_id' => $return->branch_id],
                            ['available_qty' => 0]
                        );
                        $branchStock->increment('available_qty', $diffQuantity);
                    }
                }

                // Handle new products added in request
                foreach ($submittedProductIds as $key => $product_id) {
                    $exists = $existingItems->where('product_id', $product_id)->first();
                    if ($exists) continue; // Already handled

                    $quantity = (int) ($request->return_quantity[$key] ?? 0);
                    if ($quantity <= 0) continue;

                    $unit_price = (float) ($request->unit_price[$key] ?? 0);
                    $subtotal = (float) ($request->subtotal[$key] ?? 0);

                    ReturnItem::create([
                        'return_id' => $return->id,
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'subtotal' => $subtotal
                    ]);

                    $product = Product::with('stocks')->find($product_id);
                    $product->increment('stock_qty', $quantity);

                    if ($return->branch_id) {
                        $branchStock = $product->stocks()->firstOrCreate(
                            ['branch_id' => $return->branch_id],
                            ['available_qty' => 0]
                        );
                        $branchStock->increment('available_qty', $quantity);
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
            $return = ReturnRef::with('items.product.stocks')->find($request->item_id);
            if (!$return) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }

            DB::transaction(function () use ($return) {                

                foreach ($return->items as $item) {
                    $product = $item->product;

                    // Decrease product stock
                    $product->decrement('stock_qty', $item->quantity);

                    // Decrease branch stock if exists
                    if ($return->branch_id) {
                        $branchStock = $product->stocks()->where('branch_id', $return->branch_id)->first();
                        if ($branchStock) {
                            $branchStock->decrement('available_qty', $item->quantity);
                        }
                    }

                    // Delete return item
                    $item->delete();
                }

                // Delete ReturnRef
                $return->delete();
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
