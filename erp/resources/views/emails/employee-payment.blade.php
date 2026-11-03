<div style="font-family:Arial,sans-serif;color:#1e293b;line-height:1.6;max-width:620px;margin:auto">
    <h2 style="color:#047857;margin-bottom:8px">Salary payment received</h2>
    <p>Dear {{ $employee->name }},</p>
    <p>Your salary payment has been recorded successfully.</p>
    <table style="width:100%;border-collapse:collapse;background:#f8fafc">
        <tr><td style="padding:9px;border:1px solid #e2e8f0">Amount</td><td style="padding:9px;border:1px solid #e2e8f0"><strong>৳{{ number_format((float) $payment->amount, 2) }}</strong></td></tr>
        <tr><td style="padding:9px;border:1px solid #e2e8f0">Date &amp; time</td><td style="padding:9px;border:1px solid #e2e8f0">{{ $date }}, {{ $time }}</td></tr>
        <tr><td style="padding:9px;border:1px solid #e2e8f0">Payment method</td><td style="padding:9px;border:1px solid #e2e8f0">{{ $method }}</td></tr>
        @if($payment->recipient_bank)<tr><td style="padding:9px;border:1px solid #e2e8f0">Employee bank</td><td style="padding:9px;border:1px solid #e2e8f0">{{ $payment->recipient_bank }}</td></tr>@endif
        @if($payment->recipient_account)<tr><td style="padding:9px;border:1px solid #e2e8f0">Employee account</td><td style="padding:9px;border:1px solid #e2e8f0">{{ $payment->recipient_account }}</td></tr>@endif
        <tr><td style="padding:9px;border:1px solid #e2e8f0">Paid from</td><td style="padding:9px;border:1px solid #e2e8f0">{{ $payment->bank?->name ?? 'Company account' }}</td></tr>
        <tr><td style="padding:9px;border:1px solid #e2e8f0">Reference</td><td style="padding:9px;border:1px solid #e2e8f0">{{ $payment->reference }}</td></tr>
    </table>
    @if($payment->note)<p><strong>Note:</strong> {{ $payment->note }}</p>@endif
    <p style="color:#64748b;font-size:12px">This is an automated payment confirmation.</p>
</div>
