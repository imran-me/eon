{{--
    The Liability book — what one company owes another because its expense was
    settled from that company's bank account.

    Three tables, the same three every other book on this desk uses:
      1 · Active Liability          — still owed, in whole or in part
      2 · Liability Register        — settled, written off or cancelled
      3 · Liability Transaction     — every movement, newest first

    ── NOTHING HERE POSTS ────────────────────────────────────────────────────
    No journal, no balance moved. The rule, and what it deliberately leaves
    unfixed in the ledger, is on the create_inter_company_liability_tables
    migration. The note at the top of this page says the same thing to the user,
    because a desk that shows debts without saying it is not the accounts would
    be read as the accounts.

    Expects: $liabilities, $settledLiabilities, $liabilityTxns, $liabilityTotals,
             $liabilityPairs, $liabilityReady, $role, $taka
--}}

@if(! $liabilityReady)
    {{-- The migration has not run on this server. Say so plainly rather than
         rendering four zeroes, which would read as "no company owes anything"
         — the one wrong answer that looks exactly like a right one. --}}
    <div class="fin-note" style="background:#fff7ed;border-color:#fed7aa;color:#9a3412;">
        <i class="fas fa-triangle-exclamation" style="color:#ea580c;"></i>
        The Liability book is not installed on this server yet — its tables are missing.
        Run the migration for this desk alone and it will appear:
        <code>php artisan migrate --path=database/migrations/2026_08_22_000001_create_inter_company_liability_tables.php</code>
        {{-- Deliberately NOT a bare `php artisan migrate`. On a live server other
             people's migrations are often sitting pending, and several of them
             alter tables every company shares — running them as a side effect of
             installing this desk is how an unrelated outage starts. --}}
    </div>
@else

