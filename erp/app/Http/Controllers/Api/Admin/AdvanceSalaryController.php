<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvanceSalary;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AdvanceSalaryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if ($authUser->hasRole('employee')) {
            $request->merge(['user_id' => Auth::id()]);
        }
        $query = AdvanceSalary::select('advance_salaries.*')
            ->join('users', 'users.id', '=', 'advance_salaries.user_id')
            ->orderBy('advance_salaries.id', 'desc');

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('advance_salaries.user_id', $request->user_id);
        }

        if ($request->filled('month')) {
            $query->where('advance_salaries.month', $request->month);
        }

        if ($request->filled('status')) {
            $query->whereDate('advance_salaries.status', $request->status);
        }
        
        $datas = $query->paginate(20);
        // $users = User::orderBy('name')->where('status', 'active')->role('employee')->get();

        return response()->json([
            'success' => true,
            'message' => 'Advance salaries retrieved successfully.',
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
            'user_id' => 'required',
            'amount' => 'required',
            'month' => 'required'
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        try {
            $data = AdvanceSalary::create([
                'user_id' => $request->user_id,
                'amount' => $request->amount,                
                'month' => $request->month,                                
                'reason' => $request->reason,                                
                'status' => $request->status                                                                
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Advance salary created successfully.',
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
        $data = AdvanceSalary::findOrFail($id);

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Advance salary Info Not Found!'
            ]);
        }
        $validated = $request->validate([
            'user_id' => 'required',
            'amount' => 'required',
            'month' => 'required'
        ]);

        $data->update([
            'user_id' => $request->user_id,
            'amount' => $request->amount,
            'month' => $request->month,
            'reason' => $request->reason,
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Advance salary updated successfully.',
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
            $item = AdvanceSalary::find($id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Advance salary Info Not Found!'
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
            'message' => 'Advance salary deleted successfully.'
        ]);
    }

    public function paymentSlip($role, $id)
    {
        $data = AdvanceSalary::with('user.company')->findOrFail($id);
        $this->authorizeAdvanceSalaryAccess($data);

        return view('advance-salaries.payment-slip', compact('data'));
    }

    public function downloadPaymentSlip($role, $id)
    {
        $data = AdvanceSalary::with('user.company')->findOrFail($id);
        $this->authorizeAdvanceSalaryAccess($data);

        $logoUrl = asset($data->user->company->logo ?? 'images/site-setting/69401c60d0949.png');
        $logoData = @file_get_contents($logoUrl);

        if ($logoData === false) {
            $fallbackLogoUrl = 'https://epal.com.bd/images/site-setting/69401c60d0949.png';
            $logoData = @file_get_contents($fallbackLogoUrl);
        }

        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData ?: '');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('advance-salaries.payment-slip-pdf', compact('data', 'logoBase64'));

        $employeeName = str_replace(' ', '_', $data->user->name ?? 'Employee');

        return $pdf->download("Advance_Salary_Slip_{$employeeName}_{$data->month}.pdf");
    }

    private function authorizeAdvanceSalaryAccess(AdvanceSalary $data): void
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if ($authUser->hasRole('employee') && (int) $data->user_id !== (int) Auth::id()) {
            abort(403);
        }
    }
}
