<?php

namespace App\Http\Controllers;

use App\Models\EmployeeRequest;
use App\Models\PaymentSchedule;
use App\Models\ProjectDepartment;
use App\Models\RequestDisbursement;
use App\Models\RequireAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Barryvdh\DomPDF\Facade\Pdf;

class EmployeeRequestController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view employee request',    only: ['index', 'selfService']),
            new Middleware('permission:create employee request',  only: ['create', 'store']),
            new Middleware('permission:edit employee request',    only: ['edit', 'update']),
            new Middleware('permission:delete employee request',  only: ['destroy']),
            new Middleware('permission:approve employee request', only: ['approve', 'reject', 'fastTrack', 'close']),
            new Middleware('permission:disburse employee request', only: ['disburse']),
            new Middleware('permission:generate noc',            only: ['generateNoc']),
            new Middleware('permission:view employee request',   only: ['report']),
        ];
    }

    public function index(Request $request)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if ($authUser->hasRole('employee') && !$authUser->hasPermissionTo('view all employee request')) {
            $request->merge(['employee_id' => Auth::id()]);
        }

        $query = EmployeeRequest::with(['employee', 'approvedBy', 'requireAssignment', 'disbursement'])
            ->orderBy('id', 'desc');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('request_type')) {
            $query->where('request_type', $request->request_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $datas = $query->paginate(20);
        $users = User::orderBy('name')->where('status', 'active')->role('employee')->get();
        $requestTypes = EmployeeRequest::REQUEST_TYPES;
        $requireTypes = ProjectDepartment::where('status', true)->orderBy('name')->get(['id', 'name']);

        $stats = [
            'total'       => EmployeeRequest::count(),
            'pending'     => EmployeeRequest::where('status', 'pending')->count(),
            'approved'    => EmployeeRequest::where('status', 'approved')->count(),
            'fulfilled'   => EmployeeRequest::where('status', 'fulfilled')->count(),
        ];

        return view('employee-requests.index', compact('datas', 'users', 'requestTypes', 'requireTypes', 'stats'));
    }

    public function create()
    {
        $users = User::orderBy('name')->where('status', 'active')->role('employee')->get();
        $requestTypes = EmployeeRequest::REQUEST_TYPES;
        return view('employee-requests.create-modal', compact('users', 'requestTypes'));
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        if (!$authUser->hasPermissionTo('view all employee request')) {
            $request->merge(['employee_id' => Auth::id()]);
        }

        $request->validate([
            'employee_id'  => 'required|exists:users,id',
            'category'     => 'required|in:request,require',
            'request_type' => 'required|string|max:100',
            'reason'       => 'nullable|string',
            'deadline'     => 'nullable|date|required_if:category,require',
            'image'        => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx|max:5120',
        ]);

        if ($request->category === 'request') {
            $request->validate(['amount' => 'required|numeric|min:0']);
        }

        try {
            DB::beginTransaction();

            $metadata = null;
            if ($request->request_type === 'project_payment') {
                $metadata = [
                    'project_id'   => $request->meta_project_id,
                    'expense_type' => $request->meta_expense_type,
                ];
            } elseif ($request->request_type === 'travel_allowance') {
                $metadata = [
                    'destination' => $request->meta_destination,
                    'trip_from'   => $request->meta_trip_from,
                    'trip_to'     => $request->meta_trip_to,
                    'purpose'     => $request->meta_purpose,
                ];
            } elseif ($request->request_type === 'policy_acknowledgment') {
                $metadata = [
                    'policy_title' => $request->meta_policy_title,
                ];
            } elseif ($request->request_type === 'training_completion') {
                $metadata = [
                    'training_name' => $request->meta_training_name,
                    'provider'      => $request->meta_provider,
                ];
            } elseif ($request->request_type === 'emergency_fund') {
                $metadata = [
                    'emergency_type' => $request->meta_emergency_type,
                    'fast_track'     => true,
                ];
            } elseif ($request->request_type === 'noc_certificate') {
                $metadata = [
                    'document_type' => $request->meta_document_type,
                    'purpose'       => $request->meta_noc_purpose,
                ];
            } elseif ($request->request_type === 'bonus_incentive') {
                $metadata = [
                    'bonus_type' => $request->meta_bonus_type,
                    'tax_flag'   => (bool) $request->meta_tax_flag,
                ];
            } elseif ($request->request_type === 'equipment_resource') {
                $metadata = [
                    'item_name' => $request->meta_item_name,
                    'quantity'  => $request->meta_quantity,
                    'urgency'   => $request->meta_urgency,
                ];
            } elseif ($request->request_type === 'asset_return') {
                $checklist = array_filter([
                    'laptop'  => (bool) $request->meta_asset_laptop,
                    'id_card' => (bool) $request->meta_asset_id_card,
                    'keys'    => (bool) $request->meta_asset_keys,
                    'other'   => $request->meta_asset_other ?: null,
                ]);
                $metadata = ['checklist' => $checklist];
            } elseif ($request->request_type === 'performance_review') {
                $metadata = [
                    'review_period' => $request->meta_review_period,
                    'review_cycle'  => $request->meta_review_cycle,
                ];
            } elseif ($request->request_type === 'timesheet_log') {
                $metadata = [
                    'period_type'  => $request->meta_period_type,
                    'period_start' => $request->meta_period_start,
                    'period_end'   => $request->meta_period_end,
                ];
            } elseif ($request->request_type === 'office_requisition') {
                $metadata = [
                    'requisition_category' => $request->meta_req_category,
                    'estimated_cost'       => $request->meta_req_cost,
                    'vendor_suggestion'    => $request->meta_req_vendor,
                ];
            } elseif ($request->request_type === 'interior_project') {
                $metadata = [
                    'project_id'     => $request->meta_interior_project_id,
                    'material_spec'  => $request->meta_material_spec,
                    'quantity'       => $request->meta_material_qty,
                    'unit'           => $request->meta_material_unit,
                    'estimated_cost' => $request->meta_interior_cost,
                ];
            }

            if ($request->category === 'require' && $request->filled('require_type_id')) {
                $metadata = [
                    'require_type_id' => $request->require_type_id,
                    'custom_fields'   => $request->input('custom_fields', []),
                ];
            }

            $imagePath = null;
            if ($request->hasFile('image')) {
                $image      = $request->file('image');
                $imageName  = uniqid() . '.' . strtolower($image->getClientOriginalExtension());
                $uploadPath = 'image/employee-requests/';

                if (!file_exists(public_path($uploadPath))) {
                    mkdir(public_path($uploadPath), 0777, true);
                }

                $image->move(public_path($uploadPath), $imageName);
                $imagePath = $uploadPath . $imageName;
            }

            $employeeRequest = EmployeeRequest::create([
                'employee_id'  => $request->employee_id,
                'category'     => $request->category,
                'request_type' => $request->request_type,
                'amount'       => $request->amount,
                'reason'       => $request->reason,
                'image'        => $imagePath,
                'status'       => EmployeeRequest::STATUS_PENDING,
                'deadline'     => $request->deadline,
                'requested_at' => now(),
                'metadata'     => $metadata,
            ]);

            if ($request->category === 'require') {
                RequireAssignment::create([
                    'request_id'  => $employeeRequest->id,
                    'assigned_to' => $request->employee_id,
                    'assigned_by' => Auth::id(),
                    'due_date'    => $request->deadline,
                ]);

                if ($request->filled('require_type_id') && $request->amount > 0) {
                    $employee = User::find($request->employee_id);
                    PaymentSchedule::create([
                        'company_id'       => $employee?->company_id,
                        'schedulable_type' => EmployeeRequest::class,
                        'schedulable_id'   => $employeeRequest->id,
                        'type'             => 'pay',
                        'party_type'       => 'employee',
                        'party_id'         => $request->employee_id,
                        'party_name'       => $employee?->name,
                        'source_label'     => $request->request_type,
                        'amount'           => $request->amount,
                        'scheduled_date'   => $request->deadline,
                        'status'           => 'pending',
                        'created_by'       => Auth::id(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Created successfully.',
            'data'    => $employeeRequest,
        ]);
    }

    public function edit($id)
    {
        $users = User::orderBy('name')->where('status', 'active')->role('employee')->get();
        $requestTypes = EmployeeRequest::REQUEST_TYPES;
        return view('employee-requests.edit-modal', compact('id', 'users', 'requestTypes'));
    }

    public function update(Request $request, $id)
    {
        $id = $request->id ?? $id;
        $data = EmployeeRequest::findOrFail($id);

        $request->validate([
            'employee_id'  => 'required|exists:users,id',
            'category'     => 'required|in:request,require',
            'request_type' => 'required|string|max:100',
            'reason'       => 'nullable|string',
            'deadline'     => 'nullable|date|required_if:category,require',
            'image'        => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx|max:5120',
        ]);

        try {
            DB::beginTransaction();

            // Build metadata
            $metadata = $data->metadata;
            if ($request->category === 'require' && $request->filled('require_type_id')) {
                $metadata = [
                    'require_type_id' => $request->require_type_id,
                    'custom_fields'   => $request->input('custom_fields', []),
                ];
            } elseif ($request->category === 'require' && empty($request->require_type_id)) {
                // Keep existing metadata for static require types
                $metadata = $data->metadata;
            }

            // Handle image (keep existing if no new file uploaded)
            $imagePath = $data->image;
            if ($request->hasFile('image')) {
                $image      = $request->file('image');
                $imageName  = uniqid() . '.' . strtolower($image->getClientOriginalExtension());
                $uploadPath = 'image/employee-requests/';
                if (!file_exists(public_path($uploadPath))) {
                    mkdir(public_path($uploadPath), 0777, true);
                }
                $image->move(public_path($uploadPath), $imageName);
                $imagePath = $uploadPath . $imageName;
            }

            $data->update([
                'employee_id'  => $request->employee_id,
                'category'     => $request->category,
                'request_type' => $request->request_type,
                'amount'       => $request->amount,
                'reason'       => $request->reason,
                'deadline'     => $request->deadline,
                'image'        => $imagePath,
                'metadata'     => $metadata,
            ]);

            // Update or create RequireAssignment
            if ($request->category === 'require') {
                RequireAssignment::updateOrCreate(
                    ['request_id' => $data->id],
                    [
                        'assigned_to' => $request->employee_id,
                        'due_date'    => $request->deadline,
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Updated successfully.', 'data' => $data]);
        }

        return back()->with('success', 'Updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        try {
            $item = EmployeeRequest::find($request->item_id ?? $id);
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'Record not found.']);
            }
            $item->delete();
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Deleted successfully.']);
    }

    public function approve($role,Request $request, $id)
    {
        $item = EmployeeRequest::findOrFail($id);

        if (!in_array($item->status, [EmployeeRequest::STATUS_PENDING, EmployeeRequest::STATUS_UNDER_REVIEW])) {
            return response()->json(['success' => false, 'message' => 'Cannot approve at current status.']);
        }

        $item->update([
            'status'      => EmployeeRequest::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'remarks'     => $request->remarks,
        ]);

        return response()->json(['success' => true, 'message' => 'Request approved.']);
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['remarks' => 'required|string|max:500']);

        $item = EmployeeRequest::findOrFail($id);

        if (!in_array($item->status, [EmployeeRequest::STATUS_PENDING, EmployeeRequest::STATUS_UNDER_REVIEW])) {
            return response()->json(['success' => false, 'message' => 'Cannot reject at current status.']);
        }

        $item->update([
            'status'      => EmployeeRequest::STATUS_REJECTED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'remarks'     => $request->remarks,
        ]);

        return response()->json(['success' => true, 'message' => 'Request rejected.']);
    }

    public function disburse($role,Request $request, $id)
    {
        $request->validate([
            'payment_method'   => 'required|in:cash,bank_transfer,cheque,payroll_deduction',
            'disbursed_amount' => 'required|numeric|min:0.01',
            'note'             => 'nullable|string|max:1000',
            'attachment'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $item = EmployeeRequest::findOrFail($id);

        if ($item->category !== EmployeeRequest::CATEGORY_REQUEST) {
            return response()->json(['success' => false, 'message' => 'Only request-type records can be disbursed.']);
        }

        if ($item->status !== EmployeeRequest::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Request must be approved before disbursement.']);
        }

        if ($item->disbursement) {
            return response()->json(['success' => false, 'message' => 'Already disbursed.']);
        }

        try {
            DB::beginTransaction();

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('request-disbursements', 'public');
            }

            RequestDisbursement::create([
                'request_id'       => $item->id,
                'payment_method'   => $request->payment_method,
                'disbursed_amount' => $request->disbursed_amount,
                'disbursed_by'     => Auth::id(),
                'disbursed_at'     => now(),
                'attachment'       => $attachmentPath,
                'note'             => $request->note,
            ]);

            $item->update(['status' => EmployeeRequest::STATUS_FULFILLED]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Disbursement recorded. Request marked fulfilled.']);
    }

    public function fastTrack($id)
    {
        $item = EmployeeRequest::findOrFail($id);

        if ($item->request_type !== EmployeeRequest::TYPE_EMERGENCY_FUND) {
            return response()->json(['success' => false, 'message' => 'Fast-track only for Emergency Fund requests.']);
        }

        if ($item->status !== EmployeeRequest::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Request is not in pending status.']);
        }

        $item->update([
            'status'  => EmployeeRequest::STATUS_UNDER_REVIEW,
            'remarks' => 'Fast-tracked by ' . Auth::user()->name . ' on ' . now()->format('d M Y H:i'),
        ]);

        return response()->json(['success' => true, 'message' => 'Request fast-tracked to Under Review.']);
    }

    public function generateNoc($id)
    {
        $item = EmployeeRequest::with('employee')->findOrFail($id);

        if ($item->request_type !== EmployeeRequest::TYPE_NOC_CERTIFICATE) {
            abort(400, 'Not a NOC/Certificate request.');
        }

        if (!in_array($item->status, [EmployeeRequest::STATUS_APPROVED, EmployeeRequest::STATUS_FULFILLED])) {
            return response()->json(['success' => false, 'message' => 'Request must be approved before generating document.']);
        }

        $pdf = Pdf::loadView('employee-requests.noc-pdf', ['item' => $item])
            ->setPaper('a4', 'portrait');

        $filename = 'NOC_' . $item->employee?->name . '_' . $item->id . '.pdf';

        return $pdf->download(str_replace(' ', '_', $filename));
    }

    public function close($role, $id)
    {
        $item = EmployeeRequest::findOrFail($id);

        if ($item->status === EmployeeRequest::STATUS_CLOSED) {
            return response()->json(['success' => false, 'message' => 'Already closed.']);
        }

        $item->update([
            'status'  => EmployeeRequest::STATUS_CLOSED,
            'remarks' => ($item->remarks ? $item->remarks . ' | ' : '') . 'Closed by ' . Auth::user()->name . ' on ' . now()->format('d M Y'),
        ]);

        return response()->json(['success' => true, 'message' => 'Request closed.']);
    }

    public function selfService(Request $request)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        $query = EmployeeRequest::with(['requireAssignment', 'disbursement'])
            ->where('employee_id', $authUser->id)
            ->orderBy('id', 'desc');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $datas = $query->paginate(15);
        $requestTypes = EmployeeRequest::REQUEST_TYPES;
        $users = User::orderBy('name')->where('status', 'active')->role('employee')->get();

        $stats = [
            'total'    => EmployeeRequest::where('employee_id', $authUser->id)->count(),
            'pending'  => EmployeeRequest::where('employee_id', $authUser->id)->where('status', 'pending')->count(),
            'approved' => EmployeeRequest::where('employee_id', $authUser->id)->where('status', 'approved')->count(),
            'requires_action' => EmployeeRequest::where('employee_id', $authUser->id)
                ->where('category', 'require')
                ->whereIn('status', ['pending', 'approved'])
                ->count(),
        ];

        return view('employee-requests.self-service', compact('datas', 'requestTypes', 'stats', 'users'));
    }

    public function fulfill(Request $request, $id)
    {
        $item = EmployeeRequest::with('requireAssignment')->findOrFail($id);

        if ($item->category !== EmployeeRequest::CATEGORY_REQUIRE) {
            return response()->json(['success' => false, 'message' => 'Only require-type records can be fulfilled.']);
        }

        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        if ($authUser->hasRole('employee') && (int) $item->employee_id !== (int) Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.']);
        }

        try {
            DB::beginTransaction();

            $item->update(['status' => EmployeeRequest::STATUS_FULFILLED]);

            if ($item->requireAssignment) {
                $item->requireAssignment->update([
                    'fulfilled_at'     => now(),
                    'fulfillment_note' => $request->fulfillment_note,
                ]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Marked as fulfilled.']);
    }

    public function report(Request $request)
    {
        $dateFrom = $request->filled('date_from') ? $request->date_from : now()->startOfMonth()->toDateString();
        $dateTo   = $request->filled('date_to')   ? $request->date_to   : now()->toDateString();

        $base = EmployeeRequest::whereBetween('requested_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($request->filled('category')) {
            $base->where('category', $request->category);
        }

        $byStatus = (clone $base)->selectRaw('status, count(*) as total, coalesce(sum(amount),0) as total_amount')
            ->groupBy('status')
            ->orderBy('total', 'desc')
            ->get();

        $byCategory = (clone $base)->selectRaw('category, count(*) as total, coalesce(sum(amount),0) as total_amount')
            ->groupBy('category')
            ->get();

        $byType = (clone $base)->selectRaw('request_type, category, count(*) as total, coalesce(sum(amount),0) as total_amount')
            ->groupBy('request_type', 'category')
            ->orderBy('total', 'desc')
            ->get();

        $summary = [
            'total'         => (clone $base)->count(),
            'total_amount'  => (clone $base)->sum('amount'),
            'pending'       => (clone $base)->where('status', 'pending')->count(),
            'approved'      => (clone $base)->where('status', 'approved')->count(),
            'fulfilled'     => (clone $base)->where('status', 'fulfilled')->count(),
            'rejected'      => (clone $base)->where('status', 'rejected')->count(),
            'closed'        => (clone $base)->where('status', 'closed')->count(),
        ];

        $requestTypes = EmployeeRequest::REQUEST_TYPES;

        return view('employee-requests.report', compact(
            'byStatus', 'byCategory', 'byType', 'summary',
            'requestTypes', 'dateFrom', 'dateTo'
        ));
    }

    public function recover(Request $request, $id)
    {
        $request->validate([
            'deducted_amount' => 'required|numeric|min:0.01',
            'deducted_at'     => 'required|date',
            'note'            => 'nullable|string|max:500',
        ]);

        $item = EmployeeRequest::with('recoveries')->findOrFail($id);

        if (!in_array($item->request_type, ['advance_salary', 'loan'])) {
            return response()->json(['success' => false, 'message' => 'Recovery only applicable for advance salary or loan requests.']);
        }

        if ($item->status !== EmployeeRequest::STATUS_FULFILLED) {
            return response()->json(['success' => false, 'message' => 'Request must be disbursed (fulfilled) before adding recovery.']);
        }

        $totalRecovered = $item->totalRecovered();
        $remaining      = $item->amount - $totalRecovered;

        if ($request->deducted_amount > $remaining + 0.001) {
            return response()->json(['success' => false, 'message' => 'Deduction amount exceeds remaining balance of ' . number_format($remaining, 2) . '.']);
        }

        try {
            DB::beginTransaction();

            $installmentNo   = $item->recoveries()->count() + 1;
            $newRemaining    = max(0, $remaining - $request->deducted_amount);

            \App\Models\RequestRecovery::create([
                'request_id'        => $item->id,
                'payslip_id'        => $request->payslip_id,
                'installment_no'    => $installmentNo,
                'deducted_amount'   => $request->deducted_amount,
                'remaining_balance' => $newRemaining,
                'deducted_at'       => $request->deducted_at,
                'note'              => $request->note,
            ]);

            if ($newRemaining <= 0) {
                $item->update(['status' => EmployeeRequest::STATUS_CLOSED]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json([
            'success'           => true,
            'message'           => 'Recovery installment #' . $installmentNo . ' recorded.',
            'remaining_balance' => $newRemaining ?? 0,
        ]);
    }
}
