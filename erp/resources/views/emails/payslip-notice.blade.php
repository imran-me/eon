{{--
    Payslip notification email.

    Everything is inline-styled and table-based: Gmail, Outlook and most Bangla
    webmail clients strip <style> blocks, so a class-based layout would arrive
    unformatted. Figures come from employee_salaries.summary_snapshot, frozen
    when the payslip was issued, so the email and the attached PDF always quote
    the same month.

    Expects: $user, $empSalary, $viewUrl
--}}
@php
    $snap = is_array($empSalary->summary_snapshot ?? null) ? $empSalary->summary_snapshot : [];
    $att = $snap['attendance'] ?? [];
    $pay = $snap['payment'] ?? [];

    $hasAtt = ($att['available'] ?? false) === true;
    $hasPay = ($pay['available'] ?? false) === true;

    try {
        $monthLabel = \Carbon\Carbon::createFromDate((int) $empSalary->year, (int) $empSalary->month, 1)->format('F Y');
    } catch (\Throwable $e) {
        $monthLabel = $empSalary->month . '/' . $empSalary->year;
    }

    $taka = fn ($v) => '৳' . number_format((float) $v, 2);
    $hm = function ($m) {
        $m = max(0, (int) $m);
        return intdiv($m, 60) . 'H ' . ($m % 60) . 'M';
    };

    $gross = (float) ($empSalary->gross_salary ?? 0)
        + (float) ($empSalary->overtime_salary ?? 0)
        + (float) ($empSalary->bonus_amount ?? 0);

    // Two columns of label/value, filled left-to-right like the design.
    $attRows = !$hasAtt ? [] : [
        ['Total Days', $att['total_days'] ?? 0],
        ['Present', $att['present'] ?? 0],
        ['Absent', $att['absent'] ?? 0],
        ['Leave', $att['leave'] ?? 0],
        ['Holiday', $att['holiday'] ?? 0],
        ['Late Days', $att['late'] ?? 0],
        ['Late Minutes', number_format($att['late_minutes'] ?? 0) . ' মিনিট'],
        ['Early Leave', $att['early_out'] ?? 0],
        ['Early Leave Minutes', number_format($att['early_minutes'] ?? 0) . ' মিনিট'],
        ['Worked Hours', $hm($att['worked_minutes'] ?? 0)],
        ['Overtime Days', $att['overtime_days'] ?? 0],
        ['Overtime Minutes', number_format($att['overtime_minutes'] ?? 0) . ' মিনিট'],
    ];
    $attPairs = array_chunk($attRows, 2);

    $cellL = 'padding:9px 12px;border-bottom:1px solid #eef2f7;font-size:13px;color:#334155;';
    $cellR = 'padding:9px 12px;border-bottom:1px solid #eef2f7;font-size:13px;color:#0f172a;font-weight:bold;text-align:right;';
    $headC = 'padding:8px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:11px;color:#64748b;text-transform:uppercase;';
@endphp

