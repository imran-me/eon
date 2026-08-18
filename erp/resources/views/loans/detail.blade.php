{{--
    ONE LOAN — the whole life of it: what was taken, what has come back, out of
    what, and what is still owed. Rendered as the body of the detail modal, reached
    from any row on the Loans tab.

    "Due after this" on each payment is a running balance, so the table reads down
    to exactly the "Still due" figure in the header. It is deliberately NOT footed:
    a column of balances at different moments has no meaningful total, so the foot
    carries the closing balance instead.
--}}
@php
    $via = $loan->repaidByMethod();
    $openingPaid = (float) $loan->opening_paid_amount;
    $payments = $loan->payment_rows;
    $taka = fn ($amount) => number_format(round((float) $amount));

    // Walked forward from whatever had already been repaid before the movements
    // were recorded, so the last row lands on the loan's real balance.
    $running = $openingPaid;
@endphp

<div class="space-y-5">

    {{-- ── Who, and where it stands ── --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 w-11 h-11 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-bold uppercase">
                {{ strtoupper(substr($loan->user?->name ?? '?', 0, 2)) }}
            </div>
            <div>
                <div class="text-base font-bold text-gray-900">
                    {{ $loan->user?->name ?? 'Employee #' . $loan->user_id }}
                    @if($loan->user?->employee_id_no)
                        <span class="text-xs font-mono font-normal text-gray-400">· {{ $loan->user->employee_id_no }}</span>
                    @endif
                </div>
                <div class="text-sm text-gray-500 mt-0.5">
                    ৳ {{ $taka($loan->amount) }} taken on
                    {{ $loan->start_date ? \Carbon\Carbon::parse($loan->start_date)->format('d M Y') : '—' }}
                    @if($loan->user?->company) · {{ $loan->user->company->short_name ?: $loan->user->company->name }} @endif
                </div>
            </div>
        </div>
        <div class="text-right">
            @if($loan->is_cleared)
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                    <i class="fas fa-check-circle text-xs"></i> Cleared
                </span>
                <p class="text-[11px] text-gray-400 mt-1">
                    {{ $loan->cleared_on ? \Carbon\Carbon::parse($loan->cleared_on)->format('d M Y') : '' }}
                </p>
            @else
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700 border border-yellow-200">
                    <i class="fas fa-hourglass-half text-xs"></i> Running
                </span>
                <p class="text-[11px] text-red-600 font-semibold mt-1">Due ৳ {{ $taka($loan->outstanding) }}</p>
            @endif
        </div>
    </div>

    {{-- ── The five figures ── --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 font-medium">Loan taken</p>
            <p class="text-lg font-extrabold text-gray-900 mt-0.5">৳ {{ $taka($loan->amount) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 font-medium">Paid so far</p>
            <p class="text-lg font-extrabold text-emerald-600 mt-0.5">৳ {{ $taka($loan->paid_amount) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 font-medium">Still due</p>
            <p class="text-lg font-extrabold {{ $loan->outstanding > 0 ? 'text-red-600' : 'text-green-600' }} mt-0.5">
                ৳ {{ $taka($loan->outstanding) }}
            </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 font-medium">Monthly EMI</p>
            <p class="text-lg font-extrabold text-gray-900 mt-0.5">
                {{ $loan->monthly_deduction > 0 ? '৳ ' . $taka($loan->monthly_deduction) : '—' }}
            </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 font-medium">Instalments left</p>
            <p class="text-lg font-extrabold text-gray-900 mt-0.5">
                {{ $loan->instalments_left ?: ($loan->outstanding > 0 ? 'no plan' : '—') }}
            </p>
        </div>
    </div>

    {{-- ── The terms, and how the money has been coming back ── --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <td class="px-4 py-2.5 text-gray-500 w-1/2">Disbursed from</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-800">{{ $loan->bank?->name ?: 'Cash / not specified' }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 text-gray-500">Repayment plan</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-800">
                        @if($loan->emi_months)
                            {{ $loan->emi_months }} months · ৳ {{ $taka($loan->monthly_deduction) }} from every salary
                        @elseif($loan->monthly_deduction > 0)
                            ৳ {{ $taka($loan->monthly_deduction) }} from every salary
                        @else
                            None — repaid only when a payment is recorded by hand
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 text-gray-500">Recovered from salary</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-800">৳ {{ $taka($via['salary']) }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 text-gray-500">Repaid in cash / bank</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-800">৳ {{ $taka($via['cash']) }}</td>
                </tr>
                @if($openingPaid > 0)
                    <tr>
                        <td class="px-4 py-2.5 text-gray-500">Repaid before individual payments were recorded</td>
                        <td class="px-4 py-2.5 text-right font-semibold text-gray-800">৳ {{ $taka($openingPaid) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="px-4 py-2.5 text-gray-500">Repaid so far</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-800">{{ $loan->progress_pct }}% of the loan</td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 text-gray-500">Last payment</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-800">
                        {{ $loan->last_paid_on ? \Carbon\Carbon::parse($loan->last_paid_on)->format('d M Y') : 'none yet' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ── Every payment against this loan ── --}}
    <div>
        <div class="flex items-center justify-between gap-3 mb-2">
            <h4 class="text-sm font-bold text-gray-800">
                <i class="fas fa-clock-rotate-left mr-1.5 text-blue-500"></i>Every payment against this loan
            </h4>
            <span class="text-xs text-gray-400">
                {{ $payments->count() }} {{ Str::plural('payment', $payments->count()) }} · oldest first
            </span>
        </div>

        <div class="rounded-xl border border-gray-200 overflow-hidden overflow-x-auto">
            <table class="table table-hover min-w-full divide-y divide-gray-200">
                <thead>
                    <tr style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
                        <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Paid On</th>
                        <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">How</th>
                        <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Note</th>
                        <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Paid</th>
                        <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Due After This</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($payments as $payment)
                        @php
                            $running = round($running + (float) $payment->amount, 2);
                            $dueAfter = max(0, round((float) $loan->amount - $running, 2));
                        @endphp
                        <tr class="hover:bg-blue-50 transition-colors duration-150">
                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-700">
                                {{ \Carbon\Carbon::parse($payment->date)->format('d M Y') }}
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                @if($payment->method === 'salary')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        Salary deduction
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                                        {{ $payment->bank?->name ?: 'Cash / bank' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-sm text-gray-600">{{ $payment->note ?: '—' }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-right text-sm font-semibold text-emerald-600">
                                ৳ {{ $taka($payment->amount) }}
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
                                    <h4 class="text-gray-500 text-sm font-semibold mt-1">Nothing repaid yet</h4>
                                    <p class="text-gray-400 text-xs">
                                        @if($loan->monthly_deduction > 0)
                                            The next payroll run deducts ৳ {{ $taka($loan->monthly_deduction) }}.
                                        @else
                                            No EMI plan — record a repayment when the money comes in.
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($payments->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200">
                        <tr class="bg-gray-100 text-sm border-t border-gray-300">
                            <td colspan="3" class="px-4 py-3 text-left font-extrabold text-gray-800">
                                {{ $payments->count() }} {{ Str::plural('Payment', $payments->count()) }}
                            </td>
                            <td class="px-4 py-3 text-right font-extrabold text-emerald-700">
                                ৳ {{ $taka($payments->sum(fn ($p) => (float) $p->amount)) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-extrabold text-gray-800">৳ {{ $taka($loan->outstanding) }}</span>
                                <p class="text-[11px] text-gray-400 font-normal leading-tight mt-0.5">closing balance</p>
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if($openingPaid > 0)
            <p class="text-xs text-gray-400 mt-2">
                ৳ {{ $taka($openingPaid) }} of this loan was already repaid before individual
                payments were recorded, so the balances above start from there.
            </p>
        @endif
    </div>

    {{-- ── Actions ── --}}
    <div class="flex justify-end gap-2 pt-1">
        {{-- The statement is the document you hand the employee who asks what is
             left on their loan, or file when one is settled. --}}
        <a href="{{ route('role.loans.statement', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'loan' => $loan->id]) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
           title="Open this loan's statement — print it or save it as a PDF">
            <i class="fas fa-file-pdf"></i> Statement
        </a>

        @can('create loan')
            @if(! $loan->is_cleared)
                <button type="button"
                        class="loan-repay-btn inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                        data-loan-id="{{ $loan->id }}">
                    <i class="fas fa-rotate-left"></i> Record repayment
                </button>
            @endif
        @endcan
    </div>
</div>
