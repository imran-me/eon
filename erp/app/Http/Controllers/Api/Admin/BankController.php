<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BankController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Bank::select('banks.*')->orderBy('banks.name', 'asc');

        if ($request->filled('name')) {
            $query->where('banks.name', $request->name);
        }

        if ($request->filled('status')) {
            $query->whereDate('banks.status', $request->status);
        }

        $datas = $query->paginate(20);

        // $accounts = Account::active()->where('type','asset')->get();
        // $companies = \App\Models\Company::all();

        return response()->json([
            'success' => true,
            'message' => 'Bank Data Retrieved Successfully.',
            'data' => $datas,
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
            'name' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'branch_name' => 'nullable|string|max:255',
            'account_id' => 'required',
            'account_name' => 'required|string|max:255',
            'account_type' => 'required|in:savings,current,fixed',
            'bank_type' => 'required|in:national,international',
            'type' => 'required|in:bank,mobile_banking,digital_wallet',
            'routing_number' => 'nullable|string|max:20',
            'account_number' => 'required|string|max:20|unique:banks,account_number',
            'iban' => 'nullable|string|max:34',
            'swift_code' => 'nullable|string|max:11',
            // 'currency' => 'required|string|max:10',
            'address' => 'nullable|string|max:255',
            'balance' => 'required|numeric|min:0',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        DB::beginTransaction();

        try {
            $data = Bank::create([
                'name' => $request->name,
                'company_id' => $request->company_id,
                'branch_name' => $request->branch_name,
                'account_id' => $request->account_id,
                'account_name' => $request->account_name,
                'account_type' => $request->account_type,
                'bank_type' => $request->bank_type,
                'type' => $request->type,
                'routing_number' => $request->routing_number,
                'account_number' => $request->account_number,
                'iban' => $request->iban,
                'swift_code' => $request->swift_code,
                'currency' => 'BDT', // Set default currency to BDT
                'address' => $request->address,
                'balance' => $request->balance,
                'last_transaction_date' => $request->last_transaction_date,
                'last_transaction_amount' => $request->last_transaction_amount,
                'last_transaction_type' => $request->last_transaction_type,
                'created_by' => auth()->id(),
                'status' => $request->status ? 1 : 0
            ]);

            $account  = new Account();
            $account->name = $request->name;
            $account->code = substr($request->account_number, -4);
            $account->type = 'asset';
            $account->parent_id = $request->account_id;
            $account->save();

            $data->account_id = $account->id;
            $data->save();

            if ($data && $request->balance > 0) {
                $equityAccount = Account::where('code', config('accounts.opening_balance_equity'))->first();

                if (!$equityAccount) {
                    throw new \Exception('Equity account not found for journal offset.');
                }
                $journal = JournalEntry::create([
                    'company_id' => auth()->user()->company_id ?? 1,
                    'created_by' => auth()->id(),
                    'date' => now(),
                    'reference' => 'OPENING-BANK-' . $data->name,
                    'source' => 'bank',
                    'source_id' => $data->id,
                    'description' => 'Bank Opening Balance'
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $account->id,
                    'debit' => $request->balance,
                    'credit' => 0,
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $equityAccount->id,
                    'debit' => 0,
                    'credit' => $request->balance,
                ]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bank data created successfully.',
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
        $data = Bank::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Bank Data Not Found!'
            ]);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'branch_name' => 'nullable|string|max:255',
            'account_id' => 'required',
            'account_name' => 'required|string|max:255',
            'account_type' => 'required|in:savings,current,fixed',
            'bank_type' => 'required|in:national,international',
            'type' => 'required|in:bank,mobile_banking,digital_wallet',
            'routing_number' => 'nullable|string|max:20',
            'account_number' => 'required|string|max:20|unique:banks,account_number,' . $data->id,
            'iban' => 'nullable|string|max:34',
            'swift_code' => 'nullable|string|max:11',
            'currency' => 'required|string|max:10',
            'address' => 'nullable|string|max:255',
            'balance' => 'required|numeric|min:0',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        $data->update([
            'name' => $request->name,
            'company_id' => $request->company_id,
            'branch_name' => $request->branch_name,
            'account_id' => $request->account_id,
            'account_name' => $request->account_name,
            'account_type' => $request->account_type,
            'bank_type' => $request->bank_type,
            'type' => $request->type,
            'routing_number' => $request->routing_number,
            'account_number' => $request->account_number,
            'iban' => $request->iban,
            'swift_code' => $request->swift_code,
            'currency' => $request->currency,
            'address' => $request->address,
            'balance' => $request->balance,
            'last_transaction_date' => $request->last_transaction_date,
            'last_transaction_amount' => $request->last_transaction_amount,
            'last_transaction_type' => $request->last_transaction_type,
            'updated_by' => auth()->id(),
            'status' => $request->status ? 1 : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bank data updated successfully.',
            'data' => $data
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $item = Bank::find($id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank Data Not Found!'
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
            'message' => 'Bank Data deleted successfully.'
        ]);
    }

    public function getEmployeeSalary(Request $request)
    {
        try {
            $data = Bank::where('user_id', $request->user_id)->orderBy('id', 'ASC')->get();
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => 'Bank Data Retrieved Successfully.'
        ]);
    }
}
