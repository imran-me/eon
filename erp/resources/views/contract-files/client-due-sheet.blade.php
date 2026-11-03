@php
 $agentGroups=$rows->groupBy(fn($r)=>$r['file']->client_id ?? 'unassigned')->sortByDesc(fn($items)=>$items->sum('client_due'));
 $applicantFiles=$rows->sortBy(fn($r)=>$r['file']->submit_date?->toDateString() ?? '9999');
 $dueFiles=$rows->filter(fn($r)=>$r['client_due']>0)->sortBy(fn($r)=>$r['file']->submit_date?->toDateString() ?? '9999');
@endphp
{{-- Agent / Client Due Sheet in Flight Accounts' dress (.cfa-card /
     .cfa-table). Ids and data-cfac-* hooks are what client-due-script reads. --}}
<section class="cfa-panel" id="cfac-client" data-cfac-panel="client" hidden>
 <div class="cfa-card">
  <div class="cfa-card-h"><h3>By agent / client</h3><small>Click a row to show its due files below · File dues only — not the overall Party Statement balance · sorted by due</small></div>
  <div class="cfa-scroll"><table class="cfa-table">
   <thead><tr><th>Agent / Client</th><th class="num">Files / Applicants</th><th class="num">Charged</th><th class="num">Received</th><th class="num">File due</th><th>Collected</th><th>Oldest due</th><th></th></tr></thead>
   <tbody>
   @forelse($agentGroups as $key=>$items)
    @php
     $party=$items->first()['file']->client; $name=$party?->name ?? 'Direct / Unassigned';
     $charge=$items->sum(fn($r)=>$r['file']->net_client_charge); $paid=$items->sum('received');
     $percent=$charge>0 ? max(0,min(100,$paid/$charge*100)) : 0;
     $oldest=$items->filter(fn($r)=>$r['client_due']>0 && $r['file']->submit_date)->sortBy(fn($r)=>$r['file']->submit_date->toDateString())->first();
    @endphp
    <tr class="cfac-agent-row" data-cfac-agent="{{ $key }}" data-cfac-agent-name="{{ $name }}" tabindex="0" role="button" aria-pressed="false" aria-controls="cfac-due-files" title="Show this agent's due files below">
     <td class="b">{{ $name }}@if($party)<small><a href="{{ route('role.party-statement.index',['role'=>$role,'party_id'=>$party->id]) }}" title="Open overall Party Statement">EP-AG-{{ str_pad($party->id,3,'0',STR_PAD_LEFT) }} · Ledger</a></small>@endif @if($party?->phone)<small>{{ $party->phone }}</small>@endif</td>
     <td class="num">{{ $items->count() }}</td>
     <td class="num">{{ $money($charge) }}</td>
     <td class="num cfa-good">{{ $money($paid) }}</td>
     <td class="num {{ $items->sum('client_due')>0 ? 'cfa-due' : 'cfa-good' }}">{{ $items->sum('client_due')>0 ? $money($items->sum('client_due')) : 'Clear' }}</td>
     <td><div class="cfac-collected"><span style="width:{{ $percent }}%"></span></div><small>{{ number_format($percent,0) }}%</small></td>
     <td>@if($oldest)<span class="cfa-pill warn">{{ (int) $oldest['file']->submit_date->diffInDays(now(),false) }} days</span><small>{{ $oldest['file']->submit_date->format('d/m/Y') }}</small>@else — @endif</td>
     <td><button type="button" class="cfac-pick" aria-label="Show due files for {{ $name }}"><i class="fas fa-list"></i></button></td>
    </tr>
   @empty
    <tr><td colspan="8" class="cfa-empty"><i class="fas fa-user-tie"></i>No agent or client files found.</td></tr>
   @endforelse
   </tbody>
   <tfoot class="cfa-pl-foot"><tr><td>Total</td><td class="num">{{ $rows->count() }}</td><td class="num">{{ $money($rows->sum(fn($r)=>$r['file']->net_client_charge)) }}</td><td class="num">{{ $money($rows->sum('received')) }}</td><td class="num">{{ $money($rows->sum('client_due')) }}</td><td colspan="3"></td></tr></tfoot>
  </table></div>
 </div>
 <div class="cfa-card" id="cfac-due-files">
  <div class="cfa-card-h">
   <h3 id="cfac-file-title">Due files / applicants</h3><small id="cfac-due-hint">Every file whose client charge is not fully received · select an agent above</small>
   <span class="sp"></span>
   <div class="fdl-seg" role="group" aria-label="Applicant file scope">
    <button type="button" data-cfac-scope="due" aria-pressed="true" class="is-on">Due only</button>
    <button type="button" data-cfac-scope="all" aria-pressed="false">All files</button>
   </div>
   <button type="button" class="fdl-print" id="cfac-due-clear" hidden><i class="fas fa-xmark"></i> Show all agents</button>
  </div>
  <div class="cfa-scroll"><table class="cfa-table">
   <thead><tr><th>File / Applicant / Passport</th><th>Agent / Client</th><th>Country / Category</th><th>Stage</th><th>Vendor / Cost</th><th class="num">Charge</th><th class="num">Received</th><th class="num">Due</th><th></th></tr></thead>
   <tbody>
   @foreach($applicantFiles as $row)
    @php $file=$row['file']; @endphp
    <tr @if($row['client_due']<=0) hidden @endif data-cfac-due-agent="{{ $file->client_id ?? 'unassigned' }}" data-charge="{{ $file->net_client_charge }}" data-paid="{{ $row['received'] }}" data-due="{{ $row['client_due'] }}">
     <td class="b">{{ $file->applicant_name }}<small>{{ $file->file_number }}</small><small>{{ $file->passport_no ?? '—' }}</small></td>
     <td>{{ $file->client?->name ?? 'Direct / Unassigned' }}</td>
     <td>{{ $file->country?->name ?? '—' }}<small>{{ $file->category?->name ?? '—' }}</small><small>Submitted {{ $file->submit_date?->format('d/m/Y') ?? '—' }}</small></td>
     <td><span class="cfa-pill">{{ $file->status_label }}</span>@if($file->exception_flag)<small class="cfa-warn-t">{{ \App\Models\ContractFile::EXCEPTION_FLAGS[$file->exception_flag] ?? $file->exception_flag }}</small>@endif</td>
     <td>{{ $file->vendor?->name ?? '—' }}<small>Cost {{ $money($file->total_file_cost) }}</small></td>
     <td class="num">{{ $money($file->net_client_charge) }}</td>
     <td class="num cfa-good">{{ $money($row['received']) }}</td>
     <td class="num {{ $row['client_due']>0 ? 'cfa-due' : '' }}">{{ $money($row['client_due']) }}</td>
     <td><a class="cfac-pick" data-file-sheet="{{ $file->id }}" href="{{ route('role.contract-files.show',['role'=>$role,'contractFile'=>$file]) }}" aria-label="View {{ $file->file_number }}" title="Open the file record"><i class="fas fa-eye"></i></a></td>
    </tr>
   @endforeach
   <tr id="cfac-due-empty" @if($dueFiles->isNotEmpty()) hidden @endif><td colspan="9" class="cfa-empty"><i class="fas fa-circle-check"></i>No outstanding files for this selection.</td></tr>
   </tbody>
   <tfoot class="cfa-pl-foot"><tr><td colspan="5" id="cfac-due-total-label">Total for all agents / clients</td><td class="num" id="cfac-due-charge">{{ $money($dueFiles->sum(fn($r)=>$r['file']->net_client_charge)) }}</td><td class="num" id="cfac-due-paid">{{ $money($dueFiles->sum('received')) }}</td><td class="num" id="cfac-due-total">{{ $money($dueFiles->sum('client_due')) }}</td><td></td></tr></tfoot>
  </table></div>
 </div>
