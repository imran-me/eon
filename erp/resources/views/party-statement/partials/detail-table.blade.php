{{-- The full record behind a ledger row, opened by the arrow in its Details
     cell. Drawn as the statement's "Passengers" panel (toggleTicketDetail in
     partials/scripts) so the two expanders look the same. JS twin:
     psDetailTable() in partials/scripts. $detail = ['columns' => [...], 'rows' => [[...]]] --}}
<div class="ps-detail-card">
    <table class="w-full">
        <thead><tr>
            @foreach($detail['columns'] as $col)
                <th class="py-1.5 pl-3 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase whitespace-nowrap">{{ $col }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @foreach($detail['rows'] as $row)
                <tr>
                    @foreach($row as $i => $cell)
                        <td class="py-1.5 pl-3 pr-3 text-xs {{ $i === 0 ? 'font-semibold text-gray-800' : 'text-gray-600' }}">{{ $cell !== '' && $cell !== null ? $cell : '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
