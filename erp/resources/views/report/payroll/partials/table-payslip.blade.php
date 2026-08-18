@php $monthName = fn ($m) => \Carbon\Carbon::createFromDate(2000, max(1, (int) $m), 1)->format('M'); @endphp

<table class="w-full">
    <thead class="bg-gray-50 border-b border-gray-200">
        <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Payslip #</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee ID</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Period</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Issue Date</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Gross</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Deductions</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Net</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Paid</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Due</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">PDF</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @foreach($rows as $r)
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-sm font-mono font-bold text-blue-600">{{ $r->payslip_number }}</td>
            <td class="px-4 py-3 text-xs font-mono font-semibold {{ $r->employee_id_no ? 'text-gray-600' : 'text-gray-300' }}">
                {{ $r->employee_id_no ?: '—' }}
            </td>
            <td class="px-4 py-3 text-sm font-semibold text-gray-800">{{ $r->employee_name }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ $monthName($r->month) }} {{ $r->year }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">
                {{ $r->issue_date ? \Carbon\Carbon::parse($r->issue_date)->format('d M Y') : '—' }}
            </td>
            <td class="px-4 py-3 text-right text-sm text-gray-700">৳ {{ number_format($r->gross_salary, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm text-gray-500">৳ {{ number_format($r->total_deductions, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-800">৳ {{ number_format($r->net_salary, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-green-600">৳ {{ number_format($r->paid_amount, 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold {{ $r->due_amount > 0 ? 'text-red-600' : 'text-gray-400' }}">
                ৳ {{ number_format($r->due_amount, 0) }}
            </td>
            <td class="px-4 py-3 text-center">
                @if($r->pdf_path)
                    <a href="{{ asset($r->pdf_path) }}" target="_blank" class="text-red-500 hover:text-red-700" title="Open PDF">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                @else
                    <span class="text-gray-300">—</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
        <tr>
            <td colspan="5" class="px-4 py-3 text-sm font-bold text-gray-700">Total — {{ $rows->count() }} payslip(s)</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-800">৳ {{ number_format($rows->sum('gross_salary'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-600">৳ {{ number_format($rows->sum('total_deductions'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-gray-900">৳ {{ number_format($rows->sum('net_salary'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-green-600">৳ {{ number_format($rows->sum('paid_amount'), 0) }}</td>
            <td class="px-4 py-3 text-right text-sm font-extrabold text-red-600">৳ {{ number_format($rows->sum('due_amount'), 0) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
