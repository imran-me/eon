{{-- Flight's service head + white accounting tray, with File-only fields and providers. --}}
<div class="cfbld-svc-list">
@foreach(['training'=>['title'=>'Immigration Training','provider'=>'Responsible person','fee'=>'Fee'],'others'=>['title'=>'Others','provider'=>'Vendor / provider','fee'=>'Cost']] as $serviceKey=>$definition)
 <div class="cfbld-svc" data-bld-service="{{ $serviceKey }}">
  <div class="cfbld-svc-head">
   <label class="cfbld-svc-tick"><input data-field="{{ $serviceKey }}.enabled" type="checkbox"><span class="cfbld-svc-text"><b>{{ $definition['title'] }}</b>@if($serviceKey==='training')<em>File-only training</em>@endif</span></label>
   @if($serviceKey==='others')<label class="cfbld-svc-title" data-bld-service-enabled="{{ $serviceKey }}" hidden><span class="cfbld-lbl">Service title *</span><input data-field="others.title" maxlength="160" placeholder="What we do for them"></label>@endif
   <label class="cfbld-svc-cost" data-bld-service-enabled="{{ $serviceKey }}" hidden><span class="cfbld-lbl">{{ $definition['fee'] }}</span><input data-field="{{ $serviceKey }}.amount" type="number" min="0" max="999999999" step="0.01" value="0" placeholder="0.00"></label>
  </div>
  <div class="cfbld-sc-tray" data-bld-{{ $serviceKey }} hidden>
   <div class="cfbld-sc-grid">
    <label><span class="cfbld-lbl">{{ $definition['provider'] }} *</span><select data-field="{{ $serviceKey }}.vendor_id"><option value="">— select {{ $serviceKey==='training'?'instructor':'vendor' }} —</option>@foreach(($serviceKey==='training'?$fileTrainers:$vendors) as $provider)<option value="{{ $provider->id }}">{{ $provider->name }}</option>@endforeach</select></label>
    <label><span class="cfbld-lbl">Pay status</span><select data-bld-pay-status="{{ $serviceKey }}"><option value="due">Due</option><option value="partial">Partial</option><option value="paid">Paid</option></select></label>
    <label><span class="cfbld-lbl">Paid amount</span><input data-field="{{ $serviceKey }}.paid_amount" type="number" min="0" max="999999999" step="0.01" value="0"></label>
    <label><span class="cfbld-lbl">Bank / cash account</span><select data-field="{{ $serviceKey }}.bank_id"><option value="">— select bank / cash —</option>@foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name }}</option>@endforeach</select></label>
    <label><span class="cfbld-lbl">Cost date</span><input data-field="{{ $serviceKey }}.incurred_on" type="date"></label>
    <label data-bld-due-date="{{ $serviceKey }}"><span class="cfbld-lbl">Due date</span><input data-field="{{ $serviceKey }}.due_date" type="date"></label>
    <label data-bld-payment-date="{{ $serviceKey }}" hidden><span class="cfbld-lbl">Payment date</span><input data-field="{{ $serviceKey }}.payment_date" type="date"></label>
   </div>
  </div>
 </div>
@endforeach
</div>