<style>
    /* Prefixed .lia- and loaded only by this book, so nothing here can reach
       the loan books beside it or any other company's screens. */
    .lia-pairs { padding: .35rem 0; }
    .lia-pair {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 120px auto;
        align-items: center;
        gap: 1rem;
        padding: .7rem 1.15rem;
        border-top: 1px solid var(--fin-line);
    }
    .lia-pair:first-child { border-top: 0; }
    .lia-pair-who { display: flex; align-items: center; gap: .55rem; flex-wrap: wrap; min-width: 0; }
    .lia-pair-name { font-size: .8rem; font-weight: 700; color: #111827; }
    .lia-pair-who > i { font-size: .65rem; color: #9ca3af; }
    .lia-pair-items {
        font-size: .68rem; font-weight: 600; color: #6b7280;
        background: #f3f4f6; border-radius: 999px; padding: 1px 7px;
    }
    /* Relative to the biggest pair, so the longest bar is always full width and
       the others read against it. */
    .lia-pair-bar { height: 6px; background: #f1f5f9; border-radius: 999px; overflow: hidden; }
    .lia-pair-bar > span { display: block; height: 100%; background: #f87171; border-radius: inherit; }
    .lia-pair-amt { font-size: .82rem; font-weight: 800; color: #dc2626; white-space: nowrap; text-align: right; }

    @media (max-width: 700px) {
        .lia-pair { grid-template-columns: minmax(0, 1fr) auto; }
        .lia-pair-bar { grid-column: 1 / -1; order: 3; }
    }

    /* The explainer note, open and shut. .fin-note is display:flex, so the body
       is one flex item and everything folds inside it rather than the note's
       own layout changing. */
    /* flex:1 so the body fills the note in BOTH states. Without it the collapsed
       row shrinks to its text and the chevron sits against the sentence, then
       jumps to the right edge once the long paragraph forces full width —
       the same control appearing to move between renders. */
    .lia-note-body { display: flex; flex-direction: column; gap: .4rem; min-width: 0; flex: 1 1 auto; }
    .lia-note-toggle {
        display: flex; align-items: center; gap: .5rem; width: 100%;
        background: none; border: 0; padding: 0; margin: 0;
        font: inherit; color: inherit; text-align: left; cursor: pointer;
    }
    .lia-note-toggle > span { flex: 1 1 auto; min-width: 0; }
    .lia-note-chev { flex: none; font-size: .65rem; opacity: .6; transition: transform .18s ease; }
    .lia-note.is-collapsed .lia-note-chev { transform: rotate(-90deg); }
    .lia-note.is-collapsed .lia-note-full { display: none; }
    /* Shown only while open — the summary line would otherwise repeat the
       sentence sitting right beneath it. */
    .lia-note:not(.is-collapsed) .lia-note-toggle > span strong { display: none; }
    .lia-note-toggle:focus-visible { outline: 2px solid currentColor; outline-offset: 3px; border-radius: 3px; }

    /* The "how" chooser in the settle modal. Two cards rather than a select:
       one of them moves real money and the other does not, which is too big a
       difference to hide inside a dropdown row. */
    .lia-how { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
    .lia-how-opt {
        display: flex; align-items: flex-start; gap: .5rem;
        border: 1px solid var(--fin-line); border-radius: 8px;
        padding: .55rem .7rem; cursor: pointer; margin: 0;
    }
    .lia-how-opt:hover { background: #f9fafb; }
    .lia-how-opt input { margin-top: .2rem; flex: none; }
    .lia-how-opt span { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
    .lia-how-opt strong { font-size: .78rem; font-weight: 700; color: #111827; }
    .lia-how-opt small { font-size: .68rem; color: #6b7280; line-height: 1.25; }
    .lia-how-opt:has(input:checked) { border-color: #2563eb; background: #eff6ff; }

    /* Unavailable because a company has no account. Dimmed, but the reason line
       stays readable — it is the whole point of the state, so it must not fade
       with everything else. Amber rather than grey: this is a "you need to do
       something first", not a permanently dead control. */
    .lia-how-opt.is-blocked { cursor: not-allowed; background: #fffbeb; border-color: #fde68a; }
    .lia-how-opt.is-blocked:hover { background: #fffbeb; }
    .lia-how-opt.is-blocked strong { color: #92400e; opacity: .75; }
    .lia-how-opt.is-blocked small { color: #b45309; }

    @media (max-width: 560px) { .lia-how { grid-template-columns: 1fr; } }

    html.dark .lia-pair-name { color: #e2e8f0; }
    html.dark .lia-pair-items { background: #1e293b; color: #94a3b8; }
    html.dark .lia-pair-bar { background: #1e293b; }
    html.dark .lia-how-opt strong { color: #e2e8f0; }
    html.dark .lia-how-opt:hover { background: #1e293b; }
    html.dark .lia-how-opt:has(input:checked) { border-color: #3b82f6; background: #172554; }
    html.dark .lia-how-opt.is-blocked { background: #292524; border-color: #78350f; }
    html.dark .lia-how-opt.is-blocked:hover { background: #292524; }
    html.dark .lia-how-opt.is-blocked strong { color: #fbbf24; }
    html.dark .lia-how-opt.is-blocked small { color: #fcd34d; }
</style>

@php
    // The settle route may not be registered yet on a server whose route cache
    // predates this deploy. Calling route() on a missing name throws and would
    // take the whole desk down — the 2026-08-10 shape. Degrade to a book you
    // can read but not settle from, which is strictly better than a 500.
    $canSettleHere = \Illuminate\Support\Facades\Route::has('role.financing.liability.settle')
        && auth()->user()?->can('create financing');
@endphp

{{-- The body goes in ONE span. .fin-note is display:flex, so every <strong>
     and the text between them becomes a separate flex item and the sentence
     renders as columns. Every other note in this desk wraps its text for the
     same reason. --}}
{{-- Shut by default: it explains how the desk works, which is read once and
     then never again, and at four lines it was pushing the actual figures off
     the first screen. Collapsing hides the explanation, never a number — the
     one line left standing still says the thing that must not be missed, that
     this book is not the ledger. --}}
<div class="fin-note lia-note is-collapsed" id="liaNote">
    <i class="fas fa-circle-info"></i>
    <span class="lia-note-body">
        <button type="button" class="lia-note-toggle" id="liaNoteToggle"
                aria-controls="liaNoteFull" aria-expanded="false">
            <span>
                How this book works —
                <strong>it posts nothing to the ledger and moves no balance.</strong>
            </span>
            <i class="fas fa-chevron-down lia-note-chev"></i>
        </button>

        <span class="lia-note-full" id="liaNoteFull">
            A row appears here when one company's expense is <strong>approved</strong> against a bank
            account belonging to another company — an Interiors site cost paid from a Travels account
            puts Interiors in debt to Travels. This book records who owes whom;
            <strong>it posts nothing to the ledger and moves no balance.</strong>
            Settling one records the payment here — if money really moved, record the transfer in
            Manage Banks and link it.
        </span>
    </span>
</div>

{{-- Inline and immediately after the element, deliberately. The markup ships
     collapsed, so the default costs no flash; this only has to run before paint
     for the minority who have opened it and want it to stay open. In the page's
     main script block it would run after first paint and the note would visibly
     unfold on every load. --}}
<script>
    (function () {
        try {
            if (localStorage.getItem('fin.liability.note') === 'open') {
                var n = document.getElementById('liaNote');
                if (n) {
                    n.classList.remove('is-collapsed');
                    document.getElementById('liaNoteToggle').setAttribute('aria-expanded', 'true');
                }
            }
        } catch (e) { /* storage blocked — it simply stays shut, which is the default */ }
    })();
</script>

{{-- ── The position between companies ───────────────────────────────────────
     One line per pair, above the per-expense rows. "What is the position
     between these two companies" is the question actually asked; answering it
     by making someone add up twelve expense rows is not answering it. --}}
@if(count($liabilityPairs))
<div class="fin-card" style="margin-bottom:1.15rem;">
    <div class="fin-card-head">
        <strong>Where the companies stand</strong>
        <span class="fin-sub" style="margin:0;">
            net of everything settled · {{ count($liabilityPairs) }}
            {{ \Illuminate\Support\Str::plural('pair', count($liabilityPairs)) }} outstanding
        </span>
    </div>
    {{-- Not a table. Five columns for "A owes B ৳x" left two thirds of the row
         empty and put a lot of air between the two company names that are the
         whole point of the line. A list row keeps the pair together and gives
         the bar somewhere to live. --}}
    @php
        // The bar is relative to the LARGEST pair, not to the total: it answers
        // "which of these is the big one", which is the question a glance asks.
        $liaPairMax = collect($liabilityPairs)->max('outstanding') ?: 1;
    @endphp
    <div class="lia-pairs">
        @foreach($liabilityPairs as $pair)
            <div class="lia-pair">
                <div class="lia-pair-who">
                    <span class="lia-pair-name">{{ $pair['debtor'] ?: '—' }}</span>
                    <i class="fas fa-arrow-right"></i>
                    <span class="lia-pair-name">{{ $pair['creditor'] ?: '—' }}</span>
                    <span class="lia-pair-items">
                        {{ $pair['items'] }} {{ \Illuminate\Support\Str::plural('item', $pair['items']) }}
                    </span>
                </div>
                <div class="lia-pair-bar" aria-hidden="true">
                    <span style="width: {{ max(3, round($pair['outstanding'] / $liaPairMax * 100)) }}%;"></span>
                </div>
                <div class="lia-pair-amt">৳{{ number_format($pair['outstanding'], 2) }}</div>
            </div>
        @endforeach
    </div>
</div>
@endif

<div class="fin-card">
    @php
        $liaToday = now()->toDateString();
        $liaYesterday = now()->subDay()->toDateString();
        $liaLastWeekFrom = now()->subDays(6)->toDateString();
        $liaAll14From = now()->subDays(13)->toDateString();
        $liaQuery = request()->except(['act', 'reg', 'txn']);
        $liaQuickUrl = fn ($from = null, $to = null) => route('role.financing.index', ['role' => $role] + array_filter(
            array_merge($liaQuery, ['book' => 'liability', 'date_from' => $from, 'date_to' => $to]),
            fn ($value) => $value !== null && $value !== ''
        ));
        $liaPrintUrl = route('role.financing.liability.print', ['role' => $role] + array_filter(
            array_merge($liaQuery, ['book' => 'liability']),
            fn ($value) => $value !== null && $value !== ''
        ));
    @endphp
    <form method="GET" class="fin-filter">
        <input type="hidden" name="book" value="liability">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search what it was for, or a reference…">
        <a href="{{ $liaQuickUrl($liaToday, $liaToday) }}" class="fin-btn-ghost">Today</a>
        <a href="{{ $liaQuickUrl($liaYesterday, $liaYesterday) }}" class="fin-btn-ghost">Yesterday</a>
        <a href="{{ $liaQuickUrl($liaLastWeekFrom, $liaToday) }}" class="fin-btn-ghost">Last Week</a>
        <a href="{{ $liaQuickUrl($liaAll14From, $liaToday) }}" class="fin-btn-ghost">All 14 Days</a>
        <input type="date" name="date_from" value="{{ request('date_from') }}">
        <span class="fin-sub">to</span>
        <input type="date" name="date_to" value="{{ request('date_to') }}">
        <button type="submit" class="fin-btn"><i class="fas fa-magnifying-glass"></i> Search</button>
        <a href="{{ $liaPrintUrl }}" target="_blank" class="fin-btn"><i class="fas fa-print"></i> Print</a>
        <a href="{{ route('role.financing.index', ['role' => $role, 'book' => 'liability']) }}" class="fin-btn-ghost"><i class="fas fa-rotate-left"></i> Reset</a>
    </form>
</div>

{{-- ── 1 · Active Liability ───────────────────────────────────────────────── --}}
<div class="fin-card" style="margin-bottom:1.15rem;" id="active">
    <div class="fin-card-head">
        <strong>Active Liability</strong>
        <span class="fin-sub" style="margin:0;">
            still owed between companies · {{ number_format($liabilities->total()) }} in all
        </span>
    </div>

    <div style="overflow-x:auto;">
        <table class="fin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>What it was for</th>
                    <th>Owes</th>
                    <th>Owed to</th>
                    <th>Paid from</th>
                    <th style="text-align:right;">Amount</th>
                    <th style="text-align:right;">Settled</th>
                    <th style="text-align:right;">Outstanding</th>
                    @if($canSettleHere)<th></th>@endif
                </tr>
            </thead>
            <tbody>
            @forelse($liabilities as $l)
                <tr>
                    <td>@include('partials.date-time-cell', ['date' => $l->incurred_on, 'recordedAt' => $l->created_at])</td>
                    <td>
                        <span class="fin-strong">{{ $l->title ?: 'Expense #' . $l->expense_id }}</span>
                        @if($l->reference)<div class="fin-sub">{{ $l->reference }}</div>@endif
                    </td>
                    <td class="fin-strong">{{ $l->debtor?->short_name ?: $l->debtor?->name ?: '—' }}</td>
                    <td class="fin-strong">{{ $l->creditor?->short_name ?: $l->creditor?->name ?: '—' }}</td>
                    <td class="fin-dim">{{ $l->bank?->name ?: '—' }}</td>
                    <td style="text-align:right;" class="fin-num">৳{{ number_format((float) $l->amount, 2) }}</td>
                    <td style="text-align:right;" class="fin-num">
                        {{ (float) $l->settled_amount > 0 ? '৳' . number_format((float) $l->settled_amount, 2) : '—' }}
                    </td>
                    <td style="text-align:right;" class="fin-strong fin-num" style="color:#dc2626;">
                        ৳{{ number_format($l->outstanding, 2) }}
                    </td>
                    @if($canSettleHere)
                    <td style="text-align:right;">
                        {{-- data-* rather than a modal per row: one form, filled
                             by the button that opened it. Twenty rows would
                             otherwise mean twenty hidden forms in the DOM. --}}
                        <button type="button" class="fin-btn"
                                onclick="finOpenSettle(this)"
                                data-id="{{ $l->id }}"
                                data-title="{{ e($l->title ?: 'Expense #' . $l->expense_id) }}"
                                data-debtor="{{ e($l->debtor?->short_name ?: $l->debtor?->name) }}"
                                data-creditor="{{ e($l->creditor?->short_name ?: $l->creditor?->name) }}"
                                {{-- The two company ids decide which accounts may
                                     pay and which may receive, when settling by
                                     transfer from this screen. --}}
                                data-debtor-co="{{ $l->debtor_company_id }}"
                                data-creditor-co="{{ $l->creditor_company_id }}"
                                data-outstanding="{{ $l->outstanding }}">
                            <i class="fas fa-check"></i> Settle
                        </button>
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canSettleHere ? 9 : 8 }}" class="fin-empty">
                    <i class="fas fa-right-left"></i>
                    Nothing outstanding between companies.
                    @if($liabilityTotals['count'] > 0)
                        Everything raised has been settled — it is in the register below.
                    @else
                        A row lands here when an approved expense is paid from another company's bank account.
                    @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($liabilities->hasPages())
        <div class="fin-pager">{{ $liabilities->links() }}</div>
    @endif
</div>

{{-- ── 2 · Liability Register (Settled) ───────────────────────────────────── --}}
<div class="fin-card" style="margin-bottom:1.15rem;" id="register">
    <div class="fin-card-head">
        <strong>Liability Register (Settled)</strong>
        <span class="fin-sub" style="margin:0;">
            finished with — settled, written off or cancelled · {{ number_format($settledLiabilities->total()) }} in all
        </span>
    </div>

    <div style="overflow-x:auto;">
        <table class="fin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>What it was for</th>
                    <th>Owes</th>
                    <th>Owed to</th>
                    <th>Status</th>
                    <th style="text-align:right;">Amount</th>
                    <th style="text-align:right;">Settled</th>
                </tr>
            </thead>
            <tbody>
            @forelse($settledLiabilities as $l)
                <tr>
                    <td>@include('partials.date-time-cell', ['date' => $l->incurred_on, 'recordedAt' => $l->created_at])</td>
                    <td>
                        <span class="fin-strong">{{ $l->title ?: 'Expense #' . $l->expense_id }}</span>
                        @if($l->reference)<div class="fin-sub">{{ $l->reference }}</div>@endif
                    </td>
                    <td class="fin-strong">{{ $l->debtor?->short_name ?: $l->debtor?->name ?: '—' }}</td>
                    <td class="fin-strong">{{ $l->creditor?->short_name ?: $l->creditor?->name ?: '—' }}</td>
                    <td>
                        <span class="fin-chip {{ $l->status === 'settled' ? 'fin-chip-paid' : 'fin-chip-written_off' }}">
                            {{ str_replace('_', ' ', $l->status) }}
                        </span>
                        @if($l->status === 'cancelled')
                            <div class="fin-sub">its expense was reversed</div>
                        @endif
                    </td>
                    <td style="text-align:right;" class="fin-num">৳{{ number_format((float) $l->amount, 2) }}</td>
                    <td style="text-align:right;" class="fin-strong fin-num">৳{{ number_format((float) $l->settled_amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="fin-empty">
                    <i class="fas fa-box-archive"></i>
                    Nothing has been settled yet.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($settledLiabilities->hasPages())
        <div class="fin-pager">{{ $settledLiabilities->links() }}</div>
    @endif
</div>

{{-- ── 3 · Liability Transaction ──────────────────────────────────────────── --}}
<div class="fin-card" style="margin-bottom:1.15rem;" id="transactions">
    <div class="fin-card-head">
        <strong>Liability Transaction</strong>
        <span class="fin-sub" style="margin:0;">
            every debt raised and every payment between companies · {{ number_format($liabilityTxns->total()) }} in all
        </span>
    </div>

    <div style="overflow-x:auto;">
        <table class="fin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Between</th>
                    <th>Type</th>
                    <th>What it was for</th>
                    <th>Transfer</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Memo</th>
                </tr>
            </thead>
            <tbody>
            @forelse($liabilityTxns as $t)
                <tr>
                    <td>@include('partials.date-time-cell', ['date' => $t->date, 'recordedAt' => $t->created_at])</td>
                    <td>
                        <span class="fin-strong">{{ $t->liability?->debtor?->short_name ?: $t->liability?->debtor?->name ?: '—' }}</span>
                        <span class="fin-dim"> → </span>
                        <span class="fin-strong">{{ $t->liability?->creditor?->short_name ?: $t->liability?->creditor?->name ?: '—' }}</span>
                    </td>
                    <td>
                        {{-- A raise moves the balance the opposite way from a
                             settlement, so it must never be read as one at a
                             glance. Same reason the loan book colours a taking
                             apart from a payment. --}}
                        <span class="fin-chip {{ $t->type === 'raise' ? 'fin-chip-due' : 'fin-chip-paid' }}">
                            {{ $t->type === 'raise' ? 'raised' : str_replace('_', ' ', $t->type) }}
                        </span>
                    </td>
                    <td class="fin-dim">{{ $t->liability?->title ?: '—' }}</td>
                    <td>
                        @if($t->bankTransfer)
                            <span class="fin-sub" style="color:var(--fin-pos,#16a34a);">
                                <i class="fas fa-link"></i>
                                {{ $t->bankTransfer->reference_no ?: '#' . $t->bankTransfer->id }}
                            </span>
                        @elseif($t->type === 'raise')
                            <span class="fin-dim">—</span>
                        @else
                            <span class="fin-sub">not linked</span>
                        @endif
                    </td>
                    <td style="text-align:right;" class="fin-strong fin-num">
                        {{ $t->type === 'raise' ? '+' : '−' }}৳{{ number_format((float) $t->amount, 2) }}
                    </td>
                    <td class="fin-dim">{{ $t->memo ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="fin-empty">
                    <i class="fas fa-receipt"></i>
                    Nothing has moved on this book yet.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($liabilityTxns->hasPages())
        <div class="fin-pager">{{ $liabilityTxns->links() }}</div>
    @endif
</div>

@if($canSettleHere)
{{-- ── Settle ─────────────────────────────────────────────────────────────
     One form for every row, filled by whichever Settle button opened it. --}}
<div id="finSettleModal" class="fin-modal" style="display:none;">
    <div class="fin-modal-box">
        <form method="POST" id="finSettleForm" action="">
            @csrf
            <div class="fin-modal-head">
                <h3>Settle liability</h3>
                <p id="finSettleWho"></p>
            </div>

            <div class="fin-modal-body">
                <div class="fin-field">
                    <label for="finSettleAmount">Amount <span style="color:#dc2626;">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="finSettleAmount" required>
                    <small class="fin-sub" id="finSettleMax"></small>
                </div>

                <div class="fin-field">
                    <label for="finSettleDate">Date <span style="color:#dc2626;">*</span></label>
                    <input type="date" name="date" id="finSettleDate" value="{{ now()->toDateString() }}" required>
                </div>

                <div class="fin-field">
                    <label for="finSettleType">Kind</label>
                    <select name="type" id="finSettleType">
                        <option value="settlement">Settlement — it was paid back</option>
                        <option value="write_off">Write off — forgiven, not paid</option>
                        <option value="adjustment">Adjustment — correcting the figure</option>
                    </select>
                </div>

                {{-- ── How the money is being settled ────────────────────────
                     Three ways, and they are genuinely different acts:
                       record  — it was paid, somewhere else, note it here
                       bank    — pay it NOW, from this screen, for real
                       link    — it was paid by a transfer already on file
                     Only 'bank' moves money, and it says so in red before you
                     press the button. --}}
                <div class="fin-field">
                    <label>How</label>
                    <div class="lia-how">
                        <label class="lia-how-opt">
                            <input type="radio" name="settle_via" value="record" checked
                                   onchange="finSettleVia(this.value)">
                            <span><strong>Just record it</strong><small>Money moved elsewhere, or nothing moved</small></span>
                        </label>
                        <label class="lia-how-opt" id="finSettleBankOpt">
                            <input type="radio" name="settle_via" value="bank"
                                   onchange="finSettleVia(this.value)">
                            {{-- The sub-label is rewritten by finOpenSettle when
                                 one of the two companies has no account on file.
                                 A greyed-out control whose reason lives in a
                                 title tooltip is a dead end: five of the eight
                                 companies have no bank account, so this is the
                                 common case, not the edge one. --}}
                            <span><strong>Pay now by bank transfer</strong><small id="finSettleBankWhy">Send it from here — moves real money</small></span>
                        </label>
                    </div>
                </div>

                {{-- Shown for "Pay now". The two pickers are filled by JS from
                     the debtor's and the creditor's own accounts, because a
                     settlement can only run one way: the company that owes
                     pays the company that is owed. --}}
                <div id="finSettleBankWrap" style="display:none;">
                    <div class="fin-field">
                        <label for="finSettleFrom">Paid from <span style="color:#dc2626;">*</span>
                            <span class="fin-sub" id="finSettleFromWho"></span>
                        </label>
                        <select name="from_bank_id" id="finSettleFrom">
                            <option value="">— choose the paying account —</option>
                        </select>
                    </div>

                    <div class="fin-field">
                        <label for="finSettleTo">Paid into <span style="color:#dc2626;">*</span>
                            <span class="fin-sub" id="finSettleToWho"></span>
                        </label>
                        <select name="to_bank_id" id="finSettleTo">
                            <option value="">— choose the receiving account —</option>
                        </select>
                    </div>

                    <div class="fin-note" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;margin:0 0 .9rem;">
                        <i class="fas fa-triangle-exclamation" style="color:#dc2626;"></i>
                        <span>
                            This raises a <strong>real bank transfer</strong> and posts it, exactly as Manage Banks
                            would — both balances move and the ledger is written. The liability row itself still
                            posts nothing.
                        </span>
                    </div>
                </div>

                {{-- Shown for "Just record it": point at a transfer already on
                     file, if there is one. Hidden while paying from here,
                     because the transfer this creates IS the link. --}}
                <div class="fin-field" id="finSettleLinkWrap">
                    <label for="finSettleTransfer">Bank transfer <span class="fin-sub">(optional)</span></label>
                    <select name="bank_transfer_id" id="finSettleTransfer">
                        <option value="">— not linked —</option>
                        @foreach($bankTransfers ?? [] as $tr)
                            <option value="{{ $tr->id }}">
                                {{ $tr->reference_no ?: '#' . $tr->id }} ·
                                ৳{{ number_format((float) $tr->amount, 2) }} ·
                                {{ $tr->payment_date?->format('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                    <small class="fin-sub">
                        Link the transfer that moved the money, if one was recorded in Manage Banks.
                        Nothing here posts to the ledger — the transfer already did.
                    </small>
                </div>

                <div class="fin-field">
                    <label for="finSettleMemo">Note</label>
                    <input type="text" name="memo" id="finSettleMemo" maxlength="255" placeholder="optional">
                </div>
            </div>

            <div class="fin-modal-foot">
                <button type="button" class="fin-btn-ghost" onclick="finCloseSettle()">Cancel</button>
                <button type="submit" class="fin-btn-primary"><i class="fas fa-check"></i> Record</button>
            </div>
        </form>
    </div>
</div>

<script>
    /* The explainer opens and shuts, and the choice is remembered per browser:
       whether it is worth reading is a property of the person, not the visit.
       Kept in localStorage rather than a preference column — it is display
       state, not data about the business. Falls back silently to shut. */
    (function () {
        var note   = document.getElementById('liaNote');
        var toggle = document.getElementById('liaNoteToggle');
        if (!note || !toggle) return;

        toggle.addEventListener('click', function () {
            var open = note.classList.toggle('is-collapsed') === false;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            try { localStorage.setItem('fin.liability.note', open ? 'open' : 'shut'); } catch (e) {}
        });
    })();

    /* Every active account, grouped by the company that owns it, so the settle
       form can offer the debtor's accounts to pay from and the creditor's to
       pay into without a round trip. Same list the rest of this desk uses. */
    const LIA_BANKS = @json(
        ($banks ?? collect())->groupBy('company_id')->map(
            fn ($rows) => $rows->map(fn ($b) => [
                'id'    => $b->id,
                'label' => $b->name . ($b->account_number ? ' (' . $b->account_number . ')' : ''),
            ])->values()
        )
    );

    /* Fill one account picker from one company's accounts. Says so plainly when
       a company has none on file, rather than showing an empty box the user is
       left to interpret. */
    function finFillBanks(selectId, companyId, placeholder) {
        var sel = document.getElementById(selectId);
        if (!sel) return 0;

        var rows = LIA_BANKS[String(companyId)] || [];
        sel.innerHTML = '';

        var first = document.createElement('option');
        first.value = '';
        first.textContent = rows.length ? placeholder : 'No account on file for this company';
        sel.appendChild(first);

        rows.forEach(function (b) {
            var o = document.createElement('option');
            o.value = b.id;
            o.textContent = b.label;
            sel.appendChild(o);
        });

        return rows.length;
    }

    /* Toggle between recording a settlement and actually sending one. The two
       account pickers are only `required` while they are visible — a hidden
       required field blocks submit with a message the user cannot see. */
    function finSettleVia(mode) {
        var payingNow = mode === 'bank';

        document.getElementById('finSettleBankWrap').style.display = payingNow ? '' : 'none';
        document.getElementById('finSettleLinkWrap').style.display = payingNow ? 'none' : '';

        document.getElementById('finSettleFrom').required = payingNow;
        document.getElementById('finSettleTo').required   = payingNow;

        // A write-off is money forgiven, not money sent, so the two cannot be
        // combined. The server refuses it too; this stops it being offered.
        var kind = document.getElementById('finSettleType');
        if (payingNow) {
            kind.value = 'settlement';
            kind.disabled = true;
        } else {
            kind.disabled = false;
        }
    }

    /* Scoped to this book's own ids. Nothing here touches any element outside
       the settle modal, so it cannot reach another desk on a shared page. */
    function finOpenSettle(btn) {
        var id  = btn.getAttribute('data-id');
        var out = parseFloat(btn.getAttribute('data-outstanding') || '0');

        var form = document.getElementById('finSettleForm');
        form.action = "{{ route('role.financing.liability.settle', ['role' => $role, 'liability' => '__ID__']) }}"
            .replace('__ID__', id);

        document.getElementById('finSettleWho').textContent =
            (btn.getAttribute('data-debtor') || '—') + ' owes ' +
            (btn.getAttribute('data-creditor') || '—') + ' for ' +
            (btn.getAttribute('data-title') || 'this expense') + '.';

        var amount = document.getElementById('finSettleAmount');
        // Defaulted to the whole outstanding balance and capped there: settling
        // in full is the common case, and the server refuses more anyway.
        amount.value = out.toFixed(2);
        amount.max   = out.toFixed(2);

        document.getElementById('finSettleMax').textContent =
            '৳' + out.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' outstanding';

        // ── The pay-now half ────────────────────────────────────────────────
        // Direction is fixed by the debt itself: the company that owes pays the
        // company that is owed. Offering it the other way round would let
        // someone settle a debt by taking money from the creditor.
        var debtorCo   = btn.getAttribute('data-debtor-co');
        var creditorCo = btn.getAttribute('data-creditor-co');
        var debtor     = btn.getAttribute('data-debtor') || 'the debtor';
        var creditor   = btn.getAttribute('data-creditor') || 'the creditor';

        var fromCount = finFillBanks('finSettleFrom', debtorCo, '— choose the paying account —');
        var toCount   = finFillBanks('finSettleTo', creditorCo, '— choose the receiving account —');

        document.getElementById('finSettleFromWho').textContent = '· ' + debtor + ', who owes';
        document.getElementById('finSettleToWho').textContent   = '· ' + creditor + ', who is owed';

        // Reopening always starts on "just record it", so the previous row's
        // choice cannot carry over into a screen that moves real money.
        var recordRadio = document.querySelector('input[name="settle_via"][value="record"]');
        var bankRadio   = document.querySelector('input[name="settle_via"][value="bank"]');

        if (recordRadio) { recordRadio.checked = true; }

        // You cannot send money from an account that does not exist. Five of the
        // eight companies have no bank account on file, so this is ordinary, not
        // exceptional — the card has to say WHICH company is missing one and
        // what to do about it, not just go grey.
        if (bankRadio) {
            var possible = fromCount > 0 && toCount > 0;
            var why = document.getElementById('finSettleBankWhy');
            var card = document.getElementById('finSettleBankOpt');

            bankRadio.disabled = !possible;
            card.classList.toggle('is-blocked', !possible);

            if (possible) {
                why.textContent = 'Send it from here — moves real money';
                card.removeAttribute('title');
            } else {
                // Name the company that is actually missing an account. Saying
                // "one of these companies" leaves the reader to work out which.
                var missing = fromCount === 0 ? debtor : creditor;
                var role    = fromCount === 0 ? 'no account to pay from' : 'no account to receive it';

                why.textContent = missing + ' has ' + role + ' — add one in Manage Banks, or record the settlement instead.';
                card.title = missing + ' has no bank account on file.';
            }
        }

        finSettleVia('record');

        document.getElementById('finSettleModal').style.display = 'flex';
    }

    function finCloseSettle() {
        document.getElementById('finSettleModal').style.display = 'none';
    }
</script>
@endif

@endif
