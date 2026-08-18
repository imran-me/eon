<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Flight Sales Voucher {{ $booking->booking_number }} — {{ optional($company)->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 portrait; margin: 14mm; }

    :root {
        --tc:   #c2410c;
        --tbg:  #fff7ed;
        --thd:  #ea580c;
        --bdr:  #e2e8f0;
        --txt:  #1e293b;
        --tf:   "Montserrat", sans-serif;
        --ff:   "Inter", sans-serif;
        --nf:   "Space Mono", monospace;
    }

    *, *::before, *::after {
        box-sizing: border-box; margin: 0; padding: 0;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    body { font-family: var(--ff); padding: 28px; background: #f0f2f5; color: var(--txt); font-size: 12px; }

    .card { max-width: 794px; margin: auto; background: #fff; padding: 40px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

    /* Header */
    .hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .co-info { text-align: right; font-size: 12px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 16px; display: block; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 26px; font-weight: 800; }

    /* Label bar */
    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 11px; padding: 10px;
        border-top: 2px solid var(--tc); margin-bottom: 24px;
    }

    /* Bill-to / Meta */
    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 22px; }
    .bill-to h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .bill-to p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    /* Status pills */
    .pill { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; }
    .pill-paid    { background: #dcfce7; color: #166534; }
    .pill-partial { background: #ffedd5; color: #c2410c; }
    .pill-due     { background: #fee2e2; color: #b91c1c; }

    /* Items table */
    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .items-table th { background: var(--thd); color: #fff; text-align: left; padding: 10px 12px; font-family: var(--tf); font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
    .items-table th.r, .items-table td.r { text-align: right; }
    .items-table td { padding: 10px 12px; border-bottom: 1px solid var(--bdr); font-size: 13px; }
    .items-table td.nf { font-family: var(--nf); font-size: 11px; }
    .items-table td small { display: block; font-size: 10px; color: #64748b; margin-top: 2px; }
    .items-table .total-row td { background: #fafafa; font-weight: 700; border-bottom: 2px solid var(--thd); font-family: var(--tf); font-size: 12px; }
    .items-table .total-row td.nf { font-family: var(--nf); }
    .items-table tbody tr:nth-child(even) td { background: #fffbf7; }

    /* Sub-header */
    .sh { background: var(--thd); color: #fff; padding: 5px 12px; font-family: var(--tf); font-size: 10px; font-weight: 600; margin-bottom: 8px; letter-spacing: .03em; }

    /* Bottom grid */
    .bot { display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 30px; }
    .nb { font-size: 12px; line-height: 1.8; color: #475569; }
    .amt-tbl { width: 100%; border-collapse: collapse; }
    .amt-tbl td { padding: 6px 0; border-bottom: 1px solid #eee; font-size: 12px; }
    .amt-tbl td.n { font-family: var(--nf); font-size: 11px; text-align: right; }
    .amt-tbl .grand td { font-weight: 700; color: var(--tc); font-size: 14px; border-bottom: 2px solid var(--thd); }
    .amt-tbl .paid td  { font-weight: 700; color: #16a34a; }
    .amt-tbl .due td   { font-weight: 700; color: #dc2626; }

    /* Amount in words */
    .words-lbl { margin-top: 14px; }

    /* Signature */
    .sig { margin-top: 36px; display: flex; justify-content: flex-end; }
    .sig-c { text-align: center; width: 200px; }
    .sig-l { border-top: 1px solid #333; margin-top: 6px; padding-top: 4px; font-size: 10px; font-weight: 700; font-family: var(--tf); }

    /* Print */
    @media print {
        body       { background: #fff !important; padding: 0 !important; }
        .btn-row   { display: none !important; }
        .card      { box-shadow: none !important; padding: 0 !important; max-width: 100% !important; }
        .label-bar { padding: 7px; margin-bottom: 16px; }
        .sig       { margin-top: 24px; }
    }
</style>
</head>
<body>

<div class="btn-row" style="max-width:794px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="javascript:history.back()" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#ea580c;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
    <button onclick="window.print()" style="background:#fff;color:#ea580c;border:1px solid #ea580c;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">📄 Download PDF</button>
</div>

<div class="card">

    {{-- Header --}}
    <div class="hdr">
        <div>
            @if(optional($company)->logo)
            @php $logoSrc = file_exists(public_path($company->logo)) ? asset($company->logo) : asset('logo.png'); @endphp
            <img src="{{ $logoSrc }}" alt="" style="max-width:80px;">
            @else
            <div class="logo-txt">{{ optional($company)->name ?? config('app.name') }}</div>
            @endif
        </div>
        <div class="co-info">
            <strong>{{ optional($company)->name ?? config('app.name') }}</strong>
            @if(optional($company)->address){{ $company->address }}<br>@endif
            {{ optional($company)->phone }}{{ optional($company)->phone && optional($company)->email ? ' | ' : '' }}{{ optional($company)->email }}
        </div>
    </div>

    {{-- Label bar --}}
    <div class="label-bar">Contract Flight Sales Voucher</div>

    {{-- Meta row --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>Bill To:</h4>
            <p><strong>{{ $booking->client?->name ?? '—' }}</strong></p>
            @if($booking->client?->phone)
            <p>{{ $booking->client->phone }}</p>
            @endif
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Voucher #</td><td class="nf">{{ $booking->booking_number }}</td></tr>
                <tr><td>Date</td><td class="nf">{{ $booking->created_at ? $booking->created_at->format('d M Y') : now()->format('d M Y') }}</td></tr>
                <tr><td>Flights</td><td>{{ $booking->items->count() }}</td></tr>
                <tr><td>Total Seats</td><td>{{ $booking->seats }}</td></tr>
                <tr>
                    <td>Payment</td>
                    <td><span class="pill pill-{{ $booking->payment_status }}">{{ ucfirst($booking->payment_status) }}</span></td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Flight items table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th width="36%">Flight</th>
                <th width="28%">Route / Departure</th>
                <th class="r" width="10%">Seats</th>
                <th class="r" width="13%">Unit Price</th>
                <th class="r" width="13%">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($booking->items as $item)
            @php $flight = $item->contractFlight; @endphp
            <tr>
                <td>
                    <strong>{{ $flight?->flight_number ?? '—' }}</strong>
                    <small>{{ optional($flight?->ticket?->airline)->name ?? '' }}{{ $flight?->airline_flight_no ? ' · ' . $flight->airline_flight_no : '' }} @if(optional($flight?->flightCategory)->name)· {{ $flight->flightCategory->name }}@endif</small>
                </td>
                <td>
                    {{ $flight?->route ?? '—' }}
                    <small>{{ $flight?->departure_at ? $flight->departure_at->format('d M Y, h:i A') : '—' }}</small>
                </td>
                <td class="r nf">{{ $item->seats }}</td>
                <td class="r nf">৳{{ number_format($item->unit_price, 2) }}</td>
                <td class="r nf">৳{{ number_format($item->total_amount, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4">Grand Total</td>
                <td class="r nf">৳{{ number_format($booking->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Bottom grid --}}
    <div class="bot">
        <div>
            <div class="sh">Amount In Words:</div>
            <div class="nb" style="margin-bottom:14px;">
                {{ ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) $booking->total_amount)) }} Taka Only
            </div>
            <div class="sh">Notes:</div>
            <div class="nb">{{ $booking->notes ?: 'No additional notes for this booking.' }}</div>
        </div>
        <div>
            <div class="sh">Payment Summary:</div>
            <table class="amt-tbl">
                <tr class="grand"><td>Total Amount</td><td class="n">৳{{ number_format($booking->total_amount, 2) }}</td></tr>
                <tr class="paid"><td>Paid Amount</td><td class="n">৳{{ number_format($booking->paid_amount, 2) }}</td></tr>
                <tr class="due"><td>Due Amount</td><td class="n">৳{{ number_format($booking->due_amount, 2) }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Signature --}}
    <div class="sig">
        <div class="sig-c">
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
