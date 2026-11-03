{{--
    Page 2 of the payslip — every payroll month for this employee, with what is
    still owed on each.

    Read from the frozen snapshot and dated "as at", never recomputed. A PDF is
    saved and forwarded; a balance inside one can only honestly describe the
    moment it was written. The signed view page carries the live figures.

    Expects: $data (EmployeeSalary)
--}}
@php
    $msHist = $data->summary_snapshot['payroll_history'] ?? null;
    $msHistOk = is_array($msHist) && ($msHist['available'] ?? false) === true;
@endphp

@if ($msHistOk)
    @php
        $msRows = $msHist['rows'] ?? [];
        $msTot = $msHist['totals'] ?? [];
        $msAsAt = $msHist['as_at'] ?? null;
    @endphp

    <div class="ms-wrap">
        <div class="ms-title">
            Payroll History
            <span class="ms-asof">— as at {{ $msAsAt ? \Carbon\Carbon::parse($msAsAt)->format('d M Y') : '' }}</span>
        </div>

        <table class="ms-table ms-hist" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <th class="ms-th" align="left">Month</th>
                <th class="ms-th" align="right">Gross</th>
                <th class="ms-th" align="right">Deductions</th>
                <th class="ms-th" align="right">Net</th>
                <th class="ms-th" align="right">Paid</th>
                <th class="ms-th" align="right">Due</th>
                <th class="ms-th" align="right">Status</th>
            </tr>

            @foreach ($msRows as $msR)
                <tr class="{{ !empty($msR['is_current']) ? 'ms-hist-current' : '' }}">
                    <td class="ms-td">
                        {{ $msR['label'] }}
                        @if (!empty($msR['is_current']))
                            <span class="ms-asof">(this payslip)</span>
                        @endif
                    </td>
                    <td class="ms-td" align="right">{{ number_format($msR['gross'], 2) }}</td>
                    <td class="ms-td" align="right">{{ number_format($msR['deductions'], 2) }}</td>
                    <td class="ms-td" align="right">{{ number_format($msR['net'], 2) }}</td>
                    <td class="ms-td ms-plus" align="right">{{ number_format($msR['paid'], 2) }}</td>
                    <td class="ms-td {{ $msR['due'] > 0 ? 'ms-minus' : '' }}" align="right">{{ number_format($msR['due'], 2) }}</td>
                    <td class="ms-td" align="right">{{ $msR['status'] }}</td>
                </tr>
            @endforeach

            <tr class="ms-out-total">
                <td class="ms-td">
                    {{ count($msRows) }} {{ \Illuminate\Support\Str::plural('month', count($msRows)) }} shown
                </td>
                <td class="ms-td" align="right">{{ number_format($msTot['gross'] ?? 0, 2) }}</td>
                <td class="ms-td" align="right">{{ number_format($msTot['deductions'] ?? 0, 2) }}</td>
                <td class="ms-td" align="right">{{ number_format($msTot['net'] ?? 0, 2) }}</td>
                <td class="ms-td ms-plus" align="right">{{ number_format($msTot['paid'] ?? 0, 2) }}</td>
                <td class="ms-td {{ ($msTot['due'] ?? 0) > 0 ? 'ms-minus' : '' }}" align="right">{{ number_format($msTot['due'] ?? 0, 2) }}</td>
                <td class="ms-td" align="right"></td>
            </tr>
        </table>

        @php
            $msPaidMonths = collect($msRows)->where('status', 'Paid')->count();
        @endphp
        <table class="ms-cards" cellspacing="0" cellpadding="0">
            <tr>
                <td class="ms-card-cell">
                    <div class="ms-card-label">Total earned (net)</div>
                    <div class="ms-card-value"><span class="ms-taka">BDT&nbsp;</span>{{ number_format($msTot['net'] ?? 0) }}</div>
                </td>
                <td class="ms-card-cell">
                    <div class="ms-card-label">Total received</div>
                    <div class="ms-card-value ms-plus"><span class="ms-taka">BDT&nbsp;</span>{{ number_format($msTot['paid'] ?? 0) }}</div>
                </td>
                <td class="ms-card-cell">
                    <div class="ms-card-label">Still outstanding</div>
                    <div class="ms-card-value {{ ($msTot['due'] ?? 0) > 0 ? 'ms-minus' : '' }}"><span class="ms-taka">BDT&nbsp;</span>{{ number_format($msTot['due'] ?? 0) }}</div>
                </td>
                <td class="ms-card-cell">
                    <div class="ms-card-label">Months paid in full</div>
                    <div class="ms-card-value">{{ $msPaidMonths }} / {{ count($msRows) }}</div>
                </td>
            </tr>
        </table>

        <div class="ms-note">
            @if (($msHist['older_months'] ?? 0) > 0)
                Showing the most recent {{ count($msRows) }} months; the totals above cover only
                these. {{ $msHist['older_months'] }} earlier
                {{ \Illuminate\Support\Str::plural('month', $msHist['older_months']) }}
                {{ $msHist['older_months'] === 1 ? 'is' : 'are' }} not listed — see your full
                payslip history in the ERP.<br>
            @endif
            All amounts are in BDT. "Due" is salary already earned and not yet paid — what the
            company owes you, not anything you owe. These figures were recorded on the date
            above and do not update inside this file; open the payslip link in your email for
            the current position. Loans and advances are settled separately and are not shown here.
        </div>
    </div>
@endif
