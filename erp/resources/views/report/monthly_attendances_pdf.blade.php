<!DOCTYPE html>
<html>

<head>
    <title>Attendance Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* Employee Info */
        .user-table {
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }

        .user-table td {
            padding: 10px;
            vertical-align: top;
            border-right: 1px solid #ddd;
        }

        .user-table td:last-child {
            border-right: none;
            text-align: right;
        }

        /* Attendance Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 7px;
            text-align: center;
        }

        td:first-child,
        td:nth-child(2) {
            text-align: left;
        }

        /* Status Badges */
        .status-present {
            color: #0a7c2f;
            font-weight: bold;
        }

        .status-late {
            color: #d97706;
            font-weight: bold;
        }

        .status-absent {
            color: #dc2626;
            font-weight: bold;
        }

        /* Summary */
        .summary-box {
            width: 100%;
            border: 1px solid #ddd;
            padding: 8px 10px;
            background: #fafafa;
            margin-top: 20px;
        }

        .summary-box h3 {
            margin: 0 0 6px 0;
            text-align: center;
            font-size: 13px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
        }

        .summary-table.single-row {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .summary-table.single-row td {
            padding: 4px 6px;
            white-space: nowrap;
        }

        .summary-table.single-row .label {
            color: #555;
            font-weight: 600;
            text-align: left;
        }

        .summary-table.single-row .value {
            font-weight: bold;
            text-align: right;
            padding-right: 10px;
        }

        .summary-table.single-row td:not(:last-child) {
            border-right: 1px dashed #ddd;
        }
        .clear {
            clear: both;
        }

        .note-cell {
            text-align: left;
            font-size: 11px;
        }
    </style>
</head>

<body>
    <!-- HEADER -->
    <div class="header">
        <div class="company-name">{{ $user->company?->name }}</div>
        <div>Monthly Attendance Report : {{ $month }} {{ $year }}</div>
    </div>

    <!-- USER INFO -->
    <table class="user-table">
        <tr>
            <td width="50%">
                <strong>Employee:</strong> {{ $user->name }}<br>
                <strong>Email:</strong> {{ $user->email }}
            </td>
            <td width="50%">
                <strong>Phone:</strong> {{ $user->phone ?? 'N/A' }}<br>
                <strong>Shift:</strong> {{ $user->shift?->name ?? 'N/A' }}
            </td>
        </tr>
    </table>

    @php $total_minutes = 0; @endphp

    <!-- ATTENDANCE TABLE -->
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Worked Hour</th>
                <th>Status</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($attendances as $row)
                @php
                    $worked_display = '--';
                    $dt = \Carbon\Carbon::parse($row->date);

                    if ($row->check_in && $row->check_out) {
                        $start = \Carbon\Carbon::parse($row->check_in);
                        $end = \Carbon\Carbon::parse($row->check_out);

                        if ($end->lt($start)) {
                            $end->addDay();
                        }

                        $diffInMinutes = $start->diffInMinutes($end);
                        $total_minutes += $diffInMinutes;

                        $h = floor($diffInMinutes / 60);
                        $m = $diffInMinutes % 60;
                        $worked_display = "{$h}h {$m}m";
                    } elseif ($row->check_in) {
                        $worked_display = 'Missing Out';
                    }
                @endphp

                <tr>
                    <td>{{ $dt->format('d-M-Y') }}</td>
                    <td>{{ $dt->format('l') }}</td>
                    <td>{{ $row->check_in ? \Carbon\Carbon::parse($row->check_in)->format('h:i A') : '--' }}</td>
                    <td>{{ $row->check_out ? \Carbon\Carbon::parse($row->check_out)->format('h:i A') : '--' }}</td>
                    <td>{{ $worked_display }}</td>
                    <td class="status-{{ Str::slug(preg_replace('/\s+\d+\s+mins$/', '', $row->status)) }}">{{ ucfirst($row->status) }}</td>
                    <td><small class="text-muted">@if(!empty($row->out_status)) <span style="color:red;font-weight:bold">{{ $row->out_status }}</span> @else{{ $row->note ?? '-' }} @endif</small></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $total_h = floor($total_minutes / 60);
        $total_m = $total_minutes % 60;
    @endphp

    <!-- SUMMARY -->
    <div class="summary-box">
        <h3>Attendance Summary</h3>

        <table class="summary-table compact single-row">
            <tr>
                <td class="label">Present</td>
                <td class="value">{{ $summary->present }}</td>

                <td class="label">Late</td>
                <td class="value">{{ $summary->late }}</td>
                <td class="label">Late Minutes</td>
                <td class="value">{{ $summary->late_minutes }}</td>
                <td class="label">Total Early Leave Days</td>
                <td class="value">{{ $summary->early_out }}</td>
                


                
            </tr>
            <tr>
                <td class="label">Total Early Leave Minutes</td>
                <td class="value">{{ $summary->early_minutes }}</td>
                <td class="label">Absent</td>
                <td class="value">{{ $summary->absent }}</td>
                <td class="label">Worked Hours</td>
                <td class="value">{{ "{$total_h}H {$total_m}M" }}</td>
                <td class="label">Leave</td>
                <td class="value">{{ $summary->leave }}</td>
                
                
            </tr>
            <tr>
                <td class="label">Holiday</td>
                <td class="value">{{ $summary->holiday }}</td>
                <td class="label">Overtime Days</td>
                <td class="value">{{ $summary->overtime_days }}</td>
                <td class="label">Overtime Minutes</td>
                <td class="value">{{ $summary->overtime_minutes }}</td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </div>





    <div class="clear"></div>

</body>

</html>
