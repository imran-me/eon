{{--
    Page 3 of the payslip — the whole month's attendance, one row per day, on a
    single page.

    Read from the frozen snapshot (employee_salaries.summary_snapshot ->
    attendance_days), so re-downloading this payslip next year shows the month
    exactly as it stood when the payslip was issued, not as the attendance table
    has been edited since.

    The footer counts the rows printed above it — see
    AttendanceMonthService::summaryFromRows() for why the month-walk's own
    tallies are not used here.

    Expects: $data (EmployeeSalary)
--}}
@php
    $msDays = $data->summary_snapshot['attendance_days'] ?? null;
    $msDaysOk = is_array($msDays) && ($msDays['available'] ?? false) === true;
@endphp

@if ($msDaysOk)
    @php
        $msDayRows = $msDays['rows'] ?? [];
        $msDaySum = $msDays['summary'] ?? [];

        $msMonthName = '';
        try {
            $msMonthName = \Carbon\Carbon::createFromDate((int) $data->year, (int) $data->month, 1)->format('F Y');
        } catch (\Throwable $e) {
            $msMonthName = $data->month . '/' . $data->year;
        }

        $msWorked = 0;
        $msHm = fn ($m) => intdiv(max(0, (int) $m), 60) . 'H ' . (max(0, (int) $m) % 60) . 'M';
    @endphp

    <div class="ms-wrap">
        <div class="ms-title">Attendance — {{ $msMonthName }}</div>

        <table class="ms-table ms-att" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <th class="ms-th" align="left">Date</th>
                <th class="ms-th" align="left">Day</th>
                <th class="ms-th" align="left">Check In</th>
                <th class="ms-th" align="left">Check Out</th>
                <th class="ms-th" align="right">Worked</th>
                <th class="ms-th" align="left">Status</th>
                <th class="ms-th" align="left">Note</th>
            </tr>

            @foreach ($msDayRows as $msD)
                @php
                    $msDt = \Carbon\Carbon::parse($msD['date']);
                    $msWd = '--';

                    if (!empty($msD['check_in']) && !empty($msD['check_out'])) {
                        $msIn = \Carbon\Carbon::parse($msD['date'] . ' ' . $msD['check_in']);
                        $msOutT = \Carbon\Carbon::parse($msD['date'] . ' ' . $msD['check_out']);
                        if ($msOutT->lt($msIn)) {
                            $msOutT->addDay();
                        }
                        $msMin = (int) $msIn->diffInMinutes($msOutT);
                        $msWorked += $msMin;
                        $msWd = intdiv($msMin, 60) . 'h ' . ($msMin % 60) . 'm';
                    } elseif (!empty($msD['check_in'])) {
                        $msWd = 'Missing Out';
                    }

                    $msStatus = strtolower(trim((string) $msD['status']));
                    $msCls = str_starts_with($msStatus, 'late') ? 'ms-st-late'
                        : ($msStatus === 'absent' ? 'ms-st-absent'
                        : ($msStatus === 'holiday' ? 'ms-st-holiday'
                        : (in_array($msStatus, ['leave', 'early leave'], true) ? 'ms-st-leave' : 'ms-st-present')));
                @endphp
                <tr>
                    <td class="ms-td">{{ $msDt->format('d M') }}</td>
                    <td class="ms-td">{{ $msDt->format('D') }}</td>
                    <td class="ms-td">{{ !empty($msD['check_in']) ? \Carbon\Carbon::parse($msD['check_in'])->format('h:i A') : '--' }}</td>
                    <td class="ms-td">{{ !empty($msD['check_out']) ? \Carbon\Carbon::parse($msD['check_out'])->format('h:i A') : '--' }}</td>
                    <td class="ms-td" align="right">{{ $msWd }}</td>
                    <td class="ms-td {{ $msCls }}">{{ ucfirst($msD['status']) }}</td>
                    <td class="ms-td ms-att-note">
                        @if (!empty($msD['out_status']))
                            <span class="ms-minus">{{ $msD['out_status'] }}</span>
                        @else
                            {{ $msD['note'] ?: '-' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>

        <table class="ms-table" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <th class="ms-th" align="left" colspan="6">Month totals</th>
            </tr>
            <tr>
                <td class="ms-td">Working days <strong>{{ $msDaySum['total_days'] ?? 0 }}</strong></td>
                <td class="ms-td">Present <strong>{{ $msDaySum['present'] ?? 0 }}</strong></td>
                <td class="ms-td">Absent <strong>{{ $msDaySum['absent'] ?? 0 }}</strong></td>
                <td class="ms-td">Leave <strong>{{ $msDaySum['leave'] ?? 0 }}</strong></td>
                <td class="ms-td">Holiday <strong>{{ $msDaySum['holiday'] ?? 0 }}</strong></td>
                <td class="ms-td">Worked <strong>{{ $msHm($msWorked) }}</strong></td>
            </tr>
            <tr>
                <td class="ms-td ms-last">Late days <strong>{{ $msDaySum['late'] ?? 0 }}</strong></td>
                <td class="ms-td ms-last">Late mins <strong>{{ $msDaySum['late_minutes'] ?? 0 }}</strong></td>
                <td class="ms-td ms-last">Early leave <strong>{{ $msDaySum['early_out'] ?? 0 }}</strong></td>
                <td class="ms-td ms-last">Early mins <strong>{{ $msDaySum['early_minutes'] ?? 0 }}</strong></td>
                <td class="ms-td ms-last">OT days <strong>{{ $msDaySum['overtime_days'] ?? 0 }}</strong></td>
                <td class="ms-td ms-last">OT mins <strong>{{ $msDaySum['overtime_minutes'] ?? 0 }}</strong></td>
            </tr>
        </table>

        <div class="ms-note">
            Attendance as recorded when this payslip was issued. The deductions on page 1 were
            calculated from this month.
        </div>
    </div>
@endif
