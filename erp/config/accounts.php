<?php

/*
|--------------------------------------------------------------------------
| Posting accounts — by CODE, resolved at runtime
|--------------------------------------------------------------------------
|
| Every key here names a chart-of-accounts account by its code. Nothing
| validates these at boot: a code that does not exist fails only when the one
| transaction that needs it runs, and each call site fails differently — some
| throw mid-transaction, some post to null, some fall back silently.
|
| So: after ANY chart-of-accounts edit, run
|
|     php artisan accounts:check
|
| It verifies every code below exists, is active, is the right type, belongs to
| no single company, and is a postable leaf. That is the proof step — a renumber
| that misses this file takes cash postings down across the whole ERP without a
| single error until someone tries to spend money.
|
| Rewritten 2026-08-12 for the renumbered chart: cash moved from 11xx to 10xx and
| the 11xx range became individual bank accounts, which left `office_cash` (1113)
| pointing at nothing and `petty_cash_float` (1165) pointing at Islami Bank.
|
| ── The one rule for anything below ──
| A shared posting account must have company_id = NULL. Reports filter ACCOUNTS
| by company but JOURNAL ITEMS by entry, so a company-owned account posted to by
| another company shows its debit and hides its credit, and that company's trial
| balance silently stops balancing. Codes marked "co N" below are genuinely
| single-company (only Travels sells air tickets) and are safe for that reason
| alone.
|
*/

