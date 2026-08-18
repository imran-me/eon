<?php

namespace App\Http\Controllers;

use App\Models\EmployeeRequest;
use App\Models\RequireAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RequireAssignmentController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view require assignment',    only: ['index']),
            new Middleware('permission:create require assignment',  only: ['store']),
            new Middleware('permission:verify require assignment',  only: ['verify']),
            new Middleware('permission:escalate require assignment', only: ['escalate']),
        ];
    }

    public function verify(Request $request, $id)
    {
        $assignment = RequireAssignment::with('request')->findOrFail($id);

        if (!$assignment->fulfilled_at) {
            return response()->json(['success' => false, 'message' => 'Employee has not fulfilled this requirement yet.']);
        }

        $assignment->request->update([
            'status'      => EmployeeRequest::STATUS_CLOSED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'remarks'     => $request->remarks,
        ]);

        return response()->json(['success' => true, 'message' => 'Fulfillment verified and requirement closed.']);
    }

    public function escalate(Request $request, $id)
    {
        $assignment = RequireAssignment::findOrFail($id);

        $assignment->update(['escalated' => true]);

        return response()->json(['success' => true, 'message' => 'Requirement escalated.']);
    }

    public function overdueList(Request $request)
    {
        $query = RequireAssignment::with(['request.employee', 'assignedTo'])
            ->whereNull('fulfilled_at')
            ->whereDate('due_date', '<', now())
            ->orderBy('due_date');

        if ($request->ajax()) {
            return response()->json(['success' => true, 'data' => $query->paginate(20)]);
        }

        $overdue = $query->paginate(20);

        return view('employee-requests.overdue', compact('overdue'));
    }
}
