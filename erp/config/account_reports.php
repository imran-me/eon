<?php

/*
 * Financial statements — how the chart of accounts is read into sections.
 *
 * The chart already has a clean hierarchy (1xxx assets, 2xxx liabilities, 3xxx equity, 4xxx revenue,
 * 5xxx cost of sales, 6xxx operating, 7xxx other operating, 8xxx non-operating, 9xxx tax & control), so
 * the statements are cut by CODE RANGE here — one place to correct if the chart is ever renumbered.
 * Nothing is stored; a range that matches nothing simply shows no rows.
 *
 * Every account of the right TYPE that falls in no range still lands in a visible "Other …" line, so a
 * statement's total is always the sum of every account — it can never silently drop one.
 */

return [

    // Profit & Loss ─ [type, from, to]
    'pl' => [
        'operating_income'   => ['label' => 'Operating Income',        'type' => 'income',  'ranges' => [[4000, 4999]]],
        'cogs'               => ['label' => 'Cost of Goods Sold',      'type' => 'expense', 'ranges' => [[5000, 5999]]],
        'operating_expense'  => ['label' => 'Operating Expense',       'type' => 'expense', 'ranges' => [[6000, 7999]]],
        'nonop_income'       => ['label' => 'Non Operating Income',    'type' => 'income',  'ranges' => [[8000, 8999]]],
        'nonop_expense'      => ['label' => 'Non Operating Expense',   'type' => 'expense', 'ranges' => [[8000, 8999]]],
        'tax_control'        => ['label' => 'Tax & Control Accounts',  'type' => 'expense', 'ranges' => [[9000, 9999]]],
    ],

    // Balance sheet ─ the line groups inside each section
    'bs' => [
        'cash'        => ['label' => 'Cash and Cash Equivalents',  'section' => 'current_assets', 'ranges' => [[1010, 1099], [1100, 1299]]],
        'receivables' => ['label' => 'Accounts Receivable',        'section' => 'current_assets', 'ranges' => [[1300, 1399]]],
        'inventory'   => ['label' => 'Inventory',                  'section' => 'current_assets', 'ranges' => [[1400, 1449]]],
        'advances'    => ['label' => 'Advances, Deposits & Prepayments', 'section' => 'current_assets', 'ranges' => [[1450, 1499]]],
        'tax_assets'  => ['label' => 'Tax & Other Current Assets', 'section' => 'current_assets', 'ranges' => [[1500, 1599]]],
        'ppe'         => ['label' => 'Fixed Assets',               'section' => 'fixed_assets',   'ranges' => [[1600, 1749]]],
        'investments' => ['label' => 'Investments & Other Assets', 'section' => 'other_assets',   'ranges' => [[1750, 1899]]],

        'payables'    => ['label' => 'Accounts Payable & Other Payables', 'section' => 'current_liabilities', 'ranges' => [[2100, 2199]]],
        'employee'    => ['label' => 'Employee Payables',          'section' => 'current_liabilities', 'ranges' => [[2200, 2299]]],
        'customer_adv'=> ['label' => 'Customer Advances & Unearned Revenue', 'section' => 'current_liabilities', 'ranges' => [[2300, 2399]]],
        'short_loans' => ['label' => 'Short Term Borrowings',      'section' => 'current_liabilities', 'ranges' => [[2400, 2499]]],
        'long_loans'  => ['label' => 'Long Term Borrowings',       'section' => 'noncurrent_liabilities', 'ranges' => [[2500, 2599]]],
        'leases'      => ['label' => 'Lease & Deferred Liabilities', 'section' => 'noncurrent_liabilities', 'ranges' => [[2600, 2699]]],

        'capital'     => ['label' => "Owner's Capital",            'section' => 'equity', 'ranges' => [[3100, 3199]]],
        'drawings'    => ['label' => 'Owner Drawings',             'section' => 'equity', 'ranges' => [[3200, 3299]]],
        'reserves'    => ['label' => 'Retained Earnings & Reserves', 'section' => 'equity', 'ranges' => [[3300, 3399]]],
        'opening_eq'  => ['label' => 'Opening Balance Equity',     'section' => 'equity', 'ranges' => [[3400, 3499]]],
    ],

    // Cash accounts (what the Cash Flow statement reconciles to): petty/office cash, wallets, banks, gateways.
    'cash_ranges' => [[1010, 1099], [1100, 1299]],
    // …of which "bank" (for the snapshot's Bank Balance vs Cash Balance split)
    'bank_ranges' => [[1100, 1239]],

    // Cash flow ─ where each non-cash balance-sheet group goes. Anything not listed → operating, shown as
    // "Other (unclassified)" so the statement always reconciles to the cash movement.
    'cash_flow' => [
        'operating' => [
            ['label' => 'Accounts Receivable',                 'type' => 'asset',     'ranges' => [[1300, 1399]]],
            ['label' => 'Inventory',                           'type' => 'asset',     'ranges' => [[1400, 1449]]],
            ['label' => 'Advances, Deposits & Prepayments',    'type' => 'asset',     'ranges' => [[1450, 1499]]],
            ['label' => 'Tax & Other Current Assets',          'type' => 'asset',     'ranges' => [[1500, 1599]]],
            ['label' => 'Accumulated Depreciation (non-cash)', 'type' => 'asset',     'ranges' => [[1700, 1749]]],
            ['label' => 'Accounts Payable & Other Payables',   'type' => 'liability', 'ranges' => [[2100, 2199]]],
            ['label' => 'Employee Payables',                   'type' => 'liability', 'ranges' => [[2200, 2299]]],
            ['label' => 'Customer Advances & Unearned Revenue', 'type' => 'liability', 'ranges' => [[2300, 2399]]],
        ],
        'investing' => [
            ['label' => 'Fixed Assets',                        'type' => 'asset',     'ranges' => [[1600, 1699]]],
            ['label' => 'Investments & Other Assets',          'type' => 'asset',     'ranges' => [[1750, 1899]]],
        ],
        'financing' => [
            ['label' => 'Short Term Borrowings',               'type' => 'liability', 'ranges' => [[2400, 2499]]],
            ['label' => 'Long Term Borrowings',                'type' => 'liability', 'ranges' => [[2500, 2599]]],
            ['label' => 'Lease & Deferred Liabilities',        'type' => 'liability', 'ranges' => [[2600, 2699]]],
            ['label' => "Owner's Capital",                     'type' => 'equity',    'ranges' => [[3100, 3199]]],
            ['label' => 'Owner Drawings',                      'type' => 'equity',    'ranges' => [[3200, 3299]]],
            ['label' => 'Opening Balance Equity',              'type' => 'equity',    'ranges' => [[3400, 3499]]],
        ],
    ],

    // The financial year the balance sheet's "Current Year Earnings" starts on (1 = January). The two
    // equity lines always add up to the same cumulative profit the classic balance sheet showed.
    'fiscal_year_start_month' => 1,

    // Monthly Report — expense accounts that are "fixed" (people, rent, utilities, professional fees,
    // depreciation): they do not fall when sales do.
    'fixed_cost_ranges' => [[6100, 6399], [6900, 6999], [7100, 7199]],

    // Monthly Report — health-score thresholds. Every status the page shows names the rule that set it.
    'health' => [
        'net_margin_good'        => 10,    // % of income: at or above → Good, 0..this → Moderate, below 0 → Worse
        'collection_days_good'   => 30,    // days of income sitting in receivables
        'collection_days_ok'     => 60,
        'ap_over_good'           => 10,    // % of vendor dues over 45 days past due
        'ap_over_ok'             => 30,
        'current_ratio_good'     => 1.5,   // current assets ÷ current liabilities
        'expense_growth_tolerance' => 10,  // points by which expense growth may exceed income growth and still be Moderate
        'cash_drop_tolerance'    => 0.10,  // a fall in cash up to this share of opening cash is Moderate
    ],
];
