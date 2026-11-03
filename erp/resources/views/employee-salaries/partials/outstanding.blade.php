{{--
    Unpaid salary months for this employee — LIVE, not snapshotted.

    Deliberately the opposite treatment to the attendance/task summary next to
    it. Attendance is frozen because it describes a month that has ended; an
    outstanding balance describes today and changes the moment a payment is
    recorded, so freezing it would make the page lie. That is also why this
    block appears ONLY on the signed view page and never in the PDF: a PDF gets
    saved and forwarded, and a stale "still due" inside a saved file is the
    thing that starts arguments.

    It computes itself rather than taking data from the controller because
    `employee-salaries.view` is rendered from more than one controller
    (EmployeeSalaryController::view() and Api\Admin\EmployeeSalaryController::view()),
    and a block that depends on every one of them remembering to pass a
    variable is a block that silently disappears. Everything is wrapped so this
    can never take the payslip page down.

    "Paid" follows PaymentSchedule::paidTotal() — the documented rule that a
    cancelled schedule is not money received — so these figures agree with the
    profile's Payslips tab.

    Expects: $data (EmployeeSalary)
--}}
@php
    $msOut = collect();
    $msOutFailed = false;

    try {
        $msOut = \App\Models\EmployeeSalary::with('schedules')
            ->where('user_id', $data->user_id)
            ->where('id', '!=', $data->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(function ($slip) {
                $net = round((float) $slip->net_salary, 2);
                $isPaid = strtolower((string) $slip->status) === 'paid';
                $paid = \App\Models\EmployeePaymentAllocation::paidForSalary($slip->id);

                return (object) [
                    'label' => \Carbon\Carbon::createFromDate((int) $slip->year, (int) $slip->month, 1)->format('F Y'),
                    'paid' => $paid,
                    'due' => max(0, round($net - $paid, 2)),
                    'status' => $isPaid ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending'),
                ];
            })
            ->filter(fn ($r) => $r->due > 0)
            ->values();
    } catch (\Throwable $e) {
        $msOutFailed = true;
    }

    // This month's own due, from the frozen snapshot, so the grand total below
    // covers every month rather than only the older ones.
    $msThisDue = (float) ($data->summary_snapshot['payment']['due'] ?? 0);
    $msOtherDue = (float) $msOut->sum('due');
    $msTotalDue = round($msThisDue + $msOtherDue, 2);
@endphp

@if (!$msOutFailed && ($msOut->isNotEmpty() || $msThisDue > 0))
    <div class="ms-card">
    <div class="ms-wrap">
        <div class="ms-title">
            Unpaid Salary
            <span class="ms-asof">— as of {{ now()->format('d M Y') }}</span>
        </div>

        <table class="ms-table" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <th class="ms-th" align="left" width="46%">Month</th>
                <th class="ms-th" align="right" width="30%">Still due</th>
                <th class="ms-th" align="right" width="24%">Status</th>
            </tr>

            @if ($msThisDue > 0)
                <tr>
                    <td class="ms-td">
                        {{ \Carbon\Carbon::createFromDate((int) $data->year, (int) $data->month, 1)->format('F Y') }}
                        <em>(this payslip)</em>
                    </td>
                    <td class="ms-td ms-minus" align="right">৳{{ number_format($msThisDue, 2) }}</td>
                    <td class="ms-td" align="right">{{ $data->summary_snapshot['payment']['status'] ?? '' }}</td>
                </tr>
            @endif

            @foreach ($msOut as $msRow)
                <tr>
                    <td class="ms-td">{{ $msRow->label }}</td>
                    <td class="ms-td ms-minus" align="right">৳{{ number_format($msRow->due, 2) }}</td>
                    <td class="ms-td" align="right">
                        {{ $msRow->status }}@if ($msRow->paid > 0) <span class="ms-asof">(৳{{ number_format($msRow->paid, 2) }} paid)</span>@endif
                    </td>
                </tr>
            @endforeach

            <tr class="ms-out-total">
                <td class="ms-td">Total unpaid salary</td>
                <td class="ms-td ms-minus" align="right">৳{{ number_format($msTotalDue, 2) }}</td>
                <td class="ms-td" align="right"></td>
            </tr>
        </table>

        <div class="ms-note">
            This is salary already earned and not yet paid to you — it is what the company
            owes you, not anything you owe. It is current as of the date above and changes
            as payments are made, which is why it is shown here rather than on the
            downloaded PDF. Loans and advances are settled separately and are not included.
        </div>
    </div>
    </div>
@endif
