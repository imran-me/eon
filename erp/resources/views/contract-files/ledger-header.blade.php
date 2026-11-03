{{-- The agent / client file ledger's head, drawn as the Flight Dashboard's
     Passenger Ledger: the control strip (.fd-bar), then the account card
     (.fdl-header — who, what is owed, the actions) and its figures rail
     (.fdl-meta). Styles: contract-flights.dashboard-ledger-styles + the copied
     .fd-bar rules in flight-dashboard-styles. No script: this block is also
     fetched into the dashboard, where inline scripts do not run — so the
     Status toggle is an onclick. --}}
@php
 $ledgerName = $groupClient?->name ?? 'Direct / Unassigned';
 $ledgerInitials = $groupClient ? collect(preg_split('/\s+/', trim(preg_replace('/^(mr|mrs|ms|md)\.?\s+/i', '', $ledgerName))))->filter()->take(2)->map(fn($w)=>mb_strtoupper(mb_substr($w,0,1)))->implode('') : '';
 $ledgerVendorDue = (float) $groupFiles->sum('total_vendor_due');
 $ledgerVendors = $groupFiles->pluck('vendor.name')->filter()->unique();
 $ledgerVendorKind = ($ledgerVendors->isEmpty() && ! $ledgerVendorDue) ? 'none' : ($ledgerVendorDue > 0 ? 'out' : 'in');
 $ledgerCharge = (float) $groupFiles->sum('net_client_charge');
 $ledgerCost = (float) $groupFiles->sum('total_file_cost');
 $ledgerProfit = $ledgerCharge - $ledgerCost;
 $ledgerExpected = $groupFiles->pluck('expected_out_date')->filter()->sort()->first();
 $ledgerBoardUrl = route('role.contract-files.board',['role'=>$role,'client_id'=>$groupClient?->id,'unassigned'=>$groupClient ? null : 1]);
 $ledgerFig = fn($v) => ($v < 0 ? '−' : '').'৳'.number_format(abs($v));
@endphp
<div class="fd-bar">
 <a class="fd-tool" href="{{ route('role.contract-files.dashboard',['role'=>$role]) }}" aria-label="Back to File Dashboard" title="Back to File Dashboard"><i class="fas fa-arrow-left"></i></a>
 <h3 tabindex="-1">File &amp; Passenger Ledger</h3>
 <span class="fd-count">{{ $groupFiles->count() }} files / applicants</span>
 <button type="button" class="fdl-status-btn" style="margin-left:auto;" aria-pressed="true" title="Show / hide journey stages"
  onclick="const on=this.getAttribute('aria-pressed')!=='true';this.setAttribute('aria-pressed',String(on));this.closest('[data-file-group-ledger]').querySelectorAll('.mfp-journey-row').forEach(r=>r.hidden=!on)"><i class="fas fa-route"></i> Status</button>
 <button type="button" class="fd-tool" data-cf-ledger-refresh onclick="if (!document.getElementById('cf-dashboard-ledger')) location.reload()" aria-label="Reload files" title="Reload files"><i class="fas fa-rotate"></i></button>
</div>

