<table class="w-full">
    <thead class="bg-gray-50 border-b border-gray-200">
        <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee ID</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Loan Amount</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Monthly Deduction</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Recovered</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Remaining</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Period</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Recovery</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @foreach($rows as $i => $r)
        @php
            $amount = (float) $r->amount;
            $pct    = $amount > 0 ? (int) round((float) $r->recovered_amount / $amount * 100) : 0;
        @endphp
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-sm text-gray-400">{{ $i + 1 }}</td>
            <td class="px-4 py-3 text-xs font-mono font-semibold {{ $r->employee_id_no ? 'text-gray-600' : 'text-gray-300' }}">
                {{ $r->employee_id_no ?: '—' }}
            </td>
            <td class="px-4 py-3">
                <span class="text-sm font-semibold text-gray-800">{{ $r->employee_name }}</span>
                @if($r->bank_name)
                    <span class="block text-xs text-gray-400">{{ $r->bank_name }}</span>
                @endif
            </td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-800">৳ {{ number_format($amount, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm text-gray-600">৳ {{ number_format($r->monthly_deduction, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-green-600">৳ {{ number_format($r->recovered_amount, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold {{ $r->remaining_amount > 0 ? 'text-red-600' : 'text-gray-400' }}">
                ৳ {{ number_format($r->remaining_amount, 0) }}
            </td>
            <td class="px-4 py-3 text-xs text-gray-500">
                {{ $r->start_date ? \Carbon\Carbon::parse($r->start_date)->format('d M y') : '—' }}
                →
                {{ $r->end_date ? \Carbon\Carbon::parse($r->end_date)->format('d M y') : '—' }}
            </td>
            <td class="px-4 py-3">
                <div class="flex items-center gap-2 justify-center">
                    <div style="width:52px;height:5px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
                        <div style="height:100%;width:{{ $pct }}%;background:{{ $pct === 100 ? '#16a34a' : '#2563eb' }};"></div>
                    </div>
                    <span class="text-xs font-bold {{ $pct === 100 ? 'text-green-600' : 'text-gray-500' }}">{{ $pct }}%</span>
                </div>
            </td>
            <td class="px-4 py-3 text-center">
                @if($r->status === 'Completed')
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full" style="background:#dcfce7;color:#16a34a;">Completed</span>
                @else
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full" style="background:#dbeafe;color:#1d4ed8;">Running</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
        <tr>
            <td colspan="3" class="px-4 py-3 text-sm font-bold text-gray-700">Total — {{ $rows->count() }} loan(s)</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-900">৳ {{ number_format($rows->sum('amount'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-600">৳ {{ number_format($rows->sum('monthly_deduction'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-green-600">৳ {{ number_format($rows->sum('recovered_amount'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-red-600">৳ {{ number_format($rows->sum('remaining_amount'), 0) }}</td>
            <td colspan="3"></td>
        </tr>
    </tfoot>
</table>
