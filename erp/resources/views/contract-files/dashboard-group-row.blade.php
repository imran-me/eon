{{-- One agent / client group, drawn like a row of the Flight Dashboard's
     table: a link on the first line, grey detail lines under it, the money
     right-aligned in its own three columns and the eye under the last figure. --}}
@php
 $rowUrl = $group['client_id'] ? route('role.contract-files.agent-files',['role'=>$role,'client'=>$group['client_id']]) : route('role.contract-files.index',['role'=>$role,'unassigned'=>1]);
 $rowFiles = $group['files']->take(3)->pluck('file_number')->implode(' · ').($group['total']>3 ? ' · +'.($group['total']-3).' more' : '');
 $rowCountries = $group['files']->pluck('country.name')->filter()->unique()->implode(' · ') ?: '—';
 $rowCategories = $group['files']->pluck('category.name')->filter()->unique()->implode(' · ') ?: '—';
 $rowVendors = $group['files']->pluck('vendor.name')->filter()->unique()->implode(' · ');
 $rowExpected = $group['files']->pluck('expected_out_date')->filter()->sort()->first();
@endphp
<tr tabindex="0" data-group-row aria-label="Open files for {{ $group['name'] }}" @isset($closedIndex) data-cfd-closed-group="{{ $closedIndex }}" @endisset>
 <td><a href="{{ $rowUrl }}" title="{{ $group['name'] }}">{{ $group['name'] }}</a>
  <small class="fd-clip">{{ $group['files']->first()->client?->phone ?? 'No client assigned' }}</small>
  <small>{{ $group['total'] }} files · {{ $group['in_process'] }} in progress · {{ $group['approved'] }} approved</small></td>
 <td>{{ $group['total'] }} {{ $group['total'] === 1 ? 'file' : 'files' }}
  <small class="fd-detail fd-clip" title="{{ $group['files']->pluck('file_number')->implode(' · ') }}">{{ $rowFiles }}</small>
  <small>{{ $group['files']->pluck('passport_holder_id')->filter()->unique()->count() }} applicants</small></td>
 <td><div class="fd-clip" title="{{ $rowCountries }}">{{ $rowCountries }}</div>
  <small class="fd-clip" title="{{ $rowCategories }}">{{ $rowCategories }}</small>
  <small>Documents: {{ $group['files']->sum('documents_received') }}/{{ $group['files']->sum('documents_total') }}</small></td>
 <td class="fd-nowrap" data-sort="{{ $rowExpected?->toDateString() ?? '' }}">{{ $rowExpected?->format('d/m/Y') ?? 'Not set' }}
  <small>{{ $group['in_process'] }} in progress · {{ $group['approved'] }} approved</small>
  <small>{{ $group['files']->where('status','delivered')->count() }} delivered · {{ $group['files']->whereNotNull('exception_flag')->count() }} flagged</small></td>
 <td><span class="fd-agent-name fd-clip" title="{{ $rowVendors ?: 'Unassigned vendor' }}"><i class="fas fa-store" aria-hidden="true"></i><span>{{ $rowVendors ?: 'Unassigned vendor' }}</span></span>
  <small>Paid ৳{{ number_format($group['files']->sum('cost_paid_amount')) }}</small></td>
 <td class="fd-num fd-detail">৳{{ number_format($group['charge']) }}<small>Due ৳{{ number_format($group['due']) }}</small></td>
 <td class="fd-num">৳{{ number_format($group['cost']) }}</td>
 <td class="fd-num {{ $group['vendor_due']>0 ? 'fd-due' : 'fd-clear' }}">৳{{ number_format($group['vendor_due']) }}
  <a class="fdl-eye" href="{{ $rowUrl }}" title="View files for {{ $group['name'] }}" aria-label="View files for {{ $group['name'] }}"><i class="fas fa-eye" aria-hidden="true"></i></a></td>
</tr>
