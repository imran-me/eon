<?php

namespace App\Http\Controllers;

use App\Models\ExpenseReimbursement;
use App\Services\EmployeeReimbursementService;
use Illuminate\Http\Request;

/**
 * Paying staff back for money they spent out of their own pocket.
 *
 * The other end of a reimbursable expense: ExpenseController records that the
 * company owes, this records that it paid. Both meet on the payable account, and
 * neither stores a balance — what is outstanding is always read from the ledger.
 */
class EmployeeReimbursementController extends Controller
{
    public function __construct(private EmployeeReimbursementService $reimbursements)
    {
    }

    /**
     * There is no reimbursements page of its own any more.
     *
     * What the company owes staff now sits on the petty cash desk, next to what
     * staff are holding for the company — the same relationship read from both
     * ends, which is how anyone actually thinks about it. This route stays as a
     * redirect so a bookmark or an old link still lands somewhere useful.
     */
    public function index(Request $request)
    {
        return redirect()->route('role.petty-cash.index', [
            'role' => $request->route('role'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('reimburse employee'), 403, 'You are not allowed to pay staff reimbursements.');

        $data = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'company_id' => 'required|exists:companies,id',
            'amount'     => 'required|numeric|min:0.01',
            'bank_id'    => 'nullable|exists:banks,id',
            'paid_on'    => 'required|date',
            'note'       => 'nullable|string|max:255',
        ], [
            'amount.min' => 'A reimbursement of nothing is not a payment. Enter the amount handed over.',
        ]);

        // A company-locked user settles their own company's debts and no one
        // else's, whatever the form posted.
        if (!auth()->user()->can('view all expense') && !empty(auth()->user()->company_id)) {
            $data['company_id'] = auth()->user()->company_id;
        }

        try {
            $payment = $this->reimbursements->pay($data);
        } catch (\Throwable $e) {
            return $this->respond($request, false, $e->getMessage());
        }

        return $this->respond(
            $request,
            true,
            'Paid ৳' . number_format($payment->amount, 2) . ' to ' . ($payment->user->name ?? 'staff')
                . '. They are now owed ৳' . number_format(
                    $this->reimbursements->owedTo($payment->user_id, $payment->company_id), 2
                ) . '.'
        );
    }

    /**
     * Undo a payment — by writing the opposite entry, not by deleting the first.
     */
    public function reverse(Request $request, $role, ExpenseReimbursement $reimbursement)
    {
        abort_unless(auth()->user()->can('reimburse employee'), 403, 'You are not allowed to change staff reimbursements.');

        if (!auth()->user()->can('view all expense')
            && !empty(auth()->user()->company_id)
            && (int) $reimbursement->company_id !== (int) auth()->user()->company_id) {
            abort(403, 'That payment belongs to a different company.');
        }

        try {
            $this->reimbursements->reverse($reimbursement);
        } catch (\Throwable $e) {
            return $this->respond($request, false, $e->getMessage());
        }

        return $this->respond($request, true, 'Payment reversed. The ledger carries both the payment and its reversal, and the balance is owed again.');
    }

    private function respond(Request $request, bool $ok, string $message)
    {
        if ($request->ajax()) {
            return response()->json(['success' => $ok, 'message' => $message]);
        }

        return redirect()->back()->with($ok ? 'success' : 'error', $message);
    }
}
