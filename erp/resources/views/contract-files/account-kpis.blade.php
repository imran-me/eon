{{-- File Accounts' six summaries for one set of files, drawn as Flight
     Accounts' KPI strip (.cfa-kpis): the page's period (the current month, or
     the submit-date range) at the top, and each opened month under Past
     months. Needs $kpiRows (ContractFileReporting rows), $kpiPayables (their
     payables), $kpiLabel and $money. --}}
@php
 $kRevenue=$kpiRows->sum('revenue'); $kCost=$kpiRows->sum('cost'); $kProfit=$kRevenue-$kCost;
 $kClients=$kpiRows->filter(fn($r)=>$r['client_due']>0)->groupBy(fn($r)=>$r['file']->client_id ?? 'unassigned')->count();
 $kVendors=$kpiPayables->filter(fn($r)=>$r['due']>0 || ($r['credit']??0)>0)->groupBy(fn($r)=>$r['party']?->id ?? 'unassigned')->count();
 $kReceived=$kpiRows->sum('received');
@endphp
<div class="cfa-kpis" title="{{ $kpiLabel }} — earlier months are under Profit & Loss → Past months">
 <div class="cfa-kpi in"><div class="l">Revenue (posted charges)</div><div class="v">{{ $money($kRevenue) }}</div><div class="s"><b class="cfa-kpi-mo">{{ $kpiLabel }}</b> · {{ $kpiRows->count() }} files</div></div>
 <div class="cfa-kpi out"><div class="l">Total cost</div><div class="v">{{ $money($kCost) }}</div><div class="s">Net posted vendor cost</div></div>
 <div class="cfa-kpi {{ $kProfit < 0 ? 'bad' : 'good' }}"><div class="l">Gross profit</div><div class="v">{{ $money($kProfit) }}</div><div class="s">Margin {{ $kRevenue ? number_format($kProfit/$kRevenue*100,1).'%' : '—' }}</div></div>
 <div class="cfa-kpi good"><div class="l">Received from clients</div><div class="v">{{ $money($kReceived) }}</div><div class="s">{{ $kRevenue > 0 ? number_format($kReceived/$kRevenue*100,0).'% of revenue' : 'Recorded file receipts' }}</div></div>
 <div class="cfa-kpi due"><div class="l">Agent / client due</div><div class="v">{{ $money($kpiRows->sum('client_due')) }}</div><div class="s">{{ $kClients }} {{ $kClients === 1 ? 'party' : 'parties' }}</div></div>
 <div class="cfa-kpi due"><div class="l">Vendor due</div><div class="v">{{ $money($kpiRows->sum('vendor_due')) }}</div><div class="s">{{ $kVendors }} {{ $kVendors === 1 ? 'vendor' : 'vendors' }}</div></div>
</div>
