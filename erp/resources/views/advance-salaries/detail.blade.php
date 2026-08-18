{{--
    ONE ADVANCE — the whole life of it: what was approved, whether it has gone
    out, what has come back and out of which payslip. Rendered as the body of the
    detail modal, reached from any row on the Advance tab.

    "Due after this" on each recovery is a running balance, so the table reads
    down to exactly the "Still due" figure in the header. It is deliberately NOT
    footed: a column of balances at different moments has no meaningful total, so
    the foot carries the closing balance instead.
--}}
@php
    $taka = fn ($amount) => number_format(round((float) $amount));
    $recoveries = $advance->recoveries;

    // Walked forward from nothing: unlike a loan, an advance has no pre-history
    // to carry — it starts the day it is released.
    $running = 0.0;

    $stateChip = [
        'awaiting'    => ['bg-amber-100 text-amber-700 border-amber-200', 'fa-hourglass-half', 'Awaiting release'],
        'outstanding' => ['bg-red-100 text-red-700 border-red-200', 'fa-arrow-trend-up', 'Outstanding'],
        'recovered'   => ['bg-green-100 text-green-700 border-green-200', 'fa-check-circle', 'Recovered'],
    ];
    [$chipClass, $chipIcon, $chipLabel] = $stateChip[$advance->state];
@endphp

