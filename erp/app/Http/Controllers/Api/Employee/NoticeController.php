<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NoticeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $today = now()->toDateString();
        $departmentId = EmployeeProfile::where('user_id', $user->id)->value('department_id');

        $query = Notice::with('company', 'department')
            ->where('status', 'published')
            ->where(function ($query) use ($today) {
                $query->whereNull('publish_date')
                    ->orWhereDate('publish_date', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $today);
            })
            // Null scope means "all users"; non-null scope must match current user.
            ->where(function ($query) use ($user) {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $user->company_id);
            })
            ->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id');

                if ($departmentId) {
                    $query->orWhere('department_id', $departmentId);
                }
            });

        if ($request->has('title') && !empty($request->title)) {
            $query->where('title', 'like', '%'.$request->title.'%');
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('publish_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('publish_date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $datas = $query->orderBy('created_at', 'desc')->paginate(30);
        
        // $companies = Company::orderBy('name', 'asc')->get();
        // $departments = Department::orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Notices retrieved successfully.',
            'data' => $datas,
        ]);
    }
}
