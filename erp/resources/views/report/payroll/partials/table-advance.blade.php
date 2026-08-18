@php
    $badge = [
        'Approved' => 'background:#dcfce7;color:#16a34a;',
        'Pending'  => 'background:#fef9c3;color:#ca8a04;',
        'Rejected' => 'background:#fee2e2;color:#dc2626;',
    ];
@endphp

<table class="w-full">
    <thead class="bg-gray-50 border-b border-gray-200">
        <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee ID</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">For Month</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Schedule Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Reason</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @foreach($rows as $i => $r)
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-sm text-gray-400">{{ $i + 1 }}</td>
            <td class="px-4 py-3 text-xs font-mono font-semibold {{ $r->employee_id_no ? 'text-gray-600' : 'text-gray-300' }}">
                {{ $r->employee_id_no ?: '—' }}
            </td>
            <td class="px-4 py-3 text-sm font-semibold text-gray-800">{{ $r->employee_name }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ $r->month ?: '—' }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-800">৳ {{ number_format($r->amount, 0) }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">
                {{ $r->schedule_date ? \Carbon\Carbon::parse($r->schedule_date)->format('d M Y') : '—' }}
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ Str::limit($r->reason, 45) ?: '—' }}</td>
            <td class="px-4 py-3 text-center">
                <span class="text-xs font-bold px-2.5 py-1 rounded-full" style="{{ $badge[$r->status] ?? $badge['Pending'] }}">
                    {{ $r->status }}
                </span>
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
        <tr>
            <td colspan="4" class="px-4 py-3 text-sm font-bold text-gray-700">Total — {{ $rows->count() }} request(s)</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-900">৳ {{ number_format($rows->sum('amount'), 0) }}</td>
            <td colspan="3" class="px-4 py-3 text-xs text-gray-500">
                Approved ৳{{ number_format($rows->where('status', 'Approved')->sum('amount'), 0) }}
                · Pending ৳{{ number_format($rows->where('status', 'Pending')->sum('amount'), 0) }}
                · Rejected ৳{{ number_format($rows->where('status', 'Rejected')->sum('amount'), 0) }}
            </td>
        </tr>
    </tfoot>
</table>
