@php
    use Illuminate\Support\Str;
    $role   = Str::slug(auth()->user()->getRoleNames()->first());
    $fields = $invoiceTemplate?->fields?->where('section', 'footer')->sortBy('sort_order')->where('is_visible', 1);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Visa Invoice {{ $visaSale->invoice_number }} — {{ optional($company)->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 portrait; margin: 14mm; }

    :root {
        --tc:  #1d4ed8;
        --tbg: #eff6ff;
        --thd: #2563eb;
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

    /* ── Status pill ── */
    .pill { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; }
    .pill-paid      { background: #dcfce7; color: #166534; }
    .pill-partial   { background: #ede9fe; color: #5b21b6; }
    .pill-due,
    .pill-unpaid    { background: #fee2e2; color: #b91c1c; }
    .pill-pending   { background: #fef9c3; color: #854d0e; }
    .pill-cancelled { background: #f1f5f9; color: #475569; }

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
    .items-table tbody tr:nth-child(even) td { background: #f5f8ff; }
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
    <a href="{{ route('role.visa-sales.index', ['role' => $role]) }}" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
    <button onclick="window.print()" style="background:#fff;color:#2563eb;border:1px solid #2563eb;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">📄 Download PDF</button>
</div>

<div class="card">

    {{-- Header ── --}}
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

    {{-- Label bar ── --}}
    <div class="label-bar">Visa Sales — Invoice</div>

    {{-- Meta row ── --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>Bill To:</h4>
            <p><strong>{{ $visaSale->client?->name ?? '—' }}</strong></p>
            @if($visaSale->client?->phone)
            <p style="font-family:'Space Mono',monospace;font-size:11px;">{{ $visaSale->client->phone }}</p>
            @endif
            @if($visaSale->client?->email)
            <p>{{ $visaSale->client->email }}</p>
            @endif
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Invoice No</td><td class="nf">{{ $visaSale->invoice_number }}</td></tr>
                <tr><td>Date</td><td class="nf">{{ $visaSale->voucher_date->format('d M Y') }}</td></tr>
                <tr><td>Receivable Date</td><td>{{ $visaSale->receivable_date?->format('d M Y') ?? '—' }}</td></tr>
                @if($visaSale->issuedBy)
                <tr><td>Issued By</td><td>{{ $visaSale->issuedBy->name }}</td></tr>
                @endif
                <tr>
                    <td>Status</td>
                    <td><span class="pill pill-{{ $visaSale->status }}">{{ ucfirst($visaSale->status) }}</span></td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Items table ── --}}
    @php $totalQty = 0; @endphp
    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="33%">Applicant Details</th>
                <th width="17%">Country</th>
                <th width="17%">Visa Type</th>
                <th class="r" width="7%">Qty</th>
                <th class="r" width="11%">Unit Price</th>
                <th class="r" width="10%">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($visaSale->items as $item)
            @php
                $totalQty++;
                $isOther = (bool) $item->other_visa_service_id;
                $p   = $item->visaProcess;
                $svc = $item->otherVisaService;
            @endphp
            <tr>
                <td class="nf">{{ $loop->iteration }}</td>
                @if($isOther)
                <td>
                    <strong>{{ optional(optional($svc)->passportHolder)->name ?? '—' }}</strong>
                    @if(optional(optional($svc)->passportHolder)->passport_no)
                    <span class="sub">PP: {{ $svc->passportHolder->passport_no }}</span>
                    @endif
                </td>
                <td>—</td>
                <td>{{ optional(optional($svc)->serviceType)->name ?? 'Other Service' }}</td>
                @else
                <td>
                    <strong>{{ optional(optional($p)->passportHolder)->name ?? '—' }}</strong>
                    @if(optional(optional($p)->passportHolder)->passport_no)
                    <span class="sub">PP: {{ $p->passportHolder->passport_no }}</span>
                    @endif
                </td>
                <td>{{ optional(optional($p)->country)->name ?? '—' }}</td>
                <td>{{ optional($p)->visa_type ?? optional(optional($p)->visaCategory)->name ?? '—' }}</td>
                @endif
                <td class="r nf">1</td>
                <td class="r nf">৳{{ number_format($item->sale_price, 2) }}</td>
                <td class="r nf">৳{{ number_format($item->sale_price, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4">Grand Total — {{ $totalQty }} Applicant(s)</td>
                <td class="r nf">{{ $totalQty }}</td>
                <td></td>
                <td class="r nf">৳{{ number_format($visaSale->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Bottom grid ── --}}
    <div class="bot">
        <div>
            <div class="sh">Invoice Amount In Words:</div>
            <div class="nb">
                @php
                    try { $words = ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) $visaSale->total_amount)) . ' Taka Only'; }
                    catch (\Throwable) { $words = '৳' . number_format($visaSale->total_amount, 2) . ' only'; }
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
                <tr class="sub-total"><td>Sub Total</td><td class="n">৳{{ number_format($visaSale->total_amount, 2) }}</td></tr>
                <tr class="grand"><td>Total Due</td><td class="n">৳{{ number_format($visaSale->total_amount, 2) }}</td></tr>
                <tr class="rcvd"><td>Received</td><td class="n">৳{{ number_format($visaSale->paid_amount, 2) }}</td></tr>
                @if($visaSale->due_amount > 0)
                <tr class="bal"><td>Balance</td><td class="n">৳{{ number_format($visaSale->due_amount, 2) }}</td></tr>
                @else
                <tr class="bal-nil"><td>Balance</td><td class="n">৳0.00</td></tr>
                @endif
            </table>
        </div>
    </div>

    {{-- Signature ── --}}
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
