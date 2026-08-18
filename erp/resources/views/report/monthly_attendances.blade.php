@extends('layout.app')

@section('meta-information')
    <title>Monthly Attendance Summary</title>
@endsection

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 40px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            position: absolute;
            top: 1px;
            right: 3px;
            width: 20px;
        }

        /* Example: change active page background and text */
        span [aria-current="page"] span {
            background-color: #2563eb !important;
            background: #2563eb !important;
            color: white;
            border-color: #2563eb;
        }

        /* Scoping all styles to the parent class */
        .attendance-report-container {
            padding: 25px;
            background-color: #f4f7f9;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        /* Filter Section */
        .attendance-report-container .filter-section {
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .attendance-report-container .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .attendance-report-container .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .attendance-report-container .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }

        .attendance-report-container .form-control {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        .attendance-report-container .btn-filter {
            background-color: #4f46e5;
            color: white;
            padding: 9px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .attendance-report-container .btn-filter:hover {
            background-color: #4338ca;
        }

        /* Summary Cards */
        .attendance-report-container .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .attendance-report-container .summary-card {
            padding: 20px;
            border-radius: 10px;
            color: white;
            text-align: center;
        }

        .attendance-report-container .card-total {
            background: #64748b;
        }

        .attendance-report-container .card-present {
            background: #10b981;
        }

        .attendance-report-container .card-late {
            background: #f59e0b;
        }

        .attendance-report-container .card-absent {
            background: #ef4444;
        }

        .attendance-report-container .summary-value {
            font-size: 24px;
            font-weight: 800;
            display: block;
        }

        .attendance-report-container .summary-label {
            font-size: 12px;
            text-transform: uppercase;
            opacity: 0.9;
        }

        /* Table Section */
        .attendance-report-container .report-table-wrapper {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .attendance-report-container .table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-report-container .table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 15px;
            text-align: left;
            color: #1e293b;
            font-size: 13px;
        }

        .attendance-report-container .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        /* Badge Styles */
        .attendance-report-container .badge {
            /* padding: 4px 10px; */
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* .attendance-report-container .badge-late, */
        .attendance-report-container .badge-present {
            background: #dcfce7;
            color: #15803d;
        }

        .attendance-report-container .badge-late {
            background: #fef9c3;
            color: #a16207;
        }

        .attendance-report-container .badge-absent {
            background: #fee2e2;
            color: #b91c1c;
        }

        .attendance-report-container .badge-leave {
            background: #8632d4;
            color: #e4e0e7;
        }

        .attendance-report-container .badge-early-leave {
            background: #8632d4;
            color: #e4e0e7;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-top: 15px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }

        .user-info {
            margin-bottom: 20px;
        }

        /* Print styling */
        @media print {

            /* Hide everything in the body */
            body * {
                visibility: hidden;
            }

            .show-print {
                display: block !important;
            }

            /* Show only the report container and its children */
            .show-print,
            .attendance-report-container,
            .attendance-report-container * {
                visibility: visible !important;
            }

            /* Position the report at the very top-left of the printed page */
            .attendance-report-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
                margin: 0;
            }

            /* Hide the filter bar and action buttons during print */
            .attendance-report-container .filter-section,
            .attendance-report-container .export-actions {
                display: none !important;
            }

            /* Ensure table borders and colors print correctly */
            .attendance-report-container .table {
                border: 1px solid #000 !important;
            }
        }

        .attendance-report-container .btn-export {
            background-color: #10b981;
            /* Green color for Excel */
            color: white;
            padding: 9px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: background 0.2s;
            font-size: 14px;
        }

        .attendance-report-container .btn-export:hover {
            background-color: #059669;
        }
    </style>
    <style>
        .attendance-report-container .summary-list {
            list-style: none;
            padding: 15px;
            margin: 10px 0;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .attendance-report-container .summary-list li {
            font-size: 14px;
            color: #334155;
        }

        .attendance-report-container .summary-list li strong {
            color: #4f46e5;
        }

        .attendance-report-container .badge-holiday {
            background: #e0f2fe;
            color: #0369a1;
        }
    </style>
@endsection

@section('main-content')
    <div class="attendance-report-container">

        <div class="filter-section">
            <form class="filter-form">
                @if('employee' != \Str::slug(auth()->user()->getRoleNames()->first()))
                <div class="form-group">
                    <label>Employee / User</label>
                    <select name="user_id" class="form-control select2" style="width: 100%" required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control select2" style="width: 100%">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $selectedMonth == $m ? 'selected' : '' }}>
                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="form-group">
                    <label>Year</label>
                    <select name="year" class="form-control select2" style="width: 100%">
                        @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                            <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>
                                {{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <div>
                    <a href="{{ route('role.report.monthly-attendances', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                        class="btn btn-sm bg-secondary bg-light bg-gray"
                        style="background: #e8e8e8; width: 100px; height: 40px; line-height: 40px; text-align: center; display: inline-block; border-radius: 4px; cursor: pointer;">Reset</a>
                    <button type="submit" class="btn btn-sm"
                        style="background: #4338ca; color: white; width: 100px; height: 40px; line-height: 40px; text-align: center; display: inline-block; border-radius: 4px; cursor: pointer;">Submit</button>
                </div>
            </form>
        </div>

        <div class="attendance-report-container p-0" style="padding: 0">
            @if (request()->filled('user_id'))
                @php
                    $user = \App\Models\User::find(request()->get('user_id'));
                    $startDate = \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
                @endphp
                <div class="header show-print" style="display: none; visibility: hidden;">
                    <div class="company-name">{{ $user->company?->name }}</div>
                    <div>Monthly Attendance Report: {{ $startDate->format('F') }} {{ $selectedYear }}</div>
                </div>

                <div class="user-info show-print" style="display: none; visibility: hidden;">
                    <strong>Employee:</strong> {{ $user->name }}<br>
                    <strong>Email:</strong> {{ $user->email }}<br>
                    <strong>Phone:</strong> {{ $user->phone ?? 'N/A' }}
                </div>
            @endif
            <div class="report-table-wrapper p-0" style="padding: 0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Shift</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Worked Hour</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $total_minutes = 0;
                        @endphp
                        @forelse($attendances as $attendance)
                            @php
                                $worked_display = '--';
                                if ($attendance->check_in && $attendance->check_out) {
                                    $start = \Carbon\Carbon::parse($attendance->check_in);
                                    $end = \Carbon\Carbon::parse($attendance->check_out);

                                    // Handle overnight shifts
                                    if ($end->lt($start)) {
                                        $end->addDay();
                                    }

                                    // Calculate total minutes for this row
                                    $diffInMinutes = $start->diffInMinutes($end);
                                    $total_minutes += $diffInMinutes;

                                    // Format row display: "8h 30m"
                                    $h = floor($diffInMinutes / 60);
                                    $m = $diffInMinutes % 60;
                                    $worked_display = "{$h}h {$m}m";
                                } elseif ($attendance->check_in && !$attendance->check_out) {
                                    $worked_display = 'Missing Out';
                                }
                            @endphp
                            <tr>
                                <td><strong>{{ \Carbon\Carbon::parse($attendance->date)->format('d-M Y') }}</strong></td>
                                <td><span>{{ \Carbon\Carbon::parse($attendance->date)->format('l') }}</span></td>
                                <td>{{ $attendance->shift?->name ?? '-' }}</td>
                                <td>{{ $attendance->check_in ? \Carbon\Carbon::parse($attendance->check_in)->format('h:i:s A') : '--:--' }}
                                </td>
                                <td>{{ $attendance->check_out ? \Carbon\Carbon::parse($attendance->check_out)->format('h:i:s A') : '--:--' }}
                                </td>
                                <td>{{ $worked_display }}</td>
                                <td>
                                    <span class="badge badge-{{ Str::slug(preg_replace('/\s+\d+\s+mins$/', '', $attendance->status)) }}">
                                        {{ ucfirst($attendance->status) }}
                                    </span>
                                </td>
                                <td><small class="text-muted">@if(!empty($attendance->out_status)) <span style="color:red;font-weight:bold">{{ $attendance->out_status }}-{{ $attendance->note ?? '-' }}</span> @else{{ $attendance->note ?? '-' }} @endif</small></td>
                                <td>
                                    @php
                                        $attendance_details = App\Models\AttendanceLog::where('user_id', $userId)->where('date', $attendance->date)->first();
                                    @endphp
                                    @if(!$attendance_details)
                                        <span class="text-muted">N/A</span>
                                    @else
                                    <a href="{{ route('role.report.attendance.details', [
                                        'role' => Str::slug(Auth::user()->getRoleNames()->first()),
                                        'attendance_id' => $userId,
                                        'date' => $attendance->date,
                                    ]) }}"
                                        class="btn btn-sm btn-primary"><i class="fa fa-area-chart"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">Select an employee to generate report.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($attendances->isNotEmpty())
                    @php
                        $total_h = floor($total_minutes / 60);
                        $total_m = $total_minutes % 60;
                    @endphp
                    <div class="footer-summary-wrapper" style="padding: 20px;">
                        <h4 style="margin-bottom: 10px; font-size: 18px; font-weight: bolder">Total Summary</h4>
                        <ul class="summary-list">
                            <li>Total Days: <span>{{ $summary['total_days'] }}</span></li>
                            <li>Total Present: <span>{{ $summary['present'] }}</span></li>
                            <li>Total Absent: <span>{{ $summary['absent'] }}</span></li>
                            <li>Total Late Days: <span>{{ $summary['late'] }}</span></li>
                            <li>Total Late Minutes: <span>{{ $summary['late_minutes'] }} mins</span></li>
                            <li>Total Early Leave: <span>{{ $summary['early_out'] }}</span></li>
                            <li>Total Early Leave Minutes: <span>{{ $summary['early_minutes'] }} mins</span></li>
                            <li>Total Worked Hours: <span>{{ "{$total_h}H {$total_m}M" }}</span></li>
                            <li>Total Leave: <span>{{ $summary['leave'] }}</span></li>
                            <li>Total Holiday: <span>{{ $summary['holiday'] }}</span></li>
                            <li>Total Overtime Days: <span>{{ $summary['overtime_days'] }}</span></li>
                            <li>Total Overtime Min : <span>{{ $summary['overtime_minutes'] }} mins</span></li>
                        </ul>
                        <div class="export-actions" style="margin-top: 20px;">
                            <a href="{{ route('export.monthly.attendance.excel', request()->all()) }}"
                                class="btn-export"><i class="fa-solid fa-file-excel" style="margin-right: 3px"></i>
                                Excel</a>
                            <a href="{{ route('export.monthly.attendance.pdf', request()->all()) }}" class="btn-export"
                                style="background: #ef4444;"><i class="fa-solid fa-file-pdf" style="margin-right: 3px"></i>
                                PDF</a>
                            <a href="javascript:void(0)" onclick="printBody()" class="btn-export"
                                style="background: #44b9ef;"><i class="fa-solid fa-print" style="margin-right: 3px"></i>
                                Print</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection

@section('raw-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
        });

        function printBody() {
            window.print();
        }
    </script>
@endsection
