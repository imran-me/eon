@extends('layout.app')

@section('meta-information')
    <title>Balance Sheet Report</title>
@endsection

@section('css')
<style>
    /* Scoped CSS using .bs-report-container as parent */
    .bs-report-container { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; color: #333; background: #f9f9f9; }
    .bs-report-container .report-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #444; padding-bottom: 10px; margin-bottom: 20px; }
    .bs-report-container .report-title { font-size: 24px; font-weight: bold; margin: 0; }
    .bs-report-container .filter-form { display: flex; gap: 10px; align-items: center; }
    .bs-report-container .filter-form input { padding: 5px; border: 1px solid #ccc; border-radius: 4px; }
    .bs-report-container .filter-form button { background: #007bff; color: white; border: none; padding: 6px 15px; border-radius: 4px; cursor: pointer; }
    
    .bs-report-container .report-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
    .bs-report-container .section-title { font-size: 18px; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-top: 0; color: #2c3e50; }
    
    .bs-report-container .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .bs-report-container .data-table td { padding: 8px 0; border-bottom: 1px solid #eee; }
    .bs-report-container .data-table .text-right { text-align: right; }
    .bs-report-container .data-table .total-row { font-weight: bold; border-top: 2px solid #333; }
    .bs-report-container .data-table .double-underline { border-bottom: 3px double #333; }
    
    .bs-report-container .profit-row { font-style: italic; color: #666; }

    @media (max-width: 768px) {
        .bs-report-container .report-grid { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('main-content')
<div class="bs-report-container">
    <div class="report-header">
        <div>
            <h1 class="report-title">Balance Sheet</h1>
            <small>As of {{ date('F d, Y', strtotime($date)) }}</small>
        </div>
        <form action="" method="GET" class="filter-form">
            <input type="date" name="date" value="{{ $date }}">
            <button type="submit">Update Report</button>
        </form>
    </div>

    <div class="report-grid">
        <div class="report-column">
            <h2 class="section-title">Assets</h2>
            <table class="data-table">
                @foreach($assets as $asset)
                <tr>
                    <td>{{ $asset->name }}</td>
                    <td class="text-right">{{ number_format($asset->balance, 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total Assets</td>
                    <td class="text-right double-underline">{{ number_format($assets->sum('balance'), 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="report-column">
            <h2 class="section-title">Liabilities</h2>
            <table class="data-table">
                @foreach($liabilities as $liability)
                <tr>
                    <td>{{ $liability->name }}</td>
                    <td class="text-right">{{ number_format($liability->balance, 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total Liabilities</td>
                    <td class="text-right">{{ number_format($liabilities->sum('balance'), 2) }}</td>
                </tr>
            </table>

            <h2 class="section-title" style="margin-top: 30px;">Equity</h2>
            <table class="data-table">
                @foreach($equity as $eq)
                <tr>
                    <td>{{ $eq->name }}</td>
                    <td class="text-right">{{ number_format($eq->balance, 2) }}</td>
                </tr>
                @endforeach
                <tr class="profit-row">
                    <td>Retained Earnings (Net Profit)</td>
                    <td class="text-right">{{ number_format($netProfit, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total Liabilities & Equity</td>
                    <td class="text-right double-underline">
                        {{ number_format($liabilities->sum('balance') + $equity->sum('balance') + $netProfit, 2) }}
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
@endsection

@section('raw-script')
    <script>
        $(document).ready(function() {
            // Your custom scripts if needed
        });
    </script>
@endsection