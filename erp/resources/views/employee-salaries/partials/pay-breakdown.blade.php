@if($encashment > 0)
<table width="100%" cellspacing="0" cellpadding="0" style="margin:14px 0 10px;border:1px solid #dbe3ee;border-collapse:separate;border-spacing:0;background:#fff;">
    <tr>
        <td colspan="3" style="padding:7px 10px;background:#f8fafc;border-bottom:1px solid #dbe3ee;color:#64748b;font-size:8px;font-weight:700;letter-spacing:1.2px;">
            PAYABLE SUMMARY
        </td>
    </tr>
    <tr>
        <td width="33%" style="padding:11px 12px;border-right:1px solid #dbe3ee;vertical-align:top;">
            <div style="color:#64748b;font-size:8px;font-weight:700;letter-spacing:.7px;">NET SALARY</div>
            <div style="margin-top:5px;color:#0f172a;font-size:15px;font-weight:700;">BDT {{ number_format($salary_net) }}</div>
        </td>
        <td width="34%" style="padding:11px 12px;border-right:1px solid #dbe3ee;vertical-align:top;">
            <div style="color:#2563eb;font-size:8px;font-weight:700;letter-spacing:.7px;">+ LEAVE ENCASHMENT</div>
            <div style="margin-top:5px;color:#1d4ed8;font-size:15px;font-weight:700;">BDT {{ number_format($encashment) }}</div>
            <div style="margin-top:3px;color:#64748b;font-size:8px;">{{ number_format($earned_days, 1) }} total accrued days</div>
        </td>
        <td width="33%" style="padding:11px 12px;background:#eff6ff;vertical-align:top;">
            <div style="color:#1d4ed8;font-size:8px;font-weight:700;letter-spacing:.7px;">= TOTAL PAYABLE</div>
            <div style="margin-top:5px;color:#1d4ed8;font-size:17px;font-weight:800;">BDT {{ number_format($net_salary) }}</div>
        </td>
    </tr>
    <tr>
        <td colspan="3" style="padding:7px 10px;background:#f8fafc;border-top:1px solid #dbe3ee;color:#64748b;font-size:8px;line-height:1.4;">
            Of {{ number_format($earned_days, 1) }} accrued days, {{ number_format($encashment_days, 1) }} were unused: BDT {{ number_format($encashable_value) }} unused-leave value + BDT {{ number_format($encashment_refund) }} refunded leave deductions.
        </td>
    </tr>
</table>
@endif