return [

    // ── ASSETS ───────────────────────────────────────────
    'accounts_receivable'   => '1311',  // Customer Receivable - General — customers owe us
    'inventory'             => '1400',  // Inventory — control account, stock sits in its children
    'prepaid_expense'       => '1470',  // Prepaid Expenses — control account
    // Staff loans. 1457 is the leaf under 1455 Advances & Loans; 1455 is a group.
    'loan_receivable'       => '1457',  // Employee Loan — money staff owe us
    // What the company owes the director because he paid a company bill out of his
    // own pocket. An asset only in the sense that it is a claim to be settled: it
    // sits debit while the company is in his debt and clears when he is reimbursed.
    //
    // NOT the same as 2520 Party Loan (money he lent under an agreement, which
    // carries interest) and NOT 3210 Drawings (money he took out for himself).
    // Mixing the three is how a reimbursement ends up reducing profit.
    'director_current_account' => '1351',  // Director's Current Account — SHARED (company_id NULL)
    'cash'                  => '1013',  // unused by any call site; kept aligned with office_cash
    'office_cash'           => '1013',  // Office Cash — the drawer. SHARED (company_id NULL) on purpose
    // The small pot day-to-day spending actually comes out of.
    //
    // WHY THIS IS NOT office_cash — 1013 holds the whole group's cash. Paying for
    // tea out of it works in double-entry terms but is wrong as control: there is
    // no ceiling on what a small expense can reach for, and the drawer everyone
    // draws from is the group's entire float. So a second, deliberately small pot
    // sits in front of it. It is topped up from 1013 by an ordinary journal
    // (Dr this / Cr 1013) whenever it runs low, and every cash expense and every
    // petty cash issue comes out of THIS account instead.
    //
    // ONE POT FOR THE WHOLE GROUP, on purpose. It must be SHARED (company_id
    // NULL) because every company posts to it — a company-owned account posted to
    // by another company shows its debit and hides its credit, and that company's
    // trial balance silently stops balancing. Which company a payment belongs to
    // is answered by journal_entries.company_id, exactly as it already is for 1013.
    //
    // Point this at whichever account you actually use; 1011 already exists,
    // is shared, sits under 1010 Cash In Hand and had no postings when this was
    // introduced. If the account named here is missing or inactive,
    // PettyCashService::cashPotAccount() falls back to office_cash, so a server
    // that has the code but not the account keeps working exactly as before.
    'petty_cash_pool'       => '1011',  // Petty Cash - Head Office — SHARED (company_id NULL) required
    // Money handed to a custodian and not yet spent — still ours, just in someone's
    // pocket rather than the drawer. Who is holding what is told apart by
    // party_id on the journal item, not by one account per person. That is why
    // this must NOT be a per-location drawer account.
    'petty_cash_float'      => '1015',  // Petty Cash Float — the 1165 row renamed back, see accounts:check

    // ── LIABILITIES ───────────────────────────────────────
    'accounts_payable'      => '2111',  // Supplier Payable — we owe suppliers (purchase due)
    'salary_payable'        => '2210',  // Salaries Payable — unpaid salary
    // Money an employee spent out of their OWN pocket on the company's behalf and
    // has not been paid back for. The mirror of 1015 Petty Cash Float: that one is
    // company cash sitting in a pocket (an asset), this is a pocket the company
    // has emptied and owes (a liability).
    //
    // A leaf under 2200 Employee Payables, SHARED (company_id NULL) for the same
    // reason as every other posting account here. Who is owed what is separated by
    // party_id on the journal item, exactly as custodians are on the float.
    //
    // NOT 2113 Expense Payable — that is under 2110 Accounts Payable, the supplier
    // side, and mixing staff claims into it makes supplier ageing meaningless.
    'employee_reimbursement_payable' => '2240',  // Employee Expense Reimbursement Payable
    'loan_payable'          => '2410',  // Short Term Loan — loan taken from others
    'tax_payable'           => '2270',  // Income Tax Payable
    // ── Borrowings the company itself owes ───────────────
    // One key per facility TYPE, not per lender: a second bank term loan posts to
    // 2510 as well and is told apart by the loan row behind the entry, exactly as
    // custodians are told apart on the petty cash float.
    //
    // These carry a liability on the balance sheet, which is what earns the right
    // to book interest to 8520. A loan in the OWNER's personal name has no entry
    // here and no interest — its instalments go to 3210 in full. See the
    // financing desk's posting rules.
    'bank_loan_long_term'   => '2510',  // Bank Loan - Long Term      — term loan, company's name
    'party_loan_long_term'  => '2520',  // Party Loan - Long Term     — director/third party lent to us
    'vehicle_loan'          => '2560',  // Vehicle Loan - IDLC Finance
    'credit_card_payable'   => '2440',  // Credit Card Payable - City Bank ••4417
    // Tax deducted from someone else's money and held until deposited with the NBR
    // — interest paid to a director is the case that matters here. Government money
    // being held, never the company's expense, which is why it is NOT 2270.
    'withholding_tax_payable' => '2280',  // Withholding Tax (TDS/VDS) Payable

    // ── EQUITY ───────────────────────────────────────────
    'owners_equity'          => '3110',  // Owner Investment
    // Money the owner took out for himself. Reduces his equity; it is NOT an
    // expense and must never reduce profit.
    //
    // ONE account for one owner across all his companies, SHARED (company_id NULL)
    // because every one of them posts to it — which company a withdrawal belongs to
    // is answered by journal_entries.company_id, same convention as office_cash.
    // A second owner gets 3211 under 3200, not a company-owned copy of this one.
    //
    // 3200 Owner Drawings is now a HEADER (this is its child) — do not post to it.
    'owner_drawings'         => '3210',  // Drawings - Mohsin
    'retained_earnings'      => '3310',  // Retained Earnings (was 3120, which is Additional Paid-in Capital)
    'opening_balance_equity' => '3400',  // Opening Balance Equity — contra for opening balance entries

    // ── INCOME ───────────────────────────────────────────
    'sales_revenue'         => '4610',  // Product Sales
    'ticket_sales_revenue'  => '4110',  // Air Ticket Sales           (co 2 — only Travels sells these)
    'service_charge_income' => '4160',  // Travel Commission Income   (co 2) — CONFIRM: refund/reissue service charge
    'flight_sales_revenue'  => '4150',  // Contract Flight & File Revenue (co 2)
    'file_sales_revenue'    => '4150',  // same account — the chart merged flight and file revenue
    'visa_sales_revenue'    => '4120',  // Visa Service Income        (co 2)
    // 8110 Interest Income is typed `expense` in the chart, so interest earned
    // currently reads as a cost on the P&L. Fix the TYPE in the chart, not this line.
    'loan_interest_income'  => '8110',  // Interest Income — SEE NOTE: type must be corrected to `income`

    // ── EXPENSES ─────────────────────────────────────────
    'cost_of_goods_sold'    => '5610',  // Purchase — COGS on sale
    'purchase_expense'      => '5610',  // Purchase — direct purchase cost
    'ticket_purchase_cost'  => '5110',  // Air Ticket Purchase Cost       (co 2)
    'visa_processing_cost'  => '5120',  // Visa Processing Cost           (co 2)
    'flight_purchase_cost'  => '5150',  // Contract Flight & File Cost    (co 2)
    'contract_file_cost'    => '5150',  // same account — merged in the chart
    'salary_expense'        => '6110',  // Salary Expense (was 6101, which never existed)
    // The fallback when an expense category names no account. A leaf under
    // 7000 rather than the 6000 header, so it cannot be double counted by a
    // tree-walking report and shows as its own line.
    'general_expense'       => '7400',  // Miscellaneous Expenses
    'loan_interest_expense' => '8520',  // Interest Expense — interest on loan taken
    // Arrangement, processing and early-closure charges. A separate expense, never
    // part of the principal: a ৳1,000,000 loan that credits ৳985,000 after a
    // ৳15,000 fee still owes ৳1,000,000, so the fee is expensed on day one rather
    // than netted off the liability.
    'loan_processing_fee'   => '8530',  // Loan Processing Fee

];
