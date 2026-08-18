@extends('layout.app')
@section('meta-information')
    <title>Employee Request Report</title>
@endsection
@section('css')
<style>
    .status-pending      { background:#fef3c7; color:#92400e; }
    .status-under_review { background:#dbeafe; color:#1e40af; }
    .status-approved     { background:#d1fae5; color:#065f46; }
    .status-rejected     { background:#fee2e2; color:#991b1b; }
    .status-fulfilled    { background:#e0f2fe; color:#0c4a6e; }
    .status-closed       { background:#f3f4f6; color:#4b5563; }
</style>
@endsection
@section('main-content')

@php $role = Str::slug(Auth::user()->getRoleNames()->first()); @endphp

{{-- ── Page header ──────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
        <i class="fas fa-chart-bar text-blue-500"></i>
        Request &amp; Requirement Report
    </h1>
    <a href="{{ route('role.employee-requests.index', ['role' => $role]) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200
              text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to List
    </a>
</div>

{{-- ── Filter bar ───────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-5 mb-6">
    <form method="get" action="" class="flex flex-wrap items-end gap-4">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Date From</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}"
                   class="text-sm rounded-lg border border-gray-200 px-3 py-2 focus:outline-none
                          focus:ring-2 focus:ring-blue-300 text-gray-700">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Date To</label>
            <input type="date" name="date_to" value="{{ $dateTo }}"
                   class="text-sm rounded-lg border border-gray-200 px-3 py-2 focus:outline-none
                          focus:ring-2 focus:ring-blue-300 text-gray-700">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Category</label>
            <select name="category"
                    class="text-sm rounded-lg border border-gray-200 px-3 py-2 focus:outline-none
                           focus:ring-2 focus:ring-blue-300 text-gray-700 bg-white">
                <option value="">All</option>
                <option value="request" {{ request('category') === 'request' ? 'selected' : '' }}>📤 Request</option>
                <option value="require" {{ request('category') === 'require' ? 'selected' : '' }}>📥 Require</option>
            </select>
        </div>
        <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700
                       text-white text-sm font-semibold transition-colors shadow-sm">
            <i class="fas fa-search text-xs"></i> Apply
        </button>
    </form>
</div>

{{-- ── Summary stat cards ───────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">

    <div class="bg-white rounded-2xl border-l-4 border-blue-500 px-5 py-4 shadow-sm">
        <div class="text-3xl font-bold text-gray-800 leading-none">{{ $summary['total'] }}</div>
        <div class="text-xs text-gray-400 mt-1.5 font-medium">Total Records</div>
    </div>
    <div class="bg-white rounded-2xl border-l-4 border-amber-400 px-5 py-4 shadow-sm">
        <div class="text-3xl font-bold text-gray-800 leading-none">{{ $summary['pending'] }}</div>
        <div class="text-xs text-gray-400 mt-1.5 font-medium">Pending</div>
    </div>
    <div class="bg-white rounded-2xl border-l-4 border-green-500 px-5 py-4 shadow-sm">
        <div class="text-3xl font-bold text-gray-800 leading-none">{{ $summary['approved'] }}</div>
        <div class="text-xs text-gray-400 mt-1.5 font-medium">Approved</div>
    </div>
    <div class="bg-white rounded-2xl border-l-4 border-cyan-500 px-5 py-4 shadow-sm">
        <div class="text-3xl font-bold text-gray-800 leading-none">{{ $summary['fulfilled'] }}</div>
        <div class="text-xs text-gray-400 mt-1.5 font-medium">Fulfilled</div>
    </div>
    <div class="bg-white rounded-2xl border-l-4 border-red-500 px-5 py-4 shadow-sm">
        <div class="text-3xl font-bold text-gray-800 leading-none">{{ $summary['rejected'] }}</div>
        <div class="text-xs text-gray-400 mt-1.5 font-medium">Rejected</div>
    </div>
    <div class="bg-white rounded-2xl border-l-4 border-slate-400 px-5 py-4 shadow-sm">
        <div class="text-3xl font-bold text-gray-800 leading-none">{{ $summary['closed'] }}</div>
        <div class="text-xs text-gray-400 mt-1.5 font-medium">Closed</div>
    </div>

</div>

{{-- ── By Status + By Category ─────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">

    {{-- By Status --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-bold text-gray-700">By Status</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs uppercase tracking-wide text-gray-400 bg-gray-50 border-b border-gray-100">
                        <th class="px-5 py-3 text-left font-semibold">Status</th>
                        <th class="px-5 py-3 text-left font-semibold">Count</th>
                        <th class="px-5 py-3 text-left font-semibold">Amount</th>
                        <th class="px-5 py-3 text-left font-semibold w-28">Share</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($byStatus as $row)
                    @php $pct = $summary['total'] > 0 ? round($row->total / $summary['total'] * 100) : 0; @endphp
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-5 py-3">
                            <span class="status-{{ $row->status }} inline-block px-2.5 py-0.5 rounded-md text-xs font-semibold">
                                {{ ucfirst(str_replace('_',' ',$row->status)) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 font-semibold text-gray-700">{{ $row->total }}</td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $row->total_amount > 0 ? number_format($row->total_amount, 2) : '—' }}
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-2 rounded-full bg-blue-500" style="width:{{ $pct }}%"></div>
                                </div>
                                <span class="text-xs text-gray-400 w-8 text-right">{{ $pct }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400 text-sm">No data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- By Category --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-bold text-gray-700">By Category</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs uppercase tracking-wide text-gray-400 bg-gray-50 border-b border-gray-100">
                        <th class="px-5 py-3 text-left font-semibold">Category</th>
                        <th class="px-5 py-3 text-left font-semibold">Count</th>
                        <th class="px-5 py-3 text-left font-semibold">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($byCategory as $row)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-5 py-3">
                            @if($row->category === 'request')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                    📤 Request
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                                    📥 Require
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 font-semibold text-gray-700">{{ $row->total }}</td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $row->total_amount > 0 ? number_format($row->total_amount, 2) : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-5 py-8 text-center text-gray-400 text-sm">No data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- ── By Request Type ──────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50">
        <h3 class="text-sm font-bold text-gray-700">By Request Type</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs uppercase tracking-wide text-gray-400 bg-gray-50 border-b border-gray-100">
                    <th class="px-5 py-3 text-left font-semibold">Type</th>
                    <th class="px-5 py-3 text-left font-semibold">Category</th>
                    <th class="px-5 py-3 text-left font-semibold">Count</th>
                    <th class="px-5 py-3 text-left font-semibold">Total Amount</th>
                    <th class="px-5 py-3 text-left font-semibold w-36">Share</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($byType as $row)
                @php
                    $pct     = $summary['total'] > 0 ? round($row->total / $summary['total'] * 100) : 0;
                    $barColor = $row->category === 'require' ? '#a855f7' : '#3b82f6';
                @endphp
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-5 py-3 text-gray-700 font-medium">
                        {{ \App\Models\EmployeeRequest::requestTypeLabel($row->request_type) }}
                    </td>
                    <td class="px-5 py-3">
                        @if($row->category === 'request')
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                📤 Request
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                                📥 Require
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3 font-semibold text-gray-700">{{ $row->total }}</td>
                    <td class="px-5 py-3 text-gray-600">
                        {{ $row->total_amount > 0 ? number_format($row->total_amount, 2) : '—' }}
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-2 rounded-full" style="width:{{ $pct }}%; background:{{ $barColor }}"></div>
                            </div>
                            <span class="text-xs text-gray-400 w-8 text-right">{{ $pct }}%</span>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">No data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
