@extends('layout.app')

@section('meta-information')
    <title>Monthly Attendance Summary</title>
@endsection

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 40px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            position: absolute;
            top: 1px;
            right: 3px;
            width: 20px;
        }

        /* Example: change active page background and text */
        span [aria-current="page"] span {
            background-color: #2563eb !important;
            background: #2563eb !important;
            color: white;
            border-color: #2563eb;
        }

        /* Scoping all styles to the parent class */
        .attendance-report-container {
            padding: 25px;
            background-color: #f4f7f9;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        /* Filter Section */
        .attendance-report-container .filter-section {
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .attendance-report-container .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .attendance-report-container .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .attendance-report-container .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }

        .attendance-report-container .form-control {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        .attendance-report-container .btn-filter {
            background-color: #4f46e5;
            color: white;
            padding: 9px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .attendance-report-container .btn-filter:hover {
            background-color: #4338ca;
        }

        /* Summary Cards */
        .attendance-report-container .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .attendance-report-container .summary-card {
            padding: 20px;
            border-radius: 10px;
            color: white;
            text-align: center;
        }

        .attendance-report-container .card-total {
            background: #64748b;
        }

        .attendance-report-container .card-present {
            background: #10b981;
        }

        .attendance-report-container .card-late {
            background: #f59e0b;
        }

        .attendance-report-container .card-absent {
            background: #ef4444;
        }

        .attendance-report-container .summary-value {
            font-size: 24px;
            font-weight: 800;
            display: block;
        }

        .attendance-report-container .summary-label {
            font-size: 12px;
            text-transform: uppercase;
            opacity: 0.9;
        }

        /* Table Section */
        .attendance-report-container .report-table-wrapper {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .attendance-report-container .table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-report-container .table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 15px;
            text-align: left;
            color: #1e293b;
            font-size: 13px;
        }

        .attendance-report-container .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        /* Badge Styles */
        .attendance-report-container .badge {
            /* padding: 4px 10px; */
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* .attendance-report-container .badge-late, */
        .attendance-report-container .badge-present {
            background: #dcfce7;
            color: #15803d;
        }

        .attendance-report-container .badge-late {
            background: #fef9c3;
            color: #a16207;
        }

        .attendance-report-container .badge-absent {
            background: #fee2e2;
            color: #b91c1c;
        }

        .attendance-report-container .badge-leave {
            background: #8632d4;
            color: #e4e0e7;
        }

        .attendance-report-container .badge-early-leave {
            background: #8632d4;
            color: #e4e0e7;
        }

        /* Scoped 2026-08-22. This was a bare `.header`, and `.header` is also the
           class on the app's own top bar (layout/header.blade.php), so every page
           loading this stylesheet had 25px of report padding and a 2px dark rule
           added to the shell header — which is why the header stood taller here
           than on pages that do not load it. It only ever meant the printed
           report's heading. */
        .attendance-report-container .header {
            text-align: center;
            margin-bottom: 30px;
            padding-top: 15px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }

        .attendance-report-container .user-info {
            margin-bottom: 20px;
        }

        /* Print styling — rebuilt 2026-08-30 so one employee-month lands on a
           single A4 page and fills it. Two things were wrong before: the rules
           only flipped `visibility`, which leaves the hidden shell's full height
           reserved in the flow (a 31-row month printed as five pages, four of
           them blank), and the table was a boxed grid of hairlines that made a
           month of data read as noise. Everything below is inside this view's
           own @media print block, so no other screen is touched. */
        @media print {

            @page {
                size: A4 portrait;
                margin: 9mm 10mm;
            }

            html,
            body {
                height: auto !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Drop the app shell with `display`, not `visibility` — that is what
               was leaving the blank pages behind the report. Matched on
               structure rather than class names, so a later header or footer
               restyle cannot quietly put the shell back on the paper. */
            body>*,
            #mainContentWrapper>* {
                display: none !important;
            }

            #mainContentWrapper,
            #mainContentWrapper>main {
                display: block !important;
            }

            #mainContentWrapper {
                margin: 0 !important;
                min-height: 0 !important;
            }

            #mainContentWrapper>main {
                padding: 0 !important;
            }

            /* The heading and employee line are screen-hidden inline, so they
               need both properties forced back on here. */
            .show-print {
                display: block !important;
                visibility: visible !important;
            }

            #mainContentWrapper>main>.attendance-report-container {
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
                background: #ffffff !important;
                font-family: 'Inter', 'Segoe UI', sans-serif;
                color: #0f172a;
            }

            /* The report is a full-page-height column: heading and employee
               line take what they need, the table takes the rest, the summary is
               pinned to the foot of the sheet. `100vh` is the printed page box,
               so a 28-day February fills the paper exactly like a 31-day
               August — no fixed row height to re-tune per month. */
            .attendance-report-container .attendance-report-container {
                display: flex !important;
                flex-direction: column;
                height: 100vh;
                padding: 0 !important;
                margin: 0 !important;
                background: #ffffff !important;
            }

            .attendance-report-container .filter-section,
            .attendance-report-container .export-actions,
            .attendance-report-container .attendance-hero {
                display: none !important;
            }

            /* ── Masthead ─────────────────────────────────────────────
               Heading content and markup are as they were; this only sets
               the type and the rule beneath it. */
            .attendance-report-container .header {
                flex: 0 0 auto;
                text-align: center !important;
                margin: 0 0 9px !important;
                padding: 0 0 8px !important;
                border-bottom: 1.6px solid #0f172a !important;
            }

            .attendance-report-container .header .company-name {
                font-size: 16px;
                font-weight: 700;
                letter-spacing: 0.09em;
                text-transform: uppercase;
                line-height: 1.25;
                color: #0f172a;
            }

            .attendance-report-container .header .company-name+div {
                margin-top: 2px;
                font-size: 10px;
                letter-spacing: 0.05em;
                color: #64748b;
            }

            /* ── Employee line ────────────────────────────────────────
               Three stacked <br> lines become one band: same three facts,
               a third of the vertical cost, and it balances the masthead. */
            .attendance-report-container .user-info {
                flex: 0 0 auto;
                margin: 0 0 10px !important;
                padding: 0 1px 9px !important;
                font-size: 10px;
                line-height: 1.3;
                color: #0f172a;
            }

            .attendance-report-container .user-info br {
                display: none;
            }

            .attendance-report-container .user-info strong {
                font-weight: 600;
                font-size: 7.6px;
                letter-spacing: 0.07em;
                text-transform: uppercase;
                color: #94a3b8;
                margin-right: 3px;
            }

            .attendance-report-container .user-info strong~strong {
                margin-left: 18px;
            }

            /* ── Table ────────────────────────────────────────────────
               Fixed layout, because the old auto layout let the Notes column
               re-apportion everything and broke `01-Aug 2026` over three
               lines. Horizontal rules only: the full grid of hairlines was
               most of the visual noise. */
            .attendance-report-container .report-table-wrapper {
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                min-height: 0;
                box-shadow: none !important;
                border-radius: 0 !important;
                overflow: visible !important;
                background: transparent !important;
            }

            .attendance-report-container .table {
                flex: 1 1 auto;
                width: 100% !important;
                height: 100%;
                table-layout: fixed !important;
                border: 0 !important;
                border-top: 1.2px solid #0f172a !important;
                border-bottom: 1.2px solid #0f172a !important;
            }

            .attendance-report-container .table thead {
                display: table-header-group;
            }

            .attendance-report-container .table th {
                background: transparent !important;
                border: 0 !important;
                border-bottom: 0.9px solid #0f172a !important;
                padding: 5px 7px 5px 0 !important;
                font-size: 7.4px !important;
                font-weight: 700;
                line-height: 1.2;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                text-align: left !important;
                color: #64748b;
                vertical-align: bottom;
            }

            .attendance-report-container .table td {
                border: 0 !important;
                border-bottom: 0.5px solid #eef2f7 !important;
                padding: 0 7px 0 0 !important;
                font-size: 9.4px !important;
                line-height: 1.35;
                color: #1e293b;
                vertical-align: middle !important;
                overflow-wrap: break-word;
            }

            .attendance-report-container .table tbody tr:last-child td {
                border-bottom: 0 !important;
            }

            .attendance-report-container .table tr {
                page-break-inside: avoid;
            }

            /* Action holds a link button — nothing to click on paper. */
            .attendance-report-container .table th:nth-child(9),
            .attendance-report-container .table td:nth-child(9) {
                display: none !important;
            }

            .attendance-report-container .table th:nth-child(1) { width: 11%; }
            .attendance-report-container .table th:nth-child(2) { width: 10%; }
            .attendance-report-container .table th:nth-child(3) { width: 10%; }
            .attendance-report-container .table th:nth-child(4) { width: 10.5%; }
            .attendance-report-container .table th:nth-child(5) { width: 10.5%; }
            .attendance-report-container .table th:nth-child(6) { width: 9.5%; }
            .attendance-report-container .table th:nth-child(7) { width: 11%; }
            .attendance-report-container .table th:nth-child(8) { width: 27.5%; }

            .attendance-report-container .table td:nth-child(1),
            .attendance-report-container .table td:nth-child(2),
            .attendance-report-container .table td:nth-child(3),
            .attendance-report-container .table td:nth-child(4),
            .attendance-report-container .table td:nth-child(5),
            .attendance-report-container .table td:nth-child(6) {
                white-space: nowrap;
            }

            .attendance-report-container .table td:nth-child(1) strong {
                font-weight: 600;
                color: #0f172a;
            }

            /* Shift repeats the same value down the whole column; keep it
               legible but let it recede so the eye tracks the times. */
            .attendance-report-container .table td:nth-child(3) {
                color: #94a3b8;
            }

            /* Non-working days recede as a block, so the month's shape reads
               at a glance instead of every row shouting equally. */
            .attendance-report-container .table tbody tr:has(.badge-holiday) td {
                color: #94a3b8;
            }

            /* ── Status ───────────────────────────────────────────────
               Small caps in the status colour. The filled pills only appear
               when the reader has "Background graphics" ticked, and each one
               cost a line of row height. */
            .attendance-report-container .badge {
                display: inline !important;
                background: transparent !important;
                padding: 0 !important;
                border-radius: 0 !important;
                font-size: 8px !important;
                font-weight: 700;
                letter-spacing: 0.045em;
            }

            .attendance-report-container .badge-present { color: #15803d !important; }
            .attendance-report-container .badge-late { color: #b45309 !important; }
            .attendance-report-container .badge-absent { color: #be123c !important; }
            .attendance-report-container .badge-leave,
            .attendance-report-container .badge-early-leave { color: #7e22ce !important; }
            .attendance-report-container .badge-holiday { color: #94a3b8 !important; }

            /* Notes carry an inline `color:red;font-weight:bold` from the
               markup. On screen it flags one row in a scroll; in print it was
               two thirds of the column in bold red. Overridden to a quiet
               amber so it still reads as an exception without taking over
               the page. */
            .attendance-report-container .table td:nth-child(8) {
                font-size: 8.8px !important;
                color: #64748b;
            }

            .attendance-report-container .table td:nth-child(8) small {
                font-size: inherit !important;
            }

            .attendance-report-container .table td:nth-child(8) span {
                color: #b45309 !important;
                font-weight: 600 !important;
            }

            /* ── Summary ──────────────────────────────────────────────
               Twelve wrapping list items become a fixed 6 × 2 stat block on
               the foot of the sheet. */
            .attendance-report-container .footer-summary-wrapper {
                flex: 0 0 auto;
                padding: 11px 0 0 !important;
                margin: 0 !important;
                page-break-inside: avoid;
            }

            .attendance-report-container .footer-summary-wrapper h4 {
                margin: 0 0 7px !important;
                font-size: 7.6px !important;
                font-weight: 700 !important;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: #94a3b8;
            }

            .attendance-report-container .summary-list {
                display: grid !important;
                grid-template-columns: repeat(6, 1fr);
                gap: 9px 14px !important;
                padding: 0 !important;
                margin: 0 !important;
                list-style: none !important;
                background: transparent !important;
                border: 0 !important;
                border-radius: 0 !important;
            }

            .attendance-report-container .summary-list li {
                padding-top: 5px !important;
                border-top: 0.9px solid #cbd5e1 !important;
                font-size: 7.2px !important;
                font-weight: 600;
                line-height: 1.25 !important;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                color: #94a3b8;
            }

            .attendance-report-container .summary-list li span {
                display: block;
                margin-top: 2px;
                font-size: 12.5px;
                font-weight: 700;
                letter-spacing: 0;
                text-transform: none;
                color: #0f172a;
            }

            /* Party Statement print language: branded masthead, indigo title
               band, two-column identity/meta block and a proper ledger head. */
            .attendance-report-container .header {
                border: 0 !important;
                padding: 0 !important;
                margin: 0 0 8px !important;
                text-align: left !important;
            }

            .attendance-report-container .print-brand {
                display: flex !important;
                align-items: center;
                justify-content: space-between;
                min-height: 42px;
                margin-bottom: 7px;
            }

            .attendance-report-container .print-logo {
                display: block;
                width: 125px;
                max-height: 48px;
                object-fit: contain;
                object-position: left center;
            }

            .attendance-report-container .print-logo-text {
                font-size: 20px;
                font-weight: 800;
                color: #3730a3;
                letter-spacing: -.02em;
            }

            .attendance-report-container .print-company {
                text-align: right;
                font-size: 8px;
                line-height: 1.45;
                color: #475569;
            }

            .attendance-report-container .print-company strong {
                display: block;
                font-size: 11px;
                color: #1e293b;
                letter-spacing: .02em;
            }

            .attendance-report-container .print-title-bar {
                display: block;
                padding: 6px 10px;
                border-top: 2px solid #3730a3;
                background: #f5f7ff !important;
                color: #3730a3;
                text-align: center;
                text-transform: uppercase;
                letter-spacing: .16em;
                font-size: 8.5px;
                font-weight: 700;
            }

            .attendance-report-container .user-info {
                display: flex !important;
                justify-content: space-between;
                align-items: flex-start;
                gap: 20px;
                margin: 0 0 8px !important;
                padding: 0 !important;
            }

            .attendance-report-container .employee-block h4 {
                margin: 0 0 3px;
                color: #3730a3;
                font-size: 7.5px;
                text-transform: uppercase;
                letter-spacing: .08em;
            }

            .attendance-report-container .employee-block p {
                margin: 1px 0;
                font-size: 8.5px;
                line-height: 1.35;
            }

            .attendance-report-container .employee-block .employee-name {
                font-size: 10px;
                font-weight: 700;
                color: #1e293b;
            }

            .attendance-report-container .print-meta {
                border-collapse: collapse;
            }

            .attendance-report-container .print-meta td {
                padding: 1px 0 1px 13px;
                border: 0 !important;
                font-size: 8px !important;
                line-height: 1.35;
                white-space: nowrap;
            }

            .attendance-report-container .print-meta td:first-child {
                color: #64748b;
                font-weight: 600;
            }

            .attendance-report-container .table {
                border-top: 0 !important;
                border-bottom-color: #c7d2fe !important;
            }

            .attendance-report-container .table th {
                padding: 5px 6px !important;
                border: 0 !important;
                background: #564fc8 !important;
                color: #fff !important;
                letter-spacing: .06em;
            }

            .attendance-report-container .table td {
                padding-left: 6px !important;
                padding-right: 6px !important;
                border-bottom-color: #e2e8f0 !important;
            }

            .attendance-report-container .table tbody tr:nth-child(even) td {
                background: #f8f9ff !important;
            }

            .attendance-report-container .footer-summary-wrapper {
                padding-top: 7px !important;
            }

            .attendance-report-container .footer-summary-wrapper h4 {
                margin-bottom: 5px !important;
                color: #3730a3;
            }

            .attendance-report-container .summary-list {
                gap: 0 !important;
                grid-template-columns: repeat(6, 1fr);
                overflow: hidden;
                border: 1px solid #e2e8f0 !important;
                border-radius: 4px !important;
            }

            .attendance-report-container .summary-list li {
                min-height: 32px;
                padding: 5px 7px !important;
                border-top: 0 !important;
                border-right: 1px solid #e2e8f0 !important;
                border-bottom: 1px solid #e2e8f0 !important;
                background: #fff !important;
            }

            .attendance-report-container .summary-list li:nth-child(6n) { border-right: 0 !important; }
            .attendance-report-container .summary-list li:nth-last-child(-n+6) { border-bottom: 0 !important; }

            .attendance-report-container .summary-list li span {
                font-size: 10px;
                color: #3730a3;
            }
        }

        .attendance-report-container .btn-export {
            background-color: #10b981;
            /* Green color for Excel */
            color: white;
            padding: 9px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: background 0.2s;
            font-size: 14px;
        }

        .attendance-report-container .btn-export:hover {
            background-color: #059669;
        }
    </style>
    <style>
        .attendance-report-container .summary-list {
            list-style: none;
            padding: 15px;
            margin: 10px 0;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .attendance-report-container .summary-list li {
            font-size: 14px;
            color: #334155;
        }

        .attendance-report-container .summary-list li strong {
            color: #4f46e5;
        }

        .attendance-report-container .badge-holiday {
            background: #e0f2fe;
            color: #0369a1;
        }

        .attendance-report-container .attendance-hero {
            display:flex; align-items:center; justify-content:space-between; gap:16px;
            margin-bottom:14px; padding:17px 20px; border-radius:13px;
            background:linear-gradient(135deg,#1e3a8a,#4f46e5); color:#fff;
            box-shadow:0 8px 20px rgba(49,46,129,.16);
        }
        .attendance-hero h1 { margin:0; font-size:20px; font-weight:800; }
        .attendance-hero p { margin:3px 0 0; color:#c7d2fe; font-size:11px; }
        .attendance-hero .period-chip { padding:6px 11px; border:1px solid rgba(255,255,255,.25); border-radius:999px; background:rgba(255,255,255,.12); font-size:11px; font-weight:700; white-space:nowrap; }
        .attendance-report-container > .filter-section { padding:10px 12px; margin-bottom:14px; border:1px solid #dbe4f0; border-radius:9px; }
        .attendance-report-container > .filter-section .filter-form { grid-template-columns:minmax(220px,1.5fr) minmax(150px,1fr) minmax(120px,.8fr) auto; gap:8px; }
        .attendance-report-container > .filter-section .form-group { gap:3px; }
        .attendance-report-container > .filter-section label { font-size:10px; line-height:1.1; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.035em; }
        .attendance-report-container > .filter-section .form-control { height:34px; padding:5px 9px; border-radius:5px; font-size:12px; }
        .attendance-report-container > .filter-section .select2-container .select2-selection--single { height:34px; }
        .attendance-report-container > .filter-section .select2-selection__rendered { line-height:32px; padding-left:9px; font-size:12px; }
        .attendance-report-container > .filter-section .select2-selection__arrow { height:32px; }
        .attendance-filter-actions { display:flex; align-items:center; gap:7px; }
        .attendance-filter-actions .filter-reset, .attendance-filter-actions .filter-submit { height:34px; min-width:76px; display:inline-flex; align-items:center; justify-content:center; gap:5px; padding:0 11px; border-radius:5px; border:1px solid #cbd5e1; font-size:11px; font-weight:700; line-height:1; text-decoration:none; white-space:nowrap; }
        .attendance-filter-actions .filter-reset { background:#fff; color:#475569; }
        .attendance-filter-actions .filter-submit { border-color:#4f46e5; background:#4f46e5; color:#fff; }
        .attendance-report-container .report-table-wrapper { border:1px solid #e2e8f0; }
        .attendance-report-container .table th { padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:.025em; }
        .attendance-report-container .table td { padding:9px 12px; font-size:12px; }
        .attendance-report-container .footer-summary-wrapper { padding:14px !important; }
        .attendance-report-container .footer-summary-wrapper h4 { margin:0 0 8px !important; font-size:14px !important; }
        .attendance-report-container .summary-list { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:8px; padding:0; margin:0; border:0; background:transparent; }
        .attendance-report-container .summary-list li { padding:8px 9px; border:1px solid #e2e8f0; border-radius:7px; background:#f8fafc; font-size:9px; font-weight:700; text-transform:uppercase; color:#64748b; }
        .attendance-report-container .summary-list li span { display:block; margin-top:2px; color:#3730a3; font-size:14px; text-transform:none; }
        @media (max-width:1000px) { .attendance-report-container > .filter-section .filter-form { grid-template-columns:repeat(2,minmax(160px,1fr)); } .attendance-report-container .summary-list { grid-template-columns:repeat(3,minmax(0,1fr)); } }
        @media (max-width:640px) { .attendance-report-container { padding:12px; } .attendance-report-container .attendance-hero { align-items:flex-start; flex-direction:column; } .attendance-report-container > .filter-section .filter-form { grid-template-columns:1fr; } .attendance-filter-actions { justify-content:flex-end; } .attendance-report-container .summary-list { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    </style>
@endsection

@section('main-content')
    <div class="attendance-report-container">
        <div class="attendance-hero">
            <div>
                <h1><i class="fa-solid fa-calendar-check"></i> Monthly Attendance</h1>
                <p>Review attendance, working hours, late arrivals and leave for one employee.</p>
            </div>
            <div class="period-chip"><i class="fa-regular fa-calendar"></i> {{ date('F', mktime(0, 0, 0, $selectedMonth, 1)) }} {{ $selectedYear }}</div>
        </div>

        <div class="filter-section">
            <form id="monthlyAttendanceFilterForm" class="filter-form">
                @if('employee' != \Str::slug(auth()->user()->getRoleNames()->first()))
                <div class="form-group">
                    <label>Employee / User</label>
                    <select id="monthlyAttendanceUserFilter" name="user_id" class="form-control select2" style="width: 100%" required>
                        <option value="">Select employee</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control select2" style="width: 100%">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $selectedMonth == $m ? 'selected' : '' }}>
                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="form-group">
                    <label>Year</label>
                    <select name="year" class="form-control select2" style="width: 100%">
                        @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                            <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>
                                {{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <div class="attendance-filter-actions">
                    <a href="{{ route('role.report.monthly-attendances', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                        class="filter-reset"><i class="fa fa-rotate-left"></i> Reset</a>
                    <button type="submit" class="filter-submit"><i class="fa fa-filter"></i> Apply</button>
                </div>
            </form>
        </div>

        <div class="attendance-report-container p-0" style="padding: 0">
            @if ($userId)
                @php
                    $user = \App\Models\User::find($userId);
                    $startDate = \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
                    $printCompany = $user?->company;
                    $printLogo = $printCompany?->logo && file_exists(public_path($printCompany->logo))
                        ? asset($printCompany->logo)
                        : (file_exists(public_path('logo.png')) ? asset('logo.png') : null);
                @endphp
                <div class="header show-print" style="display: none; visibility: hidden;">
                    <div class="print-brand">
                        <div>
                            @if($printLogo)
                                <img class="print-logo" src="{{ $printLogo }}" alt="{{ $printCompany?->name }}">
                            @else
                                <div class="print-logo-text">{{ $printCompany?->name ?? config('app.name') }}</div>
                            @endif
                        </div>
                        <div class="print-company">
                            <strong>{{ $printCompany?->name ?? config('app.name') }}</strong>
                            @if($printCompany?->address){{ $printCompany->address }}<br>@endif
                            {{ $printCompany?->phone }}{{ $printCompany?->phone && $printCompany?->email ? ' | ' : '' }}{{ $printCompany?->email }}
                        </div>
                    </div>
                    <div class="print-title-bar">Monthly Attendance Statement</div>
                </div>

                <div class="user-info show-print" style="display: none; visibility: hidden;">
                    <div class="employee-block">
                        <h4>Employee Details</h4>
                        <p class="employee-name">{{ $user->name }}</p>
                        <p>{{ $user->email }}</p>
                        @if($user->phone)<p>{{ $user->phone }}</p>@endif
                    </div>
                    <table class="print-meta">
                        <tr><td>Employee ID</td><td>{{ $user->employee_id_no ?: 'EMP-' . str_pad((string) $user->id, 4, '0', STR_PAD_LEFT) }}</td></tr>
                        <tr><td>Period</td><td>{{ $startDate->format('F Y') }}</td></tr>
                        <tr><td>Generated</td><td>{{ now()->format('d M Y') }}</td></tr>
                        <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
                    </table>
                </div>
            @endif
            <div class="report-table-wrapper p-0" style="padding: 0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Shift</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Worked Hour</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $total_minutes = 0;
                        @endphp
                        @forelse($attendances as $attendance)
                            @php
                                $worked_display = '--';
                                if ($attendance->check_in && $attendance->check_out) {
                                    $start = \Carbon\Carbon::parse($attendance->check_in);
                                    $end = \Carbon\Carbon::parse($attendance->check_out);

                                    // Handle overnight shifts
                                    if ($end->lt($start)) {
                                        $end->addDay();
                                    }

                                    // Calculate total minutes for this row
                                    $diffInMinutes = $start->diffInMinutes($end);
                                    $total_minutes += $diffInMinutes;

                                    // Format row display: "8h 30m"
                                    $h = floor($diffInMinutes / 60);
                                    $m = $diffInMinutes % 60;
                                    $worked_display = "{$h}h {$m}m";
                                } elseif ($attendance->check_in && !$attendance->check_out) {
                                    $worked_display = 'Missing Out';
                                }
                            @endphp
                            <tr>
                                <td><strong>{{ \Carbon\Carbon::parse($attendance->date)->format('d-M Y') }}</strong></td>
                                <td><span>{{ \Carbon\Carbon::parse($attendance->date)->format('l') }}</span></td>
                                <td>{{ $attendance->shift?->name ?? '-' }}</td>
                                <td>{{ $attendance->check_in ? \Carbon\Carbon::parse($attendance->check_in)->format('h:i:s A') : '--:--' }}
                                </td>
                                <td>{{ $attendance->check_out ? \Carbon\Carbon::parse($attendance->check_out)->format('h:i:s A') : '--:--' }}
                                </td>
                                <td>{{ $worked_display }}</td>
                                <td>
                                    <span class="badge badge-{{ Str::slug(preg_replace('/\s+\d+\s+mins$/', '', $attendance->status)) }}">
                                        {{ ucfirst($attendance->status) }}
                                    </span>
                                </td>
                                <td><small class="text-muted">@if(!empty($attendance->out_status)) <span style="color:red;font-weight:bold">{{ $attendance->out_status }}-{{ $attendance->note ?? '-' }}</span> @else{{ $attendance->note ?? '-' }} @endif</small></td>
                                <td>
                                    @php
                                        $attendance_details = App\Models\AttendanceLog::where('user_id', $userId)->where('date', $attendance->date)->first();
                                    @endphp
                                    @if(!$attendance_details)
                                        <span class="text-muted">N/A</span>
                                    @else
                                    <a href="{{ route('role.report.attendance.details', [
                                        'role' => Str::slug(Auth::user()->getRoleNames()->first()),
                                        'attendance_id' => $userId,
                                        'date' => $attendance->date,
                                    ]) }}"
                                        class="btn btn-sm btn-primary"><i class="fa fa-area-chart"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center">Select an employee to generate report.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($attendances->isNotEmpty())
                    @php
                        $total_h = floor($total_minutes / 60);
                        $total_m = $total_minutes % 60;
                    @endphp
                    <div class="footer-summary-wrapper" style="padding: 20px;">
                        <h4 style="margin-bottom: 10px; font-size: 18px; font-weight: bolder">Total Summary</h4>
                        <ul class="summary-list">
                            <li>Total Days: <span>{{ $summary['total_days'] }}</span></li>
                            <li>Total Present: <span>{{ $summary['present'] }}</span></li>
                            <li>Total Absent: <span>{{ $summary['absent'] }}</span></li>
                            <li>Total Late Days: <span>{{ $summary['late'] }}</span></li>
                            <li>Total Late Minutes: <span>{{ $summary['late_minutes'] }} mins</span></li>
                            <li>Total Early Leave: <span>{{ $summary['early_out'] }}</span></li>
                            <li>Total Early Leave Minutes: <span>{{ $summary['early_minutes'] }} mins</span></li>
                            <li>Total Worked Hours: <span>{{ "{$total_h}H {$total_m}M" }}</span></li>
                            <li>Total Leave: <span>{{ $summary['leave'] }}</span></li>
                            <li>Total Holiday: <span>{{ $summary['holiday'] }}</span></li>
                            <li>Total Overtime Days: <span>{{ $summary['overtime_days'] }}</span></li>
                            <li>Total Overtime Min : <span>{{ $summary['overtime_minutes'] }} mins</span></li>
                        </ul>
                        <div class="export-actions" style="margin-top: 20px;">
                            <a href="{{ route('export.monthly.attendance.excel', request()->all()) }}"
                                class="btn-export" download data-no-prefetch><i class="fa-solid fa-file-excel" style="margin-right: 3px"></i>
                                Excel</a>
                            {{-- `download` + `data-no-prefetch`: the export routes answer with
                                 Content-Disposition: attachment, so this must be handled as a
                                 download and never as a navigation the shell's view-transition
                                 or speculation-rules prefetch can get hold of. --}}
                            <a href="{{ route('export.monthly.attendance.pdf', request()->all()) }}" class="btn-export"
                                download data-no-prefetch
                                style="background: #ef4444;"><i class="fa-solid fa-file-pdf" style="margin-right: 3px"></i>
                                PDF</a>
                            <a href="javascript:void(0)" onclick="printBody()" class="btn-export"
                                style="background: #44b9ef;"><i class="fa-solid fa-print" style="margin-right: 3px"></i>
                                Print</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection

@section('raw-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2();

            $('#monthlyAttendanceUserFilter').on('change.monthlyAttendanceAutoLoad', function() {
                if (!this.value) {
                    return;
                }

                const form = document.getElementById('monthlyAttendanceFilterForm');
                const applyButton = form?.querySelector('.filter-submit');

                if (applyButton) {
                    applyButton.disabled = true;
                    applyButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Loading';
                }

                if (form) {
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            });

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
        });

        function printBody() {
            window.print();
        }
    </script>
@endsection
