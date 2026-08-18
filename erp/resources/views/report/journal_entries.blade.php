@extends('layout.app')

@section('meta-information')
    <title>Journal Entry Report</title>
@endsection

@section('css')
<style>
    .jr-report-container { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 20px; color: #333; }
    .jr-report-container .filter-card { background: #f8f9fa; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
    .jr-report-container .filter-card form { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
    .jr-report-container .filter-group { display: flex; flex-direction: column; gap: 5px; }
    .jr-report-container label { font-size: 12px; font-weight: bold; color: #4a5568; }
    .jr-report-container input, .jr-report-container select { padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px; }
    .jr-report-container .btn-search { background: #3182ce; color: white; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; }

    .jr-report-container .entry-block { border: 1px solid #e2e8f0; margin-bottom: 20px; border-radius: 6px; overflow: hidden; background: #fff; }
    .jr-report-container .entry-header { background: #edf2f7; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
    .jr-report-container .entry-meta { font-size: 13px; }
    .jr-report-container .source-badge { background: #4a5568; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; text-transform: uppercase; }
    
    .jr-report-container .entry-table { width: 100%; border-collapse: collapse; }
    .jr-report-container .entry-table th { text-align: left; padding: 10px 15px; font-size: 12px; color: #718096; border-bottom: 1px solid #edf2f7; text-transform: uppercase; }
    .jr-report-container .entry-table td { padding: 10px 15px; font-size: 14px; border-bottom: 1px solid #f7fafc; }
    .jr-report-container .text-right { text-align: right; }
    
    .jr-report-container .entry-footer { padding: 10px 15px; background: #fff; font-size: 13px; color: #718096; font-style: italic; }
    .jr-report-container .total-row { font-weight: bold; background: #fafafa; }

    @media print {
        .jr-report-container .filter-card { display: none; }
        .jr-report-container .entry-block { break-inside: avoid; border: 1px solid #000; }
    }
</style>
@endsection

@section('main-content')
<div class="jr-report-container">
    <div class="filter-card">
        <form action="" method="GET">
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="from_date" value="{{ $fromDate }}">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="to_date" value="{{ $toDate }}">
            </div>
            <div class="filter-group">
                <label>Source</label>
                <select name="source">
                    <option value="">All Sources</option>
                    <option value="sale" {{ request('source') == 'sale' ? 'selected' : '' }}>Sale</option>
                    <option value="purchase" {{ request('source') == 'purchase' ? 'selected' : '' }}>Purchase</option>
                    <option value="expense" {{ request('source') == 'expense' ? 'selected' : '' }}>Expense</option>
                    <option value="salary" {{ request('source') == 'salary' ? 'selected' : '' }}>Salary</option>
                </select>
            </div>
            <button type="submit" class="btn-search">Filter Records</button>
        </form>
    </div>

    @forelse($entries as $entry)
    <div class="entry-block">
        <div class="entry-header">
            <div class="entry-meta">
                <strong>Date:</strong> {{ date('d-m-Y', strtotime($entry->date)) }} | 
                <strong>Ref:</strong> {{ $entry->reference ?? 'N/A' }} |
                <span class="source-badge">{{ $entry->source }}</span>
            </div>
            <div class="entry-meta">
                <strong>ID:</strong> #{{ $entry->id }}
            </div>
        </div>
        
        <table class="entry-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Account</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                @php $tDebit = 0; $tCredit = 0; @endphp
                @foreach($entry->journalItems as $item)
                @php 
                    $tDebit += $item->debit; 
                    $tCredit += $item->credit; 
                @endphp
                <tr>
                    <td>
                        <strong>{{ $item->account->name }}</strong><br>
                        <small style="color: #a0aec0;">{{ $item->note }}</small>
                    </td>
                    <td class="text-right">{{ $item->debit > 0 ? number_format($item->debit, 2) : '-' }}</td>
                    <td class="text-right">{{ $item->credit > 0 ? number_format($item->credit, 2) : '-' }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td class="text-right">Total:</td>
                    <td class="text-right">{{ number_format($tDebit, 2) }}</td>
                    <td class="text-right">{{ number_format($tCredit, 2) }}</td>
                </tr>
            </tbody>
        </table>
        
        @if($entry->description)
        <div class="entry-footer">
            Note: {{ $entry->description }}
        </div>
        @endif
    </div>
    @empty
    <div style="text-align: center; padding: 50px; color: #a0aec0; background: #f8f9fa; border-radius: 8px;">
        <h3>No journal entries found for the selected period.</h3>
    </div>
    @endforelse
</div>
@endsection