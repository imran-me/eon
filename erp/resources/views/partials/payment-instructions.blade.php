@php $instructionBank = $document->paymentInstructionBank; @endphp
@if(($document->due_amount ?? 0) > 0 && $instructionBank)
<div style="margin-top:14px;border:1px solid #f59e0b;background:#fffbeb;padding:10px 12px;border-radius:4px;color:#451a03;font-size:11px;line-height:1.55;">
    <strong style="display:block;color:#92400e;font-family:var(--tf);font-size:11px;margin-bottom:5px;text-transform:uppercase;">Payment Instructions</strong>
    <div>Please pay the outstanding balance to:</div>
    <div><b>{{ $instructionBank->name }}</b>@if($instructionBank->branch_name) — {{ $instructionBank->branch_name }}@endif</div>
    @if($instructionBank->account_name)<div>Account Name: {{ $instructionBank->account_name }}</div>@endif
    @if($instructionBank->account_number)<div>Account Number: {{ $instructionBank->account_number }}</div>@endif
    @if($instructionBank->routing_number)<div>Routing Number: {{ $instructionBank->routing_number }}</div>@endif
    @if($instructionBank->swift_code)<div>SWIFT: {{ $instructionBank->swift_code }}</div>@endif
</div>
@endif
