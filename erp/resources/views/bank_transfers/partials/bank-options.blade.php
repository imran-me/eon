{{--
    Kept as a thin forward to the app-wide partial.

    The bank-transfer screens included this by name before every other money form
    got the same treatment. Rather than update four call sites and risk one being
    missed, this now points at partials/bank-options — one definition of what a
    bank account looks like in a dropdown, everywhere.

    Safe to delete once nothing includes 'bank_transfers.partials.bank-options'.
--}}
@include('partials.bank-options', [
    'banks'        => $banks,
    'selected'     => $selected ?? null,
    'withCurrency' => $withCurrency ?? false,
])
