{{--
    One row of a loan table.

    Extracted because the Active table and the Register show the SAME loan in the
    same shape — only the filter behind them differs. Two copies of this markup
    would drift within a week, and the two tables disagreeing about what a loan
    is worth is exactly the bug nobody reports because each looks plausible alone.

    Expects: $loan, $idx, $book, $role
--}}
@php
    $next      = $loan->is_running ? null : $loan->next_due;
    $isRunning = $loan->is_running;
@endphp
<tr onclick="window.location='{{ route('role.financing.show', ['role' => $role, 'financing' => $loan->id]) }}'"
    style="cursor:pointer;" title="Open this loan and its full history">
    <td><span class="fin-idx">{{ $idx }}</span></td>
    <td>
        <div class="fin-who">
            <span class="fin-ava">{{ strtoupper(mb_substr($loan->counterparty_name, 0, 2)) }}</span>
            <div style="min-width:0;">
                <span class="fin-strong">{{ $loan->counterparty_name }}</span>
                @if($loan->account_no)
                    <div class="fin-sub">{{ $loan->account_no }}</div>
                @endif
            </div>
        </div>
    </td>
    <td>
        {{ $loan->kind ?: '—' }}
        {{-- The shape is called out because the two behave differently in every
             column that follows: a running account has no rate, no due date and
             no agreed sum, and without the chip those blanks read as missing
             data rather than as facts about the arrangement. --}}
        @if($isRunning)
            <div class="fin-sub"><span class="fin-chip fin-chip-due">running account</span></div>
        @endif
        @if($book === 'borrowed' && $loan->taken_for)
            <div class="fin-sub">{{ $loan->taken_for_label }}</div>
        @elseif($book === 'lent' && (float) $loan->down_payment > 0)
            <div class="fin-sub">৳{{ number_format((float) $loan->down_payment, 2) }} paid up front</div>
        @endif
        @if($loan->linked_label)
            <div class="fin-sub">{{ $loan->linked_label }}</div>
        @endif
    </td>
    <td>@include('partials.date-time-cell', ['date' => $loan->start_date, 'recordedAt' => $loan->created_at])</td>
    <td style="text-align:right;" class="fin-num">
        @if($isRunning)
            {{-- What has been TAKEN, not a principal — there is no principal. --}}
            ৳{{ number_format($loan->drawn_amount, 2) }}
            <div class="fin-sub">taken so far</div>
        @else
            ৳{{ number_format((float) $loan->principal, 2) }}
        @endif
    </td>
    <td style="text-align:right;" class="fin-num">
        @if($isRunning)
            <span class="fin-dim">—</span>
        @else
            {{ (float) $loan->interest_rate > 0 ? rtrim(rtrim(number_format((float) $loan->interest_rate, 3), '0'), '.') . '%' : '—' }}
            <div class="fin-sub">{{ $loan->interest_method === 'none' ? 'no interest' : $loan->interest_method }}</div>
        @endif
    </td>
    <td style="text-align:right;">
        <span class="fin-strong fin-num">৳{{ number_format($loan->outstanding, 2) }}</span>
        {{-- The bar IS the number: width is the settled share, so a register can
             be scanned without reading every figure. --}}
        <div class="fin-bar {{ $loan->progress_pct >= 100 ? 'is-done' : '' }}">
            <i style="width:{{ max(2, min(100, $loan->progress_pct)) }}%"></i>
        </div>
        <div class="fin-sub">{{ $loan->progress_pct }}% settled</div>
    </td>
    <td>
        @if($isRunning)
            {{-- No schedule, so no due date. Saying "—" alone would look like
                 missing data; this says there is nothing to be late for. --}}
            <span class="fin-dim">no fixed date</span>
            <div class="fin-sub">settles on demand</div>
        @elseif($next)
            <span class="fin-num">{{ $next->due_date?->format('Y-m-d') }}</span>
            @if($next->is_overdue)<span class="fin-late">overdue</span>@endif
            <div class="fin-sub">৳{{ number_format($next->balance, 2) }} due</div>
        @else
            <span class="fin-dim">—</span>
        @endif
    </td>
    <td>
        <span class="fin-chip fin-chip-{{ $loan->status }}">{{ str_replace('_', ' ', $loan->status) }}</span>
        @if(! $isRunning && $loan->days_past_due > 0)
            {{-- Arrears ageing on the Bangladesh Bank scale. On a borrowing this
                 is our own lateness, so the band is shown without the provision
                 that only makes sense against money owed to us. --}}
            <div class="fin-sub" style="color:var(--fin-neg);">
                {{ $loan->classification_label }} · {{ $loan->days_past_due }}d
            </div>
        @endif
    </td>
    @can('create financing')
        {{-- The row itself opens the loan, but that is not discoverable — this
             says what you can do from here. stopPropagation so the button does
             not also fire the row's own navigation.

             Both actions LEAD TO A FORM. Neither pays anything by pressing it:
             the amount, the date and the account are always typed by a person on
             the loan's own page before anything is written. --}}
        <td style="text-align:center;white-space:nowrap;" onclick="event.stopPropagation();">
            @if($loan->status === 'active')
                @if($isRunning)
                    <a href="{{ route('role.financing.show', ['role' => $role, 'financing' => $loan->id]) }}#take"
                       class="fin-btn" style="padding:.3rem .6rem;font-size:.72rem;"
                       title="Record money going out on this account">
                        <i class="fas fa-arrow-up-from-bracket"></i> Take
                    </a>
                @endif
                <a href="{{ route('role.financing.show', ['role' => $role, 'financing' => $loan->id]) }}#repay"
                   class="fin-btn-primary" style="padding:.3rem .6rem;font-size:.72rem;"
                   title="Record a {{ $book === 'borrowed' ? 'repayment' : 'collection' }}">
                    <i class="fas fa-hand-holding-dollar"></i>
                    {{ $book === 'borrowed' ? 'Repay' : ($isRunning ? 'Receive' : 'Collect') }}
                </a>
            @else
                <span class="fin-dim" style="font-size:.75rem;">—</span>
            @endif
        </td>
    @endcan
</tr>
