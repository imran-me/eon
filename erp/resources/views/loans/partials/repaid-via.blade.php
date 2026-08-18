{{--
    How the money came back — the one thing a "paid so far" total can never show.

    $salary — repaid out of payslips
    $cash   — handed in as cash or into a bank
--}}
@php
    $salary = (float) ($salary ?? 0);
    $cash = (float) ($cash ?? 0);
@endphp

@if($salary <= 0 && $cash <= 0)
    <span class="text-sm text-gray-300">—</span>
@else
    <div class="flex flex-col gap-1">
        @if($salary > 0)
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    Salary
                </span>
                <span class="text-xs font-medium text-gray-600">৳ {{ number_format($salary) }}</span>
            </span>
        @endif
        @if($cash > 0)
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                    Cash / bank
                </span>
                <span class="text-xs font-medium text-gray-600">৳ {{ number_format($cash) }}</span>
            </span>
        @endif
    </div>
@endif
