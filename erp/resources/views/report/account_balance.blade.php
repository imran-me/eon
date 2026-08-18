@extends('layout.app')

@section('meta-information')
    <title>Account Balances Summary</title>
@endsection

@section('css')
<style>
    /* Scoped CSS to prevent conflicts */
    .acc-bal-container { font-family: 'Inter', sans-serif; padding: 25px; background: #fff; color: #333; }
    .acc-bal-container .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 15px; }
    .acc-bal-container .title-area h2 { margin: 0; font-size: 22px; color: #1a202c; }
    
    .acc-bal-container .filter-bar { display: flex; align-items: center; gap: 10px; }
    .acc-bal-container .filter-bar input { padding: 8px; border: 1px solid #cbd5e0; border-radius: 5px; }
    .acc-bal-container .btn-refresh { background: #4a5568; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; }

    .acc-bal-container .bal-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .acc-bal-container .bal-table th { background: #f7fafc; text-align: left; padding: 12px; border-bottom: 2px solid #edf2f7; font-size: 13px; text-transform: uppercase; color: #718096; }
    .acc-bal-container .bal-table td { padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; }
    
    .acc-bal-container .type-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
    .acc-bal-container .badge-asset { background: #e3f2fd; color: #1976d2; }
    .acc-bal-container .badge-liability { background: #fff3e0; color: #f57c00; }
    .acc-bal-container .badge-equity { background: #f3e5f5; color: #7b1fa2; }
    .acc-bal-container .badge-income { background: #e8f5e9; color: #388e3c; }
    .acc-bal-container .badge-expense { background: #ffebee; color: #d32f2f; }

    .acc-bal-container .text-right { text-align: right; }
    .acc-bal-container .positive-bal { color: #2d3748; font-weight: 600; }
    .acc-bal-container .negative-bal { color: #e53e3e; font-weight: 600; }
    
    .acc-bal-container tr:hover { background-color: #f9fafb; }
</style>
@endsection

@section('main-content')
<div class="acc-bal-container">
    <div class="header-flex">
        <div class="title-area">
            <h2>Account Balances Summary</h2>
            <small>Current standing as of {{ date('d M Y', strtotime($date)) }}</small>
        </div>
        <form action="" method="GET" class="filter-bar">
            <input type="date" name="date" value="{{ $date }}">
            <button type="submit" class="btn-refresh">Filter Date</button>
        </form>
    </div>

    <table class="bal-table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Account Name</th>
                <th>Type</th>
                <th class="text-right">Current Balance</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @foreach($report as $row)
                @php $grandTotal += $row->balance; @endphp
                <tr>
                    <td><code>{{ $row->code }}</code></td>
                    <td>{{ $row->name }}</td>
                    <td>
                        <span class="type-badge badge-{{ $row->type }}">
                            {{ $row->type }}
                        </span>
                    </td>
                    <td class="text-right {{ $row->balance < 0 ? 'negative-bal' : 'positive-bal' }}">
                        {{ number_format($row->balance, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($report->isEmpty())
        <div style="padding: 40px; text-align: center; color: #a0aec0;">
            No account balances found for this date.
        </div>
    @endif
</div>
@endsection