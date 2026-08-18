@extends('layout.app')

@section('meta-information')
    <title>Trial Balance</title>
@endsection

@section('css')
<style>
    .tb-container { font-family: 'Courier New', Courier, monospace; padding: 40px; background: #fff; color: #000; border: 1px solid #ccc; max-width: 1000px; margin: 20px auto; }
    .tb-container .tb-header { text-align: center; border-bottom: 2px solid #000; margin-bottom: 30px; padding-bottom: 10px; }
    .tb-container .tb-header h1 { margin: 5px 0; font-size: 24px; text-transform: uppercase; }
    
    .tb-container .filter-box { background: #f9f9f9; padding: 15px; margin-bottom: 20px; border: 1px dashed #999; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .tb-container .filter-box input { padding: 5px; border: 1px solid #000; }
    .tb-container .btn-go { background: #000; color: #fff; border: none; padding: 6px 15px; cursor: pointer; text-transform: uppercase; font-weight: bold; }

    .tb-container .tb-table { width: 100%; border-collapse: collapse; }
    .tb-container .tb-table th { border-top: 2px solid #000; border-bottom: 1px solid #000; padding: 10px; text-align: left; font-weight: bold; }
    .tb-container .tb-table td { padding: 8px 10px; border-bottom: 1px dotted #eee; }
    
    .tb-container .text-right { text-align: right; }
    .tb-container .total-row td { border-top: 2px solid #000; font-weight: bold; padding-top: 15px; }
    .tb-container .double-underline { border-bottom: 4px double #000; display: inline-block; min-width: 100px; padding-bottom: 2px; }

    @media print {
        .tb-container .filter-box { display: none; }
        .tb-container { border: none; width: 100%; max-width: 100%; padding: 0; }
    }
</style>
@endsection

@section('main-content')
<div class="tb-container">
    <div class="tb-header">
        <h1>Trial Balance</h1>
        <p>As of {{ date('d F Y', strtotime($date)) }}</p>
    </div>

    <form action="" method="GET" class="filter-box">
        <label>Report Date:</label>
        <input type="date" name="date" value="{{ $date }}">
        <button type="submit" class="btn-go">Generate</button>
    </form>

    <table class="tb-table">
        <thead>
            <tr>
                <th style="width: 15%;">Code</th>
                <th style="width: 45%;">Account Name</th>
                <th class="text-right" style="width: 20%;">Debit</th>
                <th class="text-right" style="width: 20%;">Credit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report as $row)
            <tr>
                <td>{{ $row->code }}</td>
                <td>{{ $row->name }}</td>
                <td class="text-right">{{ $row->debit > 0 ? number_format($row->debit, 2) : '-' }}</td>
                <td class="text-right">{{ $row->credit > 0 ? number_format($row->credit, 2) : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTALS</td>
                <td class="text-right">
                    <span class="double-underline">{{ number_format($report->sum('debit'), 2) }}</span>
                </td>
                <td class="text-right">
                    <span class="double-underline">{{ number_format($report->sum('credit'), 2) }}</span>
                </td>
            </tr>
        </tfoot>
    </table>

    @if($report->sum('debit') != $report->sum('credit'))
    <div style="margin-top: 20px; color: #d32f2f; font-weight: bold; text-align: center; border: 1px solid #d32f2f; padding: 10px;">
        WARNING: Trial Balance is out of balance by {{ number_format(abs($report->sum('debit') - $report->sum('credit')), 2) }}
    </div>
    @endif
</div>
@endsection