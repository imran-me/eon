@php $monthName = fn ($m) => \Carbon\Carbon::createFromDate(2000, max(1, (int) $m), 1)->format('M'); @endphp

<table class="w-full">
    <thead class="bg-gray-50 border-b border-gray-200">
        <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Month</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Gross</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Bonus</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Deductions</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Net</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Paid</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Due</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Cumulative Due</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @foreach($rows as $r)
        @php
            $isPaid    = $r->status === 'Paid';
            $isPartial = !$isPaid && (float) $r->paid_amount > 0;
        @endphp
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-sm font-semibold text-gray-800">
                {{ $monthName($r->month) }} {{ $r->year }}
                @if($r->payment_method)
                    <span class="block text-xs font-normal text-gray-400">{{ $r->payment_method }}</span>
                @endif
            </td>
            <td class="px-4 py-3 text-right text-sm text-gray-700">৳ {{ number_format($r->gross_salary, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm text-gray-500">{{ $r->bonus_amount > 0 ? '৳ ' . number_format($r->bonus_amount, 0) : '—' }}</td>
            <td class="px-4 py-3 text-right text-sm text-gray-500">৳ {{ number_format($r->total_deductions, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-800">৳ {{ number_format($r->net_salary, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-green-600">৳ {{ number_format($r->paid_amount, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold {{ $r->due_amount > 0 ? 'text-red-600' : 'text-gray-400' }}">
                ৳ {{ number_format($r->due_amount, 0) }}
            </td>
            <td class="px-4 py-3 text-right text-sm font-bold {{ $r->running_due > 0 ? 'text-red-700' : 'text-gray-400' }}">
                ৳ {{ number_format($r->running_due, 0) }}
            </td>
            <td class="px-4 py-3 text-center">
                @if($isPaid)
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full" style="background:#dcfce7;color:#16a34a;">Paid</span>
                @elseif($isPartial)
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full" style="background:#dbeafe;color:#1d4ed8;">Partial</span>
                @else
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full" style="background:#fee2e2;color:#dc2626;">Due</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
        <tr>
            <td class="px-4 py-3 text-sm font-bold text-gray-700">Total — {{ $rows->count() }} month(s)</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-800">৳ {{ number_format($rows->sum('gross_salary'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-600">৳ {{ number_format($rows->sum('bonus_amount'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-600">৳ {{ number_format($rows->sum('total_deductions'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-900">৳ {{ number_format($rows->sum('net_salary'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-green-600">৳ {{ number_format($rows->sum('paid_amount'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-red-600">৳ {{ number_format($rows->sum('due_amount'), 0) }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>
