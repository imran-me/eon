@extends('layout.app')

@section('meta-information')
    <title>{{ $loan->counterparty_name }} — Capital &amp; Financing</title>
@endsection

@section('main-content')
@php
    $role = request()->route('role') ?: \Illuminate\Support\Str::slug(auth()->user()->getRoleNames()->first() ?? '');
    $book = $loan->direction;
    $isBorrowed = $book === 'borrowed';
@endphp

<div class="fin-scope fin-scope-{{ $book }}">

@include('layout.financing-tabs')

@if(session('success'))
    <div class="fin-note" style="background:#ecfdf5;border-color:#d1fae5;color:#065f46;">
        <i class="fas fa-circle-check" style="color:#059669;"></i>{{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="fin-note" style="background:#fff1f2;border-color:#ffe4e6;color:#9f1239;">
        <i class="fas fa-triangle-exclamation" style="color:#e11d48;"></i>{{ session('error') }}
    </div>
@endif

@php
    $totalInterest = $loan->schedules->sum('interest_component');
    $pct = max(0, min(100, (float) $loan->progress_pct));
    // 2πr for r=34 — the ring's full circumference, so the dash offset can be
    // set straight from the percentage without any JavaScript.
    $circ = 213.6;
@endphp

@php
    // Whole taka, as the payroll book prints them.
    $taka = fn ($amount) => number_format(round((float) $amount));
    $rateLabel = (float) $loan->interest_rate > 0
        ? rtrim(rtrim(number_format((float) $loan->interest_rate, 3), '0'), '.') . '%'
        : null;
@endphp

{{-- .fin-hero holds ONLY the title row and the back link. The KPI cards below
     are a sibling — anything left inside would land on the same flex line. --}}
<div class="fin-hero">
    <div class="fin-hero-title">
        <h2>
            <i class="fas {{ $isBorrowed ? 'fa-building-columns' : 'fa-hand-holding-dollar' }}"></i>{{ $loan->counterparty_name }}
        </h2>
        <span class="fin-sep" aria-hidden="true"></span>
        <p>
            {{ $loan->kind ?: ($isBorrowed ? 'Borrowing' : 'Loan given') }}
            @if($loan->account_no) · {{ $loan->account_no }} @endif
            · {{ $loan->company->name ?? 'No company — personal' }}
            @if($isBorrowed && $loan->taken_for_label) · {{ $loan->taken_for_label }} @endif
            @if($loan->personal_of) · {{ $loan->personal_of }} @endif
            @if($loan->linked_label) · against {{ $loan->linked_label }} @endif
            @if($loan->purpose) · {{ $loan->purpose }} @endif
            @if($loan->security) · secured on {{ $loan->security }} @endif
        </p>
    </div>
    <a href="{{ route('role.financing.index', ['role' => $role, 'book' => $book]) }}" class="fin-btn-ghost">
        <i class="fas fa-arrow-left"></i> Register
    </a>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    @include('payroll.partials.kpi', [
        'label'     => $isBorrowed ? 'Still To Pay' : 'Still To Collect',
        'value'     => '৳ ' . $taka($loan->outstanding),
        'icon'      => 'fa-building-columns',
        'iconBg'    => '#fee2e2',
        'iconText'  => '#dc2626',
        'valueTone' => $loan->outstanding > 0 ? 'text-red-600' : 'text-green-600',
        'goodDown'  => true,
        'foot'      => $loan->progress_pct . '% of principal settled',
    ])
    {{-- A running account has no principal and no instalment, so these two
         answer the questions it DOES have: how much has been taken in total, and
         how many separate takings that was. Showing ৳0 principal on an account
         carrying a real balance would be worse than showing nothing. --}}
    @include('payroll.partials.kpi', [
        'label'    => $loan->is_running ? 'Taken In All' : ((float) $loan->down_payment > 0 ? 'Financed' : 'Principal'),
        'value'    => '৳ ' . $taka($loan->is_running ? $loan->drawn_amount : $loan->principal),
        'icon'     => 'fa-money-bill-transfer',
        'iconBg'   => '#dbeafe',
        'iconText' => '#2563eb',
        'foot'     => $loan->is_running
            ? '৳ ' . $taka($loan->settled_amount) . ' paid back so far'
            : ((float) $loan->down_payment > 0
                ? '৳ ' . $taka($loan->deal_value) . ' deal · ৳ ' . $taka($loan->down_payment) . ' paid up front'
                : 'the amount borrowed'),
    ])
    @include('payroll.partials.kpi', [
        'label'    => $loan->is_running ? 'Takings' : 'Instalment',
        'value'    => $loan->is_running
            ? number_format($loan->transactions->where('type', 'disburse')->where('method', '!=', 'adjustment')->count())
            : '৳ ' . $taka($loan->instalment_amount ?? 0),
        'icon'     => $loan->is_running ? 'fa-arrows-rotate' : 'fa-calendar-check',
        'iconBg'   => '#ede9fe',
        'iconText' => '#7c3aed',
        'foot'     => $loan->is_running
            ? 'settles on demand · no fixed date'
            : $loan->schedules->count() . ' months'
                . ($rateLabel ? ' · ' . $loan->interest_method . ' ' . $rateLabel : ' · no interest'),
    ])
    @include('payroll.partials.kpi', [
        'label'     => 'Overdue',
        'value'     => number_format($loan->overdue_count),
        'icon'      => 'fa-triangle-exclamation',
        'iconBg'    => $loan->overdue_count > 0 ? '#fee2e2' : '#dcfce7',
        'iconText'  => $loan->overdue_count > 0 ? '#dc2626' : '#16a34a',
        'valueTone' => $loan->overdue_count > 0 ? 'text-red-600' : 'text-green-600',
        'goodDown'  => true,
        'foot'      => $loan->overdue_count > 0
            ? $loan->classification_label . ' · ' . $loan->days_past_due . ' days past due'
            : '৳ ' . $taka($totalInterest) . ' interest over the term',
    ])
</div>

{{-- The balance has run past zero and now points the other way.
     "Still To Collect" is clamped at zero — it has to be, the ring and the
     register are built on it — so without this line the desk reads 0.00 and
     the overshoot is visible nowhere on the page, while the ledger has it
     recorded correctly. That silence is the whole problem: the money is real
     and only this number knows about it.

     Ordinary on a running account, which swings both ways by nature. On a
     fixed loan recordPayment() refuses a payment bigger than what is left, so
     this can only be a row written before that refusal existed — said plainly
     rather than dressed up as normal. --}}
@if($loan->overpaid > 0)
    <div class="fin-hint" style="margin:-0.4rem 0 1.25rem;padding:.7rem .9rem;background:#fffbeb;border:1px solid #fde68a;border-radius:.5rem;color:#92400e;">
        <i class="fas fa-arrow-right-arrow-left" style="color:#d97706;"></i>
        <span>
            <strong>৳ {{ $taka($loan->overpaid) }} more has come back than went out.</strong>
            @if($loan->is_running)
                A running account can sit either way round — past zero it simply means
                {{ $isBorrowed ? 'the company has paid ' . ($loan->personal_of ?: $loan->counterparty_name) . ' more than it ever took, so he owes the company now' : 'the company holds ' . $loan->counterparty_name . '\'s money now, not the other way round' }}.
                The ledger carries it; <em>Still To Collect</em> stops at zero and cannot show it.
            @else
                On a fixed loan that should not be possible — every instalment was already
                settled. Check the payments below against the schedule; one of them is
                likely on the wrong loan.
            @endif
        </span>
    </div>
@endif

<div class="fin-card" style="margin-bottom:1rem;">
    <div style="padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1.6rem;flex-wrap:wrap;">
        <div class="fin-ring">
            <svg width="84" height="84" viewBox="0 0 84 84" aria-hidden="true">
                <defs>
                    <linearGradient id="finRingGrad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%"   stop-color="var(--fin-accent)"/>
                        <stop offset="100%" stop-color="var(--fin-accent-2)"/>
                    </linearGradient>
                </defs>
                <circle class="track" cx="42" cy="42" r="34" fill="none" stroke-width="9"/>
                <circle class="fill"  cx="42" cy="42" r="34" fill="none" stroke-width="9"
                        stroke-dasharray="{{ $circ }}"
                        stroke-dashoffset="{{ $circ - ($circ * $pct / 100) }}"/>
            </svg>
            <div class="fin-ring-label">
                <div class="v">{{ $loan->progress_pct }}%</div>
                <div class="k">Settled</div>
            </div>
        </div>

        <div style="flex:1 1 300px;min-width:0;">
            @php
                $paidCount   = $loan->schedules->where('status', 'paid')->count();
                $totalCount  = $loan->schedules->count();
                $totalCost   = (float) $loan->principal + $totalInterest;
                $costRatio   = (float) $loan->principal > 0 ? round($totalInterest / (float) $loan->principal * 100, 1) : 0;

                // Interest actually recognised so far, from the movement log —
                // not a proportion of the schedule, which would overstate it on
                // a reducing loan where early instalments are interest-heavy.
                $interestPaid = (float) $loan->transactions->sum('interest_part');
                $interestLeft = max(0, round($totalInterest - $interestPaid, 2));

                // Pace: how far through the term against how much is settled.
                // On a reducing loan these diverge by design early on, so this
                // compares settled value against elapsed time, which is what
                // actually tells you if the loan is running behind.
                $elapsed = $loan->start_date
                    ? min($totalCount, max(0, $loan->start_date->diffInMonths(now())))
                    : 0;
                $termPct = $totalCount > 0 ? round($elapsed / $totalCount * 100, 1) : 0;
                $pace    = round($loan->progress_pct - $termPct, 1);

                $payoff = $loan->schedules->last()?->due_date;
            @endphp

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem 1.25rem;">
                <div>
                    <div class="fin-sub" style="margin:0 0 3px;">Total cost of credit</div>
                    <div class="fin-strong fin-num" style="font-size:.92rem;">৳{{ number_format($totalCost, 2) }}</div>
                    <div class="fin-sub">interest is {{ $costRatio }}% of principal</div>
                </div>
                <div>
                    <div class="fin-sub" style="margin:0 0 3px;">Interest {{ $isBorrowed ? 'paid' : 'earned' }} / remaining</div>
                    <div class="fin-strong fin-num" style="font-size:.92rem;">৳{{ number_format($interestPaid, 2) }}</div>
                    <div class="fin-sub">৳{{ number_format($interestLeft, 2) }} still to come</div>
                </div>
                <div>
                    <div class="fin-sub" style="margin:0 0 3px;">Pace</div>
                    <div class="fin-strong fin-num" style="font-size:.92rem;color:{{ $pace < -5 ? 'var(--fin-neg)' : ($pace > 5 ? 'var(--fin-pos)' : 'inherit') }};">
                        {{ $pace > 0 ? '+' : '' }}{{ $pace }} pts
                    </div>
                    <div class="fin-sub">{{ $loan->progress_pct }}% settled vs {{ $termPct }}% of term</div>
                </div>
                <div>
                    <div class="fin-sub" style="margin:0 0 3px;">Final instalment</div>
                    <div class="fin-strong fin-num" style="font-size:.92rem;">{{ $payoff?->format('Y-m-d') ?? '—' }}</div>
                    <div class="fin-sub">{{ $paidCount }} of {{ $totalCount }} settled</div>
                </div>
            </div>

            <p style="margin:1rem 0 0;font-size:.77rem;color:var(--fin-mute);line-height:1.65;">
                @if($loan->next_due)
                    Next instalment <strong>#{{ $loan->next_due->instalment_no }}</strong> on
                    {{ $loan->next_due->due_date?->format('Y-m-d') }} for
                    ৳{{ number_format($loan->next_due->balance, 2) }}.
                    @if($loan->overdue_count > 0)
                        <span style="color:var(--fin-neg);">{{ $loan->overdue_count }} instalment{{ $loan->overdue_count === 1 ? ' is' : 's are' }} already past due.</span>
                    @endif
                @else
                    Nothing outstanding — every instalment is settled.
                @endif
            </p>

            <div class="fin-legend" style="margin-top:.9rem;">
                <span><i style="background:var(--fin-ink)"></i> Principal</span>
                <span><i style="background:#cbd5e1"></i> Interest</span>
            </div>
        </div>
    </div>
</div>

{{-- The bridge to the ledger. This desk posts nothing, so the entry that DOES
     hit the books is made in Manage Banks — and the contra account chosen there
     is the whole of the bookkeeping. Naming it here removes the guess. --}}
<div class="fin-card" style="margin-bottom:1rem;">
    <div class="fin-card-head"><strong>How to post this in the books</strong></div>
    <div style="padding:1.05rem 1.15rem;">
        @if($loan->glAccount || $loan->glInterestAccount)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1rem 1.5rem;">
                <div>
                    <div class="fin-sub" style="margin:0 0 4px;">
                        {{ $isBorrowed ? 'Repayment — principal' : 'Collection — principal' }}
                    </div>
                    <div class="fin-strong fin-num">
                        {{ $loan->glAccount ? $loan->glAccount->code . ' — ' . $loan->glAccount->name : 'not set' }}
                    </div>
                    <div class="fin-sub">
                        Manage Banks &rsaquo; {{ $isBorrowed ? 'Withdraw' : 'Deposit' }}, contra = this account
                    </div>
                </div>
                <div>
                    <div class="fin-sub" style="margin:0 0 4px;">
                        {{ $isBorrowed ? 'Interest — expense' : 'Interest — income' }}
                    </div>
                    <div class="fin-strong fin-num">
                        {{ $loan->glInterestAccount ? $loan->glInterestAccount->code . ' — ' . $loan->glInterestAccount->name : 'not set' }}
                    </div>
                    <div class="fin-sub">
                        @if($loan->next_due && (float) $loan->next_due->interest_component > 0)
                            next instalment carries ৳{{ number_format((float) $loan->next_due->interest_component, 2) }} interest
                        @else
                            no interest on this loan
                        @endif
                    </div>
                </div>
            </div>

            @if($loan->next_due && (float) $loan->next_due->interest_component > 0)
                <p class="fin-hint" style="margin-top:1rem;">
                    <i class="fas fa-circle-info"></i>
                    A bank entry takes <strong>one</strong> contra account, so an instalment split
                    between principal and interest needs two entries — or a single two-line entry in
                    Manage Journals. Next instalment splits
                    ৳{{ number_format((float) $loan->next_due->principal_component, 2) }} principal
                    and ৳{{ number_format((float) $loan->next_due->interest_component, 2) }} interest.
                </p>
            @endif
        @else
            <p style="margin:0;font-size:.79rem;color:var(--fin-mute);line-height:1.65;">
                No posting accounts set for this loan. Whoever records the payment in Manage Banks has
                to pick the contra account themselves — for a
                {{ $isBorrowed ? 'borrowing that is usually a loan-payable account (2210, 2120)' : 'loan given that is usually a receivable (1050)' }}.
            </p>
        @endif

        <p style="margin:1rem 0 0;font-size:.77rem;color:var(--fin-faint);line-height:1.65;">
            This desk keeps its own numbers and posts no journal entry. Where a customer is paying for
            a service in instalments, the sale already put that money on the books as a receivable —
            collections must clear <em>that</em> receivable, or the same taka is counted twice.
        </p>
    </div>
</div>

{{-- ── Record a taking (running accounts only) ────────────────────────── --}}
@can('create financing')
@if($loan->is_running && $loan->status === 'active')
@if($isBorrowed && ! $loan->is_personal && $loan->company_id)
@php
    $openingTxn = $loan->transactions->first(
        fn ($txn) => $txn->type === 'disburse' && $txn->method === 'adjustment'
    );
@endphp
@if($openingTxn)
<div class="fin-note" style="margin-bottom:1rem;background:#ecfdf5;border-color:#a7f3d0;color:#065f46;">
    <i class="fas fa-circle-check" style="color:#059669;"></i>
    <span>
        <strong>Opening liability already recorded: ৳{{ number_format((float) $openingTxn->amount, 2) }}</strong>
        on {{ $openingTxn->date?->format('d M Y') }}.
        No bank or cash balance was changed.
        @if($openingTxn->journal_entry_id)
            Journal #{{ $openingTxn->journal_entry_id }}.
        @endif
    </span>
</div>
@elseif($loan->transactions->isEmpty())
<div class="fin-card" id="opening-balance" style="margin-bottom:1rem;scroll-margin-top:5rem;border-color:#fde68a;">
    <form method="POST" action="{{ route('role.financing.opening-balance', ['role' => $role, 'financing' => $loan->id]) }}">
        @csrf
        <div style="padding:.85rem 1rem;border-bottom:1px solid #fef3c7;background:#fffbeb;">
            <strong style="font-size:.85rem;color:#92400e;">
                <i class="fas fa-flag"></i> Record opening liability
            </strong>
            <div class="fin-sub" style="color:#a16207;">
                already owed before this ERP started - no bank or cash balance changes
            </div>
        </div>
        <div style="padding:1rem;">
            <div class="fin-grid">
                <div class="fin-field">
                    <label>Outstanding amount (৳) <span>*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" value="{{ old('amount') }}">
                </div>
                <div class="fin-field">
                    <label>Opening date <span>*</span></label>
                    <input type="date" name="date" required value="{{ old('date', date('Y-m-d')) }}">
                </div>
                <div class="fin-field" style="grid-column:span 2;">
                    <label>Note</label>
                    <input type="text" name="memo" placeholder="e.g. Balance outstanding when ERP started" value="{{ old('memo') }}">
                </div>
            </div>

            <p class="fin-hint" style="margin-bottom:.9rem;background:#fffbeb;border-color:#fde68a;color:#92400e;">
                <i class="fas fa-circle-info"></i>
                <span>
                    Saving posts <strong>Dr {{ config('accounts.opening_balance_equity') }} Opening Balance Equity</strong>
                    and <strong>Cr {{ $loan->glAccount?->code }} {{ $loan->glAccount?->name ?: 'the selected loan liability' }}</strong>.
                    Use this once as the first movement. Use <em>Record drawing</em> below only for new money received later.
                </span>
            </p>

            <button type="submit" class="fin-btn-primary" style="background:#d97706;">
                <i class="fas fa-flag"></i> Record opening liability
            </button>
        </div>
    </form>
</div>
@else
<div class="fin-note" style="margin-bottom:1rem;background:#fffbeb;border-color:#fde68a;color:#92400e;">
    <i class="fas fa-triangle-exclamation" style="color:#d97706;"></i>
    Opening liability is unavailable because this account already has movements. It can only be its first movement.
</div>
@endif
@endif
{{-- id="take" is the anchor the register's Take button targets. Nothing is
     written by arriving here: the amount, the date and the account are all typed
     before the button does anything. --}}
@php
    // Every label in this block depends on which way the money moves, and the
    // whole thing used to be written for a LENT account only. On a borrowing the
    // cash arrives instead of leaving, so "Taken from" named the wrong side of
    // the transfer and the posting note stated the entry upside down.
    $isBorrowing = $loan->direction === 'borrowed';
@endphp
<div class="fin-card" id="take" style="margin-bottom:1rem;scroll-margin-top:5rem;">
    <form method="POST" action="{{ route('role.financing.drawdown', ['role' => $role, 'financing' => $loan->id]) }}">
        @csrf
        <div style="padding:.85rem 1rem;border-bottom:1px solid #f1f5f9;">
            <strong style="font-size:.85rem;color:#1e293b;">
                <i class="fas fa-arrow-{{ $isBorrowing ? 'down-to-bracket' : 'up-from-bracket' }}"></i>
                {{ $isBorrowing ? 'Record a drawing' : 'Record a taking' }}
            </strong>
            <div class="fin-sub">
                {{ $isBorrowing
                    ? 'money coming in on this facility — what you owe goes up'
                    : 'money going out on this account — the balance goes up' }}
            </div>
        </div>
        <div style="padding:1rem;">
            <div class="fin-grid">
                <div class="fin-field">
                    <label>{{ $isBorrowing ? 'Amount drawn' : 'Amount taken' }} (৳) <span>*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" value="{{ old('amount') }}">
                </div>
                <div class="fin-field">
                    <label>Date <span>*</span></label>
                    <input type="date" name="date" required value="{{ old('date', date('Y-m-d')) }}">
                </div>
                {{-- Required, not optional. A movement with no account named cannot
                     be posted, and an unposted movement is cash the books never
                     heard about — in or out. --}}
                <div class="fin-field">
                    <label>{{ $isBorrowing ? 'Received into' : 'Taken from' }} <span>*</span></label>
                    <select name="bank_id" required class="fin-search">
                        <option value="">— select account —</option>
                        @include('partials.bank-options', ['banks' => $banks, 'selected' => old('bank_id')])
                    </select>
                </div>
                <div class="fin-field">
                    <label>What for</label>
                    <input type="text" name="memo" placeholder="Optional" value="{{ old('memo') }}">
                </div>
            </div>

            <p class="fin-hint" style="margin-bottom:.9rem;">
                @if($loan->taken_for === 'personal')
                    <i class="fas fa-circle-info" style="color:#b45309;"></i>
                    <span>
                        This facility is in a person's own name, so <strong>nothing reaches the
                        company's books</strong> — the drawing is kept on this desk as a record only.
                    </span>
                @elseif($loan->glAccount && $isBorrowing)
                    <i class="fas fa-circle-check" style="color:#16a34a;"></i>
                    <span>
                        Saving posts <strong>Dr</strong> the account you choose above for the cash that
                        arrived, and <strong>Cr {{ $loan->glAccount->code }} {{ $loan->glAccount->name }}</strong>
                        for what the company now owes. No interest is charged today —
                        it is settled on repayment.
                    </span>
                @elseif($loan->glAccount)
                    <i class="fas fa-circle-check" style="color:#16a34a;"></i>
                    <span>
                        Saving posts <strong>Dr {{ $loan->glAccount->code }} {{ $loan->glAccount->name }}</strong>
                        against the account you choose above. This is <strong>not an expense</strong> —
                        it is money owed back, so it never touches profit.
                    </span>
                @else
                    <i class="fas fa-triangle-exclamation" style="color:#dc2626;"></i>
                    <span>
                        This account has no posting account set, so the movement will be refused.
                        Set <strong>Posts against — principal</strong> on it first — for the boss's
                        own account that is {{ config('accounts.director_current_account') }} Director's Current Account.
                    </span>
                @endif
            </p>

            <button type="submit" class="fin-btn-primary">
                <i class="fas fa-plus"></i> {{ $isBorrowing ? 'Record drawing' : 'Record taking' }}
            </button>
        </div>
    </form>
</div>
@endif
@endcan

{{-- ── Record a payment ───────────────────────────────────────────────── --}}
@can('create financing')
@if($loan->status === 'active')
{{-- id="repay" is the anchor the register's Repay button targets, and
     scroll-margin keeps the card clear of the sticky header when it lands. --}}
<div class="fin-card" id="repay" style="margin-bottom:1rem;scroll-margin-top:5rem;">
    <form method="POST" action="{{ route('role.financing.payment', ['role' => $role, 'financing' => $loan->id]) }}">
        @csrf
        <div style="padding:.85rem 1rem;border-bottom:1px solid #f1f5f9;">
            <strong style="font-size:.85rem;color:#1e293b;">
                Record {{ $isBorrowed ? 'a repayment' : 'a collection' }}
            </strong>
        </div>
        <div style="padding:1rem;">
            <div class="fin-grid">
                @if(! $loan->is_running)
                    @php
                        // A payment on a term loan always settles some instalment —
                        // there is nothing else for it to settle. Leaving the picker
                        // blank looked harmless and was not: the money posted to the
                        // ledger while every row stayed "Due" and the page kept
                        // reporting 0% settled, and principal/interest were not split
                        // because that split is read off the instalment.
                        //
                        // So the oldest unsettled one is preselected and the field is
                        // required. Oldest first is not a guess — instalments settle
                        // in order, and any other choice is the exception.
                        $openInstalments = $loan->schedules->whereIn('status', ['due', 'partial'])->sortBy('instalment_no');
                        $nextInstalment  = $openInstalments->first();
                    @endphp
                    <div class="fin-field">
                        <label>Apply to instalment @if($nextInstalment)<span>*</span>@endif</label>
                        <select name="financing_schedule_id" class="fin-search" @required((bool) $nextInstalment)>
                            @if($nextInstalment)
                                @foreach($openInstalments as $s)
                                    <option value="{{ $s->id }}" @selected(old('financing_schedule_id', $nextInstalment->id) == $s->id)>
                                        #{{ $s->instalment_no }} · due {{ $s->due_date?->format('Y-m-d') }} · ৳{{ number_format($s->balance, 2) }} left
                                    </option>
                                @endforeach
                            @else
                                {{-- Every instalment is settled, so there is nothing left
                                     to apply a payment to. Saying so beats an empty list. --}}
                                <option value="">— every instalment is settled —</option>
                            @endif
                        </select>
                        @if($nextInstalment)
                            <p class="fin-hint" style="margin-top:.3rem;">
                                Settles in order. The amount is split into principal and interest
                                the way this instalment carries them.
                            </p>
                        @endif
                    </div>
                @endif
                <div class="fin-field">
                    <label>Amount (৳) <span>*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" value="{{ old('amount') }}">
                </div>
                <div class="fin-field">
                    <label>Date <span>*</span></label>
                    <input type="date" name="date" required value="{{ old('date', date('Y-m-d')) }}">
                </div>
                <div class="fin-field">
                    <label>Method <span>*</span></label>
                    <select name="method" required>
                        <option value="bank" @selected(old('method') === 'bank')>Bank</option>
                        <option value="cash" @selected(old('method') === 'cash')>Cash</option>
                        <option value="adjustment" @selected(old('method') === 'adjustment')>Adjustment — no money moved</option>
                    </select>
                </div>
                {{-- Which account the money actually moved through. The bank list
                     already includes cash and mobile-banking accounts, so one
                     picker covers all of them, and each carries the ledger
                     account the entry is posted against.

                     The label follows the direction, because the same field
                     means opposite things on the two books: a borrowing pays
                     OUT of the account (credit), a loan given is collected INTO
                     it (debit). "Paid from" over a collection described the leg
                     backwards on every lent loan.

                     ONE field, never two: a second <select name="bank_id"> in
                     the same form silently overwrites this one on submit, so
                     whatever was chosen here would never reach the server. --}}
                <div class="fin-field">
                    <label>{{ $isBorrowed ? 'Paid from' : 'Received into' }} @if($postsToBooks)<span>*</span>@endif</label>
                    <select name="bank_id">
                        <option value="">— select account —</option>
                        @include('partials.bank-options', ['banks' => $banks, 'selected' => old('bank_id')])
                    </select>
                </div>
                @if($isBorrowed && ! $loan->is_personal)
                    {{-- A closure charge on an early settlement rides along inside the
                         same payment. Kept apart so it is never mistaken for principal
                         that came off the loan. --}}
                    <div class="fin-field">
                        <label>Of which lender's fee (৳)</label>
                        <input type="number" name="fee_part" step="0.01" min="0" placeholder="0.00" value="{{ old('fee_part') }}">
                    </div>
                    {{-- Withheld from interest paid to a PERSON — a director who lent
                         the company money. Never on a bank's interest. It is taken out
                         of what he receives, so the bank pays out amount minus this
                         and the difference is held for the NBR. --}}
                    <div class="fin-field">
                        <label>Tax withheld (TDS) (৳)<span class="fin-i-wrap"><button type="button" class="fin-i" aria-expanded="false" aria-label="When TDS applies">i</button><span class="fin-i-pop">Only when the interest is paid to a <strong>person</strong> — a director who lent the company money — never on a bank's interest. It is withheld <strong>from</strong> the payment, so the bank pays out the amount minus this, and the difference sits in Withholding Tax Payable until it is deposited with the NBR.</span></span></label>
                        <input type="number" name="tds_amount" step="0.01" min="0" placeholder="0.00" value="{{ old('tds_amount') }}">
                    </div>
                @endif
                <div class="fin-field">
                    <label>Memo</label>
                    <input type="text" name="memo" placeholder="Optional" value="{{ old('memo') }}">
                </div>
            </div>

            @if($loan->is_personal)
                {{-- The question that decides whether this movement exists in the
                     company's books at all. Asked here because it cannot be worked
                     out afterwards, and deliberately left UNSELECTED: a preselected
                     answer would invent a withdrawal on every form nobody read. --}}
                <div class="fin-field" style="margin-top:.9rem;">
                    <label>Who paid this instalment? <span>*</span><span class="fin-i-wrap"><button type="button" class="fin-i" aria-expanded="false" aria-label="Why this question matters">i</button><span class="fin-i-pop">This is a loan in {{ $loan->personal_of ? $loan->personal_of . "'s" : "a person's" }} own name. Paid from his <strong>own pocket</strong>, the company was never involved and nothing is recorded. Paid from a <strong>company account</strong>, that is money taken out for private use — it posts in full to Drawings, never as an expense, and no part of it counts as interest.</span></span></label>
                    <div style="display:flex;flex-wrap:wrap;gap:1.1rem;padding:.35rem 0;">
                        <label style="display:flex;align-items:center;gap:.4rem;font-weight:500;text-transform:none;letter-spacing:0;cursor:pointer;">
                            <input type="radio" name="paid_by" value="personal" required @checked(old('paid_by') === 'personal')>
                            {{ $loan->personal_of ?: 'He' }} paid it himself
                        </label>
                        <label style="display:flex;align-items:center;gap:.4rem;font-weight:500;text-transform:none;letter-spacing:0;cursor:pointer;">
                            <input type="radio" name="paid_by" value="company" required @checked(old('paid_by') === 'company')>
                            Paid from a company account
                        </label>
                    </div>
                </div>
            @endif

            {{-- Say plainly whether this reaches the books, so nobody has to guess
                 whether a second entry in Manage Banks is needed. Three states,
                 because "it will post" and "it is meant to post but cannot yet"
                 are different problems and only one of them is the user's. --}}
            @if($postsToBooks && ! $loan->glAccount)
                <p class="fin-hint" style="margin-bottom:.9rem;">
                    <i class="fas fa-triangle-exclamation" style="color:#dc2626;"></i>
                    <span>
                        This loan has no posting account set, so
                        {{ $isBorrowed ? 'a repayment' : 'a collection' }} cannot reach the books
                        and will be refused. Set <strong>Posts against — principal</strong> on the
                        loan first.
                    </span>
                </p>
            @elseif($postsToBooks && ! $isBorrowed)
                {{-- The mirror of the borrowed branch below, and the wording has
                     to mirror it too: on a loan given the money ARRIVES, so the
                     account chosen above is debited and the borrower's debt to
                     us comes down. The borrowed sentence printed over a
                     collection named the debit and the credit the wrong way
                     round — see postLentReceipt() for the legs it describes.

                     Interest is read from config, not from glInterestAccount:
                     postLentReceipt() credits accounts.loan_interest_income and
                     never looks at the loan's own interest account, because
                     interest earned is income and that field points at the
                     expense side. Naming it here would promise an account the
                     entry does not touch. --}}
                <p class="fin-hint" style="margin-bottom:.9rem;">
                    <i class="fas fa-circle-check" style="color:#16a34a;"></i>
                    <span>
                        Saving posts to the ledger:
                        <strong>Dr the account you choose above</strong> for what arrives, credited to
                        <strong>{{ $loan->glAccount->code }} {{ $loan->glAccount->name }}</strong> for the principal
                        and <strong>{{ config('accounts.loan_interest_income') }} Interest Income</strong>
                        for the interest part, when the instalment carries one.
                        No second entry in Manage Banks.
                    </span>
                </p>
            @elseif($postsToBooks)
                <p class="fin-hint" style="margin-bottom:.9rem;">
                    <i class="fas fa-circle-check" style="color:#16a34a;"></i>
                    <span>
                        Saving posts to the ledger:
                        <strong>Dr {{ $loan->glAccount->code }} {{ $loan->glAccount->name }}</strong> for the principal
                        @if($loan->glInterestAccount)
                            and <strong>Dr {{ $loan->glInterestAccount->code }} {{ $loan->glInterestAccount->name }}</strong> for the interest,
                        @else
                            ,
                        @endif
                        credited to the account you choose above. No second entry in Manage Banks.
                    </span>
                </p>
            @elseif($loan->is_personal)
                <p class="fin-hint" style="margin-bottom:.9rem;">
                    <i class="fas fa-circle-info"></i>
                    <span>
                        Paid from his own pocket, nothing is recorded — the company was never involved.
                        Paid from a company account, the <strong>whole amount</strong> posts as
                        <strong>Dr {{ config('accounts.owner_drawings') }} Drawings</strong>: never an
                        expense, never split, and no part of it interest.
                    </span>
                </p>
            @else
                <p class="fin-hint" style="margin-bottom:.9rem;">
                    <i class="fas fa-circle-info"></i>
                    <span>
                        This one is recorded on the desk only —
                        @if($loan->direction === 'lent' && empty($loan->gl_account_id))
                            {{-- A setup gap, not a decision. Saying "already on the books
                                 through the sale" here would explain away a missing field
                                 as though it were intended, and nobody would go and set it. --}}
                            this loan has no <strong>Posts against — principal</strong> account, so there is
                            nothing for a collection to be credited to.
                        @elseif($loan->direction === 'lent')
                            money lent is already on the books through the sale it came from, so posting again would count it twice.
                        @else
                            this loan is not set up to post.
                        @endif
                        Enter the bank movement in Manage Banks if the company's money was involved.
                    </span>
                </p>
            @endif

            <button type="submit" class="fin-btn-primary"><i class="fas fa-check"></i> Record</button>
        </div>
    </form>
</div>
@endif
@endcan

{{-- A running account has no schedule — nothing was ever agreed to fall
     due. Showing an empty schedule table would read as data that failed
     to load; the balance below is the whole story instead. --}}
@if(! $loan->is_running)
{{-- ── Schedule ───────────────────────────────────────────────────────── --}}
<div class="fin-card" style="margin-bottom:1rem;">
    <div style="padding:.85rem 1rem;border-bottom:1px solid #f1f5f9;">
        <strong style="font-size:.85rem;color:#1e293b;">Repayment schedule</strong>
        <span class="fin-sub" style="display:inline;margin-left:6px;">{{ $loan->schedules->count() }} instalments</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="fin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Due</th>
                    <th style="text-align:right;">Principal</th>
                    <th style="text-align:right;">Interest</th>
                    <th style="text-align:right;">Instalment</th>
                    <th style="text-align:right;">Paid</th>
                    <th style="text-align:right;">Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($loan->schedules as $s)
                <tr>
                    <td class="fin-dim">{{ $s->instalment_no }}</td>
                    <td>
                        {{ $s->due_date?->format('Y-m-d') }}
                        @if($s->is_overdue)<span class="fin-late">overdue</span>@endif
                        @if($s->paid_date)<div class="fin-sub">paid {{ $s->paid_date->format('Y-m-d') }}</div>@endif
                    </td>
                    <td style="text-align:right;" class="fin-num">৳{{ number_format((float) $s->principal_component, 2) }}</td>
                    <td style="text-align:right;" class="fin-num">৳{{ number_format((float) $s->interest_component, 2) }}</td>
                    <td style="text-align:right;">
                        <span class="fin-strong fin-num">৳{{ number_format((float) $s->total_amount, 2) }}</span>
                        {{-- The bar IS this row's split: on a reducing loan the amber
                             interest share visibly shrinks as you read down. --}}
                        @php
                            $tot = (float) $s->total_amount ?: 1;
                            $pShare = (float) $s->principal_component / $tot * 100;
                        @endphp
                        <div class="fin-split" title="Principal ৳{{ number_format((float) $s->principal_component, 2) }} · Interest ৳{{ number_format((float) $s->interest_component, 2) }}">
                            <span class="p" style="width:{{ $pShare }}%"></span>
                            <span class="i" style="width:{{ 100 - $pShare }}%"></span>
                        </div>
                    </td>
                    <td style="text-align:right;" class="fin-num">৳{{ number_format((float) $s->paid_amount, 2) }}</td>
                    <td style="text-align:right;" class="fin-num">৳{{ number_format($s->balance, 2) }}</td>
                    <td><span class="fin-chip fin-chip-{{ $s->status }}">{{ $s->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="fin-empty">No schedule generated.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@else
<div class="fin-card" style="margin-bottom:1rem;">
    <div style="padding:.85rem 1rem;">
        <strong style="font-size:.85rem;color:#1e293b;">No schedule — this is a running account</strong>
        <p class="fin-sub" style="margin:.4rem 0 0;">
            Nothing here falls due on a date. The balance is simply everything taken
            less everything paid back, and every one of those movements is listed below.
        </p>
    </div>
</div>
@endif

{{-- ── Movement log ───────────────────────────────────────────────────── --}}
<div class="fin-card">
    <div style="padding:.85rem 1rem;border-bottom:1px solid #f1f5f9;">
        <strong style="font-size:.85rem;color:#1e293b;">Payments</strong>
        <span class="fin-sub" style="display:inline;margin-left:6px;">what actually changed hands</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="fin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Against</th>
                    <th>Method</th>
                    <th style="text-align:right;">Principal</th>
                    <th style="text-align:right;">Interest</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Memo</th>
                </tr>
            </thead>
            <tbody>
            @forelse($loan->transactions->sortByDesc('date') as $t)
                <tr>
                    <td>@include('partials.date-time-cell', ['date' => $t->date, 'recordedAt' => $t->created_at])</td>
                    <td><span class="fin-chip">{{ $t->type === 'disburse' && $t->method === 'adjustment' ? 'opening balance' : str_replace('_', ' ', $t->type) }}</span></td>
                    <td>{{ $t->financing_schedule_id ? '#' . ($t->schedule->instalment_no ?? '—') : '—' }}</td>
                    <td>
                        {{ $t->type === 'disburse' && $t->method === 'adjustment' ? 'Opening balance' : ucfirst($t->method) }}
                        @if($t->bank)<div class="fin-sub">{{ $t->bank->name }}</div>@endif
                        {{-- Whether this one reached the books, and under which
                             entry — so a figure on the desk can always be traced
                             to the journal it produced, or shown not to have one. --}}
                        @if($t->journal_entry_id)
                            <div class="fin-sub" style="color:#16a34a;">
                                <i class="fas fa-check"></i> posted · entry #{{ $t->journal_entry_id }}
                            </div>
                        @elseif($t->method !== 'adjustment')
                            <div class="fin-sub">desk only</div>
                        @endif
                    </td>
                    <td style="text-align:right;">৳{{ number_format((float) $t->principal_part, 2) }}</td>
                    <td style="text-align:right;">৳{{ number_format((float) $t->interest_part, 2) }}</td>
                    <td style="text-align:right;" class="fin-strong">৳{{ number_format((float) $t->amount, 2) }}</td>
                    <td class="fin-dim">{{ $t->memo ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="fin-empty">Nothing recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>{{-- /.fin-scope --}}

@include('financing.partials.styles')
@endsection