<div class="fdl-header">
 <div class="fdl-head-top">
  <div class="fdl-zone fdl-zone-id">
   <span class="fdl-avatar is-mono{{ $ledgerInitials === '' ? ' is-glyph' : '' }}"><span class="fdl-mono">{{ $ledgerInitials }}</span><i class="fas fa-user-tie" aria-hidden="true"></i></span>
   <div class="fdl-idcol">
    <h2 class="fdl-name">{{ $ledgerName }}</h2>
    <div class="fdl-line"><i class="fas fa-location-dot"></i><span>{{ $groupFiles->pluck('country.name')->filter()->unique()->implode(' · ') ?: 'Country not assigned' }}</span></div>
    <div class="fdl-line"><span class="fdl-k">Group</span><span class="fdl-ac">{{ $groupClient ? 'EP-AG-'.str_pad($groupClient->id,3,'0',STR_PAD_LEFT) : '—' }}</span><span class="fdl-type">{{ $groupClient ? 'Agent / Client' : 'Unassigned' }}</span></div>
    @if($groupClient?->phone)<div class="fdl-line"><i class="fas fa-phone"></i><span>{{ $groupClient->phone }}</span></div>@endif
   </div>
  </div>
  <div class="fdl-zone fdl-zone-co">
   <div>
    <span class="fdl-lbl">Categories</span>
    <div class="fdl-co" style="margin-top:6px;"><span class="ci"><i class="fas fa-folder"></i></span><span class="fdl-co-name">{{ $groupFiles->pluck('category.name')->filter()->unique()->implode(', ') ?: 'Not assigned' }}</span></div>
   </div>
   <div>
    <span class="fdl-lbl">Expected <span class="fdl-qual">(earliest)</span></span>
    <span class="fdl-fig">{{ $ledgerExpected?->format('d/m/Y') ?? 'Not set' }}</span>
   </div>
  </div>
  <div class="fdl-zone fdl-zone-last">
   <span class="fdl-lbl">Vendor Due</span>
   <div class="fdl-last-row">
    <span class="fdl-pill fdl-pill-{{ $ledgerVendorKind }}"><i class="fas {{ ['out'=>'fa-arrow-up','in'=>'fa-check','none'=>'fa-minus'][$ledgerVendorKind] }}"></i><span>{{ ['out'=>'Due','in'=>'Clear','none'=>'None'][$ledgerVendorKind] }}</span></span>
    <span class="fdl-last-amt{{ $ledgerVendorKind === 'none' ? '' : ' fdl-amt-'.$ledgerVendorKind }}">{{ $ledgerVendorKind === 'none' ? '—' : $ledgerFig($ledgerVendorDue) }}</span>
   </div>
   <div class="fdl-last-note">{{ $ledgerVendors->implode(', ') ?: 'No vendor on these files' }}</div>
   <div class="fdl-last-when">Client due {{ $ledgerFig((float) $groupFiles->sum('client_due_amount')) }}</div>
  </div>
  <div class="fdl-actions">
   <a href="{{ route('role.contract-files.accounts',['role'=>$role,'client_id'=>$groupClient?->id,'unassigned'=>$groupClient ? null : 1]) }}" class="fdl-btn fdl-btn-in"><i class="fas fa-file-invoice"></i> Accounts</a>
   <a href="{{ $ledgerBoardUrl }}" class="fdl-btn fdl-btn-alt"><i class="fas fa-table-columns"></i> Board</a>
   @can('create contract file')<a href="#" class="fdl-btn fdl-btn-alt create-btn" onclick="return false"><i class="fas fa-plus"></i> Add File</a>@endcan
   <a href="#" class="fdl-btn fdl-btn-alt" onclick="window.print();return false"><i class="fas fa-print"></i> Print</a>
  </div>
 </div>
 <div class="fdl-meta">
  <div><span class="fdl-lbl"><i class="fas fa-users"></i>Files / Applicants</span><span class="fdl-fig">{{ $groupFiles->count() }}</span></div>
  <div class="is-in"><span class="fdl-lbl"><i class="fas fa-circle-check"></i>Approved</span><span class="fdl-fig">{{ $groupFiles->where('status','approved')->count() }}</span></div>
  <div class="is-in"><span class="fdl-lbl"><i class="fas fa-box-archive"></i>Delivered</span><span class="fdl-fig">{{ $groupFiles->whereIn('status',['delivered','case_closed'])->count() }}</span></div>
  <div><span class="fdl-lbl"><i class="fas fa-flag"></i>Flagged</span><span class="fdl-fig">{{ $groupFiles->whereNotNull('exception_flag')->count() }}</span></div>
  <div class="is-in"><span class="fdl-lbl"><i class="fas fa-arrow-down"></i>Client Charge <span class="fdl-qual">(in)</span></span><span class="fdl-fig">{{ $ledgerFig($ledgerCharge) }}</span></div>
  <div class="is-out"><span class="fdl-lbl"><i class="fas fa-arrow-up"></i>File Cost <span class="fdl-qual">(out)</span></span><span class="fdl-fig">{{ $ledgerFig($ledgerCost) }}</span></div>
  <div class="is-close{{ $ledgerProfit < 0 ? ' is-loss' : '' }}"><span class="fdl-lbl"><i class="fas fa-flag-checkered"></i>Profit</span><span class="fdl-fig">{{ $ledgerFig($ledgerProfit) }}</span></div>
 </div>
</div>
