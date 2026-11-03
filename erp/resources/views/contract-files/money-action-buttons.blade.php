@if(\Illuminate\Support\Facades\Schema::hasTable('contract_file_changes') && \Illuminate\Support\Facades\Route::has('role.contract-files.money-action'))
@can('edit contract file')
@php
 $refunded=$file->file_changes->whereIn('type',['refund','cancel']);
 $targets=collect([['id'=>'','title'=>'Processing','vendor'=>$file->vendor?->name,'cost'=>$file->net_processing_cost,'paid'=>(float)$file->cost_paid_amount,'refunded'=>$refunded->whereNull('service_cost_id')->isNotEmpty()]]);
 foreach($file->serviceCosts->whereNull('voided_at') as $service)$targets->push(['id'=>$service->id,'title'=>$service->title,'vendor'=>$service->vendor?->name,'cost'=>$service->net_amount,'paid'=>$service->paid_amount,'refunded'=>$refunded->where('service_cost_id',$service->id)->isNotEmpty()]);
 $moneyInfo=['number'=>$file->file_number,'name'=>$file->applicant_name,'charge'=>$file->net_client_charge,'paid'=>(float)$file->client_paid_amount,'cost'=>$file->total_file_cost,'vendor_id'=>$file->vendor_id,'targets'=>$targets];
 $cancelInfo=['package'=>max(0,$file->client_price-$file->file_changes->sum('client_refund')),'processing'=>$file->net_processing_cost,'refunded'=>$refunded->whereNull('service_cost_id')->isNotEmpty()];
@endphp
<span data-cf-money-info='@json($moneyInfo)' data-cf-money-url="{{ route('role.contract-files.money-action',['role'=>$role,'contractFile'=>$file]) }}" class="cfmoney-actions">
 <button type="button" data-cf-money="refund" title="Refund file / service" aria-label="Refund {{ $file->file_number }}"><i class="fas fa-undo"></i>@if($moneyLabels??false) Refund @endif</button>
 <button type="button" data-cf-money="reprocess" title="Re-process / Re-submit" aria-label="Re-process {{ $file->file_number }}"><i class="fas fa-sync-alt"></i>@if($moneyLabels??false) Re-process @endif</button>
 <button type="button" data-cf-money="others" title="Add other service" aria-label="Add service to {{ $file->file_number }}"><i class="fas fa-plus"></i>@if($moneyLabels??false) Others @endif</button>
 @unless($file->file_changes->where('type','cancel')->isNotEmpty())<button type="button" data-cf-cancel='@json($cancelInfo)' title="Cancel File Processing" aria-label="Cancel file {{ $file->file_number }}"><i class="fas fa-ban" aria-hidden="true"></i><span class="cfmoney-cancel-label"> Cancel</span></button>@endunless
</span>
@endcan
@endif
