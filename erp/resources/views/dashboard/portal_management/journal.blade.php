@extends('layout.app')

@section('meta-information')
    <title>{{ $portal->name }} — Journal</title>
@endsection

@section('main-content')
<style>
    .journal-wrap { background: #fff; border-radius: 14px; box-shadow: 0 2px 16px rgba(0,0,0,0.06); overflow: hidden; }
    /* Header */
    .journal-header { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); padding: 1.5rem 1.75rem; color: #fff; }
    .journal-header h2 { font-size: 1.15rem; font-weight: 700; margin: 0 0 4px; }
    .journal-header p  { font-size: 0.82rem; opacity: 0.75; margin: 0; }
    /* Stats row */
    .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; padding: 1.25rem 1.75rem; border-bottom: 1px solid #f1f5f9; }
    .stat-box { border-radius: 10px; padding: 0.9rem 1.1rem; }
    .stat-box .sb-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
    .stat-box .sb-value { font-size: 1.35rem; font-weight: 800; }
    .sb-balance  { background: #eff6ff; }
    .sb-balance  .sb-label { color: #2563eb; }
    .sb-balance  .sb-value { color: #1d4ed8; }
    .sb-added    { background: #f0fdf4; }
    .sb-added    .sb-label { color: #15803d; }
    .sb-added    .sb-value { color: #16a34a; }
    .sb-used     { background: #fef2f2; }
    .sb-used     .sb-label { color: #b91c1c; }
    .sb-used     .sb-value { color: #dc2626; }
    /* Table */
    .j-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.83rem; }
    .j-table thead th {
        background: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.68rem;
        letter-spacing: 0.05em;
        padding: 11px 14px;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }
    .j-table tbody td {
        padding: 12px 14px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: middle;
    }
    .j-table tbody tr:last-child td { border-bottom: none; }
    .j-table tbody tr:hover { background: #fafbff; }
    .badge { display:inline-block; padding: 2px 9px; border-radius: 99px; font-size: 10.5px; font-weight: 700; }
    .badge-add    { background: #dcfce7; color: #15803d; }
    .badge-ticket { background: #fee2e2; color: #b91c1c; }
    .amount-add   { color: #16a34a; font-weight: 700; }
    .amount-use   { color: #dc2626; font-weight: 700; }
    .balance-val  { font-weight: 700; color: #1e293b; }
    .back-btn { display:inline-flex; align-items:center; gap:6px; padding: 7px 16px; border-radius: 8px; background: #fff; color: #2563eb; border: 1.5px solid #bfdbfe; font-weight: 600; font-size: 0.82rem; text-decoration: none; }
    .back-btn:hover { background: #eff6ff; }
    .journal-entry-link { font-size: 10px; color: #64748b; }
    .empty-state { text-align:center; padding: 3rem 1rem; color: #94a3b8; }
</style>

<div class="journal-wrap">
    <div class="journal-header">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2>{{ $portal->name }} — Transaction Journal</h2>
                <p>
                    Type: {{ strtoupper($portal->type) }}
                    @if($portal->account) &nbsp;|&nbsp; Ledger A/C: {{ $portal->account->name }} @endif
                </p>
            </div>
            <a href="{{ route('role.portal-management.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
               class="back-btn" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.25);">
                ← Back to Portals
            </a>
        </div>
    </div>

    {{-- Summary Stats --}}
    <div class="stats-row">
        <div class="stat-box sb-balance">
            <div class="sb-label">Current Balance</div>
            <div class="sb-value">{{ number_format($portal->balance, 2) }}</div>
        </div>
        <div class="stat-box sb-added">
            <div class="sb-label">Credit Limit</div>
            <div class="sb-value">{{ number_format($totalAdded, 2) }}</div>
        </div>
        <div class="stat-box sb-used">
            <div class="sb-label">Net Used</div>
            <div class="sb-value">{{ number_format($totalUsed, 2) }}</div>
        </div>
    </div>

    {{-- Ledger Table --}}
    <div style="padding: 1.25rem 1.75rem;">

        @if(session('success'))
            <div style="background:#f0fdf4;border-left:4px solid #10b981;color:#166534;padding:10px 14px;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;">
                {{ session('success') }}
            </div>
        @endif

        <div style="overflow-x:auto;">
            <table class="j-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Type</th>
                        <th>Source Account</th>
                        <th>Payment Method</th>
                        <th>Debit (Added)</th>
                        <th>Credit (Used)</th>
                        <th>Balance After</th>
                        <th>Remarks</th>
                        <th>By</th>
                        <th>Journal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td style="color:#cbd5e1;font-size:11px;">{{ $loop->iteration + ($logs->currentPage() - 1) * $logs->perPage() }}</td>
                        <td style="white-space:nowrap;">
                            {{ $log->date ? $log->date->format('d M Y') : '—' }}
                        </td>
                        <td>
                            <span style="font-family:monospace;font-size:11px;color:#475569;">
                                {{ $log->reference ?? '—' }}
                            </span>
                        </td>
                        <td>
                            @if($log->type === 'add_balance')
                                <span class="badge badge-add">Top-up</span>
                            @elseif($log->type === 'opening_balance')
                                <span class="badge" style="background:#ede9fe;color:#5b21b6;">Opening Bal.</span>
                            @elseif($log->type === 'opening_usage')
                                <span class="badge" style="background:#fef3c7;color:#92400e;">Opening Use</span>
                            @elseif($log->type === 'ticket_purchase')
                                <span class="badge badge-ticket">Ticket</span>
                            @else
                                <span class="badge" style="background:#f1f5f9;color:#64748b;">{{ $log->type }}</span>
                            @endif
                        </td>
                        <td>
                            {{ $log->sourceAccount->name ?? '—' }}
                            @if($log->sourceAccount)
                                <div style="font-size:10px;color:#94a3b8;">{{ $log->sourceAccount->code }}</div>
                            @endif
                        </td>
                        <td>
                            @if($log->payment_method)
                                <span style="font-size:11px;background:#f1f5f9;padding:2px 8px;border-radius:6px;color:#475569;">
                                    {{ ucwords(str_replace('_', ' ', $log->payment_method)) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="amount-add">
                            {{ $log->debit > 0 ? number_format($log->debit, 2) : '—' }}
                        </td>
                        <td class="amount-use">
                            {{ $log->credit > 0 ? number_format($log->credit, 2) : '—' }}
                        </td>
                        <td class="balance-val">{{ number_format($log->current_balance, 2) }}</td>
                        <td style="font-size:12px;color:#64748b;max-width:160px;">{{ $log->remarks ?? '—' }}</td>
                        <td style="font-size:11px;">{{ $log->createdBy->name ?? '—' }}</td>
                        <td>
                            @if($log->journal_entry_id)
                                <span class="journal-entry-link">#{{ $log->journal_entry_id }}</span>
                            @else
                                <span style="color:#cbd5e1;font-size:11px;">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12">
                            <div class="empty-state">
                                <div style="font-size:2rem;margin-bottom:0.5rem;">📋</div>
                                <div style="font-weight:600;color:#64748b;">No transactions yet</div>
                                <div style="font-size:0.82rem;margin-top:4px;">Add balance to see the journal here.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
