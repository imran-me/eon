{{--
    Every movement across the whole book, newest first.

    The third table on each desk, and the one that answers "what happened this
    week" without walking into every loan to find out. Each row still links back
    to the loan it belongs to, so the trail and the arrangement are never more
    than one click apart.

    Expects: $rows (paginator of FinancingTransaction), $book, $role
--}}
<div class="fin-card" style="margin-bottom:1.15rem;">
    <div class="fin-card-head">
        <strong>{{ $book === 'borrowed' ? 'Repayment history' : 'Movement history' }}</strong>
        <span class="fin-sub" style="margin:0;">
            every taking and every payment on this book · {{ number_format($rows->total()) }} in all
        </span>
    </div>

    <div style="overflow-x:auto;">
        <table class="fin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>{{ $book === 'borrowed' ? 'Lender' : 'Borrower' }}</th>
                    <th>Type</th>
                    <th>Paid from / into</th>
                    <th style="text-align:right;">Principal</th>
                    <th style="text-align:right;">Interest</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Memo</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $t)
                <tr @if($t->loan)
                        onclick="window.location='{{ route('role.financing.show', ['role' => $role, 'financing' => $t->financing_loan_id]) }}'"
                        style="cursor:pointer;" title="Open this loan and its full history"
                    @endif>
                    <td>@include('partials.date-time-cell', ['date' => $t->date, 'recordedAt' => $t->created_at])</td>
                    <td>
                        <span class="fin-strong">{{ $t->loan?->counterparty_name ?? '—' }}</span>
                        @if($t->loan?->company)
                            <div class="fin-sub">{{ $t->loan->company->name }}</div>
                        @elseif($t->loan?->is_personal)
                            <div class="fin-sub">personal — no company</div>
                        @endif
                    </td>
                    <td>
                        {{-- `disburse` is money going the other way. Coloured apart
                             from a settlement so a taking is never read as a
                             payment at a glance — they move the balance in
                             opposite directions. --}}
                        <span class="fin-chip {{ $t->type === 'disburse' ? 'fin-chip-due' : 'fin-chip-paid' }}">
                            {{ $t->type === 'disburse' ? 'taken' : str_replace('_', ' ', $t->type) }}
                        </span>
                        @if($t->financing_schedule_id)
                            <div class="fin-sub">against #{{ $t->schedule->instalment_no ?? '—' }}</div>
                        @endif
                    </td>
                    <td>
                        {{ ucfirst($t->method) }}
                        @if($t->bank)<div class="fin-sub">{{ $t->bank->name }}</div>@endif
                        @if($t->journal_entry_id)
                            <div class="fin-sub" style="color:var(--fin-pos,#16a34a);">
                                <i class="fas fa-check"></i> posted · entry #{{ $t->journal_entry_id }}
                            </div>
                        @elseif($t->method !== 'adjustment')
                            <div class="fin-sub">desk only</div>
                        @endif
                    </td>
                    <td style="text-align:right;" class="fin-num">
                        {{ $t->type === 'disburse' ? '—' : '৳' . number_format((float) $t->principal_part, 2) }}
                    </td>
                    <td style="text-align:right;" class="fin-num">
                        {{ $t->type === 'disburse' ? '—' : '৳' . number_format((float) $t->interest_part, 2) }}
                    </td>
                    <td style="text-align:right;" class="fin-strong fin-num">
                        {{ $t->type === 'disburse' ? '+' : '' }}৳{{ number_format((float) $t->amount, 2) }}
                        @if((float) $t->fee_part > 0)
                            <div class="fin-sub">incl. ৳{{ number_format((float) $t->fee_part, 2) }} fee</div>
                        @endif
                        @if((float) $t->tds_amount > 0)
                            <div class="fin-sub">less ৳{{ number_format((float) $t->tds_amount, 2) }} TDS</div>
                        @endif
                    </td>
                    <td class="fin-dim">{{ $t->memo ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="fin-empty">
                    <i class="fas fa-receipt"></i>
                    Nothing has moved on this book yet.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->hasPages())
        <div class="fin-pager">{{ $rows->links() }}</div>
    @endif
</div>
