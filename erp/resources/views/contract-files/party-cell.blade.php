{{-- Agent / Vendor cell in the Flights & Passengers dress: the agent as a
     regular-weight name with the tie glyph, his code on the grey line under
     it, then each vendor as a seller line (name shortens, code never). --}}
@php
 $fileProviders = collect([$file->vendor])->merge($file->serviceCosts->pluck('vendor'))->filter()->unique('id')->values();
 $fileStatementUrl = route('role.party-statement.index', ['role'=>$role]);
@endphp
@if($file->client)
<a class="mfp-party mfp-name" href="{{ $fileStatementUrl }}?party_id={{ $file->client->id }}" title="Open {{ $file->client->name }}'s statement"><i class="fas fa-user-tie mfp-agent-ico" aria-hidden="true"></i>{{ $file->client->name }}</a>
@else
<strong class="mfp-party mfp-name"><i class="fas fa-user-tie mfp-agent-ico" aria-hidden="true"></i>Direct</strong>
@endif
<div class="mfp-ln" title="{{ $file->client ? 'Client ID · ' : '' }}File{{ $file->category ? ' · Category' : '' }}">@if($file->client)<a class="mfp-mono" href="{{ $fileStatementUrl }}?party_id={{ $file->client->id }}">{{ $file->client_type==='direct'?'ID-':'EP-AG-' }}{{ str_pad($file->client->id,3,'0',STR_PAD_LEFT) }}</a><span class="mfp-tk-sep">·</span>@endif<a href="{{ route('role.contract-files.show', ['role'=>$role, 'contractFile'=>$file]) }}" data-file-sheet="{{ $file->id }}" title="Open file details">{{ $file->file_number }}</a>@if($file->category)<span class="mfp-tk-sep">·</span>{{ $file->category->name }}@endif</div>
@foreach($fileProviders->take(2) as $provider)
@include('contract-files.provider-line', ['provider'=>$provider])
@endforeach
@if($fileProviders->count()>2)<div class="mfp-ln" title="{{ $fileProviders->slice(2)->pluck('name')->implode(', ') }}">… +{{ $fileProviders->count()-2 }} more</div>@endif
