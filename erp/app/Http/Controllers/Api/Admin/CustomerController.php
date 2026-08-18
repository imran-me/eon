<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;


class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Customer::select('customers.*')
            // ->leftJoin('users', 'users.id', '=', 'customers.user_id')
            ->leftJoin('companies', 'companies.id', '=', 'customers.company_id')
            // ->orderBy('users.name', 'asc')
            ->orderBy('companies.name', 'asc')
            ->orderBy('customers.name', 'asc');

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('customers.user_id', $request->user_id);
        }

        if ($request->has('company_id') && !empty($request->company_id)) {
            $query->where('customers.company_id', $request->company_id);
        }

        if ($request->filled('name')) {
            $query->where('customers.name', $request->name);
        }

        if ($request->filled('is_active')) {
            $query->whereDate('customers.is_active', $request->is_active);
        }

        $datas = $query->paginate(20);
        // $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        // $companies = Company::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Customer data retrieved successfully.',
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
            'name' => 'required'
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        $data = Customer::create([
            'user_id' => auth()->id(),
            'company_id' => $request->company_id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'is_active' => $request->is_active ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer data created successfully.',
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
    public function update(Request $request,$role, $id)
    {
        DB::beginTransaction();

        try {

            $data = Customer::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required'
            ]);

            $data->update([
                'company_id' => $request->company_id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'is_active' => $request->is_active ? 1 : 0
            ]);

            if ($request->has('role') && !empty($request->role)) {
                $user = User::firstOrCreate(
                    ['email' => $request->email],
                    [
                        'name' => $request->name,
                        'phone' => $request->phone,
                        'address' => $request->address,
                        'password' => Hash::make($request->password ?? '1234568')
                    ]
                );

                // Assign Role
                $user->syncRoles($request->role);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer data updated successfully.',
                'data' => $data
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Customer data failed to update.'
            ]);
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
            $item = Customer::find($request->item_id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer info not found!'
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
            'message' => 'Customer data deleted successfully.'
        ]);
    }
}
