@php
    $title_color      = $invoiceTemplate?->style?->title_color ?? '#445ee6';
    $title_bg         = $invoiceTemplate?->style?->title_bg ?? '#f4f6ff';
    $tabler_header_bg = $invoiceTemplate?->style?->tabler_header_bg ?? '#1b75cf';
    $text_color       = $invoiceTemplate?->style?->text_color ?? '#2d3436';
    $title_font       = $invoiceTemplate?->style?->title_font ?? 'twelve';
    $text_font        = $invoiceTemplate?->style?->text_font ?? 'one';
    $number_font      = $invoiceTemplate?->style?->number_font ?? 'fifteen';
    $show_border      = $invoiceTemplate?->style?->show_border ?? false;
    $striped_table    = $invoiceTemplate?->style?->striped_table ?? false;
    $paper_size       = $invoiceTemplate?->paper_size ?? 'A4';
    $orientation      = $invoiceTemplate?->orientation ?? 'portrait';

    $paperDimensions = [
        'A4'     => ['portrait'  => ['width' => '210mm', 'height' => '297mm', 'max_width' => '794px'],
                     'landscape' => ['width' => '297mm', 'height' => '210mm', 'max_width' => '1123px']],
        'A5'     => ['portrait'  => ['width' => '148mm', 'height' => '210mm', 'max_width' => '559px'],
                     'landscape' => ['width' => '210mm', 'height' => '148mm', 'max_width' => '794px']],
        'Letter' => ['portrait'  => ['width' => '216mm', 'height' => '279mm', 'max_width' => '816px'],
                     'landscape' => ['width' => '279mm', 'height' => '216mm', 'max_width' => '1056px']],
        'Legal'  => ['portrait'  => ['width' => '216mm', 'height' => '356mm', 'max_width' => '816px'],
                     'landscape' => ['width' => '356mm', 'height' => '216mm', 'max_width' => '1344px']],
    ];

    $dims = $paperDimensions[$paper_size][$orientation] ?? $paperDimensions['A4']['portrait'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $data->invoice_no }} - {{ $company->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400;1,700&family=Crimson+Pro:ital,wght@0,200..900;1,200..900&family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=IBM+Plex+Sans:ital,wght@0,100..700;1,100..700&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Public+Sans:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Source+Sans+3:ital,wght@0,200..900;1,200..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&family=VT323&display=swap" rel="stylesheet">
    <style>
        /* ── Print / Paper Setup ─────────────────────────────────────── */
        @page {
            size: {{ $dims['width'] }} {{ $dims['height'] }};
            margin: 15mm;
        }

        /* ── Font Map ────────────────────────────────────────────────── */
        :root {
            --one:      "Inter", sans-serif;
            --two:      "Lato", sans-serif;
            --three:    "Roboto", sans-serif;
            --four:     "Poppins", sans-serif;
            --five:     "Courier Prime", monospace;
            --six:      "DM Sans", sans-serif;
            --seven:    "Open Sans", sans-serif;
            --eight:    "Public Sans", sans-serif;
            --nine:     "IBM Plex Sans", sans-serif;
            --ten:      "Source Sans 3", sans-serif;
            --eleven:   "Merriweather", serif;
            --twelve:   "Montserrat", sans-serif;
            --thirteen: "Crimson Pro", serif;
            --fourteen: "VT323", monospace;
            --fifteen:  "Space Mono", monospace;
        }

        /* ── Dynamic Theme Variables ─────────────────────────────────── */
        :root {
            --title-color:     {{ $title_color }};
            --title-bg:        {{ $title_bg }};
            --table-header-bg: {{ $tabler_header_bg }};
            --text-color:      {{ $text_color }};
            --title-font:      var(--{{ $title_font }});
            --text-font:       var(--{{ $text_font }});
            --number-font:     var(--{{ $number_font }});
            --border-color:    #e0e4f0;
        }

        /* ── Base ────────────────────────────────────────────────────── */
        body {
            font-family: var(--text-font);
            margin: 0;
            padding: 40px;
            background-color: #f0f2f5;
            color: var(--text-color);
        }

        /* ── Card ────────────────────────────────────────────────────── */
        .invoice-card {
            max-width: {{ $dims['max_width'] }};
            margin: auto;
            background: #fff;
            padding: 50px;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            @if($show_border)
            border: 1px solid var(--border-color);
            @endif
        }

        /* ── Header ──────────────────────────────────────────────────── */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .company-logo h1 {
            font-family: var(--title-font);
            color: var(--title-color);
            margin: 0;
            font-size: 32px;
            font-weight: 800;
        }

        .company-info {
            text-align: right;
            font-size: 13px;
            line-height: 1.6;
            font-family: var(--text-font);
        }

        .company-info strong {
            font-family: var(--title-font);
            font-size: 18px;
        }

        /* ── Invoice Label Bar ───────────────────────────────────────── */
        .invoice-label {
            text-align: center;
            background-color: #f4f6ff;
            color: var(--title-color);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
            font-family: var(--title-font);
            padding: 12px;
            border-top: 2px solid var(--title-color);
            margin-bottom: 30px;
        }

        /* ── Bill To / Meta ──────────────────────────────────────────── */
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

        .bill-to p {
            margin: 2px 0;
            font-family: var(--text-font);
        }

        /* Invoice number / date — use number font */
        .meta-data .number{
            font-family: var(--number-font);
        }

        .meta-data td {
            padding: 2px 0 2px 20px;
            font-weight: 600;
        }

        /* ── Items Table ─────────────────────────────────────────────── */
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
            @if($show_border)
            border: 1px solid rgba(0,0,0,0.12);
            @endif
        }

        .items-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            font-family: var(--text-font);
            @if($show_border)
            border: 1px solid var(--border-color);
            @endif
        }

        /* number cells */
        .items-table td.num {
            font-family: var(--number-font);
        }

        .items-table .total-row td {
            background-color: #fafafa;
            font-weight: 700;
            border-bottom: 2px solid var(--table-header-bg);
            font-family: var(--title-font);
        }

        .items-table .total-row td.num {
            font-family: var(--number-font);
        }

        /* Striped rows */
        @if($striped_table)
        .items-table tbody tr:not(.total-row):nth-child(even) td {
            background-color: #f6f8ff;
        }
        @endif

        /* ── Bottom Grid ─────────────────────────────────────────────── */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 40px;
        }

        /* Sub-headers inside bottom grid */
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
            font-family: var(--text-font);
            line-height: 1.6;
        }

        /* ── Amounts Table ───────────────────────────────────────────── */
        .amounts-table {
            width: 100%;
            border-collapse: collapse;
        }

        .amounts-table td {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-family: var(--text-font);
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

        .amounts-table tr.grand-total td.num {
            font-family: var(--number-font);
        }

        .amounts-table tr.balance-row td {
            font-weight: 700;
        }

        /* ── Signature ───────────────────────────────────────────────── */
        .signature-section {
            margin-top: 60px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .sig-container {
            text-align: center;
            width: 220px;
        }

        .sig-line {
            border-top: 1px solid #333;
            margin-top: 10px;
            padding-top: 5px;
            font-size: 12px;
            font-weight: bold;
            font-family: var(--title-font);
        }

        /* ── Print Styles ────────────────────────────────────────────── */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }

            .print-btn-wrapper {
                display: none !important;
            }

            .invoice-card {
                box-shadow: none;
                padding: 0px;
                margin: 0;
                border: none;
            }

            /* Tighten header */
            .header-section {
                margin-bottom: 12px;
            }

            .company-logo h1 {
                font-size: 22px;
            }

            .company-info {
                font-size: 11px;
                line-height: 1.4;
            }

            .company-info strong {
                font-size: 14px;
            }

            /* Tighten invoice label bar */
            .invoice-label {
                padding: 6px;
                margin-bottom: 12px;
                letter-spacing: 1px;
            }

            /* Tighten bill-to / meta */
            .bill-meta-grid {
                margin-bottom: 12px;
                font-size: 12px;
            }

            .bill-to h4 {
                margin: 0 0 3px 0;
            }

            .bill-to p {
                margin: 1px 0;
            }

            .meta-data td {
                padding: 1px 0 1px 12px;
                font-size: 12px;
            }

            /* Tighten items table */
            .items-table {
                margin-bottom: 12px;
            }

            .items-table th {
                padding: 7px 8px;
                font-size: 11px;
            }

            .items-table td {
                padding: 6px 8px;
                font-size: 12px;
            }

            /* Tighten bottom grid */
            .bottom-grid {
                gap: 20px;
            }

            .blue-sub-header {
                padding: 4px 8px;
                font-size: 11px;
                margin-bottom: 5px;
            }

            .content-block {
                margin-bottom: 12px;
                font-size: 12px;
                line-height: 1.4;
            }

            .amounts-table td {
                padding: 5px 0;
                font-size: 12px;
            }

            .amounts-table tr.grand-total td {
                font-size: 13px;
            }

            /* Tighten signature */
            .signature-section {
                margin-top: 25px;
            }
        }
    </style>
