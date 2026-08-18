<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Voucher #{{ $expense->id }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 15mm; }

        :root {
            --title-color:     #445ee6;
            --title-bg:        #f4f6ff;
            --table-header-bg: #1b75cf;
            --text-color:      #2d3436;
            --border-color:    #e0e4f0;
            --title-font:      "Montserrat", sans-serif;
            --text-font:       "Inter", sans-serif;
            --number-font:     "Space Mono", monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--text-font);
            background: #f0f2f5;
            color: var(--text-color);
            padding: 40px;
        }

        /* ── Card ─────────────────────────────────────────────────────── */
        .invoice-card {
            max-width: 794px;
            margin: auto;
            background: #fff;
            padding: 50px;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        /* ── Print button ─────────────────────────────────────────────── */
        .print-btn-wrapper {
            max-width: 794px;
            margin: 0 auto 15px auto;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .print-btn-wrapper button {
            border: none;
            padding: 8px 20px;
            font-family: var(--title-font);
            font-size: 14px;
            cursor: pointer;
            border-radius: 4px;
            font-weight: 600;
        }
        .btn-do-print { background: var(--table-header-bg); color: #fff; }
        .btn-close    { background: #e5e7eb; color: #374151; }

        /* ── Header ───────────────────────────────────────────────────── */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .company-logo h1 {
            font-family: var(--title-font);
            color: var(--title-color);
            font-size: 32px;
            font-weight: 800;
        }
        .company-info {
            text-align: right;
            font-size: 13px;
            line-height: 1.6;
        }
        .company-info strong {
            font-family: var(--title-font);
            font-size: 18px;
        }

        /* ── Invoice Label Bar ────────────────────────────────────────── */
        .invoice-label {
            text-align: center;
            background-color: var(--title-bg);
            color: var(--title-color);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
            font-family: var(--title-font);
            padding: 12px;
            border-top: 2px solid var(--title-color);
            margin-bottom: 30px;
        }

        /* ── Bill To / Meta ───────────────────────────────────────────── */
        .bill-meta-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .bill-to h4 {
            margin: 0 0 5px 0;
            font-family: var(--title-font);
            color: var(--title-color);
        }
        .bill-to p { margin: 2px 0; }
        .meta-data td {
            padding: 2px 0 2px 20px;
            font-weight: 600;
        }
        .meta-data .number { font-family: var(--number-font); }

        /* ── Items Table ──────────────────────────────────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: var(--table-header-bg);
            color: #fff;
            text-align: left;
            padding: 12px;
            font-size: 13px;
            font-family: var(--title-font);
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        .items-table td.num { font-family: var(--number-font); }
        .items-table .total-row td {
            background: #fafafa;
            font-weight: 700;
            border-bottom: 2px solid var(--table-header-bg);
            font-family: var(--title-font);
        }
        .items-table .total-row td.num { font-family: var(--number-font); }

        /* ── Bottom Grid ──────────────────────────────────────────────── */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 40px;
        }
        .blue-sub-header {
            background: var(--table-header-bg);
            color: #fff;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            font-family: var(--title-font);
            margin-bottom: 10px;
        }
        .content-block {
            margin-bottom: 25px;
            font-size: 15px;
            line-height: 1.6;
        }

        /* ── Amounts Table ────────────────────────────────────────────── */
        .amounts-table { width: 100%; border-collapse: collapse; }
        .amounts-table td {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .amounts-table td.num {
            font-family: var(--number-font);
            text-align: right;
        }
        .amounts-table tr.grand-total td {
            font-weight: 700;
            color: var(--title-color);
            font-size: 15px;
            border-bottom: 2px solid var(--table-header-bg);
        }
        .amounts-table tr.grand-total td.num { font-family: var(--number-font); }

        /* status badges */
        .badge-active   { background:#dcfce7; color:#15803d; padding:2px 10px; border-radius:10px; font-size:12px; font-weight:700; }
        .badge-inactive { background:#fef3c7; color:#92400e; padding:2px 10px; border-radius:10px; font-size:12px; font-weight:700; }

        /* ── Signature ────────────────────────────────────────────────── */
        .signature-section {
            margin-top: 60px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .sig-container { text-align: center; width: 220px; }
        .sig-line {
            border-top: 1px solid #333;
            margin-top: 10px;
            padding-top: 5px;
            font-size: 12px;
            font-weight: bold;
            font-family: var(--title-font);
        }

        /* ── Print ────────────────────────────────────────────────────── */
        @media print {
            body { background: #fff; padding: 0; }
            .print-btn-wrapper { display: none !important; }
            .invoice-card { box-shadow: none; padding: 0; margin: 0; border: none; }
            .header-section { margin-bottom: 12px; }
            .company-logo h1 { font-size: 22px; }
            .company-info { font-size: 11px; line-height: 1.4; }
            .company-info strong { font-size: 14px; }
            .invoice-label { padding: 6px; margin-bottom: 12px; letter-spacing: 1px; }
            .bill-meta-grid { margin-bottom: 12px; font-size: 12px; }
            .bill-to p { margin: 1px 0; }
            .meta-data td { padding: 1px 0 1px 12px; font-size: 12px; }
            .items-table { margin-bottom: 12px; }
            .items-table th { padding: 7px 8px; font-size: 11px; }
            .items-table td { padding: 6px 8px; font-size: 12px; }
            .bottom-grid { gap: 20px; }
            .blue-sub-header { padding: 4px 8px; font-size: 11px; margin-bottom: 5px; }
            .content-block { margin-bottom: 12px; font-size: 12px; line-height: 1.4; }
            .amounts-table td { padding: 5px 0; font-size: 12px; }
            .amounts-table tr.grand-total td { font-size: 13px; }
            .signature-section { margin-top: 25px; }
        }
    </style>
</head>
<body>

{{-- Print buttons --}}
<div class="print-btn-wrapper">
    <button class="btn-do-print" onclick="window.print()">&#128438; Print / Save PDF</button>
    <button class="btn-close" onclick="window.close()">Close</button>
</div>

<div class="invoice-card">

    {{-- Header --}}
    <div class="header-section">
        <div class="company-logo">
            @if($company && $company->logo && file_exists(public_path($company->logo)))
                <img src="{{ asset($company->logo) }}" alt="" style="max-width:80px;">
            @else
                <h1>{{ $company?->name ?? config('app.name') }}</h1>
            @endif
        </div>
        <div class="company-info">
            <strong>{{ $company?->name ?? config('app.name') }}</strong><br>
            @if($company?->address) {{ $company->address }}<br> @endif
            @if($company?->phone || $company?->email)
                {{ $company?->phone }} @if($company?->phone && $company?->email) | @endif {{ $company?->email }}
            @endif
        </div>
    </div>

    {{-- Label bar --}}
    <div class="invoice-label">Expense Voucher</div>

    {{-- Prepared By / Meta --}}
    <div class="bill-meta-grid">
        <div class="bill-to">
            <h4>Prepared By:</h4>
            <p><strong>{{ $expense->user?->name ?? '—' }}</strong></p>
            @if($expense->user?->email)
            <p>Email: {{ $expense->user->email }}</p>
            @endif
            @if($expense->company?->name)
            <p>Company: {{ $expense->company->name }}</p>
            @endif
        </div>
        <div class="meta-data">
            <table>
                <tr>
                    <td>Voucher No.:</td>
                    <td class="number">EXP-{{ str_pad($expense->id, 5, '0', STR_PAD_LEFT) }}</td>
                </tr>
                <tr>
                    <td>Date:</td>
                    <td class="number">{{ \Carbon\Carbon::parse($expense->expense_date)->format('d M Y') }}</td>
                </tr>
                @if($expense->reference)
                <tr>
                    <td>Reference:</td>
                    <td class="number">{{ $expense->reference }}</td>
                </tr>
                @endif
                @if($expense->expense_category)
                <tr>
                    <td>Category:</td>
                    <td class="number">{{ $expense->expense_category->name }}</td>
                </tr>
                @endif
                @if($expense->expense_sub_category)
                <tr>
                    <td>Sub-Category:</td>
                    <td class="number">{{ $expense->expense_sub_category->name }}</td>
                </tr>
                @endif
                <tr>
                    <td>Printed:</td>
                    <td class="number">{{ now()->format('d M Y, h:i A') }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th width="6%">#</th>
                <th>Description</th>
                <th width="22%" style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($expense->items as $i => $item)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td>{{ $item->description ?: '—' }}</td>
                <td class="num" style="text-align:right;">{{ number_format($item->amount, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td class="num">1</td>
                <td>{{ $expense->title }}</td>
                <td class="num" style="text-align:right;">{{ number_format($expense->amount, 2) }}</td>
            </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="num" style="text-align:right;">{{ number_format($expense->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Bottom Grid --}}
    <div class="bottom-grid">
        <div class="left-col">

            @if($expense->description)
            <div class="blue-sub-header">Description / Note:</div>
            <div class="content-block">{{ $expense->description }}</div>
            @endif

            <div class="blue-sub-header">Payment Details:</div>
            <div class="content-block">
                @if($expense->bank)
                    <strong>Bank:</strong> {{ $expense->bank->name }}<br>
                    <strong>Paid Amount:</strong> <span style="font-family:var(--number-font);">{{ number_format($expense->amount, 2) }}</span>
                @else
                    <span style="color:#9ca3af;">No payment details recorded.</span>
                @endif
            </div>

            @if($expense->attachment)
            <div class="blue-sub-header">Attachment:</div>
            <div class="content-block">
                <img src="{{ asset($expense->attachment) }}" alt="Attachment"
                     style="max-width:100%; max-height:120px; border:1px solid #e5e7eb; border-radius:4px; object-fit:contain;">
            </div>
            @endif

        </div>

        <div class="right-col">
            <div class="blue-sub-header">Summary:</div>
            <table class="amounts-table">
                <tr>
                    <td>Expense Amount</td>
                    <td class="num">{{ number_format($expense->amount, 2) }}</td>
                </tr>
                <tr class="grand-total">
                    <td>Total</td>
                    <td class="num">{{ number_format($expense->amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td style="text-align:right;">
                        @if($expense->status)
                            <span class="badge-active">Active</span>
                        @else
                            <span class="badge-inactive">Inactive</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Bank / Method</td>
                    <td class="num" style="font-size:12px;">{{ $expense->bank?->name ?? '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Signature --}}
    <div class="signature-section">
        <div class="sig-container">
            <div class="sig-line">Authorized Signatory</div>
            <div style="font-size:11px; margin-top:5px; font-family:var(--text-font);">
                For, {{ $company?->name ?? config('app.name') }}
            </div>
        </div>
    </div>

</div>

<script>
    window.onload = function () { window.print(); };
</script>
</body>
</html>
