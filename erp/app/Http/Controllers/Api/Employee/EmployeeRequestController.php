<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRequest;
use App\Models\RequestDisbursement;
use App\Models\RequireAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeRequestController extends Controller
{
    public function index(Request $request)
    {
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

        // $users = User::orderBy('name')->where('status', 'active')->role('employee')->get();
        // $requestTypes = EmployeeRequest::REQUEST_TYPES;

        // $stats = [
        //     'total'       => EmployeeRequest::count(),
        //     'pending'     => EmployeeRequest::where('status', 'pending')->count(),
        //     'approved'    => EmployeeRequest::where('status', 'approved')->count(),
        //     'fulfilled'   => EmployeeRequest::where('status', 'fulfilled')->count(),
        // ];

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully.',
            'data' => $datas,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
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
                'employee_id'  => Auth::id(),
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
                    'assigned_to' => Auth::id(),
                    'assigned_by' => Auth::id(),
                    'due_date'    => $request->deadline,
                ]);
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

    public function update(Request $request, string $id)
    {
        $data = EmployeeRequest::findOrFail($id);

        $request->validate([
            'employee_id'  => 'required|exists:users,id',
            'category'     => 'required|in:request,require',
            'request_type' => 'required|string|max:100',
            'reason'       => 'nullable|string',
            'deadline'     => 'nullable|date',
        ]);

        $data->update([
            'employee_id'  => $request->employee_id,
            'category'     => $request->category,
            'request_type' => $request->request_type,
            'amount'       => $request->amount,
            'reason'       => $request->reason,
            'deadline'     => $request->deadline,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
            'data' => $data
        ]);

    }

    public function destroy(string $id)
    {
        $item = EmployeeRequest::find($id);
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.'
            ], 404);
        }
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
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

        return response()->json([
            'success' => true,
            'message' => 'Report generated successfully.',
            'data' => [
                'by_status' => $byStatus,
                'by_category' => $byCategory,
                'by_type' => $byType,
                'summary' => $summary,
                'request_types' => $requestTypes,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