</head>
<body>

{{-- Print Button ─────────────────────────────────────────────────────── --}}
<div class="print-btn-wrapper" style="max-width: {{ $dims['max_width'] }}; margin: 0 auto 15px auto; text-align: right;">
    <button onclick="window.print()" style="
        background-color: var(--table-header-bg);
        color: #fff;
        border: none;
        padding: 8px 20px;
        font-family: var(--title-font);
        font-size: 14px;
        cursor: pointer;
        border-radius: 4px;
    ">🖨️ Print</button>
</div>

{{-- Invoice Card ─────────────────────────────────────────────────────── --}}
<div class="invoice-card">

    {{-- Header --}}
    <div class="header-section">
        <div class="company-logo">
            @if ($company->logo)  
            @php
                $c_image = asset($company->logo);
                if (!file_exists(public_path($company->logo))) {
                    $c_image = asset('logo.png');
                }
            @endphp
            <img src="{{ $c_image }}" alt="" style="max-width: 80px">              
            @else            
            <h1>{{ $company->name }}</h1>
            @endif
        </div>
        <div class="company-info">
            <strong>{{ $company->name }}</strong><br>
            {{ $company->address }}<br>
            {{ $company->phone }} | {{ $company->email }}
        </div>
    </div>

    {{-- Invoice Label --}}
    <div class="invoice-label">Sale Invoice</div>

    {{-- Bill To / Meta --}}
    <div class="bill-meta-grid">
        <div class="bill-to">
            <h4>Bill To:</h4>
            <p><strong>{{ $data->customer?->name }}</strong></p>
            <p>Email.: <span class="num">{{ $data->customer?->email }}</span></p>
            <p>Contact No.: <span class="num">{{ $data->customer?->phone }}</span></p>
        </div>
        <div class="meta-data">
            <table>
                <tr><td>Invoice No.:</td><td class="number">{{ $data->invoice_no }}</td></tr>
                <tr><td>Date:</td><td class="number">{{ $data->sale_date }}</td></tr>
                <tr><td>Time:</td><td class="number">{{ $data->created_at->format('h:i A') }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="55%">Product</th>
                <th width="10%">Qty</th>
                <th width="15%">Price/Unit</th>
                <th width="15%" style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data->items as $key => $item)
            <tr>
                <td class="num">{{ $key+1 }}</td>
                <td>
                    {{ $item->product?->name }}
                </td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num" style="text-align: right;">{{ number_format($item->unit_price, 2) }}</td>
                <td class="num" style="text-align: right;">{{ number_format($item->subtotal, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="num">{{ $data->items->sum('quantity') }}</td>
                <td class="num" style="text-align: right;">{{ number_format($data->items->sum('unit_price'), 2) }}</td>
                <td class="num" style="text-align: right;">{{ number_format($data->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Bottom Grid --}}
    <div class="bottom-grid">
        <div class="left-col">
            <div class="blue-sub-header">Invoice Amount In Words:</div>
            <div class="content-block">{{ ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) $data->total_amount)) }} only</div>          

            <div class="blue-sub-header">Payment Details:</div>
            <div class="content-block">

                @if ($data->transactions->isNotEmpty())
                    @foreach ($data->transactions as $transaction)
                        <div style="margin-bottom: 6px;">
                            <strong>Bank:</strong> {{ $transaction->account?->name }}<br>
                            <strong>A/C:</strong> <span style="font-family: var(--number-font);">{{ $transaction->account?->account_number }}</span><br>
                            <strong>Holder:</strong> {{ $transaction->account?->account_name }}<br>
                            <strong>Paid:</strong> <span style="font-family: var(--number-font);">{{ number_format($transaction->credit, 2) }}</span>
                        </div>
                        @if (!$loop->last)<hr style="margin: 4px 0;">@endif
                    @endforeach
                @else
                    <span class="text-muted">No payment recorded.</span>
                @endif

            </div>

            @php
                $fields = $invoiceTemplate?->fields?->where('section', 'footer')->sortBy('sort_order')->where('is_visible', 1);                
            @endphp
            @foreach ($fields ?? [] as $field)                
            <div class="blue-sub-header" style="text-transform: capitalize">{{ $field->label }}:</div>
            <div class="content-block">{{ $field->key }}</div>
            @endforeach
        </div>

        <div class="right-col">
            <div class="blue-sub-header">Amounts:</div>
            <table class="amounts-table">
                <tr>
                    <td>Sub Total</td>
                    <td class="num">{{ $data->currency }}{{ number_format($data->total_amount, 2) }}</td>
                </tr>
                <tr class="grand-total">
                    <td>Total</td>
                    <td class="num">{{ $data->currency }}{{ number_format($data->total_amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Received</td>
                    <td class="num">{{ $data->currency }}{{ number_format($data->paid_amount ?? 0, 2) }}</td>
                </tr>
                <tr class="balance-row">
                    <td>Balance</td>
                    <td class="num">{{ $data->currency }}{{ number_format($data->due_amount ?? 0, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Signature --}}
    <div class="signature-section">
        <div class="sig-container">
            <div style="font-family: cursive; font-size: 22px; color: var(--text-color);"></div>
            <div class="sig-line">Authorized Signatory</div>
            <div style="font-size: 11px; margin-top: 5px; font-family: var(--text-font);">For, {{ $company->name }}</div>
        </div>
    </div>

</div>

{{-- Auto-open print dialog on load --}}
<script>
    window.onload = function () {
        window.print();
    };
</script>

</body>
</html>