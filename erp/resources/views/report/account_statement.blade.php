@extends('layout.app')

@section('meta-information')
    <title>Account Statement</title>
@endsection

@section('css')
<style>
    .stmt-box { background: #fff; padding: 30px; color: #222; font-size: 14px; line-height: 1.5; }
    .stmt-box .stmt-controls { background: #f4f7f6; padding: 20px; border-radius: 5px; margin-bottom: 25px; border: 1px solid #ddd; }
    .stmt-box .stmt-controls form { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
    .stmt-box .stmt-controls label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px; color: #555; }
    .stmt-box .stmt-controls select, .stmt-box .stmt-controls input { padding: 8px; border: 1px solid #ccc; border-radius: 3px; min-width: 180px; }
    .stmt-box .btn-gen { background: #1a1a1a; color: #fff; border: none; padding: 9px 20px; border-radius: 3px; cursor: pointer; }

    .stmt-box .stmt-header { display: flex; justify-content: space-between; margin-bottom: 40px; border-bottom: 3px solid #1a1a1a; padding-bottom: 15px; }
    .stmt-box .stmt-title h1 { margin: 0; font-size: 28px; text-transform: uppercase; }
    .stmt-box .stmt-info { text-align: right; }

    .stmt-box .table-stmt { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .stmt-box .table-stmt th { border-bottom: 2px solid #1a1a1a; padding: 12px 10px; text-align: left; background: #fafafa; }
    .stmt-box .table-stmt td { padding: 12px 10px; border-bottom: 1px solid #eee; vertical-align: top; }
    .stmt-box .text-right { text-align: right; }
    
    .stmt-box .row-opening { background: #fdfdfd; font-weight: bold; }
    .stmt-box .bal-cell { font-weight: bold; background: #f9f9f9; }
    .stmt-box .source-tag { font-size: 10px; background: #eee; padding: 2px 5px; border-radius: 3px; color: #666; text-transform: uppercase; }

    @media print {
        .stmt-box .stmt-controls { display: none; }
        .stmt-box { padding: 0; }
    }
</style>
@endsection

@section('main-content')
<div class="stmt-box">
    <div class="stmt-controls">
        <form action="" method="GET">
            <div>
                <label>Account / Bank / Loan</label>
                <select name="account_id" class="select2">
                    <option value="">Select Account...</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                            {{ $acc->name }} ({{ $acc->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>From</label>
                <input type="date" name="from_date" value="{{ $fromDate }}">
            </div>
            <div>
                <label>To</label>
                <input type="date" name="to_date" value="{{ $toDate }}">
            </div>
            <button type="submit" class="btn-gen">View Statement</button>
        </form>
    </div>

    @if($selectedAccount)
    <div class="stmt-header">
        <div class="stmt-title">
            <h1>Account Statement</h1>
            <span>{{ $selectedAccount->name }}</span>
        </div>
        <div class="stmt-info">
            <strong>Period:</strong> {{ date('d M Y', strtotime($fromDate)) }} - {{ date('d M Y', strtotime($toDate)) }}<br>
            <strong>Account Code:</strong> {{ $selectedAccount->code }}
        </div>
    </div>

    <table class="table-stmt">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description / Reference</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="row-opening">
                <td colspan="2">Opening Balance</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right">{{ number_format($openingBalance, 2) }}</td>
            </tr>

            @php $runningBal = $openingBalance; @endphp
            @foreach($entries as $entry)
                @php
                    if (in_array($selectedAccount->type, ['asset', 'expense'])) {
                        $runningBal += ($entry->debit - $entry->credit);
                    } else {
                        $runningBal += ($entry->credit - $entry->debit);
                    }
                @endphp
                <tr>
                    <td>{{ date('d-m-Y', strtotime($entry->journalEntry->date)) }}</td>
                    <td>
                        <span class="source-tag">{{ $entry->journalEntry->source }}</span> 
                        <strong>{{ $entry->journalEntry->reference }}</strong><br>
                        <small>{{ $entry->note ?? $entry->journalEntry->description }}</small>
                    </td>
                    <td class="text-right">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '-' }}</td>
                    <td class="text-right">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '-' }}</td>
                    <td class="text-right bal-cell">{{ number_format($runningBal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-size: 16px; font-weight: bold;">
                <td colspan="4" class="text-right" style="padding-top: 20px;">Closing Balance:</td>
                <td class="text-right" style="padding-top: 20px; border-bottom: 4px double #1a1a1a;">
                    {{ number_format($runningBal, 2) }}
                </td>
            </tr>
        </tfoot>
    </table>
    @else
        <div style="text-align: center; padding: 100px 0; color: #999;">
            <h3>Select an account to generate a statement.</h3>
        </div>
    @endif
</div>
@endsection