<div style="margin:0;padding:24px 12px;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;">
    <tr>
        <td style="padding:28px 32px 0 32px;">

            {{-- Header --}}
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="vertical-align:top;">
                        <div style="font-size:22px;font-weight:800;color:#0f172a;letter-spacing:0.5px;">EPAL GROUP</div>
                        <div style="font-size:11px;color:#94a3b8;letter-spacing:1px;">HR &amp; PAYROLL DEPARTMENT</div>
                    </td>
                    <td style="vertical-align:top;text-align:right;">
                        <div style="font-size:11px;color:#94a3b8;">Payslip Notification</div>
                        <div style="font-size:12px;color:#334155;font-weight:bold;">{{ $monthLabel }}</div>
                    </td>
                </tr>
            </table>

            <div style="height:3px;background:#0f172a;margin:14px 0 22px 0;"></div>

            {{-- Greeting --}}
            <p style="margin:0 0 10px 0;font-size:15px;color:#0f172a;">
                প্রিয় <strong>{{ $user->name }}</strong>,
            </p>
            <p style="margin:0 0 22px 0;font-size:14px;color:#475569;line-height:1.7;">
                আপনার <strong>{{ $monthLabel }}</strong>-এর Payslip সফলভাবে তৈরি হয়েছে।
                বিস্তারিত দেখতে সংযুক্ত <strong>Payslip</strong> ফাইলটি দেখুন।
            </p>

            {{-- Salary summary --}}
            <div style="font-size:15px;font-weight:bold;color:#0f172a;margin:0 0 10px 0;">Salary Summary</div>
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-collapse:collapse;margin-bottom:24px;">
                <tr>
                    <td style="{{ $headC }}">বিবরণ</td>
                    <td style="{{ $headC }}text-align:right;">পরিমাণ</td>
                </tr>
                <tr>
                    <td style="{{ $cellL }}">মোট অর্জিত (Gross)</td>
                    <td style="{{ $cellR }}">{{ $taka($gross) }}</td>
                </tr>
                <tr>
                    <td style="{{ $cellL }}">মোট কর্তন (Deductions)</td>
                    <td style="{{ $cellR }}color:#b91c1c;">− {{ $taka($empSalary->total_deductions ?? 0) }}</td>
                </tr>
                @if ($hasPay && ($pay['paid'] ?? 0) > 0)
                    <tr>
                        <td style="{{ $cellL }}">পরিশোধিত (Paid)</td>
                        <td style="{{ $cellR }}color:#047857;">{{ $taka($pay['paid']) }}</td>
                    </tr>
                @endif
                @if ($hasPay && ($pay['due'] ?? 0) > 0)
                    <tr>
                        <td style="{{ $cellL }}">বকেয়া (Due)</td>
                        <td style="{{ $cellR }}color:#b91c1c;">{{ $taka($pay['due']) }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:13px 12px;background:#0f172a;color:#ffffff;font-size:14px;font-weight:bold;">
                        এই মাসের প্রদেয় (Net)
                    </td>
                    <td style="padding:13px 12px;background:#0f172a;color:#ffffff;font-size:16px;font-weight:bold;text-align:right;">
                        {{ $taka($empSalary->net_salary ?? 0) }}
                    </td>
                </tr>
            </table>

            {{-- Attendance --}}
            @if ($hasAtt)
                <div style="font-size:15px;font-weight:bold;color:#0f172a;margin:0 0 10px 0;">Attendance &amp; Work Summary</div>
                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-collapse:collapse;margin-bottom:24px;">
                    <tr>
                        <td style="{{ $headC }}">বিবরণ</td>
                        <td style="{{ $headC }}text-align:right;">তথ্য</td>
                        <td style="{{ $headC }}border-left:1px solid #e2e8f0;">বিবরণ</td>
                        <td style="{{ $headC }}text-align:right;">তথ্য</td>
                    </tr>
                    @foreach ($attPairs as $pair)
                        <tr>
                            <td style="{{ $cellL }}">{{ $pair[0][0] }}</td>
                            <td style="{{ $cellR }}">{{ $pair[0][1] }}</td>
                            <td style="{{ $cellL }}border-left:1px solid #e2e8f0;">{{ $pair[1][0] ?? '' }}</td>
                            <td style="{{ $cellR }}">{{ $pair[1][1] ?? '' }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            {{-- Attachments --}}
            <div style="font-size:13px;font-weight:bold;color:#334155;margin:0 0 8px 0;">সংযুক্তি:</div>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:22px;">
                <tr>
                    <td style="padding:11px 14px;border:1px solid #e2e8f0;font-size:13px;color:#334155;">
                        📎 Payslip — {{ $monthLabel }}
                        <span style="color:#94a3b8;font-size:11px;">
                            (স্যালারি ইনভয়েস · পে-রোল হিস্ট্রি · অ্যাটেনডেন্স)
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:11px 14px;border:1px solid #e2e8f0;border-top:0;font-size:13px;color:#334155;">
                        📎 Employee Task Report — {{ $monthLabel }}
                        <span style="color:#94a3b8;font-size:11px;">
                            (monthly task report)
                        </span>
                    </td>
                </tr>
            </table>


            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:22px;">
                <tr>
                    <td style="padding:12px 14px;background:#f8fafc;border-left:3px solid #0f172a;font-size:12px;color:#475569;line-height:1.6;">
                        হিসাবে কোনো সংশোধন বা অসঙ্গতি চোখে পড়লে অনুগ্রহ করে
                        <strong>HR &amp; Payroll</strong> বিভাগে যোগাযোগ করুন।
                    </td>
                </tr>
            </table>

            <div style="border-top:1px solid #e2e8f0;padding-top:16px;padding-bottom:26px;">
                <div style="font-size:13px;color:#475569;">ধন্যবাদান্তে,</div>
                <div style="font-size:13px;color:#0f172a;font-weight:bold;">HR &amp; Payroll Department</div>
                <div style="font-size:13px;color:#0f172a;font-weight:bold;">Epal Group</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:10px;">
                    এটি একটি সিস্টেম জেনারেটেড ইমেল, দয়া করে এখানে রিপ্লাই করবেন না।
                </div>
            </div>

        </td>
    </tr>
</table>
</div>
