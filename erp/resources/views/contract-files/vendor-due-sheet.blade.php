<section class="cfa-panel" id="cfac-vendor" data-cfac-panel="vendor" hidden>
 <div class="cfa-card">
  <div class="cfa-card-h"><h3>Vendor / Trainer Due Sheet</h3><small>Processing and File-only service payables / refund credits, grouped by the actual provider</small></div>
  <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
   <thead><tr><th>Provider</th><th class="num">Items</th><th class="num">Cost</th><th class="num">Paid</th><th class="num">Due</th><th class="num">Approved refund credit</th><th data-nosort>File / Service</th></tr></thead>
   <tbody>
   @forelse($vendorGroups as $items)
    <tr>
     <td class="b">{{ $items->first()['party']?->name ?? 'Unassigned' }}</td>
     <td class="num">{{ $items->count() }}</td>
     <td class="num">{{ $money($items->sum('cost')) }}</td>
     <td class="num cfa-good">{{ $money($items->sum('paid')) }}</td>
     <td class="num {{ $items->sum('due')>0 ? 'cfa-due' : '' }}">{{ $money($items->sum('due')) }}</td>
     <td class="num cfa-good">{{ $money($items->sum('credit')) }}</td>
     <td>@foreach($items as $item)<small><a href="{{ route('role.contract-files.show',['role'=>$role,'contractFile'=>$item['file']]) }}" data-file-sheet="{{ $item['file']->id }}">{{ $item['file']->file_number }}</a> · {{ $item['label'] }} · {{ $money($item['due']) }}</small>@endforeach</td>
    </tr>
   @empty
    <tr><td colspan="7" class="cfa-empty"><i class="fas fa-truck-field"></i>No provider dues.</td></tr>
   @endforelse
   </tbody>
  </table></div>
 </div>
</section>
