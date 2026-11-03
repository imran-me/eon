{{-- File Accounts' file-by-file table for one set of files, with its total,
     in Flight Accounts' .cfa-table. Used for the page's period and inside each
     opened month under Past months. Needs $tableRows, $emptyText, $money, $role. --}}
@php $tRevenue=$tableRows->sum('revenue'); $tCost=$tableRows->sum('cost'); @endphp
<div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
 <thead><tr><th>File / Applicant</th><th>Country / Category</th><th>Agent / Client</th><th>Vendor</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Client due</th><th class="num">Vendor due</th><th>Stage</th></tr></thead>
 <tbody>
 @forelse($tableRows as $row)
  @php $file=$row['file']; @endphp
  <tr>
   <td class="b" data-sort="{{ $file->file_number }}"><a href="{{ route('role.contract-files.show',['role'=>$role,'contractFile'=>$file]) }}" data-file-sheet="{{ $file->id }}">{{ $file->file_number }}</a><small>{{ $file->applicant_name }}</small><small>{{ $file->submit_date?->format('d/m/Y') ?? 'Undated' }}</small></td>
   <td>{{ $file->country?->name ?? '—' }}<small>{{ $file->category?->name ?? '—' }}</small></td>
   <td>{{ $file->client?->name ?? 'Unassigned' }}</td>
   <td>{{ $file->vendor?->name ?? 'Unassigned' }}</td>
   <td class="num cfa-in">{{ $money($row['revenue']) }}</td>
   <td class="num cfa-out">{{ $money($row['cost']) }}</td>
   <td class="num {{ $row['profit']<0 ? 'cfa-bad' : 'cfa-good' }}" data-sort="{{ $row['profit'] }}">{{ $money($row['profit']) }}</td>
   <td class="num">{{ $row['revenue'] ? number_format($row['profit']/$row['revenue']*100,1).'%' : '—' }}</td>
   <td class="num {{ $row['client_due']>0 ? 'cfa-due' : '' }}">{{ $money($row['client_due']) }}</td>
   <td class="num {{ $row['vendor_due']>0 ? 'cfa-due' : '' }}">{{ $money($row['vendor_due']) }}</td>
   <td><span class="cfa-pill">{{ $file->status_label }}</span>@if($file->exception_flag)<small class="cfa-warn-t">{{ \App\Models\ContractFile::EXCEPTION_FLAGS[$file->exception_flag] ?? $file->exception_flag }}</small>@endif</td>
  </tr>
 @empty
  <tr><td colspan="11" class="cfa-empty"><i class="fas fa-folder-open"></i>{{ $emptyText }}</td></tr>
 @endforelse
 </tbody>
 @if($tableRows->count() > 1)
 <tfoot class="cfa-pl-foot"><tr><td colspan="4">Total · {{ $tableRows->count() }} files</td><td class="num">{{ $money($tRevenue) }}</td><td class="num">{{ $money($tCost) }}</td><td class="num">{{ $money($tRevenue-$tCost) }}</td><td class="num">{{ $tRevenue ? number_format(($tRevenue-$tCost)/$tRevenue*100,1).'%' : '—' }}</td><td class="num">{{ $money($tableRows->sum('client_due')) }}</td><td class="num">{{ $money($tableRows->sum('vendor_due')) }}</td><td></td></tr></tfoot>
 @endif
</table></div>
