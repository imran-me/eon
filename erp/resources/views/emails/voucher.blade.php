@component('mail::message')
# Visa Sale Voucher

Dear {{ $visaSale->client?->name ?? 'Customer' }},

Please find attached your visa sale voucher **{{ $visaSale->invoice_number }}**.

@component('mail::table')
| Detail | Value |
|:-------|:------|
| Invoice No | {{ $visaSale->invoice_number }} |
| Date | {{ $visaSale->voucher_date->format('d M Y') }} |
| Total Amount | ৳{{ number_format($visaSale->total_amount, 2) }} |
| Paid Amount | ৳{{ number_format($visaSale->paid_amount, 2) }} |
| Due Amount | ৳{{ number_format($visaSale->due_amount, 2) }} |
@endcomponent

@if($visaSale->due_amount > 0)
There is an outstanding balance of **৳{{ number_format($visaSale->due_amount, 2) }}** on this voucher.
@else
This voucher has been paid in full. Thank you!
@endif

If you have any questions about this voucher, please feel free to contact us.

Thanks,<br>
{{ optional($visaSale->company)->name ?? config('app.name') }}
@endcomponent
