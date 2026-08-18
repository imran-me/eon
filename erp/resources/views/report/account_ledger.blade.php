@extends('layout.app')

@section('meta-information')
    <title>Account Ledger</title>
@endsection

@section('css')
<style>
    /* Scoped CSS using .ledger-report-container as parent */
    .ledger-report-container { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; color: #333; background: #fff; }
    .ledger-report-container .filter-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #e9ecef; }
    .ledger-report-container .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
    .ledger-report-container label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #495057; }
    .ledger-report-container select, .ledger-report-container input { width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; }
    .ledger-report-container .btn-filter { background: #28a745; color: white; border: none; padding: 9px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; }
    
    .ledger-report-container .report-header { text-align: center; margin-bottom: 30px; }
    .ledger-report-container .report-header h2 { margin: 0; color: #212529; text-transform: uppercase; letter-spacing: 1px; }
    
    .ledger-report-container .ledger-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
    .ledger-report-container .ledger-table th { background: #343a40; color: white; padding: 12px 10px; text-align: left; }
    .ledger-report-container .ledger-table td { padding: 10px; border-bottom: 1px solid #dee2e6; }
    .ledger-report-container .text-right { text-align: right; }
    
    .ledger-report-container .opening-bal-row { background: #fffdf0; font-weight: bold; }
    .ledger-report-container .running-bal { font-weight: 600; color: #0056b3; }
    .ledger-report-container .footer-summary { margin-top: 20px; padding: 15px; border-top: 2px solid #343a40; display: flex; justify-content: flex-end; }
</style>
@endsection

@section('main-content')
<div class="ledger-report-container">
    <div class="filter-section">
        <form action="" method="GET">
            <div class="filter-grid">
                <div>
                    <label>Select Account</label>
                    <select name="account_id" class="select2" required>
                        <option value="">-- Choose Account --</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} - {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>From Date</label>
                    <input type="date" name="from_date" value="{{ $fromDate }}">
                </div>
                <div>
                    <label>To Date</label>
                    <input type="date" name="to_date" value="{{ $toDate }}">
                </div>
                <div>
                    <button type="submit" class="btn-filter">Generate Ledger</button>
                </div>
            </div>
        </form>
    </div>

    @if($selectedAccount)
    <div class="report-header">
        <h2>Account Ledger</h2>
        <p>{{ $selectedAccount->name }} ({{ strtoupper($selectedAccount->type) }})<br>
        <small>{{ date('d M Y', strtotime($fromDate)) }} to {{ date('d M Y', strtotime($toDate)) }}</small></p>
    </div>

    <table class="ledger-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Description / Note</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="opening-bal-row">
                <td colspan="3">Opening Balance</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right">{{ number_format($openingBalance, 2) }}</td>
            </tr>

            @php 
                $currentBalance = $openingBalance; 
                $totalDebit = 0;
                $totalCredit = 0;
            @endphp

            @foreach($ledgerItems as $item)
                @php
                    $totalDebit += $item->debit;
                    $totalCredit += $item->credit;
                    
                    // Update running balance based on account type
                    if (in_array($selectedAccount->type, ['asset', 'expense'])) {
                        $currentBalance += ($item->debit - $item->credit);
                    } else {
                        $currentBalance += ($item->credit - $item->debit);
                    }
                @endphp
                <tr>
                    <td>{{ date('d-m-Y', strtotime($item->journalEntry->date)) }}</td>
                    <td>{{ $item->journalEntry->reference ?? 'N/A' }}</td>
                    <td>
                        <strong>{{ ucfirst($item->journalEntry->source) }}</strong><br>
                        <small>{{ $item->note ?? $item->journalEntry->description }}</small>
                    </td>
                    <td class="text-right text-success">{{ $item->debit > 0 ? number_format($item->debit, 2) : '-' }}</td>
                    <td class="text-right text-danger">{{ $item->credit > 0 ? number_format($item->credit, 2) : '-' }}</td>
                    <td class="text-right running-bal">{{ number_format($currentBalance, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background: #f8f9fa;">
                <td colspan="3" class="text-right">Total for Period:</td>
                <td class="text-right">{{ number_format($totalDebit, 2) }}</td>
                <td class="text-right">{{ number_format($totalCredit, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    @else
    <div style="text-align: center; padding: 50px; color: #6c757d;">
        <p>Please select an account and date range to view the ledger.</p>
    </div>
    @endif
</div>
@endsection