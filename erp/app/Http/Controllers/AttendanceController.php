<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Attendance;
use App\Models\AttendenceSetting;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Rats\Zkteco\Lib\ZKTeco;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class AttendanceController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view attendance', only: ['index']),
            new Middleware('permission:create attendance', only: ['create', 'store']),
            new Middleware('permission:edit attendance', only: ['edit', 'update']),
            new Middleware('permission:delete attendance', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if('employee' == Str::slug(Auth::user()->getRoleNames()->first()) && !Auth::user()->hasPermissionTo('view all attendance')) {
             $request->merge(['user_id' => Auth::id()]);
        }
        $query = Attendance::select('attendances.*')
            ->leftJoin('users', 'users.id', '=', 'attendances.user_id')
            ->leftJoin('companies', 'companies.id', '=', 'attendances.company_id')
            ->leftJoin('shifts', 'shifts.id', '=', 'attendances.shift_id')
            ->leftJoin('attendence_settings', 'attendence_settings.id', '=', 'attendances.attendence_setting_id')
            // ->orderBy('users.name', 'asc')
            ->orderBy('attendances.id', 'desc');

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('attendances.user_id', $request->user_id);
        }

        if ($request->has('company_id') && !empty($request->company_id)) {
            $query->where('attendances.company_id', $request->company_id);
        }

        if ($request->has('shift_id') && !empty($request->shift_id)) {
            $query->where('attendances.shift_id', $request->shift_id);
        }

        if ($request->filled('date')) {
            $query->where('attendances.date', $request->date);
        }

        if ($request->filled('status')) {
            $query->where('attendances.status', $request->status);
        }

        $datas = $query->paginate(20);
        $users = User::orderBy('name')->role('employee')->where('status', 'active')->get();
        $companies = Company::orderBy('name')->get();
        $shifts = Shift::orderBy('name')->get();
        $attendence_settings = AttendenceSetting::orderBy('id')->get();

        return view('attendances.index', compact(
            'datas',
            'users',
            'companies',
            'shifts',
            'attendence_settings'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('attendances.create-modal');
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
            'date' => 'required',
            'status' => 'required'
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        if ($request->filled('check_in') && $request->filled('check_out')) {
            if (Carbon::parse($request->check_out)->lt(Carbon::parse($request->check_in))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Check out cannot be earlier than Check in time.'
                ]);
            }
        }

        $exist = Attendance::where('date', $request->date)->where('user_id', $request->user_id)->exists();

        if ($exist) {
            return response()->json([
                'success' => false,
                'message' => 'An Attendance already exists on this date!'
            ]);
        }

        $user = User::find($request->user_id);

        $data = Attendance::create([
            'user_id' => $request->user_id,
            'shift_id' => $request->shift_id,
            'company_id' => $user->company_id,
            'attendence_setting_id' => 1,
            'date' => $request->date,
            'check_in' => $request->check_in,
            'check_out' => $request->check_out,
            'note' => $request->note,
            'status' => $request->status
        ]);

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
        return view('attendances.edit-modal', compact('id'));
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
        $data = Attendance::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }
        $validated = $request->validate([
            'user_id' => 'required',
            // 'company_id' => 'required',
            // 'shift_id' => 'required',
            // 'date' => 'required',
            // 'status' => 'required'
        ]);

        // if ($request->filled('check_in') && $request->filled('check_out')) {
        //     if (Carbon::parse($request->check_out)->lt(Carbon::parse($request->check_in))) {
        //         return response()->json([
        //             'message' => 'Check out cannot be earlier than Check in time.'
        //         ]);
        //     }
        // }

        $data->update([
            'user_id' => $request->user_id,
            // 'company_id' => $request->company_id,
            'shift_id' => $request->shift_id,
            // 'attendence_setting_id' => $request->attendence_setting_id,
            'date' => $request->date,
            'check_in' => $request->check_in,
            'check_out' => $request->check_out,
            'note' => $request->note,
            // 'status' => $request->status
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $data
            ]);
        }

        return redirect('/super-admin/airport')->with('success', 'Data updated successfully.');
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
            $item = Attendance::find($request->item_id);
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
    public function fetchLogs()
    {
        if (!function_exists("socket_create")) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sockets extension not enabled'
            ], 500);
        }

        $zk = new ZKTeco('192.168.10.211'); // device IP

        // Try to connect
        if (!$zk->connect()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to biometric device. Check IP or device power.'
            ], 500);
        }

        // Connected successfully
        $logs = $zk->getAttendance();

        // Filter only today's records
        $today = date('Y-m-d');
        $todayLogs = array_filter($logs, function ($log) use ($today) {
            return isset($log['timestamp']) && strpos($log['timestamp'], $today) === 0;
        });

        return response()->json([
            'status' => 'success',
            'data' => array_values($todayLogs)
        ]);
    }
}
