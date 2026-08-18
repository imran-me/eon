@php
    $title_color = $invoiceTemplate?->style?->title_color ?? '#5b73f2';
    $title_bg    = $invoiceTemplate?->style?->title_bg ?? '#5b73f2';
    $tabler_header_bg = $invoiceTemplate?->style?->tabler_header_bg ?? '#5b73f2';
    $text_color  = $invoiceTemplate?->style?->text_color ?? '#333333';
    $title_font  = $invoiceTemplate?->style?->title_font ?? 'twelve';
    $text_font   = $invoiceTemplate?->style?->text_font ?? 'one';
    $number_font = $invoiceTemplate?->style?->number_font ?? 'fifteen';
    $show_border   = $invoiceTemplate?->style?->show_border ?? false;
    $striped_table = $invoiceTemplate?->style?->striped_table ?? false;
    $paper_size  = $invoiceTemplate?->paper_size ?? 'A4';
    $orientation = $invoiceTemplate?->orientation ?? 'portrait';
    $paperDimensions = [
        'A4'     => ['portrait' => ['width' => '210mm', 'height' => '297mm', 'max_width' => '794px'],
                    'landscape'=> ['width' => '297mm', 'height' => '210mm', 'max_width' => '1123px']],
        'A5'     => ['portrait' => ['width' => '148mm', 'height' => '210mm', 'max_width' => '559px'],
                    'landscape'=> ['width' => '210mm', 'height' => '148mm', 'max_width' => '794px']],
        'Letter' => ['portrait' => ['width' => '216mm', 'height' => '279mm', 'max_width' => '816px'],
                    'landscape'=> ['width' => '279mm', 'height' => '216mm', 'max_width' => '1056px']],
        'Legal'  => ['portrait' => ['width' => '216mm', 'height' => '356mm', 'max_width' => '816px'],
                    'landscape'=> ['width' => '356mm', 'height' => '216mm', 'max_width' => '1344px']],
    ];
    $dims = $paperDimensions[$paper_size][$orientation] ?? $paperDimensions['A4']['portrait'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $data->ticket_no }} - {{ $company->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400;1,700&family=Crimson+Pro:ital,wght@0,200..900;1,200..900&family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=IBM+Plex+Sans:ital,wght@0,100..700;1,100..700&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Public+Sans:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Source+Sans+3:ital,wght@0,200..900;1,200..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&family=VT323&display=swap" rel="stylesheet">
    <style>
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

            --title-color:      {{ $title_color }};
            --title-bg:         {{ $title_bg }};
            --table-header-bg:  {{ $tabler_header_bg }};
            --text-color:       {{ $text_color }};
            --title-font:       var(--{{ $title_font }});
            --text-font:        var(--{{ $text_font }});
            --number-font:      var(--{{ $number_font }});
            --border-color:     #ddd;
        }

        @page {
            size: {{ $dims['width'] }} {{ $dims['height'] }};
            margin: 15mm;
        }

        body {
            font-family: var(--text-font);
            margin: 0;
            padding: 20px;
            background-color: #f0f0f0;
            color: var(--text-color);
            min-width: {{ $dims['width'] }};
        }

        .invoice-card {
            max-width: {{ $dims['max_width'] }};
            margin: auto;
            background: #fff;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);

            @if($show_border)
            border: 1px solid var(--border-color);
            @endif
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .logo-area img { width: 150px; }

        .company-contact {
            text-align: right;
            font-size: 13px;
            line-height: 1.5;
        }

        .company-contact h2 {
            margin: 0;
            font-family: var(--title-font);
            color: var(--title-color);
            font-size: 22px;
        }

        /* Invoice Title Bar */
        .invoice-title-bar {
            text-align: center;
            border-top: 2px solid var(--title-bg);
            border-bottom: 2px solid var(--title-bg);
            padding: 5px 0;
            margin: 20px 0;
            background-color: var(--title-bg);
            color: #fff;
            font-family: var(--title-font);
            font-weight: bold;
            font-size: 18px;
        }

        /* Bill To & Invoice Info */
        .bill-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .bill-to h4 {
            margin: 0 0 10px 0;
            font-family: var(--title-font);
            color: var(--title-color);
        }

        .bill-to p { margin: 2px 0; }

        .invoice-details { text-align: right; }
        .invoice-details p {
            margin: 2px 0;
            font-weight: bold;
            font-family: var(--number-font);
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        thead th {
            background-color: var(--table-header-bg);
            color: #fff;
            text-align: left;
            padding: 10px;
            font-family: var(--title-font);

            @if($show_border)
            border: 1px solid rgba(0,0,0,0.15);
            @endif
        }

        tbody td {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
            font-family: var(--text-font);

            @if($show_border)
            border: 1px solid var(--border-color);
            @endif
        }

        /* Striped rows */
        @if($striped_table)
        tbody tr:nth-child(even) td {
            background-color: #f6f6f6;
        }
        @endif

        /* Amount / number cells */
        .num {
            font-family: var(--number-font);
        }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }

        /* Summary */
        .summary-wrapper {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
            font-size: 13px;
        }

        .section-header {
            background-color: var(--title-bg);
            color: #fff;
            padding: 5px 10px;
            margin-bottom: 10px;
            font-family: var(--title-font);
            font-weight: bold;
        }

        .summary-table { width: 100%; }
        .summary-table td {
            padding: 5px;
            border-bottom: 1px solid #eee;
        }
        .summary-table td.num {
            font-family: var(--number-font);
        }

        .bank-details p { margin: 5px 0; }

        /* Footer */
        .footer {
            margin-top: 50px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .signature-area {
            text-align: center;
            width: 200px;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 5px;
            font-size: 12px;
            font-weight: bold;
            font-family: var(--title-font);
        }
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
                padding: 0;
                margin: 0;
                border: none;
            }
        }
    </style>
</head>
<body>
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
<div class="invoice-card">
    <div class="header">
        <div class="logo-area">
            @if ($company->logo)  
            <img src="{{ asset($company->logo) }}" alt="" style="max-width: 120px">              
            @else
            <h1 style="font-family: var(--title-font); color: var(--title-color); margin:0;">{{ $company->name }}</h1>
            @endif
        </div>
        <div class="company-contact">
            <h2>{{ $company->name }}</h2>
            <p>{{ $company->address }}</p>
            <p>Phone: {{ $company->phone }} &nbsp;|&nbsp; Email: {{ $company->email }}</p>
        </div>
    </div>

    <div class="invoice-title-bar">Invoice</div>

    <div class="bill-info">
        <div class="bill-to">
            <h4>Bill To:</h4>
            <p><strong>Abdul Ali</strong></p>
            <p>Contact No.: <span class="num">01626066522</span></p>
        </div>
        <div class="invoice-details">
            <p>Invoice No.: <span class="num">FB182</span></p>
            <p>Date: <span class="num">20-02-2026</span></p>
            <p>Time: <span class="num">7:21 PM</span></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="40%">Item Name</th>
                <th width="15%" class="text-center">Quantity</th>
                <th width="10%" class="text-center">Unit</th>
                <th width="15%" class="text-right">Price / Unit</th>
                <th width="15%" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="num">1</td>
                <td>Facebook Page</td>
                <td class="text-center num">1</td>
                <td class="text-center">Pcs</td>
                <td class="text-right num">৳ 2,500.00</td>
                <td class="text-right num">৳ 2,500.00</td>
            </tr>
            <tr style="font-weight: bold;">
                <td colspan="2">Total</td>
                <td class="text-center num">1</td>
                <td></td>
                <td></td>
                <td class="text-right num">৳ 2,500.00</td>
            </tr>
        </tbody>
    </table>

    <div class="summary-wrapper">
        <div class="left-column">
            <div class="section-header">Invoice Amount In Words:</div>
            <p style="margin-bottom: 20px;">Two Thousand Five Hundred only</p>

            <div class="section-header">Terms & Conditions:</div>
            <p style="margin-bottom: 20px;">Thanks for doing business with us!</p>

            <div class="section-header">Bank Details:</div>
            <div class="bank-details">
                <p>Bank Name: Islami Bank Bangladesh Limited</p>
                <p>Account No.: <span class="num">20506010200109510</span></p>
                <p>Account Holder: Md Omit Hasan</p>
            </div>
        </div>

        <div class="right-column">
            <div class="section-header">Amounts:</div>
            <table class="summary-table">
                <tr>
                    <td>Sub Total</td>
                    <td class="text-right num">৳ 2,500.00</td>
                </tr>
                <tr style="font-weight: bold;">
                    <td>Total</td>
                    <td class="text-right num">৳ 2,500.00</td>
                </tr>
                <tr>
                    <td>Received</td>
                    <td class="text-right num">৳ 0.00</td>
                </tr>
                <tr style="font-weight: bold;">
                    <td>Balance</td>
                    <td class="text-right num">৳ 2,500.00</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="footer">
        <div class="signature-area">
            <p style="font-size: 12px; margin-bottom: 0; font-family: var(--text-font);">For, Freebirds IT</p>
            <div style="height: 40px;">
                <i style="font-family: cursive; color: var(--text-color);">Omit</i>
            </div>
            <div class="signature-line">Authorized Signatory</div>
        </div>
    </div>
</div>
<script>
    window.onload = function () {
        window.print();
    };
</script>
</body>
</html>