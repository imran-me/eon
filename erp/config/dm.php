<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Which DM document folders hold COMPANY documents
    |--------------------------------------------------------------------------
    |
    | DM files every document under a category that behaves like a folder, and
    | those folders split cleanly in two: some name one of the group's companies
    | ("Epal Travels", "Wood Art Interior"), and the rest name a person or a
    | personal bundle ("Family Documents", "FATHER BOSS DOCS", "CAR DOCUMENTS").
    |
    | Only the first kind can produce a company expense. A director's passport
    | renewal is a real date to be reminded about, but it is NOT a cost of doing
    | business — booking it as one puts a personal expense on the company's
    | books, which is a disallowed expense in an audit and, if the company paid,
    | is drawings rather than an expense.
    |
    | So the Subscriptions desk uses this list to decide which document renewals
    | can be paid FROM the company, and shows everything else as reminder-only
    | with no way to file an expense against it. The dashboard Renewal Center is
    | unaffected: it is the reminder surface and still shows every document.
    |
    | Matching is case-insensitive on the whole folder name, after trimming. Add
    | a folder here the moment DM gains a new company one — anything not listed
    | is treated as personal, which fails safe: a missing entry hides a due date
    | from the expense desk, it never books a personal cost by mistake.
    |
    */

    'company_document_categories' => [
        'Epal Group',
        'Epal Travels',
        'Epal Constructions',
        'Epal It Solutions',
        'Epal Online Shop',
        'Wood Art Interior',
    ],

];
