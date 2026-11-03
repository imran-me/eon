<div style="font-family:Arial,sans-serif;color:#1e293b;line-height:1.6;max-width:620px;margin:auto">
    <h2 style="color:#1d4ed8">Employee account statement</h2>
    <p>Dear {{ $employee->name }},</p>
    <p>Your complete employee account statement is attached as a PDF.</p>
    <table style="width:100%;border-collapse:collapse;background:#f8fafc">
        <tr><td style="padding:9px;border:1px solid #e2e8f0">Total earned</td><td style="padding:9px;border:1px solid #e2e8f0">৳{{ number_format($totalDebit, 2) }}</td></tr>
        <tr><td style="padding:9px;border:1px solid #e2e8f0">Paid / recovered</td><td style="padding:9px;border:1px solid #e2e8f0">৳{{ number_format($totalCredit, 2) }}</td></tr>
        <tr><td style="padding:9px;border:1px solid #e2e8f0">Closing position</td><td style="padding:9px;border:1px solid #e2e8f0"><strong>৳{{ number_format(abs($closingBalance), 2) }} {{ $closingBalance > 0 ? 'company owes' : ($closingBalance < 0 ? 'advance/overpaid' : 'settled') }}</strong></td></tr>
    </table>
    <p style="color:#64748b;font-size:12px">Sent by {{ optional($company)->name ?? config('app.name') }} on {{ now()->format('d M Y, h:i A') }}.</p>
</div>
