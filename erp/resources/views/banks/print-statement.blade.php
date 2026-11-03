<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bank Statement — {{ $bank->name }}</title>
@include('print.statement-styles')
</head>
<body>

{{-- Action buttons ── --}}
<div class="btn-row" style="max-width:794px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="javascript:history.back()" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#4f46e5;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
    <button onclick="window.print()" style="background:#fff;color:#4f46e5;border:1px solid #4f46e5;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">📄 Download PDF</button>
</div>

<div class="card">

    {{-- Header ── --}}
    @include('print.statement-header')

    {{-- Label bar ── --}}
    @php $selection = (int) ($selection ?? 0); @endphp
    <div class="label-bar">Bank Account Statement{{ $selection ? " — Selected Entries" : "" }}</div>

    {{-- Account info / Meta ── --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>Account Details</h4>
            <p><strong>{{ $bank->name }}</strong></p>
            <p style="font-family:'Space Mono',monospace;font-size:11px;">{{ $bank->account_number }}</p>
            <p>{{ $bank->account_name }}</p>
            @if($bank->branch_name)<p>{{ $bank->branch_name }}</p>@endif
            <div style="margin-top:6px;">
                <span class="role-badge">{{ ucfirst(str_replace('_', ' ', $bank->type)) }}</span>
                <span class="role-badge">{{ ucfirst($bank->account_type) }}</span>
            </div>
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Account No.</td><td class="nf">{{ $bank->account_number }}</td></tr>
                <tr>
                    <td>{{ $selection ? "Showing" : "Period" }}</td>
                    <td class="nf">
                        @if($selection)
                            {{ $selection }} selected transaction{{ $selection === 1 ? "" : "s" }}
                        @elseif($dateFrom)
                            {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} → {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : now()->format('d M Y') }}
                        @else
                            All Time
                        @endif
                    </td>
                </tr>
                <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y') }}</td></tr>
                <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Summary strip ── --}}
    <div class="summary-strip">
        <div class="sum-card">
            <div class="sum-label">Opening Balance</div>
            <div class="sum-value">৳{{ number_format($openingBalance, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Debit (Dr)</div>
            <div class="sum-value" style="color:#dc2626;">৳{{ number_format($totalDebit, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Credit (Cr)</div>
            <div class="sum-value" style="color:#16a34a;">৳{{ number_format($totalCredit, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Closing Balance</div>
            <div class="sum-value">
                ৳{{ number_format(abs($closingBalance), 2) }}
                {{ $closingBalance > 0 ? 'Dr' : ($closingBalance < 0 ? 'Cr' : '') }}
            </div>
        </div>
    </div>

    {{-- Ledger table ── --}}
    <table class="ledger">
        <thead>
            <tr>
                <th width="10%">Date</th>
                <th width="12%">Reference</th>
                <th width="20%">Description</th>
                <th width="14%">Beneficiary</th>
                <th width="10%">Source</th>
                <th class="r" width="11%">Debit (৳)</th>
                <th class="r" width="11%">Credit (৳)</th>
                <th class="r" width="12%">Balance (৳)</th>
            </tr>
        </thead>
        <tbody>
            @if($openingBalance != 0 && !$selection)
            <tr class="opening-row">
                <td>{{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M y') : '' }}</td>
                <td>—</td>
                <td colspan="3">Opening Balance Brought Forward</td>
                <td class="r">—</td>
                <td class="r">—</td>
                <td class="r mono">{{ number_format(abs($openingBalance), 2) }} Dr</td>
            </tr>
            @endif

            @forelse($rows as $row)
            @php
                $knownSources = ['expense', 'sale', 'bank_transfer', 'bank_transaction'];
                $badgeCls = in_array($row['source'], $knownSources) ? 'badge-' . $row['source'] : 'badge-default';
                $bal = (float) $row['balance'];
            @endphp
            <tr>
                <td>{{ \Carbon\Carbon::parse($row['date'])->format('d M y') }}</td>
                <td class="mono">{{ $row['reference'] ?? '—' }}</td>
                <td>
                    <span{{ !empty($row['description_muted']) ? ' style=color:#94a3b8' : '' }}>{{ Str::limit($row['description_brief'] ?? $row['description_head'] ?? $row['description'], 55) }}</span>
                    @if(!empty($row['txn_no']))<span style="display:block;font-family:var(--nf);font-size:8.5px;color:#a3adbb;margin-top:2px;">{{ $row['txn_no'] }}</span>@endif
                </td>
                <td>
                    @if(($row['beneficiary_name'] ?? '') !== '')
                        @php
                            $m = $row['beneficiary_method'] ?? null;
                            $pill = ['cash' => ['Cash', '#dcfce7', '#15803d'], 'bank' => ['Bank', '#dbeafe', '#1e40af'], 'cheque' => ['Cheque', '#fef3c7', '#92400e']][$m] ?? null;
                            $kindLabel = match ($row['beneficiary_kind'] ?? null) {
                                'customer' => 'Customer', 'supplier' => 'Supplier', 'agent' => 'Agent',
                                'vendor' => 'Vendor', 'employee' => 'Employee', 'others' => 'Party', default => null,
                            };
                        @endphp
                        <strong>{{ Str::limit($row['beneficiary_name'], 40) }}</strong>@if($pill)<span class="badge" style="margin-left:4px;background:{{ $pill[1] }};color:{{ $pill[2] }};">{{ $pill[0] }}</span>@endif
                        @if($pill)
                            @php $via = implode(': ', array_filter([$row['beneficiary_bank'] ?? null, !empty($row['beneficiary_ref']) ? ($m === 'cheque' ? 'Cheque No. ' : '') . $row['beneficiary_ref'] : null])); @endphp
                            @if($via !== '')<span style="display:block;font-size:9px;color:#94a3b8;white-space:nowrap;">{{ $via }}</span>@endif
                        @elseif($kindLabel)
                            <span style="display:block;font-size:9px;color:#94a3b8;">{{ $kindLabel }}</span>
                        @endif
                    @else
                        —
                    @endif
                </td>
                <td><span class="badge {{ $badgeCls }}">{{ ucfirst(str_replace('_', ' ', $row['source'])) }}</span></td>
                <td class="r {{ $row['debit']  > 0 ? 'debit'  : '' }}">{{ $row['debit']  > 0 ? number_format($row['debit'],  2) : '—' }}</td>
                <td class="r {{ $row['credit'] > 0 ? 'credit' : '' }}">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '—' }}</td>
                <td class="r {{ $bal > 0 ? 'balance-dr' : ($bal < 0 ? 'balance-cr' : '') }}">
                    {{ number_format(abs($bal), 2) }} {{ $bal > 0 ? 'Dr' : ($bal < 0 ? 'Cr' : '') }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align:center;padding:20px;color:#94a3b8;">No transactions found for this period.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">
                    Closing Balance — {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : now()->format('d M Y') }}
                </td>
                <td class="r">৳{{ number_format($totalDebit, 2) }}</td>
                <td class="r">৳{{ number_format($totalCredit, 2) }}</td>
                <td class="r">
                    ৳{{ number_format(abs($closingBalance), 2) }}
                    {{ $closingBalance > 0 ? 'Dr' : ($closingBalance < 0 ? 'Cr' : '') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Bottom grid ── --}}
    <div class="bot">
        <div>
            <div class="sh">Closing Balance In Words:</div>
            <div class="nb">
                @php
                    try { $words = ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) abs($closingBalance))) . ' Taka'; }
                    catch (\Throwable) { $words = '৳' . number_format(abs($closingBalance), 2); }
                @endphp
                {{ $words }}
                {{ $closingBalance > 0 ? '(Positive)' : ($closingBalance < 0 ? '(Overdrawn)' : '(Settled)') }}
            </div>

            <div class="sh">Note:</div>
            <div class="nb" style="font-size:11px;color:#64748b;">
                This statement was generated on {{ now()->format('d M Y \a\t H:i') }} and reflects all journal-recorded transactions
                @if($dateFrom)
                    from {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} to {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : now()->format('d M Y') }}.
                @else
                    from inception to date.
                @endif
            </div>
        </div>
        <div>
            <div class="sh">Summary:</div>
            <table class="amt-tbl">
                <tr><td>Opening Balance</td><td class="n">৳{{ number_format($openingBalance, 2) }}</td></tr>
                <tr class="dr-row"><td>Total Debit (Dr)</td><td class="n">৳{{ number_format($totalDebit, 2) }}</td></tr>
                <tr class="cr-row"><td>Total Credit (Cr)</td><td class="n">৳{{ number_format($totalCredit, 2) }}</td></tr>
                <tr class="grand">
                    <td>Closing Balance</td>
                    <td class="n">
                        ৳{{ number_format(abs($closingBalance), 2) }}
                        {{ $closingBalance > 0 ? 'Dr' : ($closingBalance < 0 ? 'Cr' : '') }}
                    </td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Signature ── --}}
    @include('print.statement-signature')

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
