{{--
    Salary Due and Advance Outstanding are the same shape — a person, where they
    sit, and one number — so they share a table rather than two copies that drift.

    $rows        — [['user','company','dept','amount','left'], …]
    $showCompany — whether the Company column is earning its place
    $amountLabel — what the number is
    $amountTone  — Tailwind colour for it
--}}
<table class="table table-hover min-w-full divide-y divide-gray-200">
    <thead>
        <tr style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                <i class="fas fa-user mr-1 text-blue-400"></i>Employee
            </th>
            @if($showCompany)
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                    <i class="fas fa-building mr-1 text-cyan-500"></i>Company
                </th>
            @endif
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                <i class="fas fa-sitemap mr-1 text-indigo-400"></i>Dept
            </th>
            <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">
                <i class="fas fa-money-bill-wave mr-1 text-green-400"></i>{{ $amountLabel }}
            </th>
        </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-100">
        @foreach($rows as $row)
            <tr class="hover:bg-blue-50 transition-colors duration-150">
                <td class="px-4 py-3 whitespace-nowrap">
                    @include('payroll.partials.person-cell', ['user' => $row['user']])
                </td>
                @if($showCompany)
                    <td class="px-4 py-3 whitespace-nowrap">
                        @include('payroll.partials.company-chip', ['company' => $row['company']])
                    </td>
                @endif
                <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                        {{ $row['dept'] }}
                    </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right">
                    <span class="text-sm font-bold {{ $amountTone }}">৳ {{ $taka($row['amount']) }}</span>
                </td>
            </tr>
        @endforeach
    </tbody>
    <tfoot class="border-t-2 border-gray-200">
        <tr class="bg-gray-100 text-sm border-t border-gray-300">
            <td colspan="{{ 2 + ($showCompany ? 1 : 0) }}" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $rows->count() }} {{ Str::plural('Person', $rows->count()) }}
            </td>
            <td class="px-4 py-3.5 text-right font-extrabold {{ $amountTone }}">৳ {{ $taka($rows->sum('amount')) }}</td>
        </tr>
    </tfoot>
</table>
