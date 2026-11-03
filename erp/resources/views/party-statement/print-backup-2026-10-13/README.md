# Party Statement print layouts — backup of 2026-10-13

Exact copies of every Party Statement print / PDF template as they were
before the layout redesign (owner, 2026-10-13: "first keep a backup of
current format"). Nothing loads these files; they are here to compare
against or to restore.

| File | Used for |
|---|---|
| print-statement | Party ledger → Print / PDF (the statement of account) |
| print-feed | Dashboard feed print |
| print-company | Company Ledger print |
| print-parties | Party list print |
| pdf-statement | Statement PDF emailed / shared to a party |
| pdf-ledger-statement | Ledger statement PDF |
| pdf-invoice | Party invoice PDF |

To restore one: copy it back over `resources/views/party-statement/<file>.blade.php`
and run `php artisan view:clear`.

## 2026-10-14 — design 19 is live

Only the statement of account's ledger TABLE changed (header, summary strip,
footer untouched). The old table is `partials/statement-table/current.blade.php`
and can still be printed with `&design=current` on the print link; the new one
is `partials/statement-table/v19.blade.php`, its row facts come from
`app/Support/StatementRows.php`. Same day: `pdf-ledger-statement` (the emailed /
WhatsApp-link PDF) got the same table, drawn for dompdf in
`partials/statement-table/v19-email.blade.php`. The other templates are unchanged.
