<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ExpenseCategory;
use App\Models\productsubCategory;
use App\Models\Stock;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view product', only: ['index']),
            new Middleware('permission:create product', only: ['create', 'store']),
            new Middleware('permission:edit product', only: ['edit', 'update']),
            new Middleware('permission:delete product', only: ['destroy']),
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
        $query = Product::select('products.*')
            ->leftJoin('users', 'users.id', '=', 'products.user_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
            ->orderBy('categories.name', 'asc')
            ->orderBy('products.name', 'asc');

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('products.user_id', $request->user_id);
        }

        if ($request->has('brand_id') && !empty($request->brand_id)) {
            $query->where('products.brand_id', $request->brand_id);
        }

        if ($request->has('unit_id') && !empty($request->unit_id)) {
            $query->where('products.unit_id', $request->unit_id);
        }

        if ($request->has('supplier_id') && !empty($request->supplier_id)) {
            $query->where('products.supplier_id', $request->supplier_id);
        }

        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('products.category_id', $request->category_id);
            $req_subdatas = SubCategory::where('category_id', $request->category_id)->get();
        }

        if ($request->has('sub_category_id') && !empty($request->sub_category_id)) {
            $query->where('products.sub_category_id', $request->sub_category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->whereDate('products.is_active', $request->is_active);
        }

        $datas = $query->paginate(20);                
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $categories = Category::orderBy('name')->where('is_active', 1)->get();        
        $brands = Brand::orderBy('name')->where('is_active', 1)->get();        
        $units = Unit::orderBy('name')->where('is_active', 1)->get();        
        $suppliers = Supplier::orderBy('name')->where('is_active', 1)->get();
        $branches = Branch::orderBy('name')->where('is_active', 1)->get();

        return view('products.index', compact(
            'datas',
            'users',
            'units',
            'brands',
            'branches',
            'suppliers',
            'req_subdatas',
            'categories'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('products.create-modal');
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
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);            
        }
        
        try {
            DB::transaction(function () use($request) {            
                $data = Product::create([
                    'user_id' => auth()->id(),
                    'name' => $request->name,
                    'sku' => 'PRD-' . strtoupper(Str::random(8)),
                    'category_id' => $request->category_id,
                    'sub_category_id' => $request->sub_category_id,
                    'brand_id' => $request->brand_id,
                    'unit_id' => $request->unit_id,
                    'supplier_id' => $request->supplier_id,
                    'purchase_price' => $request->purchase_price ?? $request->selling_price,
                    'selling_price' => $request->selling_price,         
                    'is_active' => $request->is_active ? 1 : 0
                ]);
        
                //-------- update/create stock        			
                if ($request->branch_ids) {
                    foreach ($request->branch_ids as $key => $branch_id) {
                        $available = (int) ($request->available_qty[$key] ?? 0);
                        $reserved = (int) ($request->reserved_qty[$key] ?? 0);
                        $damaged = (int) ($request->damaged_qty[$key] ?? 0);
        
                        Stock::updateOrCreate([
                            'branch_id' => $branch_id, 
                            'product_id' => $data->id
                        ],[
                            'available_qty' => $available,
                            'reserved_qty' => $reserved,
                            'damaged_qty' => $damaged
                        ]);
                    }
                }
        
                //--------- then update product current quantity
                $name = strtoupper($data->name);
                $prefix = collect(explode(' ', $name))->map(fn($word) => Str::substr($word, 0, 1))->implode('');
                $productTotal = Stock::where('product_id', $data->id)->sum(DB::raw('available_qty + reserved_qty + damaged_qty'));        
                $newSku = $prefix ?: 'PRD';
        
                if (Product::where('sku', $newSku)->where('id', '!=', $data->id)->exists()) {
                    $newSku .= $data->id;
                }
        
                $data->update([
                    'sku' => $newSku,
                    'stock_qty' => $productTotal
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
    public function edit($id)
    {
        return view('products.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $id = $request->id;        
        $data = Product::findOrFail($id);
        if (empty($data)) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);                
            } else{
                return redirect()->back()->with('error', 'Data Info Not Found!');
            }
        }
        $validated = $request->validate([
            'name' => 'required'
        ]);

        $sku = $request->sku;
        if (!$sku) {
            $name = strtoupper($request->name);
            $prefix = collect(explode(' ', $name))->map(fn($word) => Str::substr($word, 0, 1))->implode('');
            $sku = $prefix ?: 'PRD';
            if (Product::where('sku', $sku)->where('id', '!=', $data->id)->exists()) {
                $sku .= $data->id;
            }
        } else {
            $exists = Product::where('sku', $sku)->where('id', '!=', $data->id)->exists();
            if ($exists) {
                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'SKU already exists for another product.'
                    ], 400);                    
                } else {
                    return redirect()->back()->with('error', 'SKU already exists for another product!');
                }
            }
        }

        try {            
            DB::transaction(function () use ($data, $sku, $request) {
                $data->update([
                    'name' => $request->name,
                    'sku' => $sku,
                    'category_id' => $request->category_id,
                    'sub_category_id' => $request->sub_category_id,
                    'brand_id' => $request->brand_id,
                    'unit_id' => $request->unit_id,
                    'supplier_id' => $request->supplier_id,
                    'purchase_price' => $request->purchase_price ?? $request->selling_price,
                    'selling_price' => $request->selling_price,
                    'is_active' => $request->is_active ? 1 : 0
                ]);
        
                //-------- update/create stock        			
                if ($request->branch_ids) {
                    foreach ($request->branch_ids as $key => $branch_id) {
                        $available = (int) ($request->available_qty[$key] ?? 0);
                        $reserved = (int) ($request->reserved_qty[$key] ?? 0);
                        $damaged = (int) ($request->damaged_qty[$key] ?? 0);

                        Stock::updateOrCreate([
                            'branch_id' => $branch_id,
                            'product_id' => $data->id
                        ], [
                            'available_qty' => $available,
                            'reserved_qty' => $reserved,
                            'damaged_qty' => $damaged
                        ]);
                    }
                }
        
                //--------- then update product current quantity
                $productTotal = Stock::where('product_id', $data->id)->sum(DB::raw('available_qty + reserved_qty + damaged_qty'));
                $data->update(['stock_qty' => $productTotal]);
            });
        } catch (\Throwable $th) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage()
                ]);                
            } else {
                return redirect()->back()->with('error', $th->getMessage());
            }
        }

        if (request()->ajax()) {            
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.'
            ]);
        } else {
            return redirect()->back()->with('success', 'Data updated successfully.');
        }
        
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
            $item = Product::find($request->item_id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }
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

    
    public function getProductEditModal(Request $request)
    {
        try {
            $product = $request->item_id ? Product::with('stocks')->find($request->item_id) : null;
            $users = User::orderBy('name')->where('is_super_admin', 0)->get();
            $categories = Category::orderBy('name')->where('is_active', 1)->get();
            $brands = Brand::orderBy('name')->where('is_active', 1)->get();
            $units = Unit::orderBy('name')->where('is_active', 1)->get();
            $suppliers = Supplier::orderBy('name')->where('is_active', 1)->get();
            $branches = Branch::orderBy('name')->where('is_active', 1)->get();
            $sub_categories = $product && $product->category_id ? SubCategory::where('category_id', $product->category_id)->get() : [];
            if (empty($product)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No product info found!'
                ]);
            }
            $data['modal_view'] = view('products.edit-modal', [
                'product' => $product,
                'users' => $users,
                'categories' => $categories,
                'brands' => $brands,
                'units' => $units,
                'suppliers' => $suppliers,
                'branches' => $branches,
                'sub_categories' => $sub_categories,
            ])->render();

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => 'Data Found Successfully.'
        ]);
    }
}