<div class="space-y-5">

    {{-- ── Who, and where it stands ── --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 w-11 h-11 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-bold uppercase">
                {{ strtoupper(substr($advance->user?->name ?? '?', 0, 2)) }}
            </div>
            <div>
                <div class="text-base font-bold text-gray-900">
                    {{ $advance->user?->name ?? 'Employee #' . $advance->user_id }}
                    @if($advance->user?->employee_id_no)
                        <span class="text-xs font-mono font-normal text-gray-400">· {{ $advance->user->employee_id_no }}</span>
                    @endif
                </div>
                <div class="text-sm text-gray-500 mt-0.5">
                    ৳ {{ $taka($advance->amount) }} for {{ $book->monthLabel($advance->month) }}
                    @if($advance->user?->company) · {{ $advance->user->company->short_name ?: $advance->user->company->name }} @endif
                </div>
            </div>
        </div>
        <div class="text-right">
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold border {{ $chipClass }}">
                <i class="fas {{ $chipIcon }} text-xs"></i> {{ $chipLabel }}
            </span>
            <p class="text-[11px] {{ $advance->outstanding > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }} mt-1">
                @if($advance->outstanding > 0)
                    Due ৳ {{ $taka($advance->outstanding) }}
                @elseif(! $advance->is_released)
                    Not yet paid out
                @else
                    Fully recovered
                @endif
            </p>
        </div>
    </div>

    {{-- ── The four figures ── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 font-medium">Advance amount</p>
            <p class="text-lg font-extrabold text-gray-900 mt-0.5">৳ {{ $taka($advance->amount) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 font-medium">Recovered</p>
            <p class="text-lg font-extrabold text-emerald-600 mt-0.5">৳ {{ $taka($advance->recovered_amount) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 font-medium">Still due</p>
            <p class="text-lg font-extrabold {{ $advance->outstanding > 0 ? 'text-red-600' : 'text-green-600' }} mt-0.5">
                ৳ {{ $taka($advance->outstanding) }}
            </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 font-medium">Recovered so far</p>
            <p class="text-lg font-extrabold text-gray-900 mt-0.5">{{ $advance->progress_pct }}%</p>
        </div>
    </div>

    {{-- ── The terms ── --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <td class="px-4 py-2.5 text-gray-500 w-1/2">Approval status</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-800">{{ $advance->status ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 text-gray-500">Released</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-800">
                        @if($advance->is_released)
                            {{ $advance->paid_at ? \Carbon\Carbon::parse($advance->paid_at)->format('d M Y') : 'yes' }}
                        @else
                            Not yet — approved and queued to go out
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 text-gray-500">Against salary month</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-800">{{ $book->monthLabel($advance->month) }}</td>
                </tr>
                @if($advance->schedule_date)
                    <tr>
                        <td class="px-4 py-2.5 text-gray-500">Scheduled for</td>
                        <td class="px-4 py-2.5 text-right font-semibold text-gray-800">
                            {{ \Carbon\Carbon::parse($advance->schedule_date)->format('d M Y') }}
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="px-4 py-2.5 text-gray-500">Reason</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-800">{{ $advance->reason ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 text-gray-500">Last recovered</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-800">
                        {{ $advance->last_recovered_on ? \Carbon\Carbon::parse($advance->last_recovered_on)->format('d M Y') : 'none yet' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ── Every recovery against this advance ──
         An advance has no movement table of its own: it comes back off a
         payslip, so these rows ARE the payslips that withheld it. --}}
    <div>
        <div class="flex items-center justify-between gap-3 mb-2">
            <h4 class="text-sm font-bold text-gray-800">
                <i class="fas fa-clock-rotate-left mr-1.5 text-blue-500"></i>Recovered from these salaries
            </h4>
            <span class="text-xs text-gray-400">
                {{ $recoveries->count() }} {{ Str::plural('recovery', $recoveries->count()) }} · oldest first
            </span>
        </div>

        <div class="rounded-xl border border-gray-200 overflow-hidden overflow-x-auto">
            <table class="table table-hover min-w-full divide-y divide-gray-200">
                <thead>
                    <tr style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
                        <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Salary Month</th>
                        <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Paid On</th>
                        <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Net Salary</th>
                        <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Recovered</th>
                        <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Due After This</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($recoveries as $salary)
                        @php
                            $running = round($running + (float) $salary->advance_salary_deduction, 2);
                            $dueAfter = max(0, round((float) $advance->amount - $running, 2));
                        @endphp
                        <tr class="hover:bg-blue-50 transition-colors duration-150">
                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-700">
                                {{ $book->monthLabel(sprintf('%04d-%02d', (int) $salary->year, (int) $salary->month)) }}
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-500">
                                {{ $salary->salary_generation_date ? \Carbon\Carbon::parse($salary->salary_generation_date)->format('d M Y') : '—' }}
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-right text-sm text-gray-700">৳ {{ $taka($salary->net_salary) }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-right text-sm font-semibold text-emerald-600">
                                ৳ {{ $taka($salary->advance_salary_deduction) }}
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-right text-sm font-semibold {{ $dueAfter > 0 ? 'text-red-600' : 'text-green-600' }}">
                                ৳ {{ $taka($dueAfter) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-10">
                                <div class="flex flex-col items-center gap-2">
                                    <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center">
                                        <i class="fas fa-hourglass-half fa-lg text-gray-300"></i>
                                    </div>
                                    <h4 class="text-gray-500 text-sm font-semibold mt-1">Nothing recovered yet</h4>
                                    <p class="text-gray-400 text-xs">
                                        @if($advance->is_released)
                                            The next payroll run will take it off the salary.
                                        @else
                                            It has not been released yet — nothing is owed back until it is.
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($recoveries->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200">
                        <tr class="bg-gray-100 text-sm border-t border-gray-300">
                            <td colspan="3" class="px-4 py-3 text-left font-extrabold text-gray-800">
                                {{ $recoveries->count() }} {{ Str::plural('Recovery', $recoveries->count()) }}
                            </td>
                            <td class="px-4 py-3 text-right font-extrabold text-emerald-700">
                                ৳ {{ $taka($recoveries->sum(fn ($s) => (float) $s->advance_salary_deduction)) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-extrabold text-gray-800">৳ {{ $taka($advance->outstanding) }}</span>
                                <p class="text-[11px] text-gray-400 font-normal leading-tight mt-0.5">closing balance</p>
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ── Actions ── --}}
    <div class="flex justify-end gap-2 pt-1">
        @can('view advance salary')
            <a href="{{ route('role.advance-salaries.payment-slip', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $advance->id]) }}"
               target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                <i class="fas fa-receipt"></i> Payment Slip
            </a>
        @endcan
    </div>
</div>
