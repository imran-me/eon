@php
 $rows=$fileAccounts['rows'];
 // Flight Accounts' money: whole taka, a real minus (owner, 2026-10-25: file looks like flight).
 $money=fn($v)=>((float) $v < 0 ? '−' : '').'৳'.number_format(abs((float) $v), 0);
 $cfacDate=function($v){ try { return $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—'; } catch (\Throwable $e) { return (string) $v; } };
 $clientGroups=$rows->filter(fn($r)=>$r['client_due']>0)->groupBy(fn($r)=>$r['file']->client_id ?? 'unassigned');
 $vendorGroups=$fileAccounts['payables']->filter(fn($r)=>$r['due']>0 || ($r['credit']??0)>0)->groupBy(fn($r)=>$r['party']?->id ?? 'unassigned');
 // Agent / Client filter: only the parties Contract File did business with (owner, 2026-10-17); the one
 // chosen stays listed. A failed lookup lists everyone, as before.
 try { $cfacIds=\App\Services\DepartmentParties::ids('contract_file'); } catch (\Throwable $e) { report($e); $cfacIds=null; }
 $cfacClients=$cfacIds===null ? $clients : $clients->filter(fn($c)=>in_array((int)$c->id,$cfacIds,true) || (string)request('client_id')===(string)$c->id);
 // P&L period (owner, 2026-10-15): the current month, or the submit-date range when one is set.
 // Every other month is a row of Past months; opening one shows its own summaries and files.
 $nowMonth=now()->format('Y-m');
 $ranged=request()->filled('from_date') || request()->filled('to_date');
 $topRows=$ranged ? $rows : $rows->filter(fn($r)=>$r['file']->submit_date?->format('Y-m')===$nowMonth)->values();
 $topLabel=$ranged ? (request('from_date') ? \Illuminate\Support\Carbon::parse(request('from_date'))->format('d M Y') : 'Start').' – '.(request('to_date') ? \Illuminate\Support\Carbon::parse(request('to_date'))->format('d M Y') : 'Today') : now()->format('M Y');
 $payablesOf=function($set) use($fileAccounts){ $ids=$set->map(fn($r)=>$r['file']->id)->flip(); return $fileAccounts['payables']->filter(fn($p)=>$p['file'] && isset($ids[$p['file']->id])); };
 $pastMonths=$fileAccounts['monthly']->reject(fn($items,$month)=>$month===$nowMonth)->sortBy(fn($items,$month)=>$month==='Undated' ? '0000' : $month)->reverse();
 $pastAll=$pastMonths->flatten(1);
 $txLabels=[
  \App\Models\ContractFile::CLIENT_LEDGER_TYPE => ['Client charge', 'in'],
  \App\Models\ContractFile::CLIENT_ADJUSTMENT_TYPE => ['Client adjustment', 'in'],
  \App\Models\ContractFile::VENDOR_LEDGER_TYPE => ['File cost', 'out'],
  \App\Models\ContractFile::VENDOR_ADJUSTMENT_TYPE => ['Vendor adjustment', 'out'],
  'party_payment' => ['Payment', 'pay'],
 ];
 $canSales=auth()->user()->canAny(['view contract file sale','view all contract file sale']) && \Illuminate\Support\Facades\Route::has('role.contract-file-sales.index');
@endphp
{{-- File Accounts in Flight Accounts' dress (owner, 2026-10-25): the KPI strip,
     the tab band, and each sheet as a .cfa-card with a .cfa-table — styles are
     the flight's own partials/accounts-styles. The file's period filter sits
     on top in the standard bar, because every sheet below reads it. --}}
<div class="cfa-wrap cfac" id="cfac-workspace">
 <div class="cfa-card">
  <form class="fdl-bar cfac-filter" method="GET" role="search" aria-label="Filter file accounts">
   <div class="fdl-search"><i class="fas fa-magnifying-glass"></i><input type="search" name="search" value="{{ request('search') }}" placeholder="Search file, applicant, agent or vendor…" aria-label="Search file accounts"></div>
   <select name="client_id" aria-label="Agent / Client"><option value="">All agents / clients</option>@foreach($cfacClients as $client)<option value="{{ $client->id }}" @selected(request('client_id')==$client->id)>{{ $client->name }}</option>@endforeach</select>
   <div class="fdl-dates">
    <input type="date" name="from_date" value="{{ request('from_date') }}" aria-label="Submitted from" title="Submission date — from"><span>to</span><input type="date" name="to_date" value="{{ request('to_date') }}" aria-label="Submitted to" title="Submission date — to">
    @if(request()->boolean('unassigned'))<input type="hidden" name="unassigned" value="1">@endif
    <button type="submit" class="fdl-go">Apply</button>
    <a class="fdl-print" href="{{ route('role.contract-files.accounts',['role'=>$role]) }}"><i class="fas fa-rotate-left"></i> Reset</a>
    <button type="button" class="fdl-print" onclick="window.print()" title="Print the sheet on screen"><i class="fas fa-print"></i> Print</button>
   </div>
  </form>
 </div>

 @include('contract-files.account-kpis',['kpiRows'=>$topRows,'kpiPayables'=>$payablesOf($topRows),'kpiLabel'=>$topLabel])

 <div class="cfa-tabs" role="tablist" aria-label="File account reports">
  @foreach(['profit'=>['Profit & Loss',$rows->count(),'fa-scale-balanced'],'client'=>['Agent / Client Due Sheet',$clientGroups->count(),'fa-user-tie'],'vendor'=>['Vendor Due Sheet',$vendorGroups->count(),'fa-truck-field'],'transactions'=>['Transactions',$fileAccounts['transactions']->count(),'fa-right-left'],'journals'=>['Journal History',$fileAccounts['journals']->count(),'fa-book']] as $key=>[$label,$count,$icon])
   <button type="button" class="cfa-tab {{ $loop->first ? 'is-on' : '' }}" data-cfac-tab="{{ $key }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="cfac-{{ $key }}"><span class="ic"><i class="fas {{ $icon }}"></i></span><span class="t">{{ $label }}</span> <span class="n">{{ $count }}</span></button>
  @endforeach
  @if($canSales)<a class="cfa-tab" href="{{ route('role.contract-file-sales.index',['role'=>$role]) }}"><span class="ic"><i class="fas fa-receipt"></i></span><span class="t">Sales History</span></a>@endif
 </div>

 <section class="cfa-panel" id="cfac-profit" data-cfac-panel="profit">
  <div class="cfa-card">
   <div class="cfa-card-h"><h3>File by file</h3><small>{{ $topLabel }} · by submission date · net journal revenue and cost, including adjustments</small></div>
   @include('contract-files.account-file-table',['tableRows'=>$topRows,'emptyText'=>($ranged ? 'No files in this range.' : 'No files submitted in '.$topLabel).($pastMonths->isNotEmpty() ? ' Earlier months are under Past months below.' : '')])
  </div>
  <div class="cfa-card">
   <div class="cfa-card-h"><h3>Past months</h3><small>By submission month · click a month for its full profit &amp; loss</small></div>
   <div class="cfa-scroll"><table class="cfa-table" id="cfac-months">
    <thead><tr><th>Month</th><th class="num">Files / Applicants</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Client due</th><th class="num">Vendor due</th></tr></thead>
    <tbody>
    @forelse($pastMonths as $month=>$items)
     @php
      $monthLabel=$month==='Undated' ? 'Undated' : \Illuminate\Support\Carbon::parse($month.'-01')->format('M Y');
      $mProfit=$items->sum('profit'); $mRevenue=$items->sum('revenue');
     @endphp
     <tr class="cfa-pl-month cfac-month" tabindex="0" role="button" aria-expanded="false" title="Show {{ $monthLabel }}'s profit &amp; loss">
      <td class="b"><i class="fas fa-chevron-right cfa-pl-mi"></i> {{ $monthLabel }}</td>
      <td class="num">{{ $items->count() }}</td>
      <td class="num cfa-in">{{ $money($mRevenue) }}</td>
      <td class="num cfa-out">{{ $money($items->sum('cost')) }}</td>
      <td class="num {{ $mProfit<0 ? 'cfa-bad' : 'cfa-good' }}">{{ $money($mProfit) }}</td>
      <td class="num">{{ $mRevenue ? number_format($mProfit/$mRevenue*100,1).'%' : '—' }}</td>
      <td class="num {{ $items->sum('client_due')>0 ? 'cfa-due' : '' }}">{{ $money($items->sum('client_due')) }}</td>
      <td class="num {{ $items->sum('vendor_due')>0 ? 'cfa-due' : '' }}">{{ $money($items->sum('vendor_due')) }}</td>
     </tr>
     <tr class="cfa-jr cfa-pl-mdetail cfac-mdetail" hidden><td colspan="8">
      @include('contract-files.account-kpis',['kpiRows'=>$items,'kpiPayables'=>$payablesOf($items),'kpiLabel'=>$monthLabel])
      <div class="cfa-card cfa-pl-mrows">
       <div class="cfa-card-h"><h3>File by file</h3><small>{{ $monthLabel }}</small></div>
       @include('contract-files.account-file-table',['tableRows'=>$items,'emptyText'=>'No files.'])
      </div>
     </td></tr>
    @empty
     <tr><td colspan="8" class="cfa-empty"><i class="fas fa-calendar"></i>No earlier months yet.</td></tr>
    @endforelse
    </tbody>
    @if($pastMonths->isNotEmpty())
    <tfoot class="cfa-pl-foot"><tr><td>Total · {{ $pastMonths->count() }} {{ $pastMonths->count()===1 ? 'month' : 'months' }}</td><td class="num">{{ $pastAll->count() }}</td><td class="num">{{ $money($pastAll->sum('revenue')) }}</td><td class="num">{{ $money($pastAll->sum('cost')) }}</td><td class="num">{{ $money($pastAll->sum('profit')) }}</td><td class="num">{{ $pastAll->sum('revenue') ? number_format($pastAll->sum('profit')/$pastAll->sum('revenue')*100,1).'%' : '—' }}</td><td class="num">{{ $money($pastAll->sum('client_due')) }}</td><td class="num">{{ $money($pastAll->sum('vendor_due')) }}</td></tr></tfoot>
    @endif
   </table></div>
  </div>
 </section>

 @include('contract-files.client-due-sheet')
 @include('contract-files.vendor-due-sheet')

 <section class="cfa-panel" id="cfac-transactions" data-cfac-panel="transactions" hidden>
  <div class="cfa-card">
   <div class="cfa-card-h"><h3>Ledger transactions</h3><small>File-linked entries · running balance is the party's overall balance</small></div>
   <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
    <thead><tr><th>Date</th><th>Party</th><th>Type</th><th>Reference</th><th>Remarks</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
    <tbody>
    @forelse($fileAccounts['transactions'] as $tx)
     @php [$txLabel, $txDir] = $txLabels[$tx->type] ?? [\Illuminate\Support\Str::headline((string) $tx->type), 'muted']; @endphp
     <tr>
      <td>{{ $cfacDate($tx->payment_date) }}</td>
      <td class="b">@if($tx->user_id)<a href="{{ route('role.party-statement.index',['role'=>$role]) }}?party_id={{ $tx->user_id }}">{{ $tx->user?->name ?? '—' }}</a>@else{{ $tx->user?->name ?? '—' }}@endif<small>{{ $tx->user_type ? ucfirst($tx->user_type) : '' }}</small></td>
      <td><span class="cfa-pill {{ ['in' => '', 'out' => 'warn', 'pay' => 'ok'][$txDir] ?? 'muted' }}">{{ $txLabel }}</span></td>
      <td>{{ $tx->reference_no ?: '—' }}</td>
      <td>{{ $tx->remarks ?: '—' }}</td>
      <td class="num {{ $tx->debit > 0 ? 'cfa-in' : '' }}">{{ $tx->debit > 0 ? $money($tx->debit) : '—' }}</td>
      <td class="num {{ $tx->credit > 0 ? 'cfa-good' : '' }}">{{ $tx->credit > 0 ? $money($tx->credit) : '—' }}</td>
      <td class="num">{{ $money($tx->balance) }}</td>
     </tr>
    @empty
     <tr><td colspan="8" class="cfa-empty"><i class="fas fa-right-left"></i>No file-linked transactions found.</td></tr>
    @endforelse
    </tbody>
   </table></div>
  </div>
 </section>

 <section class="cfa-panel" id="cfac-journals" data-cfac-panel="journals" hidden>
  <div class="cfa-card">
   <div class="cfa-card-h"><h3>Journal entries</h3><small>The general-ledger postings behind contract files — each entry with its debit and credit lines</small></div>
   <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
    <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="num">Amount</th></tr></thead>
    <tbody>
    @forelse($fileAccounts['journals'] as $journal)
     <tr>
      <td>{{ $journal->date?->format('d/m/Y') ?? '—' }}<small>#{{ $journal->id }}</small></td>
      <td class="b">{{ $journal->reference ?: '—' }}</td>
      <td>{{ $journal->description ?: '—' }}</td>
      <td class="num">{{ $money($journal->items->sum('debit')) }}</td>
     </tr>
     <tr class="cfa-jr"><td colspan="4"><table>
      @foreach($journal->items as $item)
       <tr><td class="acc" style="width:90px">{{ $item->account?->code }}</td><td>{{ $item->account?->name ?? 'Account #'.$item->account_id }}{{ $item->note ? ' — '.$item->note : '' }}</td><td class="num" style="width:130px">{{ $item->debit > 0 ? 'Dr '.$money($item->debit) : '' }}</td><td class="num" style="width:130px">{{ $item->credit > 0 ? 'Cr '.$money($item->credit) : '' }}</td></tr>
      @endforeach
     </table></td></tr>
    @empty
     <tr><td colspan="4" class="cfa-empty"><i class="fas fa-book"></i>No posted file journals found.</td></tr>
    @endforelse
    </tbody>
   </table></div>
  </div>
 </section>
</div>
<style>
#cfac-workspace .cfac-filter { border-bottom: 0; }
#cfac-workspace .cfac-filter select { flex: 0 0 210px; width: auto; }
#cfac-workspace .cfac-filter select, #cfac-workspace .cfac-filter input[type="date"] { height: 34px; padding: 0 9px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; font-size: 12px; color: #334155; outline: none; box-sizing: border-box; }
#cfac-workspace .cfac-filter select:focus, #cfac-workspace .cfac-filter input[type="date"]:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
#cfac-workspace .cfa-panel[hidden], #cfac-workspace tr[hidden] { display: none; }
#cfac-months > tbody > tr.cfa-pl-mdetail:hover { background: transparent; box-shadow: none; }
#cfac-months .cfa-pl-mdetail .cfa-kpis { margin-bottom: 12px; }
@media print { #cfac-workspace .cfac-filter, #cfac-workspace .cfa-tabs, .ex-toolbar { display: none !important; } #cfac-workspace [data-cfac-panel][hidden] { display: block !important; } }
</style>
<script>
(() => {
 const root = document.getElementById('cfac-workspace');
 const tabs = root.querySelectorAll('button[data-cfac-tab]'), panels = root.querySelectorAll('[data-cfac-panel]');
 // One sheet at a time, remembered in the hash — as Flight Accounts.
 function show(name) {
  let found = false;
  panels.forEach(p => { const on = p.dataset.cfacPanel === name; p.hidden = !on; found = found || on; });
  if (!found) return show('profit');
  tabs.forEach(t => { const on = t.dataset.cfacTab === name; t.classList.toggle('is-on', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); });
 }
 tabs.forEach(t => t.addEventListener('click', () => { show(t.dataset.cfacTab); history.replaceState(null, '', '#' + t.dataset.cfacTab); }));
 show((location.hash || '#profit').slice(1));
 root.querySelectorAll('.cfac-month').forEach(row => {
  const toggle = () => { const detail = row.nextElementSibling; detail.hidden = !detail.hidden; row.classList.toggle('is-open', !detail.hidden); row.setAttribute('aria-expanded', String(!detail.hidden)); if (!detail.hidden) row.scrollIntoView({ behavior: 'smooth', block: 'start' }); };
  row.addEventListener('click', e => { if (!e.target.closest('a')) toggle(); });
  row.addEventListener('keydown', e => { if (e.target === row && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); toggle(); } });
 });
})();
</script>
@include('contract-files.client-due-script')
