@php
 $terminalStages=['delivered','case_closed'];
 $activeFiles=$dashboardFiles->reject(fn($f)=>in_array($f->status,$terminalStages));
 $closedFiles=$dashboardFiles->filter(fn($f)=>in_array($f->status,$terminalStages));
 $summarize=function($files) use($clientSummary){return $clientSummary->map(function($group) use($files){
  $items=$group['files']->filter(fn($f)=>$files->contains('id',$f->id));
  return array_merge($group,['files'=>$items,'total'=>$items->count(),'charge'=>$items->sum('net_client_charge'),'due'=>$items->sum('client_due_amount'),'cost'=>$items->sum('total_file_cost'),'vendor_due'=>$items->sum('total_vendor_due'),'in_process'=>$items->whereIn('status',['file_received','information_entered','documents_pending','documents_verified','application_prepared','submitted_to_vendor','biometrics_appointment','under_process','additional_documents'])->count(),'approved'=>$items->where('status','approved')->count()]);
 })->filter(fn($g)=>$g['total']>0);};
 $activeGroups=$summarize($activeFiles); $closedGroups=$summarize($closedFiles);
 // File wise order — the flight's "nearest first": the soonest expected date first, undated last;
 // completed files newest submission first, as Departed Flights is newest first.
 $activeList=$activeFiles->sortBy(fn($f)=>[$f->expected_out_date ? 0 : 1, $f->expected_out_date?->toDateString() ?? '', -$f->id])->values();
 $closedList=$closedFiles->sortByDesc(fn($f)=>[$f->submit_date?->toDateString() ?? '', $f->id])->values();
 $fileFilterRow=fn($f)=>['search'=>implode(' ',[$f->file_number,$f->applicant_name,$f->passport_no,$f->client?->name,$f->country?->name,$f->category?->name,$f->vendor?->name]),'status'=>$f->status,'date'=>$f->submit_date?->toDateString(),'due'=>$f->total_vendor_due];
 $closedFileData=$closedList->map($fileFilterRow)->values();
 $closedGroupData=$closedGroups->values()->map(fn($g)=>$g['files']->map($fileFilterRow)->values());
