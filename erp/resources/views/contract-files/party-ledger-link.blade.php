@if($party)
 <span>{{ $party->name }}</span><small><a class="cfw-party-link" href="{{ route('role.party-statement.index', ['role'=>$role, 'party_id'=>$party->id]) }}" title="Open {{ $party->name }} ledger"><i class="fas fa-book-open" aria-hidden="true"></i> {{ $partyIdPrefix ?? 'EP-VN-' }}{{ str_pad($party->id, 3, '0', STR_PAD_LEFT) }}</a></small>
@else
 {{ $partyFallback ?? '—' }}
@endif
