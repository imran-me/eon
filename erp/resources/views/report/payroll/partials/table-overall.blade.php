<table class="w-full">
    <thead class="bg-gray-50 border-b border-gray-200">
        <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee ID</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Months</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Gross</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Deductions</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Net</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Paid</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Due</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Settled</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @foreach($rows as $i => $r)
        @php
            $net  = (float) $r->total_net;
            $paid = (float) $r->total_paid;
            $pct  = $net > 0 ? (int) round($paid / $net * 100) : 0;
        @endphp
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-sm text-gray-400">{{ $i + 1 }}</td>
            <td class="px-4 py-3 text-xs font-mono font-semibold {{ $r->employee_id_no ? 'text-gray-600' : 'text-gray-300' }}">
                {{ $r->employee_id_no ?: '—' }}
            </td>
            <td class="px-4 py-3">
                <a href="{{ route('role.report.payroll', ['role' => $role, 'type' => 'individual', 'employee_id' => $r->user_id, 'from' => $from, 'to' => $to]) }}"
                   class="text-sm font-semibold text-gray-800 hover:text-blue-600 hover:underline">
                    {{ $r->employee_name }}
                </a>
            </td>
            <td class="px-4 py-3 text-center text-sm text-gray-600">{{ $r->months_count }}</td>
            <td class="px-4 py-3 text-right text-sm text-gray-700">৳ {{ number_format($r->total_gross, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm text-gray-500">৳ {{ number_format($r->total_deductions, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-800">৳ {{ number_format($net, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-green-600">৳ {{ number_format($paid, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold {{ $r->total_due > 0 ? 'text-red-600' : 'text-gray-400' }}">
                ৳ {{ number_format($r->total_due, 0) }}
            </td>
            <td class="px-4 py-3">
                <div class="flex items-center gap-2 justify-center">
                    <div style="width:52px;height:5px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
                        <div style="height:100%;width:{{ $pct }}%;background:{{ $pct === 100 ? '#16a34a' : '#2563eb' }};"></div>
                    </div>
                    <span class="text-xs font-bold {{ $pct === 100 ? 'text-green-600' : 'text-gray-500' }}">{{ $pct }}%</span>
                </div>
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
        <tr>
            <td colspan="4" class="px-4 py-3 text-sm font-bold text-gray-700">Total — {{ $rows->count() }} employee(s)</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-800">৳ {{ number_format($rows->sum('total_gross'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-600">৳ {{ number_format($rows->sum('total_deductions'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-900">৳ {{ number_format($rows->sum('total_net'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-green-600">৳ {{ number_format($rows->sum('total_paid'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-red-600">৳ {{ number_format($rows->sum('total_due'), 0) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
