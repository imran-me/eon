<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Supplier::select('suppliers.*')->orderBy('suppliers.name', 'asc');

        if ($request->filled('name')) {
            $query->where('suppliers.name', $request->name);
        }

        if ($request->filled('email')) {
            $query->where('suppliers.email', $request->email);
        }

        if ($request->filled('phone')) {
            $query->where('suppliers.phone', $request->phone);
        }

        if ($request->filled('is_active')) {
            $query->whereDate('suppliers.is_active', $request->is_active);
        }

        $datas = $query->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Supplier data retrieved successfully.',
            'data' => $datas
        ]);
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
            'name'     => 'required|string|max:255|unique:suppliers,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        $data = Supplier::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'is_active' => $request->is_active ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Supplier data created successfully.',
            'data' => $data
        ]);
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
        $data = Supplier::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier Info Not Found!'
            ]);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name,' . $data->id
        ]);

        $data->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'is_active' => $request->is_active ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Supplier data updated successfully.',
            'data' => $data
        ]);
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
            $item = Supplier::find($request->item_id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier Info Not Found!'
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
            'message' => 'Supplier data deleted successfully.'
        ]);
    }
}
