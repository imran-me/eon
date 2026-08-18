<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceSetting;
use App\Models\DeviceUser;
use Illuminate\Support\Facades\DB;
use App\Models\EmployeeProfile;
use App\Models\SalaryTemplate;
use App\Models\User;
use App\Models\EmployeeDocument;
use App\Models\Department;
use App\Models\Company;
use App\Models\Attendance;
use App\Models\Leave as LeaveModel;
use App\Models\EmployeeSalary;
use App\Models\Loan;
use App\Models\AdvanceSalary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $role = normalizeRoleName($request->role);

        // Logic to list users with filters
        $query = User::with(['roles', 'company', 'profile.department'])->orderBy('id', 'desc')->role($role);
        
        // Apply filters
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }
        
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->input('phone') . '%');
        }
        
        if ($request->filled('company')) {
            $query->where('company_id', $request->input('company'));
        }
        
        if ($request->filled('department')) {
            $query->whereHas('profile', function ($q) {
                $q->where('department_id', request()->input('department'));
            });
        }
        
        $users = $query->paginate(20);
        
        // Get dropdown options
        // $departments = Department::orderBy('name')->get();
        // $companies = Company::orderBy('name')->get();
        
        return response()->json([
            "success" => true,
            "message" => "User data retrieved successfully.",
            "data" => $users
        ], 200);
    }

    public function search($role, Request $request)
    {
        $query = trim($request->query('q', ''));
        if ($query === '') {
            return response()->json([]);
        }

        $normalizedRole = $this->normalizeRoleName($role);

        $users = User::when($normalizedRole, function ($q) use ($normalizedRole) {
                return $q->role($normalizedRole);
            })
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json($users);
    }

    protected function normalizeRoleName($role)
    {
        $sluggedRole = Str::slug($role);

        $roleRecord = Role::all()->firstWhere(function ($roleModel) use ($sluggedRole) {
            return Str::slug($roleModel->name) === $sluggedRole;
        });

        return $roleRecord ? $roleRecord->name : $role;
    }

    public function summary($role, $user)
    {
        $employee = User::with(['company', 'profile.department', 'profile.designation'])
            ->findOrFail($user);

        if (! $employee->hasRole('employee')) {
            return redirect()->back()->with('error', 'Profile summary is only available for employee users.');
        }

        $today = Carbon::today();
        $currentYear = (int) $today->year;
        $currentMonth = (int) $today->month;

        $completedColumnIds = DB::table('columns')
            ->whereRaw("LOWER(name) REGEXP 'done|completed|complete|closed|finish|finished'")
            ->pluck('id');

        $taskTotal = DB::table('task_user')
            ->where('user_id', $employee->id)
            ->distinct()
            ->count('task_id');

        $taskCompleted = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $employee->id)
            ->whereNull('tasks.deleted_at')
            ->when($completedColumnIds->isNotEmpty(), function ($query) use ($completedColumnIds) {
                $query->whereIn('tasks.column_id', $completedColumnIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->distinct()
            ->count('task_user.task_id');

        $taskPending = max($taskTotal - $taskCompleted, 0);

        // Weekly task performance (Mon-Sun)
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $today->copy()->endOfWeek(Carbon::SUNDAY);

        $weeklyCreatedRaw = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $employee->id)
            ->whereNull('tasks.deleted_at')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('DATE(tasks.created_at) as day_date, COUNT(DISTINCT task_user.task_id) as total')
            ->groupBy('day_date')
            ->pluck('total', 'day_date');

        $weeklyCompletedRaw = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $employee->id)
            ->whereNull('tasks.deleted_at')
            ->when($completedColumnIds->isNotEmpty(), function ($query) use ($completedColumnIds) {
                $query->whereIn('tasks.column_id', $completedColumnIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->whereBetween(DB::raw('DATE(tasks.updated_at)'), [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('DATE(tasks.updated_at) as day_date, COUNT(DISTINCT task_user.task_id) as total')
            ->groupBy('day_date')
            ->pluck('total', 'day_date');

        $taskWeeklyLabels = [];
        $taskWeeklyCreatedData = [];
        $taskWeeklyCompletedData = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $dayKey = $day->toDateString();

            $taskWeeklyLabels[] = $day->format('D');
            $taskWeeklyCreatedData[] = (int) ($weeklyCreatedRaw[$dayKey] ?? 0);
            $taskWeeklyCompletedData[] = (int) ($weeklyCompletedRaw[$dayKey] ?? 0);
        }

        // Monthly task performance (current year)
        $monthlyCreatedRaw = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $employee->id)
            ->whereNull('tasks.deleted_at')
            ->whereYear('tasks.created_at', $currentYear)
            ->selectRaw('MONTH(tasks.created_at) as month_no, COUNT(DISTINCT task_user.task_id) as total')
            ->groupBy('month_no')
            ->pluck('total', 'month_no');

        $monthlyCompletedRaw = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $employee->id)
            ->whereNull('tasks.deleted_at')
            ->when($completedColumnIds->isNotEmpty(), function ($query) use ($completedColumnIds) {
                $query->whereIn('tasks.column_id', $completedColumnIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->whereYear('tasks.updated_at', $currentYear)
            ->selectRaw('MONTH(tasks.updated_at) as month_no, COUNT(DISTINCT task_user.task_id) as total')
            ->groupBy('month_no')
            ->pluck('total', 'month_no');

        $taskMonthlyLabels = [];
        $taskMonthlyCreatedData = [];
        $taskMonthlyCompletedData = [];

        for ($month = 1; $month <= 12; $month++) {
            $taskMonthlyLabels[] = Carbon::create()->month($month)->format('M');
            $taskMonthlyCreatedData[] = (int) ($monthlyCreatedRaw[$month] ?? 0);
            $taskMonthlyCompletedData[] = (int) ($monthlyCompletedRaw[$month] ?? 0);
        }

        $attendanceMonth = Attendance::where('user_id', $employee->id)
            ->whereYear('date', $currentYear)
            ->whereMonth('date', $currentMonth)
            ->selectRaw("SUM(CASE WHEN LOWER(status) IN ('present', 'late') THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'leave' THEN 1 ELSE 0 END) as leave_count")
            ->first();

        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $attendanceMonthRaw = DB::table('attendances as a')
            ->leftJoin('shifts as s', 's.id', '=', 'a.shift_id')
            ->leftJoin('attendence_settings as aset', 'aset.id', '=', 'a.attendence_setting_id')
            ->where('a.user_id', $employee->id)
            ->whereBetween('a.date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw('DATE(a.date) as day_date')
            ->selectRaw(
                "SUM(CASE
                    WHEN a.check_in IS NOT NULL
                    THEN 1 ELSE 0 END) as present_count"
            )
            ->selectRaw(
                "SUM(CASE
                    WHEN a.check_in IS NOT NULL
                        AND s.start_time IS NOT NULL
                        AND TIME_TO_SEC(a.check_in) > (TIME_TO_SEC(s.start_time) + (COALESCE(aset.time_after_checkin, 0) * 60))
                    THEN 1 ELSE 0 END) as late_count"
            )
            ->selectRaw("SUM(CASE WHEN LOWER(a.status) = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw(
                "SUM(CASE
                    WHEN a.check_in IS NOT NULL
                        AND s.start_time IS NOT NULL
                        AND TIME_TO_SEC(a.check_in) > (TIME_TO_SEC(s.start_time) + (COALESCE(aset.time_after_checkin, 0) * 60))
                    THEN (TIME_TO_SEC(a.check_in) - (TIME_TO_SEC(s.start_time) + (COALESCE(aset.time_after_checkin, 0) * 60))) / 60
                    ELSE 0 END) as late_minutes"
            )
            ->selectRaw(
                "SUM(CASE
                    WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL
                    THEN (CASE
                        WHEN TIME_TO_SEC(a.check_out) >= TIME_TO_SEC(a.check_in)
                        THEN (TIME_TO_SEC(a.check_out) - TIME_TO_SEC(a.check_in))
                        ELSE (TIME_TO_SEC(a.check_out) + 86400 - TIME_TO_SEC(a.check_in))
                    END) / 3600
                    ELSE 0 END) as working_hours"
            )
            ->groupBy('day_date')
            ->get()
            ->keyBy('day_date');

        $attendanceMonthLabels = [];
        $attendanceMonthPresentData = [];
        $attendanceMonthLateData = [];
        $attendanceMonthAbsentData = [];
        $attendanceMonthLateMinutesData = [];
        $attendanceMonthWorkingHoursData = [];

        $daysInMonth = $monthStart->diffInDays($monthEnd) + 1;
        for ($i = 0; $i < $daysInMonth; $i++) {
            $day = $monthStart->copy()->addDays($i);
            $dayKey = $day->toDateString();
            $dayRecord = $attendanceMonthRaw->get($dayKey);
            $attendanceMonthLabels[] = $day->format('M d');
            $attendanceMonthPresentData[] = (int) ($dayRecord->present_count ?? 0);
            $attendanceMonthLateData[] = (int) ($dayRecord->late_count ?? 0);
            $attendanceMonthAbsentData[] = (int) ($dayRecord->absent_count ?? 0);
            $attendanceMonthLateMinutesData[] = round((float) ($dayRecord->late_minutes ?? 0), 2);
            $attendanceMonthWorkingHoursData[] = round((float) ($dayRecord->working_hours ?? 0), 2);
        }

        $attendanceMonthTotalLateMinutes = round(array_sum($attendanceMonthLateMinutesData), 2);
        $attendanceMonthTotalWorkingHours = round(array_sum($attendanceMonthWorkingHoursData), 2);

        $leaveSummary = LeaveModel::where('user_id', $employee->id)
            ->whereYear('start_date', $currentYear)
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count")
            ->first();

        $leaveDaysUsed = LeaveModel::where('user_id', $employee->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $currentYear)
            ->get()
            ->sum(function ($leave) {
                return Carbon::parse($leave->start_date)->diffInDays(Carbon::parse($leave->end_date)) + 1;
            });

        $salaryStats = EmployeeSalary::where('user_id', $employee->id)
            ->whereYear('created_at', $currentYear)
            ->selectRaw("SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid_count")
            ->selectRaw("SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw('SUM(net_salary) as total_net_salary')
            ->first();

        $latestSalary = EmployeeSalary::where('user_id', $employee->id)
            ->latest('id')
            ->first();

        $loanStats = Loan::where('user_id', $employee->id)
            ->selectRaw("SUM(CASE WHEN status = 'Running' THEN 1 ELSE 0 END) as running_count")
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw('SUM(remaining_amount) as total_remaining')
            ->first();

        $advanceSalaryStats = AdvanceSalary::where('user_id', $employee->id)
            ->selectRaw("SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END) as pending_amount")
            ->selectRaw("SUM(CASE WHEN status = 'Approved' THEN amount ELSE 0 END) as approved_amount")
            ->selectRaw("SUM(CASE WHEN status = 'Rejected' THEN amount ELSE 0 END) as rejected_amount")
            ->first();

        return view('panel.users.summary', compact(
            'employee',
            'taskTotal',
            'taskCompleted',
            'taskPending',
            'taskWeeklyLabels',
            'taskWeeklyCreatedData',
            'taskWeeklyCompletedData',
            'taskMonthlyLabels',
            'taskMonthlyCreatedData',
            'taskMonthlyCompletedData',
            'attendanceMonth',
            'attendanceMonthLabels',
            'attendanceMonthPresentData',
            'attendanceMonthLateData',
            'attendanceMonthAbsentData',
            'attendanceMonthLateMinutesData',
            'attendanceMonthWorkingHoursData',
            'attendanceMonthTotalLateMinutes',
            'attendanceMonthTotalWorkingHours',
            'leaveSummary',
            'leaveDaysUsed',
            'salaryStats',
            'latestSalary',
            'loanStats',
            'advanceSalaryStats',
            'currentYear',
            'currentMonth'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Logic to show user creation form
        $salaryTemplates = SalaryTemplate::where('status',  1)->get();
        return view('panel.users.create', compact('salaryTemplates'));
    }

    public function documents(Request $request)
    {
        $query = User::with(['profile.department', 'profile.designation', 'company', 'employeeDocument'])
            ->role('employee')
            ->orderBy('id', 'desc');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        if ($request->filled('company')) {
            $query->where('company_id', $request->company);
        }

        if ($request->filled('department')) {
            $query->whereHas('profile', function ($q) use ($request) {
                $q->where('department_id', $request->department);
            });
        }

        $users = $query->paginate(15);
        // $departments = Department::orderBy('name')->get();
        // $companies = Company::orderBy('name')->get();
        
        return response()->json([
            "success" => true,
            "message" => "Employee documents retrieved successfully.",
            "data" => $users
        ], 200);
    }

    public function updateDocuments(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'passport_size_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'nid' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'appointment_letter' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "success" => false,
                "message" => "Validation errors",
                "errors" => $validator->errors()
            ], 422);
        }

        if (! $request->hasFile('passport_size_image') && ! $request->hasFile('nid') && ! $request->hasFile('appointment_letter')) {
            return response()->json([
                "success" => false,
                "message" => "Please upload at least one document."
            ], 422);
        }

        $employeeDocument = EmployeeDocument::firstOrCreate(['user_id' => $user->id]);

        $passportSizeImage = $request->file('passport_size_image');
        $nid = $request->file('nid');
        $appointmentLetter = $request->file('appointment_letter');

        if ($passportSizeImage instanceof UploadedFile) {
            $passportImagePath = $this->storePublicFile(
                $passportSizeImage,
                'uploads/employee-documents/' . $user->id,
                $employeeDocument->passport_size_image
            );
            $employeeDocument->passport_size_image = $passportImagePath;
        }

        if ($nid instanceof UploadedFile) {
            $nidPath = $this->storePublicFile(
                $nid,
                'uploads/employee-documents/' . $user->id,
                $employeeDocument->nid
            );
            $employeeDocument->nid = $nidPath;
        }

        if ($appointmentLetter instanceof UploadedFile) {
            $appointmentLetterPath = $this->storePublicFile(
                $appointmentLetter,
                'uploads/employee-documents/' . $user->id,
                $employeeDocument->appointment_letter
            );
            $employeeDocument->appointment_letter = $appointmentLetterPath;
        }

        $employeeDocument->save();

        return response()->json([
            "success" => true,
            "message" => "Employee documents updated successfully."
        ], 200);
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
            // User Table Validations
            'name'             => 'required|string|max:255',
            'email'            => 'required|string|email|max:255|unique:users,email',
            'phone'            => 'required|string|max:15|unique:users,phone',
            // 'device_user_id' => [
            //     'required',
            //     'string',
            //     'max:255',
            //     Rule::unique('users', 'device_user_id')
            //         ->where(fn($q) => $q->where('company_id', request('company_id'))),
            // ],
            'password'         => 'required|string|min:8|confirmed',
            'company_id'       => 'required|exists:companies,id',
            'salary_template_id' => 'required|exists:salary_templates,id',
            'shift_id'         => 'nullable|exists:shifts,id',
            'address'          => 'nullable|string|max:500',
            'roles'            => 'required|array|min:1',
            'roles.*'          => 'exists:roles,name',
            'image'            => 'nullable|max:2048',

            // EmployeeProfile Table Validations
            'department_id'    => 'required|exists:departments,id',
            'designation_id'   => 'required|exists:designations,id',
            'joining_date'     => 'nullable|date',
            'salary'           => 'nullable|numeric|min:0',
            'employment_type'  => 'required|in:full_time,part_time,contractual',
        ], [
            'device_user_id.required' => 'Device User ID is required.',
            // 'device_user_id.unique'   => 'This Device User ID is already assigned to another employee in this company.',
            'salary_template_id.required' => 'Salary Template is required.',
            'salary_template_id.exists' => 'Selected Salary Template does not exist.',
        ]);

        if ($validator->fails()) {
            return response ()->json([
                "success" => false,
                "message" => "Validation errors",
                "errors" => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 1. Create the User (Auth Table)
            $user = User::create([
                'name'            => $request->name,
                'email'           => $request->email,
                'phone'           => $request->phone,
                // 'device_user_id'  => $request->device_user_id,
                'company_id'      => $request->company_id,
                'salary_template_id' => $request->salary_template_id,
                'shift_id'        => $request->shift_id,
                'address'         => $request->address,
                'password'        => bcrypt($request->password),
                'status'       => $request->is_active ? 'active' : 'inactive',
            ]);

            // 2. Handle Image Upload (Optional: belongs to User or Profile? usually User)
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $image_name = uniqid() . '.' . strtolower($image->getClientOriginalExtension());
                $upload_path = 'image/user/';
                if (!file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }
                $success = $image->move(public_path($upload_path), $image_name);
                if ($success) {
                    if (!empty($data->image) && file_exists(public_path($data->image))) {
                        unlink(public_path($data->image));
                    }
                    $user->image = $upload_path . $image_name;
                    $user->save();
                }
            }

            // 3. Create the Employee Profile (Details Table)
            if ($request->designation_id) {
                EmployeeProfile::create([
                    'user_id'         => $user->id,
                    'department_id'   => $request->department_id,
                    'designation_id'  => $request->designation_id,
                    'joining_date'    => $request->joining_date,
                    'salary'          => $request->salary,
                    'employment_type' => $request->employment_type,
                ]);
            }

            // 4. Assign Roles (multiple roles supported)
            $roles = $request->roles ?? ['employee'];
            $user->syncRoles($roles);

            DB::commit();

            $primaryRole = Str::slug($roles[0]);
            return response()->json([
                "success" => true,
                "message" => "User and Profile created successfully."
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                "message" => "Something went wrong: " . $e->getMessage()
            ], 500);
        }
    }

    // Other methods like show, edit, update, destroy can be added similarly
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // Logic to show a specific user
        return view('panel.users.show', compact('id'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($role, $id)
    {
        // Logic to show user edit form
        $data = User::findOrFail($id);
        $deviceIds = json_decode($data->device_id, true) ?? [];
        $data->device_ids = array_map('intval', $deviceIds);
        $devices = DeviceSetting::get();
        $device_users = DeviceUser::where('user_id', $data->id)->get();
        $salaryTemplates = SalaryTemplate::where('status',  1)->get();
        return view('panel.users.edit', compact('data', 'devices', 'device_users', 'salaryTemplates'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $role, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make(
            $request->all(),
            [
                'name'             => 'required|string|max:255',
                'email'            => 'required|string|email|max:255|unique:users,email,' . $user->id,
                'phone'            => 'required|string|max:15|unique:users,phone,' . $user->id,
                // 'device_user_id' => [
                //     'required',
                //     'string',
                //     'max:255',
                //     Rule::unique('users', 'device_user_id')
                //         ->where(fn($q) => $q->where('company_id', request('company_id')))
                //         ->ignore($user->id),
                // ],
                'company_id'       => 'required|exists:companies,id',
                'salary_template_id' => 'required|exists:salary_templates,id',
                'device_id.*'     => 'exists:device_settings,id',
                // 'device_user_id' => 'required|string|regex:/^[0-9, ]+$/',
                'shift_id'         => 'nullable|exists:shifts,id',
                'roles'            => 'required|array|min:1',
                'roles.*'          => 'exists:roles,name',
                'address'          => 'nullable|string|max:500',
                'image'            => 'nullable|max:2048',
                'password'         => 'nullable|string|min:8|confirmed',

                // Profile Validations
                'department_id'    => 'required|exists:departments,id',
                'designation_id'   => 'required|exists:designations,id',
                'joining_date'     => 'nullable|date',
                'salary'           => 'nullable|numeric|min:0',
                'employment_type'  => 'required|in:full_time,part_time,contractual',
            ],
        );

        if ($validator->fails()) {
            return response()->json([
                "success" => false,
                "message" => "Validation errors",
                "errors" => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 1. Update User Basic Info
            $userData = [
                'name'            => $request->name,
                'email'           => $request->email,
                'phone'           => $request->phone,
                'device_id'      => json_encode($request->device_id),
                'employee_id_no' => $request->employee_id_no,
                'company_id'      => $request->company_id,
                'salary_template_id' => $request->salary_template_id,
                'shift_id'        => $request->shift_id,
                'address'         => $request->address,
                'status'       => $request->is_active ? 'active' : 'inactive',
            ];

            // Update password only if provided
            if ($request->filled('password')) {
                $userData['password'] = bcrypt($request->password);
            }

            $user->update($userData);

            // 2. Handle Image Update
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $image_name = uniqid() . '.' . strtolower($image->getClientOriginalExtension());
                $upload_path = 'image/user/';

                if (!file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }

                // Remove old image if it exists
                if (!empty($user->image) && file_exists(public_path($user->image))) {
                    unlink(public_path($user->image));
                }

                $image->move(public_path($upload_path), $image_name);
                $user->image = $upload_path . $image_name;
                $user->save();
            }

            // 3. Update or Create Employee Profile
            // using updateOrCreate in case a user exists but somehow lacks a profile record
            EmployeeProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'department_id'   => $request->department_id,
                    'designation_id'  => $request->designation_id,
                    'joining_date'    => $request->joining_date,
                    'salary'          => $request->salary,
                    'employment_type' => $request->employment_type,
                ]
            );

            $deviceIds = $request->device_id; // [1,2,3]
            if(!empty($deviceIds)){
                $deviceUserIds = $request->device_user_id;
                // [ 1 => 'ABC123', 2 => 'XYZ456' ]
                DeviceUser::where('user_id', $user->id)->delete();
                foreach ($deviceIds as $deviceId) {
                    $userId = $deviceUserIds[$deviceId] ?? null;
                    
                    if ($userId){
                        $deviceUser = new DeviceUser();
                        $deviceUser->user_id = $user->id;
                        $deviceUser->device_id = $deviceId;
                        $deviceUser->device_user_id = $userId;
                        $deviceUser->save();
                    }
                }
            }

            // 4. Sync Roles (supports multiple roles)
            $roles = $request->roles ?? ['employee'];
            $user->syncRoles($roles);

            DB::commit();

            return response()->json([
                "success" => true,
                "message" => "User and Profile updated successfully."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                "message" => "Update failed: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // Logic to delete a user
        // Delete user data here
        return response()->json([
            "success" => true,
            "message" => "User deleted successfully."
        ]);
    }

    private function storePublicFile(UploadedFile $file, string $directory, ?string $oldPath = null): string
    {
        $fileName = uniqid() . '_' . time() . '.' . strtolower($file->getClientOriginalExtension());

        if (! file_exists(public_path($directory))) {
            mkdir(public_path($directory), 0777, true);
        }

        if (! empty($oldPath) && file_exists(public_path($oldPath))) {
            unlink(public_path($oldPath));
        }

        $file->move(public_path($directory), $fileName);

        return trim($directory, '/') . '/' . $fileName;
    }
}
