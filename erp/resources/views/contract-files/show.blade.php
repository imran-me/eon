@extends('layout.app')
@section('title','Contract File Journey')
@section('main-content')
@php($role=request()->route('role'))
@include('layout.contract-file-tabs')
<div class="space-y-4" id="cf-journey" data-stage-url="{{ route('role.contract-files.stage',['role'=>$role,'contractFile'=>$file]) }}" data-exception-url="{{ route('role.contract-files.exception',['role'=>$role,'contractFile'=>$file]) }}">
 <div class="rounded-xl bg-gradient-to-r from-blue-700 to-violet-700 p-5 text-white shadow-lg">
  <div class="flex flex-wrap items-start justify-between gap-4">
   <div><a href="{{ route('role.contract-files.index',['role'=>$role]) }}" class="text-xs text-blue-100">← Applications Board</a><h1 class="mt-2 text-xl font-black">{{ $file->file_number }} · {{ $file->applicant_name }}</h1><p class="mt-1 text-xs text-blue-100">{{ $file->passport_no }} · {{ $file->country?->name }} · {{ $file->category?->name }}</p></div>
   <div class="grid grid-cols-2 gap-3 text-xs"><div><span class="block text-blue-200">Agent / Client</span><b>{{ $file->client?->name ?? 'Unassigned' }}</b></div><div><span class="block text-blue-200">Vendor</span><b>{{ $file->vendor?->name ?? 'Unassigned' }}</b></div><div><span class="block text-blue-200">Receivable due</span><b>৳{{ number_format($file->client_due) }}</b></div><div><span class="block text-blue-200">Vendor due</span><b>৳{{ number_format($file->due_amount) }}</b></div></div>
  </div>
 </div>
 <div class="rounded-xl border bg-white p-4"><h2 class="mb-3 text-sm font-bold">File charges & financial actions</h2><p class="mb-3 text-xs">Package ৳{{ number_format($file->client_price,2) }} · Net charge ৳{{ number_format($file->net_client_charge,2) }} · Net cost ৳{{ number_format($file->total_file_cost,2) }} · Profit ৳{{ number_format($file->net_client_charge-$file->total_file_cost,2) }}</p><p class="mb-3 text-xs text-slate-500">Client refund credit ৳{{ number_format($file->client_credit,2) }} · Vendor refund credit ৳{{ number_format($file->vendor_credit,2) }} · Credits are approvals, not cash settlements.</p>@include('contract-files.money-action-buttons',['moneyLabels'=>true])</div>
 <div class="overflow-x-auto rounded-xl border bg-white p-5 shadow-sm"><div class="flex min-w-[1250px] items-start">
 @foreach($stages as $key=>$label)
  @php($done=array_search($key,array_keys($stages),true)<=array_search($file->status,array_keys($stages),true))
  <button type="button" data-stage="{{ $key }}" class="group relative flex min-w-[90px] flex-1 flex-col items-center text-center">
   @unless($loop->first)<span class="absolute right-1/2 top-3 h-0.5 w-full {{ $done?'bg-emerald-400':'bg-slate-200' }}"></span>@endunless
   <span class="relative z-10 flex h-7 w-7 items-center justify-center rounded-full border-2 {{ $file->status===$key?'border-blue-600 bg-blue-600 text-white ring-4 ring-blue-100':($done?'border-emerald-500 bg-emerald-50 text-emerald-600':'border-slate-300 bg-white text-slate-400') }}"><i class="fas {{ $done?'fa-check':'fa-circle' }} text-[9px]"></i></span>
   <b class="mt-2 text-[9px] {{ $file->status===$key?'text-blue-700':'text-slate-600' }}">{{ $label }}</b><span class="text-[8px] text-slate-400">{{ $file->status===$key?'Current':($done?'Completed':'Upcoming') }}</span>
  </button>
 @endforeach
 </div></div>
 <div class="grid gap-4 lg:grid-cols-3">
 @include('contract-files.file-documents-services')
  <div class="rounded-xl border bg-white p-4 shadow-sm"><h2 class="mb-3 text-sm font-bold">Exception / adjustment</h2><select id="cf_exception" class="w-full rounded-lg border p-2 text-sm"><option value="">None</option>@foreach(\App\Models\ContractFile::EXCEPTION_FLAGS as $key=>$label)<option value="{{ $key }}" @selected($file->exception_flag===$key)>{{ $label }}</option>@endforeach</select><textarea id="cf_note" class="mt-2 w-full rounded-lg border p-2 text-sm" placeholder="Reason / note"></textarea><button id="cf_exception_save" class="mt-2 rounded-lg bg-amber-500 px-3 py-2 text-xs font-bold text-white">Save adjustment</button></div>
  <div class="rounded-xl border bg-white p-4 shadow-sm lg:col-span-2"><h2 class="mb-3 text-sm font-bold">Journey history</h2><div class="space-y-3">@forelse($file->events as $event)<div class="flex gap-3 border-b pb-3"><span class="mt-1 h-2 w-2 rounded-full {{ $event->type==='exception'?'bg-amber-500':'bg-blue-500' }}"></span><div><b class="text-xs">{{ $event->label }}</b><p class="text-[11px] text-slate-500">{{ $event->occurred_at?->format('d M Y, h:i A') }} · {{ $event->creator?->name ?? 'System' }}</p>@if($event->note)<p class="mt-1 text-xs text-slate-600">{{ $event->note }}</p>@endif</div></div>@empty<p class="text-xs text-slate-400">No journey events yet.</p>@endforelse</div></div>
 </div>
 @include('contract-files.money-history')
</div>
@include('contract-files.money-action-modal')
<script>
document.querySelectorAll('[data-cf-document-status]').forEach(select=>select.addEventListener('change',async()=>{select.disabled=true;try{await cfPatch(select.dataset.url,{status:select.value});location.reload()}catch(error){select.disabled=false}}));
document.querySelectorAll('[data-stage]').forEach(b=>b.addEventListener('click',async()=>{if(!confirm('Move this file to '+b.textContent.trim()+'?'))return;await cfPatch(document.querySelector('#cf-journey').dataset.stageUrl,{stage:b.dataset.stage});location.reload()}));
document.querySelector('#cf_exception_save').addEventListener('click',async()=>{await cfPatch(document.querySelector('#cf-journey').dataset.exceptionUrl,{exception_flag:document.querySelector('#cf_exception').value,note:document.querySelector('#cf_note').value});location.reload()});
async function cfPatch(url,data){const r=await fetch(url,{method:'PATCH',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify(data)});if(!r.ok){const j=await r.json();alert(j.message||'Update failed');throw new Error(j.message)}}
</script>
@endsection
