@php
    use Illuminate\Support\Str;
    $role   = Str::slug(auth()->user()->getRoleNames()->first());
    $fields = $invoiceTemplate?->fields?->where('section', 'footer')->sortBy('sort_order')->where('is_visible', 1);

    $clientName  = $data->client?->name  ?? $data->item?->ticketPurchase?->passportHolder?->name;
    $clientPhone = $data->client?->phone ?? $data->item?->ticketPurchase?->passportHolder?->phone;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket Invoice {{ $data->invoice_no }} — {{ $company->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 portrait; margin: 14mm; }

    :root {
        --tc:  #6d28d9;
        --tbg: #f5f3ff;
        --thd: #7c3aed;
        --bdr: #e2e8f0;
        --txt: #1e293b;
        --tf:  "Montserrat", sans-serif;
        --ff:  "Inter", sans-serif;
        --nf:  "Space Mono", monospace;
    }

    *, *::before, *::after {
        box-sizing: border-box; margin: 0; padding: 0;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    body { font-family: var(--ff); padding: 28px; background: #f0f2f5; color: var(--txt); font-size: 12px; }

    /* ── Card ── */
    .card { max-width: 794px; margin: auto; background: #fff; padding: 40px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

    /* ── Header ── */
    .hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .co-info { text-align: right; font-size: 12px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 16px; display: block; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 26px; font-weight: 800; }

    /* ── Label bar ── */
    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 11px; padding: 10px;
        border-top: 2px solid var(--tc); margin-bottom: 24px;
    }

    /* ── Bill-to / Meta ── */
    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 22px; }
    .bill-to h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .bill-to p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    /* ── Items table ── */
    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .items-table th {
        background: var(--thd); color: #fff; text-align: left;
        padding: 9px 11px; font-family: var(--tf); font-size: 10px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .items-table th.r, .items-table td.r { text-align: right; }
    .items-table td { padding: 10px 11px; border-bottom: 1px solid var(--bdr); font-size: 12px; }
    .items-table td.nf { font-family: var(--nf); font-size: 11px; }
    .items-table td .sub { display: block; font-size: 10px; color: #94a3b8; margin-top: 2px; }
    .items-table tbody tr:nth-child(even) td { background: #faf8ff; }
    .items-table .total-row td {
        background: #f8fafc; font-weight: 700; font-family: var(--tf);
        font-size: 12px; border-top: 2px solid var(--thd); border-bottom: none;
    }
    .items-table .total-row td.nf { font-family: var(--nf); }

    /* ── Sub-header bar ── */
    .sh { background: var(--thd); color: #fff; padding: 5px 12px; font-family: var(--tf); font-size: 10px; font-weight: 600; margin-bottom: 8px; letter-spacing: .03em; }

    /* ── Bottom grid ── */
    .bot { display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 30px; }
    .nb { font-size: 12px; line-height: 1.8; color: #475569; margin-bottom: 14px; }
    .amt-tbl { width: 100%; border-collapse: collapse; }
    .amt-tbl td { padding: 6px 0; border-bottom: 1px solid #eee; font-size: 12px; }
    .amt-tbl td.n { font-family: var(--nf); font-size: 11px; text-align: right; }
    .amt-tbl .sub-total td { color: #64748b; }
    .amt-tbl .grand td { font-weight: 700; color: var(--tc); font-size: 14px; border-bottom: 2px solid var(--thd); }
    .amt-tbl .rcvd td  { font-weight: 700; color: #16a34a; }
    .amt-tbl .bal td   { font-weight: 700; color: #dc2626; }
    .amt-tbl .bal-nil td { font-weight: 700; color: #16a34a; }

    /* ── Signature ── */
    .sig { margin-top: 36px; display: flex; justify-content: flex-end; }
    .sig-c { text-align: center; width: 200px; }
    .sig-l { border-top: 1px solid #333; margin-top: 6px; padding-top: 4px; font-size: 10px; font-weight: 700; font-family: var(--tf); }

    /* ── Print ── */
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

{{-- Action buttons ── --}}
<div class="btn-row" style="max-width:794px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="{{ route('role.ticket-sales.index', ['role' => $role]) }}" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#7c3aed;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
    <button onclick="window.print()" style="background:#fff;color:#7c3aed;border:1px solid #7c3aed;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">📄 Download PDF</button>
</div>

<div class="card">

    {{-- Header ── --}}
    <div class="hdr">
        <div>
            @if($company->logo)
            @php $logoSrc = file_exists(public_path($company->logo)) ? asset($company->logo) : asset('logo.png'); @endphp
            <img src="{{ $logoSrc }}" alt="" style="max-width:80px;">
            @else
            <div class="logo-txt">{{ $company->name }}</div>
            @endif
        </div>
        <div class="co-info">
            <strong>{{ $company->name }}</strong>
            @if($company->address){{ $company->address }}<br>@endif
            {{ $company->phone }}{{ $company->phone && $company->email ? ' | ' : '' }}{{ $company->email }}
        </div>
    </div>

    {{-- Label bar ── --}}
    <div class="label-bar">Air Ticketing — Sales Invoice</div>

    {{-- Meta row ── --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>Bill To:</h4>
            <p><strong>{{ $clientName ?? '—' }}</strong></p>
            @if($clientPhone)
            <p style="font-family:'Space Mono',monospace;font-size:11px;">{{ $clientPhone }}</p>
            @endif
            @if($data->ticket?->airline?->name)
            <p>Airline: {{ $data->ticket->airline->name }}</p>
            @endif
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Invoice No</td><td class="nf">{{ $data->invoice_no }}</td></tr>
                <tr><td>Date</td><td class="nf">{{ $data->sale_date }}</td></tr>
                <tr><td>Time</td><td class="nf">{{ $data->created_at->format('h:i A') }}</td></tr>
                @if($data->item?->ticketPurchase?->ticket_no)
                <tr><td>PNR / Ticket</td><td class="nf">{{ $data->item->ticketPurchase->ticket_no }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    {{-- Items table ── --}}
    @php $totalQty = 0; @endphp
    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="55%">Passenger Details</th>
                <th class="r" width="8%">Qty</th>
                <th class="r" width="16%">Unit Price</th>
                <th class="r" width="16%">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data->items as $item)
            @php $totalQty++; @endphp
            <tr>
                <td class="nf">{{ $loop->iteration }}</td>
                <td>
                    <strong>{{ $item->ticketPurchase?->passportHolder?->name ?? '—' }}</strong>
                    @php
                        $from = $item->ticketPurchase?->ticket?->from_airport?->code;
                        $to   = $item->ticketPurchase?->ticket?->to_airport?->code;
                    @endphp
                    @if($from || $to)
                    <span class="sub">{{ $from ?? '—' }} → {{ $to ?? '—' }}</span>
                    @endif
                </td>
                <td class="r nf">1</td>
                <td class="r nf">{{ $data->currency }}{{ number_format($item->price, 2) }}</td>
                <td class="r nf">{{ $data->currency }}{{ number_format($item->price, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">Grand Total — {{ $totalQty }} Passenger(s)</td>
                <td class="r nf">{{ $totalQty }}</td>
                <td></td>
                <td class="r nf">{{ $data->currency }}{{ number_format($data->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Bottom grid ── --}}
    <div class="bot">
        <div>
            <div class="sh">Invoice Amount In Words:</div>
            <div class="nb">
                @php
                    try { $words = ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) $data->total_amount)) . ' Only'; }
                    catch (\Throwable) { $words = $data->currency . number_format($data->total_amount, 2) . ' only'; }
                @endphp
                {{ $words }}
            </div>

            @foreach($fields ?? [] as $field)
            <div class="sh" style="text-transform:capitalize;">{{ $field->label }}:</div>
            <div class="nb">{{ $field->key }}</div>
            @endforeach
        </div>
        <div>
            <div class="sh">Amounts:</div>
            <table class="amt-tbl">
                <tr class="sub-total"><td>Sub Total</td><td class="n">{{ $data->currency }}{{ number_format($data->total_amount, 2) }}</td></tr>
                <tr class="grand"><td>Total Due</td><td class="n">{{ $data->currency }}{{ number_format($data->total_amount, 2) }}</td></tr>
                <tr class="rcvd"><td>Received</td><td class="n">{{ $data->currency }}{{ number_format($data->paid_amount ?? 0, 2) }}</td></tr>
                @if(($data->due_amount ?? 0) > 0)
                <tr class="bal"><td>Balance</td><td class="n">{{ $data->currency }}{{ number_format($data->due_amount, 2) }}</td></tr>
                @else
                <tr class="bal-nil"><td>Balance</td><td class="n">{{ $data->currency }}0.00</td></tr>
                @endif
            </table>
        </div>
    </div>

    {{-- Signature ── --}}
    <div class="sig">
        <div class="sig-c">
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ $company->name }}</div>
        </div>
    </div>

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
