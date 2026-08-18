@extends('layout.app')

@section('meta-information')
    <title>Profit & Loss Statement</title>
@endsection

@section('css')
<style>
    .pl-report-container { font-family: 'Inter', 'Segoe UI', sans-serif; padding: 30px; background: #fff; color: #1a202c; max-width: 900px; margin: auto; }
    .pl-report-container .report-header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #2d3748; padding-bottom: 20px; }
    .pl-report-container .report-header h1 { margin: 0; font-size: 26px; text-transform: uppercase; letter-spacing: 2px; }
    
    .pl-report-container .filter-section { background: #f7fafc; padding: 15px; border-radius: 6px; margin-bottom: 30px; display: flex; justify-content: center; gap: 15px; align-items: flex-end; border: 1px solid #e2e8f0; }
    .pl-report-container .filter-section label { display: block; font-size: 11px; font-weight: bold; color: #4a5568; margin-bottom: 4px; }
    .pl-report-container .filter-section input { padding: 6px; border: 1px solid #cbd5e0; border-radius: 4px; }
    .pl-report-container .btn-filter { background: #2d3748; color: white; border: none; padding: 7px 15px; border-radius: 4px; cursor: pointer; }

    .pl-report-container .section-heading { background: #edf2f7; padding: 10px 15px; font-weight: bold; margin-top: 25px; border-left: 4px solid #2d3748; }
    .pl-report-container .data-table { width: 100%; border-collapse: collapse; }
    .pl-report-container .data-table td { padding: 12px 15px; border-bottom: 1px solid #f0f4f8; }
    .pl-report-container .text-right { text-align: right; }
    
    .pl-report-container .total-row { font-weight: bold; font-size: 16px; background: #fafafa; }
    .pl-report-container .net-profit-box { margin-top: 40px; padding: 20px; border: 2px solid #2d3748; display: flex; justify-content: space-between; align-items: center; border-radius: 8px; }
    .pl-report-container .profit-label { font-size: 20px; font-weight: bold; }
    .pl-report-container .profit-value { font-size: 24px; font-weight: bold; color: #2f855a; }
    .pl-report-container .loss-value { font-size: 24px; font-weight: bold; color: #c53030; }

    @media print {
        .pl-report-container .filter-section { display: none; }
        .pl-report-container { padding: 0; border: none; }
    }
</style>
@endsection

@section('main-content')
<div class="pl-report-container">
    <div class="report-header">
        <h1>Profit & Loss Statement</h1>
        <p>From {{ date('d M Y', strtotime($fromDate)) }} to {{ date('d M Y', strtotime($toDate)) }}</p>
    </div>

    <form action="" method="GET" class="filter-section">
        <div>
            <label>From Date</label>
            <input type="date" name="from_date" value="{{ $fromDate }}">
        </div>
        <div>
            <label>To Date</label>
            <input type="date" name="to_date" value="{{ $toDate }}">
        </div>
        <button type="submit" class="btn-filter">Update Report</button>
    </form>

    <div class="section-heading">Operating Income</div>
    <table class="data-table">
        @foreach($incomeItems as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td class="text-right">{{ number_format($item->balance, 2) }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td>Total Income</td>
            <td class="text-right">{{ number_format($totalIncome, 2) }}</td>
        </tr>
    </table>

    <div class="section-heading">Operating Expenses</div>
    <table class="data-table">
        @foreach($expenseItems as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td class="text-right">({{ number_format($item->balance, 2) }})</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td>Total Expenses</td>
            <td class="text-right">({{ number_format($totalExpense, 2) }})</td>
        </tr>
    </table>

    <div class="net-profit-box">
        <div class="profit-label">Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}</div>
        <div class="{{ $netProfit >= 0 ? 'profit-value' : 'loss-value' }}">
            {{ number_format($netProfit, 2) }}
        </div>
    </div>
</div>
@endsection