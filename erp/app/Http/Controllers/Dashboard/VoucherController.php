<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Voucher;
use App\Models\VoucherDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $datas = Voucher::with('details.chartOfAccount')
            ->orderBy('date', 'desc')
            ->paginate(15);

        // Assuming you have a ChartOfAccount model for the dropdowns
        $accounts = Account::all();

        return view('vouchers.index', compact('datas', 'accounts'));
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $voucher = Voucher::create([
                'voucher_no'   => $this->generateVoucherNo($request->type),
                'date'         => $request->date,
                'type'         => $request->type,
                'narration'    => $request->narration,
                'total_amount' => $request->total_amount,
                'created_by'   => Auth::id(),
            ]);

            foreach ($request->details as $detail) {
                VoucherDetail::create([
                    'voucher_id'           => $voucher->id,
                    'account_id'  => $detail['account_id'],
                    'debit'                => $detail['debit'] ?? 0,
                    'credit'               => $detail['credit'] ?? 0,
                    'description'          => $detail['description'],
                ]);
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function update(Request $request)
    {
        // 1. Validation
        $request->validate([
            'item_id' => 'required|exists:vouchers,id',
            'date' => 'required|date',
            'type' => 'required',
            'details' => 'required|array|min:1',
        ]);

        try {
            DB::beginTransaction();

            // 2. Update the main Voucher header
            $voucher = Voucher::findOrFail($request->item_id);
            $voucher->update([
                'date'         => $request->date,
                'type'         => $request->type,
                'narration'    => $request->narration,
                'total_amount' => $request->total_amount,
            ]);

            // 3. Delete old details and insert new ones
            // This is safer than trying to "match" IDs for updates
            $voucher->details()->delete();

            foreach ($request->details as $row) {
                $voucher->details()->create([
                    'chart_of_account_id' => $row['account_id'],
                    'debit'               => $row['debit'] ?? 0,
                    'credit'              => $row['credit'] ?? 0,
                    'description'         => $row['description'],
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Voucher updated!']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        $voucher = Voucher::find($request->item_id);
        if ($voucher && $voucher->delete()) {
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false]);
    }

    private function generateVoucherNo($type)
    {
        $prefix = strtoupper(substr($type, 0, 2));
        return $prefix . '-' . date('Ymd') . '-' . rand(1000, 9999);
    }
}
