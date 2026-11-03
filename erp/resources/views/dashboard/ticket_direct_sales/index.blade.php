@extends('layout.app')
@section('meta-information')<title>Ticket Orders</title>@endsection
@section('css')
<style>.to-table tr.to-sibling td{border-top:1px dashed #e2e8f0;background:#fafaff;}</style>
<style>
.to-card-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px;margin-bottom:14px}.to-card{min-height:82px;padding:11px 13px!important;transition:.15s;display:block}.to-card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(30,50,100,.1)}.to-card.active{outline:2px solid #4f46e5;outline-offset:-2px}.to-card-top{display:flex;align-items:center;gap:9px}.to-card-icon{width:32px!important;height:32px!important;flex:0 0 32px}.to-card-number{margin-left:auto;font-size:21px!important;line-height:1}.to-card-label{margin-top:8px!important;font-size:10px!important}.to-card-sub{font-size:10px!important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.to-table td{font-size:12px;color:#374151}.to-table tr:hover td{background:#f8fafc}span[aria-current="page"] span{background:#2563eb!important;color:#fff!important}@media(max-width:1100px){.to-card-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}@media(max-width:700px){.to-card-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:430px){.to-card-grid{grid-template-columns:1fr}}
</style>
<style>
@include('contract-flights.partials.ticket-legs-styles')
/* Ticket Orders rows (2026-10-05): same cell discipline as Flights & Passengers */
.to-table td{padding:9px 8px;vertical-align:top;font-size:.78rem;color:#334155;height:74px;box-sizing:border-box;}
.to-table th{white-space:nowrap;}
.to-table .to-sl{color:#94a3b8;font-size:.72rem;width:34px;}
.to-table{table-layout:fixed;width:100%;}
.to-table th:nth-child(1){width:3.5%}.to-table th:nth-child(2){width:15%}.to-table th:nth-child(3){width:13.5%}.to-table th:nth-child(4){width:16%}
.to-table th:nth-child(5){width:12.5%}.to-table th:nth-child(6){width:16%}.to-table th:nth-child(7){width:8.5%}.to-table th:nth-child(8){width:8.5%}.to-table th:nth-child(9){width:6.5%}
.to-table td{overflow:hidden;}
.to-table .to-c-num{text-align:right;white-space:nowrap;}
.to-table .to-strong{font-weight:600;color:#1e293b;}
.to-table .to-strong a,.to-table .to-c-pax .mfp-name a{color:#1e293b;}
.to-table .to-strong a:hover,.to-table .to-c-pax .mfp-name a:hover{color:#4f46e5;}
.to-table .to-clip{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.to-table .to-leg{color:#7c3aed;font-weight:600;}
.to-table .to-st{font-weight:700;font-size:.78rem;}
.to-table .to-st-ok{color:#059669;} .to-table .to-st-hold{color:#2563eb;} .to-table .to-st-warn{color:#d97706;}
.to-table .to-st-bad{color:#dc2626;} .to-table .to-st-mute{color:#64748b;}
.to-table .to-st-bad-ln{color:#ef4444;}
.to-table .to-party{display:flex;align-items:center;gap:6px;color:#475569;}
.to-table .to-party-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.to-table .to-code{flex:none;font-size:.62rem;color:#a5b4fc;}
.to-table a.to-code{color:#6366f1;}
.to-table .to-ok{color:#059669;} .to-table .to-bad{color:#dc2626;} .to-table .to-mute{color:#94a3b8;}
.to-table .to-c-num .mfp-ln{text-align:right;}
.to-table .to-act{width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;border-radius:6px;font-size:11px;line-height:1;}
.to-table .to-act-blue{border-color:#bfdbfe;background:#eff6ff;color:#2563eb;}
.to-table tbody tr:hover td{background:#fafbff;}
/* Change button (2026-10-06): spans both action columns; 24px tall like the icons so every row keeps one height */
.to-table .to-act-wide{grid-column:1 / -1;width:52px;height:24px;display:inline-flex;align-items:center;justify-content:center;gap:3px;border:1px solid #c7d2fe;border-radius:6px;background:#eef2ff;color:#4338ca;font-size:9.5px;line-height:1;cursor:pointer;padding:0;}
.to-table .to-act-wide span{font-size:8.5px;font-weight:700;letter-spacing:.01em;}
.to-table .to-act-wide:hover{background:#e0e7ff;border-color:#818cf8;}
.to-table .to-chg{color:#0f766e;}
/* Return window (owner, 2026-10-15): yellow always; the last 5 hours brighter, pulsing; passed red; done grey */
.to-table .mfp-ln.to-ret{width:max-content;max-width:100%;margin-top:1px;padding:0 6px;border-radius:4px;background:#fef08a;color:#854d0e;font-weight:600;}
.to-table .mfp-ln.to-ret .mfp-ico{color:#a16207;}
.to-table .mfp-ln.to-ret.urgent{background:#facc15;color:#422006;font-weight:800;animation:to-ret-pulse 1.1s ease-in-out infinite;}
.to-table .mfp-ln.to-ret.urgent .mfp-ico{color:#422006;}
@keyframes to-ret-pulse{0%,100%{box-shadow:0 0 0 0 rgba(234,179,8,.75);background:#facc15;}50%{box-shadow:0 0 0 4px rgba(234,179,8,0);background:#fde047;}}
.to-table .mfp-ln.to-ret.late{background:none;padding:0;color:#dc2626;} .to-table .mfp-ln.to-ret.late .mfp-ico{color:#dc2626;}
.to-table .mfp-ln.to-ret.done{background:none;padding:0;color:#64748b;font-weight:400;} .to-table .mfp-ln.to-ret.done .mfp-ico{color:#64748b;}
.to-card{min-width:0;text-decoration:none;box-shadow:0 1px 4px rgba(30,50,100,.08),0 1px 2px rgba(30,50,100,.04);}.to-card-label{white-space:nowrap;}
</style>
@endsection
@section('main-content')
@include('layout.ticketing-tabs')
@php $role=Str::slug(Auth::user()->getRoleNames()->first()); $company=request('company');
 $partyUrl=route('role.party-statement.index',['role'=>$role]);
 try { $cfListUrl=route('role.contract-flights.index',['role'=>$role]); } catch (\Throwable $e) { $cfListUrl=null; } @endphp
{{-- One row, one fact per tile (owner, 2026-10-06: "why that many, why redundancy, why two rows"):
     2026-10-07: All Orders = the filter bar's All; Not Sold / Rescheduled dropped too.
     Cancelled = Refunded + Void, Expired /
     Cancelled are in the status dropdown — so they are not tiles. --}}
<section class="to-card-grid">
@foreach([
 ['booking','fa-clock','Booked','On hold, not purchased',['#eef2ff','#e0e7ff','#4f46e5','#312e81']],
 ['issued','fa-ticket','Issued','Purchased',['#ecfdf5','#d1fae5','#059669','#064e3b']],
 ['reissued','fa-arrows-rotate','Re-issued','Same PNR',['#f0fdfa','#ccfbf1','#0d9488','#134e4a']],
 ['refunded','fa-rotate-left','Refunded','Penalty kept',['#fff1f2','#ffe4e6','#e11d48','#881337']],
 ['void','fa-ban','Void','Full refund',['#fdf4ff','#fae8ff','#c026d3','#701a75']],
 ['departed','fa-plane-departure','Departed','Travel date passed',['#f8fafc','#e2e8f0','#475569','#0f172a']],
 ['due','fa-hand-holding-dollar','Seller Due','৳'.number_format($stats['due_amount'] ?? 0, 0),['#fffbeb','#fef3c7','#d97706','#78350f']],
] as [$key,$icon,$label,$sub,[$bg,$ibg,$fg,$num]])
<a href="{{route('role.ticket-purchase.index',array_filter(['role'=>$role,'company'=>$company,'view'=>($key==='all'||$view===$key)?null:$key]))}}" class="to-card {{$view===$key?'active':''}} rounded-xl" style="background:{{$bg}};border:1px solid {{$ibg}}" title="{{$label}} — {{$sub}}">
 <div class="to-card-top"><span class="to-card-icon rounded-lg flex items-center justify-center" style="background:{{$ibg}};color:{{$fg}}"><i class="fas {{$icon}}"></i></span><span class="to-card-number font-black" style="color:{{$num}}">{{$stats[$key] ?? 0}}</span></div><div class="to-card-label font-bold uppercase" style="color:{{$fg}}">{{$label}}</div><div class="to-card-sub text-slate-500 mt-1">{{$sub}}</div>
</a>@endforeach
</section>
@if(session('success'))<div class="bg-emerald-50 text-emerald-700 text-sm px-4 py-3 rounded-xl mb-4">{{session('success')}}</div>@endif
<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4"><span class="h-11 w-11 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fas fa-list-alt"></i></span><div class="flex-1"><h1 class="text-lg font-bold text-slate-900">Ticket Orders</h1><p class="text-xs text-slate-500">Bookings and purchases in one searchable register</p></div>@canany(['create ticket purchase','create ticket direct sale'])<a href="{{route('role.ticket-direct-sale.create',array_filter(['role'=>$role,'company'=>$company]))}}" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"><i class="fas fa-plus mr-1"></i> Add Entry</a>@endcanany</header>
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
 <form method="GET">@if($company)<input type="hidden" name="company" value="{{$company}}">@endif @if($view!=='all')<input type="hidden" name="view" value="{{$view}}">@endif
 <x-ticketing-filter-bar search="search" placeholder="Search reference, PNR or passenger…" :reset="route('role.ticket-purchase.index',array_filter(['role'=>$role,'company'=>$company]))" :total="$orders->total()"><select name="ticket_id" onchange="this.form.requestSubmit()" aria-label="Route"><option value="">All Routes</option>@foreach($tickets as $ticket)<option value="{{$ticket->id}}" @selected(request('ticket_id')==$ticket->id)>{{$ticket->title}}</option>@endforeach</select><select name="status" onchange="this.form.requestSubmit()" aria-label="Status"><option value="">All Status</option>@foreach(['booked'=>'Reserved','purchased'=>'Purchased','pending'=>'Pending','draft'=>'Draft','expired'=>'Expired','cancelled'=>'Cancelled'] as $key=>$label)<option value="{{$key}}" @selected(request('status')===$key)>{{$label}}</option>@endforeach</select></x-ticketing-filter-bar></form>
 <div class="overflow-x-auto" data-tfb-swap><table class="min-w-full divide-y divide-slate-200 to-table fd-table" data-cf-sort><thead class="bg-slate-50"><tr>@foreach([['SL',''],['Ticket',''],['Passenger',''],['Route',''],['Status',''],['Vendor & Agent',''],['Cost','text-right'],['Sale / Profit','text-right'],['','']] as [$h,$al])<th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500 {{$al}}">{{$h}}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">
 @forelse($orders as $i=>$order)

 @include('dashboard.ticket_direct_sales.partials.order-row')
 @empty<tr><td colspan="9" class="p-12 text-center"><i class="fas fa-inbox text-4xl text-slate-300"></i><p class="mt-3 text-sm font-semibold text-slate-500">No ticket orders found</p></td></tr>@endforelse
 </tbody></table></div><div class="p-4" data-tfb-swap>{{$orders->links()}}</div>
</div>
@endsection
@section('js')<script>
// Just saved from Ticket Entry (?new=purchase-12,booking-3): light the new rows up and
// scroll to them (owner, 2026-10-11). The highlight fades after a few seconds.
(function () {
  const ids = (new URLSearchParams(location.search).get('new') || '').split(',').filter(Boolean);
  if (!ids.length) return;
  const st = document.createElement('style');
  st.textContent = '@keyframes toNewRow{0%,60%{background:#dcfce7}100%{background:transparent}}'
    + 'tr.to-new-row>td{animation:toNewRow 6s ease-out forwards}tr.to-new-row>td:first-child{box-shadow:inset 4px 0 0 #16a34a}';
  document.head.appendChild(st);
  const mark = () => {
    const rows = ids.map(id => document.querySelector(`tr[data-order="${CSS.escape(id)}"]`)).filter(Boolean);
    rows.forEach(r => r.classList.add('to-new-row'));
    if (rows[0]) rows[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    return rows.length;
  };
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', mark) : mark();
  // drop ?new= so a refresh does not light them up again
  const u = new URL(location.href); u.searchParams.delete('new'); history.replaceState(null, '', u);
})();
const FD_LOGO_BASE = @json(asset('airline-logos'));
@include('contract-flights.partials.airline-brands-script')
@include('contract-flights.partials.ticket-logos-script')
if (window.mfpTicketLogos) mfpTicketLogos();
function toCountdowns(){document.querySelectorAll('[data-expiry]').forEach(el=>{if(el.dataset.active!=='1')return;const end=new Date(el.dataset.expiry),out=el.querySelector('.countdown');function tick(){let ms=end-new Date;if(ms<=0){out.textContent='EXPIRED';out.className='countdown text-[10px] font-bold text-red-600';return}const d=Math.floor(ms/86400000);ms%=86400000;const h=Math.floor(ms/3600000),m=Math.floor(ms%3600000/60000);out.textContent=(d?d+'d ':'')+h+'h '+m+'m left'}tick();setInterval(tick,60000)})}
toCountdowns();
// Live search swapped the rows in (x-ticketing-filter-bar): logos and holds again.
document.addEventListener('tfb:updated',()=>{if(window.mfpTicketLogos)mfpTicketLogos();toCountdowns();});</script>
@if($changeOn ?? false)
@include('dashboard.ticket_direct_sales.partials.ticket-change-script')
{{-- Change → All Trails and Edit (2026-10-26): super admin, and only once the table and routes exist --}}
@if(\App\Support\UserRoleSetting\Access::allows('travels.ticketing', 'e') && \App\Models\TicketTrailLog::available())
@include('dashboard.ticket_direct_sales.partials.ticket-trail-script')
@endif
@endif
@endsection
