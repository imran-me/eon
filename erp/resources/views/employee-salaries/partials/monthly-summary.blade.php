{{--
    Monthly attendance + task summary that accompanies the payslip.

    Rendered in two places from one file so the PDF and the web view can never
    drift apart:
      - employee-salaries/pdf.blade.php   (page 2, after the payslip)
      - employee-salaries/view.blade.php  (top of the signed landing page)

    Everything here comes from employee_salaries.summary_snapshot, frozen when
    the payslip was issued. Nothing is recomputed at render time — see the
    migration note on that column.

    Markup is table-based with self-contained inline-ish styles because DomPDF
    has no flexbox/grid and does not inherit the host page's stylesheet
    reliably. All classes are `ms-` prefixed so neither host view's CSS can
    reach into this block or be reached by it.

    Expects: $data (EmployeeSalary)
--}}
@php
    $msSnap = is_array($data->summary_snapshot ?? null) ? $data->summary_snapshot : null;
    $msAtt = $msSnap['attendance'] ?? [];
    $msTask = $msSnap['tasks'] ?? [];

    $msShowAtt = ($msAtt['available'] ?? false) === true;
    // Employees whose work never reaches a task board get no task block at all
    // rather than a row of zeros beside their salary.
    $msShowTask = ($msTask['tracked'] ?? false) === true;

    $msAmounts = $msAtt['amounts'] ?? [];

    $msMonthLabel = '';
    try {
        $msMonthLabel = \Carbon\Carbon::createFromDate((int) $data->year, (int) $data->month, 1)->format('F Y');
    } catch (\Throwable $e) {
        $msMonthLabel = trim(($data->month ?? '') . '/' . ($data->year ?? ''), '/');
    }

    $msHm = function ($minutes) {
        $minutes = max(0, (int) $minutes);
        return intdiv($minutes, 60) . 'H ' . ($minutes % 60) . 'M';
    };

    $msDays = fn ($n) => ((int) $n) . ((int) $n === 1 ? ' day' : ' days');

    // Only print a taka figure when this line actually moved money.
    $msMoney = function ($value, $sign = '-') {
        $value = (float) ($value ?? 0);
        if ($value <= 0) {
            return '';
        }
        return $sign . number_format($value);
    };
@endphp

@if ($msShowAtt || $msShowTask)
    <div class="ms-wrap">
        <div class="ms-title">Monthly Attendance &amp; Work Summary — {{ $msMonthLabel }}</div>

        @if ($msShowAtt)
            <table class="ms-table" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <th class="ms-th" align="left" width="46%">Attendance</th>
                    <th class="ms-th" align="right" width="30%">Count</th>
                    <th class="ms-th" align="right" width="24%">Effect on this payslip</th>
                </tr>
                <tr>
                    <td class="ms-td">Working days in month</td>
                    <td class="ms-td" align="right">{{ $msAtt['total_days'] }}</td>
                    <td class="ms-td" align="right"></td>
                </tr>
                <tr>
                    <td class="ms-td">Present</td>
                    <td class="ms-td" align="right">{{ $msDays($msAtt['present']) }}</td>
                    <td class="ms-td" align="right"></td>
                </tr>
                <tr>
                    <td class="ms-td">Absent</td>
                    <td class="ms-td" align="right">{{ $msDays($msAtt['absent']) }}</td>
                    <td class="ms-td ms-minus" align="right">{{ $msMoney($msAmounts['absent'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td class="ms-td">Late arrival</td>
                    <td class="ms-td" align="right">{{ $msDays($msAtt['late']) }} ({{ $msAtt['late_minutes'] }} mins)</td>
                    <td class="ms-td ms-minus" align="right">{{ $msMoney($msAmounts['late'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td class="ms-td">Early leave</td>
                    <td class="ms-td" align="right">{{ $msDays($msAtt['early_out']) }} ({{ $msAtt['early_minutes'] }} mins)</td>
                    <td class="ms-td ms-minus" align="right">{{ $msMoney($msAmounts['early_out'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td class="ms-td">Approved leave</td>
                    <td class="ms-td" align="right">{{ $msDays($msAtt['leave']) }}</td>
                    <td class="ms-td ms-minus" align="right">{{ $msMoney($msAmounts['leave'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td class="ms-td">Holiday</td>
                    <td class="ms-td" align="right">{{ $msDays($msAtt['holiday']) }}</td>
                    <td class="ms-td" align="right"></td>
                </tr>
                <tr>
                    <td class="ms-td">Overtime</td>
                    <td class="ms-td" align="right">{{ $msDays($msAtt['overtime_days']) }} ({{ $msAtt['overtime_minutes'] }} mins)</td>
                    <td class="ms-td ms-plus" align="right">{{ $msMoney($msAmounts['overtime'] ?? 0, '+') }}</td>
                </tr>
                <tr>
                    <td class="ms-td ms-last">Total worked hours</td>
                    <td class="ms-td ms-last" align="right">{{ $msHm($msAtt['worked_minutes'] ?? 0) }}</td>
                    <td class="ms-td ms-last" align="right"></td>
                </tr>
            </table>
        @endif

        @if ($msShowTask)
            <table class="ms-table" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <th class="ms-th" align="left" width="46%">Tasks</th>
                    <th class="ms-th" align="right" width="54%" colspan="2">Count</th>
                </tr>
                <tr>
                    <td class="ms-td">Completed this month</td>
                    <td class="ms-td" align="right" colspan="2">{{ $msTask['completed'] }}</td>
                </tr>
                <tr>
                    <td class="ms-td">&nbsp;&nbsp;&nbsp;on or before due date</td>
                    <td class="ms-td" align="right" colspan="2">{{ $msTask['completed_on_time'] }}</td>
                </tr>
                <tr>
                    <td class="ms-td">&nbsp;&nbsp;&nbsp;after due date</td>
                    <td class="ms-td" align="right" colspan="2">{{ $msTask['completed_late'] }}</td>
                </tr>
                <tr>
                    <td class="ms-td">Still open at month end</td>
                    <td class="ms-td" align="right" colspan="2">{{ $msTask['open'] }}</td>
                </tr>
                <tr>
                    <td class="ms-td ms-last">Overdue at month end</td>
                    <td class="ms-td ms-last" align="right" colspan="2">{{ $msTask['overdue'] }}</td>
                </tr>
            </table>

            @if (!empty($msTask['by_status']))
                <div class="ms-status">
                    <span class="ms-status-label">By status:</span>
                    @foreach ($msTask['by_status'] as $msName => $msCount)
                        <span class="ms-chip">{{ strtoupper($msName) }}: {{ $msCount }}</span>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="ms-note">
            These figures were recorded when this payslip was issued and do not change
            afterwards. For the day-by-day breakdown see Report &rsaquo; Monthly Attendance
            and Report &rsaquo; Employee Task Report.
        </div>
    </div>
@endif
