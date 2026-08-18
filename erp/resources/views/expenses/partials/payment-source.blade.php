{{--
    Where the money came from — asked as one question, with two answers.

    The old form asked "Paid With" (a five-value dropdown) and then, separately,
    "Cash Source". But the real question is which pot the money left, and the
    payment method only matters once you have said "a bank". So the pot comes
    first, as two cards, and the method lives inside the bank branch where it
    means something.

    Both branches keep the element ids the old layout used
    ({prefix}_payment_mode, _bank_wrap, _bank_id, _float_wrap,
    _petty_cash_float_id, _cash_hint, _float_hint) because partials/payment-js
    drives them and the edit modal re-syncs through it.

    Shared by the create and edit modals so the two cannot drift.
--}}
<div class="exp-source" data-prefix="{{ $prefix }}">

    {{-- payment_mode is still what the database stores; the cards set it. --}}
    <select id="{{ $prefix }}_payment_mode" name="payment_mode" class="hidden" tabindex="-1" aria-hidden="true">
        <option value="cash">Cash</option>
        <option value="mobile_banking">Mobile Banking</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="digital_wallet">Digital Wallet</option>
        <option value="other">Other</option>
    </select>

    {{-- Only the cards this person can actually use. Offering one the server
         refuses teaches nothing except that the form lies.

         The drawer — the blank "the pot" option below — belongs to whoever keeps
         the company's cash. For everyone else it is a hole: booking against it
         says money left the office for someone who was never handed any, leaving
         the pot short on paper and full in fact. So they get their own float and
         their own pocket, both of which say something true.

         Which means someone with no float and no drawer has nothing to put on the
         Petty Cash card, and it is not drawn at all. For them there is one card,
         and it is the right one. --}}
    @php
        $mayUseBank = auth()->user()->can('pay expense from bank');
        $mayUsePot  = auth()->user()->can('view all expense') || auth()->user()->can('view company expense');
        $showCash   = $mayUsePot || $pettyCashFloats->isNotEmpty();
        $cardCount  = 1 + (int) $showCash + (int) $mayUseBank;
    @endphp

    {{-- Whose money it was. Empty means the company's; a user id means theirs,
         and the posting credits what we owe them instead of moving our cash. --}}
    <input type="hidden" id="{{ $prefix }}_reimburse_to_user_id" name="reimburse_to_user_id"
           value="{{ $showCash ? '' : auth()->id() }}">

    <div class="exp-source-cards" style="grid-template-columns:repeat({{ $cardCount }},1fr)">
        @if ($showCash)
        <button type="button" class="exp-source-card is-active" id="{{ $prefix }}_source_cash" data-source="cash">
            <span class="exp-source-title"><i class="fas fa-wallet"></i> Petty Cash</span>
            <span class="exp-source-sub">{{ $mayUsePot ? 'Cash someone is holding, or the drawer' : 'The float you are holding' }}</span>
        </button>
        @endif

        <button type="button" class="exp-source-card {{ $showCash ? '' : 'is-active' }}" id="{{ $prefix }}_source_own" data-source="own">
            <span class="exp-source-title"><i class="fas fa-hand-holding-dollar"></i> My Own Pocket</span>
            <span class="exp-source-sub">You paid — the company owes you back</span>
        </button>

        @if ($mayUseBank)
        <button type="button" class="exp-source-card" id="{{ $prefix }}_source_bank" data-source="bank">
            <span class="exp-source-title"><i class="fas fa-building-columns"></i> Bank Account</span>
            <span class="exp-source-sub">Large expense / site payment</span>
        </button>
        @endif
    </div>

    {{-- ── Own pocket branch ── --}}
    <div id="{{ $prefix }}_own_branch" class="exp-source-branch {{ $showCash ? 'hidden' : '' }}">
        <div class="exp-fund" style="border-color:#ddd6fe;background:#f5f3ff">
            <div class="exp-fund-head">
                <span class="exp-fund-label" style="color:#6d28d9">Paid by {{ auth()->user()->name }}</span>
            </div>
            <div class="exp-fund-note">
                No company cash moves for this one. Once it is approved the amount is
                recorded as <strong>owed to you</strong>, and it stays owed until someone
                pays it back — it does not come out of any float or the office drawer.
            </div>
        </div>
    </div>

    {{-- ── Cash branch ── --}}
    <div id="{{ $prefix }}_cash_branch" class="exp-source-branch">
        <div id="{{ $prefix }}_float_wrap">
            <label for="{{ $prefix }}_petty_cash_float_id" class="block text-sm font-semibold text-gray-700 mb-1.5">
                Cash source
            </label>
            {{-- Picking a custodian credits THEIR float, so their balance falls as
                 receipts come in, instead of the drawer being emptied a second time
                 for money that left it days ago. --}}
            <select id="{{ $prefix }}_petty_cash_float_id" name="petty_cash_float_id"
                class="form-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm" style="width:100%">
                {{-- The blank option is "no custodian" — the pot itself. It is
                     named from the chart rather than hardcoded, because which
                     account that is depends on config/accounts.php: the small
                     petty cash pool when one exists, Office Cash when it does
                     not. Hardcoding "Office cash" here would have the form claim
                     one account while the posting credited another.

                     Offered only to the people who keep that cash; for everyone
                     else store() refuses it outright, so showing it would be an
                     invitation to a dead end. --}}
                @if ($mayUsePot)
                <option value="">{{ $cashPotName ?? 'Office Cash' }} — the pot</option>
                @endif
                @foreach ($pettyCashFloats as $float)
                    <option value="{{ $float->id }}"
                            data-company="{{ $float->company_id }}"
                            data-company-name="{{ $float->company->short_name ?? $float->company->name ?? '' }}"
                            data-name="{{ $float->custodian->name ?? 'Custodian' }}"
                            data-held="{{ number_format((float) ($float->held ?? 0), 2, '.', '') }}"
                            data-today="{{ number_format((float) ($float->spent_today ?? 0), 2, '.', '') }}">
                        {{ $float->custodian->name ?? 'Custodian' }} — holding ৳{{ number_format((float) ($float->held ?? 0), 2) }}
                    </option>
                @endforeach
            </select>
            <p class="text-[11px] text-gray-500 mt-1" id="{{ $prefix }}_float_hint">
                Bought from someone's float? Pick them, and their balance drops by this amount.
            </p>
        </div>

        {{-- Live panel: what is actually in that pot right now. Read from the
             ledger, so it is the same figure the Petty Cash page shows. --}}
        <div class="exp-fund" id="{{ $prefix }}_fund_panel">
            <div class="exp-fund-head">
                <span class="exp-fund-label" id="{{ $prefix }}_fund_label">{{ $cashPotName ?? 'Office Cash' }} — the pot</span>
                {{-- A float belongs to exactly one company, so picking one answers
                     the Company question at the top of the form. Echoed here so the
                     answer is visible without scrolling back up to see it change. --}}
                <span class="exp-fund-company hidden" id="{{ $prefix }}_fund_company"></span>
            </div>
            <div class="exp-fund-note" id="{{ $prefix }}_fund_note">
                This entry posts against <strong>{{ $cashPotName ?? 'Office Cash' }}</strong>. No bank needed.
            </div>
            <div class="exp-fund-figure" id="{{ $prefix }}_fund_figure" hidden>
                <span class="exp-fund-amount" id="{{ $prefix }}_fund_amount">৳ 0.00</span>
                <span class="exp-fund-cap" id="{{ $prefix }}_fund_cap"></span>
            </div>
        </div>

        {{-- ── Today's cash ceiling for the company paying ──
             Deliberately BELOW the pot panel and visually quieter than it, because
             the two say different things and the order matters: the panel above is
             money that exists, this is an allowance that does not. Someone who
             reads "৳150 left" as cash in hand will hand over ৳150 that is not
             there, so this block never uses the word balance and never shows a
             figure without the word limit or allowance beside it.

             Hidden until a company is known, and hidden entirely for a company
             with no fund set — an empty meter reads as a limit of zero. Shown only
             on the cash branch; a bank payment is not governed by it. --}}
        <div class="exp-dfund hidden" id="{{ $prefix }}_dfund">
            <div class="exp-dfund-head">
                <span class="exp-dfund-label" id="{{ $prefix }}_dfund_label">Daily cost fund</span>
                <span class="exp-dfund-fig" id="{{ $prefix }}_dfund_fig"></span>
            </div>
            <div class="exp-dfund-track">
                <div class="exp-dfund-fill" id="{{ $prefix }}_dfund_fill"></div>
            </div>
            <div class="exp-dfund-note" id="{{ $prefix }}_dfund_note"></div>
        </div>

        {{-- Kept for payment-js, which still toggles it; the panel above says the
             same thing with more room. --}}
        <p id="{{ $prefix }}_cash_hint" class="hidden"></p>
    </div>

    {{-- ── Bank branch ──
         Rendered even when the Bank card above is not, and deliberately so:
         partials/payment-js bails out at `if (!mode || !wrap || !bank) return;`
         if {{ $prefix }}_bank_id is missing, which would take the CASH branch's
         float panel and daily-fund meter down with it. With no card to reveal it
         the branch is unreachable, and $banks is empty for these users anyway —
         so it costs a hidden div and saves the rest of the form. --}}
    <div id="{{ $prefix }}_bank_branch" class="exp-source-branch hidden">
        {{-- Method first, Bank second: the method decides which accounts are even
             offered (Mobile Banking cannot spend from a current account), so asking
             for the bank first put the answer before the question that narrows it.
             Same reason Department now precedes Company upstairs. --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="{{ $prefix }}_method" class="block text-sm font-semibold text-gray-700 mb-1.5">Payment Method</label>
                <select id="{{ $prefix }}_method"
                    class="form-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm" style="width:100%">
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="mobile_banking">Mobile Banking</option>
                    <option value="digital_wallet">Digital Wallet</option>
                    <option value="other">Other</option>
                </select>
                <p class="text-[11px] text-gray-500 mt-1">
                    <i class="fas fa-circle-info text-gray-400"></i> Picks which accounts are offered next.
                </p>
            </div>
            <div id="{{ $prefix }}_bank_wrap">
                <label for="{{ $prefix }}_bank_id" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Bank <span class="text-red-500">*</span>
                </label>
                {{-- One picker, not the reference's Bank + Account pair: in this
                     system a bank row already names its chart-of-accounts account,
                     so a second select would have nothing behind it. --}}
                <select id="{{ $prefix }}_bank_id" name="bank_id"
                    class="form-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm" style="width:100%">
                    <option value="">— Select Bank —</option>
                    {{-- data-type is the account's own kind (bank / mobile_banking /
                         digital_wallet / cash). The payment method filters on it, so
                         choosing Mobile Banking cannot offer a current account. --}}
                    @foreach ($banks as $bank)
                        <option value="{{ $bank->id }}"
                                data-company="{{ $bank->company_id }}"
                                data-type="{{ $bank->type }}">{{ $bank->name }} ({{ $bank->account_number }})</option>
                    @endforeach
                </select>
                <p id="{{ $prefix }}_bank_msg" class="text-red-500 text-xs mt-1 hidden error-message">Choose the account the money left</p>
            </div>
        </div>
    </div>
</div>