@endphp
@include('contract-files.dashboard-controls',['cardGroups'=>$activeGroups,'summaryFiles'=>$activeFiles,'cardFiles'=>$activeList])
<div id="cfd-group-table">
 <section class="fd-table-panel" id="cfdActive" data-cfd-mode="file" aria-label="Active files">
  <div class="fd-table-scroll"><table class="fd-table cfd-file-table" id="cfdActiveTable" data-cf-sort>@include('contract-files.dashboard-file-head')<tbody>@forelse($activeList as $file)@include('contract-files.dashboard-file-row')@empty<tr><td colspan="8" class="fd-table-empty">No active files match these filters.</td></tr>@endforelse</tbody></table></div>
 </section>
 <section class="fd-table-panel" id="cfdActiveGroups" data-cfd-mode="agent" aria-label="Active agent / client groups">
  <div class="fd-table-scroll"><table class="fd-table" id="cfdActiveGroupTable" data-cf-sort>@include('contract-files.dashboard-table-head')<tbody>@forelse($activeGroups as $group)@include('contract-files.dashboard-group-row')@empty<tr><td colspan="8" class="fd-table-empty">No active file groups match these filters.</td></tr>@endforelse</tbody></table></div>
 </section>
 {{-- Delivered / Closed — the Flight Dashboard's Departed Flights panel: its
      title bar and the standard .fdl-bar (search, quick states, dd/mm/yyyy
      range, Apply, Print). One bar, both modes' tables; client-side. --}}
 <section class="fd-table-panel" id="cfdClosed" aria-labelledby="cfdClosedTitle">
  <div class="fd-departed-head"><h3 id="cfdClosedTitle"><i class="fas fa-check-double" aria-hidden="true"></i>Delivered / Closed Files</h3><span id="cfd-closed-count" aria-live="polite"><span data-cfd-mode="file">{{ $closedList->count() }} files</span><span data-cfd-mode="agent">{{ $closedGroups->count() }} groups · {{ $closedFiles->count() }} files</span></span></div>
  @include('contract-files.dashboard-closed-filter')
  <div class="fd-table-scroll" data-cfd-mode="file"><table class="fd-table cfd-file-table" id="cfdClosedTable" data-cf-sort>@include('contract-files.dashboard-file-head')<tbody>
  @foreach($closedList as $file)@include('contract-files.dashboard-file-row',['closedIndex'=>$loop->index])@endforeach
  <tr data-cfd-closed-empty @if($closedList->isNotEmpty()) hidden @endif><td colspan="8" class="fd-table-empty">No delivered or closed files match these filters.</td></tr>
  </tbody></table></div>
  <div class="fd-table-scroll" data-cfd-mode="agent"><table class="fd-table" id="cfdClosedGroupTable" data-cf-sort>@include('contract-files.dashboard-table-head')<tbody>
  @foreach($closedGroups as $group)@include('contract-files.dashboard-group-row',['closedIndex'=>$loop->index])@endforeach
  <tr data-cfd-closed-empty @if($closedGroups->isNotEmpty()) hidden @endif><td colspan="8" class="fd-table-empty">No delivered or closed file groups match these filters.</td></tr>
  </tbody></table></div>
 </section>
</div>
<style>
/* Sort arrows come from contract-flights.partials.table-sort; the flight
   dashboard's own arrows sit at .45. Fixed columns, 6px gutters, rows read
   from their first line and the eye under the last figure — the flight's
   #fdFlightTable rules, for all four tables here. */
table[data-cf-sort].fd-table th .cf-sort-ico { opacity: .45; }
table[data-cf-sort].fd-table th[aria-sort="ascending"] .cf-sort-ico, table[data-cf-sort].fd-table th[aria-sort="descending"] .cf-sort-ico { opacity: 1; color: #4338ca; }
#cfd-group-table .fd-table { table-layout: fixed; width: 100%; }
#cfd-group-table .fd-table th { white-space: normal; padding: 12px 6px; }
#cfd-group-table .fd-table td { padding: 8px 6px; vertical-align: top; }
#cfd-group-table .fd-table .fdl-eye { display: flex; align-items: center; justify-content: center; box-sizing: border-box; margin-top: 6px; margin-left: auto; border: 1px solid #dbe3ef; background: #fff; color: #64748b;
    width: 26px; height: 24px; border-radius: 6px; cursor: pointer; font-size: .72rem; font-weight: 400; line-height: 1; padding: 0; }
#cfd-group-table .fd-table .fdl-eye:hover { background: #eef2ff; border-color: #a5b4fc; color: #4338ca; }
#cfd-group-table .fd-table tbody tr[hidden] { display: none; }
#cfd-group-table .fd-table tbody tr[data-file-open], #cfd-group-table .fd-table tbody tr[data-group-row] { cursor: pointer; }
/* A stage badge for a flagged file (returned / rejected / cancelled). */
.fd-table-status.flagged { background: #fef2f2; color: #b91c1c; }
@media print { .fd-banner, .cfd-grid, .fdl-bar, .fdl-eye, .ex-toolbar { display: none !important; } .fd-table-panel { overflow: visible; } }
</style>
<script>
(() => {
 const form=document.getElementById('cfd-closed-filter');
 const files=@json($closedFileData), groups=@json($closedGroupData);
 const dmy=s=>s?s.slice(8,10)+'/'+s.slice(5,7)+'/'+s.slice(0,4):'';
 const pad=n=>String(n).padStart(2,'0');
 const fromDmy=s=>{const m=String(s||'').trim().match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/);return m?m[3]+'-'+pad(m[2])+'-'+pad(m[1]):(s?null:'');};
 const search=document.getElementById('cfdcSearch'),wrap=document.getElementById('cfdcSearchWrap');
 let state='all',from='',to='';
 const hit=(file,q)=>(!q||file.search.toLowerCase().includes(q))&&(state==='all'||(state==='vendor_due'?file.due>0:file.status===state))&&(!from||(file.date&&file.date>=from))&&(!to||(file.date&&file.date<=to));
 function apply(){
  const q=search.value.trim().toLowerCase();let shownFiles=0,shownGroups=0;
  document.querySelectorAll('#cfdClosedTable [data-cfd-closed-file]').forEach(row=>{const show=hit(files[Number(row.dataset.cfdClosedFile)],q);row.hidden=!show;if(show)shownFiles++;});
  document.querySelectorAll('#cfdClosedGroupTable [data-cfd-closed-group]').forEach(row=>{const show=groups[Number(row.dataset.cfdClosedGroup)].some(file=>hit(file,q));row.hidden=!show;if(show)shownGroups++;});
  document.querySelector('#cfdClosedTable [data-cfd-closed-empty]').hidden=shownFiles>0;
  document.querySelector('#cfdClosedGroupTable [data-cfd-closed-empty]').hidden=shownGroups>0;
  document.querySelector('#cfd-closed-count [data-cfd-mode="file"]').textContent=`${shownFiles} of ${files.length} completed files`;
  document.querySelector('#cfd-closed-count [data-cfd-mode="agent"]').textContent=`${shownGroups} of ${groups.length} completed groups`;
 }
 form.querySelectorAll('[data-cfd-closed-state]').forEach(button=>button.addEventListener('click',()=>{state=button.dataset.cfdClosedState;form.querySelectorAll('[data-cfd-closed-state]').forEach(item=>{item.classList.toggle('is-on',item===button);item.setAttribute('aria-pressed',String(item===button))});apply()}));
 search.addEventListener('input',()=>{wrap.classList.toggle('has-q',!!search.value);apply()});
 document.getElementById('cfdcClear').addEventListener('click',()=>{search.value='';search.dispatchEvent(new Event('input'));search.focus()});
 ['From','To'].forEach(w=>{
  const nat=document.getElementById('cfdc'+w),txt=document.getElementById('cfdc'+w+'Text');
  nat.addEventListener('change',()=>{txt.value=dmy(nat.value);txt.closest('.fdl-date').classList.remove('is-bad')});
  txt.addEventListener('input',()=>{const v=fromDmy(txt.value);txt.closest('.fdl-date').classList.toggle('is-bad',v===null&&txt.value.length>=8);if(v)nat.value=v;if(v==='')nat.value=''});
  txt.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();document.getElementById('cfdcApply').click()}});
 });
 document.getElementById('cfdcApply').addEventListener('click',()=>{
  const a=fromDmy(document.getElementById('cfdcFromText').value),b=fromDmy(document.getElementById('cfdcToText').value);
  if(a===null||b===null)return;
  from=a||'';to=b||'';apply();
 });
 // Print: the rows the bar shows in the mode on screen, on an A4 sheet — like the flight's Departed Flights print.
 document.getElementById('cfdcPrint').addEventListener('click',()=>{
  const agentMode=document.getElementById('cf-dashboard-overview').dataset.mode==='agent';
  const table=document.getElementById(agentMode?'cfdClosedGroupTable':'cfdClosedTable').cloneNode(true);
  table.querySelectorAll('tbody tr[hidden], colgroup, .fdl-eye, .cf-sort-ico').forEach(n=>n.remove());
  const w=window.open('','_blank');if(!w)return;
  const range=from||to?` · submitted ${from?dmy(from):'…'} – ${to?dmy(to):'…'}`:'';
  const count=document.querySelector(`#cfd-closed-count [data-cfd-mode="${agentMode?'agent':'file'}"]`).textContent;
  w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Delivered / Closed Files</title><style>@page{size:A4;margin:12mm}body{font-family:Arial,sans-serif;color:#1e293b;font-size:10.5px}h1{font-size:16px;margin:0}p{margin:3px 0 10px;color:#64748b}table{width:100%;border-collapse:collapse}th{background:#eef2ff;color:#4338ca;text-align:left;padding:6px;font-size:9.5px;text-transform:uppercase}td{padding:5px 6px;border-bottom:1px solid #e2e8f0;vertical-align:top}small{display:block;color:#94a3b8}a{color:inherit;text-decoration:none}img{width:15px;height:10px}.fd-num{text-align:right;white-space:nowrap}.fd-table-status{margin-left:4px;font-size:8.5px;color:#475569}<\/style></head><body><h1>Delivered / Closed Files</h1><p>${count}${range}</p>${table.outerHTML}</body></html>`);
  w.document.close();w.focus();w.print();
 });
})();
</script>
