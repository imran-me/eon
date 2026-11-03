{{-- One FILE (one applicant's passport) as a row of the Flight Dashboard's
     table (owner, 2026-10-25: "all will be file / passport wise, not agent
     wise"): the date first, the applicant as the link with its stage badge,
     grey detail lines, the money right-aligned in three columns and the eye
     under the last figure. A click opens the file record. --}}
@php
 $rowFlag = $file->country?->code ? asset('flags/'.strtolower($file->country->code).'.png') : null;
 $rowStage = $file->exception_flag ? 'flagged' : (in_array($file->status, ['delivered', 'case_closed'], true) ? 'closed' : (in_array($file->status, ['approved', 'passport_ready'], true) ? 'boarding' : ''));
 $rowFlagLabel = $file->exception_flag ? (\App\Models\ContractFile::EXCEPTION_FLAGS[$file->exception_flag] ?? $file->exception_flag) : null;
 $rowPending = max(0, $file->documents_total - $file->documents_received);
 $rowShow = route('role.contract-files.show', ['role' => $role, 'contractFile' => $file]);
 $rowKind = collect([$file->category?->name, $file->relationLoaded('visaType') ? $file->visaType?->name : null])->filter()->implode(' · ');
@endphp
<tr tabindex="0" data-file-open aria-label="Open the record for {{ $file->file_number }} · {{ $file->applicant_name }}" @isset($closedIndex) data-cfd-closed-file="{{ $closedIndex }}" @endisset>
 <td class="fd-nowrap" data-sort="{{ $file->submit_date?->toDateString() ?? '' }}">{{ $file->submit_date?->format('d/m/Y') ?? 'Not set' }}<small>{{ $file->submit_date ? $file->submit_date->diffForHumans() : 'Date not set' }}</small></td>
 <td><a href="{{ $rowShow }}" data-file-sheet="{{ $file->id }}">{{ $file->applicant_name ?: 'Applicant pending' }}</a><span class="fd-table-status {{ $rowStage }}">{{ $rowFlagLabel ?? $file->status_label }}</span>
  <small class="fd-detail fd-clip" title="{{ $file->file_number }} · passport {{ $file->passport_no ?? '—' }}">{{ $file->file_number }} · {{ $file->passport_no ?? 'No passport' }}</small>
  @if($file->phone)<small class="fd-clip">{{ $file->phone }}</small>@endif</td>
 <td><div class="fd-nowrap fd-clip">@if($rowFlag)<img class="fdc-flag" src="{{ $rowFlag }}" alt="" loading="lazy" onerror="this.remove()"> @endif{{ $file->country?->name ?? 'Country pending' }}</div>
  <small class="fd-clip" title="{{ $rowKind }}">{{ $rowKind ?: 'Category not set' }}</small>
  <small class="fd-clip fd-ledger-date"><i class="fas fa-calendar-day" aria-hidden="true"></i> {{ $file->expected_out_date ? 'Expected '.$file->expected_out_date->format('d M Y') : 'Expected date not set' }}</small></td>
 <td>@if($file->client)<div class="fd-agent-line fd-clip" title="{{ $file->client->name }}"><i class="fas fa-user-tie" aria-hidden="true"></i> {{ $file->client->name }} <a href="{{ route('role.party-statement.index', ['role' => $role]) }}?party_id={{ $file->client->id }}" title="Open {{ $file->client->name }}'s statement">EP-AG-{{ str_pad($file->client->id, 3, '0', STR_PAD_LEFT) }}</a></div>@else<span class="fd-agent-name fd-clip"><i class="fas fa-user-tie" aria-hidden="true"></i><span>Direct</span></span>@endif
  @if($file->vendor)
  <small class="fd-clip" title="{{ $file->vendor->name }}">Vendor: {{ $file->vendor->name }}</small>
  <small class="fd-detail">Vendor ID: <a href="{{ route('role.party-statement.index', ['role' => $role]) }}?party_id={{ $file->vendor->id }}" title="Open {{ $file->vendor->name }}'s statement">EP-VN-{{ str_pad($file->vendor->id, 3, '0', STR_PAD_LEFT) }}</a></small>
  @else<small class="fd-clip">Vendor: Not assigned</small>@endif</td>
 <td class="fd-passengers" data-sort="{{ $file->documents_received }}">{{ $file->documents_received }} / {{ $file->documents_total }}<small>documents · {{ $rowPending }} pending</small>@if($file->exception_flag)<small class="fd-due">{{ $rowFlagLabel }}</small>@endif</td>
 <td class="fd-num fd-detail" data-sort="{{ $file->net_client_charge }}">৳{{ number_format($file->net_client_charge) }}<small>{{ $file->client_due > 0 ? 'Due ৳'.number_format($file->client_due) : 'Paid' }}</small></td>
 <td class="fd-num" data-sort="{{ $file->total_file_cost }}">৳{{ number_format($file->total_file_cost) }}<small>Paid ৳{{ number_format($file->cost_paid_amount) }}</small></td>
 <td class="fd-num {{ $file->total_vendor_due > 0 ? 'fd-due' : 'fd-clear' }}" data-sort="{{ $file->total_vendor_due }}">৳{{ number_format($file->total_vendor_due) }}
  @if($file->payable_date)<small>Due: {{ \Illuminate\Support\Carbon::parse($file->payable_date)->format('d/m/Y') }}</small>@endif
  <a class="fdl-eye" href="{{ $rowShow }}" data-file-sheet="{{ $file->id }}" title="Open the full record for {{ $file->file_number }}" aria-label="Open the full record for {{ $file->file_number }}"><i class="fas fa-eye" aria-hidden="true"></i></a></td>
</tr>
