{{-- Charge & Cost Breakdown + Cost / Profit, as the flight passenger row
     draws them (.mfp-cb): the charge on top with Due / Paid, a rule, then
     each cost line as "− amount", and what the provider is still owed. --}}
@php
 $fileProfit = $file->net_client_charge - $file->total_file_cost;
 $fileMoney = fn ($v) => ((float) $v < 0 ? '−' : '').'৳'.number_format(abs((float) $v), 0);
@endphp
<td data-sort="{{ $file->net_client_charge }}">
 <div class="mfp-cb">
  <span class="k">Charge</span><span class="v mfp-detail" title="Billed to the client">{{ $fileMoney($file->net_client_charge) }}</span>
  @if($file->client_due > 0)
   @if($file->client_paid_amount > 0)<span class="k sub mfp-clear">Received</span><span class="v sub mfp-clear">{{ $fileMoney($file->client_paid_amount) }}</span>@endif
   <span class="k sub mfp-due">Due</span><span class="v sub mfp-due" title="Still owed by the client">{{ $fileMoney($file->client_due) }}</span>
  @else
   <span class="k sub mfp-clear">Paid</span><span class="v sub mfp-clear"><i class="fas fa-check"></i></span>
  @endif
  @if($file->client_credit > 0)<span class="k sub mfp-clear">Credit</span><span class="v sub mfp-clear">{{ $fileMoney($file->client_credit) }}</span>@endif
  <span class="rule"></span>
  <span class="k" title="Processing"><i class="fas fa-file-signature mfp-ico"></i>Processing</span><span class="v">− {{ $fileMoney($file->net_processing_cost) }}</span>
  @foreach($file->serviceCosts as $service)
   <span class="k" title="{{ $service->title }}"><i class="fas fa-receipt mfp-ico"></i>{{ $service->title }}</span><span class="v">− {{ $fileMoney($service->net_amount) }}</span>
  @endforeach
  <span class="k sub {{ $file->total_vendor_due > 0 ? 'mfp-due' : 'mfp-clear' }}" title="Still owed to the provider">Provider due</span><span class="v sub {{ $file->total_vendor_due > 0 ? 'mfp-due' : 'mfp-clear' }}">{{ $fileMoney($file->total_vendor_due) }}</span>
 </div>
</td>
<td class="mfp-num" data-sort="{{ $fileProfit }}"><span class="mfp-cost-total" title="File cost">{{ $fileMoney($file->total_file_cost) }}</span><span class="mfp-profit{{ $fileProfit < 0 ? ' neg' : '' }}" title="Profit = Charge − Cost">Profit {{ $fileMoney($fileProfit) }}</span></td>
