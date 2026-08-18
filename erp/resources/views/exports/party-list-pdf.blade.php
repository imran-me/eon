<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $label }} List</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
        }

        .hdr {
            width: 100%;
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .hdr td { vertical-align: middle; border: none; padding: 0; }
        .co-name { font-size: 17px; font-weight: bold; color: #1d4ed8; }
        .co-meta { font-size: 9px; color: #64748b; line-height: 1.6; }
        .doc-title {
            text-align: right;
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-meta { text-align: right; font-size: 9px; color: #64748b; line-height: 1.6; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: #1d4ed8;
            color: #fff;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .3px;
            padding: 6px 5px;
            text-align: left;
            border: 1px solid #1d4ed8;
        }
        table.data td {
            padding: 5px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.data tr:nth-child(even) td { background: #f8fafc; }

        .n { text-align: right; white-space: nowrap; }
        .c { text-align: center; white-space: nowrap; }
        .muted { color: #94a3b8; }
        .id-cell { font-weight: bold; color: #4f46e5; white-space: nowrap; }
        .dr { color: #dc2626; font-weight: bold; }
        .cr { color: #059669; font-weight: bold; }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 8px;
            font-weight: bold;
        }
        .b-active   { background: #dcfce7; color: #15803d; }
        .b-hold     { background: #fef9c3; color: #a16207; }
        .b-inactive { background: #fee2e2; color: #b91c1c; }

        .empty { text-align: center; padding: 30px; color: #94a3b8; font-style: italic; }

        .foot {
            margin-top: 12px;
            font-size: 9px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
    </style>
</head>
<body>

<table class="hdr">
    <tr>
        <td>
            <div class="co-name">{{ optional($company)->name ?? config('app.name') }}</div>
            <div class="co-meta">
                @if(optional($company)->address){{ $company->address }}<br>@endif
                {{ optional($company)->phone }}{{ optional($company)->phone && optional($company)->email ? ' | ' : '' }}{{ optional($company)->email }}
            </div>
        </td>
        <td>
            <div class="doc-title">{{ $label }} List</div>
            <div class="doc-meta">
                Generated: {{ now()->format('d M Y, h:i A') }}<br>
                Total records: {{ count($rows) }}
                @if($search)<br>Search: “{{ $search }}”@endif
            </div>
        </td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:9%">{{ $label }} ID</th>
            <th style="width:16%">Name</th>
            <th style="width:12%">Contact Person</th>
            <th style="width:15%">Email</th>
            <th style="width:10%">Phone</th>
            <th style="width:15%">Address</th>
            <th style="width:10%" class="n">Balance</th>
            <th style="width:9%" class="c">Last Txn</th>
            <th style="width:8%" class="c">Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $i => $row)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td class="id-cell">{{ $row['party_id'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['contact_person'] ?: '—' }}</td>
                <td>{{ $row['email'] ?: '—' }}</td>
                <td>{{ $row['phone'] ?: '—' }}</td>
                <td>{{ $row['address'] ?: '—' }}</td>
                <td class="n {{ $row['balance_type'] === 'Dr' ? 'dr' : ($row['balance_type'] === 'Cr' ? 'cr' : 'muted') }}">
                    {{ number_format($row['balance'], 2) }} {{ $row['balance_type'] }}
                </td>
                <td class="c">{{ $row['last_transaction'] ?: '—' }}</td>
                <td class="c">
                    @php
                        $badge = $row['status'] === 'Active' ? 'b-active' : ($row['status'] === 'On Hold' ? 'b-hold' : 'b-inactive');
                    @endphp
                    <span class="badge {{ $badge }}">{{ $row['status'] }}</span>
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty">No {{ strtolower($label) }} records found.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="foot">
    Dr = receivable from party &nbsp;|&nbsp; Cr = payable to party &nbsp;·&nbsp;
    For, {{ optional($company)->name ?? config('app.name') }}
</div>

</body>
</html>
