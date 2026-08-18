<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSalary;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Payment::select('payments.*')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->join('employee_salaries', 'employee_salaries.id', '=', 'payments.employee_salary_id')
            ->orderBy('users.name', 'asc')
            ->orderBy('payments.id', 'asc');
            
        $request_datas = null;
        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('payments.user_id', $request->user_id);
            $request_datas = EmployeeSalary::where('user_id', $request->user_id)->orderBy('name', 'ASC')->get();
        }
        if ($request->has('employee_salary_id') && !empty($request->employee_salary_id)) {
            $query->where('payments.employee_salary_id', $request->employee_salary_id);
        }
        if (!empty($request->payment_date)) {
            $query->whereDate('payments.payment_date', $request->payment_date);
        }
        if (!empty($request->transaction_no)) {
            $query->whereDate('payments.transaction_no', $request->transaction_no);
        }

        $datas = $query->paginate(30);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $emp_salaries = EmployeeSalary::orderBy('id')->get();

        return view('payments.index', compact(
            'datas',
            'request_datas',
            'users',
            'emp_salaries'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('payments.create-modal');
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
            'user_id' => 'required',
            'employee_salary_id' => 'required',
            'payment_date' => 'required',
            'payment_method' => 'required',
            'amount' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);            
        }

        try {            
            $data = Payment::updateOrCreate([
                'user_id' => $request->user_id,
                'employee_salary_id' => $request->employee_salary_id,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'amount' => $request->amount,
                'transaction_no' => $request->transaction_no,
                'notes' => $request->notes
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.',
            'data' => $data
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
        return view('payments.edit-modal', compact('id'));
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
        $data = Payment::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'employee_salary_id' => 'required',
            'payment_date' => 'required',
            'payment_method' => 'required',
            'amount' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        try {            
            $data->update([
                'user_id' => $request->user_id,
                'employee_salary_id' => $request->employee_salary_id,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'amount' => $request->amount,
                'transaction_no' => $request->transaction_no,
                'notes' => $request->notes
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);   
        }

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
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
            $item = Payment::find($request->item_id);
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
}
