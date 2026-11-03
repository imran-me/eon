{{--
    The <option> list for ANY "choose a bank account" picker, app-wide.

    LABEL: "4477 · DUTCH BANGLA (IT) — EPAL IT"
    Last four digits first, because that is what people read off a cheque book or
    a statement to tell two accounts at the same bank apart — this install has
    two accounts called BRAC BANK and two more ending 0001. Then the bank, then
    the company that owns it.

    The company is repeated on every row even though the group header already
    says it: a closed <select> shows only the chosen option's own text, never its
    optgroup, so without it the field reads "BRAC BANK" with no company at the
    one moment it matters most — just after you pick it.

    GROUPING: one <optgroup> per company, but ONLY when the list actually spans
    more than one. On a screen already scoped to a single company a lone group
    box says nothing, so it renders flat instead.

    ── USED BY EVERY MONEY FORM ──────────────────────────────────────────────
    Sales, purchases, tickets, visa, contracts, payroll, loans, expenses, petty
    cash, financing and bank transfers all include this. That is the point: the
    account you are paying from must read identically wherever you are paying
    from it. It also means a change here reaches every company's screens, so it
    is written to be unable to throw —

      · $banks may be a Collection, an array, or paginated
      · a bank whose company row is missing lands in "No company", never vanishes
      · a missing/short/NULL account number simply drops the digits
      · the company relation is read defensively, so a caller that forgot to
        eager-load it still renders (at the cost of a query per row)

    Expects : $banks
    Optional: $selected      — id to mark selected (int|string|null)
              $withCurrency  — emit data-currency (the bank-transfer From picker)
              $valueAttr     — extra data-* per option, as ['attr' => 'column']
--}}
@php
    $boSelected     = $selected ?? null;
    $boWithCurrency = $withCurrency ?? false;

    $boRows = collect($banks ?? []);

    // Resolved once per row and cached on a plain array, so the label and the
    // grouping cannot disagree about which company an account belongs to.
    $boCompanyOf = function ($bank) {
        $company = null;

        try {
            $company = $bank->company;
        } catch (\Throwable $e) {
            $company = null;   // relation missing or unloadable — never fatal
        }

        return optional($company)->short_name
            ?: (optional($company)->name ?: 'GROUP / SHARED');
    };

    $boGroups   = $boRows->groupBy($boCompanyOf)->sortKeys();
    $boMultiple = $boGroups->count() > 1;
@endphp

@foreach($boGroups as $boCompanyName => $boCompanyBanks)
    @if($boMultiple)<optgroup label="{{ $boCompanyName }}">@endif

    @foreach($boCompanyBanks as $boBank)
        @php
            $boDigits = preg_replace('/\D/', '', (string) ($boBank->account_number ?? ''));
            $boLast4  = strlen($boDigits) >= 4 ? substr($boDigits, -4) : null;

            $boLabel = ($boLast4 ? $boLast4 . ' · ' : '')
                . ($boBank->name ?? 'Account #' . $boBank->id)
                . ' — ' . $boCompanyName;
        @endphp
        {{-- data-company-name and data-type are not decoration: the expense
             desk's payment-js filters this list by account KIND (a mobile
             banking payment cannot leave a current account) and names the
             paying company in its cross-company warning. Dropping either
             silently breaks that screen, so every caller gets them. --}}
        <option value="{{ $boBank->id }}"
                data-balance="{{ $boBank->balance ?? '' }}"
                data-company="{{ $boBank->company_id ?? '' }}"
                data-company-name="{{ $boCompanyName }}"
                data-type="{{ $boBank->type ?? '' }}"
                @if($boWithCurrency) data-currency="{{ $boBank->currency ?? '' }}" @endif
                @selected($boSelected !== null && $boSelected != '' && $boSelected == $boBank->id)>{{ $boLabel }}</option>
    @endforeach

    @if($boMultiple)</optgroup>@endif
@endforeach