</section>
<style>
#cfac-client .cfac-agent-row { cursor: pointer; }
#cfac-client .cfac-agent-row.is-on td { background: #eef2ff; }
#cfac-client .cfac-agent-row.is-on td:first-child { box-shadow: inset 3px 0 #4f46e5; }
#cfac-client .cfac-agent-row:focus-visible { outline: 2px solid #6366f1; outline-offset: -2px; }
#cfac-client .cfac-collected { height: 6px; min-width: 110px; border-radius: 5px; background: #e2e8f0; overflow: hidden; margin-top: 5px; }
#cfac-client .cfac-collected span { display: block; height: 100%; background: #22c55e; }
#cfac-client .cfac-pick { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 24px; border: 1px solid #dbe3ef; border-radius: 6px; background: #fff; color: #64748b; font-size: .72rem; cursor: pointer; text-decoration: none; }
#cfac-client .cfac-pick:hover { background: #eef2ff; border-color: #a5b4fc; color: #4338ca; }
#cfac-client #cfac-due-clear[hidden] { display: none; }
#cfac-client #cfac-due-files { scroll-margin-top: 18px; }
#cfac-client tr[data-cfac-due-agent][hidden], #cfac-client tr#cfac-due-empty[hidden] { display: none; }
#cfac-client .cfa-card-h { flex-wrap: wrap; }
</style>
