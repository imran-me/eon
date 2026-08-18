<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resignation List - Print</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 12px;
            color: #1f2937;
            background: #f0f2f5;
            padding: 40px;
        }

        .print-actions {
            position: fixed;
            top: 16px;
            right: 16px;
            display: flex;
            gap: 8px;
            z-index: 20;
        }

        .print-actions button {
            border: 0;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-print { background: #0891b2; color: #fff; }
        .btn-close { background: #e5e7eb; color: #374151; }

        .invoice-card {
            max-width: 794px;
            margin: auto;
            background: #fff;
            padding: 46px;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .company-logo h1 {
            color: #0e7490;
            margin: 0;
            font-size: 30px;
            font-weight: 800;
        }

        .company-logo img {
            max-width: 95px;
            max-height: 78px;
            object-fit: contain;
        }

        .company-info {
            text-align: right;
            font-size: 13px;
            line-height: 1.6;
        }

        .company-info strong {
            font-size: 18px;
        }

        .invoice-label {
            text-align: center;
            background-color: #ecfeff;
            color: #0e7490;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
            padding: 12px;
            border-top: 2px solid #0e7490;
            margin-bottom: 30px;
        }

        .bill-meta-grid {
            display: flex;
            justify-content: space-between;
            gap: 28px;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .bill-to h4 {
            margin: 0 0 5px 0;
            color: #0e7490;
        }

        .bill-to p {
            margin: 2px 0;
        }

        .meta-data td {
            padding: 2px 0 2px 20px;
            font-weight: 600;
            border: 0;
            white-space: nowrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th {
            background: #0e7490;
            color: #fff;
            padding: 8px 7px;
            text-align: left;
            white-space: nowrap;
            font-weight: 600;
        }

        td {
            padding: 8px 7px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        tbody tr:nth-child(even) { background: #f8fafc; }

        .status {
            display: inline-block;
            border-radius: 999px;
            padding: 2px 7px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #991b1b; }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .print-actions { display: none !important; }

            .invoice-card {
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button type="button" class="btn-print" onclick="window.print()">Print / Save PDF</button>
        <button type="button" class="btn-close" onclick="window.close()">Close</button>
    </div>

    <div class="invoice-card">
        <div class="header-section">
            <div class="company-logo">
                @if(optional($company)->logo)
                    @php
                        $companyLogo = asset($company->logo);
                        if (!file_exists(public_path($company->logo))) {
                            $companyLogo = asset('logo.png');
                        }
                    @endphp
                    <img src="{{ $companyLogo }}" alt="{{ optional($company)->name ?? config('app.name') }}">
                @else
                    <h1>{{ optional($company)->name ?? config('app.name') }}</h1>
                @endif
            </div>
            <div class="company-info">
                <strong>{{ optional($company)->name ?? config('app.name') }}</strong><br>
                @if(optional($company)->address){{ $company->address }}<br>@endif
                {{ optional($company)->phone }}{{ optional($company)->phone && optional($company)->email ? ' | ' : '' }}{{ optional($company)->email }}
            </div>
        </div>

        <div class="invoice-label">Resignation Employee List</div>

        <div class="bill-meta-grid">
            <div class="bill-to">
                <h4>Report Details:</h4>
                <p><strong>All Resignation Employees</strong></p>
                <p>Total Records: {{ $resignations->count() }}</p>
                @if(request()->hasAny(['employee_id', 'resign_type', 'status', 'resign_date']))
                    <p>Filtered Results</p>
                @endif
            </div>
            <div class="meta-data">
                <table>
                    <tr><td>Report Code :</td><td>RES-LIST</td></tr>
                    <tr><td>Printed :</td><td>{{ now()->format('d M Y, h:i A') }}</td></tr>
                    @if(request('status'))
                        <tr><td>Status :</td><td>{{ ucfirst(request('status')) }}</td></tr>
                    @endif
                    @if(request('resign_date'))
                        <tr><td>Resign Date :</td><td>{{ request('resign_date') }}</td></tr>
                    @endif
                </table>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Employee</th>
                    <th>Designation</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resignations as $key => $resignation)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $resignation->user?->name ?? '-' }}</td>
                        <td>{{ $resignation->user?->profile?->designation?->name ?? '-' }}</td>
                        <td>
                            <span class="status status-{{ $resignation->status }}">
                                {{ ucfirst($resignation->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center; padding:20px; color:#9ca3af;">No resignation records found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 400);
        });
    </script>
</body>
</html>
